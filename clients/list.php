<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin', 'staff');

$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT c.*, u.name AS owner_name FROM clients c
        LEFT JOIN users u ON u.id = c.assigned_to WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (c.company_name LIKE ? OR c.industry LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like;
}
if ($statusFilter !== '') {
    $sql .= " AND c.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY c.company_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$pageTitle = 'Clients';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><h1>Clients</h1><p><?= count($clients) ?> companies<?= $search || $statusFilter ? ' matching your filters' : ' on file' ?>.</p></div>
  <a class="btn" href="add.php">+ Add client</a>
</div>

<div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
  <a class="btn btn-sm <?= $statusFilter === '' ? '' : 'btn-secondary' ?>" href="list.php<?= $search ? '?q=' . urlencode($search) : '' ?>">All</a>
  <?php foreach (['lead','active','inactive'] as $s): ?>
    <a class="btn btn-sm <?= $statusFilter === $s ? '' : 'btn-secondary' ?>" href="list.php?status=<?= $s ?><?= $search ? '&q=' . urlencode($search) : '' ?>"><?= ucfirst($s) ?></a>
  <?php endforeach; ?>
  <form method="get" style="margin-left:auto;display:flex;gap:6px">
    <?php if ($statusFilter): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
    <input type="text" name="q" placeholder="Search company or industry..." value="<?= e($search) ?>" style="min-width:220px">
    <button class="btn btn-sm btn-secondary" type="submit">Search</button>
  </form>
</div>

<div class="card">
  <?php if (!$clients): ?>
    <div class="empty-state"><h3>No clients yet</h3><p>Add your first company to start building the CRM.</p></div>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Company</th><th>Status</th><th>Industry</th><th>Owner</th><th>Phone</th></tr></thead>
    <tbody>
    <?php foreach ($clients as $c): ?>
      <tr onclick="location.href='view.php?id=<?= $c['id'] ?>'" style="cursor:pointer">
        <td><strong><?= e($c['company_name']) ?></strong></td>
        <td><span class="badge badge-<?= $c['status'] === 'active' ? 'won' : ($c['status'] === 'lead' ? 'new' : 'lost') ?>"><?= e(ucfirst($c['status'])) ?></span></td>
        <td><?= e($c['industry'] ?? '—') ?></td>
        <td><?= e($c['owner_name'] ?? '—') ?></td>
        <td><?= e($c['phone'] ?? '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
