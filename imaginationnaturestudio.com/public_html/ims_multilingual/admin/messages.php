<?php
/**
 * Admin — Contact form messages.
 *
 * Features:
 *   - List all messages from the contact form
 *   - Filter: all / unread / read
 *   - Search by name, email, subject, or body
 *   - View message in a detail panel
 *   - Mark as read/unread
 *   - Reply by email (mailto link)
 *   - Delete message
 */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

/* =====================================================
   POST actions
   ===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {

    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    /* ---------- Mark read/unread ---------- */
    if ($action === 'toggle_read' && $id) {
        $pdo->prepare("UPDATE messages SET is_read = 1 - is_read WHERE id = ?")->execute([$id]);
        flash('ok', 'Message updated.');
        redirect('messages.php' . (!empty($_POST['return']) ? '?' . $_POST['return'] : ''));
    }

    /* ---------- Mark all read ---------- */
    if ($action === 'mark_all_read') {
        $pdo->exec("UPDATE messages SET is_read = 1 WHERE is_read = 0");
        flash('ok', 'All messages marked as read.');
        redirect('messages.php');
    }

    /* ---------- Delete ---------- */
    if ($action === 'delete' && $id) {
        $pdo->prepare("DELETE FROM messages WHERE id = ?")->execute([$id]);
        flash('ok', 'Message deleted.');
        redirect('messages.php');
    }

    /* ---------- Bulk delete read ---------- */
    if ($action === 'delete_read') {
        $n = $pdo->exec("DELETE FROM messages WHERE is_read = 1");
        flash('ok', "Deleted {$n} read message(s).");
        redirect('messages.php');
    }
}

/* =====================================================
   Filters
   ===================================================== */
$filter = $_GET['filter'] ?? 'all';   // all | unread | read
$search = trim($_GET['q'] ?? '');
$viewId = (int)($_GET['id'] ?? 0);

/* =====================================================
   Query
   ===================================================== */
$sql  = "SELECT * FROM messages WHERE 1=1";
$args = [];

if ($filter === 'unread') {
    $sql .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $sql .= " AND is_read = 1";
}

if ($search !== '') {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
    $like = '%' . $search . '%';
    array_push($args, $like, $like, $like, $like);
}

$sql .= " ORDER BY is_read ASC, id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

/* ---------- Selected message for detail panel ---------- */
$view = null;
if ($viewId) {
    $v = $pdo->prepare("SELECT * FROM messages WHERE id = ?");
    $v->execute([$viewId]);
    $view = $v->fetch();

    /* Auto-mark as read when opened */
    if ($view && !$view['is_read']) {
        $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ?")->execute([$viewId]);
        $view['is_read'] = 1;
    }
}

/* ---------- Stats ---------- */
$unreadCount = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE is_read = 0")->fetchColumn();
$totalCount  = (int)$pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();

require __DIR__ . '/includes/admin-header.php';
?>

