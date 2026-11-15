<?php
/**
 * Imagination Nature Studio
 * Security — Admin resets a Staff password
 */

require_once __DIR__ . '/../../includes/functions.php';

require_admin_only();

$pdo = db();
$staffId = (int)($_GET['id'] ?? $_POST['staff_id'] ?? 0);

if ($staffId <= 0) {
    flash('err', 'Invalid staff account.');
    redirect(SITE_URL . '/admin/security/manage-users.php');
}

$stmt = $pdo->prepare("
    SELECT id, name, email, role, is_active
    FROM admins
    WHERE id = ?
      AND role = 'staff'
    LIMIT 1
");
$stmt->execute([$staffId]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$staff) {
    flash('err', 'Staff account not found.');
    redirect(SITE_URL . '/admin/security/manage-users.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($newPassword === '') {
        $errors[] = 'Enter the new password.';
    }

    if (strlen($newPassword) < 10) {
        $errors[] = 'The password must contain at least 10 characters.';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'The passwords do not match.';
    }

    if (!$errors) {

        /*
         * Detect the actual password column in admins.
         */
        $passwordColumn = null;

        try {
            $columns = $pdo->query("DESCRIBE admins")->fetchAll(PDO::FETCH_ASSOC);

            $available = [];
            foreach ($columns as $column) {
                if (!empty($column['Field'])) {
                    $available[] = $column['Field'];
                }
            }

            foreach (['password_hash', 'password', 'pass'] as $candidate) {
                if (in_array($candidate, $available, true)) {
                    $passwordColumn = $candidate;
                    break;
                }
            }
        } catch (Throwable $e) {
            $errors[] = 'Unable to inspect the admin account structure.';
        }

        if (!$passwordColumn) {
            $errors[] = 'No supported password column was found in the admins table.';
        }
    }

    if (!$errors) {

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        try {

            $pdo->beginTransaction();

            /*
             * Update only the selected STAFF account.
             */
            $stmt = $pdo->prepare("
                UPDATE admins
                SET `$passwordColumn` = ?
                WHERE id = ?
                  AND role = 'staff'
                LIMIT 1
            ");

            $stmt->execute([
                $newHash,
                $staffId
            ]);

            if ($stmt->rowCount() < 1) {
                throw new RuntimeException('Staff password was not updated.');
            }

            /*
             * Immediately revoke every session of this staff user.
             */
            $stmt = $pdo->prepare("
                UPDATE admin_sessions
                SET revoked_at = NOW()
                WHERE admin_id = ?
                  AND revoked_at IS NULL
            ");

            $stmt->execute([$staffId]);

            $revoked = $stmt->rowCount();

            $pdo->commit();

            flash(
                'ok',
                'Password for ' .
                ($staff['name'] ?: 'staff') .
                ' was changed successfully. ' .
                $revoked .
                ' active session(s) were logged out.'
            );

            redirect(SITE_URL . '/admin/security/manage-users.php');

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = 'Password could not be changed. No sessions were revoked.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reset Staff Password | Imagination Nature Studio</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--text:#172033;--muted:#6b7280;--line:#e5e7eb;
--danger:#b42318;--danger-bg:#fff1f0;--primary:#111827;--shadow:0 12px 35px rgba(15,23,42,.08)}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;background:var(--bg);color:var(--text);
font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.wrap{width:min(600px,calc(100% - 30px));margin:55px auto}
.card{background:#fff;border:1px solid var(--line);border-radius:20px;box-shadow:var(--shadow);padding:30px}
h1{margin:0 0 7px;font-size:28px}
.sub{color:var(--muted);margin:0 0 22px}
.target{background:#f8fafc;border:1px solid var(--line);border-radius:13px;padding:15px;margin-bottom:20px}
.target strong{display:block}.target span{font-size:13px;color:var(--muted)}
.warning{background:#fff8e6;border:1px solid #f3d38a;border-radius:13px;padding:14px;
font-size:14px;line-height:1.5;margin-bottom:20px}
.error{background:var(--danger-bg);border:1px solid #f1b7b2;color:var(--danger);
border-radius:13px;padding:13px 15px;margin-bottom:18px;font-size:14px}
.error div+div{margin-top:5px}
.field{margin-bottom:17px}label{display:block;font-size:13px;font-weight:800;margin-bottom:7px}
input{width:100%;border:1px solid #d1d5db;border-radius:11px;padding:13px 14px;font-size:15px}
.hint{font-size:12px;color:var(--muted);margin-top:6px}
.actions{display:flex;gap:10px;margin-top:22px}
button,.back{border-radius:11px;padding:13px 17px;font-weight:800}
button{border:0;background:var(--primary);color:#fff;cursor:pointer}
.back{text-decoration:none;color:var(--text);border:1px solid var(--line);background:#fff}
@media(max-width:600px){.wrap{margin:25px auto}.card{padding:22px}.actions{flex-direction:column}}
</style>
</head>
<body>
<div class="wrap">
<div class="card">

<h1>Reset Staff Password</h1>
<p class="sub">Admin setting a new password for a staff account.</p>

<div class="target">
<strong><?= e($staff['name'] ?: 'Staff') ?></strong>
<span><?= e($staff['email'] ?: '') ?></span>
</div>

<div class="warning">
<strong>Security:</strong> After saving the new password, all active sessions
for this staff account will be revoked. The staff member will need to log in again.
</div>

<?php if ($errors): ?>
<div class="error">
<?php foreach ($errors as $error): ?>
<div>• <?= e($error) ?></div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" autocomplete="off">
<?= csrf_field() ?>
<input type="hidden" name="staff_id" value="<?= (int)$staff['id'] ?>">

<div class="field">
<label for="new_password">New Password</label>
<input id="new_password" name="new_password" type="password"
       minlength="10" autocomplete="new-password" required>
<div class="hint">Minimum 10 characters.</div>
</div>

<div class="field">
<label for="confirm_password">Confirm New Password</label>
<input id="confirm_password" name="confirm_password" type="password"
       minlength="10" autocomplete="new-password" required>
</div>

<div class="actions">
<button type="submit">RESET PASSWORD & LOG OUT STAFF</button>
<a class="back" href="<?= e(SITE_URL . '/admin/security/manage-users.php') ?>">Cancel</a>
</div>
</form>

</div>
</div>
</body>
</html>
