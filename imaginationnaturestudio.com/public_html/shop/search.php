<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/whatsapp.php';

$q = trim((string)($_GET['q'] ?? ''));
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$opts = ['q' => $q, 'sort' => $sort, 'limit' => $perPage, 'offset' => ($page-1)*$perPage];
$products = $q !== '' ? fetch_products($opts) : fetch_products(['sort'=>$sort,'limit'=>$perPage,'offset'=>($page-1)*$perPage]);
$total = $q !== '' ? count_products(['q'=>$q]) : count_products();
$pages = max(1, (int)ceil($total / $perPage));

$pageTitle = ($q !== '' ? 'Search: ' . $q : 'All Frames') . ' — ' . (setting('store_name','') ?? '');
require __DIR__ . '/includes/header.php';
?>
<section class="page-head"><div class="container"><h1><?= $q !== '' ? 'Search results for “' . e($q) . '”' : 'All Frames' ?></h1><p><?= (int)$total ?> product(s)</p></div></section>
<div class="container content">
  <div class="product-area" style="flex:1">
    <?php if (!$products): ?>
      <p class="muted">No products matched your search.</p>
    <?php else: ?>
      <div class="product-grid">
        <?php foreach ($products as $product) require __DIR__ . '/includes/product-card.php'; ?>
      </div>
      <?php if ($pages > 1): ?>
        <nav class="pagination">
          <?php for ($i=1; $i<=$pages; $i++): ?>
            <a class="<?= $i===$page?'active':'' ?>" href="<?= e(url('search.php?' . http_build_query(['q'=>$q,'sort'=>$sort,'page'=>$i]))) ?>"><?= $i ?></a>
          <?php endfor; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>