<?php
// sitemap.php — served as /sitemap.xml via .htaccess or direct access
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/xml; charset=utf-8');
$static = ['','about.php','contact.php','privacy.php','football-clubs.php','football-legends.php','celebrities.php','custom-frames.php'];
$products = $pdo->query('SELECT slug, updated_at FROM products WHERE status = 1')->fetchAll();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($static as $p) echo '  <url><loc>' . e(url($p)) . '</loc></url>' . "\n";
foreach ($products as $p) echo '  <url><loc>' . e(url('product.php?slug=' . urlencode($p['slug']))) . '</loc><lastmod>' . e(substr($p['updated_at'],0,10)) . '</lastmod></url>' . "\n";
echo '</urlset>';