<?php
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/xml; charset=utf-8');
$urls = ['/', '/about.php','/services.php','/portfolio.php','/booking.php','/contact.php'];
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>
  <url>
    <loc><?= e(SITE_URL . $u) ?></loc>
    <changefreq>weekly</changefreq>
    <priority><?= $u === '/' ? '1.0' : '0.8' ?></priority>
  </url>
<?php endforeach; ?>
</urlset>