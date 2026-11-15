<?php
/**
 * Admin entry point — redirect to dashboard or login.
 */
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    header('Location: ' . SITE_URL . '/admin/dashboard.php');
} else {
    header('Location: ' . SITE_URL . '/admin/login.php');
}
exit;
