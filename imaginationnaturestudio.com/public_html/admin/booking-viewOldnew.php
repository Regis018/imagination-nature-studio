<?php
/**
 * Imagination Nature Studio
 * ADMIN BOOKING VIEW
 */

require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    redirect('bookings.php');
}

/* =========================================
   RETURN LOCATION
========================================= */

$returnTo = ($_GET['return'] ?? '') === 'calendar'
    ? 'calendar.php'
    : 'bookings.php';

$returnQuery = '';

if ($returnTo === 'calendar') {
    $returnY = (int)($_GET['return_y'] ?? date('Y'));
    $returnM = (int)($_GET['return_m'] ?? date('n'));

    if (
        $returnY >= 2000 &&
        $returnY <= 2100 &&
        $returnM >= 1 &&
        $returnM <= 12
    ) {
        $returnQuery = '?y=' . $returnY . '&m=' . $returnM;
    }
}

$returnSelf = 'booking-view.php?id=' . $id;

if ($returnTo === 'calendar' && $returnQuery !== '') {
    $returnSelf .= '&return=calendar&return_y=' .
        $returnY . '&return_m=' . $returnM;
}

/* =========================================
   LOAD INVOICE GENERATOR
========================================= */

function load_invoice_generator(): void
{
    $file = __DIR__ . '/../booking/generate-invoice.php';

    if (!is_file($file)) {
        throw new RuntimeException('Invoice generator file not found.');
    }

    require_once $file;

    if (!function_exists('generate_invoice')) {
        throw new RuntimeException('generate_invoice() is not defined.');
    }
}

/* =========================================
   LOAD BOOKING
========================================= */

