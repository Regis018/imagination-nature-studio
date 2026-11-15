<?php
/**
 * Admin — Sales (walk-in transactions).
 * Staff & admin can record. Only admin can delete.
 */
require_once __DIR__ . '/../includes/ops.php';
require_admin();

$pdo = db();

/* Safe schema check: adds one nullable cost field; existing sales remain unchanged. */
try {
    $saleCols = $pdo->query("SHOW COLUMNS FROM sales")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('external_supplier_unit_cost', $saleCols, true)) {
        $pdo->exec("ALTER TABLE sales ADD COLUMN external_supplier_unit_cost DECIMAL(15,2) NULL DEFAULT NULL AFTER unit_price");
    }
} catch (Throwable $e) {
    @error_log('[sales-schema] ' . $e->getMessage() . "\n", 3, ERROR_LOG_FILE);
}

function sales_is_frame_product(string $name, string $category = ''): bool
{
    $v = strtolower($name . ' ' . $category);
    return (bool)preg_match('/\b(cadre|frame|frames|a0|a1|a2|a3|a4|a5|a6)\b/i', $v);
}

/**
 * Deduct stock for a newly recorded tracked-product sale using FIFO.
 * Must be called inside the same database transaction as the sale insert.
 */
function sales_stock_deduct(PDO $pdo, int $saleId, int $productId, int $quantity, float $unitSalePrice): void
{
    $productStmt = $pdo->prepare(
        "SELECT id, name, track_stock FROM products WHERE id=? LIMIT 1 FOR UPDATE"
    );
    $productStmt->execute([$productId]);
    $product = $productStmt->fetch(PDO::FETCH_ASSOC);

    if (!$product || (int)$product['track_stock'] !== 1) {
        return; // Products not enabled for stock tracking keep the old Sales behavior.
    }

    $batchStmt = $pdo->prepare(
        "SELECT spi.id, spi.remaining_quantity, spi.unit_cost
         FROM stock_purchase_items spi
         INNER JOIN stock_purchases sp ON sp.id = spi.purchase_id
         WHERE spi.product_id = ?
           AND spi.remaining_quantity > 0
           AND sp.status = 'received'
         ORDER BY sp.purchase_date ASC, spi.id ASC
         FOR UPDATE"
    );
    $batchStmt->execute([$productId]);
    $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

    $available = 0;
    foreach ($batches as $batch) {
        $available += (int)$batch['remaining_quantity'];
    }

    if ($available < $quantity) {
        throw new RuntimeException(
            'Stock is insufficient for ' . $product['name'] .
            '. Available: ' . $available . ', requested: ' . $quantity . '.'
        );
    }

    $left = $quantity;
    $weightedCost = 0.0;

    $updateBatch = $pdo->prepare(
        "UPDATE stock_purchase_items
         SET remaining_quantity = remaining_quantity - ?
         WHERE id = ? AND remaining_quantity >= ?"
    );
    $insertAllocation = $pdo->prepare(
        "INSERT INTO stock_sale_allocations
         (sale_id, product_id, purchase_item_id, quantity, unit_cost,
          unit_sale_price, cost_total, revenue_total, profit_total)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    foreach ($batches as $batch) {
        if ($left <= 0) {
            break;
        }

        $take = min($left, (int)$batch['remaining_quantity']);
        if ($take <= 0) {
            continue;
        }

        $unitCost = (float)$batch['unit_cost'];
        $costTotal = round($take * $unitCost, 2);
        $revenueTotal = round($take * $unitSalePrice, 2);
        $profitTotal = round($revenueTotal - $costTotal, 2);

        $updateBatch->execute([$take, (int)$batch['id'], $take]);
        if ($updateBatch->rowCount() !== 1) {
            throw new RuntimeException('Stock changed during this sale. Please try again.');
        }

        $insertAllocation->execute([
            $saleId, $productId, (int)$batch['id'], $take, $unitCost,
            $unitSalePrice, $costTotal, $revenueTotal, $profitTotal
        ]);

        $weightedCost += $costTotal;
        $left -= $take;
    }

    if ($left !== 0) {
        throw new RuntimeException('Unable to allocate the full quantity from stock batches.');
    }

    $movement = $pdo->prepare(
        "INSERT INTO stock_movements
         (product_id, movement_type, quantity_in, quantity_out, unit_cost,
          reference_type, reference_id, notes, created_by)
         VALUES (?, 'sale_out', 0, ?, ?, 'sale', ?, ?, ?)"
    );
    $movement->execute([
        $productId,
        $quantity,
        round($weightedCost / $quantity, 2),
        $saleId,
        'FIFO stock deduction for sale #' . $saleId,
        isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null
    ]);
}

/**
 * Restore FIFO batch quantities when an administrator deletes a sale.
 * Existing sales without stock allocations are left untouched.
 * Must be called inside the sale-delete transaction.
 */
function sales_stock_restore(PDO $pdo, int $saleId): void
{
    $stmt = $pdo->prepare(
        "SELECT id, product_id, purchase_item_id, quantity, unit_cost
         FROM stock_sale_allocations
         WHERE sale_id = ?
         FOR UPDATE"
    );
    $stmt->execute([$saleId]);
    $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$allocations) {
        return;
    }

    $restoreBatch = $pdo->prepare(
        "UPDATE stock_purchase_items
         SET remaining_quantity = remaining_quantity + ?
         WHERE id = ?"
    );
    $movement = $pdo->prepare(
        "INSERT INTO stock_movements
         (product_id, movement_type, quantity_in, quantity_out, unit_cost,
          reference_type, reference_id, notes, created_by)
         VALUES (?, 'sale_reversal', ?, 0, ?, 'sale_reversal', ?, ?, ?)"
    );

    foreach ($allocations as $allocation) {
        if ($allocation['purchase_item_id'] !== null) {
            $restoreBatch->execute([
                (int)$allocation['quantity'],
                (int)$allocation['purchase_item_id']
            ]);
        }

        $movement->execute([
            (int)$allocation['product_id'],
            (int)$allocation['quantity'],
            (float)$allocation['unit_cost'],
            $saleId,
            'Stock restored after deleting sale #' . $saleId,
            isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null
        ]);
    }

    $pdo->prepare("DELETE FROM stock_sale_allocations WHERE sale_id=?")->execute([$saleId]);
}


