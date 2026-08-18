<?php
/**
 * Creates or upgrades the complete CSMS schema.
 *
 * Recommended usage:
 *   DB_USER=root DB_PASS=... php setup_full_system.php
 *
 * When included by the SuperAdmin settings page, the existing PDO connection
 * and authenticated request are reused without printing into the page.
 */

$isDirectExecution = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__;
$isCli = PHP_SAPI === 'cli';

if ($isDirectExecution && !$isCli) {
    http_response_code(403);
    exit('Run this migration from the SuperAdmin settings page or from the PHP CLI.');
}

$writeStatus = static function (string $message) use ($isDirectExecution, $isCli): void {
    if (!$isDirectExecution) {
        return;
    }
    echo $isCli ? $message . PHP_EOL : htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "<br>\n";
};

try {
    if (!isset($pdo) || !$pdo instanceof PDO) {
        $host = getenv('DB_HOST') !== false ? getenv('DB_HOST') : '127.0.0.1';
        $port = getenv('DB_PORT') !== false ? getenv('DB_PORT') : '3306';
        $database = getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'csms_db';
        $username = getenv('DB_USER') !== false ? getenv('DB_USER') : 'root';
        $password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

        $server = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        // Database names cannot be bound as parameters; only allow a safe
        // identifier from the environment before quoting it.
        if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new RuntimeException('DB_NAME may contain only letters, numbers, and underscores.');
        }
        $server->exec("CREATE DATABASE IF NOT EXISTS `{$database}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $writeStatus("Database {$database} is ready.");
    }

    require_once __DIR__ . '/includes/schema.php';
    $changes = run_schema_migrations($pdo);

    // Seed demo staff without overwriting existing passwords or account data.
    $seedPassword = getenv('CSMS_SEED_PASSWORD') !== false
        ? getenv('CSMS_SEED_PASSWORD')
        : 'password';
    if (strlen($seedPassword) < 8) {
        throw new RuntimeException('CSMS_SEED_PASSWORD must contain at least 8 characters.');
    }
    $passwordHash = password_hash($seedPassword, PASSWORD_DEFAULT);
    $users = [
        ['Admin User', 'admin@example.com', 'Admin'],
        ['Manager User', 'manager@example.com', 'Manager'],
        ['Cashier User', 'cashier@example.com', 'Cashier'],
        ['Technician Alex', 'tech@example.com', 'Technician'],
        ['Inventory Staff Dave', 'inventory@example.com', 'Inventory'],
        ['Accountant Sarah', 'accountant@example.com', 'Accountant'],
        ['Software Engineer (SuperAdmin)', 'superadmin@example.com', 'SuperAdmin'],
    ];
    $userStatement = $pdo->prepare(
        'INSERT IGNORE INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, 1)'
    );
    foreach ($users as [$name, $email, $role]) {
        $userStatement->execute([$name, $email, $passwordHash, $role]);
    }

    $pdo->exec("INSERT IGNORE INTO customers (id, name, phone, email, customer_type) VALUES (1, 'Walk-in Customer', '0000000000', 'walkin@shop.local', 'Individual')");

    $writeStatus($changes === []
        ? 'Schema is already up to date.'
        : 'Applied ' . count($changes) . ' schema change(s).');
    $writeStatus('CSMS setup completed successfully.');
} catch (Throwable $exception) {
    if (!$isDirectExecution) {
        throw $exception;
    }

    http_response_code(500);
    $writeStatus('Setup failed: ' . $exception->getMessage());
    if ($isCli) {
        exit(1);
    }
}
