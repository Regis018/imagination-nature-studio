<?php
/**
 * IMAGINATION NATURE STUDIO
 * SIMPLE BOOKING PAGE
 *
 * Keeps the existing booking/process.php contract while giving
 * clients a much shorter, mobile-first booking experience.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/language.php';

$bookingLang = site_lang();

$pageTitle = 'Book · Imagination Nature Studio';
$pdo = db();

$services = [];
try {
    $stmt = $pdo->query(
        "SELECT id, name, price, description
         FROM services
         WHERE status = 'active'
         ORDER BY name ASC"
    );
    $services = $stmt->fetchAll();
    foreach ($services as &$__service) {
        $__service['_original_name'] = (string)($__service['name'] ?? '');
        $__service['_original_description'] = (string)($__service['description'] ?? '');
    }
    unset($__service);
    if (function_exists('localize_services')) {
        $services = localize_services($services);
    }
} catch (Throwable $e) {
    $services = [];
}

$old = $_SESSION['booking_old'] ?? [];
$errors = $_SESSION['booking_errors'] ?? [];
if (!is_array($old)) $old = [];
if (!is_array($errors)) $errors = [$errors];
unset($_SESSION['booking_old'], $_SESSION['booking_errors']);

function simple_old($key, $default = '') {
    global $old;
    return isset($old[$key]) ? (string)$old[$key] : $default;
}

function simple_rwf($amount) {
    return 'RWF ' . number_format((float)$amount);
}

/* Studio Photo Offers — fixed marketing content, translated with the booking language. */
function studio_offer_text(string $key): string {
    global $bookingLang;
    $lang = $bookingLang ?: 'en';

    $translations = [
        'en' => [
            'title'=>'Studio Photo Offers',
            'subtitle'=>'Choose your photos after the session and enjoy extra photos or frames depending on the number selected.',
            'price_label'=>'Photo price','per_photo'=>'3,000 RWF / photo',
            'photos'=>'Photos','no_discount'=>'No discount','get'=>'Get',
            'free_photo'=>'free photo','free_photos'=>'free photos','photo'=>'photo',
            'free'=>'Free','frame'=>'frame','group'=>'Group Discount','off'=>'OFF',
            'note'=>'These offers apply to Studio Photoshoot. The final invoice is calculated from the photos selected after your session.'
        ],
        'rw' => [
            'title'=>'Amahitamo ya Studio Photoshoot',
            'subtitle'=>'Nyuma ya session, hitamo amafoto ushaka kandi ubone amafoto y’inyongera cyangwa frames bitewe n’umubare w’amafoto wahisemo.',
            'price_label'=>'Igiciro cy’ifoto','per_photo'=>'3,000 RWF / ifoto',
            'photos'=>'Amafoto','no_discount'=>'Nta discount','get'=>'Bona',
            'free_photo'=>'ifoto y’ubuntu','free_photos'=>'amafoto y’ubuntu','photo'=>'ifoto',
            'free'=>'Ubuntu','frame'=>'frame','group'=>'Discount y’amafoto menshi','off'=>'OFF',
            'note'=>'Aya mahitamo ni aya Studio Photoshoot. Invoice ya nyuma ibarwa hashingiwe ku mafoto wahisemo nyuma ya session.'
        ],
        'fr' => [
            'title'=>'Offres Photo Studio',
            'subtitle'=>'Après la séance, choisissez vos photos et profitez de photos supplémentaires ou de cadres selon le nombre sélectionné.',
            'price_label'=>'Prix par photo','per_photo'=>'3 000 RWF / photo',
            'photos'=>'Photos','no_discount'=>'Pas de remise','get'=>'Obtenez',
            'free_photo'=>'photo gratuite','free_photos'=>'photos gratuites','photo'=>'photo',
            'free'=>'Gratuit','frame'=>'cadre','group'=>'Remise de groupe','off'=>'DE REMISE',
            'note'=>'Ces offres concernent le Studio Photoshoot. La facture finale est calculée selon les photos sélectionnées après votre séance.'
        ],
    ];

    return $translations[$lang][$key] ?? $translations['en'][$key] ?? $key;
}

