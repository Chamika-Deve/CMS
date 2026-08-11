<?php
require 'includes/db.php';
try {
    $pdo->exec('ALTER TABLE products ADD COLUMN min_price DECIMAL(10,2) NULL DEFAULT 0.00 AFTER selling_price, ADD COLUMN max_price DECIMAL(10,2) NULL DEFAULT 0.00 AFTER min_price');
    echo 'OK';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
