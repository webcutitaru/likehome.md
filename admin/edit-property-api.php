<?php

declare(strict_types=1);

/**
 * JSON API: save edit form without new files, then append gallery in batches.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/lh_edit_property_save_core.php';
require_once __DIR__ . '/../includes/lh_property_gallery_batch.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Neautorizat.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($conn) && $conn instanceof mysqli) {
    dashboard_enforce_active_user($conn);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodă nepermisă.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = isset($_POST['lh_action']) ? (string) $_POST['lh_action'] : '';

if ($action === 'save_property') {
    if (lh_post_exceeds_post_max_size()) {
        http_response_code(413);
        echo json_encode(['ok' => false, 'error' => 'Cererea depășește post_max_size.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!lh_csrf_verify_post()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Sesiune invalidă sau token CSRF expirat. Reîncarcă pagina.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pid = isset($_POST['property_id']) ? (int) $_POST['property_id'] : 0;
    if ($pid <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Lipsește ID proprietate.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $chk = mysqli_query($conn, 'SELECT id FROM properties WHERE id=' . $pid . ' LIMIT 1');
    if (!$chk || (int) mysqli_num_rows($chk) === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Proprietatea nu există.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $saveResult = lh_edit_property_save_from_post($conn, getPDO(), $pid, $_POST, []);
    if (!$saveResult['ok']) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $saveResult['error']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['_lh_edit_property_upload'] = [
        'property_id' => $pid,
        'expires_at' => time() + 7200,
    ];

    $payload = ['ok' => true, 'property_id' => $pid];
    if (!empty($saveResult['debug_timings']) && is_array($saveResult['debug_timings'])) {
        $payload['debug_timings'] = $saveResult['debug_timings'];
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'append_images_edit') {
    if (lh_post_exceeds_post_max_size()) {
        http_response_code(413);
        echo json_encode(['ok' => false, 'error' => 'Lotul depășește limitele serverului (post_max_size).'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!lh_csrf_verify_post()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Sesiune invalidă sau token CSRF expirat.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $slot = $_SESSION['_lh_edit_property_upload'] ?? null;
    $postedId = isset($_POST['property_id']) ? (int) $_POST['property_id'] : 0;
    if (
        !is_array($slot)
        || (int) ($slot['property_id'] ?? 0) !== $postedId
        || $postedId <= 0
        || (int) ($slot['expires_at'] ?? 0) < time()
    ) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Salvează din nou formularul (fără lot mare deodată), apoi reîncearcă încărcarea pozelor.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $appendResult = lh_property_append_images_batch($conn, $postedId, $_FILES);
    if (!$appendResult['ok']) {
        $code = 400;
        if (str_contains($appendResult['error'], 'nu există')) {
            $code = 404;
        }
        if (str_contains($appendResult['error'], 'citirea') || str_contains($appendResult['error'], 'salvarea')) {
            $code = 500;
        }
        http_response_code($code);
        echo json_encode(['ok' => false, 'error' => $appendResult['error']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'added' => $appendResult['added'],
        'names' => $appendResult['names'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Acțiune necunoscută.'], JSON_UNESCAPED_UNICODE);
