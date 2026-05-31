<?php

declare(strict_types=1);

include('../config.php');
require_once __DIR__ . '/includes/auth.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
dashboard_enforce_active_user($conn);

if (!dashboard_user_is_admin()) {
    header('Location: dashboard.php?forbidden=1');
    exit;
}

$redirect_status = $_POST['redirect_status'] ?? 'all';
$allowed_redirect = ['all', 'active', 'disabled'];
if (!in_array($redirect_status, $allowed_redirect, true)) {
    $redirect_status = 'all';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !lh_csrf_verify_post()) {
    header('Location: users.php?status=' . urlencode($redirect_status));
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    header('Location: users.php?status=' . urlencode($redirect_status));
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT id, role, status FROM users WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$row = lh_mysqli_stmt_fetch_assoc($stmt);
mysqli_stmt_close($stmt);

if (!$row) {
    header('Location: users.php?status=' . urlencode($redirect_status));
    exit;
}

// Administrator status cannot be toggled from the list (only via edit rules / DB).
if (($row['role'] ?? '') === 'admin') {
    header('Location: users.php?status=' . urlencode($redirect_status));
    exit;
}

$current_id = (int) ($_SESSION['admin_user_id'] ?? 0);
$new_status = ($row['status'] ?? '') === 'active' ? 'disabled' : 'active';

if ($new_status === 'disabled' && $id === $current_id) {
    header('Location: users.php?status=' . urlencode($redirect_status));
    exit;
}

$upd = mysqli_prepare($conn, 'UPDATE users SET status = ? WHERE id = ?');
mysqli_stmt_bind_param($upd, 'si', $new_status, $id);
mysqli_stmt_execute($upd);
mysqli_stmt_close($upd);

lh_admin_log_activity($conn, 'user_toggle_status', 'user', $id, [
    'from' => (string) ($row['status'] ?? ''),
    'to' => $new_status,
]);

header('Location: users.php?status=' . urlencode($redirect_status));
exit;
