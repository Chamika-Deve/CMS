<?php
$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    $pdo_root = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $pdo_root->exec("CREATE DATABASE IF NOT EXISTS `csms_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database csms_db created / exists.\n";
} catch (Exception $e) {
    echo "Root DB connection note: " . $e->getMessage() . "\n";
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=csms_db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 1. Users Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
            `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Cashier',
            `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `branch_id` int NOT NULL DEFAULT 1,
            `status` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Seed 6 Distinct Roles
    $pwd_hash = password_hash('password', PASSWORD_BCRYPT);
    $seed_users = [
        ['Admin User', 'admin@example.com', $pwd_hash, 'Admin'],
        ['Manager User', 'manager@example.com', $pwd_hash, 'Manager'],
        ['Cashier User', 'cashier@example.com', $pwd_hash, 'Cashier'],
        ['Technician Alex', 'tech@example.com', $pwd_hash, 'Technician'],
        ['Inventory Staff Dave', 'inventory@example.com', $pwd_hash, 'Inventory'],
        ['Accountant Sarah', 'accountant@example.com', $pwd_hash, 'Accountant'],
    ];
    $stmt_u = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE role = VALUES(role)");
    foreach ($seed_users as $su) {
        $stmt_u->execute($su);
    }
    echo "Seed users initialized for all 6 roles.\n";

    // 2. Customers Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `customers` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `address` text COLLATE utf8mb4_unicode_ci,
            `company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `customer_type` enum('Individual','Corporate','VIP') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Individual',
            `credit_limit` decimal(10,2) NOT NULL DEFAULT 0.00,
            `store_credit` decimal(10,2) NOT NULL DEFAULT 0.00,
            `points` int NOT NULL DEFAULT 0,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Add missing columns if existing table
    $customer_cols = [
        "ALTER TABLE `customers` ADD COLUMN IF NOT EXISTS `company_name` varchar(255) DEFAULT NULL",
        "ALTER TABLE `customers` ADD COLUMN IF NOT EXISTS `customer_type` enum('Individual','Corporate','VIP') NOT NULL DEFAULT 'Individual'",
        "ALTER TABLE `customers` ADD COLUMN IF NOT EXISTS `credit_limit` decimal(10,2) NOT NULL DEFAULT 0.00",
        "ALTER TABLE `customers` ADD COLUMN IF NOT EXISTS `store_credit` decimal(10,2) NOT NULL DEFAULT 0.00",
        "ALTER TABLE `customers` ADD COLUMN IF NOT EXISTS `points` int NOT NULL DEFAULT 0"
    ];
    foreach ($customer_cols as $c_sql) {
        try { $pdo->exec($c_sql); } catch (Exception $e) {}
    }

    // Add Walk-in customer if missing
    $pdo->exec("INSERT IGNORE INTO customers (id, name, phone, email, customer_type) VALUES (1, 'Walk-in Customer', '0000000000', 'walkin@shop.local', 'Individual')");

    // 3. Brands & Categories
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `brands` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `categories` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `parent_id` bigint unsigned DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 4. Products Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `products` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `category_id` bigint unsigned DEFAULT NULL,
            `brand_id` bigint unsigned DEFAULT NULL,
            `product_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
            `barcode` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
            `selling_price` decimal(10,2) NOT NULL DEFAULT 0.00,
            `warranty_months` int NOT NULL DEFAULT 0,
            `reorder_level` int NOT NULL DEFAULT 5,
            `unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pcs',
            `description` text COLLATE utf8mb4_unicode_ci,
            `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `status` enum('Active','Discontinued') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 5. Product Serials Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `product_serials` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `product_id` bigint unsigned NOT NULL,
            `serial_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
            `purchase_id` bigint unsigned DEFAULT NULL,
            `status` enum('in_stock','sold','returned','repair','defective') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_stock',
            `warranty_start_date` date DEFAULT NULL,
            `warranty_end_date` date DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `product_id` (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 6. Suppliers Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `suppliers` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `contact_person` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `address` text COLLATE utf8mb4_unicode_ci,
            `payment_terms` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Net 30',
            `balance_due` decimal(12,2) NOT NULL DEFAULT 0.00,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Alter Purchases status column
    try {
        $pdo->exec("ALTER TABLE `purchases` MODIFY COLUMN `status` varchar(50) NOT NULL DEFAULT 'Draft'");
    } catch (Exception $e) {}

    // 7. Repair Jobs Table (Enhanced with all ticket & tracking fields)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `repair_jobs` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `ticket_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
            `customer_id` bigint unsigned NOT NULL,
            `device_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Laptop',
            `device_brand` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `device_model` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `serial_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `passcode_pin` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `issue_description` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `diagnosis_notes` text COLLATE utf8mb4_unicode_ci,
            `accessories_included` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `technician_id` bigint unsigned DEFAULT NULL,
            `status` enum('Received','Diagnosing','Waiting for Parts','In Repair','Ready for Pickup','Completed','Closed','Cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Received',
            `estimated_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
            `labor_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
            `parts_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
            `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
            `is_quote_approved` tinyint(1) NOT NULL DEFAULT 0,
            `warranty_days` int NOT NULL DEFAULT 30,
            `public_token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
            `received_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `delivered_date` datetime DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `customer_id` (`customer_id`),
            KEY `technician_id` (`technician_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 8. Repair Parts Used Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `repair_parts_used` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `repair_job_id` bigint unsigned NOT NULL,
            `product_id` bigint unsigned NOT NULL,
            `product_serial_id` bigint unsigned DEFAULT NULL,
            `quantity` int NOT NULL DEFAULT 1,
            `unit_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
            `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `repair_job_id` (`repair_job_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 9. Expenses Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `expenses` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'General',
            `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `amount` decimal(10,2) NOT NULL,
            `payment_method` enum('Cash','Card','Bank Transfer') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Cash',
            `expense_date` date NOT NULL,
            `notes` text COLLATE utf8mb4_unicode_ci,
            `receipt_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `created_by` bigint unsigned DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 10. Cash Registers (Daily Drawer Reconciliation)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `cash_registers` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint unsigned NOT NULL,
            `opening_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `closing_time` datetime DEFAULT NULL,
            `opening_cash` decimal(10,2) NOT NULL DEFAULT 0.00,
            `closing_cash_actual` decimal(10,2) DEFAULT NULL,
            `closing_cash_system` decimal(10,2) DEFAULT NULL,
            `cash_difference` decimal(10,2) DEFAULT NULL,
            `notes` text COLLATE utf8mb4_unicode_ci,
            `status` enum('Open','Closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Open',
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 11. Warranty Claims Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `warranty_claims` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `claim_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
            `product_serial_id` bigint unsigned DEFAULT NULL,
            `customer_id` bigint unsigned NOT NULL,
            `issue` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `claim_type` enum('In-House Repair','Supplier RMA','Replacement','Refund') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'In-House Repair',
            `status` enum('Pending','Approved','In Supplier RMA','Repaired','Replaced','Refunded','Rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
            `resolution` text COLLATE utf8mb4_unicode_ci,
            `created_by` bigint unsigned DEFAULT NULL,
            `claim_date` date NOT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 12. Activity Logs Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `activity_logs` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint unsigned DEFAULT NULL,
            `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `module` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `table_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `record_id` bigint unsigned DEFAULT NULL,
            `details` text COLLATE utf8mb4_unicode_ci,
            `ip_address` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    echo "All tables migrated successfully.\n";

} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
