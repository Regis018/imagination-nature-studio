<?php
/**
 * IMAGINATION NATURE STUDIO
 * Booking Status Page
 *
 * Client can enter booking number and see:
 * - Booking status
 * - Client information
 * - Service / package
 * - Date / time
 * - Payment information
 * - Invoice when booking is confirmed
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/language.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ref = trim((string)($_GET['ref'] ?? ''));

$booking = null;
$error = '';
$success = '';

/**
 * Normalize booking status
 */
function normalize_booking_status($status): string
{
    $status = strtolower(trim((string)$status));

    switch ($status) {
        case 'approved':
            return 'confirmed';

        case 'confirm':
            return 'confirmed';

        case 'pending':
        case 'waiting':
        case 'new':
            return 'pending';

        case 'cancel':
        case 'cancelled':
        case 'canceled':
            return 'cancelled';

        case 'confirmed':
            return 'confirmed';

        default:
            return $status !== '' ? $status : 'pending';
    }
}

/**
 * Safe money formatter
 */
function format_rwf_status($amount): string
{
    return number_format((float)$amount, 0) . ' RWF';
}

/**
 * Escape helper fallback
 */
if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Get database connection
 */
try {
    $pdo = db();
} catch (Throwable $e) {
    $error = 'Unable to connect to the booking system. Please try again later.';
    $pdo = null;
}

/**
 * Search booking
 */
if ($pdo && $ref !== '') {

    try {

        $sql = "
            SELECT
                b.*,

                c.full_name AS customer_full_name,
                c.phone AS customer_phone,
                c.whatsapp AS customer_whatsapp,
                c.email AS customer_email,
                c.location AS customer_location,

                s.name AS service_name,

                p.name AS package_name

            FROM bookings b

            JOIN customers c
                ON c.id = b.customer_id

            JOIN services s
                ON s.id = b.service_id

            LEFT JOIN packages p
                ON p.id = b.package_id

            WHERE b.booking_number = ?

            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ref]);

        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            $error = 'Booking not found. Please check your booking number and try again.';
        }

    } catch (Throwable $e) {

        error_log(
            'Booking status error: ' .
            $e->getMessage()
        );

        $error = 'Something went wrong while checking your booking. Please try again.';
    }
}

/**
 * If no ref supplied, simply show search form.
 */
if ($ref === '') {
    $booking = null;
}

/**
 * Normalize values
 */
