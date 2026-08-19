<?php
/**
 * Shared authentication, authorization, and CSRF helpers.
 *
 * Include this file before processing any authenticated request. Calling
 * enforce_page_access() both checks the current session/role and validates
 * CSRF protection for state-changing requests.
 */

if (!function_exists('start_secure_session')) {
    function start_secure_session(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

start_secure_session();

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('is_valid_csrf_token')) {
    function is_valid_csrf_token(?string $token = null): bool
    {
        if ($token === null) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf_token'] ?? '');
        }

        return is_string($token)
            && $token !== ''
            && hash_equals(csrf_token(), $token);
    }
}

if (!function_exists('safe_error_message')) {
    function safe_error_message(Throwable $exception, string $fallback = 'The database operation failed. Please retry.'): string
    {
        if ($exception instanceof PDOException) {
            error_log($exception->getMessage());
            $debug = filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL);
            return $debug ? $exception->getMessage() : $fallback;
        }

        return $exception->getMessage();
    }
}

if (!function_exists('request_expects_json')) {
    function request_expects_json(): bool
    {
        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');

        return isset($_POST['ajax'])
            || (isset($_POST['action']) && in_array(
                basename($_SERVER['SCRIPT_NAME'] ?? ''),
                ['pos.php', 'build_pc.php', 'product_serials.php', 'api_status.php'],
                true
            ))
            || str_contains($accept, 'application/json')
            || str_contains($contentType, 'application/json');
    }
}

if (!function_exists('abort_request')) {
    function abort_request(int $status, string $message, bool $json = false): void
    {
        http_response_code($status);

        if ($json || request_expects_json()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $message, 'error' => $message]);
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>Access denied</title></head>'
            . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;font-family:system-ui,sans-serif;background:#f8fafc;color:#0f172a">'
            . '<main style="max-width:34rem;padding:2rem;text-align:center"><h1 style="margin-bottom:.5rem">Request denied</h1>'
            . '<p style="color:#64748b">' . $safeMessage . '</p>'
            . '<a href="dashboard.php" style="color:#059669;font-weight:700">Return to dashboard</a></main></body></html>';
        exit;
    }
}

if (!function_exists('is_flag_enabled')) {
    function is_flag_enabled(string $key, int $default = 1): bool
    {
        global $pdo;
        static $flags_cache = null;

        if ($flags_cache === null) {
            $flags_cache = [];
            if (isset($pdo) && $pdo instanceof PDO) {
                try {
                    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'feature_%' OR setting_key IN ('maintenance_mode', 'shop_disabled')");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $flags_cache[$row['setting_key']] = ($row['setting_value'] === '1' || $row['setting_value'] === 'true');
                    }
                } catch (Throwable $e) {}
            }
        }

        if (isset($flags_cache[$key])) {
            return (bool)$flags_cache[$key];
        }

        return $default === 1;
    }
}

