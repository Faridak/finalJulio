-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 07, 2025 at 11:13 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `finaljulio`
--

-- --------------------------------------------------------

--
-- Table structure for table `banking_details`
--

CREATE TABLE `banking_details` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `account_type` enum('checking','savings','business') DEFAULT 'checking',
  `bank_name` varchar(100) DEFAULT NULL,
  `account_holder_name` varchar(200) DEFAULT NULL,
  `account_number_encrypted` varchar(255) DEFAULT NULL,
  `routing_number_encrypted` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `banking_details`
--

INSERT INTO `banking_details` (`id`, `user_id`, `account_type`, `bank_name`, `account_holder_name`, `account_number_encrypted`, `routing_number_encrypted`, `is_verified`, `is_default`, `created_at`) VALUES
(1, 2, 'business', 'Chase Business Bank', 'John Smith', '****1234', '****5678', 1, 1, '2025-08-07 02:50:12'),
(2, 3, 'business', 'Bank of America Business', 'Sarah Johnson', '****5678', '****9012', 1, 1, '2025-08-07 02:50:12'),
(3, 4, 'checking', 'Wells Fargo', 'Mike Davis', '****9876', '****3456', 1, 1, '2025-08-07 02:50:12'),
(4, 5, 'savings', 'Capital One', 'Emily Wilson', '****5432', '****7890', 1, 1, '2025-08-07 02:50:12'),
(5, 6, 'checking', 'Chase Bank', 'David Brown', '****1111', '****2222', 1, 1, '2025-08-07 02:50:12'),
(6, 7, 'business', 'Silicon Valley Bank', 'Tech Store LLC', '****3333', '****4444', 1, 1, '2025-08-07 02:50:12'),
(7, 8, 'business', 'Fashion Credit Union', 'Fashion Hub Inc', '****5555', '****6666', 1, 1, '2025-08-07 02:50:12'),
(8, 11, 'business', 'Chase bank', 'Uctrl', '****6789', '****2334', 1, 1, '2025-08-07 03:19:09');

-- --------------------------------------------------------

--
-- Table structure for table `cities`
--

