<?php
/**
 * Imagination Nature Studio - unified private iCalendar feed.
 *
 * Install at: public_html/calendar-feed.php
 * Includes:
 * - Website / online bookings (bookings.booking_date)
 * - Every wedding day (booking_dates.event_date)
 * - Walk-in bookings (walkin_bookings.event_date)
 *
 * Do not share the tokenized subscription URL publicly.
 * To revoke access, replace FEED_TOKEN with a new random value.
 */

require_once __DIR__ . '/includes/functions.php';

date_default_timezone_set('Africa/Kigali');

const FEED_TOKEN = '-Qu7hmwkH6uO0jtYyXhfPzL_oF7uwJeSSrNqkMZ55Q4';
const FEED_TIMEZONE = 'Africa/Kigali';

$providedToken = (string)($_GET['token'] ?? '');
if ($providedToken === '' || !hash_equals(FEED_TOKEN, $providedToken)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Not found\n");
}

function ics_escape($value): string
{
    $value = (string)$value;
    $value = str_replace("\\", "\\\\", $value);
    $value = str_replace(["\r\n", "\r", "\n"], "\\n", $value);
    return str_replace([";", ","], ["\\;", "\\,"], $value);
}

/**
 * Fold an iCalendar content line without splitting UTF-8 characters.
 */
function ics_fold(string $line): string
{
    $result = '';
    $chunk = '';
    $bytes = 0;

    foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) as $character) {
        $characterBytes = strlen($character);
        if ($bytes + $characterBytes > 73) {
            $result .= $chunk . "\r\n ";
            $chunk = '';
            $bytes = 1; // continuation whitespace counts as one octet
        }
        $chunk .= $character;
        $bytes += $characterBytes;
    }

    return $result . $chunk;
}

