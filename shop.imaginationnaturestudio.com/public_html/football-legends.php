<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/whatsapp.php';

$sort = $_GET['sort'] ?? 'newest';
$products = fetch_products(['category_id' => 2, 'sort' => $sort]);

$pageTitle = 'Football Legends Frames — ' . (setting('store_name','') ?? '');
$metaDescription = 'Framed portraits of football legends: Messi, Ronaldo, Pelé, Maradona and more.';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head"><div class="container"><h1>Football Legends</h1><p>Celebrate the greatest players in football history.</p></div></section>
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