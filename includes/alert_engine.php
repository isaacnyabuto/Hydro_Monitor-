<?php
/**
 * ============================================================================
 * ALERT ENGINE ("Alert Bot") + RISK SCORING
 * ============================================================================
 * This is the brain of the monitoring system. It is called either:
 *   1. Automatically every minute by cron/alert_bot.php, OR
 *   2. On-demand from public/api/check_alerts.php (dashboard "Run Check Now")
 *
 * For each station it:
 *   - reads the latest water level, weather, and turbine telemetry
 *   - evaluates every active alert_rule against those latest readings
 *   - opens a new alert if a threshold is breached and no identical OPEN
 *     alert already exists (prevents duplicate spam)
 *   - auto-creates/updates a flood_event when water level crosses the
 *     station's flood threshold
 *   - computes a rolling composite risk score per station/risk_type
 * ============================================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function runAlertEngine(): array
{
    $pdo = getDB();
    $summary = ['alerts_created' => 0, 'flood_events' => 0, 'risk_rows' => 0, 'stations_checked' => 0];

    $stations = fetchAll('SELECT * FROM stations WHERE status != \'decommissioned\'');
    $rules = fetchAll('SELECT * FROM alert_rules WHERE is_active = TRUE');

    foreach ($stations as $station) {
        $summary['stations_checked']++;
        $sid = $station['id'];

        $latestWater   = fetchOne('SELECT * FROM water_level_readings WHERE station_id = :s ORDER BY reading_time DESC LIMIT 1', ['s' => $sid]);
        $latestWeather = fetchOne('SELECT * FROM weather_data WHERE station_id = :s ORDER BY reading_time DESC LIMIT 1', ['s' => $sid]);
        $turbineLogs   = fetchAll(
            'SELECT tsl.* FROM turbine_status_log tsl
             JOIN turbines t ON t.id = tsl.turbine_id
             WHERE t.station_id = :s AND tsl.log_time > NOW() - INTERVAL \'10 minutes\'
             ORDER BY tsl.log_time DESC',
            ['s' => $sid]
        );

        // Build a flat "readings" map of parameter => value to test against rules
        $readings = [];
        if ($latestWater) {
            $readings['water_level_m'] = (float) $latestWater['water_level_m'];
        }
        if ($latestWeather) {
            $readings['rainfall_mm'] = (float) $latestWeather['rainfall_mm'];
        }
        foreach ($turbineLogs as $log) {
            // take the worst (max) value seen across turbines in the window
            foreach (['penstock_pressure_bar', 'vibration_mm_s', 'bearing_temperature_c'] as $param) {
                $val = (float) $log[$param];
                if (!isset($readings[$param]) || $val > $readings[$param]) {
                    $readings[$param] = $val;
                }
            }
        }

        // ---- Evaluate rules -------------------------------------------------
        foreach ($rules as $rule) {
            $param = $rule['parameter'];
            if (!array_key_exists($param, $readings)) {
                continue;
            }
            $value = $readings[$param];
            $breached = compareValue($value, $rule['condition_operator'], (float) $rule['threshold_value']);

            if ($breached) {
                $exists = fetchOne(
                    "SELECT id FROM alerts WHERE station_id = :s AND rule_id = :r AND status = 'open'",
                    ['s' => $sid, 'r' => $rule['id']]
                );
                if (!$exists) {
                    $alertType = inferAlertType($param);
                    $message = sprintf(
                        '%s at %s: %s is %s%.2f (threshold %s %.2f)',
                        $rule['name'], $station['name'], $param,
                        $rule['condition_operator'], $value,
                        $rule['condition_operator'], $rule['threshold_value']
                    );
                    execSql(
                        'INSERT INTO alerts (station_id, rule_id, alert_type, severity, message)
                         VALUES (:s, :r, :t, :sev, :m)',
                        ['s' => $sid, 'r' => $rule['id'], 't' => $alertType, 'sev' => $rule['severity'], 'm' => $message]
                    );
                    $summary['alerts_created']++;
                }
            }
        }

        // ---- Flood event detection / lifecycle -------------------------------
        if ($latestWater && $station['max_safe_water_level_m']) {
            $level = (float) $latestWater['water_level_m'];
            $threshold = (float) $station['max_safe_water_level_m'];
            $activeFlood = fetchOne(
                "SELECT * FROM flood_events WHERE station_id = :s AND status = 'active' ORDER BY event_start DESC LIMIT 1",
                ['s' => $sid]
            );

            if ($level >= $threshold) {
                $severity = $level >= $threshold * 1.05 ? 'emergency' : ($level >= $threshold * 1.02 ? 'critical' : 'warning');
                if (!$activeFlood) {
                    execSql(
                        'INSERT INTO flood_events (station_id, severity, peak_water_level_m, description, status)
                         VALUES (:s, :sev, :peak, :d, \'active\')',
                        ['s' => $sid, 'sev' => $severity, 'peak' => $level,
                         'd' => "Water level ($level m) reached/exceeded safe threshold ($threshold m)."]
                    );
                    $summary['flood_events']++;
                } else if ($level > (float) $activeFlood['peak_water_level_m']) {
                    execSql(
                        'UPDATE flood_events SET peak_water_level_m = :peak, severity = :sev WHERE id = :id',
                        ['peak' => $level, 'sev' => $severity, 'id' => $activeFlood['id']]
                    );
                }
            } elseif ($activeFlood && $level < $threshold * 0.97) {
                // Water receded comfortably below threshold -> resolve
                execSql(
                    "UPDATE flood_events SET status = 'resolved', event_end = NOW() WHERE id = :id",
                    ['id' => $activeFlood['id']]
                );
            }
        }

        // ---- Composite risk scoring ------------------------------------------
        $riskRows = computeRiskScores($station, $latestWater, $latestWeather, $turbineLogs);
        foreach ($riskRows as $r) {
            execSql(
                'INSERT INTO risk_assessments (station_id, risk_type, risk_level, score, notes, assessed_by)
                 VALUES (:s, :t, :l, :sc, :n, \'system\')',
                ['s' => $sid, 't' => $r['type'], 'l' => $r['level'], 'sc' => $r['score'], 'n' => $r['notes']]
            );
            $summary['risk_rows']++;
        }
    }

    return $summary;
}

function compareValue(float $value, string $op, float $threshold): bool
{
    return match ($op) {
        '>'  => $value > $threshold,
        '>=' => $value >= $threshold,
        '<'  => $value < $threshold,
        '<=' => $value <= $threshold,
        '='  => abs($value - $threshold) < 0.001,
        default => false,
    };
}

function inferAlertType(string $param): string
{
    return match (true) {
        str_contains($param, 'water_level') => 'water_level',
        str_contains($param, 'rainfall')    => 'weather',
        str_contains($param, 'pressure')    => 'pressure',
        str_contains($param, 'vibration'), str_contains($param, 'temperature') => 'turbine',
        default => 'system',
    };
}

/**
 * Computes 0-100 risk scores for flood, mechanical/pressure and weather risk
 * using simple, transparent weighted-threshold logic (documented in README).
 */
