<?php
/**
 * =========================================================
 * BOOKING CONFIRMATION — CLIENT
 * IMAGINATION NATURE STUDIO
 * =========================================================
 *
 * FLOW:
 *
 * Pending
 *     ↓
 * Waiting for confirmation
 *
 * Confirmed
 *     ↓
 * Studio Photoshoot: confirmation details only
 * Invoice: available after confirmation; receipt: after full payment
 *
 * Cancelled
 *     ↓
 * Booking cancelled
 *
 * LIVE STATUS:
 * The page checks the booking status automatically.
 *
 * =========================================================
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/language.php';


/* =========================================================
   GET BOOKING REFERENCE
   ========================================================= */

$ref = trim(
    $_GET['ref']
    ??
    ($_SESSION['last_booking_number'] ?? '')
);


/* =========================================================
   LIVE STATUS AJAX ENDPOINT
   ========================================================= */

if (
    isset($_GET['check_status'])
    &&
    $_GET['check_status'] === '1'
) {

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    header(
        'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
    );

    header(
        'Pragma: no-cache'
    );


    try {

        $statusStmt = db()->prepare(
            "SELECT
                b.id,
                b.booking_status,
                b.invoice_path,
                b.invoice_number,
                b.payment_status,
                b.amount_paid,
                b.total_amount,
                b.original_amount,
                b.discount_amount,
                b.balance,
                b.booking_date,
                b.start_time,
                b.end_time,
                b.booking_number
             FROM bookings b
             WHERE b.booking_number = ?
             LIMIT 1"
        );

        $statusStmt->execute([
            $ref
        ]);

        $live = $statusStmt->fetch();


        if (!$live) {

            http_response_code(404);

            echo json_encode([
                'success' => false,
                'message' => 'Booking not found.'
            ]);

            exit;
        }


        /* -----------------------------------------------------
           NORMALIZE STATUS
           ----------------------------------------------------- */

        $liveStatus = strtolower(
            trim(
                (string)(
                    $live['booking_status']
                    ??
                    'pending'
                )
            )
        );


        /* -----------------------------------------------------
           INVOICE AVAILABLE ONLY AFTER CONFIRMATION
           ----------------------------------------------------- */

        $liveTotal = (float)($live['total_amount'] ?? 0);
        $livePaid = (float)($live['amount_paid'] ?? 0);
        $liveBalance = (float)($live['balance'] ?? 0);
        $liveFullyPaid = (
            strtolower((string)($live['payment_status'] ?? '')) === 'paid'
            && $liveTotal > 0
            && $livePaid + 0.01 >= $liveTotal
            && $liveBalance <= 0.01
        );

        // Invoice is available after admin confirmation, even if unpaid/partially paid.
        // The official receipt remains restricted to full payment.
        $liveInvoiceAvailable = (
            $liveStatus === 'confirmed'
            && $liveTotal > 0
            && !empty($live['invoice_path'])
        );

        $liveItems = [];
        try {
            $liveItemsStmt = db()->prepare(
                "SELECT item_name, description, quantity, unit_price, line_total
                 FROM booking_items
                 WHERE booking_id = ?
                 ORDER BY id ASC"
            );
            $liveItemsStmt->execute([(int)$live['id']]);
            $liveItems = $liveItemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $itemsError) {
            error_log('Live booking items load failed: ' . $itemsError->getMessage());
        }


        echo json_encode([
            'success' => true,

            'booking_status' =>
                $liveStatus,

            'invoice_available' =>
                $liveInvoiceAvailable,

            'fully_paid' => $liveFullyPaid,

            'invoice_number' =>
                $live['invoice_number'] ?? null,

            'total_amount' => $liveTotal,
            'original_amount' => (float)($live['original_amount'] ?? 0),
            'discount_amount' => (float)($live['discount_amount'] ?? 0),
            'items' => $liveItems,

            'payment_status' =>
                $live['payment_status'] ?? 'unpaid',

            'amount_paid' =>
                (float)(
                    $live['amount_paid'] ?? 0
                ),

            'balance' =>
                (float)(
                    $live['balance'] ?? 0
                ),

            'booking_number' => $live['booking_number'] ?? $ref,
            'booking_date' => $live['booking_date'] ?? '',
            'start_time' => $live['start_time'] ?? '',
            'end_time' => $live['end_time'] ?? ''
        ]);

        exit;

    } catch (Throwable $e) {

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Unable to check booking status.'
        ]);

        exit;
    }
}


/* =========================================================
   IF NO REFERENCE
   ========================================================= */

