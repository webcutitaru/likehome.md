<?php

declare(strict_types=1);

if (!function_exists('lh_company_legal_name')) {
    function lh_company_legal_name(): string
    {
        return trim(lh_env('COMPANY_LEGAL_NAME', 'LIKE HOME S.R.L.'));
    }
}

if (!function_exists('lh_company_idno')) {
    function lh_company_idno(): string
    {
        return trim(lh_env('COMPANY_IDNO', '1024600038646'));
    }
}

if (!function_exists('lh_company_legal_address')) {
    function lh_company_legal_address(): string
    {
        return trim(lh_env('COMPANY_LEGAL_ADDRESS', 'MD2025, mun. Chisinau, str. Nicolae Testemitanu 23/2'));
    }
}

if (!function_exists('lh_company_iban')) {
    function lh_company_legal_iban(): string
    {
        return trim(lh_env('COMPANY_IBAN', 'MD82AG000000022516069786'));
    }
}

if (!function_exists('lh_company_bic')) {
    function lh_company_bic(): string
    {
        return trim(lh_env('COMPANY_BIC', 'AGRNMD2X'));
    }
}

if (!function_exists('lh_company_currency')) {
    function lh_company_currency(): string
    {
        return trim(lh_env('COMPANY_CURRENCY', 'MDL'));
    }
}

if (!function_exists('lh_company_contact_phones')) {
    function lh_company_contact_phones(): string
    {
        return trim(lh_env('COMPANY_CONTACT_PHONES', '+373 69 397 372 · +373 69 111 427'));
    }
}

if (!function_exists('lh_terms_replace_placeholders')) {
    function lh_terms_replace_placeholders(string $body): string
    {
        require_once __DIR__ . '/seo.php';
        require_once __DIR__ . '/booking_payment.php';

        $privacyUrl = function_exists('lh_absolute_locale_url')
            ? lh_absolute_locale_url('privacy.php')
            : lh_public_site_origin() . '/privacy.php';

        $map = [
            '{email}' => lh_site_contact_email(),
            '{company_name}' => lh_company_legal_name(),
            '{company_idno}' => lh_company_idno(),
            '{company_address}' => lh_company_legal_address(),
            '{company_iban}' => lh_company_legal_iban(),
            '{company_bic}' => lh_company_bic(),
            '{company_currency}' => lh_company_currency(),
            '{company_phones}' => lh_company_contact_phones(),
            '{privacy_url}' => $privacyUrl,
            '{site_url}' => lh_public_site_origin(),
            '{refund_hours}' => (string) lh_booking_cancellation_refund_hours(),
            '{online_discount}' => (string) (int) lh_booking_online_discount_percent(),
        ];

        return str_replace(array_keys($map), array_values($map), $body);
    }
}
