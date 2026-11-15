<?php
/**
 * Imagination Nature Studio — Core Helper Functions
 */

require_once __DIR__ . '/db.php';


/* =========================================================
   SESSION
   ========================================================= */

if (session_status() === PHP_SESSION_NONE) {

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}


/* =========================================================
   OUTPUT ESCAPING
   ========================================================= */

function e($s): string
{
    return htmlspecialchars(
        (string)$s,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/* =========================================================
   CSRF
   ========================================================= */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}


function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' .
        e(csrf_token()) .
        '">';
}


function verify_csrf(?string $t): bool
{
    return !empty($t)
        && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $t);
}


/* =========================================================
   AUTH — ROLE-AWARE
   ========================================================= */

function current_admin(): ?array
{
    static $cache = null;
    static $loaded = false;

    if ($loaded) {
        return $cache;
    }

    $loaded = true;

    if (empty($_SESSION['admin_id'])) {
        return $cache = null;
    }

    try {

        $stmt = db()->prepare(
            "SELECT *
             FROM admins
             WHERE id = ?
             LIMIT 1"
        );

        $stmt->execute([
            (int)$_SESSION['admin_id']
        ]);

        $row = $stmt->fetch();

        if ($row) {

            if (!array_key_exists('role', $row)) {
                $row['role'] = 'admin';
            }

            if (!array_key_exists('is_active', $row)) {
                $row['is_active'] = 1;
            }
        }

        return $cache = ($row ?: null);

    } catch (Throwable $e) {

        return $cache = null;
    }
}


function is_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}


/* =========================================================
   ADMIN SESSION SECURITY
   ========================================================= */

/**
 * Hash the current PHP session ID before storing it in DB.
 */
function admin_session_hash(): string
{
    return hash('sha256', session_id());
}


/**
 * Detect the user's device.
 */
function detect_device_name(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if (stripos($ua, 'iPhone') !== false) {
        return 'iPhone';
    }

    if (stripos($ua, 'iPad') !== false) {
        return 'iPad';
    }

    if (stripos($ua, 'Android') !== false) {
        return 'Android Device';
    }

    if (
        stripos($ua, 'Macintosh') !== false ||
        stripos($ua, 'Mac OS X') !== false
    ) {
        return 'Mac';
    }

    if (stripos($ua, 'Windows') !== false) {
        return 'Windows PC';
    }

    if (stripos($ua, 'Linux') !== false) {
        return 'Linux PC';
    }

    return 'Unknown Device';
}


/**
 * Detect browser.
 */
function detect_browser(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if (stripos($ua, 'Edg/') !== false) {
        return 'Microsoft Edge';
    }

    if (stripos($ua, 'OPR/') !== false) {
        return 'Opera';
    }

    if (stripos($ua, 'Chrome/') !== false) {
        return 'Google Chrome';
    }

    if (stripos($ua, 'Firefox/') !== false) {
        return 'Mozilla Firefox';
    }

    if (
        stripos($ua, 'Safari/') !== false &&
        stripos($ua, 'Chrome/') === false
    ) {
        return 'Safari';
    }

    return 'Unknown Browser';
}


/**
 * Detect operating system.
 */
function detect_operating_system(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if (stripos($ua, 'Windows NT') !== false) {
        return 'Windows';
    }

    if (
        stripos($ua, 'Mac OS X') !== false ||
        stripos($ua, 'Macintosh') !== false
    ) {
        return 'macOS';
    }

    if (stripos($ua, 'Android') !== false) {
        return 'Android';
    }

    if (
        stripos($ua, 'iPhone') !== false ||
        stripos($ua, 'iPad') !== false
    ) {
        return 'iOS';
    }

    if (stripos($ua, 'Linux') !== false) {
        return 'Linux';
    }

    return 'Unknown OS';
}


/**
 * Create a server-side session record.
 */
