-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.0.46 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.21.0.7344
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for csms_db
CREATE DATABASE IF NOT EXISTS `csms_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `csms_db`;

-- Dumping structure for table csms_db.activity_logs
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_id` bigint unsigned DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `module` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.activity_logs: ~23 rows (approximately)
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `timestamp`, `module`, `details`) VALUES
	(1, NULL, 'Create PO PO-260816-560 ($ 6,000.00)', 'purchases', 3, '2026-08-16 11:13:18', NULL, NULL),
	(2, NULL, 'Create PO', 'purchases', 6, '2026-08-16 11:24:13', 'Purchasing', 'Created PO-260816-562 for Total: $ 25,200.00'),
	(4, 5, 'Live Setting Toggle', 'settings', NULL, '2026-08-16 13:28:43', 'System', 'SuperAdmin live toggled maintenance_mode to 0'),
	(5, 5, 'Live Setting Toggle', 'settings', NULL, '2026-08-16 13:29:05', 'System', 'SuperAdmin live toggled shop_disabled to 1'),
	(6, 5, 'Live Setting Toggle', 'settings', NULL, '2026-08-16 13:45:53', 'System', 'SuperAdmin live toggled shop_disabled to 0'),
	(7, 5, 'Live Setting Toggle', 'settings', NULL, '2026-08-16 14:08:02', 'System', 'SuperAdmin live toggled shop_disabled to 1'),
	(8, 5, 'Live Setting Toggle', 'settings', NULL, '2026-08-16 14:08:51', 'System', 'SuperAdmin live toggled shop_disabled to 0'),
	(9, 5, 'Live Setting Toggle', 'settings', NULL, '2026-08-17 07:00:16', 'System', 'SuperAdmin live toggled shop_disabled to 1'),
	(10, 5, 'Live Setting Toggle', 'settings', NULL, '2026-08-17 07:00:53', 'System', 'SuperAdmin live toggled shop_disabled to 0'),
	(11, 5, 'Live Setting Toggle', 'settings', NULL, '2026-08-17 07:01:03', 'System', 'SuperAdmin live toggled maintenance_mode to 1'),
	(12, 5, 'Live Setting Toggle', 'settings', NULL, '2026-08-17 07:01:22', 'System', 'SuperAdmin live toggled maintenance_mode to 0'),
	(13, 5, 'Live Setting Toggle', 'settings', NULL, '2026-08-18 04:06:44', 'System', 'SuperAdmin live toggled maintenance_mode to 1'),
	(14, 5, 'Live Setting Toggle', 'settings', NULL, '2026-08-18 04:06:53', 'System', 'SuperAdmin live toggled maintenance_mode to 0'),
	(15, 5, 'Live Setting Toggle', 'settings', NULL, '2026-08-18 04:07:11', 'System', 'SuperAdmin live toggled shop_disabled_message to salli gewapan'),
	(16, 5, 'Live Setting Toggle', 'settings', NULL, '2026-08-18 04:07:11', 'System', 'SuperAdmin live toggled shop_disabled to 1'),
	(17, 5, 'Live Setting Toggle', 'settings', NULL, '2026-08-18 04:07:37', 'System', 'SuperAdmin live toggled shop_disabled to 0'),
	(18, NULL, 'Create PO', 'purchases', 9, '2026-08-18 04:16:35', 'Purchasing', 'Created PO-260818-726 for Total:  12,000.00'),
	(19, NULL, 'Create PO', 'purchases', 10, '2026-08-18 04:26:53', 'Purchasing', 'Created PO-260818-606 for Total:  5,000.00'),
	(20, NULL, 'Create PO', 'purchases', 11, '2026-08-18 05:20:40', 'Purchasing', 'Created PO-260818-872 for Total:  1,000.00'),
	(21, NULL, 'Create PO', 'purchases', 12, '2026-08-18 05:34:40', 'Purchasing', 'Created PO-260818-499 for Total:  1,000.00'),
	(22, NULL, 'Create PO', 'purchases', 13, '2026-08-18 06:06:30', 'Purchasing', 'Created PO-260818-446 for Total: Rs. 1,000.00'),
	(23, NULL, 'Create PO', 'purchases', 14, '2026-08-18 07:29:32', 'Purchasing', 'Created PO-260818-5088; total 6000'),
	(24, NULL, 'Create Repair Ticket', 'repair_jobs', 2, '2026-08-18 08:38:00', 'Repairs', 'Created ticket RPR-260818-9DAC49EC for 8oo uo');

-- Dumping structure for table csms_db.brands
CREATE TABLE IF NOT EXISTS `brands` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.brands: ~5 rows (approximately)
INSERT INTO `brands` (`id`, `name`, `created_at`, `updated_at`) VALUES
	(1, 'Dell', '2026-08-06 08:43:32', '2026-08-06 08:43:32'),
	(2, 'HP', '2026-08-06 08:43:32', '2026-08-06 08:43:32'),
	(3, 'Asus', '2026-08-06 08:43:32', '2026-08-06 08:43:32'),
	(4, 'Intel', '2026-08-06 08:43:32', '2026-08-06 08:43:32'),
	(5, 'Nvidia', '2026-08-06 08:43:32', '2026-08-06 08:43:32');

