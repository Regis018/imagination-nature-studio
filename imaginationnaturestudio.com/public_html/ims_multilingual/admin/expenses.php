<?php
/**
 * Admin — Daily expenses.
 * Staff & admin can record. Only admin can delete.
 */
require_once __DIR__ . '/../includes/ops.php';
require_admin();

$pdo = db();

/* =====================================================
   POST actions — DELETE runs first
   ===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {

    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    /* ---------- DELETE — handled first ---------- */
    if ($action === 'delete' && $id) {
        @error_log('[expenses-delete] id=' . $id . ' | is_admin=' . (is_admin() ? 'true' : 'false') . "\n", 3, ERROR_LOG_FILE);

        if (!is_admin()) {
            flash('err', 'Only administrators can delete expenses.');
            redirect('expenses.php');
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM expenses WHERE id=?");
            $stmt->execute([$id]);
            flash('ok', 'Expense deleted (' . $stmt->rowCount() . ' row).');
        } catch (Throwable $e) {
            flash('err', 'Delete failed: ' . $e->getMessage());
        }
        redirect('expenses.php');
    }

    /* ---------- CREATE / UPDATE ---------- */
    $date   = trim($_POST['expense_date'] ?? date('Y-m-d'));
    $desc   = trim($_POST['description'] ?? '');
    $cat    = preg_replace('/[^a-z_]/', '', strtolower($_POST['category'] ?? 'other'));
    $amount = max(0, (float)($_POST['amount'] ?? 0));
    $staff  = trim($_POST['staff_name'] ?? '');
    $notes  = trim($_POST['notes'] ?? '');

    if ($desc === '' || $amount <= 0) {
        flash('err', 'Description and amount are required.');
        redirect('expenses.php');
    }

    if ($action === 'create') {
        $pdo->prepare("INSERT INTO expenses (expense_date, description, category, amount, staff_name, notes) VALUES (?,?,?,?,?,?)")
            ->execute([$date, $desc, $cat, $amount, $staff, $notes]);
        flash('ok', 'Expense recorded.');
        redirect('expenses.php');
    }

    if ($action === 'update' && $id) {
        $pdo->prepare("UPDATE expenses SET expense_date=?, description=?, category=?, amount=?, staff_name=?, notes=? WHERE id=?")
            ->execute([$date, $desc, $cat, $amount, $staff, $notes, $id]);
        flash('ok', 'Expense updated.');
        redirect('expenses.php');
    }
}

/* =====================================================
   Filters
   ===================================================== */
$from   = $_GET['from'] ?? date('Y-m-01');
$to     = $_GET['to']   ?? date('Y-m-d');
$catF   = $_GET['cat']  ?? '';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM expenses WHERE expense_date BETWEEN ? AND ?";
$args = [$from, $to];

if ($catF !== '') { $sql .= " AND category=?"; $args[] = $catF; }
if ($search !== '') { $sql .= " AND description LIKE ?"; $args[] = "%$search%"; }

$sql .= " ORDER BY expense_date DESC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

$totals = ops_expenses_total($from, $to);
$cats   = ops_expense_categories();

$catSummary = [];
$cstmt = $pdo->prepare(
    "SELECT category, SUM(amount) AS total, COUNT(*) AS cnt
     FROM expenses WHERE expense_date BETWEEN ? AND ?
     GROUP BY category ORDER BY total DESC"
);
$cstmt->execute([$from, $to]);
$catSummary = $cstmt->fetchAll();

require __DIR__ . '/includes/admin-header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1rem">
  <div>
    <h1 style="margin:0 0 .3rem">Expenses</h1>
    <p style="color:#7a716a;margin:0">Daily studio expenses — tickets, rent, materials, salaries.</p>
  </div>
  <div style="display:flex;gap:.6rem">
    <a class="btn btn-sm btn-ghost" href="daily-report.php">Daily Report →</a>
  </div>
</div>

<?php if ($m = flash('ok')):  ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
<?php if ($m = flash('err')): ?><div class="alert alert-error"><?= e($m) ?></div><?php endif; ?>

<form method="get" class="card" style="padding:1rem 1.2rem;margin-bottom:1.4rem">
  <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center">
    <label style="margin:0;text-transform:none;letter-spacing:0;color:#7a716a;font-size:.85rem">From</label>
    <input type="date" name="from" value="<?= e($from) ?>" style="width:auto">
    <label style="margin:0;text-transform:none;letter-spacing:0;color:#7a716a;font-size:.85rem">To</label>
    <input type="date" name="to" value="<?= e($to) ?>" style="width:auto">
    <select name="cat">
      <option value="">All categories</option>
      <?php foreach ($cats as $k => $label): ?>
        <option value="<?= e($k) ?>" <?= $catF === $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="q" placeholder="Search" value="<?= e($search) ?>">
    <button class="btn btn-sm btn-primary">Filter</button>
    <a class="btn btn-sm btn-ghost" href="expenses.php">Today</a>
    <a class="btn btn-sm btn-ghost" href="expenses-export.php?from=<?= e($from) ?>&to=<?= e($to) ?>&cat=<?= e($catF) ?>&q=<?= e($search) ?>">⬇ CSV</a>
  </div>
