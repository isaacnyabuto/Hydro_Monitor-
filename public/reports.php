<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$pageTitle = 'Reports';
$pageSubtitle = 'Generation summaries and exportable operational reports';
$activeNav = 'reports';

$stations = fetchAll("SELECT * FROM stations ORDER BY name");
$stationId = (int) ($_GET['station_id'] ?? ($stations[0]['id'] ?? 0));
$range = $_GET['range'] ?? '7d';
$intervalMap = ['7d' => '7 days', '30d' => '30 days', '90d' => '90 days'];
$interval = $intervalMap[$range] ?? '7 days';

$dailyGen = $stationId ? fetchAll(
    "SELECT gen_time::date AS day, SUM(energy_kwh) AS kwh, AVG(output_mw) AS avg_mw, MAX(output_mw) AS peak_mw
     FROM power_generation WHERE station_id=:s AND gen_time > NOW() - (:iv)::interval
     GROUP BY day ORDER BY day DESC",
    ['s' => $stationId, 'iv' => $interval]
) : [];

$alertSummary = $stationId ? fetchAll(
    "SELECT severity, COUNT(*) AS c FROM alerts WHERE station_id=:s AND triggered_at > NOW() - (:iv)::interval GROUP BY severity",
    ['s' => $stationId, 'iv' => $interval]
) : [];

$floodSummary = $stationId ? fetchOne(
    "SELECT COUNT(*) AS c FROM flood_events WHERE station_id=:s AND event_start > NOW() - (:iv)::interval",
    ['s' => $stationId, 'iv' => $interval]
) : ['c' => 0];

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="generation_report_' . $range . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Total Energy (kWh)', 'Avg Output (MW)', 'Peak Output (MW)']);
    foreach ($dailyGen as $row) {
        fputcsv($out, [$row['day'], $row['kwh'], round($row['avg_mw'], 2), round($row['peak_mw'], 2)]);
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel" style="margin-bottom:20px;">
  <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;">
    <div class="form-group" style="margin:0;min-width:240px;">
      <label>Station</label>
      <select name="station_id" onchange="this.form.submit()">
        <?php foreach ($stations as $s): ?>
        <option value="<?= (int) $s['id'] ?>" <?= $s['id']==$stationId?'selected':'' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;min-width:160px;">
      <label>Period</label>
      <select name="range" onchange="this.form.submit()">
        <option value="7d" <?= $range==='7d'?'selected':'' ?>>Last 7 days</option>
        <option value="30d" <?= $range==='30d'?'selected':'' ?>>Last 30 days</option>
        <option value="90d" <?= $range==='90d'?'selected':'' ?>>Last 90 days</option>
      </select>
    </div>
    <div class="form-group" style="margin:0;align-self:end;">
      <a class="btn btn-primary" href="?station_id=<?= (int) $stationId ?>&range=<?= e($range) ?>&export=csv">Export CSV</a>
    </div>
  </form>
</div>

<div class="grid grid-3" style="margin-bottom:20px;">
  <div class="panel">
    <div class="panel-title">Alerts in Period</div>
    <?php if (empty($alertSummary)): ?><p style="color:var(--text-dim);font-size:13px;">None recorded.</p><?php endif; ?>
    <?php foreach ($alertSummary as $a): ?>
      <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);">
        <span class="<?= severityColor($a['severity']) ?>"><?= e(ucfirst($a['severity'])) ?></span><strong><?= (int) $a['c'] ?></strong>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="panel">
    <div class="panel-title">Flood Events in Period</div>
    <div class="stat-value"><?= (int) $floodSummary['c'] ?></div>
  </div>
  <div class="panel">
    <div class="panel-title">Total Energy in Period</div>
    <div class="stat-value"><?= number_format(array_sum(array_column($dailyGen, 'kwh')) / 1000, 2) ?> <small style="font-size:13px;">MWh</small></div>
  </div>
</div>

<div class="panel">
  <div class="panel-title">Daily Generation Breakdown</div>
  <table>
    <thead><tr><th>Date</th><th>Total Energy</th><th>Avg Output</th><th>Peak Output</th></tr></thead>
    <tbody>
      <?php if (empty($dailyGen)): ?><tr><td colspan="4" style="color:var(--text-dim);">No generation data in this period.</td></tr><?php endif; ?>
      <?php foreach ($dailyGen as $row): ?>
      <tr>
        <td><?= date('M j, Y', strtotime($row['day'])) ?></td>
        <td><?= number_format($row['kwh'] / 1000, 2) ?> MWh</td>
        <td><?= number_format($row['avg_mw'], 2) ?> MW</td>
        <td><?= number_format($row['peak_mw'], 2) ?> MW</td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
