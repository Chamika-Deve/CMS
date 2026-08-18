<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (($_SESSION['user']['role'] ?? '') !== 'SuperAdmin') {
    abort_request(403, 'Only SuperAdmin may use this diagnostic endpoint.', true);
}
if (!$pdo) {
    abort_request(503, $db_error ?? 'Database connection failed.', true);
}

header('Content-Type: application/json; charset=utf-8');
$stmt = $pdo->query('SELECT id, name, parent_id FROM categories ORDER BY name');
echo json_encode(['success' => true, 'categories' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
