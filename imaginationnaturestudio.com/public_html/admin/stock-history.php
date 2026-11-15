<?php
/**
 * Imagination Nature Studio — Stock History
 * Admin-only, read-only stock movement ledger.
 */
require_once __DIR__ . '/../includes/ops.php';
require_admin();

if (!is_admin()) {
    http_response_code(403);
    exit('Access denied. Admin only.');
}

$pdo = db();

function stock_history_valid_date(string $value): bool {
    if ($value === '') {
        return true;
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$productId = (int)($_GET['product_id'] ?? 0);
$movementType = trim($_GET['movement_type'] ?? '');

$errors = [];
if (!stock_history_valid_date($from)) {
    $errors[] = 'Start date is invalid.';
    $from = '';
}
if (!stock_history_valid_date($to)) {
    $errors[] = 'End date is invalid.';
    $to = '';
}
if ($from !== '' && $to !== '' && $from > $to) {
    $errors[] = 'Start date cannot be later than end date.';
    $from = '';
    $to = '';
}

$products = $pdo->query("
    SELECT id, name
    FROM products
    WHERE track_stock = 1
    ORDER BY name, id
")->fetchAll(PDO::FETCH_ASSOC);

$movementTypes = $pdo->query("
    SELECT DISTINCT movement_type
    FROM stock_movements
    WHERE movement_type IS NOT NULL AND movement_type <> ''
    ORDER BY movement_type
")->fetchAll(PDO::FETCH_COLUMN);

$where = [];
$params = [];

if ($productId > 0) {
    $where[] = 'sm.product_id = ?';
    $params[] = $productId;
}
if ($movementType !== '' && in_array($movementType, $movementTypes, true)) {
    $where[] = 'sm.movement_type = ?';
    $params[] = $movementType;
}
if ($from !== '') {
    $where[] = 'sm.created_at >= ?';
    $params[] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = 'sm.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
    $params[] = $to;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM stock_movements sm
    $whereSql
");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

$perPage = 50;
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = max(1, (int)($_GET['page'] ?? 1));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "
    SELECT
        sm.id,
        sm.product_id,
        p.name AS product_name,
        sm.movement_type,
        sm.quantity_in,
        sm.quantity_out,
        sm.reference_type,
        sm.reference_id,
        sm.notes,
        sm.created_at
    FROM stock_movements sm
    LEFT JOIN products p ON p.id = sm.product_id
    $whereSql
    ORDER BY sm.id DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

function stock_history_label(string $type): string {
    $labels = [
        'purchase_in' => 'Purchase received',
        'sale_out' => 'Sale',
        'sale_reversal' => 'Sale reversed',
        'return_in' => 'Customer return',
        'adjustment_in' => 'Stock increase',
        'adjustment_out' => 'Stock decrease',
    ];
    return $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
}

$pageTitle = 'Stock History';
require __DIR__ . '/includes/admin-header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1.2rem">
    <div>
        <h1 style="margin:0 0 .3rem">Stock History</h1>
        <p style="color:#7a716a;margin:0">Read-only record of stock purchases, sales and other movements.</p>
    </div>
    <a class="btn btn-ghost" href="stock.php">← Back to Stock</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $error): ?>
            <div><?= e($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card" style="padding:1rem;margin-bottom:1.2rem">
    <form method="get">
        <div class="form-grid">
            <div>
                <label for="product_id">Product</label>
                <select name="product_id" id="product_id">
                    <option value="0">All tracked products</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int)$product['id'] ?>" <?= $productId === (int)$product['id'] ? 'selected' : '' ?>>
                            <?= e($product['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="movement_type">Movement type</label>
                <select name="movement_type" id="movement_type">
                    <option value="">All movement types</option>
                    <?php foreach ($movementTypes as $type): ?>
                        <option value="<?= e($type) ?>" <?= $movementType === $type ? 'selected' : '' ?>>
                            <?= e(stock_history_label((string)$type)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="from">From date</label>
                <input type="date" id="from" name="from" value="<?= e($from) ?>">
            </div>
            <div>
                <label for="to">To date</label>
                <input type="date" id="to" name="to" value="<?= e($to) ?>">
            </div>
        </div>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1rem">
            <button type="submit" class="btn btn-primary">Filter History</button>
            <button
                type="button"
                class="btn btn-ghost"
                onclick="window.location.href = window.location.pathname;"
            >Clear Filters</button>
        </div>
    </form>
</div>

<div class="card" style="padding:0">
    <div style="padding:1rem 1.2rem;border-bottom:1px solid #262220;display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap">
        <h2 style="margin:0;font-size:1.1rem">Movement Records</h2>
        <span style="color:#7a716a;font-size:.85rem"><?= number_format($totalRows) ?> record(s)</span>
    </div>
    <div class="admin-table-wrap" style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;min-width:850px">
            <thead>
                <tr style="text-align:left;color:#7a716a;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">
                    <th style="padding:.85rem 1rem">Date &amp; Time</th>
                    <th>Product</th>
                    <th>Activity</th>
                    <th>Quantity In</th>
                    <th>Quantity Out</th>
                    <th>Reference</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($movements as $movement): ?>
                <tr style="border-top:1px solid #262220">
                    <td style="padding:.85rem 1rem;white-space:nowrap"><?= e($movement['created_at']) ?></td>
                    <td>
                        <strong><?= e($movement['product_name'] ?: 'Unknown product') ?></strong>
                        <small style="display:block;color:#7a716a">ID: <?= (int)$movement['product_id'] ?></small>
                    </td>
                    <td><?= e(stock_history_label((string)$movement['movement_type'])) ?></td>
                    <td style="color:#9bd5a8;font-weight:700"><?= (int)$movement['quantity_in'] > 0 ? '+' . number_format((int)$movement['quantity_in']) : '—' ?></td>
                    <td style="color:#e6a05c;font-weight:700"><?= (int)$movement['quantity_out'] > 0 ? '-' . number_format((int)$movement['quantity_out']) : '—' ?></td>
                    <td>
                        <?= e($movement['reference_type'] ?: '—') ?>
                        <?php if (!empty($movement['reference_id'])): ?>
                            <small style="display:block;color:#7a716a">#<?= (int)$movement['reference_id'] ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= e($movement['notes'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$movements): ?>
                <tr><td colspan="7" style="padding:1rem;color:#7a716a">No stock movements match these filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div style="padding:1rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
            <span style="color:#7a716a;font-size:.85rem">Page <?= $page ?> of <?= $totalPages ?></span>
            <div style="display:flex;gap:.5rem">
                <?php if ($page > 1): ?>
                    <a class="btn btn-ghost" href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">← Previous</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="btn btn-ghost" href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">Next →</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
