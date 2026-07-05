<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin', 'staff');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['company_name'] ?? '');
    if ($name === '') {
        $error = 'Company name is required.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO clients (company_name, website, phone, address, industry, status, source, notes, assigned_to)
             VALUES (?,?,?,?,?,?,?,?,?)'
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
            current_user()['id'],
        ]);
        $newId = $pdo->lastInsertId();
        log_activity($pdo, current_user()['id'], $newId, null, 'Client created.');
        header('Location: view.php?id=' . $newId);
        exit;
    }
}

$pageTitle = 'Add client';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div><h1>Add client</h1><p>Create a new company record.</p></div></div>

<div class="card" style="max-width:640px">
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="field"><label>Company name *</label><input name="company_name" required autofocus></div>
    <div class="field-row">
      <div class="field"><label>Website</label><input name="website" placeholder="https://"></div>
      <div class="field"><label>Phone</label><input name="phone"></div>
    </div>
    <div class="field"><label>Address</label><input name="address"></div>
    <div class="field-row">
      <div class="field"><label>Industry</label><input name="industry"></div>
      <div class="field"><label>Status</label>
        <select name="status">
          <option value="lead">Lead</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
    </div>
    <div class="field"><label>Source</label><input name="source" placeholder="e.g. referral, website enquiry, cold outreach"></div>
    <div class="field"><label>Notes</label><textarea name="notes"></textarea></div>
    <button class="btn" type="submit">Save client</button>
    <a class="btn btn-secondary" href="list.php">Cancel</a>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