function create_admin_session(int $admin_id): bool
{
    try {

        $pdo = db();

        $sessionHash = admin_session_hash();

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $device = detect_device_name();

        $browser = detect_browser();

        $os = detect_operating_system();

        /*
         * Remove any duplicate record for this PHP session.
         */
        $stmt = $pdo->prepare(
            "DELETE FROM admin_sessions
             WHERE session_token_hash = ?"
        );

        $stmt->execute([
            $sessionHash
        ]);

        /*
         * Maximum lifetime: 8 hours.
         */
        $expires = date(
            'Y-m-d H:i:s',
            time() + (8 * 60 * 60)
        );

        $stmt = $pdo->prepare(
            "INSERT INTO admin_sessions
            (
                admin_id,
                session_token_hash,
                device_name,
                browser,
                operating_system,
                ip_address,
                user_agent,
                created_at,
                last_activity,
                expires_at
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)"
        );

        $stmt->execute([
            $admin_id,
            $sessionHash,
            $device,
            $browser,
            $os,
            $ip,
            $ua,
            $expires
        ]);

        return true;

    } catch (Throwable $e) {

        @error_log(
            '[ADMIN SESSION CREATE] ' .
            $e->getMessage(),
            3,
            ERROR_LOG_FILE
        );

        return false;
    }
}


/**
 * Validate the current database-backed session.
 *
 * If an administrator revokes this session from another device,
 * this function returns false.
 */
function validate_admin_session(): bool
{
    if (empty($_SESSION['admin_id'])) {
        return false;
    }

    try {

        $pdo = db();

        $sessionHash = admin_session_hash();

        $stmt = $pdo->prepare(
            "SELECT
                s.id,
                s.admin_id,
                s.expires_at,
                s.revoked_at,
                a.is_active
             FROM admin_sessions s
             INNER JOIN admins a
                ON a.id = s.admin_id
             WHERE s.session_token_hash = ?
             AND s.admin_id = ?
             AND s.revoked_at IS NULL
             LIMIT 1"
        );

        $stmt->execute([
            $sessionHash,
            (int)$_SESSION['admin_id']
        ]);

        $session = $stmt->fetch();

        if (!$session) {
            return false;
        }

        /*
         * Account disabled.
         */
        if ((int)$session['is_active'] !== 1) {
            return false;
        }

        /*
         * Session expired.
         */
        if (
            !empty($session['expires_at']) &&
            strtotime($session['expires_at']) < time()
        ) {
            return false;
        }

        /*
         * Update last activity.
         */
        $update = $pdo->prepare(
            "UPDATE admin_sessions
             SET last_activity = NOW()
             WHERE id = ?"
        );

        $update->execute([
            (int)$session['id']
        ]);

        return true;

    } catch (Throwable $e) {

        @error_log(
            '[ADMIN SESSION VALIDATION] ' .
            $e->getMessage(),
            3,
            ERROR_LOG_FILE
        );

        return false;
    }
}


/**
 * Revoke the current admin session.
 */
function revoke_current_admin_session(): void
{
    try {

        if (session_status() === PHP_SESSION_ACTIVE) {

            $sessionHash = admin_session_hash();

            $stmt = db()->prepare(
                "UPDATE admin_sessions
                 SET revoked_at = NOW()
                 WHERE session_token_hash = ?
                 AND revoked_at IS NULL"
            );

            $stmt->execute([
                $sessionHash
            ]);
        }

    } catch (Throwable $e) {

        @error_log(
            '[ADMIN SESSION REVOKE] ' .
            $e->getMessage(),
            3,
            ERROR_LOG_FILE
        );
    }
}


/**
 * Destroy the current PHP session and revoke its DB session.
 */
function destroy_admin_session(): void
{
    revoke_current_admin_session();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'] ?? '',
            $params['secure'],
            $params['httponly']
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}


