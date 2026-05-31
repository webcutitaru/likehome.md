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
    header('Location: dashboard.php?forbidden=property_delete');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !lh_csrf_verify_post()) {
    header('Location: dashboard.php');
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id > 0) {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT image_name, title FROM properties WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if ($row) {
        lh_admin_log_activity($conn, 'property_delete', 'property', $id, [
            'title' => (string) ($row['title'] ?? ''),
        ]);
        $uploadsDir = __DIR__ . '/../uploads/properties/' . $id;
        lh_remove_directory($uploadsDir);
        if (!empty($row['image_name'])) {
            $names = array_filter(array_map('trim', explode(',', (string) $row['image_name'])));
            foreach ($names as $name) {
                if ($name === '' || strpbrk($name, "\\/") !== false) {
                    continue;
                }
                lh_delete_property_image_from_disk($id, $name);
            }
        }
    }

    $del = $pdo->prepare('DELETE FROM properties WHERE id = ?');
    $del->execute([$id]);
}

header('Location: dashboard.php');
exit();
