<?php
/**
 * Admin — Packages CRUD.
 *
 * Packages belong to a service. Each package has a name, description,
 * price, duration, and active/inactive status.
 *
 * Features:
 *   - List packages grouped by service
 *   - Add new package (choose service, name, price, duration)
 *   - Edit any field inline
 *   - Toggle active/inactive
 *   - Delete (with safety: shows how many bookings reference it)
 *   - Filter by service
 */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

/* =====================================================
   POST actions
   ===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {

    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    /* ---------- Create ---------- */
    if ($action === 'create') {
        $service_id = (int)($_POST['service_id'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $price      = (float)($_POST['price'] ?? 0);
        $duration   = trim($_POST['duration'] ?? '');
        $status     = ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'inactive';

        if ($service_id <= 0) {
            flash('err', 'Please choose a service.');
            redirect('packages.php');
        }
        if ($name === '') {
            flash('err', 'Package name is required.');
            redirect('packages.php');
        }

        $pdo->prepare(
            "INSERT INTO packages (service_id, name, description, price, duration, status)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([$service_id, $name, $desc, $price, $duration, $status]);

        flash('ok', 'Package added.');
        redirect('packages.php');
    }

    /* ---------- Update ---------- */
    if ($action === 'update' && $id) {
        $service_id = (int)($_POST['service_id'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $price      = (float)($_POST['price'] ?? 0);
        $duration   = trim($_POST['duration'] ?? '');
        $status     = ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'inactive';

        if ($service_id <= 0 || $name === '') {
            flash('err', 'Service and name are required.');
            redirect('packages.php');
        }

        $pdo->prepare(
            "UPDATE packages
             SET service_id=?, name=?, description=?, price=?, duration=?, status=?
             WHERE id=?"
        )->execute([$service_id, $name, $desc, $price, $duration, $status, $id]);

        flash('ok', 'Package updated.');
        redirect('packages.php');
    }

    /* ---------- Toggle status ---------- */
    if ($action === 'toggle' && $id) {
        $pdo->prepare("UPDATE packages SET status = IF(status='active','inactive','active') WHERE id=?")
            ->execute([$id]);
        flash('ok', 'Status toggled.');
        redirect('packages.php');
    }

    /* ---------- Delete ---------- */
    if ($action === 'delete' && $id) {

        /* Safety: block deletion if the package is used by any bookings */
        $count = (int)$pdo->prepare("SELECT COUNT(*) FROM bookings WHERE package_id=?")
                          ->execute([$id]);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE package_id=?");
        $stmt->execute([$id]);
        $usedBy = (int)$stmt->fetchColumn();

        if ($usedBy > 0) {
            flash('err', "Cannot delete — {$usedBy} booking(s) reference this package. Set it inactive instead.");
            redirect('packages.php');
        }

        $pdo->prepare("DELETE FROM packages WHERE id=?")->execute([$id]);
        flash('ok', 'Package deleted.');
        redirect('packages.php');
    }
}

/* =====================================================
   Filters
   ===================================================== */
$serviceFilter = (int)($_GET['service'] ?? 0);

/* =====================================================
   Load data
   ===================================================== */
$services = $pdo->query("SELECT id, name FROM services ORDER BY name")->fetchAll();

$sql = "SELECT p.*, s.name AS service_name, s.status AS service_status,
               (SELECT COUNT(*) FROM bookings b WHERE b.package_id = p.id) AS booking_count
        FROM packages p
        JOIN services s ON s.id = p.service_id
        WHERE 1=1";
$args = [];

if ($serviceFilter > 0) {
    $sql .= " AND p.service_id = ?";
    $args[] = $serviceFilter;
}

$sql .= " ORDER BY s.name, p.price";

$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$packages = $stmt->fetchAll();

/* Group by service for display */
$byService = [];
foreach ($packages as $p) {
    $byService[$p['service_name']][] = $p;
}

require __DIR__ . '/includes/admin-header.php';
?>

<style>
  .pkg-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.1rem;
  }
  .pkg-form-grid .full { grid-column: 1 / -1; }
  @media (max-width: 640px) { .pkg-form-grid { grid-template-columns: 1fr; } }

  .pkg-group {
    background: #171513;
    border: 1px solid #262220;
    border-radius: 14px;
    margin-bottom: 1.2rem;
    overflow: hidden;
  }
  .pkg-group-header {
    padding: 1rem 1.2rem;
    border-bottom: 1px solid #262220;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #100e0c;
  }
  .pkg-group-header h3 {
    font-family: var(--font-serif);
    font-size: 1.05rem;
    margin: 0;
    color: #f3ece3;
  }
  .pkg-group-header .count {
    font-size: .78rem;
    color: #7a716a;
  }

  .pkg-table { width: 100%; border-collapse: collapse; }
  .pkg-table th {
    text-align: left; font-size: .72rem; letter-spacing: .12em;
    text-transform: uppercase; color: #7a716a;
    padding: .8rem 1rem; background: #0e0d0c;
    white-space: nowrap;
  }
  .pkg-table td {
    padding: .9rem 1rem; border-top: 1px solid #262220;
    vertical-align: top;
  }
  .pkg-table tr:hover { background: #1a1714; }

  .pkg-actions {
    display: flex; gap: .4rem; flex-wrap: wrap;
  }

  .pkg-status {
    display: inline-block;
    padding: .3rem .7rem;
    border-radius: 999px;
    font-size: .72rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    font-weight: 600;
  }
  .pkg-status.active   { background: rgba(52,168,83,.15); color: #bfe7c8; border: 1px solid rgba(52,168,83,.4); }
  .pkg-status.inactive { background: rgba(200,60,60,.1);  color: #f0b3b3; border: 1px solid rgba(200,60,60,.3); }

  .pkg-badge {
    display: inline-block;
    background: rgba(224,122,47,.12);
    color: #f5cdad;
    border: 1px solid rgba(224,122,47,.35);
    border-radius: 999px;
    padding: 0 .5rem;
    font-size: .7rem;
    margin-left: .4rem;
  }
</style>

<!-- ============ HEADER ============ -->
<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1rem">
  <div>
    <h1 style="margin:0 0 .3rem">Packages</h1>
    <p style="color:#7a716a;margin:0">
      Bundled offerings that customers can select for a service.
      e.g. <em>Silver Wedding</em>, <em>Gold Wedding</em>, etc.
    </p>
  </div>

  <form method="get" style="display:flex;gap:.6rem;flex-wrap:wrap">
    <select name="service" style="padding:.55rem .8rem;border-radius:8px;background:#0e0d0c;color:#f3ece3;border:1px solid #262220;font-size:.85rem">
      <option value="">All services</option>
      <?php foreach ($services as $s): ?>
        <option value="<?= (int)$s['id'] ?>" <?= $serviceFilter === (int)$s['id'] ? 'selected' : '' ?>>
          <?= e($s['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-primary">Filter</button>
    <?php if ($serviceFilter): ?>
      <a class="btn btn-sm btn-ghost" href="packages.php">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- ============ FLASH ============ -->
<?php if ($m = flash('ok')):  ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
<?php if ($m = flash('err')): ?><div class="alert alert-error"><?= e($m) ?></div><?php endif; ?>

<!-- ============ ADD PACKAGE ============ -->
<form method="post" class="card" style="padding:1.6rem;margin-bottom:1.6rem">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">

  <h3 style="font-family:var(--font-serif);font-size:1.1rem;margin:0 0 1.2rem">Add new package</h3>

  <div class="pkg-form-grid">
    <div>
      <label>Service *</label>
      <select name="service_id" required>
        <option value="">— Choose a service —</option>
        <?php foreach ($services as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label>Package name *</label>
      <input type="text" name="name" required maxlength="150" placeholder="e.g. Gold Wedding">
    </div>

    <div>
      <label>Price (RWF)</label>
      <input type="number" name="price" min="0" step="1000" value="0">
    </div>

    <div>
      <label>Duration</label>
      <input type="text" name="duration" maxlength="60" placeholder="e.g. 10 hours">
    </div>

    <div>
      <label>Status</label>
      <select name="status">
        <option value="active">Active (selectable in booking)</option>
        <option value="inactive">Inactive (hidden)</option>
      </select>
    </div>

    <div class="full">
      <label>Description</label>
      <textarea name="description" rows="3" placeholder="What's included in this package?"></textarea>
    </div>
  </div>

  <button class="btn btn-primary" style="margin-top:1.2rem">Add package</button>
</form>

<!-- ============ PACKAGES GROUPED BY SERVICE ============ -->
<?php if (!$byService): ?>
  <div class="card" style="padding:3rem;text-align:center;color:#7a716a">
    <p style="margin:0 0 .4rem;font-size:1.05rem">No packages yet.</p>
    <p style="margin:0;font-size:.9rem">Add your first package above.</p>
  </div>
<?php else: ?>

  <h2 style="font-family:var(--font-serif);font-size:1.2rem;margin:1.6rem 0 .8rem">
    Packages by service
  </h2>

  <?php foreach ($byService as $svcName => $pkgList): ?>
    <div class="pkg-group">
      <div class="pkg-group-header">
        <h3><?= e($svcName) ?></h3>
        <span class="count"><?= count($pkgList) ?> package<?= count($pkgList) === 1 ? '' : 's' ?></span>
      </div>

      <div class="admin-table-wrap" style="overflow-x:auto">
        <table class="pkg-table">
          <thead>
            <tr>
              <th>Package</th>
              <th>Price</th>
              <th>Duration</th>
              <th>Status</th>
              <th>Bookings</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($pkgList as $p): ?>
            <tr>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="service_id" value="<?= (int)$p['service_id'] ?>">

                <td style="min-width:260px">
                  <input type="text" name="name" value="<?= e($p['name']) ?>" required maxlength="150"
                         style="width:100%">
                  <textarea name="description" rows="2" placeholder="Description"
                            style="margin-top:.5rem;font-size:.82rem;width:100%"><?= e($p['description']) ?></textarea>
                </td>

                <td style="min-width:120px">
                  <input type="number" name="price" value="<?= (float)$p['price'] ?>" step="1000" min="0"
                         style="width:100%">
                  <div style="color:#7a716a;font-size:.72rem;margin-top:.3rem">RWF</div>
                </td>

                <td style="min-width:110px">
                  <input type="text" name="duration" value="<?= e($p['duration']) ?>" maxlength="60"
                         style="width:100%">
                </td>

                <td>
                  <select name="status">
                    <option value="active"   <?= $p['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $p['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                  </select>
                </td>

                <td>
                  <?php if ((int)$p['booking_count'] > 0): ?>
                    <span class="pkg-badge"><?= (int)$p['booking_count'] ?> booking(s)</span>
                  <?php else: ?>
                    <span style="color:#6a6159;font-size:.82rem">—</span>
                  <?php endif; ?>
                </td>

                <td style="min-width:180px">
                  <div class="pkg-actions">
                    <button class="btn btn-sm btn-primary">Save</button>
                    <button class="btn btn-sm btn-ghost" formnovalidate
                            formaction="packages.php" name="action" value="delete"
                            onclick="return confirm('Delete this package? <?= (int)$p['booking_count'] > 0 ? 'It is used by ' . (int)$p['booking_count'] . ' booking(s) — deletion will be blocked.' : '' ?>')">
                      Delete
                    </button>
                  </div>
                </td>
              </form>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>