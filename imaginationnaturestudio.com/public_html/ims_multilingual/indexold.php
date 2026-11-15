<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = setting('site_name', SITE_NAME) . ' | Photography Studio in Kigali';
$pageDesc  = 'Creative wedding, portrait, maternity, family, event, editorial, and commercial photography in Kigali, Rwanda. Book ' . setting('site_name', SITE_NAME) . '.';
$canonical = SITE_URL . '/';

$hero       = home_section('hero');
$servicesS  = home_section('services');
$aboutS     = home_section('about');
$portfolioS = home_section('portfolio');
$ctaS       = home_section('cta');

$featuredServices = home_featured('service');
$featuredGallery  = home_featured('gallery');

$heroBg = SITE_URL . '/assets/images/hero.jpg';

if ($hero && !empty($hero['image'])) {
    $heroBg = SITE_URL . '/uploads/' . $hero['image'];
}

$aboutImg = SITE_URL . '/assets/images/about.jpg';

if ($aboutS && !empty($aboutS['image'])) {
    $aboutImg = SITE_URL . '/uploads/' . $aboutS['image'];
}

require __DIR__ . '/includes/header.php';
?>


<style>
/* =========================================================
   PREMIUM PUBLIC NAVIGATION
   Applies to Home / About / Services / Portfolio / Contact
   ========================================================= */

/* Navigation links */
.site-header nav a,
.site-nav a,
.nav-links a,
header nav a {
    position: relative;
    display: inline-flex;
    align-items: center;
    padding: .55rem .15rem;
    margin: 0 .45rem;

    color: inherit;
    font-weight: 800 !important;
    letter-spacing: .02em;
    text-decoration: none;

    transition:
        color .25s ease,
        transform .25s ease,
        text-shadow .25s ease;
}

/* Animated underline */
.site-header nav a::after,
.site-nav a::after,
.nav-links a::after,
header nav a::after {
    content: "";
    position: absolute;
    left: 50%;
    bottom: .12rem;

    width: 0;
    height: 2px;

    border-radius: 10px;

    background: currentColor;

    transform: translateX(-50%);
    transition: width .3s ease, box-shadow .3s ease;
}

/* Hover */
.site-header nav a:hover,
.site-nav a:hover,
.nav-links a:hover,
header nav a:hover {
    transform: translateY(-2px);
    color: #ffffff !important;
    text-shadow: 0 0 12px rgba(255,255,255,.28);
}

.site-header nav a:hover::after,
.site-nav a:hover::after,
.nav-links a:hover::after,
header nav a:hover::after {
    width: 100%;
    box-shadow: 0 0 10px rgba(255,255,255,.45);
}

/* Active/current link */
.site-header nav a.active,
.site-nav a.active,
.nav-links a.active,
header nav a.active {
    font-weight: 900 !important;
    color: #ffffff !important;
}

.site-header nav a.active::after,
.site-nav a.active::after,
.nav-links a.active::after,
header nav a.active::after {
    width: 100%;
    height: 3px;
    box-shadow: 0 0 12px rgba(255,255,255,.55);
}

