<?php
declare(strict_types=1);

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    $configFile = __DIR__ . '/../config/config.example.php';
}
$CONFIG = require $configFile;

// Error display
if (($CONFIG['app']['env'] ?? 'production') === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('log_errors', '1');
}

// Session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($CONFIG['app']['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// PDO connection
try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
        $CONFIG['db']['host'], $CONFIG['db']['name'], $CONFIG['db']['charset']);
    $pdo = new PDO($dsn, $CONFIG['db']['user'], $CONFIG['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('DB connection failed: ' . $e->getMessage());
    echo 'Service temporarily unavailable.';
    exit;
}