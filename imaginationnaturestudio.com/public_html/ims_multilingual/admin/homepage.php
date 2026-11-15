<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

/* =====================================================
   POST ACTIONS
   ===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';

    try {
        /* ----- 1. Update a section ----- */
        if ($action === 'save_section') {
            $key = preg_replace('/[^a-z_]/', '', $_POST['section_key'] ?? '');
            if (!$key) throw new RuntimeException('Invalid section key.');

            $title       = trim($_POST['title']        ?? '');
            $subtitle    = trim($_POST['subtitle']     ?? '');
            $content     = trim($_POST['content']      ?? '');
            $btnText     = trim($_POST['button_text']  ?? '');
            $btnLink     = trim($_POST['button_link']  ?? '');
            $btnText2    = trim($_POST['button_text_2']?? '');
            $btnLink2    = trim($_POST['button_link_2']?? '');
            $isActive    = !empty($_POST['is_active']) ? 1 : 0;

            // Optional image
            $imageName = null;
            if (!empty($_FILES['image']['name'])) {
                $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($_FILES['image']['tmp_name']);
                if (!isset($allowed[$mime])) throw new RuntimeException('Unsupported image.');
                if ($_FILES['image']['size'] > 5*1024*1024) throw new RuntimeException('Image too large.');
                $imageName = $key . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
                if (!move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . $imageName))
                    throw new RuntimeException('Upload failed.');
            }

            if ($imageName) {
                $pdo->prepare(
                    "UPDATE homepage_sections SET title=?, subtitle=?, content=?,
                            button_text=?, button_link=?, button_text_2=?, button_link_2=?,
                            image=?, is_active=? WHERE section_key=?"
                )->execute([$title,$subtitle,$content,$btnText,$btnLink,$btnText2,$btnLink2,$imageName,$isActive,$key]);
            } else {
                $pdo->prepare(
                    "UPDATE homepage_sections SET title=?, subtitle=?, content=?,
                            button_text=?, button_link=?, button_text_2=?, button_link_2=?,
                            is_active=? WHERE section_key=?"
                )->execute([$title,$subtitle,$content,$btnText,$btnLink,$btnText2,$btnLink2,$isActive,$key]);
            }
            flash('ok', "Section '$key' saved.");
        }

        /* ----- 2. Create a new section ----- */
        elseif ($action === 'create_section') {
            $key = strtolower(preg_replace('/[^a-zA-Z0-9]+/','_', trim($_POST['new_key'] ?? '')));
            $key = trim($key, '_');
            if (!$key) throw new RuntimeException('Section key is required.');

            $exists = $pdo->prepare("SELECT 1 FROM homepage_sections WHERE section_key=?");
            $exists->execute([$key]);
            if ($exists->fetchColumn()) throw new RuntimeException("Section '$key' already exists.");

            $pdo->prepare(
                "INSERT INTO homepage_sections (section_key,title,subtitle,content,button_text,button_link,is_active,sort_order)
                 VALUES (?,?,?,?,?,?,1,?)"
            )->execute([
                $key,
                trim($_POST['title'] ?? ''),
                trim($_POST['subtitle'] ?? ''),
                trim($_POST['content'] ?? ''),
                trim($_POST['button_text'] ?? ''),
                trim($_POST['button_link'] ?? ''),
                (int)($_POST['sort_order'] ?? 99),
            ]);
            flash('ok', "Section '$key' created.");
        }

        /* ----- 3. Delete a section ----- */
        elseif ($action === 'delete_section') {
            $key = preg_replace('/[^a-z_]/', '', $_POST['section_key'] ?? '');
            $core = ['hero','services','about','portfolio','cta','stats'];
            if (in_array($key, $core, true)) throw new RuntimeException('Core sections cannot be deleted.');
            $pdo->prepare("DELETE FROM homepage_sections WHERE section_key=?")->execute([$key]);
            flash('ok', "Section '$key' deleted.");
        }

        /* ----- 4. Toggle featured service ----- */
        elseif ($action === 'toggle_featured') {
            $type  = $_POST['type'] === 'gallery' ? 'gallery' : 'service';
            $refId = (int)($_POST['ref_id'] ?? 0);
            if (!$refId) throw new RuntimeException('Bad ref.');

            $stmt = $pdo->prepare("SELECT id FROM homepage_featured WHERE type=? AND ref_id=?");
            $stmt->execute([$type, $refId]);
            if ($row = $stmt->fetch()) {
                $pdo->prepare("DELETE FROM homepage_featured WHERE id=?")->execute([$row['id']]);
                flash('ok', 'Removed from homepage.');
            } else {
                $pdo->prepare("INSERT INTO homepage_featured (type,ref_id,sort_order) VALUES (?,?,0)")
                    ->execute([$type, $refId]);
                flash('ok', 'Added to homepage.');
            }
        }

        /* ----- 5. Reorder featured ----- */
        elseif ($action === 'reorder_featured') {
            $type = $_POST['type'] === 'gallery' ? 'gallery' : 'service';
            $order = json_decode($_POST['order'] ?? '[]', true) ?: [];
            $up = $pdo->prepare("UPDATE homepage_featured SET sort_order=? WHERE type=? AND ref_id=?");
            foreach ($order as $i => $refId) {
                $up->execute([(int)$i, $type, (int)$refId]);
            }
            flash('ok', 'Order updated.');
        }

        redirect('homepage.php');
    } catch (Throwable $e) {
        flash('err', $e->getMessage());
        redirect('homepage.php');
    }
}

