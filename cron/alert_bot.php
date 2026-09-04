<?php
/**
 * ============================================================================
 * ALERT BOT — CRON ENTRY POINT
 * ============================================================================
 * Run this on a schedule (e.g. every minute) so the system continuously
 * evaluates readings and raises alerts/flood events without a human
 * needing to click "Run Alert Check Now" on the dashboard.
 *
 * LINUX / macOS (crontab -e):
 *   * * * * * /usr/bin/php /full/path/to/hydro-monitor/cron/alert_bot.php >> /full/path/to/hydro-monitor/logs/alert_bot.log 2>&1
 *
 * WINDOWS (Task Scheduler):
 *   Program/script: C:\xampp\php\php.exe
 *   Arguments:      C:\path\to\hydro-monitor\cron\alert_bot.php
 *   Trigger:        Repeat every 1 minute
 * ============================================================================
 */

require_once __DIR__ . '/../includes/alert_engine.php';

$start = microtime(true);
$summary = runAlertEngine();
$duration = round(microtime(true) - $start, 3);

$timestamp = date('Y-m-d H:i:s');
echo "[$timestamp] Alert bot run complete in {$duration}s — "
    . "{$summary['stations_checked']} stations checked, "
    . "{$summary['alerts_created']} alerts created, "
    . "{$summary['flood_events']} flood events updated, "
    . "{$summary['risk_rows']} risk assessments logged." . PHP_EOL;
