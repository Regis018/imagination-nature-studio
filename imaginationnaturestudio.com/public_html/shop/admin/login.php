<?php
require_once __DIR__ . '/includes/auth.php';

if (is_admin_logged_in()) { redirect('admin/dashboard.php'); }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = trim((string)($_POST['username'] ?? ''));
    $p = (string)($_POST['password'] ?? '');

    $st = $pdo->prepare('SELECT * FROM admin_users WHERE username = ? LIMIT 1');
    $st->execute([$u]);
    $admin = $st->fetch();

    if ($admin && password_verify($p, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_user_id'] = (int)$admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        redirect('admin/dashboard.php');
    } else {
        $error = 'Invalid username or password.';
    }
}
$pageTitle = 'Admin Login';
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin Login</title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>"></head>
<body class="admin-login-body">
<form method="post" class="admin-login-card">
  <h1>Admin Login</h1>
  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <label>Username<input type="text" name="username" required autofocus></label>
  <label>Password<input type="password" name="password" required></label>
  <button class="btn btn-gold btn-block" type="submit">Sign In</button>
</form>
</body></html>