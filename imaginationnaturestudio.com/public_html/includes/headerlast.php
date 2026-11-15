<?php
require_once __DIR__ . '/functions.php';

/*
|--------------------------------------------------------------------------
| Public website language engine
|--------------------------------------------------------------------------
| Must be loaded before this header uses t(), current_language(),
| language_info(), ims_supported_languages(), etc.
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/language.php';

$pageTitle = $pageTitle ?? (setting('site_name', SITE_NAME) . ' | Photography Studio in Kigali');
$pageDesc  = $pageDesc  ?? 'Creative wedding, portrait, maternity, family, event, editorial, and commercial photography in Kigali, Rwanda. Book Imagination Nature Studio.';
$canonical = $canonical ?? (SITE_URL . ($_SERVER['REQUEST_URI'] ?? '/'));

$logoUrl = logo_url();

$favUrl = ($f = setting('favicon'))
    ? SITE_URL . '/uploads/' . $f
    : SITE_URL . '/assets/images/favicon.png';

$ogUrl = ($o = setting('og_image'))
    ? SITE_URL . '/uploads/' . $o
    : SITE_URL . '/assets/images/og-cover.jpg';

$primary = setting('primary_color', '#e07a2f');
$accent  = setting('accent_color', '#f2a35e');

$cssVersion = @filemtime(SITE_ROOT . '/assets/css/style.css') ?: time();

// Localize page metadata while keeping database/site settings untouched.
$pageTitle = t($pageTitle, $pageTitle);
$pageDesc  = t($pageDesc, $pageDesc);

// Start public-site translation buffer. The buffer only affects rendered output.
ob_start('ims_translate_output');


/* =========================================================
   WEBSITE VISITOR TRACKING
   ========================================================= */

