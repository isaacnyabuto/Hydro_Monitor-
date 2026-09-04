<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/alert_engine.php';
requireLogin();

$pageTitle = 'Risk Analysis';
$pageSubtitle = 'Composite risk scoring across flood, mechanical and weather factors';
$activeNav = 'risk';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recompute'])) {
    verifyCsrf();
    runAlertEngine();
    flash('success', 'Risk scores recomputed.');
    header('Location: risk-analysis.php');
    exit;
}

$stations = fetchAll("SELECT * FROM stations WHERE status != 'decommissioned' ORDER BY name");

$latestRisks = fetchAll(
    "SELECT DISTINCT ON (station_id, risk_type) *
     FROM risk_assessments
     ORDER BY station_id, risk_type, assessed_at DESC"
);
$risksByStation = [];
foreach ($latestRisks as $r) {
    $risksByStation[$r['station_id']][] = $r;
}

$history = fetchAll('SELECT ra.*, s.name AS station_name FROM risk_assessments ra
    JOIN stations s ON s.id = ra.station_id ORDER BY ra.assessed_at DESC LIMIT 25');

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg = flash('success')): ?><div class="alert-banner success"><?= e($msg) ?></div><?php endif; ?>

<div class="panel" style="margin-bottom:20px;">
  <div class="panel-title">
    Risk Scoring Methodology
    <form method="POST" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <button class="btn btn-sm btn-primary" name="recompute" value="1" type="submit">Recompute Now</button>
    </form>
  </div>
  <p style="color:var(--text-dim);font-size:13px;line-height:1.7;">
    Scores range 0–100 and are recalculated automatically by the alert bot (and on demand here):
    <strong>Flood risk</strong> = current water level &divide; station flood threshold &times; 100.
    <strong>Mechanical risk</strong> = peak turbine vibration in the last 10 minutes &divide; 6.0 mm/s ceiling &times; 100.
    <strong>Weather risk</strong> = latest rainfall intensity &divide; 60 mm/hr severe-storm reference &times; 100.
    Bands: 0–34 Low, 35–64 Moderate, 65–89 High, 90+ Severe.
  </p>
</div>

<?php foreach ($stations as $s): $risks = $risksByStation[$s['id']] ?? []; ?>
<div class="panel" style="margin-bottom:18px;">
  <div class="panel-title"><?= e($s['name']) ?></div>
  <?php if (empty($risks)): ?>
    <p style="color:var(--text-dim);font-size:13px;">No risk assessments yet — click "Recompute Now" once you have some readings logged.</p>
  <?php else: ?>
  <div class="grid grid-3">
    <?php foreach ($risks as $r): ?>
    <div>
      <div style="display:flex;justify-content:space-between;font-size:12.5px;color:var(--text-dim);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">
        <span><?= e(ucfirst($r['risk_type'])) ?> Risk</span>
        <span class="<?= riskColor($r['risk_level']) ?>"><?= e(ucfirst($r['risk_level'])) ?></span>
      </div>
      <div class="progress-track" style="height:12px;margin-bottom:6px;">
        <div class="progress-fill" style="width:<?= min(100,(float)$r['score']) ?>%;background:<?= $r['risk_level']==='severe'?'var(--emergency)':($r['risk_level']==='high'?'var(--critical)':($r['risk_level']==='moderate'?'var(--warning)':'var(--ok)')) ?>;"></div>
      </div>
      <div style="font-size:20px;font-weight:700;"><?= number_format($r['score'],1) ?></div>
      <div style="font-size:11.5px;color:var(--text-dim);"><?= e($r['notes']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<div class="panel">
  <div class="panel-title">Assessment History</div>
  <table>
    <thead><tr><th>Time</th><th>Station</th><th>Type</th><th>Level</th><th>Score</th></tr></thead>
    <tbody>
      <?php if (empty($history)): ?><tr><td colspan="5" style="color:var(--text-dim);">No history yet.</td></tr><?php endif; ?>
      <?php foreach ($history as $h): ?>
      <tr>
        <td><?= date('M j H:i', strtotime($h['assessed_at'])) ?></td>
        <td><?= e($h['station_name']) ?></td>
        <td><?= e(ucfirst($h['risk_type'])) ?></td>
        <td><span class="<?= riskColor($h['risk_level']) ?>"><?= e(ucfirst($h['risk_level'])) ?></span></td>
        <td><?= number_format($h['score'],1) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
