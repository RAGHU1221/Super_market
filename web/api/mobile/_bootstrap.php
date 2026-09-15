<?php
/**
 * Shared bootstrap for the mobile/desktop sync API.
 * Auth: Bearer token (users.api_token), issued by login.php.
 * Every response is JSON. CORS is open (app is a native client, not a browser page).
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function api_json($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $_POST;
}

/**
 * Validates the bearer token and returns the authenticated user row (without password_hash).
 * Dies with 401 JSON if invalid.
 */
function api_authenticate(): array
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $token = '';
    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
        $token = trim($m[1]);
    } elseif (!empty($_GET['token'])) {
        $token = $_GET['token'];
    }

    if ($token === '') {
        api_json(['ok' => false, 'error' => 'Missing auth token'], 401);
    }

    $stmt = db()->prepare("SELECT * FROM users WHERE api_token = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user) {
        api_json(['ok' => false, 'error' => 'Invalid or expired token'], 401);
    }
    unset($user['password_hash']);
    return $user;
}
