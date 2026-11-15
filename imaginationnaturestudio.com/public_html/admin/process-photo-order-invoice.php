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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Please submit the photo order using the form.');
}

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
    $clientToken = bin2hex(random_bytes(32));

    /*
     * Map available database columns to values.
     */
    $possibleValues = [
        'order_number'   => $orderNumber,
        'client_token'   => $clientToken,
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
        'client_token',
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

    /*
     * IMPORTANT:
     * Do not insert into the sales table here.
     * Admin approval handles the Sales entry.
     */

    /*
     * Send client to a persistent invoice URL. The token allows invoice access
     * without exposing invoices by guessable numeric ID. Pending invoices are view-only.
     */
    $newOrderId = (int)$pdo->lastInsertId();
    $invoiceUrl = rtrim(SITE_URL, '/') . '/admin/photo-order-invoice.php?id=' .
        $newOrderId . '&token=' . rawurlencode($clientToken);
    header('Location: ' . $invoiceUrl, true, 303);
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