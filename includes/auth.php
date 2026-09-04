<?php
/**
 * Authentication & session guard for the admin system.
 */
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

/** Attempt to log an admin in. Returns true/false. */
function attemptLogin(string $username, string $password): bool
{
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = :u AND is_active = TRUE');
    $stmt->execute(['u' => $username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['username']   = $admin['username'];
        $_SESSION['full_name']  = $admin['full_name'];
        $_SESSION['role']       = $admin['role'];
        $_SESSION['last_activity'] = time();

        $upd = $pdo->prepare('UPDATE admins SET last_login = NOW() WHERE id = :id');
        $upd->execute(['id' => $admin['id']]);

        logActivity($admin['id'], 'LOGIN', 'Admin logged in successfully');
        return true;
    }

    logActivity(null, 'LOGIN_FAILED', "Failed login attempt for username: $username");
    return false;
}

/** Force-require an authenticated session on protected pages. */
function requireLogin(): void
{
    $timeoutSeconds = 30 * 60; // 30-minute idle timeout

    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeoutSeconds) {
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

/** Restrict a page to specific roles, e.g. requireRole(['super_admin','admin']) */
function requireRole(array $roles): void
{
    requireLogin();
    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;color:#f66">403 — You do not have permission to access this page.</div>');
    }
}

function logActivity(?int $adminId, string $action, string $details = ''): void
{
    $pdo = getDB();
    $stmt = $pdo->prepare(
        'INSERT INTO activity_logs (admin_id, action, details, ip_address) VALUES (:a, :act, :d, :ip)'
    );
    $stmt->execute([
        'a'   => $adminId,
        'act' => $action,
        'd'   => $details,
        'ip'  => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
    ]);
}

function currentAdmin(): array
{
    return [
        'id'        => $_SESSION['admin_id'] ?? null,
        'username'  => $_SESSION['username'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null,
        'role'      => $_SESSION['role'] ?? null,
    ];
}

function logoutAdmin(): void
{
    if (!empty($_SESSION['admin_id'])) {
        logActivity($_SESSION['admin_id'], 'LOGOUT', 'Admin logged out');
    }
    $_SESSION = [];
    session_unset();
    session_destroy();
}
