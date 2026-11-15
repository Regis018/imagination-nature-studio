<?php
/**
 * IMAGINATION NATURE STUDIO
 * ADMIN / STAFF LOGIN
 */

require_once __DIR__ . '/../includes/functions.php';

$err = '';
$waitingForApproval = !empty($_SESSION['pending_staff_id']);

/*
|--------------------------------------------------------------------------
| AJAX: CHECK STAFF APPROVAL
|--------------------------------------------------------------------------
*/
if (isset($_GET['check_staff_approval'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $pendingId = (int)($_SESSION['pending_staff_id'] ?? 0);

    if ($pendingId < 1) {
        echo json_encode(['status' => 'none']);
        exit;
    }

    try {
        $stmt = db()->prepare("
            SELECT id, name, role, is_active, approval_status
            FROM admins
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$pendingId]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$staff ||
            $staff['role'] !== 'staff' ||
            (int)$staff['is_active'] !== 1
        ) {
            unset($_SESSION['pending_staff_id']);
            echo json_encode(['status' => 'blocked']);
            exit;
        }

        $status = $staff['approval_status'] ?? 'pending';

        if ($status === 'approved') {

            session_regenerate_id(true);

            $_SESSION['admin_id'] = (int)$staff['id'];
            $_SESSION['admin_name'] = $staff['name'];
            $_SESSION['admin_role'] = 'staff';

            unset($_SESSION['pending_staff_id']);

            if (create_admin_session((int)$staff['id'])) {
                echo json_encode([
                    'status' => 'approved',
                    'redirect' => SITE_URL . '/admin/daily-report.php'
                ]);
            } else {
                $_SESSION = [];
                echo json_encode(['status' => 'error']);
            }

            exit;
        }

        if (in_array($status, ['rejected', 'suspended'], true)) {
            unset($_SESSION['pending_staff_id']);
            echo json_encode(['status' => $status]);
            exit;
        }

        echo json_encode(['status' => 'pending']);

    } catch (Throwable $e) {
        error_log('[STAFF APPROVAL CHECK] ' . $e->getMessage());
        echo json_encode(['status' => 'error']);
    }

    exit;
}

/*
|--------------------------------------------------------------------------
| EXISTING SESSION CHECK
|--------------------------------------------------------------------------
*/
if (is_logged_in()) {

    if (validate_admin_session()) {

        $admin = current_admin();
        $role = $admin['role'] ?? 'admin';

        if ($role === 'staff') {

            $approvalStatus = $admin['approval_status'] ?? 'pending';

            if ($approvalStatus === 'approved') {
                redirect(SITE_URL . '/admin/daily-report.php');
                exit;
            }

            destroy_admin_session();

            if ($approvalStatus === 'rejected') {
                $err = 'Your access request was rejected. Please contact the administrator.';
            } elseif ($approvalStatus === 'suspended') {
                $err = 'Your staff access has been suspended. Please contact the administrator.';
            } else {
                $err = 'Your account is waiting for administrator approval.';
            }

        } else {
            redirect(SITE_URL . '/admin/dashboard.php');
            exit;
        }

    } else {
        destroy_admin_session();
        $err = 'Your session has ended. Please sign in again.';
    }
}

/*
|--------------------------------------------------------------------------
| LOGIN SUBMISSION
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf'] ?? null)) {

        $err = 'Your session expired. Please refresh the page and try again.';

    } else {

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!valid_email($email) || $password === '') {

            $err = 'Enter your email and password.';

        } else {

            try {

                $pdo = db();

                $stmt = $pdo->prepare("
                    SELECT *
                    FROM admins
                    WHERE email = ?
                    LIMIT 1
                ");

                $stmt->execute([$email]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$admin) {

                    $err = 'Invalid credentials.';

                } elseif ((int)$admin['is_active'] !== 1) {

                    $err = 'This account is disabled. Contact the administrator.';

                } elseif (!password_verify($password, $admin['password'])) {

                    $err = 'Invalid credentials.';

                } else {

                    $role = $admin['role'] ?? 'admin';

                    /*
                     * STAFF APPROVAL
                     */
                    if (
                        $role === 'staff' &&
                        ($admin['approval_status'] ?? 'pending') !== 'approved'
                    ) {

                        $approvalStatus = $admin['approval_status'] ?? 'pending';

                        if ($approvalStatus === 'rejected') {

                            $err = 'Your access request was rejected. Please contact the administrator.';

                        } elseif ($approvalStatus === 'suspended') {

                            $err = 'Your staff access has been suspended. Please contact the administrator.';

                        } else {

                            $_SESSION['pending_staff_id'] = (int)$admin['id'];
                            $waitingForApproval = true;
                        }

                    } else {

                        /*
                         * APPROVED STAFF OR ADMIN LOGIN
                         */
                        session_regenerate_id(true);

                        $_SESSION['admin_id'] = (int)$admin['id'];
                        $_SESSION['admin_name'] = $admin['name'];
                        $_SESSION['admin_role'] = $role;

                        if (!create_admin_session((int)$admin['id'])) {

                            $_SESSION = [];

                            if (session_status() === PHP_SESSION_ACTIVE) {
                                session_destroy();
                            }

                            $err = 'Unable to start a secure session. Please try again.';

                        } else {

                            if ($role === 'staff') {
                                redirect(SITE_URL . '/admin/daily-report.php');
                                exit;
                            }

                            redirect(SITE_URL . '/admin/dashboard.php');
                            exit;
                        }
                    }
                }

            } catch (Throwable $e) {

                error_log('[ADMIN LOGIN] ' . $e->getMessage());
                $err = 'Login error. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Sign in | Imagination Nature Studio</title>

<meta name="robots" content="noindex,nofollow">

<style>
* {
    box-sizing: border-box;
}

html, body {
    margin: 0;
    padding: 0;
    min-height: 100%;
    background: #0e0d0c;
    color: #f3ece3;
    font-family: Inter, Arial, sans-serif;
}

.login-shell {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;

    background:
        radial-gradient(
            circle at 30% 30%,
            rgba(224,122,47,.09),
            transparent 50%
        ),
        radial-gradient(
            circle at 70% 70%,
            rgba(224,122,47,.05),
            transparent 50%
        ),
        #0e0d0c;
}

.login-card {
    width: 100%;
    max-width: 420px;
    padding: 40px;
    background: #171513;
    border: 1px solid #29231f;
    border-radius: 22px;
    box-shadow: 0 30px 80px rgba(0,0,0,.5);
    animation: cardIn .6s ease both;
}

@keyframes cardIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.brand-mark {
    width: 55px;
    height: 55px;
    margin: 0 auto 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 15px;
    background: linear-gradient(135deg, #f6a34e, #a94d10);
    color: #17100a;
    font-family: Georgia, serif;
    font-size: 19px;
    font-weight: bold;
}

h1 {
    margin: 0;
    text-align: center;
    font-family: Georgia, serif;
    font-weight: 500;
    font-size: 28px;
}

.subtitle {
    text-align: center;
    color: #8c8177;
    margin: 8px 0 30px;
    font-size: 14px;
}

/* ERROR MESSAGE */

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 10px;
    background: rgba(200,60,60,.14);
    border: 1px solid rgba(200,60,60,.4);
    color: #f0b3b3;
    font-size: 14px;
}

/* IMAGINATION LOADING ANIMATION */

.approval-loading {
    min-height: 330px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 25px 15px;

    background: linear-gradient(145deg, #171513, #21180f);
    border: 1px solid rgba(224,122,47,.3);
    border-radius: 18px;
    overflow: hidden;
}

.imagination-loader {
    position: relative;
    width: 115px;
    height: 115px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 28px;
}

.imagination-ring {
    position: absolute;
    inset: 0;
    border: 3px solid rgba(224,122,47,.15);
    border-top-color: #e07a2f;
    border-right-color: #f6b36e;
    border-radius: 50%;
    animation: imaginationRotate 1.3s linear infinite;
}

.imagination-ring.inner {
    inset: 12px;
    border: 2px solid rgba(255,255,255,.08);
    border-bottom-color: #e07a2f;
    border-left-color: #f6b36e;
    animation: imaginationRotateReverse 2s linear infinite;
}

.imagination-logo {
    width: 68px;
    height: 68px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 18px;

    background: linear-gradient(135deg, #f6a34e, #a94d10);
    color: #17100a;

    font-family: Georgia, serif;
    font-size: 23px;
    font-weight: bold;
    letter-spacing: 2px;

    box-shadow: 0 0 30px rgba(224,122,47,.35);
    animation: imaginationPulse 2s ease-in-out infinite;
}

.imagination-brand {
    color: #f3ece3;
    font-family: Georgia, serif;
    font-size: 19px;
    letter-spacing: 2px;
    font-weight: bold;
    margin-bottom: 7px;
    animation: brandGlow 2s ease-in-out infinite;
}

.imagination-subtitle {
    color: #c18b5b;
    font-size: 10px;
    letter-spacing: 3px;
    margin-bottom: 25px;
}

.imagination-dots {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.imagination-dots span {
    width: 8px;
    height: 8px;
    background: #e07a2f;
    border-radius: 50%;
    animation: dotLoading 1.2s infinite ease-in-out;
}

.imagination-dots span:nth-child(2) {
    animation-delay: .2s;
}

.imagination-dots span:nth-child(3) {
    animation-delay: .4s;
}

.approval-loading p {
    color: #a99b8e;
    font-size: 12px;
    margin-top: 18px;
}

@keyframes imaginationRotate {
    to {
        transform: rotate(360deg);
    }
}

@keyframes imaginationRotateReverse {
    to {
        transform: rotate(-360deg);
    }
}

@keyframes imaginationPulse {
    0%, 100% {
        transform: scale(.94);
        box-shadow: 0 0 15px rgba(224,122,47,.2);
    }
    50% {
        transform: scale(1.06);
        box-shadow: 0 0 35px rgba(224,122,47,.55);
    }
}

@keyframes brandGlow {
    0%, 100% {
        opacity: .65;
    }
    50% {
        opacity: 1;
    }
}

@keyframes dotLoading {
    0%, 80%, 100% {
        transform: scale(.6);
        opacity: .4;
    }
    40% {
        transform: scale(1.2);
        opacity: 1;
    }
}

/* LOGIN FORM */

.field {
    margin-bottom: 18px;
}

label {
    display: block;
    margin-bottom: 8px;
    color: #b3aaa1;
    font-size: 12px;
    letter-spacing: 2px;
    text-transform: uppercase;
}

input[type="email"],
input[type="password"] {
    width: 100%;
    padding: 15px;
    border-radius: 10px;
    background: #100f0e;
    color: #f3ece3;
    border: 1px solid #2c2724;
    font-size: 15px;
    outline: none;
    transition: .3s;
}

input:focus {
    border-color: #e07a2f;
    box-shadow: 0 0 0 4px rgba(224,122,47,.15);
}

button {
    width: 100%;
    padding: 16px;
    margin-top: 8px;
    border: 0;
    border-radius: 999px;
    background: #e07a2f;
    color: #17100a;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
    transition: .3s;
}

button:hover {
    background: #f2a35e;
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(224,122,47,.3);
}

.forgot-link,
.back-link {
    display: block;
    text-align: center;
    text-decoration: none;
}

.forgot-link {
    margin-top: 20px;
    color: #e07a2f;
    font-size: 13px;
    font-weight: bold;
}

.back-link {
    margin-top: 18px;
    color: #7a716a;
    font-size: 13px;
}

@media (max-width: 480px) {
    .login-card {
        padding: 28px 20px;
    }
}
</style>
</head>

<body>

<div class="login-shell">

    <div class="login-card">

        <div class="brand-mark">INS</div>

        <h1>Sign in</h1>

        <p class="subtitle">Imagination Nature Studio</p>

        <?php if ($waitingForApproval): ?>

            <!-- STAFF APPROVAL LOADING -->

            <div
                class="approval-loading"
                id="approvalLoading"
                role="status"
                aria-live="polite"
            >

                <div class="imagination-loader">

                    <div class="imagination-ring"></div>

                    <div class="imagination-ring inner"></div>

                    <div class="imagination-logo">
                        INS
                    </div>

                </div>

                <div class="imagination-brand">
                    IMAGINATION
                </div>

                <div class="imagination-subtitle">
                    NATURE STUDIO
                </div>

                <div class="imagination-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                <p>Please wait...</p>

            </div>

            <div class="alert" id="approvalResult" hidden></div>

        <?php elseif ($err): ?>

            <div class="alert">
                <?= e($err) ?>
            </div>

        <?php endif; ?>

        <?php if (!$waitingForApproval): ?>

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
                        autocomplete="username"
                    >

                </div>

                <div class="field">

                    <label for="password">Password</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                    >

                </div>

                <button type="submit">
                    Sign in
                </button>

            </form>

        <?php endif; ?>

        <a
            class="forgot-link"
            href="<?= e(SITE_URL) ?>/admin/forgot-password.php"
        >
            Forgot password?
        </a>

        <a
            class="back-link"
            href="<?= e(SITE_URL) ?>/"
        >
            ← Back to website
        </a>

    </div>

</div>

<?php if ($waitingForApproval): ?>

<script>
(() => {

    const loading = document.getElementById('approvalLoading');
    const result = document.getElementById('approvalResult');

    let stopped = false;

    async function checkApproval() {

        if (stopped) return;

        try {

            const response = await fetch(
                '<?= e(SITE_URL) ?>/admin/login.php?check_staff_approval=1',
                {
                    method: 'GET',
                    cache: 'no-store',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }
            );

            if (!response.ok) {
                throw new Error('Server response error');
            }

            const data = await response.json();

            if (data.status === 'approved' && data.redirect) {

                stopped = true;

                loading.querySelector('.imagination-brand').textContent =
                    'APPROVED';

                loading.querySelector('.imagination-subtitle').textContent =
                    'WELCOME TO THE TEAM';

                loading.querySelector('p').textContent =
                    'Redirecting to your dashboard...';

                window.location.href = data.redirect;

                return;
            }

            if (
                data.status === 'rejected' ||
                data.status === 'suspended' ||
                data.status === 'blocked'
            ) {

                stopped = true;

                loading.hidden = true;
                result.hidden = false;

                if (data.status === 'rejected') {
                    result.textContent =
                        'Your access request was rejected. Please contact the administrator.';
                } else {
                    result.textContent =
                        'Your staff access is not active. Please contact the administrator.';
                }

                return;
            }

        } catch (error) {

            // Keep the animation running and retry.
        }

        if (!stopped) {
            window.setTimeout(checkApproval, 5000);
        }
    }

    window.setTimeout(checkApproval, 1500);

})();
</script>

<?php endif; ?>

</body>
</html>
