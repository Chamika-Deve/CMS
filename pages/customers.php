<?php
require_once '../includes/db.php';
require_once '../includes/header.php';

$msg = '';
$msg_type = 'success';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'delete_customer' && !in_array($role, ['Admin', 'Manager'], true)) {
        abort_request(403, 'Only a manager or administrator may delete customers.');
    }

    if ($action === 'add_customer' && $pdo) {
        try {
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $company = trim($_POST['company_name'] ?? '');
            $type = $_POST['customer_type'] ?? 'Individual';
            $credit_limit = (float)($_POST['credit_limit'] ?? 0);
            $store_credit = max(0, (float)($_POST['store_credit'] ?? 0));

            if ($name === '' || $phone === '') {
                throw new InvalidArgumentException('Customer name and phone are required.');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Enter a valid customer email address.');
            }
            if (!in_array($type, ['Individual', 'Corporate', 'VIP'], true)) {
                throw new InvalidArgumentException('Invalid customer type.');
            }
            $credit_limit = max(0, $credit_limit);

            $stmt = $pdo->prepare("INSERT INTO customers (name, phone, email, address, company_name, customer_type, credit_limit, store_credit) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $email, $address, $company, $type, $credit_limit, $store_credit]);
            $msg = "Customer \"$name\" added successfully!";
        } catch (Exception $e) {
            $msg = 'Error adding customer: ' . safe_error_message($e);
            $msg_type = 'error';
        }
    }

    if ($action === 'edit_customer' && $pdo) {
        try {
            $id = (int)$_POST['id'];
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $company = trim($_POST['company_name'] ?? '');
            $type = $_POST['customer_type'] ?? 'Individual';
            $credit_limit = max(0, (float)($_POST['credit_limit'] ?? 0));
            $store_credit = max(0, (float)($_POST['store_credit'] ?? 0));

            if ($name === '' || $phone === '') {
                throw new InvalidArgumentException('Customer name and phone are required.');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Enter a valid customer email address.');
            }
            if (!in_array($type, ['Individual', 'Corporate', 'VIP'], true)) {
                throw new InvalidArgumentException('Invalid customer type.');
            }

            $stmt = $pdo->prepare("UPDATE customers SET name=?, phone=?, email=?, address=?, company_name=?, customer_type=?, credit_limit=?, store_credit=? WHERE id=?");
            $stmt->execute([$name, $phone, $email, $address, $company, $type, $credit_limit, $store_credit, $id]);
            $msg = "Customer updated successfully!";
        } catch (Exception $e) {
            try {
                $stmt = $pdo->prepare("UPDATE customers SET name=?, phone=?, email=?, address=? WHERE id=?");
                $stmt->execute([$name, $phone, $email, $address, $id]);
                $msg = "Customer updated successfully!";
            } catch (Exception $e2) {
                $msg = "Error updating customer: " . $e2->getMessage();
                $msg_type = 'error';
            }
        }
    }

    if ($action === 'delete_customer' && $pdo) {
        try {
            $id = (int)$_POST['id'];
            if ($id === 1) {
                $msg = "Cannot delete default walk-in customer.";
                $msg_type = 'error';
            } else {
                $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
                $stmt->execute([$id]);
                $msg = "Customer deleted successfully.";
            }
        } catch (Exception $e) {
            $msg = "Error deleting customer: " . safe_error_message($e);
            $msg_type = 'error';
        }
    }
}

// Fetch Customers with their Sales & Repairs stats
$customers = [];
$total_customers = 0;
$total_points = 0;
$total_credit = 0;

if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT c.*,
                   COUNT(DISTINCT s.id) as total_purchases,
                   COALESCE(SUM(s.total_amount), 0) as total_spent,
                   COUNT(DISTINCT r.id) as total_repairs
            FROM customers c
            LEFT JOIN sales s ON c.id = s.customer_id AND s.status = 'Completed'
            LEFT JOIN repair_jobs r ON c.id = r.customer_id
            GROUP BY c.id
            ORDER BY c.id DESC
        ");
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_customers = count($customers);
        foreach ($customers as $c) {
            $total_points += (int)($c['points'] ?? 0);
            $total_credit += (float)($c['store_credit'] ?? 0);
        }
    } catch (Exception $e) {}
}

