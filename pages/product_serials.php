<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
enforce_page_access('product_serials.php');

$product_id = $_GET['id'] ?? 0;
$product = null;
$serials = [];

if ($pdo && $product_id) {
    // Fetch product details
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if ($product) {
        // Handle AJAX requests (MUST BE BEFORE ANY HTML OUTPUT)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            
            // Action 1: Check Serial
            if (isset($_POST['action']) && $_POST['action'] === 'check_serial') {
                $check_serial = trim($_POST['check_serial']);
                $response = ['success' => false, 'message' => '', 'data' => null];
                
                if (empty($check_serial)) {
                    $response['message'] = 'Serial number cannot be empty.';
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM product_serials WHERE serial_number = ? AND product_id = ?");
                    $stmt->execute([$check_serial, $product_id]);
                    $serialData = $stmt->fetch();
                    
                    if ($serialData) {
                        $response['success'] = true;
                        $response['data'] = $serialData;
                        $response['message'] = "Found! Status: " . htmlspecialchars(ucfirst(str_replace('_', ' ', $serialData['status'])));
                    } else {
                        $response['message'] = "Serial '$check_serial' not found in this product's inventory.";
                    }
                }
                echo json_encode($response);
                exit;
            }
            
            // Action 2: Add Serial
            if (isset($_POST['serial_number'])) {
                $serial_number = trim($_POST['serial_number']);
                $response = ['success' => false, 'message' => ''];
                
                if ($serial_number === '' || strlen($serial_number) > 100) {
                    $response['message'] = 'Serial number must contain 1–100 characters.';
                } else {
                    try {
                        // Check if serial already exists
                        $check = $pdo->prepare("SELECT id FROM product_serials WHERE serial_number = ?");
                        $check->execute([$serial_number]);
                        if ($check->fetch()) {
                            $response['message'] = "Serial Number '$serial_number' already exists!";
                        } else {
                            // Insert new serial
                            $insert = $pdo->prepare("INSERT INTO product_serials (product_id, serial_number, status) VALUES (?, ?, 'in_stock')");
                            $insert->execute([$product_id, $serial_number]);
                            $response['success'] = true;
                            $response['message'] = "Added successfully.";
                            $response['new_id'] = $pdo->lastInsertId();
                        }
                    } catch (\PDOException $e) {
                        $response['message'] = 'Database Error: ' . safe_error_message($e);
                    }
                }
                echo json_encode($response);
                exit;
            }
            
            // Action 3: Delete Serial
            if (isset($_POST['action']) && $_POST['action'] === 'delete_serial') {
                $serial_id = (int)$_POST['serial_id'];
                $response = ['success' => false, 'message' => ''];
                try {
                    $stmt = $pdo->prepare("DELETE FROM product_serials WHERE id = ? AND product_id = ? AND status = 'in_stock'");
                    $stmt->execute([$serial_id, $product_id]);
                    $response['success'] = $stmt->rowCount() === 1;
                    if (!$response['success']) {
                        $response['message'] = 'Only an in-stock serial belonging to this product can be deleted.';
                    }
                } catch (\PDOException $e) {
                    $response['message'] = 'Cannot delete. It may be linked to a sale.';
                }
                echo json_encode($response);
                exit;
            }
        }

        // Fetch existing serials for this product
        $s_stmt = $pdo->prepare("SELECT * FROM product_serials WHERE product_id = ? ORDER BY id DESC");
        $s_stmt->execute([$product_id]);
        $serials = $s_stmt->fetchAll();
    }
}

require_once '../includes/header.php';

// Redirect if product not found and not an AJAX request
if (!$product && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<div class='p-8'><div class='bg-red-50 text-red-600 p-4 rounded-lg'>Product not found. <a href='products.php' class='underline'>Go back</a></div></div>";
    require_once '../includes/footer.php';
    exit;
}
?>

