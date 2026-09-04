<?php
/**
 * ============================================================================
 * DATABASE CONNECTION (PDO -> PostgreSQL)
 * ============================================================================
 * This file loads connection settings from a .env file (see .env.example)
 * so credentials are never hard-coded or committed to version control.
 *
 * In pgAdmin, this must match the server/database you created:
 *   Host        -> DB_HOST   (e.g. 127.0.0.1)
 *   Port        -> DB_PORT   (default 5432)
 *   Database    -> DB_NAME   (e.g. hydro_monitor)
 *   Username    -> DB_USER   (e.g. postgres)
 *   Password    -> DB_PASS
 * ============================================================================
 */

// ---- Minimal .env loader (no Composer dependency required) ----------------
function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value);
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

loadEnv(__DIR__ . '/../.env');

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'hydro_monitor');
define('DB_USER', getenv('DB_USER') ?: 'postgres');
define('DB_PASS', getenv('DB_PASS') ?: 'postgres');
define('APP_ENV', getenv('APP_ENV') ?: 'development');

/**
 * getDB() - returns a shared PDO connection (singleton).
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            if (APP_ENV === 'development') {
                die('Database connection failed: ' . $e->getMessage());
            }
            error_log('DB connection failed: ' . $e->getMessage());
            die('A system error occurred. Please contact the administrator.');
        }
    }

    return $pdo;
}
