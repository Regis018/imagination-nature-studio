<?php
/**
 * Imagination Nature Studio — Shop Homepage
 * Multilingual: English, Kinyarwanda, Kiswahili, French
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/whatsapp.php';

/*
 * Keep the existing product queries and database structure.
 */
$featured = fetch_products(['featured' => 1, 'limit' => 8]);
$epl = fetch_products(['league_id' => 1, 'limit' => 8]);
$laliga = fetch_products(['league_id' => 2, 'limit' => 4]);
$legends = fetch_products(['category_id' => 2, 'limit' => 8]);
$celebrities = fetch_products(['category_id' => 3, 'limit' => 8]);

$heroImage = setting(
    'hero_image',
    'assets/images/football-frames-hero.png'
) ?? 'assets/images/football-frames-hero.png';

$heroHeadline = setting(
    'hero_headline',
    'TURN YOUR PASSION INTO WALL ART'
) ?? '';

$heroSub = setting('hero_subtitle', '') ?? '';
$heroSupport = setting('hero_support', '') ?? '';

/*
 * Load the shared header before using fz_t().
 * The header should load includes/language.php.
 */
$pageTitle = (setting('store_name', 'Imagination Nature Studio')
    ?? 'Imagination Nature Studio')
    . ' — Premium Football & Celebrity Frames in Rwanda';

$metaDescription = 'Premium football club frames, football legends, celebrity portraits and personalized wall art in Rwanda. Order on WhatsApp.';

require __DIR__ . '/includes/header.php';

/* TEMPORARY TEST: confirms this exact index.php file is running. */
echo '<div style="background:#ffeb3b;color:#111;padding:14px;text-align:center;font-weight:bold;position:relative;z-index:99999;">TEST 123 — SHOP INDEX FILE IS ACTIVE</div>';

/*
 * WhatsApp messages follow the selected website language.
 */
$whatsappOrderMessage = 'Hello! I would like to order a frame.';
$whatsappFramesMessage = 'Hello! I would like to order frames.';

switch (fz_lang()) {
    case 'rw':
        $whatsappOrderMessage = 'Muraho! Ndifuza gutumiza frame.';
        $whatsappFramesMessage = 'Muraho! Ndifuza gutumiza ama-frame.';
        break;

    case 'sw':
        $whatsappOrderMessage = 'Habari! Ningependa kuagiza fremu.';
        $whatsappFramesMessage = 'Habari! Ningependa kuagiza fremu.';
        break;

    case 'fr':
        $whatsappOrderMessage = 'Bonjour ! Je souhaite commander un cadre.';
        $whatsappFramesMessage = 'Bonjour ! Je souhaite commander des cadres.';
        break;
}
?>

<section class="hero">
    <div
        class="hero-bg"
        style="background-image:url('<?= e(url($heroImage)) ?>')"
    ></div>

    <div class="hero-overlay"></div>

    <div class="container hero-content">
        <p class="eyebrow">
            <?= e(fz_t('eyebrow')) ?>
        </p>

        <h1><?= e($heroHeadline) ?></h1>

        <?php if ($heroSub !== ''): ?>
            <p class="hero-sub"><?= e($heroSub) ?></p>
        <?php endif; ?>

        <?php if ($heroSupport !== ''): ?>
            <p class="hero-support"><?= e($heroSupport) ?></p>
        <?php endif; ?>

        <div class="hero-buttons">
            <a
                class="btn btn-gold"
                href="<?= e(url('football-clubs.php')) ?>"
            >
                <?= e(fz_t('explore_football')) ?>
            </a>

            <a
                class="btn btn-outline"
                href="<?= e(url('celebrities.php')) ?>"
            >
                <?= e(fz_t('discover_celebrity')) ?>
            </a>

            <a
                class="btn btn-wa"
                href="<?= e(whatsapp_url($whatsappOrderMessage)) ?>"
                target="_blank"
                rel="noopener"
            >
                <?= e(fz_t('order_whatsapp')) ?>
            </a>
        </div>
    </div>
</section>

