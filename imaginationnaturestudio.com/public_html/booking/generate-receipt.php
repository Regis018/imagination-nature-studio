<?php
/**
 * Imagination Nature Studio
 * Full HTML Receipt Generator
 *
 * Generates a professional HTML RECEIPT for a booking.
 * Receipt is issued only after payment is confirmed as PAID.
 *
 * Saved to:
 *   /receipts/RCT-YYYY-NNNNN.html
 */

require_once __DIR__ . '/../includes/functions.php';


/* =========================================================
   HELPERS
   ========================================================= */

if (!function_exists('receipt_payment_method_label')) {
    function receipt_payment_method_label(?string $method): string
    {
        $method = trim((string)$method);

        if ($method === '') {
            return '—';
        }

        $labels = [
            'cash'          => 'Cash',
            'mobile_money'  => 'Mobile Money',
            'bank_transfer' => 'Bank Transfer',
            'other'         => 'Other',
        ];

        return $labels[$method]
            ?? ucwords(str_replace('_', ' ', $method));
    }
}


if (!function_exists('receipt_date_label')) {
    function receipt_date_label(?string $date): string
    {
        $date = trim((string)$date);

        if ($date === '') {
            return '—';
        }

        $time = strtotime($date);

        if ($time === false) {
            return $date;
        }

        return date('d M Y', $time);
    }
}


if (!function_exists('receipt_datetime_label')) {
    function receipt_datetime_label(?string $date): string
    {
        $date = trim((string)$date);

        if ($date === '') {
            return date('d M Y H:i');
        }

        $time = strtotime($date);

        if ($time === false) {
            return $date;
        }

        return date('d M Y H:i', $time);
    }
}


if (!function_exists('receipt_safe_file_write')) {
    function receipt_safe_file_write(string $file, string $html): void
    {
        $bytes = @file_put_contents($file, $html, LOCK_EX);

        if ($bytes === false) {
            throw new RuntimeException(
                'Unable to save receipt file: ' . $file
            );
        }
    }
}


/* =========================================================
   MAIN RECEIPT GENERATOR
   ========================================================= */

