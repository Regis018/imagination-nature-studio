<?php
/**
 * Imagination Nature Studio
 * BOOKING PROCESS
 *
 * FLOW:
 * 1. Client submits booking
 * 2. Booking is saved as PENDING
 * 3. NO invoice is generated here
 * 4. NO receipt is generated here
 * 5. Client sees "Awaiting confirmation"
 * 6. Admin confirms booking
 * 7. Admin confirmation generates invoice automatically
 * 8. Client then sees Approved + Invoice
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';


/* =========================================================
   DEBUG
   ========================================================= */

const DEBUG_STEPS = false;

if (DEBUG_STEPS) {

    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}


/* =========================================================
   ONLY POST
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    redirect(
        SITE_URL . '/booking.php'
    );
}


/* =========================================================
   CSRF
   ========================================================= */

if (!verify_csrf($_POST['csrf'] ?? null)) {

    $_SESSION['booking_errors'] = [
        'Your session expired. Please try again.'
    ];

    $_SESSION['booking_old'] = $_POST;

    redirect(
        SITE_URL . '/booking.php'
    );
}


/* =========================================================
   COLLECT INPUT
   ========================================================= */

$in = function ($k, $default = '') {

    return trim(
        (string)(
            $_POST[$k] ?? $default
        )
    );
};


$service_id =
    (int)$in('service_id');


$package_id =
    (int)$in('package_id')
    ?: null;


$booking_date =
    $in('booking_date');


$start_time =
    $in('start_time');


$end_time =
    $in('end_time');


$full_name =
    $in('full_name');


$phone =
    $in('phone');


$whatsapp =
    $in('whatsapp')
    ?: $phone;


$email =
    $in('email');


$location =
    $in('location');


$company =
    $in('company');


$event_type =
    $in('event_type');


$event_location =
    $in('event_location')
    ?: $location;


$num_people =
    max(
        1,
        (int)$in(
            'number_of_people',
            1
        )
    );


$notes =
    $in('customer_notes');


$payment_method =
    $in('payment_method');


$amount_paid =
    max(
        0,
        (float)$in(
            'amount_paid',
            0
        )
    );


/* =========================================================
   VALIDATE
   ========================================================= */

$errs = [];


if ($service_id <= 0) {

    $errs[] =
        'Please choose a service.';
}


if (
    !$booking_date
    ||
    strtotime($booking_date)
        < strtotime('today')
) {

    $errs[] =
        'Please choose a future date.';
}


if (
    !$start_time
    ||
    !$end_time
    ||
    $start_time >= $end_time
) {

    $errs[] =
        'Please choose a valid time range.';
}


if ($full_name === '') {

    $errs[] =
        'Full name is required.';
}


if ($phone === '') {

    $errs[] =
        'Phone number is required.';
}


if (!valid_email($email)) {

    $errs[] =
        'A valid email is required.';
}


if ($location === '') {

    $errs[] =
        'Your location is required.';
}


if (
    !in_array(
        $payment_method,
        [
            'cash',
            'mobile_money',
            'bank_transfer',
            'other'
        ],
        true
    )
) {

    $payment_method =
        'cash';
}


if ($errs) {

    $_SESSION['booking_errors'] =
        $errs;

    $_SESSION['booking_old'] =
        $_POST;

    redirect(
        SITE_URL . '/booking.php'
    );
}


/* =========================================================
   DATABASE
   ========================================================= */

$pdo = db();


/* =========================================================
   LOAD SERVICE
   ========================================================= */

$svcStmt = $pdo->prepare(
    "SELECT *
     FROM services
     WHERE id=?
       AND status='active'"
);

$svcStmt->execute([
    $service_id
]);

$service =
    $svcStmt->fetch();


if (!$service) {

    $_SESSION['booking_errors'] = [
        'That service is not available.'
    ];

    $_SESSION['booking_old'] = $_POST;

    redirect(
        SITE_URL . '/booking.php'
    );
}


/* =========================================================
   DETECT WEDDING SERVICE
   ========================================================= */

$serviceNameLower =
    strtolower(
        trim(
            (string)($service['name'] ?? '')
        )
    );

$isWedding =
    strpos(
        $serviceNameLower,
        'wedding photography'
    ) !== false
    ||
    (
        strpos($serviceNameLower, 'wedding') !== false
        &&
        strpos($serviceNameLower, 'photograph') !== false
    );

/*
 * Studio Photoshoot uses per-photo pricing.
 * Wedding keeps the existing package/deposit pricing.
 */
