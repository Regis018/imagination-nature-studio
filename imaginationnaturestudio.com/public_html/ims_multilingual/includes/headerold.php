<?php
require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? (setting('site_name', SITE_NAME) . ' | Photography Studio in Kigali');
$pageDesc  = $pageDesc  ?? 'Creative wedding, portrait, maternity, family, event, editorial, and commercial photography in Kigali, Rwanda. Book Imagination Nature Studio.';
$canonical = $canonical ?? (SITE_URL . ($_SERVER['REQUEST_URI'] ?? '/'));

$logoUrl   = logo_url();
$favUrl    = ($f = setting('favicon')) ? SITE_URL . '/uploads/' . $f : SITE_URL . '/assets/images/favicon.png';
$ogUrl     = ($o = setting('og_image')) ? SITE_URL . '/uploads/' . $o : SITE_URL . '/assets/images/og-cover.jpg';
$primary   = setting('primary_color', '#e07a2f');
$accent    = setting('accent_color',  '#f2a35e');

$cssVersion = @filemtime(SITE_ROOT . '/assets/css/style.css') ?: time();
?>
<!doctype html>
<html lang="en">
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= e($pageTitle) ?></title>

<meta name="description" content="<?= e($pageDesc) ?>">

<link rel="canonical" href="<?= e($canonical) ?>">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDesc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($ogUrl) ?>">
<meta property="og:site_name" content="<?= e(setting('site_name', SITE_NAME)) ?>">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($pageTitle) ?>">
<meta name="twitter:description" content="<?= e($pageDesc) ?>">
<meta name="twitter:image" content="<?= e($ogUrl) ?>">

<!-- Favicon -->
<link rel="icon" href="<?= e($favUrl) ?>">

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link
    href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap"
    rel="stylesheet"
>

<!-- Main CSS -->
<link
    rel="stylesheet"
    href="<?= e(SITE_URL) ?>/assets/css/style.css?v=<?= e($cssVersion) ?>"
>

<style>
  :root {
    --accent: <?= e($primary) ?>;
    --accent-2: <?= e($accent) ?>;
  }
</style>


<!-- =========================================================
     GOOGLE ANALYTICS 4
     Imagination Nature Studio
     Measurement ID: G-SEMIE8BYTX
     ========================================================= -->

<script
    async
    src="https://www.googletagmanager.com/gtag/js?id=G-SEMIE8BYTX">
</script>

<script>
    window.dataLayer = window.dataLayer || [];

    function gtag() {
        dataLayer.push(arguments);
    }

    gtag('js', new Date());

    gtag('config', 'G-SEMIE8BYTX');
</script>

<!-- =========================================================
     END GOOGLE ANALYTICS 4
     ========================================================= -->


<!-- =========================================================
     WEBSITE STRUCTURED DATA
     ========================================================= -->

<script type="application/ld+json">
<?= json_encode([
  '@context'=>'https://schema.org',

  '@type'=>[
      'LocalBusiness',
      'PhotographyBusiness'
  ],

  'name'=>setting('site_name', SITE_NAME),

  'image'=>$ogUrl,

  '@id'=>SITE_URL,

  'url'=>SITE_URL,

  'telephone'=>setting('phone_primary', BIZ_PHONE_1),

  'email'=>setting('email_primary', BIZ_EMAIL),

  'priceRange'=>'RWF',

  'address'=>[
      '@type'=>'PostalAddress',
      'streetAddress'=>setting('address', BIZ_ADDRESS),
      'addressLocality'=>'Kigali',
      'addressCountry'=>'RW',
  ],

  'openingHoursSpecification'=>[[
      '@type'=>'OpeningHoursSpecification',

      'dayOfWeek'=>[
          'Monday',
          'Tuesday',
          'Wednesday',
          'Thursday',
          'Friday',
          'Saturday'
      ],

      'opens'=>'09:00',
      'closes'=>'21:00'
  ]],

  'sameAs'=>array_values(array_filter([
      setting('instagram_url', SOCIAL_INSTAGRAM),
      setting('tiktok_url', SOCIAL_TIKTOK),
      setting('facebook_url'),
      setting('youtube_url'),
  ])),

], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) ?>
</script>

</head>

<body>


<!-- =========================================================
     PRELOADER
     ========================================================= -->

<div class="preloader" aria-hidden="true">

  <div>

    <div
        class="preloader-brand"
        aria-label="Imagination Nature Studio"
    >
      <span>I</span>
      <span>M</span>
      <span>A</span>
      <span>G</span>
      <span>I</span>
      <span>N</span>
      <span>A</span>
      <span>T</span>
      <span>I</span>
      <span>O</span>
      <span>N</span>
    </div>

    <div class="preloader-bar"></div>

  </div>

</div>


<!-- =========================================================
     WEBSITE HEADER
     ========================================================= -->

<header class="site-header" id="siteHeader">

  <div class="container nav-wrap">


    <!-- LOGO -->

    <a
        href="<?= e(SITE_URL) ?>/"
        class="brand"
    >

      <?php if ($logoUrl): ?>

        <img
            src="<?= e($logoUrl) ?>"
            alt="<?= e(setting('site_name', SITE_NAME)) ?>"
        >

      <?php else: ?>

        <span class="brand-mark">
          INS
        </span>

        <span class="brand-text">

          <strong>
            <?= e(setting('site_name', 'Imagination Nature')) ?>
          </strong>

          <em>
            Studio · Kigali
          </em>

        </span>

      <?php endif; ?>

    </a>


    <!-- MAIN NAVIGATION -->

    <nav
        class="main-nav"
        id="mainNav"
        aria-label="Main navigation"
    >

      <a href="<?= e(SITE_URL) ?>/">
        Home
      </a>

      <a href="<?= e(SITE_URL) ?>/about.php">
        About
      </a>

      <a href="<?= e(SITE_URL) ?>/services.php">
        Services
      </a>

      <a href="<?= e(SITE_URL) ?>/portfolio.php">
        Portfolio
      </a>

      <a href="<?= e(SITE_URL) ?>/contact.php">
        Contact
      </a>

      <a
          class="nav-mobile-only"
          href="<?= e(SITE_URL) ?>/booking.php"
      >
        Book a Session
      </a>

    </nav>


    <!-- RIGHT SIDE -->

    <div class="nav-right">

      <a
          class="btn btn-primary nav-desktop-cta"
          href="<?= e(SITE_URL) ?>/booking.php"
      >
        Book a Session
      </a>


      <!-- MOBILE MENU BUTTON -->

      <button
          class="nav-toggle"
          id="navToggle"
          type="button"
          aria-label="Toggle menu"
          aria-expanded="false"
          aria-controls="mainNav"
      >

        <span></span>
        <span></span>
        <span></span>

      </button>

    </div>

  </div>

</header>


<!-- =========================================================
     MAIN CONTENT STARTS HERE
     ========================================================= -->

<main>