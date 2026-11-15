<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

require_admin();


/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
*/

header(
    'Content-Type: application/json; charset=utf-8'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);


/*
|--------------------------------------------------------------------------
| SETTINGS
|--------------------------------------------------------------------------
*/

$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];


$allowedCategories = [
    'weddings',
    'portraits',
    'editorial',
    'maternity',
    'family',
    'events',
    'commercial',
];


$maxFileSize =
    5 * 1024 * 1024;


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function gallery_json(
    bool $success,
    string $message,
    array $extra = []
): never {

    echo json_encode(
        array_merge(
            [
                'success' =>
                    $success,

                'message' =>
                    $message,
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| REQUEST
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    !== 'POST'
) {

    http_response_code(405);

    gallery_json(
        false,
        'Only POST requests are allowed.'
    );
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (
    !verify_csrf(
        $_POST['csrf'] ?? null
    )
) {

    http_response_code(403);

    gallery_json(
        false,
        'Security verification failed. Please refresh the page.'
    );
}


/*
|--------------------------------------------------------------------------
| FILES EXIST?
|--------------------------------------------------------------------------
*/

if (
    !isset($_FILES['image'])
) {

    gallery_json(
        false,
        'No photos were received.'
    );
}


if (
    !isset(
        $_FILES['image']['name']
    ) ||
    !is_array(
        $_FILES['image']['name']
    )
) {

    gallery_json(
        false,
        'The server did not receive the photos as a multiple-file upload.'
    );
}


/*
|--------------------------------------------------------------------------
| FILE COUNT
|--------------------------------------------------------------------------
*/

$fileCount =
    count(
        $_FILES['image']['name']
    );


if (
    $fileCount <= 0
) {

    gallery_json(
        false,
        'No photos selected.'
    );
}


/*
|--------------------------------------------------------------------------
| FORM DATA
|--------------------------------------------------------------------------
*/

$title =
    trim(
        (string)(
            $_POST['title'] ?? ''
        )
    );


$category =
    strtolower(
        trim(
            (string)(
                $_POST['category']
                ?? 'weddings'
            )
        )
    );


if (
    !in_array(
        $category,
        $allowedCategories,
        true
    )
) {

    $category =
        'weddings';
}


$description =
    trim(
        (string)(
            $_POST['description']
            ?? ''
        )
    );


$featured =
    !empty(
        $_POST['featured']
    )
        ? 1
        : 0;


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

try {

    $pdo = db();

} catch (Throwable $e) {

    http_response_code(500);

    gallery_json(
        false,
        'Database connection failed.'
    );
}


/*
|--------------------------------------------------------------------------
| UPLOAD DIRECTORY
|--------------------------------------------------------------------------
*/

if (
    !is_dir(
        UPLOAD_DIR
    )
) {

    if (
        !mkdir(
            UPLOAD_DIR,
            0755,
            true
        ) &&
        !is_dir(
            UPLOAD_DIR
        )
    ) {

        http_response_code(500);

        gallery_json(
            false,
            'Unable to create upload directory.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| RESULT
|--------------------------------------------------------------------------
*/

$uploaded = [];

$failed = [];


/*
|--------------------------------------------------------------------------
| PREPARE INSERT
|--------------------------------------------------------------------------
*/

try {

    $insert =
        $pdo->prepare(
            "INSERT INTO gallery
            (
                title,
                category,
                image,
                description,
                featured
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?
            )"
        );

} catch (Throwable $e) {

    http_response_code(500);

    gallery_json(
        false,
        'Unable to prepare database query.'
    );
}


/*
|--------------------------------------------------------------------------
| PROCESS ALL FILES
|--------------------------------------------------------------------------
*/

for (
    $i = 0;
    $i < $fileCount;
    $i++
) {

    $originalName =
        (string)(
            $_FILES['image']['name'][$i]
            ?? ''
        );


    $tmpName =
        (string)(
            $_FILES['image']['tmp_name'][$i]
            ?? ''
        );


    $fileSize =
        (int)(
            $_FILES['image']['size'][$i]
            ?? 0
        );


    $uploadError =
        (int)(
            $_FILES['image']['error'][$i]
            ?? UPLOAD_ERR_NO_FILE
        );


    /*
    |--------------------------------------------------------------------------
    | PHP ERROR
    |--------------------------------------------------------------------------
    */

    if (
        $uploadError !==
        UPLOAD_ERR_OK
    ) {

        switch (
            $uploadError
        ) {

            case UPLOAD_ERR_INI_SIZE:

                $error =
                    'File is larger than server upload_max_filesize.';

                break;


            case UPLOAD_ERR_FORM_SIZE:

                $error =
                    'File is larger than allowed form size.';

                break;


            case UPLOAD_ERR_PARTIAL:

                $error =
                    'File was only partially uploaded.';

                break;


            case UPLOAD_ERR_NO_FILE:

                $error =
                    'No file received.';

                break;


            case UPLOAD_ERR_NO_TMP_DIR:

                $error =
                    'Server temporary folder is missing.';

                break;


            case UPLOAD_ERR_CANT_WRITE:

                $error =
                    'Server cannot write uploaded file.';

                break;


            case UPLOAD_ERR_EXTENSION:

                $error =
                    'A server extension stopped the upload.';

                break;


            default:

                $error =
                    'Unknown upload error.';

                break;
        }


        $failed[] = [
            'file' =>
                $originalName,

            'error' =>
                $error,
        ];


        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | IS UPLOADED FILE?
    |--------------------------------------------------------------------------
    */

    if (
        $tmpName === '' ||
        !is_uploaded_file(
            $tmpName
        )
    ) {

        $failed[] = [
            'file' =>
                $originalName,

            'error' =>
                'Invalid uploaded file.',
        ];


        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | SIZE
    |--------------------------------------------------------------------------
    */

    if (
        $fileSize <= 0
    ) {

        $failed[] = [
            'file' =>
                $originalName,

            'error' =>
                'Empty file.',
        ];


        continue;
    }


    if (
        $fileSize >
        $maxFileSize
    ) {

        $failed[] = [
            'file' =>
                $originalName,

            'error' =>
                'Maximum allowed size is 5MB.',
        ];


        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | MIME
    |--------------------------------------------------------------------------
    */

    try {

        $finfo =
            new finfo(
                FILEINFO_MIME_TYPE
            );


        $mime =
            $finfo->file(
                $tmpName
            );

    } catch (Throwable $e) {

        $failed[] = [
            'file' =>
                $originalName,

            'error' =>
                'Unable to detect file type.',
        ];


        continue;
    }


    if (
        !isset(
            $allowedMimeTypes[$mime]
        )
    ) {

        $failed[] = [
            'file' =>
                $originalName,

            'error' =>
                'Unsupported image type.',
        ];


        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | VALID IMAGE
    |--------------------------------------------------------------------------
    */

    $imageInfo =
        @getimagesize(
            $tmpName
        );


    if (
        $imageInfo === false
    ) {

        $failed[] = [
            'file' =>
                $originalName,

            'error' =>
                'Not a valid image.',
        ];


        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | EXTENSION
    |--------------------------------------------------------------------------
    */

    $extension =
        $allowedMimeTypes[
            $mime
        ];


    /*
    |--------------------------------------------------------------------------
    | NEW FILE NAME
    |--------------------------------------------------------------------------
    */

    try {

        $newFileName =
            bin2hex(
                random_bytes(16)
            )
            .
            '.'
            .
            $extension;

    } catch (Throwable $e) {

        $failed[] = [
            'file' =>
                $originalName,

            'error' =>
                'Unable to generate filename.',
        ];


        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | DESTINATION
    |--------------------------------------------------------------------------
    */

    $destination =
        rtrim(
            UPLOAD_DIR,
            '/\\'
        )
        .
        DIRECTORY_SEPARATOR
        .
        $newFileName;


    /*
    |--------------------------------------------------------------------------
    | MOVE
    |--------------------------------------------------------------------------
    */

    if (
        !move_uploaded_file(
            $tmpName,
            $destination
        )
    ) {

        $failed[] = [
            'file' =>
                $originalName,

            'error' =>
                'Unable to save file on server.',
        ];


        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | TITLE
    |--------------------------------------------------------------------------
    */

    $photoTitle =
        $title;


    if (
        $photoTitle === ''
    ) {

        $photoTitle =
            pathinfo(
                $originalName,
                PATHINFO_FILENAME
            );
    }


    /*
    |--------------------------------------------------------------------------
    | DATABASE
    |--------------------------------------------------------------------------
    */

    try {

        $insert->execute([
            $photoTitle,
            $category,
            $newFileName,
            $description,
            $featured
        ]);


        $uploaded[] = [
            'file' =>
                $originalName,

            'filename' =>
                $newFileName,

            'id' =>
                (int)$pdo->lastInsertId(),
        ];

    } catch (Throwable $e) {

        /*
        |--------------------------------------------------------------------------
        | DB FAILED → REMOVE IMAGE
        |--------------------------------------------------------------------------
        */

        if (
            is_file(
                $destination
            )
        ) {

            @unlink(
                $destination
            );
        }


        $failed[] = [
            'file' =>
                $originalName,

            'error' =>
                'Database error while saving image.',
        ];
    }
}


/*
|--------------------------------------------------------------------------
| FINAL RESPONSE
|--------------------------------------------------------------------------
*/

$uploadedCount =
    count(
        $uploaded
    );


$failedCount =
    count(
        $failed
    );


if (
    $uploadedCount === 0
) {

    http_response_code(400);

    gallery_json(
        false,
        'No photos were uploaded.',
        [
            'uploaded' =>
                0,

            'failed' =>
                $failedCount,

            'errors' =>
                $failed,
        ]
    );
}


if (
    $failedCount > 0
) {

    gallery_json(
        true,
        $uploadedCount .
        ' photos uploaded successfully, ' .
        $failedCount .
        ' failed.',
        [
            'uploaded' =>
                $uploaded,

            'failed' =>
                $failed,
        ]
    );
}


gallery_json(
    true,
    $uploadedCount .
    ' photos uploaded successfully.',
    [
        'uploaded' =>
            $uploaded,

        'failed' =>
            [],
    ]
);