<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin', 'staff');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT q.*, c.company_name FROM quotes q JOIN clients c ON c.id = q.client_id WHERE q.id = ?');
$stmt->execute([$id]);
$quote = $stmt->fetch();
if (!$quote) { http_response_code(404); die('Quote not found.'); }

if ($quote['status'] !== 'draft') {
    // Editing a quote after it's been sent would silently change what the
    // client already saw/accepted/declined — not allowed. They can create a
    // fresh quote instead, which keeps a clean, honest history.
    $pageTitle = 'Cannot edit quote';
    include __DIR__ . '/../includes/header.php';
    ?>
    <div class="page-header"><div><h1>This quote can't be edited</h1></div></div>
    <div class="card" style="max-width:600px">
      <p>Quote <span class="ref-code"><?= e($quote['quote_number']) ?></span> is <?= e(quote_status_label($quote['status'])) ?>, so it's locked to keep an honest record of what the client actually saw.</p>
      <p>If the pricing needs to change, create a new quote instead.</p>
      <a class="btn" href="view.php?id=<?= $id ?>">Back to quote</a>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$items = $pdo->prepare('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order');
$items->execute([$id]);
$items = $items->fetchAll();

$catalogServices = $pdo->query(
    "SELECT s.id, s.name, s.price, s.price_type, s.unit_label, sc.name AS category_name
     FROM services s JOIN service_categories sc ON sc.id = s.category_id
     WHERE s.is_active = 1 ORDER BY sc.sort_order, s.sort_order"
)->fetchAll();
$catalogByCategory = [];
foreach ($catalogServices as $s) {
    $catalogByCategory[$s['category_name']][] = $s;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $descriptions = $_POST['item_desc'] ?? [];
    $qtys = $_POST['item_qty'] ?? [];
    $prices = $_POST['item_price'] ?? [];
    $taxRate = (float)($_POST['tax_rate'] ?? 20);

    if (empty($descriptions)) {
        $error = 'Add at least one line item.';
    } elseif ($taxRate < 0) {
        $error = 'Tax rate cannot be negative.';
    } else {
        $subtotal = 0;
        $newItems = [];
        $invalidRow = false;
        foreach ($descriptions as $i => $desc) {
            $desc = trim($desc);
            if ($desc === '') continue;
            $qty = (float)($qtys[$i] ?? 1);
            $price = (float)($prices[$i] ?? 0);
            if ($qty <= 0 || $price < 0) {
                $invalidRow = true;
                continue;
            }
            $lineTotal = round($qty * $price, 2);
            $subtotal += $lineTotal;
            $newItems[] = [$desc, $qty, $price, $lineTotal];
        }

        if ($invalidRow) {
            $error = 'Quantities must be greater than zero and prices cannot be negative — please fix the highlighted line item(s).';
        } elseif (!$newItems) {
            $error = 'Add at least one valid line item.';
        } else {
            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $total = $subtotal + $taxAmount;

            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'UPDATE quotes SET valid_until = ?, subtotal = ?, tax_rate = ?, tax_amount = ?, total = ?, notes = ? WHERE id = ?'
                );
                $stmt->execute([
                    trim($_POST['valid_until'] ?? '') ?: null,
                    $subtotal, $taxRate, $taxAmount, $total,
                    trim($_POST['notes'] ?? '') ?: null,
                    $id,
                ]);

                $pdo->prepare('DELETE FROM quote_items WHERE quote_id = ?')->execute([$id]);
                $itemStmt = $pdo->prepare(
                    'INSERT INTO quote_items (quote_id, description, quantity, unit_price, line_total, sort_order) VALUES (?,?,?,?,?,?)'
                );
                foreach ($newItems as $idx => $item) {
                    $itemStmt->execute([$id, $item[0], $item[1], $item[2], $item[3], $idx]);
                }
                $pdo->commit();

                log_activity($pdo, current_user()['id'], $quote['client_id'], $quote['lead_id'], "Quote {$quote['quote_number']} edited (" . money($total) . ')');
                header('Location: view.php?id=' . $id);
                exit;
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                error_log('Quote edit failed: ' . $ex->getMessage());
                $error = 'Sorry, the quote could not be saved. Please try again.';
            }
        }
    }
}

$pageTitle = 'Edit quote';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div><h1>Edit quote <span class="ref-code" style="font-size:14px;padding:3px 9px;vertical-align:2px"><?= e($quote['quote_number']) ?></span></h1><p><?= e($quote['company_name']) ?></p></div></div>

