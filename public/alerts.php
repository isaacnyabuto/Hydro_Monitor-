<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$pageTitle = 'Alert Center';
$pageSubtitle = 'The Alert Bot — automatic rule-based monitoring and notifications';
$activeNav = 'alerts';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id = (int) $_POST['alert_id'];
    if ($_POST['action'] === 'acknowledge') {
        execSql("UPDATE alerts SET status='acknowledged', acknowledged_at=NOW(), acknowledged_by=:a WHERE id=:id",
            ['a' => $_SESSION['admin_id'], 'id' => $id]);
        logActivity($_SESSION['admin_id'], 'ACK_ALERT', "Acknowledged alert #$id");
    } elseif ($_POST['action'] === 'resolve') {
        execSql("UPDATE alerts SET status='resolved', resolved_at=NOW() WHERE id=:id", ['id' => $id]);
        logActivity($_SESSION['admin_id'], 'RESOLVE_ALERT', "Resolved alert #$id");
    }
    header('Location: alerts.php?status=' . urlencode($_GET['status'] ?? 'open'));
    exit;
}

$statusFilter = $_GET['status'] ?? 'open';
$where = $statusFilter === 'all' ? '1=1' : 'a.status = :status';
$params = $statusFilter === 'all' ? [] : ['status' => $statusFilter];

$alerts = fetchAll(
    "SELECT a.*, s.name AS station_name FROM alerts a
     JOIN stations s ON s.id = a.station_id
     WHERE $where ORDER BY a.triggered_at DESC LIMIT 100",
    $params
);

$counts = fetchOne(
    "SELECT COUNT(*) FILTER (WHERE status='open') AS open,
            COUNT(*) FILTER (WHERE status='acknowledged') AS ack,
            COUNT(*) FILTER (WHERE status='resolved') AS resolved
     FROM alerts"
);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="grid grid-3" style="margin-bottom:20px;">
  <div class="panel stat-card"><div class="accent-bar" style="background:var(--critical)"></div>
    <div class="stat-label">Open</div><div class="stat-value"><?= (int) $counts['open'] ?></div>
  </div>
  <div class="panel stat-card"><div class="accent-bar" style="background:var(--warning)"></div>
    <div class="stat-label">Acknowledged</div><div class="stat-value"><?= (int) $counts['ack'] ?></div>
  </div>
  <div class="panel stat-card"><div class="accent-bar" style="background:var(--ok)"></div>
    <div class="stat-label">Resolved</div><div class="stat-value"><?= (int) $counts['resolved'] ?></div>
  </div>
</div>

<div class="panel">
  <div class="panel-title">
    Alerts
    <span>
      <a href="?status=open" class="btn btn-sm <?= $statusFilter==='open'?'btn-primary':'' ?>">Open</a>
      <a href="?status=acknowledged" class="btn btn-sm <?= $statusFilter==='acknowledged'?'btn-primary':'' ?>">Acknowledged</a>
      <a href="?status=resolved" class="btn btn-sm <?= $statusFilter==='resolved'?'btn-primary':'' ?>">Resolved</a>
      <a href="?status=all" class="btn btn-sm <?= $statusFilter==='all'?'btn-primary':'' ?>">All</a>
    </span>
  </div>
  <table>
    <thead><tr><th>Severity</th><th>Station</th><th>Type</th><th>Message</th><th>Triggered</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php if (empty($alerts)): ?><tr><td colspan="7" style="color:var(--text-dim);">No alerts in this view.</td></tr><?php endif; ?>
      <?php foreach ($alerts as $a): ?>
      <tr>
        <td><span class="<?= severityColor($a['severity']) ?>">&#9679; <?= e(ucfirst($a['severity'])) ?></span></td>
        <td><?= e($a['station_name']) ?></td>
        <td><?= e(ucfirst(str_replace('_',' ',$a['alert_type']))) ?></td>
        <td style="max-width:320px;"><?= e($a['message']) ?></td>
        <td><?= timeAgo($a['triggered_at']) ?></td>
        <td><?= statusBadge($a['status']) ?></td>
        <td>
          <?php if ($a['status'] === 'open'): ?>
          <form method="POST" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="alert_id" value="<?= (int) $a['id'] ?>">
            <input type="hidden" name="action" value="acknowledge">
            <button class="btn btn-sm" type="submit">Acknowledge</button>
          </form>
          <?php endif; ?>
          <?php if ($a['status'] !== 'resolved'): ?>
          <form method="POST" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="alert_id" value="<?= (int) $a['id'] ?>">
            <input type="hidden" name="action" value="resolve">
            <button class="btn btn-sm btn-primary" type="submit">Resolve</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
