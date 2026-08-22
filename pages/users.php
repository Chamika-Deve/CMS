<?php
require_once '../includes/db.php';
require_once '../includes/header.php';

$msg = '';
$msg_type = 'success';

$assignable_roles = match ($role) {
    'SuperAdmin' => ['Admin', 'Manager', 'Cashier', 'Technician', 'Inventory', 'Accountant', 'SuperAdmin'],
    'Admin' => ['Admin', 'Manager', 'Cashier', 'Technician', 'Inventory', 'Accountant'],
    'Manager' => ['Cashier', 'Technician', 'Inventory', 'Accountant'],
    default => [],
};

$can_manage_role = static function (string $target_role) use ($assignable_roles): bool {
    return in_array($target_role, $assignable_roles, true);
};

// Handle User Management Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_user' && $pdo) {
        try {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role_input = $_POST['role'] ?? 'Cashier';
            $phone = trim($_POST['phone'] ?? '');
            $branch = max(1, (int)($_POST['branch_id'] ?? 1));
            $status = isset($_POST['status']) ? 1 : 0;

            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('A name and valid email address are required.');
            }
            if (strlen($password) < 8) {
                throw new InvalidArgumentException('Passwords must contain at least 8 characters.');
            }
            if (!$can_manage_role($role_input)) {
                throw new RuntimeException('You cannot assign that role.');
            }

            $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $emailCheck->execute([$email]);
            if ($emailCheck->fetchColumn()) {
                throw new InvalidArgumentException('That email address is already used by another account.');
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, phone, branch_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hash, $role_input, $phone, $branch, $status]);
            $msg = "User \"$name\" ($role_input) created successfully!";
        } catch (Exception $e) {
            $msg = "Error creating user: " . safe_error_message($e);
            $msg_type = 'error';
        }
    }

    if ($action === 'edit_user' && $pdo) {
        try {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role_input = $_POST['role'] ?? 'Cashier';
            $phone = trim($_POST['phone'] ?? '');
            $branch = max(1, (int)($_POST['branch_id'] ?? 1));
            $status = isset($_POST['status']) ? 1 : 0;

            $target_statement = $pdo->prepare('SELECT role FROM users WHERE id = ?');
            $target_statement->execute([$id]);
            $existing_role = $target_statement->fetchColumn();
            if (!$existing_role || !$can_manage_role($existing_role) || !$can_manage_role($role_input)) {
                throw new RuntimeException('You cannot modify this user or assign that role.');
            }
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('A name and valid email address are required.');
            }

            $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
            $emailCheck->execute([$email, $id]);
            if ($emailCheck->fetchColumn()) {
                throw new InvalidArgumentException('That email address is already used by another account.');
            }

            if (!empty($_POST['password'])) {
                if (strlen($_POST['password']) < 8) {
                    throw new InvalidArgumentException('Passwords must contain at least 8 characters.');
                }
                $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, password=?, role=?, phone=?, branch_id=?, status=? WHERE id=?");
                $stmt->execute([$name, $email, $hash, $role_input, $phone, $branch, $status, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role=?, phone=?, branch_id=?, status=? WHERE id=?");
                $stmt->execute([$name, $email, $role_input, $phone, $branch, $status, $id]);
            }
            $msg = "User updated successfully!";
        } catch (Exception $e) {
            $msg = "Error updating user: " . safe_error_message($e);
            $msg_type = 'error';
        }
    }

    if ($action === 'delete_user' && $pdo) {
        try {
            $id = (int)($_POST['id'] ?? 0);
            // Check target user role
            $chk = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $chk->execute([$id]);
            $target_role = $chk->fetchColumn();

            if ($id < 1 || $id === (int)($user['id'] ?? 0)) {
                throw new RuntimeException('You cannot delete your own account.');
            }
            if (!$target_role || !$can_manage_role($target_role)) {
                throw new RuntimeException('You cannot delete this user.');
            }

            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $msg = 'User removed successfully.';
        } catch (Exception $e) {
            $msg = "Error removing user: " . safe_error_message($e);
            $msg_type = 'error';
        }
    }
}