function computeRiskScores(array $station, ?array $water, ?array $weather, array $turbineLogs): array
{
    $rows = [];

    // --- Flood risk ---
    if ($water && $station['max_safe_water_level_m']) {
        $ratio = ((float) $water['water_level_m']) / (float) $station['max_safe_water_level_m'];
        $score = min(100, max(0, $ratio * 100));
        $rows[] = [
            'type' => 'flood',
            'level' => $score >= 100 ? 'severe' : ($score >= 90 ? 'high' : ($score >= 70 ? 'moderate' : 'low')),
            'score' => round($score, 2),
            'notes' => 'Based on current level vs. station flood threshold.',
        ];
    }

    // --- Mechanical / pressure risk (worst turbine in window) ---
    if (!empty($turbineLogs)) {
        $maxPressureRatio = 0;
        $maxVibration = 0;
        foreach ($turbineLogs as $log) {
            $maxVibration = max($maxVibration, (float) $log['vibration_mm_s']);
        }
        $score = min(100, ($maxVibration / 6.0) * 100); // 6mm/s ~ severe vibration ceiling
        $rows[] = [
            'type' => 'mechanical',
            'level' => $score >= 90 ? 'severe' : ($score >= 65 ? 'high' : ($score >= 35 ? 'moderate' : 'low')),
            'score' => round($score, 2),
            'notes' => 'Derived from peak turbine vibration readings in the last 10 minutes.',
        ];
    }

    // --- Weather risk ---
    if ($weather) {
        $rainScore = min(100, ((float) $weather['rainfall_mm'] / 60.0) * 100);
        $rows[] = [
            'type' => 'weather',
            'level' => $rainScore >= 90 ? 'severe' : ($rainScore >= 65 ? 'high' : ($rainScore >= 35 ? 'moderate' : 'low')),
            'score' => round($rainScore, 2),
            'notes' => 'Derived from rainfall intensity relative to 60mm/hr severe-storm reference.',
        ];
    }

    return $rows;
}