/* =====================================================
   LOAD DATA
   ===================================================== */
$sections = $pdo->query("SELECT * FROM homepage_sections ORDER BY sort_order, id")->fetchAll();

$services = $pdo->query("SELECT id,name,price FROM services WHERE status='active' ORDER BY name")->fetchAll();
$gallery  = $pdo->query("SELECT id,title,image,category FROM gallery ORDER BY id DESC")->fetchAll();

$featServices = array_column(
    $pdo->query("SELECT ref_id FROM homepage_featured WHERE type='service'")->fetchAll(), 'ref_id'
);
$featGallery = array_column(
    $pdo->query("SELECT ref_id FROM homepage_featured WHERE type='gallery'")->fetchAll(), 'ref_id'
);

require __DIR__ . '/includes/admin-header.php';
?>

<h1>Homepage Manager</h1>
<p style="color:#7a716a;margin-bottom:1.6rem">
  Edit every section of the homepage, pick which services and images are featured.
</p>

<?php if ($m = flash('ok')):  ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
<?php if ($m = flash('err')): ?><div class="alert alert-error"><?= e($m) ?></div><?php endif; ?>

<!-- ============ SECTION EDITOR ============ -->
<h2 style="font-family:var(--font-serif);font-size:1.2rem;margin:1.6rem 0 1rem">Sections</h2>

<?php foreach ($sections as $s): ?>
<details class="card" style="padding:0;margin-bottom:1rem">
  <summary style="padding:1.1rem 1.4rem;cursor:pointer;display:flex;justify-content:space-between;align-items:center;list-style:none">
    <span>
      <strong style="font-family:var(--font-serif);font-size:1.05rem"><?= e($s['section_key']) ?></strong>
      <span style="color:#7a716a;font-size:.85rem;margin-left:.6rem">— <?= e($s['title']) ?></span>
    </span>
    <span class="status-pill <?= $s['is_active'] ? 'confirmed' : 'cancelled' ?>">
      <?= $s['is_active'] ? 'Active' : 'Hidden' ?>
    </span>
  </summary>

  <form method="post" enctype="multipart/form-data" style="padding:1.4rem;border-top:1px solid var(--line)">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_section">
    <input type="hidden" name="section_key" value="<?= e($s['section_key']) ?>">

    <div class="form-grid">
      <div class="full"><label>Title / Headline</label>
        <input type="text" name="title" value="<?= e($s['title']) ?>"></div>

      <div><label>Subtitle / Eyebrow</label>
        <input type="text" name="subtitle" value="<?= e($s['subtitle']) ?>"></div>

      <div><label>Image</label>
        <?php if ($s['image']): ?>
          <div style="margin-bottom:.5rem">
            <img src="<?= e(SITE_URL.'/uploads/'.$s['image']) ?>?v=<?= time() ?>"
                 style="max-height:80px;border-radius:8px">
          </div>
        <?php endif; ?>
        <input type="file" name="image" accept="image/*">
      </div>

      <div class="full"><label>Content / Paragraph</label>
        <textarea name="content" rows="4"><?= e($s['content']) ?></textarea></div>

      <div><label>Button 1 — Text</label>
        <input type="text" name="button_text" value="<?= e($s['button_text']) ?>"></div>
      <div><label>Button 1 — Link</label>
        <input type="text" name="button_link" value="<?= e($s['button_link']) ?>"></div>
      <div><label>Button 2 — Text</label>
        <input type="text" name="button_text_2" value="<?= e($s['button_text_2']) ?>"></div>
      <div><label>Button 2 — Link</label>
        <input type="text" name="button_link_2" value="<?= e($s['button_link_2']) ?>"></div>

      <div class="full">
        <label style="display:flex;gap:.6rem;align-items:center;text-transform:none;letter-spacing:0">
          <input type="checkbox" name="is_active" value="1" <?= $s['is_active']?'checked':'' ?> style="width:auto">
          Show this section on the homepage
        </label>
      </div>
    </div>

    <div style="display:flex;gap:.6rem;margin-top:1rem">
      <button class="btn btn-primary">Save section</button>
    </div>
  </form>

  <?php if (!in_array($s['section_key'], ['hero','services','about','portfolio','cta','stats'], true)): ?>
  <form method="post" style="padding:0 1.4rem 1.2rem" onsubmit="return confirm('Delete this section?')">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_section">
    <input type="hidden" name="section_key" value="<?= e($s['section_key']) ?>">
    <button class="btn btn-sm btn-ghost">Delete section</button>
  </form>
  <?php endif; ?>
