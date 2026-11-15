<?php
declare(strict_types=1);

/**
 * Admin — Walk-in Bookings
 *
 * Uses the existing walkin_bookings table:
 * id, customer_name, customer_contact, description, booking_date, event_date,
 * status, total_amount, advance, balance, notes, created_at.
 *
 * This page intentionally does NOT redirect to itself.
 * Editing/details are handled by walkin-booking-view.php.
 */

require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

/* ---------------------------------------------------------
   Helpers
   --------------------------------------------------------- */
function wb_money(float $amount): string
{
    return number_format($amount, 0, '.', ',') . ' RWF';
}

function wb_h(mixed $value): string
{
    return e((string)$value);
}

function wb_status_class(string $status): string
{
    return match (strtolower($status)) {
        'confirmed' => 'confirmed',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
        default     => 'pending',
    };
}

/* ---------------------------------------------------------
   CSRF
   --------------------------------------------------------- */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['walkin_bookings_csrf'])) {
    $_SESSION['walkin_bookings_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['walkin_bookings_csrf'];

/* ---------------------------------------------------------
   Filters
   --------------------------------------------------------- */
$q      = trim((string)($_GET['q'] ?? ''));
$status = strtolower(trim((string)($_GET['status'] ?? 'all')));
$from   = trim((string)($_GET['from'] ?? ''));
$to     = trim((string)($_GET['to'] ?? ''));

$allowedStatuses = ['all', 'pending', 'confirmed', 'completed', 'cancelled'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'all';
}

/* ---------------------------------------------------------
   POST — create a new walk-in booking
   --------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string)($_POST['csrf'] ?? '');

    if (!hash_equals($csrf, $postedCsrf)) {
        flash('err', 'Security check failed. Please try again.');
        redirect('walkin-bookings.php');
    }

    $action = strtolower(trim((string)($_POST['action'] ?? '')));

    if ($action === 'create') {
        $customerName = trim((string)($_POST['customer_name'] ?? ''));
        $customerContact = trim((string)($_POST['customer_contact'] ?? ''));
        $description  = trim((string)($_POST['description'] ?? ''));
        $notes        = trim((string)($_POST['notes'] ?? ''));
        $bookingDate  = trim((string)($_POST['booking_date'] ?? date('Y-m-d')));
        $eventDate    = trim((string)($_POST['event_date'] ?? ''));
        $bookingStatus = strtolower(trim((string)($_POST['status'] ?? 'pending')));
        $total        = max(0, (float)($_POST['total_amount'] ?? 0));
        $advance      = max(0, (float)($_POST['advance'] ?? 0));

        if (!in_array($bookingStatus, ['pending', 'confirmed', 'completed', 'cancelled'], true)) {
            $bookingStatus = 'pending';
        }

        if ($customerName === '') {
            flash('err', 'Customer name is required.');
            redirect('walkin-bookings.php');
        }

        if (mb_strlen($customerName) > 150) {
            flash('err', 'Customer name is too long (maximum 150 characters).');
            redirect('walkin-bookings.php');
        }

        if (mb_strlen($customerContact) > 30) {
            flash('err', 'Customer contact is too long (maximum 30 characters).');
            redirect('walkin-bookings.php');
        }

        if (mb_strlen($description) > 255) {
            flash('err', 'Description is too long (maximum 255 characters).');
            redirect('walkin-bookings.php');
        }

        if ($bookingDate === '') {
            $bookingDate = date('Y-m-d');
        }

        $advance = min($advance, $total);
        $balance = max(0, $total - $advance);

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO walkin_bookings
                    (customer_name, customer_contact, description, booking_date, event_date, status, total_amount, advance, balance, notes)
                 VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $customerName,
                $customerContact !== '' ? $customerContact : null,
                $description,
                $bookingDate,
                $eventDate !== '' ? $eventDate : null,
                $bookingStatus,
                $total,
                $advance,
                $balance,
                $notes !== '' ? $notes : null,
            ]);

            $newId = (int)$pdo->lastInsertId();

            flash('ok', 'Walk-in booking created successfully.');

            redirect(
                'walkin-booking-view.php?' . http_build_query([
                    'id' => $newId,
                    'return' => 'list',
                ])
            );
        } catch (Throwable $e) {
            // Keep the detailed error in the server log; do not expose SQL details to the browser.
            error_log(
                'WALKIN CREATE ERROR: ' . $e->getMessage() .
                ' | FILE: ' . $e->getFile() .
                ' | LINE: ' . $e->getLine()
            );
            flash('err', 'Walk-in booking could not be saved. Please check the server error log.');
            redirect('walkin-bookings.php');
        }
    }

    if ($action === 'delete') {
        if (!is_admin()) {
            flash('err', 'Only administrators can delete walk-in bookings.');
            redirect('walkin-bookings.php');
        }

        $deleteId = (int)($_POST['id'] ?? 0);

        if ($deleteId <= 0) {
            flash('err', 'Invalid walk-in booking.');
            redirect('walkin-bookings.php');
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM walkin_bookings WHERE id=?');
            $stmt->execute([$deleteId]);

            flash('ok', 'Walk-in booking deleted successfully.');
        } catch (Throwable $e) {
            error_log('Walk-in delete error: ' . $e->getMessage());
            flash('err', 'Could not delete the walk-in booking.');
        }

        redirect('walkin-bookings.php');
    }

    /* Unknown POST action */
    flash('err', 'Invalid action.');
    redirect('walkin-bookings.php');
}

/* ---------------------------------------------------------
   Build list query
   --------------------------------------------------------- */
$where  = [];
$params = [];

if ($q !== '') {
    $where[] = '(customer_name LIKE ? OR customer_contact LIKE ? OR description LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($status !== 'all') {
    $where[] = 'status = ?';
    $params[] = $status;
}

if ($from !== '') {
    $where[] = 'booking_date >= ?';
    $params[] = $from;
}

if ($to !== '') {
    $where[] = 'booking_date <= ?';
    $params[] = $to;
}

$sql = '
    SELECT
        id,
        customer_name,
        customer_contact,
        description,
        booking_date,
        event_date,
        status,
        total_amount,
        advance,
        balance
    FROM walkin_bookings
';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY booking_date DESC, id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$walkins = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------------------------------------------------------
   Stats — based on the same current filters
   --------------------------------------------------------- */
$statsSql = '
    SELECT
        COUNT(*) AS total_count,
        COALESCE(SUM(total_amount),0) AS total_value,
        COALESCE(SUM(advance),0) AS total_paid,
        COALESCE(SUM(balance),0) AS total_balance
    FROM walkin_bookings
';

if ($where) {
    $statsSql .= ' WHERE ' . implode(' AND ', $where);
}

$statsStmt = $pdo->prepare($statsSql);
$statsStmt->execute($params);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total_count' => 0,
    'total_value' => 0,
    'total_paid' => 0,
    'total_balance' => 0,
];

