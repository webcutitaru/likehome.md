<?php

declare(strict_types=1);

require_once __DIR__ . '/property_i18n.php';

if (!function_exists('lh_property_slug_from_title')) {
    function lh_property_slug_from_title(string $title, int $propertyId = 0): string
    {
        $base = strtolower(trim(preg_replace(
            '/[^A-Za-z0-9-]+/',
            '-',
            (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title)
        ), '-'));

        return $base !== '' ? $base : ('property-' . ($propertyId > 0 ? $propertyId : time()));
    }
}

if (!function_exists('lh_property_translations_load')) {
    /**
     * @return array<string, array{title: string, slug: string, description: string, description_long: string}>
     */
    function lh_property_translations_load(PDO $pdo, int $propertyId): array
    {
        $out = [];
        foreach (lh_property_translation_locales() as $locale) {
            $out[$locale] = [
                'title' => '',
                'slug' => '',
                'description' => '',
                'description_long' => '',
            ];
        }

        if ($propertyId <= 0 || !lh_property_translation_table_exists($pdo)) {
            return $out;
        }

        $stmt = $pdo->prepare(
            'SELECT locale, title, slug, description, description_long
             FROM property_translations WHERE property_id = :pid'
        );
        $stmt->execute([':pid' => $propertyId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $loc = (string) ($row['locale'] ?? '');
            if (!isset($out[$loc])) {
                continue;
            }
            $out[$loc] = [
                'title' => (string) ($row['title'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'description_long' => (string) ($row['description_long'] ?? ''),
            ];
        }

        return $out;
    }
}

if (!function_exists('lh_property_translations_save_from_post')) {
    /**
     * @param array<string, mixed> $post
     * @return string|null Error message or null on success
     */
    function lh_property_translations_save_from_post(PDO $pdo, int $propertyId, array $post): ?string
    {
        if ($propertyId <= 0 || !lh_property_translation_table_exists($pdo)) {
            return null;
        }

        foreach (lh_property_translation_locales() as $locale) {
            $prefix = 'tr_' . $locale . '_';
            $title = trim((string) ($post[$prefix . 'title'] ?? ''));
            $slug = trim((string) ($post[$prefix . 'slug'] ?? ''));
            $descLong = trim((string) ($post[$prefix . 'description_long'] ?? ''));
            $descShort = mb_substr($descLong, 0, 220);

            $hasAny = $title !== '' || $slug !== '' || $descLong !== '';

            if (!$hasAny) {
                $del = $pdo->prepare(
                    'DELETE FROM property_translations WHERE property_id = :pid AND locale = :locale'
                );
                $del->execute([':pid' => $propertyId, ':locale' => $locale]);
                continue;
            }

            if ($title === '') {
                return 'Titlul (' . strtoupper($locale) . ') este obligatoriu când există traducere.';
            }

            if ($slug === '') {
                $slug = lh_property_slug_from_title($title, $propertyId);
            }

            $slugCheck = $pdo->prepare(
                'SELECT property_id FROM property_translations WHERE slug = :slug AND locale = :locale AND property_id != :pid LIMIT 1'
            );
            $slugCheck->execute([':slug' => $slug, ':locale' => $locale, ':pid' => $propertyId]);
            if ($slugCheck->fetch()) {
                return 'Slug-ul (' . strtoupper($locale) . ') „' . $slug . '” este deja folosit.';
            }

            $roSlugCheck = $pdo->prepare(
                'SELECT id FROM properties WHERE slug = :slug AND id != :pid LIMIT 1'
            );
            $roSlugCheck->execute([':slug' => $slug, ':pid' => $propertyId]);
            if ($roSlugCheck->fetch()) {
                return 'Slug-ul (' . strtoupper($locale) . ') intră în conflict cu o proprietate RO.';
            }

            $upsert = $pdo->prepare(
                'INSERT INTO property_translations (property_id, locale, title, slug, description, description_long)
                 VALUES (:pid, :locale, :title, :slug, :description, :description_long)
                 ON DUPLICATE KEY UPDATE
                    title = VALUES(title),
                    slug = VALUES(slug),
                    description = VALUES(description),
                    description_long = VALUES(description_long)'
            );
            $upsert->execute([
                ':pid' => $propertyId,
                ':locale' => $locale,
                ':title' => $title,
                ':slug' => $slug,
                ':description' => $descShort,
                ':description_long' => $descLong,
            ]);
        }

        return null;
    }
}
