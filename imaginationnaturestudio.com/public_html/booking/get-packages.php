<?php
/**
 * Imagination Nature Studio
 * Public booking package loader.
 *
 * Returns packages for a selected service, including their prices.
 */
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $serviceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;

    if ($serviceId <= 0) {
        echo json_encode(['packages' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = db();

    // Detect the actual packages-table columns so this endpoint
    // remains compatible with the existing database structure.
    $columns = $pdo->query('DESCRIBE packages')->fetchAll(PDO::FETCH_COLUMN);

    if (!$columns) {
        throw new RuntimeException('Packages table could not be read.');
    }

    $required = ['id', 'name', 'price'];
    foreach ($required as $column) {
        if (!in_array($column, $columns, true)) {
            throw new RuntimeException('Missing packages column: ' . $column);
        }
    }

    $hasServiceId = in_array('service_id', $columns, true);
    $hasStatus    = in_array('status', $columns, true);

    if (!$hasServiceId) {
        throw new RuntimeException('Packages table has no service_id column.');
    }

    $sql = 'SELECT id, name, price FROM packages WHERE service_id = ?';

    if ($hasStatus) {
        $sql .= " AND status = 'active'";
    }

    $sql .= ' ORDER BY name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$serviceId]);

    $packages = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $packages[] = [
            'id'    => (int)$row['id'],
            'name'  => (string)$row['name'],
            'price' => (float)$row['price'],
        ];
    }

    echo json_encode(
        ['packages' => $packages],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(
        [
            'packages' => [],
            'error' => 'Unable to load packages.'
        ],
        JSON_UNESCAPED_UNICODE
    );
}
