<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$stationId = (int) ($_GET['station_id'] ?? 0);
$hours = isset($_GET['hours']) ? max(1, min(168, (int) $_GET['hours'])) : 48;

if (!$stationId) {
    jsonResponse(['error' => 'station_id is required'], 400);
}

$station = fetchOne('SELECT * FROM stations WHERE id = :id', ['id' => $stationId]);
if (!$station) {
    jsonResponse(['error' => 'station not found'], 404);
}

$rows = fetchAll(
    "SELECT reading_time, water_level_m FROM water_level_readings
     WHERE station_id = :s AND reading_time > NOW() - (:hrs || ' hours')::interval
     ORDER BY reading_time ASC",
    ['s' => $stationId, 'hrs' => $hours]
);

$labels = [];
$values = [];
foreach ($rows as $r) {
    $labels[] = date('M j H:i', strtotime($r['reading_time']));
    $values[] = round((float) $r['water_level_m'], 2);
}

jsonResponse([
    'labels' => $labels,
    'values' => $values,
    'max_safe_level' => $station['max_safe_water_level_m'],
    'min_operational_level' => $station['min_operational_level_m'],
]);