<div class="max-w-6xl mx-auto flex flex-col h-[calc(100vh-140px)]">
    <!-- Header -->
    <div class="mb-6 flex justify-between items-end shrink-0">
        <div>
            <div class="flex items-center text-sm text-blue-600 mb-1 font-medium">
                <a href="products.php" class="hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Products</a>
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Manage Serial Numbers</h1>
            <p class="text-slate-500 text-sm mt-1">
                Scan or enter unique physical serials for: 
                <strong class="text-slate-800"><?php echo htmlspecialchars($product['name']); ?></strong> 
                (Code: <?php echo htmlspecialchars($product['product_code']); ?>)
            </p>
        </div>
        
        <!-- Counters -->
        <div class="flex space-x-4">
            <div class="bg-blue-50 border border-blue-200 text-blue-800 px-5 py-2 rounded-lg text-center shadow-sm">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-blue-600">Total Units In Stock</span>
                <span class="text-2xl font-black" id="totalStockCounter">
                    <?php 
                        $inStock = array_filter($serials, fn($s) => $s['status'] === 'in_stock');
                        echo count($inStock); 
                    ?>
                </span>
            </div>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-5 py-2 rounded-lg text-center shadow-sm">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-emerald-600">Added This Session</span>
                <span class="text-2xl font-black" id="sessionCounter">0</span>
            </div>
        </div>
    </div>

    <div class="flex flex-col md:flex-row gap-6 flex-1 min-h-0">
        <!-- Left: Actions Area (Add & Check) -->
        <div class="w-full md:w-1/3 flex flex-col gap-6 overflow-y-auto pr-2 pb-4">
            
            <!-- ADD SERIAL WIDGET -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-32 h-32 bg-blue-500 rounded-bl-full -mr-16 -mt-16 opacity-5 pointer-events-none"></div>
                
                <h2 class="font-bold text-slate-800 text-base mb-3 flex items-center">
                    <i class="fa-solid fa-plus-circle text-blue-600 mr-2"></i> Add New Stock
                </h2>
                
                <form id="serialForm" onsubmit="addSerial(event)">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Scan Serial to Add</label>
                    <div class="flex shadow-sm rounded-lg border border-slate-300 focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500 transition-shadow">
                        <div class="px-3 py-2 bg-slate-50 border-r border-slate-300 text-slate-400 flex items-center justify-center rounded-l-lg">
                            <i class="fa-solid fa-barcode"></i>
                        </div>
                        <input type="text" id="serialInput" class="flex-1 w-full px-3 py-2 outline-none text-slate-800 bg-transparent uppercase text-sm font-mono" placeholder="Scan barcode..." autofocus required autocomplete="off">
                        <button type="submit" id="addBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-r-lg font-bold transition-colors flex items-center justify-center text-sm">
                            Add
                        </button>
                    </div>
                </form>

                <div id="statusAlert" class="mt-3 p-2.5 rounded-lg text-sm hidden font-medium flex items-start leading-snug">
                    <i id="statusIcon" class="mt-0.5 mr-2"></i>
                    <span id="statusMessage"></span>
                </div>
            </div>

            <!-- CHECK SERIAL WIDGET -->
            <div class="bg-slate-50 rounded-xl shadow-inner border border-slate-200 p-5">
                <h2 class="font-bold text-slate-700 text-base mb-3 flex items-center">
                    <i class="fa-solid fa-magnifying-glass text-slate-500 mr-2"></i> Check Serial Status
                </h2>
                
                <form id="checkForm" onsubmit="checkSerial(event)">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Scan Serial to Check</label>
                    <div class="flex shadow-sm rounded-lg border border-slate-300 focus-within:ring-2 focus-within:ring-slate-400 focus-within:border-slate-400 transition-shadow bg-white">
                        <div class="px-3 py-2 border-r border-slate-200 text-slate-400 flex items-center justify-center rounded-l-lg">
                            <i class="fa-solid fa-magnifying-glass-barcode"></i>
                        </div>
                        <input type="text" id="checkInput" class="flex-1 w-full px-3 py-2 outline-none text-slate-800 bg-transparent uppercase text-sm font-mono" placeholder="Scan to check..." required autocomplete="off">
                        <button type="submit" id="checkBtn" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-4 py-2 rounded-r-lg font-bold transition-colors flex items-center justify-center text-sm border-l border-slate-300">
                            Check
                        </button>
                    </div>
                </form>

                <div id="checkResult" class="mt-3 p-3 rounded-lg text-sm hidden border">
                    <!-- Results populated by JS -->
                </div>
            </div>
            
            <div class="bg-slate-800 rounded-xl p-4 text-white shadow-sm border border-slate-700">
                 <h3 class="font-bold text-sm mb-1 text-slate-200"><i class="fa-solid fa-circle-info mr-1"></i> Quick Tips</h3>
                 <p class="text-slate-400 text-xs leading-relaxed">
                     Focus the correct input box before scanning. Scanners automatically press Enter, which will submit the form instantly.
                 </p>
            </div>
        </div>

        <!-- Right: Log / List -->
        <div class="w-full md:w-2/3 bg-white rounded-xl shadow-sm border border-slate-200 flex flex-col min-h-0">
            <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 rounded-t-xl flex justify-between items-center shrink-0">
                <h2 class="font-bold text-slate-800">Scanned Units History</h2>
                <span class="bg-slate-200 text-slate-700 text-xs font-bold px-2.5 py-1 rounded-full"><span id="historyCount"><?php echo count($serials); ?></span> Total Records</span>
            </div>
            
            <div class="flex-1 overflow-auto p-0">
                <table class="w-full text-left border-collapse" id="serialsTable">
                    <thead class="sticky top-0 bg-white shadow-sm z-10">
                        <tr class="bg-white text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                            <th class="px-6 py-3 font-semibold">Serial Number (S/N)</th>
                            <th class="px-6 py-3 font-semibold">Status</th>
                            <th class="px-6 py-3 font-semibold">Added Date</th>
                            <th class="px-6 py-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm" id="serialsList">
                        <?php if (empty($serials)): ?>
                            <tr id="emptyRow">
                                <td colspan="4" class="px-6 py-12 text-center text-slate-500">
                                    <i class="fa-solid fa-barcode text-4xl mb-3 text-slate-300"></i>
                                    <p>No serial numbers recorded yet.</p>
                                    <p class="text-xs mt-1">Start scanning to add stock.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($serials as $s): ?>
                                <tr class="hover:bg-slate-50 transition-colors group">
                                    <td class="px-6 py-3 font-mono font-medium text-slate-800">
                                        <?php echo htmlspecialchars($s['serial_number']); ?>
                                    </td>
                                    <td class="px-6 py-3">
                                        <?php if($s['status'] === 'in_stock'): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span> In Stock
                                            </span>
                                        <?php elseif($s['status'] === 'sold'): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                Sold
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800">
                                                <?php echo htmlspecialchars(ucfirst($s['status'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-3 text-slate-500 text-xs">
                                        <?php echo date('M d, Y h:i A', strtotime($s['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <button onclick="deleteSerial(this, <?php echo $s['id']; ?>)" class="text-slate-400 hover:text-red-500 transition-colors p-1 opacity-0 group-hover:opacity-100" title="Delete">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // Audio feedback
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    function playBeep(type) {
        if (!audioCtx) return;
        const oscillator = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();
        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);
        
        if (type === 'success') {
            oscillator.type = 'sine';
            oscillator.frequency.value = 800;
            gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + 0.1);
        } else if (type === 'error') {
            oscillator.type = 'sawtooth';
            oscillator.frequency.value = 300;
            gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + 0.3);
        } else if (type === 'info') {
            oscillator.type = 'sine';
            oscillator.frequency.value = 600;
            gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + 0.1);
            
            setTimeout(() => {
                const osc2 = audioCtx.createOscillator();
                const gain2 = audioCtx.createGain();
                osc2.connect(gain2);
                gain2.connect(audioCtx.destination);
                osc2.type = 'sine';
                osc2.frequency.value = 800;
                gain2.gain.setValueAtTime(0.1, audioCtx.currentTime);
                osc2.start();
                osc2.stop(audioCtx.currentTime + 0.1);
            }, 100);
        }
    }

    // --- ADD SERIAL LOGIC ---
    let sessionAdded = 0;

    async function addSerial(e) {
        e.preventDefault();
        const input = document.getElementById('serialInput');
        const serial = input.value.trim();
        const alertBox = document.getElementById('statusAlert');
        const alertIcon = document.getElementById('statusIcon');
        const alertMsg = document.getElementById('statusMessage');
        const btn = document.getElementById('addBtn');
        
        if (!serial) return;
        
        input.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        
        try {
            let formData = new FormData();
            formData.append('serial_number', serial);
            
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            
            alertBox.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-700', 'border', 'border-emerald-200', 'bg-red-50', 'text-red-700', 'border-red-200');
            
            if (result.success) {
                playBeep('success');
                alertBox.classList.add('bg-emerald-50', 'text-emerald-700', 'border', 'border-emerald-200');
                alertIcon.className = 'fa-solid fa-check-circle text-emerald-500';
                alertMsg.innerHTML = `<span class="font-bold">${serial}</span> added.`;
                
                // Update table
                addTableRow(serial, result.new_id);
                
                // Update counters
                let totalCounter = document.getElementById('totalStockCounter');
                totalCounter.textContent = parseInt(totalCounter.textContent) + 1;
                
                sessionAdded++;
                document.getElementById('sessionCounter').textContent = sessionAdded;
                
                let histCounter = document.getElementById('historyCount');
                histCounter.textContent = parseInt(histCounter.textContent) + 1;
                
            } else {
                playBeep('error');
                alertBox.classList.add('bg-red-50', 'text-red-700', 'border', 'border-red-200');
                alertIcon.className = 'fa-solid fa-triangle-exclamation text-red-500';
                alertMsg.textContent = result.message;
            }
            
        } catch (error) {
            playBeep('error');
            alertBox.classList.remove('hidden', 'bg-emerald-50');
            alertBox.classList.add('bg-red-50', 'text-red-700', 'border', 'border-red-200');
            alertIcon.className = 'fa-solid fa-triangle-exclamation';
            alertMsg.textContent = "Network error.";
        }
        
        input.disabled = false;
        btn.innerHTML = 'Add';
        input.value = '';
        input.focus();
    }
    
    function addTableRow(serial, id) {
        const tbody = document.getElementById('serialsList');
        const emptyRow = document.getElementById('emptyRow');
        if (emptyRow) emptyRow.remove();
        
        const now = new Date();
        const dateStr = now.toLocaleDateString('en-US', {month:'short', day:'2-digit', year:'numeric'}) + ' ' + 
                        now.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit'});
        
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition-colors group bg-emerald-50'; 
        tr.innerHTML = `
            <td class="px-6 py-3 font-mono font-medium text-slate-800">${serial}</td>
            <td class="px-6 py-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span> In Stock
                </span>
            </td>
            <td class="px-6 py-3 text-slate-500 text-xs">${dateStr}</td>
            <td class="px-6 py-3 text-right">
                <button onclick="deleteSerial(this, ${id})" class="text-slate-400 hover:text-red-500 transition-colors p-1 opacity-0 group-hover:opacity-100">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </td>
        `;
        tbody.insertBefore(tr, tbody.firstChild);
        setTimeout(() => tr.classList.remove('bg-emerald-50'), 2000);
    }

    // --- CHECK SERIAL LOGIC ---
    async function checkSerial(e) {
        e.preventDefault();
        const input = document.getElementById('checkInput');
        const serial = input.value.trim();
        const resultBox = document.getElementById('checkResult');
        const btn = document.getElementById('checkBtn');
        
        if (!serial) return;
        
        input.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        
        try {
            let formData = new FormData();
            formData.append('action', 'check_serial');
            formData.append('check_serial', serial);
            
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            
            resultBox.classList.remove('hidden', 'bg-blue-50', 'text-blue-800', 'border-blue-200', 'bg-slate-100', 'text-slate-600', 'border-slate-300');
            
            if (result.success) {
                playBeep('info');
                resultBox.classList.add('bg-blue-50', 'text-blue-800', 'border-blue-200');
                
                let badge = '';
                if(result.data.status === 'in_stock') badge = '<span class="bg-emerald-500 text-white px-2 py-0.5 rounded text-xs">In Stock</span>';
                else if(result.data.status === 'sold') badge = '<span class="bg-blue-500 text-white px-2 py-0.5 rounded text-xs">Sold</span>';
                else badge = `<span class="bg-slate-500 text-white px-2 py-0.5 rounded text-xs uppercase">${result.data.status}</span>`;

                resultBox.innerHTML = `
                    <div class="font-bold flex justify-between items-center mb-1">
                        <span class="font-mono text-blue-900">${serial}</span>
                        ${badge}
                    </div>
                    <div class="text-xs text-blue-700 opacity-80">
                        Added: ${new Date(result.data.created_at).toLocaleDateString()}
                    </div>
                `;
            } else {
                playBeep('error');
                resultBox.classList.add('bg-slate-100', 'text-slate-600', 'border-slate-300');
                resultBox.innerHTML = `
                    <div class="flex items-start">
                        <i class="fa-solid fa-circle-xmark text-slate-400 mt-1 mr-2"></i>
                        <div>
                            <div class="font-bold text-slate-700">${serial}</div>
                            <div class="text-xs mt-0.5">Not found in this product's inventory.</div>
                        </div>
                    </div>
                `;
            }
            
        } catch (error) {
            playBeep('error');
            resultBox.classList.remove('hidden');
            resultBox.classList.add('bg-red-50', 'text-red-700', 'border-red-200');
            resultBox.innerHTML = "Network error while checking.";
        }
        
        input.disabled = false;
        btn.innerHTML = 'Check';
        input.value = '';
        input.focus();
    }

    // --- DELETE SERIAL LOGIC ---
    async function deleteSerial(btn, serialId) {
        if (!confirm("Are you sure you want to delete this serial number?")) return;
        
        let formData = new FormData();
        formData.append('action', 'delete_serial');
        formData.append('serial_id', serialId);
        
        try {
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            
            if (result.success) {
                // Determine if it was in stock to decrement the top counter
                let row = btn.closest('tr');
                if (row.innerHTML.includes('In Stock')) {
                    let totalCounter = document.getElementById('totalStockCounter');
                    totalCounter.textContent = Math.max(0, parseInt(totalCounter.textContent) - 1);
                }
                
                row.remove();
                
                let histCounter = document.getElementById('historyCount');
                histCounter.textContent = Math.max(0, parseInt(histCounter.textContent) - 1);
                
                playBeep('success');
            } else {
                playBeep('error');
                alert(result.message);
            }
        } catch (error) {
            playBeep('error');
            alert("Network error while deleting.");
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>
