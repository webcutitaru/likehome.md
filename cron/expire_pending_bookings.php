<?php

declare(strict_types=1);

/**
 * Expire unpaid online bookings (pending payment TTL).
 *
 * curl -fsS "http://localhost:8888/likehome.md/cron/expire_pending_bookings.php?key=YOUR_MAIB_PENDING_SECRET"
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/booking_confirm.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$secret = lh_env('MAIB_PENDING_CRON_SECRET', lh_env('ICAL_SYNC_SECRET', ''));
$key = isset($_GET['key']) ? (string) $_GET['key'] : '';

if ($secret === '') {
    http_response_code(503);
    echo "MAIB_PENDING_CRON_SECRET or ICAL_SYNC_SECRET is not set in .env\n";
    exit;
}

if (!hash_equals($secret, $key)) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$pdo = getPDO();
$stmt = $pdo->query("
    SELECT id, property_id
    FROM bookings
    WHERE status = 'pending'
      AND payment_method = 'online'
      AND payment_status = 'pending'
      AND payment_expires_at IS NOT NULL
      AND payment_expires_at < NOW()
");
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
$count = 0;

foreach ($rows as $row) {
    $id = (int) ($row['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }
    $pdo->prepare(
        "UPDATE bookings SET status = 'cancelled', payment_status = 'failed', cancelled_at = NOW(), payment_expires_at = NULL WHERE id = ?"
    )->execute([$id]);
    lh_booking_release_blocked_dates($pdo, (int) ($row['property_id'] ?? 0), $id);
    ++$count;
}

echo "LikeHome expire pending bookings\n";
echo "expired: {$count}\n";
