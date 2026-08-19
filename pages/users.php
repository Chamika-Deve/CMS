<?php
require_once '../includes/db.php';
require_once '../includes/header.php';

// ── role_permissions table + seed ────────────────────────────────────────────
$matrix_modules = [
    'Dashboard / Business KPIs'          => ['SuperAdmin'=>'F','Admin'=>'F','Manager'=>'F','Cashier'=>'V','Technician'=>'-','Inventory'=>'V','Accountant'=>'V'],
    'POS / Sales & Barcode Scanning'     => ['SuperAdmin'=>'F','Admin'=>'F','Manager'=>'F','Cashier'=>'E','Technician'=>'-','Inventory'=>'-','Accountant'=>'V'],
    'Products / Inventory & Serials'     => ['SuperAdmin'=>'F','Admin'=>'F','Manager'=>'F','Cashier'=>'V','Technician'=>'V','Inventory'=>'F','Accountant'=>'V'],
    'Repair & Service Jobs Workbench'    => ['SuperAdmin'=>'F','Admin'=>'F','Manager'=>'F','Cashier'=>'V','Technician'=>'E','Inventory'=>'-','Accountant'=>'V'],
    'Suppliers & Purchasing (POs/GRN)'   => ['SuperAdmin'=>'F','Admin'=>'F','Manager'=>'E','Cashier'=>'-','Technician'=>'-','Inventory'=>'F','Accountant'=>'V'],
    'Accounting, Expenses & Cash Drawer' => ['SuperAdmin'=>'F','Admin'=>'F','Manager'=>'V','Cashier'=>'E','Technician'=>'-','Inventory'=>'-','Accountant'=>'F'],
];
$matrix_roles = ['SuperAdmin','Admin','Manager','Cashier','Technician','Inventory','Accountant'];

