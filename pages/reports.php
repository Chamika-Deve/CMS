<?php
require_once '../includes/db.php';
require_once '../includes/header.php';

/* -------------------------------------------------------------------------
 * CSMS - Reports & Analytics
 * Date-filterable business intelligence: KPI cards, sales trend, top
 * products, category breakdown, payment methods, low stock, repairs.
 * ---------------------------------------------------------------------- */

// ---------- Helpers -------------------------------------------------------
$currency = 'Rs.';
if (isset($pdo)) {
    try {
        $s = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol' LIMIT 1");
        $s->execute();
        $v = $s->fetchColumn();
        if ($v !== false && $v !== null && $v !== '') $currency = $v;
    } catch (\Exception $e) {}
}

function money($n, $currency) {
    return $currency . ' ' . number_format((float)$n, 2);
}
function safe_int($v) { return (int)($v ?? 0); }
function safe_float($v) { return (float)($v ?? 0); }

// ---------- Filters -------------------------------------------------------
$today = date('Y-m-d');
$range = $_GET['range'] ?? 'this_month'; // today,7d,this_month,last_month,this_year,all,custom
$from  = $_GET['from']  ?? '';
$to    = $_GET['to']    ?? '';

if ($range === 'custom' && $from !== '' && $to !== '') {
    $dateFrom  = $from . ' 00:00:00';
    $dateTo    = $to . ' 23:59:59';
    $fromLabel = date('M j, Y', strtotime($from));
    $toLabel   = date('M j, Y', strtotime($to));
} else {
    switch ($range) {
        case 'today':
            $dateFrom = $today . ' 00:00:00';
            $dateTo   = $today . ' 23:59:59';
            $fromLabel = $toLabel = date('M j, Y');
            break;
        case '7d':
            $dateFrom = date('Y-m-d 00:00:00', strtotime('-6 days'));
            $dateTo   = $today . ' 23:59:59';
            $fromLabel = date('M j', strtotime($dateFrom));
            $toLabel   = date('M j, Y');
            break;
        case 'last_month':
            $dateFrom = date('Y-m-01 00:00:00', strtotime('first day of last month'));
            $dateTo   = date('Y-m-t 23:59:59', strtotime('last day of last month'));
            $fromLabel = date('M', strtotime($dateFrom)) . ' 1, ' . date('Y', strtotime($dateFrom));
            $toLabel   = date('M', strtotime($dateTo)) . ' ' . date('t', strtotime($dateTo)) . ', ' . date('Y', strtotime($dateTo));
            break;
        case 'this_year':
            $dateFrom = date('Y-01-01 00:00:00');
            $dateTo   = $today . ' 23:59:59';
            $fromLabel = 'Jan 1, ' . date('Y');
            $toLabel   = date('M j, Y');
            break;
        case 'all':
            $dateFrom = '1970-01-01 00:00:00';
            $dateTo   = $today . ' 23:59:59';
            $fromLabel = 'Beginning';
            $toLabel   = date('M j, Y');
            break;
        case 'this_month':
        default:
            $range = 'this_month';
            $dateFrom = date('Y-m-01 00:00:00');
            $dateTo   = $today . ' 23:59:59';
            $fromLabel = date('M') . ' 1, ' . date('Y');
            $toLabel   = date('M j, Y');
            break;
    }
}

$dbOk = isset($pdo);
$errorMsg = '';
if (!$dbOk) {
    $errorMsg = $db_error ?? 'Database connection unavailable.';
}

// ---------- Queries -------------------------------------------------------
$kpi = ['revenue'=>0.0,'orders'=>0,'items'=>0,'profit'=>0.0,'tax'=>0.0,'discount'=>0.0,'avg_order'=>0.0];
$trend = $topProducts = $categoryBreakdown = $paymentBreakdown = $lowStock = [];
$repairStats = ['total'=>0,'active'=>0,'completed'=>0];

