<?php
require_once '../includes/db.php';
require_once '../includes/header.php';

$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'delete_supplier' && !in_array($role, ['Admin', 'Manager'], true)) {
        abort_request(403, 'Only a manager or administrator may delete suppliers.');
    }

    if ($action === 'add_supplier' && $pdo) {
        try {
            $name = trim($_POST['name'] ?? '');
            $contact = trim($_POST['contact_person'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $terms = trim($_POST['payment_terms'] ?? 'Net 30');
            $balance = max(0, (float)($_POST['balance_due'] ?? 0));

            if ($name === '') {
                throw new InvalidArgumentException('Supplier name is required.');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Enter a valid supplier email address.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO suppliers (name, contact_person, phone, email, address, payment_terms, balance_due) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $contact, $phone, $email, $address, $terms, $balance]);
            $msg = "Supplier \"$name\" added successfully!";
        } catch (Exception $e) {
            $msg = 'Error adding supplier: ' . safe_error_message($e);
            $msg_type = 'error';
        }
    }

    if ($action === 'edit_supplier' && $pdo) {
        try {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $contact = trim($_POST['contact_person'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $terms = trim($_POST['payment_terms'] ?? 'Net 30');
            $balance = max(0, (float)($_POST['balance_due'] ?? 0));

            if ($name === '') {
                throw new InvalidArgumentException('Supplier name is required.');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Enter a valid supplier email address.');
            }

            $stmt = $pdo->prepare("UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, address=?, payment_terms=?, balance_due=? WHERE id=?");
            $stmt->execute([$name, $contact, $phone, $email, $address, $terms, $balance, $id]);
            $msg = "Supplier updated successfully!";
        } catch (Exception $e) {
            try {
                $stmt = $pdo->prepare("UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, address=? WHERE id=?");
                $stmt->execute([$name, $contact, $phone, $email, $address, $id]);
                $msg = "Supplier updated successfully!";
            } catch (Exception $e2) {
                $msg = "Error updating supplier: " . $e2->getMessage();
                $msg_type = 'error';
            }
        }
    }

    if ($action === 'delete_supplier' && $pdo) {
        try {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
            $stmt->execute([$id]);
            $msg = "Supplier deleted successfully.";
        } catch (Exception $e) {
            $msg = "Error deleting supplier: " . safe_error_message($e);
            $msg_type = 'error';
        }
    }
}

// Fetch Suppliers & Purchase Stats
$suppliers = [];
$total_ap = 0.0;
$total_pos = 0;

if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT s.*, 
                   COUNT(p.id) as total_purchases,
                   COALESCE(SUM(p.total_amount), 0) as total_purchase_volume
            FROM suppliers s
            LEFT JOIN purchases p ON s.id = p.supplier_id
            GROUP BY s.id
            ORDER BY s.name ASC
        ");
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($suppliers as $sp) {
            $total_ap += (float)($sp['balance_due'] ?? 0);
            $total_pos += (int)($sp['total_purchases'] ?? 0);
        }
    } catch (Exception $e) {}
}

