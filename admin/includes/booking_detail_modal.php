<?php

declare(strict_types=1);
?>
<!-- Modal rezervare (comun listă + calendar) -->
<div id="lhBookingModal" class="fixed inset-0 z-[8000] hidden items-center justify-center bg-slate-900/70 p-4">
    <div class="bg-white max-w-lg w-full max-h-[90vh] overflow-y-auto rounded-2xl shadow-2xl p-8 relative">
        <button type="button" class="lh-booking-modal-close absolute top-4 right-4 text-slate-400 hover:text-slate-900 font-bold text-sm">✕</button>
        <h3 class="text-xl font-black text-slate-900 pr-8">Rezervare <span id="lhBmId" class="text-slate-400 font-bold"></span></h3>

        <dl class="mt-4 space-y-2 text-sm">
            <div class="flex gap-2"><dt class="text-slate-400 font-bold shrink-0 w-24">Client</dt><dd id="lhBmGuest" class="text-slate-800 min-w-0 break-words"></dd></div>
            <div class="flex gap-2"><dt class="text-slate-400 font-bold shrink-0 w-24">Email</dt><dd id="lhBmEmail" class="text-slate-800 break-all min-w-0"></dd></div>
            <div class="flex gap-2"><dt class="text-slate-400 font-bold shrink-0 w-24">Telefon</dt><dd id="lhBmPhone" class="text-slate-800"></dd></div>
            <div class="flex gap-2" id="lhBmPropertyRow" style="display:none"><dt class="text-slate-400 font-bold shrink-0 w-24">Proprietate</dt><dd id="lhBmProperty" class="text-slate-800 min-w-0 break-words"></dd></div>
            <div class="flex gap-2"><dt class="text-slate-400 font-bold shrink-0 w-24">Perioadă</dt><dd id="lhBmRange" class="text-slate-800"></dd></div>
            <div class="flex gap-2"><dt class="text-slate-400 font-bold shrink-0 w-24">Oaspeți</dt><dd id="lhBmGuests" class="text-slate-800"></dd></div>
            <div class="flex gap-2"><dt class="text-slate-400 font-bold shrink-0 w-24">Status</dt><dd id="lhBmStatus" class="text-slate-800"></dd></div>
            <div class="flex gap-2" id="lhBmCouponRow" style="display:none"><dt class="text-slate-400 font-bold shrink-0 w-24">Cupon</dt><dd id="lhBmCouponDetail" class="text-emerald-800 font-semibold min-w-0 break-words"></dd></div>
            <div class="flex gap-2"><dt class="text-slate-400 font-bold shrink-0 w-24">Total</dt><dd id="lhBmTotal" class="text-slate-800 font-bold"></dd></div>
        </dl>

        <div id="lhBmPaymentSection" class="mt-5 pt-5 border-t border-slate-100">
            <h4 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-3">Plată</h4>
            <dl class="space-y-2 text-sm">
                <div class="flex gap-2"><dt class="text-slate-400 font-bold shrink-0 w-24">Metodă</dt><dd id="lhBmPayMethod" class="text-slate-800"></dd></div>
                <div class="flex gap-2"><dt class="text-slate-400 font-bold shrink-0 w-24">Status plată</dt><dd id="lhBmPayStatus" class="text-slate-800"></dd></div>
                <div class="flex gap-2" id="lhBmPaidRow" style="display:none"><dt class="text-slate-400 font-bold shrink-0 w-24">Plătit</dt><dd id="lhBmPaid" class="text-slate-800 tabular-nums"></dd></div>
                <div class="flex gap-2" id="lhBmRefundedRow" style="display:none"><dt class="text-slate-400 font-bold shrink-0 w-24">Rambursat</dt><dd id="lhBmRefunded" class="text-slate-800 tabular-nums"></dd></div>
                <div class="flex gap-2" id="lhBmRestRow" style="display:none"><dt class="text-slate-400 font-bold shrink-0 w-24">Rest</dt><dd id="lhBmRest" class="text-slate-800 tabular-nums font-bold"></dd></div>
                <div class="flex gap-2" id="lhBmPaidAtRow" style="display:none"><dt class="text-slate-400 font-bold shrink-0 w-24">Plătit la</dt><dd id="lhBmPaidAt" class="text-slate-800 text-xs"></dd></div>
                <div class="flex gap-2" id="lhBmCheckoutRow" style="display:none"><dt class="text-slate-400 font-bold shrink-0 w-24">checkout_id</dt><dd id="lhBmCheckout" class="text-slate-600 font-mono text-[10px] break-all min-w-0"></dd></div>
                <div class="flex gap-2" id="lhBmPaymentIdRow" style="display:none"><dt class="text-slate-400 font-bold shrink-0 w-24">payment_id</dt><dd id="lhBmPaymentId" class="text-slate-600 font-mono text-[10px] break-all min-w-0"></dd></div>
            </dl>
        </div>

        <form id="lhBmRefundForm" method="POST" action="bookings.php" class="hidden mt-5 pt-5 border-t border-slate-100" onsubmit="return lhConfirmRefund(this);">
            <?php lh_csrf_field(); ?>
            <input type="hidden" name="action" value="refund">
            <input type="hidden" name="booking_id" id="lhBmRefundBookingId" value="">
            <h4 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-3">Rambursare maib</h4>
            <p id="lhBmRefundWarn" class="hidden text-[11px] text-ink font-semibold mb-2 normal-case tracking-normal">Sub politica standard de 24h înainte de check-in.</p>
            <div class="space-y-2">
                <input type="text" name="refund_amount" id="lhBmRefundAmount" inputmode="decimal" placeholder="Sumă parțială (gol = integral)" class="w-full text-sm font-medium px-3 py-2 rounded-xl border border-black/10 bg-white focus:ring-2 focus:ring-cta/25 focus:border-cta outline-none">
                <input type="text" name="refund_reason" placeholder="Motiv (opțional)" class="w-full text-sm font-medium px-3 py-2 rounded-xl border border-black/10 bg-white focus:ring-2 focus:ring-cta/25 focus:border-cta outline-none">
                <button type="submit" class="w-full py-2.5 rounded-xl bg-cta text-white font-bold hover:brightness-110 transition-all text-sm">Rambursare maib</button>
            </div>
        </form>

        <div class="mt-6 flex flex-col gap-3">
            <div class="grid grid-cols-2 gap-2">
                <button type="button" id="lhBmBtnEdit" class="inline-flex items-center justify-center py-3 rounded-xl bg-cta text-white font-bold hover:brightness-110 text-sm">Editează</button>
                <button type="button" id="lhBmBtnCancel" class="inline-flex items-center justify-center py-3 rounded-xl border-2 border-red-200 text-red-600 font-bold hover:bg-red-50 text-sm">Anulează</button>
            </div>
            <a id="lhBmLinkList" href="#" class="hidden text-center text-sm font-bold text-slate-500 hover:text-slate-800">Vezi în listă</a>
            <a id="lhBmLinkCalendar" href="calendar.php" class="hidden text-center text-sm font-bold text-slate-500 hover:text-slate-800">Deschide calendar</a>
        </div>
    </div>