function load_booking(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("
        SELECT
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
        JOIN customers c ON c.id = b.customer_id
        JOIN services s ON s.id = b.service_id
        LEFT JOIN packages p ON p.id = b.package_id
        WHERE b.id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

$b = load_booking($pdo, $id);

if (!$b) {
    redirect('bookings.php');
}

/* =========================================
   PAYMENT CHECK
========================================= */

function booking_is_fully_paid(array $booking): bool
{
    $total = (float)($booking['total_amount'] ?? 0);
    $paid = (float)($booking['amount_paid'] ?? 0);
    $balance = (float)($booking['balance'] ?? 0);
    $status = strtolower(trim((string)($booking['payment_status'] ?? '')));

    return (
        $total > 0 &&
        $status === 'paid' &&
        $paid + 0.01 >= $total &&
        $balance <= 0.01
    );
}

$fullyPaid = booking_is_fully_paid($b);

/* =========================================
   POST ACTIONS
========================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    verify_csrf($_POST['csrf'] ?? null)
) {
    $action = $_POST['action'] ?? '';

    /* -------------------------------------
       ISSUE RECEIPT
    ------------------------------------- */

    if ($action === 'issue_receipt') {

        if (!booking_is_fully_paid($b)) {
            flash('err', 'Receipt can only be issued after full payment.');
            redirect($returnSelf);
        }

        try {
            require_once __DIR__ . '/../booking/generate-receipt.php';

            if (!function_exists('generate_receipt')) {
                throw new RuntimeException('Receipt generator is missing.');
            }

            generate_receipt($id);

            flash('ok', 'Receipt issued successfully.');

        } catch (Throwable $e) {
            error_log('[RECEIPT] ' . $e->getMessage());
            flash('err', 'Receipt generation failed: ' . $e->getMessage());
        }

        redirect($returnSelf);
    }

    /* -------------------------------------
       REGENERATE INVOICE
    ------------------------------------- */

    if ($action === 'regenerate_invoice') {

        if (!booking_is_fully_paid($b)) {
            flash('err', 'Invoice can only be generated after full payment.');
            redirect($returnSelf);
        }

        try {
            load_invoice_generator();

            generate_invoice($id);

            flash('ok', 'Invoice generated successfully.');

        } catch (Throwable $e) {
            error_log('[INVOICE] ' . $e->getMessage());
            flash('err', 'Invoice generation failed: ' . $e->getMessage());
        }

        redirect($returnSelf);
    }

    /* -------------------------------------
       SAVE BOOKING AND PAYMENT
    ------------------------------------- */

    $status = $_POST['booking_status'] ?? $b['booking_status'];

    $paymentMethod = $_POST['payment_method'] ?? $b['payment_method'];

    $paymentStatus = $_POST['payment_status'] ?? $b['payment_status'];

    $amountPaid = (float)($_POST['amount_paid'] ?? $b['amount_paid']);

    $adminNotes = trim($_POST['admin_notes'] ?? '');

    $allowedBookingStatuses = [
        'pending',
        'confirmed',
        'completed',
        'cancelled'
    ];

    $allowedPaymentStatuses = [
        'unpaid',
        'partial',
        'paid'
    ];

    $allowedMethods = [
        'cash',
        'mobile_money',
        'bank_transfer',
        'other'
    ];

    if (!in_array($status, $allowedBookingStatuses, true)) {
        $status = $b['booking_status'];
    }

    if (!in_array($paymentMethod, $allowedMethods, true)) {
        $paymentMethod = $b['payment_method'];
    }

    if (!in_array($paymentStatus, $allowedPaymentStatuses, true)) {
        $paymentStatus = $b['payment_status'];
    }

    $total = (float)$b['total_amount'];

    if ($amountPaid < 0) {
        flash('err', 'Amount paid cannot be negative.');
        redirect($returnSelf);
    }

    if ($amountPaid > $total) {
        flash('err', 'Amount paid cannot exceed the booking total.');
        redirect($returnSelf);
    }

    if ($paymentStatus === 'paid') {
        $amountPaid = $total;
    } elseif ($paymentStatus === 'unpaid') {
        $amountPaid = 0;
    }

    if ($amountPaid <= 0) {
        $paymentStatus = 'unpaid';
    } elseif ($amountPaid >= $total && $total > 0) {
        $paymentStatus = 'paid';
    } else {
        $paymentStatus = 'partial';
    }

    $balance = max(0, $total - $amountPaid);

    $oldAmountPaid = (float)$b['amount_paid'];

    /*
     * Record only a positive additional payment.
     * This prevents duplicate payment records when the same
     * amount is saved again.
     */
    $paymentDelta = $amountPaid - $oldAmountPaid;

    if ($paymentDelta < -0.01) {
        flash(
            'err',
            'The paid amount cannot be reduced here. Please correct the payment records first.'
        );
        redirect($returnSelf);
    }

    $shouldGenerateInvoice = (
        $paymentStatus === 'paid' &&
        $total > 0 &&
        $amountPaid + 0.01 >= $total &&
        $balance <= 0.01 &&
        empty($b['invoice_path'])
    );

    /*
     * TRANSACTION
     */

    try {

        $pdo->beginTransaction();

        /* Update booking */

        $update = $pdo->prepare("
            UPDATE bookings
            SET
                booking_status = ?,
                payment_status = ?,
                payment_method = ?,
                amount_paid = ?,
                balance = ?,
                admin_notes = ?
            WHERE id = ?
        ");

        $update->execute([
            $status,
            $paymentStatus,
            $paymentMethod,
            $amountPaid,
            $balance,
            $adminNotes,
            $id
        ]);

        /* Insert payment without notes column */

        if ($paymentDelta > 0.009) {

            $payment = $pdo->prepare("
                INSERT INTO payments
                (
                    booking_id,
                    amount,
                    payment_method,
                    payment_date
                )
                VALUES (?, ?, ?, ?)
            ");

            $payment->execute([
                $id,
                $paymentDelta,
                $paymentMethod,
                date('Y-m-d')
            ]);
        }

        $pdo->commit();

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            '[BOOKING VIEW SAVE] ' .
            $e->getMessage()
        );

        flash('err', 'Could not save booking: ' . $e->getMessage());

        redirect($returnSelf);
    }

    /* -------------------------------------
       GENERATE INVOICE AFTER PAYMENT SAVED
    ------------------------------------- */

    if ($shouldGenerateInvoice) {

        try {
            load_invoice_generator();

            generate_invoice($id);

            flash(
                'ok',
                'Payment confirmed and invoice generated successfully.'
            );

        } catch (Throwable $e) {

            error_log('[INVOICE] ' . $e->getMessage());

            flash(
                'err',
                'Payment was saved, but invoice generation failed: ' .
                $e->getMessage()
            );
        }

    } else {

        flash('ok', 'Booking updated successfully.');
    }

    redirect($returnSelf);
}

/* =========================================
   RELOAD BOOKING
========================================= */

$b = load_booking($pdo, $id);

$fullyPaid = booking_is_fully_paid($b);

require __DIR__ . '/includes/admin-header.php';

?>

<style>
.booking-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
}

.booking-card {
    padding: 1.3rem;
}

.booking-card h3 {
    margin-top: 0;
    margin-bottom: 1rem;
}

.booking-card p {
    margin: .55rem 0;
}

.booking-actions {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
}

.booking-actions form {
    display: inline;
}

.booking-documents {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}

.booking-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.booking-form-grid label {
    display: block;
    margin-bottom: .4rem;
}

.booking-form-grid input,
.booking-form-grid select,
.booking-form-grid textarea {
    width: 100%;
}

.booking-full {
    grid-column: 1 / -1;
}

@media(max-width:700px) {
    .booking-grid,
    .booking-documents,
    .booking-form-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- HEADER -->

<div style="
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:1rem;
">

    <div>
        <h1>
            <?= e($b['booking_number']) ?>

            <span class="status-pill <?= e($b['booking_status']) ?>">
                <?= e($b['booking_status']) ?>
            </span>
        </h1>

        <p style="color:#7a716a">
            Created: <?= e($b['created_at']) ?>
        </p>
    </div>

    <div class="booking-actions">

        <?php if ($fullyPaid && !empty($b['invoice_path'])): ?>

            <a
                class="btn btn-ghost"
                target="_blank"
                rel="noopener"
                href="<?= e(SITE_URL) ?>/booking/download-invoice.php?ref=<?= urlencode($b['booking_number']) ?>"
            >
                Invoice
            </a>

        <?php elseif ($fullyPaid): ?>

            <form method="post">
                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="action"
                    value="regenerate_invoice"
                >

                <button class="btn btn-ghost">
                    Generate Invoice
                </button>
            </form>

        <?php else: ?>

            <button class="btn btn-ghost" disabled>
                Invoice after full payment
            </button>

        <?php endif; ?>


        <?php if ($fullyPaid && !empty($b['receipt_path'])): ?>

            <a
                class="btn btn-primary"
                target="_blank"
                rel="noopener"
                href="<?= e(SITE_URL) ?>/booking/download-receipt.php?ref=<?= urlencode($b['booking_number']) ?>"
            >
                Receipt
            </a>

        <?php elseif ($fullyPaid): ?>

            <form method="post">
                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="action"
                    value="issue_receipt"
                >

                <button class="btn btn-primary">
                    Issue Receipt
                </button>
            </form>

        <?php else: ?>

            <button class="btn btn-ghost" disabled>
                Receipt after full payment
            </button>

        <?php endif; ?>


        <a
            class="btn btn-ghost"
            href="<?= e($returnTo . $returnQuery) ?>"
        >
            Back
        </a>

        <button class="btn btn-ghost" onclick="window.print()">
            Print
        </button>

    </div>
</div>


<!-- FLASH MESSAGES -->

<?php if ($m = flash('ok')): ?>
    <div class="alert alert-success">
        <?= e($m) ?>
    </div>
<?php endif; ?>

<?php if ($m = flash('err')): ?>
    <div class="alert alert-error">
        <?= e($m) ?>
    </div>
<?php endif; ?>


<!-- CLIENT AND BOOKING DETAILS -->

<div class="booking-grid">

    <div class="card booking-card">

        <h3>Client Information</h3>

        <p>
            <strong><?= e($b['full_name']) ?></strong>
        </p>

        <p>Phone: <?= e($b['phone']) ?></p>

        <p>WhatsApp: <?= e($b['whatsapp']) ?></p>

        <p>Email: <?= e($b['email']) ?></p>

        <?php if (!empty($b['company'])): ?>
            <p>Company: <?= e($b['company']) ?></p>
        <?php endif; ?>

        <p>Location: <?= e($b['location']) ?></p>

    </div>


    <div class="card booking-card">

        <h3>Booking Information</h3>

        <p>Service: <?= e($b['service_name']) ?></p>

        <?php if (!empty($b['package_name'])): ?>
            <p>Package: <?= e($b['package_name']) ?></p>
        <?php endif; ?>

        <p>Date: <?= e($b['booking_date']) ?></p>

        <p>
            Time:
            <?= e(substr($b['start_time'], 0, 5)) ?>
            –
            <?= e(substr($b['end_time'], 0, 5)) ?>
        </p>

        <p>Location: <?= e($b['location']) ?></p>

        <p>People: <?= (int)$b['number_of_people'] ?></p>

        <?php if (!empty($b['customer_notes'])): ?>
            <p>
                Notes:
                <?= nl2br(e($b['customer_notes'])) ?>
            </p>
        <?php endif; ?>

    </div>

</div>


<!-- DOCUMENTS AND PAYMENT SUMMARY -->

<div class="card" style="padding:1.3rem;margin-top:1.5rem">

    <h3>Documents & Payment</h3>

    <div class="booking-documents">

        <div>
            <small>Invoice Number</small>

            <p>
                <?php if ($fullyPaid && !empty($b['invoice_number'])): ?>
                    <?= e($b['invoice_number']) ?>
                <?php else: ?>
                    Not yet available
                <?php endif; ?>
            </p>

            <?php if ($fullyPaid && !empty($b['invoice_issued_at'])): ?>
                <small><?= e($b['invoice_issued_at']) ?></small>
            <?php endif; ?>
        </div>


        <div>
            <small>Receipt Number</small>

            <p>
                <?php if ($fullyPaid && !empty($b['receipt_number'])): ?>
                    <?= e($b['receipt_number']) ?>
                <?php else: ?>
                    Not yet available
                <?php endif; ?>
            </p>

            <?php if ($fullyPaid && !empty($b['receipt_issued_at'])): ?>
                <small><?= e($b['receipt_issued_at']) ?></small>
            <?php endif; ?>
        </div>


        <div>
            <small>Payment Status</small>

            <p>
                <span class="status-pill">
                    <?= e(ucfirst($b['payment_status'])) ?>
                </span>
            </p>
        </div>


        <div>
            <small>Balance</small>

            <h3><?= e(rwf($b['balance'])) ?></h3>
        </div>

    </div>

</div>


<!-- MANAGE BOOKING -->

<form
    method="post"
    class="card"
    style="padding:1.4rem;margin-top:1.5rem"
>

    <?= csrf_field() ?>

    <h3>Manage Booking & Payment</h3>

    <div class="booking-form-grid">

        <div>

            <label>Booking Status</label>

            <select name="booking_status">

                <?php foreach (
                    ['pending','confirmed','completed','cancelled']
                    as $s
                ): ?>

                    <option
                        value="<?= e($s) ?>"
                        <?= $b['booking_status'] === $s ? 'selected' : '' ?>
                    >
                        <?= e(ucfirst($s)) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div>

            <label>Payment Method</label>

            <select name="payment_method">

                <?php foreach (
                    ['cash','mobile_money','bank_transfer','other']
                    as $s
                ): ?>

                    <option
                        value="<?= e($s) ?>"
                        <?= $b['payment_method'] === $s ? 'selected' : '' ?>
                    >
                        <?= e(ucwords(str_replace('_', ' ', $s))) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div>

            <label>Amount Paid (RWF)</label>

            <input
                type="number"
                name="amount_paid"
                min="0"
                max="<?= (float)$b['total_amount'] ?>"
                step="1"
                value="<?= (float)$b['amount_paid'] ?>"
                required
            >

        </div>


        <div>

            <label>Payment Status</label>

            <select name="payment_status">

                <?php foreach (
                    ['unpaid','partial','paid']
                    as $s
                ): ?>

                    <option
                        value="<?= e($s) ?>"
                        <?= $b['payment_status'] === $s ? 'selected' : '' ?>
                    >
                        <?= e(ucfirst($s)) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="booking-full">

            <label>Admin Notes</label>

            <textarea name="admin_notes" rows="4"><?= e($b['admin_notes']) ?></textarea>

        </div>

    </div>

    <button
        class="btn btn-primary"
        style="margin-top:1rem"
    >
        Save Changes
    </button>

</form>


<script>
(function () {

    const statusSelect = document.querySelector(
        'select[name="payment_status"]'
    );

    const amountInput = document.querySelector(
        'input[name="amount_paid"]'
    );

    const total = <?= (float)$b['total_amount'] ?>;

    if (!statusSelect || !amountInput) {
        return;
    }

    statusSelect.addEventListener('change', function () {

        if (this.value === 'paid') {
            amountInput.value = total;
        }

        if (this.value === 'unpaid') {
            amountInput.value = 0;
        }

    });

})();
</script>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>