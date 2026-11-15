<?php
/**
 * Imagination Nature Studio
 * Security — Staff User Management
 */

require_once __DIR__ . '/../../includes/functions.php';

require_admin_only();

$pdo = db();

$stmt = $pdo->query("
    SELECT id, name, email, role, is_active
    FROM admins
    WHERE role = 'staff'
    ORDER BY name ASC, id ASC
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
 --shadow:0 10px 30px rgba(15,23,42,.07);
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);
font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.wrap{max-width:1100px;margin:0 auto;padding:32px 20px 60px}
.top{display:flex;justify-content:space-between;align-items:flex-start;gap:15px;margin-bottom:24px}
h1{margin:0 0 7px;font-size:29px}
.sub{margin:0;color:var(--muted)}
.card{background:#fff;border:1px solid var(--line);border-radius:18px;
box-shadow:var(--shadow);overflow:hidden}
.notice{padding:18px 20px;background:#fff8e6;border-bottom:1px solid #f3d38a;
font-size:14px;line-height:1.55}
table{width:100%;border-collapse:collapse}
th,td{padding:16px 18px;text-align:left;border-bottom:1px solid var(--line)}
th{background:#fafafa;font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#4b5563}
tr:last-child td{border-bottom:0}
.name{font-weight:800}.email{font-size:12px;color:var(--muted);margin-top:3px}
.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:800}
.active{background:var(--green-bg);color:var(--green)}
.inactive{background:#f3f4f6;color:#6b7280}
.btn{border:0;border-radius:10px;padding:10px 13px;font-weight:800;cursor:pointer}
.reset{background:var(--danger-bg);color:var(--danger);border:1px solid #f1b7b2}
.back{display:inline-block;text-decoration:none;color:var(--text);background:#fff;
border:1px solid var(--line);padding:11px 15px;border-radius:11px;font-weight:700}
.empty{text-align:center;padding:45px;color:var(--muted)}
@media(max-width:750px){.top{flex-direction:column}table{min-width:760px}.card{overflow-x:auto}}
</style>
</head>
<body>
<div class="wrap">
<div class="top">
<div>
<h1>Staff Security</h1>
<p class="sub">Admins can manage staff passwords and revoke their active sessions.</p>
</div>
<a class="back" href="<?= e(SITE_URL . '/admin/dashboard.php') ?>">← Dashboard</a>
</div>

<?php if ($ok): ?>
<div class="notice" style="background:#ecfdf3;border-color:#a7e3c1;color:#067647;margin-bottom:18px">
<?= e($ok) ?>
</div>
<?php endif; ?>

<?php if ($err): ?>
<div class="notice" style="background:#fff1f0;border-color:#f1b7b2;color:#b42318;margin-bottom:18px">
<?= e($err) ?>
</div>
<?php endif; ?>

<div class="card">
<div class="notice">
<strong>Admin only</strong><br>
Resetting a staff password will immediately revoke all active sessions belonging to that staff account.
</div>

<div style="overflow-x:auto">
<table>
<thead>
<tr>
<th>Staff</th>
<th>Email</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php if (!$staffUsers): ?>
<tr><td colspan="4" class="empty">Nta staff account yabonetse.</td></tr>
<?php else: ?>
<?php foreach ($staffUsers as $staff): ?>
<tr>
<td>
<div class="name"><?= e($staff['name'] ?: 'Unnamed staff') ?></div>
</td>
<td><?= e($staff['email'] ?: '—') ?></td>
<td>
<?php if ((int)$staff['is_active'] === 1): ?>
<span class="badge active">Active</span>
<?php else: ?>
<span class="badge inactive">Inactive</span>
<?php endif; ?>
</td>
<td>
<a
class="btn reset"
href="<?= e(SITE_URL . '/admin/security/reset-staff-password.php?id=' . (int)$staff['id']) ?>"
>
RESET PASSWORD
</a>
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