/* =====================================================
   POST actions — DELETE runs first, before any validation
   ===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {

    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    /* ---------- DELETE — handled first ---------- */
    if ($action === 'delete' && $id) {
        @error_log(
            '[sales-delete] id=' . $id
            . ' | is_admin=' . (is_admin() ? 'true' : 'false') . "\n",
            3, ERROR_LOG_FILE
        );

        if (!is_admin()) {
            flash('err', 'Only administrators can delete sales.');
            redirect('sales.php');
        }

        try {
            $pdo->beginTransaction();

            // Restore stock first (only if this sale previously deducted tracked stock).
            sales_stock_restore($pdo, $id);

            $pdo->prepare("DELETE FROM sale_payments WHERE sale_id=?")->execute([$id]);
            $stmt = $pdo->prepare("DELETE FROM sales WHERE id=?");
            $stmt->execute([$id]);
            $pdo->commit();
            flash('ok', 'Sale deleted (' . $stmt->rowCount() . ' row).');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            flash('err', 'Delete failed: ' . $e->getMessage());
            @error_log('[sales-delete] ' . $e->getMessage() . "\n", 3, ERROR_LOG_FILE);
        }
        redirect('sales.php');
    }

    /* ---------- UPDATE PAYMENT — admin only ---------- */
    if ($action === 'update_payment' && $id) {

        if (!is_admin()) {
            flash('err', 'Only administrators can update payments.');
            redirect('sales.php');
        }

        $additionalPayment = max(0, (float)($_POST['additional_payment'] ?? 0));
        $newPayMethod = $_POST['payment_method'] ?? 'cash';

        if (!in_array($newPayMethod, ['cash','mobile_money','bank_transfer','other'], true)) {
            $newPayMethod = 'cash';
        }

        if ($additionalPayment <= 0) {
            flash('err', 'Enter a payment amount greater than 0.');
            redirect('sales.php');
        }

        try {
            $pdo->beginTransaction();

            // Lock the sale row so two payments cannot overwrite each other.
            $stmt = $pdo->prepare(
                "SELECT id, total, amount_paid, balance
                 FROM sales
                 WHERE id=?
                 LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([$id]);
            $sale = $stmt->fetch();

            if (!$sale) {
                $pdo->rollBack();
                flash('err', 'Sale not found.');
                redirect('sales.php');
            }

            $currentPaid = (float)$sale['amount_paid'];
            $totalAmount = (float)$sale['total'];
            $currentBalance = max(0, $totalAmount - $currentPaid);

            if ($currentBalance <= 0) {
                $pdo->rollBack();
                flash('err', 'This sale is already fully paid.');
                redirect('sales.php');
            }

            // Never allow a payment to exceed the remaining balance.
            $additionalPayment = min($additionalPayment, $currentBalance);
            $newPaid = min($totalAmount, $currentPaid + $additionalPayment);
            $newBalance = max(0, $totalAmount - $newPaid);

            // Save each installment separately as well as the cumulative totals.
            $payment = $pdo->prepare(
                "INSERT INTO sale_payments (sale_id, amount, payment_method, notes)
                 VALUES (?, ?, ?, ?)"
            );
            $payment->execute([$id, $additionalPayment, $newPayMethod, 'Additional sale payment']);

            $update = $pdo->prepare(
                "UPDATE sales
                 SET amount_paid=?, balance=?, payment_method=?
                 WHERE id=?"
            );
            $update->execute([$newPaid, $newBalance, $newPayMethod, $id]);
            $pdo->commit();

            if ($newBalance <= 0) {
                flash('ok', 'Payment saved. Sale is now FULLY PAID.');
            } else {
                flash('ok', 'Payment saved. Remaining balance: ' . ops_money($newBalance) . ' RWF.');
            }

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            flash('err', 'Payment update failed: ' . $e->getMessage());
            @error_log(
                '[sales-payment-update] ' . $e->getMessage() . "\n",
                3,
                ERROR_LOG_FILE
            );
        }

        redirect('sales.php');
    }

    /* ---------- CREATE / UPDATE — collect all fields ---------- */
    $saleDate    = trim($_POST['sale_date'] ?? date('Y-m-d'));
    $custName    = trim($_POST['customer_name'] ?? '');
    $custPhone   = trim($_POST['customer_phone'] ?? '');
    $productId   = (int)($_POST['product_id'] ?? 0) ?: null;
    $productName = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $qty         = max(1, (int)($_POST['quantity'] ?? 1));
    $unitPrice   = max(0, (float)($_POST['unit_price'] ?? 0));
    $paid        = max(0, (float)($_POST['amount_paid'] ?? 0));
    $payMethod   = $_POST['payment_method'] ?? 'cash';
    $staff       = trim($_POST['staff_name'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');
$supplierUnitCost = max(0, (float)($_POST['external_supplier_unit_cost'] ?? 0));
$isTrackedProduct = false;
$isExternalFrame = false;
if ($productId) {
    $classStmt = $pdo->prepare("SELECT name, category, track_stock FROM products WHERE id=? LIMIT 1");
    $classStmt->execute([$productId]);
    $classRow = $classStmt->fetch(PDO::FETCH_ASSOC);
    if ($classRow) {
        $isTrackedProduct = (int)$classRow['track_stock'] === 1;
        $isExternalFrame = !$isTrackedProduct && sales_is_frame_product((string)$classRow['name'], (string)($classRow['category'] ?? ''));
    }
} else {
    $isExternalFrame = sales_is_frame_product($productName, '');
}
if ($isExternalFrame && $supplierUnitCost <= 0) {
    flash('err', 'Enter the supplier cost per frame for an externally sourced frame.');
    redirect('sales.php');
}

    if (!in_array($payMethod, ['cash','mobile_money','bank_transfer','other'], true)) {
        $payMethod = 'cash';
    }

    $total   = $unitPrice * $qty;
    $paid    = min($paid, $total);
    $balance = max(0, $total - $paid);

    /* Auto-fill from product */
    if ($productId && $productName === '') {
        $p = $pdo->prepare("SELECT name, price FROM products WHERE id=?");
        $p->execute([$productId]);
        if ($row = $p->fetch()) {
            $productName = $row['name'];
            if ($unitPrice <= 0) $unitPrice = (float)$row['price'];
            $total   = $unitPrice * $qty;
            $paid    = min($paid, $total);
            $balance = max(0, $total - $paid);
        }
    }

    if ($productName === '') {
        flash('err', 'Product name is required.');
        redirect('sales.php');
    }

    if ($action === 'create') {
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                "INSERT INTO sales
                 (sale_date, customer_name, customer_phone, product_id, product_name, description,
                  quantity, unit_price, external_supplier_unit_cost, total, amount_paid, balance, payment_method, staff_name, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $saleDate, $custName, $custPhone, $productId, $productName, $description,
                $qty, $unitPrice, ($isExternalFrame ? $supplierUnitCost : null), $total, $paid, $balance, $payMethod, $staff, $notes
            ]);
            $saleId = (int)$pdo->lastInsertId();

            // Deduct tracked stock immediately, regardless of partial payment or credit.
            // If stock is insufficient, the transaction rolls back the sale as well.
            if ($productId) {
                sales_stock_deduct($pdo, $saleId, (int)$productId, $qty, $unitPrice);
            }

            // Record the initial payment too, but do not treat unpaid balance as income.
            if ($paid > 0) {
                $pdo->prepare(
                    "INSERT INTO sale_payments (sale_id, amount, payment_method, notes)
                     VALUES (?, ?, ?, ?)"
                )->execute([$saleId, $paid, $payMethod, 'Initial sale payment']);
            }
            $pdo->commit();
            flash('ok', 'Sale recorded.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            @error_log('[sales-create] ' . $e->getMessage() . "\n", 3, ERROR_LOG_FILE);
            $message = ($e instanceof RuntimeException)
                ? $e->getMessage()
                : 'Sale could not be saved. Check the server error log.';
            flash('err', $message);
        }
        redirect('sales.php');
    }

    if ($action === 'update' && $id) {
        $allocationCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM stock_sale_allocations WHERE sale_id=?"
        );
        $allocationCheck->execute([$id]);
        if ((int)$allocationCheck->fetchColumn() > 0) {
            flash('err', 'This sale has stock allocations. To avoid incorrect stock totals, delete it and record it again instead of editing it.');
            redirect('sales.php');
        }

        $pdo->prepare(
            "UPDATE sales SET
                sale_date=?, customer_name=?, customer_phone=?, product_id=?, product_name=?,
                description=?, quantity=?, unit_price=?, external_supplier_unit_cost=?, total=?, amount_paid=?, balance=?,
                payment_method=?, staff_name=?, notes=?
             WHERE id=?"
        )->execute([
            $saleDate, $custName, $custPhone, $productId, $productName, $description,
            $qty, $unitPrice, ($isExternalFrame ? $supplierUnitCost : null), $total, $paid, $balance, $payMethod, $staff, $notes, $id
        ]);
        flash('ok', 'Sale updated.');
        redirect('sales.php');
    }
}

