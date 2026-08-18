<?php
$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    http_response_code(403);
    exit('Run this command from the PHP CLI or use the SuperAdmin migration tool.');
}

require_once __DIR__ . '/includes/db.php';
if (!$pdo) {
    http_response_code(500);
    exit($db_error ?? 'Database connection failed.');
}

require_once __DIR__ . '/includes/schema.php';
run_schema_migrations($pdo);

echo "Settings initialized successfully.\n";
