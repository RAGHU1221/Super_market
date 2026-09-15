<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_permission('categories');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');
    if ($action === 'save') {
        $id = (int)post('id', 0);
        $name = post('name', '');
        $nameTa = post('name_ta', '');
        if ($name === '') {
            flash('error', 'Name is required.');
        } elseif ($id > 0) {
            $stmt = db()->prepare("UPDATE categories SET name=?, name_ta=? WHERE id=?");
            $stmt->execute([$name, $nameTa, $id]);
            flash('success', 'Category updated.');
        } else {
            $stmt = db()->prepare("INSERT INTO categories (name, name_ta) VALUES (?, ?)");
            $stmt->execute([$name, $nameTa]);
            flash('success', 'Category added.');
        }
    } elseif ($action === 'delete') {
        $id = (int)post('id', 0);
        db()->prepare("UPDATE categories SET deleted_at = NOW(), is_active = 0 WHERE id = ?")->execute([$id]);
        flash('success', 'Category deleted.');
    }
    header('Location: categories.php');
    exit;
}

$categories = db()->query("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id AND p.deleted_at IS NULL) product_count FROM categories c WHERE c.deleted_at IS NULL ORDER BY c.name")->fetchAll();

$pageTitle = t('categories');
$activeModule = 'categories';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-title">
    ➕ <?= e(t('add_new')) ?>
  </div>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <div class="form-row">
      <div><label>Name (English)</label><input type="text" name="name" required></div>
      <div><label>பெயர் (Tamil)</label><input type="text" name="name_ta"></div>
    </div>
    <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
  </form>
</div>

<div class="card">
  <div class="card-title"><?= e(t('categories')) ?> (<?= count($categories) ?>)</div>
  <div class="table-scroll">
  <table class="data-table">
    <tr><th>#</th><th>English</th><th>Tamil</th><th>Products</th><th></th></tr>
    <?php foreach ($categories as $c): ?>
    <tr>
      <td><?= (int)$c['id'] ?></td>
      <td><?= e($c['name']) ?></td>
      <td><?= e($c['name_ta']) ?></td>
      <td><?= (int)$c['product_count'] ?></td>
      <td>
        <form method="post" style="display:inline" onsubmit="return confirmDelete('Delete this category?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button class="btn btn-sm btn-danger" type="submit"><?= e(t('delete')) ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
