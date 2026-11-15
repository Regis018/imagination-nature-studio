<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/ops.php';

/**
 * Imagination Nature Studio
 * Expenses CSV Export
 *
 * Admin only.
 * This file exports expenses for the selected date range.
 */

require_admin_only();

// --------------------------------------
// 1. Get and validate filters
// --------------------------------------

$from   = trim((string)($_GET['from'] ?? date('Y-m-01')));
$to     = trim((string)($_GET['to'] ?? date('Y-m-d')));
$catF   = trim((string)($_GET['cat'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));

$isValidDate = static function (string $date): bool {
    $d = DateTime::createFromFormat('!Y-m-d', $date);

    return $d !== false && $d->format('Y-m-d') === $date;
};

if (!$isValidDate($from) || !$isValidDate($to)) {
    http_response_code(400);
    exit('Invalid date format. Use YYYY-MM-DD.');
}

if ($from > $to) {
    http_response_code(400);
    exit('The start date cannot be later than the end date.');
}

// --------------------------------------
// 2. Fetch expenses
// --------------------------------------

$sql = "
    SELECT
        expense_date,
        description,
        category,
        amount,
        staff_name,
        notes
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
";

$args = [$from, $to];

if ($catF !== '') {
    $sql .= " AND category = ?";
    $args[] = $catF;
}

if ($search !== '') {
    $sql .= " AND description LIKE ?";
    $args[] = '%' . $search . '%';
}

$sql .= " ORDER BY expense_date ASC, id ASC";

$stmt = db()->prepare($sql);
$stmt->execute($args);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------
// 3. Prepare CSV data
// --------------------------------------

$csv = [];

$csv[] = [
    'Date',
    'Description',
    'Category',
    'Amount (RWF)',
    'Staff',
    'Notes'
];

foreach ($rows as $r) {
    $csv[] = [
        $r['expense_date'] ?? '',
        $r['description'] ?? '',
        $r['category'] ?? '',
        $r['amount'] ?? 0,
        $r['staff_name'] ?? '',
        $r['notes'] ?? ''
    ];
}

// --------------------------------------
// 4. Download CSV
// --------------------------------------

$filename = 'expenses-' . $from . '-to-' . $to . '.csv';

ops_csv_download(
    $filename,
    ['IMAGINATION NATURE STUDIO - EXPENSES REPORT'],
    $csv
);
