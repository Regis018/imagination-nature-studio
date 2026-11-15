<?php
// privacy.php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Privacy Policy — ' . (setting('store_name','') ?? '');
require __DIR__ . '/includes/header.php';
?>
<section class="page-head"><div class="container"><h1>Privacy Policy</h1></div></section>
<div class="container content prose">
  <p>We collect the minimum information needed to fulfil your order: your name, phone number, and delivery details shared via WhatsApp or our custom request form.</p>
  <p>We do not sell or share your data with third parties. Uploaded images are used only to process your custom frame request.</p>
  <p>To request deletion of any personal data, contact us on WhatsApp.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>