<?php

declare(strict_types=1);

/**
 * Plain-text bodies for guest-facing booking emails (confirmation + pre-check-in reminder).
 */

if (!function_exists('lh_booking_guest_first_name')) {
    function lh_booking_guest_first_name(string $fullName): string
    {
        $t = trim($fullName);
        if ($t === '') {
            return 'oaspete';
        }
        $parts = preg_split('/\s+/u', $t, 2);

        return $parts[0] ?? $t;
    }
}

if (!function_exists('lh_booking_time_to_hi')) {
    /**
     * Normalize DB time (e.g. 14:00:00) to HH:MM for guest emails.
     */
    function lh_booking_time_to_hi(string $raw): string
    {
        $t = trim($raw);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $t, $m)) {
            $h = (int) $m[1];
            $min = (int) $m[2];
            if ($h >= 0 && $h <= 23 && $min >= 0 && $min <= 59) {
                return sprintf('%02d:%02d', $h, $min);
            }
        }

        return '';
    }
}

if (!function_exists('lh_booking_guest_support_phones_block')) {
    /**
     * Multiline contact list for emails (override via BOOKING_GUEST_SUPPORT_PHONES in .env).
     */
    function lh_booking_guest_support_phones_block(): string
    {
        $block = defined('BOOKING_GUEST_SUPPORT_PHONES')
            ? trim((string) BOOKING_GUEST_SUPPORT_PHONES)
            : '';

        return $block !== '' ? $block : "Andrei — +373 69 397 372\nAurel — +373 69 111 427";
    }
}

if (!function_exists('lh_build_guest_booking_confirmation_body')) {
    /**
     * @param array{
     *   guest_name: string,
     *   property_title: string,
     *   check_in: string,
     *   check_out: string,
     *   guests: int,
     *   total_price: float|string,
     *   booking_id: int,
     *   coupon_code?: string,
     *   coupon_discount_amount?: float|int|string
     * } $ctx
     */
    function lh_build_guest_booking_confirmation_body(array $ctx): string
    {
        $locale = (string) ($ctx['locale'] ?? lh_default_locale());
        $t = static fn (string $key, array $replace = []) => lh_translate($key, $replace, $locale);

        $guestName = (string) ($ctx['guest_name'] ?? '');
        $first = lh_booking_guest_first_name($guestName);
        if ($first === 'oaspete' && $locale !== 'ro') {
            $first = lh_translate('email.guest_fallback', [], $locale);
        }
        $propertyTitle = (string) ($ctx['property_title'] ?? '');
        $checkIn = (string) ($ctx['check_in'] ?? '');
        $checkOut = (string) ($ctx['check_out'] ?? '');
        $guests = (int) ($ctx['guests'] ?? 1);
        $total = $ctx['total_price'] ?? 0;
        $totalFormatted = function_exists('lh_format_money')
            ? lh_format_money((float) $total, 2)
            : (string) $total;
        $bookingId = (int) ($ctx['booking_id'] ?? 0);

        $phones = lh_booking_guest_support_phones_block();
        $contactUrl = function_exists('lh_absolute_locale_url')
            ? lh_absolute_locale_url('contact.php', $locale)
            : 'https://www.likehome.md/contact.php';

        $lines = [];
        $lines[] = $t('email.confirm_greeting', ['name' => $first]);
        $lines[] = '';
        $lines[] = $t('email.confirm_thanks');
        $lines[] = '';
        $lines[] = $t('email.confirm_body');
        $lines[] = '';
        $lines[] = $t('email.confirm_details');
        $lines[] = $t('email.confirm_property') . ': ' . $propertyTitle;
        $lines[] = $t('email.confirm_period') . ': ' . $checkIn . ' → ' . $checkOut;
        $lines[] = $t('email.confirm_guests') . ': ' . $guests;
        $cCode = trim((string) ($ctx['coupon_code'] ?? ''));
        $cDisc = isset($ctx['coupon_discount_amount']) ? (float) $ctx['coupon_discount_amount'] : 0.0;
        if ($cCode !== '' && $cDisc > 0.004) {
            $discFmt = function_exists('lh_format_money')
                ? lh_format_money($cDisc, 2)
                : (string) $cDisc;
            $lines[] = '«' . $cCode . '»: -' . $discFmt;
        }
        $lines[] = $t('email.confirm_total') . ': ' . $totalFormatted;
        if (!empty($ctx['paid_online'])) {
            $paidFmt = function_exists('lh_format_money')
                ? lh_format_money((float) ($ctx['payment_amount'] ?? $total), 2)
                : (string) ($ctx['payment_amount'] ?? $total);
            $lines[] = $t('email.confirm_paid_online') . ': ' . $paidFmt;
            if (function_exists('lh_company_legal_name')) {
                $lines[] = $t('email.confirm_merchant') . ': ' . lh_company_legal_name();
            }
            $siteUrl = function_exists('lh_public_site_origin')
                ? lh_public_site_origin()
                : 'https://www.likehome.md';
            $lines[] = $t('email.confirm_website') . ': ' . $siteUrl;
            $lines[] = $t('email.confirm_order_no') . ': LH-' . $bookingId;
            $currency = trim((string) ($ctx['currency'] ?? ''));
            if ($currency === '' && function_exists('lh_company_currency')) {
                $currency = lh_company_currency();
            }
            if ($currency !== '') {
                $lines[] = $t('email.confirm_currency') . ': ' . $currency;
            }
            $paidAtRaw = trim((string) ($ctx['paid_at'] ?? ''));
            if ($paidAtRaw !== '') {
                $paidAtDisp = $paidAtRaw;
                try {
                    $paidAtDt = new DateTimeImmutable($paidAtRaw);
                    $paidAtDisp = $paidAtDt->format('d.m.Y H:i');
                } catch (Throwable) {
                    /* keep raw */
                }
                $lines[] = $t('email.confirm_payment_date') . ': ' . $paidAtDisp;
            }
        } elseif (($ctx['payment_method'] ?? '') === 'on_site') {
            $lines[] = $t('email.confirm_pay_at_checkin');
        }
        if (!empty($ctx['online_discount_amount']) && (float) $ctx['online_discount_amount'] > 0.004) {
            $discOnline = function_exists('lh_format_money')
                ? lh_format_money((float) $ctx['online_discount_amount'], 2)
                : (string) $ctx['online_discount_amount'];
            $lines[] = $t('email.confirm_online_discount') . ': -' . $discOnline;
        }
        $lines[] = 'Booking ID: #' . $bookingId;
        $lines[] = '';
        $lines[] = $phones;
        $lines[] = '';
        $lines[] = $contactUrl;
        $lines[] = '';
        $lines[] = $t('email.confirm_signoff');
        $lines[] = $t('email.confirm_team');

        return implode("\n", $lines);
    }
}

