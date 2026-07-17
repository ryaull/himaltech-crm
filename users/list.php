<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $targetId = (int)($_POST['user_id'] ?? 0);

    if ($action === 'toggle_status' && $targetId !== current_user()['id']) {
        $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ? AND role IN ('admin','staff')");
        $stmt->execute([$targetId]);
        $row = $stmt->fetch();
        if ($row) {
            $newStatus = $row['status'] === 'active' ? 'disabled' : 'active';
            $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $targetId]);
            $notice = 'Account ' . ($newStatus === 'active' ? 'enabled' : 'disabled') . '.';
        }
    }
}

$staff = $pdo->query("SELECT * FROM users WHERE role IN ('admin','staff') ORDER BY name")->fetchAll();

$pageTitle = 'Staff';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><h1>Staff</h1><p><?= count($staff) ?> internal accounts.</p></div>
  <a class="btn" href="add.php">+ Add staff member</a>
</div>

<?php if (!empty($_GET['invited'])): ?><div class="alert alert-success"><?= e($_GET['invited']) ?> has been invited — they'll get an email to set their password.</div><?php endif; ?>
<?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>

<div class="card">
  <table class="table">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($staff as $s): ?>
      <tr>
        <td><?= e($s['name']) ?></td>
        <td><?= e($s['email']) ?></td>
        <td><?= e(ucfirst($s['role'])) ?></td>
        <td><span class="badge badge-<?= $s['status'] === 'active' ? 'won' : 'lost' ?>"><?= ucfirst($s['status']) ?></span></td>
        <td class="text-muted"><?= date('d M Y', strtotime($s['created_at'])) ?></td>
        <td>
          <?php if ($s['id'] !== current_user()['id']): ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="user_id" value="<?= $s['id'] ?>">
            <button class="btn btn-sm btn-secondary" type="submit"><?= $s['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
          </form>
          <?php else: ?>
            <span class="text-muted" style="font-size:12px">You</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
