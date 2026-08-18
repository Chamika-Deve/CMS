<?php
/**
 * Idempotent database schema creation and upgrade helpers.
 *
 * This is intentionally separate from page rendering so an existing database
 * can be repaired from the SuperAdmin migration action or from the CLI.
 */

if (!function_exists('run_schema_migrations')) {
    function run_schema_migrations(PDO $pdo): array
    {
        $changes = [];

        $tableExists = static function (string $table) use ($pdo): bool {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        };

        $columnExists = static function (string $table, string $column) use ($pdo, $tableExists): bool {
            if (!$tableExists($table)) {
                return false;
            }
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
            $stmt->execute([$table, $column]);
            return (bool)$stmt->fetchColumn();
        };

        $indexExists = static function (string $table, string $index) use ($pdo, $tableExists): bool {
            if (!$tableExists($table)) {
                return false;
            }
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
            $stmt->execute([$table, $index]);
            return (bool)$stmt->fetchColumn();
        };

        $createStatements = [
            'users' => "CREATE TABLE IF NOT EXISTS `users` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `email` varchar(255) NOT NULL,
                `password` varchar(255) NOT NULL,
                `role` varchar(50) NOT NULL DEFAULT 'Cashier',
                `phone` varchar(50) DEFAULT NULL,
                `branch_id` int NOT NULL DEFAULT 1,
                `status` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`), UNIQUE KEY `email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'customers' => "CREATE TABLE IF NOT EXISTS `customers` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `phone` varchar(50) DEFAULT NULL,
                `email` varchar(255) DEFAULT NULL,
                `address` text,
                `points` int NOT NULL DEFAULT 0,
                `company_name` varchar(255) DEFAULT NULL,
                `customer_type` varchar(50) NOT NULL DEFAULT 'Individual',
                `credit_limit` decimal(10,2) NOT NULL DEFAULT 0.00,
                `store_credit` decimal(10,2) NOT NULL DEFAULT 0.00,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'brands' => "CREATE TABLE IF NOT EXISTS `brands` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'categories' => "CREATE TABLE IF NOT EXISTS `categories` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `parent_id` bigint unsigned DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`), KEY `parent_id` (`parent_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'suppliers' => "CREATE TABLE IF NOT EXISTS `suppliers` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `contact_person` varchar(255) DEFAULT NULL,
                `phone` varchar(50) DEFAULT NULL,
                `email` varchar(255) DEFAULT NULL,
                `address` text,
                `payment_terms` varchar(50) NOT NULL DEFAULT 'Net 30',
                `balance_due` decimal(10,2) NOT NULL DEFAULT 0.00,
                `tax_id` varchar(100) DEFAULT NULL,
                `bank_details` text,
                `category` varchar(100) DEFAULT 'Hardware',
                `status` varchar(50) NOT NULL DEFAULT 'Active',
                `notes` text,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'products' => "CREATE TABLE IF NOT EXISTS `products` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `category_id` bigint unsigned DEFAULT NULL,
                `brand_id` bigint unsigned DEFAULT NULL,
                `product_code` varchar(50) NOT NULL,
                `ean` varchar(20) DEFAULT NULL,
                `upc` varchar(20) DEFAULT NULL,
                `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
                `selling_price` decimal(10,2) NOT NULL DEFAULT 0.00,
                `min_price` decimal(10,2) DEFAULT 0.00,
                `max_price` decimal(10,2) DEFAULT 0.00,
                `warranty_months` int NOT NULL DEFAULT 0,
                `reorder_level` int NOT NULL DEFAULT 5,
                `unit` varchar(20) NOT NULL DEFAULT 'pcs',
                `description` text,
                `image` varchar(255) DEFAULT NULL,
                `status` varchar(50) NOT NULL DEFAULT 'Active',
                `sub_category` varchar(100) DEFAULT NULL,
                `model_number` varchar(100) DEFAULT NULL,
                `specifications` text,
                `unit_of_measure` varchar(50) NOT NULL DEFAULT 'pcs',
                `wholesale_price` decimal(10,2) NOT NULL DEFAULT 0.00,
                `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
                `location` varchar(100) DEFAULT 'Main Shelf',
                `is_bundle` tinyint(1) NOT NULL DEFAULT 0,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`), UNIQUE KEY `product_code` (`product_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'purchases' => "CREATE TABLE IF NOT EXISTS `purchases` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `supplier_id` bigint unsigned NOT NULL,
                `invoice_no` varchar(100) NOT NULL,
                `purchase_date` date NOT NULL,
                `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
                `status` varchar(50) NOT NULL DEFAULT 'Draft',
                `expected_delivery_date` date DEFAULT NULL,
                `shipping_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
                `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
                `notes` text,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`), KEY `supplier_id` (`supplier_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'purchase_items' => "CREATE TABLE IF NOT EXISTS `purchase_items` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `purchase_id` bigint unsigned NOT NULL,
                `product_id` bigint unsigned NOT NULL,
                `quantity` int NOT NULL,
                `unit_cost` decimal(10,2) NOT NULL,
                `received_quantity` int NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`), KEY `purchase_id` (`purchase_id`), KEY `product_id` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'purchase_returns' => "CREATE TABLE IF NOT EXISTS `purchase_returns` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `return_no` varchar(50) NOT NULL,
                `supplier_id` bigint unsigned NOT NULL,
                `product_id` bigint unsigned NOT NULL,
                `serial_number` varchar(100) DEFAULT NULL,
                `quantity` int NOT NULL DEFAULT 1,
                `refund_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
                `reason` varchar(255) DEFAULT NULL,
                `refund_type` varchar(50) NOT NULL DEFAULT 'Credit Note',
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`), UNIQUE KEY `return_no` (`return_no`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'product_serials' => "CREATE TABLE IF NOT EXISTS `product_serials` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `product_id` bigint unsigned NOT NULL,
                `serial_number` varchar(100) NOT NULL,
                `purchase_id` bigint unsigned DEFAULT NULL,
                `status` varchar(50) NOT NULL DEFAULT 'in_stock',
                `warranty_start_date` date DEFAULT NULL,
                `warranty_end_date` date DEFAULT NULL,
                `branch_id` int DEFAULT 1,
                `sale_id` bigint unsigned DEFAULT NULL,
                `notes` text,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`), UNIQUE KEY `serial_number` (`serial_number`), KEY `product_id` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'sales' => "CREATE TABLE IF NOT EXISTS `sales` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `customer_id` bigint unsigned DEFAULT NULL,
                `invoice_no` varchar(100) NOT NULL,
                `sale_date` datetime NOT NULL,
                `total_amount` decimal(12,2) NOT NULL,
                `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
                `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
                `payment_method` varchar(50) NOT NULL DEFAULT 'Cash',
                `status` varchar(50) NOT NULL DEFAULT 'Completed',
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`), UNIQUE KEY `invoice_no` (`invoice_no`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'sale_items' => "CREATE TABLE IF NOT EXISTS `sale_items` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `sale_id` bigint unsigned NOT NULL,
                `product_id` bigint unsigned NOT NULL,
                `product_serial_id` bigint unsigned DEFAULT NULL,
                `quantity` int NOT NULL,
                `unit_price` decimal(10,2) NOT NULL,
                PRIMARY KEY (`id`), KEY `sale_id` (`sale_id`), KEY `product_id` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'product_bundles' => "CREATE TABLE IF NOT EXISTS `product_bundles` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `bundle_product_id` bigint unsigned NOT NULL,
                `component_product_id` bigint unsigned NOT NULL,
                `quantity` int NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'stock_movements' => "CREATE TABLE IF NOT EXISTS `stock_movements` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `product_id` bigint unsigned NOT NULL,
                `serial_number` varchar(100) DEFAULT NULL,
                `movement_type` varchar(50) NOT NULL,
                `quantity` int NOT NULL DEFAULT 1,
                `reason` varchar(255) DEFAULT NULL,
                `reference_id` varchar(100) DEFAULT NULL,
                `user_id` bigint unsigned DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`), KEY `product_id` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'settings' => "CREATE TABLE IF NOT EXISTS `settings` (
                `setting_key` varchar(100) NOT NULL,
                `setting_value` text,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'repair_jobs' => "CREATE TABLE IF NOT EXISTS `repair_jobs` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `ticket_no` varchar(50) NOT NULL,
                `customer_id` bigint unsigned NOT NULL,
                `device_type` varchar(100) NOT NULL DEFAULT 'Laptop',
                `device_brand` varchar(100) DEFAULT NULL,
                `device_model` varchar(100) DEFAULT NULL,
                `serial_number` varchar(100) DEFAULT NULL,
                `passcode_pin` varchar(50) DEFAULT NULL,
                `issue_description` text NOT NULL,
                `diagnosis_notes` text,
                `accessories_included` varchar(255) DEFAULT NULL,
                `technician_id` bigint unsigned DEFAULT NULL,
                `status` varchar(50) NOT NULL DEFAULT 'Received',
                `estimated_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
                `labor_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
                `parts_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
                `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
                `is_quote_approved` tinyint(1) NOT NULL DEFAULT 0,
                `warranty_days` int NOT NULL DEFAULT 30,
                `public_token` varchar(64) NOT NULL,
                `received_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `delivered_date` datetime DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`), UNIQUE KEY `ticket_no` (`ticket_no`), UNIQUE KEY `public_token` (`public_token`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'repair_parts_used' => "CREATE TABLE IF NOT EXISTS `repair_parts_used` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `repair_job_id` bigint unsigned NOT NULL,
                `product_id` bigint unsigned NOT NULL,
                `product_serial_id` bigint unsigned DEFAULT NULL,
                `quantity` int NOT NULL DEFAULT 1,
                `unit_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
                `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`), KEY `repair_job_id` (`repair_job_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'warranty_claims' => "CREATE TABLE IF NOT EXISTS `warranty_claims` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `claim_no` varchar(50) NOT NULL,
                `product_serial_id` bigint unsigned NOT NULL,
                `customer_id` bigint unsigned NOT NULL,
                `claim_date` date NOT NULL,
                `issue` text NOT NULL,
                `claim_type` varchar(50) NOT NULL DEFAULT 'In-House Repair',
                `status` varchar(50) NOT NULL DEFAULT 'Pending',
                `resolution` text,
                `created_by` bigint unsigned DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`), UNIQUE KEY `claim_no` (`claim_no`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'expenses' => "CREATE TABLE IF NOT EXISTS `expenses` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `category` varchar(100) NOT NULL DEFAULT 'General',
                `title` varchar(255) NOT NULL,
                `amount` decimal(10,2) NOT NULL,
                `payment_method` varchar(50) NOT NULL DEFAULT 'Cash',
                `expense_date` date NOT NULL,
                `notes` text,
                `receipt_file` varchar(255) DEFAULT NULL,
                `created_by` bigint unsigned DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'cash_registers' => "CREATE TABLE IF NOT EXISTS `cash_registers` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `user_id` bigint unsigned NOT NULL,
                `opening_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `closing_time` datetime DEFAULT NULL,
                `opening_cash` decimal(10,2) NOT NULL DEFAULT 0.00,
                `closing_cash_actual` decimal(10,2) DEFAULT NULL,
                `closing_cash_system` decimal(10,2) DEFAULT NULL,
                `cash_difference` decimal(10,2) DEFAULT NULL,
                `notes` text,
                `status` varchar(20) NOT NULL DEFAULT 'Open',
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`), KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'activity_logs' => "CREATE TABLE IF NOT EXISTS `activity_logs` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `user_id` bigint unsigned DEFAULT NULL,
                `action` varchar(255) NOT NULL,
                `module` varchar(100) DEFAULT NULL,
                `table_name` varchar(100) DEFAULT NULL,
                `record_id` bigint unsigned DEFAULT NULL,
                `details` text,
                `ip_address` varchar(50) DEFAULT NULL,
                `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`), KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($createStatements as $table => $sql) {
            $existed = $tableExists($table);
            $pdo->exec($sql);
            if (!$existed) {
                $changes[] = "Created table {$table}";
            }
        }

        $columns = [
            'users' => [
                'phone' => 'varchar(50) DEFAULT NULL',
                'branch_id' => 'int NOT NULL DEFAULT 1',
            ],
            'customers' => [
                'company_name' => 'varchar(255) DEFAULT NULL',
                'customer_type' => "varchar(50) NOT NULL DEFAULT 'Individual'",
                'credit_limit' => 'decimal(10,2) NOT NULL DEFAULT 0.00',
                'store_credit' => 'decimal(10,2) NOT NULL DEFAULT 0.00',
                'points' => 'int NOT NULL DEFAULT 0',
            ],
            'products' => [
                'ean' => 'varchar(20) DEFAULT NULL',
                'upc' => 'varchar(20) DEFAULT NULL',
                'min_price' => 'decimal(10,2) DEFAULT 0.00',
                'max_price' => 'decimal(10,2) DEFAULT 0.00',
                'sub_category' => 'varchar(100) DEFAULT NULL',
                'model_number' => 'varchar(100) DEFAULT NULL',
                'specifications' => 'text DEFAULT NULL',
                'unit_of_measure' => "varchar(50) NOT NULL DEFAULT 'pcs'",
                'wholesale_price' => 'decimal(10,2) NOT NULL DEFAULT 0.00',
                'tax_rate' => 'decimal(5,2) NOT NULL DEFAULT 0.00',
                'location' => "varchar(100) DEFAULT 'Main Shelf'",
                'is_bundle' => 'tinyint(1) NOT NULL DEFAULT 0',
            ],
            'product_serials' => [
                'purchase_id' => 'bigint unsigned DEFAULT NULL',
                'warranty_start_date' => 'date DEFAULT NULL',
                'warranty_end_date' => 'date DEFAULT NULL',
                'branch_id' => 'int DEFAULT 1',
                'sale_id' => 'bigint unsigned DEFAULT NULL',
                'notes' => 'text DEFAULT NULL',
                'updated_at' => 'timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            ],
            'suppliers' => [
                'payment_terms' => "varchar(50) NOT NULL DEFAULT 'Net 30'",
                'balance_due' => 'decimal(10,2) NOT NULL DEFAULT 0.00',
                'tax_id' => 'varchar(100) DEFAULT NULL',
                'bank_details' => 'text DEFAULT NULL',
                'category' => "varchar(100) DEFAULT 'Hardware'",
                'status' => "varchar(50) NOT NULL DEFAULT 'Active'",
                'notes' => 'text DEFAULT NULL',
            ],
            'purchases' => [
                'expected_delivery_date' => 'date DEFAULT NULL',
                'shipping_cost' => 'decimal(10,2) NOT NULL DEFAULT 0.00',
                'tax_rate' => 'decimal(5,2) NOT NULL DEFAULT 0.00',
                'notes' => 'text DEFAULT NULL',
            ],
            'purchase_items' => [
                'received_quantity' => 'int NOT NULL DEFAULT 0',
            ],
            'repair_jobs' => [
                'ticket_no' => 'varchar(50) DEFAULT NULL',
                'device_type' => "varchar(100) NOT NULL DEFAULT 'Laptop'",
                'device_brand' => 'varchar(100) DEFAULT NULL',
                'device_model' => 'varchar(100) DEFAULT NULL',
                'passcode_pin' => 'varchar(50) DEFAULT NULL',
                'diagnosis_notes' => 'text DEFAULT NULL',
                'accessories_included' => 'varchar(255) DEFAULT NULL',
                'estimated_cost' => 'decimal(10,2) NOT NULL DEFAULT 0.00',
                'labor_fee' => 'decimal(10,2) NOT NULL DEFAULT 0.00',
                'parts_cost' => 'decimal(10,2) NOT NULL DEFAULT 0.00',
                'total_amount' => 'decimal(10,2) NOT NULL DEFAULT 0.00',
                'is_quote_approved' => 'tinyint(1) NOT NULL DEFAULT 0',
                'warranty_days' => 'int NOT NULL DEFAULT 30',
                'public_token' => 'varchar(64) DEFAULT NULL',
            ],
            'repair_parts_used' => [
                'unit_cost' => 'decimal(10,2) NOT NULL DEFAULT 0.00',
                'unit_price' => 'decimal(10,2) NOT NULL DEFAULT 0.00',
                'created_at' => 'timestamp NULL DEFAULT CURRENT_TIMESTAMP',
            ],
            'warranty_claims' => [
                'claim_no' => 'varchar(50) DEFAULT NULL',
                'claim_type' => "varchar(50) NOT NULL DEFAULT 'In-House Repair'",
                'created_by' => 'bigint unsigned DEFAULT NULL',
            ],
            'activity_logs' => [
                'module' => 'varchar(100) DEFAULT NULL',
                'details' => 'text DEFAULT NULL',
                'ip_address' => 'varchar(50) DEFAULT NULL',
            ],
        ];

        foreach ($columns as $table => $definitions) {
            foreach ($definitions as $column => $definition) {
                if (!$columnExists($table, $column)) {
                    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
                    $changes[] = "Added {$table}.{$column}";
                }
            }
        }

        // Brand is optional in the product forms and therefore must accept NULL.
        $pdo->exec('ALTER TABLE `products` MODIFY COLUMN `brand_id` bigint unsigned NULL');

        // Preserve barcodes created by the early setup script.
        if ($columnExists('products', 'barcode')) {
            $pdo->exec("UPDATE products SET ean = barcode WHERE (ean IS NULL OR ean = '') AND barcode IS NOT NULL");
        }

        // Normalize flexible statuses before expanding legacy enum values.
        $pdo->exec("ALTER TABLE `product_serials` MODIFY COLUMN `status` varchar(50) NOT NULL DEFAULT 'in_stock'");
        $pdo->exec("ALTER TABLE `purchases` MODIFY COLUMN `status` varchar(50) NOT NULL DEFAULT 'Draft'");

        if ($columnExists('repair_jobs', 'device_name')) {
            $pdo->exec('ALTER TABLE `repair_jobs` MODIFY COLUMN `device_name` varchar(255) NULL');
        }
        $pdo->exec("ALTER TABLE `repair_jobs` MODIFY COLUMN `status` varchar(50) NOT NULL DEFAULT 'Received'");
        $pdo->exec("UPDATE repair_jobs SET status = 'In Repair' WHERE status = 'In Progress'");
        $pdo->exec("UPDATE repair_jobs SET status = 'Closed' WHERE status = 'Delivered'");
        $pdo->exec("UPDATE repair_jobs SET ticket_no = CONCAT('RPR-MIG-', id) WHERE ticket_no IS NULL OR ticket_no = ''");
        $pdo->exec("UPDATE repair_jobs SET public_token = SHA2(CONCAT('repair-', id, '-', COALESCE(created_at, NOW())), 256) WHERE public_token IS NULL OR public_token = ''");
        $pdo->exec("ALTER TABLE `repair_jobs` MODIFY COLUMN `ticket_no` varchar(50) NOT NULL");
        $pdo->exec("ALTER TABLE `repair_jobs` MODIFY COLUMN `public_token` varchar(64) NOT NULL");
        if (!$indexExists('repair_jobs', 'ticket_no')) {
            $pdo->exec('ALTER TABLE `repair_jobs` ADD UNIQUE KEY `ticket_no` (`ticket_no`)');
            $changes[] = 'Added repair ticket index';
        }
        if (!$indexExists('repair_jobs', 'public_token')) {
            $pdo->exec('ALTER TABLE `repair_jobs` ADD UNIQUE KEY `public_token` (`public_token`)');
            $changes[] = 'Added repair public-token index';
        }

        $pdo->exec("ALTER TABLE `warranty_claims` MODIFY COLUMN `status` varchar(50) NOT NULL DEFAULT 'Pending'");
        $pdo->exec("UPDATE warranty_claims SET claim_no = CONCAT('RMA-MIG-', id) WHERE claim_no IS NULL OR claim_no = ''");
        $pdo->exec("ALTER TABLE `warranty_claims` MODIFY COLUMN `claim_no` varchar(50) NOT NULL");
        if (!$indexExists('warranty_claims', 'claim_no')) {
            $pdo->exec('ALTER TABLE `warranty_claims` ADD UNIQUE KEY `claim_no` (`claim_no`)');
            $changes[] = 'Added warranty claim index';
        }

        $defaults = [
            'shop_name' => 'Tech Solutions Inc.',
            'shop_address' => '123 Main Street, Colombo 01',
            'shop_phone' => '+94 77 123 4567',
            'shop_email' => 'info@techsolutions.lk',
            'tax_rate' => '0',
            'currency_symbol' => 'Rs.',
            'bill_footer_message' => 'Thank you for shopping with us!',
            'return_policy_days' => '7',
            'receipt_printer_width' => '80mm',
            'system_timezone' => 'Asia/Colombo',
            'system_name' => 'TechShop',
            'maintenance_mode' => '0',
            'shop_disabled' => '0',
        ];
        $settingStatement = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)');
        foreach ($defaults as $key => $value) {
            $settingStatement->execute([$key, $value]);
        }

        return $changes;
    }
}
