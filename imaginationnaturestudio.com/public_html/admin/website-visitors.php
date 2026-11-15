<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$pageTitle = 'Website Visitors';
$pdo = db();

// This page expects the one-time SQL migration supplied with this file.
$todayStart = date('Y-m-d 00:00:00');
$weekStart = date('Y-m-d 00:00:00', strtotime('monday this week'));
$monthStart = date('Y-m-01 00:00:00');

$stats = [
    'unique_today' => 0, 'unique_week' => 0, 'unique_month' => 0,
    'sessions_today' => 0, 'pageviews_today' => 0, 'online_now' => 0,
    'bots_today' => 0, 'total_unique' => 0
];

// Unique visitors are distinct anonymous browser IDs, not verified human identities.
$q = $pdo->prepare("SELECT COUNT(DISTINCT visitor_id) FROM website_visits WHERE visited_at >= ? AND is_bot = 0");
$q->execute([$todayStart]); $stats['unique_today'] = (int)$q->fetchColumn();
$q->execute([$weekStart]); $stats['unique_week'] = (int)$q->fetchColumn();
$q->execute([$monthStart]); $stats['unique_month'] = (int)$q->fetchColumn();
$stats['sessions_today'] = (int)$pdo->query("SELECT COUNT(*) FROM website_visits WHERE visited_at >= CURDATE() AND is_bot = 0")->fetchColumn();
$stats['bots_today'] = (int)$pdo->query("SELECT COUNT(*) FROM website_visits WHERE visited_at >= CURDATE() AND is_bot = 1")->fetchColumn();
$stats['online_now'] = (int)$pdo->query("SELECT COUNT(DISTINCT visitor_id) FROM website_visits WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) AND is_bot = 0")->fetchColumn();
$stats['total_unique'] = (int)$pdo->query("SELECT COUNT(DISTINCT visitor_id) FROM website_visits WHERE is_bot = 0")->fetchColumn();
$stats['pageviews_today'] = (int)$pdo->query("SELECT COUNT(*) FROM website_pageviews WHERE visited_at >= CURDATE() AND is_bot = 0")->fetchColumn();

