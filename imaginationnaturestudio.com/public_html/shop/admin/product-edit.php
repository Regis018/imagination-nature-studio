<?php
$adminTitle = 'Edit Product';
require_once __DIR__ . '/includes/admin-header.php';

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$product = ['product_code'=>'','name'=>'','slug'=>'','description'=>'','category_id'=>1,'league_id'=>null,
            'base_price'=>'0','sale_price'=>'','main_image'=>'','featured'=>0,'status'=>1];

if ($isEdit) {
    $st = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $found = $st->fetch();
    if (!$found) { echo 'Not found'; require_once __DIR__ . '/includes/admin-footer.php'; exit; }
    $product = $found;
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$leagues = $pdo->query('SELECT * FROM leagues ORDER BY name')->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $product['product_code'] = trim((string)$_POST['product_code']);
    $product['name'] = trim((string)$_POST['name']);
    $product['slug'] = trim((string)($_POST['slug'] ?: slugify($product['name'])));
    $product['description'] = trim((string)$_POST['description']);
    $product['category_id'] = (int)$_POST['category_id'];
    $product['league_id'] = $_POST['league_id'] !== '' ? (int)$_POST['league_id'] : null;
    $product['base_price'] = (float)$_POST['base_price'];
    $product['sale_price'] = $_POST['sale_price'] !== '' ? (float)$_POST['sale_price'] : null;
    $product['featured'] = !empty($_POST['featured']) ? 1 : 0;
    $product['status'] = !empty($_POST['status']) ? 1 : 0;

    if ($product['product_code'] === '' || $product['name'] === '' || !$product['category_id']) {
        $errors[] = 'Code, name, and category are required.';
    }

    // Image upload
    if (!empty($_FILES['main_image']['name']) && $_FILES['main_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['main_image'];
        $info = @getimagesize($f['tmp_name']);
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!$info || !isset($allowed[$info['mime']])) {
            $errors[] = 'Invalid image format.';
        } elseif ($f['size'] > (int)($CONFIG['app']['upload_max_bytes'] ?? 5242880)) {
            $errors[] = 'Image too large.';
        } else {
            $dir = __DIR__ . '/../uploads/products';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $name = bin2hex(random_bytes(16)) . '.' . $allowed[$info['mime']];
            if (move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
                $product['main_image'] = 'uploads/products/' . $name;
            }
        }
    }

    if (!$errors) {
        if ($isEdit) {
            $sql = 'UPDATE products SET product_code=?,name=?,slug=?,description=?,category_id=?,league_id=?,
                    base_price=?,sale_price=?,main_image=?,featured=?,status=? WHERE id=?';
            $pdo->prepare($sql)->execute([
                $product['product_code'],$product['name'],$product['slug'],$product['description'],
                $product['category_id'],$product['league_id'],$product['base_price'],$product['sale_price'],
                $product['main_image'],$product['featured'],$product['status'],$id
            ]);
            redirect('admin/product-edit.php?id=' . $id);
        } else {
            $sql = 'INSERT INTO products (product_code,name,slug,description,category_id,league_id,base_price,sale_price,main_image,featured,status)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)';
            $pdo->prepare($sql)->execute([
                $product['product_code'],$product['name'],$product['slug'],$product['description'],
                $product['category_id'],$product['league_id'],$product['base_price'],$product['sale_price'],
                $product['main_image'],$product['featured'],$product['status']
            ]);
            $newId = (int)$pdo->lastInsertId();
            // Seed sizes
            $sizes = [['Small','20x25 cm',0],['Medium','30x40 cm',8000],['Large','40x50 cm',15000],['Extra Large','50x70 cm',25000]];
            $si = $pdo->prepare('INSERT INTO product_sizes (product_id,size_name,dimensions,price,stock_status) VALUES (?,?,?,?,"in_stock")');
            foreach ($sizes as $s) $si->execute([$newId,$s[0],$s[1],(float)$product['base_price'] + $s[2]]);
            redirect('admin/product-edit.php?id=' . $newId);
        }
    }
}

// Load sizes if editing
$sizes = $isEdit ? get_product_sizes($id) : [];
?>
<div class="admin-page-head"><h1><?= $isEdit ? 'Edit Product' : 'New Product' ?></h1><a class="btn" href="<?= e(url('admin/products.php')) ?>">← Back</a></div>
<?php if ($errors): ?><div class="alert error"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="form-grid">
  <?= csrf_field() ?>
  <label>Product Code<input type="text" name="product_code" value="<?= e($product['product_code']) ?>" required></label>
  <label>Name<input type="text" name="name" value="<?= e($product['name']) ?>" required></label>
  <label>Slug<input type="text" name="slug" value="<?= e($product['slug']) ?>" placeholder="auto-generated"></label>
  <label>Category<select name="category_id" required>
    <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$product['category_id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
  </select></label>
  <label>League (optional)<select name="league_id">
    <option value="">— none —</option>
    <?php foreach ($leagues as $l): ?><option value="<?= (int)$l['id'] ?>" <?= (int)$product['league_id']===(int)$l['id']?'selected':'' ?>><?= e($l['name']) ?></option><?php endforeach; ?>
  </select></label>
  <label>Base Price (RWF)<input type="number" step="0.01" name="base_price" value="<?= e((string)$product['base_price']) ?>" required></label>
  <label>Sale Price (optional)<input type="number" step="0.01" name="sale_price" value="<?= e((string)($product['sale_price'] ?? '')) ?>"></label>
  <label>Main Image<input type="file" name="main_image" accept="image/jpeg,image/png,image/webp">
    <?php if (!empty($product['main_image'])): ?><img class="admin-thumb" src="<?= e(url($product['main_image'])) ?>" alt=""><?php endif; ?>
  </label>
  <label class="full">Description<textarea name="description" rows="4"><?= e($product['description'] ?? '') ?></textarea></label>
  <label><input type="checkbox" name="featured" <?= $product['featured']?'checked':'' ?>> Featured</label>
  <label><input type="checkbox" name="status" <?= $product['status']?'checked':'' ?>> Active / Visible</label>
  <div class="full"><button class="btn btn-gold" type="submit">Save Product</button></div>
</form>

<?php if ($isEdit && $sizes): ?>
<h2>Sizes &amp; Prices</h2>
<table class="admin-table">
  <thead><tr><th>Size</th><th>Dimensions</th><th>Price (RWF)</th><th>Stock</th></tr></thead>
  <tbody>
    <?php foreach ($sizes as $s): ?>
      <tr>
        <td><?= e($s['size_name']) ?></td>
        <td><?= e($s['dimensions']) ?></td>
        <td><?= e(format_price($s['price'])) ?></td>
        <td><?= e($s['stock_status']) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<p class="muted small">For full per-size editing, extend this screen. Defaults are auto-created.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>