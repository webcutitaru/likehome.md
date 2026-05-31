<?php

declare(strict_types=1);

/**
 * JSON API for chunked add-property: create without files, then append image batches.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';

require_once __DIR__ . '/../includes/lh_add_property_core.php';

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

if ($action === 'create') {
    if (lh_post_exceeds_post_max_size()) {
        http_response_code(413);
        echo json_encode(['ok' => false, 'error' => 'Cererea depășește post_max_size. Micșorează datele sau contactează hostingul.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!lh_csrf_verify_post()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Sesiune invalidă sau token CSRF expirat. Reîncarcă pagina.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = lh_add_property_create_from_post($conn, $_POST);
    if (!$result['ok']) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $result['error']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pid = (int) $result['property_id'];
    $_SESSION['_lh_property_image_upload'] = [
        'property_id' => $pid,
        'expires_at' => time() + 7200,
    ];

    echo json_encode(['ok' => true, 'property_id' => $pid], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'append_images') {
    if (lh_post_exceeds_post_max_size()) {
        http_response_code(413);
        echo json_encode(['ok' => false, 'error' => 'Lotul de imagini depășește limitele serverului (post_max_size).'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!lh_csrf_verify_post()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Sesiune invalidă sau token CSRF expirat.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $slot = $_SESSION['_lh_property_image_upload'] ?? null;
    $postedId = isset($_POST['property_id']) ? (int) $_POST['property_id'] : 0;
    if (
        !is_array($slot)
        || ($slot['property_id'] ?? 0) !== $postedId
        || $postedId <= 0
        || (int) ($slot['expires_at'] ?? 0) < time()
    ) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Încărcarea imaginilor nu este permisă pentru această proprietate. Publică din nou formularul.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    require_once __DIR__ . '/../includes/lh_property_gallery_batch.php';
    $appendResult = lh_property_append_images_batch($conn, $postedId, $_FILES);
    if (!$appendResult['ok']) {
        http_response_code(400);
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
