<?php

declare(strict_types=1);

require_once __DIR__ . '/ical_fetch.php';

/**
 * RFC 5545: unfold content lines (merge line breaks + single space/tab).
 */
function lh_ical_unfold_ics(string $ics): string
{
    $ics = str_replace(["\r\n", "\r"], "\n", $ics);

    return (string) preg_replace('/\n[ \t]/', '', $ics);
}

/**
 * Parse one ICS DTSTART/DTEND value (date or date-time) into an instant.
 */
function lh_ical_parse_dt_value(string $params, string $value, string $defaultTz): ?DateTimeImmutable
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $paramsU = strtoupper($params);
    if (str_contains($paramsU, 'VALUE=DATE') || preg_match('/^\d{8}$/', $value)) {
        if (!preg_match('/^(\d{8})$/', $value, $m)) {
            return null;
        }

        try {
            $tz = new DateTimeZone($defaultTz);
        } catch (Exception $e) {
            $tz = new DateTimeZone('UTC');
        }

        $dt = DateTimeImmutable::createFromFormat('!Ymd', $m[1], $tz);

        return $dt ?: null;
    }

    $tzName = $defaultTz;
    if (preg_match('/TZID=([^;:]+)/i', $params, $tm)) {
        $tzName = trim($tm[1], " \"'");
        if ($tzName === '') {
            $tzName = $defaultTz;
        }
    }

    try {
        $tz = new DateTimeZone($tzName);
    } catch (Exception $e) {
        try {
            $tz = new DateTimeZone($defaultTz);
        } catch (Exception $e2) {
            $tz = new DateTimeZone('UTC');
        }
    }

    if (preg_match('/^(\d{8})T(\d{6})Z$/i', $value, $m)) {
        $dt = DateTimeImmutable::createFromFormat('!Ymd His', $m[1] . ' ' . $m[2], new DateTimeZone('UTC'));

        return $dt ?: null;
    }

    if (preg_match('/^(\d{8})T(\d{6})$/i', $value, $m)) {
        $dt = DateTimeImmutable::createFromFormat('!Ymd His', $m[1] . ' ' . $m[2], $tz);

        return $dt ?: null;
    }

    if (preg_match('/^(\d{8})T(\d{6})([+-]\d{4})$/i', $value, $m)) {
        $sign = $m[3][0] === '-' ? '-' : '+';
        $hm = substr($m[3], 1);
        if (strlen($hm) === 4) {
            $offset = $sign . substr($hm, 0, 2) . ':' . substr($hm, 2, 2);
            $dt = DateTimeImmutable::createFromFormat('!Ymd His P', $m[1] . ' ' . $m[2] . ' ' . $offset);

            return $dt ?: null;
        }
    }

    $parsed = date_create_immutable($value, $tz);

    return $parsed instanceof DateTimeImmutable ? $parsed : null;
}

/**
 * Map a VEVENT body to [start_date, end_date] (Y-m-d) matching blocked_dates overlap semantics
 * (same as direct_booking: checkout day is end_date, exclusive for night math).
 *
 * All-day: DTEND is exclusive per RFC 5545 — stored end_date equals that calendar day.
 */
function lh_ical_vevent_to_date_range(string $event, string $defaultTz): ?array
{
    if (!preg_match('/^DTSTART([^:\r\n]*):([^\r\n]+)/mi', $event, $ms)) {
        return null;
    }

    $hasEnd = preg_match('/^DTEND([^:\r\n]*):([^\r\n]+)/mi', $event, $me);
    $vS = trim($ms[2]);
    $startIsDateOnly = str_contains(strtoupper($ms[1]), 'VALUE=DATE')
        || (preg_match('/^\d{8}$/', $vS) && strpos($vS, 'T') === false);

    try {
        $appTz = new DateTimeZone($defaultTz);
    } catch (Exception $e) {
        $appTz = new DateTimeZone('UTC');
    }

    if ($startIsDateOnly) {
        if (!preg_match('/^(\d{8})$/', $vS, $m)) {
            return null;
        }

        $startDt = DateTimeImmutable::createFromFormat('!Ymd', $m[1], $appTz);
        if (!$startDt) {
            return null;
        }

        $start_date = $startDt->format('Y-m-d');

        if (!$hasEnd) {
            $end_date = $startDt->modify('+1 day')->format('Y-m-d');
        } else {
            $vE = trim($me[2]);
            if (!preg_match('/^(\d{8})$/', $vE, $mE)) {
                return null;
            }

            $endDt = DateTimeImmutable::createFromFormat('!Ymd', $mE[1], $appTz);
            if (!$endDt) {
                return null;
            }

            $end_date = $endDt->format('Y-m-d');
        }

        if ($end_date <= $start_date) {
            return null;
        }

        return ['start_date' => $start_date, 'end_date' => $end_date];
    }

    $dtS = lh_ical_parse_dt_value($ms[1], $vS, $defaultTz);
    if (!$dtS) {
        return null;
    }

    if (!$hasEnd) {
        $dtE = $dtS->modify('+1 day');
    } else {
        $dtE = lh_ical_parse_dt_value($me[1], trim($me[2]), $defaultTz);
        if (!$dtE) {
            return null;
        }
    }

    $start_date = $dtS->setTimezone($appTz)->format('Y-m-d');
    $end_date = $dtE->setTimezone($appTz)->format('Y-m-d');

    if ($end_date <= $start_date) {
        $end_date = $dtE->setTimezone($appTz)->modify('+1 day')->format('Y-m-d');
    }

    if ($end_date <= $start_date) {
        return null;
    }

    return ['start_date' => $start_date, 'end_date' => $end_date];
}

