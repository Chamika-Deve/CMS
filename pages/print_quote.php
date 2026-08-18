<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
enforce_page_access('print_quote.php');

// Fetch Settings
$settings = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (\Exception $e) {}
}

function get_setting($key, $default = '') {
    global $settings;
    return isset($settings[$key]) ? htmlspecialchars($settings[$key]) : $default;
}

$shop_name = get_setting('shop_name', 'TechShop');
$shop_address = get_setting('shop_address', '');
$shop_phone = get_setting('shop_phone', '');
$shop_logo = get_setting('shop_logo', '');
$currency = get_setting('currency_symbol', 'Rs.');

$customer_name = htmlspecialchars($_POST['customer_name'] ?? 'Walk-in Customer');
$build_data_json = $_POST['build_data'] ?? '{}';
$build_data = json_decode($build_data_json, true);

if (!$build_data) {
    die("Invalid build data. Please return to the PC Builder and try again.");
}

$total = 0;
$quote_number = 'QT-' . date('Ymd') . '-' . rand(1000, 9999);
$date = date('F j, Y');

// Map slot IDs to nice names
$slot_names = [
    'cpu' => 'Processor',
    'mb' => 'Motherboard',
    'ram' => 'Memory (RAM)',
    'gpu' => 'Graphics Card',
    'storage1' => 'Primary Storage',
    'storage2' => 'Secondary Storage',
    'psu' => 'Power Supply',
    'case' => 'Casing',
    'cooler' => 'Cooler'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation <?php echo $quote_number; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        .print-area { max-width: 800px; margin: 40px auto; background: white; padding: 40px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-radius: 8px; }
        @media print {
            body { background: white; margin: 0; padding: 0; }
            .print-area { box-shadow: none; margin: 0; padding: 20px; max-width: 100%; border-radius: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="text-slate-800">

    <div class="text-center mt-6 no-print">
        <button onclick="window.print()" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-blue-700 shadow-md transition">Print Quotation</button>
        <button onclick="window.close()" class="bg-slate-200 text-slate-700 px-6 py-2 rounded-lg font-bold hover:bg-slate-300 shadow-sm transition ml-2">Close</button>
    </div>

    <div class="print-area">
        
        <!-- Header -->
        <div class="flex justify-between items-start border-b border-slate-200 pb-6 mb-6">
            <div class="flex items-center">
                <?php if ($shop_logo): ?>
                    <img src="../uploads/logo/<?php echo $shop_logo; ?>" alt="Logo" class="h-16 w-16 object-contain mr-4 rounded">
                <?php endif; ?>
                <div>
                    <h1 class="text-3xl font-bold text-slate-900"><?php echo $shop_name; ?></h1>
                    <p class="text-sm text-slate-500 mt-1"><?php echo nl2br($shop_address); ?></p>
                    <p class="text-sm text-slate-500"><?php echo $shop_phone; ?></p>
                </div>
            </div>
            <div class="text-right">
                <h2 class="text-3xl font-bold text-slate-300 uppercase tracking-widest">Quotation</h2>
                <p class="text-sm text-slate-600 mt-2 font-bold"><?php echo $quote_number; ?></p>
                <p class="text-sm text-slate-500">Date: <?php echo $date; ?></p>
            </div>
        </div>

        <!-- Customer Info -->
        <div class="mb-8">
            <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-2">Quotation For:</h3>
            <p class="text-lg font-bold text-slate-800"><?php echo $customer_name ? $customer_name : 'Walk-in Customer'; ?></p>
        </div>

        <!-- Items Table -->
        <table class="w-full text-left mb-8">
            <thead>
                <tr class="border-b-2 border-slate-800 text-sm">
                    <th class="py-2 font-bold w-1/4">Component</th>
                    <th class="py-2 font-bold w-1/2">Description / Model</th>
                    <th class="py-2 font-bold text-right w-1/4">Price</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php foreach ($slot_names as $slot_id => $slot_name): ?>
                    <?php 
                    if (isset($build_data[$slot_id]) && $build_data[$slot_id] !== null) {
                        $item = $build_data[$slot_id];
                        $price = (float)$item['price'];
                        $total += $price;
                    ?>
                        <tr>
                            <td class="py-3 text-sm font-bold text-slate-600"><?php echo $slot_name; ?></td>
                            <td class="py-3 text-sm">
                                <div class="font-bold text-slate-800"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="text-xs text-slate-500 font-mono mt-0.5"><?php echo htmlspecialchars($item['code']); ?></div>
                            </td>
                            <td class="py-3 text-sm font-bold text-right text-slate-800">
                                <?php echo $currency . ' ' . number_format($price, 2); ?>
                            </td>
                        </tr>
                    <?php } ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="flex justify-end border-t-2 border-slate-800 pt-4 mb-10">
            <div class="w-1/2">
                <div class="flex justify-between items-center mb-2">
                    <span class="font-bold text-slate-600 uppercase text-sm">Total Amount</span>
                    <span class="text-2xl font-bold text-slate-900"><?php echo $currency . ' ' . number_format($total, 2); ?></span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="border-t border-slate-200 pt-6 text-xs text-slate-500 text-center">
            <p class="mb-1"><strong>Terms & Conditions:</strong> Prices are subject to change based on market availability.</p>
            <p>This is a computer generated quotation and does not require a signature.</p>
        </div>

    </div>

    <script>
        // Auto print dialog on load
        window.onload = function() {
            setTimeout(() => {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
