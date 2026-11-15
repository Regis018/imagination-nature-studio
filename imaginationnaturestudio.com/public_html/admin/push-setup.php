<?php
/**
 * IMAGINATION NATURE STUDIO
 * Admin Push Notification Setup
 *
 * File:
 * public_html/admin/push-setup.php
 */

require_once __DIR__ . '/../includes/functions.php';

$pushConfig = __DIR__ . '/../includes/push-config.php';
if (is_file($pushConfig)) {
    require_once $pushConfig;
}

require_login();

$pageTitle = 'Push Notifications';
$vapidPublicKey = defined('IMS_VAPID_PUBLIC_KEY') ? (string) IMS_VAPID_PUBLIC_KEY : '';

$siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
$serviceWorkerUrl = $siteUrl . '/sw.js';
$subscribeUrl = $siteUrl . '/admin/push-subscribe.php';
$dashboardUrl = $siteUrl . '/admin/dashboard.php';

$me = function_exists('current_admin') ? (current_admin() ?: []) : [];
$userName = $me['name'] ?? 'Admin';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= e($pageTitle) ?> · INS</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">

    <link rel="icon"
          href="<?= e($siteUrl) ?>/assets/images/favicon.png">

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at top right, rgba(42,108,255,.16), transparent 32%),
                #080b10;
            color: #f5f1eb;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .wrap {
            width: min(720px, calc(100% - 28px));
            margin: 0 auto;
            padding: 30px 0 50px;
        }

        .top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 22px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #fff;
            text-decoration: none;
        }

        .brand img {
            width: 44px;
            height: 44px;
            object-fit: contain;
        }

        .brand strong {
            display: block;
            font-size: 17px;
        }

        .brand small {
            color: #8d98a8;
            display: block;
            margin-top: 3px;
        }

        .back {
            color: #dbe5f4;
            text-decoration: none;
            border: 1px solid #283241;
            border-radius: 10px;
            padding: 9px 12px;
            font-size: 13px;
        }

        .card {
            background: rgba(17,22,30,.96);
            border: 1px solid #283241;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 24px 70px rgba(0,0,0,.35);
        }

        h1 {
            margin: 0 0 10px;
            font-size: clamp(25px, 5vw, 36px);
        }

        .intro {
            color: #aeb8c7;
            line-height: 1.65;
            margin: 0 0 22px;
        }

        .status {
            border: 1px solid #303b4b;
            background: #0c1118;
            border-radius: 14px;
            padding: 15px;
            margin-bottom: 18px;
        }

        .status strong {
            display: block;
            margin-bottom: 5px;
        }

        .status span {
            color: #aeb8c7;
            line-height: 1.5;
            font-size: 14px;
        }

        .status.ok {
            border-color: rgba(52,211,153,.5);
            background: rgba(16,185,129,.08);
        }

        .status.error {
            border-color: rgba(248,113,113,.5);
            background: rgba(239,68,68,.08);
        }

        .status.warn {
            border-color: rgba(251,191,36,.5);
            background: rgba(245,158,11,.08);
        }

        .steps {
            display: grid;
            gap: 10px;
            margin: 20px 0;
        }

        .step {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            padding: 13px;
            border-radius: 12px;
            background: #0d131b;
            border: 1px solid #202b39;
        }

        .num {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: #1769ff;
            color: white;
            font-weight: 800;
            flex: 0 0 28px;
        }

        .step b {
            display: block;
            margin-bottom: 3px;
        }

        .step span {
            color: #9ba6b5;
            font-size: 13px;
            line-height: 1.5;
        }

        button {
            width: 100%;
            border: 0;
            border-radius: 13px;
            padding: 15px 18px;
            background: #1769ff;
            color: white;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
        }

        button:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        .secondary {
            display: block;
            text-align: center;
            margin-top: 12px;
            color: #aeb8c7;
            text-decoration: none;
            font-size: 14px;
        }

        .technical {
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid #222c38;
            color: #7f8a99;
            font-size: 12px;
            line-height: 1.7;
            word-break: break-word;
        }

        code {
            color: #dbe8ff;
        }
    </style>
</head>

