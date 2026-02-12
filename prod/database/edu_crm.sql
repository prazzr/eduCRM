-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 12, 2026 at 11:44 AM
-- Server version: 10.11.14-MariaDB-0ubuntu0.24.04.1
-- PHP Version: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `edu_crm`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_daily_summary`
--

CREATE TABLE `activity_daily_summary` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `summary_date` date NOT NULL,
  `total_actions` int(11) DEFAULT 0,
  `inquiries_added` int(11) DEFAULT 0,
  `inquiries_converted` int(11) DEFAULT 0,
  `students_added` int(11) DEFAULT 0,
  `tasks_completed` int(11) DEFAULT 0,
  `appointments_created` int(11) DEFAULT 0,
  `logins` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `analytics_goals`
--

CREATE TABLE `analytics_goals` (
  `id` int(11) NOT NULL,
  `goal_name` varchar(100) NOT NULL,
  `goal_type` enum('inquiries','conversions','revenue','applications') NOT NULL,
  `target_value` decimal(15,2) NOT NULL,
  `current_value` decimal(15,2) DEFAULT 0.00,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `status` enum('active','achieved','missed','cancelled') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `analytics_goals`
--

INSERT INTO `analytics_goals` (`id`, `goal_name`, `goal_type`, `target_value`, `current_value`, `period_start`, `period_end`, `status`, `created_by`, `created_at`) VALUES
(1, 'Monthly Inquiry Target', 'inquiries', 500.00, 11.00, '2026-01-01', '2026-01-31', 'active', 1, '2026-01-01 09:56:20'),
(2, 'Q1 Revenue Target', 'revenue', 500000.00, 70000.00, '2026-01-01', '2026-03-31', 'active', 1, '2026-01-01 09:56:20');

-- --------------------------------------------------------

--
-- Table structure for table `analytics_metrics`
--

CREATE TABLE `analytics_metrics` (
  `id` int(11) NOT NULL,
  `metric_name` varchar(100) NOT NULL,
  `metric_value` decimal(15,2) NOT NULL,
  `metric_type` enum('count','revenue','percentage','duration') DEFAULT 'count',
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `analytics_snapshots`
--

CREATE TABLE `analytics_snapshots` (
  `id` int(11) NOT NULL,
  `snapshot_date` date NOT NULL,
  `total_inquiries` int(11) DEFAULT 0,
  `total_revenue` decimal(15,2) DEFAULT 0.00,
  `metrics_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metrics_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `analytics_snapshots`
--

INSERT INTO `analytics_snapshots` (`id`, `snapshot_date`, `total_inquiries`, `total_revenue`, `metrics_json`, `created_at`) VALUES
(1, '2026-01-06', 11, 70000.00, '{\"total_students\":16,\"total_applications\":6,\"conversion_rate\":145.45,\"active_counselors\":1,\"pending_tasks\":1,\"upcoming_appointments\":0}', '2026-01-06 11:03:04'),
(2, '2026-01-21', 11, 70000.00, '{\"total_students\":16,\"total_applications\":6,\"conversion_rate\":145.45,\"active_counselors\":1,\"pending_tasks\":1,\"upcoming_appointments\":0}', '2026-01-21 06:32:07');

-- --------------------------------------------------------

--
-- Table structure for table `application_statuses`
--

CREATE TABLE `application_statuses` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status_order` int(11) NOT NULL DEFAULT 0,
  `is_final` tinyint(1) DEFAULT 0 COMMENT 'True for terminal statuses',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `application_statuses`
--

INSERT INTO `application_statuses` (`id`, `name`, `status_order`, `is_final`, `created_at`) VALUES
(1, 'applied', 1, 0, '2026-01-04 11:42:24'),
(2, 'offer_received', 2, 0, '2026-01-04 11:42:24'),
(3, 'offer_accepted', 3, 0, '2026-01-04 11:42:24'),
(4, 'visa_lodged', 4, 0, '2026-01-04 11:42:24'),
(5, 'visa_granted', 5, 1, '2026-01-04 11:42:24'),
(6, 'rejected', 6, 1, '2026-01-04 11:42:24');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `inquiry_id` int(11) DEFAULT NULL,
  `counselor_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `appointment_date` datetime NOT NULL,
  `duration_minutes` int(11) DEFAULT 30,
  `location` varchar(255) DEFAULT NULL,
  `meeting_link` varchar(255) DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled','no_show') DEFAULT 'scheduled',
  `reminder_sent` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `student_id`, `inquiry_id`, `counselor_id`, `title`, `description`, `appointment_date`, `duration_minutes`, `location`, `meeting_link`, `status`, `reminder_sent`, `notes`, `created_at`, `updated_at`, `branch_id`) VALUES
(1, NULL, NULL, 1, 'tEST', 'TEST', '2026-01-04 13:56:00', 30, NULL, NULL, 'completed', 0, 'TEST', '2026-01-04 08:11:52', '2026-01-04 08:12:35', NULL),
(2, NULL, NULL, 1, 'Visit', 'test', '2026-01-05 17:05:00', 30, 'office', NULL, 'scheduled', 0, NULL, '2026-01-04 10:21:14', '2026-01-04 10:21:14', NULL),
(3, 27, NULL, 1, 'Test Meeting', '', '2026-01-23 10:00:00', 30, NULL, NULL, 'scheduled', 0, NULL, '2026-01-22 05:43:33', '2026-01-22 05:43:33', NULL),
(4, 27, NULL, 1, 'Far Future Debug', 'Testing appointment creation with far future date', '2029-12-12 12:12:00', 30, NULL, NULL, 'scheduled', 0, NULL, '2026-01-22 05:55:17', '2026-01-22 05:55:17', NULL),
(5, 10, NULL, 18, 'Counselor Test', 'Testing different counselor and student', '2029-11-11 11:11:00', 60, NULL, NULL, 'scheduled', 0, NULL, '2026-01-22 05:55:49', '2026-01-22 05:55:49', NULL),
(6, NULL, 12, 1, 'Inquiry Meeting', 'Testing inquiry appointment', '2029-10-10 10:10:00', 30, NULL, NULL, 'scheduled', 0, NULL, '2026-01-22 05:56:16', '2026-01-22 05:56:16', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `attachments`
--

CREATE TABLE `attachments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_mime` varchar(100) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attachments`
--

INSERT INTO `attachments` (`id`, `user_id`, `file_path`, `file_name`, `file_mime`, `file_size`, `created_at`) VALUES
(1, 25, 'secure_uploads/1767682943_LOCATION.png', '1767682943_LOCATION.png', 'image/png', 4070, '2026-01-06 07:02:23'),
(2, 32, 'secure_uploads/1770118014_Binayak Pandey - Passport.pdf', '1770118014_Binayak Pandey - Passport.pdf', 'application/pdf', 4240609, '2026-02-03 11:26:54'),
(3, 32, 'secure_uploads/1770118067_Acedemic Certificate of Binayak Pandey.pdf', '1770118067_Acedemic Certificate of Binayak Pandey.pdf', 'application/pdf', 6568807, '2026-02-03 11:27:47'),
(4, 32, 'secure_uploads/1770289759_SLCE Grade 10 Certificate, Character and Gradesheet.pdf', '1770289759_SLCE Grade 10 Certificate, Character and Gradesheet.pdf', 'application/pdf', 1232865, '2026-02-05 11:09:19'),
(5, 32, 'secure_uploads/1770289774_+2 Certificate, Transcripit, Provisional and Migration Certificate.pdf', '1770289774_+2 Certificate, Transcripit, Provisional and Migration Certificate.pdf', 'application/pdf', 1797450, '2026-02-05 11:09:34'),
(6, 32, 'secure_uploads/1770289786_Letter of Recommandation.pdf', '1770289786_Letter of Recommandation.pdf', 'application/pdf', 1926776, '2026-02-05 11:09:46'),
(7, 32, 'secure_uploads/1770290604_Samyog Shrestha - CV.pdf', '1770290604_Samyog Shrestha - CV.pdf', 'application/pdf', 413905, '2026-02-05 11:23:24'),
(8, 32, 'secure_uploads/1770291038_Letter of Recommandation.pdf', '1770291038_Letter of Recommandation.pdf', 'application/pdf', 1926776, '2026-02-05 11:30:38'),
(9, 32, 'secure_uploads/1770291084_Samyog Shrestha - New Work Experience.pdf', '1770291084_Samyog Shrestha - New Work Experience.pdf', 'application/pdf', 294724, '2026-02-05 11:31:24'),
(10, 32, 'secure_uploads/1770291103_Samyog Shrestha - Passport.pdf', '1770291103_Samyog Shrestha - Passport.pdf', 'application/pdf', 323313, '2026-02-05 11:31:43'),
(11, 32, 'secure_uploads/1770291470_Nikesh Mahat-Academic.pdf', '1770291470_Nikesh Mahat-Academic.pdf', 'application/pdf', 4221589, '2026-02-05 11:37:50'),
(12, 32, 'secure_uploads/1770291506_Nikesh Mahat-Letter of Recommendation.pdf', '1770291506_Nikesh Mahat-Letter of Recommendation.pdf', 'application/pdf', 1602868, '2026-02-05 11:38:26'),
(13, 32, 'secure_uploads/1770291532_Work Experience for Nikesh Mahat IYC Nepal.pdf', '1770291532_Work Experience for Nikesh Mahat IYC Nepal.pdf', 'application/pdf', 189839, '2026-02-05 11:38:52'),
(14, 32, 'secure_uploads/1770291548_Nikesh Mahat-Passport.pdf', '1770291548_Nikesh Mahat-Passport.pdf', 'application/pdf', 345914, '2026-02-05 11:39:08'),
(15, 32, 'secure_uploads/1770291574_Nikesh Mahat-MOI.pdf', '1770291574_Nikesh Mahat-MOI.pdf', 'application/pdf', 891085, '2026-02-05 11:39:34'),
(16, 32, 'secure_uploads/1770291600_Nikesh Mahat-Photo.JPG', '1770291600_Nikesh Mahat-Photo.JPG', 'image/jpeg', 283414, '2026-02-05 11:40:00'),
(17, 32, 'secure_uploads/1770291615_Nikesh Mahat Europass CV 1.pdf', '1770291615_Nikesh Mahat Europass CV 1.pdf', 'application/pdf', 537209, '2026-02-05 11:40:15'),
(18, 32, 'secure_uploads/1770376432_Paurakh Shah-Academic Certificate.pdf', '1770376432_Paurakh Shah-Academic Certificate.pdf', 'application/pdf', 4325746, '2026-02-06 11:13:52'),
(19, 32, 'secure_uploads/1770376470_Paurakh Shah-Letter of recommendation.pdf', '1770376470_Paurakh Shah-Letter of recommendation.pdf', 'application/pdf', 1790032, '2026-02-06 11:14:30'),
(20, 32, 'secure_uploads/1770376484_Paurakh Shah-Passport.pdf', '1770376484_Paurakh Shah-Passport.pdf', 'application/pdf', 247755, '2026-02-06 11:14:44'),
(21, 32, 'secure_uploads/1770376497_Paurakh Shah-Photo.jpg', '1770376497_Paurakh Shah-Photo.jpg', 'image/jpeg', 93064, '2026-02-06 11:14:57'),
(22, 32, 'secure_uploads/1770376511_Paurakh Shah-MOI.pdf', '1770376511_Paurakh Shah-MOI.pdf', 'application/pdf', 771235, '2026-02-06 11:15:11'),
(23, 32, 'secure_uploads/1770376542_Paurakh Shah - IELTS Certificate .pdf', '1770376542_Paurakh Shah - IELTS Certificate .pdf', 'application/pdf', 1021860, '2026-02-06 11:15:42'),
(24, 32, 'secure_uploads/1770376572_Paurakh Shah- T.U Equivalence.pdf', '1770376572_Paurakh Shah- T.U Equivalence.pdf', 'application/pdf', 591970, '2026-02-06 11:16:12'),
(25, 32, 'secure_uploads/1770378722_Samyog Shrestha - CV.pdf', '1770378722_Samyog Shrestha - CV.pdf', 'application/pdf', 425409, '2026-02-06 11:52:02'),
(26, 32, 'secure_uploads/1770378757_Samyog Shrestha - Letter of Recommendation for YFEED Foundation.pdf', '1770378757_Samyog Shrestha - Letter of Recommendation for YFEED Foundation.pdf', 'application/pdf', 11947178, '2026-02-06 11:52:37'),
(27, 32, 'secure_uploads/1770378771_Samyog Shrestha - New Work Experience.pdf', '1770378771_Samyog Shrestha - New Work Experience.pdf', 'application/pdf', 1523603, '2026-02-06 11:52:51'),
(28, 32, 'secure_uploads/1770547631_Work Experience for Nikesh Mahat IYC Nepal.pdf', '1770547631_Work Experience for Nikesh Mahat IYC Nepal.pdf', 'application/pdf', 189839, '2026-02-08 10:47:11'),
(29, 32, 'secure_uploads/1770547666_Nikesh Mahat - New CV and Work Experience.pdf', '1770547666_Nikesh Mahat - New CV and Work Experience.pdf', 'application/pdf', 541033, '2026-02-08 10:47:46'),
(30, 32, 'secure_uploads/1770548236_Paurakh Shah - CV and Work Experience Letter.pdf', '1770548236_Paurakh Shah - CV and Work Experience Letter.pdf', 'application/pdf', 1014859, '2026-02-08 10:57:16'),
(31, 32, 'secure_uploads/1770548888_Nikesh Mahat Letter of Recommendation (work).pdf', '1770548888_Nikesh Mahat Letter of Recommendation (work).pdf', 'application/pdf', 11808717, '2026-02-08 11:08:08'),
(32, 32, 'secure_uploads/1770623389_Dikshant Singh -Academic Certificate.pdf', '1770623389_Dikshant Singh -Academic Certificate.pdf', 'application/pdf', 4644257, '2026-02-09 07:49:49'),
(33, 32, 'secure_uploads/1770623402_Dikshant Singh -Passport.pdf', '1770623402_Dikshant Singh -Passport.pdf', 'application/pdf', 245981, '2026-02-09 07:50:02'),
(34, 32, 'secure_uploads/1770623423_Dikshant Singh - Letter of Recommendation (Collage).pdf', '1770623423_Dikshant Singh - Letter of Recommendation (Collage).pdf', 'application/pdf', 1035998, '2026-02-09 07:50:23'),
(35, 32, 'secure_uploads/1770623447_Dikshant Singh - Work Exprience Letter NEW LOR .pdf', '1770623447_Dikshant Singh - Work Exprience Letter NEW LOR .pdf', 'application/pdf', 224457, '2026-02-09 07:50:47'),
(36, 32, 'secure_uploads/1770623484_Dikshant-IELTS Cerificate.pdf', '1770623484_Dikshant-IELTS Cerificate.pdf', 'application/pdf', 1022701, '2026-02-09 07:51:24'),
(37, 32, 'secure_uploads/1770623498_Dikshant-Photo.jpg', '1770623498_Dikshant-Photo.jpg', 'image/jpeg', 125218, '2026-02-09 07:51:38'),
(38, 32, 'secure_uploads/1770623509_Dikshant Singh - T.U Equivalence.pdf', '1770623509_Dikshant Singh - T.U Equivalence.pdf', 'application/pdf', 599132, '2026-02-09 07:51:49'),
(39, 32, 'secure_uploads/1770623524_Dikshant Singh -MOI.pdf', '1770623524_Dikshant Singh -MOI.pdf', 'application/pdf', 842969, '2026-02-09 07:52:04'),
(40, 32, 'secure_uploads/1770623607_Paurakh Shah - CV and Work Experience Letter.pdf', '1770623607_Paurakh Shah - CV and Work Experience Letter.pdf', 'application/pdf', 908900, '2026-02-09 07:53:27'),
(41, 32, 'secure_uploads/1770625589_Dikshant Singh - CV and Work Experience Letter.pdf', '1770625589_Dikshant Singh - CV and Work Experience Letter.pdf', 'application/pdf', 1247170, '2026-02-09 08:26:29'),
(42, 32, 'secure_uploads/1770625780_Saman Rokka - Academic Documents.pdf', '1770625780_Saman Rokka - Academic Documents.pdf', 'application/pdf', 3240443, '2026-02-09 08:29:40'),
(43, 32, 'secure_uploads/1770625836_Passport.pdf', '1770625836_Passport.pdf', 'application/pdf', 708539, '2026-02-09 08:30:36'),
(44, 32, 'secure_uploads/1770625855_Saman Rokka - Photo.jpeg', '1770625855_Saman Rokka - Photo.jpeg', 'image/jpeg', 273920, '2026-02-09 08:30:55'),
(45, 32, 'secure_uploads/1770625948_IELTS.pdf', '1770625948_IELTS.pdf', 'application/pdf', 720321, '2026-02-09 08:32:28'),
(46, 32, 'secure_uploads/1770625978_Letter of Recommandation.pdf', '1770625978_Letter of Recommandation.pdf', 'application/pdf', 318157, '2026-02-09 08:32:58'),
(47, 32, 'secure_uploads/1770626561_Academic Documents.pdf', '1770626561_Academic Documents.pdf', 'application/pdf', 3028544, '2026-02-09 08:42:41'),
(48, 32, 'secure_uploads/1770626605_Samyog Shrestha - IELTS Certificate.pdf', '1770626605_Samyog Shrestha - IELTS Certificate.pdf', 'application/pdf', 1017474, '2026-02-09 08:43:25'),
(49, 32, 'secure_uploads/1770626621_Letter of Recommandation (Collage).pdf', '1770626621_Letter of Recommandation (Collage).pdf', 'application/pdf', 1224626, '2026-02-09 08:43:41'),
(50, 32, 'secure_uploads/1770626682_Binayak Pandey - Passport.pdf', '1770626682_Binayak Pandey - Passport.pdf', 'application/pdf', 4240609, '2026-02-09 08:44:42'),
(51, 32, 'secure_uploads/1770626706_Binayak Chhetri Photo.jpg', '1770626706_Binayak Chhetri Photo.jpg', 'image/jpeg', 180214, '2026-02-09 08:45:07'),
(52, 32, 'secure_uploads/1770626728_Medium of Instruction.pdf', '1770626728_Medium of Instruction.pdf', 'application/pdf', 8729331, '2026-02-09 08:45:28'),
(53, 32, 'secure_uploads/1770626745_Binayak Pandey Work Experience Letter.pdf', '1770626745_Binayak Pandey Work Experience Letter.pdf', 'application/pdf', 219880, '2026-02-09 08:45:45'),
(54, 32, 'secure_uploads/1770627053_Binayak  Pandey NOC Certificate .pdf', '1770627053_Binayak  Pandey NOC Certificate .pdf', 'application/pdf', 1001827, '2026-02-09 08:50:53'),
(55, 32, 'secure_uploads/1770627112_All Stamp documents.pdf', '1770627112_All Stamp documents.pdf', 'application/pdf', 4295202, '2026-02-09 08:51:52'),
(56, 32, 'secure_uploads/1770627133_Pan Card.pdf', '1770627133_Pan Card.pdf', 'application/pdf', 217489, '2026-02-09 08:52:13'),
(57, 32, 'secure_uploads/1770627199_CAL-for-MIC offer letter.pdf', '1770627199_CAL-for-MIC offer letter.pdf', 'application/pdf', 754541, '2026-02-09 08:53:19'),
(58, 32, 'secure_uploads/1770627306_Work Experience Agreement paper of Madhushaudan Pandey Singh Tech Engineering Consultant Pvt.pdf', '1770627306_Work Experience Agreement paper of Madhushaudan Pandey Singh Tech Engineering Consultant Pvt.pdf', 'application/pdf', 7551757, '2026-02-09 08:55:06'),
(59, 32, 'secure_uploads/1770627328_Work Experience Agreement paper of Mina Pandey Vastushilpa Architects.pdf', '1770627328_Work Experience Agreement paper of Mina Pandey Vastushilpa Architects.pdf', 'application/pdf', 7172271, '2026-02-09 08:55:28'),
(60, 32, 'secure_uploads/1770809346_Samyog Shrestha -  Letter of Recommendation (WORK).pdf', '1770809346_Samyog Shrestha -  Letter of Recommendation (WORK).pdf', 'application/pdf', 8017449, '2026-02-11 11:29:06'),
(61, 32, 'secure_uploads/1770809400_Samyog Shrestha - Work Experience YCN.pdf', '1770809400_Samyog Shrestha - Work Experience YCN.pdf', 'application/pdf', 12177226, '2026-02-11 11:30:00'),
(62, 32, 'secure_uploads/1770809595_Europass CV.pdf', '1770809595_Europass CV.pdf', 'application/pdf', 383880, '2026-02-11 11:33:15'),
(63, 32, 'secure_uploads/1770882327_Samyog Shrestha - New  Work Experience YCN.pdf', '1770882327_Samyog Shrestha - New  Work Experience YCN.pdf', 'application/pdf', 12177226, '2026-02-12 07:45:27'),
(64, 32, 'secure_uploads/1770882345_Samyog Shrestha - CV.pdf', '1770882345_Samyog Shrestha - CV.pdf', 'application/pdf', 425479, '2026-02-12 07:45:45'),
(65, 32, 'secure_uploads/1770882372_Samyog Shrestha -  New Letter of Recommendation (WORK).pdf', '1770882372_Samyog Shrestha -  New Letter of Recommendation (WORK).pdf', 'application/pdf', 8017449, '2026-02-12 07:46:12'),
(66, 32, 'secure_uploads/1770882428_Old Work Experience Letter Recommendation Letter V guys.pdf', '1770882428_Old Work Experience Letter Recommendation Letter V guys.pdf', 'application/pdf', 156735, '2026-02-12 07:47:08'),
(67, 32, 'secure_uploads/1770882507_Samyog Shrestha - CV.pdf', '1770882507_Samyog Shrestha - CV.pdf', 'application/pdf', 425479, '2026-02-12 07:48:27'),
(68, 32, 'secure_uploads/1770882525_Old Work Experience Letter Recommendation Letter V guys.pdf', '1770882525_Old Work Experience Letter Recommendation Letter V guys.pdf', 'application/pdf', 156735, '2026-02-12 07:48:45'),
(69, 32, 'secure_uploads/1770882612_Europass CV.pdf', '1770882612_Europass CV.pdf', 'application/pdf', 372414, '2026-02-12 07:50:12'),
(70, 32, 'secure_uploads/1770882630_Work Exprience Saman Rokka Alka Pharmacy.pdf', '1770882630_Work Exprience Saman Rokka Alka Pharmacy.pdf', 'application/pdf', 12351073, '2026-02-12 07:50:30'),
(71, 32, 'secure_uploads/1770891996_Nikesh Mahat - New CV and Work Experience.pdf', '1770891996_Nikesh Mahat - New CV and Work Experience.pdf', 'application/pdf', 408781, '2026-02-12 10:26:36'),
(72, 32, 'secure_uploads/1770892718_Hem Raj Bhandari -Academic.pdf', '1770892718_Hem Raj Bhandari -Academic.pdf', 'application/pdf', 18065479, '2026-02-12 10:38:38'),
(73, 32, 'secure_uploads/1770892733_Hem Raj Bhandari-Passport.pdf', '1770892733_Hem Raj Bhandari-Passport.pdf', 'application/pdf', 1083187, '2026-02-12 10:38:53'),
(74, 32, 'secure_uploads/1770892943_Hemraj Bhandari-Photo.pdf', '1770892943_Hemraj Bhandari-Photo.pdf', 'application/pdf', 327079, '2026-02-12 10:42:23'),
(75, 32, 'secure_uploads/1770892973_Hemraj Bhndari - Letter of Recommandation.pdf', '1770892973_Hemraj Bhndari - Letter of Recommandation.pdf', 'application/pdf', 1672152, '2026-02-12 10:42:53'),
(76, 32, 'secure_uploads/1770893028_Hem Raj Bhandari- Meduim of Instructaion.pdf', '1770893028_Hem Raj Bhandari- Meduim of Instructaion.pdf', 'application/pdf', 792713, '2026-02-12 10:43:48'),
(77, 32, 'secure_uploads/1770893042_Hem Raj Bhandari -Ielts.pdf', '1770893042_Hem Raj Bhandari -Ielts.pdf', 'application/pdf', 2781950, '2026-02-12 10:44:02');

-- --------------------------------------------------------

--
-- Table structure for table `automation_logs`
--

CREATE TABLE `automation_logs` (
  `id` int(11) NOT NULL,
  `workflow_id` int(11) DEFAULT NULL,
  `workflow_name` varchar(200) DEFAULT NULL,
  `trigger_event` varchar(100) NOT NULL,
  `recipient_id` int(11) DEFAULT NULL,
  `recipient_name` varchar(200) DEFAULT NULL,
  `recipient_contact` varchar(255) DEFAULT NULL,
  `channel` varchar(20) NOT NULL,
  `template_key` varchar(100) DEFAULT NULL,
  `status` enum('queued','sent','failed','skipped') NOT NULL DEFAULT 'queued',
  `error_message` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `automation_queue`
--

CREATE TABLE `automation_queue` (
  `id` int(11) NOT NULL,
  `workflow_id` int(11) NOT NULL,
  `recipient_email` varchar(255) DEFAULT NULL,
  `recipient_phone` varchar(50) DEFAULT NULL,
  `serialized_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`serialized_data`)),
  `scheduled_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('pending','processing','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `automation_queue`
--

INSERT INTO `automation_queue` (`id`, `workflow_id`, `recipient_email`, `recipient_phone`, `serialized_data`, `scheduled_at`, `status`, `error_message`, `created_at`, `updated_at`) VALUES
(9, 8, '', '', '{\"created_at\":\"2026-01-07 09:39:24\",\"due_date\":\"2026-01-12 09:39:24\"}', '2026-01-07 03:54:24', 'pending', NULL, '2026-01-07 08:39:24', '2026-01-07 08:39:24'),
(10, 26, '', '', '{\"created_at\":\"2026-01-07 09:39:24\",\"due_date\":\"2026-01-12 09:39:24\"}', '2026-01-11 03:54:24', 'pending', NULL, '2026-01-07 08:39:24', '2026-01-07 08:39:24'),
(13, 8, '', '', '{\"created_at\":\"2026-01-07 09:39:24\",\"due_date\":\"2026-01-12 09:39:24\"}', '2026-01-07 03:54:24', 'pending', NULL, '2026-01-07 08:39:24', '2026-01-07 08:39:24'),
(14, 26, '', '', '{\"created_at\":\"2026-01-07 09:39:24\",\"due_date\":\"2026-01-12 09:39:24\"}', '2026-01-11 03:54:24', 'pending', NULL, '2026-01-07 08:39:24', '2026-01-07 08:39:24'),
(15, 27, '', '', '{\"created_at\":\"2026-01-07 09:39:24\",\"due_date\":\"2026-01-12 09:39:24\"}', '2026-01-07 05:54:24', 'pending', NULL, '2026-01-07 08:39:24', '2026-01-07 08:39:24');

-- --------------------------------------------------------

--
-- Table structure for table `automation_templates`
--

CREATE TABLE `automation_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `template_key` varchar(100) NOT NULL,
  `channel` enum('email','sms','whatsapp','viber') NOT NULL DEFAULT 'email',
  `subject` varchar(500) DEFAULT NULL COMMENT 'For email only',
  `body_html` longtext DEFAULT NULL COMMENT 'HTML body for email',
  `body_text` text DEFAULT NULL COMMENT 'Plain text body for SMS/fallback',
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Available placeholders' CHECK (json_valid(`variables`)),
  `is_system` tinyint(1) DEFAULT 0 COMMENT 'System templates cannot be deleted',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `automation_templates`
--

INSERT INTO `automation_templates` (`id`, `name`, `template_key`, `channel`, `subject`, `body_html`, `body_text`, `variables`, `is_system`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Welcome Email', 'welcome', 'email', 'Welcome to EduCRM - Your Account Has Been Created', '\r\n            <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;\'>\r\n                <h2 style=\'color: #0f766e;\'>🎉 Welcome to EduCRM!</h2>\r\n                <p>Hi {name},</p>\r\n                <p>Your account has been successfully created. Below are your login credentials:</p>\r\n                <div style=\'background: #f0fdfa; padding: 20px; border-left: 4px solid #0f766e; margin: 20px 0;\'>\r\n                    <p style=\'margin: 5px 0;\'><strong>Email:</strong> {email}</p>\r\n                    <p style=\'margin: 5px 0;\'><strong>Temporary Password:</strong> <code style=\'background: #e2e8f0; padding: 2px 8px; border-radius: 4px;\'>{password}</code></p>\r\n                </div>\r\n                <p style=\'color: #dc2626; font-weight: bold;\'>⚠️ For security, please change your password after your first login.</p>\r\n                <p><a href=\'{login_url}\' style=\'background: #0f766e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;\'>Login to Your Account</a></p>\r\n                <p style=\'color: #64748b; font-size: 12px; margin-top: 30px;\'>If you did not request this account, please ignore this email.</p>\r\n            </div>\r\n        ', 'Welcome to EduCRM!\n\nHi {name},\n\nYour account has been created.\nEmail: {email}\nPassword: {password}\n\nPlease login at: {login_url}', '[\"name\",\"email\",\"password\",\"login_url\"]', 1, 1, NULL, '2026-01-06 11:26:25', '2026-01-06 11:26:25'),
(2, 'Profile Updated', 'profile_update', 'email', 'Your Profile Has Been Updated', '\r\n            <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;\'>\r\n                <h2 style=\'color: #0f766e;\'>📝 Profile Updated</h2>\r\n                <p>Hi {name},</p>\r\n                <p>Your profile has been updated with the following changes:</p>\r\n                <div style=\'background: #f8fafc; padding: 15px; border-left: 4px solid #0f766e; margin: 20px 0;\'>\r\n                    {changes}\r\n                </div>\r\n                <p>If you did not make these changes, please contact support immediately.</p>\r\n                <p><a href=\'{profile_url}\' style=\'background: #0f766e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;\'>View Your Profile</a></p>\r\n                <p style=\'color: #64748b; font-size: 12px; margin-top: 30px;\'>This is an automated notification from EduCRM.</p>\r\n            </div>\r\n        ', 'Profile Updated\n\nHi {name},\n\nYour profile has been updated:\n{changes}\n\nView profile: {profile_url}', '[\"name\",\"changes\",\"profile_url\"]', 1, 1, NULL, '2026-01-06 11:26:25', '2026-01-06 11:26:25'),
(3, 'Visa Workflow Update', 'workflow_update', 'email', 'Visa Application Update - {new_stage}', '\r\n            <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;\'>\r\n                <h2 style=\'color: #0f766e;\'>🔄 Visa Workflow Update</h2>\r\n                <p>Hi {name},</p>\r\n                <p>Your visa application status has been updated:</p>\r\n                <div style=\'background: #f0fdfa; padding: 15px; border-left: 4px solid #0f766e; margin: 20px 0;\'>\r\n                    <p style=\'margin: 5px 0;\'><strong>Application:</strong> {application_title}</p>\r\n                    <p style=\'margin: 5px 0;\'><strong>Previous Stage:</strong> <span style=\'color: #64748b;\'>{old_stage}</span></p>\r\n                    <p style=\'margin: 5px 0;\'><strong>New Stage:</strong> <span style=\'color: #0f766e; font-weight: bold;\'>{new_stage}</span></p>\r\n                    <p style=\'margin: 5px 0;\'><strong>Updated:</strong> {updated_at}</p>\r\n                </div>\r\n                <p><a href=\'{workflow_url}\' style=\'background: #0f766e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;\'>View Application</a></p>\r\n                <p style=\'color: #64748b; font-size: 12px; margin-top: 30px;\'>This is an automated notification from EduCRM.</p>\r\n            </div>\r\n        ', 'Visa Workflow Update\n\nHi {name},\n\nYour visa application status:\nPrevious: {old_stage}\nNew: {new_stage}\nUpdated: {updated_at}\n\nView: {workflow_url}', '[\"name\",\"application_title\",\"old_stage\",\"new_stage\",\"updated_at\",\"workflow_url\"]', 1, 1, NULL, '2026-01-06 11:26:25', '2026-01-06 11:26:25'),
(4, 'Document Status Update', 'document_update', 'email', 'Document Status Update: {document_name}', '\r\n            <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;\'>\r\n                <h2 style=\'color: #0f766e;\'>📄 Document Status Update</h2>\r\n                <p>Hi {name},</p>\r\n                <p>A document associated with your profile has been updated:</p>\r\n                <div style=\'background: #f8fafc; padding: 15px; border-left: 4px solid #0f766e; margin: 20px 0;\'>\r\n                    <p style=\'margin: 5px 0;\'><strong>Document:</strong> {document_name}</p>\r\n                    <p style=\'margin: 5px 0;\'><strong>Status:</strong> <span style=\'color: {status_color}; font-weight: bold;\'>{status}</span></p>\r\n                    {remarks}\r\n                </div>\r\n                <p><a href=\'{documents_url}\' style=\'background: #0f766e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;\'>View Documents</a></p>\r\n                <p style=\'color: #64748b; font-size: 12px; margin-top: 30px;\'>This is an automated notification from EduCRM.</p>\r\n            </div>\r\n        ', 'Document Status Update\n\nHi {name},\n\nDocument: {document_name}\nStatus: {status}\n{remarks}\n\nView: {documents_url}', '[\"name\",\"document_name\",\"status\",\"status_color\",\"remarks\",\"documents_url\"]', 1, 1, NULL, '2026-01-06 11:26:25', '2026-01-06 11:26:25'),
(5, 'Class Enrollment Confirmation', 'enrollment', 'email', 'Class Enrollment Confirmation: {course_name}', '\r\n            <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;\'>\r\n                <h2 style=\'color: #0f766e;\'>📚 Course Enrollment Confirmation</h2>\r\n                <p>Hi {name},</p>\r\n                <p>Congratulations! You have been enrolled in a new course:</p>\r\n                <div style=\'background: #f0fdfa; padding: 15px; border-left: 4px solid #0f766e; margin: 20px 0;\'>\r\n                    <h3 style=\'margin: 0 0 10px 0; color: #0f766e;\'>{course_name}</h3>\r\n                    <p style=\'margin: 5px 0;\'><strong>Start Date:</strong> {start_date}</p>\r\n                    <p style=\'margin: 5px 0;\'><strong>Instructor:</strong> {instructor}</p>\r\n                    <p style=\'margin: 5px 0;\'><strong>Schedule:</strong> {schedule}</p>\r\n                </div>\r\n                <p><a href=\'{course_url}\' style=\'background: #0f766e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;\'>View Course Details</a></p>\r\n                <p style=\'color: #64748b; font-size: 12px; margin-top: 30px;\'>Welcome aboard! This is an automated notification from EduCRM.</p>\r\n            </div>\r\n        ', 'Course Enrollment Confirmation\n\nHi {name},\n\nYou have been enrolled in: {course_name}\nStart Date: {start_date}\nInstructor: {instructor}\nSchedule: {schedule}\n\nView: {course_url}', '[\"name\",\"course_name\",\"start_date\",\"instructor\",\"schedule\",\"course_url\"]', 1, 1, NULL, '2026-01-06 11:26:25', '2026-01-06 11:26:25'),
(6, 'Task Assignment', 'task_assignment', 'email', 'New Task Assigned: {task_title}', '\r\n            <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;\'>\r\n                <h2 style=\'color: #4f46e5;\'>New Task Assigned</h2>\r\n                <p>Hi {name},</p>\r\n                <p>A new task has been assigned to you:</p>\r\n                <div style=\'background: #f8fafc; padding: 15px; border-left: 4px solid #4f46e5; margin: 20px 0;\'>\r\n                    <h3 style=\'margin: 0 0 10px 0;\'>{task_title}</h3>\r\n                    <p style=\'margin: 5px 0;\'><strong>Description:</strong> {task_description}</p>\r\n                    <p style=\'margin: 5px 0;\'><strong>Priority:</strong> <span style=\'text-transform: uppercase;\'>{priority}</span></p>\r\n                    <p style=\'margin: 5px 0;\'><strong>Due Date:</strong> {due_date}</p>\r\n                </div>\r\n                <p><a href=\'{task_url}\' style=\'background: #4f46e5; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;\'>View Task</a></p>\r\n                <p style=\'color: #64748b; font-size: 12px; margin-top: 30px;\'>This is an automated notification from EduCRM.</p>\r\n            </div>\r\n        ', 'New Task Assigned\n\nHi {name},\n\nTask: {task_title}\nDescription: {task_description}\nPriority: {priority}\nDue: {due_date}\n\nView: {task_url}', '[\"name\",\"task_title\",\"task_description\",\"priority\",\"due_date\",\"task_url\"]', 1, 1, NULL, '2026-01-06 11:26:25', '2026-01-06 11:26:25'),
(7, 'Appointment Reminder', 'appointment_reminder', 'email', 'Appointment Reminder: {appointment_title}', '\r\n            <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;\'>\r\n                <h2 style=\'color: #4f46e5;\'>Appointment Reminder</h2>\r\n                <p>Hi {name},</p>\r\n                <p>This is a reminder for your upcoming appointment:</p>\r\n                <div style=\'background: #f8fafc; padding: 15px; border-left: 4px solid #3b82f6; margin: 20px 0;\'>\r\n                    <h3 style=\'margin: 0 0 10px 0;\'>{appointment_title}</h3>\r\n                    <p style=\'margin: 5px 0;\'><strong>Client:</strong> {client_name}</p>\r\n                    <p style=\'margin: 5px 0;\'><strong>Date & Time:</strong> {appointment_date}</p>\r\n                    <p style=\'margin: 5px 0;\'><strong>Location:</strong> {location}</p>\r\n                </div>\r\n                <p><a href=\'{appointment_url}\' style=\'background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;\'>View Appointment</a></p>\r\n                <p style=\'color: #64748b; font-size: 12px; margin-top: 30px;\'>This is an automated reminder from EduCRM.</p>\r\n            </div>\r\n        ', 'Appointment Reminder\n\nHi {name},\n\nAppointment: {appointment_title}\nClient: {client_name}\nDate: {appointment_date}\nLocation: {location}\n\nView: {appointment_url}', '[\"name\",\"appointment_title\",\"client_name\",\"appointment_date\",\"location\",\"meeting_link\",\"appointment_url\"]', 1, 1, NULL, '2026-01-06 11:26:25', '2026-01-06 11:26:25'),
(8, 'Task Overdue Alert', 'task_overdue', 'email', 'Overdue Task Alert: {task_title}', '\r\n            <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;\'>\r\n                <h2 style=\'color: #ef4444;\'>⚠️ Overdue Task Alert</h2>\r\n                <p>Hi {name},</p>\r\n                <p>The following task is now <strong>{days_overdue} day(s) overdue</strong>:</p>\r\n                <div style=\'background: #fef2f2; padding: 15px; border-left: 4px solid #ef4444; margin: 20px 0;\'>\r\n                    <h3 style=\'margin: 0 0 10px 0;\'>{task_title}</h3>\r\n                    <p style=\'margin: 5px 0;\'><strong>Priority:</strong> <span style=\'text-transform: uppercase;\'>{priority}</span></p>\r\n                    <p style=\'margin: 5px 0;\'><strong>Was Due:</strong> {due_date}</p>\r\n                </div>\r\n                <p><a href=\'{task_url}\' style=\'background: #ef4444; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;\'>Complete Task Now</a></p>\r\n                <p style=\'color: #64748b; font-size: 12px; margin-top: 30px;\'>This is an automated alert from EduCRM.</p>\r\n            </div>\r\n        ', 'Overdue Task Alert\n\nHi {name},\n\nTask: {task_title} is {days_overdue} day(s) overdue!\nPriority: {priority}\nWas Due: {due_date}\n\nComplete now: {task_url}', '[\"name\",\"task_title\",\"days_overdue\",\"due_date\",\"priority\",\"task_url\"]', 1, 1, NULL, '2026-01-06 11:26:25', '2026-01-06 11:26:25'),
(9, 'Welcome SMS', 'sms_welcome', 'sms', NULL, NULL, 'Welcome to EduCRM, {name}! Your account is ready. Login: {login_url}', '[\"name\",\"login_url\"]', 1, 1, NULL, '2026-01-06 11:26:25', '2026-01-06 11:26:25'),
(10, 'Appointment Reminder SMS', 'sms_appointment_reminder', 'sms', NULL, NULL, 'Reminder: You have an appointment tomorrow at {appointment_date}. Location: {location}', '[\"appointment_date\",\"location\"]', 1, 1, NULL, '2026-01-06 11:26:25', '2026-01-06 11:26:25'),
(11, 'Visa Update SMS', 'sms_workflow_update', 'sms', NULL, NULL, 'Hi {name}, your visa application status updated to: {new_stage}. Check EduCRM for details.', '[\"name\",\"new_stage\"]', 1, 1, NULL, '2026-01-06 11:26:25', '2026-01-06 11:26:25'),
(12, 'Welcome WhatsApp', 'whatsapp_welcome', 'whatsapp', NULL, NULL, '🎉 *Welcome to EduCRM!*\n\nHi {name},\n\nYour account has been created successfully.\n\n📧 Email: {email}\n🔑 Password: {password}\n\nLogin here: {login_url}', '[\"name\",\"email\",\"password\",\"login_url\"]', 1, 1, NULL, '2026-01-06 11:26:25', '2026-01-06 11:26:25'),
(13, 'Enrollment WhatsApp', 'whatsapp_enrollment', 'whatsapp', NULL, NULL, '📚 *Course Enrollment Confirmed!*\n\nHi {name},\n\nYou\'ve been enrolled in:\n*{course_name}*\n\n📅 Start: {start_date}\n👨‍🏫 Instructor: {instructor}\n⏰ Schedule: {schedule}', '[\"name\",\"course_name\",\"start_date\",\"instructor\",\"schedule\"]', 1, 1, NULL, '2026-01-06 11:26:25', '2026-01-06 11:26:25'),
(14, 'Test Email Template', 'test_email', 'email', 'Subject', NULL, NULL, NULL, 0, 1, 1, '2026-01-07 07:56:38', '2026-01-07 07:56:38'),
(15, 'Test SMS Template', 'test_sms', 'sms', NULL, NULL, NULL, NULL, 0, 1, 1, '2026-01-07 07:56:38', '2026-01-07 07:56:38'),
(16, 'Test Viber Template', 'test_viber', 'viber', NULL, NULL, NULL, NULL, 0, 1, 1, '2026-01-07 07:56:38', '2026-01-07 07:56:38'),
(23, 'Test Schedule Tpl', 'test_schedule_tpl', 'email', 'Test Subject', NULL, NULL, NULL, 0, 1, 1, '2026-01-07 08:39:24', '2026-01-07 08:39:24');

