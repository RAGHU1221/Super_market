<?php
/**
 * GET -> { ok, stock: [ {id, stock_qty}, ... ], server_time }
 * Lightweight endpoint the desktop/mobile app can poll to detect stock drift
 * (e.g. after another cashier's sale synced) without pulling the full catalogue.
 */
require_once __DIR__ . '/_bootstrap.php';
$user = api_authenticate();

$rows = db()->query("SELECT id, stock_qty, updated_at FROM products WHERE deleted_at IS NULL")->fetchAll();

api_json(['ok' => true, 'stock' => $rows, 'server_time' => date('Y-m-d H:i:s')]);
