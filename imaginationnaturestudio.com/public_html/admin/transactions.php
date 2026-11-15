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
require_admin_only();

$pdo = db();

// Avoid counting sale-linked payment rows twice when sales.amount_paid is also reported.
$txPaymentSaleFilter = '';
try {
    $txPaymentColumns = $pdo->query("SHOW COLUMNS FROM payments")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('sale_id', $txPaymentColumns, true)) {
        $txPaymentSaleFilter = ' AND (p.sale_id IS NULL OR p.sale_id = 0)';
    }
} catch (Throwable $e) {
    $txPaymentSaleFilter = '';
}

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

/** Return table columns safely, so this page tolerates schema differences. */
function txColumns(PDO $pdo, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    // Table names are internal constants only; reject anything unexpected.
    if (!in_array($table, ['payments', 'bookings', 'customers', 'sales', 'expenses'], true)) {
        return [];
    }
    try {
        $cache[$table] = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        $cache[$table] = [];
    }
    return $cache[$table];
}

function txFirstColumn(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }
    return null;
}

function txSqlColumn(string $alias, ?string $column, string $fallback = "''"): string
{
    return $column !== null ? "$alias.`$column`" : $fallback;
}

/* =========================================================
   BUILD TRANSACTION LIST
   ========================================================= */

$transactions = [];

$searchLike = '%' . $search . '%';

