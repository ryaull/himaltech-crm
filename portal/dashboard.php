<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_role('client');

$user = current_user();
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $quoteId = (int)($_POST['quote_id'] ?? 0);
    $decision = $_POST['decision'] ?? '';

    if (!in_array($decision, ['accepted', 'declined'], true)) {
        $error = 'Invalid request.';
    } else {
        // Ownership + status check together: this can only ever act on a
        // quote that belongs to this client's own company AND is currently
        // Sent — a client can't accept/decline a draft, or something
        // already resolved, or another company's quote by guessing an ID.
        $stmt = $pdo->prepare("SELECT * FROM quotes WHERE id = ? AND client_id = ? AND status = 'sent'");
        $stmt->execute([$quoteId, $user['client_id']]);
        $quote = $stmt->fetch();

        if (!$quote) {
            $error = 'That quote is no longer available to respond to.';
        } else {
            $pdo->prepare('UPDATE quotes SET status = ? WHERE id = ?')->execute([$decision, $quoteId]);
            if ($decision === 'accepted' && $quote['lead_id']) {
                $pdo->prepare("UPDATE leads SET stage = 'won' WHERE id = ?")->execute([$quote['lead_id']]);
            }
            log_activity($pdo, $user['id'], $user['client_id'], $quote['lead_id'],
                "Client {$decision} quote {$quote['quote_number']}");

            if ($quote['created_by']) {
                notify_quote_response($pdo, (int)$quote['created_by'], $quote, $decision);
            }
            $notice = $decision === 'accepted' ? 'Quote accepted — thank you!' : 'Quote declined.';
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$user['client_id']]);
$client = $stmt->fetch();

$quotes = $pdo->prepare("SELECT * FROM quotes WHERE client_id = ? AND status != 'draft' ORDER BY created_at DESC");
$quotes->execute([$user['client_id']]);
$quotes = $quotes->fetchAll();

$pageTitle = 'My Quotes';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Welcome, <?= e($client['company_name'] ?? '') ?></h1>
    <p>Quotes and proposals from Himal Tech.</p>
  </div>
</div>

<?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <?php if (!$quotes): ?>
    <div class="empty-state"><h3>No quotes yet</h3><p>When Himal Tech sends you a quote, it will appear here.</p></div>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Number</th><th>Status</th><th>Total</th><th>Issued</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($quotes as $q): ?>
      <tr>
        <td><span class="ref-code"><?= e($q['quote_number']) ?></span></td>
        <td><span class="badge badge-<?= $q['status'] ?>"><?= quote_status_label($q['status']) ?></span></td>
        <td><?= money($q['total']) ?></td>
        <td class="text-muted"><?= date('d M Y', strtotime($q['issue_date'])) ?></td>
        <td>
          <a href="<?= APP_URL ?>/quotes/print.php?id=<?= $q['id'] ?>" target="_blank">View / print</a> ·
          <a href="<?= APP_URL ?>/quotes/pdf.php?id=<?= $q['id'] ?>">Download PDF</a>
          <?php if ($q['status'] === 'sent'): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Accept this quote?');">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="quote_id" value="<?= $q['id'] ?>">
              <input type="hidden" name="decision" value="accepted">
              <button class="btn btn-sm" type="submit" style="margin-left:6px">Accept</button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Decline this quote?');">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="quote_id" value="<?= $q['id'] ?>">
              <input type="hidden" name="decision" value="declined">
              <button class="btn btn-sm btn-secondary" type="submit">Decline</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