/* Gentle entrance animation */
@keyframes publicNavIn {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.site-header nav a,
.site-nav a,
.nav-links a,
header nav a {
    animation: publicNavIn .5s ease both;
}

/* Stagger */
.site-header nav a:nth-child(1),
.site-nav a:nth-child(1),
.nav-links a:nth-child(1),
header nav a:nth-child(1) { animation-delay: .05s; }

.site-header nav a:nth-child(2),
.site-nav a:nth-child(2),
.nav-links a:nth-child(2),
header nav a:nth-child(2) { animation-delay: .10s; }

.site-header nav a:nth-child(3),
.site-nav a:nth-child(3),
.nav-links a:nth-child(3),
header nav a:nth-child(3) { animation-delay: .15s; }

.site-header nav a:nth-child(4),
.site-nav a:nth-child(4),
.nav-links a:nth-child(4),
header nav a:nth-child(4) { animation-delay: .20s; }

.site-header nav a:nth-child(5),
.site-nav a:nth-child(5),
.nav-links a:nth-child(5),
header nav a:nth-child(5) { animation-delay: .25s; }

/* Mobile */
@media (max-width: 800px) {
    .site-header nav a,
    .site-nav a,
    .nav-links a,
    header nav a {
        margin: .15rem 0;
        padding: .7rem .2rem;
        font-size: .95rem;
    }
}
</style>


<?php if ($hero): ?>

<!-- ================= HERO SLIDER ================= -->

<section class="hero hero-slider">

  <!-- HERO SLIDES -->
  <div class="hero-slides">

    <div class="hero-slide active"
         style="background-image:url('<?= e(SITE_URL) ?>/assets/images/hero.jpg');">
    </div>

    <div class="hero-slide"
         style="background-image:url('<?= e(SITE_URL) ?>/assets/images/hero1.jpg');">
    </div>

    <div class="hero-slide"
         style="background-image:url('<?= e(SITE_URL) ?>/assets/images/hero2.jpg');">
    </div>

    <div class="hero-slide"
         style="background-image:url('<?= e(SITE_URL) ?>/assets/images/hero3.jpg');">
    </div>

    <div class="hero-slide"
         style="background-image:url('<?= e(SITE_URL) ?>/assets/images/hero4.jpg');">
    </div>

    <div class="hero-slide"
         style="background-image:url('<?= e(SITE_URL) ?>/assets/images/hero5.jpg');">
    </div>

  </div>


  <!-- DARK OVERLAY -->
  <div class="hero-slider-overlay"></div>


  <!-- HERO CONTENT -->
  <div class="container hero-inner">

    <?php if (!empty($hero['subtitle'])): ?>

      <span class="hero-eyebrow">
        <?= e($hero['subtitle']) ?>
      </span>

    <?php endif; ?>


    <h1><?= $hero['title'] ?></h1>


    <?php if (!empty($hero['content'])): ?>

      <p>
        <?= e($hero['content']) ?>
      </p>

    <?php endif; ?>


    <div class="hero-cta">

      <?php if (!empty($hero['button_text'])): ?>

        <a class="btn btn-primary"
           href="<?= e($hero['button_link'] ?: '/booking.php') ?>">

          <?= e($hero['button_text']) ?>

        </a>

      <?php endif; ?>


      <?php if (!empty($hero['button_text_2'])): ?>

        <a class="btn btn-ghost"
           href="<?= e($hero['button_link_2'] ?: '/portfolio.php') ?>">

          <?= e($hero['button_text_2']) ?>

        </a>

      <?php endif; ?>

    </div>

  </div>


  <!-- SLIDER DOTS -->
  <div class="hero-slider-dots">

    <button class="hero-dot active"
            data-slide="0"
            aria-label="Slide 1">
    </button>

    <button class="hero-dot"
            data-slide="1"
            aria-label="Slide 2">
    </button>

    <button class="hero-dot"
            data-slide="2"
            aria-label="Slide 3">
    </button>

    <button class="hero-dot"
            data-slide="3"
            aria-label="Slide 4">
    </button>

    <button class="hero-dot"
            data-slide="4"
            aria-label="Slide 5">
    </button>

    <button class="hero-dot"
            data-slide="5"
            aria-label="Slide 6">
    </button>

  </div>

</section>


<!-- ================= HERO SLIDER STYLE ================= -->

<style>

.hero-slider {
    position: relative;
    overflow: hidden;
    isolation: isolate;
    background: #111;
}


.hero-slider .hero-slides {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    z-index: 0;
}


.hero-slider .hero-slide {
    position: absolute;
    inset: 0;

    width: 100%;
    height: 100%;

    background-size: cover;
    background-position: center center;
    background-repeat: no-repeat;

    opacity: 0;

    transform: scale(1.04);

    transition:
        opacity 1.2s ease-in-out,
        transform 6s ease-out;
}


.hero-slider .hero-slide.active {
    opacity: 1;
    transform: scale(1);
}


/* DARK OVERLAY */

.hero-slider .hero-slider-overlay {
    position: absolute;
    inset: 0;

    width: 100%;
    height: 100%;

    z-index: 1;

    background:
        linear-gradient(
            90deg,
            rgba(0,0,0,0.58) 0%,
            rgba(0,0,0,0.30) 50%,
            rgba(0,0,0,0.40) 100%
        );

    pointer-events: none;
}


/* HERO TEXT */

.hero-slider .hero-inner {
    position: relative;
    z-index: 2;
}


/* DOTS */

.hero-slider .hero-slider-dots {
    position: absolute;

    left: 50%;
    bottom: 30px;

    transform: translateX(-50%);

    display: flex;
    align-items: center;

    gap: 9px;

    z-index: 5;
}


.hero-slider .hero-dot {
    width: 9px;
    height: 9px;

    padding: 0;

    border: 1px solid rgba(255,255,255,0.85);

    border-radius: 50%;

    background: transparent;

    cursor: pointer;

    transition:
        width 0.3s ease,
        background 0.3s ease,
        border-radius 0.3s ease;
}


.hero-slider .hero-dot.active {
    width: 28px;

    border-radius: 20px;

    background: #ffffff;
}


/* MOBILE */

@media (max-width: 768px) {

    .hero-slider .hero-slide {
        background-position: center center;
    }


    .hero-slider .hero-slider-overlay {
        background:
            linear-gradient(
                180deg,
                rgba(0,0,0,0.32) 0%,
                rgba(0,0,0,0.58) 100%
            );
    }


    .hero-slider .hero-slider-dots {
        bottom: 20px;
    }

}

</style>


<!-- ================= HERO SLIDER JAVASCRIPT ================= -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const slides = document.querySelectorAll('.hero-slider .hero-slide');
    const dots = document.querySelectorAll('.hero-slider .hero-dot');

    if (!slides.length) {
        return;
    }


    let currentSlide = 0;
    let sliderTimer = null;


    function showSlide(index) {

        slides.forEach(function (slide, i) {

            if (i === index) {
                slide.classList.add('active');
            } else {
                slide.classList.remove('active');
            }

        });


        dots.forEach(function (dot, i) {

            if (i === index) {
                dot.classList.add('active');
            } else {
                dot.classList.remove('active');
            }

        });


        currentSlide = index;

    }


    function nextSlide() {

        let nextSlideIndex = currentSlide + 1;


        if (nextSlideIndex >= slides.length) {
            nextSlideIndex = 0;
        }


        showSlide(nextSlideIndex);

    }


    function startSlider() {

        if (sliderTimer) {
            clearInterval(sliderTimer);
        }


        sliderTimer = setInterval(function () {

            nextSlide();

        }, 5000);

    }


    dots.forEach(function (dot, index) {

        dot.addEventListener('click', function () {

            showSlide(index);

            startSlider();

        });

    });


    showSlide(0);

    startSlider();

});

