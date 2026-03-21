-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 21, 2026 at 03:17 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `budget_app`
--

-- --------------------------------------------------------

--
-- Table structure for table `budgets`
--

CREATE TABLE `budgets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `month` varchar(20) DEFAULT NULL,
  `total_budget` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `budgets`
--

INSERT INTO `budgets` (`id`, `user_id`, `month`, `total_budget`) VALUES
(1, 1, '2026-02', 25000.00),
(2, 4, '2026-03', 2000.00),
(3, 4, '2026-03', 4000.00),
(4, 4, '2026-03', 1000.00),
(5, 4, '2026-03', 10000.00),
(6, 4, '2026-03', 50000.00);

-- --------------------------------------------------------

--
-- Table structure for table `category_budgets`
--

CREATE TABLE `category_budgets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `month` varchar(20) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `allocated_amount` decimal(10,2) DEFAULT NULL,
  `percentage` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `category_budgets`
--

INSERT INTO `category_budgets` (`id`, `user_id`, `month`, `category`, `allocated_amount`, `percentage`) VALUES
(17, 4, '2026-03', 'Food', 20000.00, 40.00),
(18, 4, '2026-03', 'Transportation', 12500.00, 25.00),
(19, 4, '2026-03', 'Bills', 10000.00, 20.00),
(20, 4, '2026-03', 'Savings', 7500.00, 15.00);

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `user_id`, `amount`, `category`, `description`, `date`, `deleted_at`) VALUES
(1, 1, 500.00, 'Food', 'Lunch', '2026-02-17', NULL),
(2, 4, 500.00, 'Food', 'Daily lunch', '2026-03-13', '2026-03-21 21:26:07'),
(3, 4, 13000.00, 'Bills', 'Pambili RTX 3060', '2026-03-15', '2026-03-21 21:26:05'),
(4, 4, 2000.00, 'Transportation', 'Jeepney Ride', '2026-03-21', NULL),
(5, 4, 3000.00, 'Food', 'Daily Food', '2026-03-21', NULL),
(6, 4, 3000.00, 'Bills', 'Daily Bills', '2026-03-21', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `qr_codes`
--

CREATE TABLE `qr_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `qr_data` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qr_codes`
--

INSERT INTO `qr_codes` (`id`, `user_id`, `qr_data`) VALUES
(6, 4, 'https://www.youtube.com/@BroCodez');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `mobile` varchar(30) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `security_pin` varchar(255) DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `mobile`, `date_of_birth`, `password`, `security_pin`, `reset_token`, `reset_token_expires`, `avatar`, `city`, `country`, `created_at`) VALUES
(1, 'User_1', 'user1@gmail.com', '0912-345-6789', '0000-00-00', '$2y$10$cO1USq30ksKwD3dtiFGASeGhcQdtTqVzipYPQKcjCKRrZuM/XmQU.', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-20 12:23:14'),
(2, 'User_2', 'user2@gmail.com', '', NULL, '$2y$10$ddrzF1Dsr8crNutDjBeDRuLnQ2i/Oanofr/Sk9OPxl6BLMvBmNTtm', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-20 12:23:14'),
(4, 'User_3', 'user3@gmail.com', '', NULL, '$2y$10$2yD0pxXxRdc5arJrwNr3quxbKH8GPkYpK3xpMqbWQY7EYCwRn9bJm', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-20 12:23:14');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `budgets`
--
ALTER TABLE `budgets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_budgets_user_month` (`user_id`,`month`);

--
-- Indexes for table `category_budgets`
--
ALTER TABLE `category_budgets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category_budgets_user_month` (`user_id`,`month`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_expenses_user_date` (`user_id`,`date`);

--
-- Indexes for table `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_user_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `budgets`
--
ALTER TABLE `budgets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `category_budgets`
--
ALTER TABLE `category_budgets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `qr_codes`
--
ALTER TABLE `qr_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