<!-- POPULAR CATEGORIES -->
<section class="section">
    <div class="container">
        <h2 class="section-title">
            <?= e(fz_t('popular_categories')) ?>
        </h2>

        <div class="cat-grid">
            <a
                class="cat-card"
                href="<?= e(url('football-clubs.php')) ?>"
            >
                <span><?= e(fz_t('football_clubs')) ?></span>
            </a>

            <a
                class="cat-card"
                href="<?= e(url('football-legends.php')) ?>"
            >
                <span><?= e(fz_t('legends')) ?></span>
            </a>

            <a
                class="cat-card"
                href="<?= e(url('celebrities.php')) ?>"
            >
                <span><?= e(fz_t('celebrities')) ?></span>
            </a>

            <a
                class="cat-card"
                href="<?= e(url('custom-frames.php')) ?>"
            >
                <span><?= e(fz_t('custom_frames')) ?></span>
            </a>
        </div>
    </div>
</section>

<!-- FEATURED FRAMES -->
<?php if (!empty($featured)): ?>
<section class="section section-dark">
    <div class="container">
        <div class="section-head">
            <h2 class="section-title">
                <?= e(fz_t('featured_frames')) ?>
            </h2>

            <a
                class="link-gold"
                href="<?= e(url('search.php?sort=newest')) ?>"
            >
                <?= e(fz_t('view_all')) ?> →
            </a>
        </div>

        <div class="product-grid">
            <?php foreach ($featured as $product): ?>
                <?php require __DIR__ . '/includes/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ENGLISH PREMIER LEAGUE -->
<?php if (!empty($epl)): ?>
<section class="section">
    <div class="container">
        <div class="section-head">
            <h2 class="section-title">
                <?= e(fz_t('english_premier_league')) ?>
            </h2>

            <a
                class="link-gold"
                href="<?= e(url('league.php?slug=english-premier-league')) ?>"
            >
                <?= e(fz_t('see_all')) ?> EPL →
            </a>
        </div>

        <div class="product-grid">
            <?php foreach ($epl as $product): ?>
                <?php require __DIR__ . '/includes/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- LA LIGA -->
<?php if (!empty($laliga)): ?>
<section class="section section-dark">
    <div class="container">
        <div class="section-head">
            <h2 class="section-title">
                <?= e(fz_t('la_liga')) ?>
            </h2>

            <a
                class="link-gold"
                href="<?= e(url('league.php?slug=spanish-la-liga')) ?>"
            >
                <?= e(fz_t('see_all')) ?> La Liga →
            </a>
        </div>

        <div class="product-grid">
            <?php foreach ($laliga as $product): ?>
                <?php require __DIR__ . '/includes/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- OTHER LEAGUES -->
<section class="section">
    <div class="container league-strip">
        <a
            class="league-chip"
            href="<?= e(url('league.php?slug=italian-serie-a')) ?>"
        >
            <?= e(fz_t('serie_a')) ?>
        </a>

        <a
            class="league-chip"
            href="<?= e(url('league.php?slug=german-bundesliga')) ?>"
        >
            <?= e(fz_t('bundesliga')) ?>
        </a>

        <a
            class="league-chip"
            href="<?= e(url('league.php?slug=french-ligue-1')) ?>"
        >
            <?= e(fz_t('ligue_1')) ?>
        </a>

        <a
            class="league-chip"
            href="<?= e(url('league.php?slug=african-international')) ?>"
        >
            <?= e(fz_t('african_international')) ?>
        </a>
    </div>
</section>

<!-- FOOTBALL LEGENDS -->
<?php if (!empty($legends)): ?>
<section class="section section-dark">
    <div class="container">
        <div class="section-head">
            <h2 class="section-title">
                <?= e(fz_t('legends')) ?>
            </h2>

            <a
                class="link-gold"
                href="<?= e(url('football-legends.php')) ?>"
            >
                <?= e(fz_t('see_all')) ?> →
            </a>
        </div>

        <div class="product-grid">
            <?php foreach ($legends as $product): ?>
                <?php require __DIR__ . '/includes/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- CELEBRITY FRAMES -->
