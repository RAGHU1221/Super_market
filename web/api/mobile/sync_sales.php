<?php
/**
 * POST { device_id, sales: [ { sync_uid, items:[{product_id, qty, unit_price, tax_percent}], discount_amount,
 *         payment_mode, paid_amount, customer_name, customer_phone, sale_date } ] }
 * Pushes offline bills recorded on the Flutter app up to the server.
 * Idempotent: sync_uid (client-generated UUID) prevents duplicate inserts if the request is retried.
 * -> { ok, results: [ {sync_uid, status: 'inserted'|'duplicate'|'error', sale_id?, invoice_no?, error?} ] }
 */
require_once __DIR__ . '/_bootstrap.php';
$user = api_authenticate();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_json(['ok' => false, 'error' => 'POST required'], 405);

$in = api_input();
$deviceId = $in['device_id'] ?? 'unknown';
$sales = $in['sales'] ?? [];
if (!is_array($sales) || !$sales) {
    api_json(['ok' => false, 'error' => 'No sales to sync'], 400);
}

$pdo = db();
$results = [];

foreach ($sales as $saleIn) {
    $syncUid = $saleIn['sync_uid'] ?? null;
    if (!$syncUid) { $results[] = ['sync_uid' => null, 'status' => 'error', 'error' => 'missing sync_uid']; continue; }

    // dedupe check
    $chk = $pdo->prepare("SELECT id, invoice_no FROM sales WHERE sync_uid = ?");
    $chk->execute([$syncUid]);
    $existing = $chk->fetch();
    if ($existing) {
        $results[] = ['sync_uid' => $syncUid, 'status' => 'duplicate', 'sale_id' => (int)$existing['id'], 'invoice_no' => $existing['invoice_no']];
        continue;
    }

    $items = $saleIn['items'] ?? [];
    if (!$items) { $results[] = ['sync_uid' => $syncUid, 'status' => 'error', 'error' => 'no items']; continue; }

    $pdo->beginTransaction();
    try {
        $subtotal = 0; $taxTotal = 0; $lines = [];
        $prodStmt = $pdo->prepare("SELECT * FROM products WHERE id = ? FOR UPDATE");
        foreach ($items as $it) {
            $pid = (int)($it['product_id'] ?? 0);
            $qty = (float)($it['qty'] ?? 0);
            if ($pid <= 0 || $qty <= 0) continue;
            $prodStmt->execute([$pid]);
            $product = $prodStmt->fetch();
            if (!$product) throw new Exception("Product $pid not found");

            $unitPrice = isset($it['unit_price']) ? (float)$it['unit_price'] : (float)$product['selling_price'];
            $taxPercent = isset($it['tax_percent']) ? (float)$it['tax_percent'] : (float)$product['tax_percent'];
            $lineSub = $unitPrice * $qty;
            $lineTax = $lineSub * ($taxPercent / 100);
            $subtotal += $lineSub;
            $taxTotal += $lineTax;
            $lines[] = ['product_id' => $pid, 'qty' => $qty, 'unit_price' => $unitPrice, 'tax_percent' => $taxPercent, 'tax_amount' => $lineTax, 'line_total' => $lineSub + $lineTax];
            // allow negative stock on sync (offline sale already happened physically); just record it
            $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?")->execute([$qty, $pid]);
        }
        if (!$lines) throw new Exception('No valid items');

        $discount = (float)($saleIn['discount_amount'] ?? 0);
        $grandTotal = round($subtotal + $taxTotal - $discount, 2);
        $paidAmount = (float)($saleIn['paid_amount'] ?? $grandTotal);
        $balance = round($grandTotal - $paidAmount, 2);
        $paymentMode = in_array($saleIn['payment_mode'] ?? '', ['cash','card','upi','credit','split']) ? $saleIn['payment_mode'] : 'cash';
        $saleDate = $saleIn['sale_date'] ?? date('Y-m-d H:i:s');

        $customerId = null;
        $custPhone = trim($saleIn['customer_phone'] ?? '');
        if ($custPhone !== '') {
            $cstmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ? LIMIT 1");
            $cstmt->execute([$custPhone]);
            $crow = $cstmt->fetch();
            if ($crow) { $customerId = $crow['id']; }
            else {
                $cins = $pdo->prepare("INSERT INTO customers (name, phone) VALUES (?, ?)");
                $cins->execute([$saleIn['customer_name'] ?? 'Customer', $custPhone]);
                $customerId = $pdo->lastInsertId();
            }
        }

        $invoiceNo = generate_invoice_no();
        $source = $saleIn['source'] ?? 'desktop';
        $ins = $pdo->prepare("INSERT INTO sales (invoice_no, customer_id, cashier_id, sale_date, subtotal, discount_amount, tax_amount, total_amount, paid_amount, balance_amount, payment_mode, status, sync_source, sync_uid)
            VALUES (?,?,?,?,?,?,?,?,?,?,?, 'completed', ?, ?)");
        $ins->execute([$invoiceNo, $customerId, $user['id'], $saleDate, $subtotal, $discount, $taxTotal, $grandTotal, $paidAmount, $balance, $paymentMode, $source, $syncUid]);
        $saleId = $pdo->lastInsertId();

        $itemStmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, qty, unit_price, tax_percent, tax_amount, line_total) VALUES (?,?,?,?,?,?,?)");
        foreach ($lines as $l) {
            $itemStmt->execute([$saleId, $l['product_id'], $l['qty'], $l['unit_price'], $l['tax_percent'], $l['tax_amount'], $l['line_total']]);
        }

        $pdo->commit();
        $results[] = ['sync_uid' => $syncUid, 'status' => 'inserted', 'sale_id' => (int)$saleId, 'invoice_no' => $invoiceNo];
    } catch (Exception $e) {
        $pdo->rollBack();
        $results[] = ['sync_uid' => $syncUid, 'status' => 'error', 'error' => $e->getMessage()];
    }
}

db()->prepare("INSERT INTO sync_log (device_id, user_id, direction, entity, record_count, status) VALUES (?,?, 'push', 'sales', ?, 'success')")
    ->execute([$deviceId, $user['id'], count($results)]);

api_json(['ok' => true, 'results' => $results]);
