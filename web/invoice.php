<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = (int)get('id', 0);
$stmt = db()->prepare("SELECT s.*, c.name AS customer_name, c.phone AS customer_phone, u.name AS cashier_name
    FROM sales s LEFT JOIN customers c ON c.id = s.customer_id LEFT JOIN users u ON u.id = s.cashier_id WHERE s.id = ?");
$stmt->execute([$id]);
$sale = $stmt->fetch();
if (!$sale) { die('Invoice not found.'); }

$itemStmt = db()->prepare("SELECT si.*, p.name AS product_name, p.name_ta AS product_name_ta, p.hsn_code, p.unit
    FROM sale_items si LEFT JOIN products p ON p.id = si.product_id WHERE si.sale_id = ?");
$itemStmt->execute([$id]);
$items = $itemStmt->fetchAll();

$store = get_setting();
$autoPrint = get('print', '') === '1';
$paperSize = $store['receipt_paper_size'] ?? 'thermal80';
?>
<!DOCTYPE html>
<html lang="ta">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice <?= e($sale['invoice_no']) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body style="background:#eee;">
<div class="no-print" style="padding:1rem; display:flex; gap:.6rem; justify-content:center; flex-wrap:wrap;">
  <a class="btn" href="billing.php">← Back to Billing</a>
  <button class="btn btn-primary" onclick="window.print()">🖨️ Print</button>
  <button class="btn btn-gold" onclick="downloadPdf()">⬇️ Download PDF</button>
  <a class="btn" target="_blank" href="https://wa.me/?text=<?= urlencode('Invoice ' . $sale['invoice_no'] . ' — Total ' . money((float)$sale['total_amount'])) ?>">📱 Share (WhatsApp)</a>
</div>

<div id="receipt-capture" style="max-width:<?= $paperSize === 'a4' ? '750px' : '320px' ?>; margin:0 auto 2rem; background:#fff; padding:16px; box-shadow:0 2px 10px rgba(0,0,0,.15);">
  <div class="receipt <?= $paperSize === 'a4' ? 'a4' : '' ?>">
    <div class="center">
      <?php if (!empty($store['logo_path'])): ?><img src="<?= e($store['logo_path']) ?>" style="max-height:50px;"><?php endif; ?>
      <h3 style="margin:.2rem 0;"><?= e($store['store_name']) ?></h3>
      <?php if (!empty($store['store_name_ta'])): ?><div><?= e($store['store_name_ta']) ?></div><?php endif; ?>
      <div><?= e($store['address']) ?></div>
      <div><?php if($store['phone']): ?>Ph: <?= e($store['phone']) ?><?php endif; ?></div>
      <?php if (!empty($store['gstin'])): ?><div>GSTIN: <?= e($store['gstin']) ?></div><?php endif; ?>
    </div>
    <hr>
    <div>Invoice: <b><?= e($sale['invoice_no']) ?></b></div>
    <div>Date: <?= date('d-m-Y h:i A', strtotime($sale['sale_date'])) ?></div>
    <div>Cashier: <?= e($sale['cashier_name'] ?? '-') ?></div>
    <?php if ($sale['customer_name']): ?><div>Customer: <?= e($sale['customer_name']) ?> <?= $sale['customer_phone'] ? '('.e($sale['customer_phone']).')' : '' ?></div><?php endif; ?>
    <hr>
    <table>
      <thead><tr><th>Item</th><th class="right">Qty</th><th class="right">Rate</th><th class="right">Amt</th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td><?= e($it['product_name']) ?><?php if($it['hsn_code']):?> <small>(<?= e($it['hsn_code']) ?>)</small><?php endif; ?></td>
          <td class="right"><?= rtrim(rtrim(number_format($it['qty'],3),'0'),'.') ?> <?= e($it['unit']) ?></td>
          <td class="right"><?= number_format($it['unit_price'],2) ?></td>
          <td class="right"><?= number_format($it['line_total'],2) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <hr>
    <div style="display:flex; justify-content:space-between;"><span>Subtotal</span><span><?= money((float)$sale['subtotal']) ?></span></div>
    <div style="display:flex; justify-content:space-between;"><span>Discount</span><span>-<?= money((float)$sale['discount_amount']) ?></span></div>
    <div style="display:flex; justify-content:space-between;"><span>GST</span><span><?= money((float)$sale['tax_amount']) ?></span></div>
    <div style="display:flex; justify-content:space-between; font-weight:800; font-size:1.05em;"><span>TOTAL</span><span><?= money((float)$sale['total_amount']) ?></span></div>
    <div style="display:flex; justify-content:space-between;"><span>Paid (<?= e(strtoupper($sale['payment_mode'])) ?>)</span><span><?= money((float)$sale['paid_amount']) ?></span></div>
    <?php if ((float)$sale['balance_amount'] != 0): ?>
    <div style="display:flex; justify-content:space-between;"><span>Balance</span><span><?= money((float)$sale['balance_amount']) ?></span></div>
    <?php endif; ?>
    <hr>
    <div class="center">Thank you! Visit again 🙏<br>மீண்டும் வருக!</div>
  </div>
</div>

<script>
function downloadPdf() {
  const el = document.getElementById('receipt-capture');
  html2canvas(el, { scale: 2 }).then(canvas => {
    const { jsPDF } = window.jspdf;
    const imgData = canvas.toDataURL('image/png');
    const widthMm = <?= $paperSize === 'a4' ? '210' : '80' ?>;
    const heightMm = widthMm * (canvas.height / canvas.width);
    const pdf = new jsPDF({ unit: 'mm', format: [widthMm, heightMm] });
    pdf.addImage(imgData, 'PNG', 0, 0, widthMm, heightMm);
    pdf.save('<?= e($sale['invoice_no']) ?>.pdf');
  });
}
<?php if ($autoPrint): ?>
window.addEventListener('load', () => setTimeout(() => window.print(), 400));
<?php endif; ?>
</script>
</body>
</html>
