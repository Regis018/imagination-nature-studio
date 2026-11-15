<?php
/**
 * Imagination Nature Studio
 * Admin Security — Active Devices
 */

require_once __DIR__ . '/../../includes/functions.php';

require_admin_only();

$pdo = db();
$currentAdminId = (int)($_SESSION['admin_id'] ?? 0);

/*
 * Show only currently active, non-expired sessions.
 * Device identification is based on the browser User-Agent.
 */
$sql = "
    SELECT
        s.id,
        s.admin_id,
        s.device_name,
        s.browser,
        s.operating_system,
        s.ip_address,
        s.created_at,
        s.last_activity,
        s.expires_at,
        a.name AS admin_name,
        a.email AS admin_email,
        a.role AS admin_role
    FROM admin_sessions s
    INNER JOIN admins a ON a.id = s.admin_id
    WHERE s.revoked_at IS NULL
      AND (s.expires_at IS NULL OR s.expires_at > NOW())
      AND COALESCE(a.is_active, 1) = 1
    ORDER BY s.last_activity DESC
";

$stmt = $pdo->query($sql);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

function session_time_label(?string $date): string
{
    if (!$date) {
        return '—';
    }

    $ts = strtotime($date);

    if (!$ts) {
        return e($date);
    }

    return date('d M Y, H:i', $ts);
}

