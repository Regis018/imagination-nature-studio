<?php
/**
 * Admin — Quotations
 *
 * Create and manage custom quotations.
 */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();
$me  = current_admin();

$pageTitle = 'Quotations';

/* =====================================================
   Helpers
   ===================================================== */
function quotation_money($amount): string
{
    return number_format((float)$amount, 0, '.', ',');
}

/* =====================================================
   POST — Create quotation
   ===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {

    $action = $_POST['action'] ?? '';

    /* ---------- Delete quotation ---------- */
    if ($action === 'delete') {

        $deleteId = (int)($_POST['quotation_id'] ?? 0);

        if ($deleteId <= 0) {
            flash('err', 'Invalid quotation.');
            redirect('quotations.php');
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("DELETE FROM quotation_items WHERE quotation_id = ?");
            $stmt->execute([$deleteId]);

            $stmt = $pdo->prepare("DELETE FROM quotations WHERE id = ?");
            $stmt->execute([$deleteId]);

            $pdo->commit();

            flash('ok', 'Quotation deleted successfully.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('err', 'Could not delete quotation.');
        }

        redirect('quotations.php');
    }

    /* ---------- Update quotation ---------- */
    if ($action === 'update') {

        $editId = (int)($_POST['quotation_id'] ?? 0);

        $clientName    = trim($_POST['client_name'] ?? '');
        $clientPhone   = trim($_POST['client_phone'] ?? '');
        $clientEmail   = trim($_POST['client_email'] ?? '');
        $clientAddress = trim($_POST['client_address'] ?? '');
        $quotationDate = $_POST['quotation_date'] ?? date('Y-m-d');
        $validUntil    = $_POST['valid_until'] ?? '';
        $discount      = max(0, (float)($_POST['discount'] ?? 0));

        $advanceAmount       = max(0, (float)($_POST['advance_amount'] ?? 0));
        $advanceDate         = trim($_POST['advance_date'] ?? '');
        $secondPaymentAmount = max(0, (float)($_POST['second_payment_amount'] ?? 0));
        $secondPaymentDate   = trim($_POST['second_payment_date'] ?? '');
        $finalPaymentDate    = trim($_POST['final_payment_date'] ?? '');

        $notes = trim($_POST['notes'] ?? '');
        $terms = trim($_POST['terms'] ?? '');

        if ($editId <= 0 || $clientName === '') {
            flash('err', 'Client name and quotation ID are required.');
            redirect($editId > 0 ? 'quotations.php?edit=' . $editId : 'quotations.php');
        }

        $descriptions = $_POST['description'] ?? [];
        $quantities   = $_POST['quantity'] ?? [];
        $prices       = $_POST['unit_price'] ?? [];
        $packageIds   = $_POST['package_id'] ?? [];

        $items = [];
        $subtotal = 0;

        foreach ($descriptions as $i => $description) {
            $description = trim($description);
            if ($description === '') continue;

            $quantity  = max(1, (float)($quantities[$i] ?? 1));
            $unitPrice = max(0, (float)($prices[$i] ?? 0));
            $packageId = (int)($packageIds[$i] ?? 0);

            $lineTotal = $quantity * $unitPrice;

            $items[] = [
                'package_id'  => $packageId > 0 ? $packageId : null,
                'description' => $description,
                'quantity'    => $quantity,
                'unit_price'  => $unitPrice,
                'total'       => $lineTotal
            ];

            $subtotal += $lineTotal;
        }

        if (!$items) {
            flash('err', 'Please add at least one quotation item.');
            redirect('quotations.php?edit=' . $editId);
        }

        $discount = min($discount, $subtotal);
        $total = $subtotal - $discount;

        $paymentsEntered = $advanceAmount + $secondPaymentAmount;

        if ($paymentsEntered > $total) {
            flash('err', 'Advance + second payment cannot be greater than the quotation total.');
            redirect('quotations.php?edit=' . $editId);
        }

        $finalPaymentAmount = max(0, $total - $paymentsEntered);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT quotation_number
                 FROM quotations
                 WHERE id = ?
                 LIMIT 1"
            );
            $stmt->execute([$editId]);
            $existing = $stmt->fetch();

            if (!$existing) {
                throw new RuntimeException('Quotation not found.');
            }

            $stmt = $pdo->prepare(
                "UPDATE quotations SET
                    client_name = ?,
                    client_phone = ?,
                    client_email = ?,
                    client_address = ?,
                    quotation_date = ?,
                    valid_until = ?,
                    subtotal = ?,
                    discount = ?,
                    advance_amount = ?,
                    advance_date = ?,
                    second_payment_amount = ?,
                    second_payment_date = ?,
                    final_payment_amount = ?,
                    final_payment_date = ?,
                    total = ?,
                    notes = ?,
                    terms = ?
                 WHERE id = ?"
            );

            $stmt->execute([
                $clientName,
                $clientPhone ?: null,
                $clientEmail ?: null,
                $clientAddress ?: null,
                $quotationDate,
                $validUntil ?: null,
                $subtotal,
                $discount,
                $advanceAmount,
                $advanceDate ?: null,
                $secondPaymentAmount,
                $secondPaymentDate ?: null,
                $finalPaymentAmount,
                $finalPaymentDate ?: null,
                $total,
                $notes ?: null,
                $terms ?: null,
                $editId
            ]);

            $stmt = $pdo->prepare("DELETE FROM quotation_items WHERE quotation_id = ?");
            $stmt->execute([$editId]);

            $stmt = $pdo->prepare(
                "INSERT INTO quotation_items
                 (quotation_id, package_id, description, quantity, unit_price, total)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            foreach ($items as $item) {
                $stmt->execute([
                    $editId,
                    $item['package_id'],
                    $item['description'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total']
                ]);
            }

            $pdo->commit();

            flash('ok', 'Quotation ' . $existing['quotation_number'] . ' updated successfully.');
            redirect('quotations.php?view=' . $editId);

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('err', 'Could not update quotation. Please try again.');
            redirect('quotations.php?edit=' . $editId);
        }
    }

    if ($action === 'create') {

        $clientName    = trim($_POST['client_name'] ?? '');
        $clientPhone   = trim($_POST['client_phone'] ?? '');
        $clientEmail   = trim($_POST['client_email'] ?? '');
        $clientAddress = trim($_POST['client_address'] ?? '');
        $quotationDate = $_POST['quotation_date'] ?? date('Y-m-d');
        $validUntil    = $_POST['valid_until'] ?? '';
        $discount      = (float)($_POST['discount'] ?? 0);

        // Payment schedule
        $advanceAmount       = max(0, (float)($_POST['advance_amount'] ?? 0));
        $advanceDate         = trim($_POST['advance_date'] ?? '');
        $secondPaymentAmount = max(0, (float)($_POST['second_payment_amount'] ?? 0));
        $secondPaymentDate   = trim($_POST['second_payment_date'] ?? '');
        $finalPaymentDate    = trim($_POST['final_payment_date'] ?? '');

        $notes         = trim($_POST['notes'] ?? '');
        $terms         = trim($_POST['terms'] ?? '');

        if ($clientName === '') {
            flash('err', 'Client name is required.');
            redirect('quotations.php');
        }

        /* ---------- Items ---------- */
        $descriptions = $_POST['description'] ?? [];
        $quantities   = $_POST['quantity'] ?? [];
        $prices       = $_POST['unit_price'] ?? [];
        $packageIds   = $_POST['package_id'] ?? [];

        $items = [];
        $subtotal = 0;

        foreach ($descriptions as $i => $description) {

            $description = trim($description);
            $quantity    = (float)($quantities[$i] ?? 0);
            $unitPrice   = (float)($prices[$i] ?? 0);
            $packageId   = (int)($packageIds[$i] ?? 0);

            if ($description === '') {
                continue;
            }

            if ($quantity <= 0) {
                $quantity = 1;
            }

            if ($unitPrice < 0) {
                $unitPrice = 0;
            }

            $lineTotal = $quantity * $unitPrice;

            $items[] = [
                'package_id'  => $packageId > 0 ? $packageId : null,
                'description' => $description,
                'quantity'    => $quantity,
                'unit_price'  => $unitPrice,
                'total'       => $lineTotal
            ];

            $subtotal += $lineTotal;
        }

        if (!$items) {
            flash('err', 'Please add at least one quotation item.');
            redirect('quotations.php');
        }

        if ($discount < 0) {
            $discount = 0;
        }

        if ($discount > $subtotal) {
            $discount = $subtotal;
        }

        $total = $subtotal - $discount;

        $paymentsEntered = $advanceAmount + $secondPaymentAmount;
        if ($paymentsEntered > $total) {
            flash('err', 'Advance + second payment cannot be greater than the quotation total.');
            redirect('quotations.php');
        }

        $finalPaymentAmount = max(0, $total - $paymentsEntered);

        /* ---------- Quotation number ---------- */
        $year = date('Y');

        $stmt = $pdo->prepare(
            "SELECT quotation_number
             FROM quotations
             WHERE quotation_number LIKE ?
             ORDER BY id DESC
             LIMIT 1"
        );

        $stmt->execute(["QTN-$year-%"]);
        $lastNumber = $stmt->fetchColumn();

        if ($lastNumber && preg_match('/QTN-' . $year . '-(\d+)/', $lastNumber, $m)) {
            $next = ((int)$m[1]) + 1;
        } else {
            $next = 1;
        }

        $quotationNumber = 'QTN-' . $year . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);

        /* ---------- Save ---------- */
        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO quotations
                (
                    quotation_number,
                    client_name,
                    client_phone,
                    client_email,
                    client_address,
                    quotation_date,
                    valid_until,
                    subtotal,
                    discount,
                    advance_amount,
                    advance_date,
                    second_payment_amount,
                    second_payment_date,
                    final_payment_amount,
                    final_payment_date,
                    total,
                    notes,
                    terms,
                    status,
                    created_by
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)"
            );

            $createdBy = isset($me['id']) ? (int)$me['id'] : null;

            $stmt->execute([
                $quotationNumber,
                $clientName,
                $clientPhone ?: null,
                $clientEmail ?: null,
                $clientAddress ?: null,
                $quotationDate,
                $validUntil ?: null,
                $subtotal,
                $discount,
                $advanceAmount,
                $advanceDate ?: null,
                $secondPaymentAmount,
                $secondPaymentDate ?: null,
                $finalPaymentAmount,
                $finalPaymentDate ?: null,
                $total,
                $notes ?: null,
                $terms ?: null,
                $createdBy
            ]);

            $quotationId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                "INSERT INTO quotation_items
                (
                    quotation_id,
                    package_id,
                    description,
                    quantity,
                    unit_price,
                    total
                )
                VALUES (?, ?, ?, ?, ?, ?)"
            );

            foreach ($items as $item) {
                $itemStmt->execute([
                    $quotationId,
                    $item['package_id'],
                    $item['description'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total']
                ]);
            }

            $pdo->commit();

            flash('ok', "Quotation {$quotationNumber} created successfully.");
            redirect('quotations.php?view=' . $quotationId);

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            flash('err', 'Could not create quotation. Please try again.');
            redirect('quotations.php');
        }
    }
}


