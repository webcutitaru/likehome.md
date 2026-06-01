<?php

declare(strict_types=1);

/**
 * Sitemap XML pentru Google Search Console (pagini publice + proprietăți active, toate limbile).
 */

require_once __DIR__ . '/config.php';

if (!headers_sent()) {
    header('Content-Type: application/xml; charset=UTF-8');
    header('X-Robots-Tag: noindex');
}

$esc = static function (string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
};

$now = gmdate('Y-m-d');

$static = [
    ['path' => '', 'priority' => '1.0', 'changefreq' => 'daily'],
    ['path' => 'properties.php', 'priority' => '0.9', 'changefreq' => 'daily'],
    ['path' => 'about.php', 'priority' => '0.6', 'changefreq' => 'monthly'],
    ['path' => 'contact.php', 'priority' => '0.7', 'changefreq' => 'monthly'],
    ['path' => 'faq.php', 'priority' => '0.6', 'changefreq' => 'monthly'],
    ['path' => 'terms.php', 'priority' => '0.3', 'changefreq' => 'yearly'],
    ['path' => 'privacy.php', 'priority' => '0.3', 'changefreq' => 'yearly'],
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach (lh_supported_locales() as $locale) {
    foreach ($static as $row) {
        $path = $row['path'];
        $loc = $path === ''
            ? lh_absolute_locale_url('', $locale)
            : lh_absolute_locale_url($path, $locale);
        echo '  <url>';
        echo '<loc>' . $esc($loc) . '</loc>';
        echo '<lastmod>' . $esc($now) . '</lastmod>';
        echo '<changefreq>' . $esc($row['changefreq']) . '</changefreq>';
        echo '<priority>' . $esc($row['priority']) . '</priority>';
        echo "</url>\n";
    }
}

try {
    $pdo = getPDO();
    $stmt = $pdo->query(
        'SELECT * FROM properties WHERE is_active = 1 ORDER BY id ASC'
    );
    if ($stmt) {
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $prop) {
            foreach (lh_supported_locales() as $locale) {
                $localized = lh_property_apply_locale($prop, $pdo, $locale);
                $slug = trim((string) ($localized['slug'] ?? ''));
                $q = $slug !== ''
                    ? http_build_query(['slug' => $slug])
                    : http_build_query(['id' => (int) ($prop['id'] ?? 0)]);
                $loc = lh_absolute_locale_url('property-details.php?' . $q, $locale);
                $created = $prop['created_at'] ?? null;
                $lastmod = $now;
                if ($created) {
                    $ts = strtotime((string) $created);
                    if ($ts !== false) {
                        $lastmod = gmdate('Y-m-d', $ts);
                    }
                }
                echo '  <url>';
                echo '<loc>' . $esc($loc) . '</loc>';
                echo '<lastmod>' . $esc($lastmod) . '</lastmod>';
                echo '<changefreq>weekly</changefreq>';
                echo '<priority>0.8</priority>';
                echo "</url>\n";
            }
        }
    }
} catch (Throwable $e) {
    // Sitemap-ul rămâne cu paginile statice.
}

echo "</urlset>\n";
