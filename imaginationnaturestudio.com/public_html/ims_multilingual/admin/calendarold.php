<?php
/**
 * Admin — Booking calendar.
 * Shows BOTH online bookings AND walk-in bookings.
 * Click any booking to open its detail page.
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
   1. Online bookings
   ========================================================= */
$stmt = $pdo->prepare(
  "SELECT b.id, b.booking_number, b.booking_date, b.start_time, b.end_time,
          b.booking_status, b.total_amount,
          c.full_name, s.name AS service_name
   FROM bookings b
   JOIN customers c ON c.id = b.customer_id
   JOIN services  s ON s.id = b.service_id
   WHERE MONTH(b.booking_date) = ? AND YEAR(b.booking_date) = ?
   ORDER BY b.start_time"
);
$stmt->execute([$month, $year]);
$onlineBookings = $stmt->fetchAll();

/* =========================================================
   2. Walk-in bookings
   ========================================================= */
$stmt = $pdo->prepare(
  "SELECT id, customer_name AS full_name, description AS service_name,
          booking_date, event_date, status AS booking_status,
          total_amount, advance, balance
   FROM walkin_bookings
   WHERE MONTH(booking_date) = ? AND YEAR(booking_date) = ?
   ORDER BY booking_date"
);
$stmt->execute([$month, $year]);
$walkinBookings = $stmt->fetchAll();

/* =========================================================
   3. Merge by day
   ========================================================= */
$byDay = [];

foreach ($onlineBookings as $b) {
    $day = (int)substr($b['booking_date'], 8, 2);
    $byDay[$day][] = [
        'type'       => 'online',
        'id'         => $b['id'],
        'ref'        => $b['booking_number'],
        'name'       => $b['full_name'],
        'service'    => $b['service_name'],
        'time'       => substr($b['start_time'], 0, 5),
        'status'     => $b['booking_status'],
        'total'      => (float)$b['total_amount'],
    ];
}

foreach ($walkinBookings as $b) {
    $day = (int)substr($b['booking_date'], 8, 2);
    $byDay[$day][] = [
        'type'       => 'walkin',
        'id'         => $b['id'],
        'ref'        => 'WALKIN-' . $b['id'],
        'name'       => $b['full_name'],
        'service'    => $b['service_name'],
        'time'       => $b['event_date'] ? 'Event ' . date('d M', strtotime($b['event_date'])) : 'Walk-in',
        'status'     => $b['booking_status'],
        'total'      => (float)$b['total_amount'],
        'advance'    => (float)$b['advance'],
        'balance'    => (float)$b['balance'],
    ];
}

/* Sort each day by time */
foreach ($byDay as $d => $items) {
    usort($byDay[$d], function ($a, $b) {
        return strcmp($a['time'], $b['time']);
    });
}

/* Prev / next month */
$prevM = $month - 1 ?: 12;
$prevY = $month - 1 ? $year : $year - 1;
$nextM = $month % 12 + 1;
$nextY = $month == 12 ? $year + 1 : $year;

require __DIR__ . '/includes/admin-header.php';
?>

