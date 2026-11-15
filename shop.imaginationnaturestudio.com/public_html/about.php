<?php
// about.php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/whatsapp.php';
$pageTitle = 'About Us — ' . (setting('store_name','') ?? '');
require __DIR__ . '/includes/header.php';
?>
<section class="page-head"><div class="container"><h1>About Us</h1></div></section>
<div class="container content prose">
  <p>Imagination Nature Studio designs and delivers premium framed football club crests, football legends, celebrity portraits and custom wall art across Rwanda.</p>
  <p>We combine quality craftsmanship with a love of football and pop culture, so your walls tell a story that's uniquely yours.</p>
  <p>Ordering is simple: choose a frame, add it to your cart, and confirm your order with us on WhatsApp.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>