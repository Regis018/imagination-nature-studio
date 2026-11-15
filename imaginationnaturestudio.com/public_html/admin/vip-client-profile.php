<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin_only();

$pdo = db();

$pageTitle = 'VIP Client Profile';

$phoneInput = trim((string)($_GET['phone'] ?? ''));
$phoneKey = preg_replace('/\D+/', '', $phoneInput);

if ($phoneKey === '') {
    http_response_code(400);
    exit('A client phone number is required.');
}

/**
 * Normalize phone number.
 */
function vip_profile_phone_key($value): string
{
    return preg_replace('/\D+/', '', (string)$value);
}

/**
 * Determine VIP level.
 * Client qualifies by either visits OR spending.
 */
function vip_profile_level(int $visits, float $spent): array
{
    if ($visits >= 12 || $spent >= 1000000) {
        return ['VIP', 'vip'];
    }

    if ($visits >= 8 || $spent >= 500000) {
        return ['Gold', 'gold'];
    }

    if ($visits >= 5 || $spent >= 250000) {
        return ['Silver', 'silver'];
    }

    if ($visits >= 3 || $spent >= 100000) {
        return ['Bronze', 'bronze'];
    }

    return ['Regular', 'regular'];
}

/**
 * Read actual columns available in a database table.
 */
function vip_get_columns(PDO $pdo, string $table): array
{
    $allowedTables = [
        'customers',
        'bookings',
        'payments',
        'sales'
    ];

    if (!in_array($table, $allowedTables, true)) {
        throw new RuntimeException('Invalid database table.');
    }

    $columns = [];

    $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[] = $column['Field'];
    }

    return $columns;
}

/**
 * Build a SELECT query using only columns that exist.
 * Missing optional columns receive a default value.
 */
function vip_select_sql(
    string $table,
    array $availableColumns,
    array $fields
): string {
    $select = [];

    foreach ($fields as $alias => $definition) {

        $column = $definition[0];
        $default = $definition[1] ?? "''";

        if (in_array($column, $availableColumns, true)) {
            $select[] = "`$column` AS `$alias`";
        } else {
            $select[] = "$default AS `$alias`";
        }
    }

    return "SELECT " . implode(', ', $select) . " FROM `$table`";
}

/**
 * Format currency.
 */
function vip_money($amount): string
{
    return number_format((float)$amount) . ' RWF';
}

$client = null;

$customerIds = [];
$bookings = [];
$sales = [];
$paymentsByBooking = [];

$errorMessage = '';

$totalBookingPaid = 0;
$totalSalesPaid = 0;
$visits = 0;

$bookingBalance = 0;

