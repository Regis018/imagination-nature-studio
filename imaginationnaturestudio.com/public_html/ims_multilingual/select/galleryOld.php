<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/db.php';

$pdo = db();

$token = trim($_GET['token'] ?? '');

if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
    http_response_code(404);
    exit('Invalid gallery link.');
}

/*
|--------------------------------------------------------------------------
| LOAD GALLERY
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM photo_galleries
    WHERE access_token = ?
    LIMIT 1
");

$stmt->execute([$token]);

$gallery = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$gallery) {
    http_response_code(404);
    exit('Gallery not found.');
}

$galleryId = (int)$gallery['id'];

/*
|--------------------------------------------------------------------------
| START TIMER
|--------------------------------------------------------------------------
*/

if (
    $gallery['status'] === 'draft' &&
    empty($gallery['started_at'])
) {

    $startedAt = date('Y-m-d H:i:s');

    $expiresAt = date(
        'Y-m-d H:i:s',
        time() + ((int)$gallery['duration_minutes'] * 60)
    );

    $stmt = $pdo->prepare("
        UPDATE photo_galleries
        SET
            started_at = ?,
            expires_at = ?,
            status = 'active'
        WHERE id = ?
    ");

    $stmt->execute([
        $startedAt,
        $expiresAt,
        $galleryId
    ]);

    $gallery['started_at'] = $startedAt;
    $gallery['expires_at'] = $expiresAt;
    $gallery['status'] = 'active';
}

/*
|--------------------------------------------------------------------------
| CHECK EXPIRATION
|--------------------------------------------------------------------------
*/

if (
    !empty($gallery['expires_at']) &&
    strtotime($gallery['expires_at']) <= time() &&
    $gallery['status'] === 'active'
) {

    $stmt = $pdo->prepare("
        UPDATE photo_galleries
        SET status = 'expired'
        WHERE id = ?
    ");

    $stmt->execute([$galleryId]);

    $gallery['status'] = 'expired';
}

$isExpired = in_array(
    $gallery['status'],
    ['expired', 'closed'],
    true
);

/*
|--------------------------------------------------------------------------
| LOAD PHOTOS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        photo_number,
        original_filename,
        preview_filename,
        preview_path
    FROM gallery_photos
    WHERE gallery_id = ?
    ORDER BY photo_number ASC
");

$stmt->execute([$galleryId]);

$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| LOAD SELECTED
|--------------------------------------------------------------------------
*/

$selectedIds = [];

$stmt = $pdo->prepare("
    SELECT gallery_photo_id
    FROM photo_selections
    WHERE gallery_id = ?
");

$stmt->execute([$galleryId]);

foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
    $selectedIds[(int)$id] = true;
}

$selectedCount = count($selectedIds);

$pricePerPhoto = (float)$gallery['price_per_photo'];

$total = $selectedCount * $pricePerPhoto;

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function e(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function previewUrl(string $filename, int $galleryId): string
{
    return '../uploads/photo-selection/previews/'
        . rawurlencode((string)$galleryId)
        . '/'
        . rawurlencode($filename);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"
>

<title>
    <?= e($gallery['title']) ?> |
    Imagination Nature Studio
</title>

<style>

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;

    background: #f5f5f5;
    color: #111;

    font-family:
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Arial,
        sans-serif;
}

body {
    padding-bottom: 105px;
}

/* =========================================================
   HEADER
========================================================= */

.topbar {

    position: sticky;

    top: 0;

    z-index: 100;

    background: rgba(255,255,255,.96);

    backdrop-filter: blur(12px);

    border-bottom: 1px solid #ddd;

    padding: 15px 18px;
}

.topbar-inner {

    max-width: 1200px;

    margin: auto;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;
}

.brand {

    font-weight: 800;

    font-size: 18px;
}

.gallery-title {

    font-size: 13px;

    color: #666;

    margin-top: 3px;
}

.timer {

    background: #111;

    color: #fff;

    padding: 9px 13px;

    border-radius: 10px;

    font-size: 14px;

    font-weight: 700;

    white-space: nowrap;
}

.timer.expired {

    background: #b42318;
}

/* =========================================================
   CONTAINER
========================================================= */

.container {

    max-width: 1200px;

    margin: auto;

    padding: 22px 15px;
}

.info {

    background: #fff;

    border: 1px solid #ddd;

    border-radius: 15px;

    padding: 18px;

    margin-bottom: 20px;
}

.info h1 {

    margin: 0 0 7px;

    font-size: 23px;
}

.info p {

    margin: 5px 0;

    color: #666;
}

.price {

    font-weight: 800;

    color: #111 !important;
}

/* =========================================================
   EXPIRED
========================================================= */

.expired-box {

    background: #fff0f0;

    border: 1px solid #ffcaca;

    color: #9b1c1c;

    padding: 20px;

    border-radius: 14px;

    text-align: center;

    margin-bottom: 20px;
}

/* =========================================================
   GRID
========================================================= */

.photo-grid {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0,1fr));

    gap: 12px;
}

@media (min-width: 700px) {

    .photo-grid {

        grid-template-columns:
            repeat(3, minmax(0,1fr));

        gap: 16px;
    }
}

@media (min-width: 1000px) {

    .photo-grid {

        grid-template-columns:
            repeat(4, minmax(0,1fr));
    }
}

/* =========================================================
   CARD
========================================================= */

.photo-card {

    position: relative;

    background: #fff;

    border-radius: 12px;

    overflow: hidden;

    border: 3px solid transparent;

    cursor: pointer;

    transition: .15s;
}

.photo-card:hover {

    transform: translateY(-2px);
}

.photo-card.selected {

    border-color: #111;
}

.photo-card img {

    display: block;

    width: 100%;

    aspect-ratio: 4 / 3;

    object-fit: cover;

    user-select: none;

    -webkit-user-drag: none;
}

.photo-number {

    position: absolute;

    top: 8px;

    left: 8px;

    background: rgba(0,0,0,.75);

    color: #fff;

    padding: 5px 8px;

    border-radius: 7px;

    font-size: 12px;

    font-weight: 700;

    z-index: 3;
}

.check {

    position: absolute;

    right: 8px;

    top: 8px;

    width: 32px;

    height: 32px;

    border-radius: 50%;

    background: rgba(255,255,255,.92);

    display: flex;

    justify-content: center;

    align-items: center;

    font-size: 17px;

    opacity: 0;

    z-index: 3;
}

.photo-card.selected .check {

    opacity: 1;

    background: #111;

    color: #fff;
}

.zoom-hint {

    position: absolute;

    left: 50%;

    bottom: 10px;

    transform: translateX(-50%);

    background: rgba(0,0,0,.72);

    color: #fff;

    padding: 6px 10px;

    border-radius: 20px;

    font-size: 11px;

    pointer-events: none;
}

/* =========================================================
   BOTTOM
========================================================= */

.bottom-bar {

    position: fixed;

    left: 0;

    right: 0;

    bottom: 0;

    z-index: 150;

    background: rgba(255,255,255,.97);

    backdrop-filter: blur(12px);

    border-top: 1px solid #ddd;

    padding: 12px 15px;
}

.bottom-inner {

    max-width: 1200px;

    margin: auto;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;
}

.summary strong {

    display: block;

    font-size: 18px;
}

.summary small {

    color: #666;
}

.bottom-actions {

    display: flex;

    align-items: center;

    gap: 10px;
}

.whatsapp-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    border: 0;

    background: #25D366;

    color: #fff;

    padding: 13px 18px;

    border-radius: 10px;

    font-weight: 800;

    cursor: pointer;

    text-decoration: none;

    white-space: nowrap;

    transition: .15s;
}