?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <!-- Top Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-users text-emerald-600"></i>
                <span>Customer Management & CRM</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Individual & corporate profiles, purchase history, store credit, and loyalty points.</p>
        </div>
        
        <button onclick="openCustomerModal('add')" class="px-5 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs sm:text-sm font-bold transition-all shadow-sm shadow-emerald-500/25 flex items-center gap-2">
            <i class="fa-solid fa-user-plus text-xs"></i>
            <span>Add New Customer</span>
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

    <!-- CRM Overview Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-5">
        <div class="bg-white rounded-3xl p-6 shadow-card border border-slate-100/90 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl font-bold">
                <i class="fa-solid fa-users"></i>
            </div>
            <div>
                <h3 class="text-2xl font-extrabold text-slate-900"><?php echo number_format($total_customers); ?></h3>
                <p class="text-xs text-slate-400 font-medium">Registered Customers</p>
            </div>
        </div>

        <div class="bg-white rounded-3xl p-6 shadow-card border border-slate-100/90 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl font-bold">
                <i class="fa-solid fa-coins"></i>
            </div>
            <div>
                <h3 class="text-2xl font-extrabold text-slate-900"><?php echo number_format($total_points); ?></h3>
                <p class="text-xs text-slate-400 font-medium">Total Loyalty Points</p>
            </div>
        </div>

        <div class="bg-white rounded-3xl p-6 shadow-card border border-slate-100/90 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl font-bold">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <div>
                <h3 class="text-2xl font-extrabold text-slate-900"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($total_credit, 2); ?></h3>
                <p class="text-xs text-slate-400 font-medium">Total Store Credit Balance</p>
            </div>
        </div>
    </div>

    <!-- Customers Table Card -->
    <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7 overflow-hidden">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <h2 class="text-lg font-bold text-slate-900">Customer Directory</h2>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input 
                    type="text" 
                    id="customerSearch" 
                    onkeyup="filterCustomers()" 
                    placeholder="Search name, phone, company..." 
                    class="pl-9 pr-4 py-2 bg-slate-50 border border-slate-200/80 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 w-64"
                >
            </div>
        </div>

        <div class="overflow-x-auto -mx-6 sm:-mx-7 px-6 sm:px-7">
            <table class="w-full text-left border-collapse" id="customersTable">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 pr-4 pl-2">Customer</th>
                        <th class="py-3.5 px-4">Contact Info</th>
                        <th class="py-3.5 px-4 text-center">Type</th>
                        <th class="py-3.5 px-4 text-center">Purchases / Repairs</th>
                        <th class="py-3.5 px-4">Total Spent</th>
                        <th class="py-3.5 px-4">Store Credit</th>
                        <th class="py-3.5 pl-4 pr-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 text-xs sm:text-sm text-slate-700">
                    <?php foreach ($customers as $c): 
                        $cust_type = $c['customer_type'] ?? 'Individual';
                        $cust_name = $c['name'] ?? 'Customer';
                        $cust_phone = $c['phone'] ?? '';
                        $cust_email = $c['email'] ?? '';
                        $cust_company = $c['company_name'] ?? '';
                        $cust_spent = (float)($c['total_spent'] ?? 0);
                        $cust_credit = (float)($c['store_credit'] ?? 0);
                        $cust_purchases = (int)($c['total_purchases'] ?? 0);
                        $cust_repairs = (int)($c['total_repairs'] ?? 0);
                    ?>
                    <tr class="hover:bg-slate-50/60 transition-colors group">
                        
                        <!-- Customer Name & Avatar -->
                        <td class="py-4 pr-4 pl-2 font-semibold text-slate-900">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-emerald-50 text-emerald-700 font-bold flex items-center justify-center text-xs shrink-0 border border-emerald-100">
                                    <?php echo strtoupper(substr($cust_name, 0, 2)); ?>
                                </div>
                                <div>
                                    <span class="block font-bold text-slate-900"><?php echo htmlspecialchars($cust_name); ?></span>
                                    <?php if (!empty($cust_company)): ?>
                                        <span class="block text-[11px] text-slate-400 font-medium"><?php echo htmlspecialchars($cust_company); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <!-- Contact -->
                        <td class="py-4 px-4">
                            <p class="font-medium text-slate-800"><?php echo htmlspecialchars($cust_phone); ?></p>
                            <p class="text-xs text-slate-400 truncate max-w-xs"><?php echo htmlspecialchars($cust_email); ?></p>
                        </td>

                        <!-- Type Badge -->
                        <td class="py-4 px-4 text-center">
                            <span class="inline-block text-[11px] font-bold px-2.5 py-1 rounded-xl <?php echo $cust_type === 'Corporate' ? 'bg-purple-50 text-purple-700 border border-purple-200' : 'bg-slate-100 text-slate-600 border border-slate-200'; ?>">
                                <?php echo htmlspecialchars($cust_type); ?>
                            </span>
                        </td>

                        <!-- Activity Counts -->
                        <td class="py-4 px-4 text-center">
                            <span class="font-bold text-slate-800"><?php echo $cust_purchases; ?> sales</span> / 
                            <span class="font-bold text-emerald-600"><?php echo $cust_repairs; ?> repairs</span>
                        </td>

                        <!-- Total Spent -->
                        <td class="py-4 px-4 font-bold text-slate-900">
                            <?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($cust_spent, 2); ?>
                        </td>

                        <!-- Store Credit -->
                        <td class="py-4 px-4 font-bold text-emerald-700">
                            <?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($cust_credit, 2); ?>
                        </td>

                        <!-- Actions -->
                        <td class="py-4 pl-4 pr-2 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <button onclick="openCustomerModal('edit', <?php echo htmlspecialchars(json_encode($c)); ?>)" class="w-8 h-8 rounded-xl border border-slate-200 text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 flex items-center justify-center transition-colors text-xs">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <?php if (($c['id'] ?? 0) != 1 && in_array($role, ['Admin', 'Manager'], true)): ?>
                                <form method="POST" action="customers.php" onsubmit="return confirm('Delete customer <?php echo addslashes($cust_name); ?>?');" class="inline">
                                    <input type="hidden" name="action" value="delete_customer">
                                    <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
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

