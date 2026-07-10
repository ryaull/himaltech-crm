<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin', 'staff');

$isAdmin = current_user()['role'] === 'admin';
$categories = $pdo->query('SELECT * FROM service_categories ORDER BY sort_order, name')->fetchAll();

$servicesByCategory = [];
$services = $pdo->query('SELECT * FROM services ORDER BY sort_order, name')->fetchAll();
foreach ($services as $s) {
    $servicesByCategory[$s['category_id']][] = $s;
}

$pageTitle = 'Service Catalog';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><h1>Service Catalog</h1><p>Every service Himal Tech offers, with pricing. This feeds quotes and (soon) the public quote form.</p></div>
  <?php if ($isAdmin): ?>
  <div style="display:flex;gap:8px">
    <a class="btn btn-secondary" href="categories.php">Manage categories</a>
    <a class="btn" href="add.php">+ Add service</a>
  </div>
  <?php endif; ?>
</div>

<?php if (!$categories): ?>
  <div class="card"><div class="empty-state"><h3>No categories yet</h3><p><?= $isAdmin ? '<a href="categories.php">Add a category</a> to get started.' : 'Ask an admin to set up the service catalog.' ?></p></div></div>
<?php else: ?>
  <?php foreach ($categories as $cat): ?>
    <div class="card">
      <h3 style="margin-top:0"><?= e($cat['name']) ?></h3>
      <?php if (empty($servicesByCategory[$cat['id']])): ?>
        <p class="text-muted" style="margin:0">No services in this category yet.</p>
      <?php else: ?>
      <table class="table">
        <thead><tr><th>Service</th><th>Price</th><th>Status</th><?php if ($isAdmin): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($servicesByCategory[$cat['id']] as $s): ?>
          <tr<?= $isAdmin ? " onclick=\"location.href='edit.php?id={$s['id']}'\" style=\"cursor:pointer\"" : '' ?>>
            <td>
              <?= e($s['name']) ?>
              <?php if ($s['description']): ?><br><span class="text-muted" style="font-size:12px"><?= e($s['description']) ?></span><?php endif; ?>
            </td>
            <td class="mono"><?= service_price_display($s) ?></td>
            <td><?= $s['is_active'] ? '<span class="badge badge-won">Active</span>' : '<span class="badge badge-lost">Hidden</span>' ?></td>
            <?php if ($isAdmin): ?><td><a href="edit.php?id=<?= $s['id'] ?>">Edit</a></td><?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
