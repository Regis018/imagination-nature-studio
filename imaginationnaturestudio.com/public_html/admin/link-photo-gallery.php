<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

/*
 * Admin helper:
 * Link an already-created photo-selection gallery to a Studio booking.
 * This is useful if your existing gallery creation/upload page is already working.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        flash('err', 'Invalid security token. Please try again.');
        redirect('link-photo-gallery.php');
    }

    $galleryId = (int)($_POST['gallery_id'] ?? 0);
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    if ($galleryId <= 0 || $bookingId <= 0) {
        flash('err', 'Please select both a gallery and a booking.');
        redirect('link-photo-gallery.php');
    }

    $gStmt = $pdo->prepare('SELECT id, title, client_name, price_per_photo, status FROM photo_galleries WHERE id=? LIMIT 1');
    $gStmt->execute([$galleryId]);
    $gallery = $gStmt->fetch(PDO::FETCH_ASSOC);

    if (!$gallery) {
        flash('err', 'Gallery not found.');
        redirect('link-photo-gallery.php');
    }

    $bStmt = $pdo->prepare(
        "SELECT b.id, b.booking_number, b.booking_status, c.full_name, s.name AS service_name
         FROM bookings b
         JOIN customers c ON c.id=b.customer_id
         JOIN services s ON s.id=b.service_id
         WHERE b.id=? LIMIT 1"
    );
    $bStmt->execute([$bookingId]);
    $booking = $bStmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        flash('err', 'Booking not found.');
        redirect('link-photo-gallery.php');
    }

    $serviceName = strtolower(trim((string)$booking['service_name']));
    $isStudio = (
        strpos($serviceName, 'studio photoshoot') !== false
        ||
        (strpos($serviceName, 'studio') !== false && strpos($serviceName, 'photo') !== false)
    );

    if (!$isStudio) {
        flash('err', 'Only Studio Photoshoot bookings can be linked to this photo-selection workflow.');
        redirect('link-photo-gallery.php');
    }

    $price = (float)$gallery['price_per_photo'];
    if ($price <= 0) {
        $price = 3000.0;
        $pdo->prepare('UPDATE photo_galleries SET price_per_photo=? WHERE id=?')
            ->execute([$price, $galleryId]);
    }

    $pdo->prepare('UPDATE photo_galleries SET booking_id=? WHERE id=?')
        ->execute([$bookingId, $galleryId]);

    flash(
        'ok',
        'Gallery linked to booking ' . $booking['booking_number'] . '. Photo price: ' . rwf($price)
    );

    redirect('link-photo-gallery.php');
}

$galleries = $pdo->query(
    "SELECT id, title, client_name, price_per_photo, duration_minutes, status, booking_id
     FROM photo_galleries
     ORDER BY id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$bookings = $pdo->query(
    "SELECT b.id, b.booking_number, b.booking_date, b.start_time, b.end_time,
            b.booking_status, c.full_name, s.name AS service_name
     FROM bookings b
     JOIN customers c ON c.id=b.customer_id
     JOIN services s ON s.id=b.service_id
     WHERE LOWER(s.name) LIKE '%studio%'
       AND LOWER(s.name) LIKE '%photo%'
     ORDER BY b.id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/admin-header.php';
?>

<div style="max-width:1100px;margin:0 auto;padding:1rem 0 3rem">

    <h1>📸 Link Photo Gallery to Studio Booking</h1>

    <p style="color:#777;max-width:800px">
        After the Studio photoshoot, create the client's photo-selection gallery using your existing gallery system,
        then link that gallery to the correct booking here. Once the client submits the selection,
        the system calculates the final price and generates the invoice automatically.
    </p>

    <div style="background:#fff;border:1px solid #ddd;border-radius:14px;padding:1.2rem;margin-top:1rem">
        <form method="post">
            <?= csrf_field() ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <label>
                    <strong>Photo Selection Gallery</strong>
                    <select name="gallery_id" required style="width:100%;padding:.75rem;margin-top:.4rem">
                        <option value="">Select gallery...</option>
                        <?php foreach ($galleries as $g): ?>
                            <option value="<?= (int)$g['id'] ?>">
                                #<?= (int)$g['id'] ?> — <?= e((string)$g['title']) ?> —
                                <?= e((string)$g['client_name']) ?> —
                                <?= rwf((float)$g['price_per_photo']) ?>/photo
                                <?= $g['booking_id'] ? ' — already linked' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <strong>Studio Booking</strong>
                    <select name="booking_id" required style="width:100%;padding:.75rem;margin-top:.4rem">
                        <option value="">Select booking...</option>
                        <?php foreach ($bookings as $b): ?>
                            <option value="<?= (int)$b['id'] ?>">
                                <?= e((string)$b['booking_number']) ?> —
                                <?= e((string)$b['full_name']) ?> —
                                <?= e((string)$b['booking_date']) ?> —
                                <?= substr((string)$b['start_time'],0,5) ?>-<?= substr((string)$b['end_time'],0,5) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <button type="submit" style="margin-top:1rem;padding:.8rem 1.2rem;border:0;border-radius:10px;background:#111;color:#fff;font-weight:700;cursor:pointer">
                🔗 Link Gallery
            </button>
        </form>
    </div>

    <div style="margin-top:1.5rem;background:#fff;border:1px solid #ddd;border-radius:14px;overflow:auto">
        <table style="width:100%;border-collapse:collapse;min-width:750px">
            <thead>
                <tr>
                    <th style="padding:.8rem;text-align:left">Gallery</th>
                    <th style="padding:.8rem;text-align:left">Client</th>
                    <th style="padding:.8rem;text-align:left">Price</th>
                    <th style="padding:.8rem;text-align:left">Status</th>
                    <th style="padding:.8rem;text-align:left">Booking ID</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($galleries as $g): ?>
                <tr style="border-top:1px solid #eee">
                    <td style="padding:.8rem">#<?= (int)$g['id'] ?> — <?= e((string)$g['title']) ?></td>
                    <td style="padding:.8rem"><?= e((string)$g['client_name']) ?></td>
                    <td style="padding:.8rem"><?= rwf((float)$g['price_per_photo']) ?></td>
                    <td style="padding:.8rem"><?= e((string)$g['status']) ?></td>
                    <td style="padding:.8rem"><?= $g['booking_id'] ? (int)$g['booking_id'] : 'Not linked' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>
