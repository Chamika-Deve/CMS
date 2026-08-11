<?php
$host = '127.0.0.1';
$db   = 'csms_db';
$user = 'root';
$pass = ''; // Default XAMPP/Laragon password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // We suppress the error with @ in case the DB doesn't exist yet so we can show a friendly message
    @$pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // For demo purposes, we will set $pdo to null if it fails so the UI can still render with fake data if needed
    // In production, you would die() or throw here.
    $pdo = null;
    $db_error = $e->getMessage();
}
?>
