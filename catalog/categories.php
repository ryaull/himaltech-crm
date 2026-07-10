<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $error = 'Category name is required.';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO service_categories (name, sort_order) VALUES (?,?)');
                $stmt->execute([$name, (int)($_POST['sort_order'] ?? 0)]);
                $notice = 'Category added.';
            } catch (PDOException $ex) {
                $error = $ex->getCode() === '23000' ? 'That category already exists.' : 'Could not add category.';
            }
        }
    }

    if ($action === 'delete') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $count = $pdo->prepare('SELECT COUNT(*) c FROM services WHERE category_id = ?');
        $count->execute([$catId]);
        if ($count->fetch()['c'] > 0) {
            $error = 'This category still has services in it — move or delete those first.';
        } else {
            $pdo->prepare('DELETE FROM service_categories WHERE id = ?')->execute([$catId]);
            $notice = 'Category deleted.';
        }
    }
}

$categories = $pdo->query(
    'SELECT sc.*, (SELECT COUNT(*) FROM services s WHERE s.category_id = sc.id) AS service_count
     FROM service_categories sc ORDER BY sort_order, name'
)->fetchAll();

$pageTitle = 'Service Categories';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div><h1>Service Categories</h1><p>Group services in the catalog (Website, SEO, Design, etc.)</p></div></div>

<?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <?php if (!$categories): ?>
    <p class="text-muted">No categories yet — add your first one below.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Name</th><th>Services</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($categories as $c): ?>
      <tr>
        <td><?= e($c['name']) ?></td>
        <td class="text-muted"><?= $c['service_count'] ?></td>
        <td>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this category?');">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="category_id" value="<?= $c['id'] ?>">
            <button class="btn btn-sm btn-secondary" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="card" style="max-width:420px">
  <h3 style="margin-top:0">Add a category</h3>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="add">
    <div class="field"><label>Name</label><input name="name" required placeholder="e.g. Website, SEO, Design"></div>
    <div class="field"><label>Sort order</label><input type="number" name="sort_order" value="0"></div>
    <button class="btn btn-sm" type="submit">Add category</button>
  </form>
</div>
<a class="btn btn-secondary" href="list.php">Back to catalog</a>
<?php include __DIR__ . '/../includes/footer.php'; ?>
