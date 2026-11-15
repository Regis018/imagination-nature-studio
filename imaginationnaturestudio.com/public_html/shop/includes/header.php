<?php
declare(strict_types=1);
if (!isset($pdo)) require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/whatsapp.php';

$pageTitle = $pageTitle ?? (setting('store_name', 'Frames Store') ?? 'Frames Store');
$metaDescription = $metaDescription ?? 'Premium football club frames, football legends and celebrity wall art in Rwanda.';
$canonical = $canonical ?? url(ltrim($_SERVER['REQUEST_URI'] ?? '/', '/'));
$cartCount = get_cart_count();
$storeName = setting('store_name', 'Imagination Nature Studio') ?? 'Imagination Nature Studio';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($metaDescription) ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= e($canonical) ?>">
<?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<header class="site-header" id="siteHeader">
  <div class="container header-inner">
    <a class="brand" href="<?= e(url('index.php')) ?>">
      <span class="brand-mark">◈</span>
      <span class="brand-name"><?= e($storeName) ?></span>
    </a>
    <nav class="main-nav" id="mainNav">
      <a href="<?= e(url('football-clubs.php')) ?>">Football Clubs</a>
      <a href="<?= e(url('football-legends.php')) ?>">Legends</a>
      <a href="<?= e(url('celebrities.php')) ?>">Celebrities</a>
      <a href="<?= e(url('custom-frames.php')) ?>">Custom Frames</a>
      <a href="<?= e(url('about.php')) ?>">About</a>
      <a href="<?= e(url('contact.php')) ?>">Contact</a>
    </nav>
    <div class="header-actions">
      <form class="search-form" action="<?= e(url('search.php')) ?>" method="get">
        <input type="search" name="q" placeholder="Search frames…" aria-label="Search" value="<?= e($_GET['q'] ?? '') ?>">
        <button type="submit" aria-label="Search">⌕</button>
      </form>
      <a class="cart-link" href="<?= e(url('cart.php')) ?>">
        Cart <span class="cart-badge"><?= (int)$cartCount ?></span>
      </a>
      <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">☰</button>
    </div>
  </div>
</header>
<main>