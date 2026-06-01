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
$pdo = null;
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

$all_props = lh_property_apply_locale_list($all_props, $pdo ?? null);

if ($lh_area_district !== '') {
    $pageTitle = __('page.properties.title_district', ['area' => lh_location_label($lh_area_district)]);
    $catalog_hero_title = __('page.properties.hero_area');
    $catalog_hero_subtitle = lh_location_label($lh_area_district);
    $pageDescription = __('page.properties.desc_district', ['area' => lh_location_label($lh_area_district)]);
    $canonicalUrl = lh_absolute_locale_url('properties.php?' . http_build_query(['district' => $lh_area_district]));
} elseif ($lh_area_city !== '') {
    $pageTitle = __('page.properties.title_city', ['city' => lh_location_label($lh_area_city)]);
    $catalog_hero_title = __('page.properties.hero_area');
    $catalog_hero_subtitle = lh_location_label($lh_area_city);
    $pageDescription = __('page.properties.desc_city', ['city' => lh_location_label($lh_area_city)]);
    $canonicalUrl = lh_absolute_locale_url('properties.php?' . http_build_query(['city' => $lh_area_city]));
} else {
    $pageTitle = __('page.properties.title_default');
    $catalog_hero_title = __('page.properties.hero_default');
    $catalog_hero_subtitle = '';
    $pageDescription = __('page.properties.desc_default');
    $canonicalUrl = lh_absolute_locale_url('properties.php');
}

$getCheckIn  = $_GET['check_in'] ?? '';
$getCheckOut = $_GET['check_out'] ?? '';
$getGuests   = $_GET['guests'] ?? '';

$lh_prop_count = count($all_props);
$lh_prop_count_label = $lh_prop_count === 1
    ? '1 ' . __('page.properties.count_one')
    : __('page.properties.count_many', ['n' => (string) $lh_prop_count]);
$lh_prop_filter_hint = __('page.properties.filter_hint');
if ($lh_area_district !== '' || $lh_area_city !== '') {
    $catalog_hero_desc = __('page.properties.hero_in_area', [
        'count' => $lh_prop_count_label,
        'hint' => $lh_prop_filter_hint,
    ]);
} else {
    $catalog_hero_desc = __('page.properties.hero_browse', [
        'count' => $lh_prop_count_label,
        'hint' => $lh_prop_filter_hint,
    ]);
}
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
      <?= htmlspecialchars($catalog_hero_desc, ENT_QUOTES, 'UTF-8') ?>
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
    <h2 class="text-xl font-semibold text-ink text-center"><?= htmlspecialchars(__('search.available_properties'), ENT_QUOTES, 'UTF-8') ?></h2>
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
        echo '<p class="col-span-full text-blue-grey">' . htmlspecialchars(__('page.index.empty'), ENT_QUOTES, 'UTF-8') . '</p>';
    }
    ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
