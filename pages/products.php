<?php
require_once '../includes/db.php';

// Handle AJAX requests for stock entry (MUST BE BEFORE ANY HTML OUTPUT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => ''];
    
    if ($action === 'lookup_product' || $action === 'add_serial' || $action === 'add_category' || $action === 'get_categories' || $action === 'delete_category') {
        header('Content-Type: application/json');

        if ($action === 'get_categories') {
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
            } catch (\PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
            }
            exit;
        }

        if ($action === 'delete_category') {
            $cat_id = (int)$_POST['category_id'];
            if (!$cat_id) {
                echo json_encode(['success' => false, 'message' => 'Invalid Category ID.']);
                exit;
            }

            // Check if products are currently assigned to this category
            $chk = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
            $chk->execute([$cat_id]);
            $count = $chk->fetchColumn();

            if ($count > 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => "Cannot delete category. $count product(s) are currently assigned to it."
                ]);
                exit;
            }

            try {
                $del = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $del->execute([$cat_id]);
                echo json_encode(['success' => true, 'message' => 'Category deleted successfully!']);
            } catch (\PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
            }
            exit;
        }

        if ($action === 'add_category') {
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

                echo json_encode([
                    'success' => true, 
                    'message' => "Category '$cat_name' added successfully!",
                    'category' => ['id' => (int)$new_id, 'name' => $cat_name]
                ]);
            } catch (\PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
            }
            exit;
        }

        if ($action === 'lookup_product') {
            $code = trim($_POST['code']);
            $stmt = $pdo->prepare("SELECT id, name, product_code FROM products WHERE product_code = ? OR ean = ? OR upc = ?");
            $stmt->execute([$code, $code, $code]);
            $product = $stmt->fetch();

            if ($product) {
                $response['success'] = true;
                $response['product'] = $product;
            } else {
                $response['message'] = 'Product not found. Please add the product model first.';
            }
        }

        if ($action === 'add_serial') {
            $product_id = (int)$_POST['product_id'];
            $serial = trim($_POST['serial_number']);
            
            try {
                $check = $pdo->prepare("SELECT id FROM product_serials WHERE serial_number = ?");
                $check->execute([$serial]);
                if ($check->fetch()) {
                    $response['message'] = "Serial Number '$serial' already exists!";
                } else {
                    $insert = $pdo->prepare("INSERT INTO product_serials (product_id, serial_number, status) VALUES (?, ?, 'in_stock')");
                    $insert->execute([$product_id, $serial]);
                    $response['success'] = true;
                    $response['message'] = "Added '$serial'";
                }
            } catch (\PDOException $e) {
                $response['message'] = 'Database Error: ' . $e->getMessage();
            }
        }
        
        echo json_encode($response);
        exit;
    }
}

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_product') {
    $del_id = (int)$_POST['product_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$del_id]);
        $success_msg = "Product deleted successfully.";
    } catch (\PDOException $e) {
        $error_msg = "Cannot delete product because it has associated stock or sales history. Please edit the product and set it to 'Discontinued' instead.";
    }
}

require_once '../includes/header.php';

