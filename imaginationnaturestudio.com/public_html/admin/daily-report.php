<?php
/**
 * Admin — Daily closing report.
 * Pick a date, see: sales, expenses, walk-in bookings, totals, cash in hand.
 * Export CSV / print.
 */
require_once __DIR__ . '/../includes/ops.php';
require_admin();

$pdo  = db();
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

/* Sales */
$sStmt = $pdo->prepare("SELECT * FROM sales WHERE sale_date = ? ORDER BY id");
$sStmt->execute([$date]);
$sales = $sStmt->fetchAll();

$salesTotals = ops_sales_total($date, $date);

/* Expenses */
$eStmt = $pdo->prepare("SELECT * FROM expenses WHERE expense_date = ? ORDER BY id");
$eStmt->execute([$date]);
$expenses = $eStmt->fetchAll();

$expTotals = ops_expenses_total($date, $date);

/* Walk-in bookings */
$bStmt = $pdo->prepare("SELECT * FROM walkin_bookings WHERE booking_date = ? ORDER BY id");
$bStmt->execute([$date]);
$bookings = $bStmt->fetchAll();

$bkTotals = ops_bookings_total($date, $date);

/* Computed */
$grossSales     = (float)$salesTotals['total'];
$totalExpenses  = (float)$expTotals['total'];
$netProfit      = $grossSales - $totalExpenses;
$cashInHand     = (float)$salesTotals['paid'] - $totalExpenses;
$outstanding    = (float)$salesTotals['balance'];

/* Export CSV if requested */
if (isset($_GET['export'])) {
    $csvRows = [];
    $csvRows[] = ['DAILY STUDIO REPORT', ops_format_date($date)];
    $csvRows[] = [];

    $csvRows[] = ['SALES'];
    $csvRows[] = ['#','Customer','Phone','Product','Description','Qty','Unit','Total','Paid','Balance','Method'];
    $i = 1;
    foreach ($sales as $s) {
        $csvRows[] = [
            $i++,
            $s['customer_name'],
            $s['customer_phone'],
            $s['product_name'],
            $s['description'],
            $s['quantity'],
            $s['unit_price'],
            $s['total'],
            $s['amount_paid'],
            $s['balance'],
            $s['payment_method'],
        ];
    }
    $csvRows[] = ['','','','','SUBTOTAL',$salesTotals['count'],'', $salesTotals['total'], $salesTotals['paid'], $salesTotals['balance'],''];

    $csvRows[] = [];
    $csvRows[] = ['EXPENSES'];
    $csvRows[] = ['#','Date','Description','Category','Amount','Staff'];
    $i = 1;
    foreach ($expenses as $e) {
        $csvRows[] = [$i++, $e['expense_date'], $e['description'], $e['category'], $e['amount'], $e['staff_name']];
    }
    $csvRows[] = ['','','','SUBTOTAL', $expTotals['total'],''];

    $csvRows[] = [];
    $csvRows[] = ['WALK-IN BOOKINGS'];
    $csvRows[] = ['#','Booking','Customer','Contact','Description','Event','Total','Advance','Balance','Status'];
    $i = 1;
    foreach ($bookings as $b) {
        $csvRows[] = [
            $i++,
            $b['booking_date'],
            $b['customer_name'],
            $b['customer_contact'],
            $b['description'],
            $b['event_date'],
            $b['total_amount'],
            $b['advance'],
            $b['balance'],
            $b['status'],
        ];
    }
    $csvRows[] = ['','','','','SUBTOTAL','', $bkTotals['total'], $bkTotals['advance'], $bkTotals['balance'],''];

    $csvRows[] = [];
    $csvRows[] = ['SUMMARY'];
    $csvRows[] = ['Gross sales',        $grossSales];
    $csvRows[] = ['Total expenses',     $totalExpenses];
    $csvRows[] = ['Net profit',         $netProfit];
    $csvRows[] = ['Cash in hand',       $cashInHand];
    $csvRows[] = ['Outstanding balance',$outstanding];

    ops_csv_download('daily-report-' . $date . '.csv', ['DAILY STUDIO REPORT'], $csvRows);
}

