<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } elseif (attemptLogin($username, $password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — HGMS</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo"><div class="logo-dot"></div></div>
    <h1>Hydroelectric Monitoring System</h1>
    <div class="tag">Secure Administrator Access</div>

    <?php if ($error): ?><div class="alert-banner error"><?= e($error) ?></div><?php endif; ?>
    <?php if (isset($_GET['timeout'])): ?><div class="alert-banner warning">Your session expired due to inactivity. Please log in again.</div><?php endif; ?>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" required autofocus>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;">Sign In</button>
    </form>
    <p style="text-align:center;color:var(--text-dim);font-size:11.5px;margin-top:18px;">
    
    </p>
  </div>
</div>
</body>
</html>