<style>
  .cal-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1rem;
  }
  .cal-nav h1 { margin: 0; font-family: var(--font-serif); font-size: 1.5rem; }

  .cal-legend {
    display: flex;
    gap: 1.2rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
    font-size: .8rem;
    color: #7a716a;
  }
  .cal-legend span {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
  }
  .cal-legend i {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 3px;
  }

  .cal-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: .4rem;
    margin-bottom: .4rem;
  }
  .cal-weekdays div {
    text-align: center;
    font-size: .72rem;
    letter-spacing: .15em;
    text-transform: uppercase;
    color: #7a716a;
    padding: .5rem 0;
  }

  .cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: .4rem;
  }

  .cal-day {
    min-height: 130px;
    background: #131110;
    border: 1px solid #262220;
    border-radius: 10px;
    padding: .5rem;
    font-size: .78rem;
    position: relative;
    display: flex;
    flex-direction: column;
    gap: .3rem;
  }
  .cal-day.is-today {
    background: #1c1a17;
    border-color: rgba(224,122,47,.5);
  }
  .cal-day.is-empty {
    background: transparent;
    border: 1px dashed #1d1a17;
  }

  .cal-day-num {
    font-weight: 700;
    color: #c8bfb5;
    font-size: .82rem;
    margin-bottom: .2rem;
  }
  .cal-day.is-today .cal-day-num { color: var(--accent-2); }

  .cal-event {
    display: block;
    padding: .3rem .45rem;
    border-radius: 6px;
    font-size: .72rem;
    line-height: 1.35;
    text-decoration: none;
    color: #e6dccd;
    border-left: 3px solid #666;
    background: #171513;
    transition: background .2s, transform .2s;
    cursor: pointer;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .cal-event:hover {
    background: #221e1a;
    transform: translateX(2px);
  }

  /* Online booking — colour by status */
  .cal-event.online[data-status="pending"]   { border-left-color: #e07a2f; }
  .cal-event.online[data-status="confirmed"] { border-left-color: #3aa855; }
  .cal-event.online[data-status="completed"] { border-left-color: #508cdc; }
  .cal-event.online[data-status="cancelled"] { border-left-color: #b33; opacity: .7; }

  /* Walk-in booking — purple accent */
  .cal-event.walkin {
    border-left-color: #a06bd4;
    background: #1a1520;
  }
  .cal-event.walkin:hover { background: #241b2e; }

  .cal-event .ev-time { font-weight: 700; color: var(--accent-2); }
  .cal-event.walkin .ev-time { color: #c9a3e8; }
  .cal-event .ev-name { display: block; color: #e6dccd; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .cal-event .ev-type {
    display: inline-block;
    font-size: .62rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #7a716a;
    margin-top: .1rem;
  }

  @media (max-width: 900px) {
    .cal-grid, .cal-weekdays { gap: .25rem; }
    .cal-day { min-height: 90px; padding: .35rem; font-size: .7rem; }
    .cal-day-num { font-size: .75rem; }
    .cal-event { padding: .25rem .35rem; font-size: .66rem; }
  }
  @media (max-width: 640px) {
    .cal-day { min-height: 70px; }
    .cal-event .ev-type { display: none; }
  }
</style>

<div class="cal-nav">
  <h1><?= date('F Y', $firstDay) ?></h1>

  <div style="display:flex;gap:.6rem;flex-wrap:wrap">
    <a class="btn btn-sm btn-ghost" href="?y=<?= $prevY ?>&m=<?= $prevM ?>">← Previous</a>
    <a class="btn btn-sm btn-ghost" href="?y=<?= date('Y') ?>&m=<?= date('n') ?>">Today</a>
    <a class="btn btn-sm btn-ghost" href="?y=<?= $nextY ?>&m=<?= $nextM ?>">Next →</a>
  </div>
</div>

<!-- Legend -->
<div class="cal-legend">
  <span><i style="background:#e07a2f"></i> Pending (online)</span>
  <span><i style="background:#3aa855"></i> Confirmed (online)</span>
  <span><i style="background:#508cdc"></i> Completed (online)</span>
  <span><i style="background:#b33"></i> Cancelled (online)</span>
  <span><i style="background:#a06bd4"></i> Walk-in booking</span>
</div>

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
    $isToday = ($d == (int)date('j')
             && $month == (int)date('n')
             && $year  == (int)date('Y'));
    $events = $byDay[$d] ?? [];
  ?>
    <div class="cal-day<?= $isToday ? ' is-today' : '' ?>">
      <div class="cal-day-num"><?= $d ?></div>

      <?php foreach ($events as $ev): ?>
        <?php
          if ($ev['type'] === 'online') {
              $url = 'booking-view.php?id=' . (int)$ev['id'];
              $cls = 'cal-event online';
              $typeLabel = 'Online';
          } else {
              $url = 'walkin-bookings.php';
              $cls = 'cal-event walkin';
              $typeLabel = 'Walk-in';
          }
        ?>
        <a href="<?= e($url) ?>" class="<?= $cls ?>" data-status="<?= e($ev['status']) ?>"
           title="<?= e($ev['name'] . ' · ' . $ev['service']) ?>">
          <span class="ev-time"><?= e($ev['time']) ?></span>
          <span class="ev-name"><?= e($ev['name']) ?></span>
          <span class="ev-type"><?= e($typeLabel) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endfor; ?>

  <?php
    /* Pad the end so the grid stays rectangular */
    $totalCells = $startWeekday - 1 + $daysInMonth;
    $remainder  = $totalCells % 7;
    if ($remainder !== 0) {
        for ($i = 0; $i < 7 - $remainder; $i++) {
            echo '<div class="cal-day is-empty"></div>';
        }
    }
  ?>

</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>