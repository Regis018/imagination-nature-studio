<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

/*
|--------------------------------------------------------------------------
| BUSINESS DASHBOARD
|--------------------------------------------------------------------------
| Cash-based dashboard:
| - Money Received = successful booking payments + sales amount_paid
| - Expenses = recorded expenses
| - Net Profit = Money Received - Expenses
| - Outstanding = unpaid booking balances
|--------------------------------------------------------------------------
*/


/* =========================================================
   DATE FILTER
   ========================================================= */

$period = $_GET['period'] ?? 'today';

$allowedPeriods = [
    'today',
    'week',
    'month',
    'year'
];

if (!in_array($period, $allowedPeriods, true)) {
    $period = 'today';
}


switch ($period) {

    case 'week':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate   = date('Y-m-d');
        $periodLabel = 'This Week';
        break;

    case 'month':
        $startDate = date('Y-m-01');
        $endDate   = date('Y-m-d');
        $periodLabel = 'This Month';
        break;

    case 'year':
        $startDate = date('Y-01-01');
        $endDate   = date('Y-m-d');
        $periodLabel = 'This Year';
        break;

    default:
        $startDate = date('Y-m-d');
        $endDate   = date('Y-m-d');
        $periodLabel = 'Today';
        break;
}


/* =========================================================
   HELPER
   ========================================================= */

function dashNumber($value)
{
    return number_format((float)$value, 0);
}

function dashPercent($value)
{
    return number_format((float)$value, 1) . '%';
}


/* =========================================================
   BOOKING STATISTICS
   ========================================================= */

