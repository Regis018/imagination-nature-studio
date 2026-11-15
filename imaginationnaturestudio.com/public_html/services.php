
<?php
/**
 * Public — Services page
 * Multilingual: English / Kinyarwanda / Français
 * Displays all active services, including Small Party.
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/language.php';

/*
|--------------------------------------------------------------------------
| LOAD ALL ACTIVE SERVICES
|--------------------------------------------------------------------------
| No service is excluded by name.
| Booking restrictions are handled separately.
|--------------------------------------------------------------------------
*/

$services = db()
    ->query("
        SELECT *
        FROM services
        WHERE status = 'active'
        ORDER BY name
    ")
    ->fetchAll();

/*
|--------------------------------------------------------------------------
| LOCALIZE SERVICES
|--------------------------------------------------------------------------
*/

$services = localize_services($services);

/*
|--------------------------------------------------------------------------
| PAGE SEO
|--------------------------------------------------------------------------
*/

$pageTitle = 'Services · ' . setting('site_name', SITE_NAME);

$pageDesc = t(
    'Wedding, portrait, maternity, family, event, editorial, and commercial photography services in Kigali. Book ' .
    setting('site_name', SITE_NAME) . '.'
);

/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

require __DIR__ . '/includes/header.php';
?>

<section class="container" style="padding:5rem 0">

  <div class="section-head">

    <span
      class="kicker"
      style="color:var(--accent-2);font-size:.72rem;letter-spacing:.3em;text-transform:uppercase;"
    >
      <?= e(t('Services')) ?>
    </span>

    <h1
      style="font-family:var(--font-serif);font-weight:500;font-size:clamp(2rem,4vw,3rem);margin:.6rem 0 1rem"
    >
      <?= e(t('Everything we offer.')) ?>
    </h1>

    <p>
      <?= e(t('From intimate portraits to grand weddings, our studio crafts imagery with intention.')) ?>
    </p>

  </div>

  <?php if (!$services): ?>

    <div class="card" style="padding:3rem;text-align:center;color:#7a716a">
      <?= e(t('No services available yet.')) ?>
    </div>

  <?php else: ?>

    <div class="grid grid-3">

      <?php foreach ($services as $s): ?>

        <?php
        $uploaded = (
            !empty($s['image']) &&
            is_file(UPLOAD_DIR . $s['image'])
        )
            ? SITE_URL . '/uploads/' . $s['image']
            : null;

        $static = SITE_URL . '/assets/images/services/' . $s['slug'] . '.jpg';
        $placeholder = SITE_URL . '/assets/images/placeholder.jpg';

        $imageSrc = $uploaded ?: $static;
        ?>

        <article class="card reveal">

          <div class="card-media">
            <img
              alt="<?= e($s['name']) ?>"
              loading="lazy"
              src="<?= e($imageSrc) ?>?v=<?= time() ?>"
              onerror="this.onerror=null;this.src='<?= e($placeholder) ?>';"
            >
          </div>

          <div class="card-body">

            <h3><?= e($s['name']) ?></h3>

            <?php if (!empty($s['description'])): ?>
              <p><?= e($s['description']) ?></p>
            <?php endif; ?>

            <div class="card-meta">

              <?php if ((float)$s['price'] > 0): ?>
                <span class="price">
                  <?= e(t('From')) ?> <?= e(rwf($s['price'])) ?>
                </span>
              <?php else: ?>
                <span class="price"><?= e(t('On request')) ?></span>
              <?php endif; ?>

              <a
                class="btn btn-sm btn-primary"
                href="<?= e(SITE_URL) ?>/booking.php?service=<?= (int)$s['id'] ?>"
              >
                <?= e(t('Book This Service')) ?>
              </a>

            </div>

            <?php if (!empty($s['duration'])): ?>
              <p style="color:#7a716a;font-size:.8rem;margin-top:.8rem">
                <?= e(t('Duration')) ?>: <?= e($s['duration']) ?>
              </p>
            <?php endif; ?>

          </div>

        </article>

      <?php endforeach; ?>

    </div>

  <?php endif; ?>

</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
