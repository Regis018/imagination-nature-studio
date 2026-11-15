<?php
/**
 * Admin — Weekly report.
 * Pick a start date, see 7 days of sales, expenses, profit.
 */
require_once __DIR__ . '/../includes/ops.php';
require_admin();   // staff allowed

$pdo = db();

/* ---------- Determine the week ---------- */
$startInput = $_GET['start'] ?? date('Y-m-d', strtotime('monday this week'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startInput)) {
    $startInput = date('Y-m-d', strtotime('monday this week'));
}

/* Force to Monday */
$start = date('Y-m-d', strtotime('monday', strtotime($startInput)));
$end   = date('Y-m-d', strtotime($start . ' +6 days'));

/* ---------- Daily breakdown ---------- */
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
      SELECT 0 AS seq UNION SELECT 1 UNION SELECT 2 UNION SELECT 3
      UNION SELECT 4 UNION SELECT 5 UNION SELECT 6
    ) s
  ) d
  ORDER BY d.day
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start]);
$days = $stmt->fetchAll();

/* ---------- Week totals ---------- */
$wSales = ops_sales_total($start, $end);
$wExp   = ops_expenses_total($start, $end);

$weekSales = (float)$wSales['total'];
$weekExp   = (float)$wExp['total'];
$weekNet   = $weekSales - $weekExp;
$weekCash  = (float)$wSales['paid'] - $weekExp;

