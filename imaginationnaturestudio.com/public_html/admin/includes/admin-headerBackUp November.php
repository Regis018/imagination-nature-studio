<?php
require_once __DIR__ . '/../../includes/functions.php';
$imsPushConfig = __DIR__ . '/../../includes/push-config.php';
if (is_file($imsPushConfig)) {
    require_once $imsPushConfig;
}
require_login();

$cur         = basename($_SERVER['PHP_SELF']);
$isAdminUser = is_admin();
$me          = current_admin();

/*
 * STAFF ACCESS CONTROL
 * Staff may access ONLY these admin pages.
 * Admin keeps full access to the entire admin panel.
 */
$staffAllowedPages = [
    'daily-report.php',
    'weekly-report.php',
    'sales.php',
    'expenses.php',

    // Photo Selection — Staff is allowed to use it.
    'photo-selection.php',
    'photo-selection-view.php',
    'photo-selection-archived.php',

    'walkin-bookings.php',
    'products.php',
    'logout.php',
];

if (!$isAdminUser && !in_array($cur, $staffAllowedPages, true)) {
    header('Location: ' . SITE_URL . '/admin/daily-report.php');
    exit;
}

// Safe default for pages such as Google Reviews.
$configured = isset($configured) ? (bool)$configured : false;

$unreadMsg = 0;
try {
    $unreadMsg = (int)db()->query("SELECT COUNT(*) FROM messages WHERE is_read = 0")->fetchColumn();
} catch (Throwable $e) {}

// Pending booking notification badge
$pendingBookings = 0;
try {
    $pendingBookings = (int)db()->query(
        "SELECT COUNT(*) FROM bookings WHERE booking_status = 'pending'"
    )->fetchColumn();
} catch (Throwable $e) {}

$cssV = @filemtime(SITE_ROOT . '/assets/css/style.css') ?: time();
$favV = @filemtime(SITE_ROOT . '/assets/images/favicon.png') ?: time();
$logV = @filemtime(SITE_ROOT . '/assets/images/logo.png') ?: time();
?>
<!doctype html>
<html lang="en">

<head>

<meta charset="utf-8">

<title><?= e($pageTitle ?? 'Admin') ?> · INS</title>

<meta name="viewport" content="width=device-width,initial-scale=1">

<meta name="robots" content="noindex,nofollow">

<link
    rel="icon"
    href="<?= e(SITE_URL) ?>/assets/images/favicon.png?v=<?= e($favV) ?>"
>

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
>

<link
    href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="<?= e(SITE_URL) ?>/assets/css/style.css?v=<?= e($cssV) ?>"
>

<style>

/* =========================================================
   ADMIN TOP BAR + USER MENU
   ========================================================= */

.admin-topbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 60px;
    background: #0a0908;
    border-bottom: 1px solid #262220;
    display: flex;
    align-items: center;
    gap: .8rem;
    padding: 0 1.4rem;
    z-index: 90;
}