?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-truck-field text-emerald-600"></i>
                <span>Suppliers & Vendor Management</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Vendor profiles, tax registration, payment terms, and accounts payable ledger.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <a href="purchases.php" class="px-4 py-2.5 rounded-2xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs sm:text-sm font-bold transition-all shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-cart-flatbed text-emerald-600"></i>
                <span>Purchase Orders & GRN</span>
            </a>
            <button onclick="openSupplierModal('add')" class="px-5 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs sm:text-sm font-bold transition-all shadow-sm shadow-emerald-500/25 flex items-center gap-2">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Add Supplier</span>
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

    <!-- AP & Procurement KPI Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-5">
        <div class="bg-white rounded-3xl p-6 shadow-card border border-slate-100/90 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl font-bold">
                <i class="fa-solid fa-building"></i>
            </div>
            <div>
                <h3 class="text-2xl font-extrabold text-slate-900"><?php echo count($suppliers); ?></h3>
                <p class="text-xs text-slate-400 font-medium">Registered Suppliers</p>
            </div>
        </div>

        <div class="bg-white rounded-3xl p-6 shadow-card border border-slate-100/90 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center text-xl font-bold">
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>
            <div>
                <h3 class="text-2xl font-extrabold text-red-600"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($total_ap, 2); ?></h3>
                <p class="text-xs text-slate-400 font-medium">Total Accounts Payable (Due)</p>
            </div>
        </div>

        <div class="bg-white rounded-3xl p-6 shadow-card border border-slate-100/90 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl font-bold">
                <i class="fa-solid fa-boxes-packing"></i>
            </div>
            <div>
                <h3 class="text-2xl font-extrabold text-slate-900"><?php echo $total_pos; ?> Orders</h3>
                <p class="text-xs text-slate-400 font-medium">Procurement Orders</p>
            </div>
        </div>
    </div>

    <!-- Suppliers Table Card -->
    <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7 overflow-hidden">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <h2 class="text-lg font-bold text-slate-900">Supplier Directory & Scorecards</h2>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input 
                    type="text" 
                    id="supplierSearch" 
                    onkeyup="filterSuppliers()" 
                    placeholder="Search supplier, contact, email..." 
                    class="pl-9 pr-4 py-2 bg-slate-50 border border-slate-200/80 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 w-64"
                >
            </div>
        </div>

        <div class="overflow-x-auto -mx-6 sm:-mx-7 px-6 sm:px-7">
            <table class="w-full text-left border-collapse" id="suppliersTable">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 pr-4 pl-2">Supplier Name</th>
                        <th class="py-3.5 px-4">Contact Person</th>
                        <th class="py-3.5 px-4">Phone / Email</th>
                        <th class="py-3.5 px-4">Terms</th>
                        <th class="py-3.5 px-4">Purchase Volume</th>
                        <th class="py-3.5 px-4">Balance Due</th>
                        <th class="py-3.5 pl-4 pr-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 text-xs sm:text-sm text-slate-700">
                    <?php foreach ($suppliers as $s): 
                        $supp_name = $s['name'] ?? 'Supplier';
                        $supp_contact = $s['contact_person'] ?? '-';
                        $supp_phone = $s['phone'] ?? '';
                        $supp_email = $s['email'] ?? '';
                        $supp_address = $s['address'] ?? '';
                        $supp_terms = $s['payment_terms'] ?? 'Net 30';
                        $supp_bal = (float)($s['balance_due'] ?? 0);
                        $supp_vol = (float)($s['total_purchase_volume'] ?? 0);
                        $supp_pos = (int)($s['total_purchases'] ?? 0);
                    ?>
                    <tr class="hover:bg-slate-50/60 transition-colors group">
                        
                        <!-- Name -->
                        <td class="py-4 pr-4 pl-2 font-bold text-slate-900">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-2xl bg-emerald-50 text-emerald-700 font-bold flex items-center justify-center text-xs shrink-0 border border-emerald-100">
                                    <i class="fa-solid fa-building"></i>
                                </div>
                                <div>
                                    <span class="block font-bold text-slate-900"><?php echo htmlspecialchars($supp_name); ?></span>
                                    <span class="block text-[11px] text-slate-400 font-normal truncate max-w-xs"><?php echo htmlspecialchars($supp_address); ?></span>
                                </div>
                            </div>
                        </td>

                        <!-- Contact -->
                        <td class="py-4 px-4 font-medium text-slate-800">
                            <?php echo htmlspecialchars($supp_contact); ?>
                        </td>

                        <!-- Phone / Email -->
                        <td class="py-4 px-4">
                            <p class="font-medium text-slate-800"><?php echo htmlspecialchars($supp_phone); ?></p>
                            <p class="text-xs text-slate-400"><?php echo htmlspecialchars($supp_email); ?></p>
                        </td>

                        <!-- Terms -->
                        <td class="py-4 px-4">
                            <span class="inline-block text-xs font-semibold bg-slate-100 px-3 py-1 rounded-xl text-slate-600">
                                <?php echo htmlspecialchars($supp_terms); ?>
                            </span>
                        </td>

                        <!-- Volume -->
                        <td class="py-4 px-4 font-bold text-slate-800">
                            <?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($supp_vol, 2); ?>
                            <span class="block text-[10px] text-slate-400 font-normal"><?php echo $supp_pos; ?> Orders</span>
                        </td>

                        <!-- Balance Due -->
                        <td class="py-4 px-4 font-bold <?php echo $supp_bal > 0 ? 'text-red-600' : 'text-slate-800'; ?>">
                            <?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($supp_bal, 2); ?>
                        </td>

                        <!-- Actions -->
                        <td class="py-4 pl-4 pr-2 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <button onclick="openSupplierModal('edit', <?php echo htmlspecialchars(json_encode($s)); ?>)" class="w-8 h-8 rounded-xl border border-slate-200 text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 flex items-center justify-center transition-colors text-xs">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <?php if (in_array($role, ['Admin', 'Manager'], true)): ?>
                                <form method="POST" action="suppliers.php" onsubmit="return confirm('Delete supplier <?php echo addslashes($supp_name); ?>?');" class="inline">
                                    <input type="hidden" name="action" value="delete_supplier">
                                    <input type="hidden" name="id" value="<?php echo $s['id'] ?? ''; ?>">
                                    <button type="submit" class="w-8 h-8 rounded-xl border border-slate-200 text-slate-500 hover:text-red-600 hover:bg-red-50 flex items-center justify-center transition-colors text-xs">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>

                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

