<?php

require_once __DIR__ . '/../includes/functions.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);

// Where the admin came from. Calendar keeps its month/year when returning.
$returnTo = ($_GET['return'] ?? '') === 'calendar' ? 'calendar.php' : 'bookings.php';
$returnQuery = '';
if ($returnTo === 'calendar') {
    $returnY = (int)($_GET['return_y'] ?? date('Y'));
    $returnM = (int)($_GET['return_m'] ?? date('n'));
    if ($returnY >= 2000 && $returnY <= 2100 && $returnM >= 1 && $returnM <= 12) {
        $returnQuery = '?y=' . $returnY . '&m=' . $returnM;
    }
}

$returnSelf = 'booking-view.php?id=' . $id;
if ($returnTo === 'calendar' && $returnQuery !== '') {
    $returnSelf .= '&return=calendar&return_y=' . $returnY . '&return_m=' . $returnM;
}

if (!$id) {
    redirect('bookings.php');
}

$pdo = db();

function load_invoice_generator(): void
{
    $invoiceFile = __DIR__ . '/../booking/generate-invoice.php';

    if (!is_file($invoiceFile)) {
        throw new RuntimeException(
            'Invoice generator file not found: ' . $invoiceFile
        );
    }

    require_once $invoiceFile;

    if (!function_exists('generate_invoice')) {
        throw new RuntimeException(
            'generate_invoice() is not defined in: ' . $invoiceFile
        );
    }
}

$stmt = $pdo->prepare(
    "SELECT b.*, c.full_name, c.phone, c.whatsapp, c.email, c.location, c.company,
            s.name AS service_name, p.name AS package_name
     FROM bookings b
     JOIN customers c ON c.id=b.customer_id
     JOIN services s ON s.id=b.service_id
     LEFT JOIN packages p ON p.id=b.package_id
     WHERE b.id=? LIMIT 1"
);

$stmt->execute([$id]);
$b = $stmt->fetch();

