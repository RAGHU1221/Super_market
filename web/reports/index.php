<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_permission('reports');

$type = get('type', 'sales');
$dateFrom = get('from', date('Y-m-01'));
$dateTo = get('to', today());
$export = get('export', '');

// ---------------- Data builders ----------------
function report_sales(string $from, string $to): array {
    $stmt = db()->prepare("SELECT DATE(sale_date) d, COUNT(*) bills, SUM(subtotal) subtotal, SUM(discount_amount) discount, SUM(tax_amount) tax, SUM(total_amount) total
        FROM sales WHERE status='completed' AND DATE(sale_date) BETWEEN ? AND ? GROUP BY DATE(sale_date) ORDER BY d DESC");
    $stmt->execute([$from, $to]);
    return $stmt->fetchAll();
}
function report_gst(string $from, string $to): array {
    $stmt = db()->prepare("SELECT p.hsn_code, si.tax_percent, SUM(si.qty) qty, SUM(si.unit_price*si.qty) taxable, SUM(si.tax_amount) tax
        FROM sale_items si JOIN sales s ON s.id=si.sale_id LEFT JOIN products p ON p.id=si.product_id
        WHERE s.status='completed' AND DATE(s.sale_date) BETWEEN ? AND ? GROUP BY p.hsn_code, si.tax_percent ORDER BY si.tax_percent");
    $stmt->execute([$from, $to]);
    return $stmt->fetchAll();
}
function report_stock(): array {
    return db()->query("SELECT p.name, c.name AS category, p.stock_qty, p.unit, p.cost_price, (p.stock_qty*p.cost_price) stock_value, p.reorder_level
        FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.deleted_at IS NULL AND p.is_active=1 ORDER BY p.name")->fetchAll();
}
function report_staff(string $from, string $to): array {
    $stmt = db()->prepare("SELECT u.name, u.role, COUNT(s.id) bills, COALESCE(SUM(s.total_amount),0) total
        FROM users u LEFT JOIN sales s ON s.cashier_id=u.id AND s.status='completed' AND DATE(s.sale_date) BETWEEN ? AND ?
        WHERE u.deleted_at IS NULL GROUP BY u.id ORDER BY total DESC");
    $stmt->execute([$from, $to]);
    return $stmt->fetchAll();
}
function report_profit(string $from, string $to): array {
    $stmt = db()->prepare("SELECT p.name, SUM(si.qty) qty_sold, SUM(si.unit_price*si.qty) revenue, SUM(p.cost_price*si.qty) cost,
        (SUM(si.unit_price*si.qty) - SUM(p.cost_price*si.qty)) profit
        FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id
        WHERE s.status='completed' AND DATE(s.sale_date) BETWEEN ? AND ? GROUP BY p.id ORDER BY profit DESC");
    $stmt->execute([$from, $to]);
    return $stmt->fetchAll();
}

$rows = [];
switch ($type) {
    case 'gst': $rows = report_gst($dateFrom, $dateTo); break;
    case 'stock': $rows = report_stock(); break;
    case 'staff': $rows = report_staff($dateFrom, $dateTo); break;
    case 'profit': $rows = report_profit($dateFrom, $dateTo); break;
    default: $type = 'sales'; $rows = report_sales($dateFrom, $dateTo); break;
}

if ($export === '1') {
    $headersMap = [
        'sales' => ['Date','Bills','Subtotal','Discount','Tax','Total'],
        'gst'   => ['HSN Code','Tax %','Qty','Taxable Value','Tax Amount'],
        'stock' => ['Product','Category','Stock Qty','Unit','Cost Price','Stock Value','Reorder Level'],
        'staff' => ['Name','Role','Bills','Total Sales'],
        'profit'=> ['Product','Qty Sold','Revenue','Cost','Profit'],
    ];
    export_html_table_as_excel("report_{$type}_{$dateFrom}_to_{$dateTo}", $headersMap[$type], $rows);
}

$pageTitle = t('reports');
$activeModule = 'reports';
require_once __DIR__ . '/../includes/header.php';

$tabs = [
    'sales' => 'Sales Summary',
    'gst' => 'GST Report',
    'profit' => 'Profit Report',
    'stock' => 'Stock Report',
    'staff' => 'Staff-wise Sales',
];
?>
<div class="card">
  <div style="display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:.8rem;">
    <?php foreach ($tabs as $key => $label): ?>
      <a class="btn btn-sm <?= $type === $key ? 'btn-primary' : '' ?>" href="?type=<?= $key ?>&from=<?= e($dateFrom) ?>&to=<?= e($dateTo) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <form method="get" class="form-row">
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <div><label>From</label><input type="date" name="from" value="<?= e($dateFrom) ?>"></div>
    <div><label>To</label><input type="date" name="to" value="<?= e($dateTo) ?>"></div>
    <div style="align-self:flex-end;"><button class="btn btn-primary" type="submit">Apply</button></div>
    <div style="align-self:flex-end;"><a class="btn btn-gold" href="?type=<?= $type ?>&from=<?= e($dateFrom) ?>&to=<?= e($dateTo) ?>&export=1">⬇️ Export Excel</a></div>
  </form>
</div>

<div class="card">
  <div class="card-title"><?= e($tabs[$type]) ?> (<?= count($rows) ?> rows)</div>
  <div class="table-scroll">
  <table class="data-table">
    <?php if ($type === 'sales'): ?>
      <tr><th>Date</th><th>Bills</th><th>Subtotal</th><th>Discount</th><th>Tax</th><th>Total</th></tr>
      <?php foreach ($rows as $r): ?>
      <tr><td><?= e($r['d']) ?></td><td><?= (int)$r['bills'] ?></td><td><?= money((float)$r['subtotal']) ?></td><td><?= money((float)$r['discount']) ?></td><td><?= money((float)$r['tax']) ?></td><td><b><?= money((float)$r['total']) ?></b></td></tr>
      <?php endforeach; ?>
    <?php elseif ($type === 'gst'): ?>
      <tr><th>HSN Code</th><th>Tax %</th><th>Qty</th><th>Taxable Value</th><th>Tax Amount</th></tr>
      <?php foreach ($rows as $r): ?>
      <tr><td><?= e($r['hsn_code'] ?: '-') ?></td><td><?= e($r['tax_percent']) ?>%</td><td><?= rtrim(rtrim(number_format($r['qty'],3),'0'),'.') ?></td><td><?= money((float)$r['taxable']) ?></td><td><?= money((float)$r['tax']) ?></td></tr>
      <?php endforeach; ?>
    <?php elseif ($type === 'profit'): ?>
      <tr><th>Product</th><th>Qty Sold</th><th>Revenue</th><th>Cost</th><th>Profit</th></tr>
      <?php foreach ($rows as $r): ?>
      <tr><td><?= e($r['name']) ?></td><td><?= rtrim(rtrim(number_format($r['qty_sold'],3),'0'),'.') ?></td><td><?= money((float)$r['revenue']) ?></td><td><?= money((float)$r['cost']) ?></td><td style="color:<?= $r['profit']>=0?'var(--green-700)':'var(--red-500)' ?>; font-weight:700;"><?= money((float)$r['profit']) ?></td></tr>
      <?php endforeach; ?>
    <?php elseif ($type === 'stock'): ?>
      <tr><th>Product</th><th>Category</th><th>Stock Qty</th><th>Cost Price</th><th>Stock Value</th><th>Reorder Level</th></tr>
      <?php foreach ($rows as $r): ?>
      <tr><td><?= e($r['name']) ?></td><td><?= e($r['category']) ?></td><td><?= rtrim(rtrim(number_format($r['stock_qty'],3),'0'),'.') ?> <?= e($r['unit']) ?></td><td><?= money((float)$r['cost_price']) ?></td><td><?= money((float)$r['stock_value']) ?></td><td><?= rtrim(rtrim(number_format($r['reorder_level'],3),'0'),'.') ?></td></tr>
      <?php endforeach; ?>
    <?php elseif ($type === 'staff'): ?>
      <tr><th>Name</th><th>Role</th><th>Bills</th><th>Total Sales</th></tr>
      <?php foreach ($rows as $r): ?>
      <tr><td><?= e($r['name']) ?></td><td><?= e(t('role_' . $r['role'])) ?></td><td><?= (int)$r['bills'] ?></td><td><?= money((float)$r['total']) ?></td></tr>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php if (!$rows): ?><tr><td colspan="6" style="color:var(--ink-500);">No data for this range.</td></tr><?php endif; ?>
  </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
