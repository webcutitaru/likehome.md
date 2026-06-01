<?php
declare(strict_types=1);

$lhSeoTitle = (string) ($pageTitle ?? 'Like HOME');
$lhSeoDesc = isset($pageDescription) ? (string) $pageDescription : '';
$lhSeoCanonical = isset($canonicalUrl) ? trim((string) $canonicalUrl) : '';
if ($lhSeoCanonical === '') {
    $lhSeoCanonical = lh_seo_fallback_canonical();
}
$lhSeoOgTitle = isset($ogTitle) ? (string) $ogTitle : $lhSeoTitle;
$lhSeoOgDesc = isset($ogDescription) ? (string) $ogDescription : ($lhSeoDesc !== '' ? $lhSeoDesc : $lhSeoTitle);
$lhSeoOgType = isset($ogType) ? (string) $ogType : 'website';
$lhSeoOgImage = isset($ogImage) ? trim((string) $ogImage) : '';
$lhSeoOgLocale = isset($ogLocale) ? (string) $ogLocale : lh_locale_og_tag();
$lhSeoTwitterCard = isset($twitterCard) ? (string) $twitterCard : ($lhSeoOgImage !== '' ? 'summary_large_image' : 'summary');
$lhSeoRobots = isset($robotsMeta) ? (string) $robotsMeta : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
$lhSeoSiteName = isset($siteName) ? (string) $siteName : 'Like HOME';
$lhHeadJsonLd = (isset($lhJsonLd) && is_string($lhJsonLd) && trim($lhJsonLd) !== '') ? trim($lhJsonLd) : '';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(lh_locale_html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($lhSeoTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="robots" content="<?= htmlspecialchars($lhSeoRobots, ENT_QUOTES, 'UTF-8') ?>">
<?php if ($lhSeoDesc !== ''): ?>
  <meta name="description" content="<?= htmlspecialchars($lhSeoDesc, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
  <link rel="canonical" href="<?= htmlspecialchars($lhSeoCanonical, ENT_QUOTES, 'UTF-8') ?>">
<?php
$lhAlternateUrls = (isset($lhLocaleAlternateUrls) && is_array($lhLocaleAlternateUrls))
    ? $lhLocaleAlternateUrls
    : lh_locale_alternate_urls();
foreach ($lhAlternateUrls as $lhHrefLang => $lhAltHref):
?>
  <link rel="alternate" hreflang="<?= htmlspecialchars($lhHrefLang, ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($lhAltHref, ENT_QUOTES, 'UTF-8') ?>">
<?php endforeach;
if (isset($lhAlternateUrls['ro'])): ?>
  <link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($lhAlternateUrls['ro'], ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
  <meta name="theme-color" content="#1e3a5f">
  <meta property="og:site_name" content="<?= htmlspecialchars($lhSeoSiteName, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:locale" content="<?= htmlspecialchars($lhSeoOgLocale, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:type" content="<?= htmlspecialchars($lhSeoOgType, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:title" content="<?= htmlspecialchars($lhSeoOgTitle, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:description" content="<?= htmlspecialchars($lhSeoOgDesc, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:url" content="<?= htmlspecialchars($lhSeoCanonical, ENT_QUOTES, 'UTF-8') ?>">
<?php if ($lhSeoOgImage !== ''): ?>
  <meta property="og:image" content="<?= htmlspecialchars($lhSeoOgImage, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
  <meta name="twitter:card" content="<?= htmlspecialchars($lhSeoTwitterCard, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="twitter:title" content="<?= htmlspecialchars($lhSeoOgTitle, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($lhSeoOgDesc, ENT_QUOTES, 'UTF-8') ?>">
<?php if ($lhSeoOgImage !== ''): ?>
  <meta name="twitter:image" content="<?= htmlspecialchars($lhSeoOgImage, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
  <link rel="icon" href="<?= htmlspecialchars(lh_public_url('assets/favicon.svg'), ENT_QUOTES, 'UTF-8') ?>" type="image/svg+xml">

  <!-- Tailwind: npm run build:css → assets/css/tailwind.build.css (vezi tailwind.config.js) -->

  <!-- Google Font: Inter (opțional, elimină dacă ai deja un font) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Flatpickr CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css">

  <link rel="stylesheet" href="<?= htmlspecialchars(lh_public_url('assets/css/tailwind.build.css'), ENT_QUOTES, 'UTF-8') ?>">
  <!-- Stiluri custom proprii (după Tailwind) -->
  <link rel="stylesheet" href="<?= htmlspecialchars(lh_public_url('assets/css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
  <style>
    .site-shell {
      background:
        radial-gradient(circle at top left, rgb(var(--color-logo) / 0.06), transparent 28%),
        radial-gradient(circle at top right, rgb(var(--color-blue-grey) / 0.08), transparent 24%),
        linear-gradient(180deg, var(--surface) 0%, var(--surface-2) 100%);
    }
    .premium-header-blur {
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
    }
    .no-scrollbar::-webkit-scrollbar {
      display: none;
    }
    .no-scrollbar {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }
    @media (max-width: 767px) {
      .touch-scroll-x {
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-x: contain;
      }
    }
    .mobile-drawer-open {
      overflow: hidden;
    }
    #site-header.is-scrolled {
      box-shadow: 0 8px 28px rgb(0 0 0 / 0.14);
    }
    .lh-lang-switcher__menu {
      transform-origin: top right;
      transform: translateY(-4px) scale(0.98);
      opacity: 0;
      transition: opacity 0.15s ease, transform 0.15s ease;
    }
    .lh-lang-switcher__menu--open {
      transform: translateY(0) scale(1);
      opacity: 1;
    }
  </style>
<?php if ($lhHeadJsonLd !== ''): ?>
  <script type="application/ld+json"><?= $lhHeadJsonLd ?></script>
<?php endif; ?>
<?php require_once __DIR__ . '/clarity.php'; ?>
<?php require_once __DIR__ . '/gtag.php'; ?>
</head>
<body class="site-shell text-ink font-sans antialiased min-h-screen">
<?php
require_once __DIR__ . '/site_nav.php';
$lh_nav_items = lh_site_nav_items();
?>
<header id="site-header" class="sticky top-0 z-50 border-b border-white/10 bg-logo text-white transition-shadow duration-300">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="relative flex items-center min-h-[4.25rem] py-2 gap-4 lg:gap-6">
      <div class="flex flex-1 min-w-0 justify-start">
        <a href="<?= htmlspecialchars(lh_locale_url(), ENT_QUOTES, 'UTF-8') ?>" class="flex items-center gap-2.5 group shrink-0 min-w-0" aria-label="<?= htmlspecialchars(__('nav.home_aria'), ENT_QUOTES, 'UTF-8') ?>">
          <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/10 ring-1 ring-white/20 shadow-sm shadow-black/10">
            <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
          </span>
          <span class="flex flex-col justify-center leading-tight text-white min-w-0">
            <span class="text-[13px] font-medium tracking-tight">Like</span>
            <span class="text-[13px] font-bold tracking-wide uppercase">HOME</span>
          </span>
        </a>
      </div>

      <nav class="hidden lg:flex absolute left-1/2 top-1/2 z-10 -translate-x-1/2 -translate-y-1/2 items-center gap-0.5 xl:gap-1" aria-label="<?= htmlspecialchars(__('nav.main'), ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($lh_nav_items as $item):
            $navCurrent = lh_nav_is_current($item['file']);
            $navClass = $navCurrent
                ? 'lh-nav-link lh-nav-link--current text-white bg-white/20 shadow-sm shadow-black/10'
                : 'lh-nav-link text-white/85 hover:text-white hover:bg-white/10';
        ?>
          <a
            href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
            class="px-3 xl:px-3.5 py-2 rounded-lg text-sm font-semibold tracking-tight transition-colors whitespace-nowrap <?= $navClass ?>"
            <?= $navCurrent ? 'aria-current="page"' : '' ?>
          ><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
      </nav>

      <div class="relative z-20 flex flex-1 justify-end items-center gap-1.5 sm:gap-2 min-w-0 shrink-0 pointer-events-none">
<?php require __DIR__ . '/locale_switcher.php'; ?>
        <a href="<?= htmlspecialchars(lh_locale_url('index.php#search-bar'), ENT_QUOTES, 'UTF-8') ?>" class="lh-header-cta hidden sm:inline-flex lg:hidden items-center px-4 py-2.5 rounded-xl text-sm font-semibold transition-all whitespace-nowrap bg-white/15 text-white hover:bg-white/25 ring-1 ring-white/20 pointer-events-auto">
          <?= htmlspecialchars(__('nav.book'), ENT_QUOTES, 'UTF-8') ?>
        </a>
        <button id="mobile-menu-toggle" type="button" class="lg:hidden inline-flex items-center justify-center w-10 h-10 rounded-xl transition-all border border-white/30 text-white hover:bg-white/10 pointer-events-auto" aria-label="<?= htmlspecialchars(__('nav.open_menu'), ENT_QUOTES, 'UTF-8') ?>" aria-expanded="false" aria-controls="mobile-drawer">
          <svg id="mobile-menu-open-icon" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
          <svg id="mobile-menu-close-icon" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>

    <div id="mobile-drawer-backdrop" class="fixed inset-0 bg-ink/40 opacity-0 pointer-events-none transition-opacity duration-300 lg:hidden z-50"></div>
    <aside id="mobile-drawer" class="fixed top-0 right-0 h-full w-[86vw] max-w-sm bg-white/95 text-ink premium-header-blur shadow-2xl shadow-black/15 translate-x-full transition-transform duration-300 lg:hidden z-[60] border-l border-black/8">
      <div class="flex items-center justify-between px-5 py-4 border-b border-black/8">
        <a href="<?= htmlspecialchars(lh_locale_url(), ENT_QUOTES, 'UTF-8') ?>" class="flex items-center gap-2 min-w-0" aria-label="<?= htmlspecialchars(__('nav.home_aria'), ENT_QUOTES, 'UTF-8') ?>">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-logo text-white ring-1 ring-black/10 shadow-sm">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
          </span>
          <span class="flex flex-col leading-tight text-ink min-w-0">
            <span class="text-xs font-medium tracking-tight">Like</span>
            <span class="text-xs font-bold tracking-wide uppercase">HOME</span>
          </span>
        </a>
        <button id="mobile-drawer-close" type="button" class="inline-flex items-center justify-center w-10 h-10 rounded-xl border border-black/10 text-ink hover:bg-black/[0.04] transition-all" aria-label="<?= htmlspecialchars(__('nav.close_menu'), ENT_QUOTES, 'UTF-8') ?>">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      <nav class="px-5 py-6 flex flex-col min-h-[calc(100vh-81px)]" aria-label="<?= htmlspecialchars(__('nav.mobile'), ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($lh_nav_items as $item):
            $navCurrent = lh_nav_is_current($item['file']);
            $mClass = $navCurrent ? 'text-ink font-semibold' : 'text-ink/70 font-medium';
        ?>
          <a
            href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
            class="py-3.5 text-[15px] border-b border-black/[0.06] first:pt-0 transition-colors hover:text-ink <?= $mClass ?>"
            <?= $navCurrent ? 'aria-current="page"' : '' ?>
          ><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
        <a href="<?= htmlspecialchars(lh_locale_url('index.php#search-bar'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6 py-3.5 text-center rounded-xl bg-cta text-white text-sm font-semibold shadow-md shadow-black/10 hover:brightness-110 transition-all">
          <?= htmlspecialchars(__('nav.book_search'), ENT_QUOTES, 'UTF-8') ?>
        </a>
      </nav>
    </aside>
  </div>
</header>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;
    const toggleBtn = document.getElementById('mobile-menu-toggle');
    const closeBtn = document.getElementById('mobile-drawer-close');
    const drawer = document.getElementById('mobile-drawer');
    const backdrop = document.getElementById('mobile-drawer-backdrop');
    const openIcon = document.getElementById('mobile-menu-open-icon');
    const closeIcon = document.getElementById('mobile-menu-close-icon');

    if (!toggleBtn || !drawer || !backdrop) return;

    function openDrawer() {
      drawer.classList.remove('translate-x-full');
      backdrop.classList.remove('opacity-0', 'pointer-events-none');
      backdrop.classList.add('opacity-100');
      body.classList.add('mobile-drawer-open');
      toggleBtn.setAttribute('aria-expanded', 'true');
      if (openIcon) openIcon.classList.add('hidden');
      if (closeIcon) closeIcon.classList.remove('hidden');
    }

    function closeDrawer() {
      drawer.classList.add('translate-x-full');
      backdrop.classList.add('opacity-0', 'pointer-events-none');
      backdrop.classList.remove('opacity-100');
      body.classList.remove('mobile-drawer-open');
      toggleBtn.setAttribute('aria-expanded', 'false');
      if (openIcon) openIcon.classList.remove('hidden');
      if (closeIcon) closeIcon.classList.add('hidden');
    }

    toggleBtn.addEventListener('click', function () {
      const isOpen = !drawer.classList.contains('translate-x-full');
      if (isOpen) {
        closeDrawer();
      } else {
        openDrawer();
      }
    });

    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    backdrop.addEventListener('click', closeDrawer);

    drawer.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', closeDrawer);
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') closeDrawer();
    });

    var siteHeader = document.getElementById('site-header');
    if (siteHeader) {
      function lhHeaderScroll() {
        if (window.scrollY > 8) {
          siteHeader.classList.add('is-scrolled');
        } else {
          siteHeader.classList.remove('is-scrolled');
        }
      }
      lhHeaderScroll();
      window.addEventListener('scroll', lhHeaderScroll, { passive: true });
    }
  });
</script>
<main>
