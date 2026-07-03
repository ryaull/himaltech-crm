<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        // Only actually send if the account exists — but always show the same
        // confirmation message either way, so this form can't be used to check
        // which emails are registered in the system.
        if ($user) {
            $token = create_reset_token($pdo, $user['id'], 'reset', 2);
            notify_password_reset($user['email'], $user['name'], $token);
        }
    }
    $sent = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot password · Himal Tech CRM</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="brand"><span class="brand-mark"><img src="<?= APP_URL ?>/assets/img/logo.png" alt="Himal Tech"></span></div>
    <?php if ($sent): ?>
      <h1>Check your email</h1>
      <p class="text-muted">If an account exists for that email, we've sent a link to reset the password. The link expires in 2 hours.</p>
      <p><a href="<?= APP_URL ?>/login.php">Back to login</a></p>
    <?php else: ?>
      <h1>Forgot password</h1>
      <p class="text-muted">Enter your account email and we'll send you a reset link.</p>
      <form method="post">
        <div class="field"><label>Email</label><input type="email" name="email" required autofocus></div>
        <button class="btn" type="submit">Send reset link</button>
      </form>
      <p style="margin-top:14px"><a href="<?= APP_URL ?>/login.php">Back to login</a></p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