/* =====================================================
   Load packages
   ===================================================== */
$packages = $pdo->query(
    "SELECT p.id, p.name, p.description, p.price, p.duration,
            s.name AS service_name
     FROM packages p
     JOIN services s ON s.id = p.service_id
     WHERE p.status = 'active'
     ORDER BY s.name, p.name"
)->fetchAll();


/* =====================================================
   View quotation
   ===================================================== */
$viewId = (int)($_GET['view'] ?? 0);
$quotation = null;
$quotationItems = [];

/* =====================================================
   Stamp image — embed from the existing website file
   so it works in browser preview and Print / Save PDF.
   ===================================================== */
$stampFile = __DIR__ . '/../assets/images/stamp1.png';
$stampDataUri = null;

if (is_file($stampFile) && is_readable($stampFile)) {
    $stampBinary = @file_get_contents($stampFile);
    if ($stampBinary !== false) {
        $stampDataUri = 'data:image/png;base64,' . base64_encode($stampBinary);
    }
}

if ($viewId > 0) {

    $stmt = $pdo->prepare(
        "SELECT *
         FROM quotations
         WHERE id = ?
         LIMIT 1"
    );

    $stmt->execute([$viewId]);
    $quotation = $stmt->fetch();

    if ($quotation) {

        $stmt = $pdo->prepare(
            "SELECT qi.*, p.name AS package_name
             FROM quotation_items qi
             LEFT JOIN packages p ON p.id = qi.package_id
             WHERE qi.quotation_id = ?
             ORDER BY qi.id"
        );

        $stmt->execute([$viewId]);
        $quotationItems = $stmt->fetchAll();
    }
}


