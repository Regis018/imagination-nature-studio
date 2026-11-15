<?php
/**
 * Imagination Nature Studio
 * ADMIN BOOKINGS
 */

require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

/* =========================================
   LOAD INVOICE GENERATOR
========================================= */

function load_booking_invoice_generator(): void
{
    $file = __DIR__ . '/../booking/generate-invoice.php';

    if (!is_file($file)) {
        throw new RuntimeException('Invoice generator file not found.');
    }

    require_once $file;

    if (!function_exists('generate_invoice')) {
        throw new RuntimeException('generate_invoice() function not found.');
    }
}

/* =========================================
   POST ACTIONS
========================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    verify_csrf($_POST['csrf'] ?? null)
) {
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    /* DELETE BOOKING */

    if ($action === 'delete' && $id > 0) {

        if (!is_admin()) {
            flash('err', 'Only administrators can delete bookings.');
            redirect('bookings.php');
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT booking_number FROM bookings WHERE id = ? FOR UPDATE"
            );
            $stmt->execute([$id]);
            $bookingNumber = $stmt->fetchColumn();

            if (!$bookingNumber) {
                throw new RuntimeException('Booking not found.');
            }

            // Remove child records with no FK cascade before deleting the booking.
            $pdo->prepare("DELETE FROM booking_items WHERE booking_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM booking_dates WHERE booking_id = ?")->execute([$id]);
            // payments cascades automatically via FK ON DELETE CASCADE.

            $pdo->prepare("DELETE FROM bookings WHERE id = ?")->execute([$id]);

            $pdo->commit();

            flash('ok', 'Booking ' . $bookingNumber . ' deleted.');

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('[BOOKING DELETE] ' . $e->getMessage());
            flash('err', 'Delete failed: ' . $e->getMessage());
        }

        redirect('bookings.php');
    }

    /* BOOKING STATUS */

    $allowed = [
        'confirm',
        'reject',
        'cancel',
        'complete',
        'pending'
    ];

    if ($id > 0 && in_array($action, $allowed, true)) {

        $map = [
            'confirm'  => 'confirmed',
            'reject'   => 'cancelled',
            'cancel'   => 'cancelled',
            'complete' => 'completed',
            'pending'  => 'pending'
        ];

        try {

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    booking_number,
                    total_amount,
                    invoice_path,
                    booking_status
                FROM bookings
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$id]);

            $booking = $stmt->fetch();

            if (!$booking) {
                flash('err', 'Booking not found.');
                redirect('bookings.php');
            }

            $newStatus = $map[$action];

            $update = $pdo->prepare("
                UPDATE bookings
                SET booking_status = ?
                WHERE id = ?
            ");

            $update->execute([$newStatus, $id]);

            /*
             * AUTOMATIC INVOICE
             *
             * Generate invoice immediately when booking
             * is confirmed, even if partially paid or unpaid.
             *
             * Studio bookings with zero total are skipped.
             */

            if (
                $newStatus === 'confirmed' &&
                (float)$booking['total_amount'] > 0 &&
                empty($booking['invoice_path'])
            ) {

                try {

                    load_booking_invoice_generator();

                    generate_invoice($id);

                    flash(
                        'ok',
                        'Booking confirmed and invoice generated successfully.'
                    );

                } catch (Throwable $invoiceError) {

                    error_log(
                        '[AUTO INVOICE] ' .
                        $invoiceError->getMessage()
                    );

                    flash(
                        'err',
                        'Booking confirmed, but invoice generation failed: ' .
                        $invoiceError->getMessage()
                    );
                }

            } else {

                flash('ok', 'Booking status updated successfully.');
            }

        } catch (Throwable $e) {

            error_log('[BOOKING STATUS] ' . $e->getMessage());

            flash('err', 'Could not update booking status.');
        }

        redirect('bookings.php');
    }

    redirect('bookings.php');
}

/* =========================================
   FILTERS
========================================= */

$status   = $_GET['status'] ?? '';
$search   = trim($_GET['q'] ?? '');
$svc      = (int)($_GET['service'] ?? 0);
$dateFrom = $_GET['from'] ?? '';
$dateTo   = $_GET['to'] ?? '';

