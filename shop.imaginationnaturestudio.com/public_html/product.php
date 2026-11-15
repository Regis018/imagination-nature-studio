<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/whatsapp.php';

$slug = $_GET['slug'] ?? '';
$st = $pdo->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug, l.name AS league_name
                     FROM products p JOIN categories c ON c.id = p.category_id
                     LEFT JOIN leagues l ON l.id = p.league_id
                     WHERE p.slug = ? AND p.status = 1 LIMIT 1');
$st->execute([$slug]);
$product = $st->fetch();
if (!$product) { http_response_code(404); $pageTitle='Product not found'; require __DIR__.'/includes/header.php'; echo '<div class="container content"><h1>Product not found</h1></div>'; require __DIR__.'/includes/footer.php'; exit; }

$sizes = get_product_sizes((int)$product['id']);
$gallery = $pdo->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC');
$gallery->execute([(int)$product['id']]);
$gallery = $gallery->fetchAll();

$price = get_effective_price($product);
$img = $product['main_image'] ?: 'assets/images/placeholder.jpg';

$pageTitle = $product['name'] . ' — ' . (setting('store_name','') ?? '');
$metaDescription = mb_substr(strip_tags((string)$product['description']), 0, 155);
$ogImage = url($img);
$canonical = url('product.php?slug=' . urlencode($product['slug']));

$ldJson = [
  '@context' => 'https://schema.org',
  '@type' => 'Product',
  'name' => $product['name'],
  'sku' => $product['product_code'],
  'description' => strip_tags((string)$product['description']),
  'image' => $ogImage,
  'brand' => ['@type'=>'Brand','name'=>setting('store_name','')],
  'offers' => [
    '@type' => 'Offer',
    'priceCurrency' => 'RWF',
    'price' => $price,
    'availability' => 'https://schema.org/InStock',
    'url' => $canonical,
  ],
];

require __DIR__ . '/includes/header.php';
?>
<section class="container product-detail">
  <div class="pd-media">
    <img id="pdMainImg" src="<?= e(url($img)) ?>" alt="<?= e($product['name']) ?>">
    <?php if ($gallery): ?>
      <div class="pd-thumbs">
        <button type="button" class="thumb active" data-src="<?= e(url($img)) ?>"><img src="<?= e(url($img)) ?>" alt=""></button>
        <?php foreach ($gallery as $g): ?>
          <button type="button" class="thumb" data-src="<?= e(url($g['image_path'])) ?>"><img src="<?= e(url($g['image_path'])) ?>" alt=""></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="pd-info">
    <?php if ($product['league_name']): ?><div class="pd-league"><?= e($product['league_name']) ?></div><?php endif; ?>
    <h1><?= e($product['name']) ?></h1>
    <div class="pd-code">Product Code: <?= e($product['product_code']) ?></div>
    <div class="pd-price" id="pdPrice"><?= e(format_price($price)) ?></div>
    <p class="pd-desc"><?= nl2br(e($product['description'] ?? '')) ?></p>

    <form method="post" action="<?= e(url('cart-action.php')) ?>" class="pd-form" id="pdForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">

      <label>Frame Size</label>
      <select name="size_id" id="sizeSelect" required>
        <?php foreach ($sizes as $s): ?>
          <option value="<?= (int)$s['id'] ?>" data-price="<?= (float)$s['price'] ?>" <?= $s['stock_status']==='out_of_stock'?'disabled':'' ?>>
            <?= e($s['size_name']) ?><?= $s['dimensions'] ? ' (' . e($s['dimensions']) . ')' : '' ?> — <?= e(format_price($s['price'])) ?><?= $s['stock_status']==='out_of_stock' ? ' — Sold out' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label>Quantity</label>
      <input type="number" name="qty" value="1" min="1" max="20" required>

      <button type="submit" class="btn btn-gold btn-block">Add to Cart</button>
    </form>

    <a class="btn btn-wa btn-block" id="waOrderBtn"
       href="<?= e(whatsapp_url('Hello! I am interested in ' . $product['name'] . ' (Code ' . $product['product_code'] . '). Please share more details.')) ?>"
       target="_blank" rel="noopener">Order this Frame on WhatsApp</a>

    <p class="muted small">Prices in RWF. Availability and delivery confirmed via WhatsApp.</p>
  </div>
</section>

<script type="application/ld+json"><?= json_encode($ldJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<script>
(function(){
  var sizes = document.getElementById('sizeSelect');
  var priceEl = document.getElementById('pdPrice');
  var waBtn = document.getElementById('waOrderBtn');
  var productName = <?= json_encode($product['name']) ?>;
  var productCode = <?= json_encode($product['product_code']) ?>;
  function updatePrice(){
    var opt = sizes.options[sizes.selectedIndex];
    if (!opt) return;
    var p = parseFloat(opt.getAttribute('data-price')) || 0;
    priceEl.textContent = new Intl.NumberFormat().format(p) + ' RWF';
    var size = opt.text.split(' — ')[0];
    var msg = 'Hello! I would like to order:\n\n' + productName + '\nCode: ' + productCode + '\nSize: ' + size + '\nQuantity: 1\n\nPlease confirm availability, delivery charges and payment.';
    waBtn.href = 'https://wa.me/<?= e(whatsapp_number()) ?>?text=' + encodeURIComponent(msg);
  }
  sizes.addEventListener('change', updatePrice);
  document.querySelectorAll('.thumb').forEach(function(b){
    b.addEventListener('click', function(){
      document.querySelectorAll('.thumb').forEach(t=>t.classList.remove('active'));
      b.classList.add('active');
      document.getElementById('pdMainImg').src = b.getAttribute('data-src');
    });
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>