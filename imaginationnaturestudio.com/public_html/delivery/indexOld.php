<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$pdo = db();
$token = (string)($_GET['token'] ?? '');

function delivery_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function delivery_not_found(): void {
    http_response_code(404);
    exit('Gallery not found.');
}

if (!preg_match('/^[a-f0-9]{64}$/i', trim($token))) {
    delivery_not_found();
}

/*
 * IMPORTANT:
 * Find the gallery by its token first. A missing/stale Sales relationship
 * must never make a valid published gallery appear as "Gallery not found".
 * Sales are matched to this Photo Order from either relationship direction.
 */
$sql = "
    SELECT
        g.*,
        po.order_number,
        po.total_amount,
        po.amount_paid AS order_paid,
        po.payment_status AS order_payment_status,
        s.id AS resolved_sale_id,
        s.balance AS sale_balance,
        s.total AS sale_total,
        s.amount_paid AS sale_paid
    FROM final_delivery_galleries g
    LEFT JOIN photo_orders po
        ON po.id = g.photo_order_id
    LEFT JOIN sales s
        ON s.id = (
            SELECT s2.id
            FROM sales s2
            WHERE (
                (g.sale_id IS NOT NULL AND s2.id = g.sale_id)
                OR (po.sale_id IS NOT NULL AND s2.id = po.sale_id)
                OR (po.id IS NOT NULL AND s2.photo_order_id = po.id)
            )
            AND LOWER(COALESCE(s2.product_name, '')) LIKE 'photo editing order%'
            ORDER BY
                CASE
                    WHEN s2.id = g.sale_id THEN 0
                    WHEN s2.id = po.sale_id THEN 1
                    ELSE 2
                END,
                s2.id DESC
            LIMIT 1
        )
    WHERE LOWER(TRIM(g.access_token)) = LOWER(TRIM(?))
      AND LOWER(TRIM(g.status)) = 'published'
    LIMIT 1
";
$q = $pdo->prepare($sql);
$q->execute([$token]);
$g = $q->fetch(PDO::FETCH_ASSOC);

if (!$g) {
    delivery_not_found();
}

$total = $g['sale_total'] !== null
    ? (float)$g['sale_total']
    : (float)$g['total_amount'];

$amountPaid = $g['sale_paid'] !== null
    ? (float)$g['sale_paid']
    : (float)$g['order_paid'];

$balance = $g['sale_balance'] !== null
    ? max(0, (float)$g['sale_balance'])
    : max(0, $total - $amountPaid);

$fullyPaid = $total > 0
    && $amountPaid + 0.00001 >= $total
    && $balance <= 0.00001;

$galleryId = (int)$g['id'];

/*
 * Secure image preview endpoint in this same file.
 * The original files remain outside the public web directory.
 */
