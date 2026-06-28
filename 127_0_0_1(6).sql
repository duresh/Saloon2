-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 28, 2026 at 11:00 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `saloon`
--
CREATE DATABASE IF NOT EXISTS `saloon` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `saloon`;

-- --------------------------------------------------------

--
-- Table structure for table `admin_action_logs`
--

CREATE TABLE `admin_action_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `target_user` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `reschedule_count` int(11) NOT NULL DEFAULT 0,
  `last_reschedule_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `user_id`, `service_id`, `staff_id`, `appointment_date`, `appointment_time`, `status`, `notes`, `created_at`, `modified_at`, `reschedule_count`, `last_reschedule_at`) VALUES
(1, 4, 1, 2, '2026-05-24', '12:00:00', 'completed', '', '2026-05-24 03:50:28', '2026-05-24 06:42:43', 0, NULL),
(2, 4, 2, 1, '2026-05-24', '09:30:00', 'completed', '', '2026-05-24 03:50:44', '2026-05-24 06:42:52', 0, NULL),
(3, 4, 3, 2, '2026-05-30', '12:00:00', 'completed', '', '2026-05-24 03:51:09', '2026-05-24 05:27:50', 0, NULL),
(4, 4, 7, 2, '2026-05-24', '15:30:00', 'completed', '', '2026-05-24 03:51:39', '2026-05-24 06:43:08', 0, NULL),
(5, 4, 6, NULL, '2026-06-03', '12:00:00', 'pending', '', '2026-05-31 05:10:20', NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `appointment_edit_logs`
--

CREATE TABLE `appointment_edit_logs` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `changes` text NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_edit_logs`
--

INSERT INTO `appointment_edit_logs` (`id`, `appointment_id`, `admin_id`, `changes`, `reason`, `created_at`) VALUES
(1, 2, 1, 'Date: 2026-05-30 → 2026-05-24, Time: 13:00:00 → 09:30', 'customer_request', '2026-05-24 04:10:31');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_extension_requests`
--

CREATE TABLE `appointment_extension_requests` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `new_end_time` time NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointment_move_requests`
--

CREATE TABLE `appointment_move_requests` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `new_date` date NOT NULL,
  `new_time` time NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointment_reschedules`
--

CREATE TABLE `appointment_reschedules` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `old_service_id` int(11) DEFAULT NULL,
  `new_service_id` int(11) DEFAULT NULL,
  `old_date` date NOT NULL,
  `new_date` date NOT NULL,
  `old_time` time NOT NULL,
  `new_time` time NOT NULL,
  `reschedule_reason` varchar(255) DEFAULT NULL,
  `rescheduled_by` enum('customer','admin','system') DEFAULT 'customer',
  `rescheduled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointment_staff_logs`
--

CREATE TABLE `appointment_staff_logs` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `action` enum('assigned','removed','changed') NOT NULL,
  `performed_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_staff_logs`
--

INSERT INTO `appointment_staff_logs` (`id`, `appointment_id`, `staff_id`, `action`, `performed_by`, `created_at`) VALUES
(1, 2, 1, 'assigned', 1, '2026-05-24 03:52:51'),
(2, 3, 2, 'assigned', 1, '2026-05-24 03:53:01'),
(3, 4, 2, 'assigned', 1, '2026-05-24 03:53:15'),
(4, 1, 2, 'assigned', 1, '2026-05-24 03:53:22');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_status_logs`
--

CREATE TABLE `appointment_status_logs` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `old_status` varchar(20) NOT NULL,
  `new_status` varchar(20) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `available_time_slots`
-- (See below for the actual view)
--
CREATE TABLE `available_time_slots` (
`staff_id` int(11)
,`staff_name` varchar(25)
,`specialization` varchar(100)
,`day_of_week` tinyint(1)
,`start_time` time
,`end_time` time
,`is_available` tinyint(1)
,`today_appointments` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_notifications`
--

CREATE TABLE `customer_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_notifications`
--

INSERT INTO `customer_notifications` (`id`, `user_id`, `order_id`, `type`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 4, 1, 'order_confirmed', 'Order Confirmed', 'Your order #SE-20260531-807088 has been confirmed by our staff. We are preparing your items.', 0, '2026-06-28 05:35:47'),
(2, 4, 1, 'order_processing', 'Order Being Processed', 'Your order #SE-20260531-807088 is now being processed and prepared for shipping.', 0, '2026-06-28 05:36:27'),
(3, 4, 1, 'order_shipped', 'Order Shipped', 'Great news! Your order #SE-20260531-807088 has been shipped and is on its way to you.', 0, '2026-06-28 05:36:35'),
(4, 4, 1, 'order_delivered', 'Order Delivered', 'Your order #SE-20260531-807088 has been delivered successfully. Thank you for shopping with us!', 0, '2026-06-28 05:37:22');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `grand_total` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','processing','shipped','delivered','cancelled','refunded') DEFAULT 'pending',
  `payment_method` enum('cash_on_delivery','card','bank_transfer','online') DEFAULT 'cash_on_delivery',
  `payment_status` enum('unpaid','paid','refunded','failed') DEFAULT 'unpaid',
  `shipping_address` text DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `staff_notes` text DEFAULT NULL,
  `ordered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `total_amount`, `discount_amount`, `tax_amount`, `grand_total`, `status`, `payment_method`, `payment_status`, `shipping_address`, `billing_address`, `phone`, `notes`, `staff_notes`, `ordered_at`, `updated_at`, `approved_at`, `delivered_at`, `processed_by`) VALUES
(1, 'SE-20260531-807088', 4, 27400.00, 0.00, 0.00, 27400.00, 'delivered', 'cash_on_delivery', 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-05-31 05:57:44', '2026-06-28 05:37:22', '2026-06-28 05:35:47', '2026-06-28 05:37:22', NULL),
(3, 'SE-20260628-8CB61C', 4, 23050.00, 0.00, 2305.00, 25355.00, 'cancelled', 'cash_on_delivery', 'unpaid', '12,katuwawala', NULL, '0745689877', '', NULL, '2026-06-28 04:23:36', '2026-06-28 04:52:53', NULL, NULL, NULL),
(4, 'SE-20260628-DD42E8', 4, 38840.00, 0.00, 3884.00, 42724.00, 'pending', 'cash_on_delivery', 'unpaid', 'Beach road, Matara', NULL, '0745689877', '', NULL, '2026-06-28 05:46:53', NULL, NULL, NULL, NULL),
(5, 'SE-20260628-A7D9E1', 4, 9990.00, 0.00, 999.00, 10989.00, 'pending', 'cash_on_delivery', 'unpaid', 'Galle', NULL, '0745689877', '', NULL, '2026-06-28 06:29:14', NULL, NULL, NULL, NULL),
(6, 'SE-20260628-263539', 4, 40990.00, 0.00, 4099.00, 45089.00, 'pending', 'cash_on_delivery', 'unpaid', '35, Beach Road, Matara', NULL, '0745689877', '', NULL, '2026-06-28 07:26:10', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `quantity`, `price`, `subtotal`, `created_at`) VALUES
(1, 1, 2, 'Ultra-Glide Face Polisher', 1, 8900.00, 8900.00, '2026-05-31 05:57:44'),
(2, 1, 3, 'Aromatherapy Foot Massager', 1, 18500.00, 18500.00, '2026-05-31 05:57:44'),
(4, 3, 3, 'Aromatherapy Foot Massager', 1, 18500.00, 18500.00, '2026-06-28 04:23:36'),
(5, 3, 8, 'Keratin Hair Mask', 1, 4550.00, 4550.00, '2026-06-28 04:23:36'),
(6, 4, 5, 'LED Light Therapy Mask', 1, 25900.00, 25900.00, '2026-06-28 05:46:54'),
(7, 4, 6, 'Professional Hair Clipper Kit', 1, 9990.00, 9990.00, '2026-06-28 05:46:54'),
(8, 4, 9, 'Organic Shea Body Butter', 1, 2950.00, 2950.00, '2026-06-28 05:46:54'),
(9, 5, 6, 'Professional Hair Clipper Kit', 1, 9990.00, 9990.00, '2026-06-28 06:29:14'),
(10, 6, 6, 'Professional Hair Clipper Kit', 1, 9990.00, 9990.00, '2026-06-28 07:26:10'),
(11, 6, 3, 'Aromatherapy Foot Massager', 1, 18500.00, 18500.00, '2026-06-28 07:26:10'),
(12, 6, 1, 'Professional Hair Dryer - Ionic', 1, 12500.00, 12500.00, '2026-06-28 07:26:10');

-- --------------------------------------------------------

--
-- Table structure for table `order_status_logs`
--

CREATE TABLE `order_status_logs` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `old_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_status_logs`
--

INSERT INTO `order_status_logs` (`id`, `order_id`, `old_status`, `new_status`, `changed_by`, `reason`, `changed_at`) VALUES
(1, 1, NULL, 'pending', 4, NULL, '2026-05-31 05:57:44'),
(2, 3, NULL, 'pending', 4, NULL, '2026-06-28 04:23:37'),
(3, 3, 'pending', 'cancelled', 4, 'Cancelled by customer', '2026-06-28 04:52:53'),
(15, 1, 'pending', 'confirmed', 2, 'Staff action', '2026-06-28 05:35:47'),
(16, 1, 'confirmed', 'processing', 2, 'Staff action', '2026-06-28 05:36:27'),
(17, 1, 'processing', 'shipped', 2, 'Staff action', '2026-06-28 05:36:35'),
(18, 1, 'shipped', 'delivered', 2, 'Staff action', '2026-06-28 05:37:22'),
(19, 4, NULL, 'pending', 4, NULL, '2026-06-28 05:46:54'),
(20, 5, NULL, 'pending', 4, NULL, '2026-06-28 06:29:14'),
(21, 6, NULL, 'pending', 4, NULL, '2026-06-28 07:26:10');

-- --------------------------------------------------------

--
-- Table structure for table `password_change_logs`
--

CREATE TABLE `password_change_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT 0.00,
  `image_url` varchar(500) DEFAULT NULL,
  `offer_tag` varchar(50) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `reorder_level` int(11) DEFAULT 5,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `category`, `rating`, `image_url`, `offer_tag`, `stock_quantity`, `reorder_level`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Professional Hair Dryer - Ionic', '2200W professional ionic hair dryer with diffuser. Reduces frizz, adds shine. Salon grade motor.', 12500.00, 'equipment', 4.80, 'https://images.unsplash.com/photo-1522338140262-f46f5913618a?w=400&h=300&fit=crop', 'pro', 14, 5, 'active', '2026-05-31 05:51:15', '2026-06-28 07:26:10'),
(2, 'Ultra-Glide Face Polisher', 'Ultrasonic face scrubber with 3 brush heads. Removes dead skin, boosts glow.', 8900.00, 'face care', 4.70, 'https://images.unsplash.com/photo-1596462502278-27bfdc6e39db?w=400&h=300&fit=crop', 'bestseller', 24, 5, 'active', '2026-05-31 05:51:15', '2026-05-31 05:57:44'),
(3, 'Aromatherapy Foot Massager', 'Shiatsu rolling massager with heat, air compression, and remote control.', 18500.00, 'foot care', 4.90, 'https://images.unsplash.com/photo-1519823551278-64ac92734fb1?w=400&h=300&fit=crop', 'sale', 6, 5, 'active', '2026-05-31 05:51:15', '2026-06-28 07:26:10'),
(4, 'Organic Argan Hair Oil', '100% pure Moroccan argan oil. Nourishes, repairs split ends, adds shine.', 3450.00, 'hair care', 4.60, 'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?w=400&h=300&fit=crop', 'organic', 50, 5, 'active', '2026-05-31 05:51:15', NULL),
(5, 'LED Light Therapy Mask', '7 color light therapy for anti-aging, acne treatment, and skin rejuvenation.', 25900.00, 'face care', 5.00, 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=400&h=300&fit=crop', 'bestseller', 11, 5, 'active', '2026-05-31 05:51:15', '2026-06-28 05:46:54'),
(6, 'Professional Hair Clipper Kit', 'Cordless precision trimmer with titanium blades. 8 guide combs, barber grade.', 9990.00, 'equipment', 4.80, 'https://images.unsplash.com/photo-1621605815971-fbc98d665033?w=400&h=300&fit=crop', 'pro', 17, 5, 'active', '2026-05-31 05:51:15', '2026-06-28 07:26:10'),
(7, 'Volcanic Foot Scrub Cream', 'Exfoliating pumice cream with peppermint. Softens calluses, refreshes feet.', 2250.00, 'foot care', 4.50, 'https://images.unsplash.com/photo-1519415510236-718bdfcd89c8?w=400&h=300&fit=crop', 'sale', 40, 5, 'active', '2026-05-31 05:51:15', NULL),
(8, 'Keratin Hair Mask', 'Restorative mask with keratin & argan oil. Repairs split ends, adds shine.', 4550.00, 'hair care', 4.90, 'https://images.unsplash.com/photo-1526947425960-945c6e72858f?w=400&h=300&fit=crop', 'organic', 35, 5, 'active', '2026-05-31 05:51:15', '2026-06-28 04:52:53'),
(9, 'Organic Shea Body Butter', 'Deeply nourishing, 98% organic ingredients. Restores skin elasticity.', 2950.00, 'body care', 4.60, 'https://images.unsplash.com/photo-1556228720-195a672e8a03?w=400&h=300&fit=crop', 'organic', 44, 5, 'active', '2026-05-31 05:51:15', '2026-06-28 05:46:54'),
(10, 'Infrared Body Wrap', 'Detox and slim waist wrap using far-infrared technology. Home spa solution.', 35900.00, 'body care', 4.70, 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=400&h=300&fit=crop', 'sale', 5, 5, 'active', '2026-05-31 05:51:15', NULL),
(11, 'Premium Shaving Kit', 'Complete wet shaving kit with badger brush, safety razor, and stand.', 12500.00, 'equipment', 4.80, 'https://images.unsplash.com/photo-1621605815971-fbc98d665033?w=400&h=300&fit=crop', 'pro', 18, 5, 'active', '2026-05-31 05:51:15', NULL),
(12, 'Sandalwood Face Wash', 'Natural sandalwood face wash for deep cleansing and glowing skin.', 1850.00, 'face care', 4.40, 'https://images.unsplash.com/photo-1556229010-6c3f2c9ca5f8?w=400&h=300&fit=crop', 'organic', 60, 5, 'active', '2026-05-31 05:51:15', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `reg`
--

CREATE TABLE `reg` (
  `regID` int(10) NOT NULL,
  `fName` varchar(25) NOT NULL,
  `lName` varchar(25) NOT NULL,
  `email` varchar(50) NOT NULL,
  `contactNo` varchar(10) NOT NULL,
  `password` varchar(64) NOT NULL,
  `password_changed` tinyint(1) DEFAULT 0,
  `regDate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `modifyDate` datetime NOT NULL,
  `role` varchar(20) NOT NULL,
  `cStatus` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reg`
--

INSERT INTO `reg` (`regID`, `fName`, `lName`, `email`, `contactNo`, `password`, `password_changed`, `regDate`, `modifyDate`, `role`, `cStatus`) VALUES
(1, 'Duresh Weerasekara', '', 'duresh@gmail.com', '0718059219', '$2y$10$d65..e3c6O8z4tVPU/Z07OLFdhd2LBbxxaTwSLVBmyBkMNgu6nlXW', 1, '2026-05-24 03:34:01', '2026-05-24 09:04:01', 'admin', 1),
(2, 'Pasan Silva', '', 'pasan@gmail.com', '0728823555', '$2y$10$Pp4/F8Wc4fVzs0jfV0/alOKmjb07Bvrz1QZvTasJzH4ywicaGns22', 1, '2026-05-24 03:47:42', '2026-05-24 09:17:42', 'staff', 1),
(3, 'Damith Pathirana', '', 'damith@gmail.com', '0778942366', '$2y$10$d65..e3c6O8z4tVPU/Z07OLFdhd2LBbxxaTwSLVBmyBkMNgu6nlXW', 0, '2026-05-24 03:46:43', '2026-05-24 09:08:08', 'staff', 1),
(4, 'Dasun Gamage', '', 'dasun@gmail.com', '0745689877', '$2y$10$702CspgHZn1Vuum407rLs.JBwn8Zct13nDb0Mhe01tSJhDMEF32kK', 1, '2026-05-24 03:49:35', '2026-05-24 09:19:35', 'user', 1);

-- --------------------------------------------------------

--
-- Table structure for table `reschedule_logs`
--

CREATE TABLE `reschedule_logs` (
  `id` int(11) NOT NULL,
  `reschedule_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `field_name` varchar(50) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration` int(11) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `description`, `price`, `duration`, `category`, `status`) VALUES
(1, 'Haircut', 'Professional haircut with styling', 1000.00, 45, 'Hair', 'active'),
(2, 'Hair Color', 'Full hair coloring service', 1250.00, 120, 'Hair', 'active'),
(3, 'Manicure', 'Basic manicure with polish', 1500.00, 45, 'Nails', 'active'),
(4, 'Pedicure', 'Relaxing pedicure treatment', 1750.00, 60, 'Nails', 'active'),
(5, 'Facial', 'Deep cleansing facial', 2500.00, 60, 'Skin Care', 'active'),
(6, 'Waxing', 'Full leg waxing', 3000.00, 45, 'Waxing', 'active'),
(7, 'Massage', '60-minute full body massage', 5000.00, 60, 'Spa', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `emergency_contact` varchar(15) DEFAULT NULL,
  `emergency_name` varchar(100) DEFAULT NULL,
  `working_hours` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `user_id`, `phone`, `address`, `specialization`, `qualification`, `experience_years`, `joining_date`, `bio`, `profile_image`, `emergency_contact`, `emergency_name`, `working_hours`, `created_at`, `updated_at`) VALUES
(1, 2, '', '', 'Hair Cut, Color', '', 2, '2025-01-04', '', NULL, NULL, NULL, NULL, '2026-05-24 03:36:26', NULL),
(2, 3, '', '', 'Manicure, Padicure', '', 4, '2025-03-12', '', NULL, NULL, NULL, NULL, '2026-05-24 03:38:08', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff_action_logs`
--

CREATE TABLE `staff_action_logs` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_action_logs`
--

INSERT INTO `staff_action_logs` (`id`, `staff_id`, `order_id`, `action`, `details`, `created_at`) VALUES
(1, 2, 1, 'approve', 'Order #SE-20260531-807088 status changed from pending to confirmed', '2026-06-28 05:35:47'),
(2, 2, 1, 'processing', 'Order #SE-20260531-807088 status changed from confirmed to processing', '2026-06-28 05:36:27'),
(3, 2, 1, 'ship', 'Order #SE-20260531-807088 status changed from processing to shipped', '2026-06-28 05:36:35'),
(4, 2, 1, 'deliver', 'Order #SE-20260531-807088 status changed from shipped to delivered', '2026-06-28 05:37:22');

-- --------------------------------------------------------

--
-- Table structure for table `staff_availability`
--

CREATE TABLE `staff_availability` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=Sunday, 1=Monday, etc.',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `staff_daily_availability`
-- (See below for the actual view)
--
CREATE TABLE `staff_daily_availability` (
`staff_id` int(11)
,`staff_name` varchar(25)
,`specialization` varchar(100)
,`day_of_week` tinyint(1)
,`start_time` time
,`end_time` time
,`is_available` tinyint(1)
,`availability_status` varchar(13)
);

-- --------------------------------------------------------

--
-- Table structure for table `staff_documents`
--

CREATE TABLE `staff_documents` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_leave`
--

CREATE TABLE `staff_leave` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `leave_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `leave_type` enum('sick','casual','annual','emergency','other') DEFAULT 'casual',
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_notifications`
--

CREATE TABLE `staff_notifications` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_notifications`
--

INSERT INTO `staff_notifications` (`id`, `staff_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 'New Rating', 'You received a 5-star rating from customer for service: Manicure', 0, '2026-05-24 06:44:02'),
(2, 2, 'New Rating', 'You received a 4-star rating from customer for service: Massage', 0, '2026-05-24 06:44:36'),
(3, 2, 'New Rating', 'You received a 5-star rating from customer for service: Haircut', 0, '2026-05-24 06:44:59'),
(4, 1, 'New Rating', 'You received a 4-star rating from customer for service: Hair Color', 0, '2026-05-24 06:45:20');

-- --------------------------------------------------------

--
-- Table structure for table `staff_performance`
--

CREATE TABLE `staff_performance` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `month` int(2) NOT NULL,
  `year` int(4) NOT NULL,
  `total_appointments` int(11) DEFAULT 0,
  `completed_appointments` int(11) DEFAULT 0,
  `cancelled_appointments` int(11) DEFAULT 0,
  `no_show_appointments` int(11) DEFAULT 0,
  `avg_rating` decimal(3,2) DEFAULT 0.00,
  `total_revenue` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `staff_performance_summary`
-- (See below for the actual view)
--
CREATE TABLE `staff_performance_summary` (
`id` int(11)
,`user_id` int(11)
,`staff_name` varchar(25)
,`email` varchar(50)
,`contactNo` varchar(10)
,`specialization` varchar(100)
,`experience_years` int(11)
,`joining_date` date
,`total_appointments` bigint(21)
,`completed_appointments` decimal(22,0)
,`cancelled_appointments` decimal(22,0)
,`completion_rate` decimal(28,2)
,`avg_rating` decimal(7,4)
,`total_ratings` bigint(21)
,`upcoming_appointments` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Table structure for table `staff_ratings`
--

CREATE TABLE `staff_ratings` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `service_name` varchar(100) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `response` text DEFAULT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `responded_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_ratings`
--

INSERT INTO `staff_ratings` (`id`, `appointment_id`, `staff_id`, `user_id`, `rating`, `review`, `created_at`, `service_name`, `comments`, `response`, `responded_at`, `responded_by`) VALUES
(1, 3, 2, 4, 5, NULL, '2026-05-24 06:44:02', 'Manicure', 'Great Work. Nicely done', NULL, NULL, NULL),
(2, 4, 2, 4, 4, NULL, '2026-05-24 06:44:36', 'Massage', 'Satisfied', NULL, NULL, NULL),
(3, 1, 2, 4, 5, NULL, '2026-05-24 06:44:59', 'Haircut', 'Perfect as I expected', NULL, NULL, NULL),
(4, 2, 1, 4, 4, NULL, '2026-05-24 06:45:20', 'Hair Color', 'Nice work', NULL, NULL, NULL);

--
-- Triggers `staff_ratings`
--
DELIMITER $$
CREATE TRIGGER `update_staff_rating_summary` AFTER INSERT ON `staff_ratings` FOR EACH ROW BEGIN
    INSERT INTO staff_rating_summary (staff_id, total_ratings, average_rating, rating_5_count, rating_4_count, rating_3_count, rating_2_count, rating_1_count)
    SELECT 
        NEW.staff_id,
        COUNT(*),
        ROUND(AVG(rating), 2),
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END),
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END),
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END),
        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END),
        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END)
    FROM staff_ratings
    WHERE staff_id = NEW.staff_id
    ON DUPLICATE KEY UPDATE
        total_ratings = VALUES(total_ratings),
        average_rating = VALUES(average_rating),
        rating_5_count = VALUES(rating_5_count),
        rating_4_count = VALUES(rating_4_count),
        rating_3_count = VALUES(rating_3_count),
        rating_2_count = VALUES(rating_2_count),
        rating_1_count = VALUES(rating_1_count),
        last_updated = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `staff_rating_summary`
--

CREATE TABLE `staff_rating_summary` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `total_ratings` int(11) DEFAULT 0,
  `average_rating` decimal(3,2) DEFAULT 0.00,
  `rating_5_count` int(11) DEFAULT 0,
  `rating_4_count` int(11) DEFAULT 0,
  `rating_3_count` int(11) DEFAULT 0,
  `rating_2_count` int(11) DEFAULT 0,
  `rating_1_count` int(11) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_rating_summary`
--

INSERT INTO `staff_rating_summary` (`id`, `staff_id`, `total_ratings`, `average_rating`, `rating_5_count`, `rating_4_count`, `rating_3_count`, `rating_2_count`, `rating_1_count`, `last_updated`) VALUES
(1, 2, 3, 4.67, 2, 1, 0, 0, 0, '2026-05-24 06:44:59'),
(4, 1, 1, 4.00, 0, 1, 0, 0, 0, '2026-05-24 06:45:20');

-- --------------------------------------------------------

--
-- Table structure for table `staff_schedule_exceptions`
--

CREATE TABLE `staff_schedule_exceptions` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `exception_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_working_day` tinyint(1) DEFAULT 1,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_specializations`
--

CREATE TABLE `staff_specializations` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `specialization` varchar(100) NOT NULL,
  `certificate` varchar(255) DEFAULT NULL,
  `years_experience` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `movement_type` enum('addition','subtraction','adjustment') NOT NULL,
  `reference_type` enum('order','restock','return','manual') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `product_id`, `quantity`, `movement_type`, `reference_type`, `reference_id`, `notes`, `created_by`, `created_at`) VALUES
(1, 3, -1, 'subtraction', 'order', 3, 'Order #SE-20260628-8CB61C', 4, '2026-06-28 04:23:36'),
(2, 8, -1, 'subtraction', 'order', 3, 'Order #SE-20260628-8CB61C', 4, '2026-06-28 04:23:37'),
(3, 5, -1, 'subtraction', 'order', 4, 'Order #SE-20260628-DD42E8', 4, '2026-06-28 05:46:54'),
(4, 6, -1, 'subtraction', 'order', 4, 'Order #SE-20260628-DD42E8', 4, '2026-06-28 05:46:54'),
(5, 9, -1, 'subtraction', 'order', 4, 'Order #SE-20260628-DD42E8', 4, '2026-06-28 05:46:54'),
(6, 6, -1, 'subtraction', 'order', 5, 'Order #SE-20260628-A7D9E1', 4, '2026-06-28 06:29:14');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','json','file') DEFAULT 'text',
  `category` varchar(50) DEFAULT 'general',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_action_logs`
--

CREATE TABLE `user_action_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `old_value` varchar(255) DEFAULT NULL,
  `new_value` varchar(255) DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `notification_email` tinyint(1) DEFAULT 1,
  `notification_sms` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`id`, `user_id`, `profile_image`, `address`, `shipping_address`, `phone`, `bio`, `notification_email`, `notification_sms`, `created_at`, `updated_at`) VALUES
(1, 4, NULL, NULL, '35, Beach Road, Matara', '0745689877', NULL, 1, 0, '2026-05-24 03:51:51', '2026-06-28 07:26:09'),
(2, 1, 'user_1_1779604788.jpg', NULL, NULL, NULL, NULL, 1, 0, '2026-05-24 06:39:27', '2026-05-24 06:39:49'),
(3, 2, NULL, NULL, NULL, NULL, NULL, 1, 0, '2026-06-28 03:22:01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `available_time_slots`
--
DROP TABLE IF EXISTS `available_time_slots`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `available_time_slots`  AS SELECT `s`.`id` AS `staff_id`, `r`.`fName` AS `staff_name`, `s`.`specialization` AS `specialization`, `a`.`day_of_week` AS `day_of_week`, `a`.`start_time` AS `start_time`, `a`.`end_time` AS `end_time`, `a`.`is_available` AS `is_available`, (select count(0) from `appointments` `apt` where `apt`.`staff_id` = `s`.`id` and `apt`.`appointment_date` = curdate() and `apt`.`status` in ('pending','confirmed')) AS `today_appointments` FROM ((`staff` `s` join `reg` `r` on(`s`.`user_id` = `r`.`regID`)) left join `staff_availability` `a` on(`s`.`id` = `a`.`staff_id`)) WHERE `r`.`cStatus` = 1 ;

-- --------------------------------------------------------

--
-- Structure for view `staff_daily_availability`
--
DROP TABLE IF EXISTS `staff_daily_availability`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `staff_daily_availability`  AS SELECT `s`.`id` AS `staff_id`, `r`.`fName` AS `staff_name`, `s`.`specialization` AS `specialization`, `a`.`day_of_week` AS `day_of_week`, `a`.`start_time` AS `start_time`, `a`.`end_time` AS `end_time`, `a`.`is_available` AS `is_available`, CASE WHEN `a`.`is_available` = 1 THEN 'Available' ELSE 'Not Available' END AS `availability_status` FROM ((`staff` `s` join `reg` `r` on(`s`.`user_id` = `r`.`regID`)) left join `staff_availability` `a` on(`s`.`id` = `a`.`staff_id`)) WHERE `r`.`cStatus` = 1 ;

-- --------------------------------------------------------

--
-- Structure for view `staff_performance_summary`
--
DROP TABLE IF EXISTS `staff_performance_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `staff_performance_summary`  AS SELECT `s`.`id` AS `id`, `s`.`user_id` AS `user_id`, `r`.`fName` AS `staff_name`, `r`.`email` AS `email`, `r`.`contactNo` AS `contactNo`, `s`.`specialization` AS `specialization`, `s`.`experience_years` AS `experience_years`, `s`.`joining_date` AS `joining_date`, count(distinct `a`.`id`) AS `total_appointments`, sum(case when `a`.`status` = 'completed' then 1 else 0 end) AS `completed_appointments`, sum(case when `a`.`status` = 'cancelled' then 1 else 0 end) AS `cancelled_appointments`, round(sum(case when `a`.`status` = 'completed' then 1 else 0 end) / nullif(count(distinct `a`.`id`),0) * 100,2) AS `completion_rate`, avg(`sr`.`rating`) AS `avg_rating`, count(distinct `sr`.`id`) AS `total_ratings`, sum(case when `a`.`appointment_date` >= curdate() then 1 else 0 end) AS `upcoming_appointments` FROM (((`staff` `s` join `reg` `r` on(`s`.`user_id` = `r`.`regID`)) left join `appointments` `a` on(`s`.`id` = `a`.`staff_id`)) left join `staff_ratings` `sr` on(`s`.`id` = `sr`.`staff_id`)) GROUP BY `s`.`id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_action_logs`
--
ALTER TABLE `admin_action_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `idx_appointment_dates` (`appointment_date`,`appointment_time`,`status`),
  ADD KEY `idx_appointment_user` (`user_id`,`status`),
  ADD KEY `idx_appointment_staff_date` (`staff_id`,`appointment_date`,`status`),
  ADD KEY `idx_appointment_staff_performance` (`staff_id`,`status`,`appointment_date`);

--
-- Indexes for table `appointment_edit_logs`
--
ALTER TABLE `appointment_edit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `appointment_extension_requests`
--
ALTER TABLE `appointment_extension_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `appointment_move_requests`
--
ALTER TABLE `appointment_move_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `appointment_reschedules`
--
ALTER TABLE `appointment_reschedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `rescheduled_at` (`rescheduled_at`),
  ADD KEY `idx_reschedule_dates` (`old_date`,`new_date`);

--
-- Indexes for table `appointment_staff_logs`
--
ALTER TABLE `appointment_staff_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `appointment_status_logs`
--
ALTER TABLE `appointment_status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_product` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `customer_notifications`
--
ALTER TABLE `customer_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`),
  ADD KEY `ordered_at` (`ordered_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `order_status_logs`
--
ALTER TABLE `order_status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `password_change_logs`
--
ALTER TABLE `password_change_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `reg`
--
ALTER TABLE `reg`
  ADD PRIMARY KEY (`regID`);

--
-- Indexes for table `reschedule_logs`
--
ALTER TABLE `reschedule_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reschedule_id` (`reschedule_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_staff_specialization` (`specialization`),
  ADD KEY `idx_staff_experience` (`experience_years`),
  ADD KEY `idx_staff_joining` (`joining_date`);

--
-- Indexes for table `staff_action_logs`
--
ALTER TABLE `staff_action_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `staff_availability`
--
ALTER TABLE `staff_availability`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_staff_day` (`staff_id`,`day_of_week`),
  ADD KEY `idx_availability_day` (`day_of_week`),
  ADD KEY `idx_availability_time` (`start_time`,`end_time`);

--
-- Indexes for table `staff_documents`
--
ALTER TABLE `staff_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `staff_leave`
--
ALTER TABLE `staff_leave`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `leave_date` (`leave_date`);

--
-- Indexes for table `staff_notifications`
--
ALTER TABLE `staff_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `staff_performance`
--
ALTER TABLE `staff_performance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_staff_month_year` (`staff_id`,`month`,`year`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `staff_ratings`
--
ALTER TABLE `staff_ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_appointment_rating` (`appointment_id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_staff_rating` (`staff_id`,`rating`),
  ADD KEY `idx_appointment_rating` (`appointment_id`);

--
-- Indexes for table `staff_rating_summary`
--
ALTER TABLE `staff_rating_summary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `staff_id` (`staff_id`);

--
-- Indexes for table `staff_schedule_exceptions`
--
ALTER TABLE `staff_schedule_exceptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `exception_date` (`exception_date`);

--
-- Indexes for table `staff_specializations`
--
ALTER TABLE `staff_specializations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `category` (`category`);

--
-- Indexes for table `user_action_logs`
--
ALTER TABLE `user_action_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_product_wish` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_action_logs`
--
ALTER TABLE `admin_action_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `appointment_edit_logs`
--
ALTER TABLE `appointment_edit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `appointment_extension_requests`
--
ALTER TABLE `appointment_extension_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointment_move_requests`
--
ALTER TABLE `appointment_move_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointment_reschedules`
--
ALTER TABLE `appointment_reschedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointment_staff_logs`
--
ALTER TABLE `appointment_staff_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `appointment_status_logs`
--
ALTER TABLE `appointment_status_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_notifications`
--
ALTER TABLE `customer_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `order_status_logs`
--
ALTER TABLE `order_status_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `password_change_logs`
--
ALTER TABLE `password_change_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `reg`
--
ALTER TABLE `reg`
  MODIFY `regID` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `reschedule_logs`
--
ALTER TABLE `reschedule_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `staff_action_logs`
--
ALTER TABLE `staff_action_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `staff_availability`
--
ALTER TABLE `staff_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_documents`
--
ALTER TABLE `staff_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_leave`
--
ALTER TABLE `staff_leave`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_notifications`
--
ALTER TABLE `staff_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `staff_performance`
--
ALTER TABLE `staff_performance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_ratings`
--
ALTER TABLE `staff_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `staff_rating_summary`
--
ALTER TABLE `staff_rating_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `staff_schedule_exceptions`
--
ALTER TABLE `staff_schedule_exceptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_specializations`
--
ALTER TABLE `staff_specializations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_action_logs`
--
ALTER TABLE `user_action_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_profiles`
--
ALTER TABLE `user_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_action_logs`
--
ALTER TABLE `admin_action_logs`
  ADD CONSTRAINT `admin_action_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `reg` (`regID`) ON DELETE CASCADE;

--
-- Constraints for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD CONSTRAINT `admin_notifications_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `reg` (`regID`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_notifications_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `reg` (`regID`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `appointment_edit_logs`
--
ALTER TABLE `appointment_edit_logs`
  ADD CONSTRAINT `edit_logs_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `edit_logs_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `reg` (`regID`);

--
-- Constraints for table `appointment_extension_requests`
--
ALTER TABLE `appointment_extension_requests`
  ADD CONSTRAINT `extension_requests_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `extension_requests_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `appointment_move_requests`
--
ALTER TABLE `appointment_move_requests`
  ADD CONSTRAINT `move_requests_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `move_requests_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `appointment_reschedules`
--
ALTER TABLE `appointment_reschedules`
  ADD CONSTRAINT `appointment_reschedules_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointment_reschedules_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `reg` (`regID`) ON DELETE CASCADE;

--
-- Constraints for table `appointment_staff_logs`
--
ALTER TABLE `appointment_staff_logs`
  ADD CONSTRAINT `staff_logs_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_logs_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `staff_logs_ibfk_3` FOREIGN KEY (`performed_by`) REFERENCES `reg` (`regID`);

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_user` FOREIGN KEY (`user_id`) REFERENCES `reg` (`regID`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_user` FOREIGN KEY (`user_id`) REFERENCES `reg` (`regID`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_status_logs`
--
ALTER TABLE `order_status_logs`
  ADD CONSTRAINT `order_status_logs_ibfk_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_status_logs_ibfk_user` FOREIGN KEY (`changed_by`) REFERENCES `reg` (`regID`) ON DELETE SET NULL;

--
-- Constraints for table `password_change_logs`
--
ALTER TABLE `password_change_logs`
  ADD CONSTRAINT `password_change_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `reg` (`regID`) ON DELETE CASCADE;

--
-- Constraints for table `reschedule_logs`
--
ALTER TABLE `reschedule_logs`
  ADD CONSTRAINT `reschedule_logs_ibfk_1` FOREIGN KEY (`reschedule_id`) REFERENCES `appointment_reschedules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `reg` (`regID`);

--
-- Constraints for table `staff_availability`
--
ALTER TABLE `staff_availability`
  ADD CONSTRAINT `staff_availability_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_documents`
--
ALTER TABLE `staff_documents`
  ADD CONSTRAINT `staff_documents_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_leave`
--
ALTER TABLE `staff_leave`
  ADD CONSTRAINT `staff_leave_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_leave_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `reg` (`regID`) ON DELETE SET NULL;

--
-- Constraints for table `staff_notifications`
--
ALTER TABLE `staff_notifications`
  ADD CONSTRAINT `staff_notifications_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_performance`
--
ALTER TABLE `staff_performance`
  ADD CONSTRAINT `staff_performance_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_ratings`
--
ALTER TABLE `staff_ratings`
  ADD CONSTRAINT `staff_ratings_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_ratings_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_ratings_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `reg` (`regID`) ON DELETE CASCADE;

--
-- Constraints for table `staff_rating_summary`
--
ALTER TABLE `staff_rating_summary`
  ADD CONSTRAINT `staff_rating_summary_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_schedule_exceptions`
--
ALTER TABLE `staff_schedule_exceptions`
  ADD CONSTRAINT `staff_schedule_exceptions_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_specializations`
--
ALTER TABLE `staff_specializations`
  ADD CONSTRAINT `staff_specializations_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_action_logs`
--
ALTER TABLE `user_action_logs`
  ADD CONSTRAINT `user_action_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `reg` (`regID`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_action_logs_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `reg` (`regID`) ON DELETE CASCADE;

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `reg` (`regID`) ON DELETE CASCADE;

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_ibfk_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlist_ibfk_user` FOREIGN KEY (`user_id`) REFERENCES `reg` (`regID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
