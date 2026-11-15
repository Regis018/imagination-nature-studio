
<?php
/**
 * Client receipt access.
 * Receipt is available ONLY after full payment is confirmed.
 */
require_once __DIR__ . '/../includes/functions.php';

$ref = trim((string)($_GET['ref'] ?? ''));

if (!preg_match('/^INS-\d{4}-\d{5}$/', $ref)) {
    http_response_code(400);
    exit('Invalid reference.');
}

$stmt = db()->prepare(
    "SELECT id, booking_number, receipt_path,
            payment_status, amount_paid, total_amount, balance
     FROM bookings
     WHERE booking_number = ?
     LIMIT 1"
);
$stmt->execute([$ref]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    exit('Booking not found.');
}

/*
 * SECURITY: Require confirmed full payment.
 */
$total = (float)$row['total_amount'];
$paid = (float)$row['amount_paid'];
$balance = (float)$row['balance'];

$fullyPaid = (
    strtolower((string)$row['payment_status']) === 'paid'
    && $total > 0
    && $paid + 0.01 >= $total
    && $balance <= 0.01
);

if (!$fullyPaid) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    exit(
        '<h2>Receipt not yet available</h2>' .
        '<p>Your receipt will become available after the full photoshoot ' .
        'payment has been confirmed.</p>'
    );
}

$receiptPath = trim((string)($row['receipt_path'] ?? ''));
$abs = '';

if ($receiptPath !== '') {
    $abs = SITE_ROOT . '/' . ltrim($receiptPath, '/');
}

/*
 * Generate receipt only after full payment.
 */
if ($abs === '' || !is_file($abs)) {
    require_once __DIR__ . '/generate-receipt.php';

    try {
        $path = generate_receipt((int)$row['id']);
    } catch (Throwable $e) {
        error_log(
            '[receipt] ' . $e->getMessage()
            . ' | ' . $e->getFile()
            . ':' . $e->getLine()
        );

        http_response_code(500);
        exit('Unable to generate receipt.');
    }

    if (!$path) {
        http_response_code(500);
        exit('Unable to generate receipt.');
    }

    $abs = SITE_ROOT . '/' . ltrim((string)$path, '/');

    db()->prepare(
        "UPDATE bookings SET receipt_path = ? WHERE id = ?"
    )->execute([(string)$path, (int)$row['id']]);
}

if (!is_file($abs)) {
    http_response_code(404);
    exit('Receipt file missing.');
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($abs);
exit;
