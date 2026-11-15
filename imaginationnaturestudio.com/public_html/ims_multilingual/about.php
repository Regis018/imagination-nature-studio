<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'About · Imagination Nature Studio';
require __DIR__ . '/includes/header.php';
?>
<section class="container" style="padding:5rem 0">
  <div class="split">
    <div>
      <span class="kicker" style="color:var(--accent-2);font-size:.72rem;letter-spacing:.3em;text-transform:uppercase;">About us</span>
      <h1 style="font-family:var(--font-serif);font-weight:500;font-size:clamp(2rem,4vw,3rem);margin:.8rem 0 1.4rem">Photography with intent, in the heart of Kigali.</h1>
      <p style="color:#b9b1a8">Imagination Nature Studio is a Kigali-based photography and creative studio founded by Tuyikunde Regis. We craft images with intention—elegant, honest, and lasting.</p>
      <p style="color:#b9b1a8">From weddings in Kiyovu to editorial work across Rwanda, our practice is rooted in story, craft and genuine care for the people in front of our lens.</p>
      <a class="btn btn-primary" href="<?= e(SITE_URL) ?>/booking.php">Book a Session</a>
    </div>
    <img src="<?= e(SITE_URL) ?>/assets/images/about.jpg" alt="Studio in Kigali">
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>