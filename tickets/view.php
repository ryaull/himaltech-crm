<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/uploads.php';
require_once __DIR__ . '/../includes/mailer.php';
require_role('admin', 'staff');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT t.*, c.company_name FROM tickets t JOIN clients c ON c.id = t.client_id WHERE t.id = ?');
$stmt->execute([$id]);
$ticket = $stmt->fetch();
if (!$ticket) { http_response_code(404); die('Ticket not found.'); }

$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_meta') {
        $status = $_POST['status'] ?? $ticket['status'];
        $priority = $_POST['priority'] ?? $ticket['priority'];
        $newAssigneeIds = array_filter(array_map('intval', $_POST['assigned_to'] ?? []));
        $primaryAssignee = $newAssigneeIds ? $newAssigneeIds[0] : null;

        $stmt = $pdo->prepare('SELECT user_id FROM ticket_assignees WHERE ticket_id = ?');
        $stmt->execute([$id]);
        $previousAssigneeIds = array_map('intval', array_column($stmt->fetchAll(), 'user_id'));

        $stmt = $pdo->prepare('UPDATE tickets SET status = ?, priority = ?, assigned_to = ? WHERE id = ?');
        $stmt->execute([$status, $priority, $primaryAssignee, $id]);
        log_activity($pdo, current_user()['id'], $ticket['client_id'], null,
            "Ticket {$ticket['ticket_number']} updated: " . ticket_status_label($status) . ', ' . ticket_priority_label($priority));

        $pdo->prepare('DELETE FROM ticket_assignees WHERE ticket_id = ?')->execute([$id]);
        foreach ($newAssigneeIds as $uid) {
            $pdo->prepare('INSERT IGNORE INTO ticket_assignees (ticket_id, user_id) VALUES (?,?)')->execute([$id, $uid]);
            if (!in_array($uid, $previousAssigneeIds, true) && $uid !== current_user()['id']) {
                notify_ticket_assigned($pdo, $uid, $ticket);
            }
        }

        $notice = 'Ticket updated.';
        $ticket['status'] = $status; $ticket['priority'] = $priority; $ticket['assigned_to'] = $primaryAssignee;
    }

    if ($action === 'reply') {
        $message = trim($_POST['message'] ?? '');
        $hasFile = !empty($_FILES['attachment']['name']);

        if ($message === '' && !$hasFile) {
            $error = 'Write a message or attach a file.';
        } else {
            $upload = null;
            $uploadError = '';
            if ($hasFile) {
                try {
                    $upload = handle_upload($_FILES['attachment'], 'tickets');
                } catch (RuntimeException $e) {
                    $uploadError = $e->getMessage();
                }
            }

            if ($message === '' && $upload === null) {
                // Nothing usable came through (message empty, upload failed) — don't post an empty reply.
                $error = $uploadError;
            } else {
                $stmt = $pdo->prepare('INSERT INTO ticket_replies (ticket_id, user_id, message) VALUES (?,?,?)');
                $stmt->execute([$id, current_user()['id'], $message !== '' ? $message : '(attachment only)']);
                $replyId = (int)$pdo->lastInsertId();

                if ($upload !== null) {
                    $stmt = $pdo->prepare(
                        'INSERT INTO ticket_attachments (ticket_id, ticket_reply_id, uploaded_by, original_filename, stored_filename, filesize)
                         VALUES (?,?,?,?,?,?)'
                    );
                    $stmt->execute([$id, $replyId, current_user()['id'], $upload['original_filename'], $upload['stored_filename'], $upload['filesize']]);
                }

                $pdo->prepare("UPDATE tickets SET status = 'in_progress' WHERE id = ? AND status = 'open'")->execute([$id]);
                if ($message !== '') {
                    notify_ticket_reply($pdo, $ticket);
                }
                $notice = $uploadError !== '' ? "Reply posted, but the file couldn't be attached: $uploadError" : 'Reply posted.';
            }
        }
    }
}

$staffUsers = $pdo->query("SELECT id, name FROM users WHERE role IN ('admin','staff') ORDER BY name")->fetchAll();

$currentAssigneeIds = $pdo->prepare('SELECT user_id FROM ticket_assignees WHERE ticket_id = ?');
$currentAssigneeIds->execute([$id]);
$currentAssigneeIds = array_map('intval', array_column($currentAssigneeIds->fetchAll(), 'user_id'));

$replies = $pdo->prepare(
    'SELECT r.*, u.name AS user_name, u.role AS user_role FROM ticket_replies r
     LEFT JOIN users u ON u.id = r.user_id WHERE r.ticket_id = ? ORDER BY r.created_at ASC'
);
$replies->execute([$id]);
$replies = $replies->fetchAll();