function generate_receipt(int $bookingId): string
{
    if ($bookingId <= 0) {
        throw new InvalidArgumentException('Invalid booking ID.');
    }

    $pdo = db();


    /* =====================================================
       LOAD BOOKING
       ===================================================== */

    $stmt = $pdo->prepare(
        "SELECT
            b.*,

            c.full_name,
            c.phone,
            c.whatsapp,
            c.email,
            c.location AS customer_location,
            c.company,

            s.name AS service_name,

            p.name AS package_name

         FROM bookings b

         JOIN customers c
           ON c.id = b.customer_id

         JOIN services s
           ON s.id = b.service_id

         LEFT JOIN packages p
           ON p.id = b.package_id

         WHERE b.id = ?

         LIMIT 1"
    );

    $stmt->execute([$bookingId]);

    $b = $stmt->fetch();


    if (!$b) {
        throw new RuntimeException('Booking not found.');
    }


    /* =====================================================
       RECEIPT SAFETY
       Receipt should only be generated for PAID booking.
       ===================================================== */

    if (($b['payment_status'] ?? '') !== 'paid') {
        throw new RuntimeException(
            'Receipt can only be generated after the booking is marked as PAID.'
        );
    }


    /* =====================================================
       RECEIPT NUMBER
       ===================================================== */

    $rcpNo = trim((string)($b['receipt_number'] ?? ''));

    if ($rcpNo === '') {

        /*
         * Existing booking numbers are in the format:
         * INS-YYYY-NNNNN
         *
         * Receipt becomes:
         * RCT-YYYY-NNNNN
         */

        $bookingNumber = (string)($b['booking_number'] ?? '');

        if (
            preg_match(
                '/^INS-(\d{4})-(\d{5})$/',
                $bookingNumber,
                $matches
            )
        ) {
            $rcpNo =
                'RCT-' .
                $matches[1] .
                '-' .
                $matches[2];
        } else {
            /*
             * Fallback for older/unusual booking numbers.
             */
            $rcpNo =
                'RCT-' .
                date('Y') .
                '-' .
                str_pad(
                    (string)$bookingId,
                    5,
                    '0',
                    STR_PAD_LEFT
                );
        }

        $updateReceipt = $pdo->prepare(
            "UPDATE bookings
             SET
                receipt_number = ?,
                receipt_issued_at = NOW()
             WHERE id = ?"
        );

        $updateReceipt->execute([
            $rcpNo,
            $bookingId
        ]);
    }


    /* =====================================================
       ISSUED DATE
       ===================================================== */

    $issuedAtRaw = $b['receipt_issued_at'] ?? '';

    if (!$issuedAtRaw) {
        $issuedAtRaw = date('Y-m-d H:i:s');
    }

    $issuedAt = receipt_datetime_label($issuedAtRaw);


    /* =====================================================
       PAYMENT CALCULATIONS
       ===================================================== */

    $totalAmount = (float)($b['total_amount'] ?? 0);

    /*
     * A PAID booking always has the full amount paid.
     * This keeps the receipt internally consistent.
     */
    $amountPaid = $totalAmount;

    $balance = 0.0;


    $depositAmount = (float)($b['deposit_amount'] ?? 0);


    /* =====================================================
       PAYMENT METHOD
       ===================================================== */

    $paymentMethod =
        receipt_payment_method_label(
            $b['payment_method'] ?? ''
        );


    /* =====================================================
       CUSTOMER DATA
       ===================================================== */

    $customerName =
        trim((string)($b['full_name'] ?? ''));

    $customerPhone =
        trim((string)($b['phone'] ?? ''));

    $customerEmail =
        trim((string)($b['email'] ?? ''));

    $customerCompany =
        trim((string)($b['company'] ?? ''));


    /* =====================================================
       BOOKING DATA
       ===================================================== */

    $bookingNumber =
        trim((string)($b['booking_number'] ?? ''));

    $invoiceNumber =
        trim((string)($b['invoice_number'] ?? ''));

    $serviceName =
        trim((string)($b['service_name'] ?? ''));

    $packageName =
        trim((string)($b['package_name'] ?? ''));

    $bookingDate =
        trim((string)($b['booking_date'] ?? ''));

    $startTime =
        trim((string)($b['start_time'] ?? ''));

    $endTime =
        trim((string)($b['end_time'] ?? ''));

    $location =
        trim((string)($b['location'] ?? ''));

    if ($location === '') {
        $location =
            trim((string)($b['customer_location'] ?? ''));
    }


    /* =====================================================
       OPTIONAL PAYMENT REFERENCE
       Works with different payments table structures.
       Does not assume a notes column exists.
       ===================================================== */

    $paymentReference = '';

    try {
        $paymentColumns = [];
        $columnStmt = $pdo->query("SHOW COLUMNS FROM payments");

        foreach ($columnStmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            if (!empty($column['Field'])) {
                $paymentColumns[] = (string)$column['Field'];
            }
        }

        $availableFields = [];
        foreach (['provider_reference', 'reference', 'payment_date', 'payment_method'] as $field) {
            if (in_array($field, $paymentColumns, true)) {
                $availableFields[] = '`' . $field . '`';
            }
        }

        if ($availableFields && in_array('booking_id', $paymentColumns, true)) {
            $where = ['`booking_id` = ?'];
            $params = [$bookingId];

            if (in_array('payment_status', $paymentColumns, true)) {
                $where[] = "(`payment_status` = 'successful' OR `payment_status` = 'paid')";
            }

            $orderBy = in_array('id', $paymentColumns, true)
                ? ' ORDER BY `id` DESC'
                : '';

            $referenceSql = 'SELECT ' . implode(', ', $availableFields)
                . ' FROM `payments` WHERE ' . implode(' AND ', $where)
                . $orderBy . ' LIMIT 1';

            $referenceStmt = $pdo->prepare($referenceSql);
            $referenceStmt->execute($params);
            $paymentRow = $referenceStmt->fetch(PDO::FETCH_ASSOC);

            if ($paymentRow) {
                $providerReference = trim((string)($paymentRow['provider_reference'] ?? ''));
                $normalReference = trim((string)($paymentRow['reference'] ?? ''));
                $paymentReference = $providerReference !== ''
                    ? $providerReference
                    : $normalReference;

                if ($paymentMethod === '—' && !empty($paymentRow['payment_method'])) {
                    $paymentMethod = receipt_payment_method_label(
                        (string)$paymentRow['payment_method']
                    );
                }
            }
        }
    } catch (Throwable $e) {
        // A missing/older payments table must not prevent receipt generation.
        $paymentReference = '';
        if (defined('ERROR_LOG_FILE')) {
            error_log(
                '[' . date('Y-m-d H:i:s') . '] Receipt payment reference lookup: '
                . $e->getMessage() . PHP_EOL,
                3,
                ERROR_LOG_FILE
            );
        }
    }


    /* =====================================================
       BUSINESS INFORMATION
       ===================================================== */

    $businessAddress =
        defined('BIZ_ADDRESS')
            ? BIZ_ADDRESS
            : '';

    $businessTin =
        defined('BIZ_TIN')
            ? BIZ_TIN
            : '';

    $businessPhone1 =
        defined('BIZ_PHONE_1')
            ? BIZ_PHONE_1
            : '';

    $businessPhone2 =
        defined('BIZ_PHONE_2')
            ? BIZ_PHONE_2
            : '';

    $businessEmail =
        defined('BIZ_EMAIL')
            ? BIZ_EMAIL
            : '';


    /* =====================================================
       DISPLAY VALUES
       ===================================================== */

    $eventDateDisplay =
        receipt_date_label($bookingDate);

    $serviceDisplay =
        $serviceName !== ''
            ? $serviceName
            : 'Photography Service';

    $paymentStatusDisplay =
        strtoupper(
            (string)($b['payment_status'] ?? 'paid')
        );


    /*
     * Escape helper is already supplied by functions.php.
     */

    $e = static function ($value): string {
        return e((string)$value);
    };


    /* =====================================================
       HTML
       ===================================================== */

    $html = '<!doctype html>
<html lang="en">
<head>

<meta charset="utf-8">

<title>
    Receipt ' . $e($rcpNo) . ' — Imagination Nature Studio
</title>

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<meta
    name="robots"
    content="noindex,nofollow"
>

<style>

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
}

