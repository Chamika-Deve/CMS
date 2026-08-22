<?php
require_once '../includes/db.php';
require_once '../includes/header.php';

// Handle POST actions for repairs
$msg = '';
$msg_type = 'success';
$can_update_repairs = in_array($role, ['Admin', 'Manager', 'Technician'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'update_status' && !in_array($role, ['Admin', 'Manager', 'Technician'], true)) {
        abort_request(403, 'You do not have permission to update repair jobs.');
    }

    if ($action === 'create_ticket' && $pdo) {
        try {
            $ticket_no = 'RPR-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $customer_id = (int)($_POST['customer_id'] ?? 0);
            $device_type = trim($_POST['device_type'] ?? 'Laptop');
            $device_brand = trim($_POST['device_brand'] ?? '');
            $device_model = trim($_POST['device_model'] ?? '');
            $serial_number = trim($_POST['serial_number'] ?? '');
            $passcode_pin = trim($_POST['passcode_pin'] ?? '');
            $issue_description = trim($_POST['issue_description'] ?? '');
            $accessories_included = trim($_POST['accessories_included'] ?? '');
            $technician_id = !empty($_POST['technician_id']) ? (int)$_POST['technician_id'] : null;
            $estimated_cost = max(0, (float)($_POST['estimated_cost'] ?? 0));
            $public_token = bin2hex(random_bytes(32));

            if ($customer_id < 1 || $device_type === '' || $issue_description === '') {
                throw new InvalidArgumentException('Customer, device type, and issue description are required.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO repair_jobs (
                    ticket_no, customer_id, device_type, device_brand, device_model, 
                    serial_number, passcode_pin, issue_description, accessories_included, 
                    technician_id, status, estimated_cost, total_amount, public_token, received_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Received', ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $ticket_no, $customer_id, $device_type, $device_brand, $device_model,
                $serial_number, $passcode_pin, $issue_description, $accessories_included,
                $technician_id, $estimated_cost, $estimated_cost, $public_token
            ]);

            // Audit log
            if (isset($_SESSION['user']['id'])) {
                $stmt_log = $pdo->prepare("INSERT INTO activity_logs (user_id, action, module, table_name, record_id, details) VALUES (?, 'Create Repair Ticket', 'Repairs', 'repair_jobs', ?, ?)");
                $stmt_log->execute([$_SESSION['user']['id'], $pdo->lastInsertId(), "Created ticket $ticket_no for $device_brand $device_model"]);
            }

            $msg = "Repair Ticket #$ticket_no created successfully!";
        } catch (Exception $e) {
            $msg = "Error creating ticket: " . safe_error_message($e);
            $msg_type = 'error';
        }
    }

    if ($action === 'update_status' && $pdo) {
        try {
            $job_id = (int)($_POST['job_id'] ?? 0);
            $new_status = $_POST['status'] ?? '';
            $allowed_statuses = ['Received', 'Diagnosing', 'Waiting for Parts', 'In Repair', 'Ready for Pickup', 'Completed', 'Closed', 'Cancelled'];
            if (!in_array($new_status, $allowed_statuses, true)) {
                throw new InvalidArgumentException('Invalid repair status.');
            }
            $diagnosis = trim($_POST['diagnosis_notes'] ?? '');
            $labor_fee = max(0, (float)($_POST['labor_fee'] ?? 0));
            $parts_cost = max(0, (float)($_POST['parts_cost'] ?? 0));
            $total_amt = $labor_fee + $parts_cost;
            $is_approved = isset($_POST['is_quote_approved']) ? 1 : 0;
            $tech_id = !empty($_POST['technician_id']) ? (int)$_POST['technician_id'] : null;

            $delivered_date = $new_status === 'Closed' ? date('Y-m-d H:i:s') : null;

            $stmt = $pdo->prepare("
                UPDATE repair_jobs SET 
                    status = ?, diagnosis_notes = ?, labor_fee = ?, parts_cost = ?, 
                    total_amount = ?, is_quote_approved = ?, technician_id = COALESCE(?, technician_id),
                    delivered_date = COALESCE(?, delivered_date), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$new_status, $diagnosis, $labor_fee, $parts_cost, $total_amt, $is_approved, $tech_id, $delivered_date, $job_id]);

            $msg = "Repair Job #$job_id updated successfully!";
        } catch (Exception $e) {
            $msg = "Error updating job: " . safe_error_message($e);
            $msg_type = 'error';
        }
    }
}

// Fetch Customers, Technicians, and Repair Jobs
$customers = [];
$technicians = [];
$repairs = [];
$stats = [
    'received' => 0,
    'in_progress' => 0,
    'ready' => 0,
    'completed' => 0
];

