<?php
/**
 * Admin — Services CRUD with image upload.
 *
 * Features:
 *   - Add new service (with image)
 *   - Edit existing service (update image, replace old one)
 *   - Toggle active/inactive
 *   - Delete service (removes image from disk too)
 *   - Auto-generates slug from name
 *   - Auto-creates category folder for images in uploads/services/
 */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

/* =====================================================
   POST actions
   ===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {

    $action = $_POST['action'] ?? '';

    /* ---------- Helper: handle image upload ---------- */
    $handleImage = function (string $prefix) use (&$error) {
        if (empty($_FILES['image']['name']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            return null;   // no file uploaded, not an error
        }

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($_FILES['image']['tmp_name']);

        if (!isset($allowed[$mime])) {
            $error = 'Unsupported image type. Use JPG, PNG, WEBP or GIF.';
            return false;
        }
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            $error = 'Image too large (max 5 MB).';
            return false;
        }

        $dir = UPLOAD_DIR . 'services/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $name = $prefix . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $dir . $name)) {
            $error = 'Could not save the uploaded image.';
            return false;
        }
        return 'services/' . $name;   // relative to uploads/
    };

    $error = '';

    /* ---------- Delete service ---------- */
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            /* Remove image file too */
            $stmt = $pdo->prepare("SELECT image FROM services WHERE id=?");
            $stmt->execute([$id]);
            $img = $stmt->fetchColumn();
            if ($img && is_file(UPLOAD_DIR . $img)) {
                @unlink(UPLOAD_DIR . $img);
            }
            $pdo->prepare("DELETE FROM services WHERE id=?")->execute([$id]);
            flash('ok', 'Service deleted.');
        }
        redirect('services.php');
    }

    /* ---------- Update service ---------- */
    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { flash('err', 'Invalid service.'); redirect('services.php'); }

        $name     = trim($_POST['name'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $price    = (float)($_POST['price'] ?? 0);
        $duration = trim($_POST['duration'] ?? '');
        $status   = ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'inactive';
        $slug     = trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name)), '-');

        if ($name === '') {
            flash('err', 'Service name is required.');
            redirect('services.php');
        }

        /* Ensure slug is unique */
        $check = $pdo->prepare("SELECT id FROM services WHERE slug=? AND id<>?");
        $check->execute([$slug, $id]);
        if ($check->fetchColumn()) {
            $slug .= '-' . $id;
        }

        /* Image upload (optional) */
        $newImage = $handleImage('service');
        if ($newImage === false) {
            flash('err', $error);
            redirect('services.php');
        }

        if ($newImage) {
            /* Delete old image */
            $old = $pdo->prepare("SELECT image FROM services WHERE id=?");
            $old->execute([$id]);
            $oldImg = $old->fetchColumn();
            if ($oldImg && $oldImg !== $newImage && is_file(UPLOAD_DIR . $oldImg)) {
                @unlink(UPLOAD_DIR . $oldImg);
            }

            $pdo->prepare(
                "UPDATE services
                 SET name=?, slug=?, description=?, price=?, duration=?, status=?, image=?
                 WHERE id=?"
            )->execute([$name, $slug, $desc, $price, $duration, $status, $newImage, $id]);
        } else {
            $pdo->prepare(
                "UPDATE services
                 SET name=?, slug=?, description=?, price=?, duration=?, status=?
                 WHERE id=?"
            )->execute([$name, $slug, $desc, $price, $duration, $status, $id]);
        }

        flash('ok', 'Service updated.');
        redirect('services.php');
    }

    /* ---------- Add new service ---------- */
    if ($action === 'create') {
        $name     = trim($_POST['name'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $price    = (float)($_POST['price'] ?? 0);
        $duration = trim($_POST['duration'] ?? '');
        $status   = ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'inactive';
        $slug     = trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name)), '-');

        if ($name === '') {
            flash('err', 'Service name is required.');
            redirect('services.php');
        }

        /* Ensure slug is unique */
        $check = $pdo->prepare("SELECT id FROM services WHERE slug=?");
        $check->execute([$slug]);
        if ($check->fetchColumn()) {
            $slug .= '-' . time();
        }

        /* Image upload (optional) */
        $newImage = $handleImage('service');
        if ($newImage === false) {
            flash('err', $error);
            redirect('services.php');
        }

        $pdo->prepare(
            "INSERT INTO services (name, slug, description, price, duration, status, image)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([$name, $slug, $desc, $price, $duration, $status, $newImage]);

        flash('ok', 'Service added.');
        redirect('services.php');
    }
}

/* =====================================================
   Load services
   ===================================================== */
$rows = $pdo->query("SELECT * FROM services ORDER BY name")->fetchAll();

require __DIR__ . '/includes/admin-header.php';
?>

<style>
  .svc-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.1rem;
  }
  .svc-form-grid .full { grid-column: 1 / -1; }
  @media (max-width: 640px) {
    .svc-form-grid { grid-template-columns: 1fr; }
  }

  .svc-table { width: 100%; border-collapse: collapse; }
  .svc-table th {
    text-align: left; font-size: .72rem; letter-spacing: .12em;
    text-transform: uppercase; color: #7a716a;
    padding: .9rem 1rem; white-space: nowrap;
  }
  .svc-table td {
    padding: 1rem; border-top: 1px solid #262220;
    vertical-align: top;
  }

  .svc-thumb {
    width: 60px; height: 60px; border-radius: 10px;
    object-fit: cover; background: #0e0d0c;
    border: 1px solid #262220;
  }

  .svc-actions {
    display: flex; gap: .4rem; flex-wrap: wrap;
  }

  .svc-preview {
    display: inline-block; margin-bottom: .5rem;
    padding: .4rem; background: #0e0d0c; border-radius: 8px;
  }
  .svc-preview img {
    display: block; max-height: 70px; border-radius: 6px;
  }
