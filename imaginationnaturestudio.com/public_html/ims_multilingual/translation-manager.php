<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

require_admin_only();

$pdo = db();

/* =========================================================
   TRANSLATION MANAGER
   English = source
   RW = Kinyarwanda
   FR = French
   ========================================================= */

$allowedTables = [
    'homepage_sections' => [
        'title',
        'subtitle',
        'content',
        'button_text',

        'title_rw',
        'subtitle_rw',
        'content_rw',
        'button_text_rw',

        'title_fr',
        'subtitle_fr',
        'content_fr',
        'button_text_fr',
    ],

    'services' => [
        'name',
        'description',

        'name_rw',
        'description_rw',

        'name_fr',
        'description_fr',
    ],

    'promotions' => [
        'title',
        'subtitle',
        'description',
        'button_text',

        'title_rw',
        'subtitle_rw',
        'description_rw',
        'button_text_rw',

        'title_fr',
        'subtitle_fr',
        'description_fr',
        'button_text_fr',
    ],

    'gallery' => [
        'title',
        'caption',
        'description',

        'title_rw',
        'caption_rw',
        'description_rw',

        'title_fr',
        'caption_fr',
        'description_fr',
    ],
];

$tabLabels = [
    'homepage_sections' => 'Homepage',
    'services'         => 'Services',
    'promotions'       => 'Promotions',
    'gallery'         => 'Gallery',
];

$tab = (string)($_GET['tab'] ?? 'homepage_sections');

if (!isset($allowedTables[$tab])) {
    $tab = 'homepage_sections';
}


/* =========================================================
   HELPERS
   ========================================================= */

function tm_columns(PDO $pdo, string $table): array
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $stmt = $pdo->query(
        'SHOW COLUMNS FROM `' . $table . '`'
    );

    $columns = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['Field'])) {
            $columns[] = (string)$row['Field'];
        }
    }

    $cache[$table] = $columns;

    return $columns;
}


function tm_e($value): string
{
    return e($value ?? '');
}


function tm_flash(string $type, string $message): void
{
    $_SESSION['tm_flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}


/* =========================================================
   CSRF
   ========================================================= */

if (empty($_SESSION['translation_manager_csrf'])) {
    $_SESSION['translation_manager_csrf'] = bin2hex(
        random_bytes(32)
    );
}

$csrf = (string)$_SESSION['translation_manager_csrf'];


/* =========================================================
   POST — SAVE TRANSLATIONS
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $postedToken = (string)(
            $_POST['csrf_token'] ?? ''
        );

        if (
            $postedToken === '' ||
            !hash_equals($csrf, $postedToken)
        ) {
            throw new RuntimeException(
                'Invalid security token. Please refresh the page and try again.'
            );
        }

        $postedTable = (string)(
            $_POST['table'] ?? ''
        );

        if (isset($allowedTables[$postedTable])) {
            $postedTab = $postedTable;
        } else {
            $postedTab = 'homepage_sections';
        }

        $columns = tm_columns(
            $pdo,
            $postedTab
        );

        $allowedFields =
            $allowedTables[$postedTab];

        $id = (int)(
            $_POST['id'] ?? 0
        );

        if ($id < 1) {
            throw new RuntimeException(
                'Invalid record selected.'
            );
        }

        if (!in_array(
            'id',
            $columns,
            true
        )) {
            throw new RuntimeException(
                'The selected table does not have an id column.'
            );
        }

        $sets = [];
        $values = [];

        foreach ($allowedFields as $field) {

            if (!in_array(
                $field,
                $columns,
                true
            )) {
                continue;
            }

            /*
             * Never modify English source fields.
             */
            if (
                !str_ends_with($field, '_rw') &&
                !str_ends_with($field, '_fr')
            ) {
                continue;
            }

            $postedFields =
                $_POST['fields'] ?? [];

            if (!is_array($postedFields)) {
                $postedFields = [];
            }

            $value = trim(
                (string)(
                    $postedFields[$field] ?? ''
                )
            );

            $sets[] =
                '`' . $field . '` = ?';

            $values[] =
                ($value === '')
                    ? null
                    : $value;
        }

        if ($sets === []) {

            throw new RuntimeException(
                'No translation columns are available. Run the multilingual database migration first.'
            );
        }

        $values[] = $id;

        $sql =
            'UPDATE `' . $postedTab . '` SET ' .
            implode(', ', $sets) .
            ' WHERE `id` = ? LIMIT 1';

        $stmt = $pdo->prepare($sql);

        $stmt->execute($values);

        tm_flash(
            'success',
            'Translations saved successfully.'
        );

        header(
            'Location: translation-manager.php?tab=' .
            rawurlencode($postedTab)
        );

        exit;

    } catch (Throwable $ex) {

        tm_flash(
            'error',
            $ex->getMessage()
        );

        $submittedTable =
            (string)(
                $_POST['table'] ?? ''
            );

        if (
            isset(
                $allowedTables[$submittedTable]
            )
        ) {
            $redirectTab =
                $submittedTable;
        } else {
            $redirectTab =
                'homepage_sections';
        }

        header(
            'Location: translation-manager.php?tab=' .
            rawurlencode($redirectTab)
        );

        exit;
    }
}


