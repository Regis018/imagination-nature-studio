<?php
declare(strict_types=1);

/*
 * Imagination Nature Studio
 * Photo-selection final submission
 *
 * Studio flow:
 *   1. Client books a Studio Photoshoot.
 *   2. Booking is confirmed without a photo invoice.
 *   3. Admin creates/opens the client's selection gallery and links it to booking_id.
 *   4. Client selects photos.
 *   5. On Submit Selection, total = selected_count x price_per_photo.
 *   6. Booking total/balance are updated.
 *   7. Final invoice is generated automatically.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../includes/functions.php';

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Invalid request method.');
    }

    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);

    $token = trim((string)(
        is_array($json)
            ? ($json['token'] ?? '')
            : ($_POST['token'] ?? '')
    ));

    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
        throw new RuntimeException('Invalid gallery link.');
    }

    $pdo = db();

    /* =========================================================
       LOAD GALLERY
       ========================================================= */

    $stmt = $pdo->prepare("SELECT * FROM photo_galleries WHERE access_token=? LIMIT 1");
    $stmt->execute([$token]);
    $gallery = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$gallery) {
        throw new RuntimeException('Gallery not found.');
    }

    $galleryId = (int)$gallery['id'];

    /* Already submitted: return the final amount without creating a duplicate invoice. */
    if (($gallery['status'] ?? '') === 'closed') {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM photo_selections WHERE gallery_id=?");
        $countStmt->execute([$galleryId]);
        $count = (int)$countStmt->fetchColumn();

        echo json_encode([
            'ok' => true,
            'already_submitted' => true,
            'selected_count' => $count,
            'total' => $count * (float)$gallery['price_per_photo'],
            'message' => 'Your selection has already been submitted.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* =========================================================
       EXPIRATION
       ========================================================= */

    if (!empty($gallery['expires_at']) && strtotime((string)$gallery['expires_at']) <= time()) {
        $pdo->prepare("UPDATE photo_galleries SET status='expired' WHERE id=?")
            ->execute([$galleryId]);
        throw new RuntimeException('This photo selection session has expired.');
    }

    if (!in_array((string)$gallery['status'], ['draft', 'active'], true)) {
        throw new RuntimeException('This photo selection session is not available.');
    }

    /* =========================================================
       SELECTED PHOTO COUNT
       ========================================================= */

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM photo_selections WHERE gallery_id=?");
    $countStmt->execute([$galleryId]);
    $selectedCount = (int)$countStmt->fetchColumn();

    if ($selectedCount < 1) {
        throw new RuntimeException('Please select at least one photo before submitting.');
    }

    $pricePerPhoto = (float)$gallery['price_per_photo'];

    if ($pricePerPhoto <= 0) {
        throw new RuntimeException('Photo price is not configured for this gallery.');
    }

    $total = round($selectedCount * $pricePerPhoto, 2);

    /* =========================================================
       BOOKING LINK
       ========================================================= */

    $bookingId = isset($gallery['booking_id'])
        ? (int)$gallery['booking_id']
        : 0;

    if ($bookingId <= 0) {
        throw new RuntimeException(
            'This gallery is not linked to a booking yet. Please contact Imagination Nature Studio.'
        );
    }

    $bookingStmt = $pdo->prepare("SELECT * FROM bookings WHERE id=? LIMIT 1");
    $bookingStmt->execute([$bookingId]);
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        throw new RuntimeException('The booking linked to this gallery could not be found.');
    }

    /* Verify this is a Studio/per-photo booking before changing its price. */
    $serviceStmt = $pdo->prepare("SELECT name FROM services WHERE id=? LIMIT 1");
    $serviceStmt->execute([(int)$booking['service_id']]);
    $serviceName = strtolower(trim((string)$serviceStmt->fetchColumn()));

    $isStudio = (
        strpos($serviceName, 'studio photoshoot') !== false
        ||
        (
            strpos($serviceName, 'studio') !== false
            && strpos($serviceName, 'photo') !== false
        )
    );

    if (!$isStudio) {
        throw new RuntimeException('This photo-selection gallery is not linked to a Studio Photoshoot booking.');
    }

    /* =========================================================
       TRANSACTION
       ========================================================= */

    $pdo->beginTransaction();

    /* Preserve any payment already recorded, but never invent a payment. */
    $amountPaid = max(0, (float)$booking['amount_paid']);
    $balance = max(0, $total - $amountPaid);

    $paymentStatus = $amountPaid <= 0
        ? 'unpaid'
        : ($amountPaid >= $total ? 'paid' : 'partial');

    $update = $pdo->prepare("\
        UPDATE bookings\
        SET\
            total_amount=?,\
            deposit_amount=0,\
            amount_paid=?,\
            balance=?,\
            payment_status=?,\
            updated_at=NOW()\
        WHERE id=?\
    ");

    $update->execute([
        $total,
        $amountPaid,
        $balance,
        $paymentStatus,
        $bookingId
    ]);

    /* Close the selection session and keep an audit timestamp. */
    $galleryUpdate = $pdo->prepare("\
        UPDATE photo_galleries\
        SET status='closed', selection_submitted_at=NOW()\
        WHERE id=?\
    ");
    $galleryUpdate->execute([$galleryId]);

    $pdo->commit();

    /* =========================================================
       GENERATE FINAL INVOICE
       ========================================================= */

    $invoiceFile = __DIR__ . '/../booking/generate-invoice.php';

    if (!is_file($invoiceFile)) {
        throw new RuntimeException('Invoice generator file not found.');
    }

    require_once $invoiceFile;

    if (!function_exists('generate_invoice')) {
        throw new RuntimeException('Invoice generator function is unavailable.');
    }

    generate_invoice($bookingId);

    echo json_encode([
        'ok' => true,
        'selected_count' => $selectedCount,
        'price_per_photo' => $pricePerPhoto,
        'total' => $total,
        'message' => 'Selection submitted and final invoice generated successfully.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {

    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    @error_log(
        '[photo-selection submit] '
        . $e->getMessage()
        . ' | FILE: '
        . $e->getFile()
        . ' | LINE: '
        . $e->getLine()
        . "\n",
        3,
        defined('ERROR_LOG_FILE') ? ERROR_LOG_FILE : __DIR__ . '/../booking-errors.log'
    );

    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