function is_admin(): bool
{
    $a = current_admin();

    if (!$a) {
        return false;
    }

    return
        ($a['role'] ?? 'admin') === 'admin'
        &&
        (int)($a['is_active'] ?? 1) === 1;
}


function is_staff(): bool
{
    $a = current_admin();

    if (!$a) {
        return false;
    }

    return
        ($a['role'] ?? '') === 'staff'
        &&
        (int)($a['is_active'] ?? 1) === 1;
}


/**
 * Require any logged-in user
 * Admin OR Staff
 */
function require_login(): void
{
    /*
     * User is not logged in at all.
     */
    if (!is_logged_in()) {

        redirect(
            SITE_URL . '/admin/login.php'
        );
    }

    /*
     * Validate the database-backed session.
     *
     * This allows the Super Admin to remotely revoke
     * a user's session from another computer.
     */
    if (!validate_admin_session()) {

        destroy_admin_session();

        redirect(
            SITE_URL .
            '/admin/login.php?session=revoked'
        );
    }
}


/**
 * require_admin() accepts BOTH roles.
 * Existing pages keep working.
 */
function require_admin(): void
{
    require_login();
}


/**
 * Strict — full admin only.
 * Staff is redirected to daily report.
 */
function require_admin_only(): void
{
    require_login();

    if (!is_admin()) {

        flash(
            'err',
            'You do not have access to that page.'
        );

        header(
            'Location: ' .
            SITE_URL .
            '/admin/daily-report.php'
        );

        exit;
    }
}


/* Aliases for compatibility */

function require_full_admin(): void
{
    require_admin_only();
}


function require_ops(): void
{
    require_login();
}


/* =========================================================
   FLASH
   ========================================================= */

function flash(string $key, ?string $msg = null)
{
    if ($msg === null) {

        $v = $_SESSION['flash'][$key] ?? null;

        unset($_SESSION['flash'][$key]);

        return $v;
    }

    $_SESSION['flash'][$key] = $msg;

    return null;
}


/* =========================================================
   BOOKING NUMBER
   NOTE:
   Does NOT start its own transaction.
   The caller already has one open.
   ========================================================= */

function generate_booking_number(): string
{
    $pdo = db();

    $year = (int)date('Y');

    $stmt = $pdo->prepare(
        'SELECT counter
         FROM booking_counter
         WHERE year = ?
         FOR UPDATE'
    );

    $stmt->execute([$year]);

    $row = $stmt->fetch();

    if (!$row) {

        $pdo->prepare(
            'INSERT INTO booking_counter
             (year, counter)
             VALUES (?, 1)'
        )->execute([$year]);

        $next = 1;

    } else {

        $next = (int)$row['counter'] + 1;

        $pdo->prepare(
            'UPDATE booking_counter
             SET counter = ?
             WHERE year = ?'
        )->execute([
            $next,
            $year
        ]);
    }

    return sprintf(
        'INS-%d-%05d',
        $year,
        $next
    );
}


/* =========================================================
   AVAILABILITY
   ========================================================= */

function get_blocked_slots(string $date): array
{
    $stmt = db()->prepare(
        "SELECT start_time, end_time
         FROM bookings
         WHERE booking_date = ?
         AND booking_status IN
         ('pending','confirmed','completed')"
    );

    $stmt->execute([$date]);

    return $stmt->fetchAll();
}


function is_slot_available(
    string $date,
    string $start,
    string $end,
    ?int $exclude_id = null
): bool {

    $sql = "
        SELECT COUNT(*)
        FROM bookings
        WHERE booking_date = ?
        AND booking_status IN
        ('pending','confirmed','completed')
        AND NOT (
            end_time <= ?
            OR
            start_time >= ?
        )
    ";

    $params = [
        $date,
        $start,
        $end
    ];

    if ($exclude_id) {

        $sql .= " AND id <> ?";

        $params[] = $exclude_id;
    }

    $stmt = db()->prepare($sql);

    $stmt->execute($params);

    return (
        (int)$stmt->fetchColumn()
    ) === 0;
}


