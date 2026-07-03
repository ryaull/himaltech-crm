<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$reset = $token !== '' ? find_valid_reset_token($pdo, $token) : null;

$error = '';
$done = false;

if (!$reset) {
    $error = 'This link is invalid or has expired. Ask an admin to send you a new one, or use "Forgot password" on the login page.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $reset['user_id']]);
        consume_reset_token($pdo, $reset['id']);
        $done = true;
    }
}

$pageTitle = $reset && $reset['purpose'] === 'invite' ? 'Set your password' : 'Reset your password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= e($pageTitle) ?> · Himal Tech CRM</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= defined('APP_URL') ? APP_URL : '' ?>/assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="brand"><span class="brand-mark"><img src="<?= APP_URL ?>/assets/img/logo.png" alt="Himal Tech"></span></div>
    <?php if ($done): ?>
      <h1><?= $reset['purpose'] === 'invite' ? 'Password set' : 'Password updated' ?></h1>
      <p>You're all set, <?= e($reset['name']) ?>. <a href="<?= APP_URL ?>/login.php">Log in now</a>.</p>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <?php if ($reset): ?>
        <p class="text-muted">Hi <?= e($reset['name']) ?> — choose a password for <?= e($reset['email']) ?>.</p>
        <form method="post">
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <div class="field"><label>New password</label><input type="password" name="password" required minlength="8" autofocus></div>
          <div class="field"><label>Confirm password</label><input type="password" name="password_confirm" required minlength="8"></div>
          <button class="btn" type="submit">Set password</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
