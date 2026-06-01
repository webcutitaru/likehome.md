<?php

declare(strict_types=1);

/**
 * Send reminder emails ~24h before guest check-in (booking check_in date + property check_in_start − 24h).
 *
 * cron-job.org — schedule GET every 15–60 minutes, for example:
 *   curl -fsS "https://YOURDOMAIN.tld/SITE_PATH/cron/checkin_reminder.php?key=YOUR_CHECKIN_REMINDER_SECRET"
 *
 * Replace YOURDOMAIN.tld, SITE_PATH (e.g. /likehome2 if the site lives in a subfolder — match SITE_BASE_PATH in .env),
 * and YOUR_CHECKIN_REMINDER_SECRET with CHECKIN_REMINDER_SECRET from .env.
 *
 * Prerequisites: run sql/migrations/001_pre_checkin_reminder.sql on the database.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/checkin_reminder_send.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$secret = lh_env('CHECKIN_REMINDER_SECRET', '');
$key = isset($_GET['key']) ? (string) $_GET['key'] : '';

if ($secret === '') {
    http_response_code(503);
    echo "CHECKIN_REMINDER_SECRET is not set in .env\n";
    exit;
}

if (!hash_equals($secret, $key)) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$pdo = getPDO();
$adminEmail = lh_booking_resolve_admin_notification_email();
$telegram_bot_token = defined('TELEGRAM_BOT_TOKEN') ? trim((string) TELEGRAM_BOT_TOKEN) : '';
$telegram_chat_id = defined('TELEGRAM_CHAT_ID') ? trim((string) TELEGRAM_CHAT_ID) : '';

$sql = <<<'SQL'
SELECT b.id AS booking_id, b.guest_name, b.guest_email, b.guest_phone, b.check_in, b.check_out, b.guests, b.total_price, b.locale,
       p.id AS property_id, p.title AS property_title, p.check_in_start, p.check_in_end, p.check_out_end,
       p.address, p.city, p.district, p.floor,
       p.pre_checkin_email_message
FROM bookings b
INNER JOIN properties p ON p.id = b.property_id
WHERE b.status = 'confirmed'
  AND b.checkin_reminder_sent_at IS NULL
  AND b.check_in >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
  AND b.check_in <= DATE_ADD(CURDATE(), INTERVAL 5 DAY)
ORDER BY b.check_in ASC, b.id ASC
SQL;

$stmt = $pdo->query($sql);
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$now = new DateTimeImmutable('now');

$processed = 0;
$sent = 0;
$skipped = 0;
$errors = 0;

$sendOpts = [
    'enforce_24h_window' => true,
    'admin_email' => $adminEmail,
    'telegram_bot_token' => $telegram_bot_token,
    'telegram_chat_id' => $telegram_chat_id,
    'sent_at_update_mode' => 'if_null',
    'log_context' => 'checkin_reminder',
];

foreach ($rows as $row) {
    ++$processed;
    $out = lh_checkin_reminder_send_for_booking_row($pdo, $row, $now, $sendOpts);
    if ($out['result'] === 'sent') {
        ++$sent;
    } elseif ($out['result'] === 'skipped') {
        ++$skipped;
    } else {
        ++$errors;
    }
}

echo "LikeHome check-in reminder\n";
echo "candidates: {$processed}, sent: {$sent}, skipped (not due or past check-in): {$skipped}, errors: {$errors}\n";
