<?php
require_once '../includes/db.php';

// Handle AJAX Search for PC Builder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search_parts') {
    header('Content-Type: application/json');
    $query = trim($_POST['query'] ?? '');
    
    try {
        if ($query !== '') {
            $stmt = $pdo->prepare("SELECT id, name, product_code, selling_price FROM products WHERE name LIKE ? OR product_code LIKE ? LIMIT 20");
            $like = "%$query%";
            $stmt->execute([$like, $like]);
        } else {
            // Default show top 20
            $stmt = $pdo->query("SELECT id, name, product_code, selling_price FROM products LIMIT 20");
        }
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'products' => $products]);
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

require_once '../includes/header.php';
?>

<div class="max-w-6xl mx-auto h-[calc(100vh-100px)] flex flex-col pb-8">
    
    <div class="flex justify-between items-center shrink-0 mb-6 mt-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Custom PC Builder</h1>
            <p class="text-slate-500 text-sm mt-1">Assemble a custom PC and generate a professional quotation.</p>
        </div>
        <div class="flex gap-3">
            <button onclick="clearBuild()" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50 transition-colors text-sm font-medium">Clear Build</button>
            <form action="print_quote.php" method="POST" id="printQuoteForm" target="_blank">
                <input type="hidden" name="customer_name" id="hiddenCustomerName">
                <input type="hidden" name="build_data" id="hiddenBuildData">
                <button type="button" onclick="submitQuote()" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors shadow-sm flex items-center">
                    <i class="fa-solid fa-file-invoice mr-2"></i> Print Quotation
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 flex-1 min-h-0">
        
        <!-- Left Column: Components List -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 flex flex-col h-full">
            <div class="p-4 border-b border-slate-200 bg-slate-50 rounded-t-xl shrink-0">
                <h2 class="font-bold text-slate-800">Select Components</h2>
            </div>
            <div class="flex-1 overflow-y-auto p-2" id="componentsList">
                <!-- Javascript will render component slots here -->
            </div>
        </div>

        <!-- Right Column: Summary & Customer -->
        <div class="bg-slate-900 text-white rounded-xl shadow-lg border border-slate-800 flex flex-col h-full relative overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-blue-600 rounded-bl-full -mr-32 -mt-32 opacity-20 pointer-events-none"></div>
            
            <div class="p-6 shrink-0 relative z-10 border-b border-slate-800">
                <h2 class="font-bold text-xl mb-4">Build Summary</h2>
                
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-400 mb-1 uppercase tracking-wider">Customer Name</label>
                    <input type="text" id="customerName" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 outline-none text-white placeholder-slate-500" placeholder="e.g. John Doe">
                </div>
            </div>

            <div class="flex-1 p-6 relative z-10 flex flex-col justify-end">
                <div class="space-y-3 mb-6">
                    <div class="flex justify-between text-slate-300 text-sm">
                        <span>Total Components</span>
                        <span id="summaryCount">0</span>
                    </div>
                    <div class="flex justify-between font-bold text-2xl text-white pt-3 border-t border-slate-700">
                        <span>Total Price</span>
                        <span id="summaryTotal"><?php echo htmlspecialchars($currency_symbol); ?> 0.00</span>
                    </div>
                </div>
                <button onclick="submitQuote()" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white py-3 rounded-xl font-bold shadow-lg transition-colors flex items-center justify-center">
                    <i class="fa-solid fa-print mr-2"></i> Print Quotation
                </button>
            </div>
        </div>

    </div>
</div>

<!-- Search Modal -->
<div id="searchModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden transform scale-95 transition-transform" id="searchModalInner">
        <div class="p-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800 flex items-center">
                <i class="fa-solid fa-magnifying-glass mr-2 text-blue-600"></i> Select <span id="modalCategoryName" class="ml-1 text-blue-600">Part</span>
            </h3>
            <button onclick="closeSearchModal()" class="text-slate-400 hover:text-red-500 transition-colors p-1"><i class="fa-solid fa-times text-xl"></i></button>
        </div>
        
        <div class="p-4 border-b border-slate-200 shrink-0">
            <input type="text" id="partSearchInput" oninput="searchParts()" class="w-full border border-slate-300 rounded-lg px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="Search product name or code..." autofocus>
        </div>
        
        <div class="flex-1 overflow-y-auto p-2" id="searchResults">
            <!-- Search results rendered here -->
            <div class="text-center py-10 text-slate-400 text-sm">Type to search...</div>
        </div>
    </div>
