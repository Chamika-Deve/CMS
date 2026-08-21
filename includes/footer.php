        </div> <!-- End of Page Content Scrollable Area -->
    </main>

    <!-- Interactive Calculator Modal -->
    <div id="calculatorModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-xs p-6 relative">
            <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm font-bold">
                        <i class="fa-solid fa-calculator"></i>
                    </div>
                    <span class="font-bold text-slate-800 text-sm">POS Calculator</span>
                </div>
                <button onclick="toggleCalculator()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>
            
            <!-- Calculator Display -->
            <div class="bg-slate-50 rounded-2xl p-4 mb-4 border border-slate-200/60 text-right">
                <div id="calcHistory" class="text-slate-400 text-xs font-mono h-4 overflow-hidden"></div>
                <div id="calcDisplay" class="text-2xl font-extrabold text-slate-900 font-mono tracking-tight overflow-x-auto">0</div>
            </div>

            <!-- Calculator Keypad -->
            <div class="grid grid-cols-4 gap-2 text-sm font-bold">
                <button onclick="calcClear()" class="p-3 bg-red-50 text-red-600 rounded-xl hover:bg-red-100 transition-colors">C</button>
                <button onclick="calcBackspace()" class="p-3 bg-slate-100 text-slate-600 rounded-xl hover:bg-slate-200 transition-colors"><i class="fa-solid fa-delete-left"></i></button>
                <button onclick="calcOp('/')" class="p-3 bg-emerald-50 text-emerald-600 rounded-xl hover:bg-emerald-100 transition-colors">÷</button>
                <button onclick="calcOp('*')" class="p-3 bg-emerald-50 text-emerald-600 rounded-xl hover:bg-emerald-100 transition-colors">×</button>

                <button onclick="calcNum('7')" class="p-3 bg-slate-50 text-slate-800 rounded-xl hover:bg-slate-100 transition-colors">7</button>
                <button onclick="calcNum('8')" class="p-3 bg-slate-50 text-slate-800 rounded-xl hover:bg-slate-100 transition-colors">8</button>
                <button onclick="calcNum('9')" class="p-3 bg-slate-50 text-slate-800 rounded-xl hover:bg-slate-100 transition-colors">9</button>
                <button onclick="calcOp('-')" class="p-3 bg-emerald-50 text-emerald-600 rounded-xl hover:bg-emerald-100 transition-colors">−</button>

                <button onclick="calcNum('4')" class="p-3 bg-slate-50 text-slate-800 rounded-xl hover:bg-slate-100 transition-colors">4</button>
                <button onclick="calcNum('5')" class="p-3 bg-slate-50 text-slate-800 rounded-xl hover:bg-slate-100 transition-colors">5</button>
                <button onclick="calcNum('6')" class="p-3 bg-slate-50 text-slate-800 rounded-xl hover:bg-slate-100 transition-colors">6</button>
                <button onclick="calcOp('+')" class="p-3 bg-emerald-50 text-emerald-600 rounded-xl hover:bg-emerald-100 transition-colors">+</button>

                <button onclick="calcNum('1')" class="p-3 bg-slate-50 text-slate-800 rounded-xl hover:bg-slate-100 transition-colors">1</button>
                <button onclick="calcNum('2')" class="p-3 bg-slate-50 text-slate-800 rounded-xl hover:bg-slate-100 transition-colors">2</button>
                <button onclick="calcNum('3')" class="p-3 bg-slate-50 text-slate-800 rounded-xl hover:bg-slate-100 transition-colors">3</button>
                <button onclick="calcEquals()" class="p-3 bg-emerald-500 text-white rounded-xl hover:bg-emerald-600 transition-colors row-span-2 flex items-center justify-center text-lg font-bold shadow-sm shadow-emerald-500/20">=</button>

                <button onclick="calcNum('0')" class="p-3 bg-slate-50 text-slate-800 rounded-xl hover:bg-slate-100 transition-colors col-span-2">0</button>
                <button onclick="calcNum('.')" class="p-3 bg-slate-50 text-slate-800 rounded-xl hover:bg-slate-100 transition-colors">.</button>
            </div>
        </div>
    </div>

    <!-- Interactive Item Details Modal -->
    <div id="itemDetailModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-md p-7 relative">
            <button onclick="closeItemModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
            
            <div class="flex items-center gap-4 mb-6">
                <img id="modalItemImg" src="" alt="Product" class="w-16 h-16 rounded-2xl object-cover border border-slate-100 shadow-sm shrink-0">
                <div>
                    <h3 id="modalItemName" class="text-xl font-bold text-slate-900 tracking-tight"></h3>
                    <p id="modalItemBrand" class="text-xs font-semibold text-slate-400"></p>
                </div>
            </div>

            <div class="space-y-3 bg-slate-50/80 p-4 rounded-2xl border border-slate-100 text-sm mb-6">
                <div class="flex justify-between py-1 border-b border-slate-200/60">
                    <span class="text-slate-500 font-medium">Category</span>
                    <span id="modalItemCat" class="font-bold text-slate-800"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-200/60">
                    <span class="text-slate-500 font-medium">Current Stock</span>
                    <span id="modalItemQty" class="font-bold text-slate-800"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-200/60">
                    <span class="text-slate-500 font-medium">Buying Price</span>
                    <span id="modalItemBuyPrice" class="font-medium text-slate-700"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-200/60">
                    <span class="text-slate-500 font-medium">Selling Price</span>
                    <span id="modalItemSellPrice" class="font-bold text-emerald-600"></span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="text-slate-500 font-medium">Status</span>
                    <span id="modalItemStatus" class="font-bold"></span>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="product_add.php" class="flex-1 py-3 bg-emerald-500 hover:bg-emerald-600 text-white text-center font-bold text-sm rounded-2xl transition-all shadow-sm shadow-emerald-500/20">
                    Restock Item
                </a>
                <button onclick="closeItemModal()" class="px-5 py-3 border border-slate-200 text-slate-700 font-bold text-sm rounded-2xl hover:bg-slate-50 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Help Modal -->
    <div id="helpModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-lg p-7 relative">
            <button onclick="toggleHelpModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                    <i class="fa-solid fa-headset"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900">TechShop & POS Help</h3>
                    <p class="text-xs text-slate-400">Quick guides and shortcuts</p>
                </div>
            </div>
            <div class="space-y-3 text-xs text-slate-600 mb-6">
                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                    <p class="font-bold text-slate-800 mb-1"><i class="fa-solid fa-barcode mr-1.5 text-emerald-500"></i> Barcode & Serial Scanning</p>
                    <p>In Sales POS or Rapid Purchases, directly scan barcodes or serial numbers to instantly add items to the cart or stock.</p>
                </div>
                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                    <p class="font-bold text-slate-800 mb-1"><i class="fa-solid fa-keyboard mr-1.5 text-emerald-500"></i> Hotkeys & Shortcuts</p>
                    <p><kbd class="bg-white border px-1.5 py-0.5 rounded text-[10px] font-mono">F2</kbd> Focus Search | <kbd class="bg-white border px-1.5 py-0.5 rounded text-[10px] font-mono">F8</kbd> Quick Checkout | <kbd class="bg-white border px-1.5 py-0.5 rounded text-[10px] font-mono">ESC</kbd> Close Modals</p>
                </div>
            </div>
            <button onclick="toggleHelpModal()" class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm rounded-2xl transition-colors">
                Got it
            </button>
        </div>
    </div>

    <!-- Privacy Policy Modal -->
    <div id="privacyModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-lg p-7 relative">
            <button onclick="togglePrivacyModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Privacy & Security</h3>
                    <p class="text-xs text-slate-400">Inventory & Customer Data Protection</p>
                </div>
            </div>
            <p class="text-xs text-slate-600 leading-relaxed mb-6">
                Your shop inventory, customer records, invoices, and serial numbers are encrypted and stored locally in accordance with enterprise data protection standards. No customer financial details are stored externally without authorization.
            </p>
            <button onclick="togglePrivacyModal()" class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm rounded-2xl transition-colors">
                Accept & Close
            </button>
        </div>
    </div>

    <!-- Interactive Scripts -->
    <script>
        // Toggle Mobile Sidebar
        function toggleMobileSidebar() {
            const sidebar = document.getElementById('mainSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
            }
        }

        // Toggle Notifications
        function toggleNotifications() {
            const dropdown = document.getElementById('notifDropdown');
            dropdown.classList.toggle('hidden');
        }

        // Toggle User Menu
        function toggleUserMenu() {
            const dropdown = document.getElementById('userMenuDropdown');
            dropdown.classList.toggle('hidden');
        }

        // Toggle Calculator
        function toggleCalculator() {
            const modal = document.getElementById('calculatorModal');
            modal.classList.toggle('hidden');
        }

        // Toggle Help
        function toggleHelpModal() {
            const modal = document.getElementById('helpModal');
            modal.classList.toggle('hidden');
        }

        // Toggle Privacy
        function togglePrivacyModal() {
            const modal = document.getElementById('privacyModal');
            modal.classList.toggle('hidden');
        }

        // Close dropdowns on outside click
        document.addEventListener('click', function(e) {
            const notifBtn = document.getElementById('notifBtn');
            const notifDropdown = document.getElementById('notifDropdown');
            if (notifBtn && notifDropdown && !notifBtn.contains(e.target) && !notifDropdown.contains(e.target)) {
                notifDropdown.classList.add('hidden');
            }

            const userMenuBtn = document.getElementById('userMenuBtn');
            const userMenuDropdown = document.getElementById('userMenuDropdown');
            if (userMenuBtn && userMenuDropdown && !userMenuBtn.contains(e.target) && !userMenuDropdown.contains(e.target)) {
                userMenuDropdown.classList.add('hidden');
            }
        });

        // Theme Toggle (Light / Dark)
        function toggleTheme() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('techshop_theme', isDark ? 'dark' : 'light');
            updateThemeIcon();
        }

        function updateThemeIcon() {
            const icon = document.querySelector('#themeToggleBtn i');
            if (icon) {
                if (document.documentElement.classList.contains('dark')) {
                    icon.className = 'fa-solid fa-sun text-base text-amber-400';
                } else {
                    icon.className = 'fa-regular fa-moon text-base text-slate-600';
                }
            }
        }
        document.addEventListener('DOMContentLoaded', updateThemeIcon);

        // Calculator Logic
        let calcCurrent = '0';
        let calcPrev = null;
        let calcOperator = null;
        let calcResetNext = false;

        function updateCalcDisplay() {
            document.getElementById('calcDisplay').textContent = calcCurrent;
            const history = document.getElementById('calcHistory');
            if (calcPrev !== null && calcOperator) {
                history.textContent = `${calcPrev} ${calcOperator}`;
            } else {
                history.textContent = '';
            }
        }

        function calcNum(num) {
            if (calcCurrent === '0' || calcResetNext) {
                calcCurrent = num;
                calcResetNext = false;
            } else {
                if (num === '.' && calcCurrent.includes('.')) return;
                calcCurrent += num;
            }
            updateCalcDisplay();
        }

        function calcClear() {
            calcCurrent = '0';
            calcPrev = null;
            calcOperator = null;
            calcResetNext = false;
            updateCalcDisplay();
        }

        function calcBackspace() {
            if (calcCurrent.length > 1) {
                calcCurrent = calcCurrent.slice(0, -1);
            } else {
                calcCurrent = '0';
            }
            updateCalcDisplay();
        }

        function calcOp(op) {
            if (calcOperator && !calcResetNext) {
                calcEquals();
            }
            calcPrev = parseFloat(calcCurrent);
            calcOperator = op;
            calcResetNext = true;
            updateCalcDisplay();
        }

        function calcEquals() {
            if (calcPrev === null || !calcOperator) return;
            const curr = parseFloat(calcCurrent);
            let result = 0;
            switch(calcOperator) {
                case '+': result = calcPrev + curr; break;
                case '-': result = calcPrev - curr; break;
                case '*': result = calcPrev * curr; break;
                case '/': result = curr !== 0 ? calcPrev / curr : 'Error'; break;
            }
            calcCurrent = typeof result === 'number' ? parseFloat(result.toFixed(6)).toString() : result;
            calcPrev = null;
            calcOperator = null;
            calcResetNext = true;
            updateCalcDisplay();
        }

        // Item Details Modal
        function openItemModal(item) {
            const imgEl = document.getElementById('modalItemImg');
            if (item.image && item.image.trim() !== '') {
                imgEl.src = item.image;
                imgEl.classList.remove('hidden');
            } else {
                imgEl.classList.add('hidden');
            }
            document.getElementById('modalItemName').textContent = item.name;
            document.getElementById('modalItemBrand').textContent = item.brand;
            document.getElementById('modalItemCat').textContent = item.category;
            document.getElementById('modalItemQty').textContent = item.qty;
            document.getElementById('modalItemBuyPrice').textContent = item.buying_price;
            document.getElementById('modalItemSellPrice').textContent = item.selling_price;
            
            const statusEl = document.getElementById('modalItemStatus');
            statusEl.textContent = item.status;
            statusEl.className = item.status === 'Out of Stock' ? 'font-bold text-red-600' : 'font-bold text-amber-600';

            document.getElementById('itemDetailModal').classList.remove('hidden');
        }

        function closeItemModal() {
            document.getElementById('itemDetailModal').classList.add('hidden');
        }

        // Global Search Filter on Low Stock Table
        function handleGlobalSearch(event) {
            const query = event.target.value.toLowerCase();
            const table = document.getElementById('lowStockTable');
            if (!table) return;
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Table Sorting
        let currentSortCol = -1;
        let sortAsc = true;
        function sortTable(colIndex) {
            const table = document.getElementById('lowStockTable');
            if (!table) return;
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            if (currentSortCol === colIndex) {
                sortAsc = !sortAsc;
            } else {
                currentSortCol = colIndex;
                sortAsc = true;
            }

            rows.sort((a, b) => {
                const aCell = a.children[colIndex].textContent.trim().toLowerCase();
                const bCell = b.children[colIndex].textContent.trim().toLowerCase();
                
                const aNum = parseFloat(aCell.replace(/[^0-9.-]+/g, ''));
                const bNum = parseFloat(bCell.replace(/[^0-9.-]+/g, ''));

                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return sortAsc ? aNum - bNum : bNum - aNum;
                }
                return sortAsc ? aCell.localeCompare(bCell) : bCell.localeCompare(aCell);
            });

            rows.forEach(r => tbody.appendChild(r));
        }

        function toggleLangDropdown() {
            const el = document.getElementById('langDropdown');
            if (el) el.classList.toggle('hidden');
        }

        // Close dropdowns on outside click
        window.addEventListener('click', function(e) {
            if (!e.target.closest('#langDropdown') && !e.target.closest('button[onclick*="toggleLangDropdown"]')) {
                document.getElementById('langDropdown')?.classList.add('hidden');
            }
            if (!e.target.closest('#notifDropdown') && !e.target.closest('#notifBtn')) {
                document.getElementById('notifDropdown')?.classList.add('hidden');
            }
            if (!e.target.closest('#userDropdown') && !e.target.closest('#userMenuBtn')) {
                document.getElementById('userDropdown')?.classList.add('hidden');
            }
        });

        // Global Keydown (ESC to close modals)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('calculatorModal')?.classList.add('hidden');
                document.getElementById('itemDetailModal')?.classList.add('hidden');
                document.getElementById('helpModal')?.classList.add('hidden');
                document.getElementById('privacyModal')?.classList.add('hidden');
                document.getElementById('langDropdown')?.classList.add('hidden');
            }
        });
    </script>

    <!-- Instant Real-Time Lockdown Overlay for Staff -->
    <div id="liveLockdownOverlay" class="fixed inset-0 bg-slate-950/95 backdrop-blur-2xl z-[9999] hidden flex items-center justify-center p-4 antialiased transition-all duration-300">
        <div class="max-w-lg w-full bg-slate-900 border border-red-500/40 rounded-3xl p-8 text-center shadow-2xl space-y-5">
            <div id="lockdownIconBox" class="w-16 h-16 rounded-3xl bg-red-500/20 text-red-400 flex items-center justify-center text-3xl mx-auto border border-red-500/30">
                <i id="lockdownIcon" class="fa-solid fa-ban"></i>
            </div>
            <div>
                <h1 id="lockdownTitle" class="text-2xl font-black text-white tracking-tight">Shop Access Suspended</h1>
                <p id="lockdownSub" class="text-xs text-red-400 font-bold uppercase tracking-wider mt-1">Live Lockdown Activated</p>
            </div>
            <div id="lockdownMsg" class="p-4 bg-red-950/40 rounded-2xl border border-red-500/20 text-xs text-slate-300 leading-relaxed text-left">
                This store account has been deactivated or suspended by system engineering.
            </div>
            <div class="pt-2 flex items-center justify-center gap-3">
                <a href="../logout.php" class="px-6 py-3 rounded-2xl bg-white/10 hover:bg-white/20 text-white font-bold text-xs transition-all inline-flex items-center gap-2">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
                </a>
            </div>
        </div>
    </div>

    <!-- Active Real-Time Heartbeat Listener (Checks status every 2.5 seconds for instant lockdown) -->
    <?php if (($user_role ?? '') !== 'SuperAdmin'): ?>
    <script>
    (function() {
        let isCurrentlyLocked = false;
        function pollLockdownStatus() {
            fetch('api_status.php')
                .then(r => r.json())
                .then(data => {
                    const overlay = document.getElementById('liveLockdownOverlay');
                    if (!overlay) return;

                    if (data.is_locked) {
                        isCurrentlyLocked = true;
                        const title = document.getElementById('lockdownTitle');
                        const sub = document.getElementById('lockdownSub');
                        const msg = document.getElementById('lockdownMsg');
                        const icon = document.getElementById('lockdownIcon');
                        const iconBox = document.getElementById('lockdownIconBox');

                        if (data.lock_type === 'maintenance') {
                            title.textContent = 'System Under Maintenance';
                            sub.textContent = 'Scheduled Technical Maintenance Active';
                            sub.className = 'text-xs text-purple-400 font-bold uppercase tracking-wider mt-1';
                            iconBox.className = 'w-16 h-16 rounded-3xl bg-purple-500/20 text-purple-400 flex items-center justify-center text-3xl mx-auto border border-purple-500/30';
                            icon.className = 'fa-solid fa-screwdriver-wrench animate-pulse';
                            msg.className = 'p-4 bg-purple-950/30 rounded-2xl border border-purple-500/20 text-xs text-slate-300 leading-relaxed text-left';
                        } else {
                            title.textContent = 'Shop Access Suspended';
                            sub.textContent = 'System Access Suspended';
                            sub.className = 'text-xs text-red-400 font-bold uppercase tracking-wider mt-1';
                            iconBox.className = 'w-16 h-16 rounded-3xl bg-red-500/20 text-red-400 flex items-center justify-center text-3xl mx-auto border border-red-500/30';
                            icon.className = 'fa-solid fa-ban';
                            msg.className = 'p-4 bg-red-950/40 rounded-2xl border border-red-500/20 text-xs text-slate-300 leading-relaxed text-left';
                        }
                        msg.textContent = data.lock_message || 'Access restricted by system administration.';
                        overlay.classList.remove('hidden');
                    } else {
                        if (isCurrentlyLocked) {
                            // Lockdown lifted live -> reload to unlock
                            location.reload();
                        }
                    }
                })
                .catch(() => {});
        }
        setInterval(pollLockdownStatus, 2500);
    })();
    </script>
    <?php endif; ?>
    <script>
    if (typeof window.initSmartFormSave === 'undefined') {
        window.initSmartFormSave = function(formId, barId) {
            const form = document.getElementById(formId);
            const bar = document.getElementById(barId);
            if (!form || !bar) return;

            let initialValues = '';

            function getFormSnapshot() {
                const formData = new FormData(form);
                const state = {};
                for (let [key, val] of formData.entries()) {
                    if (val instanceof File) {
                        state[key] = val.name + '-' + val.size;
                    } else {
                        state[key] = val;
                    }
                }
                return JSON.stringify(state);
            }

            setTimeout(() => {
                initialValues = getFormSnapshot();
            }, 300);

            function checkFormChanges() {
                const current = getFormSnapshot();
                if (current !== initialValues) {
                    bar.classList.remove('opacity-0', 'translate-y-24', 'pointer-events-none');
                    bar.classList.add('opacity-100', 'translate-y-0', 'pointer-events-auto');
                } else {
                    bar.classList.add('opacity-0', 'translate-y-24', 'pointer-events-none');
                    bar.classList.remove('opacity-100', 'translate-y-0', 'pointer-events-auto');
                }
            }

            form.addEventListener('input', checkFormChanges);
            form.addEventListener('change', checkFormChanges);
            form.addEventListener('keyup', checkFormChanges);

            window.discardFormChanges = function() {
                form.reset();
                checkFormChanges();
            };
        };
    }
    </script>
</body>
</html>