if ($pdo) {
    try {
        $customers = $pdo->query("SELECT id, name, phone FROM customers ORDER BY name ASC")->fetchAll();
        $technicians = $pdo->query("SELECT id, name FROM users WHERE role IN ('Technician', 'Admin', 'Manager') AND status = 1 ORDER BY name ASC")->fetchAll();

        // Status Filter
        $status_filter = $_GET['status'] ?? '';
        $tech_filter = $_GET['tech'] ?? '';
        
        // Technicians see their own jobs by default unless filter specified
        if ($role === 'Technician' && empty($tech_filter) && !isset($_GET['all'])) {
            $tech_filter = $user['id'];
        }

        $sql = "
            SELECT r.*, c.name as customer_name, c.phone as customer_phone, u.name as technician_name
            FROM repair_jobs r
            LEFT JOIN customers c ON r.customer_id = c.id
            LEFT JOIN users u ON r.technician_id = u.id
            WHERE 1=1
        ";
        $params = [];
        if (!empty($status_filter)) {
            $sql .= " AND r.status = ?";
            $params[] = $status_filter;
        }
        if (!empty($tech_filter)) {
            $sql .= " AND r.technician_id = ?";
            $params[] = $tech_filter;
        }
        $sql .= " ORDER BY r.id DESC LIMIT 100";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $repairs = $stmt->fetchAll();

        // Calculate KPI stats
        $kpi_stmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM repair_jobs GROUP BY status");
        while ($row = $kpi_stmt->fetch()) {
            if ($row['status'] === 'Received') $stats['received'] = $row['cnt'];
            if (in_array($row['status'], ['Diagnosing', 'In Repair', 'Waiting for Parts'])) $stats['in_progress'] += $row['cnt'];
            if ($row['status'] === 'Ready for Pickup') $stats['ready'] = $row['cnt'];
            if (in_array($row['status'], ['Completed', 'Closed', 'Delivered'])) $stats['completed'] += $row['cnt'];
        }
    } catch (Exception $e) {}
}

