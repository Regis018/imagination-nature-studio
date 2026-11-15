<?php
/**
 * PHP built-in server router for shop.imaginationnaturestudio.com
 * Usage: php -S localhost:8081 router.php  (from shop.imaginationnaturestudio.com dir)
 */

$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$root = __DIR__ . '/public_html';
$file = $root . $uri;

// Serve existing static files directly
if ($uri !== '/' && is_file($file)) {
    return false;
}

// Directory index → index.php
if (is_dir($file)) {
    $index = rtrim($file, '/') . '/index.php';
    if (is_file($index)) {
        require $index;
        return true;
    }
}

// Rewrite clean paths to .php
if (!str_ends_with($uri, '.php') && is_file($root . $uri . '.php')) {
    require $root . $uri . '.php';
    return true;
}

// Serve existing PHP files
if (is_file($file) && str_ends_with($file, '.php')) {
    require $file;
    return true;
}

// Fallback 404
http_response_code(404);
echo '<h1>404 Not Found</h1><p>' . htmlspecialchars($uri) . '</p>';
