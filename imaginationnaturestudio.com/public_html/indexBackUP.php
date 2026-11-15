<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/language.php';

$pageTitle = setting('site_name', SITE_NAME) . ' | Photography Studio in Kigali';
$pageDesc  = __('seo_home_desc') . ' ' . setting('site_name', SITE_NAME) . '.';
$canonical = SITE_URL . '/';

$hero       = home_section('hero');
$servicesS  = home_section('services');
$aboutS     = home_section('about');
$portfolioS = home_section('portfolio');
$ctaS       = home_section('cta');

$hero       = localize_home_section($hero);
$servicesS  = localize_home_section($servicesS);
$aboutS     = localize_home_section($aboutS);
$portfolioS = localize_home_section($portfolioS);
$ctaS       = localize_home_section($ctaS);

$featuredServices = home_featured('service');
$featuredGallery  = home_featured('gallery');

$featuredServices = localize_services($featuredServices);

/* =========================================================
   ACTIVE MARKETING PROMOTIONS
   ========================================================= */
$activePromotions = [];

try {

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $promoStmt = db()->query("
        SELECT *
        FROM promotions
        WHERE status = 'active'
          AND (starts_at IS NULL OR starts_at <= NOW())
          AND (ends_at IS NULL OR ends_at >= NOW())
        ORDER BY created_at DESC, id DESC
    ");

    $activePromotions = localize_promotions($promoStmt->fetchAll(PDO::FETCH_ASSOC));

    foreach ($activePromotions as &$promotion) {

        $promotionId = (int)$promotion['id'];
        $promoSessionKey = 'promo_viewed_' . $promotionId;

        if (empty($_SESSION[$promoSessionKey])) {

            try {

                $updatePromoViews = db()->prepare("
                    UPDATE promotions
                    SET views = COALESCE(views, 0) + 1
                    WHERE id = ?
                ");

                $updatePromoViews->execute([$promotionId]);

                $promotion['views'] =
                    (int)($promotion['views'] ?? 0) + 1;

                $_SESSION[$promoSessionKey] = true;

            } catch (Throwable $viewError) {
                // Do not break homepage if view tracking fails.
            }
        }
    }

    unset($promotion);

} catch (Throwable $e) {

    $activePromotions = [];
}

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

/* =========================================================
   MOBILE EXPERIENCE — IMAGINATION NATURE STUDIO
   Safe mobile-first refinements; desktop layout unchanged.
   ========================================================= */

@media (max-width: 800px) {
    html, body {
        max-width: 100%;
        overflow-x: hidden;
    }

    .container {
        width: min(100% - 28px, 1180px);
        margin-left: auto;
        margin-right: auto;
    }

    .site-header {
        overflow: visible;
    }

    .site-header nav a,
    .site-nav a,
    .nav-links a,
    header nav a {
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .hero-slider {
        min-height: 78vh;
    }

    .hero-slider .hero-inner {
        padding-top: 4.5rem;
        padding-bottom: 5rem;
    }

    .hero-slider h1 {
        font-size: clamp(2rem, 10vw, 3.2rem);
        line-height: 1.05;
        max-width: 100%;
    }

    .hero-slider p {
        max-width: 100%;
        font-size: .95rem;
        line-height: 1.7;
    }

    .hero-cta {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: .7rem;
        width: 100%;
        max-width: 360px;
    }

    .hero-cta .btn {
        width: 100%;
        min-height: 48px;
        box-sizing: border-box;
        text-align: center;
    }

    .hero-slider .hero-slider-dots {
        bottom: 18px;
    }

    .btn {
        min-height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
    }

    .card-body {
        padding: 1.25rem;
    }

    .card-meta {
        gap: .7rem;
        flex-wrap: wrap;
    }

    .why-choose-us .section-head,
    .client-reviews .section-head {
        margin-bottom: 2.3rem;
    }

    .review-card {
        min-height: auto;
        padding: 1.45rem;
    }

    .reviews-cta {
        margin-top: 2.2rem;
    }

    .reviews-cta p {
        line-height: 1.65;
    }

    .google-review-actions {
        flex-direction: column;
        width: 100%;
    }

    .google-review-btn,
    .google-read-btn {
        width: 100%;
        max-width: 360px;
        box-sizing: border-box;
    }

    .masonry {
        width: 100%;
    }

    .masonry img,
    .tile img {
        max-width: 100%;
        height: auto;
    }

    .split {
        gap: 2rem;
    }

    .split img {
        width: 100%;
        height: auto;
        display: block;
    }

    .call-float,
    .whatsapp-float {
        width: 50px !important;
        height: 50px !important;
        min-width: 50px;
        min-height: 50px;
    }
}

@media (max-width: 480px) {
    .container {
        width: min(100% - 24px, 1180px);
    }

    .hero-slider {
        min-height: 76vh;
    }

    .hero-slider .hero-inner {
        padding-top: 3.8rem;
        padding-bottom: 4.5rem;
    }

    .hero-slider h1 {
        font-size: clamp(1.85rem, 11vw, 2.7rem);
    }

    .hero-slider p {
        font-size: .9rem;
    }

    .section-head h2,
    .why-choose-us .section-head h2,
    .client-reviews .section-head h2 {
        font-size: clamp(1.75rem, 8vw, 2.35rem);
    }

    .why-card {
        padding: 1.35rem;
    }

    .review-card {
        padding: 1.3rem;
        border-radius: 16px;
    }

    .google-review-btn {
        min-width: 0;
        padding: .8rem .85rem;
    }

    .google-review-btn strong {
        font-size: .86rem;
    }

    .google-review-btn small {
        font-size: .68rem;
    }

    .google-g-logo {
        width: 30px;
        height: 30px;
        flex-basis: 30px;
    }

    .call-float,
    .whatsapp-float {
        right: 14px !important;
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


<?php if (!empty($activePromotions)): ?>

<!-- =========================================================
     MARKETING PROMOTIONS CAROUSEL
     ========================================================= -->

<section class="home-promotions-carousel" id="marketing-promotions">
    <div class="container">
        <div class="promo-carousel">

            <div class="promo-slides">

                <?php foreach ($activePromotions as $index => $promotion): ?>

                    <?php
                    $promotionId = (int)$promotion['id'];

                    $promoImage = '';

                    if (!empty($promotion['image'])) {
                        $promoImage = preg_match(
                            '~^https?://~i',
                            (string)$promotion['image']
                        )
                            ? (string)$promotion['image']
                            : SITE_URL . '/uploads/' .
                              ltrim((string)$promotion['image'], '/');
                    }

                    $promoOld = $promotion['old_price'] !== null
                        ? (float)$promotion['old_price']
                        : null;

                    $promoNew = $promotion['new_price'] !== null
                        ? (float)$promotion['new_price']
                        : null;

                    $promoDiscount =
                        $promotion['discount_percent'] !== null
                            ? (float)$promotion['discount_percent']
                            : null;

                    $promoEndsAt =
                        !empty($promotion['ends_at'])
                            ? strtotime((string)$promotion['ends_at'])
                            : 0;

                    $promoButton =
                        trim(
                            (string)(
                                $promotion['button_link']
                                ?? '/booking.php'
                            )
                        ) ?: '/booking.php';

                    $promoButtonUrl =
                        preg_match('~^https?://~i', $promoButton)
                            ? $promoButton
                            : SITE_URL . '/' .
                              ltrim($promoButton, '/');

                    $promoWhatsapp = preg_replace(
                        '/\D+/',
                        '',
                        (string)(
                            $promotion['whatsapp_number']
                            ?? ''
                        )
                    );

                    $promoWhatsappUrl =
                        $promoWhatsapp !== ''
                            ? SITE_URL .
                              '/promotion-click.php?id=' .
                              $promotionId .
                              '&type=whatsapp'
                            : '';
                    ?>

                    <article
                        class="promo-slide <?= $index === 0 ? 'active' : '' ?>"
                        data-promo-slide="<?= $index ?>"
                        data-promotion-id="<?= $promotionId ?>"
                    >

                        <div class="promo-shell">

                            <?php if ($promoImage): ?>

                                <?php if ($promoWhatsappUrl): ?>

                                    <a
                                        class="promo-media promo-image-link"
                                        href="<?= e($promoWhatsappUrl) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        aria-label="Contact us about <?= e($promotion['title']) ?>"
                                    >
                                        <img
                                            src="<?= e($promoImage) ?>"
                                            alt="<?= e($promotion['title']) ?>"
                                            loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"
                                            onerror="this.parentElement.classList.add('promo-no-image');this.remove();"
                                        >

                                        <span class="promo-click-badge">
                                            💬 <?= e(__("tap_to_order")) ?>
                                        </span>
                                    </a>

                                <?php else: ?>

                                    <div class="promo-media">
                                        <img
                                            src="<?= e($promoImage) ?>"
                                            alt="<?= e($promotion['title']) ?>"
                                            loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"
                                        >
                                    </div>

                                <?php endif; ?>

                            <?php else: ?>

                                <div class="promo-media promo-no-image">
                                    <div class="promo-camera">📸</div>
                                </div>

                            <?php endif; ?>

                            <div class="promo-content">

                                <div class="promo-topline">

                                    <span class="promo-kicker">
                                        🔥 <?= e(__("special_offer")) ?>
                                    </span>

                                    <?php if (
                                        $promoDiscount !== null &&
                                        $promoDiscount > 0
                                    ): ?>

                                        <span class="promo-discount">
                                            <?= e(__('save')) ?>
                                            <?= e(
                                                rtrim(
                                                    rtrim(
                                                        number_format(
                                                            $promoDiscount,
                                                            2,
                                                            '.',
                                                            ''
                                                        ),
                                                        '0'
                                                    ),
                                                    '.'
                                                )
                                            ) ?>%
                                        </span>

                                    <?php endif; ?>

                                </div>

                                <?php if (!empty($promotion['subtitle'])): ?>
                                    <div class="promo-subtitle">
                                        <?= e($promotion['subtitle']) ?>
                                    </div>
                                <?php endif; ?>

                                <h2><?= e($promotion['title']) ?></h2>

                                <?php if (!empty($promotion['description'])): ?>
                                    <p class="promo-description">
                                        <?= nl2br(e($promotion['description'])) ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (
                                    $promoOld !== null ||
                                    $promoNew !== null
                                ): ?>

                                    <div class="promo-prices">

                                        <?php if (
                                            $promoOld !== null &&
                                            (
                                                $promoNew === null ||
                                                $promoOld > $promoNew
                                            )
                                        ): ?>

                                            <span class="promo-old">
                                                <?= e(rwf($promoOld)) ?>
                                            </span>

                                        <?php endif; ?>

                                        <?php if ($promoNew !== null): ?>
                                            <strong class="promo-new">
                                                <?= e(rwf($promoNew)) ?>
                                            </strong>
                                        <?php endif; ?>

                                    </div>

                                <?php endif; ?>

                                <?php if ($promoEndsAt): ?>

                                    <div
                                        class="promo-countdown"
                                        data-promo-countdown="<?= (int)$promoEndsAt ?>"
                                    >
                                        <span>
                                            <b data-days>0</b>
                                            <small><?= e(__('days')) ?></small>
                                        </span>

                                        <span>
                                            <b data-hours>0</b>
                                            <small><?= e(__('hours')) ?></small>
                                        </span>

                                        <span>
                                            <b data-minutes>0</b>
                                            <small><?= e(__('min')) ?></small>
                                        </span>

                                        <span>
                                            <b data-seconds>0</b>
                                            <small><?= e(__('sec')) ?></small>
                                        </span>
                                    </div>

                                <?php endif; ?>

                                <div class="promo-actions">

                                    <a
                                        class="btn btn-primary"
                                        href="<?= e($promoButtonUrl) ?>"
                                    >
                                        <?= e(
                                            $promotion['button_text']
                                            ?: __('book_now')
                                        ) ?> ↗
                                    </a>

                                    <?php if ($promoWhatsappUrl): ?>

                                        <a
                                            class="promo-whatsapp"
                                            href="<?= e($promoWhatsappUrl) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <span>💬</span>
                                            <?= e(__('whatsapp_us')) ?>
                                        </a>

                                    <?php endif; ?>

                                </div>

                                <div class="promo-note">
                                    <?= e(__('limited_offer')) ?>
                                </div>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

            <?php if (count($activePromotions) > 1): ?>

                <button
                    type="button"
                    class="promo-arrow promo-prev"
                    aria-label="<?= e(__("previous_promotion")) ?>"
                >‹</button>

                <button
                    type="button"
                    class="promo-arrow promo-next"
                    aria-label="<?= e(__("next_promotion")) ?>"
                >›</button>

                <div class="promo-dots">

                    <?php foreach ($activePromotions as $index => $promotion): ?>

                        <button
                            type="button"
                            class="promo-dot <?= $index === 0 ? 'active' : '' ?>"
                            data-promo-dot="<?= $index ?>"
                            aria-label="<?= e(__("promotion")) ?> <?= $index + 1 ?>"
                        ></button>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>
    </div>
</section>

<style>

.home-promotions-carousel{
    padding:2.8rem 0;
    background:var(--bg-soft);
    overflow:hidden;
}

.promo-carousel{
    position:relative;
    width:100%;
    overflow:hidden;
    border-radius:26px;
}

.promo-slides{
    position:relative;
    width:100%;
}

.promo-slide{
    display:none;
    width:100%;
}

.promo-slide.active{
    display:block;
    animation:promoFadeIn .7s ease;
}

@keyframes promoFadeIn{
    from{
        opacity:0;
        transform:scale(.985);
    }
    to{
        opacity:1;
        transform:scale(1);
    }
}

.promo-shell{
    position:relative;
    display:grid;
    grid-template-columns:minmax(280px,42%) minmax(0,1fr);
    min-height:390px;
    overflow:hidden;
    border:1px solid rgba(255,255,255,.1);
    border-radius:26px;
    background:linear-gradient(
        135deg,
        rgba(255,255,255,.055),
        rgba(255,255,255,.018)
    );
    box-shadow:0 25px 70px rgba(0,0,0,.25);
}

.promo-media{
    position:relative;
    min-height:390px;
    background:
        radial-gradient(
            circle at 30% 20%,
            rgba(255,255,255,.12),
            transparent 40%
        ),
        #161616;
    overflow:hidden;
}

.promo-media img{
    width:100%;
    height:100%;
    display:block;
    object-fit:cover;
    transition:transform .7s ease,filter .4s ease;
}

.promo-image-link{
    display:block;
    height:100%;
    text-decoration:none;
    cursor:pointer;
}

.promo-image-link:hover img{
    transform:scale(1.055);
    filter:brightness(1.06);
}

.promo-click-badge{
    position:absolute;
    left:18px;
    bottom:18px;
    padding:.55rem .85rem;
    border-radius:999px;
    background:rgba(0,0,0,.68);
    color:#fff;
    font-size:.72rem;
    font-weight:900;
    letter-spacing:.02em;
    backdrop-filter:blur(10px);
    border:1px solid rgba(255,255,255,.16);
    opacity:.92;
    transition:transform .25s ease,background .25s ease;
}

.promo-image-link:hover .promo-click-badge{
    transform:translateY(-3px);
    background:rgba(0,0,0,.82);
}

.promo-no-image{
    display:flex;
    align-items:center;
    justify-content:center;
}

.promo-camera{
    width:90px;
    height:90px;
    display:flex;
    align-items:center;
    justify-content:center;
    border:1px solid rgba(255,255,255,.12);
    border-radius:25px;
    background:rgba(255,255,255,.05);
    font-size:2.5rem;
    box-shadow:0 20px 50px rgba(0,0,0,.25);
}

.promo-content{
    display:flex;
    flex-direction:column;
    justify-content:center;
    padding:2.7rem 3rem;
    min-width:0;
}

.promo-topline{
    display:flex;
    align-items:center;
    gap:.7rem;
    flex-wrap:wrap;
    margin-bottom:1rem;
}

.promo-kicker{
    font-size:.72rem;
    letter-spacing:.14em;
    font-weight:900;
    color:#f0c76a;
}

.promo-discount{
    padding:.35rem .65rem;
    border-radius:99px;
    background:#173d29;
    color:#79f1ac;
    font-size:.7rem;
    font-weight:900;
}

.promo-subtitle{
    color:#aaa29a;
    font-size:.9rem;
    font-weight:800;
    letter-spacing:.04em;
    margin-bottom:.45rem;
}

.promo-content h2{
    margin:0;
    font-family:var(--font-serif);
    font-size:clamp(2rem,4vw,3.3rem);
    line-height:1.05;
    font-weight:500;
    letter-spacing:-.025em;
}

.promo-description{
    max-width:650px;
    margin:1rem 0 0;
    color:#aaa29a;
    line-height:1.75;
}

.promo-prices{
    display:flex;
    align-items:baseline;
    gap:.8rem;
    margin:1.25rem 0 0;
}

.promo-old{
    font-size:1rem;
    color:#777;
    text-decoration:line-through;
}

.promo-new{
    font-size:1.75rem;
    color:#fff;
}

.promo-countdown{
    display:flex;
    gap:.55rem;
    margin-top:1.2rem;
    flex-wrap:wrap;
}

.promo-countdown span{
    min-width:58px;
    padding:.55rem .7rem;
    border:1px solid rgba(255,255,255,.09);
    border-radius:12px;
    background:rgba(255,255,255,.035);
    text-align:center;
}

.promo-countdown b{
    display:block;
    font-size:1.05rem;
    color:#fff;
}

.promo-countdown small{
    display:block;
    margin-top:2px;
    color:#777;
    font-size:.62rem;
    text-transform:uppercase;
    letter-spacing:.08em;
}

.promo-actions{
    display:flex;
    align-items:center;
    gap:.7rem;
    flex-wrap:wrap;
    margin-top:1.45rem;
}

.promo-actions .btn{
    min-height:48px;
}

.promo-whatsapp{
    min-height:48px;
    box-sizing:border-box;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:.45rem;
    padding:.75rem 1rem;
    border:1px solid rgba(255,255,255,.12);
    border-radius:12px;
    color:#fff;
    text-decoration:none;
    font-weight:800;
    background:rgba(255,255,255,.045);
    transition:transform .25s ease,background .25s ease;
}

.promo-whatsapp:hover{
    transform:translateY(-2px);
    background:rgba(255,255,255,.09);
}

.promo-note{
    margin-top:1rem;
    color:#6f6f6f;
    font-size:.72rem;
}

.promo-arrow{
    position:absolute;
    top:50%;
    transform:translateY(-50%);
    z-index:20;
    width:48px;
    height:48px;
    border:1px solid rgba(255,255,255,.18);
    border-radius:50%;
    background:rgba(0,0,0,.48);
    color:#fff;
    font-size:2rem;
    line-height:1;
    display:flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    backdrop-filter:blur(8px);
    transition:transform .25s ease,background .25s ease;
}

.promo-arrow:hover{
    background:rgba(0,0,0,.75);
    transform:translateY(-50%) scale(1.08);
}

.promo-prev{
    left:18px;
}

.promo-next{
    right:18px;
}

.promo-dots{
    position:absolute;
    left:50%;
    bottom:18px;
    transform:translateX(-50%);
    z-index:25;
    display:flex;
    align-items:center;
    gap:8px;
    padding:7px 10px;
    border-radius:999px;
    background:rgba(0,0,0,.4);
    backdrop-filter:blur(8px);
}

.promo-dot{
    width:9px;
    height:9px;
    padding:0;
    border:1px solid rgba(255,255,255,.85);
    border-radius:50%;
    background:transparent;
    cursor:pointer;
    transition:width .3s ease,background .3s ease;
}

.promo-dot.active{
    width:28px;
    border-radius:20px;
    background:#fff;
}

@media(max-width:850px){

    .promo-shell{
        grid-template-columns:1fr;
    }

    .promo-media{
        min-height:300px;
        max-height:390px;
    }

    .promo-content{
        padding:2rem 1.5rem;
    }

    .promo-content h2{
        font-size:clamp(2rem,9vw,3rem);
    }
}

@media(max-width:600px){

    .home-promotions-carousel{
        padding:1.4rem 0;
    }

    .promo-shell{
        border-radius:19px;
    }

    .promo-media{
        min-height:235px;
        max-height:280px;
    }

    .promo-content{
        padding:1.5rem 1.15rem;
    }

    .promo-description{
        font-size:.9rem;
    }

    .promo-new{
        font-size:1.5rem;
    }

    .promo-countdown{
        gap:.4rem;
    }

    .promo-countdown span{
        min-width:50px;
        padding:.5rem .45rem;
    }

    .promo-actions{
        flex-direction:column;
        align-items:stretch;
    }

    .promo-actions .btn,
    .promo-whatsapp{
        width:100%;
    }

    .promo-arrow{
        width:40px;
        height:40px;
        font-size:1.6rem;
    }

    .promo-prev{
        left:10px;
    }

    .promo-next{
        right:10px;
    }

    .promo-click-badge{
        left:12px;
        bottom:12px;
        font-size:.65rem;
    }
}

</style>

<script>

(function(){

    'use strict';

    const carousel =
        document.querySelector('.promo-carousel');

    if(!carousel){
        return;
    }

    const slides =
        carousel.querySelectorAll('.promo-slide');

    const dots =
        carousel.querySelectorAll('.promo-dot');

    const nextButton =
        carousel.querySelector('.promo-next');

    const prevButton =
        carousel.querySelector('.promo-prev');

    if(!slides.length){
        return;
    }

    let current = 0;
    let timer = null;

    const intervalTime = 5000;

    function showPromotion(index){

        if(index < 0){
            index = slides.length - 1;
        }

        if(index >= slides.length){
            index = 0;
        }

        slides.forEach(function(slide,i){

            slide.classList.toggle(
                'active',
                i === index
            );

        });

        dots.forEach(function(dot,i){

            dot.classList.toggle(
                'active',
                i === index
            );

        });

        current = index;

    }

    function nextPromotion(){
        showPromotion(current + 1);
    }

    function previousPromotion(){
        showPromotion(current - 1);
    }

    function startAutoPlay(){

        stopAutoPlay();

        if(slides.length <= 1){
            return;
        }

        timer = setInterval(
            nextPromotion,
            intervalTime
        );

    }

    function stopAutoPlay(){

        if(timer){

            clearInterval(timer);

            timer = null;

        }

    }

    if(nextButton){

        nextButton.addEventListener(
            'click',
            function(){

                nextPromotion();

                startAutoPlay();

            }
        );

    }

    if(prevButton){

        prevButton.addEventListener(
            'click',
            function(){

                previousPromotion();

                startAutoPlay();

            }
        );

    }

    dots.forEach(function(dot,index){

        dot.addEventListener(
            'click',
            function(){

                showPromotion(index);

                startAutoPlay();

            }
        );

    });

    carousel.addEventListener(
        'mouseenter',
        stopAutoPlay
    );

    carousel.addEventListener(
        'mouseleave',
        startAutoPlay
    );

    let touchStartX = 0;
    let touchEndX = 0;

    carousel.addEventListener(
        'touchstart',
        function(event){

            touchStartX =
                event.changedTouches[0].screenX;

            stopAutoPlay();

        },
        {passive:true}
    );

    carousel.addEventListener(
        'touchend',
        function(event){

            touchEndX =
                event.changedTouches[0].screenX;

            const difference =
                touchEndX - touchStartX;

            if(Math.abs(difference) > 50){

                if(difference < 0){
                    nextPromotion();
                }else{
                    previousPromotion();
                }

            }

            startAutoPlay();

        },
        {passive:true}
    );

    function updateCountdowns(){

        carousel
            .querySelectorAll(
                '[data-promo-countdown]'
            )
            .forEach(function(box){

                const end =
                    Number(
                        box.dataset.promoCountdown
                    ) * 1000;

                let diff =
                    Math.max(
                        0,
                        end - Date.now()
                    );

                const days =
                    Math.floor(
                        diff / 86400000
                    );

                diff %= 86400000;

                const hours =
                    Math.floor(
                        diff / 3600000
                    );

                diff %= 3600000;

                const minutes =
                    Math.floor(
                        diff / 60000
                    );

                diff %= 60000;

                const seconds =
                    Math.floor(
                        diff / 1000
                    );

                const daysEl =
                    box.querySelector('[data-days]');

                const hoursEl =
                    box.querySelector('[data-hours]');

                const minutesEl =
                    box.querySelector('[data-minutes]');

                const secondsEl =
                    box.querySelector('[data-seconds]');

                if(daysEl){
                    daysEl.textContent = days;
                }

                if(hoursEl){
                    hoursEl.textContent =
                        String(hours).padStart(2,'0');
                }

                if(minutesEl){
                    minutesEl.textContent =
                        String(minutes).padStart(2,'0');
                }

                if(secondsEl){
                    secondsEl.textContent =
                        String(seconds).padStart(2,'0');
                }

                if(end <= Date.now()){

                    box.innerHTML =
                        '<span style="border:0;background:transparent;color:#888;padding-left:0">Offer ending soon</span>';

                }

            });

    }

    showPromotion(0);

    startAutoPlay();

    updateCountdowns();

    setInterval(
        updateCountdowns,
        1000
    );

})();

</script>

<?php endif; ?>


<!-- ================= MARQUEE STRIP ================= -->

<div class="marquee" aria-hidden="true">

  <div class="marquee-track">

    <?php for ($i = 0; $i < 2; $i++): ?>

      <span class="marquee-item"><?= e(__("weddings")) ?></span>
      <span class="marquee-item"><?= e(__("portraits")) ?></span>
      <span class="marquee-item"><?= e(__("maternity")) ?></span>
      <span class="marquee-item"><?= e(__("family")) ?></span>
      <span class="marquee-item"><?= e(__("editorial")) ?></span>
      <span class="marquee-item"><?= e(__("events")) ?></span>
      <span class="marquee-item"><?= e(__("commercial")) ?></span>
      <span class="marquee-item"><?= e(__("kigali_rwanda")) ?></span>

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
                  <?= e(__('from')) ?> <?= e(rwf($s['price'])) ?>
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

        <?= e(__('view_all_services')) ?>

      </a>

    </div>

  </div>

</section>

<?php endif; ?>



<!-- ================= WHY CHOOSE US ================= -->

<section class="why-choose-us">
  <div class="container">

    <div class="section-head reveal">
      <span class="kicker"><?= e(__('why_choose_us')) ?></span>

      <h2><?= __('more_than_photography') ?></h2>

      <p><?= e(__('why_intro')) ?></p>
    </div>

    <div class="why-grid">

      <article class="why-card reveal">
        <div class="why-icon">📸</div>
        <h3><?= e(__('professional_photography')) ?></h3>
        <p>
          <?= e(__('professional_photography_desc')) ?>
        </p>
      </article>

      <article class="why-card reveal">
        <div class="why-icon">🎥</div>
        <h3><?= e(__('photo_video')) ?></h3>
        <p>
          <?= e(__('photo_video_desc')) ?>
        </p>
      </article>

      <article class="why-card reveal">
        <div class="why-icon">🖼️</div>
        <h3><?= e(__('premium_printing_frames')) ?></h3>
        <p>
          <?= e(__('premium_printing_frames_desc')) ?>
        </p>
      </article>

      <article class="why-card reveal">
        <div class="why-icon">⚡</div>
        <h3><?= e(__('fast_delivery')) ?></h3>
        <p>
          <?= e(__('fast_delivery_desc')) ?>
        </p>
      </article>

      <article class="why-card reveal">
        <div class="why-icon">✨</div>
        <h3><?= e(__('professional_retouching')) ?></h3>
        <p>
          <?= e(__('professional_retouching_desc')) ?>
        </p>
      </article>

      <article class="why-card reveal">
        <div class="why-icon">🤝</div>
        <h3><?= e(__('personalized_service')) ?></h3>
        <p>
          <?= e(__('personalized_service_desc')) ?>
        </p>
      </article>

    </div>

    <div class="why-cta reveal">
      <a class="btn btn-primary" href="<?= e(SITE_URL) ?>/booking.php">
        <?= e(__('book_your_session')) ?>
      </a>

      <a class="btn btn-ghost" href="<?= e(SITE_URL) ?>/services.php">
        <?= e(__('explore_services')) ?>
      </a>
    </div>

  </div>
</section>

<style>
/* =========================================================
   WHY CHOOSE US
   ========================================================= */

.why-choose-us {
    position: relative;
    padding: 6rem 0;
    background:
        radial-gradient(circle at top right, rgba(255,255,255,.035), transparent 35%),
        var(--bg);
    overflow: hidden;
}

.why-choose-us .section-head {
    max-width: 760px;
    margin: 0 auto 3.5rem;
    text-align: center;
}

.why-choose-us .section-head h2 {
    font-family: var(--font-serif);
    font-weight: 500;
    font-size: clamp(2rem, 4.2vw, 3.2rem);
    line-height: 1.12;
    margin: .8rem 0 1.2rem;
}

.why-choose-us .section-head p {
    color: #b9b1a8;
    max-width: 680px;
    margin: 0 auto;
    line-height: 1.8;
}

.why-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1.2rem;
}

.why-card {
    position: relative;
    padding: 2rem 1.7rem;
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 18px;
    background: rgba(255,255,255,.025);
    transition:
        transform .35s ease,
        border-color .35s ease,
        background .35s ease,
        box-shadow .35s ease;
}

.why-card:hover {
    transform: translateY(-7px);
    border-color: rgba(255,255,255,.2);
    background: rgba(255,255,255,.045);
    box-shadow: 0 18px 45px rgba(0,0,0,.18);
}

.why-icon {
    width: 52px;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.2rem;
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 14px;
    background: rgba(255,255,255,.04);
    font-size: 1.45rem;
}

.why-card h3 {
    margin: 0 0 .7rem;
    font-size: 1.08rem;
    font-weight: 800;
}

.why-card p {
    margin: 0;
    color: #aaa29a;
    line-height: 1.7;
    font-size: .92rem;
}

.why-cta {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: .9rem;
    flex-wrap: wrap;
    margin-top: 3rem;
}

@media (max-width: 900px) {
    .why-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .why-choose-us {
        padding: 4.5rem 0;
    }

    .why-grid {
        grid-template-columns: 1fr;
    }

    .why-card {
        padding: 1.6rem;
    }

    .why-cta {
        flex-direction: column;
    }

    .why-cta .btn {
        width: 100%;
        max-width: 320px;
        text-align: center;
    }
}
</style>


<!-- ================= CLIENT REVIEWS ================= -->

<section class="client-reviews">
  <div class="container">

    <div class="section-head reveal">
      <span class="kicker"><?= e(__("client_reviews")) ?></span>

      <h2><?= e(__('what_clients_say')) ?></h2>

      <p>
        <?= e(__('reviews_intro')) ?>
      </p>
    </div>

    <div class="reviews-grid">

      <article class="review-card reveal">
        <div class="review-stars" aria-label="Client review">★★★★★</div>
        <div class="review-quote">“</div>

        <p class="review-placeholder">
          <?= e(__('review_grace')) ?>
        </p>

        <div class="review-person">
          <div class="review-avatar">C</div>
          <div>
            <strong>Grace Mignone</strong>
            <span><?= e(__('google_review')) ?></span>
          </div>
        </div>
      </article>

      <article class="review-card review-featured reveal">
        <div class="review-stars" aria-label="Client review">★★★★★</div>
        <div class="review-quote">“</div>

        <p class="review-placeholder">
          <?= e(__('review_guillaume')) ?>
        </p>

        <div class="review-person">
          <div class="review-avatar">C</div>
          <div>
            <strong>Uwase Guillaume</strong>
            <span><?= e(__('google_review')) ?></span>
          </div>
        </div>
      </article>

      <article class="review-card reveal">
        <div class="review-stars" aria-label="Client review">★★★★★</div>
        <div class="review-quote">“</div>

        <p class="review-placeholder">
          <?= e(__('review_adeline')) ?>
        </p>

        <div class="review-person">
          <div class="review-avatar">C</div>
          <div>
            <strong>Adeline Uwizeyimana</strong>
            <span><?= e(__('google_review')) ?></span>
          </div>
        </div>
      </article>

    </div>

    <div class="reviews-cta reveal">
      <p><?= e(__('reviews_cta')) ?></p>

      <div class="google-review-actions">

        <a class="google-review-btn"
           href="https://g.page/r/CezFNPv-ll-IEAE/review"
           target="_blank"
           rel="noopener noreferrer"
           aria-label="Leave a Google Review">

          <span class="google-g-logo" aria-hidden="true">
            <svg viewBox="0 0 48 48" role="img">
              <path fill="#EA4335" d="M24 9.5c3.54 0 6.72 1.22 9.22 3.6l6.85-6.85C35.9 2.24 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.2C12.46 13.04 17.74 9.5 24 9.5z"/>
              <path fill="#4285F4" d="M46.5 24.55c0-1.6-.14-3.14-.4-4.55H24v8.6h12.65c-.54 2.94-2.2 5.43-4.7 7.1l7.58 5.88C43.94 37.55 46.5 31.58 46.5 24.55z"/>
              <path fill="#FBBC05" d="M10.54 28.58A14.48 14.48 0 0 1 9.77 24c0-1.6.27-3.15.77-4.58l-7.98-6.2A24 24 0 0 0 0 24c0 3.9.93 7.6 2.56 10.78l7.98-6.2z"/>
              <path fill="#34A853" d="M24 48c6.48 0 11.91-2.14 15.87-6.42l-7.58-5.88c-2.1 1.4-4.8 2.22-8.29 2.22-6.26 0-11.54-3.54-13.46-8.52l-7.98 6.2C6.51 42.62 14.62 48 24 48z"/>
            </svg>
          </span>

          <span>
            <strong><?= e(__('leave_google_review')) ?></strong>
            <small><?= e(__('share_experience')) ?></small>
          </span>

          <span class="google-review-arrow" aria-hidden="true">↗</span>
        </a>

        <a class="google-read-btn"
           href="https://g.page/r/CezFNPv-ll-IEAE/review"
           target="_blank"
           rel="noopener noreferrer">
          <?= e(__('read_reviews')) ?>
        </a>

      </div>
    </div>

  </div>
</section>

<style>
/* =========================================================
   CLIENT REVIEWS
   ========================================================= */

.client-reviews {
    position: relative;
    padding: 6rem 0;
    background: var(--bg-soft);
    overflow: hidden;
}

.client-reviews .section-head {
    max-width: 760px;
    margin: 0 auto 3.5rem;
    text-align: center;
}

.client-reviews .section-head h2 {
    font-family: var(--font-serif);
    font-weight: 500;
    font-size: clamp(2rem, 4vw, 3rem);
    line-height: 1.15;
    margin: .8rem 0 1.1rem;
}

.client-reviews .section-head p {
    color: #aaa29a;
    max-width: 680px;
    margin: 0 auto;
    line-height: 1.8;
}

.reviews-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1.2rem;
}

.review-card {
    position: relative;
    padding: 2rem;
    min-height: 300px;
    display: flex;
    flex-direction: column;
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 20px;
    background: rgba(255,255,255,.025);
    transition:
        transform .35s ease,
        border-color .35s ease,
        box-shadow .35s ease;
}

.review-card:hover {
    transform: translateY(-7px);
    border-color: rgba(255,255,255,.18);
    box-shadow: 0 18px 45px rgba(0,0,0,.18);
}

.review-featured {
    background: rgba(255,255,255,.045);
    border-color: rgba(255,255,255,.13);
}

.review-stars {
    letter-spacing: .18em;
    font-size: .85rem;
    margin-bottom: 1rem;
}

.review-quote {
    font-family: Georgia, serif;
    font-size: 4rem;
    line-height: .7;
    opacity: .3;
    height: 42px;
}

.review-placeholder {
    color: #aaa29a;
    line-height: 1.8;
    font-size: .94rem;
    margin: .5rem 0 1.8rem;
    flex: 1;
}

.review-person {
    display: flex;
    align-items: center;
    gap: .8rem;
    padding-top: 1.2rem;
    border-top: 1px solid rgba(255,255,255,.07);
}

.review-avatar {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(255,255,255,.08);
    font-weight: 800;
}

.review-person strong,
.review-person span {
    display: block;
}

.review-person strong {
    font-size: .9rem;
}

.review-person span {
    margin-top: .2rem;
    color: #817a73;
    font-size: .75rem;
}

.reviews-cta {
    text-align: center;
    margin-top: 3rem;
}

.reviews-cta p {
    color: #aaa29a;
    margin-bottom: 1rem;
}

.google-review-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .8rem;
    flex-wrap: wrap;
}

.google-review-btn {
    display: inline-flex;
    align-items: center;
    gap: .8rem;
    min-width: 270px;
    padding: .78rem 1rem;
    color: #fff;
    text-decoration: none;
    text-align: left;
    border: 1px solid rgba(255,255,255,.13);
    border-radius: 14px;
    background: rgba(255,255,255,.055);
    box-shadow: 0 10px 28px rgba(0,0,0,.16);
    transition: transform .25s ease, border-color .25s ease,
                background .25s ease, box-shadow .25s ease;
}

.google-review-btn:hover {
    transform: translateY(-3px);
    border-color: rgba(255,255,255,.28);
    background: rgba(255,255,255,.09);
    box-shadow: 0 16px 35px rgba(0,0,0,.24);
}

.google-g-logo {
    width: 32px;
    height: 32px;
    flex: 0 0 32px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: #fff;
    padding: 5px;
    box-sizing: border-box;
}

.google-g-logo svg {
    width: 100%;
    height: 100%;
    display: block;
}

.google-review-btn strong,
.google-review-btn small {
    display: block;
}

.google-review-btn strong {
    font-size: .92rem;
    line-height: 1.2;
}

.google-review-btn small {
    margin-top: .18rem;
    color: #aaa29a;
    font-size: .72rem;
}

.google-review-arrow {
    margin-left: auto;
    font-size: 1.15rem;
    opacity: .7;
    transition: transform .25s ease;
}

.google-review-btn:hover .google-review-arrow {
    transform: translate(2px, -2px);
}

.google-read-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 50px;
    padding: .78rem 1.05rem;
    color: inherit;
    text-decoration: none;
    font-weight: 800;
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 14px;
    transition: transform .25s ease, border-color .25s ease,
                background .25s ease;
}

.google-read-btn:hover {
    transform: translateY(-2px);
    border-color: rgba(255,255,255,.25);
    background: rgba(255,255,255,.045);
}

@media (max-width: 600px) {
    .google-review-actions {
        flex-direction: column;
        width: 100%;
    }

    .google-review-btn,
    .google-read-btn {
        width: 100%;
        max-width: 360px;
        box-sizing: border-box;
    }
}

@media (max-width: 900px) {
    .reviews-grid {
        grid-template-columns: 1fr 1fr;
    }

    .review-card:last-child {
        grid-column: 1 / -1;
        max-width: 50%;
        margin: 0 auto;
        width: 100%;
    }
}

@media (max-width: 600px) {
    .client-reviews {
        padding: 4.5rem 0;
    }

    .reviews-grid {
        grid-template-columns: 1fr;
    }

    .review-card:last-child {
        grid-column: auto;
        max-width: none;
    }
}
</style>

<?php if ($aboutS): ?>

<!-- ================= STUDIO MAP ================= -->

<section class="studio-map-section" id="studio-location">
  <div class="container">
    <div class="studio-map-head reveal">
      <span class="kicker"><?= e(__("visit_studio")) ?></span>
      <h2><?= e(__('find_studio')) ?></h2>
      <p><?= e(__('studio_intro')) ?></p>
    </div>

    <div class="studio-map-card reveal">
      <div class="studio-map-info">
        <div class="studio-map-pin">📍</div>
        <div>
          <h3>Imagination Nature Studio</h3>
          <p>Kigali, Rwanda</p>
        </div>
        <a class="studio-map-directions"
           href="https://maps.app.goo.gl/ksyEniH3PsgTAHHq8?g_st=ic"
           target="_blank" rel="noopener noreferrer">
          <span>📍</span> <?= e(__("get_directions")) ?> <span class="studio-map-arrow">↗</span>
        </a>
      </div>

      <div class="studio-map-frame">
        <iframe
          src="https://www.google.com/maps?q=Imagination%20Nature%20Studio%2C%20Kigali%2C%20Rwanda&output=embed"
          title="Imagination Nature Studio location on Google Maps"
          loading="lazy" allowfullscreen
          referrerpolicy="no-referrer-when-downgrade"></iframe>
      </div>
    </div>
  </div>
</section>

<style>
.studio-map-section{padding:6rem 0;background:var(--bg-soft);overflow:hidden}
.studio-map-head{max-width:760px;margin:0 auto 3rem;text-align:center}
.studio-map-head h2{font-family:var(--font-serif);font-weight:500;font-size:clamp(2rem,4vw,3rem);line-height:1.15;margin:.8rem 0 1.1rem}
.studio-map-head p{color:#aaa29a;max-width:680px;margin:0 auto;line-height:1.8}
.studio-map-card{display:grid;grid-template-columns:360px minmax(0,1fr);min-height:430px;overflow:hidden;border:1px solid rgba(255,255,255,.08);border-radius:22px;background:rgba(255,255,255,.025);box-shadow:0 20px 55px rgba(0,0,0,.18)}
.studio-map-info{display:flex;flex-direction:column;justify-content:center;padding:2.2rem;background:radial-gradient(circle at top left,rgba(255,255,255,.06),transparent 45%),var(--bg)}
.studio-map-pin{width:58px;height:58px;display:flex;align-items:center;justify-content:center;margin-bottom:1.4rem;border:1px solid rgba(255,255,255,.1);border-radius:16px;background:rgba(255,255,255,.045);font-size:1.5rem}
.studio-map-info h3{margin:0 0 .5rem;font-size:1.25rem;font-weight:800}
.studio-map-info p{margin:0 0 1.8rem;color:#aaa29a;line-height:1.7}
.studio-map-directions{width:100%;display:flex;align-items:center;justify-content:center;gap:.6rem;min-height:50px;box-sizing:border-box;padding:.8rem 1rem;border-radius:12px;background:#fff;color:#111;text-decoration:none;font-weight:800;transition:transform .25s ease,opacity .25s ease}
.studio-map-directions:hover{transform:translateY(-3px);opacity:.9}
.studio-map-arrow{margin-left:auto;font-size:1.1rem}
.studio-map-frame,.studio-map-frame iframe{min-height:430px}
.studio-map-frame{background:#111}
.studio-map-frame iframe{display:block;width:100%;height:100%;border:0}
@media(max-width:900px){.studio-map-card{grid-template-columns:1fr}.studio-map-info{padding:2rem}.studio-map-frame,.studio-map-frame iframe{min-height:380px}}
@media(max-width:600px){.studio-map-section{padding:4.5rem 0}.studio-map-card{border-radius:18px}.studio-map-info{padding:1.6rem}.studio-map-frame,.studio-map-frame iframe{min-height:330px}.studio-map-directions{min-height:48px}}
</style>

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
            alt="<?= e($g['title'] ?: __('portfolio_image')) ?>"
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

        <?= e(__('view_full_portfolio')) ?>

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
    aria-label="<?= e(__("close")) ?>">

    &times;

  </button>

  <img src="" alt="">

</div>


<?php require __DIR__ . '/includes/footer.php'; ?>