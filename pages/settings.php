<?php
require_once '../includes/db.php';
require_once '../includes/header.php';

// Check if user is logged in and is Admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    echo "<div class='max-w-4xl mx-auto p-8 mt-10 bg-red-50 text-red-700 rounded-xl border border-red-200 shadow-sm'>
            <h2 class='text-2xl font-bold mb-2 flex items-center'><i class='fa-solid fa-shield-halved mr-3'></i> Access Denied</h2>
            <p>You do not have permission to view this page. This area is restricted to Administrators only.</p>
            <a href='dashboard.php' class='inline-block mt-4 bg-red-600 text-white px-4 py-2 rounded font-medium hover:bg-red-700 transition'>Return to Dashboard</a>
          </div>";
    require_once '../includes/footer.php';
    exit;
}

// Handle Form Submission
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['action']) && $_POST['action'] === 'reset_settings') {
            // Factory Reset Logic
            $pdo->exec("DELETE FROM settings");
            $default_settings = [
                'shop_name' => 'Tech Solutions Inc.',
                'shop_address' => '123 Main Street, Colombo 01',
                'shop_phone' => '+94 77 123 4567',
                'shop_email' => 'info@techsolutions.lk',
                'tax_rate' => '0',
                'currency_symbol' => 'Rs.',
                'bill_footer_message' => 'Thank you for shopping with us! Goods once sold cannot be returned without the original receipt.',
                'return_policy_days' => '7',
                'receipt_printer_width' => '80mm',
                'system_timezone' => 'Asia/Colombo',
                'system_name' => 'TechShop'
            ];
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
            foreach($default_settings as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            $success_msg = 'Settings have been reset to factory defaults.';
        } else {
            // Normal Save Logic
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            
            if (isset($_POST['settings'])) {
                foreach ($_POST['settings'] as $key => $value) {
                    $stmt->execute([$key, trim($value)]);
                }
            }
        
        // Handle Logo Upload
        if (isset($_FILES['shop_logo']) && $_FILES['shop_logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/logo/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_ext = strtolower(pathinfo($_FILES['shop_logo']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
            
            if (in_array($file_ext, $allowed_exts)) {
                $new_filename = 'logo_' . time() . '.' . $file_ext;
                $destination = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['shop_logo']['tmp_name'], $destination)) {
                    $stmt->execute(['shop_logo', $new_filename]);
                }
            }
        }
        } // End else
        
        $pdo->commit();
        if (!$success_msg) $success_msg = 'Settings updated successfully.';
    } catch (\Exception $e) {
        $pdo->rollBack();
        $error_msg = 'Error updating settings: ' . $e->getMessage();
    }
}

// Fetch Current Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (\PDOException $e) {
    $error_msg = 'Database error: ' . $e->getMessage();
}

// Helper function to safely get setting value
function get_setting($key, $default = '') {
    global $settings;
    return isset($settings[$key]) ? htmlspecialchars($settings[$key]) : $default;
}
?>

