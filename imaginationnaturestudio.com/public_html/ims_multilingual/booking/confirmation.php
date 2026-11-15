<?php
/**
 * Booking Confirmation — Client
 *
 * FLOW:
 * Pending    → Waiting for confirmation
 * Confirmed  → Approved + Invoice available
 * Cancelled  → Booking cancelled
 *
 * LIVE STATUS:
 * The page automatically checks the booking status
 * every 3 seconds without refreshing the browser.
 */

require_once __DIR__ . '/../includes/functions.php';


/* =========================================================
   GET BOOKING REFERENCE
   ========================================================= */

$ref = trim(
    $_GET['ref'] ??
    ($_SESSION['last_booking_number'] ?? '')
);

if (!$ref) {

    redirect(
        SITE_URL . '/booking.php'
    );
}


/* =========================================================
   LIVE STATUS AJAX ENDPOINT
   =========================================================
   This runs when JavaScript requests:

   confirmation.php?check_status=1&ref=INS-...
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

    $statusStmt = db()->prepare(
        "SELECT
            b.booking_status,
            b.invoice_path,
            b.invoice_number,
            b.payment_status,
            b.amount_paid,
            b.balance
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
       Normalize status
       ----------------------------------------------------- */

    $liveStatus = strtolower(
        trim(
            (string)(
                $live['booking_status']
                ?? 'pending'
            )
        )
    );


    /* -----------------------------------------------------
       Invoice available ONLY after confirmation
       ----------------------------------------------------- */

    $liveInvoiceAvailable = (
        $liveStatus === 'confirmed'
        &&
        !empty($live['invoice_path'])
    );


    echo json_encode([
        'success' => true,

        'booking_status' =>
            $liveStatus,

        'invoice_available' =>
            $liveInvoiceAvailable,

        'invoice_number' =>
            $live['invoice_number'] ?? null,

        'payment_status' =>
            $live['payment_status'] ?? 'unpaid',

        'amount_paid' =>
            (float)($live['amount_paid'] ?? 0),

        'balance' =>
            (float)($live['balance'] ?? 0)
    ]);

    exit;
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
        SITE_URL . '/booking.php'
    );
}


/* =========================================================
   NORMALIZE BOOKING STATUS
   ========================================================= */

$bookingStatus = strtolower(
    trim(
        (string)(
            $b['booking_status']
            ?? 'pending'
        )
    )
);


/* =========================================================
   INVOICE AVAILABILITY
   ========================================================= */

$invoiceAvailable = (
    $bookingStatus === 'confirmed'
    &&
    !empty($b['invoice_path'])
);


/* =========================================================
   WHATSAPP SHARE MESSAGE
   ========================================================= */

$waMsg =
    "Hello Imagination Nature Studio,\n\n"
    . "I have submitted a booking request.\n\n"
    . "Booking Number: {$b['booking_number']}\n"
    . "Name: {$b['full_name']}\n"
    . "Service: {$b['service_name']}\n"
    . "Date: {$b['booking_date']}\n"
    . "Time: "
    . substr($b['start_time'], 0, 5)
    . " - "
    . substr($b['end_time'], 0, 5)
    . "\n"
    . "Location: {$b['location']}\n"
    . "Total: "
    . rwf($b['total_amount'])
    . "\n\n"
    . "Thank you.";


$waUrl =
    'https://wa.me/'
    . setting(
        'whatsapp',
        BIZ_WHATSAPP
    )
    . '?text='
    . rawurlencode($waMsg);


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
   CONFIRMATION PAGE
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
   INVOICE BUTTON
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
    border-color: rgba(80,140,220,.7);
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
    background: rgba(80,140,220,.18);
    border: 1px solid rgba(80,140,220,.5);
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
    border: 1px solid #e6e1d6;
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
        0 12px 32px rgba(0,0,0,.35);
}

.pm-single img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}


/* =========================================================
   ACTION BUTTONS
   ========================================================= */

