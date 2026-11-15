<?php
/**
 * Imagination Nature Studio - Web Push sender.
 * Requires minishlink/web-push via Composer.
 */

function ims_push_log($message): void
{
    if (defined('ERROR_LOG_FILE') && ERROR_LOG_FILE) {
        @error_log('[web-push] ' . $message . "\n", 3, ERROR_LOG_FILE);
    } else {
        @error_log('[web-push] ' . $message);
    }
}

function ims_push_send(string $title, string $body, string $url = '/admin/bookings.php'): bool
{
    try {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            ims_push_log('Composer vendor/autoload.php not found.');
            return false;
        }

        require_once $autoload;
        require_once __DIR__ . '/push-config.php';

        if (!class_exists('\Minishlink\WebPush\WebPush')) {
            ims_push_log('Minishlink WebPush class not found.');
            return false;
        }

        $pdo = db();
        $rows = $pdo->query(
            "SELECT id, endpoint, p256dh, auth_token, content_encoding
             FROM push_subscriptions
             ORDER BY id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return false;
        }

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
            'title' => $title,
            'body' => $body,
            'icon' => '/assets/images/favicon.png',
            'badge' => '/assets/images/favicon.png',
            'url' => $url,
            'tag' => 'ims-booking-' . date('YmdHis'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($rows as $row) {
            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint' => $row['endpoint'],
                'keys' => [
                    'p256dh' => $row['p256dh'],
                    'auth' => $row['auth_token'],
                ],
                'contentEncoding' => $row['content_encoding'] ?: 'aes128gcm',
            ]);

            $webPush->queueNotification($subscription, $payload);
        }

        $sent = false;
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent = true;
            } elseif ($report->isSubscriptionExpired()) {
                $endpoint = $report->getEndpoint();
                $del = $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash = ?");
                $del->execute([hash('sha256', $endpoint)]);
            } else {
                ims_push_log('Push failed: ' . $report->getReason());
            }
        }

        return $sent;
    } catch (Throwable $e) {
        ims_push_log($e->getMessage());
        return false;
    }
}

function ims_push_new_booking(array $bookingRow, string $serviceName): bool
{
    $customer = trim((string)($bookingRow['full_name'] ?? 'A client')) ?: 'A client';
    $bookingNumber = trim((string)($bookingRow['booking_number'] ?? ''));
    $body = $customer . ' · ' . ($serviceName ?: 'New booking');

    if ($bookingNumber !== '') {
        $body .= ' · ' . $bookingNumber;
    }

    return ims_push_send(
        '🔔 New Booking · Imagination Nature Studio',
        $body,
        SITE_URL . '/admin/bookings.php'
    );
}