</div>

<!-- Supplier Modal (Add / Edit) -->
<div id="supplierModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-lg p-7 relative max-h-[90vh] overflow-y-auto">
        <button onclick="closeSupplierModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center gap-3 mb-6 pb-3 border-b border-slate-100">
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                <i class="fa-solid fa-truck-field"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-slate-900" id="suppModalTitle">Add Supplier</h3>
                <p class="text-xs text-slate-400">Vendor details and payment terms</p>
            </div>
        </div>

        <form method="POST" action="suppliers.php" class="space-y-4">
            <input type="hidden" name="action" id="suppModalAction" value="add_supplier">
            <input type="hidden" name="id" id="suppModalId" value="">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Supplier / Company Name *</label>
                <input type="text" name="name" id="suppName" required placeholder="e.g. Tech Distro Direct" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Contact Person</label>
                    <input type="text" name="contact_person" id="suppContact" placeholder="e.g. John Smith" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Payment Terms</label>
                    <input type="text" name="payment_terms" id="suppTerms" placeholder="e.g. Net 30, COD, Net 15" value="Net 30" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Phone Number</label>
                    <input type="text" name="phone" id="suppPhone" placeholder="+94 77 123 4567" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Email Address</label>
                    <input type="email" name="email" id="suppEmail" placeholder="orders@supplier.com" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Warehouse / Office Address</label>
                <textarea name="address" id="suppAddress" rows="2" placeholder="Street, City, Country" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Current Balance Due / Accounts Payable ($)</label>
                <input type="number" step="0.01" name="balance_due" id="suppBalance" value="0.00" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="closeSupplierModal()" class="px-5 py-2.5 rounded-2xl border border-slate-200 text-slate-700 font-bold text-xs sm:text-sm hover:bg-slate-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs sm:text-sm transition-all shadow-sm shadow-emerald-500/25">
                    Save Supplier
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openSupplierModal(mode, data = null) {
    if (mode === 'edit' && data) {
        document.getElementById('suppModalTitle').textContent = 'Edit Supplier';
        document.getElementById('suppModalAction').value = 'edit_supplier';
        document.getElementById('suppModalId').value = data.id || '';
        document.getElementById('suppName').value = data.name || '';
        document.getElementById('suppContact').value = data.contact_person || '';
        document.getElementById('suppTerms').value = data.payment_terms || 'Net 30';
        document.getElementById('suppPhone').value = data.phone || '';
        document.getElementById('suppEmail').value = data.email || '';
        document.getElementById('suppAddress').value = data.address || '';
        document.getElementById('suppBalance').value = parseFloat(data.balance_due || 0).toFixed(2);
    } else {
        document.getElementById('suppModalTitle').textContent = 'Add Supplier';
        document.getElementById('suppModalAction').value = 'add_supplier';
        document.getElementById('suppModalId').value = '';
        document.getElementById('suppName').value = '';
        document.getElementById('suppContact').value = '';
        document.getElementById('suppTerms').value = 'Net 30';
        document.getElementById('suppPhone').value = '';
        document.getElementById('suppEmail').value = '';
        document.getElementById('suppAddress').value = '';
        document.getElementById('suppBalance').value = '0.00';
    }
    document.getElementById('supplierModal').classList.remove('hidden');
}

function closeSupplierModal() {
    document.getElementById('supplierModal').classList.add('hidden');
}

function filterSuppliers() {
    const q = document.getElementById('supplierSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#suppliersTable tbody tr');
    rows.forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
hp'; ?>
