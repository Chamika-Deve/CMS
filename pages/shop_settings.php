<?php
require_once '../includes/db.php';
require_once '../includes/header.php';

// Access Control: Only Shop Owner / Admin or SuperAdmin can edit Shop Business Settings
if (!in_array($role, ['Admin', 'SuperAdmin'])) {
    echo "<div class='max-w-4xl mx-auto p-8 mt-6 bg-white rounded-3xl shadow-card border border-slate-100/90 text-center space-y-4'>
            <div class='w-16 h-16 rounded-3xl bg-red-50 text-red-600 flex items-center justify-center text-2xl mx-auto shadow-sm'>
                <i class='fa-solid fa-shield-halved'></i>
            </div>
            <h2 class='text-xl font-bold text-slate-900'>Access Restricted</h2>
            <p class='text-xs sm:text-sm text-slate-500 max-w-md mx-auto'>
                Shop profile and receipt settings are restricted to the <b>Shop Owner / Administrator</b>.
            </p>
            <div class='pt-2'>
                <a href='dashboard.php' class='px-5 py-2.5 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition-all inline-flex items-center gap-2'>
                    <i class='fa-solid fa-arrow-left'></i> Return to Dashboard
                </a>
            </div>
          </div>";
    require_once '../includes/footer.php';
    exit;
}

$success_msg = '';
$error_msg = '';

// Handle Shop Settings Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        $allowed_settings = [
            'shop_name', 'shop_address', 'shop_phone', 'shop_email', 'shop_tax_number',
            'currency_symbol', 'tax_rate', 'receipt_printer_width',
            'return_policy_days', 'bill_footer_message', 'shop_language',
        ];
        if (isset($_POST['settings']) && is_array($_POST['settings'])) {
            foreach ($_POST['settings'] as $key => $value) {
                if (!in_array($key, $allowed_settings, true) || !is_scalar($value)) {
                    continue;
                }
                $value = trim((string)$value);
                if ($key === 'tax_rate') $value = (string)max(0, min(100, (float)$value));
                if ($key === 'return_policy_days') $value = (string)max(0, min(365, (int)$value));
                if ($key === 'receipt_printer_width' && !in_array($value, ['58mm', '80mm', 'A4'], true)) $value = '80mm';
                if ($key === 'shop_email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('Enter a valid shop email address.');
                }
                if ($key === 'shop_language' && !preg_match('/^[a-z]{2,5}$/', $value)) $value = 'en';
                $stmt->execute([$key, $value]);
            }
        }

        // Handle Logo Upload (validated image formats only; SVG is excluded).
        if (isset($_FILES['shop_logo']) && $_FILES['shop_logo']['error'] === UPLOAD_ERR_OK) {
            $upload = $_FILES['shop_logo'];
            if (($upload['size'] ?? 0) < 1 || $upload['size'] > 2 * 1024 * 1024) {
                throw new InvalidArgumentException('The logo must be a non-empty image smaller than 2 MB.');
            }

            $allowed_mimes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ];
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
            if (!isset($allowed_mimes[$mime]) || @getimagesize($upload['tmp_name']) === false) {
                throw new InvalidArgumentException('Upload a valid JPG, PNG, GIF, or WebP logo.');
            }

            $upload_dir = __DIR__ . '/../uploads/logo/';
            if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
                throw new RuntimeException('The logo upload directory could not be created.');
            }

            $new_filename = 'logo_' . bin2hex(random_bytes(12)) . '.' . $allowed_mimes[$mime];
            $destination = $upload_dir . $new_filename;
            if (!move_uploaded_file($upload['tmp_name'], $destination)) {
                throw new RuntimeException('The logo could not be saved.');
            }
            chmod($destination, 0644);
            $stmt->execute(['shop_logo', $new_filename]);
        }
        
        $pdo->commit();

        if (isset($_POST['settings']['shop_language'])) {
            $_SESSION['shop_language'] = trim($_POST['settings']['shop_language']);
        }

        $success_msg = 'Shop business preferences & receipt template updated successfully!';
    } catch (\Exception $e) {
        $pdo->rollBack();
        $error_msg = 'Error updating settings: ' . safe_error_message($e);
    }
}

