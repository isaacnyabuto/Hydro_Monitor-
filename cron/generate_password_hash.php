<?php
/**
 * Utility: generate a bcrypt password hash to insert/update an admin account
 * directly in pgAdmin, without needing the users.php UI.
 *
 * Usage:
 *   php cron/generate_password_hash.php "MyNewPassword123"
 */
$password = $argv[1] ?? null;

if (!$password) {
    echo "Usage: php generate_password_hash.php \"YourPassword\"" . PHP_EOL;
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT);
echo "Password:  $password" . PHP_EOL;
echo "Hash:      $hash" . PHP_EOL;
echo PHP_EOL . "Example pgAdmin SQL to apply it to the seed admin account:" . PHP_EOL;
echo "UPDATE admins SET password_hash = '$hash' WHERE username = 'admin';" . PHP_EOL;
