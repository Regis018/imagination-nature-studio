<?php
/**
 * Admin — Financial Transactions
 *
 * Unified read-only financial center:
 * - Booking payments (successful payments only)
 * - Walk-in sales payments (sales.amount_paid)
 * - Studio expenses
 *
 * This page does not create, edit, or delete transactions.
 */

require_once __DIR__ . '/../includes/ops.php';
require_admin();

$pdo = db();

/* =====================================================
   Filters
   ===================================================== */
$period = $_GET['period'] ?? 'month';
$allowedPeriods = ['today', 'week', 'month', 'year', 'custom'];

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

if (!in_array($period, $allowedPeriods, true)) {
    $period = 'month';
}

$today = date('Y-m-d');

if ($period === 'today') {
    $from = $today;
    $to   = $today;
} elseif ($period === 'week') {
    $from = date('Y-m-d', strtotime('monday this week'));
    $to   = $today;
} elseif ($period === 'month') {
    $from = date('Y-m-01');
    $to   = $today;
} elseif ($period === 'year') {
    $from = date('Y-01-01');
    $to   = $today;
} else {
    if ($from === '') $from = date('Y-m-01');
    if ($to === '')   $to = $today;
}

if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$type   = $_GET['type'] ?? 'all';
$method = $_GET['method'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$allowedTypes = ['all', 'income', 'expense', 'payment', 'sale'];
if (!in_array($type, $allowedTypes, true)) {
    $type = 'all';
}

/* =====================================================
   Build unified transactions
   ===================================================== */
$transactions = [];

/* ---------- Booking payments ---------- */
if ($type === 'all' || $type === 'income' || $type === 'payment') {
    $sql = "
        SELECT
            p.id,
            p.booking_id,
            p.amount,
            p.payment_method,
            p.payment_status,
            p.reference,
            p.provider_reference,
            p.payment_date,
            p.notes,
            b.booking_number,
            c.full_name AS customer_name
        FROM payments p
        LEFT JOIN bookings b ON b.id = p.booking_id
        LEFT JOIN customers c ON c.id = b.customer_id
        WHERE p.payment_status = 'successful'
          AND DATE(p.payment_date) BETWEEN ? AND ?
    ";

    $args = [$from, $to];

    if ($method !== 'all') {
        $sql .= " AND p.payment_method = ?";
        $args[] = $method;
    }

    if ($search !== '') {
        $sql .= " AND (
            b.booking_number LIKE ?
            OR c.name LIKE ?
            OR p.reference LIKE ?
            OR p.provider_reference LIKE ?
        )";
        $like = "%{$search}%";
        array_push($args, $like, $like, $like, $like);
    }

    $sql .= " ORDER BY p.payment_date DESC, p.id DESC LIMIT 1000";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);

    foreach ($stmt->fetchAll() as $r) {
        $transactions[] = [
            'date'        => $r['payment_date'],
            'type'        => 'payment',
            'direction'   => 'income',
            'description' => 'Booking payment',
            'reference'   => $r['booking_number'] ?: ('Payment #' . $r['id']),
            'customer'    => $r['customer_name'] ?: '—',
            'method'      => $r['payment_method'] ?: '—',
            'amount'      => (float)$r['amount'],
            'status'      => 'Successful',
            'notes'       => $r['notes'] ?: ($r['reference'] ?: $r['provider_reference'] ?: ''),
            'source_id'   => (int)$r['id'],
        ];
    }
}

