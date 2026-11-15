
<?php
/**
 * Imagination Nature Studio
 * Photo Order Invoice
 */

require_once __DIR__ . '/../includes/functions.php';

/**
 * Escape HTML safely.
 */
function poi_h($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/**
 * Read a value safely from a database row.
 */
function poi_value(array $row, array $keys, $default = '') 
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null) {
            if ($row[$key] !== '') {
                return $row[$key];
            }
        }
    }

    return $default;
}

/**
 * Format RWF amounts.
 */
function poi_money($amount): string
{
    return number_format((float)$amount, 0) . ' RWF';
}

$order = null;
$errorMessage = '';
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

try {
    // Require an authenticated administrator.
    require_admin_only();

    if (!$orderId || $orderId < 1) {
        http_response_code(400);
        throw new RuntimeException(
            'Order ID ntabwo ari yo. Ongera ufungure invoice ukoresheje order ID nyayo.'
        );
    }

    // Connect using the website's existing database helper.
    $pdo = db();

    if (!$pdo instanceof PDO) {
        throw new RuntimeException(
            'Database connection ntiyagarutse nka PDO.'
        );
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch the order without assuming every optional column exists.
    $stmt = $pdo->prepare(
        'SELECT * FROM photo_orders WHERE id = :id LIMIT 1'
    );

    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        throw new RuntimeException(
            'Photo order ntibashije kuboneka muri database.'
        );
    }

    // Only approved orders can have this invoice displayed.
    $status = strtolower(trim((string)poi_value(
        $order,
        ['order_status', 'status'],
        ''
    )));

    if ($status !== 'approved') {
        http_response_code(403);
        throw new RuntimeException(
            'Iyi photo order ntirafashwe nka approved. Banza uyemeze muri Photo Orders.'
        );
    }

} catch (Throwable $e) {
    $errorMessage = $e->getMessage();

    error_log(
        '[PHOTO ORDER INVOICE] ' .
        get_class($e) . ': ' .
        $e->getMessage() .
        ' in ' . $e->getFile() .
        ':' . $e->getLine()
    );
}

/*
 * Prepare invoice values only when the order was loaded successfully.
 */
if (is_array($order)) {
    $orderNumber = poi_value(
        $order,
        ['order_number', 'invoice_number', 'reference_number'],
        'PO-' . $orderId
    );

    $customerName = poi_value(
        $order,
        ['customer_name', 'client_name', 'full_name', 'name'],
        'N/A'
    );

    $customerPhone = poi_value(
        $order,
        ['customer_phone', 'client_phone', 'phone', 'telephone'],
        'N/A'
    );

    $customerEmail = poi_value(
        $order,
        ['customer_email', 'client_email', 'email'],
        'N/A'
    );

    $quantity = max(0, (int)poi_value(
        $order,
        ['photo_quantity', 'quantity', 'number_of_photos'],
        0
    ));

    $unitPrice = (float)poi_value(
        $order,
        ['unit_price', 'price_per_photo'],
        3000
    );

    $totalAmount = (float)poi_value(
        $order,
        ['total_amount', 'grand_total', 'amount'],
        $quantity * $unitPrice
    );

    $paymentStatus = poi_value(
        $order,
        ['payment_status'],
        'Pending'
    );

    $notes = poi_value(
        $order,
        ['customer_notes', 'feedback', 'notes', 'description'],
        ''
    );

    $createdAt = poi_value(
        $order,
        ['created_at', 'order_date', 'created_on'],
        ''
    );

    $dateDisplay = 'N/A';

    if ($createdAt !== '') {
        $timestamp = strtotime((string)$createdAt);

        if ($timestamp !== false) {
            $dateDisplay = date('d/m/Y H:i', $timestamp);
        }
    }

    $paidAmount = (float)poi_value(
        $order,
        ['amount_paid', 'paid_amount'],
        0
    );

    $balance = max(0, $totalAmount - $paidAmount);

    // Use a known local admin page for the back link.
    $backUrl = 'photo-orders.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?php if (is_array($order)): ?>
            Invoice <?= poi_h($orderNumber) ?> - Imagination Nature Studio
        <?php else: ?>
            Photo Order Invoice - Imagination Nature Studio
        <?php endif; ?>
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            background: #f2f4f7;
            color: #222;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
        }

        .toolbar {
            max-width: 850px;
            margin: 0 auto 18px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            border: 0;
            border-radius: 6px;
            padding: 11px 16px;
            text-decoration: none;
            color: #fff;
            background: #222;
            font-size: 14px;
            cursor: pointer;
        }

        .btn-print {
            background: #1769aa;
        }

        .invoice {
            width: 100%;
            max-width: 850px;
            min-height: 600px;
            margin: 0 auto;
            padding: 42px;
            background: #fff;
            box-shadow: 0 3px 18px rgba(0,0,0,.08);
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            border-bottom: 2px solid #222;
            padding-bottom: 22px;
        }

        .brand h1 {
            margin: 0 0 8px;
            font-size: 25px;
            letter-spacing: .3px;
        }

        .brand p,
        .invoice-meta p {
            margin: 5px 0;
            line-height: 1.5;
        }

        .invoice-title {
            margin: 25px 0;
            font-size: 25px;
            font-weight: bold;
        }

        .details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .details h3 {
            margin: 0 0 10px;
            font-size: 14px;
            text-transform: uppercase;
            color: #555;
        }

        .details p {
            margin: 6px 0;
            overflow-wrap: anywhere;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 13px 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #f5f5f5;
            font-size: 12px;
            text-transform: uppercase;
        }

        .right {
            text-align: right;
        }

        .totals {
            width: 100%;
            max-width: 340px;
            margin: 22px 0 0 auto;
        }

        .totals div {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 8px 0;
        }

        .totals .grand-total {
            border-top: 2px solid #222;
            font-size: 18px;
            font-weight: bold;
            margin-top: 5px;
            padding-top: 14px;
        }

        .notes {
            margin-top: 28px;
            padding: 14px;
            background: #f7f7f7;
            overflow-wrap: anywhere;
        }

        .footer {
            margin-top: 45px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #555;
            line-height: 1.7;
        }

        .error {
            max-width: 850px;
            margin: 0 auto;
            padding: 22px;
            border: 1px solid #e4b7b7;
            border-radius: 8px;
            background: #fff;
            color: #8a1f1f;
        }

        @media (max-width: 600px) {
            body {
                padding: 12px;
            }

            .invoice {
                padding: 22px 16px;
            }

            .details {
                grid-template-columns: 1fr;
            }

            .brand h1 {
                font-size: 21px;
            }
        }

        @media print {
            body {
                padding: 0;
                background: #fff;
            }

            .toolbar {
                display: none !important;
            }

            .invoice {
                max-width: none;
                min-height: auto;
                padding: 0;
                box-shadow: none;
            }

            .error {
                display: none;
            }

            @page {
                size: A4;
                margin: 15mm;
            }
        }
    </style>
