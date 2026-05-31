<?php

declare(strict_types=1);

/**
 * robots.txt dinamic (mapare la /robots.txt prin server sau acces direct la robots.php).
 */

require_once __DIR__ . '/config.php';

if (!headers_sent()) {
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Robots-Tag: noindex');
}

$prefix = SITE_BASE_PATH;
if ($prefix !== '' && isset($prefix[0]) && $prefix[0] !== '/') {
    $prefix = '/' . $prefix;
}
$prefix = $prefix === '/' ? '' : $prefix;
$adminPath = ($prefix === '' ? '' : rtrim($prefix, '/')) . '/admin/';

echo "User-agent: *\n";
echo "Allow: /\n";
echo 'Disallow: ' . $adminPath . "\n\n";
echo 'Sitemap: ' . lh_absolute_url('sitemap.php') . "\n";