$daily = $pdo->query("SELECT DATE(visited_at) d, COUNT(DISTINCT visitor_id) total FROM website_visits WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND is_bot = 0 GROUP BY DATE(visited_at) ORDER BY d")->fetchAll(PDO::FETCH_KEY_PAIR);
$devices = $pdo->query("SELECT device_type label, COUNT(DISTINCT visitor_id) total FROM website_visits WHERE is_bot = 0 GROUP BY device_type ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);
$browsers = $pdo->query("SELECT browser label, COUNT(DISTINCT visitor_id) total FROM website_visits WHERE is_bot = 0 GROUP BY browser ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);
$systems = $pdo->query("SELECT operating_system label, COUNT(DISTINCT visitor_id) total FROM website_visits WHERE is_bot = 0 GROUP BY operating_system ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);
$countries = $pdo->query("SELECT COALESCE(NULLIF(country,''),'Not available') label, COUNT(DISTINCT visitor_id) total FROM website_visits WHERE is_bot = 0 GROUP BY country ORDER BY total DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
$recent = $pdo->query("SELECT v.visitor_id, v.device_type, v.browser, v.operating_system, v.country, v.visited_at, v.is_bot, p.page_url FROM website_visits v LEFT JOIN (SELECT visitor_id, MAX(visited_at) last_seen FROM website_pageviews GROUP BY visitor_id) x ON x.visitor_id=v.visitor_id LEFT JOIN website_pageviews p ON p.visitor_id=x.visitor_id AND p.visited_at=x.last_seen ORDER BY v.visited_at DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/admin-header.php';
?>
<style>
.visitor-page{padding:10px 0 40px}.visitor-title{margin-bottom:22px}.visitor-title h1{margin:0 0 6px;font-size:28px;font-weight:800}.visitor-title p,.muted{opacity:.65}.visitor-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:22px}.visitor-card,.visitor-box{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);border-radius:16px;padding:19px}.visitor-card .label{font-size:12px;text-transform:uppercase;letter-spacing:.8px;opacity:.7}.visitor-card .number{font-size:30px;font-weight:800;margin-top:8px}.visitor-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-bottom:18px}.visitor-box{margin-bottom:18px}.visitor-box h2{font-size:18px;margin:0 0 14px}.vrow{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.07)}.vrow:last-child{border:0}.visitor-table{width:100%;border-collapse:collapse;min-width:680px}.visitor-table th,.visitor-table td{text-align:left;padding:11px 9px;border-bottom:1px solid rgba(255,255,255,.07);font-size:13px}.visitor-table th{opacity:.65;text-transform:uppercase;font-size:11px}.chart{height:180px;display:flex;align-items:flex-end;gap:5px}.chartcol{flex:1;min-width:3px;height:100%;display:flex;align-items:flex-end}.bar{width:100%;background:linear-gradient(180deg,#f2a35e,#e07a2f);border-radius:5px 5px 0 0;min-height:2px}.note{font-size:12px;opacity:.65;margin-top:10px}@media(max-width:900px){.visitor-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.visitor-grid{grid-template-columns:1fr}}@media(max-width:560px){.visitor-stats{grid-template-columns:1fr 1fr}.visitor-card{padding:14px}.visitor-card .number{font-size:24px}}
</style>
<div class="visitor-page">
 <div class="visitor-title"><h1>Website Visitors</h1><p>Visitor activity for Imagination Nature Studio.</p></div>
 <div class="visitor-stats">
  <div class="visitor-card"><div class="label">Unique Visitors Today</div><div class="number"><?= number_format($stats['unique_today']) ?></div></div>
  <div class="visitor-card"><div class="label">Unique Visitors This Week</div><div class="number"><?= number_format($stats['unique_week']) ?></div></div>
  <div class="visitor-card"><div class="label">Unique Visitors This Month</div><div class="number"><?= number_format($stats['unique_month']) ?></div></div>
  <div class="visitor-card"><div class="label">Online Now (5 min)</div><div class="number"><?= number_format($stats['online_now']) ?></div></div>
  <div class="visitor-card"><div class="label">Sessions Today</div><div class="number"><?= number_format($stats['sessions_today']) ?></div></div>
  <div class="visitor-card"><div class="label">Page Views Today</div><div class="number"><?= number_format($stats['pageviews_today']) ?></div></div>
  <div class="visitor-card"><div class="label">Bots Today</div><div class="number"><?= number_format($stats['bots_today']) ?></div></div>
  <div class="visitor-card"><div class="label">Total Unique Browsers</div><div class="number"><?= number_format($stats['total_unique']) ?></div></div>
 </div>
 <div class="visitor-box"><h2>Unique Visitors — Last 30 Days</h2><div class="chart">
 <?php $max = max(1, ...array_map('intval', array_values($daily ?: [0]))); $start = new DateTime('today -29 days'); $end = new DateTime('today'); while($start <= $end): $key=$start->format('Y-m-d'); $n=(int)($daily[$key]??0); $h=$n?max(4,($n/$max)*100):2; ?>
  <div class="chartcol" title="<?= e($key) ?>: <?= number_format($n) ?> unique visitors"><div class="bar" style="height:<?= min(100,$h) ?>%;opacity:<?= $n?1:.18 ?>"></div></div>
 <?php $start->modify('+1 day'); endwhile; ?>
 </div><div class="note">Counts anonymous visitor IDs recorded by the browser cookie; not verified individual people.</div></div>
 <div class="visitor-grid">
  <?php foreach(['Devices'=>$devices,'Browsers'=>$browsers,'Operating Systems'=>$systems,'Countries'=>$countries] as $heading=>$rows): ?>
   <div class="visitor-box"><h2><?= e($heading) ?></h2><?php if($rows): foreach($rows as $row): ?><div class="vrow"><span><?= e($row['label'] ?: 'Unknown') ?></span><strong><?= number_format((int)$row['total']) ?></strong></div><?php endforeach; else: ?><p class="muted">No data recorded yet.</p><?php endif; ?></div>
  <?php endforeach; ?>
 </div>
 <div class="visitor-box"><h2>Recent Sessions</h2><div style="overflow-x:auto"><table class="visitor-table"><thead><tr><th>Visitor ID</th><th>Device</th><th>Browser</th><th>OS</th><th>Country</th><th>Last Page</th><th>Time</th><th>Type</th></tr></thead><tbody>
 <?php foreach($recent as $v): ?><tr><td><?= e(substr($v['visitor_id'],0,10)) ?>…</td><td><?= e($v['device_type'] ?: 'Unknown') ?></td><td><?= e($v['browser'] ?: 'Unknown') ?></td><td><?= e($v['operating_system'] ?: 'Unknown') ?></td><td><?= e($v['country'] ?: 'Not available') ?></td><td><?= e($v['page_url'] ?: 'Not recorded') ?></td><td><?= e($v['visited_at']) ?></td><td><?= !empty($v['is_bot'])?'Bot':'Visitor' ?></td></tr><?php endforeach; ?>
 <?php if(!$recent): ?><tr><td colspan="8">No visitor data yet.</td></tr><?php endif; ?>
 </tbody></table></div><p class="note">Bots are identified from common crawler user-agent patterns. Some automated traffic may still be misclassified.</p></div>
</div>
