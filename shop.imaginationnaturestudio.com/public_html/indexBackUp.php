<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/whatsapp.php';

$pageTitle = (setting('store_name', 'Frames Store') ?? 'Frames Store') . ' — Premium Football & Celebrity Frames in Rwanda';
$metaDescription = 'Premium football club frames, football legends, celebrity portraits and personalized wall art in Rwanda. Order on WhatsApp.';

$featured = fetch_products(['featured' => 1, 'limit' => 8]);
$epl = fetch_products(['league_id' => 1, 'limit' => 8]);
$laliga = fetch_products(['league_id' => 2, 'limit' => 4]);
$legends = fetch_products(['category_id' => 2, 'limit' => 8]);
$celebrities = fetch_products(['category_id' => 3, 'limit' => 8]);

$heroImage = setting('hero_image', 'assets/images/football-frames-hero.png') ?? 'assets/images/football-frames-hero.png';
$heroHeadline = setting('hero_headline', 'TURN YOUR PASSION INTO WALL ART') ?? '';
$heroSub = setting('hero_subtitle', '') ?? '';
$heroSupport = setting('hero_support', '') ?? '';

require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="hero-bg" style="background-image:url('<?= e(url($heroImage)) ?>')"></div>
  <div class="hero-overlay"></div>
  <div class="container hero-content">
    <p class="eyebrow">Imagination Nature Studio · Rwanda</p>
    <h1><?= e($heroHeadline) ?></h1>
    <p class="hero-sub"><?= e($heroSub) ?></p>
    <p class="hero-support"><?= e($heroSupport) ?></p>
    <div class="hero-buttons">
      <a class="btn btn-gold" href="<?= e(url('football-clubs.php')) ?>">Explore Football Frames</a>
      <a class="btn btn-outline" href="<?= e(url('celebrities.php')) ?>">Discover Celebrity Frames</a>
      <a class="btn btn-wa" href="<?= e(whatsapp_url('Hello! I would like to order a frame.')) ?>" target="_blank" rel="noopener">Order on WhatsApp</a>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <h2 class="section-title">Popular Categories</h2>
    <div class="cat-grid">
      <a class="cat-card" href="<?= e(url('football-clubs.php')) ?>"><span>Football Clubs</span></a>
      <a class="cat-card" href="<?= e(url('football-legends.php')) ?>"><span>Football Legends</span></a>
      <a class="cat-card" href="<?= e(url('celebrities.php')) ?>"><span>Celebrities</span></a>
      <a class="cat-card" href="<?= e(url('custom-frames.php')) ?>"><span>Custom Frames</span></a>
    </div>
  </div>
</section>

<?php if ($featured): ?>
<section class="section section-dark">
  <div class="container">
    <div class="section-head"><h2 class="section-title">Featured Frames</h2><a class="link-gold" href="<?= e(url('search.php?sort=newest')) ?>">View all →</a></div>
    <div class="product-grid">
      <?php foreach ($featured as $product) require __DIR__ . '/includes/product-card.php'; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($epl): ?>
<section class="section">
  <div class="container">
    <div class="section-head"><h2 class="section-title">English Premier League</h2><a class="link-gold" href="<?= e(url('league.php?slug=english-premier-league')) ?>">See all EPL →</a></div>
    <div class="product-grid">
      <?php foreach ($epl as $product) require __DIR__ . '/includes/product-card.php'; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($laliga): ?>
<section class="section section-dark">
  <div class="container">
    <div class="section-head"><h2 class="section-title">La Liga</h2><a class="link-gold" href="<?= e(url('league.php?slug=spanish-la-liga')) ?>">See all La Liga →</a></div>
    <div class="product-grid">
      <?php foreach ($laliga as $product) require __DIR__ . '/includes/product-card.php'; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container league-strip">
    <a class="league-chip" href="<?= e(url('league.php?slug=italian-serie-a')) ?>">Serie A</a>
    <a class="league-chip" href="<?= e(url('league.php?slug=german-bundesliga')) ?>">Bundesliga</a>
    <a class="league-chip" href="<?= e(url('league.php?slug=french-ligue-1')) ?>">Ligue 1</a>
    <a class="league-chip" href="<?= e(url('league.php?slug=african-international')) ?>">African & International</a>
  </div>
</section>

<?php if ($legends): ?>
<section class="section section-dark">
  <div class="container">
    <div class="section-head"><h2 class="section-title">Football Legends</h2><a class="link-gold" href="<?= e(url('football-legends.php')) ?>">See all →</a></div>
    <div class="product-grid">
      <?php foreach ($legends as $product) require __DIR__ . '/includes/product-card.php'; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($celebrities): ?>
<section class="section">
  <div class="container">
    <div class="section-head"><h2 class="section-title">Celebrity Frames</h2><a class="link-gold" href="<?= e(url('celebrities.php')) ?>">See all →</a></div>
    <div class="product-grid">
      <?php foreach ($celebrities as $product) require __DIR__ . '/includes/product-card.php'; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section custom-cta">
  <div class="container custom-cta-inner">
    <div>
      <h2>Want something personal?</h2>
      <p>Send us the subject you love — a club, a player, a family photo — and we will create a custom frame just for you.</p>
    </div>
    <a class="btn btn-gold" href="<?= e(url('custom-frames.php')) ?>">Request Custom Frame</a>
  </div>
</section>

<section class="section section-dark">
  <div class="container">
    <h2 class="section-title">Why Shop With Us</h2>
    <div class="why-grid">
      <div class="why-card"><h3>Premium Quality</h3><p>Frames built to last, crafted with care.</p></div>
      <div class="why-card"><h3>Fast Delivery</h3><p>Delivered across Kigali and Rwanda.</p></div>
      <div class="why-card"><h3>Easy Ordering</h3><p>Order directly via WhatsApp in seconds.</p></div>
      <div class="why-card"><h3>Custom Design</h3><p>Personalised frames for any space or occasion.</p></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <h2 class="section-title">How to Order</h2>
    <ol class="steps">
      <li><strong>Browse</strong> our football &amp; celebrity frames.</li>
      <li><strong>Select</strong> your preferred size and add to cart.</li>
      <li><strong>Send your order</strong> via WhatsApp for confirmation.</li>
      <li><strong>Receive</strong> your frame, delivered to your door.</li>
    </ol>
  </div>
</section>

<section class="section section-dark">
  <div class="container">
    <h2 class="section-title">Frequently Asked Questions</h2>
    <div class="faq">
      <details><summary>Do you deliver outside Kigali?</summary><p>Yes. Delivery charges are confirmed via WhatsApp before payment.</p></details>
      <details><summary>Can I order a custom frame?</summary><p>Yes — use our Custom Frames page and share the details with us on WhatsApp.</p></details>
      <details><summary>What sizes are available?</summary><p>Each product shows available sizes on its product page. Custom sizes can be requested.</p></details>
      <details><summary>How do I pay?</summary><p>Payment instructions are provided after your order is confirmed on WhatsApp.</p></details>
    </div>
  </div>
</section>

<section class="section cta-final">
  <div class="container cta-final-inner">
    <h2>Ready to order?</h2>
    <p>Chat with us on WhatsApp: <?= e(setting('whatsapp_display', '+250 788 577 033') ?? '') ?></p>
    <a class="btn btn-wa" href="<?= e(whatsapp_url('Hello! I would like to order frames.')) ?>" target="_blank" rel="noopener">Order on WhatsApp</a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>