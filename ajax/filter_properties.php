<?php
/**
 * ajax/filter_properties.php
 * Filtrează proprietățile disponibile și returnează HTML gata randat.
 *
 * Returnăm HTML (nu JSON pur) pentru că:
 *   1. Ușor de injectat direct în DOM fără manipulare JS suplimentară
 *   2. property_card.php e deja template PHP - îl reutilizăm
 *   3. Dacă în viitor adaugi paginare sau logică complexă, e mai simplu în PHP
 *
 * POST params:
 *   property_id  - "all" sau int
 *   check_in     - "Y-m-d" sau gol
 *   check_out    - "Y-m-d" sau gol
 *   guests       - "1","2","3","4" (4 = 4+) sau gol
 *   district     - opțional, filtru zonă (prioritar față de city)
 *   city         - opțional, filtru zonă dacă district e gol
 *
 * Response: HTML fragment
 */

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../components/property_card.php';

$filterLocale = lh_resolve_request_locale();

// ── Validare & sanitizare input ─────────────────────────────────
$raw_property = $_POST['property_id'] ?? 'all';
$property_id  = ($raw_property === 'all') ? 'all' : filter_var($raw_property, FILTER_VALIDATE_INT);

$check_in  = $_POST['check_in']  ?? '';
$check_out = $_POST['check_out'] ?? '';
$guests    = $_POST['guests']    ?? '';

$district_filter = trim((string) ($_POST['district'] ?? ''));
$city_filter = trim((string) ($_POST['city'] ?? ''));
if (strlen($district_filter) > 255) {
    $district_filter = '';
}
if (strlen($city_filter) > 255) {
    $city_filter = '';
}

// Validare date
function is_valid_date(string $date): bool
{
    if (empty($date)) return false;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

$has_dates    = is_valid_date($check_in) && is_valid_date($check_out);
$has_guests   = in_array($guests, ['1','2','3','4'], true);

// Check-out trebuie să fie după check-in
if ($has_dates && $check_out <= $check_in) {
    echo '<div class="col-span-full text-center py-8 text-red-500 font-medium">' . htmlspecialchars(lh_translate('api.dates_order', [], $filterLocale), ENT_QUOTES, 'UTF-8') . '</div>';
    exit;
}

// ── Construiește query ─────────────────────────────────────────
try {
    $pdo = getPDO();

    $params = [];
    $where  = ['1=1', 'p.is_active = 1'];

    // Filtru proprietate individuală
    if ($property_id !== 'all' && $property_id > 0) {
        $where[]              = 'p.id = :pid';
        $params[':pid']       = $property_id;
    }

    // Filtru zonă (district are prioritate)
    if ($district_filter !== '') {
        $where[] = 'p.district = :area_district';
        $params[':area_district'] = $district_filter;
    } elseif ($city_filter !== '') {
        $where[] = 'p.city = :area_city';
        $params[':area_city'] = $city_filter;
    }

    // Filtru guests (sleep_capacity acum INT după migrare)
    if ($has_guests) {
        if ($guests === '4') {
            $where[]              = 'p.sleep_capacity >= 4';
        } else {
            $where[]              = 'p.sleep_capacity >= :guests';
            $params[':guests']    = (int)$guests;
        }
    }

    // Filtru disponibilitate după interval (excludem proprietăți cu overlap în blocked_dates)
    if ($has_dates) {
        $where[] = '
            p.id NOT IN (
                SELECT DISTINCT bd.property_id
                FROM   blocked_dates bd
                WHERE  bd.property_id = p.id
                  AND  bd.start_date < :checkout
                  AND  bd.end_date   > :checkin
            )
        ';
        $params[':checkin']  = $check_in;
        $params[':checkout'] = $check_out;
    }

    $sql = 'SELECT p.* FROM properties p WHERE ' . implode(' AND ', $where) . ' ORDER BY p.title ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = lh_property_apply_locale_list($stmt->fetchAll(), $pdo);

} catch (Exception $e) {
    error_log('filter_properties error: ' . $e->getMessage());
    echo '<div class="col-span-full text-center py-8 text-red-500 font-medium">' . htmlspecialchars(lh_translate('search.error_generic', [], $filterLocale), ENT_QUOTES, 'UTF-8') . '</div>';
    exit;
}

// ── Randare rezultate ───────────────────────────────────────────
if (empty($results)) {
    echo '
    <div class="col-span-full flex flex-col items-center py-16 text-gray-400">
      <svg class="w-14 h-14 mb-4 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
          d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <p class="text-lg font-semibold text-gray-600 mb-1">' . htmlspecialchars(lh_translate('search.no_results_title', [], $filterLocale), ENT_QUOTES, 'UTF-8') . '</p>
      <p class="text-sm">' . htmlspecialchars(lh_translate('search.no_results_hint', [], $filterLocale), ENT_QUOTES, 'UTF-8') . '</p>
    </div>
    ';
    exit;
}

foreach ($results as $property) {
    echo render_property_card($property, $check_in, $check_out, $guests);
}