<body>
<div class="wrap">

    <div class="top">
        <a class="brand" href="<?= e($dashboardUrl) ?>">
            <img src="<?= e($siteUrl) ?>/assets/images/logo.png" alt="Imagination Nature Studio">
            <span>
                <strong>Imagination Nature Studio</strong>
                <small>Admin Push Notifications</small>
            </span>
        </a>

        <a class="back" href="<?= e($dashboardUrl) ?>">Dashboard</a>
    </div>

    <div class="card">
        <h1>📱 Push Notifications</h1>

        <p class="intro">
            <?= e($userName) ?>, setup this device once. After the subscription is saved,
            new bookings can be sent directly to this device even when the admin page is not open,
            provided the device has an internet connection and notifications are allowed.
        </p>

        <div id="status" class="status">
            <strong id="statusTitle">Ready to set up</strong>
            <span id="statusText">
                Press the button below to register this device for booking notifications.
            </span>
        </div>

        <div class="steps">
            <div class="step">
                <div class="num">1</div>
                <div>
                    <b>Allow notifications</b>
                    <span>When iPhone asks for permission, choose Allow.</span>
                </div>
            </div>

            <div class="step">
                <div class="num">2</div>
                <div>
                    <b>Register this device</b>
                    <span>The device subscription is saved securely on the studio server.</span>
                </div>
            </div>

            <div class="step">
                <div class="num">3</div>
                <div>
                    <b>Receive new booking alerts</b>
                    <span>When a new booking is created, the server can send a push notification to this device.</span>
                </div>
            </div>
        </div>

        <button id="setupBtn" type="button">
            Enable Push Notifications
        </button>

        <a class="secondary" href="<?= e($dashboardUrl) ?>">
            ← Back to Admin Dashboard
        </a>

        <div class="technical">
            <b>Device:</b> <span id="deviceInfo">Checking…</span><br>
            <b>Service Worker:</b> <code><?= e($serviceWorkerUrl) ?></code><br>
            <b>Subscription endpoint:</b> <code><?= e($subscribeUrl) ?></code>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const setupBtn = document.getElementById('setupBtn');
    const statusBox = document.getElementById('status');
    const statusTitle = document.getElementById('statusTitle');
    const statusText = document.getElementById('statusText');
    const deviceInfo = document.getElementById('deviceInfo');

    const vapidPublicKey = <?= json_encode($vapidPublicKey) ?>;
    const serviceWorkerUrl = <?= json_encode($serviceWorkerUrl) ?>;
    const subscribeUrl = <?= json_encode($subscribeUrl) ?>;

    function setStatus(title, text, type) {
        statusBox.className = 'status' + (type ? ' ' + type : '');
        statusTitle.textContent = title;
        statusText.textContent = text;
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);

        return Uint8Array.from(
            Array.from(rawData).map(function (char) {
                return char.charCodeAt(0);
            })
        );
    }

    function detectDevice() {
        const ua = navigator.userAgent || '';
        const standalone =
            window.matchMedia &&
            window.matchMedia('(display-mode: standalone)').matches;

        const iosStandalone =
            ('standalone' in window.navigator) && window.navigator.standalone;

        if (/iPhone|iPad|iPod/i.test(ua)) {
            deviceInfo.textContent =
                'iPhone/iPad · ' +
                ((standalone || iosStandalone) ? 'Home Screen app' : 'Safari browser');
        } else {
            deviceInfo.textContent = 'Browser/device detected';
        }
    }

    async function setupPush() {
        setupBtn.disabled = true;
        setupBtn.textContent = 'Setting up…';

        try {
            if (!window.isSecureContext) {
                throw new Error('This page must be opened through HTTPS.');
            }

            if (!('serviceWorker' in navigator)) {
                throw new Error('Service Worker is not supported on this device/browser.');
            }

            if (!('PushManager' in window)) {
                throw new Error(
                    'Web Push is not available in this browser context. On iPhone, open the site from the Home Screen web app.'
                );
            }

            if (!('Notification' in window)) {
                throw new Error('Notifications are not available in this browser context.');
            }

            if (!vapidPublicKey) {
                throw new Error(
                    'VAPID public key is missing. Check includes/push-config.php.'
                );
            }

            setStatus(
                'Step 1 of 3',
                'Registering the notification service…',
                ''
            );

            const registration = await navigator.serviceWorker.register(
                serviceWorkerUrl,
                { scope: '/' }
            );

            await navigator.serviceWorker.ready;

            let permission = Notification.permission;

            if (permission === 'default') {
                permission = await Notification.requestPermission();
            }

            if (permission !== 'granted') {
                throw new Error(
                    'Notification permission was not granted. On iPhone, enable Notifications for this Home Screen app in Settings.'
                );
            }

            setStatus(
                'Step 2 of 3',
                'Creating this device notification subscription…',
                ''
            );

            let subscription = await registration.pushManager.getSubscription();

            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
                });
            }

            const json = subscription.toJSON();

            json.contentEncoding =
                (registration.pushManager.supportedContentEncodings || ['aes128gcm'])[0]
                || 'aes128gcm';

            setStatus(
                'Step 3 of 3',
                'Saving the device subscription on the studio server…',
                ''
            );

            const response = await fetch(subscribeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(json)
            });

            const raw = await response.text();

            let result = null;

            try {
                result = JSON.parse(raw);
            } catch (e) {
                throw new Error(
                    'The subscription server returned an invalid response. HTTP ' +
                    response.status
                );
            }

            if (!response.ok || !result.ok) {
                throw new Error(
                    result.message ||
                    result.error ||
                    'The device subscription could not be saved.'
                );
            }

            setStatus(
                'Push notifications are ON ✅',
                'This device is now registered. You should not need to enable it again unless the subscription or notification permission is removed.',
                'ok'
            );

            setupBtn.textContent = 'Push Notifications Enabled';
            setupBtn.disabled = true;

        } catch (error) {
            console.error('Push setup error:', error);

            setStatus(
                'Push setup could not be completed',
                error && error.message
                    ? error.message
                    : 'An unknown error occurred.',
                'error'
            );

            setupBtn.disabled = false;
            setupBtn.textContent = 'Try Again';
        }
    }

    detectDevice();

    setupBtn.addEventListener('click', setupPush);

})();
</script>

</body>
</html>
