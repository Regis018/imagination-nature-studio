<?php
/**
 * Web Push configuration for Imagination Nature Studio.
 * KEEP THE PRIVATE KEY SECRET. Do not put this file in a public download folder.
 */

if (!defined('IMS_VAPID_SUBJECT')) {
    define('IMS_VAPID_SUBJECT', 'https://imaginationnaturestudio.com');
}

if (!defined('IMS_VAPID_PUBLIC_KEY')) {
    define('IMS_VAPID_PUBLIC_KEY', 'BAWfGFdufV0bGJeUQbYTDIb6P82Oige4qCFnyiDS_An0qaNt8of457r8sw6l_amMbdb1LMC0vGJ6_GZeIB9tV20');
}

if (!defined('IMS_VAPID_PRIVATE_KEY')) {
    define('IMS_VAPID_PRIVATE_KEY', '9J_bBkgDOTDb8cpmYjy7SJW_UQZ0C7vsmNOB0J5kwqY');
}
