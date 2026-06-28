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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointment_edit_logs`
--
ALTER TABLE `appointment_edit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_status_logs`
--
ALTER TABLE `order_status_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_change_logs`
--
ALTER TABLE `password_change_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reg`
--
ALTER TABLE `reg`
  MODIFY `regID` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reschedule_logs`
--
ALTER TABLE `reschedule_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_action_logs`
--
ALTER TABLE `staff_action_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_performance`
--
ALTER TABLE `staff_performance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_ratings`
--
ALTER TABLE `staff_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_rating_summary`
--
ALTER TABLE `staff_rating_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
