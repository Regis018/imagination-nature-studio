
<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$pdo = db();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| LANGUAGE
|--------------------------------------------------------------------------
*/
if (isset($_GET['lang'])) {
    $selectedLanguage = strtolower(trim($_GET['lang']));

    if (in_array($selectedLanguage, ['en', 'rw'], true)) {
        $_SESSION['vip_language'] = $selectedLanguage;
    }
}

$lang = $_SESSION['vip_language'] ?? 'en';

function vip_redeem_t($english, $kinyarwanda) {
    global $lang;
    return $lang === 'rw' ? $kinyarwanda : $english;
}

$pageTitle = vip_redeem_t(
    'Redeem VIP Promotion',
    'Gukoresha Promotion ya VIP'
);

$notice = '';
$noticeType = 'info';
$promotion = null;

$code = strtoupper(trim(
    (string)($_POST['code'] ?? $_GET['code'] ?? '')
));

/*
|--------------------------------------------------------------------------
| CREATE PROMOTIONS TABLE
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

/*
|--------------------------------------------------------------------------
| REDEEM PROMOTION
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'redeem'
) {
    if (!verify_csrf($_POST['csrf'] ?? null)) {

        $notice = vip_redeem_t(
            'Session expired. Refresh the page and try again.',
            'Igihe cya session cyarangiye. Ongera ufungure page ugerageze.'
        );

    } elseif ($code === '') {

        $notice = vip_redeem_t(
            'Enter a promotion code.',
            'Andika code ya promotion.'
        );

    } else {

        try {
            $admin = current_admin();

            $staffId = isset($admin['id'])
                ? (int)$admin['id']
                : (int)($_SESSION['admin_id'] ?? 0);

            /*
             * Atomic update:
             * A code can only be redeemed once.
             */
            $stmt = $pdo->prepare("
                UPDATE vip_promotions
                SET status = 'redeemed',
                    redeemed_by = ?,
                    redeemed_at = NOW()
                WHERE code = ?
                  AND status = 'active'
                  AND (
                      expires_at IS NULL
                      OR expires_at >= NOW()
                  )
            ");

            $stmt->execute([$staffId, $code]);

            if ($stmt->rowCount() === 1) {

                $notice = vip_redeem_t(
                    'Promotion successfully redeemed. This code cannot be used again.',
                    'Promotion yakoreshejwe neza. Iyi code ntishobora kongera gukoreshwa.'
                );

            } else {

                $notice = vip_redeem_t(
                    'This code is invalid, expired, cancelled, or has already been used.',
                    'Iyi code siyo, yararengeje igihe, yarahagaritswe cyangwa yarakoreshejwe.'
                );
            }

        } catch (Throwable $e) {

            error_log('[VIP PROMOTION REDEEM] ' . $e->getMessage());

            $notice = vip_redeem_t(
                'Could not redeem this promotion. Please try again.',
                'Ntibyashobotse gukoresha iyi promotion. Ongera ugerageze.'
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| LOOK UP PROMOTION
|--------------------------------------------------------------------------
*/
if ($code !== '') {

    $stmt = $pdo->prepare("
        SELECT *
        FROM vip_promotions
        WHERE code = ?
        LIMIT 1
    ");

    $stmt->execute([$code]);

    $promotion = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

require __DIR__ . '/includes/admin-header.php';
?>

<style>
.vpr-wrap {
    max-width: 850px;
    margin: 0 auto;
    color: #f4eee7;
}

.vpr-card {
    background: #111923;
    border: 1px solid #344052;
    border-radius: 16px;
    padding: 22px;
    margin: 18px 0;
}

.vpr-card h1,
.vpr-card h2 {
    color: #fff;
}

.vpr-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.vpr-form input {
    flex: 1;
    min-width: 220px;
    padding: 13px;
    border-radius: 9px;
    border: 1px solid #465368;
    background: #0e1621;
    color: #fff;
}

.vpr-btn {
    border: 0;
    border-radius: 9px;
    padding: 12px 18px;
    background: #e58a36;
    color: #17110b;
    font-weight: 800;
    cursor: pointer;
}

.vpr-notice {
    padding: 13px;
    border-radius: 10px;
    background: #302315;
    border: 1px solid #815b2a;
    color: #f7dcae;
    margin: 15px 0;
}

.vpr-row {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 10px 0;
    border-bottom: 1px solid #293344;
}

.vpr-row:last-child {
    border-bottom: 0;
}

.vpr-status {
    font-weight: 800;
    text-transform: uppercase;
}

.vpr-status.active {
    color: #86efac;
}

.vpr-status.redeemed {
    color: #fca5a5;
}

.vpr-status.expired,
.vpr-status.cancelled {
    color: #fcd34d;
}

.vpr-language {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin: 12px 0;
}

.vpr-language a {
    padding: 8px 13px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 800;
    background: #293344;
    color: #fff;
}

.vpr-language a.selected {
    background: #e58a36;
    color: #17110b;
}

@media(max-width:520px) {
    .vpr-row {
        flex-direction: column;
        gap: 4px;
    }
}
</style>

<div class="vpr-wrap">

    <div class="vpr-language">
        <a
            class="<?= $lang === 'en' ? 'selected' : '' ?>"
            href="?lang=en<?= $code !== '' ? '&code=' . rawurlencode($code) : '' ?>"
        >🇬🇧 English</a>

        <a
            class="<?= $lang === 'rw' ? 'selected' : '' ?>"
            href="?lang=rw<?= $code !== '' ? '&code=' . rawurlencode($code) : '' ?>"
        >🇷🇼 Kinyarwanda</a>
    </div>

    <div class="vpr-card">

        <h1>
            🎁 <?= vip_redeem_t(
                'Redeem VIP Promotion',
                'Gukoresha Promotion ya VIP'
            ) ?>
        </h1>

        <p>
            <?= vip_redeem_t(
                'Enter the code shown by the client. Each promotion can only be redeemed once.',
                'Andika code umukiriya akweretse. Buri promotion yemerewe gukoreshwa inshuro imwe gusa.'
            ) ?>
        </p>

        <?php if ($notice): ?>
            <div class="vpr-notice">
                <?= e($notice) ?>
            </div>
        <?php endif; ?>

        <form method="post" class="vpr-form">

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="action"
                value="lookup"
            >

            <input
                type="hidden"
                name="lang"
                value="<?= e($lang) ?>"
            >

            <input
                name="code"
                value="<?= e($code) ?>"
                placeholder="<?= e(vip_redeem_t(
                    'Enter VIP promotion code',
                    'Andika code ya promotion'
                )) ?>"
                required
            >

            <button class="vpr-btn" type="submit">
                <?= vip_redeem_t('Check code', 'Reba code') ?>
            </button>

        </form>

    </div>

    <?php if ($promotion): ?>

        <div class="vpr-card">

            <h2>
                <?= vip_redeem_t(
                    'Promotion details',
                    'Amakuru ya Promotion'
                ) ?>
            </h2>

            <div class="vpr-row">
                <span><?= vip_redeem_t('Client', 'Umukiriya') ?></span>
                <strong><?= e($promotion['client_name']) ?></strong>
            </div>

            <div class="vpr-row">
                <span><?= vip_redeem_t('Phone', 'Telefone') ?></span>
                <strong><?= e($promotion['client_phone']) ?></strong>
            </div>

            <div class="vpr-row">
                <span><?= vip_redeem_t('Code', 'Code') ?></span>
                <strong><?= e($promotion['code']) ?></strong>
            </div>

            <div class="vpr-row">
                <span><?= vip_redeem_t('Discount', 'Igabanyirizwa') ?></span>
                <strong>
                    <?= e(number_format(
                        (float)$promotion['discount_percent'],
                        2
                    )) ?>%
                </strong>
            </div>

            <div class="vpr-row">
                <span><?= vip_redeem_t('Gift', 'Impano') ?></span>
                <strong>
                    <?= e(
                        $promotion['gift_description']
                        ?: vip_redeem_t('No gift specified', 'Nta mpano yagenwe')
                    ) ?>
                </strong>
            </div>

            <div class="vpr-row">
                <span><?= vip_redeem_t('Expires', 'Igihe irangirira') ?></span>
                <strong>
                    <?= $promotion['expires_at']
                        ? e(date(
                            'd M Y H:i',
                            strtotime($promotion['expires_at'])
                        ))
                        : vip_redeem_t('No expiry', 'Nta gihe ntarengwa')
                    ?>
                </strong>
            </div>

            <div class="vpr-row">
                <span><?= vip_redeem_t('Status', 'Imiterere') ?></span>

                <strong class="vpr-status <?= e($promotion['status']) ?>">
                    <?php
                    $statuses = [
                        'active' => ['Active', 'Iracyakora'],
                        'redeemed' => ['Redeemed', 'Yakoreshejwe'],
                        'expired' => ['Expired', 'Yararangiye'],
                        'cancelled' => ['Cancelled', 'Yahagaritswe']
                    ];

                    echo e($statuses[$promotion['status']][$lang === 'rw' ? 1 : 0]
                        ?? $promotion['status']);
                    ?>
                </strong>
            </div>

            <?php
            $isExpired = (
                $promotion['expires_at'] &&
                strtotime($promotion['expires_at']) < time()
            );

            $canRedeem = (
                $promotion['status'] === 'active' &&
                !$isExpired
            );
            ?>

            <?php if ($canRedeem): ?>

                <form
                    method="post"
                    onsubmit="return confirm(<?= htmlspecialchars(
                        json_encode(vip_redeem_t(
                            'Redeem this promotion now? It cannot be used again.',
                            'Urashaka gukoresha iyi promotion ubu? Ntizongera gukoreshwa.'
                        )),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>);"
                    style="margin-top:18px"
                >

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="redeem"
                    >

                    <input
                        type="hidden"
                        name="lang"
                        value="<?= e($lang) ?>"
                    >

                    <input
                        type="hidden"
                        name="code"
                        value="<?= e($promotion['code']) ?>"
                    >

                    <button class="vpr-btn" type="submit">
                        <?= vip_redeem_t(
                            'Confirm & Redeem Once',
                            'Emeza kandi ukoreshe rimwe'
                        ) ?>
                    </button>

                </form>

            <?php else: ?>

                <p>
                    <?= vip_redeem_t(
                        'This promotion cannot be redeemed.',
                        'Iyi promotion ntishobora gukoreshwa.'
                    ) ?>
                </p>

            <?php endif; ?>

        </div>

    <?php elseif ($code !== ''): ?>

        <div class="vpr-card">
            <?= vip_redeem_t(
                'No promotion was found for this code.',
                'Nta promotion yabonetse ijyanye n’iyi code.'
            ) ?>
        </div>

    <?php endif; ?>

</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
