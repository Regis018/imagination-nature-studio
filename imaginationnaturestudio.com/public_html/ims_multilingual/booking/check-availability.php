<?php
/**
 * Returns JSON list of hourly slots for a given date.
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

$date = $_GET['date'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['slots' => []]);
    exit;
}
if (strtotime($date) < strtotime('today')) {
    echo json_encode(['slots' => []]);
    exit;
}

$slots = build_time_slots('09:00', '21:00', 60);
$out   = [];

foreach ($slots as $start) {
    $endTs = strtotime("$date $start") + 3600;
    $end   = date('H:i', $endTs);
    $out[] = [
        'time'      => $start,
        'available' => is_slot_available($date, $start, $end),
    ];
}

echo json_encode(['slots' => $out]);