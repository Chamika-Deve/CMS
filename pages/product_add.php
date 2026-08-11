<?php
require_once '../includes/db.php';
require_once '../includes/header.php';

// Handle form submission
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    try {
        $stmt = $pdo->prepare("INSERT INTO products (name, category_id, brand_id, product_code, ean, upc, selling_price, min_price, max_price, warranty_months, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['category_id'],
            $_POST['brand_id'],
            $_POST['product_code'],
            $_POST['ean'],
            $_POST['upc'],
            $_POST['selling_price'],
            $_POST['min_price'],
            $_POST['max_price'],
            $_POST['warranty_months'],
            $_POST['status']
        ]);
        $success = true;
    } catch (\PDOException $e) {
        $error = "Failed to add product: " . $e->getMessage();
    }
}

// Fetch dropdown data
$categories = [];
$brands = [];
if ($pdo) {
    $categories = $pdo->query("SELECT * FROM categories")->fetchAll();
    $brands = $pdo->query("SELECT * FROM brands")->fetchAll();
}
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 flex items-center">
                <a href="products.php" class="text-slate-400 hover:text-blue-600 transition-colors mr-3"><i class="fa-solid fa-arrow-left"></i></a>
                Add New Product
            </h1>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg mb-6 flex items-start">
            <i class="fa-solid fa-check-circle mt-1 mr-3 text-emerald-600"></i>
            <div>
                <strong class="font-semibold block">Success!</strong>
                <span class="text-sm">Product has been added to the catalog.</span>
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
        <form method="POST" action="product_add.php" class="space-y-6">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Basic Info -->
                <div class="space-y-4">
                    <h3 class="font-semibold text-slate-800 border-b border-slate-100 pb-2">Basic Information</h3>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Product Name *</label>
                        <input type="text" name="name" required class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none">
                    </div>
                    
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label class="block text-sm font-medium text-slate-700">Category *</label>
                            <a href="products.php" class="text-xs text-blue-600 hover:underline font-bold"><i class="fa-solid fa-plus text-[10px]"></i> Add Category</a>
                        </div>
                        <select name="category_id" required class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Brand</label>
                        <select name="brand_id" class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                            <option value="">Select Brand</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?php echo $brand['id']; ?>"><?php echo htmlspecialchars($brand['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select name="status" class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                            <option value="Active">Active</option>
                            <option value="Discontinued">Discontinued</option>
                        </select>
                    </div>
                </div>

                <!-- Codes & Pricing -->
                <div class="space-y-4">
                    <h3 class="font-semibold text-slate-800 border-b border-slate-100 pb-2">Identification & Pricing</h3>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Product Code / Barcode *</label>
                        <div class="relative">
                            <input type="text" name="product_code" id="productCodeInput" required class="block w-full pl-10 pr-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none uppercase transition-colors" placeholder="Scan barcode here...">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-solid fa-barcode"></i>
                            </div>
                            <div id="barcodeLoader" class="absolute inset-y-0 right-0 pr-3 flex items-center hidden">
                                <i class="fa-solid fa-circle-notch fa-spin text-blue-500"></i>
                            </div>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1">Scanning a standard barcode will automatically fetch product details.</p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">EAN</label>
                            <input type="text" name="ean" class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">UPC</label>
                            <input type="text" name="upc" class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Selling Price *</label>
                            <input type="number" step="0.01" name="selling_price" required class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Warranty (Months)</label>
                            <input type="number" name="warranty_months" value="12" class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Min Price</label>
                            <input type="number" step="0.01" name="min_price" value="0.00" class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Max Price</label>
                            <input type="number" step="0.01" name="max_price" value="0.00" class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-200 pt-6 flex justify-end gap-3">
                <a href="products.php" class="px-5 py-2.5 border border-slate-300 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">Cancel</a>
                <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors shadow-sm">Save Product</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('productCodeInput').addEventListener('change', async function(e) {
        const barcode = e.target.value.trim();
        // Typically EAN-13 or UPC-A are 12-13 digits.
        if (barcode.length < 8) return; 

        const nameInput = document.querySelector('input[name="name"]');
        const loader = document.getElementById('barcodeLoader');
        
        loader.classList.remove('hidden');
        this.classList.add('bg-blue-50');
        
        try {
            // UPCItemDB Trial API (100 requests/day, good for general barcode lookup)
            const response = await fetch(`https://api.upcitemdb.com/prod/trial/lookup?upc=${barcode}`);
            const data = await response.json();
            
            if (data && data.items && data.items.length > 0) {
                const item = data.items[0];
                
                // Set name if empty
                if (!nameInput.value) {
                    nameInput.value = item.title;
                }
                
                // Try to set brand if exists
                if (item.brand) {
                    const brandSelect = document.querySelector('select[name="brand_id"]');
                    const options = Array.from(brandSelect.options);
                    const matchingOption = options.find(opt => opt.text.toLowerCase().includes(item.brand.toLowerCase()));
                    if (matchingOption) {
                        brandSelect.value = matchingOption.value;
                    }
                }
                
                // Set EAN/UPC fields
                const eanInput = document.querySelector('input[name="ean"]');
                const upcInput = document.querySelector('input[name="upc"]');
                if (item.ean && !eanInput.value) eanInput.value = item.ean;
                if (item.upc && !upcInput.value) upcInput.value = item.upc;
                
                // Flash success color
                this.classList.replace('bg-blue-50', 'bg-emerald-50');
                setTimeout(() => this.classList.remove('bg-emerald-50'), 1500);
            } else {
                // If not found, just remove the highlight
                this.classList.remove('bg-blue-50');
            }
        } catch (err) {
            console.error('Barcode lookup failed:', err);
            this.classList.remove('bg-blue-50');
        } finally {
            loader.classList.add('hidden');
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>