/* ---------- CSV export ---------- */
if (isset($_GET['export'])) {
    $rows = [];
    $rows[] = ['WEEKLY STUDIO REPORT'];
    $rows[] = ['Week starting', $start, 'Ending', $end];
    $rows[] = [];
    $rows[] = ['#','Date','Day','Sales count','Sales total','Paid','Balance','Expenses count','Expenses total','Net'];

    $i = 1;
    foreach ($days as $d) {
        $rows[] = [
            $i++,
            $d['day'],
            date('D', strtotime($d['day'])),
            $d['sales_count'],
            $d['sales_total'],
            $d['sales_paid'],
            $d['sales_balance'],
            $d['expenses_count'],
            $d['expenses_total'],
            (float)$d['sales_total'] - (float)$d['expenses_total'],
        ];
    }

    $rows[] = ['','','TOTAL',$wSales['count'], $wSales['total'], $wSales['paid'], $wSales['balance'], $wExp['count'], $wExp['total'], $weekNet];
    $rows[] = [];
    $rows[] = ['SUMMARY'];
    $rows[] = ['Gross sales', $weekSales];
    $rows[] = ['Total expenses', $weekExp];
    $rows[] = ['Net profit', $weekNet];
    $rows[] = ['Cash in hand', $weekCash];

    ops_csv_download('weekly-report-' . $start . '.csv', ['WEEKLY STUDIO REPORT'], $rows);
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
    <h1 style="margin:0 0 .3rem">Weekly Report</h1>
    <p style="color:#7a716a;margin:0">
      <?= e(date('d M Y', strtotime($start))) ?> → <?= e(date('d M Y', strtotime($end))) ?>
    </p>
  </div>

  <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center">
    <form method="get" style="display:flex;gap:.6rem">
      <input type="date" name="start" value="<?= e($startInput) ?>" style="width:auto">
      <button class="btn btn-sm btn-primary">View</button>
    </form>
    <a class="btn btn-sm btn-ghost" href="?start=<?= e($start) ?>&export=1">⬇ CSV</a>
    <button class="btn btn-sm btn-ghost" onclick="window.print()">🖨 Print</button>
  </div>
</div>

<!-- Navigation -->
<div class="no-print" style="display:flex;gap:.5rem;margin-bottom:1.4rem;flex-wrap:wrap">
  <?php
    $prev = date('Y-m-d', strtotime($start . ' -7 days'));
    $next = date('Y-m-d', strtotime($start . ' +7 days'));
    $thisMon = date('Y-m-d', strtotime('monday this week'));
  ?>
  <a class="btn btn-sm btn-ghost" href="?start=<?= $prev ?>">← Previous week</a>
  <a class="btn btn-sm btn-primary" href="?start=<?= $thisMon ?>">This week</a>
  <a class="btn btn-sm btn-ghost" href="?start=<?= $next ?>">Next week →</a>
</div>

<!-- Summary -->
<div class="grid grid-4" style="margin-bottom:1.4rem">
  <div class="card" style="padding:1.1rem 1.3rem">
    <div style="font-size:.7rem;letter-spacing:.16em;text-transform:uppercase;color:#7a716a">Week sales</div>
    <div style="font-family:var(--font-serif);font-size:1.7rem"><?= e(ops_money($weekSales)) ?></div>
    <div style="color:#6a6159;font-size:.75rem"><?= (int)$wSales['count'] ?> items</div>
  </div>
  <div class="card" style="padding:1.1rem 1.3rem">
    <div style="font-size:.7rem;letter-spacing:.16em;text-transform:uppercase;color:#7a716a">Week expenses</div>
    <div style="font-family:var(--font-serif);font-size:1.7rem;color:#f0b3b3"><?= e(ops_money($weekExp)) ?></div>
    <div style="color:#6a6159;font-size:.75rem"><?= (int)$wExp['count'] ?> entries</div>
  </div>
  <div class="card" style="padding:1.1rem 1.3rem;border-color:<?= $weekNet >= 0 ? 'rgba(52,168,83,.5)' : 'rgba(200,60,60,.5)' ?>">
    <div style="font-size:.7rem;letter-spacing:.16em;text-transform:uppercase;color:#7a716a">Net profit</div>
    <div style="font-family:var(--font-serif);font-size:1.7rem;color:<?= $weekNet >= 0 ? '#bfe7c8' : '#f0b3b3' ?>">
      <?= e(ops_money($weekNet)) ?>
    </div>
  </div>
  <div class="card" style="padding:1.1rem 1.3rem">
    <div style="font-size:.7rem;letter-spacing:.16em;text-transform:uppercase;color:#7a716a">Cash in hand</div>
    <div style="font-family:var(--font-serif);font-size:1.7rem;color:<?= $weekCash >= 0 ? '#bfe7c8' : '#f0b3b3' ?>">
      <?= e(ops_money($weekCash)) ?>
    </div>
  </div>
</div>

<!-- Daily table -->
<div class="card" style="padding:0">
  <div class="admin-table-wrap" style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
      <thead><tr style="text-align:left;font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:#7a716a">
        <th style="padding:.9rem 1rem">Day</th>
        <th>Items</th>
        <th>Sales</th>
        <th>Paid</th>
        <th>Balance</th>
        <th>Expenses</th>
        <th>Net</th>
        <th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($days as $d): $net = (float)$d['sales_total'] - (float)$d['expenses_total']; ?>
        <tr style="border-top:1px solid #262220">
          <td style="padding:.9rem 1rem;white-space:nowrap">
            <?= e(date('d M', strtotime($d['day']))) ?><br>
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
      </tbody>
      <tfoot>
        <tr style="border-top:2px solid var(--accent);background:#100e0c">
          <td style="padding:1rem;font-weight:600">TOTAL</td>
          <td><?= (int)$wSales['count'] ?></td>
          <td><strong><?= e(ops_money($wSales['total'])) ?></strong></td>
          <td><?= e(ops_money($wSales['paid'])) ?></td>
          <td style="color:#f0b3b3"><?= e(ops_money($wSales['balance'])) ?></td>
          <td style="color:#f0b3b3"><strong><?= e(ops_money($wExp['total'])) ?></strong></td>
          <td style="color:<?= $weekNet >= 0 ? '#bfe7c8' : '#f0b3b3' ?>"><strong><?= e(ops_money($weekNet)) ?></strong></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>