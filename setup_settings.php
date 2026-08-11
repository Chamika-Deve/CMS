<?php
$pdo = new PDO('mysql:host=localhost;dbname=csms_db', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(50) NOT NULL PRIMARY KEY,
  `setting_value` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

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
    'system_timezone' => 'Asia/Colombo'
];

$stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
foreach($default_settings as $key => $value) {
    $stmt->execute([$key, $value]);
}

echo "Settings OK\n";
