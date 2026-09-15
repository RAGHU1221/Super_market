<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!can('billing')) json_response(['ok' => false, 'error' => 'Access denied'], 403);

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    json_response(['ok' => false, 'error' => 'Session expired. Please refresh.'], 419);
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'hold') {
    if (empty($input['cart'])) json_response(['ok' => false, 'error' => 'Cart is empty.'], 400);
    $ref = generate_code('HOLD');
    $stmt = db()->prepare("INSERT INTO held_bills (hold_ref, cashier_id, customer_name, cart_json) VALUES (?,?,?,?)");
    $stmt->execute([$ref, current_user()['id'], $input['customer_name'] ?? '', json_encode($input['cart'], JSON_UNESCAPED_UNICODE)]);
    json_response(['ok' => true, 'hold_ref' => $ref]);
} elseif ($action === 'delete') {
    $id = (int)($input['id'] ?? 0);
    db()->prepare("DELETE FROM held_bills WHERE id = ?")->execute([$id]);
    json_response(['ok' => true]);
} else {
    json_response(['ok' => false, 'error' => 'Unknown action'], 400);
}
