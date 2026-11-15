<?php
/**
 * Imagination Nature Studio — Global Configuration
 */

/* -------------------------------------------------
   Environment
   ------------------------------------------------- */
define('ENVIRONMENT', 'development');

if (ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}


/* -------------------------------------------------
   Database
   ------------------------------------------------- */
define('DB_HOST', 'localhost');
define('DB_NAME', 'imagination_db');
define('DB_USER', 'imagination_db');

/*
 * Database password
 */
define('DB_PASS', 'WvhvtIxyL-AS(@C0');

define('DB_CHARSET', 'utf8mb4');


/* -------------------------------------------------
   Site
   ------------------------------------------------- */
define('SITE_NAME', 'Imagination Nature Studio');
define('SITE_TAGLINE', 'Stories you can feel.');

// Auto-detect local dev vs production
$_siteHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'imaginationnaturestudio.com';
$_isLocal  = in_array($_siteHost, ['localhost', '127.0.0.1'], true)
          || str_starts_with($_siteHost, 'localhost:');
define('SITE_URL', $_isLocal
    ? 'http://' . $_siteHost
    : 'https://imaginationnaturestudio.com'
);
unset($_siteHost, $_isLocal);
define('SITE_ROOT', __DIR__);

define('UPLOAD_DIR', SITE_ROOT . '/uploads/');
define('RECEIPT_DIR', SITE_ROOT . '/receipts/');
define('UPLOAD_URL', SITE_URL . '/uploads/');
define('RECEIPT_URL', SITE_URL . '/receipts/');


/* -------------------------------------------------
   Business
   ------------------------------------------------- */
define('BIZ_ADDRESS', 'Kiyovu, Kigali, Rwanda');
define('BIZ_PHONE_1', '+250 788 577 033');
define('BIZ_PHONE_2', '+250 782 156 008');
define('BIZ_WHATSAPP', '250788577033');
define('BIZ_EMAIL', 'info@imaginationnaturestudio.com');
define('BIZ_TIN', '111997931');
define('BIZ_HOURS', 'Monday – Saturday, 09:00 – 21:00');


/* -------------------------------------------------
   Social
   ------------------------------------------------- */
define(
    'SOCIAL_INSTAGRAM',
    'https://www.instagram.com/imaginationnature_studio/'
);

define(
    'SOCIAL_TIKTOK',
    'https://www.tiktok.com/@imaginationnaturestudio'
);


/* -------------------------------------------------
   Email — SMTP
   ------------------------------------------------- */
define('MAIL_ENABLED', true);
define('MAIL_HOST', 'localhost');
define('MAIL_PORT', 587);
define('MAIL_USER', 'admin@imaginationnaturestudio.com');

define('MAIL_PASS', '');

define('MAIL_SECURE', 'tls');
define('MAIL_FROM', 'admin@imaginationnaturestudio.com');
define('MAIL_FROM_NAME', 'Imagination Nature Studio');
define('ADMIN_NOTIFY_EMAIL', 'admin@imaginationnaturestudio.com');


/* -------------------------------------------------
   MTN MoMo Collections — SANDBOX
   PAYMENT IS PAUSED FOR NOW
   ------------------------------------------------- */
define(
    'MTN_MOMO_BASE_URL',
    'https://sandbox.momodeveloper.mtn.com'
);

define(
    'MTN_MOMO_TARGET_ENV',
    'sandbox'
);

define(
    'MTN_MOMO_CURRENCY',
    'EUR'
);


/* -------------------------------------------------
   MTN Credentials
   Leave empty for now.
   ------------------------------------------------- */
define(
    'MTN_MOMO_SUBSCRIPTION_KEY',
    ''
);

define(
    'MTN_MOMO_API_USER',
    ''
);

define(
    'MTN_MOMO_API_KEY',
    ''
);

define(
    'MTN_MOMO_USE_TRANSFER_TYPE',
    true
);

define(
    'MTN_MOMO_TRANSFER_TYPE',
    'CUSTOM_PAYMENT'
);

define(
    'MTN_MOMO_CALLBACK_URL',
    ''
);


/* -------------------------------------------------
   Session
   ------------------------------------------------- */
if (session_status() === PHP_SESSION_NONE) {

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (
            !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off'
        ),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}


/* -------------------------------------------------
   Error Log
   ------------------------------------------------- */
define(
    'ERROR_LOG_FILE',
    SITE_ROOT . '/error.log'
);


/* -------------------------------------------------
   Timezone
   ------------------------------------------------- */
date_default_timezone_set('Africa/Kigali');