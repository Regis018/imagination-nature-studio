<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_admin_only();

$pdo = db();
$pageTitle = 'Finance Control & Reserves';

/* Keep the new cost field available even if Finance is opened before Sales. */
try {
    $saleColsForFrameCost = $pdo->query("SHOW COLUMNS FROM sales")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('external_supplier_unit_cost', $saleColsForFrameCost, true)) {
        $pdo->exec("ALTER TABLE sales ADD COLUMN external_supplier_unit_cost DECIMAL(15,2) NULL DEFAULT NULL AFTER unit_price");
    }
} catch (Throwable $e) {
    @error_log('[finance-sales-schema] ' . $e->getMessage() . "\n", 3, ERROR_LOG_FILE);
}

/*
 * Finance Control for Imagination Nature Studio.
 * - Monthly obligations are plans, not expenses.
 * - A payment is written once to expenses and linked to the obligation.
 * - Existing expenses remain the source of truth for cash-out totals.
 * - Reserve = planned amount less payments recorded for that obligation/month.
 * - Sales are counted from sales.amount_paid; sale-linked payments are excluded
 *   to avoid counting the same sales collection twice.
 */

$schemaError = '';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS finance_monthly_obligations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        obligation_type ENUM('rent','salary','other') NOT NULL DEFAULT 'other',
        label VARCHAR(190) NOT NULL,
        monthly_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        due_day TINYINT UNSIGNED NOT NULL DEFAULT 1,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_finance_obligation_active (active, obligation_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS finance_obligation_payments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        obligation_id BIGINT UNSIGNED NOT NULL,
        obligation_month CHAR(7) NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        paid_date DATE NOT NULL,
        payment_method VARCHAR(40) NOT NULL DEFAULT 'Cash',
        notes VARCHAR(500) NULL,
        expense_id BIGINT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_finance_obligation_expense (expense_id),
        INDEX idx_finance_obligation_month (obligation_id, obligation_month),
        CONSTRAINT fk_finance_obligation_payment_obligation FOREIGN KEY (obligation_id)
            REFERENCES finance_monthly_obligations(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    // Preserve the page instead of exposing a blank HTTP 500; show a safe diagnostic below.
    $schemaError = 'Finance tables could not be checked/created. Ask hosting to check database permissions and the PHP error log.';
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['finance_control_csrf'])) {
    $_SESSION['finance_control_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['finance_control_csrf'];

function fc_money(float $n): string { return number_format($n, 0) . ' RWF'; }
function fc_h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function fc_columns(PDO $pdo, string $table): array {
    $out = [];
    $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $out[(string)$row['Field']] = true;
    return $out;
}
function fc_add_expense(PDO $pdo, array $cols, string $date, string $description, float $amount, string $method, string $category, string $notes): int {
    $values = [];
    $put = static function(string $column, $value) use (&$values, $cols): void {
        if (isset($cols[$column])) $values[$column] = $value;
    };
    $put('expense_date', $date);
    $put('description', $description);
    $put('amount', $amount);
    $put('payment_method', $method);
    $put('category', $category);
    $put('paid_to', $description);
    $put('notes', $notes);
    if (!$values || !isset($values['expense_date'], $values['description'], $values['amount'])) {
        throw new RuntimeException('Expenses table is missing a required column (expense_date, description, or amount). No payment was saved.');
    }
    $names = array_keys($values);
    $sql = 'INSERT INTO expenses (' . implode(',', array_map(static fn($x) => '`' . $x . '`', $names)) . ') VALUES (' . implode(',', array_fill(0, count($names), '?')) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($values));
    return (int)$pdo->lastInsertId();
}

$notice = '';
$error = $schemaError;
$month = (string)($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
$monthStart = $month . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Security check failed. Refresh the page and try again.');
        }
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'add_obligation') {
            $type = (string)($_POST['obligation_type'] ?? 'other');
            if (!in_array($type, ['rent','salary','other'], true)) $type = 'other';
            $label = trim((string)($_POST['label'] ?? ''));
            $amount = filter_var($_POST['monthly_amount'] ?? null, FILTER_VALIDATE_FLOAT);
            $dueDay = filter_var($_POST['due_day'] ?? 1, FILTER_VALIDATE_INT);
            if ($label === '' || $amount === false || $amount <= 0) throw new RuntimeException('Enter a name and a monthly amount greater than zero.');
            $dueDay = max(1, min(31, (int)$dueDay));
            $stmt = $pdo->prepare('INSERT INTO finance_monthly_obligations (obligation_type,label,monthly_amount,due_day,active) VALUES (?,?,?,?,1)');
            $stmt->execute([$type, $label, $amount, $dueDay]);
            $notice = 'Monthly obligation saved.';
        } elseif ($action === 'pay_obligation') {
            $obligationId = filter_var($_POST['obligation_id'] ?? null, FILTER_VALIDATE_INT);
            $payMonth = (string)($_POST['obligation_month'] ?? $month);
            $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
            $paidDate = (string)($_POST['paid_date'] ?? date('Y-m-d'));
            $method = (string)($_POST['payment_method'] ?? 'Cash');
            $notes = trim((string)($_POST['notes'] ?? ''));
            if (!$obligationId || !preg_match('/^\d{4}-\d{2}$/', $payMonth) || $amount === false || $amount <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidDate)) {
                throw new RuntimeException('Check the obligation, month, date and payment amount.');
            }
            if (!in_array($method, ['Cash','Mobile Money','Bank','Card','Other'], true)) $method = 'Other';
            $stmt = $pdo->prepare('SELECT * FROM finance_monthly_obligations WHERE id = ? AND active = 1');
            $stmt->execute([$obligationId]);
            $obligation = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$obligation) throw new RuntimeException('The selected obligation was not found or is inactive.');

            $expenseCols = fc_columns($pdo, 'expenses');
            $category = $obligation['obligation_type'] === 'rent' ? 'Rent' : ($obligation['obligation_type'] === 'salary' ? 'Salary' : 'Monthly obligation');
            $description = 'Monthly ' . $category . ' payment: ' . $obligation['label'] . ' (' . $payMonth . ')';
            $pdo->beginTransaction();
            try {
                // Actual payments are entered into the existing expenses ledger exactly once.
                $expenseId = fc_add_expense($pdo, $expenseCols, $paidDate, $description, (float)$amount, $method, $category, $notes);
                $stmt = $pdo->prepare('INSERT INTO finance_obligation_payments (obligation_id,obligation_month,amount,paid_date,payment_method,notes,expense_id) VALUES (?,?,?,?,?,?,?)');
                $stmt->execute([$obligationId, $payMonth, $amount, $paidDate, $method, $notes !== '' ? $notes : null, $expenseId]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            $notice = 'Payment saved and added to Expenses once. Do not enter this same payment again in Expenses.';
            $month = $payMonth;
            $monthStart = $month . '-01';
            $monthEnd = date('Y-m-t', strtotime($monthStart));
        } elseif ($action === 'toggle_obligation') {
            $obligationId = filter_var($_POST['obligation_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$obligationId) throw new RuntimeException('Invalid obligation.');
            $stmt = $pdo->prepare('UPDATE finance_monthly_obligations SET active = IF(active=1,0,1) WHERE id=?');
            $stmt->execute([$obligationId]);
            $notice = 'Obligation status updated. Existing payment history is preserved.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

/* Obligations and amounts paid against each month. */
$stmt = $pdo->prepare("SELECT o.*,
    COALESCE(p.paid,0) AS paid,
    GREATEST(o.monthly_amount - COALESCE(p.paid,0),0) AS remaining
    FROM finance_monthly_obligations o
    LEFT JOIN (
        SELECT obligation_id, SUM(amount) AS paid
        FROM finance_obligation_payments WHERE obligation_month = ? GROUP BY obligation_id
    ) p ON p.obligation_id = o.id
    ORDER BY o.active DESC, FIELD(o.obligation_type,'rent','salary','other'), o.label");
$stmt->execute([$month]);
$obligations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$plannedTotal = 0.0; $paidObligations = 0.0; $reserveNeeded = 0.0;
foreach ($obligations as $o) {
    if ((int)$o['active'] === 1) {
        $plannedTotal += (float)$o['monthly_amount'];
        $paidObligations += (float)$o['paid'];
        $reserveNeeded += (float)$o['remaining'];
    }
}

/* Frame sales profitability for the selected month.
   FIFO cost comes from stock_sale_allocations; externally sourced frames use
   the per-unit supplier cost saved on the sale. Legacy rows with no cost stay flagged. */
$frameReport = [];
foreach (['A0','A1','A2','A3','A4','A5','A6','Other'] as $size) {
    $frameReport[$size] = ['qty'=>0, 'revenue'=>0.0, 'cost'=>0.0, 'profit'=>0.0, 'missing'=>0];
}
$frameRevenueTotal = 0.0;
$frameCostTotal = 0.0;
$frameMissingTotal = 0;
try {
    $frameStmt = $pdo->prepare("SELECT s.id, s.product_name, s.quantity, s.unit_price, s.total,
             s.external_supplier_unit_cost,
             COALESCE((SELECT SUM(a.cost_total) FROM stock_sale_allocations a WHERE a.sale_id=s.id),0) AS fifo_cost,
             (SELECT COUNT(*) FROM stock_sale_allocations a WHERE a.sale_id=s.id) AS allocation_count
        FROM sales s
        WHERE s.sale_date BETWEEN ? AND ?
          AND (LOWER(s.product_name) LIKE '%cadre%' OR LOWER(s.product_name) LIKE '%frame%')");
    $frameStmt->execute([$monthStart, $monthEnd]);
    foreach ($frameStmt->fetchAll(PDO::FETCH_ASSOC) as $saleLine) {
        preg_match('/\bA[0-6]\b/i', (string)$saleLine['product_name'], $match);
        $size = isset($match[0]) ? strtoupper($match[0]) : 'Other';
        $qty = (int)$saleLine['quantity'];
        $revenue = (float)$saleLine['total'];
        $hasFifo = (int)$saleLine['allocation_count'] > 0;
        $hasSupplier = $saleLine['external_supplier_unit_cost'] !== null && (float)$saleLine['external_supplier_unit_cost'] > 0;
        $costKnown = $hasFifo || $hasSupplier;
        $cost = $hasFifo ? (float)$saleLine['fifo_cost'] : ($hasSupplier ? (float)$saleLine['external_supplier_unit_cost'] * $qty : 0.0);
        $frameReport[$size]['qty'] += $qty;
        $frameReport[$size]['revenue'] += $revenue;
        $frameRevenueTotal += $revenue;
        if ($costKnown) {
            $frameReport[$size]['cost'] += $cost;
            $frameReport[$size]['profit'] += ($revenue - $cost);
            $frameCostTotal += $cost;
        } else {
            $frameReport[$size]['missing'] += $qty;
            $frameMissingTotal += $qty;
        }
    }
} catch (Throwable $e) {
    $error = $error !== '' ? $error : 'Frame profitability report could not be loaded. Check the sales cost field and stock allocation tables.';
    @error_log('[finance-frame-report] ' . $e->getMessage() . "\n", 3, ERROR_LOG_FILE);
}
$frameProfitTotal = $frameRevenueTotal - $frameCostTotal;

/* Cash-based lifetime totals; adapt to the actual columns in this site's database. */
$bookingIncome = 0.0;
try {
    $paymentCols = fc_columns($pdo, 'payments');
    if (isset($paymentCols['amount'])) {
        $where = [];
        // Filter to successful/paid records only when the live table has a status column.
        if (isset($paymentCols['payment_status'])) {
            $where[] = "LOWER(payment_status) IN ('successful','success','paid','completed')";
        } elseif (isset($paymentCols['status'])) {
            $where[] = "LOWER(status) IN ('successful','success','paid','completed')";
        }
        // Some database versions store sales receipts in payments.sale_id; others do not.
        // Only apply this condition if the column actually exists.
        if (isset($paymentCols['sale_id'])) {
            $where[] = '(sale_id IS NULL OR sale_id = 0)';
        }
        $sql = 'SELECT COALESCE(SUM(amount),0) FROM payments' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $bookingIncome = (float)$pdo->query($sql)->fetchColumn();
    }
} catch (Throwable $e) {
    // Keep the page usable if this installation has no compatible payments table.
    $bookingIncome = 0.0;
}

$salesIncome = 0.0;
try {
    $salesCols = fc_columns($pdo, 'sales');
    if (isset($salesCols['amount_paid'])) {
        $salesIncome = (float)$pdo->query('SELECT COALESCE(SUM(amount_paid),0) FROM sales')->fetchColumn();
    } elseif (isset($salesCols['paid_amount'])) {
        $salesIncome = (float)$pdo->query('SELECT COALESCE(SUM(paid_amount),0) FROM sales')->fetchColumn();
    }
} catch (Throwable $e) {
    $salesIncome = 0.0;
}

$moneyReceived = $bookingIncome + $salesIncome;
$moneySpent = 0.0;
try {
    $expenseColsForTotals = fc_columns($pdo, 'expenses');
    if (isset($expenseColsForTotals['amount'])) {
        $moneySpent = (float)$pdo->query('SELECT COALESCE(SUM(amount),0) FROM expenses')->fetchColumn();
    }
} catch (Throwable $e) {
    $moneySpent = 0.0;
}
$cashAfterRecordedExpenses = $moneyReceived - $moneySpent;
$availableAfterReserve = $cashAfterRecordedExpenses - $reserveNeeded;

require __DIR__ . '/includes/admin-header.php';
?>
<style>
.fc-wrap{max-width:1450px;margin:0 auto;color:#edf4f1}.fc-head{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:20px}.fc-head h1{margin:0;font-size:30px}.fc-muted{color:#9baaa6;font-size:13px}.fc-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px;margin:18px 0}.fc-card,.fc-panel{background:linear-gradient(145deg,#172020,#111718);border:1px solid #2b3938;border-radius:15px;padding:18px;box-shadow:0 12px 28px #0002}.fc-label{font-size:12px;color:#9baaa6;font-weight:700}.fc-value{font-size:24px;font-weight:850;margin-top:9px;overflow-wrap:anywhere}.fc-green{color:#65e6a5}.fc-red{color:#ff8585}.fc-amber{color:#ffd17a}.fc-panels{display:grid;grid-template-columns:1.1fr .9fr;gap:16px}.fc-panel h2{margin:0 0 6px;font-size:19px}.fc-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:14px}.fc-form label{font-size:12px;color:#b9c7c2;display:block;margin-bottom:5px}.fc-form input,.fc-form select,.fc-form textarea,.fc-month{width:100%;box-sizing:border-box;background:#0c1213;color:#edf4f1;border:1px solid #354543;border-radius:8px;padding:10px;font:inherit}.fc-form .full{grid-column:1/-1}.fc-btn{border:1px solid #3b5b4b;background:#1d3d2f;color:#9ff2c3;border-radius:9px;padding:10px 14px;font-weight:750;cursor:pointer;text-decoration:none;display:inline-block}.fc-btn.secondary{background:#202b2b;color:#edf4f1;border-color:#3a4948}.fc-table-wrap{overflow-x:auto;margin-top:14px}.fc-table{width:100%;border-collapse:collapse;min-width:650px}.fc-table th,.fc-table td{text-align:left;padding:11px 8px;border-bottom:1px solid #2b3737;font-size:13px}.fc-table th{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#9baaa6}.fc-table td:last-child,.fc-table th:last-child{text-align:right}.fc-pill{border:1px solid #40504e;border-radius:99px;padding:4px 8px;font-size:11px}.fc-notice{padding:12px 14px;border-radius:10px;margin-bottom:14px;background:#173627;border:1px solid #326847;color:#9ff2c3}.fc-error{background:#3a2020;border-color:#743838;color:#ffc0c0}.fc-note{padding:12px 14px;border-radius:10px;background:#202a2a;color:#b9c7c2;font-size:12px;line-height:1.6;margin-top:12px}.fc-inline{display:inline-flex;gap:6px;align-items:center}.fc-mini{padding:6px 9px;font-size:11px}@media(max-width:950px){.fc-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.fc-panels{grid-template-columns:1fr}}@media(max-width:560px){.fc-grid{grid-template-columns:1fr}.fc-form{grid-template-columns:1fr}.fc-form .full{grid-column:auto}.fc-head h1{font-size:24px}}
@media print{.admin-sidebar,.admin-header,.fc-actions,.fc-form,.fc-btn,.fc-note{display:none!important}.fc-wrap,.fc-card,.fc-panel{color:#111!important;background:#fff!important;box-shadow:none!important}.fc-muted,.fc-label{color:#444!important}.fc-value{color:#111!important}.fc-panels{display:block}.fc-panel{margin-bottom:12px}}
</style>
<div class="fc-wrap">
  <div class="fc-head">
    <div><div class="fc-muted">IMAGINATION NATURE STUDIO · FINANCE CENTER</div><h1>Cash Control & Monthly Reserves</h1><div class="fc-muted">Plan rent and salaries, record actual payments, and see what cash remains.</div></div>
    <div class="fc-actions"><a class="fc-btn secondary" href="profit-loss.php">Profit &amp; Loss</a> <a class="fc-btn secondary" href="transactions.php">Transactions</a> <button class="fc-btn" onclick="window.print()" type="button">Print report</button></div>
  </div>
  <?php if ($notice !== ''): ?><div class="fc-notice"><?= fc_h($notice) ?></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="fc-notice fc-error"><?= fc_h($error) ?></div><?php endif; ?>
  <form method="get" class="fc-inline" style="margin:12px 0 4px"><label for="month" class="fc-muted">Planning month</label><input class="fc-month" style="width:auto" type="month" id="month" name="month" value="<?= fc_h($month) ?>"><button class="fc-btn secondary" type="submit">View month</button></form>
  <div class="fc-grid">
    <div class="fc-card"><div class="fc-label">TOTAL MONEY RECEIVED (ALL TIME)</div><div class="fc-value fc-green"><?= fc_h(fc_money($moneyReceived)) ?></div><div class="fc-muted">Successful booking receipts + sales collected, with sale-linked receipts excluded.</div></div>
    <div class="fc-card"><div class="fc-label">TOTAL RECORDED EXPENSES (ALL TIME)</div><div class="fc-value fc-red"><?= fc_h(fc_money($moneySpent)) ?></div><div class="fc-muted">All expense entries, including rent/salary payments recorded here.</div></div>
    <div class="fc-card"><div class="fc-label">CASH AFTER RECORDED EXPENSES</div><div class="fc-value <?= $cashAfterRecordedExpenses >= 0 ? 'fc-green' : 'fc-red' ?>"><?= fc_h(fc_money($cashAfterRecordedExpenses)) ?></div><div class="fc-muted">Received minus recorded expenses. Not a bank reconciliation.</div></div>
    <div class="fc-card"><div class="fc-label">AVAILABLE AFTER <?= fc_h($month) ?> RESERVE</div><div class="fc-value <?= $availableAfterReserve >= 0 ? 'fc-green' : 'fc-red' ?>"><?= fc_h(fc_money($availableAfterReserve)) ?></div><div class="fc-muted">Cash after expenses minus unpaid obligations for this month.</div></div>
  </div>
  <div class="fc-grid">
    <div class="fc-card"><div class="fc-label">PLANNED MONTHLY OBLIGATIONS</div><div class="fc-value"><?= fc_h(fc_money($plannedTotal)) ?></div></div>
    <div class="fc-card"><div class="fc-label">PAID FOR SELECTED MONTH</div><div class="fc-value fc-green"><?= fc_h(fc_money($paidObligations)) ?></div></div>
    <div class="fc-card"><div class="fc-label">STILL TO RESERVE / PAY</div><div class="fc-value fc-amber"><?= fc_h(fc_money($reserveNeeded)) ?></div></div>
    <div class="fc-card"><div class="fc-label">SELECTED MONTH</div><div class="fc-value"><?= fc_h(date('F Y', strtotime($monthStart))) ?></div><div class="fc-muted">Planned amounts are not counted as expenses until paid.</div></div>
  </div>
  <section class="fc-panel" style="margin:18px 0">
    <h2>Frame Sales & Profit by Size</h2>
    <div class="fc-muted">Selected month: <?= fc_h(date('F Y', strtotime($monthStart))) ?> · Revenue uses sale totals; costs use FIFO or saved supplier cost.</div>
    <div class="fc-table-wrap"><table class="fc-table" style="min-width:720px">
      <thead><tr><th>Frame size</th><th>Quantity sold</th><th>Revenue</th><th>Cost</th><th>Gross profit</th><th>Cost status</th></tr></thead><tbody>
      <?php foreach ($frameReport as $size => $v): if ((int)$v['qty'] === 0) continue; ?>
        <tr><td><strong><?= fc_h($size) ?></strong></td><td><?= number_format((int)$v['qty']) ?></td><td><?= fc_h(fc_money((float)$v['revenue'])) ?></td><td><?= fc_h(fc_money((float)$v['cost'])) ?></td><td class="fc-green"><?= $v['missing'] > 0 ? 'Incomplete' : fc_h(fc_money((float)$v['profit'])) ?></td><td><?php if ($v['missing'] > 0): ?><span class="fc-amber">Historical cost unknown: <?= (int)$v['missing'] ?> unit(s)</span><?php else: ?><span class="fc-green">Complete</span><?php endif; ?></td></tr>
      <?php endforeach; ?>
      <?php if (array_sum(array_column($frameReport, 'qty')) === 0): ?><tr><td colspan="6">No frame sales recorded for this month.</td></tr><?php endif; ?>
      </tbody><tfoot><tr><th>Total</th><th><?= number_format(array_sum(array_column($frameReport, 'qty'))) ?></th><th><?= fc_h(fc_money($frameRevenueTotal)) ?></th><th><?= fc_h(fc_money($frameCostTotal)) ?></th><th><?= $frameMissingTotal > 0 ? 'Incomplete' : fc_h(fc_money($frameProfitTotal)) ?></th><th><?= $frameMissingTotal > 0 ? 'Historical cost unknown: ' . (int)$frameMissingTotal . ' unit(s)' : 'Complete' ?></th></tr></tfoot>
    </table></div>
    <div class="fc-note">Historical sales recorded before stock-cost tracking may have an unknown purchase cost. These units are marked ‘Historical cost unknown’; their revenue is shown, but gross profit is not treated as confirmed. Add the original purchase cost later if you recover it. This report does not change stock quantities or rewrite sales history.</div>
  </section>

  <div class="fc-panels">
    <section class="fc-panel">
      <h2>Monthly rent, salaries & other obligations</h2><div class="fc-muted">Add one line for rent and one line per employee or salary group. Update the amount when it changes.</div>
      <form method="post" class="fc-form">
        <input type="hidden" name="csrf" value="<?= fc_h($csrf) ?>"><input type="hidden" name="action" value="add_obligation">
        <div><label for="obligation_type">Type</label><select name="obligation_type" id="obligation_type"><option value="rent">Rent</option><option value="salary">Salary</option><option value="other">Other monthly obligation</option></select></div>
        <div><label for="label">Name / description</label><input id="label" name="label" required placeholder="e.g. Studio rent or Joyeuse salary"></div>
        <div><label for="monthly_amount">Amount due each month (RWF)</label><input id="monthly_amount" name="monthly_amount" type="number" min="1" step="1" required placeholder="Enter amount"></div>
        <div><label for="due_day">Due day of month</label><input id="due_day" name="due_day" type="number" min="1" max="31" value="1" required></div>
        <div class="full"><button class="fc-btn" type="submit">Add monthly obligation</button></div>
      </form>
      <div class="fc-table-wrap"><table class="fc-table"><thead><tr><th>Obligation</th><th>Monthly plan</th><th>Paid</th><th>Still due</th><th>Status</th></tr></thead><tbody>
      <?php if (!$obligations): ?><tr><td colspan="5">No monthly obligations configured yet.</td></tr><?php endif; ?>
      <?php foreach ($obligations as $o): ?><tr><td><strong><?= fc_h((string)$o['label']) ?></strong><br><span class="fc-muted"><?= fc_h(ucfirst((string)$o['obligation_type'])) ?> · due day <?= (int)$o['due_day'] ?> <?= (int)$o['active'] ? '' : '· inactive' ?></span></td><td><?= fc_h(fc_money((float)$o['monthly_amount'])) ?></td><td class="fc-green"><?= fc_h(fc_money((float)$o['paid'])) ?></td><td class="fc-amber"><?= fc_h(fc_money((float)$o['remaining'])) ?></td><td><form method="post"><input type="hidden" name="csrf" value="<?= fc_h($csrf) ?>"><input type="hidden" name="action" value="toggle_obligation"><input type="hidden" name="obligation_id" value="<?= (int)$o['id'] ?>"><button class="fc-btn secondary fc-mini" type="submit"><?= (int)$o['active'] ? 'Deactivate' : 'Activate' ?></button></form></td></tr><?php endforeach; ?>
      </tbody></table></div>
      <div class="fc-note">The reserve is a planning figure, not a separate bank account. For accurate figures, record every real income and expense in the system. Deactivating an obligation preserves its history but stops it from adding to future reserves.</div>
    </section>
    <section class="fc-panel">
      <h2>Record an actual payment</h2><div class="fc-muted">Saving a payment adds one row to the existing Expenses ledger and links it to the monthly plan.</div>
      <form method="post" class="fc-form">
        <input type="hidden" name="csrf" value="<?= fc_h($csrf) ?>"><input type="hidden" name="action" value="pay_obligation">
        <div class="full"><label for="obligation_id">Obligation</label><select id="obligation_id" name="obligation_id" required><?php foreach ($obligations as $o): if ((int)$o['active'] !== 1) continue; ?><option value="<?= (int)$o['id'] ?>"><?= fc_h((string)$o['label']) ?> — remaining <?= fc_h(fc_money((float)$o['remaining'])) ?></option><?php endforeach; ?></select></div>
        <div><label for="obligation_month">Which month is this payment for?</label><input type="month" id="obligation_month" name="obligation_month" value="<?= fc_h($month) ?>" required></div>
        <div><label for="amount">Amount actually paid (RWF)</label><input id="amount" name="amount" type="number" min="1" step="1" required></div>
        <div><label for="paid_date">Date paid</label><input id="paid_date" name="paid_date" type="date" value="<?= fc_h(date('Y-m-d')) ?>" required></div>
        <div><label for="payment_method">Payment method</label><select id="payment_method" name="payment_method"><option>Cash</option><option>Mobile Money</option><option>Bank</option><option>Card</option><option>Other</option></select></div>
        <div class="full"><label for="notes">Reference / notes (optional)</label><textarea id="notes" name="notes" rows="2" placeholder="Transaction reference or short note"></textarea></div>
        <div class="full"><button class="fc-btn" type="submit" <?= count(array_filter($obligations, static fn($o) => (int)$o['active'] === 1)) === 0 ? 'disabled' : '' ?>>Save payment</button></div>
      </form>
      <div class="fc-note"><strong>Important:</strong> Do not manually add the same rent/salary payment again in Expenses. This page already records it there. If you already entered a payment in Expenses before using this page, do not re-enter it here; otherwise the cash-out total would count it twice.</div>
    </section>
  </div>
</div>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
