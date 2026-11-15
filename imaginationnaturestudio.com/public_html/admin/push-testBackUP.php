<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_once __DIR__ . '/../includes/push.php';

header('Content-Type: application/json; charset=utf-8');

$ok = ims_push_send(
    '🔔 Test Notification · Imagination Nature Studio',
    'Web Push iri gukora neza kuri iyi device.',
    SITE_URL . '/admin/bookings.php'
);

echo json_encode(['ok' => $ok]);
