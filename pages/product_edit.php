<?php
require_once '../includes/db.php';
require_once '../includes/header.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='p-8'><div class='bg-red-50 text-red-600 p-4 rounded-lg'>Invalid Product ID. <a href='products.php' class='underline'>Return to products</a></div></div>";
    require_once '../includes/footer.php';
    exit;
}

$id = (int)$_GET['id'];
$success = false;
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    try {
        $stmt = $pdo->prepare("UPDATE products SET name = ?, category_id = ?, brand_id = ?, product_code = ?, ean = ?, upc = ?, selling_price = ?, min_price = ?, max_price = ?, warranty_months = ?, status = ? WHERE id = ?");
        $stmt->execute([
            $_POST['name'],
            $_POST['category_id'],
            $_POST['brand_id'] !== '' ? $_POST['brand_id'] : null,
            $_POST['product_code'],
            $_POST['ean'],
            $_POST['upc'],
            $_POST['selling_price'],
            $_POST['min_price'],
            $_POST['max_price'],
            $_POST['warranty_months'],
            $_POST['status'],
            $id
        ]);
        $success = true;
    } catch (\PDOException $e) {
        $error = "Failed to update product: " . $e->getMessage();
    }
}

// Fetch current product data
$product = null;
if ($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
}

if (!$product) {
    echo "<div class='p-8'><div class='bg-red-50 text-red-600 p-4 rounded-lg'>Product not found. <a href='products.php' class='underline'>Return to products</a></div></div>";
    require_once '../includes/footer.php';
    exit;
}

// Fetch dropdown data
$categories = [];
$brands = [];
if ($pdo) {
    $categories = $pdo->query("SELECT * FROM categories")->fetchAll();
    $brands = $pdo->query("SELECT * FROM brands")->fetchAll();
}
?>

<div class="max-w-4xl mx-auto pb-8 h-[calc(100vh-100px)] overflow-y-auto">
    <div class="mb-6 flex justify-between items-center mt-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 flex items-center">
                <a href="products.php" class="text-slate-400 hover:text-blue-600 transition-colors mr-3"><i class="fa-solid fa-arrow-left"></i></a>
                Edit Product
            </h1>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg mb-6 flex items-start">
            <i class="fa-solid fa-check-circle mt-1 mr-3 text-emerald-600"></i>
            <div>
                <strong class="font-semibold block">Success!</strong>
                <span class="text-sm">Product has been updated successfully.</span>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 flex items-start">
            <i class="fa-solid fa-triangle-exclamation mt-1 mr-3 text-red-600"></i>
            <div>
                <strong class="font-semibold block">Error</strong>
                <span class="text-sm"><?php echo htmlspecialchars($error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <form method="POST" action="product_edit.php?id=<?php echo $id; ?>" class="space-y-6">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Basic Info -->
                <div class="space-y-4">
                    <h3 class="font-semibold text-slate-800 border-b border-slate-100 pb-2">Basic Information</h3>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Product Name *</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Category *</label>
                        <select name="category_id" required class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php if ($product['category_id'] == $cat['id']) echo 'selected'; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Brand</label>
                        <select name="brand_id" class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                            <option value="">Select Brand</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?php echo $brand['id']; ?>" <?php if ($product['brand_id'] == $brand['id']) echo 'selected'; ?>><?php echo htmlspecialchars($brand['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select name="status" class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                            <option value="Active" <?php if ($product['status'] === 'Active') echo 'selected'; ?>>Active</option>
                            <option value="Discontinued" <?php if ($product['status'] === 'Discontinued') echo 'selected'; ?>>Discontinued</option>
                        </select>
                    </div>
                </div>

                <!-- Codes & Pricing -->
                <div class="space-y-4">
                    <h3 class="font-semibold text-slate-800 border-b border-slate-100 pb-2">Identification & Pricing</h3>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Product Code / Model *</label>
                        <input type="text" name="product_code" value="<?php echo htmlspecialchars($product['product_code']); ?>" required class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none uppercase">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">EAN</label>
                            <input type="text" name="ean" value="<?php echo htmlspecialchars($product['ean']); ?>" class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">UPC</label>
                            <input type="text" name="upc" value="<?php echo htmlspecialchars($product['upc']); ?>" class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Selling Price *</label>
                            <input type="number" step="0.01" name="selling_price" value="<?php echo htmlspecialchars($product['selling_price']); ?>" required class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Warranty (Months)</label>
                            <input type="number" name="warranty_months" value="<?php echo htmlspecialchars($product['warranty_months']); ?>" class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Min Price</label>
                            <input type="number" step="0.01" name="min_price" value="<?php echo htmlspecialchars($product['min_price']); ?>" class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Max Price</label>
                            <input type="number" step="0.01" name="max_price" value="<?php echo htmlspecialchars($product['max_price']); ?>" class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-200 pt-6 flex justify-end gap-3">
                <a href="products.php" class="px-5 py-2.5 border border-slate-300 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">Cancel</a>
                <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors shadow-sm">Update Product</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
