<?php
$user = current_user();
$path = $_SERVER['SCRIPT_NAME'];
function nav_active(string $needle, string $path): string {
    return str_contains($path, $needle) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' · ' : '' ?>Himal Tech CRM</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand">
      <span class="brand-mark"><img src="<?= APP_URL ?>/assets/img/logo.png" alt="Himal Tech"></span>
    </div>
    <?php if ($user && $user['role'] !== 'client'): ?>
    <nav class="nav">
      <a href="<?= APP_URL ?>/index.php" class="<?= nav_active('/index.php', $path) ?>">Dashboard</a>
      <a href="<?= APP_URL ?>/leads/list.php" class="<?= nav_active('/leads/', $path) ?>">Leads</a>
      <a href="<?= APP_URL ?>/clients/list.php" class="<?= nav_active('/clients/', $path) ?>">Clients</a>
      <a href="<?= APP_URL ?>/quotes/list.php" class="<?= nav_active('/quotes/', $path) ?>">Quotes</a>
      <a href="<?= APP_URL ?>/tickets/list.php" class="<?= nav_active('/tickets/', $path) ?>">Tickets</a>
      <a href="<?= APP_URL ?>/catalog/list.php" class="<?= nav_active('/catalog/', $path) ?>">Catalog</a>
      <?php if ($user['role'] === 'admin'): ?>
      <a href="<?= APP_URL ?>/users/list.php" class="<?= nav_active('/users/', $path) ?>">Staff</a>
      <?php endif; ?>
    </nav>
    <?php elseif ($user): ?>
    <nav class="nav">
      <a href="<?= APP_URL ?>/portal/dashboard.php" class="<?= nav_active('dashboard.php', $path) ?>">My Quotes</a>
      <a href="<?= APP_URL ?>/portal/tickets.php" class="<?= nav_active('ticket', $path) ?>">My Requests</a>
    </nav>
    <?php endif; ?>
    <?php if ($user): ?>
    <div class="sidebar-footer">
      <div class="user-chip">
        <div class="user-name"><?= e($user['name']) ?></div>
        <div class="user-role"><?= e(ucfirst($user['role'])) ?></div>
      </div>
      <a href="<?= APP_URL ?>/account/change-password.php" class="<?= nav_active('change-password', $path) ?>">Change password</a>
      <a href="<?= APP_URL ?>/logout.php" class="logout-link">Log out</a>
    </div>
    <?php endif; ?>
  </aside>
  <main class="content">
