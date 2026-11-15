<?php
/**
 * Admin — Monthly report.
 * Shows every day of a chosen month with sales, expenses, profit, cash.
 */
require_once __DIR__ . '/../includes/ops.php';
require_admin();

$pdo   = db();
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');

$from = ops_month_start($month);
$to   = ops_month_end($month);

/* Daily breakdown */
$sql = "
  SELECT d.day,
    (SELECT COALESCE(SUM(total),0) FROM sales WHERE sale_date = d.day) AS sales_total,
    (SELECT COALESCE(SUM(amount_paid),0) FROM sales WHERE sale_date = d.day) AS sales_paid,
    (SELECT COALESCE(SUM(balance),0) FROM sales WHERE sale_date = d.day) AS sales_balance,
    (SELECT COUNT(*) FROM sales WHERE sale_date = d.day) AS sales_count,
    (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date = d.day) AS expenses_total,
    (SELECT COUNT(*) FROM expenses WHERE expense_date = d.day) AS expenses_count
  FROM (
    SELECT DATE_ADD(?, INTERVAL seq DAY) AS day
    FROM (
      SELECT 0 AS seq UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
      UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
      UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14
      UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19
      UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24
      UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29
      UNION SELECT 30 UNION SELECT 31
    ) s
  ) d
  WHERE d.day BETWEEN ? AND ?
  HAVING sales_count > 0 OR expenses_count > 0
  ORDER BY d.day
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$from, $from, $to]);
$days = $stmt->fetchAll();

/* Month totals */
$mSales = ops_sales_total($from, $to);
$mExp   = ops_expenses_total($from, $to);
$mBk    = ops_bookings_total($from, $to);

$monthSales = (float)$mSales['total'];
$monthExp   = (float)$mExp['total'];
$monthNet   = $monthSales - $monthExp;
$monthCash  = (float)$mSales['paid'] - $monthExp;

/* Export */
if (isset($_GET['export'])) {
    $rows = [];
    $rows[] = ['DAILY STUDIO REPORT ' . $month];
    $rows[] = [];
    $rows[] = ['#','Date','Sales count','Sales total','Paid','Balance','Expenses count','Expenses total','Net'];

    $i = 1;
    foreach ($days as $d) {
        $rows[] = [
            $i++,
            $d['day'],
            $d['sales_count'],
            $d['sales_total'],
            $d['sales_paid'],
            $d['sales_balance'],
            $d['expenses_count'],
            $d['expenses_total'],
            (float)$d['sales_total'] - (float)$d['expenses_total'],
        ];
    }

    $rows[] = ['','TOTAL','', $mSales['total'], $mSales['paid'], $mSales['balance'], $mExp['count'], $mExp['total'], $monthNet];
    $rows[] = [];
    $rows[] = ['SUMMARY'];
    $rows[] = ['Gross sales',          $monthSales];
    $rows[] = ['Total expenses',       $monthExp];
    $rows[] = ['Net profit',           $monthNet];
    $rows[] = ['Cash in hand',         $monthCash];
    $rows[] = ['Outstanding balance',  $mSales['balance']];

    ops_csv_download('monthly-report-' . $month . '.csv', ['MONTHLY STUDIO REPORT'], $rows);
}

require __DIR__ . '/includes/admin-header.php';
?>