function session_status_label(string $lastActivity): string
{
    $ts = strtotime($lastActivity);

    if (!$ts) {
        return 'Active';
    }

    $minutes = (time() - $ts) / 60;

    if ($minutes <= 5) {
        return 'Online now';
    }

    if ($minutes <= 30) {
        return 'Recently active';
    }

    return 'Active';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Active Devices | Security | Imagination Nature Studio</title>

    <style>
        :root{
            --bg:#f5f7fb;
            --card:#fff;
            --text:#172033;
            --muted:#6b7280;
            --line:#e5e7eb;
            --primary:#111827;
            --danger:#b42318;
            --danger-bg:#fff1f0;
            --green:#067647;
            --green-bg:#ecfdf3;
            --shadow:0 10px 30px rgba(15,23,42,.07);
            --radius:18px;
        }

        *{box-sizing:border-box}

        body{
            margin:0;
            background:var(--bg);
            color:var(--text);
            font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
        }

        .wrap{
            max-width:1400px;
            margin:0 auto;
            padding:32px 22px 60px;
        }

        .top{
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:20px;
            margin-bottom:24px;
        }

        h1{
            margin:0 0 8px;
            font-size:30px;
            letter-spacing:-.5px;
        }

        .subtitle{
            margin:0;
            color:var(--muted);
            line-height:1.6;
        }

        .back{
            text-decoration:none;
            color:var(--text);
            background:#fff;
            border:1px solid var(--line);
            padding:11px 15px;
            border-radius:12px;
            font-weight:700;
        }

        .notice{
            background:#fff;
            border:1px solid var(--line);
            border-radius:var(--radius);
            padding:18px 20px;
            box-shadow:var(--shadow);
            margin-bottom:20px;
        }

        .notice strong{display:block;margin-bottom:5px}
        .notice span{color:var(--muted);font-size:14px}

        .table-card{
            background:var(--card);
            border:1px solid var(--line);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            overflow:hidden;
        }

        .table-scroll{overflow-x:auto}

        table{
            width:100%;
            min-width:1050px;
            border-collapse:collapse;
        }

        th,td{
            padding:16px 18px;
            text-align:left;
            border-bottom:1px solid var(--line);
            vertical-align:middle;
        }

        th{
            background:#fafafa;
            color:#4b5563;
            font-size:12px;
            text-transform:uppercase;
            letter-spacing:.06em;
        }

        td{font-size:14px}

        tr:last-child td{border-bottom:0}

        .person{
            font-weight:800;
        }

        .email{
            color:var(--muted);
            font-size:12px;
            margin-top:3px;
        }

        .device{
            font-weight:700;
        }

        .meta{
            color:var(--muted);
            font-size:12px;
            margin-top:4px;
        }

        .badge{
            display:inline-flex;
            align-items:center;
            gap:7px;
            padding:6px 10px;
            border-radius:999px;
            font-size:12px;
            font-weight:800;
            white-space:nowrap;
        }

        .badge.green{
            color:var(--green);
            background:var(--green-bg);
        }

        .dot{
            width:7px;
            height:7px;
            border-radius:50%;
            background:currentColor;
        }

        .current{
            display:inline-block;
            margin-left:7px;
            padding:3px 7px;
            border-radius:6px;
            background:#eef2ff;
            color:#4338ca;
            font-size:10px;
            font-weight:800;
            text-transform:uppercase;
        }

        .danger{
            border:1px solid #f1b7b2;
            background:var(--danger-bg);
            color:var(--danger);
            border-radius:10px;
            padding:9px 12px;
            font-weight:800;
            cursor:pointer;
        }

        .danger:hover{filter:brightness(.97)}

        .empty{
            text-align:center;
            padding:55px 20px;
            color:var(--muted);
        }

        @media(max-width:700px){
            .wrap{padding:22px 14px 40px}
            .top{flex-direction:column}
            h1{font-size:25px}
        }
    </style>
</head>

<body>
<div class="wrap">

    <div class="top">
        <div>
            <h1>Active Devices</h1>
            <p class="subtitle">
                Reba devices zose zifite active admin sessions kuri Imagination Nature Studio.
            </p>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end">
            <form method="post"
                  action="<?= e(SITE_URL . '/admin/security/logout-all.php') ?>"
                  onsubmit="return confirm('Log out all other devices? Your current device will remain logged in.');"
                  style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="mode" value="others">
                <button
                    type="submit"
                    style="border:1px solid #b42318;background:#fff1f0;color:#b42318;border-radius:10px;padding:11px 15px;font-weight:800;cursor:pointer"
                >
                    LOG OUT ALL OTHER DEVICES
                </button>
            </form>

            <a class="back" href="<?= e(SITE_URL . '/admin/dashboard.php') ?>">
                ← Dashboard
            </a>
        </div>
    </div>

    <div class="notice">
        <strong>Security control</strong>
        <span>
            Ushobora guhita ukura device kuri account ukoresheje
            <b>FORCE LOGOUT</b>. Device izongera kwemererwa gusa nyuma yo kongera login.
        </span>
    </div>

    <div class="table-card">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>User</th>
                    <th>Device</th>
                    <th>Browser</th>
                    <th>Operating System</th>
                    <th>IP Address</th>
                    <th>Last Activity</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>

                <tbody>
                <?php if (!$sessions): ?>
                    <tr>
                        <td colspan="8" class="empty">
                            Nta active sessions zihari ubu.
                        </td>
                    </tr>
                <?php else: ?>

                    <?php foreach ($sessions as $s): ?>
                        <?php
                            $isCurrent =
                                (int)$s['id'] === (int)($_SESSION['admin_session_id'] ?? 0);

                            $status = session_status_label(
                                (string)$s['last_activity']
                            );
                        ?>

                        <tr>
                            <td>
                                <div class="person">
                                    <?= e($s['admin_name'] ?: 'Unknown') ?>

                                    <?php if ($isCurrent): ?>
                                        <span class="current">This device</span>
                                    <?php endif; ?>
                                </div>

                                <div class="email">
                                    <?= e($s['admin_email'] ?: '') ?>
                                </div>
                            </td>

                            <td>
                                <div class="device">
                                    <?= e($s['device_name'] ?: 'Unknown device') ?>
                                </div>
                            </td>

                            <td><?= e($s['browser'] ?: 'Unknown') ?></td>

                            <td><?= e($s['operating_system'] ?: 'Unknown') ?></td>

                            <td>
                                <code><?= e($s['ip_address'] ?: 'Unknown') ?></code>
                            </td>

                            <td>
                                <?= session_time_label($s['last_activity']) ?>
                            </td>

                            <td>
                                <span class="badge green">
                                    <span class="dot"></span>
                                    <?= e($status) ?>
                                </span>
                            </td>

                            <td>
                                <form
                                    method="post"
                                    action="<?= e(SITE_URL . '/admin/security/force-logout.php') ?>"
                                    onsubmit="return confirm('Force logout this device?');"
                                >
                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="session_id"
                                        value="<?= (int)$s['id'] ?>"
                                    >

                                    <button class="danger" type="submit">
                                        FORCE LOGOUT
                                    </button>
                                </form>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
