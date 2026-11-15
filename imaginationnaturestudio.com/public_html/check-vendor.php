<?php
header('Content-Type: text/plain');
$root = __DIR__;

echo "=== Vendor check ===\n\n";

$paths = [
    'vendor/autoload.php',
    'vendor/dompdf',
    'vendor/dompdf/src',
    'vendor/dompdf/src/Dompdf.php',
    'vendor/php-font-lib',
    'vendor/php-svg-lib',
    'composer.json',
    'composer.lock',
];

foreach ($paths as $p) {
    $full = $root . '/' . $p;
    $exists = file_exists($full);
    $type   = is_dir($full) ? 'dir' : (is_file($full) ? 'file' : '');
    echo str_pad($p, 40) . ($exists ? "✅ $type" : '❌ missing') . "\n";
}

echo "\n=== PHP capabilities ===\n";
echo 'PHP version:   ' . PHP_VERSION . "\n";
echo 'GD loaded:     ' . (extension_loaded('gd') ? '✅' : '❌') . "\n";
echo 'mbstring:      ' . (extension_loaded('mbstring') ? '✅' : '❌') . "\n";
echo 'dom (for XML): ' . (extension_loaded('dom') ? '✅' : '❌') . "\n";
echo 'zlib:          ' . (extension_loaded('zlib') ? '✅' : '❌') . "\n";

echo "\n=== Upload limits ===\n";
echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . "\n";
echo 'post_max_size:       ' . ini_get('post_max_size') . "\n";
echo 'memory_limit:        ' . ini_get('memory_limit') . "\n";

echo "\n=== Writable dirs ===\n";
foreach (['uploads', 'receipts', 'vendor'] as $d) {
    $p = $root . '/' . $d;
    echo str_pad($d, 15)
       . (!is_dir($p) ? '❌ missing' : (is_writable($p) ? '✅ writable' : '⚠️ not writable'))
       . "\n";
}