if (!$ref) {

    redirect(
        SITE_URL . '/booking/status.php'
    );
}


/* =========================================================
   LOAD BOOKING
   ========================================================= */

$stmt = db()->prepare(
    "SELECT
        b.*,

        c.full_name,
        c.phone,
        c.whatsapp,
        c.email,
        c.location,

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

     LIMIT 1"
);

$stmt->execute([
    $ref
]);

$b = $stmt->fetch();


/* =========================================================
   BOOKING NOT FOUND
   ========================================================= */

if (!$b) {

    flash(
        'error',
        'Booking not found.'
    );

    redirect(
        SITE_URL . '/booking/status.php'
    );
}


/* =========================================================
   LOAD CUSTOM PRICING ITEMS
   ========================================================= */

$bookingItems = [];
try {
    $itemsStmt = db()->prepare(
        "SELECT item_name, description
         FROM booking_items
         WHERE booking_id = ?
         ORDER BY id ASC"
    );
    $itemsStmt->execute([(int)$b['id']]);
    $bookingItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('Booking confirmation items load failed: ' . $e->getMessage());
    $bookingItems = [];
}

$originalAmount = 0.0;
$discountAmount = 0.0;
$agreedTotal = (float)($b['total_amount'] ?? 0);
$amountPaid = (float)($b['amount_paid'] ?? 0);
$balanceDue = max(0, $agreedTotal - $amountPaid);


/* =========================================================
   NORMALIZE BOOKING STATUS
   ========================================================= */

$bookingStatus = strtolower(
    trim(
        (string)(
            $b['booking_status']
            ??
            'pending'
        )
    )
);


/* =========================================================
   INVOICE AVAILABILITY
   ========================================================= */

$totalDue = (float)($b['total_amount'] ?? 0);
$amountPaid = (float)($b['amount_paid'] ?? 0);
$balanceDue = (float)($b['balance'] ?? 0);

$fullyPaid = (
    strtolower((string)($b['payment_status'] ?? '')) === 'paid'
    && $totalDue > 0
    && $amountPaid + 0.01 >= $totalDue
    && $balanceDue <= 0.01
);

// Invoice becomes available once Admin confirms the booking and generates it.
// Full payment is NOT required; only the official receipt requires full payment.
$invoiceAvailable = (
    $bookingStatus === 'confirmed'
    && $totalDue > 0
    && !empty($b['invoice_path'])
);


/* =========================================================
   STUDIO PHOTO SELECTION
   ========================================================= */

$serviceNameLower =
    strtolower(
        trim(
            (string)(
                $b['service_name']
                ??
                ''
            )
        )
    );


$isStudioBooking = (
    strpos(
        $serviceNameLower,
        'studio photoshoot'
    ) !== false

    ||

    (
        strpos(
            $serviceNameLower,
            'studio'
        ) !== false

        &&

        strpos(
            $serviceNameLower,
            'photo'
        ) !== false
    )
);


$photoSelectionUrl = null;

$photoSelectionSubmitted = false;