// Fetch Catalog Data & Categories for the Table
$products = [];
$categories_list = [];
if ($pdo) {
    try {
        $cat_stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
        $categories_list = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("
            SELECT p.*, c.name as category_name, b.name as brand_name,
                   (SELECT COUNT(*) FROM product_serials WHERE product_id = p.id AND status = 'in_stock') as in_stock_count
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN brands b ON p.brand_id = b.id
            ORDER BY p.id DESC LIMIT 100
        ");
        $products = $stmt->fetchAll();
    } catch (\PDOException $e) {
        $db_error = $e->getMessage();
    }
}
?>

<!-- Manage Categories Modal -->
<div id="categoryModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex justify-center items-center opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden transform scale-95 transition-transform duration-300" id="categoryModalContent">
        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800 flex items-center">
                <i class="fa-solid fa-folder text-emerald-600 mr-2"></i> Manage Categories
            </h3>
            <button onclick="closeCategoryModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        
        <div class="p-6">
            <div id="catModalMsg" class="mb-4 text-sm hidden p-3 rounded-lg"></div>

            <!-- Add Category Form -->
            <form onsubmit="submitCategory(event)" class="mb-6">
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Add New Category</label>
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-folder-plus"></i>
                        </div>
                        <input type="text" id="newCategoryName" required class="block w-full pl-10 pr-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none" placeholder="Enter Category Name (e.g. Storage, RAM)...">
                    </div>
                    <button type="submit" id="saveCatBtn" class="px-4 py-2 text-sm font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors flex items-center shadow-sm shrink-0">
                        <i class="fa-solid fa-plus mr-1"></i> Add
                    </button>
                </div>
            </form>

            <!-- Existing Categories List -->
            <div>
                <div class="flex justify-between items-center mb-2">
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider">Existing Categories</label>
                    <span id="catCountBadge" class="text-xs text-slate-500 font-medium"></span>
                </div>
                <div class="max-h-60 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100 bg-slate-50" id="categoriesContainer">
                    <div class="p-4 text-center text-slate-400 text-sm">Loading categories...</div>
                </div>
            </div>
        </div>

        <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
            <button type="button" onclick="closeCategoryModal()" class="px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-200 rounded-lg transition-colors">Close</button>
        </div>
    </div>
</div>

<div class="max-w-6xl mx-auto flex flex-col gap-6 h-[calc(100vh-100px)] overflow-y-auto pb-8">
    
    <div class="flex flex-col sm:flex-row justify-between sm:items-center shrink-0 mt-4 gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Products & Inventory</h1>
            <p class="text-slate-500 text-sm mt-1">Manage your catalog, add categories, and enter stock rapidly.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="openCategoryModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition-colors shadow-sm flex items-center cursor-pointer">
                <i class="fa-solid fa-folder-plus mr-2"></i> Add Category
            </button>
            <a href="product_add.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition-colors shadow-sm flex items-center">
                <i class="fa-solid fa-plus mr-2"></i> New Product Model
            </a>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg flex items-center">
            <i class="fa-solid fa-check-circle mr-2 text-emerald-600"></i>
            <span class="text-sm font-medium"><?php echo $success_msg; ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($error_msg): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center">
            <i class="fa-solid fa-triangle-exclamation mr-2 text-red-600"></i>
            <span class="text-sm font-medium"><?php echo $error_msg; ?></span>
        </div>
    <?php endif; ?>

    <!-- Rapid Stock Entry Section -->
    <div class="bg-slate-800 rounded-xl shadow-lg border border-slate-700 p-6 relative overflow-hidden shrink-0">
        <div class="absolute top-0 right-0 w-64 h-64 bg-blue-500 rounded-bl-full -mr-32 -mt-32 opacity-10 pointer-events-none"></div>
        
        <h2 class="font-bold text-white text-lg mb-4 flex items-center">
            <i class="fa-solid fa-bolt text-amber-400 mr-2"></i> Rapid Stock Entry (Scan Mode)
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 relative z-10">
            <!-- Step 1: Scan Product -->
            <div class="bg-slate-700/50 rounded-xl border border-slate-600 p-5 transition-all" id="step1Box">
                <h3 class="font-bold text-slate-200 text-sm mb-3 flex items-center">
                    <span class="bg-blue-500 text-white w-5 h-5 rounded-full flex items-center justify-center text-xs mr-2">1</span>
                    Scan Product Barcode
                </h3>
                
                <form id="productSearchForm" onsubmit="lookupProduct(event)">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-box-open"></i>
                        </div>
                        <input type="text" id="productBarcode" class="block w-full pl-10 pr-3 py-3 border border-slate-600 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none uppercase bg-slate-800 text-white transition-colors" placeholder="Scan Product Code / EAN..." autofocus autocomplete="off">
                    </div>
                </form>

                <div id="activeProductDisplay" class="mt-3 p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-lg hidden">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-xs text-emerald-400 font-bold uppercase tracking-wider mb-1">Active Product</div>
                            <div id="activeProductName" class="font-bold text-white text-lg">Dell XPS 15</div>
                            <div id="activeProductCode" class="text-slate-400 text-sm font-mono mt-1">LT-DXPS15</div>
                            <input type="hidden" id="activeProductId" value="">
                        </div>
                        <button type="button" onclick="resetProduct()" class="text-slate-400 hover:text-red-400 text-sm underline">Change</button>
                    </div>
                </div>
                
                <div id="productError" class="mt-3 text-red-400 text-sm font-medium hidden">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i> <span></span>
                </div>
            </div>

            <!-- Step 2: Scan Serials -->
            <div class="bg-slate-700/50 rounded-xl border border-slate-600 p-5 opacity-50 pointer-events-none transition-all" id="step2Box">
                <h3 class="font-bold text-slate-200 text-sm mb-3 flex items-center">
                    <span class="bg-slate-600 text-slate-300 w-5 h-5 rounded-full flex items-center justify-center text-xs mr-2" id="step2Badge">2</span>
                    Scan Unique Serial Numbers (S/N)
                </h3>
                
                <form id="serialForm" onsubmit="addSerial(event)">
                    <div class="flex rounded-lg border border-slate-600 focus-within:ring-2 focus-within:ring-emerald-500 focus-within:border-emerald-500 transition-shadow bg-slate-800">
                        <div class="px-3 py-3 border-r border-slate-600 text-slate-400 flex items-center justify-center rounded-l-lg">
                            <i class="fa-solid fa-barcode"></i>
                        </div>
                        <input type="text" id="serialInput" class="flex-1 w-full px-3 py-3 outline-none text-white bg-transparent uppercase" placeholder="Scan unique S/N..." autocomplete="off" disabled>
                        <button type="submit" id="addBtn" class="bg-emerald-600 hover:bg-emerald-500 text-white px-5 py-3 rounded-r-lg font-bold transition-colors disabled:opacity-50" disabled>
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </form>
                
                <div id="serialStatus" class="mt-3 text-sm font-medium hidden flex items-center">
                    <i id="serialStatusIcon" class="mr-2"></i>
                    <span id="serialStatusMsg"></span>
                </div>
                
                <div class="mt-3 text-xs text-slate-400">
                    Session Count: <strong id="sessionCount" class="text-white">0</strong> units added.
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 flex flex-col md:flex-row justify-between items-center gap-4 shrink-0 shadow-sm">
        <div class="relative w-full md:w-96">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>
            <input type="text" id="catalogSearch" oninput="filterCatalogTable()" class="block w-full pl-10 pr-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none" placeholder="Search catalog...">
        </div>
        <div class="flex items-center space-x-3 w-full md:w-auto">
            <select id="categoryFilter" onchange="filterCatalogTable()" class="border border-slate-300 rounded-lg text-sm py-2 px-3 outline-none focus:border-blue-500 text-slate-600 bg-white">
                <option value="">All Categories</option>
                <?php foreach ($categories_list as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Products Catalog Table -->
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm flex-1">
        <div class="overflow-x-auto h-full">
            <table class="w-full text-left border-collapse relative">
                <thead class="sticky top-0 z-10 shadow-sm">
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                        <th class="px-6 py-4 font-semibold">Product Info</th>
                        <th class="px-6 py-4 font-semibold">Category/Brand</th>
                        <th class="px-6 py-4 font-semibold">Price</th>
                        <th class="px-6 py-4 font-semibold">Stock</th>
                        <th class="px-6 py-4 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm" id="catalogTable">
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                                <i class="fa-solid fa-box-open text-4xl mb-3 text-slate-300"></i>
                                <p>No products in the catalog yet.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr class="hover:bg-slate-50 transition-colors group catalog-row">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-slate-800"><?php echo htmlspecialchars($p['name']); ?></div>
                                    <div class="text-xs text-slate-500 mt-1 flex items-center">
                                        <i class="fa-solid fa-barcode mr-1"></i> <?php echo htmlspecialchars($p['product_code']); ?> 
                                        <span class="mx-2">&bull;</span> EAN: <?php echo htmlspecialchars($p['ean'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-slate-700"><?php echo htmlspecialchars($p['category_name'] ?? 'N/A'); ?></div>
                                    <div class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($p['brand_name'] ?? 'N/A'); ?></div>
                                </td>
                                <td class="px-6 py-4 font-medium text-slate-800">
                                    $<?php echo number_format($p['selling_price'], 2); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($p['in_stock_count'] > 0): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 stock-badge" data-id="<?php echo $p['id']; ?>">
                                            <?php echo $p['in_stock_count']; ?> In Stock
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 stock-badge" data-id="<?php echo $p['id']; ?>">
                                            Out of Stock
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="product_serials.php?id=<?php echo $p['id']; ?>" class="text-slate-400 hover:text-blue-600 transition-colors mx-1 p-2 inline-block" title="Manage Serials">
                                        <i class="fa-solid fa-list-ol"></i>
                                    </a>
                                    <a href="product_edit.php?id=<?php echo $p['id']; ?>" class="text-slate-400 hover:text-amber-600 transition-colors mx-1 p-2 inline-block" title="Edit">
                                        <i class="fa-regular fa-pen-to-square"></i>
                                    </a>
                                    <form method="POST" action="products.php" class="inline-block m-0 p-0" onsubmit="return confirm('Are you sure you want to permanently delete this product?');">
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" class="text-slate-400 hover:text-red-600 transition-colors mx-1 p-2" title="Delete">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Client side search filtering
    document.getElementById('catalogSearch').addEventListener('input', function(e) {
        let text = e.target.value.toLowerCase();
        document.querySelectorAll('.catalog-row').forEach(function(row) {
            row.style.display = row.innerText.toLowerCase().includes(text) ? '' : 'none';
        });
    });

    // Audio feedback
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    function playBeep(success) {
        if (!audioCtx) return;
        const oscillator = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();
        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);
        if (success) {
            oscillator.type = 'sine';
            oscillator.frequency.value = 800;
            gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + 0.1);
        } else {
            oscillator.type = 'sawtooth';
            oscillator.frequency.value = 300;
            gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + 0.3);
        }
    }

    let sessionCount = 0;

    async function lookupProduct(e) {
        e.preventDefault();
        const input = document.getElementById('productBarcode');
        const code = input.value.trim();
        const errBox = document.getElementById('productError');
        
        if (!code) return;
        
        input.disabled = true;
        
        try {
            let formData = new FormData();
            formData.append('action', 'lookup_product');
            formData.append('code', code);
            
            let response = await fetch('products.php', { method: 'POST', body: formData });
            let result = await response.json();
            
            if (result.success) {
                errBox.classList.add('hidden');
                
                document.getElementById('activeProductId').value = result.product.id;
                document.getElementById('activeProductName').textContent = result.product.name;
                document.getElementById('activeProductCode').textContent = result.product.product_code;
                document.getElementById('activeProductDisplay').classList.remove('hidden');
                
                input.parentElement.classList.add('hidden');
                
                const step2Box = document.getElementById('step2Box');
                step2Box.classList.remove('opacity-50', 'pointer-events-none');
                step2Box.classList.add('border-emerald-500');
                
                const step2Badge = document.getElementById('step2Badge');
                step2Badge.classList.replace('bg-slate-600', 'bg-emerald-500');
                step2Badge.classList.replace('text-slate-300', 'text-white');
                
                const serialInput = document.getElementById('serialInput');
                serialInput.disabled = false;
                document.getElementById('addBtn').disabled = false;
                
                playBeep(true);
                serialInput.focus();
                
            } else {
                playBeep(false);
                errBox.classList.remove('hidden');
                errBox.querySelector('span').textContent = result.message;
                input.disabled = false;
                input.value = '';
                input.focus();
            }
        } catch(error) {
            playBeep(false);
            errBox.classList.remove('hidden');
            errBox.querySelector('span').textContent = "Network error";
            input.disabled = false;
        }
    }

    function resetProduct() {
        document.getElementById('activeProductDisplay').classList.add('hidden');
        const input = document.getElementById('productBarcode');
        input.parentElement.classList.remove('hidden');
        input.disabled = false;
        input.value = '';
        input.focus();
        
        const step2Box = document.getElementById('step2Box');
        step2Box.classList.add('opacity-50', 'pointer-events-none');
        step2Box.classList.remove('border-emerald-500');
        
        const step2Badge = document.getElementById('step2Badge');
        step2Badge.classList.replace('bg-emerald-500', 'bg-slate-600');
        step2Badge.classList.replace('text-white', 'text-slate-300');
        
        document.getElementById('serialInput').disabled = true;
        document.getElementById('addBtn').disabled = true;
        document.getElementById('serialStatus').classList.add('hidden');
    }

    async function addSerial(e) {
        e.preventDefault();
        const input = document.getElementById('serialInput');
        const serial = input.value.trim();
        const productId = document.getElementById('activeProductId').value;
        const statusBox = document.getElementById('serialStatus');
        const statusIcon = document.getElementById('serialStatusIcon');
        const statusMsg = document.getElementById('serialStatusMsg');
        
        if (!serial) return;
        
        input.disabled = true;
        
        try {
            let formData = new FormData();
            formData.append('action', 'add_serial');
            formData.append('product_id', productId);
            formData.append('serial_number', serial);
            
            let response = await fetch('products.php', { method: 'POST', body: formData });
            let result = await response.json();
            
            statusBox.classList.remove('hidden', 'text-emerald-400', 'text-red-400');
            
            if (result.success) {
                playBeep(true);
                statusBox.classList.add('text-emerald-400');
                statusIcon.className = 'fa-solid fa-check-circle';
                statusMsg.textContent = result.message;
                
                sessionCount++;
                document.getElementById('sessionCount').textContent = sessionCount;
                
                // Update stock badge in table dynamically if visible
                updateTableRowStock(productId);
            } else {
                playBeep(false);
                statusBox.classList.add('text-red-400');
                statusIcon.className = 'fa-solid fa-triangle-exclamation';
                statusMsg.textContent = result.message;
            }
        } catch(error) {
            playBeep(false);
            statusBox.classList.remove('hidden', 'text-emerald-400');
            statusBox.classList.add('text-red-400');
            statusIcon.className = 'fa-solid fa-triangle-exclamation';
            statusMsg.textContent = "Network error";
        }
        
        input.disabled = false;
        input.value = '';
        input.focus();
    }
    
    function updateTableRowStock(productId) {
        const badges = document.querySelectorAll('.stock-badge');
        badges.forEach(badge => {
            if (badge.getAttribute('data-id') === productId) {
                // Parse current count
                let text = badge.textContent;
                let current = parseInt(text.replace(/[^0-9]/g, '')) || 0;
                let next = current + 1;
                
                badge.textContent = next + " In Stock";
                badge.className = "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 stock-badge transition-all scale-110";
                
                setTimeout(() => {
                    badge.classList.remove('scale-110');
                }, 300);
            }
        });
    }

    // Category Modal Logic
    function openCategoryModal() {
        const modal = document.getElementById('categoryModal');
        const content = document.getElementById('categoryModalContent');
        const input = document.getElementById('newCategoryName');
        const msg = document.getElementById('catModalMsg');
        
        input.value = '';
        msg.className = 'mb-4 text-sm hidden p-3 rounded-lg';
        msg.textContent = '';

        loadCategoriesList();

        modal.classList.remove('hidden');
        void modal.offsetWidth;
        modal.classList.add('opacity-100');
        content.classList.remove('scale-95');
        setTimeout(() => input.focus(), 150);
    }

    function closeCategoryModal() {
        const modal = document.getElementById('categoryModal');
        const content = document.getElementById('categoryModalContent');
        modal.classList.remove('opacity-100');
        content.classList.add('scale-95');
        setTimeout(() => modal.classList.add('hidden'), 300);
    }

    async function loadCategoriesList() {
        const container = document.getElementById('categoriesContainer');
        const countBadge = document.getElementById('catCountBadge');
        try {
            let fd = new FormData();
            fd.append('action', 'get_categories');
            let res = await fetch('products.php', { method: 'POST', body: fd });
            let data = await res.json();

            if (data.success) {
                let cats = data.categories;
                countBadge.textContent = `${cats.length} Categories`;
                if (cats.length === 0) {
                    container.innerHTML = `<div class="p-4 text-center text-slate-400 text-sm">No categories found.</div>`;
                    return;
                }
                let html = '<ul class="divide-y divide-slate-100">';
                cats.forEach(c => {
                    let safeName = c.name.replace(/'/g, "\\'").replace(/"/g, "&quot;");
                    html += `
                    <li class="px-4 py-2.5 flex justify-between items-center hover:bg-white transition-colors bg-white/50">
                        <div class="flex items-center">
                            <i class="fa-solid fa-folder text-slate-400 mr-2 text-sm"></i>
                            <span class="font-medium text-slate-800 text-sm">${c.name}</span>
                            <span class="text-xs text-slate-400 ml-2 font-mono">(${c.product_count} products)</span>
                        </div>
                        <button type="button" 
                                onclick="deleteCategory(${c.id}, '${safeName}', ${c.product_count})" 
                                class="text-slate-400 hover:text-red-600 p-1.5 hover:bg-red-50 rounded-md transition-colors" 
                                title="Delete Category">
                            <i class="fa-solid fa-trash-can text-sm"></i>
                        </button>
                    </li>`;
                });
                html += '</ul>';
                container.innerHTML = html;
            } else {
                container.innerHTML = `<div class="p-4 text-center text-red-500 text-sm">Failed to load categories.</div>`;
            }
        } catch(e) {
            container.innerHTML = `<div class="p-4 text-center text-red-500 text-sm">Network error loading categories.</div>`;
        }
    }

    async function deleteCategory(catId, catName, productCount) {
        const msg = document.getElementById('catModalMsg');
        if (productCount > 0) {
            msg.className = 'mb-4 text-sm p-3 rounded-lg bg-red-50 text-red-800 border border-red-200 block';
            msg.textContent = `Cannot delete category "${catName}" because ${productCount} product(s) are assigned to it. Please reassign or remove those products first.`;
            return;
        }

        if (!confirm(`Are you sure you want to delete the category "${catName}"?`)) return;

        try {
            let fd = new FormData();
            fd.append('action', 'delete_category');
            fd.append('category_id', catId);

            let res = await fetch('products.php', { method: 'POST', body: fd });
            let data = await res.json();

            if (data.success) {
                msg.className = 'mb-4 text-sm p-3 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 block';
                msg.textContent = data.message;
                
                // Remove option from filter select
                const filterSelect = document.getElementById('categoryFilter');
                if (filterSelect) {
                    Array.from(filterSelect.options).forEach(opt => {
                        if (opt.value === catName) opt.remove();
                    });
                }
                
                // Reload categories list in modal
                loadCategoriesList();
            } else {
                msg.className = 'mb-4 text-sm p-3 rounded-lg bg-red-50 text-red-800 border border-red-200 block';
                msg.textContent = data.message;
            }
        } catch(e) {
            msg.className = 'mb-4 text-sm p-3 rounded-lg bg-red-50 text-red-800 border border-red-200 block';
            msg.textContent = 'Network error deleting category.';
        }
    }

    async function submitCategory(e) {
        e.preventDefault();
        const input = document.getElementById('newCategoryName');
        const catName = input.value.trim();
        const btn = document.getElementById('saveCatBtn');
        const msg = document.getElementById('catModalMsg');

        if (!catName) return;

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-1"></i> Saving...';

        try {
            let fd = new FormData();
            fd.append('action', 'add_category');
            fd.append('category_name', catName);

            let res = await fetch('products.php', { method: 'POST', body: fd });
            let data = await res.json();

            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-plus mr-1"></i> Add';

            if (data.success) {
                msg.className = 'mb-4 text-sm p-3 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 block';
                msg.textContent = data.message;
                input.value = '';
                
                // Add to filter select dropdown dynamically
                const filterSelect = document.getElementById('categoryFilter');
                if (filterSelect) {
                    const opt = document.createElement('option');
                    opt.value = data.category.name;
                    opt.textContent = data.category.name;
                    filterSelect.appendChild(opt);
                }

                // Refresh categories list in modal
                loadCategoriesList();
            } else {
                msg.className = 'mb-4 text-sm p-3 rounded-lg bg-red-50 text-red-800 border border-red-200 block';
                msg.textContent = data.message;
            }
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-plus mr-1"></i> Add';
            msg.className = 'mb-4 text-sm p-3 rounded-lg bg-red-50 text-red-800 border border-red-200 block';
            msg.textContent = 'Network error saving category.';
        }
    }

    function filterCatalogTable() {
        const query = (document.getElementById('catalogSearch').value || '').toLowerCase();
        const selectedCat = (document.getElementById('categoryFilter').value || '').toLowerCase();
        
        document.querySelectorAll('.catalog-row').forEach(row => {
            const text = row.textContent.toLowerCase();
            const catCell = row.children[1] ? row.children[1].textContent.toLowerCase() : '';
            
            const matchesQuery = !query || text.includes(query);
            const matchesCat = !selectedCat || catCell.includes(selectedCat);
            
            if (matchesQuery && matchesCat) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
</script>

<?php require_once '../includes/footer.php'; ?>
