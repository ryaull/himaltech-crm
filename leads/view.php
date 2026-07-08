<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/uploads.php';
require_role('admin', 'staff');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT l.*, c.company_name FROM leads l JOIN clients c ON c.id = l.client_id WHERE l.id = ?');
$stmt->execute([$id]);
$lead = $stmt->fetch();
if (!$lead) { http_response_code(404); die('Lead not found.'); }

$staff = $pdo->query("SELECT id, name FROM users WHERE role IN ('admin','staff') ORDER BY name")->fetchAll();
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'update') {
        $title = trim($_POST['title'] ?? '');
        $newStage = $_POST['stage'] ?? 'new';
        $newAssigneeIds = array_filter(array_map('intval', $_POST['assigned_to'] ?? []));
        $primaryAssignee = $newAssigneeIds ? $newAssigneeIds[0] : null;

        $stmt = $pdo->prepare('SELECT user_id FROM lead_assignees WHERE lead_id = ?');
        $stmt->execute([$id]);
        $previousAssigneeIds = array_map('intval', array_column($stmt->fetchAll(), 'user_id'));

        $stmt = $pdo->prepare(
            'UPDATE leads SET title=?, description=?, stage=?, value_estimate=?, assigned_to=? WHERE id=?'
        );
        $stmt->execute([
            $title,
            trim($_POST['description'] ?? '') ?: null,
            $newStage,
            ($_POST['value_estimate'] ?? '') !== '' ? (float)$_POST['value_estimate'] : null,
            $primaryAssignee,
            $id,
        ]);
        if ($newStage !== $lead['stage']) {
            log_activity($pdo, current_user()['id'], $lead['client_id'], $id, 'Stage changed to ' . stage_label($newStage));
        }

        // Full sync: remove anyone unchecked, add anyone newly checked, only
        // email people who are newly added (not everyone re-saved each time).
        $pdo->prepare('DELETE FROM lead_assignees WHERE lead_id = ?')->execute([$id]);
        foreach ($newAssigneeIds as $uid) {
            $pdo->prepare('INSERT IGNORE INTO lead_assignees (lead_id, user_id) VALUES (?,?)')->execute([$id, $uid]);
            if (!in_array($uid, $previousAssigneeIds, true) && $uid !== current_user()['id']) {
                notify_lead_assigned($pdo, $uid, ['id' => $id, 'title' => $title], $lead['company_name']);
            }
        }

        $notice = 'Lead updated.';
        $stmt = $pdo->prepare('SELECT l.*, c.company_name FROM leads l JOIN clients c ON c.id = l.client_id WHERE l.id = ?');
        $stmt->execute([$id]);
        $lead = $stmt->fetch();
    }

    if ($action === 'save_internal') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $priority = $_POST['internal_priority'] ?? 'normal';
        if (!in_array($priority, ['low', 'normal', 'high'], true)) { $priority = 'normal'; }

        $stmt = $pdo->prepare(
            'UPDATE quote_requests SET
                internal_estimated_hours = ?, internal_estimated_cost = ?, internal_hosting_cost = ?,
                internal_seo_cost = ?, internal_maintenance_cost = ?, internal_priority = ?, internal_notes = ?
             WHERE id = ? AND lead_id = ?'
        );
        $stmt->execute([
            ($_POST['internal_estimated_hours'] ?? '') !== '' ? (float)$_POST['internal_estimated_hours'] : null,
            ($_POST['internal_estimated_cost'] ?? '') !== '' ? (float)$_POST['internal_estimated_cost'] : null,
            ($_POST['internal_hosting_cost'] ?? '') !== '' ? (float)$_POST['internal_hosting_cost'] : null,
            ($_POST['internal_seo_cost'] ?? '') !== '' ? (float)$_POST['internal_seo_cost'] : null,
            ($_POST['internal_maintenance_cost'] ?? '') !== '' ? (float)$_POST['internal_maintenance_cost'] : null,
            $priority,
            trim($_POST['internal_notes'] ?? '') ?: null,
            $requestId, $id,
        ]);
        $notice = 'Internal notes saved.';
    }

    if ($action === 'add_note') {
        $note = trim($_POST['note'] ?? '');
        if ($note !== '') {
            log_activity($pdo, current_user()['id'], $lead['client_id'], $id, $note);
            $notice = 'Note added.';
        }
    }
}

