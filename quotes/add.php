<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin', 'staff');

$presetClientId = (int)($_GET['client_id'] ?? 0);
$presetLeadId = (int)($_GET['lead_id'] ?? 0);
$clients = $pdo->query("SELECT id, company_name FROM clients ORDER BY company_name")->fetchAll();
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
    $clientId = (int)($_POST['client_id'] ?? 0);
    $leadId = ($_POST['lead_id'] ?? '') !== '' ? (int)$_POST['lead_id'] : null;
    $descriptions = $_POST['item_desc'] ?? [];
    $qtys = $_POST['item_qty'] ?? [];
    $prices = $_POST['item_price'] ?? [];
    $taxRate = (float)($_POST['tax_rate'] ?? 20);

    if (!$clientId || empty($descriptions)) {
        $error = 'Select a client and add at least one line item.';
    } elseif ($taxRate < 0) {
        $error = 'Tax rate cannot be negative.';
    } else {
        $subtotal = 0;
        $items = [];
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
            $items[] = [$desc, $qty, $price, $lineTotal];
        }
        if ($invalidRow) {
            $error = 'Quantities must be greater than zero and prices cannot be negative — please fix the highlighted line item(s).';
        } elseif (!$items) {
            $error = 'Add at least one valid line item.';
        } else {
            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $total = $subtotal + $taxAmount;

            $quoteId = null;
            $attempts = 0;
            do {
                $attempts++;
                $quoteNumber = next_quote_number($pdo);
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare(
                        'INSERT INTO quotes (quote_number, client_id, lead_id, status, issue_date, valid_until, subtotal, tax_rate, tax_amount, total, notes, created_by)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
                    );
                    $stmt->execute([
                        $quoteNumber, $clientId, $leadId, 'draft',
                        date('Y-m-d'),
                        trim($_POST['valid_until'] ?? '') ?: null,
                        $subtotal, $taxRate, $taxAmount, $total,
                        trim($_POST['notes'] ?? '') ?: null,
                        current_user()['id'],
                    ]);
                    $quoteId = $pdo->lastInsertId();

                    $itemStmt = $pdo->prepare(
                        'INSERT INTO quote_items (quote_id, description, quantity, unit_price, line_total, sort_order) VALUES (?,?,?,?,?,?)'
                    );
                    foreach ($items as $idx => $item) {
                        $itemStmt->execute([$quoteId, $item[0], $item[1], $item[2], $item[3], $idx]);
                    }
                    $pdo->commit();
                } catch (PDOException $ex) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $quoteId = null;
                    // 23000 = integrity constraint violation (e.g. two people grabbed
                    // the same quote number at once). Retry with a fresh number.
                    if ($ex->getCode() === '23000' && $attempts < 3) {
                        continue;
                    }
                    error_log('Quote creation failed: ' . $ex->getMessage());
                    $error = 'Sorry, the quote could not be saved. Please try again.';
                    break;
                }
            } while ($quoteId === null && $attempts < 3);

            if ($quoteId !== null) {
                log_activity($pdo, current_user()['id'], $clientId, $leadId, "Quote $quoteNumber created (" . money($total) . ')');
                if ($leadId) {
                    $pdo->prepare("UPDATE leads SET stage = 'quoted' WHERE id = ? AND stage NOT IN ('won','lost')")->execute([$leadId]);
                }
                header('Location: view.php?id=' . $quoteId);
                exit;
            }
        }
    }
}

$pageTitle = 'Create quote';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div><h1>Create quote</h1></div></div>

<div class="card" style="max-width:760px">
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" id="quoteForm">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="field-row">
      <div class="field"><label>Client *</label>
        <select name="client_id" required>
          <option value="">Select a client…</option>
          <?php foreach ($clients as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $presetClientId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['company_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Valid until</label><input type="date" name="valid_until"></div>
    </div>
    <input type="hidden" name="lead_id" value="<?= $presetLeadId ?: '' ?>">

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
        <tr>
          <td><input name="item_desc[]" required></td>
          <td><input name="item_qty[]" type="number" step="0.01" min="0.01" value="1" class="qty"></td>
          <td><input name="item_price[]" type="number" step="0.01" min="0" value="0" class="price"></td>
          <td class="line-total">£0.00</td>
          <td><button type="button" class="btn btn-sm btn-secondary remove-row">×</button></td>
        </tr>
      </tbody>
    </table>
    <button type="button" class="btn btn-sm btn-secondary" id="addRow" style="margin:10px 0 20px">+ Add line item</button>

    <div class="field-row">
      <div class="field"><label>Tax rate (%)</label><input type="number" step="0.01" min="0" name="tax_rate" id="taxRate" value="20"></div>
      <div class="field"><label>Notes</label><input name="notes" placeholder="Optional terms or notes"></div>
    </div>

    <div style="text-align:right;margin-bottom:20px;font-size:14px">
      Subtotal: <strong id="subtotalOut">£0.00</strong> &nbsp;·&nbsp;
      Tax: <strong id="taxOut">£0.00</strong> &nbsp;·&nbsp;
      Total: <strong id="totalOut">£0.00</strong>
    </div>

    <button class="btn" type="submit">Save quote</button>
    <a class="btn btn-secondary" href="list.php">Cancel</a>
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
  // First row is still the untouched default blank row? Only fill it if truly empty, else append.
  const rows = document.querySelectorAll('#itemsBody tr');
  const firstDesc = rows[0] ? rows[0].querySelector('input[name="item_desc[]"]') : null;
  const price = svc.price_type === 'custom' ? 0 : parseFloat(svc.price);
  const label = svc.price_type === 'custom' ? svc.name + ' (custom pricing — confirm cost)' : svc.name;
  if (rows.length === 1 && firstDesc && firstDesc.value.trim() === '') {
    firstDesc.value = label;
    rows[0].querySelector('.price').value = price;
  } else {
    document.getElementById('itemsBody').appendChild(rowTemplate(label, 1, price));
  }
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
