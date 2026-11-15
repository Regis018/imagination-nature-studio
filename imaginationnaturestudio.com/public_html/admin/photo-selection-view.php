<?php
declare(strict_types=1);

/**
 * ============================================================
 * IMAGINATION NATURE STUDIO
 * ADMIN PHOTO SELECTION VIEW
 * ============================================================
 */

require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pdo = db();

$galleryId = (int)($_GET['id'] ?? 0);

if ($galleryId <= 0) {
    redirect('bookings.php');
}


/*
|--------------------------------------------------------------------------
| Load gallery
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        pg.*,

        b.booking_number,

        c.full_name,
        c.phone,
        c.whatsapp,
        c.email

    FROM photo_galleries pg

    LEFT JOIN bookings b
        ON b.id = pg.booking_id

    LEFT JOIN customers c
        ON c.id = b.customer_id

    WHERE pg.id = ?

    LIMIT 1
");

$stmt->execute([
    $galleryId
]);

$gallery = $stmt->fetch();


if (!$gallery) {

    flash(
        'err',
        'Photo selection gallery not found.'
    );

    redirect('bookings.php');
}


/*
|--------------------------------------------------------------------------
| Archived status
|--------------------------------------------------------------------------
*/

$isArchived = !empty($gallery['archived_at']);


/*
|--------------------------------------------------------------------------
| Load selected photos
|--------------------------------------------------------------------------
|
| IMPORTANT:
| We only load photos that were selected by the client.
|
| This means that after Archive, the JPGs that remain on
| the server are exactly the ones shown here.
|
*/

