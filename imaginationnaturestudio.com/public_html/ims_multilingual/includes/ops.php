<?php
/**
 * Daily Studio Operations — shared helpers.
 * Loads automatically when required by ops pages.
 */
require_once __DIR__ . '/functions.php';

/* ---------- Date helpers ---------- */
function ops_today(): string { return date('Y-m-d'); }

function ops_month_start(?string $m = null): string {
    $m = $m ?: date('Y-m');
    return $m . '-01';
}
function ops_month_end(?string $m = null): string {
    $m = $m ?: date('Y-m');
    return date('Y-m-t', strtotime($m . '-01'));
}

function ops_format_date(?string $d): string {
    if (!$d) return '—';
    return date('d.m.Y', strtotime($d));
}

/* ---------- Totals ---------- */
function ops_sales_total(string $from, string $to): array {
    $stmt = db()->prepare(
        "SELECT
            COALESCE(SUM(total),0)        AS total,
            COALESCE(SUM(amount_paid),0)  AS paid,
            COALESCE(SUM(balance),0)      AS balance,
            COUNT(*)                      AS count
         FROM sales
         WHERE sale_date BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $to]);
    return $stmt->fetch() ?: ['total'=>0,'paid'=>0,'balance'=>0,'count'=>0];
}

function ops_expenses_total(string $from, string $to): array {
    $stmt = db()->prepare(
        "SELECT COALESCE(SUM(amount),0) AS total, COUNT(*) AS count
         FROM expenses
         WHERE expense_date BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $to]);
    return $stmt->fetch() ?: ['total'=>0,'count'=>0];
}

function ops_bookings_total(string $from, string $to): array {
    $stmt = db()->prepare(
        "SELECT
            COALESCE(SUM(total_amount),0) AS total,
            COALESCE(SUM(advance),0)      AS advance,
            COALESCE(SUM(balance),0)      AS balance,
            COUNT(*)                      AS count
         FROM walkin_bookings
         WHERE booking_date BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $to]);
    return $stmt->fetch() ?: ['total'=>0,'advance'=>0,'balance'=>0,'count'=>0];
}

/* ---------- CSV output helper ---------- */
function ops_csv_download(string $filename, array $headerRow, array $dataRows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM for Excel

    fputcsv($out, $headerRow);
    foreach ($dataRows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

/* ---------- Categories ---------- */
function ops_expense_categories(): array {
    return [
        'rent'        => 'Rent',
        'transport'   => 'Transport',
        'supplies'    => 'Supplies',
        'salaries'    => 'Salaries',
        'utilities'   => 'Utilities',
        'materials'   => 'Materials',
        'tickets'     => 'Tickets',
        'other'       => 'Other',
    ];
}

function ops_product_categories(): array {
    return [
        'photos'   => 'Photos',
        'passport' => 'Passport',
        'cadre'    => 'Cadre',
        'banner'   => 'Banner',
        'book'     => 'Book/Album',
        'other'    => 'Other',
    ];
}

/* ---------- Money format ---------- */
function ops_money($n): string {
    return number_format((float)$n, 0, '.', ',');
}