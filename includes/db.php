<?php
/**
 * PDO database connection.
 *
 * Configure production credentials with environment variables instead of
 * committing them to source control. The defaults match a standard local
 * XAMPP/Laragon installation.
 */
$host = getenv('DB_HOST') !== false ? getenv('DB_HOST') : '127.0.0.1';
$port = getenv('DB_PORT') !== false ? getenv('DB_PORT') : '3306';
$db = getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'csms_db';
$user = getenv('DB_USER') !== false ? getenv('DB_USER') : 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_TIMEOUT => 5,
];

$pdo = null;
$db_error = null;

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Apply the configured shop timezone as early as possible so invoice
    // numbers, audit timestamps, and date filters are consistent everywhere,
    // including AJAX endpoints and the public repair tracker.
    try {
        $tz = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'system_timezone' LIMIT 1")->fetchColumn();
        if (is_string($tz) && $tz !== '' && @date_default_timezone_set($tz) === false) {
            error_log('Ignoring invalid system_timezone setting: ' . $tz);
        }
    } catch (Throwable $ignored) {
    }
} catch (PDOException $exception) {
    // Keep pages renderable, but never expose credentials/server details unless
    // debug mode was explicitly enabled in the environment.
    $debug = filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL);
    $db_error = $debug
        ? $exception->getMessage()
        : 'Unable to connect to the database. Verify that MySQL is running and the DB_* settings are correct.';
}