/* =====================================================
   Filters
   ===================================================== */
$from   = $_GET['from']   ?? date('Y-m-01');
$to     = $_GET['to']     ?? date('Y-m-d');
$search = trim($_GET['q'] ?? '');

$sql = "SELECT s.*, p.category AS product_category
        FROM sales s
        LEFT JOIN products p ON p.id = s.product_id
        WHERE s.sale_date BETWEEN ? AND ?";
$args = [$from, $to];

if ($search !== '') {
    $sql .= " AND (s.customer_name LIKE ? OR s.customer_phone LIKE ? OR s.product_name LIKE ?)";
    $like = "%$search%";
    array_push($args, $like, $like, $like);
}
$sql .= " ORDER BY s.sale_date DESC, s.id DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

$products = $pdo->query("SELECT id, name, price, category, track_stock FROM products WHERE is_active=1 ORDER BY category, name")->fetchAll();
$totals   = ops_sales_total($from, $to);

require __DIR__ . '/includes/admin-header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1rem">
  <div>
    <h1 style="margin:0 0 .3rem">Sales</h1>
    <p style="color:#7a716a;margin:0">Walk-in transactions — record the first payment and update the remaining balance later.</p>
  </div>
  <div style="display:flex;gap:.6rem;flex-wrap:wrap">
    <a class="btn btn-sm btn-ghost"   href="daily-report.php">Daily Report</a>
    <a class="btn btn-sm btn-ghost"   href="weekly-report.php">Weekly Report</a>
    <a class="btn btn-sm btn-primary" href="sales.php">New Sale</a>
  </div>
