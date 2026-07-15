<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('client');

$user = current_user();
$stmt = $pdo->prepare('SELECT * FROM tickets WHERE client_id = ? ORDER BY FIELD(status,\'open\',\'in_progress\',\'closed\'), updated_at DESC');
$stmt->execute([$user['client_id']]);
$tickets = $stmt->fetchAll();

$pageTitle = 'My Requests';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>My Requests</h1>
    <p>Support requests you've raised with Himal Tech.</p>
  </div>
  <a class="btn" href="ticket_add.php">+ New request</a>
</div>

<div class="card">
  <?php if (!$tickets): ?>
    <div class="empty-state"><h3>No requests yet</h3><p>Raise a request if you need anything from Himal Tech.</p></div>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Number</th><th>Subject</th><th>Status</th><th>Updated</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($tickets as $t): ?>
      <tr onclick="location.href='ticket_view.php?id=<?= $t['id'] ?>'" style="cursor:pointer">
        <td><span class="ref-code"><?= e($t['ticket_number']) ?></span></td>
        <td><?= e($t['subject']) ?></td>
        <td><span class="badge badge-<?= ticket_status_badge($t['status']) ?>"><?= ticket_status_label($t['status']) ?></span></td>
        <td class="text-muted"><?= date('d M Y', strtotime($t['updated_at'])) ?></td>
        <td><a href="ticket_view.php?id=<?= $t['id'] ?>">View</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
