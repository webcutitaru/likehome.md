<?php
require_once __DIR__ . '/site_nav.php';
$lh_footer_nav = lh_site_nav_items();
$lh_footer_email = lh_site_contact_email();
$lh_footer_city = lh_site_contact_city();
?>
<footer class="bg-logo text-white border-t border-white/10 mt-6 md:mt-8">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-14 md:py-16 grid grid-cols-1 md:grid-cols-12 gap-12 md:gap-10 lg:gap-14">

    <!-- Brand -->
    <div class="md:col-span-5 space-y-5">
      <div class="flex items-start gap-4">
        <span class="flex h-[52px] w-[52px] shrink-0 items-center justify-center rounded-full bg-white/10 ring-2 ring-white/20 shadow-lg shadow-black/25" role="img" aria-label="Like HOME">
          <svg class="h-7 w-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
          </svg>
        </span>
        <p class="text-sm text-white leading-relaxed pt-0.5 max-w-md">
          <?= htmlspecialchars(__('footer.tagline'), ENT_QUOTES, 'UTF-8') ?>
        </p>
      </div>
    </div>

    <!-- Navigation -->
    <div class="md:col-span-4 space-y-4">
      <h4 class="text-white font-semibold text-xs uppercase tracking-[0.22em]"><?= htmlspecialchars(__('nav.footer'), ENT_QUOTES, 'UTF-8') ?></h4>
      <nav class="flex flex-col gap-2.5 text-sm" aria-label="<?= htmlspecialchars(__('nav.footer'), ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($lh_footer_nav as $fItem):
            $fCurrent = lh_nav_is_current($fItem['file']);
            $fClass = $fCurrent ? 'text-white font-semibold hover:text-zinc-200 transition-colors' : 'text-white hover:text-zinc-200 transition-colors';
        ?>
          <a
            href="<?= htmlspecialchars($fItem['href'], ENT_QUOTES, 'UTF-8') ?>"
            class="<?= $fClass ?>"
            <?= $fCurrent ? 'aria-current="page"' : '' ?>
          ><?= htmlspecialchars($fItem['label'], ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
        <a href="<?= htmlspecialchars(lh_locale_url('index.php#search-bar'), ENT_QUOTES, 'UTF-8') ?>" class="text-white hover:text-zinc-200 transition-colors pt-1"><?= htmlspecialchars(__('nav.book_footer'), ENT_QUOTES, 'UTF-8') ?></a>
      </nav>
    </div>

    <!-- Contact -->
    <div class="md:col-span-3 space-y-4">
      <h4 class="text-white font-semibold text-xs uppercase tracking-[0.22em]"><?= htmlspecialchars(__('footer.contact'), ENT_QUOTES, 'UTF-8') ?></h4>
      <div class="flex flex-col gap-1 text-sm">
        <span class="text-white text-xs uppercase tracking-wide"><?= htmlspecialchars(__('footer.email'), ENT_QUOTES, 'UTF-8') ?></span>
        <a href="mailto:<?= htmlspecialchars($lh_footer_email, ENT_QUOTES, 'UTF-8') ?>" class="text-white hover:text-zinc-200 transition-colors font-medium"><?= htmlspecialchars($lh_footer_email, ENT_QUOTES, 'UTF-8') ?></a>

        <span class="text-white text-xs uppercase tracking-wide mt-4"><?= htmlspecialchars(__('footer.location'), ENT_QUOTES, 'UTF-8') ?></span>
        <span class="text-white"><?= htmlspecialchars($lh_footer_city, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    </div>

  </div>

  <div class="border-t border-white/10">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">
      <div class="text-center text-[11px] text-white/80 leading-relaxed max-w-3xl mx-auto">
        <p class="font-semibold text-white"><?= htmlspecialchars(lh_company_legal_name(), ENT_QUOTES, 'UTF-8') ?></p>
        <p><?= htmlspecialchars(__('footer.legal_idno'), ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars(lh_company_idno(), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(lh_company_legal_address(), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <div class="flex flex-wrap items-center justify-center gap-3 sm:gap-4" aria-label="<?= htmlspecialchars(__('footer.payment_logos'), ENT_QUOTES, 'UTF-8') ?>">
        <img src="<?= htmlspecialchars(lh_public_url('assets/img/payments/maib.png'), ENT_QUOTES, 'UTF-8') ?>" alt="maib" width="72" height="28" class="h-7 w-auto" loading="lazy" decoding="async">
        <img src="<?= htmlspecialchars(lh_public_url('assets/img/payments/visa.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Visa" width="48" height="16" class="h-4 w-auto" loading="lazy" decoding="async">
        <img src="<?= htmlspecialchars(lh_public_url('assets/img/payments/mastercard.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Mastercard" width="48" height="16" class="h-4 w-auto" loading="lazy" decoding="async">
        <img src="<?= htmlspecialchars(lh_public_url('assets/img/payments/amex.png'), ENT_QUOTES, 'UTF-8') ?>" alt="American Express" width="48" height="16" class="h-4 w-auto" loading="lazy" decoding="async">
      </div>
      <div class="flex flex-col sm:flex-row justify-between items-center gap-4 text-xs text-white">
        <div>© <?php echo date('Y'); ?> Like HOME. <?= htmlspecialchars(__('footer.rights'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="flex flex-wrap justify-center items-center gap-x-6 gap-y-2 sm:gap-x-8">
          <a href="<?= htmlspecialchars(lh_locale_url('terms.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-white hover:text-zinc-200 transition-colors"><?= htmlspecialchars(__('footer.terms'), ENT_QUOTES, 'UTF-8') ?></a>
          <a href="<?= htmlspecialchars(lh_locale_url('privacy.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-white hover:text-zinc-200 transition-colors"><?= htmlspecialchars(__('footer.privacy'), ENT_QUOTES, 'UTF-8') ?></a>
          <button type="button" id="lh-footer-cookie-settings" class="text-white hover:text-zinc-200 transition-colors bg-transparent border-0 cursor-pointer p-0 text-xs font-inherit underline decoration-white/30 underline-offset-2">
            <?= htmlspecialchars(__('footer.cookies'), ENT_QUOTES, 'UTF-8') ?>
          </button>
        </div>
      </div>
    </div>
  </div>
</footer>
<?php require_once __DIR__ . '/cookie_banner.php'; ?>
<?php
$lhFpLocale = lh_current_locale();
$lhFpLocaleJs = match ($lhFpLocale) {
    'en' => 'en',
    'ru' => 'ru',
    default => 'ro',
};
?>
<!-- Flatpickr JS -->
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<?php if ($lhFpLocaleJs !== 'en'): ?>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/<?= htmlspecialchars($lhFpLocaleJs, ENT_QUOTES, 'UTF-8') ?>.js"></script>
<?php endif; ?>
  <script>window.lhFlatpickrLocale = <?= json_encode($lhFpLocaleJs, JSON_UNESCAPED_UNICODE) ?>;</script>
<?php require_once __DIR__ . '/i18n_js.php'; lh_i18n_script_tags(); ?>

  <!-- Scripts proprii -->
  <script src="<?= htmlspecialchars(lh_public_url('assets/js/scripts.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(lh_public_url('assets/js/property-card-slider.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
