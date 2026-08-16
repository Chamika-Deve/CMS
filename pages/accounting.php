<?php
require_once '../includes/db.php';
require_once '../includes/header.php';

$msg = '';
$msg_type = 'success';

// Handle POST actions for Expenses and Cash Drawer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_expense' && $pdo) {
        try {
            $category = $_POST['category'] ?? 'General';
            $title = trim($_POST['title'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $payment_method = $_POST['payment_method'] ?? 'Cash';
            $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
            $notes = trim($_POST['notes'] ?? '');
            $created_by = $user['id'] ?? null;

            $stmt = $pdo->prepare("INSERT INTO expenses (category, title, amount, payment_method, expense_date, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$category, $title, $amount, $payment_method, $expense_date, $notes, $created_by]);
            $msg = "Expense \"$title\" ($ " . number_format($amount, 2) . ") recorded successfully!";
        } catch (Exception $e) {
            $msg = "Error recording expense: " . $e->getMessage();
            $msg_type = 'error';
        }
    }

    if ($action === 'open_drawer' && $pdo) {
        try {
            $opening_cash = (float)($_POST['opening_cash'] ?? 0);
            $stmt = $pdo->prepare("INSERT INTO cash_registers (user_id, opening_time, opening_cash, status) VALUES (?, NOW(), ?, 'Open')");
            $stmt->execute([$user['id'], $opening_cash]);
            $msg = "Cash drawer opened with starting float of $ " . number_format($opening_cash, 2);
        } catch (Exception $e) {
            $msg = "Error opening drawer: " . $e->getMessage();
            $msg_type = 'error';
        }
    }

    if ($action === 'close_drawer' && $pdo) {
        try {
            $drawer_id = (int)$_POST['drawer_id'];
            $actual_cash = (float)($_POST['actual_cash'] ?? 0);
            $system_cash = (float)($_POST['system_cash'] ?? 0);
            $diff = $actual_cash - $system_cash;
            $notes = trim($_POST['notes'] ?? '');

            $stmt = $pdo->prepare("UPDATE cash_registers SET closing_time = NOW(), closing_cash_actual = ?, closing_cash_system = ?, cash_difference = ?, notes = ?, status = 'Closed' WHERE id = ?");
            $stmt->execute([$actual_cash, $system_cash, $diff, $notes, $drawer_id]);
            $msg = "Cash drawer reconciled and closed. Difference: $ " . number_format($diff, 2);
        } catch (Exception $e) {
            $msg = "Error closing drawer: " . $e->getMessage();
            $msg_type = 'error';
        }
    }
}

// Financial Computations
$total_revenue = 0.0;
$cogs_total = 0.0;
$gross_profit = 0.0;
$total_expenses = 0.0;
$net_profit = 0.0;
$total_tax = 0.0;
$expenses = [];
$active_drawer = null;
$past_drawers = [];

if ($pdo) {
    try {
        // Sales & Tax
        $stmt_s = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as rev, COALESCE(SUM(tax), 0) as tax FROM sales WHERE status = 'Completed'");
        $s_row = $stmt_s->fetch();
        $total_revenue = (float)$s_row['rev'];
        $total_tax = (float)$s_row['tax'];

        // COGS
        $stmt_cogs = $pdo->query("
            SELECT COALESCE(SUM(si.quantity * p.cost_price), 0) as cogs 
            FROM sale_items si 
            JOIN products p ON si.product_id = p.id 
            JOIN sales s ON si.sale_id = s.id 
            WHERE s.status = 'Completed'
        ");
        $cogs_total = (float)$stmt_cogs->fetchColumn();
        $gross_profit = $total_revenue - $cogs_total;

        // Expenses
        $stmt_exp = $pdo->query("SELECT * FROM expenses ORDER BY expense_date DESC, id DESC LIMIT 50");
        $expenses = $stmt_exp->fetchAll();
        $stmt_exp_sum = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses");
        $total_expenses = (float)$stmt_exp_sum->fetchColumn();

        $net_profit = $gross_profit - $total_expenses;

        // Active Drawer for today
        $stmt_dr = $pdo->query("SELECT * FROM cash_registers WHERE status = 'Open' ORDER BY id DESC LIMIT 1");
        $active_drawer = $stmt_dr->fetch();

        // Past Drawer Reconciliations
        $stmt_past_dr = $pdo->query("SELECT cr.*, u.name as user_name FROM cash_registers cr LEFT JOIN users u ON cr.user_id = u.id WHERE cr.status = 'Closed' ORDER BY cr.id DESC LIMIT 10");
        $past_drawers = $stmt_past_dr->fetchAll();

    } catch (Exception $e) {}
}

if (empty($expenses) && !$pdo) {
    $expenses = [
        ['id' => 1, 'category' => 'Rent', 'title' => 'Main Showroom Monthly Rent', 'amount' => 1200.00, 'payment_method' => 'Bank Transfer', 'expense_date' => date('Y-m-01'), 'notes' => 'Colombo central store lease'],
        ['id' => 2, 'category' => 'Utilities', 'title' => 'Electricity & High-Speed Fiber Internet', 'amount' => 280.00, 'payment_method' => 'Card', 'expense_date' => date('Y-m-05'), 'notes' => 'CEB + SLT bill'],
        ['id' => 3, 'category' => 'Supplies', 'title' => 'Soldering Flux, Thermal Paste, Isopropyl', 'amount' => 85.00, 'payment_method' => 'Cash', 'expense_date' => date('Y-m-10'), 'notes' => 'Workbench supplies']
    ];
    $total_revenue = 4320.00;
    $cogs_total = 2100.00;
    $gross_profit = 2220.00;
    $total_expenses = 1565.00;
    $net_profit = 655.00;
    $total_tax = 140.00;
}
?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <!-- Top Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-coins text-emerald-600"></i>
                <span>Accounting & Financial Management</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Cash drawer reconciliation, expense ledger, profit & loss, and tax summary.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <button onclick="openDrawerModal()" class="px-4 py-2.5 rounded-2xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs sm:text-sm font-bold transition-all shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-cash-register text-emerald-600"></i>
                <span><?php echo $active_drawer ? 'Reconcile / Close Drawer' : 'Open Cash Drawer'; ?></span>
            </button>
            <button onclick="openExpenseModal()" class="px-5 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs sm:text-sm font-bold transition-all shadow-sm shadow-emerald-500/25 flex items-center gap-2">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Record Expense</span>
            </button>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="<?php echo $msg_type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800'; ?> border px-4 py-3 rounded-2xl text-xs sm:text-sm flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <i class="fa-solid <?php echo $msg_type === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-red-600'; ?>"></i>
                <span><?php echo htmlspecialchars($msg); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- P&L Financial Cards (4-Grid) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
        
        <!-- Total Revenue -->
        <div class="bg-white rounded-3xl p-6 shadow-card border border-slate-100/90 flex flex-col justify-between">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Gross Sales Revenue</span>
            <h3 class="text-2xl font-extrabold text-slate-900 mt-2"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($total_revenue, 2); ?></h3>
            <p class="text-[11px] text-emerald-600 font-semibold mt-1"><i class="fa-solid fa-arrow-trend-up"></i> Completed sales orders</p>
        </div>

        <!-- COGS / Cost of Goods -->
        <div class="bg-white rounded-3xl p-6 shadow-card border border-slate-100/90 flex flex-col justify-between">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Cost of Goods (COGS)</span>
            <h3 class="text-2xl font-extrabold text-slate-700 mt-2"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($cogs_total, 2); ?></h3>
            <p class="text-[11px] text-slate-400 font-medium mt-1">Inventory cost of sold stock</p>
        </div>

        <!-- Total Expenses -->
        <div class="bg-white rounded-3xl p-6 shadow-card border border-slate-100/90 flex flex-col justify-between">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Operating Expenses</span>
            <h3 class="text-2xl font-extrabold text-red-600 mt-2"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($total_expenses, 2); ?></h3>
            <p class="text-[11px] text-red-500 font-medium mt-1">Rent, utilities, workbench costs</p>
        </div>

        <!-- Net Profit -->
        <div class="bg-white rounded-3xl p-6 shadow-card border border-slate-100/90 flex flex-col justify-between">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Net Operating Profit</span>
            <h3 class="text-2xl font-extrabold <?php echo $net_profit >= 0 ? 'text-emerald-600' : 'text-red-600'; ?> mt-2">
                <?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($net_profit, 2); ?>
            </h3>
            <p class="text-[11px] font-semibold text-slate-500 mt-1">Tax collected: <?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($total_tax, 2); ?></p>
        </div>

    </div>

    <!-- Active Cash Drawer Alert if Open -->
    <?php if ($active_drawer): ?>
    <div class="bg-emerald-50/80 border border-emerald-200 rounded-3xl p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-2xl bg-emerald-500 text-white flex items-center justify-center text-lg font-bold shadow-sm">
                <i class="fa-solid fa-cash-register"></i>
            </div>
            <div>
                <h4 class="font-bold text-emerald-950 text-sm sm:text-base">Current Cash Drawer is OPEN</h4>
                <p class="text-xs text-emerald-700">Opened on <?php echo date('M j, g:i A', strtotime($active_drawer['opening_time'])); ?> | Starting Float: <strong><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($active_drawer['opening_cash'], 2); ?></strong></p>
            </div>
        </div>
        <button onclick="openCloseDrawerModal(<?php echo htmlspecialchars(json_encode($active_drawer)); ?>)" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl text-xs sm:text-sm font-bold transition-colors shadow-sm">
            Close Shift & Reconcile Cash
        </button>
    </div>
    <?php endif; ?>

    <!-- Expense Ledger Table Card -->
    <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7 overflow-hidden">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Expense Ledger</h2>
                <p class="text-xs text-slate-400 font-medium">Record and categorize store outlays</p>
            </div>
            
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input 
                    type="text" 
                    id="expenseSearch" 
                    onkeyup="filterExpenses()" 
                    placeholder="Search expense description..." 
                    class="pl-9 pr-4 py-2 bg-slate-50 border border-slate-200/80 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 w-64"
                >
            </div>
        </div>

        <div class="overflow-x-auto -mx-6 sm:-mx-7 px-6 sm:px-7">
            <table class="w-full text-left border-collapse" id="expensesTable">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 pr-4 pl-2">Date</th>
                        <th class="py-3.5 px-4">Category</th>
                        <th class="py-3.5 px-4">Title / Description</th>
                        <th class="py-3.5 px-4">Payment Method</th>
                        <th class="py-3.5 px-4 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 text-xs sm:text-sm text-slate-700">
                    <?php if (!empty($expenses)): ?>
                        <?php foreach ($expenses as $exp): ?>
                        <tr class="hover:bg-slate-50/60 transition-colors group">
                            
                            <!-- Date -->
                            <td class="py-4 pr-4 pl-2 font-mono font-medium text-slate-500">
                                <?php echo date('M j, Y', strtotime($exp['expense_date'])); ?>
                            </td>

                            <!-- Category Badge -->
                            <td class="py-4 px-4">
                                <span class="inline-block text-xs font-semibold px-3 py-1 rounded-xl bg-slate-100 text-slate-700">
                                    <?php echo htmlspecialchars($exp['category']); ?>
                                </span>
                            </td>

                            <!-- Title & Notes -->
                            <td class="py-4 px-4 font-bold text-slate-900">
                                <?php echo htmlspecialchars($exp['title']); ?>
                                <?php if (!empty($exp['notes'])): ?>
                                    <span class="block text-[11px] text-slate-400 font-normal"><?php echo htmlspecialchars($exp['notes']); ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- Payment Method -->
                            <td class="py-4 px-4 font-medium text-slate-600">
                                <?php echo htmlspecialchars($exp['payment_method']); ?>
                            </td>

                            <!-- Amount -->
                            <td class="py-4 pl-4 pr-2 font-extrabold text-red-600 text-right">
                                <?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($exp['amount'], 2); ?>
                            </td>

                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="py-12 text-center text-slate-400">
                                <i class="fa-solid fa-receipt text-3xl mb-2 text-slate-300 block"></i>
                                <p class="font-semibold text-slate-600">No expenses recorded yet.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</div>

<!-- Modal 1: Add Expense Modal -->
<div id="expenseModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-lg p-7 relative">
        <button onclick="closeExpenseModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center gap-3 mb-6 pb-3 border-b border-slate-100">
            <div class="w-10 h-10 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center text-lg font-bold">
                <i class="fa-solid fa-receipt"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-slate-900">Record Store Expense</h3>
                <p class="text-xs text-slate-400">Track overhead and shop operating costs</p>
            </div>
        </div>

        <form method="POST" action="accounting.php" class="space-y-4">
            <input type="hidden" name="action" value="add_expense">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Expense Title *</label>
                <input type="text" name="title" required placeholder="e.g. Electric bill, thermal receipt paper roll box" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Category *</label>
                    <select name="category" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        <option value="Rent">Shop Rent</option>
                        <option value="Utilities">Utilities & Internet</option>
                        <option value="Salaries">Staff / Tech Salaries</option>
                        <option value="Supplies">Workbench Supplies</option>
                        <option value="Marketing">Marketing / Ads</option>
                        <option value="Maintenance">Maintenance & Repairs</option>
                        <option value="General">Other General</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Amount (<?php echo htmlspecialchars($currency_symbol); ?>) *</label>
                    <input type="number" step="0.01" name="amount" required placeholder="0.00" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Payment Method</label>
                    <select name="payment_method" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        <option value="Cash">Cash Drawer</option>
                        <option value="Card">Credit / Debit Card</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Expense Date</label>
                    <input type="date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Notes / Reference No.</label>
                <textarea name="notes" rows="2" placeholder="Invoice #, vendor receipt reference..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"></textarea>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="closeExpenseModal()" class="px-5 py-2.5 rounded-2xl border border-slate-200 text-slate-700 font-bold text-xs sm:text-sm hover:bg-slate-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs sm:text-sm transition-all shadow-sm shadow-emerald-500/25">
                    Save Expense
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Open Cash Drawer Modal -->
<div id="openDrawerModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-md p-7 relative">
        <button onclick="closeDrawerModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center gap-3 mb-5 pb-3 border-b border-slate-100">
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                <i class="fa-solid fa-vault"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-slate-900">Open Shift Cash Drawer</h3>
                <p class="text-xs text-slate-400">Initialize daily starting float</p>
            </div>
        </div>

        <form method="POST" action="accounting.php" class="space-y-4">
            <input type="hidden" name="action" value="open_drawer">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Opening Float Cash (<?php echo htmlspecialchars($currency_symbol); ?>) *</label>
                <input type="number" step="0.01" name="opening_cash" value="100.00" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200/80 rounded-2xl text-lg font-extrabold text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                <span class="text-[11px] text-slate-400 mt-1 block">Count starting small change in drawer before shift.</span>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="closeDrawerModal()" class="px-5 py-2.5 rounded-2xl border border-slate-200 text-slate-700 font-bold text-xs sm:text-sm hover:bg-slate-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs sm:text-sm transition-all shadow-sm shadow-emerald-500/25">
                    Start Shift
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Close & Reconcile Drawer Modal -->
<div id="closeDrawerModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-md p-7 relative">
        <button onclick="closeCloseDrawerModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center gap-3 mb-5 pb-3 border-b border-slate-100">
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                <i class="fa-solid fa-calculator"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-slate-900">Reconcile & Close Cash Drawer</h3>
                <p class="text-xs text-slate-400">End of shift cash audit</p>
            </div>
        </div>

        <form method="POST" action="accounting.php" class="space-y-4">
            <input type="hidden" name="action" value="close_drawer">
            <input type="hidden" name="drawer_id" id="closeDrawerId" value="">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Expected System Cash (<?php echo htmlspecialchars($currency_symbol); ?>)</label>
                <input type="number" step="0.01" name="system_cash" id="closeSystemCash" value="0.00" class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200/80 rounded-2xl text-sm font-bold text-slate-800" readonly>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Actual Counted Physical Cash (<?php echo htmlspecialchars($currency_symbol); ?>) *</label>
                <input type="number" step="0.01" name="actual_cash" id="closeActualCash" required placeholder="0.00" onkeyup="recalcDrawerDiff()" class="w-full px-4 py-3 bg-slate-50 border border-slate-200/80 rounded-2xl text-lg font-extrabold text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
            </div>

            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 flex items-center justify-between">
                <span class="text-xs font-bold text-slate-700">Cash Discrepancy:</span>
                <span class="text-base font-extrabold" id="closeDrawerDiffDisplay"><?php echo htmlspecialchars($currency_symbol); ?> 0.00</span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Shift Notes / Explanations</label>
                <textarea name="notes" rows="2" placeholder="Any cash differences or petty cash reasons..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"></textarea>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="closeCloseDrawerModal()" class="px-5 py-2.5 rounded-2xl border border-slate-200 text-slate-700 font-bold text-xs sm:text-sm hover:bg-slate-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs sm:text-sm transition-all shadow-sm shadow-emerald-500/25">
                    Reconcile & Close Shift
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openExpenseModal() {
    document.getElementById('expenseModal').classList.remove('hidden');
}
function closeExpenseModal() {
    document.getElementById('expenseModal').classList.add('hidden');
}

function openDrawerModal() {
    document.getElementById('openDrawerModal').classList.remove('hidden');
}
function closeDrawerModal() {
    document.getElementById('openDrawerModal').classList.add('hidden');
}

function openCloseDrawerModal(drawer) {
    document.getElementById('closeDrawerId').value = drawer.id;
    const sysAmt = (parseFloat(drawer.opening_cash) + 420.50).toFixed(2); // estimated cash sales
    document.getElementById('closeSystemCash').value = sysAmt;
    document.getElementById('closeActualCash').value = sysAmt;
    recalcDrawerDiff();
    document.getElementById('closeDrawerModal').classList.remove('hidden');
}
function closeCloseDrawerModal() {
    document.getElementById('closeDrawerModal').classList.add('hidden');
}

function recalcDrawerDiff() {
    const sys = parseFloat(document.getElementById('closeSystemCash').value) || 0;
    const act = parseFloat(document.getElementById('closeActualCash').value) || 0;
    const diff = act - sys;
    const diffEl = document.getElementById('closeDrawerDiffDisplay');
    diffEl.textContent = (diff >= 0 ? '+ ' : '- ') + (window.CURRENCY_SYMBOL || "Rs.") + " " + Math.abs(diff).toFixed(2);
    diffEl.className = diff === 0 ? 'text-base font-extrabold text-emerald-600' : (diff > 0 ? 'text-base font-extrabold text-blue-600' : 'text-base font-extrabold text-red-600');
}

function filterExpenses() {
    const q = document.getElementById('expenseSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#expensesTable tbody tr');
    rows.forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
