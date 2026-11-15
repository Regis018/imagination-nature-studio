<?php
declare(strict_types=1);

function whatsapp_number(): string {
    $n = setting('whatsapp_number', '250788577033') ?? '250788577033';
    return preg_replace('/\D+/', '', $n) ?: '250788577033';
}

function whatsapp_url(string $message = ''): string {
    $base = 'https://wa.me/' . whatsapp_number();
    return $message !== '' ? $base . '?text=' . rawurlencode($message) : $base;
}

/**
 * Build a WhatsApp order message from the current session cart.
 */
function build_cart_whatsapp_message(): string {
    global $pdo;
    $cart = $_SESSION['cart'] ?? [];
    if (empty($cart)) {
        return 'Hello! I would like to ask about your football and celebrity frames.';
    }

    $lines = ["Hello! I would like to order the following frames:", ""];
    $subtotal = 0.0;
    $i = 1;

    foreach ($cart as $item) {
        $st = $pdo->prepare('SELECT p.name, p.product_code, ps.size_name, ps.price
                             FROM products p
                             JOIN product_sizes ps ON ps.id = :sid AND ps.product_id = p.id
                             WHERE p.id = :pid AND p.status = 1 LIMIT 1');
        $st->execute([':sid' => (int)$item['size_id'], ':pid' => (int)$item['product_id']]);
        $row = $st->fetch();
        if (!$row) continue;

        $qty = max(1, (int)$item['qty']);
        $unit = (float)$row['price'];
        $line = $unit * $qty;
        $subtotal += $line;

        $lines[] = $i . '. ' . $row['name'];
        $lines[] = '   Code: ' . $row['product_code'];
        $lines[] = '   Size: ' . $row['size_name'];
        $lines[] = '   Quantity: ' . $qty;
        $lines[] = '   Unit price: ' . number_format($unit, 0) . ' RWF';
        $lines[] = '';
        $i++;
    }

    $lines[] = 'Subtotal: ' . number_format($subtotal, 0) . ' RWF';
    $lines[] = '';
    $lines[] = 'Please confirm availability, delivery charges, and payment instructions.';
    $lines[] = 'Thank you.';

    return implode("\n", $lines);
}