if ($pdo) {
    // Create table
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `role_permissions` (
                `id`          int unsigned NOT NULL AUTO_INCREMENT,
                `module`      varchar(120) NOT NULL,
                `role`        varchar(40)  NOT NULL,
                `access`      varchar(10)  NOT NULL DEFAULT '-',
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_module_role` (`module`,`role`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Exception $e) {}

    // Seed defaults if empty
    try {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM role_permissions")->fetchColumn();
        if ($cnt === 0) {
            $ins = $pdo->prepare("INSERT IGNORE INTO role_permissions (module, role, access) VALUES (?,?,?)");
            foreach ($matrix_modules as $mod => $roles) {
                foreach ($roles as $r => $acc) {
                    $ins->execute([$mod, $r, $acc]);
                }
            }
        }
    } catch (Exception $e) {}

    // Load current values
    try {
        $perm_rows = $pdo->query("SELECT module, role, access FROM role_permissions")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($perm_rows as $pr) {
            $matrix_modules[$pr['module']][$pr['role']] = $pr['access'];
        }
    } catch (Exception $e) {}
}
// ─────────────────────────────────────────────────────────────────────────────

// ── users table migration ─────────────────────────────────────────────────────
if ($pdo) {
    $addUserCol = function($col, $def) use ($pdo) {
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM `users` LIKE '$col'");
            if (!$chk->fetch()) {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `$col` $def");
            }
        } catch (Exception $e) {}
    };
    $addUserCol('phone',     "varchar(30) DEFAULT NULL");
    $addUserCol('branch_id', "int unsigned NOT NULL DEFAULT 1");
}
// ─────────────────────────────────────────────────────────────────────────────

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

    // Save Permission Matrix (SuperAdmin only)
    if ($action === 'save_matrix' && $role === 'SuperAdmin' && $pdo) {
        try {
            $posted = $_POST['matrix'] ?? [];
            $allowed_access = ['F', 'E', 'V', 'A', '-'];
            $ups = $pdo->prepare("INSERT INTO role_permissions (module, role, access) VALUES (?,?,?) ON DUPLICATE KEY UPDATE access = VALUES(access)");
            foreach ($posted as $mod => $roles_arr) {
                foreach ($roles_arr as $r => $acc) {
                    $acc = in_array($acc, $allowed_access, true) ? $acc : '-';
                    $ups->execute([urldecode($mod), $r, $acc]);
                }
            }
            $msg = 'Permission matrix saved successfully!';
        } catch (Exception $e) {
            $msg = 'Error saving matrix: ' . safe_error_message($e);
            $msg_type = 'error';
        }
        // Reload updated values
        try {
            $perm_rows = $pdo->query("SELECT module, role, access FROM role_permissions")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($perm_rows as $pr) {
                $matrix_modules[$pr['module']][$pr['role']] = $pr['access'];
            }
        } catch (Exception $e) {}
    }

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
            $id = (int)$_POST['id'];
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
            $id = (int)$_POST['id'];
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

// Active Tab — non-admin roles can only view the permission matrix
$tab = $_GET['tab'] ?? 'users';
$can_manage_users = in_array($role, ['Admin', 'Manager', 'SuperAdmin'], true);
if (!$can_manage_users) {
    $tab = 'matrix'; // force read-only matrix view for other roles
}
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
            <?php if ($can_manage_users): ?>
            <a href="users.php?tab=matrix" class="px-4 py-2.5 rounded-2xl border <?php echo $tab === 'matrix' ? 'bg-emerald-50 text-emerald-700 border-emerald-300' : 'border-slate-200 text-slate-700 hover:bg-slate-50'; ?> text-xs sm:text-sm font-bold transition-all shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-table-cells text-emerald-600"></i>
                <span>Permission Matrix</span>
            </a>
            <button onclick="openUserModal('add')" class="px-5 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs sm:text-sm font-bold transition-all shadow-sm shadow-emerald-500/25 flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-xs"></i>
                <span>Add New User</span>
            </button>
            <?php else: ?>
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-100 text-slate-500 text-xs font-semibold">
                <i class="fa-solid fa-eye"></i> View Only
            </span>
            <?php endif; ?>
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
    <?php
    // Helper: render access badge (readonly)
    function access_badge(string $acc): string {
        $map = [
            'F' => ['label'=>'F','cls'=>'bg-emerald-100 text-emerald-700 border-emerald-300 font-extrabold'],
            'E' => ['label'=>'E','cls'=>'bg-blue-100 text-blue-700 border-blue-300 font-bold'],
            'V' => ['label'=>'V','cls'=>'bg-slate-100 text-slate-600 border-slate-300 font-semibold'],
            'A' => ['label'=>'A','cls'=>'bg-amber-100 text-amber-700 border-amber-300 font-bold'],
            '-' => ['label'=>'—','cls'=>'bg-transparent text-slate-300 border-transparent font-normal'],
        ];
        $d = $map[$acc] ?? $map['-'];
        return '<span class="inline-block border text-[11px] px-2 py-0.5 rounded-lg '.$d['cls'].'">'.$d['label'].'</span>';
    }
    $superadmin_only_mods = [
        'System Infrastructure & APIs',
        'Database Backups & Schema Migrations',
        'User Impersonation & Security Override',
    ];
    $matrix_roles = ($role === 'SuperAdmin')
        ? ['SuperAdmin', 'Admin', 'Manager', 'Cashier', 'Technician', 'Inventory', 'Accountant']
        : ['Admin', 'Manager', 'Cashier', 'Technician', 'Inventory', 'Accountant'];
    ?>
    <form method="POST" action="users.php?tab=matrix" id="matrixForm">
    <input type="hidden" name="action" value="save_matrix">
    <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7 overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-3 border-b border-slate-100">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Role &times; Module Access Matrix</h2>
                <p class="text-xs text-slate-400 font-medium mt-0.5">Enforced server-side across all controllers and API routes</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="inline-flex items-center gap-1.5 font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-xl"><span class="w-2 h-2 rounded-full bg-emerald-500"></span>F = Full</span>
                <span class="inline-flex items-center gap-1.5 font-bold text-blue-700 bg-blue-50 border border-blue-200 px-2.5 py-1 rounded-xl"><span class="w-2 h-2 rounded-full bg-blue-500"></span>E = Edit</span>
                <span class="inline-flex items-center gap-1.5 font-semibold text-slate-600 bg-slate-50 border border-slate-200 px-2.5 py-1 rounded-xl"><span class="w-2 h-2 rounded-full bg-slate-400"></span>V = View</span>
                <span class="inline-flex items-center gap-1.5 font-bold text-amber-700 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded-xl"><span class="w-2 h-2 rounded-full bg-amber-500"></span>A = Approval</span>
                <span class="inline-flex items-center gap-1.5 text-slate-400 bg-slate-50 border border-slate-100 px-2.5 py-1 rounded-xl">— = None</span>
                <?php if ($role === 'SuperAdmin'): ?>
                <button type="submit" form="matrixForm" class="ml-1 px-4 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold transition-all shadow-sm flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold uppercase tracking-wider">
                        <th class="py-3 px-4 text-slate-600 min-w-[180px]">Module / Feature</th>
                        <?php foreach ($matrix_roles as $r_col): ?>
                        <th class="py-3 px-3 text-center <?php echo $r_col === 'SuperAdmin' ? 'text-purple-700 bg-purple-50/60' : 'text-slate-600'; ?> min-w-[90px]">
                            <?php echo $r_col === 'Admin' ? 'Owner / Admin' : htmlspecialchars($r_col); ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php
                    $module_flag_map = [
                        'Repair & Service Jobs Workbench'    => 'feature_repairs',
                        'Accounting, Expenses & Cash Drawer' => 'feature_accounting',
                    ];
                    foreach ($matrix_modules as $mod_name => $mod_perms):
                        $flag = $module_flag_map[$mod_name] ?? null;
                        if ($flag && !is_flag_enabled($flag, 1)) {
                            continue; // Skip feature modules turned OFF by SuperAdmin
                        }
                        $mod_key = urlencode($mod_name);
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="py-3 px-4 font-semibold text-slate-800 text-[12px]">
                            <?php echo htmlspecialchars($mod_name); ?>
                        </td>
                        <?php foreach ($matrix_roles as $col_role):
                            $acc = $mod_perms[$col_role] ?? '-';
                            $is_purple_col = ($col_role === 'SuperAdmin');
                            $is_locked = $is_purple_col;
                        ?>
                        <td class="py-3 px-3 text-center <?php echo $is_purple_col ? 'bg-purple-50/30' : ''; ?>">
                            <?php if ($role === 'SuperAdmin' && !$is_locked): ?>
                                <select name="matrix[<?php echo htmlspecialchars($mod_key); ?>][<?php echo $col_role; ?>]"
                                        onchange="markChanged(this)"
                                        class="matrix-select w-full text-center text-[11px] font-bold border rounded-lg px-1 py-1 focus:outline-none transition-all cursor-pointer"
                                        data-val="<?php echo htmlspecialchars($acc); ?>">
                                    <option value="-" <?php echo $acc==='-'?'selected':''; ?>>— None</option>
                                    <option value="V" <?php echo $acc==='V'?'selected':''; ?>>V  View</option>
                                    <option value="E" <?php echo $acc==='E'?'selected':''; ?>>E  Edit</option>
                                    <option value="A" <?php echo $acc==='A'?'selected':''; ?>>A  Approval</option>
                                    <option value="F" <?php echo $acc==='F'?'selected':''; ?>>F  Full</option>
                                </select>
                            <?php else: ?>
                                <?php
                                $badge_map = [
                                    'F' => '<span class="inline-block border text-[11px] px-2 py-0.5 rounded-lg bg-emerald-100 text-emerald-700 border-emerald-300 font-extrabold">F</span>',
                                    'E' => '<span class="inline-block border text-[11px] px-2 py-0.5 rounded-lg bg-blue-100 text-blue-700 border-blue-300 font-bold">E</span>',
                                    'V' => '<span class="inline-block border text-[11px] px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600 border-slate-300 font-semibold">V</span>',
                                    'A' => '<span class="inline-block border text-[11px] px-2 py-0.5 rounded-lg bg-amber-100 text-amber-700 border-amber-300 font-bold">A</span>',
                                    '-' => '<span class="inline-block text-[11px] px-2 py-0.5 text-slate-300">—</span>',
                                ];
                                echo $badge_map[$acc] ?? $badge_map['-'];
                                ?>
                                <?php if ($role === 'SuperAdmin'): ?>
                                    <input type="hidden" name="matrix[<?php echo htmlspecialchars($mod_key); ?>][<?php echo $col_role; ?>]" value="<?php echo htmlspecialchars($acc); ?>">
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($role === 'SuperAdmin'): ?>
        <div class="mt-5 pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <p class="text-xs text-slate-400"><i class="fa-solid fa-circle-info mr-1 text-purple-400"></i>SuperAdmin column &amp; system rows are permanently locked.</p>
            <button type="submit" form="matrixForm" class="px-6 py-2.5 rounded-2xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold transition-all shadow-sm shadow-purple-500/25 flex items-center gap-2 shrink-0">
                <i class="fa-solid fa-floppy-disk"></i> Save Permission Matrix
            </button>
        </div>
        <?php endif; ?>
    </div>
    </form>
    <?php endif; ?>

    <?php if ($can_manage_users): ?>
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
    <?php endif; // end can_manage_users ?>

</div>

<?php if ($can_manage_users): ?>
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

<style>
.matrix-select {
    background: #f8fafc;
    border-color: #e2e8f0;
    color: #1e293b;
    min-width: 72px;
}
.matrix-select[data-val="F"] { background:#f0fdf4; border-color:#86efac; color:#15803d; }
.matrix-select[data-val="E"] { background:#eff6ff; border-color:#93c5fd; color:#1d4ed8; }
.matrix-select[data-val="V"] { background:#f8fafc; border-color:#cbd5e1; color:#475569; }
.matrix-select[data-val="A"] { background:#fffbeb; border-color:#fcd34d; color:#b45309; }
.matrix-select[data-val="-"] { background:#f8fafc; border-color:#e2e8f0; color:#94a3b8; }
.matrix-select.changed      { outline: 2px solid #a855f7; outline-offset: 1px; }
</style>

<script>
function markChanged(sel) {
    sel.dataset.val = sel.value;
    // Re-apply colour class
    sel.className = sel.className.replace(/\bchanged\b/,'').trim();
    sel.classList.add('matrix-select', 'changed');
    // Restyle
    const map = { 'F':'#f0fdf4|#86efac|#15803d', 'E':'#eff6ff|#93c5fd|#1d4ed8',
                  'V':'#f8fafc|#cbd5e1|#475569', 'A':'#fffbeb|#fcd34d|#b45309',
                  '-':'#f8fafc|#e2e8f0|#94a3b8' };
    const parts = (map[sel.value] || map['-']).split('|');
    sel.style.background   = parts[0];
    sel.style.borderColor  = parts[1];
    sel.style.color        = parts[2];
}
// Apply initial colours on load
document.querySelectorAll('.matrix-select').forEach(sel => {
    markChanged(sel);
    sel.classList.remove('changed');
});
</script>

<?php endif; // end can_manage_users modal ?>

<?php require_once '../includes/footer.php'; ?>
