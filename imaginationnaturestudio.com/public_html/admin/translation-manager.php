<?php
declare(strict_types=1);

/**
 * Imagination Nature Studio
 * Website Translation Manager
 *
 * Languages:
 * English / Kinyarwanda / Français
 *
 * Admin only.
 */

require_once __DIR__ . '/../includes/functions.php';

require_admin_only();

$pageTitle = 'Website Translations';

$pdo = db();

$csrf = csrf_token();


/* =========================================================
   SUPPORTED TABS
   ========================================================= */

$allowedTabs = [
    'homepage',
    'services',
    'packages',
    'promotions',
    'gallery'
];

$tab = (string)($_GET['tab'] ?? 'homepage');

if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'homepage';
}


/* =========================================================
   MESSAGES
   ========================================================= */

$flash = null;
$error = null;


/* =========================================================
   TABLE WHITELIST
   ========================================================= */

$allowedTables = [
    'homepage'    => 'homepage_sections',
    'services'    => 'services',
    'packages'    => 'packages',
    'promotions'  => 'promotions',
    'gallery'     => 'gallery',
];


/* =========================================================
   GET TABLE COLUMNS
   ========================================================= */

function tm_get_columns(
    PDO $pdo,
    string $table
): array {

    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $allowedTables = [
        'homepage_sections',
        'services',
        'packages',
        'promotions',
        'gallery'
    ];

    if (!in_array($table, $allowedTables, true)) {
        throw new RuntimeException(
            'Invalid table.'
        );
    }

    $stmt = $pdo->query(
        "SHOW COLUMNS FROM `{$table}`"
    );

    $columns = [];

    while (
        $row = $stmt->fetch(PDO::FETCH_ASSOC)
    ) {

        if (
            isset($row['Field']) &&
            $row['Field'] !== ''
        ) {

            $columns[] =
                (string)$row['Field'];
        }
    }

    $cache[$table] = $columns;

    return $columns;
}


/* =========================================================
   CHECK COLUMN
   ========================================================= */

function tm_has_column(
    PDO $pdo,
    string $table,
    string $column
): bool {

    $columns =
        tm_get_columns(
            $pdo,
            $table
        );

    return in_array(
        $column,
        $columns,
        true
    );
}


/* =========================================================
   ESCAPE
   ========================================================= */

function tm_e($value): string
{
    return e(
        $value ?? ''
    );
}


/* =========================================================
   PREVIEW
   ========================================================= */

function tm_preview(
    string $value,
    int $limit = 180
): string {

    $value =
        trim(
            strip_tags($value)
        );

    if (
        mb_strlen($value) <= $limit
    ) {

        return $value;
    }

    return mb_substr(
        $value,
        0,
        $limit - 1
    ) . '…';
}


/* =========================================================
   UPDATE TRANSLATIONS
   ========================================================= */

function tm_update(
    PDO $pdo,
    string $table,
    int $id,
    array $values
): void {

    $allowedTables = [
        'homepage_sections',
        'services',
        'packages',
        'promotions',
        'gallery'
    ];

    if (
        !in_array(
            $table,
            $allowedTables,
            true
        )
    ) {

        throw new RuntimeException(
            'Invalid translation table.'
        );
    }

    if ($id < 1) {

        throw new RuntimeException(
            'Invalid record selected.'
        );
    }

    if (!$values) {

        throw new RuntimeException(
            'No translation fields were submitted.'
        );
    }


    $columns =
        tm_get_columns(
            $pdo,
            $table
        );


    $sets = [];

    $params = [];


    foreach (
        $values as $column => $value
    ) {

        /*
         * Only existing columns.
         */
        if (
            !in_array(
                $column,
                $columns,
                true
            )
        ) {

            continue;
        }


        /*
         * Only RW / FR columns.
         */
        if (
            !str_ends_with(
                $column,
                '_rw'
            )
            &&
            !str_ends_with(
                $column,
                '_fr'
            )
        ) {

            continue;
        }


        if (
            !preg_match(
                '/^[A-Za-z0-9_]+$/',
                $column
            )
        ) {

            throw new RuntimeException(
                'Invalid translation field.'
            );
        }


        $sets[] =
            "`{$column}` = ?";


        $params[] =
            ($value === '')
                ? null
                : $value;
    }


    if (!$sets) {

        throw new RuntimeException(
            'No valid translation columns were found in the database.'
        );
    }


    $params[] = $id;


    $sql =
        "UPDATE `{$table}` SET " .
        implode(
            ', ',
            $sets
        ) .
        " WHERE id = ? LIMIT 1";


    $stmt =
        $pdo->prepare($sql);


    $stmt->execute(
        $params
    );
}


