<?php
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = db();

    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    $role = $_SESSION['admin_role'] ?? '';

    if ($adminId > 0) {

        if ($role === 'staff') {

            /*
             * STAFF LOGOUT:
             * Revoke all active database sessions.
             * Return staff account to pending approval.
             */
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE admin_sessions
                SET revoked_at = NOW()
                WHERE admin_id = ?
                  AND revoked_at IS NULL
            ");
            $stmt->execute([$adminId]);

            $stmt = $pdo->prepare("
                UPDATE admins
                SET approval_status = 'pending',
                    approved_by = NULL,
                    approved_at = NULL
                WHERE id = ?
                  AND role = 'staff'
            ");
            $stmt->execute([$adminId]);

            $pdo->commit();

        } else {

            /*
             * ADMIN LOGOUT:
             * Revoke only the current admin session.
             */
            revoke_current_admin_session();
        }
    }

} catch (Throwable $e) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[ADMIN LOGOUT] ' . $e->getMessage());
}

/*
 * Clear PHP session.
 */
$_SESSION = [];

/*
 * Remove session cookie.
 */
if (ini_get('session.use_cookies')) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

/*
 * Destroy session.
 */
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

/*
 * Redirect to login.
 */
header('Location: ' . SITE_URL . '/admin/login.php');
exit;
