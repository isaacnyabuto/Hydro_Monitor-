<?php
/**
 * General-purpose helper functions shared across the app.
 */

function e(?string $val): string
{
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}

function flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function fetchAll(string $sql, array $params = []): array
{
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetchOne(string $sql, array $params = []): ?array
{
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function execSql(string $sql, array $params = []): bool
{
    $stmt = getDB()->prepare($sql);
    return $stmt->execute($params);
}

/** Returns a Bootstrap-esque color class for a severity level. */
function severityColor(string $severity): string
{
    return match ($severity) {
        'emergency' => 'sev-emergency',
        'critical', 'high' => 'sev-critical',
        'warning', 'moderate' => 'sev-warning',
        'info' => 'sev-info',
        default => 'sev-info',
    };
}

function riskColor(string $level): string
{
    return match ($level) {
        'severe'   => 'sev-emergency',
        'high'     => 'sev-critical',
        'moderate' => 'sev-warning',
        default    => 'sev-ok',
    };
}

function statusBadge(string $status): string
{
    $map = [
        'operational' => 'badge-ok',
        'online'      => 'badge-ok',
        'active'      => 'badge-ok',
        'normal'      => 'badge-ok',
        'standby'     => 'badge-info',
        'monitoring'  => 'badge-info',
        'maintenance' => 'badge-warning',
        'warning'     => 'badge-warning',
        'faulty'      => 'badge-critical',
        'fault'       => 'badge-critical',
        'critical'    => 'badge-critical',
        'offline'     => 'badge-critical',
        'shutdown'    => 'badge-critical',
        'resolved'    => 'badge-ok',
        'open'        => 'badge-critical',
        'acknowledged'=> 'badge-warning',
    ];
    $cls = $map[$status] ?? 'badge-info';
    return '<span class="badge ' . $cls . '">' . e(ucfirst(str_replace('_', ' ', $status))) . '</span>';
}

function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        die('Invalid or expired form submission (CSRF check failed). Please go back and try again.');
    }
}

function jsonResponse($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