.confirm-actions {
    display: flex;
    gap: .8rem;
    flex-wrap: wrap;
    justify-content: center;
    margin-top: 1.5rem;
}


/* =========================================================
   LIVE CHECK INDICATOR
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
    animation: livePulse 1.5s infinite;
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

    .confirm-actions .btn {
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
            class="booking-no"
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
                Confirmation
            </span>

        </div>


        <!-- =================================================
             BOOKING STATUS
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
                        Your invoice will become available
                        after your booking is approved.
                    </strong>
                </div>


            <?php endif; ?>


        </div>


        <!-- =================================================
             LIVE STATUS CHECK
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
                <span>Service</span>
                <span>
                    <?= e($b['service_name']) ?>
                </span>
            </div>


            <?php if (!empty($b['package_name'])): ?>

                <div class="summary-row">

                    <span>
                        Package
                    </span>

                    <span>
                        <?= e($b['package_name']) ?>
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
                    <?= e($b['location']) ?>
                </span>

            </div>


            <div class="summary-row">

                <span>
                    Total
                </span>

                <strong>
                    <?= e(
                        rwf(
                            $b['total_amount']
                        )
                    ) ?>
                </strong>

            </div>


            <div class="summary-row">

                <span>
                    Deposit (30%)
                </span>

                <strong>
                    <?= e(
                        rwf(
                            $b['deposit_amount']
                        )
                    ) ?>
                </strong>

            </div>


            <div class="summary-row">

                <span>
                    Amount paid
                </span>

                <strong id="liveAmountPaid">
                    <?= e(
                        rwf(
                            $b['amount_paid']
                        )
                    ) ?>
                </strong>

            </div>


            <div class="summary-row">

                <span>
                    Balance
                </span>

                <strong id="liveBalance">
                    <?= e(
                        rwf(
                            $b['balance']
                        )
                    ) ?>
                </strong>

            </div>

        </div>


        <!-- =================================================
             INVOICE AREA
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
                Your booking has been approved.
                Your invoice is now available.
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
                Your booking is approved.
            </strong>

            <br>

            Your invoice is being prepared.

        </div>


        <!-- =================================================
             MOMO PAYMENT
             ================================================= -->

        <div
            style="
                margin-top:2.4rem
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
                Pay with MoMo
            </h3>


            <p
                style="
                    color:#7a716a;
                    font-size:.88rem;
                    margin:0
                "
            >
                Scan the QR code below to make your payment.
            </p>


            <?php

            $momoPath =
                SITE_ROOT .
                '/assets/images/payments/momo.png';

            ?>


            <?php if (is_file($momoPath)): ?>

                <div class="pm-single">

                    <img
                        src="<?= e(SITE_URL) ?>/assets/images/payments/momo.png"
                        alt="MTN Mobile Money"
                        loading="lazy"
                    >

                </div>

            <?php endif; ?>


            <p
                style="
                    text-align:center;
                    color:#7a716a;
                    font-size:.85rem;
                    margin-top:1.4rem;
                    padding:1rem;
                    border:1px dashed #262220;
                    border-radius:10px;
                    background:rgba(224,122,47,.03);
                "
            >

                After payment, send proof to WhatsApp

                <a
                    href="https://wa.me/<?= e(setting('whatsapp', BIZ_WHATSAPP)) ?>"
                    target="_blank"
                    rel="noopener"
                    style="color:var(--accent-2)"
                >
                    <?= e(
                        setting(
                            'phone_primary',
                            BIZ_PHONE_1
                        )
                    ) ?>
                </a>

                with your booking number

                <strong
                    style="
                        color:var(--accent-2)
                    "
                >
                    <?= e(
                        $b['booking_number']
                    ) ?>
                </strong>

            </p>

        </div>


        <!-- =================================================
             EMAIL NOTICE
             ================================================= -->

        <p
            style="
                color:#b9b1a8;
                margin:2rem 0 1rem;
                text-align:center
            "
        >

            A confirmation email has been sent to

            <strong>
                <?= e($b['email']) ?>
            </strong>

        </p>


        <!-- =================================================
             ACTIONS
             ================================================= -->

        <div class="confirm-actions">

            <a
                class="btn btn-light"
                href="<?= e($waUrl) ?>"
                target="_blank"
                rel="noopener"
            >
                Send via WhatsApp
            </a>


            <a
                class="btn btn-ghost"
                href="<?= e(SITE_URL) ?>/"
            >
                Back to home
            </a>

        </div>


    </div>

</section>


<script>
(function () {

    /*
     * =====================================================
     * LIVE BOOKING STATUS
     * =====================================================
     *
     * Checks the database every 3 seconds.
     *
     * NO browser refresh.
     */

    const bookingRef =
        <?= json_encode($b['booking_number']) ?>;

    const siteUrl =
        <?= json_encode(SITE_URL) ?>;


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


    /*
     * =====================================================
     * RWF FORMAT
     * =====================================================
     */

    function formatRwf(amount) {

        const number =
            Number(amount || 0);

        return 'RWF ' +
            Math.round(number)
                .toLocaleString('en-US');
    }


    /*
     * =====================================================
     * UPDATE STATUS UI
     * =====================================================
     */

    function updateStatus(data) {

        if (!data || !data.success) {
            return;
        }


        const status =
            String(
                data.booking_status || 'pending'
            ).toLowerCase();


        /* -------------------------------------------------
           Update payment information
           ------------------------------------------------- */

        if (liveAmountPaid) {

            liveAmountPaid.textContent =
                formatRwf(
                    data.amount_paid
                );
        }


        if (liveBalance) {

            liveBalance.textContent =
                formatRwf(
                    data.balance
                );
        }


        /* -------------------------------------------------
           CONFIRMED
           ------------------------------------------------- */

        if (status === 'confirmed') {

            if (statusPill) {

                statusPill.className =
                    'status-pill confirmed';

                statusPill.textContent =
                    'Confirmed Confirmation';
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


            /*
             * Invoice is ready
             */

            if (data.invoice_available) {

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
                        + '/booking/download-invoice.php?ref='
                        + encodeURIComponent(
                            bookingRef
                        );
                }


                /*
                 * Stop polling once everything
                 * is confirmed and invoice exists.
                 */

                stopPolling();

            } else {

                /*
                 * Confirmed but invoice is not
                 * ready yet. Continue checking.
                 */

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

        if (status === 'cancelled') {

            if (statusPill) {

                statusPill.className =
                    'status-pill cancelled';

                statusPill.textContent =
                    'Cancelled Confirmation';
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

        if (status === 'pending') {

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

            return;
        }

    }


    /*
     * =====================================================
     * CHECK DATABASE
     * =====================================================
     */

    async function checkBookingStatus() {

        try {

            const url =
                siteUrl
                + '/booking/confirmation.php'
                + '?check_status=1'
                + '&ref='
                + encodeURIComponent(
                    bookingRef
                )
                + '&_='
                + Date.now();


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


            if (!response.ok) {
                return;
            }


            const data =
                await response.json();


            updateStatus(data);

        } catch (error) {

            /*
             * Do not disturb the client if one
             * request temporarily fails.
             *
             * The next check will try again.
             */

            console.log(
                'Booking status check failed:',
                error
            );
        }

    }


    /*
     * =====================================================
     * POLLING TIMER
     * =====================================================
     */

    let pollingTimer = null;


    function startPolling() {

        /*
         * Check immediately.
         */

        checkBookingStatus();


        /*
         * Then check every 3 seconds.
         */

        pollingTimer =
            setInterval(
                checkBookingStatus,
                3000
            );
    }


    function stopPolling() {

        if (pollingTimer !== null) {

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


    /*
     * =====================================================
     * START ONLY WHEN NEEDED
     * =====================================================
     */

    const initialStatus =
        <?= json_encode($bookingStatus) ?>;

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