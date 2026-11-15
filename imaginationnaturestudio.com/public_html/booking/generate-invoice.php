<?php
/**
 * Generates an HTML INVOICE for a booking.
 *
 * Payment status:
 * - unpaid  -> PAYMENT DUE
 * - partial -> PARTIALLY PAID
 * - paid    -> PAYMENT RECEIVED / PAID
 *
 * Saved to:
 * /receipts/INV-YYYY-NNNNN.html
 *
 * Customers can view it in the browser
 * and print/save as PDF.
 */

require_once __DIR__ . '/../includes/functions.php';


function generate_invoice(int $bookingId): string
{
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
            c.location,
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
        throw new RuntimeException('Booking not found');
    }

    /* =====================================================
       LOAD OPTIONAL WEDDING / MULTI-DAY SCHEDULE
       booking_dates schema: id, booking_id, event_date, event_type,
       start_time, end_time, created_at.
       ===================================================== */
    $weddingDates = [];
    try {
        $datesStmt = $pdo->prepare(
            "SELECT event_date, event_type, start_time, end_time
             FROM booking_dates
             WHERE booking_id = ?
             ORDER BY event_date ASC, start_time ASC, id ASC"
        );
        $datesStmt->execute([$bookingId]);
        $weddingDates = $datesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        // A booking can still have an invoice if the optional schedule cannot be loaded.
        error_log('Invoice schedule load failed for booking ' . $bookingId . ': ' . $e->getMessage());
        $weddingDates = [];
    }


    /* =====================================================
       LOAD CUSTOM BOOKING ITEMS / PRICING
       ===================================================== */

    $bookingItems = [];
    try {
        $itemsStmt = $pdo->prepare(
            "SELECT item_name
             FROM booking_items
             WHERE booking_id = ?
             ORDER BY id ASC"
        );
        $itemsStmt->execute([$bookingId]);
        $bookingItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        // Keep legacy invoices working if the optional custom-items table is unavailable.
        error_log('Invoice custom items load failed for booking ' . $bookingId . ': ' . $e->getMessage());
        $bookingItems = [];
    }

    // Item names are descriptive only. Their individual prices are never shown.
    $baseAmount = 0.0;
    $extrasTotal = 0.0;
    $discountAmount = 0.0;
    $pricingSubtotal = (float)($b['total_amount'] ?? 0);

    /* =====================================================
       ENSURE INVOICE NUMBER EXISTS
       ===================================================== */

    $invNo = $b['invoice_number'];

    if (empty($invNo)) {

        $invNo =
            'INV-'
            . substr(
                $b['booking_number'],
                4
            );

        $pdo->prepare(
            "UPDATE bookings
             SET invoice_number=?
             WHERE id=?"
        )->execute([
            $invNo,
            $bookingId
        ]);
    }


    /* =====================================================
       PAYMENT CALCULATIONS
       ===================================================== */

    $totalDue =
        (float)$b['total_amount'];

    $depositDue =
        (float)$b['deposit_amount'];

    $paidSoFar =
        (float)$b['amount_paid'];

    $balanceDue =
        max(
            0,
            $totalDue - $paidSoFar
        );


    /* =====================================================
       DETERMINE PAYMENT STATUS
       ===================================================== */

    /*
     * Use database payment_status,
     * but also protect the invoice by checking
     * the actual amount paid.
     */

    $paymentStatus =
        strtolower(
            trim(
                (string)(
                    $b['payment_status']
                    ?? ''
                )
            )
        );


    /*
     * If balance is zero and the full amount
     * has been paid, make sure invoice says PAID.
     */

    if (
        $balanceDue <= 0
        &&
        $paidSoFar >= $totalDue
        &&
        $totalDue > 0
    ) {

        $paymentStatus = 'paid';

    } elseif ($paidSoFar > 0) {

        $paymentStatus = 'partial';

    } else {

        $paymentStatus = 'unpaid';
    }


    /* =====================================================
       STATUS DISPLAY
       ===================================================== */

    if ($paymentStatus === 'paid') {

        $statusLabel =
            'PAYMENT RECEIVED';

        $statusClass =
            'paid';

    } elseif ($paymentStatus === 'partial') {

        $statusLabel =
            'PARTIALLY PAID';

        $statusClass =
            'partial';

    } else {

        $statusLabel =
            'PAYMENT DUE';

        $statusClass =
            'due';
    }


    /* =====================================================
       DUE DATE
       ===================================================== */

    /*
     * If fully paid, there is no remaining payment due.
     */

    if ($paymentStatus === 'paid') {

        $dueDate = 'PAID';

    } else {

        $dueDate =
            date(
                'Y-m-d',
                strtotime('+7 days')
            );
    }


    /* =====================================================
       ISSUE DATE
       ===================================================== */

    $issuedAt =
        date('Y-m-d H:i');


    /* =====================================================
       PAYMENT INSTRUCTION CONTENT
       ===================================================== */

    if ($paymentStatus === 'paid') {

        $paymentBoxTitle =
            'Payment Received';

        $paymentBoxContent =
            'Payment has been received in full.<br><br>'
            . '<strong>No payment is currently due.</strong><br><br>'
            . '<span class="muted">'
            . 'Your official receipt has been or will be issued by our team.'
            . '</span>';

    } elseif ($paymentStatus === 'partial') {

        $paymentBoxTitle =
            'Payment Instructions';

        $paymentBoxContent =
            'Thank you for your payment.<br>'
            . 'The remaining balance is '
            . '<strong>'
            . e(rwf($balanceDue))
            . '</strong>.<br><br>'
            . 'Payment Methods: Cash · Mobile Money · Bank Transfer<br>'
            . 'Contact us: '
            . e(BIZ_PHONE_1)
            . ' · '
            . e(BIZ_EMAIL)
            . '<br><br>'
            . '<span class="muted">'
            . 'An official receipt will be issued once the payment is fully confirmed.'
            . '</span>';

    } else {

        $paymentBoxTitle =
            'Payment Instructions';

        $paymentBoxContent =
            'Please pay the total due amount to confirm your booking.<br><br>'
            . 'Payment Methods: Cash · Mobile Money · Bank Transfer<br>'
            . 'Contact us: '
            . e(BIZ_PHONE_1)
            . ' · '
            . e(BIZ_EMAIL)
            . '<br><br>'
            . '<span class="muted">'
            . 'An official receipt will be issued once payment is confirmed.'
            . '</span>';
    }


    /* =====================================================
       HTML INVOICE
       ===================================================== */

    $html = '<!doctype html>

