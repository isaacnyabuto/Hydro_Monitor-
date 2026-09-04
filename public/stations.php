<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$pageTitle = 'Stations';
$pageSubtitle = 'Plant registry and configuration';
$activeNav = 'stations';

// Handle create/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    requireRole(['super_admin', 'admin']);

    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'river_name' => trim($_POST['river_name'] ?? ''),
        'location' => trim($_POST['location'] ?? ''),
        'latitude' => $_POST['latitude'] ?: null,
        'longitude' => $_POST['longitude'] ?: null,
        'installed_capacity_mw' => $_POST['installed_capacity_mw'] ?: 0,
        'reservoir_max_volume_mcm' => $_POST['reservoir_max_volume_mcm'] ?: null,
        'max_safe_water_level_m' => $_POST['max_safe_water_level_m'] ?: null,
        'min_operational_level_m' => $_POST['min_operational_level_m'] ?: null,
        'status' => $_POST['status'] ?? 'operational',
    ];

    if ($data['name'] !== '') {
        if (!empty($_POST['id'])) {
            execSql(
                'UPDATE stations SET name=:name, river_name=:river_name, location=:location, latitude=:latitude,
                 longitude=:longitude, installed_capacity_mw=:installed_capacity_mw,
                 reservoir_max_volume_mcm=:reservoir_max_volume_mcm, max_safe_water_level_m=:max_safe_water_level_m,
                 min_operational_level_m=:min_operational_level_m, status=:status WHERE id=:id',
                $data + ['id' => $_POST['id']]
            );
            flash('success', 'Station updated successfully.');
            logActivity($_SESSION['admin_id'], 'UPDATE_STATION', "Updated station #{$_POST['id']}");
        } else {
            execSql(
                'INSERT INTO stations (name, river_name, location, latitude, longitude, installed_capacity_mw,
                 reservoir_max_volume_mcm, max_safe_water_level_m, min_operational_level_m, status)
                 VALUES (:name, :river_name, :location, :latitude, :longitude, :installed_capacity_mw,
                 :reservoir_max_volume_mcm, :max_safe_water_level_m, :min_operational_level_m, :status)',
                $data
            );
            flash('success', 'Station created successfully.');
            logActivity($_SESSION['admin_id'], 'CREATE_STATION', "Created station: {$data['name']}");
        }
    }
    header('Location: stations.php');
    exit;
}

$editStation = null;
if (!empty($_GET['id'])) {
    $editStation = fetchOne('SELECT * FROM stations WHERE id = :id', ['id' => (int) $_GET['id']]);
}

$stations = fetchAll('SELECT s.*,
    (SELECT COUNT(*) FROM turbines t WHERE t.station_id = s.id) AS turbine_count
    FROM stations s ORDER BY s.name');

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg = flash('success')): ?><div class="alert-banner success"><?= e($msg) ?></div><?php endif; ?>

<div class="grid grid-2" style="align-items:start;">
  <div class="panel">
    <div class="panel-title"><?= $editStation ? 'Edit Station' : 'Register New Station' ?></div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <?php if ($editStation): ?><input type="hidden" name="id" value="<?= (int) $editStation['id'] ?>"><?php endif; ?>
      <div class="form-group"><label>Station Name</label><input type="text" name="name" required value="<?= e($editStation['name'] ?? '') ?>"></div>
      <div class="form-group"><label>River Name</label><input type="text" name="river_name" value="<?= e($editStation['river_name'] ?? '') ?>"></div>
      <div class="form-group"><label>Location</label><input type="text" name="location" value="<?= e($editStation['location'] ?? '') ?>"></div>
      <div class="grid grid-2">
        <div class="form-group"><label>Latitude</label><input type="text" name="latitude" value="<?= e($editStation['latitude'] ?? '') ?>"></div>
        <div class="form-group"><label>Longitude</label><input type="text" name="longitude" value="<?= e($editStation['longitude'] ?? '') ?>"></div>
      </div>
      <div class="grid grid-2">
        <div class="form-group"><label>Installed Capacity (MW)</label><input type="number" step="0.01" name="installed_capacity_mw" value="<?= e($editStation['installed_capacity_mw'] ?? '') ?>"></div>
        <div class="form-group"><label>Reservoir Max Volume (MCM)</label><input type="number" step="0.01" name="reservoir_max_volume_mcm" value="<?= e($editStation['reservoir_max_volume_mcm'] ?? '') ?>"></div>
      </div>
      <div class="grid grid-2">
        <div class="form-group"><label>Max Safe Water Level (m) — flood threshold</label><input type="number" step="0.01" name="max_safe_water_level_m" value="<?= e($editStation['max_safe_water_level_m'] ?? '') ?>"></div>
        <div class="form-group"><label>Min Operational Level (m)</label><input type="number" step="0.01" name="min_operational_level_m" value="<?= e($editStation['min_operational_level_m'] ?? '') ?>"></div>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <?php foreach (['operational','maintenance','shutdown','decommissioned'] as $s): ?>
          <option value="<?= $s ?>" <?= (($editStation['status'] ?? '')===$s)?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-primary" type="submit"><?= $editStation ? 'Save Changes' : 'Create Station' ?></button>
      <?php if ($editStation): ?><a href="stations.php" class="btn">Cancel</a><?php endif; ?>
    </form>
  </div>

  <div class="panel">
    <div class="panel-title">All Stations</div>
    <table>
      <thead><tr><th>Name</th><th>Capacity</th><th>Turbines</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($stations as $s): ?>
        <tr>
          <td><strong><?= e($s['name']) ?></strong><br><span style="color:var(--text-dim);font-size:11.5px;"><?= e($s['river_name']) ?></span></td>
          <td><?= number_format($s['installed_capacity_mw'], 1) ?> MW</td>
          <td><?= (int) $s['turbine_count'] ?></td>
          <td><?= statusBadge($s['status']) ?></td>
          <td><a class="btn btn-sm" href="stations.php?id=<?= (int) $s['id'] ?>">Edit</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
