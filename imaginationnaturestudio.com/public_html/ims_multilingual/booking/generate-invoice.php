<?php

require_once __DIR__ . '/../includes/functions.php';


function generate_invoice(int $bookingId): string
{
    $pdo = db();


    /*
    |--------------------------------------------------------------------------
    | GET BOOKING
    |--------------------------------------------------------------------------
    */

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

    $b = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$b) {
        throw new RuntimeException('Booking not found.');
    }


    /*
    |--------------------------------------------------------------------------
    | INVOICE NUMBER
    |--------------------------------------------------------------------------
    */

    $invoiceNumber = trim(
        (string)($b['invoice_number'] ?? '')
    );


    if ($invoiceNumber === '') {

        $bookingNumber = (string)(
            $b['booking_number'] ?? $bookingId
        );


        if (strlen($bookingNumber) > 4) {

            $invoiceNumber =
                'INV-' .
                substr($bookingNumber, 4);

        } else {

            $invoiceNumber =
                'INV-' .
                $bookingNumber;
        }


        $updateInvoice = $pdo->prepare(
            "UPDATE bookings
             SET invoice_number = ?
             WHERE id = ?"
        );


        $updateInvoice->execute([
            $invoiceNumber,
            $bookingId
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | PAYMENT CALCULATIONS
    |--------------------------------------------------------------------------
    */

    $total = (float)(
        $b['total_amount'] ?? 0
    );


    $deposit = (float)(
        $b['deposit_amount'] ?? 0
    );


    $paid = (float)(
        $b['amount_paid'] ?? 0
    );


    /*
    | If deposit is missing,
    | calculate 30%.
    */

    if ($deposit <= 0 && $total > 0) {

        $deposit = round(
            $total * 0.30,
            2
        );
    }


    $balance = max(
        0,
        $total - $paid
    );


    /*
    |--------------------------------------------------------------------------
    | PAYMENT STATUS
    |--------------------------------------------------------------------------
    */

    if (
        $total > 0 &&
        $paid >= $total
    ) {

        $paymentStatus = 'paid';

        $statusLabel = 'PAYMENT RECEIVED';

        $dueDate = 'PAID';

    } elseif ($paid > 0) {

        $paymentStatus = 'partial';

        $statusLabel = 'PARTIALLY PAID';

        $dueDate = date(
            'Y-m-d',
            strtotime('+7 days')
        );

    } else {

        $paymentStatus = 'unpaid';

        $statusLabel = 'PAYMENT DUE';

        $dueDate = date(
            'Y-m-d',
            strtotime('+7 days')
        );
    }


    /*
    |--------------------------------------------------------------------------
    | COMPANY INFORMATION
    |--------------------------------------------------------------------------
    */

    $businessAddress =
        defined('BIZ_ADDRESS')
            ? BIZ_ADDRESS
            : 'Kigali, Rwanda';


    $businessTin =
        defined('BIZ_TIN')
            ? BIZ_TIN
            : '';


    $businessPhone =
        defined('BIZ_PHONE_1')
            ? BIZ_PHONE_1
            : '';


    $businessEmail =
        defined('BIZ_EMAIL')
            ? BIZ_EMAIL
            : '';


    /*
    |--------------------------------------------------------------------------
    | COMPANY ROW
    |--------------------------------------------------------------------------
    */

    $companyRow = '';


    if (
        !empty($b['company'])
    ) {

        $companyRow =
            '<tr>
                <td>Company</td>
                <td>' .
                e($b['company']) .
                '</td>
             </tr>';
    }


    /*
    |--------------------------------------------------------------------------
    | PACKAGE ROW
    |--------------------------------------------------------------------------
    */

    $packageRow = '';


    if (
        !empty($b['package_name'])
    ) {

        $packageRow =
            '<tr>
                <td>Package</td>
                <td>' .
                e($b['package_name']) .
                '</td>
             </tr>';
    }


    /*
    |--------------------------------------------------------------------------
    | WEDDING SCHEDULE
    |--------------------------------------------------------------------------
    */

    $weddingRows = '';


    try {

        $scheduleStmt = $pdo->prepare(
            "SELECT
                id,
                event_date,
                event_type,
                start_time,
                end_time
             FROM booking_dates
             WHERE booking_id = ?
             ORDER BY
                event_date ASC,
                start_time ASC,
                id ASC"
        );


        $scheduleStmt->execute([
            $bookingId
        ]);


        $scheduleRows =
            $scheduleStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        foreach (
            $scheduleRows as $index => $day
        ) {

            $startTime = '';


            if (
                !empty($day['start_time'])
            ) {

                $startTime =
                    substr(
                        (string)$day['start_time'],
                        0,
                        5
                    );
            }


            $endTime = '';


            if (
                !empty($day['end_time'])
            ) {

                $endTime =
                    substr(
                        (string)$day['end_time'],
                        0,
                        5
                    );
            }


            $time = '';


            if (
                $startTime !== '' &&
                $endTime !== ''
            ) {

                $time =
                    $startTime .
                    ' - ' .
                    $endTime;

            } elseif (
                $startTime !== ''
            ) {

                $time = $startTime;

            } elseif (
                $endTime !== ''
            ) {

                $time = $endTime;
            }


            $weddingRows .=
                '<tr>' .
                '<td>Day ' .
                ((int)$index + 1) .
                '</td>' .
                '<td>' .
                e(
                    $day['event_date'] ?? ''
                ) .
                '</td>' .
                '<td>' .
                e(
                    $day['event_type'] ?? ''
                ) .
                '</td>' .
                '<td>' .
                e($time) .
                '</td>' .
                '</tr>';
        }

    } catch (Throwable $e) {

        /*
        | booking_dates may not exist on an older
        | installation. Invoice should still work.
        */

        $weddingRows = '';
    }


    $weddingSchedule = '';


    if (
        $weddingRows !== ''
    ) {

        $weddingSchedule = '
        <h2>Wedding Schedule</h2>

        <table>

            <thead>

                <tr>
                    <th>Day</th>
                    <th>Date</th>
                    <th>Event</th>
                    <th>Time</th>
                </tr>

            </thead>

            <tbody>

                ' .
                $weddingRows .
                '

            </tbody>

        </table>';
    }


    /*
    |--------------------------------------------------------------------------
    | PAYMENT MESSAGE
    |--------------------------------------------------------------------------
    */

    if (
        $paymentStatus === 'paid'
    ) {

        $paymentMessage = '
            <strong>Payment Received</strong>
            <br><br>
            Payment has been received in full.
            No payment is currently due.';

    } elseif (
        $paymentStatus === 'partial'
    ) {

        $paymentMessage = '
            <strong>Partially Paid</strong>
            <br><br>

            Amount Paid:
            <strong>' .
            e(rwf($paid)) .
            '</strong>

            <br><br>

            Remaining Balance:
            <strong>' .
            e(rwf($balance)) .
            '</strong>';

    } else {

        $paymentMessage = '
            <strong>Payment Due</strong>
            <br><br>

            Deposit Required (30%):
            <strong>' .
            e(rwf($deposit)) .
            '</strong>

            <br><br>

            Total Booking Amount:
            <strong>' .
            e(rwf($total)) .
            '</strong>';
    }


    /*
    |--------------------------------------------------------------------------
    | PAID STAMP
    |--------------------------------------------------------------------------
    */

    $paidStamp = '';


    if (
        $paymentStatus === 'paid'
    ) {

        $paidStamp = '
            <div class="paid-stamp">
                PAID
            </div>';
    }


    /*
    |--------------------------------------------------------------------------
    | CUSTOMER DATA
    |--------------------------------------------------------------------------
    */

    $customerName =
        e($b['full_name'] ?? '');


    $customerPhone =
        e($b['phone'] ?? '');


    $customerEmail =
        e($b['email'] ?? '');


    $customerLocation =
        e($b['location'] ?? '');


    $serviceName =
        e($b['service_name'] ?? '');


    $bookingNumber =
        e($b['booking_number'] ?? '');


    $paymentMethodRaw = trim((string)($b['payment_method'] ?? ''));
    $paymentMethodLabel = $paymentMethodRaw !== ''
        ? ucwords(str_replace('_', ' ', $paymentMethodRaw))
        : 'Not specified';


    $bookingDate =
        e($b['booking_date'] ?? '');


    $startTime =
        e($b['start_time'] ?? '');


    $endTime =
        e($b['end_time'] ?? '');


    $people =
        (int)($b['number_of_people'] ?? 0);


    /*
    |--------------------------------------------------------------------------
    | HTML INVOICE
    |--------------------------------------------------------------------------
    */

    $html = '<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Invoice ' .
    e($invoiceNumber) .
    '
</title>


<style>

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    padding: 30px;

    background: #eee8df;

    color: #222;

    font-family:
        Arial,
        Helvetica,
        sans-serif;
}


.page {

    width: 100%;

    max-width: 850px;

    margin: 0 auto;

    background: #ffffff;

    padding: 45px;

    box-shadow:
        0 5px 25px
        rgba(0,0,0,.15);
}


.top {

    display: flex;

    justify-content: space-between;

    gap: 30px;

    padding-bottom: 20px;

    border-bottom:
        3px solid #e07a2f;
}


.brand {

    font-size: 24px;

    font-weight: 700;

    letter-spacing: .5px;
}


.muted {

    color: #777;

    font-size: 12px;

    line-height: 1.6;
}


.invoice-title {

    margin-top: 30px;

    font-size: 32px;

    letter-spacing: 4px;

    color: #e07a2f;
}


.status {

    display: inline-block;

    padding: 7px 15px;

    margin-top: 5px;

    border-radius: 30px;

    background: #fff3e6;

    color: #a8520c;

    font-size: 12px;

    font-weight: 700;

    letter-spacing: .5px;
}


h2 {

    margin-top: 30px;

    padding-bottom: 8px;

    border-bottom:
        1px solid #ddd;

    color: #e07a2f;

    font-size: 12px;

    letter-spacing: 2px;

    text-transform: uppercase;
}


table {

    width: 100%;

    border-collapse: collapse;
}


th,
td {

    padding: 10px;

    border-bottom:
        1px solid #eeeeee;

    text-align: left;

    vertical-align: top;
}


th {

    background: #faf6f0;

    font-size: 12px;
}


.right {

    text-align: right;
}


.info-box {

    margin-top: 25px;

    padding: 20px;

    background: #faf6f0;

    border:
        1px solid #e2ddd5;
}


.total-row td {

    font-size: 16px;

    font-weight: 700;
}


.payment-box {

    margin-top: 25px;

    padding: 20px;

    border:
        1px solid #ddd;

    background: #fafafa;
}


.paid-stamp {

    width: max-content;

    margin: 30px auto;

    padding: 10px 30px;

    border:
        3px solid #27834a;

    color: #27834a;

    font-size: 22px;

    font-weight: 800;

    letter-spacing: 5px;

    transform: rotate(-4deg);
}


.actions {

    width: 100%;

    max-width: 850px;

    margin: 0 auto 15px;

    text-align: right;
}


.actions button {

    padding: 10px 18px;

    border: 0;

    border-radius: 25px;

    background: #e07a2f;

    color: white;

    cursor: pointer;

    font-weight: 600;
}


.footer {

    margin-top: 35px;

    padding-top: 15px;

    border-top:
        1px solid #ddd;

    font-size: 12px;

    color: #777;

    line-height: 1.7;
}


@media print {

    body {

        padding: 0;

        background: white;
    }


    .page {

        max-width: none;

        box-shadow: none;

        padding: 25px;
    }


    .actions {

        display: none;
    }
}


</style>

</head>


<body>


<div class="actions">

    <button
        onclick="window.print()"
    >
        Print / Save as PDF
    </button>

</div>


<div class="page">


    <!-- HEADER -->

    <div class="top">

        <div>

            <div class="brand">
                IMAGINATION NATURE STUDIO
            </div>

            <div class="muted">
                PHOTOGRAPHY &amp;
                CREATIVE STUDIO
            </div>

            <div class="muted">
                ' .
                e($businessAddress) .
                '
            </div>

            <div class="muted">
                TIN:
                ' .
                e($businessTin) .
                '
            </div>

        </div>


        <div
            class="right"
            style="text-align:right"
        >

            <div class="muted">
                ' .
                e($businessPhone) .
                '
            </div>

            <div class="muted">
                ' .
                e($businessEmail) .
                '
            </div>

        </div>

    </div>


    <!-- TITLE -->

    <div class="invoice-title">
        INVOICE
    </div>


    <div class="status">
        ' .
        e($statusLabel) .
        '
    </div>

    <div class="invoice-meta">
        Generated from the booking information recorded by Imagination Nature Studio.
    </div>


    <!-- INVOICE INFORMATION -->

    <h2>
        Invoice Information
    </h2>


    <table>

        <tr>
            <td>
                <strong>Invoice No.</strong>
            </td>

            <td>
                ' .
                e($invoiceNumber) .
                '
            </td>
        </tr>


        <tr>
            <td>
                <strong>Booking No.</strong>
            </td>

            <td>
                ' .
                $bookingNumber .
                '
            </td>
        </tr>


        <tr>
            <td>
                <strong>Issued</strong>
            </td>

            <td>
                ' .
                e(date('Y-m-d H:i')) .
                '
            </td>
        </tr>


        <tr>
            <td>
                <strong>Due Date</strong>
            </td>

            <td>
                ' .
                e($dueDate) .
                '
            </td>
        </tr>


        <tr>
            <td>
                <strong>Payment Method</strong>
            </td>

            <td>
                ' .
                e($paymentMethodLabel) .
                '
            </td>
        </tr>


    </table>


    <!-- BILL TO -->

    <h2>
        Billed To
    </h2>


    <table>

        <tr>
            <td>Name</td>
            <td>
                ' .
                $customerName .
                '
            </td>
        </tr>


        <tr>
            <td>Phone</td>
            <td>
                ' .
                $customerPhone .
                '
            </td>
        </tr>


        <tr>
            <td>Email</td>
            <td>
                ' .
                $customerEmail .
                '
            </td>
        </tr>


        ' .
        $companyRow .
        '


        <tr>
            <td>Location</td>
            <td>
                ' .
                $customerLocation .
                '
            </td>
        </tr>

    </table>


    <!-- BOOKING DETAILS -->

    <h2>
        Booking Details
    </h2>


    <table>

        <tr>
            <td>Service</td>

            <td>
                ' .
                $serviceName .
                '
            </td>
        </tr>


        ' .
        $packageRow .
        '


        <tr>
            <td>Date</td>

            <td>
                ' .
                $bookingDate .
                '
            </td>
        </tr>


        <tr>
            <td>Time</td>

            <td>
                ' .
                $startTime .
                '
                -
                ' .
                $endTime .
                '
            </td>
        </tr>


        <tr>
            <td>Location</td>

            <td>
                ' .
                $customerLocation .
                '
            </td>
        </tr>


        <tr>
            <td>People</td>

            <td>
                ' .
                $people .
                '
            </td>
        </tr>

    </table>


    <!-- WEDDING SCHEDULE -->

    ' .
    $weddingSchedule .
    '


    <!-- ITEMS -->

    <h2>
        Items
    </h2>


    <table>

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

                <td>

                    ' .
                    $serviceName .
                    '

                    ' .
                    (
                        !empty($b['package_name'])
                        ? ' - ' .
                          e($b['package_name'])
                        : ''
                    ) .
                    '

                </td>


                <td class="right">

                    ' .
                    e(rwf($total)) .
                    '

                </td>

            </tr>

        </tbody>

    </table>


    <!-- PAYMENT SUMMARY -->

    <div class="info-box">

        <table>

            <tr>

                <td>
                    Subtotal
                </td>

                <td class="right">
                    ' .
                    e(rwf($total)) .
                    '
                </td>

            </tr>


            <tr>

                <td>
                    Deposit Required (30%)
                </td>

                <td class="right">
                    ' .
                    e(rwf($deposit)) .
                    '
                </td>

            </tr>


            <tr>

                <td>
                    Amount Paid
                </td>

                <td class="right">
                    ' .
                    e(rwf($paid)) .
                    '
                </td>

            </tr>


            <tr class="total-row">

                <td>
                    Balance Due
                </td>

                <td class="right">
                    ' .
                    e(rwf($balance)) .
                    '
                </td>

            </tr>

        </table>

    </div>


    <!-- PAYMENT STATUS -->

    <div class="payment-box">

        ' .
        $paymentMessage .
        '

    </div>


    ' .
    $paidStamp .
    '


    <p style="margin-top:30px">

        Thank you for choosing
        <strong>
            Imagination Nature Studio
        </strong>.

    </p>


    <!-- FOOTER -->

    <div class="footer">

        ' .
        e($businessAddress) .
        '

        &nbsp; · &nbsp;

        ' .
        e($businessPhone) .
        '

        &nbsp; · &nbsp;

        ' .
        e($businessEmail) .
        '

        <br>

        TIN:
        ' .
        e($businessTin) .
        '

    </div>


</div>


</body>

</html>';


    /*
    |--------------------------------------------------------------------------
    | SAVE INVOICE
    |--------------------------------------------------------------------------
    */

    if (
        !defined('RECEIPT_DIR')
    ) {

        throw new RuntimeException(
            'RECEIPT_DIR is not defined.'
        );
    }


    if (
        !is_dir(RECEIPT_DIR)
    ) {

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
                'Unable to create invoice directory.'
            );
        }
    }


    $file =
        rtrim(
            RECEIPT_DIR,
            '/\\'
        )
        .
        DIRECTORY_SEPARATOR
        .
        $invoiceNumber
        .
        '.html';


    $written = file_put_contents(
        $file,
        $html
    );


    if (
        $written === false
    ) {

        throw new RuntimeException(
            'Unable to write invoice file.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE PATH IN DATABASE
    |--------------------------------------------------------------------------
    */

    $invoicePath =
        'receipts/' .
        $invoiceNumber .
        '.html';


    $update = $pdo->prepare(
        "UPDATE bookings
         SET
            invoice_path = ?,
            invoice_issued_at = NOW()
         WHERE id = ?"
    );


    $update->execute([
        $invoicePath,
        $bookingId
    ]);


    /*
    |--------------------------------------------------------------------------
    | RETURN PATH
    |--------------------------------------------------------------------------
    */

    return $invoicePath;
}