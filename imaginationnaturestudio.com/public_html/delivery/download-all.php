<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

function da_fail(int $status, string $message): void {
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    exit($message);
}

$token = trim((string)($_GET['token'] ?? ''));
if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
    da_fail(404, 'Gallery not found.');
}

if (!class_exists('ZipArchive')) {
    da_fail(500, 'Bulk download is not available because PHP ZipArchive is not enabled on this hosting account. Please use individual photo downloads or ask hosting support to enable the PHP zip extension.');
}

$pdo = db();
$sql = "
    SELECT
        g.id,
        g.client_name,
        po.order_number,
        po.total_amount,
        po.amount_paid AS order_paid,
        s.balance AS sale_balance,
        s.total AS sale_total,
        s.amount_paid AS sale_paid
    FROM final_delivery_galleries g
    LEFT JOIN photo_orders po ON po.id = g.photo_order_id
    LEFT JOIN sales s ON s.id = (
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
    da_fail(404, 'Gallery not found.');
}

$total = $gallery['sale_total'] !== null
    ? (float)$gallery['sale_total']
    : (float)$gallery['total_amount'];
$paid = $gallery['sale_paid'] !== null
    ? (float)$gallery['sale_paid']
    : (float)$gallery['order_paid'];
$balance = $gallery['sale_balance'] !== null
    ? max(0, (float)$gallery['sale_balance'])
    : max(0, $total - $paid);
$fullyPaid = $total > 0 && $paid + 0.00001 >= $total && $balance <= 0.00001;
if (!$fullyPaid) {
    da_fail(403, 'Downloads are locked until the order is fully paid.');
}

$galleryId = (int)$gallery['id'];
$photoStmt = $pdo->prepare("SELECT id, original_name, stored_name, mime_type FROM final_delivery_photos WHERE gallery_id = ? ORDER BY id ASC");
$photoStmt->execute([$galleryId]);
$photos = $photoStmt->fetchAll(PDO::FETCH_ASSOC);
if (!$photos) {
    da_fail(404, 'There are no photos in this gallery yet.');
}

$storageDir = realpath(__DIR__ . '/../storage/final-delivery/' . $galleryId);
if ($storageDir === false || !is_dir($storageDir)) {
    da_fail(404, 'Gallery photo folder is unavailable.');
}

$tmpZip = tempnam(sys_get_temp_dir(), 'ins_delivery_');
if ($tmpZip === false) {
    da_fail(500, 'Could not create a temporary ZIP file.');
}

$zip = new ZipArchive();
$opened = $zip->open($tmpZip, ZipArchive::OVERWRITE);
if ($opened !== true) {
    @unlink($tmpZip);
    da_fail(500, 'Could not prepare the ZIP archive.');
}

$added = 0;
$allowed = [
    'image/jpeg' => ['jpg', 'jpeg'],
    'image/png'  => ['png'],
    'image/webp' => ['webp'],
];
foreach ($photos as $photo) {
    $stored = basename((string)$photo['stored_name']);
    $mime = (string)$photo['mime_type'];
    $ext = strtolower(pathinfo($stored, PATHINFO_EXTENSION));
    if (!isset($allowed[$mime]) || !in_array($ext, $allowed[$mime], true) ||
        !preg_match('/^[a-f0-9]{40}\.(jpg|jpeg|png|webp)$/i', $stored)) {
        continue;
    }
    $path = realpath($storageDir . DIRECTORY_SEPARATOR . $stored);
    if ($path === false || strpos($path, $storageDir . DIRECTORY_SEPARATOR) !== 0 || !is_file($path) || !is_readable($path)) {
        continue;
    }

    $original = (string)$photo['original_name'];
    $original = preg_replace('/[\\\\\/\:\*\?"<>\|\x00-\x1F]/u', '_', $original) ?? '';
    $original = trim($original, " .\\t\\n\\r\\0\\x0B");
    if ($original === '') {
        $original = 'photo-' . (int)$photo['id'] . '.' . $ext;
    }
    // Prefix IDs so duplicate original filenames never overwrite each other inside the ZIP.
    $entryName = sprintf('%04d_%s', (int)$photo['id'], $original);
    if ($zip->addFile($path, $entryName)) {
        $added++;
    }
}

if ($added < 1 || !$zip->close()) {
    @unlink($tmpZip);
    da_fail(500, 'No valid photos could be added to the ZIP archive.');
}

$client = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$gallery['client_name']) ?? 'Client';
$client = trim($client, '-_');
if ($client === '') $client = 'Client';
$order = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)($gallery['order_number'] ?? '')) ?? '';
$zipName = 'Imagination-Nature-Studio-' . ($order !== '' ? $order . '-' : '') . $client . '-Photos.zip';

while (ob_get_level() > 0) {
    ob_end_clean();
}
set_time_limit(0);
header('Content-Type: application/zip');
header('Content-Length: ' . (string)filesize($tmpZip));
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
readfile($tmpZip);
@unlink($tmpZip);
exit;