</script>

<?php endif; ?>


<!-- ================= MARQUEE STRIP ================= -->

<div class="marquee" aria-hidden="true">

  <div class="marquee-track">

    <?php for ($i = 0; $i < 2; $i++): ?>

      <span class="marquee-item">Weddings</span>
      <span class="marquee-item">Portraits</span>
      <span class="marquee-item">Maternity</span>
      <span class="marquee-item">Family</span>
      <span class="marquee-item">Editorial</span>
      <span class="marquee-item">Events</span>
      <span class="marquee-item">Commercial</span>
      <span class="marquee-item">Kigali · Rwanda</span>

    <?php endfor; ?>

  </div>

</div>


<?php if ($servicesS && $featuredServices): ?>

<!-- ================= SERVICES PREVIEW ================= -->

<section>

  <div class="container">

    <div class="section-head reveal">

      <?php if (!empty($servicesS['subtitle'])): ?>

        <span class="kicker">
          <?= e($servicesS['subtitle']) ?>
        </span>

      <?php endif; ?>


      <h2><?= e($servicesS['title']) ?></h2>


      <?php if (!empty($servicesS['content'])): ?>

        <p>
          <?= e($servicesS['content']) ?>
        </p>

      <?php endif; ?>

    </div>


    <div class="grid grid-3 stagger">

      <?php foreach ($featuredServices as $s):

        $svcImg = !empty($s['image']) && is_file(UPLOAD_DIR . $s['image'])

            ? SITE_URL . '/uploads/' . $s['image']

            : SITE_URL . '/assets/images/services/' . $s['slug'] . '.jpg';

      ?>

        <article class="card">

          <div class="card-media">

            <img
              loading="lazy"
              alt="<?= e($s['name']) ?>"
              src="<?= e($svcImg) ?>?v=<?= time() ?>"
              onerror="this.onerror=null;this.src='<?= e(SITE_URL) ?>/assets/images/placeholder.jpg'">

          </div>


          <div class="card-body">

            <h3><?= e($s['name']) ?></h3>

            <p>
              <?= e($s['description']) ?>
            </p>


            <div class="card-meta">

              <?php if ((float)$s['price'] > 0): ?>

                <span class="price">
                  From <?= e(rwf($s['price'])) ?>
                </span>

              <?php endif; ?>


              <a
                class="btn btn-sm btn-light"
                href="<?= e(SITE_URL) ?>/booking.php?service=<?= (int)$s['id'] ?>">

                Book

              </a>

            </div>

          </div>

        </article>

      <?php endforeach; ?>

    </div>


    <div
      style="text-align:center;margin-top:2.5rem"
      class="reveal">

      <a
        class="btn btn-ghost"
        href="<?= e(SITE_URL) ?>/services.php">

        View all services

      </a>

    </div>

  </div>

</section>

<?php endif; ?>


<?php if ($aboutS): ?>

