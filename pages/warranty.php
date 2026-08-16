<?php
require_once '../includes/db.php';
require_once '../includes/header.php';

$msg = '';
$msg_type = 'success';

// Handle POST actions for Warranty Claims
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create_claim' && $pdo) {
        try {
            $claim_no = 'RMA-' . date('ymd') . '-' . rand(100, 999);
            $serial_no = trim($_POST['serial_number'] ?? '');
            $customer_id = (int)$_POST['customer_id'];
            $issue = trim($_POST['issue'] ?? '');
            $claim_type = $_POST['claim_type'] ?? 'In-House Repair';

            // Find serial id
            $stmt_s = $pdo->prepare("SELECT id FROM product_serials WHERE serial_number = ? LIMIT 1");
            $stmt_s->execute([$serial_no]);
            $serial_id = $stmt_s->fetchColumn() ?: null;

            $stmt = $pdo->prepare("
                INSERT INTO warranty_claims (claim_no, product_serial_id, customer_id, issue, claim_type, status, claim_date) 
                VALUES (?, ?, ?, ?, ?, 'Pending', CURDATE())
            ");
            $stmt->execute([$claim_no, $serial_id, $customer_id, $issue, $claim_type]);
            $msg = "Warranty Claim #$claim_no submitted successfully!";
        } catch (Exception $e) {
            $msg = "Error creating claim: " . $e->getMessage();
            $msg_type = 'error';
        }
    }

    if ($action === 'update_claim' && $pdo) {
        try {
            $id = (int)$_POST['id'];
            $status = $_POST['status'];
            $resolution = trim($_POST['resolution'] ?? '');

            $stmt = $pdo->prepare("UPDATE warranty_claims SET status = ?, resolution = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $resolution, $id]);
            $msg = "Warranty claim status updated!";
        } catch (Exception $e) {
            $msg = "Error updating claim: " . $e->getMessage();
            $msg_type = 'error';
        }
    }
}

// Live Serial Lookup Query
$lookup_serial = trim($_GET['serial'] ?? '');
$serial_info = null;

