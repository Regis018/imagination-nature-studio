
<?php
/**
 * Client invoice access.
 * Invoice is available ONLY after full payment is confirmed.
 */
require_once __DIR__ . '/../includes/functions.php';

$ref = trim((string)($_GET['ref'] ?? ''));

if (!preg_match('/^INS-\d{4}-\d{5}$/', $ref)) {
    http_response_code(400);
    exit('Invalid reference.');
}

$stmt = db()->prepare(
    "SELECT id, booking_number, invoice_path,
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
$paid  = (float)$row['amount_paid'];
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
        '<h2>Invoice not yet available</h2>' .
        '<p>Please complete payment for your photoshoot first. ' .
        'Your invoice will become available after full payment is confirmed.</p>'
    );
}

/*
 * Load existing invoice or generate it after full payment.
 */
$invoicePath = trim((string)($row['invoice_path'] ?? ''));
$abs = '';

if ($invoicePath !== '') {
    $abs = SITE_ROOT . '/' . ltrim($invoicePath, '/');
}

if ($abs === '' || !is_file($abs)) {
    require_once __DIR__ . '/generate-invoice.php';

    try {
        $path = generate_invoice((int)$row['id']);
    } catch (Throwable $e) {
        error_log(
            '[invoice] ' . $e->getMessage()
            . ' | ' . $e->getFile()
            . ':' . $e->getLine()
        );

        http_response_code(500);
        exit('Unable to generate invoice.');
    }

    if (!$path) {
        http_response_code(500);
        exit('Unable to generate invoice.');
    }

    $abs = SITE_ROOT . '/' . ltrim((string)$path, '/');

    db()->prepare(
        "UPDATE bookings SET invoice_path = ? WHERE id = ?"
    )->execute([(string)$path, (int)$row['id']]);
}

if (!is_file($abs)) {
    http_response_code(404);
    exit('Invoice file missing.');
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($abs);
exit;