if ($isStudioBooking) {

    try {

        $galleryStmt = db()->prepare("
            SELECT
                access_token,
                status

            FROM photo_galleries

            WHERE booking_id = ?

            ORDER BY id DESC

            LIMIT 1
        ");

        $galleryStmt->execute([
            (int)$b['id']
        ]);

        $galleryRow = $galleryStmt->fetch();


        if ($galleryRow) {

            $photoSelectionUrl =
                SITE_URL
                . '/photo-selection/gallery.php?token='
                . urlencode(
                    (string)(
                        $galleryRow['access_token']
                    )
                );


            $photoSelectionSubmitted =
                (
                    (string)(
                        $galleryRow['status']
                    )
                    ===
                    'closed'
                );
        }

    } catch (Throwable $e) {

        /*
         * Gallery table may not exist yet.
         */
    }
}


/* =========================================================
   WHATSAPP MESSAGE
   ========================================================= */

$waMsg =
    "Hello Imagination Nature Studio,\n\n"

    . "I have submitted a booking request.\n\n"

    . "Booking Number: "
    . $b['booking_number']
    . "\n"

    . "Name: "
    . $b['full_name']
    . "\n"

    . "Service: "
    . $b['service_name']
    . "\n"

    . "Date: "
    . $b['booking_date']
    . "\n"

    . "Time: "
    . substr(
        $b['start_time'],
        0,
        5
    )

    . " - "

    . substr(
        $b['end_time'],
        0,
        5
    )

    . "\n"

    . "Location: "
    . $b['location']
    . "\n"

    . "Total: "
    . rwf(
        $b['total_amount']
    )

    . "\n\n"

    . "Thank you.";


$waUrl =
    'https://wa.me/'
    . setting(
        'whatsapp',
        BIZ_WHATSAPP
    )
    . '?text='
    . rawurlencode(
        $waMsg
    );


/* =========================================================
   PAGE TITLE
   ========================================================= */

$pageTitle =
    'Booking Confirmation · '
    . $b['booking_number'];


require_once __DIR__ . '/../includes/header.php';

?>


<style>

/* =========================================================
   PAGE
   ========================================================= */

.confirm-page {
    padding: 5rem 1rem;
}

.confirm-card {
    max-width: 850px;
    margin: auto;
}


/* =========================================================
   STATUS BOX
   ========================================================= */

.status-box {
    margin: 1.5rem 0;
    padding: 1.2rem;
    border-radius: 14px;
    text-align: center;
}

.status-box.confirmed {
    background: rgba(60,170,90,.08);
    border: 1px solid rgba(60,170,90,.30);
}

.status-box.pending {
    background: rgba(224,122,47,.08);
    border: 1px solid rgba(224,122,47,.30);
}

.status-box.cancelled {
    background: rgba(200,60,60,.08);
    border: 1px solid rgba(200,60,60,.30);
}


/* =========================================================
   STATUS ICON
   ========================================================= */

.status-icon {
    font-size: 2rem;
    margin-bottom: .4rem;
}

.status-title {
    font-size: 1.15rem;
    font-weight: 700;
    margin-bottom: .35rem;
}

.status-text {
    color: #aaa19a;
    line-height: 1.6;
}


/* =========================================================
   PHOTO SELECTION
   ========================================================= */

.photo-selection-box {
    margin: 1rem 0;
    padding: 1.1rem;
    border-radius: 14px;
    background: rgba(60,170,90,.08);
    border: 1px solid rgba(60,170,90,.30);
    text-align: center;
}

.photo-selection-box p {
    color: #aaa19a;
    line-height: 1.6;
}

.photo-selection-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: .8rem 1.2rem;
    border-radius: 10px;
    background: #111;
    color: #fff;
    text-decoration: none;
    font-weight: 700;
}

.photo-selection-btn:hover {
    transform: translateY(-2px);
}


/* =========================================================
   INVOICE
   ========================================================= */

.doc-action {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.2rem 1.4rem;
    border-radius: 16px;
    text-decoration: none;

    border: 1px solid rgba(80,140,220,.4);

    background:
        linear-gradient(
            135deg,
            #0f1a2e 0%,
            #14203a 100%
        );

    color: #f3ece3;

    transition:
        all .3s cubic-bezier(.16,1,.3,1);

    max-width: 640px;
    margin: 1.6rem auto 0;
}

.doc-action:hover {
    transform: translateY(-3px);

    border-color:
        rgba(80,140,220,.7);

    box-shadow:
        0 16px 40px
        rgba(44,90,160,.3);
}

.doc-action .doc-icon {
    width: 52px;
    height: 52px;

    border-radius: 14px;

    display: grid;
    place-items: center;

    flex-shrink: 0;

    background:
        rgba(80,140,220,.18);

    border:
        1px solid
        rgba(80,140,220,.5);

    color: #7cb0e8;

    font-size: 1.4rem;
}

.doc-action .doc-info {
    flex: 1;
    min-width: 0;
}

.doc-action .doc-title {
    font-weight: 700;
    font-size: 1.05rem;
    color: #f3ece3;
    display: block;
    font-family: var(--font-serif);
}

.doc-action .doc-sub {
    font-size: .82rem;
    color: #8fa8c8;
    display: block;
    margin-top: .2rem;
    line-height: 1.4;
}

.doc-action .doc-arrow {
    color: #7cb0e8;
    font-size: 1.3rem;
    transition: transform .3s;
}

.doc-action:hover .doc-arrow {
    transform: translateX(6px);
}


/* =========================================================
   PAYMENT CARD
   ========================================================= */

.pm-single {
    max-width: 320px;

    margin: 1.4rem auto 0;

    background: #fff;

    border:
        1px solid
        #e6e1d6;

    border-radius: 16px;

    overflow: hidden;

    aspect-ratio: 1 / 1;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: .8rem;

    transition:
        transform .35s,
        box-shadow .35s;
}

.pm-single:hover {
    transform: translateY(-3px);

    box-shadow:
        0 12px 32px
        rgba(0,0,0,.35);
}

.pm-single img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}


