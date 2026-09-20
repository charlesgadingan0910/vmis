-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Sep 20, 2026 at 08:17 AM
-- Server version: 9.0.1
-- PHP Version: 8.3.11
SET FOREIGN_KEY_CHECKS = 0;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `vmis`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_types`
--

DROP TABLE IF EXISTS `account_types`;
CREATE TABLE IF NOT EXISTS `account_types` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `level` int NOT NULL,
  `type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `account_types`
--

INSERT INTO `account_types` (`id`, `level`, `type`, `created_at`, `updated_at`) VALUES
(1, 1, 'SUPER ADMINISTRATOR', NULL, NULL),
(2, 2, 'ADMINISTRATOR', NULL, NULL),
(3, 3, 'UNIT ADMINISTRATOR', NULL, NULL),
(4, 4, 'STATION ADMINISTRATOR', NULL, NULL),
(5, 5, 'VIEWER', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

DROP TABLE IF EXISTS `drivers`;
CREATE TABLE IF NOT EXISTS `drivers` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `rank` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `firstname` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `middlename` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastname` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qlfr` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_number` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_expiration_date` date DEFAULT NULL,
  `license_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_number` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_path` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `drivers_license_number_unique` (`license_number`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`id`, `rank`, `firstname`, `middlename`, `lastname`, `qlfr`, `license_number`, `license_expiration_date`, `license_type`, `contact_number`, `photo_path`, `status`, `created_at`, `updated_at`) VALUES
(4, 'Pat', 'CHARLES', 'REBUSTILLO', 'GADINGAN', NULL, 'E02-22-305409', '2027-09-10', 'Professional', '091234567890', 'driver_photos/LMTj5lJ12t8brztq4UKbr9YhvKU4ky8g75mWIAE8.jpg', 'active', '2026-09-19 23:50:05', '2026-09-19 23:50:05');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_09_15_061321_create_account_types_table', 1),
(5, '2026_09_16_041039_create_units_table', 1),
(6, '2026_09_16_041326_create_stations_table', 1),
(7, '2026_09_16_041917_create_ranks_table', 1),
(8, '2026_09_16_055310_create_vehicle_types_table', 1),
(9, '2026_09_16_055311_create_drivers_table', 1),
(10, '2026_09_16_055317_create_vehicles_table', 1),
(11, '2026_09_18_005523_create_vehicle_registrations_table', 2),
(12, '2026_09_18_030000_fix_vehicles_status_enum_values', 2),
(13, '2026_09_18_060000_create_vehicle_qr_prints_table', 2),
(14, '2026_09_18_090000_add_photo_path_to_drivers_table', 3);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ranks`
--

DROP TABLE IF EXISTS `ranks`;
CREATE TABLE IF NOT EXISTS `ranks` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `rank_level` int NOT NULL,
  `rank_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rank_abbvr` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ranks`
--

INSERT INTO `ranks` (`id`, `rank_level`, `rank_name`, `rank_abbvr`, `created_at`, `updated_at`) VALUES
(1, 1, 'POLICE GENERAL', 'PGEN', NULL, NULL),
(2, 2, 'POLICE LIEUTENANT GENERAL', 'PLTGEN', NULL, NULL),
(3, 3, 'POLICE MAJOR GENERAL', 'PMGEN', NULL, NULL),
(4, 4, 'POLICE BRIGADIER GENERAL', 'PBGEN', NULL, NULL),
(5, 5, 'POLICE COLONEL', 'PCOL', NULL, NULL),
(6, 6, 'POLICE LIEUTENANT COLONEL', 'PLTCOL', NULL, NULL),
(7, 7, 'POLICE MAJOR', 'PMAJ', NULL, NULL),
(8, 8, 'POLICE CAPTAIN', 'PCPT', NULL, NULL),
(9, 9, 'POLICE LIEUTENANT', 'PLT', NULL, NULL),
(10, 10, 'POLICE EXECUTIVE MASTER SERGEANT', 'PEMS', NULL, NULL),
(11, 11, 'POLICE CHIEF MASTER SERGEANT', 'PCMS', NULL, NULL),
(12, 12, 'POLICE SENIOR MASTER SERGEANT', 'PSMS', NULL, NULL),
(13, 13, 'POLICE MASTER SERGEANT', 'PMSg', NULL, NULL),
(14, 14, 'POLICE STAFF SERGEANT', 'PSSg', NULL, NULL),
(15, 15, 'POLICE CORPORAL', 'PCpl', NULL, NULL),
(16, 16, 'PATROLMAN/PATROLWOMAN', 'Pat', NULL, NULL),
(17, 17, 'NON-UNIFORMED PERSONNEL', 'NUP', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('YQaZqbUeM0TxUxnJx9sV70qpNEkoEtpzIfsZzO68', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'eyJfdG9rZW4iOiJuQ29rM3ZjVWNPUWlHSklRdllsNjR0MldBWWRsQWI0UmY2TDdTQ0gwIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdFwvdm1pc1wvcHVibGljXC9kcml2ZXJzXC80XC9waG90byIsInJvdXRlIjoiZHJpdmVycy5waG90byJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX0sImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjoxfQ==', 1789890619);

-- --------------------------------------------------------

--
-- Table structure for table `stations`
--

DROP TABLE IF EXISTS `stations`;
CREATE TABLE IF NOT EXISTS `stations` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` int UNSIGNED NOT NULL,
  `station_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `station_abbvr` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=167 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stations`
--

INSERT INTO `stations` (`id`, `unit_id`, `station_name`, `station_abbvr`, `created_at`, `updated_at`) VALUES
(1, 1, '1ST PROVINCIAL MOBILE FORCE COMPANY', '1ST PMFC', NULL, NULL),
(2, 1, '2ND PROVINCIAL MOBILE FORCE COMPANY', '2ND PMFC', NULL, NULL),
(3, 1, 'PROVINCIAL HEADQUARTERS', 'PHQ', NULL, NULL),
(4, 1, 'DARAGA MUNICIPAL POLICE STATION', 'DARAGA MPS', NULL, NULL),
(5, 1, 'CAMALIG MUNICIPAL POLICE STATION', 'CAMALIG MPS', NULL, NULL),
(6, 1, 'GUINOBATAN MUNICIPAL POLICE STATION', 'GUINOBATAN MPS', NULL, NULL),
(7, 1, 'JOVELLAR MUNICIPAL POLICE STATION', 'JOVELLAR MPS', NULL, NULL),
(8, 1, 'LIGAO CITY POLICE STATION', 'LIGAO CPS', NULL, NULL),
(9, 1, 'OAS MUNICIPAL POLICE STATION', 'OAS MPS', NULL, NULL),
(10, 1, 'POLANGUI MUNICIPAL POLICE STATION', 'POLANGUI MPS', NULL, NULL),
(11, 1, 'PIODURAN MUNICIPAL POLICE STATION', 'PIODURAN MPS', NULL, NULL),
(12, 1, 'LIBON MUNICIPAL POLICE STATION', 'LIBON MPS', NULL, NULL),
(13, 1, 'LEGAZPI CITY POLICE STATION', 'LEGAZPI CPS', NULL, NULL),
(14, 1, 'MANITO MUNICIPAL POLICE STATION', 'MANITO MPS', NULL, NULL),
(15, 1, 'RAPU-RAPU MUNICIPAL POLICE STATION', 'RAPU-RAPU MPS', NULL, NULL),
(16, 1, 'STO DOMINGO MUNICIPAL POLICE STATION', 'STO DOMINGO MPS', NULL, NULL),
(17, 1, 'BACACAY MUNICIPAL POLICE STATION', 'BACACAY MPS', NULL, NULL),
(18, 1, 'MALILIPOT MUNICIPAL POLICE STATION', 'MALILIPOT MPS', NULL, NULL),
(19, 1, 'MALINAO MUNICIPAL POLICE STATION', 'MALINAO MPS', NULL, NULL),
(20, 1, 'TABACO CITY POLICE STATION', 'TABACO CPS', NULL, NULL),
(21, 1, 'TIWI MUNICIPAL POLICE STATION', 'TIWI MPS', NULL, NULL),
(22, 2, '1ST PROVINCIAL MOBILE FORCE COMPANY', '1ST PMFC', NULL, NULL),
(23, 2, '2ND PROVINCIAL MOBILE FORCE COMPANY', '2ND PMFC', NULL, NULL),
(24, 2, 'PROVINCIAL HEADQUARTERS', 'PHQ', NULL, NULL),
(25, 2, 'BAGAMANOC MUNICIPAL POLICE STATION', 'BAGAMANOC MPS', NULL, NULL),
(26, 2, 'BARAS MUNICIPAL POLICE STATION', 'BARAS MPS', NULL, NULL),
(27, 2, 'BATO MUNICIPAL POLICE STATION', 'BATO MPS', NULL, NULL),
(28, 2, 'CARAMORAN  MUNICIPAL POLICE STATION', 'CARAMORAN MPS', NULL, NULL),
(29, 2, 'GIGMOTO MUNICIPAL POLICE STATION', 'GIGMOTO MPS', NULL, NULL),
(30, 2, 'PANDAN MUNICIPAL POLICE STATION', 'PANDAN MPS', NULL, NULL),
(31, 2, 'PANGANIBAN MUNICIPAL POLICE STATION', 'PANGANIBAN MPS', NULL, NULL),
(32, 2, 'SAN  ANDRES MUNICIPAL POLICE STATION', 'SAN ANDRES MPS', NULL, NULL),
(33, 2, 'SAN MIGUEL MUNICIPAL POLICE STATION', 'SAN MIGUEL MPS', NULL, NULL),
(34, 2, 'VIGA MUNICIPAL POLICE STATION', 'VIGA MPS', NULL, NULL),
(35, 2, 'VIRAC MUNICIPAL POLICE STATION', 'VIRAC MPS', NULL, NULL),
(36, 3, '1ST PROVINCIAL MOBILE FORCE COMPANY', '1ST PMFC', NULL, NULL),
(37, 3, '2ND PROVINCIAL MOBILE FORCE COMPANY', '2ND PMFC', NULL, NULL),
(38, 3, 'PROVINCIAL HEADQUARTERS', 'PHQ', NULL, NULL),
(39, 3, 'BASUD MUNICIPAL POLICE STATION', 'BASUD MPS', NULL, NULL),
(40, 3, 'CAPALONGA MUNICIPAL POLICE STATION', 'CAPALONGA MPS', NULL, NULL),
(41, 3, 'DAET MUNICIPAL POLICE STATION', 'DAET MPS', NULL, NULL),
(42, 3, 'JOSE PANGANIBAN MUNICIPAL POLICE STATION', 'JOSE PANGANIBAN MPS', NULL, NULL),
(43, 3, 'LABO MUNICIPAL POLICE STATION', 'LABO MPS', NULL, NULL),
(44, 3, 'MERCEDES MUNICIPAL POLICE STATION', 'MERCEDES MPS', NULL, NULL),
(45, 3, 'PARACALE MUNICIPAL POLICE STATION', 'PARACALE MPS', NULL, NULL),
(46, 3, 'SAN LORENZO RUIZ MUNICIPAL POLICE STATION', 'SAN LORENZO RUIZ MPS', NULL, NULL),
(47, 3, 'SAN VICENTE MUNICIPAL POLICE STATION', 'SAN VICENTE MPS', NULL, NULL),
(48, 3, 'SANTA ELENA MUNICIPAL POLICE STATION', 'SANTA ELENA MPS', NULL, NULL),
(49, 3, 'TALISAY MUNICIPAL POLICE STATION', 'TALISAY MPS', NULL, NULL),
(50, 3, 'VINZONS MUNICIPAL POLICE STATION', 'VINZONS MPS', NULL, NULL),
(51, 4, '1ST PROVINCIAL MOBILE FORCE COMPANY', '1ST PMFC', NULL, NULL),
(52, 4, '2ND PROVINCIAL MOBILE FORCE COMPANY', '2ND PMFC', NULL, NULL),
(53, 4, 'PROVINCIAL HEADQUARTERS', 'PHQ', NULL, NULL),
(54, 4, 'BAAO MUNICIPAL POLICE STATION', 'BAAO MPS', NULL, NULL),
(55, 4, 'BALATAN MUNICIPAL POLICE STATION', 'BALATAN MPS', NULL, NULL),
(56, 4, 'BATO MUNICIPAL POLICE STATION', 'BATO MPS', NULL, NULL),
(57, 4, 'BOMBON MUNICIPAL POLICE STATION', 'BOMBON MPS', NULL, NULL),
(58, 4, 'BUHI MUNICIPAL POLICE STATION', 'BUHI MPS', NULL, NULL),
(59, 4, 'BULA MUNICIPAL POLICE STATION', 'BULA MPS', NULL, NULL),
(60, 4, 'CABUSAO MUNICIPAL POLICE STATION', 'CABUSAO MPS', NULL, NULL),
(61, 4, 'CALABANGA MUNICIPAL POLICE STATION', 'CALABANGA MPS', NULL, NULL),
(62, 4, 'CAMALIGAN MUNICIPAL POLICE STATION', 'CAMALIGAN MPS', NULL, NULL),
(63, 4, 'CANAMAN MUNICIPAL POLICE STATION', 'CANAMAN MPS', NULL, NULL),
(64, 4, 'CARAMOAN MUNICIPAL POLICE STATION', 'CARAMOAN MPS', NULL, NULL),
(65, 4, 'DEL GALLEGO MUNICIPAL POLICE STATION', 'DEL GALLEGO MPS', NULL, NULL),
(66, 4, 'GAINZA MUNICIPAL POLICE STATION', 'GAINZA MPS', NULL, NULL),
(67, 4, 'GARCHITORENA MUNICIPAL POLICE STATION', 'GARCHITORENA MPS', NULL, NULL),
(68, 4, 'GOA MUNICIPAL POLICE STATION', 'GOA MPS', NULL, NULL),
(69, 4, 'IRIGA CITY POLICE STATION', 'IRIGA CPS', NULL, NULL),
(70, 4, 'LAGONOY MUNICIPAL POLICE STATION', 'LAGONOY MPS', NULL, NULL),
(71, 4, 'LIBMANAN MUNICIPAL POLICE STATION', 'LIBMANAN MPS', NULL, NULL),
(72, 4, 'LUPI MUNICIPAL POLICE STATION', 'LUPI MPS', NULL, NULL),
(73, 4, 'MAGARAO MUNICIPAL POLICE STATION', 'MAGARAO MPS', NULL, NULL),
(74, 4, 'MILAOR MUNICIPAL POLICE STATION', 'MILAOR MPS', NULL, NULL),
(75, 4, 'MINALABAC MUNICIPAL POLICE STATION', 'MINALABAC MPS', NULL, NULL),
(76, 4, 'NABUA MUNICIPAL POLICE STATION', 'NABUA MPS', NULL, NULL),
(77, 4, 'OCAMPO MUNICIPAL POLICE STATION', 'OCAMPO MPS', NULL, NULL),
(78, 4, 'PAMPLONA MUNICIPAL POLICE STATION', 'PAMPLONA MPS', NULL, NULL),
(79, 4, 'PASACAO MUNICIPAL POLICE STATION', 'PASACAO MPS', NULL, NULL),
(80, 4, 'PILI MUNICIPAL POLICE STATION', 'PILI MPS', NULL, NULL),
(81, 4, 'PRESENTACION MUNICIPAL POLICE STATION', 'PRESENTACION MPS', NULL, NULL),
(82, 4, 'RAGAY MUNICIPAL POLICE STATION', 'RAGAY MPS', NULL, NULL),
(83, 4, 'SAGNAY MUNICIPAL POLICE STATION', 'SAGNAY MPS', NULL, NULL),
(84, 4, 'SAN FERNANDO MUNICIPAL POLICE STATION', 'SAN FERNANDO MPS', NULL, NULL),
(85, 4, 'SAN JOSE MUNICIPAL POLICE STATION', 'SAN JOSE MPS', NULL, NULL),
(86, 4, 'SIPOCOT MUNICIPAL POLICE STATION', 'SIPOCOT MPS', NULL, NULL),
(87, 4, 'SIRUMA MUNICIPAL POLICE STATION', 'SIRUMA MPS', NULL, NULL),
(88, 4, 'TIGAON MUNICIPAL POLICE SATION', 'TIGAON MPS', NULL, NULL),
(89, 4, 'TINAMBAC MUNICIPAL POLICE STATION', 'TINAMBAC MPS', NULL, NULL),
(90, 5, '1ST PROVINCIAL MOBILE FORCE COMPANY', '1ST PMFC', NULL, NULL),
(91, 5, '2ND PROVINCIAL MOBILE FORCE COMPANY', '2ND PMFC', NULL, NULL),
(92, 5, 'PROVINCIAL HEADQUARTERS', 'PHQ', NULL, NULL),
(93, 5, 'AROROY MUNICIPAL POLICE STATION', 'AROROY MPS', NULL, NULL),
(94, 5, 'BALENO MUNICIPAL POLICE STATION', 'BALENO MPS', NULL, NULL),
(95, 5, 'BALUD MUNICIPAL POLICE STATION', 'BALUD MPS', NULL, NULL),
(96, 5, 'BATUAN MUNICIPAL POLICE STATION', 'BATUAN MPS', NULL, NULL),
(97, 5, 'CATAINGAN MUNICIPAL POLICE STATION', 'CATAINGAN MPS', NULL, NULL),
(98, 5, 'CAWAYAN MUNICIPAL POLICE STATION', 'CAWAYAN MPS', NULL, NULL),
(99, 5, 'CLAVERIA MUNICIPAL POLICE STATION', 'CLAVERIA MPS', NULL, NULL),
(100, 5, 'DIMASALANG MUNICIPAL POLICE STATION', 'DIMASALANG MPS', NULL, NULL),
(101, 5, 'ESPERANZA MUNICIPAL POLICE STATION', 'ESPERANZA MPS', NULL, NULL),
(102, 5, 'MANDAON MUNICIPAL POLICE STATION', 'MANDAON MPS', NULL, NULL),
(103, 5, 'MASBATE CITY POLICE STATION', 'MASBATE CPS', NULL, NULL),
(104, 5, 'MILAGROS MUNICIPAL POLICE STATION', 'MILAGROS MPS', NULL, NULL),
(105, 5, 'MOBO MUNICIPAL POLICE STATION', 'MOBO MPS', NULL, NULL),
(106, 5, 'MONREAL MUNICIPAL POLICE STATION', 'MONREAL MPS', NULL, NULL),
(107, 5, 'PALANAS MUNICIPAL POLICE STATION', 'PALANAS MPS', NULL, NULL),
(108, 5, 'PIO V CORPUZ MUNICIPAL POLICE STATION', 'PIO V CORPUZ MPS', NULL, NULL),
(109, 5, 'PLACER MUNICIPAL POLICE STATION', 'PLACER MPS', NULL, NULL),
(110, 5, 'SAN FERNANDO MUNICIPAL POLICE STATION', 'SAN FERNANDO MPS', NULL, NULL),
(111, 5, 'SAN JACINTO MUNICIPAL POLICE STATION', 'SAN JACINTO MPS', NULL, NULL),
(112, 5, 'SAN PASCUAL MUNICIPAL POLICE STATION', 'SAN PASCUAL MPS', NULL, NULL),
(113, 5, 'USON MUNICIPAL POLICE STATION', 'USON MPS', NULL, NULL),
(114, 6, 'CITY MOBILE FORCE COMPANY', 'CMFC', NULL, NULL),
(115, 6, 'CITY INTELLIGENCE UNIT', 'CIU', NULL, NULL),
(116, 6, 'MOBILE PATROL UNIT', 'MPU', NULL, NULL),
(117, 6, 'NAGA CPO HQ', 'NCPO HQ', NULL, NULL),
(118, 6, 'POLICE STATION 1', 'PS1', NULL, NULL),
(119, 6, 'POLICE STATION 2', 'PS2', NULL, NULL),
(120, 6, 'POLICE STATION 3', 'PS3', NULL, NULL),
(121, 6, 'POLICE STATION 4', 'PS4', NULL, NULL),
(122, 6, 'POLICE STATION 5', 'PS5', NULL, NULL),
(123, 6, 'POLICE STATION 6', 'PS6', NULL, NULL),
(124, 6, 'TEU', 'TEU', NULL, NULL),
(125, 7, 'OFFICE OF THE REGIONAL DIRECTOR', 'ORD', NULL, NULL),
(126, 7, 'OFFICE OF THE DRDA', 'ODRDA', NULL, NULL),
(127, 7, 'OFFICE OF THE DRDO', 'ODRDO', NULL, NULL),
(128, 7, 'OFFICE OF THE CRS', 'OCRS', NULL, NULL),
(129, 7, 'REGIONAL PERSONNEL AND RECORDS MANAGEMENT DIVISION', 'RPRMD', NULL, NULL),
(130, 7, 'REGIONAL INTELLIGENCE DIVISION', 'RID', NULL, NULL),
(131, 7, 'REGIONAL OPERATIONS DIVISION', 'ROD', NULL, NULL),
(132, 7, 'REGIONAL LOGISTICS AND RESEARCH AND DEVELOPMENT DIVISION', 'RLRDD', NULL, NULL),
(133, 7, 'REGIONAL COMMUNITY AFFAIRS AND DEVELOPMENT DIVISION', 'RCADD', NULL, NULL),
(134, 7, 'REGIONAL COMPTROLLERSHIP DIVISION', 'RCD', NULL, NULL),
(135, 7, 'REGIONAL INVESTIGATION AND DETECTIVE MANAGEMENT DIVISION', 'RIDMD', NULL, NULL),
(136, 7, 'REGIONAL LEARNING AND DOCTRINE DEVELOPMENT DIVISION', 'RLDDD', NULL, NULL),
(137, 7, 'RPSMD', 'RPSMD', NULL, NULL),
(138, 7, 'REGIONAL INFORMATION AND COMMUNICATIONS TECHNOLOGY MANAGEMENT DIVISION', 'RICTMD', NULL, NULL),
(139, 7, 'HRAO', 'HRAO', NULL, NULL),
(140, 7, 'RPSMU', 'RPSMU', NULL, NULL),
(141, 7, 'RHSU', 'RHSU', NULL, NULL),
(142, 7, 'OFFICE OF THE RESPO', 'ORESPO', NULL, NULL),
(143, 8, 'HQ', 'HQ', NULL, NULL),
(144, 8, '501st MC', '501st MC', NULL, NULL),
(145, 8, '502nd MC', '502nd MC', NULL, NULL),
(146, 8, '503rd MC', '503rd MC', NULL, NULL),
(147, 8, '504th MC', '504th MC', NULL, NULL),
(148, 8, '505th MC', '505th MC', NULL, NULL),
(149, 8, 'TSC', 'TSC', NULL, NULL),
(150, 9, '1ST PROVINCIAL MOBILE FORCE COMPANY', '1ST PMFC', NULL, NULL),
(151, 9, '2ND PROVINCIAL MOBILE FORCE COMPANY', '2ND PMFC', NULL, NULL),
(152, 9, 'BARCELONA MUNICIPAL POLICE STATION', 'BARCELONA MPS', NULL, NULL),
(153, 9, 'BULAN MUNICIPAL POLICE STATION', 'BULAN MPS', NULL, NULL),
(154, 9, 'BULUSAN MUNICIPAL POLICE STATION', 'BULUSAN MPS', NULL, NULL),
(155, 9, 'CASIGURAN MUNICIPAL POLICE STATION', 'CASIGURAN MPS', NULL, NULL),
(156, 9, 'CASTILLA MUNICIPAL POLICE STATION', 'CASTILLA MPS', NULL, NULL),
(157, 9, 'DONSOL MUNICIPAL POLICE STATION', 'DONSOL MPS', NULL, NULL),
(158, 9, 'GUBAT MUNICIPAL POLICE STATION', 'GUBAT MPS', NULL, NULL),
(159, 9, 'IROSIN MUNICIPAL POLICE STATION', 'IROSIN MPS', NULL, NULL),
(160, 9, 'JUBAN MUNICIPAL POLICE STATION', 'JUBAN MPS', NULL, NULL),
(161, 9, 'MAGALLANES MUNICIPAL POLICE STATION', 'MAGALLANES MPS', NULL, NULL),
(162, 9, 'MATNOG MUNICIPAL POLICE STATION', 'MATNOG MPS', NULL, NULL),
(163, 9, 'PILAR MUNICIPAL POLICE STATION', 'PILAR MPS', NULL, NULL),
(164, 9, 'PRIETO DIAZ MUNICIPAL POLICE STATION', 'PRIETO DIAZ MPS', NULL, NULL),
(165, 9, 'SANTA MAGDALENA MUNICIPAL POLICE STATION', 'STA MAGDALENA MPS', NULL, NULL),
(166, 9, 'SORSOGON CITY POLICE STATION', 'SORSOGON CPS', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

DROP TABLE IF EXISTS `units`;
CREATE TABLE IF NOT EXISTS `units` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_abbvr` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `unit_name`, `unit_abbvr`, `created_at`, `updated_at`) VALUES
(1, 'ALBAY PPO', 'ALBAY PPO', NULL, NULL),
(2, 'CATANDUANES PPO', 'CATANDUANES PPO', NULL, NULL),
(3, 'CAMARINES NORTE PPO', 'CAMARINES NORTE PPO', NULL, NULL),
(4, 'CAMARINES SUR PPO', 'CAMARINES SUR PPO', NULL, NULL),
(5, 'MASBATE PPO', 'MASBATE PPO', NULL, NULL),
(6, 'NAGA CPO', 'NAGA CPO', NULL, NULL),
(7, 'REGIONAL HEADQUARTERS', 'RHQ', NULL, NULL),
(8, 'REGIONAL MOBILE FORCE BATALLION 5', 'RMFB5', NULL, NULL),
(9, 'SORSOGON PPO', 'SORSOGON PPO', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rank` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastname` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `firstname` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `middlename` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qlfr` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fullname` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `badge_number` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_id` int UNSIGNED DEFAULT NULL,
  `station_id` int UNSIGNED DEFAULT NULL,
  `is_active` enum('1','0') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `is_online` enum('1','0') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `is_password_changed` enum('1','0') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_badge_number_unique` (`badge_number`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `account_type`, `rank`, `lastname`, `firstname`, `middlename`, `qlfr`, `fullname`, `badge_number`, `email`, `email_verified_at`, `password`, `unit_id`, `station_id`, `is_active`, `is_online`, `is_password_changed`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'SUPER ADMINISTRATOR', 'Pat', 'Administrator', 'Super', NULL, NULL, 'Super Administrator', 'superadmin001', 'super.admin@pnp.gov.ph', NULL, '$2y$12$zHthjxInlKv8tiNeQessMegRdy0.l/uy7pxs8Drigu60ZdqHU8.Qa', NULL, NULL, '1', '1', '1', NULL, NULL, '2026-09-19 05:19:19');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

DROP TABLE IF EXISTS `vehicles`;
CREATE TABLE IF NOT EXISTS `vehicles` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `plate_number` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `engine_number` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `chassis_number` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `make` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicle_type_id` bigint UNSIGNED NOT NULL,
  `assigned_driver_id` bigint UNSIGNED DEFAULT NULL,
  `year_model` smallint UNSIGNED DEFAULT NULL,
  `color` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acquisition_date` date DEFAULT NULL,
  `unit_id` int UNSIGNED DEFAULT NULL,
  `station_id` int UNSIGNED DEFAULT NULL,
  `odometer_km` int UNSIGNED NOT NULL DEFAULT '0',
  `next_pms_date` date DEFAULT NULL,
  `status` enum('SERVICEABLE','UNSERVICEABLE','BER') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SERVICEABLE',
  `is_active` enum('1','0') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `qr_code` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vehicles_plate_number_unique` (`plate_number`),
  UNIQUE KEY `vehicles_qr_code_unique` (`qr_code`),
  KEY `vehicles_vehicle_type_id_foreign` (`vehicle_type_id`),
  KEY `vehicles_assigned_driver_id_foreign` (`assigned_driver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_qr_prints`
--

DROP TABLE IF EXISTS `vehicle_qr_prints`;
CREATE TABLE IF NOT EXISTS `vehicle_qr_prints` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `vehicle_id` bigint UNSIGNED NOT NULL,
  `printed_by` bigint UNSIGNED NOT NULL,
  `context` enum('single','bulk') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'single',
  `printed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vehicle_qr_prints_vehicle_id_foreign` (`vehicle_id`),
  KEY `vehicle_qr_prints_printed_by_foreign` (`printed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_registrations`
--

DROP TABLE IF EXISTS `vehicle_registrations`;
CREATE TABLE IF NOT EXISTS `vehicle_registrations` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `vehicle_id` bigint UNSIGNED NOT NULL,
  `or_file_path` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cr_file_path` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registration_year` year NOT NULL,
  `uploaded_by` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vehicle_registrations_vehicle_id_foreign` (`vehicle_id`),
  KEY `vehicle_registrations_uploaded_by_foreign` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_types`
--

DROP TABLE IF EXISTS `vehicle_types`;
CREATE TABLE IF NOT EXISTS `vehicle_types` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vehicle_types_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicle_types`
--

INSERT INTO `vehicle_types` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Patrol Jeeps / Light Patrol Cars', '', NULL, NULL),
(2, 'Personnel Carriers', '', NULL, NULL),
(3, 'Transport and Utility Vans', '', NULL, NULL);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_assigned_driver_id_foreign` FOREIGN KEY (`assigned_driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vehicles_vehicle_type_id_foreign` FOREIGN KEY (`vehicle_type_id`) REFERENCES `vehicle_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicle_qr_prints`
--
ALTER TABLE `vehicle_qr_prints`
  ADD CONSTRAINT `vehicle_qr_prints_printed_by_foreign` FOREIGN KEY (`printed_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `vehicle_qr_prints_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicle_registrations`
--
ALTER TABLE `vehicle_registrations`
  ADD CONSTRAINT `vehicle_registrations_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `vehicle_registrations_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;
COMMIT;

SET FOREIGN_KEY_CHECKS = 1;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