</div>

<?php if ($m = flash('ok')):  ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
<?php if ($m = flash('err')): ?><div class="alert alert-error"><?= e($m) ?></div><?php endif; ?>

<form method="get" class="card" style="padding:1rem 1.2rem;margin-bottom:1.4rem">
  <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center">
    <label style="margin:0;text-transform:none;letter-spacing:0;color:#7a716a;font-size:.85rem">From</label>
    <input type="date" name="from" value="<?= e($from) ?>" style="width:auto">
    <label style="margin:0;text-transform:none;letter-spacing:0;color:#7a716a;font-size:.85rem">To</label>
    <input type="date" name="to" value="<?= e($to) ?>" style="width:auto">
    <input type="text" name="q" placeholder="Search name/phone/product" value="<?= e($search) ?>">
    <button class="btn btn-sm btn-primary">Filter</button>
    <a class="btn btn-sm btn-ghost" href="sales.php">Today</a>
    <a class="btn btn-sm btn-ghost" href="sales-export.php?from=<?= e($from) ?>&to=<?= e($to) ?>&q=<?= e($search) ?>">⬇ CSV</a>
  </div>
</form>

<div class="grid grid-4" style="margin-bottom:1.4rem">
  <div class="card" style="padding:1rem 1.2rem">
    <div style="font-size:.72rem;letter-spacing:.16em;text-transform:uppercase;color:#7a716a">Sales</div>
    <div style="font-family:var(--font-serif);font-size:1.6rem"><?= (int)$totals['count'] ?></div>
  </div>
  <div class="card" style="padding:1rem 1.2rem">
    <div style="font-size:.72rem;letter-spacing:.16em;text-transform:uppercase;color:#7a716a">Total</div>
    <div style="font-family:var(--font-serif);font-size:1.6rem"><?= e(ops_money($totals['total'])) ?></div>
  </div>
  <div class="card" style="padding:1rem 1.2rem">
    <div style="font-size:.72rem;letter-spacing:.16em;text-transform:uppercase;color:#7a716a">Paid</div>
    <div style="font-family:var(--font-serif);font-size:1.6rem;color:var(--accent-2)"><?= e(ops_money($totals['paid'])) ?></div>
  </div>
  <div class="card" style="padding:1rem 1.2rem">
    <div style="font-size:.72rem;letter-spacing:.16em;text-transform:uppercase;color:#7a716a">Balance</div>
    <div style="font-family:var(--font-serif);font-size:1.6rem;color:#f0b3b3"><?= e(ops_money($totals['balance'])) ?></div>
  </div>
