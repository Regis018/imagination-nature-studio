<?php
/**
 * Private iCalendar feed for Imagination Nature Studio bookings.
 *
 * File:
 * public_html/calendar-feed.php
 *
 * IMPORTANT:
 * The URL contains a private token. Do not publish or share it.
 */

require_once __DIR__ . '/includes/functions.php';

/*
 * Kigali timezone
 */
date_default_timezone_set('Africa/Kigali');

/*
 * Private calendar token
 */
$privateToken = 'HsQ9rrPaZfuJOkDaM2SPMO3LfJE2tJ1Z8UrngyGauZE';

if (!hash_equals($privateToken, (string)($_GET['token'] ?? ''))) {
    http_response_code(404);
    exit;
}

$pdo = db();

/*
 * Get active bookings.
 * Phone and email are intentionally NOT included.
 */
$sql = "
    SELECT
        b.id,
        b.booking_number,
        b.booking_date,
        b.start_time,
        b.end_time,
        b.location,
        b.event_type,
        b.booking_status,
        c.full_name,
        s.name AS service_name,
        p.name AS package_name
    FROM bookings b
    INNER JOIN customers c
        ON c.id = b.customer_id
    LEFT JOIN services s
        ON s.id = b.service_id
    LEFT JOIN packages p
        ON p.id = b.package_id
    WHERE b.booking_status IN ('pending','confirmed','completed')
    ORDER BY b.booking_date ASC, b.start_time ASC
";

$stmt = $pdo->query($sql);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
 * Escape text for iCalendar.
 */
function ics_escape(string $value): string
{
    $value = str_replace("\\", "\\\\", $value);
    $value = str_replace(";", "\\;", $value);
    $value = str_replace(",", "\\,", $value);
    $value = str_replace(
        ["\r\n", "\r", "\n"],
        "\\n",
        $value
    );

    return $value;
}

/*
 * Convert Kigali date/time to UTC.
 * This keeps the event time correct on iPhone.
 */
function ics_datetime_utc(string $date, string $time): string
{
    $dt = new DateTime(
        $date . ' ' . $time,
        new DateTimeZone('Africa/Kigali')
    );

    $dt->setTimezone(new DateTimeZone('UTC'));

    return $dt->format('Ymd\THis\Z');
}

/*
 * Unique ID for every booking.
 */
function ics_uid(int $id): string
{
    return 'booking-' . $id . '@imaginationnaturestudio.com';
}

/*
 * Calendar header.
 */
$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Imagination Nature Studio//Private Booking Calendar//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'X-WR-CALNAME:Imagination Nature Studio Bookings',
    'X-WR-CALDESC:Private booking calendar',
    'X-WR-TIMEZONE:Africa/Kigali'
];

/*
 * Add bookings.
 */
foreach ($bookings as $booking) {

    $start = ics_datetime_utc(
        $booking['booking_date'],
        $booking['start_time']
    );

    $end = ics_datetime_utc(
        $booking['booking_date'],
        $booking['end_time']
    );

    /*
     * Build event title.
     *
     * Example:
     * Wedding – Photography – John Doe
     */
    $titleParts = [];

    if (!empty($booking['event_type'])) {
        $titleParts[] = trim($booking['event_type']);
    }

    if (!empty($booking['service_name'])) {
        $titleParts[] = trim($booking['service_name']);
    }

    if (!empty($booking['full_name'])) {
        $titleParts[] = trim($booking['full_name']);
    }

    $summary = !empty($titleParts)
        ? implode(' – ', $titleParts)
        : 'Photography Booking';

    /*
     * Description.
     */
    $description = [];

    if (!empty($booking['booking_number'])) {
        $description[] =
            'Booking: ' . $booking['booking_number'];
    }

    if (!empty($booking['package_name'])) {
        $description[] =
            'Package: ' . $booking['package_name'];
    }

    if (!empty($booking['service_name'])) {
        $description[] =
            'Service: ' . $booking['service_name'];
    }

    if (!empty($booking['booking_status'])) {
        $description[] =
            'Status: ' . ucfirst($booking['booking_status']);
    }

    $descriptionText = implode("\n", $description);

    /*
     * Start event.
     */
    $lines[] = 'BEGIN:VEVENT';

    $lines[] =
        'UID:' . ics_uid((int)$booking['id']);

    $lines[] =
        'DTSTAMP:' . gmdate('Ymd\THis\Z');

    $lines[] =
        'DTSTART:' . $start;

    $lines[] =
        'DTEND:' . $end;

    $lines[] =
        'SUMMARY:' . ics_escape($summary);

    /*
     * Location.
     */
    if (!empty($booking['location'])) {
        $lines[] =
            'LOCATION:' . ics_escape(
                $booking['location']
            );
    }

    /*
     * Description.
     */
    if ($descriptionText !== '') {
        $lines[] =
            'DESCRIPTION:' . ics_escape(
                $descriptionText
            );
    }

    /*
     * Event status.
     */
    $lines[] = 'STATUS:CONFIRMED';

    /*
     * Reminder:
     * 1 hour before the booking.
     */
    $lines[] = 'BEGIN:VALARM';
    $lines[] = 'ACTION:DISPLAY';
    $lines[] = 'DESCRIPTION:' . ics_escape(
        'Reminder: ' . $summary
    );
    $lines[] = 'TRIGGER:-PT1H';
    $lines[] = 'END:VALARM';

    /*
     * End event.
     */
    $lines[] = 'END:VEVENT';
}

/*
 * Close calendar.
 */
$lines[] = 'END:VCALENDAR';

$calendar = implode("\r\n", $lines) . "\r\n";

/*
 * Calendar headers.
 */
header(
    'Content-Type: text/calendar; charset=utf-8'
);

header(
    'Content-Disposition: inline; filename="imagination-nature-studio-bookings.ics"'
);

header(
    'Cache-Control: private, no-cache, no-store, must-revalidate'
);

header('Pragma: no-cache');
header('Expires: 0');

echo $calendar;
exit;
?>