</form>

<div class="grid grid-3" style="margin-bottom:1.4rem">
  <div class="card" style="padding:1rem 1.2rem">
    <div style="font-size:.72rem;letter-spacing:.16em;text-transform:uppercase;color:#7a716a">Records</div>
    <div style="font-family:var(--font-serif);font-size:1.6rem"><?= (int)$totals['count'] ?></div>
  </div>
  <div class="card" style="padding:1rem 1.2rem">
    <div style="font-size:.72rem;letter-spacing:.16em;text-transform:uppercase;color:#7a716a">Total spent</div>
    <div style="font-family:var(--font-serif);font-size:1.6rem;color:#f0b3b3"><?= e(ops_money($totals['total'])) ?></div>
  </div>
  <div class="card" style="padding:1rem 1.2rem">
    <div style="font-size:.72rem;letter-spacing:.16em;text-transform:uppercase;color:#7a716a">Top category</div>
    <div style="font-family:var(--font-serif);font-size:1.4rem">
      <?= $catSummary ? e($cats[$catSummary[0]['category']] ?? $catSummary[0]['category']) : '—' ?>
    </div>
  </div>
</div>

<form method="post" class="card" style="padding:1.4rem;margin-bottom:1.4rem">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <h3 style="font-family:var(--font-serif);font-size:1.05rem;margin:0 0 1rem">Record new expense</h3>

  <div class="form-grid">
    <div><label>Date *</label><input type="date" name="expense_date" value="<?= e(date('Y-m-d')) ?>" required></div>
    <div>
      <label>Category</label>
      <select name="category">
        <?php foreach ($cats as $k => $label): ?>
          <option value="<?= e($k) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="full"><label>Description *</label><input type="text" name="description" required maxlength="255" placeholder="e.g. Ticket for Noheri"></div>
    <div>
      <label>Amount (RWF) *</label>
      <input type="number" name="amount" min="0" step="1" required placeholder="e.g. 2000">
    </div>
    <div><label>Staff name</label><input type="text" name="staff_name" maxlength="120" value="<?= e(current_admin()['name'] ?? '') ?>"></div>
    <div class="full"><label>Notes</label><input type="text" name="notes" maxlength="255"></div>
  </div>
  <button class="btn btn-primary" style="margin-top:1rem">Record expense</button>
</form>

<?php if ($catSummary): ?>
<div class="card" style="padding:1.2rem 1.4rem;margin-bottom:1.4rem">
  <h3 style="font-family:var(--font-serif);font-size:1rem;margin:0 0 .8rem">Breakdown by category</h3>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.8rem">
    <?php foreach ($catSummary as $c): ?>
      <div style="background:#100f0e;padding:.8rem 1rem;border-radius:8px;border:1px solid #262220">
        <div style="font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;color:#7a716a">
          <?= e($cats[$c['category']] ?? $c['category']) ?>
        </div>
        <div style="font-family:var(--font-serif);font-size:1.2rem;margin-top:.2rem">
          <?= e(ops_money($c['total'])) ?>
        </div>
        <div style="color:#6a6159;font-size:.75rem"><?= (int)$c['cnt'] ?> entries</div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<h2 style="font-family:var(--font-serif);font-size:1.1rem;margin:1.4rem 0 .8rem">
  Expenses (<?= count($rows) ?>)
</h2>

<div class="card" style="padding:0">
  <div class="admin-table-wrap" style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
      <thead><tr style="text-align:left;font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:#7a716a">
        <th style="padding:.9rem 1rem">Date</th>
        <th>Description</th>
        <th>Category</th>
        <th>Amount</th>
        <th>Staff</th>
        <th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr style="border-top:1px solid #262220">
          <td style="padding:.9rem 1rem;white-space:nowrap"><?= e(ops_format_date($r['expense_date'])) ?></td>
          <td>
            <?= e($r['description']) ?>
            <?php if ($r['notes']): ?>
              <br><span style="color:#7a716a;font-size:.78rem"><?= e($r['notes']) ?></span>
            <?php endif; ?>
          </td>
          <td><span style="color:#7a716a;font-size:.82rem"><?= e($cats[$r['category']] ?? $r['category']) ?></span></td>
          <td><strong style="color:#f0b3b3"><?= e(ops_money($r['amount'])) ?></strong></td>
          <td><span style="color:#7a716a;font-size:.82rem"><?= e($r['staff_name'] ?: '—') ?></span></td>
          <td>
            <?php if (is_admin()): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this expense?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-ghost" style="border-color:#a33;color:#f0b3b3">Delete</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if (!$rows): ?>
        <tr><td colspan="6" style="padding:3rem;text-align:center;color:#7a716a">
          No expenses in this period.
        </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>