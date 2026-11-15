<?php
/**
 * IMAGINATION NATURE STUDIO
 * Studio Photo Shoot Booking
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/language.php';

$pageTitle = 'Studio Photo Shoot · ' . setting('site_name', SITE_NAME);
$pdo = db();

/* Load active services */
$services = [];

try {
    $stmt = $pdo->query(
        "SELECT id, name, price, description,
                name_rw, description_rw,
                name_fr, description_fr
         FROM services
         WHERE status = 'active'
         ORDER BY name ASC"
    );

    $services = localize_services($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    $services = [];
}

/* Studio Photoshoot is service ID 4 in the current database. */
$studioService = null;

foreach ($services as $service) {
    if ((int)($service['id'] ?? 0) === 4) {
        $studioService = $service;
        break;
    }
}

/* Do not silently use another service if ID 4 is missing. */
if (!$studioService) {
    $studioService = [
        'id' => 4,
        'name' => 'Studio Photoshoot',
        'price' => 0,
        'description' => '',
    ];
}

$errors = $_SESSION['booking_errors'] ?? [];
$old = $_SESSION['booking_old'] ?? [];

if (!is_array($errors)) {
    $errors = [$errors];
}
if (!is_array($old)) {
    $old = [];
}

unset($_SESSION['booking_errors'], $_SESSION['booking_old']);

function studio_old($key, $default = '')
{
    global $old;
    return isset($old[$key]) ? (string)$old[$key] : $default;
}

function studio_rwf($amount)
{
    return 'RWF ' . number_format((float)$amount);
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.studio-booking-page{
    min-height:100vh;
    padding:7.5rem 1rem 5rem;
    background:#0d0c0b;
    color:#f5eee7;
}
.studio-booking-wrap{
    width:min(900px,100%);
    margin:auto;
}
.studio-hero{text-align:center;margin-bottom:2rem}
.studio-kicker{
    display:inline-block;
    font-size:.75rem;
    letter-spacing:.16em;
    text-transform:uppercase;
    color:#c79a73;
    margin-bottom:.7rem;
}
.studio-hero h1{
    margin:0;
    font-family:Georgia,serif;
    font-size:clamp(2rem,5vw,3.4rem);
}
.studio-hero p{
    max-width:650px;
    margin:.8rem auto 0;
    color:#aaa19a;
    line-height:1.7;
}
.studio-card{
    background:#151311;
    border:1px solid rgba(255,255,255,.08);
    border-radius:22px;
    overflow:hidden;
    box-shadow:0 25px 80px rgba(0,0,0,.35);
}
.studio-steps{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    border-bottom:1px solid rgba(255,255,255,.08);
}
.studio-step{
    padding:1rem .6rem;
    text-align:center;
    color:#756f69;
    font-size:.82rem;
}
.studio-step.active{color:#fff;background:#1b1815}
.studio-step b{
    display:block;
    margin:auto auto .35rem;
    width:28px;height:28px;
    line-height:28px;
    border-radius:50%;
    background:#28231f;
}
.studio-step.active b{background:#b98254}
.studio-body{padding:2rem}
.studio-screen{display:none}
.studio-screen.active{display:block}
.studio-title{
    margin:0 0 .4rem;
    font-size:1.45rem;
}
.studio-sub{
    color:#99918b;
    margin:0 0 1.5rem;
}
.studio-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:1rem;
}
.studio-choice{
    border:1px solid rgba(255,255,255,.1);
    border-radius:16px;
    padding:1.15rem;
    background:#191715;
    cursor:pointer;
    transition:.18s ease;
}
.studio-choice:hover{transform:translateY(-2px);border-color:#b98254}
.studio-choice.selected{
    border:2px solid #c08a5e;
    background:#201a16;
}
.studio-choice strong{display:block;font-size:1rem}
.studio-choice span{
    display:block;
    color:#938b84;
    font-size:.86rem;
    line-height:1.5;
    margin-top:.35rem;
}
.studio-choice .price{
    color:#e0b38c;
    font-weight:800;
    margin-top:.7rem;
}
.studio-field{margin-bottom:1rem}
.studio-field label{
    display:block;
    margin-bottom:.45rem;
    font-size:.88rem;
    font-weight:700;
}
.studio-field input,
.studio-field select,
.studio-field textarea{
    width:100%;
    box-sizing:border-box;
    border:1px solid rgba(255,255,255,.12);
    background:#100f0e;
    color:#fff;
    border-radius:12px;
    padding:.9rem 1rem;
    font:inherit;
}
.studio-field textarea{min-height:95px;resize:vertical}
.studio-two{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:1rem;
}
.studio-actions{
    display:flex;
    justify-content:space-between;
    gap:.8rem;
    margin-top:1.5rem;
}
.studio-btn{
    border:0;
    border-radius:12px;
    padding:.9rem 1.25rem;
    font-weight:800;
    cursor:pointer;
}
.studio-btn.back{background:#292521;color:#ddd}
.studio-btn.next,
.studio-btn.confirm{background:#b98254;color:#fff}
.studio-summary{
    background:#100f0e;
    border:1px solid rgba(255,255,255,.08);
    border-radius:15px;
    padding:1rem 1.1rem;
    margin-top:1.2rem;
}
.studio-summary-row{
    display:flex;
    justify-content:space-between;
    gap:1rem;
    padding:.55rem 0;
    color:#aaa19a;
}
.studio-summary-row strong{color:#fff;text-align:right}
.studio-summary-total{
    border-top:1px solid rgba(255,255,255,.1);
    margin-top:.35rem;
    padding-top:.8rem;
    font-size:1.1rem;
}
.studio-notice{
    background:rgba(185,130,84,.1);
    border:1px solid rgba(185,130,84,.25);
    color:#d9bda3;
    border-radius:12px;
    padding:.85rem 1rem;
    line-height:1.5;
    margin-bottom:1rem;
}
.studio-error{
    background:rgba(190,55,55,.1);
    border:1px solid rgba(190,55,55,.35);
    color:#efb7b7;
    padding:1rem;
    border-radius:12px;
    margin-bottom:1rem;
}
.studio-error div+div{margin-top:.35rem}
.studio-empty{
    padding:1.5rem;
    border-radius:14px;
    background:#191715;
    color:#aaa19a;
}
.studio-small{
    color:#77706a;
    font-size:.8rem;
    line-height:1.5;
    margin-top:.8rem;
}
@media(max-width:650px){
    .studio-booking-page{padding:6rem .7rem 3rem}
    .studio-body{padding:1.25rem}
    .studio-grid,.studio-two{grid-template-columns:1fr}
    .studio-step{font-size:.68rem}
    .studio-step b{width:24px;height:24px;line-height:24px}
}
</style>

<main class="studio-booking-page">
<div class="studio-booking-wrap">

    <div class="studio-hero">
        <div class="studio-kicker">Imagination Nature Studio · Kigali</div>
        <h1>Studio Photo Shoot</h1>
        <p>Book your photoshoot in a few simple steps. Choose your session, date and time, then confirm your details. The final price is calculated after you select your photos.</p>
    </div>

    <?php if ($errors): ?>
        <div class="studio-error">
            <?php foreach ($errors as $error): ?>
                <div><?= e((string)$error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!$studioService): ?>

        <div class="studio-card">
            <div class="studio-body">
                <div class="studio-empty">
                    Studio Photo Shoot service is not currently available. Please add an active Studio Photo Shoot service in the admin panel first.
                </div>
            </div>
        </div>

    <?php else: ?>

    <form method="post"
          action="<?= e(SITE_URL) ?>/booking/process.php"
          id="studioBookingForm"
          class="studio-card">

        <?= csrf_field() ?>

        <input type="hidden" name="service_id" value="<?= (int)$studioService['id'] ?>">
        <input type="hidden" name="package_id" value="">
        <input type="hidden" name="event_type" id="studio_event_type" value="">
        <input type="hidden" name="number_of_people" value="1">
        <input type="hidden" name="location" value="Kigali">
        <input type="hidden" name="event_location" value="Imagination Nature Studio, Kigali">
        <input type="hidden" name="company" value="">
        <input type="hidden" name="booking_ui" value="studio">
        <!-- Studio booking fee: fixed RWF 10,000, credited toward final photo invoice -->
        <input type="hidden" name="amount_paid" value="10000">
        <input type="hidden" name="payment_method" id="studio_payment_method" value="cash">

        <div class="studio-steps">
            <div class="studio-step active" id="studioStep1"><b>1</b>Session</div>
            <div class="studio-step" id="studioStep2"><b>2</b>Date & Time</div>
            <div class="studio-step" id="studioStep3"><b>3</b>Details</div>
        </div>

        <div class="studio-body">

            <!-- STEP 1 -->
            <section class="studio-screen active" id="screen1">
                <h2 class="studio-title">What kind of photoshoot?</h2>
                <p class="studio-sub">Choose the session that best matches what you need.</p>

                <div class="studio-grid">
                    <?php
                    $sessionTypes = [
                        'Individual' => 'Portrait session for one person.',
                        'Couple' => 'Beautiful studio session for two.',
                        'Family' => 'Family portraits and memories.',
                        'Birthday' => 'Birthday portraits and celebration.',
                        'Fashion' => 'Creative fashion and editorial portraits.',
                        'Maternity' => 'Elegant maternity studio session.',
                        'Professional / Corporate' => 'Professional headshots and business portraits.'
                    ];
                    foreach ($sessionTypes as $type => $description):
                    ?>
                        <div class="studio-choice session-choice"
                             data-value="<?= e($type) ?>">
                            <strong><?= e($type) ?></strong>
                            <span><?= e($description) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="studio-actions">
                    <span></span>
                    <button type="button" class="studio-btn next" id="to2">Continue →</button>
                </div>
            </section>

            <!-- STEP 2 -->
            <section class="studio-screen" id="screen2">
                <h2 class="studio-title">Choose date & time</h2>
                <p class="studio-sub">Select the date, start time and end time for your photoshoot.</p>

                <div class="studio-two">
                    <div class="studio-field">
                        <label for="studio_date">Date *</label>
                        <input type="date"
                               name="booking_date"
                               id="studio_date"
                               min="<?= date('Y-m-d') ?>"
                               value="<?= e(studio_old('booking_date')) ?>"
                               required>
                    </div>

                    <div class="studio-field">
                        <label for="studio_start">Start time *</label>
                        <select name="start_time" id="studio_start" required>
                            <option value="">Choose start time</option>
                            <?php
                            $times = ['09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00'];
                            foreach ($times as $time):
                            ?>
                                <option value="<?= $time ?>"><?= $time ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="studio-field">
                    <label for="studio_end">End time *</label>
                    <select name="end_time" id="studio_end" required>
                        <option value="">Choose end time</option>
                        <?php foreach (['09:30','10:00','10:30','11:00','11:30','12:00','12:30','13:00','13:30','14:00','14:30','15:00','15:30','16:00','16:30','17:00','17:30','18:00','18:30','19:00'] as $time): ?>
                            <option value="<?= $time ?>"><?= $time ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="studio-notice">
                    A fixed <strong>RWF 10,000 booking fee</strong> is paid when you book. It is credited toward your final photo invoice. No photo invoice is generated yet.
                </div>

                <div class="studio-actions">
                    <button type="button" class="studio-btn back" data-back="1">← Back</button>
                    <button type="button" class="studio-btn next" id="to3">Continue →</button>
                </div>
            </section>

            <!-- STEP 3 -->
            <section class="studio-screen" id="screen3">
                <h2 class="studio-title">Your details</h2>
                <p class="studio-sub">We only need the information required to contact you and confirm the booking.</p>

                <div class="studio-two">
                    <div class="studio-field">
                        <label for="studio_name">Full name *</label>
                        <input type="text" name="full_name" id="studio_name"
                               value="<?= e(studio_old('full_name')) ?>"
                               autocomplete="name" required>
                    </div>

                    <div class="studio-field">
                        <label for="studio_phone">WhatsApp / Phone *</label>
                        <input type="tel" name="phone" id="studio_phone"
                               value="<?= e(studio_old('phone')) ?>"
                               autocomplete="tel" required>
                    </div>
                </div>

                <div class="studio-field">
                    <label for="studio_whatsapp">WhatsApp (if different)</label>
                    <input type="tel" name="whatsapp" id="studio_whatsapp"
                           value="<?= e(studio_old('whatsapp')) ?>"
                           placeholder="Optional">
                </div>

                <div class="studio-field">
                    <label for="studio_email">Email *</label>
                    <input type="email" name="email" id="studio_email"
                           value="<?= e(studio_old('email')) ?>"
                           autocomplete="email" required>
                </div>

                <div class="studio-field">
                    <label for="studio_notes">Anything we should know?</label>
                    <textarea name="customer_notes" id="studio_notes"
                              placeholder="Optional — e.g. preferred background, outfit, special request..."><?= e(studio_old('customer_notes')) ?></textarea>
                </div>

                <div class="studio-notice" style="margin-top:1rem">
                    <strong>Booking fee: RWF 10,000</strong><br>
                    This fee confirms your studio time and will be deducted from your final photo invoice after photo selection.
                </div>

                <div class="studio-field">
                    <label for="studio_payment_choice">Booking fee payment method *</label>
                    <select id="studio_payment_choice" required>
                        <option value="">Choose payment method</option>
                        <option value="cash">Cash</option>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="studio-summary">
                    <div class="studio-summary-row"><span>Session</span><strong id="sumSession">—</strong></div>
                    <div class="studio-summary-row"><span>Date</span><strong id="sumDate">—</strong></div>
                    <div class="studio-summary-row"><span>Time</span><strong id="sumTime">—</strong></div>
                    <div class="studio-summary-row"><span>Photo price</span><strong>3,000 RWF / selected photo</strong></div>
                    <div class="studio-summary-row"><span>Booking fee</span><strong>RWF 10,000</strong></div>
                    <div class="studio-summary-row studio-summary-total"><span>Final invoice</span><strong id="sumTotal">Calculated after photo selection</strong></div>
                </div>

                <div class="studio-notice" style="margin-top:1rem">
                    You are booking the studio time now. The RWF 10,000 booking fee is recorded as paid and credited toward the final invoice. After your session, select the photos you want and the final invoice will be generated automatically.
                </div>

                <div class="studio-actions">
                    <button type="button" class="studio-btn back" data-back="2">← Back</button>
                    <button type="submit" class="studio-btn confirm" id="studioConfirm">Book Studio Session</button>
                </div>
            </section>

        </div>
    </form>
    <?php endif; ?>

</div>
</main>

<script>
(function(){
    'use strict';

    const form = document.getElementById('studioBookingForm');
    if (!form) return;

    const serviceId = <?= (int)$studioService['id'] ?>;
    const siteUrl = <?= json_encode(rtrim(SITE_URL, '/')) ?>;

    let currentStep = 1;
    let sessionType = '';

    const oldPayment = <?= json_encode(studio_old('payment_method')) ?>;
    if (oldPayment) {
        document.getElementById('studio_payment_choice').value = oldPayment;
        document.getElementById('studio_payment_method').value = oldPayment;
    }

    const screens = [1,2,3].map(n => document.getElementById('screen'+n));
    const steps = [1,2,3].map(n => document.getElementById('studioStep'+n));

    function showStep(n){
        currentStep = n;
        screens.forEach((el,i) => el.classList.toggle('active', i === n-1));
        steps.forEach((el,i) => el.classList.toggle('active', i <= n-1));
        window.scrollTo({top:0, behavior:'smooth'});
    }

    function updateSummary(){
        document.getElementById('sumSession').textContent = sessionType || '—';
        document.getElementById('sumDate').textContent = document.getElementById('studio_date').value || '—';
        const start = document.getElementById('studio_start').value;
        const end = document.getElementById('studio_end').value;
        document.getElementById('sumTime').textContent = start && end ? start + ' – ' + end : '—';
    }

    document.querySelectorAll('.session-choice').forEach(card => {
        card.addEventListener('click', function(){
            document.querySelectorAll('.session-choice').forEach(x => x.classList.remove('selected'));
            this.classList.add('selected');
            sessionType = this.dataset.value;
            document.getElementById('studio_event_type').value = sessionType;
        });
    });

    document.getElementById('to2').addEventListener('click', function(){
        if (!sessionType) {
            alert('Please choose your photoshoot type.');
            return;
        }
        showStep(2);
    });

    document.getElementById('to3').addEventListener('click', function(){
        const date = document.getElementById('studio_date').value;
        const start = document.getElementById('studio_start').value;
        const end = document.getElementById('studio_end').value;

        if (!date || !start || !end) {
            alert('Please choose a date, start time and end time.');
            return;
        }

        if (start >= end) {
            alert('End time must be after start time.');
            return;
        }

        updateSummary();
        showStep(3);
    });

    document.querySelectorAll('[data-back]').forEach(btn => {
        btn.addEventListener('click', () => showStep(Number(btn.dataset.back)));
    });

    ['studio_date','studio_start','studio_end'].forEach(id => {
        document.getElementById(id).addEventListener('change', updateSummary);
    });

    form.addEventListener('submit', function(e){
        const date = document.getElementById('studio_date').value;
        const start = document.getElementById('studio_start').value;
        const end = document.getElementById('studio_end').value;

        const paymentChoice = document.getElementById('studio_payment_choice').value;

        if (!sessionType || !date || !start || !end || start >= end) {
            e.preventDefault();
            alert('Please complete the session, date and time.');
            return;
        }

        if (!paymentChoice) {
            e.preventDefault();
            alert('Please choose the payment method for the RWF 10,000 booking fee.');
            return;
        }

        document.getElementById('studio_payment_method').value = paymentChoice;
        document.getElementById('studioConfirm').disabled = true;
        document.getElementById('studioConfirm').textContent = 'Booking…';
    });

    function escapeHtml(value){
        return String(value)
            .replaceAll('&','&amp;')
            .replaceAll('<','&lt;')
            .replaceAll('>','&gt;')
            .replaceAll('"','&quot;')
            .replaceAll("'","&#039;");
    }

    updateSummary();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
