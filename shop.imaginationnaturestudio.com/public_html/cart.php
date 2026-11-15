<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/whatsapp.php';

/**
 * IMAGINATION NATURE STUDIO — SHOP CART
 * Uses unique named SQL parameters for each product/size pair.
 */

$cart = $_SESSION['cart'] ?? [];

if (!is_array($cart)) {
    $cart = [];
}

$items = [];
$subtotal = 0.0;

/**
 * Load cart products safely.
 */
if (!empty($cart)) {
    $conditions = [];
    $params = [];
    $validCartItems = [];

    foreach ($cart as $cartKey => $cartItem) {
        if (!is_array($cartItem)) {
            continue;
        }

        if (
            !isset($cartItem['product_id'], $cartItem['size_id'])
        ) {
            continue;
        }

        $productId = filter_var(
            $cartItem['product_id'],
            FILTER_VALIDATE_INT
        );

        $sizeId = filter_var(
            $cartItem['size_id'],
            FILTER_VALIDATE_INT
        );

        if (
            $productId === false ||
            $sizeId === false ||
            $productId <= 0 ||
            $sizeId <= 0
        ) {
            continue;
        }

        // Every placeholder has a unique name.
        $index = count($validCartItems);

        $productParam = ':product_id_' . $index;
        $sizeParam = ':size_id_' . $index;

        $conditions[] =
            '(p.id = ' . $productParam .
            ' AND ps.id = ' . $sizeParam . ')';

        $params[$productParam] = $productId;
        $params[$sizeParam] = $sizeId;

        $validCartItems[] = [
            'cart_key' => (string)$cartKey,
            'product_id' => $productId,
            'size_id' => $sizeId,
            'qty' => max(
                1,
                min(20, (int)($cartItem['qty'] ?? 1))
            ),
        ];
    }

    if (!empty($conditions)) {
        $sql = "
            SELECT
                p.id AS pid,
                p.name,
                p.product_code,
                p.slug,
                p.main_image,
                ps.id AS sid,
                ps.size_name,
                ps.price
            FROM products AS p
            INNER JOIN product_sizes AS ps
                ON ps.product_id = p.id
            WHERE " . implode(' OR ', $conditions);

        $statement = $pdo->prepare($sql);
        $statement->execute($params);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        // Index database results by the exact product/size pair.
        $productsByPair = [];

        foreach ($rows as $row) {
            $pairKey =
                (int)$row['pid'] . ':' . (int)$row['sid'];

            $productsByPair[$pairKey] = $row;
        }

        foreach ($validCartItems as $cartItem) {
            $pairKey =
                $cartItem['product_id'] . ':' .
                $cartItem['size_id'];

            if (!isset($productsByPair[$pairKey])) {
                continue;
            }

            $product = $productsByPair[$pairKey];
            $quantity = $cartItem['qty'];

            $unitPrice = (float)$product['price'];
            $lineTotal = $unitPrice * $quantity;

            $subtotal += $lineTotal;

            $items[] = array_merge($product, [
                // Preserve the original cart key for cart-action.php.
                'key' => $cartItem['cart_key'],
                'qty' => $quantity,
                'line' => $lineTotal,
            ]);
        }
    }
}

$pageTitle = 'Your Cart — ' . (setting('store_name', '') ?? '');

require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
    <div class="container">
        <h1>Your Cart</h1>
    </div>
</section>

<div class="container content">

    <?php if (empty($items)): ?>

        <p class="muted">Your cart is empty.</p>

        <a
            class="btn btn-gold"
            href="<?= e(url('football-clubs.php')) ?>"
        >
            Continue Shopping
        </a>

    <?php else: ?>

        <form
            method="post"
            action="<?= e(url('cart-action.php')) ?>"
        >
            <?= csrf_field() ?>

            <input
                type="hidden"
                name="action"
                value="update"
            >

            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Size</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Line Total</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <img
                                    class="cart-thumb"
                                    src="<?= e(url(
                                        $item['main_image']
                                            ?: 'assets/images/placeholder.jpg'
                                    )) ?>"
                                    alt=""
                                >

                                <div>
                                    <strong>
                                        <?= e($item['name']) ?>
                                    </strong>

                                    <div class="muted small">
                                        <?= e($item['product_code'] ?? '') ?>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <?= e($item['size_name']) ?>
                            </td>

                            <td>
                                <input
                                    type="number"
                                    name="qty[<?= e($item['key']) ?>]"
                                    value="<?= (int)$item['qty'] ?>"
                                    min="1"
                                    max="20"
                                    style="width:70px"
                                >
                            </td>

                            <td>
                                <?= e(format_price($item['price'])) ?>
                            </td>

                            <td>
                                <?= e(format_price($item['line'])) ?>
                            </td>

                            <td>
                                <button
                                    type="submit"
                                    formaction="<?= e(url('cart-action.php')) ?>"
                                    formmethod="post"
                                    name="action"
                                    value="remove"
                                    class="btn btn-small"
                                    onclick="this.form.querySelector('[name=key]').value = this.dataset.cartkey;"
                                    data-cartkey="<?= e($item['key']) ?>"
                                >
                                    Remove
                                </button>

                                <input
                                    type="hidden"
                                    name="key"
                                    value=""
                                >
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="cart-actions">
                <button
                    type="submit"
                    class="btn btn-dark"
                >
                    Update Cart
                </button>

                <a
                    class="btn btn-outline"
                    href="<?= e(url('football-clubs.php')) ?>"
                >
                    Continue Shopping
                </a>
            </div>
        </form>

        <div class="cart-summary">
            <h3>
                Subtotal: <?= e(format_price($subtotal)) ?>
            </h3>

            <p class="muted small">
                Delivery charges confirmed via WhatsApp.
            </p>

            <a
                class="btn btn-wa btn-block"
                target="_blank"
                rel="noopener"
                href="<?= e(whatsapp_url(build_cart_whatsapp_message())) ?>"
            >
                Send Order via WhatsApp
            </a>
        </div>

    <?php endif; ?>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
