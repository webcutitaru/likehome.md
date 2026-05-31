<?php
require_once __DIR__ . '/../config.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && !empty($_SESSION['admin_user_id'])) {
    header("Location: dashboard.php");
    exit();
}
header("Location: login.php");
exit();
?>