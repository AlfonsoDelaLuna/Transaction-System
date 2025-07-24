-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 15, 2025 at 11:37 AM
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
-- Database: `transaction_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `flavors`
--

CREATE TABLE `flavors` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `flavors`
--

INSERT INTO `flavors` (`id`, `name`) VALUES
(5, 'Cheesecake Series'),
(4, 'Choko N Series'),
(2, 'Coffee Based'),
(1, 'Non Coffee'),
(3, 'Oreo Series');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `order_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`order_items`)),
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','gcash') NOT NULL,
  `payment_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payment_details`)),
  `order_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `customer_name`, `order_items`, `total_amount`, `payment_method`, `payment_details`, `order_date`) VALUES
(1, 7, 'Walk-in Customer', '[{\"product\":{\"name\":\"Kopii Love\",\"id\":17},\"flavor\":\"Coffee Based\",\"size\":{\"name\":\"Small\",\"price\":59},\"add_ons\":[],\"sugar_level\":\"50%\",\"quantity\":1,\"totalPrice\":59}]', 59.00, 'gcash', '{\"method\":\"gcash\",\"transaction_no\":\"12345678\"}', '2025-06-15 08:46:42'),
(2, 7, 'Walk-in Customer', '[{\"product\":{\"name\":\"Miny Choko Berry\",\"id\":19},\"flavor\":\"Choko N Series\",\"size\":{\"name\":\"Small\",\"price\":69},\"add_ons\":[],\"sugar_level\":\"50%\",\"quantity\":1,\"totalPrice\":69}]', 69.00, 'cash', '{\"method\":\"cash\",\"cash_tendered\":200,\"change_given\":131}', '2025-06-15 08:47:29'),
(3, 7, 'Walk-in Customer', '[{\"product\":{\"name\":\"Kopii Love\",\"id\":17},\"flavor\":\"Coffee Based\",\"size\":{\"name\":\"Small\",\"price\":59},\"add_ons\":[],\"sugar_level\":\"50%\",\"quantity\":2,\"totalPrice\":118},{\"product\":{\"name\":\"Miny Choko Berry\",\"id\":19},\"flavor\":\"Choko N Series\",\"size\":{\"name\":\"Bundle Medium\",\"price\":149},\"add_ons\":[],\"sugar_level\":\"50%\",\"quantity\":1,\"totalPrice\":149}]', 267.00, 'gcash', '{\"method\":\"gcash\",\"transaction_no\":\"23456789\"}', '2025-06-15 08:48:36');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `flavors` varchar(255) DEFAULT NULL,
  `sizes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sizes`)),
  `add_ons` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`add_ons`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `flavors`, `sizes`, `add_ons`, `created_at`, `image`) VALUES
(16, 'Kreamy Ube', 'Non Coffee', '[{\"name\":\"Small\",\"price\":\"59\"},{\"name\":\"Medium\",\"price\":\"69\"},{\"name\":\"Large\",\"price\":\"79\"},{\"name\":\"B1 T1\",\"price\":\"99\"},{\"name\":\"Bundle Small\",\"price\":\"114\"},{\"name\":\"Bundle Medium\",\"price\":\"129\"},{\"name\":\"Bundle Large\",\"price\":\"149\"}]', '[{\"name\":\"aa\",\"price\":\"22\"}]', '2025-06-10 08:23:53', 'uploads/prod_6847eb99826379.32874333.png'),
(17, 'Kopii Love', 'Coffee Based', '[{\"name\":\"Small\",\"price\":\"59\"},{\"name\":\"Medium\",\"price\":\"69\"},{\"name\":\"Large\",\"price\":\"79\"},{\"name\":\"B1 T1\",\"price\":\"99\"},{\"name\":\"Bundle Small\",\"price\":\"114\"},{\"name\":\"Bundle Medium\",\"price\":\"129\"},{\"name\":\"Bundle Large\",\"price\":\"149\"}]', '[{\"name\":\"hh\",\"price\":\"33\"}]', '2025-06-10 08:25:20', 'uploads/prod_6847ebf050a0b2.21646914.png'),
(18, 'Strawberry Oreo', 'Oreo Series', '[{\"name\":\"Small\",\"price\":\"69\"},{\"name\":\"Medium\",\"price\":\"79\"},{\"name\":\"Large\",\"price\":\"89\"},{\"name\":\"B1 T1\",\"price\":\"109\"},{\"name\":\"Bundle Small\",\"price\":\"129\"},{\"name\":\"Bundle Medium\",\"price\":\"149\"},{\"name\":\"Bundle Large\",\"price\":\"169\"}]', '[{\"name\":\"ww\",\"price\":\"33\"}]', '2025-06-10 15:03:40', 'uploads/prod_6848494c942b56.86660229.png'),
(19, 'Miny Choko Berry', 'Choko N Series', '[{\"name\":\"Small\",\"price\":\"69\"},{\"name\":\"Medium\",\"price\":\"79\"},{\"name\":\"Large\",\"price\":\"95\"},{\"name\":\"B1 T1\",\"price\":\"109\"},{\"name\":\"Bundle Small\",\"price\":\"129\"},{\"name\":\"Bundle Medium\",\"price\":\"149\"},{\"name\":\"Bundle Large\",\"price\":\"179\"}]', '[{\"name\":\"ww\",\"price\":\"33\"}]', '2025-06-10 15:12:58', 'uploads/prod_68484b7a0ba039.43899814.png'),
(21, 'Strawberry Cheesecake', 'Cheesecake Series', '[{\"name\":\"Small\",\"price\":\"85\"},{\"name\":\"Medium\",\"price\":\"95\"},{\"name\":\"Large\",\"price\":\"105\"}]', '[{\"name\":\"ww\",\"price\":\"22\"}]', '2025-06-10 15:15:49', 'uploads/prod_68484c3f9d4536.77995904.png');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','gcash') NOT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `order_id`, `transaction_no`, `amount`, `payment_method`, `status`, `created_at`) VALUES
(15, 1, '12345678', 69.00, 'gcash', 'completed', '2025-06-11 07:28:39'),
(16, 2, NULL, 173.00, 'cash', 'completed', '2025-06-11 07:29:56'),
(17, 3, '98765743', 258.00, 'gcash', 'completed', '2025-06-11 07:41:23'),
(18, 4, NULL, 345.00, 'cash', 'completed', '2025-06-11 07:41:45'),
(19, 5, NULL, 118.00, 'cash', 'completed', '2025-06-11 07:45:15'),
(20, 1, '12345678', 59.00, 'gcash', 'completed', '2025-06-15 08:46:42'),
(21, 2, NULL, 69.00, 'cash', 'completed', '2025-06-15 08:47:29'),
(22, 3, '23456789', 267.00, 'gcash', 'completed', '2025-06-15 08:48:36');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$ew3YqGc/OJprl6g1NLrYsO0acX/aE850ydbw44YeTz2pPEoy2PwIm', 'System Administrator', 'admin@system.com', 'admin', 'active', '2025-05-24 10:56:28', '2025-06-02 15:50:52'),
(7, 'test', '$2y$10$J8729mbbM41l8UZ6Kyz3P.gsIZexMqJjwRNQNtDnHPgMgx6fZr2.W', 'test', 'test@email.com', 'user', 'active', '2025-06-02 15:45:28', '2025-06-10 08:21:30');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `flavors`
--
ALTER TABLE `flavors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `flavors`
--
ALTER TABLE `flavors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
