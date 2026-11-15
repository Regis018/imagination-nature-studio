<?php
declare(strict_types=1);

/**
 * Admin — Walk-in Booking details.
 * Works with the existing walkin_bookings table.
 */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['walkin_booking_view_csrf'])) {
    $_SESSION['walkin_booking_view_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['walkin_booking_view_csrf'];

function wbv_h(mixed $value): string {
    return e((string)$value);
}
function wbv_money(float $value): string {
    return number_format($value, 0, '.', ',') . ' RWF';
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$returnTo = (($_GET['return'] ?? $_POST['return'] ?? 'list') === 'list')
    ? 'walkin-bookings.php' : 'walkin-bookings.php';

if ($id <= 0) {
    flash('err', 'Invalid walk-in booking ID.');
    redirect($returnTo);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($csrf, $postedCsrf)) {
        flash('err', 'Security check failed. Please try again.');
        redirect('walkin-booking-view.php?' . http_build_query(['id' => $id, 'return' => 'list']));
    }

    $action = (string)($_POST['action'] ?? '');
    if ($action === 'update' && is_admin()) {
        $status = strtolower(trim((string)($_POST['status'] ?? 'pending')));
        $advance = max(0, (float)($_POST['advance'] ?? 0));
        $allowed = ['pending', 'confirmed', 'completed', 'cancelled'];

        if (!in_array($status, $allowed, true)) {
            flash('err', 'Invalid booking status.');
        } else {
            try {
                $stmt = $pdo->prepare('SELECT total_amount FROM walkin_bookings WHERE id=? LIMIT 1');
                $stmt->execute([$id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$existing) {
                    flash('err', 'Walk-in booking not found.');
                    redirect('walkin-bookings.php');
                }
                $total = max(0, (float)$existing['total_amount']);
                $advance = min($advance, $total);
                $balance = max(0, $total - $advance);

                $update = $pdo->prepare(
                    'UPDATE walkin_bookings SET status=?, advance=?, balance=? WHERE id=?'
                );
                $update->execute([$status, $advance, $balance, $id]);
                flash('ok', 'Walk-in booking updated successfully.');
            } catch (Throwable $e) {
                error_log('Walk-in view update error: ' . $e->getMessage());
                flash('err', 'Could not update this booking. Please check the server error log.');
            }
        }
        redirect('walkin-booking-view.php?' . http_build_query(['id' => $id, 'return' => 'list']));
    }

    flash('err', 'You are not allowed to perform that action.');
    redirect('walkin-booking-view.php?' . http_build_query(['id' => $id, 'return' => 'list']));
}

$stmt = $pdo->prepare('SELECT * FROM walkin_bookings WHERE id=? LIMIT 1');
$stmt->execute([$id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    flash('err', 'Walk-in booking not found.');
    redirect('walkin-bookings.php');
}

$ok = flash('ok');
$err = flash('err');
require __DIR__ . '/includes/admin-header.php';
?>
<style>
.wbv-wrap{max-width:1100px;margin:0 auto}
.wbv-card{background:#11100f;border:1px solid #292522;border-radius:14px;padding:1.2rem;margin-bottom:1rem}
.wbv-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
.wbv-item{padding:.85rem;background:#171513;border:1px solid #292522;border-radius:9px;min-width:0}
.wbv-label{font-size:.68rem;text-transform:uppercase;letter-spacing:.12em;color:#8f867d;margin-bottom:.35rem}
.wbv-value{font-weight:800;overflow-wrap:anywhere}
.wbv-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
.wbv-form label{display:block;color:#9b9188;font-size:.75rem;margin-bottom:.35rem}
.wbv-form input,.wbv-form select{width:100%;padding:.7rem;background:#0b0a09;color:#eee;border:1px solid #332e2a;border-radius:8px}
@media(max-width:650px){.wbv-grid,.wbv-form{grid-template-columns:1fr}}
</style>

<div class="wbv-wrap">
    <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem">
        <div>
            <h1 style="margin:0;font-family:var(--font-serif)">Walk-in Booking #<?= (int)$booking['id'] ?></h1>
            <p style="color:#8c837a;margin:.35rem 0 0">Booking details and payment information</p>
        </div>
        <a class="btn btn-ghost" href="walkin-bookings.php">← Back to Walk-in Bookings</a>
    </div>

    <?php if ($ok): ?><div class="alert alert-success"><?= wbv_h($ok) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= wbv_h($err) ?></div><?php endif; ?>

    <div class="wbv-card">
        <h3 style="margin:0 0 1rem">Booking Information</h3>
        <div class="wbv-grid">
            <div class="wbv-item"><div class="wbv-label">Customer</div><div class="wbv-value"><?= wbv_h($booking['customer_name'] ?? '') ?></div></div>
            <div class="wbv-item"><div class="wbv-label">Status</div><div class="wbv-value"><?= wbv_h(ucfirst((string)$booking['status'])) ?></div></div>
            <div class="wbv-item"><div class="wbv-label">Booking Date</div><div class="wbv-value"><?= wbv_h($booking['booking_date'] ?? '—') ?></div></div>
            <div class="wbv-item"><div class="wbv-label">Event Date</div><div class="wbv-value"><?= wbv_h(($booking['event_date'] ?? '') ?: '—') ?></div></div>
            <div class="wbv-item" style="grid-column:1/-1"><div class="wbv-label">Service / Description</div><div class="wbv-value"><?= nl2br(wbv_h(($booking['description'] ?? '') ?: '—')) ?></div></div>
        </div>
    </div>

    <div class="wbv-card">
        <h3 style="margin:0 0 1rem">Payment Summary</h3>
        <div class="wbv-grid">
            <div class="wbv-item"><div class="wbv-label">Total Amount</div><div class="wbv-value"><?= wbv_money((float)$booking['total_amount']) ?></div></div>
            <div class="wbv-item"><div class="wbv-label">Advance / Paid</div><div class="wbv-value" style="color:#67d18a"><?= wbv_money((float)$booking['advance']) ?></div></div>
            <div class="wbv-item"><div class="wbv-label">Balance</div><div class="wbv-value" style="color:#ffb15e"><?= wbv_money((float)$booking['balance']) ?></div></div>
        </div>
    </div>

    <?php if (is_admin()): ?>
    <div class="wbv-card">
        <h3 style="margin:0 0 1rem">Update Booking</h3>
        <form method="post" class="wbv-form">
            <input type="hidden" name="csrf" value="<?= wbv_h($csrf) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= (int)$booking['id'] ?>">
            <input type="hidden" name="return" value="list">
            <div>
                <label for="wbv-status">Booking Status</label>
                <select id="wbv-status" name="status">
                    <?php foreach (['pending','confirmed','completed','cancelled'] as $status): ?>
                        <option value="<?= $status ?>" <?= strtolower((string)$booking['status']) === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="wbv-advance">Advance / Paid (RWF)</label>
                <input id="wbv-advance" type="number" min="0" step="100" max="<?= (float)$booking['total_amount'] ?>" name="advance" value="<?= (float)$booking['advance'] ?>">
            </div>
            <div style="grid-column:1/-1">
                <button class="btn btn-primary" type="submit">Save Changes</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