/* =========================================================
   FLASH
   ========================================================= */

$flash =
    $_SESSION['tm_flash'] ?? null;

unset($_SESSION['tm_flash']);


/* =========================================================
   LOAD CURRENT TABLE
   ========================================================= */

$columns = [];
$rows = [];
$loadError = null;

try {

    $columns =
        tm_columns(
            $pdo,
            $tab
        );

    if (!in_array(
        'id',
        $columns,
        true
    )) {
        throw new RuntimeException(
            'The selected table does not have an id column.'
        );
    }

    $fields = ['id'];

    foreach (
        $allowedTables[$tab]
        as $field
    ) {

        if (
            in_array(
                $field,
                $columns,
                true
            )
        ) {
            $fields[] = $field;
        }
    }

    $sql =
        'SELECT `' .
        implode('`, `', $fields) .
        '` FROM `' .
        $tab .
        '` ORDER BY `id` ASC';

    $stmt =
        $pdo->query($sql);

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $ex) {

    $loadError =
        $ex->getMessage();
}


/* =========================================================
   SOURCE FIELDS
   ========================================================= */

function tm_source_fields(
    string $table
): array {

    switch ($table) {

        case 'homepage_sections':

            return [
                'title' =>
                    'Title',

                'subtitle' =>
                    'Subtitle',

                'content' =>
                    'Content',

                'button_text' =>
                    'Button Text',
            ];

        case 'services':

            return [
                'name' =>
                    'Name',

                'description' =>
                    'Description',
            ];

        case 'promotions':

            return [
                'title' =>
                    'Title',

                'subtitle' =>
                    'Subtitle',

                'description' =>
                    'Description',

                'button_text' =>
                    'Button Text',
            ];

        case 'gallery':

            return [
                'title' =>
                    'Title',

                'caption' =>
                    'Caption',

                'description' =>
                    'Description',
            ];

        default:

            return [];
    }
}


$sourceFields =
    tm_source_fields($tab);

require __DIR__ . '/includes/admin-header.php';
?>

<style>

.tm-wrap{
    max-width:1500px;
    margin:0 auto;
    padding:28px 24px 60px;
}

.tm-head{
    display:flex;
    justify-content:space-between;
    gap:20px;
    align-items:flex-start;
    margin-bottom:22px;
}

.tm-head h1{
    margin:0 0 7px;
    font-size:30px;
    font-weight:900;
}

.tm-head p{
    margin:0;
    color:#777;
    line-height:1.6;
}

.tm-tabs{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin:0 0 22px;
}

.tm-tabs a{
    display:inline-flex;
    align-items:center;
    padding:10px 15px;
    border-radius:10px;
    text-decoration:none;
    font-weight:800;
    background:#f2f2f2;
    color:#333;
}

.tm-tabs a.active{
    background:#111;
    color:#fff;
}

.tm-alert{
    padding:13px 16px;
    border-radius:10px;
    margin-bottom:18px;
    font-weight:700;
}

.tm-alert.success{
    background:#e7f7ed;
    color:#126b36;
}

.tm-alert.error{
    background:#fde9e9;
    color:#a21c1c;
}

.tm-card{
    background:#fff;
    border:1px solid #e7e7e7;
    border-radius:16px;
    box-shadow:0 5px 25px rgba(0,0,0,.05);
    overflow:hidden;
    margin-bottom:18px;
}

.tm-card-head{
    padding:17px 20px;
    border-bottom:1px solid #eee;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
}