-- Dumping structure for table csms_db.categories
CREATE TABLE IF NOT EXISTS `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.categories: ~5 rows (approximately)
INSERT INTO `categories` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
	(1, 'Laptops', NULL, '2026-08-06 08:43:32', '2026-08-06 08:43:32'),
	(2, 'Monitors', NULL, '2026-08-06 08:43:32', '2026-08-06 08:43:32'),
	(3, 'Components', NULL, '2026-08-06 08:43:32', '2026-08-06 08:43:32'),
	(4, 'Processors', 3, '2026-08-06 08:43:32', '2026-08-06 08:43:32'),
	(5, 'Graphics Cards', 3, '2026-08-06 08:43:32', '2026-08-06 08:43:32');

-- Dumping structure for table csms_db.customers
CREATE TABLE IF NOT EXISTS `customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `points` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `company_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Individual',
  `credit_limit` decimal(10,2) NOT NULL DEFAULT '0.00',
  `store_credit` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.customers: ~3 rows (approximately)
INSERT INTO `customers` (`id`, `name`, `phone`, `email`, `address`, `points`, `created_at`, `updated_at`, `company_name`, `customer_type`, `credit_limit`, `store_credit`) VALUES
	(1, 'Walk-in Customer', '00000000', 'walkin@example.com', 'N/A', 0, '2026-08-06 08:43:32', '2026-08-06 08:43:32', NULL, 'Individual', 0.00, 0.00),
	(2, 'Alice Wonderland', '555-1234', 'alice@example.com', '123 Main St', 0, '2026-08-06 08:43:32', '2026-08-06 08:43:32', NULL, 'Individual', 0.00, 0.00),
	(3, 'Chamika sandeepa', '0761042162', 'infor.chamika@gmail.com', 'Alaehala,kiribathawila,baddegama\r\nAlaehala,kiribathawila,baddegama', 0, '2026-08-18 04:39:19', '2026-08-18 04:39:19', '', 'Individual', 0.00, 0.00);

-- Dumping structure for table csms_db.product_bundles
CREATE TABLE IF NOT EXISTS `product_bundles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bundle_product_id` bigint unsigned NOT NULL,
  `component_product_id` bigint unsigned NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table csms_db.product_bundles: ~0 rows (approximately)

-- Dumping structure for table csms_db.product_serials
CREATE TABLE IF NOT EXISTS `product_serials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `serial_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_id` bigint unsigned DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_stock',
  `warranty_start_date` date DEFAULT NULL,
  `warranty_end_date` date DEFAULT NULL,
  `branch_id` int DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `sale_id` bigint unsigned DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `serial_number` (`serial_number`),
  KEY `product_id` (`product_id`),
  KEY `purchase_id` (`purchase_id`),
  CONSTRAINT `product_serials_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `product_serials_ibfk_2` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.product_serials: ~79 rows (approximately)
INSERT INTO `product_serials` (`id`, `product_id`, `serial_number`, `purchase_id`, `status`, `warranty_start_date`, `warranty_end_date`, `branch_id`, `created_at`, `updated_at`, `sale_id`, `notes`) VALUES
	(1, 1, 'SN-DXPS15-001', 1, 'sold', NULL, NULL, 1, '2026-08-06 08:43:32', '2026-08-07 08:25:26', NULL, NULL),
	(2, 2, 'SN-ASVG248-001', 1, 'sold', NULL, NULL, 1, '2026-08-06 08:43:32', '2026-08-07 08:21:28', NULL, NULL),
	(3, 3, '566876876876', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:19:14', '2026-08-07 04:06:41', NULL, NULL),
	(4, 3, '87678678676', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:19:16', '2026-08-07 04:06:41', NULL, NULL),
	(5, 3, '876', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:19:17', '2026-08-07 08:25:26', NULL, NULL),
	(6, 3, '87687687687687687', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:19:20', '2026-08-07 08:25:26', NULL, NULL),
	(7, 3, '87687687687687', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:19:22', '2026-08-07 09:43:43', NULL, NULL),
	(8, 3, '876876876876', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:19:26', '2026-08-07 09:43:43', NULL, NULL),
	(9, 3, '5345345345', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:19:33', '2026-08-07 09:48:13', NULL, NULL),
	(10, 3, '534534534534', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:19:36', '2026-08-18 04:39:55', NULL, NULL),
	(11, 3, '345345345345', NULL, 'defective', NULL, NULL, 1, '2026-08-06 09:19:37', '2026-08-18 07:31:01', NULL, 'Physical Stock Take Discrepancy (Missing)'),
	(12, 3, 'tertert', NULL, 'defective', NULL, NULL, 1, '2026-08-06 09:20:53', '2026-08-18 07:31:01', NULL, 'Physical Stock Take Discrepancy (Missing)'),
	(13, 3, '4234234', NULL, 'defective', NULL, NULL, 1, '2026-08-06 09:20:56', '2026-08-18 07:31:01', NULL, 'Physical Stock Take Discrepancy (Missing)'),
	(14, 3, '2342342342342', NULL, 'defective', NULL, NULL, 1, '2026-08-06 09:20:58', '2026-08-18 07:31:01', NULL, 'Physical Stock Take Discrepancy (Missing)'),
	(15, 3, '4234234234234', NULL, 'defective', NULL, NULL, 1, '2026-08-06 09:21:00', '2026-08-18 07:31:01', NULL, 'Physical Stock Take Discrepancy (Missing)'),
	(16, 3, '2342342423423', NULL, 'defective', NULL, NULL, 1, '2026-08-06 09:21:02', '2026-08-18 07:31:01', NULL, 'Physical Stock Take Discrepancy (Missing)'),
	(17, 2, '58876876786876876', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:22:04', '2026-08-07 08:21:28', NULL, NULL),
	(18, 2, '564654544', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:22:17', '2026-08-07 08:25:26', NULL, NULL),
	(19, 2, '4454654', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:22:18', '2026-08-07 08:25:26', NULL, NULL),
	(20, 2, '5454654654', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:22:18', '2026-08-06 09:22:18', NULL, NULL),
	(21, 2, '64', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:22:19', '2026-08-06 09:22:19', NULL, NULL),
	(22, 2, '65465', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:22:19', '2026-08-06 09:22:19', NULL, NULL),
	(23, 2, '465', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:22:19', '2026-08-06 09:22:19', NULL, NULL),
	(24, 2, '4654', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:22:19', '2026-08-06 09:22:19', NULL, NULL),
	(25, 2, '65', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:22:19', '2026-08-06 09:22:19', NULL, NULL),
	(26, 2, '6546', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:22:20', '2026-08-06 09:22:20', NULL, NULL),
	(27, 2, '54', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:22:20', '2026-08-06 09:22:20', NULL, NULL),
	(28, 2, '654', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:22:20', '2026-08-06 09:22:20', NULL, NULL),
	(29, 3, '5869869876986', NULL, 'defective', NULL, NULL, 1, '2026-08-07 03:39:29', '2026-08-18 07:31:01', NULL, 'Physical Stock Take Discrepancy (Missing)'),
	(30, 3, '876876786876', NULL, 'defective', NULL, NULL, 1, '2026-08-07 03:39:31', '2026-08-18 07:31:01', NULL, 'Physical Stock Take Discrepancy (Missing)'),
	(31, 3, '876786786', NULL, 'defective', NULL, NULL, 1, '2026-08-07 03:39:32', '2026-08-18 07:31:01', NULL, 'Physical Stock Take Discrepancy (Missing)'),
	(32, 3, '98+98+89+', NULL, 'defective', NULL, NULL, 1, '2026-08-07 03:39:48', '2026-08-18 07:31:01', NULL, 'Physical Stock Take Discrepancy (Missing)'),
	(33, 3, 'asaasas', NULL, 'defective', NULL, NULL, 1, '2026-08-07 03:47:52', '2026-08-18 07:31:01', NULL, 'Physical Stock Take Discrepancy (Missing)'),
	(34, 3, 'tyjuyjuyjuy', NULL, 'defective', NULL, NULL, 1, '2026-08-07 03:49:02', '2026-08-18 07:31:01', NULL, 'Physical Stock Take Discrepancy (Missing)'),
	(35, 3, 'tyjutyujtyju', NULL, 'defective', NULL, NULL, 1, '2026-08-07 03:49:03', '2026-08-18 07:31:01', NULL, 'Physical Stock Take Discrepancy (Missing)'),
	(36, 3, 'tyjutyu', NULL, 'defective', NULL, NULL, 1, '2026-08-07 03:49:04', '2026-08-18 07:31:01', NULL, 'Physical Stock Take Discrepancy (Missing)'),
	(37, 3, 'tyu', NULL, 'defective', NULL, NULL, 1, '2026-08-07 03:49:04', '2026-08-18 07:31:01', NULL, 'Physical Stock Take Discrepancy (Missing)'),
	(38, 3, 'ty', NULL, 'defective', NULL, NULL, 1, '2026-08-07 03:49:04', '2026-08-18 07:31:01', NULL, 'Physical Stock Take Discrepancy (Missing)'),
	(39, 3, 'u', NULL, 'defective', NULL, NULL, 1, '2026-08-07 03:49:04', '2026-08-18 07:31:01', NULL, 'Physical Stock Take Discrepancy (Missing)'),
	(40, 3, 'ut', NULL, 'sold', NULL, NULL, 1, '2026-08-07 03:49:05', '2026-08-18 07:32:26', 9, NULL),
	(41, 3, 'uyt', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 03:49:05', '2026-08-07 03:49:05', NULL, NULL),
	(42, 3, 'yu', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 03:49:06', '2026-08-07 03:49:06', NULL, NULL),
	(43, 3, 'tyju', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 03:49:06', '2026-08-07 03:49:06', NULL, NULL),
	(44, 3, 'rtretertert', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 03:55:29', '2026-08-07 03:55:29', NULL, NULL),
	(45, 3, '12321312', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 08:45:20', '2026-08-07 08:45:20', NULL, NULL),
	(46, 3, '21321313', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 08:45:22', '2026-08-07 08:45:22', NULL, NULL),
	(47, 3, '312312312', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 08:45:24', '2026-08-07 08:45:24', NULL, NULL),
	(48, 3, '3123123', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 08:45:25', '2026-08-07 08:45:25', NULL, NULL),
	(49, 3, 'dan', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 08:45:31', '2026-08-07 08:45:31', NULL, NULL),
	(51, 2, '546544648654', 3, 'in_stock', NULL, NULL, 1, '2026-08-16 11:20:54', '2026-08-16 11:20:54', NULL, NULL),
	(52, 2, '465465464654', 3, 'in_stock', NULL, NULL, 1, '2026-08-16 11:20:54', '2026-08-16 11:20:54', NULL, NULL),
	(53, 2, '54564544654', 3, 'in_stock', NULL, NULL, 1, '2026-08-16 11:20:54', '2026-08-16 11:20:54', NULL, NULL),
	(54, 1, '65445654654', 6, 'in_stock', NULL, NULL, 1, '2026-08-16 11:24:48', '2026-08-16 11:24:48', NULL, NULL),
	(55, 1, 'BATCH-260816-2-8429', 2, 'in_stock', NULL, NULL, 1, '2026-08-16 11:29:33', '2026-08-16 11:29:33', NULL, NULL),
	(56, 1, 'BATCH-260816-2-7146', 2, 'in_stock', NULL, NULL, 1, '2026-08-16 11:29:33', '2026-08-16 11:29:33', NULL, NULL),
	(57, 1, 'BATCH-260816-2-9198', 2, 'in_stock', NULL, NULL, 1, '2026-08-16 11:29:33', '2026-08-16 11:29:33', NULL, NULL),
	(58, 1, 'BATCH-260816-2-7366', 2, 'in_stock', NULL, NULL, 1, '2026-08-16 11:29:33', '2026-08-16 11:29:33', NULL, NULL),
	(59, 1, 'BATCH-260816-2-8846', 2, 'in_stock', NULL, NULL, 1, '2026-08-16 11:29:33', '2026-08-16 11:29:33', NULL, NULL),
	(65, 1, '545454545', 9, 'in_stock', NULL, NULL, 1, '2026-08-18 04:19:29', '2026-08-18 04:19:29', NULL, NULL),
	(66, 1, '454565465', 9, 'in_stock', NULL, NULL, 1, '2026-08-18 04:19:29', '2026-08-18 04:19:29', NULL, NULL),
	(67, 1, '5445565465', 9, 'in_stock', NULL, NULL, 1, '2026-08-18 04:19:29', '2026-08-18 04:19:29', NULL, NULL),
	(68, 1, '4554564654', 9, 'in_stock', NULL, NULL, 1, '2026-08-18 04:19:29', '2026-08-18 04:19:29', NULL, NULL),
	(69, 1, '54456665456', 9, 'in_stock', NULL, NULL, 1, '2026-08-18 04:19:29', '2026-08-18 04:19:29', NULL, NULL),
	(70, 1, '97979796', 9, 'in_stock', NULL, NULL, 1, '2026-08-18 04:28:00', '2026-08-18 04:28:00', NULL, NULL),
	(71, 1, '779879879', 9, 'in_stock', NULL, NULL, 1, '2026-08-18 04:28:00', '2026-08-18 04:28:00', NULL, NULL),
	(72, 1, '987987797', 9, 'in_stock', NULL, NULL, 1, '2026-08-18 04:28:00', '2026-08-18 04:28:00', NULL, NULL),
	(73, 1, '9797979', 9, 'in_stock', NULL, NULL, 1, '2026-08-18 04:28:00', '2026-08-18 04:28:00', NULL, NULL),
	(74, 1, '87897', 9, 'in_stock', NULL, NULL, 1, '2026-08-18 04:28:00', '2026-08-18 04:28:00', NULL, NULL),
	(75, 1, 'payya', 10, 'in_stock', NULL, NULL, 1, '2026-08-18 04:35:10', '2026-08-18 04:35:10', NULL, NULL),
	(76, 3, 'STAKE-FOUND-260818-6416', NULL, 'in_stock', NULL, NULL, 1, '2026-08-18 07:31:52', '2026-08-18 07:31:52', NULL, NULL),
	(77, 3, 'STAKE-FOUND-260818-6960', NULL, 'in_stock', NULL, NULL, 1, '2026-08-18 07:31:52', '2026-08-18 07:31:52', NULL, NULL),
	(78, 3, 'STAKE-FOUND-260818-4159', NULL, 'in_stock', NULL, NULL, 1, '2026-08-18 07:31:52', '2026-08-18 07:31:52', NULL, NULL),
	(79, 3, 'STAKE-FOUND-260818-6473', NULL, 'in_stock', NULL, NULL, 1, '2026-08-18 07:31:52', '2026-08-18 07:31:52', NULL, NULL),
	(80, 3, 'STAKE-FOUND-260818-3463', NULL, 'in_stock', NULL, NULL, 1, '2026-08-18 07:31:52', '2026-08-18 07:31:52', NULL, NULL),
	(81, 3, 'STAKE-FOUND-260818-7838', NULL, 'in_stock', NULL, NULL, 1, '2026-08-18 07:31:52', '2026-08-18 07:31:52', NULL, NULL),
	(82, 3, 'STAKE-FOUND-260818-6753', NULL, 'in_stock', NULL, NULL, 1, '2026-08-18 07:31:52', '2026-08-18 07:31:52', NULL, NULL),
	(83, 3, 'STAKE-FOUND-260818-9150', NULL, 'in_stock', NULL, NULL, 1, '2026-08-18 07:31:52', '2026-08-18 07:31:52', NULL, NULL),
	(84, 3, 'STAKE-FOUND-260818-4470', NULL, 'in_stock', NULL, NULL, 1, '2026-08-18 07:31:52', '2026-08-18 07:31:52', NULL, NULL),
	(85, 3, 'STAKE-FOUND-260818-3141', NULL, 'in_stock', NULL, NULL, 1, '2026-08-18 07:31:52', '2026-08-18 07:31:52', NULL, NULL);

-- Dumping structure for table csms_db.products
CREATE TABLE IF NOT EXISTS `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` bigint unsigned NOT NULL,
  `brand_id` bigint unsigned NOT NULL,
  `product_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ean` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upc` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `selling_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `min_price` decimal(10,2) DEFAULT '0.00',
  `max_price` decimal(10,2) DEFAULT '0.00',
  `warranty_months` int NOT NULL DEFAULT '0',
  `reorder_level` int NOT NULL DEFAULT '5',
  `unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pcs',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Active','Discontinued') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `sub_category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `specifications` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `unit_of_measure` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pcs',
  `wholesale_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `location` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Main Shelf',
  `is_bundle` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_code` (`product_code`),
  KEY `category_id` (`category_id`),
  KEY `brand_id` (`brand_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `products_ibfk_2` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.products: ~3 rows (approximately)