<html lang="en">

<head>

<meta charset="utf-8">

<title>
Invoice ' . e($invNo) . '
— Imagination Nature Studio
</title>

<meta
    name="viewport"
    content="width=device-width,initial-scale=1"
>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #e9e4dc;
    font-family:
        -apple-system,
        Segoe UI,
        Roboto,
        Helvetica,
        Arial,
        sans-serif;
    color: #1a1a1a;
    font-size: 14px;
    line-height: 1.5;
    padding: 30px 12px;
}

.page {
    max-width: 820px;
    margin: 0 auto;
    background: #fff;
    padding: 48px 56px;
    box-shadow:
        0 10px 40px rgba(0,0,0,.12);
    border-radius: 6px;
}


/* =====================================================
   HEADER
   ===================================================== */

.hdr {
    display: flex;
    justify-content: space-between;
    border-bottom: 3px solid #e07a2f;
    padding-bottom: 16px;
}

.brand {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: .5px;
    color: #14100d;
}

.brand small {
    display: block;
    font-weight: 400;
    font-size: 11px;
    color: #666;
    letter-spacing: 3px;
    margin-top: 3px;
}

.muted {
    color: #666;
    font-size: 12px;
}


/* =====================================================
   INVOICE TITLE
   ===================================================== */

.doc-title {
    font-size: 34px;
    font-weight: 800;
    color: #e07a2f;
    letter-spacing: 4px;
    margin: 26px 0 6px;
}


/* =====================================================
   PAYMENT STATUS PILL
   ===================================================== */

.pill {
    display: inline-block;
    padding: 5px 14px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.5px;
}

.pill.due {
    background: #fff3e6;
    color: #b35a10;
}

.pill.partial {
    background: #fff7d6;
    color: #8a6a00;
}

.pill.paid {
    background: #e8f6ed;
    color: #237a43;
}


