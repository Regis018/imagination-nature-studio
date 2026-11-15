<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

require_admin();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['photo_selection_delete_token']) ||
    !hash_equals(
        $_SESSION['photo_selection_delete_token'],
        $_POST['csrf_token'] ?? ''
    )
) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}


/*
|--------------------------------------------------------------------------
| POST ONLY
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}


/*
|--------------------------------------------------------------------------
| Gallery ID
|--------------------------------------------------------------------------
|
| We accept both gallery_id and id to avoid mismatch with older forms.
|
*/

$galleryId = filter_input(
    INPUT_POST,
    'gallery_id',
    FILTER_VALIDATE_INT
);

if (!$galleryId) {

    $galleryId = filter_input(
        INPUT_POST,
        'id',
        FILTER_VALIDATE_INT
    );
}


if (!$galleryId || $galleryId <= 0) {

    $_SESSION['flash_error'] =
        'Invalid gallery ID.';

    header(
        'Location: photo-selection-archived.php'
    );

    exit;
}


try {

    /*
    |--------------------------------------------------------------------------
    | LOAD GALLERY
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            client_name,
            title,
            booking_id,
            archived_at
        FROM photo_galleries
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $galleryId
    ]);

    $gallery =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$gallery) {

        $_SESSION['flash_error'] =
            'Gallery not found. It may already have been deleted.';

        header(
            'Location: photo-selection-archived.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | ONLY ALLOW DELETE FROM ARCHIVED
    |--------------------------------------------------------------------------
    |
    | This protects active galleries from being accidentally deleted
    | through this handler.
    |
    */

    if (empty($gallery['archived_at'])) {

        $_SESSION['flash_error'] =
            'Only archived galleries can be permanently deleted here.';

        header(
            'Location: photo-selection.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | LOAD ALL FILE PATHS BEFORE DATABASE DELETE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            original_path,
            preview_path
        FROM gallery_photos
        WHERE gallery_id = ?
    ");

    $stmt->execute([
        $galleryId
    ]);

    $photos =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | Build file list
    |--------------------------------------------------------------------------
    */

    $filesToDelete = [];


    foreach (
        $photos
        as $photo
    ) {

        /*
        |--------------------------------------------------------------------------
        | Original / RAW
        |--------------------------------------------------------------------------
        */

        if (
            !empty(
                $photo['original_path']
            )
        ) {

            $path =
                (string)$photo['original_path'];


            if (
                !preg_match(
                    '#^/#',
                    $path
                )
            ) {

                $path =
                    dirname(__DIR__)
                    . '/'
                    . ltrim(
                        $path,
                        '/'
                    );
            }


            $filesToDelete[] =
                $path;
        }


        /*
        |--------------------------------------------------------------------------
        | Preview / JPG
        |--------------------------------------------------------------------------
        */

        if (
            !empty(
                $photo['preview_path']
            )
        ) {

            $path =
                (string)$photo['preview_path'];


            if (
                !preg_match(
                    '#^/#',
                    $path
                )
            ) {

                $path =
                    dirname(__DIR__)
                    . '/'
                    . ltrim(
                        $path,
                        '/'
                    );
            }


            $filesToDelete[] =
                $path;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DIRECTORIES
    |--------------------------------------------------------------------------
    */

    $originalDir =
        dirname(__DIR__)
        . '/storage/photo-selection/originals/'
        . $galleryId;


    $previewDir =
        dirname(__DIR__)
        . '/uploads/photo-selection/previews/'
        . $galleryId;


    /*
    |--------------------------------------------------------------------------
    | DATABASE TRANSACTION
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | DELETE PHOTO SELECTIONS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        DELETE FROM photo_selections
        WHERE gallery_id = ?
    ");

    $stmt->execute([
        $galleryId
    ]);


    /*
    |--------------------------------------------------------------------------
    | DELETE GALLERY PHOTOS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        DELETE FROM gallery_photos
        WHERE gallery_id = ?
    ");

    $stmt->execute([
        $galleryId
    ]);


    /*
    |--------------------------------------------------------------------------
    | DELETE GALLERY
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | Customer and booking are NOT touched.
    |
    */

    $stmt = $pdo->prepare("
        DELETE FROM photo_galleries
        WHERE id = ?
          AND archived_at IS NOT NULL
    ");

    $stmt->execute([
        $galleryId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Make sure gallery was actually deleted
    |--------------------------------------------------------------------------
    */

    if (
        $stmt->rowCount() !== 1
    ) {

        throw new RuntimeException(
            'Gallery was not deleted from database.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | COMMIT DATABASE
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | DELETE FILES AFTER DATABASE COMMIT
    |--------------------------------------------------------------------------
    |
    | Database is now safely deleted.
    |
    */

    $deletedFiles = 0;


    foreach (
        $filesToDelete
        as $file
    ) {

        if (
            is_file(
                $file
            )
        ) {

            if (
                @unlink(
                    $file
                )
            ) {

                $deletedFiles++;
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | REMOVE ORIGINAL DIRECTORY
    |--------------------------------------------------------------------------
    */

    if (
        is_dir(
            $originalDir
        )
    ) {

        $remaining =
            scandir(
                $originalDir
            );


        if (
            $remaining !== false
        ) {

            foreach (
                $remaining
                as $file
            ) {

                if (
                    $file === '.'
                    ||
                    $file === '..'
                ) {
                    continue;
                }


                $path =
                    $originalDir
                    . '/'
                    . $file;


                if (
                    is_file(
                        $path
                    )
                ) {

                    @unlink(
                        $path
                    );
                }
            }
        }


        @rmdir(
            $originalDir
        );
    }


    /*
    |--------------------------------------------------------------------------
    | REMOVE PREVIEW DIRECTORY
    |--------------------------------------------------------------------------
    */

    if (
        is_dir(
            $previewDir
        )
    ) {

        $remaining =
            scandir(
                $previewDir
            );


        if (
            $remaining !== false
        ) {

            foreach (
                $remaining
                as $file
            ) {

                if (
                    $file === '.'
                    ||
                    $file === '..'
                ) {
                    continue;
                }


                $path =
                    $previewDir
                    . '/'
                    . $file;


                if (
                    is_file(
                        $path
                    )
                ) {

                    @unlink(
                        $path
                    );
                }
            }
        }


        @rmdir(
            $previewDir
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    $_SESSION['flash_success'] =
        'Archived gallery for "'
        . $gallery['client_name']
        . '" was permanently deleted successfully. '
        . $deletedFiles
        . ' photo files were removed. '
        . 'Customer and booking records were preserved.';


    /*
    |--------------------------------------------------------------------------
    | BACK TO ARCHIVED
    |--------------------------------------------------------------------------
    */

    header(
        'Location: photo-selection-archived.php'
    );

    exit;


} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | ROLLBACK
    |--------------------------------------------------------------------------
    */

    if (
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();
    }


    /*
    |--------------------------------------------------------------------------
    | LOG ERROR
    |--------------------------------------------------------------------------
    */

    error_log(
        'Photo Selection Permanent Delete Error: '
        . $e->getMessage()
    );


    /*
    |--------------------------------------------------------------------------
    | USER MESSAGE
    |--------------------------------------------------------------------------
    */

    $_SESSION['flash_error'] =
        'Delete failed. Nothing was removed from the database. '
        . 'Please check the server error log.';


    header(
        'Location: photo-selection-archived.php'
    );

    exit;
}