<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin', 'staff');

$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$mineOnly = !empty($_GET['mine']);
$me = current_user()['id'];

$sql = "SELECT t.*, c.company_name,
        (SELECT GROUP_CONCAT(u.name SEPARATOR ', ') FROM ticket_assignees ta JOIN users u ON u.id = ta.user_id WHERE ta.ticket_id = t.id) AS assignee_names
        FROM tickets t
        JOIN clients c ON c.id = t.client_id
        WHERE 1=1";
$params = [];

if ($statusFilter !== '') {
    $sql .= " AND t.status = ?";
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= " AND (t.subject LIKE ? OR c.company_name LIKE ? OR t.ticket_number LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($mineOnly) {
    $sql .= " AND EXISTS (SELECT 1 FROM ticket_assignees ta WHERE ta.ticket_id = t.id AND ta.user_id = ?)";
    $params[] = $me;
}
$sql .= " ORDER BY FIELD(t.status,'open','in_progress','closed'), t.updated_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

function ticket_list_qs(array $overrides, string $statusFilter, string $search, bool $mineOnly): string {
    $q = array_merge(['status' => $statusFilter, 'q' => $search, 'mine' => $mineOnly ? '1' : ''], $overrides);
    $q = array_filter($q, fn($v) => $v !== '');
    return $q ? '?' . http_build_query($q) : '';
}

$statuses = ['open','in_progress','closed'];
$pageTitle = 'Tickets';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><h1>Tickets</h1><p><?= count($tickets) ?> tickets<?= $statusFilter ? ' · ' . ticket_status_label($statusFilter) : '' ?><?= $mineOnly ? ' assigned to you' : '' ?>.</p></div>
</div>

<div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
  <a class="btn btn-sm <?= $statusFilter === '' ? '' : 'btn-secondary' ?>" href="list.php<?= ticket_list_qs(['status' => ''], '', $search, $mineOnly) ?>">All</a>
  <?php foreach ($statuses as $s): ?>
    <a class="btn btn-sm <?= $statusFilter === $s ? '' : 'btn-secondary' ?>" href="list.php<?= ticket_list_qs(['status' => $s], $statusFilter, $search, $mineOnly) ?>"><?= ticket_status_label($s) ?></a>
  <?php endforeach; ?>
  <a class="btn btn-sm <?= $mineOnly ? '' : 'btn-secondary' ?>" href="list.php<?= ticket_list_qs(['mine' => $mineOnly ? '' : '1'], $statusFilter, $search, false) ?>">My tasks</a>
  <form method="get" style="margin-left:auto;display:flex;gap:6px">
    <?php if ($statusFilter): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
    <?php if ($mineOnly): ?><input type="hidden" name="mine" value="1"><?php endif; ?>
    <input type="text" name="q" placeholder="Search subject or client..." value="<?= e($search) ?>" style="min-width:220px">
    <button class="btn btn-sm btn-secondary" type="submit">Search</button>
  </form>
</div>

<div class="card">
  <?php if (!$tickets): ?>
    <div class="empty-state"><h3>No tickets found</h3><p><?= $mineOnly ? "Nothing's assigned to you right now." : 'Client-raised support requests will appear here.' ?></p></div>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Ticket</th><th>Subject</th><th>Client</th><th>Priority</th><th>Status</th><th>Assigned</th><th>Updated</th></tr></thead>
    <tbody>
    <?php foreach ($tickets as $t): ?>
      <tr onclick="location.href='view.php?id=<?= $t['id'] ?>'" style="cursor:pointer">
        <td><span class="ref-code"><?= e($t['ticket_number']) ?></span></td>
        <td><?= e($t['subject']) ?></td>
        <td><?= e($t['company_name']) ?></td>
        <td><span class="badge badge-priority-<?= $t['priority'] ?>"><?= ticket_priority_label($t['priority']) ?></span></td>
        <td><span class="badge badge-<?= ticket_status_badge($t['status']) ?>"><?= ticket_status_label($t['status']) ?></span></td>
        <td class="text-muted"><?= $t['assignee_names'] ? e($t['assignee_names']) : '—' ?></td>
        <td class="text-muted"><?= date('d M Y', strtotime($t['updated_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
