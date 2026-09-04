<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['super_admin', 'admin']);

$pageTitle = 'Activity Log';
$pageSubtitle = 'Audit trail of all administrator actions';
$activeNav = 'logs';

$logs = fetchAll(
    'SELECT l.*, a.full_name, a.username FROM activity_logs l
     LEFT JOIN admins a ON a.id = l.admin_id
     ORDER BY l.created_at DESC LIMIT 200'
);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel">
  <div class="panel-title">Recent Activity (last 200 events)</div>
  <table>
    <thead><tr><th>Time</th><th>Admin</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
    <tbody>
      <?php foreach ($logs as $l): ?>
      <tr>
        <td style="white-space:nowrap;"><?= date('M j H:i:s', strtotime($l['created_at'])) ?></td>
        <td><?= e($l['full_name'] ?? 'System') ?></td>
        <td><span class="badge badge-info"><?= e($l['action']) ?></span></td>
        <td style="max-width:340px;"><?= e($l['details']) ?></td>
        <td style="font-size:11.5px;color:var(--text-dim);"><?= e($l['ip_address']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
