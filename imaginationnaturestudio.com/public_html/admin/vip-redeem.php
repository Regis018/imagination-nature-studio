<?php
/**
 * Admin — VIP gift redemption.
 * Fixed gift product selected when creating the promotion.
 * Records gift at retail value, with zero payment and zero balance.
 * Deducts stock using FIFO in the same database transaction.
 */

require_once __DIR__ . '/../includes/functions.php';
require_login();

$pdo = db();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['lang'])) {
    $requestedLang = strtolower(trim((string)$_GET['lang']));
    if (in_array($requestedLang, ['en', 'rw'], true)) {
        $_SESSION['vip_redeem_language'] = $requestedLang;
    }
}

$lang = $_SESSION['vip_redeem_language'] ?? 'en';

function vip_redeem_t(string $en, string $rw): string {
    global $lang;
    return $lang === 'rw' ? $rw : $en;
}

$pageTitle = vip_redeem_t(
    'Redeem VIP Promotion',
    'Gukoresha Promotion ya VIP'
);

$notice = '';
$noticeType = 'info';
$promotion = null;

$code = strtoupper(trim((string)(
    $_POST['code'] ?? $_GET['code'] ?? ''
)));

/*
|--------------------------------------------------------------------------
| Ensure VIP promotion table and fixed gift columns exist
|--------------------------------------------------------------------------
*/

