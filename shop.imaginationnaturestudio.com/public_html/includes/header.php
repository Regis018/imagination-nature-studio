```php
<?php
declare(strict_types=1);

/*
 * Imagination Nature Studio — Shop Header
 * Temporary language diagnostic enabled.
 */

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}

if (!isset($pdo)) {
    require_once __DIR__ . '/db.php';
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/whatsapp.php';
require_once __DIR__ . '/language.php';

$storeName = setting(
    'store_name',
    'Imagination Nature Studio'
) ?? 'Imagination Nature Studio';

$pageTitle = $pageTitle ?? (
    $storeName . ' — Premium Frames in Rwanda'
);

$metaDescription = $metaDescription ??
    'Football club frames, football legends, celebrity portraits and personalized wall art in Rwanda.';

$cartCount = get_cart_count();

$currentLanguage = fz_lang();
$languages = fz_languages();

/*
 * TEMPORARY DIAGNOSTIC BANNER
 * Remove this block after the language test.
 */
echo '<div style="
    background:#ffeb3b;
    color:#111;
    padding:12px;
    text-align:center;
    font-weight:bold;
    position:relative;
    z-index:99999;
">';
echo 'LANG=' . htmlspecialchars(
    fz_lang(),
    ENT_QUOTES,
    'UTF-8'
);
echo ' | MENU=' . htmlspecialchars(
    fz_t('football_clubs'),
    ENT_QUOTES,
    'UTF-8'
);
echo '</div>';

/*
 * Current URL and query parameters.
 */
$requestUri = $_SERVER['REQUEST_URI'] ?? '/shop/index.php';

$currentPath = parse_url($requestUri, PHP_URL_PATH)
    ?: '/shop/index.php';

$currentQuery = $_GET ?? [];
unset($currentQuery['lang']);

/*
 * Canonical URL.
 */
if (!isset($canonical)) {
    $host = $_SERVER['HTTP_HOST']
        ?? 'imaginationnaturestudio.com';

    $canonical = 'https://' . $host . $currentPath;
}

$ogImage = $ogImage ?? '';
?>
<!doctype html>
<html lang="<?= htmlspecialchars(
    $currentLanguage,
    ENT_QUOTES,
    'UTF-8'
) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= e($pageTitle) ?></title>

    <meta
        name="description"
        content="<?= e($metaDescription) ?>"
    >

    <link rel="canonical" href="<?= e($canonical) ?>">

    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e($canonical) ?>">

    <?php if ($ogImage !== ''): ?>
        <meta property="og:image" content="<?= e($ogImage) ?>">
    <?php endif; ?>

    <link
        rel="stylesheet"
        href="<?= e(url('assets/css/style.css')) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(url('assets/css/language.css')) ?>"
    >

    <style>
        .fz-language {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        .fz-language label {
            color: inherit;
            font-size: 14px;
        }

        .fz-language select {
            max-width: 165px;
            padding: 8px 10px;
            border: 1px solid #c9a44c;
            border-radius: 8px;
            background: #171717;
            color: #fff;
            font: inherit;
            font-size: 12px;
            cursor: pointer;
        }

        .fz-language select:focus {
            outline: 2px solid #c9a44c;
            outline-offset: 2px;
        }

        @media (max-width: 760px) {
            .fz-language select {
                max-width: 135px;
                padding: 7px;
            }
        }
    </style>
</head>

<body>

<header class="site-header" id="siteHeader">
    <div class="container header-inner">

        <a class="brand" href="<?= e(url('index.php')) ?>">
            <span class="brand-mark">◈</span>
            <span class="brand-name"><?= e($storeName) ?></span>
        </a>

        <nav class="main-nav" id="mainNav">

            <a href="<?= e(url('index.php')) ?>">
                <?= e(fz_t('home')) ?>
            </a>

            <a href="<?= e(url('football-clubs.php')) ?>">
                <?= e(fz_t('football_clubs')) ?>
            </a>

            <a href="<?= e(url('football-legends.php')) ?>">
                <?= e(fz_t('legends')) ?>
            </a>

            <a href="<?= e(url('celebrities.php')) ?>">
                <?= e(fz_t('celebrities')) ?>
            </a>

            <a href="<?= e(url('custom-frames.php')) ?>">
                <?= e(fz_t('custom_frames')) ?>
            </a>

            <a href="<?= e(url('about.php')) ?>">
                <?= e(fz_t('about')) ?>
            </a>

            <a href="<?= e(url('contact.php')) ?>">
                <?= e(fz_t('contact')) ?>
            </a>

        </nav>

        <div class="header-actions">

            <form
                class="search-form"
                action="<?= e(url('search.php')) ?>"
                method="get"
            >
                <input
                    type="search"
                    name="q"
                    placeholder="<?= e(fz_t('search')) ?>"
                    aria-label="<?= e(fz_t('search')) ?>"
                    value="<?= e(
                        isset($_GET['q']) && is_string($_GET['q'])
                            ? $_GET['q']
                            : ''
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="lang"
                    value="<?= e($currentLanguage) ?>"
                >

                <button
                    type="submit"
                    aria-label="<?= e(fz_t('search')) ?>"
                >⌕</button>
            </form>

            <a class="cart-link" href="<?= e(url('cart.php')) ?>">
                <?= e(fz_t('cart')) ?>
                <span class="cart-badge">
                    <?= (int) $cartCount ?>
                </span>
            </a>

            <form
                class="fz-language"
                method="get"
                action="<?= e($currentPath) ?>"
            >

                <?php foreach ($currentQuery as $key => $value): ?>
                    <?php if (
                        is_string($key) &&
                        (is_string($value) || is_numeric($value))
                    ): ?>
                        <input
                            type="hidden"
                            name="<?= e($key) ?>"
                            value="<?= e((string) $value) ?>"
                        >
                    <?php endif; ?>
                <?php endforeach; ?>

                <label for="fz-language-select">🌐</label>

                <select
                    id="fz-language-select"
                    name="lang"
                    aria-label="Choose language"
                    onchange="this.form.submit()"
                >
                    <?php foreach ($languages as $code => $label): ?>
                        <option
                            value="<?= e($code) ?>"
                            <?= $currentLanguage === $code
                                ? 'selected'
                                : '' ?>
                        >
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            </form>

            <button
                class="menu-toggle"
                id="menuToggle"
                type="button"
                aria-label="Toggle menu"
                aria-controls="mainNav"
                aria-expanded="false"
            >☰</button>

        </div>
    </div>
</header>

<main>
```