/* =========================================================
   GET FIELDS FOR EACH TABLE
   ========================================================= */

function tm_possible_fields(
    string $tab
): array {

    switch ($tab) {

        case 'homepage':

            return [
                'title' => 'Title',
                'subtitle' => 'Subtitle',
                'content' => 'Content',
                'button_text' => 'Button text',
            ];


        case 'services':

            return [
                'name' => 'Service name',
                'description' => 'Description',
            ];


        case 'packages':

            return [
                'name' => 'Package name',
                'description' => 'Description',
            ];


        case 'promotions':

            return [
                'title' => 'Title',
                'subtitle' => 'Subtitle',
                'description' => 'Description',
                'button_text' => 'Button text',
            ];


        case 'gallery':

            /*
             * IMPORTANT:
             * caption is NOT assumed to exist.
             *
             * The database columns are checked later.
             */

            return [
                'title' => 'Title',
                'caption' => 'Caption',
                'description' => 'Description',
            ];


        default:

            return [];
    }
}


/* =========================================================
   POST — SAVE TRANSLATIONS
   ========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    try {

        verify_csrf();


        $action =
            (string)(
                $_POST['action'] ?? ''
            );


        if (
            $action !== 'save'
        ) {

            throw new RuntimeException(
                'Invalid action.'
            );
        }


        $recordId =
            (int)(
                $_POST['id'] ?? 0
            );


        if ($recordId < 1) {

            throw new RuntimeException(
                'Invalid record selected.'
            );
        }


        $postTab =
            (string)(
                $_POST['tab'] ?? 'homepage'
            );


        if (
            !in_array(
                $postTab,
                $allowedTabs,
                true
            )
        ) {

            throw new RuntimeException(
                'Invalid translation section.'
            );
        }


        $table =
            $allowedTables[$postTab];


        $columns =
            tm_get_columns(
                $pdo,
                $table
            );


        $possibleFields =
            tm_possible_fields(
                $postTab
            );


        $values = [];


        foreach (
            ['rw', 'fr']
            as $locale
        ) {

            foreach (
                $possibleFields
                as $field => $label
            ) {

                $translationColumn =
                    $field . '_' . $locale;


                /*
                 * If the database doesn't have
                 * this translation column,
                 * simply skip it.
                 */

                if (
                    !in_array(
                        $translationColumn,
                        $columns,
                        true
                    )
                ) {

                    continue;
                }


                $value =
                    trim(
                        (string)(
                            $_POST[
                                $translationColumn
                            ] ?? ''
                        )
                    );


                $values[
                    $translationColumn
                ] =
                    ($value === '')
                        ? null
                        : $value;
            }
        }


        if (!$values) {

            throw new RuntimeException(
                'No translation columns are available for this record.'
            );
        }


        tm_update(
            $pdo,
            $table,
            $recordId,
            $values
        );


        $_SESSION['tm_success'] =
            'Translations saved successfully.';


        header(
            'Location: translation-manager.php?tab=' .
            rawurlencode($postTab)
        );

        exit;


    } catch (Throwable $ex) {

        $error =
            $ex->getMessage();
    }
}


/* =========================================================
   FLASH
   ========================================================= */

if (
    !empty(
        $_SESSION['tm_success']
    )
) {

    $flash =
        (string)
        $_SESSION['tm_success'];

    unset(
        $_SESSION['tm_success']
    );
}


/* =========================================================
   LOAD CURRENT TABLE
   ========================================================= */

$rows = [];

$loadColumns = [];