$activity = $pdo->prepare(
    'SELECT a.*, u.name AS user_name FROM activity_log a LEFT JOIN users u ON u.id = a.user_id
     WHERE a.lead_id = ? ORDER BY a.created_at DESC'
);
$activity->execute([$id]);
$activity = $activity->fetchAll();

$currentAssigneeIds = $pdo->prepare('SELECT user_id FROM lead_assignees WHERE lead_id = ?');
$currentAssigneeIds->execute([$id]);
$currentAssigneeIds = array_map('intval', array_column($currentAssigneeIds->fetchAll(), 'user_id'));

$quoteRequest = $pdo->prepare('SELECT * FROM quote_requests WHERE lead_id = ?');
$quoteRequest->execute([$id]);
$quoteRequest = $quoteRequest->fetch();

$quoteRequestAttachments = $pdo->prepare('SELECT * FROM quote_request_attachments WHERE lead_id = ? ORDER BY created_at DESC');
$quoteRequestAttachments->execute([$id]);
$quoteRequestAttachments = $quoteRequestAttachments->fetchAll();

$pageTitle = $lead['title'];
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><?= e($lead['title']) ?></h1>
    <p><a href="<?= APP_URL ?>/clients/view.php?id=<?= $lead['client_id'] ?>"><?= e($lead['company_name']) ?></a></p>
  </div>
  <a class="btn" href="<?= APP_URL ?>/quotes/add.php?lead_id=<?= $id ?>&client_id=<?= $lead['client_id'] ?>">+ Create quote</a>
</div>

<?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>

