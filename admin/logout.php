<?php
require_once __DIR__ . '/../config.php';

$logoutUid = (int) ($_SESSION['admin_user_id'] ?? 0);
if ($logoutUid > 0) {
    lh_admin_log_activity(
        $conn,
        'logout',
        'user',
        $logoutUid,
        ['email' => (string) ($_SESSION['admin_user_email'] ?? '')],
        $logoutUid,
        false
    );
}

// Ștergem toate variabilele din sesiune
$_SESSION = [];

// Distrugem sesiunea complet
session_destroy();

// Redirecționăm la pagina de login
header("Location: login.php");
exit();
?>