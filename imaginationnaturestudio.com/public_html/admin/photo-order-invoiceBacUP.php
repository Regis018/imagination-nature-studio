<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_admin_only();

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid photo order.');
}

$stmt = $pdo->prepare('SELECT * FROM photo_orders WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    exit('Photo order not found.');
}

/* Invoice/download/print controls are available only after Admin approval. */
if (($order['order_status'] ?? '') !== 'approved') {
    http_response_code(403);
    exit('This invoice is available after Admin approves the photo order.');
}

$quantity = (int)($order['photo_quantity'] ?? 0);
$unitPrice = (float)($order['unit_price'] ?? 3000);
$total = (float)($order['total_amount'] ?? ($quantity * $unitPrice));
$orderNumber = (string)($order['order_number'] ?? ('PO-' . $id));
$customerName = (string)($order['customer_name'] ?? '');
$customerPhone = (string)($order['customer_phone'] ?? '');
$customerEmail = (string)($order['customer_email'] ?? ($order['email'] ?? ''));
$feedback = (string)($order['feedback'] ?? '');
$createdAt = (string)($order['created_at'] ?? '');
$invoiceDate = $createdAt !== '' ? date('d/m/Y', strtotime($createdAt)) : date('d/m/Y');

function invoice_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice <?= invoice_h($orderNumber) ?> | Imagination Nature Studio</title>
<style>
*{box-sizing:border-box}
body{margin:0;padding:28px;background:#eef1f0;color:#222;font-family:Arial,Helvetica,sans-serif}
.actions{max-width:900px;margin:0 auto 16px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
.btn{border:0;border-radius:7px;padding:12px 16px;font-weight:700;cursor:pointer;text-decoration:none;font-size:14px}
.btn-primary{background:#18794e;color:#fff}.btn-secondary{background:#e1e5e3;color:#1e2924}
.invoice{max-width:900px;margin:0 auto;background:#fff;padding:42px;box-shadow:0 8px 28px rgba(0,0,0,.08)}
.top{display:flex;justify-content:space-between;gap:24px;border-bottom:2px solid #1d6b4b;padding-bottom:22px}
h1{font-size:28px;color:#1d6b4b;margin:0 0 10px}
p{margin:5px 0;line-height:1.45}
.invoice-title{text-align:right}.invoice-title h2{font-size:32px;margin:0 0 10px}
.status{display:inline-block;margin-top:10px;background:#e3f3e8;color:#17603f;padding:7px 11px;border-radius:20px;font-size:12px;font-weight:bold}
.details{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin:28px 0}
.section-title{color:#1d6b4b;font-weight:bold;font-size:12px;letter-spacing:.7px;margin-bottom:10px}
table{width:100%;border-collapse:collapse;margin-top:10px}
th{background:#edf4ef;color:#35443b;text-align:left;font-size:12px;text-transform:uppercase;padding:13px 10px}
td{padding:15px 10px;border-bottom:1px solid #e5e9e6}
.num{text-align:right;white-space:nowrap}
.total-row td{border:0;font-weight:bold;font-size:18px;padding-top:20px}
.notes{margin-top:24px;padding:15px;background:#f5f7f5;border-radius:8px;overflow-wrap:anywhere}
.footer{margin-top:28px;padding-top:16px;border-top:1px solid #e0e5e1;font-size:12px;color:#59645d}
@media(max-width:600px){body{padding:10px}.invoice{padding:20px}.top{display:block}.invoice-title{text-align:left;margin-top:20px}.details{grid-template-columns:1fr;gap:18px}h1{font-size:23px}}
@media print{body{background:#fff;padding:0}.actions{display:none}.invoice{max-width:none;margin:0;padding:12mm;box-shadow:none}.status{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style>
</head>
<body>
<div class="actions">
<button class="btn btn-primary" type="button" onclick="window.print()">Download PDF / Save as PDF</button>
<button class="btn btn-secondary" type="button" onclick="window.print()">Print Invoice</button>
<a class="btn btn-secondary" href="<?= invoice_h(SITE_URL) ?>/admin/photo-orders.php">Back to Photo Orders</a>
</div>
<main class="invoice">
<div class="top">
  <div>
    <h1>Imagination Nature Studio</h1>
    <p>Kigali, Rwanda</p>
    <p><strong>TIN:</strong> 111997931</p>
    <p><strong>Tel:</strong> 0788577033</p>
    <p><strong>Email:</strong> rider.regis18@gmail.com</p>
  </div>
  <div class="invoice-title">
    <h2>INVOICE</h2>
    <p><strong>Invoice No:</strong> <?= invoice_h($orderNumber) ?></p>
    <p><strong>Date:</strong> <?= invoice_h($invoiceDate) ?></p>
    <span class="status">APPROVED</span>
  </div>
</div>

<div class="details">
  <section>
    <div class="section-title">BILL TO</div>
    <p><strong><?= invoice_h($customerName) ?></strong></p>
    <?php if ($customerPhone !== ''): ?><p>Telephone: <?= invoice_h($customerPhone) ?></p><?php endif; ?>
    <?php if ($customerEmail !== ''): ?><p>Email: <?= invoice_h($customerEmail) ?></p><?php endif; ?>
  </section>
  <section>
    <div class="section-title">ORDER INFORMATION</div>
    <p><strong>Order Number:</strong> <?= invoice_h($orderNumber) ?></p>
    <p><strong>Service:</strong> Photo Editing</p>
    <p><strong>Payment Status:</strong> <?= invoice_h(ucfirst((string)($order['payment_status'] ?? 'unpaid'))) ?></p>
  </section>
</div>

<table>
<thead><tr><th>Description</th><th class="num">Quantity</th><th class="num">Unit Price</th><th class="num">Amount</th></tr></thead>
<tbody>
<tr>
  <td>Photo Editing Service</td>
  <td class="num"><?= number_format($quantity) ?></td>
  <td class="num"><?= number_format($unitPrice) ?> RWF</td>
  <td class="num"><?= number_format($total) ?> RWF</td>
</tr>
<tr class="total-row"><td colspan="3" class="num">TOTAL DUE</td><td class="num"><?= number_format($total) ?> RWF</td></tr>
</tbody>
</table>

<?php if ($feedback !== ''): ?>
<div class="notes"><strong>Customer Notes:</strong><br><?= nl2br(invoice_h($feedback)) ?></div>
<?php endif; ?>

<div class="footer">
<strong>Important:</strong> This invoice confirms that your photo editing order was approved by Imagination Nature Studio.
Please keep the order number for reference. Payment status is shown above.
</div>
</main>
</body>
</html>