try {

    $table =
        $allowedTables[$tab];


    $loadColumns =
        tm_get_columns(
            $pdo,
            $table
        );


    if (
        !in_array(
            'id',
            $loadColumns,
            true
        )
    ) {

        throw new RuntimeException(
            "Table {$table} does not contain an id column."
        );
    }


    /*
     * Always load ID.
     */

    $selectFields = [
        'id'
    ];


    /*
     * Homepage has section_key.
     */

    if (
        $tab === 'homepage' &&
        in_array(
            'section_key',
            $loadColumns,
            true
        )
    ) {

        $selectFields[] =
            'section_key';
    }


    /*
     * Add source fields only when
     * they actually exist.
     */

    $possibleFields =
        tm_possible_fields(
            $tab
        );


    foreach (
        array_keys(
            $possibleFields
        )
        as $field
    ) {

        if (
            in_array(
                $field,
                $loadColumns,
                true
            )
        ) {

            $selectFields[] =
                $field;
        }
    }


    /*
     * Add RW / FR fields only when
     * they exist.
     */

    foreach (
        ['rw', 'fr']
        as $locale
    ) {

        foreach (
            array_keys(
                $possibleFields
            )
            as $field
        ) {

            $translationColumn =
                $field . '_' . $locale;


            if (
                in_array(
                    $translationColumn,
                    $loadColumns,
                    true
                )
            ) {

                $selectFields[] =
                    $translationColumn;
            }
        }
    }


    /*
     * Remove duplicates.
     */

    $selectFields =
        array_values(
            array_unique(
                $selectFields
            )
        );


    /*
     * Safely quote fields.
     */

    $quotedFields = [];

    foreach (
        $selectFields
        as $field
    ) {

        $quotedFields[] =
            '`' . $field . '`';
    }


    $sql =
        "SELECT " .
        implode(
            ', ',
            $quotedFields
        ) .
        " FROM `{$table}`";


    /*
     * Sort records.
     */

    if (
        in_array(
            'id',
            $loadColumns,
            true
        )
    ) {

        if (
            $tab === 'homepage' ||
            $tab === 'services'
        ) {

            $sql .=
                " ORDER BY id ASC";

        } else {

            $sql .=
                " ORDER BY id DESC";
        }
    }


    $stmt =
        $pdo->query($sql);


    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $ex) {

    $error =
        $ex->getMessage();
}


/* =========================================================
   ADMIN HEADER
   ========================================================= */

require __DIR__ .
    '/includes/admin-header.php';

?>

<style>

.tm-wrap{
    max-width:1500px;
    margin:0 auto;
    padding:90px 24px 50px;
}

.tm-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-end;
    gap:20px;
    margin-bottom:22px;
    flex-wrap:wrap;
}

.tm-head h1{
    margin:0;
    color:#f3ece3;
    font-size:2rem;
    font-weight:800;
}

.tm-head p{
    margin:.45rem 0 0;
    color:#aaa09a;
    line-height:1.5;
}

.tm-tabs{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin-bottom:18px;
}

.tm-tab{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:10px 15px;
    border:1px solid #393331;
    border-radius:10px;
    color:#d9d0ca;
    text-decoration:none;
    background:#151311;
}

.tm-tab:hover,
.tm-tab.active{
    background:#292421;
    color:#fff;
    border-color:#615750;
}

.tm-alert{
    padding:13px 15px;
    border-radius:10px;
    margin:0 0 18px;
    background:#193b2a;
    color:#dff8e9;
    border:1px solid #2c6547;
}

.tm-error{
    background:#431d1d;
    color:#ffe5e5;
    border-color:#713333;
}

.tm-card{
    background:#11100f;
    border:1px solid #292624;
    border-radius:14px;
    overflow:hidden;
    margin-bottom:18px;
}

.tm-card-head{
    padding:15px 18px;
    border-bottom:1px solid #292624;
    display:flex;
    justify-content:space-between;
    gap:12px;
    align-items:center;
    flex-wrap:wrap;
}

.tm-card-head h2{
    margin:0;
    color:#f4eee9;
    font-size:1.05rem;
}

.tm-card-head small{
    color:#89817c;
}

.tm-grid{
    display:grid;
    grid-template-columns:
        repeat(
            2,
            minmax(0,1fr)
        );
    gap:14px;
    padding:18px;
}

.tm-field label{
    display:block;
    font-size:.76rem;
    text-transform:uppercase;
    letter-spacing:.08em;
    color:#9c938d;
    margin-bottom:6px;
}

.tm-field input,
.tm-field textarea{
    width:100%;
    box-sizing:border-box;
    background:#181614;
    border:1px solid #393431;
    border-radius:9px;
    color:#f7f1ec;
    padding:11px 12px;
    font:inherit;
}

.tm-field textarea{
    min-height:115px;
    resize:vertical;
    line-height:1.5;
}

.tm-field input:focus,
.tm-field textarea:focus{
    outline:none;
    border-color:#8d7c6d;
    box-shadow:
        0 0 0 2px
        rgba(
            141,
            124,
            109,
            .15
        );
}

.tm-field.full{
    grid-column:1/-1;
}

.tm-actions{
    display:flex;
    justify-content:flex-end;
    gap:10px;
    padding:0 18px 18px;
}