?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <!-- Top Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-wrench text-emerald-600"></i>
                <span>Repair & Service Workbench</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Manage device intakes, diagnostics, parts usage, quotes, and tracking.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <?php if (feature_enabled('feature_tracker')): ?>
            <a href="track.php" target="_blank" class="px-4 py-2.5 rounded-2xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs sm:text-sm font-bold transition-all shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-qrcode text-emerald-600"></i>
                <span>Public Status Portal</span>
            </a>
            <?php endif; ?>
            <button onclick="openIntakeModal()" class="px-5 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs sm:text-sm font-bold transition-all shadow-sm shadow-emerald-500/25 flex items-center gap-2">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>New Device Intake</span>
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

    <!-- Status Filter KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <a href="repairs.php?status=Received" class="bg-white rounded-2xl p-4 sm:p-5 shadow-card border border-slate-100/90 hover:border-emerald-300 transition-all group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-slate-400">Intake / Received</span>
                <span class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-xs font-bold">
                    <i class="fa-solid fa-inbox"></i>
                </span>
            </div>
            <p class="text-2xl font-extrabold text-slate-900"><?php echo sprintf("%02d", $stats['received']); ?></p>
        </a>

        <a href="repairs.php?status=In Repair" class="bg-white rounded-2xl p-4 sm:p-5 shadow-card border border-slate-100/90 hover:border-emerald-300 transition-all group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-slate-400">In Progress / Parts</span>
                <span class="w-8 h-8 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center text-xs font-bold">
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                </span>
            </div>
            <p class="text-2xl font-extrabold text-slate-900"><?php echo sprintf("%02d", $stats['in_progress']); ?></p>
        </a>

        <a href="repairs.php?status=Ready for Pickup" class="bg-white rounded-2xl p-4 sm:p-5 shadow-card border border-slate-100/90 hover:border-emerald-300 transition-all group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-slate-400">Ready for Pickup</span>
                <span class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs font-bold">
                    <i class="fa-solid fa-box-check"></i>
                </span>
            </div>
            <p class="text-2xl font-extrabold text-slate-900"><?php echo sprintf("%02d", $stats['ready']); ?></p>
        </a>

        <a href="repairs.php?status=Closed" class="bg-white rounded-2xl p-4 sm:p-5 shadow-card border border-slate-100/90 hover:border-emerald-300 transition-all group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-slate-400">Completed & Delivered</span>
                <span class="w-8 h-8 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center text-xs font-bold">
                    <i class="fa-solid fa-check-double"></i>
                </span>
            </div>
            <p class="text-2xl font-extrabold text-slate-900"><?php echo sprintf("%02d", $stats['completed']); ?></p>
        </a>
    </div>

    <!-- Repair Jobs Table Card -->
    <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7 overflow-hidden">
        
        <!-- Table Actions & Filter -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-2">
                <span class="text-sm font-bold text-slate-800">Job Orders Queue</span>
                <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2.5 py-0.5 rounded-full">
                    <?php echo count($repairs); ?> Jobs
                </span>
            </div>
            
            <div class="flex items-center gap-3">
                <?php if (isset($_GET['status']) || isset($_GET['tech'])): ?>
                    <a href="repairs.php" class="text-xs font-bold text-slate-500 hover:text-slate-800 underline">Clear Filter</a>
                <?php endif; ?>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input 
                        type="text" 
                        id="repairSearch" 
                        onkeyup="filterRepairs()" 
                        placeholder="Search ticket, device, customer..." 
                        class="pl-9 pr-4 py-2 bg-slate-50 border border-slate-200/80 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 w-52 sm:w-64"
                    >
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto -mx-6 sm:-mx-7 px-6 sm:px-7">
            <table class="w-full text-left border-collapse" id="repairsTable">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 pr-4 pl-2">Ticket #</th>
                        <th class="py-3.5 px-4">Device Details</th>
                        <th class="py-3.5 px-4">Customer</th>
                        <th class="py-3.5 px-4">Assigned Tech</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4">Estimated/Total</th>
                        <th class="py-3.5 pl-4 pr-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 text-xs sm:text-sm text-slate-700">
                    <?php if (!empty($repairs)): ?>
                        <?php foreach ($repairs as $r): 
                            $r_status = $r['status'] ?? 'Received';
                            $r_dev_type = $r['device_type'] ?? 'Device';
                            $status_colors = [
                                'Received' => 'bg-blue-50 text-blue-600 border-blue-200',
                                'Diagnosing' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
                                'Waiting for Parts' => 'bg-amber-50 text-amber-700 border-amber-200',
                                'In Repair' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                'Ready for Pickup' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'Completed' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                                'Closed' => 'bg-slate-100 text-slate-700 border-slate-200',
                                'Cancelled' => 'bg-red-50 text-red-600 border-red-200'
                            ];
                            $badge_cls = $status_colors[$r_status] ?? 'bg-slate-100 text-slate-600 border-slate-200';
                        ?>
                        <tr class="hover:bg-slate-50/60 transition-colors group">
                            
                            <!-- Ticket # -->
                            <td class="py-4 pr-4 pl-2 font-mono font-bold text-slate-900">
                                <span class="text-emerald-700 font-extrabold"><?php echo htmlspecialchars($r['ticket_no'] ?? ('RPR-' . ($r['id'] ?? '1'))); ?></span>
                                <span class="block text-[10px] text-slate-400 font-sans font-normal"><?php echo date('M j, Y', strtotime($r['received_date'] ?? 'now')); ?></span>
                            </td>

                            <!-- Device -->
                            <td class="py-4 px-4 font-semibold text-slate-800">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center text-xs shrink-0">
                                        <?php if (stripos($r_dev_type, 'Laptop') !== false): ?>
                                            <i class="fa-solid fa-laptop"></i>
                                        <?php elseif (stripos($r_dev_type, 'Phone') !== false || stripos($r_dev_type, 'Smart') !== false): ?>
                                            <i class="fa-solid fa-mobile-screen"></i>
                                        <?php else: ?>
                                            <i class="fa-solid fa-desktop"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <span class="block font-bold text-slate-900"><?php echo htmlspecialchars(($r['device_brand'] ?? '') . ' ' . ($r['device_model'] ?? '')); ?></span>
                                        <span class="block text-[11px] text-slate-400 font-mono"><?php echo htmlspecialchars($r['serial_number'] ?? 'No Serial'); ?></span>
                                    </div>
                                </div>
                            </td>

                            <!-- Customer -->
                            <td class="py-4 px-4">
                                <p class="font-bold text-slate-800"><?php echo htmlspecialchars($r['customer_name'] ?? 'Walk-in'); ?></p>
                                <p class="text-xs text-slate-400"><?php echo htmlspecialchars($r['customer_phone'] ?? ''); ?></p>
                            </td>

                            <!-- Assigned Tech -->
                            <td class="py-4 px-4">
                                <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($r['technician_name'] ?? 'Unassigned'); ?></span>
                            </td>

                            <!-- Status Badge -->
                            <td class="py-4 px-4 text-center">
                                <span class="inline-block border text-xs font-bold px-3 py-1 rounded-xl <?php echo $badge_cls; ?>">
                                    <?php echo htmlspecialchars($r['status']); ?>
                                </span>
                            </td>

                            <!-- Cost -->
                            <td class="py-4 px-4 font-bold text-slate-900">
                                <?php echo htmlspecialchars($currency_symbol) . ' ' . number_format($r['total_amount'] > 0 ? $r['total_amount'] : ($r['estimated_cost'] ?? 0), 2); ?>
                                <?php if (!empty($r['is_quote_approved'])): ?>
                                    <span class="block text-[10px] text-emerald-600 font-bold"><i class="fa-solid fa-check"></i> Quote Approved</span>
                                <?php endif; ?>
                            </td>

                            <!-- Actions -->
                            <td class="py-4 pl-4 pr-2 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <?php if (feature_enabled('feature_tracker')): ?>
                                    <a href="track.php?ticket=<?php echo urlencode($r['public_token'] ?? $r['ticket_no'] ?? $r['id']); ?>" target="_blank" title="Open Customer View" class="w-8 h-8 rounded-xl border border-slate-200 text-slate-600 hover:text-emerald-600 hover:bg-emerald-50 flex items-center justify-center transition-colors text-xs">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($can_update_repairs): ?>
                                    <button onclick="openManageJobModal(<?php echo htmlspecialchars(json_encode($r)); ?>)" class="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-500 hover:text-white font-bold text-xs transition-colors flex items-center gap-1.5">
                                        <i class="fa-solid fa-screwdriver-wrench text-xs"></i>
                                        <span>Update</span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>

                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                <i class="fa-solid fa-wrench text-3xl mb-2 text-slate-300 block"></i>
                                <p class="font-semibold text-slate-600">No repair jobs found in queue.</p>
                                <p class="text-xs mt-1">Click "New Device Intake" to check in a customer device.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</div>

