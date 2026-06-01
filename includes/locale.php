<?php

declare(strict_types=1);

/**
 * Locale detection, translation helper, and locale-prefixed public URLs.
 *
 * RO = default (no URL prefix). EN/RU = /en/… and /ru/… after SITE_BASE_PATH.
 */

if (!function_exists('lh_supported_locales')) {
    /** @return list<string> */
    function lh_supported_locales(): array
    {
        $raw = lh_env('APP_LOCALES', 'ro,en,ru');
        $parts = array_values(array_filter(array_map('trim', explode(',', $raw))));

        return $parts !== [] ? $parts : ['ro', 'en', 'ru'];
    }
}

if (!function_exists('lh_default_locale')) {
    function lh_default_locale(): string
    {
        $def = lh_env('APP_DEFAULT_LOCALE', 'ro');

        return in_array($def, lh_supported_locales(), true) ? $def : 'ro';
    }
}

if (!function_exists('lh_locale_prefix_codes')) {
    /** @return list<string> */
    function lh_locale_prefix_codes(): array
    {
        $def = lh_default_locale();

        return array_values(array_filter(
            lh_supported_locales(),
            static fn (string $loc): bool => $loc !== $def
        ));
    }
}

if (!function_exists('lh_is_locale_exempt_script')) {
    function lh_is_locale_exempt_script(): bool
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

        return (bool) preg_match('#/(admin|ajax|cron|ical|vendor)(/|$)#', $script);
    }
}

if (!function_exists('lh_uri_path_after_site_base')) {
    function lh_uri_path_after_site_base(): string
    {
        $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if (!is_string($uri) || $uri === '') {
            $uri = '/';
        }

        $base = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : '';
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base)) ?: '/';
        }

        return '/' . ltrim($uri, '/');
    }
}

if (!function_exists('lh_detect_locale_from_request')) {
    function lh_detect_locale_from_request(): string
    {
        if (lh_is_locale_exempt_script()) {
            return lh_default_locale();
        }

        $redirectLang = $_SERVER['REDIRECT_LH_LANG'] ?? null;
        if (is_string($redirectLang) && in_array($redirectLang, lh_locale_prefix_codes(), true)) {
            return $redirectLang;
        }

        $path = lh_uri_path_after_site_base();
        $codes = lh_locale_prefix_codes();
        if ($codes !== []) {
            $prefixPattern = implode('|', array_map('preg_quote', $codes));
            if (preg_match('#^/(' . $prefixPattern . ')(/|$)#', $path, $m)) {
                return $m[1];
            }
        }

        // Fără prefix în URL => limba implicită (RO). URL-ul bate cookie-ul.
        return lh_default_locale();
    }
}

