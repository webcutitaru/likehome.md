<?php

declare(strict_types=1);

if (!function_exists('lh_i18n_script_tags')) {
    function lh_i18n_script_tags(): void
    {
        $strings = lh_translation_strings();
        $locale = lh_current_locale();
        $localePrefix = $locale === lh_default_locale()
            ? ''
            : '/' . $locale;
        $base = defined('SITE_BASE_PATH') ? (string) SITE_BASE_PATH : '';
        ?>
<script>
window.lhI18n = <?= json_encode($strings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.lhLocale = <?= json_encode($locale, JSON_UNESCAPED_UNICODE) ?>;
window.lhLocalePathPrefix = <?= json_encode($base . $localePrefix, JSON_UNESCAPED_UNICODE) ?>;
window.lhSiteBasePath = <?= json_encode($base, JSON_UNESCAPED_UNICODE) ?>;
function lhT(key, replace) {
  var s = (window.lhI18n && window.lhI18n[key]) || key;
  if (replace && typeof replace === 'object') {
    Object.keys(replace).forEach(function (k) {
      s = s.split(':' + k).join(String(replace[k]));
    });
  }
  return s;
}
function lhLocaleUrl(path) {
  path = path || '';
  var base = window.lhSiteBasePath || '';
  var prefix = window.lhLocalePathPrefix || '';
  if (path.charAt(0) === '/') path = path.slice(1);
  if (path === '' || path === 'index.php') {
    return (base + prefix + '/').replace(/\/{2,}/g, '/').replace(/^(https?:\/[^/]+)\/+/, '$1/') || '/';
  }
  var sep = prefix ? prefix + '/' : (base ? base + '/' : '/');
  if (base && path.indexOf(base + '/') === 0) return path;
  return (base + prefix + '/' + path).replace(/\/{2,}/g, '/');
}
</script>
<?php
    }
}
