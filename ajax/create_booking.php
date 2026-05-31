<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');


require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/booking_pricing.php';
require_once __DIR__ . '/../includes/coupons.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/booking_notifications.php';
require_once __DIR__ . '/../includes/booking_guest_email_bodies.php';

$admin_notification_email = lh_booking_resolve_admin_notification_email();

$telegram_bot_token = defined('TELEGRAM_BOT_TOKEN') ? trim((string) TELEGRAM_BOT_TOKEN) : '';
$telegram_chat_id   = defined('TELEGRAM_CHAT_ID') ? trim((string) TELEGRAM_CHAT_ID) : '';

function fail($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function fail_booking_tx(PDO $pdo, string $message, int $code = 400): void {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fail($message, $code);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Metodă invalidă.', 405);
}

if (!lh_csrf_verify_post()) {
    fail('Sesiune invalidă. Reîncarcă pagina și încearcă din nou.', 403);
}

$honeypot = trim((string) ($_POST['company'] ?? ''));
if ($honeypot !== '') {
    fail('Cerere invalidă.', 400);
}

$property_id = filter_input(INPUT_POST, 'property_id', FILTER_VALIDATE_INT);
$guest_name  = trim($_POST['guest_name'] ?? '');
$guest_phone = trim($_POST['guest_phone'] ?? '');
$guest_email = trim($_POST['guest_email'] ?? '');
$check_in    = trim($_POST['check_in'] ?? '');
$check_out   = trim($_POST['check_out'] ?? '');
$guests      = filter_input(INPUT_POST, 'guests', FILTER_VALIDATE_INT);
$coupon_code_raw = trim((string) ($_POST['coupon_code'] ?? ''));

if (!$property_id) fail('Proprietate invalidă.');

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0';
$rateMax = (int) lh_env('BOOKING_RATE_LIMIT_MAX', '10');
$rateWindow = (int) lh_env('BOOKING_RATE_LIMIT_WINDOW', '900');
if ($rateMax > 0 && $rateWindow > 0) {
    $bucket = 'booking:' . $clientIp;
    if (lh_rate_limit_exceeded($bucket, $rateMax, $rateWindow)) {
        fail('Prea multe cereri de rezervare. Încearcă mai târziu.', 429);
    }
    $propBucket = 'booking:' . $clientIp . ':p' . $property_id;
    $perPropMax = (int) lh_env('BOOKING_RATE_LIMIT_PER_PROPERTY_MAX', '0');
    $perPropWindow = (int) lh_env('BOOKING_RATE_LIMIT_PER_PROPERTY_WINDOW', (string) $rateWindow);
    if ($perPropMax > 0 && $perPropWindow > 0 && lh_rate_limit_exceeded($propBucket, $perPropMax, $perPropWindow)) {
        fail('Prea multe cereri pentru această proprietate. Încearcă mai târziu.', 429);
    }
}

if ($guest_name === '') fail('Completează numele.');
if ($guest_phone === '') fail('Completează numărul de telefon.');
if (!filter_var($guest_email, FILTER_VALIDATE_EMAIL)) fail('Email invalid.');
if (!$guests || $guests < 1) fail('Număr invalid de oaspeți.');

$checkInDt  = DateTime::createFromFormat('Y-m-d', $check_in);
$checkOutDt = DateTime::createFromFormat('Y-m-d', $check_out);

if (!$checkInDt || $checkInDt->format('Y-m-d') !== $check_in) {
    fail('Check-in invalid.');
}
if (!$checkOutDt || $checkOutDt->format('Y-m-d') !== $check_out) {
    fail('Check-out invalid.');
}
if ($checkOutDt <= $checkInDt) {
    fail('Check-out trebuie să fie cel puțin în ziua următoare față de check-in (minim o noapte).');
}

try {
    $pdo = getPDO();

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM properties WHERE id = :id AND is_active = 1 FOR UPDATE');
    $stmt->execute([':id' => $property_id]);
    $property = $stmt->fetch();

    if (!$property) {
        fail_booking_tx($pdo, 'Proprietatea nu există.', 404);
    }

    if (!empty($property['sleep_capacity']) && $guests > (int)$property['sleep_capacity']) {
        fail_booking_tx($pdo, 'Numărul de oaspeți depășește capacitatea proprietății.');
    }

    $nightsForMin = (int) $checkOutDt->diff($checkInDt)->days;
    $effMinStay = lh_booking_effective_min_stay($property, $check_in, $check_out);
    if ($nightsForMin < $effMinStay) {
        fail_booking_tx($pdo, 'Sejurul minim este de ' . $effMinStay . ' nopți.');
    }

    $lockBlocks = $pdo->prepare(
        'SELECT id FROM blocked_dates WHERE property_id = :property_id FOR UPDATE'
    );
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

    if ((int)$overlap->fetchColumn() > 0) {
        fail_booking_tx($pdo, 'Perioada selectată nu mai este disponibilă.');
    }

    $nights = $checkOutDt->diff($checkInDt)->days;
    if ($nights < 1) {
        fail_booking_tx($pdo, 'Sejurul trebuie să includă cel puțin o noapte.');
    }
    $pricing = lh_booking_stay_total($property, $check_in, $check_out, (int) $guests);
    $total_price = $pricing['total'];

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
            fail_booking_tx($pdo, $resolved['error'], 400);
        }
        $cRow = $resolved['coupon'];
        if ($cRow !== null) {
            $coupon_id_ins = (int) ($cRow['id'] ?? 0);
            $coupon_code_ins = lh_coupon_normalize_code($coupon_code_raw);
            $coupon_discount_ins = (float) $resolved['discount'];
            $total_price = max(0.0, $total_price - $coupon_discount_ins);
        }
    }

    $insertBooking = $pdo->prepare("
        INSERT INTO bookings (
            property_id,
            guest_name,
            guest_phone,
            guest_email,
            check_in,
            check_out,
            guests,
            total_price,
            coupon_id,
            coupon_code,
            coupon_discount_amount,
            status
        ) VALUES (
            :property_id,
            :guest_name,
            :guest_phone,
            :guest_email,
            :check_in,
            :check_out,
            :guests,
            :total_price,
            :coupon_id,
            :coupon_code,
            :coupon_discount_amount,
            'confirmed'
        )
    ");

    $insertBooking->execute([
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
    ]);

    $booking_id = (int)$pdo->lastInsertId();

    $insertBlock = $pdo->prepare("
        INSERT INTO blocked_dates (
            property_id,
            start_date,
            end_date,
            source,
            external_event_id,
            notes
        ) VALUES (
            :property_id,
            :start_date,
            :end_date,
            'direct_booking',
            :external_event_id,
            :notes
        )
    ");

    $insertBlock->execute([
        ':property_id'       => $property_id,
        ':start_date'        => $check_in,
        ':end_date'          => $check_out,
        ':external_event_id' => 'booking-' . $booking_id,
        ':notes'             => 'Booking #' . $booking_id,
    ]);

    $pdo->commit();

    $property_title = $property['title'] ?? ('Property #' . $property_id);

    $admin_subject = 'Rezervare nouă #' . $booking_id . ' - ' . $property_title;
    $admin_message = "Ai primit o rezervare nouă pe site.\n\n"
        . "Booking ID: #" . $booking_id . "\n"
        . "Proprietate: " . $property_title . "\n"
        . "Nume client: " . $guest_name . "\n"
        . "Telefon: " . $guest_phone . "\n"
        . "Email: " . $guest_email . "\n"
        . "Check-in: " . $check_in . "\n"
        . "Check-out: " . $check_out . "\n"
        . "Oaspeți: " . $guests . "\n"
        . ($coupon_discount_ins > 0.004 && $coupon_code_ins !== null && trim((string) $coupon_code_ins) !== ''
            ? ('Reducere cupon «' . trim((string) $coupon_code_ins) . '»: ' . lh_format_money((float) $coupon_discount_ins, 2) . ' (din tariful nopților)' . "\n")
            : '')
        . 'Total: ' . lh_format_money((float) $total_price, 2) . "\n"
        . "Status: confirmată automat\n";

    if (!empty($admin_notification_email)) {
        $admin_sent = send_booking_notification($admin_notification_email, $admin_subject, $admin_message, $guest_email);
        if (!$admin_sent) {
            error_log('create_booking notification error: admin email could not be sent for booking #' . $booking_id);
        }
    } else {
        error_log('create_booking notification skipped: ADMIN_NOTIFICATION_EMAIL / SERVER_ADMIN not configured');
    }

    $telegram_message = "🔔 Rezervare nouă\n\n"
        . "Booking ID: #" . $booking_id . "\n"
        . "Proprietate: " . $property_title . "\n"
        . "Nume: " . $guest_name . "\n"
        . "Telefon: " . $guest_phone . "\n"
        . "Email: " . $guest_email . "\n"
        . "Check-in: " . $check_in . "\n"
        . "Check-out: " . $check_out . "\n"
        . "Oaspeți: " . $guests . "\n"
        . ($coupon_discount_ins > 0.004 && $coupon_code_ins !== null && trim((string) $coupon_code_ins) !== ''
            ? ('Reducere cupon «' . trim((string) $coupon_code_ins) . '»: ' . lh_format_money((float) $coupon_discount_ins, 2) . ' (din tariful nopților)' . "\n")
            : '')
        . 'Total: ' . lh_format_money((float) $total_price, 2) . "\n"
        . "Status: confirmată automat";

    if (!empty($telegram_bot_token) && !empty($telegram_chat_id)) {
        $telegram_sent = send_telegram_notification($telegram_bot_token, $telegram_chat_id, $telegram_message);
        if (!$telegram_sent) {
            error_log('create_booking notification error: telegram message could not be sent for booking #' . $booking_id);
        }
    } else {
        error_log('create_booking notification skipped: TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID not configured');
    }

    $client_subject = 'Confirmare rezervare — Like Home';
    $guestBodyCtx = [
        'guest_name' => $guest_name,
        'property_title' => $property_title,
        'check_in' => $check_in,
        'check_out' => $check_out,
        'guests' => (int) $guests,
        'total_price' => (float) $total_price,
        'booking_id' => (int) $booking_id,
    ];
    if ($coupon_discount_ins > 0.004 && $coupon_code_ins !== null && trim((string) $coupon_code_ins) !== '') {
        $guestBodyCtx['coupon_code'] = (string) $coupon_code_ins;
        $guestBodyCtx['coupon_discount_amount'] = (float) $coupon_discount_ins;
    }
    $client_message = lh_build_guest_booking_confirmation_body($guestBodyCtx);

    $client_sent = send_booking_notification($guest_email, $client_subject, $client_message, $admin_notification_email);
    if (!$client_sent) {
        error_log('create_booking notification error: client email could not be sent for booking #' . $booking_id);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Rezervarea a fost confirmată cu succes.',
        'booking_id' => $booking_id,
        'total_price' => $total_price,
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('create_booking error: ' . $e->getMessage());
    fail('A apărut o eroare. Încearcă din nou mai târziu.', 500);
}