<?php
require_once __DIR__ . '/../includes/functions.php';
require_full_admin();

$pdo = db();
$ok = ''; $err = '';

/* Handle uploads + text updates */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    try {
        // ---- Text/number settings ----
        foreach (($_POST['settings'] ?? []) as $key => $value) {
            $key = preg_replace('/[^a-z0-9_]/', '', $key);
            if (!$key) continue;
            $pdo->prepare(
                "INSERT INTO site_settings (setting_key, setting_value, setting_type)
                 VALUES (?, ?, 'text')
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            )->execute([$key, trim((string)$value)]);
        }

        // ---- Image uploads ----
        $imageKeys = ['logo','logo_light','favicon','og_image'];
        foreach ($imageKeys as $imgKey) {
            if (empty($_FILES[$imgKey]['name'])) continue;

            $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/svg+xml'=>'svg','image/x-icon'=>'ico'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($_FILES[$imgKey]['tmp_name']);

            if (!isset($allowed[$mime])) {
                throw new RuntimeException("Unsupported file for $imgKey. Use JPG, PNG, WEBP, SVG or ICO.");
            }
            if ($_FILES[$imgKey]['size'] > 4 * 1024 * 1024) {
                throw new RuntimeException("File too large for $imgKey (max 4 MB).");
            }

            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            $ext  = $allowed[$mime];
            $name = $imgKey . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
            $dest = UPLOAD_DIR . $name;

            if (!move_uploaded_file($_FILES[$imgKey]['tmp_name'], $dest)) {
                throw new RuntimeException("Could not save $imgKey.");
            }

            // Remove previous file
            $prev = setting($imgKey);
            if ($prev && is_file(UPLOAD_DIR . $prev) && $prev !== $name) {
                @unlink(UPLOAD_DIR . $prev);
            }

            $pdo->prepare(
                "INSERT INTO site_settings (setting_key, setting_value, setting_type)
                 VALUES (?, ?, 'image')
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type='image'"
            )->execute([$imgKey, $name]);
        }

        // Bust cache: redirect to force fresh read
        flash('ok', 'Settings saved.');
        redirect('settings.php');
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

require __DIR__ . '/includes/admin-header.php';
?>

<h1>Site Settings</h1>
<p style="color:#7a716a;margin-bottom:1.6rem">Branding, contact details, social links and images.</p>

<?php if ($m = flash('ok')): ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card" style="padding:1.6rem">
  <?= csrf_field() ?>

  <!-- ============ LOGO & BRANDING ============ -->
  <h3 style="font-family:var(--font-serif);font-size:1.05rem;margin:0 0 1rem">Logo & Branding</h3>
  <div class="form-grid">

    <div>
      <label>Primary Logo</label>
      <?php $lv = logo_url(); ?>
      <?php if ($lv): ?>
        <div style="margin-bottom:.5rem;background:#0e0d0c;padding:.6rem;border-radius:8px;display:inline-block">
          <img src="<?= e($lv) ?>?v=<?= time() ?>" alt="Logo" style="max-height:60px;display:block">
        </div>
      <?php endif; ?>
      <input type="file" name="logo" accept="image/*">
      <small style="color:#7a716a">JPG, PNG, WEBP or SVG. Recommended: 400×120.</small>
    </div>

    <div>
      <label>Light Logo (for dark backgrounds)</label>
      <?php $llv = logo_url('light'); ?>
      <?php if ($llv && $llv !== $lv): ?>
        <div style="margin-bottom:.5rem;background:#0e0d0c;padding:.6rem;border-radius:8px;display:inline-block">
          <img src="<?= e($llv) ?>?v=<?= time() ?>" alt="Light logo" style="max-height:60px;display:block">
        </div>
      <?php endif; ?>
      <input type="file" name="logo_light" accept="image/*">
    </div>

    <div>
      <label>Favicon</label>
      <?php if ($fv = setting('favicon')): ?>
        <div style="margin-bottom:.5rem">
          <img src="<?= e(SITE_URL.'/uploads/'.$fv) ?>?v=<?= time() ?>" alt="Favicon"
               style="width:32px;height:32px;border-radius:6px;background:#0e0d0c;padding:4px">
        </div>
      <?php endif; ?>
      <input type="file" name="favicon" accept="image/*">
      <small style="color:#7a716a">32×32 or 512×512 PNG / ICO.</small>
    </div>

    <div>
      <label>Open Graph image (social share)</label>
      <?php if ($og = setting('og_image')): ?>
        <div style="margin-bottom:.5rem">
          <img src="<?= e(SITE_URL.'/uploads/'.$og) ?>?v=<?= time() ?>" alt="OG"
               style="max-height:60px;border-radius:6px">
        </div>
      <?php endif; ?>
      <input type="file" name="og_image" accept="image/*">
      <small style="color:#7a716a">1200×630 recommended.</small>
    </div>

    <div>
      <label>Primary color</label>
      <input type="text" name="settings[primary_color]" value="<?= e(setting('primary_color','#e07a2f')) ?>"
             placeholder="#e07a2f">
    </div>
    <div>
      <label>Accent color</label>
      <input type="text" name="settings[accent_color]" value="<?= e(setting('accent_color','#f2a35e')) ?>"
             placeholder="#f2a35e">
    </div>

    <div>
      <label>Site name</label>
      <input type="text" name="settings[site_name]" value="<?= e(setting('site_name', SITE_NAME)) ?>">
    </div>
    <div>
      <label>Tagline</label>
      <input type="text" name="settings[site_tagline]" value="<?= e(setting('site_tagline')) ?>">
    </div>

  </div>

  <!-- ============ CONTACT ============ -->
  <h3 style="font-family:var(--font-serif);font-size:1.05rem;margin:2rem 0 1rem">Contact</h3>
  <div class="form-grid">
    <div><label>Phone (primary)</label>
      <input type="text" name="settings[phone_primary]" value="<?= e(setting('phone_primary', BIZ_PHONE_1)) ?>"></div>
    <div><label>Phone (secondary)</label>
      <input type="text" name="settings[phone_secondary]" value="<?= e(setting('phone_secondary', BIZ_PHONE_2)) ?>"></div>
    <div><label>WhatsApp number (digits only)</label>
      <input type="text" name="settings[whatsapp]" value="<?= e(setting('whatsapp', BIZ_WHATSAPP)) ?>"
             placeholder="250788577033"></div>
    <div><label>Email</label>
      <input type="email" name="settings[email_primary]" value="<?= e(setting('email_primary', BIZ_EMAIL)) ?>"></div>
    <div class="full"><label>Address</label>
      <input type="text" name="settings[address]" value="<?= e(setting('address', BIZ_ADDRESS)) ?>"></div>
    <div class="full"><label>Opening hours</label>
      <input type="text" name="settings[opening_hours]" value="<?= e(setting('opening_hours', BIZ_HOURS)) ?>"></div>
    <div><label>TIN number</label>
      <input type="text" name="settings[tin_number]" value="<?= e(setting('tin_number', BIZ_TIN)) ?>"></div>
    <div class="full"><label>Google Maps embed URL</label>
      <input type="url" name="settings[map_embed]" value="<?= e(setting('map_embed')) ?>"></div>
  </div>

  <!-- ============ SOCIAL ============ -->
  <h3 style="font-family:var(--font-serif);font-size:1.05rem;margin:2rem 0 1rem">Social Links</h3>
  <div class="form-grid">
    <div><label>Instagram</label>
      <input type="url" name="settings[instagram_url]" value="<?= e(setting('instagram_url', SOCIAL_INSTAGRAM)) ?>"></div>
    <div><label>TikTok</label>
      <input type="url" name="settings[tiktok_url]" value="<?= e(setting('tiktok_url', SOCIAL_TIKTOK)) ?>"></div>
    <div><label>Facebook</label>
      <input type="url" name="settings[facebook_url]" value="<?= e(setting('facebook_url')) ?>"></div>
    <div><label>YouTube</label>
      <input type="url" name="settings[youtube_url]" value="<?= e(setting('youtube_url')) ?>"></div>
  </div>

  <!-- ============ FOOTER ============ -->
  <h3 style="font-family:var(--font-serif);font-size:1.05rem;margin:2rem 0 1rem">Footer</h3>
  <div class="form-grid">
    <div class="full"><label>Footer copyright text</label>
      <input type="text" name="settings[footer_text]" value="<?= e(setting('footer_text')) ?>"></div>
  </div>

  <button class="btn btn-primary" style="margin-top:1.6rem">Save settings</button>
</form>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>