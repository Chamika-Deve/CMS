<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
enforce_page_access('products.php');

$user = $_SESSION['user'];
$role = $user['role'] ?? 'Cashier';
$user_id = $user['id'] ?? 1;
$can_manage_inventory = can_write_page('products.php');

// Product browsing is available to several roles, but stock/catalog changes
// are restricted to staff responsible for inventory.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $requested_action = $_POST['action'] ?? '';
    $read_only_actions = ['lookup_product', 'get_categories', 'get_product_serials'];
    if (!in_array($requested_action, $read_only_actions, true)
        && !$can_manage_inventory) {
        abort_request(403, 'You do not have permission to change inventory.', isset($_POST['ajax']));
    }
}

$msg = '';
$msg_type = 'success';

// Helper to log stock movements
$logStockMovement = function($productId, $type, $qty, $reason, $ref = null, $serial = null) use ($pdo, $user_id) {
    if (!$pdo) return;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO stock_movements (product_id, serial_number, movement_type, quantity, reason, reference_id, user_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$productId, $serial, $type, $qty, $reason, $ref, $user_id]);
    } catch (Exception $e) {}
};

// AJAX Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // 1. Lookup Product by Barcode or Code
    if ($action === 'lookup_product' && $pdo) {
        $code = trim($_POST['code'] ?? '');
        $stmt = $pdo->prepare("SELECT p.*, c.name as category_name, b.name as brand_name FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN brands b ON p.brand_id = b.id WHERE p.product_code = ? OR p.ean = ? OR p.upc = ?");
        $stmt->execute([$code, $code, $code]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            echo json_encode(['success' => true, 'product' => $product]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Product not found. Please verify barcode.']);
        }
        exit;
    }

    // 2. Add Serial Number to Stock
    if ($action === 'add_serial' && $pdo) {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $serial = trim($_POST['serial_number'] ?? '');
        if ($product_id < 1 || $serial === '' || strlen($serial) > 100) {
            echo json_encode(['success' => false, 'message' => 'A valid product and serial number are required.']);
            exit;
        }
        try {
            $chk = $pdo->prepare("SELECT id, status FROM product_serials WHERE serial_number = ?");
            $chk->execute([$serial]);
            $existing = $chk->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                echo json_encode(['success' => false, 'message' => "Serial '$serial' already exists (Status: {$existing['status']})!"]);
            } else {
                $ins = $pdo->prepare("INSERT INTO product_serials (product_id, serial_number, status) VALUES (?, ?, 'in_stock')");
                $ins->execute([$product_id, $serial]);
                $logStockMovement($product_id, 'Purchase In', 1, 'Rapid Barcode Inbound Scanner', 'RAPID-SCAN', $serial);
                echo json_encode(['success' => true, 'message' => "Added '$serial'"]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => safe_error_message($e)]);
        }
        exit;
    }

    // 3. Get Categories List
    if ($action === 'get_categories' && $pdo) {
        try {
            $stmt = $pdo->query("
                SELECT c.id, c.name, COUNT(p.id) as product_count 
                FROM categories c 
                LEFT JOIN products p ON c.id = p.category_id 
                GROUP BY c.id 
                ORDER BY c.name ASC
            ");
            $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'categories' => $cats]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => safe_error_message($e)]);
        }
        exit;
    }

    // 4. Add Category
    if ($action === 'add_category' && $pdo) {
        $cat_name = trim($_POST['category_name'] ?? '');
        if (empty($cat_name)) {
            echo json_encode(['success' => false, 'message' => 'Category name cannot be empty.']);
            exit;
        }
        try {
            $chk = $pdo->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?)");
            $chk->execute([$cat_name]);
            if ($chk->fetch()) {
                echo json_encode(['success' => false, 'message' => "Category '$cat_name' already exists."]);
                exit;
            }
            $ins = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $ins->execute([$cat_name]);
            $new_id = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'message' => "Category '$cat_name' added successfully!", 'category' => ['id' => (int)$new_id, 'name' => $cat_name]]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => safe_error_message($e)]);
        }
        exit;
    }

    // 5. Delete Category
    if ($action === 'delete_category' && $pdo) {
        $cat_id = (int)$_POST['category_id'];
        try {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
            $chk->execute([$cat_id]);
            $count = $chk->fetchColumn();
            if ($count > 0) {
                echo json_encode(['success' => false, 'message' => "Cannot delete category. $count product(s) are assigned to it."]);
                exit;
            }
            $del = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $del->execute([$cat_id]);
            echo json_encode(['success' => true, 'message' => 'Category deleted successfully!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => safe_error_message($e)]);
        }
        exit;
    }

    // 6. Get Product Serials
    if ($action === 'get_product_serials' && $pdo) {
        $pid = (int)($_POST['product_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("
                SELECT ps.*, p.name as product_name, p.product_code 
                FROM product_serials ps 
                JOIN products p ON ps.product_id = p.id 
                WHERE ps.product_id = ? 
                ORDER BY ps.id DESC
            ");
            $stmt->execute([$pid]);
            $serials = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'serials' => $serials]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => safe_error_message($e)]);
        }
        exit;
    }

    // 7. Update Serial Status
    if ($action === 'update_serial_status' && $pdo) {
        $sid = (int)($_POST['serial_id'] ?? 0);
        $new_st = trim($_POST['status'] ?? 'in_stock');
        $notes = trim($_POST['notes'] ?? '');
        try {
            $stmt_old = $pdo->prepare("SELECT * FROM product_serials WHERE id = ?");
            $stmt_old->execute([$sid]);
            $old_row = $stmt_old->fetch(PDO::FETCH_ASSOC);

            if ($old_row) {
                $up = $pdo->prepare("UPDATE product_serials SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?");
                $up->execute([$new_st, $notes, $sid]);

                $mtype = ($new_st === 'defective') ? 'Adjustment Out' : (($new_st === 'in_stock') ? 'Adjustment In' : 'Status Change');
                $logStockMovement($old_row['product_id'], $mtype, 1, "Status updated from {$old_row['status']} to $new_st: $notes", 'SERIAL-MOD', $old_row['serial_number']);

                echo json_encode(['success' => true, 'message' => "Serial status changed to $new_st."]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Serial record not found.']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => safe_error_message($e)]);
        }
        exit;
    }
}

// POST Form Submissions (Create/Edit Product, Stock Adjustment, Stocktake Reconcile, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. Save Product (Create or Edit)
    if ($action === 'save_product' && $pdo) {
        try {
            $pid = (int)($_POST['product_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['product_code'] ?? '');
            $barcode = trim($_POST['ean'] ?? '');
            $cat_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $sub_cat = trim($_POST['sub_category'] ?? '');
            $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
            $model_no = trim($_POST['model_number'] ?? '');
            $uom = trim($_POST['unit_of_measure'] ?? 'pcs');
            $cost_price = (float)($_POST['cost_price'] ?? 0);
            $selling_price = (float)($_POST['selling_price'] ?? 0);
            $wholesale_price = (float)($_POST['wholesale_price'] ?? 0);
            $tax_rate = (float)($_POST['tax_rate'] ?? 0);
            $reorder_level = (int)($_POST['reorder_level'] ?? 5);
            $location = trim($_POST['location'] ?? 'Main Shelf');
            $status = $_POST['status'] ?? 'Active';
            $specs = trim($_POST['specifications'] ?? '');
            $desc = trim($_POST['description'] ?? '');

            if ($name === '' || !$cat_id) {
                throw new InvalidArgumentException('Product name and category are required.');
            }
            if ($cost_price < 0 || $selling_price < 0 || $wholesale_price < 0) {
                throw new InvalidArgumentException('Product prices cannot be negative.');
            }
            if ($tax_rate < 0 || $tax_rate > 100 || $reorder_level < 0) {
                throw new InvalidArgumentException('Tax must be 0–100 and reorder level cannot be negative.');
            }
            if (!in_array($status, ['Active', 'Discontinued'], true)) {
                throw new InvalidArgumentException('Invalid product status.');
            }

            if (empty($code)) {
                $code = 'SKU-' . strtoupper(substr($name, 0, 3)) . '-' . rand(1000, 9999);
            }

            if ($pid > 0) {
                // Update Product
                $stmt = $pdo->prepare("
                    UPDATE products SET 
                        name = ?, category_id = ?, sub_category = ?, brand_id = ?, model_number = ?, 
                        product_code = ?, ean = ?, unit_of_measure = ?, cost_price = ?, selling_price = ?, 
                        wholesale_price = ?, tax_rate = ?, reorder_level = ?, location = ?, status = ?, 
                        specifications = ?, description = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name, $cat_id, $sub_cat, $brand_id, $model_no, 
                    $code, $barcode, $uom, $cost_price, $selling_price, 
                    $wholesale_price, $tax_rate, $reorder_level, $location, $status, 
                    $specs, $desc, $pid
                ]);
                $msg = "Product '$name' ($code) updated successfully!";
            } else {
                // Insert New Product
                $stmt = $pdo->prepare("
                    INSERT INTO products (
                        name, category_id, sub_category, brand_id, model_number, 
                        product_code, ean, unit_of_measure, cost_price, selling_price, 
                        wholesale_price, tax_rate, reorder_level, location, status, 
                        specifications, description, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $name, $cat_id, $sub_cat, $brand_id, $model_no, 
                    $code, $barcode, $uom, $cost_price, $selling_price, 
                    $wholesale_price, $tax_rate, $reorder_level, $location, $status, 
                    $specs, $desc
                ]);
                $new_pid = $pdo->lastInsertId();

                // Initial opening stock if provided
                $initial_stock = (int)($_POST['initial_stock'] ?? 0);
                if ($initial_stock > 0) {
                    $ins_sn = $pdo->prepare("INSERT INTO product_serials (product_id, serial_number, status) VALUES (?, ?, 'in_stock')");
                    for ($i = 0; $i < $initial_stock; $i++) {
                        $auto_sn = 'INIT-' . date('ymd') . '-' . $new_pid . '-' . rand(1000, 9999);
                        $ins_sn->execute([$new_pid, $auto_sn]);
                    }
                    $logStockMovement($new_pid, 'Adjustment In', $initial_stock, 'Initial Opening Stock Setup', 'OPEN-STOCK');
                }

                $msg = "Product '$name' ($code) created successfully!";
            }
        } catch (Exception $e) {
            $msg = "Error saving product: " . safe_error_message($e);
            $msg_type = 'error';
        }
    }

    // 2. Stock Adjustment (Damage, Loss, Theft, Count Correction, Found Stock)
    if ($action === 'stock_adjustment' && $pdo) {
        try {
            $product_id = (int)$_POST['product_id'];
            $adj_type = $_POST['adjustment_type'] ?? 'add'; // 'add' or 'subtract'
            $qty = max(1, (int)($_POST['quantity'] ?? 1));
            $reason = trim($_POST['reason'] ?? 'Count Correction');
            $notes = trim($_POST['notes'] ?? '');
            $serial_num = trim($_POST['serial_number'] ?? '');

            if ($adj_type === 'add') {
                // Add Stock
                $stmt_sn = $pdo->prepare("INSERT INTO product_serials (product_id, serial_number, status, notes) VALUES (?, ?, 'in_stock', ?)");
                for ($i = 0; $i < $qty; $i++) {
                    $sn = (!empty($serial_num) && $qty === 1) ? $serial_num : ('ADJ-' . date('ymd') . '-' . rand(10000, 99999));
                    $stmt_sn->execute([$product_id, $sn, $notes]);
                }
                $logStockMovement($product_id, 'Adjustment In', $qty, "$reason: $notes", 'MANUAL-ADJ', $serial_num ?: null);
                $msg = "Stock Adjustment: +$qty unit(s) added successfully!";
            } else {
                // Subtract Stock (Write-off)
                if (!empty($serial_num)) {
                    $up = $pdo->prepare("UPDATE product_serials SET status = 'defective', notes = ? WHERE product_id = ? AND serial_number = ? LIMIT 1");
                    $up->execute(["$reason: $notes", $product_id, $serial_num]);
                } else {
                    $up = $pdo->prepare("UPDATE product_serials SET status = 'defective', notes = ? WHERE product_id = ? AND status = 'in_stock' LIMIT $qty");
                    $up->execute(["$reason: $notes", $product_id]);
                }
                $logStockMovement($product_id, 'Adjustment Out', $qty, "$reason: $notes", 'MANUAL-ADJ', $serial_num ?: null);
                $msg = "Stock Adjustment: -$qty unit(s) deducted / written off successfully!";
            }
        } catch (Exception $e) {
            $msg = "Error performing adjustment: " . safe_error_message($e);
            $msg_type = 'error';
        }
    }

    // 3. Physical Stock Count (Stock Take) Reconciliation
    if ($action === 'reconcile_stocktake' && $pdo) {
        try {
            $counts = $_POST['physical_count'] ?? [];
            $reconciled_count = 0;

            foreach ($counts as $pid => $phys_qty) {
                $pid = (int)$pid;
                $phys_qty = max(0, (int)$phys_qty);

                // Get current in_stock count
                $stmt_curr = $pdo->prepare("SELECT COUNT(*) FROM product_serials WHERE product_id = ? AND status = 'in_stock'");
                $stmt_curr->execute([$pid]);
                $sys_qty = (int)$stmt_curr->fetchColumn();

                $diff = $phys_qty - $sys_qty;
                if ($diff > 0) {
                    // Physical is more than system -> Add found units
                    $stmt_add = $pdo->prepare("INSERT INTO product_serials (product_id, serial_number, status) VALUES (?, ?, 'in_stock')");
                    for ($i = 0; $i < $diff; $i++) {
                        $sn = 'STAKE-FOUND-' . date('ymd') . '-' . rand(1000, 9999);
                        $stmt_add->execute([$pid, $sn]);
                    }
                    $logStockMovement($pid, 'Adjustment In', $diff, 'Physical Stock Count Reconciliation (Found excess)', 'STOCK-TAKE');
                    $reconciled_count++;
                } elseif ($diff < 0) {
                    // Physical is less than system -> Mark missing as defective/lost
                    $abs_diff = abs($diff);
                    $stmt_sub = $pdo->prepare("UPDATE product_serials SET status = 'defective', notes = 'Physical Stock Take Discrepancy (Missing)' WHERE product_id = ? AND status = 'in_stock' LIMIT $abs_diff");
                    $stmt_sub->execute([$pid]);
                    $logStockMovement($pid, 'Adjustment Out', $abs_diff, 'Physical Stock Count Reconciliation (Missing count)', 'STOCK-TAKE');
                    $reconciled_count++;
                }
            }
            $msg = "Stock Take reconciliation complete! Adjusted $reconciled_count product variance(s).";
        } catch (Exception $e) {
            $msg = "Error reconciling stock take: " . safe_error_message($e);
            $msg_type = 'error';
        }
    }

    // 4. Delete Product (or Discontinue)
    if ($action === 'delete_product' && $pdo) {
        $del_id = (int)$_POST['product_id'];
        try {
            // Check if product has sales or stock history
            $chk = $pdo->prepare("SELECT COUNT(*) FROM product_serials WHERE product_id = ?");
            $chk->execute([$del_id]);
            $count = $chk->fetchColumn();

            if ($count > 0) {
                // Soft delete / Discontinue
                $pdo->prepare("UPDATE products SET status = 'Discontinued' WHERE id = ?")->execute([$del_id]);
                $msg = "Product has serial history, so it was safely marked as 'Discontinued' without altering historical sales.";
            } else {
                $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$del_id]);
                $msg = "Product deleted successfully from catalog.";
            }
        } catch (Exception $e) {
            $msg = "Error: " . safe_error_message($e);
            $msg_type = 'error';
        }
    }
}

