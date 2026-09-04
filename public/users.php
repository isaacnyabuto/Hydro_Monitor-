<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['super_admin', 'admin']);

$pageTitle = 'Admin Users';
$pageSubtitle = 'Manage who can access and operate the monitoring system';
$activeNav = 'users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['form_action'] ?? '';

    if ($action === 'create') {
        $exists = fetchOne('SELECT id FROM admins WHERE username=:u OR email=:e', ['u' => $_POST['username'], 'e' => $_POST['email']]);
        if ($exists) {
            flash('error', 'Username or email already exists.');
        } else {
            execSql(
                'INSERT INTO admins (username, email, password_hash, full_name, role) VALUES (:u, :e, :p, :f, :r)',
                [
                    'u' => trim($_POST['username']), 'e' => trim($_POST['email']),
                    'p' => password_hash($_POST['password'], PASSWORD_BCRYPT),
                    'f' => trim($_POST['full_name']), 'r' => $_POST['role'],
                ]
            );
            logActivity($_SESSION['admin_id'], 'CREATE_ADMIN', "Created admin user: {$_POST['username']}");
            flash('success', 'Admin user created.');
        }
    } elseif ($action === 'toggle') {
        execSql('UPDATE admins SET is_active = NOT is_active WHERE id = :id', ['id' => $_POST['id']]);
        logActivity($_SESSION['admin_id'], 'TOGGLE_ADMIN', "Toggled active status for admin #{$_POST['id']}");
        flash('success', 'Admin status updated.');
    } elseif ($action === 'reset_password') {
        execSql('UPDATE admins SET password_hash=:p WHERE id=:id', [
            'p' => password_hash($_POST['password'], PASSWORD_BCRYPT), 'id' => $_POST['id'],
        ]);
        logActivity($_SESSION['admin_id'], 'RESET_PASSWORD', "Reset password for admin #{$_POST['id']}");
        flash('success', 'Password reset.');
    }
    header('Location: users.php');
    exit;
}

$admins = fetchAll('SELECT * FROM admins ORDER BY created_at DESC');

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg = flash('success')): ?><div class="alert-banner success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($msg = flash('error')): ?><div class="alert-banner error"><?= e($msg) ?></div><?php endif; ?>

<div class="grid grid-2" style="align-items:start;">
  <div class="panel">
    <div class="panel-title">Create Admin User</div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="form_action" value="create">
      <div class="form-group"><label>Full Name</label><input type="text" name="full_name" required></div>
      <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
      <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
      <div class="form-group"><label>Password</label><input type="password" name="password" required minlength="8"></div>
      <div class="form-group">
        <label>Role</label>
        <select name="role">
          <option value="operator">Operator</option>
          <option value="admin">Admin</option>
          <?php if ($_SESSION['role'] === 'super_admin'): ?><option value="super_admin">Super Admin</option><?php endif; ?>
          <option value="viewer">Viewer (read-only)</option>
        </select>
      </div>
      <button class="btn btn-primary" type="submit">Create User</button>
    </form>
  </div>

  <div class="panel">
    <div class="panel-title">All Admin Users</div>
    <table>
      <thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Last Login</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($admins as $a): ?>
        <tr>
          <td><strong><?= e($a['full_name']) ?></strong><br><span style="color:var(--text-dim);font-size:11.5px;">@<?= e($a['username']) ?></span></td>
          <td><?= e(ucfirst(str_replace('_',' ',$a['role']))) ?></td>
          <td><?= $a['is_active'] ? statusBadge('active') : statusBadge('offline') ?></td>
          <td style="font-size:12px;"><?= $a['last_login'] ? timeAgo($a['last_login']) : 'Never' ?></td>
          <td>
            <form method="POST" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="form_action" value="toggle"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
              <button class="btn btn-sm" type="submit"><?= $a['is_active'] ? 'Disable' : 'Enable' ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
