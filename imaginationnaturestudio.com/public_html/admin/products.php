<?php
/**
 * Admin — Products (price list).
 * Staff: read-only. Admin: full CRUD.
 */
require_once __DIR__ . '/../includes/ops.php';
require_admin();

$pdo      = db();
$canEdit  = is_admin();

/* =====================================================
   EDIT MODE
   ===================================================== */

$editId = $canEdit ? (int)($_GET['edit'] ?? 0) : 0;
$editProduct = null;

if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id=? LIMIT 1");
    $stmt->execute([$editId]);
    $editProduct = $stmt->fetch();

    if (!$editProduct) {
        flash('err', 'Product not found.');
        redirect('products.php');
    }
}

/* =====================================================
   POST actions — DELETE / CREATE / UPDATE
   ===================================================== */

if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {

    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    /* ---------- DELETE ---------- */

    if ($action === 'delete' && $id) {

        @error_log(
            '[products-delete] id=' . $id . "\n",
            3,
            ERROR_LOG_FILE
        );

        $s = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE product_id=?");
        $s->execute([$id]);
        $used = (int)$s->fetchColumn();

        if ($used > 0) {

            flash(
                'err',
                "Cannot delete — {$used} sale(s) reference this product. Mark inactive instead."
            );

        } else {

            try {

                $stmt = $pdo->prepare(
                    "DELETE FROM products WHERE id=?"
                );

                $stmt->execute([$id]);

                flash(
                    'ok',
                    'Product deleted (' . $stmt->rowCount() . ' row).'
                );

            } catch (Throwable $e) {

                flash(
                    'err',
                    'Delete failed: ' . $e->getMessage()
                );
            }
        }

        redirect('products.php');
    }

    /* ---------- CREATE ---------- */

    if ($action === 'create') {

        $name = trim(
            $_POST['name'] ?? ''
        );

        $category = preg_replace(
            '/[^a-z_]/',
            '',
            strtolower(
                $_POST['category'] ?? 'other'
            )
        );

        $price = (float)(
            $_POST['price'] ?? 0
        );

        $active = !empty($_POST['is_active']) ? 1 : 0;
        $trackStock = !empty($_POST['track_stock']) ? 1 : 0;
        $stockUnit = trim($_POST['stock_unit'] ?? 'unit');
        $purchaseUnit = trim($_POST['purchase_unit'] ?? 'unit');
        $unitsPerPurchase = max(1, (int)($_POST['units_per_purchase'] ?? 1));
        $reorderLevel = max(0, (int)($_POST['reorder_level'] ?? 0));

        if ($name === '') {

            flash(
                'err',
                'Name is required.'
            );

            redirect('products.php');
        }

        $pdo->prepare(
            "INSERT INTO products
             (name, category, price, is_active, track_stock, reorder_level,
              stock_unit, purchase_unit, units_per_purchase)
             VALUES (?,?,?,?,?,?,?,?,?)"
        )->execute([
            $name, $category, $price, $active, $trackStock, $reorderLevel,
            $stockUnit, $purchaseUnit, $unitsPerPurchase
        ]);

        flash(
            'ok',
            'Product added.'
        );

        redirect('products.php');
    }

    /* ---------- UPDATE ---------- */

    if ($action === 'update' && $id) {

        $name = trim(
            $_POST['name'] ?? ''
        );

        $category = preg_replace(
            '/[^a-z_]/',
            '',
            strtolower(
                $_POST['category'] ?? 'other'
            )
        );

        $price = (float)(
            $_POST['price'] ?? 0
        );

        $active = !empty($_POST['is_active']) ? 1 : 0;
        $trackStock = !empty($_POST['track_stock']) ? 1 : 0;
        $stockUnit = trim($_POST['stock_unit'] ?? 'unit');
        $purchaseUnit = trim($_POST['purchase_unit'] ?? 'unit');
        $unitsPerPurchase = max(1, (int)($_POST['units_per_purchase'] ?? 1));
        $reorderLevel = max(0, (int)($_POST['reorder_level'] ?? 0));

        if ($name === '') {

            flash(
                'err',
                'Name is required.'
            );

            redirect(
                'products.php?edit=' . $id
            );
        }

        try {

            $stmt = $pdo->prepare(
                "UPDATE products
                 SET name=?, category=?, price=?, is_active=?,
                     track_stock=?, reorder_level=?, stock_unit=?,
                     purchase_unit=?, units_per_purchase=?
                 WHERE id=?"
            );

            $stmt->execute([
                $name, $category, $price, $active, $trackStock,
                $reorderLevel, $stockUnit, $purchaseUnit, $unitsPerPurchase, $id
            ]);

            flash(
                'ok',
                'Product updated successfully.'
            );

        } catch (Throwable $e) {

            flash(
                'err',
                'Update failed: ' . $e->getMessage()
            );

            redirect(
                'products.php?edit=' . $id
            );
        }

        redirect('products.php');
    }
}