/* =========================================
   BOOKINGS QUERY
========================================= */

$sql = "
    SELECT
        b.*,
        c.full_name,
        c.phone,
        c.email,
        s.name AS service_name
    FROM bookings b
    JOIN customers c ON c.id = b.customer_id
    JOIN services s ON s.id = b.service_id
    WHERE 1=1
";

$args = [];

if (
    in_array(
        $status,
        ['pending', 'confirmed', 'completed', 'cancelled'],
        true
    )
) {
    $sql .= " AND b.booking_status = ?";
    $args[] = $status;
}

if ($svc > 0) {
    $sql .= " AND b.service_id = ?";
    $args[] = $svc;
}

if ($search !== '') {

    $sql .= "
        AND (
            b.booking_number LIKE ?
            OR c.full_name LIKE ?
            OR c.email LIKE ?
            OR c.phone LIKE ?
        )
    ";

    $like = '%' . $search . '%';

    array_push($args, $like, $like, $like, $like);
}

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $sql .= " AND b.booking_date >= ?";
    $args[] = $dateFrom;
}

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $sql .= " AND b.booking_date <= ?";
    $args[] = $dateTo;
}

$sql .= " ORDER BY b.id DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($args);

$rows = $stmt->fetchAll();

$services = $pdo->query("
    SELECT id, name
    FROM services
    ORDER BY name
")->fetchAll();

/* =========================================
   STATISTICS
========================================= */

$stats = [
    'pending' => (int)$pdo->query("
        SELECT COUNT(*) FROM bookings
        WHERE booking_status = 'pending'
    ")->fetchColumn(),

    'confirmed' => (int)$pdo->query("
        SELECT COUNT(*) FROM bookings
        WHERE booking_status = 'confirmed'
    ")->fetchColumn(),

    'completed' => (int)$pdo->query("
        SELECT COUNT(*) FROM bookings
        WHERE booking_status = 'completed'
    ")->fetchColumn(),

    'cancelled' => (int)$pdo->query("
        SELECT COUNT(*) FROM bookings
        WHERE booking_status = 'cancelled'
    ")->fetchColumn()
];

require __DIR__ . '/includes/admin-header.php';
?>

<style>
.bk-stats {
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:.8rem;
    margin-bottom:1.4rem;
}

.bk-stats .s-card {
    background:#171513;
    border:1px solid #262220;
    border-radius:12px;
    padding:1rem 1.2rem;
    text-decoration:none;
    color:inherit;
}

.bk-stats .lbl {
    font-size:.72rem;
    letter-spacing:.16em;
    text-transform:uppercase;
    color:#7a716a;
}

.bk-stats .num {
    font-size:1.6rem;
    margin-top:.3rem;
}

.bk-stats .pending {
    border-color:rgba(224,122,47,.4);
}

.bk-stats .confirmed {
    border-color:rgba(52,168,83,.4);
}

.bk-stats .completed {
    border-color:rgba(80,140,220,.4);
}

.bk-stats .cancelled {
    border-color:rgba(200,60,60,.4);
}

.filter-row {
    display:flex;
    gap:.6rem;
    flex-wrap:wrap;
    align-items:center;
}

.filter-row input,
.filter-row select {
    padding:.55rem .8rem;
    border-radius:8px;
    background:#0e0d0c;
    color:#f3ece3;
    border:1px solid #262220;
    font:inherit;
    font-size:.85rem;
}

.filter-row input[type="text"] {
    min-width:200px;
}

.bk-table {
    width:100%;
    border-collapse:collapse;
}

.bk-table th {
    text-align:left;
    font-size:.72rem;
    letter-spacing:.12em;
    text-transform:uppercase;
    color:#7a716a;
    padding:.9rem 1rem;
    white-space:nowrap;
}

.bk-table td {
    padding:.9rem 1rem;
    border-top:1px solid #262220;
    vertical-align:top;
}

.bk-table tr:hover {
    background:#14120f;
}

.bk-table .actions {
    white-space:nowrap;
}

.btn-danger {
    border:1px solid #7a2a2a;
    color:#f0b3b3;
    background:transparent;
}

.btn-danger:hover {
    background:rgba(200,60,60,.12);
    border-color:#a33;
    color:#fff;
}

.invoice-label {
    display:inline-block;
    margin-top:5px;
    font-size:.72rem;
    color:#72c58a;
}

@media(max-width:800px) {
    .bk-stats {
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:480px) {
    .bk-stats {
        grid-template-columns:1fr;
    }
}
</style>

<!-- HEADER -->

<div style="
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    flex-wrap:wrap;
    gap:1rem;
    margin-bottom:1rem
">

    <div>
        <h1 style="margin:0 0 .3rem">Bookings</h1>

        <p style="color:#7a716a;margin:0">
            Manage and confirm customer bookings.
        </p>
    </div>

    <div style="display:flex;gap:.6rem;flex-wrap:wrap">

        <a
            class="btn btn-sm btn-ghost"
            href="bookings-export.php?<?= e(http_build_query($_GET)) ?>"
        >
            ⬇ Export CSV
        </a>

        <a
            class="btn btn-sm btn-primary"
            href="bookings.php"
        >
            ↻ Reset filters
        </a>

    </div>
</div>

<!-- FLASH -->

<?php if ($m = flash('ok')): ?>
    <div class="alert alert-success"><?= e($m) ?></div>
<?php endif; ?>

<?php if ($m = flash('err')): ?>
    <div class="alert alert-error"><?= e($m) ?></div>
<?php endif; ?>

<!-- STATISTICS -->

<div class="bk-stats">

    <a class="s-card pending" href="bookings.php?status=pending">
        <div class="lbl">Pending</div>
        <div class="num"><?= $stats['pending'] ?></div>
    </a>

    <a class="s-card confirmed" href="bookings.php?status=confirmed">
        <div class="lbl">Confirmed</div>
        <div class="num"><?= $stats['confirmed'] ?></div>
    </a>

    <a class="s-card completed" href="bookings.php?status=completed">
        <div class="lbl">Completed</div>
        <div class="num"><?= $stats['completed'] ?></div>
    </a>

    <a class="s-card cancelled" href="bookings.php?status=cancelled">
        <div class="lbl">Cancelled</div>
        <div class="num"><?= $stats['cancelled'] ?></div>
    </a>

</div>

<!-- FILTERS -->

<form
    method="get"
    class="card"
    style="padding:1rem 1.2rem;margin-bottom:1.4rem"
>

    <div class="filter-row">

        <input
            type="text"
            name="q"
            placeholder="Search booking # / name / email / phone"
            value="<?= e($search) ?>"
        >

        <select name="status">
            <option value="">All statuses</option>

            <?php foreach (
                ['pending','confirmed','completed','cancelled']
                as $s
            ): ?>

                <option
                    value="<?= e($s) ?>"
                    <?= $status === $s ? 'selected' : '' ?>
                >
                    <?= e(ucfirst($s)) ?>
                </option>

            <?php endforeach; ?>
        </select>

        <select name="service">
            <option value="">All services</option>

            <?php foreach ($services as $s): ?>

                <option
                    value="<?= (int)$s['id'] ?>"
                    <?= $svc === (int)$s['id'] ? 'selected' : '' ?>
                >
                    <?= e($s['name']) ?>
                </option>

            <?php endforeach; ?>
        </select>

        <input
            type="date"
            name="from"
            value="<?= e($dateFrom) ?>"
            title="From date"
        >

        <input
            type="date"
            name="to"
            value="<?= e($dateTo) ?>"
            title="To date"
        >

        <button class="btn btn-sm btn-primary">
            Filter
        </button>

        <a class="btn btn-sm btn-ghost" href="bookings.php">
            Clear
        </a>

        <span style="
            color:#7a716a;
            font-size:.82rem;
            margin-left:auto
        ">
            <?= count($rows) ?> result<?= count($rows) === 1 ? '' : 's' ?>
        </span>

    </div>
</form>

<!-- BOOKINGS TABLE -->

<div class="card" style="padding:0">

    <div class="admin-table-wrap" style="overflow-x:auto">

        <table class="bk-table">

            <thead>
                <tr>
                    <th>Booking</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($rows as $b): ?>

                <tr>

                    <td>
                        <strong><?= e($b['booking_number']) ?></strong>

                        <br>

                        <span style="color:#7a716a;font-size:.75rem">
                            <?= e(date('d M Y', strtotime($b['created_at']))) ?>
                        </span>

                        <?php if (!empty($b['invoice_path'])): ?>
                            <br>
                            <span class="invoice-label">
                                Invoice generated
                            </span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?= e($b['full_name']) ?>

                        <br>

                        <span style="color:#7a716a;font-size:.78rem">
                            <?= e($b['phone']) ?>
                        </span>
                    </td>

                    <td><?= e($b['service_name']) ?></td>

                    <td>
                        <?= e(date('d M Y', strtotime($b['booking_date']))) ?>

                        <br>

                        <span style="color:#7a716a;font-size:.78rem">
                            <?= e(substr($b['start_time'], 0, 5)) ?>
                            –
                            <?= e(substr($b['end_time'], 0, 5)) ?>
                        </span>
                    </td>

                    <td><?= e(rwf($b['total_amount'])) ?></td>

                    <td><?= e(rwf($b['amount_paid'])) ?></td>

                    <td><?= e(rwf($b['balance'])) ?></td>

                    <td>
                        <span class="status-pill <?= e($b['booking_status']) ?>">
                            <?= e($b['booking_status']) ?>
                        </span>
                    </td>

                    <td class="actions">

                        <a
                            class="btn btn-sm btn-ghost"
                            href="booking-view.php?id=<?= (int)$b['id'] ?>"
                        >
                            Open
                        </a>

                        <?php if ($b['booking_status'] === 'pending'): ?>

                            <form method="post" style="display:inline">

                                <?= csrf_field() ?>

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int)$b['id'] ?>"
                                >

                                <button
                                    class="btn btn-sm btn-primary"
                                    name="action"
                                    value="confirm"
                                    onclick="return confirm('Confirm this booking and generate its invoice?')"
                                >
                                    Confirm
                                </button>

                                <button
                                    class="btn btn-sm btn-ghost"
                                    name="action"
                                    value="reject"
                                    onclick="return confirm('Reject this booking?')"
                                >
                                    Reject
                                </button>

                            </form>

                        <?php elseif ($b['booking_status'] === 'confirmed'): ?>

                            <form method="post" style="display:inline">

                                <?= csrf_field() ?>

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int)$b['id'] ?>"
                                >

                                <button
                                    class="btn btn-sm btn-light"
                                    name="action"
                                    value="complete"
                                    onclick="return confirm('Mark as completed?')"
                                >
                                    Complete
                                </button>

                                <button
                                    class="btn btn-sm btn-ghost"
                                    name="action"
                                    value="cancel"
                                    onclick="return confirm('Cancel this booking?')"
                                >
                                    Cancel
                                </button>

                            </form>

                        <?php elseif (
                            $b['booking_status'] === 'cancelled' ||
                            $b['booking_status'] === 'completed'
                        ): ?>

                            <form method="post" style="display:inline">

                                <?= csrf_field() ?>

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int)$b['id'] ?>"
                                >

                                <button
                                    class="btn btn-sm btn-ghost"
                                    name="action"
                                    value="pending"
                                    onclick="return confirm('Reset to pending?')"
                                >
                                    Reset
                                </button>

                            </form>

                        <?php endif; ?>

                        <?php if (is_admin()): ?>

                            <form
                                method="post"
                                style="display:inline"
                                onsubmit="return confirm('Delete booking <?= e($b['booking_number']) ?> permanently? This cannot be undone.')"
                            >

                                <?= csrf_field() ?>

                                <input type="hidden" name="action" value="delete">

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int)$b['id'] ?>"
                                >

                                <button class="btn btn-sm btn-danger">
                                    Delete
                                </button>

                            </form>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>

            <?php if (!$rows): ?>

                <tr>
                    <td
                        colspan="9"
                        style="
                            padding:3rem 1rem;
                            text-align:center;
                            color:#7a716a
                        "
                    >
                        No bookings match your filters.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>
        </table>

    </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
