<?php
// Copy this file to config/config.php and edit values.
return [
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'imagination_shop',
        'user' => 'imagination_shop',
        'pass' => 'JvRKgE9KHKWHTNjA7x3P',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        // Auto-detect local dev vs production
        'base_url' => (function () {
            $host    = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'shop.imaginationnaturestudio.com';
            $isLocal = in_array($host, ['localhost', '127.0.0.1'], true)
                    || str_starts_with($host, 'localhost:');
            return $isLocal
                ? 'http://' . $host
                : 'https://shop.imaginationnaturestudio.com';
        })(), // no trailing slash
        'env' => 'development', // 'development' or 'production'
        'session_name' => 'FRAMESESSID',
        'upload_max_bytes' => 5 * 1024 * 1024, // 5 MB
    ],
];