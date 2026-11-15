<?php
declare(strict_types=1);

/**
 * Imagination Nature Studio — Financial Transactions
 *
 * Read-only financial center:
 *   Income  = successful booking payments + sales amount_paid
 *   Expense = recorded expenses
 *
 * This page does NOT create, edit, or delete transactions.
 */

require_once __DIR__ . '/../includes/ops.php';

/*
 * Access:
 * - Admin: full access
 * - Staff: read-only access to Financial Transactions
 */
require_login();

if (!is_admin() && !is_staff()) {
    redirect('daily-report.php');
}

$pdo = db();

/* =========================================================
   FILTERS
   ========================================================= */

$period = $_GET['period'] ?? 'month';
$allowedPeriods = ['today', 'week', 'month', 'year', 'custom'];

if (!in_array($period, $allowedPeriods, true)) {
    $period = 'month';
}

$today = date('Y-m-d');

switch ($period) {
    case 'today':
        $from = $today;
        $to = $today;
        $periodLabel = 'Today';
        break;

    case 'week':
        $from = date('Y-m-d', strtotime('monday this week'));
        $to = $today;
        $periodLabel = 'This Week';
        break;

    case 'year':
        $from = date('Y-01-01');
        $to = $today;
        $periodLabel = 'This Year';
        break;

    case 'custom':
        $from = trim((string)($_GET['from'] ?? ''));
        $to   = trim((string)($_GET['to'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = date('Y-m-01');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = $today;
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $periodLabel = date('d M Y', strtotime($from))
            . ' — '
            . date('d M Y', strtotime($to));
        break;

    default:
        $from = date('Y-m-01');
        $to = $today;
        $periodLabel = 'This Month';
        break;
}

$type = strtolower(trim((string)($_GET['type'] ?? 'all')));
$allowedTypes = ['all', 'income', 'expense'];

if (!in_array($type, $allowedTypes, true)) {
    $type = 'all';
}

$method = strtolower(trim((string)($_GET['method'] ?? 'all')));
$search = trim((string)($_GET['q'] ?? ''));

/* =========================================================
   HELPERS
   ========================================================= */

function txMoney(float $amount): string
{
    return number_format($amount, 0) . ' RWF';
}

function txMethodLabel(string $method): string
{
    $method = strtolower(trim($method));

    $labels = [
        'cash'          => 'Cash',
        'mobile_money'  => 'Mobile Money',
        'momo'          => 'Mobile Money',
        'bank_transfer' => 'Bank Transfer',
        'card'          => 'Card',
        'other'         => 'Other',
        ''              => 'Not specified',
    ];

    return $labels[$method] ?? ucwords(str_replace('_', ' ', $method));
}

function txSafeDate(string $value): string
{
    if ($value === '') {
        return '';
    }

    $time = strtotime($value);

    return $time ? date('d M Y', $time) : $value;
}

function txSafeTime(string $value): string
{
    if ($value === '') {
        return '';
    }

    $time = strtotime($value);

    return $time ? date('H:i', $time) : '';
}

/* =========================================================
   BUILD TRANSACTION LIST
   ========================================================= */

$transactions = [];

$searchLike = '%' . $search . '%';

/* ---------- Booking payments ---------- */
if ($type === 'all' || $type === 'income') {

    $sql = "
        SELECT
            p.id,
            p.payment_date AS tx_date,
            'income' AS tx_type,
            'Booking Payment' AS tx_source,
            COALESCE(p.amount, 0) AS amount,
            COALESCE(p.payment_method, '') AS payment_method,
            COALESCE(p.reference, '') AS reference,
            COALESCE(p.provider_reference, '') AS provider_reference,
            COALESCE(p.notes, '') AS notes,
            COALESCE(b.booking_number, '') AS booking_number,
            COALESCE(c.full_name, '') AS customer_name
        FROM payments p
        LEFT JOIN bookings b ON b.id = p.booking_id
        LEFT JOIN customers c ON c.id = b.customer_id
        WHERE p.payment_status = 'successful'
          AND DATE(p.payment_date) BETWEEN ? AND ?
    ";

    $args = [$from, $to];

    if ($method !== '' && $method !== 'all') {
        $sql .= " AND LOWER(COALESCE(p.payment_method,'')) = ?";
        $args[] = $method;
    }

    if ($search !== '') {
        $sql .= "
            AND (
                COALESCE(b.booking_number,'') LIKE ?
                OR COALESCE(c.full_name,'') LIKE ?
                OR COALESCE(p.reference,'') LIKE ?
                OR COALESCE(p.provider_reference,'') LIKE ?
                OR COALESCE(p.notes,'') LIKE ?
            )
        ";
        array_push($args, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
    }

    $sql .= " ORDER BY p.payment_date DESC, p.id DESC LIMIT 1000";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $transactions[] = [
            'id'              => (int)$row['id'],
            'date'            => (string)$row['tx_date'],
            'type'            => 'income',
            'source'          => 'Booking Payment',
            'description'     => $row['booking_number'] !== ''
                ? 'Booking ' . $row['booking_number']
                : 'Booking Payment',
            'customer'        => (string)$row['customer_name'],
            'method'          => (string)$row['payment_method'],
            'amount'          => (float)$row['amount'],
            'reference'       => (string)$row['reference'],
            'provider'        => (string)$row['provider_reference'],
            'notes'           => (string)$row['notes'],
        ];
    }
}

/* ---------- Sales ---------- */
if ($type === 'all' || $type === 'income') {

    $sql = "
        SELECT
            s.id,
            s.sale_date AS tx_date,
            'income' AS tx_type,
            'Product Sale' AS tx_source,
            COALESCE(s.amount_paid, 0) AS amount,
            COALESCE(s.payment_method, '') AS payment_method,
            '' AS reference,
            '' AS provider_reference,
            COALESCE(s.notes, '') AS notes,
            COALESCE(s.product_name, '') AS product_name,
            COALESCE(s.customer_name, '') AS customer_name,
            COALESCE(s.customer_phone, '') AS customer_phone,
            COALESCE(s.description, '') AS description
        FROM sales s
        WHERE s.sale_date BETWEEN ? AND ?
    ";

    $args = [$from, $to];

    if ($method !== '' && $method !== 'all') {
        $sql .= " AND LOWER(COALESCE(s.payment_method,'')) = ?";
        $args[] = $method;
    }

    if ($search !== '') {
        $sql .= "
            AND (
                COALESCE(s.customer_name,'') LIKE ?
                OR COALESCE(s.customer_phone,'') LIKE ?
                OR COALESCE(s.product_name,'') LIKE ?
                OR COALESCE(s.description,'') LIKE ?
                OR COALESCE(s.notes,'') LIKE ?
            )
        ";
        array_push($args, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
    }

    $sql .= " ORDER BY s.sale_date DESC, s.id DESC LIMIT 1000";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

        $description = $row['product_name'] !== ''
            ? 'Sale — ' . $row['product_name']
            : 'Product Sale';

        if ($row['description'] !== '') {
            $description .= ' · ' . $row['description'];
        }

        $transactions[] = [
            'id'              => (int)$row['id'],
            'date'            => (string)$row['tx_date'],
            'type'            => 'income',
            'source'          => 'Product Sale',
            'description'     => $description,
            'customer'        => (string)$row['customer_name'],
            'method'          => (string)$row['payment_method'],
            'amount'          => (float)$row['amount'],
            'reference'       => '',
            'provider'        => '',
            'notes'           => (string)$row['notes'],
        ];
    }
}

/* ---------- Expenses ---------- */
if ($type === 'all' || $type === 'expense') {

    $sql = "
        SELECT
            e.id,
            e.expense_date AS tx_date,
            'expense' AS tx_type,
            'Expense' AS tx_source,
            COALESCE(e.amount, 0) AS amount,
            '' AS payment_method,
            '' AS reference,
            '' AS provider_reference,
            COALESCE(e.notes, '') AS notes,
            COALESCE(e.description, '') AS description,
            COALESCE(e.category, '') AS category,
            COALESCE(e.staff_name, '') AS staff_name
        FROM expenses e
        WHERE e.expense_date BETWEEN ? AND ?
    ";

    $args = [$from, $to];

    if ($search !== '') {
        $sql .= "
            AND (
                COALESCE(e.description,'') LIKE ?
                OR COALESCE(e.category,'') LIKE ?
                OR COALESCE(e.staff_name,'') LIKE ?
                OR COALESCE(e.notes,'') LIKE ?
            )
        ";
        array_push($args, $searchLike, $searchLike, $searchLike, $searchLike);
    }

    $sql .= " ORDER BY e.expense_date DESC, e.id DESC LIMIT 1000";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

        $description = (string)$row['description'];

        if ($row['category'] !== '') {
            $description .= ' · ' . ucwords(str_replace('_', ' ', $row['category']));
        }

        $transactions[] = [
            'id'              => (int)$row['id'],
            'date'            => (string)$row['tx_date'],
            'type'            => 'expense',
            'source'          => 'Expense',
            'description'     => $description,
            'customer'        => (string)$row['staff_name'],
            'method'          => '',
            'amount'          => (float)$row['amount'],
            'reference'       => '',
            'provider'        => '',
            'notes'           => (string)$row['notes'],
        ];
    }
}

/* Newest first */
usort($transactions, static function (array $a, array $b): int {
    $dateCompare = strcmp((string)$b['date'], (string)$a['date']);

    if ($dateCompare !== 0) {
        return $dateCompare;
    }

    return ((int)$b['id']) <=> ((int)$a['id']);
});

/* =========================================================
   SUMMARY
   ========================================================= */

$totalIncome = 0.0;
$totalExpenses = 0.0;

foreach ($transactions as $tx) {
    if ($tx['type'] === 'income') {
        $totalIncome += (float)$tx['amount'];
    } else {
        $totalExpenses += (float)$tx['amount'];
    }
}

$netCash = $totalIncome - $totalExpenses;
$transactionCount = count($transactions);

$incomeCount = 0;
$expenseCount = 0;

foreach ($transactions as $tx) {
    if ($tx['type'] === 'income') {
        $incomeCount++;
    } else {
        $expenseCount++;
    }
}

/* =========================================================
   PAYMENT METHODS FOR FILTER
   ========================================================= */

$methods = [];

$methodStmt = $pdo->prepare("
    SELECT DISTINCT LOWER(TRIM(payment_method)) AS method
    FROM payments
    WHERE payment_status='successful'
      AND DATE(payment_date) BETWEEN ? AND ?
      AND TRIM(COALESCE(payment_method,'')) <> ''

    UNION

    SELECT DISTINCT LOWER(TRIM(payment_method)) AS method
    FROM sales
    WHERE sale_date BETWEEN ? AND ?
      AND TRIM(COALESCE(payment_method,'')) <> ''

    ORDER BY method
");

$methodStmt->execute([$from, $to, $from, $to]);

foreach ($methodStmt->fetchAll(PDO::FETCH_COLUMN) as $m) {
    if ($m !== '') {
        $methods[] = $m;
    }
}

/* =========================================================
   ADVANCED FINANCE SUMMARY
   ========================================================= */

$dailyTotals = [];
$methodTotals = [];
$sourceTotals = [];

foreach ($transactions as $tx) {
    $day = substr((string)$tx['date'], 0, 10);
    if ($day === '') {
        continue;
    }

    if (!isset($dailyTotals[$day])) {
        $dailyTotals[$day] = [
            'income' => 0.0,
            'expense' => 0.0,
        ];
    }

    if ($tx['type'] === 'income') {
        $dailyTotals[$day]['income'] += (float)$tx['amount'];
    } else {
        $dailyTotals[$day]['expense'] += (float)$tx['amount'];
    }

    if ($tx['type'] === 'income') {
        $methodName = trim((string)$tx['method']);
        if ($methodName === '') {
            $methodName = 'other';
        }
        $methodKey = strtolower($methodName);

        if (!isset($methodTotals[$methodKey])) {
            $methodTotals[$methodKey] = [
                'label' => txMethodLabel($methodKey),
                'amount' => 0.0,
            ];
        }
        $methodTotals[$methodKey]['amount'] += (float)$tx['amount'];
    }

    $sourceKey = strtolower((string)$tx['source']);
    if (!isset($sourceTotals[$sourceKey])) {
        $sourceTotals[$sourceKey] = [
            'label' => (string)$tx['source'],
            'amount' => 0.0,
            'type' => (string)$tx['type'],
        ];
    }
    $sourceTotals[$sourceKey]['amount'] += (float)$tx['amount'];
}

ksort($dailyTotals);
uksort($methodTotals, static function ($a, $b) use ($methodTotals) {
    return ($methodTotals[$b]['amount'] <=> $methodTotals[$a]['amount']);
});

$dailyRows = array_slice($dailyTotals, -31, 31, true);
$maxDaily = 0.0;
foreach ($dailyRows as $dayData) {
    $maxDaily = max($maxDaily, (float)$dayData['income'], (float)$dayData['expense']);
}
if ($maxDaily <= 0) {
    $maxDaily = 1;
}

$maxMethod = 0.0;
foreach ($methodTotals as $mData) {
    $maxMethod = max($maxMethod, (float)$mData['amount']);
}
if ($maxMethod <= 0) {
    $maxMethod = 1;
}

/* =========================================================
   CSV EXPORT
   ========================================================= */

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'transactions-' . $from . '-to-' . $to . '.csv';

    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'Date',
        'Type',
        'Source',
        'Description',
        'Customer / Staff',
        'Payment Method',
        'Reference',
        'Provider Reference',
        'Amount',
        'Notes'
    ]);

    foreach ($transactions as $tx) {
        fputcsv($out, [
            (string)$tx['date'],
            ucfirst((string)$tx['type']),
            (string)$tx['source'],
            (string)$tx['description'],
            (string)$tx['customer'],
            txMethodLabel((string)$tx['method']),
            (string)$tx['reference'],
            (string)$tx['provider'],
            number_format((float)$tx['amount'], 0, '.', ''),
            (string)$tx['notes'],
        ]);
    }

    fclose($out);
    exit;
}

