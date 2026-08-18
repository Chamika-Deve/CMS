<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
enforce_page_access('pos.php');

// Handle authenticated POS AJAX requests.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $respond = static function (array $payload, int $status = 200): void {
        http_response_code($status);
        echo json_encode($payload);
        exit;
    };

    if (!$pdo) {
        $respond(['success' => false, 'message' => 'Database connection is unavailable.'], 503);
    }

    $action = $_POST['action'];

    try {
        if ($action === 'get_serials') {
            $productId = (int)($_POST['product_id'] ?? 0);
            if ($productId < 1) {
                $respond(['success' => false, 'message' => 'Invalid product.'], 422);
            }

            $stmt = $pdo->prepare("SELECT id, serial_number FROM product_serials WHERE product_id = ? AND status = 'in_stock' ORDER BY id");
            $stmt->execute([$productId]);
            $respond(['success' => true, 'serials' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        if ($action === 'verify_serial') {
            $productId = (int)($_POST['product_id'] ?? 0);
            $serialNumber = trim($_POST['serial_number'] ?? '');
            if ($productId < 1 || $serialNumber === '') {
                $respond(['success' => false, 'message' => 'A product and serial number are required.'], 422);
            }

            $stmt = $pdo->prepare("
                SELECT ps.id AS serial_id, ps.serial_number, ps.product_id, ps.status,
                       p.name AS product_name, p.selling_price, p.min_price, p.max_price
                FROM product_serials ps
                JOIN products p ON ps.product_id = p.id
                WHERE ps.product_id = ? AND ps.serial_number = ?
                LIMIT 1
            ");
            $stmt->execute([$productId, $serialNumber]);
            $serial = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$serial) {
                $respond(['success' => false, 'message' => 'That serial number was not found for this product.'], 404);
            }
            if ($serial['status'] !== 'in_stock') {
                $respond(['success' => false, 'message' => 'That serial number is ' . str_replace('_', ' ', $serial['status']) . '.'], 409);
            }

            $respond([
                'success' => true,
                'serial' => [
                    'serial_id' => (int)$serial['serial_id'],
                    'serial_number' => $serial['serial_number'],
                    'product_id' => (int)$serial['product_id'],
                    'product_name' => $serial['product_name'],
                    'selling_price' => (float)$serial['selling_price'],
                    'min_price' => (float)$serial['min_price'],
                    'max_price' => (float)$serial['max_price'],
                ],
            ]);
        }

        if ($action === 'search_serial') {
            $serialNumber = trim($_POST['serial_number'] ?? '');
            if ($serialNumber === '') {
                $respond(['success' => false, 'message' => 'Serial number cannot be empty.'], 422);
            }

            $stmt = $pdo->prepare("
                SELECT ps.id AS serial_id, ps.serial_number, ps.product_id, ps.status,
                       p.name AS product_name, p.selling_price, p.min_price, p.max_price
                FROM product_serials ps
                JOIN products p ON ps.product_id = p.id
                WHERE ps.serial_number = ? AND ps.status = 'in_stock'
                LIMIT 1
            ");
            $stmt->execute([$serialNumber]);
            $serial = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$serial) {
                $respond(['success' => false, 'message' => 'That serial number was not found or is not in stock.'], 404);
            }

            $respond([
                'success' => true,
                'serial' => [
                    'serial_id' => (int)$serial['serial_id'],
                    'serial_number' => $serial['serial_number'],
                    'product_id' => (int)$serial['product_id'],
                    'product_name' => $serial['product_name'],
                    'selling_price' => (float)$serial['selling_price'],
                    'min_price' => (float)$serial['min_price'],
                    'max_price' => (float)$serial['max_price'],
                ],
            ]);
        }

        if ($action === 'process_sale') {
            try {
                $cart = json_decode($_POST['cart'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException('The cart data is invalid.');
            }

            if (!is_array($cart) || $cart === []) {
                throw new InvalidArgumentException('Cart is empty.');
            }

            $paymentMethod = $_POST['payment_method'] ?? '';
            $allowedPaymentMethods = ['Cash', 'Card', 'Bank Transfer'];
            if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
                throw new InvalidArgumentException('Select a valid payment method.');
            }

            $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
            if ($customerId !== null) {
                $customerCheck = $pdo->prepare('SELECT id FROM customers WHERE id = ?');
                $customerCheck->execute([$customerId]);
                if (!$customerCheck->fetchColumn()) {
                    throw new InvalidArgumentException('The selected customer no longer exists.');
                }
            }

            $pdo->beginTransaction();
            $lockedSerials = [];
            $seenSerialIds = [];
            $subtotal = 0.0;
            $lockSerial = $pdo->prepare("
                SELECT ps.id, ps.product_id, ps.serial_number, ps.status,
                       p.name AS product_name, p.selling_price, p.min_price, p.max_price
                FROM product_serials ps
                JOIN products p ON p.id = ps.product_id
                WHERE ps.id = ?
                FOR UPDATE
            ");

            foreach ($cart as $item) {
                if (!is_array($item)) {
                    throw new InvalidArgumentException('The cart contains an invalid item.');
                }

                $productId = (int)($item['product_id'] ?? 0);
                $unitPrice = filter_var($item['price'] ?? null, FILTER_VALIDATE_FLOAT);
                $serials = $item['serials'] ?? null;
                if ($productId < 1 || $unitPrice === false || $unitPrice <= 0 || !is_array($serials) || $serials === []) {
                    throw new InvalidArgumentException('Every cart item must have a valid product, price, and serial number.');
                }

                foreach ($serials as $serialInput) {
                    $serialId = (int)($serialInput['serial_id'] ?? 0);
                    if ($serialId < 1 || isset($seenSerialIds[$serialId])) {
                        throw new InvalidArgumentException('A serial number is invalid or appears more than once in the cart.');
                    }
                    $seenSerialIds[$serialId] = true;

                    $lockSerial->execute([$serialId]);
                    $serial = $lockSerial->fetch(PDO::FETCH_ASSOC);
                    if (!$serial || (int)$serial['product_id'] !== $productId) {
                        throw new InvalidArgumentException('A selected serial number does not belong to its cart product.');
                    }
                    if ($serial['status'] !== 'in_stock') {
                        throw new RuntimeException('Serial ' . $serial['serial_number'] . ' is no longer in stock.');
                    }

                    $minimum = (float)$serial['min_price'];
                    $maximum = (float)$serial['max_price'];
                    if ($minimum > 0 && $unitPrice < $minimum) {
                        throw new InvalidArgumentException($serial['product_name'] . ' cannot be sold below its minimum price.');
                    }
                    if ($maximum > 0 && $unitPrice > $maximum) {
                        throw new InvalidArgumentException($serial['product_name'] . ' cannot be sold above its maximum price.');
                    }

                    $lockedSerials[] = [
                        'id' => $serialId,
                        'product_id' => (int)$serial['product_id'],
                        'unit_price' => (float)$unitPrice,
                    ];
                    $subtotal += (float)$unitPrice;
                }
            }

            $taxStatement = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'tax_rate' LIMIT 1");
            $taxRate = max(0.0, min(100.0, (float)$taxStatement->fetchColumn()));
            $tax = round($subtotal * ($taxRate / 100), 2);
            $totalAmount = round($subtotal + $tax, 2);
            $invoiceNo = 'INV-' . date('Ymd-His') . '-' . random_int(10, 99);

            $saleStatement = $pdo->prepare("INSERT INTO sales (customer_id, invoice_no, sale_date, total_amount, tax, payment_method, status) VALUES (?, ?, NOW(), ?, ?, ?, 'Completed')");
            $saleStatement->execute([$customerId, $invoiceNo, $totalAmount, $tax, $paymentMethod]);
            $saleId = (int)$pdo->lastInsertId();

            $itemStatement = $pdo->prepare('INSERT INTO sale_items (sale_id, product_id, product_serial_id, quantity, unit_price) VALUES (?, ?, ?, 1, ?)');
            $serialStatement = $pdo->prepare("UPDATE product_serials SET status = 'sold', sale_id = ? WHERE id = ? AND status = 'in_stock'");
            foreach ($lockedSerials as $serial) {
                $itemStatement->execute([$saleId, $serial['product_id'], $serial['id'], $serial['unit_price']]);
                $serialStatement->execute([$saleId, $serial['id']]);
                if ($serialStatement->rowCount() !== 1) {
                    throw new RuntimeException('Stock changed while the sale was being completed. Please retry.');
                }
            }

            $pdo->commit();
            $respond(['success' => true, 'invoice_no' => $invoiceNo, 'sale_id' => $saleId]);
        }

        $respond(['success' => false, 'message' => 'Unknown POS action.'], 400);
    } catch (InvalidArgumentException | RuntimeException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $respond(['success' => false, 'message' => safe_error_message($exception)], 422);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('POS request failed: ' . $exception->getMessage());
        $respond(['success' => false, 'message' => 'The POS request could not be completed. Please retry.'], 500);
    }
}


require_once '../includes/header.php';

// Fetch Live Products, Customers, and Settings for UI
$products = [];
$customers = [];
$currency_symbol = 'Rs.';
$tax_rate = 0.0;

if ($pdo) {
    try {
        // Fetch Settings
        $stmt_s = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings_map = [];
        while ($s_row = $stmt_s->fetch(PDO::FETCH_ASSOC)) {
            $settings_map[$s_row['setting_key']] = $s_row['setting_value'];
        }
        if (!empty($settings_map['currency_symbol'])) {
            $currency_symbol = $settings_map['currency_symbol'];
        }
        if (isset($settings_map['tax_rate'])) {
            $tax_rate = (float)$settings_map['tax_rate'];
        }

        // Fetch products with their in-stock count
        $stmt = $pdo->query("
            SELECT p.id, p.product_code, p.name, p.selling_price, p.min_price, p.max_price, p.ean, p.upc, c.name as category_name,
                   (SELECT COUNT(*) FROM product_serials WHERE product_id = p.id AND status = 'in_stock') as stock
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'Active'
            HAVING stock > 0
            ORDER BY p.name ASC
        ");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $c_stmt = $pdo->query("SELECT id, name, phone FROM customers ORDER BY name ASC");
        $customers = $c_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        $db_error = safe_error_message($e);
    }
}
?>

<!-- Serial Selection Modal -->
<div id="serialModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex justify-center items-center opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform scale-95 transition-transform duration-300" id="serialModalContent">
        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800 flex items-center">
                <i class="fa-solid fa-barcode text-blue-600 mr-2"></i> Select Serial Number
            </h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <div class="p-6">
            <div class="mb-4 text-sm text-slate-600">
                Product: <strong id="modalProductName" class="text-slate-900"></strong>
            </div>
            
            <div class="relative mb-4">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-solid fa-search"></i>
                </div>
                <input type="text" id="modalSearch" oninput="filterSerials()" onkeypress="handleModalSearchKeypress(event)" class="block w-full pl-10 pr-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none uppercase" placeholder="Scan or type serial & press Enter...">
            </div>

            <div id="modalLoading" class="text-center py-8 hidden">
                <i class="fa-solid fa-circle-notch fa-spin text-blue-600 text-2xl"></i>
                <p class="text-sm text-slate-500 mt-2">Fetching inventory...</p>
            </div>

            <ul id="modalSerialList" class="max-h-60 overflow-y-auto divide-y divide-slate-100 border border-slate-200 rounded-lg">
                <!-- Injected via JS -->
            </ul>
        </div>
    </div>
</div>

<div class="mb-4 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Point of Sale (POS)</h1>
        <p class="text-sm text-slate-500">Select physical serial numbers to process sales.</p>
    </div>
</div>

<div class="flex flex-col lg:flex-row gap-6 h-[calc(100vh-140px)]">
    <!-- Left Panel: Products List -->
    <div class="w-full lg:w-7/12 flex flex-col h-full bg-white rounded-xl shadow-sm border border-slate-200">
        <div class="p-4 border-b border-slate-200">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-solid fa-barcode"></i>
                </div>
                <input type="text" id="posSearch" oninput="filterCatalog()" onkeypress="handlePosSearchEnter(event)" class="block w-full pl-10 pr-3 py-3 border border-slate-300 rounded-xl bg-slate-50 focus:bg-white focus:ring-blue-500 focus:border-blue-500 outline-none transition-colors" placeholder="Scan Barcode / Search Product (Press Ctrl)" autofocus>
            </div>
        </div>
        
        <div class="flex-1 p-4 overflow-y-auto bg-slate-50">
            <?php if (empty($products)): ?>
                <div class="flex flex-col items-center justify-center h-full text-slate-400">
                    <i class="fa-solid fa-box-open text-5xl mb-4 text-slate-200"></i>
                    <p>No products in stock.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4" id="catalogGrid">
                    <?php foreach ($products as $p): ?>
                    <div class="catalog-item border border-slate-200 rounded-xl p-3 hover:border-blue-500 hover:shadow-md cursor-pointer transition-all group bg-white relative overflow-hidden" 
                         data-search="<?php echo strtolower(htmlspecialchars($p['name'] . ' ' . $p['product_code'] . ' ' . $p['ean'] . ' ' . $p['upc'])); ?>"
                         onclick="openSerialModal(<?php echo $p['id']; ?>, '<?php echo addslashes(htmlspecialchars($p['name'])); ?>', <?php echo $p['selling_price']; ?>, <?php echo (float)$p['min_price']; ?>, <?php echo (float)$p['max_price']; ?>)">
                        
                        <div class="h-20 bg-slate-50 rounded-lg mb-3 flex items-center justify-center text-slate-300 group-hover:bg-blue-50 group-hover:text-blue-400 transition-colors">
                            <i class="fa-solid fa-laptop text-3xl"></i>
                        </div>
                        <div class="text-xs text-slate-400 font-mono mb-1"><?php echo htmlspecialchars($p['product_code']); ?></div>
                        <div class="font-bold text-slate-700 text-sm leading-tight mb-3 line-clamp-2" title="<?php echo htmlspecialchars($p['name']); ?>"><?php echo htmlspecialchars($p['name']); ?></div>
                        
                        <div class="flex justify-between items-center mt-auto">
                            <div class="font-black text-slate-900"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($p['selling_price'], 2); ?></div>
                            <div class="text-xs font-bold text-emerald-700 bg-emerald-100 px-2 py-1 rounded-md"><?php echo $p['stock']; ?> in stock</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Panel: Cart & Checkout -->
    <div class="w-full lg:w-5/12 flex flex-col h-full bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden relative">
        <!-- Overlay loader for checkout -->
        <div id="checkoutLoader" class="absolute inset-0 bg-white/80 backdrop-blur-sm z-40 hidden flex-col justify-center items-center">
            <i class="fa-solid fa-spinner fa-spin text-4xl text-blue-600 mb-4"></i>
            <p class="font-bold text-slate-700">Processing Sale...</p>
        </div>

        <div class="p-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center shrink-0">
            <h2 class="font-bold text-slate-800 flex items-center">
                <i class="fa-solid fa-cart-shopping mr-2 text-blue-600"></i> Current Sale
            </h2>
            <button class="text-red-500 hover:text-red-700 text-sm font-bold transition-colors bg-red-50 hover:bg-red-100 px-3 py-1 rounded-md" onclick="clearCart()">
                <i class="fa-solid fa-trash-can mr-1"></i> Clear
            </button>
        </div>
        
        <!-- Cart Items -->
        <div class="flex-1 overflow-y-auto p-0 bg-white" id="cartContainer">
            <div id="emptyCart" class="flex flex-col items-center justify-center h-full text-slate-400">
                <i class="fa-solid fa-cart-arrow-down text-5xl mb-4 text-slate-200"></i>
                <p>Cart is empty</p>
                <p class="text-sm mt-1">Select products to choose serials</p>
            </div>
            <ul id="cartList" class="divide-y divide-slate-100 hidden">
                <!-- Items injected via JS -->
            </ul>
        </div>
        
        <!-- Totals & Checkout -->
        <div class="border-t border-slate-200 bg-slate-50 shrink-0">
            <div class="p-4 space-y-1.5 bg-white">
                <div class="flex justify-between text-sm text-slate-500">
                    <span>Subtotal</span>
                    <span id="cartSubtotal" class="font-medium text-slate-700"><?php echo htmlspecialchars($currency_symbol); ?> 0.00</span>
                </div>
                <div class="flex justify-between text-sm text-slate-500">
                    <span>Tax (<?php echo (float)$tax_rate; ?>%)</span>
                    <span id="cartTax" class="font-medium text-slate-700"><?php echo htmlspecialchars($currency_symbol); ?> 0.00</span>
                </div>
                <div class="flex justify-between text-2xl font-black text-slate-900 pt-3 border-t border-slate-100 mt-2">
                    <span>Total</span>
                    <span id="cartTotal"><?php echo htmlspecialchars($currency_symbol); ?> 0.00</span>
                </div>
            </div>
            
            <div class="p-4 border-t border-slate-200 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Customer</label>
                    <div class="flex items-center gap-2">
                        <select id="customerSelect" class="flex-1 border border-slate-300 rounded-lg text-sm py-2 px-3 outline-none focus:border-blue-500 text-slate-700 bg-white">
                            <option value="">Walk-in Customer</option>
                            <?php foreach($customers as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name'] . ' (' . $c['phone'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Payment Method</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" class="pay-method-btn border-2 border-blue-600 bg-blue-50 text-blue-700 py-2 rounded-lg text-sm font-bold transition-colors" data-method="Cash">
                            <i class="fa-solid fa-money-bill-1 block text-lg mb-1"></i> Cash
                        </button>
                        <button type="button" class="pay-method-btn border-2 border-slate-200 text-slate-500 hover:border-slate-300 bg-white py-2 rounded-lg text-sm font-bold transition-colors" data-method="Card">
                            <i class="fa-regular fa-credit-card block text-lg mb-1"></i> Card
                        </button>
                        <button type="button" class="pay-method-btn border-2 border-slate-200 text-slate-500 hover:border-slate-300 bg-white py-2 rounded-lg text-sm font-bold transition-colors" data-method="Bank Transfer">
                            <i class="fa-solid fa-building-columns block text-lg mb-1"></i> Bank
                        </button>
                    </div>
                </div>
                <input type="hidden" id="selectedPaymentMethod" value="Cash">
                
                <button onclick="processCheckout()" id="checkoutBtn" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-4 rounded-xl shadow-md transition-colors text-lg flex justify-center items-center disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    Complete Sale <i class="fa-solid fa-check-circle ml-2"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const CURRENCY_SYMBOL = <?php echo json_encode($currency_symbol); ?>;
    const TAX_RATE = <?php echo json_encode($tax_rate); ?>;
    // Global Keyboard Shortcuts (Ctrl = Main Barcode/Search, Alt = SN Add Input for Last Item)
    document.addEventListener('keydown', function(e) {
        // Press Ctrl -> Focus Main Barcode / Product Search
        if (e.key === 'Control' || e.key === 'Ctrl') {
            const posSearch = document.getElementById('posSearch');
            if (posSearch) {
                posSearch.focus();
                posSearch.select();
            }
        }
        // Press Alt -> Focus Direct S/N Input for the latest added product in cart
        if (e.key === 'Alt') {
            e.preventDefault(); // Prevent browser menu focus
            if (typeof cart !== 'undefined' && cart.length > 0) {
                let lastItem = cart[cart.length - 1];
                let snInput = document.getElementById(`directSnInput_${lastItem.product_id}`);
                if (snInput) {
                    snInput.focus();
                    snInput.select();
                    snInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else {
                playSound('error');
            }
        }
    });
    // Audio feedback
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    function playSound(type) {
        if (!audioCtx) return;
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        if (type === 'success') {
            osc.type = 'sine'; osc.frequency.value = 800;
            gain.gain.setValueAtTime(0.1, audioCtx.currentTime);
            osc.start(); osc.stop(audioCtx.currentTime + 0.1);
        } else {
            osc.type = 'sawtooth'; osc.frequency.value = 300;
            gain.gain.setValueAtTime(0.1, audioCtx.currentTime);
            osc.start(); osc.stop(audioCtx.currentTime + 0.3);
        }
    }

    // Payment Method UI
    document.querySelectorAll('.pay-method-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.pay-method-btn').forEach(b => {
                b.classList.remove('border-blue-600', 'bg-blue-50', 'text-blue-700');
                b.classList.add('border-slate-200', 'text-slate-500', 'bg-white');
            });
            this.classList.remove('border-slate-200', 'text-slate-500', 'bg-white');
            this.classList.add('border-blue-600', 'bg-blue-50', 'text-blue-700');
            document.getElementById('selectedPaymentMethod').value = this.dataset.method;
        });
    });

    // Search Filter
    function filterCatalog() {
        const query = document.getElementById('posSearch').value.toLowerCase();
        document.querySelectorAll('.catalog-item').forEach(item => {
            if (item.dataset.search.includes(query)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }

    // Auto-select product on Enter if only one matches, OR search serial number directly
    async function handlePosSearchEnter(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const query = document.getElementById('posSearch').value.trim();
            if (!query) return;

            const visibleItems = document.querySelectorAll('.catalog-item:not([style*="display: none"])');
            if (visibleItems.length === 1) {
                visibleItems[0].click();
                document.getElementById('posSearch').value = '';
                filterCatalog();
                return;
            }

            // Attempt direct SN lookup across inventory
            try {
                let fd = new FormData();
                fd.append('action', 'search_serial');
                fd.append('serial_number', query);

                let res = await fetch('pos.php', { method: 'POST', body: fd });
                let data = await res.json();

                if (data.success) {
                    let s = data.serial;
                    let added = addSerialToCart(s.product_id, s.product_name, s.selling_price, s.min_price, s.max_price, s.serial_id, s.serial_number);
                    if (added) {
                        document.getElementById('posSearch').value = '';
                        filterCatalog();
                    }
                } else if (visibleItems.length === 0) {
                    playSound('error');
                    alert(data.message || "Product or Serial Number not found.");
                }
            } catch(err) {
                console.error(err);
            }
        }
    }

    // Modal Search Keypress (Enter key to select/add scanned serial)
    async function handleModalSearchKeypress(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const query = document.getElementById('modalSearch').value.trim();
            if (!query) return;

            // Search in locally loaded allSerials first
            const exactLocalMatch = allSerials.find(s => s.serial_number.toLowerCase() === query.toLowerCase());
            if (exactLocalMatch) {
                const inCart = cart.some(item => item.serials.some(s => s.serial_id === exactLocalMatch.id));
                if (inCart) {
                    playSound('error');
                    alert(`Serial "${exactLocalMatch.serial_number}" is already in the cart.`);
                } else {
                    selectSerial(exactLocalMatch.id, exactLocalMatch.serial_number);
                }
                return;
            }

            // Otherwise verify via backend for current modal product
            if (currentModalProduct) {
                try {
                    let fd = new FormData();
                    fd.append('action', 'verify_serial');
                    fd.append('product_id', currentModalProduct.productId);
                    fd.append('serial_number', query);

                    let res = await fetch('pos.php', { method: 'POST', body: fd });
                    let data = await res.json();

                    if (data.success) {
                        let s = data.serial;
                        selectSerial(s.serial_id, s.serial_number);
                    } else {
                        playSound('error');
                        alert(data.message || 'Invalid serial number.');
                    }
                } catch(err) {
                    playSound('error');
                    alert('Network error verifying serial number.');
                }
            }
        }
    }

    // Modal Logic
    let currentModalProduct = null;
    let allSerials = [];

    async function openSerialModal(productId, productName, price, minPrice, maxPrice) {
        currentModalProduct = { productId, productName, price, minPrice, maxPrice };
        const modal = document.getElementById('serialModal');
        const content = document.getElementById('serialModalContent');
        const loading = document.getElementById('modalLoading');
        const list = document.getElementById('modalSerialList');
        const search = document.getElementById('modalSearch');
        
        document.getElementById('modalProductName').textContent = productName;
        search.value = '';
        list.innerHTML = '';
        loading.classList.remove('hidden');
        
        modal.classList.remove('hidden');
        // Trigger reflow for animation
        void modal.offsetWidth;
        modal.classList.add('opacity-100');
        content.classList.remove('scale-95');
        
        try {
            let fd = new FormData();
            fd.append('action', 'get_serials');
            fd.append('product_id', productId);
            
            let res = await fetch('pos.php', { method: 'POST', body: fd });
            let data = await res.json();
            
            loading.classList.add('hidden');
            if (data.success) {
                allSerials = data.serials;
                renderSerialsList(allSerials);
                search.focus();
            } else {
                list.innerHTML = `<li class="p-4 text-center text-red-500 font-bold">Failed to load serials</li>`;
            }
        } catch(e) {
            loading.classList.add('hidden');
            list.innerHTML = `<li class="p-4 text-center text-red-500 font-bold">Network error</li>`;
        }
    }
    
    function closeModal() {
        const modal = document.getElementById('serialModal');
        const content = document.getElementById('serialModalContent');
        modal.classList.remove('opacity-100');
        content.classList.add('scale-95');
        setTimeout(() => modal.classList.add('hidden'), 300);
    }
    
    function filterSerials() {
        const query = document.getElementById('modalSearch').value.toLowerCase();
        const filtered = allSerials.filter(s => s.serial_number.toLowerCase().includes(query));
        renderSerialsList(filtered);
    }
    
    function renderSerialsList(serialsArray) {
        const list = document.getElementById('modalSerialList');
        if (serialsArray.length === 0) {
            list.innerHTML = `<li class="p-6 text-center text-slate-500">No matching serial numbers found.</li>`;
            return;
        }
        
        let html = '';
        serialsArray.forEach(s => {
            // Check if already in cart
            const inCart = cart.some(item => item.serials.some(serial => serial.serial_id === s.id));
            if (inCart) {
                html += `
                <li class="px-4 py-3 bg-slate-50 flex justify-between items-center opacity-50 cursor-not-allowed">
                    <span class="font-mono text-slate-500">${s.serial_number}</span>
                    <span class="text-xs bg-slate-200 text-slate-500 px-2 py-1 rounded font-bold">In Cart</span>
                </li>`;
            } else {
                html += `
                <li class="px-4 py-3 hover:bg-blue-50 transition-colors flex justify-between items-center cursor-pointer group"
                    onclick="selectSerial(${s.id}, '${s.serial_number}')">
                    <span class="font-mono font-medium text-slate-700 group-hover:text-blue-700">${s.serial_number}</span>
                    <button class="bg-blue-100 text-blue-700 hover:bg-blue-600 hover:text-white px-3 py-1 rounded text-xs font-bold transition-colors">
                        Select
                    </button>
                </li>`;
            }
        });
        list.innerHTML = html;
    }

    // Cart Logic
    let cart = []; // Array of grouped items: { product_id, name, price, serials: [{cart_id, serial_id, serial_number}] }
    
    function addSerialToCart(productId, productName, price, minPrice, maxPrice, serialId, serialNumber) {
        // Check if serial already exists in cart for any item
        let alreadyInCart = false;
        cart.forEach(item => {
            if (item.serials.some(s => s.serial_id === serialId || s.serial_number.toLowerCase() === serialNumber.toLowerCase())) {
                alreadyInCart = true;
            }
        });

        if (alreadyInCart) {
            playSound('error');
            alert('Serial number "' + serialNumber + '" is already in the cart!');
            return false;
        }

        let existingItem = cart.find(i => i.product_id === productId);
        let newSerialObj = {
            cart_id: Date.now() + Math.random(),
            serial_id: serialId,
            serial_number: serialNumber
        };

        if (existingItem) {
            existingItem.serials.push(newSerialObj);
        } else {
            cart.push({
                product_id: productId,
                name: productName,
                price: price,
                minPrice: minPrice,
                maxPrice: maxPrice,
                serials: [newSerialObj]
            });
        }
        playSound('success');
        renderCart();
        return true;
    }

    function selectSerial(serialId, serialNumber) {
        if (!currentModalProduct) return;
        addSerialToCart(
            currentModalProduct.productId,
            currentModalProduct.productName,
            currentModalProduct.price,
            currentModalProduct.minPrice,
            currentModalProduct.maxPrice,
            serialId,
            serialNumber
        );
        closeModal();
    }

    async function addDirectSerial(productId) {
        const inputEl = document.getElementById(`directSnInput_${productId}`);
        if (!inputEl) return;
        const serialNum = inputEl.value.trim();
        if (!serialNum) {
            inputEl.focus();
            return;
        }

        // Check locally if already in cart
        let isDuplicate = false;
        cart.forEach(item => {
            if (item.serials.some(s => s.serial_number.toLowerCase() === serialNum.toLowerCase())) {
                isDuplicate = true;
            }
        });
        if (isDuplicate) {
            playSound('error');
            alert(`Serial number "${serialNum}" is already added to the cart!`);
            return;
        }

        let cartItem = cart.find(i => i.product_id === productId);
        if (!cartItem) return;

        try {
            let fd = new FormData();
            fd.append('action', 'verify_serial');
            fd.append('product_id', productId);
            fd.append('serial_number', serialNum);

            let res = await fetch('pos.php', { method: 'POST', body: fd });
            let data = await res.json();

            if (data.success) {
                let s = data.serial;
                addSerialToCart(s.product_id, s.product_name, cartItem.price, cartItem.minPrice, cartItem.maxPrice, s.serial_id, s.serial_number);
                inputEl.value = '';
            } else {
                playSound('error');
                alert(data.message || 'Invalid serial number.');
            }
        } catch (e) {
            playSound('error');
            alert('Network error verifying serial number.');
        }
    }

    function handleDirectSnEnter(event, productId) {
        if (event.key === 'Enter') {
            event.preventDefault();
            addDirectSerial(productId);
        }
    }
    
    function removeFromCart(cartId) {
        cart.forEach(item => {
            item.serials = item.serials.filter(s => s.cart_id !== cartId);
        });
        // Remove products that have 0 serials left
        cart = cart.filter(item => item.serials.length > 0);
        renderCart();
    }
    
    function clearCart() {
        if(cart.length > 0 && confirm("Are you sure you want to clear the cart?")) {
            cart = [];
            renderCart();
        }
    }
    
    function renderCart() {
        const list = document.getElementById('cartList');
        const empty = document.getElementById('emptyCart');
        const checkoutBtn = document.getElementById('checkoutBtn');
        
        if (cart.length === 0) {
            list.classList.add('hidden');
            empty.classList.remove('hidden');
            document.getElementById('cartSubtotal').innerText = CURRENCY_SYMBOL + " 0.00";
            document.getElementById('cartTax').innerText = CURRENCY_SYMBOL + " 0.00";
            document.getElementById('cartTotal').innerText = CURRENCY_SYMBOL + " 0.00";
            checkoutBtn.disabled = true;
            return;
        }
        
        list.classList.remove('hidden');
        empty.classList.add('hidden');
        checkoutBtn.disabled = false;
        
        let html = '';
        let subtotal = 0;
        
        cart.forEach(item => {
            let qty = item.serials.length;
            let total = item.price * qty;
            subtotal += total;
            
            let serialsHtml = item.serials.map(s => `
                <div class="flex justify-between items-center text-xs bg-slate-100 px-2 py-1.5 rounded mt-1.5 border border-slate-200 group/serial">
                    <span class="font-mono text-slate-600">S/N: ${s.serial_number}</span>
                    <button class="text-slate-400 hover:text-red-500 p-0.5 transition-colors opacity-50 group-hover/serial:opacity-100" title="Remove" onclick="removeFromCart(${s.cart_id})">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            `).join('');

            let safeName = item.name.replace(/'/g, "\\'").replace(/"/g, "&quot;");

            html += `
            <li class="p-4 hover:bg-slate-50 transition-colors">
                <div class="flex justify-between items-start mb-1">
                    <div class="flex-1 flex items-center flex-wrap gap-1">
                        <span class="font-bold text-slate-800 text-sm leading-tight">${item.name}</span>
                        <span class="inline-flex items-center justify-center bg-blue-100 text-blue-700 text-[10px] font-black px-1.5 py-0.5 rounded">x${qty}</span>
                        <button type="button" 
                                onclick="openSerialModal(${item.product_id}, '${safeName}', ${item.price}, ${item.minPrice}, ${item.maxPrice})" 
                                class="inline-flex items-center justify-center bg-emerald-500 hover:bg-emerald-600 text-white text-[11px] font-bold px-2 py-0.5 rounded-full ml-1 shadow-sm transition-all hover:scale-105 active:scale-95 cursor-pointer" 
                                title="Add another Serial Number (+ S/N)">
                            <i class="fa-solid fa-plus text-[9px] mr-1"></i> Add S/N
                        </button>
                    </div>
                    <span class="font-black text-slate-900 ml-2">${CURRENCY_SYMBOL} ${total.toFixed(2)}</span>
                </div>
                
                <div class="flex items-center mt-2 mb-3 gap-2 bg-slate-100 p-2 rounded-lg border border-slate-200">
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Unit Price:</label>
                    <div class="relative flex-1">
                        <span class="absolute left-2 top-1 text-slate-400 text-xs font-bold">${CURRENCY_SYMBOL}</span>
                        <input type="number" step="0.01" class="w-full pl-7 pr-2 py-1 text-sm font-black text-blue-700 bg-white rounded shadow-sm border border-slate-300 focus:border-blue-500 outline-none transition-colors" value="${item.price}" onchange="updateCartPrice(${item.product_id}, this.value)" min="${item.minPrice}" max="${item.maxPrice}">
                    </div>
                    <div class="text-[9px] text-slate-400 font-bold whitespace-nowrap uppercase">
                        Min: ${CURRENCY_SYMBOL} ${parseFloat(item.minPrice).toFixed(2)}<br>
                        Max: ${CURRENCY_SYMBOL} ${parseFloat(item.maxPrice).toFixed(2)}
                    </div>
                </div>

                <div class="pl-2 border-l-2 border-blue-100 space-y-1">
                    ${serialsHtml}
                </div>

                <!-- Direct S/N Input for Quick Scan/Add -->
                <div class="mt-2.5 flex items-center gap-1.5 bg-slate-50 p-1.5 rounded-lg border border-slate-200">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none text-slate-400 text-xs">
                            <i class="fa-solid fa-barcode"></i>
                        </div>
                        <input type="text" 
                               id="directSnInput_${item.product_id}" 
                               placeholder="Scan / type SN & press Enter (Press Alt)..." 
                               onkeypress="handleDirectSnEnter(event, ${item.product_id})" 
                               class="w-full pl-7 pr-2 py-1 text-xs font-mono font-medium border border-slate-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none uppercase bg-white text-slate-800">
                    </div>
                    <button type="button" 
                            onclick="addDirectSerial(${item.product_id})" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-2.5 py-1 rounded text-xs font-bold transition-colors flex items-center gap-1 shrink-0">
                        <i class="fa-solid fa-plus"></i> Add
                    </button>
                </div>
            </li>`;
        });
        
        list.innerHTML = html;
        
        let taxRateCalc = (typeof TAX_RATE !== 'undefined' ? parseFloat(TAX_RATE) : 0.0) / 100; 
        let tax = subtotal * taxRateCalc;
        let grandTotal = subtotal + tax;
        
        document.getElementById('cartSubtotal').innerText = CURRENCY_SYMBOL + " " + subtotal.toFixed(2);
        document.getElementById('cartTax').innerText = CURRENCY_SYMBOL + " " + tax.toFixed(2);
        document.getElementById('cartTotal').innerText = CURRENCY_SYMBOL + " " + grandTotal.toFixed(2);
    }

    function updateCartPrice(productId, newPrice) {
        let item = cart.find(i => i.product_id === productId);
        if (item) {
            newPrice = parseFloat(newPrice);
            if (isNaN(newPrice)) newPrice = item.price;
            
            let minP = parseFloat(item.minPrice);
            let maxP = parseFloat(item.maxPrice);
            
            if (newPrice < minP && minP > 0) {
                playSound('error');
                alert("Price cannot be less than the Minimum Price: " + CURRENCY_SYMBOL + " " + minP.toFixed(2));
                newPrice = minP;
            } else if (newPrice > maxP && maxP > 0) {
                playSound('error');
                alert("Price cannot be more than the Maximum Price: " + CURRENCY_SYMBOL + " " + maxP.toFixed(2));
                newPrice = maxP;
            } else {
                playSound('success');
            }
            
            item.price = newPrice;
            renderCart();
        }
    }

    // Checkout Logic
    async function processCheckout() {
        if (cart.length === 0) return;
        
        const loader = document.getElementById('checkoutLoader');
        loader.classList.remove('hidden');
        loader.classList.add('flex');
        
        const customerId = document.getElementById('customerSelect').value;
        const paymentMethod = document.getElementById('selectedPaymentMethod').value;
        
        try {
            let fd = new FormData();
            fd.append('action', 'process_sale');
            fd.append('cart', JSON.stringify(cart));
            fd.append('customer_id', customerId);
            fd.append('payment_method', paymentMethod);
            
            let res = await fetch('pos.php', { method: 'POST', body: fd });
            let data = await res.json();
            
            loader.classList.add('hidden');
            loader.classList.remove('flex');
            
            if (data.success) {
                playSound('success');
                // Open bill in new tab
                window.open('print_bill.php?id=' + data.sale_id, '_blank');
                // Reload to refresh stock counts and clear cart
                window.location.reload();
            } else {
                playSound('error');
                alert("Error: " + data.message);
            }
        } catch (e) {
            loader.classList.add('hidden');
            loader.classList.remove('flex');
            playSound('error');
            alert("Network error processing sale.");
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>