if ($booking) {

    $booking_status = normalize_booking_status(
        $booking['booking_status'] ?? 'pending'
    );

    $payment_status = strtolower(
        trim((string)($booking['payment_status'] ?? 'unpaid'))
    );

    $invoice_path = trim(
        (string)($booking['invoice_path'] ?? '')
    );

    $invoice_number = trim(
        (string)($booking['invoice_number'] ?? '')
    );

    $total_amount = (float)(
        $booking['total_amount'] ?? 0
    );

    $amount_paid = (float)(
        $booking['amount_paid'] ?? 0
    );

    $balance = (float)(
        $booking['balance'] ?? max(
            0,
            $total_amount - $amount_paid
        )
    );

    // Only show invoice after the booking is confirmed AND fully paid.
    $invoice_available =
        $booking_status === 'confirmed'
        && $payment_status === 'paid'
        && $total_amount > 0
        && $amount_paid >= ($total_amount - 0.01)
        && $balance <= 0.01
        && $invoice_path !== '';

    /**
     * Booking date
     */
    $booking_date = '';

    if (!empty($booking['booking_date'])) {
        $timestamp = strtotime(
            (string)$booking['booking_date']
        );

        if ($timestamp !== false) {
            $booking_date = date(
                'd M Y',
                $timestamp
            );
        }
    }

    /**
     * Time
     */
    $start_time = trim(
        (string)($booking['start_time'] ?? '')
    );

    $end_time = trim(
        (string)($booking['end_time'] ?? '')
    );

    $time_display = '';

    if ($start_time !== '') {

        $start_timestamp = strtotime($start_time);

        if ($start_timestamp !== false) {

            $time_display = date(
                'H:i',
                $start_timestamp
            );

        } else {

            $time_display = $start_time;
        }
    }

    if ($end_time !== '') {

        $end_timestamp = strtotime($end_time);

        if ($end_timestamp !== false) {

            $time_display .= ' - ' . date(
                'H:i',
                $end_timestamp
            );

        } else {

            $time_display .= ' - ' . $end_time;
        }
    }

    /**
     * Status labels
     */
    switch ($booking_status) {

        case 'confirmed':
            $status_title = 'Booking Confirmed';
            $status_icon = '✓';
            $status_description =
                'Your booking has been confirmed by Imagination Nature Studio.';
            $status_class = 'confirmed';
            break;

        case 'cancelled':
            $status_title = 'Booking Cancelled';
            $status_icon = '×';
            $status_description =
                'This booking has been cancelled. Please contact the studio if you need assistance.';
            $status_class = 'cancelled';
            break;

        default:
            $status_title = 'Booking Pending';
            $status_icon = '⏳';
            $status_description =
                'Your booking has been received and is waiting for confirmation from Imagination Nature Studio.';
            $status_class = 'pending';
            break;
    }

    /**
     * Invoice URL
     */
    $invoice_url = '';

    if ($invoice_available) {

        $invoice_url =
            rtrim(SITE_URL, '/') .
            '/booking/download-invoice.php?ref=' .
            urlencode(
                (string)$booking['booking_number']
            );
    }

    /**
     * WhatsApp
     */
    $whatsapp_number = trim(
        (string)(
            $booking['customer_whatsapp']
            ?: $booking['customer_phone']
            ?: ''
        )
    );

    $whatsapp_number = preg_replace(
        '/[^0-9+]/',
        '',
        $whatsapp_number
    );

    if (strpos($whatsapp_number, '+') === 0) {
        $whatsapp_number = substr(
            $whatsapp_number,
            1
        );
    }

    /**
     * WhatsApp message
     */
    $whatsapp_message =
        "Hello Imagination Nature Studio,\n\n" .
        "I am contacting you about my booking.\n\n" .
        "Booking Number: " .
        ($booking['booking_number'] ?? '') .
        "\n" .
        "Name: " .
        ($booking['customer_full_name'] ?? '') .
        "\n" .
        "Service: " .
        ($booking['service_name'] ?? '') .
        "\n\nThank you.";

    $whatsapp_url = '';

    if ($whatsapp_number !== '') {

        $whatsapp_url =
            'https://wa.me/' .
            $whatsapp_number .
            '?text=' .
            urlencode($whatsapp_message);
    }
}

/**
 * Header
 */
require_once __DIR__ . '/../includes/header.php';

?>