<?php if ($quoteRequest): $qr = $quoteRequest; $score = discovery_lead_score($qr); ?>
<div class="card" style="border-left:3px solid var(--purple)">
  <h3 style="margin-top:0">Quote request (submitted via public form) <span class="badge <?= $score['class'] ?>"><?= e($score['label']) ?></span></h3>
  <div style="background:var(--purple-tint);border-left:3px solid var(--purple);border-radius:8px;padding:12px 14px;margin-bottom:14px;font-size:13px;color:var(--ink)">
    <strong style="display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--purple-dark);margin-bottom:4px">30-second summary</strong>
    <?= e(discovery_staff_summary($qr)) ?>
  </div>
  <p style="margin:0 0 10px">
    <?= e($qr['contact_name']) ?> · <?= e($qr['contact_email']) ?><?= $qr['contact_phone'] ? ' · ' . e($qr['contact_phone']) : '' ?>
    <?php if ($qr['website_url']): ?><br><?= e($qr['website_url']) ?><?php endif; ?>
    <?php if ($qr['business_address']): ?><br><?= e($qr['business_address']) ?><?php endif; ?>
    <br><span class="text-muted" style="font-size:12px">Submitted <?= date('d M Y, H:i', strtotime($qr['created_at'])) ?></span>
  </p>

  <?php if ($quoteRequestAttachments): ?>
  <div style="margin-bottom:14px">
    <strong style="font-size:12px">Attached by client:</strong>
    <?php foreach ($quoteRequestAttachments as $att): ?>
      <div style="margin-top:4px">
        <a href="<?= APP_URL ?>/attachments/download.php?type=quote_request&id=<?= $att['id'] ?>" style="font-size:13px">
          &#128206; <?= e($att['original_filename']) ?>
        </a>
        <span class="text-muted" style="font-size:11.5px"> (<?= human_filesize((int)$att['filesize']) ?>)</span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php
  // Small helper to render a "Label: value" line only when the field was
  // actually answered — keeps the card readable instead of a wall of blanks.
  $line = function(string $label, ?string $value) {
      if ($value === null || $value === '') return;
      echo '<p style="margin:0 0 6px"><strong>' . e($label) . ':</strong> ' . nl2br(e($value)) . '</p>';
  };
  ?>

  <div class="section-title">Project</div>
  <?php $line('Project type', $qr['project_type']); ?>
  <?php $line('Decision maker?', $qr['is_decision_maker']); ?>
  <?php $line('Current website platform', $qr['current_host']); ?>
  <?php $line('What their business does', $qr['business_description']); ?>
  <?php $line('Target customers', $qr['target_customers']); ?>
  <?php $line('Competitors', $qr['competitors']); ?>

  <div class="section-title">Website requirements</div>
  <?php $line('Solution type', $qr['solution_type']); ?>
  <?php $line('Page count', $qr['page_count_bracket']); ?>
  <?php $line('Specific pages needed', $qr['required_pages_other']); ?>
  <?php $line('Features', $qr['website_features']); ?>

  <div class="section-title">Branding &amp; content</div>
  <?php $line('Has a logo', $qr['has_logo']); ?>
  <?php $line('Wants a logo designed', $qr['wants_logo_design']); ?>
  <?php $line('Brand colours', $qr['brand_colours']); ?>
  <?php $line('Brand guidelines', $qr['brand_guidelines']); ?>
  <?php $line('Marketing materials', $qr['marketing_materials']); ?>
  <?php $line('Has content already', $qr['has_content']); ?>
  <?php $line('Needs photography', $qr['needs_photography']); ?>
  <?php $line('Needs stock images', $qr['needs_stock_images']); ?>
  <?php $line('Needs video', $qr['needs_video']); ?>

  <div class="section-title">SEO</div>
  <?php $line('Requirements', $qr['seo_requirements']); ?>
  <?php $line('Primary goal', $qr['seo_primary_goal']); ?>
  <?php $line('Target locations', $qr['seo_target_locations']); ?>
  <?php $line('Has Analytics/Search Console', $qr['seo_current_analytics']); ?>

  <div class="section-title">Domain &amp; hosting</div>
  <?php $line('Already owns a domain', $qr['has_domain']); ?>
  <?php $line('Current hosting provider', $qr['hosting_provider']); ?>
  <?php $line('Needs hosting', $qr['needs_hosting']); ?>
  <?php $line('Needs business email', $qr['needs_business_email']); ?>

  <div class="section-title">Design &amp; budget</div>
  <?php $line('Reference sites', $qr['design_references']); ?>
  <?php $line('Preferred style', $qr['preferred_style']); ?>
  <?php $line('Budget range', $qr['budget_range']); ?>
  <?php $line('Preferred launch date', $qr['preferred_launch_date']); ?>
  <?php $line('Urgent', $qr['is_urgent']); ?>
  <?php $line('Add-ons of interest', $qr['addon_services']); ?>
  <?php $line('How they heard about us', $qr['referral_source']); ?>

  <?php if ($qr['additional_notes']): ?>
    <div class="section-title">Notes from client</div>
    <?php $line('Anything else', $qr['additional_notes']); ?>
  <?php endif; ?>

  <?php if ($qr['quote_id']): ?>
    <p style="margin:18px 0 0"><a class="btn btn-sm" href="<?= APP_URL ?>/quotes/view.php?id=<?= $qr['quote_id'] ?>">View linked quote →</a></p>
  <?php else: ?>
    <form method="post" action="<?= APP_URL ?>/quotes/convert_request.php" style="margin-top:18px">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="request_id" value="<?= $qr['id'] ?>">
      <button class="btn btn-sm" type="submit">Create a linked quote</button>
    </form>
  <?php endif; ?>
</div>

