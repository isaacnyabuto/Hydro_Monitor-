<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$pageTitle = 'System Dashboard';
$pageSubtitle = 'Real-time overview of all hydroelectric stations';
$activeNav = 'dashboard';

$stationCount   = fetchOne("SELECT COUNT(*) c FROM stations WHERE status != 'decommissioned'")['c'];
$onlineTurbines = fetchOne("SELECT COUNT(*) c FROM turbines WHERE status = 'online'")['c'];
$totalCapacity  = fetchOne("SELECT COALESCE(SUM(installed_capacity_mw),0) c FROM stations")['c'];
$currentOutput  = fetchOne("SELECT COALESCE(SUM(total_output_mw),0) c FROM v_station_current_output")['c'];
$openAlerts     = fetchOne("SELECT COUNT(*) c FROM alerts WHERE status = 'open'")['c'];
$activeFloods   = fetchOne("SELECT COUNT(*) c FROM flood_events WHERE status = 'active'")['c'];

$stations = fetchAll("SELECT s.*, vl.water_level_m, vl.reservoir_volume_pct, vw.rainfall_mm, vo.total_output_mw
                       FROM stations s
                       LEFT JOIN v_latest_water_level vl ON vl.station_id = s.id
                       LEFT JOIN v_latest_weather vw ON vw.station_id = s.id
                       LEFT JOIN v_station_current_output vo ON vo.station_id = s.id
                       WHERE s.status != 'decommissioned'
                       ORDER BY s.name");

$recentAlerts = fetchAll("SELECT a.*, s.name AS station_name FROM alerts a
                           JOIN stations s ON s.id = a.station_id
                           ORDER BY a.triggered_at DESC LIMIT 8");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="grid grid-4" style="margin-bottom:20px;">
  <div class="panel stat-card"><div class="accent-bar" style="background:var(--accent)"></div>
    <div class="stat-label">Stations Online</div>
    <div class="stat-value"><?= (int)$stationCount ?></div>
    <div class="stat-sub"><?= (int)$onlineTurbines ?> turbines active</div>
    <div class="stat-icon">&#127970;</div>
  </div>
  <div class="panel stat-card"><div class="accent-bar" style="background:var(--ok)"></div>
    <div class="stat-label">Current Output</div>
    <div class="stat-value"><?= number_format($currentOutput, 1) ?> <small style="font-size:14px;">MW</small></div>
    <div class="stat-sub">of <?= number_format($totalCapacity, 1) ?> MW installed capacity</div>
    <div class="stat-icon">&#9889;</div>
  </div>
  <div class="panel stat-card"><div class="accent-bar" style="background:<?= $openAlerts>0?'var(--critical)':'var(--ok)' ?>"></div>
    <div class="stat-label">Open Alerts</div>
    <div class="stat-value" style="color:<?= $openAlerts>0?'var(--critical)':'var(--text-main)' ?>"><?= (int)$openAlerts ?></div>
    <div class="stat-sub"><a href="alerts.php">View alert center &rarr;</a></div>
    <div class="stat-icon">&#128276;</div>
  </div>
  <div class="panel stat-card"><div class="accent-bar" style="background:<?= $activeFloods>0?'var(--emergency)':'var(--ok)' ?>"></div>
    <div class="stat-label">Active Flood Events</div>
    <div class="stat-value" style="color:<?= $activeFloods>0?'var(--emergency)':'var(--text-main)' ?>"><?= (int)$activeFloods ?></div>
    <div class="stat-sub"><a href="water-level.php">View flood monitoring &rarr;</a></div>
    <div class="stat-icon">&#127754;</div>
  </div>
</div>

<div class="grid grid-3" style="margin-bottom:20px;">
  <div class="panel" style="grid-column: span 2;">
    <div class="panel-title">Power Output — Last 24 Hours <span id="chartUpdated" style="font-weight:400;text-transform:none;"></span></div>
    <canvas id="powerChart" height="90"></canvas>
  </div>
  <div class="panel">
    <div class="panel-title">Recent Alerts <a href="alerts.php" class="btn btn-sm">All</a></div>
    <div id="alertFeed">
      <?php if (empty($recentAlerts)): ?>
        <p style="color:var(--text-dim);font-size:13px;">No alerts recorded yet.</p>
      <?php endif; ?>
      <?php foreach ($recentAlerts as $a): ?>
        <div class="alert-feed-item">
          <div class="sev-dot <?= e($a['severity']) ?>"></div>
          <div>
            <div class="msg"><?= e($a['message']) ?></div>
            <div class="meta"><?= e($a['station_name']) ?> &middot; <?= timeAgo($a['triggered_at']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-title">
    Station Status Overview
    <button class="btn btn-sm btn-primary" onclick="runCheck()">Run Alert Check Now</button>
  </div>
  <table>
    <thead>
      <tr>
        <th>Station</th><th>Status</th><th>Water Level</th><th>Reservoir</th>
        <th>Rainfall</th><th>Output</th><th>Capacity</th><th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($stations as $s): ?>
      <tr>
        <td><strong><?= e($s['name']) ?></strong><br><span style="color:var(--text-dim);font-size:11.5px;"><?= e($s['location']) ?></span></td>
        <td><?= statusBadge($s['status']) ?></td>
        <td><?= $s['water_level_m'] !== null ? number_format($s['water_level_m'],2).' m' : '—' ?></td>
        <td>
          <?php if ($s['reservoir_volume_pct'] !== null): ?>
          <div class="progress-track" style="width:90px;"><div class="progress-fill" style="width:<?= min(100,(float)$s['reservoir_volume_pct']) ?>%;background:var(--accent);"></div></div>
          <span style="font-size:11.5px;color:var(--text-dim);"><?= number_format($s['reservoir_volume_pct'],0) ?>%</span>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td><?= $s['rainfall_mm'] !== null ? number_format($s['rainfall_mm'],1).' mm' : '—' ?></td>
        <td><?= $s['total_output_mw'] !== null ? number_format($s['total_output_mw'],1).' MW' : '0.0 MW' ?></td>
        <td><?= number_format($s['installed_capacity_mw'],1) ?> MW</td>
        <td><a href="stations.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm">Details</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
async function loadPowerChart() {
  const res = await fetch('api/get_power_data.php?hours=24');
  const data = await res.json();
  const ctx = document.getElementById('powerChart');
  if (window.powerChartInstance) window.powerChartInstance.destroy();
  window.powerChartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels: data.labels,
      datasets: data.datasets
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { labels: { color: '#8b9bb0' } } },
      scales: {
        x: { ticks: { color: '#8b9bb0' }, grid: { color: '#2a3644' } },
        y: { ticks: { color: '#8b9bb0' }, grid: { color: '#2a3644' }, title: { display: true, text: 'MW', color: '#8b9bb0' } }
      }
    }
  });
  document.getElementById('chartUpdated').innerText = '(updated ' + new Date().toLocaleTimeString() + ')';
}
async function runCheck() {
  const btn = event.target;
  btn.disabled = true; btn.innerText = 'Running...';
  const res = await fetch('api/check_alerts.php', { method: 'POST' });
  const data = await res.json();
  btn.disabled = false; btn.innerText = 'Run Alert Check Now';
  alert('Alert check complete: ' + data.alerts_created + ' new alert(s), ' + data.flood_events + ' flood event(s) updated.');
  location.reload();
}
loadPowerChart();
setInterval(loadPowerChart, 60000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
