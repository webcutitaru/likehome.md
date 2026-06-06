<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/booking_payment.php';
require_once __DIR__ . '/includes/company_legal.php';
require_once __DIR__ . '/includes/seo.php';

$checkoutId = trim((string) ($_GET['checkoutId'] ?? ''));
$orderId = trim((string) ($_GET['orderId'] ?? ''));
$checkoutStatus = trim((string) ($_GET['checkoutStatus'] ?? ''));

$pageTitle = __('payment.success.title');
$pageDescription = __('payment.success.description');
$canonicalUrl = lh_absolute_locale_url('booking-payment-success.php');
$robotsMeta = 'noindex, nofollow';

$booking = null;
$propertyTitle = '';
$pdo = getPDO();

if (preg_match('/^LH-(\d+)$/i', $orderId, $m)) {
    $stmt = $pdo->prepare(
        'SELECT b.*, p.title AS property_title FROM bookings b
         LEFT JOIN properties p ON p.id = b.property_id WHERE b.id = ? LIMIT 1'
    );
    $stmt->execute([(int) $m[1]]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} elseif ($checkoutId !== '') {
    $stmt = $pdo->prepare(
        'SELECT b.*, p.title AS property_title FROM bookings b
         LEFT JOIN properties p ON p.id = b.property_id WHERE b.maib_checkout_id = ? LIMIT 1'
    );
    $stmt->execute([$checkoutId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($booking) {
    $propertyTitle = (string) ($booking['property_title'] ?? '');
}

$paidAtDisplay = '';
if ($booking && !empty($booking['paid_at'])) {
    try {
        $paidAtDisplay = (new DateTimeImmutable((string) $booking['paid_at']))->format('d.m.Y H:i');
    } catch (Throwable) {
        $paidAtDisplay = (string) $booking['paid_at'];
    }
}
$siteOrigin = lh_public_site_origin();

include __DIR__ . '/includes/header.php';
?>

<div class="max-w-2xl mx-auto px-4 sm:px-6 py-14 md:py-20">
  <div class="bg-white border border-black/10 rounded-2xl p-8 sm:p-10 shadow-sm">
    <div class="flex items-start gap-4 mb-6">
      <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
      </span>
      <div>
        <h1 class="text-2xl font-black text-ink tracking-tight"><?= htmlspecialchars(__('payment.success.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-sm text-blue-grey mt-2" id="lh-payment-success-subtitle"><?= htmlspecialchars(__('payment.success.subtitle_pending'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </div>

    <dl id="lh-payment-success-details" class="space-y-3 text-sm mb-8 <?= $booking ? '' : 'hidden' ?>">
      <?php if ($booking): ?>
      <div class="flex justify-between gap-4 border-b border-black/6 pb-2">
        <dt class="text-blue-grey"><?= htmlspecialchars(__('booking.confirm_property'), ENT_QUOTES, 'UTF-8') ?></dt>
        <dd class="font-semibold text-ink text-right"><?= htmlspecialchars($propertyTitle, ENT_QUOTES, 'UTF-8') ?></dd>
      </div>
      <div class="flex justify-between gap-4 border-b border-black/6 pb-2">
        <dt class="text-blue-grey"><?= htmlspecialchars(__('booking.confirm_period'), ENT_QUOTES, 'UTF-8') ?></dt>
        <dd class="font-medium text-ink"><?= htmlspecialchars((string) $booking['check_in'] . ' → ' . (string) $booking['check_out'], ENT_QUOTES, 'UTF-8') ?></dd>
      </div>
      <div class="flex justify-between gap-4 border-b border-black/6 pb-2">
        <dt class="text-blue-grey"><?= htmlspecialchars(__('payment.success.paid_amount'), ENT_QUOTES, 'UTF-8') ?></dt>
        <dd class="font-bold text-cta tabular-nums" id="lh-payment-paid-amount"><?= htmlspecialchars(lh_format_money((float) ($booking['payment_amount'] ?? $booking['payment_due_amount'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></dd>
      </div>
      <?php if (($booking['payment_status'] ?? '') === 'paid' || (float) ($booking['payment_amount'] ?? 0) > 0.004): ?>
      <div class="flex justify-between gap-4 border-b border-black/6 pb-2">
        <dt class="text-blue-grey"><?= htmlspecialchars(__('payment.success.merchant'), ENT_QUOTES, 'UTF-8') ?></dt>
        <dd class="font-medium text-ink text-right"><?= htmlspecialchars(lh_company_legal_name(), ENT_QUOTES, 'UTF-8') ?></dd>
      </div>
      <div class="flex justify-between gap-4 border-b border-black/6 pb-2">
        <dt class="text-blue-grey"><?= htmlspecialchars(__('payment.success.website'), ENT_QUOTES, 'UTF-8') ?></dt>
        <dd class="font-medium text-ink text-right break-all"><?= htmlspecialchars($siteOrigin, ENT_QUOTES, 'UTF-8') ?></dd>
      </div>
      <div class="flex justify-between gap-4 border-b border-black/6 pb-2">
        <dt class="text-blue-grey"><?= htmlspecialchars(__('payment.success.currency'), ENT_QUOTES, 'UTF-8') ?></dt>
        <dd class="font-medium text-ink"><?= htmlspecialchars(lh_company_currency(), ENT_QUOTES, 'UTF-8') ?></dd>
      </div>
      <?php if ($paidAtDisplay !== ''): ?>
      <div class="flex justify-between gap-4 border-b border-black/6 pb-2">
        <dt class="text-blue-grey"><?= htmlspecialchars(__('payment.success.paid_at'), ENT_QUOTES, 'UTF-8') ?></dt>
        <dd class="font-medium text-ink tabular-nums"><?= htmlspecialchars($paidAtDisplay, ENT_QUOTES, 'UTF-8') ?></dd>
      </div>
      <?php endif; ?>
      <?php endif; ?>
      <div class="flex justify-between gap-4 border-b border-black/6 pb-2">
        <dt class="text-blue-grey"><?= htmlspecialchars(__('payment.success.order_no'), ENT_QUOTES, 'UTF-8') ?></dt>
        <dd class="font-medium text-ink">LH-<?= (int) $booking['id'] ?></dd>
      </div>
      <?php if ($checkoutId !== ''): ?>
      <div class="flex justify-between gap-4 pb-2">
        <dt class="text-blue-grey">checkout_id</dt>
        <dd class="font-mono text-xs text-ink break-all"><?= htmlspecialchars($checkoutId, ENT_QUOTES, 'UTF-8') ?></dd>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </dl>

    <div class="flex flex-col sm:flex-row gap-3">
      <a href="<?= htmlspecialchars(lh_locale_url('properties.php'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex justify-center items-center px-5 py-3 rounded-xl font-bold bg-cta text-white hover:brightness-110 transition-all"><?= htmlspecialchars(__('payment.success.back_properties'), ENT_QUOTES, 'UTF-8') ?></a>
      <a href="<?= htmlspecialchars(lh_locale_url('contact.php'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex justify-center items-center px-5 py-3 rounded-xl font-bold border-2 border-black/10 text-ink hover:bg-brand-50 transition-colors"><?= htmlspecialchars(__('payment.success.contact'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
  </div>
</div>

<?php if ($booking && (($booking['status'] ?? '') !== 'confirmed' || ($booking['payment_status'] ?? '') !== 'paid')): ?>
<script>
(function () {
  var checkoutId = <?= json_encode($checkoutId, JSON_UNESCAPED_UNICODE) ?>;
  var orderId = <?= json_encode($orderId, JSON_UNESCAPED_UNICODE) ?>;
  var completeUrl = <?= json_encode(lh_public_url('ajax/complete_online_booking.php'), JSON_UNESCAPED_UNICODE) ?>;
  var csrf = <?= json_encode(lh_csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var confirmedMsg = <?= json_encode(__('payment.success.subtitle_confirmed'), JSON_UNESCAPED_UNICODE) ?>;
  var pendingMsg = <?= json_encode(__('payment.success.subtitle_pending'), JSON_UNESCAPED_UNICODE) ?>;

  function poll(attempt) {
    var body = new URLSearchParams();
    body.set('csrf_token', csrf);
    if (checkoutId) body.set('checkout_id', checkoutId);
    if (orderId) body.set('order_id', orderId);
    fetch(completeUrl, { method: 'POST', headers: { Accept: 'application/json' }, body: body })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var sub = document.getElementById('lh-payment-success-subtitle');
        if (data.success && data.confirmed) {
          if (sub) sub.textContent = confirmedMsg;
          return;
        }
        if (attempt < 8) {
          setTimeout(function () { poll(attempt + 1); }, 1500);
        } else if (sub) {
          sub.textContent = pendingMsg;
        }
      })
      .catch(function () {
        if (attempt < 8) setTimeout(function () { poll(attempt + 1); }, 2000);
      });
  }
  poll(0);
})();
</script>
<?php elseif ($booking && ($booking['payment_status'] ?? '') === 'paid'): ?>
<script>
(function () {
  var sub = document.getElementById('lh-payment-success-subtitle');
  if (sub) sub.textContent = <?= json_encode(__('payment.success.subtitle_confirmed'), JSON_UNESCAPED_UNICODE) ?>;
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