body {
    background: #ece9e3;
    color: #181818;
    font-family:
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Roboto,
        Helvetica,
        Arial,
        sans-serif;
    font-size: 14px;
    line-height: 1.55;
    padding: 28px 14px 50px;
}

.receipt-shell {
    width: 100%;
    max-width: 900px;
    margin: 0 auto;
}

.actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    margin: 0 auto 18px;
    flex-wrap: wrap;
}

.btn {
    appearance: none;
    border: 0;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 11px 18px;
    border-radius: 999px;
    background: #111;
    color: #fff;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
}

.btn:hover {
    opacity: .9;
}

.btn.ghost {
    background: #fff;
    color: #171717;
    border: 1px solid #d6d1c8;
}

.page {
    position: relative;
    overflow: hidden;
    background: #fff;
    border-radius: 12px;
    box-shadow:
        0 18px 55px rgba(0, 0, 0, .12);
    padding: 52px 58px;
}

.top-line {
    height: 5px;
    width: 100%;
    background: #1f7a38;
    border-radius: 999px;
    margin-bottom: 28px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 30px;
    padding-bottom: 22px;
    border-bottom: 1px solid #e9e5df;
}

.brand {
    font-size: 22px;
    line-height: 1.2;
    font-weight: 900;
    letter-spacing: .5px;
    color: #15110e;
}

.brand small {
    display: block;
    margin-top: 6px;
    color: #777;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 3px;
}

.business-details {
    margin-top: 10px;
    color: #686868;
    font-size: 12px;
    line-height: 1.7;
}

.header-contact {
    min-width: 220px;
    text-align: right;
    color: #686868;
    font-size: 12px;
    line-height: 1.75;
}

.document-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 20px;
    margin-top: 32px;
}