.tm-card-head strong{
    font-size:18px;
}

.tm-card-head span{
    font-size:12px;
    color:#888;
}

.tm-body{
    padding:20px;
}

.tm-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}

.tm-box{
    border:1px solid #e6e6e6;
    border-radius:12px;
    padding:14px;
    background:#fafafa;
}

.tm-box h3{
    margin:0 0 10px;
    font-size:13px;
    text-transform:uppercase;
    letter-spacing:.06em;
}

.tm-box.en{
    background:#f5f5f5;
}

.tm-box.rw{
    background:#f7fbf8;
}

.tm-box.fr{
    background:#f8f8fc;
}

.tm-label{
    font-size:12px;
    font-weight:800;
    color:#666;
    margin:10px 0 6px;
    display:block;
}

.tm-input,
.tm-textarea{
    width:100%;
    box-sizing:border-box;
    border:1px solid #d8d8d8;
    border-radius:9px;
    padding:10px 11px;
    background:#fff;
    font:inherit;
}

.tm-input:focus,
.tm-textarea:focus{
    outline:none;
    border-color:#777;
    box-shadow:0 0 0 3px rgba(0,0,0,.05);
}

.tm-textarea{
    min-height:105px;
    resize:vertical;
    line-height:1.5;
}

.tm-source{
    white-space:pre-wrap;
    line-height:1.5;
    color:#444;
    min-height:40px;
}

.tm-actions{
    display:flex;
    justify-content:flex-end;
    padding:15px 20px;
    border-top:1px solid #eee;
    background:#fafafa;
}

.tm-save{
    border:0;
    border-radius:10px;
    padding:11px 20px;
    background:#111;
    color:#fff;
    font-weight:900;
    cursor:pointer;
}

.tm-save:hover{
    opacity:.9;
}

.tm-missing{
    color:#a21c1c;
    background:#fff1f1;
    border:1px solid #f0cccc;
    padding:12px;
    border-radius:10px;
    margin-bottom:18px;
}

@media(max-width:900px){

    .tm-grid{
        grid-template-columns:1fr;
    }

    .tm-wrap{
        padding:20px 14px 45px;
    }

    .tm-head{
        flex-direction:column;
    }
}

</style>