/* =========================================================
   ACTIONS
   ========================================================= */

.confirm-actions {
    display: flex;
    gap: .8rem;
    flex-wrap: wrap;
    justify-content: center;
    margin-top: 1.5rem;
}


/* =========================================================
   CHECK BOOKING STATUS BUTTON
   ========================================================= */

.check-status-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;

    padding: .85rem 1.15rem;

    border-radius: 10px;

    text-decoration: none;

    font-weight: 700;

    color: #fff;

    background:
        linear-gradient(
            135deg,
            #222 0%,
            #111 100%
        );

    border:
        1px solid
        rgba(255,255,255,.12);

    transition:
        all .25s ease;
}

.check-status-btn:hover {
    transform: translateY(-2px);

    border-color:
        var(--accent-2);
}


/* =========================================================
   LIVE CHECK
   ========================================================= */

.live-check {
    text-align: center;
    margin-top: 1rem;
    color: #7a716a;
    font-size: .78rem;
}

.live-dot {
    display: inline-block;

    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: #e07a2f;

    margin-right: 5px;

    animation:
        livePulse 1.5s infinite;
}

@keyframes livePulse {

    0% {
        opacity: .3;
        transform: scale(.8);
    }

    50% {
        opacity: 1;
        transform: scale(1);
    }

    100% {
        opacity: .3;
        transform: scale(.8);
    }
}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 600px) {

    .confirm-page {
        padding: 4rem 1rem;
    }

    .confirm-actions {
        flex-direction: column;
    }

    .confirm-actions .btn,
    .confirm-actions a {
        width: 100%;
        text-align: center;
    }

    .doc-action {
        flex-direction: column;
        text-align: center;
    }

}

</style>


