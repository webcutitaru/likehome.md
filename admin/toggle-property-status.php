<?php

declare(strict_types=1);

include('../config.php');
require_once __DIR__ . '/includes/auth.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}
dashboard_enforce_active_user($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !lh_csrf_verify_post()) {
    header('Location: dashboard.php');
    exit();
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    header('Location: dashboard.php');
    exit();
}

$res = mysqli_query($conn, "SELECT is_active FROM properties WHERE id = $id LIMIT 1");
$row = $res ? mysqli_fetch_assoc($res) : null;

if (!$row) {
    header('Location: dashboard.php');
    exit();
}

$old_active = !empty($row['is_active']) ? 1 : 0;
$new_status = $old_active ? 0 : 1;
mysqli_query($conn, "UPDATE properties SET is_active = $new_status WHERE id = $id");
lh_admin_log_activity($conn, 'property_toggle_active', 'property', $id, [
    'from_active' => $old_active,
    'to_active' => $new_status,
]);

header('Location: dashboard.php');
exit();
