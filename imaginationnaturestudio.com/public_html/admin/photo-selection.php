<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| DELETE CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (
    empty(
        $_SESSION['photo_selection_delete_token']
    )
) {
    $_SESSION['photo_selection_delete_token'] =
        bin2hex(
            random_bytes(32)
        );
}

$deleteToken =
    $_SESSION['photo_selection_delete_token'];


/*
|--------------------------------------------------------------------------
| ARCHIVE CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (
    empty(
        $_SESSION['photo_selection_archive_token']
    )
) {
    $_SESSION['photo_selection_archive_token'] =
        bin2hex(
            random_bytes(32)
        );
}

$archiveToken =
    $_SESSION['photo_selection_archive_token'];


/*
|--------------------------------------------------------------------------
| GET ACTIVE GALLERIES ONLY
|--------------------------------------------------------------------------
| Archived galleries are hidden from the normal list.
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        pg.*,

        COUNT(DISTINCT gp.id) AS total_photos,

        COUNT(DISTINCT ps.id) AS selected_photos

    FROM photo_galleries pg

    LEFT JOIN gallery_photos gp
        ON gp.gallery_id = pg.id

    LEFT JOIN photo_selections ps
        ON ps.gallery_id = pg.id
       AND ps.gallery_photo_id = gp.id

    WHERE pg.archived_at IS NULL

    GROUP BY pg.id

    ORDER BY pg.id DESC
");

$galleries =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| ARCHIVED COUNT
|--------------------------------------------------------------------------
*/

$archivedStmt = $pdo->query("
    SELECT COUNT(*)
    FROM photo_galleries
    WHERE archived_at IS NOT NULL
");

$archivedCount =
    (int)$archivedStmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalGalleries =
    count($galleries);

$activeCount = 0;
$submittedCount = 0;
$expiredCount = 0;
$draftCount = 0;

foreach (
    $galleries
    as $gallery
) {

    switch (
        $gallery['status']
    ) {

        case 'active':

            $activeCount++;

            break;


        case 'submitted':

            $submittedCount++;

            break;


        case 'expired':

            $expiredCount++;

            break;


        case 'draft':

            $draftCount++;

            break;
    }
}


require __DIR__ . '/includes/admin-header.php';

?>

<style>

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

.photo-selection-page {
    padding: 24px;
}


/*
|--------------------------------------------------------------------------
| TOP
|--------------------------------------------------------------------------
*/

.ps-top {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 25px;

    flex-wrap: wrap;
}


.ps-title h1 {

    margin: 0;

    font-size: 28px;

}


.ps-title p {

    margin: 7px 0 0;

    opacity: .7;

}


/*
|--------------------------------------------------------------------------
| CREATE BUTTON
|--------------------------------------------------------------------------
*/

.ps-create {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    background: #16a34a;

    color: #fff;

    text-decoration: none;

    padding: 12px 18px;

    border-radius: 10px;

    font-weight: 600;

}


.ps-create:hover {

    opacity: .9;

}


/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/

.ps-stats {

    display: grid;

    grid-template-columns:
        repeat(6, 1fr);

    gap: 15px;

    margin-bottom: 25px;

}


.ps-stat {

    padding: 18px;

    border-radius: 14px;

    background:
        rgba(255,255,255,.05);

    border:
        1px solid
        rgba(255,255,255,.08);

}


.ps-stat-label {

    font-size: 13px;

    opacity: .65;

    margin-bottom: 8px;

}


.ps-stat-number {

    font-size: 26px;

    font-weight: 700;

}


/*
|--------------------------------------------------------------------------
| ARCHIVED STAT
|--------------------------------------------------------------------------
*/

.ps-stat-archived {

    border-color:
        rgba(168,85,247,.25);

    background:
        rgba(168,85,247,.08);

}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.ps-table-wrap {

    overflow-x: auto;

    border-radius: 14px;

}


.ps-table {

    width: 100%;

    border-collapse: collapse;

    min-width: 1150px;

}


.ps-table th,
.ps-table td {

    padding: 14px 12px;

    text-align: left;

    border-bottom:
        1px solid
        rgba(255,255,255,.08);

}


.ps-table th {

    font-size: 13px;

    opacity: .7;

}


.ps-table td {

    font-size: 14px;

}


/*
|--------------------------------------------------------------------------
| CLIENT
|--------------------------------------------------------------------------
*/

.ps-client {

    font-weight: 600;

}


.ps-phone {

    font-size: 12px;

    opacity: .65;

    margin-top: 3px;

}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

.ps-status {

    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: 600;

}


.ps-status-active {

    background:
        rgba(34,197,94,.15);

    color: #4ade80;

}


.ps-status-submitted {

    background:
        rgba(59,130,246,.15);

    color: #60a5fa;

}


.ps-status-expired {

    background:
        rgba(239,68,68,.15);

    color: #f87171;

}


.ps-status-draft {

    background:
        rgba(234,179,8,.15);

    color: #facc15;

}


.ps-status-closed {

    background:
        rgba(148,163,184,.15);

    color: #cbd5e1;

}


/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

.ps-actions {

    display: flex;

    gap: 7px;

    flex-wrap: wrap;

    align-items: center;

}


.ps-actions form {

    margin: 0;

    padding: 0;

}


.ps-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding: 7px 11px;

    border-radius: 8px;

    text-decoration: none;

    font-size: 12px;

    font-weight: 600;

    background:
        rgba(255,255,255,.08);

    color: inherit;

    white-space: nowrap;

}


