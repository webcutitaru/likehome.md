<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/booking_pricing.php';
require_once __DIR__ . '/../includes/coupons.php';
require_once __DIR__ . '/../includes/rate_limit.php';

function booking_preview_fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    booking_preview_fail('Metodă invalidă.', 405);
}

if (!lh_csrf_verify_post()) {
    booking_preview_fail('Sesiune invalidă. Reîncarcă pagina.', 403);
}

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0';
$rateMax = (int) lh_env('BOOKING_PRICE_PREVIEW_RATE_LIMIT_MAX', '40');
$rateWindow = (int) lh_env('BOOKING_PRICE_PREVIEW_RATE_LIMIT_WINDOW', '300');
if ($rateMax > 0 && $rateWindow > 0 && lh_rate_limit_exceeded('booking_preview:' . $clientIp, $rateMax, $rateWindow)) {
    booking_preview_fail('Prea multe cereri. Încearcă din nou în câteva minute.', 429);
}

$property_id = filter_input(INPUT_POST, 'property_id', FILTER_VALIDATE_INT);
$check_in = trim((string) ($_POST['check_in'] ?? ''));
$check_out = trim((string) ($_POST['check_out'] ?? ''));
$guests = filter_input(INPUT_POST, 'guests', FILTER_VALIDATE_INT);
$coupon_raw = trim((string) ($_POST['coupon_code'] ?? ''));

if (!$property_id) {
    booking_preview_fail('Proprietate invalidă.');
}
if (!$guests || $guests < 1) {
    booking_preview_fail('Număr invalid de oaspeți.');
}

$checkInDt = DateTime::createFromFormat('Y-m-d', $check_in);
$checkOutDt = DateTime::createFromFormat('Y-m-d', $check_out);
if (!$checkInDt || $checkInDt->format('Y-m-d') !== $check_in) {
    booking_preview_fail('Check-in invalid.');
}
if (!$checkOutDt || $checkOutDt->format('Y-m-d') !== $check_out) {
    booking_preview_fail('Check-out invalid.');
}
if ($checkOutDt <= $checkInDt) {
    booking_preview_fail('Selectează un interval cu cel puțin o noapte.');
}

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM properties WHERE id = ? AND is_active = 1');
    $stmt->execute([(int) $property_id]);
    $property = $stmt->fetch();
    if (!$property) {
        booking_preview_fail('Proprietate indisponibilă.', 404);
    }

    if (!empty($property['sleep_capacity']) && $guests > (int) $property['sleep_capacity']) {
        booking_preview_fail('Numărul de oaspeți depășește capacitatea.');
    }

    $effMinStay = lh_booking_effective_min_stay($property, $check_in, $check_out);
    $nightsForMin = (int) $checkOutDt->diff($checkInDt)->days;
    if ($nightsForMin < $effMinStay) {
        booking_preview_fail('Sejur minim: ' . $effMinStay . ' nopți.');
    }

    $pricing = lh_booking_stay_total($property, $check_in, $check_out, (int) $guests);
    $coupon_discount = 0.0;
    $coupon_error = null;

    if (lh_coupon_normalize_code($coupon_raw) !== '') {
        $r = lh_coupon_resolve_for_booking(
            $pdo,
            $coupon_raw,
            (int) $property_id,
            $check_in,
            (float) $pricing['base_nights_total'],
            false
        );
        if ($r['error'] !== null) {
            $coupon_error = $r['error'];
        } else {
            $coupon_discount = (float) $r['discount'];
        }
    }

    $total = max(0.0, (float) $pricing['total'] - $coupon_discount);

    echo json_encode([
        'success' => true,
        'nights' => (int) $pricing['nights'],
        'base_nights_total' => (float) $pricing['base_nights_total'],
        'extra_guest_total' => (float) $pricing['extra_guest_total'],
        'length_discount' => (float) $pricing['length_discount'],
        'total_before_coupon' => (float) $pricing['total'],
        'coupon_discount' => $coupon_discount,
        'coupon_error' => $coupon_error,
        'total' => $total,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('booking_price_preview error: ' . $e->getMessage());
    booking_preview_fail('Eroare la calcul. Încearcă din nou.', 500);
}
