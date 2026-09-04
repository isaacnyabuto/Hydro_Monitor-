<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$pageTitle = 'Turbine Systems';
$pageSubtitle = 'Coordinated turbine operation, gate control and pressure regulation';
$activeNav = 'turbines';

$stations = fetchAll("SELECT * FROM stations WHERE status != 'decommissioned' ORDER BY name");
$stationId = (int) ($_GET['station_id'] ?? ($stations[0]['id'] ?? 0));

// Log a telemetry reading (from field SCADA or manual entry)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['turbine_id'])) {
    verifyCsrf();
    $status = 'normal';
    if ((float) $_POST['vibration_mm_s'] >= 4.5 || (float) $_POST['penstock_pressure_bar'] >= 13.5 || (float) $_POST['bearing_temperature_c'] >= 75) {
        $status = 'critical';
    } elseif ((float) $_POST['vibration_mm_s'] >= 3 || (float) $_POST['penstock_pressure_bar'] >= 12.5) {
        $status = 'warning';
    }
    execSql(
        'INSERT INTO turbine_status_log (turbine_id, rpm, output_mw, penstock_pressure_bar, bearing_temperature_c,
         vibration_mm_s, gate_opening_pct, status)
         VALUES (:t, :rpm, :out, :p, :temp, :vib, :gate, :status)',
        [
            't' => $_POST['turbine_id'], 'rpm' => $_POST['rpm'] ?: null, 'out' => $_POST['output_mw'] ?: null,
            'p' => $_POST['penstock_pressure_bar'] ?: null, 'temp' => $_POST['bearing_temperature_c'] ?: null,
            'vib' => $_POST['vibration_mm_s'] ?: null, 'gate' => $_POST['gate_opening_pct'] ?: null, 'status' => $status,
        ]
    );
    // Also mirror into power_generation so the dashboard chart reflects it
    if (!empty($_POST['output_mw'])) {
        $turbine = fetchOne('SELECT * FROM turbines WHERE id=:id', ['id' => $_POST['turbine_id']]);
        execSql(
            'INSERT INTO power_generation (station_id, turbine_id, output_mw, energy_kwh) VALUES (:s, :t, :o, :e)',
            ['s' => $turbine['station_id'], 't' => $_POST['turbine_id'], 'o' => $_POST['output_mw'], 'e' => $_POST['output_mw'] * 1000 / 60]
        );
    }
    flash('success', 'Turbine telemetry logged (status: ' . $status . ').');
    header('Location: turbines.php?station_id=' . (int) ($_POST['redirect_station'] ?? $stationId));
    exit;
}

$turbines = $stationId ? fetchAll('SELECT * FROM turbines WHERE station_id=:s ORDER BY turbine_code', ['s' => $stationId]) : [];
foreach ($turbines as &$t) {
    $t['latest'] = fetchOne('SELECT * FROM turbine_status_log WHERE turbine_id=:id ORDER BY log_time DESC LIMIT 1', ['id' => $t['id']]);
}
unset($t);

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

<div class="panel" style="margin-bottom:20px;">
  <div class="panel-title">How the Turbine Fleet Works Collaboratively</div>
  <p style="color:var(--text-dim);font-size:13px;line-height:1.7;">
    Each station runs multiple turbine units that share one reservoir and penstock system. The system logic below
    coordinates them so no single unit is overloaded and reservoir pressure stays within safe bounds:
  </p>
  <div class="grid grid-3">
    <div>
      <strong style="color:var(--accent);">1. Load Sharing</strong>
      <p style="color:var(--text-dim);font-size:12.5px;">When total demand rises, output is distributed across all <em>online</em> units rather than pushing one turbine to its limit — reducing wear and vibration risk per unit.</p>
    </div>
    <div>
      <strong style="color:var(--accent);">2. Gate Opening = Pressure Control</strong>
      <p style="color:var(--text-dim);font-size:12.5px;">The wicket gate opening (%) on each turbine is the primary lever for penstock pressure. Wider openings pass more flow at lower pressure per unit; the alert bot flags units approaching the 13.5 bar critical ceiling.</p>
    </div>
    <div>
      <strong style="color:var(--accent);">3. Standby Rotation</strong>
      <p style="color:var(--text-dim);font-size:12.5px;">Standby turbines automatically become the preferred next unit to bring online when active units near vibration/temperature thresholds, spreading operating hours evenly across the fleet.</p>
    </div>
  </div>
