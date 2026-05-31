<?php

declare(strict_types=1);

/**
 * Display / base currency from environment (amounts in DB are in this unit).
 * APP_BASE_CURRENCY: ISO-like code, e.g. MDL
 * APP_CURRENCY_DISPLAY: optional suffix text without leading space (e.g. "lei"); if empty, APP_BASE_CURRENCY is shown
 */

if (!function_exists('lh_currency_code')) {
    function lh_currency_code(): string
    {
        $c = strtoupper(trim(lh_env('APP_BASE_CURRENCY', 'MDL')));
        if ($c === '') {
            return 'MDL';
        }
        if (strlen($c) > 12) {
            return 'MDL';
        }

        return $c;
    }
}

if (!function_exists('lh_currency_suffix')) {
    /**
     * Suffix after a formatted amount, includes one leading space (e.g. " MDL").
     */
    function lh_currency_suffix(): string
    {
        $raw = trim(lh_env('APP_CURRENCY_DISPLAY', ''));
        $label = $raw !== '' ? $raw : lh_currency_code();

        return ' ' . $label;
    }
}

if (!function_exists('lh_format_money_amount')) {
    function lh_format_money_amount(float $amount, int $decimals = 0): string
    {
        return number_format($amount, $decimals, ',', '.');
    }
}

if (!function_exists('lh_format_money')) {
    function lh_format_money(float $amount, int $decimals = 0): string
    {
        return lh_format_money_amount($amount, $decimals) . lh_currency_suffix();
    }
}

if (!function_exists('lh_currency_client_config')) {
    /**
     * @return array{code: string, suffix: string, decimals: int}
     */
    function lh_currency_client_config(): array
    {
        return [
            'code' => lh_currency_code(),
            'suffix' => lh_currency_suffix(),
            'decimals' => 0,
        ];
    }
}