INSERT INTO `products` (`id`, `name`, `category_id`, `brand_id`, `product_code`, `ean`, `upc`, `cost_price`, `selling_price`, `min_price`, `max_price`, `warranty_months`, `reorder_level`, `unit`, `description`, `image`, `status`, `created_at`, `updated_at`, `sub_category`, `model_number`, `specifications`, `unit_of_measure`, `wholesale_price`, `tax_rate`, `location`, `is_bundle`) VALUES
	(1, 'Dell XPS 15', 1, 1, 'LT-DXPS15', '1234567890123', '123456789012', 1200.00, 1500.00, 0.00, 0.00, 12, 2, 'pcs', NULL, NULL, 'Active', '2026-08-06 08:43:32', '2026-08-06 08:43:32', NULL, NULL, NULL, 'pcs', 0.00, 0.00, 'Main Shelf', 0),
	(2, 'Asus VG248QE 24" Monitor', 2, 3, 'MN-ASVG248', '2234567890123', '223456789012', 200.00, 250.00, 0.00, 0.00, 24, 5, 'pcs', NULL, NULL, 'Active', '2026-08-06 08:43:32', '2026-08-06 08:43:32', NULL, NULL, NULL, 'pcs', 0.00, 0.00, 'Main Shelf', 0),
	(3, 'Intel Core i7-13700K', 4, 4, 'CP-I713700K', '3234567890123', '323456789012', 350.00, 420.00, 400.00, 500.00, 36, 10, 'pcs', NULL, NULL, 'Active', '2026-08-06 08:43:32', '2026-08-07 09:42:23', NULL, NULL, NULL, 'pcs', 0.00, 0.00, 'Main Shelf', 0);

