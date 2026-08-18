<?php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
require_once __DIR__ . '/../includes/db.php';

$search_query = trim($_GET['ticket'] ?? $_GET['q'] ?? '');
$job = null;
$error = '';
$quote_msg = '';
$shop_name = 'TechShop';
$shop_phone = '+94 77 123 4567';
$currency_symbol = 'Rs.';
$base_prefix = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/pages/') ? '../' : '';

if ($pdo) {
    try {
        $settingsStatement = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('shop_name', 'shop_phone', 'currency_symbol')");
        foreach ($settingsStatement->fetchAll(PDO::FETCH_KEY_PAIR) as $key => $value) {
            if ($key === 'shop_name' && $value !== '') $shop_name = $value;
            if ($key === 'shop_phone' && $value !== '') $shop_phone = $value;
            if ($key === 'currency_symbol' && $value !== '') $currency_symbol = $value;
        }
    } catch (Throwable $ignored) {
    }
}

// Quote approval requires the unguessable per-ticket public token. A ticket
// number by itself is sufficient for viewing, but never for changing data.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'customer_approve_quote') {
    $job_id = (int)($_POST['job_id'] ?? 0);
    $token = trim($_POST['token'] ?? '');

    if (!$pdo) {
        $error = 'The repair tracker is temporarily unavailable.';
    } elseif ($job_id < 1 || !preg_match('/^[a-f0-9]{32,64}$/i', $token)) {
        $error = 'The quote approval link is invalid.';
    } else {
        try {
            $stmt_app = $pdo->prepare("UPDATE repair_jobs SET is_quote_approved = 1, status = IF(status IN ('Received', 'Diagnosing'), 'In Repair', status), updated_at = NOW() WHERE id = ? AND public_token = ?");
            $stmt_app->execute([$job_id, $token]);
            if ($stmt_app->rowCount() === 1) {
                $quote_msg = 'Thank you! You approved this repair quotation.';
            } else {
                $error = 'This quote could not be approved. Refresh the tracking link and try again.';
            }
        } catch (Throwable $exception) {
            error_log('Repair quote approval failed: ' . $exception->getMessage());
            $error = 'The quote approval could not be completed right now.';
        }
    }
}

if ($search_query !== '') {
    if (!$pdo) {
        $error = 'The repair tracker is temporarily unavailable.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT r.*, u.name AS technician_name
                FROM repair_jobs r
                LEFT JOIN users u ON r.technician_id = u.id
                WHERE r.ticket_no = ? OR r.public_token = ?
                ORDER BY r.id DESC
                LIMIT 1
            ");
            $stmt->execute([$search_query, $search_query]);
            $job = $stmt->fetch();
            if (!$job && $error === '') {
                $error = 'No repair job matched that tracking reference. Check the ticket number and try again.';
            }
        } catch (Throwable $exception) {
            error_log('Repair tracking lookup failed: ' . $exception->getMessage());
            $error = 'The repair tracker could not complete your search right now.';
        }
    }
}

// Steps pipeline
$status_steps = [
    'Received' => ['title' => 'Device Received', 'icon' => 'fa-inbox', 'desc' => 'Device logged into workbench intake.'],
    'Diagnosing' => ['title' => 'Diagnostic in Progress', 'icon' => 'fa-stethoscope', 'desc' => 'Hardware specialist diagnosing faults.'],
    'Waiting for Parts' => ['title' => 'Parts on Order', 'icon' => 'fa-truck-fast', 'desc' => 'Waiting for replacement parts.'],
    'In Repair' => ['title' => 'In Repair', 'icon' => 'fa-screwdriver-wrench', 'desc' => 'Component soldering and repair underway.'],
    'Ready for Pickup' => ['title' => 'Ready for Pickup', 'icon' => 'fa-box-check', 'desc' => 'Quality testing passed. Ready at counter.'],
    'Completed' => ['title' => 'Collected & Completed', 'icon' => 'fa-circle-check', 'desc' => 'Device collected by customer.']
];

