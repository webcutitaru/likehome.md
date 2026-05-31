<?php
/**
 * properties.php
 * Director complet: toate proprietățile active.
 */

require_once __DIR__ . '/config.php';

const LH_AREA_PARAM_MAX_LEN = 255;

$district_get = isset($_GET['district']) ? trim((string) $_GET['district']) : '';
$city_get = isset($_GET['city']) ? trim((string) $_GET['city']) : '';
if (strlen($district_get) > LH_AREA_PARAM_MAX_LEN) {
    $district_get = '';
}
if (strlen($city_get) > LH_AREA_PARAM_MAX_LEN) {
    $city_get = '';
}

$lh_area_district = '';
$lh_area_city = '';

$lh_search_bar_show_sector = true;
$lh_sector_options         = [];

$properties = [];
$all_props  = [];
try {
    $pdo    = getPDO();

    $stmtSectors = $pdo->query(
        "SELECT DISTINCT district FROM properties WHERE is_active = 1 AND district IS NOT NULL AND district <> '' ORDER BY district"
    );
    if ($stmtSectors) {
        $seen = [];
        foreach ($stmtSectors->fetchAll(PDO::FETCH_COLUMN) as $d) {
            $t = trim((string) $d);
            if ($t !== '') {
                $seen[$t] = true;
            }
        }
        $lh_sector_options = array_keys($seen);
        sort($lh_sector_options, SORT_STRING);
    }

    if ($district_get !== '') {
        $lh_area_district = $district_get;
        $stmtAll = $pdo->prepare(
            'SELECT * FROM properties WHERE is_active = 1 AND district = :district ORDER BY created_at DESC'
        );
        $stmtAll->execute([':district' => $district_get]);
        $all_props = $stmtAll->fetchAll();
    } elseif ($city_get !== '') {
        $lh_area_city = $city_get;
        $stmtAll = $pdo->prepare(
            'SELECT * FROM properties WHERE is_active = 1 AND city = :city ORDER BY created_at DESC'
        );
        $stmtAll->execute([':city' => $city_get]);
        $all_props = $stmtAll->fetchAll();
    } else {
        $stmtAll = $pdo->query(
            'SELECT * FROM properties WHERE is_active = 1 ORDER BY created_at DESC'
        );
        $all_props = $stmtAll->fetchAll();
    }
} catch (Exception $e) {
    $properties = [];
    $all_props  = [];
    $lh_sector_options = [];
}

if ($lh_area_district !== '') {
    $pageTitle = 'Proprietăți în ' . $lh_area_district . ' — Like HOME';
    $catalog_hero_title = 'Proprietăți în zonă';
    $catalog_hero_subtitle = $lh_area_district;
    $pageDescription = 'Catalog Like HOME în zona ' . $lh_area_district
        . ': apartamente și case de închiriat pe termen scurt. Alege perioada și numărul de persoane, apoi rezervă direct.';
    $canonicalUrl = lh_absolute_url('properties.php?' . http_build_query(['district' => $lh_area_district]));
} elseif ($lh_area_city !== '') {
    $pageTitle = 'Proprietăți în ' . $lh_area_city . ' — Like HOME';
    $catalog_hero_title = 'Proprietăți în zonă';
    $catalog_hero_subtitle = $lh_area_city;
    $pageDescription = 'Proprietăți de închiriat în ' . $lh_area_city
        . ', listate pe Like HOME. Compară locațiile și verifică disponibilitatea pentru sejurul tău.';
    $canonicalUrl = lh_absolute_url('properties.php?' . http_build_query(['city' => $lh_area_city]));
} else {
    $pageTitle = 'Toate proprietățile — Like HOME';
    $catalog_hero_title = 'Toate proprietățile';
    $catalog_hero_subtitle = '';
    $pageDescription = 'Toate proprietățile active Like HOME: închirieri pe termen scurt în Moldova. Filtrează după sector sau dată și rezervă direct.';
    $canonicalUrl = lh_absolute_url('properties.php');
}

$getCheckIn  = $_GET['check_in'] ?? '';
$getCheckOut = $_GET['check_out'] ?? '';
$getGuests   = $_GET['guests'] ?? '';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/components/property_card.php'; ?>

<!-- Hero; search centered on gradient / light boundary (same pattern as index) -->
<div class="bg-gradient-to-br from-logo via-brand-700 to-ink relative pt-12 md:pt-16">
  <div class="max-w-4xl mx-auto px-4 text-center font-sans pb-6 md:pb-8 lg:pb-36">
    <h1 class="text-3xl md:text-5xl font-bold text-white mb-3">
      <?= htmlspecialchars($catalog_hero_title, ENT_QUOTES, 'UTF-8') ?>
    </h1>
    <?php if ($catalog_hero_subtitle !== ''): ?>
    <p class="text-white text-lg md:text-xl font-semibold mb-2">
      <?= htmlspecialchars($catalog_hero_subtitle, ENT_QUOTES, 'UTF-8') ?>
    </p>
    <?php endif; ?>
    <p class="text-white/70 text-base md:text-lg max-w-2xl mx-auto">
      <?php if ($lh_area_district !== '' || $lh_area_city !== ''): ?>
        Proprietăți în această zonă (<?= count($all_props) ?> <?= count($all_props) === 1 ? 'proprietate' : 'proprietăți' ?>). Poți filtra după date și număr de persoane mai jos.
      <?php else: ?>
        Răsfoiește catalogul complet (<?= count($all_props) ?> <?= count($all_props) === 1 ? 'proprietate' : 'proprietăți' ?>). Poți filtra după date și număr de persoane mai jos.
      <?php endif; ?>
    </p>
  </div>
  <div class="relative z-10 flex justify-center px-3 sm:px-4 min-w-0 pb-8 md:pb-8 lg:absolute lg:inset-x-0 lg:bottom-0 lg:translate-y-1/2 lg:pb-0">
    <div class="w-full min-w-0 max-w-6xl">
      <?php include __DIR__ . '/components/search_bar.php'; ?>
    </div>
  </div>
</div>

<div class="max-w-6xl mx-auto px-4 pb-0 lg:pt-20"></div>

<div id="search-results-section" class="max-w-6xl mx-auto px-4 mt-0 pt-0 pb-0 scroll-mt-28 md:scroll-mt-24">
  <div id="results-header" class="hidden mt-6 mb-6">
    <h2 class="text-xl font-semibold text-ink text-center">Proprietăți disponibile</h2>
  </div>
  <div
    id="results-container"
    class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 transition-opacity duration-300 ease-out opacity-0"
  ></div>
</div>

<div class="max-w-6xl mx-auto px-4 mt-8 lg:mt-0 pt-0 pb-0">
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-4">
    <?php
    if (!empty($all_props)) {
        foreach ($all_props as $property) {
            echo render_property_card($property, $getCheckIn, $getCheckOut, $getGuests);
        }
    } else {
        echo '<p class="col-span-full text-blue-grey">Nu există proprietăți disponibile momentan.</p>';
    }
    ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
