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
        'base_url' => 'https://imaginationnaturestudio.com/shop', // no trailing slash
        'env' => 'development', // 'development' or 'production'
        'session_name' => 'FRAMESESSID',
        'upload_max_bytes' => 5 * 1024 * 1024, // 5 MB
    ],
];