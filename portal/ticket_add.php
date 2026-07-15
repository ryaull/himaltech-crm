<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/uploads.php';
require_role('client');

$user = current_user();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($subject === '' || $description === '') {
        $error = 'Please fill in both a subject and a description.';
    } else {
        // Validate + store the file up front (outside any transaction) so a
        // slow or rejected upload never holds a DB lock or half-writes a ticket.
        $upload = null;
        try {
            if (!empty($_FILES['attachment']['name'])) {
                $upload = handle_upload($_FILES['attachment'], 'tickets');
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        if ($error === '') {
            $ticketId = null;
            $attempts = 0;
            do {
                $attempts++;
                $ticketNumber = next_ticket_number($pdo);
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare(
                        'INSERT INTO tickets (ticket_number, client_id, subject, description, created_by) VALUES (?,?,?,?,?)'
                    );
                    $stmt->execute([$ticketNumber, $user['client_id'], $subject, $description, $user['id']]);
                    $ticketId = (int)$pdo->lastInsertId();

                    if ($upload !== null) {
                        $stmt = $pdo->prepare(
                            'INSERT INTO ticket_attachments (ticket_id, uploaded_by, original_filename, stored_filename, filesize)
                             VALUES (?,?,?,?,?)'
                        );
                        $stmt->execute([$ticketId, $user['id'], $upload['original_filename'], $upload['stored_filename'], $upload['filesize']]);
                    }

                    log_activity($pdo, $user['id'], $user['client_id'], null, "Client raised ticket $ticketNumber: $subject");
                    $pdo->commit();
                } catch (PDOException $ex) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $ticketId = null;
                    if ($ex->getCode() === '23000' && $attempts < 3) {
                        continue;
                    }
                    error_log('Ticket creation failed: ' . $ex->getMessage());
                    $error = 'Sorry, your request could not be submitted. Please try again.';
                    break;
                }
            } while ($ticketId === null && $attempts < 3);

            if ($ticketId !== null) {
                header('Location: ticket_view.php?id=' . $ticketId);
                exit;
            }
        }
    }
}

$pageTitle = 'New Request';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><h1>New Request</h1><p>Tell us what you need — we'll get back to you here.</p></div>
</div>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card" style="max-width:560px">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="field"><label>Subject</label><input name="subject" required maxlength="190"></div>
    <div class="field"><label>Description</label><textarea name="description" required placeholder="Describe what you need..."></textarea></div>
    <div class="field"><label>Attach a file (optional)</label><input type="file" name="attachment"></div>
    <button class="btn" type="submit">Submit request</button>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
