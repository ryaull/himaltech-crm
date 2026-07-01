<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_role('admin', 'staff');

$openLeads = $pdo->query("SELECT COUNT(*) c FROM leads WHERE stage NOT IN ('won','lost')")->fetch()['c'];
$activeClients = $pdo->query("SELECT COUNT(*) c FROM clients WHERE status = 'active'")->fetch()['c'];
$pendingQuotes = $pdo->query("SELECT COUNT(*) c FROM quotes WHERE status IN ('draft','sent')")->fetch()['c'];
$pipelineValue = $pdo->query("SELECT COALESCE(SUM(value_estimate),0) v FROM leads WHERE stage NOT IN ('won','lost')")->fetch()['v'];
$pendingQuoteValue = $pdo->query("SELECT COALESCE(SUM(total),0) v FROM quotes WHERE status IN ('draft','sent')")->fetch()['v'];
$acceptedThisMonth = $pdo->query(
    "SELECT COUNT(*) c FROM quotes WHERE status = 'accepted' AND MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE())"
)->fetch()['c'];
$openTicketsCount = $pdo->query("SELECT COUNT(*) c FROM tickets WHERE status IN ('open','in_progress')")->fetch()['c'];

$recentLeads = $pdo->query(
    "SELECT l.*, c.company_name FROM leads l
     JOIN clients c ON c.id = l.client_id
     ORDER BY l.updated_at DESC LIMIT 8"
)->fetchAll();

$recentTickets = $pdo->query(
    "SELECT t.*, c.company_name FROM tickets t
     JOIN clients c ON c.id = t.client_id
     WHERE t.status IN ('open','in_progress')
     ORDER BY FIELD(t.status,'open','in_progress'), t.updated_at DESC LIMIT 6"
)->fetchAll();

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Dashboard</h1>
    <p>Overview of the pipeline right now.</p>
  </div>
</div>

<div class="stat-grid">
  <div class="stat-card"><div class="stat-value"><?= $openLeads ?></div><div class="stat-label">Open leads</div></div>
  <div class="stat-card"><div class="stat-value"><?= $activeClients ?></div><div class="stat-label">Active clients</div></div>
  <div class="stat-card"><div class="stat-value"><?= $pendingQuotes ?></div><div class="stat-label">Quotes awaiting response</div></div>
  <div class="stat-card"><div class="stat-value"><?= money($pipelineValue) ?></div><div class="stat-label">Open pipeline value</div></div>
  <div class="stat-card"><div class="stat-value"><?= money($pendingQuoteValue) ?></div><div class="stat-label">£ in pending quotes</div></div>
  <div class="stat-card"><div class="stat-value"><?= $acceptedThisMonth ?></div><div class="stat-label">Quotes accepted this month</div></div>
  <div class="stat-card"><div class="stat-value"><?= $openTicketsCount ?></div><div class="stat-label">Open tickets</div></div>
</div>

<div class="card">
  <h3 style="margin-top:0">Recently updated leads</h3>
  <?php if (!$recentLeads): ?>
    <div class="empty-state"><h3>No leads yet</h3><p>Add your first lead to get started.</p>
      <a class="btn" href="<?= APP_URL ?>/leads/add.php" style="margin-top:12px">Add a lead</a>
    </div>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Lead</th><th>Client</th><th>Stage</th><th>Est. value</th><th>Updated</th></tr></thead>
    <tbody>
    <?php foreach ($recentLeads as $l): ?>
      <tr onclick="location.href='<?= APP_URL ?>/leads/view.php?id=<?= $l['id'] ?>'" style="cursor:pointer">
        <td><?= e($l['title']) ?></td>
        <td><?= e($l['company_name']) ?></td>
        <td><span class="badge badge-<?= $l['stage'] ?>"><?= stage_label($l['stage']) ?></span></td>
        <td><?= $l['value_estimate'] ? money($l['value_estimate']) : '—' ?></td>
        <td class="text-muted"><?= date('d M', strtotime($l['updated_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0">Open tickets</h3>
  <?php if (!$recentTickets): ?>
    <p class="text-muted">No open tickets right now.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Ticket</th><th>Client</th><th>Status</th><th>Updated</th></tr></thead>
    <tbody>
    <?php foreach ($recentTickets as $t): ?>
      <tr onclick="location.href='<?= APP_URL ?>/tickets/view.php?id=<?= $t['id'] ?>'" style="cursor:pointer">
        <td><?= e($t['subject']) ?></td>
        <td><?= e($t['company_name']) ?></td>
        <td><span class="badge badge-<?= ticket_status_badge($t['status']) ?>"><?= ticket_status_label($t['status']) ?></span></td>
        <td class="text-muted"><?= date('d M', strtotime($t['updated_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
