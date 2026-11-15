<?php
require_once __DIR__ . '/includes/functions.php';
$sent = false; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? 'Website enquiry');
    $message = trim($_POST['message'] ?? '');

    if ($name && valid_email($email) && $message) {
        $stmt = db()->prepare("INSERT INTO messages (name,email,phone,subject,message) VALUES (?,?,?,?,?)");
        $stmt->execute([$name,$email,$phone,$subject,$message]);
        $sent = true;
        if (MAIL_ENABLED) {
            @mail(ADMIN_NOTIFY_EMAIL, "Website enquiry: $subject",
                "From: $name <$email>\nPhone: $phone\n\n$message", "From: ".MAIL_FROM);
        }
    } else { $err = 'Please enter your name, a valid email and a message.'; }
}
$pageTitle = 'Contact · Imagination Nature Studio';
require __DIR__ . '/includes/header.php';
?>
<section class="container" style="padding:5rem 0">
  <div class="section-head">
    <span class="kicker" style="color:var(--accent-2);font-size:.72rem;letter-spacing:.3em;text-transform:uppercase;">Contact</span>
    <h1 style="font-family:var(--font-serif);font-weight:500;font-size:clamp(2rem,4vw,3rem);margin:.6rem 0 1rem">Let's talk.</h1>
  </div>

  <?php if ($sent): ?><div class="alert alert-success">Thank you — your message has been received.</div><?php endif; ?>
  <?php if ($err):   ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>

  <div class="contact-grid">
    <div class="info-list">
      <ul>
        <li><div><strong>Studio</strong><?= e(BIZ_ADDRESS) ?></div></li>
        <li><div><strong>Phone</strong><a href="tel:<?= e(str_replace(' ','',BIZ_PHONE_1)) ?>"><?= e(BIZ_PHONE_1) ?></a><br>
                  <a href="tel:<?= e(str_replace(' ','',BIZ_PHONE_2)) ?>"><?= e(BIZ_PHONE_2) ?></a></div></li>
        <li><div><strong>Email</strong><a href="mailto:<?= e(BIZ_EMAIL) ?>"><?= e(BIZ_EMAIL) ?></a></div></li>
        <li><div><strong>Hours</strong><?= e(BIZ_HOURS) ?></div></li>
        <li><div><strong>Social</strong>
          <a href="<?= e(SOCIAL_INSTAGRAM) ?>" target="_blank" rel="noopener">Instagram</a> ·
          <a href="<?= e(SOCIAL_TIKTOK) ?>" target="_blank" rel="noopener">TikTok</a>
        </div></li>
      </ul>
      <div style="display:flex;gap:.8rem;flex-wrap:wrap;margin-top:1rem">
        <a class="btn btn-primary" href="<?= e(SITE_URL) ?>/booking.php">Book a Session</a>
        <a class="btn btn-ghost" href="https://wa.me/<?= e(BIZ_WHATSAPP) ?>" target="_blank" rel="noopener">WhatsApp us</a>
      </div>
    </div>

    <form method="post">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div><label>Name *</label><input type="text" name="name" required maxlength="150"></div>
        <div><label>Email *</label><input type="email" name="email" required maxlength="190"></div>
        <div><label>Phone</label><input type="tel" name="phone" maxlength="30"></div>
        <div><label>Subject</label><input type="text" name="subject" maxlength="190"></div>
        <div class="full"><label>Message *</label><textarea name="message" required maxlength="3000"></textarea></div>
      </div>
      <button class="btn btn-primary btn-block" style="margin-top:1.2rem">Send message</button>
    </form>
  </div>

  <div class="map-frame" style="margin-top:3rem">
    <iframe loading="lazy" referrerpolicy="no-referrer-when-downgrade"
      src="https://www.google.com/maps?q=Kiyovu%2C%20Kigali%2C%20Rwanda&output=embed"></iframe>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>