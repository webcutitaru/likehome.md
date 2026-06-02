<?php

declare(strict_types=1);

/**
 * Public display for location fields from the database (district, city, address).
 * Values are shown as stored in MySQL on all locales — not translated.
 */

if (!function_exists('lh_location_label')) {
    function lh_location_label(string $value): string
    {
        return trim($value);
    }
}