// Fetch Current Settings
$settings = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (\PDOException $e) {}
}

function get_setting($key, $default = '') {
    global $settings;
    return htmlspecialchars($settings[$key] ?? $default);
}

// SuperAdmin Defined Supported Currencies
$default_currencies = [
    ['code' => 'LKR', 'name' => 'Sri Lankan Rupee', 'symbol' => 'Rs.', 'status' => 'active'],
    ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'status' => 'active'],
    ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'status' => 'active'],
    ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'status' => 'active'],
    ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹', 'status' => 'active']
];
$system_currencies = $default_currencies;
if (!empty($settings['supported_currencies_json'])) {
    $decoded = json_decode($settings['supported_currencies_json'], true);
    if (is_array($decoded)) $system_currencies = $decoded;
}

// SuperAdmin Defined Supported Languages
$default_languages = [
    ['code' => 'en', 'name' => 'English', 'native' => 'English', 'status' => 'active'],
    ['code' => 'si', 'name' => 'Sinhala', 'native' => 'සිංහල', 'status' => 'active'],
    ['code' => 'ta', 'name' => 'Tamil', 'native' => 'தமிழ்', 'status' => 'active']
];
$system_languages = $default_languages;
if (!empty($settings['supported_languages_json'])) {
    $decoded_l = json_decode($settings['supported_languages_json'], true);
    if (is_array($decoded_l)) $system_languages = $decoded_l;
}

$active_currency_symbol = get_setting('currency_symbol', 'Rs.');
$active_language = get_setting('shop_language', 'en');
$active_logo = get_setting('shop_logo', '');
?>

