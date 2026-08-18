<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
enforce_page_access('print_bill.php');

$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$pdo) {
    http_response_code(503);
    exit('The database is temporarily unavailable.');
}
if ($sale_id < 1) {
    http_response_code(400);
    exit('A valid sale ID is required.');
}

// 1. Fetch Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log('Receipt settings query failed: ' . $e->getMessage());
}

$shop_name = $settings['shop_name'] ?? 'Shop Name';
$shop_address = $settings['shop_address'] ?? 'Shop Address';
$shop_phone = $settings['shop_phone'] ?? '';
$shop_email = $settings['shop_email'] ?? '';
$currency = $settings['currency_symbol'] ?? 'Rs.';
$footer_msg = $settings['bill_footer_message'] ?? 'Thank you!';
$printer_width = $settings['receipt_printer_width'] ?? '80mm';

// 2. Fetch Sale & Customer Details
$stmt_sale = $pdo->prepare("
    SELECT s.*, c.name as customer_name, c.phone as customer_phone
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE s.id = ?
");
$stmt_sale->execute([$sale_id]);
$sale = $stmt_sale->fetch();

if (!$sale) {
    die("Sale not found.");
}

// 3. Fetch Sale Items and Group them by Product
$stmt_items = $pdo->prepare("
    SELECT si.quantity, si.unit_price, p.id as product_id, p.name as product_name, p.product_code, ps.serial_number
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    LEFT JOIN product_serials ps ON si.product_serial_id = ps.id
    WHERE si.sale_id = ?
");
$stmt_items->execute([$sale_id]);
$raw_items = $stmt_items->fetchAll();

$grouped_items = [];
foreach ($raw_items as $item) {
    $pid = $item['product_id'];
    if (!isset($grouped_items[$pid])) {
        $grouped_items[$pid] = [
            'name' => $item['product_name'],
            'code' => $item['product_code'],
            'price' => $item['unit_price'],
            'qty' => 0,
            'serials' => []
        ];
    }
    
    $grouped_items[$pid]['qty'] += $item['quantity'];
    
    if (!empty($item['serial_number'])) {
        $grouped_items[$pid]['serials'][] = $item['serial_number'];
    }
}

// Calculate max width for CSS
$css_max_width = '100%';
if ($printer_width === '80mm') {
    $css_max_width = '300px'; // Approx 80mm
} else if ($printer_width === '58mm') {
    $css_max_width = '210px'; // Approx 58mm
} else {
    $css_max_width = '800px'; // A4
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo htmlspecialchars($sale['invoice_no']); ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            margin: 0;
            padding: 10px;
            color: #000;
            background: #fff;
            font-size: 12px;
        }
        .bill-container {
            max-width: <?php echo $css_max_width; ?>;
            margin: 0 auto;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .text-xl { font-size: 1.2rem; }
        .mb-1 { margin-bottom: 5px; }
        .mb-2 { margin-bottom: 10px; }
        .mt-2 { margin-top: 10px; }
        .w-full { width: 100%; }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 4px 0;
            vertical-align: top;
        }
        .border-t { border-top: 1px dashed #000; }
        .border-b { border-bottom: 1px dashed #000; }
        
        .item-row td { padding-top: 6px; }
        .serial-list {
            font-size: 10px;
            color: #333;
            padding-left: 10px;
            margin: 2px 0 0 0;
        }
        .serial-list li {
            list-style-type: none;
            position: relative;
        }
        .serial-list li:before {
            content: '- ';
        }
        
        /* Hide print button when printing */
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

<div class="bill-container">
    <div class="text-center mb-2">
        <div class="font-bold text-xl mb-1"><?php echo htmlspecialchars($shop_name); ?></div>
        <div><?php echo nl2br(htmlspecialchars($shop_address)); ?></div>
        <div><?php echo htmlspecialchars($shop_phone); ?></div>
        <?php if($shop_email): ?><div><?php echo htmlspecialchars($shop_email); ?></div><?php endif; ?>
    </div>

    <div class="border-t border-b mt-2 mb-2" style="padding: 5px 0;">
        <table class="w-full">
            <tr>
                <td class="font-bold text-left">Invoice No:</td>
                <td class="text-right"><?php echo htmlspecialchars($sale['invoice_no']); ?></td>
            </tr>
            <tr>
                <td class="font-bold text-left">Date:</td>
                <td class="text-right"><?php echo date('Y-m-d H:i', strtotime($sale['sale_date'])); ?></td>
            </tr>
            <?php if($sale['customer_name']): ?>
            <tr>
                <td class="font-bold text-left">Customer:</td>
                <td class="text-right"><?php echo htmlspecialchars($sale['customer_name']); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td class="font-bold text-left">Payment:</td>
                <td class="text-right"><?php echo htmlspecialchars($sale['payment_method']); ?></td>
            </tr>
        </table>
    </div>

    <table class="w-full mb-2">
        <thead>
            <tr class="border-b">
                <th class="text-left font-bold" style="width: 50%;">Item</th>
                <th class="text-center font-bold">Qty</th>
                <th class="text-right font-bold">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($grouped_items as $item): ?>
                <?php $row_total = $item['price'] * $item['qty']; ?>
                <tr class="item-row">
                    <td class="text-left">
                        <div class="font-bold"><?php echo htmlspecialchars($item['name']); ?></div>
                        <?php if (!empty($item['serials'])): ?>
                            <ul class="serial-list">
                                <?php foreach($item['serials'] as $sn): ?>
                                    <li>SN: <?php echo htmlspecialchars($sn); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?php echo $item['qty']; ?></td>
                    <td class="text-right"><?php echo number_format($row_total, 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="border-t pt-2">
        <table class="w-full font-bold">
            <?php 
                // We reverse calculate subtotal based on stored tax for simplicity on the receipt, 
                // or just display total since POS already calculated it.
                $subtotal = $sale['total_amount'] - $sale['tax']; 
            ?>
            <tr>
                <td class="text-left">Subtotal:</td>
                <td class="text-right"><?php echo $currency; ?> <?php echo number_format($subtotal, 2); ?></td>
            </tr>
            <?php if($sale['tax'] > 0): ?>
            <tr>
                <td class="text-left">Tax:</td>
                <td class="text-right"><?php echo $currency; ?> <?php echo number_format($sale['tax'], 2); ?></td>
            </tr>
            <?php endif; ?>
            <tr class="text-xl">
                <td class="text-left" style="padding-top: 5px;">TOTAL:</td>
                <td class="text-right" style="padding-top: 5px;"><?php echo $currency; ?> <?php echo number_format($sale['total_amount'], 2); ?></td>
            </tr>
        </table>
    </div>

    <div class="text-center mt-2 border-t pt-2" style="font-size: 11px;">
        <?php echo nl2br(htmlspecialchars($footer_msg)); ?>
    </div>
    
    <div class="text-center mt-2 no-print">
        <button onclick="window.print()" style="padding: 10px 20px; font-weight: bold; cursor: pointer;">Print Receipt</button>
        <button onclick="window.close()" style="padding: 10px 20px; cursor: pointer;">Close</button>
    </div>
</div>

<script>
    // Automatically trigger print dialog when page loads
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 500);
    };
</script>
</body>
</html>