/* =========================================================
   PAGE
   ========================================================= */

$pageTitle = 'Transactions';

require __DIR__ . '/includes/admin-header.php';
?>

<style>
/* =========================================================
   TRANSACTIONS — DARK PREMIUM FINANCE CENTER
   ========================================================= */

.tx-wrap{
    max-width:1500px;
    margin:0 auto;
    color:#eef5f2;
}

.tx-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-end;
    gap:20px;
    margin-bottom:22px;
}

.tx-title{
    margin:0;
    font-size:32px;
    font-weight:900;
    letter-spacing:-.03em;
    color:#f5f7f6;
}

.tx-sub{
    margin:6px 0 0;
    color:#9da9a6;
    font-size:14px;
}

.tx-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.tx-actions a,
.tx-actions button{
    text-decoration:none;
    border:1px solid #354344;
    background:#1b2324;
    color:#eef5f2;
    padding:9px 13px;
    border-radius:10px;
    font-size:13px;
    font-weight:800;
    cursor:pointer;
}

.tx-actions a:hover,
.tx-actions button:hover{
    border-color:#45d993;
    color:#72f0b1;
    background:#243334;
}

.tx-filters{
    background:linear-gradient(145deg,#151d1e,#101617);
    border:1px solid #293536;
    border-radius:16px;
    padding:15px;
    margin-bottom:18px;
    box-shadow:0 16px 35px rgba(0,0,0,.18);
}

.tx-filter-row{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    align-items:center;
}

.tx-filter-row input,
.tx-filter-row select{
    background:#1b2324;
    color:#eef5f2;
    border:1px solid #354344;
    border-radius:9px;
    padding:10px 11px;
    min-height:40px;
}

.tx-filter-row input::placeholder{
    color:#73807d;
}

.tx-filter-row button{
    background:#45d993;
    color:#08120d;
    border:0;
    border-radius:9px;
    padding:10px 15px;
    font-weight:900;
    cursor:pointer;
}

.tx-periods{
    display:flex;
    gap:7px;
    flex-wrap:wrap;
    margin-top:10px;
}

.tx-periods a{
    text-decoration:none;
    color:#b6c2bf;
    background:#182021;
    border:1px solid #303d3e;
    border-radius:8px;
    padding:8px 11px;
    font-size:12px;
    font-weight:800;
}

.tx-periods a:hover,
.tx-periods a.active{
    color:#72f0b1;
    border-color:#45d993;
    background:#20302b;
}

.tx-kpis{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
    margin-bottom:18px;
}

.tx-card{
    background:linear-gradient(145deg,#151d1e,#101617);
    border:1px solid #293536;
    border-radius:16px;
    box-shadow:0 16px 35px rgba(0,0,0,.20);
}

.tx-kpi{
    min-height:135px;
    padding:19px;
    position:relative;
    overflow:hidden;
}

.tx-kpi::after{
    content:"";
    position:absolute;
    width:100px;
    height:100px;
    right:-35px;
    bottom:-45px;
    border-radius:50%;
    background:currentColor;
    opacity:.05;
}

.tx-kpi.income{
    border-top:4px solid #45d993;
    color:#55e7aa;
}

.tx-kpi.expense{
    border-top:4px solid #ff6571;
    color:#ff707c;
}

.tx-kpi.net{
    border-top:4px solid #5b8cff;
    color:#6fa0ff;
}

.tx-kpi.count{
    border-top:4px solid #b56cff;
    color:#c084fc;
}

.tx-kpi-label{
    color:#aab6b3;
    font-size:11px;
    font-weight:900;
    letter-spacing:.12em;
}

.tx-kpi-value{
    margin-top:10px;
    font-size:25px;
    font-weight:900;
    letter-spacing:-.03em;
}

.tx-kpi.income .tx-kpi-value{color:#55e7aa}
.tx-kpi.expense .tx-kpi-value{color:#ff707c}
.tx-kpi.net .tx-kpi-value{color:#6fa0ff}
.tx-kpi.count .tx-kpi-value{color:#c084fc}

.tx-kpi-note{
    margin-top:8px;
    color:#87938f;
    font-size:12px;
}

.tx-table-card{
    overflow:hidden;
}

.tx-table-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    padding:18px 20px;
    border-bottom:1px solid #293536;
}

.tx-table-head h2{
    margin:0;
    color:#f5f7f6;
    font-size:18px;
}

.tx-table-head p{
    margin:4px 0 0;
    color:#87938f;
    font-size:12px;
}

.tx-table-wrap{
    overflow-x:auto;
}

.tx-table{
    width:100%;
    border-collapse:collapse;
    min-width:920px;
}

.tx-table th,
.tx-table td{
    padding:13px 15px;
    border-bottom:1px solid #263233;
    text-align:left;
    font-size:13px;
}

.tx-table th{
    background:#182021;
    color:#899692;
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:.10em;
    white-space:nowrap;
}

.tx-table td{
    color:#dce5e2;
}

.tx-table tbody tr:hover{
    background:#172122;
}

.tx-date{
    white-space:nowrap;
}

.tx-date strong{
    display:block;
    color:#eaf1ef;
}

.tx-date small{
    color:#73807d;
}

.tx-type{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:5px 8px;
    border-radius:999px;
    font-size:10px;
    font-weight:900;
    white-space:nowrap;
}

.tx-type.income{
    color:#61efb0;
    background:rgba(69,217,147,.10);
    border:1px solid rgba(69,217,147,.22);
}

.tx-type.expense{
    color:#ff7c86;
    background:rgba(255,101,113,.10);
    border:1px solid rgba(255,101,113,.22);
}

.tx-description{
    font-weight:800;
    color:#eef5f2;
}

.tx-customer{
    margin-top:3px;
    color:#75827f;
    font-size:11px;
}

.tx-method{
    color:#aebbb7;
    font-size:12px;
}

.tx-amount{
    text-align:right !important;
    font-weight:900;
    white-space:nowrap;
}

.tx-amount.income{
    color:#55e7aa;
}

.tx-amount.expense{
    color:#ff707c;
}

.tx-ref{
    color:#74817e;
    font-size:11px;
    max-width:150px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.tx-empty{
    text-align:center;
    padding:55px 20px !important;
    color:#8a9793 !important;
}

.tx-empty-icon{
    font-size:36px;
    margin-bottom:8px;
}

.tx-footer{
    padding:15px 20px;
    border-top:1px solid #293536;
    color:#7f8c89;
    font-size:12px;
}

.tx-footer strong{
    color:#cdd8d5;
}


.tx-insights{
    display:grid;
    grid-template-columns:minmax(0,1.7fr) minmax(320px,1fr);
    gap:14px;
    margin:18px 0;
}

.tx-insight-card{
    padding:18px;
    overflow:hidden;
}

.tx-insight-title{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    margin-bottom:14px;
}

.tx-insight-title h3{
    margin:0;
    color:#f5f7f6;
    font-size:16px;
}

.tx-insight-title span{
    color:#7f8c89;
    font-size:11px;
}

.tx-daily-chart{
    display:flex;
    gap:8px;
    align-items:flex-end;
    min-height:190px;
    padding:8px 2px 0;
    overflow-x:auto;
}

.tx-day{
    min-width:28px;
    flex:1 0 28px;
    max-width:55px;
    height:180px;
    display:flex;
    flex-direction:column;
    justify-content:flex-end;
    align-items:center;
    gap:4px;
}

.tx-bars{
    width:100%;
    height:145px;
    display:flex;
    justify-content:center;
    align-items:flex-end;
    gap:3px;
}

.tx-bar{
    width:8px;
    min-height:2px;
    border-radius:5px 5px 2px 2px;
}

.tx-bar.income{background:#45d993}
.tx-bar.expense{background:#ff6571}

.tx-day-label{
    color:#76837f;
    font-size:9px;
    white-space:nowrap;
}

.tx-day-values{
    font-size:8px;
    color:#93a09c;
    white-space:nowrap;
}

.tx-legend{
    display:flex;
    gap:14px;
    margin-top:9px;
    color:#899692;
    font-size:10px;
}

.tx-legend span{
    display:inline-flex;
    align-items:center;
    gap:5px;
}

.tx-dot{
    width:7px;
    height:7px;
    border-radius:50%;
}
.tx-dot.income{background:#45d993}
.tx-dot.expense{background:#ff6571}

.tx-method-list{
    display:flex;
    flex-direction:column;
    gap:11px;
}

.tx-method-row{
    display:grid;
    grid-template-columns:120px 1fr auto;
    gap:10px;
    align-items:center;
}

.tx-method-name{
    color:#b9c5c2;
    font-size:11px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.tx-method-track{
    height:8px;
    background:#202a2b;
    border-radius:999px;
    overflow:hidden;
}

.tx-method-fill{
    height:100%;
    background:#5b8cff;
    border-radius:999px;
}

.tx-method-value{
    color:#e2eae7;
    font-size:11px;
    font-weight:900;
    white-space:nowrap;
}

.tx-summary-strip{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
    margin:0 0 18px;
}

.tx-mini{
    padding:13px 15px;
    background:#141c1d;
    border:1px solid #293536;
    border-radius:12px;
}

.tx-mini-label{
    color:#7e8b87;
    font-size:10px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.08em;
}

.tx-mini-value{
    margin-top:6px;
    color:#eaf1ef;
    font-weight:900;
    font-size:16px;
}

.tx-detail-btn{
    border:1px solid #344142;
    background:#1b2425;
    color:#b9c6c2;
    border-radius:7px;
    padding:6px 8px;
    cursor:pointer;
    font-size:10px;
    font-weight:900;
}

.tx-detail-btn:hover{
    border-color:#45d993;
    color:#72f0b1;
}

.tx-modal{
    position:fixed;
    inset:0;
    z-index:99999;
    display:none;
    align-items:center;
    justify-content:center;
    padding:20px;
    background:rgba(0,0,0,.72);
    backdrop-filter:blur(4px);
}

.tx-modal.show{display:flex}

.tx-modal-box{
    width:min(620px,100%);
    max-height:90vh;
    overflow:auto;
    background:#11191a;
    border:1px solid #354344;
    border-radius:18px;
    box-shadow:0 25px 80px rgba(0,0,0,.45);
}

.tx-modal-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:17px 19px;
    border-bottom:1px solid #293536;
}

.tx-modal-head h3{
    margin:0;
    color:#f4f8f6;
    font-size:17px;
}

.tx-modal-close{
    border:0;
    background:#202a2b;
    color:#c9d4d1;
    width:34px;
    height:34px;
    border-radius:9px;
    cursor:pointer;
    font-size:18px;
}

.tx-detail-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
    padding:18px;
}

.tx-detail-item{
    background:#182122;
    border:1px solid #283536;
    border-radius:10px;
    padding:11px;
}

.tx-detail-item.full{grid-column:1/-1}

.tx-detail-label{
    color:#74817e;
    font-size:9px;
    text-transform:uppercase;
    letter-spacing:.1em;
    font-weight:900;
}

.tx-detail-value{
    margin-top:5px;
    color:#e5ece9;
    font-size:13px;
    font-weight:700;
    word-break:break-word;
}

@media(max-width:900px){
    .tx-insights{
        grid-template-columns:1fr;
    }
}

@media(max-width:1050px){
    .tx-kpis{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:700px){
    .tx-summary-strip{
        grid-template-columns:1fr;
    }

    .tx-detail-grid{
        grid-template-columns:1fr;
    }

    .tx-detail-item.full{
        grid-column:auto;
    }

    .tx-method-row{
        grid-template-columns:105px 1fr auto;
    }

    .tx-head{
        display:block;
    }

    .tx-actions{
        margin-top:14px;
    }

    .tx-kpis{
        grid-template-columns:1fr;
    }

    .tx-title{
        font-size:27px;
    }

    .tx-filter-row input[type="text"]{
        width:100%;
    }
}

@media print{
    body{
        background:#fff !important;
    }

    .tx-filters,
    .tx-actions,
    .tx-periods{
        display:none !important;
    }

    .tx-card,
    .tx-table-card{
        background:#fff !important;
        color:#111 !important;
        box-shadow:none !important;
        border:1px solid #ccc !important;
    }

    .tx-card *,
    .tx-table-card *{
        color:#111 !important;
    }
}
</style>

<div class="tx-wrap">

    <div class="tx-head">
        <div>
            <h1 class="tx-title">💰 Financial Transactions</h1>
            <p class="tx-sub">
                <?= e($periodLabel) ?> · All money received and money spent
            </p>
        </div>

        <div class="tx-actions">
            <a href="sales.php">🛒 Sales</a>
            <a href="expenses.php">💸 Expenses</a>
            <a href="<?= e($_SERVER['REQUEST_URI'] . (str_contains((string)$_SERVER['REQUEST_URI'], '?') ? '&' : '?') . 'export=csv') ?>">⬇ CSV</a>
            <button type="button" onclick="window.print()">🖨 Print</button>
        </div>
    </div>

    <form method="get" class="tx-filters">

        <div class="tx-filter-row">

            <input
                type="text"
                name="q"
                value="<?= e($search) ?>"
                placeholder="Search customer, booking, product, reference..."
            >

            <select name="type">
                <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>
                    All Transactions
                </option>
                <option value="income" <?= $type === 'income' ? 'selected' : '' ?>>
                    Income Only
                </option>
                <option value="expense" <?= $type === 'expense' ? 'selected' : '' ?>>
                    Expenses Only
                </option>
            </select>

            <select name="method">
                <option value="all">All Payment Methods</option>
                <?php foreach ($methods as $m): ?>
                    <option
                        value="<?= e($m) ?>"
                        <?= $method === $m ? 'selected' : '' ?>
                    >
                        <?= e(txMethodLabel($m)) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="from" value="<?= e($from) ?>">
            <input type="date" name="to" value="<?= e($to) ?>">

            <input type="hidden" name="period" value="custom">

            <button type="submit">Apply Filters</button>
        </div>

        <div class="tx-periods">
            <a
                href="transactions.php?period=today"
                class="<?= $period === 'today' ? 'active' : '' ?>"
            >Today</a>

            <a
                href="transactions.php?period=week"
                class="<?= $period === 'week' ? 'active' : '' ?>"
            >This Week</a>

            <a
                href="transactions.php?period=month"
                class="<?= $period === 'month' ? 'active' : '' ?>"
            >This Month</a>

            <a
                href="transactions.php?period=year"
                class="<?= $period === 'year' ? 'active' : '' ?>"
            >This Year</a>
        </div>

    </form>

    <div class="tx-kpis">

        <div class="tx-card tx-kpi income">
            <div class="tx-kpi-label">MONEY IN</div>
            <div class="tx-kpi-value"><?= e(txMoney($totalIncome)) ?></div>
            <div class="tx-kpi-note">
                <?= e((string)$incomeCount) ?> income transaction(s)
            </div>
        </div>

        <div class="tx-card tx-kpi expense">
            <div class="tx-kpi-label">MONEY OUT</div>
            <div class="tx-kpi-value"><?= e(txMoney($totalExpenses)) ?></div>
            <div class="tx-kpi-note">
                <?= e((string)$expenseCount) ?> expense transaction(s)
            </div>
        </div>

        <div class="tx-card tx-kpi net">
            <div class="tx-kpi-label">NET CASH</div>
            <div class="tx-kpi-value"><?= e(txMoney($netCash)) ?></div>
            <div class="tx-kpi-note">
                Money received minus recorded expenses
            </div>
        </div>

        <div class="tx-card tx-kpi count">
            <div class="tx-kpi-label">TRANSACTIONS</div>
            <div class="tx-kpi-value"><?= e(number_format($transactionCount)) ?></div>
            <div class="tx-kpi-note">
                <?= e($periodLabel) ?>
            </div>
        </div>

    </div>


    <div class="tx-summary-strip">
        <div class="tx-mini">
            <div class="tx-mini-label">Average Income Transaction</div>
            <div class="tx-mini-value">
                <?= e(txMoney($incomeCount > 0 ? $totalIncome / $incomeCount : 0)) ?>
            </div>
        </div>
        <div class="tx-mini">
            <div class="tx-mini-label">Average Expense Transaction</div>
            <div class="tx-mini-value">
                <?= e(txMoney($expenseCount > 0 ? $totalExpenses / $expenseCount : 0)) ?>
            </div>
        </div>
        <div class="tx-mini">
            <div class="tx-mini-label">Income Sources</div>
            <div class="tx-mini-value">
                <?= e((string)count($sourceTotals)) ?> sources
            </div>
        </div>
    </div>

    <div class="tx-insights">
        <section class="tx-card tx-insight-card">
            <div class="tx-insight-title">
                <h3>📊 Daily Cash Flow</h3>
                <span>Last <?= e((string)count($dailyRows)) ?> day(s) in selected period</span>
            </div>

            <?php if (!$dailyRows): ?>
                <div class="tx-empty">No daily data available.</div>
            <?php else: ?>
                <div class="tx-daily-chart">
                    <?php foreach ($dailyRows as $day => $dayData): ?>
                        <?php
                        $incomeHeight = min(100, ((float)$dayData['income'] / $maxDaily) * 100);
                        $expenseHeight = min(100, ((float)$dayData['expense'] / $maxDaily) * 100);
                        ?>
                        <div class="tx-day" title="<?= e($day) ?>">
                            <div class="tx-bars">
                                <div class="tx-bar income" style="height:<?= e((string)$incomeHeight) ?>%"></div>
                                <div class="tx-bar expense" style="height:<?= e((string)$expenseHeight) ?>%"></div>
                            </div>
                            <div class="tx-day-values">
                                <?= e(number_format((float)$dayData['income']/1000, 0)) ?>k / <?= e(number_format((float)$dayData['expense']/1000, 0)) ?>k
                            </div>
                            <div class="tx-day-label"><?= e(date('d M', strtotime($day))) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="tx-legend">
                    <span><i class="tx-dot income"></i> Income</span>
                    <span><i class="tx-dot expense"></i> Expense</span>
                </div>
            <?php endif; ?>
        </section>

        <section class="tx-card tx-insight-card">
            <div class="tx-insight-title">
                <h3>💳 Income by Payment Method</h3>
                <span>Collected income</span>
            </div>

            <?php if (!$methodTotals): ?>
                <div class="tx-empty">No payment method data available.</div>
            <?php else: ?>
                <div class="tx-method-list">
                    <?php foreach ($methodTotals as $mData): ?>
                        <?php $methodPct = min(100, ((float)$mData['amount'] / $maxMethod) * 100); ?>
                        <div class="tx-method-row">
                            <div class="tx-method-name"><?= e((string)$mData['label']) ?></div>
                            <div class="tx-method-track">
                                <div class="tx-method-fill" style="width:<?= e((string)$methodPct) ?>%"></div>
                            </div>
                            <div class="tx-method-value"><?= e(txMoney((float)$mData['amount'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <section class="tx-card tx-table-card">

        <div class="tx-table-head">
            <div>
                <h2>Recent Financial Activity</h2>
                <p>
                    Booking payments + product sales + expenses
                </p>
            </div>
        </div>

        <div class="tx-table-wrap">

            <table class="tx-table">

                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Payment Method</th>
                        <th>Reference</th>
                        <th style="text-align:right">Amount</th>
                        <th>Details</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (!$transactions): ?>

                    <tr>
                        <td colspan="7" class="tx-empty">
                            <div class="tx-empty-icon">📭</div>
                            <strong>No transactions found</strong><br>
                            Try another date range or search.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($transactions as $tx): ?>

                        <?php
                        $isIncome = $tx['type'] === 'income';
                        $sign = $isIncome ? '+' : '−';
                        ?>

                        <tr>

                            <td class="tx-date">
                                <strong><?= e(txSafeDate((string)$tx['date'])) ?></strong>
                                <?php if (txSafeTime((string)$tx['date']) !== ''): ?>
                                    <small><?= e(txSafeTime((string)$tx['date'])) ?></small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="tx-type <?= $isIncome ? 'income' : 'expense' ?>">
                                    <?= $isIncome ? '↗ Income' : '↘ Expense' ?>
                                </span>
                            </td>

                            <td>
                                <div class="tx-description">
                                    <?= e((string)$tx['description']) ?>
                                </div>

                                <?php if ((string)$tx['customer'] !== ''): ?>
                                    <div class="tx-customer">
                                        <?= e((string)$tx['customer']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="tx-method">
                                <?php if ($tx['method'] !== ''): ?>
                                    <?= e(txMethodLabel((string)$tx['method'])) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($tx['reference'] !== ''): ?>
                                    <div class="tx-ref" title="<?= e((string)$tx['reference']) ?>">
                                        <?= e((string)$tx['reference']) ?>
                                    </div>
                                <?php elseif ($tx['provider'] !== ''): ?>
                                    <div class="tx-ref" title="<?= e((string)$tx['provider']) ?>">
                                        <?= e((string)$tx['provider']) ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color:#52605d">—</span>
                                <?php endif; ?>
                            </td>

                            <td class="tx-amount <?= $isIncome ? 'income' : 'expense' ?>">
                                <?= $sign ?> <?= e(txMoney((float)$tx['amount'])) ?>
                            </td>

                            <td>
                                <button
                                    type="button"
                                    class="tx-detail-btn"
                                    onclick="openTxDetail(this)"
                                    data-date="<?= e((string)$tx['date']) ?>"
                                    data-type="<?= e(ucfirst((string)$tx['type'])) ?>"
                                    data-source="<?= e((string)$tx['source']) ?>"
                                    data-description="<?= e((string)$tx['description']) ?>"
                                    data-customer="<?= e((string)$tx['customer']) ?>"
                                    data-method="<?= e(txMethodLabel((string)$tx['method'])) ?>"
                                    data-reference="<?= e((string)$tx['reference']) ?>"
                                    data-provider="<?= e((string)$tx['provider']) ?>"
                                    data-amount="<?= e(txMoney((float)$tx['amount'])) ?>"
                                    data-notes="<?= e((string)$tx['notes']) ?>"
                                >
                                    View
                                </button>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <div class="tx-footer">
            Showing <strong><?= e(number_format($transactionCount)) ?></strong>
            transaction(s) for <strong><?= e($periodLabel) ?></strong>.
            Income uses successful booking payments and sales actually collected;
            expenses use recorded expenses.
        </div>

    </section>

</div>


<div class="tx-modal" id="txDetailModal" onclick="closeTxDetail(event)">
    <div class="tx-modal-box" onclick="event.stopPropagation()">
        <div class="tx-modal-head">
            <h3>Transaction Details</h3>
            <button type="button" class="tx-modal-close" onclick="closeTxDetail()">×</button>
        </div>

        <div class="tx-detail-grid">
            <div class="tx-detail-item">
                <div class="tx-detail-label">Date</div>
                <div class="tx-detail-value" id="txd-date">—</div>
            </div>
            <div class="tx-detail-item">
                <div class="tx-detail-label">Type</div>
                <div class="tx-detail-value" id="txd-type">—</div>
            </div>
            <div class="tx-detail-item">
                <div class="tx-detail-label">Source</div>
                <div class="tx-detail-value" id="txd-source">—</div>
            </div>
            <div class="tx-detail-item">
                <div class="tx-detail-label">Amount</div>
                <div class="tx-detail-value" id="txd-amount">—</div>
            </div>
            <div class="tx-detail-item full">
                <div class="tx-detail-label">Description</div>
                <div class="tx-detail-value" id="txd-description">—</div>
            </div>
            <div class="tx-detail-item">
                <div class="tx-detail-label">Customer / Staff</div>
                <div class="tx-detail-value" id="txd-customer">—</div>
            </div>
            <div class="tx-detail-item">
                <div class="tx-detail-label">Payment Method</div>
                <div class="tx-detail-value" id="txd-method">—</div>
            </div>
            <div class="tx-detail-item">
                <div class="tx-detail-label">Reference</div>
                <div class="tx-detail-value" id="txd-reference">—</div>
            </div>
            <div class="tx-detail-item">
                <div class="tx-detail-label">Provider Reference</div>
                <div class="tx-detail-value" id="txd-provider">—</div>
            </div>
            <div class="tx-detail-item full">
                <div class="tx-detail-label">Notes</div>
                <div class="tx-detail-value" id="txd-notes">—</div>
            </div>
        </div>
    </div>
</div>

<script>
function txSet(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value && value.trim() !== '' ? value : '—';
}

function openTxDetail(button) {
    txSet('txd-date', button.dataset.date || '');
    txSet('txd-type', button.dataset.type || '');
    txSet('txd-source', button.dataset.source || '');
    txSet('txd-description', button.dataset.description || '');
    txSet('txd-customer', button.dataset.customer || '');
    txSet('txd-method', button.dataset.method || '');
    txSet('txd-reference', button.dataset.reference || '');
    txSet('txd-provider', button.dataset.provider || '');
    txSet('txd-amount', button.dataset.amount || '');
    txSet('txd-notes', button.dataset.notes || '');

    document.getElementById('txDetailModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeTxDetail(event) {
    if (event && event.target && event.target.id !== 'txDetailModal') return;
    const modal = document.getElementById('txDetailModal');
    if (modal) modal.classList.remove('show');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') closeTxDetail();
});
</script>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
