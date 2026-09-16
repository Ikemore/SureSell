<?php
/**
 * Shared security + utility helpers used across every page.
 */
require_once __DIR__ . '/../config/db.php';

// =========================================================
// OUTPUT ESCAPING (prevents stored/reflected XSS)
// =========================================================
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// =========================================================
// CSRF PROTECTION
// =========================================================
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Security check failed. Please go back and try again.');
    }
}

// =========================================================
// INPUT VALIDATION
// =========================================================
function clean_str(string $value): string
{
    return trim(strip_tags($value));
}

/** @return list<string> Ghana's official administrative regions. */
function ghana_regions(): array
{
    return [
        'Ahafo', 'Ashanti', 'Bono', 'Bono East', 'Central', 'Eastern',
        'Greater Accra', 'North East', 'Northern', 'Oti', 'Savannah',
        'Upper East', 'Upper West', 'Volta', 'Western', 'Western North',
    ];
}

function valid_ghana_region(string $region): bool
{
    return in_array($region, ghana_regions(), true);
}

/**
 * Delivers a password-reset code by email. Local development keeps the code
 * in the session so the complete reset journey can be tested without mail
 * infrastructure; production requires a configured PHP mail transport.
 */
function send_password_reset_code(string $email, string $code): bool
{
    if (APP_ENV !== 'production') {
        $_SESSION['demo_password_reset_code'] = $code;
        return true;
    }

    $subject = 'Your SureSell password reset code';
    $message = "Use this code to reset your SureSell password: {$code}\n\nThis code expires in " . OTP_EXPIRY_MINUTES . " minutes. If you did not request it, you can ignore this email.";
    $headers = 'From: ' . MAIL_FROM . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
    return @mail($email, $subject, $message, $headers);
}

function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Accepts Ghanaian-style numbers: 0XXXXXXXXX or +233XXXXXXXXX
function valid_phone(string $phone): bool
{
    return (bool) preg_match('/^(0\d{9}|\+233\d{9})$/', $phone);
}

function normalize_phone(string $phone): string
{
    $phone = preg_replace('/\s+/', '', $phone);
    if (str_starts_with($phone, '+233')) {
        $phone = '0' . substr($phone, 4);
    }
    return $phone;
}

function valid_password(string $password): bool
{
    // Minimum length + at least one letter and one number
    return strlen($password) >= PASSWORD_MIN_LENGTH
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/\d/', $password);
}

// =========================================================
// AUTH HELPERS
// =========================================================
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $stmt = db()->prepare('SELECT id, full_name, business_name, email, phone, role, town, bio,
                                   avatar_path, phone_verified, email_verified, id_verified, trust_score, region,
                                   deals_completed, status
                            FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $cache = $stmt->fetch() ?: null;
    return $cache;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
    if ($user['status'] !== 'active') {
        session_destroy();
        header('Location: ' . APP_URL . '/login.php?suspended=1');
        exit;
    }
    return $user;
}

function is_platform_admin(?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user !== null
        && $user['role'] === 'admin'
        && strcasecmp($user['email'], ADMIN_EMAIL) === 0;
}

function require_admin(): array
{
    $user = require_login();
    if (!is_platform_admin($user)) {
        http_response_code(403);
        die('Access denied.');
    }
    return $user;
}

function regenerate_session(): void
{
    session_regenerate_id(true);
}

// =========================================================
// RATE LIMITING (login brute-force protection)
// =========================================================
function is_account_locked(array $user): bool
{
    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        return true;
    }
    return false;
}

function register_failed_login(int $userId): void
{
    $stmt = db()->prepare('SELECT failed_logins FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $attempts = (int) $stmt->fetchColumn() + 1;

    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60);
        $upd = db()->prepare('UPDATE users SET failed_logins = 0, locked_until = ? WHERE id = ?');
        $upd->execute([$lockUntil, $userId]);
    } else {
        $upd = db()->prepare('UPDATE users SET failed_logins = ? WHERE id = ?');
        $upd->execute([$attempts, $userId]);
    }
}

function clear_failed_logins(int $userId): void
{
    $stmt = db()->prepare('UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = ?');
    $stmt->execute([$userId]);
}

