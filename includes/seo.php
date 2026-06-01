<?php

declare(strict_types=1);

/**
 * SEO helpers: canonical base URL, meta text cleanup, current-request canonical fallback.
 *
 * Setează PUBLIC_SITE_URL în .env dacă difere de producție (implicit https://www.likehome.md).
 */

if (!function_exists('lh_public_site_origin')) {
    /**
     * Canonical site origin without trailing slash (e.g. https://www.likehome.md).
     */
    function lh_public_site_origin(): string
    {
        return rtrim(lh_env('PUBLIC_SITE_URL', 'https://www.likehome.md'), '/');
    }
}

if (!function_exists('lh_absolute_url')) {
    /**
     * Absolute URL for a path or script+query string understood by lh_public_url().
     */
    function lh_absolute_url(string $path = ''): string
    {
        $rel = lh_public_url($path);
        if ($rel === '' || $rel === '/') {
            return lh_public_site_origin() . '/';
        }

        return lh_public_site_origin() . $rel;
    }
}

if (!function_exists('lh_seo_meta_plain')) {
    /**
     * Strip tags / normalize whitespace and clamp length for meta descriptions.
     */
    function lh_seo_meta_plain(string $text, int $maxLen = 158): string
    {
        $t = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace('/\s+/u', ' ', trim($t)) ?? '';
        if (mb_strlen($t, 'UTF-8') <= $maxLen) {
            return $t;
        }

        $cut = mb_substr($t, 0, $maxLen - 1, 'UTF-8');
        $cut = preg_replace('/\s+\S*$/u', '', $cut) ?? $cut;

        return $cut . '…';
    }
}

if (!function_exists('lh_seo_request_uri_path')) {
    /**
     * Path + query from the current request (no fragment). For fallback canonical only.
     */
    function lh_seo_request_uri_path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $parts = explode('#', $uri, 2);

        return $parts[0] ?? '/';
    }
}

if (!function_exists('lh_seo_fallback_canonical')) {
    /**
     * Best-effort canonical from current request (locale-aware path).
     */
    function lh_seo_fallback_canonical(): string
    {
        if (function_exists('lh_locale_current_request_path') && !lh_is_locale_exempt_script()) {
            return lh_absolute_locale_url(lh_locale_current_request_path());
        }

        $path = lh_seo_request_uri_path();

        return lh_public_site_origin() . $path;
    }
}

if (!function_exists('lh_absolute_href')) {
    /**
     * Absolute URL from a root-relative href (e.g. return value of lh_public_url / lh_property_image_url)
     * or a relative script path passed to lh_public_url().
     */
    function lh_absolute_href(string $publicPath): string
    {
        $p = $publicPath;
        if ($p !== '' && $p[0] === '/') {
            return lh_public_site_origin() . $p;
        }

        return lh_absolute_url($p);
    }
}
