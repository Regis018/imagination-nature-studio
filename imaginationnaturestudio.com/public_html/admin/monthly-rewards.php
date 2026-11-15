<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin_only();

$pdo = db();
$pageTitle = 'Monthly Top Clients & Rewards';

function mr_phone_key($v): string {
    return preg_replace('/\D+/', '', (string)$v);
}

function mr_money($v): string {
    return number_format((float)$v) . ' RWF';
}

function mr_month_valid($m): bool {
    return is_string($m) &&
        preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) === 1;
}

function mr_whatsapp_number($value): string {
    $number = preg_replace('/\D+/', '', (string)$value);

    if ($number !== '' && str_starts_with($number, '0')) {
        $number = '250' . substr($number, 1);
    }

    return $number;
}

function mr_whatsapp_message(array $winner): string {
    $rank = (int)$winner['rank_position'];

    $monthLabel = date(
        'F Y',
        strtotime($winner['reward_month'] . '-01')
    );

    $reward = $winner['reward_type'] === 'discount'
        ? ((int)$winner['discount_percent'] . '% discount')
        : 'Special Offer';

    return "Hello " . $winner['client_name'] . ",\n\n" .
        "Congratulations! You are one of the Top Clients of Imagination Nature Studio for " . $monthLabel . ".\n\n" .
        "Your position: #" . $rank . "\n" .
        "Your reward: " . $reward . "\n" .
        "Reward code: " . $winner['reward_code'] . "\n" .
        "Valid until: " . date('d M Y', strtotime($winner['expires_at'])) . "\n\n" .
        "Present this code when booking or purchasing our services to redeem your reward.\n\n" .
        "Thank you for choosing Imagination Nature Studio!";
}


// CREATE REWARD TABLES