/* ---------- Walk-in sales ---------- */
if ($type === 'all' || $type === 'income' || $type === 'sale') {
    $sql = "
        SELECT
            s.id,
            s.sale_date,
            s.customer_name,
            s.customer_phone,
            s.product_name,
            s.description,
            s.amount_paid,
            s.payment_method,
            s.total,
            s.balance
        FROM sales s
        WHERE s.sale_date BETWEEN ? AND ?
          AND s.amount_paid > 0
    ";

    $args = [$from, $to];

    if ($method !== 'all') {
        $sql .= " AND s.payment_method = ?";
        $args[] = $method;
    }

    if ($search !== '') {
        $sql .= " AND (
            s.customer_name LIKE ?
            OR s.customer_phone LIKE ?
            OR s.product_name LIKE ?
            OR s.description LIKE ?
        )";
        $like = "%{$search}%";
        array_push($args, $like, $like, $like, $like);
    }

    $sql .= " ORDER BY s.sale_date DESC, s.id DESC LIMIT 1000";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);

    foreach ($stmt->fetchAll() as $r) {
        $transactions[] = [
            'date'        => $r['sale_date'] . ' 00:00:00',
            'type'        => 'sale',
            'direction'   => 'income',
            'description' => 'Sale — ' . $r['product_name'],
            'reference'   => 'Sale #' . $r['id'],
            'customer'    => $r['customer_name'] ?: 'Walk-in customer',
            'method'      => $r['payment_method'] ?: '—',
            'amount'      => (float)$r['amount_paid'],
            'status'      => ((float)$r['balance'] > 0 ? 'Partial payment' : 'Paid'),
            'notes'       => $r['description'] ?: '',
            'source_id'   => (int)$r['id'],
        ];
    }
}

/* ---------- Expenses ---------- */
if ($type === 'all' || $type === 'expense') {
    $sql = "
        SELECT
            e.id,
            e.expense_date,
            e.description,
            e.category,
            e.amount,
            e.staff_name,
            e.notes
        FROM expenses e
        WHERE e.expense_date BETWEEN ? AND ?
    ";

    $args = [$from, $to];

    /*
     * Expenses do not currently have a payment_method column,
     * so the method filter is intentionally not applied here.
     */
    if ($search !== '') {
        $sql .= " AND (
            e.description LIKE ?
            OR e.category LIKE ?
            OR e.staff_name LIKE ?
            OR e.notes LIKE ?
        )";
        $like = "%{$search}%";
        array_push($args, $like, $like, $like, $like);
    }

    $sql .= " ORDER BY e.expense_date DESC, e.id DESC LIMIT 1000";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);

    foreach ($stmt->fetchAll() as $r) {
        $transactions[] = [
            'date'        => $r['expense_date'] . ' 00:00:00',
            'type'        => 'expense',
            'direction'   => 'expense',
            'description' => $r['description'],
            'reference'   => 'Expense #' . $r['id'],
            'customer'    => $r['staff_name'] ?: 'Studio',
            'method'      => '—',
            'amount'      => (float)$r['amount'],
            'status'      => 'Recorded',
            'notes'       => $r['category'] . ($r['notes'] ? ' · ' . $r['notes'] : ''),
            'source_id'   => (int)$r['id'],
        ];
    }
}

/* =====================================================
   Sort unified list by date descending
   ===================================================== */
usort($transactions, function ($a, $b) {
    return strcmp($b['date'], $a['date']);
});

/* =====================================================
   Summary
   ===================================================== */
$moneyIn = 0.0;
$moneyOut = 0.0;
$incomeCount = 0;
$expenseCount = 0;

foreach ($transactions as $t) {
    if ($t['direction'] === 'income') {
        $moneyIn += $t['amount'];
        $incomeCount++;
    } else {
        $moneyOut += $t['amount'];
        $expenseCount++;
    }
}

$netCash = $moneyIn - $moneyOut;
$totalCount = count($transactions);

/* =====================================================
   Breakdown
   ===================================================== */
$byMethod = [];
$byType = [
    'payment' => 0,
    'sale'    => 0,
    'expense' => 0,
];

foreach ($transactions as $t) {
    $m = $t['method'];
    if ($m !== '—') {
        if (!isset($byMethod[$m])) $byMethod[$m] = 0;
        $byMethod[$m] += $t['amount'];
    }

    if (isset($byType[$t['type']])) {
        $byType[$t['type']] += $t['amount'];
    }
}

arsort($byMethod);

$methodLabels = [
    'cash'          => 'Cash',
    'mobile_money'  => 'Mobile Money',
    'bank_transfer' => 'Bank Transfer',
    'other'         => 'Other',
];

/* =====================================================
   Helpers
   ===================================================== */
function tx_money($amount) {
    return number_format((float)$amount, 0) . ' RWF';
}

