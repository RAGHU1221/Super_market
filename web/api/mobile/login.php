<?php
/**
 * POST { username, password, device_id }
 * -> { ok, token, user: {id, name, role} }
 */
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_json(['ok' => false, 'error' => 'POST required'], 405);

$in = api_input();
$username = trim($in['username'] ?? '');
$password = $in['password'] ?? '';
$deviceId = trim($in['device_id'] ?? '');

if ($username === '' || $password === '') {
    api_json(['ok' => false, 'error' => 'username and password required'], 400);
}

$stmt = db()->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    api_json(['ok' => false, 'error' => 'Invalid username or password'], 401);
}

$token = bin2hex(random_bytes(32));
db()->prepare("UPDATE users SET api_token = ? WHERE id = ?")->execute([$token, $user['id']]);

db()->prepare("INSERT INTO sync_log (device_id, user_id, direction, entity, record_count, status, details) VALUES (?,?,?,?,?,?,?)")
    ->execute([$deviceId, $user['id'], 'pull', 'login', 1, 'success', 'device login']);

api_json([
    'ok' => true,
    'token' => $token,
    'user' => ['id' => (int)$user['id'], 'name' => $user['name'], 'role' => $user['role'], 'username' => $user['username']],
    'store' => get_setting(),
]);
