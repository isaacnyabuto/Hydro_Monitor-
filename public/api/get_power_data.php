<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$hours = isset($_GET['hours']) ? max(1, min(168, (int) $_GET['hours'])) : 24;

$stations = fetchAll("SELECT id, name FROM stations WHERE status != 'decommissioned' ORDER BY name");

$rows = fetchAll(
    "SELECT station_id, date_trunc('hour', gen_time) AS bucket, SUM(output_mw) AS output_mw
     FROM power_generation
     WHERE gen_time > NOW() - (:hrs || ' hours')::interval
     GROUP BY station_id, bucket
     ORDER BY bucket ASC",
    ['hrs' => $hours]
);

// Build hour buckets for the x-axis
$labels = [];
$now = new DateTime();
for ($i = $hours - 1; $i >= 0; $i--) {
    $t = (clone $now)->modify("-$i hours");
    $labels[] = $t->format('H:i');
}

$palette = ['#00b4d8', '#f5a623', '#2ecc71', '#e63946', '#8b5cf6'];
$datasets = [];
$colorIdx = 0;

foreach ($stations as $st) {
    $seriesByHour = array_fill(0, $hours, null);
    $now2 = new DateTime();
    $bucketMap = [];
    for ($i = $hours - 1; $i >= 0; $i--) {
        $t = (clone $now2)->modify("-$i hours");
        $bucketMap[$t->format('Y-m-d H:00:00')] = $hours - 1 - $i;
    }
    foreach ($rows as $r) {
        if ((int) $r['station_id'] !== (int) $st['id']) continue;
        $key = date('Y-m-d H:00:00', strtotime($r['bucket']));
        if (isset($bucketMap[$key])) {
            $seriesByHour[$bucketMap[$key]] = round((float) $r['output_mw'], 2);
        }
    }
    $datasets[] = [
        'label' => $st['name'],
        'data' => $seriesByHour,
        'borderColor' => $palette[$colorIdx % count($palette)],
        'backgroundColor' => $palette[$colorIdx % count($palette)],
        'tension' => 0.35,
        'spanGaps' => true,
    ];
    $colorIdx++;
}

jsonResponse(['labels' => $labels, 'datasets' => $datasets]);
