<?php
/**
 * Save an admin browser/iPhone Web Push subscription.
 */
require_once __DIR__ . '/../includes/functions.php';
require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'POST required']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        throw new RuntimeException('Invalid JSON.');
    }

    $endpoint = trim((string)($data['endpoint'] ?? ''));
    $keys = isset($data['keys']) && is_array($data['keys']) ? $data['keys'] : [];
    $p256dh = trim((string)($keys['p256dh'] ?? ''));
    $auth = trim((string)($keys['auth'] ?? ''));
    $contentEncoding = trim((string)($data['contentEncoding'] ?? 'aes128gcm'));

    if (!filter_var($endpoint, FILTER_VALIDATE_URL) || $p256dh === '' || $auth === '') {
        throw new RuntimeException('Incomplete push subscription.');
    }

    if (!in_array($contentEncoding, ['aes128gcm', 'aesgcm'], true)) {
        $contentEncoding = 'aes128gcm';
    }

    $pdo = db();
    $hash = hash('sha256', $endpoint);

    $stmt = $pdo->prepare(
        "INSERT INTO push_subscriptions
            (endpoint_hash, endpoint, p256dh, auth_token, content_encoding, user_agent, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            endpoint = VALUES(endpoint),
            p256dh = VALUES(p256dh),
            auth_token = VALUES(auth_token),
            content_encoding = VALUES(content_encoding),
            user_agent = VALUES(user_agent),
            updated_at = NOW()"
    );

    $stmt->execute([
        $hash,
        $endpoint,
        $p256dh,
        $auth,
        $contentEncoding,
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500)
    ]);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save push subscription.']);
}
