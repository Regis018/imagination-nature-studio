<?php
/**
 * Imagination Nature Studio
 * Admin Booking Number Lookup
 *
 * Search a booking by the booking number supplied by the client.
 * Read-only: this page does not update bookings, payments or Sales.
 */

require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

$bookingNumber = strtoupper(trim((string)($_GET['booking_number'] ?? '')));
$booking = null;
$error = '';

if ($bookingNumber !== '') {
    // Accept only a plausible booking reference; the database lookup remains parameterized.
    if (!preg_match('/^[A-Z0-9-]{5,40}$/', $bookingNumber)) {
        $error = 'Booking Number format is not valid. Check the number and try again.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT
                    b.*,
                    c.full_name AS customer_full_name,
                    c.phone AS customer_phone,
                    c.whatsapp AS customer_whatsapp,
                    c.email AS customer_email,
                    c.location AS customer_location,
                    c.company AS customer_company,
                    s.name AS service_name,
                    p.name AS package_name
                 FROM bookings b
                 JOIN customers c ON c.id = b.customer_id
                 JOIN services s ON s.id = b.service_id
                 LEFT JOIN packages p ON p.id = b.package_id
                 WHERE UPPER(b.booking_number) = ?
                 LIMIT 1"
            );
            $stmt->execute([$bookingNumber]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$booking) {
                $error = 'No booking was found with that Booking Number.';
            }
        } catch (Throwable $e) {
            error_log('Admin booking lookup error: ' . $e->getMessage());
            $error = 'The booking could not be searched right now. Check the server error log.';
        }
    }
}

$money = static function ($value): string {
    if (function_exists('rwf')) {
        return rwf((float)$value);
    }
    return number_format((float)$value, 0) . ' RWF';
};

$show = static function ($value): string {
    $value = trim((string)($value ?? ''));
    return $value !== '' ? e($value) : '—';
};

require __DIR__ . '/includes/admin-header.php';
?>

