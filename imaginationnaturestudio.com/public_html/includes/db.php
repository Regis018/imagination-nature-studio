<?php
/**
 * Database connection — returns PDO (MySQL) or FirestoreDB depending on DB_DRIVER.
 *
 * Set  define('DB_DRIVER', 'firestore');  in config.php  to use Firebase.
 * Default is 'mysql' — existing local dev keeps working unchanged.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use IMS\Firebase\FirestoreDB;

/**
 * Returns a database handle.
 * When DB_DRIVER === 'firestore' returns FirestoreDB (PDO-compatible).
 * Otherwise returns a standard PDO (MySQL/MariaDB).
 */
function db(): PDO|FirestoreDB
{
    static $conn = null;
    if ($conn !== null) return $conn;

    $driver = defined('DB_DRIVER') ? DB_DRIVER : 'mysql';

    /* ── Firestore ─────────────────────────────────────────────────── */
    if ($driver === 'firestore') {
        try {
            $conn = FirestoreDB::getInstance();
        } catch (\Throwable $e) {
            error_log('[FirestoreDB] connection failed: ' . $e->getMessage());
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                http_response_code(500);
                echo '<pre style="background:#111;color:#f88;padding:2rem;font:14px/1.5 monospace">';
                echo "❌ FIREBASE CONNECTION ERROR\n\n" . htmlspecialchars($e->getMessage());
                echo "\n\n👉 Check that includes/firebase/serviceAccount.json exists or\n";
                echo "   FIREBASE_CREDENTIALS env var points to your service account JSON.\n";
                echo '</pre>';
                exit;
            }
            http_response_code(500);
            exit('Service temporarily unavailable.');
        }
        return $conn;
    }

    /* ── MySQL / MariaDB (default) ─────────────────────────────────── */
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
    ];

    try {
        $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        $msg = 'DB connection failed: ' . $e->getMessage();
        @error_log($msg . PHP_EOL, 3, ERROR_LOG_FILE);

        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            http_response_code(500);
            echo '<pre style="background:#111;color:#f88;padding:2rem;font:14px/1.5 monospace;border-radius:8px">';
            echo "❌ DATABASE CONNECTION ERROR\n\n";
            echo "Host:  " . DB_HOST . "\n";
            echo "DB:    " . DB_NAME . "\n";
            echo "User:  " . DB_USER . "\n\n";
            echo "Message:\n" . htmlspecialchars($e->getMessage()) . "\n\n";
            echo "👉 Check that:\n";
            echo "   1. The database '" . DB_NAME . "' exists\n";
            echo "   2. The user '" . DB_USER . "' has ALL PRIVILEGES\n";
            echo "   3. The host '" . DB_HOST . "' is correct\n";
            echo "   4. The password in config.php is correct\n";
            echo "   5. pdo_mysql PHP extension is enabled\n";
            echo '</pre>';
            exit;
        }

        http_response_code(500);
        exit('Service temporarily unavailable. Please try again later.');
    }

    return $conn;
}