.whatsapp-btn:hover {

    background: #1ebe5d;

    transform: translateY(-1px);
}

.submit-btn {

    border: 0;

    background: #111;

    color: #fff;

    padding: 13px 20px;

    border-radius: 10px;

    font-weight: 800;

    cursor: pointer;
}

.submit-btn:disabled {

    opacity: .45;

    cursor: not-allowed;
}

/* =========================================================
   LIGHTBOX
========================================================= */

.lightbox {

    position: fixed;

    inset: 0;

    z-index: 1000;

    background: rgba(0,0,0,.97);

    display: none;

    overflow: hidden;
}

.lightbox.open {

    display: block;
}

.lightbox-top {

    position: absolute;

    top: 0;

    left: 0;

    right: 0;

    z-index: 20;

    height: 65px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    padding: 10px 14px;

    background:
        linear-gradient(
            rgba(0,0,0,.75),
            transparent
        );
}

.lightbox-counter {

    color: #fff;

    font-size: 14px;

    font-weight: 700;
}

.lightbox-close {

    width: 42px;

    height: 42px;

    border: 0;

    border-radius: 50%;

    background: rgba(255,255,255,.15);

    color: #fff;

    font-size: 25px;

    cursor: pointer;
}

.lightbox-stage {

    position: absolute;

    inset: 0;

    overflow: hidden;

    display: flex;

    justify-content: center;

    align-items: center;

    touch-action: none;

    cursor: grab;
}

