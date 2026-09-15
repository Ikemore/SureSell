<?php
/**
 * Nokware Market — Core Configuration
 * Keep this file outside the public web root in a real production deploy.
 * For XAMPP local use, it stays inside htdocs but is never directly
 * requested by the browser (no routes point at it).
 */

// ---- Environment ----------------------------------------------------
function loadDotEnvFile(): void
{
    $envFile = __DIR__ . '/../.env';

    if (!is_file($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $trimmed, 2), 2, '');

        $key = trim($key);
        $value = trim($value);

        if ($value !== '') {
            $value = preg_replace('/^"(.*)"$/', '$1', $value);
            $value = preg_replace('/^\'(.*)\'$/', '$1', $value);
        }

        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

loadDotEnvFile();

function env(string $key, $default = null)
{
    $value = getenv($key);
    if ($value !== false && $value !== null) {
        return $value;
    }

    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }

    if (isset($_SERVER[$key])) {
        return $_SERVER[$key];
    }

    return $default;
}

define('APP_NAME', env('APP_NAME', 'SureSell'));
define('APP_TAGLINE', env('APP_TAGLINE', 'Buy smart. Sell sure.'));
define('APP_URL', env('APP_URL', 'http://localhost/Nokware/nokware')); // XAMPP local path
define('APP_ENV', strtolower((string) env('APP_ENV', 'development'))); // set to 'production' when deploying live
define('MAIL_FROM', env('MAIL_FROM', 'no-reply@suresell.local')); // replace with a verified sender before production
define('ADMIN_EMAIL', env('ADMIN_EMAIL', 'apponly79@gmail.com')); // sole account allowed to access the administration area

// ---- Database ---------------------------------------------------------
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', env('DB_PORT', '3307'));
define('DB_NAME', env('DB_NAME', 'nokware_market'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));          // set your MySQL root password if you have one
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// ---- Security ---------------------------------------------------------
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);
define('OTP_EXPIRY_MINUTES', 10);
define('SESSION_LIFETIME', 60 * 60 * 2); // 2 hours

// Upload limits
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('MAX_UPLOAD_BYTES', 4 * 1024 * 1024); // 4MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// Error display — never show raw errors to users in production
if (APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    // Avoid stale PHP page responses while the local site is being developed.
    header('Cache-Control: no-store, max-age=0');
}

// ---- Hardened session configuration -----------------------------------
// Must run before session_start(). Included by every entry-point script.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    // cookie_secure should be 1 once you're serving over HTTPS
    ini_set('session.cookie_secure', (APP_ENV === 'production') ? '1' : '0');
    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (APP_ENV === 'production'),
    ]);
    session_start();
}

// Basic security headers on every request
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; script-src 'self'; font-src 'self' data: https://fonts.gstatic.com;");

date_default_timezone_set('Africa/Accra');
