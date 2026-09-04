<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/alert_engine.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required'], 405);
}

$summary = runAlertEngine();
logActivity($_SESSION['admin_id'], 'RUN_ALERT_CHECK', json_encode($summary));

jsonResponse($summary);
