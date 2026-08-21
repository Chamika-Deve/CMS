<?php
require_once __DIR__ . '/../includes/db.php';

$ticket = trim($_GET['ticket'] ?? $_GET['id'] ?? $_GET['q'] ?? '');

if (!$pdo) {
    http_response_code(503);
    exit('The database is temporarily unavailable.');
}

if ($ticket === '') {
    http_response_code(400);
    exit('A valid repair ticket reference is required.');
}

// 1. Fetch Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log('Receipt settings query failed: ' . $e->getMessage());
}

$shop_name = $settings['shop_name'] ?? 'TechShop Computers & Repairs';
$shop_address = $settings['shop_address'] ?? '123 Tech Street, Colombo 03, Sri Lanka';
$shop_phone = $settings['shop_phone'] ?? '+94 77 123 4567';
$shop_email = $settings['shop_email'] ?? 'service@techshop.lk';
$shop_logo = $settings['shop_logo'] ?? '';
$currency_symbol = $settings['currency_symbol'] ?? 'Rs.';
$bill_footer = $settings['bill_footer_message'] ?? 'Thank you for choosing our repair services! Please present this receipt upon device collection.';
$printer_width = $settings['receipt_printer_width'] ?? '80mm';

// 2. Fetch Repair Job & Customer & Technician Details
$job = null;
try {
    $stmt = $pdo->prepare("
        SELECT r.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
               u.name AS technician_name
        FROM repair_jobs r
        LEFT JOIN customers c ON r.customer_id = c.id
        LEFT JOIN users u ON r.technician_id = u.id
        WHERE r.ticket_no = ? OR r.public_token = ? OR CAST(r.id AS CHAR) = ?
        ORDER BY r.id DESC
        LIMIT 1
    ");
    $stmt->execute([$ticket, $ticket, $ticket]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Repair receipt query failed: ' . $e->getMessage());
}

if (!$job) {
    http_response_code(404);
    die('<!DOCTYPE html><html><head><title>Receipt Not Found</title><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-slate-900 text-white flex items-center justify-center min-h-screen p-4"><div class="text-center space-y-4 max-w-md bg-slate-800 p-8 rounded-3xl border border-slate-700"><i class="fa-solid fa-triangle-exclamation text-4xl text-amber-400"></i><h1 class="text-xl font-bold">Repair Ticket Not Found</h1><p class="text-xs text-slate-400">No repair record matching reference: <span class="font-mono text-emerald-400 font-bold">' . htmlspecialchars($ticket) . '</span></p><a href="track.php" class="inline-block px-5 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white font-bold rounded-2xl text-xs">Return to Tracker</a></div></body></html>');
}

$ticket_no = $job['ticket_no'] ?: ('RPR-' . sprintf('%05d', $job['id']));
$cust_name = $job['customer_name'] ?: 'Valued Customer';
$cust_phone = $job['customer_phone'] ?: 'N/A';
$device_title = trim(($job['device_brand'] ?? '') . ' ' . ($job['device_model'] ?? ''));
if (empty($device_title)) {
    $device_title = $job['device_name'] ?: ($job['device_type'] ?: 'Device');
}
$status = $job['status'] ?: 'Pending';
$tech_name = $job['technician_name'] ?: 'Senior Service Specialist';
$rec_date = !empty($job['received_date']) ? date('d/m/Y h:i A', strtotime($job['received_date'])) : date('d/m/Y h:i A', strtotime($job['created_at']));
$labor = (float)($job['labor_fee'] ?? 0);
$parts = (float)($job['parts_cost'] ?? 0);
$total = (float)($job['total_amount'] ?? ($job['estimated_cost'] ?? 0));
if ($total <= 0) {
    $total = $labor + $parts;
}
$public_url = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF']) . '/track.php?ticket=' . urlencode($job['public_token'] ?: $ticket_no);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repair Receipt - <?php echo htmlspecialchars($ticket_no); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Courier+Prime:ital,wght@0,400;0,700;1,400&family=Inter:wght@400;500;600;700;800;900&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9;
            color: #0f172a;
        }
        .font-mono-receipt {
            font-family: 'Courier Prime', monospace;
        }
        @media print {
            body {
                background-color: #ffffff !important;
                color: #000000 !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .print-card {
                box-shadow: none !important;
                border: none !important;
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            @page {
                margin: 10mm;
            }
        }
    </style>
</head>
<body class="min-h-screen p-4 sm:p-8 flex flex-col items-center justify-start antialiased">

    <!-- Top Floating Toolbar (No-Print) -->
    <div class="no-print w-full max-w-2xl bg-slate-900 text-white p-4 rounded-3xl shadow-xl flex items-center justify-between mb-6 border border-slate-800">
        <div class="flex items-center gap-3">
            <a href="track.php?ticket=<?php echo urlencode($ticket_no); ?>" class="w-9 h-9 rounded-2xl bg-white/10 hover:bg-white/20 flex items-center justify-center text-xs transition-colors">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-sm font-bold">Repair Ticket Receipt</h1>
                <p class="text-[11px] text-slate-400 font-mono"><?php echo htmlspecialchars($ticket_no); ?></p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="px-5 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs rounded-2xl shadow-sm shadow-emerald-500/25 transition-all flex items-center gap-2">
                <i class="fa-solid fa-print"></i> Print Receipt
            </button>
        </div>
    </div>

    <!-- Printable Receipt Container Card -->
    <div class="print-card w-full max-w-2xl bg-white rounded-3xl shadow-2xl border border-slate-200/80 p-8 sm:p-10 space-y-7 relative">
        
        <!-- Receipt Header & Brand -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 pb-6 border-b border-slate-200">
            <div>
                <?php if ($shop_logo): ?>
                    <img src="<?php echo htmlspecialchars($shop_logo); ?>" alt="Shop Logo" class="h-12 mb-2 object-contain">
                <?php endif; ?>
                <h2 class="text-xl font-extrabold text-slate-900 tracking-tight"><?php echo htmlspecialchars($shop_name); ?></h2>
                <p class="text-xs text-slate-500 mt-1 max-w-xs leading-relaxed"><?php echo nl2br(htmlspecialchars($shop_address)); ?></p>
                <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 mt-2 font-medium">
                    <span><i class="fa-solid fa-phone text-emerald-600 mr-1"></i><?php echo htmlspecialchars($shop_phone); ?></span>
                    <?php if ($shop_email): ?>
                        <span><i class="fa-solid fa-envelope text-emerald-600 mr-1"></i><?php echo htmlspecialchars($shop_email); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Receipt Badge & Ticket Box -->
            <div class="text-left sm:text-right bg-slate-50 p-4 rounded-2xl border border-slate-100 min-w-[200px]">
                <span class="inline-block px-2.5 py-0.5 rounded-md bg-emerald-100 text-emerald-800 font-extrabold text-[10px] uppercase tracking-wider mb-1">Repair Service Ticket</span>
                <h3 class="text-xl font-black text-slate-900 font-mono tracking-tight"><?php echo htmlspecialchars($ticket_no); ?></h3>
                <p class="text-[11px] text-slate-500 mt-1">Date: <strong><?php echo $rec_date; ?></strong></p>
                <p class="text-[11px] text-emerald-700 font-bold mt-0.5">Status: <?php echo htmlspecialchars($status); ?></p>
            </div>
        </div>

        <!-- Customer & Device Overview Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 bg-slate-50/80 p-5 sm:p-6 rounded-2xl border border-slate-200/70 text-xs">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block mb-2">Customer Information</span>
                <p class="font-bold text-slate-900 text-sm mb-1"><?php echo htmlspecialchars($cust_name); ?></p>
                <p class="text-slate-600 font-medium mb-0.5"><i class="fa-solid fa-phone text-slate-400 w-4"></i> <?php echo htmlspecialchars($cust_phone); ?></p>
                <?php if ($job['customer_email']): ?>
                    <p class="text-slate-600"><i class="fa-solid fa-envelope text-slate-400 w-4"></i> <?php echo htmlspecialchars($job['customer_email']); ?></p>
                <?php endif; ?>
            </div>

            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block mb-2">Device & Technical Intake</span>
                <p class="font-bold text-slate-900 text-sm mb-1"><?php echo htmlspecialchars($device_title); ?></p>
                <p class="text-slate-600"><strong>Type:</strong> <?php echo htmlspecialchars($job['device_type'] ?: 'Computer/Hardware'); ?></p>
                <p class="text-slate-600"><strong>Serial / IMEI:</strong> <span class="font-mono font-bold text-slate-800"><?php echo htmlspecialchars($job['serial_number'] ?: 'N/A'); ?></span></p>
                <?php if (!empty($job['accessories_included'])): ?>
                    <p class="text-slate-600"><strong>Accessories:</strong> <?php echo htmlspecialchars($job['accessories_included']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reported Issue & Diagnosis -->
        <div class="space-y-4 text-xs">
            <div class="bg-amber-50/60 p-4 rounded-2xl border border-amber-200/60">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-800 block mb-1">Reported Customer Issue</span>
                <p class="text-slate-800 font-medium italic">"<?php echo htmlspecialchars($job['issue_description'] ?: 'No fault description provided.'); ?>"</p>
            </div>

            <?php if (!empty($job['diagnosis_notes'])): ?>
            <div class="bg-blue-50/60 p-4 rounded-2xl border border-blue-200/60">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-blue-800 block mb-1">Technician Diagnostic Notes</span>
                <p class="text-slate-800 font-medium leading-relaxed"><?php echo nl2br(htmlspecialchars($job['diagnosis_notes'])); ?></p>
                <p class="text-[11px] text-blue-700 font-bold mt-2"><i class="fa-solid fa-user-gear mr-1"></i> Specialist: <?php echo htmlspecialchars($tech_name); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Financial Summary Table -->
        <div>
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block mb-2">Cost & Quotation Breakdown</span>
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-100 text-slate-600 font-bold border-b border-slate-200 text-[11px] uppercase">
                        <th class="py-2.5 px-3">Service Description</th>
                        <th class="py-2.5 px-3 text-right">Amount (<?php echo htmlspecialchars($currency_symbol); ?>)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr>
                        <td class="py-3 px-3 font-medium text-slate-800">Technician Labor & Service Inspection Fee</td>
                        <td class="py-3 px-3 text-right font-bold text-slate-900"><?php echo number_format($labor, 2); ?></td>
                    </tr>
                    <tr>
                        <td class="py-3 px-3 font-medium text-slate-800">Replacement Parts & Hardware Component Costs</td>
                        <td class="py-3 px-3 text-right font-bold text-slate-900"><?php echo number_format($parts, 2); ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50 font-bold border-t-2 border-slate-200 text-sm">
                        <td class="py-3.5 px-3 text-slate-900 font-black">Total Repair Charge</td>
                        <td class="py-3.5 px-3 text-right text-emerald-600 font-black"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($total, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Warranty & Approval Banner -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs">
            <div class="flex items-center gap-2.5">
                <i class="fa-solid fa-shield-halved text-emerald-600 text-lg"></i>
                <div>
                    <p class="font-bold text-emerald-900"><?php echo !empty($job['warranty_days']) ? ($job['warranty_days'] . '-Day Service Warranty Included') : 'Standard Repair Warranty Applicable'; ?></p>
                    <p class="text-[11px] text-emerald-700">Covers defects in replaced parts and labor workmanship.</p>
                </div>
            </div>
            <span class="px-3 py-1 bg-emerald-600 text-white font-bold rounded-xl text-[11px] shrink-0">
                <?php echo !empty($job['is_quote_approved']) ? 'Quote Authorized' : 'Inspection Receipt'; ?>
            </span>
        </div>

        <!-- Signatures & Authorization Section -->
        <div class="pt-6 border-t border-slate-200 grid grid-cols-2 gap-8 text-center text-xs">
            <div>
                <div class="h-12 border-b border-slate-300 border-dashed mb-1"></div>
                <p class="font-bold text-slate-700">Customer Signature</p>
                <p class="text-[10px] text-slate-400">Device received for service</p>
            </div>
            <div>
                <div class="h-12 border-b border-slate-300 border-dashed mb-1"></div>
                <p class="font-bold text-slate-700">Authorized Officer / Stamp</p>
                <p class="text-[10px] text-slate-400"><?php echo htmlspecialchars($shop_name); ?></p>
            </div>
        </div>

        <!-- Footer Policy Notice -->
        <div class="pt-4 border-t border-slate-100 text-center space-y-2">
            <p class="text-[11px] text-slate-500 italic leading-relaxed max-w-lg mx-auto">
                <?php echo nl2br(htmlspecialchars($bill_footer)); ?>
            </p>
            <p class="text-[10px] text-slate-400 font-mono">
                Track status online at: <span class="text-emerald-600 font-bold"><?php echo htmlspecialchars($public_url); ?></span>
            </p>
        </div>

    </div>

    <!-- Auto Print Script -->
    <script>
        // Trigger print automatically if opened with ?print=1
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('print') === '1' || urlParams.get('auto') === '1') {
            window.onload = function() {
                setTimeout(() => window.print(), 300);
            };
        }
    </script>

</body>
</html>
