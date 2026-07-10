<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_role('admin', 'staff');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT q.*, c.company_name FROM quotes q JOIN clients c ON c.id = q.client_id WHERE q.id = ?');
$stmt->execute([$id]);
$quote = $stmt->fetch();
if (!$quote) { http_response_code(404); die('Quote not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $newStatus = $_POST['status'] ?? $quote['status'];
    $stmt = $pdo->prepare('UPDATE quotes SET status = ? WHERE id = ?');
    $stmt->execute([$newStatus, $id]);
    log_activity($pdo, current_user()['id'], $quote['client_id'], $quote['lead_id'], "Quote {$quote['quote_number']} marked " . quote_status_label($newStatus));
    if ($newStatus === 'accepted' && $quote['lead_id']) {
        $pdo->prepare("UPDATE leads SET stage = 'won' WHERE id = ?")->execute([$quote['lead_id']]);
    }
    if ($newStatus === 'sent' && $quote['status'] !== 'sent') {
        $quoteForEmail = $quote;
        $quoteForEmail['status'] = $newStatus;
        notify_quote_sent($pdo, $quoteForEmail, $quote['company_name']);
    }
    header('Location: view.php?id=' . $id);
    exit;
}

$items = $pdo->prepare('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order');
$items->execute([$id]);
$items = $items->fetchAll();

$pageTitle = $quote['quote_number'];
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><span class="ref-code" style="font-size:15px;padding:4px 10px"><?= e($quote['quote_number']) ?></span></h1>
    <p><a href="<?= APP_URL ?>/clients/view.php?id=<?= $quote['client_id'] ?>"><?= e($quote['company_name']) ?></a> ·
      <span class="badge badge-<?= $quote['status'] ?>"><?= quote_status_label($quote['status']) ?></span></p>
  </div>
  <a class="btn btn-secondary" href="print.php?id=<?= $id ?>" target="_blank">Print / PDF view</a>
  <a class="btn btn-secondary" href="pdf.php?id=<?= $id ?>">Download PDF</a>
  <?php if ($quote['status'] === 'draft'): ?>
  <a class="btn btn-secondary" href="edit.php?id=<?= $id ?>">Edit</a>
  <?php endif; ?>
</div>

<div class="card">
  <table class="table">
    <thead><tr><th>Description</th><th>Qty</th><th>Unit price</th><th>Line total</th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): ?>
      <tr>
        <td><?= e($it['description']) ?></td>
        <td><?= rtrim(rtrim(number_format($it['quantity'], 2), '0'), '.') ?></td>
        <td><?= money($it['unit_price']) ?></td>
        <td><?= money($it['line_total']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div style="text-align:right;margin-top:16px">
    <p>Subtotal: <?= money($quote['subtotal']) ?></p>
    <p>Tax (<?= rtrim(rtrim(number_format($quote['tax_rate'],2),'0'),'.') ?>%): <?= money($quote['tax_amount']) ?></p>
    <p style="font-size:16px"><strong>Total: <?= money($quote['total']) ?></strong></p>
  </div>
  <?php if ($quote['notes']): ?><p><strong>Notes:</strong> <?= nl2br(e($quote['notes'])) ?></p><?php endif; ?>
</div>

<div class="card" style="max-width:400px">
  <h3 style="margin-top:0">Update status</h3>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="field">
      <select name="status">
        <?php foreach (['draft','sent','accepted','declined','expired'] as $s): ?>
          <option value="<?= $s ?>" <?= $quote['status'] === $s ? 'selected' : '' ?>><?= quote_status_label($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-sm" type="submit">Save status</button>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