CREATE TABLE `cities` (
  `id` int(11) NOT NULL,
  `state_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `postal_code_pattern` varchar(20) DEFAULT NULL,
  `timezone` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_major_city` tinyint(1) DEFAULT 0,
  `population` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'NULL if guest contact',
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','in_progress','replied','closed') DEFAULT 'pending',
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `department` enum('general','support','sales','billing','technical') DEFAULT 'general',
  `assigned_to` int(11) DEFAULT NULL COMMENT 'Admin user ID',
  `replied_at` datetime DEFAULT NULL,
  `replied_by` int(11) DEFAULT NULL COMMENT 'Admin user ID who replied',
  `reply_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `countries`
--

CREATE TABLE `countries` (
  `id` int(11) NOT NULL,
  `code` varchar(3) NOT NULL COMMENT 'ISO 3166-1 alpha-3 code',
  `name` varchar(100) NOT NULL,
  `currency_code` varchar(3) NOT NULL,
  `currency_symbol` varchar(10) NOT NULL,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `latitude` decimal(10,8) NOT NULL COMMENT 'Country center latitude',
  `longitude` decimal(11,8) NOT NULL COMMENT 'Country center longitude',
  `timezone` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `shipping_allowed` tinyint(1) DEFAULT 1,
  `customs_required` tinyint(1) DEFAULT 0,
  `max_declared_value` decimal(10,2) DEFAULT 10000.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `countries`
--

INSERT INTO `countries` (`id`, `code`, `name`, `currency_code`, `currency_symbol`, `tax_rate`, `latitude`, `longitude`, `timezone`, `is_active`, `shipping_allowed`, `customs_required`, `max_declared_value`, `created_at`) VALUES
(1, 'USA', 'United States', 'USD', '$', 0.00, 39.82830000, -98.57950000, 'America/New_York', 1, 1, 0, 10000.00, '2025-08-07 03:58:39'),
(2, 'CAN', 'Canada', 'CAD', 'C$', 5.00, 56.13040000, -106.34680000, 'America/Toronto', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(3, 'MEX', 'Mexico', 'MXN', '$', 16.00, 23.63450000, -102.55280000, 'America/Mexico_City', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(4, 'GBR', 'United Kingdom', 'GBP', '£', 20.00, 55.37810000, -3.43600000, 'Europe/London', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(5, 'DEU', 'Germany', 'EUR', '€', 19.00, 51.16570000, 10.45150000, 'Europe/Berlin', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(6, 'FRA', 'France', 'EUR', '€', 20.00, 46.22760000, 2.21370000, 'Europe/Paris', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(7, 'ITA', 'Italy', 'EUR', '€', 22.00, 41.87190000, 12.56740000, 'Europe/Rome', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(8, 'ESP', 'Spain', 'EUR', '€', 21.00, 40.46370000, -3.74920000, 'Europe/Madrid', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(9, 'NLD', 'Netherlands', 'EUR', '€', 21.00, 52.13260000, 5.29130000, 'Europe/Amsterdam', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(10, 'BEL', 'Belgium', 'EUR', '€', 21.00, 50.50390000, 4.46990000, 'Europe/Brussels', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(11, 'CHE', 'Switzerland', 'CHF', 'Fr', 7.70, 46.81820000, 8.22750000, 'Europe/Zurich', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(12, 'AUT', 'Austria', 'EUR', '€', 20.00, 47.51620000, 14.55010000, 'Europe/Vienna', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(13, 'SWE', 'Sweden', 'SEK', 'kr', 25.00, 60.12820000, 18.64350000, 'Europe/Stockholm', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(14, 'NOR', 'Norway', 'NOK', 'kr', 25.00, 60.47200000, 8.46890000, 'Europe/Oslo', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(15, 'DNK', 'Denmark', 'DKK', 'kr', 25.00, 56.26390000, 9.50180000, 'Europe/Copenhagen', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(16, 'FIN', 'Finland', 'EUR', '€', 24.00, 61.92410000, 25.74820000, 'Europe/Helsinki', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(17, 'POL', 'Poland', 'PLN', 'zł', 23.00, 51.91940000, 19.14510000, 'Europe/Warsaw', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(18, 'CZE', 'Czech Republic', 'CZK', 'Kč', 21.00, 49.81750000, 15.47300000, 'Europe/Prague', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(19, 'HUN', 'Hungary', 'HUF', 'Ft', 27.00, 47.16250000, 19.50330000, 'Europe/Budapest', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(20, 'ROU', 'Romania', 'RON', 'lei', 19.00, 45.94320000, 24.96680000, 'Europe/Bucharest', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(21, 'BGR', 'Bulgaria', 'BGN', 'лв', 20.00, 42.73390000, 25.48580000, 'Europe/Sofia', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(22, 'HRV', 'Croatia', 'EUR', '€', 25.00, 45.10000000, 15.20000000, 'Europe/Zagreb', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(23, 'GRC', 'Greece', 'EUR', '€', 24.00, 39.07420000, 21.82430000, 'Europe/Athens', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(24, 'PRT', 'Portugal', 'EUR', '€', 23.00, 39.39990000, -8.22450000, 'Europe/Lisbon', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(25, 'IRL', 'Ireland', 'EUR', '€', 23.00, 53.41290000, -8.24390000, 'Europe/Dublin', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(26, 'JPN', 'Japan', 'JPY', '¥', 10.00, 36.20480000, 138.25290000, 'Asia/Tokyo', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(27, 'CHN', 'China', 'CNY', '¥', 13.00, 35.86170000, 104.19540000, 'Asia/Shanghai', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(28, 'KOR', 'South Korea', 'KRW', '₩', 10.00, 35.90780000, 127.76690000, 'Asia/Seoul', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(29, 'SGP', 'Singapore', 'SGD', 'S$', 7.00, 1.35210000, 103.81980000, 'Asia/Singapore', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(30, 'HKG', 'Hong Kong', 'HKD', 'HK$', 0.00, 22.31930000, 114.16940000, 'Asia/Hong_Kong', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(31, 'TWN', 'Taiwan', 'TWD', 'NT$', 5.00, 23.69780000, 120.96050000, 'Asia/Taipei', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(32, 'THA', 'Thailand', 'THB', '฿', 7.00, 15.87000000, 100.99250000, 'Asia/Bangkok', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(33, 'MYS', 'Malaysia', 'MYR', 'RM', 6.00, 4.21050000, 101.97580000, 'Asia/Kuala_Lumpur', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(34, 'IDN', 'Indonesia', 'IDR', 'Rp', 10.00, -0.78930000, 113.92130000, 'Asia/Jakarta', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(35, 'PHL', 'Philippines', 'PHP', '₱', 12.00, 12.87970000, 121.77400000, 'Asia/Manila', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(36, 'VNM', 'Vietnam', 'VND', '₫', 10.00, 14.05830000, 108.27720000, 'Asia/Ho_Chi_Minh', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(37, 'IND', 'India', 'INR', '₹', 18.00, 20.59370000, 78.96290000, 'Asia/Kolkata', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(38, 'AUS', 'Australia', 'AUD', 'A$', 10.00, -25.27440000, 133.77510000, 'Australia/Sydney', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(39, 'NZL', 'New Zealand', 'NZD', 'NZ$', 15.00, -40.90060000, 174.88600000, 'Pacific/Auckland', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(40, 'ARE', 'United Arab Emirates', 'AED', 'د.إ', 5.00, 23.42410000, 53.84780000, 'Asia/Dubai', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(41, 'SAU', 'Saudi Arabia', 'SAR', '﷼', 15.00, 23.88590000, 45.07920000, 'Asia/Riyadh', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(42, 'ISR', 'Israel', 'ILS', '₪', 17.00, 31.04610000, 34.85160000, 'Asia/Jerusalem', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(43, 'TUR', 'Turkey', 'TRY', '₺', 18.00, 38.96370000, 35.24330000, 'Europe/Istanbul', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(44, 'BRA', 'Brazil', 'BRL', 'R$', 17.00, -14.23500000, -51.92530000, 'America/Sao_Paulo', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(45, 'ARG', 'Argentina', 'ARS', '$', 21.00, -38.41610000, -63.61670000, 'America/Argentina/Buenos_Aires', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(46, 'CHL', 'Chile', 'CLP', '$', 19.00, -35.67510000, -71.54300000, 'America/Santiago', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(47, 'COL', 'Colombia', 'COP', '$', 19.00, 4.57090000, -74.29730000, 'America/Bogota', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(48, 'PER', 'Peru', 'PEN', 'S/', 18.00, -9.19000000, -75.01520000, 'America/Lima', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(49, 'ZAF', 'South Africa', 'ZAR', 'R', 15.00, -30.55950000, 22.93750000, 'Africa/Johannesburg', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(50, 'EGY', 'Egypt', 'EGP', '£', 14.00, 26.09750000, 31.23570000, 'Africa/Cairo', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(51, 'NGA', 'Nigeria', 'NGN', '₦', 7.50, 9.08200000, 8.67530000, 'Africa/Lagos', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(52, 'KEN', 'Kenya', 'KES', 'KSh', 16.00, -0.02360000, 37.90620000, 'Africa/Nairobi', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(53, 'MAR', 'Morocco', 'MAD', 'د.م.', 20.00, 31.79170000, -7.09260000, 'Africa/Casablanca', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(54, 'RUS', 'Russia', 'RUB', '₽', 20.00, 61.52400000, 105.31880000, 'Europe/Moscow', 1, 1, 1, 10000.00, '2025-08-07 03:58:39'),
(55, 'UKR', 'Ukraine', 'UAH', '₴', 20.00, 48.37940000, 31.16560000, 'Europe/Kiev', 1, 1, 1, 10000.00, '2025-08-07 03:58:39');

-- --------------------------------------------------------

--
-- Table structure for table `currency_rates`
--

CREATE TABLE `currency_rates` (
  `id` int(11) NOT NULL,
  `from_currency` varchar(3) NOT NULL,
  `to_currency` varchar(3) NOT NULL,
  `rate` decimal(12,6) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `currency_rates`
--

INSERT INTO `currency_rates` (`id`, `from_currency`, `to_currency`, `rate`, `updated_at`) VALUES
(1, 'USD', 'CAD', 1.350000, '2025-08-07 03:58:39'),
(2, 'USD', 'MXN', 17.500000, '2025-08-07 03:58:39'),
(3, 'USD', 'EUR', 0.850000, '2025-08-07 03:58:39'),
(4, 'USD', 'GBP', 0.730000, '2025-08-07 03:58:39'),
(5, 'USD', 'JPY', 110.000000, '2025-08-07 03:58:39'),
(6, 'USD', 'AUD', 1.450000, '2025-08-07 03:58:39'),
(7, 'USD', 'CHF', 0.920000, '2025-08-07 03:58:39'),
(8, 'USD', 'SEK', 8.750000, '2025-08-07 03:58:39'),
(9, 'USD', 'NOK', 8.500000, '2025-08-07 03:58:39'),
(10, 'USD', 'DKK', 6.350000, '2025-08-07 03:58:39'),
(11, 'USD', 'PLN', 3.850000, '2025-08-07 03:58:39'),
(12, 'USD', 'CZK', 21.500000, '2025-08-07 03:58:39'),
(13, 'USD', 'HUF', 295.000000, '2025-08-07 03:58:39'),
(14, 'USD', 'RON', 4.150000, '2025-08-07 03:58:39'),
(15, 'USD', 'BGN', 1.660000, '2025-08-07 03:58:39'),
(16, 'USD', 'HRK', 6.400000, '2025-08-07 03:58:39'),
(17, 'USD', 'CNY', 6.450000, '2025-08-07 03:58:39'),
(18, 'USD', 'KRW', 1180.000000, '2025-08-07 03:58:39'),
(19, 'USD', 'SGD', 1.350000, '2025-08-07 03:58:39'),
(20, 'USD', 'HKD', 7.800000, '2025-08-07 03:58:39'),
(21, 'USD', 'TWD', 28.500000, '2025-08-07 03:58:39'),
(22, 'USD', 'THB', 33.000000, '2025-08-07 03:58:39'),
(23, 'USD', 'MYR', 4.150000, '2025-08-07 03:58:39'),
(24, 'USD', 'IDR', 14250.000000, '2025-08-07 03:58:39'),
(25, 'USD', 'PHP', 50.000000, '2025-08-07 03:58:39'),
(26, 'USD', 'VND', 23000.000000, '2025-08-07 03:58:39'),
(27, 'USD', 'INR', 74.500000, '2025-08-07 03:58:39'),
(28, 'USD', 'NZD', 1.420000, '2025-08-07 03:58:39'),
(29, 'USD', 'AED', 3.670000, '2025-08-07 03:58:39'),
(30, 'USD', 'SAR', 3.750000, '2025-08-07 03:58:39'),
(31, 'USD', 'ILS', 3.250000, '2025-08-07 03:58:39'),
(32, 'USD', 'TRY', 8.500000, '2025-08-07 03:58:39'),
(33, 'USD', 'BRL', 5.200000, '2025-08-07 03:58:39'),
(34, 'USD', 'ARS', 98.500000, '2025-08-07 03:58:39'),
(35, 'USD', 'CLP', 750.000000, '2025-08-07 03:58:39'),
(36, 'USD', 'COP', 3650.000000, '2025-08-07 03:58:39'),
(37, 'USD', 'PEN', 3.600000, '2025-08-07 03:58:39'),
(38, 'USD', 'ZAR', 14.500000, '2025-08-07 03:58:39'),
(39, 'USD', 'EGP', 15.700000, '2025-08-07 03:58:39'),
(40, 'USD', 'NGN', 410.000000, '2025-08-07 03:58:39'),
(41, 'USD', 'KES', 108.000000, '2025-08-07 03:58:39'),
(42, 'USD', 'MAD', 9.000000, '2025-08-07 03:58:39'),
(43, 'USD', 'RUB', 73.500000, '2025-08-07 03:58:39'),
(44, 'USD', 'UAH', 27.000000, '2025-08-07 03:58:39');

-- --------------------------------------------------------

--
-- Table structure for table `customer_preferences`
--

CREATE TABLE `customer_preferences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `favorite_categories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`favorite_categories`)),
  `email_notifications` tinyint(1) DEFAULT 1,
  `sms_notifications` tinyint(1) DEFAULT 0,
  `marketing_emails` tinyint(1) DEFAULT 1,
  `order_updates` tinyint(1) DEFAULT 1,
  `newsletter` tinyint(1) DEFAULT 0,
  `language` varchar(10) DEFAULT 'en',
  `currency` varchar(10) DEFAULT 'USD',
  `timezone` varchar(50) DEFAULT 'America/New_York',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_preferences`
--

INSERT INTO `customer_preferences` (`id`, `user_id`, `favorite_categories`, `email_notifications`, `sms_notifications`, `marketing_emails`, `order_updates`, `newsletter`, `language`, `currency`, `timezone`, `created_at`, `updated_at`) VALUES
(1, 4, '[\"Electronics\", \"Sports\", \"Accessories\"]', 1, 0, 1, 1, 1, 'en', 'USD', 'America/Los_Angeles', '2025-08-07 02:50:12', '2025-08-07 02:50:12'),
(2, 5, '[\"Home\", \"Beauty\", \"Sports\"]', 1, 1, 0, 1, 0, 'en', 'USD', 'America/Chicago', '2025-08-07 02:50:12', '2025-08-07 02:50:12'),
(3, 6, '[\"Sports\", \"Electronics\", \"Accessories\"]', 1, 0, 1, 1, 1, 'en', 'USD', 'America/Chicago', '2025-08-07 02:50:12', '2025-08-07 02:50:12'),
(4, 2, '[\"Electronics\"]', 1, 1, 1, 1, 0, 'en', 'USD', 'America/Los_Angeles', '2025-08-07 02:50:12', '2025-08-07 02:50:12'),
(5, 3, '[\"Clothing\", \"Beauty\", \"Accessories\"]', 1, 0, 1, 1, 1, 'en', 'USD', 'America/New_York', '2025-08-07 02:50:12', '2025-08-07 02:50:12');

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` int(11) NOT NULL,
  `question` varchar(500) NOT NULL,
  `answer` text NOT NULL,
  `category` varchar(100) DEFAULT 'General',
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `view_count` int(11) DEFAULT 0,
  `helpful_count` int(11) DEFAULT 0,
  `not_helpful_count` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL COMMENT 'Admin user ID who created this FAQ',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faqs`
--

INSERT INTO `faqs` (`id`, `question`, `answer`, `category`, `sort_order`, `is_active`, `view_count`, `helpful_count`, `not_helpful_count`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'What is VentDepot?', 'VentDepot is your premier online destination for quality products. We connect customers with trusted merchants offering a wide variety of items at competitive prices.', 'General', 1, 1, 0, 0, 0, NULL, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(2, 'How do I create an account?', 'Click the \"Sign Up\" button in the top right corner of any page. Fill out the registration form with your email address and create a secure password. You\'ll receive a confirmation email to verify your account.', 'General', 2, 1, 0, 0, 0, NULL, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(3, 'Is my personal information secure?', 'Yes, we take your privacy and security seriously. We use industry-standard encryption and security measures to protect your personal and payment information.', 'General', 3, 1, 0, 0, 0, NULL, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(4, 'How much does shipping cost?', 'Shipping costs are calculated based on the weight, size, and destination of your order. We offer free standard shipping on orders over $50 within the United States.', 'Shipping', 1, 1, 0, 0, 0, NULL, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(5, 'How long does shipping take?', 'Standard shipping typically takes 3-7 business days within the United States. Express shipping options are available for faster delivery. International shipping may take 7-21 business days.', 'Shipping', 2, 1, 0, 0, 0, NULL, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(6, 'Do you ship internationally?', 'Yes, we ship to most countries worldwide. International shipping rates and delivery times vary by destination. Customs fees and import duties may apply and are the responsibility of the customer.', 'Shipping', 3, 1, 0, 0, 0, NULL, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(7, 'How can I track my order?', 'Once your order ships, you\'ll receive a tracking number via email. You can track your package on our website or directly on the carrier\'s website using this tracking number.', 'Shipping', 4, 1, 0, 0, 0, NULL, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(8, 'What is your return policy?', 'We accept returns within 30 days of delivery for most items in original condition. Items must be unused, in original packaging, and include all accessories.', 'Returns', 1, 1, 0, 0, 0, NULL, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(9, 'How do I return an item?', 'Contact our customer service team to initiate a return. We\'ll provide you with a prepaid return label and instructions. Package the item securely and drop it off at any authorized location.', 'Returns', 2, 1, 0, 0, 0, NULL, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(10, 'How long does it take to process a refund?', 'Refunds are typically processed within 5-7 business days after we receive your returned item. The refund will be issued to your original payment method.', 'Returns', 3, 1, 0, 0, 0, NULL, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(11, 'What payment methods do you accept?', 'We accept all major credit cards (Visa, MasterCard, American Express, Discover), PayPal, and other secure payment methods. All transactions are processed securely.', 'Payment', 1, 1, 0, 0, 0, NULL, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(12, 'Is it safe to enter my credit card information?', 'Yes, our checkout process uses SSL encryption and industry-standard security measures to protect your payment information. We never store your complete credit card details.', 'Payment', 2, 1, 0, 0, 0, NULL, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(13, 'Can I save my payment information for future orders?', 'Yes, you can securely save your payment methods in your account for faster checkout. This information is encrypted and stored securely.', 'Payment', 3, 1, 0, 0, 0, NULL, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(14, 'How do I reset my password?', 'Click \"Forgot Password\" on the login page and enter your email address. We\'ll send you a secure link to reset your password.', 'Account', 1, 1, 0, 0, 0, NULL, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(15, 'How do I update my account information?', 'Log into your account and go to \"My Profile\" to update your personal information, addresses, and preferences.', 'Account', 2, 1, 0, 0, 0, NULL, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(16, 'Can I change my email address?', 'Yes, you can update your email address in your account settings. You\'ll need to verify the new email address before the change takes effect.', 'Account', 3, 1, 0, 0, 0, NULL, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(17, 'What is VentDepot?', 'VentDepot is your premier online destination for quality products. We connect customers with trusted merchants offering a wide variety of items at competitive prices.', 'General', 1, 1, 0, 0, 0, NULL, '2025-08-07 07:01:15', '2025-08-07 07:01:15'),
(18, 'How do I create an account?', 'Click the \"Sign Up\" button in the top right corner of any page. Fill out the registration form with your email address and create a secure password. You\'ll receive a confirmation email to verify your account.', 'General', 2, 1, 0, 0, 0, NULL, '2025-08-07 07:01:15', '2025-08-07 07:01:15'),
(19, 'Is my personal information secure?', 'Yes, we take your privacy and security seriously. We use industry-standard encryption and security measures to protect your personal and payment information.', 'General', 3, 1, 0, 0, 0, NULL, '2025-08-07 07:01:15', '2025-08-07 07:01:15'),
(20, 'How much does shipping cost?', 'Shipping costs are calculated based on the weight, size, and destination of your order. We offer free standard shipping on orders over $50 within the United States.', 'Shipping', 1, 1, 0, 0, 0, NULL, '2025-08-07 07:01:15', '2025-08-07 07:01:15'),
(21, 'How long does shipping take?', 'Standard shipping typically takes 3-7 business days within the United States. Express shipping options are available for faster delivery. International shipping may take 7-21 business days.', 'Shipping', 2, 1, 0, 0, 0, NULL, '2025-08-07 07:01:15', '2025-08-07 07:01:15'),
(22, 'Do you ship internationally?', 'Yes, we ship to most countries worldwide. International shipping rates and delivery times vary by destination. Customs fees and import duties may apply and are the responsibility of the customer.', 'Shipping', 3, 1, 0, 0, 0, NULL, '2025-08-07 07:01:15', '2025-08-07 07:01:15'),
(23, 'How can I track my order?', 'Once your order ships, you\'ll receive a tracking number via email. You can track your package on our website or directly on the carrier\'s website using this tracking number.', 'Shipping', 4, 1, 0, 0, 0, NULL, '2025-08-07 07:01:15', '2025-08-07 07:01:15'),
(24, 'What is your return policy?', 'We accept returns within 30 days of delivery for most items in original condition. Items must be unused, in original packaging, and include all accessories.', 'Returns', 1, 1, 0, 0, 0, NULL, '2025-08-07 07:01:15', '2025-08-07 07:01:15'),
(25, 'How do I return an item?', 'Contact our customer service team to initiate a return. We\'ll provide you with a prepaid return label and instructions. Package the item securely and drop it off at any authorized location.', 'Returns', 2, 1, 0, 0, 0, NULL, '2025-08-07 07:01:15', '2025-08-07 07:01:15'),
(26, 'How long does it take to process a refund?', 'Refunds are typically processed within 5-7 business days after we receive your returned item. The refund will be issued to your original payment method.', 'Returns', 3, 1, 0, 0, 0, NULL, '2025-08-07 07:01:15', '2025-08-07 07:01:15'),
(27, 'What payment methods do you accept?', 'We accept all major credit cards (Visa, MasterCard, American Express, Discover), PayPal, and other secure payment methods. All transactions are processed securely.', 'Payment', 1, 1, 0, 0, 0, NULL, '2025-08-07 07:01:15', '2025-08-07 07:01:15'),
(28, 'Is it safe to enter my credit card information?', 'Yes, our checkout process uses SSL encryption and industry-standard security measures to protect your payment information. We never store your complete credit card details.', 'Payment', 2, 1, 0, 0, 0, NULL, '2025-08-07 07:01:15', '2025-08-07 07:01:15'),
(29, 'Can I save my payment information for future orders?', 'Yes, you can securely save your payment methods in your account for faster checkout. This information is encrypted and stored securely.', 'Payment', 3, 1, 0, 0, 0, NULL, '2025-08-07 07:01:15', '2025-08-07 07:01:15'),
(30, 'How do I reset my password?', 'Click \"Forgot Password\" on the login page and enter your email address. We\'ll send you a secure link to reset your password.', 'Account', 1, 1, 0, 0, 0, NULL, '2025-08-07 07:01:15', '2025-08-07 07:01:15'),
(31, 'How do I update my account information?', 'Log into your account and go to \"My Profile\" to update your personal information, addresses, and preferences.', 'Account', 2, 1, 0, 0, 0, NULL, '2025-08-07 07:01:15', '2025-08-07 07:01:15'),
(32, 'Can I change my email address?', 'Yes, you can update your email address in your account settings. You\'ll need to verify the new email address before the change takes effect.', 'Account', 3, 1, 0, 0, 0, NULL, '2025-08-07 07:01:15', '2025-08-07 07:01:15'),
(33, 'What is VentDepot?', 'VentDepot is your premier online destination for quality products. We connect customers with trusted merchants offering a wide variety of items at competitive prices.', 'General', 1, 1, 0, 0, 0, NULL, '2025-08-07 07:03:24', '2025-08-07 07:03:24'),
(34, 'How do I create an account?', 'Click the \"Sign Up\" button in the top right corner of any page. Fill out the registration form with your email address and create a secure password. You\'ll receive a confirmation email to verify your account.', 'General', 2, 1, 0, 0, 0, NULL, '2025-08-07 07:03:24', '2025-08-07 07:03:24'),
(35, 'Is my personal information secure?', 'Yes, we take your privacy and security seriously. We use industry-standard encryption and security measures to protect your personal and payment information.', 'General', 3, 1, 0, 0, 0, NULL, '2025-08-07 07:03:24', '2025-08-07 07:03:24'),
(36, 'How much does shipping cost?', 'Shipping costs are calculated based on the weight, size, and destination of your order. We offer free standard shipping on orders over $50 within the United States.', 'Shipping', 1, 1, 0, 0, 0, NULL, '2025-08-07 07:03:24', '2025-08-07 07:03:24'),
(37, 'How long does shipping take?', 'Standard shipping typically takes 3-7 business days within the United States. Express shipping options are available for faster delivery. International shipping may take 7-21 business days.', 'Shipping', 2, 1, 0, 0, 0, NULL, '2025-08-07 07:03:24', '2025-08-07 07:03:24'),
(38, 'Do you ship internationally?', 'Yes, we ship to most countries worldwide. International shipping rates and delivery times vary by destination. Customs fees and import duties may apply and are the responsibility of the customer.', 'Shipping', 3, 1, 0, 0, 0, NULL, '2025-08-07 07:03:24', '2025-08-07 07:03:24'),
(39, 'How can I track my order?', 'Once your order ships, you\'ll receive a tracking number via email. You can track your package on our website or directly on the carrier\'s website using this tracking number.', 'Shipping', 4, 1, 0, 0, 0, NULL, '2025-08-07 07:03:24', '2025-08-07 07:03:24'),
(40, 'What is your return policy?', 'We accept returns within 30 days of delivery for most items in original condition. Items must be unused, in original packaging, and include all accessories.', 'Returns', 1, 1, 0, 0, 0, NULL, '2025-08-07 07:03:24', '2025-08-07 07:03:24'),
(41, 'How do I return an item?', 'Contact our customer service team to initiate a return. We\'ll provide you with a prepaid return label and instructions. Package the item securely and drop it off at any authorized location.', 'Returns', 2, 1, 0, 0, 0, NULL, '2025-08-07 07:03:24', '2025-08-07 07:03:24'),
(42, 'How long does it take to process a refund?', 'Refunds are typically processed within 5-7 business days after we receive your returned item. The refund will be issued to your original payment method.', 'Returns', 3, 1, 0, 0, 0, NULL, '2025-08-07 07:03:24', '2025-08-07 07:03:24'),
(43, 'What payment methods do you accept?', 'We accept all major credit cards (Visa, MasterCard, American Express, Discover), PayPal, and other secure payment methods. All transactions are processed securely.', 'Payment', 1, 1, 0, 0, 0, NULL, '2025-08-07 07:03:24', '2025-08-07 07:03:24'),
(44, 'Is it safe to enter my credit card information?', 'Yes, our checkout process uses SSL encryption and industry-standard security measures to protect your payment information. We never store your complete credit card details.', 'Payment', 2, 1, 0, 0, 0, NULL, '2025-08-07 07:03:24', '2025-08-07 07:03:24'),
(45, 'Can I save my payment information for future orders?', 'Yes, you can securely save your payment methods in your account for faster checkout. This information is encrypted and stored securely.', 'Payment', 3, 1, 0, 0, 0, NULL, '2025-08-07 07:03:24', '2025-08-07 07:03:24'),
(46, 'How do I reset my password?', 'Click \"Forgot Password\" on the login page and enter your email address. We\'ll send you a secure link to reset your password.', 'Account', 1, 1, 0, 0, 0, NULL, '2025-08-07 07:03:24', '2025-08-07 07:03:24'),
(47, 'How do I update my account information?', 'Log into your account and go to \"My Profile\" to update your personal information, addresses, and preferences.', 'Account', 2, 1, 0, 0, 0, NULL, '2025-08-07 07:03:24', '2025-08-07 07:03:24'),
(48, 'Can I change my email address?', 'Yes, you can update your email address in your account settings. You\'ll need to verify the new email address before the change takes effect.', 'Account', 3, 1, 0, 0, 0, NULL, '2025-08-07 07:03:24', '2025-08-07 07:03:24');

-- --------------------------------------------------------

--
-- Table structure for table `faq_feedback`
--

CREATE TABLE `faq_feedback` (
  `id` int(11) NOT NULL,
  `faq_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_helpful` tinyint(1) NOT NULL,
  `feedback_text` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `merchant_applications`
--

CREATE TABLE `merchant_applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'NULL if not registered yet',
  `business_name` varchar(200) NOT NULL,
  `business_type` enum('individual','sole_proprietorship','partnership','llc','corporation','other') NOT NULL,
  `business_description` text DEFAULT NULL,
  `contact_name` varchar(100) NOT NULL,
  `contact_email` varchar(100) NOT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `business_address` text DEFAULT NULL,
  `business_city` varchar(100) DEFAULT NULL,
  `business_state` varchar(50) DEFAULT NULL,
  `business_postal_code` varchar(20) DEFAULT NULL,
  `business_country` varchar(3) DEFAULT 'USA',
  `tax_id` varchar(50) DEFAULT NULL COMMENT 'EIN, SSN, or other tax identifier',
  `website_url` varchar(255) DEFAULT NULL,
  `estimated_monthly_sales` decimal(12,2) DEFAULT NULL,
  `product_categories` text DEFAULT NULL COMMENT 'JSON array of product categories',
  `business_license_number` varchar(100) DEFAULT NULL,
  `years_in_business` int(11) DEFAULT NULL,
  `previous_ecommerce_experience` text DEFAULT NULL,
  `marketing_plan` text DEFAULT NULL,
  `status` enum('pending','under_review','approved','rejected','requires_info') DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL COMMENT 'Admin user ID who reviewed',
  `reviewed_at` datetime DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `documents_uploaded` tinyint(1) DEFAULT 0,
  `agreement_accepted` tinyint(1) DEFAULT 0,
  `agreement_accepted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `merchant_application_documents`
--

CREATE TABLE `merchant_application_documents` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `document_type` enum('business_license','tax_document','bank_statement','identity_proof','other') NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('order','payment','shipping','merchant','system','promotion') NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `action_url` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `is_important` tinyint(1) DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `total` decimal(10,2) NOT NULL,
  `status` enum('pending','shipped','delivered','cancelled') DEFAULT 'pending',
  `shipping_address` text DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `shipping_cost` decimal(10,2) DEFAULT 0.00,
  `currency` varchar(3) DEFAULT 'USD',
  `exchange_rate` decimal(12,6) DEFAULT 1.000000,
  `fx_gain_loss` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `total`, `status`, `shipping_address`, `payment_method`, `shipping_cost`, `created_at`) VALUES
(1, 4, 119.98, 'delivered', '123 Main Street\nAnytown, CA 90210\nUnited States', 'Stripe', 5.99, '2024-01-15 16:30:00'),
(2, 4, 89.99, 'shipped', '123 Main Street\nAnytown, CA 90210\nUnited States', 'PayPal', 5.99, '2024-01-20 20:15:00'),
(3, 5, 249.97, 'pending', '456 Oak Avenue\nSpringfield, IL 62701\nUnited States', 'Stripe', 8.99, '2024-01-25 15:45:00'),
(4, 5, 159.99, 'delivered', '456 Oak Avenue\nSpringfield, IL 62701\nUnited States', 'Cash', 0.00, '2024-01-18 22:20:00'),
(5, 6, 79.99, 'shipped', '789 Pine Road\nAustin, TX 73301\nUnited States', 'PayPal', 6.99, '2024-01-22 17:10:00'),
(6, 6, 199.98, 'delivered', '789 Pine Road\nAustin, TX 73301\nUnited States', 'Stripe', 7.99, '2024-01-12 19:45:00'),
(7, 4, 54.99, 'pending', '123 Main Street\nAnytown, CA 90210\nUnited States', 'Stripe', 4.99, '2024-01-26 14:30:00'),
(8, 5, 129.99, 'cancelled', '456 Oak Avenue\nSpringfield, IL 62701\nUnited States', 'PayPal', 5.99, '2024-01-14 21:20:00');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'USD'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`, `currency`) VALUES
(1, 1, 1, 1, 89.99, 'USD'),
(2, 1, 11, 1, 24.99, 'USD'),
(3, 2, 1, 1, 89.99, 'USD'),
(4, 3, 2, 1, 199.99, 'USD'),
(5, 3, 3, 1, 34.99, 'USD'),
(6, 3, 15, 1, 49.99, 'USD'),
(7, 4, 6, 1, 159.99, 'USD'),
(8, 5, 4, 1, 79.99, 'USD'),
(9, 6, 2, 1, 199.99, 'USD'),
(10, 7, 13, 1, 54.99, 'USD'),
(11, 8, 19, 1, 129.99, 'USD');

-- --------------------------------------------------------

--
-- Table structure for table `package_types`
--

CREATE TABLE `package_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `volume_multiplier` decimal(4,2) DEFAULT 1.00 COMMENT 'Volume calculation multiplier',
  `fragile_surcharge` decimal(5,2) DEFAULT 0.00,
  `handling_fee` decimal(5,2) DEFAULT 0.00,
  `max_weight_kg` decimal(8,2) DEFAULT NULL,
  `dimensions_required` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `package_types`
--

INSERT INTO `package_types` (`id`, `name`, `code`, `description`, `volume_multiplier`, `fragile_surcharge`, `handling_fee`, `max_weight_kg`, `dimensions_required`, `is_active`, `created_at`) VALUES
(1, 'Standard Box', 'STD_BOX', 'Standard cardboard box packaging', 1.00, 0.00, 0.00, 50.00, 1, 1, '2025-08-07 03:58:39'),
(2, 'Padded Envelope', 'PADDED_ENV', 'Padded envelope for small items', 0.50, 0.00, 0.00, 2.00, 1, 1, '2025-08-07 03:58:39'),
(3, 'Fragile Box', 'FRAGILE_BOX', 'Reinforced box for fragile items', 1.20, 5.00, 2.00, 30.00, 1, 1, '2025-08-07 03:58:39'),
(4, 'Tube', 'TUBE', 'Cylindrical tube packaging', 0.80, 0.00, 1.00, 10.00, 1, 1, '2025-08-07 03:58:39'),
(5, 'Pallet', 'PALLET', 'Pallet shipping for large items', 2.00, 0.00, 25.00, 1000.00, 1, 1, '2025-08-07 03:58:39'),
(6, 'Custom Crate', 'CUSTOM_CRATE', 'Custom wooden crate', 1.50, 10.00, 50.00, 500.00, 1, 1, '2025-08-07 03:58:39'),
(7, 'Temperature Controlled', 'TEMP_CTRL', 'Temperature controlled packaging', 2.50, 0.00, 15.00, 25.00, 1, 1, '2025-08-07 03:58:39'),
(8, 'Hazmat Container', 'HAZMAT', 'Hazardous materials container', 3.00, 0.00, 75.00, 20.00, 1, 1, '2025-08-07 03:58:39');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `provider` varchar(20) NOT NULL,
  `token` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `user_id`, `provider`, `token`) VALUES
(1, 4, 'Stripe', 'tok_visa_encrypted_demo'),
(2, 4, 'PayPal', 'paypal_token_encrypted_demo'),
(3, 5, 'Stripe', 'tok_mastercard_encrypted_demo'),
(4, 6, 'PayPal', 'paypal_business_token_demo');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `merchant_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `category` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `merchant_id`, `name`, `description`, `price`, `image_url`, `stock`, `category`, `created_at`) VALUES
(1, 2, 'Wireless Bluetooth Headphones', 'Premium noise-cancelling wireless headphones with 30-hour battery life. Perfect for music lovers and professionals who demand high-quality audio.', 89.99, 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400', 25, 'Electronics', '2025-08-07 02:50:12'),
(2, 2, 'Smart Fitness Watch', 'Advanced fitness tracker with heart rate monitoring, GPS tracking, and smartphone connectivity. Monitor your health and fitness goals.', 199.99, 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400', 15, 'Electronics', '2025-08-07 02:50:12'),
(3, 2, 'Wireless Phone Charger', 'Fast wireless charging pad compatible with all Qi-enabled devices. Sleek design with LED indicators.', 34.99, 'https://images.unsplash.com/photo-1586953208448-b95a79798f07?w=400', 40, 'Electronics', '2025-08-07 02:50:12'),
(4, 7, 'Bluetooth Portable Speaker', 'Compact Bluetooth speaker with powerful sound and waterproof design. Perfect for outdoor activities and travel.', 79.99, 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=400', 22, 'Electronics', '2025-08-07 02:50:12'),
(5, 7, 'Wireless Gaming Mouse', 'High-precision wireless gaming mouse with customizable RGB lighting and programmable buttons for serious gamers.', 89.99, 'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=400', 19, 'Electronics', '2025-08-07 02:50:12'),
(6, 7, 'Wireless Earbuds Pro', 'True wireless earbuds with active noise cancellation and premium sound quality. Includes charging case.', 159.99, 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=400', 21, 'Electronics', '2025-08-07 02:50:12'),
(7, 3, 'Organic Cotton T-Shirt', 'Comfortable and sustainable organic cotton t-shirt available in multiple colors. Perfect for casual wear and everyday comfort.', 24.99, 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=400', 50, 'Clothing', '2025-08-07 02:50:12'),
(8, 3, 'Denim Jacket Classic', 'Timeless denim jacket made from high-quality cotton. A wardrobe essential that never goes out of style.', 69.99, 'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=400', 28, 'Clothing', '2025-08-07 02:50:12'),
(9, 8, 'Silk Scarf Luxury', 'Elegant silk scarf with beautiful patterns. Perfect accessory to elevate any outfit for special occasions.', 79.99, 'https://images.unsplash.com/photo-1590736969955-71cc94901144?w=400', 14, 'Clothing', '2025-08-07 02:50:12'),
(10, 8, 'Winter Wool Coat', 'Premium wool coat for cold weather. Stylish and warm with classic design that suits any professional setting.', 199.99, 'https://images.unsplash.com/photo-1544966503-7cc5ac882d5f?w=400', 12, 'Clothing', '2025-08-07 02:50:12'),
(11, 9, 'Ceramic Coffee Mug Set', 'Set of 4 handcrafted ceramic coffee mugs. Perfect for your morning coffee routine or tea time with friends.', 39.99, 'https://images.unsplash.com/photo-1514228742587-6b1558fcf93a?w=400', 30, 'Home', '2025-08-07 02:50:12'),
(12, 9, 'Scented Candle Collection', 'Set of 3 premium scented candles with relaxing fragrances. Create a cozy atmosphere in any room.', 45.99, 'https://images.unsplash.com/photo-1602874801006-e26d3d17d0a5?w=400', 18, 'Home', '2025-08-07 02:50:12'),
(13, 9, 'Essential Oil Diffuser', 'Ultrasonic essential oil diffuser with LED lights and timer settings. Transform your space into a relaxing sanctuary.', 54.99, 'https://images.unsplash.com/photo-1608571423902-eed4a5ad8108?w=400', 16, 'Home', '2025-08-07 02:50:12'),
(14, 9, 'Bamboo Cutting Board Set', 'Set of 3 bamboo cutting boards in different sizes. Eco-friendly and durable for all your kitchen needs.', 34.99, 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=400', 26, 'Home', '2025-08-07 02:50:12'),
(15, 10, 'Yoga Mat Premium', 'Non-slip premium yoga mat with extra cushioning. Ideal for yoga, pilates, and home workout routines.', 49.99, 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?w=400', 20, 'Sports', '2025-08-07 02:50:12'),
(16, 10, 'Stainless Steel Water Bottle', 'Insulated stainless steel water bottle that keeps drinks cold for 24 hours or hot for 12 hours.', 29.99, 'https://images.unsplash.com/photo-1602143407151-7111542de6e8?w=400', 35, 'Sports', '2025-08-07 02:50:12'),
(17, 10, 'Running Shoes Pro', 'Professional running shoes with advanced cushioning and breathable mesh upper. Perfect for serious runners.', 149.99, 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400', 24, 'Sports', '2025-08-07 02:50:12'),
(18, 10, 'Hiking Backpack 40L', 'Durable hiking backpack with multiple compartments and hydration system compatibility for outdoor adventures.', 119.99, 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=400', 13, 'Sports', '2025-08-07 02:50:12'),
(19, 2, 'Leather Laptop Bag', 'Premium leather laptop bag with multiple compartments. Professional and durable for business use.', 129.99, 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=400', 12, 'Accessories', '2025-08-07 02:50:12'),
(20, 8, 'Skincare Gift Set', 'Complete skincare gift set with cleanser, moisturizer, and serum. Perfect for daily skincare routine.', 89.99, 'https://images.unsplash.com/photo-1556228578-8c89e6adf883?w=400', 17, 'Beauty', '2025-08-07 02:50:12'),
(21, 10, 'Plant-Based Protein Powder', 'Organic plant-based protein powder with vanilla flavor. Perfect for post-workout nutrition and muscle recovery.', 39.99, 'https://images.unsplash.com/photo-1593095948071-474c5cc2989d?w=400', 32, 'Health', '2025-08-07 02:50:12');

-- --------------------------------------------------------

--
-- Table structure for table `product_dimensions`
--

CREATE TABLE `product_dimensions` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `package_type_id` int(11) NOT NULL,
  `weight_kg` decimal(8,3) NOT NULL DEFAULT 0.500,
  `length_cm` decimal(8,2) NOT NULL DEFAULT 20.00,
  `width_cm` decimal(8,2) NOT NULL DEFAULT 15.00,
  `height_cm` decimal(8,2) NOT NULL DEFAULT 10.00,
  `volume_cm3` decimal(12,3) GENERATED ALWAYS AS (`length_cm` * `width_cm` * `height_cm`) STORED,
  `fragile` tinyint(1) DEFAULT 0,
  `hazardous` tinyint(1) DEFAULT 0,
  `liquid` tinyint(1) DEFAULT 0,
  `perishable` tinyint(1) DEFAULT 0,
  `requires_signature` tinyint(1) DEFAULT 0,
  `requires_adult_signature` tinyint(1) DEFAULT 0,
  `declared_value` decimal(10,2) DEFAULT 0.00,
  `country_of_origin` varchar(3) DEFAULT 'USA',
  `hs_code` varchar(20) DEFAULT NULL COMMENT 'Harmonized System code for customs',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shipments`
--

CREATE TABLE `shipments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `shipping_type_id` int(11) NOT NULL,
  `insurance_id` int(11) DEFAULT NULL,
  `tracking_number` varchar(100) NOT NULL,
  `status` enum('created','label_printed','picked_up','in_transit','customs_clearance','out_for_delivery','delivered','exception','returned','cancelled') DEFAULT 'created',
  `estimated_delivery` date DEFAULT NULL,
  `actual_delivery` datetime DEFAULT NULL,
  `shipping_cost` decimal(10,2) DEFAULT NULL,
  `insurance_cost` decimal(10,2) DEFAULT 0.00,
  `fuel_surcharge` decimal(10,2) DEFAULT 0.00,
  `customs_fee` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `weight_kg` decimal(8,3) DEFAULT NULL,
  `volume_cm3` decimal(12,3) DEFAULT NULL,
  `distance_km` int(11) DEFAULT NULL,
  `declared_value` decimal(10,2) DEFAULT NULL,
  `origin_latitude` decimal(10,8) DEFAULT NULL,
  `origin_longitude` decimal(11,8) DEFAULT NULL,
  `origin_address` text DEFAULT NULL,
  `destination_latitude` decimal(10,8) DEFAULT NULL,
  `destination_longitude` decimal(11,8) DEFAULT NULL,
  `destination_address` text DEFAULT NULL,
  `customs_declaration` text DEFAULT NULL,
  `special_instructions` text DEFAULT NULL,
  `signature_required` tinyint(1) DEFAULT 0,
  `adult_signature_required` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shipping_addresses`
--

CREATE TABLE `shipping_addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `address_name` varchar(100) DEFAULT NULL,
  `recipient_name` varchar(200) DEFAULT NULL,
  `address_line1` varchar(255) NOT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `country` varchar(100) NOT NULL DEFAULT 'United States',
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shipping_addresses`
--

INSERT INTO `shipping_addresses` (`id`, `user_id`, `address_name`, `recipient_name`, `address_line1`, `address_line2`, `city`, `state`, `postal_code`, `country`, `is_default`, `created_at`) VALUES
(1, 4, 'Home', 'Mike Davis', '123 Main Street', 'Apt 4B', 'Anytown', 'CA', '90210', 'United States', 1, '2025-08-07 02:50:12'),
(2, 4, 'Work', 'Mike Davis', '456 Business Blvd', 'Suite 200', 'Anytown', 'CA', '90211', 'United States', 0, '2025-08-07 02:50:12'),
(3, 5, 'Home', 'Emily Wilson', '456 Oak Avenue', '', 'Springfield', 'IL', '62701', 'United States', 1, '2025-08-07 02:50:12'),
(4, 5, 'Parents House', 'Emily Wilson c/o Wilson Family', '789 Family Lane', '', 'Springfield', 'IL', '62702', 'United States', 0, '2025-08-07 02:50:12'),
(5, 6, 'Home', 'David Brown', '789 Pine Road', 'Unit 12', 'Austin', 'TX', '73301', 'United States', 1, '2025-08-07 02:50:12'),
(6, 6, 'Office', 'David Brown', '321 Corporate Dr', 'Floor 5', 'Austin', 'TX', '73302', 'United States', 0, '2025-08-07 02:50:12'),
(7, 2, 'Business Address', 'John Smith - Tech Merchant', '100 Commerce St', 'Warehouse A', 'San Jose', 'CA', '95110', 'United States', 1, '2025-08-07 02:50:12'),
(8, 3, 'Store Location', 'Sarah Johnson Fashion', '200 Fashion Ave', '', 'New York', 'NY', '10001', 'United States', 1, '2025-08-07 02:50:12'),
(9, 11, '176 Jefferson Avenue. Staten Island', 'Uctrl llc', '176 Jefferson Avenue. Staten Island', '', 'New York', 'NY', '10306', 'United States', 1, '2025-08-07 03:18:19');

-- --------------------------------------------------------

--
-- Table structure for table `shipping_insurance`
--

CREATE TABLE `shipping_insurance` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `coverage_type` enum('basic','standard','premium','custom') DEFAULT 'basic',
  `max_coverage_amount` decimal(12,2) NOT NULL,
  `rate_percentage` decimal(5,4) NOT NULL COMMENT 'Insurance rate as % of declared value',
  `minimum_fee` decimal(8,2) DEFAULT 0.00,
  `maximum_fee` decimal(8,2) DEFAULT 0.00,
  `deductible_amount` decimal(8,2) DEFAULT 0.00,
  `covers_theft` tinyint(1) DEFAULT 1,
  `covers_damage` tinyint(1) DEFAULT 1,
  `covers_loss` tinyint(1) DEFAULT 1,
  `covers_delay` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shipping_insurance`
--

INSERT INTO `shipping_insurance` (`id`, `name`, `code`, `description`, `coverage_type`, `max_coverage_amount`, `rate_percentage`, `minimum_fee`, `maximum_fee`, `deductible_amount`, `covers_theft`, `covers_damage`, `covers_loss`, `covers_delay`, `is_active`, `created_at`) VALUES
(1, 'Basic Coverage', 'BASIC', 'Basic shipping insurance coverage', 'basic', 1000.00, 0.0050, 2.00, 50.00, 0.00, 1, 1, 1, 0, 1, '2025-08-07 03:58:39'),
(2, 'Standard Coverage', 'STANDARD', 'Standard shipping insurance coverage', 'standard', 5000.00, 0.0075, 5.00, 100.00, 0.00, 1, 1, 1, 0, 1, '2025-08-07 03:58:39'),
(3, 'Premium Coverage', 'PREMIUM', 'Premium shipping insurance coverage', 'premium', 25000.00, 0.0100, 10.00, 250.00, 0.00, 1, 1, 1, 0, 1, '2025-08-07 03:58:39'),
(4, 'High Value Coverage', 'HIGH_VALUE', 'High value item insurance', 'custom', 100000.00, 0.0150, 25.00, 500.00, 0.00, 1, 1, 1, 0, 1, '2025-08-07 03:58:39');

-- --------------------------------------------------------

--
-- Table structure for table `shipping_providers`
--

CREATE TABLE `shipping_providers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `api_endpoint` varchar(255) DEFAULT NULL,
  `api_key_encrypted` varchar(255) DEFAULT NULL,
  `tracking_url_template` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `supports_international` tinyint(1) DEFAULT 0,
  `supports_insurance` tinyint(1) DEFAULT 0,
  `supports_signature` tinyint(1) DEFAULT 0,
  `max_weight_kg` decimal(8,2) DEFAULT NULL,
  `max_dimensions_cm` varchar(50) DEFAULT NULL,
  `base_country_code` varchar(3) DEFAULT 'USA',
  `contact_phone` varchar(20) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shipping_providers`
--

INSERT INTO `shipping_providers` (`id`, `name`, `code`, `logo_url`, `api_endpoint`, `api_key_encrypted`, `tracking_url_template`, `is_active`, `supports_international`, `supports_insurance`, `supports_signature`, `max_weight_kg`, `max_dimensions_cm`, `base_country_code`, `contact_phone`, `contact_email`, `created_at`) VALUES
(1, 'FedEx', 'FEDEX', NULL, NULL, NULL, 'https://www.fedex.com/fedextrack/?trknbr={tracking_number}', 1, 1, 1, 0, 68.00, '274x274x274', 'USA', NULL, NULL, '2025-08-07 03:58:39'),
(2, 'UPS', 'UPS', NULL, NULL, NULL, 'https://www.ups.com/track?tracknum={tracking_number}', 1, 1, 1, 0, 70.00, '270x270x270', 'USA', NULL, NULL, '2025-08-07 03:58:39'),
(3, 'USPS', 'USPS', NULL, NULL, NULL, 'https://tools.usps.com/go/TrackConfirmAction?tLabels={tracking_number}', 1, 1, 0, 0, 32.00, '108x108x108', 'USA', NULL, NULL, '2025-08-07 03:58:39'),
(4, 'DHL', 'DHL', NULL, NULL, NULL, 'https://www.dhl.com/en/express/tracking.html?AWB={tracking_number}', 1, 1, 1, 0, 70.00, '300x300x300', 'USA', NULL, NULL, '2025-08-07 03:58:39'),
(5, 'Local Courier', 'LOCAL', NULL, NULL, NULL, 'https://ventdepot.com/track/{tracking_number}', 1, 0, 0, 0, 25.00, '100x100x100', 'USA', NULL, NULL, '2025-08-07 03:58:39');

-- --------------------------------------------------------

--
-- Table structure for table `shipping_rates`
--

CREATE TABLE `shipping_rates` (
  `id` int(11) NOT NULL,
  `origin` varchar(100) DEFAULT NULL,
  `destination` varchar(100) DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shipping_rates`
--

INSERT INTO `shipping_rates` (`id`, `origin`, `destination`, `cost`) VALUES
(1, 'Warehouse A', 'Zone 1', 5.99),
(2, 'Warehouse A', 'Zone 2', 8.99),
(3, 'Warehouse A', 'Zone 3', 12.99),
(4, 'Warehouse B', 'Zone 1', 6.99),
(5, 'Warehouse B', 'Zone 2', 9.99),
(6, 'Warehouse B', 'Zone 3', 13.99),
(7, 'Local Pickup', 'Same City', 0.00),
(8, 'Express Shipping', 'Nationwide', 19.99);

-- --------------------------------------------------------

--
-- Table structure for table `shipping_rate_rules`
--

CREATE TABLE `shipping_rate_rules` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `zone_id` int(11) NOT NULL,
  `shipping_type_id` int(11) NOT NULL,
  `weight_min_kg` decimal(8,3) DEFAULT 0.000,
  `weight_max_kg` decimal(8,3) DEFAULT 999.999,
  `volume_min_cm3` decimal(12,3) DEFAULT 0.000,
  `volume_max_cm3` decimal(12,3) DEFAULT 999999999.999,
  `distance_min_km` int(11) DEFAULT 0,
  `distance_max_km` int(11) DEFAULT 999999,
  `base_cost` decimal(10,2) NOT NULL,
  `cost_per_kg` decimal(10,2) DEFAULT 0.00,
  `cost_per_km` decimal(10,4) DEFAULT 0.0000,
  `cost_per_cm3` decimal(10,6) DEFAULT 0.000000,
  `insurance_rate` decimal(5,4) DEFAULT 0.0000 COMMENT 'Insurance cost as % of declared value',
  `fuel_surcharge_rate` decimal(5,2) DEFAULT 0.00,
  `customs_fee` decimal(10,2) DEFAULT 0.00,
  `free_shipping_threshold` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `effective_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID who created this rule',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shipping_rate_rules`
--

INSERT INTO `shipping_rate_rules` (`id`, `provider_id`, `service_id`, `zone_id`, `shipping_type_id`, `weight_min_kg`, `weight_max_kg`, `volume_min_cm3`, `volume_max_cm3`, `distance_min_km`, `distance_max_km`, `base_cost`, `cost_per_kg`, `cost_per_km`, `cost_per_cm3`, `insurance_rate`, `fuel_surcharge_rate`, `customs_fee`, `free_shipping_threshold`, `is_active`, `effective_date`, `expiry_date`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 3, 0.000, 10.000, 0.000, 100000.000, 0, 5000, 25.99, 5.00, 0.0050, 0.000100, 0.0050, 12.50, 0.00, 100.00, 1, NULL, NULL, NULL, '2025-08-07 03:58:39', '2025-08-07 03:58:39'),
(2, 1, 2, 1, 2, 0.000, 10.000, 0.000, 100000.000, 0, 5000, 15.99, 3.00, 0.0030, 0.000075, 0.0050, 12.50, 0.00, 75.00, 1, NULL, NULL, NULL, '2025-08-07 03:58:39', '2025-08-07 03:58:39'),
(3, 1, 3, 1, 1, 0.000, 50.000, 0.000, 500000.000, 0, 5000, 8.99, 1.50, 0.0015, 0.000025, 0.0050, 12.50, 0.00, 50.00, 1, NULL, NULL, NULL, '2025-08-07 03:58:39', '2025-08-07 03:58:39'),
(4, 2, 5, 1, 3, 0.000, 10.000, 0.000, 100000.000, 0, 5000, 24.99, 4.50, 0.0045, 0.000095, 0.0050, 12.50, 0.00, 100.00, 1, NULL, NULL, NULL, '2025-08-07 03:58:39', '2025-08-07 03:58:39'),
(5, 2, 6, 1, 2, 0.000, 10.000, 0.000, 100000.000, 0, 5000, 14.99, 2.75, 0.0025, 0.000070, 0.0050, 12.50, 0.00, 75.00, 1, NULL, NULL, NULL, '2025-08-07 03:58:39', '2025-08-07 03:58:39'),
(6, 2, 7, 1, 1, 0.000, 50.000, 0.000, 500000.000, 0, 5000, 7.99, 1.25, 0.0012, 0.000020, 0.0050, 12.50, 0.00, 50.00, 1, NULL, NULL, NULL, '2025-08-07 03:58:39', '2025-08-07 03:58:39'),
(7, 3, 9, 1, 3, 0.000, 10.000, 0.000, 50000.000, 0, 5000, 22.99, 4.00, 0.0040, 0.000080, 0.0000, 10.00, 0.00, 100.00, 1, NULL, NULL, NULL, '2025-08-07 03:58:39', '2025-08-07 03:58:39'),
(8, 3, 10, 1, 2, 0.000, 20.000, 0.000, 100000.000, 0, 5000, 9.99, 2.00, 0.0020, 0.000040, 0.0000, 10.00, 0.00, 50.00, 1, NULL, NULL, NULL, '2025-08-07 03:58:39', '2025-08-07 03:58:39'),
(9, 3, 11, 1, 1, 0.000, 30.000, 0.000, 200000.000, 0, 5000, 5.99, 1.00, 0.0010, 0.000015, 0.0000, 10.00, 0.00, 35.00, 1, NULL, NULL, NULL, '2025-08-07 03:58:39', '2025-08-07 03:58:39'),
(10, 1, 4, 2, 4, 0.000, 20.000, 0.000, 200000.000, 2000, 3000, 25.99, 4.00, 0.0080, 0.000120, 0.0075, 15.00, 15.00, 100.00, 1, NULL, NULL, NULL, '2025-08-07 03:58:39', '2025-08-07 03:58:39'),
(11, 2, 8, 2, 4, 0.000, 20.000, 0.000, 200000.000, 2000, 3000, 23.99, 3.75, 0.0075, 0.000115, 0.0075, 15.00, 15.00, 100.00, 1, NULL, NULL, NULL, '2025-08-07 03:58:39', '2025-08-07 03:58:39'),
(12, 1, 4, 4, 4, 0.000, 15.000, 0.000, 150000.000, 8000, 10000, 45.99, 8.00, 0.0120, 0.000200, 0.0100, 18.00, 25.00, 200.00, 1, NULL, NULL, NULL, '2025-08-07 03:58:39', '2025-08-07 03:58:39'),
(13, 2, 8, 4, 4, 0.000, 15.000, 0.000, 150000.000, 8000, 10000, 42.99, 7.50, 0.0115, 0.000190, 0.0100, 18.00, 25.00, 200.00, 1, NULL, NULL, NULL, '2025-08-07 03:58:39', '2025-08-07 03:58:39'),
(14, 4, 13, 4, 4, 0.000, 20.000, 0.000, 200000.000, 8000, 10000, 39.99, 7.00, 0.0110, 0.000180, 0.0100, 18.00, 25.00, 150.00, 1, NULL, NULL, NULL, '2025-08-07 03:58:39', '2025-08-07 03:58:39'),
(15, 1, 4, 5, 4, 0.000, 15.000, 0.000, 150000.000, 10000, 15000, 55.99, 10.00, 0.0150, 0.000250, 0.0125, 20.00, 35.00, 250.00, 1, NULL, NULL, NULL, '2025-08-07 03:58:39', '2025-08-07 03:58:39'),
(16, 2, 8, 5, 4, 0.000, 15.000, 0.000, 150000.000, 10000, 15000, 52.99, 9.50, 0.0145, 0.000240, 0.0125, 20.00, 35.00, 250.00, 1, NULL, NULL, NULL, '2025-08-07 03:58:39', '2025-08-07 03:58:39'),
(17, 4, 13, 5, 4, 0.000, 20.000, 0.000, 200000.000, 10000, 15000, 49.99, 9.00, 0.0140, 0.000230, 0.0125, 20.00, 35.00, 200.00, 1, NULL, NULL, NULL, '2025-08-07 03:58:39', '2025-08-07 03:58:39'),
(18, 5, 15, 1, 6, 0.000, 20.000, 0.000, 100000.000, 0, 100, 12.99, 2.00, 0.0500, 0.000050, 0.0000, 5.00, 0.00, 75.00, 1, NULL, NULL, NULL, '2025-08-07 03:58:39', '2025-08-07 03:58:39'),
(19, 5, 16, 1, 1, 0.000, 25.000, 0.000, 150000.000, 0, 200, 6.99, 1.00, 0.0200, 0.000025, 0.0000, 5.00, 0.00, 50.00, 1, NULL, NULL, NULL, '2025-08-07 03:58:39', '2025-08-07 03:58:39');

-- --------------------------------------------------------

--
-- Table structure for table `shipping_restrictions`
--

CREATE TABLE `shipping_restrictions` (
  `id` int(11) NOT NULL,
  `country_code` varchar(3) DEFAULT NULL,
  `state_code` varchar(10) DEFAULT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `product_category` varchar(100) DEFAULT NULL,
  `restriction_type` enum('prohibited','restricted','documentation_required','additional_fees') NOT NULL,
  `description` text DEFAULT NULL,
  `additional_fee` decimal(10,2) DEFAULT 0.00,
  `required_documents` text DEFAULT NULL,
  `max_quantity` int(11) DEFAULT NULL,
  `max_value` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shipping_services`
--

CREATE TABLE `shipping_services` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `shipping_type_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `estimated_days_min` int(11) DEFAULT 1,
  `estimated_days_max` int(11) DEFAULT 7,
  `is_express` tinyint(1) DEFAULT 0,
  `is_overnight` tinyint(1) DEFAULT 0,
  `supports_tracking` tinyint(1) DEFAULT 1,
  `supports_insurance` tinyint(1) DEFAULT 0,
  `max_insurance_value` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shipping_services`
--

INSERT INTO `shipping_services` (`id`, `provider_id`, `shipping_type_id`, `name`, `code`, `description`, `estimated_days_min`, `estimated_days_max`, `is_express`, `is_overnight`, `supports_tracking`, `supports_insurance`, `max_insurance_value`, `is_active`, `created_at`) VALUES
(1, 1, 3, 'FedEx Overnight', 'FEDEX_OVERNIGHT', 'Next business day delivery', 1, 1, 1, 0, 1, 1, 25000.00, 1, '2025-08-07 03:58:39'),
(2, 1, 2, 'FedEx 2Day', 'FEDEX_2DAY', 'Delivery in 2 business days', 2, 2, 1, 0, 1, 1, 25000.00, 1, '2025-08-07 03:58:39'),
(3, 1, 1, 'FedEx Ground', 'FEDEX_GROUND', 'Ground delivery service', 1, 5, 0, 0, 1, 1, 10000.00, 1, '2025-08-07 03:58:39'),
(4, 1, 4, 'FedEx International', 'FEDEX_INTL', 'International express delivery', 2, 7, 1, 0, 1, 1, 50000.00, 1, '2025-08-07 03:58:39'),
(5, 2, 3, 'UPS Next Day Air', 'UPS_NEXT_DAY', 'Next business day delivery', 1, 1, 1, 0, 1, 1, 25000.00, 1, '2025-08-07 03:58:39'),
(6, 2, 2, 'UPS 2nd Day Air', 'UPS_2DAY', 'Second business day delivery', 2, 2, 1, 0, 1, 1, 25000.00, 1, '2025-08-07 03:58:39'),
(7, 2, 1, 'UPS Ground', 'UPS_GROUND', 'Ground delivery service', 1, 5, 0, 0, 1, 1, 10000.00, 1, '2025-08-07 03:58:39'),
(8, 2, 4, 'UPS Worldwide Express', 'UPS_WORLDWIDE', 'International express delivery', 1, 5, 1, 0, 1, 1, 50000.00, 1, '2025-08-07 03:58:39'),
(9, 3, 3, 'USPS Priority Express', 'USPS_EXPRESS', 'Overnight delivery', 1, 2, 1, 0, 1, 0, 0.00, 1, '2025-08-07 03:58:39'),
(10, 3, 2, 'USPS Priority Mail', 'USPS_PRIORITY', 'Priority mail service', 1, 3, 0, 0, 1, 0, 0.00, 1, '2025-08-07 03:58:39'),
(11, 3, 1, 'USPS Ground Advantage', 'USPS_GROUND', 'Ground delivery service', 2, 5, 0, 0, 1, 0, 0.00, 1, '2025-08-07 03:58:39'),
(12, 3, 5, 'USPS International', 'USPS_INTL', 'International mail service', 6, 21, 0, 0, 1, 0, 0.00, 1, '2025-08-07 03:58:39'),
(13, 4, 4, 'DHL Express Worldwide', 'DHL_EXPRESS', 'Express worldwide delivery', 1, 4, 1, 0, 1, 1, 100000.00, 1, '2025-08-07 03:58:39'),
(14, 4, 5, 'DHL Economy Select', 'DHL_ECONOMY', 'Economy international delivery', 4, 8, 0, 0, 1, 1, 25000.00, 1, '2025-08-07 03:58:39'),
(15, 5, 6, 'Same Day Delivery', 'LOCAL_SAME_DAY', 'Same day local delivery', 0, 0, 1, 0, 1, 0, 0.00, 1, '2025-08-07 03:58:39'),
(16, 5, 1, 'Next Day Local', 'LOCAL_NEXT_DAY', 'Next day local delivery', 1, 1, 0, 0, 1, 0, 0.00, 1, '2025-08-07 03:58:39');

-- --------------------------------------------------------

--
-- Table structure for table `shipping_types`
--

CREATE TABLE `shipping_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `base_multiplier` decimal(4,2) DEFAULT 1.00 COMMENT 'Cost multiplier for this type',
  `max_weight_kg` decimal(8,2) DEFAULT NULL,
  `max_dimensions_cm` varchar(50) DEFAULT NULL,
  `requires_signature` tinyint(1) DEFAULT 0,
  `insurance_included` tinyint(1) DEFAULT 0,
  `tracking_included` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shipping_types`
--

INSERT INTO `shipping_types` (`id`, `name`, `code`, `description`, `base_multiplier`, `max_weight_kg`, `max_dimensions_cm`, `requires_signature`, `insurance_included`, `tracking_included`, `is_active`, `created_at`) VALUES
(1, 'Standard Ground', 'STANDARD', 'Standard ground shipping service', 1.00, 70.00, NULL, 0, 0, 1, 1, '2025-08-07 03:58:39'),
(2, 'Express', 'EXPRESS', 'Express shipping service', 1.50, 50.00, NULL, 0, 0, 1, 1, '2025-08-07 03:58:39'),
(3, 'Overnight', 'OVERNIGHT', 'Next business day delivery', 2.50, 30.00, NULL, 1, 1, 1, 1, '2025-08-07 03:58:39'),
(4, 'International Express', 'INTL_EXPRESS', 'International express delivery', 3.00, 50.00, NULL, 1, 1, 1, 1, '2025-08-07 03:58:39'),
(5, 'International Standard', 'INTL_STANDARD', 'International standard delivery', 2.00, 70.00, NULL, 0, 0, 1, 1, '2025-08-07 03:58:39'),
(6, 'Same Day', 'SAME_DAY', 'Same day delivery service', 5.00, 20.00, NULL, 1, 1, 1, 1, '2025-08-07 03:58:39'),
(7, 'Freight', 'FREIGHT', 'Heavy freight shipping', 0.80, 1000.00, NULL, 1, 0, 1, 1, '2025-08-07 03:58:39'),
(8, 'White Glove', 'WHITE_GLOVE', 'Premium delivery with setup', 4.00, 100.00, NULL, 1, 1, 1, 1, '2025-08-07 03:58:39');

-- --------------------------------------------------------

--
-- Table structure for table `shipping_zones`
--

CREATE TABLE `shipping_zones` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `base_distance_km` int(11) DEFAULT 0 COMMENT 'Base distance from origin (California)',
  `distance_multiplier` decimal(4,2) DEFAULT 1.00,
  `customs_required` tinyint(1) DEFAULT 0,
  `max_processing_days` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shipping_zones`
--

INSERT INTO `shipping_zones` (`id`, `name`, `code`, `description`, `base_distance_km`, `distance_multiplier`, `customs_required`, `max_processing_days`, `is_active`, `created_at`) VALUES
(1, 'Domestic US', 'US_DOMESTIC', 'United States domestic shipping', 0, 1.00, 0, 0, 1, '2025-08-07 03:58:39'),
(2, 'Canada', 'CANADA', 'Canada shipping zone', 2500, 1.20, 1, 0, 1, '2025-08-07 03:58:39'),
(3, 'Mexico', 'MEXICO', 'Mexico shipping zone', 2000, 1.15, 1, 0, 1, '2025-08-07 03:58:39'),
(4, 'Europe', 'EUROPE', 'European Union countries', 8500, 1.50, 1, 0, 1, '2025-08-07 03:58:39'),
(5, 'Asia Pacific', 'ASIA_PACIFIC', 'Asia Pacific region', 11000, 1.75, 1, 0, 1, '2025-08-07 03:58:39'),
(6, 'International', 'INTERNATIONAL', 'Rest of world', 12000, 2.00, 1, 0, 1, '2025-08-07 03:58:39');

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','textarea','number','boolean','json') DEFAULT 'text',
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0 COMMENT 'Whether this setting can be viewed by non-admins',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `is_public`, `created_at`, `updated_at`) VALUES
(1, 'contact_phone', '1-800-VENTDEPOT', 'text', 'Main contact phone number', 1, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(2, 'contact_email', 'info@ventdepot.com', 'text', 'General contact email', 1, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(3, 'support_email', 'support@ventdepot.com', 'text', 'Customer support email', 1, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(4, 'sales_email', 'sales@ventdepot.com', 'text', 'Sales inquiries email', 1, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(5, 'contact_address', '123 Business Street\r\nLos Angeles, CA 90210\r\nUnited States', 'textarea', 'Business address', 1, '2025-08-07 06:42:12', '2025-08-07 06:57:05'),
(6, 'business_hours', 'Monday - Friday: 9:00 AM - 6:00 PM PST\r\nSaturday: 10:00 AM - 4:00 PM PST\r\nSunday: Closed', 'textarea', 'Business operating hours', 1, '2025-08-07 06:42:12', '2025-08-07 06:57:05'),
(7, 'shipping_domestic_info', 'We offer fast and reliable domestic shipping throughout the United States. Standard shipping typically takes 3-7 business days, while express options are available for faster delivery.', 'textarea', 'Domestic shipping information', 1, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(8, 'shipping_international_info', 'International shipping is available to most countries worldwide. Delivery times vary by destination and may take 7-21 business days. Customs fees and import duties may apply.', 'textarea', 'International shipping information', 1, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(9, 'shipping_processing_time', 'Orders are typically processed within 1-2 business days. Orders placed before 2:00 PM PST on business days are usually processed the same day.', 'textarea', 'Order processing time information', 1, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(10, 'shipping_rates_info', 'Shipping rates are calculated based on package weight, dimensions, destination, and selected shipping method. Free shipping is available on orders over $50 for domestic shipments.', 'textarea', 'Shipping rates information', 1, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(11, 'shipping_restrictions', 'Some items may have shipping restrictions due to size, weight, or regulatory requirements. Hazardous materials and certain electronics may require special handling.', 'textarea', 'Shipping restrictions information', 1, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(12, 'shipping_tracking_info', 'All shipments include tracking information. You will receive a tracking number via email once your order ships. Track your package on our website or the carrier\'s website.', 'textarea', 'Package tracking information', 1, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(13, 'returns_policy', 'We accept returns within 30 days of delivery for most items in original condition. Items must be unused, in original packaging, and include all accessories and documentation.', 'textarea', 'Returns policy', 1, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(14, 'refund_policy', 'Refunds are processed within 5-7 business days after we receive your returned item. Refunds will be issued to the original payment method. Shipping costs are non-refundable unless the return is due to our error.', 'textarea', 'Refund policy', 1, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(15, 'exchange_policy', 'We offer exchanges for different sizes or colors when available. Exchanges are processed as returns and new orders to ensure fastest delivery of your preferred item.', 'textarea', 'Exchange policy', 1, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(16, 'return_process', '1. Contact our customer service to initiate a return\n2. Print the prepaid return label we provide\n3. Package the item securely in original packaging\n4. Attach the return label and drop off at any authorized location\n5. Track your return and refund status online', 'textarea', 'Return process steps', 1, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(17, 'site_name', 'VentDepot', 'text', 'Website name', 1, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(18, 'site_tagline', 'Your Premier Destination for Quality Products', 'text', 'Website tagline', 1, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(19, 'maintenance_mode', '0', 'boolean', 'Enable maintenance mode', 0, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(20, 'allow_guest_checkout', '1', 'boolean', 'Allow guest checkout', 0, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(21, 'require_email_verification', '1', 'boolean', 'Require email verification for new accounts', 0, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(22, 'max_login_attempts', '5', 'number', 'Maximum login attempts before lockout', 0, '2025-08-07 06:42:12', '2025-08-07 06:42:12'),
(23, 'session_timeout', '3600', 'number', 'Session timeout in seconds', 0, '2025-08-07 06:42:12', '2025-08-07 06:42:12');

-- --------------------------------------------------------

--
-- Table structure for table `states`
--

CREATE TABLE `states` (
  `id` int(11) NOT NULL,
  `country_id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `timezone` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `states`
--

INSERT INTO `states` (`id`, `country_id`, `code`, `name`, `tax_rate`, `latitude`, `longitude`, `timezone`, `is_active`, `created_at`) VALUES
(1, 1, 'CA', 'California', 7.25, 36.77830000, -119.41790000, 'America/Los_Angeles', 1, '2025-08-07 03:58:39'),
(2, 1, 'NY', 'New York', 4.00, 40.71280000, -74.00600000, 'America/New_York', 1, '2025-08-07 03:58:39'),
(3, 1, 'TX', 'Texas', 6.25, 31.96860000, -99.90180000, 'America/Chicago', 1, '2025-08-07 03:58:39'),
(4, 1, 'FL', 'Florida', 6.00, 27.76630000, -81.68680000, 'America/New_York', 1, '2025-08-07 03:58:39'),
(5, 1, 'IL', 'Illinois', 6.25, 40.63310000, -89.39850000, 'America/Chicago', 1, '2025-08-07 03:58:39'),
(6, 1, 'PA', 'Pennsylvania', 6.00, 41.20330000, -77.19450000, 'America/New_York', 1, '2025-08-07 03:58:39'),
(7, 1, 'OH', 'Ohio', 5.75, 40.41730000, -82.90710000, 'America/New_York', 1, '2025-08-07 03:58:39'),
(8, 1, 'GA', 'Georgia', 4.00, 32.16560000, -82.90010000, 'America/New_York', 1, '2025-08-07 03:58:39'),
(9, 1, 'NC', 'North Carolina', 4.75, 35.75960000, -79.01930000, 'America/New_York', 1, '2025-08-07 03:58:39'),
(10, 1, 'MI', 'Michigan', 6.00, 44.34670000, -85.41020000, 'America/Detroit', 1, '2025-08-07 03:58:39'),
(11, 1, 'WA', 'Washington', 6.50, 47.75110000, -120.74010000, 'America/Los_Angeles', 1, '2025-08-07 03:58:39'),
(12, 1, 'AZ', 'Arizona', 5.60, 34.04890000, -111.09370000, 'America/Phoenix', 1, '2025-08-07 03:58:39'),
(13, 1, 'NV', 'Nevada', 6.85, 38.80260000, -116.41940000, 'America/Los_Angeles', 1, '2025-08-07 03:58:39'),
(14, 1, 'OR', 'Oregon', 0.00, 43.80410000, -120.55420000, 'America/Los_Angeles', 1, '2025-08-07 03:58:39'),
(15, 1, 'CO', 'Colorado', 2.90, 39.55010000, -105.78210000, 'America/Denver', 1, '2025-08-07 03:58:39'),
(16, 2, 'ON', 'Ontario', 13.00, 50.00000000, -85.00000000, 'America/Toronto', 1, '2025-08-07 03:58:39'),
(17, 2, 'QC', 'Quebec', 14.98, 53.00000000, -70.00000000, 'America/Toronto', 1, '2025-08-07 03:58:39'),
(18, 2, 'BC', 'British Columbia', 12.00, 53.72670000, -127.64760000, 'America/Vancouver', 1, '2025-08-07 03:58:39'),
(19, 2, 'AB', 'Alberta', 5.00, 53.93330000, -116.57650000, 'America/Edmonton', 1, '2025-08-07 03:58:39'),
(20, 2, 'MB', 'Manitoba', 12.00, 53.76090000, -98.81390000, 'America/Winnipeg', 1, '2025-08-07 03:58:39'),
(21, 2, 'SK', 'Saskatchewan', 11.00, 52.93990000, -106.45090000, 'America/Regina', 1, '2025-08-07 03:58:39'),
(22, 2, 'NS', 'Nova Scotia', 15.00, 44.68200000, -63.74430000, 'America/Halifax', 1, '2025-08-07 03:58:39'),
(23, 2, 'NB', 'New Brunswick', 15.00, 46.56530000, -66.46190000, 'America/Moncton', 1, '2025-08-07 03:58:39');

-- --------------------------------------------------------

--
-- Table structure for table `support_messages`
--

CREATE TABLE `support_messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ticket_id` varchar(50) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `category` enum('order','product','payment','shipping','account','other') DEFAULT 'other',
  `admin_response` text DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `support_messages`
--

INSERT INTO `support_messages` (`id`, `user_id`, `ticket_id`, `subject`, `message`, `status`, `priority`, `category`, `admin_response`, `admin_id`, `created_at`, `updated_at`) VALUES
(1, 4, 'TKT-2024-001', 'Order Delivery Issue', 'My order #000001 was supposed to arrive yesterday but I haven\'t received it yet. Can you please check the tracking status?', 'resolved', 'medium', 'shipping', 'Hi Mike, I checked with our shipping partner and your package was delivered to your building\'s front desk. Please check with your building management. If you still can\'t locate it, we\'ll send a replacement immediately.', 1, '2025-08-07 02:50:12', '2025-08-07 02:50:12'),
(2, 5, 'TKT-2024-002', 'Product Quality Concern', 'The yoga mat I received has a strong chemical smell and seems different from what was advertised. I\'d like to return it.', 'in_progress', 'high', 'product', 'Hi Emily, I\'m sorry to hear about the quality issue. We\'re arranging a return label for you and will process a full refund once we receive the item. We\'re also investigating this with our supplier.', 1, '2025-08-07 02:50:12', '2025-08-07 02:50:12'),
(3, 6, 'TKT-2024-003', 'Payment Processing Error', 'I was charged twice for my recent order. My credit card shows two transactions but I only placed one order.', 'resolved', 'high', 'payment', 'Hi David, I found the duplicate charge and have processed a refund for the extra amount. You should see it back on your card within 3-5 business days. Sorry for the inconvenience!', 1, '2025-08-07 02:50:12', '2025-08-07 02:50:12'),
(4, 4, 'TKT-2024-004', 'Account Login Problems', 'I can\'t seem to reset my password. The reset email isn\'t coming through to my inbox.', 'open', 'medium', 'account', NULL, NULL, '2025-08-07 02:50:12', '2025-08-07 02:50:12'),
(5, 5, 'TKT-2024-005', 'Product Recommendation Request', 'I\'m looking for a good wireless speaker for outdoor use. Can you recommend something from your electronics section?', 'open', 'low', 'product', NULL, NULL, '2025-08-07 02:50:12', '2025-08-07 02:50:12'),
(6, 1, 'TKT-2025-7696', 'reset my password', 'hey i want to update my password', 'open', 'high', 'other', NULL, NULL, '2025-08-07 04:08:50', '2025-08-07 04:08:50');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL COMMENT 'product, order, user, etc.',
  `entity_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `additional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tax_rules`
--

CREATE TABLE `tax_rules` (
  `id` int(11) NOT NULL,
  `country_id` int(11) DEFAULT NULL,
  `state_id` int(11) DEFAULT NULL,
  `product_category` varchar(100) DEFAULT NULL,
  `tax_type` enum('sales','vat','gst','hst') NOT NULL,
  `tax_rate` decimal(5,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `effective_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tracking_events`
--

CREATE TABLE `tracking_events` (
  `id` int(11) NOT NULL,
  `shipment_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `facility_name` varchar(255) DEFAULT NULL,
  `event_time` datetime NOT NULL,
  `estimated_delivery` datetime DEFAULT NULL,
  `next_location` varchar(255) DEFAULT NULL,
  `delay_reason` varchar(255) DEFAULT NULL,
  `created_by_system` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('customer','merchant','admin') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'admin@ventdepot.com', '$2y$10$.s808KyHzuxxQl91drFMFuQ0OnRx2iTtpINuGFOT6ZmpfovKxcqUq', 'admin', '2025-08-07 02:50:12'),
(2, 'merchant1@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'merchant', '2025-08-07 02:50:12'),
(3, 'merchant2@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'merchant', '2025-08-07 02:50:12'),
(4, 'customer1@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '2025-08-07 02:50:12'),
(5, 'customer2@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '2025-08-07 02:50:12'),
(6, 'customer3@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '2025-08-07 02:50:12'),
(7, 'techstore@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'merchant', '2025-08-07 02:50:12'),
(8, 'fashionhub@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'merchant', '2025-08-07 02:50:12'),
(9, 'homegoods@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'merchant', '2025-08-07 02:50:12'),
(10, 'sportsworld@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'merchant', '2025-08-07 02:50:12'),
(11, 'merchant1@demo.co', '$2y$10$.s808KyHzuxxQl91drFMFuQ0OnRx2iTtpINuGFOT6ZmpfovKxcqUq', 'merchant', '2025-08-07 02:52:02');

-- --------------------------------------------------------

--
-- Table structure for table `user_addresses`
--

CREATE TABLE `user_addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `address_name` varchar(100) NOT NULL,
  `recipient_name` varchar(100) NOT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `address_line1` varchar(255) NOT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state_code` varchar(10) DEFAULT NULL,
  `postal_code` varchar(20) NOT NULL,
  `country_code` varchar(3) NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `is_business` tinyint(1) DEFAULT 0,
  `delivery_instructions` text DEFAULT NULL,
  `access_code` varchar(50) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `profile_image` varchar(500) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`id`, `user_id`, `first_name`, `last_name`, `phone`, `date_of_birth`, `profile_image`, `bio`, `preferences`, `created_at`, `updated_at`) VALUES
(1, 1, 'Admin', 'User', '+1-555-0001', '1985-01-15', NULL, 'Platform administrator with full access to all systems.', '{\"theme\": \"dark\", \"dashboard_layout\": \"compact\"}', '2025-08-07 02:50:12', '2025-08-07 02:50:12'),
(2, 2, 'John', 'Smith', '+1-555-0102', '1988-03-22', NULL, 'Electronics enthusiast and tech merchant. Specializing in cutting-edge gadgets and accessories.', '{\"business_hours\": \"9AM-6PM\", \"auto_respond\": true}', '2025-08-07 02:50:12', '2025-08-07 02:50:12'),
(3, 3, 'Sarah', 'Johnson', '+1-555-0103', '1990-07-18', NULL, 'Fashion lover and clothing merchant. Curating sustainable and stylish apparel.', '{\"business_hours\": \"10AM-8PM\", \"specialty\": \"sustainable_fashion\"}', '2025-08-07 02:50:12', '2025-08-07 02:50:12'),
(4, 4, 'Mike', 'Davis', '+1-555-0204', '1992-11-05', NULL, 'Tech professional and avid online shopper. Love discovering new gadgets and tools.', '{\"favorite_categories\": [\"Electronics\", \"Sports\"], \"newsletter\": true}', '2025-08-07 02:50:12', '2025-08-07 02:50:12'),
(5, 5, 'Emily', 'Wilson', '+1-555-0205', '1987-09-12', NULL, 'Home decor enthusiast and fitness lover. Always looking for quality products.', '{\"favorite_categories\": [\"Home\", \"Sports\", \"Beauty\"], \"budget_alerts\": true}', '2025-08-07 02:50:12', '2025-08-07 02:50:12'),
(6, 6, 'David', 'Brown', '+1-555-0206', '1995-04-30', NULL, 'Outdoor adventure seeker and sports equipment collector.', '{\"favorite_categories\": [\"Sports\", \"Electronics\"], \"deal_notifications\": true}', '2025-08-07 02:50:12', '2025-08-07 02:50:12'),
(7, 7, 'Tech', 'Store', '+1-555-0307', '1985-12-01', NULL, 'Premium technology retailer with 10+ years experience in consumer electronics.', '{\"business_type\": \"electronics\", \"warranty_offered\": true}', '2025-08-07 02:50:12', '2025-08-07 02:50:12'),
(8, 8, 'Fashion', 'Hub', '+1-555-0308', '1989-06-15', NULL, 'Trendy fashion boutique offering the latest styles and accessories.', '{\"business_type\": \"fashion\", \"size_guide\": true}', '2025-08-07 02:50:12', '2025-08-07 02:50:12'),
(9, 9, 'Home', 'Goods', '+1-555-0309', '1983-08-20', NULL, 'Quality home goods and decor specialist. Making houses into homes.', '{\"business_type\": \"home_decor\", \"custom_orders\": true}', '2025-08-07 02:50:12', '2025-08-07 02:50:12'),
(10, 10, 'Sports', 'World', '+1-555-0310', '1991-02-28', NULL, 'Athletic equipment and fitness gear expert. Helping customers achieve their fitness goals.', '{\"business_type\": \"sports\", \"expert_advice\": true}', '2025-08-07 02:50:12', '2025-08-07 02:50:12'),
(11, 11, 'Uctrl llc', 'Uctrl llc', '9292731531', NULL, NULL, 'this is a business test', NULL, '2025-08-07 03:17:56', '2025-08-07 03:17:56');

-- --------------------------------------------------------

--
-- Table structure for table `zone_countries`
--

CREATE TABLE `zone_countries` (
  `id` int(11) NOT NULL,
  `zone_id` int(11) NOT NULL,
  `country_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `zone_countries`
--

INSERT INTO `zone_countries` (`id`, `zone_id`, `country_id`, `created_at`) VALUES
(1, 1, 1, '2025-08-07 03:58:39'),
(2, 2, 2, '2025-08-07 03:58:39'),
(3, 3, 3, '2025-08-07 03:58:39'),
(4, 4, 4, '2025-08-07 03:58:39'),
(5, 4, 5, '2025-08-07 03:58:39'),
(6, 4, 6, '2025-08-07 03:58:39'),
(7, 4, 7, '2025-08-07 03:58:39'),
(8, 4, 8, '2025-08-07 03:58:39'),
(9, 4, 9, '2025-08-07 03:58:39'),
(10, 4, 10, '2025-08-07 03:58:39'),
(11, 4, 11, '2025-08-07 03:58:39'),
(12, 4, 12, '2025-08-07 03:58:39'),
(13, 4, 13, '2025-08-07 03:58:39'),
(14, 4, 14, '2025-08-07 03:58:39'),
(15, 4, 15, '2025-08-07 03:58:39'),
(16, 4, 16, '2025-08-07 03:58:39'),
(17, 4, 17, '2025-08-07 03:58:39'),
(18, 4, 18, '2025-08-07 03:58:39'),
(19, 4, 19, '2025-08-07 03:58:39'),
(20, 4, 20, '2025-08-07 03:58:39'),
(21, 4, 21, '2025-08-07 03:58:39'),
(22, 4, 22, '2025-08-07 03:58:39'),
(23, 4, 23, '2025-08-07 03:58:39'),
(24, 4, 24, '2025-08-07 03:58:39'),
(25, 4, 25, '2025-08-07 03:58:39'),
(26, 5, 26, '2025-08-07 03:58:39'),
(27, 5, 27, '2025-08-07 03:58:39'),
(28, 5, 28, '2025-08-07 03:58:39'),
(29, 5, 29, '2025-08-07 03:58:39'),
(30, 5, 30, '2025-08-07 03:58:39'),
(31, 5, 31, '2025-08-07 03:58:39'),
(32, 5, 32, '2025-08-07 03:58:39'),
(33, 5, 33, '2025-08-07 03:58:39'),
(34, 5, 34, '2025-08-07 03:58:39'),
(35, 5, 35, '2025-08-07 03:58:39'),
(36, 5, 36, '2025-08-07 03:58:39'),
(37, 5, 37, '2025-08-07 03:58:39'),
(38, 5, 38, '2025-08-07 03:58:39'),
(39, 5, 39, '2025-08-07 03:58:39'),
(40, 6, 40, '2025-08-07 03:58:39'),
(41, 6, 41, '2025-08-07 03:58:39'),
(42, 6, 42, '2025-08-07 03:58:39'),
(43, 6, 43, '2025-08-07 03:58:39'),
(44, 6, 44, '2025-08-07 03:58:39'),
(45, 6, 45, '2025-08-07 03:58:39'),
(46, 6, 46, '2025-08-07 03:58:39'),
(47, 6, 47, '2025-08-07 03:58:39'),
(48, 6, 48, '2025-08-07 03:58:39'),
(49, 6, 49, '2025-08-07 03:58:39'),
(50, 6, 50, '2025-08-07 03:58:39'),
(51, 6, 51, '2025-08-07 03:58:39'),
(52, 6, 52, '2025-08-07 03:58:39'),
(53, 6, 53, '2025-08-07 03:58:39'),
(54, 6, 54, '2025-08-07 03:58:39'),
(55, 6, 55, '2025-08-07 03:58:39');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `banking_details`
--
ALTER TABLE `banking_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_banking_details_user` (`user_id`);

--
-- Indexes for table `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cities_coordinates` (`latitude`,`longitude`),
  ADD KEY `idx_cities_state` (`state_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `replied_by` (`replied_by`),
  ADD KEY `idx_contact_status` (`status`),
  ADD KEY `idx_contact_created` (`created_at`),
  ADD KEY `idx_contact_user` (`user_id`);

--
-- Indexes for table `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_countries_coordinates` (`latitude`,`longitude`),
  ADD KEY `idx_countries_code` (`code`);

--
-- Indexes for table `currency_rates`
--
ALTER TABLE `currency_rates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_currency_pair` (`from_currency`,`to_currency`),
  ADD KEY `idx_currency_rates_pair` (`from_currency`,`to_currency`);

--
-- Indexes for table `customer_preferences`
--
ALTER TABLE `customer_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_customer_preferences_user` (`user_id`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_faq_category` (`category`),
  ADD KEY `idx_faq_active` (`is_active`),
  ADD KEY `idx_faq_sort` (`sort_order`);

--
-- Indexes for table `faq_feedback`
--
ALTER TABLE `faq_feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_faq_feedback` (`faq_id`,`user_id`,`ip_address`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_feedback_faq` (`faq_id`);

--
-- Indexes for table `merchant_applications`
--
ALTER TABLE `merchant_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_merchant_status` (`status`),
  ADD KEY `idx_merchant_created` (`created_at`),
  ADD KEY `idx_merchant_user` (`user_id`);

--
-- Indexes for table `merchant_application_documents`
--
ALTER TABLE `merchant_application_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_docs_application` (`application_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user` (`user_id`),
  ADD KEY `idx_notifications_unread` (`user_id`,`is_read`),
  ADD KEY `idx_notifications_type` (`type`),
  ADD KEY `idx_notifications_created` (`created_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orders_user` (`user_id`),
  ADD KEY `idx_orders_status` (`status`),
  ADD KEY `idx_orders_created` (`created_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `idx_order_items_product` (`product_id`);

--
-- Indexes for table `package_types`
--
ALTER TABLE `package_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_products_merchant` (`merchant_id`),
  ADD KEY `idx_products_category` (`category`);

--
-- Indexes for table `product_dimensions`
--
ALTER TABLE `product_dimensions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_id` (`product_id`),
  ADD KEY `package_type_id` (`package_type_id`),
  ADD KEY `idx_product_dimensions_product` (`product_id`);

--
-- Indexes for table `shipments`
--
ALTER TABLE `shipments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tracking_number` (`tracking_number`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `shipping_type_id` (`shipping_type_id`),
  ADD KEY `insurance_id` (`insurance_id`),
  ADD KEY `idx_shipments_tracking` (`tracking_number`),
  ADD KEY `idx_shipments_status` (`status`),
  ADD KEY `idx_shipments_order` (`order_id`);

--
-- Indexes for table `shipping_addresses`
--
ALTER TABLE `shipping_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shipping_addresses_user` (`user_id`);

--
-- Indexes for table `shipping_insurance`
--
ALTER TABLE `shipping_insurance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `shipping_providers`
--
ALTER TABLE `shipping_providers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `shipping_rates`
--
ALTER TABLE `shipping_rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shipping_rate_rules`
--
ALTER TABLE `shipping_rate_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `shipping_type_id` (`shipping_type_id`),
  ADD KEY `idx_shipping_rates_lookup` (`zone_id`,`weight_min_kg`,`weight_max_kg`,`volume_min_cm3`,`volume_max_cm3`);

--
-- Indexes for table `shipping_restrictions`
--
ALTER TABLE `shipping_restrictions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `idx_restrictions_lookup` (`country_code`,`state_code`,`provider_id`);

--
-- Indexes for table `shipping_services`
--
ALTER TABLE `shipping_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_provider_service` (`provider_id`,`code`),
  ADD KEY `shipping_type_id` (`shipping_type_id`);

--
-- Indexes for table `shipping_types`
--
ALTER TABLE `shipping_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `shipping_zones`
--
ALTER TABLE `shipping_zones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_settings_key` (`setting_key`),
  ADD KEY `idx_settings_public` (`is_public`);

--
-- Indexes for table `states`
--
ALTER TABLE `states`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_state_code` (`country_id`,`code`),
  ADD KEY `idx_states_coordinates` (`latitude`,`longitude`),
  ADD KEY `idx_states_code` (`country_id`,`code`);

--
-- Indexes for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_id` (`ticket_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `idx_support_messages_user` (`user_id`),
  ADD KEY `idx_support_messages_status` (`status`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logs_user` (`user_id`),
  ADD KEY `idx_logs_action` (`action`),
  ADD KEY `idx_logs_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_logs_created` (`created_at`);

--
-- Indexes for table `tax_rules`
--
ALTER TABLE `tax_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `state_id` (`state_id`),
  ADD KEY `idx_tax_rules_location` (`country_id`,`state_id`);

--
-- Indexes for table `tracking_events`
--
ALTER TABLE `tracking_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tracking_events_shipment` (`shipment_id`),
  ADD KEY `idx_tracking_events_time` (`event_time`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_addresses_coordinates` (`latitude`,`longitude`),
  ADD KEY `idx_user_addresses_user` (`user_id`),
  ADD KEY `idx_user_addresses_location` (`country_code`,`state_code`,`postal_code`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_user_profiles_user` (`user_id`);

--
-- Indexes for table `zone_countries`
--
ALTER TABLE `zone_countries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_zone_country` (`zone_id`,`country_id`),
  ADD KEY `country_id` (`country_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `banking_details`
--
ALTER TABLE `banking_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `cities`
--
ALTER TABLE `cities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `countries`
--
ALTER TABLE `countries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `currency_rates`
--
ALTER TABLE `currency_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `customer_preferences`
--
ALTER TABLE `customer_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `faq_feedback`
--
ALTER TABLE `faq_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `merchant_applications`
--
ALTER TABLE `merchant_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `merchant_application_documents`
--
ALTER TABLE `merchant_application_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `package_types`
--
ALTER TABLE `package_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `product_dimensions`
--
ALTER TABLE `product_dimensions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shipments`
--
ALTER TABLE `shipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shipping_addresses`
--
ALTER TABLE `shipping_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `shipping_insurance`
--
ALTER TABLE `shipping_insurance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `shipping_providers`
--
ALTER TABLE `shipping_providers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `shipping_rates`
--
ALTER TABLE `shipping_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `shipping_rate_rules`
--
ALTER TABLE `shipping_rate_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `shipping_restrictions`
--
ALTER TABLE `shipping_restrictions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shipping_services`
--
ALTER TABLE `shipping_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `shipping_types`
--
ALTER TABLE `shipping_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `shipping_zones`
--
ALTER TABLE `shipping_zones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `states`
--
ALTER TABLE `states`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `support_messages`
--
ALTER TABLE `support_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tax_rules`
--
ALTER TABLE `tax_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tracking_events`
--
ALTER TABLE `tracking_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_profiles`
--
ALTER TABLE `user_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `zone_countries`
--
ALTER TABLE `zone_countries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `banking_details`
--
ALTER TABLE `banking_details`
  ADD CONSTRAINT `banking_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cities`
--
ALTER TABLE `cities`
  ADD CONSTRAINT `cities_ibfk_1` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD CONSTRAINT `contact_messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `contact_messages_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `contact_messages_ibfk_3` FOREIGN KEY (`replied_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_preferences`
--
ALTER TABLE `customer_preferences`
  ADD CONSTRAINT `customer_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `faqs`
--
ALTER TABLE `faqs`
  ADD CONSTRAINT `faqs_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `faq_feedback`
--
ALTER TABLE `faq_feedback`
  ADD CONSTRAINT `faq_feedback_ibfk_1` FOREIGN KEY (`faq_id`) REFERENCES `faqs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `faq_feedback_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `merchant_applications`
--
ALTER TABLE `merchant_applications`
  ADD CONSTRAINT `merchant_applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `merchant_applications_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `merchant_application_documents`
--
ALTER TABLE `merchant_application_documents`
  ADD CONSTRAINT `merchant_application_documents_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `merchant_applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD CONSTRAINT `payment_methods_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`merchant_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `product_dimensions`
--
ALTER TABLE `product_dimensions`
  ADD CONSTRAINT `product_dimensions_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_dimensions_ibfk_2` FOREIGN KEY (`package_type_id`) REFERENCES `package_types` (`id`);

--
-- Constraints for table `shipments`
--
ALTER TABLE `shipments`
  ADD CONSTRAINT `shipments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shipments_ibfk_2` FOREIGN KEY (`provider_id`) REFERENCES `shipping_providers` (`id`),
  ADD CONSTRAINT `shipments_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `shipping_services` (`id`),
  ADD CONSTRAINT `shipments_ibfk_4` FOREIGN KEY (`shipping_type_id`) REFERENCES `shipping_types` (`id`),
  ADD CONSTRAINT `shipments_ibfk_5` FOREIGN KEY (`insurance_id`) REFERENCES `shipping_insurance` (`id`);

--
-- Constraints for table `shipping_addresses`
--
ALTER TABLE `shipping_addresses`
  ADD CONSTRAINT `shipping_addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shipping_rate_rules`
--
ALTER TABLE `shipping_rate_rules`
  ADD CONSTRAINT `shipping_rate_rules_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `shipping_providers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shipping_rate_rules_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `shipping_services` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shipping_rate_rules_ibfk_3` FOREIGN KEY (`zone_id`) REFERENCES `shipping_zones` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shipping_rate_rules_ibfk_4` FOREIGN KEY (`shipping_type_id`) REFERENCES `shipping_types` (`id`);

--
-- Constraints for table `shipping_restrictions`
--
ALTER TABLE `shipping_restrictions`
  ADD CONSTRAINT `shipping_restrictions_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `shipping_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shipping_services`
--
ALTER TABLE `shipping_services`
  ADD CONSTRAINT `shipping_services_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `shipping_providers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shipping_services_ibfk_2` FOREIGN KEY (`shipping_type_id`) REFERENCES `shipping_types` (`id`);

--
-- Constraints for table `states`
--
ALTER TABLE `states`
  ADD CONSTRAINT `states_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD CONSTRAINT `support_messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_messages_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tax_rules`
--
ALTER TABLE `tax_rules`
  ADD CONSTRAINT `tax_rules_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tax_rules_ibfk_2` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tracking_events`
--
ALTER TABLE `tracking_events`
  ADD CONSTRAINT `tracking_events_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD CONSTRAINT `user_addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `zone_countries`
--
ALTER TABLE `zone_countries`
  ADD CONSTRAINT `zone_countries_ibfk_1` FOREIGN KEY (`zone_id`) REFERENCES `shipping_zones` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `zone_countries_ibfk_2` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