$step_keys = array_keys($status_steps);
$pipeline_status = $job['status'] ?? 'Received';
if ($pipeline_status === 'Closed') $pipeline_status = 'Completed';
$curr_step_idx = array_search($pipeline_status, $step_keys, true);
if ($curr_step_idx === false) $curr_step_idx = 0;
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Repair Status Tracker - <?php echo htmlspecialchars($shop_name); ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-[#F7FAF8] text-slate-800 min-h-screen flex flex-col justify-between antialiased">
    
    <!-- Top Header -->
    <header class="bg-white border-b border-slate-100 py-4 px-6 sm:px-12 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-2xl bg-emerald-500 text-white flex items-center justify-center font-black text-base shadow-sm shadow-emerald-500/20">
                T
            </div>
            <div>
                <span class="text-xl font-bold text-slate-900 tracking-tight">TechShop</span>
                <span class="text-xs text-slate-400 font-semibold ml-2">Customer Care & Repair Tracker</span>
            </div>
        </div>
        <a href="dashboard.php" class="text-xs font-bold text-slate-500 hover:text-emerald-600 flex items-center gap-1.5 transition-colors">
            <i class="fa-solid fa-lock text-slate-400"></i> Staff Login
        </a>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-4xl w-full mx-auto p-4 sm:p-8">
        
        <!-- Search Card -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-card border border-slate-100/90 text-center mb-8">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mb-2">Track Your Repair Live</h1>
            <p class="text-xs sm:text-sm text-slate-400 mb-6 max-w-md mx-auto">Enter your Ticket Number (e.g. <span class="font-mono font-bold text-emerald-600">RPR-260816-101</span>), Serial Number, or Phone Number to check real-time progress.</p>

            <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] ?? 'track.php'); ?>" class="max-w-lg mx-auto flex items-center gap-2">
                <div class="relative flex-1">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input 
                        type="text" 
                        name="ticket" 
                        value="<?php echo htmlspecialchars($search_query); ?>" 
                        placeholder="Ticket # / Serial # / Phone" 
                        required
                        class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200/80 rounded-2xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all"
                    >
                </div>
                <button type="submit" class="px-6 py-3 bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-sm rounded-2xl transition-all shadow-sm shadow-emerald-500/20 shrink-0">
                    Track Device
                </button>
            </form>

            <?php if ($error): ?>
                <div class="mt-4 p-3 bg-red-50 text-red-700 text-xs rounded-xl font-medium max-w-lg mx-auto border border-red-100">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($quote_msg): ?>
                <div class="mt-4 p-3 bg-emerald-50 text-emerald-700 text-xs rounded-xl font-bold max-w-lg mx-auto border border-emerald-200">
                    <i class="fa-solid fa-circle-check mr-1"></i> <?php echo htmlspecialchars($quote_msg); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($job): ?>
        <!-- Job Details Card -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-card border border-slate-100/90 space-y-8">
            
            <!-- Ticket Top Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 border-b border-slate-100 gap-4">
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Ticket Number</span>
                    <h2 class="text-2xl font-black text-emerald-600 font-mono tracking-tight"><?php echo htmlspecialchars($job['ticket_no'] ?? ('RPR-' . $job['id'])); ?></h2>
                    <p class="text-xs text-slate-400 mt-0.5">Intake on <?php echo date('F j, Y, g:i A', strtotime($job['received_date'])); ?></p>
                </div>

                <div class="flex items-center gap-3">
                    <span class="px-4 py-1.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Status: <?php echo htmlspecialchars($job['status']); ?>
                    </span>
                    <button onclick="window.print()" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold transition-colors flex items-center gap-1.5">
                        <i class="fa-solid fa-print"></i> Print Receipt
                    </button>
                </div>
            </div>

            <!-- Visual Progress Stepper -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-6">Repair Lifecycle Progress</h3>
                <div class="grid grid-cols-2 sm:grid-cols-6 gap-3">
                    <?php 
                    $idx = 0;
                    foreach ($status_steps as $st_key => $st_info): 
                        $is_done = ($idx <= $curr_step_idx);
                        $is_current = ($idx === $curr_step_idx);
                        $idx++;
                    ?>
                    <div class="p-3.5 rounded-2xl border text-center transition-all <?php echo $is_current ? 'bg-emerald-500 text-white border-emerald-500 shadow-sm shadow-emerald-500/20' : ($is_done ? 'bg-emerald-50/70 text-emerald-800 border-emerald-200' : 'bg-slate-50 text-slate-400 border-slate-100'); ?>">
                        <div class="w-8 h-8 rounded-full mx-auto mb-2 flex items-center justify-center text-xs <?php echo $is_current ? 'bg-white text-emerald-600' : ($is_done ? 'bg-emerald-200/60 text-emerald-800' : 'bg-slate-200 text-slate-400'); ?>">
                            <i class="fa-solid <?php echo $st_info['icon']; ?>"></i>
                        </div>
                        <p class="font-bold text-[11px] leading-tight"><?php echo $st_info['title']; ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Device & Diagnosis Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 bg-slate-50/70 p-6 rounded-2xl border border-slate-100 text-xs sm:text-sm">
                <div>
                    <h4 class="font-bold text-slate-900 mb-3 uppercase tracking-wider text-[11px] text-slate-400">Device Details</h4>
                    <p class="font-bold text-slate-800 text-base mb-1"><?php echo htmlspecialchars($job['device_brand'] . ' ' . $job['device_model']); ?></p>
                    <p class="text-slate-500"><strong>Type:</strong> <?php echo htmlspecialchars($job['device_type']); ?></p>
                    <p class="text-slate-500"><strong>Serial:</strong> <span class="font-mono"><?php echo htmlspecialchars($job['serial_number'] ?? 'N/A'); ?></span></p>
                    <p class="text-slate-500"><strong>Accessories:</strong> <?php echo htmlspecialchars($job['accessories_included'] ?? 'None'); ?></p>
                    <p class="text-slate-500 mt-2"><strong>Reported Issue:</strong><br><span class="italic text-slate-700">"<?php echo htmlspecialchars($job['issue_description']); ?>"</span></p>
                </div>

                <div>
                    <h4 class="font-bold text-slate-900 mb-3 uppercase tracking-wider text-[11px] text-slate-400">Technician Diagnosis</h4>
                    <p class="text-slate-500"><strong>Assigned Specialist:</strong> <?php echo htmlspecialchars($job['technician_name'] ?? 'Senior Technician'); ?></p>
                    <p class="text-slate-500 mt-2"><strong>Diagnostic Notes:</strong></p>
                    <div class="bg-white p-3 rounded-xl border border-slate-200/70 text-slate-700 text-xs mt-1">
                        <?php echo nl2br(htmlspecialchars($job['diagnosis_notes'] ?? 'Initial diagnosis pending.')); ?>
                    </div>
                    <?php if (!empty($job['warranty_days'])): ?>
                        <p class="text-emerald-700 font-bold text-xs mt-3"><i class="fa-solid fa-shield-halved"></i> <?php echo $job['warranty_days']; ?>-Day Repair Service Warranty Included</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quotation & Approval Section -->
            <div class="bg-emerald-50/50 p-6 rounded-2xl border border-emerald-100/80 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <span class="text-xs font-bold text-emerald-800 uppercase tracking-wider">Repair Cost Breakdown</span>
                    <div class="text-xs text-slate-600 mt-1 space-x-3">
                        <span>Labor: <strong><?php echo htmlspecialchars($currency_symbol ?? "Rs."); ?> <?php echo number_format($job['labor_fee'] ?? 0, 2); ?></strong></span>
                        <span>Parts: <strong><?php echo htmlspecialchars($currency_symbol ?? "Rs."); ?> <?php echo number_format($job['parts_cost'] ?? 0, 2); ?></strong></span>
                    </div>
                    <div class="text-2xl font-black text-slate-900 mt-1">
                        Total: <span class="text-emerald-600"><?php echo htmlspecialchars($currency_symbol ?? "Rs."); ?> <?php echo number_format($job['total_amount'] ?? $job['estimated_cost'], 2); ?></span>
                    </div>
                </div>

                <div>
                    <?php if (!empty($job['is_quote_approved'])): ?>
                        <div class="px-5 py-2.5 bg-emerald-600 text-white rounded-2xl text-xs font-bold flex items-center gap-2 shadow-sm">
                            <i class="fa-solid fa-circle-check text-sm"></i>
                            <span>Quotation Approved</span>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] ?? 'track.php'); ?>?ticket=<?php echo urlencode($search_query); ?>">
                            <input type="hidden" name="action" value="customer_approve_quote">
                            <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($job['public_token'] ?? ''); ?>">
                            <button type="submit" class="px-6 py-3 bg-emerald-500 hover:bg-emerald-600 text-white rounded-2xl text-xs sm:text-sm font-bold shadow-sm shadow-emerald-500/25 transition-all flex items-center gap-2">
                                <i class="fa-solid fa-thumbs-up"></i>
                                <span>Approve & Authorize Repair</span>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

        </div>
        <?php endif; ?>

    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-100 py-6 text-center text-xs text-slate-400">
        <p>&copy; 2026 TechShop Management System. For telephone assistance, call <strong>+94 77 123 4567</strong>.</p>
    </footer>

</body>
</html>