<style>
  @media print {
    .admin-side, .admin-topbar, .no-print { display: none !important; }
    .admin-shell { grid-template-columns: 1fr !important; }
    .admin-main { padding: 0 !important; }
    body, .admin-body { background: #fff !important; color: #000 !important; }
    .card { background: #fff !important; color: #000 !important; border-color: #ddd !important; }
  }
</style>

<div class="no-print" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1rem">
  <div>
    <h1 style="margin:0 0 .3rem">Monthly Report</h1>
    <p style="color:#7a716a;margin:0"><?= e(date('F Y', strtotime($from))) ?></p>
  </div>

  <div style="display:flex;gap:.6rem;flex-wrap:wrap">
    <form method="get" style="display:flex;gap:.6rem">
      <input type="month" name="month" value="<?= e($month) ?>" style="width:auto">
      <button class="btn btn-sm btn-primary">View</button>
    </form>
    <a class="btn btn-sm btn-ghost" href="monthly-report.php?month=<?= e($month) ?>&export=1">⬇ CSV</a>
    <button class="btn btn-sm btn-ghost" onclick="window.print()">🖨 Print</button>
  </div>
</div>

<div class="grid grid-4" style="margin-bottom:1.4rem">
  <div class="card" style="padding:1.1rem 1.3rem">
    <div style="font-size:.7rem;letter-spacing:.16em;text-transform:uppercase;color:#7a716a">Month sales</div>
    <div style="font-family:var(--font-serif);font-size:1.7rem"><?= e(ops_money($monthSales)) ?></div>
    <div style="color:#6a6159;font-size:.75rem"><?= (int)$mSales['count'] ?> items</div>
  </div>
  <div class="card" style="padding:1.1rem 1.3rem">
    <div style="font-size:.7rem;letter-spacing:.16em;text-transform:uppercase;color:#7a716a">Month expenses</div>
    <div style="font-family:var(--font-serif);font-size:1.7rem;color:#f0b3b3"><?= e(ops_money($monthExp)) ?></div>
    <div style="color:#6a6159;font-size:.75rem"><?= (int)$mExp['count'] ?> entries</div>
  </div>
  <div class="card" style="padding:1.1rem 1.3rem;border-color:<?= $monthNet >= 0 ? 'rgba(52,168,83,.5)' : 'rgba(200,60,60,.5)' ?>">
    <div style="font-size:.7rem;letter-spacing:.16em;text-transform:uppercase;color:#7a716a">Net profit</div>
    <div style="font-family:var(--font-serif);font-size:1.7rem;color:<?= $monthNet >= 0 ? '#bfe7c8' : '#f0b3b3' ?>">
      <?= e(ops_money($monthNet)) ?>
    </div>
  </div>
  <div class="card" style="padding:1.1rem 1.3rem">
    <div style="font-size:.7rem;letter-spacing:.16em;text-transform:uppercase;color:#7a716a">Cash in hand</div>
    <div style="font-family:var(--font-serif);font-size:1.7rem;color:<?= $monthCash >= 0 ? '#bfe7c8' : '#f0b3b3' ?>">
      <?= e(ops_money($monthCash)) ?>
    </div>
  </div>
</div>

<div class="card" style="padding:0">
  <div class="admin-table-wrap" style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
      <thead><tr style="text-align:left;font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:#7a716a">
        <th style="padding:.9rem 1rem">Date</th>
        <th>Items</th>
        <th>Sales</th>
        <th>Paid</th>
        <th>Balance</th>
        <th>Expenses</th>
        <th>Net</th>
        <th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($days as $d): ?>
        <?php $net = (float)$d['sales_total'] - (float)$d['expenses_total']; ?>
        <tr style="border-top:1px solid #262220">
          <td style="padding:.9rem 1rem;white-space:nowrap">
            <?= e(ops_format_date($d['day'])) ?><br>
            <span style="color:#6a6159;font-size:.75rem"><?= e(date('D', strtotime($d['day']))) ?></span>
          </td>
          <td><?= (int)$d['sales_count'] ?></td>
          <td><?= e(ops_money($d['sales_total'])) ?></td>
          <td><?= e(ops_money($d['sales_paid'])) ?></td>
          <td style="color:<?= (float)$d['sales_balance'] > 0 ? '#f0b3b3' : '#6a6159' ?>">
            <?= e(ops_money($d['sales_balance'])) ?>
          </td>
          <td style="color:#f0b3b3"><?= e(ops_money($d['expenses_total'])) ?></td>
          <td style="color:<?= $net >= 0 ? '#bfe7c8' : '#f0b3b3' ?>"><strong><?= e(ops_money($net)) ?></strong></td>
          <td>
            <a class="btn btn-sm btn-ghost" href="daily-report.php?date=<?= e($d['day']) ?>">View</a>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if (!$days): ?>
        <tr><td colspan="8" style="padding:3rem;text-align:center;color:#7a716a">
          No activity this month.
        </td></tr>
      <?php endif; ?>
      </tbody>

      <?php if ($days): ?>
      <tfoot>
        <tr style="border-top:2px solid var(--accent);background:#100e0c">
          <td style="padding:1rem;font-weight:600">TOTAL</td>
          <td><?= (int)$mSales['count'] ?></td>
          <td><strong><?= e(ops_money($mSales['total'])) ?></strong></td>
          <td><?= e(ops_money($mSales['paid'])) ?></td>
          <td style="color:#f0b3b3"><?= e(ops_money($mSales['balance'])) ?></td>
          <td style="color:#f0b3b3"><strong><?= e(ops_money($mExp['total'])) ?></strong></td>
          <td style="color:<?= $monthNet >= 0 ? '#bfe7c8' : '#f0b3b3' ?>"><strong><?= e(ops_money($monthNet)) ?></strong></td>
          <td></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>