require __DIR__ . '/includes/admin-header.php';
?>

<!-- CLIENT FORM LINK: available to Admin and Staff on this page -->
<?php
$clientFormUrl = 'https://imaginationnaturestudio.com/photo-order.php';
$clientFormMessage = "Muraho! Imagination Nature Studio irabasaba kuzuza amakuru yanyu kuri iyi form: " . $clientFormUrl;
$clientFormWhatsAppUrl = 'https://wa.me/?text=' . rawurlencode($clientFormMessage);
?>

<section class="client-form-link-card no-print" aria-labelledby="client-form-link-title">
  <div class="client-form-link-copy">
    <div class="client-form-link-icon" aria-hidden="true">🔗</div>
    <div class="client-form-link-details">
      <h2 id="client-form-link-title">Client Form Link</h2>
      <p>Copy this link and send it to the client on WhatsApp so they can fill in their information.</p>
      <label for="clientFormUrlField">Customer form link</label>
      <input id="clientFormUrlField" type="text" value="<?= e($clientFormUrl) ?>" readonly onclick="this.select()">
      <span id="clientFormCopyStatus" role="status" aria-live="polite"></span>
    </div>
  </div>
  <div class="client-form-link-actions">
    <button type="button" class="client-form-btn client-form-btn-copy" onclick="copyClientFormLink()">📋 Copy Link</button>
    <a class="client-form-btn client-form-btn-whatsapp" href="<?= e($clientFormWhatsAppUrl) ?>" target="_blank" rel="noopener">WhatsApp Message ↗</a>
  </div>
</section>

