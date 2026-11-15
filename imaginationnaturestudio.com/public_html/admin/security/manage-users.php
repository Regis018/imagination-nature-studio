<?php
/**
 * Imagination Nature Studio
 * Security — Staff User Management & Approval
 */

require_once __DIR__ . '/../../includes/functions.php';

require_admin_only();

$pdo = db();

/**
 * Revoke every active DB-backed session for a staff account.
 * This forces the staff member out on their next protected request.
 */
function revoke_staff_sessions(PDO $pdo, int $staffId): void
{
    $stmt = $pdo->prepare("
        UPDATE admin_sessions
        SET revoked_at = NOW()
        WHERE admin_id = ?
          AND revoked_at IS NULL
    ");
    $stmt->execute([$staffId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';

    if (!verify_csrf(is_string($csrf) ? $csrf : null)) {
        flash('err', 'Umutekano wemeza iki gikorwa ntiwagenze neza. Ongera ugerageze.');
        header('Location: ' . SITE_URL . '/admin/security/manage-users.php');
        exit;
    }

    $staffId = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
    $action  = $_POST['action'] ?? '';

    if (!$staffId || !in_array($action, ['approve', 'reject', 'suspend'], true)) {
        flash('err', 'Igikorwa wasabye nticyemewe.');
        header('Location: ' . SITE_URL . '/admin/security/manage-users.php');
        exit;
    }

    try {
        $find = $pdo->prepare("
            SELECT id, role, approval_status
            FROM admins
            WHERE id = ?
            LIMIT 1
        ");
        $find->execute([(int)$staffId]);
        $target = $find->fetch(PDO::FETCH_ASSOC);

        if (!$target || $target['role'] !== 'staff') {
            flash('err', 'Staff account ntiyabonetse.');
        } else {
            $admin = current_admin();
            $adminId = (int)($admin['id'] ?? 0);

            if ($action === 'approve') {
                $update = $pdo->prepare("
                    UPDATE admins
                    SET approval_status = 'approved',
                        approved_by = ?,
                        approved_at = NOW()
                    WHERE id = ? AND role = 'staff'
                ");
                $update->execute([$adminId ?: null, (int)$staffId]);

                flash('ok', 'Staff yemerewe gukoresha admin panel. Azasabwa kongera kwinjira niba session ye yari yarafunzwe.');
            } elseif ($action === 'reject') {
                $update = $pdo->prepare("
                    UPDATE admins
                    SET approval_status = 'rejected'
                    WHERE id = ? AND role = 'staff'
                ");
                $update->execute([(int)$staffId]);

                revoke_staff_sessions($pdo, (int)$staffId);
                flash('ok', 'Ubusabe bwa staff bwanze kandi sessions ze zose zafunzwe.');
            } elseif ($action === 'suspend') {
                $update = $pdo->prepare("
                    UPDATE admins
                    SET approval_status = 'suspended'
                    WHERE id = ? AND role = 'staff'
                ");
                $update->execute([(int)$staffId]);

                revoke_staff_sessions($pdo, (int)$staffId);
                flash('ok', 'Staff yahagaritswe kandi sessions ze zose zafunzwe.');
            }
        }
    } catch (Throwable $e) {
        error_log('Staff approval management error: ' . $e->getMessage());
        flash('err', 'Habaye ikibazo mu kubika impinduka. Reba error log ya server.');
    }

    header('Location: ' . SITE_URL . '/admin/security/manage-users.php');
    exit;
}

$stmt = $pdo->query("
    SELECT
        id, name, email, role, is_active,
        approval_status, approved_at
    FROM admins
    WHERE role = 'staff'
    ORDER BY
        CASE approval_status
            WHEN 'pending' THEN 0
            WHEN 'approved' THEN 1
            WHEN 'rejected' THEN 2
            WHEN 'suspended' THEN 3
            ELSE 4
        END,
        name ASC, id ASC
");
$staffUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ok  = flash('ok');
$err = flash('err');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Staff Security | Imagination Nature Studio</title>
<style>
:root{
 --bg:#f5f7fb;--card:#fff;--text:#172033;--muted:#6b7280;
 --line:#e5e7eb;--danger:#b42318;--danger-bg:#fff1f0;
 --green:#067647;--green-bg:#ecfdf3;--primary:#111827;
 --amber:#92400e;--amber-bg:#fffbeb;--blue:#1d4ed8;--blue-bg:#eff6ff;
 --shadow:0 10px 30px rgba(15,23,42,.07);
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);
font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.wrap{max-width:1180px;margin:0 auto;padding:32px 20px 60px}
.top{display:flex;justify-content:space-between;align-items:flex-start;gap:15px;margin-bottom:24px}
h1{margin:0 0 7px;font-size:29px}
.sub{margin:0;color:var(--muted);line-height:1.5}
.card{background:#fff;border:1px solid var(--line);border-radius:18px;
box-shadow:var(--shadow);overflow:hidden}
.notice{padding:18px 20px;background:#fff8e6;border-bottom:1px solid #f3d38a;
font-size:14px;line-height:1.55}
table{width:100%;border-collapse:collapse;min-width:900px}
th,td{padding:15px 16px;text-align:left;border-bottom:1px solid var(--line);vertical-align:middle}
th{background:#fafafa;font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#4b5563}
tr:last-child td{border-bottom:0}
.name{font-weight:800}.email{font-size:12px;color:var(--muted);margin-top:3px}
.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:800;white-space:nowrap}
.active{background:var(--green-bg);color:var(--green)}
.inactive{background:#f3f4f6;color:#6b7280}
.pending{background:var(--amber-bg);color:var(--amber)}
.rejected,.suspended{background:var(--danger-bg);color:var(--danger)}
.approved{background:var(--green-bg);color:var(--green)}
.actions{display:flex;gap:7px;flex-wrap:wrap}
.btn{display:inline-block;border:0;border-radius:9px;padding:9px 11px;font-size:11px;
font-weight:850;cursor:pointer;text-decoration:none;white-space:nowrap}
.approve-btn{background:#067647;color:#fff}
.reject-btn{background:#fff1f0;color:#b42318;border:1px solid #f1b7b2}
.suspend-btn{background:#111827;color:#fff}
.reset{background:var(--blue-bg);color:var(--blue);border:1px solid #bfdbfe}
.back{display:inline-block;text-decoration:none;color:var(--text);background:#fff;
border:1px solid var(--line);padding:11px 15px;border-radius:11px;font-weight:700}
.empty{text-align:center;padding:45px;color:var(--muted)}
.flash{padding:14px 17px;border-radius:12px;margin-bottom:16px;font-size:14px;line-height:1.5}
.flash.ok{background:#ecfdf3;border:1px solid #a7e3c1;color:#067647}
.flash.err{background:#fff1f0;border:1px solid #f1b7b2;color:#b42318}
.small{font-size:12px;color:var(--muted);margin-top:5px}
@media(max-width:750px){.top{flex-direction:column}.wrap{padding:22px 12px 45px}.card{overflow-x:auto}}
</style>
</head>
<body>
<div class="wrap">
<div class="top">
<div>
<h1>Staff Security</h1>
<p class="sub">Approve staff accounts, reject requests, suspend access, and manage staff passwords.</p>
</div>
<a class="back" href="<?= e(SITE_URL . '/admin/dashboard.php') ?>">← Dashboard</a>
</div>

<?php if ($ok): ?>
<div class="flash ok"><?= e($ok) ?></div>
<?php endif; ?>

<?php if ($err): ?>
<div class="flash err"><?= e($err) ?></div>
<?php endif; ?>

<div class="card">
<div class="notice">
<strong>Admin only — Staff approval</strong><br>
Staff bashya cyangwa batarahabwa uburenganzira baguma kuri <strong>Pending</strong> kugeza ubemereye.
Iyo ukoze Reject cyangwa Suspend, sessions zabo zose zifungwa ako kanya; ntibashobora gukomeza gukoresha admin panel.
Iyo ukoze Approve, bemererwa kwinjira. Approval igumaho kugeza uyihinduye.
</div>

<div style="overflow-x:auto">
<table>
<thead>
<tr>
<th>Staff</th>
<th>Email</th>
<th>Account</th>
<th>Approval</th>
<th>Approved date</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php if (!$staffUsers): ?>
<tr><td colspan="6" class="empty">Nta staff account yabonetse.</td></tr>
<?php else: ?>
<?php foreach ($staffUsers as $staff): ?>
<?php
$status = $staff['approval_status'] ?? 'pending';
if (!in_array($status, ['pending','approved','rejected','suspended'], true)) {
    $status = 'pending';
}
?>
<tr>
<td><div class="name"><?= e($staff['name'] ?: 'Unnamed staff') ?></div></td>
<td><?= e($staff['email'] ?: '—') ?></td>
<td>
<?php if ((int)$staff['is_active'] === 1): ?>
<span class="badge active">Active</span>
<?php else: ?>
<span class="badge inactive">Inactive</span>
<?php endif; ?>
</td>
<td><span class="badge <?= e($status) ?>"><?= e(ucfirst($status)) ?></span></td>
<td><?= !empty($staff['approved_at']) ? e(date('d M Y, H:i', strtotime($staff['approved_at']))) : '—' ?></td>
<td>
<div class="actions">
<?php if ($status !== 'approved'): ?>
<form method="post" onsubmit="return confirm('Wemera iyi staff gukoresha admin panel?');">
<?= csrf_field() ?>
<input type="hidden" name="staff_id" value="<?= (int)$staff['id'] ?>">
<input type="hidden" name="action" value="approve">
<button class="btn approve-btn" type="submit">APPROVE</button>
</form>
<?php endif; ?>

<?php if ($status !== 'rejected'): ?>
<form method="post" onsubmit="return confirm('Wanga ubusabe bw’iyi staff? Sessions ze zose zirafungwa.');">
<?= csrf_field() ?>
<input type="hidden" name="staff_id" value="<?= (int)$staff['id'] ?>">
<input type="hidden" name="action" value="reject">
<button class="btn reject-btn" type="submit">REJECT</button>
</form>
<?php endif; ?>

<?php if ($status !== 'suspended'): ?>
<form method="post" onsubmit="return confirm('Uhagarike iyi staff? Sessions ze zose zirafungwa.');">
<?= csrf_field() ?>
<input type="hidden" name="staff_id" value="<?= (int)$staff['id'] ?>">
<input type="hidden" name="action" value="suspend">
<button class="btn suspend-btn" type="submit">SUSPEND</button>
</form>
<?php endif; ?>

<a class="btn reset"
href="<?= e(SITE_URL . '/admin/security/reset-staff-password.php?id=' . (int)$staff['id']) ?>">
RESET PASSWORD
</a>
</div>
<div class="small">Approve = yemerewe. Reject = ntiyemerewe. Suspend = guhagarika konti.</div>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</body>
</html>
