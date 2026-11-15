<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['customers_csrf'])) {
    $_SESSION['customers_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['customers_csrf'];

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function money($v): string {
    return number_format((float)$v, 0) . ' RWF';
}

function wa_link(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === '') return '#';
    if (str_starts_with($digits, '0')) $digits = '250' . substr($digits, 1);
    elseif (!str_starts_with($digits, '250')) $digits = '250' . $digits;
    return 'https://wa.me/' . $digits;
}

function customer_name_sql(): string {
    return "COALESCE(NULLIF(TRIM(c.full_name), ''), 'Unnamed Customer')";
}

$search = trim((string)($_GET['q'] ?? ''));
$location = trim((string)($_GET['location'] ?? ''));

$stats = [
    'customers' => 0,
    'with_bookings' => 0,
    'revenue' => 0.0,
    'outstanding' => 0.0,
];

try {
    $stats['customers'] = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();

    $stats['with_bookings'] = (int)$pdo->query("
        SELECT COUNT(DISTINCT customer_id)
        FROM bookings
        WHERE customer_id IS NOT NULL
    ")->fetchColumn();

    $stats['revenue'] = (float)$pdo->query("
        SELECT COALESCE(SUM(amount_paid),0)
        FROM bookings
    ")->fetchColumn();

    $stats['outstanding'] = (float)$pdo->query("
        SELECT COALESCE(SUM(GREATEST(balance,0)),0)
        FROM bookings
        WHERE booking_status <> 'cancelled'
    ")->fetchColumn();
} catch (Throwable $e) {
    error_log('[customers stats] ' . $e->getMessage());
}

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(c.full_name LIKE ? OR c.phone LIKE ? OR c.whatsapp LIKE ? OR c.email LIKE ? OR c.location LIKE ? OR c.company LIKE ?)";
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term, $term);
}

if ($location !== '') {
    $where[] = "c.location = ?";
    $params[] = $location;
}

$sql = "
    SELECT
        c.id,
        c.full_name,
        c.phone,
        c.whatsapp,
        c.email,
        c.location,
        c.company,
        c.created_at,
        COUNT(DISTINCT b.id) AS booking_count,
        COALESCE(SUM(CASE WHEN b.booking_status <> 'cancelled' THEN b.total_amount ELSE 0 END),0) AS total_value,
        COALESCE(SUM(CASE WHEN b.booking_status <> 'cancelled' THEN b.amount_paid ELSE 0 END),0) AS total_paid,
        COALESCE(SUM(CASE WHEN b.booking_status <> 'cancelled' THEN GREATEST(b.balance,0) ELSE 0 END),0) AS total_balance,
        MAX(b.booking_date) AS last_booking_date
    FROM customers c
    LEFT JOIN bookings b ON b.customer_id = c.id
";

if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= "
    GROUP BY c.id, c.full_name, c.phone, c.whatsapp, c.email, c.location, c.company, c.created_at
    ORDER BY COALESCE(MAX(b.booking_date), '1000-01-01') DESC, c.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$locations = [];
try {
    $locations = $pdo->query("
        SELECT DISTINCT location
        FROM customers
        WHERE location IS NOT NULL AND TRIM(location) <> ''
        ORDER BY location
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {}

require __DIR__ . '/includes/admin-header.php';
?>
<style>
.crm-wrap{max-width:1500px;margin:0 auto;padding:24px}
.crm-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:22px}
.crm-title{font-size:30px;font-weight:900;margin:0}
.crm-sub{color:#9ca3af;margin-top:7px}
.crm-actions{display:flex;gap:10px;flex-wrap:wrap}
.crm-btn{border:0;border-radius:12px;padding:11px 16px;text-decoration:none;font-weight:800;cursor:pointer}
.crm-btn-primary{background:#fff;color:#111827}.crm-btn-dark{background:#1f2937;color:#fff;border:1px solid #374151}
.crm-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px}
.crm-stat{background:#111827;border:1px solid #253044;border-radius:18px;padding:18px;box-shadow:0 12px 30px rgba(0,0,0,.16)}
.crm-label{font-size:12px;color:#9ca3af;text-transform:uppercase;font-weight:800;letter-spacing:.06em}
.crm-value{font-size:25px;font-weight:900;margin-top:7px}
.crm-filter{display:flex;gap:10px;flex-wrap:wrap;background:#111827;border:1px solid #253044;padding:14px;border-radius:16px;margin-bottom:18px}
.crm-filter input,.crm-filter select{background:#0b1220;color:#fff;border:1px solid #344054;border-radius:10px;padding:11px 13px;min-height:42px}
.crm-filter input{min-width:280px;flex:1}
.crm-table-card{background:#111827;border:1px solid #253044;border-radius:18px;overflow:hidden}
.crm-table{width:100%;border-collapse:collapse}
.crm-table th{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;text-align:left;padding:14px;border-bottom:1px solid #253044}
.crm-table td{padding:14px;border-bottom:1px solid #202a3a;vertical-align:middle}
.crm-table tr:hover{background:#151e2e}
.crm-name{font-weight:900;color:#fff}.crm-muted{color:#9ca3af;font-size:12px;margin-top:3px}
.crm-pill{display:inline-flex;padding:5px 9px;border-radius:999px;background:#1f2937;color:#d1d5db;font-size:11px;font-weight:800}
.crm-money{font-weight:900}.crm-green{color:#34d399}.crm-orange{color:#fbbf24}
.crm-view{display:inline-block;background:#fff;color:#111827;text-decoration:none;padding:8px 12px;border-radius:9px;font-weight:900;font-size:12px}
.crm-empty{text-align:center;padding:55px;color:#9ca3af}
.crm-wa{color:#34d399;text-decoration:none;font-weight:800}
@media(max-width:1000px){.crm-grid{grid-template-columns:repeat(2,1fr)}.crm-table-card{overflow-x:auto}.crm-table{min-width:1050px}}
@media(max-width:600px){.crm-wrap{padding:15px}.crm-head{flex-direction:column}.crm-grid{grid-template-columns:1fr 1fr}.crm-title{font-size:24px}.crm-filter input{min-width:100%}}
</style>

<div class="crm-wrap">
    <div class="crm-head">
        <div>
            <h1 class="crm-title">👥 Customers</h1>
            <div class="crm-sub">Customer database, bookings, payments and balances in one place.</div>
        </div>
        <div class="crm-actions">
            <a class="crm-btn crm-btn-dark" href="dashboard.php">← Dashboard</a>
            <a class="crm-btn crm-btn-primary" href="customer-view.php?new=1">＋ Add Customer</a>
        </div>
    </div>

    <div class="crm-grid">
        <div class="crm-stat"><div class="crm-label">Total Customers</div><div class="crm-value"><?=number_format($stats['customers'])?></div></div>
        <div class="crm-stat"><div class="crm-label">Customers With Bookings</div><div class="crm-value"><?=number_format($stats['with_bookings'])?></div></div>
        <div class="crm-stat"><div class="crm-label">Booking Value Paid</div><div class="crm-value crm-green"><?=h(money($stats['revenue']))?></div></div>
        <div class="crm-stat"><div class="crm-label">Outstanding</div><div class="crm-value crm-orange"><?=h(money($stats['outstanding']))?></div></div>
    </div>

    <form class="crm-filter" method="get">
        <input type="search" name="q" value="<?=h($search)?>" placeholder="Search name, phone, WhatsApp, email, location...">
        <select name="location">
            <option value="">All Locations</option>
            <?php foreach($locations as $loc): ?>
                <option value="<?=h($loc)?>" <?=$location===$loc?'selected':''?>><?=h($loc)?></option>
            <?php endforeach; ?>
        </select>
        <button class="crm-btn crm-btn-primary" type="submit">🔎 Search</button>
        <?php if($search!=='' || $location!==''): ?>
            <a class="crm-btn crm-btn-dark" href="customers.php">Reset</a>
        <?php endif; ?>
    </form>

    <div class="crm-table-card">
        <?php if (!$customers): ?>
            <div class="crm-empty">No customers found.</div>
        <?php else: ?>
        <table class="crm-table">
            <thead>
            <tr>
                <th>Customer</th>
                <th>Phone / WhatsApp</th>
                <th>Location</th>
                <th>Bookings</th>
                <th>Total Value</th>
                <th>Paid</th>
                <th>Balance</th>
                <th>Last Booking</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($customers as $c): ?>
                <tr>
                    <td>
                        <div class="crm-name"><?=h($c['full_name'])?></div>
                        <?php if(!empty($c['email'])): ?><div class="crm-muted"><?=h($c['email'])?></div><?php endif; ?>
                    </td>
                    <td>
                        <?php if(!empty($c['phone'])): ?><div><?=h($c['phone'])?></div><?php endif; ?>
                        <?php if(!empty($c['whatsapp'])): ?><a class="crm-wa" href="<?=h(wa_link($c['whatsapp']))?>" target="_blank">WhatsApp</a><?php endif; ?>
                    </td>
                    <td><?=h($c['location'] ?: '—')?></td>
                    <td><span class="crm-pill"><?=number_format((int)$c['booking_count'])?></span></td>
                    <td class="crm-money"><?=h(money($c['total_value']))?></td>
                    <td class="crm-money crm-green"><?=h(money($c['total_paid']))?></td>
                    <td class="crm-money crm-orange"><?=h(money($c['total_balance']))?></td>
                    <td><?=h($c['last_booking_date'] ?: '—')?></td>
                    <td><a class="crm-view" href="customer-view.php?id=<?=((int)$c['id'])?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