<!-- Modal 1: New Device Intake Modal -->
<div id="intakeModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-2xl p-7 relative max-h-[90vh] overflow-y-auto">
        <button onclick="closeIntakeModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center gap-3 mb-6 pb-3 border-b border-slate-100">
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                <i class="fa-solid fa-file-signature"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-slate-900">New Device Intake (Job Order)</h3>
                <p class="text-xs text-slate-400">Check in customer device for diagnosis & repair</p>
            </div>
        </div>

        <form method="POST" action="repairs.php" class="space-y-4">
            <input type="hidden" name="action" value="create_ticket">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                
                <!-- Customer Selection -->
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Customer *</label>
                    <select name="customer_id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        <?php foreach ($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name'] . ' (' . $c['phone'] . ')'); ?></option>
                        <?php endforeach; ?>
                        <?php if (empty($customers)): ?>
                            <option value="1">Walk-in Customer</option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Device Type -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Device Type *</label>
                    <select name="device_type" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        <option value="Laptop">Laptop / Notebook</option>
                        <option value="Desktop PC">Desktop Gaming PC / Workstation</option>
                        <option value="GPU">Graphics Card (GPU)</option>
                        <option value="Monitor">Monitor / Display</option>
                        <option value="Smartphone">Smartphone / Tablet</option>
                        <option value="Console">Console / Other</option>
                    </select>
                </div>

                <!-- Device Brand -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Brand *</label>
                    <input type="text" name="device_brand" placeholder="e.g. Dell, Asus, Apple, HP" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>

                <!-- Device Model -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Model *</label>
                    <input type="text" name="device_model" placeholder="e.g. XPS 15 9500 / TUF F15" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>

                <!-- Serial Number -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Serial / Service Tag</label>
                    <input type="text" name="serial_number" placeholder="e.g. SN-892348" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>

                <!-- Passcode / PIN -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Device Passcode / PIN</label>
                    <input type="text" name="passcode_pin" placeholder="For technician testing" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>

                <!-- Assign Technician -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Assign Technician</label>
                    <select name="technician_id" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        <option value="">-- Unassigned (General Queue) --</option>
                        <?php foreach ($technicians as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Accessories Included -->
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Accessories Included</label>
                    <input type="text" name="accessories_included" placeholder="e.g. Power adapter / charger, bag, power cable" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>

                <!-- Issue Description -->
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Reported Issue / Symptoms *</label>
                    <textarea name="issue_description" rows="3" required placeholder="Describe problem reported by customer..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"></textarea>
                </div>

                <!-- Estimated Cost -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Initial Estimate ($)</label>
                    <input type="number" step="0.01" name="estimated_cost" value="0.00" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>

            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 mt-6">
                <button type="button" onclick="closeIntakeModal()" class="px-5 py-2.5 rounded-2xl border border-slate-200 text-slate-700 font-bold text-xs sm:text-sm hover:bg-slate-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs sm:text-sm transition-all shadow-sm shadow-emerald-500/25">
                    Generate Ticket & Print
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Technician Diagnostic & Status Update Modal -->
<div id="manageJobModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-xl p-7 relative max-h-[90vh] overflow-y-auto">
        <button onclick="closeManageJobModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center gap-3 mb-5 pb-3 border-b border-slate-100">
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                <i class="fa-solid fa-screwdriver-wrench"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-slate-900" id="manageTicketTitle">Update Repair Job</h3>
                <p class="text-xs text-slate-400" id="manageTicketSubtitle">Diagnosis, parts cost, and status</p>
            </div>
        </div>

        <form method="POST" action="repairs.php" class="space-y-4">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="job_id" id="manageJobId" value="">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Repair Status *</label>
                <select name="status" id="manageStatus" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                    <option value="Received">Received (Pending Intake)</option>
                    <option value="Diagnosing">Diagnosing (On Workbench)</option>
                    <option value="Waiting for Parts">Waiting for Parts</option>
                    <option value="In Repair">In Repair / Soldering / Replacing</option>
                    <option value="Ready for Pickup">Ready for Pickup (Customer Notified)</option>
                    <option value="Completed">Completed</option>
                    <option value="Closed">Closed & Delivered</option>
                    <option value="Cancelled">Cancelled / Unrepairable</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Diagnostic & Technician Notes</label>
                <textarea name="diagnosis_notes" id="manageDiagnosis" rows="3" placeholder="Tests performed, faults found, parts replaced..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Labor Fee ($)</label>
                    <input type="number" step="0.01" name="labor_fee" id="manageLaborFee" value="0.00" onkeyup="recalcTotal()" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Parts Cost (<?php echo htmlspecialchars($currency_symbol); ?>)</label>
                    <input type="number" step="0.01" name="parts_cost" id="managePartsCost" value="0.00" onkeyup="recalcTotal()" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>
            </div>

            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 flex items-center justify-between">
                <span class="text-xs font-bold text-slate-700">Total Invoice Amount:</span>
                <span class="text-base font-extrabold text-emerald-700" id="manageTotalDisplay"><?php echo htmlspecialchars($currency_symbol); ?> 0.00</span>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" name="is_quote_approved" id="manageIsApproved" value="1" class="w-4 h-4 text-emerald-600 rounded">
                <label for="manageIsApproved" class="text-xs font-bold text-slate-700">Customer Approved Quotation</label>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="closeManageJobModal()" class="px-5 py-2.5 rounded-2xl border border-slate-200 text-slate-700 font-bold text-xs sm:text-sm hover:bg-slate-50 transition-colors">
                    Close
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs sm:text-sm transition-all shadow-sm shadow-emerald-500/25">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openIntakeModal() {
    document.getElementById('intakeModal').classList.remove('hidden');
}
function closeIntakeModal() {
    document.getElementById('intakeModal').classList.add('hidden');
}

