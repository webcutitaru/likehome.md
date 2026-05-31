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
        $guestName = (string) ($ctx['guest_name'] ?? '');
        $first = lh_booking_guest_first_name($guestName);
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
        $contactUrl = function_exists('lh_absolute_url') ? lh_absolute_url('contact.php') : 'https://www.likehome.md/contact.php';

        $lines = [];
        $lines[] = 'Salut, ' . $first . '!';
        $lines[] = '';
        $lines[] = 'Îți mulțumim că ai ales Like Home — ne bucurăm sincer să fii oaspetele nostru.';
        $lines[] = '';
        $lines[] = 'Rezervarea ta este confirmată. Îți rezervăm experiența aceea „ca acasă”, cu tot confortul unei locuințe gândite pentru șederi scurte în Chișinău.';
        $lines[] = '';
        $lines[] = '— Detalii rezervare —';
        $lines[] = 'Proprietate: ' . $propertyTitle;
        $lines[] = 'Perioadă: ' . $checkIn . ' → ' . $checkOut;
        $lines[] = 'Oaspeți: ' . $guests;
        $cCode = trim((string) ($ctx['coupon_code'] ?? ''));
        $cDisc = isset($ctx['coupon_discount_amount']) ? (float) $ctx['coupon_discount_amount'] : 0.0;
        if ($cCode !== '' && $cDisc > 0.004) {
            $discFmt = function_exists('lh_format_money')
                ? lh_format_money($cDisc, 2)
                : (string) $cDisc;
            $lines[] = 'Reducere cupon «' . $cCode . '»: ' . $discFmt . ' (din tariful nopților)';
        }
        $lines[] = 'Total: ' . $totalFormatted;
        $lines[] = 'Booking ID: #' . $bookingId;
        $lines[] = '';
        $lines[] = 'Îți trimitem și datele de contact ale administratorilor noștri, care se ocupă de cazarea ta:';
        $lines[] = $phones;
        $lines[] = '';
        $lines[] = 'Cu aproximativ 24 de ore înainte de ora ta de check-in vei primi un al doilea email, cu toate informațiile practice pentru sosire (adresă detaliată, Wi‑Fi, pașii de check-in și check-out).';
        $lines[] = '';
        $lines[] = 'Dacă acel email nu ajunge la tine în timp util, te rugăm să nu ezita să ne scrii sau să ne suni — rezolvăm imediat.';
        $lines[] = '';
        $lines[] = 'Poți folosi și pagina de contact: ' . $contactUrl;
        $lines[] = '';
        $lines[] = 'Îți dorim o ședere liniștită și plăcută.';
        $lines[] = '';
        $lines[] = 'Cu stimă,';
        $lines[] = 'Like Home Team';

        return implode("\n", $lines);
    }
}

if (!function_exists('lh_build_guest_checkin_reminder_body')) {
    /**
     * @param array<string,mixed> $row Same keys as lh_checkin_reminder_send_for_booking_row input (+ optional floor)
     */
    function lh_build_guest_checkin_reminder_body(array $row): string
    {
        $guestName = (string) ($row['guest_name'] ?? '');
        $first = lh_booking_guest_first_name($guestName);
        $propTitle = (string) ($row['property_title'] ?? 'Proprietate');
        $customMsg = trim((string) ($row['pre_checkin_email_message'] ?? ''));
        $addressLine = trim(implode(', ', array_filter([
            trim((string) ($row['address'] ?? '')),
            trim((string) ($row['district'] ?? '')),
            trim((string) ($row['city'] ?? '')),
        ])));

        $cinStartHi = lh_booking_time_to_hi((string) ($row['check_in_start'] ?? ''));
        if ($cinStartHi === '') {
            $cinStartHi = '14:00';
        }
        $cinEndHi = lh_booking_time_to_hi((string) ($row['check_in_end'] ?? ''));
        $coutEndHi = lh_booking_time_to_hi((string) ($row['check_out_end'] ?? ''));
        if ($coutEndHi === '') {
            $coutEndHi = '11:00';
        }

        $checkInDate = (string) ($row['check_in'] ?? '');
        $checkOutDate = (string) ($row['check_out'] ?? '');
        $bookingId = (int) ($row['booking_id'] ?? 0);
        $floor = isset($row['floor']) ? (int) $row['floor'] : 0;

        $contactUrl = function_exists('lh_absolute_url') ? lh_absolute_url('contact.php') : 'https://www.likehome.md/contact.php';

        $b = [];
        $b[] = 'Salut, ' . $first . '!';
        $b[] = '';
        $b[] = 'Prin prezenta, îți confirm rezervarea și îți ofer câteva informații utile cu privire la șederea ta în Chișinău.';
        $b[] = '';
        $b[] = 'Proprietate: ' . $propTitle;
        $b[] = 'Perioadă: check-in ' . $checkInDate . ', check-out ' . $checkOutDate . '.';
        $b[] = '';
        $b[] = 'ADRESA APARTAMENTULUI';
        if ($addressLine !== '') {
            $b[] = $addressLine;
        } else {
            $b[] = '(Adresa completă este în mesajul de mai jos sau te rugăm să ne contactezi.)';
        }
        if ($floor > 0) {
            $b[] = 'Etaj: ' . $floor;
        }
        if ($customMsg !== '') {
            $b[] = '';
            $b[] = $customMsg;
        }
        $b[] = '';
        $b[] = 'CHECK-IN: de la ' . $cinStartHi;
        if ($cinEndHi !== '') {
            $b[] = 'Fereastră check-in în ziua sosirii: până la ' . $cinEndHi . ' (te rugăm să anunți ora aproximativă a sosirii).';
        }
        $b[] = 'CHECK-OUT: până la ' . $coutEndHi;
        $b[] = '';
        $b[] = 'CHECK-IN';
        $b[] = 'Cu o oră înainte de sosire, te rugăm să ne scrii un mesaj: managerul nostru te va întâmpina la scară și te va caza în apartament, sau îți va trimite informațiile necesare pentru self check-in.';
        $b[] = '';
        $b[] = 'CHECK-OUT';
        $b[] = 'Managerul nostru te va contacta pentru a conveni ora de predare a cheilor.';
        $b[] = 'Te rugăm să părăsești apartamentul nu mai târziu de ora ' . $coutEndHi . ': înainte de a pleca, stingi luminile și aerul condiționat și închide robinetele de apă.';
        $b[] = '';
        $b[] = 'ARTICOLE DE CONFORT';
        $b[] = 'În apartament găsești lenjerie de pat, prosoape, o gamă mică de produse de toaletă și un uscător de păr.';
        $b[] = '';
        $b[] = 'WI-FI';
        if ($customMsg !== '') {
            $b[] = 'Dacă ai inclus SSID-ul și parola în mesajul personalizat de mai sus, le poți folosi direct; altfel răspunde la acest email sau sună-ne.';
        } else {
            $b[] = 'Pentru această proprietate nu este încă setat mesajul personalizat; te rugăm să ne contactezi pentru numele rețelei și parolă.';
        }
        $b[] = '';
        $b[] = 'Nu ezita să ne suni sau să ne scrii pentru orice clarificare — ne face plăcere să te ajutăm.';
        $b[] = '';
        $b[] = 'Cu stimă,';
        $b[] = 'Like Home Team';
        $b[] = '';
        $b[] = '—';
        $b[] = 'Booking ID: #' . $bookingId;
        $b[] = 'Contact: ' . $contactUrl;

        return implode("\n", $b);
    }
}