$isPerPhoto = (
    !$isWedding
    &&
    (
        strpos($serviceNameLower, 'studio photoshoot') !== false
        ||
        (
            strpos($serviceNameLower, 'studio') !== false
            && strpos($serviceNameLower, 'photo') !== false
        )
    )
);

if ($isPerPhoto) {
    /* Studio bookings are not priced until photos are selected. */
    $package_id = null;
    $amount_paid = 0.0;
    $payment_method = 'cash';
}


/* =========================================================
   COLLECT WEDDING DATES
   ========================================================= */

$wedding_dates = [];

if ($isWedding) {

    $rawWeddingDates =
        $_POST['wedding_dates'] ?? [];

    if (!is_array($rawWeddingDates)) {
        $rawWeddingDates = [];
    }

    foreach ($rawWeddingDates as $row) {

        if (!is_array($row)) {
            continue;
        }

        $date =
            trim(
                (string)($row['date'] ?? '')
            );

        $eventType =
            trim(
                (string)($row['event_type'] ?? '')
            );

        $start =
            trim(
                (string)($row['start_time'] ?? '')
            );

        $end =
            trim(
                (string)($row['end_time'] ?? '')
            );

        if (
            $date === ''
            &&
            $eventType === ''
            &&
            $start === ''
            &&
            $end === ''
        ) {
            continue;
        }

        $wedding_dates[] = [
            'date'       => $date,
            'event_type' => $eventType,
            'start_time' => $start,
            'end_time'   => $end
        ];
    }

    if (empty($wedding_dates)) {

        $errs[] =
            'Please add at least one wedding date.';

    } else {

        foreach (
            $wedding_dates as $index => $row
        ) {

            $dayNumber =
                $index + 1;

            if (
                $row['date'] === ''
                ||
                strtotime($row['date']) === false
                ||
                strtotime($row['date']) < strtotime('today')
            ) {

                $errs[] =
                    'Please choose a valid future date for Wedding Day '
                    . $dayNumber
                    . '.';

            }

            if ($row['event_type'] === '') {

                $errs[] =
                    'Event / Day Type is required for Wedding Day '
                    . $dayNumber
                    . '.';

            }

            if (
                $row['start_time'] === ''
                ||
                $row['end_time'] === ''
                ||
                $row['start_time'] >= $row['end_time']
            ) {

                $errs[] =
                    'Please choose a valid time range for Wedding Day '
                    . $dayNumber
                    . '.';
            }
        }

        /* First wedding day remains the main booking date/time. */
        if (!empty($wedding_dates[0])) {

            $booking_date =
                $wedding_dates[0]['date'];

            $start_time =
                $wedding_dates[0]['start_time'];

            $end_time =
                $wedding_dates[0]['end_time'];
        }
    }
}


/* =========================================================
   RE-CHECK COMMON DATE/TIME
   ========================================================= */

if (
    !$booking_date
    ||
    strtotime($booking_date) === false
    ||
    strtotime($booking_date) < strtotime('today')
) {

    $errs[] =
        'Please choose a future date.';
}

if (
    !$start_time
    ||
    !$end_time
    ||
    $start_time >= $end_time
) {

    $errs[] =
        'Please choose a valid time range.';
}


/* =========================================================
   STOP BEFORE DATABASE INSERT
   ========================================================= */

if ($errs) {

    $_SESSION['booking_errors'] =
        $errs;

    $_SESSION['booking_old'] =
        $_POST;

    redirect(
        SITE_URL . '/booking.php'
    );
}


/* =========================================================
   AVAILABILITY CHECK
   ========================================================= */

if ($isWedding) {

    foreach (
        $wedding_dates as $index => $row
    ) {

        if (
            !is_slot_available(
                $row['date'],
                $row['start_time'],
                $row['end_time']
            )
        ) {

            $_SESSION['booking_errors'] = [
                'Wedding Day '
                . ($index + 1)
                . ' is no longer available. Please choose another date or time.'
            ];

            $_SESSION['booking_old'] =
                $_POST;

            redirect(
                SITE_URL . '/booking.php'
            );
        }
    }

} elseif (
    !is_slot_available(
        $booking_date,
        $start_time,
        $end_time
    )
) {

    $_SESSION['booking_errors'] = [
        'That time has just been booked. Please choose another slot.'
    ];

    $_SESSION['booking_old'] =
        $_POST;

    redirect(
        SITE_URL . '/booking.php'
    );
}


/* =========================================================
   LOAD PACKAGE
   ========================================================= */

$package = null;