.ps-btn:hover {

    opacity: .88;

}


.ps-btn-view {

    background: #2563eb;

    color: #fff;

}


.ps-btn-upload {

    background: #7c3aed;

    color: #fff;

}


/*
|--------------------------------------------------------------------------
| ARCHIVE BUTTON
|--------------------------------------------------------------------------
*/

.ps-btn-archive {

    background: #a855f7;

    color: #fff;

    border: 0;

    cursor: pointer;

    font-family: inherit;

}


.ps-btn-archive:hover {

    background: #9333ea;

}


/*
|--------------------------------------------------------------------------
| DELETE BUTTON
|--------------------------------------------------------------------------
*/

.ps-btn-delete {

    background: #dc2626;

    color: #fff;

    border: 0;

    cursor: pointer;

    font-family: inherit;

}


.ps-btn-delete:hover {

    background: #b91c1c;

}


/*
|--------------------------------------------------------------------------
| ARCHIVED LINK
|--------------------------------------------------------------------------
*/

.ps-archived-link {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    margin-bottom: 20px;

    padding: 10px 15px;

    border-radius: 10px;

    background:
        rgba(168,85,247,.12);

    border:
        1px solid
        rgba(168,85,247,.25);

    color: #c084fc;

    text-decoration: none;

    font-weight: 600;

}


.ps-archived-link:hover {

    background:
        rgba(168,85,247,.2);

}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.ps-empty {

    text-align: center;

    padding: 60px 20px;

    opacity: .7;

}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 1200px) {

    .ps-stats {

        grid-template-columns:
            repeat(3, 1fr);

    }

}


