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

if (!function_exists('page_role_map')) {
    function page_role_map(): array
    {
        return [
            'dashboard.php' => [],
            'pos.php' => ['Admin', 'Manager', 'Cashier'],
            'print_bill.php' => ['Admin', 'Manager', 'Cashier'],
            'build_pc.php' => ['Admin', 'Manager', 'Cashier'],
            'print_quote.php' => ['Admin', 'Manager', 'Cashier'],
            'repairs.php' => ['Admin', 'Manager', 'Technician', 'Cashier'],
            'customers.php' => ['Admin', 'Manager', 'Cashier', 'Accountant'],
            'products.php' => ['Admin', 'Manager', 'Inventory', 'Cashier', 'Technician', 'Accountant'],
            'product_add.php' => ['Admin', 'Manager', 'Inventory'],
            'product_edit.php' => ['Admin', 'Manager', 'Inventory'],
            'product_serials.php' => ['Admin', 'Manager', 'Inventory'],
            'purchases.php' => ['Admin', 'Manager', 'Inventory'],
            'suppliers.php' => ['Admin', 'Manager', 'Inventory', 'Accountant'],
            'warranty.php' => ['Admin', 'Manager', 'Technician', 'Inventory', 'Cashier'],
            'accounting.php' => ['Admin', 'Manager', 'Accountant', 'Cashier'],
            'reports.php' => ['Admin', 'Manager', 'Accountant', 'Technician', 'Inventory'],
            'users.php' => ['Admin', 'Manager', 'SuperAdmin'],
            'audit_log.php' => ['Admin', 'Manager', 'SuperAdmin'],
            'shop_settings.php' => ['Admin', 'SuperAdmin'],
            'settings.php' => ['SuperAdmin'],
            'api_status.php' => [],
        ];
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
                // Keep the existing session usable during a temporary database
                // outage; page-level database handling will show the outage.
            }
        }

        $roles = page_role_map()[$page] ?? [];
        $role = $_SESSION['user']['role'] ?? '';
        if ($roles !== [] && !in_array($role, $roles, true)) {
            abort_request(403, 'Your account does not have permission to access this module.', $isJson);
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !is_valid_csrf_token()) {
            abort_request(419, 'Your security token is missing or expired. Refresh the page and try again.', $isJson);
        }

        csrf_token();
    }
}
