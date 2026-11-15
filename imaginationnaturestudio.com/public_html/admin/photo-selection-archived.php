<?php
declare(strict_types=1);

/**
 * ============================================================
 * IMAGINATION NATURE STUDIO
 * ARCHIVED PHOTO SELECTIONS
 * ============================================================
 */

require_once __DIR__ . '/../includes/functions.php';

require_admin();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();


/*
|--------------------------------------------------------------------------
| DELETE CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['photo_selection_delete_token'])
) {
    $_SESSION['photo_selection_delete_token'] =
        bin2hex(random_bytes(32));
}

$deleteToken =
    $_SESSION['photo_selection_delete_token'];


/*
|--------------------------------------------------------------------------
| Load archived galleries
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

    WHERE pg.archived_at IS NOT NULL

    GROUP BY pg.id

    ORDER BY pg.archived_at DESC
");

$galleries =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<?php require __DIR__ . '/includes/admin-header.php'; ?>


<style>

/*
|--------------------------------------------------------------------------
| Archived Page
|--------------------------------------------------------------------------
*/

.archived-page {

    max-width: 1250px;

    margin: 0 auto;

}


.archived-header {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 1rem;

    flex-wrap: wrap;

    margin-bottom: 1.5rem;

}


.archived-header h1 {

    margin: 0;

    font-family: var(--font-serif);

}


.archived-header p {

    margin: .4rem 0 0;

    color: #7a716a;

}


.archived-actions {

    display: flex;

    gap: .6rem;

    flex-wrap: wrap;

}


.archived-info {

    background: #f5f0ff;

    border: 1px solid #d8c5ff;

    color: #4c2a85;

    border-radius: 12px;

    padding: 1rem 1.15rem;

    margin-bottom: 1.25rem;

    line-height: 1.55;

}


.archived-table-wrap {

    overflow-x: auto;

    background: #fff;

    border: 1px solid #e7e1db;

    border-radius: 14px;

}


.archived-table {

    width: 100%;

    border-collapse: collapse;

    min-width: 900px;

}


.archived-table th {

    text-align: left;

    padding: .9rem 1rem;

    font-size: .7rem;

    text-transform: uppercase;

    letter-spacing: .08em;

    color: #7a716a;

    background: #faf8f5;

    border-bottom: 1px solid #e7e1db;

}


.archived-table td {

    padding: 1rem;

    border-bottom: 1px solid #eee9e4;

    vertical-align: middle;

}


.archived-table tr:last-child td {

    border-bottom: 0;

}


.client-name {

    font-weight: 800;

}


.gallery-title {

    color: #7a716a;

    font-size: .82rem;

    margin-top: .2rem;

}


.archived-badge {

    display: inline-flex;

    align-items: center;

    gap: .3rem;

    padding: .35rem .65rem;

    border-radius: 999px;

    background: #f3e8ff;

    color: #6b21a8;

    font-size: .68rem;

    font-weight: 800;

}


.number-value {

    font-weight: 800;

}


.archived-date {

    font-size: .82rem;

    color: #6b7280;

}


.action-buttons {

    display: flex;

    gap: .45rem;

    align-items: center;

    flex-wrap: wrap;

}


.action-buttons form {

    margin: 0;

}


.view-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: .3rem;

}


.delete-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: .3rem;

    border: 0;

    cursor: pointer;

    background: #dc2626;

    color: #fff;

    padding: .55rem .8rem;

    border-radius: 8px;

    font-size: .78rem;

    font-weight: 700;

}


.delete-btn:hover {

    background: #b91c1c;

}


.empty-archived {

    text-align: center;

    padding: 4rem 1rem;

    color: #7a716a;

}


.empty-archived-icon {

    font-size: 3rem;

    margin-bottom: .7rem;

}


@media(max-width:700px) {

    .archived-actions {

        width: 100%;

    }

    .archived-actions .btn {

        width: 100%;

    }

}

</style>