if (!$b) {
    redirect('bookings.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {

    $action = $_POST['action'] ?? '';

    if ($action === 'issue_receipt') {

        if ($b['payment_status'] !== 'paid') {

            flash(
                'err',
                'Cannot issue a receipt until the booking is marked as PAID.'
            );

            redirect($returnSelf);
        }

        require_once __DIR__ . '/../booking/generate-receipt.php';

        try {

            generate_receipt($id);

            flash(
                'ok',
                'Receipt issued successfully.'
            );

        } catch (Throwable $e) {

            flash(
                'err',
                'Receipt generation failed: ' . $e->getMessage()
            );
        }

        redirect($returnSelf);
    }

    if ($action === 'regenerate_invoice') {

        try {

            load_invoice_generator();

            generate_invoice($id);

            flash(
                'ok',
                'Invoice regenerated successfully.'
            );

        } catch (Throwable $e) {

            flash(
                'err',
                'Invoice generation failed: ' . $e->getMessage()
            );
        }

        redirect($returnSelf);
    }

    $status = $_POST['booking_status'] ?? $b['booking_status'];

    $pstatus = $_POST['payment_status'] ?? $b['payment_status'];

    $pmethod = $_POST['payment_method'] ?? $b['payment_method'];

    $amountPaid = (float)(
        $_POST['amount_paid'] ?? $b['amount_paid']
    );

    $adminNotes = trim(
        $_POST['admin_notes'] ?? ''
    );

    if (
        !in_array(
            $status,
            ['pending', 'confirmed', 'completed', 'cancelled'],
            true
        )
    ) {
        $status = $b['booking_status'];
    }

    if (
        !in_array(
            $pstatus,
            ['unpaid', 'partial', 'paid'],
            true
        )
    ) {
        $pstatus = $b['payment_status'];
    }

    if (
        !in_array(
            $pmethod,
            ['cash', 'mobile_money', 'bank_transfer', 'other'],
            true
        )
    ) {
        $pmethod = $b['payment_method'];
    }

    $total = (float)$b['total_amount'];

    if ($pstatus === 'paid') {

        $amountPaid = $total;

    } elseif ($pstatus === 'unpaid') {

        $amountPaid = 0;
    }

    $balance = max(
        0,
        $total - $amountPaid
    );

    if ($amountPaid <= 0) {

        $pstatus = 'unpaid';

    } elseif ($amountPaid >= $total) {

        $pstatus = 'paid';

    } else {

        $pstatus = 'partial';
    }

    $wasConfirmed = (
        $b['booking_status'] === 'confirmed'
    );

    $isConfirmedNow = (
        $status === 'confirmed'
    );

    $shouldGenerateInvoice = (
        $isConfirmedNow &&
        empty($b['invoice_path'])
    );

    $updateStmt = $pdo->prepare(
        "UPDATE bookings
         SET
            booking_status=?,
            payment_status=?,
            payment_method=?,
            amount_paid=?,
            balance=?,
            admin_notes=?
         WHERE id=?"
    );

    $updateStmt->execute([
        $status,
        $pstatus,
        $pmethod,
        $amountPaid,
        $balance,
        $adminNotes,
        $id
    ]);

    $delta = (
        $amountPaid -
        (float)$b['amount_paid']
    );

    if ($delta > 0) {

        $paymentStmt = $pdo->prepare(
            "INSERT INTO payments
                (
                    booking_id,
                    amount,
                    payment_method,
                    payment_date,
                    notes
                )
             VALUES
                (?,?,?,?,?)"
        );

        $paymentStmt->execute([
            $id,
            $delta,
            $pmethod,
            date('Y-m-d'),
            'Admin update'
        ]);
    }

    if ($shouldGenerateInvoice) {

        try {

            load_invoice_generator();

            generate_invoice($id);

            flash(
                'ok',
                'Booking confirmed and invoice generated successfully.'
            );

        } catch (Throwable $e) {

            flash(
                'err',
                'Booking confirmed, but invoice generation failed: '
                . $e->getMessage()
            );
        }

    } else {

        flash(
            'ok',
            'Booking updated successfully.'
        );
    }

    redirect($returnSelf);
}

require __DIR__ . '/includes/admin-header.php';

?>

<div style="
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:1rem
">

    <div>

        <h1>
            <?= e($b['booking_number']) ?>

            <span class="status-pill <?= e($b['booking_status']) ?>">
                <?= e($b['booking_status']) ?>
            </span>
        </h1>

        <p style="color:#7a716a">
            Created <?= e($b['created_at']) ?>
        </p>

    </div>

    <div style="
        display:flex;
        gap:.6rem;
        flex-wrap:wrap
    ">

        <?php if ($b['invoice_path']): ?>

            <a
                class="btn btn-ghost"
                target="_blank"
                href="<?= e(SITE_URL) ?>/booking/download-invoice.php?ref=<?= urlencode($b['booking_number']) ?>"
            >
                📄 Invoice
            </a>

        <?php else: ?>

            <form method="post" style="display:inline">

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

        <?php endif; ?>

        <?php if ($b['receipt_path']): ?>

            <a
                class="btn btn-primary"
                target="_blank"
                href="<?= e(SITE_URL) ?>/booking/download-receipt.php?ref=<?= urlencode($b['booking_number']) ?>"
            >
                📄 Receipt
            </a>

        <?php elseif ($b['payment_status'] === 'paid'): ?>

            <form method="post" style="display:inline">

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

            <button
                class="btn btn-ghost"
                disabled
                title="Mark as paid first"
                style="opacity:.5;cursor:not-allowed"
            >
                Issue Receipt
            </button>

        <?php endif; ?>

        <a
            class="btn btn-ghost"
            href="<?= e($returnTo . $returnQuery) ?>"
        >
            ← Back
        </a>

        <button
            class="btn btn-ghost"
            onclick="window.print()"
        >
            Print
        </button>

    </div>

</div>

<?php if ($ok = flash('ok')): ?>

    <div class="alert alert-success">
        <?= e($ok) ?>
    </div>

<?php endif; ?>

<?php if ($er = flash('err')): ?>

    <div class="alert alert-error">
        <?= e($er) ?>
    </div>

<?php endif; ?>

<div
    class="grid grid-4"
    style="margin-top:1.6rem"
>

    <div
        class="card"
        style="
            padding:1.2rem;
            grid-column:span 2
        "
    >

        <h3
            style="
                font-family:var(--font-serif);
                font-size:1.1rem;
                margin:0 0 .8rem
            "
        >
            Client
        </h3>

        <p>
            <strong>
                <?= e($b['full_name']) ?>
            </strong>
        </p>

        <p>
            Phone:
            <?= e($b['phone']) ?>
        </p>

        <p>
            WhatsApp:
            <?= e($b['whatsapp']) ?>
        </p>

        <p>
            Email:
            <?= e($b['email']) ?>
        </p>

        <?php if ($b['company']): ?>

            <p>
                Company:
                <?= e($b['company']) ?>
            </p>

        <?php endif; ?>

        <p>
            Location:
            <?= e($b['location']) ?>
        </p>

    </div>

    <div
        class="card"
        style="
            padding:1.2rem;
            grid-column:span 2
        "
    >

        <h3
            style="
                font-family:var(--font-serif);
                font-size:1.1rem;
                margin:0 0 .8rem
            "
        >
            Booking
        </h3>

        <p>
            Service:
            <?= e($b['service_name']) ?>
        </p>

        <?php if ($b['package_name']): ?>

            <p>
                Package:
                <?= e($b['package_name']) ?>
            </p>

        <?php endif; ?>

        <p>
            Date:
            <?= e($b['booking_date']) ?>
        </p>

        <p>
            Time:
            <?= e($b['start_time']) ?>
            –
            <?= e($b['end_time']) ?>
        </p>

        <p>
            Location:
            <?= e($b['location']) ?>
        </p>

        <p>
            People:
            <?= (int)$b['number_of_people'] ?>
        </p>

        <?php if ($b['customer_notes']): ?>

            <p style="margin-top:.6rem">
                Notes:
                <?= nl2br(e($b['customer_notes'])) ?>
            </p>

        <?php endif; ?>

    </div>

</div>

<div
    class="card"
    style="
        padding:1.2rem;
        margin-top:1.6rem
    "
>

    <h3
        style="
            font-family:var(--font-serif);
            font-size:1.1rem;
            margin:0 0 .8rem
        "
    >
        Documents
    </h3>

    <div class="grid grid-4">

        <div>

            <div
                style="
                    font-size:.72rem;
                    letter-spacing:.2em;
                    text-transform:uppercase;
                    color:#7a716a
                "
            >
                Invoice
            </div>

            <div style="margin-top:.3rem">

                <?= $b['invoice_number']
                    ? e($b['invoice_number'])
                    : '<em style="color:#7a716a">Not generated</em>'
                ?>

            </div>

            <?php if ($b['invoice_issued_at']): ?>

                <div
                    style="
                        font-size:.78rem;
                        color:#7a716a
                    "
                >
                    <?= e($b['invoice_issued_at']) ?>
                </div>

            <?php endif; ?>

        </div>

        <div>

            <div
                style="
                    font-size:.72rem;
                    letter-spacing:.2em;
                    text-transform:uppercase;
                    color:#7a716a
                "
            >
                Receipt
            </div>

            <div style="margin-top:.3rem">

                <?= $b['receipt_number']
                    ? e($b['receipt_number'])
                    : '<em style="color:#7a716a">Not issued</em>'
                ?>

            </div>

            <?php if ($b['receipt_issued_at']): ?>

                <div
                    style="
                        font-size:.78rem;
                        color:#7a716a
                    "
                >
                    <?= e($b['receipt_issued_at']) ?>
                </div>

            <?php endif; ?>

        </div>

        <div>

            <div
                style="
                    font-size:.72rem;
                    letter-spacing:.2em;
                    text-transform:uppercase;
                    color:#7a716a
                "
            >
                Payment Status
            </div>

            <div style="margin-top:.3rem">

                <span
                    class="status-pill
                    <?= $b['payment_status'] === 'paid'
                        ? 'confirmed'
                        : (
                            $b['payment_status'] === 'partial'
                            ? ''
                            : 'cancelled'
                        )
                    ?>"
                >
                    <?= e(
                        ucfirst(
                            $b['payment_status']
                        )
                    ) ?>
                </span>

            </div>

        </div>

        <div>

            <div
                style="
                    font-size:.72rem;
                    letter-spacing:.2em;
                    text-transform:uppercase;
                    color:#7a716a
                "
            >
                Balance
            </div>

            <div
                style="
                    margin-top:.3rem;
                    font-family:var(--font-serif);
                    font-size:1.1rem
                "
            >

                <?= e(
                    rwf($b['balance'])
                ) ?>

            </div>

        </div>

    </div>

</div>

<form
    method="post"
    class="card"
    style="
        padding:1.4rem;
        margin-top:1.6rem
    "
>

    <?= csrf_field() ?>

    <h3
        style="
            font-family:var(--font-serif);
            font-size:1.1rem;
            margin:0 0 1rem
        "
    >
        Manage
    </h3>

    <div class="form-grid">

        <div>

            <label>
                Booking Status
            </label>

            <select name="booking_status">

                <?php foreach (
                    ['pending','confirmed','completed','cancelled']
                    as $s
                ): ?>

                    <option
                        value="<?= $s ?>"
                        <?= $b['booking_status'] === $s
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= ucfirst($s) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div>

            <label>
                Payment Method
            </label>

            <select name="payment_method">

                <?php foreach (
                    [
                        'cash',
                        'mobile_money',
                        'bank_transfer',
                        'other'
                    ] as $s
                ): ?>

                    <option
                        value="<?= $s ?>"
                        <?= $b['payment_method'] === $s
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= ucwords(
                            str_replace(
                                '_',
                                ' ',
                                $s
                            )
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div>

            <label>
                Amount Paid (RWF)
            </label>

            <input
                type="number"
                name="amount_paid"
                min="0"
                step="100"
                value="<?= (float)$b['amount_paid'] ?>"
            >

        </div>

        <div>

            <label>
                Payment Status
            </label>

            <select name="payment_status">

                <?php foreach (
                    ['unpaid','partial','paid']
                    as $s
                ): ?>

                    <option
                        value="<?= $s ?>"
                        <?= $b['payment_status'] === $s
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= ucfirst($s) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div class="full">

            <label>
                Admin Notes
            </label>

            <textarea name="admin_notes"><?= e(
                $b['admin_notes']
            ) ?></textarea>

        </div>

    </div>

    <button
        class="btn btn-primary"
        style="margin-top:1rem"
    >
        Save changes
    </button>

</form>

<script>

(function () {

    var statusSel =
        document.querySelector(
            'select[name="payment_status"]'
        );

    var amountIn =
        document.querySelector(
            'input[name="amount_paid"]'
        );

    var total =
        <?= (float)$b['total_amount'] ?>;

    if (!statusSel || !amountIn) {
        return;
    }

    statusSel.addEventListener(
        'change',
        function () {

            if (this.value === 'paid') {

                amountIn.value = total;

            } else if (this.value === 'unpaid') {

                amountIn.value = 0;
            }

        }
    );

})();

</script>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>