<!-- Customer Modal (Add / Edit) -->
<div id="customerModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-lg p-7 relative max-h-[90vh] overflow-y-auto">
        <button onclick="closeCustomerModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center gap-3 mb-6 pb-3 border-b border-slate-100">
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                <i class="fa-solid fa-user-gear"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-slate-900" id="custModalTitle">Add Customer</h3>
                <p class="text-xs text-slate-400">Customer profile and credit details</p>
            </div>
        </div>

        <form method="POST" action="customers.php" class="space-y-4">
            <input type="hidden" name="action" id="custModalAction" value="add_customer">
            <input type="hidden" name="id" id="custModalId" value="">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Full Name *</label>
                <input type="text" name="name" id="custName" required placeholder="e.g. Alice Wonderland" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Phone Number *</label>
                    <input type="text" name="phone" id="custPhone" required placeholder="e.g. 555-1234" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Customer Type</label>
                    <select name="customer_type" id="custType" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        <option value="Individual">Individual</option>
                        <option value="Corporate">Corporate / B2B</option>
                        <option value="VIP">VIP</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Email Address</label>
                <input type="email" name="email" id="custEmail" placeholder="e.g. client@example.com" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Company / Business Name</label>
                <input type="text" name="company_name" id="custCompany" placeholder="Optional for corporate accounts" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Billing / Delivery Address</label>
                <textarea name="address" id="custAddress" rows="2" placeholder="Street, City, Postal Code" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Credit Limit (<?php echo htmlspecialchars($currency_symbol); ?>)</label>
                    <input type="number" step="0.01" name="credit_limit" id="custCreditLimit" value="0.00" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Store Credit (<?php echo htmlspecialchars($currency_symbol); ?>)</label>
                    <input type="number" step="0.01" name="store_credit" id="custStoreCredit" value="0.00" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="closeCustomerModal()" class="px-5 py-2.5 rounded-2xl border border-slate-200 text-slate-700 font-bold text-xs sm:text-sm hover:bg-slate-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs sm:text-sm transition-all shadow-sm shadow-emerald-500/25">
                    Save Customer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCustomerModal(mode, data = null) {
    if (mode === 'edit' && data) {
        document.getElementById('custModalTitle').textContent = 'Edit Customer';
        document.getElementById('custModalAction').value = 'edit_customer';
        document.getElementById('custModalId').value = data.id || '';
        document.getElementById('custName').value = data.name || '';
        document.getElementById('custPhone').value = data.phone || '';
        document.getElementById('custEmail').value = data.email || '';
        document.getElementById('custCompany').value = data.company_name || '';
        document.getElementById('custType').value = data.customer_type || 'Individual';
        document.getElementById('custAddress').value = data.address || '';
        document.getElementById('custCreditLimit').value = parseFloat(data.credit_limit || 0).toFixed(2);
        document.getElementById('custStoreCredit').value = parseFloat(data.store_credit || 0).toFixed(2);
    } else {
        document.getElementById('custModalTitle').textContent = 'Add New Customer';
        document.getElementById('custModalAction').value = 'add_customer';
        document.getElementById('custModalId').value = '';
        document.getElementById('custName').value = '';
        document.getElementById('custPhone').value = '';
        document.getElementById('custEmail').value = '';
        document.getElementById('custCompany').value = '';
        document.getElementById('custType').value = 'Individual';
        document.getElementById('custAddress').value = '';
        document.getElementById('custCreditLimit').value = '0.00';
        document.getElementById('custStoreCredit').value = '0.00';
    }
    document.getElementById('customerModal').classList.remove('hidden');
}

function closeCustomerModal() {
    document.getElementById('customerModal').classList.add('hidden');
}

function filterCustomers() {
    const q = document.getElementById('customerSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#customersTable tbody tr');
    rows.forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>

    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
