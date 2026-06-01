<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/page_i18n.php';

$aboutMeta = lh_page_meta('about');
$about = lh_page_sections('about');
$pageTitle = $aboutMeta['title'];
$pageDescription = $aboutMeta['description'];
$canonicalUrl = lh_absolute_locale_url('about.php');
$premiumItems = is_array($about['premium_items'] ?? null) ? $about['premium_items'] : [];
$ownersItems = is_array($about['owners_items'] ?? null) ? $about['owners_items'] : [];
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="border-b border-black/[0.06] bg-white/60">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 pt-10 md:pt-14 pb-6 md:pb-8">
    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-grey mb-3"><?= htmlspecialchars($aboutMeta['label'], ENT_QUOTES, 'UTF-8') ?></p>
    <h1 class="text-3xl md:text-4xl font-bold text-ink tracking-tight"><?= htmlspecialchars($aboutMeta['heading'], ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="mt-4 text-lg text-ink/80 leading-relaxed max-w-2xl"><?= htmlspecialchars((string) ($about['intro'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
  </div>
</div>

<div class="max-w-4xl mx-auto px-4 sm:px-6 mt-6 md:mt-10 pt-0 pb-0 space-y-16">
  <section class="grid md:grid-cols-2 gap-10 md:gap-14 items-start">
    <div>
      <h2 class="text-xl font-bold text-ink mb-4"><?= htmlspecialchars((string) ($about['mission_title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
      <p class="text-blue-grey leading-relaxed"><?= htmlspecialchars((string) ($about['mission_body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="rounded-3xl bg-gradient-to-br from-logo/90 to-ink p-8 md:p-10 text-white shadow-xl shadow-black/15">
      <h2 class="text-lg font-bold mb-3"><?= htmlspecialchars((string) ($about['premium_title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
      <ul class="space-y-3 text-sm text-white/85 leading-relaxed">
<?php foreach ($premiumItems as $item): ?>
        <li class="flex gap-3"><span class="text-white/50">·</span> <?= htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8') ?></li>
<?php endforeach; ?>
      </ul>
    </div>
  </section>

  <section class="rounded-3xl border border-black/[0.08] bg-white/90 p-8 md:p-10 shadow-sm shadow-black/[0.04]">
    <h2 class="text-xl font-bold text-ink mb-6"><?= htmlspecialchars((string) ($about['trust_title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
    <div class="grid sm:grid-cols-3 gap-8 text-center sm:text-left">
      <div>
        <div class="text-2xl font-bold text-ink mb-1"><?= htmlspecialchars((string) ($about['stat1_num'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="text-sm text-blue-grey leading-snug"><?= htmlspecialchars((string) ($about['stat1_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div>
        <div class="text-2xl font-bold text-ink mb-1"><?= htmlspecialchars((string) ($about['stat2_num'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="text-sm text-blue-grey leading-snug"><?= htmlspecialchars((string) ($about['stat2_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div>
        <div class="text-2xl font-bold text-ink mb-1"><?= htmlspecialchars((string) ($about['stat3_num'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="text-sm text-blue-grey leading-snug"><?= htmlspecialchars((string) ($about['stat3_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>
  </section>

  <section class="rounded-3xl border border-black/[0.08] bg-white/90 p-8 md:p-10 shadow-sm shadow-black/[0.04]">
    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-grey mb-3"><?= htmlspecialchars((string) ($about['owners_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <h2 class="text-xl font-bold text-ink mb-4"><?= htmlspecialchars((string) ($about['owners_title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
    <p class="text-blue-grey leading-relaxed max-w-2xl mb-5"><?= htmlspecialchars((string) ($about['owners_body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <ul class="space-y-3 text-sm text-blue-grey leading-relaxed max-w-2xl mb-8">
<?php foreach ($ownersItems as $item): ?>
      <li class="flex gap-3"><span class="text-ink/35 shrink-0">·</span> <?= htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8') ?></li>
<?php endforeach; ?>
    </ul>
    <a href="<?= htmlspecialchars(lh_locale_url('contact.php'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-xl text-sm font-semibold bg-cta text-white shadow-md shadow-black/10 hover:brightness-110 transition-all">
      <?= htmlspecialchars((string) ($about['owners_cta'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
    </a>
  </section>

  <p class="text-center text-sm text-blue-grey">
    <?= htmlspecialchars((string) ($about['footer_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
    <a href="<?= htmlspecialchars(lh_locale_url('contact.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-ink font-semibold underline decoration-black/20 underline-offset-4 hover:decoration-ink"><?= htmlspecialchars((string) ($about['footer_contact'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
    ·
    <a href="<?= htmlspecialchars(lh_locale_url('properties.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-ink font-semibold underline decoration-black/20 underline-offset-4 hover:decoration-ink"><?= htmlspecialchars((string) ($about['footer_properties'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
  </p>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
