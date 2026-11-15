<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

verify_csrf($_POST['csrf'] ?? '');

$galleryId = (int)($_POST['id'] ?? 0);

if ($galleryId <= 0) {
    http_response_code(400);
    exit('Invalid gallery ID.');
}

$pdo = db();

/*
|--------------------------------------------------------------------------
| Get gallery
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT *
    FROM photo_galleries
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$galleryId]);

$gallery = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$gallery) {
    http_response_code(404);
    exit('Gallery not found.');
}

/*
|--------------------------------------------------------------------------
| Get selected photos
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        gp.id,
        gp.photo_number,
        gp.original_filename,
        gp.original_path
    FROM gallery_photos gp
    INNER JOIN photo_selections ps
        ON ps.gallery_photo_id = gp.id
       AND ps.gallery_id = gp.gallery_id
    WHERE gp.gallery_id = ?
    ORDER BY gp.photo_number ASC
");

$stmt->execute([$galleryId]);

$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$photos) {
    http_response_code(400);
    exit('No selected photos found.');
}

/*
|--------------------------------------------------------------------------
| Check ZipArchive
|--------------------------------------------------------------------------
*/
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('ZIP support is not enabled on this server.');
}

/*
|--------------------------------------------------------------------------
| Gallery original directory
|--------------------------------------------------------------------------
*/
$galleryRoot = realpath(
    __DIR__ . '/../storage/photo-selection/originals/' . $galleryId
);

if (!$galleryRoot || !is_dir($galleryRoot)) {
    http_response_code(404);
    exit('Original photos directory not found.');
}

/*
|--------------------------------------------------------------------------
| Temporary ZIP
|--------------------------------------------------------------------------
*/
$tmpZip = tempnam(sys_get_temp_dir(), 'photo_selection_');

if ($tmpZip === false) {
    http_response_code(500);
    exit('Could not create temporary ZIP file.');
}

$zipPath = $tmpZip . '.zip';

@unlink($tmpZip);

$zip = new ZipArchive();

$result = $zip->open(
    $zipPath,
    ZipArchive::CREATE | ZipArchive::OVERWRITE
);

if ($result !== true) {
    @unlink($zipPath);

    http_response_code(500);
    exit('Could not create ZIP file.');
}

set_time_limit(0);

$missingFiles = [];

foreach ($photos as $photo) {

    $originalPath = (string)$photo['original_path'];

    /*
    |--------------------------------------------------------------------------
    | Security:
    | Make sure the file is inside this gallery's original directory.
    |--------------------------------------------------------------------------
    */
    $realFile = realpath($originalPath);

    if (
        !$realFile ||
        !is_file($realFile) ||
        strpos(
            $realFile,
            $galleryRoot . DIRECTORY_SEPARATOR
        ) !== 0
    ) {
        $missingFiles[] = $photo['original_filename'];
        continue;
    }

    /*
    |--------------------------------------------------------------------------
    | Safe filename inside ZIP
    |--------------------------------------------------------------------------
    */
    $originalName = basename(
        (string)$photo['original_filename']
    );

    $originalName = preg_replace(
        '/[^A-Za-z0-9._-]+/',
        '_',
        $originalName
    );

    if (!$originalName) {
        $originalName = 'photo.jpg';
    }

    /*
    |--------------------------------------------------------------------------
    | Add photo number
    | Example:
    | 001_IMG_7302.JPG
    |--------------------------------------------------------------------------
    */
    $zipFilename = sprintf(
        '%03d_%s',
        (int)$photo['photo_number'],
        $originalName
    );

    if (!$zip->addFile($realFile, $zipFilename)) {
        $zip->close();
        @unlink($zipPath);

        http_response_code(500);
        exit('Could not add a photo to the ZIP.');
    }
}

/*
|--------------------------------------------------------------------------
| If any original is missing, do not give an incomplete ZIP.
|--------------------------------------------------------------------------
*/
if (!empty($missingFiles)) {

    $zip->close();
    @unlink($zipPath);

    http_response_code(500);

    echo "Some selected original files are missing:\n\n";

    foreach ($missingFiles as $missing) {
        echo "- " . $missing . "\n";
    }

    exit;
}

/*
|--------------------------------------------------------------------------
| Finish ZIP
|--------------------------------------------------------------------------
*/
if (!$zip->close()) {
    @unlink($zipPath);

    http_response_code(500);
    exit('Could not finalize ZIP file.');
}

if (!is_file($zipPath)) {
    http_response_code(500);
    exit('ZIP file was not created.');
}

/*
|--------------------------------------------------------------------------
| Safe download filename
|--------------------------------------------------------------------------
*/
$clientName = trim(
    (string)($gallery['client_name'] ?? 'Client')
);

$clientName = preg_replace(
    '/[^A-Za-z0-9._-]+/',
    '-',
    $clientName
);

$clientName = trim($clientName, '-_.');

if (!$clientName) {
    $clientName = 'Client';
}

$downloadName =
    'Photo-Selection-' .
    $clientName .
    '-' .
    $galleryId .
    '.zip';

/*
|--------------------------------------------------------------------------
| Send ZIP to admin
|--------------------------------------------------------------------------
*/
header('Content-Type: application/zip');
header(
    'Content-Disposition: attachment; filename="' .
    $downloadName .
    '"'
);
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($zipPath);

/*
|--------------------------------------------------------------------------
| Delete temporary ZIP
|--------------------------------------------------------------------------
*/
@unlink($zipPath);

exit;