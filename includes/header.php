<?php
require_once __DIR__ . '/auth.php';
enforce_page_access();
require_once __DIR__ . '/lang.php';

$user = $_SESSION['user'];
$role = $user['role'] ?? 'Cashier';
$database_available = isset($pdo) && $pdo instanceof PDO;

// Maintenance Mode & Shop Lockdown Verification Middleware
$is_maint_active = false;
$maint_message = 'The system is undergoing scheduled technical maintenance and updates. Please check back shortly.';
$is_shop_locked = false;
$shop_lock_message = 'This shop account has been deactivated or suspended by system administration. Please contact technical engineering support.';

if (isset($pdo) && $pdo) {
    try {
        $stmt_lock = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('maintenance_mode', 'maintenance_message', 'shop_disabled', 'shop_disabled_message')");
        while ($r = $stmt_lock->fetch(PDO::FETCH_ASSOC)) {
            if ($r['setting_key'] === 'maintenance_mode' && ($r['setting_value'] === '1' || $r['setting_value'] === 'true')) $is_maint_active = true;
            if ($r['setting_key'] === 'maintenance_message' && !empty($r['setting_value'])) $maint_message = $r['setting_value'];
            if ($r['setting_key'] === 'shop_disabled' && ($r['setting_value'] === '1' || $r['setting_value'] === 'true')) $is_shop_locked = true;
            if ($r['setting_key'] === 'shop_disabled_message' && !empty($r['setting_value'])) $shop_lock_message = $r['setting_value'];
        }
    } catch (Exception $e) {}
}