.lightbox-stage.dragging {

    cursor: grabbing;
}

.lightbox-image {

    max-width: none;

    max-height: none;

    width: auto;

    height: auto;

    user-select: none;

    -webkit-user-drag: none;

    transform-origin: center center;

    will-change: transform;
}

/* =========================================================
   ZOOM BUTTONS
========================================================= */

.zoom-controls {

    position: absolute;

    right: 15px;

    bottom: 90px;

    z-index: 30;

    display: flex;

    flex-direction: column;

    gap: 8px;
}

.zoom-btn {

    width: 45px;

    height: 45px;

    border: 0;

    border-radius: 50%;

    background: rgba(255,255,255,.16);

    color: #fff;

    font-size: 22px;

    cursor: pointer;
}

/* =========================================================
   NEXT PREVIOUS
========================================================= */

.prev-btn,
.next-btn {

    position: absolute;

    top: 50%;

    transform: translateY(-50%);

    z-index: 30;

    width: 48px;

    height: 48px;

    border: 0;

    border-radius: 50%;

    background: rgba(255,255,255,.15);

    color: #fff;

    font-size: 30px;

    cursor: pointer;
}

.prev-btn {

    left: 15px;
}

.next-btn {

    right: 15px;
}

/* =========================================================
   SELECT BUTTON
========================================================= */

.lightbox-bottom {

    position: absolute;

    left: 0;

    right: 0;

    bottom: 0;

    z-index: 30;

    padding: 12px 15px;

    display: flex;

    justify-content: center;

    background:
        linear-gradient(
            transparent,
            rgba(0,0,0,.8)
        );
}

.lightbox-select {

    border: 0;

    border-radius: 30px;

    padding: 13px 22px;

    background: #fff;

    color: #111;

    font-weight: 800;

    cursor: pointer;
}

.lightbox-select.selected {

    background: #111;

    color: #fff;

    border: 1px solid #fff;
}

/* =========================================================
   MOBILE
========================================================= */

@media (max-width:600px) {

    .brand {

        font-size: 16px;
    }

    .timer {

        font-size: 12px;

        padding: 8px 10px;
    }

    .info h1 {

        font-size: 20px;
    }

    .bottom-actions {

        width: 100%;

        gap: 8px;
    }

    .whatsapp-btn,
    .submit-btn {

        flex: 1;

        padding: 12px 10px;

        font-size: 13px;
    }

}

</style>

</head>

<body>

<div class="topbar">

    <div class="topbar-inner">

        <div>

            <div class="brand">
                Imagination Nature Studio
            </div>

            <div class="gallery-title">
                <?= e($gallery['title']) ?>
            </div>

        </div>

        <div
            id="timer"
            class="timer <?= $isExpired ? 'expired' : '' ?>"
        >

            <?= $isExpired ? 'EXPIRED' : 'Loading...' ?>

        </div>

    </div>

</div>


