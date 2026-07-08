<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_role('admin', 'staff');

$presetClientId = (int)($_GET['client_id'] ?? 0);
$clients = $pdo->query("SELECT id, company_name FROM clients ORDER BY company_name")->fetchAll();
$staff = $pdo->query("SELECT id, name FROM users WHERE role IN ('admin','staff') ORDER BY name")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $title = trim($_POST['title'] ?? '');
    $clientId = (int)($_POST['client_id'] ?? 0);
    if ($title === '' || !$clientId) {
        $error = 'Title and client are required.';
    } else {
        $assigneeIds = array_filter(array_map('intval', $_POST['assigned_to'] ?? []));
        $primaryAssignee = $assigneeIds ? $assigneeIds[0] : null;

        $stmt = $pdo->prepare(
            'INSERT INTO leads (client_id, title, description, stage, value_estimate, assigned_to) VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([
            $clientId, $title,
            trim($_POST['description'] ?? '') ?: null,
            $_POST['stage'] ?? 'new',
            ($_POST['value_estimate'] ?? '') !== '' ? (float)$_POST['value_estimate'] : null,
            $primaryAssignee,
        ]);
        $newId = $pdo->lastInsertId();

        if ($assigneeIds) {
            $stmt = $pdo->prepare('SELECT company_name FROM clients WHERE id = ?');
            $stmt->execute([$clientId]);
            $companyName = $stmt->fetch()['company_name'] ?? '';
            foreach ($assigneeIds as $uid) {
                $pdo->prepare('INSERT IGNORE INTO lead_assignees (lead_id, user_id) VALUES (?,?)')->execute([$newId, $uid]);
                if ($uid !== current_user()['id']) {
                    notify_lead_assigned($pdo, $uid, ['id' => $newId, 'title' => $title], $companyName);
                }
            }
        }

        log_activity($pdo, current_user()['id'], $clientId, $newId, 'Lead created: ' . $title);
        header('Location: view.php?id=' . $newId);
        exit;
    }
}

$pageTitle = 'Add lead';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div><h1>Add lead</h1></div></div>

<div class="card" style="max-width:640px">
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="field"><label>Title *</label><input name="title" required autofocus placeholder="e.g. Website rebuild + SEO package"></div>
    <div class="field"><label>Client *</label>
      <select name="client_id" required>
        <option value="">Select a client…</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $presetClientId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['company_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (!$clients): ?><p class="text-muted">No clients yet — <a href="<?= APP_URL ?>/clients/add.php">add one first</a>.</p><?php endif; ?>
    </div>
    <div class="field-row">
      <div class="field"><label>Stage</label>
        <select name="stage">
          <?php foreach (['new','contacted','qualified','quoted','won','lost'] as $s): ?>
            <option value="<?= $s ?>"><?= stage_label($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Estimated value (£)</label><input type="number" step="0.01" min="0" name="value_estimate"></div>
    </div>
    <div class="field"><label>Assign to</label>
      <?php if (!$staff): ?>
        <p class="text-muted" style="margin:0">No staff accounts yet.</p>
      <?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:12px 20px;padding:8px 0">
        <?php foreach ($staff as $s): ?>
          <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:13.5px">
            <input type="checkbox" name="assigned_to[]" value="<?= $s['id'] ?>"> <?= e($s['name']) ?>
          </label>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="field"><label>Description</label><textarea name="description"></textarea></div>
    <button class="btn" type="submit">Save lead</button>
    <a class="btn btn-secondary" href="list.php">Cancel</a>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
