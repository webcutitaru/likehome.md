<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/page_i18n.php';
require_once __DIR__ . '/includes/site_nav.php';

$terms = lh_page_sections('terms');
$sections = is_array($terms['sections'] ?? null) ? $terms['sections'] : [];
$pageTitle = __('page.terms.title');
$pageDescription = __('page.terms.description');
$canonicalUrl = lh_absolute_locale_url('terms.php');
$robotsMeta = 'index, follow, max-snippet:-1';
$contactEmail = lh_site_contact_email();
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="border-b border-black/[0.06] bg-white/60">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 pt-10 md:pt-14 pb-6 md:pb-8">
    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-grey mb-3"><?= htmlspecialchars((string) ($terms['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <h1 class="text-3xl md:text-4xl font-bold text-ink tracking-tight"><?= htmlspecialchars(__('page.terms.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="mt-4 text-sm text-blue-grey">
      <?= htmlspecialchars(__('page.terms.updated'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars(date('d.m.Y'), ENT_QUOTES, 'UTF-8') ?>.
      <?= htmlspecialchars((string) ($terms['updated_note'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
    </p>
  </div>
</div>

<div class="max-w-4xl mx-auto px-4 sm:px-6 mt-6 md:mt-10 pt-0 pb-0">
  <div class="prose prose-neutral max-w-none space-y-10 text-blue-grey leading-relaxed [&_h2]:text-ink [&_h2]:text-xl [&_h2]:font-bold [&_h2]:mt-0 [&_h2]:mb-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-2">
<?php if (!empty($terms['intro'])): ?>
    <div class="text-blue-grey leading-relaxed"><?= lh_terms_replace_placeholders((string) $terms['intro']) ?></div>
<?php endif; ?>
<?php foreach ($sections as $section):
    if (!is_array($section)) {
        continue;
    }
    $body = lh_terms_replace_placeholders((string) ($section['body'] ?? ''));
?>
    <section>
      <h2><?= htmlspecialchars((string) ($section['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
      <?= $body ?>
    </section>
<?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
