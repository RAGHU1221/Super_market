<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_permission('suppliers');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');
    if ($action === 'save') {
        $id = (int)post('id', 0);
        $data = [post('name',''), post('contact_person',''), post('phone',''), post('email',''), post('address',''), post('gstin','')];
        if ($data[0] === '') {
            flash('error', 'Supplier name is required.');
        } elseif ($id > 0) {
            $stmt = db()->prepare("UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, address=?, gstin=? WHERE id=?");
            $stmt->execute([...$data, $id]);
            flash('success', 'Supplier updated.');
        } else {
            $stmt = db()->prepare("INSERT INTO suppliers (name, contact_person, phone, email, address, gstin) VALUES (?,?,?,?,?,?)");
            $stmt->execute($data);
            flash('success', 'Supplier added.');
        }
    } elseif ($action === 'delete') {
        $id = (int)post('id', 0);
        db()->prepare("UPDATE suppliers SET deleted_at = NOW(), is_active = 0 WHERE id = ?")->execute([$id]);
        flash('success', 'Supplier deleted.');
    }
    header('Location: suppliers.php');
    exit;
}

$editId = (int)get('edit', 0);
$editRow = $editId ? db()->query("SELECT * FROM suppliers WHERE id=" . $editId)->fetch() : null;
$suppliers = db()->query("SELECT s.*, (SELECT COUNT(*) FROM products p WHERE p.supplier_id=s.id AND p.deleted_at IS NULL) product_count FROM suppliers s WHERE s.deleted_at IS NULL ORDER BY s.name")->fetchAll();

$pageTitle = t('suppliers');
$activeModule = 'suppliers';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-title">➕ <?= $editRow ? 'Edit Supplier' : e(t('add_new')) ?></div>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($editRow['id'] ?? 0) ?>">
    <div class="form-row">
      <div><label>Supplier Name</label><input type="text" name="name" value="<?= e($editRow['name'] ?? '') ?>" required></div>
      <div><label>Contact Person</label><input type="text" name="contact_person" value="<?= e($editRow['contact_person'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Phone</label><input type="text" name="phone" value="<?= e($editRow['phone'] ?? '') ?>"></div>
      <div><label>Email</label><input type="email" name="email" value="<?= e($editRow['email'] ?? '') ?>"></div>
      <div><label>GSTIN</label><input type="text" name="gstin" value="<?= e($editRow['gstin'] ?? '') ?>"></div>
    </div>
    <label>Address</label><textarea name="address" rows="2"><?= e($editRow['address'] ?? '') ?></textarea>
    <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
    <?php if ($editRow): ?><a class="btn" href="suppliers.php"><?= e(t('cancel')) ?></a><?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="card-title"><?= e(t('suppliers')) ?> (<?= count($suppliers) ?>)</div>
  <div class="table-scroll">
  <table class="data-table">
    <tr><th>Name</th><th>Contact</th><th>Phone</th><th>GSTIN</th><th>Products</th><th></th></tr>
    <?php foreach ($suppliers as $s): ?>
    <tr>
      <td><?= e($s['name']) ?></td>
      <td><?= e($s['contact_person']) ?></td>
      <td><?= e($s['phone']) ?></td>
      <td><?= e($s['gstin']) ?></td>
      <td><?= (int)$s['product_count'] ?></td>
      <td>
        <a class="btn btn-sm" href="?edit=<?= (int)$s['id'] ?>"><?= e(t('edit')) ?></a>
        <form method="post" style="display:inline" onsubmit="return confirmDelete('Delete this supplier?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <button class="btn btn-sm btn-danger" type="submit"><?= e(t('delete')) ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
