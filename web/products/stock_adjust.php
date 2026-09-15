<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_permission('stock');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $productId = (int)post('product_id', 0);
    $type = post('adjustment_type', 'add');
    $qty = abs((float)post('qty', 0));
    $reason = post('reason', '');

    if ($productId <= 0 || $qty <= 0) {
        flash('error', 'Select a product and a valid quantity.');
    } else {
        $delta = $type === 'remove' ? -$qty : $qty;
        adjust_stock($productId, $delta, current_user()['id'], $reason ?: ucfirst($type) . ' adjustment');
        flash('success', 'Stock adjusted.');
    }
    header('Location: stock_adjust.php');
    exit;
}

$products = db()->query("SELECT id, name, barcode, unit, stock_qty FROM products WHERE deleted_at IS NULL AND is_active=1 ORDER BY name")->fetchAll();
$history = db()->query("SELECT sa.*, p.name AS product_name, u.name AS user_name FROM stock_adjustments sa LEFT JOIN products p ON p.id=sa.product_id LEFT JOIN users u ON u.id=sa.created_by ORDER BY sa.id DESC LIMIT 40")->fetchAll();

$pageTitle = t('stock');
$activeModule = 'stock';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-title">📊 Manual Stock Adjustment (damage, correction, stock-take)</div>
  <form method="post">
    <?= csrf_field() ?>
    <div class="form-row">
      <div><label>Product</label><select name="product_id" required>
          <option value="">-- select --</option>
          <?php foreach ($products as $p): ?>
            <option value="<?= (int)$p['id'] ?>">
              <?= e($p['name']) ?> (current: <?= rtrim(rtrim(number_format($p['stock_qty'],3),'0'),'.') ?: '0' ?> <?= e($p['unit']) ?>)
            </option>
          <?php endforeach; ?>
        </select></div>
      <div><label>Type</label><select name="adjustment_type">
          <option value="add">Add (found extra stock)</option>
          <option value="remove">Remove (damage / loss / correction)</option>
        </select></div>
      <div><label><?= e(t('qty')) ?></label><input type="number" step="0.001" name="qty" required></div>
      <div><label>Reason</label><input type="text" name="reason" placeholder="e.g. Damaged, Expired, Stock count correction"></div>
    </div>
    <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
  </form>
</div>

<div class="card">
  <div class="card-title">Adjustment History</div>
  <div class="table-scroll">
  <table class="data-table">
    <tr><th>Date</th><th>Product</th><th>Type</th><th><?= e(t('qty')) ?></th><th>Reason</th><th>By</th></tr>
    <?php foreach ($history as $h): ?>
    <tr>
      <td><?= date('d M Y, h:i A', strtotime($h['created_at'])) ?></td>
      <td><?= e($h['product_name']) ?></td>
      <td><span class="badge <?= $h['adjustment_type']==='add'?'badge-green':'badge-red' ?>"><?= e(strtoupper($h['adjustment_type'])) ?></span></td>
      <td><?= rtrim(rtrim(number_format($h['qty'],3),'0'),'.') ?></td>
      <td><?= e($h['reason']) ?></td>
      <td><?= e($h['user_name'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
