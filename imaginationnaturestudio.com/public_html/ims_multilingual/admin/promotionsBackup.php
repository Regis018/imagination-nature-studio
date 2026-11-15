<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pdo = db();

/* =========================================================
   PROMOTION IMAGE UPLOAD
   ========================================================= */

$promotionUploadDir = dirname(__DIR__) . '/uploads/promotions';
$promotionUploadUrl = rtrim(SITE_URL, '/') . '/uploads/promotions';

if (!is_dir($promotionUploadDir)) {
    @mkdir($promotionUploadDir, 0755, true);
}

/* =========================================================
   CSRF
   ========================================================= */

$csrf = $_SESSION['promotions_csrf'] ?? null;

if (!$csrf) {
    $csrf = bin2hex(random_bytes(32));
    $_SESSION['promotions_csrf'] = $csrf;
}

/* =========================================================
   HELPERS
   ========================================================= */

function promo_slug(string $value): string
{
    $value = trim($value);

    $value = function_exists('iconv')
        ? (string)@iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $value
        )
        : $value;

    $value = strtolower($value);

    $value = preg_replace(
        '/[^a-z0-9]+/',
        '-',
        $value
    ) ?? '';

    return trim($value, '-') ?: 'promotion-' . time();
}

function promo_money($value): ?float
{
    if ($value === '' || $value === null) {
        return null;
    }

    return max(0, (float)$value);
}

function promo_dt(string $value): ?string
{
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    $ts = strtotime($value);

    return $ts
        ? date('Y-m-d H:i:s', $ts)
        : null;
}