require_once '../includes/header.php';

// Active Tab
$tab = $_GET['tab'] ?? 'catalog';
if (!$can_manage_inventory && in_array($tab, ['stocktake', 'scan'], true)) {
    $tab = 'catalog';
}
$filter_cat = $_GET['category'] ?? '';
$filter_brand = $_GET['brand'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search_query = trim($_GET['search'] ?? '');

// Fetch Data for Pages
$products = [];
$categories_list = [];
$brands_list = [];
$stock_movements = [];
$serials_list = [];

$total_skus = 0;
$total_units_in_stock = 0;
$total_asset_cost = 0.0;
$total_retail_value = 0.0;
$low_stock_count = 0;

if ($pdo) {
    try {
        $categories_list = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $brands_list = $pdo->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Fetch products with live stock calculation
        $where_clauses = ["1=1"];
        $params = [];

        if (!empty($filter_cat)) {
            $where_clauses[] = "p.category_id = ?";
            $params[] = (int)$filter_cat;
        }
        if (!empty($filter_brand)) {
            $where_clauses[] = "p.brand_id = ?";
            $params[] = (int)$filter_brand;
        }
        if ($filter_status === 'Active' || $filter_status === 'Discontinued') {
            $where_clauses[] = "p.status = ?";
            $params[] = $filter_status;
        }
        if (!empty($search_query)) {
            $where_clauses[] = "(p.name LIKE ? OR p.product_code LIKE ? OR p.ean LIKE ? OR p.model_number LIKE ?)";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
        }

        $where_sql = implode(' AND ', $where_clauses);

        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name, b.name as brand_name,
                   (SELECT COUNT(*) FROM product_serials WHERE product_id = p.id AND status = 'in_stock') as in_stock_count,
                   (SELECT COUNT(*) FROM product_serials WHERE product_id = p.id AND status = 'reserved') as reserved_count,
                   (SELECT COUNT(*) FROM product_serials WHERE product_id = p.id AND status = 'sold') as sold_count,
                   (SELECT COUNT(*) FROM product_serials WHERE product_id = p.id AND status = 'defective') as defective_count
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE $where_sql
            ORDER BY p.id DESC
        ");
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_skus = count($products);

        // Compute Valuation and Low Stock Across All Active Products
        foreach ($products as $pr) {
            $in_stock = (int)$pr['in_stock_count'];
            $cost = (float)($pr['cost_price'] ?? 0);
            $sell = (float)($pr['selling_price'] ?? 0);
            $reorder = (int)($pr['reorder_level'] ?? 5);

            $total_units_in_stock += $in_stock;
            $total_asset_cost += ($in_stock * $cost);
            $total_retail_value += ($in_stock * $sell);

            if ($in_stock <= $reorder && ($pr['status'] ?? 'Active') === 'Active') {
                $low_stock_count++;
            }
        }

        // Fetch Movements for tab=movements
        if ($tab === 'movements') {
            $stmt_mov = $pdo->query("
                SELECT sm.*, p.name as product_name, p.product_code, u.name as user_name
                FROM stock_movements sm
                LEFT JOIN products p ON sm.product_id = p.id
                LEFT JOIN users u ON sm.user_id = u.id
                ORDER BY sm.id DESC LIMIT 100
            ");
            $stock_movements = $stmt_mov->fetchAll(PDO::FETCH_ASSOC);
        }

        // Fetch Serials for tab=serials
        if ($tab === 'serials') {
            $stmt_ser = $pdo->query("
                SELECT ps.*, p.name as product_name, p.product_code, c.name as category_name
                FROM product_serials ps
                JOIN products p ON ps.product_id = p.id
                LEFT JOIN categories c ON p.category_id = c.id
                ORDER BY ps.id DESC LIMIT 150
            ");
            $serials_list = $stmt_ser->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (Exception $e) {}
}
?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-boxes-stacked text-emerald-600"></i>
                <span>Products & Inventory Management</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Product catalog, serial/IMEI lifecycle, stock movements, stock take & valuation.</p>
        </div>
        
        <?php if ($can_manage_inventory): ?>
        <div class="flex items-center gap-2.5 flex-wrap">
            <button onclick="openCategoryModal()" class="px-4 py-2.5 rounded-2xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs sm:text-sm font-bold transition-all flex items-center gap-2">
                <i class="fa-solid fa-folder-plus text-slate-500"></i>
                <span>Categories</span>
            </button>

            <button onclick="openStockAdjustmentModal()" class="px-4 py-2.5 rounded-2xl bg-amber-500 hover:bg-amber-600 text-white text-xs sm:text-sm font-bold transition-all shadow-sm shadow-amber-500/20 flex items-center gap-2">
                <i class="fa-solid fa-sliders text-xs"></i>
                <span>Stock Adjustment</span>
            </button>

            <button onclick="openProductModal()" class="px-5 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs sm:text-sm font-bold transition-all shadow-sm shadow-emerald-500/25 flex items-center gap-2">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Add New Product</span>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($msg): ?>
        <div class="<?php echo $msg_type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800'; ?> border px-4 py-3 rounded-2xl text-xs sm:text-sm flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <i class="fa-solid <?php echo $msg_type === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-red-600'; ?>"></i>
                <span><?php echo htmlspecialchars($msg); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-slate-200/80">
        <a href="products.php?tab=catalog" class="px-4 py-2.5 rounded-2xl font-bold text-xs sm:text-sm transition-all whitespace-nowrap <?php echo $tab === 'catalog' ? 'bg-emerald-500 text-white shadow-sm shadow-emerald-500/20' : 'text-slate-600 hover:bg-slate-100'; ?>">
            <i class="fa-solid fa-list-ul mr-1.5"></i> Product Catalog
        </a>
        <a href="products.php?tab=serials" class="px-4 py-2.5 rounded-2xl font-bold text-xs sm:text-sm transition-all whitespace-nowrap <?php echo $tab === 'serials' ? 'bg-emerald-500 text-white shadow-sm shadow-emerald-500/20' : 'text-slate-600 hover:bg-slate-100'; ?>">
            <i class="fa-solid fa-barcode mr-1.5"></i> Serial / IMEI Tracking
        </a>
        <a href="products.php?tab=movements" class="px-4 py-2.5 rounded-2xl font-bold text-xs sm:text-sm transition-all whitespace-nowrap <?php echo $tab === 'movements' ? 'bg-emerald-500 text-white shadow-sm shadow-emerald-500/20' : 'text-slate-600 hover:bg-slate-100'; ?>">
            <i class="fa-solid fa-arrow-right-arrow-left mr-1.5"></i> Stock Movements
        </a>
        <?php if ($can_manage_inventory): ?>
        <a href="products.php?tab=stocktake" class="px-4 py-2.5 rounded-2xl font-bold text-xs sm:text-sm transition-all whitespace-nowrap <?php echo $tab === 'stocktake' ? 'bg-emerald-500 text-white shadow-sm shadow-emerald-500/20' : 'text-slate-600 hover:bg-slate-100'; ?>">
            <i class="fa-solid fa-clipboard-check mr-1.5"></i> Stock Take (Reconciliation)
        </a>
        <a href="products.php?tab=scan" class="px-4 py-2.5 rounded-2xl font-bold text-xs sm:text-sm transition-all whitespace-nowrap <?php echo $tab === 'scan' ? 'bg-emerald-500 text-white shadow-sm shadow-emerald-500/20' : 'text-slate-600 hover:bg-slate-100'; ?>">
            <i class="fa-solid fa-bolt mr-1.5"></i> Rapid Barcode Inbound
        </a>
        <?php endif; ?>
    </div>

    <!-- Inventory KPI Summary Row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- Total In-Stock -->
        <div class="bg-white rounded-3xl p-6 shadow-card border border-slate-100/90 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl font-bold">
                <i class="fa-solid fa-cubes"></i>
            </div>
            <div>
                <h3 class="text-2xl font-extrabold text-slate-900"><?php echo number_format($total_units_in_stock); ?> Units</h3>
                <p class="text-xs text-slate-400 font-medium"><?php echo $total_skus; ?> Active SKUs</p>
            </div>
        </div>

        <!-- Low Stock Alerts -->
        <div class="bg-white rounded-3xl p-6 shadow-card border border-slate-100/90 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl <?php echo $low_stock_count > 0 ? 'bg-amber-50 text-amber-600' : 'bg-slate-50 text-slate-400'; ?> flex items-center justify-center text-xl font-bold">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div>
                <h3 class="text-2xl font-extrabold <?php echo $low_stock_count > 0 ? 'text-amber-600' : 'text-slate-900'; ?>"><?php echo $low_stock_count; ?> Low Stock</h3>
                <p class="text-xs text-slate-400 font-medium">Below reorder threshold</p>
            </div>
        </div>

        <!-- Total Asset Valuation (Cost) -->
        <div class="bg-white rounded-3xl p-6 shadow-card border border-slate-100/90 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl font-bold">
                <i class="fa-solid fa-vault"></i>
            </div>
            <div>
                <h3 class="text-2xl font-extrabold text-blue-600"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($total_asset_cost, 2); ?></h3>
                <p class="text-xs text-slate-400 font-medium">Asset Valuation (Cost Basis)</p>
            </div>
        </div>

        <!-- Total Retail Value -->
        <div class="bg-white rounded-3xl p-6 shadow-card border border-slate-100/90 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl font-bold">
                <i class="fa-solid fa-tags"></i>
            </div>
            <div>
                <h3 class="text-2xl font-extrabold text-indigo-600"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($total_retail_value, 2); ?></h3>
                <p class="text-xs text-slate-400 font-medium">Potential Retail Value</p>
            </div>
        </div>

    </div>

    <?php if ($tab === 'catalog'): ?>
    <!-- Tab 1: Product Catalog -->
    <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7 overflow-hidden">
        
        <!-- Filters & Search Bar -->
        <form method="GET" action="products.php" class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
            <input type="hidden" name="tab" value="catalog">

            <div class="flex items-center gap-3 flex-1 flex-wrap">
                <!-- Search Input -->
                <div class="relative flex-1 min-w-[200px]">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input 
                        type="text" 
                        name="search" 
                        value="<?php echo htmlspecialchars($search_query); ?>" 
                        placeholder="Search SKU, name, barcode, model..." 
                        class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200/80 rounded-xl text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                    >
                </div>

                <!-- Category Filter -->
                <select name="category" onchange="this.form.submit()" class="px-3 py-2 bg-slate-50 border border-slate-200/80 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                    <option value="">All Categories</option>
                    <?php foreach ($categories_list as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $filter_cat == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Brand Filter -->
                <select name="brand" onchange="this.form.submit()" class="px-3 py-2 bg-slate-50 border border-slate-200/80 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                    <option value="">All Brands</option>
                    <?php foreach ($brands_list as $br): ?>
                        <option value="<?php echo $br['id']; ?>" <?php echo $filter_brand == $br['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($br['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Status Filter -->
                <select name="status" onchange="this.form.submit()" class="px-3 py-2 bg-slate-50 border border-slate-200/80 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo $filter_status === 'Active' ? 'selected' : ''; ?>>Active Only</option>
                    <option value="Discontinued" <?php echo $filter_status === 'Discontinued' ? 'selected' : ''; ?>>Discontinued</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 transition-colors">
                    Filter
                </button>
                <?php if ($filter_cat || $filter_brand || $filter_status || $search_query): ?>
                    <a href="products.php?tab=catalog" class="px-3 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold">
                        Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Product Catalog Table -->
        <div class="overflow-x-auto -mx-6 sm:-mx-7 px-6 sm:px-7">
            <table class="w-full text-left border-collapse" id="productsTable">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 pr-4 pl-2">Product Info</th>
                        <th class="py-3.5 px-4">Category & Brand</th>
                        <th class="py-3.5 px-4 text-center">Stock Level</th>
                        <th class="py-3.5 px-4">Location</th>
                        <th class="py-3.5 px-4 text-right">Cost Price</th>
                        <th class="py-3.5 px-4 text-right">Retail / Wholesale</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 pl-4 pr-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 text-xs sm:text-sm text-slate-700">
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $pr): 
                            $in_stock = (int)$pr['in_stock_count'];
                            $reorder = (int)($pr['reorder_level'] ?? 5);
                            $is_low = ($in_stock <= $reorder && ($pr['status'] ?? 'Active') === 'Active');
                            $cost = (float)($pr['cost_price'] ?? 0);
                            $sell = (float)($pr['selling_price'] ?? 0);
                            $wsell = (float)($pr['wholesale_price'] ?? 0);
                        ?>
                        <tr class="hover:bg-slate-50/60 transition-colors group">
                            
                            <!-- Product Info -->
                            <td class="py-4 pr-4 pl-2">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-2xl bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-sm shrink-0">
                                        <i class="fa-solid fa-box"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-slate-900 leading-tight"><?php echo htmlspecialchars($pr['name']); ?></h4>
                                        <div class="flex items-center gap-2 mt-0.5 font-mono text-[11px] text-slate-400">
                                            <span class="text-emerald-700 font-bold"><?php echo htmlspecialchars($pr['product_code']); ?></span>
                                            <?php if (!empty($pr['ean'])): ?>
                                                <span>• Barcode: <?php echo htmlspecialchars($pr['ean']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Category & Brand -->
                            <td class="py-4 px-4 font-semibold text-slate-700">
                                <?php echo htmlspecialchars($pr['category_name'] ?? 'Uncategorized'); ?>
                                <span class="block text-[11px] text-slate-400 font-normal"><?php echo htmlspecialchars($pr['brand_name'] ?? 'Generic'); ?> <?php echo !empty($pr['model_number']) ? '• ' . htmlspecialchars($pr['model_number']) : ''; ?></span>
                            </td>

                            <!-- Stock Level -->
                            <td class="py-4 px-4 text-center">
                                <div class="inline-flex flex-col items-center">
                                    <span class="font-extrabold text-sm <?php echo $in_stock === 0 ? 'text-red-600' : ($is_low ? 'text-amber-600' : 'text-emerald-700'); ?>">
                                        <?php echo $in_stock; ?> <?php echo htmlspecialchars($pr['unit_of_measure'] ?? 'pcs'); ?>
                                    </span>
                                    <?php if ($is_low): ?>
                                        <span class="text-[10px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md mt-0.5">
                                            Low (Min: <?php echo $reorder; ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Location -->
                            <td class="py-4 px-4 font-mono text-xs text-slate-600">
                                <i class="fa-solid fa-location-dot text-slate-400 mr-1"></i>
                                <?php echo htmlspecialchars($pr['location'] ?? 'Main Shelf'); ?>
                            </td>

                            <!-- Cost Price -->
                            <td class="py-4 px-4 text-right font-medium text-slate-500">
                                <?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($cost, 2); ?>
                            </td>

                            <!-- Selling / Wholesale Price -->
                            <td class="py-4 px-4 text-right">
                                <span class="font-bold text-slate-900 block"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($sell, 2); ?></span>
                                <?php if ($wsell > 0): ?>
                                    <span class="text-[10px] text-slate-400 block">Wholesale: <?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($wsell, 2); ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- Status -->
                            <td class="py-4 px-4 text-center">
                                <?php if (($pr['status'] ?? 'Active') === 'Active'): ?>
                                    <span class="inline-block px-2.5 py-1 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold">
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="inline-block px-2.5 py-1 rounded-xl bg-slate-100 text-slate-600 border border-slate-200 text-xs font-bold">
                                        Discontinued
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Actions -->
                            <td class="py-4 pl-4 pr-2 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    
                                    <!-- Print Barcode Label -->
                                    <button onclick="openBarcodeModal(<?php echo htmlspecialchars(json_encode($pr)); ?>)" class="w-8 h-8 rounded-xl border border-slate-200 text-slate-500 hover:text-slate-900 hover:bg-slate-50 flex items-center justify-center transition-colors text-xs" title="Print Barcode Label">
                                        <i class="fa-solid fa-barcode"></i>
                                    </button>

                                    <!-- View Serials -->
                                    <button onclick="viewProductSerials(<?php echo $pr['id']; ?>, '<?php echo addslashes($pr['name']); ?>')" class="w-8 h-8 rounded-xl border border-slate-200 text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 flex items-center justify-center transition-colors text-xs" title="View Serial Numbers">
                                        <i class="fa-solid fa-fingerprint"></i>
                                    </button>

                                    <?php if ($can_manage_inventory): ?>
                                    <!-- Edit Product -->
                                    <button onclick="editProduct(<?php echo htmlspecialchars(json_encode($pr)); ?>)" class="w-8 h-8 rounded-xl border border-slate-200 text-slate-500 hover:text-blue-600 hover:bg-blue-50 flex items-center justify-center transition-colors text-xs" title="Edit Product">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <!-- Delete / Discontinue -->
                                    <form method="POST" action="products.php" onsubmit="return confirm('Discontinue/Delete <?php echo addslashes($pr['name']); ?>?');" class="inline">
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="product_id" value="<?php echo $pr['id']; ?>">
                                        <button type="submit" class="w-8 h-8 rounded-xl border border-slate-200 text-slate-400 hover:text-red-600 hover:bg-red-50 flex items-center justify-center transition-colors text-xs" title="Discontinue / Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>

                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="p-8 text-center text-slate-400 italic">No products found matching the criteria.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
    <?php endif; ?>

    <?php if ($tab === 'serials'): ?>
    <!-- Tab 2: Serial / IMEI Tracking -->
    <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7 overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Serial Number & IMEI Lifecycle Tracking</h2>
                <p class="text-xs text-slate-400 font-medium">Trace each individual hardware device through intake, stock, sale, and warranty.</p>
            </div>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input 
                    type="text" 
                    id="serialSearch" 
                    onkeyup="filterSerialsTable()" 
                    placeholder="Search Serial / IMEI..." 
                    class="pl-10 pr-4 py-2 bg-slate-50 border border-slate-200/80 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 w-64"
                >
            </div>
        </div>

        <div class="overflow-x-auto -mx-6 sm:-mx-7 px-6 sm:px-7">
            <table class="w-full text-left border-collapse" id="serialsTable">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 pr-4 pl-2">Serial / IMEI</th>
                        <th class="py-3.5 px-4">Product Name</th>
                        <th class="py-3.5 px-4">Category</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4">Intake Date</th>
                        <th class="py-3.5 pl-4 pr-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 text-xs sm:text-sm text-slate-700">
                    <?php if (!empty($serials_list)): ?>
                        <?php foreach ($serials_list as $sn): 
                            $sn_st = $sn['status'] ?? 'in_stock';
                            $sn_badge = [
                                'in_stock' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'sold' => 'bg-blue-50 text-blue-700 border-blue-200',
                                'reserved' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                'defective' => 'bg-red-50 text-red-700 border-red-200',
                                'returned' => 'bg-amber-50 text-amber-700 border-amber-200',
                                'repair' => 'bg-purple-50 text-purple-700 border-purple-200'
                            ][$sn_st] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                        ?>
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3.5 pr-4 pl-2 font-mono font-bold text-emerald-700">
                                <?php echo htmlspecialchars($sn['serial_number']); ?>
                            </td>
                            <td class="py-3.5 px-4 font-semibold text-slate-900">
                                <?php echo htmlspecialchars($sn['product_name']); ?>
                                <span class="block text-[11px] text-slate-400 font-mono font-normal"><?php echo htmlspecialchars($sn['product_code']); ?></span>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600 font-medium">
                                <?php echo htmlspecialchars($sn['category_name'] ?? 'Hardware'); ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-block px-2.5 py-1 rounded-xl border text-xs font-bold <?php echo $sn_badge; ?>">
                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $sn_st))); ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-slate-500 text-xs">
                                <?php echo !empty($sn['created_at']) ? date('M j, Y H:i', strtotime($sn['created_at'])) : 'N/A'; ?>
                            </td>
                            <td class="py-3.5 pl-4 pr-2 text-right">
                                <?php if ($can_manage_inventory): ?>
                                <button onclick="openSerialEditModal(<?php echo $sn['id']; ?>, '<?php echo addslashes($sn['serial_number']); ?>', '<?php echo $sn_st; ?>', '<?php echo addslashes($sn['notes'] ?? ''); ?>')" class="px-3 py-1.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-bold">
                                    Change Status
                                </button>
                                <?php else: ?>
                                <span class="text-xs text-slate-400">View only</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-400 italic">No serial numbers found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'movements'): ?>
    <!-- Tab 3: Stock Movement History -->
    <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7 overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Stock Movements & Audit Trail</h2>
                <p class="text-xs text-slate-400 font-medium">Complete immutable log of all inventory inflows, outflows, repairs, and corrections.</p>
            </div>
        </div>

        <div class="overflow-x-auto -mx-6 sm:-mx-7 px-6 sm:px-7">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 pr-4 pl-2">Date / Time</th>
                        <th class="py-3.5 px-4">Product</th>
                        <th class="py-3.5 px-4 text-center">Movement Type</th>
                        <th class="py-3.5 px-4 text-center">Quantity Change</th>
                        <th class="py-3.5 px-4">Reason / Details</th>
                        <th class="py-3.5 px-4">Reference</th>
                        <th class="py-3.5 pl-4 pr-2 text-right">Performed By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 text-xs sm:text-sm text-slate-700">
                    <?php if (!empty($stock_movements)): ?>
                        <?php foreach ($stock_movements as $sm): 
                            $mtype = $sm['movement_type'] ?? 'Adjustment In';
                            $is_add = strpos($mtype, 'In') !== false;
                            $mtype_badge = $is_add ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200';
                        ?>
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3.5 pr-4 pl-2 text-slate-400 font-mono text-xs">
                                <?php echo date('M j, Y H:i', strtotime($sm['created_at'])); ?>
                            </td>
                            <td class="py-3.5 px-4 font-semibold text-slate-900">
                                <?php echo htmlspecialchars($sm['product_name'] ?? 'Product'); ?>
                                <span class="block text-[11px] text-slate-400 font-mono"><?php echo htmlspecialchars($sm['product_code'] ?? ''); ?></span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-block px-2.5 py-1 rounded-xl border text-xs font-bold <?php echo $mtype_badge; ?>">
                                    <?php echo htmlspecialchars($mtype); ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center font-extrabold <?php echo $is_add ? 'text-emerald-700' : 'text-red-600'; ?>">
                                <?php echo $is_add ? '+' : '-'; ?><?php echo $sm['quantity']; ?>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600">
                                <?php echo htmlspecialchars($sm['reason'] ?? 'Inventory update'); ?>
                                <?php if (!empty($sm['serial_number'])): ?>
                                    <span class="block text-[10px] font-mono text-slate-400">SN: <?php echo htmlspecialchars($sm['serial_number']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 font-mono text-xs text-slate-500">
                                <?php echo htmlspecialchars($sm['reference_id'] ?? '-'); ?>
                            </td>
                            <td class="py-3.5 pl-4 pr-2 text-right font-medium text-slate-700">
                                <?php echo htmlspecialchars($sm['user_name'] ?? 'Staff'); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400 italic">No stock movements logged yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'stocktake'): ?>
    <!-- Tab 4: Physical Stock Count Reconciliation -->
    <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Physical Stock Count (Stock Take)</h2>
                <p class="text-xs text-slate-400 font-medium">Verify physical counts against system inventory and reconcile discrepancies with 1-click.</p>
            </div>
        </div>

        <form method="POST" action="products.php" onsubmit="return confirm('Apply physical stock reconciliation? This will update system counts and log variance audit records.');">
            <input type="hidden" name="action" value="reconcile_stocktake">

            <div class="overflow-x-auto -mx-6 sm:-mx-7 px-6 sm:px-7 mb-6">
                <table class="w-full text-left border-collapse" id="stockTakeTable">
                    <thead>
                        <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                            <th class="py-3.5 pr-4 pl-2">Product Name</th>
                            <th class="py-3.5 px-4">Location</th>
                            <th class="py-3.5 px-4 text-center">System Count</th>
                            <th class="py-3.5 px-4 text-center">Physical Count</th>
                            <th class="py-3.5 pl-4 pr-2 text-right">Variance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-xs sm:text-sm text-slate-700">
                        <?php foreach ($products as $pr): 
                            $sys_qty = (int)$pr['in_stock_count'];
                        ?>
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3.5 pr-4 pl-2 font-bold text-slate-900">
                                <?php echo htmlspecialchars($pr['name']); ?>
                                <span class="block text-[11px] text-slate-400 font-mono font-normal"><?php echo htmlspecialchars($pr['product_code']); ?></span>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600 font-mono">
                                <?php echo htmlspecialchars($pr['location'] ?? 'Main Shelf'); ?>
                            </td>
                            <td class="py-3.5 px-4 text-center font-bold text-slate-700" id="sysQty_<?php echo $pr['id']; ?>">
                                <?php echo $sys_qty; ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <input 
                                    type="number" 
                                    name="physical_count[<?php echo $pr['id']; ?>]" 
                                    value="<?php echo $sys_qty; ?>" 
                                    min="0" 
                                    oninput="recalcVariance(<?php echo $pr['id']; ?>, <?php echo $sys_qty; ?>, this.value)"
                                    class="w-24 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-center font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                >
                            </td>
                            <td class="py-3.5 pl-4 pr-2 text-right font-extrabold text-slate-400" id="variance_<?php echo $pr['id']; ?>">
                                0
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-3 bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs sm:text-sm rounded-2xl transition-all shadow-sm shadow-emerald-500/25">
                    Save & Reconcile Physical Stocktake
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'scan'): ?>
    <!-- Tab 5: Rapid Barcode & Serial Inbound Scanner -->
    <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7">
        <h2 class="text-lg font-bold text-slate-900 mb-2">Rapid Serial Number Inbound Scanner</h2>
        <p class="text-xs text-slate-400 mb-6">Scan product barcode once, then rapidly scan unique serial numbers into stock.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Step 1 -->
            <div class="p-6 rounded-2xl bg-slate-50 border border-slate-200/80">
                <h3 class="font-bold text-slate-800 text-sm mb-3"><span class="bg-emerald-500 text-white w-5 h-5 rounded-full inline-flex items-center justify-center text-xs mr-2">1</span> Scan Product Barcode</h3>
                <form id="productSearchForm" onsubmit="lookupProduct(event)">
                    <div class="relative">
                        <i class="fa-solid fa-box-open absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="text" id="productBarcode" class="w-full pl-11 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs sm:text-sm uppercase focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500" placeholder="Scan SKU, EAN or UPC..." autofocus required>
                    </div>
                </form>

                <div id="activeProductDisplay" class="mt-4 p-4 bg-emerald-50 border border-emerald-200 rounded-xl hidden">
                    <span class="text-[10px] text-emerald-600 font-bold uppercase tracking-wider block mb-1">Active Product</span>
                    <h4 id="activeProductName" class="font-bold text-slate-900 text-base"></h4>
                    <p id="activeProductCode" class="text-xs text-slate-500 font-mono"></p>
                    <input type="hidden" id="activeProductId" value="">
                </div>
            </div>

            <!-- Step 2 -->
            <div class="p-6 rounded-2xl bg-slate-50 border border-slate-200/80 opacity-50" id="step2Box">
                <h3 class="font-bold text-slate-800 text-sm mb-3"><span class="bg-emerald-500 text-white w-5 h-5 rounded-full inline-flex items-center justify-center text-xs mr-2">2</span> Rapidly Scan Serial Numbers</h3>
                <form id="serialEntryForm" onsubmit="addSerial(event)">
                    <div class="relative">
                        <i class="fa-solid fa-barcode absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="text" id="serialInput" disabled class="w-full pl-11 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs sm:text-sm font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500" placeholder="Scan Serial / IMEI Number...">
                    </div>
                </form>

                <div class="mt-4">
                    <span class="text-xs font-bold text-slate-600 block mb-2">Scanned in this session (<span id="scanCount">0</span>):</span>
                    <div id="scannedLog" class="max-h-36 overflow-y-auto space-y-1 text-xs font-mono text-slate-700 bg-white p-2 rounded-xl border border-slate-200">
                        <span class="text-slate-400 italic">No serials scanned yet.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Modal: Add / Edit Product -->
<div id="productModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-3xl p-7 relative max-h-[90vh] overflow-y-auto">
        <button onclick="closeProductModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center gap-3 mb-5 pb-3 border-b border-slate-100">
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                <i class="fa-solid fa-box"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-slate-900" id="productModalTitle">Add New Product</h3>
                <p class="text-xs text-slate-400">Complete catalog details, pricing & specifications</p>
            </div>
        </div>

        <form method="POST" action="products.php" class="space-y-4">
            <input type="hidden" name="action" value="save_product">
            <input type="hidden" name="product_id" id="prodFormId" value="0">

            <!-- Section 1: Basic Information -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Product Name *</label>
                    <input type="text" name="name" id="prodFormName" required placeholder="e.g. Dell XPS 15 9530 Core i7" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Category *</label>
                    <select name="category_id" id="prodFormCategory" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        <option value="">Select Category</option>
                        <?php foreach ($categories_list as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Brand</label>
                    <select name="brand_id" id="prodFormBrand" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        <option value="">Select Brand</option>
                        <?php foreach ($brands_list as $br): ?>
                            <option value="<?php echo $br['id']; ?>"><?php echo htmlspecialchars($br['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Sub-Category</label>
                    <input type="text" name="sub_category" id="prodFormSubCat" placeholder="e.g. Gaming Laptops, NVMe SSD" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Model Number</label>
                    <input type="text" name="model_number" id="prodFormModel" placeholder="e.g. 9530-XPS" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">SKU / Product Code</label>
                    <input type="text" name="product_code" id="prodFormCode" placeholder="Auto-generated if empty" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Barcode (EAN / UPC)</label>
                    <input type="text" name="ean" id="prodFormBarcode" placeholder="Scan or enter barcode" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>
            </div>

            <!-- Section 2: Pricing & Inventory Parameters -->
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/70 space-y-3">
                <span class="text-xs font-bold text-slate-700 uppercase tracking-wider block">Pricing & Stock Controls</span>
                
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Cost Price (<?php echo htmlspecialchars($currency_symbol); ?>) *</label>
                        <input type="number" step="0.01" name="cost_price" id="prodFormCost" value="0.00" required class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Selling Price (<?php echo htmlspecialchars($currency_symbol); ?>) *</label>
                        <input type="number" step="0.01" name="selling_price" id="prodFormSell" value="0.00" required class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Wholesale Price (<?php echo htmlspecialchars($currency_symbol); ?>)</label>
                        <input type="number" step="0.01" name="wholesale_price" id="prodFormWholesale" value="0.00" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Tax Rate (%)</label>
                        <input type="number" step="0.01" name="tax_rate" id="prodFormTax" value="0.00" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Reorder Level</label>
                        <input type="number" name="reorder_level" id="prodFormReorder" value="5" min="0" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Shelf / Bin Location</label>
                        <input type="text" name="location" id="prodFormLocation" value="Aisle 1, Shelf A" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-mono text-slate-800">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Unit of Measure</label>
                        <select name="unit_of_measure" id="prodFormUom" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-800">
                            <option value="pcs">Pieces (pcs)</option>
                            <option value="box">Box</option>
                            <option value="meter">Meter (cables)</option>
                            <option value="set">Set / Kit</option>
                        </select>
                    </div>
                </div>

                <div id="initialStockDiv">
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Initial Stock Intake (Optional)</label>
                    <input type="number" name="initial_stock" id="prodFormInitialStock" value="0" min="0" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                </div>
            </div>

            <!-- Section 3: Technical Specifications & Status -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Technical Specifications</label>
                    <textarea name="specifications" id="prodFormSpecs" rows="3" placeholder="e.g. CPU: Intel i7-13700H, RAM: 16GB DDR5, SSD: 1TB NVMe, GPU: RTX 4060" class="w-full px-4 py-2 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Status & Catalog Visibility</label>
                    <select name="status" id="prodFormStatus" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        <option value="Active">Active (Available for POS & Sales)</option>
                        <option value="Discontinued">Discontinued (Archived)</option>
                    </select>
                </div>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="closeProductModal()" class="px-5 py-2.5 rounded-2xl border border-slate-200 text-slate-700 font-bold text-xs sm:text-sm hover:bg-slate-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs sm:text-sm transition-all shadow-sm shadow-emerald-500/25">
                    Save Product
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Stock Adjustment -->
<div id="stockAdjustmentModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-lg p-7 relative">
        <button onclick="closeStockAdjustmentModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center gap-3 mb-5 pb-3 border-b border-slate-100">
            <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg font-bold">
                <i class="fa-solid fa-sliders"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-slate-900">Inventory Stock Adjustment</h3>
                <p class="text-xs text-slate-400">Manual adjustments for damage, loss, theft, or count correction</p>
            </div>
        </div>

        <form method="POST" action="products.php" class="space-y-4">
            <input type="hidden" name="action" value="stock_adjustment">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Product *</label>
                <select name="product_id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                    <?php foreach ($products as $pr): ?>
                        <option value="<?php echo $pr['id']; ?>">
                            <?php echo htmlspecialchars($pr['name'] . ' (' . $pr['product_code'] . ') [Stock: ' . $pr['in_stock_count'] . ']'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Adjustment Type *</label>
                    <select name="adjustment_type" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        <option value="subtract">Deduct Stock (- Loss / Damage)</option>
                        <option value="add">Add Stock (+ Found / Inward)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Quantity *</label>
                    <input type="number" name="quantity" value="1" min="1" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Reason Code *</label>
                <select name="reason" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                    <option value="Count Correction">Count Correction</option>
                    <option value="Damaged in transit / Store">Damaged in Store / Transit</option>
                    <option value="Shrinkage / Theft / Loss">Shrinkage / Theft / Loss</option>
                    <option value="Found Unrecorded Stock">Found Unrecorded Stock</option>
                    <option value="Used in In-House Demo">In-House Demo / Testing</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Serial Number (Optional)</label>
                <input type="text" name="serial_number" placeholder="Specific S/N if adjusting 1 unit" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Notes / Explanation</label>
                <textarea name="notes" rows="2" placeholder="Details of write-off or reason..." class="w-full px-4 py-2 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs text-slate-800"></textarea>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="closeStockAdjustmentModal()" class="px-5 py-2.5 rounded-2xl border border-slate-200 text-slate-700 font-bold text-xs sm:text-sm hover:bg-slate-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-2xl bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs sm:text-sm transition-all shadow-sm shadow-amber-500/25">
                    Apply Adjustment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Manage Categories -->
<div id="categoryModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-md p-7 relative">
        <button onclick="closeCategoryModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-lg font-bold text-slate-900 mb-1 flex items-center gap-2">
            <i class="fa-solid fa-folder text-emerald-600"></i> Manage Categories
        </h3>
        <p class="text-xs text-slate-400 mb-5">Add or remove product catalog categories.</p>

        <!-- Add Category Input -->
        <form onsubmit="submitCategory(event)" class="mb-5">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">New Category Name</label>
            <div class="flex gap-2">
                <input type="text" id="newCategoryName" required placeholder="e.g. Graphics Cards, RAM..." class="flex-1 px-4 py-2 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                <button type="submit" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs rounded-2xl transition-colors">
                    + Add
                </button>
            </div>
        </form>

        <!-- Categories List -->
        <div>
            <span class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Existing Categories</span>
            <div id="categoriesContainer" class="max-h-60 overflow-y-auto space-y-1 bg-slate-50 p-2 rounded-2xl border border-slate-200/80 text-xs">
                <div class="p-3 text-center text-slate-400">Loading categories...</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Barcode Label Print Generator -->
<div id="barcodeModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-sm p-7 relative text-center">
        <button onclick="closeBarcodeModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-lg font-bold text-slate-900 mb-1">Barcode & Shelf Label</h3>
        <p class="text-xs text-slate-400 mb-5">Print standard sticker label for shelf or box</p>

        <!-- Printable Label Area -->
        <div id="printableBarcodeLabel" class="p-4 border-2 border-dashed border-slate-300 rounded-2xl bg-white space-y-2 mb-5">
            <span class="text-[11px] font-extrabold text-slate-900 uppercase tracking-wider block" id="lblShopName">Computer Solutions & Repair</span>
            <h4 class="font-bold text-slate-900 text-sm leading-tight" id="lblProdName">Product Name</h4>
            
            <div class="py-2 flex justify-center">
                <svg id="barcodeCanvas"></svg>
            </div>

            <div class="flex justify-between items-center text-xs font-mono font-bold px-2 pt-1 border-t border-slate-100">
                <span class="text-slate-500" id="lblSku">SKU-0000</span>
                <span class="text-emerald-700 font-extrabold text-sm" id="lblPrice"><?php echo htmlspecialchars($currency_symbol); ?> 0.00</span>
            </div>
        </div>

        <button onclick="window.print()" class="w-full py-2.5 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition-colors flex items-center justify-center gap-2">
            <i class="fa-solid fa-print"></i> Print Label
        </button>
    </div>
</div>

<!-- Modal: View Product Serials -->
<div id="productSerialsModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-xl p-7 relative max-h-[90vh] overflow-y-auto">
        <button onclick="closeProductSerialsModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-lg font-bold text-slate-900 mb-1" id="psModalTitle">Product Serials</h3>
        <p class="text-xs text-slate-400 mb-5">All serial numbers currently registered for this model</p>

        <div id="psModalContent" class="space-y-2">
            <div class="p-4 text-center text-slate-400 italic">Loading serials...</div>
        </div>
    </div>
</div>

<!-- Modal: Change Serial Status -->
<div id="serialEditModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-sm p-7 relative">
        <button onclick="closeSerialEditModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-lg font-bold text-slate-900 mb-1">Update Serial Status</h3>
        <p class="text-xs text-slate-400 mb-4 font-mono font-bold text-emerald-700" id="editSerialNum"></p>

        <form onsubmit="submitSerialStatus(event)" class="space-y-3">
            <input type="hidden" id="editSerialId" value="0">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Status</label>
                <select id="editSerialStatusSelect" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                    <option value="in_stock">In Stock (Available)</option>
                    <option value="reserved">Reserved</option>
                    <option value="sold">Sold</option>
                    <option value="defective">Defective / RMA</option>
                    <option value="returned">Returned</option>
                    <option value="repair">In Repair</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Notes / Reason</label>
                <input type="text" id="editSerialNotes" placeholder="Reason for status change..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800">
            </div>

            <div class="pt-2 flex justify-end gap-2">
                <button type="button" onclick="closeSerialEditModal()" class="px-4 py-2 border border-slate-200 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl text-xs font-bold">Update</button>
            </div>
        </form>
    </div>
</div>

<!-- JSBarcode CDN for barcode generation -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<script>
// Modal Controls
function openProductModal() {
    document.getElementById('prodFormId').value = 0;
    document.getElementById('productModalTitle').textContent = 'Add New Product';
    document.getElementById('prodFormName').value = '';
    document.getElementById('prodFormCategory').value = '';
    document.getElementById('prodFormBrand').value = '';
    document.getElementById('prodFormSubCat').value = '';
    document.getElementById('prodFormModel').value = '';
    document.getElementById('prodFormCode').value = '';
    document.getElementById('prodFormBarcode').value = '';
    document.getElementById('prodFormCost').value = '0.00';
    document.getElementById('prodFormSell').value = '0.00';
    document.getElementById('prodFormWholesale').value = '0.00';
    document.getElementById('prodFormTax').value = '0.00';
    document.getElementById('prodFormReorder').value = '5';
    document.getElementById('prodFormLocation').value = 'Aisle 1, Shelf A';
    document.getElementById('prodFormUom').value = 'pcs';
    document.getElementById('prodFormSpecs').value = '';
    document.getElementById('prodFormStatus').value = 'Active';
    document.getElementById('initialStockDiv').classList.remove('hidden');

    document.getElementById('productModal').classList.remove('hidden');
}

function editProduct(pr) {
    document.getElementById('prodFormId').value = pr.id;
    document.getElementById('productModalTitle').textContent = 'Edit Product: ' + pr.name;
    document.getElementById('prodFormName').value = pr.name || '';
    document.getElementById('prodFormCategory').value = pr.category_id || '';
    document.getElementById('prodFormBrand').value = pr.brand_id || '';
    document.getElementById('prodFormSubCat').value = pr.sub_category || '';
    document.getElementById('prodFormModel').value = pr.model_number || '';
    document.getElementById('prodFormCode').value = pr.product_code || '';
    document.getElementById('prodFormBarcode').value = pr.ean || '';
    document.getElementById('prodFormCost').value = parseFloat(pr.cost_price || 0).toFixed(2);
    document.getElementById('prodFormSell').value = parseFloat(pr.selling_price || 0).toFixed(2);
    document.getElementById('prodFormWholesale').value = parseFloat(pr.wholesale_price || 0).toFixed(2);
    document.getElementById('prodFormTax').value = parseFloat(pr.tax_rate || 0).toFixed(2);
    document.getElementById('prodFormReorder').value = pr.reorder_level || 5;
    document.getElementById('prodFormLocation').value = pr.location || 'Main Shelf';
    document.getElementById('prodFormUom').value = pr.unit_of_measure || 'pcs';
    document.getElementById('prodFormSpecs').value = pr.specifications || '';
    document.getElementById('prodFormStatus').value = pr.status || 'Active';
    document.getElementById('initialStockDiv').classList.add('hidden');

    document.getElementById('productModal').classList.remove('hidden');
}

function closeProductModal() {
    document.getElementById('productModal').classList.add('hidden');
}

function openStockAdjustmentModal() {
    document.getElementById('stockAdjustmentModal').classList.remove('hidden');
}
function closeStockAdjustmentModal() {
    document.getElementById('stockAdjustmentModal').classList.add('hidden');
}

// Category Management
function openCategoryModal() {
    loadCategories();
    document.getElementById('categoryModal').classList.remove('hidden');
}
function closeCategoryModal() {
    document.getElementById('categoryModal').classList.add('hidden');
}

function loadCategories() {
    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('action', 'get_categories');

    fetch('products.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            let html = '';
            d.categories.forEach(cat => {
                html += `
                    <div class="flex items-center justify-between p-2.5 bg-white rounded-xl border border-slate-200/70">
                        <span class="font-bold text-slate-800">${cat.name} (${cat.product_count} products)</span>
                        <button type="button" onclick="deleteCategory(${cat.id})" class="text-slate-400 hover:text-red-600 transition-colors"><i class="fa-solid fa-trash text-xs"></i></button>
                    </div>
                `;
            });
            document.getElementById('categoriesContainer').innerHTML = html || '<div class="p-3 text-center text-slate-400">No categories found.</div>';
        }
    });
}

function submitCategory(e) {
    e.preventDefault();
    const name = document.getElementById('newCategoryName').value.trim();
    if (!name) return;

    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('action', 'add_category');
    fd.append('category_name', name);

    fetch('products.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById('newCategoryName').value = '';
            loadCategories();
        } else {
            alert(d.message);
        }
    });
}

function deleteCategory(id) {
    if (!confirm('Delete this category?')) return;
    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('action', 'delete_category');
    fd.append('category_id', id);

    fetch('products.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            loadCategories();
        } else {
            alert(d.message);
        }
    });
}

// Barcode Generation Modal
function openBarcodeModal(pr) {
    document.getElementById('lblProdName').textContent = pr.name;
    document.getElementById('lblSku').textContent = pr.product_code;
    document.getElementById('lblPrice').textContent = (window.CURRENCY_SYMBOL || 'Rs.') + ' ' + parseFloat(pr.selling_price || 0).toFixed(2);

    const codeToRender = pr.ean || pr.product_code || '000000';
    try {
        JsBarcode("#barcodeCanvas", codeToRender, {
            format: "CODE128",
            lineColor: "#000",
            width: 1.5,
            height: 40,
            displayValue: true,
            fontSize: 11
        });
    } catch (e) {
        console.log("Barcode format fallback", e);
    }

    document.getElementById('barcodeModal').classList.remove('hidden');
}
function closeBarcodeModal() {
    document.getElementById('barcodeModal').classList.add('hidden');
}

// View Serials Modal
function viewProductSerials(pid, pname) {
    document.getElementById('psModalTitle').textContent = pname + ' Serials';
    const container = document.getElementById('psModalContent');
    container.innerHTML = '<div class="p-4 text-center text-slate-400 italic">Loading serials...</div>';
    document.getElementById('productSerialsModal').classList.remove('hidden');

    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('action', 'get_product_serials');
    fd.append('product_id', pid);

    fetch('products.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success && d.serials.length > 0) {
            let html = '<div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">';
            d.serials.forEach(sn => {
                const st = sn.status;
                const badge = st === 'in_stock' ? 'bg-emerald-50 text-emerald-700' : (st === 'sold' ? 'bg-blue-50 text-blue-700' : 'bg-red-50 text-red-700');
                html += `
                    <div class="p-2.5 bg-slate-50 border border-slate-200/80 rounded-xl flex items-center justify-between">
                        <div>
                            <span class="font-mono font-bold text-slate-900 block">${sn.serial_number}</span>
                            <span class="text-[10px] text-slate-400">${sn.created_at ? sn.created_at.substring(0, 10) : ''}</span>
                        </div>
                        <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold ${badge}">${st}</span>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div class="p-6 text-center text-slate-400 italic">No serial numbers found for this product.</div>';
        }
    });
}
function closeProductSerialsModal() {
    document.getElementById('productSerialsModal').classList.add('hidden');
}

// Edit Serial Status
function openSerialEditModal(id, sn, st, notes) {
    document.getElementById('editSerialId').value = id;
    document.getElementById('editSerialNum').textContent = 'Serial: ' + sn;
    document.getElementById('editSerialStatusSelect').value = st;
    document.getElementById('editSerialNotes').value = notes || '';
    document.getElementById('serialEditModal').classList.remove('hidden');
}
function closeSerialEditModal() {
    document.getElementById('serialEditModal').classList.add('hidden');
}

function submitSerialStatus(e) {
    e.preventDefault();
    const id = document.getElementById('editSerialId').value;
    const st = document.getElementById('editSerialStatusSelect').value;
    const notes = document.getElementById('editSerialNotes').value;

    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('action', 'update_serial_status');
    fd.append('serial_id', id);
    fd.append('status', st);
    fd.append('notes', notes);

    fetch('products.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            closeSerialEditModal();
            location.reload();
        } else {
            alert(d.message);
        }
    });
}

// Rapid Barcode Scanner
let activeProduct = null;
let scannedCount = 0;

function lookupProduct(e) {
    e.preventDefault();
    const barcode = document.getElementById('productBarcode').value;
    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('action', 'lookup_product');
    fd.append('code', barcode);

    fetch('products.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            activeProduct = d.product;
            document.getElementById('activeProductName').textContent = d.product.name;
            document.getElementById('activeProductCode').textContent = d.product.product_code;
            document.getElementById('activeProductId').value = d.product.id;
            document.getElementById('activeProductDisplay').classList.remove('hidden');
            
            document.getElementById('step2Box').classList.remove('opacity-50', 'pointer-events-none');
            document.getElementById('serialInput').disabled = false;
            document.getElementById('serialInput').focus();
        } else {
            alert(d.message);
        }
    });
}

function addSerial(e) {
    e.preventDefault();
    if (!activeProduct) return;
    const serial = document.getElementById('serialInput').value.trim();
    if (!serial) return;

    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('action', 'add_serial');
    fd.append('product_id', activeProduct.id);
    fd.append('serial_number', serial);

    fetch('products.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            scannedCount++;
            document.getElementById('scanCount').textContent = scannedCount;
            const log = document.getElementById('scannedLog');
            if (scannedCount === 1) log.innerHTML = '';
            log.innerHTML = `<div class="text-emerald-700 font-bold"><i class="fa-solid fa-check mr-1"></i> ${serial}</div>` + log.innerHTML;
            document.getElementById('serialInput').value = '';
        } else {
            alert(d.message);
        }
    });
}

function recalcVariance(pid, sysQty, physQty) {
    const diff = parseInt(physQty || 0) - parseInt(sysQty);
    const varEl = document.getElementById('variance_' + pid);
    if (diff > 0) {
        varEl.textContent = '+' + diff;
        varEl.className = 'py-3.5 pl-4 pr-2 text-right font-extrabold text-emerald-600';
    } else if (diff < 0) {
        varEl.textContent = diff;
        varEl.className = 'py-3.5 pl-4 pr-2 text-right font-extrabold text-red-600';
    } else {
        varEl.textContent = '0';
        varEl.className = 'py-3.5 pl-4 pr-2 text-right font-extrabold text-slate-400';
    }
}

function filterSerialsTable() {
    const q = document.getElementById('serialSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#serialsTable tbody tr');
    rows.forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
