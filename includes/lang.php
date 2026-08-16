<?php
/**
 * Localization & Multi-Language Dictionary Engine
 * Supports: English (en), Sinhala (si), Tamil (ta)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Language Selection Priority:
// URL ?lang=xx -> Session -> Database Setting -> Default ('en')
if (isset($_GET['lang'])) {
    $req_lang = strtolower(trim($_GET['lang']));
    if (in_array($req_lang, ['en', 'si', 'ta'])) {
        $_SESSION['shop_language'] = $req_lang;
    }
}

// Fetch active language
global $current_lang;
$current_lang = $_SESSION['shop_language'] ?? 'en';

if (isset($pdo) && $pdo && !isset($_SESSION['shop_language'])) {
    try {
        $stmt_lang = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'shop_language' LIMIT 1");
        $db_lang = $stmt_lang->fetchColumn();
        if (!empty($db_lang)) {
            $current_lang = $db_lang;
            $_SESSION['shop_language'] = $db_lang;
        }
    } catch (Exception $e) {}
}

$LANG_DICTIONARY = [
    'en' => [
        // Sidebar & Navigation
        'nav_operations' => 'Operations',
        'nav_dashboard' => 'Dashboard',
        'nav_pos' => 'Point of Sale (POS)',
        'nav_pc_builder' => 'Custom PC Builder',
        'nav_repairs' => 'Repair Workbench',
        'nav_customers' => 'Customers & CRM',
        'nav_inventory_supply' => 'Inventory & Supply',
        'nav_products' => 'Products & Stock',
        'nav_purchases' => 'Purchases & POs',
        'nav_suppliers' => 'Suppliers & AP',
        'nav_warranty' => 'Warranty & RMA',
        'nav_finance_control' => 'Finance & Control',
        'nav_accounting' => 'Accounting & Drawer',
        'nav_reports' => 'Analytics Reports',
        'nav_staff' => 'Staff & Security',
        'nav_audit' => 'Activity Audit Trail',
        'nav_settings_group' => 'Configuration',
        'nav_shop_settings' => 'Shop Settings',
        'nav_system_settings' => 'System Settings',
        'nav_sign_out' => 'Sign Out',

        // Header Actions
        'hdr_search_placeholder' => 'Search invoices, serials, tickets...',
        'hdr_new_sale' => 'New Sale',
        'hdr_calculator' => 'Calculator',
        'hdr_notifications' => 'Notifications',
        'hdr_dark_mode' => 'Dark Mode',
        'hdr_light_mode' => 'Light Mode',

        // Common Buttons & Labels
        'btn_save' => 'Save Changes',
        'btn_cancel' => 'Cancel',
        'btn_delete' => 'Delete',
        'btn_edit' => 'Edit',
        'btn_add' => 'Add New',
        'btn_print' => 'Print',
        'btn_export' => 'Export CSV',
        'btn_search' => 'Search',
        'btn_filter' => 'Filter',
        'btn_close' => 'Close',
        'btn_view' => 'View',
        'btn_actions' => 'Actions',
        'lbl_status' => 'Status',
        'lbl_total' => 'Total',
        'lbl_price' => 'Price',
        'lbl_cost' => 'Cost',
        'lbl_qty' => 'Quantity',
        'lbl_date' => 'Date',
        'lbl_name' => 'Name',
        'lbl_phone' => 'Phone',
        'lbl_email' => 'Email',
        'lbl_category' => 'Category',
        'lbl_sku' => 'SKU',
        'lbl_serial' => 'Serial Number',
        'lbl_in_stock' => 'In Stock',
        'lbl_out_of_stock' => 'Out of Stock',
        'lbl_low_stock' => 'Low Stock',
        'lbl_active' => 'Active',
        'lbl_inactive' => 'Inactive',

        // Dashboard
        'dash_sales_today' => "Today's Revenue",
        'dash_gross_sales' => 'Gross Sales',
        'dash_active_repairs' => 'Active Repair Jobs',
        'dash_in_progress' => 'In diagnostic & repair',
        'dash_low_stock' => 'Low Stock Alerts',
        'dash_reorder_needed' => 'Items require reordering',
        'dash_cash_drawer' => 'Cash Drawer Status',
        'dash_drawer_open' => 'Active & Balanced',
        'dash_recent_sales' => 'Recent POS Transactions',
        'dash_urgent_repairs' => 'Urgent Repair Workbench',

        // POS
        'pos_search_products' => 'Search products by name, SKU or barcode...',
        'pos_cart' => 'Current Order',
        'pos_empty_cart' => 'Cart is empty. Scan or select products.',
        'pos_subtotal' => 'Subtotal',
        'pos_discount' => 'Discount',
        'pos_tax' => 'Tax',
        'pos_grand_total' => 'Grand Total',
        'pos_pay_cash' => 'Pay Cash',
        'pos_pay_card' => 'Pay Card',
        'pos_complete_order' => 'Complete Order & Print Bill',

        // Repairs
        'rpr_intake' => 'New Repair Intake',
        'rpr_ticket' => 'Ticket #',
        'rpr_customer' => 'Customer',
        'rpr_device' => 'Device Model',
        'rpr_issue' => 'Reported Problem',
        'rpr_labor' => 'Labor Fee',
        'rpr_parts' => 'Parts Cost'
    ],

    'si' => [
        // Sidebar & Navigation (සිංහල)
        'nav_operations' => 'දෛනික මෙහෙයුම්',
        'nav_dashboard' => 'ප්‍රධාන පුවරුව (Dashboard)',
        'nav_pos' => 'විකුණුම් පර්යන්තය (POS)',
        'nav_pc_builder' => 'පරිගණක එකලස් කිරීම (PC Builder)',
        'nav_repairs' => 'අලුත්වැඩියා අංශය (Repairs)',
        'nav_customers' => 'පාරිභෝගික කළමනාකරණය (CRM)',
        'nav_inventory_supply' => 'තොග සහ මිලදී ගැනීම්',
        'nav_products' => 'භාණ්ඩ සහ තොග (Products)',
        'nav_purchases' => 'ඇණවුම් සහ මිලදී ගැනීම් (Purchases)',
        'nav_suppliers' => 'සැපයුම්කරුවන් (Suppliers)',
        'nav_warranty' => 'වගකීම් සහතිකය සහ RMA',
        'nav_finance_control' => 'මුදල් සහ පාලනය',
        'nav_accounting' => 'ගිණුම්කරණය සහ මුදල් පෙට්ටිය (Cash Drawer)',
        'nav_reports' => 'විශ්ලේෂණ වාර්තා (Reports)',
        'nav_staff' => 'සේවක මණ්ඩලය (Staff & Users)',
        'nav_audit' => 'ක්‍රියාකාරකම් සටහන් (Audit Trail)',
        'nav_settings_group' => 'පද්ධති සැකසුම්',
        'nav_shop_settings' => 'ව්‍යාපාරික සැකසුම් (Shop Settings)',
        'nav_system_settings' => 'ඉංජිනේරු පාලන පුවරුව (SuperAdmin)',
        'nav_sign_out' => 'පද්ධතියෙන් ඉවත් වන්න (Sign Out)',

        // Header Actions
        'hdr_search_placeholder' => 'බිල්පත්, අනුක්‍රමික අංක, ටිකට්පත් සොයන්න...',
        'hdr_new_sale' => 'නව විකිණුමක්',
        'hdr_calculator' => 'ගණක යන්ත්‍රය',
        'hdr_notifications' => 'දැනුම්දීම්',
        'hdr_dark_mode' => 'අඳුරු තේමාව (Dark Mode)',
        'hdr_light_mode' => 'දීප්තිමත් තේමාව (Light Mode)',

        // Common Buttons & Labels
        'btn_save' => 'වෙනස්කම් සුරකින්න',
        'btn_cancel' => 'අවලංගු කරන්න',
        'btn_delete' => 'ඉවත් කරන්න (Delete)',
        'btn_edit' => 'සංස්කරණය (Edit)',
        'btn_add' => 'අලුතින් එකතු කරන්න',
        'btn_print' => 'මුද්‍රණය කරන්න (Print)',
        'btn_export' => 'Export CSV',
        'btn_search' => 'සොයන්න',
        'btn_filter' => 'පෙරහන් කරන්න',
        'btn_close' => 'වසන්න',
        'btn_view' => 'බලන්න',
        'btn_actions' => 'ක්‍රියාමාර්ග (Actions)',
        'lbl_status' => 'තත්ත්වය (Status)',
        'lbl_total' => 'මුළු එකතුව',
        'lbl_price' => 'විකුණුම් මිල',
        'lbl_cost' => 'ගැණුම් පිරිවැය',
        'lbl_qty' => 'ප්‍රමාණය',
        'lbl_date' => 'දිනය',
        'lbl_name' => 'නම',
        'lbl_phone' => 'දුරකථන අංකය',
        'lbl_email' => 'විද්‍යුත් තැපෑල',
        'lbl_category' => 'වර්ගය (Category)',
        'lbl_sku' => 'භාණ්ඩ කේතය (SKU)',
        'lbl_serial' => 'අනුක්‍රමික අංකය (Serial/IMEI)',
        'lbl_in_stock' => 'තොගයේ ඇත',
        'lbl_out_of_stock' => 'තොග අවසන්',
        'lbl_low_stock' => 'අවම තොග අනතුරු ඇඟවීම්',
        'lbl_active' => 'සක්‍රීය (Active)',
        'lbl_inactive' => 'අක්‍රීය (Inactive)',

        // Dashboard
        'dash_sales_today' => 'අද දවසේ ආදායම',
        'dash_gross_sales' => 'දළ විකුණුම් එකතුව',
        'dash_active_repairs' => 'ක්‍රියාකාරී අලුත්වැඩියා රැකියා',
        'dash_in_progress' => 'පරීක්ෂා කරමින් සහ සකසමින් පවතින',
        'dash_low_stock' => 'අවම තොග අනතුරු ඇඟවීම්',
        'dash_reorder_needed' => 'නැවත ඇණවුම් කළ යුතු භාණ්ඩ',
        'dash_cash_drawer' => 'මුදල් පෙට්ටියේ තත්ත්වය',
        'dash_drawer_open' => 'විවෘතයි සහ සමබරයි',
        'dash_recent_sales' => 'මෑතකාලීන POS ගනුදෙනු',
        'dash_urgent_repairs' => 'හදිසි අලුත්වැඩියා රැකියා',

        // POS
        'pos_search_products' => 'නම, SKU හෝ බාර්කෝඩ් මගින් සොයන්න...',
        'pos_cart' => 'වත්මන් ඇණවුම',
        'pos_empty_cart' => 'කූඩය හිස්ය. භාණ්ඩයක් ස්කෑන් කරන්න.',
        'pos_subtotal' => 'උප එකතුව',
        'pos_discount' => 'වට්ටම්',
        'pos_tax' => 'බදු',
        'pos_grand_total' => 'මුළු මුදල',
        'pos_pay_cash' => 'මුදලින් ගෙවීම් (Cash)',
        'pos_pay_card' => 'කාඩ්පත් ගෙවීම් (Card)',
        'pos_complete_order' => 'ගනුදෙනුව අවසන් කර බිල්පත මුද්‍රණය කරන්න',

        // Repairs
        'rpr_intake' => 'නව අලුත්වැඩියා භාරගැනීම',
        'rpr_ticket' => 'ටිකට් අංකය',
        'rpr_customer' => 'පාරිභෝගිකයා',
        'rpr_device' => 'උපාංග මාදිලිය',
        'rpr_issue' => 'වාර්තා වූ දෝෂය',
        'rpr_labor' => 'කාර්මික ගාස්තු',
        'rpr_parts' => 'መለዋවට ගාස්තු'
    ],

    'ta' => [
        // Sidebar & Navigation (தமிழ்)
        'nav_operations' => 'செயல்பாடுகள்',
        'nav_dashboard' => 'முகப்பு பலகை (Dashboard)',
        'nav_pos' => 'விற்பனை மையம் (POS)',
        'nav_pc_builder' => 'கணினி அசெம்பிளி (PC Builder)',
        'nav_repairs' => 'பழுதுபார்க்கும் பிரிவு (Repairs)',
        'nav_customers' => 'வாடிக்கையாளர் மேலாண்மை (CRM)',
        'nav_inventory_supply' => 'சரக்கு மற்றும் கொள்முதல்',
        'nav_products' => 'பொருட்கள் மற்றும் இருப்பு (Products)',
        'nav_purchases' => 'கொள்முதல் ஆணைகள் (Purchases)',
        'nav_suppliers' => 'வழங்குநர்கள் (Suppliers)',
        'nav_warranty' => 'உத்தரவாதம் மற்றும் RMA',
        'nav_finance_control' => 'நிதி மற்றும் கட்டுப்பாடு',
        'nav_accounting' => 'கணக்கியல் மற்றும் காசாளர் பெட்டி',
        'nav_reports' => 'பகுப்பாய்வு அறிக்கைகள் (Reports)',
        'nav_staff' => 'பணியாளர்கள் (Staff & Users)',
        'nav_audit' => 'செயல்பாட்டு பதிவு (Audit Trail)',
        'nav_settings_group' => 'அமைப்புகள்',
        'nav_shop_settings' => 'கடை அமைப்புகள் (Shop Settings)',
        'nav_system_settings' => 'கணினி அமைப்புகள் (SuperAdmin)',
        'nav_sign_out' => 'வெளியேறு (Sign Out)',

        // Header Actions
        'hdr_search_placeholder' => 'விலைப்பட்டியல், வரிசை எண்களைத் தேடுங்கள்...',
        'hdr_new_sale' => 'புதிய விற்பனை',
        'hdr_calculator' => 'கணிப்பான் (Calculator)',
        'hdr_notifications' => 'அறிவிப்புகள்',
        'hdr_dark_mode' => 'இருண்ட பயன்முறை (Dark Mode)',
        'hdr_light_mode' => 'ஒளி பயன்முறை (Light Mode)',

        // Common Buttons & Labels
        'btn_save' => 'மாற்றங்களை சேமிக்கவும்',
        'btn_cancel' => 'ரத்து செய்',
        'btn_delete' => 'நீக்கு (Delete)',
        'btn_edit' => 'திருத்து (Edit)',
        'btn_add' => 'புதியதைச் சேர்',
        'btn_print' => 'அச்சிடு (Print)',
        'btn_export' => 'Export CSV',
        'btn_search' => 'தேடுங்கள்',
        'btn_filter' => 'வடிகட்டு',
        'btn_close' => 'மூடு',
        'btn_view' => 'பார்வை',
        'btn_actions' => 'நடவடிக்கைகள்',
        'lbl_status' => 'நிலை (Status)',
        'lbl_total' => 'மொத்தம்',
        'lbl_price' => 'விலை',
        'lbl_cost' => 'அடக்க விலை',
        'lbl_qty' => 'அளவு',
        'lbl_date' => 'தேதி',
        'lbl_name' => 'பெயர்',
        'lbl_phone' => 'தொலைபேசி',
        'lbl_email' => 'மின்னஞ்சல்',
        'lbl_category' => 'பிரிவு',
        'lbl_sku' => 'SKU குறியீடு',
        'lbl_serial' => 'வரிசை எண் (Serial)',
        'lbl_in_stock' => 'இருப்பில் உள்ளது',
        'lbl_out_of_stock' => 'இருப்பு இல்லை',
        'lbl_low_stock' => 'குறைந்த இருப்பு எச்சரிக்கை',
        'lbl_active' => 'செயலில் உள்ளது',
        'lbl_inactive' => 'செயலற்றது',

        // Dashboard
        'dash_sales_today' => 'இன்றைய வருமானம்',
        'dash_gross_sales' => 'மொத்த விற்பனை',
        'dash_active_repairs' => 'செயலில் உள்ள பழுதுகள்',
        'dash_in_progress' => 'செயல்பாட்டில் உள்ளது',
        'dash_low_stock' => 'குறைந்த இருப்பு எச்சரிக்கை',
        'dash_reorder_needed' => 'மீண்டும் ஆர்டர் செய்ய வேண்டியவை',
        'dash_cash_drawer' => 'பணப்பெட்டி நிலை',
        'dash_drawer_open' => 'செயலில் உள்ளது',
        'dash_recent_sales' => 'சமீபத்திய POS பரிவர்த்தனைகள்',
        'dash_urgent_repairs' => 'அவசர பழுதுபார்க்கும் பணி',

        // POS
        'pos_search_products' => 'பெயர் அல்லது பார் குறியீடு மூலம் தேடுங்கள்...',
        'pos_cart' => 'தற்போதைய ஆர்டர்',
        'pos_empty_cart' => 'கூடை காலியாக உள்ளது.',
        'pos_subtotal' => 'கூட்டுத்தொகை',
        'pos_discount' => 'தள்ளுபடி',
        'pos_tax' => 'வரி',
        'pos_grand_total' => 'பெரும் மொத்தம்',
        'pos_pay_cash' => 'பணம் செலுத்து (Cash)',
        'pos_pay_card' => 'அட்டை மூலம் (Card)',
        'pos_complete_order' => 'ஆர்டரை முடித்து ரசீது அச்சிடவும்',

        // Repairs
        'rpr_intake' => 'புதிய பழுது பதிவு',
        'rpr_ticket' => 'டிக்கெட் எண்',
        'rpr_customer' => 'வாடிக்கையாளர்',
        'rpr_device' => 'சாதன மாதிரி',
        'rpr_issue' => 'சிக்கல்',
        'rpr_labor' => 'கூலி கட்டணம்',
        'rpr_parts' => 'பாகங்கள் விலை'
    ]
];

if (!function_exists('__')) {
    function __($key, $default = '') {
        global $LANG_DICTIONARY, $current_lang;
        $lang = $current_lang ?? 'en';
        if (isset($LANG_DICTIONARY[$lang][$key])) {
            return $LANG_DICTIONARY[$lang][$key];
        }
        if (isset($LANG_DICTIONARY['en'][$key])) {
            return $LANG_DICTIONARY['en'][$key];
        }
        return !empty($default) ? $default : $key;
    }
}
