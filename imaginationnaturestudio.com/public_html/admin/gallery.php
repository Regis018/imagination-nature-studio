<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pdo = db();

$allowedCategories = [
    'weddings',
    'portraits',
    'editorial',
    'maternity',
    'family',
    'events',
    'commercial',
];


/*
|--------------------------------------------------------------------------
| DELETE IMAGE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['delete_id'])
) {

    if (
        !verify_csrf(
            $_POST['csrf'] ?? null
        )
    ) {

        flash(
            'err',
            'Invalid security token.'
        );

        redirect('gallery.php');

        exit;
    }


    $deleteId = (int)(
        $_POST['delete_id'] ?? 0
    );


    if ($deleteId > 0) {

        try {

            $stmt = $pdo->prepare(
                "SELECT image
                 FROM gallery
                 WHERE id = ?
                 LIMIT 1"
            );

            $stmt->execute([
                $deleteId
            ]);


            $image = $stmt->fetchColumn();


            if ($image) {

                $filename = basename(
                    (string)$image
                );


                $filePath =
                    rtrim(
                        UPLOAD_DIR,
                        '/\\'
                    )
                    . DIRECTORY_SEPARATOR
                    . $filename;


                if (
                    is_file($filePath)
                ) {

                    @unlink($filePath);
                }


                $delete = $pdo->prepare(
                    "DELETE FROM gallery
                     WHERE id = ?
                     LIMIT 1"
                );


                $delete->execute([
                    $deleteId
                ]);


                flash(
                    'ok',
                    'Image deleted successfully.'
                );

            } else {

                flash(
                    'err',
                    'Image not found.'
                );
            }

        } catch (Throwable $e) {

            flash(
                'err',
                'Unable to delete image.'
            );
        }
    }


    redirect('gallery.php');

    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD GALLERY
|--------------------------------------------------------------------------
*/

try {

    $rows = $pdo->query(
        "SELECT *
         FROM gallery
         ORDER BY id DESC"
    )->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $e) {

    $rows = [];

    flash(
        'err',
        'Unable to load gallery.'
    );
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

require __DIR__ .
    '/includes/admin-header.php';

?>

<style>

/* =========================================================
   UPLOAD CARD
   ========================================================= */

.gallery-upload-card {

    padding: 24px;

    margin: 20px 0;

}


/* =========================================================
   DROP ZONE
   ========================================================= */

.gallery-drop-zone {

    border: 2px dashed
        rgba(255,255,255,.22);

    border-radius: 18px;

    padding: 45px 25px;

    text-align: center;

    cursor: pointer;

    background:
        rgba(255,255,255,.025);

    transition: all .2s ease;

}


.gallery-drop-zone:hover {

    border-color:
        rgba(255,255,255,.45);

    background:
        rgba(255,255,255,.05);

}


.gallery-drop-zone.dragging {

    border-color:
        #55d77f;

    background:
        rgba(85,215,127,.08);

}


.gallery-upload-icon {

    font-size: 52px;

    margin-bottom: 10px;

}


.gallery-upload-title {

    font-size: 1.3rem;

    font-weight: 800;

}


.gallery-upload-text {

    margin-top: 8px;

    color: #999;

    line-height: 1.5;

}


.gallery-select-button {

    display: inline-block;

    margin-top: 18px;

    padding: 12px 22px;

    border-radius: 10px;

    background: #eee5dc;

    color: #171310;

    font-weight: 800;

    cursor: pointer;

}


#galleryImages {

    display: none;

}


/* =========================================================
   SELECTED COUNT
   ========================================================= */

.gallery-count {

    display: none;

    margin-top: 18px;

    padding: 12px 15px;

    border-radius: 10px;

    background:
        rgba(80,210,120,.08);

    border:
        1px solid
        rgba(80,210,120,.20);

    color: #b8e8c8;

    font-weight: 700;

}


/* =========================================================
   PREVIEW
   ========================================================= */

.gallery-preview {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fill,
            minmax(130px, 1fr)
        );

    gap: 12px;

    margin-top: 20px;

}


