<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('dashboard');
$pageTitle = t('dashboard');
$activeModule = 'dashboard';
require_once __DIR__ . '/includes/header.php';

$todaySales = db()->query("SELECT COALESCE(SUM(total_amount),0) t, COUNT(*) c FROM sales WHERE status='completed' AND DATE(sale_date) = CURDATE()")->fetch();
$totalProducts = db()->query("SELECT COUNT(*) c FROM products WHERE is_active=1 AND deleted_at IS NULL")->fetch()['c'];
$lowStock = low_stock_products(8);
$expiring = expiring_products(8);
$recentSales = db()->query("SELECT s.*, u.name AS cashier_name FROM sales s LEFT JOIN users u ON u.id = s.cashier_id WHERE s.status='completed' ORDER BY s.sale_date DESC LIMIT 8")->fetchAll();

// 7-day sales trend for a simple bar chart
$trend = db()->query("SELECT DATE(sale_date) d, SUM(total_amount) t FROM sales WHERE status='completed' AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(sale_date)")->fetchAll();
$trendMap = [];
foreach ($trend as $row) { $trendMap[$row['d']] = (float)$row['t']; }
$trendLabels = []; $trendValues = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $trendLabels[] = date('d M', strtotime($d));
    $trendValues[] = $trendMap[$d] ?? 0;
}
$maxTrend = max($trendValues) ?: 1;
?>

<div class="stat-grid">
  <div class="stat-card">
    <div class="label"><?= e(t('today_sales')) ?></div>
    <div class="value"><?= money((float)$todaySales['t']) ?></div>
  </div>
  <div class="stat-card gold">
    <div class="label"><?= e(t('today_bills')) ?></div>
    <div class="value"><?= (int)$todaySales['c'] ?></div>
  </div>
  <div class="stat-card wood">
    <div class="label"><?= e(t('total_products')) ?></div>
    <div class="value"><?= (int)$totalProducts ?></div>
  </div>
  <div class="stat-card red">
    <div class="label"><?= e(t('low_stock')) ?></div>
    <div class="value"><?= count($lowStock) ?></div>
  </div>
</div>

<div class="card">
  <div class="card-title">📈 Last 7 Days Sales</div>
  <div style="display:flex; align-items:flex-end; gap:10px; height:140px;">
    <?php foreach ($trendLabels as $i => $lbl): $h = max(6, round(($trendValues[$i] / $maxTrend) * 120)); ?>
      <div style="flex:1; text-align:center;">
        <div style="height:<?= $h ?>px; background:linear-gradient(180deg,var(--green-500),var(--green-700)); border-radius:6px 6px 0 0;" title="<?= money($trendValues[$i]) ?>"></div>
        <div style="font-size:.72rem; color:var(--ink-500); margin-top:.3rem;"><?= e($lbl) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="form-row">
  <div class="card" style="flex:1;">
    <div class="card-title">⚠️ <?= e(t('low_stock')) ?> <a class="btn btn-sm" href="products/index.php?filter=low_stock">View all</a></div>
    <?php if (!$lowStock): ?><p style="color:var(--ink-500);">No low-stock items. 👍</p><?php else: ?>
    <div class="table-scroll"><table class="data-table">
      <tr><th><?= e(t('name')) ?></th><th><?= e(t('qty')) ?></th><th>Reorder Level</th></tr>
      <?php foreach ($lowStock as $p): ?>
      <tr><td><?= e($p['name']) ?></td><td><span class="badge badge-red"><?= rtrim(rtrim(number_format($p['stock_qty'],3),'0'),'.') ?> <?= e($p['unit']) ?></span></td><td><?= rtrim(rtrim(number_format($p['reorder_level'],3),'0'),'.') ?></td></tr>
      <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
  </div>
  <div class="card" style="flex:1;">
    <div class="card-title">⏳ <?= e(t('expiring_soon')) ?></div>
    <?php if (!$expiring): ?><p style="color:var(--ink-500);">No items expiring soon. 👍</p><?php else: ?>
    <div class="table-scroll"><table class="data-table">
      <tr><th><?= e(t('name')) ?></th><th>Batch</th><th>Expiry</th></tr>
      <?php foreach ($expiring as $p): ?>
      <tr><td><?= e($p['name']) ?></td><td><?= e($p['batch_no']) ?></td><td><span class="badge badge-gold"><?= e($p['expiry_date']) ?></span></td></tr>
      <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-title">🧾 Recent Sales <a class="btn btn-sm" href="sales_history.php">View all</a></div>
  <div class="table-scroll"><table class="data-table">
    <tr><th>Invoice</th><th>Time</th><th>Cashier</th><th>Total</th><th>Mode</th></tr>
    <?php foreach ($recentSales as $s): ?>
    <tr>
      <td><a href="invoice.php?id=<?= (int)$s['id'] ?>"><?= e($s['invoice_no']) ?></a></td>
      <td><?= date('d M, h:i A', strtotime($s['sale_date'])) ?></td>
      <td><?= e($s['cashier_name'] ?? '-') ?></td>
      <td><?= money((float)$s['total_amount']) ?></td>
      <td><span class="badge badge-green"><?= e(strtoupper($s['payment_mode'])) ?></span></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
