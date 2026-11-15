
<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin_only();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['lang'])) {
    $selectedLanguage = strtolower(trim((string)$_GET['lang']));
    if (in_array($selectedLanguage, ['en', 'rw'], true)) {
        $_SESSION['vip_language'] = $selectedLanguage;
    }
}

$lang = $_SESSION['vip_language'] ?? 'en';

function vip_clients_t($english, $kinyarwanda) {
    global $lang;
    return $lang === 'rw' ? $kinyarwanda : $english;
}

$pdo = db();
$pageTitle = vip_clients_t('VIP Clients Club', 'Itsinda ry’Abakiriya ba VIP');

/*
|--------------------------------------------------------------------------
| ONE-TIME VIP PROMOTIONS
|--------------------------------------------------------------------------
*/
$pdo->exec("
    CREATE TABLE IF NOT EXISTS vip_promotions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        client_name VARCHAR(190) NOT NULL,
        client_phone VARCHAR(40) NOT NULL,
        code VARCHAR(32) NOT NULL UNIQUE,
        discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
        gift_description VARCHAR(255) DEFAULT NULL,
        expires_at DATETIME DEFAULT NULL,
        status ENUM('active','redeemed','expired','cancelled')
            NOT NULL DEFAULT 'active',
        created_by INT UNSIGNED DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        redeemed_by INT UNSIGNED DEFAULT NULL,
        redeemed_at DATETIME DEFAULT NULL,
        INDEX idx_vip_promo_phone (client_phone),
        INDEX idx_vip_promo_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
      COLLATE=utf8mb4_unicode_ci
");

// Upgrade existing installations without deleting existing promotions.
$vipPromoColumns = $pdo->query("SHOW COLUMNS FROM vip_promotions")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('gift_product_id', $vipPromoColumns, true)) {
    $pdo->exec("ALTER TABLE vip_promotions ADD COLUMN gift_product_id INT UNSIGNED NULL DEFAULT NULL AFTER gift_description");
}
if (!in_array('gift_quantity', $vipPromoColumns, true)) {
    $pdo->exec("ALTER TABLE vip_promotions ADD COLUMN gift_quantity INT UNSIGNED NOT NULL DEFAULT 1 AFTER gift_product_id");
}