/* =====================================================
   Edit quotation
   ===================================================== */
$editId = (int)($_GET['edit'] ?? 0);
$editQuotation = null;
$editItems = [];

if ($editId > 0) {

    $stmt = $pdo->prepare(
        "SELECT *
         FROM quotations
         WHERE id = ?
         LIMIT 1"
    );
    $stmt->execute([$editId]);
    $editQuotation = $stmt->fetch();

    if (!$editQuotation) {
        flash('err', 'Quotation not found.');
        redirect('quotations.php');
    }

    $stmt = $pdo->prepare(
        "SELECT qi.*, p.name AS package_name
         FROM quotation_items qi
         LEFT JOIN packages p ON p.id = qi.package_id
         WHERE qi.quotation_id = ?
         ORDER BY qi.id"
    );
    $stmt->execute([$editId]);
    $editItems = $stmt->fetchAll();
}

$isEditing = ($editQuotation !== null);
$formQuotation = $isEditing ? $editQuotation : [
    'client_name' => '',
    'client_phone' => '',
    'client_email' => '',
    'client_address' => '',
    'quotation_date' => date('Y-m-d'),
    'valid_until' => '',
    'discount' => 0,
    'advance_amount' => 0,
    'advance_date' => '',
    'second_payment_amount' => 0,
    'second_payment_date' => '',
    'final_payment_date' => '',
    'notes' => '',
    'terms' => "50% deposit is required to confirm the booking.\nBalance is payable according to the agreed payment schedule."
];

$formItems = $isEditing && $editItems ? $editItems : [[
    'package_id' => null,
    'description' => '',
    'quantity' => 1,
    'unit_price' => 0
]];


/* =====================================================
   Existing quotations
   ===================================================== */
$quotations = $pdo->query(
    "SELECT *
     FROM quotations
     ORDER BY id DESC
     LIMIT 100"
)->fetchAll();


require __DIR__ . '/includes/admin-header.php';
?>

