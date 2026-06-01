<?php
/**
 * components/search_bar.php
 * Search / Booking Bar
 *
 * Variabile disponibile din pagina părinte (toate opționale):
 *   $properties  - array cu toate proprietățile (dacă nu e pasat, se încarcă intern)
 *   $selected_*  - valori pre-selectate (pentru persistența filtrelor)
 *   $lh_area_district, $lh_area_city - filtru zonă (properties.php); district are prioritate
 *   $lh_search_bar_show_sector - mod catalog: Sector + date + Persoane + Caută (fără Proprietate)
 *   $lh_sector_options - array de stringuri (sectoare distincte), folosit cu flag-ul de mai sus
 */

require_once __DIR__ . '/../config.php';

$lh_search_bar_show_sector = !empty($lh_search_bar_show_sector);

// Dacă $properties nu a fost pasat din pagina parentă, le încărcăm noi (bara catalog nu folosește lista)
if (!isset($properties) || !is_array($properties)) {
    if (!$lh_search_bar_show_sector) {
        try {
            $pdo  = getPDO();
            $stmt = $pdo->query('SELECT id, title, slug FROM properties ORDER BY title ASC');
            $properties = $stmt->fetchAll();
        } catch (Exception $e) {
            $properties = [];
        }
    } else {
        $properties = [];
    }
} elseif ($lh_search_bar_show_sector) {
    $properties = [];
}

// Valori pre-selectate (persistență după submit)
$sel_property = $_GET['property_id'] ?? '';
$sel_checkin  = $_GET['check_in']    ?? '';
$sel_checkout = $_GET['check_out']   ?? '';
$sel_guests   = $_GET['guests']      ?? '';

$lh_area_district = isset($lh_area_district) ? trim((string) $lh_area_district) : '';
$lh_area_city = isset($lh_area_city) ? trim((string) $lh_area_city) : '';

$lh_sector_options = isset($lh_sector_options) && is_array($lh_sector_options) ? $lh_sector_options : [];

$lh_sector_choices = $lh_sector_options;
if ($lh_search_bar_show_sector && $lh_area_district !== '' && !in_array($lh_area_district, $lh_sector_choices, true)) {
    $lh_sector_choices[] = $lh_area_district;
    sort($lh_sector_choices, SORT_STRING);
}
?>

<!-- ============================================================
     SEARCH BAR
     ============================================================ -->
