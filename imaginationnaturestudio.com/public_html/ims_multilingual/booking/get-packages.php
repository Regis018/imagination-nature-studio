<?php
/**
 * Returns JSON list of active packages for a given service.
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

$sid = (int)($_GET['service_id'] ?? 0);
if ($sid <= 0) {
    echo json_encode(['packages' => []]);
    exit;
}

$stmt = db()->prepare(
    "SELECT id, name, price, duration
     FROM packages
     WHERE service_id=? AND status='active'
     ORDER BY price ASC"
);
$stmt->execute([$sid]);

echo json_encode(['packages' => $stmt->fetchAll()]);