<style>
  .quotation-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:1rem;
  }

  .quotation-grid .full {
    grid-column:1 / -1;
  }

  .quotation-items {
    width:100%;
    border-collapse:collapse;
  }

  .quotation-items th {
    text-align:left;
    padding:.8rem;
    font-size:.72rem;
    text-transform:uppercase;
    letter-spacing:.1em;
    color:#7a716a;
    background:#0e0d0c;
  }

  .quotation-items td {
    padding:.6rem;
    border-top:1px solid #262220;
  }

  .quotation-items input,
  .quotation-items select,
  .quotation-items textarea {
    width:100%;
    box-sizing:border-box;
  }

  .quotation-items textarea {
    min-height:90px;
    resize:vertical;
  }

  .quotation-preview {
    background:#fff;
    color:#171513;
    max-width:900px;
    margin:2rem auto;
    padding:3rem;
    box-shadow:0 15px 50px rgba(0,0,0,.35);
  }

  .quotation-preview h1,
  .quotation-preview h2,
  .quotation-preview h3 {
    color:#171513;
  }

  .quote-head {
    display:flex;
    justify-content:space-between;
    gap:2rem;
    border-bottom:2px solid #171513;
    padding-bottom:1.5rem;
    margin-bottom:1.5rem;
  }

  .quote-logo {
    display:block;
    width:auto;
    max-width:220px;
    height:auto;
    max-height:100px;
    object-fit:contain;
  }

  .quote-meta {
    text-align:right;
  }

  .quote-client {
    margin:1.5rem 0;
    padding:1rem;
    background:#f5f3ef;
  }

  .quote-table {
    width:100%;
    border-collapse:collapse;
    margin-top:1rem;
  }

  .quote-table th {
    background:#171513;
    color:#fff;
    padding:.7rem;
    text-align:left;
  }

  .quote-table td {
    padding:.7rem;
    border-bottom:1px solid #ddd;
  }

  .quote-total {
    margin-left:auto;
    width:300px;
    margin-top:1rem;
  }

  .quote-total div {
    display:flex;
    justify-content:space-between;
    padding:.35rem 0;
  }

  .quote-total .grand {
    font-size:1.15rem;
    font-weight:700;
    border-top:2px solid #171513;
    padding-top:.7rem;
  }

  .quote-footer {
    margin-top:3rem;
    display:flex;
    justify-content:space-between;
    gap:2rem;
    align-items:flex-end;
  }

  .quote-stamp {
    display:block;
    width:200px;
    max-width:200px;
    height:auto;
    max-height:150px;
    object-fit:contain;
    margin:0 auto .5rem;
  }

  .quote-stamp-wrap {
    min-width:220px;
    text-align:center;
  }


  .payment-schedule {
    margin-top:2rem;
    border:1px solid #ddd;
    border-radius:10px;
    overflow:hidden;
  }

  .payment-schedule h3 {
    margin:0;
    padding:.8rem 1rem;
    background:#171513;
    color:#fff;
    font-size:.9rem;
    letter-spacing:.04em;
    text-transform:uppercase;
  }

  .payment-schedule table {
    width:100%;
    border-collapse:collapse;
  }

  .payment-schedule th,
  .payment-schedule td {
    padding:.65rem .8rem;
    border-bottom:1px solid #ddd;
    text-align:left;
  }

  .payment-schedule th {
    background:#f5f3ef;
    font-size:.72rem;
    text-transform:uppercase;
    letter-spacing:.06em;
  }

  .payment-schedule tr:last-child td {
    border-bottom:0;
  }

  .payment-schedule .balance-row td {
    font-weight:700;
    background:#faf9f7;
  }

  .payment-form {
    margin:1.5rem 0;
    padding:1rem;
    border:1px solid #262220;
    border-radius:10px;
    background:rgba(255,255,255,.02);
  }

  .payment-form h3 {
    margin-top:0;
  }

  .payment-form-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:1rem;
  }

  .payment-form-grid .full {
    grid-column:1 / -1;
  }

  .payment-help {
    color:#7a716a;
    font-size:.78rem;
    margin-top:.25rem;
  }

  .description-preview {
    white-space:normal;
    line-height:1.55;
  }

  .description-preview div {
    margin:.1rem 0;
  }

  @media (max-width:700px) {
    .quotation-grid {
      grid-template-columns:1fr;
    }

    .quotation-grid .full {
      grid-column:auto;
    }

    .quotation-preview {
      padding:1.3rem;
    }

    .quote-head,
    .quote-footer {
      flex-direction:column;
    }

    .quote-meta {
      text-align:left;
    }

    .quote-total {
      width:100%;
    }
  }

  @media print {

    body {
      background:#fff !important;
    }

    .admin-topbar,
    .admin-side,
    .admin-backdrop,
    .admin-main > *:not(.quotation-preview) {
      display:none !important;
    }

    .admin-shell,
    .admin-main {
      padding:0 !important;
      margin:0 !important;
    }

    .quotation-preview {
      display:block !important;
      box-shadow:none;
      max-width:none;
      margin:0;
      padding:1cm;
      -webkit-print-color-adjust:exact;
      print-color-adjust:exact;
    }

    .quote-stamp {
      display:block !important;
      width:200px !important;
      max-width:200px !important;
      height:auto !important;
      max-height:150px !important;
    }
  }