if ($dbOk) {
    try {
        // KPI totals (Completed sales only)
        $st = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) AS revenue,
                                    COALESCE(SUM(tax),0) AS tax,
                                    COALESCE(SUM(discount),0) AS discount,
                                    COUNT(*) AS orders
                             FROM sales
                             WHERE status='Completed' AND sale_date BETWEEN :from AND :to");
        $st->execute([':from'=>$dateFrom, ':to'=>$dateTo]);
        $row = $st->fetch();
        $kpi['revenue']  = safe_float($row['revenue']);
        $kpi['tax']      = safe_float($row['tax']);
        $kpi['discount'] = safe_float($row['discount']);
        $kpi['orders']   = safe_int($row['orders']);
        $kpi['avg_order'] = $kpi['orders'] > 0 ? $kpi['revenue'] / $kpi['orders'] : 0.0;

        // Items sold + gross profit (unit_price - cost_price)
        $st = $pdo->prepare("SELECT COALESCE(SUM(si.quantity),0) AS qty,
                                    COALESCE(SUM(si.quantity * (si.unit_price - p.cost_price)),0) AS profit
                             FROM sale_items si
                             JOIN sales s ON s.id = si.sale_id
                             JOIN products p ON p.id = si.product_id
                             WHERE s.status='Completed' AND s.sale_date BETWEEN :from AND :to");
        $st->execute([':from'=>$dateFrom, ':to'=>$dateTo]);
        $row = $st->fetch();
        $kpi['items']  = safe_int($row['qty']);
        $kpi['profit'] = safe_float($row['profit']);

        // Daily sales trend
        $st = $pdo->prepare("SELECT DATE(sale_date) AS d,
                                    COALESCE(SUM(total_amount),0) AS total, COUNT(*) AS cnt
                             FROM sales
                             WHERE status='Completed' AND sale_date BETWEEN :from AND :to
                             GROUP BY DATE(sale_date) ORDER BY d ASC");
        $st->execute([':from'=>$dateFrom, ':to'=>$dateTo]);
        $trend = $st->fetchAll();

        // Top selling products
        $st = $pdo->prepare("SELECT p.id, p.name, p.product_code,
                                    COALESCE(SUM(si.quantity),0) AS qty,
                                    COALESCE(SUM(si.quantity*si.unit_price),0) AS revenue
                             FROM sale_items si
                             JOIN sales s ON s.id = si.sale_id
                             JOIN products p ON p.id = si.product_id
                             WHERE s.status='Completed' AND s.sale_date BETWEEN :from AND :to
                             GROUP BY p.id, p.name, p.product_code
                             ORDER BY qty DESC, revenue DESC LIMIT 10");
        $st->execute([':from'=>$dateFrom, ':to'=>$dateTo]);
        $topProducts = $st->fetchAll();

        // Sales by category
        $st = $pdo->prepare("SELECT COALESCE(c.name,'Uncategorized') AS category,
                                    COALESCE(SUM(si.quantity*si.unit_price),0) AS revenue,
                                    COALESCE(SUM(si.quantity),0) AS qty
                             FROM sale_items si
                             JOIN sales s ON s.id = si.sale_id
                             JOIN products p ON p.id = si.product_id
                             LEFT JOIN categories c ON c.id = p.category_id
                             WHERE s.status='Completed' AND s.sale_date BETWEEN :from AND :to
                             GROUP BY c.id, c.name ORDER BY revenue DESC");
        $st->execute([':from'=>$dateFrom, ':to'=>$dateTo]);
        $categoryBreakdown = $st->fetchAll();

        // Payment methods
        $st = $pdo->prepare("SELECT payment_method, COUNT(*) AS cnt,
                                    COALESCE(SUM(total_amount),0) AS total
                             FROM sales
                             WHERE status='Completed' AND sale_date BETWEEN :from AND :to
                             GROUP BY payment_method ORDER BY total DESC");
        $st->execute([':from'=>$dateFrom, ':to'=>$dateTo]);
        $paymentBreakdown = $st->fetchAll();

        // Low stock (stock on hand = in_stock serials per product)
        $lowStock = $pdo->query("SELECT p.id, p.name, p.product_code, p.reorder_level,
                                        COALESCE(SUM(ps.status='in_stock'),0) AS stock
                                 FROM products p
                                 LEFT JOIN product_serials ps ON ps.product_id = p.id
                                 WHERE p.status='Active'
                                 GROUP BY p.id, p.name, p.product_code, p.reorder_level
                                 HAVING stock <= p.reorder_level
                                 ORDER BY stock ASC LIMIT 10")->fetchAll();

        // Repair summary
        $st = $pdo->prepare("SELECT COUNT(*) AS total,
                                    COALESCE(SUM(status NOT IN ('Completed','Closed','Cancelled')),0) AS active,
                                    COALESCE(SUM(status IN ('Completed','Closed')),0) AS done
                             FROM repair_jobs
                             WHERE received_date BETWEEN :from AND :to");
        $st->execute([':from'=>$dateFrom, ':to'=>$dateTo]);
        $r = $st->fetch();
        $repairStats = ['total'=>safe_int($r['total']),'active'=>safe_int($r['active']),'completed'=>safe_int($r['done'])];
    } catch (\Exception $e) {
        $errorMsg = 'Query error: ' . safe_error_message($e);
    }
}

// ---------- Chart prep ----------------------------------------------------
$trendLabels = $trendValues = $trendCounts = [];
foreach ($trend as $t) {
    $trendLabels[] = date('M j', strtotime($t['d']));
    $trendValues[] = (float)$t['total'];
    $trendCounts[] = (int)$t['cnt'];
}
$catLabels = array_map(fn($c) => $c['category'], $categoryBreakdown);
$catValues = array_map(fn($c) => (float)$c['revenue'], $categoryBreakdown);
$payLabels = array_map(fn($p) => $p['payment_method'], $paymentBreakdown);
$payValues = array_map(fn($p) => (float)$p['total'], $paymentBreakdown);
$payCounts = array_map(fn($p) => (int)$p['cnt'], $paymentBreakdown);

$maxTrend = $trendValues ? max($trendValues) : 0;
$maxCat   = $catValues ? max($catValues) : 0;
function barWidth($val, $max) { return $max <= 0 ? 0 : max(4, round(($val/$max)*100, 1)); }

$rangeTabs = ['today'=>'Today','7d'=>'Last 7 Days','this_month'=>'This Month',
              'last_month'=>'Last Month','this_year'=>'This Year','all'=>'All Time'];
$catColors = ['bg-blue-500','bg-emerald-500','bg-amber-500','bg-purple-500',
              'bg-rose-500','bg-cyan-500','bg-indigo-500','bg-teal-500'];
$payColors = ['bg-emerald-500','bg-blue-500','bg-amber-500','bg-purple-500'];
?>
<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 flex items-center">
            <i class="fa-solid fa-chart-pie text-blue-600 mr-3"></i> Reports &amp; Analytics
        </h1>
        <p class="text-sm text-slate-500 mt-1">
            <i class="fa-regular fa-calendar mr-1"></i>
            <?php echo htmlspecialchars($fromLabel); ?> &mdash; <?php echo htmlspecialchars($toLabel); ?>
        </p>
    </div>
    <button onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-slate-900 text-white rounded-lg text-sm font-medium hover:bg-slate-700 transition print:hidden">
        <i class="fa-solid fa-print mr-2"></i> Print Report
    </button>
</div>

<?php if ($errorMsg): ?>
<div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 flex items-start print:hidden">
    <i class="fa-solid fa-triangle-exclamation mt-1 mr-3 text-red-600"></i>
    <div>
        <strong class="font-semibold block">Report data unavailable</strong>
        <span class="text-sm"><?php echo htmlspecialchars($errorMsg); ?></span>
    </div>
</div>
<?php endif; ?>

<!-- Filter bar -->
<form method="GET" class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-6 print:hidden">
    <div class="flex flex-wrap items-center gap-2">
        <?php foreach ($rangeTabs as $key => $label): ?>
            <button type="submit" name="range" value="<?php echo $key; ?>"
                class="px-3 py-1.5 rounded-lg text-sm font-medium transition border
                <?php echo $range === $key ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'; ?>">
                <?php echo $label; ?>
            </button>
        <?php endforeach; ?>
    </div>
    <div class="flex flex-wrap items-end gap-3 mt-4 pt-4 border-t border-slate-100">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">From</label>
            <input type="date" name="from" value="<?php echo $range === 'custom' ? htmlspecialchars($from) : ''; ?>"
                   class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">To</label>
            <input type="date" name="to" value="<?php echo $range === 'custom' ? htmlspecialchars($to) : ''; ?>"
                   class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <button type="submit" name="range" value="custom"
                class="px-4 py-1.5 bg-slate-800 text-white rounded-lg text-sm font-medium hover:bg-slate-700">
            <i class="fa-solid fa-filter mr-1"></i> Apply Custom
        </button>
        <a href="?range=this_month" class="px-4 py-1.5 text-slate-500 hover:text-slate-700 text-sm font-medium">Reset</a>
    </div>
</form>

<!-- KPI cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-500">Total Revenue</p>
            <span class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-money-bill-trend-up"></i></span>
        </div>
        <p class="text-2xl font-bold text-slate-900 mt-3"><?php echo money($kpi['revenue'], $currency); ?></p>
        <p class="text-xs text-slate-400 mt-1"><?php echo $kpi['orders']; ?> orders</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-500">Gross Profit</p>
            <span class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-chart-line"></i></span>
        </div>
        <p class="text-2xl font-bold text-slate-900 mt-3"><?php echo money($kpi['profit'], $currency); ?></p>
        <p class="text-xs text-slate-400 mt-1">sell &minus; cost</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-500">Items Sold</p>
            <span class="w-10 h-10 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center"><i class="fa-solid fa-boxes-stacked"></i></span>
        </div>
        <p class="text-2xl font-bold text-slate-900 mt-3"><?php echo number_format($kpi['items']); ?></p>
        <p class="text-xs text-slate-400 mt-1">Avg <?php echo money($kpi['avg_order'], $currency); ?> / order</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-500">Tax / Discount</p>
            <span class="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center"><i class="fa-solid fa-percent"></i></span>
        </div>
        <p class="text-2xl font-bold text-slate-900 mt-3"><?php echo money($kpi['tax'], $currency); ?></p>
        <p class="text-xs text-slate-400 mt-1">&minus; <?php echo money($kpi['discount'], $currency); ?> discount</p>
    </div>
</div>
<!-- Charts row -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Sales trend -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="font-bold text-slate-800"><i class="fa-solid fa-chart-column text-blue-600 mr-2"></i>Sales Trend</h2>
            <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Daily revenue</span>
        </div>
        <?php if ($trend): ?>
        <div class="flex items-end justify-between gap-1 h-56">
            <?php foreach ($trend as $i => $t):
                $h = barWidth((float)$t['total'], $maxTrend); ?>
                <div class="flex-1 flex flex-col items-center justify-end group h-full relative">
                    <div class="w-full max-w-[42px] bg-gradient-to-t from-blue-600 to-blue-400 rounded-t-md hover:from-blue-700 hover:to-blue-500 transition relative"
                         style="height: <?php echo $h; ?>%">
                        <div class="opacity-0 group-hover:opacity-100 transition absolute -top-12 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-xs rounded-lg px-3 py-1.5 whitespace-nowrap z-10 pointer-events-none shadow-lg">
                            <?php echo money($t['total'], $currency); ?><br>
                            <span class="text-slate-300"><?php echo (int)$t['cnt']; ?> orders</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="flex justify-between gap-1 mt-2">
            <?php foreach ($trendLabels as $lbl): ?>
                <span class="flex-1 text-center text-[10px] text-slate-400 truncate"><?php echo htmlspecialchars($lbl); ?></span>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="h-56 flex flex-col items-center justify-center text-slate-400">
            <i class="fa-solid fa-chart-column text-4xl mb-3 text-slate-200"></i>
            <p class="text-sm">No sales recorded in this period.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Payment methods (donut via conic-gradient) -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h2 class="font-bold text-slate-800 mb-6"><i class="fa-solid fa-credit-card text-emerald-600 mr-2"></i>Payment Methods</h2>
        <?php if ($paymentBreakdown):
            $totalPay = array_sum($payValues);
            $colors = ['#10b981','#3b82f6','#f59e0b','#8b5cf6','#ef4444'];
            $segments = [];
            $cursor = 0;
            foreach ($payValues as $i => $v) {
                $pct = $totalPay > 0 ? ($v / $totalPay) * 100 : 0;
                $segments[] = $colors[$i % count($colors)] . ' ' . round($cursor,2) . '% ' . round($cursor+$pct,2) . '%';
                $cursor += $pct;
            }
            $conic = implode(', ', $segments);
        ?>
        <div class="flex items-center justify-center mb-5">
            <div class="relative w-40 h-40 rounded-full" style="background: conic-gradient(<?php echo $conic; ?>);">
                <div class="absolute inset-4 bg-white rounded-full flex flex-col items-center justify-center">
                    <span class="text-xs text-slate-400">Total</span>
                    <span class="text-sm font-bold text-slate-800 text-center px-2"><?php echo money($totalPay, $currency); ?></span>
                </div>
            </div>
        </div>
        <ul class="space-y-2">
            <?php foreach ($paymentBreakdown as $i => $p): ?>
            <li class="flex items-center justify-between text-sm">
                <span class="flex items-center">
                    <span class="w-3 h-3 rounded-sm mr-2" style="background:<?php echo $colors[$i % count($colors)]; ?>"></span>
                    <?php echo htmlspecialchars($p['payment_method']); ?>
                    <span class="text-slate-400 ml-1">(<?php echo (int)$p['cnt']; ?>)</span>
                </span>
                <span class="font-semibold text-slate-700"><?php echo money($p['total'], $currency); ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <div class="h-40 flex flex-col items-center justify-center text-slate-400">
            <i class="fa-solid fa-credit-card text-4xl mb-3 text-slate-200"></i>
            <p class="text-sm">No payments in this period.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
<!-- Category + Top products row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Sales by category -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h2 class="font-bold text-slate-800 mb-5"><i class="fa-solid fa-layer-group text-purple-600 mr-2"></i>Sales by Category</h2>
        <?php if ($categoryBreakdown): ?>
        <div class="space-y-4">
            <?php foreach ($categoryBreakdown as $i => $c):
                $w = barWidth((float)$c['revenue'], $maxCat);
                $color = $catColors[$i % count($catColors)]; ?>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="font-medium text-slate-700"><?php echo htmlspecialchars($c['category']); ?></span>
                        <span class="text-slate-500"><?php echo money($c['revenue'], $currency); ?> &middot; <?php echo (int)$c['qty']; ?> sold</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2.5">
                        <div class="<?php echo $color; ?> h-2.5 rounded-full transition-all" style="width: <?php echo $w; ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-sm text-slate-400 py-8 text-center">No category sales in this period.</p>
        <?php endif; ?>
    </div>

    <!-- Top products -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h2 class="font-bold text-slate-800 mb-5"><i class="fa-solid fa-trophy text-amber-500 mr-2"></i>Top Selling Products</h2>
        <?php if ($topProducts): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-slate-400 border-b border-slate-100">
                        <th class="pb-2 font-medium">#</th>
                        <th class="pb-2 font-medium">Product</th>
                        <th class="pb-2 font-medium text-center">Qty</th>
                        <th class="pb-2 font-medium text-right">Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($topProducts as $i => $p): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="py-2.5 text-slate-400"><?php echo $i + 1; ?></td>
                        <td class="py-2.5">
                            <span class="font-medium text-slate-700"><?php echo htmlspecialchars($p['name']); ?></span>
                            <span class="block text-xs text-slate-400"><?php echo htmlspecialchars($p['product_code']); ?></span>
                        </td>
                        <td class="py-2.5 text-center text-slate-600"><?php echo (int)$p['qty']; ?></td>
                        <td class="py-2.5 text-right font-semibold text-slate-800"><?php echo money($p['revenue'], $currency); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-sm text-slate-400 py-8 text-center">No products sold in this period.</p>
        <?php endif; ?>
    </div>
</div>
<!-- Operations row: low stock + repairs + recent invoices -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Low stock -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h2 class="font-bold text-slate-800 mb-4 flex items-center">
            <i class="fa-solid fa-triangle-exclamation text-amber-500 mr-2"></i> Low Stock Alerts
        </h2>
        <?php if ($lowStock): ?>
        <ul class="space-y-3">
            <?php foreach ($lowStock as $p):
                $out = (int)$p['stock'] <= 0; ?>
            <li class="flex items-center justify-between text-sm">
                <div class="min-w-0">
                    <p class="font-medium text-slate-700 truncate"><?php echo htmlspecialchars($p['name']); ?></p>
                    <p class="text-xs text-slate-400"><?php echo htmlspecialchars($p['product_code']); ?></p>
                </div>
                <span class="ml-3 shrink-0 px-2 py-0.5 rounded-full text-xs font-semibold
                    <?php echo $out ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'; ?>">
                    <?php echo (int)$p['stock']; ?> / <?php echo (int)$p['reorder_level']; ?>
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="text-sm text-slate-400 py-6 text-center"><i class="fa-regular fa-circle-check text-emerald-400 text-2xl mb-2 block"></i> All products are well stocked.</p>
        <?php endif; ?>
    </div>

    <!-- Repair summary -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h2 class="font-bold text-slate-800 mb-4 flex items-center">
            <i class="fa-solid fa-wrench text-slate-600 mr-2"></i> Repair Jobs
        </h2>
        <div class="grid grid-cols-3 gap-3 text-center mb-4">
            <div class="bg-slate-50 rounded-lg p-3">
                <p class="text-2xl font-bold text-slate-800"><?php echo $repairStats['total']; ?></p>
                <p class="text-xs text-slate-500">Received</p>
            </div>
            <div class="bg-blue-50 rounded-lg p-3">
                <p class="text-2xl font-bold text-blue-700"><?php echo $repairStats['active']; ?></p>
                <p class="text-xs text-slate-500">Active</p>
            </div>
            <div class="bg-emerald-50 rounded-lg p-3">
                <p class="text-2xl font-bold text-emerald-700"><?php echo $repairStats['completed']; ?></p>
                <p class="text-xs text-slate-500">Completed</p>
            </div>
        </div>
        <p class="text-xs text-slate-400">Repair activity within the selected date range.</p>
    </div>

    <!-- Recent invoices -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h2 class="font-bold text-slate-800 mb-4 flex items-center">
            <i class="fa-solid fa-receipt text-blue-600 mr-2"></i> Recent Invoices
        </h2>
        <?php
        $recent = [];
        if ($dbOk) {
            try {
                $st = $pdo->prepare("SELECT invoice_no, sale_date, total_amount, payment_method, status
                                     FROM sales
                                     WHERE sale_date BETWEEN :from AND :to
                                     ORDER BY sale_date DESC LIMIT 6");
                $st->execute([':from'=>$dateFrom, ':to'=>$dateTo]);
                $recent = $st->fetchAll();
            } catch (\Exception $e) {}
        }
        ?>
        <?php if ($recent): ?>
        <ul class="space-y-2.5">
            <?php foreach ($recent as $inv): ?>
            <li class="flex items-center justify-between text-sm">
                <div class="min-w-0">
                    <p class="font-mono text-xs text-slate-600 truncate"><?php echo htmlspecialchars($inv['invoice_no']); ?></p>
                    <p class="text-xs text-slate-400"><?php echo date('M j, g:i A', strtotime($inv['sale_date'])); ?> &middot; <?php echo htmlspecialchars($inv['payment_method']); ?></p>
                </div>
                <span class="font-semibold text-slate-800 shrink-0 ml-2"><?php echo money($inv['total_amount'], $currency); ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="text-sm text-slate-400 py-6 text-center">No invoices in this period.</p>
        <?php endif; ?>
    </div>
</div>

<script>
// Expose chart data for any future JS charting; kept lightweight & dependency-free.
window.CSMS_REPORTS = {
    trend: { labels: <?php echo json_encode($trendLabels); ?>, values: <?php echo json_encode($trendValues); ?>, counts: <?php echo json_encode($trendCounts); ?> },
    categories: { labels: <?php echo json_encode($catLabels); ?>, values: <?php echo json_encode($catValues); ?> },
    payments: { labels: <?php echo json_encode($payLabels); ?>, values: <?php echo json_encode($payValues); ?>, counts: <?php echo json_encode($payCounts); ?> }
};
</script>
<?php require_once '../includes/footer.php'; ?>
