<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit;
}
$user = $_SESSION['user'];
$role = $user['role'];

function isActive($page) {
    return strpos($_SERVER['REQUEST_URI'], $page) !== false ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white';
}

// Fetch System Name and Logo for the Header
$sys_name = 'TechShop';
$sys_logo = '';
if (isset($pdo)) {
    try {
        $stmt_h = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('system_name', 'shop_logo')");
        while ($row = $stmt_h->fetch()) {
            if ($row['setting_key'] === 'system_name') $sys_name = $row['setting_value'];
            if ($row['setting_key'] === 'shop_logo') $sys_logo = $row['setting_value'];
        }
    } catch (\Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSMS - <?php echo htmlspecialchars($sys_name); ?></title>
    <!-- Tailwind CSS Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans flex h-screen overflow-hidden">
    
    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 text-white flex flex-col hidden md:flex">
        <div class="h-16 flex items-center px-6 border-b border-slate-800">
            <?php if ($sys_logo): ?>
                <img src="../uploads/logo/<?php echo htmlspecialchars($sys_logo); ?>" alt="Logo" class="h-8 w-8 object-contain mr-3 rounded">
            <?php else: ?>
                <i class="fa-solid fa-microchip text-blue-400 text-xl mr-3"></i>
            <?php endif; ?>
            <span class="text-xl font-bold tracking-wide truncate"><?php echo htmlspecialchars($sys_name); ?></span>
        </div>
        
        <div class="p-4 flex items-center border-b border-slate-800">
            <div class="w-10 h-10 rounded-full bg-slate-700 flex items-center justify-center text-lg font-bold">
                <?php echo substr($user['name'], 0, 1); ?>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium"><?php echo htmlspecialchars($user['name']); ?></p>
                <p class="text-xs text-slate-400"><?php echo htmlspecialchars($user['role']); ?></p>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            <a href="dashboard.php" class="flex items-center px-3 py-2.5 rounded-lg group transition-colors <?php echo isActive('dashboard.php'); ?>">
                <i class="fa-solid fa-gauge w-6 text-center"></i>
                <span class="ml-3 font-medium">Dashboard</span>
            </a>
            
            <?php if (in_array($role, ['Admin', 'Manager', 'Cashier', 'Technician'])): ?>
            <a href="products.php" class="flex items-center px-3 py-2.5 rounded-lg group transition-colors <?php echo isActive('products.php'); ?>">
                <i class="fa-solid fa-box w-6 text-center"></i>
                <span class="ml-3 font-medium">Products & Inventory</span>
            </a>
            <?php endif; ?>

            <?php if (in_array($role, ['Admin', 'Manager', 'Cashier'])): ?>
            <a href="pos.php" class="flex items-center px-3 py-2.5 rounded-lg group transition-colors <?php echo isActive('pos.php'); ?>">
                <i class="fa-solid fa-cart-shopping w-6 text-center"></i>
                <span class="ml-3 font-medium">Sales / POS</span>
            </a>
            <a href="build_pc.php" class="flex items-center px-3 py-2.5 rounded-lg group transition-colors <?php echo isActive('build_pc.php'); ?>">
                <i class="fa-solid fa-desktop w-6 text-center text-cyan-400 group-hover:text-cyan-300"></i>
                <span class="ml-3 font-medium">Build PC (Quote)</span>
            </a>
            <?php endif; ?>
            
            <?php if (in_array($role, ['Admin', 'Manager'])): ?>
            <a href="purchases.php" class="flex items-center px-3 py-2.5 rounded-lg group transition-colors <?php echo isActive('purchases.php'); ?>">
                <i class="fa-solid fa-truck w-6 text-center"></i>
                <span class="ml-3 font-medium">Purchases</span>
            </a>
            <?php endif; ?>
            
            <?php if (in_array($role, ['Admin', 'Manager', 'Cashier'])): ?>
            <a href="customers.php" class="flex items-center px-3 py-2.5 rounded-lg group transition-colors <?php echo isActive('customers.php'); ?>">
                <i class="fa-solid fa-users w-6 text-center"></i>
                <span class="ml-3 font-medium">Customers</span>
            </a>
            <?php endif; ?>

            <?php if (in_array($role, ['Admin', 'Manager'])): ?>
            <a href="suppliers.php" class="flex items-center px-3 py-2.5 rounded-lg group transition-colors <?php echo isActive('suppliers.php'); ?>">
                <i class="fa-solid fa-building w-6 text-center"></i>
                <span class="ml-3 font-medium">Suppliers</span>
            </a>
            <?php endif; ?>
            
            <?php if (in_array($role, ['Admin', 'Manager', 'Technician'])): ?>
            <a href="repairs.php" class="flex items-center px-3 py-2.5 rounded-lg group transition-colors <?php echo isActive('repairs.php'); ?>">
                <i class="fa-solid fa-wrench w-6 text-center"></i>
                <span class="ml-3 font-medium">Repair & Service</span>
            </a>
            <?php endif; ?>
            
            <?php if (in_array($role, ['Admin', 'Manager', 'Cashier'])): ?>
            <a href="warranty.php" class="flex items-center px-3 py-2.5 rounded-lg group transition-colors <?php echo isActive('warranty.php'); ?>">
                <i class="fa-solid fa-shield-halved w-6 text-center"></i>
                <span class="ml-3 font-medium">Warranty & Returns</span>
            </a>
            <?php endif; ?>

            <?php if (in_array($role, ['Admin', 'Manager'])): ?>
            <a href="reports.php" class="flex items-center px-3 py-2.5 rounded-lg group transition-colors <?php echo isActive('reports.php'); ?>">
                <i class="fa-solid fa-chart-pie w-6 text-center"></i>
                <span class="ml-3 font-medium">Reports</span>
            </a>
            <?php endif; ?>

            <?php if ($role === 'Admin'): ?>
            <a href="users.php" class="flex items-center px-3 py-2.5 rounded-lg group transition-colors <?php echo isActive('users.php'); ?>">
                <i class="fa-solid fa-user-gear w-6 text-center"></i>
                <span class="ml-3 font-medium">Users & Roles</span>
            </a>
            <a href="settings.php" class="flex items-center px-3 py-2.5 rounded-lg group transition-colors <?php echo isActive('settings.php'); ?>">
                <i class="fa-solid fa-gear w-6 text-center"></i>
                <span class="ml-3 font-medium">Settings</span>
            </a>
            <?php endif; ?>
        </nav>
        
        <div class="p-4 border-t border-slate-800">
            <a href="../logout.php" class="flex items-center px-3 py-2 text-slate-300 hover:bg-red-500/20 hover:text-red-400 rounded-lg group transition-colors">
                <i class="fa-solid fa-right-from-bracket w-6 text-center"></i>
                <span class="ml-3 font-medium">Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Wrapper -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden">
        <!-- Top header -->
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 lg:px-8 z-10 shrink-0">
            <div class="flex items-center md:hidden">
                <button class="text-gray-500 hover:text-gray-700">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
            </div>
            <div class="flex-1"></div>
            <div class="flex items-center space-x-4">
                <button class="text-gray-400 hover:text-gray-600 relative">
                    <i class="fa-regular fa-bell text-xl"></i>
                    <span class="absolute top-0 right-0 -mt-1 -mr-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">3</span>
                </button>
            </div>
        </header>

        <!-- Page Content Scrollable Area -->
        <div class="flex-1 overflow-auto bg-slate-50 p-6 lg:p-8">
