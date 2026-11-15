<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/whatsapp.php';

// Defensive: drop malformed cart entries so a bad session can't crash the page
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $k => $v) {
        if (!is_array($v)
            || !isset($v['product_id'], $v['size_id'], $v['qty'])
            || (int)$v['product_id'] <= 0
            || (int)$v['size_id'] <= 0
            || (int)$v['qty'] <= 0) {
            unset($_SESSION['cart'][$k]);
        }
    }
}

$cart = $_SESSION['cart'] ?? [];
$items = [];
$subtotal = 0.0;

if ($cart) {
    // One prepared statement, executed once per cart line.
    // Avoids dynamic IN(...) placeholder counts and duplicate named params.
    $stmt = $pdo->prepare(
        'SELECT p.id AS pid, p.name, p.product_code, p.slug, p.main_image,
                ps.id AS sid, ps.size_name, ps.price
         FROM product_sizes ps
         JOIN products p ON p.id = ps.product_id
         WHERE ps.id = :sid
           AND p.status = 1
         LIMIT 1'
    );

    foreach ($cart as $key => $it) {
        $stmt->execute([':sid' => (int)$it['size_id']]);
        $r = $stmt->fetch();
        if (!$r) continue;

        // Guard against tampered session: size must belong to the stored product
        if ((int)$r['pid'] !== (int)$it['product_id']) continue;

        $qty  = max(1, min(20, (int)$it['qty']));
        $line = (float)$r['price'] * $qty;
        $subtotal += $line;

        $items[] = $r + ['key' => $key, 'qty' => $qty, 'line' => $line];
    }
}

$pageTitle = 'Your Cart — ' . (setting('store_name','') ?? '');
require __DIR__ . '/includes/header.php';
?>
<section class="page-head"><div class="container"><h1>Your Cart</h1></div></section>
<div class="container content">
  <?php if (!$items): ?>
    <p class="muted">Your cart is empty.</p>
    <a class="btn btn-gold" href="<?= e(url('football-clubs.php')) ?>">Continue Shopping</a>
  <?php else: ?>
    <form method="post" action="<?= e(url('cart-action.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <table class="cart-table">
        <thead><tr><th>Item</th><th>Size</th><th>Qty</th><th>Unit Price</th><th>Line Total</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($items as $i): ?>
          <tr>
            <td>
              <img class="cart-thumb" src="<?= e(url($i['main_image'] ?: 'assets/images/placeholder.jpg')) ?>" alt="">
              <div>
                <strong><?= e($i['name']) ?></strong>
                <div class="muted small"><?= e($i['product_code']) ?></div>
              </div>
            </td>
            <td><?= e($i['size_name']) ?></td>
            <td><input type="number" name="qty[<?= e($i['key']) ?>]" value="<?= (int)$i['qty'] ?>" min="1" max="20" style="width:70px"></td>
            <td><?= e(format_price($i['price'])) ?></td>
            <td><?= e(format_price($i['line'])) ?></td>
            <td>
              <button type="submit" formaction="<?= e(url('cart-action.php')) ?>"
                      name="action" value="remove"
                      class="btn btn-small"
                      onclick="this.form.querySelector('input[name=key]').value='<?= e($i['key']) ?>'">Remove</button>
              <input type="hidden" name="key" value="">
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div class="cart-actions">
        <button type="submit" class="btn btn-dark">Update Cart</button>
        <a class="btn btn-outline" href="<?= e(url('football-clubs.php')) ?>">Continue Shopping</a>
      </div>
    </form>

    <div class="cart-summary">
      <h3>Subtotal: <?= e(format_price($subtotal)) ?></h3>
      <p class="muted small">Delivery charges confirmed via WhatsApp.</p>
      <a class="btn btn-wa btn-block" target="_blank" rel="noopener"
         href="<?= e(whatsapp_url(build_cart_whatsapp_message())) ?>">Send Order via WhatsApp</a>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>