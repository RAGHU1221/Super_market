<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('staff'); // admin only

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');

    if ($action === 'save') {
        $id = (int)post('id', 0);
        $name = post('name', '');
        $username = post('username', '');
        $role = in_array(post('role'), ['admin','manager','cashier']) ? post('role') : 'cashier';
        $phone = post('phone') ?: null;
        $password = post('password', '');

        if ($name === '' || $username === '') {
            flash('error', 'Name and username are required.');
        } elseif ($id === 0 && strlen($password) < 6) {
            flash('error', 'Password must be at least 6 characters for a new staff member.');
        } else {
            try {
                if ($id > 0) {
                    if ($password !== '') {
                        $stmt = db()->prepare("UPDATE users SET name=?, username=?, role=?, phone=?, password_hash=? WHERE id=?");
                        $stmt->execute([$name, $username, $role, $phone, password_hash($password, PASSWORD_BCRYPT), $id]);
                    } else {
                        $stmt = db()->prepare("UPDATE users SET name=?, username=?, role=?, phone=? WHERE id=?");
                        $stmt->execute([$name, $username, $role, $phone, $id]);
                    }
                    flash('success', 'Staff updated.');
                } else {
                    $stmt = db()->prepare("INSERT INTO users (name, username, password_hash, role, phone) VALUES (?,?,?,?,?)");
                    $stmt->execute([$name, $username, password_hash($password, PASSWORD_BCRYPT), $role, $phone]);
                    flash('success', 'Staff added.');
                }
            } catch (PDOException $e) {
                flash('error', str_contains($e->getMessage(), 'Duplicate') ? 'Username already taken.' : 'Save failed.');
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)post('id', 0);
        if ($id === (int)current_user()['id']) {
            flash('error', "You can't deactivate your own account.");
        } else {
            db()->prepare("UPDATE users SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
            flash('success', 'Status updated.');
        }
    }
    header('Location: staff.php');
    exit;
}

$editId = (int)get('edit', 0);
$editRow = $editId ? db()->query("SELECT * FROM users WHERE id=" . $editId)->fetch() : null;

$staff = db()->query("SELECT u.*,
    (SELECT COUNT(*) FROM sales s WHERE s.cashier_id=u.id AND s.status='completed' AND DATE(s.sale_date)=CURDATE()) today_bills,
    (SELECT COALESCE(SUM(total_amount),0) FROM sales s WHERE s.cashier_id=u.id AND s.status='completed' AND DATE(s.sale_date)=CURDATE()) today_sales
    FROM users u WHERE deleted_at IS NULL ORDER BY u.role, u.name")->fetchAll();

$pageTitle = t('staff');
$activeModule = 'staff';
require_once __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="card-title">➕ <?= $editRow ? 'Edit Staff' : e(t('add_new')) ?></div>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($editRow['id'] ?? 0) ?>">
    <div class="form-row">
      <div><label>Name</label><input type="text" name="name" value="<?= e($editRow['name'] ?? '') ?>" required></div>
      <div><label><?= e(t('username')) ?></label><input type="text" name="username" value="<?= e($editRow['username'] ?? '') ?>" required></div>
      <div><label>Role</label><select name="role">
          <?php foreach (['cashier'=>t('role_cashier'),'manager'=>t('role_manager'),'admin'=>t('role_admin')] as $val=>$label): ?>
            <option value="<?= $val ?>" <?= (($editRow['role'] ?? 'cashier') === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label>Phone</label><input type="text" name="phone" value="<?= e($editRow['phone'] ?? '') ?>"></div>
    </div>
    <label><?= e(t('password')) ?> <?= $editRow ? '(leave blank to keep unchanged)' : '' ?></label>
    <input type="password" name="password" <?= $editRow ? '' : 'required minlength="6"' ?>>
    <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
    <?php if ($editRow): ?><a class="btn" href="staff.php"><?= e(t('cancel')) ?></a><?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="card-title"><?= e(t('staff')) ?> (<?= count($staff) ?>)</div>
  <div class="table-scroll">
  <table class="data-table">
    <tr><th>Name</th><th><?= e(t('username')) ?></th><th>Role</th><th>Phone</th><th>Today's Bills</th><th>Today's Sales</th><th>Status</th><th></th></tr>
    <?php foreach ($staff as $s): ?>
    <tr>
      <td><?= e($s['name']) ?></td>
      <td><?= e($s['username']) ?></td>
      <td><span class="badge badge-gold"><?= e(t('role_' . $s['role'])) ?></span></td>
      <td><?= e($s['phone']) ?></td>
      <td><?= (int)$s['today_bills'] ?></td>
      <td><?= money((float)$s['today_sales']) ?></td>
      <td><span class="badge <?= $s['is_active'] ? 'badge-green' : 'badge-red' ?>"><?= $s['is_active'] ? 'Active' : 'Inactive' ?></span></td>
      <td>
        <a class="btn btn-sm" href="?edit=<?= (int)$s['id'] ?>"><?= e(t('edit')) ?></a>
        <form method="post" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <button class="btn btn-sm" type="submit"><?= $s['is_active'] ? 'Deactivate' : 'Activate' ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
