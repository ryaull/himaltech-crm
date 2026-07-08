<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin', 'staff');

$stageFilter = $_GET['stage'] ?? '';
$search = trim($_GET['q'] ?? '');
$mineOnly = !empty($_GET['mine']);
$me = current_user()['id'];

$sql = "SELECT l.*, c.company_name,
        qr.budget_range, qr.timeline_flexibility, qr.website_url AS qr_website_url, qr.page_count_bracket, qr.has_logo, qr.project_type AS qr_project_type,
        (SELECT GROUP_CONCAT(u.name SEPARATOR ', ') FROM lead_assignees la JOIN users u ON u.id = la.user_id WHERE la.lead_id = l.id) AS assignee_names
        FROM leads l JOIN clients c ON c.id = l.client_id
        LEFT JOIN quote_requests qr ON qr.lead_id = l.id
        WHERE 1=1";
$params = [];
if ($stageFilter !== '') {
    $sql .= " AND l.stage = ?";
    $params[] = $stageFilter;
}
if ($search !== '') {
    $sql .= " AND (l.title LIKE ? OR c.company_name LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like;
}
if ($mineOnly) {
    $sql .= " AND EXISTS (SELECT 1 FROM lead_assignees la WHERE la.lead_id = l.id AND la.user_id = ?)";
    $params[] = $me;
}
$sql .= " ORDER BY l.updated_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll();

function lead_list_qs(array $overrides, string $stageFilter, string $search, bool $mineOnly): string {
    $q = array_merge(['stage' => $stageFilter, 'q' => $search, 'mine' => $mineOnly ? '1' : ''], $overrides);
    $q = array_filter($q, fn($v) => $v !== '');
    return $q ? '?' . http_build_query($q) : '';
}

$stages = ['new','contacted','qualified','quoted','won','lost'];
$pageTitle = 'Leads';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><h1>Leads</h1><p><?= count($leads) ?> leads<?= $stageFilter ? ' in ' . stage_label($stageFilter) : '' ?><?= $mineOnly ? ' assigned to you' : '' ?>.</p></div>
  <a class="btn" href="add.php">+ Add lead</a>
</div>

<div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
  <a class="btn btn-sm <?= $stageFilter === '' ? '' : 'btn-secondary' ?>" href="list.php<?= lead_list_qs(['stage' => ''], '', $search, $mineOnly) ?>">All</a>
  <?php foreach ($stages as $s): ?>
    <a class="btn btn-sm <?= $stageFilter === $s ? '' : 'btn-secondary' ?>" href="list.php<?= lead_list_qs(['stage' => $s], $stageFilter, $search, $mineOnly) ?>"><?= stage_label($s) ?></a>
  <?php endforeach; ?>
  <a class="btn btn-sm <?= $mineOnly ? '' : 'btn-secondary' ?>" href="list.php<?= lead_list_qs(['mine' => $mineOnly ? '' : '1'], $stageFilter, $search, false) ?>">My tasks</a>
  <form method="get" style="margin-left:auto;display:flex;gap:6px">
    <?php if ($stageFilter): ?><input type="hidden" name="stage" value="<?= e($stageFilter) ?>"><?php endif; ?>
    <?php if ($mineOnly): ?><input type="hidden" name="mine" value="1"><?php endif; ?>
    <input type="text" name="q" placeholder="Search lead or client..." value="<?= e($search) ?>" style="min-width:220px">
    <button class="btn btn-sm btn-secondary" type="submit">Search</button>
  </form>
</div>

<div class="card">
  <?php if (!$leads): ?>
    <div class="empty-state"><h3>No leads here</h3><p><?= $mineOnly ? "Nothing's assigned to you right now." : 'Add a lead to start tracking it.' ?></p></div>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Title</th><th>Client</th><th>Stage</th><th>Score</th><th>Assigned</th><th>Est. value</th><th>Updated</th></tr></thead>
    <tbody>
    <?php foreach ($leads as $l): ?>
      <tr onclick="location.href='view.php?id=<?= $l['id'] ?>'" style="cursor:pointer">
        <td><?= e($l['title']) ?></td>
        <td><?= e($l['company_name']) ?></td>
        <td><span class="badge badge-<?= $l['stage'] ?>"><?= stage_label($l['stage']) ?></span></td>
        <td>
          <?php if ($l['budget_range'] !== null): ?>
            <?php $score = discovery_lead_score(['budget_range' => $l['budget_range'], 'timeline_flexibility' => $l['timeline_flexibility'], 'website_url' => $l['qr_website_url'], 'page_count_bracket' => $l['page_count_bracket'], 'has_logo' => $l['has_logo'], 'project_type' => $l['qr_project_type']]); ?>
            <span class="badge <?= $score['class'] ?>" style="font-size:10.5px"><?= e($score['label']) ?></span>
          <?php else: ?>
            <span class="text-muted">—</span>
          <?php endif; ?>
        </td>
        <td class="text-muted"><?= $l['assignee_names'] ? e($l['assignee_names']) : '—' ?></td>
        <td><?= $l['value_estimate'] ? money($l['value_estimate']) : '—' ?></td>
        <td class="text-muted"><?= date('d M Y', strtotime($l['updated_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>