</div>

<script>
    const componentSlots = [
        { id: 'cpu', name: 'Processor', icon: 'fa-microchip', color: 'text-blue-500' },
        { id: 'mb', name: 'Motherboard', icon: 'fa-chess-board', color: 'text-purple-500' },
        { id: 'ram', name: 'Memory (RAM)', icon: 'fa-memory', color: 'text-emerald-500' },
        { id: 'gpu', name: 'Graphics Card', icon: 'fa-gamepad', color: 'text-red-500' },
        { id: 'storage1', name: 'Primary Storage', icon: 'fa-hard-drive', color: 'text-amber-500' },
        { id: 'storage2', name: 'Secondary Storage', icon: 'fa-hard-drive', color: 'text-amber-500' },
        { id: 'psu', name: 'Power Supply', icon: 'fa-plug', color: 'text-slate-600' },
        { id: 'case', name: 'Casing', icon: 'fa-box', color: 'text-slate-800' },
        { id: 'cooler', name: 'Cooler', icon: 'fa-fan', color: 'text-cyan-500' }
    ];

    let currentBuild = {};
    let activeSlotId = null;

    function initBuilder() {
        const list = document.getElementById('componentsList');
        list.innerHTML = '';
        
        componentSlots.forEach(slot => {
            currentBuild[slot.id] = null;
            
            const div = document.createElement('div');
            div.className = 'p-3 hover:bg-slate-50 border-b border-slate-100 transition-colors group';
            div.id = `slot-${slot.id}`;
            div.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4 flex-1">
                        <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center shrink-0 group-hover:bg-white group-hover:shadow-sm transition-all border border-transparent group-hover:border-slate-200">
                            <i class="fa-solid ${slot.icon} ${slot.color} text-lg"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-0.5">${slot.name}</div>
                            <div class="text-sm font-medium text-slate-800 truncate" id="name-${slot.id}">Not Selected</div>
                            <div class="text-xs text-slate-500 hidden" id="code-${slot.id}"></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 shrink-0">
                        <div class="font-bold text-slate-800 hidden" id="price-${slot.id}"><?php echo htmlspecialchars($currency_symbol); ?> 0.00</div>
                        <button onclick="openSearchModal('${slot.id}', '${slot.name}')" class="bg-slate-100 text-blue-600 hover:bg-blue-600 hover:text-white px-3 py-1.5 rounded text-xs font-bold transition-colors">
                            Select
                        </button>
                        <button onclick="removePart('${slot.id}')" class="text-slate-300 hover:text-red-500 p-2 hidden" id="remove-${slot.id}">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            list.appendChild(div);
        });
        
        updateSummary();
    }

    function openSearchModal(slotId, slotName) {
        activeSlotId = slotId;
        document.getElementById('modalCategoryName').textContent = slotName;
        const modal = document.getElementById('searchModal');
        const inner = document.getElementById('searchModalInner');
        modal.classList.remove('hidden');
        setTimeout(() => {
            inner.classList.remove('scale-95');
            inner.classList.add('scale-100');
            document.getElementById('partSearchInput').focus();
            searchParts(); // Trigger initial load
        }, 10);
    }

    function closeSearchModal() {
        const modal = document.getElementById('searchModal');
        const inner = document.getElementById('searchModalInner');
        inner.classList.remove('scale-100');
        inner.classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            document.getElementById('partSearchInput').value = '';
            document.getElementById('searchResults').innerHTML = '';
        }, 200);
    }

    async function searchParts() {
        const query = document.getElementById('partSearchInput').value;
        const resultsDiv = document.getElementById('searchResults');
        
        let formData = new FormData();
        formData.append('action', 'search_parts');
        formData.append('query', query);
        
        try {
            let res = await fetch('build_pc.php', { method: 'POST', body: formData });
            let data = await res.json();
            
            if (data.success) {
                resultsDiv.innerHTML = '';
                if (data.products.length === 0) {
                    resultsDiv.innerHTML = '<div class="text-center py-10 text-slate-400 text-sm">No products found.</div>';
                    return;
                }
                
                data.products.forEach(p => {
                    const price = parseFloat(p.selling_price).toFixed(2);
                    const div = document.createElement('div');
                    div.className = 'flex items-center justify-between p-3 border-b border-slate-100 hover:bg-blue-50 cursor-pointer rounded-lg transition-colors';
                    div.onclick = () => selectPart(p);
                    div.innerHTML = `
                        <div class="flex-1 min-w-0 pr-4">
                            <div class="font-bold text-slate-800 text-sm truncate">${p.name}</div>
                            <div class="text-xs text-slate-500 font-mono mt-0.5">${p.product_code}</div>
                        </div>
                        <div class="font-bold text-emerald-600 text-sm">
                            $${price}
                        </div>
                    `;
                    resultsDiv.appendChild(div);
                });
            }
        } catch (e) {}
    }

    function selectPart(product) {
        currentBuild[activeSlotId] = {
            id: product.id,
            name: product.name,
            code: product.product_code,
            price: parseFloat(product.selling_price)
        };
        
        const slotEl = document.getElementById(`slot-${activeSlotId}`);
        slotEl.classList.add('bg-blue-50/50');
        
        document.getElementById(`name-${activeSlotId}`).textContent = product.name;
        
        const codeEl = document.getElementById(`code-${activeSlotId}`);
        codeEl.textContent = product.product_code;
        codeEl.classList.remove('hidden');
        
        const priceEl = document.getElementById(`price-${activeSlotId}`);
        priceEl.textContent = (window.CURRENCY_SYMBOL || "Rs.") + " " + currentBuild[activeSlotId].price.toFixed(2);
        priceEl.classList.remove('hidden');
        
        const btn = slotEl.querySelector('button');
        btn.textContent = 'Change';
        btn.classList.replace('bg-slate-100', 'bg-blue-100');
        
        document.getElementById(`remove-${activeSlotId}`).classList.remove('hidden');
        
        closeSearchModal();
        updateSummary();
    }

    function removePart(slotId) {
        currentBuild[slotId] = null;
        
        const slotEl = document.getElementById(`slot-${slotId}`);
        slotEl.classList.remove('bg-blue-50/50');
        
        document.getElementById(`name-${slotId}`).textContent = 'Not Selected';
        document.getElementById(`code-${slotId}`).classList.add('hidden');
        document.getElementById(`price-${slotId}`).classList.add('hidden');
        
        const btn = slotEl.querySelector('button');
        btn.textContent = 'Select';
        btn.classList.replace('bg-blue-100', 'bg-slate-100');
        
        document.getElementById(`remove-${slotId}`).classList.add('hidden');
        
        updateSummary();
    }

    function clearBuild() {
        if(confirm('Are you sure you want to clear the current build?')) {
            initBuilder();
            document.getElementById('customerName').value = '';
        }
    }

    function updateSummary() {
        let total = 0;
        let count = 0;
        
        for (let slot in currentBuild) {
            if (currentBuild[slot]) {
                total += currentBuild[slot].price;
                count++;
            }
        }
        
        document.getElementById('summaryCount').textContent = count;
        document.getElementById('summaryTotal').textContent = (window.CURRENCY_SYMBOL || "Rs.") + " " + total.toFixed(2);
    }

    function submitQuote() {
        let count = 0;
        for (let slot in currentBuild) {
            if (currentBuild[slot]) count++;
        }
        if (count === 0) {
            alert('Please select at least one component to generate a quotation.');
            return;
        }
        
        document.getElementById('hiddenCustomerName').value = document.getElementById('customerName').value;
        document.getElementById('hiddenBuildData').value = JSON.stringify(currentBuild);
        
        document.getElementById('printQuoteForm').submit();
    }

    // Initialize
    initBuilder();
</script>

<?php require_once '../includes/footer.php'; ?>
