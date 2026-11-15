<?php
// contact.php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/whatsapp.php';
$pageTitle = 'Contact — ' . (setting('store_name','') ?? '');
require __DIR__ . '/includes/header.php';
?>
<section class="page-head"><div class="container"><h1>Contact</h1></div></section>
<div class="container content prose">
  <p><strong>WhatsApp:</strong> <a href="<?= e(whatsapp_url('Hello!')) ?>" target="_blank" rel="noopener"><?= e(setting('whatsapp_display','+250 788 577 033') ?? '') ?></a></p>
  <p><strong>Email:</strong> <?= e(setting('contact_email','') ?? '') ?></p>
  <p><strong>Address:</strong> <?= e(setting('contact_address','Kigali, Rwanda') ?? '') ?></p>
  <p>For fastest service, message us on WhatsApp.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>