<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_permission('products');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');

    if ($action === 'save') {
        $id = (int)post('id', 0);
        $sku = post('sku', '');
        if ($sku === '') $sku = generate_code('SKU');
        $fields = [
            'sku' => $sku,
            'barcode' => post('barcode') ?: null,
            'name' => post('name', ''),
            'name_ta' => post('name_ta') ?: null,
            'category_id' => post('category_id') ?: null,
            'supplier_id' => post('supplier_id') ?: null,
            'unit' => post('unit', 'pcs'),
            'hsn_code' => post('hsn_code') ?: null,
            'tax_percent' => (float)post('tax_percent', 0),
            'cost_price' => (float)post('cost_price', 0),
            'selling_price' => (float)post('selling_price', 0),
            'mrp' => post('mrp') !== '' ? (float)post('mrp') : null,
            'reorder_level' => (float)post('reorder_level', 5),
            'expiry_date' => post('expiry_date') ?: null,
            'batch_no' => post('batch_no') ?: null,
        ];

        if ($fields['name'] === '') {
            flash('error', 'Product name is required.');
        } elseif ($id > 0) {
            $sql = "UPDATE products SET sku=:sku, barcode=:barcode, name=:name, name_ta=:name_ta, category_id=:category_id,
                    supplier_id=:supplier_id, unit=:unit, hsn_code=:hsn_code, tax_percent=:tax_percent, cost_price=:cost_price,
                    selling_price=:selling_price, mrp=:mrp, reorder_level=:reorder_level, expiry_date=:expiry_date, batch_no=:batch_no
                    WHERE id=:id";
            $fields['id'] = $id;
            db()->prepare($sql)->execute($fields);
            flash('success', 'Product updated.');
        } else {
            $fields['stock_qty'] = 0; // stock is added via Purchases
            $cols = implode(',', array_keys($fields));
            $ph = ':' . implode(', :', array_keys($fields));
            db()->prepare("INSERT INTO products ($cols) VALUES ($ph)")->execute($fields);
            flash('success', 'Product added. Add opening stock via Purchases.');
        }
    } elseif ($action === 'delete') {
        $id = (int)post('id', 0);
        db()->prepare("UPDATE products SET deleted_at = NOW(), is_active = 0 WHERE id = ?")->execute([$id]);
        flash('success', 'Product deleted.');
    }
    header('Location: index.php');
    exit;
}

$search = get('q', '');
$filter = get('filter', '');
$editId = (int)get('edit', 0);
$editRow = $editId ? db()->query("SELECT * FROM products WHERE id=" . $editId)->fetch() : null;

$where = "WHERE p.deleted_at IS NULL";
$params = [];
if ($search !== '') {
    $where .= " AND (p.name LIKE ? OR p.name_ta LIKE ? OR p.barcode LIKE ? OR p.sku LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like, $like];
}
if ($filter === 'low_stock') {
    $where .= " AND p.stock_qty <= p.reorder_level";
}

$stmt = db()->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id $where ORDER BY p.name LIMIT 300");
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = db()->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$suppliers = db()->query("SELECT * FROM suppliers WHERE deleted_at IS NULL ORDER BY name")->fetchAll();