.doc-title {
    margin: 0;
    color: #1f7a38;
    font-size: 38px;
    line-height: 1;
    font-weight: 900;
    letter-spacing: 5px;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    padding: 7px 14px;
    border-radius: 999px;
    background: #eaf7ed;
    color: #176329;
    border: 1px solid #c9e8cf;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 1.7px;
    white-space: nowrap;
}

.meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    margin-top: 28px;
    border: 1px solid #e7e2da;
    border-radius: 9px;
    overflow: hidden;
}

.meta-cell {
    padding: 13px 16px;
    border-bottom: 1px solid #eeeae4;
}

.meta-cell:nth-child(odd) {
    border-right: 1px solid #eeeae4;
}

.meta-cell:nth-last-child(-n+2) {
    border-bottom: 0;
}

.meta-label {
    display: block;
    color: #898989;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    font-weight: 800;
    margin-bottom: 3px;
}

.meta-value {
    color: #1d1d1d;
    font-weight: 750;
}

.section-title {
    margin: 31px 0 11px;
    padding-bottom: 7px;
    border-bottom: 1px solid #e8e4dd;
    color: #1f7a38;
    font-size: 11px;
    line-height: 1.2;
    text-transform: uppercase;
    letter-spacing: 2.4px;
    font-weight: 900;
}

.info-table {
    width: 100%;
    border-collapse: collapse;
}

.info-table td {
    padding: 7px 0;
    vertical-align: top;
}

.info-label {
    width: 145px;
    color: #777;
}

.info-value {
    color: #171717;
    font-weight: 600;
}

.right {
    text-align: right;
}

.payment-box {
    margin-top: 6px;
    padding: 20px;
    background: #f7fbf7;
    border: 1px solid #cfe8d2;
    border-radius: 10px;
}

.payment-table {
    width: 100%;
    border-collapse: collapse;
}

.payment-table td {
    padding: 7px 0;
    vertical-align: top;
}

.payment-table tr + tr td {
    border-top: 1px solid rgba(40, 110, 55, .10);
}

.payment-table .grand td {
    padding-top: 14px;
    border-top: 2px solid #acd2b1;
    color: #155b25;
    font-size: 18px;
    font-weight: 900;
}

.payment-table .status-row td {
    color: #176329;
    font-weight: 900;
}

.reference {
    margin-top: 12px;
    padding: 10px 12px;
    border-radius: 7px;
    background: #fff;
    border: 1px dashed #b9cdbc;
    color: #555;
    font-size: 11px;
}

.paid-stamp {
    margin: 30px 0 10px;
    text-align: center;
}

.paid-stamp span {
    display: inline-block;
    padding: 10px 32px;
    border: 3px solid #1f7a38;
    border-radius: 8px;
    color: #1f7a38;
    font-size: 19px;
    font-weight: 950;
    letter-spacing: 5px;
    transform: rotate(-4deg);
}

.thank-you {
    margin: 30px 0 0;
    text-align: center;
    color: #343434;
    font-size: 14px;
    font-weight: 650;
}

.footer {
    margin-top: 38px;
    padding-top: 15px;
    border-top: 1px solid #e7e3dc;
    color: #777;
    text-align: center;
    font-size: 10.5px;
    line-height: 1.7;
}

.footer strong {
    color: #4b4b4b;
}

@media (max-width: 700px) {

    body {
        padding: 14px 8px 30px;
    }

    .page {
        padding: 30px 20px;
        border-radius: 8px;
    }

    .header {
        flex-direction: column;
        gap: 14px;
    }

    .header-contact {
        min-width: 0;
        width: 100%;
        text-align: left;
    }

    .document-head {
        align-items: flex-start;
        flex-direction: column;
    }

    .doc-title {
        font-size: 29px;
        letter-spacing: 3px;
    }

    .meta-grid {
        grid-template-columns: 1fr;
    }

    .meta-cell {
        border-right: 0 !important;
    }

    .meta-cell:nth-last-child(-n+2) {
        border-bottom: 1px solid #eeeae4;
    }

    .meta-cell:last-child {
        border-bottom: 0;
    }

    .info-label {
        width: 115px;
    }

    .payment-box {
        padding: 15px;
    }

    .actions {
        justify-content: center;
    }

    .btn {
        flex: 1;
        min-width: 145px;
    }
}