.gallery-preview-item {

    position: relative;

    aspect-ratio: 1 / 1;

    overflow: hidden;

    border-radius: 12px;

    background: #111;

    border:
        1px solid
        rgba(255,255,255,.08);

}


.gallery-preview-item img {

    width: 100%;

    height: 100%;

    object-fit: cover;

}


.gallery-preview-name {

    position: absolute;

    left: 0;

    right: 0;

    bottom: 0;

    padding: 8px;

    color: #fff;

    font-size: 11px;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;

    background:
        linear-gradient(
            transparent,
            rgba(0,0,0,.9)
        );

}


/* =========================================================
   PROGRESS
   ========================================================= */

.gallery-progress {

    display: none;

    margin-top: 20px;

}


.gallery-progress-bar {

    width: 100%;

    height: 14px;

    overflow: hidden;

    border-radius: 20px;

    background:
        rgba(255,255,255,.08);

}


.gallery-progress-fill {

    width: 0%;

    height: 100%;

    border-radius: 20px;

    background: #55d77f;

    transition: width .2s ease;

}


.gallery-progress-text {

    margin-top: 9px;

    color: #aaa;

    font-size: .88rem;

}


/* =========================================================
   RESULT
   ========================================================= */

.gallery-result {

    display: none;

    margin-top: 15px;

    padding: 14px 16px;

    border-radius: 10px;

    line-height: 1.6;

}


.gallery-result.success {

    display: block;

    background:
        rgba(70,210,120,.10);

    border:
        1px solid
        rgba(70,210,120,.20);

    color: #b8e8c8;

}


.gallery-result.error {

    display: block;

    background:
        rgba(220,70,70,.10);

    border:
        1px solid
        rgba(220,70,70,.20);

    color: #ffb6b6;

}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width:700px) {

    .gallery-preview {

        grid-template-columns:
            repeat(3, 1fr);

    }

}

</style>


<h1>Gallery</h1>


<?php if (
    $m = flash('err')
): ?>

    <div class="alert alert-error">

        <?= e($m) ?>

    </div>

<?php endif; ?>


<?php if (
    $m = flash('ok')
): ?>

    <div class="alert alert-success">

        <?= e($m) ?>

    </div>

<?php endif; ?>


<!-- =========================================================
     BULK UPLOAD
     ========================================================= -->

