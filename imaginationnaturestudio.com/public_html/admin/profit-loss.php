<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_admin_only();

$pdo = db();

$pageTitle = 'Profit & Loss';

/*
|--------------------------------------------------------------------------
| PROFIT & LOSS
|--------------------------------------------------------------------------
| This report follows the same CASH-BASED financial logic used by the
| current Imagination Nature Studio dashboard:
|
|   Income  = successful booking payments + sales amount_paid
|   COGS     = FIFO cost of tracked stock sold
|   Expenses = recorded expenses
|   Net      = Income - COGS - Expenses
|
| Important:
| This is a management/cash-based P&L, not accrual accounting.
|--------------------------------------------------------------------------
*/

/* =========================================================
   DATE FILTER
   ========================================================= */

$period = $_GET['period'] ?? 'month';

$allowedPeriods = ['today', 'week', 'month', 'year', 'custom'];

if (!in_array($period, $allowedPeriods, true)) {
    $period = 'month';
}

$today = date('Y-m-d');

switch ($period) {
    case 'today':
        $startDate   = $today;
        $endDate     = $today;
        $periodLabel = 'Today';
        break;

    case 'week':
        $startDate   = date('Y-m-d', strtotime('monday this week'));
        $endDate     = $today;
        $periodLabel = 'This Week';
        break;

    case 'year':
        $startDate   = date('Y-01-01');
        $endDate     = $today;
        $periodLabel = 'This Year';
        break;

    case 'custom':
        $startDate = trim((string)($_GET['from'] ?? ''));
        $endDate   = trim((string)($_GET['to'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = date('Y-m-01');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $endDate = $today;
        }

        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $periodLabel = date('d M Y', strtotime($startDate))
            . ' — '
            . date('d M Y', strtotime($endDate));
        break;

    default:
        $startDate   = date('Y-m-01');
        $endDate     = $today;
        $periodLabel = 'This Month';
        break;
}

/* =========================================================
   HELPERS
   ========================================================= */

function plMoney(float $amount): string
{
    return number_format($amount, 0) . ' RWF';
}

function plPct(float $value): string
{
    return number_format($value, 1) . '%';
}

function plPercentOf(float $part, float $whole): float
{
    return $whole > 0 ? ($part / $whole) * 100 : 0;
}

/* =========================================================
   SHARED FINANCE RULES
   ========================================================= */

// Some installations store sale receipts in payments as well as sales.amount_paid.
// If sale_id exists, exclude those rows from booking income to avoid double counting.
$plPaymentSaleFilter = '';
try {
    $plPaymentColumns = $pdo->query("SHOW COLUMNS FROM payments")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('sale_id', $plPaymentColumns, true)) {
        $plPaymentSaleFilter = ' AND (sale_id IS NULL OR sale_id = 0)';
    }
} catch (Throwable $e) {
    $plPaymentSaleFilter = '';
}

// Current-month reserve summary is informational only; unpaid plans are not expenses.
$plReserveMonth = date('Y-m');
$plReservePlanned = 0.0;
$plReservePaid = 0.0;
$plReserveRemaining = 0.0;
try {
    $plObligationTableExists = (bool)$pdo->query("SHOW TABLES LIKE 'finance_monthly_obligations'")->fetchColumn();
    $plPaymentTableExists = (bool)$pdo->query("SHOW TABLES LIKE 'finance_obligation_payments'")->fetchColumn();
    if ($plObligationTableExists && $plPaymentTableExists) {
        $plReserveStmt = $pdo->prepare("SELECT o.monthly_amount, COALESCE(p.paid,0) AS paid
            FROM finance_monthly_obligations o
            LEFT JOIN (SELECT obligation_id, SUM(amount) AS paid FROM finance_obligation_payments WHERE obligation_month = ? GROUP BY obligation_id) p ON p.obligation_id=o.id
            WHERE o.active=1");
        $plReserveStmt->execute([$plReserveMonth]);
        foreach ($plReserveStmt->fetchAll(PDO::FETCH_ASSOC) as $plReserveRow) {
            $plReservePlanned += (float)$plReserveRow['monthly_amount'];
            $plReservePaid += (float)$plReserveRow['paid'];
            $plReserveRemaining += max(0, (float)$plReserveRow['monthly_amount'] - (float)$plReserveRow['paid']);
        }
    }
} catch (Throwable $e) {
    // Planning tables may not exist until finance-planning.php has been opened once.
}

/* =========================================================
   INCOME
   ========================================================= */

/* Booking payments */
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM payments
    WHERE payment_status = 'successful'
      AND DATE(payment_date) BETWEEN ? AND ?
      " . $plPaymentSaleFilter . "
");
$stmt->execute([$startDate, $endDate]);
$bookingPayments = (float)$stmt->fetchColumn();

/* Product/service sales actually collected */
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(total), 0) AS sales_value,
        COALESCE(SUM(amount_paid), 0) AS sales_collected,
        COALESCE(SUM(balance), 0) AS sales_balance
    FROM sales
    WHERE sale_date BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$salesRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$salesValue     = (float)($salesRow['sales_value'] ?? 0);
$salesCollected = (float)($salesRow['sales_collected'] ?? 0);
$salesBalance   = (float)($salesRow['sales_balance'] ?? 0);

$totalIncome = $bookingPayments + $salesCollected;

/* =========================================================
   COST OF GOODS SOLD — FIFO + recorded supplier unit cost fallback
   ========================================================= */
$cogs = 0.0;
$grossProfit = $totalIncome;
$plStockAllocationsExist = false;
$plSalesHasSupplierCost = false;

try {
    $plStockAllocationsExist = (bool)$pdo->query("SHOW TABLES LIKE 'stock_sale_allocations'")->fetchColumn();

    // Detect optional column safely: older database versions may not have it.
    $plSalesColumns = $pdo->query("SHOW COLUMNS FROM sales")->fetchAll(PDO::FETCH_COLUMN);
    $plSalesHasSupplierCost = in_array('external_supplier_unit_cost', $plSalesColumns, true);

    // 1) Use FIFO allocations whenever they exist. This is the actual stock cost.
    if ($plStockAllocationsExist) {
        $cogsStmt = $pdo->prepare("
            SELECT COALESCE(SUM(a.cost_total), 0)
            FROM stock_sale_allocations a
            INNER JOIN sales s ON s.id = a.sale_id
            WHERE s.sale_date BETWEEN ? AND ?
        ");
        $cogsStmt->execute([$startDate, $endDate]);
        $cogs = (float)$cogsStmt->fetchColumn();
    }

    // 2) For sales that have NO FIFO allocation, use the recorded supplier
    //    unit cost (if this optional field exists). Exclude every sale that
    //    already has an allocation to prevent double-counting the same cost.
    if ($plSalesHasSupplierCost) {
        $fallbackSql = "
            SELECT COALESCE(SUM(
                GREATEST(COALESCE(s.quantity, 1), 0) *
                GREATEST(COALESCE(s.external_supplier_unit_cost, 0), 0)
            ), 0)
            FROM sales s
            WHERE s.sale_date BETWEEN ? AND ?
              AND COALESCE(s.external_supplier_unit_cost, 0) > 0
        ";

        if ($plStockAllocationsExist) {
            $fallbackSql .= "
              AND NOT EXISTS (
                  SELECT 1
                  FROM stock_sale_allocations a2
                  WHERE a2.sale_id = s.id
              )
            ";
        }

        $fallbackStmt = $pdo->prepare($fallbackSql);
        $fallbackStmt->execute([$startDate, $endDate]);
        $cogs += (float)$fallbackStmt->fetchColumn();
    }
} catch (Throwable $e) {
    // Do not silently present an incomplete profit figure as correct.
    // Log the technical error; leave the report available with COGS at zero.
    $cogs = 0.0;
    error_log('[profit-loss COGS] ' . $e->getMessage());
}

$grossProfit = $totalIncome - $cogs;

/* =========================================================
   EXPENSES
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$totalExpenses = (float)$stmt->fetchColumn();

$netProfit = $grossProfit - $totalExpenses;

$profitMargin = $totalIncome > 0
    ? ($netProfit / $totalIncome) * 100
    : 0;

/* =========================================================
   BOOKING / RECEIVABLE CONTEXT
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS booking_count,
        COALESCE(SUM(total_amount), 0) AS booking_value,
        COALESCE(SUM(deposit_amount), 0) AS deposits,
        COALESCE(SUM(amount_paid), 0) AS booking_amount_paid,
        COALESCE(SUM(balance), 0) AS booking_balance
    FROM bookings
    WHERE booking_date BETWEEN ? AND ?
      AND booking_status <> 'cancelled'
");
$stmt->execute([$startDate, $endDate]);
$bookingRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$bookingCount       = (int)($bookingRow['booking_count'] ?? 0);
$bookingValue       = (float)($bookingRow['booking_value'] ?? 0);
$bookingDeposits    = (float)($bookingRow['deposits'] ?? 0);
$bookingAmountPaid  = (float)($bookingRow['booking_amount_paid'] ?? 0);
$bookingBalance     = (float)($bookingRow['booking_balance'] ?? 0);

/* =========================================================
   EXPENSE BREAKDOWN
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT
        COALESCE(NULLIF(TRIM(category), ''), 'Uncategorized') AS category,
        COALESCE(SUM(amount), 0) AS total,
        COUNT(*) AS count_rows
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
    GROUP BY COALESCE(NULLIF(TRIM(category), ''), 'Uncategorized')
    ORDER BY total DESC
");
$stmt->execute([$startDate, $endDate]);
$expenseBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   INCOME BY METHOD
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT
        COALESCE(NULLIF(TRIM(payment_method), ''), 'other') AS method,
        COALESCE(SUM(amount), 0) AS total,
        COUNT(*) AS count_rows
    FROM payments
    WHERE payment_status = 'successful'
      AND DATE(payment_date) BETWEEN ? AND ?
      " . $plPaymentSaleFilter . "
    GROUP BY COALESCE(NULLIF(TRIM(payment_method), ''), 'other')
    ORDER BY total DESC
");
$stmt->execute([$startDate, $endDate]);
$bookingMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
 * Sales payment methods must come from sale_payments, because one sale can
 * be paid in multiple instalments using different methods.
 * Older sales with no sale_payments rows use sales.amount_paid as a fallback.
 */
$stmt = $pdo->prepare("
    SELECT method, SUM(total) AS total, SUM(count_rows) AS count_rows
    FROM (
        SELECT
            COALESCE(NULLIF(TRIM(sp.payment_method), ''), 'other') AS method,
            COALESCE(SUM(sp.amount), 0) AS total,
            COUNT(*) AS count_rows
        FROM sale_payments sp
        INNER JOIN sales s ON s.id = sp.sale_id
        WHERE DATE(sp.payment_date) BETWEEN ? AND ?
        GROUP BY COALESCE(NULLIF(TRIM(sp.payment_method), ''), 'other')

        UNION ALL

        SELECT
            COALESCE(NULLIF(TRIM(s.payment_method), ''), 'other') AS method,
            COALESCE(SUM(s.amount_paid), 0) AS total,
            COUNT(*) AS count_rows
        FROM sales s
        WHERE s.sale_date BETWEEN ? AND ?
          AND NOT EXISTS (
              SELECT 1
              FROM sale_payments sp2
              WHERE sp2.sale_id = s.id
          )
        GROUP BY COALESCE(NULLIF(TRIM(s.payment_method), ''), 'other')
    ) AS payment_data
    GROUP BY method
    ORDER BY total DESC
");
$stmt->execute([$startDate, $endDate, $startDate, $endDate]);
$salesMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

$methodTotals = [];

foreach ($bookingMethods as $row) {
    $method = strtolower(trim((string)$row['method']));
    $methodTotals[$method] = ($methodTotals[$method] ?? 0) + (float)$row['total'];
}

foreach ($salesMethods as $row) {
    $method = strtolower(trim((string)$row['method']));
    $methodTotals[$method] = ($methodTotals[$method] ?? 0) + (float)$row['total'];
}

arsort($methodTotals);

/* =========================================================
   TOP SERVICES / PACKAGES BY BOOKING VALUE
   ========================================================= */

/*
 * Build the Top Services query from columns that actually exist.
 * Older versions of the website may store service names under a different
 * column, or may not have a services table at all.
 */
$topServices = [];
try {
    $bookingCols = $pdo->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN);
    $serviceCols = [];
    $hasServicesTable = (bool)$pdo->query("SHOW TABLES LIKE 'services'")->fetchColumn();

    if ($hasServicesTable) {
        $serviceCols = $pdo->query("SHOW COLUMNS FROM services")->fetchAll(PDO::FETCH_COLUMN);
    }

    $serviceNameColumn = null;
    foreach (['name', 'service_name', 'title', 'name_en', 'service'] as $candidate) {
        if (in_array($candidate, $serviceCols, true)) {
            $serviceNameColumn = $candidate;
            break;
        }
    }

    $bookingServiceExpr = in_array('service_name', $bookingCols, true)
        ? "NULLIF(TRIM(b.service_name), '')"
        : (in_array('event_type', $bookingCols, true)
            ? "NULLIF(TRIM(b.event_type), '')"
            : "NULL");

    $serviceJoin = '';
    if ($hasServicesTable
        && in_array('id', $serviceCols, true)
        && in_array('service_id', $bookingCols, true)
        && $serviceNameColumn !== null) {
        $serviceJoin = " LEFT JOIN services s ON s.id = b.service_id ";
        $serviceExpr = "COALESCE(NULLIF(TRIM(s.`" . $serviceNameColumn . "`), ''), " . $bookingServiceExpr . ", 'Unspecified Service')";
        $groupExpr = "s.id, s.`" . $serviceNameColumn . "`";
    } else {
        $serviceExpr = "COALESCE(" . $bookingServiceExpr . ", 'Unspecified Service')";
        $groupExpr = $bookingServiceExpr;
    }

    $totalExpr = in_array('total_amount', $bookingCols, true) ? 'b.total_amount' : '0';
    $paidExpr = in_array('amount_paid', $bookingCols, true) ? 'b.amount_paid' : '0';
    $dateExpr = in_array('booking_date', $bookingCols, true) ? 'b.booking_date' : null;
    $statusFilter = in_array('booking_status', $bookingCols, true)
        ? " AND COALESCE(b.booking_status, '') <> 'cancelled' "
        : '';

    if ($dateExpr !== null) {
        $stmt = $pdo->prepare("
            SELECT
                {$serviceExpr} AS service_name,
                COUNT(b.id) AS bookings_count,
                COALESCE(SUM({$totalExpr}), 0) AS booking_value,
                COALESCE(SUM({$paidExpr}), 0) AS amount_paid
            FROM bookings b
            {$serviceJoin}
            WHERE {$dateExpr} BETWEEN ? AND ?
            {$statusFilter}
            GROUP BY {$groupExpr}
            ORDER BY booking_value DESC
            LIMIT 10
        ");
        $stmt->execute([$startDate, $endDate]);
        $topServices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    // Keep the report available even if this optional breakdown is unavailable.
    $topServices = [];
}

/* =========================================================
   MONTHLY TREND
   ========================================================= */

$trend = [];

$monthsStmt = $pdo->prepare("
    SELECT
        DATE_FORMAT(payment_date, '%Y-%m') AS ym,
        COALESCE(SUM(amount), 0) AS total
    FROM payments
    WHERE payment_status = 'successful'
      AND DATE(payment_date) BETWEEN ? AND ?
      " . $plPaymentSaleFilter . "
    GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
");
$monthsStmt->execute([$startDate, $endDate]);

foreach ($monthsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $trend[$row['ym']]['income'] =
        ($trend[$row['ym']]['income'] ?? 0) + (float)$row['total'];
}

$salesTrendStmt = $pdo->prepare("
    SELECT
        DATE_FORMAT(sale_date, '%Y-%m') AS ym,
        COALESCE(SUM(amount_paid), 0) AS total
    FROM sales
    WHERE sale_date BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(sale_date, '%Y-%m')
");
$salesTrendStmt->execute([$startDate, $endDate]);

foreach ($salesTrendStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $trend[$row['ym']]['income'] =
        ($trend[$row['ym']]['income'] ?? 0) + (float)$row['total'];
}

$expenseTrendStmt = $pdo->prepare("
    SELECT
        DATE_FORMAT(expense_date, '%Y-%m') AS ym,
        COALESCE(SUM(amount), 0) AS total
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(expense_date, '%Y-%m')
");
$expenseTrendStmt->execute([$startDate, $endDate]);

foreach ($expenseTrendStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $trend[$row['ym']]['expense'] =
        ($trend[$row['ym']]['expense'] ?? 0) + (float)$row['total'];
}

ksort($trend);

/* =========================================================
   PAGE
   ========================================================= */

require __DIR__ . '/includes/admin-header.php';
?>

<style>
.pl-wrap{
    max-width:1500px;
    margin:0 auto;
}

.pl-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-end;
    gap:20px;
    margin-bottom:22px;
}

.pl-title{
    margin:0;
    font-size:32px;
    font-weight:800;
}

.pl-sub{
    margin:6px 0 0;
    color:#7a8495;
}

.pl-filters{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    align-items:center;
}

.pl-filters a,
.pl-filters button{
    border:1px solid #dce2ea;
    background:#fff;
    color:#273142;
    padding:9px 13px;
    border-radius:9px;
    text-decoration:none;
    font-size:13px;
    font-weight:700;
    cursor:pointer;
}

.pl-filters a.active{
    background:#1769ff;
    border-color:#1769ff;
    color:#fff;
}

.pl-custom{
    display:flex;
    gap:7px;
    align-items:center;
    flex-wrap:wrap;
}

.pl-custom input{
    border:1px solid #dce2ea;
    border-radius:8px;
    padding:9px 10px;
    font:inherit;
    font-size:13px;
}

.pl-card{
    background:#fff;
    border:1px solid #e6eaf0;
    border-radius:16px;
    box-shadow:0 8px 30px rgba(16,24,40,.05);
}

.pl-kpis{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
    margin-bottom:18px;
}

.pl-kpi{
    padding:20px;
    min-height:145px;
}

.pl-kpi-label{
    color:#6b7280;
    font-size:13px;
    font-weight:700;
}

.pl-kpi-value{
    margin-top:10px;
    font-size:26px;
    font-weight:900;
    letter-spacing:-.03em;
}

.pl-kpi-note{
    margin-top:8px;
    color:#8a93a2;
    font-size:12px;
}

.pl-green{border-top:4px solid #18a957}
.pl-red{border-top:4px solid #df4b4b}
.pl-blue{border-top:4px solid #1769ff}
.pl-orange{border-top:4px solid #f39a24}

.pl-grid{
    display:grid;
    grid-template-columns:1.15fr .85fr;
    gap:18px;
    margin-bottom:18px;
}

.pl-section{
    padding:20px;
}

.pl-section h2{
    margin:0;
    font-size:18px;
}

.pl-section p{
    margin:5px 0 18px;
    color:#7a8495;
    font-size:13px;
}

.pl-table{
    width:100%;
    border-collapse:collapse;
}

.pl-table th,
.pl-table td{
    padding:11px 8px;
    border-bottom:1px solid #edf0f4;
    text-align:left;
    font-size:13px;
}

.pl-table th{
    color:#7a8495;
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.08em;
}

.pl-table td:last-child,
.pl-table th:last-child{
    text-align:right;
}

.pl-total td{
    font-weight:900;
    border-bottom:0;
}

.pl-money-in{color:#159447;font-weight:800}
.pl-money-out{color:#d94141;font-weight:800}

.pl-bar{
    height:9px;
    background:#edf1f6;
    border-radius:99px;
    overflow:hidden;
    margin-top:7px;
}

.pl-bar span{
    display:block;
    height:100%;
    background:#1769ff;
    border-radius:99px;
}

.pl-method{
    display:flex;
    justify-content:space-between;
    gap:15px;
    padding:12px 0;
    border-bottom:1px solid #edf0f4;
}

.pl-method:last-child{border-bottom:0}

.pl-chart{
    display:flex;
    flex-direction:column;
    gap:13px;
}

.pl-chart-row{
    display:grid;
    grid-template-columns:80px 1fr 100px;
    gap:10px;
    align-items:center;
}

.pl-chart-label{
    font-size:12px;
    font-weight:800;
    color:#687386;
}

.pl-chart-track{
    height:13px;
    background:#edf1f6;
    border-radius:99px;
    overflow:hidden;
}

.pl-chart-income{
    height:100%;
    background:#18a957;
    border-radius:99px;
}

.pl-chart-expense{
    height:100%;
    background:#df4b4b;
    border-radius:99px;
    margin-top:3px;
}

.pl-chart-values{
    text-align:right;
    font-size:11px;
    color:#687386;
}

.pl-note{
    padding:14px 16px;
    background:#f7f9fc;
    border:1px solid #e5eaf1;
    border-radius:12px;
    color:#667085;
    font-size:13px;
    line-height:1.6;
    margin-top:18px;
}

.pl-print{
    float:right;
}


/* =========================================================
   DARK PREMIUM THEME — IMAGINATION NATURE STUDIO
   Matches the main admin dashboard
   ========================================================= */

.pl-wrap{
    color:#eef5f2 !important;
}

.pl-title,
.pl-section h2{
    color:#f5f7f6 !important;
}

.pl-sub,
.pl-section > p{
    color:#9da9a6 !important;
}

/* Filters */
.pl-filters a,
.pl-filters button,
.pl-custom input,
.pl-custom button{
    background:#1b2324 !important;
    color:#eef5f2 !important;
    border:1px solid #354344 !important;
}

.pl-filters a:hover,
.pl-filters button:hover,
.pl-custom button:hover,
.pl-filters a.active{
    background:#243334 !important;
    color:#72f0b1 !important;
    border-color:#45d993 !important;
}

/* Cards */
.pl-card{
    background:linear-gradient(145deg,#151d1e,#101617) !important;
    color:#eef5f2 !important;
    border:1px solid #293536 !important;
    box-shadow:0 16px 35px rgba(0,0,0,.22) !important;
}

.pl-card h2,
.pl-card h3,
.pl-card strong{
    color:#f2f6f5 !important;
}

/* KPI */
.pl-kpi-label{
    color:#aab6b3 !important;
}

.pl-kpi-value{
    color:#f2f6f5 !important;
}

.pl-kpi-note{
    color:#9da9a6 !important;
}

.pl-money-in{
    color:#55e7aa !important;
}

.pl-money-out{
    color:#ff707c !important;
}

.pl-green{
    border-top-color:#4be39d !important;
}

.pl-red{
    border-top-color:#ff6571 !important;
}

.pl-blue{
    border-top-color:#5b8cff !important;
}

.pl-orange{
    border-top-color:#f5a33b !important;
}

/* Tables */
.pl-table th,
.pl-table td{
    color:#e9efed !important;
    border-bottom-color:#2b3839 !important;
}

.pl-table th{
    color:#9fada9 !important;
    background:#182021 !important;
}

.pl-table tbody tr:hover{
    background:#1b2526 !important;
}

/* Method rows */
.pl-method{
    border-bottom-color:#2b3839 !important;
}

.pl-method span{
    color:#d8e1de !important;
}

/* Progress bars */
.pl-bar,
.pl-chart-track{
    background:#293334 !important;
}

.pl-bar span{
    background:#45d993 !important;
}

/* Trend chart */
.pl-chart-label{
    color:#aab6b3 !important;
}

.pl-chart-values{
    color:#aab6b3 !important;
}

.pl-chart-income{
    background:#45d993 !important;
}

.pl-chart-expense{
    background:#ff6571 !important;
}

/* Notes */
.pl-note{
    background:#1a2223 !important;
    border-color:#303d3e !important;
    color:#9da9a6 !important;
}

.pl-note strong{
    color:#eef5f2 !important;
}

/* Date inputs */
.pl-wrap input[type="date"]{
    color-scheme:dark;
}

/* Catch remaining light inline surfaces */
.pl-wrap [style*="background:#fff"],
.pl-wrap [style*="background: #fff"],
.pl-wrap [style*="background:#ffffff"],
.pl-wrap [style*="background: #ffffff"],
.pl-wrap [style*="background:white"],
.pl-wrap [style*="background: white"]{
    background:#151d1e !important;
    color:#eef5f2 !important;
}

/* Print remains clean */
@media print{
    .pl-card{
        background:#fff !important;
        color:#111 !important;
        box-shadow:none !important;
        border:1px solid #ccc !important;
    }

    .pl-card *,
    .pl-title,
    .pl-section h2,
    .pl-section p{
        color:#111 !important;
    }
}

@media(max-width:1000px){
    .pl-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
    .pl-grid{grid-template-columns:1fr}
}

@media(max-width:650px){
    .pl-head{display:block}
    .pl-filters{margin-top:16px}
    .pl-kpis{grid-template-columns:1fr}
    .pl-kpi-value{font-size:23px}
    .pl-chart-row{grid-template-columns:65px 1fr 80px}
    .pl-section{padding:15px}
    .pl-title{font-size:26px}
}
</style>

<div class="pl-wrap">

    <div class="pl-head">
        <div>
            <h1 class="pl-title">📊 Profit &amp; Loss</h1>
            <p class="pl-sub">
                <?= e($periodLabel) ?> · Cash-based financial performance
            </p>
        </div>

        <div class="pl-filters">
            <a href="profit-loss.php?period=today"
               class="<?= $period === 'today' ? 'active' : '' ?>">Today</a>

            <a href="profit-loss.php?period=week"
               class="<?= $period === 'week' ? 'active' : '' ?>">Week</a>

            <a href="profit-loss.php?period=month"
               class="<?= $period === 'month' ? 'active' : '' ?>">Month</a>

            <a href="profit-loss.php?period=year"
               class="<?= $period === 'year' ? 'active' : '' ?>">Year</a>

            <form class="pl-custom" method="get">
                <input type="hidden" name="period" value="custom">
                <input type="date" name="from" value="<?= e($startDate) ?>">
                <input type="date" name="to" value="<?= e($endDate) ?>">
                <button type="submit">Apply</button>
            </form>

            <button type="button" onclick="window.print()">🖨 Print</button>
            <a href="finance-planning.php">Monthly Planning &amp; Reserves</a>
            <a href="transactions.php">Transactions</a>
        </div>
    </div>

    <section class="pl-card pl-section" style="margin-bottom:18px">
        <h2>Monthly Planning &amp; Reserves — <?= e(date('F Y', strtotime($plReserveMonth . '-01'))) ?></h2>
        <p>Reserve is a planning target, not an expense. Actual rent/salary payments entered in Finance Planning are saved once to Expenses and appear in this report.</p>
        <div class="pl-method"><span>Planned obligations</span><strong><?= e(plMoney($plReservePlanned)) ?></strong></div>
        <div class="pl-method"><span>Paid against this month's plan</span><strong class="pl-money-in"><?= e(plMoney($plReservePaid)) ?></strong></div>
        <div class="pl-method"><span>Still to reserve / pay</span><strong class="pl-money-out"><?= e(plMoney($plReserveRemaining)) ?></strong></div>
        <p><a href="finance-planning.php">Open Finance Planning &amp; record payments →</a></p>
    </section>

    <div class="pl-kpis">

        <div class="pl-card pl-kpi pl-green">
            <div class="pl-kpi-label">TOTAL INCOME</div>
            <div class="pl-kpi-value pl-money-in"><?= e(plMoney($totalIncome)) ?></div>
            <div class="pl-kpi-note">
                Booking payments + sales collected
            </div>
        </div>

        <div class="pl-card pl-kpi pl-red">
            <div class="pl-kpi-label">COST OF GOODS SOLD (FIFO / SUPPLIER COST)</div>
            <div class="pl-kpi-value pl-money-out"><?= e(plMoney($cogs)) ?></div>
            <div class="pl-kpi-note">FIFO cost where allocated; recorded supplier cost for unallocated sales</div>
        </div>

        <div class="pl-card pl-kpi pl-red">
            <div class="pl-kpi-label">TOTAL EXPENSES</div>
            <div class="pl-kpi-value pl-money-out"><?= e(plMoney($totalExpenses)) ?></div>
            <div class="pl-kpi-note">
                All recorded expenses
            </div>
        </div>

        <div class="pl-card pl-kpi <?= $netProfit >= 0 ? 'pl-green' : 'pl-red' ?>">
            <div class="pl-kpi-label">NET PROFIT</div>
            <div class="pl-kpi-value <?= $netProfit >= 0 ? 'pl-money-in' : 'pl-money-out' ?>">
                <?= e(plMoney($netProfit)) ?>
            </div>
            <div class="pl-kpi-note">
                <?= e(plPct($profitMargin)) ?> profit margin
            </div>
        </div>

        <div class="pl-card pl-kpi pl-blue">
            <div class="pl-kpi-label">BOOKING VALUE</div>
            <div class="pl-kpi-value"><?= e(plMoney($bookingValue)) ?></div>
            <div class="pl-kpi-note">
                <?= e((string)$bookingCount) ?> non-cancelled bookings
            </div>
        </div>

    </div>

    <div class="pl-grid">

        <section class="pl-card pl-section">
            <h2>Income Statement</h2>
            <p><?= e($periodLabel) ?></p>

            <table class="pl-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Booking Payments</td>
                        <td class="pl-money-in"><?= e(plMoney($bookingPayments)) ?></td>
                    </tr>
                    <tr>
                        <td>Sales Collected</td>
                        <td class="pl-money-in"><?= e(plMoney($salesCollected)) ?></td>
                    </tr>
                    <tr class="pl-total">
                        <td>Total Income</td>
                        <td class="pl-money-in"><?= e(plMoney($totalIncome)) ?></td>
                    </tr>
                    <tr>
                        <td>Cost of Goods Sold (FIFO / supplier cost)</td>
                        <td class="pl-money-out">− <?= e(plMoney($cogs)) ?></td>
                    </tr>
                    <tr>
                        <td>Gross Profit</td>
                        <td><?= e(plMoney($grossProfit)) ?></td>
                    </tr>
                    <tr>
                        <td>Total Expenses</td>
                        <td class="pl-money-out">− <?= e(plMoney($totalExpenses)) ?></td>
                    </tr>
                    <tr class="pl-total">
                        <td>NET PROFIT</td>
                        <td class="<?= $netProfit >= 0 ? 'pl-money-in' : 'pl-money-out' ?>">
                            <?= e(plMoney($netProfit)) ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="pl-note">
                <strong>Profit margin:</strong>
                <?= e(plPct($profitMargin)) ?>.
                This cash-based report subtracts FIFO allocation costs where available. For sales without any FIFO allocation, it uses the recorded external supplier unit cost multiplied by quantity, when that field exists. Sales with FIFO allocations are excluded from the fallback to prevent double-counting. Stock purchases are not added again as expenses.
            </div>
        </section>

        <section class="pl-card pl-section">
            <h2>Income Breakdown</h2>
            <p>Where the money received came from.</p>

            <div class="pl-method">
                <span>Booking Payments</span>
                <strong class="pl-money-in"><?= e(plMoney($bookingPayments)) ?></strong>
            </div>

            <div class="pl-method">
                <span>Product / Other Sales</span>
                <strong class="pl-money-in"><?= e(plMoney($salesCollected)) ?></strong>
            </div>

            <div class="pl-method">
                <span>Total Income</span>
                <strong><?= e(plMoney($totalIncome)) ?></strong>
            </div>

            <div class="pl-method">
                <span>Sales Still Outstanding</span>
                <strong class="pl-money-out"><?= e(plMoney($salesBalance)) ?></strong>
            </div>

            <div class="pl-method">
                <span>Booking Balance in Period</span>
                <strong class="pl-money-out"><?= e(plMoney($bookingBalance)) ?></strong>
            </div>
        </section>

    </div>

    <div class="pl-grid">

        <section class="pl-card pl-section">
            <h2>Expense Breakdown</h2>
            <p>Expenses grouped by category.</p>

            <?php if (!$expenseBreakdown): ?>
                <div class="pl-note">No expenses recorded for this period.</div>
            <?php else: ?>

                <?php foreach ($expenseBreakdown as $row): ?>
                    <?php
                    $amount = (float)$row['total'];
                    $width = plPercentOf($amount, $totalExpenses);
                    ?>
                    <div style="margin-bottom:16px">
                        <div style="display:flex;justify-content:space-between;gap:12px">
                            <strong><?= e($row['category']) ?></strong>
                            <span class="pl-money-out"><?= e(plMoney($amount)) ?></span>
                        </div>

                        <div class="pl-bar">
                            <span style="width:<?= e((string)min(100, $width)) ?>%"></span>
                        </div>

                        <div style="margin-top:5px;color:#8a93a2;font-size:11px">
                            <?= e(plPct($width)) ?> of expenses ·
                            <?= e((string)$row['count_rows']) ?> record(s)
                        </div>
                    </div>
                <?php endforeach; ?>

            <?php endif; ?>
        </section>

        <section class="pl-card pl-section">
            <h2>Payment Methods</h2>
            <p>Total money received by payment method.</p>

            <?php if (!$methodTotals): ?>
                <div class="pl-note">No payment data for this period.</div>
            <?php else: ?>

                <?php foreach ($methodTotals as $method => $amount): ?>
                    <?php $width = plPercentOf((float)$amount, $totalIncome); ?>

                    <div class="pl-method">
                        <span><?= e(ucwords(str_replace('_', ' ', $method))) ?></span>
                        <strong class="pl-money-in"><?= e(plMoney((float)$amount)) ?></strong>
                    </div>

                    <div class="pl-bar" style="margin-bottom:8px">
                        <span style="width:<?= e((string)min(100, $width)) ?>%"></span>
                    </div>
                <?php endforeach; ?>

            <?php endif; ?>
        </section>

    </div>

    <div class="pl-grid">

        <section class="pl-card pl-section">
            <h2>Top Services</h2>
            <p>Based on booking value during the selected period.</p>

            <?php if (!$topServices): ?>
                <div class="pl-note">No booking service data for this period.</div>
            <?php else: ?>

                <table class="pl-table">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Bookings</th>
                            <th>Paid</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php foreach ($topServices as $row): ?>
                        <tr>
                            <td><?= e($row['service_name']) ?></td>
                            <td><?= e((string)$row['bookings_count']) ?></td>
                            <td><?= e(plMoney((float)$row['amount_paid'])) ?></td>
                            <td><?= e(plMoney((float)$row['booking_value'])) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    </tbody>
                </table>

            <?php endif; ?>
        </section>

        <section class="pl-card pl-section">
            <h2>Business Snapshot</h2>
            <p>Operational context for the selected period.</p>

            <div class="pl-method">
                <span>Bookings</span>
                <strong><?= e((string)$bookingCount) ?></strong>
            </div>

            <div class="pl-method">
                <span>Booking Value</span>
                <strong><?= e(plMoney($bookingValue)) ?></strong>
            </div>

            <div class="pl-method">
                <span>Amount Paid on Bookings</span>
                <strong class="pl-money-in"><?= e(plMoney($bookingAmountPaid)) ?></strong>
            </div>

            <div class="pl-method">
                <span>Booking Balance</span>
                <strong class="pl-money-out"><?= e(plMoney($bookingBalance)) ?></strong>
            </div>

            <div class="pl-method">
                <span>Sales Value</span>
                <strong><?= e(plMoney($salesValue)) ?></strong>
            </div>

            <div class="pl-method">
                <span>Sales Collected</span>
                <strong class="pl-money-in"><?= e(plMoney($salesCollected)) ?></strong>
            </div>
        </section>

    </div>

    <section class="pl-card pl-section" style="margin-bottom:18px">
        <h2>Income vs Expenses Trend</h2>
        <p>Monthly cash movement inside the selected date range.</p>

        <?php if (!$trend): ?>
            <div class="pl-note">No financial activity found for this period.</div>
        <?php else: ?>

            <?php
            $trendMax = 1;
            foreach ($trend as $t) {
                $trendMax = max(
                    $trendMax,
                    (float)($t['income'] ?? 0),
                    (float)($t['expense'] ?? 0)
                );
            }
            ?>

            <div class="pl-chart">

                <?php foreach ($trend as $ym => $t): ?>
                    <?php
                    $income  = (float)($t['income'] ?? 0);
                    $expense = (float)($t['expense'] ?? 0);

                    $incomeWidth  = ($income / $trendMax) * 100;
                    $expenseWidth = ($expense / $trendMax) * 100;

                    $monthLabel = date('M Y', strtotime($ym . '-01'));
                    ?>

                    <div class="pl-chart-row">
                        <div class="pl-chart-label"><?= e($monthLabel) ?></div>

                        <div>
                            <div class="pl-chart-track">
                                <div class="pl-chart-income"
                                     style="width:<?= e((string)$incomeWidth) ?>%"></div>
                            </div>

                            <div class="pl-chart-track">
                                <div class="pl-chart-expense"
                                     style="width:<?= e((string)$expenseWidth) ?>%"></div>
                            </div>
                        </div>

                        <div class="pl-chart-values">
                            <div class="pl-money-in"><?= e(plMoney($income)) ?></div>
                            <div class="pl-money-out"><?= e(plMoney($expense)) ?></div>
                        </div>
                    </div>

                <?php endforeach; ?>

            </div>

            <div class="pl-note">
                🟢 Income &nbsp;&nbsp; 🔴 Expenses
            </div>

        <?php endif; ?>
    </section>

    <div class="pl-note">
        <strong>Accounting note:</strong>
        This Profit &amp; Loss page is intentionally aligned with the existing
        dashboard and Transactions system. It uses successful booking payments,
        sales amounts actually paid, and recorded expenses. It includes FIFO COGS from stock_sale_allocations and, for sales without allocations, uses external_supplier_unit_cost multiplied by quantity when that column exists. It does not calculate depreciation, taxes, unpaid invoice revenue, or full accrual accounting profit. Check that external_supplier_unit_cost is stored per unit, not as a line total.
    </div>

</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
