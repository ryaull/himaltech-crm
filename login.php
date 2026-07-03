<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    $u = current_user();
    header('Location: ' . APP_URL . ($u['role'] === 'client' ? '/portal/dashboard.php' : '/index.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic brute-force throttle. Tracks recent failures in the session; after
    // 5 failures within the window, further attempts are refused for 15 minutes.
    // This is per-session (per-browser) — not a substitute for a WAF/fail2ban,
    // but it meaningfully slows credential-stuffing from a single client.
    $now = time();
    $window = 15 * 60;
    $maxFails = 5;
    $fails = $_SESSION['login_fails'] ?? ['count' => 0, 'first' => $now];
    if (($now - $fails['first']) > $window) {
        $fails = ['count' => 0, 'first' => $now]; // window elapsed, reset
    }

    if ($fails['count'] >= $maxFails) {
        $wait = ceil(($window - ($now - $fails['first'])) / 60);
        $error = "Too many attempts. Please wait about {$wait} minute(s) and try again.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND status = "active"');
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if ($row && password_verify($password, $row['password_hash'])) {
            unset($_SESSION['login_fails']);
            login_user($row);
            header('Location: ' . APP_URL . ($row['role'] === 'client' ? '/portal/dashboard.php' : '/index.php'));
            exit;
        }
        $fails['count']++;
        $_SESSION['login_fails'] = $fails;
        $error = 'Incorrect email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log in · Himal Tech CRM</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="brand"><span class="brand-mark"><img src="<?= APP_URL ?>/assets/img/logo.png" alt="Himal Tech"></span></div>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <div class="field"><label>Email</label><input type="email" name="email" required autofocus></div>
      <div class="field"><label>Password</label><input type="password" name="password" required></div>
      <button class="btn" type="submit">Log in</button>
    </form>
    <p style="margin-top:14px;text-align:center"><a href="<?= APP_URL ?>/forgot-password.php">Forgot password?</a></p>
  </div>
</div>
</body>
</html>
