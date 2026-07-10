<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT q.*, c.company_name, c.address, c.phone FROM quotes q JOIN clients c ON c.id = q.client_id WHERE q.id = ?');
$stmt->execute([$id]);
$quote = $stmt->fetch();
if (!$quote) { http_response_code(404); die('Quote not found.'); }

$user = current_user();
if ($user['role'] === 'client' && $user['client_id'] != $quote['client_id']) {
    http_response_code(403); die('Not authorised.');
}
if ($user['role'] === 'client' && $quote['status'] === 'draft') {
    http_response_code(403); die('This quote is not available yet.');
}

$items = $pdo->prepare('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order');
$items->execute([$id]);
$items = $items->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Quote <?= e($quote['quote_number']) ?></title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap');
  body { font-family: 'IBM Plex Sans', system-ui, sans-serif; color: #14121a; max-width: 720px; margin: 40px auto; padding: 0 20px; }
  .top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 32px; padding-bottom: 20px; border-bottom: 2px solid #9400ff; }
  .brand { font-family: 'Sora', sans-serif; font-weight: 700; font-size: 20px; color: #14121a; }
  h1 { font-family: 'IBM Plex Mono', monospace; font-size: 19px; font-weight: 500; margin: 0 0 4px; color: #7400cc; }
  table { width: 100%; border-collapse: collapse; margin: 24px 0; }
  th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #e5e1ea; font-size: 13.5px; }
  th { text-transform: uppercase; font-size: 11px; color: #6b6478; letter-spacing: 0.4px; }
  .totals { text-align: right; margin-top: 12px; }
  .totals p { margin: 4px 0; }
  .grand { font-size: 17px; font-weight: 700; color: #7400cc; }
  @media print { a.no-print { display: none; } }
</style>
</head>
<body>
  <div class="top">
    <div>
      <div class="brand">Himal Tech Ltd</div>
      <div style="color:#6b6478;font-size:13px">himaltech.co.uk</div>
    </div>
    <div style="text-align:right">
      <h1>Quote <?= e($quote['quote_number']) ?></h1>
      <div style="color:#6b6478;font-size:13px">Issued <?= date('d M Y', strtotime($quote['issue_date'])) ?></div>
      <?php if ($quote['valid_until']): ?><div style="color:#6b6478;font-size:13px">Valid until <?= date('d M Y', strtotime($quote['valid_until'])) ?></div><?php endif; ?>
    </div>
  </div>

  <p><strong>Prepared for:</strong><br>
  <?= e($quote['company_name']) ?><?= $quote['address'] ? '<br>' . e($quote['address']) : '' ?><?= $quote['phone'] ? '<br>' . e($quote['phone']) : '' ?></p>

  <table>
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

  <div class="totals">
    <p>Subtotal: <?= money($quote['subtotal']) ?></p>
    <p>Tax (<?= rtrim(rtrim(number_format($quote['tax_rate'],2),'0'),'.') ?>%): <?= money($quote['tax_amount']) ?></p>
    <p class="grand">Total: <?= money($quote['total']) ?></p>
  </div>

  <?php if ($quote['notes']): ?><p><strong>Notes:</strong><br><?= nl2br(e($quote['notes'])) ?></p><?php endif; ?>

  <p class="no-print" style="margin-top:32px"><a href="#" onclick="window.print();return false;">Print / Save as PDF</a></p>
</body>
</html>
