<?php
$adminTitle = 'Dashboard';
require_once __DIR__ . '/includes/admin-header.php';

$productCount = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$categoryCount = (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$leagueCount = (int)$pdo->query('SELECT COUNT(*) FROM leagues')->fetchColumn();
$requestCount = (int)$pdo->query("SELECT COUNT(*) FROM custom_requests WHERE status = 'new'")->fetchColumn();
?>
<h1>Dashboard</h1>
<div class="admin-cards">
  <div class="admin-card"><div class="num"><?= $productCount ?></div><div>Products</div></div>
  <div class="admin-card"><div class="num"><?= $categoryCount ?></div><div>Categories</div></div>
  <div class="admin-card"><div class="num"><?= $leagueCount ?></div><div>Leagues</div></div>
  <div class="admin-card"><div class="num"><?= $requestCount ?></div><div>New Custom Requests</div></div>
</div>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>