/* ---------- Booking payments ---------- */
if ($type === 'all' || $type === 'income') {

    // Detect the actual customer-name column used by this website's database.
    // Some installations use full_name/customer_name/first_name instead of name.
    $customerColumns = [];
    $bookingColumns = [];
    try {
        $customerColumns = $pdo->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        $customerColumns = [];
    }
    try {
        $bookingColumns = $pdo->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        $bookingColumns = [];
    }

    $customerJoin = '';
    $customerNameExpr = "''";
    if (in_array('customer_id', $bookingColumns, true) && in_array('id', $customerColumns, true)) {
        $customerJoin = ' LEFT JOIN customers c ON c.id = b.customer_id ';
        foreach (['name', 'full_name', 'customer_name', 'first_name', 'firstname', 'fullName'] as $candidate) {
            if (in_array($candidate, $customerColumns, true)) {
                $customerNameExpr = "COALESCE(c.`" . $candidate . "`, '')";
                break;
            }
        }
        if (in_array('first_name', $customerColumns, true) && in_array('last_name', $customerColumns, true)) {
            $customerNameExpr = "TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,'')))";
        } elseif (in_array('firstname', $customerColumns, true) && in_array('lastname', $customerColumns, true)) {
            $customerNameExpr = "TRIM(CONCAT(COALESCE(c.firstname,''), ' ', COALESCE(c.lastname,'')))";
        }
    }

    $bookingNumberExpr = in_array('booking_number', $bookingColumns, true)
        ? "COALESCE(b.booking_number, '')" : "''";
    $bookingJoin = in_array('booking_id', $txPaymentColumns, true)
        ? ' LEFT JOIN bookings b ON b.id = p.booking_id '
        : ' LEFT JOIN bookings b ON 1 = 0 ';

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
            $bookingNumberExpr AS booking_number,
            $customerNameExpr AS customer_name
        FROM payments p
        $bookingJoin
        $customerJoin
        WHERE p.payment_status = 'successful'
          AND DATE(p.payment_date) BETWEEN ? AND ?
          " . $txPaymentSaleFilter . "
    ";

    $args = [$from, $to];

    if ($method !== '' && $method !== 'all') {
        $sql .= " AND LOWER(COALESCE(p.payment_method,'')) = ?";
        $args[] = $method;
    }

    if ($search !== '') {
        $sql .= "
            AND (
                $bookingNumberExpr LIKE ?
                OR $customerNameExpr LIKE ?
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
    $salesColumns = txColumns($pdo, 'sales');
    $saleIdCol = txFirstColumn($salesColumns, ['id']);
    $saleDateCol = txFirstColumn($salesColumns, ['sale_date', 'created_at', 'date']);
    $saleAmountCol = txFirstColumn($salesColumns, ['amount_paid', 'paid_amount', 'total_amount', 'amount']);
    if ($saleIdCol !== null && $saleDateCol !== null && $saleAmountCol !== null) {
        $saleMethod = txFirstColumn($salesColumns, ['payment_method']);
        $saleNotes = txFirstColumn($salesColumns, ['notes', 'customer_notes']);
        $saleProduct = txFirstColumn($salesColumns, ['product_name', 'item_name', 'product']);
        $saleCustomer = txFirstColumn($salesColumns, ['customer_name', 'client_name', 'name']);
        $salePhone = txFirstColumn($salesColumns, ['customer_phone', 'phone', 'client_phone']);
        $saleDescription = txFirstColumn($salesColumns, ['description', 'details']);

        $productExpr = 'COALESCE(' . txSqlColumn('s', $saleProduct, "''") . ", '')";
        $customerExpr = 'COALESCE(' . txSqlColumn('s', $saleCustomer, "''") . ", '')";
        $phoneExpr = 'COALESCE(' . txSqlColumn('s', $salePhone, "''") . ", '')";
        $descriptionExpr = 'COALESCE(' . txSqlColumn('s', $saleDescription, "''") . ", '')";
        $notesExpr = 'COALESCE(' . txSqlColumn('s', $saleNotes, "''") . ", '')";
        $methodExpr = 'COALESCE(' . txSqlColumn('s', $saleMethod, "''") . ", '')";

        $sql = "
            SELECT s.`$saleIdCol` AS id,
                   s.`$saleDateCol` AS tx_date,
                   'income' AS tx_type,
                   'Product Sale' AS tx_source,
                   COALESCE(s.`$saleAmountCol`, 0) AS amount,
                   $methodExpr AS payment_method,
                   '' AS reference,
                   '' AS provider_reference,
                   $notesExpr AS notes,
                   $productExpr AS product_name,
                   $customerExpr AS customer_name,
                   $phoneExpr AS customer_phone,
                   $descriptionExpr AS description
            FROM sales s
            WHERE DATE(s.`$saleDateCol`) BETWEEN ? AND ?
        ";
        $args = [$from, $to];

        if ($method !== '' && $method !== 'all' && $saleMethod !== null) {
            $sql .= " AND LOWER(COALESCE(s.`$saleMethod`,'')) = ?";
            $args[] = $method;
        }
        if ($search !== '') {
            $sql .= " AND ($customerExpr LIKE ? OR $phoneExpr LIKE ? OR $productExpr LIKE ? OR $descriptionExpr LIKE ? OR $notesExpr LIKE ?)";
            array_push($args, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
        }
        $sql .= " ORDER BY s.`$saleDateCol` DESC, s.`$saleIdCol` DESC LIMIT 1000";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $description = $row['product_name'] !== '' ? 'Sale — ' . $row['product_name'] : 'Product Sale';
            if ($row['description'] !== '') {
                $description .= ' · ' . $row['description'];
            }
            $transactions[] = [
                'id' => (int)$row['id'],
                'date' => (string)$row['tx_date'],
                'type' => 'income',
                'source' => 'Product Sale',
                'description' => $description,
                'customer' => (string)$row['customer_name'],
                'method' => (string)$row['payment_method'],
                'amount' => (float)$row['amount'],
                'reference' => '',
                'provider' => '',
                'notes' => (string)$row['notes'],
            ];
        }
    }
}

/* ---------- Expenses ---------- */
if ($type === 'all' || $type === 'expense') {
    $expenseColumns = txColumns($pdo, 'expenses');
    $expenseIdCol = txFirstColumn($expenseColumns, ['id']);
    $expenseDateCol = txFirstColumn($expenseColumns, ['expense_date', 'created_at', 'date']);
    $expenseAmountCol = txFirstColumn($expenseColumns, ['amount', 'total_amount']);
    if ($expenseIdCol !== null && $expenseDateCol !== null && $expenseAmountCol !== null) {
        $expenseNotes = txFirstColumn($expenseColumns, ['notes', 'description']);
        $expenseDescription = txFirstColumn($expenseColumns, ['description', 'title', 'expense_name']);
        $expenseCategory = txFirstColumn($expenseColumns, ['category', 'expense_category']);
        $expenseStaff = txFirstColumn($expenseColumns, ['staff_name', 'employee_name', 'paid_to']);

        $expNotesExpr = 'COALESCE(' . txSqlColumn('e', $expenseNotes, "''") . ", '')";
        $expDescriptionExpr = 'COALESCE(' . txSqlColumn('e', $expenseDescription, "''") . ", '')";
        $expCategoryExpr = 'COALESCE(' . txSqlColumn('e', $expenseCategory, "''") . ", '')";
        $expStaffExpr = 'COALESCE(' . txSqlColumn('e', $expenseStaff, "''") . ", '')";

        $sql = "
            SELECT e.`$expenseIdCol` AS id,
                   e.`$expenseDateCol` AS tx_date,
                   'expense' AS tx_type,
                   'Expense' AS tx_source,
                   COALESCE(e.`$expenseAmountCol`, 0) AS amount,
                   '' AS payment_method,
                   '' AS reference,
                   '' AS provider_reference,
                   $expNotesExpr AS notes,
                   $expDescriptionExpr AS description,
                   $expCategoryExpr AS category,
                   $expStaffExpr AS staff_name
            FROM expenses e
            WHERE DATE(e.`$expenseDateCol`) BETWEEN ? AND ?
        ";
        $args = [$from, $to];
        if ($search !== '') {
            $sql .= " AND ($expDescriptionExpr LIKE ? OR $expCategoryExpr LIKE ? OR $expStaffExpr LIKE ? OR $expNotesExpr LIKE ?)";
            array_push($args, $searchLike, $searchLike, $searchLike, $searchLike);
        }
        $sql .= " ORDER BY e.`$expenseDateCol` DESC, e.`$expenseIdCol` DESC LIMIT 1000";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $description = (string)$row['description'];
            if ($row['category'] !== '') {
                $description .= ' · ' . ucwords(str_replace('_', ' ', $row['category']));
            }
            $transactions[] = [
                'id' => (int)$row['id'],
                'date' => (string)$row['tx_date'],
                'type' => 'expense',
                'source' => 'Expense',
                'description' => $description,
                'customer' => (string)$row['staff_name'],
                'method' => '',
                'amount' => (float)$row['amount'],
                'reference' => '',
                'provider' => '',
                'notes' => (string)$row['notes'],
            ];
        }
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
      " . str_replace('p.', '', $txPaymentSaleFilter) . "
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

@media(max-width:1050px){
    .tx-kpis{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:700px){
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
            <a href="finance-planning.php">📅 Planning &amp; Reserves</a>
            <a href="profit-loss.php">📊 Profit &amp; Loss</a>
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
                    </tr>
                </thead>

                <tbody>

                <?php if (!$transactions): ?>

                    <tr>
                        <td colspan="6" class="tx-empty">
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

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
