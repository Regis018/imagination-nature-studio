<?php
require_once __DIR__ . '/../includes/ops.php';
require_admin();

$from   = $_GET['from'] ?? date('Y-m-01');
$to     = $_GET['to']   ?? date('Y-m-d');
$catF   = $_GET['cat']  ?? '';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM expenses WHERE expense_date BETWEEN ? AND ?";
$args = [$from, $to];
if ($catF !== '') { $sql .= " AND category=?"; $args[] = $catF; }
if ($search !== '') { $sql .= " AND description LIKE ?"; $args[] = "%$search%"; }
$sql .= " ORDER BY expense_date, id";

$stmt = db()->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

$csv = [];
$csv[] = ['Date','Description','Category','Amount','Staff','Notes'];

foreach ($rows as $r) {
    $csv[] = [
        $r['expense_date'], $r['description'], $r['category'],
        $r['amount'], $r['staff_name'], $r['notes'],
    ];
}

ops_csv_download('expenses-' . $from . '-to-' . $to . '.csv', ['EXPENSES REPORT'], $csv);