<?php
require_once __DIR__ . '/functions.php';

$siteName   = setting('site_name', SITE_NAME);
$address    = setting('address', BIZ_ADDRESS);
$hours      = setting('opening_hours', BIZ_HOURS);
$phone1     = setting('phone_primary', BIZ_PHONE_1);
$phone2     = setting('phone_secondary', BIZ_PHONE_2);
$email      = setting('email_primary', BIZ_EMAIL);
$whatsapp   = setting('whatsapp', BIZ_WHATSAPP);
$instagram  = setting('instagram_url', SOCIAL_INSTAGRAM);
$tiktok     = setting('tiktok_url', SOCIAL_TIKTOK);
$facebook   = setting('facebook_url');
$youtube    = setting('youtube_url');
$footerText = setting('footer_text', '© ' . date('Y') . ' ' . SITE_NAME);

$jsV  = @filemtime(SITE_ROOT . '/assets/js/main.js') ?: time();
$jsV2 = @filemtime(SITE_ROOT . '/assets/js/animations.js') ?: time();
?>
</main>

<footer class="site-footer">
  <div class="container footer-grid">

    <div>
      <h4><?= e($siteName) ?></h4>
      <p><?= e($address) ?></p>
      <p><?= e($hours) ?></p>
    </div>

    <div>
      <h4>Contact</h4>
      <p><a href="tel:<?= e(str_replace(' ', '', $phone1)) ?>"><?= e($phone1) ?></a></p>

      <?php if ($phone2): ?>
        <p><a href="tel:<?= e(str_replace(' ', '', $phone2)) ?>"><?= e($phone2) ?></a></p>
      <?php endif; ?>

      <p><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p>
    </div>

    <div>
      <h4>Explore</h4>
      <p><a href="<?= e(SITE_URL) ?>/services.php">Services</a></p>
      <p><a href="<?= e(SITE_URL) ?>/portfolio.php">Portfolio</a></p>
      <p><a href="<?= e(SITE_URL) ?>/booking.php">Book a Session</a></p>
    </div>

    <div>
      <h4>Follow</h4>

      <?php if ($instagram): ?>
        <p><a href="<?= e($instagram) ?>" target="_blank" rel="noopener">Instagram</a></p>
      <?php endif; ?>

      <?php if ($tiktok): ?>
        <p><a href="<?= e($tiktok) ?>" target="_blank" rel="noopener">TikTok</a></p>
      <?php endif; ?>

      <?php if ($facebook): ?>
        <p><a href="<?= e($facebook) ?>" target="_blank" rel="noopener">Facebook</a></p>
      <?php endif; ?>

      <?php if ($youtube): ?>
        <p><a href="<?= e($youtube) ?>" target="_blank" rel="noopener">YouTube</a></p>
      <?php endif; ?>
    </div>

  </div>

  <div class="footer-bottom">
    <div class="container">
      <span><?= e($footerText) ?></span>
      <span>Kigali · Rwanda</span>
    </div>
  </div>
</footer>


<!-- =========================
     FLOATING CALL BUTTON
     ========================= -->
<a class="call-float"
   href="tel:<?= e(str_replace([' ', '-', '(', ')'], '', $phone1)) ?>"
   aria-label="Call <?= e($siteName) ?>"
   title="Call <?= e($siteName) ?>">

   <svg viewBox="0 0 24 24"
        width="28"
        height="28"
        fill="currentColor"
        aria-hidden="true">
      <path d="M6.6 10.8c1.5 3 3.6 5.1 6.6 6.6l2.2-2.2c.3-.3.8-.4 1.2-.2
      .9.3 1.8.5 2.8.5.6 0 1.1.5 1.1 1.1v3.5c0 .6-.5 1.1-1.1 1.1
      C10.4 21.2 2.8 13.6 2.8 4.6 2.8 4 3.3 3.5 3.9 3.5h3.5
      c.6 0 1.1.5 1.1 1.1 0 1 .2 1.9.5 2.8.1.4 0 .9-.2 1.2l-2.2 2.2Z"/>
   </svg>
</a>


<!-- =========================
     FLOATING WHATSAPP BUTTON
     ========================= -->
<a class="whatsapp-float"
   href="https://wa.me/<?= e($whatsapp) ?>?text=<?= rawurlencode('Hello ' . $siteName . ', I would like to book a session.') ?>"
   target="_blank"
   rel="noopener"
   aria-label="WhatsApp"
   title="WhatsApp">

   <svg viewBox="0 0 24 24"
        width="28"
        height="28"
        fill="currentColor"
        aria-hidden="true">
      <path d="M20.5 3.5A11 11 0 0 0 3.1 17.7L2 22l4.4-1.1A11 11 0 1 0 20.5 3.5Zm-8.4 17a9 9 0 0 1-4.6-1.3l-.3-.2-2.6.7.7-2.5-.2-.3A9 9 0 1 1 12 20.5Zm5-6.7c-.3-.1-1.6-.8-1.9-.9-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a7.4 7.4 0 0 1-3.7-3.2c-.3-.5.3-.5.7-1.5.1-.2 0-.3 0-.5L9 8.4c-.2-.4-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5 5 0 0 0 1.1 2.6 11 11 0 0 0 4.3 3.8c.6.2 1.1.4 1.5.5.7.2 1.3.2 1.8.1.5-.1 1.6-.6 1.8-1.3.2-.6.2-1.2.2-1.3s-.2-.1-.5-.2Z"/>
   </svg>
</a>


<!-- =========================
     FLOATING BUTTONS STYLE
     ========================= -->
<style>

.call-float,
.whatsapp-float {
    position: fixed;
    right: 20px;
    width: 58px;
    height: 58px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    text-decoration: none;
    z-index: 99999;
    box-shadow: 0 6px 20px rgba(0,0,0,0.25);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.call-float:hover,
.whatsapp-float:hover {
    transform: scale(1.08);
    box-shadow: 0 8px 25px rgba(0,0,0,0.35);
}

/* CALL - iri hejuru */
.call-float {
    bottom: 92px;
    background: #111111;
}

/* WHATSAPP - iri hasi */
.whatsapp-float {
    bottom: 20px;
    background: #25D366;
}

/* Mobile */
@media (max-width: 600px) {

    .call-float,
    .whatsapp-float {
        right: 15px;
        width: 54px;
        height: 54px;
    }

    .call-float {
        bottom: 82px;
    }

    .whatsapp-float {
        bottom: 15px;
    }
}

</style>


<script src="<?= e(SITE_URL) ?>/assets/js/main.js?v=<?= e($jsV) ?>" defer></script>
<script src="<?= e(SITE_URL) ?>/assets/js/animations.js?v=<?= e($jsV2) ?>" defer></script>

</body>
<?php if (ob_get_level() > 0) { @ob_end_flush(); } ?>
</html>