</div>

<!-- Modal editare rezervare -->
<div id="lhBookingEditModal" class="fixed inset-0 z-[8100] hidden items-center justify-center bg-slate-900/70 p-4">
    <form method="post" id="lhBookingEditForm" class="bg-white max-w-md w-full max-h-[90vh] overflow-y-auto rounded-2xl shadow-2xl p-8 relative">
        <?php lh_csrf_field(); ?>
        <input type="hidden" name="booking_id" id="lhBeBookingId" value="">
        <input type="hidden" name="return_page" id="lhBeReturnPage" value="bookings">
        <input type="hidden" name="calendar_action" id="lhBeCalendarAction" value="booking_update">
        <input type="hidden" name="action" id="lhBeBookingsAction" value="update">
        <input type="hidden" name="redirect_from" id="lhBeRedirectFrom" value="">
        <input type="hidden" name="redirect_days" id="lhBeRedirectDays" value="">
        <button type="button" class="lh-booking-modal-close absolute top-4 right-4 text-slate-400 hover:text-slate-900 font-bold text-sm">✕</button>
        <h3 class="text-xl font-black text-slate-900 pr-8">Editează rezervarea</h3>
        <div class="mt-4 space-y-3">
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Nume</label>
                <input type="text" name="guest_name" id="lhBeGuestName" required class="w-full border border-slate-200 rounded-xl px-3 py-2 font-bold text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Email</label>
                <input type="email" name="guest_email" id="lhBeGuestEmail" required class="w-full border border-slate-200 rounded-xl px-3 py-2 font-bold text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Telefon</label>
                <input type="text" name="guest_phone" id="lhBeGuestPhone" required class="w-full border border-slate-200 rounded-xl px-3 py-2 font-bold text-sm">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Check-in</label>
                    <input type="date" name="check_in" id="lhBeCheckIn" required class="w-full border border-slate-200 rounded-xl px-2 py-2 font-bold text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Check-out</label>
                    <input type="date" name="check_out" id="lhBeCheckOut" required class="w-full border border-slate-200 rounded-xl px-2 py-2 font-bold text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Oaspeți</label>
                <input type="number" name="guests" id="lhBeGuests" min="1" required class="w-full border border-slate-200 rounded-xl px-3 py-2 font-bold text-sm">
            </div>
            <p class="text-[10px] text-slate-400 leading-snug">La schimbarea perioadei sau numărului de oaspeți, totalul se recalculează automat. Perioada trebuie să fie liberă (fără alte blocări).</p>
        </div>
        <div class="mt-6 flex flex-col gap-2">
            <button type="submit" class="w-full py-3 rounded-xl bg-cta text-white font-bold hover:brightness-110">Salvează</button>
            <button type="button" id="lhBeBtnBack" class="w-full py-3 rounded-xl border border-slate-200 text-slate-700 font-bold hover:bg-slate-50">Înapoi</button>
        </div>
    </form>