-- --------------------------------------------------------

--
-- Table structure for table `automation_workflows`
--

CREATE TABLE `automation_workflows` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `trigger_event` enum('user_created','student_created','inquiry_created','workflow_stage_changed','document_status_changed','enrollment_created','task_assigned','task_overdue','appointment_reminder','payment_received','profile_updated') NOT NULL,
  `channel` enum('email','sms','whatsapp','viber') NOT NULL DEFAULT 'email',
  `template_id` int(11) NOT NULL,
  `gateway_id` int(11) DEFAULT NULL COMMENT 'For messaging only',
  `delay_minutes` int(11) DEFAULT 0 COMMENT 'Delay before sending',
  `conditions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Optional filters' CHECK (json_valid(`conditions`)),
  `is_active` tinyint(1) DEFAULT 1,
  `priority` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `schedule_type` enum('immediate','delay','distinct_time') NOT NULL DEFAULT 'immediate',
  `schedule_offset` int(11) DEFAULT NULL,
  `schedule_unit` enum('minutes','hours','days') DEFAULT NULL,
  `schedule_reference` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `automation_workflows`
--

INSERT INTO `automation_workflows` (`id`, `name`, `description`, `trigger_event`, `channel`, `template_id`, `gateway_id`, `delay_minutes`, `conditions`, `is_active`, `priority`, `created_by`, `created_at`, `updated_at`, `schedule_type`, `schedule_offset`, `schedule_unit`, `schedule_reference`) VALUES
(2, 'Welcome Email for New Students', NULL, 'student_created', 'email', 1, NULL, 0, '[]', 1, 0, NULL, '2026-01-06 11:26:25', '2026-01-06 11:26:25', 'immediate', NULL, NULL, NULL),
(3, 'Visa Stage Change Notification', NULL, 'workflow_stage_changed', 'email', 3, NULL, 0, '[]', 1, 0, NULL, '2026-01-06 11:26:25', '2026-01-06 11:26:25', 'immediate', NULL, NULL, NULL),
(4, 'Class Enrollment Notification', NULL, 'enrollment_created', 'email', 5, NULL, 0, '[]', 1, 0, NULL, '2026-01-06 11:26:25', '2026-01-06 11:26:25', 'immediate', NULL, NULL, NULL),
(5, 'Task Assignment Notification', NULL, 'task_assigned', 'email', 6, NULL, 0, '[]', 1, 0, NULL, '2026-01-06 11:26:25', '2026-01-06 11:26:25', 'immediate', NULL, NULL, NULL),
(6, 'Overdue Task Alert', NULL, 'task_overdue', 'email', 8, NULL, 0, '[]', 1, 0, NULL, '2026-01-06 11:26:25', '2026-01-07 08:25:40', 'immediate', NULL, NULL, NULL),
(8, 'Test Save', NULL, 'user_created', 'email', 14, NULL, 0, '[]', 1, 0, NULL, '2026-01-07 08:21:15', '2026-01-07 08:21:15', 'immediate', 1, 'minutes', 'created_at'),
(9, 'test', NULL, 'task_overdue', 'viber', 16, 6, 0, '[]', 1, 0, NULL, '2026-01-07 08:22:57', '2026-01-07 08:22:57', 'immediate', 1, 'minutes', 'created_at'),
(26, 'Test Schedule Logic', NULL, 'user_created', 'email', 23, NULL, 0, NULL, 1, 0, 1, '2026-01-07 08:39:24', '2026-01-07 08:39:24', 'distinct_time', -1, 'days', 'due_date'),
(27, 'Test Schedule Logic', NULL, 'user_created', 'email', 23, NULL, 0, NULL, 1, 0, 1, '2026-01-07 08:39:24', '2026-01-07 08:39:24', 'distinct_time', 2, 'hours', 'created_at');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `is_headquarters` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `name`, `code`, `address`, `city`, `phone`, `email`, `manager_id`, `is_headquarters`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Headquarters', 'HQ', NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-01-02 08:20:33', '2026-01-02 08:20:33'),
(2, 'Kathmandu HQ', 'KTM', '', 'Kathmandu', '01-4123456', '', NULL, 0, 1, '2026-01-02 08:28:12', '2026-01-02 08:31:56'),
(3, 'Pokhara Branch', 'PKR', '', 'Pokhara', '061-123456', '', NULL, 0, 1, '2026-01-02 08:29:44', '2026-01-02 08:31:25'),
(4, 'System Test Branch', 'STB', 'Test Address', 'Test City', '1234567890', 'branch@test.com', NULL, 0, 0, '2026-01-06 06:18:17', '2026-01-06 06:18:17'),
(9, 'System Test Branch', '', '', '', '', '', NULL, 0, 0, '2026-01-06 06:25:33', '2026-01-06 06:25:33'),
(10, 'Test Branch', 'TB-01', '', 'Kathmandu', '01-123456', 'testbranch@test.com', NULL, 0, 0, '2026-01-22 05:49:47', '2026-01-22 05:49:47'),
(11, 'Test Branch 2', 'TB-02', '', '', '', '', NULL, 0, 0, '2026-01-22 05:51:16', '2026-01-22 05:51:16'),
(13, 'Bagbazar', 'B', 'Bagbazar 28', 'Kathmandu', '', 'info@mul.edu.np', 40, 0, 1, '2026-02-03 08:30:13', '2026-02-11 06:45:53');

-- --------------------------------------------------------

--
-- Table structure for table `bulk_action_logs`
--

CREATE TABLE `bulk_action_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` enum('email','sms','status_update','assignment','export') NOT NULL,
  `entity_type` enum('inquiry','student','task','appointment') NOT NULL,
  `entity_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`entity_ids`)),
  `action_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`action_data`)),
  `total_items` int(11) NOT NULL,
  `successful_items` int(11) DEFAULT 0,
  `failed_items` int(11) DEFAULT 0,
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `error_log` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `calendar_sync_events`
--

CREATE TABLE `calendar_sync_events` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `provider_event_id` varchar(255) DEFAULT NULL,
  `last_synced_at` datetime DEFAULT NULL,
  `sync_status` enum('synced','pending','failed') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `schedule_info` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `branch_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `course_id`, `teacher_id`, `name`, `schedule_info`, `start_date`, `end_date`, `status`, `deleted_at`, `updated_at`, `branch_id`) VALUES