/* =====================================================
   HEADINGS
   ===================================================== */

h2 {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 2.5px;
    margin: 28px 0 10px;
    color: #e07a2f;
    border-bottom: 1px solid #eee;
    padding-bottom: 6px;
}


/* =====================================================
   TABLE
   ===================================================== */

table {
    width: 100%;
    border-collapse: collapse;
}

td {
    padding: 6px 0;
    vertical-align: top;
}

.right {
    text-align: right;
}


/* =====================================================
   ITEMS
   ===================================================== */


.wedding-schedule {
    margin-top: 10px;
}

.wedding-schedule th {
    text-align: left;
    font-size: 11px;
    letter-spacing: 1px;
    color: #666;
    border-bottom: 1px solid #ddd;
    padding: 10px 0;
}

.wedding-schedule td {
    border-bottom: 1px solid #eee;
    padding: 10px 0;
}

.items th {
    text-align: left;
    font-size: 11px;
    letter-spacing: 1px;
    color: #666;
    border-bottom: 1px solid #ddd;
    padding: 10px 0;
}

.items td {
    border-bottom: 1px solid #eee;
    padding: 12px 0;
}


/* =====================================================
   TOTAL BOX
   ===================================================== */

.total-box {
    margin-top: 22px;
    padding: 18px;
    background: #faf7f3;
    border: 1px solid #eee;
    border-radius: 6px;
}

.total-box .grand {
    font-size: 17px;
    font-weight: 800;
    color: #b35a10;
    border-top: 1px solid #ccc;
    padding-top: 10px;
}


/* =====================================================
   PAYMENT BOX
   ===================================================== */

.pay-box {
    margin-top: 22px;
    padding: 18px;
    border-radius: 6px;
}

.pay-box.due {
    background: #fff3e6;
    border: 1px dashed #e0a56e;
}

.pay-box.partial {
    background: #fff8df;
    border: 1px dashed #d9bd54;
}

.pay-box.paid {
    background: #edf8f1;
    border: 1px dashed #7dbb94;
}

.pay-box strong {
    color: #b35a10;
}

.pay-box.paid strong {
    color: #237a43;
}


/* =====================================================
   PAID STAMP
   ===================================================== */

.paid-stamp {
    display: inline-block;
    margin-top: 20px;
    padding: 8px 22px;
    border: 2px solid #237a43;
    border-radius: 6px;
    color: #237a43;
    font-size: 16px;
    font-weight: 800;
    letter-spacing: 4px;
}


/* =====================================================
   FOOTER
   ===================================================== */

.footer {
    margin-top: 44px;
    font-size: 11px;
    color: #666;
    text-align: center;
    border-top: 1px solid #eee;
    padding-top: 14px;
}


/* =====================================================
   ACTIONS
   ===================================================== */

.actions {
    max-width: 820px;
    margin: 0 auto 20px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 999px;
    background: #e07a2f;
    color: #fff;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    border: 0;
    cursor: pointer;
}

.btn.ghost {
    background: #fff;
    color: #14100d;
    border: 1px solid #ccc;
}


/* =====================================================
   PRINT
   ===================================================== */

@media print {

    body {
        background: #fff;
        padding: 0;
    }

    .page {
        box-shadow: none;
        border-radius: 0;
        padding: 30px 40px;
    }

    .actions {
        display: none;
    }
}


/* =====================================================
   MOBILE
   ===================================================== */

@media (max-width: 640px) {

    .page {
        padding: 26px 20px;
    }

    .hdr {
        flex-direction: column;
        gap: 10px;
    }

    .doc-title {
        font-size: 24px;
        letter-spacing: 2px;
    }
}

</style>

</head>


<body>


<div class="actions">

    <button
        class="btn"
        onclick="window.print()"
    >
        🖨 Print / Save as PDF
    </button>


    <a
        class="btn ghost"
        href="' . e(SITE_URL) . '/booking.php"
    >
        Close
    </a>

</div>


