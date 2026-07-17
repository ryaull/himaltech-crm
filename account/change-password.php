<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$user = current_user();
$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['new_password_confirm'] ?? '';

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($current, $row['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $user['id']]);
        $notice = 'Password updated.';
    }
}

$pageTitle = 'Change Password';
$backUrl = $user['role'] === 'client' ? APP_URL . '/portal/dashboard.php' : APP_URL . '/index.php';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><h1>Change Password</h1><p>Update the password for your own account.</p></div>
</div>

<?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card" style="max-width:420px">
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="field"><label>Current password</label><input type="password" name="current_password" required></div>
    <div class="field"><label>New password</label><input type="password" name="new_password" required minlength="8"></div>
    <div class="field"><label>Confirm new password</label><input type="password" name="new_password_confirm" required minlength="8"></div>
    <button class="btn" type="submit">Update password</button>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
