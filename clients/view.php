<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/uploads.php';
require_once __DIR__ . '/../includes/mailer.php';
require_role('admin', 'staff');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) { http_response_code(404); die('Client not found.'); }

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_contact') {
        $name = trim($_POST['contact_name'] ?? '');
        if ($name !== '') {
            $stmt = $pdo->prepare(
                'INSERT INTO contacts (client_id, name, email, phone, job_title, is_primary) VALUES (?,?,?,?,?,?)'
            );
            $stmt->execute([
                $id, $name,
                trim($_POST['contact_email'] ?? '') ?: null,
                trim($_POST['contact_phone'] ?? '') ?: null,
                trim($_POST['contact_title'] ?? '') ?: null,
                isset($_POST['contact_primary']) ? 1 : 0,
            ]);
            $notice = 'Contact added.';
        }
    }

    if ($action === 'add_note') {
        $note = trim($_POST['note'] ?? '');
        if ($note !== '') {
            log_activity($pdo, current_user()['id'], $id, null, $note);
            $notice = 'Note added.';
        }
    }

    if ($action === 'upload_file') {
        if (empty($_FILES['attachment']['name'])) {
            $error = 'Choose a file to upload.';
        } else {
            try {
                $up = handle_upload($_FILES['attachment'], 'clients');
                $stmt = $pdo->prepare(
                    'INSERT INTO client_attachments (client_id, uploaded_by, original_filename, stored_filename, filesize)
                     VALUES (?,?,?,?,?)'
                );
                $stmt->execute([$id, current_user()['id'], $up['original_filename'], $up['stored_filename'], $up['filesize']]);
                $notice = 'File uploaded.';
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }
    }

    if ($action === 'delete_file') {
        $fileId = (int)($_POST['file_id'] ?? 0);
        // Ownership check: only ever delete a file that actually belongs to
        // *this* client record, so a crafted file_id from another client's
        // files can't be deleted from here.
        $stmt = $pdo->prepare('SELECT * FROM client_attachments WHERE id = ? AND client_id = ?');
        $stmt->execute([$fileId, $id]);
        $file = $stmt->fetch();
        if ($file) {
            $diskPath = __DIR__ . '/../uploads/clients/' . basename($file['stored_filename']);
            $pdo->prepare('DELETE FROM client_attachments WHERE id = ?')->execute([$fileId]);
            if (is_file($diskPath)) {
                @unlink($diskPath);
            }
            $notice = 'File deleted.';
        } else {
            $error = 'File not found.';
        }
    }

    if ($action === 'create_portal_login') {
        $pEmail = trim($_POST['portal_email'] ?? '');
        if (!filter_var($pEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } else {
            // Let the UNIQUE constraint on users.email be the source of truth,
            // rather than a check-then-insert that can race. A duplicate email
            // (code 23000) is reported cleanly instead of surfacing a 500.
            try {
                $randomPassword = bin2hex(random_bytes(16));
                $hash = password_hash($randomPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    'INSERT INTO users (name, email, password_hash, role, client_id) VALUES (?,?,?,?,?)'
                );
                $stmt->execute([$client['company_name'] . ' contact', $pEmail, $hash, 'client', $id]);
                $newUserId = (int)$pdo->lastInsertId();

                $token = create_reset_token($pdo, $newUserId, 'invite', 48);
                notify_staff_invite($pEmail, $client['company_name'], $token);

                $notice = 'Portal login created — an email has been sent to ' . e($pEmail) . ' with a link to set their password.';
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') {
                    $error = 'That email is already registered.';
                } else {
                    error_log('Portal login creation failed: ' . $ex->getMessage());
                    $error = 'Sorry, the portal login could not be created. Please try again.';
                }
            }
        }
    }
}

$contacts = $pdo->prepare('SELECT * FROM contacts WHERE client_id = ? ORDER BY is_primary DESC, name');
$contacts->execute([$id]);
$contacts = $contacts->fetchAll();

$leads = $pdo->prepare('SELECT * FROM leads WHERE client_id = ? ORDER BY updated_at DESC');
$leads->execute([$id]);
$leads = $leads->fetchAll();

