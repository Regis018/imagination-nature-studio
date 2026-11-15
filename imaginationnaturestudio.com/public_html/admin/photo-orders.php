<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_admin_only();

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$allowedStatuses = [
    'pending',
    'approved',
    'processing',
    'ready',
    'completed',
    'cancelled'
];

/**
 * Get actual database columns.
 */
function poGetColumns(PDO $pdo, string $table): array
{
    if (!in_array($table, ['sales', 'photo_orders'], true)) {
        throw new RuntimeException('Invalid table name.');
    }

    $rows = $pdo->query("SHOW COLUMNS FROM `$table`")
                ->fetchAll(PDO::FETCH_ASSOC);

    return array_column($rows, null, 'Field');
}

/* ---------------- Update Photo Order Status ---------------- */

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('Invalid security token. Reload the page and try again.');
    }

    $id = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'delete' && $id && (int)$id > 0) {
        $galleryIdsToRemove = [];
        try {
            $pdo->beginTransaction();

            $lockOrder = $pdo->prepare('SELECT id, sale_id FROM photo_orders WHERE id=? LIMIT 1 FOR UPDATE');
            $lockOrder->execute([(int)$id]);
            $orderToDelete = $lockOrder->fetch(PDO::FETCH_ASSOC);
            if (!$orderToDelete) {
                throw new RuntimeException('Photo Order not found.');
            }

            $saleStmt = $pdo->prepare('SELECT id FROM sales WHERE photo_order_id=? OR id=? FOR UPDATE');
            $saleStmt->execute([(int)$id, (int)($orderToDelete['sale_id'] ?? 0)]);
            $saleIdsToDelete = array_values(array_unique(array_map('intval', $saleStmt->fetchAll(PDO::FETCH_COLUMN))));

            if ($saleIdsToDelete) {
                $marks = implode(',', array_fill(0, count($saleIdsToDelete), '?'));
                $allocationCheck = $pdo->prepare("SELECT COUNT(*) FROM stock_sale_allocations WHERE sale_id IN ($marks)");
                $allocationCheck->execute($saleIdsToDelete);
                if ((int)$allocationCheck->fetchColumn() > 0) {
                    throw new RuntimeException('This linked Sale has stock allocations. Delete it from Sales first so stock can be restored safely.');
                }
            }

            $saleCondition = $saleIdsToDelete
                ? ' OR sale_id IN (' . implode(',', array_fill(0, count($saleIdsToDelete), '?')) . ')'
                : '';
            $galleryStmt = $pdo->prepare(
                'SELECT id FROM final_delivery_galleries WHERE photo_order_id=?' . $saleCondition . ' FOR UPDATE'
            );
            $galleryStmt->execute(array_merge([(int)$id], $saleIdsToDelete));
            $galleryIdsToRemove = array_map('intval', $galleryStmt->fetchAll(PDO::FETCH_COLUMN));

            if ($galleryIdsToRemove) {
                $marks = implode(',', array_fill(0, count($galleryIdsToRemove), '?'));
                $pdo->prepare("DELETE FROM final_delivery_photos WHERE gallery_id IN ($marks)")->execute($galleryIdsToRemove);
                $pdo->prepare("DELETE FROM final_delivery_galleries WHERE id IN ($marks)")->execute($galleryIdsToRemove);
            }

            if ($saleIdsToDelete) {
                $marks = implode(',', array_fill(0, count($saleIdsToDelete), '?'));
                $pdo->prepare("DELETE FROM sale_payments WHERE sale_id IN ($marks)")->execute($saleIdsToDelete);
                $pdo->prepare("DELETE FROM sales WHERE id IN ($marks)")->execute($saleIdsToDelete);
            }

            $pdo->prepare('DELETE FROM photo_orders WHERE id=?')->execute([(int)$id]);
            $pdo->commit();

            foreach ($galleryIdsToRemove as $galleryIdToRemove) {
                $dir = dirname(__DIR__) . '/storage/final-delivery/' . (int)$galleryIdToRemove;
                if (is_dir($dir) && !is_link($dir)) {
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::CHILD_FIRST
                    );
                    foreach ($iterator as $entry) {
                        if ($entry->isDir() && !$entry->isLink()) {
                            @rmdir($entry->getPathname());
                        } else {
                            @unlink($entry->getPathname());
                        }
                    }
                    @rmdir($dir);
                }
            }

            flash('ok', 'Photo Order, linked Sale, payments, Final Delivery gallery and uploaded photos were permanently deleted.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[PHOTO ORDER DELETE ERROR] Order ID ' . (int)$id . ': ' . $e->getMessage());
            flash('err', 'Delete failed: ' . $e->getMessage());
        }

        redirect(SITE_URL . '/admin/photo-orders.php');
    }

    $status = strtolower(trim((string)($_POST['order_status'] ?? '')));

    if (!$id || !in_array($status, $allowedStatuses, true)) {
        flash('err', 'Invalid order or status.');
        redirect(SITE_URL . '/admin/photo-orders.php');
    }

    try {
        /*
         * Approval and Sales insertion happen in the same transaction.
         * If Sales insertion fails, the order will not be approved either.
         */
        $pdo->beginTransaction();

        $findOrder = $pdo->prepare(
            'SELECT * FROM photo_orders WHERE id = ? LIMIT 1 FOR UPDATE'
        );
        $findOrder->execute([$id]);
        $order = $findOrder->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new RuntimeException('Photo order not found. ID: ' . $id);
        }

        if ($status === 'approved') {
            $salesColumns = poGetColumns($pdo, 'sales');

            if (!isset($salesColumns['photo_order_id'])) {
                throw new RuntimeException(
                    'Missing sales.photo_order_id column.'
                );
            }

            // Check whether this order has already been added to Sales.
            $findSale = $pdo->prepare(
                'SELECT id FROM sales WHERE photo_order_id = ? LIMIT 1'
            );
            $findSale->execute([$id]);
            $existingSaleId = $findSale->fetchColumn();

            if (!$existingSaleId) {
                $photoQty = (int)($order['photo_quantity'] ?? 0);
                $unitPrice = (float)($order['unit_price'] ?? 3000);
                $total = (float)($order['total_amount'] ?? ($photoQty * $unitPrice));
                $amountPaid = (float)($order['amount_paid'] ?? 0);

                if ($photoQty < 1 || $unitPrice <= 0 || $total <= 0) {
                    throw new RuntimeException(
                        'Invalid photo quantity, unit price or total for order ' . $id
                    );
                }

                $orderNumber = (string)($order['order_number'] ?? ('#' . $id));

                $values = [
                    'sale_date'      => date('Y-m-d'),
                    'customer_name'  => (string)($order['customer_name'] ?? ''),
                    'customer_phone' => (string)($order['customer_phone'] ?? ''),
                    'product_id'     => null,
                    'product_name'   => 'Photo Editing Order ' . $orderNumber,
                    'description'    => $photoQty . ' photo(s) x ' .
                                        number_format($unitPrice, 0) . ' RWF',
                    'quantity'       => $photoQty,
                    'unit_price'     => $unitPrice,
                    'total'          => $total,
                    'amount_paid'    => $amountPaid,
                    'balance'        => max(0, $total - $amountPaid),
                    'payment_method' => 'other',
                    'staff_name'     => 'Admin approval',
                    'notes'          => 'Created from Photo Orders. Order ID: ' . $id,
                    'photo_order_id' => $id
                ];

                $requiredColumns = [
                    'sale_date',
                    'customer_name',
                    'product_name',
                    'quantity',
                    'unit_price',
                    'total',
                    'photo_order_id'
                ];

                foreach ($requiredColumns as $column) {
                    if (!isset($salesColumns[$column])) {
                        throw new RuntimeException(
                            'Required sales column is missing: ' . $column
                        );
                    }
                }

                // Insert only columns that exist in this database.
                $insertValues = [];

                foreach ($values as $column => $value) {
                    if (isset($salesColumns[$column])) {
                        $insertValues[$column] = $value;
                    }
                }

                $columns = array_keys($insertValues);

                $quotedColumns = array_map(
                    static function ($column) {
                        return '`' . $column . '`';
                    },
                    $columns
                );

                $placeholders = array_fill(0, count($columns), '?');

                $insertSql = 'INSERT INTO `sales` (' .
                    implode(', ', $quotedColumns) .
                    ') VALUES (' . implode(', ', $placeholders) . ')';

                $insertSale = $pdo->prepare($insertSql);
                $insertSale->execute(array_values($insertValues));
            }
        }

        // Save status only after Sales is ready.
        $updateOrder = $pdo->prepare(
            'UPDATE photo_orders SET order_status = ? WHERE id = ?'
        );
        $updateOrder->execute([$status, $id]);

        $pdo->commit();

        if ($status === 'approved') {
            flash('ok', 'Photo order approved and added to Sales successfully.');
            redirect(
                SITE_URL . '/admin/photo-order-invoice.php?id=' . (int)$id
            );
        }

        flash('ok', 'Photo order status updated successfully.');
        redirect(SITE_URL . '/admin/photo-orders.php');

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            '[PHOTO ORDER SALES SYNC ERROR] Order ID ' .
            (string)$id . ': ' . $e->getMessage()
        );

        flash(
            'err',
            $status === 'approved'
                ? 'Approval failed. The order was not approved because Sales could not be updated. Check the hosting PHP Error Log.'
                : 'Order status could not be saved. Check the hosting PHP Error Log.'
        );

        redirect(SITE_URL . '/admin/photo-orders.php');
    }
}