-- Dumping structure for table csms_db.purchase_items
CREATE TABLE IF NOT EXISTS `purchase_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `quantity` int NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `received_quantity` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `purchase_id` (`purchase_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `purchase_items_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.purchase_items: ~11 rows (approximately)
INSERT INTO `purchase_items` (`id`, `purchase_id`, `product_id`, `quantity`, `unit_cost`, `received_quantity`) VALUES
	(1, 1, 1, 1, 1200.00, 0),
	(2, 1, 2, 1, 200.00, 0),
	(3, 2, 1, 5, 1200.00, 0),
	(4, 3, 1, 5, 1200.00, 0),
	(7, 6, 1, 21, 1200.00, 0),
	(9, 9, 1, 10, 1200.00, 10),
	(10, 10, 1, 5, 1000.00, 1),
	(11, 11, 2, 5, 200.00, 0),
	(12, 12, 2, 5, 200.00, 0),
	(13, 13, 2, 5, 200.00, 0),
	(14, 14, 1, 5, 1200.00, 0);

-- Dumping structure for table csms_db.purchase_returns
CREATE TABLE IF NOT EXISTS `purchase_returns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `return_no` varchar(50) NOT NULL,
  `supplier_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `refund_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `reason` varchar(255) DEFAULT NULL,
  `refund_type` varchar(50) NOT NULL DEFAULT 'Credit Note',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `return_no` (`return_no`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table csms_db.purchase_returns: ~1 rows (approximately)
INSERT INTO `purchase_returns` (`id`, `return_no`, `supplier_id`, `product_id`, `serial_number`, `quantity`, `refund_amount`, `reason`, `refund_type`, `created_at`) VALUES
	(3, 'PR-260816-695', 1, 1, '', 1, 0.00, 'Defective on arrival (DOA)', 'Replacement', '2026-08-16 11:22:03');

-- Dumping structure for table csms_db.purchases
CREATE TABLE IF NOT EXISTS `purchases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint unsigned NOT NULL,
  `invoice_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_date` date NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Draft',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expected_delivery_date` date DEFAULT NULL,
  `shipping_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.purchases: ~11 rows (approximately)
INSERT INTO `purchases` (`id`, `supplier_id`, `invoice_no`, `purchase_date`, `total_amount`, `status`, `created_at`, `updated_at`, `expected_delivery_date`, `shipping_cost`, `tax_rate`, `notes`) VALUES
	(1, 1, 'INV-2023-001', '2023-10-01', 1400.00, 'Completed', '2026-08-06 08:43:32', '2026-08-06 08:43:32', NULL, 0.00, 0.00, NULL),
	(2, 1, 'PO-260816-992', '2026-08-16', 6000.00, 'Partially Received', '2026-08-16 11:12:26', '2026-08-16 11:29:33', NULL, 0.00, 0.00, NULL),
	(3, 1, 'PO-260816-560', '2026-08-16', 6000.00, 'Received', '2026-08-16 11:13:18', '2026-08-16 11:20:54', NULL, 0.00, 0.00, NULL),
	(6, 1, 'PO-260816-562', '2026-08-16', 25200.00, 'Received', '2026-08-16 11:24:13', '2026-08-16 11:24:48', NULL, 0.00, 0.00, NULL),
	(7, 1, 'PO-VAL-1786879967', '2026-08-16', 6000.00, 'Sent to Supplier', '2026-08-16 11:32:47', '2026-08-16 11:32:47', NULL, 0.00, 0.00, NULL),
	(9, 2, 'PO-260818-726', '2026-08-18', 12000.00, 'Received', '2026-08-18 04:16:35', '2026-08-18 04:28:00', '2026-08-25', 0.00, 0.00, ''),
	(10, 2, 'PO-260818-606', '2026-08-18', 5000.00, 'Partially Received', '2026-08-18 04:26:53', '2026-08-18 04:35:10', '2026-08-25', 0.00, 0.00, 'kadenne nati wenna genna'),
	(11, 2, 'PO-260818-872', '2026-08-18', 1000.00, 'Approved', '2026-08-18 05:20:40', '2026-08-18 05:20:40', '2026-08-25', 0.00, 0.00, ''),
	(12, 1, 'PO-260818-499', '2026-08-18', 1000.00, 'Approved', '2026-08-18 05:34:40', '2026-08-18 05:34:40', '2026-08-25', 0.00, 0.00, ''),
	(13, 2, 'PO-260818-446', '2026-08-18', 1000.00, 'Approved', '2026-08-18 06:06:30', '2026-08-18 06:06:30', '2026-08-25', 0.00, 0.00, ''),
	(14, 2, 'PO-260818-5088', '2026-08-18', 6000.00, 'Approved', '2026-08-18 07:29:32', '2026-08-18 07:29:32', '2026-08-25', 0.00, 0.00, '');

-- Dumping structure for table csms_db.repair_jobs
CREATE TABLE IF NOT EXISTS `repair_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `device_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `serial_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `technician_id` bigint unsigned DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Received',
  `received_date` datetime DEFAULT NULL,
  `delivered_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ticket_no` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `device_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Other',
  `device_brand` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `device_model` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `passcode_pin` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accessories_included` text COLLATE utf8mb4_unicode_ci,
  `estimated_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `labor_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parts_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `diagnosis_notes` text COLLATE utf8mb4_unicode_ci,
  `is_quote_approved` tinyint(1) NOT NULL DEFAULT '0',
  `public_token` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_no` (`ticket_no`),
  KEY `customer_id` (`customer_id`),
  KEY `technician_id` (`technician_id`),
  CONSTRAINT `repair_jobs_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `repair_jobs_ibfk_2` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.repair_jobs: ~1 rows (approximately)
INSERT INTO `repair_jobs` (`id`, `customer_id`, `device_name`, `serial_number`, `issue_description`, `technician_id`, `status`, `received_date`, `delivered_date`, `created_at`, `updated_at`, `ticket_no`, `device_type`, `device_brand`, `device_model`, `passcode_pin`, `accessories_included`, `estimated_cost`, `total_amount`, `labor_fee`, `parts_cost`, `diagnosis_notes`, `is_quote_approved`, `public_token`) VALUES
	(2, 2, '', 'yuo', 'yuoyuo', NULL, 'Diagnosing', '2026-08-18 14:08:00', NULL, '2026-08-18 08:38:00', '2026-08-18 09:21:28', 'RPR-260818-9DAC49EC', 'Laptop', '8oo', 'uo', 'youoy', 'oyuo', 0.00, 0.00, 0.00, 0.00, '', 0, '32bc05a2d5870fdd4d9859a9671a5fb9a6382110f8133a6566c15b3ac216055e');

-- Dumping structure for table csms_db.repair_parts_used
CREATE TABLE IF NOT EXISTS `repair_parts_used` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `repair_job_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `product_serial_id` bigint unsigned DEFAULT NULL,
  `quantity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `repair_job_id` (`repair_job_id`),
  KEY `product_id` (`product_id`),
  KEY `product_serial_id` (`product_serial_id`),
  CONSTRAINT `repair_parts_used_ibfk_1` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `repair_parts_used_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `repair_parts_used_ibfk_3` FOREIGN KEY (`product_serial_id`) REFERENCES `product_serials` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.repair_parts_used: ~0 rows (approximately)

-- Dumping structure for table csms_db.role_permissions
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(120) NOT NULL,
  `role` varchar(40) NOT NULL,
  `access` varchar(10) NOT NULL DEFAULT '-',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_module_role` (`module`,`role`)
) ENGINE=InnoDB AUTO_INCREMENT=946 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table csms_db.role_permissions: ~63 rows (approximately)
INSERT INTO `role_permissions` (`id`, `module`, `role`, `access`) VALUES
	(1, 'System Infrastructure & APIs', 'SuperAdmin', 'F'),
	(2, 'System Infrastructure & APIs', 'Admin', '-'),
	(3, 'System Infrastructure & APIs', 'Manager', '-'),
	(4, 'System Infrastructure & APIs', 'Cashier', '-'),
	(5, 'System Infrastructure & APIs', 'Technician', '-'),
	(6, 'System Infrastructure & APIs', 'Inventory', '-'),
	(7, 'System Infrastructure & APIs', 'Accountant', '-'),
	(8, 'Database Backups & Schema Migrations', 'SuperAdmin', 'F'),
	(9, 'Database Backups & Schema Migrations', 'Admin', '-'),
	(10, 'Database Backups & Schema Migrations', 'Manager', '-'),
	(11, 'Database Backups & Schema Migrations', 'Cashier', '-'),
	(12, 'Database Backups & Schema Migrations', 'Technician', '-'),
	(13, 'Database Backups & Schema Migrations', 'Inventory', '-'),
	(14, 'Database Backups & Schema Migrations', 'Accountant', '-'),
	(15, 'User Impersonation & Security Override', 'SuperAdmin', 'F'),
	(16, 'User Impersonation & Security Override', 'Admin', '-'),
	(17, 'User Impersonation & Security Override', 'Manager', '-'),
	(18, 'User Impersonation & Security Override', 'Cashier', '-'),
	(19, 'User Impersonation & Security Override', 'Technician', '-'),
	(20, 'User Impersonation & Security Override', 'Inventory', '-'),
	(21, 'User Impersonation & Security Override', 'Accountant', '-'),
	(22, 'Dashboard / Business KPIs', 'SuperAdmin', 'F'),
	(23, 'Dashboard / Business KPIs', 'Admin', 'F'),
	(24, 'Dashboard / Business KPIs', 'Manager', 'F'),
	(25, 'Dashboard / Business KPIs', 'Cashier', 'V'),
	(26, 'Dashboard / Business KPIs', 'Technician', 'V'),
	(27, 'Dashboard / Business KPIs', 'Inventory', 'V'),
	(28, 'Dashboard / Business KPIs', 'Accountant', 'V'),
	(29, 'POS / Sales & Barcode Scanning', 'SuperAdmin', 'F'),
	(30, 'POS / Sales & Barcode Scanning', 'Admin', 'F'),
	(31, 'POS / Sales & Barcode Scanning', 'Manager', 'F'),
	(32, 'POS / Sales & Barcode Scanning', 'Cashier', 'E'),
	(33, 'POS / Sales & Barcode Scanning', 'Technician', '-'),
	(34, 'POS / Sales & Barcode Scanning', 'Inventory', '-'),
	(35, 'POS / Sales & Barcode Scanning', 'Accountant', 'V'),
	(36, 'Products / Inventory & Serials', 'SuperAdmin', 'F'),
	(37, 'Products / Inventory & Serials', 'Admin', 'F'),
	(38, 'Products / Inventory & Serials', 'Manager', 'F'),
	(39, 'Products / Inventory & Serials', 'Cashier', 'V'),
	(40, 'Products / Inventory & Serials', 'Technician', 'V'),
	(41, 'Products / Inventory & Serials', 'Inventory', 'F'),
	(42, 'Products / Inventory & Serials', 'Accountant', 'V'),
	(43, 'Repair & Service Jobs Workbench', 'SuperAdmin', 'F'),
	(44, 'Repair & Service Jobs Workbench', 'Admin', 'F'),
	(45, 'Repair & Service Jobs Workbench', 'Manager', 'F'),
	(46, 'Repair & Service Jobs Workbench', 'Cashier', 'V'),
	(47, 'Repair & Service Jobs Workbench', 'Technician', 'E'),
	(48, 'Repair & Service Jobs Workbench', 'Inventory', '-'),
	(49, 'Repair & Service Jobs Workbench', 'Accountant', 'V'),
	(50, 'Suppliers & Purchasing (POs/GRN)', 'SuperAdmin', 'F'),
	(51, 'Suppliers & Purchasing (POs/GRN)', 'Admin', 'F'),
	(52, 'Suppliers & Purchasing (POs/GRN)', 'Manager', 'E'),
	(53, 'Suppliers & Purchasing (POs/GRN)', 'Cashier', '-'),
	(54, 'Suppliers & Purchasing (POs/GRN)', 'Technician', '-'),
	(55, 'Suppliers & Purchasing (POs/GRN)', 'Inventory', '-'),
	(56, 'Suppliers & Purchasing (POs/GRN)', 'Accountant', 'V'),
	(57, 'Accounting, Expenses & Cash Drawer', 'SuperAdmin', 'F'),
	(58, 'Accounting, Expenses & Cash Drawer', 'Admin', 'F'),
	(59, 'Accounting, Expenses & Cash Drawer', 'Manager', 'V'),
	(60, 'Accounting, Expenses & Cash Drawer', 'Cashier', 'E'),
	(61, 'Accounting, Expenses & Cash Drawer', 'Technician', '-'),
	(62, 'Accounting, Expenses & Cash Drawer', 'Inventory', '-'),
	(63, 'Accounting, Expenses & Cash Drawer', 'Accountant', 'F');

