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

$current_id = (int) ($_SESSION['admin_user_id'] ?? 0);
if ($id === $current_id) {
    header('Location: users.php?status=' . urlencode($redirect_status));
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT role, status, email FROM users WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$row = lh_mysqli_stmt_fetch_assoc($stmt);
mysqli_stmt_close($stmt);

if (!$row) {
    header('Location: users.php?status=' . urlencode($redirect_status));
    exit;
}

// Administrator accounts cannot be removed from the panel (only via direct DB access).
if (($row['role'] ?? '') === 'admin') {
    header('Location: users.php?status=' . urlencode($redirect_status));
    exit;
}

lh_admin_log_activity($conn, 'user_delete', 'user', $id, [
    'email' => (string) ($row['email'] ?? ''),
    'role' => (string) ($row['role'] ?? ''),
]);

$del = mysqli_prepare($conn, 'DELETE FROM users WHERE id = ?');
mysqli_stmt_bind_param($del, 'i', $id);
mysqli_stmt_execute($del);
mysqli_stmt_close($del);

header('Location: users.php?status=' . urlencode($redirect_status));
exit;
