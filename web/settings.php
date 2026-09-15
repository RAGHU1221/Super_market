<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('settings'); // admin only

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action', 'store');

    if ($action === 'store') {
        $stmt = db()->prepare("UPDATE store_settings SET store_name=?, store_name_ta=?, address=?, phone=?, email=?, gstin=?, fssai_no=?,
            currency_symbol=?, default_tax_percent=?, invoice_prefix=?, receipt_paper_size=?, low_stock_threshold=?, expiry_alert_days=?, default_language=? WHERE id=1");
        $stmt->execute([
            post('store_name'), post('store_name_ta'), post('address'), post('phone'), post('email'), post('gstin'), post('fssai_no'),
            post('currency_symbol', '₹'), (float)post('default_tax_percent', 0), post('invoice_prefix', 'INV'),
            post('receipt_paper_size', 'thermal80'), (int)post('low_stock_threshold', 10), (int)post('expiry_alert_days', 15), post('default_language', 'ta'),
        ]);
        flash('success', 'Settings saved.');
    } elseif ($action === 'change_password') {
        $current = post('current_password', '');
        $new = post('new_password', '');
        $stmt = db()->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([current_user()['id']]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($current, $row['password_hash'])) {
            flash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 6) {
            flash('error', 'New password must be at least 6 characters.');
        } else {
            db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([password_hash($new, PASSWORD_BCRYPT), current_user()['id']]);
            flash('success', 'Password changed.');
        }
    }
    header('Location: settings.php');
    exit;
}

$s = get_setting();
$pageTitle = t('settings');
$activeModule = 'settings';
require_once __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="card-title">🏪 Store Details</div>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="store">
    <div class="form-row">
      <div><label>Store Name (English)</label><input type="text" name="store_name" value="<?= e($s['store_name']) ?>"></div>
      <div><label>கடை பெயர் (Tamil)</label><input type="text" name="store_name_ta" value="<?= e($s['store_name_ta']) ?>"></div>
    </div>
    <label>Address</label><textarea name="address" rows="2"><?= e($s['address']) ?></textarea>
    <div class="form-row">
      <div><label>Phone</label><input type="text" name="phone" value="<?= e($s['phone']) ?>"></div>
      <div><label>Email</label><input type="email" name="email" value="<?= e($s['email']) ?>"></div>
      <div><label>GSTIN</label><input type="text" name="gstin" value="<?= e($s['gstin']) ?>"></div>
      <div><label>FSSAI No.</label><input type="text" name="fssai_no" value="<?= e($s['fssai_no']) ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Currency Symbol</label><input type="text" name="currency_symbol" value="<?= e($s['currency_symbol']) ?>"></div>
      <div><label>Default GST %</label><input type="number" step="0.01" name="default_tax_percent" value="<?= e($s['default_tax_percent']) ?>"></div>
      <div><label>Invoice Prefix</label><input type="text" name="invoice_prefix" value="<?= e($s['invoice_prefix']) ?>"></div>
      <div><label>Receipt Paper Size</label><select name="receipt_paper_size">
          <option value="thermal80" <?= $s['receipt_paper_size']==='thermal80'?'selected':'' ?>>Thermal 80mm</option>
          <option value="a4" <?= $s['receipt_paper_size']==='a4'?'selected':'' ?>>A4</option>
        </select></div>
    </div>
    <div class="form-row">
      <div><label>Low Stock Threshold</label><input type="number" name="low_stock_threshold" value="<?= e($s['low_stock_threshold']) ?>"></div>
      <div><label>Expiry Alert (days before)</label><input type="number" name="expiry_alert_days" value="<?= e($s['expiry_alert_days']) ?>"></div>
      <div><label>Default Language</label><select name="default_language">
          <option value="ta" <?= $s['default_language']==='ta'?'selected':'' ?>>தமிழ்</option>
          <option value="en" <?= $s['default_language']==='en'?'selected':'' ?>>English</option>
        </select></div>
    </div>
    <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
  </form>
</div>

<div class="card">
  <div class="card-title">🔒 Change My Password</div>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_password">
    <div class="form-row">
      <div><label>Current Password</label><input type="password" name="current_password" required></div>
      <div><label>New Password</label><input type="password" name="new_password" required minlength="6"></div>
    </div>
    <button class="btn btn-primary" type="submit">Change Password</button>
  </form>
</div>

<div class="card">
  <div class="card-title">🔌 Mobile / Desktop App Sync</div>
  <p>Use these settings in the Flutter billing app's login screen to connect it to this server:</p>
  <table class="data-table">
    <tr><td>API Base URL</td><td><code><?= e(base_url('api/mobile/')) ?: 'https://yourdomain.com/api/mobile/' ?></code></td></tr>
    <tr><td>Login</td><td>Use your staff username & password (same as web login)</td></tr>
  </table>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