<div class="card" style="max-width:760px">
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" id="quoteForm">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="field-row">
      <div class="field"><label>Client</label><input value="<?= e($quote['company_name']) ?>" disabled></div>
      <div class="field"><label>Valid until</label><input type="date" name="valid_until" value="<?= e($quote['valid_until'] ?? '') ?>"></div>
    </div>

    <label>Line items</label>
    <?php if ($catalogByCategory): ?>
    <div style="display:flex;gap:8px;margin-bottom:10px;align-items:center">
      <select id="catalogPicker" style="max-width:360px">
        <option value="">+ Add from catalog…</option>
        <?php foreach ($catalogByCategory as $catName => $svcList): ?>
          <optgroup label="<?= e($catName) ?>">
            <?php foreach ($svcList as $s): ?>
              <option value="<?= $s['id'] ?>"><?= e($s['name']) ?> — <?= service_price_display($s) ?></option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
      <button type="button" class="btn btn-sm btn-secondary" id="addFromCatalog">Add</button>
      <span class="text-muted" style="font-size:12px">or add a custom line item below</span>
    </div>
    <?php endif; ?>
    <table class="table" id="itemsTable">
      <thead><tr><th>Description</th><th style="width:80px">Qty</th><th style="width:120px">Unit price (£)</th><th style="width:110px">Line total</th><th></th></tr></thead>
      <tbody id="itemsBody">
        <?php foreach ($items as $it): ?>
        <tr>
          <td><input name="item_desc[]" required value="<?= e($it['description']) ?>"></td>
          <td><input name="item_qty[]" type="number" step="0.01" min="0.01" value="<?= e((string)$it['quantity']) ?>" class="qty"></td>
          <td><input name="item_price[]" type="number" step="0.01" min="0" value="<?= e((string)$it['unit_price']) ?>" class="price"></td>
          <td class="line-total">£<?= number_format((float)$it['line_total'], 2) ?></td>
          <td><button type="button" class="btn btn-sm btn-secondary remove-row">×</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <button type="button" class="btn btn-sm btn-secondary" id="addRow" style="margin:10px 0 20px">+ Add line item</button>

    <div class="field-row">
      <div class="field"><label>Tax rate (%)</label><input type="number" step="0.01" min="0" name="tax_rate" id="taxRate" value="<?= e((string)$quote['tax_rate']) ?>"></div>
      <div class="field"><label>Notes</label><input name="notes" value="<?= e($quote['notes'] ?? '') ?>" placeholder="Optional terms or notes"></div>
    </div>

    <div style="text-align:right;margin-bottom:20px;font-size:14px">
      Subtotal: <strong id="subtotalOut">£0.00</strong> &nbsp;·&nbsp;
      Tax: <strong id="taxOut">£0.00</strong> &nbsp;·&nbsp;
      Total: <strong id="totalOut">£0.00</strong>
    </div>

    <button class="btn" type="submit">Save changes</button>
    <a class="btn btn-secondary" href="view.php?id=<?= $id ?>">Cancel</a>
  </form>
</div>

<script>
const catalogData = <?= json_encode(array_column($catalogServices, null, 'id')) ?>;

function rowTemplate(desc, qty, price) {
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input name="item_desc[]" required value="${desc ? desc.replace(/"/g, '&quot;') : ''}"></td>
    <td><input name="item_qty[]" type="number" step="0.01" min="0.01" value="${qty || 1}" class="qty"></td>
    <td><input name="item_price[]" type="number" step="0.01" min="0" value="${price || 0}" class="price"></td>
    <td class="line-total">£0.00</td>
    <td><button type="button" class="btn btn-sm btn-secondary remove-row">×</button></td>`;
  return tr;
}
document.getElementById('addRow').addEventListener('click', () => {
  document.getElementById('itemsBody').appendChild(rowTemplate());
  recalc();
});
<?php if ($catalogByCategory): ?>
document.getElementById('addFromCatalog').addEventListener('click', () => {
  const picker = document.getElementById('catalogPicker');
  const svc = catalogData[picker.value];
  if (!svc) return;
  const price = svc.price_type === 'custom' ? 0 : parseFloat(svc.price);
  const label = svc.price_type === 'custom' ? svc.name + ' (custom pricing — confirm cost)' : svc.name;
  document.getElementById('itemsBody').appendChild(rowTemplate(label, 1, price));
  picker.value = '';
  recalc();
});
<?php endif; ?>
document.getElementById('itemsBody').addEventListener('click', (e) => {
  if (e.target.classList.contains('remove-row')) {
    const rows = document.querySelectorAll('#itemsBody tr');
    if (rows.length > 1) e.target.closest('tr').remove();
    recalc();
  }
});
document.getElementById('quoteForm').addEventListener('input', recalc);

function recalc() {
  let subtotal = 0;
  document.querySelectorAll('#itemsBody tr').forEach(row => {
    const qty = parseFloat(row.querySelector('.qty').value) || 0;
    const price = parseFloat(row.querySelector('.price').value) || 0;
    const lineTotal = qty * price;
    row.querySelector('.line-total').textContent = '£' + lineTotal.toFixed(2);
    subtotal += lineTotal;
  });
  const taxRate = parseFloat(document.getElementById('taxRate').value) || 0;
  const tax = subtotal * (taxRate / 100);
  document.getElementById('subtotalOut').textContent = '£' + subtotal.toFixed(2);
  document.getElementById('taxOut').textContent = '£' + tax.toFixed(2);
  document.getElementById('totalOut').textContent = '£' + (subtotal + tax).toFixed(2);
}
recalc();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