</head>

<body>

<?php if ($errorMessage !== ''): ?>

    <div class="error">
        <h2>Invoice ntiyashoboye gufunguka</h2>
        <p><?= poi_h($errorMessage) ?></p>
        <p>
            Subira muri Photo Orders urebe ko order ibaho kandi
            ko status yayo ari Approved.
        </p>
        <a class="btn" href="photo-orders.php">
            Back to Photo Orders
        </a>
    </div>

<?php else: ?>

    <div class="toolbar">
        <a class="btn" href="<?= poi_h($backUrl) ?>">
            &larr; Back to Photo Orders
        </a>

        <button class="btn btn-print" onclick="window.print()">
            Print / Save as PDF
        </button>
    </div>

    <main class="invoice">

        <header class="invoice-header">
            <div class="brand">
                <h1>IMAGINATION NATURE STUDIO</h1>
                <p>Photography, Printing &amp; Creative Services</p>
                <p>Kigali, Rwanda</p>
                <p>Tel: 0788577033</p>
            </div>

            <div class="invoice-meta">
                <p><strong>INVOICE</strong></p>
                <p>Invoice No: <?= poi_h($orderNumber) ?></p>
                <p>Date: <?= poi_h($dateDisplay) ?></p>
                <p>Order ID: <?= (int)$orderId ?></p>
            </div>
        </header>

        <h2 class="invoice-title">PHOTO ORDER INVOICE</h2>

        <section class="details">
            <div>
                <h3>Bill To</h3>
                <p><strong><?= poi_h($customerName) ?></strong></p>
                <p>Phone: <?= poi_h($customerPhone) ?></p>
                <p>Email: <?= poi_h($customerEmail) ?></p>
            </div>

            <div>
                <h3>Order Information</h3>
                <p>Order status: <?= poi_h(
                    poi_value($order, ['order_status', 'status'], 'Approved')
                ) ?></p>
                <p>Payment status: <?= poi_h($paymentStatus) ?></p>
            </div>
        </section>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="right">Qty</th>
                    <th class="right">Unit Price</th>
                    <th class="right">Amount</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <td>Selected Photos</td>
                    <td class="right"><?= (int)$quantity ?></td>
                    <td class="right"><?= poi_h(poi_money($unitPrice)) ?></td>
                    <td class="right"><?= poi_h(poi_money($totalAmount)) ?></td>
                </tr>
            </tbody>
        </table>

        <section class="totals">
            <div>
                <span>Subtotal</span>
                <strong><?= poi_h(poi_money($totalAmount)) ?></strong>
            </div>

            <div>
                <span>Amount Paid</span>
                <strong><?= poi_h(poi_money($paidAmount)) ?></strong>
            </div>

            <div class="grand-total">
                <span>Balance</span>
                <span><?= poi_h(poi_money($balance)) ?></span>
            </div>
        </section>

        <?php if (trim((string)$notes) !== ''): ?>
            <div class="notes">
                <strong>Notes:</strong><br>
                <?= nl2br(poi_h($notes)) ?>
            </div>
        <?php endif; ?>

        <footer class="footer">
            <strong>Thank you for choosing Imagination Nature Studio!</strong>
            <br>
            Kigali, Rwanda | 0788577033
        </footer>

    </main>

<?php endif; ?>

</body>
</html>
