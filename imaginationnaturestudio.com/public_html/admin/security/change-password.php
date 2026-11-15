<?php
/**
 * Imagination Nature Studio
 * Admin Security — Change Password
 *
 * Security behavior:
 * 1. Requires the currently logged-in admin.
 * 2. Verifies the current password.
 * 3. Updates the password using password_hash().
 * 4. Revokes ALL admin_sessions for this user.
 * 5. Destroys the current PHP session.
 * 6. Sends the user back to Login.
 */

require_once __DIR__ . '/../../includes/functions.php';

require_admin_only();

$pdo = db();
$adminId = (int)($_SESSION['admin_id'] ?? 0);

if ($adminId <= 0) {
    redirect(SITE_URL . '/admin/login.php');
}

$errors = [];
$success = false;

/*
 * Detect the password column safely.
 * Most systems use password_hash; if the existing database
 * uses password instead, this page supports that too.
 */
$passwordColumn = null;

try {
    $columns = $pdo
        ->query("DESCRIBE admins")
        ->fetchAll(PDO::FETCH_ASSOC);

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

/*
 * Load current account.
 */
$admin = null;

if (!$errors) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, email, `$passwordColumn` AS stored_password
            FROM admins
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            $errors[] = 'Admin account not found.';
        }
    } catch (Throwable $e) {
        $errors[] = 'Unable to load the admin account.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors) {

    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword     = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($currentPassword === '') {
        $errors[] = 'Enter your current password.';
    }

    if ($newPassword === '') {
        $errors[] = 'Enter a new password.';
    }

    /*
     * Minimum security requirement.
     */
    if ($newPassword !== '' && strlen($newPassword) < 10) {
        $errors[] = 'The new password must contain at least 10 characters.';
    }

    if ($newPassword !== '' && $newPassword !== $confirmPassword) {
        $errors[] = 'The new passwords do not match.';
    }

    if (
        !$errors &&
        !password_verify(
            $currentPassword,
            (string)$admin['stored_password']
        )
    ) {
        $errors[] = 'Your current password is incorrect.';
    }

    if (
        !$errors &&
        password_verify(
            $newPassword,
            (string)$admin['stored_password']
        )
    ) {
        $errors[] = 'The new password must be different from the current password.';
    }

    if (!$errors) {

        $newHash = password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

        try {

            $pdo->beginTransaction();

            /*
             * Update password.
             */
            $stmt = $pdo->prepare(
                "UPDATE admins
                 SET `$passwordColumn` = ?
                 WHERE id = ?
                 LIMIT 1"
            );

            $stmt->execute([
                $newHash,
                $adminId
            ]);

            /*
             * CRITICAL SECURITY STEP:
             * Revoke EVERY active session belonging to this admin.
             */
            $stmt = $pdo->prepare("
                UPDATE admin_sessions
                SET revoked_at = NOW()
                WHERE admin_id = ?
                  AND revoked_at IS NULL
            ");

            $stmt->execute([$adminId]);

            $pdo->commit();

            /*
             * Destroy the current browser session too.
             * This forces a fresh login using the new password.
             */
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();

                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'] ?? '',
                    (bool)$params['secure'],
                    (bool)$params['httponly']
                );
            }

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }

            redirect(
                SITE_URL .
                '/admin/login.php?password_changed=1'
            );

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] =
                'The password could not be changed. No sessions were revoked.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Change Password | Security | Imagination Nature Studio</title>

    <style>
        :root{
            --bg:#f5f7fb;
            --card:#fff;
            --text:#172033;
            --muted:#6b7280;
            --line:#e5e7eb;
            --danger:#b42318;
            --danger-bg:#fff1f0;
            --primary:#111827;
            --shadow:0 12px 35px rgba(15,23,42,.08);
        }

        *{box-sizing:border-box}

        body{
            margin:0;
            min-height:100vh;
            background:var(--bg);
            color:var(--text);
            font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
        }

        .wrap{
            width:min(620px,calc(100% - 30px));
            margin:55px auto;
        }

        .card{
            background:var(--card);
            border:1px solid var(--line);
            border-radius:20px;
            box-shadow:var(--shadow);
            padding:30px;
        }

        h1{
            margin:0 0 8px;
            font-size:29px;
            letter-spacing:-.5px;
        }

        .sub{
            margin:0 0 24px;
            color:var(--muted);
            line-height:1.6;
        }

        .warning{
            background:#fff8e6;
            border:1px solid #f3d38a;
            border-radius:13px;
            padding:15px 16px;
            margin-bottom:22px;
            line-height:1.55;
            font-size:14px;
        }

        .error{
            background:var(--danger-bg);
            border:1px solid #f1b7b2;
            color:var(--danger);
            border-radius:13px;
            padding:13px 15px;
            margin-bottom:18px;
            font-size:14px;
        }

        .error div + div{
            margin-top:6px;
        }

        label{
            display:block;
            font-size:13px;
            font-weight:800;
            margin:0 0 7px;
        }

        .field{
            margin-bottom:17px;
        }

        input{
            width:100%;
            border:1px solid #d1d5db;
            border-radius:11px;
            padding:13px 14px;
            font-size:15px;
            outline:none;
        }

        input:focus{
            border-color:#111827;
            box-shadow:0 0 0 3px rgba(17,24,39,.08);
        }

        .hint{
            margin-top:6px;
            color:var(--muted);
            font-size:12px;
        }

        .actions{
            display:flex;
            gap:10px;
            align-items:center;
            margin-top:22px;
        }

        button{
            border:0;
            border-radius:11px;
            background:var(--primary);
            color:#fff;
            padding:13px 18px;
            font-weight:800;
            cursor:pointer;
        }

        .back{
            text-decoration:none;
            color:var(--text);
            border:1px solid var(--line);
            background:#fff;
            border-radius:11px;
            padding:12px 16px;
            font-weight:700;
        }

        @media(max-width:600px){
            .wrap{margin:25px auto}
            .card{padding:22px}
            .actions{flex-direction:column;align-items:stretch}
            button,.back{text-align:center}
        }
    </style>
</head>

<body>
<div class="wrap">

    <div class="card">

        <h1>Change Password</h1>

        <p class="sub">
            Change the password for your administrator account.
        </p>

        <div class="warning">
            <strong>Important security action:</strong><br>
            Changing your password will immediately sign out all devices currently using this account.
            You will need to sign in again using your new password.
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

            <div class="field">
                <label for="current_password">
                    Current Password
                </label>

                <input
                    id="current_password"
                    name="current_password"
                    type="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="field">
                <label for="new_password">
                    New Password
                </label>

                <input
                    id="new_password"
                    name="new_password"
                    type="password"
                    autocomplete="new-password"
                    minlength="10"
                    required
                >

                <div class="hint">
                    Minimum 10 characters.
                </div>
            </div>

            <div class="field">
                <label for="confirm_password">
                    Confirm New Password
                </label>

                <input
                    id="confirm_password"
                    name="confirm_password"
                    type="password"
                    autocomplete="new-password"
                    minlength="10"
                    required
                >
            </div>

            <div class="actions">
                <button type="submit">
                    CHANGE PASSWORD & LOG OUT ALL DEVICES
                </button>

                <a
                    class="back"
                    href="<?= e(SITE_URL . '/admin/security/sessions.php') ?>"
                >
                    Cancel
                </a>
            </div>
        </form>

    </div>

</div>
</body>
</html>