<?php if (!empty($celebrities)): ?>
<section class="section">
    <div class="container">
        <div class="section-head">
            <h2 class="section-title">
                <?= e(fz_t('celebrities')) ?>
            </h2>

            <a
                class="link-gold"
                href="<?= e(url('celebrities.php')) ?>"
            >
                <?= e(fz_t('see_all')) ?> →
            </a>
        </div>

        <div class="product-grid">
            <?php foreach ($celebrities as $product): ?>
                <?php require __DIR__ . '/includes/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- CUSTOM FRAME CALL TO ACTION -->
<section class="section custom-cta">
    <div class="container custom-cta-inner">
        <div>
            <h2><?= e(fz_t('custom_cta_title')) ?></h2>

            <p><?= e(fz_t('custom_cta_text')) ?></p>
        </div>

        <a
            class="btn btn-gold"
            href="<?= e(url('custom-frames.php')) ?>"
        >
            <?= e(fz_t('request_custom_frame')) ?>
        </a>
    </div>
</section>

<!-- WHY SHOP WITH US -->
<section class="section section-dark">
    <div class="container">
        <h2 class="section-title">
            <?= e(fz_t('why_shop')) ?>
        </h2>

        <div class="why-grid">
            <div class="why-card">
                <h3><?= e(fz_t('premium_quality')) ?></h3>
                <p><?= e(fz_t('premium_quality_text')) ?></p>
            </div>

            <div class="why-card">
                <h3><?= e(fz_t('fast_delivery')) ?></h3>
                <p><?= e(fz_t('fast_delivery_text')) ?></p>
            </div>

            <div class="why-card">
                <h3><?= e(fz_t('easy_ordering')) ?></h3>
                <p><?= e(fz_t('easy_ordering_text')) ?></p>
            </div>

            <div class="why-card">
                <h3><?= e(fz_t('custom_design')) ?></h3>
                <p><?= e(fz_t('custom_design_text')) ?></p>
            </div>
        </div>
    </div>
</section>

<!-- HOW TO ORDER -->
<section class="section">
    <div class="container">
        <h2 class="section-title">
            <?= e(fz_t('how_to_order')) ?>
        </h2>

        <ol class="steps">
            <li>
                <strong><?= e(fz_t('football_clubs')) ?>:</strong>
                <?= e(fz_t('step_browse')) ?>
            </li>

            <li>
                <strong><?= e(fz_t('size')) ?>:</strong>
                <?= e(fz_t('step_select')) ?>
            </li>

            <li>
                <strong><?= e(fz_t('order_whatsapp')) ?>:</strong>
                <?= e(fz_t('step_send')) ?>
            </li>

            <li>
                <strong><?= e(fz_t('fast_delivery')) ?>:</strong>
                <?= e(fz_t('step_receive')) ?>
            </li>
        </ol>
    </div>
</section>

<!-- FREQUENTLY ASKED QUESTIONS -->
<section class="section section-dark">
    <div class="container">
        <h2 class="section-title"><?= e(fz_t('faq_title')) ?></h2>
        <div class="faq">
            <details><summary><?= e(fz_t('faq_delivery_question')) ?></summary><p><?= e(fz_t('faq_delivery_answer')) ?></p></details>
            <details><summary><?= e(fz_t('faq_custom_question')) ?></summary><p><?= e(fz_t('faq_custom_answer')) ?></p></details>
            <details><summary><?= e(fz_t('faq_sizes_question')) ?></summary><p><?= e(fz_t('faq_sizes_answer')) ?></p></details>
            <details><summary><?= e(fz_t('faq_payment_question')) ?></summary><p><?= e(fz_t('faq_payment_answer')) ?></p></details>
        </div>
    </div>
</section>

<section class="section cta-final">
    <div class="container cta-final-inner">
        <h2><?= e(fz_t('ready_to_order')) ?></h2>
        <p><?= e(fz_t('contact')) ?>: <?= e(setting('whatsapp_display', '+250 788 577 033') ?? '+250 788 577 033') ?></p>
        <a class="btn btn-wa" href="<?= e(whatsapp_url($whatsappFramesMessage)) ?>" target="_blank" rel="noopener"><?= e(fz_t('order_whatsapp')) ?></a>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