if (!function_exists('is_module_feature_enabled')) {
    function is_module_feature_enabled(string $page): bool
    {
        $page_flag_map = [
            'repairs.php'         => 'feature_repairs',
            'build_pc.php'        => 'feature_custom_pc',
            'warranty.php'        => 'feature_rma',
            'accounting.php'      => 'feature_accounting',
            'product_serials.php' => 'feature_serials',
            'track.php'           => 'feature_tracker',
        ];

        $flag = $page_flag_map[$page] ?? null;
        if ($flag && !is_flag_enabled($flag, 1)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('get_page_access_level')) {
    function get_page_access_level(string $page, string $role): string
    {
        if ($role === 'SuperAdmin') {
            return 'F';
        }

        $page_module_map = [
            'dashboard.php'       => 'Dashboard / Business KPIs',
            'reports.php'         => 'Dashboard / Business KPIs',
            'pos.php'             => 'POS / Sales & Barcode Scanning',
            'print_bill.php'      => 'POS / Sales & Barcode Scanning',
            'build_pc.php'        => 'POS / Sales & Barcode Scanning',
            'print_quote.php'     => 'POS / Sales & Barcode Scanning',
            'customers.php'       => 'POS / Sales & Barcode Scanning',
            'products.php'        => 'Products / Inventory & Serials',
            'product_add.php'     => 'Products / Inventory & Serials',
            'product_edit.php'    => 'Products / Inventory & Serials',
            'product_serials.php' => 'Products / Inventory & Serials',
            'warranty.php'        => 'Products / Inventory & Serials',
            'repairs.php'         => 'Repair & Service Jobs Workbench',
            'purchases.php'       => 'Suppliers & Purchasing (POs/GRN)',
            'suppliers.php'       => 'Suppliers & Purchasing (POs/GRN)',
            'accounting.php'      => 'Accounting, Expenses & Cash Drawer',
        ];

        $module = $page_module_map[$page] ?? null;
        if (!$module) {
            return 'F';
        }

        global $pdo;
        static $permissions_cache = [];
        if (!isset($permissions_cache[$role]) && isset($pdo) && $pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("SELECT module, access FROM role_permissions WHERE role = ?");
                $stmt->execute([$role]);
                $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                $permissions_cache[$role] = $rows ?: [];
            } catch (Throwable $e) {
                $permissions_cache[$role] = [];
            }
        }

        if (isset($permissions_cache[$role][$module])) {
            return $permissions_cache[$role][$module];
        }

        // Fallbacks matching initial seed if DB table hasn't loaded yet
        $defaults = [
            'Dashboard / Business KPIs'          => ['Admin'=>'F', 'Manager'=>'F', 'Cashier'=>'V', 'Inventory'=>'V', 'Accountant'=>'V'],
            'POS / Sales & Barcode Scanning'     => ['Admin'=>'F', 'Manager'=>'F', 'Cashier'=>'E', 'Accountant'=>'V'],
            'Products / Inventory & Serials'     => ['Admin'=>'F', 'Manager'=>'F', 'Inventory'=>'F', 'Cashier'=>'V', 'Technician'=>'V', 'Accountant'=>'V'],
            'Repair & Service Jobs Workbench'    => ['Admin'=>'F', 'Manager'=>'F', 'Technician'=>'E', 'Cashier'=>'V', 'Accountant'=>'V'],
            'Suppliers & Purchasing (POs/GRN)'   => ['Admin'=>'F', 'Inventory'=>'F', 'Manager'=>'E', 'Accountant'=>'V'],
            'Accounting, Expenses & Cash Drawer' => ['Admin'=>'F', 'Accountant'=>'F', 'Manager'=>'V', 'Cashier'=>'E'],
        ];

        return $defaults[$module][$role] ?? '-';
    }
}

if (!function_exists('can_access_page')) {
    function can_access_page(string $page): bool
    {
        if (!is_module_feature_enabled($page)) {
            return false;
        }

        $role = $_SESSION['user']['role'] ?? '';
        if ($role === '') {
            return false;
        }
        // users.php tab=matrix is accessible to all logged in users for viewing matrix
        if ($page === 'users.php') {
            return true;
        }
        return get_page_access_level($page, $role) !== '-';
    }
}

if (!function_exists('can_write_page')) {
    function can_write_page(string $page): bool
    {
        if (!is_module_feature_enabled($page)) {
            return false;
        }

        $role = $_SESSION['user']['role'] ?? '';
        if ($role === '' || empty($_SESSION['user'])) {
            return false;
        }
        if ($role === 'SuperAdmin') {
            return true;
        }
        $level = get_page_access_level($page, $role);
        return $level === 'F' || $level === 'E';
    }
}


if (!function_exists('enforce_page_access')) {
    function enforce_page_access(?string $page = null): void
    {
        $page = $page ?? basename($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
        $isJson = request_expects_json();

        if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) {
            if ($isJson) {
                abort_request(401, 'Authentication required.', true);
            }

            $loginPath = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/pages/')
                ? '../index.php'
                : 'index.php';
            header('Location: ' . $loginPath);
            exit;
        }

        // Refresh account status and role on every request so disabled users and
        // permission changes take effect without waiting for the session to end.
        global $pdo;
        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                $accountStatement = $pdo->prepare('SELECT id, name, email, role, status FROM users WHERE id = ? LIMIT 1');
                $accountStatement->execute([(int)($_SESSION['user']['id'] ?? 0)]);
                $account = $accountStatement->fetch(PDO::FETCH_ASSOC);
                if (!$account || (int)$account['status'] !== 1) {
                    unset($_SESSION['user'], $_SESSION['superadmin_impersonator']);
                    if ($isJson) {
                        abort_request(401, 'Your account is no longer active.', true);
                    }
                    header('Location: ../index.php');
                    exit;
                }

                $_SESSION['user'] = [
                    'id' => (int)$account['id'],
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'role' => $account['role'],
                ];

                $versionStatement = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'global_session_version' LIMIT 1");
                $globalVersion = $versionStatement->fetchColumn();
                if (is_string($globalVersion) && $globalVersion !== '') {
                    $sessionVersion = $_SESSION['user_session_version'] ?? null;
                    if ($sessionVersion === null || !hash_equals($globalVersion, (string)$sessionVersion)) {
                        unset($_SESSION['user'], $_SESSION['superadmin_impersonator'], $_SESSION['user_session_version']);
                        if ($isJson) {
                            abort_request(401, 'Your session was ended by an administrator.', true);
                        }
                        header('Location: ../index.php');
                        exit;
                    }
                }
            } catch (Throwable $ignored) {
                // Keep the existing session usable during a temporary database outage
            }
        }

        if (!is_module_feature_enabled($page)) {
            abort_request(403, 'This feature module is currently disabled for this shop by the administrator.', $isJson);
        }

        $role = $_SESSION['user']['role'] ?? '';
        $accessLevel = get_page_access_level($page, $role);

        // Allow users.php for viewing matrix
        if ($page === 'users.php' && ($role === 'Admin' || $role === 'Manager' || $role === 'SuperAdmin' || ($_GET['tab'] ?? '') === 'matrix')) {
            // OK
        } elseif ($accessLevel === '-') {
            abort_request(403, 'Your account does not have permission to access this module.', $isJson);
        }

        // Check POST requests on View-Only (V) access
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $accessLevel === 'V') {
            $isMatrixSave = ($page === 'users.php' && ($_POST['action'] ?? '') === 'save_matrix' && $role === 'SuperAdmin');
            if (!$isMatrixSave) {
                abort_request(403, 'Your account has View-Only permission for this module. You cannot save or perform modifications.', $isJson);
            }
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !is_valid_csrf_token()) {
            abort_request(419, 'Your security token is missing or expired. Refresh the page and try again.', $isJson);
        }

        csrf_token();
    }
}