if (!empty($lookup_serial) && $pdo) {
    try {
        $stmt_l = $pdo->prepare("
            SELECT ps.*, p.name as product_name, p.product_code, p.warranty_months, b.name as brand_name,
                   c.name as category_name
            FROM product_serials ps
            JOIN products p ON ps.product_id = p.id
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE ps.serial_number = ?
            LIMIT 1
        ");
        $stmt_l->execute([$lookup_serial]);
        $serial_info = $stmt_l->fetch();
    } catch (Exception $e) {}
}

// Fetch Customers & Claims
$customers = [];
$claims = [];
$stats = ['pending' => 0, 'rma' => 0, 'resolved' => 0];

if ($pdo) {
    try {
        $customers = $pdo->query("SELECT id, name, phone FROM customers ORDER BY name ASC")->fetchAll();

        $stmt_c = $pdo->query("
            SELECT wc.*, c.name as customer_name, c.phone as customer_phone,
                   ps.serial_number, p.name as product_name
            FROM warranty_claims wc
            LEFT JOIN customers c ON wc.customer_id = c.id
            LEFT JOIN product_serials ps ON wc.product_serial_id = ps.id
            LEFT JOIN products p ON ps.product_id = p.id
            ORDER BY wc.id DESC
        ");
        $claims = $stmt_c->fetchAll();

        foreach ($claims as $cl) {
            if ($cl['status'] === 'Pending') $stats['pending']++;
            if ($cl['status'] === 'In Supplier RMA') $stats['rma']++;
            if (in_array($cl['status'], ['Repaired', 'Replaced', 'Refunded'])) $stats['resolved']++;
        }
    } catch (Exception $e) {}
}

if (empty($claims) && !$pdo) {
    $claims = [
        [
            'id' => 1,
            'claim_no' => 'RMA-260816-110',
            'customer_name' => 'Alice Wonderland',
            'customer_phone' => '555-1234',
            'serial_number' => 'SN-DXPS15-001',
            'product_name' => 'Dell XPS 15',
            'issue' => 'Screen flickering horizontally under 60Hz load.',
            'claim_type' => 'Supplier RMA',
            'status' => 'In Supplier RMA',
            'resolution' => 'Sent to Dell Authorized Service Center Colombo on Aug 14.',
            'claim_date' => date('Y-m-d', strtotime('-2 days'))
        ],
        [
            'id' => 2,
            'claim_no' => 'RMA-260816-111',
            'customer_name' => 'Michael Chang',
            'customer_phone' => '555-9876',
            'serial_number' => 'SN-ASVG248-001',
            'product_name' => 'Asus VG248QE 24" Monitor',
            'issue' => 'Dead pixels on top right quadrant.',
            'claim_type' => 'Replacement',
            'status' => 'Approved',
            'resolution' => 'Direct replacement authorized from inventory.',
            'claim_date' => date('Y-m-d', strtotime('-1 day'))
        ]
    ];
    $stats = ['pending' => 1, 'rma' => 1, 'resolved' => 2];
}
?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <!-- Top Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-shield-halved text-emerald-600"></i>
                <span>Warranty & RMA Management</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Serial-based warranty lookup, customer claim filings, and supplier RMA workflows.</p>
        </div>
        
        <button onclick="openClaimModal()" class="px-5 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs sm:text-sm font-bold transition-all shadow-sm shadow-emerald-500/25 flex items-center gap-2">
            <i class="fa-solid fa-plus text-xs"></i>
            <span>File Warranty Claim</span>
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="<?php echo $msg_type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800'; ?> border px-4 py-3 rounded-2xl text-xs sm:text-sm flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <i class="fa-solid <?php echo $msg_type === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-red-600'; ?>"></i>
                <span><?php echo htmlspecialchars($msg); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Serial Warranty Quick Lookup Box -->
    <div class="bg-white rounded-3xl p-6 sm:p-7 shadow-card border border-slate-100/90">
        <h2 class="text-base font-bold text-slate-900 mb-2 flex items-center gap-2">
            <i class="fa-solid fa-barcode text-emerald-600"></i>
            <span>Instant Serial Number Warranty Checker</span>
        </h2>
        <p class="text-xs text-slate-400 mb-4">Scan or enter any product serial / IMEI to check warranty status and history.</p>

        <form method="GET" action="warranty.php" class="flex flex-col sm:flex-row gap-3 max-w-xl">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input 
                    type="text" 
                    name="serial" 
                    value="<?php echo htmlspecialchars($lookup_serial); ?>" 
                    placeholder="e.g. SN-DXPS15-001" 
                    required
                    class="w-full pl-11 pr-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                >
            </div>
            <button type="submit" class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs sm:text-sm rounded-2xl transition-colors shrink-0">
                Check Warranty
            </button>
        </form>

        <?php if ($serial_info): 
            $warranty_status = 'Under Warranty';
            $w_color = 'bg-emerald-50 text-emerald-700 border-emerald-200';
            if ($serial_info['status'] === 'defective') {
                $warranty_status = 'Defective';
                $w_color = 'bg-red-50 text-red-700 border-red-200';
            }
        ?>
        <div class="mt-6 p-5 bg-emerald-50/40 rounded-2xl border border-emerald-100/80 text-xs sm:text-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 mb-3 border-b border-emerald-100/60 gap-2">
                <div>
                    <h3 class="font-bold text-slate-900 text-base"><?php echo htmlspecialchars($serial_info['product_name']); ?></h3>
                    <p class="text-slate-500 font-mono text-xs">Serial: <?php echo htmlspecialchars($serial_info['serial_number']); ?> | Code: <?php echo htmlspecialchars($serial_info['product_code']); ?></p>
                </div>
                <span class="inline-block border font-bold px-3 py-1 rounded-xl <?php echo $w_color; ?>">
                    <?php echo $warranty_status; ?> (<?php echo $serial_info['warranty_months']; ?> Months Coverage)
                </span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                <div>
                    <span class="text-slate-400 font-medium block">Brand</span>
                    <span class="font-bold text-slate-800"><?php echo htmlspecialchars($serial_info['brand_name'] ?? 'Generic'); ?></span>
                </div>
                <div>
                    <span class="text-slate-400 font-medium block">Category</span>
                    <span class="font-bold text-slate-800"><?php echo htmlspecialchars($serial_info['category_name'] ?? 'Hardware'); ?></span>
                </div>
                <div>
                    <span class="text-slate-400 font-medium block">Current Status</span>
                    <span class="font-bold text-slate-800 capitalize"><?php echo str_replace('_', ' ', $serial_info['status']); ?></span>
                </div>
                <div class="text-right">
                    <button onclick="prefillClaim('<?php echo htmlspecialchars($serial_info['serial_number']); ?>')" class="text-emerald-600 hover:text-emerald-800 font-bold underline">
                        File Claim for this Serial &rarr;
                    </button>
                </div>
            </div>
        </div>
        <?php elseif (!empty($lookup_serial)): ?>
            <div class="mt-4 p-3 bg-amber-50 text-amber-700 text-xs rounded-xl font-medium border border-amber-100">
                <i class="fa-solid fa-triangle-exclamation mr-1"></i> No matching serial number found in store records.
            </div>
        <?php endif; ?>
    </div>

    <!-- RMA Claims Table Card -->
    <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7 overflow-hidden">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <h2 class="text-lg font-bold text-slate-900">Active RMA & Warranty Claims</h2>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input 
                    type="text" 
                    id="claimSearch" 
                    onkeyup="filterClaims()" 
                    placeholder="Search RMA #, serial, customer..." 
                    class="pl-9 pr-4 py-2 bg-slate-50 border border-slate-200/80 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 w-64"
                >
            </div>
        </div>

        <div class="overflow-x-auto -mx-6 sm:-mx-7 px-6 sm:px-7">
            <table class="w-full text-left border-collapse" id="claimsTable">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 pr-4 pl-2">Claim #</th>
                        <th class="py-3.5 px-4">Serial / Product</th>
                        <th class="py-3.5 px-4">Customer</th>
                        <th class="py-3.5 px-4">Claim Type</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4">Issue Details</th>
                        <th class="py-3.5 pl-4 pr-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 text-xs sm:text-sm text-slate-700">
                    <?php foreach ($claims as $cl): 
                        $st_class = [
                            'Pending' => 'bg-amber-50 text-amber-700 border-amber-200',
                            'Approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            'In Supplier RMA' => 'bg-blue-50 text-blue-700 border-blue-200',
                            'Repaired' => 'bg-purple-50 text-purple-700 border-purple-200',
                            'Replaced' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                            'Refunded' => 'bg-slate-100 text-slate-700 border-slate-200',
                            'Rejected' => 'bg-red-50 text-red-700 border-red-200'
                        ][$cl['status']] ?? 'bg-slate-100 text-slate-600 border-slate-200';
                    ?>
                    <tr class="hover:bg-slate-50/60 transition-colors group">
                        
                        <!-- Claim No -->
                        <td class="py-4 pr-4 pl-2 font-mono font-bold text-emerald-700">
                            <?php echo htmlspecialchars($cl['claim_no']); ?>
                            <span class="block text-[10px] text-slate-400 font-sans font-normal"><?php echo date('M j, Y', strtotime($cl['claim_date'])); ?></span>
                        </td>

                        <!-- Product & Serial -->
                        <td class="py-4 px-4">
                            <p class="font-bold text-slate-900"><?php echo htmlspecialchars($cl['product_name'] ?? 'Hardware Item'); ?></p>
                            <p class="text-xs text-slate-400 font-mono">SN: <?php echo htmlspecialchars($cl['serial_number'] ?? 'N/A'); ?></p>
                        </td>

                        <!-- Customer -->
                        <td class="py-4 px-4">
                            <p class="font-bold text-slate-800"><?php echo htmlspecialchars($cl['customer_name'] ?? 'Walk-in'); ?></p>
                            <p class="text-xs text-slate-400"><?php echo htmlspecialchars($cl['customer_phone'] ?? ''); ?></p>
                        </td>

                        <!-- Claim Type -->
                        <td class="py-4 px-4">
                            <span class="inline-block text-xs font-semibold bg-slate-100 px-3 py-1 rounded-xl text-slate-700">
                                <?php echo htmlspecialchars($cl['claim_type']); ?>
                            </span>
                        </td>

                        <!-- Status -->
                        <td class="py-4 px-4 text-center">
                            <span class="inline-block border text-xs font-bold px-3 py-1 rounded-xl <?php echo $st_class; ?>">
                                <?php echo htmlspecialchars($cl['status']); ?>
                            </span>
                        </td>

                        <!-- Issue Details -->
                        <td class="py-4 px-4 max-w-xs truncate text-slate-600">
                            <?php echo htmlspecialchars($cl['issue']); ?>
                        </td>

                        <!-- Actions -->
                        <td class="py-4 pl-4 pr-2 text-right">
                            <button onclick="openEditClaimModal(<?php echo htmlspecialchars(json_encode($cl)); ?>)" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-emerald-500 hover:text-white font-bold text-xs transition-colors">
                                Update
                            </button>
                        </td>

                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

</div>

<!-- Modal 1: Create Claim Modal -->
<div id="claimModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-lg p-7 relative max-h-[90vh] overflow-y-auto">
        <button onclick="closeClaimModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center gap-3 mb-6 pb-3 border-b border-slate-100">
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-slate-900">File Warranty / RMA Claim</h3>
                <p class="text-xs text-slate-400">Initiate replacement, repair, or supplier RMA</p>
            </div>
        </div>

        <form method="POST" action="warranty.php" class="space-y-4">
            <input type="hidden" name="action" value="create_claim">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Product Serial Number *</label>
                <input type="text" name="serial_number" id="claimSerial" required placeholder="e.g. SN-DXPS15-001" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
            </div>

            <div>
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

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Claim Type *</label>
                <select name="claim_type" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                    <option value="In-House Repair">In-House Repair Service</option>
                    <option value="Supplier RMA">Supplier RMA Return</option>
                    <option value="Replacement">Direct Store Replacement</option>
                    <option value="Refund">Refund Authorization</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Issue Description *</label>
                <textarea name="issue" rows="3" required placeholder="Describe the defect, failure reason, diagnostic result..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"></textarea>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="closeClaimModal()" class="px-5 py-2.5 rounded-2xl border border-slate-200 text-slate-700 font-bold text-xs sm:text-sm hover:bg-slate-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs sm:text-sm transition-all shadow-sm shadow-emerald-500/25">
                    Submit RMA Claim
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Edit Claim Modal -->
<div id="editClaimModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-lg p-7 relative">
        <button onclick="closeEditClaimModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-lg font-bold text-slate-900 mb-4 pb-3 border-b border-slate-100" id="editClaimTitle">Update Claim Status</h3>

        <form method="POST" action="warranty.php" class="space-y-4">
            <input type="hidden" name="action" value="update_claim">
            <input type="hidden" name="id" id="editClaimId" value="">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Claim Status *</label>
                <select name="status" id="editClaimStatus" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                    <option value="Pending">Pending Evaluation</option>
                    <option value="Approved">Approved</option>
                    <option value="In Supplier RMA">In Supplier RMA</option>
                    <option value="Repaired">Repaired & Tested</option>
                    <option value="Replaced">Replaced with New Unit</option>
                    <option value="Refunded">Refund Processed</option>
                    <option value="Rejected">Rejected / Void Warranty</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Resolution Notes</label>
                <textarea name="resolution" id="editClaimResolution" rows="3" placeholder="RMA tracking no, courier details, replacement serial..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"></textarea>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="closeEditClaimModal()" class="px-5 py-2.5 rounded-2xl border border-slate-200 text-slate-700 font-bold text-xs sm:text-sm hover:bg-slate-50 transition-colors">
                    Close
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs sm:text-sm transition-all shadow-sm shadow-emerald-500/25">
                    Save Resolution
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openClaimModal() {
    document.getElementById('claimModal').classList.remove('hidden');
}
function closeClaimModal() {
    document.getElementById('claimModal').classList.add('hidden');
}

function prefillClaim(serial) {
    document.getElementById('claimSerial').value = serial;
    openClaimModal();
}

function openEditClaimModal(claim) {
    document.getElementById('editClaimId').value = claim.id;
    document.getElementById('editClaimTitle').textContent = 'Update ' + claim.claim_no;
    document.getElementById('editClaimStatus').value = claim.status || 'Pending';
    document.getElementById('editClaimResolution').value = claim.resolution || '';
    document.getElementById('editClaimModal').classList.remove('hidden');
}
function closeEditClaimModal() {
    document.getElementById('editClaimModal').classList.add('hidden');
}

function filterClaims() {
    const q = document.getElementById('claimSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#claimsTable tbody tr');
    rows.forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
