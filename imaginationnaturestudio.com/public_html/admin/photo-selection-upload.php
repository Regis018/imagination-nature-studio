<?php
declare(strict_types=1);

/**
 * ============================================================
 * IMAGINATION NATURE STUDIO
 * ONLINE PHOTO SELECTION SYSTEM
 *
 * STEP 3.1 — RAW / JPG BATCH UPLOAD
 *
 * - Select many files at once
 * - Browser uploads ONE FILE AT A TIME
 * - Prevents Cloudflare 413 Payload Too Large
 * - RAW original remains untouched
 * - Embedded JPEG preview is extracted from RAW
 * - Preview resized + watermarked
 * ============================================================
 */

require_once __DIR__ . '/../includes/db.php';

$pdo = db();

$galleryId = (int)($_GET['id'] ?? $_POST['gallery_id'] ?? 0);

if ($galleryId <= 0) {
    http_response_code(400);
    exit('Invalid gallery ID.');
}


/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$message = '';
$error   = '';


/*
|--------------------------------------------------------------------------
| Get Gallery
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM photo_galleries
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$galleryId]);

$gallery = $stmt->fetch();

if (!$gallery) {
    http_response_code(404);
    exit('Gallery not found.');
}


/*
|--------------------------------------------------------------------------
| Directories
|--------------------------------------------------------------------------
*/

$storageRoot =
    dirname(__DIR__)
    . '/storage/photo-selection';

$originalDir =
    $storageRoot
    . '/originals/'
    . $galleryId;

$previewDir =
    __DIR__
    . '/../uploads/photo-selection/previews/'
    . $galleryId;


/*
|--------------------------------------------------------------------------
| Create directories
|--------------------------------------------------------------------------
*/

if (!is_dir($originalDir)) {

    if (
        !mkdir($originalDir, 0750, true)
        && !is_dir($originalDir)
    ) {
        exit(
            'Could not create original storage directory.'
        );
    }
}

