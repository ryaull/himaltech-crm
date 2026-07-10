<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$categories = $pdo->query('SELECT * FROM service_categories ORDER BY sort_order, name')->fetchAll();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
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
        $error = 'Per-unit pricing needs a unit label (e.g. "page", "1000 words").';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO services (category_id, name, description, price, price_type, unit_label, keywords, is_active, sort_order)
             VALUES (?,?,?,?,?,?,?,?,?)'
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
        ]);
        header('Location: list.php');
        exit;
    }
}

$pageTitle = 'Add Service';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div><h1>Add Service</h1><p>Add a new item to the catalog.</p></div></div>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card" style="max-width:560px">
  <?php if (!$categories): ?>
    <p>You need at least one category first. <a href="categories.php">Add a category</a>.</p>
  <?php else: ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="field"><label>Service name</label><input name="name" required placeholder="e.g. Booking System"></div>
    <div class="field">
      <label>Category</label>
      <select name="category_id" required>
        <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>Description (optional)</label><textarea name="description" placeholder="Shown to staff and, later, to clients"></textarea></div>
    <div class="field-row">
      <div class="field">
        <label>Pricing model</label>
        <select name="price_type" id="priceType" onchange="document.getElementById('unitField').style.display = this.value === 'per_unit' ? 'block' : 'none'; document.getElementById('priceField').style.display = this.value === 'custom' ? 'none' : 'block';">
          <option value="flat">Flat price</option>
          <option value="per_unit">Per unit (e.g. per page)</option>
          <option value="monthly">Monthly</option>
          <option value="custom">Custom quote (no fixed price)</option>
        </select>
      </div>
      <div class="field" id="priceField"><label>Price (£)</label><input type="number" step="0.01" min="0" name="price" value="0"></div>
    </div>
    <div class="field" id="unitField" style="display:none"><label>Unit label</label><input name="unit_label" placeholder="e.g. page, 1000 words, keyword"></div>
    <div class="field"><label>Keywords (optional)</label><input name="keywords" placeholder="comma-separated — powers the future quote-matching helper"></div>
    <div class="field"><label><input type="checkbox" name="is_active" checked style="width:auto;display:inline-block;margin-right:6px"> Active (visible for use in quotes)</label></div>
    <button class="btn" type="submit">Add service</button>
    <a class="btn btn-secondary" href="list.php">Cancel</a>
  </form>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
