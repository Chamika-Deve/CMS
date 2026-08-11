<?php
require_once '../includes/db.php';
session_start();
if (!isset($_SESSION['user'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    header("Location: ../index.php");
    exit;
}
// Handle AJAX requests for stock entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => ''];
    header('Content-Type: application/json');

    if ($action === 'lookup_product') {
        $code = trim($_POST['code']);
        $stmt = $pdo->prepare("SELECT id, name, product_code FROM products WHERE product_code = ? OR ean = ? OR upc = ?");
        $stmt->execute([$code, $code, $code]);
        $product = $stmt->fetch();

        if ($product) {
            $response['success'] = true;
            $response['product'] = $product;
        } else {
            $response['message'] = 'Product not found. Please check the barcode.';
        }
        echo json_encode($response);
        exit;
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
        echo json_encode($response);
        exit;
    }
}

require_once '../includes/header.php';
?>

<div class="max-w-5xl mx-auto flex flex-col h-[calc(100vh-140px)]">
    <!-- Header -->
    <div class="mb-6 shrink-0">
        <h1 class="text-2xl font-bold text-slate-900">Rapid Stock Entry</h1>
        <p class="text-slate-500 text-sm mt-1">Scan a product barcode once, then rapidly scan all unique serial numbers for that product.</p>
    </div>

    <!-- Top: Step 1 & Step 2 -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 shrink-0 mb-6">
        
        <!-- Step 1: Scan Product -->
        <div class="bg-white rounded-xl shadow-sm border border-blue-200 p-6 relative overflow-hidden transition-all" id="step1Box">
            <div class="absolute top-0 left-0 w-1 h-full bg-blue-600"></div>
            <h2 class="font-bold text-slate-800 text-lg mb-4 flex items-center">
                <span class="bg-blue-100 text-blue-700 w-6 h-6 rounded-full flex items-center justify-center text-xs mr-2">1</span>
                Scan Product Barcode
            </h2>
            
            <form id="productSearchForm" onsubmit="lookupProduct(event)">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-box-open"></i>
                    </div>
                    <input type="text" id="productBarcode" class="block w-full pl-10 pr-3 py-3 border border-slate-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 outline-none uppercase bg-slate-50 focus:bg-white transition-colors" placeholder="Scan Product Code, EAN or UPC..." autofocus required autocomplete="off">
                </div>
            </form>

            <div id="activeProductDisplay" class="mt-4 p-4 bg-emerald-50 border border-emerald-200 rounded-lg hidden">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-xs text-emerald-600 font-bold uppercase tracking-wider mb-1">Active Product</div>
                        <div id="activeProductName" class="font-bold text-slate-800 text-lg">Dell XPS 15</div>
                        <div id="activeProductCode" class="text-slate-500 text-sm font-mono mt-1">LT-DXPS15</div>
                        <input type="hidden" id="activeProductId" value="">
                    </div>
                    <button type="button" onclick="resetProduct()" class="text-slate-400 hover:text-red-500 text-sm underline">Change</button>
                </div>
            </div>
            
            <div id="productError" class="mt-3 text-red-600 text-sm font-medium hidden">
                <i class="fa-solid fa-triangle-exclamation mr-1"></i> <span></span>
            </div>
        </div>

        <!-- Step 2: Scan Serials -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 relative overflow-hidden opacity-50 pointer-events-none transition-all" id="step2Box">
            <h2 class="font-bold text-slate-800 text-lg mb-4 flex items-center">
                <span class="bg-slate-100 text-slate-500 w-6 h-6 rounded-full flex items-center justify-center text-xs mr-2" id="step2Badge">2</span>
                Scan Serial Numbers
            </h2>
            
            <form id="serialForm" onsubmit="addSerial(event)">
                <div class="flex shadow-sm rounded-lg border border-slate-300 focus-within:ring-2 focus-within:ring-emerald-500 focus-within:border-emerald-500 transition-shadow">
                    <div class="px-3 py-3 bg-slate-50 border-r border-slate-300 text-slate-400 flex items-center justify-center rounded-l-lg">
                        <i class="fa-solid fa-barcode"></i>
                    </div>
                    <input type="text" id="serialInput" class="flex-1 w-full px-3 py-3 outline-none text-slate-800 bg-transparent uppercase" placeholder="Scan unique S/N..." autocomplete="off" disabled>
                    <button type="submit" id="addBtn" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-3 rounded-r-lg font-bold transition-colors disabled:opacity-50" disabled>
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
            </form>
            
            <div id="serialStatus" class="mt-3 text-sm font-medium hidden flex items-center">
                <i id="serialStatusIcon" class="mr-2"></i>
                <span id="serialStatusMsg"></span>
            </div>
        </div>
    </div>

    <!-- Bottom: Session Table -->
    <div class="flex-1 bg-white rounded-xl shadow-sm border border-slate-200 flex flex-col min-h-0">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 rounded-t-xl flex justify-between items-center shrink-0">
            <h2 class="font-bold text-slate-800">Scanned in this Session</h2>
            <span class="bg-slate-200 text-slate-700 text-xs font-bold px-2.5 py-1 rounded-full" id="sessionCount">0 Items</span>
        </div>
        
        <div class="flex-1 overflow-auto p-0">
            <table class="w-full text-left border-collapse">
                <thead class="sticky top-0 bg-white shadow-sm z-10">
                    <tr class="bg-white text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                        <th class="px-6 py-3 font-semibold">Product</th>
                        <th class="px-6 py-3 font-semibold">Serial Number (S/N)</th>
                        <th class="px-6 py-3 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm" id="sessionTableBody">
                    <tr id="emptySessionRow">
                        <td colspan="3" class="px-6 py-12 text-center text-slate-500">
                            <i class="fa-solid fa-boxes-packing text-4xl mb-3 text-slate-300"></i>
                            <p>No items added yet.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
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
            
            let response = await fetch('purchases.php', { method: 'POST', body: formData });
            let result = await response.json();
            
            if (result.success) {
                errBox.classList.add('hidden');
                
                // Show product display
                document.getElementById('activeProductId').value = result.product.id;
                document.getElementById('activeProductName').textContent = result.product.name;
                document.getElementById('activeProductCode').textContent = result.product.product_code;
                document.getElementById('activeProductDisplay').classList.remove('hidden');
                
                // Hide input
                input.parentElement.classList.add('hidden');
                
                // Enable Step 2
                const step2Box = document.getElementById('step2Box');
                step2Box.classList.remove('opacity-50', 'pointer-events-none');
                step2Box.classList.add('border-emerald-200');
                
                const step2Badge = document.getElementById('step2Badge');
                step2Badge.classList.replace('bg-slate-100', 'bg-emerald-100');
                step2Badge.classList.replace('text-slate-500', 'text-emerald-700');
                
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
        
        // Disable step 2
        const step2Box = document.getElementById('step2Box');
        step2Box.classList.add('opacity-50', 'pointer-events-none');
        step2Box.classList.remove('border-emerald-200');
        
        const step2Badge = document.getElementById('step2Badge');
        step2Badge.classList.replace('bg-emerald-100', 'bg-slate-100');
        step2Badge.classList.replace('text-emerald-700', 'text-slate-500');
        
        document.getElementById('serialInput').disabled = true;
        document.getElementById('addBtn').disabled = true;
        document.getElementById('serialStatus').classList.add('hidden');
    }

    async function addSerial(e) {
        e.preventDefault();
        const input = document.getElementById('serialInput');
        const serial = input.value.trim();
        const productId = document.getElementById('activeProductId').value;
        const productName = document.getElementById('activeProductName').textContent;
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
            
            let response = await fetch('purchases.php', { method: 'POST', body: formData });
            let result = await response.json();
            
            statusBox.classList.remove('hidden', 'text-emerald-600', 'text-red-600');
            
            if (result.success) {
                playBeep(true);
                statusBox.classList.add('text-emerald-600');
                statusIcon.className = 'fa-solid fa-check-circle';
                statusMsg.textContent = result.message;
                
                addSessionRow(productName, serial);
            } else {
                playBeep(false);
                statusBox.classList.add('text-red-600');
                statusIcon.className = 'fa-solid fa-triangle-exclamation';
                statusMsg.textContent = result.message;
            }
        } catch(error) {
            playBeep(false);
            statusBox.classList.remove('hidden', 'text-emerald-600');
            statusBox.classList.add('text-red-600');
            statusIcon.className = 'fa-solid fa-triangle-exclamation';
            statusMsg.textContent = "Network error";
        }
        
        input.disabled = false;
        input.value = '';
        input.focus();
    }
    
    function addSessionRow(productName, serial) {
        const tbody = document.getElementById('sessionTableBody');
        const emptyRow = document.getElementById('emptySessionRow');
        if (emptyRow) emptyRow.remove();
        
        const tr = document.createElement('tr');
        tr.className = 'bg-emerald-50 transition-colors duration-1000';
        tr.innerHTML = `
            <td class="px-6 py-3 font-medium text-slate-800">${productName}</td>
            <td class="px-6 py-3 font-mono text-slate-600">${serial}</td>
            <td class="px-6 py-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span> Added
                </span>
            </td>
        `;
        
        tbody.insertBefore(tr, tbody.firstChild);
        
        sessionCount++;
        document.getElementById('sessionCount').textContent = sessionCount + " Items";
        
        setTimeout(() => {
            tr.classList.remove('bg-emerald-50');
            tr.classList.add('hover:bg-slate-50');
        }, 1000);
    }
</script>

<?php require_once '../includes/footer.php'; ?>
