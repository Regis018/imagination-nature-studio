<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo "<h2>1. Loading auth.php…</h2>\n";
require_once __DIR__ . '/includes/auth.php';
echo "<p>OK — auth loaded.</p>\n";

echo "<h2>2. PDO connection…</h2>\n";
echo "<p>PDO instance: " . get_class($pdo) . "</p>\n";

echo "<h2>3. is_admin_logged_in()…</h2>\n";
var_dump(is_admin_logged_in());

echo "<h2>4. csrf_token()…</h2>\n";
echo csrf_token();

echo "<h2>5. url() helper…</h2>\n";
echo url('assets/css/style.css') . "\n";

echo "<h2>6. Admin users table…</h2>\n";
$st = $pdo->query('SELECT id, username, LEFT(password_hash, 7) AS hash_prefix FROM admin_users');
print_r($st->fetchAll());

echo "<h2>Done.</h2>";