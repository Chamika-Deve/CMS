<?php
require_once '../includes/db.php';
require_once '../includes/header.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    try {
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
        $success = true;
    } catch (\PDOException $e) {
        $error = "Failed to add product: " . safe_error_message($e);
    } catch (Exception $e) {
        $error = safe_error_message($e);
    }
}

$categories = [];
$brands = [];
if ($pdo) {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $brands = $pdo->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="max-w-4xl mx-auto pb-10">
    <div class="mb-6 flex justify-between items-center mt-2">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 flex items-center">
                <a href="products.php" class="text-slate-400 hover:text-emerald-600 transition-colors mr-3"><i class="fa-solid fa-arrow-left"></i></a>
                Add New Product
            </h1>
            <p class="text-xs text-slate-400 mt-0.5">Register a new product model with complete specs and pricing controls</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl mb-6 flex items-start">
            <i class="fa-solid fa-circle-check mt-0.5 mr-3 text-emerald-600"></i>
            <div>
                <strong class="font-semibold block">Success!</strong>
                <span class="text-xs">Product has been added to catalog. <a href="products.php" class="underline font-bold">Return to products list</a></span>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-2xl mb-6 flex items-start">
            <i class="fa-solid fa-triangle-exclamation mt-0.5 mr-3 text-red-600"></i>
            <div>
                <strong class="font-semibold block">Error</strong>
                <span class="text-xs"><?php echo htmlspecialchars($error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-7">
        <form method="POST" action="product_add.php" class="space-y-6">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Basic Info -->
                <div class="space-y-4">
                    <h3 class="font-bold text-slate-800 border-b border-slate-100 pb-2 text-sm uppercase tracking-wider">Basic Information</h3>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Product Name *</label>
                        <input type="text" name="name" required placeholder="e.g. Asus ROG Strix G16 Gaming Laptop" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Category *</label>
                            <select name="category_id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Brand</label>
                            <select name="brand_id" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                                <option value="">Select Brand</option>
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?php echo $brand['id']; ?>"><?php echo htmlspecialchars($brand['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Sub-Category</label>
                            <input type="text" name="sub_category" placeholder="e.g. Gaming Laptops" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Model Number</label>
                            <input type="text" name="model_number" placeholder="e.g. G614JV-AS73" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">SKU / Product Code</label>
                            <input type="text" name="product_code" placeholder="Auto-generated if empty" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-mono text-slate-800">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Barcode (UPC/EAN)</label>
                            <input type="text" name="ean" placeholder="Scan barcode" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-mono text-slate-800">
                        </div>
                    </div>
                </div>

                <!-- Pricing & Inventory -->
                <div class="space-y-4">
                    <h3 class="font-bold text-slate-800 border-b border-slate-100 pb-2 text-sm uppercase tracking-wider">Pricing & Stock Controls</h3>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Cost Price (<?php echo htmlspecialchars($currency_symbol ?? "Rs."); ?>) *</label>
                            <input type="number" step="0.01" name="cost_price" value="0.00" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-bold text-slate-800">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Selling Price (<?php echo htmlspecialchars($currency_symbol ?? "Rs."); ?>) *</label>
                            <input type="number" step="0.01" name="selling_price" value="0.00" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-bold text-emerald-700">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Wholesale Price (<?php echo htmlspecialchars($currency_symbol ?? "Rs."); ?>)</label>
                            <input type="number" step="0.01" name="wholesale_price" value="0.00" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-bold text-slate-800">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Tax Rate (%)</label>
                            <input type="number" step="0.01" name="tax_rate" value="0.00" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-bold text-slate-800">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Reorder Level</label>
                            <input type="number" name="reorder_level" value="5" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-bold text-slate-800">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Shelf Location</label>
                            <input type="text" name="location" value="Aisle 1, Shelf A" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Status</label>
                        <select name="status" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800">
                            <option value="Active">Active</option>
                            <option value="Discontinued">Discontinued</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Technical Specifications -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Technical Specifications & Details</label>
                <textarea name="specifications" rows="3" placeholder="e.g. CPU: Intel i7-13700H, RAM: 16GB DDR5, Storage: 1TB NVMe, GPU: RTX 4060, Screen: 16-inch 165Hz" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs font-mono text-slate-800"></textarea>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                <a href="products.php" class="px-5 py-2.5 rounded-2xl border border-slate-200 text-slate-700 font-bold text-xs sm:text-sm hover:bg-slate-50 transition-colors">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs sm:text-sm transition-all shadow-sm shadow-emerald-500/25">
                    Save Product
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