try {

    /*
    |--------------------------------------------------------------------------
    | 1. CHECK DATABASE STRUCTURE
    |--------------------------------------------------------------------------
    */

    $customerColumns = vip_get_columns($pdo, 'customers');
    $bookingColumns = vip_get_columns($pdo, 'bookings');
    $paymentColumns = vip_get_columns($pdo, 'payments');
    $salesColumns = vip_get_columns($pdo, 'sales');


    /*
    |--------------------------------------------------------------------------
    | 2. FIND CUSTOMER
    |--------------------------------------------------------------------------
    */

    $customerFields = [
        'id'         => ['id', '0'],
        'full_name'  => ['full_name', "''"],
        'phone'      => ['phone', "''"],
        'whatsapp'   => ['whatsapp', "''"],
        'email'      => ['email', "''"],
        'location'   => ['location', "''"],
        'company'    => ['company', "''"],
        'created_at' => ['created_at', "''"]
    ];

    $customerSql = vip_select_sql(
        'customers',
        $customerColumns,
        $customerFields
    );

    $customerSql .= ' ORDER BY id DESC';

    $allCustomers = $pdo->query($customerSql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allCustomers as $row) {

        $phone = vip_profile_phone_key($row['phone']);
        $whatsapp = vip_profile_phone_key($row['whatsapp']);

        if ($phone === $phoneKey || $whatsapp === $phoneKey) {

            $customerIds[] = (int)$row['id'];

            if ($client === null) {

                $selectedPhone = $row['phone'] ?: $row['whatsapp'];

                $client = [
                    'name'       => $row['full_name'] ?: 'Unnamed client',
                    'phone'      => $selectedPhone,
                    'email'      => $row['email'],
                    'location'   => $row['location'],
                    'company'    => $row['company'],
                    'created_at' => $row['created_at']
                ];
            }
        }
    }

    $customerIds = array_values(array_unique($customerIds));


    /*
    |--------------------------------------------------------------------------
    | 3. LOAD SALES HISTORY
    |--------------------------------------------------------------------------
    */

    $salesFields = [
        'id'            => ['id', '0'],
        'sale_date'     => ['sale_date', "''"],
        'customer_name' => ['customer_name', "''"],
        'customer_phone'=> ['customer_phone', "''"],
        'product_name'  => ['product_name', "''"],
        'description'   => ['description', "''"],
        'quantity'      => ['quantity', '0'],
        'unit_price'    => ['unit_price', '0'],
        'amount'        => ['amount', '0'],
        'total_amount'  => ['total_amount', '0'],
        'amount_paid'   => ['amount_paid', '0'],
        'balance'       => ['balance', '0'],
        'payment_method'=> ['payment_method', "''"],
        'staff_name'    => ['staff_name', "''"],
        'notes'         => ['notes', "''"]
    ];

    $salesSql = vip_select_sql(
        'sales',
        $salesColumns,
        $salesFields
    );

    $salesSql .= ' ORDER BY sale_date DESC, id DESC';

    $allSales = $pdo->query($salesSql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allSales as $sale) {

        if (
            vip_profile_phone_key($sale['customer_phone']) === $phoneKey
        ) {

            $sales[] = $sale;

            $totalSalesPaid += (float)$sale['amount_paid'];

            if ($client === null) {

                $client = [
                    'name'       => $sale['customer_name'] ?: 'Walk-in client',
                    'phone'      => $sale['customer_phone'] ?: $phoneInput,
                    'email'      => '',
                    'location'   => '',
                    'company'    => '',
                    'created_at' => ''
                ];
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | 4. LOAD BOOKINGS
    |--------------------------------------------------------------------------
    */

    if (!empty($customerIds)) {

        $bookingFields = [
            'id'             => ['id', '0'],
            'booking_number' => ['booking_number', "''"],
            'invoice_number' => ['invoice_number', "''"],
            'booking_date'   => ['booking_date', "''"],
            'start_time'     => ['start_time', "''"],
            'end_time'       => ['end_time', "''"],
            'event_type'     => ['event_type', "''"],
            'total_amount'   => ['total_amount', '0'],
            'amount_paid'    => ['amount_paid', '0'],
            'balance'        => ['balance', '0'],
            'payment_status' => ['payment_status', "''"],
            'booking_status' => ['booking_status', "''"],
            'location'       => ['location', "''"]
        ];

        $bookingSql = vip_select_sql(
            'bookings',
            $bookingColumns,
            $bookingFields
        );

        $placeholders = implode(
            ',',
            array_fill(0, count($customerIds), '?')
        );

        $bookingSql .= " WHERE customer_id IN ($placeholders)";
        $bookingSql .= ' ORDER BY booking_date DESC, id DESC';

        $bookingStmt = $pdo->prepare($bookingSql);
        $bookingStmt->execute($customerIds);

        $bookings = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);


        /*
        |--------------------------------------------------------------------------
        | 5. LOAD PAYMENTS
        |--------------------------------------------------------------------------
        */

        $bookingIds = array_map(
            static function ($booking) {
                return (int)$booking['id'];
            },
            $bookings
        );

        if (!empty($bookingIds)) {

            $paymentFields = [
                'id'             => ['id', '0'],
                'booking_id'     => ['booking_id', '0'],
                'amount'         => ['amount', '0'],
                'payment_method' => ['payment_method', "''"],
                'payment_status' => ['payment_status', "''"],
                'payment_date'   => ['payment_date', "''"],
                'verified_at'    => ['verified_at', "''"],
                'notes'          => ['notes', "''"]
            ];

            $paymentSql = vip_select_sql(
                'payments',
                $paymentColumns,
                $paymentFields
            );

            $paymentPlaceholders = implode(
                ',',
                array_fill(0, count($bookingIds), '?')
            );

            $paymentSql .= " WHERE booking_id IN ($paymentPlaceholders)";
            $paymentSql .= ' ORDER BY payment_date DESC, id DESC';

            $paymentStmt = $pdo->prepare($paymentSql);
            $paymentStmt->execute($bookingIds);

            $paymentRows = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($paymentRows as $payment) {

                $bookingId = (int)$payment['booking_id'];

                $paymentsByBooking[$bookingId][] = $payment;

                if (
                    strtolower(trim($payment['payment_status'])) === 'successful'
                ) {
                    $totalBookingPaid += (float)$payment['amount'];
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | 6. CALCULATE VISITS
    |--------------------------------------------------------------------------
    */

    foreach ($bookings as $booking) {

        if (
            strtolower(trim($booking['booking_status'])) === 'completed'
        ) {
            $visits++;
        }

        $bookingBalance += max(
            0,
            (float)$booking['balance']
        );
    }

    /*
     * Count one sales visit per distinct sale date.
     */

    $saleDates = [];

    foreach ($sales as $sale) {

        $date = trim((string)$sale['sale_date']);

        if ($date !== '') {
            $saleDates[$date] = true;
        }
    }

    $visits += count($saleDates);

} catch (Throwable $e) {

    error_log(
        '[VIP CLIENT PROFILE] ' .
        $e->getMessage()
    );

    // Temporary diagnostic message to help identify database errors.
    $errorMessage = $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| 7. CLIENT NOT FOUND
|--------------------------------------------------------------------------
*/

if ($client === null && !$errorMessage) {

    http_response_code(404);

    exit('Client not found.');
}


/*
|--------------------------------------------------------------------------
| 8. VIP CALCULATIONS
|--------------------------------------------------------------------------
*/

$totalPaid = $totalBookingPaid + $totalSalesPaid;

[$level, $levelKey] = vip_profile_level(
    $visits,
    $totalPaid
);

$wa = preg_replace(
    '/\D+/',
    '',
    (string)($client['phone'] ?? $phoneInput)
);

if (substr($wa, 0, 1) === '0') {
    $wa = '250' . substr($wa, 1);
}


/*
|--------------------------------------------------------------------------
| 9. ADMIN HEADER
|--------------------------------------------------------------------------
*/

require __DIR__ . '/includes/admin-header.php';

?>

<style>

.vp-wrap {
    max-width: 1250px;
    margin: 0 auto;
    color: #f4eee7;
}

.vp-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.vp-top h1 {
    margin: 0;
    color: #fff;
    font-size: 30px;
}

.vp-muted {
    color: #a8b1bf;
}

.vp-btn {
    display: inline-block;
    padding: 10px 14px;
    border-radius: 9px;
    background: #e58a36;
    color: #1b1209;
    text-decoration: none;
    font-weight: 800;
}

.vp-btn:hover {
    background: #f4a34e;
    color: #1b1209;
}

.vp-card {
    background: #121a25;
    border: 1px solid #2a3545;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 18px;
}

.vp-profile {
    display: grid;
    grid-template-columns: 1.5fr repeat(3, 1fr);
    gap: 12px;
}

.vp-metric {
    background: #1a2432;
    border-radius: 12px;
    padding: 15px;
    min-width: 0;
}

.vp-metric small {
    display: block;
    color: #aab4c3;
    margin-bottom: 7px;
}

.vp-metric strong {
    font-size: 22px;
    color: #fff;
    overflow-wrap: anywhere;
}

.vp-level {
    display: inline-block;
    border-radius: 30px;
    padding: 6px 12px;
    font-weight: 900;
}

.vp-level.vip {
    background: #f5d77c;
    color: #30220a;
}

.vp-level.gold {
    background: #d9a441;
    color: #241805;
}

.vp-level.silver {
    background: #c8d1dc;
    color: #202832;
}

.vp-level.bronze {
    background: #b7794c;
    color: #21130c;
}

.vp-level.regular {
    background: #303b4b;
    color: #d7e0ed;
}

.vp-table-wrap {
    overflow-x: auto;
    border: 1px solid #293344;
    border-radius: 13px;
    margin-top: 12px;
}

.vp-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 720px;
}

.vp-table th,
.vp-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #293344;
}

.vp-table th {
    background: #1b2635;
    color: #b9c5d5;
    font-size: 12px;
    text-transform: uppercase;
}

.vp-table td {
    color: #eee;
    font-size: 13px;
}

.vp-section-title {
    margin: 25px 0 8px;
    color: #fff;
    font-size: 20px;
}

.vp-alert {
    padding: 14px;
    background: #352719;
    border: 1px solid #805a2c;
    color: #f5d9aa;
    border-radius: 10px;
    overflow-wrap: anywhere;
}

@media(max-width: 850px) {

    .vp-profile {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

}

@media(max-width: 520px) {

    .vp-profile {
        grid-template-columns: 1fr;
    }

    .vp-top h1 {
        font-size: 24px;
    }

}

</style>


<div class="vp-wrap">

    <div class="vp-top">

        <div>
            <h1>Client Profile</h1>

            <div class="vp-muted">
                Imagination Nature Studio · Loyalty history
            </div>
        </div>

        <a class="vp-btn" href="vip-clients.php">
            ← Back to VIP Clients
        </a>

    </div>


    <?php if ($errorMessage): ?>

        <div class="vp-alert">

            <strong>Database Error:</strong>

            <?= e($errorMessage) ?>

            <p style="margin-bottom:0">
                Please check the database table and column mentioned above.
            </p>

        </div>

    <?php else: ?>


        <!-- CLIENT INFORMATION -->

        <section class="vp-card">

            <div class="vp-profile">

                <div class="vp-metric">

                    <small>Client</small>

                    <strong>
                        <?= e($client['name']) ?>
                    </strong>

                    <div class="vp-muted">
                        <?= e($client['phone'] ?: 'No phone') ?>
                    </div>

                    <div style="margin-top:9px">

                        <span class="vp-level <?= e($levelKey) ?>">
                            <?= e($level) ?>
                        </span>

                    </div>

                </div>


                <div class="vp-metric">

                    <small>Completed visits</small>

                    <strong>
                        <?= number_format($visits) ?>
                    </strong>

                </div>


                <div class="vp-metric">

                    <small>Total paid</small>

                    <strong>
                        <?= vip_money($totalPaid) ?>
                    </strong>

                </div>


                <div class="vp-metric">

                    <small>Booking balance</small>

                    <strong>
                        <?= vip_money($bookingBalance) ?>
                    </strong>

                </div>

            </div>


            <div style="margin-top:15px" class="vp-muted">

                <?php if (!empty($client['email'])): ?>

                    Email: <?= e($client['email']) ?> ·

                <?php endif; ?>


                <?php if (!empty($client['location'])): ?>

                    Location: <?= e($client['location']) ?> ·

                <?php endif; ?>


                <?php if (!empty($client['created_at'])): ?>

                    Customer since:

                    <?= e(date('d M Y', strtotime($client['created_at']))) ?>

                <?php endif; ?>

            </div>


            <?php if ($wa): ?>

                <p style="margin:15px 0 0">

                    <a
                        class="vp-btn"
                        target="_blank"
                        rel="noopener"
                        href="https://wa.me/<?= e($wa) ?>"
                    >
                        Message on WhatsApp
                    </a>

                </p>

            <?php endif; ?>

        </section>



        <!-- BOOKING HISTORY -->

        <h2 class="vp-section-title">
            Booking history
        </h2>

        <div class="vp-table-wrap">

            <table class="vp-table">

                <thead>

                    <tr>
                        <th>Booking</th>
                        <th>Date</th>
                        <th>Service type</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Recorded paid</th>
                        <th>Balance</th>
                    </tr>

                </thead>


                <tbody>

                    <?php if (!$bookings): ?>

                        <tr>
                            <td colspan="7" class="vp-muted">
                                No bookings found for this phone number.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($bookings as $b): ?>

                            <?php

                            $paidForBooking = 0;

                            foreach (
                                ($paymentsByBooking[(int)$b['id']] ?? [])
                                as $pay
                            ) {

                                if (
                                    strtolower(trim($pay['payment_status'])) === 'successful'
                                ) {
                                    $paidForBooking += (float)$pay['amount'];
                                }

                            }

                            ?>

                            <tr>

                                <td>
                                    <?= e($b['booking_number'] ?: ('#' . $b['id'])) ?>
                                </td>

                                <td>
                                    <?= e($b['booking_date'] ?: '—') ?>
                                </td>

                                <td>
                                    <?= e($b['event_type'] ?: '—') ?>
                                </td>

                                <td>
                                    <?= e($b['booking_status'] ?: '—') ?>
                                </td>

                                <td>
                                    <?= vip_money($b['total_amount']) ?>
                                </td>

                                <td>
                                    <?= vip_money($paidForBooking) ?>
                                </td>

                                <td>
                                    <?= vip_money($b['balance']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>



        <!-- PAYMENT HISTORY -->

        <h2 class="vp-section-title">
            Booking payment history
        </h2>

        <div class="vp-table-wrap">

            <table class="vp-table">

                <thead>

                    <tr>
                        <th>Booking ID</th>
                        <th>Payment date</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Verified</th>
                    </tr>

                </thead>


                <tbody>

                    <?php

                    $hasPayments = false;

                    foreach ($paymentsByBooking as $bid => $rows):

                        foreach ($rows as $pay):

                            $hasPayments = true;

                    ?>

                        <tr>

                            <td>
                                #<?= (int)$bid ?>
                            </td>

                            <td>
                                <?= e($pay['payment_date'] ?: '—') ?>
                            </td>

                            <td>
                                <?= vip_money($pay['amount']) ?>
                            </td>

                            <td>
                                <?= e($pay['payment_method'] ?: '—') ?>
                            </td>

                            <td>
                                <?= e($pay['payment_status'] ?: '—') ?>
                            </td>

                            <td>
                                <?= e($pay['verified_at'] ?: '—') ?>
                            </td>

                        </tr>

                    <?php

                        endforeach;

                    endforeach;


                    if (!$hasPayments):

                    ?>

                        <tr>
                            <td colspan="6" class="vp-muted">
                                No payment records found.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>



        <!-- SALES HISTORY -->

        <h2 class="vp-section-title">
            Sales history
        </h2>

        <div class="vp-table-wrap">

            <table class="vp-table">

                <thead>

                    <tr>
                        <th>Date</th>
                        <th>Product / Description</th>
                        <th>Quantity</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Staff</th>
                    </tr>

                </thead>


                <tbody>

                    <?php if (!$sales): ?>

                        <tr>
                            <td colspan="7" class="vp-muted">
                                No sales found for this phone number.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($sales as $sale): ?>

                            <tr>

                                <td>
                                    <?= e($sale['sale_date'] ?: '—') ?>
                                </td>

                                <td>
                                    <?= e(
                                        $sale['product_name']
                                        ?: $sale['description']
                                        ?: 'Sale'
                                    ) ?>
                                </td>

                                <td>
                                    <?= number_format((int)$sale['quantity']) ?>
                                </td>

                                <td>
                                    <?= vip_money(
                                        $sale['total_amount'] ?: $sale['amount']
                                    ) ?>
                                </td>

                                <td>
                                    <?= vip_money($sale['amount_paid']) ?>
                                </td>

                                <td>
                                    <?= vip_money($sale['balance']) ?>
                                </td>

                                <td>
                                    <?= e($sale['staff_name'] ?: '—') ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>


        <p class="vp-muted" style="font-size:12px;margin-top:14px">

            Totals include successful booking payments and the amount_paid
            field from sales. Sale payment records are not added again,
            to avoid double counting.

        </p>


    <?php endif; ?>

</div>


<?php require __DIR__ . '/includes/admin-footer.php'; ?>