</div>

<form method="post" class="card" style="padding:1.4rem;margin-bottom:1.4rem">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <h3 style="font-family:var(--font-serif);font-size:1.05rem;margin:0 0 1rem">Record new sale</h3>

  <div class="form-grid">
    <div><label>Date *</label><input type="date" name="sale_date" value="<?= e(date('Y-m-d')) ?>" required></div>
    <div><label>Customer name</label><input type="text" name="customer_name" maxlength="150" placeholder="Optional"></div>
    <div><label>Customer phone</label><input type="text" name="customer_phone" maxlength="30" placeholder="Optional"></div>

    <div>
      <label>Product</label>
      <select name="product_id" id="saleProduct">
        <option value="">— Pick or type below —</option>
        <?php foreach ($products as $p): ?>
          <option value="<?= (int)$p['id'] ?>"
                  data-name="<?= e($p['name']) ?>"
                  data-price="<?= (float)$p['price'] ?>"
                  data-stock="<?= (int)($p['track_stock'] ?? 0) ?>"
                  data-category="<?= e($p['category'] ?? '') ?>">
            <?= e($p['name']) ?> · <?= e(ops_money($p['price'])) ?> RWF
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>Or type product name *</label>
      <input type="text" name="product_name" id="saleProductName" maxlength="150" placeholder="e.g. Photo (small)">
    </div>
    <div><label>Description</label>
      <input type="text" name="description" maxlength="255" placeholder="Optional detail">
    </div>

    <div><label>Quantity *</label>
      <input type="number" name="quantity" id="saleQty" min="1" value="1" required>
    </div>
    <div><label>Unit price (RWF) *</label>
      <input type="number" name="unit_price" id="saleUnit" min="0" step="100" value="0" required>
    </div>
    <div id="supplierCostWrap" style="display:none">
      <label>Supplier cost per frame (RWF) *</label>
      <input type="number" name="external_supplier_unit_cost" id="supplierUnitCost" min="1" step="1" value="" placeholder="e.g. 35000">
      <small style="color:#d6ad73">Only for frames sourced externally. This does not reduce your own stock.</small>
    </div>

    <div><label>Amount paid (RWF)</label>
      <input type="number" name="amount_paid" id="salePaid" min="0" step="100" value="0">
    </div>
    <div>
      <label>Payment method</label>
      <select name="payment_method">
        <option value="cash">Cash</option>
        <option value="mobile_money">Mobile Money</option>
        <option value="bank_transfer">Bank Transfer</option>
        <option value="other">Other</option>
      </select>
    </div>

    <div><label>Staff name</label><input type="text" name="staff_name" maxlength="120" value="<?= e(current_admin()['name'] ?? '') ?>"></div>
    <div><label>Notes</label><input type="text" name="notes" maxlength="255" placeholder="Optional"></div>
  </div>

  <div style="margin-top:1rem;padding:1rem;background:#100f0e;border-radius:8px;border:1px solid #262220">
    <div style="display:flex;gap:1.4rem;flex-wrap:wrap;font-size:.9rem">
      <span style="color:#7a716a">Total: <strong id="saleTotalPreview" style="color:var(--accent-2);font-size:1.05rem">0</strong> RWF</span>
      <span style="color:#7a716a">Balance: <strong id="saleBalancePreview" style="color:#f0b3b3">0</strong> RWF</span>
    </div>
  </div>

  <button class="btn btn-primary" style="margin-top:1rem">Record sale</button>
