<?php
/**
 * Admin — Manage users (admin only).
 */
require_once __DIR__ . '/../includes/functions.php';
require_full_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {

    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    /* ---------- Create ---------- */
    if ($action === 'create') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $role  = ($_POST['role'] ?? 'staff') === 'admin' ? 'admin' : 'staff';
        $title = trim($_POST['staff_title'] ?? '');
        $active= !empty($_POST['is_active']) ? 1 : 0;

        if ($name === '' || !valid_email($email)) {
            flash('err', 'Name and a valid email are required.');
            redirect('users.php');
        }
        if (strlen($pass) < 8) {
            flash('err', 'Password must be at least 8 characters.');
            redirect('users.php');
        }

        $chk = $pdo->prepare("SELECT id FROM admins WHERE email=?");
        $chk->execute([$email]);
        if ($chk->fetchColumn()) {
            flash('err', 'That email is already registered.');
            redirect('users.php');
        }

        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $pdo->prepare(
            "INSERT INTO admins (name, email, password, role, is_active, staff_title)
             VALUES (?,?,?,?,?,?)"
        )->execute([$name, $email, $hash, $role, $active, $title]);

        flash('ok', 'User created.');
        redirect('users.php');
    }

    /* ---------- Update ---------- */
    if ($action === 'update' && $id) {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role  = ($_POST['role'] ?? 'staff') === 'admin' ? 'admin' : 'staff';
        $title = trim($_POST['staff_title'] ?? '');
        $active= !empty($_POST['is_active']) ? 1 : 0;
        $pass  = $_POST['password'] ?? '';

        if ($name === '' || !valid_email($email)) {
            flash('err', 'Name and a valid email are required.');
            redirect('users.php');
        }

        if ($id === (int)$_SESSION['admin_id']) {
            $role   = 'admin';
            $active = 1;
        }

        $chk = $pdo->prepare("SELECT id FROM admins WHERE email=? AND id<>?");
        $chk->execute([$email, $id]);
        if ($chk->fetchColumn()) {
            flash('err', 'That email is already in use.');
            redirect('users.php');
        }

        if ($pass !== '') {
            if (strlen($pass) < 8) {
                flash('err', 'Password must be at least 8 characters.');
                redirect('users.php');
            }
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $pdo->prepare(
                "UPDATE admins SET name=?, email=?, role=?, is_active=?, staff_title=?, password=? WHERE id=?"
            )->execute([$name, $email, $role, $active, $title, $hash, $id]);
        } else {
            $pdo->prepare(
                "UPDATE admins SET name=?, email=?, role=?, is_active=?, staff_title=? WHERE id=?"
            )->execute([$name, $email, $role, $active, $title, $id]);
        }

        flash('ok', 'User updated.');
        redirect('users.php');
    }

    /* ---------- Delete ---------- */
    if ($action === 'delete' && $id) {
        if ($id === (int)$_SESSION['admin_id']) {
            flash('err', 'You cannot delete your own account.');
            redirect('users.php');
        }
        $pdo->prepare("DELETE FROM admins WHERE id=?")->execute([$id]);
        flash('ok', 'User deleted.');
        redirect('users.php');
    }
}

$users = $pdo->query("SELECT * FROM admins ORDER BY role, name")->fetchAll();

require __DIR__ . '/includes/admin-header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1rem">
  <div>
    <h1 style="margin:0 0 .3rem">Users</h1>
    <p style="color:#7a716a;margin:0">Admin and staff accounts.</p>
  </div>
</div>

<?php if ($m = flash('ok')):  ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
<?php if ($m = flash('err')): ?><div class="alert alert-error"><?= e($m) ?></div><?php endif; ?>

<form method="post" class="card" style="padding:1.6rem;margin-bottom:1.6rem">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <h3 style="font-family:var(--font-serif);font-size:1.1rem;margin:0 0 1.2rem">Create user</h3>

  <div class="form-grid">
    <div><label>Full name *</label><input type="text" name="name" required maxlength="120"></div>
    <div><label>Email *</label><input type="email" name="email" required maxlength="190"></div>
    <div>
      <label>Password *</label>
      <input type="text" name="password" required minlength="8"
             placeholder="At least 8 characters" style="font-family:monospace">
      <small style="color:#7a716a;font-size:.72rem">Visible while typing — copy it before saving</small>
    </div>
    <div>
      <label>Role *</label>
      <select name="role">
        <option value="staff">Staff — Daily Operations only</option>
        <option value="admin">Admin — Full access</option>
      </select>
    </div>
    <div>
      <label>Title / position</label>
      <input type="text" name="staff_title" maxlength="120" placeholder="e.g. Counter Staff">
    </div>
    <div style="display:flex;align-items:flex-end">
      <label style="display:flex;gap:.5rem;align-items:center;text-transform:none;letter-spacing:0">
        <input type="checkbox" name="is_active" value="1" checked style="width:auto"> Active
      </label>
    </div>
  </div>

  <button class="btn btn-primary" style="margin-top:1.2rem">Create user</button>
</form>

<div class="card" style="padding:0">
  <div class="admin-table-wrap" style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
      <thead><tr style="text-align:left;font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:#7a716a">
        <th style="padding:1rem">Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Title</th>
        <th>Active</th>
        <th>New password</th>
        <th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($users as $u): $isMe = (int)$u['id'] === (int)$_SESSION['admin_id']; ?>
        <tr style="border-top:1px solid #262220">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">

            <td style="padding:1rem;min-width:180px">
              <input type="text" name="name" value="<?= e($u['name']) ?>" required maxlength="120" style="width:100%">
              <?php if ($isMe): ?><div style="color:var(--accent-2);font-size:.72rem;margin-top:.3rem">You</div><?php endif; ?>
            </td>

            <td style="min-width:220px">
              <input type="email" name="email" value="<?= e($u['email']) ?>" required maxlength="190" style="width:100%">
            </td>

            <td>
              <?php if ($isMe): ?>
                <span style="color:var(--accent-2);font-weight:600">admin</span>
                <input type="hidden" name="role" value="admin">
              <?php else: ?>
                <select name="role">
                  <option value="staff" <?= $u['role']==='staff'?'selected':'' ?>>Staff</option>
                  <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
                </select>
              <?php endif; ?>
            </td>

            <td style="min-width:140px">
              <input type="text" name="staff_title" value="<?= e($u['staff_title'] ?? '') ?>" maxlength="120" style="width:100%">
            </td>

            <td>
              <?php if ($isMe): ?>
                <span style="color:#bfe7c8;font-size:.8rem">active</span>
                <input type="hidden" name="is_active" value="1">
              <?php else: ?>
                <label style="display:flex;gap:.4rem;align-items:center;text-transform:none;letter-spacing:0">
                  <input type="checkbox" name="is_active" value="1" <?= $u['is_active']?'checked':'' ?> style="width:auto">
                </label>
              <?php endif; ?>
            </td>

            <td style="min-width:180px">
              <input type="text" name="password" placeholder="Leave blank to keep"
                     minlength="8" style="width:100%;font-size:.8rem;font-family:monospace">
            </td>

            <td style="padding:1rem 0;white-space:nowrap">
              <button class="btn btn-sm btn-primary">Save</button>
              <?php if (!$isMe): ?>
                <button class="btn btn-sm btn-ghost" name="action" value="delete" formnovalidate
                        onclick="return confirm('Delete this user permanently?')"
                        style="border-color:#a33;color:#f0b3b3">Delete</button>
              <?php endif; ?>
            </td>
          </form>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>