$totalBookings = (int)$pdo->query("
    SELECT COUNT(*)
    FROM bookings
")->fetchColumn();


$pendingBookings = (int)$pdo->query("
    SELECT COUNT(*)
    FROM bookings
    WHERE booking_status = 'pending'
")->fetchColumn();


$confirmedBookings = (int)$pdo->query("
    SELECT COUNT(*)
    FROM bookings
    WHERE booking_status = 'confirmed'
")->fetchColumn();


$completedBookings = (int)$pdo->query("
    SELECT COUNT(*)
    FROM bookings
    WHERE booking_status = 'completed'
")->fetchColumn();


$cancelledBookings = (int)$pdo->query("
    SELECT COUNT(*)
    FROM bookings
    WHERE booking_status = 'cancelled'
")->fetchColumn();


$todayBookings = (int)$pdo->query("
    SELECT COUNT(*)
    FROM bookings
    WHERE booking_date = CURDATE()
")->fetchColumn();


$upcomingBookings = (int)$pdo->query("
    SELECT COUNT(*)
    FROM bookings
    WHERE booking_date >= CURDATE()
      AND booking_status IN ('pending','confirmed')
")->fetchColumn();


/* =========================================================
   MONEY RECEIVED FROM BOOKINGS
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM payments
    WHERE payment_status = 'successful'
      AND DATE(payment_date) BETWEEN ? AND ?
");

$stmt->execute([
    $startDate,
    $endDate
]);

$bookingPayments = (float)$stmt->fetchColumn();


/* =========================================================
   MONEY RECEIVED FROM SALES
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount_paid), 0)
    FROM sales
    WHERE sale_date BETWEEN ? AND ?
");

$stmt->execute([
    $startDate,
    $endDate
]);

$salesCollected = (float)$stmt->fetchColumn();


/* =========================================================
   TOTAL SALES VALUE
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total), 0)
    FROM sales
    WHERE sale_date BETWEEN ? AND ?
");

$stmt->execute([
    $startDate,
    $endDate
]);

$salesValue = (float)$stmt->fetchColumn();


/* =========================================================
   TOTAL MONEY RECEIVED
   ========================================================= */

$totalCollected = $bookingPayments + $salesCollected;


/* =========================================================
   EXPENSES
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
");

$stmt->execute([
    $startDate,
    $endDate
]);

$totalExpenses = (float)$stmt->fetchColumn();


/* =========================================================
   NET PROFIT
   ========================================================= */

$netProfit = $totalCollected - $totalExpenses;


/* =========================================================
   OUTSTANDING BOOKINGS
   ========================================================= */

$outstanding = (float)$pdo->query("
    SELECT COALESCE(SUM(balance), 0)
    FROM bookings
    WHERE booking_status IN ('pending','confirmed')
")->fetchColumn();


/* =========================================================
   CASH FLOW
   ========================================================= */

$cashFlow = $totalCollected - $totalExpenses;


/* =========================================================
   ALL-TIME CASH IN HAND
   ========================================================= */

$allTimeBookingPayments = (float)$pdo->query("
    SELECT COALESCE(SUM(amount), 0)
    FROM payments
    WHERE payment_status = 'successful'
")->fetchColumn();


$allTimeSalesCollected = (float)$pdo->query("
    SELECT COALESCE(SUM(amount_paid), 0)
    FROM sales
")->fetchColumn();


$allTimeExpenses = (float)$pdo->query("
    SELECT COALESCE(SUM(amount), 0)
    FROM expenses
")->fetchColumn();


$cashInHand =
    $allTimeBookingPayments
    + $allTimeSalesCollected
    - $allTimeExpenses;


/* =========================================================
   AVERAGE BOOKING VALUE
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT COALESCE(AVG(total_amount), 0)
    FROM bookings
    WHERE booking_date BETWEEN ? AND ?
");

$stmt->execute([
    $startDate,
    $endDate
]);

$averageBookingValue = (float)$stmt->fetchColumn();


/* =========================================================
   TOP SERVICES
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT
        s.name AS service_name,
        COUNT(b.id) AS booking_count,
        COALESCE(SUM(b.amount_paid), 0) AS revenue
    FROM bookings b
    INNER JOIN services s
        ON s.id = b.service_id
    WHERE b.booking_date BETWEEN ? AND ?
    GROUP BY b.service_id, s.name
    ORDER BY revenue DESC
    LIMIT 8
");

$stmt->execute([
    $startDate,
    $endDate
]);

$topServices = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   TOP PACKAGES
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT
        p.name AS package_name,
        COUNT(b.id) AS booking_count,
        COALESCE(SUM(b.amount_paid), 0) AS revenue
    FROM bookings b
    INNER JOIN packages p
        ON p.id = b.package_id
    WHERE b.booking_date BETWEEN ? AND ?
    GROUP BY b.package_id, p.name
    ORDER BY revenue DESC
    LIMIT 8
");

$stmt->execute([
    $startDate,
    $endDate
]);

$topPackages = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   EXPENSE BREAKDOWN
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT
        COALESCE(category, 'Other') AS category,
        COALESCE(SUM(amount), 0) AS total
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
    GROUP BY category
    ORDER BY total DESC
");

$stmt->execute([
    $startDate,
    $endDate
]);

$expenseBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   PAYMENT METHODS
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT
        COALESCE(payment_method, 'Unknown') AS payment_method,
        COUNT(*) AS payment_count,
        COALESCE(SUM(amount), 0) AS total
    FROM payments
    WHERE payment_status = 'successful'
      AND DATE(payment_date) BETWEEN ? AND ?
    GROUP BY payment_method
    ORDER BY total DESC
");

$stmt->execute([
    $startDate,
    $endDate
]);

$paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   RECENT PAYMENTS
   ========================================================= */

$recentPayments = $pdo->query("
    SELECT
        p.*,
        b.booking_number,
        c.full_name
    FROM payments p
    LEFT JOIN bookings b
        ON b.id = p.booking_id
    LEFT JOIN customers c
        ON c.id = b.customer_id
    WHERE p.payment_status = 'successful'
    ORDER BY p.id DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   RECENT EXPENSES
   ========================================================= */

$recentExpenses = $pdo->query("
    SELECT *
    FROM expenses
    ORDER BY id DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   RECENT SALES
   ========================================================= */

$recentSales = $pdo->query("
    SELECT *
    FROM sales
    ORDER BY id DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   RECENT BOOKINGS
   ========================================================= */

$recentBookings = $pdo->query("
    SELECT
        b.*,
        c.full_name,
        s.name AS service_name
    FROM bookings b
    LEFT JOIN customers c
        ON c.id = b.customer_id
    LEFT JOIN services s
        ON s.id = b.service_id
    ORDER BY b.id DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   MONTHLY CHART — LAST 6 MONTHS
   ========================================================= */

$monthlyChart = [];

for ($i = 5; $i >= 0; $i--) {

    $monthTimestamp = strtotime("-{$i} months");

    $monthStart = date('Y-m-01', $monthTimestamp);
    $monthEnd   = date('Y-m-t', $monthTimestamp);

    $monthLabel = date('M Y', $monthTimestamp);


    /* Booking payments */

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM payments
        WHERE payment_status = 'successful'
          AND DATE(payment_date) BETWEEN ? AND ?
    ");

    $stmt->execute([
        $monthStart,
        $monthEnd
    ]);

    $monthBookingPayments = (float)$stmt->fetchColumn();


    /* Sales */

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount_paid), 0)
        FROM sales
        WHERE sale_date BETWEEN ? AND ?
    ");

    $stmt->execute([
        $monthStart,
        $monthEnd
    ]);

    $monthSales = (float)$stmt->fetchColumn();


    /* Expenses */

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM expenses
        WHERE expense_date BETWEEN ? AND ?
    ");

    $stmt->execute([
        $monthStart,
        $monthEnd
    ]);

    $monthExpenses = (float)$stmt->fetchColumn();


    $monthRevenue =
        $monthBookingPayments
        + $monthSales;


    $monthlyChart[] = [
        'label'    => $monthLabel,
        'revenue'  => $monthRevenue,
        'expenses' => $monthExpenses,
        'profit'   => $monthRevenue - $monthExpenses
    ];
}


/* =========================================================
   CHART MAX
   ========================================================= */

$chartMax = 1;

foreach ($monthlyChart as $month) {

    $chartMax = max(
        $chartMax,
        $month['revenue'],
        $month['expenses']
    );
}


/* =========================================================
   SERVICE MAX
   ========================================================= */

$serviceMax = 1;

foreach ($topServices as $service) {
    $serviceMax = max(
        $serviceMax,
        (float)$service['revenue']
    );
}


/* =========================================================
   EXPENSE MAX
   ========================================================= */

$expenseMax = 1;

foreach ($expenseBreakdown as $expense) {
    $expenseMax = max(
        $expenseMax,
        (float)$expense['total']
    );
}


/* =========================================================
<?php

/* =========================================================
   DASHBOARD DESIGN HELPERS / DERIVED DATA
   ========================================================= */

$expenseTotalForChart = 0.0;
foreach ($expenseBreakdown as $expenseRow) {
    $expenseTotalForChart += (float)$expenseRow['total'];
}

$expenseTotalForChart = max($expenseTotalForChart, 1.0);

$recentTransactions = [];

foreach ($recentPayments as $payment) {
    $recentTransactions[] = [
        'sort' => $payment['payment_date'] ?? $payment['created_at'] ?? '',
        'date' => $payment['payment_date'] ?? $payment['created_at'] ?? '',
        'description' => 'Client Payment' . (!empty($payment['full_name']) ? ' — ' . $payment['full_name'] : ''),
        'amount' => (float)($payment['amount'] ?? 0),
        'type' => 'income',
        'status' => 'Received'
    ];
}

foreach ($recentSales as $sale) {
    $recentTransactions[] = [
        'sort' => $sale['sale_date'] ?? $sale['created_at'] ?? '',
        'date' => $sale['sale_date'] ?? $sale['created_at'] ?? '',
        'description' => 'Sale' . (!empty($sale['product_name']) ? ' — ' . $sale['product_name'] : ''),
        'amount' => (float)($sale['amount_paid'] ?? $sale['total'] ?? 0),
        'type' => 'income',
        'status' => 'Received'
    ];
}

foreach ($recentExpenses as $expenseRow) {
    $recentTransactions[] = [
        'sort' => $expenseRow['expense_date'] ?? $expenseRow['created_at'] ?? '',
        'date' => $expenseRow['expense_date'] ?? $expenseRow['created_at'] ?? '',
        'description' => 'Expense' . (!empty($expenseRow['description']) ? ' — ' . $expenseRow['description'] : ''),
        'amount' => (float)($expenseRow['amount'] ?? 0),
        'type' => 'expense',
        'status' => 'Paid'
    ];
}

usort($recentTransactions, static function ($a, $b) {
    return strcmp((string)$b['sort'], (string)$a['sort']);
});

$recentTransactions = array_slice($recentTransactions, 0, 8);

function dashSvgX($index, $count, $width = 760, $left = 18, $right = 18)
{
    if ($count <= 1) {
        return ($width / 2);
    }

    return $left + ($index * (($width - $left - $right) / ($count - 1)));
}

function dashSvgY($value, $max, $height = 250, $top = 18, $bottom = 22)
{
    $max = max((float)$max, 1.0);
    $usable = $height - $top - $bottom;
    $ratio = min(max((float)$value / $max, 0), 1);

    return $top + ($usable - ($ratio * $usable));
}

$chartRevenuePoints = [];
$chartExpensePoints = [];
$chartCount = count($monthlyChart);

foreach ($monthlyChart as $i => $month) {
    $chartRevenuePoints[] = [
        dashSvgX($i, $chartCount),
        dashSvgY($month['revenue'], $chartMax)
    ];

    $chartExpensePoints[] = [
        dashSvgX($i, $chartCount),
        dashSvgY($month['expenses'], $chartMax)
    ];
}

$revenuePolyline = implode(' ', array_map(static function ($point) {
    return round($point[0], 2) . ',' . round($point[1], 2);
}, $chartRevenuePoints));

$expensePolyline = implode(' ', array_map(static function ($point) {
    return round($point[0], 2) . ',' . round($point[1], 2);
}, $chartExpensePoints));

$revenueAreaPoints = $revenuePolyline . ' 742,250 18,250';
$expenseAreaPoints = $expensePolyline . ' 742,250 18,250';

require __DIR__ . '/includes/admin-header.php';
?>

<style>
/* =========================================================
   IMAGINATION NATURE STUDIO — PREMIUM DARK DASHBOARD
   Inspired by the reference dashboards supplied by the user.
   ========================================================= */

.dashboard-page {
    --dash-bg: #090b0f;
    --dash-panel: #11151b;
    --dash-panel-2: #151a21;
    --dash-border: rgba(255,255,255,.075);
    --dash-text: #f7f9fb;
    --dash-muted: #89919d;
    --dash-green: #39f29a;
    --dash-cyan: #36d8ff;
    --dash-purple: #8b6cff;
    --dash-pink: #f14bba;
    --dash-orange: #ff9d45;
    --dash-red: #ff5e70;

    width: 100%;
    color: var(--dash-text) !important;
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.dashboard-page *,
.dashboard-page *::before,
.dashboard-page *::after {
    box-sizing: border-box;
}

.dashboard-page a {
    color: inherit;
}

/* Header */
.dashboard-hero {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1.25rem;
    margin-bottom: 1.25rem;
}

.dashboard-kicker {
    margin-bottom: .45rem;
    color: var(--dash-green) !important;
    font-size: .68rem;
    font-weight: 900;
    letter-spacing: .18em;
    text-transform: uppercase;
}

.dashboard-title {
    margin: 0;
    color: #fff !important;
    font-size: clamp(1.7rem, 3vw, 2.35rem);
    font-weight: 850;
    letter-spacing: -.035em;
    line-height: 1.05;
}

.dashboard-subtitle {
    margin: .55rem 0 0;
    color: var(--dash-muted) !important;
    font-size: .86rem;
}

.dashboard-date {
    color: #c7ccd4 !important;
    font-size: .76rem;
    text-align: right;
}

.dashboard-date strong {
    display: block;
    margin-bottom: .2rem;
    color: #fff !important;
    font-size: .82rem;
}

/* Filter */
.dashboard-filter {
    display: flex;
    flex-wrap: wrap;
    gap: .45rem;
    margin-bottom: 1.2rem;
}

.dashboard-filter a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: .48rem .8rem;
    border: 1px solid var(--dash-border);
    border-radius: 9px;
    background: #0e1217 !important;
    color: #aab2bd !important;
    text-decoration: none;
    font-size: .72rem;
    font-weight: 800;
    transition: .18s ease;
}

.dashboard-filter a:hover {
    transform: translateY(-1px);
    border-color: rgba(57,242,154,.28);
    color: #fff !important;
}

.dashboard-filter a.active {
    border-color: rgba(57,242,154,.5);
    background: rgba(57,242,154,.12) !important;
    color: var(--dash-green) !important;
}

/* KPI cards */
.dashboard-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .85rem;
    margin-bottom: .9rem;
}

.dashboard-kpi {
    position: relative;
    min-height: 142px;
    padding: 1rem 1.05rem 1.05rem;
    overflow: hidden;
    border: 1px solid var(--dash-border) !important;
    border-radius: 15px;
    background: linear-gradient(145deg, rgba(255,255,255,.045), rgba(255,255,255,.012)) !important;
    box-shadow: 0 16px 35px rgba(0,0,0,.16);
}

.dashboard-kpi::before {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    top: 0;
    height: 2px;
    background: var(--dash-accent, var(--dash-green));
    opacity: .95;
}

.dashboard-kpi::after {
    content: "";
    position: absolute;
    width: 100px;
    height: 100px;
    right: -42px;
    bottom: -50px;
    border-radius: 50%;
    background: var(--dash-accent, var(--dash-green));
    opacity: .07;
    filter: blur(3px);
}

.dashboard-kpi-icon {
    position: absolute;
    top: .85rem;
    right: .9rem;
    display: grid;
    width: 31px;
    height: 31px;
    place-items: center;
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 9px;
    background: rgba(255,255,255,.035);
    color: var(--dash-accent, var(--dash-green));
    font-size: .9rem;
}

.dashboard-kpi-label {
    display: block;
    max-width: 78%;
    color: #8d96a2 !important;
    font-size: .63rem;
    font-weight: 900;
    letter-spacing: .13em;
    text-transform: uppercase;
}

.dashboard-kpi-value {
    margin-top: .55rem;
    color: #fff !important;
    font-size: clamp(1.3rem, 2.3vw, 1.9rem);
    font-weight: 850;
    letter-spacing: -.04em;
    line-height: 1.05;
}

.dashboard-kpi-note {
    margin-top: .58rem;
    color: #737d89 !important;
    font-size: .69rem;
}

.kpi-green { --dash-accent: var(--dash-green); }
.kpi-red { --dash-accent: var(--dash-red); }
.kpi-orange { --dash-accent: var(--dash-orange); }
.kpi-blue { --dash-accent: var(--dash-cyan); }
.kpi-purple { --dash-accent: var(--dash-purple); }
.kpi-pink { --dash-accent: var(--dash-pink); }

.kpi-green .dashboard-kpi-value { color: #73ffba !important; }
.kpi-red .dashboard-kpi-value { color: #ff7180 !important; }
.kpi-orange .dashboard-kpi-value { color: #ffb26d !important; }
.kpi-blue .dashboard-kpi-value { color: #6fe7ff !important; }
.kpi-purple .dashboard-kpi-value { color: #a795ff !important; }
.kpi-pink .dashboard-kpi-value { color: #ff78c9 !important; }

/* Panels */
.dashboard-card {
    position: relative;
    overflow: hidden;
    border: 1px solid var(--dash-border) !important;
    border-radius: 15px;
    background: linear-gradient(145deg, rgba(255,255,255,.035), rgba(255,255,255,.012)) !important;
    box-shadow: 0 18px 42px rgba(0,0,0,.17);
    color: var(--dash-text) !important;
}

.dashboard-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: .95rem 1.05rem;
    border-bottom: 1px solid var(--dash-border);
}

.dashboard-card-title {
    margin: 0;
    color: #f4f6f8 !important;
    font-size: .84rem;
    font-weight: 850;
    letter-spacing: -.01em;
}

.dashboard-card-subtitle {
    margin: .22rem 0 0;
    color: #747e8a !important;
    font-size: .67rem;
}

.dashboard-card-body {
    padding: 1rem 1.05rem;
}

.dashboard-link {
    color: var(--dash-green) !important;
    font-size: .68rem;
    font-weight: 800;
    text-decoration: none;
}

.dashboard-link:hover { text-decoration: underline; }

/* Main chart + donut */
.dashboard-main-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.9fr) minmax(280px, .9fr);
    gap: .9rem;
    margin-bottom: .9rem;
}

.chart-area {
    padding: .75rem 1rem 1rem;
}

.dashboard-chart-wrap {
    position: relative;
    min-height: 285px;
}

.dashboard-chart-svg {
    display: block;
    width: 100%;
    height: 270px;
    overflow: visible;
}

.chart-grid-line {
    stroke: rgba(255,255,255,.055);
    stroke-width: 1;
}

.chart-revenue-area {
    fill: rgba(54,216,255,.08);
}

.chart-expense-area {
    fill: rgba(255,157,69,.055);
}

.chart-revenue-line {
    fill: none;
    stroke: var(--dash-cyan);
    stroke-width: 2.7;
    stroke-linecap: round;
    stroke-linejoin: round;
    filter: drop-shadow(0 0 7px rgba(54,216,255,.22));
}

.chart-expense-line {
    fill: none;
    stroke: var(--dash-orange);
    stroke-width: 2.5;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.chart-dot-revenue {
    fill: var(--dash-cyan);
    stroke: #0d1116;
    stroke-width: 3;
}

.chart-dot-expense {
    fill: var(--dash-orange);
    stroke: #0d1116;
    stroke-width: 3;
}

.chart-x-label {
    fill: #697380;
    font-size: 10px;
    font-weight: 700;
}

.chart-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: .2rem;
    color: #78828e !important;
    font-size: .67rem;
}

.legend-item {
    display: inline-flex;
    align-items: center;
    gap: .38rem;
}

.legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.legend-revenue { background: var(--dash-cyan); box-shadow: 0 0 8px rgba(54,216,255,.35); }
.legend-expense { background: var(--dash-orange); }

/* Donut */
.donut-panel {
    display: flex;
    flex-direction: column;
    min-height: 100%;
}

.donut-body {
    display: grid;
    place-items: center;
    padding: 1rem;
}

.donut-chart {
    position: relative;
    width: 190px;
    height: 190px;
    border-radius: 50%;
    background: conic-gradient(
        <?php
        $gradientParts = [];
        $gradientStart = 0;
        foreach ($expenseBreakdown as $expenseRow) {
            $pct = ((float)$expenseRow['total'] / $expenseTotalForChart) * 100;
            $gradientEnd = $gradientStart + $pct;
            $gradientParts[] = 'var(--donut-color-' . (count($gradientParts) % 6) . ') ' . round($gradientStart, 2) . '% ' . round($gradientEnd, 2) . '%';
            $gradientStart = $gradientEnd;
        }
        if (!$gradientParts) {
            $gradientParts[] = 'rgba(255,255,255,.08) 0% 100%';
        }
        echo implode(', ', $gradientParts);
        ?>
    );
    box-shadow: 0 0 45px rgba(54,216,255,.05);
}

.donut-chart::after {
    content: "";
    position: absolute;
    inset: 31px;
    border-radius: 50%;
    background: #10141a;
    border: 1px solid rgba(255,255,255,.045);
}

.donut-center {
    position: absolute;
    inset: 0;
    z-index: 2;
    display: grid;
    place-content: center;
    text-align: center;
}

.donut-center strong {
    color: #fff;
    font-size: 1.2rem;
}

.donut-center span {
    margin-top: .2rem;
    color: #77818e;
    font-size: .62rem;
    text-transform: uppercase;
    letter-spacing: .1em;
}

.donut-legend {
    width: 100%;
    margin-top: 1rem;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .55rem;
}

.donut-legend-item {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: .45rem;
    color: #9aa2ad;
    font-size: .65rem;
}

.donut-legend-item .dot {
    flex: 0 0 auto;
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.donut-legend-item .name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.donut-legend-item .amount {
    margin-left: auto;
    color: #dfe3e8;
    font-weight: 800;
}

/* Financial summary */
.financial-summary {
    display: grid;
    grid-template-columns: repeat(2, minmax(0,1fr));
    gap: .55rem;
}

.financial-item {
    padding: .75rem;
    border: 1px solid rgba(255,255,255,.055);
    border-radius: 10px;
    background: rgba(255,255,255,.025) !important;
}

.financial-item-label {
    color: #737d89 !important;
    font-size: .59rem;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.financial-item-value {
    margin-top: .25rem;
    color: #f3f5f7 !important;
    font-size: .88rem;
    font-weight: 850;
}

.financial-item-value.green { color: #72ffb7 !important; }
.financial-item-value.red { color: #ff7180 !important; }

/* Two columns */
.dashboard-two-column {
    display: grid;
    grid-template-columns: repeat(2, minmax(0,1fr));
    gap: .9rem;
    margin-bottom: .9rem;
}

/* Progress */
.progress-row { margin-bottom: .9rem; }
.progress-row:last-child { margin-bottom: 0; }
.progress-top {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: .35rem;
}
.progress-name {
    min-width: 0;
    color: #b7bec7 !important;
    font-size: .7rem;
    font-weight: 700;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.progress-value {
    flex: 0 0 auto;
    color: #e9edf1 !important;
    font-size: .68rem;
    font-weight: 850;
}
.progress-track {
    height: 5px;
    overflow: hidden;
    border-radius: 99px;
    background: rgba(255,255,255,.06) !important;
}
.progress-fill {
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, var(--dash-green), var(--dash-cyan));
}

/* Tables */
.dashboard-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.dashboard-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 620px;
}

.dashboard-table th {
    padding: .68rem 1rem;
    border-bottom: 1px solid var(--dash-border);
    color: #646e7a !important;
    font-size: .58rem;
    font-weight: 900;
    letter-spacing: .1em;
    text-align: left;
    text-transform: uppercase;
}

.dashboard-table td {
    padding: .72rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,.045);
    color: #aeb6c0 !important;
    font-size: .7rem;
    vertical-align: middle;
}

.dashboard-table tbody tr:hover td {
    background: rgba(255,255,255,.018);
}

.dashboard-table tbody tr:last-child td { border-bottom: 0; }

.table-main {
    color: #e9edf1 !important;
    font-weight: 800;
}

.table-muted {
    display: block;
    margin-top: .18rem;
    color: #68727e !important;
    font-size: .61rem;
}

.table-money {
    color: #73ffb8 !important;
    font-weight: 900;
    white-space: nowrap;
}

.table-money.expense { color: #ff7180 !important; }

.dashboard-status {
    display: inline-flex;
    align-items: center;
    min-height: 22px;
    padding: .25rem .48rem;
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 999px;
    background: rgba(255,255,255,.035);
    color: #aeb7c2 !important;
    font-size: .56rem;
    font-weight: 900;
    text-transform: capitalize;
}

.dashboard-status.completed,
.dashboard-status.successful,
.dashboard-status.confirmed,
.dashboard-status.paid,
.dashboard-status.received {
    border-color: rgba(57,242,154,.18);
    background: rgba(57,242,154,.08);
    color: var(--dash-green) !important;
}

.dashboard-status.pending { color: #ffc267 !important; }
.dashboard-status.cancelled { color: #ff7180 !important; }

/* Booking overview */
.booking-overview {
    display: grid;
    grid-template-columns: repeat(5, minmax(0,1fr));
    gap: .55rem;
}

.booking-overview-item {
    padding: .9rem;
    border: 1px solid rgba(255,255,255,.055);
    border-radius: 11px;
    background: rgba(255,255,255,.022);
    text-align: center;
}

.booking-overview-number {
    color: #fff !important;
    font-size: 1.15rem;
    font-weight: 900;
}

.booking-overview-label {
    margin-top: .28rem;
    color: #707a86 !important;
    font-size: .58rem;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.dashboard-empty {
    padding: 1.5rem;
    color: #69737f !important;
    font-size: .72rem;
    text-align: center;
}

/* Responsive */
@media (max-width: 1180px) {
    .dashboard-kpi-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
    .dashboard-main-grid { grid-template-columns: 1fr; }
    .booking-overview { grid-template-columns: repeat(3, minmax(0,1fr)); }
}

@media (max-width: 760px) {
    .dashboard-hero { align-items: flex-start; flex-direction: column; }
    .dashboard-date { text-align: left; }
    .dashboard-kpi-grid { grid-template-columns: 1fr; }
    .dashboard-two-column { grid-template-columns: 1fr; }
    .financial-summary { grid-template-columns: 1fr 1fr; }
    .booking-overview { grid-template-columns: repeat(2, minmax(0,1fr)); }
    .dashboard-table { min-width: 560px; }
}

@media (max-width: 480px) {
    .financial-summary { grid-template-columns: 1fr; }
    .booking-overview { grid-template-columns: 1fr 1fr; }
    .dashboard-filter a { flex: 1 1 calc(50% - .5rem); }
}

/* Donut color variables */
.dashboard-page {
    --donut-color-0: #39f29a;
    --donut-color-1: #36d8ff;
    --donut-color-2: #8b6cff;
    --donut-color-3: #f14bba;
    --donut-color-4: #ff9d45;
    --donut-color-5: #ff5e70;
}
</style>

<div class="dashboard-page">

    <div class="dashboard-hero">
        <div>
            <div class="dashboard-kicker">Imagination Nature Studio</div>
            <h1 class="dashboard-title">Business Dashboard</h1>
            <p class="dashboard-subtitle">
                Welcome back, <?= e($_SESSION['admin_name'] ?? 'Admin') ?>. Here is how the studio is performing.
            </p>
        </div>
        <div class="dashboard-date">
            <strong><?= e($periodLabel) ?></strong>
            <?= e($startDate) ?> — <?= e($endDate) ?>
        </div>
    </div>

    <div class="dashboard-filter">
        <a href="dashboard.php?period=today" class="<?= $period === 'today' ? 'active' : '' ?>">Today</a>
        <a href="dashboard.php?period=week" class="<?= $period === 'week' ? 'active' : '' ?>">This Week</a>
        <a href="dashboard.php?period=month" class="<?= $period === 'month' ? 'active' : '' ?>">This Month</a>
        <a href="dashboard.php?period=year" class="<?= $period === 'year' ? 'active' : '' ?>">This Year</a>
        <a href="dashboard.php" style="margin-left:auto;">↻ Refresh</a>
    </div>

    <!-- KPI CARDS -->
    <div class="dashboard-kpi-grid">

        <div class="dashboard-kpi kpi-green">
            <span class="dashboard-kpi-icon">↗</span>
            <span class="dashboard-kpi-label">Money Received</span>
            <div class="dashboard-kpi-value"><?= e(rwf($totalCollected)) ?></div>
            <div class="dashboard-kpi-note">Booking payments + product sales</div>
        </div>

        <div class="dashboard-kpi kpi-red">
            <span class="dashboard-kpi-icon">−</span>
            <span class="dashboard-kpi-label">Expenses</span>
            <div class="dashboard-kpi-value"><?= e(rwf($totalExpenses)) ?></div>
            <div class="dashboard-kpi-note">Recorded expenses · <?= e($periodLabel) ?></div>
        </div>

        <div class="dashboard-kpi kpi-blue">
            <span class="dashboard-kpi-icon">✓</span>
            <span class="dashboard-kpi-label">Net Profit</span>
            <div class="dashboard-kpi-value"><?= e(rwf($netProfit)) ?></div>
            <div class="dashboard-kpi-note">Cash received minus expenses</div>
        </div>

        <div class="dashboard-kpi kpi-purple">
            <span class="dashboard-kpi-icon">◷</span>
            <span class="dashboard-kpi-label">Outstanding</span>
            <div class="dashboard-kpi-value"><?= e(rwf($outstanding)) ?></div>
            <div class="dashboard-kpi-note">Pending + confirmed balances</div>
        </div>

        <div class="dashboard-kpi kpi-green">
            <span class="dashboard-kpi-icon">◉</span>
            <span class="dashboard-kpi-label">Cash in Hand</span>
            <div class="dashboard-kpi-value"><?= e(rwf($cashInHand)) ?></div>
            <div class="dashboard-kpi-note">All-time cash position</div>
        </div>

        <div class="dashboard-kpi kpi-orange">
            <span class="dashboard-kpi-icon">▣</span>
            <span class="dashboard-kpi-label">Product Sales</span>
            <div class="dashboard-kpi-value"><?= e(rwf($salesCollected)) ?></div>
            <div class="dashboard-kpi-note">Sales collected · <?= e($periodLabel) ?></div>
        </div>

        <div class="dashboard-kpi kpi-pink">
            <span class="dashboard-kpi-icon">#</span>
            <span class="dashboard-kpi-label">Total Bookings</span>
            <div class="dashboard-kpi-value"><?= e(dashNumber($totalBookings)) ?></div>
            <div class="dashboard-kpi-note"><?= e($todayBookings) ?> scheduled today</div>
        </div>

        <div class="dashboard-kpi kpi-blue">
            <span class="dashboard-kpi-icon">★</span>
            <span class="dashboard-kpi-label">Completed</span>
            <div class="dashboard-kpi-value"><?= e(dashNumber($completedBookings)) ?></div>
            <div class="dashboard-kpi-note">Completed bookings all-time</div>
        </div>

    </div>

    <!-- CHART + EXPENSE DONUT -->
    <div class="dashboard-main-grid">

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <div>
                    <h2 class="dashboard-card-title">Financial Flow</h2>
                    <p class="dashboard-card-subtitle">Revenue and expenses — last 6 months</p>
                </div>
                <div style="color:#6f7985;font-size:.62rem;">RWF</div>
            </div>

            <div class="chart-area">
                <div class="dashboard-chart-wrap">
                    <svg class="dashboard-chart-svg" viewBox="0 0 760 250" preserveAspectRatio="none" aria-label="Revenue and expenses chart">
                        <?php for ($g = 0; $g <= 4; $g++):
                            $gy = 18 + ($g * ((250 - 18 - 22) / 4));
                        ?>
                            <line class="chart-grid-line" x1="18" y1="<?= $gy ?>" x2="742" y2="<?= $gy ?>"></line>
                        <?php endfor; ?>

                        <polygon class="chart-revenue-area" points="<?= e($revenueAreaPoints) ?>"></polygon>
                        <polygon class="chart-expense-area" points="<?= e($expenseAreaPoints) ?>"></polygon>

                        <polyline class="chart-revenue-line" points="<?= e($revenuePolyline) ?>"></polyline>
                        <polyline class="chart-expense-line" points="<?= e($expensePolyline) ?>"></polyline>

                        <?php foreach ($chartRevenuePoints as $i => $point): ?>
                            <circle class="chart-dot-revenue" cx="<?= round($point[0],2) ?>" cy="<?= round($point[1],2) ?>" r="4"></circle>
                            <circle class="chart-dot-expense" cx="<?= round($chartExpensePoints[$i][0],2) ?>" cy="<?= round($chartExpensePoints[$i][1],2) ?>" r="3.5"></circle>
                            <text class="chart-x-label" x="<?= round($point[0],2) ?>" y="247" text-anchor="middle">
                                <?= e($monthlyChart[$i]['label']) ?>
                            </text>
                        <?php endforeach; ?>
                    </svg>
                </div>

                <div class="chart-legend">
                    <span class="legend-item"><span class="legend-dot legend-revenue"></span>Revenue</span>
                    <span class="legend-item"><span class="legend-dot legend-expense"></span>Expenses</span>
                    <span style="margin-left:auto;">6-month trend</span>
                </div>
            </div>
        </div>

        <div class="dashboard-card donut-panel">
            <div class="dashboard-card-header">
                <div>
                    <h2 class="dashboard-card-title">Expense Distribution</h2>
                    <p class="dashboard-card-subtitle"><?= e($periodLabel) ?></p>
                </div>
            </div>

            <div class="dashboard-card-body donut-body">
                <div class="donut-chart">
                    <div class="donut-center">
                        <strong><?= e(rwf($totalExpenses)) ?></strong>
                        <span>Total Expenses</span>
                    </div>
                </div>

                <div class="donut-legend">
                    <?php foreach (array_slice($expenseBreakdown, 0, 6) as $i => $expenseRow):
                        $expensePct = ((float)$expenseRow['total'] / $expenseTotalForChart) * 100;
                    ?>
                        <div class="donut-legend-item">
                            <span class="dot" style="background:var(--donut-color-<?= $i ?>)"></span>
                            <span class="name"><?= e($expenseRow['category']) ?></span>
                            <span class="amount"><?= e(number_format($expensePct,1)) ?>%</span>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!$expenseBreakdown): ?>
                        <div class="dashboard-empty" style="grid-column:1/-1;">No expenses recorded for this period.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- FINANCIAL SUMMARY + TOP SERVICES -->
    <div class="dashboard-two-column">

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <div>
                    <h2 class="dashboard-card-title">Financial Summary</h2>
                    <p class="dashboard-card-subtitle">Quick view of the current period</p>
                </div>
            </div>
            <div class="dashboard-card-body">
                <div class="financial-summary">
                    <div class="financial-item">
                        <div class="financial-item-label">Booking Payments</div>
                        <div class="financial-item-value green"><?= e(rwf($bookingPayments)) ?></div>
                    </div>
                    <div class="financial-item">
                        <div class="financial-item-label">Sales Collected</div>
                        <div class="financial-item-value green"><?= e(rwf($salesCollected)) ?></div>
                    </div>
                    <div class="financial-item">
                        <div class="financial-item-label">Sales Value</div>
                        <div class="financial-item-value"><?= e(rwf($salesValue)) ?></div>
                    </div>
                    <div class="financial-item">
                        <div class="financial-item-label">Expenses</div>
                        <div class="financial-item-value red"><?= e(rwf($totalExpenses)) ?></div>
                    </div>
                    <div class="financial-item">
                        <div class="financial-item-label">Net Profit</div>
                        <div class="financial-item-value <?= $netProfit >= 0 ? 'green' : 'red' ?>"><?= e(rwf($netProfit)) ?></div>
                    </div>
                    <div class="financial-item">
                        <div class="financial-item-label">Cash Flow</div>
                        <div class="financial-item-value <?= $cashFlow >= 0 ? 'green' : 'red' ?>"><?= e(rwf($cashFlow)) ?></div>
                    </div>
                    <div class="financial-item">
                        <div class="financial-item-label">Average Booking</div>
                        <div class="financial-item-value"><?= e(rwf($averageBookingValue)) ?></div>
                    </div>
                    <div class="financial-item">
                        <div class="financial-item-label">Upcoming</div>
                        <div class="financial-item-value"><?= e(dashNumber($upcomingBookings)) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <div>
                    <h2 class="dashboard-card-title">Top Services</h2>
                    <p class="dashboard-card-subtitle">Best-performing services by collected revenue</p>
                </div>
            </div>
            <div class="dashboard-card-body">
                <?php if ($topServices): ?>
                    <?php foreach ($topServices as $service):
                        $pct = ((float)$service['revenue'] / $serviceMax) * 100;
                    ?>
                        <div class="progress-row">
                            <div class="progress-top">
                                <span class="progress-name"><?= e($service['service_name']) ?></span>
                                <span class="progress-value"><?= e(rwf($service['revenue'])) ?></span>
                            </div>
                            <div class="progress-track">
                                <div class="progress-fill" style="width:<?= min(100,max(2,$pct)) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="dashboard-empty">No service bookings found for this period.</div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- RECENT TRANSACTIONS -->
    <div class="dashboard-card" style="margin-bottom:.9rem;">
        <div class="dashboard-card-header">
            <div>
                <h2 class="dashboard-card-title">Recent Transactions</h2>
                <p class="dashboard-card-subtitle">Latest money movement across the studio</p>
            </div>
            <a class="dashboard-link" href="expenses.php">View Expenses</a>
        </div>

        <div class="dashboard-table-wrap">
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recentTransactions): ?>
                        <?php foreach ($recentTransactions as $tx): ?>
                            <tr>
                                <td><?= e($tx['date']) ?></td>
                                <td><span class="table-main"><?= e($tx['description']) ?></span></td>
                                <td><?= e(ucfirst($tx['type'])) ?></td>
                                <td class="table-money <?= $tx['type'] === 'expense' ? 'expense' : '' ?>">
                                    <?= $tx['type'] === 'expense' ? '-' : '+' ?><?= e(rwf($tx['amount'])) ?>
                                </td>
                                <td><span class="dashboard-status <?= e(strtolower($tx['status'])) ?>"><?= e($tx['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5"><div class="dashboard-empty">No recent transactions.</div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- EXPENSES + SALES -->
    <div class="dashboard-two-column">

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <div>
                    <h2 class="dashboard-card-title">Recent Expenses</h2>
                    <p class="dashboard-card-subtitle">Latest studio expenses</p>
                </div>
            </div>
            <div class="dashboard-table-wrap">
                <table class="dashboard-table">
                    <thead><tr><th>Date</th><th>Description</th><th>Category</th><th>Amount</th></tr></thead>
                    <tbody>
                    <?php if ($recentExpenses): ?>
                        <?php foreach ($recentExpenses as $expenseRow): ?>
                            <tr>
                                <td><?= e($expenseRow['expense_date']) ?></td>
                                <td><span class="table-main"><?= e($expenseRow['description']) ?></span></td>
                                <td><?= e($expenseRow['category'] ?? 'Other') ?></td>
                                <td class="table-money expense">-<?= e(rwf($expenseRow['amount'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4"><div class="dashboard-empty">No expenses recorded.</div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <div>
                    <h2 class="dashboard-card-title">Recent Sales</h2>
                    <p class="dashboard-card-subtitle">Latest product / printing sales</p>
                </div>
            </div>
            <div class="dashboard-table-wrap">
                <table class="dashboard-table">
                    <thead><tr><th>Date</th><th>Product</th><th>Customer</th><th>Paid</th></tr></thead>
                    <tbody>
                    <?php if ($recentSales): ?>
                        <?php foreach ($recentSales as $sale): ?>
                            <tr>
                                <td><?= e($sale['sale_date']) ?></td>
                                <td><span class="table-main"><?= e($sale['product_name']) ?></span></td>
                                <td><?= e($sale['customer_name'] ?? 'Walk-in') ?></td>
                                <td class="table-money">+<?= e(rwf($sale['amount_paid'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4"><div class="dashboard-empty">No sales recorded.</div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- BOOKINGS -->
    <div class="dashboard-card" style="margin-bottom:.9rem;">
        <div class="dashboard-card-header">
            <div>
                <h2 class="dashboard-card-title">Recent Bookings</h2>
                <p class="dashboard-card-subtitle">Latest studio bookings and their status</p>
            </div>
            <a class="dashboard-link" href="bookings.php">View All</a>
        </div>

        <div class="dashboard-table-wrap">
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Booking</th>
                        <th>Client</th>
                        <th>Service</th>
                        <th>Date</th>
                        <th>Amount Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($recentBookings): ?>
                    <?php foreach ($recentBookings as $booking): ?>
                        <tr>
                            <td><span class="table-main"><?= e($booking['booking_number'] ?? ('#'.$booking['id'])) ?></span></td>
                            <td><?= e($booking['full_name'] ?? '—') ?></td>
                            <td><?= e($booking['service_name'] ?? '—') ?></td>
                            <td><?= e($booking['booking_date'] ?? '—') ?></td>
                            <td class="table-money">+<?= e(rwf($booking['amount_paid'] ?? 0)) ?></td>
                            <td><?= e(rwf($booking['balance'] ?? 0)) ?></td>
                            <td><span class="dashboard-status <?= e(strtolower($booking['booking_status'] ?? '')) ?>"><?= e($booking['booking_status'] ?? '—') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7"><div class="dashboard-empty">No bookings found.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- BOOKING OVERVIEW -->
    <div class="dashboard-card" style="margin-bottom:2rem;">
        <div class="dashboard-card-header">
            <div>
                <h2 class="dashboard-card-title">Booking Overview</h2>
                <p class="dashboard-card-subtitle">Current booking pipeline</p>
            </div>
        </div>
        <div class="dashboard-card-body">
            <div class="booking-overview">
                <div class="booking-overview-item"><div class="booking-overview-number"><?= e($totalBookings) ?></div><div class="booking-overview-label">All Bookings</div></div>
                <div class="booking-overview-item"><div class="booking-overview-number"><?= e($pendingBookings) ?></div><div class="booking-overview-label">Pending</div></div>
                <div class="booking-overview-item"><div class="booking-overview-number"><?= e($confirmedBookings) ?></div><div class="booking-overview-label">Confirmed</div></div>
                <div class="booking-overview-item"><div class="booking-overview-number"><?= e($completedBookings) ?></div><div class="booking-overview-label">Completed</div></div>
                <div class="booking-overview-item"><div class="booking-overview-number"><?= e($cancelledBookings) ?></div><div class="booking-overview-label">Cancelled</div></div>
            </div>
        </div>
    </div>

</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