</details>
<?php endforeach; ?>

<!-- ============ ADD NEW SECTION ============ -->
<details class="card" style="padding:1.4rem;margin-bottom:2rem">
  <summary style="cursor:pointer;font-family:var(--font-serif);font-size:1.05rem">+ Add a new section</summary>
  <form method="post" style="margin-top:1rem">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create_section">
    <div class="form-grid">
      <div><label>Key (lowercase, no spaces)</label>
        <input type="text" name="new_key" required placeholder="e.g. testimonials"></div>
      <div><label>Sort order</label>
        <input type="number" name="sort_order" value="10"></div>
      <div class="full"><label>Title</label>
        <input type="text" name="title"></div>
      <div><label>Subtitle</label>
        <input type="text" name="subtitle"></div>
      <div><label>Button text</label>
        <input type="text" name="button_text"></div>
      <div><label>Button link</label>
        <input type="text" name="button_link" placeholder="/booking.php"></div>
      <div class="full"><label>Content</label>
        <textarea name="content" rows="3"></textarea></div>
    </div>
    <button class="btn btn-primary" style="margin-top:1rem">Create section</button>
  </form>
</details>

<!-- ============ FEATURED SERVICES ============ -->
<h2 style="font-family:var(--font-serif);font-size:1.2rem;margin:2rem 0 1rem">Featured Services (max ~6 recommended)</h2>
<div class="card" style="padding:1.4rem">
  <p style="color:#7a716a;font-size:.85rem;margin:0 0 1rem">Click to add/remove from homepage services preview.</p>
  <div style="display:flex;flex-wrap:wrap;gap:.6rem">
    <?php foreach ($services as $sv): $on = in_array((int)$sv['id'], array_map('intval',$featServices), true); ?>
      <form method="post" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle_featured">
        <input type="hidden" name="type" value="service">
        <input type="hidden" name="ref_id" value="<?= (int)$sv['id'] ?>">
        <button class="btn btn-sm <?= $on ? 'btn-primary' : 'btn-ghost' ?>">
          <?= $on ? '★ ' : '+ ' ?><?= e($sv['name']) ?>
        </button>
      </form>
    <?php endforeach; ?>
  </div>
</div>

<!-- ============ FEATURED GALLERY ============ -->
<h2 style="font-family:var(--font-serif);font-size:1.2rem;margin:2rem 0 1rem">Featured Portfolio Images</h2>
<div class="card" style="padding:1.4rem">
  <p style="color:#7a716a;font-size:.85rem;margin:0 0 1rem">Click an image to add or remove it from the homepage portfolio preview.</p>

  <?php if (!$gallery): ?>
    <p style="color:#7a716a">No gallery images yet. <a href="gallery.php" style="color:var(--accent-2)">Upload some →</a></p>
  <?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:.6rem">
    <?php foreach ($gallery as $g): $on = in_array((int)$g['id'], array_map('intval',$featGallery), true); ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle_featured">
        <input type="hidden" name="type" value="gallery">
        <input type="hidden" name="ref_id" value="<?= (int)$g['id'] ?>">
        <button type="submit" style="border:2px solid <?= $on ? 'var(--accent)' : 'transparent' ?>;padding:0;border-radius:10px;overflow:hidden;background:none;position:relative;cursor:pointer;width:100%">
          <img src="<?= e(SITE_URL.'/uploads/'.$g['image']) ?>" alt=""
               style="width:100%;aspect-ratio:1;object-fit:cover;display:block">
          <?php if ($on): ?>
            <span style="position:absolute;top:6px;right:6px;background:var(--accent);color:#14100d;
                         width:22px;height:22px;border-radius:50%;display:grid;place-items:center;font-weight:700">✓</span>
          <?php endif; ?>
        </button>
      </form>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>