<div class="card gallery-upload-card">


    <h2 style="margin-top:0;">
        Upload Multiple Photos
    </h2>


    <p style="color:#999;">
        Select many photos at once and upload them together.
    </p>


    <form
        id="galleryUploadForm"
        method="post"
        enctype="multipart/form-data"
    >

        <?= csrf_field() ?>


        <!-- =================================================
             PHOTOS
             ================================================= -->

        <div>

            <label>
                Photos *
            </label>


            <div
                class="gallery-drop-zone"
                id="galleryDropZone"
            >

                <div class="gallery-upload-icon">
                    📸
                </div>


                <div class="gallery-upload-title">

                    Select Multiple Photos

                </div>


                <div class="gallery-upload-text">

                    Click here or drag your photos here.

                    <br>

                    You can select 10, 20, 50 or more photos.

                    <br>

                    JPG, PNG, WEBP and GIF — maximum 5MB each.

                </div>


                <label
                    for="galleryImages"
                    class="gallery-select-button"
                >

                    SELECT PHOTOS

                </label>


                <input
                    type="file"
                    name="image[]"
                    id="galleryImages"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    multiple
                >


                <div
                    id="galleryCount"
                    class="gallery-count"
                ></div>

            </div>


            <!-- PREVIEW -->

            <div
                id="galleryPreview"
                class="gallery-preview"
            ></div>


            <!-- PROGRESS -->

            <div
                id="galleryProgress"
                class="gallery-progress"
            >

                <div class="gallery-progress-bar">

                    <div
                        id="galleryProgressFill"
                        class="gallery-progress-fill"
                    ></div>

                </div>


                <div
                    id="galleryProgressText"
                    class="gallery-progress-text"
                >

                    Uploading...

                </div>

            </div>


            <!-- RESULT -->

            <div
                id="galleryResult"
                class="gallery-result"
            ></div>

        </div>


        <!-- =================================================
             DETAILS
             ================================================= -->

        <div
            class="form-grid"
            style="margin-top:20px;"
        >


            <div>

                <label>
                    Title
                </label>

                <input
                    type="text"
                    id="galleryTitle"
                    maxlength="190"
                    placeholder="Example: Wedding Moments"
                >

            </div>


            <div>

                <label>
                    Category
                </label>

                <select
                    id="galleryCategory"
                >

                    <?php foreach (
                        $allowedCategories
                        as $category
                    ): ?>

                        <option
                            value="<?= e($category) ?>"
                        >

                            <?= e(
                                ucfirst($category)
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div>

                <label>
                    Featured
                </label>


                <label
                    style="
                        display:flex;
                        gap:.5rem;
                        align-items:center;
                        margin-top:.6rem;
                    "
                >

                    <input
                        type="checkbox"
                        id="galleryFeatured"
                        value="1"
                        style="width:auto;"
                    >

                    Show on home

                </label>

            </div>


            <div class="full">

                <label>
                    Description
                </label>

                <textarea
                    id="galleryDescription"
                    placeholder="Description..."
                ></textarea>

            </div>


        </div>


        <!-- =================================================
             BUTTON
             ================================================= -->

        <button
            type="button"
            class="btn btn-primary"
            id="galleryUploadButton"
            style="
                margin-top:18px;
                min-width:220px;
            "
        >

            UPLOAD ALL PHOTOS

        </button>


    </form>

</div>


<!-- =========================================================
     GALLERY LIST
     ========================================================= -->

<div class="grid grid-3">


<?php foreach (
    $rows as $g
): ?>

    <div class="card">


        <div class="card-media">

            <img
                loading="lazy"
                src="<?= e(
                    SITE_URL .
                    '/uploads/' .
                    ($g['image'] ?? '')
                ) ?>"
                alt="<?= e(
                    $g['title'] ?? ''
                ) ?>"
            >

        </div>


        <div class="card-body">


            <strong>

                <?= e(
                    !empty(
                        $g['title']
                    )
                        ? $g['title']
                        : 'Untitled'
                ) ?>

            </strong>


            <p
                style="
                    color:#7a716a;
                    font-size:.82rem;
                "
            >

                <?= e(
                    $g['category'] ?? ''
                ) ?>


                <?php if (
                    !empty(
                        $g['featured']
                    )
                ): ?>

                    · Featured

                <?php endif; ?>

            </p>


            <?php if (
                !empty(
                    $g['description']
                )
            ): ?>

                <p
                    style="
                        color:#8b837c;
                        font-size:.85rem;
                        line-height:1.5;
                    "
                >

                    <?= e(
                        $g['description']
                    ) ?>

                </p>

            <?php endif; ?>


            <form
                method="post"
                onsubmit="
                    return confirm(
                        'Delete this image?'
                    );
                "
            >

                <?= csrf_field() ?>


                <input
                    type="hidden"
                    name="delete_id"
                    value="<?= (int)$g['id'] ?>"
                >


                <button
                    type="submit"
                    class="btn btn-sm btn-ghost"
                >

                    Delete

                </button>

            </form>


        </div>

    </div>

<?php endforeach; ?>


</div>


<script>

