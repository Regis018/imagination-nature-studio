<?php
$adminTitle = 'Settings';
require_once __DIR__ . '/includes/admin-header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $keys = ['store_name','whatsapp_number','whatsapp_display','hero_headline','hero_subtitle','hero_support',
             'contact_email','contact_phone','contact_address','currency'];
    foreach ($keys as $k) {
        if (isset($_POST[$k])) set_setting($k, trim((string)$_POST[$k]));
    }
    // hero image upload
    if (!empty($_FILES['hero_image']['name']) && $_FILES['hero_image']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['hero_image'];
        $info = @getimagesize($f['tmp_name']);
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if ($info && isset($allowed[$info['mime']]) && $f['size'] <= (int)($CONFIG['app']['upload_max_bytes'] ?? 5242880)) {
            $dir = __DIR__ . '/../assets/images';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $name = 'hero-' . bin2hex(random_bytes(8)) . '.' . $allowed[$info['mime']];
            if (move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
                set_setting('hero_image', 'assets/images/' . $name);
            }
        }
    }
    redirect('admin/settings.php?saved=1');
}
?>
<h1>Settings</h1>
<?php if (isset($_GET['saved'])): ?><div class="alert success">Settings saved.</div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="form-grid">
  <?= csrf_field() ?>
  <label>Store Name<input type="text" name="store_name" value="<?= e(setting('store_name','') ?? '') ?>"></label>
  <label>WhatsApp Number (digits only, e.g. 250788577033)<input type="text" name="whatsapp_number" value="<?= e(setting('whatsapp_number','') ?? '') ?>"></label>
  <label>WhatsApp Display<input type="text" name="whatsapp_display" value="<?= e(setting('whatsapp_display','') ?? '') ?>"></label>
  <label>Contact Email<input type="text" name="contact_email" value="<?= e(setting('contact_email','') ?? '') ?>"></label>
  <label>Contact Phone<input type="text" name="contact_phone" value="<?= e(setting('contact_phone','') ?? '') ?>"></label>
  <label>Contact Address<input type="text" name="contact_address" value="<?= e(setting('contact_address','') ?? '') ?>"></label>
  <label>Currency<input type="text" name="currency" value="<?= e(setting('currency','RWF') ?? 'RWF') ?>"></label>
  <label>Hero Headline<input type="text" name="hero_headline" value="<?= e(setting('hero_headline','') ?? '') ?>"></label>
  <label class="full">Hero Subtitle<input type="text" name="hero_subtitle" value="<?= e(setting('hero_subtitle','') ?? '') ?>"></label>
  <label class="full">Hero Support<input type="text" name="hero_support" value="<?= e(setting('hero_support','') ?? '') ?>"></label>
  <label class="full">Hero Image (current: <?= e(setting('hero_image','') ?? '') ?>)<input type="file" name="hero_image" accept="image/jpeg,image/png,image/webp"></label>
  <div class="full"><button class="btn btn-gold" type="submit">Save Settings</button></div>
</form>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>