</style>


<?php if ($m = flash('ok')): ?>
  <div class="alert alert-success"><?= e($m) ?></div>
<?php endif; ?>

<?php if ($m = flash('err')): ?>
  <div class="alert alert-error"><?= e($m) ?></div>
<?php endif; ?>


<!-- =====================================================
     VIEW QUOTATION
     ===================================================== -->
<?php if ($quotation): ?>

  <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1rem">
    <div>
      <h1 style="margin:0">Quotation <?= e($quotation['quotation_number']) ?></h1>
      <p style="color:#7a716a;margin:.3rem 0 0">
        <?= e($quotation['client_name']) ?>
      </p>
    </div>

    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
      <button class="btn btn-primary" onclick="window.print()">Print / Save PDF</button>
      <a class="btn btn-ghost" href="quotations.php">Back</a>
    </div>
  </div>


  <div class="quotation-preview">

    <div class="quote-head">

      <div>
        <img
          src="../assets/images/logo.png"
          alt="Imagination Nature Studio"
          class="quote-logo"
          onerror="this.style.display='none';"
        >

        <h2 style="margin:.8rem 0 .2rem">
          IMAGINATION NATURE STUDIO
        </h2>

        <div>
          <?= e(setting('address', BIZ_ADDRESS)) ?><br>
          <?= e(setting('phone_primary', BIZ_PHONE_1)) ?>

          <?php if (setting('phone_secondary', BIZ_PHONE_2)): ?>
            · <?= e(setting('phone_secondary', BIZ_PHONE_2)) ?>
          <?php endif; ?>

          <br>
          <?= e(setting('email_primary', BIZ_EMAIL)) ?>
        </div>
      </div>

      <div class="quote-meta">
        <h1 style="margin:0">QUOTATION</h1>
        <strong><?= e($quotation['quotation_number']) ?></strong>

        <div style="margin-top:.6rem">
          Date:
          <?= e(date('d M Y', strtotime($quotation['quotation_date']))) ?>
        </div>

        <?php if (!empty($quotation['valid_until'])): ?>
          <div>
            Valid Until:
            <?= e(date('d M Y', strtotime($quotation['valid_until']))) ?>
          </div>
        <?php endif; ?>
      </div>

    </div>


    <div class="quote-client">
      <strong>Prepared For</strong><br>

      <?= e($quotation['client_name']) ?>

      <?php if (!empty($quotation['client_phone'])): ?>
        <br><?= e($quotation['client_phone']) ?>
      <?php endif; ?>

      <?php if (!empty($quotation['client_email'])): ?>
        <br><?= e($quotation['client_email']) ?>
      <?php endif; ?>

      <?php if (!empty($quotation['client_address'])): ?>
        <br><?= e($quotation['client_address']) ?>
      <?php endif; ?>
    </div>


    <table class="quote-table">
      <thead>
        <tr>
          <th>Description</th>
          <th style="width:80px">Qty</th>
          <th style="width:150px">Unit Price</th>
          <th style="width:160px">Total</th>
        </tr>
      </thead>

      <tbody>
      <?php foreach ($quotationItems as $item): ?>
        <tr>
          <td class="description-preview"><?= nl2br(e($item['description'])) ?></td>
          <td><?= e($item['quantity']) ?></td>
          <td><?= quotation_money($item['unit_price']) ?> RWF</td>
          <td><?= quotation_money($item['total']) ?> RWF</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>


    <div class="quote-total">

      <div>
        <span>Subtotal</span>
        <strong><?= quotation_money($quotation['subtotal']) ?> RWF</strong>
      </div>

      <?php if ((float)$quotation['discount'] > 0): ?>
        <div>
          <span>Discount</span>
          <strong>-<?= quotation_money($quotation['discount']) ?> RWF</strong>
        </div>
      <?php endif; ?>

      <div class="grand">
        <span>GRAND TOTAL</span>
        <strong><?= quotation_money($quotation['total']) ?> RWF</strong>
      </div>

    </div>


    <div class="payment-schedule">
      <h3>Payment Schedule</h3>
      <table>
        <thead>
          <tr>
            <th>Payment</th>
            <th>Amount</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Advance / Booking Payment</td>
            <td><?= quotation_money($quotation['advance_amount'] ?? 0) ?> RWF</td>
            <td><?= !empty($quotation['advance_date']) ? e(date('d M Y', strtotime($quotation['advance_date']))) : '—' ?></td>
          </tr>
          <tr>
            <td>Second Payment</td>
            <td><?= quotation_money($quotation['second_payment_amount'] ?? 0) ?> RWF</td>
            <td><?= !empty($quotation['second_payment_date']) ? e(date('d M Y', strtotime($quotation['second_payment_date']))) : '—' ?></td>
          </tr>
          <tr class="balance-row">
            <td>Final Payment / Balance</td>
            <td><?= quotation_money($quotation['final_payment_amount'] ?? 0) ?> RWF</td>
            <td><?= !empty($quotation['final_payment_date']) ? e(date('d M Y', strtotime($quotation['final_payment_date']))) : 'After project completion' ?></td>
          </tr>
        </tbody>
      </table>
    </div>


    <?php if (!empty($quotation['notes'])): ?>
      <div style="margin-top:2rem">
        <h3>Notes</h3>
        <div><?= nl2br(e($quotation['notes'])) ?></div>
      </div>
    <?php endif; ?>


    <?php if (!empty($quotation['terms'])): ?>
      <div style="margin-top:1.5rem">
        <h3>Terms & Conditions</h3>
        <div><?= nl2br(e($quotation['terms'])) ?></div>
      </div>
    <?php endif; ?>


    <div class="quote-footer">

      <div>
        <strong>Imagination Nature Studio</strong><br>
        Kigali · Rwanda
        <br><br>
        Authorized Signature
      </div>

      <div class="quote-stamp-wrap">
        <?php if ($stampDataUri): ?>
          <img
            src="<?= e($stampDataUri) ?>"
            alt="Official Studio Stamp"
            class="quote-stamp"
          >
        <?php else: ?>
          <div style="
            width:200px;
            height:120px;
            margin:0 auto .5rem;
            border:1px dashed #aaa;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:.75rem;
            color:#777;
          ">
            Stamp image not found
          </div>
        <?php endif; ?>

        <div style="font-size:.8rem;font-weight:600;">
          Official Studio Stamp
        </div>
      </div>

    </div>

  </div>


