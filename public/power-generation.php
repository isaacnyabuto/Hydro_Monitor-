<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$pageTitle = 'Power Generation';
$pageSubtitle = 'Output, grid metrics and energy totals';
$activeNav = 'power';

$stations = fetchAll("SELECT * FROM stations WHERE status != 'decommissioned' ORDER BY name");
$stationId = (int) ($_GET['station_id'] ?? ($stations[0]['id'] ?? 0));

$todayTotal = $stationId ? fetchOne(
    "SELECT COALESCE(SUM(energy_kwh),0) kwh FROM power_generation WHERE station_id=:s AND gen_time::date = CURRENT_DATE",
    ['s' => $stationId]
)['kwh'] : 0;

$monthTotal = $stationId ? fetchOne(
    "SELECT COALESCE(SUM(energy_kwh),0) kwh FROM power_generation WHERE station_id=:s AND date_trunc('month', gen_time) = date_trunc('month', CURRENT_DATE)",
    ['s' => $stationId]
)['kwh'] : 0;

$avgFrequency = $stationId ? fetchOne(
    "SELECT AVG(grid_frequency_hz) f FROM power_generation WHERE station_id=:s AND gen_time > NOW() - INTERVAL '1 hour'",
    ['s' => $stationId]
)['f'] : null;

$recent = $stationId ? fetchAll('SELECT pg.*, t.turbine_code FROM power_generation pg
    LEFT JOIN turbines t ON t.id = pg.turbine_id
    WHERE pg.station_id=:s ORDER BY pg.gen_time DESC LIMIT 20', ['s' => $stationId]) : [];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel" style="margin-bottom:20px;">
  <form method="GET" style="display:flex;gap:12px;">
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

<div class="grid grid-3" style="margin-bottom:20px;">
  <div class="panel stat-card"><div class="accent-bar" style="background:var(--ok)"></div>
    <div class="stat-label">Energy Generated Today</div>
    <div class="stat-value"><?= number_format($todayTotal / 1000, 2) ?> <small style="font-size:13px;">MWh</small></div>
  </div>
  <div class="panel stat-card"><div class="accent-bar" style="background:var(--accent)"></div>
    <div class="stat-label">Energy Generated This Month</div>
    <div class="stat-value"><?= number_format($monthTotal / 1000, 2) ?> <small style="font-size:13px;">MWh</small></div>
  </div>
  <div class="panel stat-card"><div class="accent-bar" style="background:var(--info)"></div>
    <div class="stat-label">Avg Grid Frequency (1h)</div>
    <div class="stat-value"><?= $avgFrequency !== null ? number_format($avgFrequency, 2) : '—' ?> <small style="font-size:13px;">Hz</small></div>
    <div class="stat-sub">Nominal: 50.00 Hz</div>
  </div>
</div>

<div class="panel">
  <div class="panel-title">Recent Generation Log</div>
  <table>
    <thead><tr><th>Time</th><th>Turbine</th><th>Output</th><th>Energy</th><th>Grid Freq.</th><th>Voltage</th></tr></thead>
    <tbody>
      <?php if (empty($recent)): ?><tr><td colspan="6" style="color:var(--text-dim);">No generation records yet. Log turbine telemetry on the Turbine Systems page to populate this.</td></tr><?php endif; ?>
      <?php foreach ($recent as $r): ?>
      <tr>
        <td><?= date('M j H:i:s', strtotime($r['gen_time'])) ?></td>
        <td><?= e($r['turbine_code'] ?? 'Station total') ?></td>
        <td><?= number_format($r['output_mw'],2) ?> MW</td>
        <td><?= $r['energy_kwh']!==null ? number_format($r['energy_kwh'],1).' kWh' : '—' ?></td>
        <td><?= $r['grid_frequency_hz']!==null ? number_format($r['grid_frequency_hz'],2).' Hz' : '—' ?></td>
        <td><?= $r['grid_voltage_kv']!==null ? number_format($r['grid_voltage_kv'],1).' kV' : '—' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