try {

    $visitorId = $_COOKIE['ins_visitor_id'] ?? '';

    if (
        $visitorId === '' ||
        !preg_match('/^[a-f0-9]{64}$/', $visitorId)
    ) {
        $visitorId = bin2hex(random_bytes(32));

        setcookie(
            'ins_visitor_id',
            $visitorId,
            [
                'expires'  => time() + (60 * 60 * 24 * 365 * 2),
                'path'     => '/',
                'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
    }

    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if (preg_match('/tablet|ipad/i', $userAgent)) {
        $deviceType = 'Tablet';
    } elseif (preg_match('/mobile|android|iphone|ipod/i', $userAgent)) {
        $deviceType = 'Mobile';
    } else {
        $deviceType = 'Desktop';
    }

    if (preg_match('/Edg/i', $userAgent)) {
        $browser = 'Microsoft Edge';
    } elseif (preg_match('/OPR|Opera/i', $userAgent)) {
        $browser = 'Opera';
    } elseif (preg_match('/Chrome/i', $userAgent)) {
        $browser = 'Google Chrome';
    } elseif (preg_match('/Safari/i', $userAgent)) {
        $browser = 'Safari';
    } elseif (preg_match('/Firefox/i', $userAgent)) {
        $browser = 'Firefox';
    } else {
        $browser = 'Other';
    }

    if (preg_match('/iPhone|iPad|iPod/i', $userAgent)) {
        $operatingSystem = 'iOS';
    } elseif (preg_match('/Android/i', $userAgent)) {
        $operatingSystem = 'Android';
    } elseif (preg_match('/Mac OS X/i', $userAgent)) {
        $operatingSystem = 'macOS';
    } elseif (preg_match('/Windows/i', $userAgent)) {
        $operatingSystem = 'Windows';
    } elseif (preg_match('/Linux/i', $userAgent)) {
        $operatingSystem = 'Linux';
    } else {
        $operatingSystem = 'Other';
    }

    $pdo = db();

    $checkVisitor = $pdo->prepare("
        SELECT id
        FROM website_visits
        WHERE visitor_id = ?
          AND visited_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        LIMIT 1
    ");

    $checkVisitor->execute([$visitorId]);

    if (!$checkVisitor->fetchColumn()) {

        $insertVisitor = $pdo->prepare("
            INSERT INTO website_visits
            (
                visitor_id,
                device_type,
                browser,
                operating_system,
                visited_at
            )
            VALUES
            (?, ?, ?, ?, NOW())
        ");

        $insertVisitor->execute([
            $visitorId,
            $deviceType,
            $browser,
            $operatingSystem
        ]);
    }

} catch (Throwable $e) {
    // Visitor tracking must never stop the website.
}

?>

<!doctype html>
<html lang="<?= e(language_info()['html']) ?>">

<head>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title><?= e($pageTitle) ?></title>

<meta
    name="description"
    content="<?= e($pageDesc) ?>"
>

<link
    rel="canonical"
    href="<?= e($canonical) ?>"
>


<!-- =========================================================
     OPEN GRAPH
     ========================================================= -->

<meta property="og:type" content="website">

<meta
    property="og:title"
    content="<?= e($pageTitle) ?>"
>

<meta
    property="og:description"
    content="<?= e($pageDesc) ?>"
>

<meta
    property="og:url"
    content="<?= e($canonical) ?>"
>

<meta
    property="og:image"
    content="<?= e($ogUrl) ?>"
>

<meta
    property="og:site_name"
    content="<?= e(setting('site_name', SITE_NAME)) ?>"
>


<!-- =========================================================
     TWITTER
     ========================================================= -->

<meta
    name="twitter:card"
    content="summary_large_image"
>

<meta
    name="twitter:title"
    content="<?= e($pageTitle) ?>"
>

<meta
    name="twitter:description"
    content="<?= e($pageDesc) ?>"
>

<meta
    name="twitter:image"
    content="<?= e($ogUrl) ?>"
>


<!-- =========================================================
     FAVICON
     ========================================================= -->

<link
    rel="icon"
    href="<?= e($favUrl) ?>"
>


<!-- =========================================================
     GOOGLE FONTS
     ========================================================= -->

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap"
    rel="stylesheet"
>


<!-- =========================================================
     MAIN CSS
     ========================================================= -->

<link
    rel="stylesheet"
    href="<?= e(SITE_URL) ?>/assets/css/style.css?v=<?= e($cssVersion) ?>"
>


<style>

:root {
    --accent: <?= e($primary) ?>;
    --accent-2: <?= e($accent) ?>;
}

</style>


<!-- =========================================================
     GOOGLE ANALYTICS 4
     Imagination Nature Studio
     Measurement ID: G-SEMIE8BYTX
     ========================================================= -->

<script
    async
    src="https://www.googletagmanager.com/gtag/js?id=G-SEMIE8BYTX">
</script>

<script>

window.dataLayer = window.dataLayer || [];

function gtag() {
    dataLayer.push(arguments);
}

gtag('js', new Date());

gtag('config', 'G-SEMIE8BYTX');

</script>

<!-- =========================================================
     END GOOGLE ANALYTICS 4
     ========================================================= -->


<!-- =========================================================
     WEBSITE STRUCTURED DATA
     ========================================================= -->

<script type="application/ld+json">

<?= json_encode([

    '@context' => 'https://schema.org',

    '@type' => [
        'LocalBusiness',
        'PhotographyBusiness'
    ],

    'name' => setting(
        'site_name',
        SITE_NAME
    ),

    'image' => $ogUrl,

    '@id' => SITE_URL,

    'url' => SITE_URL,

    'telephone' => setting(
        'phone_primary',
        BIZ_PHONE_1
    ),

    'email' => setting(
        'email_primary',
        BIZ_EMAIL
    ),

    'priceRange' => 'RWF',

    'address' => [

        '@type' => 'PostalAddress',

        'streetAddress' => setting(
            'address',
            BIZ_ADDRESS
        ),

        'addressLocality' => 'Kigali',

        'addressCountry' => 'RW',

    ],

    'openingHoursSpecification' => [[

        '@type' => 'OpeningHoursSpecification',

        'dayOfWeek' => [

            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday'

        ],

        'opens' => '09:00',

        'closes' => '21:00'

    ]],

    'sameAs' => array_values(
        array_filter([

            setting(
                'instagram_url',
                SOCIAL_INSTAGRAM
            ),

            setting(
                'tiktok_url',
                SOCIAL_TIKTOK
            ),

            setting(
                'facebook_url'
            ),

            setting(
                'youtube_url'
            ),

        ])
    ),

], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>

</script>


<style>
/* =========================================================
   LANGUAGE GLOBE DROPDOWN
   One button -> English / Kinyarwanda / Français
   ========================================================= */

.language-switcher{
    position:relative;
    display:inline-block;
    margin-right:.65rem;
    z-index:100000;
}

.language-trigger{
    appearance:none;
    -webkit-appearance:none;
    display:flex;
    align-items:center;
    gap:9px;
    height:44px;
    padding:0 14px;
    border:1px solid rgba(255,255,255,.20);
    border-radius:999px;
    background:rgba(15,18,25,.88);
    color:#fff;
    cursor:pointer;
    font-family:inherit;
    font-size:14px;
    font-weight:600;
    line-height:1;
    backdrop-filter:blur(14px);
    -webkit-backdrop-filter:blur(14px);
    box-shadow:0 8px 25px rgba(0,0,0,.18);
    transition:all .2s ease;
}

.language-trigger:hover,
.language-switcher.open .language-trigger{
    background:rgba(25,29,38,.97);
    border-color:rgba(255,255,255,.40);
}

.language-globe{
    display:grid;
    place-items:center;
    width:21px;
    height:21px;
    font-size:19px;
}

.language-current{
    white-space:nowrap;
}

.language-arrow{
    font-size:19px;
    transform:translateY(-1px);
    transition:transform .2s ease;
}

.language-switcher.open .language-arrow{
    transform:rotate(180deg) translateY(1px);
}


/* DROPDOWN */

.language-menu{
    position:absolute;
    top:calc(100% + 10px);
    right:0;
    width:225px;
    padding:8px;
    border:1px solid rgba(255,255,255,.16);
    border-radius:17px;
    background:rgba(10,14,21,.97);
    box-shadow:0 18px 45px rgba(0,0,0,.40);
    backdrop-filter:blur(18px);
    -webkit-backdrop-filter:blur(18px);

    opacity:0;
    visibility:hidden;
    pointer-events:none;
    transform:translateY(-8px) scale(.97);
    transform-origin:top right;

    transition:
        opacity .18s ease,
        transform .18s ease,
        visibility .18s ease;
}

.language-switcher.open .language-menu{
    opacity:1;
    visibility:visible;
    pointer-events:auto;
    transform:translateY(0) scale(1);
}


/* OPTIONS */

.language-option{
    display:flex;
    align-items:center;
    gap:12px;
    min-height:46px;
    padding:8px 11px;
    border-radius:11px;
    color:#fff !important;
    text-decoration:none !important;
    font-size:14px;
    transition:background .16s ease,transform .16s ease;
}

.language-option:hover{
    background:rgba(255,255,255,.09);
    transform:translateX(2px);
}

.language-option.active{
    background:var(--accent);
    color:#fff !important;
}

.language-flag{
    width:29px;
    flex:0 0 29px;
    font-size:22px;
    line-height:1;
    text-align:center;
}

.language-name{
    flex:1;
    white-space:nowrap;
    font-weight:500;
}

.language-check{
    font-size:17px;
    font-weight:700;
}


/* MOBILE */

@media(max-width:900px){
    .language-switcher{
        margin-right:.4rem;
    }

    .language-trigger{
        height:42px;
        padding:0 12px;
    }
}

@media(max-width:600px){
    .language-switcher{
        order:1;
    }

    .nav-desktop-cta{
        display:none;
    }

    .language-trigger{
        height:40px;
        padding:0 11px;
    }

    .language-current{
        font-size:13px;
    }

    .language-menu{
        width:210px;
    }
}
</style>


<script>
(function(){
    'use strict';

    function initLanguageDropdown(){
        var switcher = document.getElementById('languageSwitcher');
        var trigger  = document.getElementById('languageTrigger');

        if (!switcher || !trigger || switcher.dataset.ready === '1') {
            return;
        }

        switcher.dataset.ready = '1';

        trigger.addEventListener('click', function(event){
            event.preventDefault();
            event.stopPropagation();

            var open = switcher.classList.toggle('open');

            trigger.setAttribute(
                'aria-expanded',
                open ? 'true' : 'false'
            );
        });

        switcher.querySelectorAll('.language-option').forEach(function(option){
            option.addEventListener('click', function(){
                switcher.classList.remove('open');
                trigger.setAttribute('aria-expanded', 'false');
            });
        });

        document.addEventListener('click', function(event){
            if (!switcher.contains(event.target)){
                switcher.classList.remove('open');
                trigger.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('keydown', function(event){
            if (event.key === 'Escape'){
                switcher.classList.remove('open');
                trigger.setAttribute('aria-expanded', 'false');
                trigger.focus();
            }
        });
    }

    if (document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', initLanguageDropdown);
    } else {
        initLanguageDropdown();
    }
})();
</script>

</head>


<body>


<!-- =========================================================
     PRELOADER
     ========================================================= -->

<div
    class="preloader"
    aria-hidden="true"
>

    <div>

        <div
            class="preloader-brand"
            aria-label="Imagination Nature Studio"
        >

            <span>I</span>
            <span>M</span>
            <span>A</span>
            <span>G</span>
            <span>I</span>
            <span>N</span>
            <span>A</span>
            <span>T</span>
            <span>I</span>
            <span>O</span>
            <span>N</span>

        </div>


        <div class="preloader-bar"></div>

    </div>

</div>

<!-- =========================================================
     PREMIUM PRELOADER ANIMATION
     Imagination Nature Studio
     ========================================================= -->
<style>
.preloader{
    position:fixed!important;
    inset:0!important;
    z-index:99999!important;
    display:grid!important;
    place-items:center!important;
    background:#0a0908!important;
    color:#f3ece3!important;
    opacity:1;
    visibility:visible;
    pointer-events:auto;
    transition:opacity .65s ease, visibility .65s ease;
}

.preloader.preloader-done{
    opacity:0!important;
    visibility:hidden!important;
    pointer-events:none!important;
}

.preloader > div{
    width:min(520px,78vw);
    text-align:center;
}

.preloader-brand{
    display:flex;
    justify-content:center;
    align-items:center;
    gap:.06em;
    font-family:Inter,system-ui,sans-serif;
    font-size:clamp(1.45rem,4vw,2.65rem);
    font-weight:500;
    letter-spacing:.22em;
    padding-left:.22em;
    white-space:nowrap;
}

.preloader-brand span{
    display:inline-block;
    opacity:0;
    transform:translateY(12px);
    animation:preloaderLetterIn .55s cubic-bezier(.16,1,.3,1) forwards;
}

.preloader-brand span:nth-child(1){animation-delay:.04s}
.preloader-brand span:nth-child(2){animation-delay:.08s}
.preloader-brand span:nth-child(3){animation-delay:.12s}
.preloader-brand span:nth-child(4){animation-delay:.16s}
.preloader-brand span:nth-child(5){animation-delay:.20s}
.preloader-brand span:nth-child(6){animation-delay:.24s}
.preloader-brand span:nth-child(7){animation-delay:.28s}
.preloader-brand span:nth-child(8){animation-delay:.32s}
.preloader-brand span:nth-child(9){animation-delay:.36s}
.preloader-brand span:nth-child(10){animation-delay:.40s}
.preloader-brand span:nth-child(11){animation-delay:.44s}

@keyframes preloaderLetterIn{
    to{
        opacity:1;
        transform:translateY(0);
    }
}

/* The signature loading line: starts in the center and expands both ways. */
.preloader-bar{
    position:relative;
    width:min(260px,60vw);
    height:2px;
    margin:22px auto 0;
    background:rgba(255,255,255,.10);
    overflow:hidden;
    border-radius:99px;
}

.preloader-bar::before{
    content:"";
    position:absolute;
    left:50%;
    top:0;
    width:0;
    height:100%;
    transform:translateX(-50%);
    background:linear-gradient(90deg,#e07a2f,#f2a35e,#e07a2f);
    box-shadow:0 0 14px rgba(224,122,47,.55);
    animation:preloaderLine 1.15s cubic-bezier(.22,1,.36,1) forwards;
}

@keyframes preloaderLine{
    0%{width:0;opacity:.35}
    18%{width:18%;opacity:1}
    55%{width:100%;opacity:1}
    100%{width:100%;opacity:1}
}

@media(max-width:600px){
    .preloader-brand{
        font-size:clamp(1.05rem,6vw,1.55rem);
        letter-spacing:.15em;
    }
    .preloader-bar{
        width:min(210px,62vw);
        margin-top:18px;
    }
}

@media(prefers-reduced-motion:reduce){
    .preloader-brand span{
        animation:none!important;
        opacity:1;
        transform:none;
    }
    .preloader-bar::before{
        animation:none!important;
        width:100%;
        opacity:1;
    }
}
</style>

<script>
(function(){
    'use strict';

    var preloader = document.querySelector('.preloader');
    if (!preloader) return;

    var finished = false;

    function finishPreloader(){
        if (finished) return;
        finished = true;
        preloader.classList.add('preloader-done');

        window.setTimeout(function(){
            if (preloader && preloader.parentNode){
                preloader.parentNode.removeChild(preloader);
            }
        }, 750);
    }

    /* Wait for the page assets, but never allow the loader to trap the visitor. */
    if (document.readyState === 'complete') {
        window.setTimeout(finishPreloader, 350);
    } else {
        window.addEventListener('load', function(){
            window.setTimeout(finishPreloader, 350);
        }, {once:true});
    }

    /* Safety fallback: slow images/network must never leave a white/black screen. */
    window.setTimeout(finishPreloader, 3500);
})();
</script>


<!-- =========================================================
     WEBSITE HEADER
     ========================================================= -->

<header
    class="site-header"
    id="siteHeader"
>

    <div class="container nav-wrap">


        <!-- =====================================================
             LOGO
             ===================================================== -->

        <a
            href="<?= e(SITE_URL) ?>/"
            class="brand"
        >

            <?php if ($logoUrl): ?>

                <img
                    src="<?= e($logoUrl) ?>"
                    alt="<?= e(setting('site_name', SITE_NAME)) ?>"
                >

            <?php else: ?>

                <span class="brand-mark">
                    INS
                </span>

                <span class="brand-text">

                    <strong>
                        <?= e(setting(
                            'site_name',
                            'Imagination Nature'
                        )) ?>
                    </strong>

                    <em>
                        Studio · Kigali
                    </em>

                </span>

            <?php endif; ?>

        </a>


        <!-- =====================================================
             MAIN NAVIGATION
             ===================================================== -->

        <nav
            class="main-nav"
            id="mainNav"
            aria-label="Main navigation"
        >

            <a
                href="<?= e(SITE_URL) ?>/"
            >
                Home
            </a>

            <a
                href="<?= e(SITE_URL) ?>/about.php"
            >
                About
            </a>

            <a
                href="<?= e(SITE_URL) ?>/services.php"
            >
                Services
            </a>

            <a
                href="<?= e(SITE_URL) ?>/portfolio.php"
            >
                Portfolio
            </a>

            <a
                href="<?= e(SITE_URL) ?>/contact.php"
            >
                Contact
            </a>

            <a
                class="nav-mobile-only"
                href="<?= e(SITE_URL) ?>/booking.php"
            >
                Book a Session
            </a>

        </nav>


        <!-- =====================================================
             RIGHT SIDE
             ===================================================== -->

        <div class="nav-right">

            <div class="language-switcher" id="languageSwitcher">

                <button
                    type="button"
                    class="language-trigger"
                    id="languageTrigger"
                    aria-label="<?= e(t('choose_language', 'Choose language')) ?>"
                    aria-haspopup="true"
                    aria-expanded="false"
                >
                    <span class="language-globe" aria-hidden="true">🌐</span>
                    <span class="language-current">
                        <?= e(language_info()['native']) ?>
                    </span>
                    <span class="language-arrow" aria-hidden="true">⌄</span>
                </button>

                <div
                    class="language-menu"
                    id="languageMenu"
                    role="menu"
                    aria-label="<?= e(t('choose_language', 'Choose language')) ?>"
                >

                    <?php foreach (ims_supported_languages() as $code => $info): ?>

                        <a
                            class="language-option<?= current_language() === $code ? ' active' : '' ?>"
                            href="<?= e(language_url($code)) ?>"
                            hreflang="<?= e($info['html']) ?>"
                            title="<?= e($info['native']) ?>"
                            role="menuitem"
                        >
                            <span class="language-flag" aria-hidden="true">
                                <?= e($info['flag'] ?? '🌐') ?>
                            </span>

                            <span class="language-name">
                                <?= e($info['native']) ?>
                            </span>

                            <?php if (current_language() === $code): ?>
                                <span class="language-check" aria-hidden="true">✓</span>
                            <?php endif; ?>
                        </a>

                    <?php endforeach; ?>

                </div>

            </div>

            <a
                class="btn btn-primary nav-desktop-cta"
                href="<?= e(SITE_URL) ?>/booking.php"
            >
                Book a Session
            </a>


            <!-- MOBILE MENU BUTTON -->

            <button
                class="nav-toggle"
                id="navToggle"
                type="button"
                aria-label="Toggle menu"
                aria-expanded="false"
                aria-controls="mainNav"
            >

                <span></span>
                <span></span>
                <span></span>

            </button>

        </div>

    </div>

</header>


<!-- =========================================================
     MAIN CONTENT STARTS HERE
     ========================================================= -->

<main>
