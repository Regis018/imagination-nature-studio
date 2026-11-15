<?php
/**
 * ============================================================
 * IMAGINATION NATURE STUDIO
 * BOOKING PAGE
 * ============================================================
 *
 * Normal services:
 *   - One booking date
 *   - One start/end time
 *
 * Wedding Photography:
 *   - Multiple wedding dates
 *   - Each date has event type + start/end time
 *
 * Compatible with:
 *   /booking/process.php
 *
 * ============================================================
 */

require_once __DIR__ . '/includes/functions.php';

/* ============================================================
   PAGE DATA
   ============================================================ */

$pageTitle = 'Book a Session · Imagination Nature Studio';

$pdo = db();

/* ============================================================
   LOAD SERVICES
   ============================================================ */

$services = [];

try {

    $stmt = $pdo->query(
        "SELECT id, name, price, description
         FROM services
         WHERE status = 'active'
           AND LOWER(TRIM(name)) IN ('studio photoshoot', 'wedding photography')
         ORDER BY name ASC"
    );

    $services = $stmt->fetchAll();

} catch (Throwable $e) {

    $services = [];
}

/* ============================================================
   OLD FORM DATA
   ============================================================ */

$old = $_SESSION['booking_old'] ?? [];

if (!is_array($old)) {
    $old = [];
}

$errors = $_SESSION['booking_errors'] ?? [];

if (!is_array($errors)) {
    $errors = [$errors];
}

unset($_SESSION['booking_old'], $_SESSION['booking_errors']);

/* ============================================================
   HELPERS
   ============================================================ */

function booking_old_value($key, $default = '')
{
    global $old;

    return isset($old[$key])
        ? (string)$old[$key]
        : $default;
}

function booking_rwf($amount)
{
    return 'RWF ' . number_format((float)$amount);
}

/* ============================================================
   HEADER
   ============================================================ */

require_once __DIR__ . '/includes/header.php';
?>

<style>

/* ============================================================
   BOOKING PAGE
   ============================================================ */

.booking-page {
    padding: 7rem 1rem 5rem;
    background: var(--bg, #0d0c0b);
    color: var(--cream, #f3ece3);
}

.booking-container {
    width: min(1100px, 100%);
    margin: 0 auto;
}

.booking-hero {
    text-align: center;
    margin-bottom: 3rem;
}

.booking-hero h1 {
    font-family: var(--font-serif, Georgia, serif);
    font-size: clamp(2rem, 5vw, 3.5rem);
    margin: 0 0 .8rem;
}

.booking-hero p {
    max-width: 700px;
    margin: 0 auto;
    color: #aaa19a;
    line-height: 1.7;
}

/* ============================================================
   ALERTS
   ============================================================ */

.booking-alert {
    padding: 1rem 1.2rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    line-height: 1.5;
}

.booking-alert.error {
    background: rgba(190,55,55,.10);
    border: 1px solid rgba(190,55,55,.35);
    color: #f1b5b5;
}

/* ============================================================
   FORM CARD
   ============================================================ */

.booking-card {
    background: rgba(255,255,255,.025);
    border: 1px solid rgba(255,255,255,.09);
    border-radius: 22px;
    padding: clamp(1.2rem, 4vw, 2rem);
}

/* ============================================================
   STEPS
   ============================================================ */

.booking-step {
    padding: 0 0 2.4rem;
    margin-bottom: 2.4rem;
    border-bottom: 1px solid rgba(255,255,255,.08);
}

.booking-step:last-of-type {
    border-bottom: 0;
    margin-bottom: 0;
}

.booking-step-title {
    display: flex;
    align-items: center;
    gap: .8rem;
    margin: 0 0 1.4rem;
    font-family: var(--font-serif, Georgia, serif);
    font-size: 1.35rem;
}

.booking-step-number {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.12);
    font-family: Arial, sans-serif;
    font-size: .85rem;
    flex: 0 0 auto;
}

/* ============================================================
   GRID
   ============================================================ */

.booking-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.1rem;
}

.booking-grid .full {
    grid-column: 1 / -1;
}

/* ============================================================
   FORM ELEMENTS
   ============================================================ */

.booking-field {
    min-width: 0;
}

.booking-field label {
    display: block;
    margin-bottom: .5rem;
    color: #d8d0c8;
    font-size: .88rem;
    font-weight: 600;
}

.booking-field input,
.booking-field select,
.booking-field textarea {
    display: block;
    width: 100%;
    min-height: 50px;
    box-sizing: border-box;
    border: 1px solid rgba(255,255,255,.14);
    border-radius: 10px;
    background: #171514;
    color: #f3ece3;
    padding: .8rem .9rem;
    font: inherit;
    outline: none;
}

.booking-field input:focus,
.booking-field select:focus,
.booking-field textarea:focus {
    border-color: rgba(255,255,255,.45);
}

.booking-field textarea {
    min-height: 120px;
    resize: vertical;
}

.booking-field select option {
    background: #171514;
    color: #fff;
}

/* Fix date/time appearance */

.booking-field input[type="date"],
.booking-field input[type="time"],
.booking-field input[type="number"] {
    appearance: auto;
    -webkit-appearance: auto;
}

.booking-field input[type="date"]::-webkit-calendar-picker-indicator,
.booking-field input[type="time"]::-webkit-calendar-picker-indicator {
    filter: invert(1);
    opacity: .8;
}

/* ============================================================
   SERVICE CARDS
   ============================================================ */

.service-note {
    margin-top: .55rem;
    color: #8e8780;
    font-size: .82rem;
    line-height: 1.5;
}


/* ============================================================
   PREMIUM SERVICE SELECTOR
   ============================================================ */

.choose-service-toggle {
    width:100%;
    min-height:56px;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:.55rem;
    border:1px solid rgba(212,175,55,.35);
    border-radius:13px;
    background:rgba(212,175,55,.06);
    color:#f3ece3;
    font:inherit;
    font-weight:700;
    cursor:pointer;
    transition:background .2s ease,border-color .2s ease;
}

.choose-service-toggle:hover {
    background:rgba(212,175,55,.11);
    border-color:rgba(212,175,55,.6);
}

.choose-service-toggle-icon {
    color:#d4af37;
    font-size:1.35rem;
    line-height:1;
}

.selected-service-preview {
    min-height:56px;
    margin-top:.75rem;
    padding:.75rem .9rem;
    display:flex;
    align-items:center;
    gap:.7rem;
    border:1px solid rgba(212,175,55,.45);
    border-radius:13px;
    background:rgba(212,175,55,.07);
    color:#d9d1ca;
}

.selected-service-preview[hidden] { display:none !important; }
.selected-service-check {
    width:27px;height:27px;flex:0 0 27px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    background:#d4af37;color:#111;font-weight:900;
}
.change-service-btn {
    margin-left:auto;
    border:0;
    background:transparent;
    color:#d4af37;
    font:inherit;
    font-size:.82rem;
    font-weight:700;
    cursor:pointer;
}

.service-picker {
    display:none;
    grid-template-columns:1fr;
    gap:.55rem;
    margin-top:.5rem;
}

.service-picker.is-open { display:grid; }

.service-picker,
.service-picker * {
    box-sizing:border-box;
}

.service-picker {
    position:relative;
    z-index:1;
    width:100%;
    max-width:100%;
    overflow:visible;
}