-- Dumping structure for table csms_db.sale_items
CREATE TABLE IF NOT EXISTS `sale_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `product_serial_id` bigint unsigned DEFAULT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `product_id` (`product_id`),
  KEY `product_serial_id` (`product_serial_id`),
  CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `sale_items_ibfk_3` FOREIGN KEY (`product_serial_id`) REFERENCES `product_serials` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.sale_items: ~14 rows (approximately)
INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `product_serial_id`, `quantity`, `unit_price`) VALUES
	(1, 1, 3, 3, 1, 420.00),
	(2, 1, 3, 4, 1, 420.00),
	(7, 4, 2, 2, 1, 250.00),
	(8, 4, 2, 17, 1, 250.00),
	(9, 5, 2, 18, 1, 250.00),
	(10, 5, 2, 19, 1, 250.00),
	(11, 5, 1, 1, 1, 1500.00),
	(12, 5, 3, 5, 1, 420.00),
	(13, 5, 3, 6, 1, 420.00),
	(14, 6, 3, 7, 1, 500.00),
	(15, 6, 3, 8, 1, 500.00),
	(16, 7, 3, 9, 1, 420.00),
	(17, 8, 3, 10, 1, 420.00),
	(18, 9, 3, 40, 1, 420.00);

-- Dumping structure for table csms_db.sales
CREATE TABLE IF NOT EXISTS `sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned DEFAULT NULL,
  `invoice_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sale_date` datetime NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_method` enum('Cash','Card','Bank Transfer') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Cash',
  `status` enum('Completed','Refunded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Completed',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.sales: ~7 rows (approximately)
INSERT INTO `sales` (`id`, `customer_id`, `invoice_no`, `sale_date`, `total_amount`, `discount`, `tax`, `payment_method`, `status`, `created_at`, `updated_at`) VALUES
	(1, NULL, 'INV-20260807-040641-44', '2026-08-07 09:36:41', 924.00, 0.00, 84.00, 'Cash', 'Completed', '2026-08-07 04:06:41', '2026-08-07 04:06:41'),
	(4, NULL, 'INV-20260807-082128-83', '2026-08-07 13:51:28', 500.00, 0.00, 0.00, 'Cash', 'Completed', '2026-08-07 08:21:28', '2026-08-07 08:21:28'),
	(5, 1, 'INV-20260807-082526-19', '2026-08-07 13:55:26', 2840.00, 0.00, 0.00, 'Cash', 'Completed', '2026-08-07 08:25:26', '2026-08-07 08:25:26'),
	(6, NULL, 'INV-20260807-094343-94', '2026-08-07 15:13:43', 1000.00, 0.00, 0.00, 'Cash', 'Completed', '2026-08-07 09:43:43', '2026-08-07 09:43:43'),
	(7, NULL, 'INV-20260807-094813-44', '2026-08-07 15:18:13', 420.00, 0.00, 0.00, 'Cash', 'Completed', '2026-08-07 09:48:13', '2026-08-07 09:48:13'),
	(8, 3, 'INV-20260818-043955-34', '2026-08-18 10:09:55', 420.00, 0.00, 0.00, 'Cash', 'Completed', '2026-08-18 04:39:55', '2026-08-18 04:39:55'),
	(9, NULL, 'INV-20260818-073226-70', '2026-08-18 13:02:26', 420.00, 0.00, 0.00, 'Cash', 'Completed', '2026-08-18 07:32:26', '2026-08-18 07:32:26');

-- Dumping structure for table csms_db.settings
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.settings: ~30 rows (approximately)
INSERT INTO `settings` (`setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
	('app_debug', '0', '2026-08-16 12:25:37', '2026-08-16 12:25:37'),
	('bill_footer_message', 'Thank you for shopping with us! Goods once sold cannot be returned without the original receipt.', '2026-08-07 08:50:55', '2026-08-07 08:50:55'),
	('currency_symbol', 'Rs.', '2026-08-07 08:50:55', '2026-08-16 13:59:38'),
	('feature_accounting', '1', '2026-08-16 12:25:37', '2026-08-16 12:25:37'),
	('feature_custom_pc', '1', '2026-08-16 12:25:37', '2026-08-16 12:25:37'),
	('feature_multibranch', '1', '2026-08-16 12:25:37', '2026-08-16 12:25:37'),
	('feature_rma', '1', '2026-08-16 12:25:37', '2026-08-16 12:25:37'),
	('feature_serials', '1', '2026-08-16 12:25:37', '2026-08-16 12:25:37'),
	('feature_tracker', '1', '2026-08-16 12:25:37', '2026-08-16 12:25:37'),
	('maintenance_message', 'The system is undergoing scheduled technical maintenance and upgrades. Please check back shortly.', '2026-08-16 12:32:03', '2026-08-16 12:32:03'),
	('maintenance_mode', '0', '2026-08-16 12:25:37', '2026-08-18 04:06:53'),
	('receipt_printer_width', '80mm', '2026-08-07 08:50:55', '2026-08-07 08:50:55'),
	('return_policy_days', '7', '2026-08-07 08:50:55', '2026-08-07 08:50:55'),
	('shop_address', '123 Main Street, Colombo 01', '2026-08-07 08:50:55', '2026-08-07 08:50:55'),
	('shop_disabled', '0', '2026-08-16 12:29:32', '2026-08-18 04:07:37'),
	('shop_disabled_message', 'salli gewapan', '2026-08-16 12:32:03', '2026-08-18 04:07:11'),
	('shop_email', 'info@techsolutions.lk', '2026-08-07 08:50:55', '2026-08-07 08:50:55'),
	('shop_language', 'en', '2026-08-16 13:59:18', '2026-08-18 04:05:54'),
	('shop_name', 'Tech Solutions Inc.', '2026-08-07 08:50:55', '2026-08-07 08:50:55'),
	('shop_phone', '+94 77 123 4567', '2026-08-07 08:50:55', '2026-08-07 08:50:55'),
	('shop_tax_number', 'PV-98214', '2026-08-16 13:59:18', '2026-08-16 13:59:18'),
	('sms_api_key', 'AC_live_demo_key_89712', '2026-08-16 12:25:37', '2026-08-16 12:25:37'),
	('sms_api_token', 'â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢', '2026-08-16 12:25:37', '2026-08-16 12:25:37'),
	('smtp_crypto', 'tls', '2026-08-16 12:25:37', '2026-08-16 12:25:37'),
	('smtp_host', 'smtp.gmail.com', '2026-08-16 12:25:37', '2026-08-16 12:25:37'),
	('smtp_port', '587', '2026-08-16 12:25:37', '2026-08-16 12:25:37'),
	('supported_currencies_json', '[{"code":"LKR","name":"Sri Lankan Rupee","symbol":"Rs.","rate":1,"status":"active"},{"code":"USD","name":"US Dollar","symbol":"$","rate":310,"status":"active"},{"code":"EUR","name":"Euro","symbol":"\\u20ac","rate":340,"status":"inactive"},{"code":"GBP","name":"British Pound","symbol":"\\u00a3","rate":395,"status":"inactive"},{"code":"INR","name":"Indian Rupee","symbol":"\\u20b9","rate":3.7,"status":"inactive"}]', '2026-08-16 13:52:01', '2026-08-16 13:58:50'),
	('system_name', 'TechShop', '2026-08-07 08:50:55', '2026-08-07 08:50:55'),
	('system_timezone', 'Asia/Colombo', '2026-08-07 08:50:55', '2026-08-07 08:50:55'),
	('tax_rate', '0', '2026-08-07 08:50:55', '2026-08-07 08:50:55');

