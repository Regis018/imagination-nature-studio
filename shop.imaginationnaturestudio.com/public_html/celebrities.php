<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/whatsapp.php';

$sort = $_GET['sort'] ?? 'newest';
$products = fetch_products(['category_id' => 3, 'sort' => $sort]);

$pageTitle = 'Celebrity Frames — ' . (setting('store_name','') ?? '');
$metaDescription = 'Framed celebrity portraits: musicians, actors, athletes and inspirational personalities.';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head"><div class="container"><h1>Celebrity Frames</h1><p>Iconic personalities, immortalized in premium frames.</p></div></section>
<div class="container content">
  <div class="product-area" style="flex:1">
    <?php if (!$products): ?><p class="muted">No products yet.</p><?php else: ?>
      <div class="product-grid">
        <?php foreach ($products as $product) require __DIR__ . '/includes/product-card.php'; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>