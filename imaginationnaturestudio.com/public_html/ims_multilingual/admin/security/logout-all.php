<?php
/**
 * Imagination Nature Studio
 * Admin Security — Log Out All Other Devices
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

$adminId = (int)($_SESSION['admin_id'] ?? 0);
$currentSessionId = (int)($_SESSION['admin_session_id'] ?? 0);

if ($adminId <= 0 || $currentSessionId <= 0) {
    flash('err', 'Your secure session could not be identified.');
    redirect(SITE_URL . '/admin/security/sessions.php');
}

$pdo = db();

/*
 * Revoke every session belonging to this admin
 * except the current browser session.
 */
$stmt = $pdo->prepare("
    UPDATE admin_sessions
    SET revoked_at = NOW()
    WHERE admin_id = ?
      AND id <> ?
      AND revoked_at IS NULL
");
$stmt->execute([
    $adminId,
    $currentSessionId
]);

$count = $stmt->rowCount();

if ($count > 0) {
    flash(
        'ok',
        $count . ' other device session(s) have been logged out.'
    );
} else {
    flash(
        'ok',
        'No other active device sessions were found.'
    );
}

redirect(SITE_URL . '/admin/security/sessions.php');
