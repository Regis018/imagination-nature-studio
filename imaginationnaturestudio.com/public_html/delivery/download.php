<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$pdo = db();

$token = trim((string)($_GET['token'] ?? ''));
$photoId = filter_var($_GET['photo'] ?? null, FILTER_VALIDATE_INT);

function delivery_download_not_found(): void
{
    http_response_code(404);
    exit('Photo not found.');
}

if (!preg_match('/^[a-f0-9]{64}$/i', $token) || !$photoId || $photoId < 1) {
    delivery_download_not_found();
}

/*
 * Use the same Sale relationship resolution as delivery/index.php:
 * 1) final_delivery_galleries.sale_id
 * 2) photo_orders.sale_id
 * 3) sales.photo_order_id
 *
 * A stale gallery.sale_id must not break a valid Photo Editing Order.
 */
$sql = "
    SELECT
        g.id AS gallery_id,
        g.photo_order_id,
        po.total_amount AS order_total,
        po.amount_paid AS order_paid,
        s.id AS resolved_sale_id,
        s.total AS sale_total,
        s.amount_paid AS sale_paid,
        s.balance AS sale_balance
    FROM final_delivery_galleries g
    LEFT JOIN photo_orders po
        ON po.id = g.photo_order_id
    LEFT JOIN sales s
        ON s.id = (
            SELECT s2.id
            FROM sales s2
            WHERE (
                (g.sale_id IS NOT NULL AND s2.id = g.sale_id)
                OR (po.sale_id IS NOT NULL AND s2.id = po.sale_id)
                OR (po.id IS NOT NULL AND s2.photo_order_id = po.id)
            )
            AND LOWER(COALESCE(s2.product_name, '')) LIKE 'photo editing order%'
            ORDER BY
                CASE
                    WHEN s2.id = g.sale_id THEN 0
                    WHEN s2.id = po.sale_id THEN 1
                    ELSE 2
                END,
                s2.id DESC
            LIMIT 1
        )
    WHERE LOWER(TRIM(g.access_token)) = LOWER(TRIM(?))
      AND LOWER(TRIM(g.status)) = 'published'
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$token]);
$gallery = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$gallery) {
    delivery_download_not_found();
}

$total = $gallery['sale_total'] !== null
    ? (float)$gallery['sale_total']
    : (float)$gallery['order_total'];

$paid = $gallery['sale_paid'] !== null
    ? (float)$gallery['sale_paid']
    : (float)$gallery['order_paid'];

$balance = $gallery['sale_balance'] !== null
    ? max(0.0, (float)$gallery['sale_balance'])
    : max(0.0, $total - $paid);

/* Require both paid >= total and zero balance, same as the gallery page. */
$fullyPaid = $total > 0
    && $paid + 0.00001 >= $total
    && $balance <= 0.00001;

if (!$fullyPaid) {
    http_response_code(403);
    exit('Payment must be completed before downloading.');
}

$photoStmt = $pdo->prepare("
    SELECT original_name, stored_name, mime_type, file_size
    FROM final_delivery_photos
    WHERE id = ? AND gallery_id = ?
    LIMIT 1
");
$photoStmt->execute([(int)$photoId, (int)$gallery['gallery_id']]);
$photo = $photoStmt->fetch(PDO::FETCH_ASSOC);

if (!$photo) {
    delivery_download_not_found();
}

$storedName = basename((string)$photo['stored_name']);
$extension = strtolower(pathinfo($storedName, PATHINFO_EXTENSION));

$allowed = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
];

if (
    !isset($allowed[$extension]) ||
    !preg_match('/^[a-f0-9]{40}\.(jpg|jpeg|png|webp)$/i', $storedName) ||
    (string)$photo['mime_type'] !== $allowed[$extension]
) {
    http_response_code(415);
    exit('Unsupported file.');
}

$storageDir = dirname(__DIR__) . '/storage/final-delivery/' . (int)$gallery['gallery_id'];
$base = realpath($storageDir);
$file = $base ? realpath($base . DIRECTORY_SEPARATOR . $storedName) : false;

if (
    !$base ||
    !$file ||
    strpos($file, $base . DIRECTORY_SEPARATOR) !== 0 ||
    !is_file($file) ||
    !is_readable($file)
) {
    http_response_code(404);
    exit('File unavailable.');
}

$actualSize = filesize($file);
if ($actualSize === false || $actualSize < 1) {
    http_response_code(404);
    exit('File unavailable.');
}

$originalName = basename((string)$photo['original_name']);
$fallbackName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
if (!$fallbackName) {
    $fallbackName = 'photo-' . (int)$photoId . '.' . $extension;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $allowed[$extension]);
header('Content-Length: ' . (string)$actualSize);
header(
    'Content-Disposition: attachment; filename="' . str_replace('"', '', $fallbackName)
    . '"; filename*=UTF-8\'\'' . rawurlencode($originalName)
);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');

readfile($file);
exit;
