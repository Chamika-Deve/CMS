<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
enforce_page_access('api_status.php');

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? 'Guest';

// 1. Handle SuperAdmin Instant Live Toggle (No Save button needed)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if ($role !== 'SuperAdmin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized: SuperAdmin privileges required.']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $key = trim($input['key'] ?? '');
    $value = trim((string)($input['value'] ?? '0'));

    if (empty($key)) {
        echo json_encode(['success' => false, 'error' => 'Missing setting key.']);
        exit;
    }

    // Allowed instantaneous toggle keys
    $allowed_keys = [
        'maintenance_mode', 'maintenance_message',
        'shop_disabled', 'shop_disabled_message',
        'feature_multibranch', 'feature_repairs', 'feature_custom_pc',
        'feature_serials', 'feature_rma',
        'feature_accounting', 'feature_tracker', 'app_debug'
    ];

    if (!in_array($key, $allowed_keys, true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Invalid setting key.']);
        exit;
    }

    $message_keys = ['maintenance_message', 'shop_disabled_message'];
    if (in_array($key, $message_keys, true)) {
        $value = function_exists('mb_substr') ? mb_substr($value, 0, 1000) : substr($value, 0, 1000);
    } elseif (!in_array($value, ['0', '1'], true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Toggle values must be 0 or 1.']);
        exit;
    }

    if ($pdo) {
        try {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$key, $value]);

            // Log activity
            try {
                $stmt_log = $pdo->prepare("INSERT INTO activity_logs (user_id, action, module, table_name, details) VALUES (?, 'Live Setting Toggle', 'System', 'settings', ?)");
                $stmt_log->execute([$user['id'] ?? 99, "SuperAdmin live toggled $key to $value"]);
            } catch (Exception $ex) {}

            echo json_encode([
                'success' => true,
                'key' => $key,
                'value' => $value,
                'message' => "Setting $key updated instantly."
            ]);
            exit;
        } catch (Throwable $e) {
            error_log('Live setting update failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'The setting could not be updated.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Database offline.']);
        exit;
    }
}

// 2. Handle Live Heartbeat Polling (Status check for logged-in clients)
$is_maint = false;
$maint_msg = 'The system is undergoing scheduled technical maintenance and updates. Please check back shortly.';
$is_shop_locked = false;
$shop_dis_msg = 'This shop account has been deactivated or suspended by system administration. Please contact technical engineering support.';

if ($pdo) {
    try {
        $stmt_s = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('maintenance_mode', 'maintenance_message', 'shop_disabled', 'shop_disabled_message')");
        while ($r = $stmt_s->fetch(PDO::FETCH_ASSOC)) {
            if ($r['setting_key'] === 'maintenance_mode' && ($r['setting_value'] === '1' || $r['setting_value'] === 'true')) $is_maint = true;
            if ($r['setting_key'] === 'maintenance_message' && !empty($r['setting_value'])) $maint_msg = $r['setting_value'];
            if ($r['setting_key'] === 'shop_disabled' && ($r['setting_value'] === '1' || $r['setting_value'] === 'true')) $is_shop_locked = true;
            if ($r['setting_key'] === 'shop_disabled_message' && !empty($r['setting_value'])) $shop_dis_msg = $r['setting_value'];
        }
    } catch (Exception $e) {}
}

$is_locked_for_user = false;
$lock_type = 'none';
$lock_msg = '';

if ($role !== 'SuperAdmin') {
    if ($is_shop_locked) {
        $is_locked_for_user = true;
        $lock_type = 'shop_disabled';
        $lock_msg = $shop_dis_msg;
    } elseif ($is_maint) {
        $is_locked_for_user = true;
        $lock_type = 'maintenance';
        $lock_msg = $maint_msg;
    }
}

echo json_encode([
    'role' => $role,
    'maintenance_mode' => $is_maint,
    'maintenance_message' => $maint_msg,
    'shop_disabled' => $is_shop_locked,
    'shop_disabled_message' => $shop_dis_msg,
    'is_locked' => $is_locked_for_user,
    'lock_type' => $lock_type,
    'lock_message' => $lock_msg
]);
exit;