$giftProducts = $pdo->query("SELECT id, name, category, price FROM products WHERE is_active=1 AND track_stock=1 ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC);

$promotionNotice = '';
$createdPromotion = null;

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'create_vip_promotion'
) {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $promotionNotice =
            vip_clients_t('Your session expired. Please refresh the page and try again.', 'Igihe cya session cyarangiye. Ongera ufungure page ugerageze.');
    } else {
        $clientName = trim((string)($_POST['client_name'] ?? ''));
        $clientPhone = trim((string)($_POST['client_phone'] ?? ''));
        $discount = (float)($_POST['discount_percent'] ?? 0);
        $giftProductId = (int)($_POST['gift_product_id'] ?? 0);
        $giftQuantity = (int)($_POST['gift_quantity'] ?? 1);
        $gift = '';
        $validDays = (int)($_POST['valid_days'] ?? 30);

        if ($clientName === '' || $clientPhone === '') {
            $promotionNotice = vip_clients_t('Client name and phone number are required.', 'Izina rya client na telefone birakenewe.');
        } elseif ($discount < 0 || $discount > 100) {
            $promotionNotice = vip_clients_t('Discount must be between 0 and 100%.', 'Igabanyirizwa rigomba kuba hagati ya 0 na 100%.');
        } elseif ($discount <= 0 && $giftProductId < 1) {
            $promotionNotice = vip_clients_t('Choose a gift product or enter a discount.', 'Hitamo product y’impano cyangwa wandike igabanyirizwa.');
        } elseif ($giftProductId > 0 && ($giftQuantity < 1 || $giftQuantity > 1000)) {
            $promotionNotice = vip_clients_t('Gift quantity must be between 1 and 1000.', 'Umubare w’impano ugomba kuba hagati ya 1 na 1000.');
        } elseif ($validDays < 1 || $validDays > 365) {
            $promotionNotice = vip_clients_t('Validity must be between 1 and 365 days.', 'Igihe promotion imara kigomba kuba hagati y’umunsi 1 na 365.');
        } else {
            try {
                $giftProduct = null;
                if ($giftProductId > 0) {
                    $giftProductStmt = $pdo->prepare("SELECT id, name, track_stock FROM products WHERE id=? AND is_active=1 LIMIT 1");
                    $giftProductStmt->execute([$giftProductId]);
                    $giftProduct = $giftProductStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$giftProduct || (int)$giftProduct['track_stock'] !== 1) {
                        throw new RuntimeException(vip_clients_t('Choose an active product with Track Stock enabled.', 'Hitamo product ikora kandi ifite Track Stock yafunguye.'));
                    }
                    $gift = $giftProduct['name'];
                } else {
                    $giftQuantity = 1;
                }

                $code = 'INS-' . strtoupper(bin2hex(random_bytes(3)));

                $expiresAt = (new DateTimeImmutable('now'))
                    ->modify('+' . $validDays . ' days')
                    ->format('Y-m-d H:i:s');

                $currentAdmin = current_admin();
                $createdBy = isset($currentAdmin['id'])
                    ? (int)$currentAdmin['id']
                    : null;

                $insert = $pdo->prepare("
                    INSERT INTO vip_promotions
                        (
                            client_name,
                            client_phone,
                            code,
                            discount_percent,
                            gift_description,
                            gift_product_id,
                            gift_quantity,
                            expires_at,
                            created_by
                        )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $insert->execute([
                    $clientName,
                    $clientPhone,
                    $code,
                    $discount,
                    $gift !== '' ? $gift : null,
                    $giftProductId > 0 ? $giftProductId : null,
                    $giftQuantity,
                    $expiresAt,
                    $createdBy
                ]);

                header(
                    'Location: vip-clients.php?created=' .
                    rawurlencode($code)
                );
                exit;

            } catch (Throwable $e) {
                error_log('[VIP PROMOTION CREATE] ' . $e->getMessage());
                $promotionNotice =
                    vip_clients_t('Could not create the promotion. Please try again.', 'Ntibyashobotse gukora promotion. Ongera ugerageze.');
            }
        }
    }
}

if (!empty($_GET['created'])) {
    $findPromotion = $pdo->prepare("
        SELECT *
        FROM vip_promotions
        WHERE code = ?
        LIMIT 1
    ");
    $findPromotion->execute([trim((string)$_GET['created'])]);
    $createdPromotion = $findPromotion->fetch(PDO::FETCH_ASSOC) ?: null;
}

$search = trim((string)($_GET['q'] ?? ''));

function vip_phone_key($value): string {
    $digits = preg_replace('/\D+/', '', (string)$value);
    // Normalize Rwandan local numbers (07...) and international numbers (2507...).
    if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
        $digits = '25' . substr($digits, 1);
    }
    return $digits;
}

function vip_level(int $visits, float $spent): array {
    // A client qualifies by EITHER visits OR recorded spending.
    if ($visits >= 12 || $spent >= 1000000) {
        return ['VIP', vip_clients_t('15% + Special offers', '15% + Impano zihariye'), 'vip'];
    }

    if ($visits >= 8 || $spent >= 500000) {
        return ['Gold', '15%', 'gold'];
    }

    if ($visits >= 5 || $spent >= 250000) {
        return ['Silver', '10%', 'silver'];
    }

    if ($visits >= 3 || $spent >= 100000) {
        return ['Bronze', '5%', 'bronze'];
    }

    return ['Regular', vip_clients_t('No automatic discount yet', 'Nta gabanyirizwa riratangira'), 'regular'];
}

$clients = [];
$errorMessage = '';

