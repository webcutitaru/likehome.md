<?php
/**
 * property-details.php
 * Premium property details page
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/booking_pricing.php';
require_once __DIR__ . '/includes/property_amenity_catalog.php';
require_once __DIR__ . '/components/property_card.php';

$slug = $_GET['slug'] ?? null;
$id   = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$check_in  = $_GET['check_in']  ?? '';
$check_out = $_GET['check_out'] ?? '';
$guests    = $_GET['guests']    ?? '';

function is_valid_date_details(string $d): bool {
    if (empty($d)) return false;
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

$has_checkin  = is_valid_date_details($check_in);
$has_checkout = is_valid_date_details($check_out);

try {
    $pdo = getPDO();

    if ($slug) {
        $property = lh_property_resolve_by_slug($pdo, (string) $slug);
        if (!$property) {
            http_response_code(404);
            die(__('errors.property_not_found'));
        }
    } elseif ($id) {
        $stmt = $pdo->prepare('SELECT * FROM properties WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute([':id' => $id]);
        $property = $stmt->fetch();
        if (!$property) {
            http_response_code(404);
            die(__('errors.property_not_found'));
        }
        $property = lh_property_apply_locale($property, $pdo);
    } else {
        header('Location: ' . lh_locale_url());
        exit;
    }

    if (!$property) {
        http_response_code(404);
        die(__('errors.property_not_found'));
    }

    $property['_pricing_periods'] = lh_property_pricing_periods_load((int) $property['id']);
    $property['_stay_discounts_global'] = lh_property_stay_discounts_load_by_property((int) $property['id'])['global'];

    $same_area_properties = [];
    $same_area_see_more_url = '';
    $same_area_label = '';
    $current_prop_id = (int) $property['id'];
    $district_trim = trim((string) ($property['district'] ?? ''));
    $city_trim = trim((string) ($property['city'] ?? ''));

    if ($district_trim !== '') {
        $stmtNeighbors = $pdo->prepare(
            'SELECT * FROM properties WHERE is_active = 1 AND id != :id AND district = :district ORDER BY created_at DESC LIMIT 3'
        );
        $stmtNeighbors->execute([':id' => $current_prop_id, ':district' => $district_trim]);
        $same_area_properties = lh_property_apply_locale_list($stmtNeighbors->fetchAll(), $pdo);
        if ($same_area_properties !== []) {
            $same_area_see_more_url = lh_locale_url('properties.php?' . http_build_query(['district' => $district_trim]));
            $same_area_label = lh_location_label($district_trim);
        }
    } elseif ($city_trim !== '') {
        $stmtNeighbors = $pdo->prepare(
            'SELECT * FROM properties WHERE is_active = 1 AND id != :id AND city = :city ORDER BY created_at DESC LIMIT 3'
        );
        $stmtNeighbors->execute([':id' => $current_prop_id, ':city' => $city_trim]);
        $same_area_properties = lh_property_apply_locale_list($stmtNeighbors->fetchAll(), $pdo);
        if ($same_area_properties !== []) {
            $same_area_see_more_url = lh_locale_url('properties.php?' . http_build_query(['city' => $city_trim]));
            $same_area_label = lh_location_label($city_trim);
        }
    }

} catch (Exception $e) {
    error_log('property-details error: ' . $e->getMessage());
    die(__('page.property.server_error'));
}

$images = !empty($property['image_name']) ? array_filter(explode(',', $property['image_name'])) : [];
$propertyIdForImages = (int) ($property['id'] ?? 0);

$lhPropTitleRaw = trim((string) ($property['title'] ?? __('card.fallback_title')));
if ($lhPropTitleRaw === '') {
    $lhPropTitleRaw = __('card.fallback_title');
}
$pageTitle = $lhPropTitleRaw . ' — Like HOME';

$descSource = trim((string) ($property['description_long'] ?? ''));
if ($descSource === '') {
    $descSource = trim((string) ($property['description'] ?? ''));
}
if ($descSource === '') {
    $locHint = trim(implode(', ', array_filter([
        trim((string) ($property['district'] ?? '')),
        trim((string) ($property['city'] ?? '')),
    ])));
    $descSource = $lhPropTitleRaw
        . ($locHint !== '' ? ' — ' . $locHint : '')
        . '. Cazare de închiriat în Moldova; verifică disponibilitatea și rezervă direct prin Like HOME.';
}
$pageDescription = lh_seo_meta_plain($descSource);

$slugCanon = trim((string) ($property['slug'] ?? ''));
$detailsQuery = $slugCanon !== ''
    ? http_build_query(['slug' => $slugCanon])
    : http_build_query(['id' => (int) ($property['id'] ?? 0)]);
$canonicalUrl = lh_absolute_locale_url('property-details.php?' . $detailsQuery);
$lhLocaleAlternateUrls = lh_property_locale_alternate_urls($property);

$ogImage = '';
if ($images !== []) {
    $firstImg = trim((string) ($images[0]));
    if ($firstImg !== '') {
        $ogImage = lh_absolute_href(lh_property_image_url($propertyIdForImages, $firstImg, 'full'));
    }
}

$ldAddress = [
    '@type' => 'PostalAddress',
    'addressCountry' => 'MD',
];
$cityLd = trim((string) ($property['city'] ?? ''));
if ($cityLd !== '') {
    $ldAddress['addressLocality'] = $cityLd;
}
$streetLd = trim((string) ($property['address'] ?? ''));
if ($streetLd !== '') {
    $ldAddress['streetAddress'] = $streetLd;
}
$ldGraph = [
    '@context' => 'https://schema.org',
    '@type' => 'LodgingBusiness',
    'name' => $lhPropTitleRaw,
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'inLanguage' => match (lh_current_locale()) {
        'en' => 'en-US',
        'ru' => 'ru-RU',
        default => 'ro-MD',
    },
];
if ($ogImage !== '') {
    $ldGraph['image'] = [$ogImage];
}
$ldGraph['address'] = $ldAddress;
$lhJsonLd = json_encode($ldGraph, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$amenities = json_decode($property['amenities'] ?? '[]', true);
if (!is_array($amenities)) $amenities = [];

$nights = 0;
$total  = 0;
$guests_for_pricing = max(1, (int) ((string) $guests !== '' ? $guests : '1'));

if ($has_checkin && $has_checkout && $check_out > $check_in) {
    $stay = lh_booking_stay_total($property, $check_in, $check_out, $guests_for_pricing);
    $nights = $stay['nights'];
    $total  = $stay['total'];
}

$mapQuery = trim(implode(', ', array_filter([
    trim((string)($property['address'] ?? '')),
    trim((string)($property['district'] ?? '')),
    trim((string)($property['city'] ?? '')),
])));

$mapsEmbedKey = trim(lh_env('GOOGLE_MAPS_EMBED_API_KEY', ''));
if ($mapQuery !== '') {
    if ($mapsEmbedKey !== '') {
        $mapIframeSrc = 'https://www.google.com/maps/embed/v1/place?key=' . rawurlencode($mapsEmbedKey)
            . '&q=' . rawurlencode($mapQuery);
    } else {
        $mapIframeSrc = 'https://www.google.com/maps?q=' . rawurlencode($mapQuery) . '&output=embed';
    }
} else {
    $mapIframeSrc = '';
}

$priceFormatted = lh_format_money((float) $property['price'], 0);
$locationLineParts = array_filter([
    trim((string)($property['city'] ?? '')),
    trim((string)($property['address'] ?? '')),
]);
$locationLine = $locationLineParts ? implode(' · ', $locationLineParts) : '';
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

<style>
  /*
   * Desktop îngust pe verticală: același UX ca mobil (bară + sheet). Coloana desktop (viewport înalt)
   * folosește sticky fără max-height — widgetul crește cu conținutul; derularea e doar pe pagină.
   * LH_PD_BOOKING_MIN_VIEWPORT_HEIGHT_PX (761): de la această înălțime în sus se afișează coloana desktop.
   */
  @media (min-width: 1024px) and (max-height: 760px) {
    #lh-pd-main-wrap {
      padding-bottom: 7rem;
    }
    #lh-pd-main-col {
      grid-column: 1 / -1;
    }
    #lh-booking-desktop-col {
      display: none !important;
    }
    #lh-pd-back-link {
      display: inline-block !important;
    }
    #lh-booking-mobile-bar {
      display: block !important;
    }
    #lh-booking-overlay {
      display: block !important;
    }
    #lh-booking-sheet {
      display: flex !important;
      left: 0;
      right: 0;
      margin-left: auto;
      margin-right: auto;
      max-width: 32rem;
      width: calc(100% - 2rem);
    }
  }

  #lh-gallery-lightbox .lh-gallery-lightbox-backdrop {
    background: rgba(0, 0, 0, 0.88);
    -webkit-backdrop-filter: blur(18px);
    backdrop-filter: blur(18px);
  }

  #lh-gallery-lightbox .lh-gallery-lightbox-swiper .swiper-slide {
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    background: rgb(12 12 12);
    overflow: hidden;
  }

  #lh-gallery-lightbox .lh-gallery-lightbox-swiper .swiper-zoom-container {
    width: 100%;
    height: 100%;
    min-height: 0;
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
  }

  #lh-gallery-lightbox .lh-gallery-lightbox-swiper .swiper-zoom-container img {
    max-width: 100%;
    max-height: min(85dvh, 85vh);
    width: auto;
    height: auto;
    object-fit: contain;
    position: relative;
    z-index: 1;
  }

  #lh-gallery-lightbox .lh-lb-zoom-tools {
    pointer-events: auto;
  }

  #lh-gallery-lightbox .lh-gallery-lightbox-swiper .swiper-pagination-fraction {
    color: rgba(255, 255, 255, 0.92);
    font-weight: 600;
    font-size: 0.875rem;
    padding-bottom: max(0.5rem, env(safe-area-inset-bottom));
  }

  /*
   * Tailwind .flex pe același nod cu [hidden] poate suprascrie display:none → lightbox vizibil la load,
   * iar lhCloseGalleryLightbox() iese devreme (hasAttribute('hidden') rămâne true). Forțăm ascunderea.
   */
  #lh-gallery-lightbox[hidden] {
    display: none !important;
    pointer-events: none !important;
  }

  /* Galerie: miniaturi în 2 rânduri, scroll orizontal (property-details) */
  #lh-pd-thumbs {
    --lh-pd-thumb-w: 4.5rem;
    --lh-pd-thumb-h: 3.375rem;
    --lh-pd-thumb-gap-x: 0.5rem;
    --lh-pd-thumb-gap-y: 0.5rem;
  }
  @media (min-width: 640px) {
    #lh-pd-thumbs {
      --lh-pd-thumb-w: 5.25rem;
      --lh-pd-thumb-h: 4rem;
      --lh-pd-thumb-gap-x: 0.625rem;
      --lh-pd-thumb-gap-y: 0.625rem;
    }
  }
  @media (min-width: 768px) {
    #lh-pd-thumbs {
      --lh-pd-thumb-w: 6rem;
      --lh-pd-thumb-h: 5rem;
    }
  }
  #lh-pd-thumbs .lh-pd-thumbs-scroll {
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-x: contain;
    scroll-snap-type: x proximity;
  }
  #lh-pd-thumbs .lh-pd-thumbs-grid {
    display: grid;
    grid-template-rows: repeat(2, var(--lh-pd-thumb-h));
    grid-auto-flow: column;
    grid-auto-columns: var(--lh-pd-thumb-w);
    column-gap: var(--lh-pd-thumb-gap-x);
    row-gap: var(--lh-pd-thumb-gap-y);
    width: max-content;
    min-height: calc(var(--lh-pd-thumb-h) * 2 + var(--lh-pd-thumb-gap-y));
  }
  #lh-pd-thumbs .lh-pd-thumbs-cell {
    scroll-snap-align: start;
    min-width: 0;
    min-height: 0;
  }
  #lh-pd-thumbs .lh-pd-thumbs-cell--active {
    box-shadow: 0 0 0 2px rgb(var(--color-cta) / 0.95);
  }

  /* Flatpickr (rezervare): preț sub zi + celule mai înalte */
  .flatpickr-day {
    min-height: 2.85rem;
    height: auto;
    max-height: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    padding-top: 0.2rem;
    line-height: 1.15;
  }
  /* Widget total (#totalBox): rând final total; font scalat cu lățimea containerului (cqi) */
  #totalBox {
    container-type: inline-size;
    container-name: lhBookTotal;
  }
  #totalBox .lh-total-pricing-row {
    display: flex;
    flex-flow: row nowrap;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
    min-width: 0;
  }
  #totalBox .lh-total-pricing-label {
    flex-shrink: 0;
    white-space: nowrap;
  }
  #totalBox .lh-total-pricing-value {
    min-width: 0;
    text-align: right;
    white-space: nowrap;
    line-height: 1.15;
  }
  #totalBox .lh-total-pricing-value--total {
    font-size: clamp(0.75rem, calc(0.35rem + 5cqi), 1.05rem);
  }

  /* Modal confirmare: același principiu (container = panoul dialog) */
  .lh-booking-confirm-panel {
    container-type: inline-size;
    container-name: lhBookConfirm;
  }
  .lh-booking-confirm-panel .lh-confirm-pricing-row {
    display: flex;
    flex-flow: row nowrap;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
    min-width: 0;
  }
  .lh-booking-confirm-panel .lh-confirm-pricing-label {
    flex-shrink: 0;
    white-space: nowrap;
  }
  .lh-booking-confirm-panel #lh-confirm-total {
    min-width: 0;
    text-align: right;
    white-space: nowrap;
    line-height: 1.15;
    font-size: clamp(0.58rem, calc(0.28rem + 5cqi), 1rem);
  }

  .flatpickr-day .lh-cal-day-price {
    display: block;
    font-size: 0.625rem;
    font-weight: 600;
    color: rgb(100 116 139);
    margin-top: 0.1rem;
    line-height: 1.1;
    pointer-events: none;
  }
  .flatpickr-day.flatpickr-disabled .lh-cal-day-price {
    opacity: 0.4;
  }
  /* Zi validă doar ca check-out (turn-over în aceeași zi); nu e flatpickr-disabled */
  .flatpickr-day.lh-cal-checkout-only:not(.flatpickr-disabled) {
    box-shadow: inset 0 0 0 1px rgb(148 163 184 / 0.65);
  }
  .flatpickr-day.lh-cal-checkout-only:not(.flatpickr-disabled) .lh-cal-day-price {
    color: rgb(71 85 105);
  }
