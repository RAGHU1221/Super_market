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
if (!$input || empty($input['items']) || !is_array($input['items'])) {
    json_response(['ok' => false, 'error' => 'Cart is empty.'], 400);
}

$items = $input['items'];
$discount = (float)($input['discount_amount'] ?? 0);
$paymentMode = in_array($input['payment_mode'] ?? '', ['cash','card','upi','credit','split']) ? $input['payment_mode'] : 'cash';
$paidAmount = (float)($input['paid_amount'] ?? 0);
$custName = trim($input['customer_name'] ?? '');
$custPhone = trim($input['customer_phone'] ?? '');

$pdo = db();
$pdo->beginTransaction();
try {
    // resolve/create customer
    $customerId = null;
    if ($custPhone !== '') {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ? LIMIT 1");
        $stmt->execute([$custPhone]);
        $row = $stmt->fetch();
        if ($row) {
            $customerId = $row['id'];
        } else {
            $ins = $pdo->prepare("INSERT INTO customers (name, phone) VALUES (?, ?)");
            $ins->execute([$custName ?: 'Customer', $custPhone]);
            $customerId = $pdo->lastInsertId();
        }
    }

    // validate stock & compute totals server-side (never trust client prices for tax %, but selling price can drift; refetch from DB)
    $subtotal = 0; $taxTotal = 0; $lines = [];
    $prodStmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND deleted_at IS NULL AND is_active = 1 FOR UPDATE");
    foreach ($items as $it) {
        $pid = (int)($it['id'] ?? 0);
        $qty = (float)($it['qty'] ?? 0);
        if ($pid <= 0 || $qty <= 0) continue;
        $prodStmt->execute([$pid]);
        $product = $prodStmt->fetch();
        if (!$product) throw new Exception('A product in the cart no longer exists.');
        if ($product['stock_qty'] < $qty) throw new Exception('Insufficient stock for ' . $product['name'] . ' (available: ' . $product['stock_qty'] . ')');

        $unitPrice = (float)$product['selling_price'];
        $taxPercent = (float)$product['tax_percent'];
        $lineSub = $unitPrice * $qty;
        $lineTax = $lineSub * ($taxPercent / 100);
        $subtotal += $lineSub;
        $taxTotal += $lineTax;
        $lines[] = [
            'product_id' => $pid, 'qty' => $qty, 'unit_price' => $unitPrice,
            'tax_percent' => $taxPercent, 'tax_amount' => $lineTax, 'line_total' => $lineSub + $lineTax,
        ];
    }
    if (!$lines) throw new Exception('No valid items to bill.');

    $discount = min($discount, $subtotal + $taxTotal);
    $grandTotal = round($subtotal + $taxTotal - $discount, 2);
    $balance = round($grandTotal - $paidAmount, 2);

    $invoiceNo = generate_invoice_no();
    $ins = $pdo->prepare("INSERT INTO sales (invoice_no, customer_id, cashier_id, subtotal, discount_amount, tax_amount, total_amount, paid_amount, balance_amount, payment_mode, status, sync_source)
        VALUES (?,?,?,?,?,?,?,?,?,?, 'completed', 'web')");
    $ins->execute([$invoiceNo, $customerId, current_user()['id'], $subtotal, $discount, $taxTotal, $grandTotal, $paidAmount, $balance, $paymentMode]);
    $saleId = $pdo->lastInsertId();

    $itemStmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, qty, unit_price, tax_percent, tax_amount, line_total) VALUES (?,?,?,?,?,?,?)");
    $stockStmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?");
    foreach ($lines as $l) {
        $itemStmt->execute([$saleId, $l['product_id'], $l['qty'], $l['unit_price'], $l['tax_percent'], $l['tax_amount'], $l['line_total']]);
        $stockStmt->execute([$l['qty'], $l['product_id']]);
    }

    $pdo->commit();
    log_activity(current_user()['id'], 'sale', "Invoice $invoiceNo total $grandTotal");
    json_response(['ok' => true, 'sale_id' => $saleId, 'invoice_no' => $invoiceNo]);
} catch (Exception $e) {
    $pdo->rollBack();
    json_response(['ok' => false, 'error' => $e->getMessage()], 400);
}
