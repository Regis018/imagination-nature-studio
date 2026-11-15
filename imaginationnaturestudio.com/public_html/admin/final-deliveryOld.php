<?php
declare(strict_types=1);
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_admin_only();

$pdo = db();
$notice = '';
$error = '';

function fd_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fd_storage_dir(int $galleryId): string
{
    $dir = dirname(__DIR__) . '/storage/final-delivery/' . $galleryId;

    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Ntibyashobotse gukora ububiko bw’amafoto.');
    }

    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        $written = file_put_contents($htaccess, "Options -Indexes\nRequire all denied\n");
        if ($written === false) {
            throw new RuntimeException('Ntibyashobotse kurinda ububiko bw’amafoto.');
        }
    }

    return $dir;
}


/** Create a lightweight JPEG preview; never alter the original. */
function fd_create_thumbnail(string $sourcePath, string $storageDir, string $storedName): ?string
{
    if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) return null;
    $raw = @file_get_contents($sourcePath);
    if ($raw === false) return null;
    $source = @imagecreatefromstring($raw);
    if (!$source) return null;
    $width = imagesx($source); $height = imagesy($source);
    if ($width < 1 || $height < 1) { imagedestroy($source); return null; }
    $scale = min(1, 640 / max($width, $height));
    $nw = max(1, (int)round($width * $scale)); $nh = max(1, (int)round($height * $scale));
    $thumb = imagecreatetruecolor($nw, $nh);
    if (!$thumb) { imagedestroy($source); return null; }
    $white = imagecolorallocate($thumb, 255, 255, 255);
    imagefill($thumb, 0, 0, $white);
    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $nw, $nh, $width, $height);
    $previewName = pathinfo($storedName, PATHINFO_FILENAME) . '-preview.jpg';
    $previewPath = $storageDir . '/' . $previewName;
    $saved = @imagejpeg($thumb, $previewPath, 70);
    imagedestroy($thumb); imagedestroy($source);
    if (!$saved || !is_file($previewPath)) { @unlink($previewPath); return null; }
    @chmod($previewPath, 0640);
    return $previewName;
}

/**
 * Return the best matching Sale for a Photo Order / delivery gallery.
 * Priority: gallery.sale_id, photo_orders.sale_id, sales.photo_order_id.
 * Only Photo Editing Order sales are eligible.
 */