.service-card {
    position:relative;
    min-height:92px;
    overflow:hidden;
    border:1px solid rgba(255,255,255,.10);
    border-radius:10px;
    background:
        radial-gradient(circle at 80% 15%, rgba(212,175,55,.18), transparent 34%),
        linear-gradient(145deg,#1b1917,#0f0e0d);
    cursor:pointer;
    transition:border-color .25s ease,background .25s ease,box-shadow .25s ease;
    transform:none;
    box-sizing:border-box;
}

.service-card::before {
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(135deg,transparent 35%,rgba(212,175,55,.08));
    pointer-events:none;
}

.service-card:hover {
    transform:none;
    border-color:rgba(212,175,55,.48);
    box-shadow:0 3px 10px rgba(0,0,0,.12);
}

.service-card.is-selected {
    border-color:#d4af37;
    box-shadow:0 0 0 1px rgba(212,175,55,.35);
}

.service-card-content {
    position:relative;
    z-index:1;
    min-height:92px;
    padding:.75rem .8rem;
    display:flex;
    flex-direction:column;
    justify-content:flex-end;
}

.service-card-icon {
    position:absolute;
    top:.65rem;
    left:.7rem;
    width:28px;
    height:28px;
    display:flex;
    align-items:center;
    justify-content:center;
    border:1px solid rgba(212,175,55,.35);
    border-radius:50%;
    color:#d4af37;
    font-size:.78rem;
    background:rgba(0,0,0,.22);
}

.service-card-number {
    position:absolute;
    top:.65rem;
    right:.7rem;
    color:rgba(255,255,255,.28);
    font-size:.72rem;
    letter-spacing:.18em;
    font-weight:700;
}

.service-card h3 {
    margin:0 0 .2rem;
    color:#fff;
    font-family:var(--font-serif,Georgia,serif);
    font-size:.92rem;
    line-height:1.15;
}

.service-card p {
    margin:0;
    color:#a9a19a;
    font-size:.84rem;
    line-height:1.55;
    display:-webkit-box;
    -webkit-line-clamp:2;
    -webkit-box-orient:vertical;
    overflow:hidden;
}

.service-card-footer {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:1rem;
    margin-top:1rem;
}

.service-card-price {
    color:#d4af37;
    font-size:.84rem;
    font-weight:700;
}

.service-card-action {
    display:inline-flex;
    align-items:center;
    gap:.35rem;
    color:#fff;
    font-size:.78rem;
    font-weight:700;
    letter-spacing:.03em;
}

.service-card-action span {
    transition:transform .25s ease;
}

.service-card:hover .service-card-action span {
    transform:translateX(4px);
}

.service-card-check {
    position:absolute;
    right:1.15rem;
    top:3.9rem;
    width:26px;
    height:26px;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#d4af37;
    color:#111;
    font-size:.75rem;
    font-weight:900;
    opacity:0;
    transform:scale(.7);
    transition:opacity .2s ease,transform .2s ease;
}

.service-card.is-selected .service-card-check {
    opacity:1;
    transform:scale(1);
}

.service-select-native {
    position:absolute !important;
    width:1px !important;
    height:1px !important;
    padding:0 !important;
    margin:-1px !important;
    overflow:hidden !important;
    clip:rect(0,0,0,0) !important;
    white-space:nowrap !important;
    border:0 !important;
}

@media (max-width:700px) {
    .service-picker { grid-template-columns:1fr; }
    .service-card,.service-card-content { min-height:210px; }
}

/* ============================================================
   PACKAGE AREA
   ============================================================ */

.package-area {
    margin-top: 1rem;
}

/* ============================================================
   NORMAL DATE/TIME
   ============================================================ */

#normalDateTime {
    display: block;
}

#normalDateTime.is-hidden {
    display: none !important;
}

/* ============================================================
   WEDDING SECTION
   ============================================================ */

#weddingDatesSection {
    display: none;
}

#weddingDatesSection.is-visible {
    display: block;
}

.wedding-description {
    margin: 0 0 1rem;
    padding: 1rem 1.1rem;
    border-radius: 12px;
    background: rgba(255,255,255,.035);
    border: 1px solid rgba(255,255,255,.08);
    color: #aaa19a;
    line-height: 1.6;
    font-size: .9rem;
}

.wedding-date-row {
    padding: 1.2rem;
    margin-bottom: 1rem;
    border-radius: 15px;
    background: rgba(255,255,255,.025);
    border: 1px solid rgba(255,255,255,.09);
}

.wedding-day-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
}

.wedding-day-header strong {
    font-family: var(--font-serif, Georgia, serif);
    font-size: 1.05rem;
}

.remove-wedding-date {
    border: 1px solid rgba(220,80,80,.35);
    background: rgba(220,80,80,.08);
    color: #efb1b1;
    border-radius: 8px;
    padding: .45rem .75rem;
    cursor: pointer;
}

.remove-wedding-date:hover {
    background: rgba(220,80,80,.15);
}

.add-wedding-date {
    width: 100%;
    border: 1px dashed rgba(255,255,255,.22);
    background: rgba(255,255,255,.025);
    color: #eee;
    border-radius: 11px;
    padding: .85rem 1rem;
    cursor: pointer;
    font: inherit;
}

.add-wedding-date:hover {
    background: rgba(255,255,255,.06);
}

/* ============================================================
   SLOTS
   ============================================================ */

.slot-grid {
    display: flex;
    flex-wrap: wrap;
    gap: .55rem;
    min-height: 45px;
    align-items: center;
}

.slot {
    border: 1px solid rgba(255,255,255,.14);
    background: rgba(255,255,255,.035);
    color: #f3ece3;
    border-radius: 8px;
    padding: .65rem .8rem;
    cursor: pointer;
}

.slot:hover {
    background: rgba(255,255,255,.08);
}

.slot.selected {
    background: #f3ece3;
    color: #111;
}

.slot.disabled {
    opacity: .35;
    cursor: not-allowed;
}

/* ============================================================
   PAYMENT
   ============================================================ */

.payment-box {
    padding: 1.2rem;
    border-radius: 15px;
    background: rgba(255,255,255,.03);
    border: 1px solid rgba(255,255,255,.09);
}

.deposit-notice {
    margin-bottom: 1rem;
    padding: .9rem 1rem;
    border-radius: 10px;
    background: rgba(255,255,255,.04);
    color: #c9c1ba;
    font-size: .88rem;
    line-height: 1.6;
}

/* ============================================================
   SUMMARY
   ============================================================ */

.booking-summary {
    margin-top: 1.5rem;
    border-radius: 15px;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,.09);
}

.summary-row {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: .85rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,.06);
}

.summary-row:last-child {
    border-bottom: 0;
}

.summary-row span:first-child {
    color: #928a83;
}

.summary-row span:last-child,
.summary-row strong {
    text-align: right;
}

/* ============================================================
   SUBMIT
   ============================================================ */

.booking-submit-area {
    margin-top: 2rem;
}

