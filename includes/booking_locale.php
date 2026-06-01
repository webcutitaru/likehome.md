<?php

declare(strict_types=1);

if (!function_exists('lh_bookings_has_locale_column')) {
    function lh_bookings_has_locale_column(PDO $pdo): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'locale'");
            $has = $stmt !== false && $stmt->fetch() !== false;
        } catch (Throwable) {
            $has = false;
        }

        return $has;
    }
}