<style>

    :root {
        --status-black: #111111;
        --status-white: #ffffff;
        --status-gray: #6b7280;
        --status-light: #f5f5f5;
        --status-border: #e5e7eb;
        --status-green: #15803d;
        --status-green-bg: #f0fdf4;
        --status-orange: #c2410c;
        --status-orange-bg: #fff7ed;
        --status-red: #b91c1c;
        --status-red-bg: #fef2f2;
    }

    .booking-status-page {
        min-height: calc(100vh - 100px);
        padding: 50px 20px 80px;
        background: #fafafa;
    }

    .booking-status-container {
        max-width: 850px;
        margin: 0 auto;
    }

    .booking-status-header {
        text-align: center;
        margin-bottom: 35px;
    }

    .booking-status-header h1 {
        margin: 0 0 10px;
        font-size: 38px;
        line-height: 1.15;
        font-weight: 800;
        color: var(--status-black);
    }

    .booking-status-header p {
        margin: 0;
        color: var(--status-gray);
        font-size: 16px;
    }

    .status-search-card {
        background: #fff;
        border: 1px solid var(--status-border);
        border-radius: 18px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 10px 35px rgba(0,0,0,.05);
    }

    .status-search-form {
        display: flex;
        gap: 12px;
    }

    .status-search-form input {
        flex: 1;
        height: 54px;
        padding: 0 16px;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        outline: none;
        font-size: 16px;
        background: #fff;
        color: #111;
    }

    .status-search-form input:focus {
        border-color: #111;
        box-shadow: 0 0 0 3px rgba(0,0,0,.06);
    }

    .status-search-form button {
        height: 54px;
        padding: 0 25px;
        border: none;
        border-radius: 10px;
        background: #111;
        color: #fff;
        font-weight: 700;
        cursor: pointer;
        font-size: 15px;
    }

    .status-search-form button:hover {
        opacity: .9;
    }

    .booking-error {
        background: var(--status-red-bg);
        border: 1px solid #fecaca;
        color: var(--status-red);
        border-radius: 12px;
        padding: 15px 17px;
        margin-bottom: 25px;
        font-weight: 600;
    }

    .booking-success {
        background: var(--status-green-bg);
        border: 1px solid #bbf7d0;
        color: var(--status-green);
        border-radius: 12px;
        padding: 15px 17px;
        margin-bottom: 25px;
    }

    .booking-card {
        background: #fff;
        border: 1px solid var(--status-border);
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 12px 40px rgba(0,0,0,.06);
    }

    .booking-status-box {
        padding: 35px 25px;
        text-align: center;
        border-bottom: 1px solid var(--status-border);
    }

    .booking-status-icon {
        width: 70px;
        height: 70px;
        margin: 0 auto 15px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 34px;
        font-weight: 900;
    }

    .booking-status-box.pending .booking-status-icon {
        background: var(--status-orange-bg);
        color: var(--status-orange);
    }

    .booking-status-box.confirmed .booking-status-icon {
        background: var(--status-green-bg);
        color: var(--status-green);
    }

    .booking-status-box.cancelled .booking-status-icon {
        background: var(--status-red-bg);
        color: var(--status-red);
    }

    .booking-status-box h2 {
        margin: 0 0 8px;
        font-size: 27px;
        color: #111;
    }

    .booking-status-box p {
        margin: 0 auto;
        max-width: 600px;
        color: var(--status-gray);
        line-height: 1.6;
    }

    .booking-number-box {
        margin-top: 20px;
        display: inline-flex;
        flex-direction: column;
        gap: 5px;
        background: #f8fafc;
        border: 1px solid var(--status-border);
        border-radius: 10px;
        padding: 10px 18px;
    }

    .booking-number-box span {
        font-size: 11px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: .08em;
    }

    .booking-number-box strong {
        font-size: 18px;
        letter-spacing: .03em;
        color: #111;
    }

    .booking-details {
        padding: 30px 25px;
    }

    .booking-details h3 {
        margin: 0 0 20px;
        font-size: 21px;
        color: #111;
    }

    .details-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .detail-item {
        padding: 15px;
        border: 1px solid var(--status-border);
        border-radius: 12px;
        background: #fff;
    }

    .detail-item.full {
        grid-column: 1 / -1;
    }

    .detail-label {
        display: block;
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .detail-value {
        display: block;
        font-size: 15px;
        font-weight: 700;
        color: #111;
        word-break: break-word;
    }

    .payment-section {
        margin-top: 30px;
        padding-top: 25px;
        border-top: 1px solid var(--status-border);
    }

    .payment-section h3 {
        margin-bottom: 18px;
    }

    .payment-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .payment-item {
        padding: 17px;
        background: #f8fafc;
        border-radius: 12px;
    }

    .payment-item span {
        display: block;
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 5px;
    }

    .payment-item strong {
        font-size: 16px;
        color: #111;
    }

    .booking-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding: 25px;
        border-top: 1px solid var(--status-border);
        background: #fafafa;
    }

    .booking-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 50px;
        padding: 0 20px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 700;
        font-size: 14px;
        transition: .2s ease;
    }

    .booking-action:hover {
        transform: translateY(-1px);
    }

    .invoice-button {
        background: #111;
        color: #fff;
    }

    .whatsapp-button {
        background: #25D366;
        color: #fff;
    }

    .new-booking-button {
        background: #fff;
        color: #111;
        border: 1px solid #d1d5db;
    }

    .waiting-note {
        margin-top: 20px;
        padding: 15px;
        background: var(--status-orange-bg);
        border: 1px solid #fed7aa;
        border-radius: 12px;
        color: #9a3412;
        font-size: 14px;
        line-height: 1.6;
    }

    .confirmed-note {
        margin-top: 20px;
        padding: 15px;
        background: var(--status-green-bg);
        border: 1px solid #bbf7d0;
        border-radius: 12px;
        color: #166534;
        font-size: 14px;
        line-height: 1.6;
    }

    .invoice-unavailable {
        margin-top: 20px;
        padding: 15px;
        background: #f8fafc;
        border: 1px solid var(--status-border);
        border-radius: 12px;
        color: #475569;
        font-size: 14px;
    }

    .help-text {
        text-align: center;
        margin-top: 22px;
        color: #6b7280;
        font-size: 14px;
        line-height: 1.6;
    }

    .help-text a {
        color: #111;
        font-weight: 700;
        text-decoration: none;
    }

    @media (max-width: 700px) {

        .booking-status-page {
            padding: 30px 14px 60px;
        }

        .booking-status-header h1 {
            font-size: 29px;
        }

        .status-search-form {
            flex-direction: column;
        }

        .status-search-form button {
            width: 100%;
        }

        .details-grid {
            grid-template-columns: 1fr;
        }

        .detail-item.full {
            grid-column: auto;
        }

        .payment-grid {
            grid-template-columns: 1fr;
        }

        .booking-actions {
            flex-direction: column;
        }

        .booking-action {
            width: 100%;
        }

        .booking-status-box {
            padding: 28px 18px;
        }

        .booking-details {
            padding: 25px 18px;
        }

        .booking-number-box strong {
            font-size: 16px;
        }
    }

