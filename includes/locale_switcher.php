<?php

declare(strict_types=1);

$lhLangItems = lh_locale_switcher_items();
if ($lhLangItems === []) {
    return;
}

$lhLangCurrent = null;
foreach ($lhLangItems as $lhLangItem) {
    if ($lhLangItem['current']) {
        $lhLangCurrent = $lhLangItem;
        break;
    }
}
if ($lhLangCurrent === null) {
    $lhLangCurrent = $lhLangItems[0];
}
?>
<div class="lh-lang-switcher relative shrink-0 pointer-events-auto" data-lh-lang-switcher>
  <button
    type="button"
    class="inline-flex items-center justify-center gap-1.5 h-10 px-2.5 sm:px-3 rounded-xl border border-white/30 text-white hover:bg-white/10 transition-all"
    aria-label="<?= htmlspecialchars(__('lang.switch'), ENT_QUOTES, 'UTF-8') ?>"
    aria-haspopup="listbox"
    aria-expanded="false"
    data-lh-lang-trigger
  >
    <svg class="w-4 h-4 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 21a9 9 0 100-18 9 9 0 000 18z"/>
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3.6 9h16.8M3.6 15h16.8M12 3c2.2 2.4 3.4 5.6 3.4 9s-1.2 6.6-3.4 9M12 3c-2.2 2.4-3.4 5.6-3.4 9s1.2 6.6 3.4 9"/>
    </svg>
    <span class="text-base leading-none select-none" aria-hidden="true"><?= $lhLangCurrent['flag'] ?></span>
    <svg class="w-3.5 h-3.5 shrink-0 opacity-70 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" data-lh-lang-chevron>
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
    </svg>
  </button>
  <div
    class="lh-lang-switcher__menu hidden absolute right-0 top-full mt-1.5 min-w-[10.5rem] py-1 rounded-xl bg-white/95 text-ink premium-header-blur shadow-lg shadow-black/15 ring-1 ring-black/8 z-[70]"
    role="listbox"
    aria-label="<?= htmlspecialchars(__('lang.switch'), ENT_QUOTES, 'UTF-8') ?>"
    data-lh-lang-menu
  >
<?php foreach ($lhLangItems as $lhLangItem):
    $lhMenuClass = $lhLangItem['current']
        ? 'bg-black/[0.04] font-semibold text-ink'
        : 'text-ink/75 hover:bg-black/[0.04] hover:text-ink';
?>
    <a
      href="<?= htmlspecialchars($lhLangItem['href'], ENT_QUOTES, 'UTF-8') ?>"
      class="flex items-center gap-2.5 px-3 py-2 text-sm transition-colors <?= $lhMenuClass ?>"
      role="option"
      hreflang="<?= htmlspecialchars(lh_locale_hreflang($lhLangItem['locale']), ENT_QUOTES, 'UTF-8') ?>"
      lang="<?= htmlspecialchars($lhLangItem['locale'], ENT_QUOTES, 'UTF-8') ?>"
      <?= $lhLangItem['current'] ? 'aria-current="true"' : '' ?>
    >
      <span class="text-base leading-none shrink-0" aria-hidden="true"><?= $lhLangItem['flag'] ?></span>
      <span class="flex-1 min-w-0 truncate"><?= htmlspecialchars($lhLangItem['name'], ENT_QUOTES, 'UTF-8') ?></span>
<?php if ($lhLangItem['current']): ?>
      <svg class="w-4 h-4 shrink-0 text-cta" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
      </svg>
<?php endif; ?>
    </a>
<?php endforeach; ?>
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-lh-lang-switcher]').forEach(function (root) {
      var trigger = root.querySelector('[data-lh-lang-trigger]');
      var menu = root.querySelector('[data-lh-lang-menu]');
      var chevron = root.querySelector('[data-lh-lang-chevron]');
      if (!trigger || !menu) return;

      function openMenu() {
        menu.classList.remove('hidden');
        menu.classList.add('lh-lang-switcher__menu--open');
        trigger.setAttribute('aria-expanded', 'true');
        if (chevron) chevron.classList.add('rotate-180');
      }

      function closeMenu() {
        menu.classList.add('hidden');
        menu.classList.remove('lh-lang-switcher__menu--open');
        trigger.setAttribute('aria-expanded', 'false');
        if (chevron) chevron.classList.remove('rotate-180');
      }

      function isOpen() {
        return !menu.classList.contains('hidden');
      }

      trigger.addEventListener('click', function (event) {
        event.stopPropagation();
        if (isOpen()) {
          closeMenu();
        } else {
          openMenu();
        }
      });

      document.addEventListener('click', function (event) {
        if (!root.contains(event.target)) closeMenu();
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && isOpen()) {
          closeMenu();
          trigger.focus();
        }
      });
    });
  });
</script>
