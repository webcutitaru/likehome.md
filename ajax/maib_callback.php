<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/maib_client.php';
require_once __DIR__ . '/../includes/booking_confirm.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$rawBody = file_get_contents('php://input') ?: '';
$headers = function_exists('getallheaders') ? getallheaders() : [];
$norm = [];
foreach ($headers as $k => $v) {
    $norm[strtolower((string) $k)] = $v;
}

$xSignature = isset($norm['x-signature']) ? (string) $norm['x-signature'] : null;
$xTimestamp = isset($norm['x-signature-timestamp']) ? (string) $norm['x-signature-timestamp'] : null;

if (!lh_maib_verify_callback_signature($rawBody, $xSignature, $xTimestamp)) {
    error_log('maib_callback: invalid signature');
    http_response_code(401);
    echo 'Invalid signature';
    exit;
}

$data = json_decode($rawBody, true);
if (!is_array($data)) {
    http_response_code(400);
    echo 'Invalid JSON';
    exit;
}

$checkoutId = trim((string) ($data['checkoutId'] ?? ''));
$paymentStatus = trim((string) ($data['paymentStatus'] ?? ''));
$paymentId = trim((string) ($data['paymentId'] ?? ''));
$orderId = trim((string) ($data['orderId'] ?? ''));
$paymentAmount = (float) ($data['paymentAmount'] ?? $data['amount'] ?? 0);

$bookingId = 0;
if (preg_match('/^LH-(\d+)$/i', $orderId, $m)) {
    $bookingId = (int) $m[1];
}

try {
    $pdo = getPDO();

    if ($bookingId <= 0 && $checkoutId !== '') {
        $stmt = $pdo->prepare('SELECT id FROM bookings WHERE maib_checkout_id = ? LIMIT 1');
        $stmt->execute([$checkoutId]);
        $bookingId = (int) ($stmt->fetchColumn() ?: 0);
    }

    if ($bookingId <= 0) {
        error_log('maib_callback: booking not found for orderId=' . $orderId . ' checkoutId=' . $checkoutId);
        http_response_code(200);
        echo 'OK';
        exit;
    }

    if (strcasecmp($paymentStatus, 'Executed') === 0) {
        lh_booking_confirm_after_online_payment($pdo, $bookingId, $checkoutId, $paymentId !== '' ? $paymentId : null, $paymentAmount);
    } elseif (in_array(strtolower($paymentStatus), ['failed', 'cancelled', 'canceled'], true)) {
        lh_booking_fail_online_payment($pdo, $bookingId);
    }
} catch (Throwable $e) {
    error_log('maib_callback error: ' . $e->getMessage());
}

http_response_code(200);
echo 'OK';
