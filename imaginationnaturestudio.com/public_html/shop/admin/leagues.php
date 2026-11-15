<?php
// admin/leagues.php — same pattern as categories
$adminTitle = 'Leagues';
require_once __DIR__ . '/includes/admin-header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'add') {
        $name = trim((string)$_POST['name']);
        $country = trim((string)($_POST['country'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        if ($name) {
            $slug = slugify($name);
            $pdo->prepare('INSERT INTO leagues (name,country,slug,description) VALUES (?,?,?,?)')->execute([$name,$country,$slug,$desc]);
        }
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare('DELETE FROM leagues WHERE id=?')->execute([$id]);
    }
    redirect('admin/leagues.php');
}
$ls = $pdo->query('SELECT * FROM leagues ORDER BY sort_order, name')->fetchAll();
?>
<h1>Leagues</h1>
<form method="post" class="form-inline">
  <?= csrf_field() ?><input type="hidden" name="action" value="add">
  <input type="text" name="name" placeholder="League name" required>
  <input type="text" name="country" placeholder="Country">
  <input type="text" name="description" placeholder="Description">
  <button class="btn btn-gold" type="submit">Add League</button>
</form>
<table class="admin-table">
  <thead><tr><th>ID</th><th>Name</th><th>Country</th><th>Slug</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($ls as $l): ?>
    <tr><td><?= (int)$l['id'] ?></td><td><?= e($l['name']) ?></td><td><?= e($l['country'] ?? '') ?></td><td><?= e($l['slug']) ?></td>
    <td>
      <form method="post" onsubmit="return confirm('Delete?')">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
        <button class="btn btn-small" type="submit">Delete</button>
      </form>
    </td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>