(function () {

    'use strict';


    const form =
        document.getElementById(
            'galleryUploadForm'
        );


    const input =
        document.getElementById(
            'galleryImages'
        );


    const dropZone =
        document.getElementById(
            'galleryDropZone'
        );


    const countBox =
        document.getElementById(
            'galleryCount'
        );


    const preview =
        document.getElementById(
            'galleryPreview'
        );


    const uploadButton =
        document.getElementById(
            'galleryUploadButton'
        );


    const progress =
        document.getElementById(
            'galleryProgress'
        );


    const progressFill =
        document.getElementById(
            'galleryProgressFill'
        );


    const progressText =
        document.getElementById(
            'galleryProgressText'
        );


    const result =
        document.getElementById(
            'galleryResult'
        );


    let selectedFiles = [];


    /*
    |--------------------------------------------------------------------------
    | SHOW SELECTED FILES
    |--------------------------------------------------------------------------
    */

    function showFiles(files) {

        selectedFiles =
            Array.from(files || []);


        preview.innerHTML = '';


        if (
            selectedFiles.length === 0
        ) {

            countBox.style.display =
                'none';

            return;
        }


        countBox.style.display =
            'block';


        countBox.textContent =
            selectedFiles.length +
            ' photos selected';


        selectedFiles.forEach(
            function (file) {

                const item =
                    document.createElement(
                        'div'
                    );


                item.className =
                    'gallery-preview-item';


                const img =
                    document.createElement(
                        'img'
                    );


                const filename =
                    document.createElement(
                        'div'
                    );


                filename.className =
                    'gallery-preview-name';


                filename.textContent =
                    file.name;


                const reader =
                    new FileReader();


                reader.onload =
                    function (event) {

                        img.src =
                            event.target.result;

                    };


                reader.readAsDataURL(
                    file
                );


                item.appendChild(
                    img
                );


                item.appendChild(
                    filename
                );


                preview.appendChild(
                    item
                );

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | FILE SELECT
    |--------------------------------------------------------------------------
    */

    input.addEventListener(
        'change',
        function () {

            showFiles(
                input.files
            );

        }
    );


    /*
    |--------------------------------------------------------------------------
    | DRAG & DROP
    |--------------------------------------------------------------------------
    */

    [
        'dragenter',
        'dragover'
    ].forEach(
        function (eventName) {

            dropZone.addEventListener(
                eventName,
                function (event) {

                    event.preventDefault();

                    event.stopPropagation();

                    dropZone.classList.add(
                        'dragging'
                    );

                }
            );

        }
    );


    [
        'dragleave',
        'drop'
    ].forEach(
        function (eventName) {

            dropZone.addEventListener(
                eventName,
                function (event) {

                    event.preventDefault();

                    event.stopPropagation();

                    dropZone.classList.remove(
                        'dragging'
                    );

                }
            );

        }
    );


    dropZone.addEventListener(
        'drop',
        function (event) {

            const files =
                event.dataTransfer.files;


            if (
                files &&
                files.length > 0
            ) {

                showFiles(
                    files
                );

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | UPLOAD
    |--------------------------------------------------------------------------
    */

    uploadButton.addEventListener(
        'click',
        function () {

            if (
                selectedFiles.length === 0
            ) {

                alert(
                    'Please select photos first.'
                );

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | BUILD ONE FORM DATA REQUEST
            |--------------------------------------------------------------------------
            */

            const formData =
                new FormData();


            /*
            |--------------------------------------------------------------------------
            | CSRF
            |--------------------------------------------------------------------------
            */

            const csrfInput =
                form.querySelector(
                    'input[name="csrf"]'
                );


            if (
                !csrfInput ||
                !csrfInput.value
            ) {

                alert(
                    'Security token is missing. Please refresh the page.'
                );

                return;
            }


            formData.append(
                'csrf',
                csrfInput.value
            );


            /*
            |--------------------------------------------------------------------------
            | IMPORTANT:
            | SAME NAME image[]
            | MULTIPLE FILES IN ONE REQUEST
            |--------------------------------------------------------------------------
            */

            selectedFiles.forEach(
                function (file) {

                    formData.append(
                        'image[]',
                        file,
                        file.name
                    );

                }
            );


            /*
            |--------------------------------------------------------------------------
            | OTHER DATA
            |--------------------------------------------------------------------------
            */

            formData.append(
                'title',
                document.getElementById(
                    'galleryTitle'
                ).value
            );


            formData.append(
                'category',
                document.getElementById(
                    'galleryCategory'
                ).value
            );


            formData.append(
                'description',
                document.getElementById(
                    'galleryDescription'
                ).value
            );


            if (
                document.getElementById(
                    'galleryFeatured'
                ).checked
            ) {

                formData.append(
                    'featured',
                    '1'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | UI
            |--------------------------------------------------------------------------
            */

            uploadButton.disabled =
                true;


            input.disabled =
                true;


            progress.style.display =
                'block';


            result.style.display =
                'none';


            progressFill.style.width =
                '0%';


            progressText.textContent =
                'Uploading ' +
                selectedFiles.length +
                ' photos...';


            /*
            |--------------------------------------------------------------------------
            | XHR
            |--------------------------------------------------------------------------
            */

            const xhr =
                new XMLHttpRequest();


            xhr.open(
                'POST',
                'gallery-bulk-upload.php',
                true
            );


            xhr.setRequestHeader(
                'X-Requested-With',
                'XMLHttpRequest'
            );


            /*
            |--------------------------------------------------------------------------
            | PROGRESS
            |--------------------------------------------------------------------------
            */

            xhr.upload.onprogress =
                function (event) {

                    if (
                        event.lengthComputable
                    ) {

                        const percent =
                            Math.round(
                                (
                                    event.loaded /
                                    event.total
                                ) * 100
                            );


                        progressFill.style.width =
                            percent + '%';


                        progressText.textContent =
                            'Uploading ' +
                            selectedFiles.length +
                            ' photos — ' +
                            percent +
                            '%';

                    }

                };


            /*
            |--------------------------------------------------------------------------
            | COMPLETE
            |--------------------------------------------------------------------------
            */

            xhr.onload =
                function () {

                    let data;


                    try {

                        data =
                            JSON.parse(
                                xhr.responseText
                            );

                    } catch (error) {

                        result.className =
                            'gallery-result error';

                        result.innerHTML =
                            '<strong>Server error.</strong><br>' +
                            'The server did not return valid JSON.' +
                            '<br><br>' +
                            xhr.responseText.substring(
                                0,
                                1000
                            );


                        uploadButton.disabled =
                            false;


                        input.disabled =
                            false;


                        return;
                    }


                    if (
                        data.success
                    ) {

                        progressFill.style.width =
                            '100%';


                        progressText.textContent =
                            'Upload complete.';


                        result.className =
                            'gallery-result success';


                        result.innerHTML =
                            '<strong>✓ Upload complete!</strong><br>' +
                            data.message;


                        /*
                        |--------------------------------------------------------------------------
                        | REFRESH GALLERY
                        |--------------------------------------------------------------------------
                        */

                        setTimeout(
                            function () {

                                window.location.reload();

                            },
                            1200
                        );

                    } else {

                        result.className =
                            'gallery-result error';


                        result.innerHTML =
                            '<strong>Upload failed.</strong><br>' +
                            (
                                data.message ||
                                'Unknown error.'
                            );


                        uploadButton.disabled =
                            false;


                        input.disabled =
                            false;

                    }

                };


            /*
            |--------------------------------------------------------------------------
            | NETWORK ERROR
            |--------------------------------------------------------------------------
            */

            xhr.onerror =
                function () {

                    result.className =
                        'gallery-result error';


                    result.innerHTML =
                        '<strong>Network error.</strong><br>' +
                        'The server could not receive the photos.';


                    uploadButton.disabled =
                        false;


                    input.disabled =
                        false;

                };


            /*
            |--------------------------------------------------------------------------
            | TIMEOUT
            |--------------------------------------------------------------------------
            */

            xhr.timeout =
                15 * 60 * 1000;


            xhr.ontimeout =
                function () {

                    result.className =
                        'gallery-result error';


                    result.innerHTML =
                        '<strong>Upload timed out.</strong><br>' +
                        'Try fewer photos or smaller files.';


                    uploadButton.disabled =
                        false;


                    input.disabled =
                        false;

                };


            /*
            |--------------------------------------------------------------------------
            | SEND ALL FILES AT ONCE
            |--------------------------------------------------------------------------
            */

            xhr.send(
                formData
            );

        }
    );

})();

</script>


<?php

require __DIR__ .
    '/includes/admin-footer.php';

?>