<style>
.booking-lookup-wrap{max-width:1100px;margin:0 auto;padding:10px 0 45px}
.booking-lookup-intro{color:#756f68;margin:6px 0 22px;line-height:1.6}
.booking-lookup-card{background:#fff;border:1px solid #e8e2da;border-radius:15px;padding:22px;margin:18px 0;box-shadow:0 8px 28px rgba(0,0,0,.04)}
.booking-lookup-form{display:flex;gap:10px;flex-wrap:wrap}
.booking-lookup-form input{flex:1;min-width:220px;padding:14px 15px;border:1px solid #d8d2ca;border-radius:9px;font-size:15px}
.booking-lookup-form button,.booking-lookup-link{border:0;border-radius:9px;padding:13px 20px;background:#1f7a38;color:#fff;font-weight:700;text-decoration:none;cursor:pointer;display:inline-block}
.lookup-error{background:#fff1f0;color:#a12622;border:1px solid #ffd2ce;padding:13px 15px;border-radius:9px;margin-top:15px}
.lookup-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.lookup-item{padding:12px 14px;background:#faf9f7;border:1px solid #eee9e2;border-radius:9px;min-width:0}
.lookup-label{display:block;color:#817a72;font-size:11px;text-transform:uppercase;letter-spacing:.07em;margin-bottom:5px}
.lookup-value{font-weight:650;color:#201e1b;overflow-wrap:anywhere}
.lookup-section-title{font-size:17px;margin:22px 0 12px;color:#1f7a38}
.lookup-status{display:inline-block;padding:5px 10px;border-radius:30px;background:#f2f2f2;font-size:12px;font-weight:800;text-transform:uppercase}
.lookup-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}
.lookup-secondary{display:inline-block;padding:12px 18px;border:1px solid #d9d3ca;border-radius:9px;color:#24211d;text-decoration:none;font-weight:700}
@media(max-width:650px){.lookup-grid{grid-template-columns:1fr}.booking-lookup-card{padding:16px}.booking-lookup-form button{width:100%}}
</style>

<div class="booking-lookup-wrap">
    <h1>🔎 Booking Number Lookup</h1>
    <p class="booking-lookup-intro">
        Enter the Booking Number provided by the client to find the exact booking and view its details.
    </p>

    <div class="booking-lookup-card">
        <form method="get" action="booking-lookup.php" class="booking-lookup-form">
            <input
                type="text"
                name="booking_number"
                value="<?= e($bookingNumber) ?>"
                placeholder="Example: INS-20261105-12345"
                autocomplete="off"
                maxlength="40"
                required
            >
            <button type="submit">Search Booking</button>
        </form>

        <?php if ($error !== ''): ?>
            <div class="lookup-error"><?= e($error) ?></div>
        <?php endif; ?>
    </div>

    <?php if ($booking): ?>
        <div class="booking-lookup-card">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap">
                <div>
                    <span class="lookup-label">Booking Number</span>
                    <h2 style="margin:4px 0 0"><?= $show($booking['booking_number'] ?? '') ?></h2>
                </div>
                <span class="lookup-status"><?= $show($booking['booking_status'] ?? 'pending') ?></span>
            </div>

            <h3 class="lookup-section-title">Client Information</h3>
            <div class="lookup-grid">
                <div class="lookup-item"><span class="lookup-label">Full Name</span><div class="lookup-value"><?= $show($booking['customer_full_name'] ?? '') ?></div></div>
                <div class="lookup-item"><span class="lookup-label">Phone</span><div class="lookup-value"><?= $show($booking['customer_phone'] ?? '') ?></div></div>
                <div class="lookup-item"><span class="lookup-label">WhatsApp</span><div class="lookup-value"><?= $show($booking['customer_whatsapp'] ?? '') ?></div></div>
                <div class="lookup-item"><span class="lookup-label">Email</span><div class="lookup-value"><?= $show($booking['customer_email'] ?? '') ?></div></div>
                <div class="lookup-item"><span class="lookup-label">Client Location</span><div class="lookup-value"><?= $show($booking['customer_location'] ?? '') ?></div></div>
                <?php if (!empty($booking['customer_company'])): ?>
                    <div class="lookup-item"><span class="lookup-label">Company</span><div class="lookup-value"><?= $show($booking['customer_company']) ?></div></div>
                <?php endif; ?>
            </div>

            <h3 class="lookup-section-title">Booking Information</h3>
            <div class="lookup-grid">
                <div class="lookup-item"><span class="lookup-label">Service</span><div class="lookup-value"><?= $show($booking['service_name'] ?? '') ?></div></div>
                <div class="lookup-item"><span class="lookup-label">Package</span><div class="lookup-value"><?= $show($booking['package_name'] ?? '') ?></div></div>
                <div class="lookup-item"><span class="lookup-label">Booking Date</span><div class="lookup-value"><?= $show($booking['booking_date'] ?? '') ?></div></div>
                <div class="lookup-item"><span class="lookup-label">Time</span><div class="lookup-value"><?= $show($booking['start_time'] ?? '') ?><?= !empty($booking['end_time']) ? ' – ' . $show($booking['end_time']) : '' ?></div></div>
                <div class="lookup-item"><span class="lookup-label">Event Location</span><div class="lookup-value"><?= $show($booking['location'] ?? '') ?></div></div>
                <div class="lookup-item"><span class="lookup-label">Number of People</span><div class="lookup-value"><?= $show($booking['number_of_people'] ?? '') ?></div></div>
                <div class="lookup-item"><span class="lookup-label">Created At</span><div class="lookup-value"><?= $show($booking['created_at'] ?? '') ?></div></div>
                <div class="lookup-item"><span class="lookup-label">Booking Status</span><div class="lookup-value"><?= $show($booking['booking_status'] ?? '') ?></div></div>
            </div>

            <h3 class="lookup-section-title">Payment Overview</h3>
            <div class="lookup-grid">
                <div class="lookup-item"><span class="lookup-label">Total Amount</span><div class="lookup-value"><?= e($money($booking['total_amount'] ?? 0)) ?></div></div>
                <div class="lookup-item"><span class="lookup-label">Deposit Required</span><div class="lookup-value"><?= e($money($booking['deposit_amount'] ?? 0)) ?></div></div>
                <div class="lookup-item"><span class="lookup-label">Amount Paid</span><div class="lookup-value"><?= e($money($booking['amount_paid'] ?? 0)) ?></div></div>
                <div class="lookup-item"><span class="lookup-label">Balance</span><div class="lookup-value"><?= e($money($booking['balance'] ?? 0)) ?></div></div>
                <div class="lookup-item"><span class="lookup-label">Payment Status</span><div class="lookup-value"><?= $show($booking['payment_status'] ?? '') ?></div></div>
                <div class="lookup-item"><span class="lookup-label">Payment Method</span><div class="lookup-value"><?= $show($booking['payment_method'] ?? '') ?></div></div>
            </div>

            <div class="lookup-actions">
                <a class="booking-lookup-link" href="booking-view.php?id=<?= (int)$booking['id'] ?>">Open Full Booking in Admin</a>
                <a class="lookup-secondary" href="booking-lookup.php">Search Another Booking</a>
            </div>
        </div>
    <?php endif; ?>
</div>
