<?php

declare(strict_types=1);

require_once __DIR__ . '/locale.php';

if (!function_exists('lh_property_translation_locales')) {
    /** @return list<string> */
    function lh_property_translation_locales(): array
    {
        return array_values(array_filter(
            lh_supported_locales(),
            static fn (string $loc): bool => $loc !== lh_default_locale()
        ));
    }
}

if (!function_exists('lh_property_translation_table_exists')) {
    function lh_property_translation_table_exists(PDO $pdo): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'property_translations'");
            $exists = $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            $exists = false;
        }

        return $exists;
    }
}

if (!function_exists('lh_property_fetch_translation')) {
    /**
     * @return array{title: string, slug: string, description: string, description_long: string}|null
     */
    function lh_property_fetch_translation(PDO $pdo, int $propertyId, string $locale): ?array
    {
        if ($locale === lh_default_locale() || !lh_property_translation_table_exists($pdo)) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT title, slug, description, description_long
             FROM property_translations
             WHERE property_id = :pid AND locale = :locale
             LIMIT 1'
        );
        $stmt->execute([':pid' => $propertyId, ':locale' => $locale]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('lh_property_apply_locale')) {
    /**
     * Merge translated fields onto a property row for the current (or given) locale.
     *
     * @param array<string, mixed> $property
     * @return array<string, mixed>
     */
    function lh_property_apply_locale(array $property, ?PDO $pdo = null, ?string $locale = null): array
    {
        $locale = $locale ?? lh_current_locale();
        if ($locale === lh_default_locale()) {
            return $property;
        }

        $propertyId = (int) ($property['id'] ?? 0);
        if ($propertyId <= 0) {
            return $property;
        }

        try {
            $pdo = $pdo ?? getPDO();
        } catch (Throwable) {
            return $property;
        }

        $tr = lh_property_fetch_translation($pdo, $propertyId, $locale);
        if ($tr === null) {
            return $property;
        }

        foreach (['title', 'slug', 'description', 'description_long'] as $field) {
            $val = trim((string) ($tr[$field] ?? ''));
            if ($val !== '') {
                $property[$field] = $val;
            }
        }

        return $property;
    }
}

if (!function_exists('lh_property_apply_locale_list')) {
    /**
     * @param list<array<string, mixed>> $properties
     * @return list<array<string, mixed>>
     */
    function lh_property_apply_locale_list(array $properties, ?PDO $pdo = null, ?string $locale = null): array
    {
        $locale = $locale ?? lh_current_locale();
        if ($locale === lh_default_locale() || $properties === []) {
            return $properties;
        }

        try {
            $pdo = $pdo ?? getPDO();
        } catch (Throwable) {
            return $properties;
        }

        if (!lh_property_translation_table_exists($pdo)) {
            return $properties;
        }

        $ids = [];
        foreach ($properties as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            return $properties;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT property_id, title, slug, description, description_long
             FROM property_translations
             WHERE locale = ? AND property_id IN ($placeholders)"
        );
        $stmt->execute(array_merge([$locale], $ids));
        $byId = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $byId[(int) $row['property_id']] = $row;
        }

        foreach ($properties as $i => $property) {
            $pid = (int) ($property['id'] ?? 0);
            if ($pid <= 0 || !isset($byId[$pid])) {
                continue;
            }
            $tr = $byId[$pid];
            foreach (['title', 'slug', 'description', 'description_long'] as $field) {
                $val = trim((string) ($tr[$field] ?? ''));
                if ($val !== '') {
                    $properties[$i][$field] = $val;
                }
            }
        }

        return $properties;
    }
}

if (!function_exists('lh_property_locale_slug')) {
    function lh_property_locale_slug(array $property, string $locale): string
    {
        if ($locale === lh_default_locale()) {
            return (string) ($property['slug'] ?? '');
        }

        try {
            $pdo = getPDO();
            $pid = (int) ($property['id'] ?? 0);
            if ($pid <= 0) {
                return (string) ($property['slug'] ?? '');
            }
            $tr = lh_property_fetch_translation($pdo, $pid, $locale);

            return $tr !== null && trim((string) ($tr['slug'] ?? '')) !== ''
                ? (string) $tr['slug']
                : (string) ($property['slug'] ?? '');
        } catch (Throwable) {
            return (string) ($property['slug'] ?? '');
        }
    }
}

if (!function_exists('lh_property_locale_alternate_urls')) {
    /**
     * @param array<string, mixed> $property
     * @return array<string, string> hreflang => absolute URL
     */
    function lh_property_locale_alternate_urls(array $property, ?PDO $pdo = null): array
    {
        $urls = [];
        foreach (lh_supported_locales() as $locale) {
            $slug = lh_property_locale_slug($property, $locale);
            $q = $slug !== ''
                ? http_build_query(['slug' => $slug])
                : http_build_query(['id' => (int) ($property['id'] ?? 0)]);
            $urls[lh_locale_hreflang($locale)] = lh_absolute_locale_url(
                'property-details.php?' . $q,
                $locale
            );
        }

        return $urls;
    }
}

if (!function_exists('lh_property_resolve_by_slug')) {
    /**
     * Find property by RO slug or translated slug for current locale.
     *
     * @return array<string, mixed>|null
     */
    function lh_property_resolve_by_slug(PDO $pdo, string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM properties WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            return lh_property_apply_locale($row, $pdo);
        }

        $locale = lh_current_locale();
        if ($locale === lh_default_locale() || !lh_property_translation_table_exists($pdo)) {
            return null;
        }

        $stmtTr = $pdo->prepare(
            'SELECT p.* FROM properties p
             INNER JOIN property_translations t ON t.property_id = p.id
             WHERE t.slug = :slug AND t.locale = :locale AND p.is_active = 1
             LIMIT 1'
        );
        $stmtTr->execute([':slug' => $slug, ':locale' => $locale]);
        $row = $stmtTr->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? lh_property_apply_locale($row, $pdo, $locale) : null;
    }
}

if (!function_exists('lh_property_details_url')) {
    /** @param array<string, mixed> $property */
    function lh_property_details_url(array $property, ?string $locale = null): string
    {
        $locale = $locale ?? lh_current_locale();
        $slug = lh_property_locale_slug($property, $locale);
        if ($slug !== '') {
            return lh_locale_url('property-details.php?' . http_build_query(['slug' => $slug]), $locale);
        }

        $id = (int) ($property['id'] ?? 0);

        return lh_locale_url('property-details.php?' . http_build_query(['id' => $id]), $locale);
    }
}