/* ---------------- Load Photo Orders ---------------- */

$pageTitle = 'Photo Orders';

$filter = (string)($_GET['status'] ?? '');
$params = [];
$orders = [];
$pageLoadError = false;

try {
    $photoOrderColumns = poGetColumns($pdo, 'photo_orders');

    // Show all statuses so administrators can also delete approved/cancelled test orders.
    $sql = 'SELECT * FROM photo_orders';
    $where = [];

    if (
        isset($photoOrderColumns['order_status']) &&
        $filter !== '' &&
        in_array($filter, $allowedStatuses, true)
    ) {
        $where[] = 'order_status = ?';
        $params[] = $filter;
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $orderBy = [];

    if (isset($photoOrderColumns['created_at'])) {
        $orderBy[] = 'created_at DESC';
    }

    if (isset($photoOrderColumns['id'])) {
        $orderBy[] = 'id DESC';
    }

    if ($orderBy) {
        $sql .= ' ORDER BY ' . implode(', ', $orderBy);
    }

    $sql .= ' LIMIT 500';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $pageLoadError = true;

    error_log('[PHOTO ORDERS PAGE ERROR] ' . $e->getMessage());

    $orders = [];
}

$success = flash('ok');
$error = flash('err');

if ($pageLoadError) {
    $error = 'Photo Orders could not be loaded. Check the hosting PHP Error Log.';
}

$totalOrders = count($orders);
$totalValue = 0;
$pendingCount = 0;

foreach ($orders as $order) {
    if (($order['order_status'] ?? '') !== 'cancelled') {
        $totalValue += (float)($order['total_amount'] ?? 0);
    }

    if (($order['order_status'] ?? '') === 'pending') {
        $pendingCount++;
    }
}

require_once __DIR__ . '/includes/admin-header.php';
?>

<style>
.photo-orders {
    color: #e5e7eb;
    padding: 22px;
    max-width: 1600px;
    margin: 0 auto;
    font-family: Arial, Helvetica, sans-serif;
}
.photo-orders h1 {
    color: #f8fafc;
    font-size: 27px;
    margin: 0 0 8px;
}
.po-subtitle {
    color: #aab7c8;
    margin: 0 0 22px;
    line-height: 1.6;
}
.po-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 24px;
}
.po-stat {
    background: #172235;
    border: 1px solid #334155;
    border-radius: 12px;
    padding: 19px;
    box-shadow: 0 5px 18px rgba(0,0,0,.12);
}
.po-stat-label {
    color: #aab7c8;
    font-size: 13px;
    margin-bottom: 9px;
}
.po-stat-value {
    color: #f8fafc;
    font-size: 25px;
    font-weight: 700;
    overflow-wrap: anywhere;
}
.po-green { color: #86efac !important; }
.po-orange { color: #fdba74 !important; }
.po-message {
    padding: 13px 16px;
    border-radius: 9px;
    margin-bottom: 18px;
    background: #14532d;
    border: 1px solid #166534;
    color: #dcfce7;
}
.po-error {
    background: #7f1d1d;
    border-color: #991b1b;
    color: #fee2e2;
}
.po-filter-title {
    color: #f8fafc;
    font-size: 15px;
    margin: 0 0 11px;
    font-weight: 700;
}
.po-filter {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
    margin-bottom: 22px;
}
.po-filter a {
    display: inline-block;
    padding: 10px 14px;
    border: 1px solid #3b4b62;
    border-radius: 8px;
    text-decoration: none;
    color: #dbeafe;
    background: #1e293b;
    font-size: 13px;
}
.po-filter a:hover { background: #334155; }
.po-filter a.po-active {
    background: #2563eb;
    color: #fff;
    border-color: #3b82f6;
}
.po-table-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin: 0 0 12px;
}
.po-table-heading h2 {
    color: #f8fafc;
    font-size: 18px;
    margin: 0;
}
.po-count {
    color: #cbd5e1;
    background: #263449;
    border: 1px solid #3b4b62;
    border-radius: 20px;
    padding: 6px 11px;
    font-size: 12px;
}
.po-table-wrap {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #334155;
    border-radius: 12px;
    background: #111827;
    box-shadow: 0 8px 25px rgba(0,0,0,.15);
}
.po-table {
    width: 100%;
    min-width: 1000px;
    border-collapse: collapse;
    background: #111827;
    color: #e5e7eb;
}
.po-table th {
    background: #263449;
    color: #f8fafc;
    text-align: left;
    padding: 15px 13px;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .35px;
    white-space: nowrap;
    border-bottom: 1px solid #475569;
}
.po-table td {
    padding: 15px 13px;
    border-bottom: 1px solid #2b3749;
    text-align: left;
    vertical-align: top;
    font-size: 13px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}
.po-table tbody tr { background: #111827; }
.po-table tbody tr:nth-child(even) { background: #172235; }
.po-table tbody tr:hover { background: #243247; }
.po-table td strong { color: #f8fafc; }
.po-table td a {
    color: #7dd3fc;
    text-decoration: none;
}
.po-muted {
    color: #a8b4c5;
    font-size: 12px;
    margin-top: 4px;
}
.po-total {
    color: #86efac;
    font-weight: 700;
    white-space: nowrap;
}
.po-payment {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 20px;
    background: #263449;
    color: #e2e8f0;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
}
.po-status-form {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 130px;
}
.po-table select {
    width: 100%;
    max-width: 160px;
    padding: 9px;
    border: 1px solid #475569;
    border-radius: 7px;
    background: #1e293b;
    color: #f8fafc;
    font-size: 12px;
}
.po-table button {
    padding: 9px 13px;
    border: 0;
    border-radius: 7px;
    background: #2563eb;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}
.po-empty {
    padding: 35px 15px !important;
    text-align: center !important;
    color: #cbd5e1;
}
.po-note {
    max-width: 250px;
    white-space: normal;
    overflow-wrap: anywhere;
}
@media (max-width: 900px) {
    .po-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .photo-orders { padding: 16px; }
}
@media (max-width: 560px) {
    .photo-orders { padding: 12px; }
    .photo-orders h1 { font-size: 23px; }
    .po-summary { grid-template-columns: 1fr; gap: 10px; }
    .po-stat { padding: 15px; }
    .po-stat-value { font-size: 22px; }
    .po-filter { gap: 7px; }
    .po-filter a { padding: 9px 10px; }
}
</style>

<div class="photo-orders">
    <h1>📸 Photo Orders</h1>
    <p class="po-subtitle">
        Manage customer photo orders. Price per photo: 3,000 RWF.
    </p>

    <?php if ($success): ?>
        <div class="po-message"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="po-message po-error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="po-summary">
        <div class="po-stat">
            <div class="po-stat-label">Orders displayed</div>
            <div class="po-stat-value">
                <?= number_format($totalOrders) ?>
            </div>
        </div>

        <div class="po-stat">
            <div class="po-stat-label">Pending orders</div>
            <div class="po-stat-value po-orange">
                <?= number_format($pendingCount) ?>
            </div>
        </div>

        <div class="po-stat">
            <div class="po-stat-label">
                Value of non-cancelled orders displayed
            </div>
            <div class="po-stat-value po-green">
                <?= number_format($totalValue) ?>
                <span style="font-size:14px">RWF</span>
            </div>
        </div>
    </div>

    <div class="po-filter-title">Filter orders by status</div>

    <div class="po-filter">
        <a class="<?= $filter === '' ? 'po-active' : '' ?>"
           href="photo-orders.php">All orders</a>

        <?php foreach ($allowedStatuses as $s): ?>
            <a class="<?= $filter === $s ? 'po-active' : '' ?>"
               href="?status=<?= e($s) ?>">
                <?= e(ucfirst($s)) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="po-table-heading">
        <h2>Customer orders</h2>
        <span class="po-count">
            <?= number_format($totalOrders) ?> orders shown · Latest 500 maximum
        </span>
    </div>

    <div class="po-table-wrap">
        <table class="po-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Photos</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Feedback</th>
                    <th>Order status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$orders): ?>
                <tr>
                    <td colspan="8" class="po-empty">
                        No photo orders found for this filter.
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($orders as $order): ?>
                <tr>
                    <td>
                        <strong>
                            <?= e($order['order_number'] ?? '') ?>
                        </strong>
                        <div class="po-muted">
                            <?php
                            $createdAt = $order['created_at'] ?? '';
                            echo $createdAt
                                ? e(date('d.m.Y H:i', strtotime($createdAt)))
                                : 'Date unavailable';
                            ?>
                        </div>
                    </td>

                    <td>
                        <strong>
                            <?= e($order['customer_name'] ?? '') ?>
                        </strong>
                        <div>
                            <?php
                            $rawPhone = (string)(
                                $order['customer_phone'] ?? ''
                            );
                            $digits = preg_replace('/\D+/', '', $rawPhone);

                            if (
                                strlen($digits) === 10 &&
                                substr($digits, 0, 1) === '0'
                            ) {
                                $waPhone = '25' . $digits;
                            } else {
                                $waPhone = $digits;
                            }
                            ?>
                            <a href="https://wa.me/<?= e($waPhone) ?>"
                               target="_blank"
                               rel="noopener noreferrer">
                                <?= e($rawPhone) ?>
                            </a>
                        </div>
                    </td>

                    <td>
                        <?= number_format(
                            (int)($order['photo_quantity'] ?? 0)
                        ) ?>
                    </td>

                    <td class="po-total">
                        <?= number_format(
                            (float)($order['total_amount'] ?? 0)
                        ) ?> RWF
                    </td>

                    <td>
                        <span class="po-payment">
                            <?= e(ucfirst(
                                (string)($order['payment_status'] ?? 'unknown')
                            )) ?>
                        </span>
                        <div class="po-muted">
                            Paid:
                            <?= number_format(
                                (float)($order['amount_paid'] ?? 0)
                            ) ?> RWF
                        </div>
                    </td>

                    <td class="po-note">
                        <?php if (!empty($order['feedback'])): ?>
                            <?= nl2br(e($order['feedback'])) ?>
                        <?php else: ?>
                            <span class="po-muted">
                                No feedback provided
                            </span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <form class="po-status-form"
                              method="post"
                              action="photo-orders.php">

                            <?= csrf_field() ?>

                            <input type="hidden"
                                   name="order_id"
                                   value="<?= (int)$order['id'] ?>">

                            <select name="order_status"
                                    aria-label="Order status">
                                <?php foreach ($allowedStatuses as $s): ?>
                                    <option value="<?= e($s) ?>"
                                        <?= ($order['order_status'] ?? '') === $s
                                            ? 'selected'
                                            : '' ?>>
                                        <?= e(ucfirst($s)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button type="submit">Save status</button>
                        </form>
                    </td>
                    <td>
                        <form method="post"
                              action="photo-orders.php"
                              onsubmit="return confirm('PERMANENTLY DELETE this Photo Order? Its linked Sale, payments, Final Delivery gallery and uploaded photos will also be deleted. This cannot be undone.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                            <button type="submit"
                                    style="background:#991b1b;border:1px solid #dc2626;color:#fff;">
                                Delete Order
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</main>
</div>
</body>
</html>