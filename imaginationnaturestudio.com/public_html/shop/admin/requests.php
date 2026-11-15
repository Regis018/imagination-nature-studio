<?php
$adminTitle = 'Custom Requests';
require_once __DIR__ . '/includes/admin-header.php';

$rows = $pdo->query('SELECT * FROM custom_requests ORDER BY created_at DESC')->fetchAll();
?>
<h1>Custom Frame Requests</h1>
<table class="admin-table">
  <thead><tr><th>Date</th><th>Name</th><th>Phone</th><th>Subject</th><th>Qty</th><th>Status</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['created_at']) ?></td>
      <td><?= e($r['customer_name']) ?></td>
      <td><?= e($r['phone']) ?></td>
      <td><?= e($r['subject']) ?></td>
      <td><?= (int)$r['quantity'] ?></td>
      <td><?= e($r['status']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>