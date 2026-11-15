<?php

require_once __DIR__ . '/../includes/functions.php';

require_login();

$pageTitle = 'Website Visitors';

$pdo = db();


/* =========================================================
   STATISTICS
   ========================================================= */

$today = (int)$pdo->query("
    SELECT COUNT(*)
    FROM website_visits
    WHERE DATE(visited_at) = CURDATE()
")->fetchColumn();


$thisWeek = (int)$pdo->query("
    SELECT COUNT(*)
    FROM website_visits
    WHERE YEARWEEK(visited_at, 1) = YEARWEEK(CURDATE(), 1)
")->fetchColumn();


$thisMonth = (int)$pdo->query("
    SELECT COUNT(*)
    FROM website_visits
    WHERE YEAR(visited_at) = YEAR(CURDATE())
      AND MONTH(visited_at) = MONTH(CURDATE())
")->fetchColumn();


$totalVisits = (int)$pdo->query("
    SELECT COUNT(*)
    FROM website_visits
")->fetchColumn();


/* =========================================================
   DEVICE / BROWSER / OS
   ========================================================= */

$devices = $pdo->query("
    SELECT
        device_type,
        COUNT(*) AS total
    FROM website_visits
    GROUP BY device_type
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);


$browsers = $pdo->query("
    SELECT
        browser,
        COUNT(*) AS total
    FROM website_visits
    GROUP BY browser
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);


$systems = $pdo->query("
    SELECT
        operating_system,
        COUNT(*) AS total
    FROM website_visits
    GROUP BY operating_system
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   LAST 30 DAYS
   ========================================================= */

$dailyVisits = $pdo->query("
    SELECT
        DATE(visited_at) AS visit_date,
        COUNT(*) AS total
    FROM website_visits
    WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY DATE(visited_at)
    ORDER BY visit_date ASC
")->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   RECENT VISITORS
   ========================================================= */

$recentVisitors = $pdo->query("
    SELECT
        device_type,
        browser,
        operating_system,
        visited_at
    FROM website_visits
    ORDER BY visited_at DESC
    LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);


require __DIR__ . '/includes/admin-header.php';

?>

<style>

/* =========================================================
   WEBSITE VISITORS PAGE
   ========================================================= */

.visitor-page {
    padding: 10px 0 40px;
}

.visitor-title {
    margin-bottom: 25px;
}

.visitor-title h1 {
    margin: 0 0 6px;
    font-size: 30px;
    font-weight: 800;
}

.visitor-title p {
    margin: 0;
    opacity: .65;
}


/* =========================================================
   STAT CARDS
   ========================================================= */

.visitor-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 25px;
}

.visitor-card {
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.09);
    border-radius: 18px;
    padding: 22px;
    box-shadow: 0 12px 30px rgba(0,0,0,.15);
}

.visitor-card .icon {
    font-size: 25px;
    margin-bottom: 12px;
}

.visitor-card .label {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: .65;
}

.visitor-card .number {
    font-size: 32px;
    font-weight: 800;
    margin-top: 7px;
}


/* =========================================================
   BOXES
   ========================================================= */

.visitor-box {
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 18px;
    padding: 22px;
    margin-bottom: 20px;
}

.visitor-box h2 {
    margin: 0 0 18px;
    font-size: 19px;
}


/* =========================================================
   TWO COLUMN GRID
   ========================================================= */

.visitor-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}


/* =========================================================
   ROWS
   ========================================================= */

.visitor-row {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 11px 0;
    border-bottom: 1px solid rgba(255,255,255,.07);
}

.visitor-row:last-child {
    border-bottom: 0;
}


/* =========================================================
   TABLE
   ========================================================= */

.visitor-table {
    width: 100%;
    border-collapse: collapse;
}

.visitor-table th,
.visitor-table td {
    text-align: left;
    padding: 13px 10px;
    border-bottom: 1px solid rgba(255,255,255,.07);
}

.visitor-table th {
    font-size: 12px;
    text-transform: uppercase;
    opacity: .65;
}


/* =========================================================
   CHART
   ========================================================= */

.visitor-chart {
    display: flex;
    align-items: flex-end;
    gap: 7px;
    height: 230px;
    padding-top: 20px;
}

.chart-column {
    flex: 1;
    min-width: 0;
    height: 100%;
    display: flex;
    align-items: flex-end;
}

.chart-bar {
    width: 100%;
    min-height: 4px;
    border-radius: 7px 7px 2px 2px;
    background: linear-gradient(
        180deg,
        #f2a35e,
        #e07a2f
    );
}

.chart-date {
    font-size: 9px;
    opacity: .5;
    text-align: center;
    margin-top: 7px;
}


/* =========================================================
   EMPTY STATE
   ========================================================= */

.empty-state {
    padding: 30px 10px;
    text-align: center;
    opacity: .6;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 900px) {

    .visitor-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .visitor-grid {
        grid-template-columns: 1fr;
    }

}


@media (max-width: 600px) {

    .visitor-stats {
        grid-template-columns: 1fr;
    }

    .visitor-title h1 {
        font-size: 25px;
    }

    .visitor-table {
        font-size: 12px;
    }

}

</style>


<div class="visitor-page">


    <!-- =====================================================
         PAGE TITLE
         ===================================================== -->

    <div class="visitor-title">

        <h1>
            Website Visitors
        </h1>

        <p>
            Monitor visitors coming to Imagination Nature Studio website.
        </p>

    </div>


    <!-- =====================================================
         STATISTICS
         ===================================================== -->

    <div class="visitor-stats">


        <!-- TODAY -->

        <div class="visitor-card">

            <div class="icon">
                👥
            </div>

            <div class="label">
                Today
            </div>

            <div class="number">
                <?= number_format($today) ?>
            </div>

        </div>


        <!-- THIS WEEK -->

        <div class="visitor-card">

            <div class="icon">
                📅
            </div>

            <div class="label">
                This Week
            </div>

            <div class="number">
                <?= number_format($thisWeek) ?>
            </div>

        </div>


        <!-- THIS MONTH -->

        <div class="visitor-card">

            <div class="icon">
                📊
            </div>

            <div class="label">
                This Month
            </div>

            <div class="number">
                <?= number_format($thisMonth) ?>
            </div>

        </div>


        <!-- TOTAL -->

        <div class="visitor-card">

            <div class="icon">
                🌍
            </div>

            <div class="label">
                Total Visits
            </div>

            <div class="number">
                <?= number_format($totalVisits) ?>
            </div>

        </div>

    </div>


    <!-- =====================================================
         LAST 30 DAYS CHART
         ===================================================== -->

    <div class="visitor-box">

        <h2>
            📈 Visitors — Last 30 Days
        </h2>


        <?php

        /*
         * Create a date => visitor count map.
         */

        $dailyMap = [];

        foreach ($dailyVisits as $day) {

            $dailyMap[$day['visit_date']] =
                (int)($day['total'] ?? 0);

        }


        /*
         * IMPORTANT FIX:
         * Do NOT use:
         *
         * max(1, ...array_values($dailyMap))
         *
         * because when the array is empty,
         * some PHP versions throw a TypeError.
         */

        $maxDaily = 1;

        if (!empty($dailyMap)) {

            $dailyValues = array_values($dailyMap);

            $maxDaily = max($dailyValues);

            if ($maxDaily < 1) {
                $maxDaily = 1;
            }

        }


        /*
         * Last 30 days.
         */

        $chartStart = new DateTime('today -29 days');

        $chartEnd = new DateTime('today');

        ?>


        <div class="visitor-chart">


            <?php while ($chartStart <= $chartEnd): ?>


                <?php

                $dateKey = $chartStart->format('Y-m-d');

                $count = $dailyMap[$dateKey] ?? 0;


                /*
                 * Calculate bar height safely.
                 */

                if ($count > 0) {

                    $height =
                        ($count / $maxDaily) * 100;

                    if ($height < 5) {
                        $height = 5;
                    }

                    if ($height > 100) {
                        $height = 100;
                    }

                } else {

                    $height = 2;

                }

                ?>


                <div
                    class="chart-column"
                    title="<?= e($dateKey) ?>: <?= number_format($count) ?> visitors"
                >

                    <div
                        class="chart-bar"
                        style="
                            height: <?= $height ?>%;
                            opacity: <?= $count ? '1' : '.18' ?>;
                        "
                    ></div>

                </div>


                <?php

                $chartStart->modify('+1 day');

                ?>


            <?php endwhile; ?>


        </div>


        <!-- CHART DATE LABELS -->

        <div
            style="
                display:flex;
                justify-content:space-between;
                opacity:.5;
                font-size:11px;
                margin-top:6px;
            "
        >

            <span>
                <?= e(date('M d', strtotime('-29 days'))) ?>
            </span>

            <span>
                <?= e(date('M d')) ?>
            </span>

        </div>


    </div>


    <!-- =====================================================
         DEVICES / BROWSERS
         ===================================================== -->

    <div class="visitor-grid">


        <!-- DEVICES -->

        <div class="visitor-box">

            <h2>
                📱 Devices
            </h2>


            <?php if ($devices): ?>


                <?php foreach ($devices as $device): ?>

                    <div class="visitor-row">

                        <strong>
                            <?= e(
                                $device['device_type']
                                ?: 'Unknown'
                            ) ?>
                        </strong>

                        <span>
                            <?= number_format(
                                (int)$device['total']
                            ) ?>
                        </span>

                    </div>

                <?php endforeach; ?>


            <?php else: ?>


                <div class="empty-state">
                    No visitor data yet.
                </div>


            <?php endif; ?>


        </div>


        <!-- BROWSERS -->

        <div class="visitor-box">

            <h2>
                🌐 Browsers
            </h2>


            <?php if ($browsers): ?>


                <?php foreach ($browsers as $browser): ?>

                    <div class="visitor-row">

                        <strong>
                            <?= e(
                                $browser['browser']
                                ?: 'Unknown'
                            ) ?>
                        </strong>

                        <span>
                            <?= number_format(
                                (int)$browser['total']
                            ) ?>
                        </span>

                    </div>

                <?php endforeach; ?>


            <?php else: ?>


                <div class="empty-state">
                    No browser data yet.
                </div>


            <?php endif; ?>


        </div>


    </div>


    <!-- =====================================================
         OPERATING SYSTEMS
         ===================================================== -->

    <div class="visitor-box">

        <h2>
            💻 Operating Systems
        </h2>


        <?php if ($systems): ?>


            <?php foreach ($systems as $system): ?>

                <div class="visitor-row">

                    <strong>
                        <?= e(
                            $system['operating_system']
                            ?: 'Unknown'
                        ) ?>
                    </strong>

                    <span>
                        <?= number_format(
                            (int)$system['total']
                        ) ?>
                    </span>

                </div>

            <?php endforeach; ?>


        <?php else: ?>


            <div class="empty-state">
                No operating system data yet.
            </div>


        <?php endif; ?>


    </div>


    <!-- =====================================================
         RECENT VISITORS
         ===================================================== -->

    <div class="visitor-box">

        <h2>
            🕒 Recent Visitors
        </h2>


        <div style="overflow-x:auto">


            <table class="visitor-table">


                <thead>

                    <tr>

                        <th>
                            Device
                        </th>

                        <th>
                            Browser
                        </th>

                        <th>
                            Operating System
                        </th>

                        <th>
                            Visited At
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if ($recentVisitors): ?>


                    <?php foreach ($recentVisitors as $visitor): ?>


                        <tr>

                            <td>
                                <?= e(
                                    $visitor['device_type']
                                    ?: 'Unknown'
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    $visitor['browser']
                                    ?: 'Unknown'
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    $visitor['operating_system']
                                    ?: 'Unknown'
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    $visitor['visited_at']
                                ) ?>
                            </td>

                        </tr>


                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="4"
                            class="empty-state"
                        >
                            No visitors recorded yet.
                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>


            </table>


        </div>


    </div>


</div>