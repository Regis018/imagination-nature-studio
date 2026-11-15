<?php
/**
 * TEMPORARY diagnostic — tests email sending.
 * DELETE THIS FILE after use.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

echo "=== Email Diagnostic ===\n\n";

echo "MAIL_ENABLED:   " . (MAIL_ENABLED ? 'true' : 'false') . "\n";
echo "MAIL_HOST:      " . MAIL_HOST . "\n";
echo "MAIL_PORT:      " . MAIL_PORT . "\n";
echo "MAIL_USER:      " . MAIL_USER . "\n";
echo "MAIL_SECURE:    " . MAIL_SECURE . "\n";
echo "MAIL_FROM:      " . MAIL_FROM . "\n";
echo "MAIL_FROM_NAME: " . MAIL_FROM_NAME . "\n";
echo "ADMIN_NOTIFY:   " . ADMIN_NOTIFY_EMAIL . "\n\n";

echo "PHP version:    " . PHP_VERSION . "\n";
echo "OpenSSL:        " . (extension_loaded('openssl') ? '✅' : '❌') . "\n";
echo "Sockets:        " . (extension_loaded('sockets') ? '✅' : '⚠️ (not required)') . "\n\n";

/* ---------- Test 1: Can we reach the SMTP server? ---------- */
echo "--- Test 1: TCP connect to " . MAIL_HOST . ":" . MAIL_PORT . " ---\n";
$start = microtime(true);
$fp = @stream_socket_client(
    'tcp://' . MAIL_HOST . ':' . MAIL_PORT,
    $errno, $errstr, 8
);
$elapsed = round(microtime(true) - $start, 2);

if (!$fp) {
    echo "❌ FAILED after {$elapsed}s\n";
    echo "   Error: {$errstr} ({$errno})\n\n";
    echo "👉 Likely causes:\n";
    echo "   • Your host blocks outbound connections on port " . MAIL_PORT . "\n";
    echo "   • The hostname is wrong\n";
    echo "   • The mail server is down\n\n";
} else {
    echo "✅ Connected in {$elapsed}s\n";
    $banner = fgets($fp, 512);
    echo "   Server greeting: " . trim($banner) . "\n\n";
    fclose($fp);
}

/* ---------- Test 2: Try the mailer ---------- */
echo "--- Test 2: Send test email ---\n";

$to = trim($_GET['to'] ?? ADMIN_NOTIFY_EMAIL);
echo "Sending to: {$to}\n\n";

if (!file_exists(SITE_ROOT . '/includes/mailer.php')) {
    echo "❌ includes/mailer.php NOT FOUND\n";
    echo "   You must upload includes/mailer.php first.\n";
    exit;
}

require_once SITE_ROOT . '/includes/mailer.php';

$html = '<h2>Test email from INS</h2>
         <p>If you received this, SMTP is working correctly.</p>
         <p>Time: ' . date('Y-m-d H:i:s') . '</p>';

$sent = send_mail($to, 'INS SMTP Test — ' . date('H:i:s'), $html);

echo "Result: " . ($sent ? "✅ SENT" : "❌ FAILED") . "\n\n";

/* ---------- Show recent mail log entries ---------- */
echo "--- Recent mail log entries ---\n";
$logFile = ERROR_LOG_FILE;
if (file_exists($logFile)) {
    $lines = file($logFile);
    $mailLines = array_filter($lines, function ($l) {
        return strpos($l, '[mail]') !== false;
    });
    $last = array_slice($mailLines, -20);
    if ($last) {
        foreach ($last as $l) echo $l;
    } else {
        echo "(no [mail] entries found in log)\n";
    }
} else {
    echo "(no error.log file yet)\n";
}

echo "\n=== Done ===\n";
echo "Delete this file after testing.\n";