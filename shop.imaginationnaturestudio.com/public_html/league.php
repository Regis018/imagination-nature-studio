<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/whatsapp.php';

$slug = $_GET['slug'] ?? '';
$st = $pdo->prepare('SELECT * FROM leagues WHERE slug = ? AND status = 1 LIMIT 1');
$st->execute([$slug]);
$league = $st->fetch();
if (!$league) { http_response_code(404); $pageTitle = 'League not found'; require __DIR__ . '/includes/header.php'; echo '<div class="container content"><h1>League not found</h1></div>'; require __DIR__ . '/includes/footer.php'; exit; }

$sort = $_GET['sort'] ?? 'newest';
$products = fetch_products(['league_id' => (int)$league['id'], 'sort' => $sort]);

$pageTitle = $league['name'] . ' Frames — ' . (setting('store_name','') ?? '');
$metaDescription = ($league['description'] ?? $league['name']) . ' Framed club crests and portraits.';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head"><div class="container"><h1><?= e($league['name']) ?></h1><p><?= e($league['description'] ?? '') ?></p></div></section>
<div class="container content">
  <div class="product-area" style="flex:1">
    <?php if (!$products): ?>
      <p class="muted">No products yet in this league.</p>
    <?php else: ?>
      <div class="product-grid">
        <?php foreach ($products as $product) require __DIR__ . '/includes/product-card.php'; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>