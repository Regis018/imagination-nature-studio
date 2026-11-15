<?php
/** @var array $product  (expected keys: id, name, slug, product_code, base_price, sale_price, main_image, league_name) */
$price = get_effective_price($product);
$isSale = isset($product['sale_price']) && (float)$product['sale_price'] > 0 && (float)$product['sale_price'] < (float)$product['base_price'];
$img = $product['main_image'] ?: 'assets/images/placeholder.jpg';
?>
<article class="product-card">
  <a href="<?= e(url('product.php?slug=' . urlencode($product['slug']))) ?>" class="card-img">
    <img src="<?= e(url($img)) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
    <?php if (!empty($product['featured'])): ?><span class="badge badge-featured">Featured</span><?php endif; ?>
    <?php if ($isSale): ?><span class="badge badge-sale">Sale</span><?php endif; ?>
  </a>
  <div class="card-body">
    <?php if (!empty($product['league_name'])): ?><div class="card-league"><?= e($product['league_name']) ?></div><?php endif; ?>
    <h3 class="card-title"><a href="<?= e(url('product.php?slug=' . urlencode($product['slug']))) ?>"><?= e($product['name']) ?></a></h3>
    <div class="card-code"><?= e($product['product_code']) ?></div>
    <div class="card-price">
      <?php if ($isSale): ?><span class="price-old"><?= e(format_price($product['base_price'])) ?></span><?php endif; ?>
      <span class="price-now"><?= e(format_price($price)) ?></span>
    </div>
    <a class="btn btn-dark btn-block" href="<?= e(url('product.php?slug=' . urlencode($product['slug']))) ?>">View Frame</a>
  </div>
</article>