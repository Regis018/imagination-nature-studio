<?php
require_once __DIR__ . '/../includes/db.php';

$newPassword = 'Shop#3031'; // <-- change this to your new password
$username    = 'admin';              // <-- your admin username

$hash = password_hash($newPassword, PASSWORD_DEFAULT);

$st = $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE username = ?');
$st->execute([$hash, $username]);

echo $st->rowCount() ? "Password updated for '$username'.\n" : "User '$username' not found.\n";
echo "New password: $newPassword\n";