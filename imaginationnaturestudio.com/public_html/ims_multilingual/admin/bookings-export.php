<?php
/**
 * Admin — export filtered bookings as CSV.
 * Uses the same filters as bookings.php.
 */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

$status   = $_GET['status']   ?? '';
$search   = trim($_GET['q']   ?? '');
$svc      = (int)($_GET['service'] ?? 0);
$dateFrom = $_GET['from']     ?? '';
$dateTo   = $_GET['to']       ?? '';

$sql = "SELECT b.booking_number, b.booking_date, b.start_time, b.end_time,
               b.total_amount, b.deposit_amount, b.amount_paid, b.balance,
               b.payment_method, b.payment_status, b.booking_status,
               b.location, b.event_type, b.number_of_people, b.created_at,
               c.full_name, c.phone, c.whatsapp, c.email, c.company,
               s.name AS service_name, p.name AS package_name
        FROM bookings b
        JOIN customers c ON c.id = b.customer_id
        JOIN services  s ON s.id = b.service_id
        LEFT JOIN packages p ON p.id = b.package_id
        WHERE 1=1";
$args = [];

if (in_array($status, ['pending','confirmed','completed','cancelled'], true)) {
    $sql .= " AND b.booking_status = ?";
    $args[] = $status;
}
if ($svc > 0) {
    $sql .= " AND b.service_id = ?";
    $args[] = $svc;
}
if ($search !== '') {
    $sql .= " AND (b.booking_number LIKE ?
                   OR c.full_name LIKE ?
                   OR c.email LIKE ?
                   OR c.phone LIKE ?)";
    $like = '%' . $search . '%';
    array_push($args, $like, $like, $like, $like);
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $sql .= " AND b.booking_date >= ?";
    $args[] = $dateFrom;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $sql .= " AND b.booking_date <= ?";
    $args[] = $dateTo;
}
$sql .= " ORDER BY b.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

/* ---------- Output CSV ---------- */
$filename = 'bookings-' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

/* UTF-8 BOM so Excel opens it correctly */
fwrite($out, "\xEF\xBB\xBF");

/* Header row */
fputcsv($out, [
    'Booking #',
    'Created',
    'Event Date',
    'Start',
    'End',
    'Customer',
    'Phone',
    'WhatsApp',
    'Email',
    'Company',
    'Service',
    'Package',
    'Location',
    'Event Type',
    'People',
    'Total (RWF)',
    'Deposit (RWF)',
    'Paid (RWF)',
    'Balance (RWF)',
    'Payment Method',
    'Payment Status',
    'Booking Status',
]);

/* Data rows */
foreach ($rows as $r) {
    fputcsv($out, [
        $r['booking_number'],
        $r['created_at'],
        $r['booking_date'],
        substr($r['start_time'], 0, 5),
        substr($r['end_time'], 0, 5),
        $r['full_name'],
        $r['phone'],
        $r['whatsapp'],
        $r['email'],
        $r['company'],
        $r['service_name'],
        $r['package_name'],
        $r['location'],
        $r['event_type'],
        $r['number_of_people'],
        number_format((float)$r['total_amount'], 2, '.', ''),
        number_format((float)$r['deposit_amount'], 2, '.', ''),
        number_format((float)$r['amount_paid'], 2, '.', ''),
        number_format((float)$r['balance'], 2, '.', ''),
        $r['payment_method'],
        $r['payment_status'],
        $r['booking_status'],
    ]);
}

fclose($out);
exit;