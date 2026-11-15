<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pdo = db();

$pageTitle = 'Promotion Analytics';

try {
    /*
     * ============================================================
     * PROMOTION ANALYTICS
     * Uses the existing promotions table:
     * - views
     * - whatsapp_clicks
     * - status
     * ============================================================
     */

    $summaryStmt = $pdo->query("
        SELECT
            COUNT(*) AS total_promotions,
            COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) AS active_promotions,
            COALESCE(SUM(views), 0) AS total_views,
            COALESCE(SUM(whatsapp_clicks), 0) AS total_clicks
        FROM promotions
    ");

    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $totalPromotions = (int)($summary['total_promotions'] ?? 0);
    $activePromotions = (int)($summary['active_promotions'] ?? 0);
    $totalViews = (int)($summary['total_views'] ?? 0);
    $totalClicks = (int)($summary['total_clicks'] ?? 0);

    $overallRate = $totalViews > 0
        ? ($totalClicks / $totalViews) * 100
        : 0;

    /*
     * One row per promotion.
     * The existing counters are cumulative, so this dashboard
     * intentionally reports all-time performance.
     */
    $stmt = $pdo->query("
        SELECT
            id,
            title,
            slug,
            image,
            status,
            views,
            whatsapp_clicks,
            starts_at,
            ends_at,
            created_at
        FROM promotions
        ORDER BY whatsapp_clicks DESC, views DESC, id DESC
    ");

    $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $bestClicks = $promotions[0] ?? null;

    $bestViews = null;
    foreach ($promotions as $promotion) {
        if (
            $bestViews === null ||
            (int)$promotion['views'] > (int)$bestViews['views']
        ) {
            $bestViews = $promotion;
        }
    }

    $maxViews = 0;
    foreach ($promotions as $promotion) {
        $maxViews = max($maxViews, (int)$promotion['views']);
    }

    $maxClicks = 0;
    foreach ($promotions as $promotion) {
        $maxClicks = max($maxClicks, (int)$promotion['whatsapp_clicks']);
    }

} catch (Throwable $e) {
    $promotions = [];
    $summary = [];
    $totalPromotions = 0;
    $activePromotions = 0;
    $totalViews = 0;
    $totalClicks = 0;
    $overallRate = 0;
    $bestClicks = null;
    $bestViews = null;
    $maxViews = 0;
    $maxClicks = 0;
}

require __DIR__ . '/includes/admin-header.php';
?>

<style>
.pa-wrap{
    max-width:1450px;
    margin:0 auto;
    padding:28px;
}

.pa-head{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:20px;
    margin-bottom:24px;
}

.pa-head h1{
    margin:0;
    font-size:31px;
    letter-spacing:-.02em;
}

.pa-head p{
    margin:8px 0 0;
    color:#999;
}

.pa-back{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:44px;
    padding:0 15px;
    border-radius:11px;
    border:1px solid rgba(255,255,255,.12);
    background:rgba(255,255,255,.045);
    color:#fff;
    text-decoration:none;
    font-weight:800;
}

.pa-back:hover{
    background:rgba(255,255,255,.09);
}

.pa-stats{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
    margin-bottom:18px;
}

.pa-stat{
    padding:20px;
    border:1px solid rgba(255,255,255,.08);
    border-radius:18px;
    background:rgba(255,255,255,.035);
    box-shadow:0 15px 45px rgba(0,0,0,.12);
}

.pa-stat-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
}

.pa-stat small{
    color:#999;
    font-weight:700;
}

.pa-stat-icon{
    width:40px;
    height:40px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:12px;
    background:rgba(255,255,255,.06);
    font-size:19px;
}

.pa-stat strong{
    display:block;
    margin-top:9px;
    font-size:29px;
    letter-spacing:-.02em;
}

.pa-stat span{
    display:block;
    margin-top:4px;
    color:#777;
    font-size:.78rem;
}

.pa-grid{
    display:grid;
    grid-template-columns:minmax(0,1.65fr) minmax(300px,.8fr);
    gap:18px;
    align-items:start;
}

.pa-card{
    border:1px solid rgba(255,255,255,.08);
    border-radius:18px;
    background:rgba(255,255,255,.035);
    overflow:hidden;
}

.pa-card-head{
    padding:20px 20px 14px;
}

.pa-card-head h2{
    margin:0;
    font-size:19px;
}

.pa-card-head p{
    margin:6px 0 0;
    color:#888;
    font-size:.86rem;
}

.pa-table-wrap{
    width:100%;
    overflow-x:auto;
}

.pa-table{
    width:100%;
    border-collapse:collapse;
    min-width:760px;
}

.pa-table th,
.pa-table td{
    padding:14px 16px;
    text-align:left;
    border-top:1px solid rgba(255,255,255,.06);
    vertical-align:middle;
}

.pa-table th{
    color:#858585;
    font-size:.72rem;
    text-transform:uppercase;
    letter-spacing:.08em;
    font-weight:900;
}

.pa-promo{
    display:flex;
    align-items:center;
    gap:11px;
    min-width:220px;
}

