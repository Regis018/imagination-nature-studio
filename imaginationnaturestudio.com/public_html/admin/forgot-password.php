<?php
/**
 * Admin / Staff Forgot Password
 * Imagination Nature Studio
 *
 * Sends a one-time password reset link.
 */

require_once __DIR__ . '/../includes/functions.php';

$err = '';
$ok  = '';

$email = trim($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf'] ?? null)) {

        $err = 'Your session expired. Please refresh the page and try again.';

    } elseif (!valid_email($email)) {

        $err = 'Enter a valid email address.';

    } else {

        try {

            $pdo = db();

            /*
             * Always show the same success message whether the
             * email exists or not. This prevents account enumeration.
             */
            $genericMessage =
                'If an account exists for that email, a password reset link has been sent. Please check your inbox.';

            $stmt = $pdo->prepare(
                "SELECT id, name, email, is_active
                 FROM admins
                 WHERE email = ?
                 LIMIT 1"
            );

            $stmt->execute([$email]);

            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin || (int)($admin['is_active'] ?? 1) !== 1) {

                $ok = $genericMessage;

            } else {

                /*
                 * Small rate limit:
                 * do not issue another token for the same account
                 * within 60 seconds.
                 */
                $rate = $pdo->prepare(
                    "SELECT id
                     FROM password_resets
                     WHERE admin_id = ?
                       AND created_at >= (NOW() - INTERVAL 60 SECOND)
                     ORDER BY id DESC
                     LIMIT 1"
                );

                $rate->execute([(int)$admin['id']]);

                if ($rate->fetch()) {

                    $ok =
                        'If an account exists for that email, a password reset link has been sent. Please check your inbox.';

                } else {

                    /*
                     * Invalidate previous active reset tokens.
                     */
                    $pdo->prepare(
                        "DELETE FROM password_resets
                         WHERE admin_id = ?"
                    )->execute([(int)$admin['id']]);

                    /*
                     * Generate a strong random token.
                     * Only the SHA-256 hash is stored in the database.
                     */
                    $rawToken  = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $rawToken);

                    $pdo->prepare(
                        "INSERT INTO password_resets
                         (admin_id, token_hash, expires_at, created_at)
                         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW())"
                    )->execute([
                        (int)$admin['id'],
                        $tokenHash
                    ]);

                    $resetUrl =
                        SITE_URL .
                        '/admin/reset-password.php?token=' .
                        urlencode($rawToken);

                    $name = trim((string)($admin['name'] ?? ''));
                    $hello = $name !== '' ? 'Hello ' . $name . ',' : 'Hello,';

                    $subject = 'Reset your Imagination Nature Studio password';

                    $body =
                        $hello . "\n\n" .
                        "We received a request to reset your Imagination Nature Studio admin password.\n\n" .
                        "Use the link below to create a new password:\n" .
                        $resetUrl . "\n\n" .
                        "This link expires in 30 minutes and can only be used once.\n\n" .
                        "If you did not request this, you can safely ignore this email.\n\n" .
                        "Imagination Nature Studio";

                    $headers = [
                        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
                        'Reply-To: ' . MAIL_FROM,
                        'MIME-Version: 1.0',
                        'Content-Type: text/plain; charset=UTF-8'
                    ];

                    $sent = @mail(
                        $admin['email'],
                        $subject,
                        $body,
                        implode("\r\n", $headers)
                    );

                    if (!$sent) {

                        /*
                         * Remove the token if the mail could not be handed
                         * to the server.
                         */
                        $pdo->prepare(
                            "DELETE FROM password_resets
                             WHERE token_hash = ?"
                        )->execute([$tokenHash]);

                        @error_log(
                            '[PASSWORD RESET] mail() failed for ' .
                            $admin['email'] . "\n",
                            3,
                            ERROR_LOG_FILE
                        );

                        $err =
                            'We could not send the reset email right now. Please try again later.';

                    } else {

                        $ok = $genericMessage;

                        @error_log(
                            '[PASSWORD RESET] reset email sent to ' .
                            $admin['email'] . "\n",
                            3,
                            ERROR_LOG_FILE
                        );
                    }
                }
            }

        } catch (Throwable $e) {

            $err = 'Password reset is temporarily unavailable. Please try again later.';

            @error_log(
                '[PASSWORD RESET] ' .
                $e->getMessage() . "\n",
                3,
                ERROR_LOG_FILE
            );
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
<meta charset="utf-8">
<title>Forgot password · Imagination Nature Studio</title>
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

        <h1>Forgot password?</h1>

        <p class="subtitle">
            Enter your admin email and we will send you a secure reset link.
        </p>

        <?php if ($err): ?>
            <div class="alert"><?= e($err) ?></div>
        <?php endif; ?>

        <?php if ($ok): ?>
            <div class="info"><?= e($ok) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <?= csrf_field() ?>

            <div class="field">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($email) ?>"
                    required
                    autofocus
                    autocomplete="email">
            </div>

            <button type="submit">Send reset link</button>
        </form>

        <a class="back-link" href="<?= e(SITE_URL) ?>/admin/login.php">
            ← Back to sign in
        </a>

    </div>
</div>

</body>
</html>