</form>

<h2 style="font-family:var(--font-serif);font-size:1.1rem;margin:1.4rem 0 .8rem">
  Sales in period (<?= count($rows) ?>)
</h2>

<div class="card" style="padding:0">
  <div class="admin-table-wrap" style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
      <thead><tr style="text-align:left;font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:#7a716a">
        <th style="padding:.9rem 1rem">Date</th>
        <th>Customer</th>
        <th>Product</th>
        <th>Qty</th>
        <th>Unit</th>
        <th>Total</th>
        <th>Paid</th>
        <th>Balance</th>
        <th>Status</th>
        <th>Method</th>
        <th>Action</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr style="border-top:1px solid #262220">
          <td style="padding:.9rem 1rem;white-space:nowrap"><?= e(ops_format_date($r['sale_date'])) ?></td>
          <td>
            <?= e($r['customer_name'] ?: '—') ?>
            <?php if ($r['customer_phone']): ?>
              <br><span style="color:#7a716a;font-size:.78rem"><?= e($r['customer_phone']) ?></span>
            <?php endif; ?>
          </td>
          <td><?= e($r['product_name']) ?>
            <?php if ($r['description']): ?>
              <br><span style="color:#7a716a;font-size:.78rem"><?= e($r['description']) ?></span>
            <?php endif; ?>
          </td>
          <td><?= (int)$r['quantity'] ?></td>
          <td><?= e(ops_money($r['unit_price'])) ?></td>
          <td><strong><?= e(ops_money($r['total'])) ?></strong></td>
          <td><?= e(ops_money($r['amount_paid'])) ?></td>
          <td style="color:<?= (float)$r['balance'] > 0 ? '#f0b3b3' : '#6a6159' ?>">
            <?= e(ops_money($r['balance'])) ?>
          </td>

          <td>
            <?php
              // VIP gifts are recorded as zero-payment sales with a descriptive marker.
              // Show them separately so staff do not mistake them for customer payments.
              $isVipGift = stripos((string)($r['description'] ?? ''), 'VIP gift') !== false
                        || stripos((string)($r['notes'] ?? ''), 'VIP gift') !== false;
            ?>
            <?php if ($isVipGift): ?>
              <span style="display:inline-block;padding:.25rem .55rem;border-radius:999px;background:#3b2b52;color:#d9b8ff;font-size:.72rem;font-weight:700;letter-spacing:.05em">
                VIP GIFT
              </span>
            <?php elseif ((float)$r['balance'] <= 0): ?>
              <span style="display:inline-block;padding:.25rem .55rem;border-radius:999px;background:#173b27;color:#8ee0ad;font-size:.72rem;font-weight:700;letter-spacing:.05em">
                PAID
              </span>
            <?php elseif ((float)$r['amount_paid'] > 0): ?>
              <span style="display:inline-block;padding:.25rem .55rem;border-radius:999px;background:#3b3217;color:#f4d36b;font-size:.72rem;font-weight:700;letter-spacing:.05em">
                PARTIAL
              </span>
            <?php else: ?>
              <span style="display:inline-block;padding:.25rem .55rem;border-radius:999px;background:#3a2020;color:#f0b3b3;font-size:.72rem;font-weight:700;letter-spacing:.05em">
                UNPAID
              </span>
            <?php endif; ?>
          </td>

          <td><span style="color:#7a716a;font-size:.78rem"><?=
            $isVipGift
              ? 'VIP Gift'
              : e(ucwords(str_replace('_', ' ', (string)$r['payment_method'])))
          ?></span></td>

          <td style="white-space:nowrap">
            <?php if (is_admin()): ?>

              <?php if (!$isVipGift && (float)$r['balance'] > 0): ?>
                <details style="display:inline-block;margin-right:.35rem;vertical-align:middle">
                  <summary class="btn btn-sm btn-primary" style="cursor:pointer;display:inline-block">
                    💰 Update Payment
                  </summary>

                  <form method="post"
                        style="margin-top:.7rem;padding:.8rem;background:#151311;border:1px solid #302b27;border-radius:8px;min-width:250px;white-space:normal">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_payment">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

                    <div style="font-size:.78rem;color:#7a716a;margin-bottom:.55rem">
                      Remaining:
                      <strong style="color:#f0b3b3">
                        <?= e(ops_money($r['balance'])) ?> RWF
                      </strong>
                    </div>

                    <input
                      type="number"
                      name="additional_payment"
                      min="100"
                      max="<?= e((float)$r['balance']) ?>"
                      step="100"
                      value="<?= e((float)$r['balance']) ?>"
                      required
                      style="width:100%;margin-bottom:.5rem"
                      placeholder="Payment amount"
                    >

                    <select name="payment_method" style="width:100%;margin-bottom:.6rem">
                      <option value="cash">Cash</option>
                      <option value="mobile_money">Mobile Money</option>
                      <option value="bank_transfer">Bank Transfer</option>
                      <option value="other">Other</option>
                    </select>

                    <button class="btn btn-sm btn-primary" style="width:100%">
                      Save Payment
                    </button>
                  </form>
                </details>
              <?php elseif (!$isVipGift): ?>
                <span style="color:#6a6159;font-size:.78rem;margin-right:.4rem">✓ Fully paid</span>
              <?php endif; ?>

              <form method="post" style="display:inline" onsubmit="return confirm('Delete this sale?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-ghost" style="border-color:#a33;color:#f0b3b3">Delete</button>
              </form>

            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if (!$rows): ?>
        <tr><td colspan="11" style="padding:3rem;text-align:center;color:#7a716a">
          No sales in this period.
        </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function () {
  var sel   = document.getElementById('saleProduct');
  var name  = document.getElementById('saleProductName');
  var qty   = document.getElementById('saleQty');
  var unit  = document.getElementById('saleUnit');
  var paid  = document.getElementById('salePaid');
  var tOut  = document.getElementById('saleTotalPreview');
  var bOut  = document.getElementById('saleBalancePreview');
  var supplierWrap = document.getElementById('supplierCostWrap');
  var supplierCost = document.getElementById('supplierUnitCost');

  function recalc() {
    var t = (parseFloat(unit.value) || 0) * (parseInt(qty.value, 10) || 1);
    var p = parseFloat(paid.value) || 0;
    tOut.textContent = t.toLocaleString();
    bOut.textContent = Math.max(0, t - p).toLocaleString();
  }

  if (sel && name && unit) {
    sel.addEventListener('change', function () {
      var opt = sel.options[sel.selectedIndex];
      if (opt && opt.dataset.name) {
        name.value = opt.dataset.name;
        unit.value = opt.dataset.price || 0;
        var frame = /\b(cadre|frame|frames|a0|a1|a2|a3|a4|a5|a6)\b/i.test((opt.dataset.name || '') + ' ' + (opt.dataset.category || ''));
        var external = frame && opt.dataset.stock !== '1';
        if (supplierWrap) supplierWrap.style.display = external ? 'block' : 'none';
        if (supplierCost) supplierCost.required = external;
        recalc();
      } else {
        if (supplierWrap) supplierWrap.style.display = 'none';
        if (supplierCost) supplierCost.required = false;
      }
    });
  }
  if (name) name.addEventListener('input', function () {
    var noProduct = !sel || !sel.value;
    var frame = /\b(cadre|frame|frames|a0|a1|a2|a3|a4|a5|a6)\b/i.test(name.value || '');
    var external = noProduct && frame;
    if (supplierWrap) supplierWrap.style.display = external ? 'block' : 'none';
    if (supplierCost) supplierCost.required = external;
  });
  [qty, unit, paid].forEach(function (el) {
    if (el) el.addEventListener('input', recalc);
  });
  recalc();
})();
</script>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>