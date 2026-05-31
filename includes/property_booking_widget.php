<?php
/**
 * Booking form card for property-details.php (desktop slot + mobile sheet via JS reparent).
 * Expects $property (array) with price and optional pricing columns.
 */
if (!isset($property) || !is_array($property)) {
    return;
}

$lh_std = (float) ($property['price'] ?? 0);
$lh_gi = isset($property['guests_included']) ? (int) $property['guests_included'] : 0;
$lh_eg = isset($property['extra_guest_price']) ? (float) $property['extra_guest_price'] : 0.0;
$lh_min_stay = max(1, (int) ($property['min_stay'] ?? 1));
if ($lh_min_stay === 1) {
    $lh_date_hint_rest = 'Minim 1 noapte (check-out în ziua următoare față de check-in sau mai târziu). Pentru sejururi integral într-o perioadă cu tarif special, minimul poate fi altul.';
} else {
    $lh_date_hint_rest = 'Minim de bază ' . $lh_min_stay . ' nopți; pentru sejururi integral într-o perioadă cu tarif special, minimul poate fi altul dacă e setat acolo.';
}
?>
<div id="lh-booking-widget" class="bg-white border border-black/10 rounded-2xl p-6 sm:p-8">

<div class="hidden" aria-hidden="true">
<label for="bookingCompany">Company</label>
<input type="text" id="bookingCompany" tabindex="-1" autocomplete="off" value="">
</div>

<div class="mb-6 text-ink space-y-1">
  <div class="flex flex-nowrap items-baseline gap-x-1 min-w-0 whitespace-nowrap text-3xl font-black tabular-nums">
    <span class="text-lg font-extrabold text-blue-grey shrink-0">De la </span><?= htmlspecialchars(lh_format_money($lh_std, 0), ENT_QUOTES, 'UTF-8') ?>
    <span class="text-sm text-blue-grey font-bold shrink-0">/ noapte</span>
  </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-2">
  <div class="min-w-0">
    <div id="lh-booking-checkin-label" class="block text-xs font-semibold text-blue-grey uppercase tracking-wide mb-1">Check-in</div>
    <input
      type="text"
      id="booking-check-in"
      autocomplete="off"
      placeholder="Data"
      readonly
      class="w-full bg-surface border border-black/10 rounded-xl p-3 text-ink placeholder:text-blue-grey focus:outline-none focus:ring-2 focus:ring-cta/20 focus:border-cta cursor-pointer"
    >
  </div>
  <div class="min-w-0">
    <div id="lh-booking-checkout-label" class="block text-xs font-semibold text-blue-grey uppercase tracking-wide mb-1">Check-out</div>
    <input
      type="text"
      id="booking-check-out"
      autocomplete="off"
      placeholder="Data"
      readonly
      class="w-full bg-surface border border-black/10 rounded-xl p-3 text-ink placeholder:text-blue-grey focus:outline-none focus:ring-2 focus:ring-cta/20 focus:border-cta cursor-pointer"
    >
  </div>
</div>

<p id="lh-date-range-hint" class="text-xs text-blue-grey mb-4 leading-snug">Alege mai întâi check-in, apoi check-out. <?= htmlspecialchars($lh_date_hint_rest, ENT_QUOTES, 'UTF-8') ?></p>

<select id="guests" class="w-full mb-2 bg-surface border border-black/10 rounded-xl p-3 text-ink focus:outline-none focus:ring-2 focus:ring-cta/20 focus:border-cta">
<option value="1">1 persoană</option>
<option value="2">2 persoane</option>
<option value="3">3 persoane</option>
<option value="4">4 persoane</option>
<option value="5">5 persoane</option>
<option value="6">6+ persoane</option>
</select>

<label for="booking-coupon-code" class="block text-xs font-semibold text-blue-grey uppercase tracking-wide mb-1">Cod cupon (opțional)</label>
<input type="text" id="booking-coupon-code" name="booking_coupon_code" autocomplete="off" spellcheck="false" placeholder="ex. VARA2026" class="w-full mb-1 bg-surface border border-black/10 rounded-xl p-3 text-ink placeholder:text-blue-grey focus:outline-none focus:ring-2 focus:ring-cta/20 focus:border-cta uppercase">
<p id="lh-coupon-hint" class="hidden text-xs mb-3 leading-snug" role="status" aria-live="polite"></p>

<p id="lh-extra-guest-notice" class="hidden text-xs text-amber-900 font-medium mb-4 leading-snug" role="status" aria-live="polite"></p>

<div id="totalBox" class="hidden mb-4 bg-brand-100 border border-black/10 rounded-xl p-4 text-sm text-ink/85">
<div id="lh-total-breakdown" class="flex flex-col gap-2 mb-3 pb-3 border-b border-black/10">
<p class="text-xs font-semibold text-blue-grey uppercase tracking-wide m-0">Subtotal</p>
<div id="lh-total-base-line" class="font-medium text-ink tabular-nums leading-snug text-sm"></div>
<div id="lh-total-discount-line" class="hidden font-medium text-emerald-800 tabular-nums leading-snug text-sm"></div>
<div id="lh-total-coupon-line" class="hidden font-medium text-emerald-800 tabular-nums leading-snug text-sm"></div>
<div id="lh-total-extra-line" class="hidden font-medium text-ink tabular-nums leading-snug text-sm"></div>
<p id="lh-total-extra-guest-note" class="hidden text-[10px] text-blue-grey font-medium leading-snug m-0" role="status" aria-live="polite"></p>
</div>
<div class="lh-total-pricing-row">
<span class="text-blue-grey lh-total-pricing-label">Total de plată:</span>
<span id="totalPrice" class="font-bold text-cta lh-total-pricing-value lh-total-pricing-value--total tabular-nums"></span>
</div>
</div>

<input id="guestName" type="text" placeholder="Nume complet" class="w-full mb-3 bg-surface border border-black/10 rounded-xl p-3 text-ink placeholder:text-blue-grey focus:outline-none focus:ring-2 focus:ring-cta/20 focus:border-cta">

<input id="guestPhone" type="tel" placeholder="Număr telefon" class="w-full mb-3 bg-surface border border-black/10 rounded-xl p-3 text-ink placeholder:text-blue-grey focus:outline-none focus:ring-2 focus:ring-cta/20 focus:border-cta">

<input id="guestEmail" type="email" placeholder="Email" class="w-full mb-4 bg-surface border border-black/10 rounded-xl p-3 text-ink placeholder:text-blue-grey focus:outline-none focus:ring-2 focus:ring-cta/20 focus:border-cta">

<button type="button" id="reserveBtn" class="w-full inline-flex items-center justify-center gap-2 bg-cta hover:brightness-110 text-white py-4 rounded-xl font-bold transition-all disabled:opacity-70 disabled:pointer-events-none">
<span id="reserveBtnLabel">Rezervă acum</span>
<span id="reserveBtnSpinner" class="hidden inline-flex"><svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg></span>
</button>

<div id="availabilityMsg" class="text-xs text-center mt-3 text-blue-grey min-h-[1.25rem]"></div>

<p class="mt-6 pt-5 border-t border-black/8 text-sm font-medium text-ink/70 flex items-start gap-2.5 leading-snug">
<i data-lucide="badge-check" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" aria-hidden="true"></i>
<span>Rezervare directă — fără comisioane ascunse</span>
</p>

</div>
