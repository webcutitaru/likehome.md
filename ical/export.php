<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';

/**
 * RFC 5545 TEXT escaping for SUMMARY/DESCRIPTION.
 */
function lh_ical_export_escape_text(string $s): string
{
    $s = str_replace('\\', '\\\\', $s);
    $s = str_replace(["\r\n", "\r", "\n"], '\n', $s);
    $s = str_replace(';', '\;', $s);
    $s = str_replace(',', '\,', $s);

    return $s;
}

/**
 * Emit one property line with octet folding (75 max per segment).
 */
function lh_ical_export_emit_line(string $name, string $value): void
{
    $line = $name . ':' . $value;
    $first = true;
    while ($line !== '') {
        $limit = $first ? 75 : 74;
        $len = strlen($line);
        if ($len <= $limit) {
            echo $first ? $line : ("\r\n " . $line);
            echo "\r\n";
            return;
        }
        $chunk = substr($line, 0, $limit);
        $line = substr($line, $limit);
        echo $first ? $chunk : ("\r\n " . $chunk);
        $first = false;
    }
}

$token = $_GET['token'] ?? '';

if ($token === '') {
    http_response_code(404);
    exit('Invalid token');
}

$stmt = mysqli_prepare(
    $conn,
    'SELECT id, title FROM properties WHERE ical_export_token = ? LIMIT 1'
);

if ($stmt === false) {
    http_response_code(500);
    exit('Database error');
}

mysqli_stmt_bind_param($stmt, 's', $token);
mysqli_stmt_execute($stmt);
$property = lh_mysqli_stmt_fetch_assoc($stmt);
mysqli_stmt_close($stmt);

if (!$property) {
    http_response_code(404);
    exit('Property not found');
}

$property_id = (int) $property['id'];

$res = mysqli_query(
    $conn,
    'SELECT bd.start_date, bd.end_date, bd.source, bd.external_event_id, bd.notes,
            b.guest_name, b.guest_phone
     FROM blocked_dates bd
     LEFT JOIN bookings b
       ON b.property_id = bd.property_id
      AND b.check_in = bd.start_date
      AND b.check_out = bd.end_date
     WHERE bd.property_id = ' . $property_id . "
       AND bd.source IN ('direct_booking', 'manual_block')
     ORDER BY bd.start_date ASC"
);

if ($res === false) {
    http_response_code(500);
    exit('Database error');
}

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="calendar.ics"');

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//LIKEHOME//ICAL EXPORT//EN\r\n";
echo "CALSCALE:GREGORIAN\r\n";

while ($row = mysqli_fetch_assoc($res)) {
    $start = date('Ymd', strtotime((string) $row['start_date']));
    $end = date('Ymd', strtotime((string) $row['end_date']));

    $extId = trim((string) ($row['external_event_id'] ?? ''));
    if ($extId !== '' && strlen($extId) <= 200 && preg_match('/^[A-Za-z0-9._@-]+$/', $extId)) {
        $uid = strpos($extId, '@') !== false ? $extId : ($extId . '@likehome');
    } else {
        $uid = md5($property_id . $start . $end . ($row['source'] ?? '')) . '@likehome';
    }

    echo "BEGIN:VEVENT\r\n";
    lh_ical_export_emit_line('UID', lh_ical_export_escape_text($uid));

    lh_ical_export_emit_line('DTSTART;VALUE=DATE', $start);
    lh_ical_export_emit_line('DTEND;VALUE=DATE', $end);

    $title = (string) ($property['title'] ?? 'Property');

    if (($row['source'] ?? '') === 'direct_booking') {
        $guest = (string) ($row['guest_name'] ?? '');
        $phone = (string) ($row['guest_phone'] ?? '');
        $summary = 'Reserved - ' . $title;
        lh_ical_export_emit_line('SUMMARY', lh_ical_export_escape_text($summary));

        if ($guest !== '' || $phone !== '') {
            $desc = 'Booking via website';
            if ($guest !== '') {
                $desc .= ' | Guest: ' . $guest;
            }
            if ($phone !== '') {
                $desc .= ' | Phone: ' . $phone;
            }
            lh_ical_export_emit_line('DESCRIPTION', lh_ical_export_escape_text($desc));
        }
    } else {
        $summary = 'Manual Block - ' . $title;
        lh_ical_export_emit_line('SUMMARY', lh_ical_export_escape_text($summary));
        $notes = trim((string) ($row['notes'] ?? ''));
        if ($notes !== '') {
            lh_ical_export_emit_line('DESCRIPTION', lh_ical_export_escape_text($notes));
        }
    }

    echo "END:VEVENT\r\n";
}

echo "END:VCALENDAR\r\n";