if (!function_exists('lh_current_locale')) {
    function lh_current_locale(): string
    {
        static $locale = null;
        if ($locale !== null) {
            return $locale;
        }

        $locale = lh_detect_locale_from_request();

        if (
            PHP_SAPI !== 'cli'
            && !lh_is_locale_exempt_script()
            && !headers_sent()
        ) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
            setcookie('lh_locale', $locale, [
                'expires' => time() + 86400 * 365,
                'path' => '/',
                'secure' => $secure,
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }

        return $locale;
    }
}

if (!function_exists('lh_translation_file_path')) {
    function lh_translation_file_path(string $locale): ?string
    {
        $path = dirname(__DIR__) . '/lang/' . $locale . '.php';
        if (is_readable($path)) {
            return $path;
        }

        return null;
    }
}

if (!function_exists('lh_translation_strings')) {
    /** @return array<string, string> */
    function lh_translation_strings(?string $locale = null): array
    {
        static $cache = [];
        $locale = $locale ?? lh_current_locale();

        if (isset($cache[$locale])) {
            return $cache[$locale];
        }

        $strings = [];
        $file = lh_translation_file_path($locale);
        if ($file !== null) {
            /** @var mixed $loaded */
            $loaded = require $file;
            if (is_array($loaded)) {
                $strings = $loaded;
            }
        }

        $extrasPath = dirname(__DIR__) . '/lang/extras.' . $locale . '.php';
        if (is_readable($extrasPath)) {
            /** @var mixed $extras */
            $extras = require $extrasPath;
            if (is_array($extras)) {
                $strings = array_merge($strings, $extras);
            }
        }

        if ($locale !== lh_default_locale()) {
            $fallbackFile = lh_translation_file_path(lh_default_locale());
            if ($fallbackFile !== null) {
                /** @var mixed $fallback */
                $fallback = require $fallbackFile;
                if (is_array($fallback)) {
                    $strings = array_merge($fallback, $strings);
                }
            }
        }

        $cache[$locale] = $strings;

        return $strings;
    }
}

if (!function_exists('lh_resolve_request_locale')) {
    function lh_resolve_request_locale(): string
    {
        $fromPost = trim((string) ($_POST['locale'] ?? ''));
        if ($fromPost !== '' && in_array($fromPost, lh_supported_locales(), true)) {
            return $fromPost;
        }

        return lh_current_locale();
    }
}

if (!function_exists('lh_translate')) {
    /**
     * @param array<string, string|int|float> $replace
     */
    function lh_translate(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? lh_current_locale();
        $strings = lh_translation_strings($locale);
        $text = $strings[$key] ?? $key;

        foreach ($replace as $name => $value) {
            $text = str_replace(':' . $name, (string) $value, $text);
        }

        return $text;
    }
}

if (!function_exists('__')) {
    /**
     * @param array<string, string|int|float> $replace
     */
    function __(string $key, array $replace = []): string
    {
        return lh_translate($key, $replace);
    }
}

if (!function_exists('lh_locale_url')) {
    function lh_locale_url(string $path = '', ?string $locale = null): string
    {
        $locale = $locale ?? lh_current_locale();

        if ($locale === lh_default_locale() || lh_is_locale_exempt_script()) {
            return lh_public_url($path);
        }

        $path = trim($path);
        if ($path === '' || $path === '/') {
            return lh_public_url($locale . '/');
        }

        if (isset($path[0]) && $path[0] === '#') {
            return lh_public_url($locale . '/' . $path);
        }

        return lh_public_url($locale . '/' . ltrim($path, '/'));
    }
}

if (!function_exists('lh_absolute_locale_url')) {
    function lh_absolute_locale_url(string $path = '', ?string $locale = null): string
    {
        $rel = lh_locale_url($path, $locale);
        if ($rel === '' || $rel === '/') {
            return lh_public_site_origin() . '/';
        }

        return lh_public_site_origin() . $rel;
    }
}

if (!function_exists('lh_locale_html_lang')) {
    function lh_locale_html_lang(): string
    {
        return lh_current_locale();
    }
}

if (!function_exists('lh_locale_og_tag')) {
    function lh_locale_og_tag(): string
    {
        return match (lh_current_locale()) {
            'en' => 'en_US',
            'ru' => 'ru_RU',
            default => 'ro_MD',
        };
    }
}

if (!function_exists('lh_locale_hreflang')) {
    function lh_locale_hreflang(string $locale): string
    {
        return match ($locale) {
            'en' => 'en',
            'ru' => 'ru',
            default => 'ro',
        };
    }
}

if (!function_exists('lh_locale_current_request_path')) {
    function lh_locale_current_request_path(): string
    {
        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
        $query = (string) ($_SERVER['QUERY_STRING'] ?? '');

        return $script . ($query !== '' ? '?' . $query : '');
    }
}

if (!function_exists('lh_locale_flag')) {
    function lh_locale_flag(string $locale): string
    {
        return match ($locale) {
            'en' => '🇬🇧',
            'ru' => '🇷🇺',
            default => '🇲🇩',
        };
    }
}

if (!function_exists('lh_locale_switcher_items')) {
    /**
     * @return list<array{locale: string, label: string, name: string, flag: string, href: string, current: bool}>
     */
    function lh_locale_switcher_items(): array
    {
        if (lh_is_locale_exempt_script()) {
            return [];
        }

        $path = lh_locale_current_request_path();
        $items = [];

        foreach (lh_supported_locales() as $locale) {
            $items[] = [
                'locale' => $locale,
                'label' => match ($locale) {
                    'en' => 'EN',
                    'ru' => 'RU',
                    default => 'RO',
                },
                'name' => __('lang.' . $locale),
                'flag' => lh_locale_flag($locale),
                'href' => lh_locale_url($path, $locale),
                'current' => $locale === lh_current_locale(),
            ];
        }

        return $items;
    }
}

if (!function_exists('lh_locale_alternate_urls')) {
    /**
     * @return array<string, string> hreflang code => absolute URL
     */
    function lh_locale_alternate_urls(): array
    {
        if (lh_is_locale_exempt_script()) {
            return [];
        }

        $path = lh_locale_current_request_path();
        $urls = [];

        foreach (lh_supported_locales() as $locale) {
            $urls[lh_locale_hreflang($locale)] = lh_absolute_locale_url($path, $locale);
        }

        return $urls;
    }
}

lh_current_locale();
