<?php
/**
 * Public — Services page
 * Loads every active service and displays the image uploaded from the admin.
 * Falls back to a static file or a placeholder if no image is set.
 */
require_once __DIR__ . '/includes/functions.php';

/*
 * Load all active services EXCEPT "Small Party".
 * TRIM + LOWER ensures it is removed even if the database
 * contains extra spaces or different capitalization.
 */
$services = db()
    ->query("
        SELECT *
        FROM services
        WHERE status='active'
          AND LOWER(TRIM(name)) <> 'small party'
        ORDER BY name
    ")
    ->fetchAll();

$pageTitle = 'Services · ' . setting('site_name', SITE_NAME);
$pageDesc  = 'Wedding, portrait, maternity, family, event, editorial, and commercial photography services in Kigali. Book ' . setting('site_name', SITE_NAME) . '.';

require __DIR__ . '/includes/header.php';
?>

<section class="container" style="padding:5rem 0">

  <div class="section-head">
    <span class="kicker" style="color:var(--accent-2);font-size:.72rem;letter-spacing:.3em;text-transform:uppercase;">
      Services
    </span>

    <h1 style="font-family:var(--font-serif);font-weight:500;font-size:clamp(2rem,4vw,3rem);margin:.6rem 0 1rem">
      Everything we offer.
    </h1>

    <p>From intimate portraits to grand weddings, our studio crafts imagery with intention.</p>
  </div>

  <?php if (!$services): ?>

    <div class="card" style="padding:3rem;text-align:center;color:#7a716a">
      No services available yet.
    </div>

  <?php else: ?>

  <div class="grid grid-3">

    <?php foreach ($services as $s): ?>

      <?php
        /* ---------- Decide which image to show ----------
           1. Uploaded image from admin (uploads/<image>)
           2. Static fallback  (assets/images/services/<slug>.jpg)
           3. Generic placeholder (assets/images/placeholder.jpg)
        ------------------------------------------------------ */

        $uploaded = !empty($s['image']) && is_file(UPLOAD_DIR . $s['image'])
            ? SITE_URL . '/uploads/' . $s['image']
            : null;

        $static  = SITE_URL . '/assets/images/services/' . $s['slug'] . '.jpg';

        $placeholder = SITE_URL . '/assets/images/placeholder.jpg';

        $imageSrc = $uploaded ?: $static;
      ?>

      <article class="card reveal">

        <div class="card-media">

          <img
            alt="<?= e($s['name']) ?>"
            loading="lazy"
            src="<?= e($imageSrc) ?>?v=<?= time() ?>"
            onerror="this.onerror=null;this.src='<?= e($placeholder) ?>'">

        </div>

        <div class="card-body">

          <h3><?= e($s['name']) ?></h3>

          <?php if (!empty($s['description'])): ?>
            <p><?= e($s['description']) ?></p>
          <?php endif; ?>

          <div class="card-meta">

            <?php if ((float)$s['price'] > 0): ?>

              <span class="price">
                From <?= e(rwf($s['price'])) ?>
              </span>

            <?php else: ?>

              <span class="price">
                On request
              </span>

            <?php endif; ?>

            <a
              class="btn btn-sm btn-primary"
              href="<?= e(SITE_URL) ?>/booking.php?service=<?= (int)$s['id'] ?>">
              Book This Service
            </a>

          </div>

          <?php if (!empty($s['duration'])): ?>

            <p style="color:#7a716a;font-size:.8rem;margin-top:.8rem">
              Duration: <?= e($s['duration']) ?>
            </p>

          <?php endif; ?>

        </div>

      </article>

    <?php endforeach; ?>

  </div>

  <?php endif; ?>

</section>

<?php require __DIR__ . '/includes/footer.php'; ?>