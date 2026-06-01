<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/page_i18n.php';

$faqMeta = lh_page_meta('faq');
$pageTitle = $faqMeta['title'];
$pageDescription = $faqMeta['description'];
$canonicalUrl = lh_absolute_locale_url('faq.php');

$faqItems = lh_page_faq_items();

$faqEntities = [];
foreach ($faqItems as $item) {
    $faqEntities[] = [
        '@type' => 'Question',
        'name' => $item['q'],
        'acceptedAnswer' => [
            '@type' => 'Answer',
            'text' => $item['a'],
        ],
    ];
}
$lhJsonLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'inLanguage' => match (lh_current_locale()) {
        'en' => 'en-US',
        'ru' => 'ru-RU',
        default => 'ro-MD',
    },
    'mainEntity' => $faqEntities,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="border-b border-black/[0.06] bg-white/60">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 pt-10 md:pt-14 pb-6 md:pb-8">
    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-grey mb-3"><?= htmlspecialchars($faqMeta['label'], ENT_QUOTES, 'UTF-8') ?></p>
    <h1 class="text-3xl md:text-4xl font-bold text-ink tracking-tight"><?= htmlspecialchars($faqMeta['heading'], ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="mt-4 text-base text-blue-grey leading-relaxed max-w-xl">
      <?= htmlspecialchars(__('page.faq.intro'), ENT_QUOTES, 'UTF-8') ?>
      <a href="<?= htmlspecialchars(lh_locale_url('contact.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-ink font-semibold underline decoration-black/20 underline-offset-4 hover:decoration-ink"><?= htmlspecialchars(__('page.faq.intro_contact'), ENT_QUOTES, 'UTF-8') ?></a>.
    </p>
  </div>
</div>

<div class="max-w-4xl mx-auto px-4 sm:px-6 mt-6 md:mt-10 pt-0 pb-0">
  <div class="space-y-3">
    <?php foreach ($faqItems as $item): ?>
      <details class="group rounded-2xl border border-black/[0.08] bg-white/80 shadow-sm shadow-black/[0.03] open:shadow-md open:border-black/12 transition-shadow">
        <summary class="cursor-pointer list-none flex items-center justify-between gap-4 px-5 py-4 md:px-6 md:py-5 text-left font-semibold text-ink text-[15px] md:text-base [&::-webkit-details-marker]:hidden">
          <span><?= htmlspecialchars($item['q'], ENT_QUOTES, 'UTF-8') ?></span>
          <span class="shrink-0 w-8 h-8 rounded-full bg-black/[0.04] flex items-center justify-center text-ink/60 group-open:rotate-180 transition-transform" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </span>
        </summary>
        <div class="px-5 pb-5 md:px-6 md:pb-6 pt-0 text-sm md:text-[15px] text-blue-grey leading-relaxed border-t border-black/[0.06]">
          <p class="pt-4"><?= htmlspecialchars($item['a'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