</style>

<!-- ============ HEADER ============ -->
<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1rem">
  <div>
    <h1 style="margin:0 0 .3rem">Services</h1>
    <p style="color:#7a716a;margin:0">
      Manage your studio's service catalogue — pricing, duration, images, visibility.
    </p>
  </div>
</div>

<!-- ============ FLASH ============ -->
<?php if ($m = flash('ok')):  ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
<?php if ($m = flash('err')): ?><div class="alert alert-error"><?= e($m) ?></div><?php endif; ?>

<!-- ============ ADD NEW SERVICE ============ -->
<form method="post" enctype="multipart/form-data" class="card" style="padding:1.6rem;margin-bottom:1.6rem">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">

  <h3 style="font-family:var(--font-serif);font-size:1.1rem;margin:0 0 1.2rem">Add new service</h3>

  <div class="svc-form-grid">
    <div class="full">
      <label>Service name *</label>
      <input type="text" name="name" required maxlength="150" placeholder="e.g. Wedding Photography">
    </div>

    <div>
      <label>Price (RWF)</label>
      <input type="number" name="price" min="0" step="1000" value="0">
      <small style="color:#7a716a">Set 0 for "on request" / custom pricing</small>
    </div>

    <div>
      <label>Duration</label>
      <input type="text" name="duration" maxlength="60" placeholder="e.g. 2 hours">
    </div>

    <div>
      <label>Status</label>
      <select name="status">
        <option value="active">Active (shown on site)</option>
        <option value="inactive">Inactive (hidden)</option>
      </select>
    </div>

    <div>
      <label>Image (optional)</label>
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
      <small style="color:#7a716a">JPG, PNG, WEBP or GIF · max 5 MB</small>
    </div>

    <div class="full">
      <label>Description</label>
      <textarea name="description" rows="3" placeholder="Short description shown on the services page and homepage"></textarea>
    </div>
  </div>

  <button class="btn btn-primary" style="margin-top:1.2rem">Add service</button>
</form>

<!-- ============ EXISTING SERVICES ============ -->
<h2 style="font-family:var(--font-serif);font-size:1.2rem;margin:1.6rem 0 .8rem">
  All services (<?= count($rows) ?>)
</h2>

<div class="card" style="padding:0">
  <div class="admin-table-wrap" style="overflow-x:auto">
    <table class="svc-table">
      <thead>
        <tr>
          <th style="width:80px">Image</th>
          <th>Name & slug</th>
          <th>Price</th>
          <th>Duration</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $s): ?>
        <tr>
          <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">

            <!-- IMAGE -->
            <td>
              <?php
                $imgPath = $s['image'] && is_file(UPLOAD_DIR . $s['image'])
                    ? SITE_URL . '/uploads/' . $s['image']
                    : '';
              ?>
              <?php if ($imgPath): ?>
                <div class="svc-preview">
                  <img src="<?= e($imgPath) ?>?v=<?= time() ?>" alt="">
                </div>
              <?php else: ?>
                <div class="svc-thumb" style="display:grid;place-items:center;color:#4a4340;font-size:.7rem">
                  no img
                </div>
              <?php endif; ?>

              <input type="file" name="image"
                     accept="image/jpeg,image/png,image/webp,image/gif"
                     style="font-size:.75rem;margin-top:.4rem;max-width:110px">
            </td>

            <!-- NAME & SLUG -->
            <td style="min-width:240px">
              <input type="text" name="name" value="<?= e($s['name']) ?>" required maxlength="150"
                     style="width:100%">
              <div style="color:#7a716a;font-size:.72rem;margin-top:.3rem">
                /<?= e($s['slug']) ?>
              </div>
              <textarea name="description" rows="2"
                        placeholder="Description"
                        style="margin-top:.5rem;font-size:.82rem"><?= e($s['description']) ?></textarea>
            </td>

            <!-- PRICE -->
            <td style="min-width:120px">
              <input type="number" name="price" value="<?= (float)$s['price'] ?>" step="1000" min="0"
                     style="width:100%">
              <div style="color:#7a716a;font-size:.72rem;margin-top:.3rem">RWF</div>
            </td>

            <!-- DURATION -->
            <td style="min-width:110px">
              <input type="text" name="duration" value="<?= e($s['duration']) ?>" maxlength="60"
                     style="width:100%">
            </td>

            <!-- STATUS -->
            <td>
              <select name="status">
                <option value="active"   <?= $s['status']==='active'  ?'selected':'' ?>>Active</option>
                <option value="inactive" <?= $s['status']==='inactive'?'selected':'' ?>>Inactive</option>
              </select>
            </td>

            <!-- ACTIONS -->
            <td style="min-width:170px">
              <div class="svc-actions">
                <button class="btn btn-sm btn-primary">Save</button>
                <button class="btn btn-sm btn-ghost"
                        name="action" value="delete"
                        formnovalidate
                        onclick="return confirm('Delete this service? The image will also be removed.')">
                  Delete
                </button>
              </div>
            </td>
          </form>
        </tr>
      <?php endforeach; ?>

      <?php if (!$rows): ?>
        <tr>
          <td colspan="6" style="padding:3rem 1rem;text-align:center;color:#7a716a">
            No services yet. Add your first one above.
          </td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>