<main class="container">

    <div class="info">

        <h1>
            <?= e($gallery['title']) ?>
        </h1>

        <p>
            Hello
            <strong>
                <?= e($gallery['client_name']) ?>
            </strong>
        </p>

        <p>
            Select your favorite photos.
            Tap a photo to view it larger and zoom in.
        </p>

        <p class="price">

            <?= number_format($pricePerPhoto, 0) ?>
            RWF / photo

        </p>

    </div>


    <?php if ($isExpired): ?>

        <div class="expired-box">

            <strong>
                This photo selection session has expired.
            </strong>

            <br>

            Please contact Imagination Nature Studio.

        </div>

    <?php endif; ?>


    <div class="photo-grid">

        <?php foreach ($photos as $index => $photo): ?>

            <?php

            $photoId =
                (int)$photo['id'];

            $number =
                (int)$photo['photo_number'];

            $selected =
                isset($selectedIds[$photoId]);

            $url =
                previewUrl(
                    (string)$photo['preview_filename'],
                    $galleryId
                );

            ?>

            <div
                class="photo-card <?= $selected ? 'selected' : '' ?>"
                data-id="<?= $photoId ?>"
                data-index="<?= $index ?>"
                data-number="<?= $number ?>"
                data-url="<?= e($url) ?>"
            >

                <span class="photo-number">

                    #<?= str_pad(
                        (string)$number,
                        3,
                        '0',
                        STR_PAD_LEFT
                    ) ?>

                </span>

                <span class="check">
                    ✓
                </span>

                <img
                    src="<?= e($url) ?>"
                    alt="Photo #<?= $number ?>"
                    loading="lazy"
                    draggable="false"
                >

                <span class="zoom-hint">
                    🔍 Tap to view
                </span>

            </div>

        <?php endforeach; ?>

    </div>

</main>


<div class="bottom-bar">

    <div class="bottom-inner">

        <div class="summary">

            <strong>

                <span id="selectedCount">
                    <?= $selectedCount ?>
                </span>

                selected

            </strong>

            <small>

                Total:

                <span id="totalAmount">
                    <?= number_format($total, 0) ?>
                </span>

                RWF

            </small>

        </div>


        <div class="bottom-actions">

            <a
                href="https://wa.me/250788577033?text=Hello%20Imagination%20Nature%20Studio%2C%20I%20need%20help%20with%20my%20photo%20selection."
                class="whatsapp-btn"
                target="_blank"
                rel="noopener noreferrer"
            >
                💬 WhatsApp
            </a>

            <button
                id="submitBtn"
                class="submit-btn"
                type="button"
            <?= (
                $isExpired ||
                $selectedCount < 1
            ) ? 'disabled' : '' ?>
            >

                Submit Selection

            </button>

        </div>

    </div>

</div>


<!-- =========================================================
     LIGHTBOX
========================================================= -->

<div
    id="lightbox"
    class="lightbox"
    aria-hidden="true"
>

    <div class="lightbox-top">

        <div
            id="lightboxCounter"
            class="lightbox-counter"
        >
            Photo
        </div>

        <button
            id="closeLightbox"
            class="lightbox-close"
            type="button"
        >
            ×
        </button>

    </div>


    <button
        id="prevBtn"
        class="prev-btn"
        type="button"
    >
        ‹
    </button>


    <div
        id="lightboxStage"
        class="lightbox-stage"
    >

        <img
            id="lightboxImage"
            class="lightbox-image"
            src=""
            alt=""
            draggable="false"
        >

    </div>


    <button
        id="nextBtn"
        class="next-btn"
        type="button"
    >
        ›
    </button>


    <div class="zoom-controls">

        <button
            id="zoomIn"
            class="zoom-btn"
            type="button"
        >
            +
        </button>

        <button
            id="zoomOut"
            class="zoom-btn"
            type="button"
        >
            −
        </button>

        <button
            id="zoomReset"
            class="zoom-btn"
            type="button"
        >
            ⟳
        </button>

    </div>


    <div class="lightbox-bottom">

        <button
            id="lightboxSelect"
            class="lightbox-select"
            type="button"
        >
            Select Photo
        </button>

    </div>

</div>


<script>