@media print {

    @page {
        size: A4;
        margin: 12mm;
    }

    body {
        background: #fff;
        padding: 0;
    }

    .actions {
        display: none !important;
    }

    .page {
        max-width: none;
        box-shadow: none;
        border-radius: 0;
        padding: 10px 12px;
    }

    .top-line {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .status-pill,
    .payment-box,
    .paid-stamp span {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}

</style>
</head>

<body>

<div class="receipt-shell">

    <div class="actions">

        <button
            type="button"
            class="btn"
            onclick="window.print()"
        >
            🖨 Print / Save as PDF
        </button>

        <a
            class="btn ghost"
            href="' . $e(SITE_URL) . '/admin/booking-view.php?id=' . (int)$bookingId . '"
        >
            ← Back to Booking
        </a>

    </div>


    <div class="page">

        <div class="top-line"></div>


        <!-- =================================================
             BUSINESS HEADER
             ================================================= -->

        <div class="header">

            <div>

                <div class="brand">
                    IMAGINATION NATURE STUDIO

                    <small>
                        PHOTOGRAPHY &amp; CREATIVE STUDIO
                    </small>
                </div>

                <div class="business-details">
                    ' . $e($businessAddress) . '<br>
                    TIN: ' . $e($businessTin) . '
                </div>

            </div>


            <div class="header-contact">

                ' . $e($businessPhone1) . '<br>
                ' . $e($businessPhone2) . '<br>
                ' . $e($businessEmail) . '

            </div>

        </div>


        <!-- =================================================
             RECEIPT TITLE
             ================================================= -->

        <div class="document-head">

            <div>

                <h1 class="doc-title">
                    RECEIPT
                </h1>

            </div>

            <div class="status-pill">
                PAYMENT RECEIVED
            </div>

        </div>


        <!-- =================================================
             RECEIPT META
             ================================================= -->

        <div class="meta-grid">

            <div class="meta-cell">

                <span class="meta-label">
                    Receipt Number
                </span>

                <span class="meta-value">
                    ' . $e($rcpNo) . '
                </span>

            </div>


            <div class="meta-cell">

                <span class="meta-label">
                    Issued
                </span>

                <span class="meta-value">
                    ' . $e($issuedAt) . '
                </span>

            </div>


            <div class="meta-cell">

                <span class="meta-label">
                    Booking Number
                </span>

                <span class="meta-value">
                    ' . $e($bookingNumber) . '
                </span>

            </div>


            <div class="meta-cell">

                <span class="meta-label">
                    Invoice Number
                </span>

                <span class="meta-value">
                    ' . $e($invoiceNumber !== '' ? $invoiceNumber : '—') . '
                </span>

            </div>

        </div>


        <!-- =================================================
             CUSTOMER
             ================================================= -->

        <div class="section-title">
            Received From
        </div>

        <table class="info-table">

            <tr>
                <td class="info-label">Name</td>
                <td class="info-value">' . $e($customerName) . '</td>
            </tr>

            <tr>
                <td class="info-label">Phone</td>
                <td class="info-value">' . $e($customerPhone ?: '—') . '</td>
            </tr>

            <tr>
                <td class="info-label">Email</td>
                <td class="info-value">' . $e($customerEmail ?: '—') . '</td>
            </tr>

            ' .
            (
                $customerCompany !== ''
                ? '
            <tr>
                <td class="info-label">Company</td>
                <td class="info-value">' .
                    $e($customerCompany) .
                '</td>
            </tr>'
                : ''
            )
            . '

        </table>


        <!-- =================================================
             BOOKING
             ================================================= -->

        <div class="section-title">
            Booking Details
        </div>

        <table class="info-table">

            <tr>
                <td class="info-label">Service</td>
                <td class="info-value">' . $e($serviceDisplay) . '</td>
            </tr>

            ' .
            (
                $packageName !== ''
                ? '
            <tr>
                <td class="info-label">Package</td>
                <td class="info-value">' .
                    $e($packageName) .
                '</td>
            </tr>'
                : ''
            )
            . '

            <tr>
                <td class="info-label">Event Date</td>
                <td class="info-value">' . $e($eventDateDisplay) . '</td>
            </tr>

            <tr>
                <td class="info-label">Time</td>
                <td class="info-value">' .
                    $e(
                        $startTime !== ''
                            ? $startTime
                            : '—'
                    )
                    .
                    (
                        $endTime !== ''
                            ? ' – ' . $e($endTime)
                            : ''
                    )
                . '</td>
            </tr>

            <tr>
                <td class="info-label">Location</td>
                <td class="info-value">' .
                    $e($location !== '' ? $location : '—')
                . '</td>
            </tr>

        </table>


        <!-- =================================================
             PAYMENT
             ================================================= -->

        <div class="section-title">
            Payment Details
        </div>

        <div class="payment-box">

            <table class="payment-table">

                <tr>
                    <td>Total Amount</td>
                    <td class="right">
                        ' . $e(rwf($totalAmount)) . '
                    </td>
                </tr>

                <tr>
                    <td>Deposit Required</td>
                    <td class="right">
                        ' . $e(rwf($depositAmount)) . '
                    </td>
                </tr>

                <tr>
                    <td>Amount Paid</td>
                    <td class="right">
                        ' . $e(rwf($amountPaid)) . '
                    </td>
                </tr>

                <tr>
                    <td>Remaining Balance</td>
                    <td class="right">
                        ' . $e(rwf($balance)) . '
                    </td>
                </tr>

                <tr>
                    <td>Payment Method</td>
                    <td class="right">
                        ' . $e($paymentMethod) . '
                    </td>
                </tr>

                <tr class="status-row">
                    <td>Payment Status</td>
                    <td class="right">
                        ' . $e($paymentStatusDisplay) . '
                    </td>
                </tr>

            </table>

            ' .
            (
                $paymentReference !== ''
                ? '
            <div class="reference">
                <strong>Payment Reference:</strong>
                ' . $e($paymentReference) . '
            </div>'
                : ''
            )
            . '

        </div>


        <!-- =================================================
             PAID STAMP
             ================================================= -->

        <div class="paid-stamp">
            <span>PAID</span>
        </div>


        <!-- =================================================
             THANK YOU
             ================================================= -->

        <p class="thank-you">
            Thank you for choosing
            Imagination Nature Studio.
        </p>


        <!-- =================================================
             FOOTER
             ================================================= -->

        <div class="footer">

            <strong>
                IMAGINATION NATURE STUDIO
            </strong>
            <br>

            ' . $e($businessAddress) . '
            · ' . $e($businessPhone1) . '
            · ' . $e($businessEmail) . '

            <br>

            TIN:
            ' . $e($businessTin) . '

        </div>

    </div>

</div>

</body>
</html>';


    /* =====================================================
       SAVE RECEIPT
       ===================================================== */

    if (!defined('RECEIPT_DIR')) {
        throw new RuntimeException(
            'RECEIPT_DIR is not defined.'
        );
    }

    if (!is_dir(RECEIPT_DIR)) {

        if (
            !mkdir(
                RECEIPT_DIR,
                0755,
                true
            )
            &&
            !is_dir(RECEIPT_DIR)
        ) {
            throw new RuntimeException(
                'Unable to create receipt directory.'
            );
        }
    }


    $file =
        rtrim(RECEIPT_DIR, '/\\')
        . DIRECTORY_SEPARATOR
        . $rcpNo
        . '.html';


    receipt_safe_file_write(
        $file,
        $html
    );


    /* =====================================================
       UPDATE BOOKING
       ===================================================== */

    $updateStmt = $pdo->prepare(
        "UPDATE bookings

         SET
            receipt_path = ?,
            receipt_issued_at =
                COALESCE(
                    receipt_issued_at,
                    NOW()
                )

         WHERE id = ?"
    );

    $updateStmt->execute([
        'receipts/' . $rcpNo . '.html',
        $bookingId
    ]);


    /* =====================================================
       RETURN WEB-RELATIVE PATH
       ===================================================== */

    return 'receipts/' . $rcpNo . '.html';
}
