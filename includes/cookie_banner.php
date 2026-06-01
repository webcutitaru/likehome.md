<?php

declare(strict_types=1);

$lhCookiePrivacyUrl = lh_locale_url('privacy.php');
?>
<div
  id="lh-cookie-banner"
  class="hidden fixed bottom-0 left-0 right-0 z-[10040] p-4 sm:p-5 pointer-events-none"
  role="dialog"
  aria-modal="false"
  aria-labelledby="lh-cookie-banner-title"
  aria-describedby="lh-cookie-banner-desc"
  data-privacy-url="<?= htmlspecialchars($lhCookiePrivacyUrl, ENT_QUOTES, 'UTF-8') ?>"
>
  <div class="pointer-events-auto max-w-4xl mx-auto rounded-2xl border border-black/10 bg-white/95 backdrop-blur-md shadow-2xl shadow-black/20 text-ink px-5 py-5 sm:px-7 sm:py-6">
    <h2 id="lh-cookie-banner-title" class="text-base sm:text-lg font-bold text-ink tracking-tight"><?= htmlspecialchars(__('cookie.title'), ENT_QUOTES, 'UTF-8') ?></h2>
    <p id="lh-cookie-banner-desc" class="mt-2 text-sm text-blue-grey leading-relaxed">
      <?= htmlspecialchars(__('cookie.description'), ENT_QUOTES, 'UTF-8') ?>
      <a href="<?= htmlspecialchars($lhCookiePrivacyUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-ink font-semibold underline decoration-black/20 underline-offset-2 hover:decoration-ink"><?= htmlspecialchars(__('cookie.privacy_link'), ENT_QUOTES, 'UTF-8') ?></a>
    </p>
    <div class="mt-5 flex flex-col sm:flex-row flex-wrap gap-2 sm:gap-3">
      <button type="button" id="lh-cookie-reject" class="order-2 sm:order-1 px-4 py-2.5 rounded-xl text-sm font-semibold border border-black/15 bg-white text-ink hover:bg-black/[0.03] transition-colors">
        <?= htmlspecialchars(__('cookie.reject'), ENT_QUOTES, 'UTF-8') ?>
      </button>
      <button type="button" id="lh-cookie-customize" class="order-3 sm:order-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-black/15 bg-white text-ink hover:bg-black/[0.03] transition-colors">
        <?= htmlspecialchars(__('cookie.settings'), ENT_QUOTES, 'UTF-8') ?>
      </button>
      <button type="button" id="lh-cookie-accept-all" class="order-1 sm:order-3 sm:ml-auto px-5 py-2.5 rounded-xl text-sm font-semibold bg-cta text-white shadow-md shadow-black/10 hover:brightness-110 transition-all">
        <?= htmlspecialchars(__('cookie.accept'), ENT_QUOTES, 'UTF-8') ?>
      </button>
    </div>
  </div>
</div>

<div id="lh-cookie-modal-backdrop" class="hidden fixed inset-0 z-[10050] bg-ink/50 backdrop-blur-sm" aria-hidden="true"></div>
<div
  id="lh-cookie-modal"
  class="hidden fixed inset-x-4 top-[8vh] sm:inset-auto sm:left-1/2 sm:top-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 z-[10060] max-w-lg w-auto sm:w-full max-h-[85vh] overflow-y-auto rounded-2xl border border-black/10 bg-white shadow-2xl text-ink p-6 sm:p-8"
  role="dialog"
  aria-modal="true"
  aria-labelledby="lh-cookie-modal-title"
>
  <h2 id="lh-cookie-modal-title" class="text-lg font-bold text-ink"><?= htmlspecialchars(__('cookie.modal_title'), ENT_QUOTES, 'UTF-8') ?></h2>
  <p class="mt-2 text-sm text-blue-grey leading-relaxed">
    <?= htmlspecialchars(__('cookie.modal_intro'), ENT_QUOTES, 'UTF-8') ?>
  </p>
  <ul class="mt-6 space-y-5">
    <li class="rounded-xl border border-black/[0.08] bg-black/[0.02] p-4">
      <div class="flex items-start justify-between gap-3">
        <div>
          <span class="font-semibold text-ink text-sm"><?= htmlspecialchars(__('cookie.category_necessary'), ENT_QUOTES, 'UTF-8') ?></span>
          <p class="text-xs text-blue-grey mt-1 leading-relaxed"><?= htmlspecialchars(__('cookie.category_necessary_desc'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <span class="text-xs font-semibold text-blue-grey shrink-0"><?= htmlspecialchars(__('cookie.category_active'), ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    </li>
    <li class="rounded-xl border border-black/[0.08] p-4">
      <div class="flex items-start justify-between gap-3">
        <div>
          <label for="lh-cookie-toggle-analytics" class="font-semibold text-ink text-sm cursor-pointer"><?= htmlspecialchars(__('cookie.category_analytics'), ENT_QUOTES, 'UTF-8') ?></label>
          <p class="text-xs text-blue-grey mt-1 leading-relaxed"><?= htmlspecialchars(__('cookie.category_analytics_desc'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <input type="checkbox" id="lh-cookie-toggle-analytics" class="mt-1 h-4 w-4 rounded border-black/20 text-cta focus:ring-cta" />
      </div>
    </li>
    <li class="rounded-xl border border-black/[0.08] p-4">
      <div class="flex items-start justify-between gap-3">
        <div>
          <label for="lh-cookie-toggle-marketing" class="font-semibold text-ink text-sm cursor-pointer"><?= htmlspecialchars(__('cookie.category_marketing'), ENT_QUOTES, 'UTF-8') ?></label>
          <p class="text-xs text-blue-grey mt-1 leading-relaxed"><?= htmlspecialchars(__('cookie.category_marketing_desc'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <input type="checkbox" id="lh-cookie-toggle-marketing" class="mt-1 h-4 w-4 rounded border-black/20 text-cta focus:ring-cta" />
      </div>
    </li>
  </ul>
  <div class="mt-6 flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
    <button type="button" id="lh-cookie-modal-cancel" class="px-4 py-2.5 rounded-xl text-sm font-semibold border border-black/15 text-ink hover:bg-black/[0.03]">
      <?= htmlspecialchars(__('cookie.modal_cancel'), ENT_QUOTES, 'UTF-8') ?>
    </button>
    <button type="button" id="lh-cookie-modal-save" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-cta text-white hover:brightness-110">
      <?= htmlspecialchars(__('cookie.modal_save'), ENT_QUOTES, 'UTF-8') ?>
    </button>
  </div>
</div>

<script src="<?= htmlspecialchars(lh_public_url('assets/js/cookie-consent.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