$quotes = $pdo->prepare('SELECT * FROM quotes WHERE client_id = ? ORDER BY created_at DESC');
$quotes->execute([$id]);
$quotes = $quotes->fetchAll();

$activity = $pdo->prepare(
    'SELECT a.*, u.name AS user_name FROM activity_log a LEFT JOIN users u ON u.id = a.user_id
     WHERE a.client_id = ? ORDER BY a.created_at DESC LIMIT 20'
);
$activity->execute([$id]);
$activity = $activity->fetchAll();

$portalUsers = $pdo->prepare("SELECT * FROM users WHERE client_id = ? AND role = 'client'");
$portalUsers->execute([$id]);
$portalUsers = $portalUsers->fetchAll();

$attachmentsList = $pdo->prepare(
    'SELECT fa.*, u.name AS uploader_name FROM client_attachments fa
     LEFT JOIN users u ON u.id = fa.uploaded_by WHERE fa.client_id = ? ORDER BY fa.created_at DESC'
);
$attachmentsList->execute([$id]);
$attachmentsList = $attachmentsList->fetchAll();

$openTickets = $pdo->prepare("SELECT * FROM tickets WHERE client_id = ? ORDER BY FIELD(status,'open','in_progress','closed'), updated_at DESC");
$openTickets->execute([$id]);
$openTickets = $openTickets->fetchAll();

$pageTitle = $client['company_name'];
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><?= e($client['company_name']) ?></h1>
    <p><span class="badge badge-<?= $client['status'] === 'active' ? 'won' : ($client['status'] === 'lead' ? 'new' : 'lost') ?>"><?= ucfirst($client['status']) ?></span>
    <?= $client['industry'] ? ' · ' . e($client['industry']) : '' ?></p>
  </div>
  <div>
    <a class="btn btn-secondary" href="edit.php?id=<?= $id ?>">Edit</a>
    <a class="btn" href="<?= APP_URL ?>/leads/add.php?client_id=<?= $id ?>">+ New lead</a>
  </div>
</div>