-- Dumping structure for table csms_db.stock_movements
CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `movement_type` varchar(50) NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `reason` varchar(255) DEFAULT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table csms_db.stock_movements: ~2 rows (approximately)
INSERT INTO `stock_movements` (`id`, `product_id`, `serial_number`, `movement_type`, `quantity`, `reason`, `reference_id`, `user_id`, `created_at`) VALUES
	(1, 3, NULL, 'Adjustment Out', 17, 'Physical Stock Count Reconciliation (Missing count)', 'STOCK-TAKE', 1, '2026-08-18 07:31:01'),
	(2, 3, NULL, 'Adjustment In', 10, 'Physical Stock Count Reconciliation (Found excess)', 'STOCK-TAKE', 1, '2026-08-18 07:31:52');

-- Dumping structure for table csms_db.suppliers
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `payment_terms` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Net 30',
  `balance_due` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Hardware',
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.suppliers: ~2 rows (approximately)
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `created_at`, `updated_at`, `payment_terms`, `balance_due`, `tax_id`, `bank_details`, `category`, `status`, `notes`) VALUES
	(1, 'Tech Distro', 'John Smith', '123456789', 'john@techdistro.com', NULL, '2026-08-06 08:43:32', '2026-08-16 11:29:33', 'Net 30', 37200.00, NULL, NULL, 'Hardware', 'Active', NULL),
	(2, 'Global Hardware', 'Jane Doe', '987654321', 'jane@globalhw.com', NULL, '2026-08-06 08:43:32', '2026-08-18 04:35:10', 'Net 30', 13000.00, NULL, NULL, 'Hardware', 'Active', NULL);

