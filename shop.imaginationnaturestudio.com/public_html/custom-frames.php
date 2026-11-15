<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/whatsapp.php';

$errors = [];
$success = false;
$old = ['customer_name'=>'','phone'=>'','subject'=>'','category'=>'','size'=>'','quantity'=>1,'preferences'=>'','notes'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (array_keys($old) as $k) { if (isset($_POST[$k])) $old[$k] = trim((string)$_POST[$k]); }
    $old['quantity'] = max(1, min(50, (int)$_POST['quantity']));

    if ($old['customer_name'] === '') $errors[] = 'Name is required.';
    if ($old['phone'] === '') $errors[] = 'Phone is required.';
    if ($old['subject'] === '') $errors[] = 'Subject is required.';

    $uploadPath = null;
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['image'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload error.';
        } else {
            $max = (int)($CONFIG['app']['upload_max_bytes'] ?? 5242880);
            if ($f['size'] > $max) $errors[] = 'Image too large (max 5MB).';
            $info = @getimagesize($f['tmp_name']);
            $allowedMime = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            if (!$info || !isset($allowedMime[$info['mime']])) {
                $errors[] = 'Unsupported image format (JPG, PNG, WEBP only).';
            } else {
                $ext = $allowedMime[$info['mime']];
                $dir = __DIR__ . '/uploads/custom-requests';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $name = bin2hex(random_bytes(16)) . '.' . $ext;
                $target = $dir . '/' . $name;
                if (move_uploaded_file($f['tmp_name'], $target)) {
                    $uploadPath = 'uploads/custom-requests/' . $name;
                } else {
                    $errors[] = 'Could not save uploaded image.';
                }
            }
        }
    }

    if (!$errors) {
        $st = $pdo->prepare('INSERT INTO custom_requests
            (customer_name, phone, subject, category, size, quantity, preferences, notes, upload_path)
            VALUES (?,?,?,?,?,?,?,?,?)');
        $st->execute([
            $old['customer_name'], $old['phone'], $old['subject'],
            $old['category'], $old['size'], $old['quantity'], $old['preferences'], $old['notes'], $uploadPath
        ]);
        $_SESSION['custom_request_msg'] = "Hello! I'd like a custom frame request:\n\n"
            . "Name: {$old['customer_name']}\n"
            . "Subject: {$old['subject']}\n"
            . "Category: {$old['category']}\n"
            . "Size: {$old['size']}\n"
            . "Quantity: {$old['quantity']}\n"
            . "Preferences: {$old['preferences']}\n"
            . "Notes: {$old['notes']}\n"
            . ($uploadPath ? "\nI have an image to share via WhatsApp." : '');
        redirect('custom-frames.php?submitted=1');
    }
}

$pageTitle = 'Custom Frames — ' . (setting('store_name','') ?? '');
require __DIR__ . '/includes/header.php';
?>
<section class="page-head"><div class="container"><h1>Custom Frames</h1><p>Design your own frame — football, family, celebrity or anything you love.</p></div></section>
<div class="container content custom-form-wrap">
  <?php if (isset($_GET['submitted']) && !empty($_SESSION['custom_request_msg'])): ?>
    <div class="alert success">
      <p>Your custom frame request has been saved. Now send it on WhatsApp so we can start your design.</p>
      <a class="btn btn-wa" target="_blank" rel="noopener"
         href="<?= e(whatsapp_url($_SESSION['custom_request_msg'])) ?>">Send Request on WhatsApp</a>
    </div>
    <?php unset($_SESSION['custom_request_msg']); ?>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert error"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="form-grid">
    <?= csrf_field() ?>
    <label>Your Name *<input type="text" name="customer_name" value="<?= e($old['customer_name']) ?>" required></label>
    <label>WhatsApp Phone *<input type="text" name="phone" value="<?= e($old['phone']) ?>" placeholder="+250 …" required></label>
    <label>Frame Subject *<input type="text" name="subject" value="<?= e($old['subject']) ?>" placeholder="e.g. Real Madrid crest, family portrait" required></label>
    <label>Category
      <select name="category">
        <?php foreach (['Football Club','Football Legend','Celebrity','Family / Personal','Other'] as $c): ?>
          <option <?= $old['category']===$c?'selected':'' ?>><?= e($c) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Preferred Size
      <select name="size">
        <?php foreach (['Small (20x25 cm)','Medium (30x40 cm)','Large (40x50 cm)','Extra Large (50x70 cm)','Custom'] as $s): ?>
          <option <?= $old['size']===$s?'selected':'' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Quantity<input type="number" name="quantity" min="1" max="50" value="<?= (int)$old['quantity'] ?>"></label>
    <label class="full">Design Preferences<textarea name="preferences" rows="3"><?= e($old['preferences']) ?></textarea></label>
    <label class="full">Additional Notes<textarea name="notes" rows="3"><?= e($old['notes']) ?></textarea></label>
    <label class="full">Reference Image (optional, JPG/PNG/WEBP, max 5MB)
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
    </label>
    <div class="full">
      <button type="submit" class="btn btn-gold">Submit Request</button>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>