function lh_ical_vevent_uid(string $event): string
{
    if (preg_match('/^UID:([^\r\n]+)/mi', $event, $u)) {
        return trim($u[1]);
    }

    return '';
}

/**
 * Best-effort: set after successful import (column may be missing until migration is applied).
 */
function lh_ical_touch_last_synced_at(PDO $pdo, int $property_id): void
{
    try {
        $st = $pdo->prepare(
            'UPDATE properties SET ical_last_synced_at = NOW() WHERE id = :id LIMIT 1'
        );
        $st->execute([':id' => $property_id]);
    } catch (Throwable $e) {
        error_log('ical_last_synced_at: ' . $e->getMessage());
    }
}

/**
 * Importă rezervări din link iCal și le salvează în blocked_dates (PDO).
 *
 * @return array{success: true, imported: int}|array{success: false, error: string}
 */
function importPropertyIcal(int $property_id): array
{
    $pdo = getPDO();

    $stmt = $pdo->prepare('SELECT ical_import_link FROM properties WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $property_id]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['success' => false, 'error' => 'Property not found'];
    }

    $ical_url = trim((string) ($row['ical_import_link'] ?? ''));

    if ($ical_url === '') {
        return ['success' => false, 'error' => 'No iCal link'];
    }

    $fetched = lh_ical_fetch_url($ical_url);
    if (!$fetched['ok']) {
        return ['success' => false, 'error' => $fetched['error']];
    }

    $ics = lh_ical_unfold_ics($fetched['body']);

    preg_match_all('/BEGIN:VEVENT.*?END:VEVENT/s', $ics, $blocks);
    $events = $blocks[0] ?? [];

    $defaultTz = date_default_timezone_get() ?: 'UTC';

    if ($events === []) {
        $pdo->beginTransaction();

        try {
            $del = $pdo->prepare(
                'DELETE FROM blocked_dates WHERE property_id = :pid AND source = :src'
            );
            $del->execute([':pid' => $property_id, ':src' => 'ical_import']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('importPropertyIcal error: ' . $e->getMessage());

            return ['success' => false, 'error' => 'Import failed'];
        }

        lh_ical_touch_last_synced_at($pdo, $property_id);

        return ['success' => true, 'imported' => 0];
    }

    $pdo->beginTransaction();

    try {
        $del = $pdo->prepare(
            'DELETE FROM blocked_dates WHERE property_id = :pid AND source = :src'
        );
        $del->execute([':pid' => $property_id, ':src' => 'ical_import']);

        $insert = $pdo->prepare(
            'INSERT INTO blocked_dates (property_id, start_date, end_date, source, external_event_id)
             VALUES (:pid, :start_date, :end_date, :src, :eid)'
        );

        $imported = 0;

        foreach ($events as $rawEvent) {
            $event = str_replace("\r", '', $rawEvent);
            $range = lh_ical_vevent_to_date_range($event, $defaultTz);
            if ($range === null) {
                continue;
            }

            $external_id = lh_ical_vevent_uid($event);
            if ($external_id === '') {
                $external_id = 'evt-' . md5($range['start_date'] . '|' . $range['end_date'] . '|' . $property_id . '|' . $imported);
            }

            $insert->execute([
                ':pid' => $property_id,
                ':start_date' => $range['start_date'],
                ':end_date' => $range['end_date'],
                ':src' => 'ical_import',
                ':eid' => function_exists('mb_substr') ? mb_substr($external_id, 0, 500) : substr($external_id, 0, 500),
            ]);

            ++$imported;
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('importPropertyIcal error: ' . $e->getMessage());

        return ['success' => false, 'error' => 'Import failed'];
    }

    lh_ical_touch_last_synced_at($pdo, $property_id);

    return [
        'success' => true,
        'imported' => $imported,
    ];
}

/**
 * Store feedback for dashboard after redirect (session flash).
 *
 * @param array{success: bool, imported?: int, error?: string} $result
 */
function lh_ical_set_import_feedback(array $result): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION['lh_ical_import_feedback'] = [
        'success' => !empty($result['success']),
        'imported' => isset($result['imported']) ? (int) $result['imported'] : 0,
        'error' => isset($result['error']) ? (string) $result['error'] : '',
    ];
}

/**
 * @return array{success: bool, imported: int, error: string}|null
 */
function lh_ical_consume_import_feedback(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }

    if (empty($_SESSION['lh_ical_import_feedback']) || !is_array($_SESSION['lh_ical_import_feedback'])) {
        return null;
    }

    $fb = $_SESSION['lh_ical_import_feedback'];
    unset($_SESSION['lh_ical_import_feedback']);

    return [
        'success' => !empty($fb['success']),
        'imported' => (int) ($fb['imported'] ?? 0),
        'error' => (string) ($fb['error'] ?? ''),
    ];
}
