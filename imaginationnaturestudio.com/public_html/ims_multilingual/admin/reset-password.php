<?php
/**
 * Admin / Staff Reset Password
 * Imagination Nature Studio
 *
 * One-time reset token, 30-minute expiry.
 */

require_once __DIR__ . '/../includes/functions.php';

$err = '';
$ok  = '';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

$tokenValid = false;
$resetRow   = null;

if ($token !== '' && preg_match('/^[a-f0-9]{64}$/i', $token)) {

    try {

        $pdo = db();

        $tokenHash = hash('sha256', $token);

        $stmt = $pdo->prepare(
            "SELECT
                pr.id AS reset_id,
                pr.admin_id,
                pr.expires_at,
                a.email,
                a.name,
                a.is_active
             FROM password_resets pr
             INNER JOIN admins a
                ON a.id = pr.admin_id
             WHERE pr.token_hash = ?
               AND pr.expires_at > NOW()
             LIMIT 1"
        );

        $stmt->execute([$tokenHash]);

        $resetRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            $resetRow
            &&
            (int)($resetRow['is_active'] ?? 1) === 1
        ) {
            $tokenValid = true;
        }

    } catch (Throwable $e) {

        $err = 'This password reset link is unavailable. Please request a new one.';

        @error_log(
            '[PASSWORD RESET VERIFY] ' .
            $e->getMessage() . "\n",
            3,
            ERROR_LOG_FILE
        );
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf'] ?? null)) {

        $err = 'Your session expired. Please refresh the page and try again.';

    } elseif (!$tokenValid || !$resetRow) {

        $err = 'This reset link is invalid or has expired. Please request a new one.';

    } else {

        $password  = $_POST['password'] ?? '';
        $password2 = $_POST['password_confirm'] ?? '';

        if (strlen($password) < 10) {

            $err = 'Your new password must be at least 10 characters long.';

        } elseif ($password !== $password2) {

            $err = 'The passwords do not match.';

        } else {

            try {

                $pdo->beginTransaction();

                /*
                 * Re-check the token inside the transaction so an old
                 * or already-used token cannot be reused.
                 */
                $check = $pdo->prepare(
                    "SELECT
                        pr.id AS reset_id,
                        pr.admin_id,
                        pr.expires_at,
                        a.email,
                        a.is_active
                     FROM password_resets pr
                     INNER JOIN admins a
                        ON a.id = pr.admin_id
                     WHERE pr.token_hash = ?
                       AND pr.expires_at > NOW()
                     LIMIT 1
                     FOR UPDATE"
                );

                $check->execute([$tokenHash]);

                $fresh = $check->fetch(PDO::FETCH_ASSOC);

                if (
                    !$fresh
                    ||
                    (int)($fresh['is_active'] ?? 1) !== 1
                ) {

                    throw new RuntimeException(
                        'This reset link is invalid or has expired.'
                    );
                }

                $newHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $update = $pdo->prepare(
                    "UPDATE admins
                     SET password = ?
                     WHERE id = ?"
                );

                $update->execute([
                    $newHash,
                    (int)$fresh['admin_id']
                ]);

                /*
                 * Delete ALL reset tokens for this admin.
                 * This also invalidates any older reset email.
                 */
                $pdo->prepare(
                    "DELETE FROM password_resets
                     WHERE admin_id = ?"
                )->execute([
                    (int)$fresh['admin_id']
                ]);

                $pdo->commit();

                /*
                 * Security: invalidate any existing logged-in session
                 * for this browser before sending the user to login.
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

                session_destroy();

                $ok =
                    'Your password has been changed successfully. You can now sign in with your new password.';

                /*
                 * Render success page below rather than redirecting
                 * immediately, so the user sees confirmation.
                 */
                $tokenValid = false;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $err =
                    $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'We could not change your password. Please try again.';

                if (!($e instanceof RuntimeException)) {
                    @error_log(
                        '[PASSWORD RESET SAVE] ' .
                        $e->getMessage() . "\n",
                        3,
                        ERROR_LOG_FILE
                    );
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
<meta charset="utf-8">
<title>Reset password · Imagination Nature Studio</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">

<style>
*,*::before,*::after{box-sizing:border-box}
html,body{
    margin:0;
    padding:0;
    min-height:100%;
    background:#0e0d0c;
    color:#f3ece3;
    font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
    line-height:1.6;
    -webkit-font-smoothing:antialiased;
}
.login-shell{
    min-height:100vh;
    display:grid;
    place-items:center;
    padding:1.5rem;
    background:
        radial-gradient(circle at 30% 30%,rgba(224,122,47,.08),transparent 50%),
        radial-gradient(circle at 70% 70%,rgba(224,122,47,.05),transparent 50%),
        #0e0d0c;
}
.login-card{
    width:min(420px,100%);
    background:#171513;
    border:1px solid #262220;
    border-radius:24px;
    padding:2.5rem;
    box-shadow:0 30px 80px rgba(0,0,0,.5);
}
.brand-mark{
    display:grid;
    place-items:center;
    width:52px;
    height:52px;
    border-radius:14px;
    background:linear-gradient(135deg,#e07a2f,#a94d10);
    color:#14100d;
    font-family:Georgia,serif;
    font-weight:700;
    letter-spacing:.05em;
    margin:0 auto 1.2rem;
}
h1{
    font-family:Georgia,serif;
    font-weight:500;
    font-size:1.5rem;
    margin:0 0 .35rem;
    text-align:center;
}
.subtitle{
    text-align:center;
    color:#7a716a;
    font-size:.85rem;
    margin:0 0 1.8rem;
}
.info,.alert{
    padding:1rem 1.1rem;
    border-radius:10px;
    margin-bottom:1.3rem;
    font-size:.9rem;
}
.info{
    background:rgba(63,155,92,.12);
    border:1px solid rgba(63,155,92,.35);
    color:#a9e0b8;
}
.alert{
    background:rgba(200,60,60,.14);
    border:1px solid rgba(200,60,60,.4);
    color:#f0b3b3;
}
label{
    display:block;
    font-size:.78rem;
    letter-spacing:.12em;
    text-transform:uppercase;
    color:#b3aaa1;
    margin-bottom:.45rem;
}
.field{margin-bottom:1.1rem}
input{
    width:100%;
    padding:.95rem 1rem;
    border-radius:10px;
    background:#100f0e;
    color:#f3ece3;
    border:1px solid #2c2724;
    font:inherit;
}
input:focus{
    outline:none;
    border-color:#e07a2f;
    box-shadow:0 0 0 4px rgba(224,122,47,.15);
}
.hint{
    margin-top:.45rem;
    color:#716a64;
    font-size:.75rem;
}
button{
    width:100%;
    padding:1rem;
    border-radius:999px;
    background:#e07a2f;
    color:#14100d;
    border:0;
    font:inherit;
    font-weight:600;
    cursor:pointer;
    margin-top:.4rem;
}
button:hover{
    background:#f2a35e;
    transform:translateY(-1px);
    box-shadow:0 12px 30px rgba(224,122,47,.3);
}
.back-link{
    display:block;
    text-align:center;
    margin-top:1.3rem;
    color:#7a716a;
    font-size:.82rem;
    text-decoration:none;
}
.back-link:hover{color:#e07a2f}
@media(max-width:480px){
    .login-shell{padding:1rem}
    .login-card{padding:2rem 1.4rem;border-radius:20px}
}
</style>
</head>

<body>

<div class="login-shell">
    <div class="login-card">

        <div class="brand-mark">INS</div>

        <h1>Reset password</h1>

        <p class="subtitle">
            Create a new secure password for your account.
        </p>

        <?php if ($err): ?>
            <div class="alert"><?= e($err) ?></div>
        <?php endif; ?>

        <?php if ($ok): ?>

            <div class="info"><?= e($ok) ?></div>

            <a
                class="back-link"
                href="<?= e(SITE_URL) ?>/admin/login.php"
                style="color:#e07a2f;font-weight:700"
            >
                Continue to sign in →
            </a>

        <?php elseif ($tokenValid && $resetRow): ?>

            <form method="post" autocomplete="off">
                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="token"
                    value="<?= e($token) ?>">

                <div class="field">
                    <label for="password">New password</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="10"
                        required
                        autofocus
                        autocomplete="new-password">

                    <div class="hint">
                        Use at least 10 characters.
                    </div>
                </div>

                <div class="field">
                    <label for="password_confirm">Confirm new password</label>

                    <input
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        minlength="10"
                        required
                        autocomplete="new-password">
                </div>

                <button type="submit">
                    Change password
                </button>
            </form>

        <?php else: ?>

            <div class="alert">
                This reset link is invalid or has expired.
            </div>

            <a
                class="back-link"
                href="<?= e(SITE_URL) ?>/admin/forgot-password.php"
                style="color:#e07a2f;font-weight:700"
            >
                Request a new reset link →
            </a>

        <?php endif; ?>

        <?php if (!$ok): ?>
            <a class="back-link" href="<?= e(SITE_URL) ?>/admin/login.php">
                ← Back to sign in
            </a>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
