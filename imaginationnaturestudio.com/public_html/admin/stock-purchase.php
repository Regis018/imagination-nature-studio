<?php
/**
 * Imagination Nature Studio — Record a received stock purchase.
 * Purchase quantities are converted into each product's stock unit.
 */
require_once __DIR__ . '/../includes/ops.php';
require_admin();
if (!is_admin()) { http_response_code(403); exit('Access denied. Admin only.'); }

$pdo = db();
$errors = [];
$products = $pdo->query("
    SELECT id, name, stock_unit, purchase_unit, units_per_purchase
    FROM products
    WHERE is_active=1 AND track_stock=1
    ORDER BY name, id
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $errors[] = 'Security token is invalid. Refresh and try again.';
    } else {
        $purchaseDate = trim($_POST['purchase_date'] ?? date('Y-m-d'));
        $supplierName = trim($_POST['supplier_name'] ?? '');
        $supplierPhone = trim($_POST['supplier_phone'] ?? '');
        $referenceNote = trim($_POST['reference_note'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $quantities = $_POST['purchase_quantity'] ?? [];
        $unitCosts = $_POST['purchase_cost'] ?? [];
        $items = [];

        $dateObj = DateTime::createFromFormat('Y-m-d', $purchaseDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $purchaseDate) $errors[] = 'Enter a valid purchase date.';

        $allowed = [];
        foreach ($products as $p) $allowed[(int)$p['id']] = $p;

        foreach ($allowed as $productId => $p) {
            $qtyRaw = $quantities[$productId] ?? '0';
            $costRaw = $unitCosts[$productId] ?? '0';
            if (!is_numeric($qtyRaw) || (string)(int)$qtyRaw !== (string)$qtyRaw || (int)$qtyRaw < 0) {
                $errors[] = 'Invalid purchase quantity for ' . $p['name'] . '.';
                continue;
            }
            if (!is_numeric($costRaw) || (float)$costRaw < 0) {
                $errors[] = 'Invalid purchase cost for ' . $p['name'] . '.';
                continue;
            }
            $purchaseQty = (int)$qtyRaw;
            $purchaseCost = round((float)$costRaw, 2);
            if ($purchaseQty > 0) {
                $factor = max(1, (int)$p['units_per_purchase']);
                $stockQty = $purchaseQty * $factor;
                $lineTotal = round($purchaseQty * $purchaseCost, 2);
                $items[] = [
                    'product_id'=>$productId, 'name'=>$p['name'],
                    'purchase_qty'=>$purchaseQty, 'purchase_unit'=>$p['purchase_unit'],
                    'stock_qty'=>$stockQty, 'factor'=>$factor,
                    'unit_cost'=>round($purchaseCost / $factor, 4),
                    'line_total'=>$lineTotal
                ];
            }
        }
        if (!$items) $errors[] = 'Enter a purchase quantity greater than zero.';

        if (!$errors) {
            $totalCost = round(array_sum(array_column($items, 'line_total')), 2);
            try {
                $pdo->beginTransaction();
                $purchaseNumber = 'STK-' . date('Ymd', strtotime($purchaseDate)) . '-' . strtoupper(bin2hex(random_bytes(3)));
                $stmt = $pdo->prepare("
                    INSERT INTO stock_purchases
                    (purchase_number,purchase_date,supplier_name,supplier_phone,reference_note,notes,total_cost,status,created_by,received_at)
                    VALUES (?,?,?,?,?,?,?,'received',?,NOW())
                ");
                $stmt->execute([
                    $purchaseNumber, $purchaseDate,
                    $supplierName !== '' ? $supplierName : null,
                    $supplierPhone !== '' ? $supplierPhone : null,
                    $referenceNote !== '' ? $referenceNote : null,
                    $notes !== '' ? $notes : null, $totalCost,
                    isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null
                ]);
                $purchaseId = (int)$pdo->lastInsertId();

                $insertItem = $pdo->prepare("
                    INSERT INTO stock_purchase_items
                    (purchase_id,product_id,quantity,remaining_quantity,unit_cost,line_total)
                    VALUES (?,?,?,?,?,?)
                ");
                $insertMovement = $pdo->prepare("
                    INSERT INTO stock_movements
                    (product_id,movement_type,quantity_in,quantity_out,unit_cost,reference_type,reference_id,notes,created_by)
                    VALUES (?,'purchase_in',?,0,?,'purchase',?,?,?)
                ");
                foreach ($items as $item) {
                    $insertItem->execute([
                        $purchaseId, $item['product_id'], $item['stock_qty'],
                        $item['stock_qty'], $item['unit_cost'],
                        round($item['stock_qty'] * $item['unit_cost'], 2)
                    ]);
                    $insertMovement->execute([
                        $item['product_id'], $item['stock_qty'], $item['unit_cost'],
                        $purchaseId,
                        'Purchase ' . $purchaseNumber . ': ' . $item['purchase_qty'] . ' ' . $item['purchase_unit'] .
                        ' x ' . $item['factor'] . ' stock units',
                        isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null
                    ]);
                }
                $pdo->commit();
                header('Location: stock.php?purchase_saved=1');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('[stock-purchase] ' . $e->getMessage());
                $errors[] = 'Purchase could not be saved. Check the server error log.';
            }
        }
    }
}
$pageTitle = 'Record Stock Purchase';
require __DIR__ . '/includes/admin-header.php';
?>
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1.2rem">
  <div><h1 style="margin:0 0 .3rem">Record Stock Purchase</h1>
  <p style="color:#7a716a;margin:0">Enter how many purchase units you bought and their cost.</p></div>
  <a class="btn btn-ghost" href="stock.php">← Back to Stock</a>
</div>
<?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (!$products): ?>
  <div class="alert alert-error">No active products have stock tracking enabled. Enable it in Products first.</div>
<?php else: ?>
<form method="post" class="card" style="padding:1.3rem">
  <?= csrf_field() ?>
  <div class="form-grid">
    <div><label>Purchase date *</label><input type="date" name="purchase_date" required value="<?= e($_POST['purchase_date'] ?? date('Y-m-d')) ?>"></div>
    <div><label>Supplier name</label><input name="supplier_name" maxlength="150" value="<?= e($_POST['supplier_name'] ?? '') ?>"></div>
    <div><label>Supplier phone</label><input name="supplier_phone" maxlength="50" value="<?= e($_POST['supplier_phone'] ?? '') ?>"></div>
    <div><label>Invoice / reference number</label><input name="reference_note" maxlength="150" value="<?= e($_POST['reference_note'] ?? '') ?>"></div>
  </div>
  <h2 style="margin:1.4rem 0 .4rem;font-size:1.1rem">Items purchased</h2>
  <p style="color:#7a716a;font-size:.85rem">Quantity is entered in the purchase unit (e.g. packets). Stock is automatically converted to the stock unit (e.g. sheets). Cost is per packet.</p>
  <div class="admin-table-wrap" style="overflow-x:auto">
  <table style="width:100%;border-collapse:collapse;min-width:700px">
    <thead><tr style="text-align:left;color:#7a716a"><th>Product</th><th>Purchase qty</th><th>Cost / purchase unit (RWF)</th><th>Stock added</th><th>Line total</th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): $pid=(int)$p['id']; $factor=max(1,(int)$p['units_per_purchase']); ?>
      <tr style="border-top:1px solid #262220">
        <td style="padding:.8rem .5rem"><strong><?= e($p['name']) ?></strong><br><small><?= e($p['purchase_unit']) ?> → <?= e($factor) ?> <?= e($p['stock_unit']) ?></small></td>
        <td><input class="pqty" data-factor="<?= $factor ?>" type="number" min="0" step="1" name="purchase_quantity[<?= $pid ?>]" value="<?= e($_POST['purchase_quantity'][$pid] ?? '0') ?>" style="max-width:110px"></td>
        <td><input class="pcost" type="number" min="0" step="0.01" name="purchase_cost[<?= $pid ?>]" value="<?= e($_POST['purchase_cost'][$pid] ?? '0') ?>" style="max-width:170px"></td>
        <td class="stock-added">0 <?= e($p['stock_unit']) ?></td>
        <td class="line-total">0 RWF</td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><th colspan="4" style="text-align:right;padding:.9rem">Purchase total</th><th id="grand-total">0 RWF</th></tr></tfoot>
  </table></div>
  <div style="margin-top:1rem"><label>Notes</label><textarea name="notes" rows="3"><?= e($_POST['notes'] ?? '') ?></textarea></div>
  <button class="btn btn-primary" style="margin-top:1rem" onclick="return confirm('Save purchase and add converted stock quantities?')">Save Purchase & Add to Stock</button>
</form>
<script>
(function(){
 const rows=document.querySelectorAll('tbody tr');
 const fmt=n=>new Intl.NumberFormat('en-US',{maximumFractionDigits:0}).format(n);
 function calc(){let grand=0;rows.forEach(row=>{
  const q=Number(row.querySelector('.pqty')?.value||0), c=Number(row.querySelector('.pcost')?.value||0);
  const f=Number(row.querySelector('.pqty')?.dataset.factor||1), line=q*c; grand+=line;
  row.querySelector('.stock-added').textContent=(q*f).toLocaleString()+' stock units';
  row.querySelector('.line-total').textContent=fmt(line)+' RWF';
 });document.getElementById('grand-total').textContent=fmt(grand)+' RWF';}
 document.querySelectorAll('.pqty,.pcost').forEach(el=>el.addEventListener('input',calc));calc();
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