function log_login_attempt(?int $userId, string $emailTried, bool $success): void
{
    $stmt = db()->prepare('INSERT INTO login_audit (user_id, email_tried, success, ip_address, user_agent)
                            VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $userId,
        $emailTried,
        $success ? 1 : 0,
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
}

// Simple sliding-window limiter for actions like posting listings / OTP requests
function too_many_recent_actions(int $userId, string $action, int $maxCount, int $windowMinutes): bool
{
    $key = "rl_{$action}_{$userId}";
    $now = time();
    $log = $_SESSION[$key] ?? [];
    $log = array_filter($log, fn($t) => $t > $now - $windowMinutes * 60);
    if (count($log) >= $maxCount) {
        $_SESSION[$key] = $log;
        return true;
    }
    $log[] = $now;
    $_SESSION[$key] = $log;
    return false;
}

// =========================================================
// FILE UPLOAD SECURITY
// =========================================================
/**
 * Validates and stores an uploaded image safely.
 * Returns the relative stored path, or null on failure (sets $error).
 */
function handle_image_upload(array $file, string $subfolder, ?string &$error): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // optional upload, nothing sent
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed. Please try again.';
        return null;
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        $error = 'Image is too large (max 4MB).';
        return null;
    }
    if (!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatefrompng') || !function_exists('imagecreatefromwebp')) {
        $error = 'Image uploads are temporarily unavailable because PHP GD is disabled. You can publish without a photo, or enable GD in XAMPP.';
        return null;
    }

    // Verify actual MIME type from file content, not the client-supplied name
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
        $error = 'Only JPG, PNG or WEBP images are allowed.';
        return null;
    }

    // Re-encode the image to strip EXIF metadata (can leak seller's GPS location)
    // and to neutralise any polyglot file tricks.
    $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $ext = $extMap[$mime];

    $imgResource = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => @imagecreatefrompng($file['tmp_name']),
        'image/webp' => @imagecreatefromwebp($file['tmp_name']),
        default      => null,
    };
    if (!$imgResource) {
        $error = 'The image file appears to be corrupted.';
        return null;
    }

    $dir = rtrim(UPLOAD_DIR, '/') . '/' . $subfolder . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = $dir . $filename;

    $saved = match ($ext) {
        'jpg'  => imagejpeg($imgResource, $destination, 85),
        'png'  => imagepng($imgResource, $destination, 6),
        'webp' => imagewebp($imgResource, $destination, 85),
        default => false,
    };
    imagedestroy($imgResource);

    if (!$saved) {
        $error = 'Could not save the image. Please try again.';
        return null;
    }

    return $subfolder . '/' . $filename;
}

// =========================================================
// MASKING SENSITIVE DATA
// =========================================================
// Shows only the last 3 digits of a phone number until a viewer "reveals" it.
function mask_phone(string $phone): string
{
    $len = strlen($phone);
    if ($len <= 3) {
        return str_repeat('*', $len);
    }
    return str_repeat('*', $len - 3) . substr($phone, -3);
}

// =========================================================
// MISC
// =========================================================
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function flash_set(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

function trust_label(array $user): string
{
    if (is_fully_verified($user)) return 'Verified to trade';
    if ($user['phone_verified'] && !empty($user['email_verified'])) return 'Phone + Email Verified';
    if ($user['phone_verified']) return 'Phone Verified';
    if (!empty($user['email_verified'])) return 'Email Verified';
    return 'Unverified';
}

function is_fully_verified(array $user): bool
{
    return !empty($user['email_verified'])
        && !empty($user['phone_verified'])
        && !empty($user['id_verified']);
}

function verification_badge(array $user): array
{
    if (is_fully_verified($user)) {
        return ['class' => 'verified', 'text' => 'Verified to trade'];
    }

    if (!empty($user['phone_verified']) && !empty($user['email_verified'])) {
        return ['class' => 'phone-email', 'text' => 'Email + Phone verified'];
    }

    if (!empty($user['phone_verified'])) {
        return ['class' => 'phone', 'text' => 'Phone verified'];
    }

    if (!empty($user['email_verified'])) {
        return ['class' => 'email', 'text' => 'Email verified'];
    }

    return ['class' => 'unverified', 'text' => 'Verification pending'];
}

function ensure_verification_schema(): void
{
    try {
        $userColumns = db()->query("SHOW COLUMNS FROM users LIKE 'email_verified'")->fetchAll();
        if (!$userColumns) {
            db()->exec("ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER phone_verified");
        }

        db()->exec("ALTER TABLE otp_codes MODIFY COLUMN purpose ENUM('phone_verify','email_verify','password_reset') NOT NULL DEFAULT 'phone_verify'");
    } catch (Throwable $e) {
        error_log('Schema verification bootstrap failed: ' . $e->getMessage());
    }
}

function ensure_compare_tables(): void
{
    $tables = [
        'CREATE TABLE IF NOT EXISTS buyer_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            buyer_id INT UNSIGNED NOT NULL,
            message TEXT NOT NULL,
            reveal_contact TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB',
        'CREATE TABLE IF NOT EXISTS buyer_request_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            request_id INT UNSIGNED NOT NULL,
            seller_id INT UNSIGNED NOT NULL,
            listing_id INT UNSIGNED NOT NULL,
            status ENUM("pending","replied","contact_shared","closed") NOT NULL DEFAULT "pending",
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (request_id) REFERENCES buyer_requests(id) ON DELETE CASCADE,
            FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
            INDEX idx_request_items_request (request_id),
            INDEX idx_request_items_status (status)
        ) ENGINE=InnoDB',
    ];

    foreach ($tables as $sql) {
        db()->exec($sql);
    }
}

function seller_response_hint(array $seller): string
{
    $deals = (int) ($seller['deals_completed'] ?? 0);

    if ($deals >= 25) {
        return 'Usually replies in 2 hrs';
    }
    if ($deals >= 10) {
        return 'Usually replies within a day';
    }
    if ($deals >= 3) {
        return 'Usually replies within 24 hrs';
    }

    return 'New seller — response time may vary';
}

ensure_verification_schema();
