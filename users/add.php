<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_role('admin');

$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'staff';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a name and a valid email address.';
    } elseif (!in_array($role, ['admin', 'staff'], true)) {
        $error = 'Invalid role.';
    } else {
        try {
            // No one ever needs to know this password — it's immediately
            // replaced the moment the invite link is used. Generating it
            // randomly (rather than asking the admin to type one) means no
            // plain-text temporary password is ever typed, stored in this
            // form, or shared over chat.
            $randomPassword = bin2hex(random_bytes(16));
            $hash = password_hash($randomPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)');
            $stmt->execute([$name, $email, $hash, $role]);
            $newUserId = (int)$pdo->lastInsertId();

            $token = create_reset_token($pdo, $newUserId, 'invite', 48);
            notify_staff_invite($email, $name, $token);

            header('Location: list.php?invited=' . urlencode($name));
            exit;
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $error = 'That email is already registered.';
            } else {
                error_log('Staff creation failed: ' . $ex->getMessage());
                $error = 'Sorry, the account could not be created. Please try again.';
            }
        }
    }
}

$pageTitle = 'Add Staff Member';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><h1>Add Staff Member</h1><p>They'll get an email with a link to set their own password.</p></div>
</div>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card" style="max-width:480px">
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="field"><label>Name</label><input name="name" required></div>
    <div class="field"><label>Email</label><input type="email" name="email" required></div>
    <div class="field">
      <label>Role</label>
      <select name="role">
        <option value="staff">Staff</option>
        <option value="admin">Admin</option>
      </select>
    </div>
    <button class="btn" type="submit">Create account &amp; send invite</button>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