.pa-thumb{
    width:52px;
    height:52px;
    flex:0 0 52px;
    overflow:hidden;
    border-radius:11px;
    background:rgba(255,255,255,.05);
    border:1px solid rgba(255,255,255,.08);
}

.pa-thumb img{
    width:100%;
    height:100%;
    display:block;
    object-fit:cover;
}

.pa-thumb-empty{
    width:100%;
    height:100%;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#777;
    font-size:20px;
}

.pa-promo strong{
    display:block;
    color:#fff;
}

.pa-promo small{
    display:block;
    margin-top:3px;
    color:#777;
}

.pa-number{
    font-weight:900;
    white-space:nowrap;
}

.pa-rate{
    min-width:105px;
}

.pa-rate strong{
    display:block;
    margin-bottom:6px;
}

.pa-bar{
    width:100%;
    height:6px;
    overflow:hidden;
    border-radius:99px;
    background:rgba(255,255,255,.07);
}

.pa-bar span{
    display:block;
    height:100%;
    border-radius:99px;
    background:#fff;
}

.pa-badge{
    display:inline-flex;
    align-items:center;
    padding:5px 9px;
    border-radius:99px;
    font-size:.68rem;
    font-weight:900;
}

.pa-badge.active{
    background:#163c28;
    color:#7ff0ae;
}

.pa-badge.inactive{
    background:#3a2424;
    color:#e99b9b;
}

.pa-side{
    display:grid;
    gap:18px;
}

.pa-highlight{
    padding:21px;
    border:1px solid rgba(255,255,255,.08);
    border-radius:18px;
    background:rgba(255,255,255,.035);
}

.pa-highlight-label{
    color:#888;
    font-size:.72rem;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.1em;
}

.pa-highlight h3{
    margin:10px 0 5px;
    font-size:21px;
}

.pa-highlight p{
    margin:0;
    color:#999;
    line-height:1.6;
    font-size:.87rem;
}

.pa-highlight-value{
    margin-top:18px;
    font-size:31px;
    font-weight:900;
}

.pa-highlight-value small{
    font-size:.78rem;
    color:#888;
    font-weight:700;
}

.pa-mini-list{
    display:grid;
    gap:11px;
    margin-top:17px;
}

.pa-mini-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:11px 12px;
    border-radius:11px;
    background:rgba(255,255,255,.035);
}

.pa-mini-row strong{
    font-size:.9rem;
}

.pa-mini-row span{
    color:#aaa;
    font-weight:900;
}

.pa-empty{
    padding:45px 20px;
    text-align:center;
    color:#888;
}

.pa-note{
    margin-top:18px;
    padding:15px 17px;
    border:1px solid rgba(255,255,255,.07);
    border-radius:14px;
    color:#888;
    background:rgba(255,255,255,.025);
    font-size:.82rem;
    line-height:1.65;
}