function ics_kigali_datetime(string $date, ?string $time, int $defaultDurationMinutes = 60): ?array
{
    $date = trim($date);
    $time = trim((string)$time);

    if ($date === '') {
        return null;
    }

    if ($time === '') {
        $time = '09:00:00';
    } elseif (preg_match('/^\d{2}:\d{2}$/', $time)) {
        $time .= ':00';
    }

    try {
        $timezone = new DateTimeZone(FEED_TIMEZONE);
        $start = new DateTimeImmutable($date . ' ' . $time, $timezone);
        $end = $start->modify('+' . $defaultDurationMinutes . ' minutes');

        return [
            'start' => $start->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'),
            'default_end' => $end->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'),
            'start_local' => $start
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function ics_event(
    array &$lines,
    string $uid,
    string $summary,
    string $description,
    string $date,
    ?string $startTime,
    ?string $endTime,
    string $status = '',
    ?string $location = null,
    bool $allDay = false,
    bool $addReminder = true
): void {
    if ($date === '') {
        return;
    }

    $cancelled = strtolower($status) === 'cancelled';

    $lines[] = 'BEGIN:VEVENT';
    $lines[] = 'UID:' . $uid . '@imaginationnaturestudio.com';
    $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');

    if ($allDay) {
        try {
            $day = new DateTimeImmutable($date, new DateTimeZone(FEED_TIMEZONE));
            $lines[] = 'DTSTART;VALUE=DATE:' . $day->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $day->modify('+1 day')->format('Ymd');
        } catch (Throwable $e) {
            return;
        }
    } else {
        $dateTimes = ics_kigali_datetime($date, $startTime);
        if (!$dateTimes) {
            return;
        }

        $lines[] = 'DTSTART:' . $dateTimes['start'];

        $endUtc = null;
        if ($endTime !== null && trim($endTime) !== '') {
            $endTime = trim($endTime);
            if (preg_match('/^\d{2}:\d{2}$/', $endTime)) {
                $endTime .= ':00';
            }
            try {
                $end = new DateTimeImmutable($date . ' ' . $endTime, new DateTimeZone(FEED_TIMEZONE));
                $endUtc = $end->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
            } catch (Throwable $e) {
                $endUtc = null;
            }
        }
        $lines[] = 'DTEND:' . ($endUtc ?: $dateTimes['default_end']);
    }

    $displaySummary = $cancelled ? '[CANCELLED] ' . $summary : $summary;
    $lines[] = 'SUMMARY:' . ics_escape($displaySummary);

    if ($location !== null && trim($location) !== '') {
        $lines[] = 'LOCATION:' . ics_escape($location);
    }

    if ($description !== '') {
        $lines[] = 'DESCRIPTION:' . ics_escape($description);
    }

    $lines[] = 'STATUS:' . ($cancelled ? 'CANCELLED' : 'CONFIRMED');

    // A one-hour reminder is appropriate for timed events, not all-day entries.
    if ($addReminder && !$allDay && !$cancelled) {
        $lines[] = 'BEGIN:VALARM';
        $lines[] = 'ACTION:DISPLAY';
        $lines[] = 'DESCRIPTION:' . ics_escape('Reminder: ' . $displaySummary);
        $lines[] = 'TRIGGER:-PT1H';
        $lines[] = 'END:VALARM';
    }

    $lines[] = 'END:VEVENT';
}

try {
    $pdo = db();

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Imagination Nature Studio//Unified Booking Calendar//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'X-WR-CALNAME:Imagination Nature Studio Bookings',
        'X-WR-CALDESC:Private online, wedding and walk-in booking calendar',
        'X-WR-TIMEZONE:Africa/Kigali',
        'REFRESH-INTERVAL;VALUE=DURATION:PT15M',
        'X-PUBLISHED-TTL:PT15M'
    ];

    /*
     * 1. Standard online bookings.
     * Wedding bookings are represented by each row in booking_dates below,
     * to avoid showing the first wedding day twice.
     */
    $onlineSql = "
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
        INNER JOIN customers c ON c.id = b.customer_id
        LEFT JOIN services s ON s.id = b.service_id
        LEFT JOIN packages p ON p.id = b.package_id
        ORDER BY b.booking_date ASC, b.start_time ASC
    ";

    $onlineBookings = $pdo->query($onlineSql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($onlineBookings as $booking) {
        $serviceName = (string)($booking['service_name'] ?? '');
        $eventType = (string)($booking['event_type'] ?? '');
        $isWedding = (stripos($serviceName, 'wedding') !== false)
                  || (stripos($eventType, 'wedding') !== false);

        if ($isWedding) {
            continue;
        }

        $name = trim((string)($booking['full_name'] ?? ''));
        $summaryParts = array_filter([
            $eventType,
            $serviceName,
            $name
        ], static fn($value) => trim((string)$value) !== '');

        $summary = $summaryParts
            ? implode(' - ', $summaryParts)
            : 'Photography Booking';

        $descriptionParts = [
            'Booking: ' . (string)($booking['booking_number'] ?? ''),
            'Status: ' . (string)($booking['booking_status'] ?? '')
        ];

        if (!empty($booking['package_name'])) {
            $descriptionParts[] = 'Package: ' . $booking['package_name'];
        }

        $status = strtolower((string)($booking['booking_status'] ?? ''));

        ics_event(
            $lines,
            'online-booking-' . (int)$booking['id'],
            $summary,
            implode("\n", $descriptionParts),
            (string)$booking['booking_date'],
            $booking['start_time'] ?? null,
            $booking['end_time'] ?? null,
            $status,
            $booking['location'] ?? null,
            false,
            true
        );
    }

    /*
     * 2. Every wedding day from booking_dates.
     * Uses the schema shown in the existing booking process:
     * id, booking_id, event_date, event_type, start_time, end_time.
     */
    $weddingSql = "
        SELECT
            bd.id AS wedding_day_id,
            bd.event_date,
            bd.event_type AS wedding_event_type,
            bd.start_time,
            bd.end_time,
            b.booking_number,
            b.booking_status,
            b.location,
            c.full_name,
            s.name AS service_name
        FROM booking_dates bd
        INNER JOIN bookings b ON b.id = bd.booking_id
        INNER JOIN customers c ON c.id = b.customer_id
        LEFT JOIN services s ON s.id = b.service_id
        ORDER BY bd.event_date ASC, bd.start_time ASC
    ";

    $weddingDays = $pdo->query($weddingSql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($weddingDays as $day) {
        $clientName = trim((string)($day['full_name'] ?? ''));
        $eventName = trim((string)($day['wedding_event_type'] ?? 'Wedding event'));
        $summary = $eventName . ' - ' . $clientName;

        $descriptionParts = [
            'Booking: ' . (string)($day['booking_number'] ?? ''),
            'Service: ' . (string)($day['service_name'] ?? 'Wedding Photography'),
            'Status: ' . (string)($day['booking_status'] ?? '')
        ];

        ics_event(
            $lines,
            'wedding-day-' . (int)$day['wedding_day_id'],
            $summary,
            implode("\n", $descriptionParts),
            (string)$day['event_date'],
            $day['start_time'] ?? null,
            $day['end_time'] ?? null,
            strtolower((string)($day['booking_status'] ?? '')),
            $day['location'] ?? null,
            false,
            true
        );
    }

    /*
     * 3. Walk-in bookings.
     * Calendar date is event_date, not booking_date.
     * Current walkin_bookings schema has no event start/end time, so these
     * are all-day calendar entries.
     */
    $walkinSql = "
        SELECT
            id,
            customer_name,
            customer_contact,
            description,
            event_date,
            status,
            notes
        FROM walkin_bookings
        WHERE event_date IS NOT NULL
          AND event_date <> ''
        ORDER BY event_date ASC
    ";

    $walkins = $pdo->query($walkinSql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($walkins as $walkin) {
        $clientName = trim((string)($walkin['customer_name'] ?? ''));
        $service = trim((string)($walkin['description'] ?? 'Walk-in booking'));
        $summary = $clientName . ' - ' . $service;

        $descriptionParts = [
            'Type: Walk-in',
            'Status: ' . (string)($walkin['status'] ?? '')
        ];

        if (!empty($walkin['customer_contact'])) {
            $descriptionParts[] = 'Contact: ' . $walkin['customer_contact'];
        }
        if (!empty($walkin['notes'])) {
            $descriptionParts[] = 'Notes: ' . $walkin['notes'];
        }

        ics_event(
            $lines,
            'walkin-booking-' . (int)$walkin['id'],
            $summary,
            implode("\n", $descriptionParts),
            (string)$walkin['event_date'],
            null,
            null,
            strtolower((string)($walkin['status'] ?? '')),
            null,
            true,
            false
        );
    }

    $lines[] = 'END:VCALENDAR';

    $calendar = implode("\r\n", array_map('ics_fold', $lines)) . "\r\n";

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: inline; filename="imagination-nature-studio-bookings.ics"');
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');

    echo $calendar;
    exit;

} catch (Throwable $e) {
    error_log('[calendar-feed] ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Calendar feed could not be generated. Please check the server error log.\n";
    exit;
}
?>
