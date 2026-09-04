<?php
/**
 * ============================================================================
 * DEMO DATA SIMULATOR (OPTIONAL — for testing/demo only)
 * ============================================================================
 * Real deployments feed water_level_readings / weather_data /
 * turbine_status_log from actual field sensors and SCADA/PLC systems (see
 * README "Connecting Real Sensors"). Until that hardware integration exists,
 * run this script manually or on a cron to generate realistic sample data
 * so every dashboard, chart, and the alert bot have something to work with.
 *
 * Run manually:   php cron/simulate_data.php
 * Or on cron every 5 minutes:
 *   */5 * * * * /usr/bin/php /full/path/to/hydro-monitor/cron/simulate_data.php
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';

$pdo = getDB();
$stations = $pdo->query('SELECT * FROM stations WHERE status != \'decommissioned\'')->fetchAll(PDO::FETCH_ASSOC);

foreach ($stations as $station) {
    $sid = $station['id'];

    // --- Water level: random walk around 60-80% of flood threshold, occasional spikes ---
    $lastLevel = $pdo->prepare('SELECT water_level_m FROM water_level_readings WHERE station_id=:s ORDER BY reading_time DESC LIMIT 1');
    $lastLevel->execute(['s' => $sid]);
    $prev = $lastLevel->fetchColumn();
    $base = $prev !== false ? (float) $prev : ($station['max_safe_water_level_m'] * 0.7);
    $spikeChance = mt_rand(1, 100) <= 8; // 8% chance of a storm surge event for demo purposes
    $delta = $spikeChance ? mt_rand(150, 400) / 100 : (mt_rand(-40, 40) / 100);
    $newLevel = max(0, $base + $delta);

    $inflow = round(mt_rand(80, 220) / 10, 1);
    $outflow = round($inflow * (mt_rand(85, 105) / 100), 1);
    $volumePct = min(100, round(($newLevel / $station['max_safe_water_level_m']) * 100, 1));

    $stmt = $pdo->prepare('INSERT INTO water_level_readings (station_id, water_level_m, inflow_rate_m3s, outflow_rate_m3s, reservoir_volume_pct, source)
                            VALUES (:s, :wl, :in, :out, :pct, \'sensor\')');
    $stmt->execute(['s' => $sid, 'wl' => round($newLevel, 2), 'in' => $inflow, 'out' => $outflow, 'pct' => $volumePct]);

    // --- Weather ---
    $rainfall = $spikeChance ? mt_rand(400, 900) / 10 : mt_rand(0, 150) / 10;
    $stmt = $pdo->prepare('INSERT INTO weather_data (station_id, rainfall_mm, temperature_c, humidity_pct, wind_speed_kmh, barometric_pressure_hpa, forecast_summary, forecast_rain_probability_pct)
                            VALUES (:s, :r, :t, :h, :w, :bp, :fc, :fp)');
    $stmt->execute([
        's' => $sid, 'r' => round($rainfall, 1), 't' => round(mt_rand(180, 280) / 10, 1),
        'h' => mt_rand(50, 95), 'w' => round(mt_rand(0, 350) / 10, 1),
        'bp' => round(mt_rand(9950, 10250) / 10, 1),
        'fc' => $spikeChance ? 'Heavy rainfall / storm system moving through the catchment area' : 'Partly cloudy, light showers possible',
        'fp' => $spikeChance ? mt_rand(70, 95) : mt_rand(5, 40),
    ]);

    // --- Turbines ---
    $turbines = $pdo->prepare('SELECT * FROM turbines WHERE station_id=:s');
    $turbines->execute(['s' => $sid]);
    foreach ($turbines->fetchAll(PDO::FETCH_ASSOC) as $t) {
        if ($t['status'] !== 'online') continue;

        $loadFactor = mt_rand(60, 98) / 100;
        $output = round($t['capacity_mw'] * $loadFactor, 2);
        $rpm = round($t['rated_rpm'] * (0.97 + mt_rand(0, 6) / 100), 1);
        $pressure = round($t['rated_pressure_bar'] * (0.9 + mt_rand(0, 25) / 100), 2);
        $vibration = round(mt_rand(5, 55) / 10, 2);
        $temp = round(mt_rand(400, 720) / 10, 1);
        $gate = round(40 + $loadFactor * 55, 1);

        $status = 'normal';
        if ($vibration >= 4.5 || $pressure >= 13.5 || $temp >= 75) $status = 'critical';
        elseif ($vibration >= 3 || $pressure >= 12.5) $status = 'warning';

        $stmt = $pdo->prepare('INSERT INTO turbine_status_log (turbine_id, rpm, output_mw, penstock_pressure_bar, bearing_temperature_c, vibration_mm_s, gate_opening_pct, status)
                                VALUES (:t, :rpm, :o, :p, :temp, :v, :g, :st)');
        $stmt->execute(['t' => $t['id'], 'rpm' => $rpm, 'o' => $output, 'p' => $pressure, 'temp' => $temp, 'v' => $vibration, 'g' => $gate, 'st' => $status]);

        $stmt2 = $pdo->prepare('INSERT INTO power_generation (station_id, turbine_id, output_mw, energy_kwh, grid_frequency_hz, grid_voltage_kv, efficiency_pct)
                                 VALUES (:s, :t, :o, :e, :f, :v, :eff)');
        $stmt2->execute([
            's' => $sid, 't' => $t['id'], 'o' => $output, 'e' => round($output * 1000 / 12, 1), // ~5-min interval energy
            'f' => round(49.9 + mt_rand(0, 20) / 100, 2), 'v' => round(mt_rand(1080, 1120) / 10, 1),
            'eff' => round(mt_rand(880, 970) / 10, 1),
        ]);
    }

    echo "Simulated readings inserted for station: {$station['name']}" . PHP_EOL;
}

echo 'Done. Now run cron/alert_bot.php (or click "Run Alert Check Now" on the dashboard) to evaluate alerts.' . PHP_EOL;