const TOKEN =
    <?= json_encode($token) ?>;

const PRICE =
    <?= json_encode($pricePerPhoto) ?>;

const EXPIRES_AT =
    <?= json_encode($gallery['expires_at']) ?>;

const IS_EXPIRED =
    <?= $isExpired ? 'true' : 'false' ?>;


/*
|--------------------------------------------------------------------------
| ELEMENTS
|--------------------------------------------------------------------------
*/

const cards =
    Array.from(
        document.querySelectorAll('.photo-card')
    );

const lightbox =
    document.getElementById('lightbox');

const stage =
    document.getElementById('lightboxStage');

const lightboxImage =
    document.getElementById('lightboxImage');

const lightboxCounter =
    document.getElementById('lightboxCounter');

const lightboxSelect =
    document.getElementById('lightboxSelect');

const selectedCountEl =
    document.getElementById('selectedCount');

const totalAmountEl =
    document.getElementById('totalAmount');

const submitBtn =
    document.getElementById('submitBtn');

const timerEl =
    document.getElementById('timer');


/*
|--------------------------------------------------------------------------
| STATE
|--------------------------------------------------------------------------
*/

let currentIndex = 0;

let selectedCount =
    <?= (int)$selectedCount ?>;

let zoom = 1;

let panX = 0;

let panY = 0;

let dragging = false;

let startX = 0;

let startY = 0;

let startPanX = 0;

let startPanY = 0;


/*
|--------------------------------------------------------------------------
| MONEY
|--------------------------------------------------------------------------
*/

function formatMoney(number) {

    return new Intl.NumberFormat(
        'en-US',
        {
            maximumFractionDigits: 0
        }
    ).format(number);

}


/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

function updateSummary() {

    selectedCountEl.textContent =
        selectedCount;

    totalAmountEl.textContent =
        formatMoney(
            selectedCount * PRICE
        );

    submitBtn.disabled =
        IS_EXPIRED ||
        selectedCount < 1;
}


/*
|--------------------------------------------------------------------------
| SELECTED
|--------------------------------------------------------------------------
*/

function isSelected(card) {

    return card.classList.contains(
        'selected'
    );

}


/*
|--------------------------------------------------------------------------
| TOGGLE PHOTO
|--------------------------------------------------------------------------
| IMPORTANT:
| toggle.php expects normal POST $_POST values.
|--------------------------------------------------------------------------
*/

async function togglePhoto(card) {

    if (IS_EXPIRED) {
        return;
    }

    const photoId =
        card.dataset.id;

    const action =
        isSelected(card)
            ? 'remove'
            : 'add';

    try {

        const form =
            new FormData();

        form.append(
            'token',
            TOKEN
        );

        form.append(
            'photo_id',
            photoId
        );

        form.append(
            'action',
            action
        );


        const response =
            await fetch(
                'toggle.php',
                {
                    method: 'POST',
                    body: form
                }
            );


        const data =
            await response.json();


        if (!data.success) {

            alert(
                data.message ||
                'Unable to update selection.'
            );

            return;
        }


        if (action === 'add') {

            card.classList.add(
                'selected'
            );

            selectedCount++;

        } else {

            card.classList.remove(
                'selected'
            );

            selectedCount--;

        }


        updateSummary();

        updateLightboxButton();


    } catch (error) {

        console.error(error);

        alert(
            'Unable to update selection. Please try again.'
        );

    }

}


/*
|--------------------------------------------------------------------------
| LIGHTBOX
|--------------------------------------------------------------------------
*/

function openLightbox(index) {

    if (!cards.length) {
        return;
    }

    currentIndex =
        Math.max(
            0,
            Math.min(
                index,
                cards.length - 1
            )
        );

    lightbox.classList.add(
        'open'
    );

    lightbox.setAttribute(
        'aria-hidden',
        'false'
    );

    document.body.style.overflow =
        'hidden';

    showCurrentPhoto();
}


function closeLightbox() {

    lightbox.classList.remove(
        'open'
    );

    lightbox.setAttribute(
        'aria-hidden',
        'true'
    );

    document.body.style.overflow =
        '';

    resetZoom();
}


