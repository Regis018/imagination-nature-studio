<?php
declare(strict_types=1);

/*
 * Imagination Nature Studio
 * Photo Editing Order Processor
 * Price: 3,000 RWF per photo
 * Orders remain pending until Admin approves them.
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');

try {
    require_once __DIR__ . '/includes/functions.php';

    if (!function_exists('db')) {
        throw new RuntimeException(
            'Database helper db() is unavailable.'
        );
    }

    $pdo = db();

    if (!$pdo instanceof PDO) {
        throw new RuntimeException(
            'Database connection is invalid.'
        );
    }

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    /*
     * AJAX status check: lets the customer page unlock printing only after
     * Admin approves the same order. Requires ID + order number + phone.
     */
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
        && isset($_GET['check_status'])) {
        header('Content-Type: application/json; charset=UTF-8');
        $checkId = filter_var($_GET['check_status'], FILTER_VALIDATE_INT);
        $checkNumber = trim((string)($_GET['order_number'] ?? ''));
        $checkPhone = trim((string)($_GET['phone'] ?? ''));

        if (!$checkId || $checkNumber === '' || $checkPhone === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid request']);
            exit;
        }

        $check = $pdo->prepare(
            'SELECT order_status, order_number, customer_phone
             FROM photo_orders WHERE id = ? LIMIT 1'
        );
        $check->execute([$checkId]);
        $record = $check->fetch(PDO::FETCH_ASSOC);

        if (!$record
            || !hash_equals((string)$record['order_number'], $checkNumber)
            || !hash_equals((string)$record['customer_phone'], $checkPhone)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Order not found']);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'status' => strtolower(trim((string)$record['order_status']))
        ]);
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Please submit the photo order using the form.');
    }

    /*
     * Read form values using common field-name variations.
     */
    $getValue = static function (
        array $names,
        string $default = ''
    ): string {
        foreach ($names as $name) {
            if (
                isset($_POST[$name]) &&
                !is_array($_POST[$name])
            ) {
                return trim((string) $_POST[$name]);
            }
        }

        return $default;
    };

    $customerName = $getValue([
        'customer_name',
        'name',
        'client_name',
        'full_name'
    ]);

    $customerPhone = $getValue([
        'customer_phone',
        'phone',
        'telephone',
        'client_phone'
    ]);

    $customerEmail = $getValue([
        'customer_email',
        'email',
        'client_email'
    ]);

    $feedback = $getValue([
        'feedback',
        'notes',
        'customer_notes',
        'message'
    ]);

    /*
     * Find selected photos from common form field names.
     */
    $selectedPhotos = [];

    foreach ([
        'selected_photos',
        'photo_ids',
        'photos',
        'selectedPhotos',
        'selected'
    ] as $key) {
        if (!isset($_POST[$key])) {
            continue;
        }

        $value = $_POST[$key];

        if (is_array($value)) {
            $selectedPhotos = $value;
            break;
        }

        if (
            is_string($value) &&
            trim($value) !== ''
        ) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $selectedPhotos = $decoded;
            } else {
                $selectedPhotos = array_filter(
                    array_map(
                        'trim',
                        explode(',', $value)
                    ),
                    static function ($item) {
                        return $item !== '';
                    }
                );
            }

            break;
        }
    }

    $quantity = count($selectedPhotos);

    /*
     * Support forms that submit quantity instead of photo IDs.
     */
    if ($quantity < 1) {
        $quantity = (int) $getValue([
            'photo_quantity',
            'photo_count',
            'quantity'
        ], '0');
    }

    if ($customerName === '') {
        throw new RuntimeException(
            'Customer name is missing. Check the name field in the form.'
        );
    }

    if ($customerPhone === '') {
        throw new RuntimeException(
            'Customer phone is missing. Check the phone field in the form.'
        );
    }

    if ($quantity < 1 || $quantity > 1000) {
        throw new RuntimeException(
            'No selected photos were received. Please select photos again.'
        );
    }

    /*
     * Calculate price.
     */
    $unitPrice = 3000;
    $totalAmount = $quantity * $unitPrice;

    /*
     * Read actual photo_orders database columns.
     */
    $columnQuery = $pdo->query(
        'SHOW COLUMNS FROM `photo_orders`'
    );

    $columns = [];

    foreach (
        $columnQuery->fetchAll(PDO::FETCH_ASSOC)
        as $column
    ) {
        $columns[$column['Field']] = $column;
    }

    if (!$columns) {
        throw new RuntimeException(
            'The photo_orders table was not found or has no columns.'
        );
    }

    /*
     * Generate order number.
     */
    $orderNumber = 'PO-' . date('Ymd') . '-' .
        strtoupper(bin2hex(random_bytes(4)));

    $now = date('Y-m-d H:i:s');

    /*
     * Map available database columns to values.
     */
    $possibleValues = [
        'order_number'   => $orderNumber,
        'customer_name'  => $customerName,
        'customer_phone' => $customerPhone,
        'customer_email' => $customerEmail,
        'email'          => $customerEmail,
        'photo_quantity' => $quantity,
        'quantity'       => $quantity,
        'unit_price'     => $unitPrice,
        'total_amount'   => $totalAmount,
        'total'          => $totalAmount,
        'payment_status' => 'unpaid',
        'amount_paid'    => 0,
        'feedback'       => $feedback,
        'order_status'   => 'pending',
        'status'         => 'pending',
        'sale_id'        => null,
        'created_at'     => $now,
        'updated_at'     => $now
    ];

    /*
     * Store selected photo identifiers if supported.
     */
    $photoJson = json_encode(
        array_values($selectedPhotos),
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );

    foreach ([
        'selected_photos',
        'photo_ids',
        'photos_json',
        'selected_photo_ids'
    ] as $photoColumn) {
        $possibleValues[$photoColumn] = $photoJson;
    }

    /*
     * Required columns used by Admin Photo Orders.
     */
    $requiredColumns = [
        'order_number',
        'customer_name',
        'customer_phone',
        'photo_quantity',
        'unit_price',
        'total_amount',
        'order_status'
    ];

    foreach ($requiredColumns as $requiredColumn) {
        if (!isset($columns[$requiredColumn])) {
            throw new RuntimeException(
                'Missing required photo_orders column: ' .
                $requiredColumn
            );
        }
    }

    /*
     * Insert only columns that exist in this database.
     */
    $insertValues = [];

    foreach ($possibleValues as $column => $value) {
        if (isset($columns[$column])) {
            $insertValues[$column] = $value;
        }
    }

    $fieldNames = array_keys($insertValues);

    $quotedFields = array_map(
        static function ($field) {
            return '`' .
                str_replace('`', '``', $field) .
                '`';
        },
        $fieldNames
    );

    $placeholders = array_fill(
        0,
        count($fieldNames),
        '?'
    );

    $sql = 'INSERT INTO `photo_orders` (' .
        implode(', ', $quotedFields) .
        ') VALUES (' .
        implode(', ', $placeholders) .
        ')';

    $stmt = $pdo->prepare($sql);

    $stmt->execute(
        array_values($insertValues)
    );

    $orderId = (int)$pdo->lastInsertId();

    /*
     * IMPORTANT:
     * Do not insert into the sales table here.
     * Admin approval handles the Sales entry.
     */

    /*
     * Show a professional invoice/confirmation immediately after saving.
     * Keep the order pending; Sales is still created only by Admin approval.
     */
    $escape = static function ($value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $invoiceDate = date('d/m/Y H:i');
    $safeOrderNumber = $escape($orderNumber);
    $safeOrderId = (int)$orderId;
    $phoneForScript = json_encode($customerPhone, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $orderNumberForScript = json_encode($orderNumber, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $safeCustomerName = $escape($customerName);
    $safeCustomerPhone = $escape($customerPhone);
    $safeCustomerEmail = $escape($customerEmail);
    $safeFeedback = $escape($feedback);
    $formattedQuantity = number_format($quantity);
    $formattedUnitPrice = number_format($unitPrice);
    $formattedTotal = number_format($totalAmount);

    http_response_code(200);
    header('Content-Type: text/html; charset=UTF-8');

    echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice ' . $safeOrderNumber . ' | Imagination Nature Studio</title>
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0; padding: 28px 14px; background: #f2f5f3;
        color: #24312b; font-family: Arial, Helvetica, sans-serif;
    }
    .page { max-width: 850px; margin: 0 auto; }
    .actions { display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; margin-bottom: 16px; }
    .btn { border: 0; border-radius: 7px; padding: 12px 17px; font-size: 14px; font-weight: 700; cursor: pointer; }
    .btn-primary { color: #fff; background: #16845b; }
    .btn-secondary { color: #24312b; background: #e1e9e4; }
    .invoice { background: #fff; padding: 42px; border-radius: 12px; box-shadow: 0 8px 28px rgba(0,0,0,.07); }
    .top { display: flex; justify-content: space-between; gap: 24px; padding-bottom: 26px; border-bottom: 2px solid #16845b; }
    .brand { color: #16845b; font-size: 25px; font-weight: 800; margin: 0 0 9px; }
    .muted { color: #66736c; line-height: 1.65; font-size: 13px; }
    .invoice-title { text-align: right; }
    .invoice-title h1 { margin: 0 0 8px; font-size: 30px; letter-spacing: 1px; }
    .status { display: inline-block; margin-top: 9px; padding: 7px 11px; border-radius: 20px; background: #fff0d5; color: #805500; font-size: 12px; font-weight: 700; }
    .details { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; padding: 26px 0; }
    .details h3 { margin: 0 0 10px; font-size: 12px; color: #16845b; text-transform: uppercase; letter-spacing: .7px; }
    .details p { margin: 4px 0; line-height: 1.5; overflow-wrap: anywhere; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th { padding: 13px 12px; text-align: left; background: #edf5f0; font-size: 12px; text-transform: uppercase; }
    td { padding: 16px 12px; border-bottom: 1px solid #e5ebe7; }
    .right { text-align: right; white-space: nowrap; }
    .total-row td { border-bottom: 0; font-weight: 800; font-size: 17px; }
    .total-row td:last-child { color: #16845b; }
    .note { margin-top: 26px; padding: 16px; border-radius: 8px; background: #f4f8f5; line-height: 1.65; font-size: 13px; }
    .footer { margin-top: 28px; padding-top: 16px; border-top: 1px solid #e5ebe7; text-align: center; color: #66736c; font-size: 12px; line-height: 1.7; }
    @media (max-width: 600px) {
        body { padding: 12px 8px; }
        .invoice { padding: 23px 16px; }
        .top { flex-direction: column; }
        .invoice-title { text-align: left; }
        .details { grid-template-columns: 1fr; gap: 18px; }
        .brand { font-size: 21px; }
        th, td { padding: 11px 7px; font-size: 12px; }
    }
    @media print {
        @page { size: A4; margin: 12mm; }
        body { padding: 0; background: #fff; }
        .page { max-width: none; }
        .actions { display: none !important; }
        .invoice { padding: 0; box-shadow: none; border-radius: 0; }
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>
</head>
<body data-order-id="' . $safeOrderId . '" data-order-number="' . $escape($orderNumber) . '" data-customer-phone="' . $escape($customerPhone) . '">
<div class="page">
    <div class="actions" id="invoiceActions" hidden style="display:none !important" aria-hidden="true">
        <button class="btn btn-primary" id="savePdfButton" type="button" disabled>Download PDF / Save as PDF</button>
        <button class="btn btn-secondary" id="printInvoiceButton" type="button" disabled>Print Invoice</button>
    </div>
    <main class="invoice">
        <header class="top">
            <div>
                <p class="brand">Imagination Nature Studio</p>
                <div class="muted">
                    Kigali, Rwanda<br>
                    TIN: 111997931<br>
                    Tel: 0788577033<br>
                    Email: rider.regis18@gmail.com
                </div>
            </div>
            <div class="invoice-title">
                <h1>INVOICE</h1>
                <div><strong>Invoice No:</strong> ' . $safeOrderNumber . '</div>
                <div><strong>Date:</strong> ' . $escape($invoiceDate) . '</div>
                <span class="status" id="approvalStatus">PENDING APPROVAL</span>
            </div>
        </header>

        <section class="details">
            <div>
                <h3>Bill To</h3>
                <p><strong>' . $safeCustomerName . '</strong></p>
                <p>Telephone: ' . $safeCustomerPhone . '</p>
                ' . ($customerEmail !== '' ? '<p>Email: ' . $safeCustomerEmail . '</p>' : '') . '
            </div>
            <div>
                <h3>Order Information</h3>
                <p><strong>Order Number:</strong> ' . $safeOrderNumber . '</p>
                <p><strong>Service:</strong> Photo Editing</p>
                <p><strong>Payment Status:</strong> Unpaid</p>
            </div>
        </section>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="right">Quantity</th>
                    <th class="right">Unit Price</th>
                    <th class="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Photo Editing Service</td>
                    <td class="right">' . $formattedQuantity . '</td>
                    <td class="right">' . $formattedUnitPrice . ' RWF</td>
                    <td class="right">' . $formattedTotal . ' RWF</td>
                </tr>
                <tr class="total-row">
                    <td colspan="3" class="right">TOTAL DUE</td>
                    <td class="right">' . $formattedTotal . ' RWF</td>
                </tr>
            </tbody>
        </table>

        ' . ($feedback !== '' ? '<div class="note"><strong>Customer Notes:</strong><br>' . nl2br($safeFeedback) . '</div>' : '') . '

        <div class="note" id="approvalMessage">
            <strong>Order received:</strong> Your photo editing order is pending Admin approval.
            Printing and PDF saving will become available after approval. Keep your order number for reference.
        </div>

        <footer class="footer">
            Thank you for choosing Imagination Nature Studio.<br>
            Kigali, Rwanda · 0788577033 · rider.regis18@gmail.com
        </footer>
    </main>
</div>
<script>
/* Fail closed: no print/save until the database confirms approval. */
(function () {
    const actions = document.getElementById(\'invoiceActions\');
    const status = document.getElementById(\'approvalStatus\');
    const message = document.getElementById(\'approvalMessage\');
    const saveButton = document.getElementById(\'savePdfButton\');
    const printButton = document.getElementById(\'printInvoiceButton\');
    const orderId = Number(document.body.dataset.orderId);
    const orderNumber = document.body.dataset.orderNumber || \'\';
    const phone = document.body.dataset.customerPhone || \'\';
    let approved = false;

    // Keep controls hidden and disabled until server confirmation.
    actions.hidden = true;
    actions.style.setProperty(\'display\', \'none\', \'important\');
    saveButton.disabled = true;
    printButton.disabled = true;

    function enableActions() {
        approved = true;
        actions.hidden = false;
        actions.removeAttribute(\'aria-hidden\');
        actions.style.removeProperty(\'display\');
        actions.style.setProperty(\'display\', \'flex\', \'important\');
        saveButton.disabled = false;
        printButton.disabled = false;
        saveButton.onclick = function () { if (approved) window.print(); };
        printButton.onclick = function () { if (approved) window.print(); };
        status.textContent = \'APPROVED\';
        status.style.background = \'#dcfce7\';
        status.style.color = \'#166534\';
        message.textContent = \'Approved: Your order has been approved. You can now print this invoice or save it as PDF.\';
    }

    async function checkApproval() {
        if (approved || !orderId || !orderNumber || !phone) return;
        try {
            const url = new URL(window.location.href);
            url.search = \'\';
            url.searchParams.set(\'check_status\', String(orderId));
            url.searchParams.set(\'order_number\', orderNumber);
            url.searchParams.set(\'phone\', phone);
            url.searchParams.set(\'_\', String(Date.now()));
            const response = await fetch(url.toString(), {
                method: \'GET\', credentials: \'same-origin\', cache: \'no-store\',
                headers: {\'Accept\': \'application/json\'}
            });
            if (!response.ok) return;
            const data = await response.json();
            if (!data.ok) return;
            const normalizedStatus = String(data.status || \'\').trim().toLowerCase().replace(/[\s-]+/g, \'_\');
            if (normalizedStatus === \'approved\') {
                enableActions();
            } else {
                status.textContent = normalizedStatus.toUpperCase().replace(/_/g, \' \') || \'PENDING APPROVAL\';
            }
        } catch (error) {
            // Intentionally keep controls hidden when the server cannot verify status.
        }
    }

    checkApproval();
    window.setInterval(checkApproval, 5000);
})();
</script>
</body>
</html>';

    exit;

} catch (Throwable $e) {

    error_log(
        '[PHOTO ORDER SUBMIT] ' .
        get_class($e) . ': ' .
        $e->getMessage()
    );

    http_response_code(500);

    header('Content-Type: text/html; charset=UTF-8');

    echo '<!DOCTYPE html>';
    echo '<html lang="en"><head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Photo Order Error</title>';
    echo '</head><body>';

    echo '<h2>Photo order could not be submitted.</h2>';

    echo '<p>Please try again. If the problem continues, ';
    echo 'contact Imagination Nature Studio.</p>';

    echo '<p>Your order may not have been saved. ';
    echo 'Please check before submitting again.</p>';

    echo '</body></html>';

    exit;
}