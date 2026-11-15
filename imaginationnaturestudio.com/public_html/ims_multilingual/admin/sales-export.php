<?php
require_once __DIR__ . '/../includes/ops.php';
require_admin();

$from   = $_GET['from'] ?? date('Y-m-01');
$to     = $_GET['to']   ?? date('Y-m-d');
$search = trim($_GET['q'] ?? '');

$sql = "SELECT s.*, p.category FROM sales s LEFT JOIN products p ON p.id=s.product_id
        WHERE s.sale_date BETWEEN ? AND ?";
$args = [$from, $to];
if ($search !== '') {
    $sql .= " AND (s.customer_name LIKE ? OR s.customer_phone LIKE ? OR s.product_name LIKE ?)";
    $like = "%$search%";
    array_push($args, $like, $like, $like);
}
$sql .= " ORDER BY s.sale_date, s.id";

$stmt = db()->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

$csv = [];
$csv[] = ['Date','Customer','Phone','Product','Category','Description','Qty','Unit','Total','Paid','Balance','Method','Staff','Notes'];

foreach ($rows as $r) {
    $csv[] = [
        $r['sale_date'], $r['customer_name'], $r['customer_phone'],
        $r['product_name'], $r['category'], $r['description'],
        $r['quantity'], $r['unit_price'], $r['total'],
        $r['amount_paid'], $r['balance'], $r['payment_method'],
        $r['staff_name'], $r['notes'],
    ];
}

ops_csv_download('sales-' . $from . '-to-' . $to . '.csv', ['SALES REPORT'], $csv);