</style>

<main class="booking-status-page">

    <div class="booking-status-container">

        <div class="booking-status-header">

            <h1>Check Your Booking</h1>

            <p>
                Enter your booking number below to check the current status of your booking.
            </p>

        </div>


        <!-- SEARCH -->
        <div class="status-search-card">

            <form
                class="status-search-form"
                method="get"
                action="<?= e(SITE_URL) ?>/booking/status.php"
            >

                <input
                    type="text"
                    name="ref"
                    value="<?= e($ref) ?>"
                    placeholder="Enter Booking Number e.g. INS-20261008-1234"
                    autocomplete="off"
                    required
                >

                <button type="submit">
                    🔎 Check Status
                </button>

            </form>

        </div>


        <?php if ($error !== ''): ?>

            <div class="booking-error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>


        <?php if ($booking): ?>

            <div class="booking-card">

                <!-- STATUS -->
                <div class="booking-status-box <?= e($status_class) ?>">

                    <div class="booking-status-icon">
                        <?= e($status_icon) ?>
                    </div>

                    <h2>
                        <?= e($status_title) ?>
                    </h2>

                    <p>
                        <?= e($status_description) ?>
                    </p>

                    <div class="booking-number-box">

                        <span>
                            Booking Number
                        </span>

                        <strong>
                            <?= e($booking['booking_number'] ?? '') ?>
                        </strong>

                    </div>

                </div>


                <!-- DETAILS -->
                <div class="booking-details">

                    <h3>
                        Booking Details
                    </h3>

                    <div class="details-grid">

                        <div class="detail-item">

                            <span class="detail-label">
                                Full Name
                            </span>

                            <span class="detail-value">
                                <?= e(
                                    $booking['customer_full_name'] ?? ''
                                ) ?>
                            </span>

                        </div>


                        <div class="detail-item">

                            <span class="detail-label">
                                Phone
                            </span>

                            <span class="detail-value">
                                <?= e(
                                    $booking['customer_phone'] ?? ''
                                ) ?>
                            </span>

                        </div>


                        <div class="detail-item">

                            <span class="detail-label">
                                Service
                            </span>

                            <span class="detail-value">
                                <?= e(
                                    $booking['service_name'] ?? '—'
                                ) ?>
                            </span>

                        </div>


                        <?php if (!empty($booking['package_name'])): ?>

                            <div class="detail-item">

                                <span class="detail-label">
                                    Package
                                </span>

                                <span class="detail-value">
                                    <?= e(
                                        $booking['package_name']
                                    ) ?>
                                </span>

                            </div>

                        <?php endif; ?>


                        <div class="detail-item">

                            <span class="detail-label">
                                Date
                            </span>

                            <span class="detail-value">
                                <?= e(
                                    $booking_date !== ''
                                        ? $booking_date
                                        : '—'
                                ) ?>
                            </span>

                        </div>


                        <div class="detail-item">

                            <span class="detail-label">
                                Time
                            </span>

                            <span class="detail-value">
                                <?= e(
                                    $time_display !== ''
                                        ? $time_display
                                        : '—'
                                ) ?>
                            </span>

                        </div>


                        <div class="detail-item full">

                            <span class="detail-label">
                                Location
                            </span>

                            <span class="detail-value">
                                <?= e(
                                    $booking['location']
                                    ?? $booking['customer_location']
                                    ?? 'Kigali'
                                ) ?>
                            </span>

                        </div>

                    </div>


                </div>


                <!-- ACTIONS -->
                <div class="booking-actions">


                    <?php if ($invoice_available && $invoice_url !== ''): ?>

                        <a
                            class="booking-action invoice-button"
                            href="<?= e($invoice_url) ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            📄 Download Invoice
                        </a>

                    <?php endif; ?>


                    <?php if ($whatsapp_url !== ''): ?>

                        <a
                            class="booking-action whatsapp-button"
                            href="<?= e($whatsapp_url) ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            💬 Contact via WhatsApp
                        </a>

                    <?php endif; ?>


                    <a
                        class="booking-action new-booking-button"
                        href="<?= e(SITE_URL) ?>/booking-simple.php"
                    >
                        + New Booking
                    </a>

                </div>

            </div>

        <?php endif; ?>


        <div class="help-text">

            Need help with your booking?

            <a
                href="<?= e(SITE_URL) ?>/booking-simple.php"
            >
                Make another booking
            </a>

        </div>

    </div>

