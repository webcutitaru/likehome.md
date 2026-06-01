<?php
/**
 * index.php
 * Homepage cu search bar, preview 9 proprietăți, CTA catalog, trust bar, recenzii oaspeți.
 */

require_once __DIR__ . '/config.php';

$pageTitle = __('page.index.title');
$pageDescription = __('page.index.description');

$properties      = [];
$featured_props  = [];

try {
    $pdo = getPDO();
    $stmt = $pdo->query('SELECT id, title, slug FROM properties WHERE is_active = 1 ORDER BY title ASC');
    $properties = $stmt->fetchAll();

    $stmt_feat = $pdo->query(
        'SELECT * FROM properties WHERE is_active = 1 ORDER BY created_at DESC LIMIT 9'
    );
    $featured_props = lh_property_apply_locale_list($stmt_feat->fetchAll(), $pdo);
} catch (Exception $e) {
    $properties     = [];
    $featured_props = [];
}

$canonicalUrl = lh_absolute_locale_url('');

$ogImage = '';
if ($featured_props !== []) {
    $fp = $featured_props[0];
    $fpImgs = !empty($fp['image_name']) ? array_filter(explode(',', (string) $fp['image_name'])) : [];
    if ($fpImgs !== []) {
        $bn = trim((string) $fpImgs[0]);
        if ($bn !== '') {
            $ogImage = lh_absolute_href(lh_property_image_url((int) ($fp['id'] ?? 0), $bn, 'full'));
        }
    }
}

$lhJsonLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => 'Like HOME',
    'url' => lh_absolute_url(''),
    'description' => $pageDescription,
    'inLanguage' => match (lh_current_locale()) {
        'en' => 'en-US',
        'ru' => 'ru-RU',
        default => 'ro-MD',
    },
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'Like HOME',
        'url' => lh_absolute_url(''),
        'logo' => [
            '@type' => 'ImageObject',
            'url' => lh_absolute_href(lh_public_url('assets/favicon.svg')),
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/components/property_card.php'; ?>

<!-- ── Hero (full bleed); search centered on dark/light boundary ─ -->
<div class="w-full bg-logo text-white relative">
  <div class="max-w-4xl mx-auto px-4 pt-12 md:pt-16 pb-6 md:pb-8 lg:pb-36 text-center font-sans">
    <h1 class="text-3xl md:text-5xl font-bold text-white mb-3">
      <?= htmlspecialchars(__('page.index.hero_title'), ENT_QUOTES, 'UTF-8') ?>
    </h1>
    <p class="text-white/70 text-base md:text-lg max-w-2xl mx-auto">
      <?= htmlspecialchars(__('page.index.hero_subtitle'), ENT_QUOTES, 'UTF-8') ?>
    </p>
  </div>
  <div class="relative z-10 flex justify-center px-3 sm:px-4 min-w-0 pb-8 md:pb-8 lg:absolute lg:inset-x-0 lg:bottom-0 lg:translate-y-1/2 lg:pb-0">
    <div class="w-full min-w-0 max-w-6xl">
      <?php include __DIR__ . '/components/search_bar.php'; ?>
    </div>
  </div>
</div>

<!-- Spațiu sub hero: mic pe mobil/tabletă (bara în flux); compensare bară flotantă doar la lg+ -->
<div class="max-w-6xl mx-auto px-4 pb-0 lg:pt-20"></div>

<!-- ── Preview proprietăți (9) + CTA catalog ───────────────────── -->
<div
  id="home-properties-preview"
  class="max-w-6xl mx-auto px-4 mt-8 lg:mt-0 scroll-mt-28 md:scroll-mt-24"
>
  <h2 id="home-properties-heading" class="text-xl font-semibold text-ink mt-6 mb-6 text-center hidden"></h2>
  <div
    id="home-properties-grid"
    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-4 transition-opacity duration-300 ease-out opacity-100"
  >
    <?php
    if (!empty($featured_props)) {
        foreach ($featured_props as $property) {
            echo render_property_card(
                $property,
                $_GET['check_in'] ?? '',
                $_GET['check_out'] ?? '',
                $_GET['guests'] ?? ''
            );
        }
    } else {
        echo '<p class="col-span-full text-blue-grey">' . htmlspecialchars(__('page.index.empty'), ENT_QUOTES, 'UTF-8') . '</p>';
    }
    ?>
  </div>
  <div id="home-cta-wrap" class="flex justify-center mt-10 mb-10 md:mb-14">
    <a
      href="<?= htmlspecialchars(lh_locale_url('properties.php'), ENT_QUOTES, 'UTF-8') ?>"
      class="inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-xl text-sm font-semibold bg-cta text-white shadow-md shadow-black/10 hover:brightness-110 transition-all"
    >
      <?= htmlspecialchars(__('page.index.cta_all'), ENT_QUOTES, 'UTF-8') ?>
      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
      </svg>
    </a>
  </div>
</div>

<!-- ── Bară încredere (4 puncte premium) — fundal full-bleed ───── -->
<section class="w-full border-t border-cta/10 bg-gradient-to-b from-cta/[0.04] to-transparent mt-10 md:mt-14" aria-labelledby="home-trust-heading">
  <div class="max-w-6xl mx-auto px-4 pt-7 pb-0 md:pt-9 md:pb-0">
  <h2 id="home-trust-heading" class="sr-only"><?= htmlspecialchars(__('page.index.trust_heading'), ENT_QUOTES, 'UTF-8') ?></h2>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-6 md:gap-8">
    <div class="flex flex-col items-center text-center">
      <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-cta/10 text-cta ring-1 ring-cta/15 mb-4">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
        </svg>
      </div>
      <h3 class="font-semibold text-ink mb-1.5 text-sm md:text-base"><?= htmlspecialchars(__('page.index.trust1_title'), ENT_QUOTES, 'UTF-8') ?></h3>
      <p class="text-sm text-blue-grey leading-relaxed"><?= htmlspecialchars(__('page.index.trust1_desc'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="flex flex-col items-center text-center">
      <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-cta/10 text-cta ring-1 ring-cta/15 mb-4">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
        </svg>
      </div>
      <h3 class="font-semibold text-ink mb-1.5 text-sm md:text-base"><?= htmlspecialchars(__('page.index.trust2_title'), ENT_QUOTES, 'UTF-8') ?></h3>
      <p class="text-sm text-blue-grey leading-relaxed"><?= htmlspecialchars(__('page.index.trust2_desc'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="flex flex-col items-center text-center">
      <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-cta/10 text-cta ring-1 ring-cta/15 mb-4">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
      </div>
      <h3 class="font-semibold text-ink mb-1.5 text-sm md:text-base"><?= htmlspecialchars(__('page.index.trust3_title'), ENT_QUOTES, 'UTF-8') ?></h3>
      <p class="text-sm text-blue-grey leading-relaxed"><?= htmlspecialchars(__('page.index.trust3_desc'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="flex flex-col items-center text-center">
      <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-cta/10 text-cta ring-1 ring-cta/15 mb-4">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
      </div>
      <h3 class="font-semibold text-ink mb-1.5 text-sm md:text-base"><?= htmlspecialchars(__('page.index.trust4_title'), ENT_QUOTES, 'UTF-8') ?></h3>
      <p class="text-sm text-blue-grey leading-relaxed"><?= htmlspecialchars(__('page.index.trust4_desc'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  </div>
  </div>
</section>

<?php
// Recenzii statice (homepage).
$lh_reviews_hero_title = __('page.index.reviews_title');
$lh_reviews_static = [
    [
        'name' => 'Lilia',
        'subtitle' => 'De 8 ani pe Airbnb',
        'platform' => 'airbnb',
        'rating' => 5,
        'date' => 'iulie 2025',
        'stay' => 'Ședere de câteva nopți',
        'text' => 'Apartament amplu, luminos, aproape de centru orașului. Toate comoditățile pentru o vacanță în Chișinău. Sergiu a fost flexibil cu noi și la check-in că am ajuns târziu, și la check-out ...',
    ],
    [
        'name' => 'Eugenia',
        'subtitle' => 'Wallisellen, Elveția',
        'platform' => 'booking',
        'rating' => 5,
        'date' => 'iulie 2025',
        'stay' => 'Ședere cu copii',
        'text' => 'locuinta superba ,gazda foarte receptiva,apartamentul este foarte mare și bine amenajat,curat și dotat cu tot ce e nevoie....',
    ],
    [
        'name' => 'Andrei',
        'subtitle' => 'Milano, Italia',
        'platform' => 'booking',
        'rating' => 5,
        'date' => 'august 2025',
        'stay' => 'Ședere de o noapte',
        'text' => 'Apartament elegant frumos si curat! Merci mult! Host foarte intelegator ! Totul perfect',
    ],
    [
        'name' => 'Denis',
        'subtitle' => 'De 2 ani pe Airbnb',
        'platform' => 'airbnb',
        'rating' => 5,
        'date' => 'ianuarie 2026',
        'stay' => 'Ședere de câteva nopți',
        'text' => 'Foarte frumos și bine aranjat',
    ],
    [
        'name' => 'Andrei',
        'subtitle' => 'De 4 ani pe Airbnb',
        'platform' => 'airbnb',
        'rating' => 5,
        'date' => 'iunie 2025',
        'stay' => 'Ședere de câteva nopți',
        'text' => 'Foarte curat și spațios! Voi reveni cu familia mea acolo !',
    ],
    [
        'name' => 'Joey',
        'subtitle' => 'Thompson\'s Station, Tennessee',
        'platform' => 'booking',
        'rating' => 5,
        'date' => 'ianuarie 2026',
        'stay' => 'Ședere cu copii',
        'text' => 'Ne place locul și am sta din nou. Paturile au fost confortabile. Apartamentul a fost foarte curat. Este chiar lângă o piață. O alegere excelentă pentru o ședere.',
    ],
    [
        'name' => 'Adi',
        'subtitle' => 'De 6 ani pe Airbnb',
        'platform' => 'airbnb',
        'rating' => 5,
        'date' => 'mai 2025',
        'stay' => 'Ședere de o noapte',
        'text' => 'Totul bine apartament 👌 frumos',
    ],
    [
        'name' => 'Oleksandr',
        'subtitle' => 'Kingston upon Hull, Regatul Unit',
        'platform' => 'airbnb',
        'rating' => 5,
        'date' => 'octombrie 2025',
        'stay' => 'Ședere de circa o săptămână',
        'text' => '.',
    ],
    [
        'name' => 'Oleg',
        'subtitle' => 'Sacramento, California',
        'platform' => 'airbnb',
        'rating' => 5,
        'date' => 'septembrie 2025',
        'stay' => 'Ședere cu copii',
        'text' => 'Foarte recomandat! Unul dintre cele mai bune apartamente din acest oraș!',
    ],
    [
        'name' => 'Viorica',
        'subtitle' => 'De 9 luni pe Airbnb',
        'platform' => 'airbnb',
        'rating' => 5,
        'date' => 'august 2025',
        'stay' => 'Ședere de peste o săptămână',
        'text' => 'Ședere minunată,plăcută și curată!',
    ],
];
$lh_review_mailto = 'mailto:contact@likehome.md?subject=' . rawurlencode(__('page.index.reviews_mailto_subject'));

$lh_str_len = static function (string $s): int {
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
};
$lh_str_cut = static function (string $s, int $len): string {
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $len, 'UTF-8');
    }
    return substr($s, 0, $len);
};
?>
<!-- ── Recenzii oaspeți: bară + carousel (aceeași distanță verticală ca secțiunea „De ce Like HOME”) ─ -->
<section class="w-full border-t border-cta/10 bg-gradient-to-b from-cta/[0.04] to-transparent mt-6 md:mt-8" aria-labelledby="home-reviews-heading">
  <div class="max-w-6xl mx-auto px-4 pt-3 pb-4 md:pt-4 md:pb-5">
  <div class="rounded-2xl border border-black/[0.08] bg-white/85 backdrop-blur-sm shadow-sm shadow-black/[0.04] px-3 sm:px-5 md:px-6 mb-4 md:mb-5 flex flex-row flex-nowrap items-center justify-center gap-2 sm:gap-3 md:gap-4 py-3 md:py-4 min-h-[3.25rem] overflow-x-auto no-scrollbar">
      <span class="text-2xl sm:text-3xl md:text-4xl font-bold text-ink tabular-nums tracking-tight shrink-0">4,9</span>
      <span class="hidden sm:inline h-7 w-px sm:h-8 bg-black/10 shrink-0 self-center" aria-hidden="true"></span>
      <div class="flex items-center gap-3 sm:gap-4 shrink-0" role="group" aria-label="Platforme de recenzii">
        <span class="inline-flex items-center h-7 sm:h-8" title="Booking.com">
          <svg class="h-7 w-auto sm:h-8 text-[#003580]" role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
            <path fill="currentColor" d="M24 0H0v24h24ZM8.575 6.563h2.658c2.108 0 3.473 1.15 3.473 2.898 0 1.15-.575 1.82-.91 2.108l-.287.263.335.192c.815.479 1.318 1.389 1.318 2.395 0 1.988-1.51 3.257-3.857 3.257H7.449V7.713c0-.623.503-1.126 1.126-1.15zm1.7 1.868c-.479.024-.694.264-.694.79v1.893h1.676c.958 0 1.294-.743 1.294-1.365 0-.815-.503-1.318-1.318-1.318zm-.096 4.36c-.407.071-.598.31-.598.79v2.251h1.868c.934 0 1.509-.55 1.509-1.533 0-.934-.599-1.509-1.51-1.509zm7.737 2.394c.743 0 1.341.599 1.341 1.342a1.34 1.34 0 0 1-1.341 1.341 1.355 1.355 0 0 1-1.341-1.341c0-.743.598-1.342 1.34-1.342z"/>
          </svg>
          <span class="sr-only">Booking.com</span>
        </span>
        <span class="inline-flex items-center h-7 sm:h-8" title="Airbnb">
          <svg class="h-7 w-auto sm:h-8 text-[#FF5A5F]" role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
            <path fill="currentColor" d="M12.001 18.275c-1.353-1.697-2.148-3.184-2.413-4.457-.263-1.027-.16-1.848.291-2.465.477-.71 1.188-1.056 2.121-1.056s1.643.345 2.12 1.063c.446.61.558 1.432.286 2.465-.291 1.298-1.085 2.785-2.412 4.458zm9.601 1.14c-.185 1.246-1.034 2.28-2.2 2.783-2.253.98-4.483-.583-6.392-2.704 3.157-3.951 3.74-7.028 2.385-9.018-.795-1.14-1.933-1.695-3.394-1.695-2.944 0-4.563 2.49-3.927 5.382.37 1.565 1.352 3.343 2.917 5.332-.98 1.085-1.91 1.856-2.732 2.333-.636.344-1.245.558-1.828.609-2.679.399-4.778-2.2-3.825-4.88.132-.345.395-.98.845-1.961l.025-.053c1.464-3.178 3.242-6.79 5.285-10.795l.053-.132.58-1.116c.45-.822.635-1.19 1.351-1.643.346-.21.77-.315 1.246-.315.954 0 1.698.558 2.016 1.007.158.239.345.557.582.953l.558 1.089.08.159c2.041 4.004 3.821 7.608 5.279 10.794l.026.025.533 1.22.318.764c.243.613.294 1.222.213 1.858zm1.22-2.39c-.186-.583-.505-1.271-.9-2.094v-.03c-1.889-4.006-3.642-7.608-5.307-10.844l-.111-.163C15.317 1.461 14.468 0 12.001 0c-2.44 0-3.476 1.695-4.535 3.898l-.081.16c-1.669 3.236-3.421 6.843-5.303 10.847v.053l-.559 1.22c-.21.504-.317.768-.345.847C-.172 20.74 2.611 24 5.98 24c.027 0 .132 0 .265-.027h.372c1.75-.213 3.554-1.325 5.384-3.317 1.829 1.989 3.635 3.104 5.382 3.317h.372c.133.027.239.027.265.027 3.37.003 6.152-3.261 4.802-6.975z"/>
          </svg>
          <span class="sr-only">Airbnb</span>
        </span>
      </div>
      <span class="hidden sm:inline h-8 w-px bg-black/10 shrink-0 self-center" aria-hidden="true"></span>
    <h2 id="home-reviews-heading" class="text-xs sm:text-sm md:text-base lg:text-lg font-semibold text-ink leading-snug text-center shrink min-w-0">
      <?= htmlspecialchars($lh_reviews_hero_title, ENT_QUOTES, 'UTF-8') ?>
    </h2>
  </div>

  <div class="relative" role="region" aria-roledescription="carousel" aria-label="<?= htmlspecialchars(__('page.index.reviews_aria'), ENT_QUOTES, 'UTF-8') ?>">
    <div class="flex items-center gap-2 sm:gap-3">
      <button
        type="button"
        id="lh-home-reviews-prev"
        class="lh-home-reviews-arrow inline-flex h-11 w-11 sm:h-12 sm:w-12 shrink-0 items-center justify-center rounded-full border border-black/[0.08] bg-white text-ink shadow-sm hover:bg-cta/10 hover:border-cta/30 transition-colors disabled:pointer-events-none disabled:opacity-40"
        aria-label="<?= htmlspecialchars(__('page.index.reviews_prev'), ENT_QUOTES, 'UTF-8') ?>"
        aria-controls="lh-home-reviews-track"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </button>
      <div
        id="lh-home-reviews-track"
        class="flex-1 min-w-0 flex items-stretch gap-4 overflow-x-auto scroll-smooth snap-x snap-mandatory no-scrollbar touch-scroll-x overscroll-x-contain py-1 pr-1"
      >
    <?php foreach ($lh_reviews_static as $rev): ?>
    <?php
        $lh_initial = function_exists('mb_substr')
            ? mb_strtoupper(mb_substr($rev['name'], 0, 1, 'UTF-8'), 'UTF-8')
            : strtoupper(substr($rev['name'], 0, 1));
        $lh_long = $lh_str_len($rev['text']) > 150;
        $lh_platform = ($rev['platform'] ?? 'airbnb') === 'booking' ? 'booking' : 'airbnb';
    ?>
        <article class="lh-home-review-card shrink-0 snap-start min-w-0 self-stretch w-full max-w-full flex-[0_0_100%] sm:flex-[0_0_calc((100%-1rem)/2)] lg:flex-[0_0_calc((100%-2rem)/3)] rounded-2xl border border-black/[0.06] bg-white/90 p-3 sm:p-4 md:p-5 shadow-sm shadow-black/[0.04] flex flex-col h-full sm:max-w-none">
          <div class="flex-1 flex flex-col min-w-0 min-h-0">
          <header class="flex gap-2.5 sm:gap-3 mb-2 sm:mb-3 shrink-0">
            <div class="h-10 w-10 sm:h-11 sm:w-11 rounded-full bg-gradient-to-br from-logo/90 to-brand-700 flex items-center justify-center text-white text-xs sm:text-sm font-semibold shrink-0" aria-hidden="true">
              <?= htmlspecialchars($lh_initial, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="min-w-0">
              <p class="text-sm sm:text-base font-semibold text-ink leading-tight"><?= htmlspecialchars($rev['name'], ENT_QUOTES, 'UTF-8') ?></p>
              <p class="text-xs sm:text-sm text-blue-grey leading-snug"><?= htmlspecialchars($rev['subtitle'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
          </header>
          <div class="flex flex-wrap items-center gap-x-1.5 sm:gap-x-2 gap-y-1 text-xs sm:text-sm text-blue-grey mb-2 sm:mb-3 shrink-0">
            <span class="inline-flex text-ink gap-0.5" aria-label="<?= htmlspecialchars(__('page.index.rating_stars', ['n' => (string) (int) $rev['rating']]), ENT_QUOTES, 'UTF-8') ?>">
              <?php for ($s = 0; $s < (int) $rev['rating']; $s++): ?>
              <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
              <?php endfor; ?>
            </span>
            <span class="text-blue-grey/80" aria-hidden="true">·</span>
            <span><?= htmlspecialchars($rev['date'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="text-blue-grey/80" aria-hidden="true">·</span>
            <span><?= htmlspecialchars($rev['stay'], ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="flex-1 flex flex-col min-w-0 min-h-0">
          <?php if ($lh_long): ?>
          <details class="group text-ink/90 flex flex-col min-h-0 text-xs sm:text-sm leading-snug">
            <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden shrink-0 block h-[3lh] overflow-hidden open:h-auto open:min-h-0 open:overflow-visible">
              <span class="group-open:hidden line-clamp-3 block"><?= htmlspecialchars($lh_str_cut($rev['text'], 150) . '… ', ENT_QUOTES, 'UTF-8') ?><span class="text-ink font-medium underline underline-offset-2"><?= htmlspecialchars(__('page.index.reviews_more'), ENT_QUOTES, 'UTF-8') ?></span></span>
            </summary>
            <p class="mt-2 text-xs sm:text-sm leading-snug"><?= htmlspecialchars($rev['text'], ENT_QUOTES, 'UTF-8') ?></p>
          </details>
          <?php else: ?>
          <p class="text-ink/90 text-xs sm:text-sm leading-snug line-clamp-3 h-[3lh] overflow-hidden"><?= htmlspecialchars($rev['text'], ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
          </div>
          </div>
          <div class="mt-auto pt-2.5 sm:pt-3 border-t border-black/[0.06] flex items-center gap-2 text-xs text-blue-grey">
            <?php if ($lh_platform === 'booking'): ?>
            <svg class="h-4 sm:h-5 w-auto shrink-0 text-[#003580]" role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
              <path fill="currentColor" d="M24 0H0v24h24ZM8.575 6.563h2.658c2.108 0 3.473 1.15 3.473 2.898 0 1.15-.575 1.82-.91 2.108l-.287.263.335.192c.815.479 1.318 1.389 1.318 2.395 0 1.988-1.51 3.257-3.857 3.257H7.449V7.713c0-.623.503-1.126 1.126-1.15zm1.7 1.868c-.479.024-.694.264-.694.79v1.893h1.676c.958 0 1.294-.743 1.294-1.365 0-.815-.503-1.318-1.318-1.318zm-.096 4.36c-.407.071-.598.31-.598.79v2.251h1.868c.934 0 1.509-.55 1.509-1.533 0-.934-.599-1.509-1.51-1.509zm7.737 2.394c.743 0 1.341.599 1.341 1.342a1.34 1.34 0 0 1-1.341 1.341 1.355 1.355 0 0 1-1.341-1.341c0-.743.598-1.342 1.34-1.342z"/>
            </svg>
            <span><?= htmlspecialchars(__('page.index.review_on_booking'), ENT_QUOTES, 'UTF-8') ?></span>
            <?php else: ?>
            <svg class="h-4 sm:h-5 w-auto shrink-0 text-[#FF5A5F]" role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
              <path fill="currentColor" d="M12.001 18.275c-1.353-1.697-2.148-3.184-2.413-4.457-.263-1.027-.16-1.848.291-2.465.477-.71 1.188-1.056 2.121-1.056s1.643.345 2.12 1.063c.446.61.558 1.432.286 2.465-.291 1.298-1.085 2.785-2.412 4.458zm9.601 1.14c-.185 1.246-1.034 2.28-2.2 2.783-2.253.98-4.483-.583-6.392-2.704 3.157-3.951 3.74-7.028 2.385-9.018-.795-1.14-1.933-1.695-3.394-1.695-2.944 0-4.563 2.49-3.927 5.382.37 1.565 1.352 3.343 2.917 5.332-.98 1.085-1.91 1.856-2.732 2.333-.636.344-1.245.558-1.828.609-2.679.399-4.778-2.2-3.825-4.88.132-.345.395-.98.845-1.961l.025-.053c1.464-3.178 3.242-6.79 5.285-10.795l.053-.132.58-1.116c.45-.822.635-1.19 1.351-1.643.346-.21.77-.315 1.246-.315.954 0 1.698.558 2.016 1.007.158.239.345.557.582.953l.558 1.089.08.159c2.041 4.004 3.821 7.608 5.279 10.794l.026.025.533 1.22.318.764c.243.613.294 1.222.213 1.858zm1.22-2.39c-.186-.583-.505-1.271-.9-2.094v-.03c-1.889-4.006-3.642-7.608-5.307-10.844l-.111-.163C15.317 1.461 14.468 0 12.001 0c-2.44 0-3.476 1.695-4.535 3.898l-.081.16c-1.669 3.236-3.421 6.843-5.303 10.847v.053l-.559 1.22c-.21.504-.317.768-.345.847C-.172 20.74 2.611 24 5.98 24c.027 0 .132 0 .265-.027h.372c1.75-.213 3.554-1.325 5.384-3.317 1.829 1.989 3.635 3.104 5.382 3.317h.372c.133.027.239.027.265.027 3.37.003 6.152-3.261 4.802-6.975z"/>
            </svg>
            <span><?= htmlspecialchars(__('page.index.review_on_airbnb'), ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
          </div>
        </article>
    <?php endforeach; ?>
      </div>
      <button
        type="button"
        id="lh-home-reviews-next"
        class="lh-home-reviews-arrow inline-flex h-11 w-11 sm:h-12 sm:w-12 shrink-0 items-center justify-center rounded-full border border-black/[0.08] bg-white text-ink shadow-sm hover:bg-cta/10 hover:border-cta/30 transition-colors disabled:pointer-events-none disabled:opacity-40"
        aria-label="<?= htmlspecialchars(__('page.index.reviews_next'), ENT_QUOTES, 'UTF-8') ?>"
        aria-controls="lh-home-reviews-track"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </button>
    </div>
  </div>

  <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center justify-center gap-3 mt-4 md:mt-5">
    <a
      href="https://www.instagram.com/like.home.e/"
      target="_blank"
      rel="noopener noreferrer"
      class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold border-2 border-black/[0.08] text-ink bg-white hover:bg-black/[0.03] transition-colors shadow-sm shadow-black/[0.04]"
    >
      <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
        <defs>
          <linearGradient id="lh-instagram-brand-gradient" x1="0%" y1="100%" x2="100%" y2="0%">
            <stop offset="0%" stop-color="#f09433"/>
            <stop offset="25%" stop-color="#e6683c"/>
            <stop offset="50%" stop-color="#dc2743"/>
            <stop offset="75%" stop-color="#cc2366"/>
            <stop offset="100%" stop-color="#bc1888"/>
          </linearGradient>
        </defs>
        <path fill="url(#lh-instagram-brand-gradient)" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
      </svg>
      <?= htmlspecialchars(__('page.index.reviews_instagram'), ENT_QUOTES, 'UTF-8') ?>
    </a>
    <a
      href="<?= htmlspecialchars($lh_review_mailto, ENT_QUOTES, 'UTF-8') ?>"
      class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold bg-cta text-white shadow-md shadow-black/10 hover:brightness-110 transition-all"
    >
      <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
      </svg>
      <?= htmlspecialchars(__('page.index.reviews_leave'), ENT_QUOTES, 'UTF-8') ?>
    </a>
  </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
