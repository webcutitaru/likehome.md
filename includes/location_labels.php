<?php

declare(strict_types=1);

/**
 * Display labels for canonical location values (DB stays in local form).
 */

if (!function_exists('lh_location_label_map')) {
    /** @return array<string, string> canonical value => translation key */
    function lh_location_label_map(): array
    {
        return [
            'Centru' => 'location.district.centru',
            'Râșcani' => 'location.district.rascani',
            'Botanica' => 'location.district.botanica',
            'Ciocana' => 'location.district.ciocana',
            'Buiucani' => 'location.district.buiucani',
            'Telecentru' => 'location.district.telecentru',
            'Chișinău' => 'location.city.chisinau',
        ];
    }
}

if (!function_exists('lh_location_label')) {
    function lh_location_label(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $map = lh_location_label_map();
        if (isset($map[$value])) {
            $translated = __($map[$value]);
            if ($translated !== $map[$value]) {
                return $translated;
            }
        }

        return $value;
    }
}
