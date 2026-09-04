<?php
/**
 * Shared header/sidebar shell. Expects $pageTitle and $pageSubtitle to be set
 * by the including page, and optionally $activeNav (matches nav-link data-key).
 */
$admin = currentAdmin();
$activeNav = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'HGMS') ?> — Hydroelectric Generation Monitoring System</title>
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class="logo-dot"></div>
      <div>
        <h1>HGMS</h1>
        <span>Hydro Monitoring &amp; Control</span>
      </div>
    </div>

    <div class="nav-section-label">Overview</div>
    <a href="dashboard.php" class="nav-link <?= $activeNav==='dashboard'?'active':'' ?>"><span class="nav-icon">&#9673;</span> Dashboard</a>
    <a href="stations.php" class="nav-link <?= $activeNav==='stations'?'active':'' ?>"><span class="nav-icon">&#127970;</span> Stations</a>

    <div class="nav-section-label">Monitoring</div>
    <a href="water-level.php" class="nav-link <?= $activeNav==='water'?'active':'' ?>"><span class="nav-icon">&#128167;</span> Water &amp; Flood</a>
    <a href="weather.php" class="nav-link <?= $activeNav==='weather'?'active':'' ?>"><span class="nav-icon">&#9925;</span> Weather Analysis</a>
    <a href="turbines.php" class="nav-link <?= $activeNav==='turbines'?'active':'' ?>"><span class="nav-icon">&#9881;</span> Turbine Systems</a>
    <a href="power-generation.php" class="nav-link <?= $activeNav==='power'?'active':'' ?>"><span class="nav-icon">&#9889;</span> Power Generation</a>

    <div class="nav-section-label">Safety &amp; Alerts</div>
    <a href="risk-analysis.php" class="nav-link <?= $activeNav==='risk'?'active':'' ?>"><span class="nav-icon">&#9888;</span> Risk Analysis</a>
    <a href="alerts.php" class="nav-link <?= $activeNav==='alerts'?'active':'' ?>"><span class="nav-icon">&#128276;</span> Alert Center</a>
    <a href="reports.php" class="nav-link <?= $activeNav==='reports'?'active':'' ?>"><span class="nav-icon">&#128202;</span> Reports</a>

    <?php if (in_array($admin['role'], ['super_admin','admin'], true)): ?>
    <div class="nav-section-label">Administration</div>
    <a href="users.php" class="nav-link <?= $activeNav==='users'?'active':'' ?>"><span class="nav-icon">&#128101;</span> Admin Users</a>
    <a href="activity-log.php" class="nav-link <?= $activeNav==='logs'?'active':'' ?>"><span class="nav-icon">&#128220;</span> Activity Log</a>
    <?php endif; ?>

    <a href="logout.php" class="nav-link" style="margin-top:10px;"><span class="nav-icon">&#10162;</span> Logout</a>
  </aside>

  <div class="main">
    <div class="topbar">
      <div>
        <h2><?= e($pageTitle ?? '') ?></h2>
        <?php if (!empty($pageSubtitle)): ?><div class="subtitle"><?= e($pageSubtitle) ?></div><?php endif; ?>
      </div>
      <div class="topbar-right">
        <span class="live-dot">LIVE</span>
        <div class="user-chip">
          <div class="avatar-circle"><?= e(strtoupper(substr($admin['full_name'] ?? 'A', 0, 1))) ?></div>
          <div>
            <div><?= e($admin['full_name'] ?? '') ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="content">
