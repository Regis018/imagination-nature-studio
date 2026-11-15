<?php
$adminTitle = 'Products';
require_once __DIR__ . '/includes/admin-header.php';

$rows = $pdo->query('SELECT p.*, c.name AS category_name, l.name AS league_name
                     FROM products p JOIN categories c ON c.id=p.category_id
                     LEFT JOIN leagues l ON l.id=p.league_id
                     ORDER BY p.created_at DESC')->fetchAll();
?>
<div class="admin-page-head">
  <h1>Products</h1>
  <a class="btn btn-gold" href="<?= e(url('admin/product-edit.php')) ?>">+ New Product</a>
</div>
<table class="admin-table">
  <thead><tr><th>Code</th><th>Name</th><th>Category</th><th>League</th><th>Base</th><th>Featured</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['product_code']) ?></td>
      <td><?= e($r['name']) ?></td>
      <td><?= e($r['category_name']) ?></td>
      <td><?= e($r['league_name'] ?? '—') ?></td>
      <td><?= e(format_price($r['base_price'])) ?></td>
      <td><?= $r['featured'] ? '★' : '—' ?></td>
      <td><?= $r['status'] ? 'Active' : 'Hidden' ?></td>
      <td><a class="btn btn-small" href="<?= e(url('admin/product-edit.php?id=' . (int)$r['id'])) ?>">Edit</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>