<?php

declare(strict_types=1);

/**
 * Bootstrap for /en/*.php and /ru/*.php stub files (works without mod_rewrite).
 */

$scriptPath = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
$localeDir = basename(dirname($scriptPath));

if ($localeDir === 'en' || $localeDir === 'ru') {
    $_SERVER['REDIRECT_LH_LANG'] = $localeDir;
}

$rootScript = dirname(dirname($scriptPath)) . DIRECTORY_SEPARATOR . basename($scriptPath);

if (!is_readable($rootScript)) {
    http_response_code(404);
    exit('Not found');
}

require $rootScript;
