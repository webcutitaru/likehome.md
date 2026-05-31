<?php

declare(strict_types=1);

/**
 * Google Analytics 4 — Consent Mode v2.
 * Încărcat din includes/header.php (după config.php).
 * Consent implicit: denied. Se actualizează via lh:cookie-consent în cookie-consent.js.
 */
if (!function_exists('lh_env')) {
    return;
}

$gaId = trim(lh_env('GA_MEASUREMENT_ID', ''));
if ($gaId === '' || !preg_match('/^G-[A-Z0-9]+$/', $gaId)) {
    return;
}

$gaIdJson = json_encode($gaId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($gaIdJson === false) {
    return;
}
?>
  <!-- Google Analytics 4 – Consent Mode v2 -->
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('consent', 'default', {
      analytics_storage: 'denied',
      ad_storage: 'denied',
      wait_for_update: 500
    });
    gtag('js', new Date());
    gtag('config', <?= $gaIdJson ?>);
  </script>
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($gaId, ENT_QUOTES, 'UTF-8') ?>"></script>
