<?php
/**
 * Booking form card for property-details.php (desktop slot + mobile sheet via JS reparent).
 * Expects $property (array) with price and optional pricing columns.
 */
require_once __DIR__ . '/booking_payment.php';

if (!isset($property) || !is_array($property)) {
    return;
}

$lh_std = (float) ($property['price'] ?? 0);
$lh_min_stay = max(1, (int) ($property['min_stay'] ?? 1));
$lh_online_discount_pct = (int) lh_booking_online_discount_percent();
$lh_date_hint_rest = $lh_min_stay === 1
    ? __('booking.date_hint_min1')
    : __('booking.date_hint_min_n', ['n' => (string) $lh_min_stay]);
?>
<div id="lh-booking-widget" class="bg-white border border-black/10 rounded-2xl p-6 sm:p-8">

<div class="hidden" aria-hidden="true">
<label for="bookingCompany" class="sr-only"><?= htmlspecialchars(__('booking.honeypot_company'), ENT_QUOTES, 'UTF-8') ?></label>
<input type="text" id="bookingCompany" tabindex="-1" autocomplete="off" value="">
</div>

<div class="mb-6 text-ink space-y-1">
  <div class="flex flex-nowrap items-baseline gap-x-1 min-w-0 whitespace-nowrap text-3xl font-black tabular-nums">
    <span class="text-lg font-extrabold text-blue-grey shrink-0"><?= htmlspecialchars(__('booking.from'), ENT_QUOTES, 'UTF-8') ?> </span><?= htmlspecialchars(lh_format_money($lh_std, 0), ENT_QUOTES, 'UTF-8') ?>
    <span class="text-sm text-blue-grey font-bold shrink-0"><?= htmlspecialchars(__('booking.per_night'), ENT_QUOTES, 'UTF-8') ?></span>
  </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-2">
  <div class="min-w-0">
    <div id="lh-booking-checkin-label" class="block text-xs font-semibold text-blue-grey uppercase tracking-wide mb-1"><?= htmlspecialchars(__('search.check_in'), ENT_QUOTES, 'UTF-8') ?></div>
    <input
      type="text"
      id="booking-check-in"
      autocomplete="off"
      placeholder="<?= htmlspecialchars(__('booking.date'), ENT_QUOTES, 'UTF-8') ?>"
      readonly
      class="w-full bg-surface border border-black/10 rounded-xl p-3 text-ink placeholder:text-blue-grey focus:outline-none focus:ring-2 focus:ring-cta/20 focus:border-cta cursor-pointer"
    >
  </div>
  <div class="min-w-0">
    <div id="lh-booking-checkout-label" class="block text-xs font-semibold text-blue-grey uppercase tracking-wide mb-1"><?= htmlspecialchars(__('search.check_out'), ENT_QUOTES, 'UTF-8') ?></div>
    <input
      type="text"
      id="booking-check-out"
      autocomplete="off"
      placeholder="<?= htmlspecialchars(__('booking.date'), ENT_QUOTES, 'UTF-8') ?>"
      readonly
      class="w-full bg-surface border border-black/10 rounded-xl p-3 text-ink placeholder:text-blue-grey focus:outline-none focus:ring-2 focus:ring-cta/20 focus:border-cta cursor-pointer"
    >
  </div>
</div>

