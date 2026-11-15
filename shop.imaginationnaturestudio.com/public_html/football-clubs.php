<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/whatsapp.php';

$catId = 1; // Football Clubs
$leagues = $pdo->query('SELECT * FROM leagues WHERE status = 1 ORDER BY sort_order, name')->fetchAll();
$leagueId = isset($_GET['league_id']) ? (int)$_GET['league_id'] : 0;
$sort = $_GET['sort'] ?? 'newest';

$products = fetch_products(['category_id' => $catId, 'league_id' => $leagueId ?: null, 'sort' => $sort]);

$pageTitle = 'Football Club Frames in Rwanda — ' . (setting('store_name','') ?? '');
$metaDescription = 'Shop framed football club logos from the English Premier League, La Liga, Serie A, Bundesliga and Ligue 1.';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head"><div class="container"><h1>Football Club Frames</h1><p>Framed club crests from the world's biggest leagues.</p></div></section>
<div class="container content">
  <aside class="filters">
    <h3>Filter by League</h3>
    <ul class="filter-list">
      <li><a class="<?= !$leagueId ? 'active':'' ?>" href="<?= e(url('football-clubs.php')) ?>">All Leagues</a></li>
      <?php foreach ($leagues as $lg): ?>
        <li><a class="<?= $leagueId===(int)$lg['id'] ? 'active':'' ?>" href="<?= e(url('football-clubs.php?league_id=' . (int)$lg['id'])) ?>"><?= e($lg['name']) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <h3>Sort</h3>
    <ul class="filter-list">
      <?php foreach (['newest'=>'Newest','price_asc'=>'Price: Low → High','price_desc'=>'Price: High → Low','name_asc'=>'Name A–Z'] as $k=>$label): ?>
        <li><a class="<?= $sort===$k?'active':'' ?>" href="<?= e(url('football-clubs.php?' . http_build_query(array_filter(['league_id'=>$leagueId ?: null,'sort'=>$k])))) ?>"><?= e($label) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </aside>
  <div class="product-area">
    <?php if (!$products): ?>
      <p class="muted">No products found in this selection.</p>
    <?php else: ?>
      <div class="product-grid">
        <?php foreach ($products as $product) require __DIR__ . '/includes/product-card.php'; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>