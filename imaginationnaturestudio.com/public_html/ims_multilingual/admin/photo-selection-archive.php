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
| CSRF protection
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['photo_selection_archive_token']) ||
    !hash_equals(
        $_SESSION['photo_selection_archive_token'],
        $_POST['csrf_token'] ?? ''
    )
) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}


/*
|--------------------------------------------------------------------------
| POST only
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
*/

$galleryId = filter_input(
    INPUT_POST,
    'gallery_id',
    FILTER_VALIDATE_INT
);

if (!$galleryId || $galleryId <= 0) {

    $_SESSION['flash_error'] =
        'Invalid gallery ID.';

    header(
        'Location: photo-selection.php'
    );

    exit;
}


try {

    /*
    |--------------------------------------------------------------------------
    | Get gallery
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            client_name,
            title,
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
            'Gallery not found.';

        header(
            'Location: photo-selection.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Already archived?
    |--------------------------------------------------------------------------
    */

    if (!empty($gallery['archived_at'])) {

        $_SESSION['flash_error'] =
            'This gallery is already archived.';

        header(
            'Location: photo-selection.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Load all gallery photos
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
    | Load selected photo IDs
    |--------------------------------------------------------------------------
    |
    | These are the JPGs that MUST remain.
    |
    */

    $stmt = $pdo->prepare("
        SELECT
            gallery_photo_id
        FROM photo_selections
        WHERE gallery_id = ?
    ");

    $stmt->execute([
        $galleryId
    ]);


    $selectedPhotoIds = [];


    while (
        $row =
        $stmt->fetch(PDO::FETCH_ASSOC)
    ) {

        $selectedPhotoIds[
            (int)$row['gallery_photo_id']
        ] = true;
    }


    /*
    |--------------------------------------------------------------------------
    | Counters
    |--------------------------------------------------------------------------
    */

    $deletedOriginals = 0;

    $deletedUnselectedPreviews = 0;

    $keptSelectedPreviews = 0;


    /*
    |--------------------------------------------------------------------------
    | Process photos
    |--------------------------------------------------------------------------
    */

    foreach (
        $photos
        as $photo
    ) {

        $photoId =
            (int)$photo['id'];


        /*
        |--------------------------------------------------------------------------
        | DELETE ORIGINAL / RAW
        |--------------------------------------------------------------------------
        |
        | RAW files are no longer needed after archive.
        |
        */

        if (
            !empty(
                $photo['original_path']
            )
        ) {

            $originalPath =
                (string)$photo['original_path'];


            /*
            |--------------------------------------------------------------------------
            | Convert relative path to absolute server path
            |--------------------------------------------------------------------------
            */

            if (
                !preg_match(
                    '#^/#',
                    $originalPath
                )
            ) {

                $originalPath =
                    dirname(__DIR__)
                    . '/'
                    . ltrim(
                        $originalPath,
                        '/'
                    );
            }


            if (
                is_file(
                    $originalPath
                )
            ) {

                if (
                    @unlink(
                        $originalPath
                    )
                ) {

                    $deletedOriginals++;
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | PROCESS PREVIEW / JPG
        |--------------------------------------------------------------------------
        */

        if (
            !empty(
                $photo['preview_path']
            )
        ) {

            $previewPath =
                (string)$photo['preview_path'];


            /*
            |--------------------------------------------------------------------------
            | Convert relative path to absolute server path
            |--------------------------------------------------------------------------
            */

            if (
                !preg_match(
                    '#^/#',
                    $previewPath
                )
            ) {

                $previewPath =
                    dirname(__DIR__)
                    . '/'
                    . ltrim(
                        $previewPath,
                        '/'
                    );
            }


            /*
            |--------------------------------------------------------------------------
            | SELECTED JPG
            |--------------------------------------------------------------------------
            |
            | KEEP IT.
            |
            */

            if (
                isset(
                    $selectedPhotoIds[
                        $photoId
                    ]
                )
            ) {

                if (
                    is_file(
                        $previewPath
                    )
                ) {

                    $keptSelectedPreviews++;
                }


            } else {

                /*
                |--------------------------------------------------------------------------
                | UNSELECTED JPG
                |--------------------------------------------------------------------------
                |
                | Delete it to save storage.
                |
                */

                if (
                    is_file(
                        $previewPath
                    )
                ) {

                    if (
                        @unlink(
                            $previewPath
                        )
                    ) {

                        $deletedUnselectedPreviews++;
                    }
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Remove ORIGINAL directory
    |--------------------------------------------------------------------------
    |
    | All RAW/original files should now be gone.
    |
    */

    $originalDir =
        dirname(__DIR__)
        . '/storage/photo-selection/originals/'
        . $galleryId;


    if (
        is_dir(
            $originalDir
        )
    ) {

        $files =
            scandir(
                $originalDir
            );


        if (
            $files !== false
        ) {

            foreach (
                $files
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


        /*
        |--------------------------------------------------------------------------
        | Remove directory if empty
        |--------------------------------------------------------------------------
        */

        @rmdir(
            $originalDir
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Preview directory
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | We MUST NOT remove this directory if selected JPGs
    | are still inside it.
    |
    */

    $previewDir =
        dirname(__DIR__)
        . '/uploads/photo-selection/previews/'
        . $galleryId;


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

            $realFiles = [];


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


                $realFiles[] =
                    $file;
            }


            /*
            |--------------------------------------------------------------------------
            | Remove directory only if completely empty
            |--------------------------------------------------------------------------
            */

            if (
                count(
                    $realFiles
                ) === 0
            ) {

                @rmdir(
                    $previewDir
                );
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Mark gallery as archived
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE photo_galleries
        SET archived_at = NOW()
        WHERE id = ?
          AND archived_at IS NULL
    ");

    $stmt->execute([
        $galleryId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Success message
    |--------------------------------------------------------------------------
    */

    $_SESSION['flash_success'] =
        'Gallery for "'
        . $gallery['client_name']
        . '" was archived successfully. '

        . $deletedOriginals
        . ' RAW/original files deleted, '

        . $deletedUnselectedPreviews
        . ' unselected JPG/preview files deleted, '

        . $keptSelectedPreviews
        . ' selected JPG files kept. '

        . 'Client and booking records were preserved.';


    /*
    |--------------------------------------------------------------------------
    | Back to Photo Selection
    |--------------------------------------------------------------------------
    */

    header(
        'Location: photo-selection.php'
    );

    exit;


} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Log technical error
    |--------------------------------------------------------------------------
    */

    error_log(
        'Photo Selection Archive Error: '
        . $e->getMessage()
    );


    /*
    |--------------------------------------------------------------------------
    | User-friendly error
    |--------------------------------------------------------------------------
    */

    $_SESSION['flash_error'] =
        'Failed to archive this gallery. '
        . 'Please check the server error log.';


    header(
        'Location: photo-selection.php'
    );

    exit;
}