function fd_find_sale(PDO $pdo, int $photoOrderId, ?int $gallerySaleId = null): ?array
{
    if ($gallerySaleId !== null && $gallerySaleId > 0) {
        $stmt = $pdo->prepare("
            SELECT id, product_name, total, amount_paid, balance
            FROM sales
            WHERE id = ?
              AND LOWER(COALESCE(product_name, '')) LIKE 'photo editing order%'
            LIMIT 1
        ");
        $stmt->execute([$gallerySaleId]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sale) return $sale;
    }

    $stmt = $pdo->prepare("
        SELECT s.id, s.product_name, s.total, s.amount_paid, s.balance
        FROM photo_orders po
        INNER JOIN sales s ON s.id = po.sale_id
        WHERE po.id = ?
          AND LOWER(COALESCE(s.product_name, '')) LIKE 'photo editing order%'
        LIMIT 1
    ");
    $stmt->execute([$photoOrderId]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($sale) return $sale;

    $stmt = $pdo->prepare("
        SELECT id, product_name, total, amount_paid, balance
        FROM sales
        WHERE photo_order_id = ?
          AND LOWER(COALESCE(product_name, '')) LIKE 'photo editing order%'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$photoOrderId]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    return $sale ?: null;
}

function fd_get_gallery(PDO $pdo, int $galleryId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            g.*,
            po.order_number,
            po.total_amount,
            po.amount_paid AS order_amount_paid,
            po.payment_status AS order_payment_status,
            po.sale_id AS order_sale_id
        FROM final_delivery_galleries g
        LEFT JOIN photo_orders po ON po.id = g.photo_order_id
        WHERE g.id = ?
        LIMIT 1
    ");
    $stmt->execute([$galleryId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Build the public URL for a client's delivery gallery.
 */
function fd_client_url(string $token): string
{
    return rtrim((string)SITE_URL, '/') . '/delivery/index.php?token=' . rawurlencode($token);
}

/* =========================================================
   HANDLE ADMIN ACTIONS
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('Invalid security token. Ongera ufungure page ugerageze nanone.');
    }

    $action = (string)($_POST['action'] ?? '');
    $postedGalleryId = (int)($_POST['gallery_id'] ?? 0);

    try {
        /* ---------------- CREATE GALLERY ---------------- */
        if ($action === 'create') {
            $photoOrderId = filter_var($_POST['photo_order_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$photoOrderId || $photoOrderId < 1) {
                throw new RuntimeException('Banza uhitemo Photo Order.');
            }

            $orderStmt = $pdo->prepare("
                SELECT id, order_number, customer_name, customer_phone, total_amount, amount_paid, payment_status, sale_id
                FROM photo_orders
                WHERE id = ?
                LIMIT 1
            ");
            $orderStmt->execute([$photoOrderId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new RuntimeException('Iyo Photo Order ntiyabonetse.');
            }

            $existingStmt = $pdo->prepare("SELECT id FROM final_delivery_galleries WHERE photo_order_id = ? LIMIT 1");
            $existingStmt->execute([$photoOrderId]);
            if ($existingStmt->fetchColumn()) {
                throw new RuntimeException('Iyi Photo Order isanzwe ifite Final Delivery Gallery. Fungura gallery isanzweho.');
            }

            $sale = fd_find_sale($pdo, (int)$photoOrderId, !empty($order['sale_id']) ? (int)$order['sale_id'] : null);
            if (!$sale) {
                throw new RuntimeException('Nta Sale ihuye na Photo Editing Order yabonetse kuri iyi Photo Order.');
            }

            $token = bin2hex(random_bytes(32));
            $insert = $pdo->prepare("
                INSERT INTO final_delivery_galleries
                    (photo_order_id, sale_id, client_name, access_token, status)
                VALUES (?, ?, ?, ?, 'draft')
            ");
            $insert->execute([
                (int)$photoOrderId,
                (int)$sale['id'],
                $order['customer_name'] ?: 'Client',
                $token,
            ]);

            $newGalleryId = (int)$pdo->lastInsertId();
            fd_storage_dir($newGalleryId);
            $notice = 'Final Delivery Gallery yakozwe neza. Noneho ongeramo amafoto.';
            $_GET['gallery'] = (string)$newGalleryId;
        }

        /* ---------------- UPLOAD PHOTOS ---------------- */
        elseif ($action === 'upload') {
            $galleryId = $postedGalleryId;
            if ($galleryId < 1) {
                throw new RuntimeException('Gallery ID ntabwo ari yo.');
            }

            $gallery = fd_get_gallery($pdo, $galleryId);
            if (!$gallery) {
                throw new RuntimeException('Gallery ntiyabonetse.');
            }
            if (strtolower((string)$gallery['status']) === 'published') {
                throw new RuntimeException('Iyi gallery yamaze gusohoka. Ntushobora kongeramo amafoto kuri Published gallery.');
            }

            if (!isset($_FILES['photos']) || !is_array($_FILES['photos']['name'] ?? null)) {
                throw new RuntimeException('Hitamo amafoto ushaka kohereza.');
            }

            $storageDir = fd_storage_dir($galleryId);
            $allowedMimeTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];

            if (!class_exists('finfo')) {
                throw new RuntimeException('PHP Fileinfo extension ntabwo ikora kuri hosting.');
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $insertPhoto = $pdo->prepare("
                INSERT INTO final_delivery_photos
                    (gallery_id, original_name, stored_name, mime_type, file_size, preview_name)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $successCount = 0;
            $failedCount = 0;
            $fileCount = count($_FILES['photos']['name']);

            for ($i = 0; $i < $fileCount; $i++) {
                $uploadError = (int)($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                $tmpName = (string)($_FILES['photos']['tmp_name'][$i] ?? '');

                if ($uploadError !== UPLOAD_ERR_OK || $tmpName === '' || !is_uploaded_file($tmpName)) {
                    $failedCount++;
                    continue;
                }

                $fileSize = (int)($_FILES['photos']['size'][$i] ?? 0);
                if ($fileSize < 1 || $fileSize > 20 * 1024 * 1024) {
                    $failedCount++;
                    continue;
                }

                $mimeType = $finfo->file($tmpName);
                if (!isset($allowedMimeTypes[$mimeType])) {
                    $failedCount++;
                    continue;
                }

                $extension = $allowedMimeTypes[$mimeType];
                $storedName = bin2hex(random_bytes(20)) . '.' . $extension;
                $destination = $storageDir . '/' . $storedName;

                if (!move_uploaded_file($tmpName, $destination)) {
                    $failedCount++;
                    continue;
                }

                @chmod($destination, 0640);
                $originalName = basename((string)($_FILES['photos']['name'][$i] ?? ('photo.' . $extension)));
                $previewName = fd_create_thumbnail($destination, $storageDir, $storedName);

                try {
                    $insertPhoto->execute([
                        $galleryId, $originalName, $storedName, $mimeType,
                        (int)filesize($destination), $previewName,
                    ]);
                    $successCount++;
                } catch (Throwable $insertError) {
                    @unlink($destination);
                    if ($previewName) @unlink($storageDir . '/' . $previewName);
                    throw $insertError;
                }
            }

            if ($successCount < 1) {
                throw new RuntimeException('Nta foto yashoboye koherezwa. Emera JPG, PNG cyangwa WEBP, kandi buri foto ibe munsi ya 20MB.');
            }

            $notice = $successCount . ' amafoto yoherejwe neza.';
            if ($failedCount > 0) {
                $notice .= ' Amafoto ' . $failedCount . ' yanze koherezwa (format, size cyangwa upload error).';
            }
            $_GET['gallery'] = (string)$galleryId;
        }

        /* ---------------- GENERATE PREVIEWS FOR EXISTING PHOTOS ---------------- */
        elseif ($action === 'generate_previews') {
            $galleryId = $postedGalleryId;
            if ($galleryId < 1) throw new RuntimeException('Gallery ID ntabwo ari yo.');
            $gallery = fd_get_gallery($pdo, $galleryId);
            if (!$gallery) throw new RuntimeException('Gallery ntiyabonetse.');

            $storageDir = fd_storage_dir($galleryId);
            $list = $pdo->prepare("
                SELECT id, stored_name, preview_name
                FROM final_delivery_photos
                WHERE gallery_id = ?
                ORDER BY id
            ");
            $list->execute([$galleryId]);
            $update = $pdo->prepare("UPDATE final_delivery_photos SET preview_name = ? WHERE id = ? AND gallery_id = ?");
            $made = 0;
            $skipped = 0;

            foreach ($list->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $storedName = basename((string)$item['stored_name']);
                $existingPreview = basename((string)($item['preview_name'] ?? ''));
                if ($existingPreview !== '' && is_file($storageDir . '/' . $existingPreview)) {
                    $skipped++;
                    continue;
                }
                if (!preg_match('/^[a-f0-9]{40}\\.(jpg|png|webp)$/', $storedName)) {
                    $skipped++;
                    continue;
                }
                $originalPath = $storageDir . '/' . $storedName;
                if (!is_file($originalPath) || !is_readable($originalPath)) {
                    $skipped++;
                    continue;
                }
                $newPreview = fd_create_thumbnail($originalPath, $storageDir, $storedName);
                if ($newPreview) {
                    $update->execute([$newPreview, (int)$item['id'], $galleryId]);
                    $made++;
                } else {
                    $skipped++;
                }
            }

            $notice = $made . ' thumbnails zakozwe neza. ' . $skipped . ' zasimbutswe cyangwa ntizashobotse gukorwa.';
            $_GET['gallery'] = (string)$galleryId;
        }

        /* ---------------- PUBLISH GALLERY ---------------- */
        elseif ($action === 'publish') {
            $galleryId = $postedGalleryId;
            if ($galleryId < 1) {
                throw new RuntimeException('Gallery ID ntabwo ari yo.');
            }

            $gallery = fd_get_gallery($pdo, $galleryId);
            if (!$gallery) {
                throw new RuntimeException('Gallery ntiyabonetse.');
            }

            $photoCountStmt = $pdo->prepare("SELECT COUNT(*) FROM final_delivery_photos WHERE gallery_id = ?");
            $photoCountStmt->execute([$galleryId]);
            $photoCount = (int)$photoCountStmt->fetchColumn();

            if ($photoCount < 1) {
                throw new RuntimeException('Publish ntiyakozwe: banza ushyiremo nibura ifoto imwe muri iyi gallery.');
            }

            $photoOrderId = (int)$gallery['photo_order_id'];
            if ($photoOrderId < 1) {
                throw new RuntimeException('Iyi gallery nta Photo Order ifite.');
            }

            $gallerySaleId = !empty($gallery['sale_id']) ? (int)$gallery['sale_id'] : null;
            $sale = fd_find_sale($pdo, $photoOrderId, $gallerySaleId);

            if (!$sale) {
                throw new RuntimeException('Nta Sale ya Photo Editing Order ihujwe n’iyi Photo Order yabonetse.');
            }

            $saleTotal = (float)$sale['total'];
            $salePaid = (float)$sale['amount_paid'];
            $saleBalance = $sale['balance'] !== null
                ? (float)$sale['balance']
                : max(0, $saleTotal - $salePaid);

            if ($saleTotal <= 0 || $salePaid + 0.00001 < $saleTotal || $saleBalance > 0.00001) {
                throw new RuntimeException(
                    'Publish ntiyemewe. Sale igomba kwishyurwa 100%. Total: '
                    . number_format($saleTotal) . ' RWF; Paid: '
                    . number_format($salePaid) . ' RWF; Balance: '
                    . number_format(max(0, $saleBalance)) . ' RWF.'
                );
            }

            $publishStmt = $pdo->prepare("
                UPDATE final_delivery_galleries
                SET sale_id = ?, status = 'published'
                WHERE id = ?
            ");
            $publishStmt->execute([(int)$sale['id'], $galleryId]);

            $verifyStmt = $pdo->prepare("SELECT status FROM final_delivery_galleries WHERE id = ? LIMIT 1");
            $verifyStmt->execute([$galleryId]);
            $publishedStatus = strtolower((string)$verifyStmt->fetchColumn());

            if ($publishedStatus !== 'published') {
                throw new RuntimeException('Database ntiyemeje ko gallery yasohotse. Ongera ugerageze.');
            }

            $notice = 'Gallery yasohotse neza. Client ashobora kureba no gukuramo amafoto.';
            $_GET['gallery'] = (string)$galleryId;
        }

        else {
            throw new RuntimeException('Action ntiyamenyekanye.');
        }

    } catch (Throwable $e) {
        error_log('[FINAL DELIVERY] ' . $e->getMessage());
        $error = $e->getMessage();
        if ($postedGalleryId > 0) {
            $_GET['gallery'] = (string)$postedGalleryId;
        }
    }
}

/* =========================================================
   PHOTO ORDER DROPDOWN
   - Only Photo Editing Order sales
   - Include unpaid orders (payment is checked at Publish)
   - Hide orders that already have a delivery gallery
========================================================= */

$orders = $pdo->query("
    SELECT
        po.id,
        po.order_number,
        po.customer_name,
        po.total_amount,
        po.amount_paid,
        po.payment_status,
        po.sale_id,
        s.id AS linked_sale_id,
        s.total AS sale_total,
        s.amount_paid AS sale_amount_paid,
        s.balance AS sale_balance
    FROM photo_orders po
    INNER JOIN sales s ON s.id = (
        SELECT s2.id
        FROM sales s2
        WHERE LOWER(COALESCE(s2.product_name, '')) LIKE 'photo editing order%'
          AND (
              (po.sale_id IS NOT NULL AND s2.id = po.sale_id)
              OR s2.photo_order_id = po.id
          )
        ORDER BY
          CASE WHEN po.sale_id IS NOT NULL AND s2.id = po.sale_id THEN 0 ELSE 1 END,
          s2.id DESC
        LIMIT 1
    )
    WHERE NOT EXISTS (
        SELECT 1 FROM final_delivery_galleries g2 WHERE g2.photo_order_id = po.id
    )
    ORDER BY po.id DESC
    LIMIT 300
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   SELECTED GALLERY DETAILS
========================================================= */

$galleryId = (int)($_GET['gallery'] ?? 0);
$gallery = null;
$photos = [];
$sale = null;
$photoCount = 0;
$fullyPaid = false;
$saleTotal = 0.0;
$salePaid = 0.0;
$saleBalance = 0.0;

if ($galleryId > 0) {
    $gallery = fd_get_gallery($pdo, $galleryId);

    if ($gallery) {
        $photoStmt = $pdo->prepare("
            SELECT id, gallery_id, original_name, stored_name, mime_type, file_size
            FROM final_delivery_photos
            WHERE gallery_id = ?
            ORDER BY id ASC
        ");
        $photoStmt->execute([$galleryId]);
        $photos = $photoStmt->fetchAll(PDO::FETCH_ASSOC);
        $photoCount = count($photos);

        $orderId = (int)$gallery['photo_order_id'];
        $gallerySaleId = !empty($gallery['sale_id']) ? (int)$gallery['sale_id'] : null;
        if ($orderId > 0) {
            $sale = fd_find_sale($pdo, $orderId, $gallerySaleId);
        }

        if ($sale) {
            $saleTotal = (float)$sale['total'];
            $salePaid = (float)$sale['amount_paid'];
            $saleBalance = $sale['balance'] !== null
                ? (float)$sale['balance']
                : max(0, $saleTotal - $salePaid);
            $fullyPaid = $saleTotal > 0
                && $salePaid + 0.00001 >= $saleTotal
                && $saleBalance <= 0.00001;
        }
    } else {
        $error = $error ?: 'Gallery ntiyabonetse.';
    }
}

/* =========================================================
   EXISTING GALLERIES
   Do not INNER JOIN Sales here: old galleries must remain visible.
========================================================= */

$existingGalleries = $pdo->query("
    SELECT
        g.id,
        g.client_name,
        g.status,
        g.access_token,
        po.order_number,
        COUNT(DISTINCT p.id) AS photo_count
    FROM final_delivery_galleries g
    LEFT JOIN photo_orders po ON po.id = g.photo_order_id
    LEFT JOIN final_delivery_photos p ON p.gallery_id = g.id
    GROUP BY g.id, g.client_name, g.status, g.access_token, po.order_number
    ORDER BY g.id DESC
    LIMIT 500
")->fetchAll(PDO::FETCH_ASSOC);

/* ADMIN-ONLY PHOTO PREVIEW
 * Draft galleries must be previewable by an authenticated admin.
 * Do not route draft previews through the public client page, which
 * intentionally exposes published galleries only.
 */
if (isset($_GET['admin_preview'])) {
    $previewGalleryId = (int)($_GET['gallery'] ?? 0);
    $previewPhotoId = (int)($_GET['admin_preview'] ?? 0);

    if ($previewGalleryId < 1 || $previewPhotoId < 1) {
        http_response_code(404);
        exit('Photo not found.');
    }

    $previewStmt = $pdo->prepare("
        SELECT stored_name, preview_name, mime_type
        FROM final_delivery_photos
        WHERE id = ? AND gallery_id = ?
        LIMIT 1
    ");
    $previewStmt->execute([$previewPhotoId, $previewGalleryId]);
    $previewPhoto = $previewStmt->fetch(PDO::FETCH_ASSOC);

    $previewMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $previewName = $previewPhoto ? basename((string)$previewPhoto['stored_name']) : '';
    if (!$previewPhoto || !in_array((string)$previewPhoto['mime_type'], $previewMimes, true)
        || !preg_match('/^[a-f0-9]{40}\\.(jpg|png|webp)$/', $previewName)) {
        http_response_code(404);
        exit('Photo not found.');
    }

    $previewBase = realpath(dirname(__DIR__) . '/storage/final-delivery/' . $previewGalleryId);
    $savedPreviewName = basename((string)($previewPhoto['preview_name'] ?? ''));
    if ($savedPreviewName !== '' && preg_match('/^[a-f0-9]{40}-preview\.jpg$/', $savedPreviewName)) {
        $candidate = $previewBase ? realpath($previewBase . DIRECTORY_SEPARATOR . $savedPreviewName) : false;
        if ($candidate && is_file($candidate)) $previewName = $savedPreviewName;
    }
    $previewFile = $previewBase ? realpath($previewBase . DIRECTORY_SEPARATOR . $previewName) : false;
    if (!$previewBase || !$previewFile
        || strpos($previewFile, $previewBase . DIRECTORY_SEPARATOR) !== 0
        || !is_file($previewFile) || !is_readable($previewFile)) {
        http_response_code(404);
        exit('Photo file unavailable.');
    }

    header('Content-Type: ' . (str_ends_with($previewName, '-preview.jpg') ? 'image/jpeg' : $previewPhoto['mime_type']));
    header('Content-Length: ' . (string)filesize($previewFile));
    header('Content-Disposition: inline; filename="preview-' . $previewPhotoId . '.' . pathinfo($previewName, PATHINFO_EXTENSION) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($previewFile);
    exit;
}

require __DIR__ . '/includes/admin-header.php';
?>

<style id="final-delivery-styles">
.fd-wrap { max-width:1100px; margin:auto; padding:1rem; }
.fd-card { padding:1rem; margin:0 0 1rem; border-radius:12px; }
.fd-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; }
.fd-stat { padding:12px; border:1px solid rgba(128,128,128,.25); border-radius:10px; }
.fd-stat small { display:block; opacity:.75; margin-bottom:4px; }
.fd-actions { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-top:12px; }
.fd-open-btn, .fd-client-link, .fd-publish-btn, .fd-upload-btn {
    display:inline-flex!important; align-items:center!important; justify-content:center!important;
    border-radius:10px!important; padding:11px 16px!important; font-weight:800!important;
    text-decoration:none!important; cursor:pointer!important; border:2px solid transparent!important;
}
.fd-open-btn { background:linear-gradient(135deg,#ffd36b,#e5a323)!important; color:#241707!important; border-color:#d99b25!important; }
.fd-client-link { background:linear-gradient(135deg,#39d47b,#168447)!important; color:#fff!important; border-color:#168447!important; }
.fd-publish-btn { background:linear-gradient(135deg,#39d47b,#168447)!important; color:#fff!important; border-color:#168447!important; }
.fd-upload-btn { background:linear-gradient(135deg,#ffd36b,#e5a323)!important; color:#241707!important; border-color:#d99b25!important; }
.fd-publish-btn:disabled, .fd-upload-btn:disabled { opacity:.55!important; cursor:not-allowed!important; }
.fd-alert { padding:12px 15px; border-radius:10px; margin:12px 0; }
.fd-success { background:#e8f7ed; color:#14532d; border:1px solid #86c99b; }
.fd-error { background:#fff0ef; color:#8b1e16; border:1px solid #e6a09a; }
.fd-table-wrap { overflow-x:auto; }
.fd-table { width:100%; border-collapse:collapse; }
.fd-table th, .fd-table td { padding:10px; border-bottom:1px solid rgba(128,128,128,.22); text-align:left; vertical-align:middle; }
.fd-muted { opacity:.72; font-size:13px; }
.fd-photo-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:12px; margin-top:15px; }
.fd-photo { border:1px solid rgba(128,128,128,.25); border-radius:10px; padding:8px; overflow:hidden; }
.fd-photo img { width:100%; height:130px; object-fit:cover; display:block; border-radius:7px; background:#222; }
.fd-photo-name { font-size:12px; overflow-wrap:anywhere; margin-top:6px; }
.fd-progress { display:none; margin:14px 0; padding:14px; background:#171717; color:#fff; border:1px solid #555; border-radius:12px; }
.fd-progress-track { height:13px; background:#383838; border-radius:99px; overflow:hidden; }
.fd-progress-bar { height:100%; width:0; background:linear-gradient(90deg,#e5a323,#ffd36b); border-radius:99px; transition:width .15s ease; }
@media(max-width:600px) { .fd-wrap { padding:.65rem; } .fd-table th,.fd-table td { padding:8px; font-size:13px; } .fd-open-btn,.fd-client-link,.fd-publish-btn,.fd-upload-btn { padding:10px 12px!important; font-size:13px!important; } }
</style>

<div class="fd-wrap">
    <h1>Final Client Photo Delivery</h1>
    <p>Create a private gallery from a Photo Editing Order. Client downloads remain locked until the linked Sale is fully paid.</p>

    <?php if ($notice !== ''): ?>
        <div class="fd-alert fd-success"><?= fd_h($notice) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="fd-alert fd-error"><?= fd_h($error) ?></div>
    <?php endif; ?>

    <section class="card fd-card">
        <h3>Create Delivery Gallery</h3>
        <?php if (!$orders): ?>
            <p class="fd-muted">Nta Photo Editing Order nshya ibonetse itarafite Final Delivery Gallery.</p>
        <?php else: ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <label for="photo_order_id">Photo Order</label>
                <select id="photo_order_id" name="photo_order_id" required style="width:100%;max-width:700px">
                    <option value="">Choose order...</option>
                    <?php foreach ($orders as $order): ?>
                        <?php
                            $optTotal = $order['sale_total'] !== null ? (float)$order['sale_total'] : (float)$order['total_amount'];
                            $optPaid = $order['sale_amount_paid'] !== null ? (float)$order['sale_amount_paid'] : (float)$order['amount_paid'];
                            $optBalance = $order['sale_balance'] !== null ? (float)$order['sale_balance'] : max(0, $optTotal - $optPaid);
                        ?>
                        <option value="<?= (int)$order['id'] ?>">
                            #<?= fd_h($order['order_number']) ?> — <?= fd_h($order['customer_name']) ?> — <?= number_format($optTotal) ?> RWF — Balance: <?= number_format($optBalance) ?> RWF
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="fd-actions">
                    <button type="submit" class="btn btn-primary">Create Gallery</button>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <?php if ($gallery): ?>
        <section class="card fd-card">
            <h3>Gallery #<?= (int)$gallery['id'] ?> — <?= fd_h($gallery['client_name']) ?></h3>
            <div class="fd-grid">
                <div class="fd-stat"><small>Photo Order</small><strong><?= fd_h($gallery['order_number'] ?? ('#' . (int)$gallery['photo_order_id'])) ?></strong></div>
                <div class="fd-stat"><small>Gallery Status</small><strong><?= fd_h(strtoupper((string)$gallery['status'])) ?></strong></div>
                <div class="fd-stat"><small>Photos Uploaded</small><strong><?= (int)$photoCount ?></strong></div>
                <div class="fd-stat"><small>Sale Payment</small><strong><?= $sale ? ($fullyPaid ? '100% PAID' : 'NOT FULLY PAID') : 'SALE NOT FOUND' ?></strong></div>
            </div>

            <p style="margin-top:14px">
                <strong>Total:</strong> <?= number_format($saleTotal) ?> RWF &nbsp; | &nbsp;
                <strong>Paid:</strong> <?= number_format($salePaid) ?> RWF &nbsp; | &nbsp;
                <strong>Balance:</strong> <?= number_format(max(0, $saleBalance)) ?> RWF
            </p>

            <div class="fd-actions">
                <a class="fd-client-link" href="<?= fd_h(fd_client_url((string)$gallery['access_token'])) ?>" target="_blank" rel="noopener">✓ Open delivery link</a>
            </div>

            <?php if (strtolower((string)$gallery['status']) !== 'published'): ?>
                <hr>
                <h4>Upload Final Edited Photos</h4>
                <p class="fd-muted">JPG, PNG cyangwa WEBP. Ntarengwa 20MB kuri buri foto. RAW files ntizemewe.</p>

                <form id="fdUploadForm" method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="gallery_id" value="<?= (int)$gallery['id'] ?>">
                    <input id="fdPhotoFiles" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>

                    <div id="fdUploadProgress" class="fd-progress">
                        <div style="display:flex;justify-content:space-between;gap:10px;margin-bottom:8px">
                            <strong id="fdUploadStatus">Preparing upload...</strong>
                            <strong id="fdUploadPercent">0%</strong>
                        </div>
                        <div class="fd-progress-track"><div id="fdUploadBar" class="fd-progress-bar"></div></div>
                        <small id="fdUploadDetails" style="display:block;margin-top:8px">Keep this page open until upload finishes.</small>
                    </div>

                    <div class="fd-actions">
                        <button id="fdUploadButton" class="fd-upload-btn" type="submit">Upload Photos</button>
                    </div>
                </form>

                <hr>
                <h4>Publish Gallery</h4>
                <?php if (!$sale): ?>
                    <div class="fd-alert fd-error">Sale ya Photo Editing Order ntiyabonetse. Reba ihuriro rya Sales na Photo Order.</div>
                <?php elseif (!$fullyPaid): ?>
                    <div class="fd-alert fd-error">Publish irafunze kugeza Sale yishyuwe 100%. Balance isigaye: <?= number_format(max(0, $saleBalance)) ?> RWF.</div>
                <?php elseif ($photoCount < 1): ?>
                    <div class="fd-alert fd-error">Payment yemejwe. Banza ushyiremo nibura ifoto imwe; Publish button izakwereka ubutumwa niba nta foto irimo.</div>
                <?php endif; ?>

                <form method="post" class="fd-actions">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="publish">
                    <input type="hidden" name="gallery_id" value="<?= (int)$gallery['id'] ?>">
                    <!-- Button remains clickable when paid; server explains if no photos exist. -->
                    <button class="fd-publish-btn" type="submit" <?= !$fullyPaid ? 'disabled' : '' ?>>Publish Gallery</button>
                </form>
            <?php else: ?>
                <div class="fd-alert fd-success" style="margin-top:15px">Iyi gallery yamaze gusohoka.</div>
            <?php endif; ?>

            <?php if ($photos): ?>
                <form method="post" class="fd-actions" style="margin-top:18px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="generate_previews">
                    <input type="hidden" name="gallery_id" value="<?= (int)$gallery['id'] ?>">
                    <button type="submit" class="fd-upload-btn">Optimize Existing Photos for Faster Viewing</button>
                </form>
                <p class="fd-muted">Ibi bikora thumbnails ntoya zo kureberaho; originals ntizihinduka.</p>
                <h4 style="margin-top:22px">Uploaded Photos</h4>
                <div class="fd-photo-grid">
                    <?php foreach ($photos as $photo): ?>
                        <?php
                            $previewUrl = 'final-delivery.php?gallery=' . (int)$gallery['id'] . '&admin_preview=' . (int)$photo['id'];
                        ?>
                        <div class="fd-photo">
                            <img src="<?= fd_h($previewUrl) ?>" alt="<?= fd_h($photo['original_name']) ?>" loading="lazy">
                            <div class="fd-photo-name"><?= fd_h($photo['original_name']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="card fd-card">
        <h3>Existing Delivery Galleries</h3>
        <div class="fd-table-wrap">
            <table class="fd-table">
                <thead>
                    <tr><th>Client</th><th>Order</th><th>Photos</th><th>Status</th><th>Manage</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($existingGalleries as $item): ?>
                        <tr>
                            <td><?= fd_h($item['client_name']) ?></td>
                            <td><?= fd_h($item['order_number'] ?? ('#' . (int)$item['id'])) ?></td>
                            <td><?= (int)$item['photo_count'] ?></td>
                            <td><?= fd_h($item['status']) ?></td>
                            <td><a class="fd-open-btn" href="?gallery=<?= (int)$item['id'] ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$existingGalleries): ?>
                        <tr><td colspan="5">No delivery galleries found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
(function () {
    const form = document.getElementById('fdUploadForm');
    if (!form) return;

    const input = document.getElementById('fdPhotoFiles');
    const button = document.getElementById('fdUploadButton');
    const panel = document.getElementById('fdUploadProgress');
    const bar = document.getElementById('fdUploadBar');
    const percent = document.getElementById('fdUploadPercent');
    const status = document.getElementById('fdUploadStatus');
    const details = document.getElementById('fdUploadDetails');

    function formatBytes(bytes) {
        if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function showFailure(message) {
        status.textContent = 'Upload failed.';
        details.textContent = message;
        button.disabled = false;
        button.textContent = 'Try Upload Again';
        input.disabled = false;
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const files = Array.from(input.files || []);
        if (!files.length) {
            alert('Banza uhitemo amafoto.');
            return;
        }

        const oversized = files.filter(file => file.size > 20 * 1024 * 1024);
        if (oversized.length) {
            alert('Hari amafoto arenze 20MB. Gabanya size yayo cyangwa uyohereze ari munsi ya 20MB kuri buri foto.');
            return;
        }

        const csrf = form.querySelector('input[name="csrf"]');
        const galleryInput = form.querySelector('input[name="gallery_id"]');
        if (!csrf || !galleryInput) {
            showFailure('CSRF cyangwa Gallery ID ntibiboneka muri form. Ongera ufungure page.');
            return;
        }

        const totalBytes = files.reduce((sum, file) => sum + file.size, 0);
        const galleryId = galleryInput.value;
        const endpoint = window.location.pathname + '?gallery=' + encodeURIComponent(galleryId);
        let completedBytes = 0;

        panel.style.display = 'block';
        button.disabled = true;
        button.textContent = 'Uploading...';
        input.disabled = true;
        bar.style.width = '0%';
        percent.textContent = '0%';
        status.textContent = 'Preparing ' + files.length + ' photo(s)...';
        details.textContent = '0 MB of ' + formatBytes(totalBytes) + ' uploaded.';

        // Send one photo per HTTP request. This avoids exceeding hosting
        // post_max_size limits when many photos are selected together.
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            status.textContent = 'Uploading photo ' + (i + 1) + ' of ' + files.length + ': ' + file.name;

            const data = new FormData();
            data.append('csrf', csrf.value);
            data.append('action', 'upload');
            data.append('gallery_id', galleryId);
            data.append('photos[]', file, file.name);

            const result = await new Promise(resolve => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', endpoint, true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                xhr.upload.addEventListener('progress', function (e) {
                    if (!e.lengthComputable) return;
                    const overall = Math.min(100, Math.round(((completedBytes + e.loaded) / totalBytes) * 100));
                    bar.style.width = overall + '%';
                    percent.textContent = overall + '%';
                    details.textContent = formatBytes(completedBytes + e.loaded) + ' of ' + formatBytes(totalBytes) + ' uploaded.';
                });

                xhr.onload = function () {
                    resolve({ ok: xhr.status >= 200 && xhr.status < 400, status: xhr.status, response: xhr.responseText || '' });
                };
                xhr.onerror = function () {
                    resolve({ ok: false, status: 0, response: 'Network connection error.' });
                };
                xhr.ontimeout = function () {
                    resolve({ ok: false, status: 0, response: 'Upload timed out.' });
                };
                xhr.timeout = 300000;
                xhr.send(data);
            });

            if (!result.ok) {
                const detail = result.status
                    ? 'Server response: HTTP ' + result.status + '. ' + result.response.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220)
                    : result.response;
                showFailure('Ifoto ' + (i + 1) + ' (' + file.name + ') ntiyoherejwe. ' + detail + ' Amafoto yabanje koherezwa yagumye muri gallery.');
                return;
            }

            completedBytes += file.size;
            const overall = Math.round((completedBytes / totalBytes) * 100);
            bar.style.width = overall + '%';
            percent.textContent = overall + '%';
            details.textContent = formatBytes(completedBytes) + ' of ' + formatBytes(totalBytes) + ' uploaded.';
        }

        status.textContent = 'All photos uploaded.';
        details.textContent = 'Upload yarangiye. Turongera gufungura gallery...';
        bar.style.width = '100%';
        percent.textContent = '100%';
        window.location.href = endpoint;
    });
})();
</script>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>

