<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (empty($_SESSION['customer_view_csrf'])) {
    $_SESSION['customer_view_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['customer_view_csrf'];

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function money($v): string {
    return number_format((float)$v, 0) . ' RWF';
}
function wa_link(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === '') return '#';
    if (str_starts_with($digits, '0')) $digits = '250' . substr($digits, 1);
    elseif (!str_starts_with($digits, '250')) $digits = '250' . $digits;
    return 'https://wa.me/' . $digits;
}

$isNew = isset($_GET['new']) && $_GET['new'] === '1';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($csrf, $token)) {
        flash('error', 'Security token expired. Please try again.');
        redirect('customers.php');
    }

    $action = (string)($_POST['action'] ?? '');
    $customerId = (int)($_POST['customer_id'] ?? 0);

    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $whatsapp = trim((string)($_POST['whatsapp'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $location = trim((string)($_POST['location'] ?? ''));
    $company = trim((string)($_POST['company'] ?? ''));

    if ($fullName === '') {
        flash('error', 'Customer name is required.');
        redirect($customerId > 0 ? 'customer-view.php?id='.$customerId : 'customer-view.php?new=1');
    }

    try {
        if ($action === 'create') {
            $stmt = $pdo->prepare("
                INSERT INTO customers
                (full_name, phone, whatsapp, email, location, company, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$fullName, $phone, $whatsapp, $email, $location, $company]);
            $newId = (int)$pdo->lastInsertId();

            flash('success', 'Customer created successfully.');
            redirect('customer-view.php?id='.$newId);
        }

        if ($action === 'update' && $customerId > 0) {
            $stmt = $pdo->prepare("
                UPDATE customers
                SET full_name=?, phone=?, whatsapp=?, email=?, location=?, company=?
                WHERE id=?
                LIMIT 1
            ");
            $stmt->execute([$fullName, $phone, $whatsapp, $email, $location, $company, $customerId]);

            flash('success', 'Customer information updated.');
            redirect('customer-view.php?id='.$customerId);
        }

        flash('error', 'Invalid customer action.');
        redirect('customers.php');
    } catch (Throwable $e) {
        error_log('[customer save] '.$e->getMessage());
        flash('error', 'Could not save customer. Please check the information and try again.');
        redirect($customerId > 0 ? 'customer-view.php?id='.$customerId : 'customer-view.php?new=1');
    }
}

$customer = null;

if (!$isNew) {
    if ($id <= 0) {
        flash('error', 'Customer not found.');
        redirect('customers.php');
    }

    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        flash('error', 'Customer not found.');
        redirect('customers.php');
    }
}

if ($isNew) {
    $customer = [
        'id'=>0,'full_name'=>'','phone'=>'','whatsapp'=>'','email'=>'',
        'location'=>'','company'=>'','created_at'=>''
    ];
}

$bookings = [];
$payments = [];
$totals = ['booking_value'=>0,'paid'=>0,'balance'=>0,'booking_count'=>0];

if (!$isNew) {
    $stmt = $pdo->prepare("
        SELECT
            b.id, b.booking_number, b.booking_date, b.start_time,
            b.total_amount, b.deposit_amount, b.amount_paid, b.balance,
            b.payment_method, b.payment_status, b.booking_status,
            s.name AS service_name,
            p.name AS package_name
        FROM bookings b
        LEFT JOIN services s ON s.id=b.service_id
        LEFT JOIN packages p ON p.id=b.package_id
        WHERE b.customer_id=?
        ORDER BY b.booking_date DESC, b.id DESC
    ");
    $stmt->execute([$id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bookings as $b) {
        if ($b['booking_status'] !== 'cancelled') {
            $totals['booking_value'] += (float)$b['total_amount'];
            $totals['paid'] += (float)$b['amount_paid'];
            $totals['balance'] += max(0,(float)$b['balance']);
        }
    }
    $totals['booking_count'] = count($bookings);

    $stmt = $pdo->prepare("
        SELECT
            pay.id, pay.amount, pay.payment_method, pay.payment_status,
            pay.reference, pay.provider_reference, pay.payment_date,
            pay.verified_at, b.booking_number
        FROM payments pay
        INNER JOIN bookings b ON b.id=pay.booking_id
        WHERE b.customer_id=?
        ORDER BY pay.payment_date DESC, pay.id DESC
        LIMIT 100
    ");
    $stmt->execute([$id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require __DIR__ . '/includes/admin-header.php';
?>
<style>
.cv-wrap{max-width:1450px;margin:0 auto;padding:24px}.cv-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:20px}
.cv-title{font-size:30px;font-weight:900;margin:0}.cv-sub{color:#9ca3af;margin-top:6px}.cv-actions{display:flex;gap:10px;flex-wrap:wrap}
.cv-btn{border-radius:11px;padding:10px 15px;text-decoration:none;font-weight:900;border:1px solid #344054;background:#111827;color:#fff;cursor:pointer}.cv-btn-main{background:#fff;color:#111827;border-color:#fff}
.cv-grid{display:grid;grid-template-columns:340px 1fr;gap:18px}.cv-card{background:#111827;border:1px solid #253044;border-radius:18px;padding:20px;box-shadow:0 12px 30px rgba(0,0,0,.15)}
.cv-profile{text-align:center}.cv-avatar{width:78px;height:78px;border-radius:50%;background:#1f2937;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 13px}
.cv-name{font-size:22px;font-weight:900}.cv-contact{color:#9ca3af;font-size:13px;margin-top:6px;line-height:1.7}.cv-links{display:flex;gap:8px;justify-content:center;margin-top:14px;flex-wrap:wrap}
.cv-stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px}.cv-stat{background:#111827;border:1px solid #253044;border-radius:16px;padding:16px}.cv-label{color:#9ca3af;font-size:11px;text-transform:uppercase;font-weight:800}.cv-value{font-size:20px;font-weight:900;margin-top:6px}
.cv-green{color:#34d399}.cv-orange{color:#fbbf24}.cv-blue{color:#60a5fa}
.cv-section-title{font-size:17px;font-weight:900;margin:0 0 14px}.cv-table-wrap{overflow-x:auto}.cv-table{width:100%;border-collapse:collapse;min-width:850px}.cv-table th{font-size:11px;text-transform:uppercase;color:#9ca3af;text-align:left;padding:12px;border-bottom:1px solid #253044}.cv-table td{padding:12px;border-bottom:1px solid #202a3a}.cv-pill{padding:5px 8px;border-radius:999px;background:#1f2937;font-size:10px;font-weight:900}
.cv-form{display:grid;grid-template-columns:1fr 1fr;gap:13px}.cv-field{display:flex;flex-direction:column;gap:6px}.cv-field.full{grid-column:1/-1}.cv-field label{font-size:12px;color:#9ca3af;font-weight:800}.cv-field input{background:#0b1220;border:1px solid #344054;color:#fff;border-radius:10px;padding:12px}.cv-save{grid-column:1/-1;background:#fff;color:#111827;border:0;border-radius:11px;padding:12px;font-weight:900;cursor:pointer}
.cv-back{color:#9ca3af;text-decoration:none}.cv-muted{color:#9ca3af}.cv-danger{color:#f87171}
@media(max-width:900px){.cv-grid{grid-template-columns:1fr}.cv-stat-grid{grid-template-columns:1fr 1fr}}@media(max-width:600px){.cv-wrap{padding:15px}.cv-head{flex-direction:column}.cv-form{grid-template-columns:1fr}.cv-field.full{grid-column:auto}.cv-save{grid-column:auto}.cv-stat-grid{grid-template-columns:1fr}}
</style>

<div class="cv-wrap">
    <div class="cv-head">
        <div>
            <a class="cv-back" href="customers.php">← Back to Customers</a>
            <h1 class="cv-title"><?= $isNew ? '＋ Add Customer' : '👤 '.h($customer['full_name']) ?></h1>
            <div class="cv-sub"><?= $isNew ? 'Create a customer profile.' : 'Complete customer history and financial overview.' ?></div>
        </div>
        <?php if(!$isNew): ?>
        <div class="cv-actions">
            <?php if(!empty($customer['phone'])): ?><a class="cv-btn" href="tel:<?=h($customer['phone'])?>">📞 Call</a><?php endif; ?>
            <?php if(!empty($customer['whatsapp'])): ?><a class="cv-btn" href="<?=h(wa_link($customer['whatsapp']))?>" target="_blank">💬 WhatsApp</a><?php endif; ?>
            <a class="cv-btn cv-btn-main" href="#edit">✏️ Edit Customer</a>
        </div>
        <?php endif; ?>
    </div>

    <?php if($isNew): ?>
        <div class="cv-card">
            <h2 class="cv-section-title">Customer Information</h2>
            <form class="cv-form" method="post">
                <input type="hidden" name="csrf" value="<?=h($csrf)?>">
                <input type="hidden" name="action" value="create">
                <div class="cv-field"><label>Full Name *</label><input name="full_name" required></div>
                <div class="cv-field"><label>Phone</label><input name="phone"></div>
                <div class="cv-field"><label>WhatsApp</label><input name="whatsapp"></div>
                <div class="cv-field"><label>Email</label><input type="email" name="email"></div>
                <div class="cv-field"><label>Location</label><input name="location"></div>
                <div class="cv-field"><label>Company</label><input name="company"></div>
                <button class="cv-save" type="submit">Create Customer</button>
            </form>
        </div>
    <?php else: ?>

    <div class="cv-grid">
        <div>
            <div class="cv-card cv-profile">
                <div class="cv-avatar">👤</div>
                <div class="cv-name"><?=h($customer['full_name'])?></div>
                <div class="cv-contact">
                    <?=h($customer['phone'] ?: 'No phone')?><br>
                    <?=h($customer['email'] ?: 'No email')?><br>
                    <?=h($customer['location'] ?: 'No location')?>
                </div>
                <div class="cv-links">
                    <?php if(!empty($customer['phone'])): ?><a class="cv-btn" href="tel:<?=h($customer['phone'])?>">Call</a><?php endif; ?>
                    <?php if(!empty($customer['whatsapp'])): ?><a class="cv-btn" target="_blank" href="<?=h(wa_link($customer['whatsapp']))?>">WhatsApp</a><?php endif; ?>
                </div>
            </div>

            <div class="cv-card" style="margin-top:18px" id="edit">
                <h2 class="cv-section-title">Edit Customer</h2>
                <form class="cv-form" method="post">
                    <input type="hidden" name="csrf" value="<?=h($csrf)?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="customer_id" value="<?=((int)$customer['id'])?>">
                    <div class="cv-field full"><label>Full Name *</label><input name="full_name" value="<?=h($customer['full_name'])?>" required></div>
                    <div class="cv-field"><label>Phone</label><input name="phone" value="<?=h($customer['phone'])?>"></div>
                    <div class="cv-field"><label>WhatsApp</label><input name="whatsapp" value="<?=h($customer['whatsapp'])?>"></div>
                    <div class="cv-field"><label>Email</label><input type="email" name="email" value="<?=h($customer['email'])?>"></div>
                    <div class="cv-field"><label>Location</label><input name="location" value="<?=h($customer['location'])?>"></div>
                    <div class="cv-field full"><label>Company</label><input name="company" value="<?=h($customer['company'])?>"></div>
                    <button class="cv-save" type="submit">Save Customer</button>
                </form>
            </div>
        </div>

        <div>
            <div class="cv-stat-grid">
                <div class="cv-stat"><div class="cv-label">Bookings</div><div class="cv-value"><?=number_format($totals['booking_count'])?></div></div>
                <div class="cv-stat"><div class="cv-label">Paid</div><div class="cv-value cv-green"><?=h(money($totals['paid']))?></div></div>
                <div class="cv-stat"><div class="cv-label">Balance</div><div class="cv-value cv-orange"><?=h(money($totals['balance']))?></div></div>
            </div>

            <div class="cv-card">
                <h2 class="cv-section-title">📅 Booking History</h2>
                <div class="cv-table-wrap">
                    <?php if(!$bookings): ?>
                        <div class="cv-muted">No bookings found for this customer.</div>
                    <?php else: ?>
                    <table class="cv-table">
                        <thead><tr><th>Booking</th><th>Date</th><th>Service</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach($bookings as $b): ?>
                            <tr>
                                <td><strong><?=h($b['booking_number'])?></strong></td>
                                <td><?=h($b['booking_date'])?></td>
                                <td><?=h($b['service_name'] ?: '—')?><?=!empty($b['package_name']) ? '<br><small class="cv-muted">'.h($b['package_name']).'</small>' : ''?></td>
                                <td><?=h(money($b['total_amount']))?></td>
                                <td class="cv-green"><?=h(money($b['amount_paid']))?></td>
                                <td class="cv-orange"><?=h(money($b['balance']))?></td>
                                <td><span class="cv-pill"><?=h($b['booking_status'])?></span></td>
                                <td><a class="cv-btn" href="booking-view.php?id=<?=((int)$b['id'])?>">Open</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="cv-card" style="margin-top:18px">
                <h2 class="cv-section-title">💰 Payment History</h2>
                <div class="cv-table-wrap">
                    <?php if(!$payments): ?>
                        <div class="cv-muted">No payment records found.</div>
                    <?php else: ?>
                    <table class="cv-table">
                        <thead><tr><th>Date</th><th>Booking</th><th>Amount</th><th>Method</th><th>Status</th><th>Reference</th></tr></thead>
                        <tbody>
                        <?php foreach($payments as $p): ?>
                            <tr>
                                <td><?=h($p['payment_date'])?></td>
                                <td><?=h($p['booking_number'])?></td>
                                <td class="cv-green"><strong><?=h(money($p['amount']))?></strong></td>
                                <td><?=h($p['payment_method'] ?: '—')?></td>
                                <td><span class="cv-pill"><?=h($p['payment_status'] ?: '—')?></span></td>
                                <td><?=h($p['reference'] ?: ($p['provider_reference'] ?: '—'))?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