function build_time_slots(
    string $open = '09:00',
    string $close = '21:00',
    int $step = 60
): array {

    $slots = [];

    $start = strtotime($open);

    $end = strtotime($close);

    while (
        $start + ($step * 60) <= $end
    ) {

        $slots[] = date(
            'H:i',
            $start
        );

        $start += $step * 60;
    }

    return $slots;
}


/* =========================================================
   UTIL
   ========================================================= */

function redirect(string $path): void
{
    header(
        'Location: ' . $path
    );

    exit;
}


function valid_email(?string $e): bool
{
    return (bool)filter_var(
        (string)$e,
        FILTER_VALIDATE_EMAIL
    );
}


function rwf($n): string
{
    return 'RWF ' .
        number_format(
            (float)$n,
            0,
            '.',
            ','
        );
}


/* =========================================================
   SITE SETTINGS
   ========================================================= */

function settings(): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    try {

        $rows = db()
            ->query(
                "SELECT setting_key, setting_value
                 FROM site_settings"
            )
            ->fetchAll();

        $cache = [];

        foreach ($rows as $r) {

            $cache[
                $r['setting_key']
            ] = $r['setting_value'];
        }

    } catch (Throwable $e) {

        $cache = [];
    }

    return $cache;
}


function setting(
    string $key,
    $default = ''
) {

    $s = settings();

    return (
        array_key_exists($key, $s)
        &&
        $s[$key] !== ''
        &&
        $s[$key] !== null
    )
        ? $s[$key]
        : $default;
}


function logo_url(
    string $variant = 'default'
): ?string {

    $key =
        $variant === 'light'
        ? 'logo_light'
        : 'logo';

    $file = setting($key);

    if ($file) {

        return SITE_URL .
            '/uploads/' .
            $file;
    }

    if ($variant === 'light') {

        $file = setting('logo');

        if ($file) {

            return SITE_URL .
                '/uploads/' .
                $file;
        }
    }

    return null;
}


/* =========================================================
   HOMEPAGE
   ========================================================= */

function home_section(
    string $key
): ?array {

    static $cache = [];

    if (
        array_key_exists(
            $key,
            $cache
        )
    ) {

        return $cache[$key];
    }

    try {

        $stmt = db()->prepare(
            "SELECT *
             FROM homepage_sections
             WHERE section_key = ?
             AND is_active = 1
             LIMIT 1"
        );

        $stmt->execute([$key]);

        $row = $stmt->fetch();

    } catch (Throwable $e) {

        $row = null;
    }

    return $cache[$key] =
        ($row ?: null);
}


function home_featured(
    string $type
): array {

    try {

        $stmt = db()->prepare(
            "SELECT ref_id
             FROM homepage_featured
             WHERE type = ?
             ORDER BY sort_order, id"
        );

        $stmt->execute([$type]);

        $ids = array_map(
            'intval',
            array_column(
                $stmt->fetchAll(),
                'ref_id'
            )
        );

    } catch (Throwable $e) {

        return [];
    }

    if (!$ids) {
        return [];
    }

    $in = implode(
        ',',
        array_fill(
            0,
            count($ids),
            '?'
        )
    );

    if ($type === 'service') {

        $stmt = db()->prepare(
            "SELECT *
             FROM services
             WHERE id IN ($in)
             AND status = 'active'"
        );

    } else {

        $stmt = db()->prepare(
            "SELECT *
             FROM gallery
             WHERE id IN ($in)"
        );
    }

    $stmt->execute($ids);

    $rows = $stmt->fetchAll();

    $map = [];

    foreach ($rows as $r) {

        $map[
            (int)$r['id']
        ] = $r;
    }

    $out = [];

    foreach ($ids as $id) {

        if (isset($map[$id])) {

            $out[] = $map[$id];
        }
    }

    return $out;
}