<section class="container confirm-page">

    <div class="confirm-card">


        <!-- =================================================
             PAGE TITLE
             ================================================= -->

        <span
            class="kicker"
            style="
                color:var(--accent-2);
                font-size:.72rem;
                letter-spacing:.3em;
                text-transform:uppercase;
            "
        >
            Booking Confirmation
        </span>


        <h1
            style="
                font-family:var(--font-serif);
                font-weight:500;
                font-size:1.6rem;
                margin:.8rem 0 .4rem
            "
        >
            Thank you,
            <?= e($b['full_name']) ?>.
        </h1>


        <p
            style="
                color:#a79e95;
                margin-bottom:.6rem
            "
        >
            Your booking number is
        </p>


        <div
            style="
                font-family:var(--font-serif);
                font-size:2.2rem;
                color:var(--accent-2);
                letter-spacing:.02em;
                margin:.4rem 0 1rem;
                word-break:break-all;
            "
        >
            <?= e($b['booking_number']) ?>
        </div>


        <!-- =================================================
             STATUS PILL
             ================================================= -->

        <div
            style="
                text-align:center;
                margin-bottom:1.4rem
            "
        >

            <span
                id="bookingStatusPill"
                class="status-pill <?= e($bookingStatus) ?>"
            >
                <?= e(
                    ucfirst($bookingStatus)
                ) ?>
            </span>

        </div>


        <!-- =================================================
             STATUS
             ================================================= -->

        <div
            id="bookingStatusBox"
            class="status-box <?= e($bookingStatus) ?>"
        >

            <?php if ($bookingStatus === 'confirmed'): ?>

                <div class="status-icon">
                    ✓
                </div>

                <div class="status-title">
                    Your booking is approved
                </div>

                <div class="status-text">
                    Your booking has been confirmed by
                    Imagination Nature Studio.
                    We look forward to capturing your moments.
                </div>


            <?php elseif ($bookingStatus === 'cancelled'): ?>

                <div class="status-icon">
                    ✕
                </div>

                <div class="status-title">
                    This booking was cancelled
                </div>

                <div class="status-text">
                    Contact us if this was unexpected.
                </div>


            <?php else: ?>

                <div class="status-icon">
                    ⏳
                </div>

                <div class="status-title">
                    Awaiting confirmation
                </div>

                <div class="status-text">
                    Your booking request has been received.
                    Our team will review it and confirm
                    the appointment.

                    <br><br>

                    <strong>
                        You will receive your booking confirmation
                        with the date, time and booking code once approved.
                        The invoice is available after confirmation. The official receipt is issued after full payment.
                    </strong>
                </div>

            <?php endif; ?>

        </div>


        <!-- =================================================
             LIVE STATUS
             ================================================= -->

        <?php if (
            $bookingStatus === 'pending'
            ||
            (
                $bookingStatus === 'confirmed'
                &&
                !$invoiceAvailable
            )
        ): ?>

            <div
                id="liveCheck"
                class="live-check"
            >

                <span class="live-dot"></span>

                Checking booking status automatically…

            </div>

        <?php endif; ?>


        <!-- =================================================
             BOOKING SUMMARY
             ================================================= -->

        <div
            class="summary"
            style="
                text-align:left;
                margin-top:2rem
            "
        >

            <div class="summary-row">

                <span>
                    Service
                </span>

                <span>
                    <?= e(
                        $b['service_name']
                    ) ?>
                </span>

            </div>


            <?php if (!empty($b['package_name'])): ?>

                <div class="summary-row">

                    <span>
                        Package
                    </span>

                    <span>
                        <?= e(
                            $b['package_name']
                        ) ?>
                    </span>

                </div>

            <?php endif; ?>


            <div class="summary-row">

                <span>
                    Date
                </span>

                <span>
                    <?= e(
                        date(
                            'd F Y',
                            strtotime(
                                $b['booking_date']
                            )
                        )
                    ) ?>
                </span>

            </div>


            <div class="summary-row">

                <span>
                    Time
                </span>

                <span>

                    <?= e(
                        substr(
                            $b['start_time'],
                            0,
                            5
                        )
                    ) ?>

                    –

                    <?= e(
                        substr(
                            $b['end_time'],
                            0,
                            5
                        )
                    ) ?>

                </span>

            </div>


            <div class="summary-row">

                <span>
                    Location
                </span>

                <span>
                    <?= e(
                        $b['location']
                    ) ?>
                </span>

            </div>

        </div>

        <!-- =================================================
             CUSTOM PRICING SUMMARY
             ================================================= -->

        <?php if ($agreedTotal > 0 || $originalAmount > 0 || !empty($bookingItems) || $discountAmount > 0): ?>
            <div id="customPricingArea" class="summary" style="text-align:left;margin-top:1.5rem;padding:1.2rem;border:1px solid rgba(224,122,47,.25);border-radius:14px">
                <h3 style="font-family:var(--font-serif);margin:0 0 1rem;color:var(--accent-2)">Booking Price Details</h3>
                <div id="customPricingRows">
                    <?php foreach ($bookingItems as $item): ?>
                        <div class="summary-row"><span><?= e($item['item_name']) ?></span></div>
                    <?php endforeach; ?>
                    <div class="summary-row" style="border-top:1px solid rgba(150,140,130,.3);padding-top:.8rem;margin-top:.5rem"><strong>Agreed total</strong><strong><?= e(rwf($agreedTotal)) ?></strong></div>
                    <div class="summary-row"><span>Amount paid</span><strong id="liveAmountPaid"><?= e(rwf($amountPaid)) ?></strong></div>
                    <div class="summary-row"><span>Balance due</span><strong id="liveBalance"><?= e(rwf($balanceDue)) ?></strong></div>
                </div>
            </div>
        <?php endif; ?>


        <!-- =================================================
             STUDIO PHOTO SELECTION
             ================================================= -->

        <?php if (
            $isStudioBooking
            &&
            $bookingStatus === 'confirmed'
            &&
            $photoSelectionUrl
            &&
            !$photoSelectionSubmitted
        ): ?>

            <div
                class="photo-selection-box"
                id="photoSelectionArea"
            >

                <strong>
                    📸 Your photos are ready for selection
                </strong>

                <p>
                    Choose the photos you want us to edit.
                    Your final invoice will be calculated
                    from the number of photos you select.
                </p>

                <a
                    class="photo-selection-btn"
                    target="_blank"
                    rel="noopener noreferrer"
                    href="<?= e(
                        $photoSelectionUrl
                    ) ?>"
                >
                    Select My Photos
                </a>

            </div>


        <?php elseif (
            $isStudioBooking
            &&
            $bookingStatus === 'confirmed'
            &&
            $photoSelectionSubmitted
            &&
            !$invoiceAvailable
        ): ?>

            <div
                class="photo-selection-box"
                id="photoSelectionArea"
            >

                <strong>
                    ✅ Photo selection submitted
                </strong>

                <p>
                    Your final invoice is being prepared.
                    Please check again shortly.
                </p>

            </div>

        <?php endif; ?>


        <!-- =================================================
             PAYMENT CONFIRMATION — AVAILABLE AFTER ANY PAYMENT
             ================================================= -->
        <div
            id="paymentProofArea"
            style="margin-top:2rem;padding:1.25rem;border:1px solid rgba(60,170,90,.35);border-radius:14px;background:rgba(60,170,90,.06);<?= $amountPaid > 0 ? '' : 'display:none;' ?>"
        >
            <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap">
                <h3 style="font-family:var(--font-serif);margin:0;color:#3caa5a">Payment Confirmation</h3>
                <strong id="paymentProofStatus" style="font-size:.8rem;letter-spacing:.08em;color:#3caa5a"><?= $fullyPaid ? 'FULLY PAID' : 'PAYMENT RECEIVED' ?></strong>
            </div>
            <p style="margin:.7rem 0;color:#8a8178">This confirms the payment amount recorded by Imagination Nature Studio.</p>
            <div class="summary-row"><span>Booking Number</span><strong><?= e($b['booking_number']) ?></strong></div>
            <div class="summary-row"><span>Total Booking Amount</span><strong><?= e(rwf($totalDue)) ?></strong></div>
            <div class="summary-row"><span>Amount Received</span><strong id="proofAmountPaid"><?= e(rwf($amountPaid)) ?></strong></div>
            <div class="summary-row"><span>Remaining Balance</span><strong id="proofBalance"><?= e(rwf($balanceDue)) ?></strong></div>
            <p style="font-size:.8rem;color:#8a8178;margin:.8rem 0 0">Keep this confirmation for your records. The official receipt becomes available after the full balance is paid and confirmed. Your invoice is available once the booking is confirmed.</p>
            <button type="button" class="btn btn-ghost" style="margin-top:1rem" onclick="window.print()">Print Payment Confirmation</button>
        </div>


        <!-- =================================================
             INVOICE — AVAILABLE AFTER BOOKING CONFIRMATION
             ================================================= -->

        <div
            id="invoiceArea"
            style="
                margin-top:2rem;
                <?= $invoiceAvailable
                    ? ''
                    : 'display:none;'
                ?>
            "
        >

            <h3
                style="
                    font-family:var(--font-serif);
                    font-size:1.15rem;
                    margin:0 0 .4rem;
                    color:var(--accent-2)
                "
            >
                Your Invoice
            </h3>


            <p
                style="
                    color:#7a716a;
                    font-size:.88rem;
                    margin:0
                "
            >
                Your booking is fully paid. Your final invoice is now available.
            </p>


            <a
                id="invoiceLink"
                class="doc-action"
                href="<?= e(SITE_URL) ?>/booking/download-invoice.php?ref=<?= urlencode($b['booking_number']) ?>"
                target="_blank"
                rel="noopener"
            >

                <span class="doc-icon">
                    📄
                </span>


                <span class="doc-info">

                    <span class="doc-title">
                        Download Invoice
                    </span>

                    <span class="doc-sub">
                        Amount due &amp;
                        payment instructions
                    </span>

                </span>


                <span class="doc-arrow">
                    →
                </span>

            </a>


            <p
                style="
                    text-align:center;
                    color:#7a716a;
                    font-size:.85rem;
                    margin-top:1rem
                "
            >
                A receipt will be issued by our team
                once your payment is confirmed.
            </p>

        </div>


        <!-- =================================================
             CONFIRMED BUT INVOICE NOT READY
             ================================================= -->

        <div
            id="invoiceWaiting"
            style="
                margin-top:2rem;
                padding:1rem;
                border:1px dashed #7a716a;
                border-radius:12px;
                text-align:center;
                color:#7a716a;

                <?= (
                    $bookingStatus === 'confirmed'
                    &&
                    !$invoiceAvailable
                )
                    ? ''
                    : 'display:none;'
                ?>
            "
        >

            <strong>
                <?= $isStudioBooking
                    ? 'Your Studio Photoshoot booking is confirmed!'
                    : 'Your booking is approved.'
                ?>
            </strong>

            <?php if ($isStudioBooking): ?>
                <div style="margin-top:.7rem;line-height:1.8">
                    <div><strong>Booking code:</strong> <?= e($b['booking_number']) ?></div>
                    <div><strong>Date:</strong> <?= e(date('d M Y', strtotime((string)$b['booking_date']))) ?></div>
                    <div><strong>Time:</strong> <?= e(substr((string)$b['start_time'], 0, 5)) ?> – <?= e(substr((string)$b['end_time'], 0, 5)) ?></div>
                </div>
                <p style="margin:.8rem 0 0">
                    Please keep your booking code and arrive at the scheduled time.
                    Your payment confirmation appears after a payment is recorded. Your final invoice and paid receipt become available after full payment.
                </p>
            <?php else: ?>
                <br>
                Your invoice will be available after confirmation. Your official receipt will be issued after full payment.
            <?php endif; ?>

        </div>




        <!-- =================================================
             EMAIL NOTICE
             ================================================= -->

        <?php if (
            !empty(
                trim(
                    (string)(
                        $b['email']
                        ??
                        ''
                    )
                )
            )
        ): ?>

            <p
                style="
                    color:#b9b1a8;
                    margin:2rem 0 1rem;
                    text-align:center
                "
            >

                A confirmation email has been sent to

                <strong>
                    <?= e(
                        $b['email']
                    ) ?>
                </strong>

            </p>

        <?php endif; ?>


        <!-- =================================================
             ACTIONS
             ================================================= -->

        <div class="confirm-actions">


            <!-- WHATSAPP -->

            <a
                class="btn btn-light"
                href="<?= e($waUrl) ?>"
                target="_blank"
                rel="noopener"
            >
                Send via WhatsApp
            </a>


            <!-- CHECK BOOKING STATUS -->

            <a
                class="check-status-btn"
                href="<?= e(SITE_URL) ?>/booking/status.php?ref=<?= urlencode($b['booking_number']) ?>"
            >
                🔎 Check Booking Status
            </a>


            <!-- HOME -->

            <a
                class="btn btn-ghost"
                href="<?= e(SITE_URL) ?>/"
            >
                Back to Home
            </a>

        </div>


    </div>