$pageTitle = t('products');
$activeModule = 'products';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-title">➕ <?= $editRow ? 'Edit Product' : e(t('add_new')) ?></div>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($editRow['id'] ?? 0) ?>">
    <div class="form-row">
      <div><label>Name (English)</label><input type="text" name="name" value="<?= e($editRow['name'] ?? '') ?>" required></div>
      <div><label>பெயர் (Tamil)</label><input type="text" name="name_ta" value="<?= e($editRow['name_ta'] ?? '') ?>"></div>
      <div><label><?= e(t('barcode')) ?></label><input type="text" name="barcode" value="<?= e($editRow['barcode'] ?? '') ?>" placeholder="Scan or type"></div>
    </div>
    <div class="form-row">
      <div><label>SKU (auto if blank)</label><input type="text" name="sku" value="<?= e($editRow['sku'] ?? '') ?>"></div>
      <div><label>Category</label><select name="category_id">
          <option value="">--</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (($editRow['category_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label>Supplier</label><select name="supplier_id">
          <option value="">--</option>
          <?php foreach ($suppliers as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= (($editRow['supplier_id'] ?? null) == $s['id']) ? 'selected' : '' ?>><?= e($s['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label>Unit</label><select name="unit">
        <?php foreach (['pcs','kg','g','ltr','ml','box','pack','dozen'] as $u): ?>
          <option value="<?= $u ?>" <?= (($editRow['unit'] ?? 'pcs') === $u) ? 'selected' : '' ?>><?= $u ?></option>
        <?php endforeach; ?>
      </select></div>
    </div>
    <div class="form-row">
      <div><label>Cost Price</label><input type="number" step="0.01" name="cost_price" value="<?= e($editRow['cost_price'] ?? '0') ?>"></div>
      <div><label>Selling Price</label><input type="number" step="0.01" name="selling_price" value="<?= e($editRow['selling_price'] ?? '0') ?>" required></div>
      <div><label>MRP</label><input type="number" step="0.01" name="mrp" value="<?= e($editRow['mrp'] ?? '') ?>"></div>
      <div><label>GST %</label><input type="number" step="0.01" name="tax_percent" value="<?= e($editRow['tax_percent'] ?? '0') ?>"></div>
    </div>
    <div class="form-row">
      <div><label>HSN Code</label><input type="text" name="hsn_code" value="<?= e($editRow['hsn_code'] ?? '') ?>"></div>
      <div><label>Reorder Level</label><input type="number" step="0.01" name="reorder_level" value="<?= e($editRow['reorder_level'] ?? '5') ?>"></div>
      <div><label>Batch No</label><input type="text" name="batch_no" value="<?= e($editRow['batch_no'] ?? '') ?>"></div>
      <div><label>Expiry Date</label><input type="date" name="expiry_date" value="<?= e($editRow['expiry_date'] ?? '') ?>"></div>
    </div>
    <?php if (!$editRow): ?><p style="color:var(--ink-500); font-size:.85rem;">New products start at 0 stock — add opening stock via <a href="purchases.php">Purchases</a>.</p><?php endif; ?>
    <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
    <?php if ($editRow): ?><a class="btn" href="index.php"><?= e(t('cancel')) ?></a><?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="card-title">
    <?= e(t('products')) ?> (<?= count($products) ?>)
    <form method="get" style="display:flex; gap:.5rem;">
      <input type="search" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('search')) ?> name / barcode / SKU" style="margin:0; min-width:220px;">
      <button class="btn btn-sm" type="submit"><?= e(t('search')) ?></button>
    </form>
  </div>
  <div class="table-scroll">
  <table class="data-table">
    <tr><th>Name</th><th><?= e(t('barcode')) ?></th><th>Category</th><th>Cost</th><th>Price</th><th>GST%</th><th><?= e(t('qty')) ?></th><th></th></tr>
    <?php foreach ($products as $p): $low = $p['stock_qty'] <= $p['reorder_level']; ?>
    <tr>
      <td><?= e($p['name']) ?><?php if($p['name_ta']): ?><br><small style="color:var(--ink-500)"><?= e($p['name_ta']) ?></small><?php endif; ?></td>
      <td><?= e($p['barcode']) ?></td>
      <td><?= e($p['category_name']) ?></td>
      <td><?= money((float)$p['cost_price']) ?></td>
      <td><?= money((float)$p['selling_price']) ?></td>
      <td><?= e($p['tax_percent']) ?>%</td>
      <td><span class="badge <?= $low ? 'badge-red' : 'badge-green' ?>"><?= rtrim(rtrim(number_format($p['stock_qty'],3),'0'),'.') ?: '0' ?> <?= e($p['unit']) ?></span></td>
      <td>
        <a class="btn btn-sm" href="?edit=<?= (int)$p['id'] ?>"><?= e(t('edit')) ?></a>
        <form method="post" style="display:inline" onsubmit="return confirmDelete('Delete this product?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn btn-sm btn-danger" type="submit"><?= e(t('delete')) ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
