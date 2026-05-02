-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 02, 2026 at 07:56 PM
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
-- Database: `bayantap_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `billings`
--

CREATE TABLE `billings` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `billing_month` varchar(20) NOT NULL,
  `previous_reading` int(11) NOT NULL,
  `current_reading` int(11) NOT NULL,
  `usage_m3` int(11) NOT NULL,
  `amount_due` decimal(10,2) NOT NULL,
  `status` enum('paid','unpaid','pending','started') DEFAULT 'unpaid',
  `paid_date` datetime DEFAULT NULL,
  `receipt_no` varchar(50) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `previous_reading_image` varchar(255) DEFAULT NULL,
  `current_reading_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billings`
--

INSERT INTO `billings` (`id`, `resident_id`, `billing_month`, `previous_reading`, `current_reading`, `usage_m3`, `amount_due`, `status`, `paid_date`, `receipt_no`, `remarks`, `previous_reading_image`, `current_reading_image`) VALUES
(64, 34, 'May 2026', 0, 17, 17, 572.90, 'paid', '2026-05-02 18:46:04', 'MV-2026-0064', NULL, NULL, NULL),
(65, 34, 'Jun 2026', 17, 0, 0, 0.00, 'pending', NULL, NULL, NULL, NULL, NULL),
(66, 35, 'May 2026', 0, 67, 67, 2257.90, 'unpaid', NULL, NULL, 'test101', NULL, NULL),
(67, 35, 'Jun 2026', 0, 0, 0, 0.00, 'pending', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `residents`
--

CREATE TABLE `residents` (
  `id` int(11) NOT NULL,
  `household_id` varchar(20) DEFAULT NULL,
  `block_no` varchar(20) NOT NULL,
  `lot_no` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `initial_meter` int(11) DEFAULT 0,
  `monthly_rate` decimal(10,2) DEFAULT 0.00,
  `contact_number` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `status` enum('paid','unpaid','pending','started') DEFAULT 'unpaid',
  `access_token` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `residents`
--

INSERT INTO `residents` (`id`, `household_id`, `block_no`, `lot_no`, `full_name`, `initial_meter`, `monthly_rate`, `contact_number`, `email`, `status`, `access_token`, `created_at`) VALUES
(34, 'BT-0001', 'Blk 1', 'Lot 1', 'Ian Reyes', 0, 33.70, '0912-345-6789', 'ianreyes1818@gmail.com', 'paid', '01995111ff125df550195b9dbde1ab18', '2026-05-02 15:44:36'),
(35, 'BT-0002', 'Blk 6', 'Lot 7', 'Justin Basco', 0, 33.70, '0967-768-1303', 'reignbasco29@gmail.com', 'paid', 'ab64915a09a462353d2ea7822a22957b', '2026-05-02 15:45:29');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('current_rate', '33.70', 'Price per cubic meter for water usage'),
('smtp_from_email', 'bayantap58@gmail.com', NULL),
('smtp_from_name', 'BayanTap Water District', NULL),
('smtp_host', 'smtp.gmail.com', NULL),
('smtp_pass', 'jedrihqudfzakejh', NULL),
('smtp_port', '587', NULL),
('smtp_user', 'bayantap58@gmail.com', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `receipt_no` varchar(50) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('complete','partial') DEFAULT NULL,
  `treasurer_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `receipt_no`, `resident_id`, `amount_paid`, `payment_date`, `status`, `treasurer_id`) VALUES
(14, 'MV-2026-0064', 34, 572.90, '2026-05-02 16:46:04', 'complete', 3);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('treasurer','admin') DEFAULT 'treasurer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `created_at`) VALUES
(1, 'treasurer', '$2y$10$yE6nE4mpUIkW1xh4FsqEy.osgCKQx3vmzS8idVk9psPvduh4kkRRi', 'treasurer', '2026-04-03 11:54:08'),
(3, 'admin', '$2y$10$NU1SA5nTdK44QsEl5XsYEeMccBYz4dB/fecmDyJlSq67/xDV9pkL6', 'admin', '2026-04-22 08:15:52');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `billings`
--
ALTER TABLE `billings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `resident_id` (`resident_id`);

--
-- Indexes for table `residents`
--
ALTER TABLE `residents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `access_token` (`access_token`),
  ADD UNIQUE KEY `household_id` (`household_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_no` (`receipt_no`),
  ADD KEY `resident_id` (`resident_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `billings`
--
ALTER TABLE `billings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `residents`
--
ALTER TABLE `residents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `billings`
--
ALTER TABLE `billings`
  ADD CONSTRAINT `billings_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
