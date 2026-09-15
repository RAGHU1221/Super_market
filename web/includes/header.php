<?php
/**
 * Shared header - expects $pageTitle and $activeModule to be set before include
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_login();

if (isset($_GET['lang']) && in_array($_GET['lang'], ['ta', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}

$pageTitle = $pageTitle ?? t('dashboard');
$activeModule = $activeModule ?? '';
$user = current_user();
$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="<?= e($_SESSION['lang'] ?? 'ta') ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?> — <?= e(t('app_name')) ?></title>
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Catamaran:wght@400;600;800&family=Noto+Sans+Tamil:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand"><span class="emoji">🛒</span> <span><?= e(t('app_name')) ?></span></div>
    <nav>
      <?php
      $links = [
        'dashboard'      => ['dashboard.php', t('dashboard'), '🏠'],
        'billing'        => ['billing.php', t('billing'), '💳'],
        'products'       => ['products/index.php', t('products'), '📦'],
        'categories'     => ['products/categories.php', t('categories'), '🏷️'],
        'suppliers'      => ['products/suppliers.php', t('suppliers'), '🚚'],
        'purchases'      => ['products/purchases.php', t('purchases'), '📥'],
        'stock'          => ['products/stock_adjust.php', t('stock'), '📊'],
        'sales_history'  => ['sales_history.php', t('sales_history'), '🧾'],
        'returns'        => ['returns.php', t('returns'), '↩️'],
        'staff'          => ['staff.php', t('staff'), '👥'],
        'reports'        => ['reports/index.php', t('reports'), '📈'],
        'settings'       => ['settings.php', t('settings'), '⚙️'],
      ];
      foreach ($links as $key => [$href, $label, $icon]) {
          if (!can($key)) continue;
          $cls = ($activeModule === $key) ? 'active' : '';
          echo '<a class="' . $cls . '" href="' . base_url($href) . '">' . $icon . ' <span>' . e($label) . '</span></a>';
      }
      ?>
    </nav>
    <div class="role-tag">👤 <?= e($user['name']) ?><br><?= e(t('role_' . $user['role'])) ?></div>
    <nav><a href="<?= base_url('logout.php') ?>">🚪 <?= e(t('logout')) ?></a></nav>
  </aside>
  <main class="main">
    <div class="topbar">
      <h3 style="margin:0;"><?= e($pageTitle) ?></h3>
      <div class="lang-switch">
        <a href="?lang=ta" class="<?= (($_SESSION['lang'] ?? 'ta') === 'ta') ? 'active' : '' ?>">தமிழ்</a>
        <a href="?lang=en" class="<?= (($_SESSION['lang'] ?? 'ta') === 'en') ? 'active' : '' ?>">English</a>
      </div>
    </div>
    <?php foreach ($flashes as $f): ?>
      <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
