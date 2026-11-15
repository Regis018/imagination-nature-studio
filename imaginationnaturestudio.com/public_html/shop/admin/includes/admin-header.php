<?php
require_once __DIR__ . '/auth.php';
require_admin();
$adminTitle = $adminTitle ?? 'Dashboard';
$adminName = $_SESSION['admin_username'] ?? 'admin';
$storeName = setting('store_name','') ?? '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($adminTitle) ?> · Admin</title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="admin-body">
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="admin-brand"><?= e($storeName) ?></div>
    <nav>
      <a href="<?= e(url('admin/dashboard.php')) ?>">Dashboard</a>
      <a href="<?= e(url('admin/products.php')) ?>">Products</a>
      <a href="<?= e(url('admin/categories.php')) ?>">Categories</a>
      <a href="<?= e(url('admin/leagues.php')) ?>">Leagues</a>
      <a href="<?= e(url('admin/requests.php')) ?>">Custom Requests</a>
      <a href="<?= e(url('admin/settings.php')) ?>">Settings</a>
      <a href="<?= e(url('admin/logout.php')) ?>">Logout</a>
    </nav>
  </aside>
  <div class="admin-main">
    <div class="admin-topbar">
      <span>Signed in as <strong><?= e($adminName) ?></strong></span>
      <a class="btn btn-small" href="<?= e(url('index.php')) ?>" target="_blank">View Store</a>
    </div>
    <div class="admin-content">