$pdo->exec("
CREATE TABLE IF NOT EXISTS monthly_rewards (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    reward_month CHAR(7) NOT NULL UNIQUE,
    finalized_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS monthly_reward_winners (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    reward_month CHAR(7) NOT NULL,
    phone_key VARCHAR(40) NOT NULL,
    client_name VARCHAR(190) NOT NULL,
    client_phone VARCHAR(60) NOT NULL,
    rank_position TINYINT UNSIGNED NOT NULL,
    amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0,
    visits INT UNSIGNED NOT NULL DEFAULT 0,
    reward_type ENUM('discount','special_offer') NOT NULL,
    discount_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    reward_code VARCHAR(40) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_month_phone (reward_month, phone_key),
    KEY idx_reward_code (reward_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$winnerColumns = $pdo->query("
    SHOW COLUMNS FROM monthly_reward_winners
")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('redeemed_at', $winnerColumns, true)) {
    $pdo->exec("
        ALTER TABLE monthly_reward_winners
        ADD COLUMN redeemed_at DATETIME NULL DEFAULT NULL
    ");
}


// SELECT MONTH

$month = (string)($_GET['month'] ?? date('Y-m'));

if (!mr_month_valid($month)) {
    $month = date('Y-m');
}

$start = $month . '-01 00:00:00';
$end = date('Y-m-d H:i:s', strtotime($start . ' +1 month'));

$startDate = substr($start, 0, 10);
$endDate = substr($end, 0, 10);

$error = '';
$success = '';
$leaderboard = [];
$finalized = null;
$winners = [];

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['mr_csrf'])) {
    $_SESSION['mr_csrf'] = bin2hex(random_bytes(24));
}


try {

    // LOAD CUSTOMERS

    $customerRows = $pdo->query("
        SELECT id, full_name, phone, whatsapp
        FROM customers
        ORDER BY id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $idToKey = [];
    $people = [];

    foreach ($customerRows as $c) {

        $phone = trim((string)($c['phone'] ?: $c['whatsapp']));
        $key = mr_phone_key($phone);

        if ($key === '') {
            continue;
        }

        if (!isset($people[$key])) {
            $people[$key] = [
                'name' => $c['full_name'] ?: 'Unnamed client',
                'phone' => $phone,
                'paid' => 0.0,
                'visits' => 0
            ];
        }

        $idToKey[(int)$c['id']] = $key;
    }


    // BOOKING PAYMENTS

    $paymentStmt = $pdo->prepare("
        SELECT
            b.customer_id,
            COALESCE(SUM(p.amount),0) AS paid
        FROM payments p
        INNER JOIN bookings b ON b.id = p.booking_id
        WHERE LOWER(TRIM(p.payment_status)) = 'successful'
          AND p.payment_date >= ?
          AND p.payment_date < ?
        GROUP BY b.customer_id
    ");

    $paymentStmt->execute([$start, $end]);

    foreach ($paymentStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {

        $key = $idToKey[(int)$r['customer_id']] ?? '';

        if ($key !== '' && isset($people[$key])) {
            $people[$key]['paid'] += (float)$r['paid'];
        }
    }


    // COMPLETED BOOKING VISITS

    $visitStmt = $pdo->prepare("
        SELECT
            b.customer_id,
            COUNT(DISTINCT b.id) AS visits
        FROM bookings b
        WHERE LOWER(TRIM(b.booking_status)) = 'completed'
          AND b.booking_date >= ?
          AND b.booking_date < ?
        GROUP BY b.customer_id
    ");

    $visitStmt->execute([$startDate, $endDate]);

    foreach ($visitStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {

        $key = $idToKey[(int)$r['customer_id']] ?? '';

        if ($key !== '' && isset($people[$key])) {
            $people[$key]['visits'] += (int)$r['visits'];
        }
    }


    // SALES

    $salesStmt = $pdo->prepare("
        SELECT
            customer_name,
            customer_phone,
            COALESCE(SUM(amount_paid),0) AS paid,
            COUNT(DISTINCT sale_date) AS visits
        FROM sales
        WHERE customer_phone IS NOT NULL
          AND customer_phone <> ''
          AND sale_date >= ?
          AND sale_date < ?
        GROUP BY customer_phone, customer_name
    ");

    $salesStmt->execute([$startDate, $endDate]);

    foreach ($salesStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {

        $key = mr_phone_key($r['customer_phone']);

        if ($key === '') {
            continue;
        }

        if (!isset($people[$key])) {
            $people[$key] = [
                'name' => $r['customer_name'] ?: 'Walk-in client',
                'phone' => $r['customer_phone'],
                'paid' => 0.0,
                'visits' => 0
            ];
        } elseif (
            $people[$key]['name'] === 'Unnamed client' &&
            $r['customer_name']
        ) {
            $people[$key]['name'] = $r['customer_name'];
        }

        $people[$key]['paid'] += (float)$r['paid'];
        $people[$key]['visits'] += (int)$r['visits'];
    }


    // LEADERBOARD

    foreach ($people as $key => $p) {

        if ($p['paid'] > 0) {
            $p['phone_key'] = $key;
            $leaderboard[] = $p;
        }
    }

    usort($leaderboard, static function($a, $b) {

        if (abs($b['paid'] - $a['paid']) > 0.009) {
            return $b['paid'] <=> $a['paid'];
        }

        if ($b['visits'] !== $a['visits']) {
            return $b['visits'] <=> $a['visits'];
        }

        return strcasecmp($a['name'], $b['name']);
    });


    // REDEEM REWARD

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        ($_POST['action'] ?? '') === 'redeem'
    ) {

        if (!hash_equals(
            $_SESSION['mr_csrf'],
            (string)($_POST['csrf'] ?? '')
        )) {
            throw new RuntimeException('Security check failed.');
        }

        $code = strtoupper(trim((string)($_POST['reward_code'] ?? '')));

        if ($code === '' || strlen($code) > 40) {
            throw new RuntimeException('Enter a valid reward code.');
        }

        $pdo->beginTransaction();

        $redeemStmt = $pdo->prepare("
            SELECT *
            FROM monthly_reward_winners
            WHERE reward_code = ?
            LIMIT 1
            FOR UPDATE
        ");

        $redeemStmt->execute([$code]);
        $rewardRow = $redeemStmt->fetch(PDO::FETCH_ASSOC);

        if (!$rewardRow) {
            $pdo->rollBack();
            throw new RuntimeException('Reward code was not found.');
        }

        if (!empty($rewardRow['redeemed_at'])) {
            $pdo->rollBack();
            throw new RuntimeException('This reward has already been redeemed.');
        }

        if (strtotime($rewardRow['expires_at']) < time()) {
            $pdo->rollBack();
            throw new RuntimeException('This reward has expired.');
        }

        $updateRedeem = $pdo->prepare("
            UPDATE monthly_reward_winners
            SET redeemed_at = NOW()
            WHERE id = ?
              AND redeemed_at IS NULL
        ");

        $updateRedeem->execute([(int)$rewardRow['id']]);

        if ($updateRedeem->rowCount() !== 1) {
            $pdo->rollBack();
            throw new RuntimeException('This reward has already been redeemed.');
        }

        $pdo->commit();

        $success = 'Reward redeemed successfully for ' .
            $rewardRow['client_name'] . '.';
    }


    // FINALIZE MONTHLY WINNERS

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        ($_POST['action'] ?? '') === 'finalize'
    ) {

        if (!hash_equals(
            $_SESSION['mr_csrf'],
            (string)($_POST['csrf'] ?? '')
        )) {
            throw new RuntimeException('Security check failed.');
        }

        if ((string)($_POST['month'] ?? '') !== $month) {
            throw new RuntimeException('Selected month changed. Refresh and try again.');
        }

        $check = $pdo->prepare("
            SELECT id FROM monthly_rewards WHERE reward_month = ?
        ");

        $check->execute([$month]);

        if ($check->fetchColumn()) {
            throw new RuntimeException('This month has already been finalized.');
        }

        if (!$leaderboard) {
            throw new RuntimeException('No clients with recorded payments for this month.');
        }

        $expiry = date(
            'Y-m-d H:i:s',
            strtotime(date('Y-m-d H:i:s') . ' +30 days')
        );

        $pdo->beginTransaction();

        $insMonth = $pdo->prepare("
            INSERT INTO monthly_rewards
            (reward_month, finalized_at, expires_at)
            VALUES (?, NOW(), ?)
        ");

        $insMonth->execute([$month, $expiry]);

        $insWinner = $pdo->prepare("
            INSERT INTO monthly_reward_winners
            (
                reward_month, phone_key, client_name, client_phone,
                rank_position, amount_paid, visits, reward_type,
                discount_percent, reward_code, expires_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach (array_slice($leaderboard, 0, 10) as $i => $p) {

            $rank = $i + 1;

            $type = $rank <= 3 ? 'discount' : 'special_offer';

            $discount = $rank === 1
                ? 15
                : ($rank === 2 ? 10 : ($rank === 3 ? 5 : 0));

            $code = 'INS-' .
                str_replace('-', '', $month) .
                '-' . $rank . '-' .
                strtoupper(bin2hex(random_bytes(3)));

            $insWinner->execute([
                $month,
                $p['phone_key'],
                $p['name'],
                $p['phone'],
                $rank,
                $p['paid'],
                $p['visits'],
                $type,
                $discount,
                $code,
                $expiry
            ]);
        }

        $pdo->commit();

        $success = 'Monthly winners saved successfully.';
    }


    // LOAD SAVED WINNERS

    $monthStmt = $pdo->prepare("
        SELECT * FROM monthly_rewards WHERE reward_month = ?
    ");

    $monthStmt->execute([$month]);
    $finalized = $monthStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($finalized) {

        $winnerStmt = $pdo->prepare("
            SELECT *
            FROM monthly_reward_winners
            WHERE reward_month = ?
            ORDER BY rank_position ASC
        ");

        $winnerStmt->execute([$month]);
        $winners = $winnerStmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[MONTHLY REWARDS] ' . $e->getMessage());
    $error = $e->getMessage();
}


require __DIR__ . '/includes/admin-header.php';
?>

<style>
.mr-wrap{max-width:1350px;margin:0 auto;color:#f4eee7}
.mr-head{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;margin-bottom:20px}
.mr-head h1{margin:0;color:#fff;font-size:30px}
.mr-sub{color:#aab4c4;margin-top:6px}
.mr-panel{background:#121a25;border:1px solid #2a3545;border-radius:15px;padding:18px;margin-bottom:18px}
.mr-toolbar{display:flex;gap:10px;align-items:end;flex-wrap:wrap}
.mr-toolbar label{display:block;color:#b9c5d5;font-size:13px;margin-bottom:6px}
.mr-toolbar input{background:#0e1621;color:#fff;border:1px solid #354357;border-radius:9px;padding:10px}
.mr-btn{display:inline-block;border:0;border-radius:9px;padding:11px 15px;background:#e58a36;color:#17110b;font-weight:800;text-decoration:none;cursor:pointer}
.mr-btn:disabled{opacity:.5;cursor:not-allowed}
.mr-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:16px 0}
.mr-stat{background:#1a2432;border-radius:12px;padding:14px}
.mr-stat small{display:block;color:#aab4c4}
.mr-stat strong{font-size:22px;color:#fff}
.mr-table-wrap{overflow:auto;border:1px solid #2a3545;border-radius:12px}
.mr-table{width:100%;border-collapse:collapse;min-width:750px}
.mr-table th,.mr-table td{padding:12px 14px;border-bottom:1px solid #293344;text-align:left}
.mr-table th{background:#1a2432;color:#b9c5d5;font-size:12px;text-transform:uppercase}
.mr-table td{color:#f1f1f1}
.mr-rank{font-weight:900;color:#f5c77e}
.mr-muted{color:#aab4c4}
.mr-alert{padding:13px 15px;border-radius:10px;margin-bottom:15px;background:#302315;border:1px solid #805a2c;color:#f5d9aa}
.mr-success{background:#142c23;border-color:#286447;color:#c5f0d7}
.mr-pill{display:inline-block;border-radius:20px;padding:5px 9px;background:#29384b;color:#e9edf4;font-size:12px}
.mr-winner{background:#183326}
.mr-note{font-size:12px;color:#aab4c4;line-height:1.6}
@media(max-width:700px){.mr-stats{grid-template-columns:1fr}.mr-head h1{font-size:24px}}
</style>

<div class="mr-wrap">

    <div class="mr-head">
        <div>
            <h1>🏆 Monthly Top Clients & Rewards</h1>
            <div class="mr-sub">VIP Clients Club · Monthly client leaderboard</div>
        </div>

        <a class="mr-btn" href="vip-clients.php"
           style="background:#344052;color:#fff">
            ← VIP Clients Club
        </a>
    </div>

    <?php if ($error): ?>
        <div class="mr-alert">
            <strong>Notice:</strong> <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="mr-alert mr-success"><?= e($success) ?></div>
    <?php endif; ?>


    <!-- MONTH SELECTOR -->

    <section class="mr-panel">

        <form method="get" class="mr-toolbar">
            <div>
                <label for="month">Choose month</label>
                <input id="month" type="month"
                       name="month" value="<?= e($month) ?>">
            </div>

            <button class="mr-btn" type="submit">View month</button>
        </form>

        <div class="mr-stats">

            <div class="mr-stat">
                <small>Clients with payments</small>
                <strong><?= number_format(count($leaderboard)) ?></strong>
            </div>

            <div class="mr-stat">
                <small>Total paid this month</small>
                <strong>
                    <?= mr_money(array_sum(array_column($leaderboard, 'paid'))) ?>
                </strong>
            </div>

            <div class="mr-stat">
                <small>Rewards status</small>
                <strong style="font-size:17px">
                    <?= $finalized ? 'Finalized' : 'Not finalized' ?>
                </strong>
            </div>

        </div>

        <p class="mr-note">
            Ranking is based on payments recorded during the selected month.
            Completed bookings and sales dates count as visits; visits are
            used only to break payment ties.
        </p>

    </section>


    <!-- LEADERBOARD -->

    <section class="mr-panel">

        <h2 style="margin:0 0 14px;color:#fff">
            Leaderboard — <?= e(date('F Y', strtotime($month . '-01'))) ?>
        </h2>

        <div class="mr-table-wrap">
            <table class="mr-table">

                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Client</th>
                        <th>Phone</th>
                        <th>Visits</th>
                        <th>Paid this month</th>
                        <th>Reward if finalized</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (!$leaderboard): ?>

                    <tr>
                        <td colspan="6" class="mr-muted">
                            No recorded payments for this month.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($leaderboard as $i => $p):

                        $rank = $i + 1;

                        $reward = $rank === 1
                            ? '15% discount'
                            : ($rank === 2
                                ? '10% discount'
                                : ($rank === 3
                                    ? '5% discount'
                                    : ($rank <= 10
                                        ? 'Special offer'
                                        : '—')));

                    ?>

                        <tr class="<?= $rank <= 3 ? 'mr-winner' : '' ?>">
                            <td class="mr-rank">#<?= $rank ?></td>
                            <td><strong><?= e($p['name']) ?></strong></td>
                            <td><?= e($p['phone']) ?></td>
                            <td><?= number_format($p['visits']) ?></td>
                            <td><strong><?= mr_money($p['paid']) ?></strong></td>
                            <td><?= e($reward) ?></td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>
            </table>
        </div>

        <?php if (!$finalized): ?>

            <form method="post" style="margin-top:16px"
                  onsubmit="return confirm('Finalize and save the top 10 rewards for this month?');">

                <input type="hidden" name="csrf"
                       value="<?= e($_SESSION['mr_csrf']) ?>">

                <input type="hidden" name="month" value="<?= e($month) ?>">
                <input type="hidden" name="action" value="finalize">

                <button class="mr-btn" type="submit"
                        <?= !$leaderboard ? 'disabled' : '' ?>>
                    Finalize Monthly Winners
                </button>
            </form>

        <?php else: ?>

            <div class="mr-alert mr-success" style="margin-top:15px">
                This month's winners have been saved.
                Finalized on <?= e($finalized['finalized_at']) ?>.
                Rewards expire on <?= e($finalized['expires_at']) ?>.
            </div>

        <?php endif; ?>

    </section>


    <!-- REDEEM REWARD -->

    <section class="mr-panel">

        <h2 style="margin:0 0 12px;color:#fff">
            🎟️ Redeem a Client Reward
        </h2>

        <p class="mr-note">
            Enter the code shown by the client. The system checks that it
            exists, has not expired and has not been used before.
        </p>

        <form method="post" class="mr-toolbar"
              onsubmit="return confirm('Confirm that you are applying this reward now?');">

            <input type="hidden" name="csrf"
                   value="<?= e($_SESSION['mr_csrf']) ?>">

            <input type="hidden" name="action" value="redeem">

            <div>
                <label for="reward_code">Reward code</label>
                <input id="reward_code" name="reward_code"
                       type="text" maxlength="40" required
                       placeholder="INS-202611-1-ABC123"
                       autocomplete="off">
            </div>

            <button class="mr-btn" type="submit">
                Verify & Redeem Reward
            </button>

        </form>

    </section>


    <!-- SAVED WINNERS -->

    <?php if ($finalized): ?>

        <section class="mr-panel">

            <h2 style="margin:0 0 14px;color:#fff">
                Saved monthly winners & reward codes
            </h2>

            <div class="mr-table-wrap">

                <table class="mr-table">

                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Client</th>
                            <th>Reward</th>
                            <th>Code</th>
                            <th>Paid</th>
                            <th>Expires</th>
                            <th>Redemption</th>
                            <th>WhatsApp</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($winners as $w):

                        $waNumber = mr_whatsapp_number($w['client_phone']);
                        $waMessage = mr_whatsapp_message($w);

                        // Base64 protects the complete message in HTML attributes.
                        $waMessageBase64 = base64_encode($waMessage);

                    ?>

                        <tr>

                            <td class="mr-rank">
                                #<?= (int)$w['rank_position'] ?>
                            </td>

                            <td>
                                <strong><?= e($w['client_name']) ?></strong>
                                <br>
                                <span class="mr-muted">
                                    <?= e($w['client_phone']) ?>
                                </span>
                            </td>

                            <td>
                                <?= $w['reward_type'] === 'discount'
                                    ? (int)$w['discount_percent'] . '% discount'
                                    : 'Special offer'
                                ?>
                            </td>

                            <td>
                                <code><?= e($w['reward_code']) ?></code>
                            </td>

                            <td><?= mr_money($w['amount_paid']) ?></td>

                            <td><?= e($w['expires_at']) ?></td>

                            <td>
                                <?php if (!empty($w['redeemed_at'])): ?>
                                    <span class="mr-pill">
                                        Redeemed: <?= e($w['redeemed_at']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="mr-pill">Unused</span>
                                <?php endif; ?>
                            </td>

                            <td>

                                <?php if ($waNumber !== ''): ?>

                                    <button
                                        type="button"
                                        class="mr-btn mr-whatsapp-send"
                                        style="padding:7px 10px;font-size:12px;background:#25D366;color:#062b17"
                                        data-phone="<?= e($waNumber) ?>"
                                        data-message-b64="<?= e($waMessageBase64) ?>"
                                    >
                                        Send WhatsApp
                                    </button>

                                <?php else: ?>

                                    <span class="mr-muted">No phone</span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>
                </table>

            </div>

            <p class="mr-note">
                Click Send WhatsApp to open a pre-filled message.
                Review it and press Send in WhatsApp.
                Messages are not sent automatically.
                Staff must apply the discount manually to the purchase.
            </p>

        </section>

    <?php endif; ?>

</div>


<!-- WHATSAPP MESSAGE HANDLER -->

<script>
(function () {

    document.addEventListener('click', function (event) {

        const button = event.target.closest('.mr-whatsapp-send');

        if (!button) {
            return;
        }

        const phone = (button.dataset.phone || '')
            .replace(/\D/g, '');

        const encodedMessage = button.dataset.messageB64 || '';

        let message = '';

        // Decode Base64 safely, including emoji and Kinyarwanda characters.
        try {

            const binary = atob(encodedMessage);

            const bytes = Uint8Array.from(
                binary,
                character => character.charCodeAt(0)
            );

            message = new TextDecoder('utf-8').decode(bytes);

        } catch (error) {

            alert('The message could not be decoded. Please refresh the page.');
            return;
        }


        if (!/^250\d{9}$/.test(phone)) {

            alert(
                'Invalid Rwanda phone number: ' + phone +
                '. Please correct the customer phone number.'
            );

            return;
        }


        if (!message.trim()) {

            alert('The WhatsApp message is empty.');
            return;
        }


        // Build the WhatsApp message URL.
        const whatsappUrl =
            'https://web.whatsapp.com/send?phone=' +
            encodeURIComponent(phone) +
            '&text=' +
            encodeURIComponent(message);


        // Open after the user's direct click.
        const newWindow = window.open(whatsappUrl, '_blank');

        if (!newWindow) {
            alert('Your browser blocked the WhatsApp window. Allow pop-ups and try again.');
        }

    });

})();
</script>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