/* =====================================================
   LOAD PRODUCTS
   ===================================================== */

$rows = $pdo->query(
    "SELECT *
     FROM products
     ORDER BY category, name"
)->fetchAll();

$cats = ops_product_categories();

require __DIR__ . '/includes/admin-header.php';
?>


<!-- =====================================================
     PAGE HEADER
     ===================================================== -->

<div
    style="
        display:flex;
        justify-content:space-between;
        align-items:flex-start;
        flex-wrap:wrap;
        gap:1rem;
        margin-bottom:1rem
    "
>

    <div>

        <h1 style="margin:0 0 .3rem">
            Products
        </h1>

        <p style="color:#7a716a;margin:0">

            Price list for the sale counter —
            photos, passport, cadre, banner, etc.

            <?php if (!$canEdit): ?>

                <br>

                <span
                    style="
                        color:var(--accent-2);
                        font-size:.85rem
                    "
                >
                    View only
                </span>

            <?php endif; ?>

        </p>

    </div>


    <div
        style="
            display:flex;
            gap:.6rem
        "
    >

        <a
            class="btn btn-sm btn-ghost"
            href="sales.php"
        >
            Sales →
        </a>

        <a
            class="btn btn-sm btn-ghost"
            href="daily-report.php"
        >
            Daily Report →
        </a>

    </div>

</div>


<!-- =====================================================
     FLASH MESSAGES
     ===================================================== -->

<?php if ($m = flash('ok')): ?>

    <div class="alert alert-success">
        <?= e($m) ?>
    </div>

<?php endif; ?>


<?php if ($m = flash('err')): ?>

    <div class="alert alert-error">
        <?= e($m) ?>
    </div>

<?php endif; ?>


<!-- =====================================================
     ADD / EDIT PRODUCT
     ===================================================== -->

<?php if ($canEdit): ?>

<form
    method="post"
    class="card"
    style="
        padding:1.4rem;
        margin-bottom:1.6rem
    "
>

    <?= csrf_field() ?>


    <?php if ($editProduct): ?>

        <input
            type="hidden"
            name="action"
            value="update"
        >

        <input
            type="hidden"
            name="id"
            value="<?= (int)$editProduct['id'] ?>"
        >

        <h3
            style="
                font-family:var(--font-serif);
                font-size:1.05rem;
                margin:0 0 1rem
            "
        >
            Edit Product
        </h3>

    <?php else: ?>

        <input
            type="hidden"
            name="action"
            value="create"
        >

        <h3
            style="
                font-family:var(--font-serif);
                font-size:1.05rem;
                margin:0 0 1rem
            "
        >
            Add product
        </h3>

    <?php endif; ?>


    <div class="form-grid">


        <!-- NAME -->

        <div>

            <label>
                Name *
            </label>

            <input
                type="text"
                name="name"
                required
                maxlength="150"
                placeholder="e.g. Cadre A2"
                value="<?= e($editProduct['name'] ?? '') ?>"
            >

        </div>


        <!-- CATEGORY -->

        <div>

            <label>
                Category
            </label>

            <select name="category">

                <?php foreach ($cats as $k => $label): ?>

                    <option
                        value="<?= e($k) ?>"
                        <?= (
                            ($editProduct['category'] ?? '') === $k
                        ) ? 'selected' : '' ?>
                    >
                        <?= e($label) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- PRICE -->

        <div>

            <label>
                Price (RWF)
            </label>

            <input
                type="number"
                name="price"
                min="0"
                step="100"
                value="<?= e($editProduct['price'] ?? '0') ?>"
            >

        </div>


        <!-- STOCK SETTINGS -->
        <div>
            <label>Stock unit (the unit Sales deducts)</label>
            <input type="text" name="stock_unit" maxlength="30"
                   value="<?= e($editProduct['stock_unit'] ?? 'unit') ?>"
                   placeholder="e.g. sheet">
        </div>
        <div>
            <label>Purchase unit</label>
            <input type="text" name="purchase_unit" maxlength="30"
                   value="<?= e($editProduct['purchase_unit'] ?? 'unit') ?>"
                   placeholder="e.g. packet">
        </div>
        <div>
            <label>Stock units per purchase unit</label>
            <input type="number" name="units_per_purchase" min="1" step="1"
                   value="<?= e($editProduct['units_per_purchase'] ?? '1') ?>">
            <small>Example: 1 packet contains 36 sheets → enter 36.</small>
        </div>
        <div>
            <label>Low-stock alert level (stock units)</label>
            <input type="number" name="reorder_level" min="0" step="1"
                   value="<?= e($editProduct['reorder_level'] ?? '0') ?>">
        </div>
        <div>
            <label style="display:flex;gap:.5rem;align-items:center;text-transform:none;letter-spacing:0">
                <input type="checkbox" name="track_stock" value="1"
                       <?= !empty($editProduct['track_stock']) ? 'checked' : '' ?>
                       style="width:auto">
                Track stock and deduct automatically in Sales
            </label>
        </div>

        <!-- ACTIVE -->

        <div
            style="
                display:flex;
                align-items:flex-end
            "
        >

            <label
                style="
                    display:flex;
                    gap:.5rem;
                    align-items:center;
                    text-transform:none;
                    letter-spacing:0
                "
            >

                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    <?= (
                        !isset($editProduct['is_active']) ||
                        $editProduct['is_active']
                    ) ? 'checked' : '' ?>
                    style="width:auto"
                >

                Active

            </label>

        </div>

    </div>


    <!-- BUTTONS -->

    <div
        style="
            display:flex;
            gap:.6rem;
            flex-wrap:wrap;
            margin-top:1rem
        "
    >

        <button
            class="btn btn-primary"
            type="submit"
        >

            <?= $editProduct
                ? 'Update Product'
                : 'Add product'
            ?>

        </button>


        <?php if ($editProduct): ?>

            <a
                class="btn btn-ghost"
                href="products.php"
            >
                Cancel
            </a>

        <?php endif; ?>

    </div>

