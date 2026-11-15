<?php
/**
 * Imagination Nature Studio — Stock Dashboard
 * Admin-only. Reads the stock movement ledger; does not change Sales.
 */
require_once __DIR__ . '/../includes/ops.php';
require_admin();

if (!is_admin()) {
    http_response_code(403);
    exit('Access denied. Admin only.');
}

$pdo = db();

/*
 * Purchased In: received purchase movements only.
 * Net Deducted: stock-out quantities minus non-purchase stock-in quantities
 * (for example sale reversals, customer returns and positive adjustments).
 * Available: the exact ledger balance, all quantity_in minus quantity_out.
 *
 * Aggregate movements first so joining products cannot multiply totals.
 */
$products = $pdo->query("
    SELECT
        p.id,
        p.name,
        p.price,
        p.reorder_level,
        COALESCE(sm.total_purchased, 0) AS total_in,
        COALESCE(sm.net_deducted, 0) AS total_out,
        COALESCE(sm.stock_balance, 0) AS stock_balance
    FROM products p
    LEFT JOIN (
        SELECT
            product_id,
            SUM(
                CASE
                    WHEN movement_type = 'purchase_in'
                    THEN CAST(quantity_in AS SIGNED)
                    ELSE 0
                END
            ) AS total_purchased,
            SUM(CAST(quantity_out AS SIGNED))
              - SUM(
                    CASE
                        WHEN movement_type <> 'purchase_in'
                        THEN CAST(quantity_in AS SIGNED)
                        ELSE 0
                    END
                ) AS net_deducted,
            SUM(CAST(quantity_in AS SIGNED))
              - SUM(CAST(quantity_out AS SIGNED)) AS stock_balance
        FROM stock_movements
        GROUP BY product_id
    ) sm ON sm.product_id = p.id
    WHERE p.track_stock = 1
    ORDER BY p.name, p.id
")->fetchAll(PDO::FETCH_ASSOC);

$totalUnits = 0;
$lowStockCount = 0;

foreach ($products as $p) {
    $balance = (int)$p['stock_balance'];
    $totalUnits += $balance;

    if ($balance <= (int)$p['reorder_level']) {
        $lowStockCount++;
    }
}

$totalPurchases = (float)$pdo->query("
    SELECT COALESCE(SUM(total_cost), 0)
    FROM stock_purchases
    WHERE status = 'received'
")->fetchColumn();

$recentPurchases = $pdo->query("
    SELECT id, purchase_number, purchase_date, supplier_name, total_cost, status
    FROM stock_purchases
    ORDER BY id DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Stock Management';
require __DIR__ . '/includes/admin-header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1.2rem">
    <div>
        <h1 style="margin:0 0 .3rem">Stock Management</h1>
        <p style="color:#7a716a;margin:0">
            Track frame purchases, current quantities and low-stock alerts.
        </p>
    </div>

    <a class="btn btn-primary" href="stock-purchase.php">
        ＋ Record Stock Purchase
    </a>
</div>

<div class="form-grid" style="margin-bottom:1.3rem">

    <div class="card" style="padding:1.1rem">
        <div style="color:#7a716a;font-size:.78rem">Products tracked</div>
        <div style="font-size:1.8rem;font-weight:800">
            <?= count($products) ?>
        </div>
    </div>

    <div class="card" style="padding:1.1rem">
        <div style="color:#7a716a;font-size:.78rem">Units currently in stock</div>
        <div style="font-size:1.8rem;font-weight:800">
            <?= number_format($totalUnits) ?>
        </div>
    </div>

    <div class="card" style="padding:1.1rem">
        <div style="color:#7a716a;font-size:.78rem">Low-stock products</div>
        <div style="font-size:1.8rem;font-weight:800;color:<?= $lowStockCount ? '#e6a05c' : 'inherit' ?>">
            <?= $lowStockCount ?>
        </div>
    </div>

    <div class="card" style="padding:1.1rem">
        <div style="color:#7a716a;font-size:.78rem">Total received purchase cost</div>
        <div style="font-size:1.55rem;font-weight:800">
            <?= e(ops_money($totalPurchases)) ?> RWF
        </div>
    </div>

</div>

<div class="card" style="padding:0;margin-bottom:1.4rem">

    <div style="padding:1rem 1.2rem;border-bottom:1px solid #262220">

        <h2 style="margin:0;font-size:1.1rem">Current Stock</h2>

        <p style="color:#7a716a;margin:.25rem 0 0;font-size:.85rem">
            Purchased In = purchases received.
            Net Deducted = stock issued minus non-purchase stock returned.
            Available = current balance from all stock movements.
        </p>

    </div>

    <div class="admin-table-wrap" style="overflow-x:auto">

        <table style="width:100%;border-collapse:collapse">

            <thead>
                <tr style="text-align:left;color:#7a716a;font-size:.72rem;text-transform:uppercase;letter-spacing:.08em">
                    <th style="padding:.85rem 1rem">Product</th>
                    <th>Purchased In</th>
                    <th>Net Deducted</th>
                    <th>Available</th>
                    <th>Alert At</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($products as $p): ?>

                <?php
                    $balance = (int)$p['stock_balance'];
                    $low = $balance <= (int)$p['reorder_level'];
                ?>

                <tr style="border-top:1px solid #262220">

                    <td style="padding:.9rem 1rem">
                        <strong><?= e($p['name']) ?></strong>
                        <small style="display:block;color:#7a716a">
                            ID: <?= (int)$p['id'] ?>
                        </small>
                    </td>

                    <td>
                        <?= number_format((int)$p['total_in']) ?>
                    </td>

                    <td>
                        <?= number_format((int)$p['total_out']) ?>
                    </td>

                    <td>
                        <strong><?= number_format($balance) ?></strong>
                    </td>

                    <td>
                        <?= (int)$p['reorder_level'] ?>
                    </td>

                    <td>
                        <?php if ($low): ?>
                            <span style="color:#e6a05c;font-weight:700">
                                Low stock
                            </span>
                        <?php else: ?>
                            <span style="color:#9bd5a8;font-weight:700">
                                In stock
                            </span>
                        <?php endif; ?>
                    </td>

                </tr>

            <?php endforeach; ?>

            <?php if (!$products): ?>
                <tr>
                    <td colspan="6" style="padding:1rem;color:#7a716a">
                        No products have stock tracking enabled.
                    </td>
                </tr>
            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<div class="card" style="padding:0">

    <div style="padding:1rem 1.2rem;border-bottom:1px solid #262220">
        <h2 style="margin:0;font-size:1.1rem">
            Recent Stock Purchases
        </h2>
    </div>

    <div class="admin-table-wrap" style="overflow-x:auto">

        <table style="width:100%;border-collapse:collapse">

            <thead>
                <tr style="text-align:left;color:#7a716a;font-size:.72rem;text-transform:uppercase;letter-spacing:.08em">
                    <th style="padding:.85rem 1rem">Purchase No.</th>
                    <th>Date</th>
                    <th>Supplier</th>
                    <th>Total Cost</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($recentPurchases as $purchase): ?>

                <tr style="border-top:1px solid #262220">

                    <td style="padding:.9rem 1rem">
                        <strong><?= e($purchase['purchase_number']) ?></strong>
                    </td>

                    <td>
                        <?= e(ops_format_date($purchase['purchase_date'])) ?>
                    </td>

                    <td>
                        <?= e($purchase['supplier_name'] ?: '—') ?>
                    </td>

                    <td>
                        <?= e(ops_money($purchase['total_cost'])) ?> RWF
                    </td>

                    <td>
                        <?= e(ucfirst($purchase['status'])) ?>
                    </td>

                </tr>

            <?php endforeach; ?>

            <?php if (!$recentPurchases): ?>
                <tr>
                    <td colspan="5" style="padding:1rem;color:#7a716a">
                        No stock purchases recorded yet.
                    </td>
                </tr>
            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>