function tx_method_label($method, $labels) {
    return $labels[$method] ?? ucwords(str_replace('_', ' ', (string)$method));
}

function tx_type_label($type) {
    if ($type === 'payment') return 'Booking Payment';
    if ($type === 'sale') return 'Sale';
    return 'Expense';
}

function tx_date_label($date) {
    $ts = strtotime($date);
    return $ts ? date('d M Y', $ts) : $date;
}

require __DIR__ . '/includes/admin-header.php';
?>

<style>
.tx-wrap{
    max-width:1500px;
    margin:0 auto;
}
.tx-hero{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:1rem;
    flex-wrap:wrap;
    margin-bottom:1.4rem;
}
.tx-kicker{
    color:var(--accent-2,#d5a15c);
    font-size:.72rem;
    letter-spacing:.2em;
    text-transform:uppercase;
}
.tx-hero h1{
    margin:.35rem 0 .35rem;
    font-family:var(--font-serif);
    font-size:2rem;
}
.tx-hero p{
    margin:0;
    color:#7a716a;
}
.tx-actions{
    display:flex;
    gap:.55rem;
    flex-wrap:wrap;
}
.tx-actions .btn{
    white-space:nowrap;
}

.tx-kpis{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:1rem;
    margin-bottom:1.2rem;
}
.tx-kpi{
    position:relative;
    overflow:hidden;
    min-height:145px;
    padding:1.25rem;
    border:1px solid #2b2724;
    border-radius:16px;
    background:
        radial-gradient(circle at 90% 10%,rgba(255,255,255,.04),transparent 34%),
        #11100f;
    box-shadow:0 10px 30px rgba(0,0,0,.12);
}
.tx-kpi .label{
    color:#8f8780;
    font-size:.68rem;
    letter-spacing:.16em;
    text-transform:uppercase;
}
.tx-kpi .value{
    margin-top:.5rem;
    font-family:var(--font-serif);
    font-size:1.8rem;
    line-height:1.1;
}
.tx-kpi .sub{
    margin-top:.55rem;
    color:#6e6761;
    font-size:.78rem;
}
.tx-kpi.in .value{color:#79e6a3}
.tx-kpi.out .value{color:#f19b8f}
.tx-kpi.net .value{color:#67d8df}
.tx-kpi.count .value{color:#c6a4ff}

.tx-filters{
    padding:1rem;
    margin-bottom:1.2rem;
    border:1px solid #2b2724;
    border-radius:14px;
    background:#11100f;
}
.tx-filter-row{
    display:flex;
    gap:.6rem;
    flex-wrap:wrap;
    align-items:center;
}
.tx-filter-row input,
.tx-filter-row select{
    min-width:150px;
}
.tx-filter-row input[type="text"]{
    min-width:240px;
}
.tx-quick{
    display:flex;
    gap:.45rem;
    flex-wrap:wrap;
    margin-top:.7rem;
}
.tx-quick a{
    display:inline-flex;
    align-items:center;
    padding:.45rem .7rem;
    border-radius:999px;
    border:1px solid #302b27;
    color:#a69d96;
    text-decoration:none;
    font-size:.78rem;
}
.tx-quick a:hover,
.tx-quick a.active{
    color:#fff;
    border-color:#8d7661;
    background:#1a1715;
}

.tx-layout{
    display:grid;
    grid-template-columns:minmax(0,1fr) 310px;
    gap:1.2rem;
    align-items:start;
}
.tx-table-card,
.tx-side-card{
    border:1px solid #2b2724;
    border-radius:16px;
    background:#11100f;
    overflow:hidden;
}
.tx-card-head{
    padding:1rem 1.15rem;
    border-bottom:1px solid #262220;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:.8rem;
    flex-wrap:wrap;
}
.tx-card-head h2{
    margin:0;
    font-family:var(--font-serif);
    font-size:1.08rem;
}
.tx-card-head span{
    color:#746c65;
    font-size:.78rem;
}
.tx-table-wrap{
    overflow-x:auto;
}
.tx-table{
    width:100%;
    min-width:920px;
    border-collapse:collapse;
}
.tx-table th{
    padding:.8rem 1rem;
    text-align:left;
    color:#706861;
    font-size:.68rem;
    letter-spacing:.12em;
    text-transform:uppercase;
    white-space:nowrap;
}
.tx-table td{
    padding:.85rem 1rem;
    border-top:1px solid #25211f;
    vertical-align:middle;
}
.tx-table tr:hover td{
    background:#151311;
}
.tx-date{
    white-space:nowrap;
    color:#c0b7b0;
    font-size:.82rem;
}
.tx-main{
    color:#f0e9e3;
    font-weight:600;
}
.tx-muted{
    color:#716962;
    font-size:.76rem;
    margin-top:.18rem;
}
.tx-amount{
    white-space:nowrap;
    font-weight:700;
}
.tx-amount.in{color:#79e6a3}
.tx-amount.out{color:#f19b8f}

.tx-badge{
    display:inline-flex;
    align-items:center;
    gap:.35rem;
    padding:.32rem .55rem;
    border-radius:999px;
    font-size:.7rem;
    white-space:nowrap;
    border:1px solid #332f2b;
}
.tx-badge.payment{
    color:#75dce4;
    background:rgba(103,216,223,.07);
}
.tx-badge.sale{
    color:#c8a1ff;
    background:rgba(198,164,255,.07);
}
.tx-badge.expense{
    color:#f1a08f;
    background:rgba(241,160,143,.07);
}

.tx-side{
    padding:1rem;
}
.tx-side h3{
    margin:0 0 1rem;
    font-family:var(--font-serif);
    font-size:1rem;
}
.tx-side-section{
    padding-bottom:1rem;
    margin-bottom:1rem;
    border-bottom:1px solid #25211f;
}
.tx-side-section:last-child{
    border-bottom:0;
    margin-bottom:0;
    padding-bottom:0;
}
.tx-stat-row{
    display:flex;
    justify-content:space-between;
    gap:.8rem;
    padding:.48rem 0;
    color:#8e867f;
    font-size:.82rem;
}
.tx-stat-row strong{
    color:#e9e1da;
    font-weight:600;
}
.tx-method-row{
    margin-bottom:.75rem;
}
.tx-method-top{
    display:flex;
    justify-content:space-between;
    gap:.5rem;
    color:#9c948d;
    font-size:.78rem;
    margin-bottom:.35rem;
}
.tx-bar{
    height:6px;
    overflow:hidden;
    border-radius:99px;
    background:#262220;
}
.tx-bar span{
    display:block;
    height:100%;
    border-radius:99px;
    background:linear-gradient(90deg,#65d6df,#79e6a3);
}
.tx-empty{
    padding:3.5rem 1rem;
    text-align:center;
    color:#716962;
}
.tx-print{
    display:none;
}

@media(max-width:1100px){
    .tx-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
    .tx-layout{grid-template-columns:1fr}
}
@media(max-width:650px){
    .tx-kpis{grid-template-columns:1fr}
    .tx-hero h1{font-size:1.55rem}
    .tx-filter-row > *{
        width:100%;
        min-width:0 !important;
    }
    .tx-filter-row .btn{
        width:auto;
    }
}

@media print{
    .admin-sidebar,
    .admin-header,
    .tx-actions,
    .tx-filters,
    .tx-side-card{
        display:none !important;
    }
    .tx-wrap{max-width:none}
    .tx-layout{display:block}
    .tx-table-card{
        border:0;
        background:#fff;
    }
    .tx-table{
        min-width:0;
        color:#111;
    }
    .tx-table td,
    .tx-table th{
        border-color:#ddd;
    }
    .tx-print{display:block}
}
</style>

<div class="tx-wrap">

    <div class="tx-hero">
        <div>
            <div class="tx-kicker">Finance Center</div>
            <h1>Financial Transactions</h1>
            <p>All money received and money spent by Imagination Nature Studio.</p>
        </div>

        <div class="tx-actions">
            <button type="button" class="btn btn-sm btn-ghost" onclick="window.print()">🖨 Print</button>
            <a class="btn btn-sm btn-ghost" href="sales.php">Sales</a>
            <a class="btn btn-sm btn-ghost" href="expenses.php">Expenses</a>
            <a class="btn btn-sm btn-primary" href="dashboard.php">Dashboard</a>
        </div>
    </div>

    <div class="tx-kpis">

        <div class="tx-kpi in">
            <div class="label">Money In</div>
            <div class="value"><?= e(tx_money($moneyIn)) ?></div>
            <div class="sub"><?= (int)$incomeCount ?> income transactions</div>
        </div>

        <div class="tx-kpi out">
            <div class="label">Money Out</div>
            <div class="value"><?= e(tx_money($moneyOut)) ?></div>
            <div class="sub"><?= (int)$expenseCount ?> expense transactions</div>
        </div>

        <div class="tx-kpi net">
            <div class="label">Net Cash</div>
            <div class="value"><?= e(tx_money($netCash)) ?></div>
            <div class="sub"><?= $netCash >= 0 ? 'Positive cash flow' : 'Negative cash flow' ?></div>
        </div>

        <div class="tx-kpi count">
            <div class="label">Transactions</div>
            <div class="value"><?= (int)$totalCount ?></div>
            <div class="sub"><?= e(date('d M Y', strtotime($from))) ?> → <?= e(date('d M Y', strtotime($to))) ?></div>
        </div>

    </div>

    <form method="get" class="tx-filters">

        <div class="tx-filter-row">

            <input type="date" name="from" value="<?= e($from) ?>">
            <input type="date" name="to" value="<?= e($to) ?>">

            <select name="type">
                <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All Types</option>
                <option value="payment" <?= $type === 'payment' ? 'selected' : '' ?>>Booking Payments</option>
                <option value="sale" <?= $type === 'sale' ? 'selected' : '' ?>>Sales</option>
                <option value="expense" <?= $type === 'expense' ? 'selected' : '' ?>>Expenses</option>
                <option value="income" <?= $type === 'income' ? 'selected' : '' ?>>All Income</option>
            </select>

            <select name="method">
                <option value="all" <?= $method === 'all' ? 'selected' : '' ?>>All Payment Methods</option>
                <?php foreach ($methodLabels as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $method === $key ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input
                type="text"
                name="q"
                value="<?= e($search) ?>"
                placeholder="Search transaction..."
            >

            <input type="hidden" name="period" value="custom">

            <button class="btn btn-sm btn-primary">Filter</button>
            <a class="btn btn-sm btn-ghost" href="transactions.php">Reset</a>

        </div>

        <div class="tx-quick">
            <a href="transactions.php?period=today" class="<?= $period === 'today' ? 'active' : '' ?>">Today</a>
            <a href="transactions.php?period=week" class="<?= $period === 'week' ? 'active' : '' ?>">This Week</a>
            <a href="transactions.php?period=month" class="<?= $period === 'month' ? 'active' : '' ?>">This Month</a>
            <a href="transactions.php?period=year" class="<?= $period === 'year' ? 'active' : '' ?>">This Year</a>
        </div>

    </form>

    <div class="tx-layout">

        <section class="tx-table-card">

            <div class="tx-card-head">
                <h2>Transaction History</h2>
                <span><?= (int)$totalCount ?> records</span>
            </div>

            <?php if ($transactions): ?>

                <div class="tx-table-wrap">
                    <table class="tx-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Customer / Staff</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th style="text-align:right">Amount</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($transactions as $t): ?>

                            <tr>

                                <td class="tx-date">
                                    <?= e(tx_date_label($t['date'])) ?>
                                    <?php if (date('H:i', strtotime($t['date'])) !== '00:00'): ?>
                                        <div class="tx-muted"><?= e(date('H:i', strtotime($t['date']))) ?></div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="tx-badge <?= e($t['type']) ?>">
                                        <?php if ($t['type'] === 'payment'): ?>
                                            💳
                                        <?php elseif ($t['type'] === 'sale'): ?>
                                            🛒
                                        <?php else: ?>
                                            💸
                                        <?php endif; ?>
                                        <?= e(tx_type_label($t['type'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="tx-main"><?= e($t['description']) ?></div>
                                    <div class="tx-muted"><?= e($t['reference']) ?></div>
                                    <?php if ($t['notes'] !== ''): ?>
                                        <div class="tx-muted"><?= e($t['notes']) ?></div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="tx-main"><?= e($t['customer']) ?></div>
                                </td>

                                <td>
                                    <span style="color:#9a918a;font-size:.8rem">
                                        <?= e(tx_method_label($t['method'], $methodLabels)) ?>
                                    </span>
                                </td>

                                <td>
                                    <span style="color:#8d8580;font-size:.76rem">
                                        <?= e($t['status']) ?>
                                    </span>
                                </td>

                                <td style="text-align:right">
                                    <span class="tx-amount <?= $t['direction'] === 'income' ? 'in' : 'out' ?>">
                                        <?= $t['direction'] === 'income' ? '+' : '−' ?>
                                        <?= e(tx_money($t['amount'])) ?>
                                    </span>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>
                    </table>
                </div>

            <?php else: ?>

                <div class="tx-empty">
                    <div style="font-size:2rem;margin-bottom:.5rem">💳</div>
                    <strong style="display:block;color:#b4aaa2;margin-bottom:.35rem">
                        No transactions found
                    </strong>
                    Try another date range, type, payment method, or search term.
                </div>

            <?php endif; ?>

        </section>

        <aside class="tx-side-card">

            <div class="tx-side">

                <h3>Financial Summary</h3>

                <div class="tx-side-section">

                    <div class="tx-stat-row">
                        <span>Booking Payments</span>
                        <strong><?= e(tx_money($byType['payment'])) ?></strong>
                    </div>

                    <div class="tx-stat-row">
                        <span>Sales Collected</span>
                        <strong><?= e(tx_money($byType['sale'])) ?></strong>
                    </div>

                    <div class="tx-stat-row">
                        <span>Expenses</span>
                        <strong style="color:#f19b8f"><?= e(tx_money($byType['expense'])) ?></strong>
                    </div>

                    <div class="tx-stat-row" style="padding-top:.8rem;border-top:1px solid #25211f">
                        <span>Net Cash</span>
                        <strong style="color:<?= $netCash >= 0 ? '#79e6a3' : '#f19b8f' ?>">
                            <?= e(tx_money($netCash)) ?>
                        </strong>
                    </div>

                </div>

                <div class="tx-side-section">

                    <h3 style="margin-bottom:.75rem">Money In by Method</h3>

                    <?php
                    $methodTotal = array_sum($byMethod);
                    ?>

                    <?php if ($byMethod): ?>

                        <?php foreach ($byMethod as $key => $amount): ?>

                            <?php
                            $pct = $methodTotal > 0 ? ($amount / $methodTotal) * 100 : 0;
                            ?>

                            <div class="tx-method-row">

                                <div class="tx-method-top">
                                    <span><?= e(tx_method_label($key, $methodLabels)) ?></span>
                                    <strong><?= e(tx_money($amount)) ?></strong>
                                </div>

                                <div class="tx-bar">
                                    <span style="width:<?= min(100, max(0, $pct)) ?>%"></span>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div style="color:#706861;font-size:.8rem">
                            No income transactions in this period.
                        </div>

                    <?php endif; ?>

                </div>

                <div class="tx-side-section">

                    <h3 style="margin-bottom:.75rem">Period</h3>

                    <div class="tx-stat-row">
                        <span>From</span>
                        <strong><?= e(date('d M Y', strtotime($from))) ?></strong>
                    </div>

                    <div class="tx-stat-row">
                        <span>To</span>
                        <strong><?= e(date('d M Y', strtotime($to))) ?></strong>
                    </div>

                    <div class="tx-stat-row">
                        <span>Income count</span>
                        <strong><?= (int)$incomeCount ?></strong>
                    </div>

                    <div class="tx-stat-row">
                        <span>Expense count</span>
                        <strong><?= (int)$expenseCount ?></strong>
                    </div>

                </div>

            </div>

        </aside>

    </div>

    <div class="tx-print" style="margin-top:1rem;color:#555">
        Printed from Imagination Nature Studio — Financial Transactions
    </div>

</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