if (isset($_GET['preview'])) {
    $photoId = filter_var($_GET['preview'], FILTER_VALIDATE_INT);
    if (!$photoId || $photoId < 1) delivery_not_found();
    $photoQuery = $pdo->prepare("
        SELECT stored_name, preview_name, mime_type, file_size
        FROM final_delivery_photos WHERE id = ? AND gallery_id = ? LIMIT 1
    ");
    $photoQuery->execute([$photoId, $galleryId]);
    $photo = $photoQuery->fetch(PDO::FETCH_ASSOC);
    if (!$photo) delivery_not_found();

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $mime = (string)$photo['mime_type'];
    $storedName = basename((string)$photo['stored_name']);
    if (!in_array($mime, $allowedMimes, true) ||
        !preg_match('/^[a-f0-9]{40}\\.(jpg|png|webp)$/', $storedName)) delivery_not_found();

    $storageDir = dirname(__DIR__) . '/storage/final-delivery/' . $galleryId . '/';
    $isFull = isset($_GET['full']) && $_GET['full'] === '1';
    $imageName = $storedName;
    $responseMime = $mime;
    if (!$isFull) {
        $previewName = basename((string)($photo['preview_name'] ?? ''));
        if ($previewName !== '' &&
            preg_match('/^[a-f0-9]{40}-preview\\.jpg$/', $previewName) &&
            is_file($storageDir . $previewName)) {
            $imageName = $previewName;
            $responseMime = 'image/jpeg';
        }
    }
    $imagePath = $storageDir . $imageName;
    if (!is_file($imagePath) || !is_readable($imagePath)) delivery_not_found();
    header('Content-Type: ' . $responseMime);
    header('Content-Length: ' . (string)filesize($imagePath));
    header('Content-Disposition: inline; filename="photo-' . (int)$photoId . '.' . pathinfo($imageName, PATHINFO_EXTENSION) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=86400');
    readfile($imagePath);
    exit;
}

$photoQuery = $pdo->prepare("
    SELECT id, original_name
    FROM final_delivery_photos
    WHERE gallery_id = ?
    ORDER BY id
");
$photoQuery->execute([$galleryId]);
$photos = $photoQuery->fetchAll(PDO::FETCH_ASSOC);

/*
 * Studio profile details:
 * The location and services below are safe general details.
 * Add the official phone, WhatsApp and street address when confirmed.
 */
$studioName = 'Imagination Nature Studio';
$studioLocation = 'Kigali, Rwanda';
$studioServices = [
    'Photography & Videography',
    'Wedding and Event Coverage',
    'Photo Books & Printing',
    'Photo Frames',
    'Portraits & Product Photography'
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#171512">
<title>Photo Delivery | <?= delivery_h($studioName) ?></title>
<style>
:root {
    --bg: #11100e;
    --panel: #1d1a16;
    --panel-2: #252019;
    --gold: #d6ad70;
    --gold-light: #f0d5a8;
    --text: #f7f2e9;
    --muted: #b9b0a3;
    --line: rgba(214,173,112,.22);
    --radius: 18px;
}
* { box-sizing: border-box; }
body {
    margin: 0;
    color: var(--text);
    background:
        radial-gradient(ellipse at 15% 0%, rgba(214,173,112,.13), transparent 38%),
        radial-gradient(ellipse at 100% 35%, rgba(214,173,112,.08), transparent 34%),
        var(--bg);
    font: 16px/1.6 Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
}
a { color: inherit; }
.page { width: min(1180px, calc(100% - 36px)); margin: 0 auto; }
.topbar {
    display:flex; align-items:center; justify-content:space-between;
    gap:18px; padding:22px 0; border-bottom:1px solid var(--line);
}
.brand { display:flex; align-items:center; gap:13px; min-width:0; }
.brand-mark {
    width:48px; height:48px; flex:0 0 48px; display:grid; place-items:center;
    border:1px solid rgba(214,173,112,.65); border-radius:14px;
    color:var(--gold-light); font-family:Georgia,serif; font-size:24px;
}
.brand-name { margin:0; font-family:Georgia,serif; font-size:clamp(17px,2.5vw,23px); letter-spacing:.02em; }
.brand-sub { color:var(--muted); font-size:12px; letter-spacing:.15em; text-transform:uppercase; }
.location { color:var(--muted); font-size:13px; white-space:nowrap; }
.hero { padding:46px 0 28px; }
.eyebrow { color:var(--gold); font-size:12px; font-weight:700; letter-spacing:.2em; text-transform:uppercase; }
h1 { margin:8px 0 10px; font:clamp(32px,5vw,52px)/1.1 Georgia,serif; font-weight:500; }
.intro { max-width:680px; color:var(--muted); margin:0; }
.order-card {
    margin-top:25px; padding:20px 22px; display:flex; justify-content:space-between;
    align-items:center; flex-wrap:wrap; gap:14px;
    background:linear-gradient(135deg,rgba(214,173,112,.12),rgba(255,255,255,.025));
    border:1px solid var(--line); border-radius:var(--radius);
}
.client-name { font-size:19px; font-weight:700; }
.order-meta { color:var(--muted); font-size:13px; }
.count-pill {
    padding:8px 13px; border:1px solid var(--line); border-radius:999px;
    color:var(--gold-light); font-size:13px; white-space:nowrap;
}
.payment {
    margin:20px 0 0; padding:15px 18px; border-radius:13px;
    border:1px solid rgba(116,184,139,.28); background:rgba(61,120,81,.13);
    color:#d5f0dc;
}
.payment strong { display:block; margin-bottom:2px; }
.gallery-heading {
    display:flex; align-items:end; justify-content:space-between; gap:12px;
    margin:42px 0 17px;
}
.gallery-heading h2 { margin:0; font:30px Georgia,serif; font-weight:500; }
.gallery-heading p { margin:0; color:var(--muted); font-size:13px; }
.download-all-wrap { margin: 0 0 20px; }
.download-all-btn {
    display:inline-flex; align-items:center; justify-content:center; gap:9px;
    padding:13px 20px; border-radius:11px; border:1px solid rgba(240,213,168,.55);
    background:linear-gradient(135deg,var(--gold-light),var(--gold));
    color:#20180e; text-decoration:none; font-weight:800; font-size:14px;
    box-shadow:0 8px 22px rgba(0,0,0,.2);
}
.download-all-note { display:block; margin-top:7px; color:var(--muted); font-size:12px; }
.grid {
    display:grid; grid-template-columns:repeat(auto-fill,minmax(min(100%,270px),1fr));
    gap:20px; align-items:start;
}
.photo-card {
    overflow:hidden; background:var(--panel); border:1px solid var(--line);
    border-radius:16px; box-shadow:0 12px 30px rgba(0,0,0,.2);
}
.photo-open {
    position:relative; display:block; width:100%; padding:0; border:0;
    background:#29241e; cursor:zoom-in; overflow:hidden; text-align:inherit;
    -webkit-tap-highlight-color:transparent;
}
.photo-preview {
    display:block; width:100%; aspect-ratio:4/3; object-fit:cover;
    background:#29241e; transition:transform .25s ease;
}
.photo-open:active .photo-preview { transform:scale(1.025); }
.zoom-hint {
    position:absolute; right:10px; bottom:10px; padding:6px 10px;
    border-radius:999px; color:#fff; background:rgba(0,0,0,.62);
    font-size:11px; pointer-events:none;
}
.lightbox {
    position:fixed; inset:0; z-index:9999; display:none;
    align-items:center; justify-content:center; padding:16px;
    background:rgba(0,0,0,.96); overscroll-behavior:contain;
}
.lightbox.is-open { display:flex; }
.lightbox-image {
    display:block; max-width:100%; max-height:calc(100dvh - 76px);
    width:auto; height:auto; object-fit:contain; border-radius:5px;
    touch-action:pan-x pan-y pinch-zoom; user-select:none;
}
.lightbox-close {
    position:absolute; top: max(14px, env(safe-area-inset-top));
    right: max(14px, env(safe-area-inset-right)); z-index:2;
    width:46px; height:46px; border:1px solid rgba(255,255,255,.35);
    border-radius:50%; color:#fff; background:rgba(35,35,35,.8);
    font-size:27px; line-height:1; cursor:pointer;
}
.lightbox-caption {
    position:absolute; left:12px; right:12px; bottom:max(12px, env(safe-area-inset-bottom));
    color:#eee; text-align:center; font-size:12px;
    text-shadow:0 1px 4px #000; overflow-wrap:anywhere;
}
.photo-info { padding:13px 14px 15px; }
.photo-name {
    color:var(--muted); font-size:12px; overflow-wrap:anywhere;
    min-height:19px; margin-bottom:10px;
}
.btn {
    display:flex; align-items:center; justify-content:center; gap:8px;
    width:100%; padding:12px 15px; border-radius:10px;
    background:linear-gradient(135deg,var(--gold-light),var(--gold));
    color:#20180e; text-decoration:none; font-size:14px; font-weight:800;
    transition:filter .2s ease, transform .2s ease;
}
.btn:hover { filter:brightness(1.08); transform:translateY(-1px); }
.locked {
    margin-top:28px; padding:18px 20px; border:1px solid rgba(214,173,112,.3);
    border-radius:14px; background:rgba(214,173,112,.09); color:var(--gold-light);
}
.locked p { margin:5px 0 0; color:var(--muted); }
.studio-promo {
    margin:52px 0 28px; padding:26px;
    border:1px solid var(--line); border-radius:var(--radius);
    background:linear-gradient(120deg,rgba(214,173,112,.14),rgba(255,255,255,.025));
}
.studio-promo h2 { margin:0 0 7px; font:27px Georgia,serif; font-weight:500; }
.studio-promo p { margin:0; color:var(--muted); }
.services { display:flex; flex-wrap:wrap; gap:9px; margin-top:17px; }
.service {
    padding:7px 11px; border:1px solid var(--line); border-radius:999px;
    color:var(--gold-light); font-size:12px;
}
.footer {
    display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px;
    padding:20px 0 30px; border-top:1px solid var(--line);
    color:var(--muted); font-size:12px;
}
@media (max-width:600px) {
    .page { width:min(100% - 24px,1180px); }
    .topbar { align-items:flex-start; }
    .location { display:none; }
    .hero { padding-top:32px; }
    .order-card { padding:16px; }
    .gallery-heading { align-items:flex-start; flex-direction:column; }
    .grid { grid-template-columns:repeat(2,minmax(0,1fr)); gap:11px; }
    .photo-info { padding:10px; }
    .btn { padding:11px 8px; font-size:12px; }
    .studio-promo { padding:19px; margin-top:38px; }
}
@media (max-width:380px) {
    .grid { grid-template-columns:1fr; }
}
</style>
</head>
<body>
<div class="page">
    <header class="topbar">
        <div class="brand">
            <div class="brand-mark" aria-hidden="true">IN</div>
            <div>
                <p class="brand-name"><?= delivery_h($studioName) ?></p>
                <div class="brand-sub">Photography · Art · Printing</div>
            </div>
        </div>
        <div class="location"><?= delivery_h($studioLocation) ?></div>
    </header>

    <main>
        <section class="hero">
            <div class="eyebrow">Your memories, beautifully delivered</div>
            <h1>Final Photo Delivery</h1>
            <p class="intro">Your edited photographs are ready. View your gallery below and download your images in their original quality.</p>

            <div class="order-card">
                <div>
                    <div class="client-name"><?= delivery_h($g['client_name']) ?></div>
                    <div class="order-meta">Order <?= delivery_h($g['order_number']) ?></div>
                </div>
                <div class="count-pill"><?= count($photos) ?> final photos</div>
            </div>

            <?php if ($fullyPaid): ?>
                <div class="payment">
                    <strong>✓ Payment confirmed</strong>
                    You can download each photo individually in full resolution.
                </div>
            <?php else: ?>
                <div class="locked">
                    <strong>Downloads are locked.</strong>
                    <p>Your order must be paid in full before downloading the original photos.</p>
                    <p>Balance remaining: <?= number_format($balance) ?> RWF</p>
                </div>
            <?php endif; ?>
        </section>

        <section aria-label="Delivered photographs">
            <div class="gallery-heading">
                <h2>Your photo gallery</h2>
                <p>Tap a photo to view it larger.</p>
            </div>

            <?php if ($fullyPaid && count($photos) > 0): ?>
                <div class="download-all-wrap">
                    <a class="download-all-btn" href="download-all.php?token=<?= delivery_h($token) ?>">
                        <span aria-hidden="true">⬇</span> Download all photos (ZIP)
                    </a>
                    <span class="download-all-note">Download every original photo together in one ZIP file, or use the individual download buttons below.</span>
                </div>
            <?php endif; ?>

            <div class="grid">
                <?php foreach ($photos as $photo): ?>
                    <article class="photo-card">
                        <button class="photo-open" type="button"
                                data-full-src="?token=<?= delivery_h($token) ?>&preview=<?= (int)$photo['id'] ?>&full=1"
                                data-photo-name="<?= delivery_h($photo['original_name']) ?>"
                                aria-label="Zoom photo">
                            <img class="photo-preview"
                                 src="?token=<?= delivery_h($token) ?>&preview=<?= (int)$photo['id'] ?>"
                                 alt="<?= delivery_h($photo['original_name']) ?>"
                                 loading="lazy">
                            <span class="zoom-hint">Tap to zoom</span>
                        </button>
                        <div class="photo-info">
                            <div class="photo-name"><?= delivery_h($photo['original_name']) ?></div>
                            <?php if ($fullyPaid): ?>
                                <a class="btn" href="download.php?token=<?= delivery_h($token) ?>&photo=<?= (int)$photo['id'] ?>">
                                    Download full photo
                                </a>
                            <?php else: ?>
                                <div class="locked">Download locked</div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="studio-promo">
            <div class="eyebrow">More from our studio</div>
            <h2>Imagination Nature Studio</h2>
            <p>We help you preserve your special moments and bring your ideas to life in print.</p>
            <div class="services">
                <?php foreach ($studioServices as $service): ?>
                    <span class="service"><?= delivery_h($service) ?></span>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <div class="lightbox" id="photoLightbox" role="dialog" aria-modal="true" aria-label="Photo preview">
        <button class="lightbox-close" type="button" id="lightboxClose" aria-label="Close photo">×</button>
        <img class="lightbox-image" id="lightboxImage" src="" alt="">
        <div class="lightbox-caption" id="lightboxCaption"></div>
    </div>

    <footer class="footer">
        <span>© <?= date('Y') ?> <?= delivery_h($studioName) ?></span>
        <span><?= delivery_h($studioLocation) ?> · Thank you for choosing us</span>
    </footer>
</div>
<script>
(function () {
    const viewer = document.getElementById('photoLightbox');
    const viewerImage = document.getElementById('lightboxImage');
    const caption = document.getElementById('lightboxCaption');
    const closeButton = document.getElementById('lightboxClose');
    if (!viewer || !viewerImage || !closeButton) return;

    let previousFocus = null;

    function openViewer(src, name) {
        previousFocus = document.activeElement;
        viewerImage.src = src;
        viewerImage.alt = name || 'Delivered photo';
        caption.textContent = name || '';
        viewer.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        closeButton.focus();
    }

    function closeViewer() {
        viewer.classList.remove('is-open');
        viewerImage.src = '';
        document.body.style.overflow = '';
        if (previousFocus && typeof previousFocus.focus === 'function') {
            previousFocus.focus();
        }
    }

    document.querySelectorAll('.photo-open').forEach(function (button) {
        button.addEventListener('click', function () {
            openViewer(button.getAttribute('data-full-src'), button.getAttribute('data-photo-name'));
        });
    });

    closeButton.addEventListener('click', closeViewer);
    viewer.addEventListener('click', function (event) {
        if (event.target === viewer) closeViewer();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && viewer.classList.contains('is-open')) {
            closeViewer();
        }
    });
})();
</script>
</body>
</html>