<?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <h3 style="margin-top:0">Details</h3>
  <p><strong>Website:</strong> <?= $client['website'] ? '<a href="' . e($client['website']) . '" target="_blank">' . e($client['website']) . '</a>' : '—' ?></p>
  <p><strong>Phone:</strong> <?= e($client['phone'] ?? '—') ?></p>
  <p><strong>Address:</strong> <?= e($client['address'] ?? '—') ?></p>
  <p><strong>Source:</strong> <?= e($client['source'] ?? '—') ?></p>
  <?php if ($client['notes']): ?><p><strong>Notes:</strong> <?= nl2br(e($client['notes'])) ?></p><?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0">Contacts</h3>
  <?php if ($contacts): ?>
  <table class="table">
    <thead><tr><th>Name</th><th>Title</th><th>Email</th><th>Phone</th></tr></thead>
    <tbody>
    <?php foreach ($contacts as $c): ?>
      <tr>
        <td><?= e($c['name']) ?> <?= $c['is_primary'] ? '<span class="badge">Primary</span>' : '' ?></td>
        <td><?= e($c['job_title'] ?? '—') ?></td>
        <td><?= e($c['email'] ?? '—') ?></td>
        <td><?= e($c['phone'] ?? '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?><p class="text-muted">No contacts added yet.</p><?php endif; ?>

  <form method="post" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="add_contact">
    <div class="field-row">
      <div class="field"><label>Name</label><input name="contact_name" required></div>
      <div class="field"><label>Job title</label><input name="contact_title"></div>
    </div>
    <div class="field-row">
      <div class="field"><label>Email</label><input type="email" name="contact_email"></div>
      <div class="field"><label>Phone</label><input name="contact_phone"></div>
    </div>
    <label style="font-weight:400;display:flex;align-items:center;gap:6px;margin-bottom:12px">
      <input type="checkbox" name="contact_primary" style="width:auto"> Primary contact
    </label>
    <button class="btn btn-sm" type="submit">Add contact</button>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0">Leads</h3>
  <?php if ($leads): ?>
  <table class="table">
    <thead><tr><th>Title</th><th>Stage</th><th>Est. value</th><th>Updated</th></tr></thead>
    <tbody>
    <?php foreach ($leads as $l): ?>
      <tr onclick="location.href='<?= APP_URL ?>/leads/view.php?id=<?= $l['id'] ?>'" style="cursor:pointer">
        <td><?= e($l['title']) ?></td>
        <td><span class="badge badge-<?= $l['stage'] ?>"><?= stage_label($l['stage']) ?></span></td>
        <td><?= $l['value_estimate'] ? money($l['value_estimate']) : '—' ?></td>
        <td class="text-muted"><?= date('d M Y', strtotime($l['updated_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?><p class="text-muted">No leads for this client yet.</p><?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0">Quotes</h3>
  <?php if ($quotes): ?>
  <table class="table">
    <thead><tr><th>Number</th><th>Status</th><th>Total</th><th>Issued</th></tr></thead>
    <tbody>
    <?php foreach ($quotes as $q): ?>
      <tr onclick="location.href='<?= APP_URL ?>/quotes/view.php?id=<?= $q['id'] ?>'" style="cursor:pointer">
        <td><span class="ref-code"><?= e($q['quote_number']) ?></span></td>
        <td><span class="badge badge-<?= $q['status'] ?>"><?= quote_status_label($q['status']) ?></span></td>
        <td><?= money($q['total']) ?></td>
        <td class="text-muted"><?= date('d M Y', strtotime($q['issue_date'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?><p class="text-muted">No quotes for this client yet.</p>
  <a class="btn btn-sm" href="<?= APP_URL ?>/quotes/add.php?client_id=<?= $id ?>">+ Create quote</a>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0">Files</h3>
  <?php if ($attachmentsList): ?>
  <table class="table">
    <thead><tr><th>File</th><th>Size</th><th>Uploaded by</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($attachmentsList as $f): ?>
      <tr>
        <td><a href="<?= APP_URL ?>/attachments/download.php?type=client&id=<?= $f['id'] ?>"><?= e($f['original_filename']) ?></a></td>
        <td class="text-muted"><?= human_filesize($f['filesize']) ?></td>
        <td><?= e($f['uploader_name'] ?? '—') ?></td>
        <td class="text-muted"><?= date('d M Y', strtotime($f['created_at'])) ?></td>
        <td>
          <form method="post" onsubmit="return confirm('Delete this file? This can\'t be undone.');" style="display:inline">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="delete_file">
            <input type="hidden" name="file_id" value="<?= $f['id'] ?>">
            <button class="btn btn-sm btn-secondary" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?><p class="text-muted">No files uploaded yet.</p><?php endif; ?>

  <form method="post" enctype="multipart/form-data" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="upload_file">
    <div class="field"><label>Upload a file (contract, brand assets, report...)</label><input type="file" name="attachment" required></div>
    <button class="btn btn-sm" type="submit">Upload</button>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0">Tickets</h3>
  <?php if ($openTickets): ?>
  <table class="table">
    <thead><tr><th>Number</th><th>Subject</th><th>Status</th><th>Updated</th></tr></thead>
    <tbody>
    <?php foreach ($openTickets as $t): ?>
      <tr onclick="location.href='<?= APP_URL ?>/tickets/view.php?id=<?= $t['id'] ?>'" style="cursor:pointer">
        <td><span class="ref-code"><?= e($t['ticket_number']) ?></span></td>
        <td><?= e($t['subject']) ?></td>
        <td><span class="badge badge-<?= ticket_status_badge($t['status']) ?>"><?= ticket_status_label($t['status']) ?></span></td>
        <td class="text-muted"><?= date('d M Y', strtotime($t['updated_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?><p class="text-muted">No tickets raised by this client yet.</p><?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0">Client portal access</h3>
  <?php if ($portalUsers): ?>
    <ul style="margin:0;padding-left:18px">
      <?php foreach ($portalUsers as $pu): ?><li><?= e($pu['email']) ?></li><?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="text-muted">This client has no portal login yet. Create one so they can view their quotes online.</p>
  <?php endif; ?>
  <form method="post" style="margin-top:12px">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="create_portal_login">
    <div class="field"><label>Login email</label><input type="email" name="portal_email" required></div>
    <button class="btn btn-sm" type="submit">Create portal login &amp; send invite</button>
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
