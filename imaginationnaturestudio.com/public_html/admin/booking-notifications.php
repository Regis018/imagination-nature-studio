<?php
/**
 * Imagination Nature Studio
 * Admin live booking notifications endpoint.
 *
 * Returns the latest pending booking and the number of pending bookings.
 * Authentication is required.
 */

require_once __DIR__ . '/../includes/functions.php';
require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $pdo = db();

    $pendingCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM bookings WHERE booking_status = 'pending'"
    )->fetchColumn();

    $stmt = $pdo->query(
        "SELECT
            id,
            booking_number,
            customer_id,
            service_id,
            booking_date,
            start_time,
            created_at
         FROM bookings
         WHERE booking_status = 'pending'
         ORDER BY id DESC
         LIMIT 1"
    );

    $latest = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $customerName = '';
    $serviceName = '';

    if ($latest) {
        try {
            if (!empty($latest['customer_id'])) {
                $s = $pdo->prepare("SELECT full_name FROM customers WHERE id = ? LIMIT 1");
                $s->execute([(int)$latest['customer_id']]);
                $customerName = (string)($s->fetchColumn() ?: '');
            }
        } catch (Throwable $ignore) {}

        try {
            if (!empty($latest['service_id'])) {
                $s = $pdo->prepare("SELECT name FROM services WHERE id = ? LIMIT 1");
                $s->execute([(int)$latest['service_id']]);
                $serviceName = (string)($s->fetchColumn() ?: '');
            }
        } catch (Throwable $ignore) {}
    }

    echo json_encode([
        'ok' => true,
        'pending_count' => $pendingCount,
        'latest' => $latest ? [
            'id' => (int)$latest['id'],
            'booking_number' => (string)$latest['booking_number'],
            'customer_name' => $customerName,
            'service_name' => $serviceName,
            'booking_date' => (string)$latest['booking_date'],
            'start_time' => (string)$latest['start_time'],
            'created_at' => (string)$latest['created_at'],
        ] : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'pending_count' => 0,
        'latest' => null,
    ], JSON_UNESCAPED_UNICODE);
}