</section>


<script>

(function () {


    /* =====================================================
       BOOKING REFERENCE
       ===================================================== */

    const bookingRef =
        <?= json_encode(
            $b['booking_number']
        ) ?>;


    const siteUrl =
        <?= json_encode(
            SITE_URL
        ) ?>;


    /* =====================================================
       ELEMENTS
       ===================================================== */

    const statusPill =
        document.getElementById(
            'bookingStatusPill'
        );


    const statusBox =
        document.getElementById(
            'bookingStatusBox'
        );


    const invoiceArea =
        document.getElementById(
            'invoiceArea'
        );


    const invoiceWaiting =
        document.getElementById(
            'invoiceWaiting'
        );


    const invoiceLink =
        document.getElementById(
            'invoiceLink'
        );


    const liveCheck =
        document.getElementById(
            'liveCheck'
        );


    const liveAmountPaid =
        document.getElementById(
            'liveAmountPaid'
        );


    const liveBalance =
        document.getElementById(
            'liveBalance'
        );

    const paymentProofArea = document.getElementById('paymentProofArea');
    const proofAmountPaid = document.getElementById('proofAmountPaid');
    const proofBalance = document.getElementById('proofBalance');
    const paymentProofStatus = document.getElementById('paymentProofStatus');


    /* =====================================================
       RWF FORMAT
       ===================================================== */

    function formatRwf(amount) {

        const number =
            Number(
                amount || 0
            );


        return 'RWF ' +
            Math.round(number)
                .toLocaleString(
                    'en-US'
                );
    }


    /* =====================================================
       UPDATE STATUS
       ===================================================== */

    function updateStatus(data) {


        if (
            !data
            ||
            !data.success
        ) {
            return;
        }


        const status =
            String(
                data.booking_status
                ||
                'pending'
            ).toLowerCase();


        /* -------------------------------------------------
           PAYMENT
           ------------------------------------------------- */

        if (liveAmountPaid) {

            liveAmountPaid.textContent =
                formatRwf(
                    data.amount_paid
                );
        }


        if (liveBalance) {
            liveBalance.textContent = formatRwf(data.balance);
        }

        const paidNow = Number(data.amount_paid || 0);
        const balanceNow = Number(data.balance || 0);
        const fullyPaidNow = Boolean(data.fully_paid);

        if (proofAmountPaid) proofAmountPaid.textContent = formatRwf(paidNow);
        if (proofBalance) proofBalance.textContent = formatRwf(balanceNow);
        if (paymentProofArea) paymentProofArea.style.display = paidNow > 0 ? 'block' : 'none';
        if (paymentProofStatus && paidNow > 0) {
            paymentProofStatus.textContent = fullyPaidNow ? 'FULLY PAID' : 'PAYMENT RECEIVED';
        }

        /* -------------------------------------------------
           CONFIRMED
           ------------------------------------------------- */

        if (
            status === 'confirmed'
        ) {


            if (statusPill) {

                statusPill.className =
                    'status-pill confirmed';

                statusPill.textContent =
                    'Confirmed';
            }


            if (statusBox) {

                statusBox.className =
                    'status-box confirmed';

                statusBox.innerHTML = `

                    <div class="status-icon">
                        ✓
                    </div>

                    <div class="status-title">
                        Your booking is approved
                    </div>

                    <div class="status-text">
                        Your booking has been confirmed by
                        Imagination Nature Studio.
                        We look forward to capturing your moments.
                    </div>

                `;
            }


            /* -------------------------------------------------
               INVOICE READY
               ------------------------------------------------- */

            if (
                data.invoice_available
            ) {


                if (invoiceWaiting) {

                    invoiceWaiting.style.display =
                        'none';
                }


                if (invoiceArea) {

                    invoiceArea.style.display =
                        'block';
                }


                if (invoiceLink) {

                    invoiceLink.href =
                        siteUrl
                        +
                        '/booking/download-invoice.php?ref='
                        +
                        encodeURIComponent(
                            bookingRef
                        );
                }


                stopPolling();


            } else {


                if (invoiceWaiting) {

                    invoiceWaiting.style.display =
                        'block';
                }


                if (invoiceArea) {

                    invoiceArea.style.display =
                        'none';
                }

            }


            return;
        }


        /* -------------------------------------------------
           CANCELLED
           ------------------------------------------------- */

        if (
            status === 'cancelled'
        ) {


            if (statusPill) {

                statusPill.className =
                    'status-pill cancelled';

                statusPill.textContent =
                    'Cancelled';
            }


            if (statusBox) {

                statusBox.className =
                    'status-box cancelled';

                statusBox.innerHTML = `

                    <div class="status-icon">
                        ✕
                    </div>

                    <div class="status-title">
                        This booking was cancelled
                    </div>

                    <div class="status-text">
                        Contact us if this was unexpected.
                    </div>

                `;
            }


            if (invoiceArea) {

                invoiceArea.style.display =
                    'none';
            }


            if (invoiceWaiting) {

                invoiceWaiting.style.display =
                    'none';
            }


            stopPolling();

            return;
        }


        /* -------------------------------------------------
           PENDING
           ------------------------------------------------- */

        if (
            status === 'pending'
        ) {


            if (statusPill) {

                statusPill.className =
                    'status-pill pending';

                statusPill.textContent =
                    'Pending Confirmation';
            }


            if (statusBox) {

                statusBox.className =
                    'status-box pending';

                statusBox.innerHTML = `

                    <div class="status-icon">
                        ⏳
                    </div>

                    <div class="status-title">
                        Awaiting confirmation
                    </div>

                    <div class="status-text">
                        Your booking request has been received.
                        Our team will review it and confirm
                        the appointment.

                        <br><br>

                        <strong>
                            Your invoice will become available
                            after your booking is approved.
                        </strong>
                    </div>

                `;
            }

        }

    }


    /* =====================================================
       CHECK DATABASE
       ===================================================== */

    async function checkBookingStatus() {

        try {


            const url =
                siteUrl
                +
                '/booking/confirmation.php'
                +
                '?check_status=1'
                +
                '&ref='
                +
                encodeURIComponent(
                    bookingRef
                )
                +
                '&_='
                +
                Date.now();


            const response =
                await fetch(
                    url,
                    {
                        method: 'GET',

                        cache: 'no-store',

                        headers: {

                            'Accept':
                                'application/json',

                            'Cache-Control':
                                'no-cache'
                        }
                    }
                );


            if (
                !response.ok
            ) {
                return;
            }


            const data =
                await response.json();


            updateStatus(
                data
            );


        } catch (error) {

            console.log(
                'Booking status check failed:',
                error
            );

        }

    }


    /* =====================================================
       POLLING
       ===================================================== */

    let pollingTimer = null;


    function startPolling() {


        /* Check immediately */

        checkBookingStatus();


        /* Check every 3 seconds */

        pollingTimer =
            setInterval(
                checkBookingStatus,
                3000
            );

    }


    function stopPolling() {


        if (
            pollingTimer !== null
        ) {

            clearInterval(
                pollingTimer
            );

            pollingTimer = null;
        }


        if (liveCheck) {

            liveCheck.style.display =
                'none';
        }

    }


    /* =====================================================
       INITIAL STATUS
       ===================================================== */

    const initialStatus =
        <?= json_encode(
            $bookingStatus
        ) ?>;


    const initialInvoiceAvailable =
        <?= $invoiceAvailable
            ? 'true'
            : 'false'
        ?>;


    if (

        initialStatus === 'pending'

        ||

        (
            initialStatus === 'confirmed'
            &&
            !initialInvoiceAvailable
        )

    ) {

        startPolling();

    } else {

        stopPolling();

    }


})();

</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>