.booking-submit {
    width: 100%;
    min-height: 55px;
    border: 0;
    border-radius: 12px;
    padding: .9rem 1.2rem;
    background: var(--accent, #e07a2f);
    color: #fff;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
}

.booking-submit:hover {
    opacity: .92;
}

.booking-submit:disabled {
    opacity: .55;
    cursor: wait;
}

.booking-small {
    margin-top: .8rem;
    text-align: center;
    color: #807972;
    font-size: .8rem;
    line-height: 1.5;
}

/* ============================================================
   RESPONSIVE
   ============================================================ */

@media (max-width: 700px) {

    .booking-page {
        padding-top: 5rem;
    }

    .booking-grid {
        grid-template-columns: 1fr;
    }

    .booking-grid .full {
        grid-column: auto;
    }

    .summary-row {
        align-items: flex-start;
        flex-direction: column;
        gap: .25rem;
    }

    .summary-row span:last-child,
    .summary-row strong {
        text-align: left;
    }

    .wedding-day-header {
        align-items: flex-start;
    }
}


/* ============================================================
   COMPACT SERVICE PICKER OVERRIDES
   ============================================================ */
.service-picker .service-card p {
    font-size:.72rem;
    line-height:1.3;
    display:-webkit-box;
    -webkit-line-clamp:1;
    -webkit-box-orient:vertical;
    overflow:hidden;
}

.service-picker .service-card .service-card-meta {
    margin-top:.3rem;
    font-size:.68rem;
}

.service-picker .service-card .service-card-content {
    justify-content:flex-end;
}

@media (max-width:700px) {
    .service-picker {
        grid-template-columns:1fr;
        gap:.45rem;
    }

    .service-card,
    .service-card-content {
        min-height:82px;
    }

    .service-card-content {
        padding:.6rem;
    }

    .service-card h3 {
        font-size:.82rem;
        padding-left:2.15rem;
    }

    .service-card p {
        display:none;
    }

    .service-card-icon {
        top:.55rem;
        left:.55rem;
        width:25px;
        height:25px;
        font-size:.7rem;
    }

    .service-card-number {
        top:.55rem;
        right:.55rem;
        font-size:.6rem;
    }
}


/* ============================================================
   IMAGINATION NATURE STUDIO — COLORFUL SERVICE CARDS
   Keeps the existing PHP/HTML/JS untouched.
   ============================================================ */

.service-picker .service-card {
    position: relative;
    isolation: isolate;
    border: 1px solid rgba(255,255,255,.12);
    background:
        linear-gradient(135deg, rgba(255,255,255,.045), rgba(0,0,0,.20)),
        #171514;
    transition:
        transform .22s ease,
        border-color .22s ease,
        box-shadow .22s ease,
        background .22s ease;
}

.service-picker .service-card::before {
    content: "";
    position: absolute;
    inset: 0;
    z-index: -1;
    background: linear-gradient(
        135deg,
        var(--service-color, #d4af37),
        transparent 42%
    );
    opacity: .13;
    pointer-events: none;
}

.service-picker .service-card:hover {
    transform: translateY(-2px);
    border-color: color-mix(in srgb, var(--service-color, #d4af37) 70%, white 10%);
    box-shadow:
        0 8px 24px rgba(0,0,0,.28),
        0 0 0 1px color-mix(in srgb, var(--service-color, #d4af37) 18%, transparent);
}

.service-picker .service-card.is-selected {
    border-color: var(--service-color, #d4af37);
    box-shadow:
        0 8px 24px rgba(0,0,0,.30),
        0 0 0 1px color-mix(in srgb, var(--service-color, #d4af37) 35%, transparent);
}

.service-picker .service-card-icon {
    color: var(--service-color, #d4af37);
    border-color: color-mix(in srgb, var(--service-color, #d4af37) 42%, rgba(255,255,255,.10));
    background: color-mix(in srgb, var(--service-color, #d4af37) 12%, #151311);
    box-shadow: 0 0 18px color-mix(in srgb, var(--service-color, #d4af37) 14%, transparent);
}

.service-picker .service-card-number {
    color: color-mix(in srgb, var(--service-color, #d4af37) 78%, white 8%);
}

.service-picker .service-card-title {
    color: #fff;
}

.service-picker .service-card-price {
    color: var(--service-color, #d4af37);
    font-weight: 700;
}

.service-picker .service-card-action {
    color: var(--service-color, #d4af37);
}

.service-picker .service-card-check {
    background: var(--service-color, #d4af37);
    color: #111;
}

/* Service colors are assigned automatically from card position.
   This does not depend on service names and therefore works with DB data. */

.service-picker .service-card:nth-child(6n + 1) {
    --service-color: #d4af37; /* Luxury Gold */
}

.service-picker .service-card:nth-child(6n + 2) {
    --service-color: #e58b9a; /* Rose */
}

.service-picker .service-card:nth-child(6n + 3) {
    --service-color: #6fa8dc; /* Blue */
}

.service-picker .service-card:nth-child(6n + 4) {
    --service-color: #a985d6; /* Purple */
}

.service-picker .service-card:nth-child(6n + 5) {
    --service-color: #68b88b; /* Green */
}

.service-picker .service-card:nth-child(6n + 6) {
    --service-color: #e69b55; /* Orange */
}

/* Make the color accents subtle on small screens too. */
@media (max-width:700px) {
    .service-picker .service-card:hover {
        transform: none;
    }

    .service-picker .service-card-content {
        padding-top: .9rem;
        padding-bottom: .9rem;
    }
}

</style>


<div class="booking-page">

    <div class="booking-container">

        <!-- ====================================================
             HERO
             ==================================================== -->

        <div class="booking-hero">

            <h1>Book Your Session</h1>

            <p>
                Tell us about your photography session or event.
                Our team will review your booking and contact you
                to confirm the appointment.
            </p>

        </div>


        <!-- ====================================================
             ERRORS
             ==================================================== -->

        <?php if (!empty($errors)): ?>

            <div class="booking-alert error">

                <?php foreach ($errors as $error): ?>

                    <div>
                        <?= e($error) ?>
                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>


        <!-- ====================================================
             FORM
             ==================================================== -->

        <form
            method="post"
            action="<?= e(SITE_URL) ?>/booking/process.php"
            id="bookingForm"
            class="booking-card"
            novalidate
        >

            <?= csrf_field() ?>


            <!-- ==================================================
                 STEP 1 — SERVICE
                 ================================================== -->

            <section class="booking-step">

                <h2 class="booking-step-title">

                    <span class="booking-step-number">
                        1
                    </span>

                    Choose your service

                </h2>


                <div class="booking-grid">

                    <!-- SERVICE -->

                    <div class="booking-field full">

                        <label>
                            Choose your service *
                        </label>

                        <button type="button" class="choose-service-toggle" id="chooseServiceToggle" aria-expanded="false">
                            <span class="choose-service-toggle-icon">＋</span>
                            <span>Choose a service</span>
                        </button>

                        <div class="selected-service-preview" id="selectedServicePreview" hidden>
                            <span class="selected-service-check">✓</span>
                            <span>Selected: <strong id="selectedServiceName">—</strong></span>
                            <button type="button" class="change-service-btn" id="changeServiceBtn">Change</button>
                        </div>

                        <select
                            name="service_id"
                            id="service_id"
                            class="service-select-native"
                            required
                        >
                            <option value="">— Select a service —</option>
                            <?php foreach ($services as $service): ?>
                                <option
                                    value="<?= (int)$service['id'] ?>"
                                    data-service-name="<?= e($service['name']) ?>"
                                    data-price="<?= (float)$service['price'] ?>"
                                    <?= (string)booking_old_value('service_id') === (string)$service['id'] ? 'selected' : '' ?>
                                ><?= e($service['name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <div class="service-picker" id="servicePicker">
                            <?php foreach ($services as $index => $service): ?>
                                <?php
                                    $sid = (int)$service['id'];
                                    $selected = (string)booking_old_value('service_id') === (string)$sid;
                                    $serviceName = (string)($service['name'] ?? 'Service');
                                    $description = trim((string)($service['description'] ?? ''));
                                    $price = (float)($service['price'] ?? 0);
                                    $isWedding = stripos($serviceName, 'wedding') !== false;
                                    $isStudio = ((int)$sid === 4) || (stripos($serviceName, 'studio photoshoot') !== false);
                                    $icon = $isWedding ? '♡' : ($isStudio ? '▣' : '✦');
                                ?>
                                <button
                                    type="button"
                                    class="service-card<?= $selected ? ' is-selected' : '' ?>"
                                    data-service-card="<?= $sid ?>"
                                    data-studio-link="<?= $isStudio ? '1' : '0' ?>"
                                    aria-pressed="<?= $selected ? 'true' : 'false' ?>"
                                >
                                    <span class="service-card-icon"><?= $icon ?></span>
                                    <span class="service-card-number"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                                    <span class="service-card-check">✓</span>
                                    <span class="service-card-content">
                                        <strong class="service-card-title"><?= e($serviceName) ?></strong>
                                        <span class="service-card-description"><?= e($description ?: 'Professional photography tailored to your special moment.') ?></span>
                                        <span class="service-card-footer">
                                            <span class="service-card-price"><?= $price > 0 ? e(booking_rwf($price)) : 'Custom pricing' ?></span>
                                            <span class="service-card-action">Select <span>→</span></span>
                                        </span>
                                    </span>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <div id="serviceDescription" class="service-note"></div>

                    </div>


                    <!-- PACKAGE -->

                    <div class="booking-field">

                        <label for="package_id">
                            Package
                        </label>

                        <select
                            name="package_id"
                            id="package_id"
                        >

                            <option value="">
                                — No package / custom —
                            </option>

                        </select>

                        <div class="service-note">
                            Packages are loaded according to
                            the selected service.
                        </div>

                    </div>

                </div>

            </section>


            <!-- ==================================================
                 STEP 2 — DATE & TIME
                 ================================================== -->

            <section class="booking-step">

                <h2 class="booking-step-title">

                    <span class="booking-step-number">
                        2
                    </span>

                    Date &amp; time

                </h2>


                <!-- ==============================================
                     NORMAL SERVICES
                     ============================================== -->

                <div id="normalDateTime">

                    <div class="booking-grid">


                        <!-- DATE -->

                        <div class="booking-field">

                            <label for="booking_date">
                                Preferred date *
                            </label>

                            <input
                                type="date"
                                name="booking_date"
                                id="booking_date"
                                min="<?= date('Y-m-d') ?>"
                                value="<?= e(booking_old_value('booking_date')) ?>"
                                required
                            >

                        </div>


                        <!-- PEOPLE -->

                        <div class="booking-field">

                            <label for="number_of_people_normal">
                                Number of people *
                            </label>

                            <input
                                type="number"
                                name="number_of_people"
                                id="number_of_people_normal"
                                min="1"
                                max="500"
                                value="<?= e(booking_old_value('number_of_people', '1')) ?>"
                                required
                            >

                        </div>


                        <!-- AVAILABLE TIMES -->

                        <div class="booking-field full">

                            <label>
                                Available start times *
                            </label>

                            <div
                                class="slot-grid"
                                id="slots"
                            >
                                <em style="color:#817a73">
                                    Select a date to see available times.
                                </em>
                            </div>

                            <input
                                type="hidden"
                                name="start_time"
                                id="start_time"
                                value="<?= e(booking_old_value('start_time')) ?>"
                            >

                        </div>


                        <!-- END TIME -->

                        <div class="booking-field">

                            <label for="end_time">
                                End time *
                            </label>

                            <input
                                type="time"
                                name="end_time"
                                id="end_time"
                                value="<?= e(booking_old_value('end_time')) ?>"
                                required
                            >

                        </div>


                        <!-- EVENT TYPE -->

                        <div class="booking-field">

                            <label for="normal_event_type">
                                Event type
                            </label>

                            <input
                                type="text"
                                name="event_type"
                                id="normal_event_type"
                                value="<?= e(booking_old_value('event_type')) ?>"
                                placeholder="e.g. Birthday, Portrait, Event"
                            >

                        </div>

                    </div>

                </div>


                <!-- ==============================================
                     WEDDING PHOTOGRAPHY
                     ============================================== -->

                <div id="weddingDatesSection">

                    <div class="wedding-description">

                        <strong>Wedding Photography</strong><br>

                        Wedding bookings can contain more than one
                        event day. Add every date included in the
                        wedding package below.

                    </div>


                    <div id="weddingDatesList">


                        <?php

                        $weddingDates =
                            $old['wedding_dates'] ?? [];

                        if (
                            !is_array($weddingDates) ||
                            empty($weddingDates)
                        ) {

                            $weddingDates = [
                                [
                                    'date'       => '',
                                    'event_type' => '',
                                    'start_time' => '',
                                    'end_time'   => ''
                                ]
                            ];

                        }

                        ?>


                        <?php foreach ($weddingDates as $i => $wd): ?>

                            <?php

                            $wd = is_array($wd)
                                ? $wd
                                : [];

                            ?>

                            <div
                                class="wedding-date-row"
                                data-index="<?= (int)$i ?>"
                            >

                                <div class="wedding-day-header">

                                    <strong>
                                        Wedding Day
                                        <?= (int)$i + 1 ?>
                                    </strong>


                                    <?php if ((int)$i > 0): ?>

                                        <button
                                            type="button"
                                            class="remove-wedding-date"
                                        >
                                            Remove
                                        </button>

                                    <?php endif; ?>

                                </div>


                                <div class="booking-grid">


                                    <!-- WEDDING DATE -->

                                    <div class="booking-field">

                                        <label>
                                            Date *
                                        </label>

                                        <input
                                            type="date"
                                            class="wedding-date"
                                            name="wedding_dates[<?= (int)$i ?>][date]"
                                            min="<?= date('Y-m-d') ?>"
                                            value="<?= e($wd['date'] ?? '') ?>"
                                            required
                                        >

                                    </div>


                                    <!-- EVENT TYPE -->

                                    <div class="booking-field">

                                        <label>
                                            Event / Day Type *
                                        </label>

                                        <input
                                            type="text"
                                            class="wedding-event-type"
                                            name="wedding_dates[<?= (int)$i ?>][event_type]"
                                            value="<?= e($wd['event_type'] ?? '') ?>"
                                            placeholder="e.g. Civil, Traditional, Reception"
                                            required
                                        >

                                    </div>


                                    <!-- START -->

                                    <div class="booking-field">

                                        <label>
                                            Start time *
                                        </label>

                                        <input
                                            type="time"
                                            class="wedding-start-time"
                                            name="wedding_dates[<?= (int)$i ?>][start_time]"
                                            value="<?= e($wd['start_time'] ?? '') ?>"
                                            required
                                        >

                                    </div>


                                    <!-- END -->

                                    <div class="booking-field">

                                        <label>
                                            End time *
                                        </label>

                                        <input
                                            type="time"
                                            class="wedding-end-time"
                                            name="wedding_dates[<?= (int)$i ?>][end_time]"
                                            value="<?= e($wd['end_time'] ?? '') ?>"
                                            required
                                        >

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>


                    <!-- ADD DATE -->

                    <button
                        type="button"
                        id="addWeddingDate"
                        class="add-wedding-date"
                    >
                        + Add another wedding date
                    </button>


                    <!-- ==========================================
                         COMPATIBILITY FIELDS
                         ==========================================

                         process.php currently requires:
                         booking_date
                         start_time
                         end_time

                         We automatically copy Wedding Day 1
                         into these hidden fields.
                    =========================================== -->

                    <input
                        type="hidden"
                        name="booking_date"
                        id="wedding_booking_date"
                        value=""
                    >

                    <input
                        type="hidden"
                        name="start_time"
                        id="wedding_start_time"
                        value=""
                    >

                    <input
                        type="hidden"
                        name="end_time"
                        id="wedding_end_time"
                        value=""
                    >


                    <!-- WEDDING PEOPLE -->

                    <div
                        class="booking-field"
                        style="margin-top:1rem"
                    >

                        <label for="number_of_people_wedding">
                            Number of people *
                        </label>

                        <input
                            type="number"
                            name="number_of_people"
                            id="number_of_people_wedding"
                            min="1"
                            max="500"
                            value="<?= e(booking_old_value('number_of_people', '1')) ?>"
                            required
                        >

                    </div>

                </div>

            </section>


            <!-- ==================================================
                 STEP 3 — CLIENT DETAILS
                 ================================================== -->

            <section class="booking-step">

                <h2 class="booking-step-title">

                    <span class="booking-step-number">
                        3
                    </span>

                    Your details

                </h2>


                <div class="booking-grid">


                    <!-- FULL NAME -->

                    <div class="booking-field">

                        <label for="full_name">
                            Full name *
                        </label>

                        <input
                            type="text"
                            name="full_name"
                            id="full_name"
                            value="<?= e(booking_old_value('full_name')) ?>"
                            autocomplete="name"
                            required
                        >

                    </div>


                    <!-- PHONE -->

                    <div class="booking-field">

                        <label for="phone">
                            Phone number *
                        </label>

                        <input
                            type="tel"
                            name="phone"
                            id="phone"
                            value="<?= e(booking_old_value('phone')) ?>"
                            autocomplete="tel"
                            required
                        >

                    </div>


                    <!-- WHATSAPP -->

                    <div class="booking-field">

                        <label for="whatsapp">
                            WhatsApp
                        </label>

                        <input
                            type="tel"
                            name="whatsapp"
                            id="whatsapp"
                            value="<?= e(booking_old_value('whatsapp')) ?>"
                            placeholder="Optional"
                        >

                    </div>


                    <!-- EMAIL -->

                    <div class="booking-field">

                        <label for="email">
                            Email *
                        </label>

                        <input
                            type="email"
                            name="email"
                            id="email"
                            value="<?= e(booking_old_value('email')) ?>"
                            autocomplete="email"
                            required
                        >

                    </div>


                    <!-- LOCATION -->

                    <div class="booking-field">

                        <label for="location">
                            Your location *
                        </label>

                        <input
                            type="text"
                            name="location"
                            id="location"
                            value="<?= e(booking_old_value('location')) ?>"
                            placeholder="Kigali / District / Area"
                            required
                        >

                    </div>


                    <!-- COMPANY -->

                    <div class="booking-field">

                        <label for="company">
                            Company
                        </label>

                        <input
                            type="text"
                            name="company"
                            id="company"
                            value="<?= e(booking_old_value('company')) ?>"
                            placeholder="Optional"
                        >

                    </div>


                    <!-- EVENT LOCATION -->

                    <div class="booking-field">

                        <label for="event_location">
                            Event location
                        </label>

                        <input
                            type="text"
                            name="event_location"
                            id="event_location"
                            value="<?= e(booking_old_value('event_location')) ?>"
                            placeholder="Where the photography will take place"
                        >

                    </div>


                    <!-- NOTES -->

                    <div class="booking-field full">

                        <label for="customer_notes">
                            Additional notes
                        </label>

                        <textarea
                            name="customer_notes"
                            id="customer_notes"
                            placeholder="Tell us anything important about your booking..."
                        ><?= e(booking_old_value('customer_notes')) ?></textarea>

                    </div>

                </div>

            </section>


            <!-- ==================================================
                 STEP 4 — PAYMENT
                 ================================================== -->

            <section class="booking-step">

                <h2 class="booking-step-title">

                    <span class="booking-step-number">
                        4
                    </span>

                    Payment

                </h2>


                <div class="payment-box">


                    <div class="deposit-notice">

                        <strong>30% deposit required.</strong>

                        A minimum deposit of 30% of the booking
                        total is required when submitting your
                        booking request.

                    </div>


                    <div class="booking-grid">


                        <!-- PAYMENT METHOD -->

                        <div class="booking-field">

                            <label for="payment_method">
                                Payment method *
                            </label>

                            <select
                                name="payment_method"
                                id="payment_method"
                                required
                            >

                                <option value="">
                                    — Select payment method —
                                </option>

                                <?php
                                $oldPayment =
                                    booking_old_value('payment_method');
                                ?>

                                <option
                                    value="cash"
                                    <?= $oldPayment === 'cash' ? 'selected' : '' ?>
                                >
                                    Cash
                                </option>

                                <option
                                    value="mobile_money"
                                    <?= $oldPayment === 'mobile_money' ? 'selected' : '' ?>
                                >
                                    Mobile Money
                                </option>

                                <option
                                    value="bank_transfer"
                                    <?= $oldPayment === 'bank_transfer' ? 'selected' : '' ?>
                                >
                                    Bank Transfer
                                </option>

                                <option
                                    value="other"
                                    <?= $oldPayment === 'other' ? 'selected' : '' ?>
                                >
                                    Other
                                </option>

                            </select>

                        </div>


                        <!-- AMOUNT PAID -->

                        <div class="booking-field">

                            <label for="amount_paid">
                                Amount paid (RWF) *
                            </label>

                            <input
                                type="number"
                                name="amount_paid"
                                id="amount_paid"
                                min="0"
                                step="1"
                                inputmode="numeric"
                                placeholder="Enter amount manually"
                                value="<?= e(booking_old_value('amount_paid', '')) ?>"
                                required
                            >

                            <div
                                id="depositHelp"
                                class="service-note"
                            >
                                Select a service to calculate
                                the required 30% deposit.
                            </div>

                        </div>

                    </div>


                    <!-- ==================================================
                         SUMMARY
                         ================================================== -->

                    <div
                        class="booking-summary"
                        id="bookingSummary"
                    >

                        <div class="summary-row">

                            <span>
                                Service
                            </span>

                            <span id="sumService">
                                —
                            </span>

                        </div>


                        <div class="summary-row">

                            <span>
                                Package
                            </span>

                            <span id="sumPackage">
                                —
                            </span>

                        </div>


                        <div class="summary-row">

                            <span>
                                Total
                            </span>

                            <strong id="sumTotal">
                                RWF 0
                            </strong>

                        </div>


                        <div class="summary-row">

                            <span>
                                Required deposit (30%)
                            </span>

                            <strong id="sumDeposit">
                                RWF 0
                            </strong>

                        </div>


                        <div class="summary-row">

                            <span>
                                Amount paid
                            </span>

                            <strong id="sumPaid">
                                RWF 0
                            </strong>

                        </div>


                        <div class="summary-row">

                            <span>
                                Remaining balance
                            </span>

                            <strong id="sumBalance">
                                RWF 0
                            </strong>

                        </div>

                    </div>

                </div>

            </section>


            <!-- ==================================================
                 SUBMIT
                 ================================================== -->

            <div class="booking-submit-area">

                <button
                    type="submit"
                    class="booking-submit"
                    id="submitBooking"
                >
                    Submit Booking Request
                </button>

                <div class="booking-small">

                    Your booking will first be submitted as
                    <strong>Pending</strong>.

                    Our team will review it and confirm it.
                    Once confirmed, your invoice will become
                    available.

                </div>

            </div>

        </form>

    </div>

</div>


<script>

/* ============================================================
   IMAGINATION NATURE STUDIO
   BOOKING JAVASCRIPT
   ============================================================ */

(function () {

    'use strict';


    /* ========================================================
       CONFIG
       ======================================================== */

    var SITE_URL =
        <?= json_encode(rtrim(SITE_URL, '/')) ?>;


    var services =
        <?= json_encode($services, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;


    var oldPackage =
        <?= json_encode(booking_old_value('package_id')) ?>;


    /* ========================================================
       ELEMENTS
       ======================================================== */

    var form =
        document.getElementById('bookingForm');

    var serviceSelect =
        document.getElementById('service_id');

    var packageSelect =
        document.getElementById('package_id');

    var normalSection =
        document.getElementById('normalDateTime');

    var weddingSection =
        document.getElementById('weddingDatesSection');

    var weddingList =
        document.getElementById('weddingDatesList');

    var addWeddingButton =
        document.getElementById('addWeddingDate');

    var normalDate =
        document.getElementById('booking_date');

    var normalStart =
        document.getElementById('start_time');

    var normalEnd =
        document.getElementById('end_time');

    var weddingBookingDate =
        document.getElementById('wedding_booking_date');

    var weddingStart =
        document.getElementById('wedding_start_time');

    var weddingEnd =
        document.getElementById('wedding_end_time');

    var normalPeople =
        document.getElementById('number_of_people_normal');

    var weddingPeople =
        document.getElementById('number_of_people_wedding');

    var slotsBox =
        document.getElementById('slots');

    var amountPaid =
        document.getElementById('amount_paid');

    var sumService =
        document.getElementById('sumService');

    var sumPackage =
        document.getElementById('sumPackage');

    var sumTotal =
        document.getElementById('sumTotal');

    var sumDeposit =
        document.getElementById('sumDeposit');

    var sumPaid =
        document.getElementById('sumPaid');

    var sumBalance =
        document.getElementById('sumBalance');

    var depositHelp =
        document.getElementById('depositHelp');

    var serviceDescription =
        document.getElementById('serviceDescription');

    var submitButton =
        document.getElementById('submitBooking');


    /* ========================================================
       SAFETY
       ======================================================== */

    if (!form || !serviceSelect || !packageSelect) {
        return;
    }


    /* ========================================================
       HELPERS
       ======================================================== */

    function rwf(value) {

        var number =
            Number(value || 0);

        return 'RWF ' +
            number.toLocaleString('en-US');

    }


    function getSelectedService() {

        var id =
            parseInt(
                serviceSelect.value,
                10
            );

        if (!id) {
            return null;
        }

        for (
            var i = 0;
            i < services.length;
            i++
        ) {

            if (
                parseInt(services[i].id, 10) === id
            ) {

                return services[i];

            }

        }

        return null;

    }


    function getSelectedPackage() {

        if (!packageSelect.value) {
            return null;
        }

        var option =
            packageSelect.options[
                packageSelect.selectedIndex
            ];

        if (!option) {
            return null;
        }

        return {

            id:
                option.value,

            name:
                option.getAttribute('data-name') ||
                option.textContent,

            price:
                parseFloat(
                    option.getAttribute('data-price') || 0
                )

        };

    }


    function isWeddingService() {

        var service =
            getSelectedService();

        if (!service) {
            return false;
        }

        var name =
            String(
                service.name || ''
            ).toLowerCase();

        return (
            name.indexOf('wedding photography') !== -1
            ||
            (
                name.indexOf('wedding') !== -1
                &&
                name.indexOf('photograph') !== -1
            )
        );

    }


    function getPricing() {

        var service =
            getSelectedService();

        var pkg =
            getSelectedPackage();

        var total =
            parseFloat(
                (
                    pkg &&
                    pkg.price
                )
                ||
                (
                    service &&
                    service.price
                )
                ||
                0
            );

        var deposit =
            Math.round(
                total * 0.30
            );

        var paid =
            parseFloat(
                amountPaid.value || 0
            );

        var balance =
            Math.max(
                0,
                total - paid
            );

        return {

            total: total,

            deposit: deposit,

            paid: paid,

            balance: balance

        };

    }


    /* ========================================================
       UPDATE SERVICE DESCRIPTION
       ======================================================== */

    function updateServiceDescription() {

        var service =
            getSelectedService();

        if (!serviceDescription) {
            return;
        }

        serviceDescription.textContent =
            service && service.description
                ? service.description
                : '';

    }


    /* ========================================================
       LOAD PACKAGES
       ======================================================== */

    function loadPackages(serviceId) {

        packageSelect.innerHTML =
            '<option value="">Loading…</option>';

        packageSelect.disabled = true;

        if (!serviceId) {

            packageSelect.innerHTML =
                '<option value="">— No package / custom —</option>';

            packageSelect.disabled = false;

            recalculate();

            return;

        }


        fetch(
            SITE_URL +
            '/booking/get-packages.php?service_id=' +
            encodeURIComponent(serviceId)
        )

        .then(function (response) {

            if (!response.ok) {
                throw new Error('Package request failed');
            }

            return response.json();

        })

        .then(function (data) {

            packageSelect.innerHTML =
                '<option value="">— No package / custom —</option>';

            var packages =
                data.packages || [];


            packages.forEach(function (pkg) {

                var option =
                    document.createElement('option');

                option.value =
                    pkg.id;

                option.textContent =
                    pkg.name +
                    ' · ' +
                    rwf(pkg.price);

                option.setAttribute(
                    'data-price',
                    pkg.price
                );

                option.setAttribute(
                    'data-name',
                    pkg.name
                );

                packageSelect.appendChild(
                    option
                );

            });


            packageSelect.disabled = false;


            if (oldPackage) {

                packageSelect.value =
                    String(oldPackage);

            }


            recalculate();

        })

        .catch(function () {

            packageSelect.innerHTML =
                '<option value="">— No package / custom —</option>';

            packageSelect.disabled = false;

            recalculate();

        });

    }


    /* ========================================================
       TOGGLE NORMAL / WEDDING
       ======================================================== */

    function toggleBookingMode() {

        var wedding =
            isWeddingService();


        if (wedding) {

            normalSection.classList.add(
                'is-hidden'
            );

            weddingSection.classList.add(
                'is-visible'
            );

            /* ----------------------------------------------
               Disable normal fields
               ---------------------------------------------- */

            if (normalDate) {
                normalDate.disabled = true;
            }

            if (normalStart) {
                normalStart.disabled = true;
            }

            if (normalEnd) {
                normalEnd.disabled = true;
            }

            if (normalPeople) {
                normalPeople.disabled = true;
            }

            /* ----------------------------------------------
               Enable wedding fields
               ---------------------------------------------- */

            weddingSection
                .querySelectorAll('input')
                .forEach(function (input) {

                    input.disabled = false;

                });

            syncWeddingCompatibility();

        } else {

            normalSection.classList.remove(
                'is-hidden'
            );

            weddingSection.classList.remove(
                'is-visible'
            );

            /* ----------------------------------------------
               Enable normal fields
               ---------------------------------------------- */

            if (normalDate) {
                normalDate.disabled = false;
            }

            if (normalStart) {
                normalStart.disabled = false;
            }

            if (normalEnd) {
                normalEnd.disabled = false;
            }

            if (normalPeople) {
                normalPeople.disabled = false;
            }

            /* ----------------------------------------------
               Disable wedding fields
               ---------------------------------------------- */

            weddingSection
                .querySelectorAll('input')
                .forEach(function (input) {

                    input.disabled = true;

                });

        }


        updateServiceDescription();

        recalculate();

    }


    /* ========================================================
       SYNC FIRST WEDDING DATE
       ======================================================== */

    function syncWeddingCompatibility() {

        if (!isWeddingService()) {
            return;
        }


        var firstRow =
            weddingList.querySelector(
                '.wedding-date-row'
            );

        if (!firstRow) {
            return;
        }


        var dateInput =
            firstRow.querySelector(
                '.wedding-date'
            );

        var startInput =
            firstRow.querySelector(
                '.wedding-start-time'
            );

        var endInput =
            firstRow.querySelector(
                '.wedding-end-time'
            );


        if (weddingBookingDate) {

            weddingBookingDate.value =
                dateInput
                    ? dateInput.value
                    : '';

        }

        if (weddingStart) {

            weddingStart.value =
                startInput
                    ? startInput.value
                    : '';

        }

        if (weddingEnd) {

            weddingEnd.value =
                endInput
                    ? endInput.value
                    : '';

        }

    }


    /* ========================================================
       RENUMBER WEDDING DAYS
       ======================================================== */

    function renumberWeddingDays() {

        var rows =
            weddingList.querySelectorAll(
                '.wedding-date-row'
            );


        rows.forEach(function (row, index) {

            row.setAttribute(
                'data-index',
                index
            );


            var title =
                row.querySelector(
                    '.wedding-day-header strong'
                );

            if (title) {

                title.textContent =
                    'Wedding Day ' +
                    (index + 1);

            }


            var inputs =
                row.querySelectorAll(
                    'input'
                );


            inputs.forEach(function (input) {

                var name =
                    input.getAttribute('name');

                if (!name) {
                    return;
                }


                name =
                    name.replace(
                        /wedding_dates\[\d+\]/,
                        'wedding_dates[' + index + ']'
                    );


                input.setAttribute(
                    'name',
                    name
                );

            });


            var removeButton =
                row.querySelector(
                    '.remove-wedding-date'
                );


            if (index === 0) {

                if (removeButton) {
                    removeButton.remove();
                }

            } else {

                if (!removeButton) {

                    var button =
                        document.createElement(
                            'button'
                        );

                    button.type =
                        'button';

                    button.className =
                        'remove-wedding-date';

                    button.textContent =
                        'Remove';

                    row
                        .querySelector(
                            '.wedding-day-header'
                        )
                        .appendChild(button);

                }

            }

        });


        syncWeddingCompatibility();

    }


    /* ========================================================
       ADD WEDDING DATE
       ======================================================== */

    function addWeddingDate() {

        var rows =
            weddingList.querySelectorAll(
                '.wedding-date-row'
            );

        var index =
            rows.length;


        var row =
            document.createElement('div');

        row.className =
            'wedding-date-row';

        row.setAttribute(
            'data-index',
            index
        );


        row.innerHTML = `

            <div class="wedding-day-header">

                <strong>
                    Wedding Day ${index + 1}
                </strong>

                <button
                    type="button"
                    class="remove-wedding-date"
                >
                    Remove
                </button>

            </div>

            <div class="booking-grid">

                <div class="booking-field">

                    <label>
                        Date *
                    </label>

                    <input
                        type="date"
                        class="wedding-date"
                        name="wedding_dates[${index}][date]"
                        min="<?= date('Y-m-d') ?>"
                        required
                    >

                </div>

                <div class="booking-field">

                    <label>
                        Event / Day Type *
                    </label>

                    <input
                        type="text"
                        class="wedding-event-type"
                        name="wedding_dates[${index}][event_type]"
                        placeholder="e.g. Civil, Traditional, Reception"
                        required
                    >

                </div>

                <div class="booking-field">

                    <label>
                        Start time *
                    </label>

                    <input
                        type="time"
                        class="wedding-start-time"
                        name="wedding_dates[${index}][start_time]"
                        required
                    >

                </div>

                <div class="booking-field">

                    <label>
                        End time *
                    </label>

                    <input
                        type="time"
                        class="wedding-end-time"
                        name="wedding_dates[${index}][end_time]"
                        required
                    >

                </div>

            </div>

        `;


        weddingList.appendChild(
            row
        );


        renumberWeddingDays();


        if (!isWeddingService()) {

            row
                .querySelectorAll('input')
                .forEach(function (input) {

                    input.disabled = true;

                });

        }

    }


    /* ========================================================
       REMOVE WEDDING DATE
       ======================================================== */

    weddingList.addEventListener(
        'click',
        function (event) {

            var button =
                event.target.closest(
                    '.remove-wedding-date'
                );

            if (!button) {
                return;
            }


            var row =
                button.closest(
                    '.wedding-date-row'
                );

            if (!row) {
                return;
            }


            row.remove();

            renumberWeddingDays();

        }
    );


    /* ========================================================
       ADD BUTTON
       ======================================================== */

    if (addWeddingButton) {

        addWeddingButton.addEventListener(
            'click',
            addWeddingDate
        );

    }


    /* ========================================================
       WEDDING INPUT LISTENER
       ======================================================== */

    weddingList.addEventListener(
        'input',
        function () {

            syncWeddingCompatibility();

        }
    );

    weddingList.addEventListener(
        'change',
        function () {

            syncWeddingCompatibility();

        }
    );


    /* ========================================================
       LOAD AVAILABLE SLOTS
       ======================================================== */

    function loadSlots(date) {

        if (!slotsBox) {
            return;
        }


        if (!date) {

            slotsBox.innerHTML =
                '<em style="color:#817a73">' +
                'Select a date to see available times.' +
                '</em>';

            return;

        }


        slotsBox.innerHTML =
            '<em style="color:#817a73">' +
            'Loading available times…' +
            '</em>';


        fetch(
            SITE_URL +
            '/booking/check-availability.php?date=' +
            encodeURIComponent(date)
        )

        .then(function (response) {

            if (!response.ok) {
                throw new Error('Availability request failed');
            }

            return response.json();

        })

        .then(function (data) {

            if (
                !data.slots ||
                !data.slots.length
            ) {

                slotsBox.innerHTML =
                    '<em style="color:#817a73">' +
                    'No slots available.' +
                    '</em>';

                return;

            }


            slotsBox.innerHTML = '';


            data.slots.forEach(
                function (slot) {

                    var button =
                        document.createElement(
                            'button'
                        );

                    button.type =
                        'button';

                    button.className =
                        'slot';


                    if (!slot.available) {

                        button.classList.add(
                            'disabled'
                        );

                        button.disabled =
                            true;

                    }


                    button.textContent =
                        slot.time;


                    if (slot.available) {

                        button.addEventListener(
                            'click',
                            function () {

                                slotsBox
                                    .querySelectorAll(
                                        '.slot'
                                    )
                                    .forEach(
                                        function (item) {

                                            item.classList.remove(
                                                'selected'
                                            );

                                        }
                                    );


                                button.classList.add(
                                    'selected'
                                );


                                if (normalStart) {

                                    normalStart.value =
                                        slot.time;

                                }


                                /*
                                 * Default end time:
                                 * 2 hours after start.
                                 */

                                var parts =
                                    slot.time.split(':');

                                var hour =
                                    parseInt(
                                        parts[0],
                                        10
                                    );

                                var minute =
                                    parts[1] || '00';


                                hour += 2;


                                if (hour > 23) {
                                    hour = 23;
                                }


                                if (normalEnd) {

                                    normalEnd.value =
                                        String(hour)
                                            .padStart(2, '0')
                                        +
                                        ':' +
                                        minute;

                                }

                            }
                        );

                    }


                    slotsBox.appendChild(
                        button
                    );

                }
            );

        })

        .catch(function () {

            slotsBox.innerHTML =
                '<em style="color:#d99">' +
                'Could not load availability.' +
                '</em>';

        });

    }


    /* ========================================================
       RECALCULATE
       ======================================================== */

    function recalculate() {

        var service =
            getSelectedService();

        var pkg =
            getSelectedPackage();

        var pricing =
            getPricing();


        if (sumService) {

            sumService.textContent =
                service
                    ? service.name
                    : '—';

        }


        if (sumPackage) {

            sumPackage.textContent =
                pkg
                    ? pkg.name
                    : '—';

        }


        if (sumTotal) {

            sumTotal.textContent =
                rwf(pricing.total);

        }


        if (sumDeposit) {

            sumDeposit.textContent =
                rwf(pricing.deposit);

        }


        if (sumPaid) {

            sumPaid.textContent =
                rwf(pricing.paid);

        }


        if (sumBalance) {

            sumBalance.textContent =
                rwf(pricing.balance);

        }


        if (depositHelp) {

            if (pricing.total > 0) {

                depositHelp.textContent =
                    'Minimum required deposit: ' +
                    rwf(pricing.deposit);

            } else {

                depositHelp.textContent =
                    'Select a service or package first.';

            }

        }


        /*
         * The customer enters the amount manually.
         * Keep the 30% minimum as validation only;
         * never fill or overwrite the amount automatically.
         */

        amountPaid.min =
            pricing.deposit;

        amountPaid.max =
            pricing.total;

    }


    /* ========================================================
       COLLAPSIBLE SERVICE PICKER
       ======================================================== */

    var servicePicker = document.getElementById('servicePicker');
    var chooseServiceToggle = document.getElementById('chooseServiceToggle');
    var changeServiceBtn = document.getElementById('changeServiceBtn');
    var selectedServicePreview = document.getElementById('selectedServicePreview');
    var selectedServiceName = document.getElementById('selectedServiceName');

    function updateServicePickerState(open) {
        if (!servicePicker || !chooseServiceToggle) return;
        servicePicker.classList.toggle('is-open', !!open);
        chooseServiceToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        chooseServiceToggle.querySelector('.choose-service-toggle-icon').textContent = open ? '−' : '＋';
    }

    function updateSelectedServicePreview() {
        var service = getSelectedService();
        var hasSelection = !!service;
        if (selectedServicePreview) selectedServicePreview.hidden = !hasSelection;
        if (selectedServiceName) selectedServiceName.textContent = service ? service.name : '—';
        if (chooseServiceToggle) chooseServiceToggle.style.display = hasSelection ? 'none' : 'flex';
    }

    if (chooseServiceToggle) {
        chooseServiceToggle.addEventListener('click', function () {
            updateServicePickerState(!servicePicker.classList.contains('is-open'));
        });
    }

    if (changeServiceBtn) {
        changeServiceBtn.addEventListener('click', function () {
            updateSelectedServicePreview();
            if (chooseServiceToggle) chooseServiceToggle.style.display = 'flex';
            updateServicePickerState(true);
            if (servicePicker) servicePicker.scrollIntoView({behavior:'smooth', block:'nearest'});
        });
    }

    /* ========================================================
       PREMIUM SERVICE CARDS
       ======================================================== */

    var serviceCards =
        document.querySelectorAll('[data-service-card]');

    function syncServiceCards() {
        var selectedId = serviceSelect.value;

        serviceCards.forEach(function (card) {
            var active = card.getAttribute('data-service-card') === String(selectedId);
            card.classList.toggle('is-selected', active);
            card.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    serviceCards.forEach(function (card) {
        card.addEventListener('click', function () {
            var id = this.getAttribute('data-service-card');

            /* Studio Photoshoot uses the dedicated Studio form.
             * All other services — including Wedding Photography —
             * remain on this normal booking form.
             */
            /* Studio Photoshoot always uses the dedicated form.
             * Use the card's server-generated flag first, then ID 4
             * as a safe fallback. This also works when the site language
             * changes the displayed service name to Kinyarwanda/French.
             */
            var studioFlag = this.getAttribute('data-studio-link') === '1';
            var isStudioId = parseInt(id, 10) === 4;

            if (studioFlag || isStudioId) {
                window.location.href = '/studio-booking.php';
                return;
            }

            serviceSelect.value = id;
            syncServiceCards();
            serviceSelect.dispatchEvent(new Event('change', { bubbles: true }));
            updateSelectedServicePreview();
            updateServicePickerState(false);
        });
    });

    syncServiceCards();
    updateSelectedServicePreview();

    /* ========================================================
       SERVICE CHANGE
       ======================================================== */

    serviceSelect.addEventListener(
        'change',
        function () {

            loadPackages(
                this.value
            );

            toggleBookingMode();
            updateSelectedServicePreview();

        }
    );


    /* ========================================================
       PACKAGE CHANGE
       ======================================================== */

    packageSelect.addEventListener(
        'change',
        function () {

            recalculate();

        }
    );


    /* ========================================================
       DATE CHANGE
       ======================================================== */

    if (normalDate) {

        normalDate.addEventListener(
            'change',
            function () {

                if (
                    !isWeddingService()
                ) {

                    loadSlots(
                        this.value
                    );

                }

            }
        );

    }


    /* ========================================================
       AMOUNT CHANGE
       ======================================================== */

    amountPaid.addEventListener(
        'input',
        function () {

            var pricing =
                getPricing();

            var value =
                parseFloat(
                    this.value || 0
                );


            if (
                pricing.total > 0 &&
                value < pricing.deposit
            ) {

                this.setCustomValidity(
                    'A minimum deposit of ' +
                    rwf(pricing.deposit) +
                    ' is required.'
                );

            } else if (
                pricing.total > 0 &&
                value > pricing.total
            ) {

                this.setCustomValidity(
                    'Amount paid cannot be greater than ' +
                    rwf(pricing.total) +
                    '.'
                );

            } else {

                this.setCustomValidity('');

            }


            recalculate();

        }
    );


    /* ========================================================
       FORM SUBMIT VALIDATION
       ======================================================== */

    form.addEventListener(
        'submit',
        function (event) {

            var service =
                getSelectedService();


            if (!service) {

                event.preventDefault();

                alert(
                    'Please select a service.'
                );

                serviceSelect.focus();

                return;

            }


            /* -----------------------------------------------
               Wedding validation
               ----------------------------------------------- */

            if (isWeddingService()) {

                /*
                 * Validate only visible/active wedding rows.
                 * Hidden/disabled rows must not trigger a false error.
                 */

                var rows =
                    weddingList.querySelectorAll(
                        '.wedding-date-row'
                    );

                var validRows = [];

                for (
                    var weddingIndex = 0;
                    weddingIndex < rows.length;
                    weddingIndex++
                ) {

                    var row = rows[weddingIndex];

                    if (
                        row.offsetParent === null ||
                        row.querySelector('.wedding-date:disabled')
                    ) {
                        continue;
                    }

                    var date =
                        row.querySelector('.wedding-date');

                    var type =
                        row.querySelector('.wedding-event-type');

                    var start =
                        row.querySelector('.wedding-start-time');

                    var end =
                        row.querySelector('.wedding-end-time');

                    var dateValue =
                        date
                            ? String(date.value || '').trim()
                            : '';

                    var typeValue =
                        type
                            ? String(type.value || '').trim()
                            : '';

                    var startValue =
                        start
                            ? String(start.value || '').trim()
                            : '';

                    var endValue =
                        end
                            ? String(end.value || '').trim()
                            : '';

                    if (
                        !dateValue ||
                        !typeValue ||
                        !startValue ||
                        !endValue
                    ) {

                        event.preventDefault();

                        alert(
                            'Please complete all fields for Wedding Day ' +
                            (validRows.length + 1) +
                            '.'
                        );

                        if (!dateValue && date) {
                            date.focus();
                        } else if (!typeValue && type) {
                            type.focus();
                        } else if (!startValue && start) {
                            start.focus();
                        } else if (!endValue && end) {
                            end.focus();
                        }

                        return;
                    }

                    if (startValue >= endValue) {

                        event.preventDefault();

                        alert(
                            'End time must be after start time for Wedding Day ' +
                            (validRows.length + 1) +
                            '.'
                        );

                        if (end) {
                            end.focus();
                        }

                        return;
                    }

                    validRows.push(row);
                }

                if (!validRows.length) {

                    event.preventDefault();

                    alert(
                        'Please add at least one wedding date.'
                    );

                    return;
                }

                /*
                 * process.php currently receives the first/main
                 * booking date and time fields. Wedding Day 1 is
                 * copied into those compatibility fields.
                 */

                syncWeddingCompatibility();

                if (
                    !weddingBookingDate.value ||
                    !weddingStart.value ||
                    !weddingEnd.value
                ) {

                    event.preventDefault();

                    alert(
                        'Please complete Wedding Day 1.'
                    );

                    return;
                }

            } else {

                /* -------------------------------------------
                   Normal service validation
                   ------------------------------------------- */

                if (
                    !normalDate ||
                    !normalDate.value ||
                    !normalStart ||
                    !normalStart.value ||
                    !normalEnd ||
                    !normalEnd.value
                ) {

                    event.preventDefault();

                    alert(
                        'Please select a date and valid time.'
                    );

                    return;

                }


                if (
                    normalStart.value >=
                    normalEnd.value
                ) {

                    event.preventDefault();

                    alert(
                        'End time must be after start time.'
                    );

                    return;

                }

            }


            /* -----------------------------------------------
               Client details
               ----------------------------------------------- */

            var requiredFields = [

                'full_name',
                'phone',
                'email',
                'location'

            ];


            for (
                var i = 0;
                i < requiredFields.length;
                i++
            ) {

                var field =
                    document.getElementById(
                        requiredFields[i]
                    );


                if (
                    !field ||
                    !field.value.trim()
                ) {

                    event.preventDefault();

                    alert(
                        'Please complete all required client details.'
                    );

                    if (field) {
                        field.focus();
                    }

                    return;

                }

            }


            /* -----------------------------------------------
               Email
               ----------------------------------------------- */

            var email =
                document.getElementById(
                    'email'
                );


            if (
                email &&
                !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(
                    email.value
                )
            ) {

                event.preventDefault();

                alert(
                    'Please enter a valid email address.'
                );

                email.focus();

                return;

            }


            /* -----------------------------------------------
               Payment
               ----------------------------------------------- */

            var pricing =
                getPricing();

            var paid =
                parseFloat(
                    amountPaid.value || 0
                );


            if (pricing.total <= 0) {

                event.preventDefault();

                alert(
                    'Please select a service or package with a valid price.'
                );

                return;

            }


            if (
                paid < pricing.deposit
            ) {

                event.preventDefault();

                amountPaid.focus();

                alert(
                    'A 30% deposit of ' +
                    rwf(pricing.deposit) +
                    ' is required.'
                );

                return;

            }


            if (
                paid > pricing.total
            ) {

                event.preventDefault();

                amountPaid.value =
                    pricing.total;

                amountPaid.focus();

                alert(
                    'Amount paid cannot be greater than ' +
                    rwf(pricing.total) +
                    '.'
                );

                return;

            }


            amountPaid.setCustomValidity('');


            /* -----------------------------------------------
               Prevent double submit
               ----------------------------------------------- */

            if (submitButton) {

                submitButton.disabled =
                    true;

                submitButton.textContent =
                    'Submitting booking…';

            }

        }
    );


    /* ========================================================
       INITIALIZE
       ======================================================== */

    if (serviceSelect.value) {

        loadPackages(
            serviceSelect.value
        );

    }


    toggleBookingMode();

    recalculate();


    if (
        normalDate &&
        normalDate.value &&
        !isWeddingService()
    ) {

        loadSlots(
            normalDate.value
        );

    }


    /*
     * If the page was returned after an error and
     * wedding dates already existed, make sure their
     * enabled/disabled state is correct.
     */

    if (isWeddingService()) {

        syncWeddingCompatibility();

    }

})();

</script>


<?php

require_once __DIR__ . '/includes/footer.php';

?>