<section class="w-full min-w-0 max-w-full overflow-x-clip bg-white border border-black/[0.04] shadow-xl shadow-black/5 rounded-[1.5rem] sm:rounded-[2rem] px-3 py-4 sm:px-4 sm:py-5 md:px-6 md:py-6" id="search-bar">
  <?php if (!$lh_search_bar_show_sector): ?>
    <?php if ($lh_area_district !== ''): ?>
    <input type="hidden" id="lh-filter-district" name="lh_filter_district" value="<?= htmlspecialchars($lh_area_district, ENT_QUOTES, 'UTF-8') ?>">
    <?php elseif ($lh_area_city !== ''): ?>
    <input type="hidden" id="lh-filter-city" name="lh_filter_city" value="<?= htmlspecialchars($lh_area_city, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
  <?php elseif ($lh_area_city !== '' && $lh_area_district === ''): ?>
  <input type="hidden" id="lh-filter-city" name="lh_filter_city" value="<?= htmlspecialchars($lh_area_city, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
  <div class="flex w-full min-w-0 flex-col gap-3 sm:gap-4 lg:flex-row lg:items-stretch lg:gap-4">

    <?php if ($lh_search_bar_show_sector): ?>
    <!-- 0. SECTOR (catalog) -->
    <div class="flex-1 min-w-0 lg:max-w-[14rem] lg:shrink-0">
      <div id="lh-search-sector-label" class="block text-xs font-semibold text-blue-grey uppercase tracking-wide mb-1">
        <?= htmlspecialchars(__('search.sector'), ENT_QUOTES, 'UTF-8') ?>
      </div>
      <div class="relative">
        <select
          id="lh-sector-select"
          name="lh_sector"
          aria-labelledby="lh-search-sector-label"
          class="w-full min-h-[3rem] h-12 min-w-0 box-border appearance-none bg-surface border border-black/8 rounded-2xl px-4 py-2 pr-10 text-ink text-base md:text-sm font-medium focus:outline-none focus:ring-2 focus:ring-cta/20 focus:border-cta transition cursor-pointer shadow-sm"
        >
          <option value="" <?= $lh_area_district === '' ? 'selected' : '' ?>><?= htmlspecialchars(__('search.all_sectors'), ENT_QUOTES, 'UTF-8') ?></option>
          <?php foreach ($lh_sector_choices as $sector): ?>
            <option
              value="<?= htmlspecialchars($sector, ENT_QUOTES, 'UTF-8') ?>"
              <?= $lh_area_district === $sector ? 'selected' : '' ?>
            >
              <?= htmlspecialchars(lh_location_label($sector), ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
          <svg class="w-4 h-4 text-blue-grey" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </div>
      </div>
    </div>
    <input type="hidden" id="lh-property-id-all" name="property_id" value="all">
    <?php endif; ?>

    <?php if (!$lh_search_bar_show_sector): ?>
    <!-- 1. DROPDOWN PROPRIETĂȚI -->
    <div class="flex-1 min-w-0">
      <div id="lh-search-property-label" class="block text-xs font-semibold text-blue-grey uppercase tracking-wide mb-1">
        <?= htmlspecialchars(__('search.property'), ENT_QUOTES, 'UTF-8') ?>
      </div>
      <div class="relative">
        <select
          id="property-select"
          name="property_id"
          aria-labelledby="lh-search-property-label"
          class="w-full min-h-[3rem] h-12 min-w-0 box-border appearance-none bg-surface border border-black/8 rounded-2xl px-4 py-2 pr-10 text-ink text-base md:text-sm font-medium focus:outline-none focus:ring-2 focus:ring-cta/20 focus:border-cta transition cursor-pointer shadow-sm"
        >
          <option value="all" <?= $sel_property === 'all' || $sel_property === '' ? 'selected' : '' ?>>
            <?= htmlspecialchars(__('search.all_properties'), ENT_QUOTES, 'UTF-8') ?>
          </option>
          <?php foreach ($properties as $prop): ?>
            <option
              value="<?= (int)$prop['id'] ?>"
              data-slug="<?= htmlspecialchars($prop['slug'] ?? '') ?>"
              <?= (string)$sel_property === (string)$prop['id'] ? 'selected' : '' ?>
            >
              <?= htmlspecialchars($prop['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <!-- Chevron icon -->
        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
          <svg class="w-4 h-4 text-blue-grey" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- 2. CHECK-IN -->
    <div class="flex-1 min-w-0">
      <div id="lh-search-checkin-label" class="block text-xs font-semibold text-blue-grey uppercase tracking-wide mb-1">
        <?= htmlspecialchars(__('search.check_in'), ENT_QUOTES, 'UTF-8') ?>
      </div>
      <div class="relative">
        <input
          type="text"
          id="check-in"
          name="check_in"
          aria-labelledby="lh-search-checkin-label"
          placeholder="<?= htmlspecialchars(__('search.select_date'), ENT_QUOTES, 'UTF-8') ?>"
          readonly
          value="<?= htmlspecialchars($sel_checkin) ?>"
          class="w-full min-h-[3rem] min-w-0 bg-surface border border-black/8 rounded-2xl px-4 py-3 pl-10 text-ink text-base md:text-sm font-medium focus:outline-none focus:ring-2 focus:ring-cta/20 focus:border-cta transition cursor-pointer shadow-sm box-border"
        >
        <div class="pointer-events-none absolute inset-y-0 left-3 flex items-center">
          <svg class="w-4 h-4 text-blue-grey" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
        </div>
      </div>
    </div>

    <!-- 3. CHECK-OUT -->
    <div class="flex-1 min-w-0">
      <div id="lh-search-checkout-label" class="block text-xs font-semibold text-blue-grey uppercase tracking-wide mb-1">
        <?= htmlspecialchars(__('search.check_out'), ENT_QUOTES, 'UTF-8') ?>
      </div>
      <div class="relative">
        <input
          type="text"
          id="check-out"
          name="check_out"
          aria-labelledby="lh-search-checkout-label"
          placeholder="<?= htmlspecialchars(__('search.select_date'), ENT_QUOTES, 'UTF-8') ?>"
          readonly
          value="<?= htmlspecialchars($sel_checkout) ?>"
          class="w-full min-h-[3rem] min-w-0 bg-surface border border-black/8 rounded-2xl px-4 py-3 pl-10 text-ink text-base md:text-sm font-medium focus:outline-none focus:ring-2 focus:ring-cta/20 focus:border-cta transition cursor-pointer shadow-sm box-border"
        >
        <div class="pointer-events-none absolute inset-y-0 left-3 flex items-center">
          <svg class="w-4 h-4 text-blue-grey" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
        </div>
      </div>
    </div>

    <!-- 4. GUESTS -->
    <div class="w-full min-w-0 lg:w-36 lg:shrink-0">
      <div id="lh-search-guests-label" class="block text-xs font-semibold text-blue-grey uppercase tracking-wide mb-1">
        <?= htmlspecialchars(__('search.guests'), ENT_QUOTES, 'UTF-8') ?>
      </div>
      <div class="relative">
        <select
          id="guests-select"
          name="guests"
          aria-labelledby="lh-search-guests-label"
          class="w-full min-h-[3rem] h-12 min-w-0 box-border appearance-none bg-surface border border-black/8 rounded-2xl px-4 py-2 pr-10 text-ink text-base md:text-sm font-medium focus:outline-none focus:ring-2 focus:ring-cta/20 focus:border-cta transition cursor-pointer shadow-sm"
        >
          <option value="1" <?= $sel_guests === '1' ? 'selected' : '' ?>><?= htmlspecialchars(__('booking.guest_one'), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="2" <?= $sel_guests === '2' ? 'selected' : '' ?>><?= htmlspecialchars(__('booking.guest_many', ['n' => '2']), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="3" <?= $sel_guests === '3' ? 'selected' : '' ?>><?= htmlspecialchars(__('booking.guest_many', ['n' => '3']), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="4" <?= $sel_guests === '4' ? 'selected' : '' ?>><?= htmlspecialchars(__('booking.guest_many', ['n' => '4']), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="5" <?= $sel_guests === '5' ? 'selected' : '' ?>><?= htmlspecialchars(__('booking.guest_many', ['n' => '5']), ENT_QUOTES, 'UTF-8') ?></option>
          <option value="6" <?= $sel_guests === '6' ? 'selected' : '' ?>><?= htmlspecialchars(__('booking.guest_six_plus'), ENT_QUOTES, 'UTF-8') ?></option>
        </select>
        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
          <svg class="w-4 h-4 text-blue-grey" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
        </div>
      </div>
    </div>

    <!-- 5. BUTON ACȚIUNE -->
    <div class="w-full min-w-0 lg:w-auto lg:shrink-0 flex flex-col justify-end">
      <div class="block text-xs font-semibold text-transparent uppercase tracking-wide mb-1 select-none max-lg:hidden">
        &nbsp;
      </div>
      <button
        id="search-btn"
        type="button"
        class="w-full lg:w-auto inline-flex items-center justify-center gap-2 min-h-[3rem] h-12 px-6 sm:px-8 bg-cta hover:brightness-110 active:brightness-95 text-white font-semibold text-base md:text-sm rounded-full shadow-md shadow-black/10 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-cta/25 focus:ring-offset-2 focus:ring-offset-white lg:whitespace-nowrap"
        data-mode="search"
      >
        <!-- Icon search -->
        <svg id="btn-icon-search" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <!-- Icon rezervă -->
        <svg id="btn-icon-reserve" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M5 13l4 4L19 7"/>
        </svg>
        <span id="search-btn-text"><?= htmlspecialchars(__('search.search'), ENT_QUOTES, 'UTF-8') ?></span>
      </button>
    </div>

  </div><!-- end flex row -->

  <p class="text-xs text-blue-grey mt-2 px-0.5 leading-snug"><?= htmlspecialchars(__('search.min_night_hint'), ENT_QUOTES, 'UTF-8') ?></p>
  <p id="lh-search-date-error" class="hidden mt-1 text-xs font-medium text-red-800 px-0.5" role="alert"></p>

  <!-- Loading indicator (ascuns implicit) -->
  <div id="search-loading" class="hidden mt-4 flex items-center gap-2 text-sm text-blue-grey">
    <svg class="animate-spin w-4 h-4 text-cta" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
    </svg>
    <?= htmlspecialchars(__('search.loading'), ENT_QUOTES, 'UTF-8') ?>
  </div>

</section>
<!-- END SEARCH BAR -->