// If user is NOT SuperAdmin and shop is locked/disabled or in maintenance -> Terminate access with security screen!
if ($role !== 'SuperAdmin') {
    if ($is_shop_locked) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Shop Deactivated - TechShop</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        </head>
        <body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 antialiased">
            <div class="max-w-lg w-full bg-slate-900 border border-red-500/30 rounded-3xl p-8 text-center shadow-2xl space-y-5 backdrop-blur-xl">
                <div class="w-16 h-16 rounded-3xl bg-red-500/20 text-red-400 flex items-center justify-center text-3xl mx-auto border border-red-500/30">
                    <i class="fa-solid fa-ban"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-white tracking-tight">Shop Access Suspended</h1>
                    <p class="text-xs text-red-400 font-bold uppercase tracking-wider mt-1">Deactivated by System Administrator</p>
                </div>
                <div class="p-4 bg-red-950/40 rounded-2xl border border-red-500/20 text-xs text-slate-300 leading-relaxed text-left">
                    <?php echo nl2br(htmlspecialchars($shop_lock_message)); ?>
                </div>
                <div class="pt-2">
                    <a href="../logout.php" class="px-6 py-3 rounded-2xl bg-white/10 hover:bg-white/20 text-white font-bold text-xs transition-all inline-flex items-center gap-2">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
                    </a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    if ($is_maint_active) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>System Maintenance - TechShop</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        </head>
        <body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 antialiased">
            <div class="max-w-lg w-full bg-slate-900 border border-purple-500/30 rounded-3xl p-8 text-center shadow-2xl space-y-5 backdrop-blur-xl">
                <div class="w-16 h-16 rounded-3xl bg-purple-500/20 text-purple-400 flex items-center justify-center text-3xl mx-auto border border-purple-500/30">
                    <i class="fa-solid fa-screwdriver-wrench animate-pulse"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-white tracking-tight">System Under Maintenance</h1>
                    <p class="text-xs text-purple-400 font-bold uppercase tracking-wider mt-1">Scheduled Technical Maintenance</p>
                </div>
                <div class="p-4 bg-purple-950/30 rounded-2xl border border-purple-500/20 text-xs text-slate-300 leading-relaxed text-left">
                    <?php echo nl2br(htmlspecialchars($maint_message)); ?>
                </div>
                <div class="pt-2">
                    <a href="../logout.php" class="px-6 py-3 rounded-2xl bg-white/10 hover:bg-white/20 text-white font-bold text-xs transition-all inline-flex items-center gap-2">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
                    </a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

function isActive($page) {
    $current = basename($_SERVER['PHP_SELF']);
    if ($current === $page || strpos($_SERVER['REQUEST_URI'], $page) !== false) {
        return 'bg-emerald-50 text-emerald-600 font-semibold shadow-sm shadow-emerald-500/5';
    }
    return 'text-slate-500 hover:text-slate-900 hover:bg-slate-50/80 font-medium';
}

function getIconColor($page) {
    $current = basename($_SERVER['PHP_SELF']);
    if ($current === $page || strpos($_SERVER['REQUEST_URI'], $page) !== false) {
        return 'text-emerald-500';
    }
    return 'text-slate-400 group-hover:text-slate-600';
}

// Fetch System Settings & Real Notifications
$sys_name = 'TechShop';
$sys_logo = '';
$header_notifications = [];
$unread_notif_count = 0;

// Dynamic Active Currency Resolution
$currency_symbol = 'Rs.';
$currency_code = 'LKR';

if (isset($pdo) && $pdo) {
    try {
        // Settings
        $stmt_h = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('system_name', 'shop_logo', 'currency_symbol', 'shop_currency', 'supported_currencies_json')");
        $all_settings = [];
        while ($row = $stmt_h->fetch()) {
            $all_settings[$row['setting_key']] = $row['setting_value'];
            if ($row['setting_key'] === 'system_name' && !empty($row['setting_value'])) $sys_name = $row['setting_value'];
            if ($row['setting_key'] === 'shop_logo') $sys_logo = $row['setting_value'];
        }

        // Active Currencies list from Master
        $active_currencies = [];
        if (!empty($all_settings['supported_currencies_json'])) {
            $decoded_currs = json_decode($all_settings['supported_currencies_json'], true);
            if (is_array($decoded_currs)) {
                foreach ($decoded_currs as $c) {
                    if (($c['status'] ?? 'active') === 'active') {
                        $active_currencies[$c['code']] = $c;
                    }
                }
            }
        }

        $pref_code = $all_settings['shop_currency'] ?? '';
        if (!empty($pref_code) && isset($active_currencies[$pref_code])) {
            $currency_code = $pref_code;
            $currency_symbol = $active_currencies[$pref_code]['symbol'] ?? 'Rs.';
        } elseif (!empty($all_settings['currency_symbol'])) {
            $currency_symbol = $all_settings['currency_symbol'];
        } elseif (!empty($active_currencies)) {
            $first_active = reset($active_currencies);
            $currency_code = $first_active['code'];
            $currency_symbol = $first_active['symbol'] ?? 'Rs.';
        }

        // Real Notifications: Low stock
        $stmt_notif_low = $pdo->query("
            SELECT p.name, COALESCE(s.cnt, 0) as stock_qty, p.reorder_level 
            FROM products p 
            LEFT JOIN (SELECT product_id, COUNT(*) as cnt FROM product_serials WHERE status = 'in_stock' GROUP BY product_id) s ON p.id = s.product_id 
            WHERE p.status = 'Active' AND COALESCE(s.cnt, 0) <= p.reorder_level
            LIMIT 3
        ");
        while ($n = $stmt_notif_low->fetch()) {
            $header_notifications[] = [
                'type' => 'warning',
                'title' => 'Low Stock: ' . $n['name'],
                'desc' => 'Only ' . $n['stock_qty'] . ' in stock (Reorder at ' . $n['reorder_level'] . ')',
                'time' => 'Inventory Alert'
            ];
            $unread_notif_count++;
        }

        // Real Notifications: Recent sales
        $stmt_notif_sales = $pdo->query("
            SELECT invoice_no, total_amount, created_at 
            FROM sales 
            WHERE status = 'Completed' 
            ORDER BY id DESC 
            LIMIT 2
        ");
        while ($s = $stmt_notif_sales->fetch()) {
            $header_notifications[] = [
                'type' => 'success',
                'title' => 'Sale ' . $s['invoice_no'],
                'desc' => 'Completed order of ' . htmlspecialchars($currency_symbol) . ' ' . number_format($s['total_amount'], 2),
                'time' => date('M j, g:i A', strtotime($s['created_at']))
            ];
            $unread_notif_count++;
        }

    } catch (\Exception $e) {}
}

if (!function_exists('format_currency')) {
    function format_currency($amount) {
        global $currency_symbol;
        return htmlspecialchars($currency_symbol ?? 'Rs.') . ' ' . number_format((float)$amount, 2);
    }
}

// User Initials
$user_name = $user['name'] ?? 'Staff User';
$user_email = $user['email'] ?? '';
$user_role = $user['role'] ?? 'Cashier';
$name_parts = explode(' ', trim($user_name));
$initials = strtoupper(substr($name_parts[0] ?? 'U', 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
if (empty($initials)) $initials = 'U';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($sys_name); ?> - Management & POS System</title>
    <!-- Tailwind CSS Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Plus Jakarta Sans & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome 6 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // Instant theme initialisation before DOM paint to prevent flash
        (function() {
            const savedTheme = localStorage.getItem('techshop_theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();

        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { 
                        sans: ['"Plus Jakarta Sans"', 'Inter', 'sans-serif'],
                        display: ['"Plus Jakarta Sans"', 'sans-serif']
                    },
                    colors: {
                        emerald: {
                            50: '#F0FDF4',
                            100: '#DCFCE7',
                            200: '#BBF7D0',
                            300: '#86EFAC',
                            400: '#4ADE80',
                            500: '#10B981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065F46',
                            900: '#064E3B',
                        }
                    },
                    borderRadius: {
                        'xl': '0.875rem',
                        '2xl': '1.125rem',
                        '3xl': '1.5rem',
                    },
                    boxShadow: {
                        'soft': '0 4px 20px -2px rgba(0, 0, 0, 0.03), 0 2px 6px -1px rgba(0, 0, 0, 0.02)',
                        'card': '0 2px 10px rgba(0, 0, 0, 0.02), 0 1px 3px rgba(0, 0, 0, 0.01)',
                        'floating': '0 20px 35px -5px rgba(0, 0, 0, 0.08), 0 10px 15px -5px rgba(0, 0, 0, 0.04)',
                    }
                }
            }
        }
        window.APP_CURRENCY = <?php echo json_encode($currency_symbol, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        window.CURRENCY_SYMBOL = window.APP_CURRENCY;
        window.CSMS_CSRF_TOKEN = <?php echo json_encode(csrf_token()); ?>;

        // Attach CSRF protection to all same-origin POST forms and fetch calls.
        (() => {
            const addTokenToForm = (form) => {
                if ((form.method || 'get').toLowerCase() !== 'post') return;
                let input = form.querySelector('input[name="_csrf_token"]');
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = '_csrf_token';
                    form.appendChild(input);
                }
                input.value = window.CSMS_CSRF_TOKEN;
            };

            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('form').forEach(addTokenToForm);
            });
            document.addEventListener('submit', (event) => addTokenToForm(event.target), true);

            const originalFetch = window.fetch.bind(window);
            window.fetch = (input, init = {}) => {
                const requestUrl = typeof input === 'string' ? input : input.url;
                const url = new URL(requestUrl, window.location.href);
                const method = (init.method || (typeof input !== 'string' && input.method) || 'GET').toUpperCase();

                if (url.origin === window.location.origin && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
                    const headers = new Headers(init.headers || (typeof input !== 'string' ? input.headers : undefined));
                    headers.set('X-CSRF-Token', window.CSMS_CSRF_TOKEN);
                    init = { ...init, headers };
                    if (init.body instanceof FormData && !init.body.has('_csrf_token')) {
                        init.body.append('_csrf_token', window.CSMS_CSRF_TOKEN);
                    }
                }

                return originalFetch(input, init);
            };
        })();
    </script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', Inter, sans-serif;
            background-color: #F7FAF8;
            color: #1E293B;
            -webkit-font-smoothing: antialiased;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #E2E8F0;
            border-radius: 9999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #CBD5E1;
        }
        .sidebar-item {
            transition: all 0.18s ease;
        }

        /* Full Dark Theme Styles */
        html.dark {
            color-scheme: dark;
        }
        html.dark body {
            background-color: #0F172A !important;
            color: #F1F5F9 !important;
        }
        html.dark aside#mainSidebar {
            background-color: #1E293B !important;
            border-color: #334155 !important;
        }
        html.dark main {
            background-color: #0F172A !important;
        }
        html.dark .bg-white {
            background-color: #1E293B !important;
            color: #F1F5F9 !important;
            border-color: #334155 !important;
        }
        html.dark .bg-\[\#F7FAF8\] {
            background-color: #0F172A !important;
        }
        html.dark .bg-slate-50, 
        html.dark .bg-slate-50\/80, 
        html.dark .bg-slate-50\/70, 
        html.dark .bg-slate-50\/60, 
        html.dark .bg-slate-50\/50, 
        html.dark .bg-slate-50\/40 {
            background-color: #151F32 !important;
            border-color: #334155 !important;
        }
        html.dark .bg-slate-100,
        html.dark .bg-slate-100\/80 {
            background-color: #334155 !important;
            color: #E2E8F0 !important;
            border-color: #475569 !important;
        }
        html.dark .text-slate-900,
        html.dark .text-slate-800,
        html.dark .text-slate-700 {
            color: #F8FAFC !important;
        }
        html.dark .text-slate-600,
        html.dark .text-slate-500 {
            color: #94A3B8 !important;
        }
        html.dark .text-slate-400 {
            color: #64748B !important;
        }
        html.dark .border-slate-100,
        html.dark .border-slate-100\/90,
        html.dark .border-slate-100\/80,
        html.dark .border-slate-100\/60,
        html.dark .border-slate-200,
        html.dark .border-slate-200\/80,
        html.dark .border-slate-200\/70,
        html.dark .border-slate-200\/60 {
            border-color: #334155 !important;
        }
        html.dark .divide-slate-100,
        html.dark .divide-slate-50 {
            border-color: #334155 !important;
        }
        html.dark input, 
        html.dark select, 
        html.dark textarea {
            background-color: #151F32 !important;
            color: #F8FAFC !important;
            border-color: #334155 !important;
        }
        html.dark input::placeholder,
        html.dark textarea::placeholder {
            color: #64748B !important;
        }
        html.dark .shadow-card,
        html.dark .shadow-floating,
        html.dark .shadow-soft {
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5) !important;
        }
        html.dark .hover\:bg-slate-50:hover,
        html.dark .hover\:bg-slate-50\/60:hover,
        html.dark .hover\:bg-slate-50\/80:hover,
        html.dark .hover\:bg-slate-100:hover,
        html.dark .hover\:bg-white:hover {
            background-color: #334155 !important;
        }
        html.dark .bg-emerald-50,
        html.dark .bg-emerald-50\/80,
        html.dark .bg-emerald-50\/70,
        html.dark .bg-emerald-50\/50,
        html.dark .bg-emerald-50\/40 {
            background-color: rgba(16, 185, 129, 0.15) !important;
            border-color: rgba(16, 185, 129, 0.3) !important;
            color: #6EE7B7 !important;
        }
        html.dark .text-emerald-800,
        html.dark .text-emerald-950,
        html.dark .text-emerald-700 {
            color: #6EE7B7 !important;
        }
        html.dark .border-emerald-200,
        html.dark .border-emerald-100,
        html.dark .border-emerald-100\/80,
        html.dark .border-emerald-100\/60 {
            border-color: rgba(16, 185, 129, 0.3) !important;
        }
        html.dark .bg-blue-50 {
            background-color: rgba(59, 130, 246, 0.15) !important;
            border-color: rgba(59, 130, 246, 0.3) !important;
            color: #93C5FD !important;
        }
        html.dark .bg-red-50 {
            background-color: rgba(239, 68, 68, 0.15) !important;
            border-color: rgba(239, 68, 68, 0.3) !important;
            color: #FCA5A5 !important;
        }
        html.dark .bg-amber-50 {
            background-color: rgba(245, 158, 11, 0.15) !important;
            border-color: rgba(245, 158, 11, 0.3) !important;
            color: #FCD34D !important;
        }
        html.dark .bg-purple-50 {
            background-color: rgba(168, 85, 247, 0.15) !important;
            border-color: rgba(168, 85, 247, 0.3) !important;
            color: #D8B4FE !important;
        }
        html.dark .bg-cyan-50 {
            background-color: rgba(6, 182, 212, 0.15) !important;
            border-color: rgba(6, 182, 212, 0.3) !important;
            color: #67E8F9 !important;
        }
        html.dark .bg-indigo-50 {
            background-color: rgba(99, 102, 241, 0.15) !important;
            border-color: rgba(99, 102, 241, 0.3) !important;
            color: #A5B4FC !important;
        }
        html.dark ::-webkit-scrollbar-thumb {
            background: #334155;
        }
        html.dark ::-webkit-scrollbar-thumb:hover {
            background: #475569;
        }
    </style>
