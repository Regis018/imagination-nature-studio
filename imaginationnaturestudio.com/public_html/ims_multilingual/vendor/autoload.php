<?php
/**
 * Minimal autoloader for Dompdf (no Composer).
 * Loads Dompdf, PHP FontLib, and PHP SVG Lib classes on demand.
 */
spl_autoload_register(function ($class) {
    $base = __DIR__;

    $map = [
        'Dompdf\\'    => $base . '/dompdf/src/',
        'FontLib\\'   => $base . '/php-font-lib/src/',
        'Svg\\'       => $base . '/php-svg-lib/src/Svg/',
        'Sabberworm\\CSS\\' => $base . '/php-svg-lib/src/Sabberworm/CSS/',
    ];

    foreach ($map as $prefix => $dir) {
        if (strpos($class, $prefix) === 0) {
            $relative = substr($class, strlen($prefix));
            $file = $dir . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
                return;
            }
        }
    }
});