<p id="lh-date-range-hint" class="text-xs text-blue-grey mb-4 leading-snug"><?= htmlspecialchars(__('booking.date_hint_intro'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($lh_date_hint_rest, ENT_QUOTES, 'UTF-8') ?></p>

<select id="guests" class="w-full mb-2 bg-surface border border-black/10 rounded-xl p-3 text-ink focus:outline-none focus:ring-2 focus:ring-cta/20 focus:border-cta">
<?php for ($g = 1; $g <= 5; $g++): ?>
  <option value="<?= $g ?>"><?= htmlspecialchars($g === 1 ? __('booking.guest_one') : __('booking.guest_many', ['n' => (string) $g]), ENT_QUOTES, 'UTF-8') ?></option>
<?php endfor; ?>
  <option value="6"><?= htmlspecialchars(__('booking.guest_six_plus'), ENT_QUOTES, 'UTF-8') ?></option>
</select>

<label for="booking-coupon-code" class="block text-xs font-semibold text-blue-grey uppercase tracking-wide mb-1"><?= htmlspecialchars(__('booking.coupon_optional'), ENT_QUOTES, 'UTF-8') ?></label>
<input type="text" id="booking-coupon-code" name="booking_coupon_code" autocomplete="off" spellcheck="false" placeholder="<?= htmlspecialchars(__('booking.coupon_placeholder'), ENT_QUOTES, 'UTF-8') ?>" class="w-full mb-1 bg-surface border border-black/10 rounded-xl p-3 text-ink placeholder:text-blue-grey focus:outline-none focus:ring-2 focus:ring-cta/20 focus:border-cta uppercase">
<p id="lh-coupon-hint" class="hidden text-xs mb-3 leading-snug" role="status" aria-live="polite"></p>

<p id="lh-extra-guest-notice" class="hidden text-xs text-amber-900 font-medium mb-4 leading-snug" role="status" aria-live="polite"></p>

<div id="totalBox" class="hidden mb-4 bg-brand-100 border border-black/10 rounded-xl p-4 text-sm text-ink/85">
<div id="lh-total-breakdown" class="flex flex-col gap-2 mb-3 pb-3 border-b border-black/10">
<p class="text-xs font-semibold text-blue-grey uppercase tracking-wide m-0"><?= htmlspecialchars(__('booking.subtotal'), ENT_QUOTES, 'UTF-8') ?></p>
<div id="lh-total-base-line" class="font-medium text-ink tabular-nums leading-snug text-sm"></div>
<div id="lh-total-discount-line" class="hidden font-medium text-emerald-800 tabular-nums leading-snug text-sm"></div>
<div id="lh-total-coupon-line" class="hidden font-medium text-emerald-800 tabular-nums leading-snug text-sm"></div>
<div id="lh-total-extra-line" class="hidden font-medium text-ink tabular-nums leading-snug text-sm"></div>
<p id="lh-total-extra-guest-note" class="hidden text-[10px] text-blue-grey font-medium leading-snug m-0" role="status" aria-live="polite"></p>
<div id="lh-total-online-discount-line" class="hidden font-medium text-emerald-800 tabular-nums leading-snug text-sm"></div>
</div>
<div class="lh-total-pricing-row">
<span id="lh-total-pay-label" class="text-blue-grey lh-total-pricing-label"><?= htmlspecialchars(__('booking.total_pay'), ENT_QUOTES, 'UTF-8') ?></span>
<span id="totalPrice" class="font-bold text-cta lh-total-pricing-value lh-total-pricing-value--total tabular-nums"></span>
</div>
</div>

<fieldset class="mb-4 space-y-2 border-0 p-0 m-0">
<legend class="block text-xs font-semibold text-blue-grey uppercase tracking-wide mb-2"><?= htmlspecialchars(__('booking.payment_method_label'), ENT_QUOTES, 'UTF-8') ?></legend>
<label class="flex items-start gap-3 p-3 rounded-xl border border-black/10 bg-surface cursor-pointer has-[:checked]:border-cta has-[:checked]:ring-2 has-[:checked]:ring-cta/20">
  <input type="radio" name="booking_payment_method" value="on_site" class="mt-1 accent-cta" checked>
  <span class="min-w-0">
    <span class="block text-sm font-bold text-ink"><?= htmlspecialchars(__('booking.payment_on_site'), ENT_QUOTES, 'UTF-8') ?></span>
    <span class="block text-xs text-blue-grey mt-0.5"><?= htmlspecialchars(__('booking.payment_on_site_hint'), ENT_QUOTES, 'UTF-8') ?></span>
    <span id="lh-pay-on-site-amount" class="block text-sm font-bold text-ink tabular-nums mt-1"></span>
  </span>
</label>
<label class="flex items-start gap-3 p-3 rounded-xl border border-black/10 bg-surface cursor-pointer has-[:checked]:border-cta has-[:checked]:ring-2 has-[:checked]:ring-cta/20">
  <input type="radio" name="booking_payment_method" value="online" class="mt-1 accent-cta">
  <span class="min-w-0">
    <span class="block text-sm font-bold text-ink"><?= htmlspecialchars(__('booking.payment_online'), ENT_QUOTES, 'UTF-8') ?><?php if ($lh_online_discount_pct > 0): ?> <span class="text-emerald-700">(−<?= (int) $lh_online_discount_pct ?>%)</span><?php endif; ?></span>
    <span class="block text-xs text-blue-grey mt-0.5"><?= htmlspecialchars(__('booking.payment_online_hint'), ENT_QUOTES, 'UTF-8') ?></span>
    <span id="lh-pay-online-amount" class="block text-sm font-bold text-cta tabular-nums mt-1"></span>
  </span>
</label>
</fieldset>

<label class="flex items-start gap-2.5 mb-4 text-sm text-ink/85 cursor-pointer">
  <input type="checkbox" id="bookingTermsAccepted" class="mt-1 accent-cta shrink-0">
  <span><?= __('booking.terms_accept_html', ['terms_url' => lh_locale_url('terms.php')]) ?></span>
</label>

<input id="guestName" type="text" placeholder="<?= htmlspecialchars(__('booking.name_placeholder'), ENT_QUOTES, 'UTF-8') ?>" class="w-full mb-3 bg-surface border border-black/10 rounded-xl p-3 text-ink placeholder:text-blue-grey focus:outline-none focus:ring-2 focus:ring-cta/20 focus:border-cta">

<input id="guestPhone" type="tel" placeholder="<?= htmlspecialchars(__('booking.phone_placeholder'), ENT_QUOTES, 'UTF-8') ?>" class="w-full mb-3 bg-surface border border-black/10 rounded-xl p-3 text-ink placeholder:text-blue-grey focus:outline-none focus:ring-2 focus:ring-cta/20 focus:border-cta">

<input id="guestEmail" type="email" placeholder="<?= htmlspecialchars(__('booking.email_placeholder'), ENT_QUOTES, 'UTF-8') ?>" class="w-full mb-4 bg-surface border border-black/10 rounded-xl p-3 text-ink placeholder:text-blue-grey focus:outline-none focus:ring-2 focus:ring-cta/20 focus:border-cta">

<button type="button" id="reserveBtn" class="w-full inline-flex items-center justify-center gap-2 bg-cta hover:brightness-110 text-white py-4 rounded-xl font-bold transition-all disabled:opacity-70 disabled:pointer-events-none">
<span id="reserveBtnLabel"><?= htmlspecialchars(__('booking.book_now'), ENT_QUOTES, 'UTF-8') ?></span>
<span id="reserveBtnSpinner" class="hidden inline-flex"><svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg></span>
</button>

<div id="availabilityMsg" class="text-xs text-center mt-3 text-blue-grey min-h-[1.25rem]"></div>

<p class="mt-6 pt-5 border-t border-black/8 text-sm font-medium text-ink/70 flex items-start gap-2.5 leading-snug">
<i data-lucide="badge-check" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" aria-hidden="true"></i>
<span><?= htmlspecialchars(__('booking.direct_booking'), ENT_QUOTES, 'UTF-8') ?></span>
</p>

</div>
