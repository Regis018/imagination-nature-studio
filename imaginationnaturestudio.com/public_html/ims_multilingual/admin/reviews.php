<?php
require_once __DIR__ . '/../includes/functions.php';

require_login();

$pageTitle = 'Google Reviews';

require __DIR__ . '/includes/admin-header.php';

$apiConfigured = defined('GOOGLE_PLACES_API_KEY')
    && defined('GOOGLE_PLACE_ID')
    && trim((string)GOOGLE_PLACES_API_KEY) !== ''
    && trim((string)GOOGLE_PLACE_ID) !== '';

?>

<style>
.reviews-page {
    max-width: 1100px;
    margin: 0 auto;
}

.review-card {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 8px 30px rgba(0,0,0,.08);
}

.review-card h3 {
    margin: 0 0 8px;
}

.review-card p {
    line-height: 1.7;
}

.google-stars {
    color: #fbbc04;
    font-size: 20px;
    letter-spacing: 2px;
}

.notice {
    padding: 18px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.notice-warning {
    background: #fff7df;
    border: 1px solid #f0d36b;
}

.notice-success {
    background: #eaf8ee;
    border: 1px solid #8bd19b;
}

.btn-review {
    display: inline-block;
    padding: 11px 18px;
    border-radius: 9px;
    text-decoration: none;
    font-weight: 700;
    margin-right: 8px;
    margin-bottom: 8px;
}

.btn-google {
    background: #4285f4;
    color: #fff;
}

.btn-maps {
    background: #222;
    color: #fff;
}
</style>

<div class="reviews-page">

    <h1>Google Reviews</h1>

    <?php if (!$apiConfigured): ?>

        <div class="notice notice-warning">
            <h2>Google Reviews ntirashyirwaho neza</h2>

            <p>
                Google Places API Key na Google Place ID ntabwo birashyirwa muri
                <strong>config.php</strong>.
            </p>

            <p>
                Reviews ntizaboneka kugeza tubanje gushyiraho ibyo byombi.
            </p>
        </div>

        <div class="review-card">

            <h2>Step 1 — Google Places API</h2>

            <p>
                Tugomba kubanza gukora Google Places API Key.
            </p>

            <h2>Step 2 — Google Place ID</h2>

            <p>
                Tugomba kubona Place ID ya
                <strong>Imagination Nature Studio</strong>.
            </p>

            <h2>Step 3 — Shyira credentials muri config.php</h2>

            <p>
                Tuzashyiramo:
            </p>

<pre>define('GOOGLE_PLACES_API_KEY', 'YOUR_API_KEY');
define('GOOGLE_PLACE_ID', 'YOUR_PLACE_ID');</pre>

        </div>

    <?php else: ?>

        <div class="notice notice-success">
            <strong>Google Reviews API irashyizweho.</strong>
            <p>
                Reviews zizajya zizanwa live kuri Google.
            </p>
        </div>

        <div class="review-card">

            <h2>Reviews</h2>

            <p>
                Abakiriya bashobora gusiga review yabo kuri Google,
                hanyuma website ikayerekana.
            </p>

            <a
                href="<?= e(SITE_URL) ?>/#client-reviews"
                target="_blank"
                class="btn-review btn-google"
            >
                View Reviews on Website
            </a>

        </div>

    <?php endif; ?>

</div>

</main>