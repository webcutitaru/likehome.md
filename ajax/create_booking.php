<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/booking_pricing.php';
require_once __DIR__ . '/../includes/coupons.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/booking_locale.php';
require_once __DIR__ . '/../includes/booking_payment.php';
require_once __DIR__ . '/../includes/booking_confirm.php';
require_once __DIR__ . '/../includes/maib_client.php';

$bookingLocale = lh_resolve_request_locale();

function fail($message, $code = 400, array $replace = []) {
    global $bookingLocale;
    if (is_string($message) && str_starts_with($message, 'api.')) {
        $message = lh_translate($message, $replace, $bookingLocale ?? lh_default_locale());
    }
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function fail_booking_tx(PDO $pdo, string $message, int $code = 400, array $replace = []): void {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fail($message, $code, $replace);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('api.method_invalid', 405);
}

if (!lh_csrf_verify_post()) {
    fail('api.session_invalid', 403);
}

$honeypot = trim((string) ($_POST['company'] ?? ''));
if ($honeypot !== '') {
    fail('api.request_invalid', 400);
}

$termsAccepted = filter_var($_POST['terms_accepted'] ?? false, FILTER_VALIDATE_BOOLEAN);
if (!$termsAccepted) {
    fail('api.terms_required', 400);
}

$payment_method = strtolower(trim((string) ($_POST['payment_method'] ?? 'on_site')));
if (!lh_booking_payment_method_valid($payment_method)) {
    fail('api.payment_method_invalid', 400);
}

$property_id = filter_input(INPUT_POST, 'property_id', FILTER_VALIDATE_INT);
$guest_name  = trim($_POST['guest_name'] ?? '');
$guest_phone = trim($_POST['guest_phone'] ?? '');
$guest_email = trim($_POST['guest_email'] ?? '');
$check_in    = trim($_POST['check_in'] ?? '');
$check_out   = trim($_POST['check_out'] ?? '');
$guests      = filter_input(INPUT_POST, 'guests', FILTER_VALIDATE_INT);
$coupon_code_raw = trim((string) ($_POST['coupon_code'] ?? ''));

if (!$property_id) {
    fail('api.property_invalid');
}

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0';
$rateMax = (int) lh_env('BOOKING_RATE_LIMIT_MAX', '10');
$rateWindow = (int) lh_env('BOOKING_RATE_LIMIT_WINDOW', '900');
if ($rateMax > 0 && $rateWindow > 0) {
    $bucket = 'booking:' . $clientIp;
    if (lh_rate_limit_exceeded($bucket, $rateMax, $rateWindow)) {
        fail('api.rate_limit', 429);
    }
    $propBucket = 'booking:' . $clientIp . ':p' . $property_id;
    $perPropMax = (int) lh_env('BOOKING_RATE_LIMIT_PER_PROPERTY_MAX', '0');
    $perPropWindow = (int) lh_env('BOOKING_RATE_LIMIT_PER_PROPERTY_WINDOW', (string) $rateWindow);
    if ($perPropMax > 0 && $perPropWindow > 0 && lh_rate_limit_exceeded($propBucket, $perPropMax, $perPropWindow)) {
        fail('api.rate_limit_property', 429);
    }
}

if ($guest_name === '') {
    fail('api.name_required');
}
if ($guest_phone === '') {
    fail('api.phone_required');
}
if (!filter_var($guest_email, FILTER_VALIDATE_EMAIL)) {
    fail('api.email_invalid');
}
if (!$guests || $guests < 1) {
    fail('api.guests_invalid');
}

$checkInDt  = DateTime::createFromFormat('Y-m-d', $check_in);
$checkOutDt = DateTime::createFromFormat('Y-m-d', $check_out);

if (!$checkInDt || $checkInDt->format('Y-m-d') !== $check_in) {
    fail('api.checkin_invalid');
}
if (!$checkOutDt || $checkOutDt->format('Y-m-d') !== $check_out) {
    fail('api.checkout_invalid');
}
if ($checkOutDt <= $checkInDt) {
    fail('api.dates_order');
}

try {
    $pdo = getPDO();
    $hasPaymentCols = lh_bookings_has_payment_columns($pdo);

    if ($payment_method === 'online' && (!$hasPaymentCols || !lh_maib_configured())) {
        fail('api.online_payment_unavailable', 503);
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM properties WHERE id = :id AND is_active = 1 FOR UPDATE');
    $stmt->execute([':id' => $property_id]);
    $property = $stmt->fetch();

    if (!$property) {
        fail_booking_tx($pdo, 'api.property_not_found', 404);
    }

    if (!empty($property['sleep_capacity']) && $guests > (int) $property['sleep_capacity']) {
        fail_booking_tx($pdo, 'api.guests_over_capacity');
    }

    $nightsForMin = (int) $checkOutDt->diff($checkInDt)->days;
    $effMinStay = lh_booking_effective_min_stay($property, $check_in, $check_out);
    if ($nightsForMin < $effMinStay) {
        fail_booking_tx($pdo, 'api.min_stay', 400, ['n' => (string) $effMinStay]);
    }

    $lockBlocks = $pdo->prepare('SELECT id FROM blocked_dates WHERE property_id = :property_id FOR UPDATE');
    $lockBlocks->execute([':property_id' => $property_id]);

    $overlap = $pdo->prepare("
        SELECT COUNT(*)
        FROM blocked_dates
        WHERE property_id = :property_id
          AND start_date < :check_out
          AND end_date > :check_in
    ");
    $overlap->execute([
        ':property_id' => $property_id,
        ':check_in'    => $check_in,
        ':check_out'   => $check_out,
    ]);

    if ((int) $overlap->fetchColumn() > 0) {
        fail_booking_tx($pdo, 'api.period_unavailable');
    }

    $nights = $checkOutDt->diff($checkInDt)->days;
    if ($nights < 1) {
        fail_booking_tx($pdo, 'api.min_one_night');
    }

    $pricing = lh_booking_stay_total($property, $check_in, $check_out, (int) $guests);
    $subtotal = (float) $pricing['total'];

    $coupon_id_ins = null;
    $coupon_code_ins = null;
    $coupon_discount_ins = 0.0;

    if (lh_coupon_normalize_code($coupon_code_raw) !== '') {
        $resolved = lh_coupon_resolve_for_booking(
            $pdo,
            $coupon_code_raw,
            (int) $property_id,
            $check_in,
            (float) $pricing['base_nights_total'],
            true
        );
        if ($resolved['error'] !== null) {
            fail_booking_tx($pdo, lh_coupon_translate_error((string) $resolved['error'], $bookingLocale), 400);
        }
        $cRow = $resolved['coupon'];
        if ($cRow !== null) {
            $coupon_id_ins = (int) ($cRow['id'] ?? 0);
            $coupon_code_ins = lh_coupon_normalize_code($coupon_code_raw);
            $coupon_discount_ins = (float) $resolved['discount'];
            $subtotal = max(0.0, $subtotal - $coupon_discount_ins);
        }
    }

    $payTotals = lh_booking_payment_totals($subtotal);
    $total_price = $payTotals['on_site_total'];
    $payment_due = $payment_method === 'online' ? $payTotals['online_total'] : $total_price;

    if ($payment_due < 0.01) {
        fail_booking_tx($pdo, 'api.payment_amount_invalid');
    }

    $bookingStatus = $payment_method === 'online' ? 'pending' : 'confirmed';
    $paymentStatus = $payment_method === 'online' ? 'pending' : 'pay_at_property';
    $ttlMinutes = lh_booking_pending_ttl_minutes();
    $expiresAt = (new DateTimeImmutable('now'))->modify('+' . $ttlMinutes . ' minutes')->format('Y-m-d H:i:s');

    if ($hasPaymentCols) {
        $insertBooking = $pdo->prepare(
            lh_bookings_has_locale_column($pdo)
                ? "INSERT INTO bookings (
                property_id, guest_name, guest_phone, guest_email,
                check_in, check_out, guests, total_price,
                coupon_id, coupon_code, coupon_discount_amount,
                status, payment_method, payment_status,
                online_discount_percent, online_discount_amount,
                payment_due_amount, payment_expires_at, locale
            ) VALUES (
                :property_id, :guest_name, :guest_phone, :guest_email,
                :check_in, :check_out, :guests, :total_price,
                :coupon_id, :coupon_code, :coupon_discount_amount,
                :status, :payment_method, :payment_status,
                :online_discount_percent, :online_discount_amount,
                :payment_due_amount, :payment_expires_at, :locale
            )"
                : "INSERT INTO bookings (
                property_id, guest_name, guest_phone, guest_email,
                check_in, check_out, guests, total_price,
                coupon_id, coupon_code, coupon_discount_amount,
                status, payment_method, payment_status,
                online_discount_percent, online_discount_amount,
                payment_due_amount, payment_expires_at
            ) VALUES (
                :property_id, :guest_name, :guest_phone, :guest_email,
                :check_in, :check_out, :guests, :total_price,
                :coupon_id, :coupon_code, :coupon_discount_amount,
                :status, :payment_method, :payment_status,
                :online_discount_percent, :online_discount_amount,
                :payment_due_amount, :payment_expires_at
            )"
        );

        $insertParams = [
            ':property_id' => $property_id,
            ':guest_name'  => $guest_name,
            ':guest_phone' => $guest_phone,
            ':guest_email' => $guest_email,
            ':check_in'    => $check_in,
            ':check_out'   => $check_out,
            ':guests'      => $guests,
            ':total_price' => $total_price,
            ':coupon_id' => $coupon_id_ins,
            ':coupon_code' => $coupon_code_ins,
            ':coupon_discount_amount' => $coupon_discount_ins,
            ':status' => $bookingStatus,
            ':payment_method' => $payment_method,
            ':payment_status' => $paymentStatus,
            ':online_discount_percent' => $payTotals['online_discount_percent'],
            ':online_discount_amount' => $payTotals['online_discount_amount'],
            ':payment_due_amount' => $payment_due,
            ':payment_expires_at' => $payment_method === 'online' ? $expiresAt : null,
        ];
    } else {
        $insertBooking = $pdo->prepare(
            lh_bookings_has_locale_column($pdo)
                ? "INSERT INTO bookings (
                property_id, guest_name, guest_phone, guest_email,
                check_in, check_out, guests, total_price,
                coupon_id, coupon_code, coupon_discount_amount,
                status, locale
            ) VALUES (
                :property_id, :guest_name, :guest_phone, :guest_email,
                :check_in, :check_out, :guests, :total_price,
                :coupon_id, :coupon_code, :coupon_discount_amount,
                'confirmed', :locale
            )"
                : "INSERT INTO bookings (
                property_id, guest_name, guest_phone, guest_email,
                check_in, check_out, guests, total_price,
                coupon_id, coupon_code, coupon_discount_amount, status
            ) VALUES (
                :property_id, :guest_name, :guest_phone, :guest_email,
                :check_in, :check_out, :guests, :total_price,
                :coupon_id, :coupon_code, :coupon_discount_amount, 'confirmed'
            )"
        );

        $insertParams = [
            ':property_id' => $property_id,
            ':guest_name'  => $guest_name,
            ':guest_phone' => $guest_phone,
            ':guest_email' => $guest_email,
            ':check_in'    => $check_in,
            ':check_out'   => $check_out,
            ':guests'      => $guests,
            ':total_price' => $total_price,
            ':coupon_id' => $coupon_id_ins,
            ':coupon_code' => $coupon_code_ins,
            ':coupon_discount_amount' => $coupon_discount_ins,
        ];
    }

    if (lh_bookings_has_locale_column($pdo)) {
        $insertParams[':locale'] = $bookingLocale;
    }

    $insertBooking->execute($insertParams);
    $booking_id = (int) $pdo->lastInsertId();

    $blockSource = $payment_method === 'online' ? 'pending_payment' : 'direct_booking';
    $insertBlock = $pdo->prepare("
        INSERT INTO blocked_dates (
            property_id, start_date, end_date, source, external_event_id, notes
        ) VALUES (
            :property_id, :start_date, :end_date, :source, :external_event_id, :notes
        )
    ");
    $insertBlock->execute([
        ':property_id'       => $property_id,
        ':start_date'        => $check_in,
        ':end_date'          => $check_out,
        ':source'            => $blockSource,
        ':external_event_id' => 'booking-' . $booking_id,
        ':notes'             => 'Booking #' . $booking_id,
    ]);

    $checkout_url = null;
    $checkout_id = null;

    if ($payment_method === 'online' && $hasPaymentCols) {
        $property_title = $property['title'] ?? ('Property #' . $property_id);
        if (function_exists('lh_property_apply_locale')) {
            $propertyLocalized = lh_property_apply_locale($property, $pdo, $bookingLocale);
            $property_title = $propertyLocalized['title'] ?? $property_title;
        }

        $urls = lh_maib_payment_urls($bookingLocale);
        $orderId = 'LH-' . $booking_id;
        $checkoutPayload = [
            'amount' => $payment_due,
            'currency' => lh_currency_code(),
            'callbackUrl' => $urls['callbackUrl'],
            'successUrl' => $urls['successUrl'],
            'failUrl' => $urls['failUrl'],
            'language' => lh_maib_checkout_language($bookingLocale),
            'orderInfo' => [
                'id' => $orderId,
                'description' => mb_substr($property_title . ' · ' . $check_in . ' → ' . $check_out, 0, 120),
                'date' => (new DateTimeImmutable('now'))->format('c'),
                'orderAmount' => $payment_due,
                'orderCurrency' => lh_currency_code(),
            ],
            'payerInfo' => [
                'name' => $guest_name,
                'email' => $guest_email,
                'phone' => preg_replace('/\D+/', '', $guest_phone),
                'ip' => $clientIp,
                'userAgent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
            ],
        ];

        try {
            $checkout = lh_maib_create_checkout($checkoutPayload);
            $checkout_id = $checkout->checkoutId;
            $checkout_url = $checkout->checkoutUrl;

            $pdo->prepare('UPDATE bookings SET maib_checkout_id = ? WHERE id = ?')
                ->execute([$checkout_id, $booking_id]);
        } catch (Throwable $e) {
            error_log('create_booking maib error: ' . $e->getMessage());
            fail_booking_tx($pdo, 'api.payment_init_failed', 502);
        }
    }

    $pdo->commit();

    if ($payment_method === 'on_site') {
        $stmtFresh = $pdo->prepare('SELECT * FROM bookings WHERE id = ? LIMIT 1');
        $stmtFresh->execute([$booking_id]);
        $bookingRow = $stmtFresh->fetch(PDO::FETCH_ASSOC) ?: [];

        lh_booking_send_confirmation_notifications($pdo, $bookingRow, $property, $bookingLocale, false);

        echo json_encode([
            'success' => true,
            'message' => lh_translate('api.booking_success', [], $bookingLocale),
            'booking_id' => $booking_id,
            'payment_method' => 'on_site',
            'total_price' => $total_price,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => lh_translate('api.payment_redirect', [], $bookingLocale),
        'booking_id' => $booking_id,
        'payment_method' => 'online',
        'checkout_url' => $checkout_url,
        'checkout_id' => $checkout_id,
        'payment_due_amount' => $payment_due,
        'online_discount_amount' => $payTotals['online_discount_amount'],
        'total_price' => $total_price,
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('create_booking error: ' . $e->getMessage());
    fail('api.server_error', 500);
}