</main>


<script>
(function () {

    const bookingNumber =
        <?= json_encode(
            $booking['booking_number'] ?? ''
        ) ?>;

    if (!bookingNumber) {
        return;
    }

    let lastStatus =
        <?= json_encode(
            $booking_status ?? ''
        ) ?>;

    let polling = true;

    /**
     * Automatically refresh booking status.
     *
     * This allows the client to keep this page open
     * while the admin confirms the booking.
     */
    async function checkBookingStatus() {

        if (!polling) {
            return;
        }

        try {

            const url =
                <?= json_encode(
                    rtrim(SITE_URL, '/') .
                    '/booking/confirmation.php'
                ) ?>
                +
                '?check_status=1&ref='
                +
                encodeURIComponent(
                    bookingNumber
                );

            const response =
                await fetch(
                    url,
                    {
                        method: 'GET',
                        cache: 'no-store',
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                );

            if (!response.ok) {
                return;
            }

            const data =
                await response.json();

            if (!data || !data.success) {
                return;
            }

            const newStatus =
                String(
                    data.booking_status || ''
                ).toLowerCase();

            /**
             * Reload only when status changes.
             */
            if (
                newStatus &&
                newStatus !== lastStatus
            ) {

                lastStatus = newStatus;

                window.location.reload();

                return;
            }

            /**
             * Invoice may be generated
             * after confirmation.
             */
            if (
                newStatus === 'confirmed' &&
                data.invoice_path
            ) {

                window.location.reload();

                return;
            }

            /**
             * Stop polling after cancellation.
             */
            if (
                newStatus === 'cancelled'
            ) {

                polling = false;

                return;
            }

        } catch (error) {

            // Silently ignore temporary network errors.

        }
    }


    /**
     * Check every 5 seconds.
     */
    setInterval(
        checkBookingStatus,
        5000
    );

})();
</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>