</div>

<div class="grid grid-2" style="margin-bottom:20px;">
  <?php foreach ($turbines as $t): $lt = $t['latest']; ?>
  <div class="panel">
    <div class="panel-title">
      <?= e($t['turbine_code']) ?> — <?= e(ucfirst($t['turbine_type'])) ?>
      <?= statusBadge($t['status']) ?>
    </div>
    <div class="grid grid-3" style="margin-bottom:10px;">
      <div><div class="stat-label" style="font-size:11px;">Output</div><div style="font-size:18px;font-weight:700;"><?= $lt ? number_format($lt['output_mw'],1) : '0.0' ?> MW</div></div>
      <div><div class="stat-label" style="font-size:11px;">Pressure</div><div style="font-size:18px;font-weight:700;" class="<?= $lt && $lt['penstock_pressure_bar']>=13.5 ? 'sev-critical' : ($lt && $lt['penstock_pressure_bar']>=12.5 ? 'sev-warning':'') ?>"><?= $lt ? number_format($lt['penstock_pressure_bar'],1) : '—' ?> bar</div></div>
      <div><div class="stat-label" style="font-size:11px;">Vibration</div><div style="font-size:18px;font-weight:700;" class="<?= $lt && $lt['vibration_mm_s']>=4.5 ? 'sev-critical' : ($lt && $lt['vibration_mm_s']>=3 ? 'sev-warning':'') ?>"><?= $lt ? number_format($lt['vibration_mm_s'],2) : '—' ?> mm/s</div></div>
    </div>
    <div style="font-size:12px;color:var(--text-dim);margin-bottom:12px;">
      RPM: <?= $lt ? number_format($lt['rpm'],0) : '—' ?> / <?= number_format($t['rated_rpm'],0) ?> rated &middot;
      Gate: <?= $lt ? number_format($lt['gate_opening_pct'],0) : '—' ?>% &middot;
      Bearing: <?= $lt ? number_format($lt['bearing_temperature_c'],1) : '—' ?>&deg;C &middot;
      Capacity: <?= number_format($t['capacity_mw'],1) ?> MW
    </div>

    <details>
      <summary style="cursor:pointer;color:var(--accent);font-size:12.5px;">+ Log new telemetry reading</summary>
      <form method="POST" style="margin-top:12px;">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="turbine_id" value="<?= (int) $t['id'] ?>">
        <input type="hidden" name="redirect_station" value="<?= (int) $stationId ?>">
        <div class="grid grid-3">
          <div class="form-group"><label>RPM</label><input type="number" step="0.1" name="rpm"></div>
          <div class="form-group"><label>Output (MW)</label><input type="number" step="0.01" name="output_mw"></div>
          <div class="form-group"><label>Gate Opening (%)</label><input type="number" step="0.1" name="gate_opening_pct"></div>
          <div class="form-group"><label>Pressure (bar)</label><input type="number" step="0.01" name="penstock_pressure_bar"></div>
          <div class="form-group"><label>Vibration (mm/s)</label><input type="number" step="0.01" name="vibration_mm_s"></div>
          <div class="form-group"><label>Bearing Temp (&deg;C)</label><input type="number" step="0.1" name="bearing_temperature_c"></div>
        </div>
        <button class="btn btn-primary btn-sm" type="submit">Submit Reading</button>
      </form>
    </details>
  </div>
  <?php endforeach; ?>
  <?php if (empty($turbines)): ?><div class="panel">No turbines registered for this station.</div><?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