<style>
  .msg-layout {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 1.2rem;
    align-items: start;
  }
  @media (max-width: 1000px) {
    .msg-layout { grid-template-columns: 1fr; }
  }

  .msg-list {
    background: #171513;
    border: 1px solid #262220;
    border-radius: 14px;
    overflow: hidden;
    max-height: 78vh;
    overflow-y: auto;
  }
  .msg-item {
    display: block;
    padding: 1rem 1.1rem;
    border-bottom: 1px solid #262220;
    text-decoration: none;
    color: #f3ece3;
    transition: background .2s;
    position: relative;
  }
  .msg-item:hover { background: #1c1916; }
  .msg-item.is-selected { background: #221e1a; border-left: 3px solid var(--accent); }
  .msg-item.unread { background: #1a1714; }
  .msg-item.unread .msg-name::before {
    content: "";
    display: inline-block;
    width: 8px; height: 8px;
    background: var(--accent);
    border-radius: 50%;
    margin-right: .5rem;
    vertical-align: middle;
  }
  .msg-item .msg-name {
    font-weight: 600;
    font-size: .93rem;
    display: block;
    margin-bottom: .15rem;
  }
  .msg-item .msg-subject {
    font-size: .82rem;
    color: #b3aaa1;
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .msg-item .msg-meta {
    font-size: .72rem;
    color: #6a6159;
    display: block;
    margin-top: .3rem;
  }

  .msg-detail {
    background: #171513;
    border: 1px solid #262220;
    border-radius: 14px;
    padding: 1.6rem 1.8rem;
    min-height: 400px;
  }
  .msg-detail h2 {
    font-family: var(--font-serif);
    font-size: 1.3rem;
    margin: 0 0 .4rem;
  }
  .msg-detail .detail-meta {
    color: #7a716a;
    font-size: .85rem;
    margin-bottom: 1.4rem;
    padding-bottom: 1.2rem;
    border-bottom: 1px solid #262220;
    line-height: 1.8;
  }
  .msg-detail .detail-meta strong { color: #b3aaa1; font-weight: 500; }
  .msg-detail .detail-body {
    white-space: pre-wrap;
    line-height: 1.7;
    color: #e6dccd;
    font-size: .95rem;
  }

  .msg-toolbar {
    display: flex;
    gap: .6rem;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 1.2rem;
  }
  .msg-toolbar input[type=text] {
    flex: 1; min-width: 200px;
    padding: .6rem .9rem;
    background: #0e0d0c;
    color: #f3ece3;
    border: 1px solid #262220;
    border-radius: 8px;
    font-size: .85rem;
  }
  .msg-tabs {
    display: flex; gap: .3rem;
  }
  .msg-tabs a {
    padding: .55rem .95rem;
    background: #0e0d0c;
    border: 1px solid #262220;
    border-radius: 8px;
    font-size: .85rem;
    color: #b3aaa1;
    text-decoration: none;
  }
  .msg-tabs a.active { background: var(--accent); color: #14100d; border-color: var(--accent); font-weight: 600; }
  .msg-tabs a .count {
    display: inline-block;
    background: rgba(255,255,255,.1);
    border-radius: 999px;
    padding: 0 .5rem;
    font-size: .72rem;
    margin-left: .35rem;
  }
  .msg-tabs a.active .count { background: rgba(0,0,0,.25); }
</style>

<!-- ============ HEADER ============ -->
<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1rem">
  <div>
    <h1 style="margin:0 0 .3rem">Messages</h1>
    <p style="color:#7a716a;margin:0">
      Contact-form messages submitted from the website.
      <?php if ($unreadCount): ?>
        <strong style="color:var(--accent-2);"> <?= (int)$unreadCount ?> unread</strong>.
      <?php endif; ?>
    </p>
  </div>

  <?php if ($unreadCount > 0): ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="mark_all_read">
      <button class="btn btn-sm btn-primary">Mark all as read</button>
    </form>
  <?php endif; ?>
</div>

<!-- ============ FLASH ============ -->
<?php if ($m = flash('ok')):  ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
<?php if ($m = flash('err')): ?><div class="alert alert-error"><?= e($m) ?></div><?php endif; ?>

<!-- ============ TOOLBAR ============ -->
<form method="get" class="msg-toolbar">
  <div class="msg-tabs">
    <a href="?filter=all"    class="<?= $filter === 'all'    ? 'active' : '' ?>">All <span class="count"><?= (int)$totalCount ?></span></a>
    <a href="?filter=unread" class="<?= $filter === 'unread' ? 'active' : '' ?>">Unread <span class="count"><?= (int)$unreadCount ?></span></a>
    <a href="?filter=read"   class="<?= $filter === 'read'   ? 'active' : '' ?>">Read</a>
  </div>

  <input type="text" name="q" placeholder="Search name, email, subject, message…" value="<?= e($search) ?>">
  <button class="btn btn-sm btn-primary">Search</button>
  <?php if ($search !== '' || $filter !== 'all'): ?>
    <a class="btn btn-sm btn-ghost" href="messages.php">Clear</a>
  <?php endif; ?>
</form>

<!-- ============ LAYOUT ============ -->
<div class="msg-layout">

  <!-- ============ LIST ============ -->
  <div class="msg-list">
    <?php foreach ($rows as $r): ?>
      <?php
        $isSelected = $view && (int)$view['id'] === (int)$r['id'];
        $cls = 'msg-item';
        if (!$r['is_read']) $cls .= ' unread';
        if ($isSelected)    $cls .= ' is-selected';
        $url = 'messages.php?id=' . (int)$r['id'] . '&filter=' . urlencode($filter) . ($search ? '&q=' . urlencode($search) : '');
      ?>
      <a href="<?= e($url) ?>" class="<?= $cls ?>">
        <span class="msg-name"><?= e($r['name']) ?></span>
        <span class="msg-subject">
          <?= e($r['subject'] ?: '(no subject)') ?>
        </span>
        <span class="msg-meta">
          <?= e(date('d M Y · H:i', strtotime($r['created_at']))) ?>
          &nbsp;·&nbsp;
          <?= e($r['email']) ?>
        </span>
      </a>
    <?php endforeach; ?>

    <?php if (!$rows): ?>
      <div style="padding:2.5rem 1.4rem;text-align:center;color:#7a716a;font-size:.9rem">
        <?php if ($search): ?>
          No messages match "<strong><?= e($search) ?></strong>".
        <?php elseif ($filter === 'unread'): ?>
          No unread messages. 🎉
        <?php else: ?>
          No messages yet.
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- ============ DETAIL PANEL ============ -->
  <?php if ($view): ?>
    <div class="msg-detail">

      <h2><?= e($view['subject'] ?: '(no subject)') ?></h2>

      <div class="detail-meta">
        <div><strong>From:</strong> <?= e($view['name']) ?></div>
        <div><strong>Email:</strong> <a href="mailto:<?= e($view['email']) ?>" style="color:var(--accent-2)"><?= e($view['email']) ?></a></div>
        <?php if (!empty($view['phone'])): ?>
          <div><strong>Phone:</strong> <a href="tel:<?= e($view['phone']) ?>" style="color:var(--accent-2)"><?= e($view['phone']) ?></a></div>
        <?php endif; ?>
        <div><strong>Received:</strong> <?= e(date('d F Y · H:i', strtotime($view['created_at']))) ?></div>
      </div>

      <div class="detail-body"><?= e($view['message']) ?></div>

      <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:2rem;padding-top:1.4rem;border-top:1px solid #262220">
        <a class="btn btn-sm btn-primary"
           href="mailto:<?= e($view['email']) ?>?subject=<?= rawurlencode('Re: ' . ($view['subject'] ?: 'Your message to Imagination Nature Studio')) ?>">
          ✉ Reply by email
        </a>

        <form method="post" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle_read">
          <input type="hidden" name="id" value="<?= (int)$view['id'] ?>">
          <input type="hidden" name="return" value="<?= e(http_build_query(['id' => $view['id'], 'filter' => $filter])) ?>">
          <button class="btn btn-sm btn-ghost">
            <?= $view['is_read'] ? 'Mark as unread' : 'Mark as read' ?>
          </button>
        </form>

        <form method="post" style="display:inline"
              onsubmit="return confirm('Delete this message permanently?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$view['id'] ?>">
          <button class="btn btn-sm btn-ghost" style="border-color:#a33;color:#f0b3b3">
            Delete
          </button>
        </form>
      </div>

    </div>
  <?php else: ?>
    <div class="msg-detail" style="display:grid;place-items:center;color:#7a716a">
      <div style="text-align:center">
        <div style="font-size:2.4rem;margin-bottom:.8rem;opacity:.4">✉</div>
        <p style="margin:0">Select a message from the list to read it.</p>
      </div>
    </div>
  <?php endif; ?>

</div>

<!-- ============ BULK ACTIONS ============ -->
<?php if ($filter !== 'unread' && $totalCount > 0): ?>
<div class="card" style="padding:1.2rem 1.4rem;margin-top:1.6rem;border-color:rgba(200,60,60,.25)">
  <h3 style="font-family:var(--font-serif);font-size:1rem;margin:0 0 .8rem;color:#f0b3b3">
    Maintenance
  </h3>
  <form method="post" style="display:flex;gap:.6rem;flex-wrap:wrap"
        onsubmit="return confirm('Delete all messages marked as read? This cannot be undone.')">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_read">
    <button class="btn btn-sm btn-ghost" style="border-color:#a33;color:#f0b3b3">
      Delete all read messages
    </button>
  </form>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>