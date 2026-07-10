<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM services WHERE id = ?');
$stmt->execute([$id]);
$service = $stmt->fetch();
if (!$service) { http_response_code(404); die('Service not found.'); }

$categories = $pdo->query('SELECT * FROM service_categories ORDER BY sort_order, name')->fetchAll();
$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'update';

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM services WHERE id = ?')->execute([$id]);
        header('Location: list.php');
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $priceType = $_POST['price_type'] ?? 'flat';
    $price = (float)($_POST['price'] ?? 0);

    if ($name === '' || !$categoryId) {
        $error = 'Name and category are required.';
    } elseif (!in_array($priceType, ['flat', 'per_unit', 'monthly', 'custom'], true)) {
        $error = 'Invalid price type.';
    } elseif ($price < 0) {
        $error = 'Price cannot be negative.';
    } elseif ($priceType === 'per_unit' && trim($_POST['unit_label'] ?? '') === '') {
        $error = 'Per-unit pricing needs a unit label.';
    } else {
        $stmt = $pdo->prepare(
            'UPDATE services SET category_id=?, name=?, description=?, price=?, price_type=?, unit_label=?, keywords=?, is_active=?, sort_order=? WHERE id=?'
        );
        $stmt->execute([
            $categoryId, $name,
            trim($_POST['description'] ?? '') ?: null,
            $priceType === 'custom' ? 0 : $price,
            $priceType,
            $priceType === 'per_unit' ? trim($_POST['unit_label']) : null,
            trim($_POST['keywords'] ?? '') ?: null,
            isset($_POST['is_active']) ? 1 : 0,
            (int)($_POST['sort_order'] ?? 0),
            $id,
        ]);
        $notice = 'Service updated.';
        $stmt = $pdo->prepare('SELECT * FROM services WHERE id = ?');
        $stmt->execute([$id]);
        $service = $stmt->fetch();
    }
}

$pageTitle = 'Edit Service';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div><h1>Edit Service</h1></div></div>

<?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card" style="max-width:560px">
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="update">
    <div class="field"><label>Service name</label><input name="name" required value="<?= e($service['name']) ?>"></div>
    <div class="field">
      <label>Category</label>
      <select name="category_id" required>
        <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $c['id'] == $service['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>Description (optional)</label><textarea name="description"><?= e($service['description'] ?? '') ?></textarea></div>
    <div class="field-row">
      <div class="field">
        <label>Pricing model</label>
        <select name="price_type" id="priceType" onchange="document.getElementById('unitField').style.display = this.value === 'per_unit' ? 'block' : 'none'; document.getElementById('priceField').style.display = this.value === 'custom' ? 'none' : 'block';">
          <?php foreach (['flat' => 'Flat price', 'per_unit' => 'Per unit', 'monthly' => 'Monthly', 'custom' => 'Custom quote (no fixed price)'] as $val => $lbl): ?>
            <option value="<?= $val ?>" <?= $service['price_type'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" id="priceField" style="<?= $service['price_type'] === 'custom' ? 'display:none' : '' ?>"><label>Price (£)</label><input type="number" step="0.01" min="0" name="price" value="<?= e((string)$service['price']) ?>"></div>
    </div>
    <div class="field" id="unitField" style="<?= $service['price_type'] === 'per_unit' ? '' : 'display:none' ?>"><label>Unit label</label><input name="unit_label" value="<?= e($service['unit_label'] ?? '') ?>" placeholder="e.g. page, 1000 words, keyword"></div>
    <div class="field"><label>Keywords (optional)</label><input name="keywords" value="<?= e($service['keywords'] ?? '') ?>" placeholder="comma-separated"></div>
    <div class="field"><label><input type="checkbox" name="is_active" <?= $service['is_active'] ? 'checked' : '' ?> style="width:auto;display:inline-block;margin-right:6px"> Active (visible for use in quotes)</label></div>
    <button class="btn" type="submit">Save changes</button>
    <a class="btn btn-secondary" href="list.php">Cancel</a>
  </form>

  <form method="post" onsubmit="return confirm('Delete this service permanently? This can\'t be undone.');" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="delete">
    <button class="btn btn-danger btn-sm" type="submit">Delete this service</button>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
