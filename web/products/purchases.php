<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_permission('purchases');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $supplierId = post('supplier_id') ?: null;
    $invoiceNo = post('invoice_no') ?: null;
    $purchaseDate = post('purchase_date', today());
    $paidAmount = (float)post('paid_amount', 0);

    $productIds = $_POST['product_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $costs = $_POST['cost_price'] ?? [];
    $batches = $_POST['batch_no'] ?? [];
    $expiries = $_POST['expiry_date'] ?? [];

    $lines = [];
    $total = 0;
    foreach ($productIds as $i => $pid) {
        $pid = (int)$pid;
        $qty = (float)($qtys[$i] ?? 0);
        $cost = (float)($costs[$i] ?? 0);
        if ($pid <= 0 || $qty <= 0) continue;
        $lineTotal = $qty * $cost;
        $total += $lineTotal;
        $lines[] = [
            'product_id' => $pid, 'qty' => $qty, 'cost_price' => $cost,
            'batch_no' => $batches[$i] ?? null, 'expiry_date' => $expiries[$i] ?: null,
            'line_total' => $lineTotal,
        ];
    }

    if (!$lines) {
        flash('error', 'Add at least one valid product line.');
        header('Location: purchases.php');
        exit;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $purchaseNo = generate_code('PUR');
        $status = $paidAmount >= $total ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid');
        $ins = $pdo->prepare("INSERT INTO purchases (purchase_no, supplier_id, invoice_no, purchase_date, total_amount, paid_amount, payment_status, created_by) VALUES (?,?,?,?,?,?,?,?)");
        $ins->execute([$purchaseNo, $supplierId, $invoiceNo, $purchaseDate, $total, $paidAmount, $status, current_user()['id']]);
        $purchaseId = $pdo->lastInsertId();

        $itemStmt = $pdo->prepare("INSERT INTO purchase_items (purchase_id, product_id, qty, cost_price, batch_no, expiry_date, line_total) VALUES (?,?,?,?,?,?,?)");
        $stockStmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ?, cost_price = ?, batch_no = COALESCE(?, batch_no), expiry_date = COALESCE(?, expiry_date) WHERE id = ?");

        foreach ($lines as $l) {
            $itemStmt->execute([$purchaseId, $l['product_id'], $l['qty'], $l['cost_price'], $l['batch_no'], $l['expiry_date'], $l['line_total']]);
            $stockStmt->execute([$l['qty'], $l['cost_price'], $l['batch_no'], $l['expiry_date'], $l['product_id']]);
        }

        $pdo->commit();
        log_activity(current_user()['id'], 'purchase', "Purchase $purchaseNo total " . $total);
        flash('success', "Purchase $purchaseNo recorded. Stock updated.");
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', 'Purchase failed: ' . $e->getMessage());
    }
    header('Location: purchases.php');
    exit;
}

$suppliers = db()->query("SELECT * FROM suppliers WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$products = db()->query("SELECT id, name, barcode, unit, cost_price FROM products WHERE deleted_at IS NULL AND is_active=1 ORDER BY name")->fetchAll();
$recentPurchases = db()->query("SELECT p.*, s.name AS supplier_name FROM purchases p LEFT JOIN suppliers s ON s.id=p.supplier_id ORDER BY p.id DESC LIMIT 30")->fetchAll();

$pageTitle = t('purchases');
$activeModule = 'purchases';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-title">📥 New Purchase (Stock In)</div>
  <form method="post" id="purchase-form">
    <?= csrf_field() ?>
    <div class="form-row">
      <div><label>Supplier</label><select name="supplier_id">
          <option value="">--</option>
          <?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div><label>Supplier Invoice No.</label><input type="text" name="invoice_no"></div>
      <div><label>Purchase Date</label><input type="date" name="purchase_date" value="<?= today() ?>"></div>
      <div><label>Paid Amount</label><input type="number" step="0.01" name="paid_amount" value="0"></div>
    </div>

    <table class="data-table" id="items-table">
      <tr><th>Product</th><th>Qty</th><th>Cost Price</th><th>Batch No</th><th>Expiry</th><th>Line Total</th><th></th></tr>
      <tbody id="items-body"></tbody>
    </table>
    <button type="button" class="btn btn-sm" onclick="addRow()">➕ Add Line</button>
    <p style="font-weight:800; margin-top:.6rem;">Total: <span id="grand-total">₹0.00</span></p>
    <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
  </form>
</div>

<div class="card">
  <div class="card-title"><?= e(t('purchases')) ?> — Recent</div>
  <div class="table-scroll">
  <table class="data-table">
    <tr><th>Purchase No</th><th>Supplier</th><th>Date</th><th>Total</th><th>Paid</th><th>Status</th></tr>
    <?php foreach ($recentPurchases as $p): ?>
    <tr>
      <td><?= e($p['purchase_no']) ?></td>
      <td><?= e($p['supplier_name'] ?? '-') ?></td>
      <td><?= e($p['purchase_date']) ?></td>
      <td><?= money((float)$p['total_amount']) ?></td>
      <td><?= money((float)$p['paid_amount']) ?></td>
      <td><span class="badge <?= $p['payment_status']==='paid'?'badge-green':($p['payment_status']==='partial'?'badge-gold':'badge-red') ?>"><?= e(strtoupper($p['payment_status'])) ?></span></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
</div>

<script>
const PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
let rowIdx = 0;
function addRow() {
  const tbody = document.getElementById('items-body');
  const tr = document.createElement('tr');
  const options = PRODUCTS.map(p => `<option value="${p.id}" data-cost="${p.cost_price}">${p.name.replace(/"/g,'')} (${p.unit})</option>`).join('');
  tr.innerHTML = `
    <td><select name="product_id[]" class="prod-select" style="margin:0;"><option value="">-- select --</option>${options}</select></td>
    <td><input type="number" step="0.001" name="qty[]" class="qty-input" style="margin:0;width:80px;" value="1"></td>
    <td><input type="number" step="0.01" name="cost_price[]" class="cost-input" style="margin:0;width:90px;" value="0"></td>
    <td><input type="text" name="batch_no[]" style="margin:0;width:90px;"></td>
    <td><input type="date" name="expiry_date[]" style="margin:0;"></td>
    <td class="line-total">₹0.00</td>
    <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove(); recalc();">✕</button></td>
  `;
  tbody.appendChild(tr);
  const sel = tr.querySelector('.prod-select');
  sel.addEventListener('change', () => {
    const opt = sel.selectedOptions[0];
    tr.querySelector('.cost-input').value = opt.dataset.cost || 0;
    recalc();
  });
  tr.querySelector('.qty-input').addEventListener('input', recalc);
  tr.querySelector('.cost-input').addEventListener('input', recalc);
  recalc();
}
function recalc() {
  let grand = 0;
  document.querySelectorAll('#items-body tr').forEach(tr => {
    const qty = parseFloat(tr.querySelector('.qty-input').value) || 0;
    const cost = parseFloat(tr.querySelector('.cost-input').value) || 0;
    const lt = qty * cost;
    tr.querySelector('.line-total').textContent = '₹' + lt.toFixed(2);
    grand += lt;
  });
  document.getElementById('grand-total').textContent = '₹' + grand.toFixed(2);
}
addRow();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