$attachments = $pdo->prepare('SELECT * FROM ticket_attachments WHERE ticket_id = ? ORDER BY created_at ASC');
$attachments->execute([$id]);
$attachments = $attachments->fetchAll();
$attachmentsByReply = [];
$attachmentsOnTicket = [];
foreach ($attachments as $a) {
    if ($a['ticket_reply_id']) { $attachmentsByReply[$a['ticket_reply_id']][] = $a; }
    else { $attachmentsOnTicket[] = $a; }
}

$pageTitle = $ticket['ticket_number'];
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><span class="ref-code" style="font-size:14px;padding:3px 9px;vertical-align:2px"><?= e($ticket['ticket_number']) ?></span> <?= e($ticket['subject']) ?></h1>
    <p><a href="<?= APP_URL ?>/clients/view.php?id=<?= $ticket['client_id'] ?>"><?= e($ticket['company_name']) ?></a> ·
      <span class="badge badge-<?= ticket_status_badge($ticket['status']) ?>"><?= ticket_status_label($ticket['status']) ?></span> ·
      <span class="badge badge-priority-<?= $ticket['priority'] ?>"><?= ticket_priority_label($ticket['priority']) ?></span> priority</p>
  </div>
</div>

<?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <h3 style="margin-top:0">Original request</h3>
  <p><?= nl2br(e($ticket['description'])) ?></p>
  <?php if ($attachmentsOnTicket): ?>
    <p><strong>Attachments:</strong></p>
    <ul style="margin:0;padding-left:18px">
      <?php foreach ($attachmentsOnTicket as $a): ?>
        <li><a href="<?= APP_URL ?>/attachments/download.php?type=ticket&id=<?= $a['id'] ?>"><?= e($a['original_filename']) ?></a> <span class="text-muted">(<?= human_filesize($a['filesize']) ?>)</span></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <p class="text-muted" style="margin-top:12px;font-size:12px">Raised <?= date('d M Y, H:i', strtotime($ticket['created_at'])) ?></p>
</div>

<div class="card" style="max-width:420px">
  <h3 style="margin-top:0">Manage ticket</h3>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="update_meta">
    <div class="field-row">
      <div class="field">
        <label>Status</label>
        <select name="status">
          <?php foreach (['open','in_progress','closed'] as $s): ?>
            <option value="<?= $s ?>" <?= $ticket['status'] === $s ? 'selected' : '' ?>><?= ticket_status_label($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Priority</label>
        <select name="priority">
          <?php foreach (['low','normal','high'] as $p): ?>
            <option value="<?= $p ?>" <?= $ticket['priority'] === $p ? 'selected' : '' ?>><?= ticket_priority_label($p) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="field">
      <label>Assigned to</label>
      <?php if (!$staffUsers): ?>
        <p class="text-muted" style="margin:0">No staff accounts yet.</p>
      <?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:10px 16px;padding:6px 0">
        <?php foreach ($staffUsers as $s): ?>
          <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:13.5px">
            <input type="checkbox" name="assigned_to[]" value="<?= $s['id'] ?>" <?= in_array((int)$s['id'], $currentAssigneeIds, true) ? 'checked' : '' ?>> <?= e($s['name']) ?>
          </label>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <button class="btn btn-sm" type="submit">Save</button>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0">Conversation</h3>
  <?php if (!$replies): ?>
    <p class="text-muted">No replies yet.</p>
  <?php else: ?>
    <?php foreach ($replies as $r): ?>
      <div style="margin:0 0 14px;padding-bottom:14px;border-bottom:1px solid var(--border)">
        <p style="margin:0 0 4px"><strong><?= e($r['user_name'] ?? 'Client') ?></strong>
          <span class="text-muted" style="font-size:12px"> · <?= $r['user_role'] === 'client' ? 'Client' : 'Staff' ?> · <?= date('d M Y, H:i', strtotime($r['created_at'])) ?></span></p>
        <p style="margin:0"><?= nl2br(e($r['message'])) ?></p>
        <?php if (!empty($attachmentsByReply[$r['id']])): ?>
          <ul style="margin:6px 0 0;padding-left:18px">
            <?php foreach ($attachmentsByReply[$r['id']] as $a): ?>
              <li><a href="<?= APP_URL ?>/attachments/download.php?type=ticket&id=<?= $a['id'] ?>"><?= e($a['original_filename']) ?></a> <span class="text-muted">(<?= human_filesize($a['filesize']) ?>)</span></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" style="margin-top:12px">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="reply">
    <div class="field"><textarea name="message" placeholder="Reply to the client..."></textarea></div>
    <div class="field"><label>Attach a file (optional)</label><input type="file" name="attachment"></div>
    <button class="btn btn-sm" type="submit">Post reply</button>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
