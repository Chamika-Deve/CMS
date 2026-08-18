<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (!empty($_SESSION['user'])) {
    header('Location: pages/dashboard.php');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!is_valid_csrf_token()) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!$pdo) {
        $error = 'The database is unavailable. Check the database service and connection settings.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter a valid email address and password.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $error = 'Invalid email or password.';
            } elseif ((int)$user['status'] !== 1) {
                $error = 'Your account is disabled. Contact the system administrator.';
            } else {
                $isSuperAdmin = $user['role'] === 'SuperAdmin';

                // Non-SuperAdmins must respect maintenance and shop lockdowns.
                if (!$isSuperAdmin) {
                    $isMaintenance = false;
                    $maintenanceMessage = 'System maintenance is in progress. Staff cannot sign in right now.';
                    $isShopDisabled = false;
                    $shopDisabledMessage = 'This shop account has been suspended. Contact technical support.';

                    try {
                        $lockStatement = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('maintenance_mode', 'maintenance_message', 'shop_disabled', 'shop_disabled_message')");
                        while ($setting = $lockStatement->fetch(PDO::FETCH_ASSOC)) {
                            if ($setting['setting_key'] === 'maintenance_mode') {
                                $isMaintenance = in_array(strtolower($setting['setting_value']), ['1', 'true'], true);
                            } elseif ($setting['setting_key'] === 'maintenance_message' && $setting['setting_value'] !== '') {
                                $maintenanceMessage = $setting['setting_value'];
                            } elseif ($setting['setting_key'] === 'shop_disabled') {
                                $isShopDisabled = in_array(strtolower($setting['setting_value']), ['1', 'true'], true);
                            } elseif ($setting['setting_key'] === 'shop_disabled_message' && $setting['setting_value'] !== '') {
                                $shopDisabledMessage = $setting['setting_value'];
                            }
                        }
                    } catch (Throwable $ignored) {
                        // Older installations may not have the optional settings yet.
                    }

                    if ($isShopDisabled) {
                        $error = $shopDisabledMessage;
                    } elseif ($isMaintenance) {
                        $error = $maintenanceMessage;
                    }
                }

                if ($error === '') {
                    $sessionVersion = '';
                    try {
                        $sessionVersionStatement = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'global_session_version' LIMIT 1");
                        $sessionVersion = (string)($sessionVersionStatement->fetchColumn() ?: '');
                    } catch (Throwable $ignored) {
                    }

                    session_regenerate_id(true);
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $_SESSION['user_session_version'] = $sessionVersion;
                    $_SESSION['user'] = [
                        'id' => (int)$user['id'],
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                    ];
                    header('Location: pages/dashboard.php');
                    exit;
                }
            }
        } catch (Throwable $exception) {
            $error = 'Sign-in could not be completed. Check the database schema and server logs.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechShop - Computer Shop & Repair Management POS</title>
    <!-- Tailwind CSS Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'Inter', 'sans-serif'],
                    },
                    colors: {
                        emerald: {
                            500: '#10B981',
                            600: '#059669',
                            700: '#047857',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 50%, #064E3B 100%);
            min-height: 100vh;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.07);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
    </style>
</head>
<body class="flex items-center justify-center p-4 min-h-screen text-slate-100 antialiased">
    
    <div class="w-full max-w-lg z-10 py-8">
        
        <!-- Brand Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-3xl bg-emerald-500 text-white text-2xl font-black shadow-lg shadow-emerald-500/30 mb-3">
                T
            </div>
            <h1 class="text-3xl font-extrabold text-white tracking-tight">TechShop</h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-1">Computer Shop POS, Inventory & Repair Management</p>
        </div>

        <!-- Login Card -->
        <div class="glass-card rounded-3xl p-7 sm:p-9">
            <h2 class="text-xl font-bold text-white mb-6 text-center">Staff Sign In</h2>
            
            <?php if ($error): ?>
                <div class="bg-red-500/20 border border-red-500/40 text-red-200 px-4 py-3 rounded-2xl mb-6 text-xs sm:text-sm flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="index.php" class="space-y-4">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5" for="email">Staff Email / Username</label>
                    <div class="relative">
                        <i class="fa-regular fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="email" id="email" name="email" required 
                               class="w-full pl-11 pr-4 py-3 bg-white/10 border border-white/10 rounded-2xl text-sm font-medium text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition-all" 
                               placeholder="user@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" autocomplete="username">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5" for="password">Password</label>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="password" id="password" name="password" required 
                               class="w-full pl-11 pr-4 py-3 bg-white/10 border border-white/10 rounded-2xl text-sm font-medium text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition-all" 
                               placeholder="••••••••" autocomplete="current-password">
                    </div>
                </div>

                <button type="submit" class="w-full py-3.5 px-4 rounded-2xl font-bold text-sm text-slate-950 bg-emerald-400 hover:bg-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all duration-200 shadow-lg shadow-emerald-500/25 mt-4 flex items-center justify-center gap-2">
                    <span>Sign In to System</span>
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </button>
            </form>

            <!-- 1-Click Role Logins (6 Store Roles) -->
            <div class="mt-8 pt-6 border-t border-white/10">
                <p class="text-xs font-bold text-slate-400 text-center uppercase tracking-wider mb-3">1-Click Store Role Switcher</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs">
                    <button type="button" onclick="setRole('admin@example.com')" class="p-2.5 bg-white/5 hover:bg-white/15 border border-white/10 rounded-xl text-left transition-all group">
                        <span class="block font-bold text-white group-hover:text-emerald-400">Admin</span>
                        <span class="text-[10px] text-slate-400 block">Owner / Control</span>
                    </button>
                    <button type="button" onclick="setRole('manager@example.com')" class="p-2.5 bg-white/5 hover:bg-white/15 border border-white/10 rounded-xl text-left transition-all group">
                        <span class="block font-bold text-white group-hover:text-emerald-400">Manager</span>
                        <span class="text-[10px] text-slate-400 block">Store Operations</span>
                    </button>
                    <button type="button" onclick="setRole('cashier@example.com')" class="p-2.5 bg-white/5 hover:bg-white/15 border border-white/10 rounded-xl text-left transition-all group">
                        <span class="block font-bold text-white group-hover:text-emerald-400">Cashier</span>
                        <span class="text-[10px] text-slate-400 block">Fast POS & Intake</span>
                    </button>
                    <button type="button" onclick="setRole('tech@example.com')" class="p-2.5 bg-white/5 hover:bg-white/15 border border-white/10 rounded-xl text-left transition-all group">
                        <span class="block font-bold text-white group-hover:text-emerald-400">Technician</span>
                        <span class="text-[10px] text-slate-400 block">Repair Workbench</span>
                    </button>
                    <button type="button" onclick="setRole('inventory@example.com')" class="p-2.5 bg-white/5 hover:bg-white/15 border border-white/10 rounded-xl text-left transition-all group">
                        <span class="block font-bold text-white group-hover:text-emerald-400">Inventory</span>
                        <span class="text-[10px] text-slate-400 block">Stock & PO/GRN</span>
                    </button>
                    <button type="button" onclick="setRole('accountant@example.com')" class="p-2.5 bg-white/5 hover:bg-white/15 border border-white/10 rounded-xl text-left transition-all group">
                        <span class="block font-bold text-white group-hover:text-emerald-400">Accountant</span>
                        <span class="text-[10px] text-slate-400 block">Drawers & P&L</span>
                    </button>
                </div>
            </div>

            <!-- Customer Repair Tracker Link -->
            <div class="mt-6 pt-4 border-t border-white/10 flex items-center justify-between text-xs">
                <a href="track.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-400 hover:text-emerald-300 transition-colors">
                    <i class="fa-solid fa-qrcode"></i>
                    <span>Repair Status Tracker &rarr;</span>
                </a>
                <button type="button" onclick="setRole('superadmin@example.com')" title="Developer Root Access" class="text-slate-500 hover:text-purple-400 text-[11px] font-mono transition-colors flex items-center gap-1">
                    <i class="fa-solid fa-terminal"></i>
                    <span>Engineer Login</span>
                </button>
            </div>

        </div>
        
        <p class="text-center text-slate-400 text-xs mt-6">
            &copy; 2026 TechShop System. Enterprise Computer Shop & POS Platform.
        </p>
    </div>

    <script>
        function setRole(email) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = 'password';
        }
    </script>
</body>
</html>
