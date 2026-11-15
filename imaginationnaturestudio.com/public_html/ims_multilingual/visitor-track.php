<?php

require_once __DIR__ . '/includes/functions.php';

try {

    /*
     * UNIQUE VISITOR ID
     */
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

    /*
     * USER AGENT
     */
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    /*
     * DEVICE
     */
    if (preg_match('/tablet|ipad/i', $userAgent)) {
        $deviceType = 'Tablet';
    } elseif (preg_match('/mobile|android|iphone|ipod/i', $userAgent)) {
        $deviceType = 'Mobile';
    } else {
        $deviceType = 'Desktop';
    }

    /*
     * BROWSER
     */
    if (preg_match('/Edg/i', $userAgent)) {
        $browser = 'Microsoft Edge';
    } elseif (preg_match('/OPR|Opera/i', $userAgent)) {
        $browser = 'Opera';
    } elseif (preg_match('/Chrome/i', $userAgent)) {
        $browser = 'Google Chrome';
    } elseif (preg_match('/Firefox/i', $userAgent)) {
        $browser = 'Firefox';
    } elseif (preg_match('/Safari/i', $userAgent)) {
        $browser = 'Safari';
    } else {
        $browser = 'Other';
    }

    /*
     * OPERATING SYSTEM
     */
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

    /*
     * DATABASE
     */
    $pdo = db();

    /*
     * COUNT ONE VISIT PER VISITOR
     * EVERY 30 MINUTES
     */
    $check = $pdo->prepare("
        SELECT id
        FROM website_visits
        WHERE visitor_id = ?
          AND visited_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        LIMIT 1
    ");

    $check->execute([$visitorId]);

    /*
     * INSERT VISITOR
     */
    if (!$check->fetchColumn()) {

        $insert = $pdo->prepare("
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

        $insert->execute([
            $visitorId,
            $deviceType,
            $browser,
            $operatingSystem
        ]);
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    header('Content-Type: application/json');

    echo json_encode([
        'success' => false
    ]);
}

exit;