<?php
/**
 * IMAGINATION NATURE STUDIO
 * SIMPLE BOOKING PAGE
 *
 * Keeps the existing booking/process.php contract while giving
 * clients a much shorter, mobile-first booking experience.
 */
require_once __DIR__ . '/includes/functions.php';

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
</style>

<main class="simple-booking-page">
<div class="simple-booking-wrap">

    <div class="simple-hero">
        <h1>Book Your Session</h1>
        <p>Choose your service, pick a date and time, then leave us your WhatsApp details.</p>
    </div>

    <?php if ($errors): ?>
        <div class="simple-error">
            <?php foreach ($errors as $error): ?>
                <div><?= e((string)$error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(SITE_URL) ?>/booking/process.php" id="simpleBookingForm" class="simple-card" novalidate>
        <?= csrf_field() ?>

        <div class="simple-field">
            <label for="service_id">Service *</label>
            <select name="service_id" id="service_id" required>
                <option value="">Choose a service</option>
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
        </div>

        <div id="weddingOnly" class="simple-hidden">
            <div class="simple-field">
                <label for="package_id">Wedding package</label>
                <select name="package_id" id="package_id">
                    <option value="">Choose package (optional)</option>
                </select>
            </div>
        </div>

        <div class="simple-grid">
            <div class="simple-field">
                <label for="booking_date">Date *</label>
                <input type="date" name="booking_date" id="booking_date" min="<?= date('Y-m-d') ?>" value="<?= e(simple_old('booking_date')) ?>" required>
            </div>

            <div class="simple-field">
                <label>Time *</label>
                <div id="slots" class="simple-slots"><span class="simple-note">Choose a date first.</span></div>
                <input type="hidden" name="start_time" id="start_time" value="<?= e(simple_old('start_time')) ?>">
                <input type="hidden" name="end_time" id="end_time" value="<?= e(simple_old('end_time')) ?>">
            </div>
        </div>

        <div id="weddingDates" class="simple-wedding simple-hidden">
            <h2 class="simple-wedding-title">Wedding dates</h2>
            <div class="simple-note">Add another day only if your wedding package has more than one event day.</div>
            <div id="weddingList"></div>
            <button type="button" id="addWedding" class="simple-small-btn">+ Add another date</button>
        </div>

        <div class="simple-grid" style="margin-top:1rem">
            <div class="simple-field">
                <label for="full_name">Your name *</label>
                <input type="text" name="full_name" id="full_name" value="<?= e(simple_old('full_name')) ?>" autocomplete="name" required>
            </div>

            <div class="simple-field">
                <label for="phone">WhatsApp / Phone *</label>
                <input type="tel" name="phone" id="phone" value="<?= e(simple_old('phone')) ?>" autocomplete="tel" required placeholder="07XXXXXXXX">
            </div>

            <div class="simple-field simple-full">
                <label for="email">Email *</label>
                <input type="email" name="email" id="email" value="<?= e(simple_old('email')) ?>" autocomplete="email" required placeholder="yourname@example.com">
                <div class="simple-note">We will send your booking confirmation and invoice to this email.</div>
            </div>

            <div class="simple-field simple-full">
                <label for="customer_notes">Message <span style="font-weight:400;color:#817a73">(optional)</span></label>
                <textarea name="customer_notes" id="customer_notes" placeholder="Anything you want us to know?"><?= e(simple_old('customer_notes')) ?></textarea>
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

        <button type="submit" class="simple-submit" id="submitBooking">BOOK NOW</button>
        <div class="simple-bottom">Your request will be reviewed and confirmed by Imagination Nature Studio.</div>
    </form>
</div>
</main>

<script>
(function () {
    'use strict';

    var SITE_URL = <?= json_encode(rtrim(SITE_URL, '/')) ?>;
    var services = <?= json_encode($services, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var oldPackage = <?= json_encode(simple_old('package_id')) ?>;

    var service = document.getElementById('service_id');
    var date = document.getElementById('booking_date');
    var slots = document.getElementById('slots');
    var start = document.getElementById('start_time');
    var end = document.getElementById('end_time');
    var note = document.getElementById('serviceNote');
    var weddingOnly = document.getElementById('weddingOnly');
    var weddingDates = document.getElementById('weddingDates');
    var packageSelect = document.getElementById('package_id');
    var weddingList = document.getElementById('weddingList');
    var addWedding = document.getElementById('addWedding');
    var form = document.getElementById('simpleBookingForm');
    var phone = document.getElementById('phone');
    var whatsapp = document.getElementById('whatsapp');
    var eventType = document.getElementById('event_type');

    if (!service || !date || !slots || !start || !end || !form) {
        return;
    }

    function selectedService() {
        var id = parseInt(service.value, 10);

        if (!id) {
            return null;
        }

        for (var i = 0; i < services.length; i++) {
            if (parseInt(services[i].id, 10) === id) {
                return services[i];
            }
        }

        return null;
    }

    function isWedding() {
        var s = selectedService();
        var name = s ? String(s.name || '').toLowerCase() : '';
        return name.indexOf('wedding') !== -1;
    }

    function rwf(value) {
        return 'RWF ' + Number(value || 0).toLocaleString('en-US');
    }

    function loadPackages() {
        if (!packageSelect) {
            return;
        }

        if (!isWedding()) {
            packageSelect.innerHTML =
                '<option value="">Choose package (optional)</option>';
            packageSelect.disabled = false;
            return;
        }

        packageSelect.innerHTML =
            '<option value="">Loading packages...</option>';
        packageSelect.disabled = true;

        fetch(
            SITE_URL +
            '/booking/get-packages.php?service_id=' +
            encodeURIComponent(service.value)
        )
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Package request failed');
            }
            return response.json();
        })
        .then(function (data) {
            packageSelect.innerHTML =
                '<option value="">Choose package (optional)</option>';

            (data.packages || []).forEach(function (pkg) {
                var option = document.createElement('option');

                option.value = pkg.id;
                option.textContent =
                    pkg.name + ' · ' + rwf(pkg.price);

                option.setAttribute('data-price', pkg.price);
                option.setAttribute('data-name', pkg.name);

                packageSelect.appendChild(option);
            });

            if (oldPackage) {
                packageSelect.value = String(oldPackage);
            }

            packageSelect.disabled = false;
        })
        .catch(function () {
            packageSelect.innerHTML =
                '<option value="">Choose package (optional)</option>';
            packageSelect.disabled = false;
        });
    }

    function updateMode() {
        var s = selectedService();

        if (note) {
            note.textContent =
                s && s.description
                    ? s.description
                    : '';
        }

        var wedding = isWedding();

        if (weddingOnly) {
            weddingOnly.classList.toggle(
                'simple-hidden',
                !wedding
            );
        }

        if (weddingDates) {
            weddingDates.classList.toggle(
                'simple-hidden',
                !wedding
            );
        }

        loadPackages();

        if (
            wedding &&
            weddingList &&
            !weddingList.children.length
        ) {
            addWeddingRow();
        }
    }

    /*
     * Normal studio slots:
     * 00:00 → 01:00
     * 01:00 → 02:00
     * ...
     * 22:00 → 23:00
     *
     * 23:00 is intentionally not shown because the current
     * process.php does not safely accept 23:00 → 00:00.
     */
    function buildSlots() {
        var result = [];

        for (var hour = 0; hour <= 22; hour++) {
            result.push(
                String(hour).padStart(2, '0') + ':00'
            );
        }

        return result;
    }

    function renderSlots(unavailable) {
        slots.innerHTML = '';

        var blocked = {};

        (unavailable || []).forEach(function (item) {
            if (typeof item === 'string') {
                blocked[item] = true;
            } else if (item && item.time) {
                blocked[item.time] = true;
            } else if (item && item.start_time) {
                blocked[item.start_time] = true;
            }
        });

        buildSlots().forEach(function (time) {
            var button =
                document.createElement('button');

            button.type = 'button';
            button.className = 'simple-slot';
            button.textContent = time;

            if (blocked[time]) {
                button.classList.add('disabled');
                button.disabled = true;
                button.title = 'This time is already booked';
            } else {
                button.addEventListener(
                    'click',
                    function () {
                        document
                            .querySelectorAll('.simple-slot')
                            .forEach(function (item) {
                                item.classList.remove(
                                    'selected'
                                );
                            });

                        button.classList.add('selected');

                        start.value = time;

                        var hour =
                            parseInt(
                                time.split(':')[0],
                                10
                            );

                        end.value =
                            String(hour + 1)
                                .padStart(2, '0') +
                            ':00';
                    }
                );
            }

            slots.appendChild(button);
        });
    }

    function loadSlots() {
        if (!date.value) {
            slots.innerHTML =
                '<span class="simple-note">' +
                'Choose a date first.' +
                '</span>';

            start.value = '';
            end.value = '';

            return;
        }

        slots.innerHTML =
            '<span class="simple-note">' +
            'Checking available times...' +
            '</span>';

        start.value = '';
        end.value = '';

        fetch(
            SITE_URL +
            '/booking/check-availability.php?date=' +
            encodeURIComponent(date.value)
        )
        .then(function (response) {
            if (!response.ok) {
                throw new Error(
                    'Availability request failed'
                );
            }

            return response.json();
        })
        .then(function (data) {
            var unavailable = [];

            if (Array.isArray(data.slots)) {
                data.slots.forEach(function (slot) {
                    if (
                        slot &&
                        slot.available === false
                    ) {
                        unavailable.push(
                            slot.time ||
                            slot.start_time ||
                            ''
                        );
                    }
                });
            }

            renderSlots(unavailable);
        })
        .catch(function () {
            /*
             * Do not leave the client with a broken page.
             * If the availability endpoint fails, show the
             * normal slot grid so the form remains usable.
             */
            renderSlots([]);
        });
    }

    function addWeddingRow() {
        if (!weddingList) {
            return;
        }

        var index =
            weddingList.children.length;

        var row =
            document.createElement('div');

        row.className =
            'simple-wedding-row';

        row.innerHTML =
            '<div class="simple-wedding-head">' +
                '<strong>Wedding Day ' +
                    (index + 1) +
                '</strong>' +

                (
                    index > 0
                        ? '<button type="button" ' +
                          'class="simple-small-btn remove-day">' +
                          'Remove</button>'
                        : ''
                ) +
            '</div>' +

            '<div class="simple-grid">' +

                '<div class="simple-field">' +
                    '<label>Date *</label>' +
                    '<input type="date" ' +
                        'class="w-date" ' +
                        'min="<?= date('Y-m-d') ?>" ' +
                        'name="wedding_dates[' +
                            index +
                        '][date]" required>' +
                '</div>' +

                '<div class="simple-field">' +
                    '<label>Event / Day Type *</label>' +
                    '<input type="text" ' +
                        'class="w-type" ' +
                        'name="wedding_dates[' +
                            index +
                        '][event_type]" ' +
                        'placeholder="Civil, Traditional, Reception" ' +
                        'required>' +
                '</div>' +

                '<div class="simple-field">' +
                    '<label>Start *</label>' +
                    '<input type="time" ' +
                        'class="w-start" ' +
                        'name="wedding_dates[' +
                            index +
                        '][start_time]" required>' +
                '</div>' +

                '<div class="simple-field">' +
                    '<label>End *</label>' +
                    '<input type="time" ' +
                        'class="w-end" ' +
                        'name="wedding_dates[' +
                            index +
                        '][end_time]" required>' +
                '</div>' +

            '</div>';

        weddingList.appendChild(row);

        row.querySelectorAll('input').forEach(
            function (input) {
                input.addEventListener(
                    'change',
                    syncWedding
                );
            }
        );

        var remove =
            row.querySelector('.remove-day');

        if (remove) {
            remove.addEventListener(
                'click',
                function () {
                    row.remove();
                    renumberWedding();
                }
            );
        }
    }

    function renumberWedding() {
        if (!weddingList) {
            return;
        }

        Array.from(
            weddingList.children
        ).forEach(function (row, index) {

            var title =
                row.querySelector(
                    '.simple-wedding-head strong'
                );

            if (title) {
                title.textContent =
                    'Wedding Day ' +
                    (index + 1);
            }

            row.querySelectorAll(
                '[name]'
            ).forEach(function (input) {

                input.name =
                    input.name.replace(
                        /wedding_dates\[\d+\]/,
                        'wedding_dates[' +
                        index +
                        ']'
                    );
            });
        });

        syncWedding();
    }

    function syncWedding() {
        if (
            !isWedding() ||
            !weddingList
        ) {
            return;
        }

        var first =
            weddingList.querySelector(
                '.simple-wedding-row'
            );

        if (!first) {
            return;
        }

        var d =
            first.querySelector('.w-date');

        var s =
            first.querySelector('.w-start');

        var e =
            first.querySelector('.w-end');

        var t =
            first.querySelector('.w-type');

        if (d && d.value) {
            date.value = d.value;
        }

        if (s && s.value) {
            start.value = s.value;
        }

        if (e && e.value) {
            end.value = e.value;
        }

        if (eventType && t) {
            eventType.value = t.value;
        }
    }

    service.addEventListener(
        'change',
        updateMode
    );

    date.addEventListener(
        'change',
        function () {
            if (!isWedding()) {
                loadSlots();
            }
        }
    );

    if (addWedding) {
        addWedding.addEventListener(
            'click',
            addWeddingRow
        );
    }

    form.addEventListener(
        'submit',
        function (event) {

            if (phone && whatsapp) {
                whatsapp.value =
                    phone.value.trim();
            }

            var email = document.getElementById('email');
            if (email && !email.checkValidity()) {
                event.preventDefault();
                email.reportValidity();
                return false;
            }

            if (isWedding()) {
                syncWedding();
            } else if (start.value) {

                var hour =
                    parseInt(
                        start.value.split(':')[0],
                        10
                    );

                if (
                    hour >= 0 &&
                    hour <= 22
                ) {
                    end.value =
                        String(hour + 1)
                            .padStart(2, '0') +
                        ':00';
                }
            }

            if (
                !isWedding() &&
                (
                    !start.value ||
                    !end.value
                )
            ) {
                event.preventDefault();

                alert(
                    'Please choose an available time.'
                );

                return false;
            }
        }
    );

    updateMode();

    if (
        date.value &&
        !isWedding()
    ) {
        loadSlots();
    }

})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
