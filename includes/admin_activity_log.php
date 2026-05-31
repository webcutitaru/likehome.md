<?php

declare(strict_types=1);

/**
 * Append one row to admin_activity_log (if the table exists).
 *
 * @param int|null $actorUserId When set, this user id is stored. When null, uses $_SESSION['admin_user_id'] if > 0, else NULL in DB.
 * @param bool $anonymous When true, always stores NULL for user_id (e.g. failed login).
 */
function lh_admin_log_activity(
    mysqli $conn,
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    ?array $details = null,
    ?int $actorUserId = null,
    bool $anonymous = false
): void {
    static $tableExists = null;
    if ($tableExists === null) {
        $check = mysqli_query($conn, "SHOW TABLES LIKE 'admin_activity_log'");
        $tableExists = $check && mysqli_num_rows($check) > 0;
    }
    if (!$tableExists) {
        return;
    }

    $uid = null;
    if (!$anonymous) {
        if ($actorUserId !== null && $actorUserId > 0) {
            $uid = $actorUserId;
        } else {
            $sid = (int) ($_SESSION['admin_user_id'] ?? 0);
            $uid = $sid > 0 ? $sid : null;
        }
    }

    $detailsJson = null;
    if ($details !== null && $details !== []) {
        $encoded = json_encode($details, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            $encoded = '{}';
        }
        if (strlen($encoded) > 4000) {
            $encoded = substr($encoded, 0, 3997) . '...';
        }
        $detailsJson = $encoded;
    }

    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if (strlen($ip) > 45) {
        $ip = substr($ip, 0, 45);
    }
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (strlen($ua) > 2000) {
        $ua = substr($ua, 0, 2000);
    }

    $actionDb = substr($action, 0, 64);
    $uidPart = $uid === null ? 'NULL' : (string) (int) $uid;
    $etPart = $entityType === null
        ? 'NULL'
        : ("'" . mysqli_real_escape_string($conn, substr($entityType, 0, 32)) . "'");
    $eidPart = $entityId === null ? 'NULL' : (string) (int) $entityId;
    $detPart = $detailsJson === null
        ? 'NULL'
        : ("'" . mysqli_real_escape_string($conn, $detailsJson) . "'");
    $ipEsc = mysqli_real_escape_string($conn, $ip);
    $uaEsc = mysqli_real_escape_string($conn, $ua);
    $actEsc = mysqli_real_escape_string($conn, $actionDb);

    $sql = "INSERT INTO admin_activity_log (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
            VALUES ({$uidPart}, '{$actEsc}', {$etPart}, {$eidPart}, {$detPart}, '{$ipEsc}', '{$uaEsc}')";

    mysqli_query($conn, $sql);
}