<div class="card" style="border-left:3px solid var(--warn)">
  <h3 style="margin-top:0">Internal use only <span class="text-muted" style="font-weight:400;font-size:12px">— never visible to the client</span></h3>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="save_internal">
    <input type="hidden" name="request_id" value="<?= $qr['id'] ?>">
    <div class="field-row">
      <div class="field"><label>Estimated hours</label><input type="number" step="0.5" min="0" name="internal_estimated_hours" value="<?= e((string)($qr['internal_estimated_hours'] ?? '')) ?>"></div>
      <div class="field"><label>Estimated cost (£)</label><input type="number" step="0.01" min="0" name="internal_estimated_cost" value="<?= e((string)($qr['internal_estimated_cost'] ?? '')) ?>"></div>
    </div>
    <div class="field-row">
      <div class="field"><label>Hosting cost (£)</label><input type="number" step="0.01" min="0" name="internal_hosting_cost" value="<?= e((string)($qr['internal_hosting_cost'] ?? '')) ?>"></div>
      <div class="field"><label>SEO cost (£)</label><input type="number" step="0.01" min="0" name="internal_seo_cost" value="<?= e((string)($qr['internal_seo_cost'] ?? '')) ?>"></div>
    </div>
    <div class="field-row">
      <div class="field"><label>Maintenance cost (£)</label><input type="number" step="0.01" min="0" name="internal_maintenance_cost" value="<?= e((string)($qr['internal_maintenance_cost'] ?? '')) ?>"></div>
      <div class="field">
        <label>Priority</label>
        <select name="internal_priority">
          <?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'] as $val => $lbl): ?>
            <option value="<?= $val ?>" <?= $qr['internal_priority'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="field"><label>Internal notes</label><textarea name="internal_notes"><?= e($qr['internal_notes'] ?? '') ?></textarea></div>
    <button class="btn btn-sm" type="submit">Save internal notes</button>
  </form>
</div>
<?php endif; ?>

<div class="card" style="max-width:640px">
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="update">
    <div class="field"><label>Title</label><input name="title" value="<?= e($lead['title']) ?>" required></div>
    <div class="field-row">
      <div class="field"><label>Stage</label>
        <select name="stage">
          <?php foreach (['new','contacted','qualified','quoted','won','lost'] as $s): ?>
            <option value="<?= $s ?>" <?= $lead['stage'] === $s ? 'selected' : '' ?>><?= stage_label($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Estimated value (£)</label><input type="number" step="0.01" min="0" name="value_estimate" value="<?= e((string)($lead['value_estimate'] ?? '')) ?>"></div>
    </div>
    <div class="field"><label>Assigned to</label>
      <?php if (!$staff): ?>
        <p class="text-muted" style="margin:0">No staff accounts yet.</p>
      <?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:12px 20px;padding:8px 0">
        <?php foreach ($staff as $s): ?>
          <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:13.5px">
            <input type="checkbox" name="assigned_to[]" value="<?= $s['id'] ?>" <?= in_array((int)$s['id'], $currentAssigneeIds, true) ? 'checked' : '' ?>> <?= e($s['name']) ?>
          </label>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="field"><label>Description</label><textarea name="description"><?= e($lead['description'] ?? '') ?></textarea></div>
    <button class="btn" type="submit">Save changes</button>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0">Activity</h3>
  <form method="post" style="margin-bottom:16px">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="add_note">
    <div class="field"><textarea name="note" placeholder="Log a call, email, or update..." required></textarea></div>
    <button class="btn btn-sm" type="submit">Add note</button>
  </form>
  <?php if ($activity): ?>
    <?php foreach ($activity as $i => $a): ?>
      <p class="<?= $i >= 5 ? 'activity-extra' : '' ?>" style="<?= $i >= 5 ? 'display:none;' : '' ?>margin:0 0 10px;padding-bottom:10px;border-bottom:1px solid var(--border)">
        <?= nl2br(e($a['note'])) ?><br>
        <span class="text-muted" style="font-size:12px"><?= e($a['user_name'] ?? 'System') ?> · <?= date('d M Y, H:i', strtotime($a['created_at'])) ?></span>
      </p>
    <?php endforeach; ?>
    <?php if (count($activity) > 5): ?>
      <button type="button" class="btn btn-sm btn-secondary" id="activityToggle" onclick="
        document.querySelectorAll('.activity-extra').forEach(el => el.style.display = 'block');
        this.remove();
      "><?= count($activity) - 5 ?> more entries — show all</button>
    <?php endif; ?>
  <?php else: ?><p class="text-muted">No activity logged yet.</p><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>