</style>

<div id="lh-pd-main-wrap" class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 pb-28 lg:pb-0">

<a href="<?= htmlspecialchars(lh_locale_url(), ENT_QUOTES, 'UTF-8') ?>" id="lh-pd-back-link" class="lg:hidden text-sm text-cta/80 hover:text-ink transition-colors mb-6 inline-block">← <?= htmlspecialchars(__('booking.back'), ENT_QUOTES, 'UTF-8') ?></a>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-10 lg:items-start">

<!-- MAIN COLUMN: header → gallery → features → description → amenities -->
<div id="lh-pd-main-col" class="lg:col-span-2 space-y-10 min-w-0">

<!-- PROPERTY HEADER (title + address above gallery on all breakpoints) -->
<div class="space-y-2 lg:space-y-3">
<h1 class="text-3xl sm:text-4xl font-black tracking-tight text-ink">
<?= htmlspecialchars($lhPropTitleRaw, ENT_QUOTES, 'UTF-8') ?>
</h1>
<?php
$addressDisplay = $locationLine !== '' ? $locationLine : (trim((string)($property['location'] ?? '')) ?: '');
?>
<?php if ($addressDisplay !== ''): ?>
<p class="text-sm lg:text-[0.9375rem] text-blue-grey font-medium leading-relaxed max-w-3xl">
<?= htmlspecialchars($addressDisplay, ENT_QUOTES, 'UTF-8') ?>
</p>
<?php endif; ?>
</div>

<!-- GALLERY -->
<?php if (!empty($images)): ?>
<div class="swiper mainSlider rounded-3xl overflow-hidden shadow-xl shadow-black/10 border border-black/5 bg-zinc-900">
<div class="swiper-wrapper">
<?php foreach ($images as $img): ?>
<div class="swiper-slide">
<div class="relative w-full aspect-[4/3] bg-zinc-900">
<img src="<?= htmlspecialchars(lh_property_image_url($propertyIdForImages, trim($img), 'full'), ENT_QUOTES, 'UTF-8') ?>" class="absolute inset-0 w-full h-full object-cover object-center cursor-zoom-in" alt="" decoding="async">
</div>
</div>
<?php endforeach; ?>
</div>
<div class="swiper-pagination"></div>
</div>
<?php else: ?>
<div class="rounded-3xl border border-black/5 bg-white/80 flex items-center justify-center h-[min(280px,40vh)] text-blue-grey font-semibold shadow-inner">
Fără imagini
</div>
<?php endif; ?>

<?php if (count($images) > 1): ?>
<div id="lh-pd-thumbs" class="mt-3">
<div class="lh-pd-thumbs-scroll">
<div class="lh-pd-thumbs-grid">
<?php foreach ($images as $idx => $img): ?>
<div class="lh-pd-thumbs-cell cursor-pointer rounded-xl overflow-hidden border border-black/5 hover:border-cta/40 transition bg-white/80"
     data-thumb-index="<?= (int)$idx ?>"
     role="button"
     tabindex="0"
     aria-label="<?= htmlspecialchars(__('booking.image_n', ['n' => (string) ((int) $idx + 1)]), ENT_QUOTES, 'UTF-8') ?>">
<img src="<?= htmlspecialchars(lh_property_image_url($propertyIdForImages, trim($img), 'thumb'), ENT_QUOTES, 'UTF-8') ?>"
     class="block h-full w-full object-cover" alt="" loading="lazy">
</div>
<?php endforeach; ?>
</div>
</div>
</div>
<?php endif; ?>

<!-- KEY FEATURES -->
<div class="border-y border-black/6 py-5">
<div class="grid grid-cols-3 gap-3 sm:gap-6">
<div class="flex items-center gap-2 sm:gap-3 min-w-0">
<div class="w-9 h-9 sm:w-10 sm:h-10 shrink-0 bg-brand-100 rounded-xl flex items-center justify-center text-cta/70 border border-black/8">
<i data-lucide="users" class="w-4 h-4 sm:w-5 sm:h-5"></i>
</div>
<div class="min-w-0">
<p class="text-xs text-blue-grey leading-tight"><?= htmlspecialchars(__('booking.capacity'), ENT_QUOTES, 'UTF-8') ?></p>
<p class="font-bold text-sm leading-tight"><?= (int)$property['sleep_capacity'] ?> <?= htmlspecialchars(__('booking.persons_abbr'), ENT_QUOTES, 'UTF-8') ?></p>
</div>
</div>
<div class="flex items-center gap-2 sm:gap-3 min-w-0">
<div class="w-9 h-9 sm:w-10 sm:h-10 shrink-0 bg-brand-100 rounded-xl flex items-center justify-center text-cta/70 border border-black/8">
<i data-lucide="bed-double" class="w-4 h-4 sm:w-5 sm:h-5"></i>
</div>
<div class="min-w-0">
<p class="text-xs text-blue-grey leading-tight"><?= htmlspecialchars(__('booking.rooms'), ENT_QUOTES, 'UTF-8') ?></p>
<p class="font-bold text-sm leading-tight"><?= (int)$property['rooms'] ?></p>
</div>
</div>
<div class="flex items-center gap-2 sm:gap-3 min-w-0">
<div class="w-9 h-9 sm:w-10 sm:h-10 shrink-0 bg-brand-100 rounded-xl flex items-center justify-center text-cta/70 border border-black/8">
<i data-lucide="maximize" class="w-4 h-4 sm:w-5 sm:h-5"></i>
</div>
<div class="min-w-0">
<p class="text-xs text-blue-grey leading-tight"><?= htmlspecialchars(__('booking.area'), ENT_QUOTES, 'UTF-8') ?></p>
<p class="font-bold text-sm leading-tight"><?= (int)$property['area_sqm'] ?> m²</p>
</div>
</div>
</div>
</div>