.tm-btn{
    border:0;
    border-radius:9px;
    padding:11px 17px;
    background:#e9e0d7;
    color:#171310;
    font-weight:700;
    cursor:pointer;
}

.tm-btn:hover{
    opacity:.9;
}

.tm-id{
    color:#827a74;
    font-size:.8rem;
}

.tm-source{
    background:#171513;
    border:1px solid #302d2a;
    border-radius:8px;
    padding:10px 12px;
    color:#b9b0aa;
    line-height:1.55;
    white-space:normal;
    max-height:240px;
    overflow:auto;
}

.tm-source-row{
    margin-bottom:8px;
}

.tm-source-row:last-child{
    margin-bottom:0;
}

.tm-missing{
    margin-top:8px;
    padding:9px 11px;
    border-radius:8px;
    background:#241f1b;
    border:1px solid #40362f;
    color:#a99f97;
    font-size:.78rem;
}

.tm-empty{
    color:#aaa09a;
    padding:18px;
}

@media(max-width:900px){

    .tm-grid{
        grid-template-columns:1fr;
    }

    .tm-field.full{
        grid-column:auto;
    }

    .tm-wrap{
        padding:70px 14px 40px;
    }
}

</style>


<div class="tm-wrap">


    <!-- =====================================================
         HEADER
         ===================================================== -->

    <div class="tm-head">

        <div>

            <h1>
                🌍 Website Translations
            </h1>

            <p>
                Manage Kinyarwanda and French content
                while keeping the original English content unchanged.
            </p>

        </div>

    </div>


    <!-- =====================================================
         SUCCESS
         ===================================================== -->

    <?php if ($flash): ?>

        <div class="tm-alert">

            <?= tm_e($flash) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         ERROR
         ===================================================== -->

    <?php if ($error): ?>

        <div class="tm-alert tm-error">

            <?= tm_e($error) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         TABS
         ===================================================== -->

    <div class="tm-tabs">

        <a
            class="tm-tab
                <?= $tab === 'homepage'
                    ? 'active'
                    : '' ?>"
            href="translation-manager.php?tab=homepage"
        >
            🏠 Homepage
        </a>


        <a
            class="tm-tab
                <?= $tab === 'services'
                    ? 'active'
                    : '' ?>"
            href="translation-manager.php?tab=services"
        >
            📸 Services
        </a>


        <a
            class="tm-tab
                <?= $tab === 'packages'
                    ? 'active'
                    : '' ?>"
            href="translation-manager.php?tab=packages"
        >
            📦 Packages
        </a>


        <a
            class="tm-tab
                <?= $tab === 'promotions'
                    ? 'active'
                    : '' ?>"
            href="translation-manager.php?tab=promotions"
        >
            🎯 Promotions
        </a>


        <a
            class="tm-tab
                <?= $tab === 'gallery'
                    ? 'active'
                    : '' ?>"
            href="translation-manager.php?tab=gallery"
        >
            🖼️ Gallery
        </a>

    </div>


    <!-- =====================================================
         RECORDS
         ===================================================== -->

    <?php if (!$rows): ?>

        <div class="tm-card">

            <div class="tm-empty">

                No records were found in this section.

            </div>

        </div>

    <?php endif; ?>


    <?php foreach (
        $rows
        as $row
    ): ?>


        <?php

        /*
         * Available fields for this tab.
         */

        $possibleFields =
            tm_possible_fields(
                $tab
            );


        /*
         * Only display fields whose
         * source column actually exists.
         */

        $fields = [];

        foreach (
            $possibleFields
            as $key => $label
        ) {

            if (
                in_array(
                    $key,
                    $loadColumns,
                    true
                )
            ) {

                $fields[
                    $key
                ] = $label;
            }
        }


        /*
         * Card title.
         */

        if (
            $tab === 'homepage'
        ) {

            $title =
                (string)(
                    $row['section_key']
                    ?? 'Homepage section'
                );

        } elseif (
            isset($row['name'])
        ) {

            $title =
                (string)$row['name'];

        } elseif (
            isset($row['title'])
        ) {

            $title =
                (string)$row['title'];

        } else {

            $title =
                'Record';
        }

        ?>


        <div class="tm-card">


            <!-- =================================================
                 CARD HEADER
                 ================================================= -->

            <div class="tm-card-head">

                <h2>

                    <?= tm_e(
                        $title ?: 'Untitled'
                    ) ?>

                </h2>


                <small class="tm-id">

                    ID #

                    <?= (int)$row['id'] ?>

                </small>

            </div>


            <!-- =================================================
                 ENGLISH SOURCE
                 ================================================= -->

            <div
                class="tm-grid"
                style="padding-bottom:0"
            >

                <div
                    class="tm-field full"
                >

                    <label>
                        🇬🇧 English — original content
                    </label>


                    <div class="tm-source">


                        <?php foreach (
                            $fields
                            as $key => $label
                        ): ?>


                            <?php

                            $source =
                                trim(
                                    (string)(
                                        $row[$key]
                                        ?? ''
                                    )
                                );

                            ?>


                            <?php if (
                                $source !== ''
                            ): ?>

                                <div
                                    class="tm-source-row"
                                >

                                    <strong>

                                        <?= tm_e(
                                            $label
                                        ) ?>:

                                    </strong>

                                    <?= tm_e(
                                        tm_preview(
                                            $source
                                        )
                                    ) ?>

                                </div>

                            <?php endif; ?>


                        <?php endforeach; ?>


                    </div>

                </div>

            </div>


            <!-- =================================================
                 TRANSLATION FORM
                 ================================================= -->

            <form
                method="post"
                class="tm-grid"
            >


                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= tm_e($csrf) ?>"
                >


                <input
                    type="hidden"
                    name="action"
                    value="save"
                >


                <input
                    type="hidden"
                    name="tab"
                    value="<?= tm_e($tab) ?>"
                >


                <input
                    type="hidden"
                    name="id"
                    value="<?= (int)$row['id'] ?>"
                >


                <?php foreach (
                    $fields
                    as $key => $label
                ): ?>


                    <?php

                    $rwColumn =
                        $key . '_rw';

                    $frColumn =
                        $key . '_fr';


                    $hasRw =
                        in_array(
                            $rwColumn,
                            $loadColumns,
                            true
                        );


                    $hasFr =
                        in_array(
                            $frColumn,
                            $loadColumns,
                            true
                        );

                    ?>


                    <!-- ================================
                         KINYARWANDA
                         ================================ -->

                    <?php if ($hasRw): ?>

                        <div class="tm-field">

                            <label>

                                🇷🇼
                                <?= tm_e($label) ?>

                                — Kinyarwanda

                            </label>


                            <?php

                            $rwValue =
                                (string)(
                                    $row[
                                        $rwColumn
                                    ] ?? ''
                                );

                            ?>


                            <?php if (
                                $key === 'content' ||
                                $key === 'description' ||
                                $key === 'caption'
                            ): ?>

                                <textarea
                                    name="<?= tm_e(
                                        $rwColumn
                                    ) ?>"
                                ><?= tm_e(
                                    $rwValue
                                ) ?></textarea>

                            <?php else: ?>

                                <input
                                    type="text"
                                    name="<?= tm_e(
                                        $rwColumn
                                    ) ?>"
                                    value="<?= tm_e(
                                        $rwValue
                                    ) ?>"
                                >

                            <?php endif; ?>

                        </div>

                    <?php endif; ?>


                    <!-- ================================
                         FRENCH
                         ================================ -->

                    <?php if ($hasFr): ?>

                        <div class="tm-field">

                            <label>

                                🇫🇷
                                <?= tm_e($label) ?>

                                — Français

                            </label>


                            <?php

                            $frValue =
                                (string)(
                                    $row[
                                        $frColumn
                                    ] ?? ''
                                );

                            ?>


                            <?php if (
                                $key === 'content' ||
                                $key === 'description' ||
                                $key === 'caption'
                            ): ?>

                                <textarea
                                    name="<?= tm_e(
                                        $frColumn
                                    ) ?>"
                                ><?= tm_e(
                                    $frValue
                                ) ?></textarea>

                            <?php else: ?>

                                <input
                                    type="text"
                                    name="<?= tm_e(
                                        $frColumn
                                    ) ?>"
                                    value="<?= tm_e(
                                        $frValue
                                    ) ?>"
                                >

                            <?php endif; ?>

                        </div>

                    <?php endif; ?>


                <?php endforeach; ?>


                <!-- =================================================
                     SAVE BUTTON
                     ================================================= -->

                <div
                    class="tm-actions"
                    style="grid-column:1/-1"
                >

                    <button
                        class="tm-btn"
                        type="submit"
                    >

                        Save Translations

                    </button>

                </div>


            </form>


        </div>


    <?php endforeach; ?>


</div>


<?php

require __DIR__ .
    '/includes/admin-footer.php';

?>