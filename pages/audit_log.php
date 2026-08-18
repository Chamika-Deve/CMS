<?php
require_once '../includes/db.php';
require_once '../includes/header.php';

$logs = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT al.*, u.name as user_name, u.role as user_role
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.id DESC
            LIMIT 100
        ");
        $logs = $stmt->fetchAll();
    } catch (Exception $e) {}
}

?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <!-- Top Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-clock-rotate-left text-emerald-600"></i>
                <span>Activity & Audit Trail</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Full audit log of user actions, price modifications, discounts, and system events.</p>
        </div>
        
        <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-full border border-emerald-100">
                <i class="fa-solid fa-shield-halved mr-1"></i> Recent Activity Logging
            </span>
        </div>
    </div>

    <!-- Audit Logs Table Card -->
    <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7 overflow-hidden">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <h2 class="text-lg font-bold text-slate-900">Event History Log (<?php echo count($logs); ?> Events)</h2>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input 
                    type="text" 
                    id="logSearch" 
                    onkeyup="filterLogs()" 
                    placeholder="Search action, user, module..." 
                    class="pl-9 pr-4 py-2 bg-slate-50 border border-slate-200/80 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 w-64"
                >
            </div>
        </div>

        <div class="overflow-x-auto -mx-6 sm:-mx-7 px-6 sm:px-7">
            <table class="w-full text-left border-collapse" id="logsTable">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 pr-4 pl-2">Timestamp</th>
                        <th class="py-3.5 px-4">User</th>
                        <th class="py-3.5 px-4">Module</th>
                        <th class="py-3.5 px-4">Action</th>
                        <th class="py-3.5 pl-4 pr-2">Details / Change Description</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 text-xs sm:text-sm text-slate-700">
                    <?php foreach ($logs as $l): ?>
                    <tr class="hover:bg-slate-50/60 transition-colors group">
                        
                        <!-- Timestamp -->
                        <td class="py-4 pr-4 pl-2 font-mono text-slate-500 text-xs shrink-0">
                            <?php echo date('M j, Y H:i:s', strtotime($l['timestamp'])); ?>
                        </td>

                        <!-- User -->
                        <td class="py-4 px-4 font-bold text-slate-900">
                            <span class="block"><?php echo htmlspecialchars($l['user_name'] ?? 'System'); ?></span>
                            <span class="block text-[10px] text-slate-400 font-normal"><?php echo htmlspecialchars($l['user_role'] ?? 'Bot'); ?></span>
                        </td>

                        <!-- Module -->
                        <td class="py-4 px-4">
                            <span class="inline-block text-xs font-semibold px-2.5 py-0.5 rounded-lg bg-slate-100 text-slate-700">
                                <?php echo htmlspecialchars($l['module'] ?? 'System'); ?>
                            </span>
                        </td>

                        <!-- Action -->
                        <td class="py-4 px-4 font-bold text-emerald-800">
                            <?php echo htmlspecialchars($l['action']); ?>
                        </td>

                        <!-- Details -->
                        <td class="py-4 pl-4 pr-2 text-slate-600">
                            <?php echo htmlspecialchars($l['details'] ?? ''); ?>
                        </td>

                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

</div>

<script>
function filterLogs() {
    const q = document.getElementById('logSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#logsTable tbody tr');
    rows.forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