<!-- DESCRIPTION -->
<div>
<h2 class="text-2xl font-bold mb-4"><?= htmlspecialchars(__('booking.about_property'), ENT_QUOTES, 'UTF-8') ?></h2>
<p class="text-ink/80 leading-relaxed whitespace-pre-line">
<?= htmlspecialchars($property['description_long'] ?? $property['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>
</p>
</div>

<!-- AMENITIES (icons + labels match admin Facilități & Dotări catalog) -->
<?php if (!empty($amenities)): ?>
<div>
<h2 class="text-2xl font-bold mb-6"><?= htmlspecialchars(__('booking.amenities'), ENT_QUOTES, 'UTF-8') ?></h2>
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 md:gap-4">
<?php foreach ($amenities as $a):
    if (!is_string($a) && !is_int($a)) {
        continue;
    }
    [$amenityLabel, $amenityIcon] = lh_property_amenity_resolve((string) $a);
    if ($amenityLabel === '') {
        continue;
    }
    ?>
<div class="flex items-center gap-3 bg-white/75 border border-black/5 p-4 rounded-2xl hover:bg-brand-50 transition backdrop-blur-sm">
<div class="w-8 h-8 bg-brand-100 rounded-lg flex items-center justify-center text-cta/70 border border-black/8 shrink-0">
<i data-lucide="<?= htmlspecialchars($amenityIcon, ENT_QUOTES, 'UTF-8') ?>" class="w-4 h-4"></i>
</div>
<span class="text-sm font-medium text-ink/85">
<?= htmlspecialchars($amenityLabel, ENT_QUOTES, 'UTF-8') ?>
</span>
</div>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>

</div>

<!-- DESKTOP: sticky booking column (ascuns la lg + viewport scurt; vezi LH_PD_BOOKING_MIN_VIEWPORT_HEIGHT_PX) -->
<div id="lh-booking-desktop-col" class="hidden lg:block lg:sticky lg:top-24 lg:self-start min-w-0">
<div id="lh-booking-desktop-slot">
<?php include __DIR__ . '/includes/property_booking_widget.php'; ?>
</div>
</div>

</div>

<!-- MAP (full content width below grid) -->
<section class="mt-10 md:mt-14 min-w-0 max-w-full" aria-labelledby="lh-map-heading">
<h2 id="lh-map-heading" class="text-2xl font-bold mb-4 flex items-center gap-2">
<span class="w-10 h-10 bg-brand-100 rounded-xl flex items-center justify-center text-cta/70 border border-black/8 shrink-0" aria-hidden="true">
<i data-lucide="map-pin" class="w-5 h-5"></i>
</span>
<?= htmlspecialchars(__('booking.location'), ENT_QUOTES, 'UTF-8') ?>
</h2>
<?php if ($mapIframeSrc !== ''): ?>
<div class="relative w-full max-w-full overflow-hidden rounded-2xl border border-black/10 bg-surface h-[220px] sm:h-[280px] md:h-[360px] lg:h-[400px]">
<iframe
  title="<?= htmlspecialchars(__('booking.map_title', ['property' => $lhPropTitleRaw]), ENT_QUOTES, 'UTF-8') ?>"
  class="absolute inset-0 block h-full w-full border-0"
  src="<?= htmlspecialchars($mapIframeSrc, ENT_QUOTES, 'UTF-8') ?>"
  loading="lazy"
  referrerpolicy="strict-origin-when-cross-origin"
  allowfullscreen></iframe>
</div>
<?php else: ?>
<p class="text-blue-grey text-sm py-8 px-4 rounded-2xl border border-black/8 bg-white/60 text-center">
<?= htmlspecialchars(__('booking.map_unavailable'), ENT_QUOTES, 'UTF-8') ?>
</p>
<?php endif; ?>
</section>

<?php if (!empty($same_area_properties)): ?>
<section class="mt-10 md:mt-14 min-w-0 max-w-full" aria-labelledby="lh-same-area-heading">
<h2 id="lh-same-area-heading" class="text-2xl font-bold mb-2"><?= htmlspecialchars(__('booking.same_area'), ENT_QUOTES, 'UTF-8') ?></h2>
<?php if ($same_area_label !== ''): ?>
<p class="text-sm text-blue-grey font-medium mb-6"><?= htmlspecialchars($same_area_label, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
<?php foreach ($same_area_properties as $neighbor): ?>
<?= render_property_card($neighbor, $check_in, $check_out, $guests) ?>
<?php endforeach; ?>
</div>
<div class="flex justify-center sm:justify-start">
<a href="<?= htmlspecialchars($same_area_see_more_url, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center gap-2 bg-white border-2 border-cta text-cta hover:bg-brand-50 font-bold px-8 py-3.5 rounded-2xl transition-colors shadow-sm">
<?= htmlspecialchars(__('booking.see_more'), ENT_QUOTES, 'UTF-8') ?>
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
</svg>
</a>
</div>
</section>
<?php endif; ?>

</div>

<!-- MOBILE (și desktop viewport scurt): bară fixă jos -->
<div id="lh-booking-mobile-bar" class="lg:hidden fixed bottom-0 inset-x-0 z-[90] border-t border-black/8 bg-white/95 premium-header-blur px-4 sm:px-6 lg:px-8 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-8px_30px_rgb(0_0_0/0.08)]">
<div class="max-w-6xl mx-auto flex items-center justify-between gap-2">
<div class="min-w-0 flex-1">
<p class="flex flex-nowrap items-baseline gap-x-0.5 min-w-0 whitespace-nowrap text-lg font-black text-ink tabular-nums leading-none"><span class="text-xs sm:text-sm font-bold text-blue-grey shrink-0"><?= htmlspecialchars(__('booking.mobile_from'), ENT_QUOTES, 'UTF-8') ?> </span><?= htmlspecialchars($priceFormatted, ENT_QUOTES, 'UTF-8') ?> <span class="text-xs sm:text-sm font-bold text-blue-grey shrink-0"><?= htmlspecialchars(__('booking.per_night'), ENT_QUOTES, 'UTF-8') ?></span></p>
</div>
<button type="button" id="lh-open-booking-sheet" class="shrink-0 inline-flex items-center justify-center gap-1.5 bg-cta hover:brightness-110 text-white px-4 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-black/10 transition-all">
<?= htmlspecialchars(__('booking.book_now'), ENT_QUOTES, 'UTF-8') ?>
</button>
</div>
</div>

<!-- MOBILE: bottom sheet -->
<div id="lh-booking-overlay" class="fixed inset-0 z-[100] bg-black/40 opacity-0 pointer-events-none transition-opacity duration-200 lg:hidden" aria-hidden="true"></div>
<div
  id="lh-booking-sheet"
  class="fixed inset-x-0 bottom-0 z-[101] max-h-[min(88dvh,840px)] rounded-t-3xl border border-black/10 bg-white shadow-2xl translate-y-full transition-transform duration-300 ease-out lg:hidden flex flex-col"
  role="dialog"
  aria-modal="true"
  aria-labelledby="lh-booking-sheet-title"
  aria-hidden="true">
<div class="shrink-0 flex items-center justify-between gap-3 px-5 pt-[max(1rem,env(safe-area-inset-top))] pb-2 border-b border-black/6">
<h2 id="lh-booking-sheet-title" class="text-lg font-black text-ink tracking-tight"><?= htmlspecialchars(__('booking.booking_title'), ENT_QUOTES, 'UTF-8') ?></h2>
<button type="button" id="lh-close-booking-sheet" class="p-2 rounded-xl text-blue-grey hover:bg-brand-100 hover:text-ink transition-colors" aria-label="<?= htmlspecialchars(__('booking.close'), ENT_QUOTES, 'UTF-8') ?>">
<i data-lucide="x" class="w-6 h-6"></i>
</button>
</div>
<div id="lh-booking-sheet-body" class="flex-1 overflow-y-auto overscroll-contain px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))]"></div>
</div>

<div id="lh-booking-toast" class="lh-toast" role="status" aria-live="polite"></div>

<div id="lh-booking-confirm-root" class="lh-booking-confirm-root" hidden aria-hidden="true">
<div class="lh-booking-confirm-overlay" id="lh-booking-confirm-overlay"></div>
<div
  class="lh-booking-confirm-panel p-6 sm:p-8"
  role="dialog"
  aria-modal="true"
  aria-labelledby="lh-booking-confirm-title">
<h2 id="lh-booking-confirm-title" class="text-lg font-black text-ink tracking-tight"><?= htmlspecialchars(__('booking.confirm_title'), ENT_QUOTES, 'UTF-8') ?></h2>
<p class="text-sm text-blue-grey font-medium mt-1 mb-4"><?= htmlspecialchars(__('booking.confirm_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
<dl class="space-y-2 text-sm mb-6">
<div class="flex flex-col gap-0.5 border-b border-black/6 pb-2"><dt class="text-blue-grey font-semibold text-xs uppercase tracking-wide"><?= htmlspecialchars(__('booking.confirm_property'), ENT_QUOTES, 'UTF-8') ?></dt><dd id="lh-confirm-property" class="font-bold text-ink"></dd></div>
<div class="flex flex-col gap-0.5 border-b border-black/6 pb-2"><dt class="text-blue-grey font-semibold text-xs uppercase tracking-wide"><?= htmlspecialchars(__('booking.confirm_period'), ENT_QUOTES, 'UTF-8') ?></dt><dd id="lh-confirm-period" class="font-medium text-ink"></dd></div>
<div class="flex flex-col gap-0.5 border-b border-black/6 pb-2"><dt class="text-blue-grey font-semibold text-xs uppercase tracking-wide"><?= htmlspecialchars(__('booking.confirm_guests'), ENT_QUOTES, 'UTF-8') ?></dt><dd id="lh-confirm-guests" class="font-medium text-ink"></dd></div>
<div id="lh-confirm-price-break" class="hidden border-b border-black/6 pb-2 text-sm space-y-1.5">
<p class="text-xs font-semibold text-blue-grey uppercase tracking-wide m-0"><?= htmlspecialchars(__('booking.subtotal'), ENT_QUOTES, 'UTF-8') ?></p>
<div id="lh-confirm-base-line" class="font-medium text-ink tabular-nums leading-snug"></div>
<div id="lh-confirm-discount-line" class="hidden font-medium text-emerald-800 tabular-nums leading-snug"></div>
<div id="lh-confirm-coupon-line" class="hidden font-medium text-emerald-800 tabular-nums leading-snug"></div>
<div id="lh-confirm-extra-line" class="hidden font-medium text-ink tabular-nums leading-snug"></div>
<p id="lh-confirm-extra-note" class="hidden text-[10px] text-blue-grey font-medium leading-snug m-0"></p>
</div>
<div class="lh-confirm-pricing-row border-b border-black/6 pb-2">
<dt class="text-blue-grey font-semibold text-xs uppercase tracking-wide lh-confirm-pricing-label"><?= htmlspecialchars(__('booking.confirm_total_label'), ENT_QUOTES, 'UTF-8') ?></dt>
<dd id="lh-confirm-total" class="font-bold text-cta m-0 tabular-nums"></dd>
</div>
<div class="flex flex-col gap-0.5 border-b border-black/6 pb-2"><dt class="text-blue-grey font-semibold text-xs uppercase tracking-wide"><?= htmlspecialchars(__('booking.confirm_name'), ENT_QUOTES, 'UTF-8') ?></dt><dd id="lh-confirm-name" class="font-medium text-ink break-words"></dd></div>
<div class="flex flex-col gap-0.5 border-b border-black/6 pb-2"><dt class="text-blue-grey font-semibold text-xs uppercase tracking-wide"><?= htmlspecialchars(__('booking.confirm_phone'), ENT_QUOTES, 'UTF-8') ?></dt><dd id="lh-confirm-phone" class="font-medium text-ink"></dd></div>
<div class="flex flex-col gap-0.5 pb-1"><dt class="text-blue-grey font-semibold text-xs uppercase tracking-wide"><?= htmlspecialchars(__('booking.confirm_email'), ENT_QUOTES, 'UTF-8') ?></dt><dd id="lh-confirm-email" class="font-medium text-ink break-all"></dd></div>
</dl>
<div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end">
<button type="button" id="lh-booking-confirm-back" class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-3.5 rounded-xl font-bold border-2 border-black/10 text-ink hover:bg-brand-50 transition-colors"><?= htmlspecialchars(__('booking.confirm_back'), ENT_QUOTES, 'UTF-8') ?></button>
<button type="button" id="lh-booking-confirm-submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-cta hover:brightness-110 text-white px-5 py-3.5 rounded-xl font-bold transition-all"><?= htmlspecialchars(__('booking.confirm_submit'), ENT_QUOTES, 'UTF-8') ?></button>
</div>
</div>
</div>

<div id="lh-booking-success-banner" class="lh-booking-success-banner" hidden role="status" aria-live="polite">
<div class="lh-booking-success-banner__inner">
<div class="min-w-0 flex-1">
<strong><?= htmlspecialchars(__('booking.success_title'), ENT_QUOTES, 'UTF-8') ?></strong>
<p id="lh-booking-success-text" class="text-blue-grey font-medium"></p>
</div>
<button type="button" class="lh-booking-success-banner__close" id="lh-booking-success-close"><?= htmlspecialchars(__('booking.success_close'), ENT_QUOTES, 'UTF-8') ?></button>
</div>
</div>

<?php if (!empty($images)): ?>
<div
  id="lh-gallery-lightbox"
  class="fixed inset-0 z-[120] flex flex-col bg-transparent"
  hidden
  role="dialog"
  aria-modal="true"
  aria-label="<?= htmlspecialchars(__('booking.gallery_aria'), ENT_QUOTES, 'UTF-8') ?>"
  aria-hidden="true">
  <div class="absolute inset-0 z-0 cursor-pointer lh-gallery-lightbox-backdrop" id="lh-gallery-lightbox-backdrop" aria-hidden="true"></div>
  <button
    type="button"
    id="lh-gallery-lightbox-close"
    class="absolute top-3 right-3 z-20 inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 border border-white/20 transition-colors pointer-events-auto"
    aria-label="<?= htmlspecialchars(__('booking.gallery_close'), ENT_QUOTES, 'UTF-8') ?>">
    <i data-lucide="x" class="w-6 h-6" aria-hidden="true"></i>
  </button>
  <div class="lh-gallery-lightbox-inner relative z-10 flex flex-1 min-h-0 w-full flex-col pt-14 pb-2 px-2 sm:px-4 pointer-events-none">
    <div class="swiper lh-gallery-lightbox-swiper flex-1 min-h-0 w-full pointer-events-auto">
      <div class="swiper-wrapper">
        <?php foreach ($images as $img): ?>
        <div class="swiper-slide">
          <div class="swiper-zoom-container">
            <img src="<?= htmlspecialchars(lh_property_image_url($propertyIdForImages, trim($img), 'full'), ENT_QUOTES, 'UTF-8') ?>" alt="" decoding="async">
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="lh-lb-zoom-tools flex shrink-0 items-center justify-center gap-2 pt-2" role="toolbar" aria-label="<?= htmlspecialchars(__('booking.zoom_toolbar'), ENT_QUOTES, 'UTF-8') ?>">
        <button
          type="button"
          id="lh-lb-zoom-out"
          class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 border border-white/20 transition-colors"
          aria-label="<?= htmlspecialchars(__('booking.zoom_out'), ENT_QUOTES, 'UTF-8') ?>">
          <i data-lucide="zoom-out" class="w-5 h-5" aria-hidden="true"></i>
        </button>
        <button
          type="button"
          id="lh-lb-zoom-in"
          class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 border border-white/20 transition-colors"
          aria-label="<?= htmlspecialchars(__('booking.zoom_in'), ENT_QUOTES, 'UTF-8') ?>">
          <i data-lucide="zoom-in" class="w-5 h-5" aria-hidden="true"></i>
        </button>
      </div>
      <div class="swiper-pagination shrink-0 pt-2" id="lh-gallery-lightbox-pagination"></div>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<?php
$lhPdFpJs = match (lh_current_locale()) {
    'en' => 'en',
    'ru' => 'ru',
    default => 'ro',
};
if ($lhPdFpJs === 'ro' || $lhPdFpJs === 'ru'): ?>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/<?= htmlspecialchars($lhPdFpJs, ENT_QUOTES, 'UTF-8') ?>.js"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/i18n_js.php'; lh_i18n_script_tags(); ?>
<script>
var ajaxBookedDates = <?= json_encode(lh_public_url('ajax/get_booked_dates.php'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var ajaxCreateBooking = <?= json_encode(lh_public_url('ajax/create_booking.php'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var ajaxBookingPricePreview = <?= json_encode(lh_public_url('ajax/booking_price_preview.php'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

var mainSwiper = null;
var lightboxSwiper = null;
var lhGalleryLightboxRoot = document.getElementById('lh-gallery-lightbox');
var lhGalleryLightboxLastFocus = null;
var lhGalleryLightboxBodyOverflow = '';

function lhCloseGalleryLightbox() {
  if (!lhGalleryLightboxRoot || lhGalleryLightboxRoot.hasAttribute('hidden')) return;
  lhLbZoomReset();
  if (mainSwiper && lightboxSwiper) {
    mainSwiper.slideTo(lightboxSwiper.activeIndex, 0);
  }
  lhGalleryLightboxRoot.setAttribute('hidden', '');
  lhGalleryLightboxRoot.setAttribute('aria-hidden', 'true');
  document.body.style.overflow = lhGalleryLightboxBodyOverflow;
  var prev = lhGalleryLightboxLastFocus;
  lhGalleryLightboxLastFocus = null;
  if (prev && typeof prev.focus === 'function') {
    try {
      prev.focus();
    } catch (err) {}
  }
}

function lhLbZoomReset() {
  if (lightboxSwiper && lightboxSwiper.zoom && typeof lightboxSwiper.zoom.out === 'function') {
    lightboxSwiper.zoom.out();
  }
}

function lhOpenGalleryLightbox(index) {
  if (!lhGalleryLightboxRoot || !lightboxSwiper) return;
  lhGalleryLightboxLastFocus = document.activeElement;
  lhGalleryLightboxRoot.removeAttribute('hidden');
  lhGalleryLightboxRoot.setAttribute('aria-hidden', 'false');
  lhGalleryLightboxBodyOverflow = document.body.style.overflow;
  document.body.style.overflow = 'hidden';
  var i = typeof index === 'number' && index >= 0 ? index : 0;
  lightboxSwiper.slideTo(i, 0);
  lhLbZoomReset();
  lightboxSwiper.update();
  requestAnimationFrame(function () {
    lightboxSwiper.update();
  });
  var closeBtn = document.getElementById('lh-gallery-lightbox-close');
  if (closeBtn) {
    requestAnimationFrame(function () {
      closeBtn.focus();
    });
  }
  lhRefreshLucide();
}

var lbSwiperEl = document.querySelector('.lh-gallery-lightbox-swiper');
if (lbSwiperEl && lbSwiperEl.querySelector('.swiper-slide')) {
  lightboxSwiper = new Swiper('.lh-gallery-lightbox-swiper', {
    slidesPerView: 1,
    spaceBetween: 12,
    keyboard: { enabled: true },
    zoom: {
      maxRatio: 3,
      minRatio: 1,
      toggle: true
    },
    pagination: {
      el: '#lh-gallery-lightbox-pagination',
      type: 'fraction'
    },
    on: {
      slideChange: function () {
        lhLbZoomReset();
      }
    }
  });

  var zIn = document.getElementById('lh-lb-zoom-in');
  var zOut = document.getElementById('lh-lb-zoom-out');
  if (zIn) {
    zIn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (lightboxSwiper && lightboxSwiper.zoom && typeof lightboxSwiper.zoom.in === 'function') {
        lightboxSwiper.zoom.in();
      }
    });
  }
  if (zOut) {
    zOut.addEventListener('click', function (e) {
      e.stopPropagation();
      lhLbZoomReset();
    });
  }
}

function lhPdSyncThumbStrip(activeIdx) {
  var root = document.getElementById('lh-pd-thumbs');
  if (!root) return;
  var cells = root.querySelectorAll('[data-thumb-index]');
  var i;
  for (i = 0; i < cells.length; i++) {
    var el = cells[i];
    var idx = parseInt(el.getAttribute('data-thumb-index'), 10);
    if (idx === activeIdx) {
      el.classList.add('lh-pd-thumbs-cell--active');
      try {
        el.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
      } catch (err) {
        el.scrollIntoView(false);
      }
    } else {
      el.classList.remove('lh-pd-thumbs-cell--active');
    }
  }
}

var mainSliderEl = document.querySelector('.mainSlider');
if (mainSliderEl && mainSliderEl.querySelector('.swiper-slide')) {
  mainSwiper = new Swiper(mainSliderEl, {
    pagination: { el: mainSliderEl.querySelector('.swiper-pagination') },
    grabCursor: true,
    preventClicks: false,
    on: {
      slideChange: function (swiper) {
        lhPdSyncThumbStrip(swiper.activeIndex);
      },
      click: function (swiper, event) {
        if (!event || !event.target) return;
        if (event.target.closest && event.target.closest('.swiper-pagination')) return;
        var slide = event.target.closest ? event.target.closest('.swiper-slide') : null;
        if (!slide || !swiper.el.contains(slide)) return;
        var slides = Array.prototype.slice.call(swiper.slides);
        var idx = slides.indexOf(slide);
        if (idx < 0) idx = swiper.activeIndex;
        lhOpenGalleryLightbox(idx);
      }
    }
  });
  lhPdSyncThumbStrip(mainSwiper.activeIndex);
}

if (lhGalleryLightboxRoot) {
  var lhLbClose = document.getElementById('lh-gallery-lightbox-close');
  var lhLbBackdrop = document.getElementById('lh-gallery-lightbox-backdrop');
  if (lhLbClose) {
    lhLbClose.addEventListener('click', function (e) {
      e.stopPropagation();
      lhCloseGalleryLightbox();
    });
  }
  lhGalleryLightboxRoot.addEventListener('click', function (e) {
    if (lhGalleryLightboxRoot.hasAttribute('hidden')) return;
    if (e.target.closest && e.target.closest('#lh-gallery-lightbox-close')) return;
    if (e.target.closest && e.target.closest('.swiper-pagination')) return;
    if (e.target.closest && e.target.closest('img')) return;
    lhCloseGalleryLightbox();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (!lhGalleryLightboxRoot || lhGalleryLightboxRoot.hasAttribute('hidden')) return;
    e.preventDefault();
    lhCloseGalleryLightbox();
  });
}

document.querySelectorAll('[data-thumb-index]').forEach(function (el) {
  var idx = parseInt(el.getAttribute('data-thumb-index'), 10);
  var go = function () {
    if (mainSwiper) mainSwiper.slideTo(idx);
  };
  el.addEventListener('click', go);
  el.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      go();
    }
  });
});

function lhRefreshLucide() {
  if (typeof lucide !== 'undefined') lucide.createIcons();
}
lhRefreshLucide();

function lhFocusNoScroll(el) {
  if (!el || typeof el.focus !== 'function') return;
  try {
    el.focus({ preventScroll: true });
  } catch (err) {
    try {
      el.focus();
    } catch (e2) {}
  }
}

window.LH_CURRENCY = <?= json_encode(lh_currency_client_config(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
function lhCurrencySuffix() {
  var c = window.LH_CURRENCY;
  if (c && c.suffix != null && String(c.suffix) !== '') {
    return String(c.suffix);
  }
  return ' MDL';
}

/** Aligned with Flatpickr altInput (localized month names via lhBookingFpLocale). */
var lhBookingAltFormat = 'd M Y';

function lhFormatCouponLine(code, discountEuro) {
  var amt = discountEuro.toFixed(0) + lhCurrencySuffix();
  if (typeof lhT === 'function') {
    return lhT('booking.coupon_line', { code: String(code).toUpperCase(), amount: amt });
  }
  return '\u00ab' + String(code).toUpperCase() + '\u00bb: -' + amt;
}

var lhPricing = {
  priceStandard: <?= json_encode((float) ($property['price'] ?? 0)) ?>,
  priceWeekend: <?= json_encode(isset($property['price_weekend']) ? (float) $property['price_weekend'] : 0.0) ?>,
  guestsIncluded: <?= json_encode(isset($property['guests_included']) ? (int) $property['guests_included'] : 0) ?>,
  extraGuestPrice: <?= json_encode(isset($property['extra_guest_price']) ? (float) $property['extra_guest_price'] : 0.0) ?>,
  extraGuestUnit: <?= json_encode((string) ($property['extra_guest_unit'] ?? 'per_guest_per_night'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>,
  stayDiscountsGlobal: <?= json_encode($property['_stay_discounts_global'] ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>,
  periods: <?= json_encode(
      array_map(static function (array $r): array {
          $ms = $r['min_stay'] ?? null;

          return [
              'start' => (string) ($r['date_start'] ?? ''),
              'end' => (string) ($r['date_end'] ?? ''),
              'price' => (float) ($r['price'] ?? 0),
              'priceWeekend' => (isset($r['price_weekend']) && $r['price_weekend'] !== null && (float) $r['price_weekend'] > 0)
                  ? (float) $r['price_weekend']
                  : 0,
              'minStay' => ($ms !== null && $ms !== '' && (int) $ms >= 1) ? (int) $ms : null,
              'stayDiscounts' => isset($r['stay_discounts']) && is_array($r['stay_discounts']) ? $r['stay_discounts'] : [],
          ];
      }, $property['_pricing_periods'] ?? []),
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  ) ?>
};

var lhMinStayBase = <?= max(1, (int) ($property['min_stay'] ?? 1)) ?>;

function lhEffectiveMinStay(checkInYmd, checkOutYmd) {
  var base = lhMinStayBase;
  if (!checkInYmd || !checkOutYmd || checkOutYmd <= checkInYmd) {
    return base;
  }
  var periods = lhPricing.periods || [];
  var i;
  for (i = 0; i < periods.length; i++) {
    var pr = periods[i];
    if (lhBookingStayFullyInPeriod(checkInYmd, checkOutYmd, pr)) {
      var ms = pr.minStay;
      if (ms != null && ms >= 1) {
        return ms;
      }
      return base;
    }
  }
  return base;
}

function lhNightsLabel(n) {
  n = parseInt(String(n), 10) || 0;
  if (n === 1) return typeof lhT === 'function' ? lhT('booking.night_one') : '1';
  if (n < 1) return typeof lhT === 'function' ? lhT('booking.nights_zero') : '0';
  return typeof lhT === 'function' ? lhT('booking.nights_count', { n: String(n) }) : String(n);
}

function lhMinStayTooShortMsg(eff) {
  var m = parseInt(String(eff), 10);
  if (!m || m < 1) {
    m = lhMinStayBase;
  }
  if (m === 1) {
    return typeof lhT === 'function' ? lhT('booking.checkout_after_checkin') : '';
  }
  return typeof lhT === 'function' ? lhT('booking.min_stay_property', { n: String(m) }) : String(m);
}

function lhIsWeekendNightStartYmd(ymd) {
  var p = String(ymd).split('-');
  if (p.length !== 3) return false;
  var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
  var w = d.getDay();
  return w === 0 || w === 6;
}

function lhYmdInPricingPeriod(ymd, period) {
  return period.start && period.end && period.start <= ymd && ymd < period.end;
}

function lhNightRateEuroForYmd(ymd) {
  var cfg = lhPricing;
  var periods = cfg.periods || [];
  for (var i = 0; i < periods.length; i++) {
    var pr = periods[i];
    if (lhYmdInPricingPeriod(ymd, pr)) {
      var ps = pr.price;
      var pw = pr.priceWeekend > 0 ? pr.priceWeekend : ps;
      return lhIsWeekendNightStartYmd(ymd) ? pw : ps;
    }
  }
  var std = cfg.priceStandard;
  var wnd = cfg.priceWeekend > 0 ? cfg.priceWeekend : std;
  return lhIsWeekendNightStartYmd(ymd) ? wnd : std;
}

function lhYmdAddOne(ymd) {
  var p = String(ymd).split('-');
  var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
  d.setDate(d.getDate() + 1);
  var mo = String(d.getMonth() + 1).padStart(2, '0');
  var day = String(d.getDate()).padStart(2, '0');
  return d.getFullYear() + '-' + mo + '-' + day;
}

function lhBookingStayFullyInPeriod(checkInYmd, checkOutYmd, period) {
  return period.start && period.end && checkInYmd >= period.start && checkOutYmd <= period.end;
}

function lhSelectStayDiscountRules(checkInYmd, checkOutYmd, periods, globalRules) {
  var i;
  for (i = 0; i < (periods || []).length; i++) {
    if (lhBookingStayFullyInPeriod(checkInYmd, checkOutYmd, periods[i])) {
      return periods[i].stayDiscounts || [];
    }
  }
  return globalRules || [];
}

function lhBookingStayDiscountResult(nights, subtotal, rules) {
  var out = { discount: 0, rule: null };
  if (nights < 1 || !rules || rules.length === 0 || subtotal <= 0) return out;
  var bestMn = null;
  var best = null;
  var i;
  for (i = 0; i < rules.length; i++) {
    var r = rules[i];
    var mn = parseInt(String(r.min_nights), 10) || 0;
    if (nights <= mn) continue;
    if (bestMn === null || mn > bestMn) {
      bestMn = mn;
      best = r;
    }
  }
  if (!best) return out;
  var val = parseFloat(String(best.value).replace(',', '.'));
  if (!val || val <= 0) return out;
  var unit = best.unit === 'fixed_stay' ? 'fixed_stay' : 'percent';
  if (unit === 'fixed_stay') {
    out.discount = Math.min(subtotal, val);
  } else {
    out.discount = Math.min(subtotal, subtotal * (val / 100));
  }
  out.rule = best;
  return out;
}

function lhFormatBaseStayLine(nights, baseEuro, nightlyUniform, uniformRate) {
  if (nights < 1) return '';
  var btxt = baseEuro.toFixed(0) + lhCurrencySuffix();
  var nword = nights === 1 ? lhT('booking.night_word') : lhT('booking.nights_word');
  if (nightlyUniform && uniformRate != null) {
    return lhT('booking.base_stay_uniform', {
      n: nights,
      nword: nword,
      rate: uniformRate.toFixed(0) + lhCurrencySuffix(),
      total: btxt,
    });
  }
  var avg = baseEuro / nights;
  return lhT('booking.base_stay_avg', {
    n: nights,
    avg: avg.toFixed(0) + lhCurrencySuffix(),
    total: btxt,
  });
}

function lhFormatDiscountDisplayLine(discountEuro, rule) {
  if (!rule || discountEuro <= 0.005) return '';
  var val = parseFloat(String(rule.value).replace(',', '.'));
  if (!val || val <= 0) return '';
  var unit = rule.unit === 'fixed_stay' ? 'fixed_stay' : 'percent';
  var mn = parseInt(String(rule.min_nights), 10) || 0;
  var dtxt = '−' + discountEuro.toFixed(0) + lhCurrencySuffix();
  var overPhrase =
    mn < 1 ? '' : lhT('booking.over_nights', { n: mn, word: mn === 1 ? lhT('booking.night_word') : lhT('booking.nights_word') });
  if (unit === 'percent') {
    var rounded = Math.round(val);
    var ptxt = Math.abs(val - rounded) < 1e-6 ? String(rounded) : String(val);
    return lhT('booking.discount_percent', { pct: ptxt, over: overPhrase, amount: dtxt });
  }
  return lhT('booking.discount_fixed', { over: overPhrase, amount: dtxt });
}

function lhFormatExtraGuestMathLine(overGuests, pricePerGuest, nights, extraEuro) {
  if (overGuests < 1 || pricePerGuest <= 0 || nights < 1 || extraEuro <= 0.005) return '';
  var gword = overGuests === 1 ? lhT('booking.guest_singular') : lhT('booking.guests_plural');
  var nword = nights === 1 ? lhT('booking.night_word') : lhT('booking.nights_word');
  return lhT('booking.extra_guest_math', {
    over: overGuests,
    guests: gword,
    price: pricePerGuest.toFixed(0) + lhCurrencySuffix(),
    n: nights,
    nword: nword,
    total: extraEuro.toFixed(0) + lhCurrencySuffix(),
  });
}

function lhBookingLengthDiscountEuro(nights, subtotal, rules) {
  return lhBookingStayDiscountResult(nights, subtotal, rules).discount;
}

function lhExtraGuestNoticeText(guestsInt) {
  var cfg = lhPricing;
  var g = parseInt(String(guestsInt), 10) || 1;
  if (
    cfg.guestsIncluded <= 0 ||
    cfg.extraGuestPrice <= 0 ||
    cfg.extraGuestUnit !== 'per_guest_per_night' ||
    g <= cfg.guestsIncluded
  ) {
    return '';
  }
  var over = g - cfg.guestsIncluded;
  var gword = over === 1 ? lhT('booking.guest_singular') : lhT('booking.guests_plural');
  return lhT('booking.extra_guest_notice', {
    price: cfg.extraGuestPrice.toFixed(0) + lhCurrencySuffix(),
    over: over,
    guests: gword,
    included: cfg.guestsIncluded,
  });
}

function lhBookingStayPricingEuro(checkInYmd, checkOutYmd, guestsInt) {
  var empty = {
    nights: 0,
    baseEuro: 0,
    extraGuestEuro: 0,
    subtotal: 0,
    discount: 0,
    total: 0,
    baseLine: '',
    discountLine: '',
    extraGuestMathLine: '',
    extraGuestNote: '',
  };
  var cfg = lhPricing;
  if (!checkInYmd || !checkOutYmd || checkOutYmd <= checkInYmd) return empty;
  var base = 0;
  var nights = 0;
  var cur = checkInYmd;
  var nightlyUniform = true;
  var firstRate = null;
  while (cur < checkOutYmd) {
    var nightRate = lhNightRateEuroForYmd(cur);
    if (firstRate === null) firstRate = nightRate;
    else if (Math.abs(nightRate - firstRate) > 1e-6) nightlyUniform = false;
    base += nightRate;
    cur = lhYmdAddOne(cur);
    nights++;
  }
  var extra = 0;
  var overGuests = 0;
  if (
    cfg.guestsIncluded > 0 &&
    guestsInt > cfg.guestsIncluded &&
    cfg.extraGuestPrice > 0 &&
    cfg.extraGuestUnit === 'per_guest_per_night'
  ) {
    overGuests = guestsInt - cfg.guestsIncluded;
    extra = overGuests * cfg.extraGuestPrice * nights;
  }
  var subtotal = base + extra;
  var rules = lhSelectStayDiscountRules(checkInYmd, checkOutYmd, cfg.periods || [], cfg.stayDiscountsGlobal || []);
  var dr = lhBookingStayDiscountResult(nights, subtotal, rules);
  var disc = dr.discount;
  var total = Math.max(0, subtotal - disc);
  var baseLine = lhFormatBaseStayLine(nights, base, nightlyUniform, firstRate);
  var discountLine = disc > 0.005 ? lhFormatDiscountDisplayLine(disc, dr.rule) : '';
  var extraMath =
    extra > 0.005
      ? lhFormatExtraGuestMathLine(overGuests, cfg.extraGuestPrice, nights, extra)
      : '';
  return {
    nights: nights,
    baseEuro: base,
    extraGuestEuro: extra,
    subtotal: subtotal,
    discount: disc,
    total: total,
    baseLine: baseLine,
    discountLine: discountLine,
    extraGuestMathLine: extraMath,
    extraGuestNote: lhExtraGuestNoticeText(guestsInt),
  };
}

function lhBookingStayTotalEuro(checkInYmd, checkOutYmd, guestsInt) {
  return lhBookingStayPricingEuro(checkInYmd, checkOutYmd, guestsInt).total;
}

function lhGetGuestsIntForPricing() {
  var sel = document.getElementById('guests');
  var v = sel ? parseInt(String(sel.value), 10) : 1;
  if (!v || v < 1) v = 1;
  return v;
}

var lhLastPricePreview = null;
var lhPricePreviewTimer = null;
var lhPricePreviewAbort = null;

function lhGetCouponRawInput() {
  var el = document.getElementById('booking-coupon-code');
  return el ? String(el.value || '').trim() : '';
}

function lhPricePreviewSyncKey(cinYmd, coutYmd, guestsInt, couponRaw) {
  return cinYmd + '|' + coutYmd + '|' + guestsInt + '|' + couponRaw.toUpperCase();
}

function lhScheduleBookingPricePreview(cinYmd, coutYmd, guestsInt, couponRaw) {
  window.clearTimeout(lhPricePreviewTimer);
  lhPricePreviewTimer = window.setTimeout(function () {
    lhRunBookingPricePreview(cinYmd, coutYmd, guestsInt, couponRaw);
  }, 400);
}

function lhRunBookingPricePreview(cinYmd, coutYmd, guestsInt, couponRaw) {
  if (!ajaxBookingPricePreview || !bookingCsrf) return;
  if (lhPricePreviewAbort) {
    lhPricePreviewAbort.abort();
  }
  lhPricePreviewAbort = typeof AbortController !== 'undefined' ? new AbortController() : null;
  var reqKey = lhPricePreviewSyncKey(cinYmd, coutYmd, guestsInt, couponRaw);
  var body = new URLSearchParams({
    csrf_token: bookingCsrf,
    property_id: String(propertyId),
    check_in: cinYmd,
    check_out: coutYmd,
    guests: String(guestsInt),
    coupon_code: couponRaw,
    locale: window.lhLocale || 'ro',
  });
  var fetchOpts = {
    method: 'POST',
    headers: { Accept: 'application/json' },
    body: body,
  };
  if (lhPricePreviewAbort) fetchOpts.signal = lhPricePreviewAbort.signal;
  window
    .fetch(ajaxBookingPricePreview, fetchOpts)
    .then(function (r) {
      return r.json();
    })
    .then(function (data) {
      if (!data || !data.success) return;
      data._syncKey = reqKey;
      lhLastPricePreview = data;
      lhRefreshCouponPricingUiFromPreview();
    })
    .catch(function (e) {
      if (e && e.name === 'AbortError') return;
    });
}

function lhRefreshCouponPricingUiFromPreview() {
  if (!fpCheckIn || !fpCheckOut || !fpCheckIn.selectedDates || !fpCheckOut.selectedDates) return;
  var cinD = fpCheckIn.selectedDates[0];
  var coutD = fpCheckOut.selectedDates[0];
  if (!cinD || !coutD) return;
  var nights = (coutD - cinD) / 86400000;
  if (nights < 1) return;
  var cinY = fpCheckIn.formatDate(cinD, 'Y-m-d');
  var coutY = fpCheckOut.formatDate(coutD, 'Y-m-d');
  var p = lhBookingStayPricingEuro(cinY, coutY, lhGetGuestsIntForPricing());
  lhApplyCouponLayerToTotals(p, cinY, coutY, nights);
}

function lhApplyCouponLayerToTotals(pricingSync, cinYmd, coutYmd, nights) {
  var totalPrice = document.getElementById('totalPrice');
  var couponLineEl = document.getElementById('lh-total-coupon-line');
  var hintEl = document.getElementById('lh-coupon-hint');
  var breakdown = document.getElementById('lh-total-breakdown');
  var coup = lhGetCouponRawInput();
  var gInt = lhGetGuestsIntForPricing();
  if (coup === '') {
    if (hintEl) {
      hintEl.textContent = '';
      hintEl.classList.add('hidden');
    }
    if (couponLineEl) {
      couponLineEl.textContent = '';
      couponLineEl.classList.add('hidden');
    }
    if (totalPrice) totalPrice.textContent = pricingSync.total.toFixed(0) + lhCurrencySuffix();
    lhLastPricePreview = null;
    if (breakdown) {
      var showB =
        pricingSync.baseLine ||
        pricingSync.discountLine ||
        pricingSync.extraGuestMathLine ||
        pricingSync.extraGuestNote;
      if (showB) breakdown.classList.remove('hidden');
    }
    return;
  }
  var key = lhPricePreviewSyncKey(cinYmd, coutYmd, gInt, coup);
  if (!lhLastPricePreview || lhLastPricePreview._syncKey !== key) {
    if (hintEl) {
      hintEl.textContent = typeof lhT === 'function' ? lhT('booking.coupon_checking') : '';
      hintEl.classList.remove('hidden');
      hintEl.className =
        'text-xs text-blue-grey font-medium mb-3 leading-snug';
    }
    if (couponLineEl) couponLineEl.classList.add('hidden');
    if (totalPrice) totalPrice.textContent = pricingSync.total.toFixed(0) + lhCurrencySuffix();
    return;
  }
  if (lhLastPricePreview.coupon_error) {
    if (hintEl) {
      hintEl.textContent = lhLastPricePreview.coupon_error;
      hintEl.classList.remove('hidden');
      hintEl.className = 'text-xs text-red-700 font-semibold mb-3 leading-snug';
    }
    if (couponLineEl) couponLineEl.classList.add('hidden');
  } else {
    if (hintEl) {
      hintEl.textContent = '';
      hintEl.classList.add('hidden');
    }
    var cd = parseFloat(String(lhLastPricePreview.coupon_discount || '0'));
    if (cd > 0.499 && couponLineEl) {
      couponLineEl.textContent = lhFormatCouponLine(coup, cd);
      couponLineEl.classList.remove('hidden');
    } else if (couponLineEl) {
      couponLineEl.textContent = '';
      couponLineEl.classList.add('hidden');
    }
  }
  var totNum = parseFloat(String(lhLastPricePreview.total));
  if (totalPrice && !isNaN(totNum))
    totalPrice.textContent = totNum.toFixed(0) + lhCurrencySuffix();
  if (breakdown) {
    var showB2 =
      pricingSync.baseLine ||
      pricingSync.discountLine ||
      (couponLineEl && !couponLineEl.classList.contains('hidden')) ||
      pricingSync.extraGuestMathLine ||
      pricingSync.extraGuestNote;
    if (showB2) breakdown.classList.remove('hidden');
  }
}

function lhPricePreviewReadyForSubmit(cinYmd, coutYmd, guestsStr) {
  var coup = lhGetCouponRawInput();
  var gInt = parseInt(String(guestsStr), 10) || 1;
  if (coup === '') return true;
  var key = lhPricePreviewSyncKey(cinYmd, coutYmd, gInt, coup);
  if (!lhLastPricePreview || lhLastPricePreview._syncKey !== key) return false;
  return !lhLastPricePreview.coupon_error;
}

function lhToggleExtraGuestNotice() {
  var el = document.getElementById('lh-extra-guest-notice');
  if (!el) return;
  var msg = lhExtraGuestNoticeText(lhGetGuestsIntForPricing());
  var box = document.getElementById('totalBox');
  if (msg && box && !box.classList.contains('hidden')) {
    el.textContent = '';
    el.classList.add('hidden');
    return;
  }
  if (msg) {
    el.textContent = msg;
    el.classList.remove('hidden');
  } else {
    el.textContent = '';
    el.classList.add('hidden');
  }
}

function lhUpdateTotalBoxFromRange(checkInYmd, checkOutYmd, nights) {
  var totalPrice = document.getElementById('totalPrice');
  var box = document.getElementById('totalBox');
  var breakdown = document.getElementById('lh-total-breakdown');
  var baseLineEl = document.getElementById('lh-total-base-line');
  var discountLineEl = document.getElementById('lh-total-discount-line');
  var couponLineEl = document.getElementById('lh-total-coupon-line');
  var extraLineEl = document.getElementById('lh-total-extra-line');
  var extraNoteEl = document.getElementById('lh-total-extra-guest-note');
  var hintElCoupon = document.getElementById('lh-coupon-hint');
  if (!box) return;
  function resetBreakdown() {
    if (baseLineEl) baseLineEl.textContent = '';
    if (discountLineEl) {
      discountLineEl.textContent = '';
      discountLineEl.classList.add('hidden');
    }
    if (couponLineEl) {
      couponLineEl.textContent = '';
      couponLineEl.classList.add('hidden');
    }
    if (hintElCoupon) {
      hintElCoupon.textContent = '';
      hintElCoupon.classList.add('hidden');
    }
    lhLastPricePreview = null;
    if (extraLineEl) {
      extraLineEl.textContent = '';
      extraLineEl.classList.add('hidden');
    }
    if (extraNoteEl) {
      extraNoteEl.textContent = '';
      extraNoteEl.classList.add('hidden');
    }
    if (breakdown) breakdown.classList.add('hidden');
  }
  nights = parseInt(String(nights), 10) || 0;
  var effMin = lhEffectiveMinStay(checkInYmd, checkOutYmd);
  if (nights > 0 && nights < effMin) {
    if (totalPrice) totalPrice.textContent = '';
    resetBreakdown();
    box.classList.add('hidden');
    lhSetDateRangeHintInvalid(lhMinStayTooShortMsg(effMin));
    lhToggleExtraGuestNotice();
    return;
  }
  lhResetDateRangeHint();
  if (nights < 1) {
    if (totalPrice) totalPrice.textContent = '';
    resetBreakdown();
    box.classList.add('hidden');
    lhToggleExtraGuestNotice();
    return;
  }
  var p = lhBookingStayPricingEuro(checkInYmd, checkOutYmd, lhGetGuestsIntForPricing());
  if (baseLineEl) baseLineEl.textContent = p.baseLine || '';
  if (discountLineEl) {
    if (p.discountLine) {
      discountLineEl.textContent = p.discountLine;
      discountLineEl.classList.remove('hidden');
    } else {
      discountLineEl.textContent = '';
      discountLineEl.classList.add('hidden');
    }
  }
  if (extraLineEl) {
    if (p.extraGuestMathLine) {
      extraLineEl.textContent = p.extraGuestMathLine;
      extraLineEl.classList.remove('hidden');
    } else {
      extraLineEl.textContent = '';
      extraLineEl.classList.add('hidden');
    }
  }
  if (extraNoteEl) {
    if (p.extraGuestNote) {
      extraNoteEl.textContent = p.extraGuestNote;
      extraNoteEl.classList.remove('hidden');
    } else {
      extraNoteEl.textContent = '';
      extraNoteEl.classList.add('hidden');
    }
  }
  if (breakdown) {
    if (
      p.baseLine ||
      p.discountLine ||
      p.extraGuestMathLine ||
      p.extraGuestNote ||
      lhGetCouponRawInput() !== ''
    ) {
      breakdown.classList.remove('hidden');
    } else {
      breakdown.classList.add('hidden');
    }
  }
  lhApplyCouponLayerToTotals(p, checkInYmd, checkOutYmd, nights);
  var coupRun = lhGetCouponRawInput();
  if (coupRun !== '') {
    lhScheduleBookingPricePreview(
      checkInYmd,
      checkOutYmd,
      lhGetGuestsIntForPricing(),
      coupRun
    );
  } else {
    window.clearTimeout(lhPricePreviewTimer);
  }
  box.classList.remove('hidden');
  lhToggleExtraGuestNotice();
}

var propertyId = <?= (int)$property['id'] ?>;
var propertyTitle = <?= json_encode((string)($property['title'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
var bookingCsrf = <?= json_encode(lh_csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

var lhPendingBooking = null;
var lhConfirmModalLastFocus = null;

var preGuests = "<?= htmlspecialchars($guests ?? '') ?>";
if (preGuests) {
  var guestSelect = document.getElementById('guests');
  if (guestSelect && ['1', '2', '3', '4', '5', '6'].indexOf(preGuests) !== -1) {
    guestSelect.value = preGuests;
  }
}
lhToggleExtraGuestNotice();

var totalBox=document.getElementById('totalBox');
var dateHintEl=document.getElementById('lh-date-range-hint');
var lhDateHintDefaultText=dateHintEl?dateHintEl.textContent.trim():'';
var fpCheckIn, fpCheckOut;

function lhSyncDateRangeInstructionVisibility() {
  if (!dateHintEl) return;
  if (
    typeof fpCheckIn === 'undefined' ||
    !fpCheckIn ||
    typeof fpCheckOut === 'undefined' ||
    !fpCheckOut
  ) {
    dateHintEl.classList.remove('hidden');
    return;
  }
  if (dateHintEl.className.indexOf('amber') !== -1) {
    return;
  }
  var cinD = fpCheckIn.selectedDates[0];
  var coutD = fpCheckOut.selectedDates[0];
  if (!cinD || !coutD) {
    dateHintEl.classList.remove('hidden');
    return;
  }
  var nights = (coutD - cinD) / 86400000;
  var cin = fpCheckIn.formatDate(cinD, 'Y-m-d');
  var cout = fpCheckOut.formatDate(coutD, 'Y-m-d');
  var effR = lhEffectiveMinStay(cin, cout);
  if (cout > cin && nights >= effR) {
    dateHintEl.classList.add('hidden');
  } else {
    dateHintEl.classList.remove('hidden');
  }
}

function lhResetDateRangeHint(){
if(!dateHintEl)return;
dateHintEl.className='text-xs text-blue-grey mb-4 leading-snug';
dateHintEl.textContent=lhDateHintDefaultText;
lhSyncDateRangeInstructionVisibility();
}
function lhSetDateRangeHintInvalid(msg){
if(!dateHintEl)return;
dateHintEl.classList.remove('hidden');
dateHintEl.className='text-xs text-amber-800 font-medium mb-4 leading-snug';
dateHintEl.textContent=msg;
}

var preCheckIn  = "<?= $has_checkin ? $check_in : '' ?>";
var preCheckOut = "<?= $has_checkout ? $check_out : '' ?>";

function lhShowToast(message, kind) {
  var el = document.getElementById('lh-booking-toast');
  if (!el) return;
  el.textContent = message;
  el.classList.remove('lh-toast--success', 'lh-toast--error', 'lh-toast--visible');
  el.classList.add(kind === 'error' ? 'lh-toast--error' : 'lh-toast--success');
  requestAnimationFrame(function () {
    el.classList.add('lh-toast--visible');
  });
  clearTimeout(el._lhT);
  el._lhT = setTimeout(function () {
    el.classList.remove('lh-toast--visible');
  }, 4200);
}

function lhSetLoading(isLoading) {
  var btn = document.getElementById('reserveBtn');
  var label = document.getElementById('reserveBtnLabel');
  var spin = document.getElementById('reserveBtnSpinner');
  if (!btn || !label || !spin) return;
  btn.disabled = !!isLoading;
  if (isLoading) {
    label.textContent = typeof lhT === 'function' ? lhT('booking.processing') : '';
    spin.classList.remove('hidden');
  } else {
    spin.classList.add('hidden');
  }
}

function lhGetBookingConfirmFocusables() {
  var panel = document.querySelector('.lh-booking-confirm-panel');
  if (!panel) return [];
  return Array.prototype.slice
    .call(panel.querySelectorAll('button:not([disabled]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'))
    .filter(function (el) {
      return panel.contains(el) && (el.offsetParent !== null || el.getClientRects().length > 0);
    });
}

function lhBookingConfirmKeydown(e) {
  var root = document.getElementById('lh-booking-confirm-root');
  if (!root || root.hasAttribute('hidden')) return;
  if (e.key === 'Escape') {
    e.preventDefault();
    e.stopPropagation();
    lhCloseBookingConfirmModal();
    return;
  }
  if (e.key !== 'Tab') return;
  var focusables = lhGetBookingConfirmFocusables();
  if (focusables.length === 0) return;
  e.preventDefault();
  var first = focusables[0];
  var last = focusables[focusables.length - 1];
  var idx = focusables.indexOf(document.activeElement);
  if (e.shiftKey) {
    if (idx <= 0) last.focus();
    else focusables[idx - 1].focus();
  } else {
    if (idx === -1 || idx >= focusables.length - 1) first.focus();
    else focusables[idx + 1].focus();
  }
}

function lhCloseBookingConfirmModal() {
  var root = document.getElementById('lh-booking-confirm-root');
  if (!root || root.hasAttribute('hidden')) return;
  root.setAttribute('hidden', '');
  root.setAttribute('aria-hidden', 'true');
  root.classList.remove('lh-booking-confirm-root--open');
  document.removeEventListener('keydown', lhBookingConfirmKeydown, true);
  lhPendingBooking = null;
  if (!lhOpenBookingConfirmModal._hadSheet) {
    document.body.classList.remove('overflow-hidden');
  }
  lhOpenBookingConfirmModal._hadSheet = false;
  lhFocusNoScroll(lhConfirmModalLastFocus);
  lhConfirmModalLastFocus = null;
}

function lhOpenBookingConfirmModal(payload) {
  lhPendingBooking = payload;
  var sheet = document.getElementById('lh-booking-sheet');
  var sheetActive = sheet && sheet.getAttribute('aria-hidden') !== 'true';
  lhOpenBookingConfirmModal._hadSheet = !!sheetActive;
  if (!sheetActive) {
    document.body.classList.add('overflow-hidden');
  }

  document.getElementById('lh-confirm-property').textContent = propertyTitle;
  document.getElementById('lh-confirm-period').textContent =
    fpCheckIn.formatDate(payload.dateFrom, lhBookingAltFormat) +
    ' → ' +
    fpCheckOut.formatDate(payload.dateTo, lhBookingAltFormat);
  var gStr = String(payload.guests);
  var gDisp =
    gStr === '6'
      ? '6+ ' + (typeof lhT === 'function' ? lhT('booking.guests_plural') : 'guests')
      : gStr === '1'
        ? '1 ' + (typeof lhT === 'function' ? lhT('booking.guest_singular') : 'guest')
        : gStr + ' ' + (typeof lhT === 'function' ? lhT('booking.guests_plural') : 'guests');
  document.getElementById('lh-confirm-guests').textContent = gDisp;
  var breakWrap = document.getElementById('lh-confirm-price-break');
  var cBase = document.getElementById('lh-confirm-base-line');
  var cDisc = document.getElementById('lh-confirm-discount-line');
  var cCup = document.getElementById('lh-confirm-coupon-line');
  var cExtra = document.getElementById('lh-confirm-extra-line');
  var cNote = document.getElementById('lh-confirm-extra-note');
  var bl = typeof payload.pricingBaseLine === 'string' ? payload.pricingBaseLine : '';
  var dl = typeof payload.pricingDiscountLine === 'string' ? payload.pricingDiscountLine : '';
  var cl = typeof payload.pricingCouponLine === 'string' ? payload.pricingCouponLine : '';
  var eln = typeof payload.pricingExtraLine === 'string' ? payload.pricingExtraLine : '';
  var en = typeof payload.pricingExtraNote === 'string' ? payload.pricingExtraNote : '';
  if (breakWrap && cBase) {
    cBase.textContent = bl;
    if (cDisc) {
      if (dl) {
        cDisc.textContent = dl;
        cDisc.classList.remove('hidden');
      } else {
        cDisc.textContent = '';
        cDisc.classList.add('hidden');
      }
    }
    if (cCup) {
      if (cl) {
        cCup.textContent = cl;
        cCup.classList.remove('hidden');
      } else {
        cCup.textContent = '';
        cCup.classList.add('hidden');
      }
    }
    if (cExtra) {
      if (eln) {
        cExtra.textContent = eln;
        cExtra.classList.remove('hidden');
      } else {
        cExtra.textContent = '';
        cExtra.classList.add('hidden');
      }
    }
    if (cNote) {
      if (en) {
        cNote.textContent = en;
        cNote.classList.remove('hidden');
      } else {
        cNote.textContent = '';
        cNote.classList.add('hidden');
      }
    }
    if (bl || dl || cl || eln || en) breakWrap.classList.remove('hidden');
    else breakWrap.classList.add('hidden');
  }
  document.getElementById('lh-confirm-total').textContent =
    (payload.totalEuro ? payload.totalEuro.toFixed(0) : '0') + lhCurrencySuffix();
  document.getElementById('lh-confirm-name').textContent = payload.guestName;
  document.getElementById('lh-confirm-phone').textContent = payload.guestPhone;
  document.getElementById('lh-confirm-email').textContent = payload.guestEmail;

  var root = document.getElementById('lh-booking-confirm-root');
  root.removeAttribute('hidden');
  root.setAttribute('aria-hidden', 'false');
  root.classList.add('lh-booking-confirm-root--open');
  lhConfirmModalLastFocus = document.activeElement;
  document.addEventListener('keydown', lhBookingConfirmKeydown, true);
  requestAnimationFrame(function () {
    var submitBtn = document.getElementById('lh-booking-confirm-submit');
    if (submitBtn) submitBtn.focus();
  });
}

function lhShowBookingSuccessBanner(bookingId, email) {
  var banner = document.getElementById('lh-booking-success-banner');
  var text = document.getElementById('lh-booking-success-text');
  if (!banner || !text) return;
  text.textContent = typeof lhT === 'function'
    ? lhT('booking.success_banner', { email: email, id: String(bookingId) })
    : email;
  banner.removeAttribute('hidden');
  requestAnimationFrame(function () {
    banner.classList.add('lh-booking-success-banner--visible');
  });
}

function lhHideBookingSuccessBanner() {
  var banner = document.getElementById('lh-booking-success-banner');
  if (!banner) return;
  banner.classList.remove('lh-booking-success-banner--visible');
  setTimeout(function () {
    if (!banner.classList.contains('lh-booking-success-banner--visible')) {
      banner.setAttribute('hidden', '');
    }
  }, 320);
}

function lhExecuteBookingRequest(payload) {
  var btn = document.getElementById('reserveBtn');
  var msg = document.getElementById('availabilityMsg');
  lhSetLoading(true);
  if (msg) {
    msg.innerHTML = '';
    msg.className = 'text-xs text-center mt-3 text-blue-grey';
  }

  fetch(ajaxCreateBooking, {
    method: 'POST',
    headers: { Accept: 'application/json' },
    body: new URLSearchParams({
      csrf_token: bookingCsrf,
      company: document.getElementById('bookingCompany') ? document.getElementById('bookingCompany').value : '',
      property_id: propertyId,
      guest_name: payload.guestName,
      guest_phone: payload.guestPhone,
      guest_email: payload.guestEmail,
      check_in: payload.checkin,
      check_out: payload.checkout,
      guests: payload.guests,
      coupon_code: payload.couponCode || '',
      locale: window.lhLocale || 'ro',
    }),
  })
    .then(function (r) {
      return r.json();
    })
    .then(function (resp) {
      lhSetLoading(false);
      var label = document.getElementById('reserveBtnLabel');
      if (resp.success) {
        if (msg) {
          msg.innerHTML = '';
          msg.className = 'text-xs text-center mt-3 text-blue-grey';
        }
        if (label) label.textContent = typeof lhT === 'function' ? lhT('booking.confirmed') : '';
        btn.disabled = true;
        lhShowBookingSuccessBanner(resp.booking_id, payload.guestEmail);
      } else {
        if (msg) {
          msg.innerHTML = resp.message || (typeof lhT === 'function' ? lhT('booking.generic_error') : '');
          msg.className = 'text-xs text-center mt-3 font-semibold text-red-800';
        }
        if (label) label.textContent = <?= json_encode(__('booking.book_now'), JSON_UNESCAPED_UNICODE) ?>;
        btn.disabled = false;
        lhShowToast(resp.message || (typeof lhT === 'function' ? lhT('booking.generic_error') : ''), 'error');
      }
    })
    .catch(function () {
      lhSetLoading(false);
      var label = document.getElementById('reserveBtnLabel');
      if (msg) {
        msg.innerHTML = typeof lhT === 'function' ? lhT('errors.network') : '';
        msg.className = 'text-xs text-center mt-3 font-semibold text-red-800';
      }
      if (label) label.textContent = <?= json_encode(__('booking.book_now'), JSON_UNESCAPED_UNICODE) ?>;
      btn.disabled = false;
      lhShowToast(typeof lhT === 'function' ? lhT('errors.network') : '', 'error');
    });
}

/** Intervale blocked_dates: [from, to) half-open, ca în create_booking / iCal. */
var lhBookingBlockedRanges = [];

/** Flatpickr apelează predicatele disable ca d(date) fără this = instanță. */
function lhLocalDateToYmd(d) {
  if (!d || typeof d.getFullYear !== 'function') return '';
  var y = d.getFullYear();
  var m = String(d.getMonth() + 1).padStart(2, '0');
  var day = String(d.getDate()).padStart(2, '0');
  return y + '-' + m + '-' + day;
}

function lhBookingStayOverlapsBlocked(checkInYmd, checkOutYmd) {
  var ranges = lhBookingBlockedRanges;
  if (!ranges || !ranges.length) return false;
  for (var i = 0; i < ranges.length; i++) {
    var r = ranges[i];
    var from = r && r.from;
    var to = r && r.to;
    if (!from || !to) continue;
    if (from < checkOutYmd && to > checkInYmd) return true;
  }
  return false;
}

function lhYmdCannotBeCheckIn(ymd) {
  var ranges = lhBookingBlockedRanges;
  if (!ranges || !ranges.length) return false;
  for (var j = 0; j < ranges.length; j++) {
    var b = ranges[j];
    var f = b && b.from;
    var t = b && b.to;
    if (!f || !t) continue;
    if (f <= ymd && ymd < t) return true;
  }
  return false;
}

function lhNightsFromCheckInToCheckoutYmd(checkInYmd, checkOutYmd) {
  var a = String(checkInYmd).split('-');
  var b = String(checkOutYmd).split('-');
  if (a.length !== 3 || b.length !== 3) return 0;
  var d0 = new Date(parseInt(a[0], 10), parseInt(a[1], 10) - 1, parseInt(a[2], 10));
  var d1 = new Date(parseInt(b[0], 10), parseInt(b[1], 10) - 1, parseInt(b[2], 10));
  return Math.round((d1 - d0) / 86400000);
}

function lhCheckoutInvalidForBooking(checkInYmd, checkOutYmd) {
  if (!checkOutYmd || !checkInYmd || checkOutYmd <= checkInYmd) return true;
  var need = lhEffectiveMinStay(checkInYmd, checkOutYmd);
  if (lhNightsFromCheckInToCheckoutYmd(checkInYmd, checkOutYmd) < need) return true;
  return lhBookingStayOverlapsBlocked(checkInYmd, checkOutYmd);
}

function lhBookingFpBindLegend(fp, legendId) {
  if (!fp || !legendId) return;
  if (fp.input) fp.input.setAttribute('aria-labelledby', legendId);
  if (fp.altInput) fp.altInput.setAttribute('aria-labelledby', legendId);
}

function lhBookingCheckInDisableFn(date) {
  var ymd = lhLocalDateToYmd(date);
  return ymd ? lhYmdCannotBeCheckIn(ymd) : false;
}

function lhBookingCheckOutDisableFn(date) {
  var ymd = lhLocalDateToYmd(date);
  if (!ymd) return false;
  var cinEl = document.getElementById('booking-check-in');
  var cinInst = cinEl && cinEl._flatpickr;
  var cinD = cinInst && cinInst.selectedDates && cinInst.selectedDates[0];
  if (!cinD) {
    return lhYmdCannotBeCheckIn(ymd);
  }
  var cinYmd = lhLocalDateToYmd(cinD);
  if (!cinYmd) return lhYmdCannotBeCheckIn(ymd);
  return lhCheckoutInvalidForBooking(cinYmd, ymd);
}

function lhBookingOnDayCreateCheckIn(_dObj, _dStr, fpInst, dayElem) {
  var prev = dayElem.querySelector('.lh-cal-day-price');
  if (prev) prev.remove();
  if (!dayElem.dateObj) return;
  var ymd = fpInst.formatDate(dayElem.dateObj, 'Y-m-d');
  var rate = lhNightRateEuroForYmd(ymd);
  var span = document.createElement('span');
  span.className = 'lh-cal-day-price';
  span.textContent = Math.round(rate) + lhCurrencySuffix();
  dayElem.appendChild(span);
}

function lhBookingOnDayCreateCheckOut(_dObj, _dStr, fpInst, dayElem) {
  var prev = dayElem.querySelector('.lh-cal-day-price');
  if (prev) prev.remove();
  dayElem.classList.remove('lh-cal-checkout-only');
  if (!dayElem.dateObj) return;
  var ymd = fpInst.formatDate(dayElem.dateObj, 'Y-m-d');
  var rate = lhNightRateEuroForYmd(ymd);
  var span = document.createElement('span');
  span.className = 'lh-cal-day-price';
  span.textContent = Math.round(rate) + lhCurrencySuffix();
  dayElem.appendChild(span);
  var cinEl = document.getElementById('booking-check-in');
  var cinInst = cinEl && cinEl._flatpickr;
  var s = cinInst && cinInst.selectedDates ? cinInst.selectedDates : [];
  if (s.length === 1) {
    var cin0 = cinInst.formatDate(s[0], 'Y-m-d');
    if (
      ymd > cin0 &&
      lhYmdCannotBeCheckIn(ymd) &&
      !lhCheckoutInvalidForBooking(cin0, ymd)
    ) {
      dayElem.classList.add('lh-cal-checkout-only');
    }
  }
}

function lhRepositionBookingFlatpickr() {
  ['booking-check-in', 'booking-check-out'].forEach(function (id) {
    var el = document.getElementById(id);
    var inst = el && el._flatpickr;
    if (inst && inst.isOpen && typeof inst._positionCalendar === 'function') {
      inst._positionCalendar();
    }
  });
}

function lhBookingAttachScrollForPicker() {
  window.addEventListener('scroll', lhRepositionBookingFlatpickr, true);
  var sheetBody = document.getElementById('lh-booking-sheet-body');
  if (sheetBody) {
    sheetBody.addEventListener('scroll', lhRepositionBookingFlatpickr, { passive: true });
  }
}

function lhBookingDetachScrollForPicker() {
  window.removeEventListener('scroll', lhRepositionBookingFlatpickr, true);
  var sheetBody = document.getElementById('lh-booking-sheet-body');
  if (sheetBody) sheetBody.removeEventListener('scroll', lhRepositionBookingFlatpickr);
}

function lhBookingOnDatesChanged() {
  if (!fpCheckIn || !fpCheckOut) return;
  var cinD = fpCheckIn.selectedDates[0];
  var coutD = fpCheckOut.selectedDates[0];
  if (cinD && coutD) {
    var nights = (coutD - cinD) / 86400000;
    var cinCh = fpCheckIn.formatDate(cinD, 'Y-m-d');
    var coutCh = fpCheckOut.formatDate(coutD, 'Y-m-d');
    if (nights > 0) {
      lhUpdateTotalBoxFromRange(cinCh, coutCh, nights);
    } else {
      totalBox.classList.add('hidden');
      lhSetDateRangeHintInvalid(lhMinStayTooShortMsg(lhEffectiveMinStay(cinCh, coutCh)));
      lhToggleExtraGuestNotice();
    }
  } else {
    totalBox.classList.add('hidden');
    lhResetDateRangeHint();
    lhToggleExtraGuestNotice();
  }
  lhSyncDateRangeInstructionVisibility();
}

var lhBookingFpLocale = (function () {
  var loc = <?= json_encode($lhPdFpJs, JSON_UNESCAPED_UNICODE) ?>;
  if (typeof flatpickr !== 'undefined' && flatpickr.l10ns && flatpickr.l10ns[loc]) {
    return Object.assign({}, flatpickr.l10ns[loc], { firstDayOfWeek: 1 });
  }
  return { firstDayOfWeek: 1 };
})();

fpCheckOut = flatpickr('#booking-check-out', {
  locale: lhBookingFpLocale,
  dateFormat: 'Y-m-d',
  altInput: true,
  altFormat: lhBookingAltFormat,
  minDate: (function () {
    if (!preCheckIn) return 'today';
    var d = new Date(preCheckIn + 'T12:00:00');
    return new Date(d.getFullYear(), d.getMonth(), d.getDate() + 1);
  })(),
  disableMobile: true,
  disable: [lhBookingCheckOutDisableFn],
  defaultDate: preCheckOut || null,
  clickOpens: !!preCheckIn,
  onReady: function (_d, _s, instance) {
    lhBookingFpBindLegend(instance, 'lh-booking-checkout-label');
    if (preCheckIn) {
      var d0 = new Date(preCheckIn + 'T12:00:00');
      var nextMin = new Date(d0.getFullYear(), d0.getMonth(), d0.getDate() + 1);
      instance.set('minDate', nextMin);
    }
  },
  onDayCreate: lhBookingOnDayCreateCheckOut,
  onOpen: function () {
    lhBookingAttachScrollForPicker();
  },
  onClose: function () {
    lhBookingDetachScrollForPicker();
    lhBookingOnDatesChanged();
  },
  onChange: function () {
    lhBookingOnDatesChanged();
  },
});

fpCheckIn = flatpickr('#booking-check-in', {
  locale: lhBookingFpLocale,
  dateFormat: 'Y-m-d',
  altInput: true,
  altFormat: lhBookingAltFormat,
  minDate: 'today',
  disableMobile: true,
  disable: [lhBookingCheckInDisableFn],
  defaultDate: preCheckIn || null,
  onReady: function (_selectedDates, _dateStr, instance) {
    lhBookingFpBindLegend(instance, 'lh-booking-checkin-label');
    fetch(ajaxBookedDates + '?property_id=' + propertyId)
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        lhBookingBlockedRanges = data && data.blocked_ranges ? data.blocked_ranges : [];
        instance.set('disable', [lhBookingCheckInDisableFn]);
        fpCheckOut.set('disable', [lhBookingCheckOutDisableFn]);
        if (instance.redraw) instance.redraw();
        if (fpCheckOut.redraw) fpCheckOut.redraw();
      })
      .catch(function () {
        lhBookingBlockedRanges = [];
        instance.set('disable', [lhBookingCheckInDisableFn]);
        fpCheckOut.set('disable', [lhBookingCheckOutDisableFn]);
        if (instance.redraw) instance.redraw();
        if (fpCheckOut.redraw) fpCheckOut.redraw();
      });
    if (preCheckIn && preCheckOut) {
      var nightsPre = (new Date(preCheckOut) - new Date(preCheckIn)) / 86400000;
      if (nightsPre > 0) {
        lhUpdateTotalBoxFromRange(preCheckIn, preCheckOut, nightsPre);
      } else if (nightsPre <= 0) {
        totalBox.classList.add('hidden');
        lhSetDateRangeHintInvalid(lhMinStayTooShortMsg(lhEffectiveMinStay(preCheckIn, preCheckOut)));
        lhToggleExtraGuestNotice();
      }
    }
  },
  onDayCreate: lhBookingOnDayCreateCheckIn,
  onOpen: function () {
    lhBookingAttachScrollForPicker();
  },
  onClose: function (selectedDates) {
    lhBookingDetachScrollForPicker();
    if (!selectedDates[0]) {
      fpCheckOut.clear();
      fpCheckOut.set('minDate', 'today');
      fpCheckOut.set('clickOpens', false);
      fpCheckOut.set('disable', [lhBookingCheckOutDisableFn]);
      lhBookingOnDatesChanged();
      return;
    }
    var cin = selectedDates[0];
    var nextMin = new Date(cin.getFullYear(), cin.getMonth(), cin.getDate() + 1);
    fpCheckOut.set('minDate', nextMin);
    var co = fpCheckOut.selectedDates[0];
    if (co && co <= cin) {
      fpCheckOut.clear();
    } else if (co) {
      var cinY = lhLocalDateToYmd(cin);
      var coY = lhLocalDateToYmd(co);
      if (lhCheckoutInvalidForBooking(cinY, coY)) {
        fpCheckOut.clear();
      }
    }
    fpCheckOut.set('disable', [lhBookingCheckOutDisableFn]);
    fpCheckOut.set('clickOpens', true);
    lhBookingOnDatesChanged();
    fpCheckOut.open();
  },
  onChange: function (selectedDates) {
    if (!selectedDates.length) {
      fpCheckOut.clear();
      fpCheckOut.set('minDate', 'today');
      fpCheckOut.set('clickOpens', false);
      fpCheckOut.set('disable', [lhBookingCheckOutDisableFn]);
    }
    lhBookingOnDatesChanged();
  },
});

lhSyncDateRangeInstructionVisibility();

(function () {
  var guestSelectForPricing = document.getElementById('guests');
  if (!guestSelectForPricing) return;
  guestSelectForPricing.addEventListener('change', function () {
    var cinD = fpCheckIn.selectedDates[0];
    var coutD = fpCheckOut.selectedDates[0];
    if (cinD && coutD) {
      var nights = (coutD - cinD) / 86400000;
      if (nights > 0) {
        lhUpdateTotalBoxFromRange(
          fpCheckIn.formatDate(cinD, 'Y-m-d'),
          fpCheckOut.formatDate(coutD, 'Y-m-d'),
          nights
        );
        return;
      }
    }
    lhToggleExtraGuestNotice();
  });
})();

(function () {
  var coupIn = document.getElementById('booking-coupon-code');
  if (!coupIn || !fpCheckIn || !fpCheckOut) return;
  coupIn.addEventListener('input', function () {
    var cinD = fpCheckIn.selectedDates[0];
    var coutD = fpCheckOut.selectedDates[0];
    if (cinD && coutD) {
      var nx = (coutD - cinD) / 86400000;
      if (nx > 0) {
        lhUpdateTotalBoxFromRange(
          fpCheckIn.formatDate(cinD, 'Y-m-d'),
          fpCheckOut.formatDate(coutD, 'Y-m-d'),
          nx
        );
      }
    }
  });
})();

var btn=document.getElementById('reserveBtn');

btn.onclick=function(){

var cinD = fpCheckIn.selectedDates[0];
var coutD = fpCheckOut.selectedDates[0];
var msg=document.getElementById('availabilityMsg');

if(!cinD || !coutD){
msg.innerHTML=typeof lhT==='function'?lhT('booking.select_period'):'';
msg.className="text-xs text-center mt-3 font-semibold text-red-800";
lhShowToast(typeof lhT==='function'?lhT('booking.select_period'):'', 'error');
return;
}

var checkin=fpCheckIn.formatDate(cinD,"Y-m-d");
var checkout=fpCheckOut.formatDate(coutD,"Y-m-d");
var nightsEarly=(coutD-cinD)/86400000;
if(checkout<=checkin||nightsEarly<1){
msg.innerHTML=typeof lhT==='function'?lhT('booking.min_one_night'):'';
msg.className="text-xs text-center mt-3 font-semibold text-red-800";
lhShowToast(typeof lhT==='function'?lhT('search.min_night_hint'):'', 'error');
return;
}
var effBtn = lhEffectiveMinStay(checkin, checkout);
if(nightsEarly < effBtn){
msg.innerHTML = effBtn === 1 ? lhT('api.min_one_night') : lhT('booking.min_stay_extend', { n: effBtn });
msg.className="text-xs text-center mt-3 font-semibold text-red-800";
lhShowToast(lhMinStayTooShortMsg(effBtn), 'error');
return;
}

var guestName=document.getElementById('guestName').value.trim();
var guestPhone=document.getElementById('guestPhone').value.trim();
var guestEmail=document.getElementById('guestEmail').value.trim();

if(!guestName){
msg.innerHTML=typeof lhT==='function'?lhT('booking.fill_name'):'';
msg.className="text-xs text-center mt-3 font-semibold text-red-800";
lhShowToast(typeof lhT==='function'?lhT('booking.fill_name'):'', 'error');
return;
}

if(!guestPhone){
msg.innerHTML=typeof lhT==='function'?lhT('booking.fill_phone'):'';
msg.className="text-xs text-center mt-3 font-semibold text-red-800";
lhShowToast(typeof lhT==='function'?lhT('booking.fill_phone'):'', 'error');
return;
}

if(!guestEmail){
msg.innerHTML=typeof lhT==='function'?lhT('booking.fill_email'):'';
msg.className="text-xs text-center mt-3 font-semibold text-red-800";
lhShowToast(typeof lhT==='function'?lhT('booking.fill_email'):'', 'error');
return;
}

var guests=document.getElementById('guests').value;
var nights=nightsEarly;
var gInt = parseInt(String(guests), 10) || 1;
var pr = nights > 0 ? lhBookingStayPricingEuro(checkin, checkout, gInt) : { total: 0, baseLine: '', discountLine: '', extraGuestMathLine: '', extraGuestNote: '' };

var coupReserve = lhGetCouponRawInput();
if (coupReserve !== '' && !lhPricePreviewReadyForSubmit(checkin, checkout, guests)) {
  msg.innerHTML = typeof lhT==='function'?lhT('booking.coupon_wait'):'';
  msg.className = 'text-xs text-center mt-3 font-semibold text-red-800';
  lhShowToast(typeof lhT==='function'?lhT('booking.coupon_fix'):'', 'error');
  return;
}
var totalEuroRes = pr.total;
var pricingCouponLineRes = '';
if (coupReserve !== '' && lhLastPricePreview) {
  var tNv = parseFloat(String(lhLastPricePreview.total));
  if (!isNaN(tNv)) totalEuroRes = tNv;
  var cdNv = parseFloat(String(lhLastPricePreview.coupon_discount || 0));
  if (cdNv > 0.499) {
    pricingCouponLineRes = lhFormatCouponLine(coupReserve, cdNv);
  }
}

lhOpenBookingConfirmModal({
guestName:guestName,
guestPhone:guestPhone,
guestEmail:guestEmail,
checkin:checkin,
checkout:checkout,
guests:guests,
nights:nights,
totalEuro: totalEuroRes,
pricingBaseLine: pr.baseLine || '',
pricingDiscountLine: pr.discountLine || '',
pricingCouponLine: pricingCouponLineRes,
pricingExtraLine: pr.extraGuestMathLine || '',
pricingExtraNote: pr.extraGuestNote || '',
couponCode: coupReserve,
dateFrom:cinD,
dateTo:coutD
});

};

(function () {
  var overlay = document.getElementById('lh-booking-confirm-overlay');
  var back = document.getElementById('lh-booking-confirm-back');
  var submit = document.getElementById('lh-booking-confirm-submit');
  var successClose = document.getElementById('lh-booking-success-close');
  if (overlay) overlay.addEventListener('click', lhCloseBookingConfirmModal);
  if (back) back.addEventListener('click', lhCloseBookingConfirmModal);
  if (submit) {
    submit.addEventListener('click', function () {
      var payload = lhPendingBooking;
      if (!payload) return;
      lhCloseBookingConfirmModal();
      lhExecuteBookingRequest(payload);
    });
  }
  if (successClose) successClose.addEventListener('click', lhHideBookingSuccessBanner);
})();

(function () {
  /** Coloană sticky desktop doar când lg ȘI înălțime viewport ≥ această valoare (px). Sub prag: bară + sheet (vezi max-height: 760px în CSS). */
  var LH_PD_BOOKING_MIN_VIEWPORT_HEIGHT_PX = 761;

  function lhPdUseDesktopBookingColumn() {
    return window.matchMedia(
      '(min-width: 1024px) and (min-height: ' + LH_PD_BOOKING_MIN_VIEWPORT_HEIGHT_PX + 'px)'
    ).matches;
  }

  var widget = document.getElementById('lh-booking-widget');
  var desktopSlot = document.getElementById('lh-booking-desktop-slot');
  var sheetBody = document.getElementById('lh-booking-sheet-body');
  var sheet = document.getElementById('lh-booking-sheet');
  var overlay = document.getElementById('lh-booking-overlay');
  var openBtn = document.getElementById('lh-open-booking-sheet');
  var closeBtn = document.getElementById('lh-close-booking-sheet');
  if (!widget || !desktopSlot || !sheetBody || !sheet || !overlay) return;

  var lastFocus = null;

  function isSheetOpen() {
    return sheetBody.contains(widget);
  }

  function getFocusables() {
    return sheet.querySelectorAll('a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])');
  }

  function openBookingSheet() {
    if (lhPdUseDesktopBookingColumn()) return;
    if (isSheetOpen()) return;
    lastFocus = document.activeElement;
    sheetBody.appendChild(widget);
    overlay.classList.remove('opacity-0', 'pointer-events-none');
    overlay.setAttribute('aria-hidden', 'false');
    sheet.classList.remove('translate-y-full');
    sheet.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');
    lhRefreshLucide();
    var focusables = getFocusables();
    if (focusables.length) {
      try {
        focusables[0].focus({ preventScroll: true });
      } catch (err) {
        focusables[0].focus();
      }
    }
    document.addEventListener('keydown', onKeydown);
  }

  function closeBookingSheet() {
    if (!isSheetOpen()) return;
    desktopSlot.appendChild(widget);
    overlay.classList.add('opacity-0', 'pointer-events-none');
    overlay.setAttribute('aria-hidden', 'true');
    sheet.classList.add('translate-y-full');
    sheet.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('overflow-hidden');
    lhRefreshLucide();
    document.removeEventListener('keydown', onKeydown);
    lhFocusNoScroll(lastFocus);
    lastFocus = null;
  }

  function onKeydown(e) {
    if (e.key === 'Escape') {
      e.preventDefault();
      closeBookingSheet();
      return;
    }
    if (e.key !== 'Tab' || !isSheetOpen()) return;
    var focusables = Array.prototype.slice.call(getFocusables()).filter(function (el) {
      return el.offsetParent !== null || el === document.activeElement;
    });
    if (!focusables.length) return;
    var first = focusables[0];
    var last = focusables[focusables.length - 1];
    if (e.shiftKey) {
      if (document.activeElement === first) {
        e.preventDefault();
        last.focus();
      }
    } else {
      if (document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  }

  if (openBtn) openBtn.addEventListener('click', openBookingSheet);
  if (closeBtn) closeBtn.addEventListener('click', closeBookingSheet);
  overlay.addEventListener('click', closeBookingSheet);

  window.addEventListener('resize', function () {
    if (lhPdUseDesktopBookingColumn() && isSheetOpen()) {
      desktopSlot.appendChild(widget);
      overlay.classList.add('opacity-0', 'pointer-events-none');
      overlay.setAttribute('aria-hidden', 'true');
      sheet.classList.add('translate-y-full');
      sheet.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('overflow-hidden');
      document.removeEventListener('keydown', onKeydown);
      lhRefreshLucide();
    }
  });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
