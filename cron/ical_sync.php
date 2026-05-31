<?php

declare(strict_types=1);

/**
 * Periodic iCal import for all properties with ical_import_link set.
 *
 * cPanel → Cron Jobs → example (every 30 minutes):
 *   curl -fsS "https://YOURDOMAIN.tld/SITE_PATH/cron/ical_sync.php?key=YOUR_ICAL_SYNC_SECRET"
 *
 * Replace YOURDOMAIN.tld, SITE_PATH (omit or use /likehome2 if site is in a subfolder),
 * and YOUR_ICAL_SYNC_SECRET with the value from .env (ICAL_SYNC_SECRET).
 *
 * Test once in the browser with the same ?key= before enabling cron.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/ical_importer.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$secret = lh_env('ICAL_SYNC_SECRET', '');
$key = isset($_GET['key']) ? (string) $_GET['key'] : '';

if ($secret === '') {
    http_response_code(503);
    echo "ICAL_SYNC_SECRET is not set in .env\n";
    exit;
}

if (!hash_equals($secret, $key)) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$pdo = getPDO();
$stmt = $pdo->query(
    "SELECT id FROM properties WHERE TRIM(COALESCE(ical_import_link, '')) <> '' ORDER BY id ASC"
);
$ids = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

if ($ids === false) {
    $ids = [];
}

$ok = 0;
$fail = 0;
$lines = [];

foreach ($ids as $pid) {
    $propertyId = (int) $pid;
    $result = importPropertyIcal($propertyId);
    if (!empty($result['success'])) {
        ++$ok;
        $lines[] = sprintf(
            'property %d: ok, imported %d',
            $propertyId,
            (int) ($result['imported'] ?? 0)
        );
    } else {
        ++$fail;
        $lines[] = sprintf(
            'property %d: fail — %s',
            $propertyId,
            (string) ($result['error'] ?? 'unknown')
        );
    }
}

$total = count($ids);
echo "LikeHome iCal sync\n";
echo "properties: {$total}, ok: {$ok}, failed: {$fail}\n";
if ($lines !== []) {
    echo "\n";
    echo implode("\n", $lines);
    echo "\n";
}
