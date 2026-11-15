<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';

$pdo = db();

/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
*/
function response(bool $success, string $message = '', array $extra = []): void
{
    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| ONLY POST
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response(false, 'Invalid request method.');
}

/*
|--------------------------------------------------------------------------
| GET POST DATA
|--------------------------------------------------------------------------
*/
$token = trim((string)($_POST['token'] ?? ''));

$photoId = (int)($_POST['photo_id'] ?? 0);

$action = strtolower(
    trim((string)($_POST['action'] ?? ''))
);

/*
|--------------------------------------------------------------------------
| VALIDATE TOKEN
|--------------------------------------------------------------------------
*/
if (
    $token === '' ||
    !preg_match('/^[a-f0-9]{64}$/i', $token)
) {
    response(false, 'Invalid gallery token.');
}

/*
|--------------------------------------------------------------------------
| VALIDATE PHOTO
|--------------------------------------------------------------------------
*/
if ($photoId <= 0) {
    response(false, 'Invalid photo.');
}

/*
|--------------------------------------------------------------------------
| VALIDATE ACTION
|--------------------------------------------------------------------------
*/
if (!in_array($action, ['add', 'remove'], true)) {
    response(false, 'Invalid selection action.');
}

/*
|--------------------------------------------------------------------------
| LOAD GALLERY
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        id,
        status,
        expires_at
    FROM photo_galleries
    WHERE access_token = ?
    LIMIT 1
");

$stmt->execute([$token]);

$gallery = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$gallery) {
    response(false, 'Gallery not found.');
}

$galleryId = (int)$gallery['id'];

/*
|--------------------------------------------------------------------------
| CHECK STATUS
|--------------------------------------------------------------------------
*/
if ($gallery['status'] !== 'active') {

    if ($gallery['status'] === 'submitted') {
        response(
            false,
            'This selection has already been submitted.'
        );
    }

    if ($gallery['status'] === 'expired') {
        response(
            false,
            'This gallery has expired.'
        );
    }

    if ($gallery['status'] === 'closed') {
        response(
            false,
            'This gallery is closed.'
        );
    }

    response(
        false,
        'This gallery is not active.'
    );
}

/*
|--------------------------------------------------------------------------
| CHECK EXPIRATION
|--------------------------------------------------------------------------
*/
if (
    !empty($gallery['expires_at']) &&
    strtotime($gallery['expires_at']) <= time()
) {

    $stmt = $pdo->prepare("
        UPDATE photo_galleries
        SET status = 'expired'
        WHERE id = ?
    ");

    $stmt->execute([$galleryId]);

    response(
        false,
        'Your selection time has expired.'
    );
}

/*
|--------------------------------------------------------------------------
| MAKE SURE PHOTO BELONGS TO THIS GALLERY
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        id,
        gallery_id,
        photo_number
    FROM gallery_photos
    WHERE id = ?
      AND gallery_id = ?
    LIMIT 1
");

$stmt->execute([
    $photoId,
    $galleryId
]);

$photo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$photo) {
    response(
        false,
        'Invalid photo for this gallery.'
    );
}

/*
|--------------------------------------------------------------------------
| ADD SELECTION
|--------------------------------------------------------------------------
*/
if ($action === 'add') {

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO photo_selections
        (
            gallery_id,
            gallery_photo_id
        )
        VALUES
        (
            ?,
            ?
        )
    ");

    $stmt->execute([
        $galleryId,
        $photoId
    ]);
}

/*
|--------------------------------------------------------------------------
| REMOVE SELECTION
|--------------------------------------------------------------------------
*/
if ($action === 'remove') {

    $stmt = $pdo->prepare("
        DELETE FROM photo_selections
        WHERE gallery_id = ?
          AND gallery_photo_id = ?
    ");

    $stmt->execute([
        $galleryId,
        $photoId
    ]);
}

/*
|--------------------------------------------------------------------------
| GET UPDATED COUNT
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM photo_selections
    WHERE gallery_id = ?
");

$stmt->execute([
    $galleryId
]);

$count = (int)$stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
*/
response(
    true,
    $action === 'add'
        ? 'Photo selected.'
        : 'Photo removed.',
    [
        'selected_count' => $count,
        'photo_id' => $photoId,
        'action' => $action
    ]
);