// Fetch Users
$users_list = [];
if ($pdo) {
    try {
        if ($role === 'SuperAdmin') {
            $users_list = $pdo->query("SELECT * FROM users ORDER BY id ASC")->fetchAll();
        } else {
            // SuperAdmin is completely hidden from Shop Owner and store staff
            $users_list = $pdo->query("SELECT * FROM users WHERE role != 'SuperAdmin' ORDER BY id ASC")->fetchAll();
        }
    } catch (Exception $e) {}
}

// Active Tab
$tab = $_GET['tab'] ?? 'users';
?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <!-- Top Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-user-gear text-emerald-600"></i>
                <span>Users, Roles & Access Control (RBAC)</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Manage 6 distinct roles: Admin, Manager, Cashier, Technician, Inventory, and Accountant.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <a href="users.php?tab=matrix" class="px-4 py-2.5 rounded-2xl border <?php echo $tab === 'matrix' ? 'bg-emerald-50 text-emerald-700 border-emerald-300' : 'border-slate-200 text-slate-700 hover:bg-slate-50'; ?> text-xs sm:text-sm font-bold transition-all shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-table-cells text-emerald-600"></i>
                <span>Permission Matrix</span>
            </a>
            <button onclick="openUserModal('add')" class="px-5 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs sm:text-sm font-bold transition-all shadow-sm shadow-emerald-500/25 flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-xs"></i>
                <span>Add New User</span>
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

    <?php if ($tab === 'matrix'): ?>
    <!-- Permission Matrix Table Card -->
    <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7 overflow-hidden">
        <div class="flex items-center justify-between mb-6 pb-3 border-b border-slate-100">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Role &times; Module Access Matrix</h2>
                <p class="text-xs text-slate-400 font-medium mt-0.5">Enforced server-side across all controllers and API routes</p>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <span class="inline-flex items-center gap-1 font-bold text-emerald-600"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> F = Full</span>
                <span class="inline-flex items-center gap-1 font-bold text-blue-600"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> E = Edit/Create</span>
                <span class="inline-flex items-center gap-1 font-bold text-slate-600"><span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span> V = View Only</span>
                <span class="inline-flex items-center gap-1 font-bold text-amber-600"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> A = Approval Required</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-700 font-bold uppercase tracking-wider text-[11px]">
                        <th class="py-3 px-4">Module / Feature Action</th>
                        <?php if ($role === 'SuperAdmin'): ?>
                        <th class="py-3 px-3 text-center text-purple-700 font-extrabold bg-purple-50/70">SuperAdmin</th>
                        <?php endif; ?>
                        <th class="py-3 px-3 text-center">Owner / Admin</th>
                        <th class="py-3 px-3 text-center">Manager</th>
                        <th class="py-3 px-3 text-center">Cashier</th>
                        <th class="py-3 px-3 text-center">Technician</th>
                        <th class="py-3 px-3 text-center">Inventory</th>
                        <th class="py-3 px-3 text-center">Accountant</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                    <?php if ($role === 'SuperAdmin'): ?>
                    <tr>
                        <td class="py-3 px-4 font-bold">System Infrastructure & APIs</td>
                        <td class="py-3 px-3 text-center font-bold text-purple-700 bg-purple-50/40">F (Exclusive)</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                    </tr>
                    <tr>
                        <td class="py-3 px-4 font-bold">Database Backups & Schema Migrations</td>
                        <td class="py-3 px-3 text-center font-bold text-purple-700 bg-purple-50/40">F (Exclusive)</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                    </tr>
                    <tr>
                        <td class="py-3 px-4 font-bold">User Impersonation & Security Override</td>
                        <td class="py-3 px-3 text-center font-bold text-purple-700 bg-purple-50/40">F (Exclusive)</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="py-3 px-4 font-bold">Dashboard / Business KPIs</td>
                        <?php if ($role === 'SuperAdmin'): ?>
                        <td class="py-3 px-3 text-center font-bold text-purple-700 bg-purple-50/40">F</td>
                        <?php endif; ?>
                        <td class="py-3 px-3 text-center font-bold text-emerald-600">F</td>
                        <td class="py-3 px-3 text-center font-bold text-emerald-600">F</td>
                        <td class="py-3 px-3 text-center font-semibold text-slate-500">V (own shift)</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center font-semibold text-slate-500">V (stock)</td>
                        <td class="py-3 px-3 text-center font-semibold text-slate-500">V (financial)</td>
                    </tr>
                    <tr>
                        <td class="py-3 px-4 font-bold">POS / Sales & Barcode Scanning</td>
                        <td class="py-3 px-3 text-center font-bold text-purple-700 bg-purple-50/40">F</td>
                        <td class="py-3 px-3 text-center font-bold text-emerald-600">F</td>
                        <td class="py-3 px-3 text-center font-bold text-emerald-600">F</td>
                        <td class="py-3 px-3 text-center font-bold text-blue-600">E</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center font-semibold text-slate-500">V</td>
                    </tr>
                    <tr>
                        <td class="py-3 px-4">Products / Inventory & Serials</td>
                        <td class="py-3 px-3 text-center font-bold text-purple-700 bg-purple-50/40">F</td>
                        <td class="py-3 px-3 text-center font-bold text-emerald-600">F</td>
                        <td class="py-3 px-3 text-center font-bold text-emerald-600">F</td>
                        <td class="py-3 px-3 text-center font-semibold text-slate-500">V</td>
                        <td class="py-3 px-3 text-center font-semibold text-slate-500">V</td>
                        <td class="py-3 px-3 text-center font-bold text-emerald-600">F</td>
                        <td class="py-3 px-3 text-center font-semibold text-slate-500">V</td>
                    </tr>
                    <tr>
                        <td class="py-3 px-4">Repair & Service Jobs Workbench</td>
                        <td class="py-3 px-3 text-center font-bold text-purple-700 bg-purple-50/40">F</td>
                        <td class="py-3 px-3 text-center font-bold text-emerald-600">F</td>
                        <td class="py-3 px-3 text-center font-bold text-emerald-600">F</td>
                        <td class="py-3 px-3 text-center font-semibold text-slate-500">V</td>
                        <td class="py-3 px-3 text-center font-bold text-blue-600">E</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center font-semibold text-slate-500">V</td>
                    </tr>
                    <tr>
                        <td class="py-3 px-4">Suppliers & Purchasing (POs/GRN)</td>
                        <td class="py-3 px-3 text-center font-bold text-purple-700 bg-purple-50/40">F</td>
                        <td class="py-3 px-3 text-center font-bold text-emerald-600">F</td>
                        <td class="py-3 px-3 text-center font-bold text-blue-600">E</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center font-bold text-emerald-600">F</td>
                        <td class="py-3 px-3 text-center font-semibold text-slate-500">V</td>
                    </tr>
                    <tr>
                        <td class="py-3 px-4">Accounting, Expenses & Cash Drawer</td>
                        <td class="py-3 px-3 text-center font-bold text-purple-700 bg-purple-50/40">F</td>
                        <td class="py-3 px-3 text-center font-bold text-emerald-600">F</td>
                        <td class="py-3 px-3 text-center font-semibold text-slate-500">V</td>
                        <td class="py-3 px-3 text-center font-bold text-blue-600">E</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center text-slate-300">-</td>
                        <td class="py-3 px-3 text-center font-bold text-emerald-600">F</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Users Table Card -->
    <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7 overflow-hidden">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <h2 class="text-lg font-bold text-slate-900">System Users (<?php echo count($users_list); ?>)</h2>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input 
                    type="text" 
                    id="userSearch" 
                    onkeyup="filterUsers()" 
                    placeholder="Search name, email, role..." 
                    class="pl-9 pr-4 py-2 bg-slate-50 border border-slate-200/80 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 w-64"
                >
            </div>
        </div>

        <div class="overflow-x-auto -mx-6 sm:-mx-7 px-6 sm:px-7">
            <table class="w-full text-left border-collapse" id="usersTable">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 pr-4 pl-2">User Details</th>
                        <th class="py-3.5 px-4">Assigned Role</th>
                        <th class="py-3.5 px-4">Contact Phone</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 pl-4 pr-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 text-xs sm:text-sm text-slate-700">
                    <?php foreach ($users_list as $u): 
                        $u_role = $u['role'] ?? 'Cashier';
                        $u_name = $u['name'] ?? 'Staff User';
                        $u_email = $u['email'] ?? '';
                        $u_status = $u['status'] ?? 1;
                        $role_colors = [
                            'SuperAdmin' => 'bg-purple-100 text-purple-800 border-purple-300 font-extrabold',
                            'Admin' => 'bg-red-50 text-red-700 border-red-200',
                            'Manager' => 'bg-purple-50 text-purple-700 border-purple-200',
                            'Cashier' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            'Technician' => 'bg-blue-50 text-blue-700 border-blue-200',
                            'Inventory' => 'bg-amber-50 text-amber-700 border-amber-200',
                            'Accountant' => 'bg-cyan-50 text-cyan-700 border-cyan-200'
                        ][$u_role] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                    ?>
                    <tr class="hover:bg-slate-50/60 transition-colors group">
                        
                        <!-- Name & Email -->
                        <td class="py-4 pr-4 pl-2 font-bold text-slate-900">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-2xl <?php echo $u_role === 'SuperAdmin' ? 'bg-purple-700' : 'bg-emerald-600'; ?> text-white font-extrabold flex items-center justify-center text-xs shrink-0 shadow-sm">
                                    <?php echo strtoupper(substr($u['name'], 0, 2)); ?>
                                </div>
                                <div>
                                    <span class="block font-bold text-slate-900"><?php echo htmlspecialchars($u['name']); ?></span>
                                    <span class="block text-[11px] text-slate-400 font-normal"><?php echo htmlspecialchars($u['email']); ?></span>
                                </div>
                            </div>
                        </td>

                        <!-- Role Badge -->
                        <td class="py-4 px-4">
                            <span class="inline-block border text-xs font-bold px-3 py-1 rounded-xl <?php echo $role_colors; ?>">
                                <?php echo htmlspecialchars($u['role']); ?>
                            </span>
                        </td>

                        <!-- Phone -->
                        <td class="py-4 px-4 font-medium text-slate-600">
                            <?php echo htmlspecialchars($u['phone'] ?? '-'); ?>
                        </td>

                        <!-- Status -->
                        <td class="py-4 px-4 text-center">
                            <?php if ($u['status'] == 1): ?>
                                <span class="inline-block text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">Active</span>
                            <?php else: ?>
                                <span class="inline-block text-xs font-bold text-slate-400 bg-slate-100 px-2.5 py-0.5 rounded-full">Disabled</span>
                            <?php endif; ?>
                        </td>

                        <!-- Actions -->
                        <td class="py-4 pl-4 pr-2 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <?php if ($u['role'] !== 'SuperAdmin' || $role === 'SuperAdmin'): ?>
                                <button onclick="openUserModal('edit', <?php echo htmlspecialchars(json_encode($u)); ?>)" class="w-8 h-8 rounded-xl border border-slate-200 text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 flex items-center justify-center transition-colors text-xs" title="Edit User">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <?php endif; ?>

                                <?php if ((int)$u['id'] !== (int)($user['id'] ?? 0) && $can_manage_role($u['role'])): ?>
                                <form method="POST" action="users.php" onsubmit="return confirm('Delete user <?php echo addslashes($u['name']); ?>?');" class="inline">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="w-8 h-8 rounded-xl border border-slate-200 text-slate-500 hover:text-red-600 hover:bg-red-50 flex items-center justify-center transition-colors text-xs" title="Delete User">
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

