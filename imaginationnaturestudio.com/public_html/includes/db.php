<?php
/**
 * PDO Database connection (singleton).
 * In development mode, real error messages are shown.
 * In production mode, they are hidden and logged.
 */
require_once __DIR__ . '/../config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        $msg = 'DB connection failed: ' . $e->getMessage();
        @error_log($msg . PHP_EOL, 3, ERROR_LOG_FILE);

        if (ENVIRONMENT === 'development') {
            // Show real error during development
            http_response_code(500);
            echo '<pre style="background:#111;color:#f88;padding:2rem;font:14px/1.5 monospace;border-radius:8px">';
            echo "❌ DATABASE CONNECTION ERROR\n\n";
            echo "Host:  " . DB_HOST . "\n";
            echo "DB:    " . DB_NAME . "\n";
            echo "User:  " . DB_USER . "\n\n";
            echo "Message:\n" . htmlspecialchars($e->getMessage()) . "\n\n";
            echo "👉 Check that:\n";
            echo "   1. The database '" . DB_NAME . "' exists\n";
            echo "   2. The user '" . DB_USER . "' exists and has ALL PRIVILEGES\n";
            echo "   3. The host '" . DB_HOST . "' is correct (not always 'localhost')\n";
            echo "   4. The password in config.php is correct\n";
            echo "   5. pdo_mysql PHP extension is enabled\n";
            echo '</pre>';
            exit;
        }

        http_response_code(500);
        exit('Service temporarily unavailable. Please try again later.');
    }

    return $pdo;
}