if ($package_id) {

    $pkStmt = $pdo->prepare(
        "SELECT *
         FROM packages
         WHERE id=?
           AND service_id=?
           AND status='active'"
    );

    $pkStmt->execute([
        $package_id,
        $service_id
    ]);

    $package =
        $pkStmt->fetch()
        ?: null;
}


/* =========================================================
   PRICING
   ========================================================= */

if ($isPerPhoto) {

    /*
     * Studio price is calculated later from the client's
     * final photo selection.
     */
    $total = 0.0;
    $deposit = 0.0;
    $amount_paid = 0.0;
    $balance = 0.0;
    $payment_status = 'unpaid';

} else {

    /* Existing wedding/package pricing remains unchanged. */
    $total =
        (float)(
            $package['price']
            ??
            $service['price']
            ??
            0
        );

    $deposit =
        round(
            $total * 0.30,
            2
        );

    $balance =
        max(
            0,
            $total - $amount_paid
        );

    $payment_status =
        $amount_paid <= 0
            ? 'unpaid'
            : (
                $amount_paid >= $total
                    ? 'paid'
                    : 'partial'
            );
}


/* =========================================================
   SERVER-SIDE 30% DEPOSIT REQUIREMENT
   ========================================================= */

if (
    !$isPerPhoto
    &&
    $total > 0
    &&
    $amount_paid < $deposit
) {

    $_SESSION['booking_errors'] = [
        'A minimum 30% deposit of '
        . rwf($deposit)
        . ' is required.'
    ];

    $_SESSION['booking_old'] =
        $_POST;

    redirect(
        SITE_URL . '/booking.php'
    );
}


/* Amount paid may never exceed the booking total. */
if ($amount_paid > $total) {

    $_SESSION['booking_errors'] = [
        'Amount paid cannot be greater than the booking total.'
    ];

    $_SESSION['booking_old'] =
        $_POST;

    redirect(
        SITE_URL . '/booking.php'
    );
}


/* =========================================================
   INSERT CUSTOMER + BOOKING
   ========================================================= */

try {

    // Reuse an existing transaction safely, if another caller opened one.
    $ownsTransaction = !$pdo->inTransaction();
    $savepointName = 'booking_process_savepoint';

    if ($ownsTransaction) {
        $pdo->beginTransaction();
    } else {
        $pdo->exec('SAVEPOINT ' . $savepointName);
    }


    /* =====================================================
       1. CUSTOMER
       ===================================================== */

    $cStmt = $pdo->prepare(
        "INSERT INTO customers
        (
            full_name,
            phone,
            whatsapp,
            email,
            location,
            company
        )
        VALUES
        (?, ?, ?, ?, ?, ?)"
    );


    $cStmt->execute([
        $full_name,
        $phone,
        $whatsapp,
        $email,
        $location,
        $company
    ]);


    $customer_id =
        (int)$pdo->lastInsertId();


    /* =====================================================
       2. BOOKING NUMBER
       ===================================================== */

    $booking_number =
        generate_booking_number();


    /* =====================================================
       3. BOOKING
       ===================================================== */

    /*
     * IMPORTANT:
     *
     * New bookings ALWAYS start as:
     *
     * booking_status = pending
     *
     * Invoice is NOT generated here.
     * Receipt is NOT generated here.
     */

    $bStmt = $pdo->prepare(
        "INSERT INTO bookings
        (
            booking_number,
            customer_id,
            service_id,
            package_id,
            booking_date,
            start_time,
            end_time,
            location,
            event_type,
            number_of_people,
            total_amount,
            deposit_amount,
            amount_paid,
            balance,
            payment_method,
            payment_status,
            booking_status,
            customer_notes
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'pending',
            ?
        )"
    );


    $bStmt->execute([

        $booking_number,

        $customer_id,

        $service_id,

        $package_id,

        $booking_date,

        $start_time,

        $end_time,

        $event_location,

        $event_type,

        $num_people,

        $total,

        $deposit,

        $amount_paid,

        $balance,

        $payment_method,

        $payment_status,

        $notes
    ]);


    $booking_id =
        (int)$pdo->lastInsertId();


    /* =====================================================
       4. WEDDING DATES
       ===================================================== */

    if ($isWedding && !empty($wedding_dates)) {

        // booking_dates table has no `notes` column in the current database.
        $wdStmt = $pdo->prepare(
            "INSERT INTO booking_dates
            (
                booking_id,
                event_date,
                event_type,
                start_time,
                end_time
            )
            VALUES (?, ?, ?, ?, ?)"
        );

        foreach ($wedding_dates as $row) {
            $wdStmt->execute([
                $booking_id,
                $row['date'],
                $row['event_type'],
                $row['start_time'],
                $row['end_time']
            ]);
        }
    }


    /* =====================================================
       5. PAYMENT ROW
       ===================================================== */

    /*
     * If client enters an initial payment,
     * keep the payment record.
     */

    if ($amount_paid > 0) {

        $pStmt = $pdo->prepare(
            "INSERT INTO payments
            (
                booking_id,
                amount,
                payment_method,
                payment_date,
                notes
            )
            VALUES
            (?, ?, ?, ?, ?)"
        );


        $pStmt->execute([

            $booking_id,

            $amount_paid,

            $payment_method,

            date('Y-m-d'),

            'Initial payment captured at booking'
        ]);
    }


    /* =====================================================
       COMMIT
       ===================================================== */

    if ($ownsTransaction && $pdo->inTransaction()) {
        $pdo->commit();
    } elseif (!$ownsTransaction && $pdo->inTransaction()) {
        $pdo->exec('RELEASE SAVEPOINT ' . $savepointName);
    }


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        if (isset($ownsTransaction) && $ownsTransaction) {
            $pdo->rollBack();
        } elseif (isset($ownsTransaction) && !$ownsTransaction) {
            try {
                $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepointName);
                $pdo->exec('RELEASE SAVEPOINT ' . $savepointName);
            } catch (Throwable $ignored) {
                // Keep the original exception for logging below.
            }
        }
    }


    @error_log(
        '[booking] insert failed: '
        . $e->getMessage()
        . ' | FILE: '
        . $e->getFile()
        . ' | LINE: '
        . $e->getLine()
        . "\n",
        3,
        ERROR_LOG_FILE
    );


    if (DEBUG_STEPS) {

        die(
            '<pre style="
                background:#111;
                color:#f88;
                padding:20px;
                font:13px monospace
            ">'
            .
            htmlspecialchars(
                $e->getMessage()
            )
            .
            "\n"
            .
            htmlspecialchars(
                $e->getFile()
            )
            .
            ':'
            .
            $e->getLine()
            .
            '</pre>'
        );
    }


    $_SESSION['booking_errors'] = [
        'We could not save your booking. Please try again.'
    ];


    $_SESSION['booking_old'] =
        $_POST;


    redirect(
        SITE_URL . '/booking.php'
    );
}


