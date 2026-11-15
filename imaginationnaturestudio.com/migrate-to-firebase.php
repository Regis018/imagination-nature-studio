<?php
/**
 * ═══════════════════════════════════════════════════════════════
 *  Imagination Nature Studio — MySQL → Firebase Firestore Migration
 * ═══════════════════════════════════════════════════════════════
 *
 * Run from CLI:
 *   php migrate-to-firebase.php
 *
 * Prerequisites:
 *   1. Place your Firebase service account JSON at:
 *      public_html/includes/firebase/serviceAccount.json
 *   2. MySQL must be running locally with the imagination_db data
 *   3. composer install must have been run in public_html/
 *
 * What it does:
 *   - Reads every table from MySQL imagination_db
 *   - Writes each row as a Firestore document in matching collection
 *   - Preserves numeric IDs as document IDs (as strings)
 *   - Converts MySQL NULLs, timestamps, and booleans properly
 *   - Idempotent: safe to re-run (uses SET not ADD)
 */

declare(strict_types=1);

require __DIR__ . '/public_html/vendor/autoload.php';

use Kreait\Firebase\Factory;

/* ── Config ──────────────────────────────────────────────────── */
const MYSQL_HOST    = 'localhost';
const MYSQL_DB      = 'imagination_db';
const MYSQL_USER    = 'imagination_db';
const MYSQL_PASS    = 'WvhvtIxyL-AS(@C0';
const SERVICE_ACCOUNT = __DIR__ . '/public_html/includes/firebase/serviceAccount.json';
const BATCH_SIZE    = 400; // Firestore max batch = 500

/* ── Tables to migrate (order matters for dependencies) ─────── */
const TABLES = [
    'site_settings',
    'admins',
    'admin_sessions',
    'password_resets',
    'customers',
    'services',
    'packages',
    'promotions',
    'bookings',
    'booking_dates',
    'booking_items',
    'booking_counter',
    'walkin_bookings',
    'payments',
    'sales',
    'sale_payments',
    'expenses',
    'finance_expense_requests',
    'finance_monthly_obligations',
    'finance_obligation_payments',
    'stock_purchases',
    'stock_purchase_items',
    'stock_returns',
    'stock_return_items',
    'stock_movements',
    'stock_sale_allocations',
    'products',
    'gallery',
    'gallery_photos',
    'photo_galleries',
    'photo_selections',
    'photo_orders',
    'final_deliveries',
    'final_delivery_galleries',
    'final_delivery_photos',
    'homepage_sections',
    'homepage_featured',
    'messages',
    'monthly_rewards',
    'monthly_reward_winners',
    'push_subscriptions',
    'quotations',
    'quotation_items',
    'vip_promotions',
    'website_visits',
    'website_pageviews',
];

/* ══════════════════════════════════════════════════════════════ */
/*  MAIN                                                          */
/* ══════════════════════════════════════════════════════════════ */

echo "\n╔══════════════════════════════════════════════════╗\n";
echo "║  MySQL → Firebase Firestore Migration             ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";

/* Connect MySQL */
try {
    $pdo = new PDO(
        'mysql:host=' . MYSQL_HOST . ';dbname=' . MYSQL_DB . ';charset=utf8mb4',
        MYSQL_USER, MYSQL_PASS,
        [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ MySQL connected\n";
} catch (\PDOException $e) {
    die("✗ MySQL error: " . $e->getMessage() . "\n");
}

/* Connect Firebase */
if (!file_exists(SERVICE_ACCOUNT)) {
    die("✗ Service account not found at: " . SERVICE_ACCOUNT . "\n" .
        "  Download it from Firebase Console → Project Settings → Service Accounts\n");
}
try {
    $factory   = (new Factory)->withServiceAccount(SERVICE_ACCOUNT);
    $firestore = $factory->createFirestore();
    $db        = $firestore->database();
    echo "✓ Firebase connected\n\n";
} catch (\Throwable $e) {
    die("✗ Firebase error: " . $e->getMessage() . "\n");
}

/* ── Migrate each table ───────────────────────────────────────── */
$totalDocs   = 0;
$totalTables = 0;

foreach (TABLES as $table) {
    // Check table exists
    $exists = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
    if (!$exists) {
        echo "  ⚠  Table '$table' not found — skipping\n";
        continue;
    }

    $rows  = $pdo->query("SELECT * FROM `$table`")->fetchAll();
    $count = count($rows);

    if ($count === 0) {
        echo "  ○  $table (empty)\n";
        continue;
    }

    echo "  → Migrating $table ($count rows) … ";

    $collection = $db->collection($table);
    $batched    = 0;
    $batch      = $db->batch();

    foreach ($rows as $i => $row) {
        // Use MySQL id as Firestore document ID
        $docId = isset($row['id']) ? (string)$row['id'] : (string)($i + 1);
        $ref   = $collection->document($docId);

        $data = castRow($row);
        $batch->set($ref, $data);
        $batched++;

        // Commit in batches of BATCH_SIZE
        if ($batched >= BATCH_SIZE) {
            $batch->commit();
            $batch   = $db->batch();
            $batched = 0;
        }
    }

    if ($batched > 0) {
        $batch->commit();
    }

    $totalDocs   += $count;
    $totalTables++;
    echo "✓ $count docs\n";
}

echo "\n╔══════════════════════════════════════════════════╗\n";
echo "║  Migration complete!                              ║\n";
printf("║  Tables: %-3d  |  Documents: %-6d            ║\n", $totalTables, $totalDocs);
echo "╚══════════════════════════════════════════════════╝\n\n";

echo "Next step: set  define('DB_DRIVER', 'firestore');  in config.php\n\n";

/* ══════════════════════════════════════════════════════════════ */
/*  HELPERS                                                       */
/* ══════════════════════════════════════════════════════════════ */

function castRow(array $row): array
{
    $out = [];
    foreach ($row as $k => $v) {
        $out[$k] = castValue($k, $v);
    }
    return $out;
}

function castValue(string $key, mixed $v): mixed
{
    if ($v === null) return null;

    // Boolean-looking columns
    $boolCols = ['is_active','is_featured','is_published','is_deleted','is_read',
                 'is_paid','is_verified','is_blocked','enabled','active','deleted'];
    if (in_array($key, $boolCols, true)) {
        return (bool)(int)$v;
    }

    // Timestamp / datetime columns → Firestore Timestamp
    $tsCols = ['created_at','updated_at','deleted_at','approved_at','paid_at',
               'sent_at','opened_at','expired_at','booked_at','completed_at',
               'date','booking_date','start_date','end_date','due_date'];
    if (in_array($key, $tsCols, true) && $v !== '' && $v !== '0000-00-00 00:00:00') {
        try {
            $dt = new DateTime($v);
            return new \Google\Cloud\Core\Timestamp($dt);
        } catch (\Throwable) {}
    }

    // Numeric
    if (is_numeric($v) && !in_array($key, ['phone','phone_number','national_id','tin',
                                            'booking_number','order_number','receipt_number'])) {
        return str_contains((string)$v, '.') ? (float)$v : (int)$v;
    }

    return (string)$v;
}