(1, 1, 2, 'IELTS 11 AM', NULL, '2025-12-01', NULL, 'active', NULL, NULL, NULL),
(2, 2, 5, 'PTE Morning Batch', NULL, '2024-01-01', NULL, 'active', NULL, NULL, NULL),
(3, 1, 5, 'IELTS Evening Batch', NULL, '2024-01-01', NULL, 'active', NULL, NULL, NULL),
(4, 3, 17, 'PTE Batch Jan 2026', 'Mon-Fri 10am', '2026-01-05', NULL, 'active', NULL, NULL, NULL),
(5, 3, 17, 'PTE Batch Jan 2026', 'Mon-Fri 10am', '2026-01-05', NULL, 'active', NULL, NULL, NULL),
(6, 1, 24, 'IELTS System Test Class', NULL, '2026-01-01', NULL, 'active', NULL, NULL, NULL),
(7, 1, 1, 'Test Batch 101', NULL, '2026-02-01', NULL, 'active', NULL, NULL, NULL),
(8, 4, 1, 'Validation Batch 001', NULL, '0000-00-00', NULL, 'active', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `class_materials`
--

CREATE TABLE `class_materials` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `type` enum('assignment','reading','notice','class_task','home_task') DEFAULT 'notice',
  `due_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class_materials`
--

INSERT INTO `class_materials` (`id`, `class_id`, `teacher_id`, `title`, `description`, `file_path`, `type`, `due_date`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'Matching Information and Table completion', 'home work', '', 'home_task', '2025-12-25 00:00:00', '2025-12-25 08:32:24', NULL),
(2, 4, 17, 'Mock Test 1', 'Solve section A', NULL, 'assignment', '2026-01-12 16:58:42', '2026-01-05 11:13:42', NULL),
(3, 5, 17, 'Mock Test 1', 'Solve section A', NULL, 'assignment', '2026-01-12 17:00:33', '2026-01-05 11:15:33', NULL),
(4, 6, 1, 'IELTS Speaking Assignment', 'Marking test.', '', 'notice', NULL, '2026-01-06 06:42:46', NULL),
(5, 8, 1, 'Welcome', 'Hello World', '', 'notice', NULL, '2026-01-22 06:17:28', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `communication_credits`
--

CREATE TABLE `communication_credits` (
  `id` int(11) NOT NULL,
  `credit_type` enum('sms','email','whatsapp','viber') NOT NULL,
  `type_id` int(11) DEFAULT NULL,
  `credits_available` int(11) DEFAULT 0,
  `credits_used` int(11) DEFAULT 0,
  `last_recharged_at` datetime DEFAULT NULL,
  `low_credit_threshold` int(11) DEFAULT 100,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `communication_credits`
--

INSERT INTO `communication_credits` (`id`, `credit_type`, `type_id`, `credits_available`, `credits_used`, `last_recharged_at`, `low_credit_threshold`, `created_at`, `updated_at`) VALUES
(1, 'sms', 1, 1000, 0, NULL, 100, '2026-01-01 11:26:15', '2026-01-04 11:42:25'),
(2, 'email', 2, 5000, 0, NULL, 500, '2026-01-01 11:26:15', '2026-01-04 11:42:25'),
(3, 'whatsapp', 3, 2000, 0, NULL, 200, '2026-01-01 11:26:15', '2026-01-04 11:42:25'),
(4, 'viber', 4, 1000, 0, NULL, 100, '2026-01-01 11:26:15', '2026-01-04 11:42:25');

-- --------------------------------------------------------

--
-- Table structure for table `communication_types`
--

CREATE TABLE `communication_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL COMMENT 'sms, email, whatsapp, viber, call',
  `is_messaging` tinyint(1) DEFAULT 1 COMMENT 'True for automated messaging channels',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `communication_types`
--

INSERT INTO `communication_types` (`id`, `name`, `is_messaging`, `created_at`) VALUES
(1, 'sms', 1, '2026-01-04 11:42:24'),
(2, 'email', 1, '2026-01-04 11:42:24'),
(3, 'whatsapp', 1, '2026-01-04 11:42:24'),
(4, 'viber', 1, '2026-01-04 11:42:24'),
(5, 'call', 0, '2026-01-04 11:42:24'),
(6, 'meeting', 0, '2026-01-04 11:42:24'),
(7, 'note', 0, '2026-01-04 11:42:24');

-- --------------------------------------------------------

--
-- Table structure for table `communication_usage_logs`
--

CREATE TABLE `communication_usage_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `recipient` varchar(255) NOT NULL,
  `type` enum('sms','email','whatsapp','viber') NOT NULL,
  `type_id` int(11) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('sent','failed','pending') DEFAULT 'pending',
  `credits_consumed` int(11) DEFAULT 1,
  `sent_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `countries`
--

CREATE TABLE `countries` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(3) NOT NULL COMMENT 'ISO 3166-1 alpha-3 code',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `countries`
--

INSERT INTO `countries` (`id`, `name`, `code`, `is_active`, `created_at`) VALUES
(1, 'Australia', 'AUS', 1, '2026-01-04 11:42:24'),
(2, 'United States', 'USA', 1, '2026-01-04 11:42:24'),
(3, 'United Kingdom', 'GBR', 1, '2026-01-04 11:42:24'),
(4, 'Canada', 'CAN', 1, '2026-01-04 11:42:24'),
(5, 'New Zealand', 'NZL', 1, '2026-01-04 11:42:24'),
(6, 'Germany', 'DEU', 1, '2026-01-04 11:42:24'),
(7, 'Japan', 'JPN', 1, '2026-01-04 11:42:24'),
(8, 'Nepal', 'NPL', 1, '2026-01-04 11:42:24'),
(9, 'India', 'IND', 1, '2026-01-04 11:42:24'),
(10, 'China', 'CHN', 1, '2026-01-04 11:42:24'),
(11, 'South Korea', 'KOR', 1, '2026-01-04 11:42:24'),
(12, 'Singapore', 'SGP', 1, '2026-01-04 11:42:24'),
(13, 'Malaysia', 'MYS', 1, '2026-01-04 11:42:24');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'IELTS', 'IELTS', '2025-12-25 08:25:26', NULL),
(2, 'PTE', 'Pearson Test of English', '2026-01-02 08:33:36', NULL),
(3, 'PTE Preparation', 'English Proficiency', '2026-01-05 11:13:42', NULL),
(4, 'Browser Test Course', 'Testing LMS', '2026-01-22 06:13:06', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `daily_performance`
--

CREATE TABLE `daily_performance` (
  `id` int(11) NOT NULL,
  `roster_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `attendance` enum('present','absent','late') DEFAULT 'present',
  `class_task_mark` decimal(5,2) DEFAULT 0.00,
  `home_task_mark` decimal(5,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `daily_performance`
--

INSERT INTO `daily_performance` (`id`, `roster_id`, `student_id`, `attendance`, `class_task_mark`, `home_task_mark`, `remarks`) VALUES
(1, 1, 3, 'present', 75.00, 50.00, 'Matching Information'),
(2, 1, 4, 'absent', 0.00, 0.00, ''),
(5, 2, 6, 'present', 8.00, 7.00, ''),
(8, 3, 8, 'present', 9.00, 8.00, ''),
(9, 3, 9, 'present', 0.00, 0.00, ''),
(11, 4, 16, 'present', 0.00, 0.00, 'Automated Test'),
(12, 5, 20, 'present', 50.00, 0.00, 'Automated Test'),
(14, 5, 25, 'absent', 5.00, 0.00, '');

-- --------------------------------------------------------

--
-- Table structure for table `daily_rosters`
--

CREATE TABLE `daily_rosters` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `roster_date` date NOT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `daily_rosters`
--

INSERT INTO `daily_rosters` (`id`, `class_id`, `teacher_id`, `roster_date`, `topic`, `created_at`, `updated_at`) VALUES
(1, 1, 2, '2025-12-25', 'Daily Session', '2025-12-25 08:29:13', NULL),
(2, 2, 1, '2026-01-02', '', '2026-01-02 08:39:09', NULL),
(3, 3, 1, '2026-01-02', 'IELTS Writing Practice', '2026-01-02 08:39:54', NULL),
(4, 4, 17, '2026-01-05', 'Unit Testing Topic', '2026-01-05 11:13:42', NULL),
(5, 5, 17, '2026-01-05', 'Unit Testing Topic', '2026-01-05 11:15:33', NULL),
(6, 6, 1, '2026-01-06', 'IELTS Speaking Practice', '2026-01-06 06:35:47', NULL),
(7, 8, 1, '2026-01-22', 'Intro', '2026-01-22 06:18:24', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(20) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `expiry_alert_sent` tinyint(1) DEFAULT 0,
  `expiry_alert_days` int(11) DEFAULT 30,
  `download_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_expiry_alerts`
--

CREATE TABLE `document_expiry_alerts` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `alert_type` enum('30_days','14_days','7_days','expired') NOT NULL,
  `sent_at` datetime NOT NULL,
  `sent_to` int(11) NOT NULL,
  `channel` enum('email','notification','sms') DEFAULT 'notification'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_types`
--

CREATE TABLE `document_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_required_default` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_types`
--

INSERT INTO `document_types` (`id`, `name`, `code`, `description`, `is_required_default`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Valid Passport', 'passport', 'Current passport with at least 6 months validity', 1, 1, 1, '2026-01-05 08:31:25', '2026-01-05 08:31:25'),
(2, 'Offer Letter / CoE', 'offer_letter', 'Confirmation of Enrolment or Offer Letter', 1, 2, 1, '2026-01-05 08:31:25', '2026-01-05 08:31:25'),
(3, 'Financial Proof', 'financials', 'Bank statements, sponsor letters, scholarship docs', 1, 3, 1, '2026-01-05 08:31:25', '2026-01-05 08:31:25'),
(4, 'English Test Results', 'english_test', 'IELTS, PTE, TOEFL or equivalent', 1, 4, 1, '2026-01-05 08:31:25', '2026-01-05 08:31:25'),
(5, 'Academic Documents', 'academic_docs', 'Transcripts, certificates, marksheets', 1, 5, 1, '2026-01-05 08:31:25', '2026-01-05 08:31:25'),
(6, 'Health Insurance', 'health_insurance', 'OSHC or equivalent health coverage', 0, 6, 1, '2026-01-05 08:31:25', '2026-01-05 08:31:25'),
(7, 'Police Clearance', 'police_clearance', 'Police verification certificate', 0, 7, 1, '2026-01-05 08:31:25', '2026-01-05 08:31:25'),
(8, 'Medical Examination', 'medical_exam', 'Health examination report if required', 0, 8, 1, '2026-01-05 08:31:25', '2026-01-05 08:31:25'),
(9, 'Statement of Purpose', 'sop', 'Personal statement or essay', 0, 9, 1, '2026-01-05 08:31:25', '2026-01-05 08:31:25'),
(10, 'Recommendation Letters', 'recommendation', 'Letters from professors or employers', 0, 10, 1, '2026-01-05 08:31:25', '2026-01-05 10:17:17'),
(15, 'Color Test Updated', 'color_test', '', 1, 102, 1, '2026-01-05 09:52:14', '2026-01-05 09:52:31');

-- --------------------------------------------------------

--
-- Table structure for table `document_versions`
--

CREATE TABLE `document_versions` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `version_number` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `change_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `education_levels`
--

CREATE TABLE `education_levels` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `level_order` int(11) DEFAULT 0 COMMENT 'For sorting: 1=High School, 2=Diploma, etc.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `education_levels`
--

INSERT INTO `education_levels` (`id`, `name`, `level_order`, `created_at`) VALUES
(1, 'High School', 1, '2026-01-04 11:42:24'),
(2, 'High School Completed', 2, '2026-01-04 11:42:24'),
(3, 'Diploma', 3, '2026-01-04 11:42:24'),
(4, 'Bachelor Running', 4, '2026-01-04 11:42:24'),
(5, 'Bachelor Completed', 5, '2026-01-04 11:42:24'),
(6, 'Master Running', 6, '2026-01-04 11:42:24'),
(7, 'Master Completed', 7, '2026-01-04 11:42:24'),
(8, 'PhD Running', 8, '2026-01-04 11:42:24'),
(9, 'PhD Completed', 9, '2026-01-04 11:42:24');

-- --------------------------------------------------------

--
-- Table structure for table `email_queue`
--

CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `subject` varchar(500) NOT NULL,
  `body` longtext NOT NULL,
  `template` varchar(100) DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_queue`
--

INSERT INTO `email_queue` (`id`, `recipient_email`, `recipient_name`, `subject`, `body`, `template`, `scheduled_at`, `status`, `attempts`, `error_message`, `sent_at`, `created_at`) VALUES
(1, 'admin@example.com', 'System Admin', 'Notification: Task Assigned', 'You have a new notification: task assigned', 'task_assigned', NULL, 'pending', 0, NULL, NULL, '2026-01-21 09:23:21'),
(2, 'admin@example.com', 'System Admin', 'Notification: Task Assigned', 'You have a new notification: task assigned', 'task_assigned', NULL, 'pending', 0, NULL, NULL, '2026-01-21 09:28:48'),
(3, 'admin@example.com', 'System Admin', 'Notification: Task Assigned', 'You have a new notification: task assigned', 'task_assigned', NULL, 'pending', 0, NULL, NULL, '2026-01-21 09:30:34'),
(4, 'student987@test.com', 'Test Student 987', 'Welcome to EduCRM - Your Account Has Been Created Test', '<body style=\"margin: 0;\" ><div id=\"ias8\"><h2 id=\"i9in\">???? Welcome to EduCRM!</h2><p>Hi Test Student 987,</p><p>Your account has been successfully created. Below are your login credentials:</p><div id=\"izta\"><p id=\"iid7\"><strong>Email:</strong> student987@test.com</p><p id=\"i3zhw\"><strong>Temporary Password:</strong> <code id=\"ixvyh\">unNYoODo7Z))</code></p></div><p id=\"ij93i\">?????? For security, please change your password after your first login.</p><p><a href=\"http://localhost/CRM/login.php\" id=\"ia3dl\">Login to Your Account</a></p><p id=\"im52l\">If you did not request this account, please ignore this email.</p></div></body>', 'welcome', NULL, 'pending', 0, NULL, NULL, '2026-01-22 05:41:22'),
(5, 'admin@example.com', 'System Admin', 'Appointment Reminder: Test Meeting', 'Reminder email will be generated by cron job', 'appointment_reminder', '2026-01-22 10:00:00', 'pending', 0, NULL, NULL, '2026-01-22 05:43:33'),
(6, 'staff852@test.com', 'Test Staff', 'Welcome to EduCRM - Your Account Has Been Created Test', '<body style=\"margin: 0;\" ><div id=\"ias8\"><h2 id=\"i9in\">???? Welcome to EduCRM!</h2><p>Hi Test Staff,</p><p>Your account has been successfully created. Below are your login credentials:</p><div id=\"izta\"><p id=\"iid7\"><strong>Email:</strong> staff852@test.com</p><p id=\"i3zhw\"><strong>Temporary Password:</strong> <code id=\"ixvyh\">z-hjFKVkDdRL</code></p></div><p id=\"ij93i\">?????? For security, please change your password after your first login.</p><p><a href=\"http://localhost/CRM/login.php\" id=\"ia3dl\">Login to Your Account</a></p><p id=\"im52l\">If you did not request this account, please ignore this email.</p></div></body>', 'welcome', NULL, 'pending', 0, NULL, NULL, '2026-01-22 05:48:49'),
(7, 'admin@example.com', 'System Admin', 'Appointment Reminder: Far Future Debug', 'Reminder email will be generated by cron job', 'appointment_reminder', '2029-12-11 12:12:00', 'pending', 0, NULL, NULL, '2026-01-22 05:55:17'),
(8, 'counselor.1767611622@example.com', 'Test Counselor', 'Appointment Reminder: Counselor Test', 'Reminder email will be generated by cron job', 'appointment_reminder', '2029-11-10 11:11:00', 'pending', 0, NULL, NULL, '2026-01-22 05:55:50'),
(9, 'admin@example.com', 'System Admin', 'Appointment Reminder: Inquiry Meeting', 'Reminder email will be generated by cron job', 'appointment_reminder', '2029-10-09 10:10:00', 'pending', 0, NULL, NULL, '2026-01-22 05:56:16'),
(10, 'teststudent@test.com', 'Test Student UI', 'Welcome to EduCRM - Your Account Has Been Created Test', '<body style=\"margin: 0;\" ><div id=\"ias8\"><h2 id=\"i9in\">???? Welcome to EduCRM!</h2><p>Hi Test Student UI,</p><p>Your account has been successfully created. Below are your login credentials:</p><div id=\"izta\"><p id=\"iid7\"><strong>Email:</strong> teststudent@test.com</p><p id=\"i3zhw\"><strong>Temporary Password:</strong> <code id=\"ixvyh\">TWU54Ma#ANg8</code></p></div><p id=\"ij93i\">?????? For security, please change your password after your first login.</p><p><a href=\"http://localhost/CRM/login.php\" id=\"ia3dl\">Login to Your Account</a></p><p id=\"im52l\">If you did not request this account, please ignore this email.</p></div></body>', 'welcome', NULL, 'pending', 0, NULL, NULL, '2026-01-22 06:38:05'),
(11, 'sara@mul.edu.np', 'Sara', 'Welcome to EduCRM - Your Account Has Been Created Test', '<body style=\"margin: 0;\" ><div id=\"ias8\"><h2 id=\"i9in\">???? Welcome to EduCRM!</h2><p>Hi Sara,</p><p>Your account has been successfully created. Below are your login credentials:</p><div id=\"izta\"><p id=\"iid7\"><strong>Email:</strong> sara@mul.edu.np</p><p id=\"i3zhw\"><strong>Temporary Password:</strong> <code id=\"ixvyh\">WFuMn0Sgp$Xh</code></p></div><p id=\"ij93i\">?????? For security, please change your password after your first login.</p><p><a href=\"http://system.mul.edu.np/login.php\" id=\"ia3dl\">Login to Your Account</a></p><p id=\"im52l\">If you did not request this account, please ignore this email.</p></div></body>', 'welcome', NULL, 'pending', 0, NULL, NULL, '2026-02-03 08:18:08'),
(12, 'binayakchhetri56@gmail.com', 'Binayak Pandey', 'Welcome to EduCRM - Your Account Has Been Created Test', '<body style=\"margin: 0;\" ><div id=\"ias8\"><h2 id=\"i9in\">???? Welcome to EduCRM!</h2><p>Hi Binayak Pandey,</p><p>Your account has been successfully created. Below are your login credentials:</p><div id=\"izta\"><p id=\"iid7\"><strong>Email:</strong> binayakchhetri56@gmail.com</p><p id=\"i3zhw\"><strong>Temporary Password:</strong> <code id=\"ixvyh\">CfWd6Rbt=jop</code></p></div><p id=\"ij93i\">?????? For security, please change your password after your first login.</p><p><a href=\"http://system.mul.edu.np/login.php\" id=\"ia3dl\">Login to Your Account</a></p><p id=\"im52l\">If you did not request this account, please ignore this email.</p></div></body>', 'welcome', NULL, 'pending', 0, NULL, NULL, '2026-02-03 10:06:31'),
(13, 'finjob.tamang@outlook.com', 'Saman Rokka', 'Welcome to EduCRM - Your Account Has Been Created Test', '<body style=\"margin: 0;\" ><div id=\"ias8\"><h2 id=\"i9in\">???? Welcome to EduCRM!</h2><p>Hi Saman Rokka,</p><p>Your account has been successfully created. Below are your login credentials:</p><div id=\"izta\"><p id=\"iid7\"><strong>Email:</strong> finjob.tamang@outlook.com</p><p id=\"i3zhw\"><strong>Temporary Password:</strong> <code id=\"ixvyh\">oN9Uw-NZQ-(s</code></p></div><p id=\"ij93i\">?????? For security, please change your password after your first login.</p><p><a href=\"http://system.mul.edu.np/login.php\" id=\"ia3dl\">Login to Your Account</a></p><p id=\"im52l\">If you did not request this account, please ignore this email.</p></div></body>', 'welcome', NULL, 'pending', 0, NULL, NULL, '2026-02-03 10:51:13'),
(14, 'samyog.shrestha@outlook.com', 'Samyog Shrestha', 'Welcome to EduCRM - Your Account Has Been Created Test', '<body style=\"margin: 0;\" ><div id=\"ias8\"><h2 id=\"i9in\">???? Welcome to EduCRM!</h2><p>Hi Samyog Shrestha,</p><p>Your account has been successfully created. Below are your login credentials:</p><div id=\"izta\"><p id=\"iid7\"><strong>Email:</strong> samyog.shrestha@outlook.com</p><p id=\"i3zhw\"><strong>Temporary Password:</strong> <code id=\"ixvyh\">BXms4P^19T(d</code></p></div><p id=\"ij93i\">?????? For security, please change your password after your first login.</p><p><a href=\"http://system.mul.edu.np/login.php\" id=\"ia3dl\">Login to Your Account</a></p><p id=\"im52l\">If you did not request this account, please ignore this email.</p></div></body>', 'welcome', NULL, 'pending', 0, NULL, NULL, '2026-02-05 11:07:35'),
(15, 'nikesh.mahat@outlook.com', 'Nikesh Mahat', 'Welcome to EduCRM - Your Account Has Been Created Test', '<body style=\"margin: 0;\" ><div id=\"ias8\"><h2 id=\"i9in\">???? Welcome to EduCRM!</h2><p>Hi Nikesh Mahat,</p><p>Your account has been successfully created. Below are your login credentials:</p><div id=\"izta\"><p id=\"iid7\"><strong>Email:</strong> nikesh.mahat@outlook.com</p><p id=\"i3zhw\"><strong>Temporary Password:</strong> <code id=\"ixvyh\">DjcP-lsiN$KK</code></p></div><p id=\"ij93i\">?????? For security, please change your password after your first login.</p><p><a href=\"http://system.mul.edu.np/login.php\" id=\"ia3dl\">Login to Your Account</a></p><p id=\"im52l\">If you did not request this account, please ignore this email.</p></div></body>', 'welcome', NULL, 'pending', 0, NULL, NULL, '2026-02-05 11:36:42'),
(16, 'paurakh.shah@outlook.com', 'Paurakh Shah', 'Welcome to EduCRM - Your Account Has Been Created Test', '<body style=\"margin: 0;\" ><div id=\"ias8\"><h2 id=\"i9in\">???? Welcome to EduCRM!</h2><p>Hi Paurakh Shah,</p><p>Your account has been successfully created. Below are your login credentials:</p><div id=\"izta\"><p id=\"iid7\"><strong>Email:</strong> paurakh.shah@outlook.com</p><p id=\"i3zhw\"><strong>Temporary Password:</strong> <code id=\"ixvyh\">z(N&kel9VZqN</code></p></div><p id=\"ij93i\">?????? For security, please change your password after your first login.</p><p><a href=\"http://system.mul.edu.np/login.php\" id=\"ia3dl\">Login to Your Account</a></p><p id=\"im52l\">If you did not request this account, please ignore this email.</p></div></body>', 'welcome', NULL, 'pending', 0, NULL, NULL, '2026-02-06 11:13:14'),
(17, 'bohemiankhulal@gmail.com', 'IS Khulal Magar', 'Welcome to EduCRM - Your Account Has Been Created Test', '<body style=\"margin: 0;\" ><div id=\"ias8\"><h2 id=\"i9in\">???? Welcome to EduCRM!</h2><p>Hi IS Khulal Magar,</p><p>Your account has been successfully created. Below are your login credentials:</p><div id=\"izta\"><p id=\"iid7\"><strong>Email:</strong> bohemiankhulal@gmail.com</p><p id=\"i3zhw\"><strong>Temporary Password:</strong> <code id=\"ixvyh\">M8Xe#7A%uSi6</code></p></div><p id=\"ij93i\">?????? For security, please change your password after your first login.</p><p><a href=\"http://system.mul.edu.np/login.php\" id=\"ia3dl\">Login to Your Account</a></p><p id=\"im52l\">If you did not request this account, please ignore this email.</p></div></body>', 'welcome', NULL, 'pending', 0, NULL, NULL, '2026-02-06 11:16:14'),
(18, 'dikshant.singh04@outlook.com', 'Dikshant Singh', 'Welcome to EduCRM - Your Account Has Been Created Test', '<body style=\"margin: 0;\" ><div id=\"ias8\"><h2 id=\"i9in\">???? Welcome to EduCRM!</h2><p>Hi Dikshant Singh,</p><p>Your account has been successfully created. Below are your login credentials:</p><div id=\"izta\"><p id=\"iid7\"><strong>Email:</strong> dikshant.singh04@outlook.com</p><p id=\"i3zhw\"><strong>Temporary Password:</strong> <code id=\"ixvyh\">-wI+n@#Zz=d7</code></p></div><p id=\"ij93i\">?????? For security, please change your password after your first login.</p><p><a href=\"http://system.mul.edu.np/login.php\" id=\"ia3dl\">Login to Your Account</a></p><p id=\"im52l\">If you did not request this account, please ignore this email.</p></div></body>', 'welcome', NULL, 'pending', 0, NULL, NULL, '2026-02-09 07:49:07'),
(19, 'rimalprajwal@gmail.com', 'Prajwal Rimal', 'Welcome to EduCRM - Your Account Has Been Created Test', '<body style=\"margin: 0;\" ><div id=\"ias8\"><h2 id=\"i9in\">???? Welcome to EduCRM!</h2><p>Hi Prajwal Rimal,</p><p>Your account has been successfully created. Below are your login credentials:</p><div id=\"izta\"><p id=\"iid7\"><strong>Email:</strong> rimalprajwal@gmail.com</p><p id=\"i3zhw\"><strong>Temporary Password:</strong> <code id=\"ixvyh\">ludvcufv8m7o</code></p></div><p id=\"ij93i\">?????? For security, please change your password after your first login.</p><p><a href=\"http://system.mul.edu.np/login.php\" id=\"ia3dl\">Login to Your Account</a></p><p id=\"im52l\">If you did not request this account, please ignore this email.</p></div></body>', 'welcome', NULL, 'pending', 0, NULL, NULL, '2026-02-10 09:26:25'),
(20, 'bohemiankhulal@gmail.com', 'IS Khulal Magar', 'Welcome to EduCRM - Your Account Has Been Created Test', '<body style=\"margin: 0;\" ><div id=\"ias8\"><h2 id=\"i9in\">???? Welcome to EduCRM!</h2><p>Hi IS Khulal Magar,</p><p>Your account has been successfully created. Below are your login credentials:</p><div id=\"izta\"><p id=\"iid7\"><strong>Email:</strong> bohemiankhulal@gmail.com</p><p id=\"i3zhw\"><strong>Temporary Password:</strong> <code id=\"ixvyh\">DBBK$UWS0w8%</code></p></div><p id=\"ij93i\">?????? For security, please change your password after your first login.</p><p><a href=\"https://system.mul.edu.np/login.php\" id=\"ia3dl\">Login to Your Account</a></p><p id=\"im52l\">If you did not request this account, please ignore this email.</p></div></body>', 'welcome', NULL, 'pending', 0, NULL, NULL, '2026-02-11 09:44:54'),
(21, 'bhandari.hemraj@outlook.com', 'Hem Raj Bhandari', 'Welcome to EduCRM - Your Account Has Been Created Test', '<body style=\"margin: 0;\" ><div id=\"ias8\"><h2 id=\"i9in\">???? Welcome to EduCRM!</h2><p>Hi Hem Raj Bhandari,</p><p>Your account has been successfully created. Below are your login credentials:</p><div id=\"izta\"><p id=\"iid7\"><strong>Email:</strong> bhandari.hemraj@outlook.com</p><p id=\"i3zhw\"><strong>Temporary Password:</strong> <code id=\"ixvyh\">NSYpg*)fuTO(</code></p></div><p id=\"ij93i\">?????? For security, please change your password after your first login.</p><p><a href=\"https://system.mul.edu.np/login.php\" id=\"ia3dl\">Login to Your Account</a></p><p id=\"im52l\">If you did not request this account, please ignore this email.</p></div></body>', 'welcome', NULL, 'pending', 0, NULL, NULL, '2026-02-11 14:55:53'),
(22, 'hemraj.bhandari@outlook.com', 'Hem Raj Bhandari', 'Welcome to EduCRM - Your Account Has Been Created Test', '<body style=\"margin: 0;\" ><div id=\"ias8\"><h2 id=\"i9in\">???? Welcome to EduCRM!</h2><p>Hi Hem Raj Bhandari,</p><p>Your account has been successfully created. Below are your login credentials:</p><div id=\"izta\"><p id=\"iid7\"><strong>Email:</strong> hemraj.bhandari@outlook.com</p><p id=\"i3zhw\"><strong>Temporary Password:</strong> <code id=\"ixvyh\">S(l0_%rW969%</code></p></div><p id=\"ij93i\">?????? For security, please change your password after your first login.</p><p><a href=\"https://system.mul.edu.np/login.php\" id=\"ia3dl\">Login to Your Account</a></p><p id=\"im52l\">If you did not request this account, please ignore this email.</p></div></body>', 'welcome', NULL, 'pending', 0, NULL, NULL, '2026-02-12 07:55:46');

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL,
  `template_key` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `subject` varchar(500) NOT NULL,
  `body_html` longtext NOT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `email_templates`
--

INSERT INTO `email_templates` (`id`, `template_key`, `name`, `description`, `subject`, `body_html`, `variables`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'welcome', 'Welcome Email', 'Sent to new users with login credentials', 'Welcome to EduCRM - Your Account Has Been Created Test', '<style>* { box-sizing: border-box; } body {margin: 0;}#ias8{font-family:Arial, sans-serif;max-width:600px;margin:0 auto;}#i9in{color:#0f766e;}#izta{background:#f0fdfa;padding:20px;border-left:4px solid #0f766e;margin:20px 0;}#iid7{margin:5px 0;}#i3zhw{margin:5px 0;}#ixvyh{background:#e2e8f0;padding:2px 8px;border-radius:4px;}#ij93i{color:#dc2626;font-weight:bold;}#ia3dl{background:#0f766e;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;}#im52l{color:#64748b;font-size:12px;margin-top:30px;}</style><body><div id=\"ias8\"><h2 id=\"i9in\">???? Welcome to EduCRM!</h2><p>Hi {name},</p><p>Your account has been successfully created. Below are your login credentials:</p><div id=\"izta\"><p id=\"iid7\"><strong>Email:</strong> {email}</p><p id=\"i3zhw\"><strong>Temporary Password:</strong> <code id=\"ixvyh\">{password}</code></p></div><p id=\"ij93i\">?????? For security, please change your password after your first login.</p><p><a href=\"{login_url}\" id=\"ia3dl\">Login to Your Account</a></p><p id=\"im52l\">If you did not request this account, please ignore this email.</p></div></body>', '[\"name\",\"email\",\"password\",\"login_url\"]', 1, '2026-01-08 07:55:25', '2026-01-08 10:01:17'),
(2, 'task_assignment', 'Task Assignment', 'Sent when a task is assigned to a user', 'New Task Assigned: {task_title}', '<style>* { box-sizing: border-box; } body {margin: 0;}*{box-sizing:border-box;}body{margin-top:0px;margin-right:0px;margin-bottom:0px;margin-left:0px;}#it2o{font-family:Arial, sans-serif;max-width:600px;margin-top:0px;margin-right:auto;margin-bottom:0px;margin-left:auto;}#iw5k{color:rgb(79, 70, 229);}#ign4{background-image:initial;background-position-x:initial;background-position-y:initial;background-size:initial;background-repeat:initial;background-attachment:initial;background-origin:initial;background-clip:initial;background-color:rgb(248, 250, 252);padding-top:15px;padding-right:15px;padding-bottom:15px;padding-left:15px;border-left-width:4px;border-left-style:solid;border-left-color:rgb(79, 70, 229);margin-top:20px;margin-right:0px;margin-bottom:20px;margin-left:0px;}#iwz9{margin-top:0px;margin-right:0px;margin-bottom:10px;margin-left:0px;}#i7rx5{margin-top:5px;margin-right:0px;margin-bottom:5px;margin-left:0px;}#im79i{margin-top:5px;margin-right:0px;margin-bottom:5px;margin-left:0px;}#icu4z{text-transform:uppercase;}#im2o2{margin-top:5px;margin-right:0px;margin-bottom:5px;margin-left:0px;}#ipdmj{background-image:initial;background-position-x:initial;background-position-y:initial;background-size:initial;background-repeat:initial;background-attachment:initial;background-origin:initial;background-clip:initial;background-color:rgb(79, 70, 229);color:white;padding-top:10px;padding-right:20px;padding-bottom:10px;padding-left:20px;text-decoration-line:none;text-decoration-thickness:initial;text-decoration-style:initial;text-decoration-color:initial;border-top-left-radius:5px;border-top-right-radius:5px;border-bottom-right-radius:5px;border-bottom-left-radius:5px;display:inline-block;}#iiiu1{color:rgb(100, 116, 139);font-size:12px;margin-top:30px;}</style><body><div id=\"it2o\"><h2 id=\"iw5k\">New Task Assigned</h2><p>Hi {name},</p><p>A new task has been assigned to you:</p><div id=\"ign4\"><h3 id=\"iwz9\">{task_title}</h3><p id=\"i7rx5\"><strong>Description:</strong> {task_description}</p><p id=\"im79i\"><strong>Priority:</strong> <span id=\"icu4z\">{priority}</span></p><p id=\"im2o2\"><strong>Due Date:</strong> {due_date}</p></div><p><a href=\"{task_url}\" id=\"ipdmj\">View Task</a></p><p id=\"iiiu1\">This is an automated notification from EduCRM.</p></div></body>', '[\"application_title\",\"appointment_date\",\"appointment_title\",\"appointment_url\",\"changes\",\"client_name\",\"counselor_name\",\"course_name\",\"course_url\",\"days_overdue\",\"document_name\",\"documents_url\",\"due_date\",\"email\",\"instructor\",\"location\",\"login_url\",\"meeting_link\",\"name\",\"new_stage\",\"old_stage\",\"passport\",\"password\",\"priority\",\"profile_url\",\"remarks\",\"schedule\",\"start_date\",\"status\",\"status_color\",\"task_description\",\"task_title\",\"task_url\",\"updated_at\",\"workflow_url\"]', 1, '2026-01-08 07:55:25', '2026-01-21 10:19:16'),
(3, 'task_overdue', 'Overdue Task Alert', 'Sent when a task becomes overdue', 'Overdue Task Alert: {task_title}', '<div style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;\">\n    <h2 style=\"color: #ef4444;\">?????? Overdue Task Alert</h2>\n    <p>Hi {name},</p>\n    <p>The following task is now <strong>{days_overdue} day(s) overdue</strong>:</p>\n    <div style=\"background: #fef2f2; padding: 15px; border-left: 4px solid #ef4444; margin: 20px 0;\">\n        <h3 style=\"margin: 0 0 10px 0;\">{task_title}</h3>\n        <p style=\"margin: 5px 0;\"><strong>Priority:</strong> <span style=\"text-transform: uppercase;\">{priority}</span></p>\n        <p style=\"margin: 5px 0;\"><strong>Was Due:</strong> {due_date}</p>\n    </div>\n    <p><a href=\"{task_url}\" style=\"background: #ef4444; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;\">Complete Task Now</a></p>\n    <p style=\"color: #64748b; font-size: 12px; margin-top: 30px;\">This is an automated alert from EduCRM.</p>\n</div>', '[\"name\", \"task_title\", \"days_overdue\", \"due_date\", \"priority\", \"task_url\"]', 1, '2026-01-08 07:55:25', '2026-01-08 07:55:25'),
(4, 'appointment_reminder', 'Appointment Reminder (Counselor)', 'Sent to counselors 24 hours before appointment', 'Appointment Reminder: {appointment_title}', '<div style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;\">\n    <h2 style=\"color: #4f46e5;\">Appointment Reminder</h2>\n    <p>Hi {name},</p>\n    <p>This is a reminder for your upcoming appointment:</p>\n    <div style=\"background: #f8fafc; padding: 15px; border-left: 4px solid #3b82f6; margin: 20px 0;\">\n        <h3 style=\"margin: 0 0 10px 0;\">{appointment_title}</h3>\n        <p style=\"margin: 5px 0;\"><strong>Client:</strong> {client_name}</p>\n        <p style=\"margin: 5px 0;\"><strong>Date & Time:</strong> {appointment_date}</p>\n        <p style=\"margin: 5px 0;\"><strong>Location:</strong> {location}</p>\n    </div>\n    <p><a href=\"{appointment_url}\" style=\"background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;\">View Appointment</a></p>\n    <p style=\"color: #64748b; font-size: 12px; margin-top: 30px;\">This is an automated reminder from EduCRM.</p>\n</div>', '[\"name\", \"appointment_title\", \"client_name\", \"appointment_date\", \"location\", \"meeting_link\", \"appointment_url\"]', 1, '2026-01-08 07:55:25', '2026-01-08 07:55:25'),
(5, 'appointment_reminder_client', 'Appointment Reminder (Client)', 'Sent to students/inquiries 24 hours before appointment', 'Appointment Reminder: {appointment_title}', '<div style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;\">\n    <h2 style=\"color: #4f46e5;\">Appointment Reminder</h2>\n    <p>Hi {name},</p>\n    <p>This is a reminder for your upcoming appointment with {counselor_name}:</p>\n    <div style=\"background: #f8fafc; padding: 15px; border-left: 4px solid #3b82f6; margin: 20px 0;\">\n        <h3 style=\"margin: 0 0 10px 0;\">{appointment_title}</h3>\n        <p style=\"margin: 5px 0;\"><strong>Date & Time:</strong> {appointment_date}</p>\n        <p style=\"margin: 5px 0;\"><strong>Location:</strong> {location}</p>\n    </div>\n    <p style=\"color: #64748b; font-size: 12px; margin-top: 30px;\">This is an automated reminder from EduCRM.</p>\n</div>', '[\"name\", \"appointment_title\", \"counselor_name\", \"appointment_date\", \"location\", \"meeting_link\"]', 1, '2026-01-08 07:55:25', '2026-01-08 07:55:25'),
(6, 'workflow_update', 'Visa Workflow Update', 'Sent when visa application stage changes', 'Visa Application Update - {new_stage}', '<div style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;\">\n    <h2 style=\"color: #0f766e;\">???? Visa Workflow Update</h2>\n    <p>Hi {name},</p>\n    <p>Your visa application status has been updated:</p>\n    <div style=\"background: #f0fdfa; padding: 15px; border-left: 4px solid #0f766e; margin: 20px 0;\">\n        <p style=\"margin: 5px 0;\"><strong>Application:</strong> {application_title}</p>\n        <p style=\"margin: 5px 0;\"><strong>Previous Stage:</strong> <span style=\"color: #64748b;\">{old_stage}</span></p>\n        <p style=\"margin: 5px 0;\"><strong>New Stage:</strong> <span style=\"color: #0f766e; font-weight: bold;\">{new_stage}</span></p>\n        <p style=\"margin: 5px 0;\"><strong>Updated:</strong> {updated_at}</p>\n    </div>\n    <p><a href=\"{workflow_url}\" style=\"background: #0f766e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;\">View Application</a></p>\n    <p style=\"color: #64748b; font-size: 12px; margin-top: 30px;\">This is an automated notification from EduCRM.</p>\n</div>', '[\"name\", \"application_title\", \"old_stage\", \"new_stage\", \"updated_at\", \"workflow_url\"]', 1, '2026-01-08 07:55:25', '2026-01-08 07:55:25'),
(7, 'document_update', 'Document Status Update', 'Sent when document status changes', 'Document Status Update: {document_name}', '<div style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;\">\n    <h2 style=\"color: #0f766e;\">???? Document Status Update</h2>\n    <p>Hi {name},</p>\n    <p>A document associated with your profile has been updated:</p>\n    <div style=\"background: #f8fafc; padding: 15px; border-left: 4px solid #0f766e; margin: 20px 0;\">\n        <p style=\"margin: 5px 0;\"><strong>Document:</strong> {document_name}</p>\n        <p style=\"margin: 5px 0;\"><strong>Status:</strong> <span style=\"color: {status_color}; font-weight: bold;\">{status}</span></p>\n        {remarks}\n    </div>\n    <p><a href=\"{documents_url}\" style=\"background: #0f766e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;\">View Documents</a></p>\n    <p style=\"color: #64748b; font-size: 12px; margin-top: 30px;\">This is an automated notification from EduCRM.</p>\n</div>', '[\"name\", \"document_name\", \"status\", \"status_color\", \"remarks\", \"documents_url\"]', 1, '2026-01-08 07:55:25', '2026-01-08 07:55:25'),
(8, 'enrollment', 'Course Enrollment Confirmation', 'Sent when student is enrolled in a class', 'Class Enrollment Confirmation: {course_name}', '<div style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;\">\n    <h2 style=\"color: #0f766e;\">???? Course Enrollment Confirmation</h2>\n    <p>Hi {name},</p>\n    <p>Congratulations! You have been enrolled in a new course:</p>\n    <div style=\"background: #f0fdfa; padding: 15px; border-left: 4px solid #0f766e; margin: 20px 0;\">\n        <h3 style=\"margin: 0 0 10px 0; color: #0f766e;\">{course_name}</h3>\n        <p style=\"margin: 5px 0;\"><strong>Start Date:</strong> {start_date}</p>\n        <p style=\"margin: 5px 0;\"><strong>Instructor:</strong> {instructor}</p>\n        <p style=\"margin: 5px 0;\"><strong>Schedule:</strong> {schedule}</p>\n    </div>\n    <p><a href=\"{course_url}\" style=\"background: #0f766e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;\">View Course Details</a></p>\n    <p style=\"color: #64748b; font-size: 12px; margin-top: 30px;\">Welcome aboard! This is an automated notification from EduCRM.</p>\n</div>', '[\"name\", \"course_name\", \"start_date\", \"instructor\", \"schedule\", \"course_url\"]', 1, '2026-01-08 07:55:25', '2026-01-08 07:55:25'),
(9, 'profile_update', 'Profile Update Notification', 'Sent when profile is modified', 'Your Profile Has Been Updated', '<style>* { box-sizing: border-box; } body {margin: 0;}#i6sg{font-family:Arial, sans-serif;max-width:600px;margin:0 auto;}#i9br{color:#0f766e;}#ik1t{background:#f8fafc;padding:15px;border-left:4px solid #0f766e;margin:20px 0;}#i39zv{background:#0f766e;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;}#izusm{color:#64748b;font-size:12px;margin-top:30px;}</style><body><div id=\"i6sg\"><h2 id=\"i9br\">???? Profile Updated</h2><p>Hi {name},</p><p>Your profile has been updated with the following changes:</p><div id=\"ik1t\">\r\n        {changes}\r\n    </div><p>If you did not make these changes, please contact support immediately.</p><p><a href=\"{profile_url}\" id=\"i39zv\">View Your Profile</a></p><p id=\"izusm\">This is an automated notification from EduCRM.</p></div></body>', '[\"name\",\"changes\",\"profile_url\",\"email\"]', 1, '2026-01-08 07:55:25', '2026-01-08 09:59:38'),
(10, 'welcome_login', 'Welcome & Login', '', 'Welcome to Mul Education {name}!', '<style>* { box-sizing: border-box; } body {margin: 0;}*{box-sizing:border-box;}body{margin-top:0px;margin-right:0px;margin-bottom:0px;margin-left:0px;}#iowj{font-family:Arial, sans-serif;max-width:600px;margin-top:0px;margin-right:auto;margin-bottom:0px;margin-left:auto;padding-top:20px;padding-right:20px;padding-bottom:20px;padding-left:20px;}#ivqs{color:rgb(15, 118, 110);}#iusc{display:inline-block;background-image:initial;background-position-x:initial;background-position-y:initial;background-size:initial;background-repeat:initial;background-attachment:initial;background-origin:initial;background-clip:initial;background-color:rgb(15, 118, 110);color:white;padding-top:12px;padding-right:24px;padding-bottom:12px;padding-left:24px;text-decoration-line:none;text-decoration-thickness:initial;text-decoration-style:initial;text-decoration-color:initial;border-top-left-radius:6px;border-top-right-radius:6px;border-bottom-right-radius:6px;border-bottom-left-radius:6px;margin-top:20px;}</style><body><div id=\"iowj\"><h2 id=\"ivqs\">Hello {name}!</h2><p id=\"ijtj\">Welcome to Student System of Mul Education.<br id=\"ihuv\" draggable=\"true\"/>Here, is your login link  {login_url}<br/>Your login details are following,<br/>{email} <br/>{password}<br/><br/><br/></p><a href=\"#\" id=\"iusc\">Call to Action</a></div></body>', '[\"name\",\"email\",\"login_url\",\"password\",\"passport\"]', 1, '2026-01-08 08:50:55', '2026-01-08 09:56:37');

-- --------------------------------------------------------

--
-- Table structure for table `email_template_channels`
--

CREATE TABLE `email_template_channels` (
  `id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `gateway_id` int(11) DEFAULT NULL,
  `channel_type` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `custom_content` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_template_channels`
--

INSERT INTO `email_template_channels` (`id`, `template_id`, `gateway_id`, `channel_type`, `is_active`, `custom_content`, `created_at`, `updated_at`) VALUES
(2, 2, NULL, 'sms', 1, 'Retry Verify: {task_title}', '2026-01-21 10:19:16', '2026-01-21 10:19:16');

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `student_id`, `class_id`, `enrolled_at`, `updated_at`) VALUES
(1, 3, 1, '2025-12-25 08:27:15', NULL),
(2, 4, 1, '2025-12-25 08:27:19', NULL),
(3, 6, 2, '2026-01-02 08:37:00', NULL),
(4, 7, 2, '2026-01-02 08:37:08', NULL),
(5, 8, 3, '2026-01-02 08:37:35', NULL),
(6, 9, 3, '2026-01-02 08:37:43', NULL),
(7, 11, 1, '2026-01-04 10:16:24', NULL),
(8, 16, 4, '2026-01-05 11:13:42', NULL),
(9, 20, 5, '2026-01-05 11:15:33', NULL),
(10, 25, 5, '2026-01-06 07:03:34', NULL),
(11, 3, 8, '2026-01-22 06:16:40', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `fee_types`
--

CREATE TABLE `fee_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `default_amount` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fee_types`
--

INSERT INTO `fee_types` (`id`, `name`, `default_amount`) VALUES
(1, 'Course Fee', 25000.00),
(2, 'IELTS Fee', 5000.00),
(3, 'Tuition Fee 2025', 1500.00);

-- --------------------------------------------------------

--
-- Table structure for table `inquiries`
--

CREATE TABLE `inquiries` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `intended_country` varchar(100) DEFAULT NULL,
  `country_id` int(11) DEFAULT NULL,
  `intended_course` varchar(50) DEFAULT NULL,
  `education_level` varchar(50) DEFAULT NULL,
  `education_level_id` int(11) DEFAULT NULL,
  `status` enum('new','contacted','converted','closed') DEFAULT 'new',
  `status_id` int(11) DEFAULT NULL,
  `priority` enum('hot','warm','cold') DEFAULT 'warm',
  `priority_id` int(11) DEFAULT NULL,
  `score` int(11) DEFAULT 0,
  `last_contact_date` datetime DEFAULT NULL,
  `engagement_count` int(11) DEFAULT 0,
  `assigned_to` int(11) DEFAULT NULL,
  `last_contacted` datetime DEFAULT NULL,
  `contact_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `source` varchar(50) DEFAULT NULL COMMENT 'Lead source: walk_in, referred, social_media_post, social_media_ad, sms_campaign, other',
  `source_other` varchar(255) DEFAULT NULL COMMENT 'Additional details when source is other',
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inquiries`
--

INSERT INTO `inquiries` (`id`, `name`, `email`, `phone`, `intended_country`, `country_id`, `intended_course`, `education_level`, `education_level_id`, `status`, `status_id`, `priority`, `priority_id`, `score`, `last_contact_date`, `engagement_count`, `assigned_to`, `last_contacted`, `contact_count`, `created_at`, `deleted_at`, `branch_id`, `source`, `source_other`, `updated_at`) VALUES
(1, 'Anita Rai', 'anita@test.com', '9841111111', 'Australia', 1, 'IELTS', 'Bachelor Completed', 5, 'converted', 3, 'warm', 2, 65, NULL, 0, 1, '2026-01-02 14:33:59', 0, '2026-01-02 08:48:59', NULL, NULL, NULL, NULL, '2026-01-22 17:11:05'),
(2, 'Test Migration', 'test@migrate.com', '123456', 'Canada', 4, 'MBA', 'Bachelor Completed', 5, 'new', 1, 'warm', 2, 0, NULL, 0, 1, '2026-01-04 17:31:44', 0, '2026-01-04 11:46:44', NULL, NULL, NULL, NULL, '2026-01-22 17:11:05'),
(3, 'Test Trigger', 'trigger@test.com', '555-1234', 'Australia', 1, 'Engineering', 'Bachelor Completed', 5, 'new', 1, 'warm', 1, 0, NULL, 0, 1, '2026-01-04 17:32:51', 0, '2026-01-04 11:47:51', NULL, NULL, NULL, NULL, '2026-01-22 17:11:05'),
(4, 'tesst', 'fsdf@fsdaf.com', '12321321', NULL, 1, 'Study Abroad', NULL, 1, 'new', 3, 'warm', 1, 65, NULL, 0, 1, '2026-01-05 10:07:17', 0, '2026-01-05 04:22:17', NULL, NULL, 'referred', NULL, '2026-01-22 17:11:05'),
(5, 'Sarah Johnson', 'sarah.johnson@gmail.com', '+977-9841234567', NULL, 1, 'IELTS', NULL, 4, 'new', 3, 'warm', 2, 65, NULL, 0, 1, '2026-01-05 13:56:16', 0, '2026-01-05 08:11:16', NULL, NULL, 'social_media_post', NULL, '2026-01-22 17:11:05'),
(6, 'tes', 'taeta@Qefadsf.dfd', '32r43', NULL, 10, 'IELTS', NULL, 1, 'new', 1, 'warm', 1, 55, NULL, 0, 1, '2026-01-05 15:42:07', 0, '2026-01-05 09:57:07', NULL, NULL, 'walk_in', NULL, '2026-01-22 17:11:05'),
(7, 'xxx', 'sdfasd@efdsf', '324324', NULL, 3, 'Study Abroad', NULL, 1, 'new', 3, 'warm', 2, 55, NULL, 0, 1, '2026-01-05 16:28:53', 0, '2026-01-05 10:43:53', NULL, NULL, 'walk_in', NULL, '2026-01-22 17:11:05'),
(8, 'John Doe 1767611622', 'john.doe.1767611622@test.com', '1234567890', 'Canada', 4, 'PTE', 'Bachelor', NULL, 'converted', 3, 'warm', 2, 0, NULL, 0, 1, '2026-01-05 16:58:42', 0, '2026-01-05 11:13:42', NULL, NULL, NULL, NULL, '2026-01-22 17:11:05'),
(9, 'John Doe 1767611732', 'john.doe.1767611732@test.com', '1234567890', 'Canada', 4, 'PTE', 'Bachelor', NULL, 'converted', 3, 'warm', 2, 0, NULL, 0, 1, '2026-01-05 17:00:32', 0, '2026-01-05 11:15:32', NULL, NULL, NULL, NULL, '2026-01-22 17:11:05'),
(10, 'Test Student 1', 'student1@test.com', '1111111111', NULL, NULL, '', NULL, NULL, 'new', 3, 'warm', 2, 55, NULL, 0, 1, '2026-01-06 12:11:37', 0, '2026-01-06 06:26:37', NULL, NULL, '', NULL, '2026-01-22 17:11:05'),
(11, 'Test Student 2', 'student2@test.com', '2222222222', NULL, NULL, '', NULL, NULL, 'new', 3, 'warm', 2, 55, NULL, 0, 1, '2026-01-06 12:13:19', 0, '2026-01-06 06:28:19', NULL, NULL, '', NULL, '2026-01-22 17:11:05'),
(12, 'Robot Test User', 'robot@example.com', '555-0199', NULL, NULL, '', NULL, NULL, 'new', 3, 'warm', 2, 55, NULL, 0, 1, '2026-01-22 11:16:59', 0, '2026-01-22 05:31:59', NULL, NULL, 'walk_in', NULL, '2026-01-22 17:11:05'),
(13, 'UI Test Lead', 'testlead@test.com', '9800000001', NULL, 1, 'IELTS', NULL, NULL, 'new', 1, 'warm', 2, 65, NULL, 0, 1, '2026-01-22 12:20:30', 0, '2026-01-22 06:35:30', NULL, NULL, 'social_media_ad', NULL, '2026-01-23 14:45:00');

-- --------------------------------------------------------

--
-- Table structure for table `inquiry_score_history`
--

CREATE TABLE `inquiry_score_history` (
  `id` int(11) NOT NULL,
  `inquiry_id` int(11) NOT NULL,
  `old_score` int(11) DEFAULT NULL,
  `new_score` int(11) DEFAULT NULL,
  `old_priority` enum('hot','warm','cold') DEFAULT NULL,
  `new_priority` enum('hot','warm','cold') DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inquiry_statuses`
--

CREATE TABLE `inquiry_statuses` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `status_order` int(11) NOT NULL DEFAULT 0,
  `is_final` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inquiry_statuses`
--

INSERT INTO `inquiry_statuses` (`id`, `name`, `status_order`, `is_final`, `created_at`) VALUES
(1, 'new', 1, 0, '2026-01-04 11:42:24'),
(2, 'contacted', 2, 0, '2026-01-04 11:42:24'),
(3, 'converted', 3, 1, '2026-01-04 11:42:24'),
(4, 'closed', 4, 1, '2026-01-04 11:42:24');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `fee_type_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('paid','unpaid','partial','cancelled','overdue') DEFAULT 'unpaid',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messaging_campaigns`
--

CREATE TABLE `messaging_campaigns` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `message_type` enum('sms','whatsapp','viber','email') NOT NULL,
  `type_id` int(11) DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `target_audience` text DEFAULT NULL,
  `total_recipients` int(11) DEFAULT 0,
  `sent_count` int(11) DEFAULT 0,
  `delivered_count` int(11) DEFAULT 0,
  `failed_count` int(11) DEFAULT 0,
  `status` enum('draft','scheduled','processing','completed','cancelled') DEFAULT 'draft',
  `scheduled_at` datetime DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messaging_gateways`
--

CREATE TABLE `messaging_gateways` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('sms','whatsapp','viber','email') NOT NULL,
  `type_id` int(11) DEFAULT NULL,
  `provider` varchar(50) NOT NULL,
  `config` text NOT NULL,
  `priority` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `is_default` tinyint(1) DEFAULT 0,
  `daily_limit` int(11) DEFAULT 1000,
  `daily_sent` int(11) DEFAULT 0,
  `total_sent` int(11) DEFAULT 0,
  `total_failed` int(11) DEFAULT 0,
  `cost_per_message` decimal(10,4) DEFAULT 0.0000,
  `last_used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messaging_gateways`
--

INSERT INTO `messaging_gateways` (`id`, `name`, `type`, `type_id`, `provider`, `config`, `priority`, `is_active`, `is_default`, `daily_limit`, `daily_sent`, `total_sent`, `total_failed`, `cost_per_message`, `last_used_at`, `created_at`, `updated_at`) VALUES
(1, 'Pankaj Kumar Chhetry', 'sms', 1, 'gammu', '[]', 0, 1, 0, 1000, 0, 0, 0, 0.0000, NULL, '2026-01-04 10:37:09', '2026-01-04 11:42:25'),
(2, 'Whatsapp', 'whatsapp', 3, 'whatsapp_business', '{\"phone_number_id\":\"46546546\",\"default_country_code\":\"+977\"}', 0, 1, 0, 1000, 0, 0, 0, 0.0000, NULL, '2026-01-04 10:43:45', '2026-01-04 11:42:25'),
(3, 'Viber', 'viber', 4, 'viber_bot', '{\"default_country_code\":\"+1\"}', 0, 1, 0, 1000, 0, 0, 0, 0.0000, NULL, '2026-01-04 11:22:27', '2026-01-04 11:42:25'),
(4, 'Test Viber', 'viber', 4, 'viber_bot', '{\"auth_token\":\"34535\",\"bot_name\":\"vib_bot\",\"bot_avatar\":\"=)\",\"default_country_code\":\"+977\"}', 0, 1, 0, 1000, 0, 0, 0, 0.0000, NULL, '2026-01-07 08:00:55', '2026-01-07 08:00:55'),
(5, 'Test SMS Gateway', 'sms', 1, 'twilio', '', 0, 1, 0, 1000, 0, 0, 0, 0.0000, NULL, '2026-01-07 08:05:40', '2026-01-07 08:05:40'),
(6, 'Test Viber Gateway', 'viber', 4, 'viber_bot', '', 0, 1, 0, 1000, 0, 0, 0, 0.0000, NULL, '2026-01-07 08:05:40', '2026-01-07 08:05:40'),
(7, 'Test WhatsApp Gateway', 'whatsapp', 3, 'twilio_whatsapp', '', 0, 1, 0, 1000, 0, 0, 0, 0.0000, NULL, '2026-01-07 08:05:40', '2026-01-07 08:05:40'),
(8, 'ntfy Local', '', NULL, 'ntfy', '{\"url\":\"http:\\/\\/localhost:8090\",\"topic_prefix\":\"educrm\",\"default_country_code\":\"+1\"}', 0, 1, 0, 1000, 0, 0, 0, 0.0000, NULL, '2026-01-19 11:08:38', '2026-01-19 11:08:38'),
(9, 'ntfy Test New', '', NULL, 'ntfy', '{\"url\":\"http:\\/\\/localhost:8090\",\"topic_prefix\":\"educrm\",\"default_country_code\":\"+1\"}', 0, 1, 0, 1000, 0, 0, 0, 0.0000, NULL, '2026-01-19 11:27:06', '2026-01-19 11:27:06');

-- --------------------------------------------------------

--
-- Table structure for table `messaging_logs`
--

CREATE TABLE `messaging_logs` (
  `id` int(11) NOT NULL,
  `gateway_id` int(11) NOT NULL,
  `gateway_name` varchar(100) NOT NULL,
  `recipient` varchar(50) DEFAULT NULL,
  `message_id` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `level` enum('DEBUG','INFO','WARNING','ERROR') DEFAULT 'INFO',
  `status` enum('pending','sent','delivered','failed','error') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messaging_logs`
--

INSERT INTO `messaging_logs` (`id`, `gateway_id`, `gateway_name`, `recipient`, `message_id`, `message`, `level`, `status`, `error_message`, `metadata`, `created_at`) VALUES
(1, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_BF51024089FD', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2026-01-04 08:32:20'),
(2, 1, 'Pankaj Kumar Chhetry', '+9779876543210', 'MSG_F4BF0D2AF67C', 'Delivery receipt received', 'INFO', 'delivered', NULL, NULL, '2026-01-04 14:23:36'),
(3, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_C52CE8DFE80A', 'Message queued for delivery', 'INFO', 'sent', NULL, NULL, '2026-01-04 06:39:24'),
(4, 1, 'Pankaj Kumar Chhetry', '+9779876543210', 'MSG_29D3F6E7F035', 'Delivery receipt received', 'INFO', 'delivered', NULL, NULL, '2026-01-04 09:30:36'),
(5, 1, 'Pankaj Kumar Chhetry', '+9779876543210', 'MSG_E0AED6F1E6D1', 'Rate limit 80% reached', 'WARNING', 'sent', NULL, NULL, '2026-01-04 13:40:11'),
(6, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_8E2B16AB396D', 'Delivery receipt received', 'INFO', 'delivered', NULL, NULL, '2026-01-03 10:22:12'),
(7, 1, 'Pankaj Kumar Chhetry', '+9779876543210', 'MSG_22A27D4FF0BA', 'SMS delivered and confirmed', 'INFO', 'delivered', NULL, NULL, '2026-01-03 05:43:35'),
(8, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_6D549D00087A', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2026-01-03 11:15:17'),
(9, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_1BBCB43CF3A8', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2026-01-03 09:04:49'),
(10, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_F228504B58D3', 'Rate limit 80% reached', 'WARNING', 'sent', NULL, NULL, '2026-01-03 02:22:29'),
(11, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_4482BE1DB83C', 'Initializing gateway connection', 'DEBUG', 'pending', NULL, NULL, '2026-01-03 08:41:42'),
(12, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_68D519319EC4', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2026-01-03 15:40:30'),
(13, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_258290678181', 'Initializing gateway connection', 'DEBUG', 'pending', NULL, NULL, '2026-01-03 12:13:39'),
(14, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_504BB8B1B0A4', 'Message queued for delivery', 'INFO', 'sent', NULL, NULL, '2026-01-02 16:24:23'),
(15, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_5EA8A6B125E0', 'SMS delivered and confirmed', 'INFO', 'delivered', NULL, NULL, '2026-01-02 06:42:16'),
(16, 1, 'Pankaj Kumar Chhetry', '+9779876543210', 'MSG_DDB5E6EDDFDF', 'Initializing gateway connection', 'DEBUG', 'pending', NULL, NULL, '2026-01-02 08:12:22'),
(17, 1, 'Pankaj Kumar Chhetry', '+9779876543210', 'MSG_6FEBC6053F8C', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2026-01-02 05:46:43'),
(18, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_B43F6F9A6936', 'SMS delivered and confirmed', 'INFO', 'delivered', NULL, NULL, '2026-01-02 08:54:34'),
(19, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_2FB81504E631', 'Rate limit 80% reached', 'WARNING', 'sent', NULL, NULL, '2026-01-02 05:05:12'),
(20, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_0F3A2220D9EC', 'Initializing gateway connection', 'DEBUG', 'pending', NULL, NULL, '2026-01-02 05:07:28'),
(21, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_459F2995851D', 'Rate limit 80% reached', 'WARNING', 'sent', NULL, NULL, '2026-01-02 12:39:50'),
(22, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_1E0E668A71C1', 'Initializing gateway connection', 'DEBUG', 'pending', NULL, NULL, '2026-01-02 15:59:50'),
(23, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_4D1B89CC83B3', 'Delivery receipt received', 'INFO', 'delivered', NULL, NULL, '2026-01-02 03:04:31'),
(24, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_D496B8998DCD', 'Delivery receipt received', 'INFO', 'delivered', NULL, NULL, '2026-01-02 06:09:11'),
(25, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_BC4B66D8F029', 'Rate limit 80% reached', 'WARNING', 'sent', NULL, NULL, '2026-01-02 04:32:56'),
(26, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_3ED66FD975CE', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2026-01-01 05:13:20'),
(27, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_EEF6BFCD7BCE', 'Rate limit 80% reached', 'WARNING', 'sent', NULL, NULL, '2026-01-01 10:02:04'),
(28, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_2624E9501ABB', 'Rate limit 80% reached', 'WARNING', 'sent', NULL, NULL, '2026-01-01 02:28:16'),
(29, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_D9BF079EF06B', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2026-01-01 13:46:48'),
(30, 1, 'Pankaj Kumar Chhetry', '+9779876543210', 'MSG_440B68C3E23E', 'Message queued for delivery', 'INFO', 'sent', NULL, NULL, '2026-01-01 04:13:06'),
(31, 1, 'Pankaj Kumar Chhetry', '+9779876543210', 'MSG_28FF8D35D58B', 'Message queued for delivery', 'INFO', 'sent', NULL, NULL, '2026-01-01 06:15:43'),
(32, 1, 'Pankaj Kumar Chhetry', '+9779876543210', 'MSG_FB215589394A', 'SMS delivered and confirmed', 'INFO', 'delivered', NULL, NULL, '2026-01-01 12:50:12'),
(33, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_CC622C5B77BB', 'Invalid phone number +123', 'ERROR', 'failed', NULL, NULL, '2026-01-01 15:31:26'),
(34, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_DAE07A2A6B55', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2026-01-01 14:24:47'),
(35, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_9D392646F152', 'Message queued for delivery', 'INFO', 'sent', NULL, NULL, '2025-12-31 15:17:20'),
(36, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_AF522F7E9A7F', 'Rate limit 80% reached', 'WARNING', 'sent', NULL, NULL, '2025-12-31 04:12:24'),
(37, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_B7F85BF8E180', 'SMS delivered and confirmed', 'INFO', 'delivered', NULL, NULL, '2025-12-31 10:09:46'),
(38, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_41B137A8286E', 'Initializing gateway connection', 'DEBUG', 'pending', NULL, NULL, '2025-12-31 09:37:24'),
(39, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_D1A43ABE5FA6', 'Rate limit 80% reached', 'WARNING', 'sent', NULL, NULL, '2025-12-31 05:12:43'),
(40, 1, 'Pankaj Kumar Chhetry', '+9779876543210', 'MSG_D5ABAB0D942B', 'Delivery receipt received', 'INFO', 'delivered', NULL, NULL, '2025-12-31 14:33:14'),
(41, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_A68A48B58D0F', 'Rate limit 80% reached', 'WARNING', 'sent', NULL, NULL, '2025-12-31 11:25:04'),
(42, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_67749FD9B5B7', 'Message queued for delivery', 'INFO', 'sent', NULL, NULL, '2025-12-31 11:22:09'),
(43, 1, 'Pankaj Kumar Chhetry', '+9779876543210', 'MSG_A1B0C0988164', 'Initializing gateway connection', 'DEBUG', 'pending', NULL, NULL, '2025-12-31 05:42:33'),
(44, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_4BA8B67879BA', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2025-12-31 06:19:04'),
(45, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_207DD5AF2DEA', 'SMS delivered and confirmed', 'INFO', 'delivered', NULL, NULL, '2025-12-30 15:33:37'),
(46, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_712C6FC10A35', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2025-12-30 07:03:29'),
(47, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_9C1F16281E6E', 'Invalid phone number +123', 'ERROR', 'failed', NULL, NULL, '2025-12-30 10:45:07'),
(48, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_2F1B3FD52F5A', 'Initializing gateway connection', 'DEBUG', 'pending', NULL, NULL, '2025-12-30 08:15:25'),
(49, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_0D32311931F5', 'Rate limit 80% reached', 'WARNING', 'sent', NULL, NULL, '2025-12-30 11:33:36'),
(50, 1, 'Pankaj Kumar Chhetry', '+9779876543210', 'MSG_65F7863B3D2B', 'SMS delivered and confirmed', 'INFO', 'delivered', NULL, NULL, '2025-12-30 14:35:54'),
(51, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_5AEC8D58461E', 'Invalid phone number +123', 'ERROR', 'failed', NULL, NULL, '2025-12-30 12:49:53'),
(52, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_6BD41CA9C2E2', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2025-12-30 16:07:57'),
(53, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_C338655D0B32', 'Message queued for delivery', 'INFO', 'sent', NULL, NULL, '2025-12-30 09:10:28'),
(54, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_917EA8655A06', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2025-12-30 10:21:29'),
(55, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_735359888092', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2025-12-30 09:47:43'),
(56, 1, 'Pankaj Kumar Chhetry', '+9779876543210', 'MSG_CB5A7FA4BED3', 'Message queued for delivery', 'INFO', 'sent', NULL, NULL, '2025-12-30 05:55:23'),
(57, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_B1E486B56006', 'Message queued for delivery', 'INFO', 'sent', NULL, NULL, '2025-12-30 02:18:17'),
(58, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_A3DE3DDE3CAC', 'Message queued for delivery', 'INFO', 'sent', NULL, NULL, '2025-12-30 09:38:55'),
(59, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_154B3B4988C2', 'Initializing gateway connection', 'DEBUG', 'pending', NULL, NULL, '2025-12-30 13:27:07'),
(60, 1, 'Pankaj Kumar Chhetry', '+9779876543210', 'MSG_E53194077403', 'Message queued for delivery', 'INFO', 'sent', NULL, NULL, '2025-12-29 13:31:52'),
(61, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_774E1F187216', 'Rate limit 80% reached', 'WARNING', 'sent', NULL, NULL, '2025-12-29 07:04:09'),
(62, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_FFC5D7854DF1', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2025-12-29 14:17:54'),
(63, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_0C14B976E330', 'Rate limit 80% reached', 'WARNING', 'sent', NULL, NULL, '2025-12-29 12:14:09'),
(64, 1, 'Pankaj Kumar Chhetry', '+9779812345678', 'MSG_79A43F7A2EC4', 'Initializing gateway connection', 'DEBUG', 'pending', NULL, NULL, '2025-12-29 14:29:56'),
(65, 1, 'Pankaj Kumar Chhetry', '+9771234567890', 'MSG_C03623D0BD4F', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2025-12-29 14:44:54'),
(66, 2, 'Whatsapp', '+9779876543210', 'MSG_72D9E291952E', 'SMS delivered and confirmed', 'INFO', 'delivered', NULL, NULL, '2026-01-04 15:25:34'),
(67, 2, 'Whatsapp', '+9771234567890', 'MSG_97227EC478A3', 'SMS delivered and confirmed', 'INFO', 'delivered', NULL, NULL, '2026-01-04 05:34:08'),
(68, 2, 'Whatsapp', '+9779812345678', 'MSG_8094EB898581', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2026-01-04 16:33:22'),
(69, 2, 'Whatsapp', '+9779876543210', 'MSG_846A2C719920', 'Message queued for delivery', 'INFO', 'sent', NULL, NULL, '2026-01-04 13:37:48'),
(70, 2, 'Whatsapp', '+9779876543210', 'MSG_50C65E9A6D6C', 'Message queued for delivery', 'INFO', 'sent', NULL, NULL, '2026-01-04 14:21:31'),
(71, 2, 'Whatsapp', '+9779812345678', 'MSG_28386DEB2448', 'Invalid phone number +123', 'ERROR', 'failed', NULL, NULL, '2026-01-04 12:37:36'),
(72, 2, 'Whatsapp', '+9779876543210', 'MSG_C741606301A9', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2026-01-03 13:14:21'),
(73, 2, 'Whatsapp', '+9771234567890', 'MSG_23C112464BC0', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2026-01-03 03:24:03'),
(74, 2, 'Whatsapp', '+9779876543210', 'MSG_814859B178A3', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2026-01-03 02:46:31'),
(75, 2, 'Whatsapp', '+9779812345678', 'MSG_55666448BF5D', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2026-01-03 15:02:44'),
(76, 2, 'Whatsapp', '+9771234567890', 'MSG_4CEC691A972A', 'Message queued for delivery', 'INFO', 'sent', NULL, NULL, '2026-01-03 14:13:58'),
(77, 2, 'Whatsapp', '+9779812345678', 'MSG_030268C9A44C', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2026-01-03 06:04:11'),
(78, 2, 'Whatsapp', '+9771234567890', 'MSG_297B8B87AB6E', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2026-01-03 05:03:24'),
(79, 2, 'Whatsapp', '+9779876543210', 'MSG_2F058B4C2ABF', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2026-01-03 14:08:10'),
(80, 2, 'Whatsapp', '+9771234567890', 'MSG_70E8D6137049', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2026-01-03 07:00:14'),
(81, 2, 'Whatsapp', '+9779812345678', 'MSG_E7A39B8E422A', 'Message queued for delivery', 'INFO', 'sent', NULL, NULL, '2026-01-03 05:50:31'),
(82, 2, 'Whatsapp', '+9771234567890', 'MSG_79AFE30370C5', 'Delivery receipt received', 'INFO', 'delivered', NULL, NULL, '2026-01-03 13:12:46'),
(83, 2, 'Whatsapp', '+9771234567890', 'MSG_4D6D3DDE7233', 'SMS delivered and confirmed', 'INFO', 'delivered', NULL, NULL, '2026-01-03 11:20:30'),
(84, 2, 'Whatsapp', '+9779812345678', 'MSG_2BAFFC02A9EC', 'SMS delivered and confirmed', 'INFO', 'delivered', NULL, NULL, '2026-01-02 06:57:25'),
(85, 2, 'Whatsapp', '+9779876543210', 'MSG_8628CE9A605C', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2026-01-02 06:00:49'),
(86, 2, 'Whatsapp', '+9779876543210', 'MSG_F675F19C72D8', 'Rate limit 80% reached', 'WARNING', 'sent', NULL, NULL, '2026-01-02 16:40:11'),
(87, 2, 'Whatsapp', '+9771234567890', 'MSG_2C9AD1176867', 'Message queued for delivery', 'INFO', 'sent', NULL, NULL, '2026-01-02 15:47:47'),
(88, 2, 'Whatsapp', '+9771234567890', 'MSG_45109ACF7A17', 'Rate limit 80% reached', 'WARNING', 'sent', NULL, NULL, '2026-01-02 11:25:56'),
(89, 2, 'Whatsapp', '+9771234567890', 'MSG_A7BEE773028F', 'Initializing gateway connection', 'DEBUG', 'pending', NULL, NULL, '2026-01-02 15:11:35'),
(90, 2, 'Whatsapp', '+9779812345678', 'MSG_A79BF51C8389', 'SMS delivered and confirmed', 'INFO', 'delivered', NULL, NULL, '2026-01-02 04:49:39'),
(91, 2, 'Whatsapp', '+9771234567890', 'MSG_6C122160F1F9', 'Initializing gateway connection', 'DEBUG', 'pending', NULL, NULL, '2026-01-02 12:09:58'),
(92, 2, 'Whatsapp', '+9771234567890', 'MSG_92B5F41BFAFF', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2026-01-02 12:57:04'),
(93, 2, 'Whatsapp', '+9779812345678', 'MSG_06DD2A14F113', 'SMS delivered and confirmed', 'INFO', 'delivered', NULL, NULL, '2026-01-02 13:16:15'),
(94, 2, 'Whatsapp', '+9771234567890', 'MSG_AA145B601636', 'Message queued for delivery', 'INFO', 'sent', NULL, NULL, '2026-01-02 12:35:59'),
(95, 2, 'Whatsapp', '+9779812345678', 'MSG_5DF84E2FB270', 'Delivery receipt received', 'INFO', 'delivered', NULL, NULL, '2026-01-02 13:47:03'),
(96, 2, 'Whatsapp', '+9779876543210', 'MSG_56CFFDF136E4', 'Delivery receipt received', 'INFO', 'delivered', NULL, NULL, '2026-01-02 07:12:06'),
(97, 2, 'Whatsapp', '+9779812345678', 'MSG_57665DD67143', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2026-01-02 09:42:27'),
(98, 2, 'Whatsapp', '+9779812345678', 'MSG_B0F7BD08385F', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2026-01-02 13:47:20'),
(99, 2, 'Whatsapp', '+9779812345678', 'MSG_BD9D408A9404', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2026-01-01 06:38:35'),
(100, 2, 'Whatsapp', '+9779812345678', 'MSG_7FAAA636809E', 'Rate limit 80% reached', 'WARNING', 'sent', NULL, NULL, '2026-01-01 07:09:58'),
(101, 2, 'Whatsapp', '+9779876543210', 'MSG_FD969B9C3637', 'Invalid phone number +123', 'ERROR', 'failed', NULL, NULL, '2026-01-01 11:48:18'),
(102, 2, 'Whatsapp', '+9779876543210', 'MSG_575FFF84928D', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2026-01-01 05:00:06'),
(103, 2, 'Whatsapp', '+9779876543210', 'MSG_DE420C2E17D7', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2026-01-01 16:57:20'),
(104, 2, 'Whatsapp', '+9779812345678', 'MSG_74F02DEC9849', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2026-01-01 07:11:57'),
(105, 2, 'Whatsapp', '+9771234567890', 'MSG_FD5A94092C97', 'Delivery receipt received', 'INFO', 'delivered', NULL, NULL, '2026-01-01 03:01:18'),
(106, 2, 'Whatsapp', '+9779876543210', 'MSG_1A2C2229F083', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2026-01-01 04:56:08'),
(107, 2, 'Whatsapp', '+9779812345678', 'MSG_F511A381826A', 'SMS delivered and confirmed', 'INFO', 'delivered', NULL, NULL, '2026-01-01 03:35:02'),
(108, 2, 'Whatsapp', '+9779876543210', 'MSG_C2AAA950F9E1', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2026-01-01 05:39:50'),
(109, 2, 'Whatsapp', '+9779876543210', 'MSG_CFA6EFFE4FE0', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2026-01-01 13:33:53'),
(110, 2, 'Whatsapp', '+9779812345678', 'MSG_5CB58656858E', 'Initializing gateway connection', 'DEBUG', 'pending', NULL, NULL, '2025-12-31 16:15:10'),
(111, 2, 'Whatsapp', '+9771234567890', 'MSG_6AF9206A57DC', 'Invalid phone number +123', 'ERROR', 'failed', NULL, NULL, '2025-12-31 15:50:59'),
(112, 2, 'Whatsapp', '+9771234567890', 'MSG_B47A1BC20779', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2025-12-31 15:05:20'),
(113, 2, 'Whatsapp', '+9771234567890', 'MSG_4C632CB06DBA', 'Delivery receipt received', 'INFO', 'delivered', NULL, NULL, '2025-12-31 16:44:45'),
(114, 2, 'Whatsapp', '+9779812345678', 'MSG_EC7128800D6E', 'Delivery receipt received', 'INFO', 'delivered', NULL, NULL, '2025-12-31 14:46:59'),
(115, 2, 'Whatsapp', '+9779876543210', 'MSG_6A7020C6670C', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2025-12-31 09:04:04'),
(116, 2, 'Whatsapp', '+9779876543210', 'MSG_DB2678E697FA', 'Delivery receipt received', 'INFO', 'delivered', NULL, NULL, '2025-12-31 08:32:20'),
(117, 2, 'Whatsapp', '+9771234567890', 'MSG_F9265F090792', 'SMS delivered and confirmed', 'INFO', 'delivered', NULL, NULL, '2025-12-31 05:51:42'),
(118, 2, 'Whatsapp', '+9779876543210', 'MSG_2CD2F00AC0CD', 'SMS delivered and confirmed', 'INFO', 'delivered', NULL, NULL, '2025-12-31 14:01:37'),
(119, 2, 'Whatsapp', '+9779812345678', 'MSG_CA7B4DFFBF2B', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2025-12-31 09:40:22'),
(120, 2, 'Whatsapp', '+9779812345678', 'MSG_40140DECE610', 'SMS delivered and confirmed', 'INFO', 'delivered', NULL, NULL, '2025-12-30 15:49:35'),
(121, 2, 'Whatsapp', '+9779812345678', 'MSG_B2B280745F8F', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2025-12-30 06:05:18'),
(122, 2, 'Whatsapp', '+9779876543210', 'MSG_32ACD9ECC60F', 'Initializing gateway connection', 'DEBUG', 'pending', NULL, NULL, '2025-12-30 12:42:15'),
(123, 2, 'Whatsapp', '+9771234567890', 'MSG_7534A084DD3E', 'Invalid phone number +123', 'ERROR', 'failed', NULL, NULL, '2025-12-30 04:16:41'),
(124, 2, 'Whatsapp', '+9771234567890', 'MSG_951B9642FC8F', 'SMS delivered and confirmed', 'INFO', 'delivered', NULL, NULL, '2025-12-30 10:23:58'),
(125, 2, 'Whatsapp', '+9779876543210', 'MSG_9EE98F43CA7E', 'Initializing gateway connection', 'DEBUG', 'pending', NULL, NULL, '2025-12-30 10:54:02'),
(126, 2, 'Whatsapp', '+9771234567890', 'MSG_F836E254B764', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2025-12-30 05:02:07'),
(127, 2, 'Whatsapp', '+9771234567890', 'MSG_7D9EE2F5B489', 'Initializing gateway connection', 'DEBUG', 'pending', NULL, NULL, '2025-12-30 04:34:11'),
(128, 2, 'Whatsapp', '+9779876543210', 'MSG_24DB634EA27F', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2025-12-30 13:16:13'),
(129, 2, 'Whatsapp', '+9771234567890', 'MSG_93D6A8B4041D', 'Rate limit 80% reached', 'WARNING', 'sent', NULL, NULL, '2025-12-30 14:32:51'),
(130, 2, 'Whatsapp', '+9771234567890', 'MSG_437195975968', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2025-12-30 09:40:15'),
(131, 2, 'Whatsapp', '+9771234567890', 'MSG_48AAFC98589E', 'SMS delivered and confirmed', 'INFO', 'delivered', NULL, NULL, '2025-12-30 13:13:08'),
(132, 2, 'Whatsapp', '+9771234567890', 'MSG_5CD1E35B2662', 'Message queued for delivery', 'INFO', 'sent', NULL, NULL, '2025-12-30 05:39:09'),
(133, 2, 'Whatsapp', '+9771234567890', 'MSG_C637DD165DBB', 'Rate limit 80% reached', 'WARNING', 'sent', NULL, NULL, '2025-12-30 14:29:37'),
(134, 2, 'Whatsapp', '+9771234567890', 'MSG_2DC93B47E443', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2025-12-30 07:02:56'),
(135, 2, 'Whatsapp', '+9779876543210', 'MSG_06DD112ECB18', 'Message queued for delivery', 'INFO', 'sent', NULL, NULL, '2025-12-29 05:10:03'),
(136, 2, 'Whatsapp', '+9779876543210', 'MSG_B14B99E1DE59', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2025-12-29 15:33:33'),
(137, 2, 'Whatsapp', '+9771234567890', 'MSG_3F6E4E8D27F9', 'SMS delivered and confirmed', 'INFO', 'delivered', NULL, NULL, '2025-12-29 10:48:59'),
(138, 2, 'Whatsapp', '+9779812345678', 'MSG_F387553544D4', 'Connection timeout after 30s', 'ERROR', 'failed', NULL, NULL, '2025-12-29 12:58:43'),
(139, 2, 'Whatsapp', '+9779812345678', 'MSG_AB294A8B4FD5', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2025-12-29 15:04:28'),
(140, 2, 'Whatsapp', '+9771234567890', 'MSG_756CD534C7EC', 'Rate limit 80% reached', 'WARNING', 'sent', NULL, NULL, '2025-12-29 15:57:05'),
(141, 2, 'Whatsapp', '+9771234567890', 'MSG_B47C04B10919', 'Invalid phone number +123', 'ERROR', 'failed', NULL, NULL, '2025-12-29 02:22:16'),
(142, 2, 'Whatsapp', '+9779876543210', 'MSG_FB7BDA147F7E', 'Rate limit 80% reached', 'WARNING', 'sent', NULL, NULL, '2025-12-29 16:39:55'),
(143, 2, 'Whatsapp', '+9779876543210', 'MSG_A1FC775AD142', 'Initializing gateway connection', 'DEBUG', 'pending', NULL, NULL, '2025-12-29 08:29:01'),
(144, 2, 'Whatsapp', '+9771234567890', 'MSG_7A95C6D1EF1D', 'Message sent successfully to carrier', 'INFO', 'delivered', NULL, NULL, '2025-12-29 16:11:52'),
(145, 9, 'ntfy Test New', 'test', NULL, 'Sending ntfy notification', 'INFO', 'pending', NULL, '{\"recipient\":\"test\",\"topic\":\"educrm-test\"}', '2026-01-19 11:34:15'),
(146, 9, 'ntfy Test New', NULL, NULL, '[HTTP] HTTP POST request', 'DEBUG', 'pending', NULL, '{\"url\":\"http:\\/\\/localhost:8090\\/educrm-test\",\"has_body\":true,\"has_auth\":false}', '2026-01-19 11:34:15'),
(147, 9, 'ntfy Test New', NULL, NULL, '[HTTP] HTTP response received', 'DEBUG', 'pending', NULL, '{\"http_code\":0,\"has_error\":true,\"response_size\":0}', '2026-01-19 11:34:18'),
(148, 9, 'ntfy Test New', NULL, NULL, 'ntfy cURL error', 'ERROR', 'failed', 'Failed to connect to localhost port 8090: Connection refused', '{\"error\":\"Failed to connect to localhost port 8090: Connection refused\"}', '2026-01-19 11:34:18'),
(149, 8, 'ntfy Local', 'test_cli', NULL, 'Sending ntfy notification', 'INFO', 'pending', NULL, '{\"recipient\":\"test_cli\",\"topic\":\"educrm-test_cli\"}', '2026-01-19 11:38:09'),
(150, 8, 'ntfy Local', NULL, NULL, '[HTTP] HTTP POST request', 'DEBUG', 'pending', NULL, '{\"url\":\"http:\\/\\/localhost:8090\\/educrm-test_cli\",\"has_body\":true,\"has_auth\":false}', '2026-01-19 11:38:09'),
(151, 8, 'ntfy Local', NULL, NULL, '[HTTP] HTTP response received', 'DEBUG', 'pending', NULL, '{\"http_code\":0,\"has_error\":true,\"response_size\":0}', '2026-01-19 11:38:11'),
(152, 8, 'ntfy Local', NULL, NULL, 'ntfy cURL error', 'ERROR', 'failed', 'Failed to connect to localhost port 8090: Connection refused', '{\"error\":\"Failed to connect to localhost port 8090: Connection refused\"}', '2026-01-19 11:38:11'),
(153, 5, 'Test SMS Gateway', '9876543210', NULL, 'Sending SMS via Twilio', 'INFO', 'pending', NULL, '{\"recipient\":\"9876543210\"}', '2026-01-21 10:26:12'),
(154, 5, 'Test SMS Gateway', NULL, NULL, 'Formatted phone number', 'DEBUG', 'pending', NULL, '{\"original\":\"9876543210\",\"formatted\":\"+19876543210\",\"country_code\":\"+1\"}', '2026-01-21 10:26:12'),
(155, 5, 'Test SMS Gateway', NULL, NULL, 'Twilio API request', 'DEBUG', 'pending', NULL, '{\"from\":\"\",\"to\":\"+19876543210\"}', '2026-01-21 10:26:12'),
(156, 5, 'Test SMS Gateway', NULL, NULL, '[HTTP] HTTP POST request', 'DEBUG', 'pending', NULL, '{\"url\":\"https:\\/\\/api.twilio.com\\/2010-04-01\\/Accounts\\/\\/Messages.json\",\"has_body\":true,\"has_auth\":true}', '2026-01-21 10:26:12'),
(157, 5, 'Test SMS Gateway', NULL, NULL, '[HTTP] HTTP response received', 'DEBUG', 'pending', NULL, '{\"http_code\":404,\"has_error\":false,\"response_size\":167}', '2026-01-21 10:26:13'),
(158, 5, 'Test SMS Gateway', NULL, NULL, 'API error', 'ERROR', 'failed', 'The requested resource /2010-04-01/Accounts//Messages.json was not found', '{\"error\":\"The requested resource \\/2010-04-01\\/Accounts\\/\\/Messages.json was not found\",\"http_code\":404}', '2026-01-21 10:26:13'),
(159, 1, 'Pankaj Kumar Chhetry', '1234567890', NULL, 'Sending SMS via Gammu', 'INFO', 'pending', NULL, '{\"recipient\":\"1234567890\"}', '2026-01-22 05:43:33'),
(160, 1, 'Pankaj Kumar Chhetry', NULL, NULL, 'Formatted phone number', 'DEBUG', 'pending', NULL, '{\"original\":\"1234567890\",\"formatted\":\"+11234567890\",\"country_code\":\"+1\"}', '2026-01-22 05:43:33'),
(161, 1, 'Pankaj Kumar Chhetry', NULL, NULL, 'Gammu command', 'DEBUG', 'pending', NULL, '{\"cmd\":\"gammu -c \\\"C:\\\\Users\\\\DELL\\\\AppData\\\\Local\\\\Temp\\/gammu_4e09844d2586f0df783fcaa2753899f8.conf\\\" sendsms TEXT \\\"+11234567890\\\" -text \\\"Hi Test Student 987, this is a reminder for your appointment  Test Meeting  on Friday, January 23, 2026 at 10:00 AM. Location: TBD\\\" 2>&1\"}', '2026-01-22 05:43:33'),
(162, 1, 'Pankaj Kumar Chhetry', NULL, NULL, 'Gammu output', 'DEBUG', 'pending', NULL, '{\"return_code\":1,\"output\":\"\'gammu\' is not recognized as an internal or external command,\\noperable program or batch file.\"}', '2026-01-22 05:43:33'),
(163, 1, 'Pankaj Kumar Chhetry', '+11234567890', NULL, 'Gammu send failed', 'ERROR', 'failed', NULL, '{\"recipient\":\"+11234567890\",\"return_code\":1,\"output\":\"\'gammu\' is not recognized as an internal or external command,\\noperable program or batch file.\"}', '2026-01-22 05:43:33'),
(164, 1, 'Pankaj Kumar Chhetry', '1234567890', NULL, 'Sending SMS via Gammu', 'INFO', 'pending', NULL, '{\"recipient\":\"1234567890\"}', '2026-01-22 05:55:17'),
(165, 1, 'Pankaj Kumar Chhetry', NULL, NULL, 'Formatted phone number', 'DEBUG', 'pending', NULL, '{\"original\":\"1234567890\",\"formatted\":\"+11234567890\",\"country_code\":\"+1\"}', '2026-01-22 05:55:17'),
(166, 1, 'Pankaj Kumar Chhetry', NULL, NULL, 'Gammu command', 'DEBUG', 'pending', NULL, '{\"cmd\":\"gammu -c \\\"C:\\\\Users\\\\DELL\\\\AppData\\\\Local\\\\Temp\\/gammu_4e09844d2586f0df783fcaa2753899f8.conf\\\" sendsms TEXT \\\"+11234567890\\\" -text \\\"Hi Test Student 987, this is a reminder for your appointment  Far Future Debug  on Wednesday, December 12, 2029 at 12:12 PM. Location: TBD\\\" 2>&1\"}', '2026-01-22 05:55:17'),
(167, 1, 'Pankaj Kumar Chhetry', NULL, NULL, 'Gammu output', 'DEBUG', 'pending', NULL, '{\"return_code\":1,\"output\":\"\'gammu\' is not recognized as an internal or external command,\\noperable program or batch file.\"}', '2026-01-22 05:55:17'),
(168, 1, 'Pankaj Kumar Chhetry', '+11234567890', NULL, 'Gammu send failed', 'ERROR', 'failed', NULL, '{\"recipient\":\"+11234567890\",\"return_code\":1,\"output\":\"\'gammu\' is not recognized as an internal or external command,\\noperable program or batch file.\"}', '2026-01-22 05:55:17'),
(169, 1, 'Pankaj Kumar Chhetry', '9841111111', NULL, 'Sending SMS via Gammu', 'INFO', 'pending', NULL, '{\"recipient\":\"9841111111\"}', '2026-01-22 05:55:49'),
(170, 1, 'Pankaj Kumar Chhetry', NULL, NULL, 'Formatted phone number', 'DEBUG', 'pending', NULL, '{\"original\":\"9841111111\",\"formatted\":\"+19841111111\",\"country_code\":\"+1\"}', '2026-01-22 05:55:49'),
(171, 1, 'Pankaj Kumar Chhetry', NULL, NULL, 'Gammu command', 'DEBUG', 'pending', NULL, '{\"cmd\":\"gammu -c \\\"C:\\\\Users\\\\DELL\\\\AppData\\\\Local\\\\Temp\\/gammu_4e09844d2586f0df783fcaa2753899f8.conf\\\" sendsms TEXT \\\"+19841111111\\\" -text \\\"Hi Anita Rai, this is a reminder for your appointment  Counselor Test  on Sunday, November 11, 2029 at 11:11 AM. Location: TBD\\\" 2>&1\"}', '2026-01-22 05:55:49'),
(172, 1, 'Pankaj Kumar Chhetry', NULL, NULL, 'Gammu output', 'DEBUG', 'pending', NULL, '{\"return_code\":1,\"output\":\"\'gammu\' is not recognized as an internal or external command,\\noperable program or batch file.\"}', '2026-01-22 05:55:50'),
(173, 1, 'Pankaj Kumar Chhetry', '+19841111111', NULL, 'Gammu send failed', 'ERROR', 'failed', NULL, '{\"recipient\":\"+19841111111\",\"return_code\":1,\"output\":\"\'gammu\' is not recognized as an internal or external command,\\noperable program or batch file.\"}', '2026-01-22 05:55:50'),
(174, 1, 'Pankaj Kumar Chhetry', '555-0199', NULL, 'Sending SMS via Gammu', 'INFO', 'pending', NULL, '{\"recipient\":\"555-0199\"}', '2026-01-22 05:56:16'),
(175, 1, 'Pankaj Kumar Chhetry', NULL, NULL, 'Formatted phone number', 'DEBUG', 'pending', NULL, '{\"original\":\"555-0199\",\"formatted\":\"+15550199\",\"country_code\":\"+1\"}', '2026-01-22 05:56:16'),
(176, 1, 'Pankaj Kumar Chhetry', NULL, NULL, 'Gammu command', 'DEBUG', 'pending', NULL, '{\"cmd\":\"gammu -c \\\"C:\\\\Users\\\\DELL\\\\AppData\\\\Local\\\\Temp\\/gammu_4e09844d2586f0df783fcaa2753899f8.conf\\\" sendsms TEXT \\\"+15550199\\\" -text \\\"Hi Robot Test User, this is a reminder for your appointment  Inquiry Meeting  on Wednesday, October 10, 2029 at 10:10 AM. Location: TBD\\\" 2>&1\"}', '2026-01-22 05:56:16'),
(177, 1, 'Pankaj Kumar Chhetry', NULL, NULL, 'Gammu output', 'DEBUG', 'pending', NULL, '{\"return_code\":1,\"output\":\"\'gammu\' is not recognized as an internal or external command,\\noperable program or batch file.\"}', '2026-01-22 05:56:16'),
(178, 1, 'Pankaj Kumar Chhetry', '+15550199', NULL, 'Gammu send failed', 'ERROR', 'failed', NULL, '{\"recipient\":\"+15550199\",\"return_code\":1,\"output\":\"\'gammu\' is not recognized as an internal or external command,\\noperable program or batch file.\"}', '2026-01-22 05:56:16');

-- --------------------------------------------------------

--
-- Table structure for table `messaging_queue`
--

CREATE TABLE `messaging_queue` (
  `id` int(11) NOT NULL,
  `gateway_id` int(11) DEFAULT NULL,
  `campaign_id` int(11) DEFAULT NULL,
  `message_type` enum('sms','whatsapp','viber','email') NOT NULL,
  `type_id` int(11) DEFAULT NULL,
  `recipient` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `status` enum('pending','processing','sent','failed','cancelled') DEFAULT 'pending',
  `priority` int(11) DEFAULT 0,
  `scheduled_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `gateway_message_id` varchar(100) DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `max_retries` int(11) DEFAULT 3,
  `error_message` text DEFAULT NULL,
  `cost` decimal(10,4) DEFAULT 0.0000,
  `metadata` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messaging_templates`
--

CREATE TABLE `messaging_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `message_type` enum('sms','whatsapp','viber','email') NOT NULL,
  `type_id` int(11) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `event_key` varchar(50) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `variables` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `usage_count` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messaging_templates`
--

INSERT INTO `messaging_templates` (`id`, `name`, `message_type`, `type_id`, `category`, `event_key`, `subject`, `content`, `variables`, `is_active`, `usage_count`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Task Assignment Demo', 'sms', 1, '', 'task_assigned', '', 'Hi {name}, new task: {task_title}', '[]', 1, 0, 1, '2026-01-21 09:20:05', '2026-01-21 09:20:05');

--
-- Triggers `messaging_templates`
--
DELIMITER $$
CREATE TRIGGER `trg_messaging_templates_before_insert` BEFORE INSERT ON `messaging_templates` FOR EACH ROW BEGIN
    IF NEW.type_id IS NULL AND NEW.message_type IS NOT NULL THEN
        SET NEW.type_id = (
            SELECT id FROM communication_types 
            WHERE LOWER(name) = LOWER(NEW.message_type)
            LIMIT 1
        )$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_events`
--

CREATE TABLE `notification_events` (
  `id` int(11) NOT NULL,
  `event_key` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT 'general',
  `default_channels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`default_channels`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_events`
--

INSERT INTO `notification_events` (`id`, `event_key`, `name`, `description`, `category`, `default_channels`, `created_at`) VALUES
(1, 'task_assigned', 'Task Assignment', NULL, 'task', '[\"email\"]', '2026-01-21 09:05:18'),
(2, 'task_overdue', 'Task Overdue Alert', NULL, 'task', '[\"email\"]', '2026-01-21 09:05:18'),
(3, 'appointment_reminder', 'Appointment Reminder', NULL, 'appointment', '[\"email\", \"sms\"]', '2026-01-21 09:05:18'),
(4, 'welcome_email', 'New Account Welcome', NULL, 'account', '[\"email\"]', '2026-01-21 09:05:18'),
(5, 'visa_stage_update', 'Visa Stage Update', NULL, 'visa', '[\"email\", \"whatsapp\"]', '2026-01-21 09:05:18'),
(6, 'document_status', 'Document Status Change', NULL, 'visa', '[\"email\"]', '2026-01-21 09:05:18'),
(7, 'all', 'Global Preferences', NULL, 'general', NULL, '2026-01-23 09:22:18');

-- --------------------------------------------------------

--
-- Table structure for table `notification_logs`
--

CREATE TABLE `notification_logs` (
  `id` int(11) NOT NULL,
  `event_key` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `recipient` varchar(255) NOT NULL,
  `channel` varchar(20) NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `partners`
--

CREATE TABLE `partners` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` enum('university','college','agent','other') DEFAULT 'university',
  `country` varchar(100) DEFAULT NULL,
  `country_id` int(11) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `student_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','bank_transfer','online','check','other') DEFAULT 'cash',
  `transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('completed','pending','failed') DEFAULT 'completed',
  `recorded_by` int(11) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `performance_logs`
--

CREATE TABLE `performance_logs` (
  `id` int(11) NOT NULL,
  `page_url` varchar(255) DEFAULT NULL,
  `execution_time` decimal(10,4) DEFAULT NULL,
  `memory_usage` int(11) DEFAULT NULL,
  `query_count` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `priority_levels`
--

CREATE TABLE `priority_levels` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `priority_order` int(11) NOT NULL DEFAULT 0,
  `color_code` varchar(7) DEFAULT NULL COMMENT 'Hex color for UI',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `priority_levels`
--

INSERT INTO `priority_levels` (`id`, `name`, `priority_order`, `color_code`, `created_at`) VALUES
(1, 'hot', 1, '#dc2626', '2026-01-04 11:42:24'),
(2, 'warm', 2, '#f59e0b', '2026-01-04 11:42:24'),
(3, 'cold', 3, '#3b82f6', '2026-01-04 11:42:24');

-- --------------------------------------------------------

--
-- Table structure for table `push_notification_logs`
--

CREATE TABLE `push_notification_logs` (
  `id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `status` enum('pending','sent','failed','delivered') DEFAULT 'pending',
  `fcm_response` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qr_attendance_scans`
--

CREATE TABLE `qr_attendance_scans` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `scanned_at` datetime NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qr_attendance_sessions`
--

CREATE TABLE `qr_attendance_sessions` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `qr_token` varchar(64) NOT NULL,
  `qr_code_path` varchar(255) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `query_cache`
--

CREATE TABLE `query_cache` (
  `cache_key` varchar(255) NOT NULL,
  `cache_value` longtext NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`) VALUES
(5, 'accountant'),
(1, 'admin'),
(6, 'branch_manager'),
(2, 'counselor'),
(4, 'student'),
(3, 'teacher');

-- --------------------------------------------------------

--
-- Table structure for table `saved_searches`
--

CREATE TABLE `saved_searches` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `search_name` varchar(100) NOT NULL,
  `search_type` enum('inquiry','student','application','task','appointment') NOT NULL,
  `search_criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`search_criteria`)),
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scoring_rules`
--

CREATE TABLE `scoring_rules` (
  `id` int(11) NOT NULL,
  `rule_name` varchar(100) NOT NULL,
  `rule_type` enum('recency','response','value','education','engagement') NOT NULL,
  `points` int(11) NOT NULL,
  `condition_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`condition_json`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `scoring_rules`
--

INSERT INTO `scoring_rules` (`id`, `rule_name`, `rule_type`, `points`, `condition_json`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Recent Contact (< 24h)', 'recency', 20, '{\"hours\": 24}', 1, '2026-01-01 11:00:49', '2026-01-01 11:00:49'),
(2, 'Recent Contact (< 7 days)', 'recency', 10, '{\"days\": 7}', 1, '2026-01-01 11:00:49', '2026-01-01 11:00:49'),
(3, 'Old Lead (> 30 days)', 'recency', -15, '{\"days\": 30}', 1, '2026-01-01 11:00:49', '2026-01-01 11:00:49'),
(4, 'Very Old Lead (> 90 days)', 'recency', -30, '{\"days\": 90}', 1, '2026-01-01 11:00:49', '2026-01-01 11:00:49'),
(5, 'High Response Rate (>80%)', 'response', 15, '{\"rate\": 0.8}', 1, '2026-01-01 11:00:49', '2026-01-01 11:00:49'),
(6, 'Medium Response Rate (>50%)', 'response', 5, '{\"rate\": 0.5}', 1, '2026-01-01 11:00:49', '2026-01-01 11:00:49'),
(7, 'Low Response Rate (<30%)', 'response', -10, '{\"rate\": 0.3}', 1, '2026-01-01 11:00:49', '2026-01-01 11:00:49'),
(8, 'High Value Course (>$10k)', 'value', 25, '{\"min_value\": 10000}', 1, '2026-01-01 11:00:49', '2026-01-01 11:00:49'),
(9, 'Medium Value Course (>$5k)', 'value', 10, '{\"min_value\": 5000}', 1, '2026-01-01 11:00:49', '2026-01-01 11:00:49'),
(10, 'Low Value Course (<$2k)', 'value', -5, '{\"max_value\": 2000}', 1, '2026-01-01 11:00:49', '2026-01-01 11:00:49'),
(11, 'Education Level Match', 'education', 15, '{\"match\": true}', 1, '2026-01-01 11:00:49', '2026-01-01 11:00:49'),
(12, 'Overqualified', 'education', -5, '{\"overqualified\": true}', 1, '2026-01-01 11:00:49', '2026-01-01 11:00:49'),
(13, 'High Engagement (5+ interactions)', 'engagement', 20, '{\"min_interactions\": 5}', 1, '2026-01-01 11:00:49', '2026-01-01 11:00:49'),
(14, 'Medium Engagement (3+ interactions)', 'engagement', 10, '{\"min_interactions\": 3}', 1, '2026-01-01 11:00:49', '2026-01-01 11:00:49'),
(15, 'Low Engagement (<2 interactions)', 'engagement', -10, '{\"max_interactions\": 1}', 1, '2026-01-01 11:00:49', '2026-01-01 11:00:49'),
(16, 'Recent Contact (< 24h)', 'recency', 20, '{\"hours\": 24}', 1, '2026-01-01 11:03:58', '2026-01-01 11:03:58'),
(17, 'Recent Contact (< 7 days)', 'recency', 10, '{\"days\": 7}', 1, '2026-01-01 11:03:58', '2026-01-01 11:03:58'),
(18, 'Old Lead (> 30 days)', 'recency', -15, '{\"days\": 30}', 1, '2026-01-01 11:03:58', '2026-01-01 11:03:58'),
(19, 'Very Old Lead (> 90 days)', 'recency', -30, '{\"days\": 90}', 1, '2026-01-01 11:03:58', '2026-01-01 11:03:58'),
(20, 'High Response Rate (>80%)', 'response', 15, '{\"rate\": 0.8}', 1, '2026-01-01 11:03:58', '2026-01-01 11:03:58'),
(21, 'Medium Response Rate (>50%)', 'response', 5, '{\"rate\": 0.5}', 1, '2026-01-01 11:03:58', '2026-01-01 11:03:58'),
(22, 'Low Response Rate (<30%)', 'response', -10, '{\"rate\": 0.3}', 1, '2026-01-01 11:03:58', '2026-01-01 11:03:58'),
(23, 'High Value Course (>$10k)', 'value', 25, '{\"min_value\": 10000}', 1, '2026-01-01 11:03:58', '2026-01-01 11:03:58'),
(24, 'Medium Value Course (>$5k)', 'value', 10, '{\"min_value\": 5000}', 1, '2026-01-01 11:03:58', '2026-01-01 11:03:58'),
(25, 'Low Value Course (<$2k)', 'value', -5, '{\"max_value\": 2000}', 1, '2026-01-01 11:03:58', '2026-01-01 11:03:58'),
(26, 'Education Level Match', 'education', 15, '{\"match\": true}', 1, '2026-01-01 11:03:58', '2026-01-01 11:03:58'),
(27, 'Overqualified', 'education', -5, '{\"overqualified\": true}', 1, '2026-01-01 11:03:58', '2026-01-01 11:03:58'),
(28, 'High Engagement (5+ interactions)', 'engagement', 20, '{\"min_interactions\": 5}', 1, '2026-01-01 11:03:58', '2026-01-01 11:03:58'),
(29, 'Medium Engagement (3+ interactions)', 'engagement', 10, '{\"min_interactions\": 3}', 1, '2026-01-01 11:03:58', '2026-01-01 11:03:58'),
(30, 'Low Engagement (<2 interactions)', 'engagement', -10, '{\"max_interactions\": 1}', 1, '2026-01-01 11:03:58', '2026-01-01 11:03:58');

-- --------------------------------------------------------

--
-- Table structure for table `security_logs`
--

CREATE TABLE `security_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_logs`
--

INSERT INTO `security_logs` (`id`, `user_id`, `action`, `ip_address`, `user_agent`, `metadata`, `created_at`) VALUES
(16, 1, 'rate_limit_exceeded', 'unknown', 'unknown', '{\"action\":\"test_action\",\"limit\":5,\"period\":3600}', '2026-01-04 07:14:44'),
(17, 1, 'rate_limit_exceeded', 'unknown', 'unknown', '{\"action\":\"test_action\",\"limit\":5,\"period\":3600}', '2026-01-04 07:14:53'),
(21, 1, 'test_action', 'unknown', 'unknown', '[]', '2026-01-05 10:58:55');

-- --------------------------------------------------------

--
-- Table structure for table `student_documents`
--

CREATE TABLE `student_documents` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `document_type_id` int(11) DEFAULT NULL,
  `workflow_id` int(11) DEFAULT NULL,
  `status` enum('pending','uploaded','verified','rejected','not_required') DEFAULT 'pending',
  `title` varchar(100) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_documents`
--

INSERT INTO `student_documents` (`id`, `student_id`, `document_type_id`, `workflow_id`, `status`, `title`, `file_path`, `original_filename`, `file_size`, `mime_type`, `uploaded_by`, `verified_by`, `verified_at`, `rejection_reason`, `notes`, `uploaded_at`, `updated_at`) VALUES
(1, 11, 1, NULL, 'pending', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:13:19', '2026-01-05 10:13:19'),
(2, 11, 2, NULL, 'pending', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:13:19', '2026-01-05 10:13:19'),
(3, 11, 3, NULL, 'pending', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:13:19', '2026-01-05 10:13:19'),
(4, 11, 4, NULL, 'pending', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:13:19', '2026-01-05 10:13:19'),
(5, 11, 5, NULL, 'pending', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:13:19', '2026-01-05 10:13:19'),
(6, 11, 6, NULL, 'pending', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:13:19', '2026-01-05 10:13:19'),
(7, 11, 7, NULL, 'pending', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:13:19', '2026-01-05 10:13:19'),
(8, 11, 8, NULL, 'pending', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:13:19', '2026-01-05 10:13:19'),
(9, 11, 9, NULL, 'pending', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:13:19', '2026-01-05 10:13:19'),
(10, 11, 10, NULL, 'pending', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:13:19', '2026-01-05 10:13:19'),
(11, 11, 15, NULL, 'pending', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:13:19', '2026-01-05 10:13:19'),
(12, 14, 1, NULL, 'pending', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:36:58', '2026-01-05 10:36:58'),
(13, 14, 2, NULL, 'not_required', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:36:58', '2026-01-05 10:36:58'),
(14, 14, 3, NULL, 'pending', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:36:58', '2026-01-05 10:36:58'),
(15, 14, 4, NULL, 'pending', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:36:58', '2026-01-05 10:36:58'),
(16, 14, 5, NULL, 'not_required', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:36:58', '2026-01-05 10:36:58'),
(17, 14, 6, NULL, 'pending', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:36:58', '2026-01-05 10:36:58'),
(18, 14, 7, NULL, 'not_required', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:36:58', '2026-01-05 10:36:58'),
(19, 14, 8, NULL, 'not_required', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:36:58', '2026-01-05 10:36:58'),
(20, 14, 9, NULL, 'not_required', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:36:58', '2026-01-05 10:36:58'),
(21, 14, 10, NULL, 'not_required', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:36:58', '2026-01-05 10:36:58'),
(22, 14, 15, NULL, 'not_required', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:36:58', '2026-01-05 10:36:58'),
(23, 14, 1, 4, 'pending', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:38:53', '2026-01-05 10:38:53'),
(24, 14, 2, 4, 'not_required', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:38:53', '2026-01-05 10:38:53'),
(25, 14, 3, 4, 'pending', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:38:53', '2026-01-05 10:38:53'),
(26, 14, 4, 4, 'pending', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:38:53', '2026-01-05 10:38:53'),
(27, 14, 5, 4, 'not_required', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:38:53', '2026-01-05 10:38:53'),
(28, 14, 6, 4, 'pending', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:38:53', '2026-01-05 10:38:53'),
(29, 14, 7, 4, 'not_required', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:38:53', '2026-01-05 10:38:53'),
(30, 14, 8, 4, 'not_required', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:38:53', '2026-01-05 10:38:53'),
(31, 14, 9, 4, 'not_required', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:38:53', '2026-01-05 10:38:53'),
(32, 14, 10, 4, 'not_required', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:38:53', '2026-01-05 10:38:53'),
(33, 14, 15, 4, 'not_required', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 10:38:53', '2026-01-05 10:38:53'),
(34, 25, NULL, NULL, 'pending', 'Passport', 'download.php?id=1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-06 07:02:23', '2026-01-06 07:02:23'),
(36, 33, NULL, NULL, 'pending', 'Academic', 'download.php?id=3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-03 11:27:47', '2026-02-03 11:27:47'),
(43, 35, NULL, NULL, 'pending', 'Passport', 'download.php?id=10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-05 11:31:43', '2026-02-05 11:31:43'),
(44, 36, NULL, NULL, 'pending', 'Academic', 'download.php?id=11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-05 11:37:50', '2026-02-05 11:37:50'),
(45, 36, NULL, NULL, 'pending', 'Letter of Recommendation', 'download.php?id=12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-05 11:38:26', '2026-02-05 11:38:26'),
(47, 36, NULL, NULL, 'pending', 'Passport', 'download.php?id=14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-05 11:39:08', '2026-02-05 11:39:08'),
(48, 36, NULL, NULL, 'pending', 'MOI', 'download.php?id=15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-05 11:39:34', '2026-02-05 11:39:34'),
(49, 36, NULL, NULL, 'pending', 'Photo', 'download.php?id=16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-05 11:40:00', '2026-02-05 11:40:00'),
(51, 37, NULL, NULL, 'pending', 'Academic', 'download.php?id=18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-06 11:13:52', '2026-02-06 11:13:52'),
(52, 37, NULL, NULL, 'pending', 'Letter of Recommendation (collage)', 'download.php?id=19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-06 11:14:30', '2026-02-06 11:14:30'),
(53, 37, NULL, NULL, 'pending', 'Passport', 'download.php?id=20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-06 11:14:44', '2026-02-06 11:14:44'),
(54, 37, NULL, NULL, 'pending', 'Photo', 'download.php?id=21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-06 11:14:57', '2026-02-06 11:14:57'),
(55, 37, NULL, NULL, 'pending', 'MOI', 'download.php?id=22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-06 11:15:11', '2026-02-06 11:15:11'),
(56, 37, NULL, NULL, 'pending', 'IELTS Certificate', 'download.php?id=23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-06 11:15:42', '2026-02-06 11:15:42'),
(57, 37, NULL, NULL, 'pending', 'TU Equivalence', 'download.php?id=24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-06 11:16:12', '2026-02-06 11:16:12'),
(61, 36, NULL, NULL, 'pending', 'Work Experience', 'download.php?id=28', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-08 10:47:11', '2026-02-08 10:47:11'),
(64, 36, NULL, NULL, 'pending', 'Letter of Recommendation (WORK)', 'download.php?id=31', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-08 11:08:08', '2026-02-08 11:08:08'),
(65, 39, NULL, NULL, 'pending', 'Academic', 'download.php?id=32', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 07:49:49', '2026-02-09 07:49:49'),
(66, 39, NULL, NULL, 'pending', 'Passport', 'download.php?id=33', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 07:50:02', '2026-02-09 07:50:02'),
(67, 39, NULL, NULL, 'pending', 'Letter of Recommendation (collage)', 'download.php?id=34', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 07:50:23', '2026-02-09 07:50:23'),
(68, 39, NULL, NULL, 'pending', 'Letter of Recommendation (WORK)', 'download.php?id=35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 07:50:47', '2026-02-09 07:50:47'),
(69, 39, NULL, NULL, 'pending', 'IELTS Certificate', 'download.php?id=36', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 07:51:24', '2026-02-09 07:51:24'),
(70, 39, NULL, NULL, 'pending', 'Photo', 'download.php?id=37', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 07:51:38', '2026-02-09 07:51:38'),
(71, 39, NULL, NULL, 'pending', 'TU Equivalence', 'download.php?id=38', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 07:51:49', '2026-02-09 07:51:49'),
(72, 39, NULL, NULL, 'pending', 'MOI', 'download.php?id=39', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 07:52:04', '2026-02-09 07:52:04'),
(73, 37, NULL, NULL, 'pending', 'CV and work experience', 'download.php?id=40', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 07:53:27', '2026-02-09 07:53:27'),
(74, 39, NULL, NULL, 'pending', 'CV and work experience', 'download.php?id=41', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 08:26:30', '2026-02-09 08:26:30'),
(75, 34, NULL, NULL, 'pending', 'Academic', 'download.php?id=42', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 08:29:40', '2026-02-09 08:29:40'),
(76, 34, NULL, NULL, 'pending', 'Passport', 'download.php?id=43', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 08:30:36', '2026-02-09 08:30:36'),
(77, 34, NULL, NULL, 'pending', 'Photo', 'download.php?id=44', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 08:30:55', '2026-02-09 08:30:55'),
(78, 34, NULL, NULL, 'pending', 'IELTS Certificate', 'download.php?id=45', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 08:32:28', '2026-02-09 08:32:28'),
(79, 34, NULL, NULL, 'pending', 'Letter of Recommendation', 'download.php?id=46', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 08:32:58', '2026-02-09 08:32:58'),
(80, 35, NULL, NULL, 'pending', 'Academic', 'download.php?id=47', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 08:42:41', '2026-02-09 08:42:41'),
(81, 35, NULL, NULL, 'pending', 'IELTS Certificate', 'download.php?id=48', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 08:43:25', '2026-02-09 08:43:25'),
(82, 35, NULL, NULL, 'pending', 'Letter of Recommendation (collage)', 'download.php?id=49', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 08:43:41', '2026-02-09 08:43:41'),
(83, 33, NULL, NULL, 'pending', 'Passport', 'download.php?id=50', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 08:44:42', '2026-02-09 08:44:42'),
(84, 33, NULL, NULL, 'pending', 'Photo', 'download.php?id=51', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 08:45:07', '2026-02-09 08:45:07'),
(85, 33, NULL, NULL, 'pending', 'MOI', 'download.php?id=52', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 08:45:28', '2026-02-09 08:45:28'),
(86, 33, NULL, NULL, 'pending', 'Work Experience', 'download.php?id=53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 08:45:45', '2026-02-09 08:45:45'),
(87, 33, NULL, NULL, 'pending', 'NOC', 'download.php?id=54', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 08:50:53', '2026-02-09 08:50:53'),
(88, 33, NULL, NULL, 'pending', 'Verify with stamp documents', 'download.php?id=55', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 08:51:52', '2026-02-09 08:51:52'),
(89, 33, NULL, NULL, 'pending', 'Pan Card', 'download.php?id=56', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 08:52:13', '2026-02-09 08:52:13'),
(90, 33, NULL, NULL, 'pending', 'Conditional Letter of Malita International College', 'download.php?id=57', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 08:53:19', '2026-02-09 08:53:19'),
(91, 33, NULL, NULL, 'pending', 'Agreement paper Work Experience Madhusudhan Pandey', 'download.php?id=58', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 08:55:06', '2026-02-09 08:55:06'),
(92, 33, NULL, NULL, 'pending', 'Agreement paper Work Experience Mina Pandey', 'download.php?id=59', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 08:55:28', '2026-02-09 08:55:28'),
(93, 35, NULL, NULL, 'pending', 'Letter of Recommendation (WORK)', 'download.php?id=60', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-11 11:29:06', '2026-02-11 11:29:06'),
(94, 35, NULL, NULL, 'pending', 'Work Experience', 'download.php?id=61', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-11 11:30:00', '2026-02-11 11:30:00'),
(95, 33, NULL, NULL, 'pending', 'Europass CV', 'download.php?id=62', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-11 11:33:15', '2026-02-11 11:33:15'),
(100, 35, NULL, NULL, 'pending', 'CV', 'download.php?id=67', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-12 07:48:27', '2026-02-12 07:48:27'),
(101, 35, NULL, NULL, 'pending', 'Old Work Letter and LOR', 'download.php?id=68', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-12 07:48:45', '2026-02-12 07:48:45'),
(102, 34, NULL, NULL, 'pending', 'CV', 'download.php?id=69', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-12 07:50:12', '2026-02-12 07:50:12'),
(103, 34, NULL, NULL, 'pending', 'Work Experience', 'download.php?id=70', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-12 07:50:30', '2026-02-12 07:50:30'),
(104, 36, NULL, NULL, 'pending', 'CV and work experience', 'download.php?id=71', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-12 10:26:36', '2026-02-12 10:26:36'),
(105, 43, NULL, NULL, 'pending', 'Academic', 'download.php?id=72', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-12 10:38:38', '2026-02-12 10:38:38'),
(106, 43, NULL, NULL, 'pending', 'Passport', 'download.php?id=73', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-12 10:38:53', '2026-02-12 10:38:53'),
(107, 43, NULL, NULL, 'pending', 'Photo', 'download.php?id=74', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-12 10:42:23', '2026-02-12 10:42:23'),
(108, 43, NULL, NULL, 'pending', 'Letter of Recommendation (collage)', 'download.php?id=75', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-12 10:42:53', '2026-02-12 10:42:53'),
(109, 43, NULL, NULL, 'pending', 'MOI', 'download.php?id=76', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-12 10:43:48', '2026-02-12 10:43:48'),
(110, 43, NULL, NULL, 'pending', 'IELTS Certificate', 'download.php?id=77', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-12 10:44:02', '2026-02-12 10:44:02');

-- --------------------------------------------------------

--
-- Table structure for table `student_fees`
--

CREATE TABLE `student_fees` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `fee_type_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_fees`
--

INSERT INTO `student_fees` (`id`, `student_id`, `fee_type_id`, `description`, `amount`, `due_date`, `status`, `created_at`, `branch_id`) VALUES
(1, 6, 1, '', 25000.00, '2026-01-15', 'paid', '2026-01-02 08:46:46', NULL),
(2, 7, 1, '', 25000.00, '0000-00-00', 'unpaid', '2026-01-02 08:47:06', NULL),
(3, 8, 1, '', 25000.00, '0000-00-00', 'paid', '2026-01-02 08:47:22', NULL),
(4, 9, 1, '', 25000.00, '0000-00-00', 'unpaid', '2026-01-02 08:47:42', NULL),
(5, 10, 1, 'test', 20000.00, '2026-01-07', 'partial', '2026-01-04 08:20:19', NULL),
(6, 10, 2, 'test', 5000.00, '2026-01-05', 'paid', '2026-01-04 08:20:33', NULL),
(7, 9, 2, 'test', 5000.00, '2026-01-04', 'unpaid', '2026-01-04 08:28:32', NULL),
(8, 11, 2, 'test', 5000.00, '2026-01-04', 'partial', '2026-01-04 10:18:28', NULL),
(9, 16, 3, NULL, 1500.00, NULL, 'partial', '2026-01-05 11:13:42', NULL),
(10, 20, 3, NULL, 1500.00, NULL, 'partial', '2026-01-05 11:15:33', NULL),
(11, 27, 2, '', 100.00, '2026-02-01', 'paid', '2026-01-22 05:46:18', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_logs`
--

CREATE TABLE `student_logs` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL,
  `type` enum('call','email','meeting','note') DEFAULT 'note',
  `type_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_logs`
--

INSERT INTO `student_logs` (`id`, `student_id`, `author_id`, `type`, `type_id`, `message`, `created_at`) VALUES
(1, 10, 1, 'note', 7, 'Visa status updated for Australia: Doc Collection. Notes: Chronological Progress:\r\n- 2025-10-15: Test Score Achieved (IELTS 7.0)\r\n- 2025-11-01: Offer Letter Received\r\n- 2025-11-15: Fee Payment of fee made\r\n- 2025-12-01: Visa Applied - Currently waiting for results.', '2026-01-02 08:49:41'),
(2, 10, 1, 'note', 7, 'Visa status updated for Australia: Submission. Notes: Chronological Progress:\r\n- 2025-10-15: Test Score Achieved (IELTS 7.0)\r\n- 2025-11-01: Offer Letter Received\r\n- 2025-11-15: Fee Payment of fee made\r\n- 2025-12-01: Visa Applied - Currently waiting for results.', '2026-01-02 08:50:01'),
(3, 10, 1, '', NULL, 'Application for dfdsf updated to: Offer Accepted', '2026-01-04 08:09:02'),
(4, 10, 1, 'call', 5, 'please call offer accepted', '2026-01-04 08:09:26'),
(5, 10, 1, 'note', 7, 'Visa status updated for Australia: Approved. Notes: Chronological Progress:\r\n- 2025-10-15: Test Score Achieved (IELTS 7.0)\r\n- 2025-11-01: Offer Letter Received\r\n- 2025-11-15: Fee Payment of fee made\r\n- 2025-12-01: Visa Applied - Currently waiting for results.', '2026-01-04 10:14:51'),
(6, 10, 1, 'call', 5, 'please call us ur documents have been approved', '2026-01-04 10:15:34'),
(7, 10, 1, '', NULL, 'Application for dfdsf updated to: Visa Granted', '2026-01-04 10:15:50'),
(8, 13, 1, 'note', 7, 'Visa status updated for Australia: Doc Collection (Priority: urgent). Notes: All main documents verified. Ready for submission.', '2026-01-05 08:16:27'),
(9, 13, 1, 'note', 7, 'Visa status updated for Australia: Doc Collection (Priority: urgent). Notes: All documents verified. Application submitted to Australian Embassy - Week 2', '2026-01-05 08:19:25'),
(10, 13, 1, 'note', 7, 'Visa status updated for Australia: Submission (Priority: urgent). Notes: All documents verified. Application submitted to Australian Embassy - Week 2', '2026-01-05 08:19:46'),
(11, 13, 1, 'note', 7, 'Visa status updated for Australia: Interview (Priority: urgent). Notes: Embassy scheduled interview for Week 3', '2026-01-05 08:20:01'),
(12, 13, 1, 'note', 7, 'Visa status updated for Australia: Approved (Priority: urgent). Notes: Visa approved! Week 4 - Student ready for enrollment', '2026-01-05 08:20:17'),
(13, 11, 1, 'note', 7, 'Visa status updated for Australia: Doc Collection (Priority: normal). Notes: ', '2026-01-05 10:13:19'),
(14, 14, 1, 'note', 7, 'Visa status updated for Singapore: Doc Collection (Priority: normal). Notes: ', '2026-01-05 10:36:58'),
(15, 14, 1, 'note', 7, 'Visa status updated for Australia: Doc Collection (Priority: normal). Notes: ', '2026-01-05 10:38:53'),
(16, 30, 1, 'call', 5, 'please call us ur documents have been approved', '2026-02-04 10:21:06'),
(17, 35, 32, 'note', 7, '📄 Document uploaded: 1770291038_Letter of Recommandation.pdf', '2026-02-05 11:30:38'),
(18, 35, 32, 'note', 7, '📄 Document uploaded: 1770291084_Samyog Shrestha - New Work Experience.pdf', '2026-02-05 11:31:24'),
(19, 35, 32, 'note', 7, '📄 Document uploaded: 1770291103_Samyog Shrestha - Passport.pdf', '2026-02-05 11:31:43'),
(20, 34, 32, 'note', 7, '✏️ Profile updated', '2026-02-05 11:34:26'),
(21, 36, 32, 'note', 7, '🎉 Student profile created', '2026-02-05 11:36:42'),
(22, 36, 32, 'note', 7, '📧 Welcome email sent to Student ID: 36', '2026-02-05 11:36:42'),
(23, 36, 32, 'note', 7, '📄 Document uploaded: 1770291470_Nikesh Mahat-Academic.pdf', '2026-02-05 11:37:50'),
(24, 36, 32, 'note', 7, '📄 Document uploaded: 1770291506_Nikesh Mahat-Letter of Recommendation.pdf', '2026-02-05 11:38:26'),
(25, 36, 32, 'note', 7, '📄 Document uploaded: 1770291532_Work Experience for Nikesh Mahat IYC Nepal.pdf', '2026-02-05 11:38:52'),
(26, 36, 32, 'note', 7, '📄 Document uploaded: 1770291548_Nikesh Mahat-Passport.pdf', '2026-02-05 11:39:08'),
(27, 36, 32, 'note', 7, '📄 Document uploaded: 1770291574_Nikesh Mahat-MOI.pdf', '2026-02-05 11:39:34'),
(28, 36, 32, 'note', 7, '📄 Document uploaded: 1770291600_Nikesh Mahat-Photo.JPG', '2026-02-05 11:40:00'),
(29, 36, 32, 'note', 7, '📄 Document uploaded: 1770291615_Nikesh Mahat Europass CV 1.pdf', '2026-02-05 11:40:15'),
(30, 37, 32, 'note', 7, '🎉 Student profile created', '2026-02-06 11:13:14'),
(31, 37, 32, 'note', 7, '📧 Welcome email sent to Student ID: 37', '2026-02-06 11:13:14'),
(32, 37, 32, 'note', 7, '📄 Document uploaded: 1770376432_Paurakh Shah-Academic Certificate.pdf', '2026-02-06 11:13:52'),
(33, 37, 32, 'note', 7, '📄 Document uploaded: 1770376470_Paurakh Shah-Letter of recommendation.pdf', '2026-02-06 11:14:30'),
(34, 37, 32, 'note', 7, '📄 Document uploaded: 1770376484_Paurakh Shah-Passport.pdf', '2026-02-06 11:14:44'),
(35, 37, 32, 'note', 7, '📄 Document uploaded: 1770376497_Paurakh Shah-Photo.jpg', '2026-02-06 11:14:57'),
(36, 37, 32, 'note', 7, '📄 Document uploaded: 1770376511_Paurakh Shah-MOI.pdf', '2026-02-06 11:15:11'),
(37, 37, 32, 'note', 7, '📄 Document uploaded: 1770376542_Paurakh Shah - IELTS Certificate .pdf', '2026-02-06 11:15:42'),
(38, 37, 32, 'note', 7, '📄 Document uploaded: 1770376572_Paurakh Shah- T.U Equivalence.pdf', '2026-02-06 11:16:12'),
(39, 35, 32, 'note', 7, '📄 Document uploaded: 1770378722_Samyog Shrestha - CV.pdf', '2026-02-06 11:52:02'),
(40, 35, 32, 'note', 7, '📄 Document uploaded: 1770378757_Samyog Shrestha - Letter of Recommendation for YFEED Foundation.pdf', '2026-02-06 11:52:37'),
(41, 35, 32, 'note', 7, '📄 Document uploaded: 1770378771_Samyog Shrestha - New Work Experience.pdf', '2026-02-06 11:52:51'),
(42, 36, 32, 'note', 7, '📄 Document uploaded: 1770547631_Work Experience for Nikesh Mahat IYC Nepal.pdf', '2026-02-08 10:47:11'),
(43, 36, 32, 'note', 7, '📄 Document uploaded: 1770547666_Nikesh Mahat - New CV and Work Experience.pdf', '2026-02-08 10:47:46'),
(44, 37, 32, 'note', 7, '📄 Document uploaded: 1770548236_Paurakh Shah - CV and Work Experience Letter.pdf', '2026-02-08 10:57:16'),
(45, 36, 32, 'note', 7, '📄 Document uploaded: 1770548888_Nikesh Mahat Letter of Recommendation (work).pdf', '2026-02-08 11:08:08'),
(46, 39, 32, 'note', 7, '🎉 Student profile created', '2026-02-09 07:49:07'),
(47, 39, 32, 'note', 7, '📧 Welcome email sent to Student ID: 39', '2026-02-09 07:49:07'),
(48, 39, 32, 'note', 7, '📄 Document uploaded: 1770623389_Dikshant Singh -Academic Certificate.pdf', '2026-02-09 07:49:49'),
(49, 39, 32, 'note', 7, '📄 Document uploaded: 1770623402_Dikshant Singh -Passport.pdf', '2026-02-09 07:50:02'),
(50, 39, 32, 'note', 7, '📄 Document uploaded: 1770623423_Dikshant Singh - Letter of Recommendation (Collage).pdf', '2026-02-09 07:50:23'),
(51, 39, 32, 'note', 7, '📄 Document uploaded: 1770623447_Dikshant Singh - Work Exprience Letter NEW LOR .pdf', '2026-02-09 07:50:47'),
(52, 39, 32, 'note', 7, '📄 Document uploaded: 1770623484_Dikshant-IELTS Cerificate.pdf', '2026-02-09 07:51:24'),
(53, 39, 32, 'note', 7, '📄 Document uploaded: 1770623498_Dikshant-Photo.jpg', '2026-02-09 07:51:38'),
(54, 39, 32, 'note', 7, '📄 Document uploaded: 1770623509_Dikshant Singh - T.U Equivalence.pdf', '2026-02-09 07:51:49'),
(55, 39, 32, 'note', 7, '📄 Document uploaded: 1770623524_Dikshant Singh -MOI.pdf', '2026-02-09 07:52:04'),
(56, 37, 32, 'note', 7, '📄 Document uploaded: 1770623607_Paurakh Shah - CV and Work Experience Letter.pdf', '2026-02-09 07:53:27'),
(57, 39, 32, 'note', 7, '📄 Document uploaded: 1770625589_Dikshant Singh - CV and Work Experience Letter.pdf', '2026-02-09 08:26:30'),
(58, 34, 32, 'note', 7, '📄 Document uploaded: 1770625780_Saman Rokka - Academic Documents.pdf', '2026-02-09 08:29:40'),
(59, 34, 32, 'note', 7, '📄 Document uploaded: 1770625836_Passport.pdf', '2026-02-09 08:30:36'),
(60, 34, 32, 'note', 7, '📄 Document uploaded: 1770625855_Saman Rokka - Photo.jpeg', '2026-02-09 08:30:55'),
(61, 34, 32, 'note', 7, '📄 Document uploaded: 1770625948_IELTS.pdf', '2026-02-09 08:32:28'),
(62, 34, 32, 'note', 7, '📄 Document uploaded: 1770625978_Letter of Recommandation.pdf', '2026-02-09 08:32:58'),
(63, 35, 32, 'note', 7, '📄 Document uploaded: 1770626561_Academic Documents.pdf', '2026-02-09 08:42:41'),
(64, 35, 32, 'note', 7, '📄 Document uploaded: 1770626605_Samyog Shrestha - IELTS Certificate.pdf', '2026-02-09 08:43:25'),
(65, 35, 32, 'note', 7, '📄 Document uploaded: 1770626621_Letter of Recommandation (Collage).pdf', '2026-02-09 08:43:41'),
(66, 33, 32, 'note', 7, '📄 Document uploaded: 1770626682_Binayak Pandey - Passport.pdf', '2026-02-09 08:44:42'),
(67, 33, 32, 'note', 7, '📄 Document uploaded: 1770626706_Binayak Chhetri Photo.jpg', '2026-02-09 08:45:07'),
(68, 33, 32, 'note', 7, '📄 Document uploaded: 1770626728_Medium of Instruction.pdf', '2026-02-09 08:45:28'),
(69, 33, 32, 'note', 7, '📄 Document uploaded: 1770626745_Binayak Pandey Work Experience Letter.pdf', '2026-02-09 08:45:45'),
(70, 33, 32, 'note', 7, '📄 Document uploaded: 1770627053_Binayak  Pandey NOC Certificate .pdf', '2026-02-09 08:50:53'),
(71, 33, 32, 'note', 7, '📄 Document uploaded: 1770627112_All Stamp documents.pdf', '2026-02-09 08:51:52'),
(72, 33, 32, 'note', 7, '📄 Document uploaded: 1770627133_Pan Card.pdf', '2026-02-09 08:52:13'),
(73, 33, 32, 'note', 7, '📄 Document uploaded: 1770627199_CAL-for-MIC offer letter.pdf', '2026-02-09 08:53:19'),
(74, 33, 32, 'note', 7, '📄 Document uploaded: 1770627306_Work Experience Agreement paper of Madhushaudan Pandey Singh Tech Engineering Consultant Pvt.pdf', '2026-02-09 08:55:06'),
(75, 33, 32, 'note', 7, '📄 Document uploaded: 1770627328_Work Experience Agreement paper of Mina Pandey Vastushilpa Architects.pdf', '2026-02-09 08:55:28');

--
-- Triggers `student_logs`
--
DELIMITER $$
CREATE TRIGGER `trg_student_logs_before_insert` BEFORE INSERT ON `student_logs` FOR EACH ROW BEGIN
    IF NEW.type_id IS NULL AND NEW.type IS NOT NULL AND NEW.type != '' THEN
        SET NEW.type_id = (
            SELECT id FROM communication_types 
            WHERE LOWER(name) = LOWER(NEW.type)
            LIMIT 1
        )$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `student_workflow_progress`
--

CREATE TABLE `student_workflow_progress` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `current_step_id` int(11) DEFAULT NULL,
  `status` enum('not_started','in_progress','completed','on_hold','cancelled') DEFAULT 'not_started',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `submissions`
--

CREATE TABLE `submissions` (
  `id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `grade` varchar(10) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `submissions`
--

INSERT INTO `submissions` (`id`, `material_id`, `student_id`, `file_path`, `comments`, `submitted_at`, `grade`, `updated_at`) VALUES
(1, 1, 3, 'uploads/submissions/1766651589_u3_logo.jpeg', 'submited', '2025-12-25 08:33:09', '50', NULL),
(2, 2, 16, 'uploads/mock_test.pdf', 'Completed', '2026-01-05 11:13:42', 'A', NULL),
(3, 3, 20, 'uploads/mock_test.pdf', 'Completed', '2026-01-05 11:15:33', 'A', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 25, 'document_upload', 'User 25 uploaded 1767682943_LOCATION.png for Student 25', '::1', '2026-01-06 07:02:23'),
(2, 25, 'file_download', 'ID: 1', '::1', '2026-01-06 07:02:26'),
(3, 1, 'file_download', 'ID: 1', '::1', '2026-01-06 07:03:03'),
(4, 1, 'inquiry_create', 'Created inquiry ID: 12', '::1', '2026-01-22 05:31:59'),
(5, 1, 'student_create', 'Created student ID: 27', '::1', '2026-01-22 05:41:22'),
(6, 1, 'inquiry_create', 'Created inquiry ID: 13', '::1', '2026-01-22 06:35:30'),
(7, 1, 'student_create', 'Created student ID: 30', '::1', '2026-01-22 06:38:05'),
(8, 1, 'student_update', 'Updated student ID: 30', '::1', '2026-01-22 06:39:15'),
(9, 1, 'student_update', 'Updated student ID: 30', '::1', '2026-01-23 09:00:25'),
(10, 1, 'student_update', 'Updated student ID: 30', '::1', '2026-01-23 09:00:31'),
(11, 1, 'student_update', 'Updated student ID: 30', '::1', '2026-01-23 09:00:50'),
(12, 1, 'student_update', 'Updated student ID: 30', '::1', '2026-01-23 09:00:58'),
(13, 1, 'student_update', 'Updated student ID: 31', '::1', '2026-01-23 09:08:22'),
(14, 1, 'student_update', 'Updated student ID: 31', '::1', '2026-01-23 09:08:58'),
(15, 1, 'student_update', 'Updated student ID: 31', '::1', '2026-01-23 09:09:07'),
(16, 1, 'student_update', 'Updated student ID: 31', '::1', '2026-01-23 09:09:45'),
(17, 1, 'student_update', 'Updated student ID: 31', '::1', '2026-01-23 09:10:06'),
(24, 1, 'student_update', 'Updated student ID: 30', '::1', '2026-01-23 09:22:50'),
(25, 1, 'student_update', 'Updated student ID: 30', '::1', '2026-01-23 09:22:58'),
(26, 1, 'student_update', 'Updated student ID: 30', '::1', '2026-01-25 05:42:47'),
(27, 1, 'student_update', 'Updated student ID: 30', '::1', '2026-01-25 05:42:52'),
(28, 32, 'student_create', 'Created student ID: 33', '127.0.0.1', '2026-02-03 10:06:31'),
(29, 1, 'student_update', 'Updated student ID: 33', '127.0.0.1', '2026-02-03 10:40:14'),
(30, 1, 'student_update', 'Updated student ID: 33', '127.0.0.1', '2026-02-03 10:40:37'),
(31, 32, 'student_create', 'Created student ID: 34', '127.0.0.1', '2026-02-03 10:51:13'),
(32, 32, 'student_update', 'Updated student ID: 33', '127.0.0.1', '2026-02-03 10:51:36'),
(33, 32, 'document_upload', 'User 32 uploaded 1770118014_Binayak Pandey - Passport.pdf for Student 33', '127.0.0.1', '2026-02-03 11:26:54'),
(34, 32, 'file_download', 'ID: 2', '127.0.0.1', '2026-02-03 11:27:05'),
(35, 32, 'file_download', 'ID: 2', '127.0.0.1', '2026-02-03 11:27:23'),
(36, 32, 'document_upload', 'User 32 uploaded 1770118067_Acedemic Certificate of Binayak Pandey.pdf for Student 33', '127.0.0.1', '2026-02-03 11:27:47'),
(37, 32, 'file_download', 'ID: 3', '127.0.0.1', '2026-02-03 11:27:49'),
(38, 1, 'file_download', 'ID: 2', '127.0.0.1', '2026-02-04 10:19:41'),
(39, 32, 'student_create', 'Created student ID: 35', '127.0.0.1', '2026-02-05 11:07:35'),
(40, 32, 'document_upload', 'User 32 uploaded 1770289759_SLCE Grade 10 Certificate, Character and Gradesheet.pdf for Student 35', '127.0.0.1', '2026-02-05 11:09:19'),
(41, 32, 'document_upload', 'User 32 uploaded 1770289774_+2 Certificate, Transcripit, Provisional and Migration Certificate.pdf for Student 35', '127.0.0.1', '2026-02-05 11:09:34'),
(42, 32, 'document_upload', 'User 32 uploaded 1770289786_Letter of Recommandation.pdf for Student 35', '127.0.0.1', '2026-02-05 11:09:46'),
(43, 32, 'document_upload', 'User 32 uploaded 1770290604_Samyog Shrestha - CV.pdf for Student 35', '127.0.0.1', '2026-02-05 11:23:24'),
(44, 32, 'document_upload', 'User 32 uploaded 1770291038_Letter of Recommandation.pdf for Student 35', '127.0.0.1', '2026-02-05 11:30:38'),
(45, 32, 'document_upload', 'User 32 uploaded 1770291084_Samyog Shrestha - New Work Experience.pdf for Student 35', '127.0.0.1', '2026-02-05 11:31:24'),
(46, 32, 'document_upload', 'User 32 uploaded 1770291103_Samyog Shrestha - Passport.pdf for Student 35', '127.0.0.1', '2026-02-05 11:31:43'),
(47, 32, 'student_update', 'Updated student ID: 34', '127.0.0.1', '2026-02-05 11:34:26'),
(48, 32, 'student_create', 'Created student ID: 36', '127.0.0.1', '2026-02-05 11:36:42'),
(49, 32, 'notification_sent', 'Welcome email sent to Student ID: 36', '127.0.0.1', '2026-02-05 11:36:42'),
(50, 32, 'document_upload', 'User 32 uploaded 1770291470_Nikesh Mahat-Academic.pdf for Student 36', '127.0.0.1', '2026-02-05 11:37:50'),
(51, 32, 'document_upload', 'User 32 uploaded 1770291506_Nikesh Mahat-Letter of Recommendation.pdf for Student 36', '127.0.0.1', '2026-02-05 11:38:26'),
(52, 32, 'document_upload', 'User 32 uploaded 1770291532_Work Experience for Nikesh Mahat IYC Nepal.pdf for Student 36', '127.0.0.1', '2026-02-05 11:38:52'),
(53, 32, 'document_upload', 'User 32 uploaded 1770291548_Nikesh Mahat-Passport.pdf for Student 36', '127.0.0.1', '2026-02-05 11:39:08'),
(54, 32, 'document_upload', 'User 32 uploaded 1770291574_Nikesh Mahat-MOI.pdf for Student 36', '127.0.0.1', '2026-02-05 11:39:34'),
(55, 32, 'document_upload', 'User 32 uploaded 1770291600_Nikesh Mahat-Photo.JPG for Student 36', '127.0.0.1', '2026-02-05 11:40:00'),
(56, 32, 'document_upload', 'User 32 uploaded 1770291615_Nikesh Mahat Europass CV 1.pdf for Student 36', '127.0.0.1', '2026-02-05 11:40:15'),
(57, 1, 'file_download', 'ID: 9', '127.0.0.1', '2026-02-06 06:28:40'),
(58, 1, 'file_download', 'ID: 7', '127.0.0.1', '2026-02-06 06:29:04'),
(59, 1, 'file_download', 'ID: 4', '127.0.0.1', '2026-02-06 06:57:47'),
(60, 1, 'file_download', 'ID: 6', '127.0.0.1', '2026-02-06 06:57:55'),
(61, 1, 'file_download', 'ID: 7', '127.0.0.1', '2026-02-06 06:58:23'),
(62, 1, 'file_download', 'ID: 6', '127.0.0.1', '2026-02-06 06:58:40'),
(63, 1, 'file_download', 'ID: 6', '127.0.0.1', '2026-02-06 07:27:09'),
(64, 1, 'file_download', 'ID: 7', '127.0.0.1', '2026-02-06 07:58:46'),
(65, 32, 'student_create', 'Created student ID: 37', '127.0.0.1', '2026-02-06 11:13:14'),
(66, 32, 'notification_sent', 'Welcome email sent to Student ID: 37', '127.0.0.1', '2026-02-06 11:13:14'),
(67, 32, 'document_upload', 'User 32 uploaded 1770376432_Paurakh Shah-Academic Certificate.pdf for Student 37', '127.0.0.1', '2026-02-06 11:13:52'),
(68, 32, 'document_upload', 'User 32 uploaded 1770376470_Paurakh Shah-Letter of recommendation.pdf for Student 37', '127.0.0.1', '2026-02-06 11:14:30'),
(69, 32, 'document_upload', 'User 32 uploaded 1770376484_Paurakh Shah-Passport.pdf for Student 37', '127.0.0.1', '2026-02-06 11:14:44'),
(70, 32, 'document_upload', 'User 32 uploaded 1770376497_Paurakh Shah-Photo.jpg for Student 37', '127.0.0.1', '2026-02-06 11:14:57'),
(71, 32, 'document_upload', 'User 32 uploaded 1770376511_Paurakh Shah-MOI.pdf for Student 37', '127.0.0.1', '2026-02-06 11:15:11'),
(72, 32, 'document_upload', 'User 32 uploaded 1770376542_Paurakh Shah - IELTS Certificate .pdf for Student 37', '127.0.0.1', '2026-02-06 11:15:42'),
(73, 32, 'document_upload', 'User 32 uploaded 1770376572_Paurakh Shah- T.U Equivalence.pdf for Student 37', '127.0.0.1', '2026-02-06 11:16:12'),
(74, NULL, 'file_download', 'ID: 11', '127.0.0.1', '2026-02-06 11:17:47'),
(75, 32, 'document_upload', 'User 32 uploaded 1770378722_Samyog Shrestha - CV.pdf for Student 35', '127.0.0.1', '2026-02-06 11:52:02'),
(76, 32, 'document_upload', 'User 32 uploaded 1770378757_Samyog Shrestha - Letter of Recommendation for YFEED Foundation.pdf for Student 35', '127.0.0.1', '2026-02-06 11:52:37'),
(77, 32, 'document_upload', 'User 32 uploaded 1770378771_Samyog Shrestha - New Work Experience.pdf for Student 35', '127.0.0.1', '2026-02-06 11:52:51'),
(78, 32, 'document_upload', 'User 32 uploaded 1770547631_Work Experience for Nikesh Mahat IYC Nepal.pdf for Student 36', '127.0.0.1', '2026-02-08 10:47:11'),
(79, 32, 'document_upload', 'User 32 uploaded 1770547666_Nikesh Mahat - New CV and Work Experience.pdf for Student 36', '127.0.0.1', '2026-02-08 10:47:46'),
(80, 32, 'document_upload', 'User 32 uploaded 1770548236_Paurakh Shah - CV and Work Experience Letter.pdf for Student 37', '127.0.0.1', '2026-02-08 10:57:16'),
(81, 32, 'document_upload', 'User 32 uploaded 1770548888_Nikesh Mahat Letter of Recommendation (work).pdf for Student 36', '127.0.0.1', '2026-02-08 11:08:08'),
(82, 32, 'file_download', 'ID: 12', '127.0.0.1', '2026-02-08 11:08:12'),
(83, 32, 'file_download', 'ID: 25', '127.0.0.1', '2026-02-08 11:08:45'),
(84, 32, 'student_create', 'Created student ID: 39', '127.0.0.1', '2026-02-09 07:49:07'),
(85, 32, 'notification_sent', 'Welcome email sent to Student ID: 39', '127.0.0.1', '2026-02-09 07:49:07'),
(86, 32, 'document_upload', 'User 32 uploaded 1770623389_Dikshant Singh -Academic Certificate.pdf for Student 39', '127.0.0.1', '2026-02-09 07:49:49'),
(87, 32, 'document_upload', 'User 32 uploaded 1770623402_Dikshant Singh -Passport.pdf for Student 39', '127.0.0.1', '2026-02-09 07:50:02'),
(88, 32, 'document_upload', 'User 32 uploaded 1770623423_Dikshant Singh - Letter of Recommendation (Collage).pdf for Student 39', '127.0.0.1', '2026-02-09 07:50:23'),
(89, 32, 'document_upload', 'User 32 uploaded 1770623447_Dikshant Singh - Work Exprience Letter NEW LOR .pdf for Student 39', '127.0.0.1', '2026-02-09 07:50:47'),
(90, 32, 'document_upload', 'User 32 uploaded 1770623484_Dikshant-IELTS Cerificate.pdf for Student 39', '127.0.0.1', '2026-02-09 07:51:24'),
(91, 32, 'document_upload', 'User 32 uploaded 1770623498_Dikshant-Photo.jpg for Student 39', '127.0.0.1', '2026-02-09 07:51:38'),
(92, 32, 'document_upload', 'User 32 uploaded 1770623509_Dikshant Singh - T.U Equivalence.pdf for Student 39', '127.0.0.1', '2026-02-09 07:51:49'),
(93, 32, 'document_upload', 'User 32 uploaded 1770623524_Dikshant Singh -MOI.pdf for Student 39', '127.0.0.1', '2026-02-09 07:52:04'),
(94, 32, 'document_upload', 'User 32 uploaded 1770623607_Paurakh Shah - CV and Work Experience Letter.pdf for Student 37', '127.0.0.1', '2026-02-09 07:53:27'),
(95, 32, 'document_upload', 'User 32 uploaded 1770625589_Dikshant Singh - CV and Work Experience Letter.pdf for Student 39', '127.0.0.1', '2026-02-09 08:26:30'),
(96, 32, 'document_upload', 'User 32 uploaded 1770625780_Saman Rokka - Academic Documents.pdf for Student 34', '127.0.0.1', '2026-02-09 08:29:40'),
(97, 32, 'file_download', 'ID: 42', '127.0.0.1', '2026-02-09 08:29:58'),
(98, 32, 'document_upload', 'User 32 uploaded 1770625836_Passport.pdf for Student 34', '127.0.0.1', '2026-02-09 08:30:36'),
(99, 32, 'document_upload', 'User 32 uploaded 1770625855_Saman Rokka - Photo.jpeg for Student 34', '127.0.0.1', '2026-02-09 08:30:55'),
(100, 32, 'document_upload', 'User 32 uploaded 1770625948_IELTS.pdf for Student 34', '127.0.0.1', '2026-02-09 08:32:28'),
(101, 32, 'document_upload', 'User 32 uploaded 1770625978_Letter of Recommandation.pdf for Student 34', '127.0.0.1', '2026-02-09 08:32:58'),
(102, 32, 'document_upload', 'User 32 uploaded 1770626561_Academic Documents.pdf for Student 35', '127.0.0.1', '2026-02-09 08:42:41'),
(103, 32, 'document_upload', 'User 32 uploaded 1770626605_Samyog Shrestha - IELTS Certificate.pdf for Student 35', '127.0.0.1', '2026-02-09 08:43:25'),
(104, 32, 'document_upload', 'User 32 uploaded 1770626621_Letter of Recommandation (Collage).pdf for Student 35', '127.0.0.1', '2026-02-09 08:43:41'),
(105, 32, 'file_download', 'ID: 3', '127.0.0.1', '2026-02-09 08:44:06'),
(106, 32, 'document_upload', 'User 32 uploaded 1770626682_Binayak Pandey - Passport.pdf for Student 33', '127.0.0.1', '2026-02-09 08:44:42'),
(107, 32, 'document_upload', 'User 32 uploaded 1770626706_Binayak Chhetri Photo.jpg for Student 33', '127.0.0.1', '2026-02-09 08:45:07'),
(108, 32, 'document_upload', 'User 32 uploaded 1770626728_Medium of Instruction.pdf for Student 33', '127.0.0.1', '2026-02-09 08:45:28'),
(109, 32, 'document_upload', 'User 32 uploaded 1770626745_Binayak Pandey Work Experience Letter.pdf for Student 33', '127.0.0.1', '2026-02-09 08:45:45'),
(110, 32, 'document_upload', 'User 32 uploaded 1770627053_Binayak  Pandey NOC Certificate .pdf for Student 33', '127.0.0.1', '2026-02-09 08:50:53'),
(111, 32, 'document_upload', 'User 32 uploaded 1770627112_All Stamp documents.pdf for Student 33', '127.0.0.1', '2026-02-09 08:51:52'),
(112, 32, 'document_upload', 'User 32 uploaded 1770627133_Pan Card.pdf for Student 33', '127.0.0.1', '2026-02-09 08:52:13'),
(113, 32, 'document_upload', 'User 32 uploaded 1770627199_CAL-for-MIC offer letter.pdf for Student 33', '127.0.0.1', '2026-02-09 08:53:19'),
(114, 32, 'document_upload', 'User 32 uploaded 1770627306_Work Experience Agreement paper of Madhushaudan Pandey Singh Tech Engineering Consultant Pvt.pdf for Student 33', '127.0.0.1', '2026-02-09 08:55:06'),
(115, 32, 'document_upload', 'User 32 uploaded 1770627328_Work Experience Agreement paper of Mina Pandey Vastushilpa Architects.pdf for Student 33', '127.0.0.1', '2026-02-09 08:55:28'),
(116, 1, 'user_delete', 'Deleted user ID: 38', '127.0.0.1', '2026-02-11 09:29:14'),
(117, 32, 'document_upload', 'User 32 uploaded 1770809346_Samyog Shrestha -  Letter of Recommendation (WORK).pdf for Student 35', '127.0.0.1', '2026-02-11 11:29:06'),
(118, 32, 'document_upload', 'User 32 uploaded 1770809400_Samyog Shrestha - Work Experience YCN.pdf for Student 35', '127.0.0.1', '2026-02-11 11:30:00'),
(119, 32, 'document_upload', 'User 32 uploaded 1770809595_Europass CV.pdf for Student 33', '127.0.0.1', '2026-02-11 11:33:15'),
(120, 32, 'file_download', 'ID: 62', '127.0.0.1', '2026-02-11 14:51:00'),
(121, 32, 'student_create', 'Created student ID: 42', '127.0.0.1', '2026-02-11 14:55:53'),
(122, 32, 'document_upload', 'User 32 uploaded 1770882327_Samyog Shrestha - New  Work Experience YCN.pdf for Student 34', '127.0.0.1', '2026-02-12 07:45:27'),
(123, 32, 'document_upload', 'User 32 uploaded 1770882345_Samyog Shrestha - CV.pdf for Student 34', '127.0.0.1', '2026-02-12 07:45:45'),
(124, 32, 'document_upload', 'User 32 uploaded 1770882372_Samyog Shrestha -  New Letter of Recommendation (WORK).pdf for Student 34', '127.0.0.1', '2026-02-12 07:46:12'),
(125, 32, 'document_upload', 'User 32 uploaded 1770882428_Old Work Experience Letter Recommendation Letter V guys.pdf for Student 34', '127.0.0.1', '2026-02-12 07:47:08'),
(126, 32, 'file_download', 'ID: 60', '127.0.0.1', '2026-02-12 07:47:54'),
(127, 32, 'file_download', 'ID: 61', '127.0.0.1', '2026-02-12 07:48:04'),
(128, 32, 'document_upload', 'User 32 uploaded 1770882507_Samyog Shrestha - CV.pdf for Student 35', '127.0.0.1', '2026-02-12 07:48:27'),
(129, 32, 'document_upload', 'User 32 uploaded 1770882525_Old Work Experience Letter Recommendation Letter V guys.pdf for Student 35', '127.0.0.1', '2026-02-12 07:48:45'),
(130, 32, 'file_download', 'ID: 63', '127.0.0.1', '2026-02-12 07:49:21'),
(131, 32, 'file_download', 'ID: 64', '127.0.0.1', '2026-02-12 07:49:29'),
(132, 32, 'file_download', 'ID: 45', '127.0.0.1', '2026-02-12 07:49:38'),
(133, 32, 'file_download', 'ID: 46', '127.0.0.1', '2026-02-12 07:49:46'),
(134, 32, 'document_upload', 'User 32 uploaded 1770882612_Europass CV.pdf for Student 34', '127.0.0.1', '2026-02-12 07:50:12'),
(135, 32, 'document_upload', 'User 32 uploaded 1770882630_Work Exprience Saman Rokka Alka Pharmacy.pdf for Student 34', '127.0.0.1', '2026-02-12 07:50:30'),
(136, 32, 'student_create', 'Created student ID: 43', '127.0.0.1', '2026-02-12 07:55:46'),
(137, 1, 'student_update', 'Updated student ID: 43', '127.0.0.1', '2026-02-12 09:02:12'),
(138, 1, 'student_delete', 'Deleted student ID: 42', '127.0.0.1', '2026-02-12 09:03:29'),
(139, 32, 'document_upload', 'User 32 uploaded 1770891996_Nikesh Mahat - New CV and Work Experience.pdf for Student 36', '127.0.0.1', '2026-02-12 10:26:36'),
(140, 32, 'document_upload', 'User 32 uploaded 1770892718_Hem Raj Bhandari -Academic.pdf for Student 43', '127.0.0.1', '2026-02-12 10:38:38'),
(141, 32, 'document_upload', 'User 32 uploaded 1770892733_Hem Raj Bhandari-Passport.pdf for Student 43', '127.0.0.1', '2026-02-12 10:38:53'),
(142, 32, 'document_upload', 'User 32 uploaded 1770892943_Hemraj Bhandari-Photo.pdf for Student 43', '127.0.0.1', '2026-02-12 10:42:23'),
(143, 32, 'document_upload', 'User 32 uploaded 1770892973_Hemraj Bhndari - Letter of Recommandation.pdf for Student 43', '127.0.0.1', '2026-02-12 10:42:53'),
(144, 32, 'document_upload', 'User 32 uploaded 1770893028_Hem Raj Bhandari- Meduim of Instructaion.pdf for Student 43', '127.0.0.1', '2026-02-12 10:43:48'),
(145, 32, 'document_upload', 'User 32 uploaded 1770893042_Hem Raj Bhandari -Ielts.pdf for Student 43', '127.0.0.1', '2026-02-12 10:44:02');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','int','boolean','json') DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES
(1, 'eod_report_time', '18:00', 'string', 'End of Day report generation time (HH:MM)', '2025-12-31 10:40:10'),
(2, 'eod_report_enabled', 'false', 'boolean', 'Enable automated EOD reports', '2025-12-31 10:40:10'),
(3, 'eod_report_recipients', '[]', 'json', 'Email addresses for EOD reports', '2025-12-31 10:40:10'),
(4, 'lead_scoring_enabled', 'true', 'boolean', 'Enable automatic lead scoring', '2025-12-31 10:40:10'),
(5, 'appointment_reminder_hours', '24', 'int', 'Hours before appointment to send reminder', '2025-12-31 10:40:10'),
(6, 'task_overdue_notification', 'true', 'boolean', 'Send notifications for overdue tasks', '2025-12-31 10:40:10'),
(8, 'messaging_last_reset', '2026-01-06', 'string', NULL, '2026-01-06 11:03:09');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `related_entity_type` enum('inquiry','student','application','class','general') NOT NULL DEFAULT 'general',
  `related_entity_id` int(11) DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `due_date` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `title`, `description`, `assigned_to`, `created_by`, `related_entity_type`, `related_entity_id`, `priority`, `status`, `due_date`, `completed_at`, `created_at`, `updated_at`, `branch_id`) VALUES
(1, 'Test Task', 'Testing icon buttons', 1, 1, 'general', NULL, 'medium', 'pending', NULL, NULL, '2026-01-02 10:00:22', '2026-01-02 10:00:22', NULL),
(2, 'Test Notification', '', 1, 1, 'general', NULL, 'medium', 'pending', NULL, NULL, '2026-01-21 09:21:35', '2026-01-21 09:21:35', NULL),
(3, 'Verification Task', '', 1, 1, 'general', NULL, 'medium', 'pending', '2026-01-22 10:00:00', NULL, '2026-01-21 09:23:21', '2026-01-21 09:23:21', NULL),
(4, 'Final Verification Task', '', 1, 1, 'general', NULL, 'medium', 'pending', '0000-00-00 00:00:00', NULL, '2026-01-21 09:28:23', '2026-01-21 09:28:23', NULL),
(5, 'Final Verification Task', '', 1, 1, 'general', NULL, 'medium', 'pending', '2026-01-22 10:00:00', NULL, '2026-01-21 09:28:48', '2026-01-21 09:28:48', NULL),
(6, 'Final Verification Task', '', 1, 1, 'general', NULL, 'medium', 'pending', '2026-01-22 10:00:00', NULL, '2026-01-21 09:30:34', '2026-01-21 09:30:34', NULL),
(7, 'Unified Task', '', 1, 1, 'general', NULL, 'medium', 'pending', '2026-01-22 10:00:00', NULL, '2026-01-21 10:06:40', '2026-01-21 10:06:40', NULL),
(8, 'Verification Task', '', 1, 1, 'general', NULL, 'medium', 'pending', NULL, NULL, '2026-01-21 10:15:47', '2026-01-21 10:15:47', NULL),
(9, 'Retry Task', '', 1, 1, 'general', NULL, 'medium', 'pending', NULL, NULL, '2026-01-21 10:20:17', '2026-01-21 10:20:17', NULL),
(10, 'Retry Task 2', '', 1, 1, 'general', NULL, 'medium', 'pending', NULL, NULL, '2026-01-21 10:23:26', '2026-01-21 10:23:26', NULL),
(11, 'Retry Task 3', '', 1, 1, 'general', NULL, 'medium', 'pending', NULL, NULL, '2026-01-21 10:29:59', '2026-01-21 10:29:59', NULL),
(12, 'Debug Task', '', 1, 1, 'general', NULL, 'medium', 'pending', NULL, NULL, '2026-01-21 10:34:05', '2026-01-21 10:34:05', NULL),
(13, 'Call Robot Test User', '', 1, 1, 'general', NULL, 'high', 'pending', '1970-01-02 12:00:00', NULL, '2026-01-22 05:34:59', '2026-01-22 05:34:59', NULL),
(14, 'tes', 'test', 1, 1, 'general', NULL, 'medium', 'pending', '2026-01-22 16:26:00', NULL, '2026-01-22 05:37:44', '2026-01-22 05:37:44', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `test_scores`
--

CREATE TABLE `test_scores` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `test_type` enum('IELTS','PTE','SAT','TOEFL') NOT NULL,
  `test_type_id` int(11) DEFAULT NULL,
  `overall_score` decimal(3,1) NOT NULL,
  `listening` decimal(3,1) DEFAULT NULL,
  `reading` decimal(3,1) DEFAULT NULL,
  `writing` decimal(3,1) DEFAULT NULL,
  `speaking` decimal(3,1) DEFAULT NULL,
  `test_date` date DEFAULT NULL,
  `report_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `test_types`
--

CREATE TABLE `test_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `has_section_scores` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_types`
--

INSERT INTO `test_types` (`id`, `name`, `full_name`, `has_section_scores`, `created_at`) VALUES
(1, 'IELTS', 'International English Language Testing System', 1, '2026-01-04 11:42:24'),
(2, 'PTE', 'Pearson Test of English', 1, '2026-01-04 11:42:24'),
(3, 'SAT', 'Scholastic Assessment Test', 0, '2026-01-04 11:42:24'),
(4, 'TOEFL', 'Test of English as a Foreign Language', 1, '2026-01-04 11:42:24'),
(5, 'GRE', 'Graduate Record Examination', 0, '2026-01-04 11:42:24'),
(6, 'GMAT', 'Graduate Management Admission Test', 0, '2026-01-04 11:42:24');

-- --------------------------------------------------------

--
-- Table structure for table `university_applications`
--

CREATE TABLE `university_applications` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `partner_id` int(11) DEFAULT NULL,
  `university_name` varchar(150) NOT NULL,
  `course_name` varchar(150) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `country_id` int(11) DEFAULT NULL,
  `status` enum('applied','offer_received','offer_accepted','visa_lodged','visa_granted','rejected') DEFAULT 'applied',
  `status_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `university_applications`
--

INSERT INTO `university_applications` (`id`, `student_id`, `partner_id`, `university_name`, `course_name`, `country`, `country_id`, `status`, `status_id`, `notes`, `created_at`, `updated_at`, `branch_id`) VALUES
(1, 10, NULL, 'dfdsf', 'sdfsdf', 'USA', 2, 'visa_granted', 5, 'test', '2026-01-04 08:04:17', '2026-01-04 11:44:11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','counselor','teacher','student') NOT NULL DEFAULT 'student',
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `country_id` int(11) DEFAULT NULL,
  `education_level_id` int(11) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `education_level` varchar(50) DEFAULT NULL,
  `passport_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_token` varchar(100) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `two_factor_secret` varchar(32) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `phone`, `address`, `country_id`, `education_level_id`, `country`, `education_level`, `passport_number`, `created_at`, `reset_token`, `token_expiry`, `deleted_at`, `two_factor_secret`, `two_factor_enabled`, `last_login_at`, `last_login_ip`, `branch_id`, `updated_at`) VALUES
(1, 'System Admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '9876543210', NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-17 11:44:07', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(2, 'Teacher 1', 'teacher1@example.com', '$2y$10$XQha2GaIv2duRgTAZWxZVOFXKK2p4Kc.I3JRYZlp8zA6bFtVqdKua', 'student', '465456465', NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-25 08:24:49', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(3, 'Student 1', 'student@example.com', '$2y$10$G7ui6LjKhS6gFaLy33EJ9e.TjYkOpknT3g2D9VLww/4798ePkgtSK', 'student', '15156', NULL, 1, 1, 'AUS', 'High School', '465465A', '2025-12-25 08:26:31', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(4, 'Student 2', 'student2@example.com', '$2y$10$aTWRVv4o8xhQoJ97qX8CJOMtBDzapOSYkADJ14pLqDspyKJkTRFHC', 'student', '456465', NULL, 2, 1, 'US', 'High School', '564564', '2025-12-25 08:26:54', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(5, 'Ramesh Sharma', 'teacher@educrm.local', '$2y$10$mRldG8rnKQ9yCOHLfftHmuAoELc1PdDwkwsIt2Xrmg680HCm3aTI6', 'student', '9800000000', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-02 08:33:11', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(6, 'Sita Thapa', 'sita@test.com', '$2y$10$0Uhac4SrCGJvCDBGLp7kW.tX2mVD6BsF1EoLQXDK7d6V0CTzDQz4O', 'student', '', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-02 08:35:38', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(7, 'Ram Gurung', 'ram@test.com', '$2y$10$lwYeURDc3OyfAlwM62H1h.CXDI4Y0VpD2gIbCLKQJBWJCb/GgUPk6', 'student', '', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-02 08:35:53', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(8, 'Maya KC', 'maya@test.com', '$2y$10$EtNdSd/SismYUEZDWPKyreOAeoBmRzTVM9PvEey.HVImIZLZRLdr2', 'student', '', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-02 08:36:07', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(9, 'Hari Basnet', 'hari@test.com', '$2y$10$TF6zgI58Yf80ye9hamdtCeABLylklBY3/2gKXMB1JfsISAcoqfizm', 'student', '', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-02 08:36:22', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(10, 'Anita Rai', 'anita@test.com', '$2y$10$OzesXbJ.Hm.T807w/9nst.kuxWJav9xPI1CD.vtp3/fto/rJLyFsW', 'student', '9841111111', NULL, 1, 5, 'Australia', 'Bachelor Completed', NULL, '2026-01-02 08:49:21', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(11, 'Bir Bahadur Thapa', 'bir@dfidsafl.com', '$2y$10$TuK6qbFP8oYuyBhR8RXmjeZlB4qFjZ1D6ik7W8jO9ZOifYXKApiaW', 'student', '56456465', NULL, 7, 1, 'Japan', 'High School', 'PA132456', '2026-01-04 10:13:06', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(12, 'tesst', 'fsdf@fsdaf.com', '$2y$10$NSr9O2QN/bcJ36skv7CTu.EQbxoh4gCr9GhQLXwXzDWyRzaVgxzT2', 'student', '12321321', NULL, 1, 1, NULL, NULL, NULL, '2026-01-05 04:27:38', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(13, 'Sarah Johnson', 'sarah.johnson@gmail.com', '$2y$10$jxAo9Zh.ipNKppXJOd8h3ui0SJ9iNBZwhGppibXGtrD2nldcDz8Ta', 'student', '+977-9841234567', NULL, 1, 4, NULL, NULL, NULL, '2026-01-05 08:12:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(14, 'pppp', 'dsfdsfd@2w4e324', '$2y$10$ejaNlg2MK9E.EkzK8rU2ouTDGTpvq/Nr4dLtQax6/QbrlkYXYuw3e', 'student', '324234', NULL, 12, 1, NULL, NULL, '34234', '2026-01-05 10:34:46', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(15, 'xxx', 'sdfasd@efdsf', '$2y$10$lqZG/R7L3AbyYqFZKCsI0OJSfTfk/9nBIatXdxdCDby2980Er3cGC', 'student', '324324', NULL, 3, 1, NULL, NULL, NULL, '2026-01-05 10:44:08', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(16, 'John Doe 1767611622', 'john.doe.1767611622@test.com', '$2y$10$klaVc6ia8AufLWqTXubRlOrCy7RL7IPBxrFyeKnGC5lfpns.gB/Qu', 'student', '1234567890', NULL, 4, NULL, 'Canada', 'Bachelor', NULL, '2026-01-05 11:13:42', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(17, 'System Teacher', 'test_teacher_sys@example.com', '$2y$10$NNi9EwDr3RgbbJjX4QIpF.NnrAWRgvWU0uWiO76QDB926sFNwl8zu', 'teacher', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 11:13:42', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(18, 'Test Counselor', 'counselor.1767611622@example.com', '$2y$10$37hMxt4J4i3Yi7lJPArs3ezOS/wn2VYuVRqOFUkPEDQbMVy7ZX3Ye', 'counselor', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 11:13:42', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(19, 'Counselor Walkin', 'student_by_counselor_1767611622@example.com', '$2y$10$JmNxX8Wno8nKv5lfi/XM2.3cXXDAu8Jcx3U/lGVnEb6gkDggoA9Bq', 'student', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 11:13:42', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(20, 'John Doe 1767611732', 'john.doe.1767611732@test.com', '$2y$10$d0SEDG522fXv9igl9Ky1Uegr7ewXaCmt3F16Z2XWGWNZDGOcYMgR.', 'student', '1234567890', NULL, 4, NULL, 'Canada', 'Bachelor', NULL, '2026-01-05 11:15:33', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(21, 'Test Counselor', 'counselor.1767611733@example.com', '$2y$10$1E0LYiF.HWQ0d5gyS6Ne/.bMohwAJHzNFdr7Np8qBJJAI61zhF5Ka', 'counselor', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 11:15:33', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(22, 'Counselor Walkin', 'student_by_counselor_1767611733@example.com', '$2y$10$YJ/k9NG74dEZlrcDZn9k0us1r/2qZXgEljpZQ9ZCDYD0jKdvnctXe', 'student', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 11:15:33', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(23, 'Test Counselor', 'counselor@test.com', '$2y$10$s66lsFjEqsEwlbT6mvPY/Oh62DukFmncBz5hkfsI6YBo4Fj8jw45y', 'student', '1234567890', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-06 06:23:31', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(24, 'Test Teacher', 'teacher@test.com', '$2y$10$43FqQskj1gcLwF2mbecc8.e2M3mdx/eAiSmWPg8Wr3L9ZhnIzOyGu', 'student', '1234567890', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-06 06:24:33', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(25, 'Test Student 1', 'student1@test.com', '$2y$10$/KZ.vsp83HGoCzr/qfJU2.sbTXp7lw4xix408XXIk7.QM5Zvmx1pm', 'student', '1111111111', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-06 06:29:27', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(26, 'Test Student 2', 'student2@test.com', '$2y$10$gMPbTTMD..pGsyXZrubx0ulVUA81Z0rqHyq5xq33g0U3hNMMYLcYW', 'student', '2222222222', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-06 06:30:30', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(27, 'Test Student 987', 'student987@test.com', '$2y$10$0NdTqtS1rpQEzJp.KALMW.E8lT6Q/HgbPfS8Sdp8uL6O29g4IZJ8y', 'student', '1234567890', NULL, NULL, NULL, NULL, NULL, 'P1234567', '2026-01-22 05:41:22', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(28, 'Test Staff', 'staff852@test.com', '$2y$10$nXn.ezKEh/lK9iD.qRZ9y.ZTXNjAgUrsT7gF50ujLSVWx85MMrDBW', 'student', '', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-22 05:48:49', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(29, 'Robot Test User', 'robot@example.com', '$2y$10$XSS.MvRjUDhFb5Ae5norwOhGWUmdFRjS2wLmxo11q.e2z0gebqgq6', 'student', '555-0199', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-22 06:01:17', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL),
(30, 'Test Student UI Updated', 'teststudent@test.com', '$2y$10$YtfxXYR/s61DjMtOeUMDZed.JcWUpSeUHdtuLZX/5Kiwu2Y.g/eH.', 'student', '9800000002', NULL, 1, 1, NULL, NULL, '', '2026-01-22 06:38:05', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, '2026-01-25 11:27:52'),
(31, 'Test Student', 'test@student.com', 'hash', 'student', '1234567890', NULL, NULL, NULL, NULL, NULL, '', '2026-01-23 09:05:57', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, '2026-01-23 14:55:06'),
(32, 'Sara', 'sara@mul.edu.np', '$2y$10$/zbfkz8RH9EQjbeTnZibS.zX.kKuI236rVWg1jf1g5KOe3WKSGwTW', 'student', '', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-03 08:18:08', NULL, NULL, NULL, NULL, 0, NULL, NULL, 13, NULL),
(33, 'Binayak Pandey', 'binayakchhetri56@gmail.com', '$2y$10$FemMq2p3ZaBbJIj1t8o2L.6ANt/WxH4Xeh3BZ.da6tmBTN08EJRwy', 'student', '+977 986-7324737', NULL, 8, 2, NULL, NULL, 'PA0533419', '2026-02-03 10:06:31', NULL, NULL, NULL, NULL, 0, NULL, NULL, 13, '2026-02-03 10:51:36'),
(34, 'Saman Rokka', 'Samanrokka1@gmail.com', '$2y$10$8dKhIuNO0FG9EJD3uyTlm.PRmQCE7/0r13QFqHRmRkFxN0o71hWwe', 'student', '9743238720', NULL, 8, 2, NULL, NULL, 'PA2946271', '2026-02-03 10:51:13', NULL, NULL, NULL, NULL, 0, NULL, NULL, 13, '2026-02-05 11:34:26'),
(35, 'Samyog Shrestha', 'samyog.shrestha@outlook.com', '$2y$10$Rundj7ZUvEbfxtwM9sFocuVUpsAdgF/IXiZ/rPDAEjcm9MAFLpWoq', 'student', '9708057911', NULL, 8, 2, NULL, NULL, 'PA3800523', '2026-02-05 11:07:35', NULL, NULL, NULL, NULL, 0, NULL, NULL, 13, NULL),
(36, 'Nikesh Mahat', 'nikesh.mahat@outlook.com', '$2y$10$T2.U3q07/RDnBkJUg448U./0/l2FaHWhhdWW9l7I.r95bJ1j.y1ku', 'student', '9827345623', NULL, 8, 2, NULL, NULL, 'PA1168691', '2026-02-05 11:36:42', NULL, NULL, NULL, NULL, 0, NULL, NULL, 13, NULL),
(37, 'Paurakh Shah', 'paurakh.shah@outlook.com', '$2y$10$6OUbBsZJocbc00L.VvlisuqifAXkizSjFz5cY8dPFLjJhsUXZkNyO', 'student', '9803646632', NULL, 8, 5, NULL, NULL, 'PA2056125', '2026-02-06 11:13:14', NULL, NULL, NULL, NULL, 0, NULL, NULL, 13, NULL),
(39, 'Dikshant Singh', 'dikshant.singh04@outlook.com', '$2y$10$vjpiOOtoQYz3JHqi6KHCgew85xNryH9De5p2XqR.ChpwCzsbjLPfC', 'student', '+977 984-5873618', NULL, 8, 5, NULL, NULL, 'PA3795638', '2026-02-09 07:49:07', NULL, NULL, NULL, NULL, 0, NULL, NULL, 13, NULL),
(40, 'Prajwal Rimal', 'rimalprajwal@gmail.com', '$2y$10$FrqD7PvpR8mPpq8ykQPMR.sHvTE9sUbPfdAFgyDJiaGzqsCY.PYYm', 'student', '9861800462', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-10 09:26:25', NULL, NULL, NULL, NULL, 0, NULL, NULL, 13, NULL),
(41, 'IS Khulal Magar', 'bohemiankhulal@gmail.com', '$2y$10$Qf8L.MjqJI3kBLQJDzlj0Oav4nRpwPA2J4fuLOph1zTHVucRfPOrO', 'student', '9860088609', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-11 09:44:54', NULL, NULL, NULL, NULL, 0, NULL, NULL, 13, NULL),
(43, 'Hem Raj Bhandari', 'hemraj.bhandari@outlook.com', '$2y$10$lD3ATEknq112JWNlpzNJneRI4hvMu/aEnCrrO19MIY79cWnblYqk6', 'student', '9843597802', NULL, 8, 7, NULL, NULL, 'PA1876236', '2026-02-12 07:55:46', NULL, NULL, NULL, NULL, 0, NULL, NULL, 13, '2026-02-12 09:02:12');

-- --------------------------------------------------------

--
-- Table structure for table `user_activity`
--

CREATE TABLE `user_activity` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_calendar_tokens`
--

CREATE TABLE `user_calendar_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `provider` enum('google','outlook') DEFAULT 'google',
  `access_token` text DEFAULT NULL,
  `refresh_token` text DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `calendar_id` varchar(255) DEFAULT 'primary',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_devices`
--

CREATE TABLE `user_devices` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `device_token` varchar(500) NOT NULL,
  `device_type` enum('ios','android','web') DEFAULT 'android',
  `device_name` varchar(255) DEFAULT 'Mobile Device',
  `app_version` varchar(20) DEFAULT '1.0.0',
  `is_active` tinyint(1) DEFAULT 1,
  `last_active_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_notification_preferences`
--

CREATE TABLE `user_notification_preferences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_key` varchar(50) NOT NULL,
  `channel` varchar(20) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_notification_preferences`
--

INSERT INTO `user_notification_preferences` (`id`, `user_id`, `event_key`, `channel`, `is_enabled`, `updated_at`) VALUES
(1, 1, 'appointment_reminder', 'email', 1, '2026-01-21 09:20:26'),
(2, 1, 'appointment_reminder', 'sms', 1, '2026-01-21 09:20:26'),
(3, 1, 'appointment_reminder', 'whatsapp', 0, '2026-01-21 09:20:26'),
(4, 1, 'document_status', 'email', 1, '2026-01-21 09:20:26'),
(5, 1, 'document_status', 'sms', 0, '2026-01-21 09:20:26'),
(6, 1, 'document_status', 'whatsapp', 0, '2026-01-21 09:20:26'),
(7, 1, 'task_assigned', 'email', 1, '2026-01-21 09:20:26'),
(8, 1, 'task_assigned', 'sms', 1, '2026-01-21 10:22:18'),
(9, 1, 'task_assigned', 'whatsapp', 0, '2026-01-21 09:20:26'),
(10, 1, 'task_overdue', 'email', 1, '2026-01-21 09:20:26'),
(11, 1, 'task_overdue', 'sms', 0, '2026-01-21 09:20:26'),
(12, 1, 'task_overdue', 'whatsapp', 0, '2026-01-21 09:20:26'),
(13, 1, 'visa_stage_update', 'email', 1, '2026-01-21 09:20:26'),
(14, 1, 'visa_stage_update', 'sms', 0, '2026-01-21 09:20:26'),
(15, 1, 'visa_stage_update', 'whatsapp', 1, '2026-01-21 09:20:26'),
(16, 1, 'welcome_email', 'email', 1, '2026-01-21 09:20:26'),
(17, 1, 'welcome_email', 'sms', 0, '2026-01-21 09:20:26'),
(18, 1, 'welcome_email', 'whatsapp', 0, '2026-01-21 09:20:26'),
(85, 30, 'all', 'email', 1, '2026-01-25 05:42:52'),
(86, 30, 'all', 'push', 0, '2026-01-25 05:42:52'),
(87, 30, 'all', 'sms', 0, '2026-01-25 05:42:52'),
(88, 30, 'all', 'viber', 0, '2026-01-25 05:42:52'),
(89, 30, 'all', 'whatsapp', 0, '2026-01-25 05:42:52'),
(120, 33, 'all', 'email', 1, '2026-02-03 10:51:36'),
(121, 33, 'all', 'push', 0, '2026-02-03 10:51:36'),
(122, 33, 'all', 'sms', 0, '2026-02-03 10:51:36'),
(123, 33, 'all', 'viber', 0, '2026-02-03 10:51:36'),
(124, 33, 'all', 'whatsapp', 0, '2026-02-03 10:51:36'),
(125, 35, 'all', 'email', 1, '2026-02-05 11:07:35'),
(126, 35, 'all', 'push', 0, '2026-02-05 11:07:35'),
(127, 35, 'all', 'sms', 0, '2026-02-05 11:07:35'),
(128, 35, 'all', 'viber', 0, '2026-02-05 11:07:35'),
(129, 35, 'all', 'whatsapp', 0, '2026-02-05 11:07:35'),
(130, 34, 'all', 'email', 1, '2026-02-05 11:34:26'),
(131, 34, 'all', 'push', 0, '2026-02-05 11:34:26'),
(132, 34, 'all', 'sms', 0, '2026-02-05 11:34:26'),
(133, 34, 'all', 'viber', 0, '2026-02-05 11:34:26'),
(134, 34, 'all', 'whatsapp', 0, '2026-02-05 11:34:26'),
(135, 36, 'all', 'email', 1, '2026-02-05 11:36:42'),
(136, 36, 'all', 'push', 0, '2026-02-05 11:36:42'),
(137, 36, 'all', 'sms', 0, '2026-02-05 11:36:42'),
(138, 36, 'all', 'viber', 0, '2026-02-05 11:36:42'),
(139, 36, 'all', 'whatsapp', 0, '2026-02-05 11:36:42'),
(140, 37, 'all', 'email', 1, '2026-02-06 11:13:14'),
(141, 37, 'all', 'push', 0, '2026-02-06 11:13:14'),
(142, 37, 'all', 'sms', 0, '2026-02-06 11:13:14'),
(143, 37, 'all', 'viber', 0, '2026-02-06 11:13:14'),
(144, 37, 'all', 'whatsapp', 0, '2026-02-06 11:13:14'),
(150, 39, 'all', 'email', 1, '2026-02-09 07:49:07'),
(151, 39, 'all', 'push', 0, '2026-02-09 07:49:07'),
(152, 39, 'all', 'sms', 0, '2026-02-09 07:49:07'),
(153, 39, 'all', 'viber', 0, '2026-02-09 07:49:07'),
(154, 39, 'all', 'whatsapp', 0, '2026-02-09 07:49:07'),
(160, 40, 'all', 'email', 1, '2026-02-10 09:27:41'),
(161, 40, 'all', 'push', 0, '2026-02-10 09:27:41'),
(162, 40, 'all', 'sms', 0, '2026-02-10 09:27:41'),
(163, 40, 'all', 'viber', 0, '2026-02-10 09:27:41'),
(164, 40, 'all', 'whatsapp', 0, '2026-02-10 09:27:41'),
(165, 41, 'all', 'email', 1, '2026-02-11 09:44:54'),
(166, 41, 'all', 'push', 0, '2026-02-11 09:44:54'),
(167, 41, 'all', 'sms', 0, '2026-02-11 09:44:54'),
(168, 41, 'all', 'viber', 0, '2026-02-11 09:44:54'),
(169, 41, 'all', 'whatsapp', 0, '2026-02-11 09:44:54'),
(175, 32, 'all', 'email', 1, '2026-02-11 14:47:11'),
(176, 32, 'all', 'push', 0, '2026-02-11 14:47:11'),
(177, 32, 'all', 'sms', 0, '2026-02-11 14:47:11'),
(178, 32, 'all', 'viber', 0, '2026-02-11 14:47:11'),
(179, 32, 'all', 'whatsapp', 0, '2026-02-11 14:47:11'),
(190, 43, 'all', 'email', 1, '2026-02-12 09:02:12'),
(191, 43, 'all', 'push', 0, '2026-02-12 09:02:12'),
(192, 43, 'all', 'sms', 0, '2026-02-12 09:02:12'),
(193, 43, 'all', 'viber', 0, '2026-02-12 09:02:12'),
(194, 43, 'all', 'whatsapp', 0, '2026-02-12 09:02:12');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
(1, 1),
(2, 3),
(3, 4),
(4, 4),
(5, 3),
(6, 4),
(7, 4),
(8, 4),
(9, 4),
(10, 4),
(11, 4),
(12, 4),
(13, 4),
(14, 4),
(15, 4),
(16, 4),
(18, 2),
(20, 4),
(21, 2),
(23, 2),
(24, 3),
(25, 4),
(26, 4),
(27, 4),
(28, 2),
(29, 4),
(30, 4),
(32, 2),
(32, 3),
(32, 5),
(32, 6),
(33, 4),
(34, 4),
(35, 4),
(36, 4),
(37, 4),
(39, 4),
(40, 2),
(40, 3),
(40, 5),
(40, 6),
(41, 2),
(41, 3),
(41, 5),
(43, 4);

-- --------------------------------------------------------

--
-- Table structure for table `visa_stages`
--

CREATE TABLE `visa_stages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `stage_order` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `default_sla_days` int(11) DEFAULT 7,
  `allowed_next_stages` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowed_next_stages`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visa_stages`
--

INSERT INTO `visa_stages` (`id`, `name`, `stage_order`, `description`, `created_at`, `default_sla_days`, `allowed_next_stages`) VALUES
(1, 'Doc Collection', 1, NULL, '2026-01-04 11:42:24', 7, '[\"Submission\"]'),
(2, 'Submission', 2, NULL, '2026-01-04 11:42:24', 3, '[\"Interview\", \"Approved\", \"Rejected\"]'),
(3, 'Interview', 3, NULL, '2026-01-04 11:42:24', 14, '[\"Approved\", \"Rejected\"]'),
(4, 'Approved', 4, NULL, '2026-01-04 11:42:24', 60, '[]'),
(5, 'Rejected', 5, NULL, '2026-01-04 11:42:24', 7, '[\"Doc Collection\"]');

-- --------------------------------------------------------

--
-- Table structure for table `visa_workflows`
--

CREATE TABLE `visa_workflows` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `country` varchar(100) NOT NULL,
  `country_id` int(11) DEFAULT NULL,
  `current_stage` enum('Doc Collection','Submission','Interview','Approved','Rejected') DEFAULT 'Doc Collection',
  `stage_id` int(11) DEFAULT NULL,
  `workflow_progress_id` int(11) DEFAULT NULL,
  `checklist_json` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `stage_started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expected_completion_date` date DEFAULT NULL,
  `priority` enum('normal','urgent','critical') DEFAULT 'normal',
  `branch_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visa_workflows`
--

INSERT INTO `visa_workflows` (`id`, `student_id`, `country`, `country_id`, `current_stage`, `stage_id`, `workflow_progress_id`, `checklist_json`, `notes`, `updated_at`, `stage_started_at`, `expected_completion_date`, `priority`, `branch_id`) VALUES
(1, 10, 'Australia', 1, 'Approved', 4, NULL, NULL, 'Chronological Progress:\r\n- 2025-10-15: Test Score Achieved (IELTS 7.0)\r\n- 2025-11-01: Offer Letter Received\r\n- 2025-11-15: Fee Payment of fee made\r\n- 2025-12-01: Visa Applied - Currently waiting for results.', '2026-01-04 11:42:25', '2026-01-05 07:53:41', NULL, 'normal', NULL),
(2, 13, '', 1, 'Doc Collection', 4, NULL, '[{\"id\":\"passport\",\"label\":\"Valid Passport\",\"required\":true,\"status\":\"verified\"},{\"id\":\"offer_letter\",\"label\":\"Offer Letter \\/ CoE\",\"required\":true,\"status\":\"verified\"},{\"id\":\"financials\",\"label\":\"Financial Proof\",\"required\":true,\"status\":\"verified\"},{\"id\":\"english_test\",\"label\":\"English Test Results\",\"required\":true,\"status\":\"verified\"},{\"id\":\"health_insurance\",\"label\":\"Health Insurance\",\"required\":false,\"status\":\"verified\"},{\"id\":\"police_clearance\",\"label\":\"Police Clearance\",\"required\":false,\"status\":\"verified\"}]', 'Visa approved! Week 4 - Student ready for enrollment', '2026-01-05 08:20:17', '2026-01-05 08:16:27', '2026-01-12', 'urgent', NULL),
(3, 11, '', 1, 'Doc Collection', 1, NULL, '[{\"id\":\"1\",\"code\":\"passport\",\"label\":\"Valid Passport\",\"required\":\"1\",\"status\":\"pending\",\"has_file\":false,\"filename\":null},{\"id\":\"2\",\"code\":\"offer_letter\",\"label\":\"Offer Letter \\/ CoE\",\"required\":\"1\",\"status\":\"pending\",\"has_file\":false,\"filename\":null},{\"id\":\"3\",\"code\":\"financials\",\"label\":\"Financial Proof\",\"required\":\"1\",\"status\":\"pending\",\"has_file\":false,\"filename\":null},{\"id\":\"4\",\"code\":\"english_test\",\"label\":\"English Test Results\",\"required\":\"1\",\"status\":\"pending\",\"has_file\":false,\"filename\":null},{\"id\":\"5\",\"code\":\"academic_docs\",\"label\":\"Academic Documents\",\"required\":\"1\",\"status\":\"pending\",\"has_file\":false,\"filename\":null},{\"id\":\"6\",\"code\":\"health_insurance\",\"label\":\"Health Insurance\",\"required\":\"0\",\"status\":\"pending\",\"has_file\":false,\"filename\":null},{\"id\":\"7\",\"code\":\"police_clearance\",\"label\":\"Police Clearance\",\"required\":\"0\",\"status\":\"pending\",\"has_file\":false,\"filename\":null},{\"id\":\"8\",\"code\":\"medical_exam\",\"label\":\"Medical Examination\",\"required\":\"0\",\"status\":\"pending\",\"has_file\":false,\"filename\":null},{\"id\":\"9\",\"code\":\"sop\",\"label\":\"Statement of Purpose\",\"required\":\"0\",\"status\":\"pending\",\"has_file\":false,\"filename\":null},{\"id\":\"10\",\"code\":\"recommendation\",\"label\":\"Recommendation Letters\",\"required\":\"0\",\"status\":\"pending\",\"has_file\":false,\"filename\":null},{\"id\":\"15\",\"code\":\"color_test\",\"label\":\"Color Test Updated\",\"required\":\"1\",\"status\":\"pending\",\"has_file\":false,\"filename\":null}]', '', '2026-01-05 10:13:19', '2026-01-05 10:13:19', '2026-01-12', 'normal', NULL),
(4, 14, '', 1, 'Doc Collection', 1, NULL, '[{\"id\":\"1\",\"code\":\"passport\",\"label\":\"Valid Passport\",\"required\":\"1\",\"status\":\"pending\",\"has_file\":false,\"filename\":null},{\"id\":\"2\",\"code\":\"offer_letter\",\"label\":\"Offer Letter \\/ CoE\",\"required\":\"1\",\"status\":\"not_required\",\"has_file\":false,\"filename\":null},{\"id\":\"3\",\"code\":\"financials\",\"label\":\"Financial Proof\",\"required\":\"1\",\"status\":\"pending\",\"has_file\":false,\"filename\":null},{\"id\":\"4\",\"code\":\"english_test\",\"label\":\"English Test Results\",\"required\":\"1\",\"status\":\"pending\",\"has_file\":false,\"filename\":null},{\"id\":\"5\",\"code\":\"academic_docs\",\"label\":\"Academic Documents\",\"required\":\"1\",\"status\":\"not_required\",\"has_file\":false,\"filename\":null},{\"id\":\"6\",\"code\":\"health_insurance\",\"label\":\"Health Insurance\",\"required\":\"0\",\"status\":\"pending\",\"has_file\":false,\"filename\":null},{\"id\":\"7\",\"code\":\"police_clearance\",\"label\":\"Police Clearance\",\"required\":\"0\",\"status\":\"not_required\",\"has_file\":false,\"filename\":null},{\"id\":\"8\",\"code\":\"medical_exam\",\"label\":\"Medical Examination\",\"required\":\"0\",\"status\":\"not_required\",\"has_file\":false,\"filename\":null},{\"id\":\"9\",\"code\":\"sop\",\"label\":\"Statement of Purpose\",\"required\":\"0\",\"status\":\"not_required\",\"has_file\":false,\"filename\":null},{\"id\":\"10\",\"code\":\"recommendation\",\"label\":\"Recommendation Letters\",\"required\":\"0\",\"status\":\"not_required\",\"has_file\":false,\"filename\":null},{\"id\":\"15\",\"code\":\"color_test\",\"label\":\"Color Test Updated\",\"required\":\"1\",\"status\":\"not_required\",\"has_file\":false,\"filename\":null}]', '', '2026-01-05 10:38:53', '2026-01-05 10:36:58', '2026-01-12', 'normal', NULL),
(5, 19, 'Australia', 1, '', NULL, NULL, NULL, 'Application submitted to embassy.', '2026-01-05 11:13:42', '2026-01-05 11:13:42', NULL, 'normal', NULL),
(6, 22, 'Australia', 1, '', NULL, NULL, NULL, 'Application submitted to embassy.', '2026-01-05 11:15:33', '2026-01-05 11:15:33', NULL, 'normal', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `visa_workflow_history`
--

CREATE TABLE `visa_workflow_history` (
  `id` int(11) NOT NULL,
  `workflow_id` int(11) NOT NULL,
  `from_stage_id` int(11) DEFAULT NULL,
  `to_stage_id` int(11) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visa_workflow_history`
--

INSERT INTO `visa_workflow_history` (`id`, `workflow_id`, `from_stage_id`, `to_stage_id`, `changed_by`, `changed_at`, `notes`, `created_at`) VALUES
(1, 2, NULL, 1, 1, '2026-01-05 08:16:27', 'Initial visa workflow created', '2026-01-05 08:16:27'),
(2, 2, 1, 2, 1, '2026-01-05 08:19:46', 'All documents verified. Application submitted to Australian Embassy - Week 2', '2026-01-05 08:19:46'),
(3, 2, 2, 3, 1, '2026-01-05 08:20:01', 'Embassy scheduled interview for Week 3', '2026-01-05 08:20:01'),
(4, 2, 3, 4, 1, '2026-01-05 08:20:17', 'Visa approved! Week 4 - Student ready for enrollment', '2026-01-05 08:20:17'),
(5, 3, NULL, 1, 1, '2026-01-05 10:13:19', 'Initial visa workflow created', '2026-01-05 10:13:19'),
(6, 4, NULL, 1, 1, '2026-01-05 10:36:58', 'Initial visa workflow created', '2026-01-05 10:36:58');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_inquiries_full`
-- (See below for the actual view)
--
CREATE TABLE `v_inquiries_full` (
`id` int(11)
,`name` varchar(100)
,`email` varchar(100)
,`phone` varchar(20)
,`intended_country` varchar(100)
,`intended_course` varchar(50)
,`education_level` varchar(100)
,`status` varchar(50)
,`priority` varchar(50)
,`score` int(11)
,`last_contact_date` datetime
,`engagement_count` int(11)
,`assigned_to` int(11)
,`assigned_to_name` varchar(100)
,`branch_id` int(11)
,`branch_name` varchar(100)
,`created_at` timestamp
,`deleted_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_test_scores_full`
-- (See below for the actual view)
--
CREATE TABLE `v_test_scores_full` (
`id` int(11)
,`student_id` int(11)
,`student_name` varchar(100)
,`test_type` varchar(50)
,`test_full_name` varchar(150)
,`overall_score` decimal(3,1)
,`listening` decimal(3,1)
,`reading` decimal(3,1)
,`writing` decimal(3,1)
,`speaking` decimal(3,1)
,`test_date` date
,`report_file` varchar(255)
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_university_applications_full`
-- (See below for the actual view)
--
CREATE TABLE `v_university_applications_full` (
`id` int(11)
,`student_id` int(11)
,`student_name` varchar(100)
,`university_name` varchar(150)
,`partner_id` int(11)
,`course_name` varchar(150)
,`country` varchar(100)
,`status` varchar(100)
,`notes` text
,`created_at` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_visa_workflows_full`
-- (See below for the actual view)
--
CREATE TABLE `v_visa_workflows_full` (
`id` int(11)
,`student_id` int(11)
,`student_name` varchar(100)
,`country` varchar(100)
,`current_stage` varchar(100)
,`checklist_json` text
,`notes` text
,`updated_at` timestamp
,`is_overdue` int(1)
);

-- --------------------------------------------------------

--
-- Table structure for table `workflow_steps`
--

CREATE TABLE `workflow_steps` (
  `id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `step_order` int(11) NOT NULL,
  `step_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `estimated_days` int(11) DEFAULT 7,
  `required_documents` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`required_documents`)),
  `auto_create_task` tinyint(1) DEFAULT 0,
  `task_title` varchar(200) DEFAULT NULL,
  `task_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workflow_steps`
--

INSERT INTO `workflow_steps` (`id`, `template_id`, `step_order`, `step_name`, `description`, `estimated_days`, `required_documents`, `auto_create_task`, `task_title`, `task_description`, `created_at`) VALUES
(1, 1, 1, 'Document Collection', 'Gather all required documents for visa application', 7, '[\"Passport\", \"CoE\", \"Financial proof\", \"English test results\", \"Health insurance\"]', 1, 'Collect visa documents', NULL, '2026-01-01 11:25:02'),
(2, 1, 2, 'GTE Statement Preparation', 'Prepare Genuine Temporary Entrant statement', 3, '[\"GTE statement draft\"]', 1, 'Draft GTE statement', NULL, '2026-01-01 11:25:02'),
(3, 1, 3, 'Health Insurance', 'Obtain Overseas Student Health Cover (OSHC)', 1, '[\"OSHC certificate\"]', 1, 'Purchase OSHC', NULL, '2026-01-01 11:25:02'),
(4, 1, 4, 'Application Submission', 'Submit visa application online via ImmiAccount', 1, '[\"Completed application form\", \"Payment receipt\"]', 1, 'Submit visa application', NULL, '2026-01-01 11:25:02'),
(5, 1, 5, 'Biometrics Appointment', 'Attend biometrics collection appointment', 7, '[\"Biometrics confirmation\"]', 1, 'Schedule biometrics', NULL, '2026-01-01 11:25:02'),
(6, 1, 6, 'Health Examination', 'Complete required health examinations', 7, '[\"Health examination results\"]', 1, 'Book health exam', NULL, '2026-01-01 11:25:02'),
(7, 1, 7, 'Decision Awaited', 'Wait for visa decision from Department of Home Affairs', 60, '[]', 0, NULL, NULL, '2026-01-01 11:25:02'),
(8, 1, 8, 'Visa Grant', 'Visa granted - prepare for travel', 3, '[\"Visa grant letter\"]', 1, 'Prepare for departure', NULL, '2026-01-01 11:25:02'),
(9, 2, 1, 'CAS Confirmation', 'Obtain Confirmation of Acceptance for Studies from university', 7, '[\"CAS letter\"]', 1, 'Request CAS from university', NULL, '2026-01-01 11:25:02'),
(10, 2, 2, 'Document Preparation', 'Gather all required supporting documents', 5, '[\"Passport\", \"Financial evidence\", \"English test\", \"Academic transcripts\"]', 1, 'Collect visa documents', NULL, '2026-01-01 11:25:02'),
(11, 2, 3, 'Online Application', 'Complete visa application online', 1, '[\"Application form\", \"Payment receipt\"]', 1, 'Submit online application', NULL, '2026-01-01 11:25:02'),
(12, 2, 4, 'Biometrics & Interview', 'Attend visa application center for biometrics', 7, '[\"Appointment confirmation\"]', 1, 'Book VAC appointment', NULL, '2026-01-01 11:25:02'),
(13, 2, 5, 'TB Test (if required)', 'Complete tuberculosis test if from listed country', 3, '[\"TB test certificate\"]', 1, 'Schedule TB test', NULL, '2026-01-01 11:25:02'),
(14, 2, 6, 'Decision Awaited', 'Wait for visa decision', 30, '[]', 0, NULL, NULL, '2026-01-01 11:25:02'),
(15, 2, 7, 'Visa Collection', 'Collect passport with visa', 2, '[\"Visa vignette\"]', 1, 'Collect passport', NULL, '2026-01-01 11:25:02'),
(16, 3, 1, 'Letter of Acceptance', 'Obtain acceptance letter from DLI', 7, '[\"LOA from DLI\"]', 1, 'Request LOA', NULL, '2026-01-01 11:25:02'),
(17, 3, 2, 'GIC & Financial Proof', 'Set up GIC account and gather financial documents', 5, '[\"GIC certificate\", \"Bank statements\"]', 1, 'Open GIC account', NULL, '2026-01-01 11:25:02'),
(18, 3, 3, 'Document Collection', 'Gather all required documents', 5, '[\"Passport\", \"Photos\", \"Language test\", \"Academic records\"]', 1, 'Collect documents', NULL, '2026-01-01 11:25:02'),
(19, 3, 4, 'Online Application', 'Submit study permit application via IRCC portal', 2, '[\"Application form\", \"Payment receipt\"]', 1, 'Submit application', NULL, '2026-01-01 11:25:02'),
(20, 3, 5, 'Biometrics', 'Provide biometrics at VAC', 7, '[\"Biometrics confirmation\"]', 1, 'Schedule biometrics', NULL, '2026-01-01 11:25:02'),
(21, 3, 6, 'Medical Examination', 'Complete medical exam if required', 7, '[\"Medical exam results\"]', 1, 'Book medical exam', NULL, '2026-01-01 11:25:02'),
(22, 3, 7, 'Decision Awaited', 'Wait for study permit decision', 35, '[]', 0, NULL, NULL, '2026-01-01 11:25:02'),
(23, 3, 8, 'Permit Approval', 'Receive study permit approval', 2, '[\"Approval letter\", \"Port of entry letter\"]', 1, 'Prepare for travel', NULL, '2026-01-01 11:25:02'),
(24, 4, 1, 'I-20 Form', 'Obtain I-20 form from university', 7, '[\"I-20 form\"]', 1, 'Request I-20', NULL, '2026-01-01 11:25:02'),
(25, 4, 2, 'SEVIS Fee Payment', 'Pay SEVIS I-901 fee online', 1, '[\"SEVIS payment receipt\"]', 1, 'Pay SEVIS fee', NULL, '2026-01-01 11:25:02'),
(26, 4, 3, 'DS-160 Form', 'Complete DS-160 online application form', 2, '[\"DS-160 confirmation\"]', 1, 'Complete DS-160', NULL, '2026-01-01 11:25:02'),
(27, 4, 4, 'Visa Fee Payment', 'Pay visa application fee', 1, '[\"Visa fee receipt\"]', 1, 'Pay visa fee', NULL, '2026-01-01 11:25:02'),
(28, 4, 5, 'Interview Scheduling', 'Schedule visa interview appointment', 3, '[\"Interview appointment\"]', 1, 'Schedule interview', NULL, '2026-01-01 11:25:02'),
(29, 4, 6, 'Document Preparation', 'Prepare all documents for interview', 5, '[\"Financial documents\", \"Academic records\", \"Ties to home country\"]', 1, 'Prepare interview docs', NULL, '2026-01-01 11:25:02'),
(30, 4, 7, 'Visa Interview', 'Attend visa interview at US Embassy/Consulate', 1, '[\"Interview attendance\"]', 1, 'Attend interview', NULL, '2026-01-01 11:25:02'),
(31, 4, 8, 'Passport Processing', 'Wait for passport with visa', 7, '[]', 0, NULL, NULL, '2026-01-01 11:25:02'),
(32, 4, 9, 'Visa Received', 'Collect passport with F-1 visa', 2, '[\"F-1 visa\"]', 1, 'Prepare for departure', NULL, '2026-01-01 11:25:02'),
(33, 5, 1, 'Offer of Place', 'Obtain offer of place from NZ institution', 7, '[\"Offer letter\"]', 1, 'Request offer letter', NULL, '2026-01-01 11:25:02'),
(34, 5, 2, 'Financial Evidence', 'Prepare proof of funds', 3, '[\"Bank statements\", \"Scholarship letter\"]', 1, 'Gather financial proof', NULL, '2026-01-01 11:25:02'),
(35, 5, 3, 'Document Collection', 'Gather all required documents', 5, '[\"Passport\", \"Photos\", \"Police certificate\", \"Medical certificate\"]', 1, 'Collect documents', NULL, '2026-01-01 11:25:02'),
(36, 5, 4, 'Online Application', 'Submit visa application via Immigration NZ', 2, '[\"Application form\", \"Payment receipt\"]', 1, 'Submit application', NULL, '2026-01-01 11:25:02'),
(37, 5, 5, 'Medical & X-ray', 'Complete medical and chest X-ray', 5, '[\"Medical certificate\", \"X-ray results\"]', 1, 'Book medical exam', NULL, '2026-01-01 11:25:02'),
(38, 5, 6, 'Decision Awaited', 'Wait for visa decision', 20, '[]', 0, NULL, NULL, '2026-01-01 11:25:02'),
(39, 5, 7, 'Visa Grant', 'Visa granted - prepare for travel', 3, '[\"Visa approval\"]', 1, 'Prepare for departure', NULL, '2026-01-01 11:25:02');

-- --------------------------------------------------------

--
-- Table structure for table `workflow_step_completions`
--

CREATE TABLE `workflow_step_completions` (
  `id` int(11) NOT NULL,
  `progress_id` int(11) NOT NULL,
  `step_id` int(11) NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `documents_uploaded` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`documents_uploaded`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `workflow_templates`
--

CREATE TABLE `workflow_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category` enum('visa','admission','onboarding','custom') DEFAULT 'custom',
  `country` varchar(100) DEFAULT NULL,
  `country_id` int(11) DEFAULT NULL,
  `visa_type` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `estimated_days` int(11) DEFAULT 90,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workflow_templates`
--

INSERT INTO `workflow_templates` (`id`, `name`, `category`, `country`, `country_id`, `visa_type`, `description`, `estimated_days`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Australia Student Visa (Subclass 500)', 'visa', 'Australia', 1, '500', 'Complete workflow for Australian student visa application', 90, 1, NULL, '2026-01-01 11:25:02', '2026-01-04 11:42:25'),
(2, 'UK Student Visa (Tier 4)', 'visa', 'United Kingdom', 3, 'Tier 4', 'Complete workflow for UK Tier 4 student visa application', 60, 1, NULL, '2026-01-01 11:25:02', '2026-01-04 11:42:25'),
(3, 'Canada Study Permit', 'visa', 'Canada', 4, 'Study Permit', 'Complete workflow for Canadian study permit application', 75, 1, NULL, '2026-01-01 11:25:02', '2026-01-04 11:42:25'),
(4, 'USA F-1 Student Visa', 'visa', 'United States', 2, 'F-1', 'Complete workflow for US F-1 student visa application', 60, 1, NULL, '2026-01-01 11:25:02', '2026-01-04 11:42:25'),
(5, 'New Zealand Student Visa', 'visa', 'New Zealand', 5, 'Student Visa', 'Complete workflow for New Zealand student visa application', 45, 1, NULL, '2026-01-01 11:25:02', '2026-01-04 11:42:25');

-- --------------------------------------------------------

--
-- Structure for view `v_inquiries_full`
--
DROP TABLE IF EXISTS `v_inquiries_full`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_inquiries_full`  AS SELECT `i`.`id` AS `id`, `i`.`name` AS `name`, `i`.`email` AS `email`, `i`.`phone` AS `phone`, `c`.`name` AS `intended_country`, `i`.`intended_course` AS `intended_course`, `el`.`name` AS `education_level`, `ist`.`name` AS `status`, `pl`.`name` AS `priority`, `i`.`score` AS `score`, `i`.`last_contact_date` AS `last_contact_date`, `i`.`engagement_count` AS `engagement_count`, `i`.`assigned_to` AS `assigned_to`, `u`.`name` AS `assigned_to_name`, `i`.`branch_id` AS `branch_id`, `b`.`name` AS `branch_name`, `i`.`created_at` AS `created_at`, `i`.`deleted_at` AS `deleted_at` FROM ((((((`inquiries` `i` left join `countries` `c` on(`i`.`country_id` = `c`.`id`)) left join `education_levels` `el` on(`i`.`education_level_id` = `el`.`id`)) left join `inquiry_statuses` `ist` on(`i`.`status_id` = `ist`.`id`)) left join `priority_levels` `pl` on(`i`.`priority_id` = `pl`.`id`)) left join `users` `u` on(`i`.`assigned_to` = `u`.`id`)) left join `branches` `b` on(`i`.`branch_id` = `b`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_test_scores_full`
--
DROP TABLE IF EXISTS `v_test_scores_full`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_test_scores_full`  AS SELECT `ts`.`id` AS `id`, `ts`.`student_id` AS `student_id`, `u`.`name` AS `student_name`, coalesce(`tt`.`name`,`ts`.`test_type`) AS `test_type`, `tt`.`full_name` AS `test_full_name`, `ts`.`overall_score` AS `overall_score`, `ts`.`listening` AS `listening`, `ts`.`reading` AS `reading`, `ts`.`writing` AS `writing`, `ts`.`speaking` AS `speaking`, `ts`.`test_date` AS `test_date`, `ts`.`report_file` AS `report_file`, `ts`.`created_at` AS `created_at` FROM ((`test_scores` `ts` left join `users` `u` on(`ts`.`student_id` = `u`.`id`)) left join `test_types` `tt` on(`ts`.`test_type_id` = `tt`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_university_applications_full`
--
DROP TABLE IF EXISTS `v_university_applications_full`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_university_applications_full`  AS SELECT `ua`.`id` AS `id`, `ua`.`student_id` AS `student_id`, `u`.`name` AS `student_name`, coalesce(`p`.`name`,`ua`.`university_name`) AS `university_name`, `ua`.`partner_id` AS `partner_id`, `ua`.`course_name` AS `course_name`, coalesce(`c`.`name`,`ua`.`country`) AS `country`, `ast`.`name` AS `status`, `ua`.`notes` AS `notes`, `ua`.`created_at` AS `created_at`, `ua`.`updated_at` AS `updated_at` FROM ((((`university_applications` `ua` left join `users` `u` on(`ua`.`student_id` = `u`.`id`)) left join `partners` `p` on(`ua`.`partner_id` = `p`.`id`)) left join `countries` `c` on(`ua`.`country_id` = `c`.`id`)) left join `application_statuses` `ast` on(`ua`.`status_id` = `ast`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_visa_workflows_full`
--
DROP TABLE IF EXISTS `v_visa_workflows_full`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_visa_workflows_full`  AS SELECT `v`.`id` AS `id`, `v`.`student_id` AS `student_id`, `u`.`name` AS `student_name`, coalesce(`c`.`name`,`v`.`country`) AS `country`, coalesce(`vs`.`name`,`v`.`current_stage`) AS `current_stage`, `v`.`checklist_json` AS `checklist_json`, `v`.`notes` AS `notes`, `v`.`updated_at` AS `updated_at`, CASE WHEN `v`.`expected_completion_date` is not null AND `v`.`expected_completion_date` < curdate() AND `vs`.`name` not in ('Approved','Rejected') THEN 1 ELSE 0 END AS `is_overdue` FROM (((`visa_workflows` `v` left join `users` `u` on(`v`.`student_id` = `u`.`id`)) left join `countries` `c` on(`v`.`country_id` = `c`.`id`)) left join `visa_stages` `vs` on(`v`.`stage_id` = `vs`.`id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_daily_summary`
--
ALTER TABLE `activity_daily_summary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`summary_date`);

--
-- Indexes for table `analytics_goals`
--
ALTER TABLE `analytics_goals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `analytics_metrics`
--
ALTER TABLE `analytics_metrics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_metric_name` (`metric_name`),
  ADD KEY `idx_period` (`period_start`,`period_end`);

--
-- Indexes for table `analytics_snapshots`
--
ALTER TABLE `analytics_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `snapshot_date` (`snapshot_date`);

--
-- Indexes for table `application_statuses`
--
ALTER TABLE `application_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`appointment_date`),
  ADD KEY `idx_counselor` (`counselor_id`,`appointment_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_inquiry` (`inquiry_id`),
  ADD KEY `idx_appointment_status` (`status`,`appointment_date`),
  ADD KEY `idx_appointments_counselor_date` (`counselor_id`,`status`,`appointment_date`),
  ADD KEY `idx_appointments_date_status` (`appointment_date`,`status`),
  ADD KEY `fk_appointment_branch` (`branch_id`);

--
-- Indexes for table `attachments`
--
ALTER TABLE `attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `automation_logs`
--
ALTER TABLE `automation_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `workflow_id` (`workflow_id`),
  ADD KEY `idx_trigger_event` (`trigger_event`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_executed_at` (`executed_at`);

--
-- Indexes for table `automation_queue`
--
ALTER TABLE `automation_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_queue_status_schedule` (`status`,`scheduled_at`),
  ADD KEY `fk_queue_workflow` (`workflow_id`);

--
-- Indexes for table `automation_templates`
--
ALTER TABLE `automation_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `template_key` (`template_key`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_templates_channel` (`channel`),
  ADD KEY `idx_templates_active` (`is_active`);

--
-- Indexes for table `automation_workflows`
--
ALTER TABLE `automation_workflows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `template_id` (`template_id`),
  ADD KEY `gateway_id` (`gateway_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_workflows_trigger` (`trigger_event`),
  ADD KEY `idx_workflows_active` (`is_active`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `bulk_action_logs`
--
ALTER TABLE `bulk_action_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_type` (`action_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `calendar_sync_events`
--
ALTER TABLE `calendar_sync_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sync` (`appointment_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_calendar_sync_status` (`sync_status`),
  ADD KEY `idx_calendar_sync_appointment` (`appointment_id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `fk_class_branch` (`branch_id`);

--
-- Indexes for table `class_materials`
--
ALTER TABLE `class_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `communication_credits`
--
ALTER TABLE `communication_credits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_credit_type` (`credit_type`),
  ADD KEY `fk_credits_type` (`type_id`);

--
-- Indexes for table `communication_types`
--
ALTER TABLE `communication_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `communication_usage_logs`
--
ALTER TABLE `communication_usage_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_type` (`user_id`,`type`),
  ADD KEY `idx_sent_at` (`sent_at`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_usage_log_type` (`type_id`);

--
-- Indexes for table `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `daily_performance`
--
ALTER TABLE `daily_performance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roster_id` (`roster_id`,`student_id`),
  ADD KEY `idx_perf_student_roster` (`student_id`,`roster_id`),
  ADD KEY `idx_daily_performance_student_roster` (`student_id`,`roster_id`);

--
-- Indexes for table `daily_rosters`
--
ALTER TABLE `daily_rosters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `idx_daily_rosters_class_date` (`class_id`,`roster_date`),
  ADD KEY `idx_daily_rosters_date` (`roster_date`,`class_id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `idx_documents_expiry` (`expiry_date`,`expiry_alert_sent`);

--
-- Indexes for table `document_expiry_alerts`
--
ALTER TABLE `document_expiry_alerts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_alert` (`document_id`,`alert_type`),
  ADD KEY `idx_expiry_alerts_document` (`document_id`),
  ADD KEY `idx_expiry_alerts_sent_to` (`sent_to`);

--
-- Indexes for table `document_types`
--
ALTER TABLE `document_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_doctype_active` (`is_active`),
  ADD KEY `idx_doctype_order` (`display_order`);

--
-- Indexes for table `document_versions`
--
ALTER TABLE `document_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `education_levels`
--
ALTER TABLE `education_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `email_queue`
--
ALTER TABLE `email_queue`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `template_key` (`template_key`);

--
-- Indexes for table `email_template_channels`
--
ALTER TABLE `email_template_channels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_template_channel` (`template_id`,`channel_type`),
  ADD KEY `fk_template_gateway` (`gateway_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `idx_enroll_student_class` (`student_id`,`class_id`);

--
-- Indexes for table `fee_types`
--
ALTER TABLE `fee_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_score` (`score`),
  ADD KEY `idx_inquiry_priority_score` (`priority`,`score`),
  ADD KEY `idx_inquiry_status_priority` (`status`,`priority`),
  ADD KEY `idx_last_contact` (`last_contact_date`),
  ADD KEY `fk_inquiry_branch` (`branch_id`),
  ADD KEY `fk_inquiries_education` (`education_level_id`),
  ADD KEY `idx_inquiries_country` (`country_id`),
  ADD KEY `idx_inquiries_status_id` (`status_id`),
  ADD KEY `idx_inquiries_priority_id` (`priority_id`),
  ADD KEY `idx_inquiries_source` (`source`),
  ADD KEY `idx_inquiries_status_priority` (`status`,`priority`,`created_at`),
  ADD KEY `idx_inquiries_priority_status_created` (`priority`,`status`,`created_at`),
  ADD KEY `idx_inquiries_assigned_status` (`assigned_to`,`status`,`created_at`),
  ADD KEY `idx_inquiries_country_id` (`country_id`),
  ADD KEY `idx_inquiries_education_level_id` (`education_level_id`),
  ADD KEY `idx_inquiries_last_contacted` (`last_contacted`);

--
-- Indexes for table `inquiry_score_history`
--
ALTER TABLE `inquiry_score_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `idx_inquiry` (`inquiry_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `inquiry_statuses`
--
ALTER TABLE `inquiry_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `fee_type_id` (`fee_type_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `messaging_campaigns`
--
ALTER TABLE `messaging_campaigns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_campaign_type` (`type_id`);

--
-- Indexes for table `messaging_gateways`
--
ALTER TABLE `messaging_gateways`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_gateway_type` (`type_id`);

--
-- Indexes for table `messaging_logs`
--
ALTER TABLE `messaging_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gateway_id` (`gateway_id`),
  ADD KEY `idx_gateway_date` (`gateway_id`,`created_at`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `messaging_queue`
--
ALTER TABLE `messaging_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status_scheduled` (`status`,`scheduled_at`),
  ADD KEY `idx_recipient` (`recipient`),
  ADD KEY `fk_queue_type` (`type_id`),
  ADD KEY `idx_messaging_queue_status_scheduled` (`status`,`scheduled_at`,`priority`),
  ADD KEY `idx_messaging_queue_type_status` (`type_id`,`status`,`scheduled_at`),
  ADD KEY `idx_messaging_queue_recipient` (`recipient`,`status`);

--
-- Indexes for table `messaging_templates`
--
ALTER TABLE `messaging_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_template_type` (`type_id`),
  ADD KEY `idx_event_key` (`event_key`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_notifications_user_read` (`user_id`,`is_read`,`created_at`);

--
-- Indexes for table `notification_events`
--
ALTER TABLE `notification_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_key` (`event_key`);

--
-- Indexes for table `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `partners`
--
ALTER TABLE `partners`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_partners_country` (`country_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `performance_logs`
--
ALTER TABLE `performance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_page` (`page_url`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `priority_levels`
--
ALTER TABLE `priority_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `push_notification_logs`
--
ALTER TABLE `push_notification_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_device_status` (`device_id`,`status`);

--
-- Indexes for table `qr_attendance_scans`
--
ALTER TABLE `qr_attendance_scans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_session_student` (`session_id`,`student_id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `qr_attendance_sessions`
--
ALTER TABLE `qr_attendance_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_class_date` (`class_id`,`session_date`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_token` (`qr_token`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `query_cache`
--
ALTER TABLE `query_cache`
  ADD PRIMARY KEY (`cache_key`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `saved_searches`
--
ALTER TABLE `saved_searches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_type` (`search_type`);

--
-- Indexes for table `scoring_rules`
--
ALTER TABLE `scoring_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`rule_type`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `security_logs`
--
ALTER TABLE `security_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_action` (`user_id`,`action`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `student_documents`
--
ALTER TABLE `student_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `fk_studoc_doctype` (`document_type_id`),
  ADD KEY `fk_studoc_workflow` (`workflow_id`),
  ADD KEY `idx_student_documents_student_type` (`student_id`,`document_type_id`,`status`);

--
-- Indexes for table `student_fees`
--
ALTER TABLE `student_fees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `fee_type_id` (`fee_type_id`),
  ADD KEY `idx_student_fees_student_status` (`student_id`,`status`,`due_date`),
  ADD KEY `idx_student_fees_overdue` (`status`,`due_date`,`student_id`);

--
-- Indexes for table `student_logs`
--
ALTER TABLE `student_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `author_id` (`author_id`),
  ADD KEY `fk_student_log_type` (`type_id`),
  ADD KEY `idx_student_logs_student_date` (`student_id`,`created_at`),
  ADD KEY `idx_student_logs_type_date` (`type_id`,`created_at`);

--
-- Indexes for table `student_workflow_progress`
--
ALTER TABLE `student_workflow_progress`
  ADD PRIMARY KEY (`id`),
  ADD KEY `current_step_id` (`current_step_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_template` (`template_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_assigned` (`assigned_to`);

--
-- Indexes for table `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_key` (`setting_key`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_assigned` (`assigned_to`,`status`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_entity` (`related_entity_type`,`related_entity_id`),
  ADD KEY `idx_task_due_date` (`due_date`,`status`),
  ADD KEY `idx_tasks_assigned_status_due` (`assigned_to`,`status`,`due_date`),
  ADD KEY `idx_tasks_priority_status` (`priority`,`status`,`due_date`),
  ADD KEY `fk_task_branch` (`branch_id`);

--
-- Indexes for table `test_scores`
--
ALTER TABLE `test_scores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `fk_scores_test_type` (`test_type_id`),
  ADD KEY `idx_test_scores_student_type` (`student_id`,`test_type_id`,`test_date`);

--
-- Indexes for table `test_types`
--
ALTER TABLE `test_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `university_applications`
--
ALTER TABLE `university_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `idx_app_partner` (`partner_id`),
  ADD KEY `idx_app_country` (`country_id`),
  ADD KEY `idx_app_status_id` (`status_id`),
  ADD KEY `idx_applications_student_status` (`student_id`,`status`),
  ADD KEY `idx_applications_partner_status` (`partner_id`,`status`),
  ADD KEY `idx_applications_country_id` (`country_id`),
  ADD KEY `idx_applications_status_id` (`status_id`),
  ADD KEY `fk_application_branch` (`branch_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_user_branch` (`branch_id`),
  ADD KEY `idx_users_country` (`country_id`),
  ADD KEY `idx_users_education` (`education_level_id`);

--
-- Indexes for table `user_activity`
--
ALTER TABLE `user_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_date` (`user_id`,`created_at`),
  ADD KEY `idx_action_date` (`action`,`created_at`);

--
-- Indexes for table `user_calendar_tokens`
--
ALTER TABLE `user_calendar_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `user_devices`
--
ALTER TABLE `user_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_device` (`user_id`,`device_token`),
  ADD KEY `idx_device_token` (`device_token`),
  ADD KEY `idx_user_active` (`user_id`,`is_active`);

--
-- Indexes for table `user_notification_preferences`
--
ALTER TABLE `user_notification_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_event_channel` (`user_id`,`event_key`,`channel`),
  ADD KEY `event_key` (`event_key`),
  ADD KEY `idx_notif_prefs_user` (`user_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `visa_stages`
--
ALTER TABLE `visa_stages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `visa_workflows`
--
ALTER TABLE `visa_workflows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `idx_visa_country` (`country_id`),
  ADD KEY `idx_visa_stage` (`stage_id`),
  ADD KEY `idx_visa_workflows_student_stage` (`student_id`,`current_stage`),
  ADD KEY `idx_visa_workflows_sla` (`stage_id`,`stage_started_at`,`expected_completion_date`),
  ADD KEY `idx_visa_workflows_progress` (`workflow_progress_id`,`stage_id`),
  ADD KEY `idx_visa_workflows_country_id` (`country_id`),
  ADD KEY `idx_visa_workflows_stage_id` (`stage_id`),
  ADD KEY `fk_visa_branch` (`branch_id`);

--
-- Indexes for table `visa_workflow_history`
--
ALTER TABLE `visa_workflow_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vwh_workflow` (`workflow_id`),
  ADD KEY `idx_vwh_changed_at` (`changed_at`),
  ADD KEY `fk_vwh_to_stage` (`to_stage_id`),
  ADD KEY `fk_vwh_changed_by` (`changed_by`),
  ADD KEY `idx_visa_history_workflow_date` (`workflow_id`,`changed_at`),
  ADD KEY `idx_visa_history_stages` (`from_stage_id`,`to_stage_id`,`changed_at`);

--
-- Indexes for table `workflow_steps`
--
ALTER TABLE `workflow_steps`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_template_step` (`template_id`,`step_order`),
  ADD KEY `idx_template` (`template_id`,`step_order`);

--
-- Indexes for table `workflow_step_completions`
--
ALTER TABLE `workflow_step_completions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_progress_step` (`progress_id`,`step_id`),
  ADD KEY `completed_by` (`completed_by`),
  ADD KEY `idx_progress` (`progress_id`),
  ADD KEY `idx_step` (`step_id`);

--
-- Indexes for table `workflow_templates`
--
ALTER TABLE `workflow_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_country` (`country`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `fk_workflow_template_country` (`country_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_daily_summary`
--
ALTER TABLE `activity_daily_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `analytics_goals`
--
ALTER TABLE `analytics_goals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `analytics_metrics`
--
ALTER TABLE `analytics_metrics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `analytics_snapshots`
--
ALTER TABLE `analytics_snapshots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `application_statuses`
--
ALTER TABLE `application_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `attachments`
--
ALTER TABLE `attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT for table `automation_logs`
--
ALTER TABLE `automation_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `automation_queue`
--
ALTER TABLE `automation_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `automation_templates`
--
ALTER TABLE `automation_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `automation_workflows`
--
ALTER TABLE `automation_workflows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `bulk_action_logs`
--
ALTER TABLE `bulk_action_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calendar_sync_events`
--
ALTER TABLE `calendar_sync_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `class_materials`
--
ALTER TABLE `class_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `communication_credits`
--
ALTER TABLE `communication_credits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `communication_types`
--
ALTER TABLE `communication_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `communication_usage_logs`
--
ALTER TABLE `communication_usage_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `countries`
--
ALTER TABLE `countries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `daily_performance`
--
ALTER TABLE `daily_performance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `daily_rosters`
--
ALTER TABLE `daily_rosters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_expiry_alerts`
--
ALTER TABLE `document_expiry_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_types`
--
ALTER TABLE `document_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `document_versions`
--
ALTER TABLE `document_versions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `education_levels`
--
ALTER TABLE `education_levels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `email_queue`
--
ALTER TABLE `email_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `email_template_channels`
--
ALTER TABLE `email_template_channels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `fee_types`
--
ALTER TABLE `fee_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `inquiries`
--
ALTER TABLE `inquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `inquiry_score_history`
--
ALTER TABLE `inquiry_score_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inquiry_statuses`
--
ALTER TABLE `inquiry_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messaging_campaigns`
--
ALTER TABLE `messaging_campaigns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messaging_gateways`
--
ALTER TABLE `messaging_gateways`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `messaging_logs`
--
ALTER TABLE `messaging_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=179;

--
-- AUTO_INCREMENT for table `messaging_queue`
--
ALTER TABLE `messaging_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messaging_templates`
--
ALTER TABLE `messaging_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_events`
--
ALTER TABLE `notification_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `notification_logs`
--
ALTER TABLE `notification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `partners`
--
ALTER TABLE `partners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `performance_logs`
--
ALTER TABLE `performance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `priority_levels`
--
ALTER TABLE `priority_levels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `push_notification_logs`
--
ALTER TABLE `push_notification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qr_attendance_scans`
--
ALTER TABLE `qr_attendance_scans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qr_attendance_sessions`
--
ALTER TABLE `qr_attendance_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `saved_searches`
--
ALTER TABLE `saved_searches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scoring_rules`
--
ALTER TABLE `scoring_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `security_logs`
--
ALTER TABLE `security_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `student_documents`
--
ALTER TABLE `student_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `student_fees`
--
ALTER TABLE `student_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `student_logs`
--
ALTER TABLE `student_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `student_workflow_progress`
--
ALTER TABLE `student_workflow_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=146;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `test_scores`
--
ALTER TABLE `test_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `test_types`
--
ALTER TABLE `test_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `university_applications`
--
ALTER TABLE `university_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `user_activity`
--
ALTER TABLE `user_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_calendar_tokens`
--
ALTER TABLE `user_calendar_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_devices`
--
ALTER TABLE `user_devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_notification_preferences`
--
ALTER TABLE `user_notification_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=195;

--
-- AUTO_INCREMENT for table `visa_stages`
--
ALTER TABLE `visa_stages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `visa_workflows`
--
ALTER TABLE `visa_workflows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `visa_workflow_history`
--
ALTER TABLE `visa_workflow_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `workflow_steps`
--
ALTER TABLE `workflow_steps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `workflow_step_completions`
--
ALTER TABLE `workflow_step_completions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `workflow_templates`
--
ALTER TABLE `workflow_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_daily_summary`
--
ALTER TABLE `activity_daily_summary`
  ADD CONSTRAINT `activity_daily_summary_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `analytics_goals`
--
ALTER TABLE `analytics_goals`
  ADD CONSTRAINT `analytics_goals_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`inquiry_id`) REFERENCES `inquiries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`counselor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_appointment_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `attachments`
--
ALTER TABLE `attachments`
  ADD CONSTRAINT `attachments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `automation_logs`
--
ALTER TABLE `automation_logs`
  ADD CONSTRAINT `automation_logs_ibfk_1` FOREIGN KEY (`workflow_id`) REFERENCES `automation_workflows` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `automation_queue`
--
ALTER TABLE `automation_queue`
  ADD CONSTRAINT `fk_queue_workflow` FOREIGN KEY (`workflow_id`) REFERENCES `automation_workflows` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `automation_templates`
--
ALTER TABLE `automation_templates`
  ADD CONSTRAINT `automation_templates_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `automation_workflows`
--
ALTER TABLE `automation_workflows`
  ADD CONSTRAINT `automation_workflows_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `automation_templates` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `automation_workflows_ibfk_2` FOREIGN KEY (`gateway_id`) REFERENCES `messaging_gateways` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `automation_workflows_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `branches`
--
ALTER TABLE `branches`
  ADD CONSTRAINT `branches_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bulk_action_logs`
--
ALTER TABLE `bulk_action_logs`
  ADD CONSTRAINT `bulk_action_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `calendar_sync_events`
--
ALTER TABLE `calendar_sync_events`
  ADD CONSTRAINT `calendar_sync_events_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `calendar_sync_events_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  ADD CONSTRAINT `classes_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_class_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `class_materials`
--
ALTER TABLE `class_materials`
  ADD CONSTRAINT `class_materials_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_materials_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `communication_credits`
--
ALTER TABLE `communication_credits`
  ADD CONSTRAINT `fk_credits_type` FOREIGN KEY (`type_id`) REFERENCES `communication_types` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `communication_usage_logs`
--
ALTER TABLE `communication_usage_logs`
  ADD CONSTRAINT `communication_usage_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_usage_log_type` FOREIGN KEY (`type_id`) REFERENCES `communication_types` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `daily_performance`
--
ALTER TABLE `daily_performance`
  ADD CONSTRAINT `daily_performance_ibfk_1` FOREIGN KEY (`roster_id`) REFERENCES `daily_rosters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `daily_performance_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `daily_rosters`
--
ALTER TABLE `daily_rosters`
  ADD CONSTRAINT `daily_rosters_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `daily_rosters_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `document_expiry_alerts`
--
ALTER TABLE `document_expiry_alerts`
  ADD CONSTRAINT `document_expiry_alerts_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_expiry_alerts_ibfk_2` FOREIGN KEY (`sent_to`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_versions`
--
ALTER TABLE `document_versions`
  ADD CONSTRAINT `document_versions_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_versions_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `email_template_channels`
--
ALTER TABLE `email_template_channels`
  ADD CONSTRAINT `email_template_channels_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `email_templates` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_template_gateway` FOREIGN KEY (`gateway_id`) REFERENCES `messaging_gateways` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD CONSTRAINT `fk_inquiries_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inquiries_education` FOREIGN KEY (`education_level_id`) REFERENCES `education_levels` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inquiries_priority` FOREIGN KEY (`priority_id`) REFERENCES `priority_levels` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inquiries_status` FOREIGN KEY (`status_id`) REFERENCES `inquiry_statuses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inquiry_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inquiries_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inquiry_score_history`
--
ALTER TABLE `inquiry_score_history`
  ADD CONSTRAINT `inquiry_score_history_ibfk_1` FOREIGN KEY (`inquiry_id`) REFERENCES `inquiries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inquiry_score_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`fee_type_id`) REFERENCES `fee_types` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `invoices_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messaging_campaigns`
--
ALTER TABLE `messaging_campaigns`
  ADD CONSTRAINT `fk_campaign_type` FOREIGN KEY (`type_id`) REFERENCES `communication_types` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messaging_gateways`
--
ALTER TABLE `messaging_gateways`
  ADD CONSTRAINT `fk_gateway_type` FOREIGN KEY (`type_id`) REFERENCES `communication_types` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messaging_queue`
--
ALTER TABLE `messaging_queue`
  ADD CONSTRAINT `fk_queue_type` FOREIGN KEY (`type_id`) REFERENCES `communication_types` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messaging_templates`
--
ALTER TABLE `messaging_templates`
  ADD CONSTRAINT `fk_template_type` FOREIGN KEY (`type_id`) REFERENCES `communication_types` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `partners`
--
ALTER TABLE `partners`
  ADD CONSTRAINT `fk_partners_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `performance_logs`
--
ALTER TABLE `performance_logs`
  ADD CONSTRAINT `performance_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `push_notification_logs`
--
ALTER TABLE `push_notification_logs`
  ADD CONSTRAINT `fk_push_logs_device` FOREIGN KEY (`device_id`) REFERENCES `user_devices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `qr_attendance_scans`
--
ALTER TABLE `qr_attendance_scans`
  ADD CONSTRAINT `qr_attendance_scans_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `qr_attendance_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `qr_attendance_scans_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `qr_attendance_sessions`
--
ALTER TABLE `qr_attendance_sessions`
  ADD CONSTRAINT `qr_attendance_sessions_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `qr_attendance_sessions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `saved_searches`
--
ALTER TABLE `saved_searches`
  ADD CONSTRAINT `saved_searches_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `security_logs`
--
ALTER TABLE `security_logs`
  ADD CONSTRAINT `security_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_documents`
--
ALTER TABLE `student_documents`
  ADD CONSTRAINT `fk_studoc_doctype` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`),
  ADD CONSTRAINT `fk_studoc_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_studoc_workflow` FOREIGN KEY (`workflow_id`) REFERENCES `visa_workflows` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `student_documents_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_fees`
--
ALTER TABLE `student_fees`
  ADD CONSTRAINT `student_fees_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_fees_ibfk_2` FOREIGN KEY (`fee_type_id`) REFERENCES `fee_types` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_logs`
--
ALTER TABLE `student_logs`
  ADD CONSTRAINT `fk_student_log_type` FOREIGN KEY (`type_id`) REFERENCES `communication_types` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `student_logs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_logs_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_workflow_progress`
--
ALTER TABLE `student_workflow_progress`
  ADD CONSTRAINT `student_workflow_progress_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_workflow_progress_ibfk_2` FOREIGN KEY (`template_id`) REFERENCES `workflow_templates` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_workflow_progress_ibfk_3` FOREIGN KEY (`current_step_id`) REFERENCES `workflow_steps` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `student_workflow_progress_ibfk_4` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `submissions`
--
ALTER TABLE `submissions`
  ADD CONSTRAINT `submissions_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `class_materials` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `submissions_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `fk_task_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `test_scores`
--
ALTER TABLE `test_scores`
  ADD CONSTRAINT `fk_scores_test_type` FOREIGN KEY (`test_type_id`) REFERENCES `test_types` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `test_scores_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `university_applications`
--
ALTER TABLE `university_applications`
  ADD CONSTRAINT `fk_application_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_applications_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_applications_partner` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_applications_status` FOREIGN KEY (`status_id`) REFERENCES `application_statuses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `university_applications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_education` FOREIGN KEY (`education_level_id`) REFERENCES `education_levels` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_activity`
--
ALTER TABLE `user_activity`
  ADD CONSTRAINT `user_activity_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_calendar_tokens`
--
ALTER TABLE `user_calendar_tokens`
  ADD CONSTRAINT `user_calendar_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_devices`
--
ALTER TABLE `user_devices`
  ADD CONSTRAINT `fk_user_devices_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_notification_preferences`
--
ALTER TABLE `user_notification_preferences`
  ADD CONSTRAINT `user_notification_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_notification_preferences_ibfk_2` FOREIGN KEY (`event_key`) REFERENCES `notification_events` (`event_key`) ON DELETE CASCADE;

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `visa_workflows`
--
ALTER TABLE `visa_workflows`
  ADD CONSTRAINT `fk_visa_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_visa_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_visa_stage` FOREIGN KEY (`stage_id`) REFERENCES `visa_stages` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `visa_workflows_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `visa_workflow_history`
--
ALTER TABLE `visa_workflow_history`
  ADD CONSTRAINT `fk_vwh_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vwh_from_stage` FOREIGN KEY (`from_stage_id`) REFERENCES `visa_stages` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_vwh_to_stage` FOREIGN KEY (`to_stage_id`) REFERENCES `visa_stages` (`id`) ON DELETE NO ACTION,
  ADD CONSTRAINT `fk_vwh_workflow` FOREIGN KEY (`workflow_id`) REFERENCES `visa_workflows` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `workflow_steps`
--
ALTER TABLE `workflow_steps`
  ADD CONSTRAINT `workflow_steps_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `workflow_templates` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `workflow_step_completions`
--
ALTER TABLE `workflow_step_completions`
  ADD CONSTRAINT `workflow_step_completions_ibfk_1` FOREIGN KEY (`progress_id`) REFERENCES `student_workflow_progress` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `workflow_step_completions_ibfk_2` FOREIGN KEY (`step_id`) REFERENCES `workflow_steps` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `workflow_step_completions_ibfk_3` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `workflow_templates`
--
ALTER TABLE `workflow_templates`
  ADD CONSTRAINT `fk_workflow_template_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `workflow_templates_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `cleanup_old_messaging_logs` ON SCHEDULE EVERY 1 DAY STARTS '2026-01-04 16:53:11' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM messaging_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
