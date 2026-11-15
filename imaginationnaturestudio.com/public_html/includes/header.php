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
   WEBSITE VISITOR + PAGE VIEW TRACKING
   Requires:
   - website_visits.is_bot
   - website_pageviews table
   The SQL migration supplied with Visitor Analytics creates these.
   ========================================================= */

try {
    $visitorId = $_COOKIE['ins_visitor_id'] ?? '';

    if ($visitorId === '' || !preg_match('/^[a-f0-9]{64}$/', $visitorId)) {
        $visitorId = bin2hex(random_bytes(32));

        if (!headers_sent()) {
            setcookie('ins_visitor_id', $visitorId, [
                'expires'  => time() + (60 * 60 * 24 * 365 * 2),
                'path'     => '/',
                'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    }

    $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $isBot = (bool)preg_match(
        '/bot|crawler|spider|slurp|bingpreview|facebookexternalhit|whatsapp|telegrambot|ahrefs|semrush|petalbot|yandex|baiduspider|google-inspectiontool|googlebot|bingbot|duckduckbot|bytespider|gptbot|claudebot/i',
        $userAgent
    );

    // Device detection
    if (preg_match('/ipad|tablet/i', $userAgent)) {
        $deviceType = 'Tablet';
    } elseif (preg_match('/mobile|android|iphone|ipod/i', $userAgent)) {
        $deviceType = 'Mobile';
    } else {
        $deviceType = 'Desktop';
    }

    // Browser detection: specific Chromium browsers must be checked before Chrome.
    if (preg_match('/EdgA?\/|EdgiOS\//i', $userAgent)) {
        $browser = 'Microsoft Edge';
    } elseif (preg_match('/OPR\/|Opera/i', $userAgent)) {
        $browser = 'Opera';
    } elseif (preg_match('/SamsungBrowser\//i', $userAgent)) {
        $browser = 'Samsung Internet';
    } elseif (preg_match('/CriOS\//i', $userAgent)) {
        $browser = 'Google Chrome (iOS)';
    } elseif (preg_match('/FxiOS\//i', $userAgent)) {
        $browser = 'Firefox (iOS)';
    } elseif (preg_match('/Firefox\//i', $userAgent)) {
        $browser = 'Firefox';
    } elseif (preg_match('/Chrome\//i', $userAgent)) {
        $browser = 'Google Chrome';
    } elseif (preg_match('/Safari\//i', $userAgent)) {
        $browser = 'Safari';
    } else {
        $browser = 'Other';
    }

    // Operating system detection
    if (preg_match('/iPhone|iPad|iPod|CPU OS/i', $userAgent)) {
        $operatingSystem = 'iOS';
    } elseif (preg_match('/Android/i', $userAgent)) {
        $operatingSystem = 'Android';
    } elseif (preg_match('/Mac OS X|Macintosh/i', $userAgent)) {
        $operatingSystem = 'macOS';
    } elseif (preg_match('/Windows NT|Windows/i', $userAgent)) {
        $operatingSystem = 'Windows';
    } elseif (preg_match('/Linux/i', $userAgent)) {
        $operatingSystem = 'Linux';
    } else {
        $operatingSystem = 'Other';
    }

    // Country detection:
    // 1) Prefer country headers supplied by the hosting/proxy.
    // 2) Otherwise use IPWho.is over HTTPS, once per visitor cookie cache.
    // The visitor IP is sent to the geolocation provider only when needed.
    $countryCode = strtoupper(trim((string)(
        $_SERVER['HTTP_CF_IPCOUNTRY']
        ?? $_SERVER['GEOIP_COUNTRY_CODE']
        ?? $_SERVER['HTTP_X_COUNTRY_CODE']
        ?? ''
    )));

    $countryNames = [
        'RW' => 'Rwanda', 'UG' => 'Uganda', 'KE' => 'Kenya',
        'TZ' => 'Tanzania', 'BI' => 'Burundi', 'CD' => 'DR Congo',
        'US' => 'United States', 'GB' => 'United Kingdom',
        'CA' => 'Canada', 'FR' => 'France', 'DE' => 'Germany',
        'BE' => 'Belgium', 'NL' => 'Netherlands', 'IN' => 'India',
        'CN' => 'China', 'JP' => 'Japan', 'AE' => 'United Arab Emirates',
        'ZA' => 'South Africa', 'NG' => 'Nigeria', 'GH' => 'Ghana',
        'AU' => 'Australia', 'BR' => 'Brazil', 'CH' => 'Switzerland',
        'SE' => 'Sweden', 'NO' => 'Norway', 'IT' => 'Italy',
        'ES' => 'Spain', 'IE' => 'Ireland', 'PL' => 'Poland'
    ];

    if ($countryCode === 'XX' || $countryCode === 'T1') {
        $countryCode = '';
    }

    $visitorCountry = $countryCode !== ''
        ? ($countryNames[$countryCode] ?? $countryCode)
        : trim((string)($_SERVER['GEOIP_COUNTRY_NAME'] ?? ''));

    // Use a previously detected country to avoid calling the API on every page.
    $cachedCountry = trim((string)($_COOKIE['ins_country'] ?? ''));
    if ($visitorCountry === '' && $cachedCountry !== '') {
        $visitorCountry = $cachedCountry;
    }

    // If the hosting/proxy did not provide a country, look it up over HTTPS.
    if ($visitorCountry === '') {
        $visitorIp = trim((string)(
            $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? ''
        ));

        $isPublicIp = filter_var(
            $visitorIp,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ($isPublicIp) {
            $geoUrl = 'https://ipwho.is/' . rawurlencode($visitorIp);
            $geoResponse = false;

            if (function_exists('curl_init')) {
                $ch = curl_init($geoUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 2,
                    CURLOPT_TIMEOUT => 3,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_USERAGENT => 'ImaginationNatureStudio-VisitorAnalytics/1.0'
                ]);
                $geoResponse = curl_exec($ch);
                curl_close($ch);
            } else {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 3,
                        'ignore_errors' => true,
                        'header' => "User-Agent: ImaginationNatureStudio-VisitorAnalytics/1.0\r\n"
                    ],
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true
                    ]
                ]);
                $geoResponse = @file_get_contents($geoUrl, false, $context);
            }

            if (is_string($geoResponse) && $geoResponse !== '') {
                $geoData = json_decode($geoResponse, true);

                if (
                    is_array($geoData)
                    && !empty($geoData['success'])
                    && !empty($geoData['country'])
                ) {
                    $visitorCountry = trim((string)$geoData['country']);
                    $countryCode = strtoupper(trim((string)($geoData['country_code'] ?? '')));

                    if (!headers_sent() && $visitorCountry !== '') {
                        setcookie('ins_country', $visitorCountry, [
                            'expires' => time() + (30 * 24 * 60 * 60),
                            'path' => '/',
                            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]);
                    }
                }
            }
        }
    }

    $pdo = db();
    $pageUrl = substr((string)($_SERVER['REQUEST_URI'] ?? '/'), 0, 500);
    $trackingTitle = isset($pageTitle) ? substr((string)$pageTitle, 0, 255) : null;

    // Every public page request is a page view. Bots are stored but separated.
    $pageView = $pdo->prepare(
        'INSERT INTO website_pageviews
            (visitor_id, page_url, page_title, is_bot, visited_at)
         VALUES (?, ?, ?, ?, NOW())'
    );
    $pageView->execute([
        $visitorId,
        $pageUrl,
        $trackingTitle,
        $isBot ? 1 : 0
    ]);

    // One session row per browser ID per 30-minute inactivity window.
    // Refresh visited_at on every request so Online Now reflects recent activity.
    $findSession = $pdo->prepare(
        'SELECT id
           FROM website_visits
          WHERE visitor_id = ?
            AND visited_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
          ORDER BY visited_at DESC
          LIMIT 1'
    );
    $findSession->execute([$visitorId]);
    $sessionId = $findSession->fetchColumn();

    if ($sessionId) {
        $updateSession = $pdo->prepare(
            'UPDATE website_visits
                SET page_url = ?,
                    page_title = ?,
                    device_type = ?,
                    browser = ?,
                    operating_system = ?,
                    country = COALESCE(NULLIF(?, \'\'), country),
                    is_bot = ?,
                    visited_at = NOW()
              WHERE id = ?'
        );
        $updateSession->execute([
            $pageUrl,
            $trackingTitle,
            $deviceType,
            $browser,
            $operatingSystem,
            $visitorCountry,
            $isBot ? 1 : 0,
            $sessionId
        ]);
    } else {
        $insertSession = $pdo->prepare(
            'INSERT INTO website_visits
                (visitor_id, page_url, page_title, device_type, browser,
                 operating_system, country, is_bot, visited_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $insertSession->execute([
            $visitorId,
            $pageUrl,
            $trackingTitle,
            $deviceType,
            $browser,
            $operatingSystem,
            $visitorCountry,
            $isBot ? 1 : 0
        ]);
    }

} catch (Throwable $e) {
    // Analytics must never interrupt the public website.
    error_log('[INS visitor tracking] ' . $e->getMessage());
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
    15%{width:12%;opacity:1}
    70%{width:100%;opacity:1}
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
