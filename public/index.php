<?php
require_once __DIR__ . '/../includes/auth.php';
header('Location: ' . (!empty($_SESSION['admin_id']) ? 'dashboard.php' : 'login.php'));
exit;
