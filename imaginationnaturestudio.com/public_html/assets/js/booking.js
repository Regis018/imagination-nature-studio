/* =========================================================
   Imagination Nature Studio — Booking Form
   ---------------------------------------------------------
   NORMAL SERVICES
   - One date
   - One start time
   - One end time

   WEDDING PHOTOGRAPHY
   - Multiple dates
   - Event / Day Type
   - Start time
   - End time
   - Add / Remove dates

   OTHER
   - Packages
   - Availability
   - 30% deposit
   - Live pricing
   - Form validation
   ========================================================= */

(function () {

    'use strict';


    /* =====================================================
       CONFIG
       ===================================================== */

    var cfg = window.INS || {};

    if (
        !cfg.services ||
        !Array.isArray(cfg.services)
    ) {

        console.error(
            'Imagination Nature Studio: services configuration missing.'
        );

        return;
    }


    /* =====================================================
       MAIN ELEMENTS
       ===================================================== */

    var serviceSel =
        document.getElementById('service_id');

    var packageSel =
        document.getElementById('package_id');

    var dateInput =
        document.getElementById('booking_date');

    var slotsBox =
        document.getElementById('slots');

    var startInput =
        document.getElementById('start_time');

    var endInput =
        document.getElementById('end_time');

    var amountPaid =
        document.getElementById('amount_paid');

    var bookingForm =
        document.getElementById('bookingForm');


    /* =====================================================
       SUMMARY
       ===================================================== */

    var sumService =
        document.getElementById('sumService');

    var sumPackage =
        document.getElementById('sumPackage');

    var sumTotal =
        document.getElementById('sumTotal');

    var sumDeposit =
        document.getElementById('sumDeposit');

    var sumBalance =
        document.getElementById('sumBalance');


    /* =====================================================
       NORMAL DATE SECTION
       ===================================================== */

    var normalDateTime =
        document.getElementById('normalDateTime');


    /* =====================================================
       WEDDING SECTION
       ===================================================== */

    var weddingSection =
        document.getElementById('weddingDatesSection');

    var weddingList =
        document.getElementById('weddingDatesList');

    var addWeddingDate =
        document.getElementById('addWeddingDate');

    var weddingBookingDate =
        document.getElementById('wedding_booking_date');

    var weddingStartTime =
        document.getElementById('wedding_start_time');

    var weddingEndTime =
        document.getElementById('wedding_end_time');

    var normalPeople =
        document.getElementById(
            'number_of_people_normal'
        );

    var weddingPeople =
        document.getElementById(
            'number_of_people_wedding'
        );


    /* =====================================================
       MONEY FORMAT
       ===================================================== */

    function rwf(value) {

        return 'RWF ' +
            Number(value || 0)
                .toLocaleString('en-US');

    }


    /* =====================================================
       CURRENT SERVICE
       ===================================================== */

    function currentService() {

        if (!serviceSel) {
            return null;
        }


        var id =
            parseInt(
                serviceSel.value,
                10
            );


        if (!id) {
            return null;
        }


        for (
            var i = 0;
            i < cfg.services.length;
            i++
        ) {

            if (
                parseInt(
                    cfg.services[i].id,
                    10
                ) === id
            ) {

                return cfg.services[i];

            }

        }


        return null;

    }


    /* =====================================================
       CHECK WEDDING SERVICE
       ===================================================== */

    function isWeddingService() {

        var service =
            currentService();


        if (!service) {
            return false;
        }


        var name =
            String(
                service.name || ''
            )
            .trim()
            .toLowerCase();


        return (
            name === 'wedding photography' ||
            name.indexOf(
                'wedding photography'
            ) !== -1
        );

    }


    /* =====================================================
       CURRENT PACKAGE
       ===================================================== */

    function currentPackage() {

        if (
            !packageSel ||
            !packageSel.value
        ) {

            return null;

        }


        var option =
            packageSel.options[
                packageSel.selectedIndex
            ];


        if (!option) {
            return null;
        }


        return {

            price:
                option.getAttribute(
                    'data-price'
                ),

            name:
                option.getAttribute(
                    'data-name'
                )

        };

    }


    /* =====================================================
       PRICING
       ===================================================== */

    function getPricing() {

        var service =
            currentService();

        var pkg =
            currentPackage();


        var total =
            parseFloat(
                (pkg && pkg.price) ||
                (service && service.price) ||
                0
            );


        var deposit =
            Math.round(
                total * 0.30
            );


        return {

            total: total,

            deposit: deposit,

            balance:
                Math.max(
                    0,
                    total - deposit
                )

        };

    }


    /* =====================================================
       UPDATE PRICE SUMMARY
       ===================================================== */

    function recalc() {

        var service =
            currentService();

        var pkg =
            currentPackage();

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
                rwf(
                    pricing.total
                );

        }


        if (sumDeposit) {

            sumDeposit.textContent =
                rwf(
                    pricing.deposit
                );

        }


        if (sumBalance) {

            sumBalance.textContent =
                rwf(
                    pricing.balance
                );

        }


        if (
            amountPaid &&
            pricing.total > 0
        ) {

            var paid =
                parseFloat(
                    amountPaid.value || 0
                );


            if (
                !paid ||
                paid < pricing.deposit
            ) {

                amountPaid.value =
                    pricing.deposit;

            }


            amountPaid.min =
                pricing.deposit;

            amountPaid.max =
                pricing.total;

        }

    }


    /* =====================================================
       LOAD PACKAGES
       ===================================================== */

    function loadPackages(
        serviceId
    ) {

        if (!packageSel) {
            return;
        }


        packageSel.innerHTML =
            '<option value="">Loading…</option>';

        packageSel.disabled =
            true;


        if (!serviceId) {

            packageSel.innerHTML =
                '<option value="">— Select a service first —</option>';

            if (amountPaid) {
                amountPaid.value = 0;
            }

            recalc();

            applyBookingMode();

            return;

        }


        fetch(
            cfg.url +
            '/booking/get-packages.php?service_id=' +
            encodeURIComponent(
                serviceId
            )
        )

        .then(function (response) {

            if (!response.ok) {

                throw new Error(
                    'Could not load packages.'
                );

            }

            return response.json();

        })

        .then(function (data) {

            packageSel.innerHTML =
                '<option value="">— No package / custom —</option>';


            (
                data.packages || []
            ).forEach(
                function (pkg) {

                    var option =
                        document.createElement(
                            'option'
                        );


                    option.value =
                        pkg.id;


                    option.textContent =
                        pkg.name +
                        ' · ' +
                        rwf(
                            pkg.price
                        );


                    option.setAttribute(
                        'data-price',
                        pkg.price
                    );


                    option.setAttribute(
                        'data-name',
                        pkg.name
                    );


                    packageSel.appendChild(
                        option
                    );

                }
            );


            packageSel.disabled =
                false;


            if (cfg.oldPkg) {

                packageSel.value =
                    cfg.oldPkg;

            }


            recalc();

            applyBookingMode();

        })

        .catch(function (error) {

            console.error(
                'Package loading error:',
                error
            );


            packageSel.innerHTML =
                '<option value="">Error loading packages</option>';


            packageSel.disabled =
                false;

        });

    }


    /* =====================================================
       LOAD NORMAL SERVICE SLOTS
       ===================================================== */

    function loadSlots(
        date
    ) {

        if (
            !slotsBox ||
            !startInput ||
            !endInput
        ) {

            return;

        }


        slotsBox.innerHTML =
            '<em style="color:#7a716a;font-size:.9rem">Loading…</em>';


        startInput.value = '';
        endInput.value = '';


        if (!date) {

            slotsBox.innerHTML =
                '<em style="color:#7a716a">Pick a date.</em>';

            return;

        }


        fetch(
            cfg.url +
            '/booking/check-availability.php?date=' +
            encodeURIComponent(
                date
            )
        )

        .then(function (response) {

            if (!response.ok) {

                throw new Error(
                    'Could not load availability.'
                );

            }

            return response.json();

        })

        .then(function (data) {

            if (
                !data.slots ||
                !data.slots.length
            ) {

                slotsBox.innerHTML =
                    '<em style="color:#7a716a">No slots available.</em>';

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
                        'slot' +
                        (
                            slot.available
                                ? ''
                                : ' disabled'
                        );


                    button.textContent =
                        slot.time;


                    if (
                        slot.available
                    ) {

                        button.addEventListener(
                            'click',
                            function () {

                                var buttons =
                                    slotsBox.querySelectorAll(
                                        '.slot'
                                    );


                                for (
                                    var i = 0;
                                    i < buttons.length;
                                    i++
                                ) {

                                    buttons[i]
                                        .classList
                                        .remove(
                                            'selected'
                                        );

                                }


                                button.classList.add(
                                    'selected'
                                );


                                startInput.value =
                                    slot.time;


                                var parts =
                                    slot.time.split(':');


                                var hour =
                                    parseInt(
                                        parts[0],
                                        10
                                    ) + 2;


                                var minute =
                                    parts[1];


                                if (hour > 23) {
                                    hour = 23;
                                }


                                endInput.value =
                                    String(hour)
                                        .padStart(
                                            2,
                                            '0'
                                        ) +
                                    ':' +
                                    minute;

                            }
                        );

                    } else {

                        button.disabled =
                            true;

                    }


                    slotsBox.appendChild(
                        button
                    );

                }
            );

        })

        .catch(function (error) {

            console.error(
                'Availability error:',
                error
            );


            slotsBox.innerHTML =
                '<em style="color:#c88">Could not load availability.</em>';

        });

    }


    /* =====================================================
       SYNC FIRST WEDDING DAY
       ===================================================== */

    function syncFirstWeddingDate() {

        if (!weddingList) {
            return;
        }


        var first =
            weddingList.querySelector(
                '.wedding-date-row'
            );


        if (!first) {
            return;
        }


        var date =
            first.querySelector(
                '.wedding-date'
            );

        var start =
            first.querySelector(
                '.wedding-start-time'
            );

        var end =
            first.querySelector(
                '.wedding-end-time'
            );


        if (
            weddingBookingDate &&
            date
        ) {

            weddingBookingDate.value =
                date.value;

        }


        if (
            weddingStartTime &&
            start
        ) {

            weddingStartTime.value =
                start.value;

        }


        if (
            weddingEndTime &&
            end
        ) {

            weddingEndTime.value =
                end.value;

        }

    }


    /* =====================================================
       ENABLE / DISABLE WEDDING ROWS
       ===================================================== */

    function disableWeddingRows(
        disabled
    ) {

        if (!weddingList) {
            return;
        }


        var fields =
            weddingList.querySelectorAll(
                'input'
            );


        fields.forEach(
            function (field) {

                field.disabled =
                    disabled;

            }
        );

    }


    /* =====================================================
       APPLY NORMAL / WEDDING MODE
       ===================================================== */

    function applyBookingMode() {

        var wedding =
            isWeddingService();


        console.log(
            'INS Booking Mode:',
            wedding
                ? 'WEDDING'
                : 'NORMAL'
        );


        /* ---------------------------------------------
           SHOW / HIDE SECTIONS
           --------------------------------------------- */

        if (normalDateTime) {

            normalDateTime.style.display =
                wedding
                    ? 'none'
                    : '';

        }


        if (weddingSection) {

            weddingSection.style.display =
                wedding
                    ? 'block'
                    : 'none';

        }


        /* ---------------------------------------------
           NORMAL FIELDS
           --------------------------------------------- */

        if (dateInput) {

            dateInput.disabled =
                wedding;

        }


        if (startInput) {

            startInput.disabled =
                wedding;

        }


        if (endInput) {

            endInput.disabled =
                wedding;

        }


        if (normalPeople) {

            normalPeople.disabled =
                wedding;

        }


        /* ---------------------------------------------
           WEDDING COMPATIBILITY FIELDS
           --------------------------------------------- */

        if (weddingBookingDate) {

            weddingBookingDate.disabled =
                !wedding;

        }


        if (weddingStartTime) {

            weddingStartTime.disabled =
                !wedding;

        }


        if (weddingEndTime) {

            weddingEndTime.disabled =
                !wedding;

        }


        if (weddingPeople) {

            weddingPeople.disabled =
                !wedding;

        }


        /* ---------------------------------------------
           WEDDING ROWS
           --------------------------------------------- */

        disableWeddingRows(
            !wedding
        );


        if (wedding) {

            syncFirstWeddingDate();

        }

    }


    /* =====================================================
       ADD WEDDING DATE
       ===================================================== */

    if (
        addWeddingDate &&
        weddingList
    ) {

        addWeddingDate.addEventListener(
            'click',
            function () {

                var rows =
                    weddingList.querySelectorAll(
                        '.wedding-date-row'
                    );


                var index =
                    rows.length;


                var row =
                    document.createElement(
                        'div'
                    );


                row.className =
                    'wedding-date-row';


                row.dataset.index =
                    index;


                row.style.cssText =
                    [
                        'border:1px solid rgba(255,255,255,.12)',
                        'border-radius:12px',
                        'padding:1rem',
                        'margin-bottom:1rem'
                    ].join(';');


                row.innerHTML = `

                    <div
                        style="
                            display:flex;
                            justify-content:space-between;
                            align-items:center;
                            margin-bottom:1rem;
                            gap:1rem;
                        "
                    >

                        <strong>
                            Wedding Day ${index + 1}
                        </strong>

                        <button
                            type="button"
                            class="remove-wedding-date"
                            style="
                                border:0;
                                background:none;
                                color:#e58b7a;
                                cursor:pointer;
                            "
                        >
                            Remove
                        </button>

                    </div>


                    <div class="form-grid">

                        <div>

                            <label>
                                Date *
                            </label>

                            <input
                                type="date"
                                name="wedding_dates[${index}][date]"
                                class="wedding-date"
                                min="${new Date().toISOString().split('T')[0]}"
                            >

                        </div>


                        <div>

                            <label>
                                Event / Day Type *
                            </label>

                            <input
                                type="text"
                                name="wedding_dates[${index}][event_type]"
                                class="wedding-event-type"
                                maxlength="120"
                                placeholder="Civil Wedding, Pre-Shoot, Wedding Day..."
                            >

                        </div>


                        <div>

                            <label>
                                Start time *
                            </label>

                            <input
                                type="time"
                                name="wedding_dates[${index}][start_time]"
                                class="wedding-start-time"
                            >

                        </div>


                        <div>

                            <label>
                                End time *
                            </label>

                            <input
                                type="time"
                                name="wedding_dates[${index}][end_time]"
                                class="wedding-end-time"
                            >

                        </div>

                    </div>

                `;


                weddingList.appendChild(
                    row
                );


                applyBookingMode();

                syncFirstWeddingDate();

            }
        );

    }


    /* =====================================================
       REMOVE WEDDING DATE
       ===================================================== */

    if (weddingList) {

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


                var rows =
                    weddingList.querySelectorAll(
                        '.wedding-date-row'
                    );


                if (rows.length <= 1) {

                    alert(
                        'At least one wedding date is required.'
                    );

                    return;

                }


                var row =
                    button.closest(
                        '.wedding-date-row'
                    );


                if (row) {

                    row.remove();

                    renumberWeddingDates();

                    syncFirstWeddingDate();

                }

            }
        );

    }


    /* =====================================================
       RENUMBER WEDDING DAYS
       ===================================================== */

    function renumberWeddingDates() {

        if (!weddingList) {
            return;
        }


        var rows =
            weddingList.querySelectorAll(
                '.wedding-date-row'
            );


        rows.forEach(
            function (
                row,
                index
            ) {

                row.dataset.index =
                    index;


                var title =
                    row.querySelector(
                        'strong'
                    );


                if (title) {

                    title.textContent =
                        'Wedding Day ' +
                        (index + 1);

                }


                var fields =
                    row.querySelectorAll(
                        '[name]'
                    );


                fields.forEach(
                    function (field) {

                        field.name =
                            field.name.replace(
                                /wedding_dates\[\d+\]/,
                                'wedding_dates[' +
                                index +
                                ']'
                            );

                    }
                );

            }
        );

    }


    /* =====================================================
       WEDDING INPUT EVENTS
       ===================================================== */

    if (weddingList) {

        weddingList.addEventListener(
            'input',
            function () {

                syncFirstWeddingDate();

            }
        );


        weddingList.addEventListener(
            'change',
            function () {

                syncFirstWeddingDate();

            }
        );

    }


    /* =====================================================
       SERVICE CHANGE
       ===================================================== */

    if (serviceSel) {

        serviceSel.addEventListener(
            'change',
            function () {

                loadPackages(
                    this.value
                );

                applyBookingMode();

                recalc();

            }
        );

    }


    /* =====================================================
       PACKAGE CHANGE
       ===================================================== */

    if (packageSel) {

        packageSel.addEventListener(
            'change',
            function () {

                recalc();

            }
        );

    }


    /* =====================================================
       AMOUNT PAID
       ===================================================== */

    if (amountPaid) {

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
                        'A 30% deposit of ' +
                        rwf(
                            pricing.deposit
                        ) +
                        ' is required.'
                    );

                }

                else if (
                    value > pricing.total
                ) {

                    this.setCustomValidity(
                        'Amount paid cannot be greater than the total.'
                    );

                }

                else {

                    this.setCustomValidity('');

                }

            }
        );

    }


    /* =====================================================
       NORMAL DATE CHANGE
       ===================================================== */

    if (dateInput) {

        dateInput.addEventListener(
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


    /* =====================================================
       FORM SUBMIT
       ===================================================== */

    if (bookingForm) {

        bookingForm.addEventListener(
            'submit',
            function (event) {

                var wedding =
                    isWeddingService();


                /* =========================================
                   WEDDING VALIDATION
                   ========================================= */

                if (wedding) {

                    syncFirstWeddingDate();


                    if (!weddingList) {

                        event.preventDefault();

                        alert(
                            'Wedding dates section is missing.'
                        );

                        return;

                    }


                    var rows =
                        weddingList.querySelectorAll(
                            '.wedding-date-row'
                        );


                    if (!rows.length) {

                        event.preventDefault();

                        alert(
                            'Please add at least one wedding date.'
                        );

                        return;

                    }


                    for (
                        var i = 0;
                        i < rows.length;
                        i++
                    ) {

                        var dateField =
                            rows[i].querySelector(
                                '.wedding-date'
                            );

                        var typeField =
                            rows[i].querySelector(
                                '.wedding-event-type'
                            );

                        var startField =
                            rows[i].querySelector(
                                '.wedding-start-time'
                            );

                        var endField =
                            rows[i].querySelector(
                                '.wedding-end-time'
                            );


                        var date =
                            dateField
                                ? dateField.value
                                : '';

                        var type =
                            typeField
                                ? typeField.value.trim()
                                : '';

                        var start =
                            startField
                                ? startField.value
                                : '';

                        var end =
                            endField
                                ? endField.value
                                : '';


                        if (
                            !date ||
                            !type ||
                            !start ||
                            !end
                        ) {

                            event.preventDefault();

                            alert(
                                'Please complete all fields for Wedding Day ' +
                                (i + 1) +
                                '.'
                            );

                            return;

                        }


                        if (
                            start >= end
                        ) {

                            event.preventDefault();

                            alert(
                                'End time must be after start time for Wedding Day ' +
                                (i + 1) +
                                '.'
                            );

                            return;

                        }

                    }


                    syncFirstWeddingDate();

                }


                /* =========================================
                   NORMAL SERVICE VALIDATION
                   ========================================= */

                else {

                    var required = [

                        'service_id',
                        'booking_date',
                        'start_time',
                        'end_time',
                        'full_name',
                        'phone',
                        'email',
                        'location'

                    ];


                    for (
                        var j = 0;
                        j < required.length;
                        j++
                    ) {

                        var element =
                            document.getElementById(
                                required[j]
                            );


                        if (
                            !element ||
                            !String(
                                element.value || ''
                            ).trim()
                        ) {

                            event.preventDefault();


                            if (element) {
                                element.focus();
                            }


                            alert(
                                'Please complete all required fields.'
                            );

                            return;

                        }

                    }

                }


                /* =========================================
                   EMAIL
                   ========================================= */

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

                    return;

                }


                /* =========================================
                   DEPOSIT
                   ========================================= */

                var pricing =
                    getPricing();


                var paid =
                    parseFloat(
                        amountPaid
                            ? amountPaid.value || 0
                            : 0
                    );


                if (
                    pricing.total <= 0
                ) {

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


                    if (amountPaid) {

                        amountPaid.value =
                            pricing.deposit;

                        amountPaid.focus();

                    }


                    alert(
                        'A 30% deposit of ' +
                        rwf(
                            pricing.deposit
                        ) +
                        ' is required to submit this booking.'
                    );

                    return;

                }


                if (
                    paid > pricing.total
                ) {

                    event.preventDefault();


                    if (amountPaid) {

                        amountPaid.value =
                            pricing.total;

                        amountPaid.focus();

                    }


                    alert(
                        'The amount paid cannot be greater than ' +
                        rwf(
                            pricing.total
                        ) +
                        '.'
                    );

                    return;

                }


                if (amountPaid) {

                    amountPaid.setCustomValidity('');

                }

            }
        );

    }


    /* =====================================================
       INITIAL LOAD
       ===================================================== */

    applyBookingMode();


    if (
        serviceSel &&
        serviceSel.value
    ) {

        loadPackages(
            serviceSel.value
        );

    }


    if (
        dateInput &&
        dateInput.value &&
        !isWeddingService()
    ) {

        loadSlots(
            dateInput.value
        );

    }


    recalc();


    console.log(
        'Imagination Nature Studio booking.js loaded.'
    );


})();