$photosStmt = $pdo->prepare("
    SELECT

        gp.id,
        gp.photo_number,
        gp.original_filename,
        gp.original_path,
        gp.preview_filename,
        gp.preview_path,

        ps.selected_at

    FROM photo_selections ps

    INNER JOIN gallery_photos gp
        ON gp.id = ps.gallery_photo_id

    WHERE ps.gallery_id = ?

    ORDER BY gp.photo_number ASC
");

$photosStmt->execute([
    $galleryId
]);

$selectedPhotos = $photosStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Count / total
|--------------------------------------------------------------------------
*/

$selectedCount =
    count($selectedPhotos);

$pricePerPhoto =
    (float)$gallery['price_per_photo'];

$total =
    $selectedCount * $pricePerPhoto;


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function h($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| Status class
|--------------------------------------------------------------------------
*/

$statusClass = match (
    $gallery['status']
) {

    'submitted' => 'submitted',

    'active' => 'active',

    'expired' => 'expired',

    'closed' => 'closed',

    default => 'draft'
};


/*
|--------------------------------------------------------------------------
| Client information
|--------------------------------------------------------------------------
*/

$clientName =
    $gallery['client_name']
    ?: ($gallery['full_name'] ?? 'Client');

$clientPhone =
    $gallery['client_phone']
    ?: ($gallery['phone'] ?? '');

$clientWhatsApp =
    $gallery['whatsapp']
    ?: ($gallery['client_whatsapp'] ?? '')
    ?: ($gallery['client_phone'] ?? '')
    ?: ($gallery['phone'] ?? '');


/*
|--------------------------------------------------------------------------
| Build client summary
|--------------------------------------------------------------------------
*/

$summary =
    "IMAGINATION NATURE STUDIO\n"
    . "PHOTO SELECTION\n\n"
    . "Client: "
    . $clientName
    . "\n"
    . "Selected photos: "
    . $selectedCount
    . "\n"
    . "Price/photo: "
    . number_format(
        $pricePerPhoto
    )
    . " RWF\n"
    . "Total: "
    . number_format(
        $total
    )
    . " RWF\n\n"
    . "Selected photos:\n";


foreach (
    $selectedPhotos
    as $photo
) {

    $summary .=
        "PHOTO #"
        . str_pad(
            (string)$photo['photo_number'],
            3,
            '0',
            STR_PAD_LEFT
        )
        . " — "
        . $photo['original_filename']
        . "\n";
}


/*
|--------------------------------------------------------------------------
| Build WhatsApp URL
|--------------------------------------------------------------------------
|
| Rwanda numbers are converted to international format.
|
| 0788577033     -> 250788577033
| +250788577033  -> 250788577033
| 250788577033   -> 250788577033
| 788577033      -> 250788577033
|
*/

$whatsappNumber = '';

if ($clientWhatsApp) {

    // Keep numbers only
    $whatsappNumber = preg_replace(
        '/\D+/',
        '',
        (string)$clientWhatsApp
    );

    /*
    |--------------------------------------------------------------------------
    | Remove international 00 prefix
    |--------------------------------------------------------------------------
    */

    if (
        str_starts_with(
            $whatsappNumber,
            '00'
        )
    ) {

        $whatsappNumber =
            substr(
                $whatsappNumber,
                2
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Rwanda local number
    |--------------------------------------------------------------------------
    */

    if (
        strlen($whatsappNumber) === 10
        &&
        str_starts_with(
            $whatsappNumber,
            '0'
        )
    ) {

        $whatsappNumber =
            '250'
            . substr(
                $whatsappNumber,
                1
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Rwanda number without leading zero
    |--------------------------------------------------------------------------
    */

    elseif (
        strlen($whatsappNumber) === 9
        &&
        !str_starts_with(
            $whatsappNumber,
            '250'
        )
    ) {

        $whatsappNumber =
            '250'
            . $whatsappNumber;
    }


    /*
    |--------------------------------------------------------------------------
    | Build final WhatsApp URL
    |--------------------------------------------------------------------------
    */

    if ($whatsappNumber !== '') {

        $whatsappUrl =
            'https://wa.me/'
            . $whatsappNumber
            . '?text='
            . rawurlencode(
                $summary
            );

    } else {

        $whatsappUrl = '';

    }

} else {

    $whatsappUrl = '';

}

?>

<?php require __DIR__ . '/includes/admin-header.php'; ?>


<style>

/*
|--------------------------------------------------------------------------
| Photo Selection Admin
|--------------------------------------------------------------------------
*/

.selection-page {

    max-width: 1250px;

    margin: 0 auto;

}


.selection-header {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 1rem;

    flex-wrap: wrap;

    margin-bottom: 1.5rem;

}


.selection-title h1 {

    margin: 0;

    font-family: var(--font-serif);

}


.selection-title p {

    margin: .35rem 0 0;

    color: #7a716a;

}


.selection-actions {

    display: flex;

    gap: .6rem;

    flex-wrap: wrap;

    align-items: center;

}


.selection-actions form {

    margin: 0;

}


.selection-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 1rem;

}


.selection-card {

    background: #fff;

    border: 1px solid #e7e1db;

    border-radius: 14px;

    padding: 1.15rem;

}


.selection-card .label {

    font-size: .68rem;

    letter-spacing: .16em;

    text-transform: uppercase;

    color: #7a716a;

    margin-bottom: .35rem;

}


.selection-card .value {

    font-family: var(--font-serif);

    font-size: 1.25rem;

    font-weight: 700;

}


.client-card {

    grid-column:
        span 2;

}


.summary-card {

    grid-column:
        span 2;

}


.status-pill-custom {

    display: inline-flex;

    align-items: center;

    padding: .35rem .7rem;

    border-radius: 999px;

    font-size: .72rem;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .04em;

}


.status-pill-custom.submitted {

    background: #ecfdf3;

    color: #067647;

}


.status-pill-custom.active {

    background: #eff6ff;

    color: #175cd3;

}


.status-pill-custom.expired {

    background: #fff7ed;

    color: #c2410c;

}


.status-pill-custom.closed {

    background: #f3f4f6;

    color: #374151;

}


.status-pill-custom.draft {

    background: #fef3c7;

    color: #92400e;

}


/*
|--------------------------------------------------------------------------
| Archived badge
|--------------------------------------------------------------------------
*/

.archived-badge {

    display: inline-flex;

    align-items: center;

    gap: .4rem;

    margin-top: .65rem;

    padding: .4rem .75rem;

    border-radius: 999px;

    background: #f3f4f6;

    color: #374151;

    font-size: .72rem;

    font-weight: 800;

    letter-spacing: .04em;

}


.archived-info {

    margin-top: .8rem;

    padding: .75rem 1rem;

    border-radius: 10px;

    background: #fffbeb;

    border: 1px solid #fde68a;

    color: #92400e;

    font-size: .82rem;

    line-height: 1.5;

}


.photos-card {

    margin-top: 1.5rem;

    padding: 1.25rem;

}


.photos-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 1rem;

    flex-wrap: wrap;

    margin-bottom: 1rem;

}


.photos-header h2 {

    margin: 0;

    font-family: var(--font-serif);

}


.photo-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 14px;

}


.selected-photo {

    border: 1px solid #e4e4e4;

    border-radius: 13px;

    overflow: hidden;

    background: #fff;

}


.selected-photo-image {

    aspect-ratio: 4 / 3;

    background: #eee;

    position: relative;

}


.selected-photo-image img {

    width: 100%;

    height: 100%;

    object-fit: cover;

    display: block;

}


.archived-photo-label {

    position: absolute;

    top: .6rem;

    right: .6rem;

    background: rgba(0,0,0,.72);

    color: #fff;

    padding: .3rem .55rem;

    border-radius: 999px;

    font-size: .65rem;

    font-weight: 700;

}


.selected-photo-info {

    padding: .75rem;

}


.photo-number {

    font-weight: 800;

    font-size: .9rem;

}


.original-name {

    margin-top: .25rem;

    font-size: .75rem;

    color: #6b7280;

    word-break: break-word;

}


.selected-at {

    margin-top: .4rem;

    font-size: .68rem;

    color: #9ca3af;

}


.empty-selection {

    text-align: center;

    padding: 4rem 1rem;

    color: #7a716a;

}


.copy-box {

    margin-top: 1.5rem;

}


.copy-box textarea {

    width: 100%;

    min-height: 150px;

    resize: vertical;

    border: 1px solid #ddd;

    border-radius: 10px;

    padding: 12px;

    font: inherit;

}


.copy-btn {

    margin-top: .6rem;

}


.download-originals-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: .45rem;

    border: 0;

    cursor: pointer;

    white-space: nowrap;

}


.download-originals-btn:hover {

    transform: translateY(-1px);

}


/*
|--------------------------------------------------------------------------
| Print
|--------------------------------------------------------------------------
*/

@media print {

    .selection-actions,
    .copy-box {

        display: none !important;

    }

}


/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media(max-width:900px) {

    .selection-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }

    .photo-grid {

        grid-template-columns:
            repeat(3, 1fr);

    }

}


@media(max-width:600px) {

    .selection-grid {

        grid-template-columns:
            1fr;

    }

    .client-card,
    .summary-card {

        grid-column:
            span 1;

    }

    .photo-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }

    .selection-actions {

        width: 100%;

    }

    .selection-actions .btn,
    .selection-actions form {

        width: 100%;

    }

    .selection-actions form button {

        width: 100%;

    }

}

