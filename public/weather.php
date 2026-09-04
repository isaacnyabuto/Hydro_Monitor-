<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$pageTitle = 'Weather Analysis';
$pageSubtitle = 'Rainfall, temperature and forecast conditions per station';
$activeNav = 'weather';

$stations = fetchAll("SELECT * FROM stations WHERE status != 'decommissioned' ORDER BY name");
$stationId = (int) ($_GET['station_id'] ?? ($stations[0]['id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    execSql(
        'INSERT INTO weather_data (station_id, rainfall_mm, temperature_c, humidity_pct, wind_speed_kmh,
         barometric_pressure_hpa, forecast_summary, forecast_rain_probability_pct)
         VALUES (:s, :rain, :temp, :hum, :wind, :bp, :fc, :fcp)',
        [
            's' => $_POST['station_id'], 'rain' => $_POST['rainfall_mm'] ?: 0,
            'temp' => $_POST['temperature_c'] ?: null, 'hum' => $_POST['humidity_pct'] ?: null,
            'wind' => $_POST['wind_speed_kmh'] ?: null, 'bp' => $_POST['barometric_pressure_hpa'] ?: null,
            'fc' => $_POST['forecast_summary'] ?: null, 'fcp' => $_POST['forecast_rain_probability_pct'] ?: null,
        ]
    );
    flash('success', 'Weather reading logged.');
    header('Location: weather.php?station_id=' . (int) $_POST['station_id']);
    exit;
}

$latest = $stationId ? fetchOne('SELECT * FROM weather_data WHERE station_id=:s ORDER BY reading_time DESC LIMIT 1', ['s' => $stationId]) : null;
$history = $stationId ? fetchAll('SELECT * FROM weather_data WHERE station_id=:s ORDER BY reading_time DESC LIMIT 20', ['s' => $stationId]) : [];

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg = flash('success')): ?><div class="alert-banner success"><?= e($msg) ?></div><?php endif; ?>

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

<div class="grid grid-4" style="margin-bottom:20px;">
  <div class="panel stat-card"><div class="accent-bar" style="background:var(--info)"></div>
    <div class="stat-label">Rainfall</div>
    <div class="stat-value"><?= $latest ? number_format($latest['rainfall_mm'],1) : '0.0' ?> <small style="font-size:13px;">mm/hr</small></div>
  </div>
  <div class="panel stat-card"><div class="accent-bar" style="background:var(--warning)"></div>
    <div class="stat-label">Temperature</div>
    <div class="stat-value"><?= $latest && $latest['temperature_c']!==null ? number_format($latest['temperature_c'],1) : '—' ?> <small style="font-size:13px;">&deg;C</small></div>
  </div>
  <div class="panel stat-card"><div class="accent-bar" style="background:var(--accent)"></div>
    <div class="stat-label">Humidity</div>
    <div class="stat-value"><?= $latest && $latest['humidity_pct']!==null ? number_format($latest['humidity_pct'],0) : '—' ?> <small style="font-size:13px;">%</small></div>
  </div>
  <div class="panel stat-card"><div class="accent-bar" style="background:var(--ok)"></div>
    <div class="stat-label">Wind Speed</div>
    <div class="stat-value"><?= $latest && $latest['wind_speed_kmh']!==null ? number_format($latest['wind_speed_kmh'],1) : '—' ?> <small style="font-size:13px;">km/h</small></div>
  </div>
</div>

<div class="grid grid-2">
  <div class="panel">
    <div class="panel-title">Log Weather Reading</div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="station_id" value="<?= (int) $stationId ?>">
      <div class="grid grid-2">
        <div class="form-group"><label>Rainfall (mm/hr)</label><input type="number" step="0.01" name="rainfall_mm"></div>
        <div class="form-group"><label>Temperature (&deg;C)</label><input type="number" step="0.01" name="temperature_c"></div>
      </div>
      <div class="grid grid-2">
        <div class="form-group"><label>Humidity (%)</label><input type="number" step="0.01" name="humidity_pct"></div>
        <div class="form-group"><label>Wind Speed (km/h)</label><input type="number" step="0.01" name="wind_speed_kmh"></div>
      </div>
      <div class="grid grid-2">
        <div class="form-group"><label>Barometric Pressure (hPa)</label><input type="number" step="0.01" name="barometric_pressure_hpa"></div>
        <div class="form-group"><label>Rain Probability Forecast (%)</label><input type="number" step="0.01" name="forecast_rain_probability_pct"></div>
      </div>
      <div class="form-group"><label>Forecast Summary</label><input type="text" name="forecast_summary" placeholder="e.g. Heavy storms expected within 6 hours"></div>
      <button class="btn btn-primary" type="submit">Save Reading</button>
    </form>
  </div>

  <div class="panel">
    <div class="panel-title">Recent Weather Log</div>
    <table>
      <thead><tr><th>Time</th><th>Rainfall</th><th>Temp</th><th>Forecast</th></tr></thead>
      <tbody>
        <?php if (empty($history)): ?><tr><td colspan="4" style="color:var(--text-dim);">No records yet.</td></tr><?php endif; ?>
        <?php foreach ($history as $h): ?>
        <tr>
          <td><?= date('M j H:i', strtotime($h['reading_time'])) ?></td>
          <td><?= number_format($h['rainfall_mm'],1) ?> mm</td>
          <td><?= $h['temperature_c']!==null ? number_format($h['temperature_c'],1).'&deg;C' : '—' ?></td>
          <td style="max-width:200px;"><?= e($h['forecast_summary'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