-- Dumping structure for table csms_db.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Cashier',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `branch_id` int unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.users: ~3 rows (approximately)
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`, `phone`, `branch_id`) VALUES
	(5, 'Software Engineer (SuperAdmin)', 'superadmin@example.com', '$2y$12$.raE1VMrm4emjKD2IdafNej/vTq9pn8nsNbk44Bupci0KwkOZ90EC', 'SuperAdmin', 1, '2026-08-16 12:13:14', '2026-08-16 12:13:14', NULL, 1),
	(6, 'Chamika sandeepa', 'infor.chamika@gmail.com', '$2y$12$1hIFklgUbDOqPCLfSaK7ROHN2.myHe/RapaeD7.g0RwQ6j5mcSoPe', 'Admin', 1, '2026-08-18 09:43:06', '2026-08-18 09:43:06', '+94761042162', 1),
	(7, 'Chamika sandeepa', 'infor.rp@gmail.com', '$2y$12$7pINVdM3HJie2hWgaV57j.oJHosNtOQrrmADyrJ7Bpc5NgMB2ap76', 'Technician', 1, '2026-08-18 10:00:10', '2026-08-18 10:00:10', '+94761042162', 1);

-- Dumping structure for table csms_db.warranty_claims
CREATE TABLE IF NOT EXISTS `warranty_claims` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_serial_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `claim_date` date NOT NULL,
  `issue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('Pending','Approved','Rejected','Repaired','Replaced') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `resolution` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_serial_id` (`product_serial_id`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `warranty_claims_ibfk_1` FOREIGN KEY (`product_serial_id`) REFERENCES `product_serials` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `warranty_claims_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.warranty_claims: ~0 rows (approximately)

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
