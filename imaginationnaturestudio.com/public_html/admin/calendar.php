<?php
/**
 * Admin — Premium Booking Calendar.
 * Shows BOTH online bookings AND walk-in bookings.
 * Keeps the existing booking flow and database structure.
 *
 * Added:
 * - Search by client / booking reference / service
 * - Filter by Online / Walk-in / status
 * - Monthly statistics
 * - Total / paid / balance on events
 * - Better mobile layout
 * - Keeps the existing online and walk-in routes
 */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo   = db();
$year  = (int)($_GET['y'] ?? date('Y'));
$month = (int)($_GET['m'] ?? date('n'));

if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$firstDay     = strtotime("$year-$month-01");
$daysInMonth  = (int)date('t', $firstDay);
$startWeekday = (int)date('N', $firstDay); // 1 = Monday

/* =========================================================
   Filters
   ========================================================= */
$search = trim((string)($_GET['q'] ?? ''));
$typeFilter = strtolower(trim((string)($_GET['type'] ?? 'all')));
$statusFilter = strtolower(trim((string)($_GET['status'] ?? 'all')));

if (!in_array($typeFilter, ['all', 'online', 'walkin'], true)) {
    $typeFilter = 'all';
}

if (!in_array($statusFilter, ['all', 'pending', 'confirmed', 'completed', 'cancelled'], true)) {
    $statusFilter = 'all';
}

/* =========================================================
   1. Online bookings + all wedding event dates
   Calendar date = actual event date:
   - Regular online booking: bookings.booking_date
   - Wedding: every row in booking_dates.event_date
   ========================================================= */
$stmt = $pdo->prepare(
  "SELECT b.id, b.booking_number, b.booking_date, b.start_time, b.end_time,
          b.booking_status, b.total_amount, b.amount_paid, b.balance,
          c.full_name, c.phone, s.name AS service_name
   FROM bookings b
   JOIN customers c ON c.id = b.customer_id
   JOIN services s ON s.id = b.service_id
   WHERE (MONTH(b.booking_date) = ? AND YEAR(b.booking_date) = ?)
      OR EXISTS (
          SELECT 1 FROM booking_dates bd
          WHERE bd.booking_id = b.id
            AND MONTH(bd.event_date) = ?
            AND YEAR(bd.event_date) = ?
      )
   ORDER BY b.booking_date, b.start_time"
);
$stmt->execute([$month, $year, $month, $year]);
$onlineBookings = $stmt->fetchAll();

/* Load wedding dates for the retrieved bookings in one query. */
$onlineIds = array_values(array_unique(array_map(
    static function ($row) { return (int)$row['id']; },
    $onlineBookings
)));
$weddingDatesByBooking = [];

if ($onlineIds) {
    $placeholders = implode(',', array_fill(0, count($onlineIds), '?'));
    $dateStmt = $pdo->prepare(
        "SELECT booking_id, event_date, event_type, start_time, end_time
         FROM booking_dates
         WHERE booking_id IN ($placeholders)
         ORDER BY event_date, start_time"
    );
    $dateStmt->execute($onlineIds);

    foreach ($dateStmt->fetchAll() as $dateRow) {
        $weddingDatesByBooking[(int)$dateRow['booking_id']][] = $dateRow;
    }
}

/* =========================================================
   2. Walk-in bookings
   Calendar date = event_date. If an older walk-in has no
   event_date, keep it visible on its booking_date.
   ========================================================= */
$stmt = $pdo->prepare(
  "SELECT id, customer_name AS full_name, description AS service_name,
          booking_date, event_date, status AS booking_status,
          total_amount, advance, balance
   FROM walkin_bookings
   WHERE MONTH(COALESCE(event_date, booking_date)) = ?
     AND YEAR(COALESCE(event_date, booking_date)) = ?
   ORDER BY COALESCE(event_date, booking_date)"
);
$stmt->execute([$month, $year]);
$walkinBookings = $stmt->fetchAll();

/* =========================================================
   3. Merge events by their actual event dates
   ========================================================= */
$byDay = [];
$allEvents = [];