function showCurrentPhoto() {

    const card =
        cards[currentIndex];

    if (!card) {
        return;
    }

    lightboxImage.src =
        card.dataset.url;

    lightboxImage.alt =
        'Photo #' +
        card.dataset.number;

    lightboxCounter.textContent =
        'Photo #' +
        String(
            card.dataset.number
        ).padStart(3, '0') +
        ' / ' +
        cards.length;

    updateLightboxButton();

    resetZoom();
}


/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
*/

function nextPhoto() {

    currentIndex =
        (currentIndex + 1)
        % cards.length;

    showCurrentPhoto();
}


function previousPhoto() {

    currentIndex =
        (
            currentIndex -
            1 +
            cards.length
        )
        % cards.length;

    showCurrentPhoto();
}


/*
|--------------------------------------------------------------------------
| ZOOM
|--------------------------------------------------------------------------
*/

function applyTransform() {

    lightboxImage.style.transform =
        `translate3d(
            ${panX}px,
            ${panY}px,
            0
        ) scale(${zoom})`;
}


function resetZoom() {

    zoom = 1;

    panX = 0;

    panY = 0;

    applyTransform();
}


function zoomIn() {

    zoom =
        Math.min(
            5,
            zoom + .5
        );

    applyTransform();
}


function zoomOut() {

    zoom =
        Math.max(
            1,
            zoom - .5
        );

    if (zoom === 1) {

        panX = 0;

        panY = 0;
    }

    applyTransform();
}


/*
|--------------------------------------------------------------------------
| WHEEL
|--------------------------------------------------------------------------
*/

stage.addEventListener(
    'wheel',
    function(event) {

        event.preventDefault();

        if (event.deltaY < 0) {

            zoomIn();

        } else {

            zoomOut();
        }

    },
    {
        passive: false
    }
);


/*
|--------------------------------------------------------------------------
| DRAG
|--------------------------------------------------------------------------
*/

stage.addEventListener(
    'pointerdown',
    function(event) {

        if (zoom <= 1) {
            return;
        }

        dragging = true;

        stage.classList.add(
            'dragging'
        );

        startX =
            event.clientX;

        startY =
            event.clientY;

        startPanX =
            panX;

        startPanY =
            panY;

        stage.setPointerCapture(
            event.pointerId
        );
    }
);


stage.addEventListener(
    'pointermove',
    function(event) {

        if (!dragging) {
            return;
        }

        panX =
            startPanX +
            (
                event.clientX -
                startX
            );

        panY =
            startPanY +
            (
                event.clientY -
                startY
            );

        applyTransform();
    }
);


stage.addEventListener(
    'pointerup',
    function() {

        dragging = false;

        stage.classList.remove(
            'dragging'
        );
    }
);


/*
|--------------------------------------------------------------------------
| DOUBLE CLICK ZOOM
|--------------------------------------------------------------------------
*/

stage.addEventListener(
    'dblclick',
    function(event) {

        event.preventDefault();

        if (zoom === 1) {

            zoom = 2.5;

        } else {

            resetZoom();
        }

        applyTransform();
    }
);


/*
|--------------------------------------------------------------------------
| LIGHTBOX SELECT
|--------------------------------------------------------------------------
*/

function updateLightboxButton() {

    const card =
        cards[currentIndex];

    if (!card) {
        return;
    }

    if (isSelected(card)) {

        lightboxSelect.textContent =
            '✓ Selected — Tap to Remove';

        lightboxSelect.classList.add(
            'selected'
        );

    } else {

        lightboxSelect.textContent =
            'Select Photo';

        lightboxSelect.classList.remove(
            'selected'
        );
    }
}


lightboxSelect.addEventListener(
    'click',
    function() {

        const card =
            cards[currentIndex];

        togglePhoto(card);
    }
);


/*
|--------------------------------------------------------------------------
| CARD CLICK
|--------------------------------------------------------------------------
*/

cards.forEach(
    function(card) {

        card.addEventListener(
            'click',
            function() {

                openLightbox(
                    parseInt(
                        card.dataset.index,
                        10
                    )
                );

            }
        );

    }
);