/* ---------------------------------------------------------
   Page
   --------------------------------------------------------- */
require __DIR__ . '/includes/admin-header.php';
?>

<style>
.wb-wrap{
    max-width:1500px;
    margin:0 auto;
}
.wb-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:1rem;
    flex-wrap:wrap;
    margin-bottom:1.2rem;
}
.wb-title{
    margin:0;
    font-family:var(--font-serif);
    font-size:1.7rem;
}
.wb-sub{
    margin:.35rem 0 0;
    color:#8c837a;
}
.wb-actions{
    display:flex;
    gap:.55rem;
    flex-wrap:wrap;
}
.wb-filters{
    display:grid;
    grid-template-columns:minmax(220px,1.4fr) repeat(4,minmax(130px,1fr)) auto;
    gap:.65rem;
    padding:1rem;
    background:#11100f;
    border:1px solid #292522;
    border-radius:14px;
    margin-bottom:1rem;
}
.wb-filters input,
.wb-filters select{
    width:100%;
    min-height:42px;
    background:#0b0a09;
    color:#eee;
    border:1px solid #332e2a;
    border-radius:8px;
    padding:.65rem .75rem;
}
.wb-stats{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:.8rem;
    margin-bottom:1rem;
}
.wb-stat{
    padding:1rem;
    border:1px solid #292522;
    background:#131110;
    border-radius:12px;
}
.wb-stat .label{
    font-size:.68rem;
    text-transform:uppercase;
    letter-spacing:.13em;
    color:#817970;
}
.wb-stat .value{
    margin-top:.35rem;
    font-size:1.15rem;
    font-weight:900;
}
.wb-card{
    background:#11100f;
    border:1px solid #292522;
    border-radius:14px;
    overflow:hidden;
}
.wb-table-wrap{
    overflow-x:auto;
}
.wb-table{
    width:100%;
    border-collapse:collapse;
    min-width:950px;
}
.wb-table th{
    text-align:left;
    padding:.85rem .9rem;
    font-size:.67rem;
    text-transform:uppercase;
    letter-spacing:.12em;
    color:#8f867d;
    border-bottom:1px solid #292522;
    white-space:nowrap;
}
.wb-table td{
    padding:.9rem;
    border-bottom:1px solid #211e1b;
    vertical-align:middle;
}
.wb-table tbody tr:hover{
    background:#171513;
}
.wb-customer{
    font-weight:900;
}
.wb-desc{
    color:#938980;
    font-size:.82rem;
    margin-top:.2rem;
}
.wb-status{
    display:inline-flex;
    align-items:center;
    padding:.34rem .62rem;
    border-radius:999px;
    font-size:.67rem;
    font-weight:900;
    text-transform:uppercase;
}
.wb-status.pending{
    background:rgba(224,122,47,.15);
    color:#f0a05e;
}
.wb-status.confirmed{
    background:rgba(58,168,85,.15);
    color:#6bd58b;
}
.wb-status.completed{
    background:rgba(80,140,220,.15);
    color:#82b1ee;
}
.wb-status.cancelled{
    background:rgba(179,51,51,.15);
    color:#f08080;
}
.wb-paid{color:#67d18a;font-weight:800}
.wb-balance{color:#ffb15e;font-weight:800}
.wb-actions-cell{
    display:flex;
    gap:.4rem;
    flex-wrap:wrap;
}
.wb-empty{
    padding:3rem 1rem;
    text-align:center;
    color:#8c837a;
}
.wb-create{
    display:none;
    margin-bottom:1rem;
    padding:1.2rem;
    background:#11100f;
    border:1px solid #292522;
    border-radius:14px;
}
.wb-create.open{
    display:block;
}
.wb-create-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:1rem;
}
.wb-create label{
    display:block;
    margin-bottom:.4rem;
    font-size:.7rem;
    text-transform:uppercase;
    letter-spacing:.12em;
    color:#9b9188;
    font-weight:800;
}
.wb-create input,
.wb-create select,
.wb-create textarea{
    width:100%;
    background:#0b0a09;
    color:#eee;
    border:1px solid #332e2a;
    border-radius:8px;
    padding:.72rem;
}
.wb-create textarea{
    min-height:90px;
    resize:vertical;
}
.wb-full{
    grid-column:1/-1;
}
.wb-create-buttons{
    display:flex;
    gap:.5rem;
    margin-top:1rem;
}
@media(max-width:1100px){
    .wb-filters{
        grid-template-columns:repeat(3,minmax(0,1fr));
    }
    .wb-filters .wb-search{
        grid-column:1/-1;
    }
    .wb-stats{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}
@media(max-width:700px){
    .wb-filters{
        grid-template-columns:1fr;
    }
    .wb-filters .wb-search{
        grid-column:auto;
    }
    .wb-stats{
        grid-template-columns:1fr 1fr;
    }
    .wb-create-grid{
        grid-template-columns:1fr;
    }
    .wb-full{
        grid-column:auto;
    }
}
@media(max-width:480px){
    .wb-stats{
        grid-template-columns:1fr;
    }
}
</style>

<div class="wb-wrap">

    <div class="wb-head">
        <div>
            <h1 class="wb-title">Walk-in Bookings</h1>
            <p class="wb-sub">Manage bookings created directly at the studio.</p>
        </div>

        <div class="wb-actions">
            <button
                type="button"
                class="btn btn-primary"
                onclick="document.getElementById('wbCreate').classList.toggle('open')"
            >
                + New Walk-in
            </button>

            <a class="btn btn-ghost" href="calendar.php">
                📅 Calendar
            </a>
        </div>
    </div>

    <?php if ($ok = flash('ok')): ?>
        <div class="alert alert-success"><?= wb_h($ok) ?></div>
    <?php endif; ?>

    <?php if ($er = flash('err')): ?>
        <div class="alert alert-error"><?= wb_h($er) ?></div>
    <?php endif; ?>

    <div id="wbCreate" class="wb-create">
        <h3 style="margin:0 0 1rem;font-family:var(--font-serif);">
            New Walk-in Booking
        </h3>

        <form method="post">
            <input type="hidden" name="csrf" value="<?= wb_h($csrf) ?>">
            <input type="hidden" name="action" value="create">

            <div class="wb-create-grid">
                <div>
                    <label>Customer Name</label>
                    <input required name="customer_name" placeholder="Customer name">
                </div>

                <div>
                    <label>Customer Contact (Optional)</label>
                    <input name="customer_contact" maxlength="30" placeholder="Phone number">
                </div>

                <div>
                    <label>Booking Date</label>
                    <input type="date" name="booking_date" value="<?= wb_h(date('Y-m-d')) ?>">
                </div>

                <div>
                    <label>Event Date</label>
                    <input type="date" name="event_date">
                </div>

                <div>
                    <label>Status</label>
                    <select name="status">
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div>
                    <label>Total Amount</label>
                    <input type="number" min="0" step="100" name="total_amount" value="0">
                </div>

                <div>
                    <label>Advance / Paid</label>
                    <input type="number" min="0" step="100" name="advance" value="0">
                </div>

                <div class="wb-full">
                    <label>Service / Description</label>
                    <textarea name="description" maxlength="255" placeholder="Service or booking description"></textarea>
                </div>

                <div class="wb-full">
                    <label>Notes (Optional)</label>
                    <textarea name="notes" placeholder="Additional notes for this booking"></textarea>
                </div>
            </div>

            <div class="wb-create-buttons">
                <button type="submit" class="btn btn-primary">Create Booking</button>
                <button
                    type="button"
                    class="btn btn-ghost"
                    onclick="document.getElementById('wbCreate').classList.remove('open')"
                >
                    Cancel
                </button>
            </div>
        </form>
    </div>

    <form method="get" class="wb-filters">
        <div class="wb-search">
            <input
                type="search"
                name="q"
                value="<?= wb_h($q) ?>"
                placeholder="Search customer or service..."
            >
        </div>

        <div>
            <select name="status">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>

        <div>
            <input type="date" name="from" value="<?= wb_h($from) ?>" title="From date">
        </div>

        <div>
            <input type="date" name="to" value="<?= wb_h($to) ?>" title="To date">
        </div>

        <div>
            <button class="btn btn-primary" type="submit">Filter</button>
        </div>

        <div>
            <a class="btn btn-ghost" href="walkin-bookings.php">Reset</a>
        </div>
    </form>

    <div class="wb-stats">
        <div class="wb-stat">
            <div class="label">Bookings</div>
            <div class="value"><?= number_format((int)$stats['total_count']) ?></div>
        </div>

        <div class="wb-stat">
            <div class="label">Booking Value</div>
            <div class="value"><?= wb_money((float)$stats['total_value']) ?></div>
        </div>

        <div class="wb-stat">
            <div class="label">Paid / Advance</div>
            <div class="value wb-paid"><?= wb_money((float)$stats['total_paid']) ?></div>
        </div>

        <div class="wb-stat">
            <div class="label">Outstanding</div>
            <div class="value wb-balance"><?= wb_money((float)$stats['total_balance']) ?></div>
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-table-wrap">
            <table class="wb-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Booking Date</th>
                        <th>Event Date</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (!$walkins): ?>
                    <tr>
                        <td colspan="9">
                            <div class="wb-empty">
                                No walk-in bookings found.
                            </div>
                        </td>
                    </tr>
                <?php else: ?>

                    <?php foreach ($walkins as $row): ?>
                        <tr>
                            <td>#<?= (int)$row['id'] ?></td>

                            <td>
                                <div class="wb-customer">
                                    <?= wb_h($row['customer_name']) ?>
                                </div>
                                <div class="wb-desc">
                                    <?= wb_h($row['description'] ?: 'Walk-in Service') ?>
                                </div>
                                <?php if (!empty($row['customer_contact'])): ?>
                                    <div class="wb-desc"><?= wb_h($row['customer_contact']) ?></div>
                                <?php endif; ?>
                            </td>

                            <td><?= wb_h($row['booking_date']) ?></td>

                            <td><?= wb_h($row['event_date'] ?: '—') ?></td>

                            <td>
                                <span class="wb-status <?= wb_h(wb_status_class((string)$row['status'])) ?>">
                                    <?= wb_h(ucfirst((string)$row['status'])) ?>
                                </span>
                            </td>

                            <td><?= wb_money((float)$row['total_amount']) ?></td>

                            <td class="wb-paid">
                                <?= wb_money((float)$row['advance']) ?>
                            </td>

                            <td class="wb-balance">
                                <?= wb_money((float)$row['balance']) ?>
                            </td>

                            <td>
                                <div class="wb-actions-cell">
                                    <a
                                        class="btn btn-ghost"
                                        href="walkin-booking-view.php?id=<?= (int)$row['id'] ?>&return=list"
                                    >
                                        View
                                    </a>

                                    <?php if (is_admin()): ?>
                                        <form
                                            method="post"
                                            style="display:inline"
                                            onsubmit="return confirm('Delete this walk-in booking permanently?');"
                                        >
                                            <input type="hidden" name="csrf" value="<?= wb_h($csrf) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                            <button type="submit" class="btn btn-ghost" style="color:#ff9a9a;border-color:#5b2424">
                                                Delete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
