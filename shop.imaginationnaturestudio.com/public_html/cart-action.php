<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/whatsapp.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('cart.php'); }
csrf_check();

$action = $_POST['action'] ?? '';
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

if ($action === 'add') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $sizeId = (int)($_POST['size_id'] ?? 0);
    $qty = max(1, min(20, (int)($_POST['qty'] ?? 1)));

    $st = $pdo->prepare('SELECT ps.* FROM product_sizes ps
                         JOIN products p ON p.id = ps.product_id
                         WHERE ps.id = ? AND ps.product_id = ? AND p.status = 1
                         AND ps.stock_status = "in_stock" LIMIT 1');
    $st->execute([$sizeId, $productId]);
    if (!$st->fetch()) { redirect('cart.php'); }

    $key = $productId . ':' . $sizeId;
    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['qty'] += $qty;
    } else {
        $_SESSION['cart'][$key] = ['product_id'=>$productId,'size_id'=>$sizeId,'qty'=>$qty];
    }
    redirect('cart.php');
}

if ($action === 'update') {
    foreach (($_POST['qty'] ?? []) as $key => $q) {
        if (isset($_SESSION['cart'][$key])) {
            $q = max(1, min(20, (int)$q));
            $_SESSION['cart'][$key]['qty'] = $q;
        }
    }
    redirect('cart.php');
}

if ($action === 'remove') {
    $key = $_POST['key'] ?? '';
    unset($_SESSION['cart'][$key]);
    redirect('cart.php');
}

if ($action === 'clear') {
    $_SESSION['cart'] = [];
    redirect('cart.php');
}

redirect('cart.php');