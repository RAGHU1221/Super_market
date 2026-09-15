<?php
/**
 * One-time install wizard.
 * 1) Reads DB credentials from ../config/db.php (edit that file first!)
 * 2) Runs schema.sql + seed.sql
 * 3) Sets a fresh bcrypt hash for the admin password you choose
 * DELETE THIS install/ FOLDER after installing, for security.
 */
require_once __DIR__ . '/../config/db.php';

$step = $_GET['step'] ?? 'form';
$message = '';
$error = '';

function run_sql_file(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    // naive splitter on ; at end of line - fine for our schema (no stored procs)
    $statements = array_filter(array_map('trim', explode(";\n", $sql)));
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || strpos($stmt, '--') === 0) continue;
        $pdo->exec($stmt);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminUser = trim($_POST['admin_username'] ?? 'admin');
    $adminPass = $_POST['admin_password'] ?? '';
    $storeName = trim($_POST['store_name'] ?? 'My Supermarket');

    if (strlen($adminPass) < 6) {
        $error = 'Admin password must be at least 6 characters.';
    } else {
        try {
            $pdo = db();
            run_sql_file($pdo, __DIR__ . '/schema.sql');
            run_sql_file($pdo, __DIR__ . '/seed.sql');

            // set the real bcrypt hash + chosen username + store name
            $hash = password_hash($adminPass, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET username = ?, password_hash = ? WHERE username = 'admin'")
                ->execute([$adminUser, $hash]);
            $pdo->prepare("UPDATE store_settings SET store_name = ? WHERE id = 1")
                ->execute([$storeName]);

            $message = 'Installation complete! You can now log in.';
            $step = 'done';
        } catch (Exception $e) {
            $error = 'Install failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Install — Supermarket Suite</title>
<style>body{font-family:sans-serif;max-width:560px;margin:40px auto;background:#f4ecdd;color:#26261f;padding:0 1rem}
.card{background:#fffdf9;border-radius:14px;padding:1.5rem;box-shadow:0 6px 18px rgba(0,0,0,.1)}
input{width:100%;padding:.6rem;margin-bottom:1rem;border-radius:8px;border:1px solid #ccc}
button{padding:.7rem 1.4rem;border:none;border-radius:8px;background:#2d6a4f;color:#fff;font-weight:700;cursor:pointer}
.err{background:#fde2dd;color:#a3301f;padding:.7rem;border-radius:8px;margin-bottom:1rem}
.ok{background:#d8f3dc;color:#1b4332;padding:.7rem;border-radius:8px;margin-bottom:1rem}
</style></head><body>
<h2>🛒 Supermarket Suite — Installer</h2>
<div class="card">
<?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($step === 'done'): ?>
  <div class="ok"><?= htmlspecialchars($message) ?></div>
  <p><a href="../login.php"><button>Go to Login →</button></a></p>
  <p style="color:#a3301f;font-weight:700;">⚠️ For security, delete the /install folder now.</p>
<?php else: ?>
  <p>Before submitting, make sure <code>config/db.php</code> has your real hosting DB host/name/user/password.</p>
  <form method="post">
    <label>Store name</label>
    <input type="text" name="store_name" value="My Supermarket" required>
    <label>Choose admin username</label>
    <input type="text" name="admin_username" value="admin" required>
    <label>Choose admin password (min 6 chars)</label>
    <input type="password" name="admin_password" required minlength="6">
    <button type="submit">Install Database</button>
  </form>
<?php endif; ?>
</div>
</body></html>
