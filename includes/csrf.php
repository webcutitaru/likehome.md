<?php

declare(strict_types=1);

if (!function_exists('lh_csrf_token')) {
    function lh_csrf_token(): string
    {
        if (empty($_SESSION['_lh_csrf_token'])) {
            $_SESSION['_lh_csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['_lh_csrf_token'];
    }

    function lh_csrf_regenerate_token(): void
    {
        $_SESSION['_lh_csrf_token'] = bin2hex(random_bytes(32));
    }

    function lh_csrf_verify_post(): bool
    {
        $sent = $_POST['csrf_token'] ?? '';
        if (!is_string($sent) || $sent === '') {
            return false;
        }
        $expected = $_SESSION['_lh_csrf_token'] ?? '';

        return $expected !== '' && hash_equals($expected, $sent);
    }

    function lh_csrf_field(): void
    {
        echo '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(lh_csrf_token(), ENT_QUOTES, 'UTF-8')
            . '">';
    }
}

if (!function_exists('lh_ini_bytes')) {
    /**
     * Parse PHP ini size values (e.g. "8M", "512K") to bytes.
     */
    function lh_ini_bytes(string $iniKey): int
    {
        $val = trim((string) ini_get($iniKey));
        if ($val === '') {
            return 0;
        }
        $last = strtolower($val[strlen($val) - 1]);
        if (in_array($last, ['g', 'm', 'k'], true)) {
            $num = (float) substr($val, 0, -1);
            if ($last === 'g') {
                $num *= 1024 * 1024 * 1024;
            } elseif ($last === 'm') {
                $num *= 1024 * 1024;
            } else {
                $num *= 1024;
            }

            return max(0, (int) round($num));
        }

        return max(0, (int) round((float) $val));
    }
}

if (!function_exists('lh_post_exceeds_post_max_size')) {
    /**
     * True when the raw POST body is larger than post_max_size. In that case PHP
     * drops all of $_POST/$_FILES, so CSRF tokens disappear and uploads appear as "invalid session".
     */
    function lh_post_exceeds_post_max_size(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return false;
        }
        if (!isset($_SERVER['CONTENT_LENGTH'])) {
            return false;
        }
        $len = (int) $_SERVER['CONTENT_LENGTH'];
        if ($len <= 0) {
            return false;
        }
        $max = lh_ini_bytes('post_max_size');

        return $max > 0 && $len > $max;
    }
}