if (!function_exists('lh_build_guest_checkin_reminder_body')) {
    /**
     * @param array<string,mixed> $row Same keys as lh_checkin_reminder_send_for_booking_row input (+ optional floor)
     */
    function lh_build_guest_checkin_reminder_body(array $row): string
    {
        $locale = (string) ($row['locale'] ?? lh_default_locale());
        $t = static fn (string $key, array $replace = []) => lh_translate($key, $replace, $locale);

        $guestName = (string) ($row['guest_name'] ?? '');
        $first = lh_booking_guest_first_name($guestName);
        if ($first === 'oaspete' && $locale !== 'ro') {
            $first = lh_translate('email.guest_fallback', [], $locale);
        }
        $propTitle = (string) ($row['property_title'] ?? lh_translate('card.fallback_title', [], $locale));
        $customMsg = trim((string) ($row['pre_checkin_email_message'] ?? ''));
        $addressLine = trim(implode(', ', array_filter([
            trim((string) ($row['address'] ?? '')),
            trim((string) ($row['district'] ?? '')),
            trim((string) ($row['city'] ?? '')),
        ])));

        $cinStartHi = lh_booking_time_to_hi((string) ($row['check_in_start'] ?? '')) ?: '14:00';
        $cinEndHi = lh_booking_time_to_hi((string) ($row['check_in_end'] ?? ''));
        $coutEndHi = lh_booking_time_to_hi((string) ($row['check_out_end'] ?? '')) ?: '11:00';

        $checkInDate = (string) ($row['check_in'] ?? '');
        $checkOutDate = (string) ($row['check_out'] ?? '');
        $bookingId = (int) ($row['booking_id'] ?? 0);

        $contactUrl = function_exists('lh_absolute_locale_url')
            ? lh_absolute_locale_url('contact.php', $locale)
            : (function_exists('lh_absolute_url') ? lh_absolute_url('contact.php') : 'https://www.likehome.md/contact.php');

        $b = [];
        $b[] = $t('email.reminder.greeting', ['name' => $first]);
        $b[] = '';
        $b[] = $t('email.reminder.intro');
        $b[] = '';
        $b[] = $t('email.reminder.property_label') . ': ' . $propTitle;
        $b[] = $t('email.reminder.period_label') . ': check-in ' . $checkInDate . ', check-out ' . $checkOutDate . '.';
        $b[] = '';
        $b[] = $t('email.reminder.address_label');
        $b[] = $addressLine !== '' ? $addressLine : '—';
        if ($customMsg !== '') {
            $b[] = '';
            $b[] = $customMsg;
        }
        $b[] = '';
        $b[] = $t('email.reminder.checkin_label') . ': ' . $cinStartHi;
        if ($cinEndHi !== '') {
            $b[] = $t('email.reminder.checkin_label') . ' (max): ' . $cinEndHi;
        }
        $b[] = $t('email.reminder.checkout_label') . ': ' . $coutEndHi;
        $b[] = '';
        $b[] = $t('email.reminder.wifi_label');
        if ($customMsg === '') {
            $b[] = $t('email.reminder.wifi_none');
        }
        $b[] = '';
        $b[] = $t('email.reminder.instructions');
        $b[] = '';
        $b[] = $t('email.reminder.signoff');
        $b[] = $t('email.reminder.team');
        $b[] = '';
        $b[] = '—';
        $b[] = $t('email.confirm_booking_id', ['id' => (string) $bookingId]);
        $b[] = $t('email.reminder.contact_label') . ': ' . $contactUrl;

        return implode("\n", $b);
    }
}
