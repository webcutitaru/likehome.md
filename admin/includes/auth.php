<?php

declare(strict_types=1);

function admin_session_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], !empty($p['secure']), !empty($p['httponly']));
    }
    session_destroy();
}

/**
 * Ensures the logged-in dashboard account still exists and is active (disabled users are signed out).
 */
function dashboard_enforce_active_user(mysqli $conn): void
{
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        return;
    }
    if (empty($_SESSION['admin_user_id'])) {
        admin_session_logout();
        header('Location: login.php?reason=session');
        exit;
    }

    $uid = (int) $_SESSION['admin_user_id'];
    $stmt = mysqli_prepare($conn, 'SELECT id, status FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return;
    }
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $row = lh_mysqli_stmt_fetch_assoc($stmt);
    mysqli_stmt_close($stmt);

    if (!$row || ($row['status'] ?? '') !== 'active') {
        admin_session_logout();
        header('Location: login.php?reason=disabled');
        exit;
    }
}

function dashboard_user_is_admin(): bool
{
    return isset($_SESSION['admin_user_role']) && $_SESSION['admin_user_role'] === 'admin';
}

function dashboard_require_admin_user(mysqli $conn): void
{
    dashboard_enforce_active_user($conn);
    if (!dashboard_user_is_admin()) {
        header('Location: dashboard.php?forbidden=1');
        exit;
    }
}

function dashboard_count_active_admins(mysqli $conn): int
{
    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'admin' AND status = 'active'");
    if (!$res) {
        return 0;
    }
    $row = mysqli_fetch_assoc($res);

    return (int) ($row['c'] ?? 0);
}