<div class="page">


    <!-- =================================================
         HEADER
         ================================================= -->

    <div class="hdr">

        <div>

            <div class="brand">

                IMAGINATION NATURE STUDIO

                <small>
                    PHOTOGRAPHY &amp; CREATIVE STUDIO
                </small>

            </div>


            <div
                class="muted"
                style="margin-top:8px"
            >
                ' . e(BIZ_ADDRESS) . '
            </div>


            <div class="muted">
                TIN: ' . e(BIZ_TIN) . '
            </div>

        </div>


        <div class="right">

            <div class="muted">
                ' . e(BIZ_PHONE_1) . '
            </div>

            <div class="muted">
                ' . e(BIZ_PHONE_2) . '
            </div>

            <div class="muted">
                ' . e(BIZ_EMAIL) . '
            </div>

        </div>

    </div>


    <!-- =================================================
         TITLE
         ================================================= -->

    <div class="doc-title">
        INVOICE
    </div>


    <span class="pill ' . e($statusClass) . '">
        ' . e($statusLabel) . '
    </span>


    <!-- =================================================
         INVOICE INFORMATION
         ================================================= -->

    <table style="margin-top:20px">

        <tr>

            <td>
                <strong>Invoice No:</strong>
                ' . e($invNo) . '
            </td>

            <td class="right">

                <strong>
                    Issued:
                </strong>

                ' . e($issuedAt) . '

            </td>

        </tr>


        <tr>

            <td>

                <strong>
                    Booking No:
                </strong>

                ' . e(
                    $b['booking_number']
                ) . '

            </td>


            <td class="right">

                <strong>
                    Due Date:
                </strong>

                ' . e($dueDate) . '

            </td>

        </tr>

    </table>


    <!-- =================================================
         BILLED TO
         ================================================= -->

    <h2>
        Billed To
    </h2>


    <table>

        <tr>

            <td style="width:140px">
                Name:
            </td>

            <td>
                ' . e(
                    $b['full_name']
                ) . '
            </td>

        </tr>


        <tr>

            <td>
                Phone:
            </td>

            <td>
                ' . e(
                    $b['phone']
                ) . '
            </td>

        </tr>


        <tr>

            <td>
                Email:
            </td>

            <td>
                ' . e(
                    $b['email']
                ) . '
            </td>

        </tr>


        ' . (
            $b['company']
            ?
            '<tr>
                <td>Company:</td>
                <td>'
                . e($b['company'])
                .
                '</td>
            </tr>'
            :
            ''
        ) . '


        <tr>

            <td>
                Address:
            </td>

            <td>
                ' . e(
                    $b['location']
                ) . '
            </td>

        </tr>

    </table>


    <!-- =================================================
         BOOKING DETAILS
         ================================================= -->

    <h2>
        Booking Details
    </h2>


    <table>

        <tr>

            <td style="width:140px">
                Service:
            </td>

            <td>
                ' . e(
                    $b['service_name']
                ) . '
            </td>

        </tr>


        ' . (
            $b['package_name']
            ?
            '<tr>
                <td>Package:</td>
                <td>'
                . e($b['package_name'])
                .
                '</td>
            </tr>'
            :
            ''
        ) . '


        <tr>

            <td>
                Event Date:
            </td>

            <td>
                ' . e(
                    $b['booking_date']
                ) . '
            </td>

        </tr>


        <tr>

            <td>
                Time:
            </td>

            <td>

                ' . e(
                    $b['start_time']
                ) . '

                –

                ' . e(
                    $b['end_time']
                ) . '

            </td>

        </tr>


        <tr>

            <td>
                Location:
            </td>

            <td>
                ' . e(
                    $b['location']
                ) . '
            </td>

        </tr>
