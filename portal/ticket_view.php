<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/uploads.php';
require_role('client');

$user = current_user();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM tickets WHERE id = ?');
$stmt->execute([$id]);
$ticket = $stmt->fetch();
if (!$ticket) { http_response_code(404); die('Ticket not found.'); }
if ($ticket['client_id'] != $user['client_id']) { http_response_code(403); die('Not authorised.'); }

$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
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
            $error = $uploadError;
        } else {
            $stmt = $pdo->prepare('INSERT INTO ticket_replies (ticket_id, user_id, message) VALUES (?,?,?)');
            $stmt->execute([$id, $user['id'], $message !== '' ? $message : '(attachment only)']);
            $replyId = (int)$pdo->lastInsertId();

            if ($upload !== null) {
                $stmt = $pdo->prepare(
                    'INSERT INTO ticket_attachments (ticket_id, ticket_reply_id, uploaded_by, original_filename, stored_filename, filesize)
                     VALUES (?,?,?,?,?,?)'
                );
                $stmt->execute([$id, $replyId, $user['id'], $upload['original_filename'], $upload['stored_filename'], $upload['filesize']]);
            }
            // Reopen a closed ticket if the client replies again.
            if ($ticket['status'] === 'closed') {
                $pdo->prepare("UPDATE tickets SET status = 'open' WHERE id = ?")->execute([$id]);
            }
            $notice = $uploadError !== '' ? "Reply sent, but the file couldn't be attached: $uploadError" : 'Reply sent.';
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM tickets WHERE id = ?');
$stmt->execute([$id]);
$ticket = $stmt->fetch();

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
    <p><span class="badge badge-<?= ticket_status_badge($ticket['status']) ?>"><?= ticket_status_label($ticket['status']) ?></span></p>
  </div>
</div>

<?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <h3 style="margin-top:0">Your request</h3>
  <p><?= nl2br(e($ticket['description'])) ?></p>
  <?php if ($attachmentsOnTicket): ?>
    <ul style="margin:8px 0 0;padding-left:18px">
      <?php foreach ($attachmentsOnTicket as $a): ?>
        <li><a href="<?= APP_URL ?>/attachments/download.php?type=ticket&id=<?= $a['id'] ?>"><?= e($a['original_filename']) ?></a></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <p class="text-muted" style="margin-top:12px;font-size:12px">Raised <?= date('d M Y, H:i', strtotime($ticket['created_at'])) ?></p>
</div>

<div class="card">
  <h3 style="margin-top:0">Conversation</h3>
  <?php if (!$replies): ?>
    <p class="text-muted">No replies yet — we'll respond here soon.</p>
  <?php else: ?>
    <?php foreach ($replies as $r): ?>
      <div style="margin:0 0 14px;padding-bottom:14px;border-bottom:1px solid var(--border)">
        <p style="margin:0 0 4px"><strong><?= $r['user_role'] === 'client' ? 'You' : e($r['user_name'] ?? 'Himal Tech') ?></strong>
          <span class="text-muted" style="font-size:12px"> · <?= date('d M Y, H:i', strtotime($r['created_at'])) ?></span></p>
        <p style="margin:0"><?= nl2br(e($r['message'])) ?></p>
        <?php if (!empty($attachmentsByReply[$r['id']])): ?>
          <ul style="margin:6px 0 0;padding-left:18px">
            <?php foreach ($attachmentsByReply[$r['id']] as $a): ?>
              <li><a href="<?= APP_URL ?>/attachments/download.php?type=ticket&id=<?= $a['id'] ?>"><?= e($a['original_filename']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" style="margin-top:12px">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="field"><textarea name="message" placeholder="Add a reply..."></textarea></div>
    <div class="field"><label>Attach a file (optional)</label><input type="file" name="attachment"></div>
    <button class="btn btn-sm" type="submit">Send reply</button>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