foreach ($onlineBookings as $b) {
    $bookingId = (int)$b['id'];
    $weddingRows = $weddingDatesByBooking[$bookingId] ?? [];

    // If wedding dates exist, show every wedding day separately.
    // Otherwise, the main booking_date is the event date.
    $datesToShow = $weddingRows ?: [[
        'event_date' => $b['booking_date'],
        'event_type' => '',
        'start_time' => $b['start_time'],
        'end_time' => $b['end_time'],
    ]];

    foreach ($datesToShow as $eventDate) {
        $calendarDate = (string)($eventDate['event_date'] ?? '');
        if ($calendarDate === '' ||
            date('n', strtotime($calendarDate)) != $month ||
            date('Y', strtotime($calendarDate)) != $year) {
            continue;
        }

        $start = $eventDate['start_time'] ?? $b['start_time'];
        $end = $eventDate['end_time'] ?? $b['end_time'];
        $eventType = trim((string)($eventDate['event_type'] ?? ''));

        $allEvents[] = [
            'type'       => 'online',
            'id'         => $bookingId,
            'ref'        => $b['booking_number'],
            'name'       => $b['full_name'],
            'phone'      => $b['phone'] ?? '',
            'service'    => $eventType !== ''
                ? $b['service_name'] . ' — ' . $eventType
                : $b['service_name'],
            'date'       => $calendarDate,
            'time'       => $start ? substr($start, 0, 5) : '—',
            'end_time'   => $end ? substr($end, 0, 5) : '',
            'status'     => strtolower((string)$b['booking_status']),
            'total'      => (float)$b['total_amount'],
            'paid'       => (float)$b['amount_paid'],
            'balance'    => (float)$b['balance'],
        ];
    }
}

foreach ($walkinBookings as $b) {
    $calendarDate = $b['event_date'] ?: $b['booking_date'];

    $allEvents[] = [
        'type'       => 'walkin',
        'id'         => (int)$b['id'],
        'ref'        => 'WALKIN-' . (int)$b['id'],
        'name'       => $b['full_name'],
        'phone'      => '',
        'service'    => $b['service_name'],
        'date'       => $calendarDate,
        'time'       => '—',
        'end_time'   => '',
        'status'     => strtolower((string)$b['booking_status']),
        'total'      => (float)$b['total_amount'],
        'paid'       => (float)$b['advance'],
        'balance'    => (float)$b['balance'],
    ];
}

/* Apply UI filters without changing the database flow. */
$filteredEvents = [];

foreach ($allEvents as $event) {
    if ($typeFilter !== 'all' && $event['type'] !== $typeFilter) {
        continue;
    }

    if ($statusFilter !== 'all' && $event['status'] !== $statusFilter) {
        continue;
    }

    if ($search !== '') {
        $haystack = strtolower(
            ($event['name'] ?? '') . ' ' .
            ($event['ref'] ?? '') . ' ' .
            ($event['service'] ?? '') . ' ' .
            ($event['phone'] ?? '')
        );

        if (strpos($haystack, strtolower($search)) === false) {
            continue;
        }
    }

    $filteredEvents[] = $event;

    $day = (int)date('j', strtotime($event['date']));
    $byDay[$day][] = $event;
}

/* Sort each day by time / event label. */
foreach ($byDay as $d => $items) {
    usort($byDay[$d], function ($a, $b) {
        return strcmp((string)$a['time'], (string)$b['time']);
    });
}

/* =========================================================
   4. Monthly statistics — based on the visible filter result
   ========================================================= */
$stats = [
    'total'     => count($filteredEvents),
    'online'    => 0,
    'walkin'    => 0,
    'pending'   => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'value'     => 0.0,
    'paid'      => 0.0,
    'balance'   => 0.0,
];

foreach ($filteredEvents as $event) {
    if ($event['type'] === 'online') {
        $stats['online']++;
    } else {
        $stats['walkin']++;
    }

    $status = $event['status'];
    if (isset($stats[$status])) {
        $stats[$status]++;
    }

    $stats['value']   += $event['total'];
    $stats['paid']    += $event['paid'];
    $stats['balance'] += max(0, $event['balance']);
}

/* =========================================================
   Helpers
   ========================================================= */
function cal_money(float $amount): string
{
    return number_format($amount, 0, '.', ',') . ' RWF';
}

function cal_query(array $extra = []): string
{
    $params = array_merge([
        'y'      => $_GET['y'] ?? date('Y'),
        'm'      => $_GET['m'] ?? date('n'),
        'q'      => $_GET['q'] ?? '',
        'type'   => $_GET['type'] ?? 'all',
        'status' => $_GET['status'] ?? 'all',
    ], $extra);

    return http_build_query($params);
}

