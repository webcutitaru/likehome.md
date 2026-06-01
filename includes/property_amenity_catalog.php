<?php
/**
 * Single source for property amenity labels + Lucide icon names (admin add/edit + public details).
 * Keys are stored in properties.amenities JSON; values are [label_ro, lucide_name].
 */
declare(strict_types=1);

if (!function_exists('lh_property_amenity_categories')) {
    /**
     * Ordered list (4-column grid in admin) aligned with the canonical facilities set.
     */
    function lh_property_amenity_categories(): array
    {
        return [
            '' => [
                'washer' => ['Mașină de spălat', 'washing-machine'],
                'wifi' => ['Wi-Fi', 'wifi'],
                'tv' => ['TV', 'tv'],
                'ac' => ['Aer condiționat', 'wind'],
                'kids' => ['Copii acceptați', 'baby'],
                'events' => ['Evenimente permise', 'party-popper'],
                'fridge' => ['Frigider', 'refrigerator'],
                'telephone' => ['Telefon', 'phone'],
                'stove' => ['Aragaz / plită', 'cooking-pot'],
                'dishwasher' => ['Mașină de vase', 'utensils'],
                'music_center' => ['Sistem audio', 'music-4'],
                'microwave' => ['Cuptor cu microunde', 'microwave'],
                'iron' => ['Fier și masă de călcat', 'shirt'],
                'concierge' => ['Concierge', 'concierge-bell'],
                'parking' => ['Parcare', 'car'],
                'safe' => ['Seif', 'lock'],
                'water_heater' => ['Boiler (apă caldă)', 'flame'],
                'cable_tv' => ['TV cablu', 'satellite-dish'],
                'toiletries' => ['Produse de toaletă', 'droplets'],
                'pets' => ['Animale acceptate', 'dog'],
                'smoking' => ['Fumat permis', 'cigarette'],
                'romantic' => ['Aranjament romantic', 'heart'],
                'sea_view' => ['Vedere la mare', 'waves'],
                'mountain_view' => ['Vedere la munte', 'mountain'],
                'jacuzzi' => ['Jacuzzi', 'bath'],
                'balcony' => ['Balcon', 'door-open'],
                'elevator' => ['Lift', 'arrow-up-down'],
                'beach' => ['Front de plajă', 'palmtree'],
                'pool' => ['Piscină', 'life-buoy'],
                'playground' => ['Loc de joacă copii', 'trees'],
                'transfer' => ['Transfer', 'bus'],
                'crib' => ['Pătuț copii', 'bed-single'],
                'sauna' => ['Saună', 'thermometer-sun'],
            ],
        ];
    }
}

if (!function_exists('lh_property_amenity_legacy_map')) {
    /**
     * Keys removed from the form but still stored in older properties.amenities JSON.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    function lh_property_amenity_legacy_map(): array
    {
        return [
            'coffee' => ['Espressor cafea', 'coffee'],
            'kettle' => ['Fierbător', 'cup-soda'],
            'heating' => ['Încălzire autonomă', 'thermometer-sun'],
            'city_view' => ['Vedere spre oraș', 'building-2'],
        ];
    }
}

if (!function_exists('lh_property_amenity_flat_map')) {
    /**
     * @return array<string, array{0: string, 1: string}> key => [label, icon]
     */
    function lh_property_amenity_flat_map(): array
    {
        static $flat = null;
        if ($flat !== null) {
            return $flat;
        }
        $flat = [];
        foreach (lh_property_amenity_categories() as $items) {
            foreach ($items as $key => $info) {
                $flat[(string) $key] = $info;
            }
        }
        foreach (lh_property_amenity_legacy_map() as $key => $info) {
            if (!isset($flat[(string) $key])) {
                $flat[(string) $key] = $info;
            }
        }
        return $flat;
    }
}

if (!function_exists('lh_property_amenity_resolve')) {
    /**
     * Resolve a stored amenity value (checkbox key, or legacy free-text label) to [label, lucide_icon].
     *
     * @return array{0: string, 1: string}
     */
    function lh_property_amenity_resolve(string $stored): array
    {
        $stored = trim($stored);
        if ($stored === '') {
            return ['', 'check'];
        }
        $flat = lh_property_amenity_flat_map();
        if (isset($flat[$stored])) {
            $info = $flat[$stored];
            $labelKey = 'amenity.' . $stored;
            $translated = __($labelKey);
            if ($translated !== $labelKey) {
                return [$translated, $info[1]];
            }

            return $info;
        }
        foreach ($flat as $info) {
            if ($info[0] === $stored) {
                return $info;
            }
        }
        return [$stored, 'check'];
    }
}
