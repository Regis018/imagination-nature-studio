<?php
/**
 * Admin / Staff Login
 * Imagination Nature Studio
 *
 * Security:
 * - Database-backed admin sessions
 * - Device / browser / OS tracking
 * - Remote session revocation support
 * - Session expiration
 * - CSRF protection
 * - Password verification
 */

require_once __DIR__ . '/../includes/functions.php';

$err = '';

/*
 * If already logged in AND the database session is still valid,
 * do not show the login form again.
 *
 * Important:
 * If the session was remotely revoked, destroy it here
 * instead of redirecting the user back to the dashboard.
 */
if (is_logged_in()) {

    if (validate_admin_session()) {

        $a = current_admin();

        if (($a['role'] ?? 'admin') === 'staff') {
            redirect(SITE_URL . '/admin/daily-report.php');
        }

        redirect(SITE_URL . '/admin/dashboard.php');

    } else {

        destroy_admin_session();

        $err = 'Your session has ended. Please sign in again.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* CSRF */
    if (!verify_csrf($_POST['csrf'] ?? null)) {

        $err = 'Your session expired. Please refresh the page and try again.';

    } else {

        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';

        if (!valid_email($email) || $pass === '') {

            $err = 'Enter your email and password.';

        } else {

            try {

                $pdo = db();

                $stmt = $pdo->prepare(
                    "SELECT *
                     FROM admins
                     WHERE email = ?
                     LIMIT 1"
                );

                $stmt->execute([$email]);

                $a = $stmt->fetch();

                if (!$a) {

                    $err = 'Invalid credentials.';

                } else {

                    $active = (int)($a['is_active'] ?? 1);
                    $role   = $a['role'] ?? 'admin';

                    if ($active !== 1) {

                        $err = 'This account is disabled. Contact the administrator.';

                    } elseif (!password_verify($pass, $a['password'])) {

                        $err = 'Invalid credentials.';

                    } else {

                        /*
                         * LOGIN SUCCESS
                         *
                         * Regenerate the PHP session ID before
                         * creating the database-backed session record.
                         */
                        session_regenerate_id(true);

                        $_SESSION['admin_id']   = (int)$a['id'];
                        $_SESSION['admin_name'] = $a['name'];
                        $_SESSION['admin_role'] = $role;

                        /*
                         * Create the secure server-side session record.
                         *
                         * If this fails, do NOT allow the user into
                         * the admin area without a tracked session.
                         */
                        if (!create_admin_session((int)$a['id'])) {

                            $_SESSION = [];

                            if (session_status() === PHP_SESSION_ACTIVE) {
                                session_destroy();
                            }

                            $err = 'Unable to start a secure session. Please try again.';

                        } else {

                            /*
                             * Staff
                             */
                            if ($role === 'staff') {

                                redirect(
                                    SITE_URL . '/admin/daily-report.php'
                                );

                                exit;
                            }

                            /*
                             * Full Admin
                             */
                            redirect(
                                SITE_URL . '/admin/dashboard.php'
                            );

                            exit;
                        }
                    }
                }

            } catch (Throwable $e) {

                /*
                 * Keep the real error in the server log.
                 * Do not expose database credentials/errors to visitors.
                 */

                $err = 'Login error. Please try again.';

                @error_log(
                    '[ADMIN LOGIN] ' . $e->getMessage(),
                    3,
                    ERROR_LOG_FILE
                );
            }
        }
    }

    /*
     * Small delay against repeated login attempts.
     */
    usleep(400000);
}
?>
<!doctype html>
<html lang="en">

<head>

<meta charset="utf-8">

<title>Sign in · Imagination Nature Studio</title>

<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">

<style>
*, *::before, *::after {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
    min-height: 100%;
    background: #0e0d0c;
    color: #f3ece3;
    font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    font-size: 16px;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
}

.login-shell {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 1.5rem;
    background:
        radial-gradient(circle at 30% 30%, rgba(224,122,47,.08), transparent 50%),
        radial-gradient(circle at 70% 70%, rgba(224,122,47,.05), transparent 50%),
        #0e0d0c;
}

.login-card {
    width: min(420px, 100%);
    background: #171513;
    border: 1px solid #262220;
    border-radius: 24px;
    padding: 2.5rem;
    box-shadow: 0 30px 80px rgba(0,0,0,.5);
    animation: cardIn .6s cubic-bezier(.16,1,.3,1) both;
}

@keyframes cardIn {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}

.brand-mark {
    display: grid;
    place-items: center;
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, #e07a2f, #a94d10);
    color: #14100d;
    font-family: 'Playfair Display', Georgia, serif;
    font-weight: 700;
    letter-spacing: .05em;
    font-size: 1rem;
    margin: 0 auto 1.2rem;
}

h1 {
    font-family: 'Playfair Display', Georgia, serif;
    font-weight: 500;
    font-size: 1.5rem;
    margin: 0 0 .35rem;
    text-align: center;
    color: #f3ece3;
}

.subtitle {
    text-align: center;
    color: #7a716a;
    font-size: .85rem;
    margin: 0 0 2rem;
}

.alert {
    padding: 1rem 1.2rem;
    border-radius: 10px;
    margin-bottom: 1.4rem;
    font-size: .9rem;
    background: rgba(200,60,60,.14);
    border: 1px solid rgba(200,60,60,.4);
    color: #f0b3b3;
    animation: alertIn .4s ease both;
}

@keyframes alertIn {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}

label {
    display: block;
    font-size: .78rem;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #b3aaa1;
    margin-bottom: .45rem;
}

.field {
    margin-bottom: 1.1rem;
}

input[type="email"],
input[type="password"] {
    width: 100%;
    padding: .95rem 1rem;
    border-radius: 10px;
    background: #100f0e;
    color: #f3ece3;
    border: 1px solid #2c2724;
    font: inherit;
    font-size: .95rem;
    transition: border-color .3s ease, box-shadow .3s ease, background .3s ease;
}

input[type="email"]:focus,
input[type="password"]:focus {
    outline: none;
    border-color: #e07a2f;
    background: #14110f;
    box-shadow: 0 0 0 4px rgba(224,122,47,.15);
}

button {
    width: 100%;
    padding: 1rem;
    border-radius: 999px;
    background: #e07a2f;
    color: #14100d;
    border: 0;
    font: inherit;
    font-weight: 600;
    font-size: .95rem;
    letter-spacing: .02em;
    cursor: pointer;
    transition: all .3s cubic-bezier(.16,1,.3,1);
    margin-top: .6rem;
}

button:hover {
    background: #f2a35e;
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(224,122,47,.35);
}

button:active {
    transform: translateY(0);
}

.forgot-link {
    display: block;
    text-align: center;
    margin-top: 1rem;
    color: #e07a2f;
    font-size: .84rem;
    font-weight: 600;
    text-decoration: none;
    transition: color .25s;
}

.forgot-link:hover {
    color: #f2a35e;
}

.back-link {
    display: block;
    text-align: center;
    margin-top: 1.2rem;
    color: #7a716a;
    font-size: .82rem;
    text-decoration: none;
    transition: color .25s;
}

.back-link:hover {
    color: #e07a2f;
}

@media (max-width: 480px) {
    .login-shell { padding: 1rem; }
    .login-card { padding: 2rem 1.4rem; border-radius: 20px; }
}
</style>

</head>

<body>

<div class="login-shell">
    <div class="login-card">

        <div class="brand-mark">INS</div>

        <h1>Sign in</h1>

        <p class="subtitle">Imagination Nature Studio</p>

        <?php if ($err): ?>
            <div class="alert"><?= e($err) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <?= csrf_field() ?>

            <div class="field">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($_POST['email'] ?? '') ?>"
                    required
                    autofocus
                    autocomplete="username">
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password">
            </div>

            <button type="submit">Sign in</button>
        </form>

        <a class="forgot-link" href="<?= e(SITE_URL) ?>/admin/forgot-password.php">
            Forgot password?
        </a>

        <a class="back-link" href="<?= e(SITE_URL) ?>/">
            ← Back to website
        </a>

    </div>
</div>

</body>
</html>
