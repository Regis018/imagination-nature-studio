<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';

$pdo = db();

/*
|--------------------------------------------------------------------------
| READ REQUEST DATA
|--------------------------------------------------------------------------
*/

$input = [];

/*
 * Try JSON first
 */
$rawInput = file_get_contents('php://input');

if ($rawInput !== false && trim($rawInput) !== '') {

    $decoded = json_decode(
        $rawInput,
        true
    );

    if (is_array($decoded)) {
        $input = $decoded;
    }
}

/*
 * Fallback to normal POST
 */
if (empty($input) && !empty($_POST)) {
    $input = $_POST;
}


/*
|--------------------------------------------------------------------------
| VALIDATE REQUEST
|--------------------------------------------------------------------------
*/

if (!is_array($input) || empty($input)) {

    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'message' => 'Invalid request.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| TOKEN
|--------------------------------------------------------------------------
*/

$token = trim(
    (string)($input['token'] ?? '')
);


/*
|--------------------------------------------------------------------------
| VALIDATE TOKEN
|--------------------------------------------------------------------------
*/

if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {

    http_response_code(404);

    echo json_encode([
        'ok' => false,
        'message' => 'Invalid gallery link.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| FIND GALLERY
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM photo_galleries
    WHERE access_token = ?
    LIMIT 1
");

$stmt->execute([
    $token
]);

$gallery = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$gallery) {

    http_response_code(404);

    echo json_encode([
        'ok' => false,
        'message' => 'Gallery not found.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| MUST STILL BE ACTIVE
|--------------------------------------------------------------------------
*/

if (($gallery['status'] ?? '') !== 'active') {

    echo json_encode([
        'ok' => false,
        'message' =>
            'This selection has already been submitted or expired.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| SERVER-SIDE EXPIRY CHECK
|--------------------------------------------------------------------------
*/

if (!empty($gallery['expires_at'])) {

    try {

        $now = new DateTimeImmutable('now');

        $expiresAt = new DateTimeImmutable(
            (string)$gallery['expires_at']
        );

        if ($now >= $expiresAt) {

            $expire = $pdo->prepare("
                UPDATE photo_galleries
                SET status = 'expired'
                WHERE id = ?
            ");

            $expire->execute([
                $gallery['id']
            ]);

            echo json_encode([
                'ok' => false,
                'message' =>
                    'The 2-hour selection period has expired.'
            ]);

            exit;
        }

    } catch (Throwable $e) {

        error_log(
            'Gallery expiry check error: ' .
            $e->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| COUNT SELECTED PHOTOS
|--------------------------------------------------------------------------
*/

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM photo_selections
    WHERE gallery_id = ?
");

$countStmt->execute([
    $gallery['id']
]);

$selectedCount =
    (int)$countStmt->fetchColumn();


if ($selectedCount <= 0) {

    echo json_encode([
        'ok' => false,
        'message' =>
            'Please select at least one photo.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| CALCULATE TOTAL
|--------------------------------------------------------------------------
*/

$pricePerPhoto =
    (float)($gallery['price_per_photo'] ?? 0);

$total =
    $selectedCount * $pricePerPhoto;


/*
|--------------------------------------------------------------------------
| SUBMIT TRANSACTION
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | LOCK GALLERY
    |--------------------------------------------------------------------------
    */

    $lockStmt = $pdo->prepare("
        SELECT
            id,
            status
        FROM photo_galleries
        WHERE id = ?
        FOR UPDATE
    ");

    $lockStmt->execute([
        $gallery['id']
    ]);

    $lockedGallery =
        $lockStmt->fetch(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | VERIFY LOCKED GALLERY
    |--------------------------------------------------------------------------
    */

    if (
        !$lockedGallery ||
        ($lockedGallery['status'] ?? '') !== 'active'
    ) {

        throw new RuntimeException(
            'Gallery is no longer active.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MARK SUBMITTED
    |--------------------------------------------------------------------------
    */

    $submitStmt = $pdo->prepare("
        UPDATE photo_galleries
        SET
            status = 'submitted',
            submitted_at = NOW()
        WHERE id = ?
          AND status = 'active'
    ");

    $submitStmt->execute([
        $gallery['id']
    ]);


    if ($submitStmt->rowCount() !== 1) {

        throw new RuntimeException(
            'Unable to submit gallery.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        'ok' => true,

        'message' =>
            'Selection submitted successfully.',

        'selected_count' =>
            $selectedCount,

        'total' =>
            $total

    ]);

    exit;


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Photo selection submit error: ' .
        $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([

        'ok' => false,

        'message' =>
            'Unable to submit selection. Please try again.'

    ]);

    exit;
}