if (!is_dir($previewDir)) {

    if (
        !mkdir($previewDir, 0755, true)
        && !is_dir($previewDir)
    ) {
        exit(
            'Could not create preview directory.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Block direct access to original storage
|--------------------------------------------------------------------------
*/

$storageHtaccess =
    dirname(__DIR__)
    . '/storage/.htaccess';

if (!file_exists($storageHtaccess)) {

    @file_put_contents(
        $storageHtaccess,
        "Options -Indexes\n"
        . "<FilesMatch \".*\">\n"
        . "    Require all denied\n"
        . "</FilesMatch>\n"
    );
}


/*
|--------------------------------------------------------------------------
| Allowed RAW extensions
|--------------------------------------------------------------------------
*/

$allowedRawExtensions = [

    'cr2',
    'cr3',
    'crw',

    'nef',
    'nrw',

    'arw',
    'sr2',
    'srf',

    'raf',

    'dng',

    'rw2',
    'rwl',

    'orf',

    'pef',

    'srw',

    'mrw',

    '3fr',

    'iiq',

    'erf',

    'k25',
    'kdc',

    'mef',

    'mos',

    'x3f',
];


/*
|--------------------------------------------------------------------------
| Allowed JPG extensions
|--------------------------------------------------------------------------
*/

$allowedJpgExtensions = [
    'jpg',
    'jpeg',
];


/*
|--------------------------------------------------------------------------
| Detect AJAX upload
|--------------------------------------------------------------------------
*/

$isAjaxUpload =
    isset($_POST['ajax_upload'])
    && $_POST['ajax_upload'] === '1';


/*
|--------------------------------------------------------------------------
| JSON response helper
|--------------------------------------------------------------------------
*/

function jsonResponse(
    bool $success,
    string $message,
    array $extra = []
): never {

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message,
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Clean original filename
|--------------------------------------------------------------------------
*/

function cleanOriginalFilename(
    string $filename
): string {

    $filename = basename($filename);

    $filename = preg_replace(
        '/[\/\\\\:*?"<>|]+/',
        '_',
        $filename
    );

    $filename = trim($filename);

    if ($filename === '') {
        $filename = 'photo';
    }

    return $filename;
}


/*
|--------------------------------------------------------------------------
| Extract largest embedded JPEG from RAW
|--------------------------------------------------------------------------
|
| CR3/RAW files can contain embedded JPEG previews.
| We scan for JPEG SOI/EOI markers and choose the
| largest valid JPEG candidate.
|
*/

function extractLargestEmbeddedJpeg(
    string $source
): ?string {

    $data = @file_get_contents($source);

    if ($data === false || $data === '') {
        return null;
    }

    $dataLength = strlen($data);

    $searchFrom = 0;

    $bestJpeg = null;
    $bestScore = 0;

    while (true) {

        $start = strpos(
            $data,
            "\xFF\xD8\xFF",
            $searchFrom
        );

        if ($start === false) {
            break;
        }


        $end = strpos(
            $data,
            "\xFF\xD9",
            $start + 3
        );

        if ($end === false) {
            break;
        }


        $end += 2;


        if (
            $end <= $start
            || $end > $dataLength
        ) {

            $searchFrom =
                $start + 3;

            continue;
        }


        $candidateLength =
            $end - $start;


        /*
        |--------------------------------------------------------------------------
        | Ignore tiny thumbnails
        |--------------------------------------------------------------------------
        */

        if ($candidateLength < 5000) {

            $searchFrom =
                $start + 3;

            continue;
        }


        $candidate =
            substr(
                $data,
                $start,
                $candidateLength
            );


        /*
        |--------------------------------------------------------------------------
        | Validate JPEG
        |--------------------------------------------------------------------------
        */

        $info =
            @getimagesizefromstring(
                $candidate
            );


        if ($info !== false) {

            $width =
                (int)($info[0] ?? 0);

            $height =
                (int)($info[1] ?? 0);


            /*
            |--------------------------------------------------------------------------
            | Prefer the largest image dimensions
            |--------------------------------------------------------------------------
            */

            $score =
                ($width * $height * 1000)
                + $candidateLength;


            if (
                $width > 0
                && $height > 0
                && $score > $bestScore
            ) {

                $bestScore = $score;

                $bestJpeg = $candidate;
            }
        }


        $searchFrom = $end;
    }


    unset($data);

    return $bestJpeg;
}


/*
|--------------------------------------------------------------------------
| Create watermarked preview from JPEG binary
|--------------------------------------------------------------------------
*/

function createPreviewFromJpegBinary(
    string $jpegBinary,
    string $destination,
    string $watermarkText
): bool {

    try {

        if (
            !function_exists(
                'imagecreatefromstring'
            )
        ) {
            return false;
        }


        $src =
            @imagecreatefromstring(
                $jpegBinary
            );


        if (!$src) {
            return false;
        }


        $width =
            imagesx($src);

        $height =
            imagesy($src);


        if (
            $width <= 0
            || $height <= 0
        ) {

            imagedestroy($src);

            return false;
        }


        /*
        |--------------------------------------------------------------------------
        | Maximum preview size
        |--------------------------------------------------------------------------
        */

        $maxWidth  = 1600;
        $maxHeight = 1600;


        $scale = min(
            1,
            $maxWidth / $width,
            $maxHeight / $height
        );


        $newWidth =
            max(
                1,
                (int)round(
                    $width * $scale
                )
            );


        $newHeight =
            max(
                1,
                (int)round(
                    $height * $scale
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Create preview canvas
        |--------------------------------------------------------------------------
        */

        $preview =
            imagecreatetruecolor(
                $newWidth,
                $newHeight
            );


        /*
        |--------------------------------------------------------------------------
        | Resize
        |--------------------------------------------------------------------------
        */

        imagecopyresampled(
            $preview,
            $src,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $width,
            $height
        );


        /*
        |--------------------------------------------------------------------------
        | Watermark
        |--------------------------------------------------------------------------
        */

        $fontSize = 5;


        $textWidth =
            imagefontwidth(
                $fontSize
            )
            * strlen(
                $watermarkText
            );


        $textHeight =
            imagefontheight(
                $fontSize
            );


        $x =
            (int)(
                ($newWidth - $textWidth)
                / 2
            );


        $y =
            (int)(
                ($newHeight - $textHeight)
                / 2
            );


        /*
        |--------------------------------------------------------------------------
        | Shadow
        |--------------------------------------------------------------------------
        */

        $shadowColor =
            imagecolorallocatealpha(
                $preview,
                0,
                0,
                0,
                70
            );


        /*
        |--------------------------------------------------------------------------
        | Watermark color
        |--------------------------------------------------------------------------
        */

        $textColor =
            imagecolorallocatealpha(
                $preview,
                255,
                255,
                255,
                65
            );


        /*
        |--------------------------------------------------------------------------
        | Draw shadow
        |--------------------------------------------------------------------------
        */

        imagestring(
            $preview,
            $fontSize,
            $x + 2,
            $y + 2,
            $watermarkText,
            $shadowColor
        );


        /*
        |--------------------------------------------------------------------------
        | Draw watermark
        |--------------------------------------------------------------------------
        */

        imagestring(
            $preview,
            $fontSize,
            $x,
            $y,
            $watermarkText,
            $textColor
        );


        /*
        |--------------------------------------------------------------------------
        | Save JPEG
        |--------------------------------------------------------------------------
        */

        $result =
            imagejpeg(
                $preview,
                $destination,
                82
            );


        /*
        |--------------------------------------------------------------------------
        | Cleanup
        |--------------------------------------------------------------------------
        */

        imagedestroy($src);

        imagedestroy($preview);


        return $result;

    } catch (Throwable $e) {

        error_log(
            'Photo Selection Preview Error: '
            . $e->getMessage()
        );

        return false;
    }
}


/*
|--------------------------------------------------------------------------
| Create preview from normal JPG
|--------------------------------------------------------------------------
*/

function createPreviewFromJpg(
    string $source,
    string $destination,
    string $watermarkText
): bool {

    $binary =
        @file_get_contents(
            $source
        );

    if ($binary === false) {
        return false;
    }

    return createPreviewFromJpegBinary(
        $binary,
        $destination,
        $watermarkText
    );
}


/*
|--------------------------------------------------------------------------
| ONE FILE UPLOAD
|--------------------------------------------------------------------------
|
| AJAX sends one file per request.
|
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_FILES['photo'])
) {

    $file = $_FILES['photo'];


    /*
    |--------------------------------------------------------------------------
    | Upload error
    |--------------------------------------------------------------------------
    */

    if (
        !isset($file['error'])
        || $file['error'] !== UPLOAD_ERR_OK
    ) {

        $uploadErrorCode =
            (int)($file['error'] ?? -1);


        $errorMessage =
            'Upload failed. Error code: '
            . $uploadErrorCode;


        if ($isAjaxUpload) {

            jsonResponse(
                false,
                $errorMessage
            );
        }


        $error =
            $errorMessage;

    } else {


        /*
        |--------------------------------------------------------------------------
        | Temporary file
        |--------------------------------------------------------------------------
        */

        $tmpFile =
            (string)$file['tmp_name'];


        if (
            $tmpFile === ''
            || !is_uploaded_file(
                $tmpFile
            )
        ) {

            if ($isAjaxUpload) {

                jsonResponse(
                    false,
                    'Invalid uploaded file.'
                );
            }


            $error =
                'Invalid uploaded file.';

        } else {


            /*
            |--------------------------------------------------------------------------
            | Original filename
            |--------------------------------------------------------------------------
            */

            $originalFilename =
                cleanOriginalFilename(
                    (string)$file['name']
                );


            /*
            |--------------------------------------------------------------------------
            | Extension
            |--------------------------------------------------------------------------
            */

            $extension =
                strtolower(
                    pathinfo(
                        $originalFilename,
                        PATHINFO_EXTENSION
                    )
                );


            /*
            |--------------------------------------------------------------------------
            | Determine file type
            |--------------------------------------------------------------------------
            */

            $isRaw =
                in_array(
                    $extension,
                    $allowedRawExtensions,
                    true
                );


            $isJpg =
                in_array(
                    $extension,
                    $allowedJpgExtensions,
                    true
                );


            if (
                !$isRaw
                && !$isJpg
            ) {

                $errorMessage =
                    'Unsupported file type: '
                    . strtoupper(
                        $extension
                    );


                if ($isAjaxUpload) {

                    jsonResponse(
                        false,
                        $errorMessage
                    );
                }


                $error =
                    $errorMessage;

            } else {


                /*
                |--------------------------------------------------------------------------
                | Validate JPG
                |--------------------------------------------------------------------------
                */

                $validFile = true;


                if ($isJpg) {

                    $info =
                        @getimagesize(
                            $tmpFile
                        );


                    if (!$info) {

                        $validFile = false;

                    } else {

                        $mime =
                            strtolower(
                                (string)(
                                    $info['mime']
                                    ?? ''
                                )
                            );


                        if (
                            $mime !==
                            'image/jpeg'
                        ) {

                            $validFile = false;
                        }
                    }
                }


                if (!$validFile) {

                    if ($isAjaxUpload) {

                        jsonResponse(
                            false,
                            'Invalid JPG file.'
                        );
                    }


                    $error =
                        'Invalid JPG file.';

                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | Get next photo number
                    |--------------------------------------------------------------------------
                    */

                    $numberStmt =
                        $pdo->prepare("
                            SELECT COALESCE(
                                MAX(photo_number),
                                0
                            )
                            FROM gallery_photos
                            WHERE gallery_id = ?
                        ");

                    $numberStmt->execute([
                        $galleryId
                    ]);


                    $photoNumber =
                        (int)$numberStmt->fetchColumn()
                        + 1;


                    /*
                    |--------------------------------------------------------------------------
                    | Secure server filename
                    |--------------------------------------------------------------------------
                    */

                    $randomName =
                        bin2hex(
                            random_bytes(18)
                        )
                        . '.'
                        . $extension;


                    $originalPath =
                        $originalDir
                        . '/'
                        . $randomName;


                    /*
                    |--------------------------------------------------------------------------
                    | Move original
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !move_uploaded_file(
                            $tmpFile,
                            $originalPath
                        )
                    ) {

                        if ($isAjaxUpload) {

                            jsonResponse(
                                false,
                                'Could not save original file.'
                            );
                        }


                        $error =
                            'Could not save original file.';

                    } else {


                        /*
                        |--------------------------------------------------------------------------
                        | Preview filename
                        |--------------------------------------------------------------------------
                        */

                        $previewFilename =
                            'photo_'
                            . str_pad(
                                (string)$photoNumber,
                                3,
                                '0',
                                STR_PAD_LEFT
                            )
                            . '_'
                            . bin2hex(
                                random_bytes(8)
                            )
                            . '.jpg';


                        $previewPath =
                            $previewDir
                            . '/'
                            . $previewFilename;


                        /*
                        |--------------------------------------------------------------------------
                        | Watermark
                        |--------------------------------------------------------------------------
                        */

                        $watermark =
                            'IMAGINATION NATURE STUDIO'
                            . '  •  PHOTO #'
                            . str_pad(
                                (string)$photoNumber,
                                3,
                                '0',
                                STR_PAD_LEFT
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | Create preview
                        |--------------------------------------------------------------------------
                        */

                        $previewCreated =
                            false;


                        if ($isJpg) {

                            /*
                            | Normal JPG
                            */

                            $previewCreated =
                                createPreviewFromJpg(
                                    $originalPath,
                                    $previewPath,
                                    $watermark
                                );

                        } else {

                            /*
                            | RAW:
                            | extract embedded JPEG.
                            */

                            $embeddedJpeg =
                                extractLargestEmbeddedJpeg(
                                    $originalPath
                                );


                            if (
                                $embeddedJpeg !== null
                            ) {

                                $previewCreated =
                                    createPreviewFromJpegBinary(
                                        $embeddedJpeg,
                                        $previewPath,
                                        $watermark
                                    );
                            }
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Preview failed
                        |--------------------------------------------------------------------------
                        */

                        if (!$previewCreated) {

                            @unlink(
                                $originalPath
                            );

                            @unlink(
                                $previewPath
                            );


                            $errorMessage =
                                'Could not create JPG preview for '
                                . $originalFilename;


                            if ($isAjaxUpload) {

                                jsonResponse(
                                    false,
                                    $errorMessage
                                );
                            }


                            $error =
                                $errorMessage;

                        } else {


                            /*
                            |--------------------------------------------------------------------------
                            | Insert database
                            |--------------------------------------------------------------------------
                            */

                            try {

                                $insert =
                                    $pdo->prepare("
                                        INSERT INTO gallery_photos
                                        (
                                            gallery_id,
                                            photo_number,
                                            original_filename,
                                            preview_filename,
                                            original_path,
                                            preview_path
                                        )
                                        VALUES
                                        (
                                            :gallery_id,
                                            :photo_number,
                                            :original_filename,
                                            :preview_filename,
                                            :original_path,
                                            :preview_path
                                        )
                                    ");


                                $insert->execute([

                                    ':gallery_id'
                                        => $galleryId,

                                    ':photo_number'
                                        => $photoNumber,

                                    ':original_filename'
                                        => $originalFilename,

                                    ':preview_filename'
                                        => $previewFilename,

                                    ':original_path'
                                        => $originalPath,

                                    ':preview_path'
                                        => $previewPath,

                                ]);


                                /*
                                |--------------------------------------------------------------------------
                                | AJAX success
                                |--------------------------------------------------------------------------
                                */

                                if ($isAjaxUpload) {

                                    jsonResponse(
                                        true,
                                        'Uploaded successfully.',
                                        [
                                            'photo_number'
                                                => $photoNumber,

                                            'original_filename'
                                                => $originalFilename,

                                            'preview_filename'
                                                => $previewFilename,

                                            'photo_url'
                                                => SITE_URL
                                                . '/uploads/photo-selection/previews/'
                                                . rawurlencode(
                                                    (string)$galleryId
                                                )
                                                . '/'
                                                . rawurlencode(
                                                    $previewFilename
                                                ),
                                        ]
                                    );
                                }


                                $message =
                                    'Photo uploaded successfully.';

                            } catch (Throwable $e) {

                                @unlink(
                                    $originalPath
                                );

                                @unlink(
                                    $previewPath
                                );


                                error_log(
                                    'Photo Selection Upload DB Error: '
                                    . $e->getMessage()
                                );


                                $errorMessage =
                                    'Database error while saving photo.';


                                if ($isAjaxUpload) {

                                    jsonResponse(
                                        false,
                                        $errorMessage
                                    );
                                }


                                $error =
                                    $errorMessage;
                            }
                        }
                    }
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Get uploaded photos
|--------------------------------------------------------------------------
*/

$photosStmt = $pdo->prepare("
    SELECT
        id,
        photo_number,
        original_filename,
        preview_filename,
        created_at
    FROM gallery_photos
    WHERE gallery_id = ?
    ORDER BY photo_number ASC
");

$photosStmt->execute([
    $galleryId
]);

$photos =
    $photosStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Client Gallery Link
|--------------------------------------------------------------------------
*/

$clientLink =
    SITE_URL
    . '/select/gallery.php?token='
    . urlencode(
        $gallery['access_token']
    );

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
Upload Photos —
<?= htmlspecialchars(
    $gallery['title'],
    ENT_QUOTES,
    'UTF-8'
) ?>
</title>


<style>

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    font-family:
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Arial,
        sans-serif;

    background: #f5f6f8;

    color: #17181b;
}


.container {

    max-width: 1100px;

    margin: 35px auto;

    padding: 20px;
}


.card {

    background: #fff;

    border-radius: 18px;

    padding: 25px;

    border: 1px solid #e5e7eb;

    margin-bottom: 20px;

    box-shadow:
        0 8px 30px rgba(0,0,0,.05);
}


h1 {
    margin-top: 0;
}


.info {

    color: #70757d;

    font-size: 14px;

    line-height: 1.7;
}


.success {

    background: #ecfdf3;

    border: 1px solid #abefc6;

    color: #067647;

    padding: 13px;

    border-radius: 10px;

    margin-bottom: 15px;
}


.error {

    background: #fff1f1;

    border: 1px solid #ffd1d1;

    color: #b42318;

    padding: 13px;

    border-radius: 10px;

    margin-bottom: 15px;

    white-space: pre-line;
}


.upload-box {

    border: 2px dashed #cfd3d9;

    border-radius: 15px;

    padding: 35px;

    text-align: center;

    background: #fafafa;
}


.upload-note {

    color: #70757d;

    font-size: 13px;

    line-height: 1.7;

    margin-bottom: 20px;
}


input[type="file"] {

    width: 100%;

    padding: 15px;

    background: white;

    border: 1px solid #ddd;

    border-radius: 10px;

    margin-bottom: 15px;
}


button {

    background: #111;

    color: #fff;

    border: 0;

    border-radius: 10px;

    padding: 13px 22px;

    font-weight: 700;

    cursor: pointer;
}


button:disabled {

    opacity: .55;

    cursor: not-allowed;
}


.progress-wrap {

    display: none;

    margin-top: 20px;

    text-align: left;
}


.progress-text {

    display: flex;

    justify-content: space-between;

    gap: 15px;

    font-size: 14px;

    margin-bottom: 8px;
}


.progress-bar {

    width: 100%;

    height: 12px;

    background: #e5e7eb;

    border-radius: 999px;

    overflow: hidden;
}


.progress-fill {

    width: 0%;

    height: 100%;

    background: #111;

    transition: width .25s ease;
}


.current-file {

    margin-top: 8px;

    font-size: 12px;

    color: #70757d;

    word-break: break-word;
}


.upload-result {

    margin-top: 15px;

    display: none;

    padding: 12px;

    border-radius: 10px;

    font-size: 14px;
}


.upload-result.success-result {

    display: block;

    background: #ecfdf3;

    color: #067647;

    border: 1px solid #abefc6;
}


.upload-result.error-result {

    display: block;

    background: #fff1f1;

    color: #b42318;

    border: 1px solid #abefc6;
}


.gallery-link {

    width: 100%;

    padding: 12px;

    border: 1px solid #ddd;

    border-radius: 9px;

    margin-top: 8px;

    font-size: 14px;
}


.grid {

    display: grid;

    grid-template-columns:
        repeat(5, 1fr);

    gap: 12px;

    margin-top: 20px;
}


.photo {

    border: 1px solid #ddd;

    border-radius: 12px;

    overflow: hidden;

    background: #fff;
}


.photo img {

    width: 100%;

    aspect-ratio: 4 / 3;

    object-fit: cover;

    display: block;
}


.photo-info {

    padding: 10px;

    font-size: 12px;
}


.photo-info strong {

    display: block;

    margin-bottom: 5px;
}


.photo-info span {

    color: #70757d;

    word-break: break-word;
}


.raw-badge {

    display: inline-block;

    margin-top: 7px;

    padding: 4px 7px;

    border-radius: 6px;

    background: #f1f5f9;

    color: #475569;

    font-size: 11px;

    font-weight: 700;
}


@media(max-width:900px) {

    .grid {

        grid-template-columns:
            repeat(3, 1fr);
    }

}


@media(max-width:600px) {

    .container {

        margin: 10px auto;

        padding: 10px;
    }

    .grid {

        grid-template-columns:
            repeat(2, 1fr);
    }

}

</style>

</head>


<body>


<div class="container">


<div class="card">

<h1>
📸 <?= htmlspecialchars(
    $gallery['title'],
    ENT_QUOTES,
    'UTF-8'
) ?>
</h1>


<div class="info">

<strong>Client:</strong>

<?= htmlspecialchars(
    $gallery['client_name'],
    ENT_QUOTES,
    'UTF-8'
) ?>

<br>

<strong>Price:</strong>

<?= number_format(
    (float)$gallery['price_per_photo']
) ?>

RWF / photo

<br>

<strong>Selection time:</strong>

<?= (int)$gallery['duration_minutes'] ?>

minutes

</div>

</div>


<?php if ($message !== ''): ?>

<div class="success">

<?= htmlspecialchars(
    $message,
    ENT_QUOTES,
    'UTF-8'
) ?>

</div>

<?php endif; ?>


<?php if ($error !== ''): ?>

<div class="error">

<?= htmlspecialchars(
    $error,
    ENT_QUOTES,
    'UTF-8'
) ?>

</div>

<?php endif; ?>


<div class="card">

<h2>
Upload Photos
</h2>


<div class="upload-box">

<p>
Select as many RAW/JPG photos as you want.
</p>


<div class="upload-note">

<strong>Important:</strong>

You can select many files at once.

The system uploads them
<strong>one by one</strong>
to prevent Cloudflare
<strong>413 Payload Too Large</strong> errors.

<br><br>

<strong>RAW:</strong>

CR3, CR2, NEF, ARW, RAF, DNG,
RW2, ORF, PEF, SRW, RWL, 3FR,
IIQ, ERF, MEF, MOS, X3F and more.

<br>

<strong>JPG:</strong>

JPG / JPEG

<br><br>

Original RAW files remain private.
Clients receive only watermarked JPG previews.

</div>


<form
    id="uploadForm"
    enctype="multipart/form-data"
>


<input
    type="hidden"
    name="gallery_id"
    value="<?= $galleryId ?>"
>


<input
    id="photoFiles"
    type="file"
    name="photos[]"
    accept="
        .cr2,
        .cr3,
        .crw,
        .nef,
        .nrw,
        .arw,
        .sr2,
        .srf,
        .raf,
        .dng,
        .rw2,
        .rwl,
        .orf,
        .pef,
        .srw,
        .mrw,
        .3fr,
        .iiq,
        .erf,
        .k25,
        .kdc,
        .mef,
        .mos,
        .x3f,
        .jpg,
        .jpeg
    "
    multiple
    required
>


<button
    id="uploadButton"
    type="submit"
>

Upload Photos

</button>


<div
    id="progressWrap"
    class="progress-wrap"
>

<div class="progress-text">

<strong id="progressText">
Preparing...
</strong>

<strong id="progressPercent">
0%
</strong>

</div>


<div class="progress-bar">

<div
    id="progressFill"
    class="progress-fill"
></div>

</div>


<div
    id="currentFile"
    class="current-file"
>
</div>

</div>


<div
    id="uploadResult"
    class="upload-result"
>
</div>


</form>

</div>

</div>


<div class="card">

<h2>
🔗 Client Gallery Link
</h2>


<p class="info">

Send this private link to the client
after uploading the photos.

</p>


<input
    class="gallery-link"
    value="<?= htmlspecialchars(
        $clientLink,
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    readonly
    onclick="this.select()"
>

</div>


<div class="card">

<h2>

Photos Uploaded:
<?= count($photos) ?>

</h2>


<?php if (!$photos): ?>

<p class="info">

No photos uploaded yet.

</p>

<?php else: ?>


<div class="grid">


<?php foreach ($photos as $photo): ?>


<div class="photo">

<img
    src="<?= SITE_URL ?>/uploads/photo-selection/previews/<?= rawurlencode((string)$galleryId) ?>/<?= rawurlencode($photo['preview_filename']) ?>"
    alt="Photo #<?= (int)$photo['photo_number'] ?>"
>


<div class="photo-info">

<strong>

PHOTO #

<?= str_pad(
    (string)$photo['photo_number'],
    3,
    '0',
    STR_PAD_LEFT
) ?>

</strong>


<span>

<?= htmlspecialchars(
    $photo['original_filename'],
    ENT_QUOTES,
    'UTF-8'
) ?>

</span>


<?php

$displayExtension =
    strtoupper(
        pathinfo(
            (string)$photo['original_filename'],
            PATHINFO_EXTENSION
        )
    );

?>


<span class="raw-badge">

<?= htmlspecialchars(
    $displayExtension,
    ENT_QUOTES,
    'UTF-8'
) ?>

 ORIGINAL

</span>


</div>

</div>


<?php endforeach; ?>


</div>

<?php endif; ?>


</div>


</div>


<script>

const uploadForm =
    document.getElementById(
        'uploadForm'
    );


const photoFiles =
    document.getElementById(
        'photoFiles'
    );


const uploadButton =
    document.getElementById(
        'uploadButton'
    );


const progressWrap =
    document.getElementById(
        'progressWrap'
    );


const progressText =
    document.getElementById(
        'progressText'
    );


const progressPercent =
    document.getElementById(
        'progressPercent'
    );


const progressFill =
    document.getElementById(
        'progressFill'
    );


const currentFile =
    document.getElementById(
        'currentFile'
    );


const uploadResult =
    document.getElementById(
        'uploadResult'
    );


uploadForm.addEventListener(
    'submit',
    async function(event) {

        event.preventDefault();


        const files =
            Array.from(
                photoFiles.files
            );


        if (!files.length) {

            alert(
                'Please select photos first.'
            );

            return;
        }


        uploadButton.disabled = true;

        photoFiles.disabled = true;

        progressWrap.style.display =
            'block';


        uploadResult.className =
            'upload-result';


        uploadResult.style.display =
            'none';


        let successCount = 0;

        let failedCount = 0;

        const failedFiles = [];


        /*
        |--------------------------------------------------------------------------
        | Upload files one by one
        |--------------------------------------------------------------------------
        */

        for (
            let i = 0;
            i < files.length;
            i++
        ) {

            const file =
                files[i];


            const current =
                i + 1;


            const total =
                files.length;


            progressText.textContent =
                'Uploading '
                + current
                + ' / '
                + total;


            currentFile.textContent =
                file.name;


            progressPercent.textContent =
                '0%';


            progressFill.style.width =
                '0%';


            const formData =
                new FormData();


            formData.append(
                'gallery_id',
                '<?= $galleryId ?>'
            );


            formData.append(
                'ajax_upload',
                '1'
            );


            formData.append(
                'photo',
                file,
                file.name
            );


            try {

                const result =
                    await uploadSingleFile(
                        formData,
                        function(percent) {

                            progressPercent.textContent =
                                percent + '%';

                            progressFill.style.width =
                                percent + '%';
                        }
                    );


                if (
                    result
                    && result.success
                ) {

                    successCount++;

                } else {

                    failedCount++;

                    failedFiles.push(
                        file.name
                    );
                }


            } catch (error) {

                failedCount++;

                failedFiles.push(
                    file.name
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Complete
        |--------------------------------------------------------------------------
        */

        progressText.textContent =
            'Upload complete';


        progressPercent.textContent =
            '100%';


        progressFill.style.width =
            '100%';


        currentFile.textContent =
            successCount
            + ' uploaded successfully'
            + (
                failedCount > 0
                    ? ' • '
                    + failedCount
                    + ' failed'
                    : ''
            );


        /*
        |--------------------------------------------------------------------------
        | Show result
        |--------------------------------------------------------------------------
        */

        if (
            failedCount === 0
        ) {

            uploadResult.className =
                'upload-result success-result';


            uploadResult.textContent =
                '🎉 All '
                + successCount
                + ' photos uploaded successfully.';

        } else {

            uploadResult.className =
                'upload-result error-result';


            uploadResult.innerHTML =
                '<strong>'
                + successCount
                + ' uploaded.</strong><br>'
                + failedCount
                + ' failed.'
                + '<br><br>'
                + failedFiles
                    .map(
                        function(name) {

                            return '• '
                                + escapeHtml(
                                    name
                                );
                        }
                    )
                    .join(
                        '<br>'
                    );
        }


        /*
        |--------------------------------------------------------------------------
        | Re-enable controls
        |--------------------------------------------------------------------------
        */

        uploadButton.disabled =
            false;

        photoFiles.disabled =
            false;


        /*
        |--------------------------------------------------------------------------
        | Refresh after complete success
        |--------------------------------------------------------------------------
        */

        if (
            successCount > 0
            && failedCount === 0
        ) {

            setTimeout(
                function() {

                    window.location.reload();

                },
                1200
            );
        }

    }
);


/*
|--------------------------------------------------------------------------
| Upload one file
|--------------------------------------------------------------------------
*/

function uploadSingleFile(
    formData,
    onProgress
) {

    return new Promise(
        function(resolve, reject) {

            const xhr =
                new XMLHttpRequest();


            xhr.open(
                'POST',
                window.location.href,
                true
            );


            xhr.upload.addEventListener(
                'progress',
                function(event) {

                    if (
                        event.lengthComputable
                    ) {

                        const percent =
                            Math.round(
                                (
                                    event.loaded
                                    /
                                    event.total
                                )
                                * 100
                            );


                        onProgress(
                            percent
                        );
                    }
                }
            );


            xhr.onload =
                function() {

                    if (
                        xhr.status >= 200
                        && xhr.status < 300
                    ) {

                        try {

                            const response =
                                JSON.parse(
                                    xhr.responseText
                                );


                            resolve(
                                response
                            );

                        } catch (error) {

                            reject(
                                new Error(
                                    'Invalid server response.'
                                )
                            );
                        }

                    } else {

                        reject(
                            new Error(
                                'Server returned HTTP '
                                + xhr.status
                            )
                        );
                    }
                };


            xhr.onerror =
                function() {

                    reject(
                        new Error(
                            'Network error.'
                        )
                    );
                };


            xhr.ontimeout =
                function() {

                    reject(
                        new Error(
                            'Upload timeout.'
                        )
                    );
                };


            xhr.timeout = 0;


            xhr.send(
                formData
            );
        }
    );
}


/*
|--------------------------------------------------------------------------
| Escape HTML
|--------------------------------------------------------------------------
*/

function escapeHtml(
    value
) {

    return String(value)
        .replace(
            /&/g,
            '&amp;'
        )
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        )
        .replace(
            /"/g,
            '&quot;'
        )
        .replace(
            /'/g,
            '&#039;'
        );
}

</script>


</body>

</html>