</form>

<?php endif; ?>


<!-- =====================================================
     PRODUCT LIST
     ===================================================== -->

<div
    class="card"
    style="padding:0"
>

    <div
        class="admin-table-wrap"
        style="overflow-x:auto"
    >

        <table
            style="
                width:100%;
                border-collapse:collapse
            "
        >

            <thead>

                <tr
                    style="
                        text-align:left;
                        font-size:.72rem;
                        letter-spacing:.12em;
                        text-transform:uppercase;
                        color:#7a716a
                    "
                >

                    <th
                        style="
                            padding:.9rem 1rem
                        "
                    >
                        Name
                    </th>

                    <th>
                        Category
                    </th>

                    <th>
                        Price
                    </th>

                    <th>
                        Status
                    </th>

                    <?php if ($canEdit): ?>

                        <th>
                            Actions
                        </th>

                    <?php endif; ?>

                </tr>

            </thead>


            <tbody>

            <?php foreach ($rows as $p): ?>

                <tr
                    style="
                        border-top:1px solid #262220
                    "
                >

                    <td
                        style="
                            padding:.9rem 1rem;
                            min-width:180px
                        "
                    >

                        <strong>
                            <?= e($p['name']) ?>
                        </strong>

                    </td>


                    <td>

                        <span
                            style="
                                color:#7a716a;
                                font-size:.82rem
                            "
                        >
                            <?= e(
                                $cats[$p['category']]
                                ?? $p['category']
                            ) ?>
                        </span>

                    </td>


                    <td>

                        <strong>
                            <?= e(
                                ops_money($p['price'])
                            ) ?>
                        </strong>

                    </td>


                    <td>

                        <?php if ($p['is_active']): ?>

                            <span
                                style="
                                    color:#bfe7c8;
                                    font-size:.8rem
                                "
                            >
                                Active
                            </span>

                        <?php else: ?>

                            <span
                                style="
                                    color:#f0b3b3;
                                    font-size:.8rem
                                "
                            >
                                Inactive
                            </span>

                        <?php endif; ?>

                    </td>


                    <?php if ($canEdit): ?>

                    <td
                        style="
                            white-space:nowrap
                        "
                    >

                        <div
                            style="
                                display:flex;
                                gap:.4rem;
                                flex-wrap:wrap
                            "
                        >

                            <!-- EDIT -->

                            <a
                                class="btn btn-sm btn-primary"
                                href="products.php?edit=<?= (int)$p['id'] ?>"
                            >
                                Edit
                            </a>


                            <!-- DELETE -->

                            <form
                                method="post"
                                style="display:inline"
                                onsubmit="return confirm('Delete this product?')"
                            >

                                <?= csrf_field() ?>

                                <input
                                    type="hidden"
                                    name="action"
                                    value="delete"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int)$p['id'] ?>"
                                >

                                <button
                                    class="btn btn-sm btn-ghost"
                                    type="submit"
                                    style="
                                        border-color:#a33;
                                        color:#f0b3b3
                                    "
                                >
                                    Delete
                                </button>

                            </form>

                        </div>

                    </td>

                    <?php endif; ?>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>


<?php require __DIR__ . '/includes/admin-footer.php'; ?>