try {
    // Registered customers
    $stmt = $pdo->query("
        SELECT id, full_name, phone, whatsapp, email, created_at
        FROM customers
        ORDER BY id DESC
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $phone = $c['phone'] ?: ($c['whatsapp'] ?? '');
        $key = vip_phone_key($phone);

        if ($key === '') {
            $key = 'customer-' . (int)$c['id'];
        }

        $clients[$key] = [
            'name' => $c['full_name'] ?: 'Unnamed client',
            'phone' => $phone,
            'email' => $c['email'] ?? '',
            'visits' => 0,
            'spent' => 0.0,
            'last_visit' => '',
        ];
    }

    // Online bookings: count completed bookings as visits.
    // Use bookings.amount_paid when recorded; otherwise fall back to successful payment rows.
    // The payment subquery prevents booking rows from multiplying payment totals.
    $bookingRows = $pdo->query("
        SELECT
            c.full_name,
            c.phone,
            c.whatsapp,
            COUNT(DISTINCT CASE
                WHEN b.booking_status = 'completed' THEN b.id
            END) AS visits,
            COALESCE(SUM(
                CASE
                    WHEN b.id IS NULL THEN 0
                    WHEN COALESCE(b.amount_paid, 0) > 0 THEN b.amount_paid
                    ELSE COALESCE(pay.successful_paid, 0)
                END
            ), 0) AS spent,
            MAX(CASE
                WHEN b.booking_status = 'completed' THEN b.booking_date
            END) AS last_visit
        FROM customers c
        LEFT JOIN bookings b ON b.customer_id = c.id
        LEFT JOIN (
            SELECT booking_id, SUM(amount) AS successful_paid
            FROM payments
            WHERE payment_status = 'successful'
            GROUP BY booking_id
        ) pay ON pay.booking_id = b.id
        GROUP BY c.id, c.full_name, c.phone, c.whatsapp
    " )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bookingRows as $r) {
        $phone = $r['phone'] ?: ($r['whatsapp'] ?? '');
        $key = vip_phone_key($phone);

        if ($key === '') {
            continue;
        }

        if (!isset($clients[$key])) {
            $clients[$key] = [
                'name' => $r['full_name'] ?: 'Unnamed client',
                'phone' => $phone,
                'email' => '',
                'visits' => 0,
                'spent' => 0.0,
                'last_visit' => ''
            ];
        }

        $clients[$key]['visits'] += (int)$r['visits'];
        $clients[$key]['spent'] += (float)$r['spent'];

        if (!empty($r['last_visit']) && $r['last_visit'] > $clients[$key]['last_visit']) {
            $clients[$key]['last_visit'] = $r['last_visit'];
        }
    }

    // Sales are also treated as studio visits.
    // sales.amount_paid is used to avoid double-counting sale_payments.
    $salesRows = $pdo->query("
        SELECT
            customer_name,
            customer_phone,
            COUNT(*) AS visits,
            COALESCE(SUM(amount_paid), 0) AS spent,
            MAX(sale_date) AS last_visit
        FROM sales
        WHERE customer_phone IS NOT NULL
          AND customer_phone <> ''
        GROUP BY customer_phone, customer_name
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($salesRows as $r) {
        $phone = (string)$r['customer_phone'];
        $key = vip_phone_key($phone);

        if ($key === '') {
            continue;
        }

        if (!isset($clients[$key])) {
            $clients[$key] = [
                'name' => $r['customer_name'] ?: 'Walk-in client',
                'phone' => $phone,
                'email' => '',
                'visits' => 0,
                'spent' => 0.0,
                'last_visit' => ''
            ];
        } elseif (
            $clients[$key]['name'] === 'Unnamed client' &&
            !empty($r['customer_name'])
        ) {
            $clients[$key]['name'] = $r['customer_name'];
        }

        $clients[$key]['visits'] += (int)$r['visits'];
        $clients[$key]['spent'] += (float)$r['spent'];

        if (
            !empty($r['last_visit']) &&
            $r['last_visit'] > $clients[$key]['last_visit']
        ) {
            $clients[$key]['last_visit'] = $r['last_visit'];
        }
    }

    // Walk-in bookings count as visits when confirmed OR completed.
    // Only advance is included as paid money because total_amount may still be unpaid.
    $walkinRows = $pdo->query("
        SELECT
            customer_name,
            customer_contact,
            COUNT(*) AS visits,
            COALESCE(SUM(advance), 0) AS spent,
            MAX(COALESCE(event_date, booking_date)) AS last_visit
        FROM walkin_bookings
        WHERE status IN ('confirmed', 'completed')
          AND customer_contact IS NOT NULL
          AND customer_contact <> ''
        GROUP BY customer_contact, customer_name
    " )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($walkinRows as $r) {
        $phone = (string)$r['customer_contact'];
        $key = vip_phone_key($phone);
        if ($key === '') {
            continue;
        }

        if (!isset($clients[$key])) {
            $clients[$key] = [
                'name' => $r['customer_name'] ?: 'Walk-in client',
                'phone' => $phone,
                'email' => '',
                'visits' => 0,
                'spent' => 0.0,
                'last_visit' => ''
            ];
        } elseif ($clients[$key]['name'] === 'Unnamed client' && !empty($r['customer_name'])) {
            $clients[$key]['name'] = $r['customer_name'];
        }

        $clients[$key]['visits'] += (int)$r['visits'];
        $clients[$key]['spent'] += (float)$r['spent'];
        if (!empty($r['last_visit']) && $r['last_visit'] > $clients[$key]['last_visit']) {
            $clients[$key]['last_visit'] = $r['last_visit'];
        }
    }

} catch (Throwable $e) {
    error_log('[VIP CLIENTS] ' . $e->getMessage());
    $errorMessage =
        vip_clients_t('Could not load VIP client information. Please check the database columns and try again.', 'Ntibyashobotse kubona amakuru y’abakiriya ba VIP. Reba imiterere ya database wongere ugerageze.');
}


// Calculate VIP levels
foreach ($clients as &$client) {
    [
        $client['level'],
        $client['reward'],
        $client['level_key']
    ] = vip_level(
        (int)$client['visits'],
        (float)$client['spent']
    );
}
unset($client);


// Search
if ($search !== '') {
    $needle = function_exists('mb_strtolower')
        ? mb_strtolower($search)
        : strtolower($search);

    $clients = array_filter($clients, static function($c) use ($needle) {
        $name = function_exists('mb_strtolower')
            ? mb_strtolower((string)$c['name'])
            : strtolower((string)$c['name']);

        $phone = strtolower((string)$c['phone']);
        $email = strtolower((string)$c['email']);

        return str_contains($name, $needle)
            || str_contains($phone, $needle)
            || str_contains($email, $needle);
    });
}


// Sort clients by VIP level and spending
uasort($clients, static function($a, $b) {
    $rank = [
        'VIP' => 4,
        'Gold' => 3,
        'Silver' => 2,
        'Bronze' => 1,
        'Regular' => 0
    ];

    return ($rank[$b['level']] <=> $rank[$a['level']])
        ?: ($b['spent'] <=> $a['spent']);
});

$totalClients = count($clients);

$vipCount = count(array_filter(
    $clients,
    static fn($c) => $c['level'] === 'VIP'
));

$goldPlus = count(array_filter(
    $clients,
    static fn($c) => in_array(
        $c['level'],
        ['VIP', 'Gold'],
        true
    )
));

$totalSpent = array_sum(array_column($clients, 'spent'));

require __DIR__ . '/includes/admin-header.php';
?>

<style>
.vip-wrap {
    max-width: 1400px;
    margin: 0 auto;
    color: #f4eee7;
}

.vip-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 22px;
}