</table>


    <!-- =================================================
         ITEMS
         ================================================= -->

    ' . (!empty($weddingDates) ? '

    <h2>
        Wedding Schedule
    </h2>

    <table class="wedding-schedule">
        <thead>
            <tr>
                <th>Day</th>
                <th>Date</th>
                <th>Event / Day Type</th>
                <th>Time</th>
            </tr>
        </thead>
        <tbody>
            ' . (function () use ($weddingDates) {
                $rows = '';
                foreach ($weddingDates as $i => $wd) {
                    $start = !empty($wd['start_time']) ? substr((string)$wd['start_time'], 0, 5) : '';
                    $end   = !empty($wd['end_time']) ? substr((string)$wd['end_time'], 0, 5) : '';
                    $time  = trim($start . (($start || $end) ? ' – ' : '') . $end, ' –');
                    $rows .= '<tr>'
                        . '<td>Day ' . ((int)$i + 1) . '</td>'
                        . '<td>' . e($wd['event_date'] ?? '') . '</td>'
                        . '<td>' . e($wd['event_type'] ?? '') . '</td>'
                        . '<td>' . e($time) . '</td>'
                        . '</tr>';
                }
                return $rows;
            })() . '
        </tbody>
    </table>

    ' : '') . '

    <h2>
        Items
    </h2>


    <table class="items">

        <thead>

            <tr>

                <th>
                    Description
                </th>

                <th class="right">
                    Amount
                </th>

            </tr>

        </thead>


        <tbody>

            <tr>
                <td colspan="2">
                    <strong>' . e($b['service_name'])
                    . ($b['package_name'] ? ' — ' . e($b['package_name']) : '') . '</strong>
                    <br><span class="muted">Package · Booking ' . e($b['booking_number']) . '</span>
                </td>
            </tr>

            ' . (function () use ($bookingItems) {
                $rows = '';
                foreach ($bookingItems as $item) {
                    $rows .= '<tr><td colspan="2">Additional item: ' . e($item['item_name'] ?? 'Additional item') . '</td></tr>';
                }
                return $rows;
            })() . '

        </tbody>

    </table>


    <!-- =================================================
         TOTALS
         ================================================= -->

    <div class="total-box">

        <table>


            <tr>
                <td><strong>Agreed Total:</strong></td>
                <td class="right"><strong>' . e(rwf($totalDue)) . '</strong></td>
            </tr>

            <tr>
                <td>
                    Amount Paid:
                </td>

                <td class="right">

                    −
                    ' . e(
                        rwf($paidSoFar)
                    ) . '

                </td>

            </tr>


            <tr>

                <td>
                    Deposit Required:
                </td>

                <td class="right">

                    ' . e(
                        rwf($depositDue)
                    ) . '

                </td>

            </tr>


            <tr>

                <td class="grand">
                    BALANCE DUE:
                </td>

                <td class="right grand">

                    ' . e(
                        rwf($balanceDue)
                    ) . '

                </td>

            </tr>

        </table>

    </div>


    <!-- =================================================
         PAYMENT BOX
         ================================================= -->

    <div class="pay-box ' . e($statusClass) . '">

        <strong>
            ' . e($paymentBoxTitle) . '
        </strong>

        <br><br>

        ' . $paymentBoxContent . '

    </div>


    <!-- =================================================
         PAID STAMP
         ================================================= -->

    ' . (
        $paymentStatus === 'paid'
        ?
        '<div style="text-align:center">
            <div class="paid-stamp">
                PAID
            </div>
        </div>'
        :
        ''
    ) . '


    <p style="margin-top:28px">

        Thank you for choosing
        Imagination Nature Studio.

    </p>


    <!-- =================================================
         FOOTER
         ================================================= -->

    <div class="footer">

        ' . e(BIZ_ADDRESS) . '

        ·

        ' . e(BIZ_PHONE_1) . '

        ·

        ' . e(BIZ_EMAIL) . '

        · TIN

        ' . e(BIZ_TIN) . '

    </div>


</div>


</body>

</html>';


    /* =====================================================
       SAVE INVOICE
       ===================================================== */

    if (!is_dir(RECEIPT_DIR)) {

        mkdir(
            RECEIPT_DIR,
            0755,
            true
        );
    }


    $file =
        RECEIPT_DIR
        . $invNo
        . '.html';


    $written = file_put_contents($file, $html, LOCK_EX);
    if ($written === false) {
        throw new RuntimeException('Could not write invoice file. Check the receipts directory permissions.');
    }


    /* =====================================================
       SAVE INVOICE PATH + ISSUE DATE
       ===================================================== */

    $pdo->prepare(
        "UPDATE bookings
         SET
            invoice_path=?,
            invoice_issued_at=NOW()
         WHERE id=?"
    )->execute([
        'receipts/' . $invNo . '.html',
        $bookingId
    ]);


    return
        'receipts/'
        . $invNo
        . '.html';
}