/*
|--------------------------------------------------------------------------
| BUTTONS
|--------------------------------------------------------------------------
*/

document
    .getElementById('closeLightbox')
    .addEventListener(
        'click',
        closeLightbox
    );

document
    .getElementById('nextBtn')
    .addEventListener(
        'click',
        nextPhoto
    );

document
    .getElementById('prevBtn')
    .addEventListener(
        'click',
        previousPhoto
    );

document
    .getElementById('zoomIn')
    .addEventListener(
        'click',
        zoomIn
    );

document
    .getElementById('zoomOut')
    .addEventListener(
        'click',
        zoomOut
    );

document
    .getElementById('zoomReset')
    .addEventListener(
        'click',
        resetZoom
    );


/*
|--------------------------------------------------------------------------
| KEYBOARD
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event) {

        if (
            !lightbox.classList.contains(
                'open'
            )
        ) {

            return;
        }

        if (event.key === 'Escape') {
            closeLightbox();
        }

        if (event.key === 'ArrowRight') {
            nextPhoto();
        }

        if (event.key === 'ArrowLeft') {
            previousPhoto();
        }

        if (
            event.key === '+' ||
            event.key === '='
        ) {

            zoomIn();
        }

        if (event.key === '-') {

            zoomOut();
        }

    }
);


/*
|--------------------------------------------------------------------------
| SUBMIT
|--------------------------------------------------------------------------
| IMPORTANT:
| submit.php expects JSON, NOT FormData.
|--------------------------------------------------------------------------
*/

submitBtn.addEventListener(
    'click',
    async function() {

        if (
            IS_EXPIRED ||
            selectedCount < 1
        ) {

            return;
        }

        const confirmed =
            confirm(
                'Submit your selected photos?'
            );

        if (!confirmed) {
            return;
        }

        submitBtn.disabled = true;

        submitBtn.textContent =
            'Submitting...';


        try {

            /*
             * submit.php expects JSON
             */

            const response =
                await fetch(
                    'submit.php',
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type':
                                'application/json'
                        },

                        body: JSON.stringify({
                            token: TOKEN
                        })
                    }
                );


            const data =
                await response.json();


            if (!data.ok) {

                alert(
                    data.message ||
                    'Could not submit selection.'
                );

                submitBtn.disabled =
                    false;

                submitBtn.textContent =
                    'Submit Selection';

                return;
            }


            alert(
                'Selection submitted successfully.'
            );


            location.reload();


        } catch (error) {

            console.error(error);

            alert(
                'Something went wrong. Please try again.'
            );

            submitBtn.disabled =
                false;

            submitBtn.textContent =
                'Submit Selection';
        }

    }
);


/*
|--------------------------------------------------------------------------
| TIMER
|--------------------------------------------------------------------------
*/

function updateTimer() {

    if (IS_EXPIRED) {

        timerEl.textContent =
            'EXPIRED';

        timerEl.classList.add(
            'expired'
        );

        return;
    }

    if (!EXPIRES_AT) {
        return;
    }

    const end =
        new Date(
            EXPIRES_AT.replace(
                ' ',
                'T'
            )
        ).getTime();

    const now =
        Date.now();

    let diff =
        end - now;


    if (diff <= 0) {

        timerEl.textContent =
            'EXPIRED';

        timerEl.classList.add(
            'expired'
        );

        return;
    }


    const hours =
        Math.floor(
            diff /
            (1000 * 60 * 60)
        );

    diff %=
        1000 * 60 * 60;


    const minutes =
        Math.floor(
            diff /
            (1000 * 60)
        );

    diff %=
        1000 * 60;


    const seconds =
        Math.floor(
            diff /
            1000
        );


    timerEl.textContent =
        String(hours).padStart(2, '0') +
        ':' +
        String(minutes).padStart(2, '0') +
        ':' +
        String(seconds).padStart(2, '0');
}


updateTimer();

setInterval(
    updateTimer,
    1000
);


updateSummary();

</script>

</body>
</html>