.vip-heading h1 {
    margin: 0;
    font-size: clamp(24px, 3vw, 34px);
    color: #fff;
}

.vip-heading p {
    margin: 6px 0 0;
    color: #a9a19a;
}

.vip-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 22px;
}

.vip-stat {
    background: linear-gradient(145deg, #191f2a, #10151d);
    border: 1px solid #293344;
    border-radius: 16px;
    padding: 18px;
}

.vip-stat small {
    color: #aab4c4;
    display: block;
    margin-bottom: 8px;
}

.vip-stat strong {
    font-size: 25px;
    color: #fff;
}

.vip-toolbar {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    margin: 18px 0;
}

.vip-search {
    display: flex;
    gap: 8px;
    flex: 1;
    min-width: 240px;
}

.vip-search input {
    flex: 1;
    min-width: 0;
    background: #111923;
    border: 1px solid #344052;
    color: #fff;
    border-radius: 10px;
    padding: 12px;
}

.vip-btn {
    border: 0;
    border-radius: 10px;
    padding: 11px 16px;
    background: #e58a36;
    color: #17110b;
    font-weight: 800;
    text-decoration: none;
    cursor: pointer;
}

.vip-table-wrap {
    overflow: auto;
    border: 1px solid #293344;
    border-radius: 15px;
    background: #111720;
}

.vip-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

.vip-table th,
.vip-table td {
    text-align: left;
    padding: 13px 14px;
    border-bottom: 1px solid #27303d;
}

.vip-table th {
    color: #aeb9c9;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .07em;
    background: #171f2b;
}

.vip-table td {
    color: #eee;
    font-size: 14px;
}

.vip-table tr:last-child td {
    border-bottom: 0;
}

.vip-level {
    display: inline-block;
    border-radius: 99px;
    padding: 5px 10px;
    font-size: 11px;
    font-weight: 900;
}

.vip-level.vip {
    background: #f5d77c;
    color: #30220a;
}

.vip-level.gold {
    background: #d9a441;
    color: #241805;
}

.vip-level.silver {
    background: #c8d1dc;
    color: #202832;
}

.vip-level.bronze {
    background: #b7794c;
    color: #21130c;
}

.vip-level.regular {
    background: #303b4b;
    color: #d7e0ed;
}

.vip-muted {
    color: #a6afbd;
}

.vip-empty {
    text-align: center;
    padding: 34px;
    color: #abb4c0;
}

.vip-promo-details {
    margin-top: 8px;
}

.vip-promo-details summary {
    display: inline-block;
    cursor: pointer;
    color: #f4c27a;
    font-size: 12px;
    font-weight: 800;
}

.vip-promo-form {
    min-width: 230px;
    max-width: 300px;
    margin-top: 9px;
    padding: 12px;
    border: 1px solid #3b4658;
    border-radius: 10px;
    background: #182231;
}

.vip-promo-form label {
    display: block;
    margin: 7px 0 4px;
    color: #cbd5e1;
    font-size: 11px;
}

.vip-promo-form input {
    width: 100%;
    padding: 8px;
    border-radius: 7px;
    border: 1px solid #465368;
    background: #0e1621;
    color: #fff;
}

.vip-promo-form button {
    margin-top: 10px;
    width: 100%;
}

.vip-promo-success {
    padding: 16px;
    margin-bottom: 18px;
    border: 1px solid #397c58;
    border-radius: 12px;
    background: #14291f;
    color: #d9fbe5;
}

.vip-promo-success code {
    display: inline-block;
    padding: 5px 9px;
    margin: 5px 0;
    border-radius: 6px;
    background: #09170f;
    color: #fff;
    font-size: 18px;
    font-weight: 800;
}

.vip-promo-success a {
    display: inline-block;
    margin-top: 8px;
    color: #a7f3c2;
    font-weight: 800;
}

.vip-alert {
    padding: 13px 15px;
    border: 1px solid #815b2a;
    background: #302315;
    color: #f7dcae;
    border-radius: 12px;
    margin-bottom: 18px;
}

@media(max-width:900px) {
    .vip-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media(max-width:520px) {
    .vip-stats {
        grid-template-columns: 1fr 1fr;
    }

    .vip-stat {
        padding: 13px;
    }

    .vip-stat strong {
        font-size: 20px;
    }
}
</style>

<div class="vip-wrap">

    <div class="vip-heading">

        <div>
            <h1><?= vip_clients_t('⭐ VIP Clients Club', '⭐ Itsinda ry’Abakiriya ba VIP') ?></h1>
            <p>
                <?= vip_clients_t('Imagination Nature Studio · Client loyalty & rewards', 'Imagination Nature Studio · Ubudahemuka n’ibihembo ku bakiriya') ?>
            </p>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <a class="vip-btn" href="?lang=en<?= $search !== '' ? '&q=' . rawurlencode($search) : '' ?>" style="background:<?= $lang === 'en' ? '#e58a36' : '#344052' ?>;color:<?= $lang === 'en' ? '#17110b' : '#fff' ?>">🇬🇧 English</a>
            <a class="vip-btn" href="?lang=rw<?= $search !== '' ? '&q=' . rawurlencode($search) : '' ?>" style="background:<?= $lang === 'rw' ? '#e58a36' : '#344052' ?>;color:<?= $lang === 'rw' ? '#17110b' : '#fff' ?>">🇷🇼 Kinyarwanda</a>
            <a class="vip-btn" href="vip-redeem.php"
               style="background:#344052;color:#fff">
                🎁 <?= vip_clients_t('Redeem Promotion', 'Koresha Promotion') ?>
            </a>

            <a class="vip-btn" href="monthly-rewards.php">
                🏆 <?= vip_clients_t('Monthly Top Clients & Rewards', 'Abakiriya beza n’ibihembo by’ukwezi') ?>
            </a>
        </div>

    </div>

    <?php if ($errorMessage): ?>
        <div class="vip-alert">
            <?= e($errorMessage) ?>
            <?= vip_clients_t('If it continues, send the error details to your developer.', 'Niba bikomeje, ohereza amakuru y’ikosa ku muntu ugufasha mu ikoranabuhanga.') ?>
        </div>
    <?php endif; ?>

    <?php if ($promotionNotice): ?>
        <div class="vip-alert">
            <?= e($promotionNotice) ?>
        </div>
    <?php endif; ?>

    <?php if ($createdPromotion): ?>
        <?php
            $createdPhone = preg_replace(
                '/\D+/',
                '',
                (string)$createdPromotion['client_phone']
            );

            if (str_starts_with($createdPhone, '0')) {
                $createdPhone = '250' . substr($createdPhone, 1);
            }

            $promoBenefits = [];

            if ((float)$createdPromotion['discount_percent'] > 0) {
                $promoBenefits[] =
                    number_format((float)$createdPromotion['discount_percent'])
                    . vip_clients_t('% discount', '% igabanyirizwa');
            }

            if (!empty($createdPromotion['gift_description'])) {
                $promoBenefits[] =
                    vip_clients_t('Gift: ', 'Impano: ') . $createdPromotion['gift_description'];
            }

            if ($lang === 'rw') {
                $promoBenefitsRw = [];
                if ((float)$createdPromotion['discount_percent'] > 0) {
                    $promoBenefitsRw[] = number_format((float)$createdPromotion['discount_percent']) . '% igabanyirizwa';
                }
                if (!empty($createdPromotion['gift_description'])) {
                    $promoBenefitsRw[] = 'Impano: ' . $createdPromotion['gift_description'];
                }
                $promoMessage =
                    "🎉 TURAGUSHIMIYE! 🎁\n\n" .
                    "Mukiriya " . $createdPromotion['client_name'] . ",\n\n" .
                    "Imagination Nature Studio iragushimira ku bw’icyizere utugirira. Twaguteguriye impano yihariye. ❤️\n\n" .
                    "CODE YAWE: " . $createdPromotion['code'] . "\n" .
                    implode("\n", $promoBenefitsRw) . "\n\n" .
                    "Uzerekane ubu butumwa n’iyi code ku bakozi bacu uje muri studio. Iyi promotion izarangira ku wa " .
                    date('d M Y', strtotime($createdPromotion['expires_at'])) .
                    " kandi ikoreshwa inshuro IMWE gusa.\n\n" .
                    "Imagination Nature Studio – We Capture Your Beautiful Moments!";
            } else {
                $promoMessage =
                    "🎉 CONGRATULATIONS! 🎁\n\n" .
                    "Dear " . $createdPromotion['client_name'] . ",\n\n" .
                    "Imagination Nature Studio is happy to reward you with an exclusive offer as a thank-you for your support. ❤️\n\n" .
                    "YOUR PROMOTION CODE: " . $createdPromotion['code'] . "\n" .
                    implode("\n", $promoBenefits) . "\n\n" .
                    "Show this message and code to our staff when you visit the studio. This code is valid until " .
                    date('d M Y', strtotime($createdPromotion['expires_at'])) .
                    " and can only be used ONCE.\n\n" .
                    "Imagination Nature Studio – We Capture Your Beautiful Moments!";
            }

            $whatsappLink = $createdPhone
                ? 'https://wa.me/' . $createdPhone .
                    '?text=' . rawurlencode($promoMessage)
                : '';
        ?>

        <div class="vip-promo-success">
            <strong><?= vip_clients_t('Promotion created successfully!', 'Promotion yakozwe neza!') ?></strong><br>

            <?= vip_clients_t('Client:', 'Umukiriya:') ?> <?= e($createdPromotion['client_name']) ?><br>

            <?= vip_clients_t('Promotion code:', 'Code ya promotion:') ?>
            <code><?= e($createdPromotion['code']) ?></code><br>

            <?= vip_clients_t('Valid until:', 'Izageza igihe:') ?>
            <?= e(date(
                'd M Y',
                strtotime($createdPromotion['expires_at'])
            )) ?><br>

            <?php if ($whatsappLink): ?>
                <a href="<?= e($whatsappLink) ?>"
                   target="_blank"
                   rel="noopener">
                    <?= vip_clients_t('Open WhatsApp with the ready-to-send message →', 'Fungura WhatsApp wohereze ubutumwa bwateguwe →') ?>
                </a>
            <?php else: ?>
                <span>
                    <?= vip_clients_t('Client phone number is missing.', 'Nimero ya telefone y’umukiriya ntihari.') ?>
                    <?= vip_clients_t('Add a phone number to send via WhatsApp.', 'Ongeramo nimero ya telefone kugira ngo wohereze kuri WhatsApp.') ?>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="vip-stats">

        <div class="vip-stat">
            <small><?= vip_clients_t('Clients in this view', 'Abakiriya bagaragara') ?></small>
            <strong><?= number_format($totalClients) ?></strong>
        </div>

        <div class="vip-stat">
            <small><?= vip_clients_t('VIP Clients', 'Abakiriya ba VIP') ?></small>
            <strong><?= number_format($vipCount) ?></strong>
        </div>

        <div class="vip-stat">
            <small><?= vip_clients_t('Gold + VIP', 'Gold + VIP') ?></small>
            <strong><?= number_format($goldPlus) ?></strong>
        </div>

        <div class="vip-stat">
            <small><?= vip_clients_t('Recorded amount paid', 'Amafaranga yose yishyuwe') ?></small>
            <strong>
                <?= number_format($totalSpent) ?>
                <small style="display:inline">RWF</small>
            </strong>
        </div>

    </div>

    <div class="vip-toolbar">

        <form class="vip-search"
              method="get"
              action="vip-clients.php">

            <input type="hidden" name="lang" value="<?= e($lang) ?>">

            <input
                type="search"
                name="q"
                value="<?= e($search) ?>"
                placeholder="<?= vip_clients_t('Search name, phone or email...', 'Shakisha izina, telefone cyangwa email...') ?>"
            >

            <button class="vip-btn" type="submit">
                Search
            </button>

            <?php if ($search !== ''): ?>
                <a class="vip-btn"
                   href="vip-clients.php?lang=<?= e($lang) ?>"
                   style="background:#344052;color:#fff">
                    Clear
                </a>
            <?php endif; ?>

        </form>

    </div>

    <div class="vip-table-wrap">

        <table class="vip-table">

            <thead>
                <tr>
                    <th><?= vip_clients_t('Client', 'Umukiriya') ?></th>
                    <th><?= vip_clients_t('Phone / WhatsApp', 'Telefone / WhatsApp') ?></th>
                    <th><?= vip_clients_t('Visits', 'Inshuro yasuye') ?></th>
                    <th><?= vip_clients_t('Total paid', 'Amafaranga yishyuwe') ?></th>
                    <th><?= vip_clients_t('VIP level', 'Urwego rwa VIP') ?></th>
                    <th><?= vip_clients_t('Reward guide', 'Ibihembo') ?></th>
                    <th><?= vip_clients_t('Last visit', 'Aheruka gusura') ?></th>
                    <th><?= vip_clients_t('Contact / Promotion', 'Kuvugana / Promotion') ?></th>
                </tr>
            </thead>

            <tbody>

                <?php if (!$clients): ?>

                    <tr>
                        <td colspan="8" class="vip-empty">
                            <?= vip_clients_t('No clients found.', 'Nta bakiriya babonetse.') ?>
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($clients as $c):

                        $wa = preg_replace(
                            '/\D+/',
                            '',
                            (string)$c['phone']
                        );

                        if (str_starts_with($wa, '0')) {
                            $wa = '250' . substr($wa, 1);
                        }

                    ?>

                        <tr>

                            <td>
                                <strong>
                                    <a
                                        style="color:#f4c27a;text-decoration:none"
                                        href="vip-client-profile.php?phone=<?= rawurlencode((string)$c['phone']) ?>"
                                    >
                                        <?= e($c['name']) ?>
                                    </a>
                                </strong>

                                <?php if ($c['email']): ?>
                                    <br>
                                    <span class="vip-muted">
                                        <?= e($c['email']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= e($c['phone'] ?: '—') ?>
                            </td>

                            <td>
                                <?= number_format((int)$c['visits']) ?>
                            </td>

                            <td>
                                <strong>
                                    <?= number_format((float)$c['spent']) ?>
                                    RWF
                                </strong>
                            </td>

                            <td>
                                <span class="vip-level <?= e($c['level_key']) ?>">
                                    <?= e($c['level']) ?>
                                </span>
                            </td>

                            <td>
                                <?= e($c['reward']) ?>
                            </td>

                            <td>
                                <?= $c['last_visit']
                                    ? e(date(
                                        'd M Y',
                                        strtotime($c['last_visit'])
                                    ))
                                    : '—'
                                ?>
                            </td>

                            <td>

                                <?php if ($wa): ?>

                                    <a
                                        class="vip-btn"
                                        style="padding:7px 10px;font-size:12px"
                                        target="_blank"
                                        rel="noopener"
                                        href="https://wa.me/<?= e($wa) ?>"
                                    >
                                        WhatsApp
                                    </a>

                                    <details class="vip-promo-details">

                                        <summary>
                                            + <?= vip_clients_t('Create promotion', 'Kora promotion') ?>
                                        </summary>

                                        <form
                                            class="vip-promo-form"
                                            method="post"
                                        >
                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="create_vip_promotion"
                                            >

                                            <input
                                                type="hidden"
                                                name="client_name"
                                                value="<?= e($c['name']) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="client_phone"
                                                value="<?= e($c['phone']) ?>"
                                            >

                                            <label><?= vip_clients_t('Discount (%)', 'Igabanyirizwa (%)') ?></label>

                                            <input
                                                type="number"
                                                name="discount_percent"
                                                min="0"
                                                max="100"
                                                step="0.5"
                                                value="10"
                                            >

                                            <label><?= vip_clients_t('Gift product (optional)', 'Product y’impano (si ngombwa)') ?></label>

                                            <select name="gift_product_id">
                                                <option value="0"><?= e(vip_clients_t('No physical gift — discount only', 'Nta mpano y’igicuruzwa — igabanyirizwa gusa')) ?></option>
                                                <?php foreach ($giftProducts as $giftProductOption): ?>
                                                    <option value="<?= (int)$giftProductOption['id'] ?>">
                                                        <?= e(($giftProductOption['category'] ? $giftProductOption['category'] . ' — ' : '') . $giftProductOption['name']) ?>
                                                        (<?= e(number_format((float)$giftProductOption['price'])) ?> RWF)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <label><?= vip_clients_t('Gift quantity', 'Umubare w’impano') ?></label>
                                            <input type="number" name="gift_quantity" min="1" max="1000" value="1">

                                            <label><?= vip_clients_t('Valid for (days)', 'Igihe imara (iminsi)') ?></label>

                                            <input
                                                type="number"
                                                name="valid_days"
                                                min="1"
                                                max="365"
                                                value="30"
                                            >

                                            <button
                                                class="vip-btn"
                                                type="submit"
                                            >
                                                <?= vip_clients_t('Generate one-time code', 'Kora code ikoreshwa rimwe') ?>
                                            </button>

                                        </form>

                                    </details>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

    <p class="vip-muted" style="font-size:12px;margin-top:14px">
        <?= vip_clients_t('VIP qualification is based on either completed visits or', 'Kugira ngo umukiriya agere ku rwego rwa VIP harebwa inshuro yasuye cyangwa') ?>
        <?= vip_clients_t('recorded spending. Completed online bookings, confirmed/completed walk-ins and sales count as visits.', 'amafaranga yishyuye. Online booking zakozwe, walk-in zemejwe/zarangiye n’ibicuruzwa bigira uruhare mu mubare w’ingendo.') ?>
        <?= vip_clients_t('Booking payments use amount_paid or successful payments as a fallback; walk-ins use advance.', 'Amafaranga ya booking akoresha amount_paid cyangwa successful payments; walk-in ikoresha advance.') ?>
        <?= vip_clients_t('sale_payments twice. Discounts shown are reward guidelines;', 'sale_payments kabiri. Igabanyirizwa rigaragara ni umurongo ngenderwaho;') ?>
        <?= vip_clients_t('they are not automatically applied at checkout.', 'ntirihita rishyirwa kuri facture mu gihe cyo kwishyura.') ?>
    </p>

</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