</style>


<div class="selection-page">


    <!-- =====================================================
         HEADER
         ===================================================== -->

    <div class="selection-header">


        <div class="selection-title">

            <h1>
                📸 Photo Selection
            </h1>

            <p>
                <?= h(
                    $gallery['title']
                ) ?>
            </p>


            <?php if ($isArchived): ?>

                <div class="archived-badge">

                    📦 ARCHIVED

                    <?php if (!empty($gallery['archived_at'])): ?>

                        ·

                        <?= h(
                            $gallery['archived_at']
                        ) ?>

                    <?php endif; ?>

                </div>

            <?php endif; ?>


        </div>


        <div class="selection-actions">


            <?php if (
                $gallery['booking_id']
            ): ?>

                <a
                    class="btn btn-ghost"
                    href="booking-view.php?id=<?= (int)$gallery['booking_id'] ?>"
                >
                    ← Booking
                </a>

            <?php endif; ?>


            <?php if (
                $selectedCount > 0
                &&
                !$isArchived
            ): ?>

                <form
                    method="post"
                    action="photo-selection-download.php"
                    target="_blank"
                >

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int)$gallery['id'] ?>"
                    >

                    <button
                        class="btn btn-primary download-originals-btn"
                        type="submit"
                    >
                        📦 Download Selected Originals
                    </button>

                </form>


            <?php elseif (
                $selectedCount > 0
                &&
                $isArchived
            ): ?>

                <span
                    class="btn btn-ghost"
                    style="
                        opacity:.7;
                        cursor:not-allowed;
                    "
                    title="Original RAW files were removed when this gallery was archived."
                >
                    📦 Originals Archived
                </span>

            <?php endif; ?>


            <button
                class="btn btn-ghost"
                type="button"
                onclick="window.print()"
            >
                🖨 Print
            </button>


        </div>

    </div>



    <!-- =====================================================
         ARCHIVED INFORMATION
         ===================================================== -->

    <?php if ($isArchived): ?>

        <div class="archived-info">

            <strong>📦 Archived Gallery</strong><br>

            This gallery has been archived to free server storage.
            Original RAW files and unselected JPG/preview files
            were removed.

            <br>

            <strong>
                The JPG files selected by the client are preserved.
            </strong>

            Client, booking and selection records remain available
            for your records.

        </div>

    <?php endif; ?>



    <!-- =====================================================
         SUMMARY
         ===================================================== -->

    <div class="selection-grid">


        <!-- CLIENT -->

        <div class="card client-card">

            <div class="label">
                Client
            </div>


            <div
                style="
                    font-family:var(--font-serif);
                    font-size:1.35rem;
                    font-weight:700
                "
            >

                <?= h($clientName) ?>

            </div>


            <?php if ($clientPhone): ?>

                <p style="margin:.5rem 0 0">

                    📞
                    <?= h($clientPhone) ?>

                </p>

            <?php endif; ?>


            <?php if ($gallery['booking_number']): ?>

                <p
                    style="
                        margin:.3rem 0 0;
                        color:#7a716a
                    "
                >

                    Booking:

                    <strong>

                        <?= h(
                            $gallery['booking_number']
                        ) ?>

                    </strong>

                </p>

            <?php endif; ?>


        </div>



        <!-- STATUS -->

        <div class="card">

            <div class="label">
                Status
            </div>


            <div style="margin-top:.4rem">

                <span
                    class="status-pill-custom
                    <?= h($statusClass) ?>"
                >

                    <?= h(
                        ucfirst(
                            $gallery['status']
                        )
                    ) ?>

                </span>

            </div>

        </div>



        <!-- SELECTED -->

        <div class="card">

            <div class="label">
                Selected Photos
            </div>


            <div class="value">

                <?= $selectedCount ?>

            </div>

        </div>



        <!-- PRICE -->

        <div class="card">

            <div class="label">
                Price / Photo
            </div>


            <div class="value">

                <?= number_format(
                    $pricePerPhoto
                ) ?>

                RWF

            </div>

        </div>



        <!-- TOTAL -->

        <div class="card">

            <div class="label">
                Selection Total
            </div>


            <div
                class="value"
                style="font-size:1.5rem"
            >

                <?= number_format(
                    $total
                ) ?>

                RWF

            </div>

        </div>



        <!-- TIME -->

        <div class="card">

            <div class="label">
                Selection Time
            </div>


            <div class="value">

                <?= (int)$gallery['duration_minutes'] ?>

                min

            </div>

        </div>



        <!-- STARTED -->

        <div class="card">

            <div class="label">
                Started
            </div>


            <div
                style="
                    font-size:.9rem
                "
            >

                <?= $gallery['started_at']
                    ? h($gallery['started_at'])
                    : 'Not started'
                ?>

            </div>

        </div>



        <!-- SUBMITTED -->

        <div class="card">

            <div class="label">
                Submitted
            </div>


            <div
                style="
                    font-size:.9rem
                "
            >

                <?= $gallery['submitted_at']
                    ? h($gallery['submitted_at'])
                    : 'Not submitted'
                ?>

            </div>

        </div>


    </div>



    <!-- =====================================================
         SELECTED PHOTOS
         ===================================================== -->

    <div class="card photos-card">


        <div class="photos-header">

            <div>

                <h2>
                    Selected Photos
                </h2>

                <div
                    style="
                        color:#7a716a;
                        font-size:.8rem;
                        margin-top:.3rem
                    "
                >

                    <?php if ($isArchived): ?>

                        JPG photos preserved from the
                        client's final selection.

                    <?php else: ?>

                        These are the photos selected
                        by the client for editing.

                    <?php endif; ?>

                </div>

            </div>


            <?php if ($selectedCount > 0): ?>

                <div
                    style="
                        font-weight:700
                    "
                >

                    <?= $selectedCount ?>

                    photos ·

                    <?= number_format(
                        $total
                    ) ?>

                    RWF

                </div>

            <?php endif; ?>


        </div>



        <?php if (!$selectedPhotos): ?>


            <div class="empty-selection">

                <div
                    style="
                        font-size:3rem;
                        margin-bottom:.7rem
                    "
                >
                    📷
                </div>

                <h3>
                    No photos selected yet
                </h3>

                <p>
                    The client has not submitted
                    any selected photos.
                </p>

            </div>


        <?php else: ?>


            <div class="photo-grid">


                <?php foreach (
                    $selectedPhotos
                    as $photo
                ): ?>


                    <?php

                    /*
                    |--------------------------------------------------------------------------
                    | Build preview URL
                    |--------------------------------------------------------------------------
                    */

                    $previewUrl =
                        SITE_URL
                        . '/uploads/photo-selection/previews/'
                        . rawurlencode(
                            (string)$gallery['id']
                        )
                        . '/'
                        . rawurlencode(
                            $photo['preview_filename']
                        );

                    ?>


                    <div class="selected-photo">


                        <div class="selected-photo-image">

                            <img
                                src="<?= h(
                                    $previewUrl
                                ) ?>"
                                alt="Photo #<?= h(
                                    str_pad(
                                        (string)$photo['photo_number'],
                                        3,
                                        '0',
                                        STR_PAD_LEFT
                                    )
                                ) ?>"
                                loading="lazy"
                            >


                            <?php if ($isArchived): ?>

                                <div class="archived-photo-label">

                                    📦 KEPT JPG

                                </div>

                            <?php endif; ?>


                        </div>


                        <div class="selected-photo-info">


                            <div class="photo-number">

                                PHOTO #

                                <?= h(
                                    str_pad(
                                        (string)$photo['photo_number'],
                                        3,
                                        '0',
                                        STR_PAD_LEFT
                                    )
                                ) ?>

                            </div>


                            <div class="original-name">

                                <?= h(
                                    $photo['original_filename']
                                ) ?>

                            </div>


                            <?php if (
                                $photo['selected_at']
                            ): ?>

                                <div class="selected-at">

                                    Selected:

                                    <?= h(
                                        $photo['selected_at']
                                    ) ?>

                                </div>

                            <?php endif; ?>


                        </div>

                    </div>


                <?php endforeach; ?>


            </div>


        <?php endif; ?>


    </div>



    <!-- =====================================================
         WHATSAPP SUMMARY
         ===================================================== -->

    <?php if ($selectedCount > 0): ?>


        <div class="card copy-box">


            <h2
                style="
                    margin-top:0;
                    font-family:var(--font-serif)
                "
            >
                📱 Client Summary
            </h2>


            <p
                style="
                    color:#7a716a;
                    font-size:.85rem
                "
            >

                Use this summary when communicating
                the selected photos with the client.

            </p>


            <textarea
                id="clientSummary"
                readonly
            ><?= h($summary) ?></textarea>


            <button
                class="btn btn-primary copy-btn"
                type="button"
                onclick="copySummary()"
            >
                📋 Copy Summary
            </button>


            <?php if ($whatsappUrl): ?>

                <a
                    class="btn btn-ghost copy-btn"
                    target="_blank"
                    rel="noopener noreferrer"
                    href="<?= h($whatsappUrl) ?>"
                >
                    💬 Open WhatsApp
                </a>

            <?php endif; ?>


        </div>


    <?php endif; ?>


</div>


<script>

/*
|--------------------------------------------------------------------------
| Copy Client Summary
|--------------------------------------------------------------------------
*/

function copySummary() {

    const textarea =
        document.getElementById(
            'clientSummary'
        );

    if (!textarea) {
        return;
    }


    if (
        navigator.clipboard &&
        window.isSecureContext
    ) {

        navigator.clipboard
            .writeText(
                textarea.value
            )
            .then(function () {

                alert(
                    'Client summary copied.'
                );

            })
            .catch(function () {

                fallbackCopy(textarea);

            });

    } else {

        fallbackCopy(textarea);

    }

}


/*
|--------------------------------------------------------------------------
| Fallback Copy
|--------------------------------------------------------------------------
*/

function fallbackCopy(textarea) {

    textarea.focus();

    textarea.select();

    textarea.setSelectionRange(
        0,
        textarea.value.length
    );

    try {

        document.execCommand(
            'copy'
        );

        alert(
            'Client summary copied.'
        );

    } catch (error) {

        alert(
            'Please copy the summary manually.'
        );

    }

}

</script>


<?php require __DIR__ . '/includes/admin-footer.php'; ?>