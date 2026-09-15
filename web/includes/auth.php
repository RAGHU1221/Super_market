<?php
/**
 * Authentication + RBAC + CSRF helpers
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . base_url('login.php'));
        exit;
    }
}

function require_permission(string $moduleKey): void
{
    require_login();
    $user = current_user();
    $allowed = $GLOBALS['PERMISSIONS'][$moduleKey] ?? [];
    if (!in_array($user['role'], $allowed, true)) {
        http_response_code(403);
        die('<h2>Access denied</h2><p>Your role (' . htmlspecialchars($user['role']) . ') cannot access this page. / உங்கள் பங்கு இந்தப் பக்கத்தை அணுக முடியாது.</p><p><a href="' . base_url('dashboard.php') . '">Back to Dashboard</a></p>');
    }
}

function can(string $moduleKey): bool
{
    $user = current_user();
    if (!$user) return false;
    $allowed = $GLOBALS['PERMISSIONS'][$moduleKey] ?? [];
    return in_array($user['role'], $allowed, true);
}

function base_url(string $path = ''): string
{
    $base = defined('APP_BASE_URL') ? APP_BASE_URL : '';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function attempt_login(string $username, string $password): bool
{
    $stmt = db()->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        regenerate_csrf_token();

        // log shift start
        $log = db()->prepare("INSERT INTO staff_shift_logs (user_id, ip_address, device) VALUES (?, ?, 'web')");
        $log->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? null]);
        $_SESSION['shift_log_id'] = db()->lastInsertId();

        log_activity($user['id'], 'login', 'User logged in');
        return true;
    }
    return false;
}

function do_logout(): void
{
    $user = current_user();
    if ($user && !empty($_SESSION['shift_log_id'])) {
        $stmt = db()->prepare("UPDATE staff_shift_logs SET logout_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['shift_log_id']]);
    }
    if ($user) log_activity($user['id'], 'logout', 'User logged out');
    $_SESSION = [];
    session_destroy();
}

function log_activity(?int $userId, string $action, string $details = ''): void
{
    try {
        $stmt = db()->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Exception $e) { /* non-fatal */ }
}

// ---------------- CSRF ----------------
function regenerate_csrf_token(): string
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        return regenerate_csrf_token();
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        die('Session expired or invalid request (CSRF check failed). Please refresh and try again. / அமர்வு காலாவதியானது, மீண்டும் முயற்சிக்கவும்.');
    }
}