<div class="max-w-5xl mx-auto h-[calc(100vh-100px)] overflow-y-auto pb-10">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">System Settings</h1>
        <p class="text-sm text-slate-500">Configure global parameters for the shop, billing, and system preferences.</p>
    </div>

    <?php if ($success_msg): ?>
        <div class="bg-emerald-50 text-emerald-700 p-4 rounded-lg mb-6 border border-emerald-200 flex items-center">
            <i class="fa-solid fa-check-circle mr-2 text-lg"></i> <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6 border border-red-200 flex items-center">
            <i class="fa-solid fa-triangle-exclamation mr-2 text-lg"></i> <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="settings.php" enctype="multipart/form-data" class="space-y-6">
        
        <!-- Shop Profile Section -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
                <h2 class="font-bold text-slate-800 flex items-center">
                    <i class="fa-solid fa-store text-blue-600 mr-2 w-5 text-center"></i> Shop Profile
                </h2>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Shop Name *</label>
                    <input type="text" name="settings[shop_name]" value="<?php echo get_setting('shop_name'); ?>" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                    <p class="text-xs text-slate-400 mt-1">Displayed on bills and UI headers.</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Shop Phone *</label>
                    <input type="text" name="settings[shop_phone]" value="<?php echo get_setting('shop_phone'); ?>" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Shop Address *</label>
                    <textarea name="settings[shop_address]" rows="2" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm"><?php echo get_setting('shop_address'); ?></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Shop Email</label>
                    <input type="email" name="settings[shop_email]" value="<?php echo get_setting('shop_email'); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                </div>
            </div>
        </div>

        <!-- Billing & POS Section -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
                <h2 class="font-bold text-slate-800 flex items-center">
                    <i class="fa-solid fa-file-invoice text-emerald-600 mr-2 w-5 text-center"></i> Billing & POS Settings
                </h2>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Currency Symbol</label>
                    <input type="text" name="settings[currency_symbol]" value="<?php echo get_setting('currency_symbol', 'Rs.'); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Tax Rate (%)</label>
                    <input type="number" step="0.01" min="0" name="settings[tax_rate]" value="<?php echo get_setting('tax_rate', '0'); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Receipt Printer Width</label>
                    <select name="settings[receipt_printer_width]" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                        <option value="80mm" <?php echo get_setting('receipt_printer_width') === '80mm' ? 'selected' : ''; ?>>80mm (Standard POS)</option>
                        <option value="58mm" <?php echo get_setting('receipt_printer_width') === '58mm' ? 'selected' : ''; ?>>58mm (Small POS)</option>
                        <option value="A4" <?php echo get_setting('receipt_printer_width') === 'A4' ? 'selected' : ''; ?>>A4 (Standard Printer)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Return Policy (Days)</label>
                    <input type="number" min="0" name="settings[return_policy_days]" value="<?php echo get_setting('return_policy_days', '7'); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Bill Footer Message</label>
                    <textarea name="settings[bill_footer_message]" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm"><?php echo get_setting('bill_footer_message'); ?></textarea>
                    <p class="text-xs text-slate-400 mt-1">Text to display at the very bottom of the receipt.</p>
                </div>
            </div>
        </div>

        <!-- System Section -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                <h2 class="font-bold text-slate-800 flex items-center">
                    <i class="fa-solid fa-server text-purple-600 mr-2 w-5 text-center"></i> System Preferences
                </h2>
                <!-- Reset Button -->
                <button type="submit" name="action" value="reset_settings" onclick="return confirm('Are you sure you want to reset all settings to Factory Defaults? This will delete your custom logo and preferences.');" class="text-xs bg-red-100 hover:bg-red-600 text-red-600 hover:text-white px-3 py-1.5 rounded-lg font-bold transition-colors shadow-sm">
                    <i class="fa-solid fa-rotate-left mr-1"></i> Factory Reset
                </button>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">System Timezone</label>
                    <select name="settings[system_timezone]" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                        <option value="Asia/Colombo" <?php echo get_setting('system_timezone') === 'Asia/Colombo' ? 'selected' : ''; ?>>Asia/Colombo (Sri Lanka)</option>
                        <option value="UTC" <?php echo get_setting('system_timezone') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                        <!-- Can add more timezones if needed -->
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">System Name</label>
                    <input type="text" name="settings[system_name]" value="<?php echo get_setting('system_name', 'TechShop'); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                    <p class="text-xs text-slate-400 mt-1">Displayed in the top sidebar navigation.</p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Shop Logo</label>
                    <div class="flex items-center space-x-4">
                        <?php $current_logo = get_setting('shop_logo'); ?>
                        <?php if ($current_logo): ?>
                            <img src="../uploads/logo/<?php echo $current_logo; ?>" alt="Logo" class="h-12 w-12 object-contain bg-white rounded border border-slate-200 p-1">
                        <?php else: ?>
                            <div class="h-12 w-12 bg-slate-200 rounded flex items-center justify-center text-slate-400">
                                <i class="fa-solid fa-image"></i>
                            </div>
                        <?php endif; ?>
                        <div class="flex-1">
                            <input type="file" name="shop_logo" accept="image/*" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm bg-white">
                            <p class="text-xs text-slate-400 mt-1">Leave empty to keep the current logo. (Recommended: Square aspect ratio)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end pt-4 pb-10">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-xl shadow-md transition-colors flex items-center">
                <i class="fa-solid fa-save mr-2"></i> Save Settings
            </button>
        </div>

    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