$pdo->exec("CREATE TABLE IF NOT EXISTS vip_promotions (
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
COLLATE=utf8mb4_unicode_ci");

$vipPromoColumns = $pdo
    ->query("SHOW COLUMNS FROM vip_promotions")
    ->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('gift_product_id', $vipPromoColumns, true)) {
    $pdo->exec("
        ALTER TABLE vip_promotions
        ADD COLUMN gift_product_id INT UNSIGNED NULL DEFAULT NULL
        AFTER gift_description
    ");
}

if (!in_array('gift_quantity', $vipPromoColumns, true)) {
    $pdo->exec("
        ALTER TABLE vip_promotions
        ADD COLUMN gift_quantity INT UNSIGNED NOT NULL DEFAULT 1
        AFTER gift_product_id
    ");
}

/*
|--------------------------------------------------------------------------
| FIFO stock deduction
|--------------------------------------------------------------------------
*/

function vip_redeem_stock_deduct(
    PDO $pdo,
    int $saleId,
    int $productId,
    int $qty,
    int $staffId
): void {

    $pstmt = $pdo->prepare("
        SELECT id, name, track_stock
        FROM products
        WHERE id = ? AND is_active = 1
        LIMIT 1
        FOR UPDATE
    ");

    $pstmt->execute([$productId]);
    $product = $pstmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        throw new RuntimeException(
            'The selected product is unavailable.'
        );
    }

    if ((int)$product['track_stock'] !== 1) {
        throw new RuntimeException(
            'Stock tracking is not enabled for this product.'
        );
    }

    $bstmt = $pdo->prepare("
        SELECT
            spi.id,
            spi.remaining_quantity,
            spi.unit_cost
        FROM stock_purchase_items spi
        INNER JOIN stock_purchases sp
            ON sp.id = spi.purchase_id
        WHERE spi.product_id = ?
          AND spi.remaining_quantity > 0
          AND sp.status = 'received'
        ORDER BY sp.purchase_date ASC, spi.id ASC
        FOR UPDATE
    ");

    $bstmt->execute([$productId]);
    $batches = $bstmt->fetchAll(PDO::FETCH_ASSOC);

    $available = 0;

    foreach ($batches as $batch) {
        $available += (int)$batch['remaining_quantity'];
    }

    if ($available < $qty) {
        throw new RuntimeException(
            'Stock idahagije kuri ' . $product['name'] .
            '. Ihari: ' . $available .
            ', isabwa: ' . $qty . '.'
        );
    }

    $left = $qty;
    $weightedCost = 0.0;

    $updateBatch = $pdo->prepare("
        UPDATE stock_purchase_items
        SET remaining_quantity = remaining_quantity - ?
        WHERE id = ?
          AND remaining_quantity >= ?
    ");

    $allocation = $pdo->prepare("
        INSERT INTO stock_sale_allocations
        (
            sale_id,
            product_id,
            purchase_item_id,
            quantity,
            unit_cost,
            unit_sale_price,
            cost_total,
            revenue_total,
            profit_total
        )
        VALUES (?, ?, ?, ?, ?, 0, ?, 0, ?)
    ");

    foreach ($batches as $batch) {

        if ($left <= 0) {
            break;
        }

        $take = min(
            $left,
            (int)$batch['remaining_quantity']
        );

        if ($take <= 0) {
            continue;
        }

        $unitCost = (float)$batch['unit_cost'];
        $costTotal = round($take * $unitCost, 2);

        $updateBatch->execute([
            $take,
            (int)$batch['id'],
            $take
        ]);

        if ($updateBatch->rowCount() !== 1) {
            throw new RuntimeException(
                'Stock yahindutse. Ongera ugerageze.'
            );
        }

        $allocation->execute([
            $saleId,
            $productId,
            (int)$batch['id'],
            $take,
            $unitCost,
            $costTotal,
            -$costTotal
        ]);

        $weightedCost += $costTotal;
        $left -= $take;
    }

    if ($left !== 0) {
        throw new RuntimeException(
            'Ntibyashobotse gukata stock yose isabwa.'
        );
    }

    $movement = $pdo->prepare("
        INSERT INTO stock_movements
        (
            product_id,
            movement_type,
            quantity_in,
            quantity_out,
            unit_cost,
            reference_type,
            reference_id,
            notes,
            created_by
        )
        VALUES (?, 'sale_out', 0, ?, ?, 'sale', ?, ?, ?)
    ");

    $movement->execute([
        $productId,
        $qty,
        round($weightedCost / $qty, 2),
        $saleId,
        'FIFO stock deduction for VIP gift sale #' . $saleId,
        $staffId > 0 ? $staffId : null
    ]);
}

/*
|--------------------------------------------------------------------------
| Redeem promotion
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'redeem'
) {

    if (!verify_csrf($_POST['csrf'] ?? null)) {

        $notice = vip_redeem_t(
            'Session expired. Refresh and try again.',
            'Igihe cya session cyarangiye. Ongera ufungure page.'
        );

    } elseif ($code === '') {

        $notice = vip_redeem_t(
            'Enter a valid promotion code.',
            'Andika code ya promotion yemewe.'
        );

    } else {

        try {

            $admin = current_admin();

            $staffId = isset($admin['id'])
                ? (int)$admin['id']
                : (int)($_SESSION['admin_id'] ?? 0);

            $pdo->beginTransaction();

            $promoStmt = $pdo->prepare("
                SELECT *
                FROM vip_promotions
                WHERE code = ?
                LIMIT 1
                FOR UPDATE
            ");

            $promoStmt->execute([$code]);

            $lockedPromo = $promoStmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$lockedPromo
                || $lockedPromo['status'] !== 'active'
                || (
                    $lockedPromo['expires_at']
                    && strtotime($lockedPromo['expires_at']) < time()
                )
            ) {
                throw new RuntimeException(
                    'This promotion is invalid, expired, cancelled, or already redeemed.'
                );
            }

            /*
             * The gift product and quantity are fixed
             * when the promotion is created.
             */
            $productId = (int)(
                $lockedPromo['gift_product_id'] ?? 0
            );

            $qty = (int)(
                $lockedPromo['gift_quantity'] ?? 0
            );

            if ($productId < 1 || $qty < 1) {
                throw new RuntimeException(
                    'This promotion has no fixed gift product. It may be an older promotion; create a new one with a gift product selected.'
                );
            }

            $productStmt = $pdo->prepare("
                SELECT id, name, price, track_stock
                FROM products
                WHERE id = ?
                  AND is_active = 1
                LIMIT 1
                FOR UPDATE
            ");

            $productStmt->execute([$productId]);
            $product = $productStmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new RuntimeException(
                    'Selected product was not found or is inactive.'
                );
            }

            if ((int)$product['track_stock'] !== 1) {
                throw new RuntimeException(
                    'Enable Track Stock for this product before giving it as a VIP gift.'
                );
            }

            $unitPrice = (float)$product['price'];

            if ($unitPrice < 0) {
                throw new RuntimeException(
                    'Product price is invalid.'
                );
            }

            $total = round($unitPrice * $qty, 2);

            /*
             * Check whether sales has the optional cost column.
             */
            $saleColumns = $pdo
                ->query("SHOW COLUMNS FROM sales")
                ->fetchAll(PDO::FETCH_COLUMN);

            $hasExternalCost = in_array(
                'external_supplier_unit_cost',
                $saleColumns,
                true
            );

            if ($hasExternalCost) {

                $saleInsert = $pdo->prepare("
                    INSERT INTO sales
                    (
                        sale_date,
                        customer_name,
                        customer_phone,
                        product_id,
                        product_name,
                        description,
                        quantity,
                        unit_price,
                        external_supplier_unit_cost,
                        total,
                        amount_paid,
                        balance,
                        payment_method,
                        staff_name,
                        notes
                    )
                    VALUES (
                        CURDATE(), ?, ?, ?, ?, ?, ?, ?,
                        NULL, ?, 0, 0, 'other', ?, ?
                    )
                ");

                $saleInsert->execute([
                    $lockedPromo['client_name'],
                    $lockedPromo['client_phone'],
                    $productId,
                    $product['name'],
                    'VIP gift — promotion ' . $code,
                    $qty,
                    $unitPrice,
                    $total,
                    (string)($admin['name'] ?? 'Staff'),
                    'VIP GIFT | Code: ' . $code .
                    ' | Gift value: ' .
                    number_format($total, 2) .
                    ' RWF | No customer payment; no receivable balance.'
                ]);

            } else {

                $saleInsert = $pdo->prepare("
                    INSERT INTO sales
                    (
                        sale_date,
                        customer_name,
                        customer_phone,
                        product_id,
                        product_name,
                        description,
                        quantity,
                        unit_price,
                        total,
                        amount_paid,
                        balance,
                        payment_method,
                        staff_name,
                        notes
                    )
                    VALUES (
                        CURDATE(), ?, ?, ?, ?, ?, ?, ?,
                        ?, 0, 0, 'other', ?, ?
                    )
                ");

                $saleInsert->execute([
                    $lockedPromo['client_name'],
                    $lockedPromo['client_phone'],
                    $productId,
                    $product['name'],
                    'VIP gift — promotion ' . $code,
                    $qty,
                    $unitPrice,
                    $total,
                    (string)($admin['name'] ?? 'Staff'),
                    'VIP GIFT | Code: ' . $code .
                    ' | Gift value: ' .
                    number_format($total, 2) .
                    ' RWF | No customer payment; no receivable balance.'
                ]);
            }

            $saleId = (int)$pdo->lastInsertId();

            /*
             * Deduct stock using FIFO.
             */
            vip_redeem_stock_deduct(
                $pdo,
                $saleId,
                $productId,
                $qty,
                $staffId
            );

            /*
             * Mark promotion as redeemed.
             */
            $updatePromo = $pdo->prepare("
                UPDATE vip_promotions
                SET
                    status = 'redeemed',
                    redeemed_by = ?,
                    redeemed_at = NOW()
                WHERE id = ?
                  AND status = 'active'
            ");

            $updatePromo->execute([
                $staffId,
                (int)$lockedPromo['id']
            ]);

            if ($updatePromo->rowCount() !== 1) {
                throw new RuntimeException(
                    'Promotion status could not be updated.'
                );
            }

            $pdo->commit();

            $noticeType = 'success';

            $notice = vip_redeem_t(
                'VIP gift recorded successfully. Stock deducted and promotion redeemed. No customer debt was created.',
                'Impano ya VIP yanditswe neza. Stock yagabanutse, promotion irafunze. Nta mwenda umukiriya yandikiwe.'
            );

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                '[VIP PROMOTION REDEEM] ' . $e->getMessage()
            );

            $notice = $e instanceof RuntimeException
                ? $e->getMessage()
                : vip_redeem_t(
                    'Could not complete the VIP gift. Check server error log.',
                    'Ntibyashobotse gutanga impano. Reba server error log.'
                );
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load promotion details
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

$giftProduct = null;

if ($promotion && !empty($promotion['gift_product_id'])) {

    $giftProductStmt = $pdo->prepare("
        SELECT
            id,
            name,
            category,
            price,
            track_stock,
            is_active
        FROM products
        WHERE id = ?
        LIMIT 1
    ");

    $giftProductStmt->execute([
        (int)$promotion['gift_product_id']
    ]);

    $giftProduct = $giftProductStmt->fetch(PDO::FETCH_ASSOC) ?: null;
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

.vpr-form input,
.vpr-form select {
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

.vpr-notice.success {
    background: #123222;
    border-color: #28764a;
    color: #bbf7d0;
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

.vpr-help {
    color: #bdc8d8;
    font-size: 13px;
    margin-top: 8px;
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
        >
            🇬🇧 English
        </a>

        <a
            class="<?= $lang === 'rw' ? 'selected' : '' ?>"
            href="?lang=rw<?= $code !== '' ? '&code=' . rawurlencode($code) : '' ?>"
        >
            🇷🇼 Kinyarwanda
        </a>

    </div>

    <div class="vpr-card">

        <h1>
            🎁 <?= e(vip_redeem_t(
                'Redeem VIP Promotion',
                'Gukoresha Promotion ya VIP'
            )) ?>
        </h1>

        <p>
            <?= e(vip_redeem_t(
                'Enter the code shown by the client. A promotion can only be used once.',
                'Andika code umukiriya akweretse. Promotion ikoreshwa rimwe gusa.'
            )) ?>
        </p>

        <?php if ($notice): ?>

            <div class="vpr-notice <?= e($noticeType) ?>">
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
                <?= e(vip_redeem_t(
                    'Check code',
                    'Reba code'
                )) ?>
            </button>

        </form>

    </div>

    <?php if ($promotion): ?>

        <div class="vpr-card">

            <h2>
                <?= e(vip_redeem_t(
                    'Promotion details',
                    'Amakuru ya Promotion'
                )) ?>
            </h2>

            <div class="vpr-row">
                <span><?= e(vip_redeem_t('Client', 'Umukiriya')) ?></span>
                <strong><?= e($promotion['client_name']) ?></strong>
            </div>

            <div class="vpr-row">
                <span><?= e(vip_redeem_t('Phone', 'Telefone')) ?></span>
                <strong><?= e($promotion['client_phone']) ?></strong>
            </div>

            <div class="vpr-row">
                <span>Code</span>
                <strong><?= e($promotion['code']) ?></strong>
            </div>

            <div class="vpr-row">
                <span><?= e(vip_redeem_t('Discount', 'Igabanyirizwa')) ?></span>
                <strong>
                    <?= e(number_format(
                        (float)$promotion['discount_percent'],
                        2
                    )) ?>%
                </strong>
            </div>

            <div class="vpr-row">
                <span><?= e(vip_redeem_t(
                    'Gift product',
                    'Product y’impano'
                )) ?></span>

                <strong>
                    <?= e(
                        $giftProduct['name']
                        ?? (
                            $promotion['gift_description']
                            ?: vip_redeem_t(
                                'No gift specified',
                                'Nta mpano yagenwe'
                            )
                        )
                    ) ?>
                </strong>
            </div>

            <div class="vpr-row">
                <span><?= e(vip_redeem_t(
                    'Gift quantity',
                    'Umubare w’impano'
                )) ?></span>

                <strong>
                    <?= (int)($promotion['gift_quantity'] ?? 1) ?>
                </strong>
            </div>

            <div class="vpr-row">
                <span><?= e(vip_redeem_t('Expires', 'Igihe irangirira')) ?></span>

                <strong>
                    <?= $promotion['expires_at']
                        ? e(date(
                            'd M Y H:i',
                            strtotime($promotion['expires_at'])
                        ))
                        : e(vip_redeem_t(
                            'No expiry',
                            'Nta gihe ntarengwa'
                        ))
                    ?>
                </strong>
            </div>

            <div class="vpr-row">
                <span><?= e(vip_redeem_t('Status', 'Imiterere')) ?></span>

                <strong class="vpr-status <?= e($promotion['status']) ?>">
                    <?= e($promotion['status']) ?>
                </strong>
            </div>

            <?php
            $isExpired = (
                $promotion['expires_at']
                && strtotime($promotion['expires_at']) < time()
            );

            $canRedeem = (
                $promotion['status'] === 'active'
                && !$isExpired
            );
            ?>

            <?php if ($canRedeem): ?>

                <?php if (
                    !$giftProduct
                    || (int)$giftProduct['is_active'] !== 1
                    || (int)$giftProduct['track_stock'] !== 1
                ): ?>

                    <p class="vpr-help">
                        <?= e(vip_redeem_t(
                            'The product assigned to this promotion is no longer active or stock tracking is disabled. Update the product before redeeming.',
                            'Product yari yagenewe iyi promotion ntikiri gukora cyangwa Track Stock yazimijwe. Banza ukosore product mbere yo gutanga impano.'
                        )) ?>
                    </p>

                <?php else: ?>

                    <h3>
                        <?= e(vip_redeem_t(
                            'Gift assigned to this promotion',
                            'Impano yagenewe iyi promotion'
                        )) ?>
                    </h3>

                    <p>
                        <strong><?= e($giftProduct['name']) ?></strong>
                        × <?= (int)$promotion['gift_quantity'] ?>
                    </p>

                    <p class="vpr-help">
                        <?= e(vip_redeem_t(
                            'The product and quantity were fixed when this promotion was created. You cannot change them here. Confirm to deduct FIFO stock and close this code.',
                            'Product n’umubare byatoranyijwe igihe hakorwaga promotion. Ntibishobora guhindurirwa hano. Emeza kugira ngo stock ikurweho kandi code ifungwe.'
                        )) ?>
                    </p>

                    <form
                        method="post"
                        style="margin-top:14px"
                        onsubmit="return confirm(<?= htmlspecialchars(
                            json_encode(vip_redeem_t(
                                'Give the assigned VIP gift? The exact product quantity will be deducted from stock and this code will be used permanently.',
                                'Urashaka gutanga iyi mpano yagenewe umukiriya? Umubare wateganyijwe urakurwa muri stock kandi code ikoreshwe burundu.'
                            )),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>);"
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

                        <button
                            class="vpr-btn"
                            type="submit"
                            style="margin-top:10px"
                        >
                            <?= e(vip_redeem_t(
                                'Confirm & Give Assigned Gift',
                                'Emeza utange impano yagenwe'
                            )) ?>
                        </button>

                    </form>

                <?php endif; ?>

            <?php else: ?>

                <p>
                    <?= e(vip_redeem_t(
                        'This promotion cannot be redeemed.',
                        'Iyi promotion ntishobora gukoreshwa.'
                    )) ?>
                </p>

            <?php endif; ?>

        </div>

    <?php elseif ($code !== ''): ?>

        <div class="vpr-card">
            <?= e(vip_redeem_t(
                'No promotion was found for this code.',
                'Nta promotion yabonetse ijyanye n’iyi code.'
            )) ?>
        </div>

    <?php endif; ?>

</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
