<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin', 'staff');

$quotes = $pdo->query(
    "SELECT q.*, c.company_name FROM quotes q JOIN clients c ON c.id = q.client_id ORDER BY q.created_at DESC"
)->fetchAll();

$pageTitle = 'Quotes';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><h1>Quotes</h1><p><?= count($quotes) ?> quotes issued.</p></div>
  <a class="btn" href="add.php">+ Create quote</a>
</div>

<div class="card">
  <?php if (!$quotes): ?>
    <div class="empty-state"><h3>No quotes yet</h3><p>Create your first quote from a client or lead.</p></div>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Number</th><th>Client</th><th>Status</th><th>Total</th><th>Issued</th></tr></thead>
    <tbody>
    <?php foreach ($quotes as $q): ?>
      <tr onclick="location.href='view.php?id=<?= $q['id'] ?>'" style="cursor:pointer">
        <td><span class="ref-code"><?= e($q['quote_number']) ?></span></td>
        <td><?= e($q['company_name']) ?></td>
        <td><span class="badge badge-<?= $q['status'] ?>"><?= quote_status_label($q['status']) ?></span></td>
        <td><?= money($q['total']) ?></td>
        <td class="text-muted"><?= date('d M Y', strtotime($q['issue_date'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