<!-- User Modal (Add / Edit) -->
<div id="userModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-lg p-7 relative max-h-[90vh] overflow-y-auto">
        <button onclick="closeUserModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center gap-3 mb-6 pb-3 border-b border-slate-100">
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-slate-900" id="userModalTitle">Add Staff User</h3>
                <p class="text-xs text-slate-400">Configure role permissions and login credentials</p>
            </div>
        </div>

        <form method="POST" action="users.php" class="space-y-4">
            <input type="hidden" name="action" id="userModalAction" value="add_user">
            <input type="hidden" name="id" id="userModalId" value="">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Full Name *</label>
                <input type="text" name="name" id="userName" required placeholder="e.g. Alex Henderson" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Email Address (Username) *</label>
                <input type="email" name="email" id="userEmail" required placeholder="alex@techshop.com" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Role *</label>
                    <select name="role" id="userRole" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        <?php
                        $role_labels = [
                            'SuperAdmin' => 'SuperAdmin (Software Engineer)',
                            'Admin' => 'Owner / Admin',
                            'Manager' => 'Manager',
                            'Cashier' => 'Cashier / Front Desk',
                            'Technician' => 'Repair Technician',
                            'Inventory' => 'Inventory / Purchasing',
                            'Accountant' => 'Accountant / Finance',
                        ];
                        foreach ($assignable_roles as $assignable_role):
                        ?>
                        <option value="<?php echo htmlspecialchars($assignable_role); ?>"><?php echo htmlspecialchars($role_labels[$assignable_role] ?? $assignable_role); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Phone Number</label>
                    <input type="text" name="phone" id="userPhone" placeholder="+94 77 123 4567" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5" id="userPasswordLabel">Password</label>
                <input type="password" name="password" id="userPassword" placeholder="••••••••" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                <span class="text-[11px] text-slate-400 mt-1 block" id="userPasswordHint">Leave blank on edit to keep existing password.</span>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" name="status" id="userStatus" value="1" checked class="w-4 h-4 text-emerald-600 rounded">
                <label for="userStatus" class="text-xs font-bold text-slate-700">Account Active (Can log in)</label>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="closeUserModal()" class="px-5 py-2.5 rounded-2xl border border-slate-200 text-slate-700 font-bold text-xs sm:text-sm hover:bg-slate-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs sm:text-sm transition-all shadow-sm shadow-emerald-500/25">
                    Save User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openUserModal(mode, data = null) {
    if (mode === 'edit' && data) {
        document.getElementById('userModalTitle').textContent = 'Edit Staff User';
        document.getElementById('userModalAction').value = 'edit_user';
        document.getElementById('userModalId').value = data.id;
        document.getElementById('userName').value = data.name;
        document.getElementById('userEmail').value = data.email;
        document.getElementById('userRole').value = data.role;
        document.getElementById('userPhone').value = data.phone || '';
        document.getElementById('userPassword').value = '';
        document.getElementById('userPasswordLabel').textContent = 'New Password (Optional)';
        document.getElementById('userStatus').checked = (data.status == 1);
    } else {
        document.getElementById('userModalTitle').textContent = 'Add Staff User';
        document.getElementById('userModalAction').value = 'add_user';
        document.getElementById('userModalId').value = '';
        document.getElementById('userName').value = '';
        document.getElementById('userEmail').value = '';
        document.getElementById('userRole').value = 'Cashier';
        document.getElementById('userPhone').value = '';
        document.getElementById('userPassword').value = '';
        document.getElementById('userPasswordLabel').textContent = 'Password *';
        document.getElementById('userStatus').checked = true;
    }
    document.getElementById('userModal').classList.remove('hidden');
}

function closeUserModal() {
    document.getElementById('userModal').classList.add('hidden');
}

function filterUsers() {
    const q = document.getElementById('userSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#usersTable tbody tr');
    rows.forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