/* Prev / next month */
$prevM = $month - 1 ?: 12;
$prevY = $month - 1 ? $year : $year - 1;
$nextM = $month % 12 + 1;
$nextY = $month == 12 ? $year + 1 : $year;

$todayUrl = 'calendar.php?' . http_build_query([
    'y' => date('Y'),
    'm' => date('n'),
]);

require __DIR__ . '/includes/admin-header.php';
?>

<style>
/* =========================================================
   PREMIUM CALENDAR
   ========================================================= */
.cal-wrap {
    max-width: 1600px;
    margin: 0 auto;
}

.cal-nav {
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:1rem;
    margin-bottom:1rem;
}

.cal-nav h1 {
    margin:0;
    font-family:var(--font-serif);
    font-size:1.7rem;
    color:#f1e8dc;
}

.cal-nav-sub {
    margin:.3rem 0 0;
    color:#8f867d;
    font-size:.82rem;
}

.cal-nav-actions {
    display:flex;
    gap:.55rem;
    flex-wrap:wrap;
}

.cal-legend {
    display:flex;
    gap:1rem;
    flex-wrap:wrap;
    margin-bottom:1rem;
    font-size:.76rem;
    color:#8f867d;
}

.cal-legend span {
    display:inline-flex;
    align-items:center;
    gap:.4rem;
}

.cal-legend i {
    display:inline-block;
    width:10px;
    height:10px;
    border-radius:3px;
}

/* Stats */
.cal-stats {
    display:grid;
    grid-template-columns:repeat(8, minmax(0,1fr));
    gap:.7rem;
    margin:1rem 0;
}

