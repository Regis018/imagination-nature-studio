<?php
/**
 * Imagination Nature Studio
 * BOOKING PROCESS
 *
 * FLOW:
 * 1. Client submits booking
 * 2. Booking is saved as PENDING
 * 3. Wedding Photography can contain multiple event dates
 * 4. NO invoice is generated here
 * 5. NO receipt is generated here
 * 6. Client sees "Awaiting confirmation"
 * 7. Admin confirms booking
 * 8. Admin confirmation generates invoice automatically
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
   INPUT HELPER
   ========================================================= */

$in = function ($k, $default = '') {

    return trim(
        (string)(
            $_POST[$k] ?? $default
        )
    );
};


/* =========================================================
   COLLECT BASIC INPUT
   ========================================================= */

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
   WEDDING DATES
   =========================================================
 *
 * Only used when service = Wedding Photography.
 *
 * Expected:
 *
 * wedding_dates[0][date]
 * wedding_dates[0][event_type]
 * wedding_dates[0][start_time]
 * wedding_dates[0][end_time]
 *
 * wedding_dates[1][date]
 * wedding_dates[1][event_type]
 * ...
 *
 * ========================================================= */

$rawWeddingDates =
    $_POST['wedding_dates']
    ??
    [];


$weddingDates = [];


if (is_array($rawWeddingDates)) {

    foreach (
        $rawWeddingDates
        as $row
    ) {

        if (!is_array($row)) {
            continue;
        }


        $date =
            trim(
                (string)(
                    $row['date']
                    ?? ''
                )
            );


        $type =
            trim(
                (string)(
                    $row['event_type']
                    ?? ''
                )
            );


        $start =
            trim(
                (string)(
                    $row['start_time']
                    ?? ''
                )
            );


        $end =
            trim(
                (string)(
                    $row['end_time']
                    ?? ''
                )
            );


        /*
         * Ignore completely empty rows.
         */

        if (
            $date === ''
            &&
            $type === ''
            &&
            $start === ''
            &&
            $end === ''
        ) {
            continue;
        }


        $weddingDates[] = [

            'date' =>
                $date,

            'event_type' =>
                $type,

            'start_time' =>
                $start,

            'end_time' =>
                $end
        ];
    }
}


/* =========================================================
   NORMALIZE PAYMENT METHOD
   ========================================================= */

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


/* =========================================================
   INITIAL VALIDATION
   ========================================================= */

$errs = [];


