<?php
/**
 * IMAGINATION NATURE STUDIO
 * Web Push Diagnostic / Test
 *
 * File:
 * public_html/admin/push-test.php
 *
 * This page is intentionally diagnostic.
 * It tells us WHY a push send fails instead of returning only {"ok":false}.
 */

require_once __DIR__ . '/../includes/functions.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

function push_test_response(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_PRETTY_PRINT
    );
    exit;
}

try {

    /* ---------------------------------------------------------
       1. Check push configuration
       --------------------------------------------------------- */

    $pushConfig = __DIR__ . '/../includes/push-config.php';

    if (!is_file($pushConfig)) {
        push_test_response([
            'ok' => false,
            'step' => 'configuration',
            'message' => 'push-config.php was not found.',
            'path' => $pushConfig
        ], 500);
    }

    require_once $pushConfig;

    $missingConfig = [];

    foreach ([
        'IMS_VAPID_SUBJECT',
        'IMS_VAPID_PUBLIC_KEY',
        'IMS_VAPID_PRIVATE_KEY'
    ] as $constant) {
        if (!defined($constant) || trim((string)constant($constant)) === '') {
            $missingConfig[] = $constant;
        }
    }

    if ($missingConfig) {
        push_test_response([
            'ok' => false,
            'step' => 'configuration',
            'message' => 'VAPID configuration is incomplete.',
            'missing' => $missingConfig
        ], 500);
    }

    /* ---------------------------------------------------------
       2. Check Composer
       --------------------------------------------------------- */

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';

    if (!is_file($autoload)) {
        push_test_response([
            'ok' => false,
            'step' => 'composer',
            'message' => 'Composer vendor/autoload.php was not found.',
            'expected_path' => $autoload
        ], 500);
    }

    require_once $autoload;

    if (!class_exists('\Minishlink\WebPush\WebPush')) {
        push_test_response([
            'ok' => false,
            'step' => 'composer',
            'message' => 'Minishlink WebPush is not installed.',
            'hint' => 'Install minishlink/web-push in the public_html Composer environment.'
        ], 500);
    }

    if (!class_exists('\Minishlink\WebPush\Subscription')) {
        push_test_response([
            'ok' => false,
            'step' => 'composer',
            'message' => 'Minishlink WebPush Subscription class is missing.'
        ], 500);
    }

    /* ---------------------------------------------------------
       3. Check database/table
       --------------------------------------------------------- */

    $pdo = db();

    try {
        $tableExists = $pdo->query(
            "SELECT 1 FROM push_subscriptions LIMIT 1"
        );
    } catch (Throwable $e) {
        push_test_response([
            'ok' => false,
            'step' => 'database',
            'message' => 'The push_subscriptions table could not be read.',
            'error' => $e->getMessage()
        ], 500);
    }

    $rows = $pdo->query(
        "SELECT id, endpoint, p256dh, auth_token, content_encoding
         FROM push_subscriptions
         ORDER BY id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        push_test_response([
            'ok' => false,
            'step' => 'subscription',
            'message' => 'No push subscription is saved for this account/device.',
            'hint' => 'Open push-setup.php on the device and make sure it says Push notifications are ON.'
        ], 400);
    }

    /* ---------------------------------------------------------
       4. Prepare Web Push
       --------------------------------------------------------- */

    $auth = [
        'VAPID' => [
            'subject' => IMS_VAPID_SUBJECT,
            'publicKey' => IMS_VAPID_PUBLIC_KEY,
            'privateKey' => IMS_VAPID_PRIVATE_KEY,
        ],
    ];

    $webPush = new \Minishlink\WebPush\WebPush(
        $auth,
        [
            'TTL' => 86400,
            'urgency' => 'high',
        ]
    );

    $payload = json_encode([
        'title' => '🔔 Test Notification · Imagination Nature Studio',
        'body' => 'Web Push test is working on this device.',
        'icon' => '/assets/images/favicon.png',
        'badge' => '/assets/images/favicon.png',
        'url' => SITE_URL . '/admin/bookings.php',
        'tag' => 'ims-push-test-' . date('YmdHis'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $queued = 0;

    /* ---------------------------------------------------------
       5. Queue all saved subscriptions
       --------------------------------------------------------- */

    foreach ($rows as $row) {

        $endpoint = trim((string)($row['endpoint'] ?? ''));
        $p256dh   = trim((string)($row['p256dh'] ?? ''));
        $authKey  = trim((string)($row['auth_token'] ?? ''));

        if ($endpoint === '' || $p256dh === '' || $authKey === '') {
            continue;
        }

        try {

            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => $p256dh,
                    'auth' => $authKey,
                ],
                'contentEncoding' =>
                    !empty($row['content_encoding'])
                        ? $row['content_encoding']
                        : 'aes128gcm',
            ]);

            $webPush->queueNotification(
                $subscription,
                $payload
            );

            $queued++;

        } catch (Throwable $e) {

            push_test_response([
                'ok' => false,
                'step' => 'subscription',
                'message' => 'A saved push subscription is invalid.',
                'subscription_id' => (int)$row['id'],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    if ($queued === 0) {
        push_test_response([
            'ok' => false,
            'step' => 'subscription',
            'message' => 'No valid push subscription could be queued.'
        ], 400);
    }

    /* ---------------------------------------------------------
       6. Send and inspect the actual Web Push response
       --------------------------------------------------------- */

    $results = [];
    $sent = 0;
    $expired = 0;
    $failed = 0;

    foreach ($webPush->flush() as $report) {

        $endpoint = (string)$report->getEndpoint();

        if ($report->isSuccess()) {

            $sent++;

            $results[] = [
                'success' => true,
                'endpoint' => substr($endpoint, 0, 70) . '...'
            ];

        } elseif ($report->isSubscriptionExpired()) {

            $expired++;

            $results[] = [
                'success' => false,
                'expired' => true,
                'endpoint' => substr($endpoint, 0, 70) . '...',
                'reason' => $report->getReason()
            ];

            /* Remove dead subscription. */
            try {
                $delete = $pdo->prepare(
                    "DELETE FROM push_subscriptions
                     WHERE endpoint_hash = ?"
                );

                $delete->execute([
                    hash('sha256', $endpoint)
                ]);

            } catch (Throwable $e) {
                /* Do not hide the original push result. */
            }

        } else {

            $failed++;

            $results[] = [
                'success' => false,
                'expired' => false,
                'endpoint' => substr($endpoint, 0, 70) . '...',
                'reason' => $report->getReason()
            ];
        }
    }

    if ($sent > 0) {

        push_test_response([
            'ok' => true,
            'message' => 'Push notification was accepted by the push service.',
            'subscriptions_found' => count($rows),
            'queued' => $queued,
            'sent' => $sent,
            'expired' => $expired,
            'failed' => $failed,
            'results' => $results
        ]);

    }

    push_test_response([
        'ok' => false,
        'message' => 'The server could not send the push notification.',
        'subscriptions_found' => count($rows),
        'queued' => $queued,
        'sent' => $sent,
        'expired' => $expired,
        'failed' => $failed,
        'results' => $results
    ], 502);

} catch (Throwable $e) {

    push_test_response([
        'ok' => false,
        'step' => 'server',
        'message' => 'Unexpected Web Push server error.',
        'error' => $e->getMessage()
    ], 500);
}
