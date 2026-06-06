<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$checkoutId = trim((string) ($_GET['checkoutId'] ?? ''));
$orderId = trim((string) ($_GET['orderId'] ?? ''));

$pageTitle = __('payment.failed.title');
$pageDescription = __('payment.failed.description');
$canonicalUrl = lh_absolute_locale_url('booking-payment-failed.php');
$robotsMeta = 'noindex, nofollow';

include __DIR__ . '/includes/header.php';
?>

<div class="max-w-2xl mx-auto px-4 sm:px-6 py-14 md:py-20">
  <div class="bg-white border border-black/10 rounded-2xl p-8 sm:p-10 shadow-sm">
    <div class="flex items-start gap-4 mb-6">
      <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-700">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </span>
      <div>
        <h1 class="text-2xl font-black text-ink tracking-tight"><?= htmlspecialchars(__('payment.failed.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-sm text-blue-grey mt-2"><?= htmlspecialchars(__('payment.failed.subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </div>

    <?php if ($checkoutId !== '' || $orderId !== ''): ?>
    <p class="text-xs text-blue-grey mb-6 font-mono break-all">
      <?php if ($orderId !== ''): ?>orderId: <?= htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
      <?php if ($checkoutId !== ''): ?><?= $orderId !== '' ? ' · ' : '' ?>checkoutId: <?= htmlspecialchars($checkoutId, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
    </p>
    <?php endif; ?>

    <div class="flex flex-col sm:flex-row gap-3">
      <a href="javascript:history.back()" class="inline-flex justify-center items-center px-5 py-3 rounded-xl font-bold bg-cta text-white hover:brightness-110 transition-all"><?= htmlspecialchars(__('payment.failed.retry'), ENT_QUOTES, 'UTF-8') ?></a>
      <a href="<?= htmlspecialchars(lh_locale_url('contact.php'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex justify-center items-center px-5 py-3 rounded-xl font-bold border-2 border-black/10 text-ink hover:bg-brand-50 transition-colors"><?= htmlspecialchars(__('payment.failed.contact'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
