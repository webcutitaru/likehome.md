<?php

declare(strict_types=1);

if (!function_exists('lh_env')) {
    /**
     * @return non-falsy-string when default is non-empty
     */
    function lh_env(string $key, string $default = ''): string
    {
        $v = getenv($key);
        if ($v === false && isset($_ENV[$key]) && is_string($_ENV[$key])) {
            $v = $_ENV[$key];
        }
        if ($v !== false && $v !== '') {
            return $v;
        }

        return $default;
    }
}

if (!function_exists('lh_load_dotenv')) {
    function lh_load_dotenv(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            if ($k === '') {
                continue;
            }
            $v = trim($v);
            if (strlen($v) >= 2) {
                $q = $v[0];
                if (($q === '"' || $q === "'") && substr($v, -1) === $q) {
                    $v = substr($v, 1, -1);
                }
            }
            putenv("{$k}={$v}");
            $_ENV[$k] = $v;
        }
    }
}

/**
 * Locate .env: LH_ENV_PATH (server) → project root → parent dir (e.g. /home/likehome/.env on cPanel).
 */
if (!function_exists('lh_find_env_file')) {
    function lh_find_env_file(): ?string
    {
        $root = __DIR__;
        $candidates = [];
        $explicit = getenv('LH_ENV_PATH');
        if (is_string($explicit) && $explicit !== '') {
            $candidates[] = rtrim($explicit, '/');
        }
        $candidates[] = $root;
        $candidates[] = dirname($root);

        foreach ($candidates as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $envFile = $dir . '/.env';
            if (is_readable($envFile)) {
                return $envFile;
            }
        }

        return null;
    }
}

if (!function_exists('lh_load_env')) {
    function lh_load_env(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $envFile = lh_find_env_file();
        if ($envFile === null) {
            return;
        }

        lh_load_dotenv($envFile);
    }
}

lh_load_env();

$lhVendorAutoload = __DIR__ . '/vendor/autoload.php';
if (is_readable($lhVendorAutoload)) {
    require_once $lhVendorAutoload;
}

$appEnv = strtolower(lh_env('APP_ENV', 'local'));
$isProduction = ($appEnv === 'production');
$appDebugDefault = $isProduction ? '0' : '1';
$appDebug = in_array(strtolower(lh_env('APP_DEBUG', $appDebugDefault)), ['1', 'true', 'yes', 'on'], true);

error_reporting(E_ALL);
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('log_errors', '1');
$errorLogPath = lh_env('ERROR_LOG_PATH');
if ($errorLogPath !== '') {
    ini_set('error_log', $errorLogPath);
}

$dbHost = lh_env('DB_HOST', $isProduction ? '' : 'localhost');
$dbName = lh_env('DB_NAME', $isProduction ? '' : 'likehome2_db');
$dbUser = lh_env('DB_USER', $isProduction ? '' : 'root');
$dbPass = lh_env('DB_PASS');
if ($dbPass === '') {
    if ($isProduction) {
        throw new RuntimeException('DB_PASS must be set in the environment when APP_ENV=production.');
    }
    $dbPass = 'root';
}

if ($isProduction && ($dbHost === '' || $dbName === '' || $dbUser === '')) {
    throw new RuntimeException('DB_HOST, DB_NAME, and DB_USER must be set when APP_ENV=production.');
}

define('DB_HOST', $dbHost);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_CHARSET', lh_env('DB_CHARSET', 'utf8mb4'));

$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$dbname = DB_NAME;

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die('Connection failed: ' . mysqli_connect_error());
}

if (!mysqli_set_charset($conn, DB_CHARSET)) {
    die('Connection charset failed: ' . mysqli_error($conn));
}

$lhIsCli = PHP_SAPI === 'cli';

if (!$lhIsCli && session_status() === PHP_SESSION_NONE) {
    $secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/admin_activity_log.php';
require_once __DIR__ . '/includes/mysqli_stmt_compat.php';

$siteBaseRaw = lh_env('SITE_BASE_PATH', '/likehome2');
if ($siteBaseRaw === '' || $siteBaseRaw === '/') {
    define('SITE_BASE_PATH', '');
} else {
    define('SITE_BASE_PATH', rtrim($siteBaseRaw, '/'));
}

if (!function_exists('lh_public_url')) {
    function lh_public_url(string $path = ''): string
    {
        $path = trim($path);
        $base = SITE_BASE_PATH;
        if ($path === '' || $path === '/') {
            return $base === '' ? '/' : $base . '/';
        }
        if (isset($path[0]) && $path[0] === '#') {
            return ($base === '' ? '/' : $base . '/') . $path;
        }

        return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
    }
}

require_once __DIR__ . '/includes/seo.php';

require_once __DIR__ . '/includes/upload_image.php';
require_once __DIR__ . '/includes/currency.php';

require_once __DIR__ . '/includes/security_headers.php';
if (!$lhIsCli) {
    lh_send_security_headers();
}

date_default_timezone_set(lh_env('APP_TIMEZONE', 'Europe/Chisinau'));

if (!defined('ADMIN_NOTIFICATION_EMAIL')) {
    define('ADMIN_NOTIFICATION_EMAIL', lh_env('ADMIN_NOTIFICATION_EMAIL'));
}

if (!defined('TELEGRAM_BOT_TOKEN')) {
    define('TELEGRAM_BOT_TOKEN', lh_env('TELEGRAM_BOT_TOKEN'));
}

if (!defined('TELEGRAM_CHAT_ID')) {
    define('TELEGRAM_CHAT_ID', lh_env('TELEGRAM_CHAT_ID'));
}

if (!defined('BOOKING_MAIL_FROM')) {
    define('BOOKING_MAIL_FROM', lh_env('BOOKING_MAIL_FROM'));
}

$lhMailjetKey    = trim(lh_env('MAILJET_API_KEY'));
$lhMailjetSecret = trim(lh_env('MAILJET_API_SECRET'));
$lhMailjetReady  = $lhMailjetKey !== ''
    && $lhMailjetSecret !== ''
    && class_exists(\Mailjet\Client::class);

if (!defined('MAILJET_API_KEY')) {
    define('MAILJET_API_KEY', $lhMailjetKey);
}
if (!defined('MAILJET_API_SECRET')) {
    define('MAILJET_API_SECRET', $lhMailjetSecret);
}
if (!defined('MAILJET_READY')) {
    define('MAILJET_READY', $lhMailjetReady);
}

// SMTP disabled — kept for backward compatibility so any MAIL_SMTP_READY guards don't crash.
if (!defined('MAIL_SMTP_READY')) {
    define('MAIL_SMTP_READY', false);
}

/** Multiline plain text: guest-facing coordinator phones (confirmation email). Override in .env BOOKING_GUEST_SUPPORT_PHONES */
if (!defined('BOOKING_GUEST_SUPPORT_PHONES')) {
    define('BOOKING_GUEST_SUPPORT_PHONES', lh_env('BOOKING_GUEST_SUPPORT_PHONES', "Andrei — +373 69 397 372\nAurel — +373 69 111 427"));
}

function getPDO(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    return $pdo;
}
