<?php
declare(strict_types=1);

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string {
    global $CONFIG;
    $base = rtrim($CONFIG['app']['base_url'] ?? '', '/');
    return $base . '/' . ltrim($path, '/');
}

function slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

function redirect(string $path): void {
    header('Location: ' . url($path));
    exit;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void {
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
        http_response_code(419);
        echo 'Invalid or expired form token. Please reload and try again.';
        exit;
    }
}

function setting(string $key, ?string $default = null): ?string {
    global $pdo;
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    $st = $pdo->prepare('SELECT setting_value FROM store_settings WHERE setting_key = ? LIMIT 1');
    $st->execute([$key]);
    $v = $st->fetchColumn();
    $cache[$key] = ($v === false || $v === null) ? $default : (string)$v;
    return $cache[$key];
}

function set_setting(string $key, string $value): void {
    global $pdo;
    $st = $pdo->prepare('INSERT INTO store_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $st->execute([$key, $value]);
}

function format_price($amount): string {
    return number_format((float)$amount, 0, '.', ',') . ' ' . (setting('currency', 'RWF') ?? 'RWF');
}

function is_admin_logged_in(): bool {
    return !empty($_SESSION['admin_user_id']);
}

function require_admin(): void {
    if (!is_admin_logged_in()) {
        redirect('admin/login.php');
    }
}

function get_effective_price(array $product): float {
    $sale = $product['sale_price'] ?? null;
    $base = (float)$product['base_price'];
    return ($sale !== null && (float)$sale > 0 && (float)$sale < $base) ? (float)$sale : $base;
}

/**
 * Fetch products with optional filters.
 */
function fetch_products(array $opts = []): array {
    global $pdo;
    $where = ['p.status = 1'];
    $params = [];

    if (!empty($opts['category_id'])) { $where[] = 'p.category_id = :cat'; $params[':cat'] = (int)$opts['category_id']; }
    if (!empty($opts['league_id']))   { $where[] = 'p.league_id = :lg';   $params[':lg']  = (int)$opts['league_id']; }
    if (!empty($opts['featured']))    { $where[] = 'p.featured = 1'; }
    if (!empty($opts['q'])) {
        $where[] = '(p.name LIKE :q OR p.description LIKE :q OR p.product_code LIKE :q)';
        $params[':q'] = '%' . $opts['q'] . '%';
    }
    if (!empty($opts['slug'])) { $where[] = 'p.slug = :slug'; $params[':slug'] = $opts['slug']; }

    $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                   l.name AS league_name, l.slug AS league_slug
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN leagues l ON l.id = p.league_id
            WHERE ' . implode(' AND ', $where);

    $sort = $opts['sort'] ?? 'newest';
    $sortMap = [
        'newest'     => 'p.created_at DESC',
        'price_asc'  => 'p.base_price ASC',
        'price_desc' => 'p.base_price DESC',
        'name_asc'   => 'p.name ASC',
        'name_desc'  => 'p.name DESC',
    ];
    $sql .= ' ORDER BY ' . ($sortMap[$sort] ?? 'p.created_at DESC');

    if (!empty($opts['limit'])) {
        $sql .= ' LIMIT ' . (int)$opts['limit'];
        if (!empty($opts['offset'])) $sql .= ' OFFSET ' . (int)$opts['offset'];
    }

    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function count_products(array $opts = []): int {
    global $pdo;
    $where = ['p.status = 1'];
    $params = [];
    if (!empty($opts['category_id'])) { $where[] = 'p.category_id = :cat'; $params[':cat'] = (int)$opts['category_id']; }
    if (!empty($opts['league_id']))   { $where[] = 'p.league_id = :lg';   $params[':lg']  = (int)$opts['league_id']; }
    if (!empty($opts['q'])) {
        $where[] = '(p.name LIKE :q OR p.description LIKE :q OR p.product_code LIKE :q)';
        $params[':q'] = '%' . $opts['q'] . '%';
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM products p WHERE ' . implode(' AND ', $where));
    $st->execute($params);
    return (int)$st->fetchColumn();
}

function get_product_sizes(int $productId): array {
    global $pdo;
    $st = $pdo->prepare('SELECT * FROM product_sizes WHERE product_id = ? ORDER BY price ASC');
    $st->execute([$productId]);
    return $st->fetchAll();
}

function get_cart_count(): int {
    $n = 0;
    foreach ($_SESSION['cart'] ?? [] as $item) $n += (int)$item['qty'];
    return $n;
}