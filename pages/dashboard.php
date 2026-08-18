<?php
require_once '../includes/db.php';
require_once '../includes/header.php';

// System Currency Symbol
$currency_symbol = 'Rs.';
$total_sales_amount = 0.0;
$total_revenue_amount = 0.0;
$low_stock_count = 0;
$out_of_stock_count = 0;

// Dynamic 7-day Sales Trend (Past 7 Days)
$days_labels = [];
$daily_sales_map = [];
for ($i = 6; $i >= 0; $i--) {
    $date_key = date('Y-m-d', strtotime("-$i days"));
    $day_short = date('D', strtotime("-$i days")); // Sun, Mon, Tue, etc.
    $days_labels[] = $day_short;
    $daily_sales_map[$date_key] = 0.0;
}

$peak_day_name = '';
$peak_sales_val = 0.0;
$low_stock_products = [];

if ($pdo) {
    try {
        // 1. Fetch currency symbol from settings
        $stmt_curr = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol' LIMIT 1");
        $curr_row = $stmt_curr->fetch();
        if ($curr_row && !empty($curr_row['setting_value'])) {
            $currency_symbol = $curr_row['setting_value'];
        }

        // 2. Real Total Sales (Completed)
        $stmt_sales = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE status = 'Completed'");
        $total_sales_amount = (float)$stmt_sales->fetchColumn();

        // 3. Real Total Revenue / Gross Profit (Completed sales)
        $stmt_rev = $pdo->query("
            SELECT COALESCE(SUM(si.quantity * (si.unit_price - COALESCE(p.cost_price, 0))), 0) as profit 
            FROM sale_items si 
            JOIN products p ON si.product_id = p.id 
            JOIN sales s ON si.sale_id = s.id 
            WHERE s.status = 'Completed'
        ");
        $profit_val = (float)$stmt_rev->fetchColumn();
        if ($profit_val > 0) {
            $total_revenue_amount = $profit_val;
        } else {
            // Fallback to total sales if profit is 0
            $total_revenue_amount = $total_sales_amount;
        }

        // 4. Real Low Stock Products Count (in_stock serials <= reorder_level AND in_stock > 0)
        $stmt_low = $pdo->query("
            SELECT COUNT(*) FROM products p 
            LEFT JOIN (
                SELECT product_id, COUNT(*) as cnt 
                FROM product_serials 
                WHERE status = 'in_stock' 
                GROUP BY product_id
            ) s ON p.id = s.product_id 
            WHERE p.status = 'Active' AND COALESCE(s.cnt, 0) <= p.reorder_level AND COALESCE(s.cnt, 0) > 0
        ");
        $low_stock_count = (int)$stmt_low->fetchColumn();

        // 5. Real Out of Stock Products Count (in_stock serials = 0)
        $stmt_out = $pdo->query("
            SELECT COUNT(*) FROM products p 
            LEFT JOIN (
                SELECT product_id, COUNT(*) as cnt 
                FROM product_serials 
                WHERE status = 'in_stock' 
                GROUP BY product_id
            ) s ON p.id = s.product_id 
            WHERE p.status = 'Active' AND COALESCE(s.cnt, 0) = 0
        ");
        $out_of_stock_count = (int)$stmt_out->fetchColumn();

        // 6. Real Daily Sales for the Past 7 Days
        $start_date = date('Y-m-d 00:00:00', strtotime("-6 days"));
        $stmt_trend = $pdo->prepare("
            SELECT DATE(sale_date) as sdate, COALESCE(SUM(total_amount), 0) as daily_total
            FROM sales 
            WHERE status = 'Completed' AND sale_date >= ?
            GROUP BY DATE(sale_date)
        ");
        $stmt_trend->execute([$start_date]);
        while ($row = $stmt_trend->fetch()) {
            if (isset($daily_sales_map[$row['sdate']])) {
                $daily_sales_map[$row['sdate']] = (float)$row['daily_total'];
            }
        }

        // Calculate peak day
        foreach ($daily_sales_map as $date_k => $amt) {
            if ($amt >= $peak_sales_val) {
                $peak_sales_val = $amt;
                $peak_day_name = date('D', strtotime($date_k));
            }
        }

        // 7. Real Low Stock Items Table List
        $stmt_items = $pdo->query("
            SELECT p.id, p.name, p.product_code, p.cost_price, p.selling_price, p.unit, p.reorder_level, p.image,
                   c.name as category_name, b.name as brand_name,
                   COALESCE(s.stock_count, 0) as current_qty
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN (
                SELECT product_id, COUNT(*) as stock_count 
                FROM product_serials 
                WHERE status = 'in_stock' 
                GROUP BY product_id
            ) s ON p.id = s.product_id
            WHERE p.status = 'Active' AND COALESCE(s.stock_count, 0) <= p.reorder_level
            ORDER BY current_qty ASC, p.name ASC
            LIMIT 15
        ");
        $low_stock_products = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    } catch (\Exception $e) {
        $db_error = safe_error_message($e);
    }
}

// Prepare values for chart javascript
$chart_series = array_values($daily_sales_map);
?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <!-- Top Row: Left 2x2 Metric Cards Grid + Right Weekly Sales Chart -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
        
        <!-- Left: 2x2 Stat Cards (5 cols on lg) -->
        <div class="lg:col-span-5 grid grid-cols-2 gap-4 sm:gap-5">
            
            <!-- Stat Card 1: Total Sales -->
            <div class="bg-white rounded-3xl p-6 shadow-card border border-slate-100/90 flex flex-col justify-between hover:shadow-soft transition-all duration-200 group">
                <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl mb-4 group-hover:scale-105 transition-transform">
                    <i class="fa-solid fa-dollar-sign"></i>
                </div>
                <div>
                    <h3 class="text-xl sm:text-2xl lg:text-3xl font-extrabold text-slate-900 tracking-tight mb-1">
                        <?php echo htmlspecialchars($currency_symbol) . ' ' . number_format($total_sales_amount, 2); ?>
                    </h3>
                    <p class="text-xs sm:text-sm font-medium text-slate-400">Total Sales</p>
                </div>
            </div>

            <!-- Stat Card 2: Total Revenue -->
            <div class="bg-white rounded-3xl p-6 shadow-card border border-slate-100/90 flex flex-col justify-between hover:shadow-soft transition-all duration-200 group">
                <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg mb-4 group-hover:scale-105 transition-transform">
                    <i class="fa-solid fa-chart-simple"></i>
                </div>
                <div>
                    <h3 class="text-xl sm:text-2xl lg:text-3xl font-extrabold text-slate-900 tracking-tight mb-1">
                        <?php echo htmlspecialchars($currency_symbol) . ' ' . number_format($total_revenue_amount, 2); ?>
                    </h3>
                    <p class="text-xs sm:text-sm font-medium text-slate-400">Total Revenue</p>
                </div>
            </div>

            <!-- Stat Card 3: Low Stock Items -->
            <div class="bg-white rounded-3xl p-6 shadow-card border border-slate-100/90 flex flex-col justify-between hover:shadow-soft transition-all duration-200 group">
                <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg mb-4 group-hover:scale-105 transition-transform">
                    <i class="fa-solid fa-arrow-down"></i>
                </div>
                <div>
                    <h3 class="text-xl sm:text-2xl lg:text-3xl font-extrabold text-slate-900 tracking-tight mb-1">
                        <?php echo sprintf("%02d", $low_stock_count); ?>
                    </h3>
                    <p class="text-xs sm:text-sm font-medium text-slate-400">Low Stock Items</p>
                </div>
            </div>

            <!-- Stat Card 4: Out of Stock -->
            <div class="bg-white rounded-3xl p-6 shadow-card border border-slate-100/90 flex flex-col justify-between hover:shadow-soft transition-all duration-200 group">
                <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg mb-4 group-hover:scale-105 transition-transform">
                    <i class="fa-solid fa-box-open"></i>
                </div>
                <div>
                    <h3 class="text-xl sm:text-2xl lg:text-3xl font-extrabold text-slate-900 tracking-tight mb-1">
                        <?php echo sprintf("%02d", $out_of_stock_count); ?>
                    </h3>
                    <p class="text-xs sm:text-sm font-medium text-slate-400">Out of Stock</p>
                </div>
            </div>

        </div>

        <!-- Right: Weekly Sales Area Chart (7 cols on lg) -->
        <div class="lg:col-span-7 bg-white rounded-3xl p-6 sm:p-7 shadow-card border border-slate-100/90 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">Weekly Sales</h2>
                    <p class="text-xs text-slate-400 font-medium">Real-time 7-day revenue trend</p>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($peak_sales_val > 0): ?>
                        <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-100">
                            Peak: <?php echo htmlspecialchars($currency_symbol) . ' ' . number_format($peak_sales_val, 2); ?>
                        </span>
                    <?php else: ?>
                        <span class="text-xs font-medium text-slate-400 bg-slate-50 px-3 py-1 rounded-full">
                            No sales this week
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Chart Canvas Container -->
            <div class="relative w-full h-[230px] sm:h-[250px] mt-2">
                <canvas id="weeklySalesChart"></canvas>
            </div>
        </div>

    </div>

    <!-- Bottom Section: Low Stock Items Table Card -->
    <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7 overflow-hidden">
        
        <!-- Table Card Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight">Low Stock Items</h2>
                <p class="text-xs text-slate-400 font-medium mt-0.5">Products that need immediate reordering</p>
            </div>
            
            <div class="flex items-center gap-3">
                <a href="products.php" class="px-5 py-2.5 rounded-2xl border border-slate-200/90 text-slate-700 hover:bg-slate-50 text-xs sm:text-sm font-bold transition-all shadow-sm">
                    See All
                </a>
                <a href="product_add.php" class="px-5 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs sm:text-sm font-bold transition-all flex items-center gap-2 shadow-sm shadow-emerald-500/25">
                    <span>Add Item</span>
                    <i class="fa-solid fa-plus text-xs"></i>
                </a>
            </div>
        </div>

        <?php if (!empty($low_stock_products)): ?>
        <!-- Table Container -->
        <div class="overflow-x-auto -mx-6 sm:-mx-7 px-6 sm:px-7">
            <table class="w-full text-left border-collapse" id="lowStockTable">
                <thead>
                    <tr class="border-b border-slate-100 text-[12px] font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-3.5 pr-4 pl-2 font-semibold">
                            <span class="inline-flex items-center gap-1.5 cursor-pointer hover:text-slate-800" onclick="sortTable(0)">
                                Item Name <i class="fa-solid fa-caret-down text-[10px] text-slate-300"></i>
                            </span>
                        </th>
                        <th class="py-3.5 px-4 font-semibold">
                            <span class="inline-flex items-center gap-1.5 cursor-pointer hover:text-slate-800" onclick="sortTable(1)">
                                Brand <i class="fa-solid fa-caret-down text-[10px] text-slate-300"></i>
                            </span>
                        </th>
                        <th class="py-3.5 px-4 font-semibold">
                            <span class="inline-flex items-center gap-1.5 cursor-pointer hover:text-slate-800" onclick="sortTable(2)">
                                Category <i class="fa-solid fa-caret-down text-[10px] text-slate-300"></i>
                            </span>
                        </th>
                        <th class="py-3.5 px-4 font-semibold">
                            <span class="inline-flex items-center gap-1.5 cursor-pointer hover:text-slate-800" onclick="sortTable(3)">
                                Current Quantity <i class="fa-solid fa-caret-down text-[10px] text-slate-300"></i>
                            </span>
                        </th>
                        <th class="py-3.5 px-4 font-semibold">
                            <span class="inline-flex items-center gap-1.5 cursor-pointer hover:text-slate-800" onclick="sortTable(4)">
                                Buying Price <i class="fa-solid fa-caret-down text-[10px] text-slate-300"></i>
                            </span>
                        </th>
                        <th class="py-3.5 px-4 font-semibold">
                            <span class="inline-flex items-center gap-1.5 cursor-pointer hover:text-slate-800" onclick="sortTable(5)">
                                Selling Price <i class="fa-solid fa-caret-down text-[10px] text-slate-300"></i>
                            </span>
                        </th>
                        <th class="py-3.5 px-4 font-semibold text-center">
                            <span class="inline-flex items-center gap-1.5 cursor-pointer hover:text-slate-800" onclick="sortTable(6)">
                                Status <i class="fa-solid fa-caret-down text-[10px] text-slate-300"></i>
                            </span>
                        </th>
                        <th class="py-3.5 pl-4 pr-2 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 text-sm text-slate-700">
                    <?php foreach ($low_stock_products as $item): 
                        $status_str = ((int)$item['current_qty'] <= 0) ? 'Out of Stock' : 'Low Stock';
                        $item_json = [
                            'name' => $item['name'],
                            'brand' => $item['brand_name'] ?? 'Generic',
                            'category' => $item['category_name'] ?? 'Uncategorized',
                            'qty' => $item['current_qty'] . ' ' . ($item['unit'] ?? 'pcs'),
                            'buying_price' => $currency_symbol . ' ' . number_format($item['cost_price'], 2),
                            'selling_price' => $currency_symbol . ' ' . number_format($item['selling_price'], 2),
                            'status' => $status_str,
                            'image' => !empty($item['image']) ? '../uploads/' . htmlspecialchars($item['image']) : ''
                        ];
                    ?>
                    <tr class="hover:bg-slate-50/60 transition-colors group">
                        
                        <!-- Item Name & Thumbnail -->
                        <td class="py-4 pr-4 pl-2 font-semibold text-slate-900">
                            <div class="flex items-center gap-3.5">
                                <?php if (!empty($item['image'])): ?>
                                    <img 
                                        src="../uploads/<?php echo htmlspecialchars($item['image']); ?>" 
                                        alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                        class="w-10 h-10 rounded-2xl object-cover border border-slate-100 shadow-sm shrink-0 bg-slate-100"
                                    >
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-xs border border-emerald-100 shrink-0">
                                        <?php echo strtoupper(substr($item['name'], 0, 2)); ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <span class="block text-slate-900 font-bold leading-snug"><?php echo htmlspecialchars($item['name']); ?></span>
                                    <span class="block text-xs text-slate-400 font-mono"><?php echo htmlspecialchars($item['product_code']); ?></span>
                                </div>
                            </div>
                        </td>

                        <!-- Brand -->
                        <td class="py-4 px-4 text-slate-600 font-medium">
                            <?php echo htmlspecialchars($item['brand_name'] ?? '-'); ?>
                        </td>

                        <!-- Category Badge -->
                        <td class="py-4 px-4">
                            <span class="inline-block bg-slate-100/80 text-slate-600 text-xs font-semibold px-3 py-1 rounded-xl">
                                <?php echo htmlspecialchars($item['category_name'] ?? 'General'); ?>
                            </span>
                        </td>

                        <!-- Current Quantity -->
                        <td class="py-4 px-4 font-bold text-slate-800">
                            <?php echo htmlspecialchars($item['current_qty']) . ' ' . htmlspecialchars($item['unit'] ?? 'pcs'); ?>
                        </td>

                        <!-- Buying Price -->
                        <td class="py-4 px-4 text-slate-600 font-medium">
                            <?php echo htmlspecialchars($currency_symbol) . ' ' . number_format($item['cost_price'], 2); ?>
                        </td>

                        <!-- Selling Price -->
                        <td class="py-4 px-4 text-slate-800 font-bold">
                            <?php echo htmlspecialchars($currency_symbol) . ' ' . number_format($item['selling_price'], 2); ?>
                        </td>

                        <!-- Status Badge -->
                        <td class="py-4 px-4 text-center">
                            <?php if ($status_str === 'Out of Stock'): ?>
                                <span class="inline-block border border-red-200 bg-red-50/70 text-red-600 text-xs font-bold px-3 py-1 rounded-xl">
                                    Out of Stock
                                </span>
                            <?php else: ?>
                                <span class="inline-block border border-amber-300/80 bg-amber-50/60 text-amber-600 text-xs font-bold px-3 py-1 rounded-xl">
                                    Low Stock
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Action Link -->
                        <td class="py-4 pl-4 pr-2 text-right">
                            <button 
                                onclick="openItemModal(<?php echo htmlspecialchars(json_encode($item_json)); ?>)" 
                                class="inline-flex items-center gap-1.5 text-slate-400 hover:text-emerald-600 text-xs font-bold transition-colors py-1 px-2.5 rounded-lg hover:bg-emerald-50/50"
                            >
                                <i class="fa-regular fa-circle-question text-sm"></i>
                                <span>Details</span>
                            </button>
                        </td>

                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <!-- Clean Empty State when all inventory is optimal -->
        <div class="py-14 px-4 text-center">
            <div class="w-16 h-16 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl mx-auto mb-4 border border-emerald-100">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <h3 class="text-base font-bold text-slate-800 mb-1">Inventory is Fully Stocked</h3>
            <p class="text-xs text-slate-400 max-w-sm mx-auto mb-5">There are currently no items running below their designated reorder level.</p>
            <a href="products.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-bold transition-all shadow-sm">
                <i class="fa-solid fa-boxes-stacked text-xs"></i>
                <span>Browse All Products</span>
            </a>
        </div>
        <?php endif; ?>

    </div>

</div>

<!-- Chart Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('weeklySalesChart').getContext('2d');
    
    // Dynamic chart labels and data series from PHP
    const labels = <?php echo json_encode($days_labels); ?>;
    const dataValues = <?php echo json_encode($chart_series); ?>;
    const currency = <?php echo json_encode($currency_symbol); ?>;

    // Gradient styling
    const gradient = ctx.createLinearGradient(0, 0, 0, 240);
    gradient.addColorStop(0, 'rgba(16, 185, 129, 0.45)');
    gradient.addColorStop(0.6, 'rgba(16, 185, 129, 0.12)');
    gradient.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

    // Find index with highest sales for dot highlight
    let maxIdx = 0;
    let maxVal = -1;
    for (let i = 0; i < dataValues.length; i++) {
        if (dataValues[i] > maxVal) {
            maxVal = dataValues[i];
            maxIdx = i;
        }
    }
    const pointRadii = dataValues.map((v, i) => (v > 0 && i === maxIdx) ? 7 : 0);

    const weeklyChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue',
                data: dataValues,
                borderColor: '#10B981',
                borderWidth: 3.5,
                backgroundColor: gradient,
                fill: true,
                tension: 0.45,
                pointRadius: pointRadii,
                pointBackgroundColor: '#10B981',
                pointBorderColor: '#FFFFFF',
                pointBorderWidth: 3.5,
                pointHoverRadius: 8,
                pointHoverBackgroundColor: '#059669',
                pointHoverBorderColor: '#FFFFFF',
                pointHoverBorderWidth: 3.5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#1E293B',
                    titleFont: { family: '"Plus Jakarta Sans"', size: 12, weight: 'bold' },
                    bodyFont: { family: '"Plus Jakarta Sans"', size: 12 },
                    padding: 10,
                    cornerRadius: 12,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return ' Sales: ' + currency + ' ' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: true,
                        color: document.documentElement.classList.contains('dark') ? 'rgba(51, 65, 85, 0.5)' : 'rgba(241, 245, 249, 0.8)',
                        drawBorder: false,
                    },
                    ticks: {
                        font: { family: '"Plus Jakarta Sans"', size: 12, weight: '500' },
                        color: '#94A3B8'
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: document.documentElement.classList.contains('dark') ? 'rgba(51, 65, 85, 0.5)' : 'rgba(241, 245, 249, 0.8)',
                        drawBorder: false,
                    },
                    ticks: {
                        font: { family: '"Plus Jakarta Sans"', size: 11, weight: '500' },
                        color: '#94A3B8',
                        callback: function(value) {
                            if (value >= 1000) {
                                return currency + ' ' + (value / 1000).toFixed(1) + 'K';
                            }
                            return currency + ' ' + value;
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
