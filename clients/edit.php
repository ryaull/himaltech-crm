<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin', 'staff');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) { http_response_code(404); die('Client not found.'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['company_name'] ?? '');
    if ($name === '') {
        $error = 'Company name is required.';
    } else {
        $stmt = $pdo->prepare(
            'UPDATE clients SET company_name=?, website=?, phone=?, address=?, industry=?, status=?, source=?, notes=? WHERE id=?'
        );
        $stmt->execute([
            $name,
            trim($_POST['website'] ?? '') ?: null,
            trim($_POST['phone'] ?? '') ?: null,
            trim($_POST['address'] ?? '') ?: null,
            trim($_POST['industry'] ?? '') ?: null,
            $_POST['status'] ?? 'lead',
            trim($_POST['source'] ?? '') ?: null,
            trim($_POST['notes'] ?? '') ?: null,
            $id,
        ]);
        header('Location: view.php?id=' . $id);
        exit;
    }
}

$pageTitle = 'Edit client';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div><h1>Edit client</h1></div></div>

<div class="card" style="max-width:640px">
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="field"><label>Company name *</label><input name="company_name" required value="<?= e($client['company_name']) ?>"></div>
    <div class="field-row">
      <div class="field"><label>Website</label><input name="website" value="<?= e($client['website'] ?? '') ?>"></div>
      <div class="field"><label>Phone</label><input name="phone" value="<?= e($client['phone'] ?? '') ?>"></div>
    </div>
    <div class="field"><label>Address</label><input name="address" value="<?= e($client['address'] ?? '') ?>"></div>
    <div class="field-row">
      <div class="field"><label>Industry</label><input name="industry" value="<?= e($client['industry'] ?? '') ?>"></div>
      <div class="field"><label>Status</label>
        <select name="status">
          <?php foreach (['lead','active','inactive'] as $s): ?>
            <option value="<?= $s ?>" <?= $client['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="field"><label>Source</label><input name="source" value="<?= e($client['source'] ?? '') ?>"></div>
    <div class="field"><label>Notes</label><textarea name="notes"><?= e($client['notes'] ?? '') ?></textarea></div>
    <button class="btn" type="submit">Save changes</button>
    <a class="btn btn-secondary" href="view.php?id=<?= $id ?>">Cancel</a>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