<div class="space-y-6 max-w-5xl mx-auto pb-12">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-store text-emerald-600"></i>
                <span>Shop Profile & Business Settings</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Customize your store details, receipt templates, and operational preferences.</p>
        </div>
        
        <?php if ($role === 'SuperAdmin'): ?>
        <a href="settings.php" class="px-4 py-2 rounded-2xl bg-purple-100 hover:bg-purple-200 text-purple-800 text-xs font-bold transition-all inline-flex items-center gap-2">
            <i class="fa-solid fa-gears"></i> SuperAdmin System Control &rarr;
        </a>
        <?php endif; ?>
    </div>

    <?php if ($success_msg): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs sm:text-sm flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-circle-check text-emerald-600"></i>
            <span><?php echo htmlspecialchars($success_msg); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-2xl text-xs sm:text-sm flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-circle-exclamation text-red-600"></i>
            <span><?php echo htmlspecialchars($error_msg); ?></span>
        </div>
    <?php endif; ?>

    <form id="shopSettingsForm" method="POST" action="shop_settings.php" enctype="multipart/form-data" class="space-y-6">
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Store Profile & Branding -->
            <div class="lg:col-span-2 space-y-6">
                
                <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7 space-y-4">
                    <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-building text-emerald-600"></i> Store Profile & Contact Details
                    </h2>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Shop / Business Name *</label>
                            <input type="text" name="settings[shop_name]" required value="<?php echo get_setting('shop_name', 'Tech Solutions Inc.'); ?>" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Business Registration / Tax No</label>
                            <input type="text" name="settings[shop_tax_number]" value="<?php echo get_setting('shop_tax_number', 'PV-98214'); ?>" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Store Phone Number *</label>
                            <input type="text" name="settings[shop_phone]" required value="<?php echo get_setting('shop_phone', '+94 77 123 4567'); ?>" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Store Email Address *</label>
                            <input type="email" name="settings[shop_email]" required value="<?php echo get_setting('shop_email', 'info@techsolutions.lk'); ?>" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Physical Store Address</label>
                            <textarea name="settings[shop_address]" rows="2" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"><?php echo get_setting('shop_address', '123 Main Street, Colombo 01'); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Receipt & POS Invoice Customizer -->
                <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7 space-y-4">
                    <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-receipt text-emerald-600"></i> Thermal Receipt & Invoice Customizer
                    </h2>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Thermal Printer Paper Width</label>
                            <select name="settings[receipt_printer_width]" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                                <option value="80mm" <?php echo get_setting('receipt_printer_width') === '80mm' ? 'selected' : ''; ?>>80mm (Standard POS Thermal)</option>
                                <option value="58mm" <?php echo get_setting('receipt_printer_width') === '58mm' ? 'selected' : ''; ?>>58mm (Compact Mobile POS)</option>
                                <option value="A4" <?php echo get_setting('receipt_printer_width') === 'A4' ? 'selected' : ''; ?>>A4 Full Page Laser Invoice</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Return Policy Window (Days)</label>
                            <input type="number" name="settings[return_policy_days]" value="<?php echo get_setting('return_policy_days', '7'); ?>" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Receipt Footer Notice & Warranty Policy</label>
                            <textarea name="settings[bill_footer_message]" rows="2" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"><?php echo get_setting('bill_footer_message', 'Thank you for shopping with us! Goods once sold cannot be returned without the original receipt.'); ?></textarea>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Currency, Language & Logo -->
            <div class="space-y-6">
                
                <!-- Currency & Language Selection (From SuperAdmin-Approved List) -->
                <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7 space-y-4">
                    <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-globe text-emerald-600"></i> Currency & Language
                    </h2>
                    <p class="text-[11px] text-slate-400">Select from system-supported currencies and languages.</p>

                    <div class="space-y-4 pt-1">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Shop Operating Currency</label>
                            <select name="settings[currency_symbol]" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                                <?php foreach ($system_currencies as $c): 
                                    if (($c['status'] ?? 'active') !== 'active') continue;
                                ?>
                                    <option value="<?php echo htmlspecialchars($c['symbol']); ?>" <?php echo $active_currency_symbol === $c['symbol'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['code'] . ' - ' . $c['name'] . ' (' . $c['symbol'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Shop Interface Language</label>
                            <select name="settings[shop_language]" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                                <?php foreach ($system_languages as $l): 
                                    if (($l['status'] ?? 'active') !== 'active') continue;
                                ?>
                                    <option value="<?php echo htmlspecialchars($l['code']); ?>" <?php echo $active_language === $l['code'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($l['name'] . ' (' . ($l['native'] ?? '') . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Default Sales Tax Rate (%)</label>
                            <input type="number" step="0.01" name="settings[tax_rate]" value="<?php echo get_setting('tax_rate', '0.00'); ?>" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                        </div>
                    </div>
                </div>

                <!-- Logo Upload -->
                <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7 space-y-4">
                    <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-image text-emerald-600"></i> Store Logo
                    </h2>
                    
                    <?php if ($active_logo && file_exists('../uploads/logo/' . $active_logo)): ?>
                        <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200 text-center">
                            <img src="../uploads/logo/<?php echo htmlspecialchars($active_logo); ?>" alt="Shop Logo" class="max-h-16 mx-auto object-contain">
                            <span class="text-[10px] text-slate-400 mt-1 block">Current Store Logo</span>
                        </div>
                    <?php endif; ?>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Upload New Logo</label>
                        <input type="file" name="shop_logo" accept="image/*" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                    </div>
                </div>

            </div>

        </div>

        <!-- Dynamic Floating Save Bar (Pops up only when input changes occur) -->
        <div id="shopSaveBar" class="fixed bottom-6 right-6 z-50 transform translate-y-24 opacity-0 pointer-events-none transition-all duration-300 ease-out">
            <div class="bg-slate-900/95 backdrop-blur-md text-white px-5 py-3.5 rounded-2xl shadow-2xl border border-slate-800 flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                    <span class="text-xs font-bold text-slate-200">Unsaved Shop Settings Detected</span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="discardFormChanges()" class="px-3.5 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-slate-300 text-xs font-bold transition-all">
                        Discard
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold transition-all shadow-lg shadow-emerald-500/30 flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Save Shop Settings</span>
                    </button>
                </div>
            </div>
        </div>
    </form>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof initSmartFormSave === 'function') {
                initSmartFormSave('shopSettingsForm', 'shopSaveBar');
            }
        });
    </script>
</div>

<?php require_once '../includes/footer.php'; ?>