/* =========================================================
   POST ACTIONS
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        /* -----------------------------------------------------
           CSRF CHECK
           ----------------------------------------------------- */

        if (
            !hash_equals(
                $_SESSION['promotions_csrf'] ?? '',
                (string)($_POST['csrf_token'] ?? '')
            )
        ) {
            throw new RuntimeException(
                'Invalid security token. Please refresh and try again.'
            );
        }

        $action = (string)($_POST['action'] ?? '');

        /* =====================================================
           DELETE PROMOTION
           ===================================================== */

        if ($action === 'delete') {

            $id = (int)($_POST['id'] ?? 0);

            if ($id < 1) {
                throw new RuntimeException(
                    'Invalid promotion.'
                );
            }

            $stmt = $pdo->prepare(
                "DELETE FROM promotions WHERE id = ?"
            );

            $stmt->execute([$id]);

            $_SESSION['flash_success'] =
                'Promotion deleted successfully.';

            header('Location: promotions.php');
            exit;
        }

        /* =====================================================
           TOGGLE STATUS
           ===================================================== */

        if ($action === 'toggle') {

            $id = (int)($_POST['id'] ?? 0);

            if ($id < 1) {
                throw new RuntimeException(
                    'Invalid promotion.'
                );
            }

            $stmt = $pdo->prepare("
                UPDATE promotions
                SET status =
                    CASE
                        WHEN status = 'active'
                        THEN 'inactive'
                        ELSE 'active'
                    END
                WHERE id = ?
            ");

            $stmt->execute([$id]);

            $_SESSION['flash_success'] =
                'Promotion status updated.';

            header('Location: promotions.php');
            exit;
        }

        /* =====================================================
           SAVE / CREATE / UPDATE
           ===================================================== */

        if ($action === 'save') {

            $id = (int)($_POST['id'] ?? 0);

            /* -------------------------------------------------
               BASIC INFORMATION
               ------------------------------------------------- */

            $title = trim(
                (string)($_POST['title'] ?? '')
            );

            if ($title === '') {
                throw new RuntimeException(
                    'Promotion title is required.'
                );
            }

            $slug = promo_slug(
                (string)($_POST['slug'] ?? '') ?: $title
            );

            $subtitle = trim(
                (string)($_POST['subtitle'] ?? '')
            );

            $description = trim(
                (string)($_POST['description'] ?? '')
            );

            $image = trim(
                (string)(
                    $_POST['existing_image']
                    ?? ($edit['image'] ?? '')
                )
            );

            /* =================================================
               PROMOTION IMAGE UPLOAD
               ================================================= */

            if (
                isset($_FILES['promotion_image']) &&
                is_array($_FILES['promotion_image']) &&
                (int)(
                    $_FILES['promotion_image']['error']
                    ?? UPLOAD_ERR_NO_FILE
                ) !== UPLOAD_ERR_NO_FILE
            ) {

                $uploadError = (int)(
                    $_FILES['promotion_image']['error']
                    ?? UPLOAD_ERR_NO_FILE
                );

                if ($uploadError !== UPLOAD_ERR_OK) {
                    throw new RuntimeException(
                        'Promotion image upload failed. Please try again.'
                    );
                }

                $tmp = (string)(
                    $_FILES['promotion_image']['tmp_name']
                    ?? ''
                );

                $originalName = (string)(
                    $_FILES['promotion_image']['name']
                    ?? ''
                );

                $fileSize = (int)(
                    $_FILES['promotion_image']['size']
                    ?? 0
                );

                /* ---------------------------------------------
                   MAXIMUM FILE SIZE = 8 MB
                   --------------------------------------------- */

                if (
                    $fileSize <= 0 ||
                    $fileSize > 8 * 1024 * 1024
                ) {
                    throw new RuntimeException(
                        'Promotion image must be between 1 byte and 8 MB.'
                    );
                }

                if (!is_uploaded_file($tmp)) {
                    throw new RuntimeException(
                        'Invalid uploaded image.'
                    );
                }

                /* ---------------------------------------------
                   CHECK MIME TYPE
                   --------------------------------------------- */

                $finfo = new finfo(FILEINFO_MIME_TYPE);

                $mime = (string)$finfo->file($tmp);

                $allowedMimes = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                ];

                if (!isset($allowedMimes[$mime])) {
                    throw new RuntimeException(
                        'Only JPG, PNG, and WEBP images are allowed.'
                    );
                }

                /* ---------------------------------------------
                   SAFE FILE NAME
                   --------------------------------------------- */

                $safeBase = promo_slug(
                    pathinfo(
                        $originalName,
                        PATHINFO_FILENAME
                    )
                );

                $newFilename =
                    $safeBase .
                    '-' .
                    bin2hex(random_bytes(6)) .
                    '.' .
                    $allowedMimes[$mime];

                $destination =
                    $promotionUploadDir .
                    '/' .
                    $newFilename;

                if (!move_uploaded_file(
                    $tmp,
                    $destination
                )) {
                    throw new RuntimeException(
                        'Could not save the promotion image.'
                    );
                }

                /* ---------------------------------------------
                   DELETE OLD IMAGE WHEN REPLACING
                   --------------------------------------------- */

                if ($image !== '') {

                    $oldRelative = ltrim(
                        str_replace(
                            '\\',
                            '/',
                            $image
                        ),
                        '/'
                    );

                    if (
                        strpos(
                            $oldRelative,
                            'promotions/'
                        ) === 0
                    ) {

                        $oldPath =
                            $promotionUploadDir .
                            '/' .
                            basename($oldRelative);

                        if (is_file($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                }

                $image =
                    'promotions/' .
                    $newFilename;
            }

            /* =================================================
               PRICING
               ================================================= */

            $oldPrice = promo_money(
                $_POST['old_price'] ?? ''
            );

            $newPrice = promo_money(
                $_POST['new_price'] ?? ''
            );

            $discount = promo_money(
                $_POST['discount_percent'] ?? ''
            );

            /* =================================================
               BUTTON
               ================================================= */

            $buttonText = trim(
                (string)(
                    $_POST['button_text']
                    ?? 'Book Now'
                )
            ) ?: 'Book Now';

            $buttonLink = trim(
                (string)(
                    $_POST['button_link']
                    ?? '/booking.php'
                )
            ) ?: '/booking.php';

            /* =================================================
               WHATSAPP
               ================================================= */

            $whatsapp = trim(
                (string)(
                    $_POST['whatsapp_number']
                    ?? ''
                )
            );

            /* =================================================
               DATES
               ================================================= */

            $starts = promo_dt(
                (string)(
                    $_POST['starts_at']
                    ?? ''
                )
            );

            $ends = promo_dt(
                (string)(
                    $_POST['ends_at']
                    ?? ''
                )
            );

            /* =================================================
               STATUS
               ================================================= */

            $status = in_array(
                $_POST['status'] ?? '',
                [
                    'draft',
                    'active',
                    'inactive'
                ],
                true
            )
                ? (string)$_POST['status']
                : 'draft';

            /* =================================================
               AUTO CALCULATE DISCOUNT
               ================================================= */

            if (
                $newPrice !== null &&
                $oldPrice !== null &&
                $oldPrice > 0 &&
                $discount === null
            ) {

                $discount = round(
                    (
                        ($oldPrice - $newPrice)
                        / $oldPrice
                    ) * 100,
                    2
                );

                $discount = max(
                    0,
                    min(100, $discount)
                );
            }

            /* =================================================
               CHECK UNIQUE SLUG
               ================================================= */

            $check = $pdo->prepare("
                SELECT id
                FROM promotions
                WHERE slug = ?
                AND id <> ?
            ");

            $check->execute([
                $slug,
                $id
            ]);

            if ($check->fetchColumn()) {
                $slug .= '-' . time();
            }

            /* =================================================
               UPDATE EXISTING PROMOTION
               ================================================= */

            if ($id > 0) {

                $stmt = $pdo->prepare("
                    UPDATE promotions SET
                        title=?,
                        slug=?,
                        subtitle=?,
                        description=?,
                        image=?,
                        old_price=?,
                        new_price=?,
                        discount_percent=?,
                        button_text=?,
                        button_link=?,
                        whatsapp_number=?,
                        starts_at=?,
                        ends_at=?,
                        status=?
                    WHERE id=?
                ");

                $stmt->execute([
                    $title,
                    $slug,
                    $subtitle,
                    $description,
                    $image,
                    $oldPrice,
                    $newPrice,
                    $discount,
                    $buttonText,
                    $buttonLink,
                    $whatsapp,
                    $starts,
                    $ends,
                    $status,
                    $id
                ]);

                $_SESSION['flash_success'] =
                    'Promotion updated successfully.';

            } else {

                /* =================================================
                   CREATE NEW PROMOTION
                   ================================================= */

                $stmt = $pdo->prepare("
                    INSERT INTO promotions
                    (
                        title,
                        slug,
                        subtitle,
                        description,
                        image,
                        old_price,
                        new_price,
                        discount_percent,
                        button_text,
                        button_link,
                        whatsapp_number,
                        starts_at,
                        ends_at,
                        status
                    )
                    VALUES
                    (
                        ?,?,?,?,?,?,?,?,?,?,?,?,?,?
                    )
                ");

                $stmt->execute([
                    $title,
                    $slug,
                    $subtitle,
                    $description,
                    $image,
                    $oldPrice,
                    $newPrice,
                    $discount,
                    $buttonText,
                    $buttonLink,
                    $whatsapp,
                    $starts,
                    $ends,
                    $status
                ]);

                $_SESSION['flash_success'] =
                    'Promotion created successfully.';
            }

            header('Location: promotions.php');
            exit;
        }

    } catch (Throwable $e) {

        $_SESSION['flash_error'] =
            $e->getMessage();

        header('Location: promotions.php');
        exit;
    }
}

/* =========================================================
   EDIT PROMOTION
   ========================================================= */

$edit = null;

if (isset($_GET['edit'])) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM promotions
        WHERE id = ?
    ");

    $stmt->execute([
        (int)$_GET['edit']
    ]);

    $edit =
        $stmt->fetch(PDO::FETCH_ASSOC)
        ?: null;
}

/* =========================================================
   GET ALL PROMOTIONS
   ========================================================= */

$promotions = $pdo->query("
    SELECT *
    FROM promotions
    ORDER BY created_at DESC, id DESC
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   STATISTICS
   ========================================================= */

$total = count($promotions);

$active = count(
    array_filter(
        $promotions,
        fn($p) => $p['status'] === 'active'
    )
);

$views = array_sum(
    array_map(
        fn($p) => (int)$p['views'],
        $promotions
    )
);

$clicks = array_sum(
    array_map(
        fn($p) => (int)$p['whatsapp_clicks'],
        $promotions
    )
);

/* =========================================================
   PAGE HEADER
   ========================================================= */

$pageTitle = 'Marketing Promotions';

require __DIR__ . '/includes/admin-header.php';

?>

<style>

/* =========================================================
   MAIN WRAPPER
   ========================================================= */

.pm-wrap{
    padding:28px;
    max-width:1400px;
    margin:0 auto;
    box-sizing:border-box;
}


/* =========================================================
   HEADER
   ========================================================= */

.pm-head{
    display:flex;
    justify-content:space-between;
    gap:20px;
    align-items:center;
    margin-bottom:22px;
}

.pm-title h1{
    margin:0;
    font-size:30px;
}

.pm-title p{
    margin:7px 0 0;
    color:#999;
}


/* =========================================================
   BUTTONS
   ========================================================= */

.pm-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:11px 16px;
    border-radius:11px;
    text-decoration:none;
    border:1px solid rgba(255,255,255,.12);
    background:#fff;
    color:#111;
    font-weight:800;
    cursor:pointer;
    box-sizing:border-box;
}

.pm-btn.dark{
    background:rgba(255,255,255,.06);
    color:#fff;
}

.pm-btn.danger{
    background:#5a1717;
    color:#fff;
}


/* =========================================================
   STATISTICS
   ========================================================= */

.pm-stats{
    display:grid;
    grid-template-columns:
        repeat(4,minmax(0,1fr));
    gap:14px;
    margin-bottom:22px;
}

.pm-stat,
.pm-card{
    background:rgba(255,255,255,.035);
    border:1px solid rgba(255,255,255,.08);
    border-radius:17px;
}

.pm-stat{
    padding:18px;
}

.pm-stat small{
    color:#999;
}

.pm-stat strong{
    display:block;
    font-size:27px;
    margin-top:7px;
}


/* =========================================================
   MAIN GRID
   ========================================================= */

/*
   IMPORTANT:
   The form is deliberately limited to 420px.
   This prevents the input boxes from stretching
   across the whole screen.
*/

.pm-grid{
    display:grid;
    grid-template-columns:
        420px
        minmax(0,1fr);
    gap:22px;
    align-items:start;
}


/* =========================================================
   CARDS
   ========================================================= */

.pm-card{
    padding:20px;
    box-sizing:border-box;
}

.pm-card h2{
    margin-top:0;
    margin-bottom:20px;
}


/* =========================================================
   FORM
   ========================================================= */

.pm-form{
    display:grid;
    gap:12px;
}

.pm-form label{
    display:block;
    font-size:12px;
    color:#aaa;
    font-weight:700;
    margin-bottom:5px;
}


/* =========================================================
   INPUTS
   ========================================================= */

.pm-form input,
.pm-form textarea,
.pm-form select{

    width:100%;

    /*
       This is the important part.
       Inputs cannot become extremely wide.
    */

    max-width:350px;

    box-sizing:border-box;

    padding:11px 12px;

    border-radius:10px;

    border:1px solid
        rgba(255,255,255,.12);

    background:#111;

    color:#fff;

    outline:none;
}


/* =========================================================
   INPUT FOCUS
   ========================================================= */

.pm-form input:focus,
.pm-form textarea:focus,
.pm-form select:focus{

    border-color:
        rgba(255,255,255,.30);

    box-shadow:
        0 0 0 3px
        rgba(255,255,255,.04);
}


/* =========================================================
   DESCRIPTION
   ========================================================= */

.pm-form textarea{

    min-height:90px;

    max-width:350px;

    resize:vertical;

    line-height:1.5;
}


/* =========================================================
   PRICE / DATE ROW
   ========================================================= */

.pm-row{

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:10px;

    max-width:350px;
}

.pm-row > div{
    min-width:0;
}

.pm-row input{
    max-width:170px;
}


/* =========================================================
   FILE INPUT
   ========================================================= */

.pm-form input[type="file"]{

    max-width:350px;

    padding:8px;

    cursor:pointer;
}


/* =========================================================
   CURRENT IMAGE
   ========================================================= */

.pm-current-image{

    display:flex;

    align-items:center;

    gap:12px;

    padding:10px;

    max-width:350px;

    border:1px solid
        rgba(255,255,255,.08);

    border-radius:12px;

    background:
        rgba(255,255,255,.025);

    box-sizing:border-box;
}

.pm-current-image img{

    width:82px;

    height:58px;

    object-fit:cover;

    border-radius:9px;

    background:#111;

    flex-shrink:0;
}

.pm-current-image div{

    min-width:0;

    display:flex;

    flex-direction:column;

    gap:4px;
}

.pm-current-image strong{
    font-size:12px;
    color:#fff;
}

.pm-current-image small{

    color:#777;

    word-break:break-all;
}

.pm-current-image.broken img{
    display:none;
}


/* =========================================================
   HELP TEXT
   ========================================================= */

.pm-help{

    max-width:350px;

    font-size:12px;

    color:#888;

    line-height:1.6;
}


/* =========================================================
   FLASH MESSAGE
   ========================================================= */

.pm-flash{

    padding:12px 14px;

    border-radius:10px;

    margin-bottom:16px;

    background:#123d27;

    color:#8df2b4;
}

.pm-error{

    background:#4b1e1e;

    color:#ffb1b1;
}


/* =========================================================
   TABLE
   ========================================================= */

.pm-table{

    width:100%;

    border-collapse:collapse;

    table-layout:auto;
}

.pm-table th,
.pm-table td{

    padding:13px 10px;

    border-bottom:
        1px solid
        rgba(255,255,255,.07);

    text-align:left;

    vertical-align:middle;
}

.pm-table th{

    font-size:11px;

    color:#999;

    text-transform:uppercase;

    white-space:nowrap;
}

.pm-table td{

    font-size:13px;
}

.pm-table td strong{
    color:#fff;
}

.pm-table td small{
    color:#888;
}


/* =========================================================
   STATUS BADGES
   ========================================================= */

.pm-badge{

    display:inline-block;

    padding:5px 9px;

    border-radius:99px;

    font-size:11px;

    font-weight:800;

    white-space:nowrap;
}

.pm-badge.active{

    background:#123d27;

    color:#6df0a8;
}

.pm-badge.inactive{

    background:#402323;

    color:#ff9b9b;
}

.pm-badge.draft{

    background:#37321b;

    color:#f5d76e;
}


/* =========================================================
   ACTION BUTTONS
   ========================================================= */

.pm-actions{

    display:flex;

    gap:6px;

    flex-wrap:wrap;
}

.pm-actions .pm-btn{

    padding:
        8px 11px;

    font-size:12px;
}


/* =========================================================
   TABLE CARD
   ========================================================= */

.table-card{

    min-width:0;

    overflow:hidden;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media(max-width:1100px){

    .pm-grid{

        grid-template-columns:
            380px
            minmax(0,1fr);

        gap:18px;
    }

    .pm-form input,
    .pm-form textarea,
    .pm-form select,
    .pm-form input[type="file"]{

        max-width:330px;
    }

    .pm-row{

        max-width:330px;
    }

    .pm-row input{

        max-width:160px;
    }
}


/* =========================================================
   TABLET
   ========================================================= */

@media(max-width:900px){

    .pm-grid{

        grid-template-columns:1fr;
    }

    .pm-card{

        width:100%;
    }

    .pm-form input,
    .pm-form textarea,
    .pm-form select,
    .pm-form input[type="file"]{

        max-width:500px;
    }

    .pm-row{

        max-width:500px;
    }

    .pm-row input{

        max-width:none;
    }

    .table-card{

        overflow-x:auto;
    }

    .pm-table{

        min-width:850px;
    }
}


/* =========================================================
   MOBILE
   ========================================================= */

@media(max-width:650px){

    .pm-wrap{

        padding:16px;
    }

    .pm-head{

        align-items:flex-start;

        flex-direction:column;
    }

    .pm-title h1{

        font-size:24px;
    }

    .pm-stats{

        grid-template-columns:
            repeat(2,minmax(0,1fr));
    }

    .pm-form input,
    .pm-form textarea,
    .pm-form select,
    .pm-form input[type="file"]{

        max-width:100%;
    }

    .pm-row{

        grid-template-columns:1fr;

        max-width:100%;
    }

    .pm-row input{

        max-width:100%;
    }

    .pm-current-image{

        max-width:100%;
    }

    .pm-help{

        max-width:100%;
    }
}


/* =========================================================
   VERY SMALL MOBILE
   ========================================================= */

@media(max-width:420px){

    .pm-stats{

        grid-template-columns:1fr;
    }

    .pm-card{

        padding:16px;
    }
}

</style>


<div class="pm-wrap">

    <!-- =====================================================
         PAGE HEADER
         ===================================================== -->

    <div class="pm-head">

        <div class="pm-title">

            <h1>
                📢 Marketing Promotions
            </h1>

            <p>
                Create offers once and display them
                on your public website.
            </p>

        </div>


        <?php if ($edit): ?>

            <a
                class="pm-btn dark"
                href="promotions.php"
            >
                ＋ New Promotion
            </a>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         SUCCESS MESSAGE
         ===================================================== -->

    <?php if (!empty($_SESSION['flash_success'])): ?>

        <div class="pm-flash">

            <?= e($_SESSION['flash_success']); ?>

            <?php
            unset($_SESSION['flash_success']);
            ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         ERROR MESSAGE
         ===================================================== -->

    <?php if (!empty($_SESSION['flash_error'])): ?>

        <div class="pm-flash pm-error">

            <?= e($_SESSION['flash_error']); ?>

            <?php
            unset($_SESSION['flash_error']);
            ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         STATISTICS
         ===================================================== -->

    <div class="pm-stats">

        <div class="pm-stat">

            <small>
                Total Promotions
            </small>

            <strong>
                <?= $total ?>
            </strong>

        </div>


        <div class="pm-stat">

            <small>
                Active
            </small>

            <strong>
                <?= $active ?>
            </strong>

        </div>


        <div class="pm-stat">

            <small>
                Website Views
            </small>

            <strong>
                <?= number_format($views) ?>
            </strong>

        </div>


        <div class="pm-stat">

            <small>
                WhatsApp Clicks
            </small>

            <strong>
                <?= number_format($clicks) ?>
            </strong>

        </div>

    </div>


    <!-- =====================================================
         MAIN CONTENT
         ===================================================== -->

    <div class="pm-grid">


        <!-- =================================================
             CREATE / EDIT PROMOTION
             ================================================= -->

        <div class="pm-card">

            <h2>

                <?= $edit
                    ? '✏️ Edit Promotion'
                    : '➕ Create Promotion'
                ?>

            </h2>


            <form
                method="post"
                class="pm-form"
                enctype="multipart/form-data"
            >

                <!-- CSRF -->

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrf) ?>"
                >


                <!-- ACTION -->

                <input
                    type="hidden"
                    name="action"
                    value="save"
                >


                <!-- ID -->

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int)($edit['id'] ?? 0) ?>"
                >


                <!-- =================================================
                     TITLE
                     ================================================= -->

                <div>

                    <label>
                        Title
                    </label>

                    <input
                        name="title"
                        required
                        value="<?= e($edit['title'] ?? '') ?>"
                        placeholder="Weekend Portrait Offer"
                    >

                </div>


                <!-- =================================================
                     SUBTITLE
                     ================================================= -->

                <div>

                    <label>
                        Subtitle
                    </label>

                    <input
                        name="subtitle"
                        value="<?= e($edit['subtitle'] ?? '') ?>"
                        placeholder="Limited time offer"
                    >

                </div>


                <!-- =================================================
                     SLUG
                     ================================================= -->

                <div>

                    <label>
                        Slug (optional)
                    </label>

                    <input
                        name="slug"
                        value="<?= e($edit['slug'] ?? '') ?>"
                        placeholder="weekend-portrait-offer"
                    >

                </div>


                <!-- =================================================
                     DESCRIPTION
                     ================================================= -->

                <div>

                    <label>
                        Description
                    </label>

                    <textarea
                        name="description"
                        placeholder="Describe the offer..."
                    ><?= e($edit['description'] ?? '') ?></textarea>

                </div>


                <!-- =================================================
                     PROMOTION IMAGE
                     ================================================= -->

                <div>

                    <label>
                        Promotion Image
                    </label>


                    <?php if (!empty($edit['image'])): ?>

                        <?php

                        $currentPromoImage =
                            (string)$edit['image'];

                        $currentPromoImageUrl =
                            preg_match(
                                '~^https?://~i',
                                $currentPromoImage
                            )
                            ? $currentPromoImage
                            : rtrim(
                                SITE_URL,
                                '/'
                            )
                            . '/uploads/'
                            . ltrim(
                                $currentPromoImage,
                                '/'
                            );

                        ?>


                        <div class="pm-current-image">

                            <img
                                src="<?= e($currentPromoImageUrl) ?>"
                                alt="<?= e(
                                    $edit['title']
                                    ?? 'Promotion'
                                ) ?>"
                                onerror="
                                    this.parentElement
                                    .classList.add('broken')
                                "
                            >


                            <div>

                                <strong>
                                    Current image
                                </strong>

                                <small>
                                    <?= e($currentPromoImage) ?>
                                </small>

                            </div>

                        </div>

                    <?php endif; ?>


                    <input
                        type="hidden"
                        name="existing_image"
                        value="<?= e($edit['image'] ?? '') ?>"
                    >


                    <input
                        type="file"
                        name="promotion_image"
                        accept="
                            image/jpeg,
                            image/png,
                            image/webp
                        "
                    >


                    <div class="pm-help">

                        Upload JPG, PNG or WEBP.
                        Maximum 8 MB.

                        <br>

                        Recommended:
                        1600×900 or 1920×1080
                        for a professional homepage promotion.

                    </div>

                </div>


                <!-- =================================================
                     PRICES
                     ================================================= -->

                <div class="pm-row">

                    <div>

                        <label>
                            Old price
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="old_price"
                            value="<?= e(
                                $edit['old_price'] ?? ''
                            ) ?>"
                        >

                    </div>


                    <div>

                        <label>
                            New price
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="new_price"
                            value="<?= e(
                                $edit['new_price'] ?? ''
                            ) ?>"
                        >

                    </div>

                </div>


                <!-- =================================================
                     DISCOUNT
                     ================================================= -->

                <div>

                    <label>
                        Discount % (optional)
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        name="discount_percent"
                        value="<?= e(
                            $edit['discount_percent']
                            ?? ''
                        ) ?>"
                    >

                </div>


                <!-- =================================================
                     START / END
                     ================================================= -->

                <div class="pm-row">

                    <div>

                        <label>
                            Starts
                        </label>

                        <input
                            type="datetime-local"
                            name="starts_at"
                            value="<?=
                                !empty($edit['starts_at'])
                                ? e(
                                    date(
                                        'Y-m-d\TH:i',
                                        strtotime(
                                            $edit['starts_at']
                                        )
                                    )
                                )
                                : ''
                            ?>"
                        >

                    </div>


                    <div>

                        <label>
                            Ends
                        </label>

                        <input
                            type="datetime-local"
                            name="ends_at"
                            value="<?=
                                !empty($edit['ends_at'])
                                ? e(
                                    date(
                                        'Y-m-d\TH:i',
                                        strtotime(
                                            $edit['ends_at']
                                        )
                                    )
                                )
                                : ''
                            ?>"
                        >

                    </div>

                </div>


                <!-- =================================================
                     STATUS
                     ================================================= -->

                <div>

                    <label>
                        Status
                    </label>

                    <select name="status">

                        <?php foreach (
                            [
                                'draft',
                                'active',
                                'inactive'
                            ]
                            as $st
                        ): ?>

                            <option
                                value="<?= $st ?>"
                                <?=
                                    (
                                        (
                                            $edit['status']
                                            ?? 'draft'
                                        ) === $st
                                    )
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= ucfirst($st) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- =================================================
                     BUTTON TEXT
                     ================================================= -->

                <div>

                    <label>
                        Button text
                    </label>

                    <input
                        name="button_text"
                        value="<?= e(
                            $edit['button_text']
                            ?? 'Book Now'
                        ) ?>"
                    >

                </div>


                <!-- =================================================
                     BUTTON LINK
                     ================================================= -->

                <div>

                    <label>
                        Button link
                    </label>

                    <input
                        name="button_link"
                        value="<?= e(
                            $edit['button_link']
                            ?? '/booking.php'
                        ) ?>"
                    >

                </div>


                <!-- =================================================
                     WHATSAPP
                     ================================================= -->

                <div>

                    <label>
                        WhatsApp number (optional)
                    </label>

                    <input
                        name="whatsapp_number"
                        value="<?= e(
                            $edit['whatsapp_number']
                            ?? '250788577033'
                        ) ?>"
                        placeholder="250788577033"
                    >

                </div>


                <!-- =================================================
                     SUBMIT
                     ================================================= -->

                <div>

                    <button
                        class="pm-btn"
                        type="submit"
                    >

                        <?=
                            $edit
                            ? 'Save Changes'
                            : 'Create Promotion'
                        ?>

                    </button>

                </div>

            </form>

        </div>


        <!-- =====================================================
             PROMOTIONS TABLE
             ===================================================== -->

        <div class="pm-card table-card">

            <h2>
                📋 Promotions
            </h2>


            <table class="pm-table">

                <thead>

                    <tr>

                        <th>
                            Promotion
                        </th>

                        <th>
                            Price
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Views
                        </th>

                        <th>
                            Clicks
                        </th>

                        <th>
                            Period
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (!$promotions): ?>

                    <tr>

                        <td colspan="7">

                            No promotions yet.

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach (
                        $promotions as $p
                    ): ?>

                        <tr>


                            <!-- PROMOTION -->

                            <td>

                                <strong>
                                    <?= e(
                                        $p['title']
                                    ) ?>
                                </strong>

                                <br>

                                <small>
                                    <?= e(
                                        $p['slug']
                                    ) ?>
                                </small>

                            </td>


                            <!-- PRICE -->

                            <td>

                                <?php if (
                                    $p['old_price']
                                    !== null
                                ): ?>

                                    <s>
                                        <?= e(
                                            rwf(
                                                $p['old_price']
                                            )
                                        ) ?>
                                    </s>

                                    <br>

                                <?php endif; ?>


                                <?php if (
                                    $p['new_price']
                                    !== null
                                ): ?>

                                    <strong>
                                        <?= e(
                                            rwf(
                                                $p['new_price']
                                            )
                                        ) ?>
                                    </strong>

                                <?php endif; ?>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="pm-badge <?= e(
                                        $p['status']
                                    ) ?>"
                                >

                                    <?= e(
                                        ucfirst(
                                            $p['status']
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- VIEWS -->

                            <td>

                                <?= number_format(
                                    (int)$p['views']
                                ) ?>

                            </td>


                            <!-- CLICKS -->

                            <td>

                                <?= number_format(
                                    (int)$p['whatsapp_clicks']
                                ) ?>

                            </td>


                            <!-- PERIOD -->

                            <td>

                                <small>

                                    <?=
                                        $p['starts_at']
                                        ? e(
                                            date(
                                                'd M Y H:i',
                                                strtotime(
                                                    $p['starts_at']
                                                )
                                            )
                                        )
                                        : '—'
                                    ?>

                                    <br>

                                    <?=
                                        $p['ends_at']
                                        ? '→ ' . e(
                                            date(
                                                'd M Y H:i',
                                                strtotime(
                                                    $p['ends_at']
                                                )
                                            )
                                        )
                                        : 'No end'
                                    ?>

                                </small>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div class="pm-actions">


                                    <!-- EDIT -->

                                    <a
                                        class="pm-btn dark"
                                        href="?edit=<?= (int)$p['id'] ?>"
                                    >

                                        Edit

                                    </a>


                                    <!-- TOGGLE -->

                                    <form
                                        method="post"
                                        onsubmit="
                                            return confirm(
                                                'Change this promotion status?'
                                            )
                                        "
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($csrf) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="toggle"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$p['id'] ?>"
                                        >

                                        <button
                                            class="pm-btn dark"
                                            type="submit"
                                        >

                                            <?=
                                                $p['status'] === 'active'
                                                ? 'Disable'
                                                : 'Activate'
                                            ?>

                                        </button>

                                    </form>


                                    <!-- DELETE -->

                                    <form
                                        method="post"
                                        onsubmit="
                                            return confirm(
                                                'Delete this promotion permanently?'
                                            )
                                        "
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($csrf) ?>"
                                        >

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
                                            class="pm-btn danger"
                                            type="submit"
                                        >

                                            Delete

                                        </button>

                                    </form>


                                </div>

                            </td>


                        </tr>

                    <?php endforeach; ?>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>

</div>


<?php

require __DIR__ . '/includes/admin-footer.php';

?>