<div class="tm-wrap">

    <div class="tm-head">

        <div>

            <h1>
                🌍 Website Translations
            </h1>

            <p>
                Manage the public website content in English, Kinyarwanda and French.
            </p>

        </div>

    </div>


    <?php if (
        is_array($flash) &&
        !empty($flash['message'])
    ): ?>

        <div class="tm-alert
            <?= (
                ($flash['type'] ?? '') === 'error'
            )
                ? 'error'
                : 'success'
            ?>">

            <?= tm_e(
                $flash['message']
            ) ?>

        </div>

    <?php endif; ?>


    <div class="tm-tabs">

        <?php foreach (
            $tabLabels as $table => $label
        ): ?>

            <a
                href="translation-manager.php?tab=<?= e($table) ?>"
                class="<?= (
                    $tab === $table
                )
                    ? 'active'
                    : ''
                ?>"
            >

                <?= e($label) ?>

            </a>

        <?php endforeach; ?>

    </div>


    <?php if (
        $loadError !== null
    ): ?>

        <div class="tm-alert error">

            <?= tm_e(
                $loadError
            ) ?>

        </div>

    <?php endif; ?>


    <?php

    $missingColumns = [];

    foreach (
        $allowedTables[$tab]
        as $field
    ) {

        if (
            !in_array(
                $field,
                $columns,
                true
            )
        ) {
            $missingColumns[] =
                $field;
        }
    }

    ?>


    <?php if (
        $missingColumns
    ): ?>

        <div class="tm-missing">

            <strong>
                Some translation columns are missing:
            </strong>

            <?= tm_e(
                implode(
                    ', ',
                    $missingColumns
                )
            ) ?>

            <br>

            <small>
                Run the multilingual database migration before using these fields.
            </small>

        </div>

    <?php endif; ?>


    <?php if (
        !$rows &&
        $loadError === null
    ): ?>

        <div class="tm-card">

            <div class="tm-body">

                No records found in this section.

            </div>

        </div>

    <?php endif; ?>


    <?php foreach (
        $rows as $row
    ): ?>

        <div class="tm-card">

            <div class="tm-card-head">

                <strong>

                    <?= e(
                        $tabLabels[$tab]
                    ) ?>

                    #

                    <?= (int)$row['id'] ?>

                </strong>

                <span>
                    English is the original/source content
                </span>

            </div>


            <form method="post">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrf) ?>"
                >

                <input
                    type="hidden"
                    name="table"
                    value="<?= e($tab) ?>"
                >

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int)$row['id'] ?>"
                >


                <div class="tm-body">

                    <div class="tm-grid">


                        <!-- ENGLISH -->

                        <div class="tm-box en">

                            <h3>
                                🇬🇧 English — Original
                            </h3>

                            <?php foreach (
                                $sourceFields
                                as $field => $label
                            ): ?>

                                <?php if (
                                    in_array(
                                        $field,
                                        $columns,
                                        true
                                    )
                                ): ?>

                                    <span class="tm-label">
                                        <?= e($label) ?>
                                    </span>

                                    <div class="tm-source">

                                        <?= tm_e(
                                            $row[$field] ?? ''
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            <?php endforeach; ?>

                        </div>


                        <!-- KINYARWANDA -->

                        <div class="tm-box rw">

                            <h3>
                                🇷🇼 Kinyarwanda
                            </h3>

                            <?php foreach (
                                $sourceFields
                                as $field => $label
                            ): ?>

                                <?php

                                $rw =
                                    $field . '_rw';

                                ?>

                                <?php if (
                                    in_array(
                                        $rw,
                                        $columns,
                                        true
                                    )
                                ): ?>

                                    <label
                                        class="tm-label"
                                        for="tm_<?= (int)$row['id'] ?>_<?= e($rw) ?>"
                                    >

                                        <?= e($label) ?>

                                    </label>


                                    <?php if (
                                        $field === 'content' ||
                                        $field === 'description'
                                    ): ?>

                                        <textarea
                                            class="tm-textarea"
                                            id="tm_<?= (int)$row['id'] ?>_<?= e($rw) ?>"
                                            name="fields[<?= e($rw) ?>]"
                                        ><?= tm_e(
                                            $row[$rw] ?? ''
                                        ) ?></textarea>

                                    <?php else: ?>

                                        <input
                                            class="tm-input"
                                            id="tm_<?= (int)$row['id'] ?>_<?= e($rw) ?>"
                                            name="fields[<?= e($rw) ?>]"
                                            value="<?= tm_e(
                                                $row[$rw] ?? ''
                                            ) ?>"
                                        >

                                    <?php endif; ?>

                                <?php endif; ?>

                            <?php endforeach; ?>

                        </div>


                        <!-- FRENCH -->

                        <div class="tm-box fr">

                            <h3>
                                🇫🇷 Français
                            </h3>

                            <?php foreach (
                                $sourceFields
                                as $field => $label
                            ): ?>

                                <?php

                                $fr =
                                    $field . '_fr';

                                ?>

                                <?php if (
                                    in_array(
                                        $fr,
                                        $columns,
                                        true
                                    )
                                ): ?>

                                    <label
                                        class="tm-label"
                                        for="tm_<?= (int)$row['id'] ?>_<?= e($fr) ?>"
                                    >

                                        <?= e($label) ?>

                                    </label>


                                    <?php if (
                                        $field === 'content' ||
                                        $field === 'description'
                                    ): ?>

                                        <textarea
                                            class="tm-textarea"
                                            id="tm_<?= (int)$row['id'] ?>_<?= e($fr) ?>"
                                            name="fields[<?= e($fr) ?>]"
                                        ><?= tm_e(
                                            $row[$fr] ?? ''
                                        ) ?></textarea>

                                    <?php else: ?>

                                        <input
                                            class="tm-input"
                                            id="tm_<?= (int)$row['id'] ?>_<?= e($fr) ?>"
                                            name="fields[<?= e($fr) ?>]"
                                            value="<?= tm_e(
                                                $row[$fr] ?? ''
                                            ) ?>"
                                        >

                                    <?php endif; ?>

                                <?php endif; ?>

                            <?php endforeach; ?>

                        </div>


                    </div>

                </div>


                <div class="tm-actions">

                    <button
                        class="tm-save"
                        type="submit"
                    >
                        Save Translations
                    </button>

                </div>

            </form>

        </div>

    <?php endforeach; ?>

</div>