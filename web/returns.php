<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('returns');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $saleItemId = (int)post('sale_item_id', 0);
    $qty = (float)post('qty', 0);
    $reason = post('reason', '');

    $stmt = db()->prepare("SELECT si.*, s.id AS sale_id FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE si.id = ?");
    $stmt->execute([$saleItemId]);
    $item = $stmt->fetch();

    if (!$item) {
        flash('error', 'Sale item not found.');
    } elseif ($qty <= 0 || $qty > $item['qty']) {
        flash('error', 'Invalid return quantity.');
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $refund = round(($item['line_total'] / $item['qty']) * $qty, 2);
            $ins = $pdo->prepare("INSERT INTO sale_returns (sale_id, sale_item_id, qty, refund_amount, reason, created_by) VALUES (?,?,?,?,?,?)");
            $ins->execute([$item['sale_id'], $saleItemId, $qty, $refund, $reason, current_user()['id']]);
            adjust_stock((int)$item['product_id'], $qty, current_user()['id'], 'Return: ' . $reason);
            $pdo->commit();
            flash('success', 'Return recorded. Refund: ' . money($refund));
        } catch (Exception $e) {
            $pdo->rollBack();
            flash('error', 'Return failed: ' . $e->getMessage());
        }
    }
    header('Location: returns.php');
    exit;
}

$invoiceSearch = get('invoice', '');
$sale = null; $items = [];
if ($invoiceSearch !== '') {
    $stmt = db()->prepare("SELECT * FROM sales WHERE invoice_no = ? LIMIT 1");
    $stmt->execute([$invoiceSearch]);
    $sale = $stmt->fetch();
    if ($sale) {
        $itemStmt = db()->prepare("SELECT si.*, p.name AS product_name,
            (SELECT COALESCE(SUM(qty),0) FROM sale_returns r WHERE r.sale_item_id = si.id) returned_qty
            FROM sale_items si LEFT JOIN products p ON p.id=si.product_id WHERE si.sale_id = ?");
        $itemStmt->execute([$sale['id']]);
        $items = $itemStmt->fetchAll();
    }
}

$recentReturns = db()->query("SELECT r.*, p.name AS product_name, s.invoice_no FROM sale_returns r
    LEFT JOIN sale_items si ON si.id = r.sale_item_id LEFT JOIN products p ON p.id = si.product_id
    LEFT JOIN sales s ON s.id = r.sale_id ORDER BY r.id DESC LIMIT 30")->fetchAll();

$pageTitle = t('returns');
$activeModule = 'returns';
require_once __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="card-title">↩️ Find Invoice for Return</div>
  <form method="get" class="form-row">
    <div><label>Invoice Number</label><input type="text" name="invoice" value="<?= e($invoiceSearch) ?>" required></div>
    <div style="align-self:flex-end;"><button class="btn btn-primary" type="submit"><?= e(t('search')) ?></button></div>
  </form>

  <?php if ($invoiceSearch !== '' && !$sale): ?>
    <p style="color:var(--red-500);">Invoice not found.</p>
  <?php elseif ($sale): ?>
    <h4>Invoice <?= e($sale['invoice_no']) ?> — <?= money((float)$sale['total_amount']) ?></h4>
    <table class="data-table">
      <tr><th>Item</th><th>Sold Qty</th><th>Already Returned</th><th>Return Qty</th><th>Reason</th><th></th></tr>
      <?php foreach ($items as $it): $remaining = $it['qty'] - $it['returned_qty']; ?>
      <tr>
        <td><?= e($it['product_name']) ?></td>
        <td><?= rtrim(rtrim(number_format($it['qty'],3),'0'),'.') ?></td>
        <td><?= rtrim(rtrim(number_format($it['returned_qty'],3),'0'),'.') ?></td>
        <td>
          <?php if ($remaining > 0): ?>
          <form method="post" style="display:flex; gap:.3rem; align-items:center;">
            <?= csrf_field() ?>
            <input type="hidden" name="sale_item_id" value="<?= (int)$it['id'] ?>">
            <input type="number" step="0.001" name="qty" max="<?= $remaining ?>" value="<?= $remaining ?>" style="width:80px; margin:0;">
            <input type="text" name="reason" placeholder="Reason" style="width:120px; margin:0;">
            <button class="btn btn-sm btn-danger" type="submit">Return</button>
          </form>
          <?php else: ?><span class="badge badge-gray">Fully returned</span><?php endif; ?>
        </td>
        <td></td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-title">Recent Returns</div>
  <div class="table-scroll">
  <table class="data-table">
    <tr><th>Invoice</th><th>Item</th><th><?= e(t('qty')) ?></th><th>Refund</th><th>Reason</th><th>Date</th></tr>
    <?php foreach ($recentReturns as $r): ?>
    <tr>
      <td><?= e($r['invoice_no']) ?></td>
      <td><?= e($r['product_name']) ?></td>
      <td><?= rtrim(rtrim(number_format($r['qty'],3),'0'),'.') ?></td>
      <td><?= money((float)$r['refund_amount']) ?></td>
      <td><?= e($r['reason']) ?></td>
      <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
