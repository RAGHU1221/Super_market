<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: ' . base_url('dashboard.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = post('username', '');
    $password = post('password', '');
    if ($username === '' || $password === '') {
        $error = 'Please enter username and password. / பயனர்பெயர் மற்றும் கடவுச்சொல்லை உள்ளிடவும்.';
    } elseif (attempt_login($username, $password)) {
        header('Location: ' . base_url('dashboard.php'));
        exit;
    } else {
        $error = 'Invalid username or password. / பயனர்பெயர் அல்லது கடவுச்சொல் தவறு.';
    }
}
?>
<!DOCTYPE html>
<html lang="ta">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Login — Supermarket Suite</title>
<link rel="stylesheet" href="assets/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Catamaran:wght@400;600;800&family=Noto+Sans+Tamil:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="emoji">🛒</div>
    <h2>சூப்பர்மார்க்கெட் சூட்</h2>
    <p style="color:var(--ink-500); margin-top:-.4rem;">Supermarket Suite</p>
    <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <label>பயனர்பெயர் / Username</label>
      <input type="text" name="username" required autofocus>
      <label>கடவுச்சொல் / Password</label>
      <input type="password" name="password" required>
      <button class="btn btn-primary btn-block" type="submit">உள்நுழைய / Login</button>
    </form>
    <p style="font-size:.78rem; color:var(--ink-500); margin-top:1rem;">Default: admin / admin123 (first login: please change password)</p>
  </div>
</div>
</body>
</html>