.cal-stat {
    background:linear-gradient(145deg,#151311,#100f0e);
    border:1px solid #292522;
    border-radius:12px;
    padding:.85rem .9rem;
    min-width:0;
    box-shadow:0 8px 24px rgba(0,0,0,.14);
}

.cal-stat-label {
    color:#7f7770;
    font-size:.65rem;
    letter-spacing:.09em;
    text-transform:uppercase;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.cal-stat-value {
    margin-top:.25rem;
    color:#eee5da;
    font-weight:900;
    font-size:1.08rem;
}

.cal-stat-value.orange { color:#e5a06a; }
.cal-stat-value.green { color:#66c47a; }
.cal-stat-value.blue { color:#76a9ee; }
.cal-stat-value.purple { color:#bd91df; }
.cal-stat-value.red { color:#e97878; }

/* Filters */
.cal-filters {
    display:grid;
    grid-template-columns:minmax(220px,1.6fr) repeat(2,minmax(150px,.65fr)) auto auto;
    gap:.65rem;
    align-items:end;
    background:#12100f;
    border:1px solid #292522;
    border-radius:14px;
    padding:.85rem;
    margin:1rem 0 1.15rem;
}

.cal-filter-group label {
    display:block;
    margin-bottom:.35rem;
    color:#827a72;
    font-size:.66rem;
    text-transform:uppercase;
    letter-spacing:.08em;
}

.cal-filter-group input,
.cal-filter-group select {
    width:100%;
    box-sizing:border-box;
    background:#0d0c0b;
    color:#e9e1d8;
    border:1px solid #302c29;
    border-radius:9px;
    padding:.65rem .7rem;
    outline:none;
}

.cal-filter-group input:focus,
.cal-filter-group select:focus {
    border-color:rgba(224,122,47,.7);
    box-shadow:0 0 0 3px rgba(224,122,47,.08);
}

.cal-filter-actions {
    display:flex;
    gap:.5rem;
    align-items:end;
}

.cal-filter-actions .btn {
    white-space:nowrap;
}

/* Calendar */
.cal-weekdays {
    display:grid;
    grid-template-columns:repeat(7,1fr);
    gap:.45rem;
    margin-bottom:.45rem;
}

.cal-weekdays div {
    text-align:center;
    font-size:.68rem;
    letter-spacing:.15em;
    text-transform:uppercase;
    color:#756e67;
    padding:.45rem 0;
}

.cal-grid {
    display:grid;
    grid-template-columns:repeat(7,1fr);
    gap:.45rem;
}

.cal-day {
    min-height:145px;
    background:linear-gradient(145deg,#131110,#11100f);
    border:1px solid #282421;
    border-radius:11px;
    padding:.5rem;
    font-size:.78rem;
    position:relative;
    display:flex;
    flex-direction:column;
    gap:.3rem;
    overflow:hidden;
}

.cal-day.is-today {
    background:linear-gradient(145deg,#1b1713,#141210);
    border-color:rgba(224,122,47,.58);
    box-shadow:inset 0 0 0 1px rgba(224,122,47,.06),0 7px 22px rgba(0,0,0,.16);
}

.cal-day.is-empty {
    background:transparent;
    border:1px dashed #1c1917;
    box-shadow:none;
}

.cal-day-num {
    display:flex;
    align-items:center;
    justify-content:space-between;
    font-weight:800;
    color:#c8bfb5;
    font-size:.82rem;
    margin-bottom:.15rem;
}

.cal-day.is-today .cal-day-num {
    color:var(--accent-2);
}

.cal-day-count {
    font-size:.58rem;
    color:#625c56;
    font-weight:700;
}

.cal-event {
    display:block;
    padding:.38rem .45rem;
    border-radius:7px;
    font-size:.69rem;
    line-height:1.3;
    text-decoration:none;
    color:#e6dccd;
    border-left:3px solid #666;
    background:#171513;
    transition:background .2s,transform .2s,border-color .2s;
    cursor:pointer;
    overflow:hidden;
}

.cal-event:hover {
    background:#221e1a;
    transform:translateX(2px);
}

.cal-event.online[data-status="pending"] { border-left-color:#e07a2f; }
.cal-event.online[data-status="confirmed"] { border-left-color:#3aa855; }
.cal-event.online[data-status="completed"] { border-left-color:#508cdc; }
.cal-event.online[data-status="cancelled"] { border-left-color:#b33; opacity:.72; }

.cal-event.walkin {
    border-left-color:#a06bd4;
    background:#1a1520;
}

.cal-event.walkin:hover { background:#241b2e; }

.cal-event .ev-top {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:.35rem;
}

.cal-event .ev-time {
    font-weight:800;
    color:var(--accent-2);
}

.cal-event.walkin .ev-time { color:#c9a3e8; }

.cal-event .ev-name {
    display:block;
    color:#e6dccd;
    font-weight:700;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    margin-top:.08rem;
}

.cal-event .ev-service {
    display:block;
    color:#857d76;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    margin-top:.08rem;
}

.cal-event .ev-money {
    display:flex;
    gap:.4rem;
    flex-wrap:wrap;
    color:#77706a;
    font-size:.6rem;
    margin-top:.15rem;
}

.cal-event .ev-money strong {
    color:#bcb3aa;
}

.cal-event .ev-type {
    display:inline-block;
    font-size:.56rem;
    letter-spacing:.08em;
    text-transform:uppercase;
    color:#706962;
    margin-top:.1rem;
}

.cal-event.walkin .ev-type { color:#9a7fb0; }

.cal-empty-message {
    padding:2.5rem 1rem;
    text-align:center;
    color:#746d66;
    background:#12100f;
    border:1px solid #292522;
    border-radius:12px;
    margin-top:1rem;
}

.cal-results-note {
    color:#746d66;
    font-size:.72rem;
    margin:.55rem 0 .8rem;
}

.cal-results-note strong { color:#bdb4aa; }

@media (max-width:1200px) {
    .cal-stats { grid-template-columns:repeat(4,1fr); }
    .cal-filters { grid-template-columns:1fr 1fr 1fr; }
}

@media (max-width:900px) {
    .cal-grid,.cal-weekdays { gap:.25rem; }
    .cal-day { min-height:105px; padding:.35rem; }
    .cal-day-num { font-size:.74rem; }
    .cal-event { padding:.3rem .35rem; font-size:.64rem; }
    .cal-event .ev-money,.cal-event .ev-service { display:none; }
    .cal-event .ev-type { display:none; }
}

@media (max-width:640px) {
    .cal-nav h1 { font-size:1.35rem; }
    .cal-nav-actions { width:100%; }
    .cal-nav-actions .btn { flex:1; text-align:center; }

    .cal-stats {
        grid-template-columns:repeat(2,1fr);
        gap:.5rem;
    }

    .cal-stat { padding:.7rem; }
    .cal-stat-value { font-size:.92rem; }

    .cal-filters {
        grid-template-columns:1fr;
        padding:.7rem;
    }

    .cal-filter-actions { width:100%; }
    .cal-filter-actions .btn { flex:1; text-align:center; }

    .cal-weekdays div {
        font-size:.55rem;
        letter-spacing:.04em;
    }

    .cal-day {
        min-height:82px;
        padding:.25rem;
        border-radius:7px;
    }

    .cal-day-num {
        font-size:.68rem;
    }

    .cal-day-count { display:none; }

    .cal-event {
        border-left-width:2px;
        border-radius:5px;
        padding:.23rem .25rem;
        font-size:.57rem;
        line-height:1.2;
    }

    .cal-event .ev-time { font-size:.57rem; }
    .cal-event .ev-name { margin-top:0; }
}

@media print {
    .cal-filters,.cal-nav-actions,.admin-side,.admin-topbar,.cal-legend { display:none !important; }
    .cal-wrap { max-width:none; }
    .cal-day { min-height:120px; }
}
</style>

<div class="cal-wrap">

    <div class="cal-nav">
        <div>
            <h1><?= e(date('F Y', $firstDay)) ?></h1>
            <p class="cal-nav-sub">
                <?= (int)$stats['total'] ?> visible booking<?= $stats['total'] == 1 ? '' : 's' ?> this month
            </p>
        </div>

        <div class="cal-nav-actions">
            <a class="btn btn-sm btn-ghost"
               href="calendar.php?y=<?= $prevY ?>&m=<?= $prevM ?>">← Previous</a>

            <a class="btn btn-sm btn-ghost" href="<?= e($todayUrl) ?>">Today</a>

            <a class="btn btn-sm btn-ghost"
               href="calendar.php?y=<?= $nextY ?>&m=<?= $nextM ?>">Next →</a>
        </div>
    </div>

    <div class="cal-legend">
        <span><i style="background:#e07a2f"></i> Pending</span>
        <span><i style="background:#3aa855"></i> Confirmed</span>
        <span><i style="background:#508cdc"></i> Completed</span>
        <span><i style="background:#b33"></i> Cancelled</span>
        <span><i style="background:#a06bd4"></i> Walk-in</span>
    </div>

    <!-- Monthly statistics -->
    <div class="cal-stats">
        <div class="cal-stat">
            <div class="cal-stat-label">Total Bookings</div>
            <div class="cal-stat-value"><?= (int)$stats['total'] ?></div>
        </div>
        <div class="cal-stat">
            <div class="cal-stat-label">Online</div>
            <div class="cal-stat-value blue"><?= (int)$stats['online'] ?></div>
        </div>
        <div class="cal-stat">
            <div class="cal-stat-label">Walk-in</div>
            <div class="cal-stat-value purple"><?= (int)$stats['walkin'] ?></div>
        </div>
        <div class="cal-stat">
            <div class="cal-stat-label">Pending</div>
            <div class="cal-stat-value orange"><?= (int)$stats['pending'] ?></div>
        </div>
        <div class="cal-stat">
            <div class="cal-stat-label">Confirmed</div>
            <div class="cal-stat-value green"><?= (int)$stats['confirmed'] ?></div>
        </div>
        <div class="cal-stat">
            <div class="cal-stat-label">Completed</div>
            <div class="cal-stat-value blue"><?= (int)$stats['completed'] ?></div>
        </div>
        <div class="cal-stat">
            <div class="cal-stat-label">Paid / Collected</div>
            <div class="cal-stat-value green"><?= e(cal_money($stats['paid'])) ?></div>
        </div>
        <div class="cal-stat">
            <div class="cal-stat-label">Balance Due</div>
            <div class="cal-stat-value red"><?= e(cal_money($stats['balance'])) ?></div>
        </div>
    </div>

    <!-- Search / filters -->
    <form class="cal-filters" method="get" action="calendar.php">
        <input type="hidden" name="y" value="<?= (int)$year ?>">
        <input type="hidden" name="m" value="<?= (int)$month ?>">

        <div class="cal-filter-group">
            <label for="cal-q">Search</label>
            <input
                id="cal-q"
                type="search"
                name="q"
                value="<?= e($search) ?>"
                placeholder="Client, booking number, service..."
            >
        </div>

        <div class="cal-filter-group">
            <label for="cal-type">Type</label>
            <select id="cal-type" name="type">
                <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All bookings</option>
                <option value="online" <?= $typeFilter === 'online' ? 'selected' : '' ?>>Online</option>
                <option value="walkin" <?= $typeFilter === 'walkin' ? 'selected' : '' ?>>Walk-in</option>
            </select>
        </div>

        <div class="cal-filter-group">
            <label for="cal-status">Status</label>
            <select id="cal-status" name="status">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>

        <div class="cal-filter-actions">
            <button class="btn btn-sm" type="submit">🔎 Filter</button>
            <a class="btn btn-sm btn-ghost"
               href="calendar.php?y=<?= (int)$year ?>&m=<?= (int)$month ?>">Reset</a>
        </div>
    </form>

    <?php if ($search !== '' || $typeFilter !== 'all' || $statusFilter !== 'all'): ?>
        <div class="cal-results-note">
            Showing <strong><?= (int)$stats['total'] ?></strong> booking<?= $stats['total'] == 1 ? '' : 's' ?> after filters.
        </div>
    <?php endif; ?>

    <!-- Weekday header -->
    <div class="cal-weekdays">
        <div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div><div>Sun</div>
    </div>

    <!-- Month grid -->
    <div class="cal-grid">

        <?php for ($i = 1; $i < $startWeekday; $i++): ?>
            <div class="cal-day is-empty"></div>
        <?php endfor; ?>

        <?php for ($d = 1; $d <= $daysInMonth; $d++):
            $isToday = (
                $d == (int)date('j') &&
                $month == (int)date('n') &&
                $year  == (int)date('Y')
            );
            $events = $byDay[$d] ?? [];
        ?>
            <div class="cal-day<?= $isToday ? ' is-today' : '' ?>">
                <div class="cal-day-num">
                    <span><?= $d ?></span>
                    <?php if ($events): ?>
                        <span class="cal-day-count"><?= count($events) ?></span>
                    <?php endif; ?>
                </div>

                <?php foreach ($events as $ev): ?>
                    <?php
                        if ($ev['type'] === 'online') {
                            $url = 'booking-view.php?id=' . (int)$ev['id']
                   . '&return=calendar&return_y=' . $year
                   . '&return_m=' . $month;
                            $cls = 'cal-event online';
                            $typeLabel = 'Online';
                        } else {
                            /* Existing system has walkin-bookings.php as its known route. */
                            $url = 'walkin-booking-view.php?id=' . (int)$ev['id']
                   . '&return=calendar&return_y=' . $year
                   . '&return_m=' . $month;
                            $cls = 'cal-event walkin';
                            $typeLabel = 'Walk-in';
                        }

                        $moneyTitle = 'Total: ' . cal_money($ev['total'])
                            . ' · Paid: ' . cal_money($ev['paid'])
                            . ' · Balance: ' . cal_money(max(0, $ev['balance']));
                    ?>
                    <a
                        href="<?= e($url) ?>"
                        class="<?= e($cls) ?>"
                        data-status="<?= e($ev['status']) ?>"
                        title="<?= e($ev['name'] . ' · ' . $ev['service'] . ' · ' . $moneyTitle) ?>"
                    >
                        <span class="ev-top">
                            <span class="ev-time"><?= e($ev['time']) ?></span>
                            <?php if ($ev['status']): ?>
                                <span class="ev-type"><?= e($ev['status']) ?></span>
                            <?php endif; ?>
                        </span>

                        <span class="ev-name"><?= e($ev['name']) ?></span>
                        <span class="ev-service"><?= e($ev['service'] ?: 'Booking') ?></span>

                        <span class="ev-money">
                            <span>Total <strong><?= e(cal_money($ev['total'])) ?></strong></span>
                            <span>Bal <strong><?= e(cal_money(max(0, $ev['balance']))) ?></strong></span>
                        </span>

                        <span class="ev-type"><?= e($typeLabel) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endfor; ?>

        <?php
            $totalCells = $startWeekday - 1 + $daysInMonth;
            $remainder  = $totalCells % 7;
            if ($remainder !== 0) {
                for ($i = 0; $i < 7 - $remainder; $i++) {
                    echo '<div class="cal-day is-empty"></div>';
                }
            }
        ?>

    </div>

    <?php if (!$filteredEvents): ?>
        <div class="cal-empty-message">
            <div style="font-size:1.6rem;margin-bottom:.35rem">📅</div>
            No bookings match the current filters for <?= e(date('F Y', $firstDay)) ?>.
        </div>
    <?php endif; ?>

</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
