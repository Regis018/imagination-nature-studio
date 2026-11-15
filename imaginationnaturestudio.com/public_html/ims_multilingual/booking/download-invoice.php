<?php
/**
 * Serves the printable HTML invoice.
 *
 * The client needs the invoice in order to make the payment,
 * so no payment or approval gate is applied here.
 */
require_once __DIR__ . '/../includes/functions.php';

$ref = trim($_GET['ref'] ?? '');

if (!preg_match('/^INS-\d{4}-\d{5}$/', $ref)) {
    http_response_code(400);
    exit('Invalid reference.');
}

/*
|--------------------------------------------------------------------------
| LOAD BOOKING
|--------------------------------------------------------------------------
*/
$stmt = db()->prepare(
    "SELECT id, booking_number, invoice_path, booking_status
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
|--------------------------------------------------------------------------
| INVOICE FILE — use stored path if present
|--------------------------------------------------------------------------
*/
$invoicePath = trim((string)($row['invoice_path'] ?? ''));
$abs = '';

if ($invoicePath !== '') {
    $abs = SITE_ROOT . '/' . ltrim($invoicePath, '/');
}

/*
|--------------------------------------------------------------------------
| REGENERATE IF MISSING
|--------------------------------------------------------------------------
*/
if ($abs === '' || !is_file($abs)) {

    require_once __DIR__ . '/generate-invoice.php';

    try {
        $path = generate_invoice((int)$row['id']);
    } catch (Throwable $e) {
        @error_log(
            '[invoice] ' . $e->getMessage()
            . ' | ' . $e->getFile()
            . ':' . $e->getLine() . "\n",
            3, ERROR_LOG_FILE
        );

        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            header('Content-Type: text/plain; charset=utf-8');
            echo "❌ INVOICE GENERATION FAILED\n\n";
            echo "Message: " . $e->getMessage() . "\n";
            echo "File:    " . $e->getFile() . "\n";
            echo "Line:    " . $e->getLine() . "\n";
            exit;
        }

        http_response_code(500);
        exit('Unable to generate invoice.');
    }

    if (!$path) {
        http_response_code(500);
        exit('Unable to generate invoice.');
    }

    $abs = SITE_ROOT . '/' . ltrim((string)$path, '/');

    /* Remember the generated path for next time */
    try {
        db()->prepare("UPDATE bookings SET invoice_path=? WHERE id=?")
            ->execute([$path, (int)$row['id']]);
    } catch (Throwable $e) {
        /* not fatal — invoice still served */
    }
}

/*
|--------------------------------------------------------------------------
| FINAL FILE CHECK
|--------------------------------------------------------------------------
*/
if (!is_file($abs)) {
    http_response_code(404);
    exit('Invoice file missing.');
}

/*
|--------------------------------------------------------------------------
| DISPLAY
|--------------------------------------------------------------------------
*/
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($abs);
exit;