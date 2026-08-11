<?php
require 'includes/db.php';
$stmt = $pdo->query('SELECT * FROM categories');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
