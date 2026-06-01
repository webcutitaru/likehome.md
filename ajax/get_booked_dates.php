<?php
/**
 * ajax/get_booked_dates.php
 * Returnează datele ocupate pentru o proprietate specifică.
 *
 * GET params:
 *   property_id (int, obligatoriu)
 *
 * Response: JSON
 *   { "success": true, "booked_dates": ["2025-07-10", "2025-07-11", ...] }
 *   sau
 *   { "success": false, "error": "mesaj" }
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config.php';

// ── Validare input ──────────────────────────────────────────────
$property_id = filter_input(INPUT_GET, 'property_id', FILTER_VALIDATE_INT);

if (!$property_id || $property_id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => lh_translate('api.property_id_invalid')]);
    exit;
}

// ── Query ───────────────────────────────────────────────────────
try {
    $pdo = getPDO();

    // Intervale blocate [start_date, end_date), din azi înainte.
    // DISTINCT: pot exista duplicate (ex. dublură la insert blocked_dates pentru același booking).
    $stmt = $pdo->prepare('
        SELECT DISTINCT start_date, end_date
        FROM   blocked_dates
        WHERE  property_id = :pid
          AND  end_date >= CURDATE()
        ORDER  BY start_date ASC
    ');
    $stmt->execute([':pid' => $property_id]);
    $intervals = $stmt->fetchAll();

    // Expandăm intervalele în zile individuale pentru Flatpickr
    // Flatpickr acceptă "disable" ca array de date exacte sau funcții.
    // Trimitem intervalele brute - JS le va procesa.
    $blocked_ranges = [];
    foreach ($intervals as $row) {
        $blocked_ranges[] = [
            'from' => $row['start_date'],
            'to'   => $row['end_date'],
        ];
    }

    echo json_encode([
        'success'        => true,
        'blocked_ranges' => $blocked_ranges,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => lh_translate('api.server_error')]);
    // Log eroarea intern, nu o expune clientului
    error_log('get_booked_dates error: ' . $e->getMessage());
}