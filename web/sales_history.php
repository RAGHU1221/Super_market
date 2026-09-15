<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('sales_history');

$dateFrom = get('from', today());
$dateTo = get('to', today());
$search = get('q', '');

$where = "WHERE s.status='completed' AND DATE(s.sale_date) BETWEEN ? AND ?";
$params = [$dateFrom, $dateTo];
if ($search !== '') {
    $where .= " AND s.invoice_no LIKE ?";
    $params[] = "%$search%";
}

$stmt = db()->prepare("SELECT s.*, u.name AS cashier_name, c.name AS customer_name
    FROM sales s LEFT JOIN users u ON u.id=s.cashier_id LEFT JOIN customers c ON c.id=s.customer_id
    $where ORDER BY s.sale_date DESC LIMIT 500");
$stmt->execute($params);
$sales = $stmt->fetchAll();

$totals = ['count' => count($sales), 'amount' => array_sum(array_column($sales, 'total_amount'))];

$pageTitle = t('sales_history');
$activeModule = 'sales_history';
require_once __DIR__ . '/includes/header.php';
?>
<div class="card">
  <form method="get" class="form-row">
    <div><label>From</label><input type="date" name="from" value="<?= e($dateFrom) ?>"></div>
    <div><label>To</label><input type="date" name="to" value="<?= e($dateTo) ?>"></div>
    <div><label>Invoice No.</label><input type="text" name="q" value="<?= e($search) ?>"></div>
    <div style="align-self:flex-end;"><button class="btn btn-primary" type="submit"><?= e(t('search')) ?></button></div>
  </form>
</div>

<div class="card">
  <div class="card-title">
    <?= e(t('sales_history')) ?> — <?= $totals['count'] ?> bills, <?= money((float)$totals['amount']) ?>
  </div>
  <div class="table-scroll">
  <table class="data-table">
    <tr><th>Invoice</th><th>Date</th><th>Customer</th><th>Cashier</th><th>Total</th><th>Mode</th><th></th></tr>
    <?php foreach ($sales as $s): ?>
    <tr>
      <td><?= e($s['invoice_no']) ?></td>
      <td><?= date('d M Y, h:i A', strtotime($s['sale_date'])) ?></td>
      <td><?= e($s['customer_name'] ?? 'Walk-in') ?></td>
      <td><?= e($s['cashier_name'] ?? '-') ?></td>
      <td><?= money((float)$s['total_amount']) ?></td>
      <td><span class="badge badge-green"><?= e(strtoupper($s['payment_mode'])) ?></span></td>
      <td><a class="btn btn-sm" href="invoice.php?id=<?= (int)$s['id'] ?>">View</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$sales): ?><tr><td colspan="7" style="color:var(--ink-500);">No sales in this range.</td></tr><?php endif; ?>
  </table>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