function booking_t(string $text): string {
    $lang = function_exists('site_lang') ? site_lang() : 'en';
    if ($lang === 'en') return $text;
    $map = [
        'rw' => [
            'Book Your Session'=>'Bika Gahunda Yawe',
            'Choose your service, pick a date and time, then leave us your WhatsApp details.'=>'Hitamo serivisi, uhitemo itariki n’igihe, hanyuma udusigire amakuru ya WhatsApp.',
            'Service *'=>'Serivisi *','Choose a service'=>'Hitamo serivisi','Wedding package'=>'Package y’ubukwe','Choose package (optional)'=>'Hitamo package (birashoboka)',
            'Date *'=>'Itariki *','Time *'=>'Igihe *','Choose a date first.'=>'Banza uhitemo itariki.','Your name *'=>'Amazina yawe *','WhatsApp / Phone *'=>'WhatsApp / Telefone *','Email *'=>'Email *','We will send your booking confirmation and invoice to this email.'=>'Tuzohereza confirmation ya booking na invoice kuri iyi email.',
            'Message '=>'Ubutumwa ','Anything you want us to know?'=>'Hari icyo ushaka kutubwira?','BOOK NOW'=>'BIKA UBU',
            'Your request will be reviewed and confirmed by Imagination Nature Studio.'=>'Ubusabe bwawe buzarebwa kandi bwemezwe na Imagination Nature Studio.',
            'Wedding dates'=>'Amatariki y’ubukwe','Add another day only if your wedding package has more than one event day.'=>'Ongeramo indi tariki gusa niba package y’ubukwe ifite iminsi irenze umwe.',
            '+ Add another date'=>'+ Ongeramo indi tariki','Wedding Day'=>'Umunsi w’ubukwe','Remove'=>'Kuraho','Event / Day Type *'=>'Ubwoko bw’umunsi / Event *',
            'Start *'=>'Gutangira *','End *'=>'Kurangiza *','Civil, Traditional, Reception'=>'Civil, Traditional, Reception',
            'Loading…'=>'Birimo gutegurwa…','No available times for this date.'=>'Nta masaha aboneka kuri iyi tariki.','Could not load available times. Please try again.'=>'Ntibyashoboye kubona amasaha aboneka. Ongera ugerageze.',
            'Loading times…'=>'Turimo gushaka amasaha…'
        ],
        'fr' => [
            'Book Your Session'=>'Réserver votre séance',
            'Choose your service, pick a date and time, then leave us your WhatsApp details.'=>'Choisissez votre service, sélectionnez une date et une heure, puis laissez vos coordonnées WhatsApp.',
            'Service *'=>'Service *','Choose a service'=>'Choisissez un service','Wedding package'=>'Forfait mariage','Choose package (optional)'=>'Choisir un forfait (facultatif)',
            'Date *'=>'Date *','Time *'=>'Heure *','Choose a date first.'=>'Choisissez d’abord une date.','Your name *'=>'Votre nom *','WhatsApp / Phone *'=>'WhatsApp / Téléphone *','Email *'=>'Email *','We will send your booking confirmation and invoice to this email.'=>'Nous enverrons la confirmation de votre réservation et la facture à cet email.',
            'Message '=>'Message ','Anything you want us to know?'=>'Quelque chose que vous souhaitez nous communiquer ?','BOOK NOW'=>'RÉSERVER',
            'Your request will be reviewed and confirmed by Imagination Nature Studio.'=>'Votre demande sera examinée et confirmée par Imagination Nature Studio.',
            'Wedding dates'=>'Dates du mariage','Add another day only if your wedding package has more than one event day.'=>'Ajoutez un autre jour seulement si votre forfait mariage comprend plusieurs jours.',
            '+ Add another date'=>'+ Ajouter une autre date','Wedding Day'=>'Jour du mariage','Remove'=>'Supprimer','Event / Day Type *'=>'Type d’événement / journée *',
            'Start *'=>'Début *','End *'=>'Fin *','Loading…'=>'Chargement…','No available times for this date.'=>'Aucun horaire disponible pour cette date.','Could not load available times. Please try again.'=>'Impossible de charger les horaires disponibles. Veuillez réessayer.',
            'Loading times…'=>'Chargement des horaires…'
        ]
    ];
    return $map[$lang][$text] ?? (function_exists('t') ? t($text) : $text);
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.simple-booking-page{padding:7rem 1rem 4rem;background:var(--bg,#0d0c0b);color:var(--cream,#f3ece3);min-height:70vh}
.simple-booking-wrap{width:min(720px,100%);margin:auto}
.simple-hero{text-align:center;margin-bottom:1.5rem}
.simple-hero h1{font-family:var(--font-serif,Georgia,serif);font-size:clamp(2rem,7vw,3rem);margin:0 0 .45rem}
.simple-hero p{margin:0;color:#aaa19a;line-height:1.6;font-size:.95rem}
.simple-card{background:rgba(255,255,255,.035);border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:1.15rem}
.simple-error{margin-bottom:1rem;padding:.9rem 1rem;border-radius:12px;background:rgba(190,55,55,.12);border:1px solid rgba(190,55,55,.35);color:#f1b5b5;line-height:1.5}
.simple-field{margin-bottom:1rem}
.simple-field label{display:block;margin-bottom:.42rem;font-size:.86rem;font-weight:600;color:#ddd5cd}
.simple-field input,.simple-field select,.simple-field textarea{width:100%;box-sizing:border-box;min-height:50px;border:1px solid rgba(255,255,255,.14);border-radius:12px;background:#171514;color:#f3ece3;padding:.8rem .9rem;font:inherit;outline:none}
.simple-field textarea{min-height:90px;resize:vertical}
.simple-field input:focus,.simple-field select:focus,.simple-field textarea:focus{border-color:rgba(255,255,255,.38)}
.simple-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 1rem}
.simple-full{grid-column:1/-1}
.simple-note{font-size:.78rem;color:#8f8982;margin-top:.35rem;line-height:1.45}
.simple-hidden{display:none!important}
.simple-slots{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.4rem}
.simple-slot{border:1px solid rgba(255,255,255,.14);background:#171514;color:#eee5dc;border-radius:10px;padding:.65rem .85rem;cursor:pointer;font:inherit}
.simple-slot:hover{border-color:rgba(255,255,255,.35)}
.simple-slot.selected{border-color:#f3ece3;background:#f3ece3;color:#111}
.simple-slot.disabled{opacity:.35;cursor:not-allowed;text-decoration:line-through}
.simple-wedding{border-top:1px solid rgba(255,255,255,.08);margin-top:1rem;padding-top:1rem}
.simple-wedding-title{font-family:var(--font-serif,Georgia,serif);font-size:1.15rem;margin:0 0 .35rem}
.simple-wedding-row{border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:.85rem;margin-top:.75rem}
.simple-wedding-head{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:.75rem}
.simple-small-btn{border:1px solid rgba(255,255,255,.14);background:transparent;color:#ddd5cd;border-radius:9px;padding:.45rem .65rem;cursor:pointer}
.simple-submit{width:100%;min-height:54px;border:0;border-radius:13px;background:#f3ece3;color:#111;font-size:1rem;font-weight:700;cursor:pointer;margin-top:.25rem}
.simple-submit:disabled{opacity:.55;cursor:wait}
.simple-bottom{margin-top:.75rem;text-align:center;color:#827b74;font-size:.78rem;line-height:1.5}
@media(max-width:650px){.simple-booking-page{padding:6rem .75rem 3rem}.simple-grid{grid-template-columns:1fr}.simple-full{grid-column:auto}.simple-card{padding:1rem}}

.simple-studio-offers{display:none;margin:1rem 0 0;padding:1rem;border:1px solid rgba(255,255,255,.10);border-radius:16px;background:rgba(255,255,255,.025)}
.simple-studio-offers.is-visible{display:block}
.simple-offer-head{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;margin-bottom:.9rem}
.simple-offer-title{font-family:var(--font-serif,Georgia,serif);font-size:1.25rem;margin:0 0 .25rem}
.simple-offer-subtitle{font-size:.78rem;color:#9b938c;line-height:1.45;margin:0}
.simple-offer-price{white-space:nowrap;font-weight:800;font-size:.9rem}
.simple-offer-price small{display:block;color:#8f8982;font-weight:500;font-size:.7rem;margin-bottom:.15rem}
.simple-offer-list{display:grid;gap:.5rem}
.simple-offer-item,.simple-group-item{display:flex;justify-content:space-between;gap:.75rem;align-items:center;padding:.7rem .75rem;border-radius:10px;background:rgba(255,255,255,.035);border:1px solid rgba(255,255,255,.07);font-size:.8rem}
.simple-offer-range{font-weight:700}
.simple-offer-reward{text-align:right;color:#bdb5ad}
.simple-offer-reward b{color:#f3ece3}
.simple-group-offer{margin-top:.8rem}
.simple-group-title{font-size:.9rem;margin:0 0 .5rem}
.simple-group-list{display:grid;gap:.45rem}
.simple-group-item strong{white-space:nowrap}
.simple-offer-note{font-size:.72rem;color:#817a73;line-height:1.45;margin:.75rem 0 0}
@media(max-width:650px){.simple-offer-head{display:block}.simple-offer-price{margin-top:.6rem}.simple-offer-item,.simple-group-item{align-items:flex-start}.simple-offer-reward{text-align:right}}
</style>

<main class="simple-booking-page">
<div class="simple-booking-wrap">

    <div class="simple-hero">
        <h1><?= e(booking_t('Book Your Session')) ?></h1>
        <p><?= e(booking_t('Choose your service, pick a date and time, then leave us your WhatsApp details.')) ?></p>
    </div>

    <?php if ($errors): ?>
        <div class="simple-error">
            <?php foreach ($errors as $error): ?>
                <div><?= e((string)$error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(SITE_URL) ?>/booking/process.php?lang=<?= e($bookingLang) ?>" id="simpleBookingForm" class="simple-card" novalidate>
        <?= csrf_field() ?>

        <div class="simple-field">
            <label for="service_id"><?= e(booking_t('Service *')) ?></label>
            <select name="service_id" id="service_id" required>
                <option value=""><?= e(booking_t('Choose a service')) ?></option>
                <?php foreach ($services as $service): ?>
                    <option
                        value="<?= (int)$service['id'] ?>"
                        data-name="<?= e($service['name']) ?>"
                        data-description="<?= e($service['description'] ?? '') ?>"
                        <?= simple_old('service_id') === (string)$service['id'] ? 'selected' : '' ?>
                    ><?= e($service['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div id="serviceNote" class="simple-note"></div>

            <section id="studioOffers" class="simple-studio-offers" aria-hidden="true">
                <div class="simple-offer-head">
                    <div>
                        <h2 class="simple-offer-title"><?= e(studio_offer_text('title')) ?></h2>
                        <p class="simple-offer-subtitle"><?= e(studio_offer_text('subtitle')) ?></p>
                    </div>
                    <div class="simple-offer-price">
                        <small><?= e(studio_offer_text('price_label')) ?></small>
                        <?= e(studio_offer_text('per_photo')) ?>
                    </div>
                </div>

                <div class="simple-offer-list">
                    <div class="simple-offer-item">
                        <span class="simple-offer-range">1–9 <?= e(studio_offer_text('photos')) ?></span>
                        <span class="simple-offer-reward"><?= e(studio_offer_text('no_discount')) ?></span>
                    </div>
                    <div class="simple-offer-item">
                        <span class="simple-offer-range">10–20 <?= e(studio_offer_text('photos')) ?></span>
                        <span class="simple-offer-reward"><?= e(studio_offer_text('get')) ?> <b>1 <?= e(studio_offer_text('free_photo')) ?></b></span>
                    </div>
                    <div class="simple-offer-item">
                        <span class="simple-offer-range">21–35 <?= e(studio_offer_text('photos')) ?></span>
                        <span class="simple-offer-reward"><?= e(studio_offer_text('get')) ?> <b>2 <?= e(studio_offer_text('free_photos')) ?></b></span>
                    </div>
                    <div class="simple-offer-item">
                        <span class="simple-offer-range">36–45 <?= e(studio_offer_text('photos')) ?></span>
                        <span class="simple-offer-reward"><?= e(studio_offer_text('get')) ?> <b>3 <?= e(studio_offer_text('free_photos')) ?></b></span>
                    </div>
                    <div class="simple-offer-item">
                        <span class="simple-offer-range">46–60 <?= e(studio_offer_text('photos')) ?></span>
                        <span class="simple-offer-reward"><?= e(studio_offer_text('free')) ?> <b>1 <?= e(studio_offer_text('photo')) ?></b> + A4 <?= e(studio_offer_text('frame')) ?></span>
                    </div>
                    <div class="simple-offer-item">
                        <span class="simple-offer-range">61–75 <?= e(studio_offer_text('photos')) ?></span>
                        <span class="simple-offer-reward"><?= e(studio_offer_text('free')) ?> <b>3 <?= e(studio_offer_text('free_photos')) ?></b> + A3 <?= e(studio_offer_text('frame')) ?></span>
                    </div>
                    <div class="simple-offer-item">
                        <span class="simple-offer-range">76–150 <?= e(studio_offer_text('photos')) ?></span>
                        <span class="simple-offer-reward"><?= e(studio_offer_text('free')) ?> <b>4 <?= e(studio_offer_text('free_photos')) ?></b> + A3 <?= e(studio_offer_text('frame')) ?></span>
                    </div>
                    <div class="simple-offer-item">
                        <span class="simple-offer-range">151–200 <?= e(studio_offer_text('photos')) ?></span>
                        <span class="simple-offer-reward"><?= e(studio_offer_text('free')) ?> <b>8 <?= e(studio_offer_text('free_photos')) ?></b> + A2 <?= e(studio_offer_text('frame')) ?></span>
                    </div>
                </div>

                <div class="simple-group-offer">
                    <h3 class="simple-group-title"><?= e(studio_offer_text('group')) ?></h3>
                    <div class="simple-group-list">
                        <div class="simple-group-item"><span>35–50 <?= e(studio_offer_text('photos')) ?></span><strong>5% <?= e(studio_offer_text('off')) ?></strong></div>
                        <div class="simple-group-item"><span>51–80 <?= e(studio_offer_text('photos')) ?></span><strong>10% <?= e(studio_offer_text('off')) ?></strong></div>
                        <div class="simple-group-item"><span>81–120 <?= e(studio_offer_text('photos')) ?></span><strong>15% <?= e(studio_offer_text('off')) ?></strong></div>
                        <div class="simple-group-item"><span>121–300 <?= e(studio_offer_text('photos')) ?></span><strong>20% <?= e(studio_offer_text('off')) ?></strong></div>
                    </div>
                </div>

                <p class="simple-offer-note"><?= e(studio_offer_text('note')) ?></p>
            </section>
        </div>

        <div id="weddingOnly" class="simple-hidden">
            <div class="simple-field">
                <label for="package_id"><?= e(booking_t('Wedding package')) ?></label>
                <select name="package_id" id="package_id">
                    <option value=""><?= e(booking_t('Choose package (optional)')) ?></option>
                </select>
            </div>
        </div>

        <div class="simple-grid">
            <div class="simple-field">
                <label for="booking_date"><?= e(booking_t('Date *')) ?></label>
                <input type="date" name="booking_date" id="booking_date" min="<?= date('Y-m-d') ?>" value="<?= e(simple_old('booking_date')) ?>" required>
            </div>

            <div class="simple-field">
                <label><?= e(booking_t('Time *')) ?></label>
                <div id="slots" class="simple-slots"><span class="simple-note"><?= e(booking_t('Choose a date first.')) ?></span></div>
                <input type="hidden" name="start_time" id="start_time" value="<?= e(simple_old('start_time')) ?>">
                <input type="hidden" name="end_time" id="end_time" value="<?= e(simple_old('end_time')) ?>">
            </div>
        </div>

        <div id="weddingDates" class="simple-wedding simple-hidden">
            <h2 class="simple-wedding-title"><?= e(booking_t('Wedding dates')) ?></h2>
            <div class="simple-note"><?= e(booking_t('Add another day only if your wedding package has more than one event day.')) ?></div>
            <div id="weddingList"></div>
            <button type="button" id="addWedding" class="simple-small-btn"><?= e(booking_t('+ Add another date')) ?></button>
        </div>

        <div class="simple-grid" style="margin-top:1rem">
            <div class="simple-field">
                <label for="full_name"><?= e(booking_t('Your name *')) ?></label>
                <input type="text" name="full_name" id="full_name" value="<?= e(simple_old('full_name')) ?>" autocomplete="name" required>
            </div>

            <div class="simple-field">
                <label for="phone"><?= e(booking_t('WhatsApp / Phone *')) ?></label>
                <input type="tel" name="phone" id="phone" value="<?= e(simple_old('phone')) ?>" autocomplete="tel" required placeholder="07XXXXXXXX">
            </div>

            <div class="simple-field simple-full">
                <label for="email"><?= e(booking_t('Email *')) ?></label>
                <input type="email" name="email" id="email" value="<?= e(simple_old('email')) ?>" autocomplete="email" required placeholder="yourname@example.com">
                <div class="simple-note"><?= e(booking_t('We will send your booking confirmation and invoice to this email.')) ?></div>
            </div>

            <div class="simple-field simple-full">
                <label for="customer_notes"><?= e(booking_t('Message ')) ?><span style="font-weight:400;color:#817a73">(<?= $bookingLang==='fr' ? 'facultatif' : ($bookingLang==='rw' ? 'birashoboka' : 'optional') ?>)</span></label>
                <textarea name="customer_notes" id="customer_notes" placeholder="<?= e(booking_t('Anything you want us to know?')) ?>"><?= e(simple_old('customer_notes')) ?></textarea>
            </div>
        </div>

        <!-- Values required by the existing process are kept out of the client's way. -->
        <input type="hidden" name="whatsapp" id="whatsapp" value="<?= e(simple_old('whatsapp')) ?>">
        <input type="hidden" name="location" value="Kigali">
        <input type="hidden" name="event_location" id="event_location" value="<?= e(simple_old('event_location', 'Kigali')) ?>">
        <input type="hidden" name="company" value="<?= e(simple_old('company')) ?>">
        <input type="hidden" name="event_type" id="event_type" value="<?= e(simple_old('event_type')) ?>">
        <input type="hidden" name="number_of_people" value="1">
        <input type="hidden" name="payment_method" value="cash">
        <input type="hidden" name="amount_paid" value="0">
        <input type="hidden" name="booking_ui" value="simple">
        <input type="hidden" name="lang" value="<?= e($bookingLang) ?>">

        <button type="submit" class="simple-submit" id="submitBooking"><?= e(booking_t('BOOK NOW')) ?></button>
        <div class="simple-bottom"><?= e(booking_t('Your request will be reviewed and confirmed by Imagination Nature Studio.')) ?></div>
    </form>
</div>
</main>

<script>
(function(){
    'use strict';
    var SITE_URL = <?= json_encode(rtrim(SITE_URL,'/')) ?>;
    var BOOKING_LANG = <?= json_encode($bookingLang) ?>;
    var T = <?= json_encode([
        'chooseDate'=>booking_t('Choose a date first.'),
        'loading'=>booking_t('Loading times…'),
        'noSlots'=>booking_t('No available times for this date.'),
        'failed'=>booking_t('Could not load available times. Please try again.'),
        'choosePackage'=>booking_t('Choose package (optional)'),
        'loadingPackage'=>booking_t('Loading…'),
        'weddingDay'=>'Wedding Day',
        'remove'=>'Remove',
        'date'=>'Date *',
        'dayType'=>'Event / Day Type *',
        'start'=>'Start *',
        'end'=>'End *',
        'addWedding'=>'+ Add another date'
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    var services = <?= json_encode($services, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    var oldPackage = <?= json_encode(simple_old('package_id')) ?>;
    var service = document.getElementById('service_id');
    var date = document.getElementById('booking_date');
    var slots = document.getElementById('slots');
    var start = document.getElementById('start_time');
    var end = document.getElementById('end_time');
    var note = document.getElementById('serviceNote');
    var studioOffers = document.getElementById('studioOffers');
    var weddingOnly = document.getElementById('weddingOnly');
    var weddingDates = document.getElementById('weddingDates');
    var packageSelect = document.getElementById('package_id');
    var weddingList = document.getElementById('weddingList');
    var addWedding = document.getElementById('addWedding');
    var form = document.getElementById('simpleBookingForm');

    function selectedService(){
        var id = parseInt(service.value,10);
        return services.find(function(s){ return parseInt(s.id,10) === id; }) || null;
    }
    function isWedding(){
        var s = selectedService();
        var n = s ? String(s._original_name || s.name || '').toLowerCase() : '';
        return n.indexOf('wedding') !== -1;
    }

    /* Use the original database service name so translation never breaks Studio logic. */
    function isStudio(){
        var s = selectedService();
        var n = s ? String(s._original_name || s.name || '').toLowerCase() : '';
        return n.indexOf('studio') !== -1 && n.indexOf('photo') !== -1;
    }

    function rwf(v){ return 'RWF ' + Number(v||0).toLocaleString('en-US'); }

    function loadPackages(){
        if(!isWedding()){
            packageSelect.innerHTML = '<option value=""><?= e(booking_t('Choose package (optional)')) ?></option>';
            return;
        }
        packageSelect.innerHTML = '<option value="">' + T.loadingPackage + '</option>';
        fetch(SITE_URL + '/booking/get-packages.php?service_id=' + encodeURIComponent(service.value) + '&lang=' + encodeURIComponent(BOOKING_LANG))
            .then(function(r){ if(!r.ok) throw new Error(); return r.json(); })
            .then(function(data){
                packageSelect.innerHTML = '<option value=""><?= e(booking_t('Choose package (optional)')) ?></option>';
                (data.packages||[]).forEach(function(p){
                    var o=document.createElement('option');
                    o.value=p.id; o.textContent=p.name + ' · ' + rwf(p.price);
                    o.setAttribute('data-price',p.price);
                    packageSelect.appendChild(o);
                });
                if(oldPackage) packageSelect.value=String(oldPackage);
            })
            .catch(function(){ packageSelect.innerHTML='<option value=""><?= e(booking_t('Choose package (optional)')) ?></option>'; });
    }

    function updateMode(){
        var s=selectedService();
        note.textContent=s && s.description ? s.description : '';

        var studio = isStudio();
        studioOffers.classList.toggle('is-visible', studio);
        studioOffers.setAttribute('aria-hidden', studio ? 'false' : 'true');

        weddingOnly.classList.toggle('simple-hidden', !isWedding());
        weddingDates.classList.toggle('simple-hidden', !isWedding());
        loadPackages();
        if(isWedding() && !weddingList.children.length) addWeddingRow();
    }

    function loadSlots(){
        if(!date.value){ slots.innerHTML='<span class="simple-note">' + T.chooseDate + '</span>'; return; }
        slots.innerHTML='<span class="simple-note">' + T.loading + '</span>';
        fetch(SITE_URL + '/booking/check-availability.php?date=' + encodeURIComponent(date.value) + '&lang=' + encodeURIComponent(BOOKING_LANG))
            .then(function(r){ if(!r.ok) throw new Error(); return r.json(); })
            .then(function(data){
                slots.innerHTML='';
                if(!data.slots || !data.slots.length){ slots.innerHTML='<span class="simple-note">' + T.noSlots + '</span>'; return; }
                data.slots.forEach(function(slot){
                    var b=document.createElement('button');
                    b.type='button'; b.className='simple-slot' + (!slot.available?' disabled':'');
                    b.textContent=slot.time;
                    b.disabled=!slot.available;
                    if(slot.available){
                        b.addEventListener('click',function(){
                            document.querySelectorAll('.simple-slot').forEach(function(x){x.classList.remove('selected');});
                            b.classList.add('selected');
                            start.value=slot.time;
                            var p=slot.time.split(':'), h=parseInt(p[0],10)+2;
                            if(h>23) h=23;
                            end.value=String(h).padStart(2,'0') + ':' + (p[1]||'00');
                        });
                    }
                    slots.appendChild(b);
                });
            })
            .catch(function(){ slots.innerHTML='<span class="simple-note">' + T.failed + '</span>'; });
    }

    function addWeddingRow(){
        var index=weddingList.children.length;
        var row=document.createElement('div');
        row.className='simple-wedding-row';
        row.innerHTML='<div class="simple-wedding-head"><strong>Wedding Day '+(index+1)+'</strong>'+(index?' <button type="button" class="simple-small-btn remove-day">Remove</button>':'')+'</div>'+
            '<div class="simple-grid">'+
            '<div class="simple-field"><label>Date *</label><input type="date" class="w-date" min="<?= date('Y-m-d') ?>" name="wedding_dates['+index+'][date]" required></div>'+
            '<div class="simple-field"><label>Event / Day Type *</label><input type="text" class="w-type" name="wedding_dates['+index+'][event_type]" placeholder="Civil, Traditional, Reception" required></div>'+
            '<div class="simple-field"><label>Start *</label><input type="time" class="w-start" name="wedding_dates['+index+'][start_time]" required></div>'+
            '<div class="simple-field"><label>End *</label><input type="time" class="w-end" name="wedding_dates['+index+'][end_time]" required></div>'+
            '</div>';
        weddingList.appendChild(row);
        row.querySelectorAll('input').forEach(function(i){i.addEventListener('change',syncWedding);});
        var remove=row.querySelector('.remove-day');
        if(remove) remove.addEventListener('click',function(){row.remove(); renumberWedding();});
    }
    function renumberWedding(){
        Array.from(weddingList.children).forEach(function(row,index){
            var title=row.querySelector('.simple-wedding-head strong'); if(title) title.textContent='Wedding Day '+(index+1);
            row.querySelectorAll('[name]').forEach(function(i){i.name=i.name.replace(/wedding_dates\[\d+\]/,'wedding_dates['+index+']');});
        });
        syncWedding();
    }
    function syncWedding(){
        if(!isWedding()) return;
        var first=weddingList.querySelector('.simple-wedding-row'); if(!first) return;
        var d=first.querySelector('.w-date'), s=first.querySelector('.w-start'), e=first.querySelector('.w-end'), t=first.querySelector('.w-type');
        if(d) date.value=d.value;
        if(s) start.value=s.value;
        if(e) end.value=e.value;
        var et=document.getElementById('event_type'); if(et) et.value=t?t.value:'';
    }

    service.addEventListener('change',updateMode);
    date.addEventListener('change',function(){ if(!isWedding()) loadSlots(); });
    addWedding.addEventListener('click',addWeddingRow);
    form.addEventListener('submit',function(){
        var phone=document.getElementById('phone'), wa=document.getElementById('whatsapp');
        if(phone && wa && !wa.value) wa.value=phone.value;
        var email = document.getElementById('email');
        if(email && !email.checkValidity()){
            event.preventDefault();
            email.reportValidity();
            return false;
        }
        if(isWedding()) syncWedding();
    });

    updateMode();
    if(date.value && !isWedding()) loadSlots();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