@media (max-width: 1000px) {

    .ps-stats {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media (max-width: 600px) {

    .photo-selection-page {

        padding: 15px;

    }


    .ps-stats {

        grid-template-columns:
            1fr;

    }


    .ps-actions {

        flex-direction: column;

        align-items: stretch;

    }


    .ps-actions a,
    .ps-actions form,
    .ps-actions button {

        width: 100%;

    }

}

</style>


<div class="photo-selection-page">


    <!-- =====================================================
         HEADER
         ===================================================== -->

    <div class="ps-top">

        <div class="ps-title">

            <h1>
                📸 Photo Selection
            </h1>

            <p>
                Manage client photo selections,
                uploads and selected originals.
            </p>

        </div>


        <a
            href="photo-selection-create.php"
            class="ps-create"
        >
            ➕ Create New Selection
        </a>

    </div>


    <!-- =====================================================
         ARCHIVED LINK
         ===================================================== -->

    <a
        href="photo-selection-archived.php"
        class="ps-archived-link"
    >
        📦 Archived Galleries
        (<?= $archivedCount ?>)
    </a>


    <!-- =====================================================
         STATISTICS
         ===================================================== -->

    <div class="ps-stats">


        <div class="ps-stat">

            <div class="ps-stat-label">
                Total Galleries
            </div>

            <div class="ps-stat-number">
                <?= $totalGalleries ?>
            </div>

        </div>


        <div class="ps-stat">

            <div class="ps-stat-label">
                Draft
            </div>

            <div class="ps-stat-number">
                <?= $draftCount ?>
            </div>

        </div>


        <div class="ps-stat">

            <div class="ps-stat-label">
                Active
            </div>

            <div class="ps-stat-number">
                <?= $activeCount ?>
            </div>

        </div>


        <div class="ps-stat">

            <div class="ps-stat-label">
                Submitted
            </div>

            <div class="ps-stat-number">
                <?= $submittedCount ?>
            </div>

        </div>


        <div class="ps-stat">

            <div class="ps-stat-label">
                Expired
            </div>

            <div class="ps-stat-number">
                <?= $expiredCount ?>
            </div>

        </div>


        <div
            class="
                ps-stat
                ps-stat-archived
            "
        >

            <div class="ps-stat-label">
                Archived
            </div>

            <div class="ps-stat-number">
                <?= $archivedCount ?>
            </div>

        </div>


    </div>


    <!-- =====================================================
         GALLERIES
         ===================================================== -->

    <?php if (!$galleries): ?>


        <div class="ps-empty">

            <div style="font-size:48px;">
                📸
            </div>

            <h2>
                No Active Photo Selection Galleries
            </h2>

            <p>
                Create your first client
                photo selection gallery.
            </p>

            <a
                href="photo-selection-create.php"
                class="ps-create"
            >
                ➕ Create First Selection
            </a>

        </div>


    <?php else: ?>


        <div class="ps-table-wrap">

            <table class="ps-table">


                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Client</th>

                        <th>Title</th>

                        <th>Photos</th>

                        <th>Selected</th>

                        <th>Price / Photo</th>

                        <th>Status</th>

                        <th>Created</th>

                        <th>Actions</th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach (
                    $galleries
                    as $gallery
                ): ?>


                    <?php

                    $status =
                        strtolower(
                            (string)
                            $gallery['status']
                        );

                    $statusClass =
                        'ps-status-' .
                        $status;

                    $clientName =
                        (string)
                        (
                            $gallery[
                                'client_name'
                            ]
                            ?? 'Client'
                        );

                    ?>


                    <tr>


                        <!-- ID -->

                        <td>

                            #<?= (int)
                                $gallery['id']
                            ?>

                        </td>


                        <!-- CLIENT -->

                        <td>

                            <div class="ps-client">

                                <?= e(
                                    $clientName
                                ) ?>

                            </div>


                            <?php if (
                                !empty(
                                    $gallery[
                                        'client_phone'
                                    ]
                                )
                            ): ?>

                                <div class="ps-phone">

                                    <?= e(
                                        $gallery[
                                            'client_phone'
                                        ]
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </td>


                        <!-- TITLE -->

                        <td>

                            <?= e(
                                $gallery['title']
                            ) ?>

                        </td>


                        <!-- PHOTOS -->

                        <td>

                            <?= (int)
                                $gallery[
                                    'total_photos'
                                ]
                            ?>

                        </td>


                        <!-- SELECTED -->

                        <td>

                            <strong>

                                <?= (int)
                                    $gallery[
                                        'selected_photos'
                                    ]
                                ?>

                            </strong>

                        </td>


                        <!-- PRICE -->

                        <td>

                            <?= number_format(
                                (float)
                                $gallery[
                                    'price_per_photo'
                                ]
                            ) ?>

                            RWF

                        </td>


                        <!-- STATUS -->

                        <td>

                            <span
                                class="
                                    ps-status
                                    <?= e(
                                        $statusClass
                                    ) ?>
                                "
                            >

                                <?= e(
                                    ucfirst(
                                        $status
                                    )
                                ) ?>

                            </span>

                        </td>


                        <!-- CREATED -->

                        <td>

                            <?php

                            $created =
                                $gallery[
                                    'created_at'
                                ]
                                ?? null;


                            if ($created) {

                                echo e(
                                    date(
                                        'd M Y H:i',
                                        strtotime(
                                            $created
                                        )
                                    )
                                );

                            } else {

                                echo '-';

                            }

                            ?>

                        </td>


                        <!-- =================================================
                             ACTIONS
                             ================================================= -->

                        <td>

                            <div class="ps-actions">


                                <!-- VIEW -->

                                <a
                                    href="
                                        photo-selection-view.php?id=
                                        <?= (int)
                                            $gallery['id']
                                        ?>
                                    "
                                    class="
                                        ps-btn
                                        ps-btn-view
                                    "
                                >
                                    👁 View
                                </a>


                                <!-- UPLOAD -->

                                <a
                                    href="
                                        photo-selection-upload.php?id=
                                        <?= (int)
                                            $gallery['id']
                                        ?>
                                    "
                                    class="
                                        ps-btn
                                        ps-btn-upload
                                    "
                                >
                                    📤 Upload
                                </a>


                                <!-- ARCHIVE -->

                                <form
                                    method="POST"
                                    action="photo-selection-archive.php"
                                    onsubmit="return confirm(
                                        '📦 ARCHIVE PHOTO SELECTION\\n\\n'
                                        + 'Client: <?= htmlspecialchars(
                                            $clientName,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>\\n\\n'
                                        + 'Archive will:\\n'
                                        + '• Remove RAW/original files\\n'
                                        + '• Remove preview files\\n'
                                        + '• Free server storage\\n'
                                        + '• Keep the client\\n'
                                        + '• Keep the booking\\n'
                                        + '• Keep the gallery record\\n\\n'
                                        + 'The photo files will NOT be recoverable from the server after archiving.\\n\\n'
                                        + 'Continue?'
                                    );"
                                >

                                    <input
                                        type="hidden"
                                        name="gallery_id"
                                        value="<?= (int)
                                            $gallery['id']
                                        ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e(
                                            $archiveToken
                                        ) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="
                                            ps-btn
                                            ps-btn-archive
                                        "
                                    >
                                        📦 Archive
                                    </button>

                                </form>


                                <!-- DELETE -->

                                <form
                                    method="POST"
                                    action="photo-selection-delete.php"
                                    onsubmit="return confirm(
                                        '⚠️ DELETE PHOTO SELECTION\\n\\n'
                                        + 'Client: <?= htmlspecialchars(
                                            $clientName,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>\\n\\n'
                                        + 'This will delete:\\n'
                                        + '• Photo Selection gallery\\n'
                                        + '• Uploaded photos\\n'
                                        + '• Selected photos\\n'
                                        + '• RAW/JPG original files\\n'
                                        + '• Preview files\\n\\n'
                                        + 'The CLIENT and BOOKING will NOT be deleted.\\n\\n'
                                        + 'This action cannot be undone.\\n\\n'
                                        + 'Continue?'
                                    );"
                                >


                                    <input
                                        type="hidden"
                                        name="gallery_id"
                                        value="<?= (int)
                                            $gallery['id']
                                        ?>"
                                    >


                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e(
                                            $deleteToken
                                        ) ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="
                                            ps-btn
                                            ps-btn-delete
                                        "
                                    >
                                        🗑 Delete
                                    </button>


                                </form>


                            </div>

                        </td>


                    </tr>


                <?php endforeach; ?>


                </tbody>

            </table>

        </div>


    <?php endif; ?>


</div>


<?php

require __DIR__ .
    '/includes/admin-footer.php';

?>