/* =========================================================
   RELOAD BOOKING
   For email only
   ========================================================= */

$bkStmt = $pdo->prepare(
    "SELECT
        b.*,
        c.full_name,
        c.phone,
        c.whatsapp,
        c.email,
        c.location,
        c.company
     FROM bookings b
     JOIN customers c
        ON c.id = b.customer_id
     WHERE b.id = ?
     LIMIT 1"
);


$bkStmt->execute([
    $booking_id
]);


$bookingRow =
    $bkStmt->fetch();


/* =========================================================
   NO RECEIPT HERE
   =========================================================
 *
 * IMPORTANT:
 *
 * The old version generated a receipt immediately
 * after booking.
 *
 * That has been REMOVED.
 *
 * Receipt should only be generated by Admin after
 * payment is confirmed as PAID.
 *
 * ========================================================= */


/* =========================================================
   EMAILS
   ========================================================= */

if (
    defined('MAIL_ENABLED')
    &&
    MAIL_ENABLED
    &&
    $bookingRow
) {

    /*
     * For now we keep the existing mail functions.
     *
     * The booking remains PENDING.
     */

    try {

        /*
         * This URL is only the booking confirmation page.
         * It is NOT an invoice and NOT a receipt.
         */

        $confirmationUrl =
            SITE_URL
            . '/booking/confirmation.php?ref='
            . urlencode(
                $booking_number
            );


        send_booking_email(
            $bookingRow,
            $service['name'],
            $package['name'] ?? null,
            $confirmationUrl
        );

    } catch (Throwable $e) {

        @error_log(
            '[booking] customer email failed: '
            . $e->getMessage()
            . "\n",
            3,
            ERROR_LOG_FILE
        );
    }


    try {

        send_admin_booking_email(
            $bookingRow,
            $service['name'],
            $package['name'] ?? null
        );

    } catch (Throwable $e) {

        @error_log(
            '[booking] admin email failed: '
            . $e->getMessage()
            . "\n",
            3,
            ERROR_LOG_FILE
        );
    }
}


/* =========================================================
   SUCCESS REDIRECT
   ========================================================= */

$_SESSION['last_booking_number'] =
    $booking_number;

$_SESSION['last_booking_id'] =
    $booking_id;


redirect(
    SITE_URL
    . '/booking/confirmation.php?ref='
    . urlencode(
        $booking_number
    )
);