</div>

<form id="lhFormBookingCancelCalendar" method="post" action="calendar.php" class="hidden" aria-hidden="true">
    <?php lh_csrf_field(); ?>
    <input type="hidden" name="calendar_action" value="booking_cancel">
    <input type="hidden" name="booking_id" id="lhBcCalendarBookingId" value="">
    <input type="hidden" name="redirect_from" id="lhBcCalendarRedirectFrom" value="">
    <input type="hidden" name="redirect_days" id="lhBcCalendarRedirectDays" value="">
</form>

<form id="lhFormBookingCancelBookings" method="post" action="bookings.php" class="hidden" aria-hidden="true">
    <?php lh_csrf_field(); ?>
    <input type="hidden" name="action" value="cancel">
    <input type="hidden" name="booking_id" id="lhBcBookingsBookingId" value="">
</form>

<script>
(function () {
    var LH_CUR = <?php echo json_encode(function_exists('lh_currency_client_config') ? lh_currency_client_config() : ['suffix' => ' MDL'], JSON_UNESCAPED_UNICODE); ?>;

    function lhBmCurrencySuffix() {
        if (LH_CUR && LH_CUR.suffix != null && String(LH_CUR.suffix) !== '') {
            return String(LH_CUR.suffix);
        }
        return ' MDL';
    }

    function lhBmFormatMoney(amount, decimals) {
        var n = typeof amount === 'number' ? amount : parseFloat(String(amount || ''), 10) || 0;
        var d = decimals != null ? decimals : 0;
        return n.toLocaleString('ro-RO', { minimumFractionDigits: d, maximumFractionDigits: d }) + lhBmCurrencySuffix();
    }

    function lhBmOpenModal(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('hidden');
        el.classList.add('flex');
        try {
            if (typeof lucide !== 'undefined' && lucide.createIcons) lucide.createIcons();
        } catch (e) { /* ignore */ }
    }

    function lhBmCloseModalEl(el) {
        if (!el) return;
        el.classList.add('hidden');
        el.classList.remove('flex');
    }

    window.lhConfirmRefund = function (form) {
        var fullAmount = parseFloat(form.getAttribute('data-refundable') || '0') || 0;
        var input = form.querySelector('input[name="refund_amount"]');
        var raw = input ? String(input.value || '').trim().replace(',', '.') : '';
        var amount = raw === '' ? fullAmount : parseFloat(raw);
        if (isNaN(amount) || amount <= 0) {
            alert('Introdu o sumă validă sau lasă câmpul gol pentru rambursare integrală.');
            return false;
        }
        var msg = raw === ''
            ? 'Rambursare integrală: ' + lhBmFormatMoney(fullAmount, 2) + '?'
            : 'Rambursare parțială: ' + lhBmFormatMoney(amount, 2) + '?';
        return confirm(msg);
    };

    window.LhBookingModal = {
        lastBooking: null,
        calendarRedirectFrom: '',
        calendarRedirectDays: '60',

        setCalendarContext: function (fromYmd, days) {
            this.calendarRedirectFrom = fromYmd || '';
            this.calendarRedirectDays = String(days || 60);
        },

        open: function (payload) {
            if (!payload || !payload.id) return;
            this.lastBooking = payload;

            document.getElementById('lhBmId').textContent = '#' + payload.id;
            document.getElementById('lhBmGuest').textContent = payload.guest_name || '—';
            document.getElementById('lhBmEmail').textContent = payload.guest_email || '—';
            document.getElementById('lhBmPhone').textContent = payload.guest_phone || '—';
            document.getElementById('lhBmRange').textContent = (payload.check_in || '') + ' → ' + (payload.check_out || '');
            document.getElementById('lhBmGuests').textContent = payload.guests > 0 ? String(payload.guests) : '—';
            document.getElementById('lhBmStatus').textContent = payload.status_label || payload.status || '—';
            document.getElementById('lhBmTotal').textContent = lhBmFormatMoney(payload.total_price || 0, 0);

            var propRow = document.getElementById('lhBmPropertyRow');
            var propTitle = (payload.property_title || '').trim();
            if (propRow) {
                if (propTitle !== '') {
                    propRow.style.display = 'flex';
                    var lot = (payload.property_lot_id || '').trim();
                    document.getElementById('lhBmProperty').textContent = propTitle + (lot !== '' ? ' · LOT #' + lot : '');
                } else {
                    propRow.style.display = 'none';
                }
            }

            var coupRow = document.getElementById('lhBmCouponRow');
            var coupDetail = document.getElementById('lhBmCouponDetail');
            var coupDisc = typeof payload.coupon_discount_amount === 'number' ? payload.coupon_discount_amount : parseFloat(String(payload.coupon_discount_amount || ''), 10) || 0;
            var coupCode = ((payload.coupon_code) ? String(payload.coupon_code) : '').trim();
            if (coupRow && coupDetail && coupDisc > 0.004 && coupCode !== '') {
                coupRow.style.display = 'flex';
                coupDetail.textContent = '«' + coupCode + '» · reducere ' + lhBmFormatMoney(coupDisc, 0) + ' (din tariful nopților)';
            } else if (coupRow && coupDetail) {
                coupRow.style.display = 'none';
                coupDetail.textContent = '';
            }

            document.getElementById('lhBmPayMethod').textContent = payload.payment_method_label || '—';
            document.getElementById('lhBmPayStatus').textContent = payload.payment_status_label || '—';

            var paid = parseFloat(payload.payment_amount) || 0;
            var refunded = parseFloat(payload.refunded_amount) || 0;
            var rest = parseFloat(payload.refundable_amount) || 0;
            var isOnline = payload.payment_method === 'online';

            function toggleRow(id, show) {
                var el = document.getElementById(id);
                if (el) el.style.display = show ? 'flex' : 'none';
            }
            toggleRow('lhBmPaidRow', isOnline && paid > 0.004);
            toggleRow('lhBmRefundedRow', isOnline && refunded > 0.004);
            toggleRow('lhBmRestRow', isOnline && rest > 0.004);
            if (document.getElementById('lhBmPaid')) document.getElementById('lhBmPaid').textContent = lhBmFormatMoney(paid, 2);
            if (document.getElementById('lhBmRefunded')) document.getElementById('lhBmRefunded').textContent = lhBmFormatMoney(refunded, 2);
            if (document.getElementById('lhBmRest')) document.getElementById('lhBmRest').textContent = lhBmFormatMoney(rest, 2);

            var paidAt = (payload.paid_at || '').trim();
            toggleRow('lhBmPaidAtRow', paidAt !== '');
            if (document.getElementById('lhBmPaidAt')) document.getElementById('lhBmPaidAt').textContent = paidAt || '—';

            var checkoutId = (payload.maib_checkout_id || '').trim();
            toggleRow('lhBmCheckoutRow', checkoutId !== '');
            if (document.getElementById('lhBmCheckout')) {
                document.getElementById('lhBmCheckout').textContent = checkoutId;
                document.getElementById('lhBmCheckout').title = checkoutId;
            }

            var paymentId = (payload.maib_payment_id || '').trim();
            toggleRow('lhBmPaymentIdRow', paymentId !== '');
            if (document.getElementById('lhBmPaymentId')) {
                document.getElementById('lhBmPaymentId').textContent = paymentId;
                document.getElementById('lhBmPaymentId').title = paymentId;
            }

            var refundForm = document.getElementById('lhBmRefundForm');
            var canRefund = !!payload.can_refund;
            if (refundForm) {
                refundForm.classList.toggle('hidden', !canRefund);
                refundForm.setAttribute('data-refundable', String(rest));
                document.getElementById('lhBmRefundBookingId').value = String(payload.id);
                var amtInput = document.getElementById('lhBmRefundAmount');
                if (amtInput) {
                    amtInput.value = '';
                    amtInput.placeholder = 'Sumă parțială (gol = integral ' + lhBmFormatMoney(rest, 2) + ')';
                }
                var warn = document.getElementById('lhBmRefundWarn');
                if (warn) warn.classList.toggle('hidden', !payload.refund_warning_24h || !canRefund);
            }

            var btnCancel = document.getElementById('lhBmBtnCancel');
            var btnEdit = document.getElementById('lhBmBtnEdit');
            var canCancel = payload.can_cancel !== false && payload.status !== 'cancelled';
            if (btnCancel) btnCancel.style.display = canCancel ? '' : 'none';
            if (btnEdit) btnEdit.style.display = canCancel ? '' : 'none';

            var linkList = document.getElementById('lhBmLinkList');
            var linkCal = document.getElementById('lhBmLinkCalendar');
            if (linkList) {
                linkList.href = 'bookings.php?status=all&booking_id=' + encodeURIComponent(payload.id) + '#booking-' + payload.id;
                linkList.classList.toggle('hidden', payload.context === 'bookings');
            }
            if (linkCal) {
                linkCal.classList.toggle('hidden', payload.context === 'calendar');
            }

            lhBmOpenModal('lhBookingModal');
        },

        openEdit: function () {
            var b = this.lastBooking;
            if (!b || !b.id) return;
            lhBmCloseModalEl(document.getElementById('lhBookingModal'));

            document.getElementById('lhBeBookingId').value = String(b.id);
            document.getElementById('lhBeGuestName').value = b.guest_name || '';
            document.getElementById('lhBeGuestEmail').value = b.guest_email || '';
            document.getElementById('lhBeGuestPhone').value = b.guest_phone || '';
            document.getElementById('lhBeCheckIn').value = b.check_in || '';
            document.getElementById('lhBeCheckOut').value = b.check_out || '';
            document.getElementById('lhBeGuests').value = b.guests > 0 ? String(b.guests) : '1';

            var ctx = b.context || 'bookings';
            document.getElementById('lhBeReturnPage').value = ctx;
            var form = document.getElementById('lhBookingEditForm');
            if (form) {
                form.action = ctx === 'calendar' ? 'calendar.php' : 'bookings.php';
            }
            document.getElementById('lhBeRedirectFrom').value = this.calendarRedirectFrom || '';
            document.getElementById('lhBeRedirectDays').value = this.calendarRedirectDays || '60';

            lhBmOpenModal('lhBookingEditModal');
        },

        openView: function () {
            lhBmCloseModalEl(document.getElementById('lhBookingEditModal'));
            lhBmOpenModal('lhBookingModal');
        }
    };

    document.getElementById('lhBmBtnEdit')?.addEventListener('click', function () {
        window.LhBookingModal.openEdit();
    });

    document.getElementById('lhBeBtnBack')?.addEventListener('click', function () {
        window.LhBookingModal.openView();
    });

    document.getElementById('lhBmBtnCancel')?.addEventListener('click', function () {
        var b = window.LhBookingModal.lastBooking;
        if (!b || !b.id) return;
        if (!window.confirm('Sigur anulezi această rezervare? Rambursarea nu se face automat.')) return;

        if (b.context === 'calendar') {
            document.getElementById('lhBcCalendarBookingId').value = String(b.id);
            document.getElementById('lhBcCalendarRedirectFrom').value = window.LhBookingModal.calendarRedirectFrom || '';
            document.getElementById('lhBcCalendarRedirectDays').value = window.LhBookingModal.calendarRedirectDays || '60';
            document.getElementById('lhFormBookingCancelCalendar').submit();
        } else {
            document.getElementById('lhBcBookingsBookingId').value = String(b.id);
            document.getElementById('lhFormBookingCancelBookings').submit();
        }
    });

    document.querySelectorAll('.lh-booking-modal-close').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modal = btn.closest('#lhBookingModal, #lhBookingEditModal');
            if (modal) lhBmCloseModalEl(modal);
        });
    });

    [document.getElementById('lhBookingModal'), document.getElementById('lhBookingEditModal')].forEach(function (modal) {
        if (!modal) return;
        modal.addEventListener('click', function (e) {
            if (e.target === modal) lhBmCloseModalEl(modal);
        });
    });

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-lh-booking-open]');
        if (!trigger) return;
        e.preventDefault();
        var raw = trigger.getAttribute('data-booking') || trigger.getAttribute('data-lh-booking-open');
        if (!raw) return;
        try {
            var payload = JSON.parse(raw);
            window.LhBookingModal.open(payload);
        } catch (err) { /* ignore */ }
    });
})();
</script>
