<?php
/**
 * Imagination Nature Studio
 * Admin Security — Force Logout
 */

require_once __DIR__ . '/../../includes/functions.php';

require_admin_only();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/admin/security/sessions.php');
}

if (!verify_csrf($_POST['csrf'] ?? null)) {
    http_response_code(403);
    exit('Invalid security token.');
}

$sessionId = (int)($_POST['session_id'] ?? 0);

if ($sessionId <= 0) {
    flash('err', 'Invalid session selected.');
    redirect(SITE_URL . '/admin/security/sessions.php');
}

$pdo = db();

/*
 * Get the session before revoking it.
 */
$stmt = $pdo->prepare("
    SELECT id, admin_id
    FROM admin_sessions
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$sessionId]);

$target = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$target) {
    flash('err', 'Session not found.');
    redirect(SITE_URL . '/admin/security/sessions.php');
}

/*
 * Revoke the selected session.
 */
$stmt = $pdo->prepare("
    UPDATE admin_sessions
    SET revoked_at = NOW()
    WHERE id = ?
      AND revoked_at IS NULL
");
$stmt->execute([$sessionId]);

if ($stmt->rowCount() > 0) {
    flash(
        'ok',
        'The selected device has been logged out successfully.'
    );
} else {
    flash(
        'err',
        'This session was already logged out or revoked.'
    );
}

/*
 * If the admin revoked the current browser session,
 * destroy the local PHP session as well.
 */
$currentSessionId = (int)($_SESSION['admin_session_id'] ?? 0);

if ($currentSessionId === $sessionId) {
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

    header('Location: ' . SITE_URL . '/admin/login.php?session=revoked');
    exit;
}

redirect(SITE_URL . '/admin/security/sessions.php');
