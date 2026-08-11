<?php
require_once '../includes/db.php';
require_once '../includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-2xl font-bold text-slate-900">Overview</h1>
    <div class="text-sm text-slate-500">
        <i class="fa-regular fa-calendar mr-2"></i> <?php echo date('F j, Y'); ?>
    </div>
</div>

<?php if ($db_error ?? false): ?>
    <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg mb-6 flex items-start">
        <i class="fa-solid fa-triangle-exclamation mt-1 mr-3 text-amber-600"></i>
        <div>
            <strong class="font-semibold block">Database Connection Error</strong>
            <span class="text-sm"><?php echo htmlspecialchars($db_error); ?></span>
            <p class="text-xs mt-1">The dashboard is currently running in UI Demo Mode without a database.</p>
        </div>
    </div>
<?php endif; ?>

<!-- Stats grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Stat Card 1 -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-100 flex items-center">
        <div class="w-14 h-14 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-2xl">
            <i class="fa-solid fa-box"></i>
        </div>
        <div class="ml-4">
            <p class="text-sm font-medium text-slate-500">Total Products</p>
            <p class="text-2xl font-bold text-slate-800">1,248</p>
        </div>
    </div>
    <!-- Stat Card 2 -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-100 flex items-center">
        <div class="w-14 h-14 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl">
            <i class="fa-solid fa-dollar-sign"></i>
        </div>
        <div class="ml-4">
            <p class="text-sm font-medium text-slate-500">Today's Sales</p>
            <p class="text-2xl font-bold text-slate-800">$4,320.50</p>
        </div>
    </div>
    <!-- Stat Card 3 -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-100 flex items-center">
        <div class="w-14 h-14 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center text-2xl">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="ml-4">
            <p class="text-sm font-medium text-slate-500">Low Stock Alerts</p>
            <p class="text-2xl font-bold text-slate-800">12</p>
        </div>
    </div>
    <!-- Stat Card 4 -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-100 flex items-center">
        <div class="w-14 h-14 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center text-2xl">
            <i class="fa-solid fa-wrench"></i>
        </div>
        <div class="ml-4">
            <p class="text-sm font-medium text-slate-500">Active Repairs</p>
            <p class="text-2xl font-bold text-slate-800">8</p>
        </div>
    </div>
</div>

<!-- Recent Activity Placeholder -->
<div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
        <h2 class="font-bold text-slate-800">Recent Transactions</h2>
        <a href="#" class="text-sm text-blue-600 font-medium hover:text-blue-800">View All</a>
    </div>
    <div class="p-6 text-center text-slate-500 py-12">
        <i class="fa-solid fa-receipt text-4xl mb-3 text-slate-300"></i>
        <p>No recent transactions to display in demo mode.</p>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
