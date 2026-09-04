<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$pageTitle = 'Water Level & Flood Monitoring';
$pageSubtitle = 'Reservoir levels, inflow/outflow, and flood detection';
$activeNav = 'water';

$stations = fetchAll("SELECT * FROM stations WHERE status != 'decommissioned' ORDER BY name");
$stationId = (int) ($_GET['station_id'] ?? ($stations[0]['id'] ?? 0));
$station = fetchOne('SELECT * FROM stations WHERE id = :id', ['id' => $stationId]);

// Manual reading entry
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    execSql(
        'INSERT INTO water_level_readings (station_id, water_level_m, inflow_rate_m3s, outflow_rate_m3s, reservoir_volume_pct, source)
         VALUES (:s, :wl, :inf, :out, :pct, \'manual\')',
        [
            's' => $_POST['station_id'], 'wl' => $_POST['water_level_m'],
            'inf' => $_POST['inflow_rate_m3s'] ?: null, 'out' => $_POST['outflow_rate_m3s'] ?: null,
            'pct' => $_POST['reservoir_volume_pct'] ?: null,
        ]
    );
    logActivity($_SESSION['admin_id'], 'MANUAL_WATER_READING', "Station #{$_POST['station_id']}: {$_POST['water_level_m']}m");
    flash('success', 'Water level reading recorded.');
    header('Location: water-level.php?station_id=' . (int) $_POST['station_id']);
    exit;
}

$latest = $stationId ? fetchOne('SELECT * FROM water_level_readings WHERE station_id=:s ORDER BY reading_time DESC LIMIT 1', ['s' => $stationId]) : null;
$recentReadings = $stationId ? fetchAll('SELECT * FROM water_level_readings WHERE station_id=:s ORDER BY reading_time DESC LIMIT 15', ['s' => $stationId]) : [];
$floodEvents = $stationId ? fetchAll('SELECT * FROM flood_events WHERE station_id=:s ORDER BY event_start DESC LIMIT 10', ['s' => $stationId]) : [];

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg = flash('success')): ?><div class="alert-banner success"><?= e($msg) ?></div><?php endif; ?>

<div class="panel" style="margin-bottom:20px;">
  <form method="GET" style="display:flex;gap:12px;align-items:end;">
    <div class="form-group" style="margin:0;min-width:260px;">
      <label>Select Station</label>
      <select name="station_id" onchange="this.form.submit()">
        <?php foreach ($stations as $s): ?>
        <option value="<?= (int) $s['id'] ?>" <?= $s['id']==$stationId?'selected':'' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
</div>

<?php if ($station): ?>
<div class="grid grid-4" style="margin-bottom:20px;">
  <div class="panel stat-card"><div class="accent-bar" style="background:var(--accent)"></div>
    <div class="stat-label">Current Water Level</div>
    <div class="stat-value"><?= $latest ? number_format($latest['water_level_m'],2) : '—' ?> <small style="font-size:13px;">m</small></div>
    <div class="stat-sub">Flood threshold: <?= number_format($station['max_safe_water_level_m'],2) ?> m</div>
  </div>
  <div class="panel stat-card"><div class="accent-bar" style="background:var(--info)"></div>
    <div class="stat-label">Inflow Rate</div>
    <div class="stat-value"><?= $latest && $latest['inflow_rate_m3s']!==null ? number_format($latest['inflow_rate_m3s'],1) : '—' ?> <small style="font-size:13px;">m&sup3;/s</small></div>
  </div>
  <div class="panel stat-card"><div class="accent-bar" style="background:var(--warning)"></div>
    <div class="stat-label">Outflow Rate</div>
    <div class="stat-value"><?= $latest && $latest['outflow_rate_m3s']!==null ? number_format($latest['outflow_rate_m3s'],1) : '—' ?> <small style="font-size:13px;">m&sup3;/s</small></div>
  </div>
  <div class="panel stat-card"><div class="accent-bar" style="background:<?= !empty($floodEvents) && $floodEvents[0]['status']==='active' ? 'var(--emergency)':'var(--ok)' ?>"></div>
    <div class="stat-label">Flood Status</div>
    <div class="stat-value" style="font-size:20px;">
      <?= (!empty($floodEvents) && $floodEvents[0]['status']==='active') ? statusBadge('critical').' Active' : statusBadge('operational').' Normal' ?>
    </div>
  </div>
</div>

<div class="panel" style="margin-bottom:20px;">
  <div class="panel-title">Water Level Trend</div>
  <canvas id="waterChart" height="80"></canvas>
</div>

<div class="grid grid-2">
  <div class="panel">
    <div class="panel-title">Record Manual Reading</div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="station_id" value="<?= (int) $stationId ?>">
      <div class="form-group"><label>Water Level (m)</label><input type="number" step="0.01" name="water_level_m" required></div>
      <div class="grid grid-2">
        <div class="form-group"><label>Inflow (m&sup3;/s)</label><input type="number" step="0.01" name="inflow_rate_m3s"></div>
        <div class="form-group"><label>Outflow (m&sup3;/s)</label><input type="number" step="0.01" name="outflow_rate_m3s"></div>
      </div>
      <div class="form-group"><label>Reservoir Volume (%)</label><input type="number" step="0.01" name="reservoir_volume_pct"></div>
      <button class="btn btn-primary" type="submit">Save Reading</button>
    </form>
  </div>

  <div class="panel">
    <div class="panel-title">Flood Event History</div>
    <table>
      <thead><tr><th>Started</th><th>Severity</th><th>Peak Level</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (empty($floodEvents)): ?><tr><td colspan="4" style="color:var(--text-dim);">No flood events recorded.</td></tr><?php endif; ?>
        <?php foreach ($floodEvents as $f): ?>
        <tr>
          <td><?= date('M j, Y H:i', strtotime($f['event_start'])) ?></td>
          <td><span class="<?= severityColor($f['severity']) ?>"><?= ucfirst($f['severity']) ?></span></td>
          <td><?= number_format($f['peak_water_level_m'],2) ?> m</td>
          <td><?= statusBadge($f['status']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
async function loadWaterChart() {
  const res = await fetch('api/get_water_levels.php?station_id=<?= (int) $stationId ?>&hours=48');
  const data = await res.json();
  const ctx = document.getElementById('waterChart');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: data.labels,
      datasets: [
        { label: 'Water Level (m)', data: data.values, borderColor: '#00b4d8', backgroundColor: 'rgba(0,180,216,.1)', fill: true, tension: 0.3 },
        { label: 'Flood Threshold', data: data.labels.map(()=>data.max_safe_level), borderColor: '#e63946', borderDash: [6,6], pointRadius: 0 },
        { label: 'Min Operational', data: data.labels.map(()=>data.min_operational_level), borderColor: '#f5a623', borderDash: [6,6], pointRadius: 0 }
      ]
    },
    options: {
      responsive: true,
      plugins: { legend: { labels: { color: '#8b9bb0' } } },
      scales: {
        x: { ticks: { color: '#8b9bb0', maxTicksLimit: 10 }, grid: { color: '#2a3644' } },
        y: { ticks: { color: '#8b9bb0' }, grid: { color: '#2a3644' } }
      }
    }
  });
}
loadWaterChart();
</script>
<?php else: ?>
<div class="panel">No stations registered yet. <a href="stations.php">Add one here</a>.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