@media(max-width:1050px){
    .pa-stats{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .pa-grid{
        grid-template-columns:1fr;
    }
}

@media(max-width:650px){
    .pa-wrap{
        padding:18px 13px;
    }

    .pa-head{
        align-items:stretch;
        flex-direction:column;
    }

    .pa-head h1{
        font-size:26px;
    }

    .pa-back{
        width:100%;
    }

    .pa-stats{
        grid-template-columns:1fr 1fr;
        gap:10px;
    }

    .pa-stat{
        padding:15px;
    }

    .pa-stat strong{
        font-size:23px;
    }
}
</style>

<div class="pa-wrap">

    <div class="pa-head">
        <div>
            <h1>📊 Promotion Analytics</h1>
            <p>See which marketing promotions attract the most attention and WhatsApp interest.</p>
        </div>

        <a class="pa-back" href="<?= e(SITE_URL) ?>/admin/promotions.php">
            ← Manage Promotions
        </a>
    </div>

    <!-- SUMMARY -->
    <div class="pa-stats">

        <div class="pa-stat">
            <div class="pa-stat-top">
                <small>Total Promotions</small>
                <div class="pa-stat-icon">🎯</div>
            </div>
            <strong><?= number_format($totalPromotions) ?></strong>
            <span>All promotions in the system</span>
        </div>

        <div class="pa-stat">
            <div class="pa-stat-top">
                <small>Active Promotions</small>
                <div class="pa-stat-icon">🔥</div>
            </div>
            <strong><?= number_format($activePromotions) ?></strong>
            <span>Currently eligible for homepage display</span>
        </div>

        <div class="pa-stat">
            <div class="pa-stat-top">
                <small>Total Views</small>
                <div class="pa-stat-icon">👁️</div>
            </div>
            <strong><?= number_format($totalViews) ?></strong>
            <span>Homepage promotion views</span>
        </div>

        <div class="pa-stat">
            <div class="pa-stat-top">
                <small>WhatsApp Clicks</small>
                <div class="pa-stat-icon">💬</div>
            </div>
            <strong><?= number_format($totalClicks) ?></strong>
            <span><?= number_format($overallRate, 1) ?>% click / view rate</span>
        </div>

    </div>

    <div class="pa-grid">

        <!-- PERFORMANCE TABLE -->
        <div class="pa-card">

            <div class="pa-card-head">
                <h2>Promotion Performance</h2>
                <p>Ranked by WhatsApp clicks, then views.</p>
            </div>

            <div class="pa-table-wrap">

                <table class="pa-table">

                    <thead>
                        <tr>
                            <th>Promotion</th>
                            <th>Status</th>
                            <th>Views</th>
                            <th>WhatsApp</th>
                            <th>Rate</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (!$promotions): ?>

                        <tr>
                            <td colspan="5">
                                <div class="pa-empty">
                                    No promotion data available yet.
                                </div>
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($promotions as $promotion): ?>

                            <?php
                            $views = (int)($promotion['views'] ?? 0);
                            $clicks = (int)($promotion['whatsapp_clicks'] ?? 0);

                            $rate = $views > 0
                                ? ($clicks / $views) * 100
                                : 0;

                            $viewBar = $maxViews > 0
                                ? min(100, ($views / $maxViews) * 100)
                                : 0;
                            ?>

                            <tr>

                                <td>
                                    <div class="pa-promo">

                                        <div class="pa-thumb">

                                            <?php if (!empty($promotion['image'])): ?>

                                                <?php
                                                $imageValue = (string)$promotion['image'];

                                                $imageUrl = preg_match(
                                                    '~^https?://~i',
                                                    $imageValue
                                                )
                                                    ? $imageValue
                                                    : rtrim(SITE_URL, '/') .
                                                      '/uploads/' .
                                                      ltrim($imageValue, '/');
                                                ?>

                                                <img
                                                    src="<?= e($imageUrl) ?>"
                                                    alt="<?= e($promotion['title']) ?>"
                                                    loading="lazy"
                                                >

                                            <?php else: ?>

                                                <div class="pa-thumb-empty">📸</div>

                                            <?php endif; ?>

                                        </div>

                                        <div>
                                            <strong><?= e($promotion['title']) ?></strong>
                                            <small>#<?= (int)$promotion['id'] ?> · <?= e($promotion['slug'] ?? '') ?></small>
                                        </div>

                                    </div>
                                </td>

                                <td>
                                    <span class="pa-badge <?= $promotion['status'] === 'active' ? 'active' : 'inactive' ?>">
                                        <?= e(ucfirst((string)$promotion['status'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="pa-number">
                                        <?= number_format($views) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="pa-number">
                                        <?= number_format($clicks) ?>
                                    </span>
                                </td>

                                <td class="pa-rate">

                                    <strong><?= number_format($rate, 1) ?>%</strong>

                                    <div class="pa-bar">
                                        <span style="width:<?= e(number_format(min(100, $rate), 2, '.', '')) ?>%"></span>
                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

        <!-- HIGHLIGHTS -->
        <div class="pa-side">

            <div class="pa-highlight">

                <div class="pa-highlight-label">
                    🏆 Most Clicked
                </div>

                <?php if ($bestClicks): ?>

                    <h3><?= e($bestClicks['title']) ?></h3>

                    <p>
                        This promotion has generated the highest number
                        of WhatsApp clicks so far.
                    </p>

                    <div class="pa-highlight-value">
                        <?= number_format((int)$bestClicks['whatsapp_clicks']) ?>
                        <small>clicks</small>
                    </div>

                <?php else: ?>

                    <h3>No data yet</h3>

                    <p>Create a promotion and start sending visitors to the homepage.</p>

                <?php endif; ?>

            </div>

            <div class="pa-highlight">

                <div class="pa-highlight-label">
                    👁️ Most Viewed
                </div>

                <?php if ($bestViews): ?>

                    <h3><?= e($bestViews['title']) ?></h3>

                    <p>
                        This promotion has received the highest number
                        of recorded homepage views.
                    </p>

                    <div class="pa-highlight-value">
                        <?= number_format((int)$bestViews['views']) ?>
                        <small>views</small>
                    </div>

                <?php else: ?>

                    <h3>No data yet</h3>

                    <p>Views will appear here after visitors see promotions.</p>

                <?php endif; ?>

            </div>

            <div class="pa-highlight">

                <div class="pa-highlight-label">
                    💡 Quick Ranking
                </div>

                <div class="pa-mini-list">

                    <?php
                    $topFive = array_slice($promotions, 0, 5);
                    ?>

                    <?php if (!$topFive): ?>

                        <div class="pa-mini-row">
                            <strong>No promotions</strong>
                            <span>—</span>
                        </div>

                    <?php else: ?>

                        <?php foreach ($topFive as $index => $promotion): ?>

                            <div class="pa-mini-row">
                                <strong>
                                    <?= ($index + 1) ?>.
                                    <?= e($promotion['title']) ?>
                                </strong>

                                <span>
                                    <?= number_format((int)$promotion['whatsapp_clicks']) ?>
                                </span>
                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

    <div class="pa-note">
        <strong>How to read this:</strong>
        Views count recorded promotion impressions from the homepage.
        WhatsApp clicks count tracked clicks through your promotion link.
        The percentage is calculated as WhatsApp clicks ÷ views. These are
        cumulative counters from the existing promotions table, so this
        dashboard reports all-time performance rather than daily history.
    </div>

</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