<?php else: ?>


<!-- =====================================================
     CREATE QUOTATION
     ===================================================== -->

<div style="margin-bottom:1.3rem">
  <h1 style="margin:0 0 .3rem">
    <?= $isEditing ? 'Edit Quotation' : 'Quotations' ?>
  </h1>
  <p style="color:#7a716a;margin:0">
    <?= $isEditing ? 'Update the quotation details below.' : 'Create a professional quotation for your client.' ?>
  </p>
</div>


<form method="post" class="card" style="padding:1.5rem">

  <?= csrf_field() ?>

  <input type="hidden" name="action" value="<?= $isEditing ? 'update' : 'create' ?>">
  <?php if ($isEditing): ?>
    <input type="hidden" name="quotation_id" value="<?= (int)$editQuotation['id'] ?>">
  <?php endif; ?>


  <h3 style="font-family:var(--font-serif);margin-top:0">
    Client Information
  </h3>

  <div class="quotation-grid">

    <div>
      <label>Client Name *</label>
      <input
        type="text"
        name="client_name"
        required
        value="<?= e($formQuotation['client_name']) ?>"
        placeholder="Client full name"
      >
    </div>

    <div>
      <label>Phone</label>
      <input
        type="text"
        name="client_phone"
        value="<?= e($formQuotation['client_phone']) ?>"
        placeholder="+250 ..."
      >
    </div>

    <div>
      <label>Email</label>
      <input
        type="email"
        name="client_email"
        value="<?= e($formQuotation['client_email']) ?>"
        placeholder="client@email.com"
      >
    </div>

    <div>
      <label>Address</label>
      <input
        type="text"
        name="client_address"
        value="<?= e($formQuotation['client_address']) ?>"
        placeholder="Kigali, Rwanda"
      >
    </div>

    <div>
      <label>Quotation Date</label>
      <input
        type="date"
        name="quotation_date"
        value="<?= e($formQuotation['quotation_date']) ?>"
        required
      >
    </div>

    <div>
      <label>Valid Until</label>
      <input
        type="date"
        name="valid_until"
        value="<?= e($formQuotation['valid_until']) ?>"
      >
    </div>

  </div>


  <hr style="border-color:#262220;margin:2rem 0">


  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h3 style="font-family:var(--font-serif);margin:0">
      Quotation Items
    </h3>

    <button
      type="button"
      class="btn btn-sm btn-primary"
      onclick="addQuotationRow()"
    >
      + Add Item
    </button>
  </div>


  <div style="overflow-x:auto">

    <table class="quotation-items">

      <thead>
        <tr>
          <th>Package / Service</th>
          <th>Description</th>
          <th style="width:90px">Qty</th>
          <th style="width:150px">Unit Price</th>
          <th style="width:150px">Total</th>
          <th style="width:60px"></th>
        </tr>
      </thead>

      <tbody id="quotationItemsBody">

      <?php foreach ($formItems as $formItem): ?>
        <tr class="quote-row">

          <td>
            <select name="package_id[]" class="package-select">
              <option value="">Manual item</option>
              <?php foreach ($packages as $p): ?>
                <option
                  value="<?= (int)$p['id'] ?>"
                  data-name="<?= e($p['name']) ?>"
                  data-description="<?= e($p['description']) ?>"
                  data-price="<?= e($p['price']) ?>"
                  <?= ((int)($formItem['package_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>
                >
                  <?= e($p['service_name']) ?> — <?= e($p['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>

          <td>
            <textarea
              name="description[]"
              class="item-description"
              rows="4"
              placeholder="Write each service/deliverable on a new line..."
              required
            ><?= e($formItem['description'] ?? '') ?></textarea>
          </td>

          <td>
            <input
              type="number"
              name="quantity[]"
              class="item-qty"
              value="<?= e($formItem['quantity'] ?? 1) ?>"
              min="0.01"
              step="0.01"
            >
          </td>

          <td>
            <input
              type="number"
              name="unit_price[]"
              class="item-price"
              value="<?= e($formItem['unit_price'] ?? 0) ?>"
              min="0"
              step="1000"
            >
          </td>

          <td>
            <strong class="item-total">0 RWF</strong>
          </td>

          <td>
            <button
              type="button"
              class="btn btn-sm btn-ghost"
              onclick="removeQuotationRow(this)"
            >×</button>
          </td>

        </tr>
      <?php endforeach; ?>

      </tbody>

    </table>

  </div>


  <div style="max-width:400px;margin:1.5rem 0 1.5rem auto">

    <div style="display:flex;justify-content:space-between;padding:.4rem 0">
      <span>Subtotal</span>
      <strong id="subtotalDisplay">0 RWF</strong>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;padding:.4rem 0">

      <label style="margin:0">
        Discount
      </label>

      <input
        type="number"
        name="discount"
        id="discount"
        value="<?= e($formQuotation['discount']) ?>"
        min="0"
        step="1000"
        style="width:160px"
      >

    </div>

    <div style="display:flex;justify-content:space-between;padding:.8rem 0;border-top:2px solid #262220;font-size:1.1rem">

      <strong>GRAND TOTAL</strong>

      <strong id="grandTotalDisplay">
        0 RWF
      </strong>

    </div>

  </div>


  <div class="payment-form">
    <h3 style="font-family:var(--font-serif);margin-bottom:.3rem">Payment Schedule</h3>
    <p class="payment-help">Enter the agreed payment amounts and dates. The final balance is calculated automatically from the Grand Total.</p>

    <div class="payment-form-grid">
      <div>
        <label>Advance / Booking Amount</label>
        <input type="number" name="advance_amount" id="advance_amount" value="<?= e($formQuotation['advance_amount']) ?>" min="0" step="1000">
      </div>

      <div>
        <label>Advance / Booking Date</label>
        <input type="date" name="advance_date" id="advance_date" value="<?= e($formQuotation['advance_date']) ?>">
      </div>

      <div>
        <label>Second Payment Amount</label>
        <input type="number" name="second_payment_amount" id="second_payment_amount" value="<?= e($formQuotation['second_payment_amount']) ?>" min="0" step="1000">
      </div>

      <div>
        <label>Second Payment Date</label>
        <input type="date" name="second_payment_date" id="second_payment_date" value="<?= e($formQuotation['second_payment_date']) ?>">
      </div>

      <div>
        <label>Final Payment / Balance</label>
        <input type="text" id="finalPaymentDisplay" value="0 RWF" readonly>
        <div class="payment-help">Automatically calculated: Grand Total − Advance − Second Payment.</div>
      </div>

      <div>
        <label>Final Payment Date</label>
        <input type="date" name="final_payment_date" id="final_payment_date" value="<?= e($formQuotation['final_payment_date']) ?>">
      </div>
    </div>
  </div>


  <div class="quotation-grid">

    <div class="full">
      <label>Notes</label>
      <textarea
        name="notes"
        rows="4"
        placeholder="Additional information for the client..."
      ><?= e($formQuotation['notes']) ?></textarea>
    </div>

    <div class="full">
      <label>Terms & Conditions</label>
      <textarea
        name="terms"
        rows="5"
        placeholder="Payment terms, cancellation policy, delivery details..."
      ><?= e($formQuotation['terms']) ?></textarea>
    </div>

  </div>


  <div style="margin-top:1.5rem;display:flex;gap:.7rem;flex-wrap:wrap">

    <button class="btn btn-primary" type="submit">
      <?= $isEditing ? 'Update Quotation' : 'Save Quotation' ?>
    </button>

    <a class="btn btn-ghost" href="quotations.php">
      <?= $isEditing ? 'Cancel' : 'Clear' ?>
    </a>

  </div>

</form>


<!-- =====================================================
     RECENT QUOTATIONS
     ===================================================== -->

<div style="margin-top:2rem">

  <h2 style="font-family:var(--font-serif);font-size:1.2rem">
    Recent Quotations
  </h2>

  <div class="admin-table-wrap" style="overflow-x:auto">

    <table class="pkg-table">

      <thead>
        <tr>
          <th>Quotation</th>
          <th>Client</th>
          <th>Date</th>
          <th>Total</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>

      <tbody>

      <?php if (!$quotations): ?>

        <tr>
          <td colspan="6" style="text-align:center;color:#7a716a">
            No quotations yet.
          </td>
        </tr>

      <?php else: ?>

        <?php foreach ($quotations as $q): ?>

          <tr>

            <td>
              <strong><?= e($q['quotation_number']) ?></strong>
            </td>

            <td>
              <?= e($q['client_name']) ?>
            </td>

            <td>
              <?= e(date('d M Y', strtotime($q['quotation_date']))) ?>
            </td>

            <td>
              <?= quotation_money($q['total']) ?> RWF
            </td>

            <td>
              <span class="pkg-status <?= e($q['status']) ?>">
                <?= e($q['status']) ?>
              </span>
            </td>

            <td>
              <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                <a
                  class="btn btn-sm btn-primary"
                  href="quotations.php?view=<?= (int)$q['id'] ?>"
                >View</a>

                <a
                  class="btn btn-sm btn-ghost"
                  href="quotations.php?edit=<?= (int)$q['id'] ?>"
                >Edit</a>

                <form method="post" style="display:inline"
                      onsubmit="return confirm('Delete quotation <?= e($q['quotation_number']) ?>? This cannot be undone.');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="quotation_id" value="<?= (int)$q['id'] ?>">
                  <button type="submit"
                          class="btn btn-sm btn-ghost"
                          style="color:#b42318;border-color:#b42318">
                    Delete
                  </button>
                </form>
              </div>
            </td>

          </tr>

        <?php endforeach; ?>

      <?php endif; ?>

      </tbody>

    </table>

  </div>

</div>


<?php endif; ?>


<script>
function money(value) {
    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 0
    }).format(value) + ' RWF';
}


function calculateQuotation() {

    let subtotal = 0;

    document.querySelectorAll('.quote-row').forEach(function(row) {

        const qtyInput = row.querySelector('.item-qty');
        const priceInput = row.querySelector('.item-price');
        const totalEl = row.querySelector('.item-total');

        if (!qtyInput || !priceInput || !totalEl) return;

        const qty = parseFloat(qtyInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;

        const total = qty * price;

        totalEl.textContent = money(total);

        subtotal += total;
    });


    const discountInput = document.getElementById('discount');

    const discount = discountInput
        ? parseFloat(discountInput.value) || 0
        : 0;

    const grandTotal = Math.max(0, subtotal - discount);

    const subtotalDisplay = document.getElementById('subtotalDisplay');
    const grandTotalDisplay = document.getElementById('grandTotalDisplay');

    if (subtotalDisplay) {
        subtotalDisplay.textContent = money(subtotal);
    }

    if (grandTotalDisplay) {
        grandTotalDisplay.textContent = money(grandTotal);
    }

    const advanceInput = document.getElementById('advance_amount');
    const secondInput = document.getElementById('second_payment_amount');
    const finalDisplay = document.getElementById('finalPaymentDisplay');

    const advance = advanceInput ? Math.max(0, parseFloat(advanceInput.value) || 0) : 0;
    const second = secondInput ? Math.max(0, parseFloat(secondInput.value) || 0) : 0;
    const finalBalance = Math.max(0, grandTotal - advance - second);

    if (finalDisplay) {
        finalDisplay.value = money(finalBalance);
    }
}


function bindQuotationRow(row) {

    const packageSelect = row.querySelector('.package-select');

    if (packageSelect) {

        packageSelect.addEventListener('change', function() {

            const option =
                this.options[this.selectedIndex];

            const description =
                row.querySelector('.item-description');

            const price =
                row.querySelector('.item-price');

            if (this.value) {

                if (description) {
                    description.value =
                        option.dataset.description ||
                        option.dataset.name ||
                        '';
                }

                if (price) {
                    price.value =
                        option.dataset.price || 0;
                }

            }

            calculateQuotation();
        });
    }


    row.querySelectorAll('.item-qty, .item-price')
        .forEach(function(input) {
            input.addEventListener(
                'input',
                calculateQuotation
            );
        });
}


function addQuotationRow() {

    const tbody =
        document.getElementById('quotationItemsBody');

    if (!tbody) return;

    const firstRow =
        tbody.querySelector('.quote-row');

    const newRow =
        firstRow.cloneNode(true);

    newRow.querySelector('.package-select').value = '';

    newRow.querySelector('.item-description').value = '';

    newRow.querySelector('.item-qty').value = '1';

    newRow.querySelector('.item-price').value = '0';

    newRow.querySelector('.item-total').textContent = '0 RWF';

    tbody.appendChild(newRow);

    bindQuotationRow(newRow);

    calculateQuotation();
}


function removeQuotationRow(button) {

    const tbody =
        document.getElementById('quotationItemsBody');

    const rows =
        tbody.querySelectorAll('.quote-row');

    if (rows.length <= 1) {
        return;
    }

    button.closest('.quote-row').remove();

    calculateQuotation();
}


document.addEventListener('DOMContentLoaded', function() {

    document
        .querySelectorAll('.quote-row')
        .forEach(bindQuotationRow);

    const discount =
        document.getElementById('discount');

    if (discount) {
        discount.addEventListener(
            'input',
            calculateQuotation
        );
    }

    ['advance_amount', 'second_payment_amount'].forEach(function(id) {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', calculateQuotation);
        }
    });

    calculateQuotation();
});
</script>


<?php require __DIR__ . '/includes/admin-footer.php'; ?>