if ($service_id <= 0) {

    $errs[] =
        'Please choose a service.';
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


/* =========================================================
   LOAD DATABASE
   ========================================================= */

$pdo = db();


/* =========================================================
   LOAD SERVICE
   ========================================================= */

$svcStmt = $pdo->prepare(
    "SELECT *
     FROM services
     WHERE id = ?
       AND status = 'active'
     LIMIT 1"
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

    $_SESSION['booking_old'] =
        $_POST;

    redirect(
        SITE_URL . '/booking.php'
    );
}


/* =========================================================
   DETERMINE WEDDING SERVICE
   ========================================================= */

$serviceName =
    strtolower(
        trim(
            (string)(
                $service['name']
                ?? ''
            )
        )
    );


$isWedding =
    (
        $serviceName === 'wedding photography'
        ||
        strpos(
            $serviceName,
            'wedding photography'
        ) !== false
    );


/* =========================================================
   LOAD PACKAGE
   ========================================================= */

$package = null;


if ($package_id) {

    $pkStmt = $pdo->prepare(
        "SELECT *
         FROM packages
         WHERE id = ?
           AND service_id = ?
           AND status = 'active'
         LIMIT 1"
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
   DATE / TIME VALIDATION
   ========================================================= */

if ($isWedding) {

    /* =====================================================
       WEDDING MULTIPLE DATES
       ===================================================== */

    if (!$weddingDates) {

        $errs[] =
            'Please add at least one wedding event date.';
    }


    if (
        count($weddingDates)
        > 30
    ) {

        $errs[] =
            'You can add a maximum of 30 wedding event dates.';
    }


    $today =
        date('Y-m-d');


    foreach (
        $weddingDates
        as $index => $wd
    ) {

        $dayNumber =
            $index + 1;


        $date =
            $wd['date'];


        $type =
            $wd['event_type'];


        $start =
            $wd['start_time'];


        $end =
            $wd['end_time'];


        /*
         * DATE
         */

        $validDate =
            false;


        if ($date !== '') {

            $dt =
                DateTime::createFromFormat(
                    'Y-m-d',
                    $date
                );


            $validDate =
                (
                    $dt !== false
                    &&
                    $dt->format('Y-m-d')
                        === $date
                );
        }


        if (!$validDate) {

            $errs[] =
                'Please choose a valid date for Wedding Day '
                . $dayNumber
                . '.';

        } elseif ($date < $today) {

            $errs[] =
                'Wedding Day '
                . $dayNumber
                . ' must be a future date.';
        }


        /*
         * EVENT TYPE
         */

        if ($type === '') {

            $errs[] =
                'Please enter the event type for Wedding Day '
                . $dayNumber
                . '.';
        }


        /*
         * TIME
         */

        if (
            $start === ''
            ||
            $end === ''
            ||
            $start >= $end
        ) {

            $errs[] =
                'Please choose a valid time range for Wedding Day '
                . $dayNumber
                . '.';
        }
    }


    /* =====================================================
       CHECK WEDDING DATES AGAINST EACH OTHER
       ===================================================== */

    $count =
        count($weddingDates);


    for (
        $i = 0;
        $i < $count;
        $i++
    ) {

        for (
            $j = $i + 1;
            $j < $count;
            $j++
        ) {

            $a =
                $weddingDates[$i];


            $b =
                $weddingDates[$j];


            /*
             * Only compare same dates.
             */

            if (
                $a['date'] !== ''
                &&
                $a['date'] ===
                    $b['date']
                &&
                $a['start_time'] !== ''
                &&
                $a['end_time'] !== ''
                &&
                $b['start_time'] !== ''
                &&
                $b['end_time'] !== ''
            ) {

                $overlap =
                    !(
                        $a['end_time']
                        <=
                        $b['start_time']
                        ||
                        $a['start_time']
                        >=
                        $b['end_time']
                    );


                if ($overlap) {

                    $errs[] =
                        'Wedding Day '
                        . ($i + 1)
                        . ' and Wedding Day '
                        . ($j + 1)
                        . ' have overlapping times on '
                        . $a['date']
                        . '.';
                }
            }
        }
    }


    /*
     * FIRST WEDDING DATE BECOMES THE MAIN BOOKING DATE.
     */

    if (
        isset(
            $weddingDates[0]
        )
    ) {

        $booking_date =
            $weddingDates[0]['date'];


        $start_time =
            $weddingDates[0]['start_time'];


        $end_time =
            $weddingDates[0]['end_time'];


        /*
         * If general event type is empty,
         * use the first wedding event type.
         */

        if (
            $event_type === ''
            &&
            $weddingDates[0]['event_type'] !== ''
        ) {

            $event_type =
                $weddingDates[0]['event_type'];
        }
    }


} else {

    /* =====================================================
       NORMAL SERVICE — ORIGINAL SINGLE DATE FLOW
       ===================================================== */

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
}


/* =========================================================
   STOP VALIDATION ERRORS
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
   AVAILABILITY
   =========================================================
 *
 * Normal:
 *     Check bookings table.
 *
 * Wedding:
 *     Check both:
 *       1. old/main bookings
 *       2. booking_dates
 *
 * ========================================================= */


/* =========================================================
   HELPER — CHECK EXISTING MAIN BOOKING
   ========================================================= */

$checkMainAvailability =
    function (
        PDO $pdo,
        string $date,
        string $start,
        string $end
    ): bool {

        $stmt =
            $pdo->prepare(
                "SELECT COUNT(*)
                 FROM bookings
                 WHERE booking_date = ?
                   AND booking_status IN
                       ('pending','confirmed','completed')
                   AND NOT (
                       end_time <= ?
                       OR
                       start_time >= ?
                   )"
            );


        $stmt->execute([
            $date,
            $start,
            $end
        ]);


        return (
            (int)$stmt->fetchColumn()
        ) === 0;
    };


/* =========================================================
   HELPER — CHECK WEDDING EVENT DATES
   ========================================================= */

$checkWeddingAvailability =
    function (
        PDO $pdo,
        string $date,
        string $start,
        string $end
    ): bool {

        /*
         * Check normal/main booking dates.
         */

        $stmt =
            $pdo->prepare(
                "SELECT COUNT(*)
                 FROM bookings
                 WHERE booking_date = ?
                   AND booking_status IN
                       ('pending','confirmed','completed')
                   AND NOT (
                       end_time <= ?
                       OR
                       start_time >= ?
                   )"
            );


        $stmt->execute([
            $date,
            $start,
            $end
        ]);


        if (
            (int)$stmt->fetchColumn()
            > 0
        ) {

            return false;
        }


        /*
         * Check booking_dates table.
         */

        $stmt =
            $pdo->prepare(
                "SELECT COUNT(*)
                 FROM booking_dates bd
                 INNER JOIN bookings b
                    ON b.id = bd.booking_id
                 WHERE bd.event_date = ?
                   AND b.booking_status IN
                       ('pending','confirmed','completed')
                   AND NOT (
                       bd.end_time <= ?
                       OR
                       bd.start_time >= ?
                   )"
            );


        $stmt->execute([
            $date,
            $start,
            $end
        ]);


        return (
            (int)$stmt->fetchColumn()
        ) === 0;
    };


/* =========================================================
   CHECK AVAILABILITY BEFORE SAVE
   ========================================================= */

if ($isWedding) {

    foreach (
        $weddingDates
        as $index => $wd
    ) {

        if (
            !$checkWeddingAvailability(
                $pdo,
                $wd['date'],
                $wd['start_time'],
                $wd['end_time']
            )
        ) {

            $_SESSION['booking_errors'] = [

                'Wedding Day '
                . ($index + 1)
                . ' on '
                . $wd['date']
                . ' from '
                . $wd['start_time']
                . ' to '
                . $wd['end_time']
                . ' is not available. Please choose another time.'
            ];


            $_SESSION['booking_old'] =
                $_POST;


            redirect(
                SITE_URL . '/booking.php'
            );
        }
    }

} else {

    if (
        !$checkMainAvailability(
            $pdo,
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
}


/* =========================================================
   PRICING
   ========================================================= */

$total =
    (float)(
        $package['price']
        ??
        $service['price']
        ??
        0
    );


if ($total <= 0) {

    $_SESSION['booking_errors'] = [

        'The selected service or package has an invalid price.'
    ];


    $_SESSION['booking_old'] =
        $_POST;


    redirect(
        SITE_URL . '/booking.php'
    );
}


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


/* =========================================================
   DATABASE TRANSACTION
   ========================================================= */

$pdo->beginTransaction();


try {


    /* =====================================================
       1. CUSTOMER
       ===================================================== */

    $cStmt =
        $pdo->prepare(
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


    if ($customer_id <= 0) {

        throw new Exception(
            'Customer could not be created.'
        );
    }


    /* =====================================================
       2. BOOKING NUMBER
       ===================================================== */

    $booking_number =
        generate_booking_number();


    /* =====================================================
       3. MAIN BOOKING
       ===================================================== */

    $bStmt =
        $pdo->prepare(
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


    if ($booking_id <= 0) {

        throw new Exception(
            'Booking could not be created.'
        );
    }


    /* =====================================================
       4. SAVE WEDDING DATES
       ===================================================== */

    if ($isWedding) {

        $dateStmt =
            $pdo->prepare(
                "INSERT INTO booking_dates
                (
                    booking_id,
                    event_date,
                    event_type,
                    start_time,
                    end_time
                )
                VALUES
                (?, ?, ?, ?, ?)"
            );


        foreach (
            $weddingDates
            as $wd
        ) {

            $dateStmt->execute([

                $booking_id,

                $wd['date'],

                $wd['event_type'],

                $wd['start_time'],

                $wd['end_time']
            ]);
        }
    }


    /* =====================================================
       5. PAYMENT ROW
       ===================================================== */

    if ($amount_paid > 0) {

        $pStmt =
            $pdo->prepare(
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
       6. COMMIT
       ===================================================== */

    $pdo->commit();


} catch (Throwable $e) {


    if (
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();
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

$bkStmt =
    $pdo->prepare(
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
   EMAILS
   ========================================================= */

if (
    defined('MAIL_ENABLED')
    &&
    MAIL_ENABLED
    &&
    $bookingRow
) {


    /* =====================================================
       CLIENT EMAIL
       ===================================================== */

    try {

        $confirmationUrl =
            SITE_URL
            . '/booking/confirmation.php?ref='
            . urlencode(
                $booking_number
            );


        send_booking_email(

            $bookingRow,

            $service['name'],

            $package['name']
                ?? null,

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


    /* =====================================================
       ADMIN EMAIL
       ===================================================== */

    try {

        send_admin_booking_email(

            $bookingRow,

            $service['name'],

            $package['name']
                ?? null
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
   SUCCESS SESSION
   ========================================================= */

$_SESSION['last_booking_number'] =
    $booking_number;


$_SESSION['last_booking_id'] =
    $booking_id;


/* =========================================================
   SUCCESS REDIRECT
   ========================================================= */

redirect(

    SITE_URL
    . '/booking/confirmation.php?ref='
    . urlencode(
        $booking_number
    )
);