<style>
.client-form-link-card{display:flex;align-items:center;justify-content:space-between;gap:22px;margin:0 0 24px;padding:20px;border:1px solid rgba(54,216,255,.28);border-radius:16px;background:linear-gradient(135deg,rgba(54,216,255,.09),rgba(57,242,154,.06));color:inherit}
.client-form-link-copy{display:flex;align-items:flex-start;gap:14px;min-width:0;flex:1}
.client-form-link-icon{display:grid;place-items:center;width:44px;height:44px;flex:0 0 44px;border-radius:12px;background:rgba(54,216,255,.14);font-size:22px}
.client-form-link-details h2{margin:0 0 6px;font-size:19px}
.client-form-link-details p{margin:0 0 12px;opacity:.8;line-height:1.5}
.client-form-link-details label{display:block;margin-bottom:5px;font-size:12px;font-weight:700}
#clientFormUrlField{display:block;width:min(560px,100%);box-sizing:border-box;padding:11px 12px;border:1px solid rgba(127,127,127,.4);border-radius:8px;background:rgba(127,127,127,.08);color:inherit;font-size:14px}
#clientFormCopyStatus{display:block;min-height:18px;margin-top:5px;font-size:12px;color:#15965c}
.client-form-link-actions{display:flex;flex-wrap:wrap;gap:9px;align-items:center}
.client-form-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:11px 14px;border:0;border-radius:9px;font:inherit;font-weight:700;text-decoration:none;cursor:pointer;white-space:nowrap}
.client-form-btn-copy{background:#1677e8;color:#fff}
.client-form-btn-whatsapp{background:#159b5a;color:#fff}
@media(max-width:760px){.client-form-link-card{align-items:stretch;flex-direction:column}.client-form-link-actions{display:grid;grid-template-columns:1fr 1fr}.client-form-btn{white-space:normal;text-align:center}}
@media(max-width:430px){.client-form-link-actions{grid-template-columns:1fr}}
</style>
<script>
async function copyClientFormLink(){
  const field=document.getElementById('clientFormUrlField');
  const status=document.getElementById('clientFormCopyStatus');
  try{
    if(navigator.clipboard && window.isSecureContext){
      await navigator.clipboard.writeText(field.value);
    }else{
      field.focus();field.select();field.setSelectionRange(0,field.value.length);
      if(!document.execCommand('copy')) throw new Error('Copy failed');
    }
    status.textContent='Link copied! You can now paste it into WhatsApp.';
  }catch(err){
    field.focus();field.select();field.setSelectionRange(0,field.value.length);
    status.textContent='Select the link and copy it manually (Ctrl+C / Cmd+C).';
  }
}
</script>


<style>
  @media print {
    .admin-side, .admin-topbar, .no-print { display: none !important; }
    .admin-shell { grid-template-columns: 1fr !important; }
    .admin-main { padding: 0 !important; }
    body, .admin-body { background: #fff !important; color: #000 !important; }
    .card { border-color: #ddd !important; background: #fff !important; color: #000 !important; }
    table { color: #000 !important; }
    h1, h2, h3 { color: #000 !important; }
    .stat-card { border: 1px solid #ddd !important; background: #f9f9f9 !important; }
    .stat-card .val { color: #000 !important; }
  }
</style>

<div class="no-print" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1rem">
  <div>
    <h1 style="margin:0 0 .3rem">Daily Report</h1>
    <p style="color:#7a716a;margin:0">Closing report for <?= e(date('d F Y', strtotime($date))) ?></p>
  </div>

  <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center">
    <form method="get" style="display:flex;gap:.6rem">
      <input type="date" name="date" value="<?= e($date) ?>" style="width:auto">
      <button class="btn btn-sm btn-primary">View</button>
    </form>
    <a class="btn btn-sm btn-ghost" href="daily-report.php?date=<?= e($date) ?>&export=1">⬇ CSV</a>
    <button class="btn btn-sm btn-ghost" onclick="window.print()">🖨 Print</button>
  </div>
</div>

<?php if ($m = flash('ok')): ?><div class="alert alert-success no-print"><?= e($m) ?></div><?php endif; ?>

<!-- ========== SUMMARY CARDS ========== -->
<div class="grid grid-4" style="margin-bottom:1.4rem">
  <div class="card stat-card" style="padding:1.1rem 1.3rem">
    <div style="font-size:.7rem;letter-spacing:.16em;text-transform:uppercase;color:#7a716a">Gross sales</div>
    <div class="val" style="font-family:var(--font-serif);font-size:1.7rem"><?= e(ops_money($grossSales)) ?></div>
    <div style="color:#6a6159;font-size:.75rem"><?= (int)$salesTotals['count'] ?> items</div>
  </div>
  <div class="card stat-card" style="padding:1.1rem 1.3rem">
    <div style="font-size:.7rem;letter-spacing:.16em;text-transform:uppercase;color:#7a716a">Expenses</div>
    <div class="val" style="font-family:var(--font-serif);font-size:1.7rem;color:#f0b3b3"><?= e(ops_money($totalExpenses)) ?></div>
    <div style="color:#6a6159;font-size:.75rem"><?= (int)$expTotals['count'] ?> entries</div>
  </div>
  <div class="card stat-card" style="padding:1.1rem 1.3rem;border-color:<?= $netProfit >= 0 ? 'rgba(52,168,83,.5)' : 'rgba(200,60,60,.5)' ?>">
    <div style="font-size:.7rem;letter-spacing:.16em;text-transform:uppercase;color:#7a716a">Net profit</div>
    <div class="val" style="font-family:var(--font-serif);font-size:1.7rem;color:<?= $netProfit >= 0 ? '#bfe7c8' : '#f0b3b3' ?>">
      <?= e(ops_money($netProfit)) ?>
    </div>
  </div>
  <div class="card stat-card" style="padding:1.1rem 1.3rem">
    <div style="font-size:.7rem;letter-spacing:.16em;text-transform:uppercase;color:#7a716a">Cash in hand</div>
    <div class="val" style="font-family:var(--font-serif);font-size:1.7rem;color:<?= $cashInHand >= 0 ? '#bfe7c8' : '#f0b3b3' ?>">
      <?= e(ops_money($cashInHand)) ?>
    </div>
    <div style="color:#6a6159;font-size:.75rem">Paid − Expenses</div>
  </div>
</div>

<!-- ========== SALES ========== -->
<div class="card" style="padding:0;margin-bottom:1.4rem">
  <div style="padding:1rem 1.2rem;border-bottom:1px solid #262220;display:flex;justify-content:space-between;align-items:center">
    <h3 style="font-family:var(--font-serif);font-size:1.05rem;margin:0">Sales</h3>
    <span style="color:#7a716a;font-size:.85rem">
      Total <strong style="color:var(--accent-2)"><?= e(ops_money($salesTotals['total'])) ?></strong>
      · Paid <?= e(ops_money($salesTotals['paid'])) ?>
      · Balance <?= e(ops_money($salesTotals['balance'])) ?>
    </span>
  </div>

  <div class="admin-table-wrap" style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
      <thead><tr style="text-align:left;font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:#7a716a">
        <th style="padding:.7rem 1rem">#</th>
        <th>Customer</th>
        <th>Product</th>
        <th>Qty</th>
        <th>Unit</th>
        <th>Total</th>
        <th>Paid</th>
        <th>Balance</th>
        <th>Method</th>
      </tr></thead>
      <tbody>
      <?php $i = 1; foreach ($sales as $s): ?>
        <tr style="border-top:1px solid #262220">
          <td style="padding:.7rem 1rem"><?= $i++ ?></td>
          <td><?= e($s['customer_name'] ?: '—') ?><br><span style="color:#7a716a;font-size:.75rem"><?= e($s['customer_phone']) ?></span></td>
          <td><?= e($s['product_name']) ?><?php if ($s['description']): ?><br><span style="color:#7a716a;font-size:.75rem"><?= e($s['description']) ?></span><?php endif; ?></td>
          <td><?= (int)$s['quantity'] ?></td>
          <td><?= e(ops_money($s['unit_price'])) ?></td>
          <td><strong><?= e(ops_money($s['total'])) ?></strong></td>
          <td><?= e(ops_money($s['amount_paid'])) ?></td>
          <td style="color:<?= (float)$s['balance'] > 0 ? '#f0b3b3' : '#6a6159' ?>"><?= e(ops_money($s['balance'])) ?></td>
          <td><span style="color:#7a716a;font-size:.78rem"><?= e(str_replace('_',' ', $s['payment_method'])) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$sales): ?>
        <tr><td colspan="9" style="padding:2rem;text-align:center;color:#7a716a">No sales today.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ========== EXPENSES ========== -->
<div class="card" style="padding:0;margin-bottom:1.4rem">
  <div style="padding:1rem 1.2rem;border-bottom:1px solid #262220;display:flex;justify-content:space-between;align-items:center">
    <h3 style="font-family:var(--font-serif);font-size:1.05rem;margin:0">Expenses</h3>
    <span style="color:#7a716a;font-size:.85rem">
      Total <strong style="color:#f0b3b3"><?= e(ops_money($expTotals['total'])) ?></strong>
    </span>
  </div>

  <div class="admin-table-wrap" style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
      <thead><tr style="text-align:left;font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:#7a716a">
        <th style="padding:.7rem 1rem">#</th>
        <th>Description</th>
        <th>Category</th>
        <th>Amount</th>
        <th>Staff</th>
      </tr></thead>
      <tbody>
      <?php $i = 1; foreach ($expenses as $e): ?>
        <tr style="border-top:1px solid #262220">
          <td style="padding:.7rem 1rem"><?= $i++ ?></td>
          <td><?= e($e['description']) ?></td>
          <td><span style="color:#7a716a;font-size:.82rem"><?= e($e['category']) ?></span></td>
          <td><strong style="color:#f0b3b3"><?= e(ops_money($e['amount'])) ?></strong></td>
          <td><span style="color:#7a716a;font-size:.82rem"><?= e($e['staff_name'] ?: '—') ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$expenses): ?>
        <tr><td colspan="5" style="padding:2rem;text-align:center;color:#7a716a">No expenses today.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ========== WALK-IN BOOKINGS ========== -->
<div class="card" style="padding:0;margin-bottom:1.4rem">
  <div style="padding:1rem 1.2rem;border-bottom:1px solid #262220;display:flex;justify-content:space-between;align-items:center">
    <h3 style="font-family:var(--font-serif);font-size:1.05rem;margin:0">Walk-in bookings</h3>
    <span style="color:#7a716a;font-size:.85rem">
      Total <strong><?= e(ops_money($bkTotals['total'])) ?></strong>
      · Advance <?= e(ops_money($bkTotals['advance'])) ?>
      · Balance <?= e(ops_money($bkTotals['balance'])) ?>
    </span>
  </div>

  <div class="admin-table-wrap" style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
      <thead><tr style="text-align:left;font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:#7a716a">
        <th style="padding:.7rem 1rem">#</th>
        <th>Customer</th>
        <th>Description</th>
        <th>Event</th>
        <th>Total</th>
        <th>Advance</th>
        <th>Balance</th>
        <th>Status</th>
      </tr></thead>
      <tbody>
      <?php $i = 1; foreach ($bookings as $b): ?>
        <tr style="border-top:1px solid #262220">
          <td style="padding:.7rem 1rem"><?= $i++ ?></td>
          <td><?= e($b['customer_name']) ?><br><span style="color:#7a716a;font-size:.75rem"><?= e($b['customer_contact']) ?></span></td>
          <td><?= e($b['description']) ?></td>
          <td><?= e($b['event_date'] ? ops_format_date($b['event_date']) : '—') ?></td>
          <td><strong><?= e(ops_money($b['total_amount'])) ?></strong></td>
          <td><?= e(ops_money($b['advance'])) ?></td>
          <td style="color:<?= (float)$b['balance'] > 0 ? '#f0b3b3' : '#6a6159' ?>"><?= e(ops_money($b['balance'])) ?></td>
          <td><span class="status-pill <?= e($b['status']) ?>"><?= e($b['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$bookings): ?>
        <tr><td colspan="8" style="padding:2rem;text-align:center;color:#7a716a">No walk-in bookings today.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ========== CLOSING SUMMARY ========== -->
<div class="card" style="padding:1.6rem 1.8rem">
  <h3 style="font-family:var(--font-serif);font-size:1.15rem;margin:0 0 1.2rem">Closing summary</h3>

  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1rem">
    <div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px dashed #2a2523">
      <span style="color:#7a716a">Gross sales</span>
      <strong><?= e(ops_money($grossSales)) ?></strong>
    </div>
    <div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px dashed #2a2523">
      <span style="color:#7a716a">Cash received</span>
      <strong><?= e(ops_money($salesTotals['paid'])) ?></strong>
    </div>
    <div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px dashed #2a2523">
      <span style="color:#7a716a">Total expenses</span>
      <strong style="color:#f0b3b3"><?= e(ops_money($totalExpenses)) ?></strong>
    </div>
    <div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px dashed #2a2523">
      <span style="color:#7a716a">Net profit</span>
      <strong style="color:<?= $netProfit >= 0 ? '#bfe7c8' : '#f0b3b3' ?>"><?= e(ops_money($netProfit)) ?></strong>
    </div>
    <div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px dashed #2a2523">
      <span style="color:#7a716a">Cash in hand</span>
      <strong style="color:<?= $cashInHand >= 0 ? '#bfe7c8' : '#f0b3b3' ?>"><?= e(ops_money($cashInHand)) ?></strong>
    </div>
    <div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px dashed #2a2523">
      <span style="color:#7a716a">Outstanding balance</span>
      <strong style="color:#f5cdad"><?= e(ops_money($outstanding)) ?></strong>
    </div>
  </div>

  <p style="color:#6a6159;font-size:.8rem;margin:1.4rem 0 0">
    Printed <?= e(date('d F Y · H:i')) ?> · Imagination Nature Studio · Kiyovu, Kigali
  </p>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>