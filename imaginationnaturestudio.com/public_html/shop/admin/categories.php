<?php
// admin/categories.php
$adminTitle = 'Categories';
require_once __DIR__ . '/includes/admin-header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'add') {
        $name = trim((string)$_POST['name']);
        $desc = trim((string)($_POST['description'] ?? ''));
        if ($name) {
            $slug = slugify($name);
            $pdo->prepare('INSERT INTO categories (name,slug,description) VALUES (?,?,?)')->execute([$name,$slug,$desc]);
        }
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);
    }
    redirect('admin/categories.php');
}
$cats = $pdo->query('SELECT * FROM categories ORDER BY sort_order, name')->fetchAll();
?>
<h1>Categories</h1>
<form method="post" class="form-inline">
  <?= csrf_field() ?><input type="hidden" name="action" value="add">
  <input type="text" name="name" placeholder="Category name" required>
  <input type="text" name="description" placeholder="Short description">
  <button class="btn btn-gold" type="submit">Add Category</button>
</form>
<table class="admin-table">
  <thead><tr><th>ID</th><th>Name</th><th>Slug</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($cats as $c): ?>
    <tr><td><?= (int)$c['id'] ?></td><td><?= e($c['name']) ?></td><td><?= e($c['slug']) ?></td>
    <td>
      <form method="post" onsubmit="return confirm('Delete?')">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <button class="btn btn-small" type="submit">Delete</button>
      </form>
    </td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>