.admin-burger {
    display: none;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 5px;
    width: 40px;
    height: 40px;
    padding: 9px;
    background: transparent;
    border: 0;
    border-radius: 8px;
    color: var(--cream, #f3ece3);
    cursor: pointer;
    transition: background .2s;
}

.admin-burger:hover {
    background: rgba(255,255,255,.07);
}

.admin-burger span {
    display: block;
    width: 20px;
    height: 2px;
    background: currentColor;
    border-radius: 2px;
    transition: transform .3s, opacity .25s;
}

.admin-burger.is-active span:nth-child(1) {
    transform: translateY(7px) rotate(45deg);
}

.admin-burger.is-active span:nth-child(2) {
    opacity: 0;
}

.admin-burger.is-active span:nth-child(3) {
    transform: translateY(-7px) rotate(-45deg);
}

.admin-topbar-logo {
    display: flex;
    align-items: center;
    gap: .6rem;
    text-decoration: none;
    color: var(--cream, #f3ece3);
    flex-shrink: 0;
}

.admin-topbar-logo img {
    max-height: 38px;
    width: auto;
    display: block;
}

.admin-topbar-logo .logo-text {
    display: flex;
    flex-direction: column;
    line-height: 1.1;
}

.admin-topbar-logo .logo-text strong {
    font-family: 'Playfair Display', Georgia, serif;
    font-weight: 600;
    font-size: .95rem;
    letter-spacing: .02em;
}

.admin-topbar-logo .logo-text em {
    font-style: normal;
    font-size: .65rem;
    color: #7a716a;
    letter-spacing: .18em;
    text-transform: uppercase;
}

.admin-topbar-spacer {
    flex: 1;
}

.admin-topbar-link {
    color: #a79e95;
    font-size: .82rem;
    padding: .5rem .8rem;
    border-radius: 8px;
    text-decoration: none;
    transition: background .2s, color .2s;
    display: inline-flex;
    align-items: center;
    gap: .35rem;
}

.admin-topbar-link:hover {
    background: rgba(255,255,255,.05);
    color: var(--cream, #f3ece3);
}

/* ---------- User dropdown ---------- */

.admin-user {
    position: relative;
    flex-shrink: 0;
}

.admin-user-btn {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .35rem .7rem .35rem .35rem;
    background: rgba(255,255,255,.04);
    border: 1px solid #262220;
    border-radius: 999px;
    color: var(--cream, #f3ece3);
    cursor: pointer;
    font: inherit;
    font-size: .82rem;
    transition: background .2s, border-color .2s;
}

.admin-user-btn:hover {
    background: rgba(255,255,255,.08);
    border-color: #3a332e;
}

.admin-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent, #e07a2f), #a94d10);
    color: #14100d;
    display: grid;
    place-items: center;
    font-weight: 700;
    font-size: .78rem;
    letter-spacing: .02em;
    flex-shrink: 0;
}

.admin-user-name {
    max-width: 140px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-weight: 500;
}

.admin-user-chev {
    width: 10px;
    height: 10px;
    color: #7a716a;
    transition: transform .25s;
}

.admin-user.open .admin-user-chev {
    transform: rotate(180deg);
}

/* =========================================================
   DROPDOWN
   ========================================================= */

.admin-user-menu {
    display: none;
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    min-width: 240px;
    background: #171513;
    border: 1px solid #262220;
    border-radius: 12px;
    box-shadow: 0 20px 50px rgba(0,0,0,.55);
    padding: .5rem;
    z-index: 200;
}

.admin-user.open .admin-user-menu {
    display: block;
    animation: adminMenuIn .18s ease both;
}

@keyframes adminMenuIn {
    from {
        opacity: 0;
        transform: translateY(-6px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.admin-user-menu-head {
    padding: .7rem .8rem .6rem;
    border-bottom: 1px solid #262220;
    margin-bottom: .4rem;
}

.admin-user-menu-head strong {
    display: block;
    color: var(--cream, #f3ece3);
    font-size: .9rem;
    font-weight: 600;
}

.admin-user-menu-head em {
    display: block;
    color: #7a716a;
    font-style: normal;
    font-size: .75rem;
    margin-top: .15rem;
}

.admin-user-menu-head .badge {
    display: inline-block;
    margin-top: .5rem;
    padding: .18rem .55rem;
    background: rgba(224,122,47,.15);
    border: 1px solid rgba(224,122,47,.4);
    color: var(--accent-2, #f2a35e);
    border-radius: 999px;
    font-size: .68rem;
    letter-spacing: .1em;
    text-transform: uppercase;
    font-weight: 600;
}

.admin-user-menu a {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .65rem .8rem;
    border-radius: 8px;
    color: #d9d0c6;
    font-size: .87rem;
    text-decoration: none;
    transition: background .2s, color .2s;
}

.admin-user-menu a:hover {
    background: #221e1a;
    color: #fff;
}

.admin-user-menu a svg {
    width: 15px;
    height: 15px;
    opacity: .7;
    flex-shrink: 0;
}

.admin-user-menu a.danger {
    color: #f0b3b3;
}

.admin-user-menu a.danger:hover {
    background: rgba(200,60,60,.12);
    color: #ffc8c8;
}

/* Push content below top bar */

.admin-shell {
    padding-top: 60px;
}

.admin-topbar-brand {
    display: none;
}

/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 800px) {

    .admin-burger {
        display: flex;
    }

    .admin-topbar-logo .logo-text {
        display: none;
    }

    .admin-topbar-logo img {
        max-height: 32px;
    }

    .admin-topbar-link .txt {
        display: none;
    }

    .admin-topbar-link {
        padding: .5rem;
    }

    .admin-user-name {
        display: none;
    }

    .admin-user-btn {
        padding: .3rem;
    }

    .admin-user-chev {
        display: none;
    }

    .admin-side {
        position: fixed;
        top: 60px;
        left: 0;
        bottom: 0;
        width: 260px;
        max-width: 82vw;
        transform: translateX(-105%);
        transition: transform .3s ease;
        z-index: 100;
        overflow-y: auto;
    }

    .admin-side.is-open {
        transform: translateX(0);
        box-shadow: 6px 0 40px rgba(0,0,0,.6);
    }

    .admin-backdrop {
        display: block;
        position: fixed;
        inset: 60px 0 0 0;
        background: rgba(0,0,0,.6);
        opacity: 0;
        pointer-events: none;
        transition: opacity .25s ease;
        z-index: 95;
    }

    .admin-backdrop.is-open {
        opacity: 1;
        pointer-events: auto;
    }

    .admin-main {
        padding: 1.5rem 1rem 2rem;
    }

    body.admin-nav-open {
        overflow: hidden;
    }
}

@media (max-width: 480px) {

    .admin-topbar {
        padding: 0 .8rem;
    }

    .admin-main {
        padding: 1.2rem .8rem 2rem;
    }
}

/* =========================================================
   PREMIUM SIDEBAR NAVIGATION
   ========================================================= */

.admin-side {
    background: linear-gradient(
        180deg,
        #0a1220 0%,
        #08101c 55%,
        #060c15 100%
    );

    border-right: 1px solid rgba(255,255,255,.06);
}

.admin-side .admin-nav > a {
    position: relative;
    display: flex;
    align-items: center;
    gap: .75rem;
    min-height: 46px;
    margin: 4px 10px;
    padding: 11px 16px;
    border-radius: 12px;
    color: #f4f7ff !important;
    font-size: 15px;
    font-weight: 800 !important;
    letter-spacing: .01em;
    text-decoration: none;
    overflow: hidden;

    transition:
        transform .25s ease,
        background .25s ease,
        color .25s ease,
        box-shadow .25s ease,
        padding-left .25s ease;
}


/* Pending booking notification badge */
.admin-nav-badge {
    position: absolute;
    right: 42px;
    top: 50%;
    transform: translateY(-50%);
    min-width: 22px;
    height: 22px;
    padding: 0 7px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: #e53935;
    color: #fff !important;
    font-size: 11px;
    font-weight: 900;
    line-height: 1;
    box-sizing: border-box;
    z-index: 5;
    pointer-events: none;
    box-shadow: 0 0 0 2px rgba(229,57,53,.12), 0 5px 16px rgba(229,57,53,.28);
    animation: bookingBadgePulse 1.8s ease-in-out infinite;
}

@keyframes bookingBadgePulse {
    0%, 100% { transform: translateY(-50%) scale(1); }
    50% { transform: translateY(-50%) scale(1.08); }
}

.admin-side .admin-nav > a:hover .admin-nav-badge {
    background: #ff4d49;
}

.admin-side .admin-nav > a.active .admin-nav-badge {
    background: #fff;
    color: #d9231f !important;
    box-shadow: 0 0 0 2px rgba(255,255,255,.18), 0 5px 18px rgba(0,0,0,.25);
}

@media (max-width: 800px) {
    .admin-nav-badge {
        right: 38px;
        min-width: 21px;
        height: 21px;
        font-size: 10px;
    }
}

/* Arrow */

.admin-side .admin-nav > a:not(.admin-logout)::after {
    content: "›";
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%) translateX(-5px);
    font-size: 24px;
    line-height: 1;
    font-weight: 400;
    color: #8fa8cf;
    opacity: .65;

    transition:
        transform .25s ease,
        color .25s ease,
        opacity .25s ease;
}

/* Shine */

.admin-side .admin-nav > a:not(.admin-logout)::before {
    content: "";
    position: absolute;
    top: 0;
    left: -120%;
    width: 65%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255,255,255,.16),
        transparent
    );
    transform: skewX(-20deg);
    transition: left .55s ease;
    pointer-events: none;
}

.admin-side .admin-nav > a:hover {
    transform: translateX(5px);
    padding-left: 21px;
    background: linear-gradient(
        90deg,
        rgba(35,125,255,.20),
        rgba(35,125,255,.05)
    );
    color: #fff !important;

    box-shadow:
        0 6px 20px rgba(0,0,0,.20),
        inset 3px 0 0 #3b8cff;
}

.admin-side .admin-nav > a:hover::before {
    left: 135%;
}

.admin-side .admin-nav > a:hover::after {
    transform: translateY(-50%) translateX(2px);
    color: #fff;
    opacity: 1;
}

/* Active item */

.admin-side .admin-nav > a.active {
    transform: translateX(4px);
    color: #fff !important;
    font-weight: 900 !important;

    background: linear-gradient(
        90deg,
        #1769ff,
        #245eff 55%,
        #164dcc
    );

    box-shadow:
        0 8px 25px rgba(23,105,255,.35),
        inset 0 1px 0 rgba(255,255,255,.18);
}

.admin-side .admin-nav > a.active::after {
    color: #fff;
    opacity: 1;
    transform: translateY(-50%) translateX(2px);
}

.admin-side .admin-nav > a.active {
    animation: sidebarActiveGlow 2.2s ease-in-out infinite;
}

@keyframes sidebarActiveGlow {

    0%,100% {
        box-shadow:
            0 8px 22px rgba(23,105,255,.28),
            inset 0 1px 0 rgba(255,255,255,.18);
    }

    50% {
        box-shadow:
            0 10px 32px rgba(23,105,255,.50),
            inset 0 1px 0 rgba(255,255,255,.22);
    }
}

/* Active white indicator */

.admin-side .admin-nav > a.active::marker {
    content: "";
}

.admin-side .admin-nav > a.active {
    border-left: 3px solid #fff;
}

/* Section headings */

.admin-side .admin-nav > div[style*="text-transform:uppercase"] {
    color: #8ea1bd !important;
    font-weight: 900 !important;
    letter-spacing: .14em !important;
}

/* Log out */

.admin-side .admin-nav > a.admin-logout {
    font-weight: 900 !important;
}

/* Mobile */

@media (max-width: 800px) {

    .admin-side .admin-nav > a {
        font-size: 14px;
        min-height: 44px;
        margin: 3px 8px;
        padding: 11px 14px;
    }

    .admin-side .admin-nav > a:hover {
        transform: translateX(3px);
        padding-left: 17px;
    }
}

/* =========================================================
   LIVE BOOKING NOTIFICATIONS
   ========================================================= */
.admin-booking-notify {
    position: relative;
    flex-shrink: 0;
}

.admin-booking-notify-btn {
    position: relative;
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    border: 1px solid #262220;
    border-radius: 10px;
    background: rgba(255,255,255,.04);
    color: #f3ece3;
    cursor: pointer;
    transition: background .2s, border-color .2s, transform .2s;
}

.admin-booking-notify-btn:hover {
    background: rgba(255,255,255,.08);
    border-color: #3a332e;
    transform: translateY(-1px);
}

.admin-booking-notify-btn svg {
    width: 19px;
    height: 19px;
}

.admin-booking-notify-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: #e53935;
    color: #fff;
    font-size: 10px;
    line-height: 1;
    font-weight: 900;
    box-shadow: 0 0 0 2px #0a0908;
}

.admin-booking-notify-btn.has-new {
    animation: adminNotifyShake .8s ease;
}

@keyframes adminNotifyShake {
    0%,100% { transform: rotate(0); }
    20% { transform: rotate(-10deg); }
    40% { transform: rotate(10deg); }
    60% { transform: rotate(-7deg); }
    80% { transform: rotate(5deg); }
}

.admin-sound-label {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    white-space: nowrap;
    padding: .42rem .65rem;
    border: 1px solid #2b2724;
    border-radius: 8px;
    background: #171513;
    color: #d9d0c6;
    font-size: .68rem;
    box-shadow: 0 12px 28px rgba(0,0,0,.4);
    z-index: 210;
}

.admin-sound-label.is-on {
    color: #9ff0b2;
}

.admin-booking-toast {
    position: fixed;
    top: 74px;
    right: 18px;
    width: min(390px, calc(100vw - 36px));
    padding: 14px 15px;
    display: none;
    align-items: flex-start;
    gap: 11px;
    background: #151311;
    border: 1px solid rgba(229,57,53,.45);
    border-radius: 14px;
    color: #f3ece3;
    box-shadow: 0 18px 50px rgba(0,0,0,.5);
    z-index: 1000;
    cursor: pointer;
}

.admin-booking-toast.show {
    display: flex;
    animation: adminToastIn .28s ease both;
}

.admin-booking-toast .icon {
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: rgba(229,57,53,.14);
    font-size: 19px;
}

.admin-booking-toast strong {
    display: block;
    font-size: .9rem;
    margin-bottom: 3px;
}

.admin-booking-toast small {
    display: block;
    color: #a79e95;
    line-height: 1.45;
    font-size: .74rem;
}

@keyframes adminToastIn {
    from { opacity: 0; transform: translateY(-10px) translateX(12px); }
    to { opacity: 1; transform: translateY(0) translateX(0); }
}

@media (max-width: 800px) {
    .admin-booking-notify-btn {
        width: 38px;
        height: 38px;
    }

    .admin-booking-toast {
        top: 68px;
        right: 10px;
        width: calc(100vw - 20px);
    }
}


</style>

</head>

<body class="admin-body">



<div
    class="admin-booking-toast"
    id="adminBookingToast"
    role="alert"
    aria-live="assertive"
>
    <div class="icon">🔔</div>
    <div>
        <strong>New Booking Received</strong>
        <small id="adminBookingToastText">A client has just made a booking.</small>
    </div>
</div>



<!-- ============================================================
     TOP BAR
     ============================================================ -->

<header class="admin-topbar" id="adminTopbar">

    <!-- Mobile menu toggle -->

    <button
        class="admin-burger"
        id="adminNavToggle"
        type="button"
        aria-label="Toggle menu"
        aria-expanded="false"
    >
        <span></span>
        <span></span>
        <span></span>
    </button>


    <!-- Logo -->

    <a
        href="<?= e(SITE_URL) ?>/admin/dashboard.php"
        class="admin-topbar-logo"
    >

        <img
            src="<?= e(SITE_URL) ?>/assets/images/logo.png?v=<?= e($logV) ?>"
            alt="Imagination Nature Studio"
            onerror="this.style.display='none'"
        >

        <span class="logo-text">

            <strong>
                Imagination Nature
            </strong>

            <em>
                Admin Panel
            </em>

        </span>

    </a>


    <div class="admin-topbar-spacer"></div>


    <!-- View public site -->

    <a
        class="admin-topbar-link"
        href="<?= e(SITE_URL) ?>/"
        target="_blank"
        rel="noopener"
        title="Open public website"
    >

        <svg
            viewBox="0 0 24 24"
            width="15"
            height="15"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
        >

            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
            <polyline points="15 3 21 3 21 9"/>
            <line x1="10" y1="14" x2="21" y2="3"/>

        </svg>

        <span class="txt">
            View site
        </span>

    </a>






    <!-- Live booking notification -->
    <div class="admin-booking-notify" id="adminBookingNotify">
        <button
            class="admin-booking-notify-btn"
            id="adminBookingNotifyBtn"
            type="button"
            title="Enable booking sound"
            aria-label="Booking notifications"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                <path d="M10 21h4"/>
            </svg>
            <span class="admin-booking-notify-badge" id="adminBookingNotifyBadge" hidden>0</span>
        </button>
        <span class="admin-sound-label" id="adminSoundLabel">🔊 Click to enable sound</span>
    </div>


    <!-- User dropdown -->

    <div
        class="admin-user"
        id="adminUser"
    >

        <button
            class="admin-user-btn"
            type="button"
            id="adminUserBtn"
            aria-haspopup="true"
            aria-expanded="false"
        >

            <span class="admin-avatar">

                <?= e(
                    strtoupper(
                        mb_substr(
                            $me['name'] ?? 'U',
                            0,
                            1
                        )
                    )
                ) ?>

            </span>

            <span class="admin-user-name">
                <?= e($me['name'] ?? 'User') ?>
            </span>

            <svg
                class="admin-user-chev"
                viewBox="0 0 12 12"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >

                <polyline points="3 4.5 6 7.5 9 4.5"/>

            </svg>

        </button>


        <div
            class="admin-user-menu"
            id="adminUserMenu"
            role="menu"
        >

            <div class="admin-user-menu-head">

                <strong>
                    <?= e($me['name'] ?? 'User') ?>
                </strong>

                <em>
                    <?= e($me['email'] ?? '') ?>
                </em>

                <span class="badge">
                    <?= $isAdminUser ? 'Administrator' : 'Staff' ?>
                </span>

                <?php if (!empty($me['staff_title'])): ?>

                    <em style="margin-top:.4rem">
                        <?= e($me['staff_title']) ?>
                    </em>

                <?php endif; ?>

            </div>


            <?php if ($isAdminUser): ?>

                <a href="<?= e(SITE_URL) ?>/admin/dashboard.php">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >

                        <rect x="3" y="3" width="7" height="7"/>
                        <rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/>
                        <rect x="3" y="14" width="7" height="7"/>

                    </svg>

                    Dashboard

                </a>

            <?php endif; ?>


            <?php if ($isAdminUser): ?>

                <a href="<?= e(SITE_URL) ?>/admin/settings.php">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >

                        <circle cx="12" cy="12" r="3"/>

                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>

                    </svg>

                    Settings

                </a>

            <?php endif; ?>


            <a
                href="<?= e(SITE_URL) ?>/admin/logout.php"
                class="danger"
            >

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >

                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>

                </svg>

                Log out

            </a>

        </div>

    </div>

</header>


<!-- ============================================================
     SHELL
     ============================================================ -->

<div class="admin-shell">

    <aside
        class="admin-side"
        id="adminSidebar"
    >

        <nav class="admin-nav">

        <?php if ($isAdminUser): ?>

            <a
                href="dashboard.php"
                class="<?= $cur==='dashboard.php' ? 'active' : '' ?>"
            >
                Dashboard
            </a>


            <a
                href="bookings.php"
                class="<?= $cur==='bookings.php' ? 'active' : '' ?>"
            >
                <span>Bookings</span>

                <?php if ($pendingBookings > 0): ?>
                    <span
                        class="admin-nav-badge"
                        aria-label="<?= (int)$pendingBookings ?> pending bookings"
                    >
                        <?= (int)$pendingBookings > 99 ? '99+' : (int)$pendingBookings ?>
                    </span>
                <?php endif; ?>
            </a>


            <a
                href="customers.php"
                class="<?= in_array($cur, ['customers.php', 'customer-view.php'], true) ? 'active' : '' ?>"
            >
                👥 Customers
            </a>


            <a
                href="calendar.php"
                class="<?= $cur==='calendar.php' ? 'active' : '' ?>"
            >
                Calendar
            </a>


            <a
                href="services.php"
                class="<?= $cur==='services.php' ? 'active' : '' ?>"
            >
                Services
            </a>


            <a
                href="packages.php"
                class="<?= $cur==='packages.php' ? 'active' : '' ?>"
            >
                Packages
            </a>


            <a
                href="quotations.php"
                class="<?= $cur==='quotations.php' ? 'active' : '' ?>"
            >
                Quotations
            </a>


            <!-- =================================================
                 EXISTING GALLERY
                 ================================================= -->

            <a
                href="gallery.php"
                class="<?= $cur==='gallery.php' ? 'active' : '' ?>"
            >
                Gallery
            </a>


            <!-- =================================================
                 NEW PHOTO SELECTION
                 ================================================= -->

            <a
                href="photo-selection.php"
                class="<?= $cur==='photo-selection.php' ? 'active' : '' ?>"
            >
                📸 Photo Selection
            </a>

            <a
                href="photo-orders.php"
                class="<?= $cur==='photo-orders.php' ? 'active' : '' ?>"
            >
                🖨️ Photo Orders
            </a>


            <a
                href="promotions.php"
                class="<?= $cur==='promotions.php' ? 'active' : '' ?>"
            >
                🎯 Promotions
            </a>


            <a
                href="homepage.php"
                class="<?= $cur==='homepage.php' ? 'active' : '' ?>"
            >
                Homepage
            </a>


            <a
                href="translation-manager.php"
                class="<?= $cur==='translation-manager.php' ? 'active' : '' ?>"
            >
                🌐 Translation Manager
            </a>


            <a
                href="settings.php"
                class="<?= $cur==='settings.php' ? 'active' : '' ?>"
            >
                Settings
            </a>


            <a
                href="users.php"
                class="<?= $cur==='users.php' ? 'active' : '' ?>"
            >
                Users
            </a>


            <div
                style="
                    margin:1.1rem 0 .4rem;
                    padding:.3rem .9rem;
                    font-size:.66rem;
                    letter-spacing:.18em;
                    text-transform:uppercase;
                    color:#4a4340
                "
            >
                Security
            </div>

            <a
                href="security/sessions.php"
                class="<?= in_array($cur, ['sessions.php', 'force-logout.php', 'logout-all.php'], true) ? 'active' : '' ?>"
            >
                🔐 Active Devices
            </a>

            <a
                href="security/manage-users.php"
                class="<?= $cur==='manage-users.php' ? 'active' : '' ?>"
            >
                👥 Staff Security
            </a>

            <a
                href="security/change-password.php"
                class="<?= $cur==='change-password.php' ? 'active' : '' ?>"
            >
                🔑 Change Password
            </a>


            <div
                style="
                    margin:1.1rem 0 .4rem;
                    padding:.3rem .9rem;
                    font-size:.66rem;
                    letter-spacing:.18em;
                    text-transform:uppercase;
                    color:#4a4340
                "
            >
                Daily Operations
            </div>


            <a
                href="daily-report.php"
                class="<?= $cur==='daily-report.php' ? 'active' : '' ?>"
            >
                Daily Report
            </a>


            <a
                href="website-visitors.php"
                class="<?= $cur==='website-visitors.php' ? 'active' : '' ?>"
            >
                Website Visitors
            </a>


            <a
                href="weekly-report.php"
                class="<?= $cur==='weekly-report.php' ? 'active' : '' ?>"
            >
                Weekly Report
            </a>


            <a
                href="sales.php"
                class="<?= $cur==='sales.php' ? 'active' : '' ?>"
            >
                Sales
            </a>


            <a
                href="expenses.php"
                class="<?= $cur==='expenses.php' ? 'active' : '' ?>"
            >
                Expenses
            </a>

            <a
                href="transactions.php"
                class="<?= $cur==='transactions.php' ? 'active' : '' ?>"
            >
                💰 Transactions
            </a>


            <a
                href="profit-loss.php"
                class="<?= $cur==='profit-loss.php' ? 'active' : '' ?>"
            >
                📊 Profit & Loss
            </a>


            <a
                href="finance-planning.php"
                class="<?= $cur==='finance-planning.php' ? 'active' : '' ?>"
            >
                📈 Finance Planning
            </a>


            <a
                href="walkin-bookings.php"
                class="<?= $cur==='walkin-bookings.php' ? 'active' : '' ?>"
            >
                Walk-in Bookings
            </a>


            <a
                href="products.php"
                class="<?= $cur==='products.php' ? 'active' : '' ?>"
            >
                Products
            </a>

            <!-- STOCK MANAGEMENT — ADMIN ONLY -->
            <a
                href="stock.php"
                class="<?= $cur==='stock.php' ? 'active' : '' ?>"
            >
                📦 Stock Management
            </a>

            <a
                href="stock-purchase.php"
                class="<?= $cur==='stock-purchase.php' ? 'active' : '' ?>"
            >
                🛒 Stock Purchase
            </a>

            <a
                href="stock-history.php"
                class="<?= $cur==='stock-history.php' ? 'active' : '' ?>"
            >
                📜 Stock History
            </a>


            <a
                href="monthly-report.php"
                class="<?= $cur==='monthly-report.php' ? 'active' : '' ?>"
            >
                Monthly Report
            </a>


            <div
                style="
                    margin:1.1rem 0 .4rem;
                    padding:.3rem .9rem;
                    font-size:.66rem;
                    letter-spacing:.18em;
                    text-transform:uppercase;
                    color:#4a4340
                "
            >
                Team
            </div>


            <a
                href="messages.php"
                class="<?= $cur==='messages.php' ? 'active' : '' ?>"
            >

                Messages

                <?php if ($unreadMsg > 0): ?>

                    <span
                        style="
                            background:var(--accent, #e07a2f);
                            color:#14100d;
                            border-radius:999px;
                            padding:0 .5rem;
                            font-size:.68rem;
                            font-weight:700;
                            margin-left:.4rem
                        "
                    >
                        <?= (int)$unreadMsg ?>
                    </span>

                <?php endif; ?>

            </a>


            <a
                href="logout.php"
                class="admin-logout"
            >
                Log out
            </a>


        <?php else: ?>

            <div
                style="
                    margin:1.1rem 0 .4rem;
                    padding:.3rem .9rem;
                    font-size:.66rem;
                    letter-spacing:.18em;
                    text-transform:uppercase;
                    color:#4a4340
                "
            >
                Daily Operations
            </div>


            <a
                href="daily-report.php"
                class="<?= $cur==='daily-report.php' ? 'active' : '' ?>"
            >
                Daily Report
            </a>


            <a
                href="weekly-report.php"
                class="<?= $cur==='weekly-report.php' ? 'active' : '' ?>"
            >
                Weekly Report
            </a>


            <!-- Photo Selection — available to Staff -->
            <a
                href="photo-selection.php"
                class="<?= in_array($cur, ['photo-selection.php', 'photo-selection-view.php', 'photo-selection-archived.php'], true) ? 'active' : '' ?>"
            >
                📸 Photo Selection
            </a>


            <a
                href="sales.php"
                class="<?= $cur==='sales.php' ? 'active' : '' ?>"
            >
                Sales
            </a>


            <a
                href="expenses.php"
                class="<?= $cur==='expenses.php' ? 'active' : '' ?>"
            >
                Expenses
            </a>


            <a
                href="walkin-bookings.php"
                class="<?= $cur==='walkin-bookings.php' ? 'active' : '' ?>"
            >
                Walk-in Bookings
            </a>


            <a
                href="products.php"
                class="<?= $cur==='products.php' ? 'active' : '' ?>"
            >
                Products
            </a>


            <a
                href="logout.php"
                class="admin-logout"
            >
                Log out
            </a>


        <?php endif; ?>

        </nav>

    </aside>


    <div
        class="admin-backdrop"
        id="adminBackdrop"
    >
    </div>


    <main class="admin-main">

<script>
(function () {
    'use strict';

    const endpoint = <?= json_encode(SITE_URL . '/admin/booking-notifications.php') ?>;
    const bookingsUrl = <?= json_encode(SITE_URL . '/admin/bookings.php') ?>;
    const pushSubscribeUrl = <?= json_encode(SITE_URL . '/admin/push-subscribe.php') ?>;
    const pushServiceWorkerUrl = <?= json_encode(SITE_URL . '/sw.js') ?>;
    const vapidPublicKey = <?= json_encode(defined('IMS_VAPID_PUBLIC_KEY') ? IMS_VAPID_PUBLIC_KEY : '') ?>;

    const btn = document.getElementById('adminBookingNotifyBtn');
    const badge = document.getElementById('adminBookingNotifyBadge');
    const label = document.getElementById('adminSoundLabel');
    const toast = document.getElementById('adminBookingToast');
    const toastText = document.getElementById('adminBookingToastText');

    if (!btn || !badge || !label) return;

    const STORAGE_KEY = 'ims_last_booking_notification_id';
    let soundEnabled = false;
    let audioContext = null;
    let firstCheck = true;
    let lastId = parseInt(localStorage.getItem(STORAGE_KEY) || '0', 10) || 0;
    let toastTimer = null;

    function setSoundLabel() {
        if (soundEnabled) {
            label.textContent = '🔊 Sound is ON';
            label.classList.add('is-on');
            btn.title = 'Booking sound is enabled';
        } else {
            label.textContent = '🔊 Click to enable sound';
            label.classList.remove('is-on');
            btn.title = 'Enable booking sound';
        }
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');
        const rawData = window.atob(base64);
        return Uint8Array.from([...rawData].map(function (char) {
            return char.charCodeAt(0);
        }));
    }

    async function enablePushNotifications() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            label.textContent = '📱 Push notifications are not supported here';
            return;
        }

        if (!vapidPublicKey) {
            label.textContent = '📱 Push is not configured yet';
            return;
        }

        try {
            const registration = await navigator.serviceWorker.register(pushServiceWorkerUrl, {
                scope: '/'
            });

            let permission = Notification.permission;
            if (permission === 'default') {
                permission = await Notification.requestPermission();
            }

            if (permission !== 'granted') {
                label.textContent = '📱 Allow notifications on this device';
                return;
            }

            let subscription = await registration.pushManager.getSubscription();

            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
                });
            }

            const json = subscription.toJSON();
            json.contentEncoding =
                (registration.pushManager.supportedContentEncodings || ['aes128gcm'])[0] || 'aes128gcm';

            const response = await fetch(pushSubscribeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(json)
            });

            const result = await response.json();

            if (!response.ok || !result.ok) {
                throw new Error('Could not save push subscription');
            }

            label.textContent = '🔊 Sound + 📱 iPhone notifications ON';
            label.classList.add('is-on');
        } catch (e) {
            label.textContent = '📱 Push setup failed — check iPhone permissions';
        }
    }

    function enableSound() {
        try {
            const AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return;

            if (!audioContext) {
                audioContext = new AC();
            }

            if (audioContext.state === 'suspended') {
                audioContext.resume();
            }

            // Short silent/very soft initialization beep to unlock the audio context.
            const osc = audioContext.createOscillator();
            const gain = audioContext.createGain();
            gain.gain.value = 0.0001;
            osc.connect(gain);
            gain.connect(audioContext.destination);
            osc.start();
            osc.stop(audioContext.currentTime + 0.03);

            soundEnabled = true;
            localStorage.setItem('ims_booking_sound_enabled', '1');
            setSoundLabel();
        } catch (e) {}
    }

    function playBookingSound() {
        if (!soundEnabled || !audioContext) return;

        try {
            if (audioContext.state === 'suspended') {
                audioContext.resume();
            }

            const now = audioContext.currentTime;

            [0, 0.65, 1.30].forEach(function (delay) {
                const osc = audioContext.createOscillator();
                const gain = audioContext.createGain();

                osc.type = 'sine';
                osc.frequency.value = 880;

                gain.gain.setValueAtTime(0.0001, now + delay);
                gain.gain.exponentialRampToValueAtTime(0.25, now + delay + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + delay + 0.25);

                osc.connect(gain);
                gain.connect(audioContext.destination);

                osc.start(now + delay);
                osc.stop(now + delay + 0.28);
            });
        } catch (e) {}
    }

    function showToast(booking) {
        if (!toast || !toastText) return;

        const name = booking.customer_name || 'A client';
        const service = booking.service_name || 'New booking';
        const number = booking.booking_number ? ' · ' + booking.booking_number : '';

        toastText.textContent = name + ' · ' + service + number;
        toast.classList.add('show');

        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            toast.classList.remove('show');
        }, 9000);
    }

    function updateBadge(count) {
        count = parseInt(count || 0, 10) || 0;

        if (count > 0) {
            badge.hidden = false;
            badge.textContent = count > 99 ? '99+' : String(count);
        } else {
            badge.hidden = true;
        }
    }

    async function checkBookings() {
        try {
            const response = await fetch(endpoint + '?_=' + Date.now(), {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) return;

            const data = await response.json();
            if (!data || !data.ok) return;

            updateBadge(data.pending_count);

            const latest = data.latest;
            if (!latest || !latest.id) {
                firstCheck = false;
                return;
            }

            const currentId = parseInt(latest.id, 10) || 0;

            // First page load establishes the baseline and does not make a sound.
            if (firstCheck) {
                if (currentId > lastId) {
                    lastId = currentId;
                    localStorage.setItem(STORAGE_KEY, String(lastId));
                }
                firstCheck = false;
                return;
            }

            // A higher booking ID means a new booking was created.
            if (currentId > lastId) {
                lastId = currentId;
                localStorage.setItem(STORAGE_KEY, String(lastId));

                btn.classList.remove('has-new');
                void btn.offsetWidth;
                btn.classList.add('has-new');

                playBookingSound();
                showToast(latest);

                if ('Notification' in window && Notification.permission === 'granted') {
                    try {
                        new Notification('New Booking · Imagination Nature Studio', {
                            body: (latest.customer_name || 'A client') +
                                  ' · ' +
                                  (latest.service_name || 'New booking'),
                            icon: <?= json_encode(SITE_URL . '/assets/images/favicon.png') ?>
                        });
                    } catch (e) {}
                }
            }
        } catch (e) {
            // Do not disturb the admin panel if notification polling fails.
        }
    }

    btn.addEventListener('click', function () {
        enableSound();
        enablePushNotifications();

        if ('Notification' in window && Notification.permission === 'default') {
            try {
                Notification.requestPermission();
            } catch (e) {}
        }

        checkBookings();
    });

    if (toast) {
        toast.addEventListener('click', function () {
            window.location.href = bookingsUrl;
        });
    }

    // Restore sound preference, but browser audio still needs a user gesture
    // after a fresh tab/session before actual playback is allowed.
    if (localStorage.getItem('ims_booking_sound_enabled') === '1') {
        label.textContent = '🔊 Click bell to activate sound';
    }

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(pushServiceWorkerUrl, { scope: '/' }).catch(function () {});
    }

    checkBookings();
    setInterval(checkBookings, 8000);
})();
</script>
