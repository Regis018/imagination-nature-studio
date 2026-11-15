<?php
/**
 * Imagination Nature Studio — Record a received stock purchase.
 * Admin-only. Uses FIFO purchase batches and movement ledger.
 */
require_once __DIR__ . '/../includes/ops.php';
require_admin();

if (!is_admin()) {
    http_response_code(403);
    exit('Access denied. Admin only.');
}

$pdo = db();
$errors = [];
$success = '';

$products = $pdo->query("
    SELECT id, name
    FROM products
    WHERE is_active = 1 AND track_stock = 1
    ORDER BY name, id
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $errors[] = 'Security token is invalid. Refresh the page and try again.';
    } else {
        $purchaseDate = trim($_POST['purchase_date'] ?? date('Y-m-d'));
        $supplierName = trim($_POST['supplier_name'] ?? '');
        $supplierPhone = trim($_POST['supplier_phone'] ?? '');
        $referenceNote = trim($_POST['reference_note'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        $quantities = $_POST['quantity'] ?? [];
        $unitCosts = $_POST['unit_cost'] ?? [];
        $items = [];

        $dateObj = DateTime::createFromFormat('Y-m-d', $purchaseDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $purchaseDate) {
            $errors[] = 'Please enter a valid purchase date.';
        }
        if (mb_strlen($supplierName) > 150 || mb_strlen($supplierPhone) > 50 ||
            mb_strlen($referenceNote) > 150) {
            $errors[] = 'Supplier or reference information is too long.';
        }

        $allowed = [];
        foreach ($products as $product) {
            $allowed[(int)$product['id']] = $product['name'];
        }

        foreach ($allowed as $productId => $productName) {
            $qtyRaw = $quantities[$productId] ?? '0';
            $costRaw = $unitCosts[$productId] ?? '0';

            if (!is_numeric($qtyRaw) || (string)(int)$qtyRaw !== (string)$qtyRaw || (int)$qtyRaw < 0) {
                $errors[] = 'Quantity for ' . $productName . ' must be a whole number of 0 or more.';
                continue;
            }
            if (!is_numeric($costRaw) || (float)$costRaw < 0) {
                $errors[] = 'Purchase cost for ' . $productName . ' must be 0 or more.';
                continue;
            }

            $qty = (int)$qtyRaw;
            $cost = round((float)$costRaw, 2);
            if ($qty > 0) {
                $items[] = [
                    'product_id' => $productId,
                    'name' => $productName,
                    'quantity' => $qty,
                    'unit_cost' => $cost,
                    'line_total' => round($qty * $cost, 2),
                ];
            }
        }

        if (!$items) {
            $errors[] = 'Enter a quantity greater than zero for at least one product.';
        }

        if (!$errors) {
            $totalCost = 0.0;
            foreach ($items as $item) {
                $totalCost += $item['line_total'];
            }
            $totalCost = round($totalCost, 2);

            try {
                $pdo->beginTransaction();

                $purchaseNumber = 'STK-' . date('Ymd', strtotime($purchaseDate)) . '-' . strtoupper(bin2hex(random_bytes(3)));

                $insertPurchase = $pdo->prepare("
                    INSERT INTO stock_purchases
                    (purchase_number, purchase_date, supplier_name, supplier_phone,
                     reference_note, notes, total_cost, status, created_by, received_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'received', ?, NOW())
                ");
                $insertPurchase->execute([
                    $purchaseNumber,
                    $purchaseDate,
                    $supplierName !== '' ? $supplierName : null,
                    $supplierPhone !== '' ? $supplierPhone : null,
                    $referenceNote !== '' ? $referenceNote : null,
                    $notes !== '' ? $notes : null,
                    $totalCost,
                    isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
                ]);

                $purchaseId = (int)$pdo->lastInsertId();

                $insertItem = $pdo->prepare("
                    INSERT INTO stock_purchase_items
                    (purchase_id, product_id, quantity, remaining_quantity, unit_cost, line_total)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insertMovement = $pdo->prepare("
                    INSERT INTO stock_movements
                    (product_id, movement_type, quantity_in, quantity_out, unit_cost,
                     reference_type, reference_id, notes, created_by)
                    VALUES (?, 'purchase_in', ?, 0, ?, 'purchase', ?, ?, ?)
                ");

                foreach ($items as $item) {
                    $insertItem->execute([
                        $purchaseId,
                        $item['product_id'],
                        $item['quantity'],
                        $item['quantity'],
                        $item['unit_cost'],
                        $item['line_total'],
                    ]);

                    $insertMovement->execute([
                        $item['product_id'],
                        $item['quantity'],
                        $item['unit_cost'],
                        $purchaseId,
                        'Purchase ' . $purchaseNumber,
                        isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
                    ]);
                }

                $pdo->commit();
                header('Location: stock.php?purchase_saved=1');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[stock-purchase] ' . $e->getMessage());
                $errors[] = 'Purchase could not be saved. Check the server error log or contact your developer.';
            }
        }
    }
}

$pageTitle = 'Record Stock Purchase';
require __DIR__ . '/includes/admin-header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1.2rem">
    <div>
        <h1 style="margin:0 0 .3rem">Record Stock Purchase</h1>
        <p style="color:#7a716a;margin:0">Enter the quantity purchased and the actual cost paid per item.</p>
    </div>
    <a class="btn btn-ghost" href="stock.php">← Back to Stock</a>
</div>

<?php if (!empty($_GET['purchase_saved'])): ?>
    <div class="alert alert-success">Stock purchase saved successfully. Stock quantities have been increased.</div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-error">
        <strong>Please correct the following:</strong>
        <ul style="margin:.5rem 0 0;padding-left:1.2rem">
            <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!$products): ?>
    <div class="alert alert-error">No active products are enabled for stock tracking.</div>
<?php else: ?>
<form method="post" class="card" style="padding:1.3rem">
    <?= csrf_field() ?>

    <h2 style="margin:0 0 1rem;font-size:1.1rem">Purchase Details</h2>
    <div class="form-grid">
        <div>
            <label for="purchase_date">Purchase date *</label>
            <input id="purchase_date" type="date" name="purchase_date" required value="<?= e($_POST['purchase_date'] ?? date('Y-m-d')) ?>">
        </div>
        <div>
            <label for="supplier_name">Supplier name</label>
            <input id="supplier_name" type="text" name="supplier_name" maxlength="150" value="<?= e($_POST['supplier_name'] ?? '') ?>" placeholder="Supplier / shop">
        </div>
        <div>
            <label for="supplier_phone">Supplier phone</label>
            <input id="supplier_phone" type="text" name="supplier_phone" maxlength="50" value="<?= e($_POST['supplier_phone'] ?? '') ?>">
        </div>
        <div>
            <label for="reference_note">Invoice / reference number</label>
            <input id="reference_note" type="text" name="reference_note" maxlength="150" value="<?= e($_POST['reference_note'] ?? '') ?>">
        </div>
    </div>

    <h2 style="margin:1.5rem 0 .35rem;font-size:1.1rem">Items Purchased</h2>
    <p style="color:#7a716a;font-size:.85rem;margin:0 0 .8rem">Leave quantity as 0 for products not included in this purchase. Unit cost is the amount you paid when buying it, not the selling price.</p>

    <div class="admin-table-wrap" style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;min-width:520px">
            <thead>
                <tr style="text-align:left;color:#7a716a;font-size:.72rem;text-transform:uppercase;letter-spacing:.08em">
                    <th style="padding:.8rem .5rem">Product</th>
                    <th style="padding:.8rem .5rem">Quantity</th>
                    <th style="padding:.8rem .5rem">Unit cost (RWF)</th>
                    <th style="padding:.8rem .5rem">Line total</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $product): ?>
                <?php $pid = (int)$product['id']; ?>
                <tr style="border-top:1px solid #262220">
                    <td style="padding:.8rem .5rem"><strong><?= e($product['name']) ?></strong></td>
                    <td style="padding:.6rem .5rem"><input class="stock-qty" type="number" min="0" step="1" name="quantity[<?= $pid ?>]" value="<?= e($_POST['quantity'][$pid] ?? '0') ?>" style="max-width:110px"></td>
                    <td style="padding:.6rem .5rem"><input class="stock-cost" type="number" min="0" step="0.01" name="unit_cost[<?= $pid ?>]" value="<?= e($_POST['unit_cost'][$pid] ?? '0') ?>" style="max-width:160px"></td>
                    <td class="stock-line-total" style="padding:.6rem .5rem;font-weight:700">0 RWF</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="border-top:2px solid #5a5149">
                    <th colspan="3" style="text-align:right;padding:.9rem .5rem">Purchase total</th>
                    <th id="stock-grand-total" style="padding:.9rem .5rem">0 RWF</th>
                </tr>
            </tfoot>
        </table>
    </div>

    <div style="margin-top:1rem">
        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" rows="3" placeholder="Optional notes about this purchase"><?= e($_POST['notes'] ?? '') ?></textarea>
    </div>

    <div style="display:flex;gap:.7rem;flex-wrap:wrap;margin-top:1.2rem">
        <button type="submit" class="btn btn-primary" onclick="return confirm('Save this purchase and add the quantities to stock?')">Save Purchase & Add to Stock</button>
        <a href="stock.php" class="btn btn-ghost">Cancel</a>
    </div>
</form>

<script>
(function () {
    const rows = document.querySelectorAll('tbody tr');
    const fmt = n => new Intl.NumberFormat('en-US', {maximumFractionDigits: 0}).format(n) + ' RWF';
    function recalc() {
        let grand = 0;
        rows.forEach(row => {
            const q = Math.max(0, Number(row.querySelector('.stock-qty')?.value || 0));
            const c = Math.max(0, Number(row.querySelector('.stock-cost')?.value || 0));
            const line = q * c;
            grand += line;
            const cell = row.querySelector('.stock-line-total');
            if (cell) cell.textContent = fmt(line);
        });
        document.getElementById('stock-grand-total').textContent = fmt(grand);
    }
    document.querySelectorAll('.stock-qty, .stock-cost').forEach(input => input.addEventListener('input', recalc));
    recalc();
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
