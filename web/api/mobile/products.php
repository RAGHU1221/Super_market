<?php
/**
 * GET ?since=2026-09-01+10:00:00  (optional - for incremental sync)
 * -> { ok, products: [...], categories: [...], server_time }
 * Pulls the full active catalogue (or only rows changed since `since`) for offline SQLite caching.
 */
require_once __DIR__ . '/_bootstrap.php';
$user = api_authenticate();

$since = $_GET['since'] ?? '';

$sql = "SELECT id, sku, barcode, name, name_ta, category_id, supplier_id, unit, hsn_code, tax_percent,
        cost_price, selling_price, mrp, stock_qty, reorder_level, expiry_date, batch_no, is_active, updated_at
        FROM products WHERE deleted_at IS NULL";
$params = [];
if ($since !== '') {
    $sql .= " AND updated_at > ?";
    $params[] = $since;
}
$sql .= " ORDER BY id";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = db()->query("SELECT id, name, name_ta, parent_id, is_active FROM categories WHERE deleted_at IS NULL ORDER BY id")->fetchAll();

db()->prepare("INSERT INTO sync_log (user_id, direction, entity, record_count, status) VALUES (?, 'pull', 'products', ?, 'success')")
    ->execute([$user['id'], count($products)]);

api_json([
    'ok' => true,
    'products' => $products,
    'categories' => $categories,
    'server_time' => date('Y-m-d H:i:s'),
]);
