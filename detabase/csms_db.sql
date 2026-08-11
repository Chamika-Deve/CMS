-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               9.7.1 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.20.0.7320
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
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_id` bigint unsigned DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.activity_logs: ~0 rows (approximately)

-- Dumping structure for table csms_db.brands
CREATE TABLE IF NOT EXISTS `brands` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `points` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.customers: ~2 rows (approximately)
INSERT INTO `customers` (`id`, `name`, `phone`, `email`, `address`, `points`, `created_at`, `updated_at`) VALUES
	(1, 'Walk-in Customer', '00000000', 'walkin@example.com', 'N/A', 0, '2026-08-06 08:43:32', '2026-08-06 08:43:32'),
	(2, 'Alice Wonderland', '555-1234', 'alice@example.com', '123 Main St', 0, '2026-08-06 08:43:32', '2026-08-06 08:43:32');

-- Dumping structure for table csms_db.product_serials
CREATE TABLE IF NOT EXISTS `product_serials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `serial_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_id` bigint unsigned DEFAULT NULL,
  `status` enum('in_stock','sold','returned','repair','defective') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_stock',
  `warranty_start_date` date DEFAULT NULL,
  `warranty_end_date` date DEFAULT NULL,
  `branch_id` int DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `serial_number` (`serial_number`),
  KEY `product_id` (`product_id`),
  KEY `purchase_id` (`purchase_id`),
  CONSTRAINT `product_serials_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `product_serials_ibfk_2` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.product_serials: ~46 rows (approximately)
INSERT INTO `product_serials` (`id`, `product_id`, `serial_number`, `purchase_id`, `status`, `warranty_start_date`, `warranty_end_date`, `branch_id`, `created_at`, `updated_at`) VALUES
	(1, 1, 'SN-DXPS15-001', 1, 'sold', NULL, NULL, 1, '2026-08-06 08:43:32', '2026-08-07 08:25:26'),
	(2, 2, 'SN-ASVG248-001', 1, 'sold', NULL, NULL, 1, '2026-08-06 08:43:32', '2026-08-07 08:21:28'),
	(3, 3, '566876876876', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:19:14', '2026-08-07 04:06:41'),
	(4, 3, '87678678676', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:19:16', '2026-08-07 04:06:41'),
	(5, 3, '876', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:19:17', '2026-08-07 08:25:26'),
	(6, 3, '87687687687687687', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:19:20', '2026-08-07 08:25:26'),
	(7, 3, '87687687687687', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:19:22', '2026-08-07 09:43:43'),
	(8, 3, '876876876876', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:19:26', '2026-08-07 09:43:43'),
	(9, 3, '5345345345', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:19:33', '2026-08-07 09:48:13'),
	(10, 3, '534534534534', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:19:36', '2026-08-06 09:19:36'),
	(11, 3, '345345345345', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:19:37', '2026-08-06 09:19:37'),
	(12, 3, 'tertert', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:20:53', '2026-08-06 09:20:53'),
	(13, 3, '4234234', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:20:56', '2026-08-06 09:20:56'),
	(14, 3, '2342342342342', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:20:58', '2026-08-06 09:20:58'),
	(15, 3, '4234234234234', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:21:00', '2026-08-06 09:21:00'),
	(16, 3, '2342342423423', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:21:02', '2026-08-06 09:21:02'),
	(17, 2, '58876876786876876', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:22:04', '2026-08-07 08:21:28'),
	(18, 2, '564654544', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:22:17', '2026-08-07 08:25:26'),
	(19, 2, '4454654', NULL, 'sold', NULL, NULL, 1, '2026-08-06 09:22:18', '2026-08-07 08:25:26'),
	(20, 2, '5454654654', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:22:18', '2026-08-06 09:22:18'),
	(21, 2, '64', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:22:19', '2026-08-06 09:22:19'),
	(22, 2, '65465', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:22:19', '2026-08-06 09:22:19'),
	(23, 2, '465', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:22:19', '2026-08-06 09:22:19'),
	(24, 2, '4654', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:22:19', '2026-08-06 09:22:19'),
	(25, 2, '65', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:22:19', '2026-08-06 09:22:19'),
	(26, 2, '6546', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:22:20', '2026-08-06 09:22:20'),
	(27, 2, '54', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:22:20', '2026-08-06 09:22:20'),
	(28, 2, '654', NULL, 'in_stock', NULL, NULL, 1, '2026-08-06 09:22:20', '2026-08-06 09:22:20'),
	(29, 3, '5869869876986', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 03:39:29', '2026-08-07 03:39:29'),
	(30, 3, '876876786876', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 03:39:31', '2026-08-07 03:39:31'),
	(31, 3, '876786786', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 03:39:32', '2026-08-07 03:39:32'),
	(32, 3, '98+98+89+', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 03:39:48', '2026-08-07 03:39:48'),
	(33, 3, 'asaasas', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 03:47:52', '2026-08-07 03:47:52'),
	(34, 3, 'tyjuyjuyjuy', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 03:49:02', '2026-08-07 03:49:02'),
	(35, 3, 'tyjutyujtyju', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 03:49:03', '2026-08-07 03:49:03'),
	(36, 3, 'tyjutyu', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 03:49:04', '2026-08-07 03:49:04'),
	(37, 3, 'tyu', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 03:49:04', '2026-08-07 03:49:04'),
	(38, 3, 'ty', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 03:49:04', '2026-08-07 03:49:04'),
	(39, 3, 'u', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 03:49:04', '2026-08-07 03:49:04'),
	(40, 3, 'ut', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 03:49:05', '2026-08-07 03:49:05'),
	(41, 3, 'uyt', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 03:49:05', '2026-08-07 03:49:05'),
	(42, 3, 'yu', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 03:49:06', '2026-08-07 03:49:06'),
	(43, 3, 'tyju', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 03:49:06', '2026-08-07 03:49:06'),
	(44, 3, 'rtretertert', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 03:55:29', '2026-08-07 03:55:29'),
	(45, 3, '12321312', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 08:45:20', '2026-08-07 08:45:20'),
	(46, 3, '21321313', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 08:45:22', '2026-08-07 08:45:22'),
	(47, 3, '312312312', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 08:45:24', '2026-08-07 08:45:24'),
	(48, 3, '3123123', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 08:45:25', '2026-08-07 08:45:25'),
	(49, 3, 'dan', NULL, 'in_stock', NULL, NULL, 1, '2026-08-07 08:45:31', '2026-08-07 08:45:31');

-- Dumping structure for table csms_db.products
CREATE TABLE IF NOT EXISTS `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` bigint unsigned NOT NULL,
  `brand_id` bigint unsigned NOT NULL,
  `product_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ean` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upc` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `selling_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `min_price` decimal(10,2) DEFAULT '0.00',
  `max_price` decimal(10,2) DEFAULT '0.00',
  `warranty_months` int NOT NULL DEFAULT '0',
  `reorder_level` int NOT NULL DEFAULT '5',
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pcs',
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Active','Discontinued') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_code` (`product_code`),
  KEY `category_id` (`category_id`),
  KEY `brand_id` (`brand_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `products_ibfk_2` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.products: ~4 rows (approximately)
INSERT INTO `products` (`id`, `name`, `category_id`, `brand_id`, `product_code`, `ean`, `upc`, `cost_price`, `selling_price`, `min_price`, `max_price`, `warranty_months`, `reorder_level`, `unit`, `description`, `image`, `status`, `created_at`, `updated_at`) VALUES
	(1, 'Dell XPS 15', 1, 1, 'LT-DXPS15', '1234567890123', '123456789012', 1200.00, 1500.00, 0.00, 0.00, 12, 2, 'pcs', NULL, NULL, 'Active', '2026-08-06 08:43:32', '2026-08-06 08:43:32'),
	(2, 'Asus VG248QE 24" Monitor', 2, 3, 'MN-ASVG248', '2234567890123', '223456789012', 200.00, 250.00, 0.00, 0.00, 24, 5, 'pcs', NULL, NULL, 'Active', '2026-08-06 08:43:32', '2026-08-06 08:43:32'),
	(3, 'Intel Core i7-13700K', 4, 4, 'CP-I713700K', '3234567890123', '323456789012', 350.00, 420.00, 400.00, 500.00, 36, 10, 'pcs', NULL, NULL, 'Active', '2026-08-06 08:43:32', '2026-08-07 09:42:23');

-- Dumping structure for table csms_db.purchase_items
CREATE TABLE IF NOT EXISTS `purchase_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `quantity` int NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_id` (`purchase_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `purchase_items_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.purchase_items: ~2 rows (approximately)
INSERT INTO `purchase_items` (`id`, `purchase_id`, `product_id`, `quantity`, `unit_cost`) VALUES
	(1, 1, 1, 1, 1200.00),
	(2, 1, 2, 1, 200.00);

-- Dumping structure for table csms_db.purchases
CREATE TABLE IF NOT EXISTS `purchases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint unsigned NOT NULL,
  `invoice_no` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_date` date NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('Pending','Completed','Cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Completed',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.purchases: ~0 rows (approximately)
INSERT INTO `purchases` (`id`, `supplier_id`, `invoice_no`, `purchase_date`, `total_amount`, `status`, `created_at`, `updated_at`) VALUES
	(1, 1, 'INV-2023-001', '2023-10-01', 1400.00, 'Completed', '2026-08-06 08:43:32', '2026-08-06 08:43:32');

-- Dumping structure for table csms_db.repair_jobs
CREATE TABLE IF NOT EXISTS `repair_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `device_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `serial_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue_description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `technician_id` bigint unsigned DEFAULT NULL,
  `status` enum('Received','Diagnosing','In Progress','Waiting for Parts','Completed','Delivered') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Received',
  `received_date` datetime NOT NULL,
  `delivered_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `technician_id` (`technician_id`),
  CONSTRAINT `repair_jobs_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `repair_jobs_ibfk_2` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.repair_jobs: ~0 rows (approximately)

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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.sale_items: ~9 rows (approximately)
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
	(16, 7, 3, 9, 1, 420.00);

-- Dumping structure for table csms_db.sales
CREATE TABLE IF NOT EXISTS `sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned DEFAULT NULL,
  `invoice_no` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sale_date` datetime NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_method` enum('Cash','Card','Bank Transfer') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Cash',
  `status` enum('Completed','Refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Completed',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.sales: ~3 rows (approximately)
INSERT INTO `sales` (`id`, `customer_id`, `invoice_no`, `sale_date`, `total_amount`, `discount`, `tax`, `payment_method`, `status`, `created_at`, `updated_at`) VALUES
	(1, NULL, 'INV-20260807-040641-44', '2026-08-07 09:36:41', 924.00, 0.00, 84.00, 'Cash', 'Completed', '2026-08-07 04:06:41', '2026-08-07 04:06:41'),
	(4, NULL, 'INV-20260807-082128-83', '2026-08-07 13:51:28', 500.00, 0.00, 0.00, 'Cash', 'Completed', '2026-08-07 08:21:28', '2026-08-07 08:21:28'),
	(5, 1, 'INV-20260807-082526-19', '2026-08-07 13:55:26', 2840.00, 0.00, 0.00, 'Cash', 'Completed', '2026-08-07 08:25:26', '2026-08-07 08:25:26'),
	(6, NULL, 'INV-20260807-094343-94', '2026-08-07 15:13:43', 1000.00, 0.00, 0.00, 'Cash', 'Completed', '2026-08-07 09:43:43', '2026-08-07 09:43:43'),
	(7, NULL, 'INV-20260807-094813-44', '2026-08-07 15:18:13', 420.00, 0.00, 0.00, 'Cash', 'Completed', '2026-08-07 09:48:13', '2026-08-07 09:48:13');

-- Dumping structure for table csms_db.settings
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.settings: ~11 rows (approximately)
INSERT INTO `settings` (`setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
	('bill_footer_message', 'Thank you for shopping with us! Goods once sold cannot be returned without the original receipt.', '2026-08-07 08:50:55', '2026-08-07 08:50:55'),
	('currency_symbol', 'Rs.', '2026-08-07 08:50:55', '2026-08-07 08:50:55'),
	('receipt_printer_width', '80mm', '2026-08-07 08:50:55', '2026-08-07 08:50:55'),
	('return_policy_days', '7', '2026-08-07 08:50:55', '2026-08-07 08:50:55'),
	('shop_address', '123 Main Street, Colombo 01', '2026-08-07 08:50:55', '2026-08-07 08:50:55'),
	('shop_email', 'info@techsolutions.lk', '2026-08-07 08:50:55', '2026-08-07 08:50:55'),
	('shop_name', 'Tech Solutions Inc.', '2026-08-07 08:50:55', '2026-08-07 08:50:55'),
	('shop_phone', '+94 77 123 4567', '2026-08-07 08:50:55', '2026-08-07 08:50:55'),
	('system_name', 'TechShop', '2026-08-07 08:50:55', '2026-08-07 08:50:55'),
	('system_timezone', 'Asia/Colombo', '2026-08-07 08:50:55', '2026-08-07 08:50:55'),
	('tax_rate', '0', '2026-08-07 08:50:55', '2026-08-07 08:50:55');

-- Dumping structure for table csms_db.suppliers
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.suppliers: ~2 rows (approximately)
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `created_at`, `updated_at`) VALUES
	(1, 'Tech Distro', 'John Smith', '123456789', 'john@techdistro.com', NULL, '2026-08-06 08:43:32', '2026-08-06 08:43:32'),
	(2, 'Global Hardware', 'Jane Doe', '987654321', 'jane@globalhw.com', NULL, '2026-08-06 08:43:32', '2026-08-06 08:43:32');

-- Dumping structure for table csms_db.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('Admin','Manager','Cashier','Technician') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Cashier',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table csms_db.users: ~4 rows (approximately)
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`) VALUES
	(1, 'Admin User', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 1, '2026-08-06 08:43:32', '2026-08-06 08:43:32'),
	(2, 'Manager User', 'manager@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Manager', 1, '2026-08-06 08:43:32', '2026-08-06 08:43:32'),
	(3, 'Cashier User', 'cashier@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cashier', 1, '2026-08-06 08:43:32', '2026-08-06 08:43:32'),
	(4, 'Tech User', 'tech@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Technician', 1, '2026-08-06 08:43:32', '2026-08-06 08:43:32');

-- Dumping structure for table csms_db.warranty_claims
CREATE TABLE IF NOT EXISTS `warranty_claims` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_serial_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `claim_date` date NOT NULL,
  `issue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('Pending','Approved','Rejected','Repaired','Replaced') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `resolution` text COLLATE utf8mb4_unicode_ci,
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
