<?php
/**
 * Run this ONCE after uploading, to create your first admin account.
 * Visit: https://himaltech.co.uk/crm/setup.php
 * Then DELETE this file — it will refuse to run if an admin already exists,
 * but there's no reason to leave it on a live server.
 */
require_once __DIR__ . '/config/db.php';

$existing = $pdo->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetch();
if ($existing['c'] > 0) {
    die('An admin account already exists. Delete setup.php — it has done its job.');
}

$error = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $error = 'Please fill in a valid name, email, and a password of at least 8 characters.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email, $hash, 'admin']);
            $done = true;
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $error = 'That email is already registered. Use a different email.';
            } else {
                error_log('Admin setup failed: ' . $ex->getMessage());
                $error = 'Sorry, the account could not be created. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Himal Tech CRM — Setup</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="brand"><span class="brand-mark"><img src="<?= APP_URL ?>/assets/img/logo.png" alt="Himal Tech"></span></div>
    <?php if ($done): ?>
      <div class="alert alert-success">Admin account created. Delete setup.php now, then <a href="login.php">log in</a>.</div>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post">
        <div class="field"><label>Your name</label><input name="name" required></div>
        <div class="field"><label>Email</label><input type="email" name="email" required></div>
        <div class="field"><label>Password (min 8 characters)</label><input type="password" name="password" required minlength="8"></div>
        <button class="btn" type="submit">Create admin account</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