<div class="archived-page">


    <!-- =====================================================
         HEADER
         ===================================================== -->

    <div class="archived-header">


        <div>

            <h1>
                📦 Archived Photo Selections
            </h1>

            <p>
                Archived galleries whose photo files have been
                cleaned from server storage.
            </p>

        </div>


        <div class="archived-actions">

            <a
                href="photo-selection.php"
                class="btn btn-ghost"
            >
                ← Back to Photo Selection
            </a>

        </div>


    </div>



    <!-- =====================================================
         INFO
         ===================================================== -->

    <div class="archived-info">

        <strong>
            📦 Archived Galleries
        </strong>

        <br>

        These galleries are kept for record purposes.

        Client, booking and selection information remain available.

        Original RAW files and unselected JPG/preview files
        were removed from server storage.

        <br><br>

        <strong>
            🟢 Selected JPGs are preserved.
        </strong>

        <br>

        If you no longer need an archived gallery at all,
        you can permanently delete it using the
        <strong>Delete</strong> button.

    </div>



    <!-- =====================================================
         ARCHIVED TABLE
         ===================================================== -->

    <?php if (!$galleries): ?>


        <div class="card empty-archived">

            <div class="empty-archived-icon">
                📦
            </div>

            <h2>
                No Archived Galleries
            </h2>

            <p>
                There are currently no archived photo
                selection galleries.
            </p>

        </div>


    <?php else: ?>


        <div class="archived-table-wrap">


            <table class="archived-table">


                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Client
                        </th>

                        <th>
                            Title
                        </th>

                        <th>
                            Photos
                        </th>

                        <th>
                            Selected
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Archived
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php foreach (
                        $galleries
                        as $gallery
                    ): ?>


                        <tr>


                            <!-- ID -->

                            <td>

                                #<?= (int)$gallery['id'] ?>

                            </td>


                            <!-- CLIENT -->

                            <td>

                                <div class="client-name">

                                    <?= htmlspecialchars(
                                        (string)$gallery['client_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </div>

                            </td>


                            <!-- TITLE -->

                            <td>

                                <div class="gallery-title">

                                    <?= htmlspecialchars(
                                        (string)$gallery['title'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </div>

                            </td>


                            <!-- PHOTOS -->

                            <td>

                                <span class="number-value">

                                    <?= (int)$gallery['total_photos'] ?>

                                </span>

                            </td>


                            <!-- SELECTED -->

                            <td>

                                <span class="number-value">

                                    <?= (int)$gallery['selected_photos'] ?>

                                </span>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span class="archived-badge">

                                    📦 Archived

                                </span>

                            </td>


                            <!-- ARCHIVED DATE -->

                            <td>

                                <div class="archived-date">

                                    <?= htmlspecialchars(
                                        (string)$gallery['archived_at'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </div>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div class="action-buttons">


                                    <!-- VIEW INFO -->

                                    <a
                                        href="photo-selection-view.php?id=<?= (int)$gallery['id'] ?>"
                                        class="btn btn-primary view-btn"
                                    >
                                        👁 View Info
                                    </a>


                                    <!-- DELETE -->

                                    <form
                                        method="POST"
                                        action="photo-selection-delete.php"
                                        onsubmit="return confirmArchivedDelete(this);"
                                    >

                                        <input
                                            type="hidden"
                                            name="gallery_id"
                                            value="<?= (int)$gallery['id'] ?>"
                                        >


                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= htmlspecialchars(
                                                $deleteToken,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="delete-btn"
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


<script>

/*
|--------------------------------------------------------------------------
| Confirm Permanent Delete
|--------------------------------------------------------------------------
*/

function confirmArchivedDelete(form) {

    const confirmed = confirm(
        "⚠️ PERMANENT DELETE\n\n" +

        "This archived gallery will be permanently deleted.\n\n" +

        "The gallery record, selected photos, JPG files and " +
        "photo selection records will be removed.\n\n" +

        "This action CANNOT be undone.\n\n" +

        "Are you sure you want to continue?"
    );


    return confirmed;
}

</script>


<?php require __DIR__ . '/includes/admin-footer.php'; ?>