function openManageJobModal(job) {
    document.getElementById('manageJobId').value = job.id;
    document.getElementById('manageTicketTitle').textContent = 'Job #' + (job.ticket_no || job.id) + ' (' + job.device_brand + ' ' + job.device_model + ')';
    document.getElementById('manageStatus').value = job.status || 'Received';
    document.getElementById('manageDiagnosis').value = job.diagnosis_notes || '';
    document.getElementById('manageLaborFee').value = parseFloat(job.labor_fee || 0).toFixed(2);
    document.getElementById('managePartsCost').value = parseFloat(job.parts_cost || 0).toFixed(2);
    document.getElementById('manageIsApproved').checked = (job.is_quote_approved == 1);
    recalcTotal();
    document.getElementById('manageJobModal').classList.remove('hidden');
}

function closeManageJobModal() {
    document.getElementById('manageJobModal').classList.add('hidden');
}

function recalcTotal() {
    const labor = parseFloat(document.getElementById('manageLaborFee').value) || 0;
    const parts = parseFloat(document.getElementById('managePartsCost').value) || 0;
    const total = labor + parts;
    document.getElementById('manageTotalDisplay').textContent = (window.CURRENCY_SYMBOL || 'Rs.') + ' ' + total.toFixed(2);
}

function filterRepairs() {
    const query = document.getElementById('repairSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#repairsTable tbody tr');
    rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        r.style.display = text.includes(query) ? '' : 'none';
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
