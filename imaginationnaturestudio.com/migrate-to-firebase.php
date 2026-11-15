<?php
/**
 * MySQL → Firebase Firestore Migration (REST API — no gRPC needed)
 * Run: php migrate-to-firebase.php
 */
declare(strict_types=1);
ini_set('memory_limit', '256M');
set_time_limit(0);
error_reporting(E_ALL & ~E_DEPRECATED); // suppress curl_close() deprecation (PHP 8.5)

require __DIR__ . '/public_html/vendor/autoload.php';

const MYSQL_HOST      = 'localhost';
const MYSQL_DB        = 'imagination_db';
const MYSQL_USER      = 'imagination_db';
const MYSQL_PASS      = 'WvhvtIxyL-AS(@C0';
const SERVICE_ACCOUNT = __DIR__ . '/public_html/includes/firebase/serviceAccount.json';
const PROJECT_ID      = 'imagination-nature-studio';
const FS_BASE         = 'https://firestore.googleapis.com/v1/projects/' . PROJECT_ID . '/databases/(default)/documents';
const CHUNK_SIZE      = 20;

const TABLES = [
    'site_settings','admins','admin_sessions','password_resets',
    'customers','services','packages','promotions',
    'bookings','booking_dates','booking_items','booking_counter',
    'walkin_bookings','payments','sales','sale_payments',
    'expenses','finance_expense_requests','finance_monthly_obligations',
    'finance_obligation_payments','stock_purchases','stock_purchase_items',
    'stock_returns','stock_return_items','stock_movements','stock_sale_allocations',
    'products','gallery','gallery_photos','photo_galleries',
    'photo_selections','photo_orders','final_deliveries',
    'final_delivery_galleries','final_delivery_photos',
    'homepage_sections','homepage_featured','messages',
    'monthly_rewards','monthly_reward_winners','push_subscriptions',
    'quotations','quotation_items','vip_promotions',
    // website_visits (19K) and website_pageviews (5.6K) skipped — analytics only,
    // exceeds free-tier 20K writes/day; app will track fresh visits in Firestore
];

echo "\n╔══════════════════════════════════════════════════╗\n";
echo "║  MySQL → Firebase Firestore Migration (REST)      ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";

/* ── MySQL ──────────────────────────────────────────────────── */
try {
    $pdo = new PDO(
        'mysql:host='.MYSQL_HOST.';dbname='.MYSQL_DB.';charset=utf8mb4',
        MYSQL_USER, MYSQL_PASS,
        [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ MySQL connected\n";
} catch (\Exception $e) { die("✗ MySQL: " . $e->getMessage() . "\n"); }

/* ── Firebase Auth Token ─────────────────────────────────────── */
$token = getAccessToken(SERVICE_ACCOUNT);
echo "✓ Firebase token obtained\n\n";

/* ── Migrate ─────────────────────────────────────────────────── */
$totalDocs = 0;

foreach (TABLES as $table) {
    $exists = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
    if (!$exists) { echo "  ⚠  $table — not found, skipping\n"; continue; }

    $count = (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    if ($count === 0) { echo "  ○  $table (empty)\n"; continue; }

    echo "  → $table ($count rows) … ";
    $done   = 0;
    $offset = 0;

    while ($offset < $count) {
        $rows = $pdo->query("SELECT * FROM `$table` LIMIT " . CHUNK_SIZE . " OFFSET $offset")->fetchAll();
        if (empty($rows)) break;

        foreach ($rows as $i => $row) {
            $docId = isset($row['id']) ? (string)$row['id'] : (string)($offset + $i + 1);
            $body  = ['fields' => toFirestoreFields($row)];
            $url   = FS_BASE . "/$table/$docId";

            $ok = fsRequest('PATCH', $url, $token, $body);
            if ($ok) { $done++; } else { echo "\n    ✗ doc $docId"; }
        }

        $offset += CHUNK_SIZE;
        usleep(100000); // 100ms between chunks
    }

    $totalDocs += $done;
    echo "✓ $done docs\n";
}

echo "\n╔══════════════════════════════════════════════════╗\n";
echo "║  Migration complete!                              ║\n";
printf("║  Documents written: %-6d                       ║\n", $totalDocs);
echo "╠══════════════════════════════════════════════════╣\n";
echo "║  Next: set define('DB_DRIVER','firestore')        ║\n";
echo "║        in public_html/config.php                  ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";

/* ══════════════════════════════════════════════════════════════
   HELPERS
   ══════════════════════════════════════════════════════════════ */

function getAccessToken(string $keyFile): string
{
    $key    = json_decode(file_get_contents($keyFile), true);
    $now    = time();
    $exp    = $now + 3600;
    $header = base64url(['alg'=>'RS256','typ'=>'JWT']);
    $claim  = base64url([
        'iss'   => $key['client_email'],
        'scope' => 'https://www.googleapis.com/auth/datastore',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $exp,
    ]);

    $sig = '';
    openssl_sign("$header.$claim", $sig, $key['private_key'], 'SHA256');
    $jwt = "$header.$claim." . base64url($sig, true);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
    ]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (empty($res['access_token'])) {
        die("✗ Could not get Firebase token: " . json_encode($res) . "\n");
    }
    return $res['access_token'];
}

function base64url(mixed $data, bool $raw = false): string
{
    $str = $raw ? $data : json_encode($data);
    return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
}

function fsRequest(string $method, string $url, string $token, ?array $body = null): bool
{
    $ch = curl_init($url);
    $headers = ['Authorization: Bearer ' . $token, 'Content-Type: application/json'];

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() is a no-op since PHP 8.0 — skip to avoid deprecation notice

    if ($code < 200 || $code >= 300) {
        $err = json_decode($res, true);
        $msg = $err['error']['message'] ?? $res;
        echo "\n    [HTTP $code] $msg";
        return false;
    }

    return true;
}

function toFirestoreFields(array $row): array
{
    $fields = [];
    foreach ($row as $k => $v) {
        $fields[$k] = toFirestoreValue($k, $v);
    }
    return $fields;
}

function toFirestoreValue(string $key, mixed $v): array
{
    if ($v === null || $v === '') return ['nullValue' => null];

    // Boolean columns
    $boolCols = ['is_active','is_featured','is_published','is_read','is_paid',
                 'is_verified','is_blocked','enabled','active'];
    if (in_array($key, $boolCols, true)) {
        return ['booleanValue' => (bool)(int)$v];
    }

    // Timestamp columns
    $tsCols = ['created_at','updated_at','deleted_at','approved_at','paid_at',
               'sent_at','expired_at','booked_at','completed_at',
               'date','booking_date','start_date','end_date','due_date'];
    if (in_array($key, $tsCols, true) && $v !== '0000-00-00 00:00:00' && $v !== '0000-00-00') {
        try {
            $dt = new DateTime($v);
            return ['timestampValue' => $dt->format('Y-m-d\TH:i:s\Z')];
        } catch (\Throwable) {}
    }

    // Skip numeric for phone/ID columns
    $skipNumeric = ['phone','phone_number','national_id','tin',
                    'booking_number','order_number','receipt_number'];
    if (is_numeric($v) && !in_array($key, $skipNumeric, true)) {
        if (str_contains((string)$v, '.')) return ['doubleValue' => (float)$v];
        return ['integerValue' => (string)(int)$v];
    }

    return ['stringValue' => (string)$v];
}
