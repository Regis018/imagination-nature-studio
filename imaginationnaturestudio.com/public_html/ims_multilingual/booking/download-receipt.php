<?php
require_once __DIR__ . '/../includes/functions.php';

$ref = $_GET['ref'] ?? '';
if (!preg_match('/^INS-\d{4}-\d{5}$/', $ref)) {
    http_response_code(400);
    exit('Invalid reference.');
}

$stmt = db()->prepare("SELECT id, receipt_path, payment_status FROM bookings WHERE booking_number = ? LIMIT 1");
$stmt->execute([$ref]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit('Receipt not found.');
}

$abs = SITE_ROOT . '/' . ltrim((string)$row['receipt_path'], '/');

// Regenerate if missing — but only if payment is confirmed
if (!$row['receipt_path'] || !is_file($abs)) {
    if ($row['payment_status'] !== 'paid') {
        http_response_code(403);
        exit('Receipt is not yet available — payment has not been confirmed.');
    }
    require_once __DIR__ . '/generate-receipt.php';
    $path = generate_receipt((int)$row['id']);
    $abs  = SITE_ROOT . '/' . ltrim($path, '/');
}

if (!is_file($abs)) {
    http_response_code(404);
    exit('Receipt file missing.');
}

header('Content-Type: text/html; charset=utf-8');
readfile($abs);
exit;