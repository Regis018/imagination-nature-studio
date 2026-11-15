<?php
require_once __DIR__ . '/includes/functions.php';
$categories = ['weddings','portraits','editorial','maternity','family','events','commercial'];
$gallery = db()->query("SELECT * FROM gallery ORDER BY id DESC")->fetchAll();
$pageTitle = 'Portfolio · Imagination Nature Studio';
require __DIR__ . '/includes/header.php';
?>
<section class="container" style="padding:5rem 0">
  <div class="section-head">
    <span class="kicker" style="color:var(--accent-2);font-size:.72rem;letter-spacing:.3em;text-transform:uppercase;">Portfolio</span>
    <h1 style="font-family:var(--font-serif);font-weight:500;font-size:clamp(2rem,4vw,3rem);margin:.6rem 0 1rem">Selected stories.</h1>
  </div>

  <div class="filter-bar">
    <button class="active" data-filter="all">All</button>
    <?php foreach ($categories as $c): ?>
      <button data-filter="<?= e($c) ?>"><?= e(ucfirst($c)) ?></button>
    <?php endforeach; ?>
  </div>

  <div class="masonry">
    <?php foreach ($gallery as $g): ?>
      <div class="tile" data-category="<?= e($g['category']) ?>"
           data-full="<?= e(SITE_URL.'/uploads/'.$g['image']) ?>">
        <img loading="lazy" alt="<?= e($g['title'] ?: 'Portfolio image') ?>"
             src="<?= e(SITE_URL.'/uploads/'.$g['image']) ?>">
      </div>
    <?php endforeach; ?>
  </div>
</section>

<div class="lightbox" id="lightbox">
  <button class="lightbox-close" aria-label="Close">&times;</button>
  <img src="" alt="">
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>