</head>
<body class="bg-[#F7FAF8] text-slate-800 font-sans flex h-screen overflow-hidden antialiased">
    
    <!-- Mobile Sidebar Backdrop -->
    <div id="sidebarBackdrop" onclick="toggleMobileSidebar()" class="fixed inset-0 bg-slate-900/30 backdrop-blur-sm z-40 hidden md:hidden transition-opacity"></div>

    <!-- Sidebar -->
    <aside id="mainSidebar" class="w-64 bg-white border-r border-slate-100/90 flex flex-col fixed md:static inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out">
        
        <!-- Logo / Brand Header -->
        <div class="h-20 flex items-center justify-between px-7">
            <a href="dashboard.php" class="flex items-center gap-2.5 group">
                <?php if ($sys_logo): ?>
                    <img src="../uploads/logo/<?php echo htmlspecialchars($sys_logo); ?>" alt="Logo" class="h-9 w-9 object-contain rounded-xl">
                <?php else: ?>
                    <div class="w-9 h-9 rounded-2xl bg-emerald-500 text-white flex items-center justify-center font-black text-base shadow-sm shadow-emerald-500/20">
                        <?php echo strtoupper(substr($sys_name, 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <div class="flex items-center tracking-tight">
                    <span class="text-xl font-bold text-slate-900 tracking-tight"><?php echo htmlspecialchars($sys_name); ?></span>
                    <span class="w-2 h-2 rounded-full bg-emerald-500 ml-1 inline-block"></span>
                </div>
            </a>
            <button onclick="toggleMobileSidebar()" class="md:hidden text-slate-400 hover:text-slate-600 p-1.5 rounded-lg">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
            <button class="hidden md:flex text-slate-400 hover:text-slate-700 p-1.5 rounded-lg transition-colors">
                <i class="fa-solid fa-bars-staggered text-sm"></i>
            </button>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 overflow-y-auto px-4 py-2 space-y-6">
            
            <!-- Group: Operations -->
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 px-3 mb-2"><?php echo __('nav_operations'); ?></p>
                <div class="space-y-1">
                    <a href="dashboard.php" class="sidebar-item flex items-center px-3.5 py-3 rounded-2xl group <?php echo isActive('dashboard.php'); ?>">
                        <i class="fa-solid fa-house w-5 text-center text-[15px] <?php echo getIconColor('dashboard.php'); ?>"></i>
                        <span class="ml-3 text-[14px]"><?php echo __('nav_dashboard'); ?></span>
                    </a>
                    
                    <?php if (can_access_page('pos.php')): ?>
                    <a href="pos.php" class="sidebar-item flex items-center px-3.5 py-3 rounded-2xl group <?php echo isActive('pos.php'); ?>">
                        <i class="fa-solid fa-cash-register w-5 text-center text-[15px] <?php echo getIconColor('pos.php'); ?>"></i>
                        <span class="ml-3 text-[14px]"><?php echo __('nav_pos'); ?></span>
                    </a>
                    <a href="build_pc.php" class="sidebar-item flex items-center px-3.5 py-3 rounded-2xl group <?php echo isActive('build_pc.php'); ?>">
                        <i class="fa-solid fa-desktop w-5 text-center text-[15px] <?php echo getIconColor('build_pc.php'); ?>"></i>
                        <span class="ml-3 text-[14px]"><?php echo __('nav_pc_builder'); ?></span>
                    </a>
                    <?php endif; ?>

                    <?php if (can_access_page('repairs.php')): ?>
                    <a href="repairs.php" class="sidebar-item flex items-center px-3.5 py-3 rounded-2xl group <?php echo isActive('repairs.php'); ?>">
                        <i class="fa-solid fa-wrench w-5 text-center text-[15px] <?php echo getIconColor('repairs.php'); ?>"></i>
                        <span class="ml-3 text-[14px]"><?php echo __('nav_repairs'); ?></span>
                    </a>
                    <?php endif; ?>

                    <?php if (can_access_page('customers.php')): ?>
                    <a href="customers.php" class="sidebar-item flex items-center px-3.5 py-3 rounded-2xl group <?php echo isActive('customers.php'); ?>">
                        <i class="fa-solid fa-users w-5 text-center text-[15px] <?php echo getIconColor('customers.php'); ?>"></i>
                        <span class="ml-3 text-[14px]"><?php echo __('nav_customers'); ?></span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Group: Inventory & Procurement -->
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 px-3 mb-2"><?php echo __('nav_inventory_supply'); ?></p>
                <div class="space-y-1">
                    <?php if (can_access_page('products.php')): ?>
                    <a href="products.php" class="sidebar-item flex items-center px-3.5 py-3 rounded-2xl group <?php echo isActive('products.php'); ?>">
                        <i class="fa-solid fa-boxes-stacked w-5 text-center text-[15px] <?php echo getIconColor('products.php'); ?>"></i>
                        <span class="ml-3 text-[14px]"><?php echo __('nav_products'); ?></span>
                    </a>
                    <?php endif; ?>

                    <?php if (can_access_page('purchases.php')): ?>
                    <a href="purchases.php" class="sidebar-item flex items-center px-3.5 py-3 rounded-2xl group <?php echo isActive('purchases.php'); ?>">
                        <i class="fa-solid fa-cart-flatbed w-5 text-center text-[15px] <?php echo getIconColor('purchases.php'); ?>"></i>
                        <span class="ml-3 text-[14px]"><?php echo __('nav_purchases'); ?></span>
                    </a>
                    <?php endif; ?>

                    <?php if (can_access_page('suppliers.php')): ?>
                    <a href="suppliers.php" class="sidebar-item flex items-center px-3.5 py-3 rounded-2xl group <?php echo isActive('suppliers.php'); ?>">
                        <i class="fa-solid fa-truck-field w-5 text-center text-[15px] <?php echo getIconColor('suppliers.php'); ?>"></i>
                        <span class="ml-3 text-[14px]"><?php echo __('nav_suppliers'); ?></span>
                    </a>
                    <?php endif; ?>

                    <?php if (can_access_page('warranty.php')): ?>
                    <a href="warranty.php" class="sidebar-item flex items-center px-3.5 py-3 rounded-2xl group <?php echo isActive('warranty.php'); ?>">
                        <i class="fa-solid fa-shield-halved w-5 text-center text-[15px] <?php echo getIconColor('warranty.php'); ?>"></i>
                        <span class="ml-3 text-[14px]"><?php echo __('nav_warranty'); ?></span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Group: Finance & Administration -->
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 px-3 mb-2"><?php echo __('nav_finance_control'); ?></p>
                <div class="space-y-1">
                    <?php if (can_access_page('accounting.php')): ?>
                    <a href="accounting.php" class="sidebar-item flex items-center px-3.5 py-3 rounded-2xl group <?php echo isActive('accounting.php'); ?>">
                        <i class="fa-solid fa-coins w-5 text-center text-[15px] <?php echo getIconColor('accounting.php'); ?>"></i>
                        <span class="ml-3 text-[14px]"><?php echo __('nav_accounting'); ?></span>
                    </a>
                    <?php endif; ?>

                    <?php if (can_access_page('reports.php')): ?>
                    <a href="reports.php" class="sidebar-item flex items-center px-3.5 py-3 rounded-2xl group <?php echo isActive('reports.php'); ?>">
                        <i class="fa-solid fa-chart-pie w-5 text-center text-[15px] <?php echo getIconColor('reports.php'); ?>"></i>
                        <span class="ml-3 text-[14px]"><?php echo __('nav_reports'); ?></span>
                    </a>
                    <?php endif; ?>

                    <?php if (in_array($role, ['Admin', 'Manager', 'SuperAdmin'], true)): ?>
                    <a href="users.php" class="sidebar-item flex items-center px-3.5 py-3 rounded-2xl group <?php echo isActive('users.php'); ?>">
                        <i class="fa-solid fa-user-gear w-5 text-center text-[15px] <?php echo getIconColor('users.php'); ?>"></i>
                        <span class="ml-3 text-[14px]"><?php echo __('nav_staff'); ?></span>
                    </a>
                    <a href="audit_log.php" class="sidebar-item flex items-center px-3.5 py-3 rounded-2xl group <?php echo isActive('audit_log.php'); ?>">
                        <i class="fa-solid fa-clock-rotate-left w-5 text-center text-[15px] <?php echo getIconColor('audit_log.php'); ?>"></i>
                        <span class="ml-3 text-[14px]"><?php echo __('nav_audit'); ?></span>
                    </a>
                    <?php endif; ?>
                    <?php if (!in_array($role, ['Admin', 'Manager', 'SuperAdmin'], true)): ?>
                    <!-- All other roles: show Permission Matrix as read-only -->
                    <a href="users.php?tab=matrix" class="sidebar-item flex items-center px-3.5 py-3 rounded-2xl group <?php echo (basename($_SERVER['PHP_SELF']) === 'users.php' && ($_GET['tab'] ?? '') === 'matrix') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-table-cells w-5 text-center text-[15px] text-slate-400 group-hover:text-emerald-500"></i>
                        <span class="ml-3 text-[14px]">Permission Matrix</span>
                    </a>
                    <?php endif; ?>

                    <?php if (can_access_page('shop_settings.php')): ?>
                    <a href="shop_settings.php" class="sidebar-item flex items-center px-3.5 py-3 rounded-2xl group <?php echo isActive('shop_settings.php'); ?>">
                        <i class="fa-solid fa-store w-5 text-center text-[15px] <?php echo getIconColor('shop_settings.php'); ?>"></i>
                        <span class="ml-3 text-[14px]"><?php echo __('nav_shop_settings'); ?></span>
                    </a>
                    <?php endif; ?>

                    <?php if ($role === 'SuperAdmin'): ?>
                    <a href="settings.php" class="sidebar-item flex items-center justify-between px-3.5 py-3 rounded-2xl group <?php echo isActive('settings.php'); ?>">
                        <div class="flex items-center">
                            <i class="fa-solid fa-gears w-5 text-center text-[15px] <?php echo getIconColor('settings.php'); ?>"></i>
                            <span class="ml-3 text-[14px]"><?php echo __('nav_system_settings'); ?></span>
                        </div>
                        <span class="px-1.5 py-0.5 rounded-md bg-purple-100 text-purple-700 text-[10px] font-extrabold uppercase">Super</span>
                    </a>
                    <?php endif; ?>

                    <a href="track.php" target="_blank" class="sidebar-item flex items-center px-3.5 py-3 rounded-2xl group text-emerald-600 hover:bg-emerald-50/80 font-bold">
                        <i class="fa-solid fa-qrcode w-5 text-center text-[15px] text-emerald-500"></i>
                        <span class="ml-3 text-[14px]">Public Tracker</span>
                    </a>
                </div>
            </div>

        </nav>
        
        <!-- Bottom Logout Area -->
        <div class="p-4 border-t border-slate-100">
            <a href="../logout.php" class="flex items-center px-3.5 py-2.5 text-slate-400 hover:text-red-500 hover:bg-red-50/60 rounded-2xl transition-all">
                <i class="fa-solid fa-arrow-right-from-bracket w-5 text-center text-[14px]"></i>
                <span class="ml-3 text-[13px] font-semibold"><?php echo __('nav_sign_out'); ?></span>
            </a>
        </div>
    </aside>

    <!-- Main Content Wrapper -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-[#F7FAF8]">

        <?php if (!$database_available): ?>
        <div class="bg-red-600 text-white px-6 py-2.5 flex items-center justify-between shadow-md z-30 shrink-0 text-xs font-bold">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-database"></i>
                <span>Database unavailable. Live data and write actions are disabled; no demo data is being shown.</span>
            </div>
            <a href="../logout.php" class="underline hover:text-red-100">Sign out</a>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['superadmin_impersonator'])): ?>
        <!-- Impersonation Alert Banner -->
        <div class="bg-gradient-to-r from-purple-800 via-indigo-800 to-purple-900 text-white px-6 py-2.5 flex items-center justify-between shadow-md z-30 shrink-0 text-xs font-semibold">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                <i class="fa-solid fa-user-secret text-amber-300"></i>
                <span><b>SuperAdmin Developer Mode:</b> Impersonating <b><?php echo htmlspecialchars($user_name); ?></b> (Role: <span class="bg-white/20 px-2 py-0.5 rounded-full font-mono"><?php echo htmlspecialchars($user_role); ?></span>).</span>
            </div>
            <a href="settings.php?action=revert_impersonate" class="px-3.5 py-1 rounded-xl bg-white text-purple-900 hover:bg-purple-50 font-bold transition-all shadow-sm flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-rotate-left"></i>
                <span>Return to SuperAdmin</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($role === 'SuperAdmin' && is_flag_enabled('superadmin_shop_access', 0)): ?>
        <div class="bg-purple-900 text-white px-6 py-1.5 flex items-center justify-between shadow-md z-30 shrink-0 text-xs font-semibold">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <i class="fa-solid fa-bug text-purple-300"></i>
                <span><b>SuperAdmin Support Mode ACTIVE:</b> Full shop module access enabled for error handling &amp; debugging.</span>
            </div>
            <a href="settings.php" class="underline text-purple-200 hover:text-white font-bold">Disable Support Mode &rarr;</a>
        </div>
        <?php endif; ?>

        <?php if ($role === 'SuperAdmin' && $is_shop_locked): ?>
        <div class="bg-red-600 text-white px-6 py-2 flex items-center justify-between shadow-md z-30 shrink-0 text-xs font-bold">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-ban text-red-200"></i>
                <span>🛑 <b>SHOP DEACTIVATED / LOCKDOWN ACTIVE:</b> Non-admin staff and shop owners are locked out.</span>
            </div>
            <a href="settings.php?tab=system" class="underline hover:text-red-100">Manage Lockdown &rarr;</a>
        </div>
        <?php elseif ($role === 'SuperAdmin' && $is_maint_active): ?>
        <div class="bg-purple-700 text-white px-6 py-2 flex items-center justify-between shadow-md z-30 shrink-0 text-xs font-bold">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-screwdriver-wrench text-purple-200"></i>
                <span>🔧 <b>SYSTEM IN MAINTENANCE MODE:</b> Non-SuperAdmin staff are locked out.</span>
            </div>
            <a href="settings.php?tab=system" class="underline hover:text-purple-100">Manage Maintenance &rarr;</a>
        </div>
        <?php endif; ?>
        
        <!-- Top Navigation Bar -->
        <header class="h-20 bg-transparent flex items-center justify-between px-6 lg:px-10 z-20 shrink-0">
            
            <!-- Mobile Toggle & Search Bar -->
            <div class="flex items-center gap-4 flex-1">
                <button onclick="toggleMobileSidebar()" class="md:hidden w-10 h-10 rounded-xl bg-white border border-slate-200 text-slate-600 flex items-center justify-center hover:bg-slate-50">
                    <i class="fa-solid fa-bars text-base"></i>
                </button>

                <!-- Pill Search Input -->
                <div class="relative w-full max-w-sm hidden sm:block">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none"></i>
                    <input 
                        type="text" 
                        id="globalSearchInput" 
                        placeholder="<?php echo __('hdr_search_placeholder', 'Search anything (F2)...'); ?>" 
                        class="w-full pl-11 pr-4 py-2.5 bg-white border border-slate-200/80 rounded-2xl text-sm font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 shadow-sm transition-all"
                        onkeyup="handleGlobalSearch(event)"
                    >
                </div>
            </div>

            <!-- Right Utilities & Profile -->
            <div class="flex items-center space-x-2 sm:space-x-3">
                
                <!-- Quick Language Switcher Dropdown -->
                <div class="relative">
                    <button onclick="toggleLangDropdown()" title="Switch Language" class="h-10 px-3.5 rounded-full bg-white border border-slate-200/80 text-slate-700 hover:text-emerald-600 hover:border-emerald-200 flex items-center gap-1.5 shadow-sm text-xs font-bold transition-all">
                        <i class="fa-solid fa-globe text-emerald-500 text-sm"></i>
                        <span><?php echo ($current_lang ?? 'en') === 'si' ? 'සිංහල' : (($current_lang ?? 'en') === 'ta' ? 'தமிழ்' : 'English'); ?></span>
                        <i class="fa-solid fa-chevron-down text-[10px] text-slate-400"></i>
                    </button>
                    <div id="langDropdown" class="hidden absolute right-0 mt-3 w-40 bg-white rounded-2xl shadow-floating border border-slate-100 p-2 z-50 text-xs font-bold divide-y divide-slate-50">
                        <?php
                        $current_query = $_GET;
                        $current_query['lang'] = 'en';
                        $lang_en_url = '?' . http_build_query($current_query);
                        $current_query['lang'] = 'si';
                        $lang_si_url = '?' . http_build_query($current_query);
                        $current_query['lang'] = 'ta';
                        $lang_ta_url = '?' . http_build_query($current_query);
                        ?>
                        <a href="<?php echo htmlspecialchars($lang_en_url); ?>" class="flex items-center justify-between px-3 py-2 rounded-xl hover:bg-slate-50 text-slate-700 <?php echo ($current_lang ?? 'en') === 'en' ? 'text-emerald-600 bg-emerald-50/60' : ''; ?>">
                            <span>English</span>
                            <span class="text-[10px] text-slate-400 font-mono">EN</span>
                        </a>
                        <a href="<?php echo htmlspecialchars($lang_si_url); ?>" class="flex items-center justify-between px-3 py-2 rounded-xl hover:bg-slate-50 text-slate-700 <?php echo ($current_lang ?? 'en') === 'si' ? 'text-emerald-600 bg-emerald-50/60' : ''; ?>">
                            <span>සිංහල</span>
                            <span class="text-[10px] text-slate-400 font-mono">SI</span>
                        </a>
                        <a href="<?php echo htmlspecialchars($lang_ta_url); ?>" class="flex items-center justify-between px-3 py-2 rounded-xl hover:bg-slate-50 text-slate-700 <?php echo ($current_lang ?? 'en') === 'ta' ? 'text-emerald-600 bg-emerald-50/60' : ''; ?>">
                            <span>தமிழ்</span>
                            <span class="text-[10px] text-slate-400 font-mono">TA</span>
                        </a>
                    </div>
                </div>

                <!-- Dark / Light Mode Toggle Button -->
                <button id="themeToggleBtn" onclick="toggleTheme()" title="Toggle Theme" class="w-10 h-10 rounded-full bg-white border border-slate-200/80 text-slate-600 hover:text-emerald-600 hover:border-emerald-200 flex items-center justify-center shadow-sm transition-all">
                    <i class="fa-regular fa-moon text-base"></i>
                </button>

                <!-- Quick Calculator Launcher Button -->
                <button id="calculatorBtn" onclick="toggleCalculator()" title="Quick Calculator" class="w-10 h-10 rounded-full bg-white border border-slate-200/80 text-slate-600 hover:text-emerald-600 hover:border-emerald-200 flex items-center justify-center shadow-sm transition-all">
                    <i class="fa-solid fa-calculator text-base"></i>
                </button>

                <!-- Notifications Button with Badge & Dropdown -->
                <div class="relative">
                    <button id="notifBtn" onclick="toggleNotifications()" title="Notifications" class="w-10 h-10 rounded-full bg-white border border-slate-200/80 text-slate-600 hover:text-emerald-600 hover:border-emerald-200 flex items-center justify-center shadow-sm transition-all relative">
                        <i class="fa-regular fa-bell text-base"></i>
                        <?php if ($unread_notif_count > 0): ?>
                            <span class="absolute top-2 right-2 w-2 h-2 rounded-full bg-emerald-500 ring-2 ring-white"></span>
                        <?php endif; ?>
                    </button>

                    <!-- Notifications Dropdown Popover -->
                    <div id="notifDropdown" class="hidden absolute right-0 mt-3 w-80 bg-white rounded-2xl shadow-floating border border-slate-100 p-4 z-50">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <span class="font-bold text-slate-800 text-sm">Notifications</span>
                            <?php if ($unread_notif_count > 0): ?>
                                <span class="text-[11px] font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full"><?php echo $unread_notif_count; ?> Alert<?php echo $unread_notif_count > 1 ? 's' : ''; ?></span>
                            <?php else: ?>
                                <span class="text-[11px] text-slate-400">All clear</span>
                            <?php endif; ?>
                        </div>
                        <div class="divide-y divide-slate-50 py-2 text-xs space-y-2 max-h-64 overflow-y-auto">
                            <?php if (!empty($header_notifications)): ?>
                                <?php foreach ($header_notifications as $notif): ?>
                                <div class="pt-2 flex items-start gap-2.5">
                                    <div class="w-7 h-7 rounded-full <?php echo $notif['type'] === 'warning' ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600'; ?> flex items-center justify-center shrink-0 mt-0.5">
                                        <i class="fa-solid <?php echo $notif['type'] === 'warning' ? 'fa-triangle-exclamation' : 'fa-check'; ?> text-xs"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($notif['title']); ?></p>
                                        <p class="text-slate-500"><?php echo htmlspecialchars($notif['desc']); ?></p>
                                        <span class="text-[10px] text-slate-400"><?php echo htmlspecialchars($notif['time']); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="py-6 text-center text-slate-400">
                                    <i class="fa-regular fa-bell-slash text-2xl mb-2 text-slate-300 block"></i>
                                    <p>No new notifications</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- User Profile Chip & Dropdown -->
                <div class="relative">
                    <button id="userMenuBtn" onclick="toggleUserMenu()" class="flex items-center gap-3 p-1.5 pr-3 rounded-full hover:bg-white transition-all">
                        <div class="w-10 h-10 rounded-full bg-emerald-600 text-white font-extrabold flex items-center justify-center text-xs shadow-sm ring-2 ring-emerald-500/20">
                            <?php echo htmlspecialchars($initials); ?>
                        </div>
                        <div class="text-left hidden md:block">
                            <div class="text-sm font-bold text-slate-900 leading-tight"><?php echo htmlspecialchars($user_name); ?></div>
                            <div class="text-[11px] font-medium text-slate-400"><?php echo htmlspecialchars($user_role); ?></div>
                        </div>
                        <i class="fa-solid fa-chevron-down text-slate-400 text-[11px] ml-1"></i>
                    </button>

                    <!-- User Menu Dropdown Popover -->
                    <div id="userMenuDropdown" class="hidden absolute right-0 mt-2 w-52 bg-white rounded-2xl shadow-floating border border-slate-100 p-2 z-50">
                        <div class="px-3 py-2 border-b border-slate-100">
                            <p class="text-xs font-bold text-slate-800"><?php echo htmlspecialchars($user_name); ?></p>
                            <p class="text-[11px] text-slate-400 truncate"><?php echo htmlspecialchars($user_email); ?></p>
                            <span class="inline-block text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md mt-1"><?php echo htmlspecialchars($user_role); ?></span>
                        </div>
                        <div class="py-1">
                            <?php if ($role === 'Admin'): ?>
                            <a href="settings.php" class="flex items-center px-3 py-2 text-xs font-medium text-slate-600 hover:text-emerald-600 hover:bg-emerald-50/60 rounded-xl transition-colors">
                                <i class="fa-solid fa-sliders w-4 text-slate-400 mr-2"></i> Shop Settings
                            </a>
                            <?php endif; ?>
                            <a href="../logout.php" class="flex items-center px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-50/60 rounded-xl transition-colors">
                                <i class="fa-solid fa-right-from-bracket w-4 text-red-400 mr-2"></i> Sign Out
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </header>

        <!-- Page Content Scrollable Area -->
        <div class="flex-1 overflow-y-auto px-6 lg:px-10 pb-10">