<!-- ================= ABOUT ================= -->

<section style="background:var(--bg-soft)">

  <div class="container split">

    <div class="reveal-left">

      <?php if (!empty($aboutS['subtitle'])): ?>

        <span
          class="kicker"
          style="color:var(--accent-2);font-size:.72rem;letter-spacing:.3em;text-transform:uppercase">

          <?= e($aboutS['subtitle']) ?>

        </span>

      <?php endif; ?>


      <h2
        style="font-family:var(--font-serif);font-weight:500;font-size:clamp(1.8rem,3.6vw,2.8rem);margin:.8rem 0 1rem">

        <?= e($aboutS['title']) ?>

      </h2>


      <?php if (!empty($aboutS['content'])): ?>

        <p style="color:#b9b1a8">

          <?= nl2br(e($aboutS['content'])) ?>

        </p>

      <?php endif; ?>


      <?php if (!empty($aboutS['button_text'])): ?>

        <a
          class="btn btn-primary"
          href="<?= e($aboutS['button_link'] ?: '/about.php') ?>"
          style="margin-top:1rem">

          <?= e($aboutS['button_text']) ?>

        </a>

      <?php endif; ?>

    </div>


    <div class="reveal-right">

      <img
        src="<?= e($aboutImg) ?>"
        alt="<?= e(setting('site_name', SITE_NAME)) ?>"
        onerror="this.onerror=null;this.src='<?= e(SITE_URL) ?>/assets/images/placeholder.jpg'">

    </div>

  </div>

</section>

<?php endif; ?>


<?php if ($portfolioS && $featuredGallery): ?>

<!-- ================= PORTFOLIO PREVIEW ================= -->

<section>

  <div class="container">

    <div class="section-head reveal">

      <?php if (!empty($portfolioS['subtitle'])): ?>

        <span class="kicker">
          <?= e($portfolioS['subtitle']) ?>
        </span>

      <?php endif; ?>


      <h2>
        <?= e($portfolioS['title']) ?>
      </h2>


      <?php if (!empty($portfolioS['content'])): ?>

        <p>
          <?= e($portfolioS['content']) ?>
        </p>

      <?php endif; ?>

    </div>


    <div class="masonry stagger">

      <?php foreach ($featuredGallery as $g): ?>

        <div
          class="tile"
          data-category="<?= e($g['category']) ?>"
          data-full="<?= e(SITE_URL . '/uploads/' . $g['image']) ?>">

          <img
            loading="lazy"
            alt="<?= e($g['title'] ?: 'Portfolio image') ?>"
            src="<?= e(SITE_URL . '/uploads/' . $g['image']) ?>">

        </div>

      <?php endforeach; ?>

    </div>


    <div
      style="text-align:center;margin-top:2.5rem"
      class="reveal">

      <a
        class="btn btn-ghost"
        href="<?= e(SITE_URL) ?>/portfolio.php">

        View full portfolio

      </a>

    </div>

  </div>

</section>

<?php endif; ?>


<?php if ($ctaS): ?>

<!-- ================= BOOKING CTA ================= -->

<section
  style="background:linear-gradient(180deg,var(--bg) 0%,#161210 100%)">

  <div
    class="container reveal-blur"
    style="text-align:center;max-width:760px">

    <?php if (!empty($ctaS['subtitle'])): ?>

      <span
        class="kicker"
        style="color:var(--accent-2);font-size:.72rem;letter-spacing:.3em;text-transform:uppercase">

        <?= e($ctaS['subtitle']) ?>

      </span>

    <?php endif; ?>


    <h2
      style="font-family:var(--font-serif);font-weight:500;font-size:clamp(2rem,4.2vw,3.2rem);margin:1rem 0 1.2rem">

      <?= e($ctaS['title']) ?>

    </h2>


    <?php if (!empty($ctaS['content'])): ?>

      <p
        style="color:#b9b1a8;margin-bottom:2rem">

        <?= e($ctaS['content']) ?>

      </p>

    <?php endif; ?>


    <?php if (!empty($ctaS['button_text'])): ?>

      <a
        class="btn btn-primary"
        href="<?= e($ctaS['button_link'] ?: '/booking.php') ?>">

        <?= e($ctaS['button_text']) ?>

      </a>

    <?php endif; ?>

  </div>

</section>

<?php endif; ?>


<!-- ================= LIGHTBOX ================= -->

<div class="lightbox" id="lightbox">

  <button
    class="lightbox-close"
    aria-label="Close">

    &times;

  </button>

  <img src="" alt="">

</div>


<?php require __DIR__ . '/includes/footer.php'; ?>