-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 02, 2026 at 09:42 PM
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
-- Database: `pg`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `google2fa_enabled` tinyint(1) DEFAULT 0,
  `google2fa_secret` text DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `notification_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `category` enum('SYSTEM','USER','APPLICATION','SECURITY') NOT NULL DEFAULT 'SYSTEM',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `related_user_id` int(11) DEFAULT NULL,
  `actor_user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_recovery_codes`
--

CREATE TABLE `admin_recovery_codes` (
  `id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(255) NOT NULL,
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_reports`
--

CREATE TABLE `admin_reports` (
  `report_id` int(11) NOT NULL,
  `report_name` varchar(255) NOT NULL,
  `report_type` varchar(100) NOT NULL,
  `format` varchar(20) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_reports`
--

INSERT INTO `admin_reports` (`report_id`, `report_name`, `report_type`, `format`, `file_path`, `generated_by`, `created_at`) VALUES
(10, 'Faculty Breakdown - Feb 12, 2026 01:05', 'Faculty Breakdown', 'PDF', 'reports/report_20260212_010505.pdf', 4, '2026-02-12 00:05:05'),
(11, 'Admissions Summary - Feb 12, 2026 01:05', 'Admissions Summary', 'PDF', 'reports/report_20260212_010526.pdf', 4, '2026-02-12 00:05:27'),
(12, 'Faculty Breakdown - Feb 12, 2026 01:05', 'Faculty Breakdown', 'PDF', 'reports/report_20260212_010540.pdf', 4, '2026-02-12 00:05:40'),
(13, 'Programme Capacity - Feb 12, 2026 01:05', 'Programme Capacity', 'PDF', 'reports/report_20260212_010559.pdf', 4, '2026-02-12 00:05:59'),
(14, 'Programme Capacity - Feb 12, 2026 01:14', 'Programme Capacity', 'PDF', 'reports/report_20260212_011431_698d1b6702ecc818983596.pdf', 4, '2026-02-12 00:14:31'),
(15, 'Faculty Breakdown - Feb 12, 2026 01:14', 'Faculty Breakdown', 'PDF', 'reports/report_20260212_011450_698d1b7ac92c9721267889.pdf', 4, '2026-02-12 00:14:51'),
(16, 'Admissions Summary - Feb 12, 2026 01:15', 'Admissions Summary', 'PDF', 'reports/report_20260212_011509_698d1b8dc5efa649914316.pdf', 4, '2026-02-12 00:15:10'),
(17, 'Admissions Summary - Feb 12, 2026 01:15', 'Admissions Summary', 'EXCEL', 'reports/report_20260212_011539_698d1bab3b379264875552.csv', 4, '2026-02-12 00:15:39');

-- --------------------------------------------------------

--
-- Table structure for table `applicants`
--

CREATE TABLE `applicants` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `applicant_accounts`
--

CREATE TABLE `applicant_accounts` (
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `account_status` enum('Active','Suspended','Locked') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `applicant_notifications`
--

CREATE TABLE `applicant_notifications` (
  `notification_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `notification_title` varchar(200) NOT NULL,
  `notification_message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applicant_notifications`
--

INSERT INTO `applicant_notifications` (`notification_id`, `application_id`, `notification_title`, `notification_message`, `is_read`, `created_at`) VALUES
(6, 19, 'Welcome to JOSTUM', 'Thank you for choosing JOSTUM, please ensure you complete your application on time', 0, '2026-01-15 17:25:42'),
(7, 19, 'Application Accepted', 'Your application has been accepted for processing.', 0, '2026-01-25 14:29:48'),
(10, 19, 'Submitted', 'Your application is currently being processed.', 0, '2026-01-25 14:45:57'),
(11, 19, 'Application Rejected', 'Your application has been rejected.', 0, '2026-01-25 14:46:17'),
(16, 19, 'Referee Contacted', 'We have emailed your referee to complete verification.', 0, '2026-01-25 14:57:59'),
(19, 21, 'Application Accepted', 'Your application has been accepted for processing.', 0, '2026-02-07 11:29:43'),
(20, 21, 'Submitted', 'Your application is currently being processed.', 0, '2026-02-07 11:34:33'),
(21, 21, 'Referee Contacted', 'We have emailed your referee to complete verification.', 0, '2026-02-07 11:34:54'),
(22, 21, 'Referee Submitted', 'Your referee has submitted verification details.', 0, '2026-02-07 11:36:14'),
(23, 21, 'Referee Verified', 'Your referee submission has been verified.', 0, '2026-02-07 11:37:10'),
(24, 21, 'Admission Approved', 'Congratulations! Your admission has been approved.', 0, '2026-02-07 11:39:45'),
(25, 21, 'Referee Contacted', 'We have emailed your referee to complete verification.', 0, '2026-02-07 12:07:45'),
(26, 21, 'Referee Contacted', 'We have emailed your referee to complete verification.', 0, '2026-02-07 12:07:53'),
(27, 19, 'Referee Contacted', 'We have emailed your referee to complete verification.', 0, '2026-02-07 12:08:40'),
(28, 19, 'Referee Contacted', 'We have emailed your referee to complete verification.', 0, '2026-02-07 12:08:47');

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `application_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `application_number` varchar(50) DEFAULT NULL,
  `status` enum('Draft','Submitted','Admitted','Rejected') DEFAULT 'Draft',
  `current_step` int(11) DEFAULT 1,
  `submitted_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `department_id` int(11) DEFAULT NULL,
  `reviewer_id` int(11) DEFAULT NULL,
  `completion_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `current_status` varchar(60) DEFAULT 'DRAFT'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`application_id`, `user_id`, `application_number`, `status`, `current_step`, `submitted_at`, `updated_at`, `department_id`, `reviewer_id`, `completion_percentage`, `current_status`) VALUES
(19, 1, 'PG/2026/A7B92', 'Rejected', 10, '2026-01-17 10:54:06', '2026-01-25 14:46:17', NULL, NULL, 0.00, 'ADMISSION_REJECTED'),
(21, 25, 'PG-2026-0021', 'Admitted', 10, '2026-02-07 12:23:54', '2026-02-07 16:24:16', 3, NULL, 0.00, 'ADMISSION_APPROVED'),
(22, 27, 'APP-2026-D6ED51', 'Draft', 3, NULL, '2026-07-02 18:21:57', NULL, NULL, 30.00, 'DRAFT');

-- --------------------------------------------------------

--
-- Table structure for table `application_progress`
--

CREATE TABLE `application_progress` (
  `progress_id` bigint(20) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED NOT NULL,
  `stage` enum('Application Submitted','Documents Verified','Academic Review','Referee Reports','Final Decision') NOT NULL,
  `stage_status` enum('Pending','In Progress','Completed') NOT NULL DEFAULT 'Pending',
  `stage_updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `application_progress`
--

INSERT INTO `application_progress` (`progress_id`, `application_id`, `stage`, `stage_status`, `stage_updated_at`) VALUES
(1, 20, 'Application Submitted', 'Completed', '2026-01-25 12:56:09'),
(2, 21, 'Application Submitted', 'Completed', '2026-02-07 11:23:54');

-- --------------------------------------------------------

--
-- Table structure for table `application_status`
--

CREATE TABLE `application_status` (
  `status_id` bigint(20) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED NOT NULL,
  `public_status` enum('Submitted','Under Review','Shortlisted','Decision Made','Admitted','Not Admitted','Deferred') NOT NULL DEFAULT 'Submitted',
  `internal_status` enum('Draft','Submitted','Document Verification','Academic Review','Referee Review','Committee Review','Final Decision') NOT NULL DEFAULT 'Submitted',
  `status_updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` bigint(20) UNSIGNED NOT NULL,
  `actor_user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `entity` varchar(120) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `severity` enum('INFO','WARNING','CRITICAL') NOT NULL DEFAULT 'INFO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chapter_submissions`
--

CREATE TABLE `chapter_submissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_user_id` int(11) NOT NULL,
  `application_id` int(11) DEFAULT NULL,
  `application_number` varchar(50) DEFAULT NULL,
  `chapter_no` tinyint(4) NOT NULL,
  `chapter_label` varchar(100) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_ext` varchar(10) DEFAULT NULL,
  `status` enum('Submitted','Under Review','Changes Requested','Approved') NOT NULL DEFAULT 'Submitted',
  `supervisor_note` text DEFAULT NULL,
  `supervisor_user_id` int(11) DEFAULT NULL,
  `review_file_path` varchar(255) DEFAULT NULL,
  `version_no` int(11) NOT NULL DEFAULT 1,
  `submitted_at` datetime NOT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chapter_submissions`
--

INSERT INTO `chapter_submissions` (`id`, `student_user_id`, `application_id`, `application_number`, `chapter_no`, `chapter_label`, `file_path`, `file_name`, `file_ext`, `status`, `supervisor_note`, `supervisor_user_id`, `review_file_path`, `version_no`, `submitted_at`, `reviewed_at`, `updated_at`) VALUES
(1, 25, 21, 'PG-2026-0021', 1, 'CHAPTER 1', 'uploads/supervision/chapter-1/chapter-1_20260208_124724_form-a-academic-portfolio.docx', 'chapter-1_20260208_124724_form-a-academic-portfolio.docx', 'docx', 'Approved', 'Proceed to Chapter 2', 26, NULL, 1, '2026-02-08 12:47:24', '2026-02-08 13:09:57', '2026-02-08 12:09:57'),
(2, 25, 21, 'PG-2026-0021', 2, 'CHAPTER 2', 'uploads/supervision/chapter-2/chapter-2_20260208_202850_Revised_Project_Format_2024_020300.pdf', 'chapter-2_20260208_202850_Revised_Project_Format_2024_020300.pdf', 'pdf', 'Changes Requested', 'Kindly take effect', 26, NULL, 1, '2026-02-08 20:28:50', '2026-02-08 20:35:58', '2026-02-08 19:35:58');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_title` varchar(255) NOT NULL,
  `dept_id` int(11) NOT NULL,
  `degree_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course_title`, `dept_id`, `degree_id`, `created_at`) VALUES
(8, 'PGD PROCUREMENT MANAGEMENT', 6, 4, '2026-07-02 18:02:05'),
(9, 'MSC PROCUREMENT MANAGEMENT', 6, 2, '2026-07-02 18:02:24'),
(10, 'PGD ENVIRONMENTAL SUSTAINABILITY', 7, 4, '2026-07-02 18:02:56'),
(11, 'MSC ENVIRONMENTAL SUSTAINABILITY', 7, 2, '2026-07-02 18:03:08'),
(12, 'PGD SUSTAINABLE SOCIAL DEVELOPEMENT', 8, 4, '2026-07-02 18:03:42'),
(13, 'MSC SUSTAINABLE SOCIAL DEVELOPEMENT', 8, 2, '2026-07-02 18:03:51');

-- --------------------------------------------------------

--
-- Table structure for table `degree_types`
--

CREATE TABLE `degree_types` (
  `degree_id` int(11) NOT NULL,
  `degree_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `degree_types`
--

INSERT INTO `degree_types` (`degree_id`, `degree_name`) VALUES
(2, 'Msc'),
(4, 'PGD');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `dept_id` int(11) NOT NULL,
  `dept_name` varchar(100) NOT NULL,
  `faculty_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`dept_id`, `dept_name`, `faculty_id`) VALUES
(5, 'Agronomy', 5),
(3, 'Business', 3),
(1, 'Computer Science', 1),
(7, 'Department of Environmental Standards', 6),
(6, 'Department of Procurement Standards', 6),
(8, 'Department of Social Standards', 6);

-- --------------------------------------------------------

--
-- Table structure for table `dept_applications`
--

CREATE TABLE `dept_applications` (
  `app_code` varchar(50) NOT NULL,
  `applicant_name` varchar(150) NOT NULL,
  `programme` varchar(150) NOT NULL,
  `status` varchar(50) NOT NULL,
  `reviewer_name` varchar(150) DEFAULT NULL,
  `submitted_date` date DEFAULT NULL,
  `priority` varchar(20) DEFAULT 'Normal',
  `department` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dept_applications`
--

INSERT INTO `dept_applications` (`app_code`, `applicant_name`, `programme`, `status`, `reviewer_name`, `submitted_date`, `priority`, `department`, `updated_at`) VALUES
('APP-2024-001', 'Aisha Umar', 'M.Sc Computer Science', 'Under Review', 'Dr. Hadiza Abubakar', '2024-01-10', 'High', 'Computer Science', '2026-01-24 11:06:22'),
('APP-2024-002', 'Ibrahim Sule', 'Ph.D Computer Science', 'Reviewer Assigned', 'Prof. Chinedu Okafor', '2024-01-08', 'Medium', 'Computer Science', '2026-01-24 11:06:22'),
('APP-2024-003', 'Fatima Bello', 'M.Sc Data Science', 'Department Approved', 'Dr. Uche Nwosu', '2024-01-05', 'Normal', 'Computer Science', '2026-01-24 11:06:23');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `doc_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `document_type` varchar(50) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
  `comments` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`doc_id`, `application_id`, `document_type`, `file_path`, `uploaded_at`, `status`, `comments`) VALUES
(162, 19, 'passport', 'uploads/passports/passport_19_1768504091.jpg', '2026-01-15 19:00:35', 'Pending', NULL),
(182, 19, 'olevel_1', 'uploads/olevel/olevel_1_19_1768643527.pdf', '2026-01-17 09:52:07', 'Pending', NULL),
(183, 19, 'degree', 'uploads/degree/degree_19_1768643527.pdf', '2026-01-17 09:52:07', 'Pending', NULL),
(184, 19, 'transcript', 'uploads/transcripts/transcript_19_1768643527.pdf', '2026-01-17 09:52:07', 'Pending', NULL),
(185, 19, 'nysc', 'uploads/nysc/nysc_19_1768643527.pdf', '2026-01-17 09:52:07', 'Pending', NULL),
(200, 21, 'passport', 'uploads/passports/passport_21_1770463356.png', '2026-02-07 11:22:36', 'Pending', NULL),
(201, 21, 'passport_profile', 'uploads/passports/passport_profile_21_1770463356.jpg', '2026-02-07 11:22:36', 'Pending', NULL),
(202, 21, 'olevel_1', 'uploads/olevel/olevel_1_21_1770463356.jpg', '2026-02-07 11:22:36', 'Pending', NULL),
(203, 21, 'degree', 'uploads/degree/degree_21_1770463356.jpg', '2026-02-07 11:22:36', 'Pending', NULL),
(204, 21, 'transcript', 'uploads/transcripts/transcript_21_1770463357.jpg', '2026-02-07 11:22:37', 'Pending', NULL),
(205, 21, 'nysc', 'uploads/nysc/nysc_21_1770463357.jpg', '2026-02-07 11:22:37', 'Pending', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `document_verification`
--

CREATE TABLE `document_verification` (
  `verification_id` bigint(20) UNSIGNED NOT NULL,
  `upload_id` bigint(20) UNSIGNED NOT NULL,
  `verification_status` enum('Pending','Verified','Re-upload Required') NOT NULL DEFAULT 'Pending',
  `verified_by` bigint(20) UNSIGNED DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `admin_remark` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_verification`
--

INSERT INTO `document_verification` (`verification_id`, `upload_id`, `verification_status`, `verified_by`, `verified_at`, `admin_remark`) VALUES
(1, 162, 'Verified', 4, '2026-01-24 12:15:41', ''),
(2, 183, 'Re-upload Required', 4, '2026-01-24 12:34:00', 'Reason: illegible. '),
(3, 196, 'Verified', 4, '2026-01-25 13:20:41', ''),
(4, 196, 'Verified', 4, '2026-01-25 13:20:54', ''),
(5, 198, 'Verified', 4, '2026-01-25 13:21:46', ''),
(6, 195, 'Verified', 4, '2026-01-25 13:22:06', ''),
(7, 194, 'Verified', 4, '2026-01-25 13:22:16', ''),
(8, 199, 'Verified', 4, '2026-01-25 13:22:26', ''),
(9, 197, 'Verified', 4, '2026-01-25 13:22:39', ''),
(10, 193, 'Verified', 4, '2026-01-25 13:22:51', ''),
(11, 205, 'Verified', 4, '2026-02-07 11:30:23', ''),
(12, 204, 'Verified', 4, '2026-02-07 11:30:33', ''),
(13, 203, 'Verified', 4, '2026-02-07 11:30:57', ''),
(14, 202, 'Verified', 4, '2026-02-07 11:33:14', ''),
(15, 200, 'Verified', 4, '2026-02-07 11:33:23', ''),
(16, 201, 'Verified', 4, '2026-02-07 11:33:46', '');

-- --------------------------------------------------------

--
-- Table structure for table `faculties`
--

CREATE TABLE `faculties` (
  `faculty_id` int(11) NOT NULL,
  `faculty_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculties`
--

INSERT INTO `faculties` (`faculty_id`, `faculty_name`) VALUES
(3, 'Business Adminstration'),
(6, 'Institute of Procurement, Environmental and Social Standards (IPESS)'),
(5, 'Life Sciences'),
(4, 'PGD'),
(1, 'Sciences');

-- --------------------------------------------------------

--
-- Table structure for table `higher_education`
--

CREATE TABLE `higher_education` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `highest_qualification` varchar(50) DEFAULT NULL,
  `course_study` varchar(150) DEFAULT NULL,
  `institution` varchar(150) DEFAULT NULL,
  `grad_year` int(11) DEFAULT NULL,
  `cgpa` decimal(4,2) DEFAULT NULL,
  `mode_study` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `higher_education`
--

INSERT INTO `higher_education` (`id`, `application_id`, `highest_qualification`, `course_study`, `institution`, `grad_year`, `cgpa`, `mode_study`) VALUES
(1, 19, 'PhD', 'Computer Science', 'Modibbo Adama University ', 2022, 4.24, 'FT'),
(5, 21, 'BSc', 'CS', 'FPM', 2011, 3.00, 'FT');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nysc_details`
--

CREATE TABLE `nysc_details` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `nysc_status` varchar(50) DEFAULT NULL,
  `certificate_number` varchar(50) DEFAULT NULL,
  `completion_year` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nysc_details`
--

INSERT INTO `nysc_details` (`id`, `application_id`, `nysc_status`, `certificate_number`, `completion_year`) VALUES
(1, 19, 'Completed', 'A83773737', 2022),
(5, 21, 'Completed', 'A8467284', 2017);

-- --------------------------------------------------------

--
-- Table structure for table `olevel_exams`
--

CREATE TABLE `olevel_exams` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `sitting_number` tinyint(4) NOT NULL COMMENT '1 for First Sitting, 2 for Second Sitting',
  `exam_type` enum('WAEC','NECO','NABTEB','GCE') NOT NULL,
  `school_name` varchar(255) NOT NULL,
  `exam_year` year(4) NOT NULL,
  `exam_number` varchar(50) NOT NULL COMMENT 'Registration Number',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `olevel_exams`
--

INSERT INTO `olevel_exams` (`id`, `application_id`, `sitting_number`, `exam_type`, `school_name`, `exam_year`, `exam_number`, `created_at`) VALUES
(1, 19, 1, 'WAEC', 'GDSS ARMY BARRACKS YOLA', '2022', '98389289', '2026-01-15 17:39:28'),
(5, 21, 1, 'WAEC', 'DSS', '2012', '34432245', '2026-02-07 11:19:04');

-- --------------------------------------------------------

--
-- Table structure for table `olevel_results`
--

CREATE TABLE `olevel_results` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `grade` char(2) NOT NULL COMMENT 'e.g., A1, B3, C6, F9'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `olevel_results`
--

INSERT INTO `olevel_results` (`id`, `exam_id`, `subject_name`, `grade`) VALUES
(2, 1, 'Agricultural Science', 'C4'),
(13, 5, 'English Language', 'A1'),
(14, 5, 'Mathematics', 'A1'),
(15, 5, 'Biology', 'B2'),
(16, 5, 'Chemistry', 'B3'),
(17, 5, 'Physics', 'C4'),
(18, 5, 'Civic Education', 'A1'),
(19, 5, 'Agricultural Science', 'A1'),
(20, 5, 'ICT', 'C5'),
(21, 5, 'Physical & Health Education', 'C5');

-- --------------------------------------------------------

--
-- Table structure for table `olevel_sittings`
--

CREATE TABLE `olevel_sittings` (
  `sitting_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `sitting_number` int(11) NOT NULL,
  `exam_year` int(11) DEFAULT NULL,
  `exam_type` varchar(50) DEFAULT NULL,
  `school_name` varchar(150) DEFAULT NULL,
  `exam_type_other` varchar(100) DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `email`, `token_hash`, `expires_at`, `created_at`) VALUES
(1, 5, 'teckexpert4solutions.me@gmail.com', '3306e92049ffc4405e58bb2d5618a7d1f0247f9a1527cdcaee763aa9c219a42d', '2026-02-06 13:46:02', '2026-02-06 11:46:02'),
(2, 6, 'teckexpert4solutions.me@gmail.com', '6f39172f53c8cf24fa0c9771ead9355a9473330dceb4651a60c24b7fd2de9545', '2026-02-07 16:07:49', '2026-02-06 15:07:55'),
(3, 7, 'teckexpert4solutions.me@gmail.com', 'f48d107f97694055d3645265055a73cbb96de9c6b8009a9989424e90e29fcfbe', '2026-02-07 16:27:03', '2026-02-06 15:27:03'),
(4, 8, 'teckexpert4solutions.me@gmail.com', '0950beb41c8c396cc6249725f0e6214f9716262b79a8dba1669e86e8e731f99f', '2026-02-07 16:38:25', '2026-02-06 15:38:26'),
(5, 9, 'teckexpert4solutions.me@gmail.com', '87c5a7337d5c1804af195c89dc532763d6ad844495452af0616b618924e88587', '2026-02-07 17:07:52', '2026-02-06 16:07:53'),
(6, 10, 'teckexpert4solutions.me@gmail.com', '7033c12d3ffc283b8af523c7407e08a8d7567f208a0c9096655943487b39031b', '2026-02-07 17:15:07', '2026-02-06 16:15:07'),
(7, 11, 'teckexpert4solutions.me@gmail.com', 'aeffe6c85013b3cf54f0b728cac7438fac628c6cbf00b8f2de13c0228fd9dfef', '2026-02-07 17:26:59', '2026-02-06 16:27:00'),
(8, 12, 'teckexpert4solutions.me@gmail.com', '9ebbf21567cadf4c47f3cf2a734ad8bfb759068a4ec7c974d5e5e48d4d32eacf', '2026-02-07 17:32:34', '2026-02-06 16:32:34'),
(9, 13, 'teckexpert4solutions.me@gmail.com', '402bd67fc2a262c8d2e1fdb5b0f6b01aa92ba750a6f82dd2311cfc97454ebc6b', '2026-02-07 17:41:49', '2026-02-06 16:41:50'),
(10, 13, 'teckexpert4solutions.me@gmail.com', '7d6e013c4b5291c6861c46d2e650b99fb64f1ac7d290d424dc9d689f21af7b6c', '2026-02-07 17:42:26', '2026-02-06 16:42:26'),
(11, 13, 'teckexpert4solutions.me@gmail.com', '6c2a9d595ff267ec7ac369f4b6a726d479eb714ff18b19731cd2ada7e16ae13d', '2026-02-07 17:42:43', '2026-02-06 16:42:43'),
(12, 13, 'teckexpert4solutions.me@gmail.com', 'a5783eb96c5ca0f991af7da32865990862124d11edd0b66bd162630d06bb483e', '2026-02-07 17:43:30', '2026-02-06 16:43:34'),
(13, 14, 'teckexpert4solutions.me@gmail.com', '8ca7925df9eb1bbb09cb1d073c643b345065112d26b8965fab714a7726741eaf', '2026-02-07 17:45:16', '2026-02-06 16:45:16'),
(14, 14, 'teckexpert4solutions.me@gmail.com', 'c7260e4bfc7c753abbdd5c206c53e7a4a5cecdd32158745b61ee88c3ebc1425d', '2026-02-07 17:53:52', '2026-02-06 16:53:52'),
(15, 15, 'teckexpert4solutions.me@gmail.com', 'ac7978107c95afedcaeae64295b4fa9f4c1c5b3c97853a062db83ac6b0742cce', '2026-02-07 17:54:28', '2026-02-06 16:54:29'),
(16, 15, 'teckexpert4solutions.me@gmail.com', '51bb84b5174346e219c2110badc5297c90574cc337ecb0f20c48c2d84c608ca0', '2026-02-07 17:54:49', '2026-02-06 16:54:50'),
(17, 15, 'teckexpert4solutions.me@gmail.com', '0df0084da32f005ab2795910552f9b6b78b718cd779e84a521eddc667a8788ef', '2026-02-07 17:54:51', '2026-02-06 16:54:51'),
(18, 15, 'teckexpert4solutions.me@gmail.com', 'c921e7faa54e911e6a666f2b94969b813cdaea1228d8b1ef47b46fd0d35762b7', '2026-02-07 17:54:52', '2026-02-06 16:54:52'),
(19, 15, 'teckexpert4solutions.me@gmail.com', '2f466bdf0eaa3645ba2a41d9f0949749cfe989434bda1bcfdd0ebc5bad307537', '2026-02-07 17:54:52', '2026-02-06 16:54:52'),
(20, 15, 'teckexpert4solutions.me@gmail.com', '63cd0761635434e290b3349bd080275b1bed4cb2ba0553e8523a7c8ad12bcf40', '2026-02-07 17:54:52', '2026-02-06 16:54:53'),
(21, 15, 'teckexpert4solutions.me@gmail.com', '87a95c25d440823096320aac6e389e9328d03ebb553eb16d1d6b9ce8554239b5', '2026-02-07 17:54:53', '2026-02-06 16:54:53'),
(22, 15, 'teckexpert4solutions.me@gmail.com', '336345fde04ffe669eaa98e1042487390a9c70470b52604831ee18065fe1b49c', '2026-02-07 17:54:53', '2026-02-06 16:54:53'),
(23, 15, 'teckexpert4solutions.me@gmail.com', '64385437cb4b62b5b2df4bfd3ab7dd6036c71cf615af49f4a1287cf1879d18f6', '2026-02-07 17:54:53', '2026-02-06 16:54:53'),
(24, 15, 'teckexpert4solutions.me@gmail.com', 'd68ec6c8915d4e00e30fe2001c9129485501c78ff5ff2c0f579f402ff7f0da11', '2026-02-07 17:54:53', '2026-02-06 16:54:53'),
(25, 15, 'teckexpert4solutions.me@gmail.com', '1f70b38a4c10af54750e0df1f285879d36104a8ad1d5a1b5346f995397ba85ea', '2026-02-07 17:54:53', '2026-02-06 16:54:54'),
(26, 15, 'teckexpert4solutions.me@gmail.com', '649ff1c6b773ac87440fc376c09db1e572d220f58f1de185f15089c85481d3b7', '2026-02-07 17:54:53', '2026-02-06 16:54:54'),
(27, 15, 'teckexpert4solutions.me@gmail.com', 'a8f279f455e56570b77cef9f465053a1dd9184f8809f8a11fb5ab570247ce570', '2026-02-07 17:54:53', '2026-02-06 16:54:54'),
(28, 15, 'teckexpert4solutions.me@gmail.com', '6c572b532be778dd9eec3c26f8a244280de675761dc43954395f30466017dce6', '2026-02-07 17:54:54', '2026-02-06 16:54:54'),
(29, 16, 'teckexpert4solutions.me@gmail.com', '8b2262d826860698c4e97f1d30d1cf7ef4b4ecc9b385105b1084cebadc9d2ebe', '2026-02-07 17:57:39', '2026-02-06 16:57:39'),
(30, 17, 'teckexpert4solutions.me@gmail.com', 'f2ca4e5994053164fdff9a01d1f49f713f3daaa1192b303cf05aa9d8252e97d8', '2026-02-07 18:02:53', '2026-02-06 17:02:54'),
(31, 17, 'teckexpert4solutions.me@gmail.com', 'cc8718f56f857f5ff8899c397dfcc2f485ecbba7c11e441a7058abda238287f3', '2026-02-07 18:03:01', '2026-02-06 17:03:01'),
(32, 17, 'teckexpert4solutions.me@gmail.com', '756ce3ab64239217a857efcfe54c9f33135035e543ac94110be6f135e5d97181', '2026-02-07 18:03:03', '2026-02-06 17:03:03'),
(33, 17, 'teckexpert4solutions.me@gmail.com', '159cef3d3c3fca45089716a68077ac89a49a16ac723c5837cb3ed3afe0104ec7', '2026-02-07 18:03:05', '2026-02-06 17:03:05'),
(34, 17, 'teckexpert4solutions.me@gmail.com', '7b478718778011193683c79993d47844b8e8b23384d0bfe696db20979e4b356c', '2026-02-07 18:03:05', '2026-02-06 17:03:05'),
(35, 17, 'teckexpert4solutions.me@gmail.com', 'affc4ba2c0ce5141b90126d07783420a0312c697d07598ac331edb32f7504f56', '2026-02-07 18:03:05', '2026-02-06 17:03:06'),
(36, 17, 'teckexpert4solutions.me@gmail.com', 'ea64ffa7495057eaa09bdbf0aa7258bbfc5e78bebbd15fbf56d72bef6e481594', '2026-02-07 18:03:06', '2026-02-06 17:03:06'),
(37, 17, 'teckexpert4solutions.me@gmail.com', '211acc19a432d6b31b27953dced30d5f68fbb338b68094ccaf5a15876aa52459', '2026-02-07 18:03:06', '2026-02-06 17:03:06'),
(38, 17, 'teckexpert4solutions.me@gmail.com', '3bc58d75975e1235422cac12d2fd726f2d7905801b895a778dccb12652f0864e', '2026-02-07 18:03:06', '2026-02-06 17:03:06'),
(39, 17, 'teckexpert4solutions.me@gmail.com', 'e8e944b220e42927ece2fbf2a16065ba3cf571e751c25a1a9ab7874273517029', '2026-02-07 18:03:06', '2026-02-06 17:03:06'),
(40, 17, 'teckexpert4solutions.me@gmail.com', 'ddcc05025a5b937e65eee31ac6d2ea75423df9eae83e6e09520172890becf6cf', '2026-02-07 18:03:13', '2026-02-06 17:03:13'),
(41, 18, 'teckexpert4solutions.me@gmail.com', '0d1ceed8822534a6bdee36289e695df82f9f91998b95ca00836d7df9cefcabbb', '2026-02-07 18:14:29', '2026-02-06 17:14:32'),
(42, 18, 'teckexpert4solutions.me@gmail.com', 'de385b53ab528ffaa278f46efd7e702dbade2d9a7a0be8098abf246bb5cf0ed8', '2026-02-07 18:14:43', '2026-02-06 17:14:44'),
(43, 18, 'teckexpert4solutions.me@gmail.com', 'd0e5c33142aa68204298327c1f879074ff5aaf4e04d2bd192098652010b241b1', '2026-02-07 18:16:22', '2026-02-06 17:16:22'),
(44, 19, 'teckexpert4solutions.me@gmail.com', '65b0c6327c7fc6538943428745f9efefebce4ef10004e5cebcd4d5a29548375b', '2026-02-07 18:21:49', '2026-02-06 17:21:50'),
(45, 20, 'teckexpert4solutions.me@gmail.com', '7b4f10dad505e71e53d863ca2583f5494cac149edc17b2c664cad6ede70bd3ee', '2026-02-07 18:39:19', '2026-02-06 17:39:19'),
(46, 21, 'teckexpert4solutions.me@gmail.com', 'd855375d5dc75ce1aa22b6f536745aa6993b7c1c6448e688b9bd4b0aa0876d1c', '2026-02-07 19:18:25', '2026-02-06 18:18:25'),
(47, 21, 'teckexpert4solutions.me@gmail.com', '08e3f32b93cf22e4b7dc3b9e4440bc5a9bf20de58995e3ef630ae3db00293fa1', '2026-02-07 19:18:49', '2026-02-06 18:18:52'),
(49, 23, 'teckexpert4solutions.me@gmail.com', '1dc62396345ec4998c7255628c3e58903585b91cc931f193721e5430029c3fa8', '2026-02-08 10:24:49', '2026-02-07 09:24:50'),
(50, 24, 'teckexpert4solutions.me@gmail.com', '0a6e675f549899d15aedabbc3b77a3374b48268efebdc404133b5138ec78c036', '2026-02-08 10:28:49', '2026-02-07 09:28:50'),
(53, 4, 'muhdmukhtar2019@gmail.com', '7b8dfbeaf667eed5fa2f53ee95df6ead2450251c0520c77799c14537e372dcf6', '2026-02-11 21:10:33', '2026-02-11 19:10:33');

-- --------------------------------------------------------

--
-- Table structure for table `personal_details`
--

CREATE TABLE `personal_details` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `surname` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `other_name` varchar(100) DEFAULT NULL,
  `dob` date NOT NULL,
  `sex` enum('Male','Female','Other') DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `state_origin` varchar(100) DEFAULT NULL,
  `lga` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `personal_details`
--

INSERT INTO `personal_details` (`id`, `application_id`, `surname`, `first_name`, `other_name`, `dob`, `sex`, `nationality`, `state_origin`, `lga`, `phone`, `address`) VALUES
(2, 19, 'UMAR', 'RILWANU', '', '2026-01-15', 'Male', 'Nigerian', 'Adamawa', 'Madagali', '09042340091', 'Karewa Ward Bachure Near Jibwis Mosque'),
(6, 21, 'UMAR', 'GADDAFI', '', '1987-12-12', 'Male', 'Nigerian', 'Adamawa', 'Mubi North', '09089898989', 'Ahmadu Bello Way'),
(7, 22, 'Philip', 'Omolaye', '', '1900-01-01', 'Male', 'Nigerian', 'Ebonyi', 'Ivo', '08034266266', 'Good');

-- --------------------------------------------------------

--
-- Table structure for table `programme_capacities`
--

CREATE TABLE `programme_capacities` (
  `capacity_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `programme_capacities`
--

INSERT INTO `programme_capacities` (`capacity_id`, `course_id`, `capacity`, `is_active`, `created_at`) VALUES
(1, 4, 3, 1, '2026-01-18 15:12:15'),
(2, 7, 10, 1, '2026-01-21 04:58:18');

-- --------------------------------------------------------

--
-- Table structure for table `programme_choices`
--

CREATE TABLE `programme_choices` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `faculty` int(11) DEFAULT NULL,
  `department` int(11) DEFAULT NULL,
  `degree_type` int(11) DEFAULT NULL,
  `mode_of_study` int(11) DEFAULT NULL,
  `course` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `programme_choices`
--

INSERT INTO `programme_choices` (`id`, `application_id`, `faculty`, `department`, `degree_type`, `mode_of_study`, `course`) VALUES
(1, 19, 3, 3, 2, 2, 4),
(4, 21, 3, 3, 2, 1, 5),
(5, 22, 6, 7, 2, 1, 11);

-- --------------------------------------------------------

--
-- Table structure for table `referees`
--

CREATE TABLE `referees` (
  `referee_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `organization` varchar(150) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `referees`
--

INSERT INTO `referees` (`referee_id`, `application_id`, `full_name`, `title`, `organization`, `email`, `phone`) VALUES
(2, 19, 'Rilwanu Umar', 'Professor ', 'JOSTUM', 'rilwanumar29@gmail.com', '09042340091'),
(5, 21, 'Muhammad Adamu Garba', 'PROFF', 'JOSTUM', 'asmaugella@gmail.com', '07065883821');

-- --------------------------------------------------------

--
-- Table structure for table `referee_requests`
--

CREATE TABLE `referee_requests` (
  `request_id` int(11) NOT NULL,
  `referee_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `token` varchar(100) NOT NULL,
  `status` enum('Requested','Submitted','Verified','Rejected') DEFAULT 'Requested',
  `requested_by` int(11) DEFAULT NULL,
  `requested_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `referee_requests`
--

INSERT INTO `referee_requests` (`request_id`, `referee_id`, `application_id`, `token`, `status`, `requested_by`, `requested_at`, `expires_at`) VALUES
(2, 2, 19, '837a84601b4e81e3c3b8431911e689ec1b300a5b', 'Requested', 4, '2026-01-25 15:57:54', '2026-02-01 15:57:54'),
(3, 5, 21, '2adc46c17e9510f906a313f309f6c086e933c316', 'Submitted', 4, '2026-02-07 12:34:45', '2026-02-14 12:34:45'),
(4, 5, 21, '111d24dfb5a5105657c46e8e67422d4eb7d70882', 'Requested', 4, '2026-02-07 13:07:39', '2026-02-14 13:07:39'),
(5, 5, 21, '57728a34e87ad9203c38a88bb29e6689742d7bb5', 'Requested', 4, '2026-02-07 13:07:49', '2026-02-14 13:07:49'),
(6, 2, 19, '6336b3e1fad66cd36af2d0be0784c658b60ce38d', 'Requested', 4, '2026-02-07 13:08:36', '2026-02-14 13:08:36'),
(7, 2, 19, 'f40018e424cc6dda49dc4a2f1c3acc7baa539a1d', 'Requested', 4, '2026-02-07 13:08:44', '2026-02-14 13:08:44');

-- --------------------------------------------------------

--
-- Table structure for table `referee_status`
--

CREATE TABLE `referee_status` (
  `referee_id` bigint(20) UNSIGNED NOT NULL,
  `submission_status` enum('Not Submitted','Submitted','Received','Verified') NOT NULL DEFAULT 'Not Submitted',
  `received_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referee_uploads`
--

CREATE TABLE `referee_uploads` (
  `upload_id` int(11) NOT NULL,
  `referee_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `work_email` varchar(150) DEFAULT NULL,
  `passport_path` varchar(255) DEFAULT NULL,
  `work_id_path` varchar(255) DEFAULT NULL,
  `verified_status` enum('Submitted','Verified','Rejected') DEFAULT 'Submitted',
  `submitted_at` datetime DEFAULT current_timestamp(),
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `referee_uploads`
--

INSERT INTO `referee_uploads` (`upload_id`, `referee_id`, `application_id`, `work_email`, `passport_path`, `work_id_path`, `verified_status`, `submitted_at`, `verified_by`, `verified_at`, `rejection_reason`) VALUES
(2, 5, 21, 'referee1@jostum.edu.ng', 'uploads/referee_passports/1770464174_icon_192.png', 'uploads/referee_ids/1770464174_sample_of_induction.jpg', 'Verified', '2026-02-07 12:36:14', 4, '2026-02-07 12:37:10', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `research_details`
--

CREATE TABLE `research_details` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `research_area` varchar(255) DEFAULT NULL,
  `reason_for_choosing` text DEFAULT NULL,
  `statement_of_purpose` text DEFAULT NULL,
  `career_objectives` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `research_details`
--

INSERT INTO `research_details` (`id`, `application_id`, `research_area`, `reason_for_choosing`, `statement_of_purpose`, `career_objectives`) VALUES
(1, 19, 'alal', 'lalaklk', 'klalakl', 'lkaklalk'),
(5, 21, 'ML AND DATA SCIENCE', 'GOOD', 'GOOD', 'GOOD');

-- --------------------------------------------------------

--
-- Table structure for table `reviewer_assignments`
--

CREATE TABLE `reviewer_assignments` (
  `assignment_id` int(11) NOT NULL,
  `application_code` varchar(50) NOT NULL,
  `applicant_name` varchar(150) NOT NULL,
  `programme` varchar(150) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'Pending',
  `due_date` date DEFAULT NULL,
  `reviewer_name` varchar(150) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviewer_feedback`
--

CREATE TABLE `reviewer_feedback` (
  `feedback_id` int(11) NOT NULL,
  `application_code` varchar(50) NOT NULL,
  `student_name` varchar(150) NOT NULL,
  `chapter` varchar(50) DEFAULT NULL,
  `feedback` text NOT NULL,
  `status` varchar(30) DEFAULT 'Awaiting Response',
  `reviewer_name` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviewer_history`
--

CREATE TABLE `reviewer_history` (
  `history_id` int(11) NOT NULL,
  `application_code` varchar(50) NOT NULL,
  `applicant_name` varchar(150) NOT NULL,
  `programme` varchar(150) DEFAULT NULL,
  `decision` varchar(30) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `reviewer_name` varchar(150) DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_key` varchar(50) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_key`, `role_name`, `created_at`) VALUES
(1, 'SUPER_ADMIN', 'Super Admin', '2026-01-18 10:47:08'),
(2, 'ADMIN', 'Admin', '2026-01-18 10:47:08'),
(3, 'DEPARTMENT_ADMIN', 'Departmental Admin', '2026-01-18 10:47:08'),
(4, 'SUPERVISOR', 'Supervisor', '2026-01-18 10:47:08'),
(5, 'REVIEWER', 'Reviewer', '2026-01-18 10:47:08');

-- --------------------------------------------------------

--
-- Table structure for table `student_messages`
--

CREATE TABLE `student_messages` (
  `message_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_notifications`
--

CREATE TABLE `student_notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_notifications`
--

INSERT INTO `student_notifications` (`id`, `student_user_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 25, 'Chapter 1 Review', 'Chapter approved. You can proceed to the next chapter.', 0, '2026-02-08 12:09:57'),
(2, 25, 'Update', 'Do quick and update your records', 0, '2026-02-08 19:20:59'),
(3, 25, 'Chapter 2 Review', 'Kindly take effect', 0, '2026-02-08 19:36:02');

-- --------------------------------------------------------

--
-- Table structure for table `student_profiles`
--

CREATE TABLE `student_profiles` (
  `student_id` varchar(50) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `programme` varchar(150) DEFAULT NULL,
  `supervisor_name` varchar(150) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `progress_pct` int(11) DEFAULT 0,
  `last_activity` varchar(50) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `research_topic` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_tracking_updates`
--

CREATE TABLE `student_tracking_updates` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `note` text NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'In Progress',
  `progress` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_tracking_updates`
--

INSERT INTO `student_tracking_updates` (`id`, `student_user_id`, `title`, `note`, `status`, `progress`, `updated_at`) VALUES
(1, 25, 'Submitted', 'Submitted to the supervisor', 'In Progress', 20, '2026-02-08 21:03:11');

-- --------------------------------------------------------

--
-- Table structure for table `study_modes`
--

CREATE TABLE `study_modes` (
  `mode_id` int(11) NOT NULL,
  `mode_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `study_modes`
--

INSERT INTO `study_modes` (`mode_id`, `mode_name`) VALUES
(1, 'Full Time'),
(2, 'Part Time');

-- --------------------------------------------------------

--
-- Table structure for table `supervisor_messages`
--

CREATE TABLE `supervisor_messages` (
  `message_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `supervisor_user_id` int(11) DEFAULT NULL,
  `student_user_id` int(11) DEFAULT NULL,
  `sender_role` enum('SUPERVISOR','STUDENT') DEFAULT 'STUDENT',
  `subject` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supervisor_messages`
--

INSERT INTO `supervisor_messages` (`message_id`, `student_id`, `message`, `created_at`, `supervisor_user_id`, `student_user_id`, `sender_role`, `subject`) VALUES
(1, 'PG-2026-0021', 'Kindly check your problem statement', '2026-02-08 19:34:05', 26, 25, 'SUPERVISOR', 'Update'),
(2, 'PG-2026-0021', 'Updated sir', '2026-02-08 19:39:50', 26, 25, 'STUDENT', 'Update'),
(3, 'PG-2026-0021', 'Any update???', '2026-02-11 22:09:25', 26, 25, 'STUDENT', 'Update'),
(4, 'PG-2026-0021', 'Check your mail', '2026-02-11 22:44:32', 26, 25, 'SUPERVISOR', 'Update');

-- --------------------------------------------------------

--
-- Table structure for table `supervisor_milestones`
--

CREATE TABLE `supervisor_milestones` (
  `milestone_id` int(11) NOT NULL,
  `student_name` varchar(150) NOT NULL,
  `title` varchar(200) NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(30) DEFAULT 'Upcoming',
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `supervisor_user_id` int(11) DEFAULT NULL,
  `student_user_id` int(11) DEFAULT NULL,
  `application_id` int(11) DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supervisor_milestones`
--

INSERT INTO `supervisor_milestones` (`milestone_id`, `student_name`, `title`, `due_date`, `status`, `note`, `created_at`, `updated_at`, `supervisor_user_id`, `student_user_id`, `application_id`, `acknowledged_at`) VALUES
(1, 'GADDAFI UMAR', 'Chapter 2 Review', '2026-02-09', 'Completed', 'Be reminded', '2026-02-08 21:50:14', '2026-02-08 21:57:07', 26, 25, NULL, '2026-02-08 22:52:35');

-- --------------------------------------------------------

--
-- Table structure for table `supervisor_notifications`
--

CREATE TABLE `supervisor_notifications` (
  `notification_id` int(11) NOT NULL,
  `supervisor_id` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supervisor_profiles`
--

CREATE TABLE `supervisor_profiles` (
  `supervisor_id` varchar(50) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `title` varchar(120) DEFAULT NULL,
  `specialization` varchar(150) DEFAULT NULL,
  `max_capacity` int(11) DEFAULT 8,
  `current_students` int(11) DEFAULT 0,
  `status` varchar(20) DEFAULT 'Active',
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `research_interests` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `last_active` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supervisor_profiles`
--

INSERT INTO `supervisor_profiles` (`supervisor_id`, `full_name`, `title`, `specialization`, `max_capacity`, `current_students`, `status`, `email`, `phone`, `research_interests`, `notes`, `last_active`, `created_at`, `updated_at`) VALUES
('SUP-001', 'Dr. Hadiza Abubakar', 'Senior Lecturer', 'Artificial Intelligence', 10, 8, 'Active', 'hadiza.abubakar@jostum.edu', '0803-111-2345', 'Machine learning, NLP', 'Focus on AI ethics.', '2 hours ago', '2026-01-24 11:06:25', '2026-01-24 11:06:25'),
('SUP-002', 'Prof. Chinedu Okafor', 'Professor', 'Computer Networks', 10, 6, 'Active', 'chinedu.okafor@jostum.edu', '0805-221-8974', 'Network security, IoT', 'Handles doctoral supervision.', '4 hours ago', '2026-01-24 11:06:25', '2026-01-24 11:06:25'),
('SUP-003', 'Dr. Uche Nwosu', 'Lecturer', 'Software Engineering', 10, 5, 'Active', 'uche.nwosu@jostum.edu', '0807-451-6702', 'Agile methods, QA', 'Available for new students.', '1 day ago', '2026-01-24 11:06:26', '2026-01-24 11:06:26');

-- --------------------------------------------------------

--
-- Table structure for table `supervisor_students`
--

CREATE TABLE `supervisor_students` (
  `student_id` varchar(50) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `programme` varchar(150) DEFAULT NULL,
  `current_chapter` varchar(50) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'Pending Review',
  `last_submission` date DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `progress_pct` int(11) DEFAULT 0,
  `supervisor_name` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `supervisor_user_id` int(11) DEFAULT NULL,
  `student_user_id` int(11) DEFAULT NULL,
  `application_id` int(11) DEFAULT NULL,
  `application_number` varchar(50) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supervisor_students`
--

INSERT INTO `supervisor_students` (`student_id`, `full_name`, `programme`, `current_chapter`, `status`, `last_submission`, `email`, `progress_pct`, `supervisor_name`, `notes`, `updated_at`, `supervisor_user_id`, `student_user_id`, `application_id`, `application_number`, `department_id`) VALUES
('PG-2026-0021', 'GADDAFI UMAR', 'Msc Business Management', 'Chapter 2', 'Awaiting Revision', '2026-02-08', 'gaddafiumar4445@gmail.com', 20, 'Proff Sani Ahmad', NULL, '2026-02-08 19:35:59', 26, 25, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `settings_id` int(11) NOT NULL,
  `institution_name` varchar(150) NOT NULL,
  `support_email` varchar(150) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `website_url` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `password_min_length` int(11) NOT NULL DEFAULT 8,
  `lockout_attempts` int(11) NOT NULL DEFAULT 5,
  `session_timeout` int(11) NOT NULL DEFAULT 60,
  `two_factor_policy` enum('REQUIRED','OPTIONAL','DISABLED') NOT NULL DEFAULT 'REQUIRED',
  `audit_level` enum('STANDARD','VERBOSE','CRITICAL') NOT NULL DEFAULT 'STANDARD',
  `smtp_host` varchar(150) DEFAULT NULL,
  `smtp_port` int(11) NOT NULL DEFAULT 587,
  `smtp_encryption` enum('TLS','SSL','NONE') NOT NULL DEFAULT 'TLS',
  `system_email` varchar(150) DEFAULT NULL,
  `reply_to_email` varchar(150) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`settings_id`, `institution_name`, `support_email`, `phone`, `website_url`, `address`, `password_min_length`, `lockout_attempts`, `session_timeout`, `two_factor_policy`, `audit_level`, `smtp_host`, `smtp_port`, `smtp_encryption`, `system_email`, `reply_to_email`, `updated_at`) VALUES
(1, 'JOSTUM PG School', 'admin@jostum.edu.ng', '+234 123 456 7890', 'https://www.uam.edu.ng', 'Makurdi, Benue State, Nigeria', 8, 5, 60, 'REQUIRED', 'STANDARD', 'smtp.gmail.com', 587, 'TLS', 'no-reply@jostum.edu.ng', 'support@jostum.edu.ng', '2026-01-18 15:10:48');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `account_status` enum('Active','Suspended','Locked') DEFAULT 'Active',
  `totp_secret` varchar(64) DEFAULT NULL,
  `totp_enabled` tinyint(1) DEFAULT 0,
  `totp_verified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `full_name`, `role_id`, `avatar_url`, `department_id`, `password_hash`, `last_login`, `created_at`, `reset_token`, `reset_expires`, `account_status`, `totp_secret`, `totp_enabled`, `totp_verified_at`) VALUES
(1, 'test@mail.com', NULL, 2, NULL, NULL, '$2y$10$QNaZwvkaOH8bseotP4Kj8.ITaiVbwddC0kVeWIXpF9XxXgxQYUlTm', '2026-01-15 18:21:16', '2026-01-15 17:25:42', NULL, NULL, 'Locked', NULL, 0, NULL),
(4, 'muhdmukhtar2019@gmail.com', 'Muhammad Adamu Garba', 1, 'http://localhost/JOSTUM/uploads/avatars/admin_4_1770843355.jpeg', 1, '$2y$10$ytEx3buqUghhhKz14HFtquiGXoI1uDFSsDbnEIPh1Up0a7o.DxQcm', '2026-01-25 17:11:40', '2026-01-18 15:12:50', NULL, NULL, 'Active', '7ESOD7SWWBHMEUY37GSW', 1, '2026-07-02 18:38:16'),
(25, 'gaddafiumar4445@gmail.com', NULL, NULL, NULL, NULL, '$2y$10$XkjKD.GJqxaw68BjCul16OFr8k2kl.oqTmVeWoxKY6aqHiFpey.kO', '2026-07-02 17:51:38', '2026-02-07 10:34:54', NULL, NULL, 'Active', NULL, 0, NULL),
(26, 'rukayyamohammedbuba428@gmail.com', 'Proff Sani Ahmad', 4, 'http://localhost/JOSTUM/uploads/avatars/admin_26_1770849165.jpeg', 3, '$2y$10$4xZ/iLEJH0MH6i6J4JKEku.2sVphZvgYmiFbReEya/pHJpfZ/EAMO', NULL, '2026-02-07 15:01:51', '10760752f31a1404f6ac4f45d348decc', '2026-02-08 16:01:51', 'Active', 'QGNJDXS2RDLNYLP2GNWB', 1, '2026-02-11 23:37:03'),
(27, 'philomolaye@gmail.com', 'Philip Omolaye', NULL, NULL, NULL, '$2y$10$5oXI63XxahkOFA9x12n.2ey5n6hwB5ka3NH0MxLq4YgtmYElEdgDi', '2026-07-02 19:14:25', '2026-07-02 18:14:51', NULL, NULL, 'Active', NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_applicant_accounts`
-- (See below for the actual view)
--
CREATE TABLE `v_applicant_accounts` (
`user_id` int(11)
,`email` varchar(255)
,`password_hash` varchar(255)
,`last_login` timestamp
,`created_at` timestamp
,`reset_token` varchar(64)
,`reset_expires` datetime
,`account_status` varchar(9)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_document_verification_like`
-- (See below for the actual view)
--
CREATE TABLE `v_document_verification_like` (
`upload_id` int(11)
,`application_id` int(11)
,`document_type` varchar(50)
,`file_path` varchar(255)
,`uploaded_at` timestamp
,`verification_status` enum('Pending','Verified','Rejected')
,`admin_remark` text
);

-- --------------------------------------------------------

--
-- Table structure for table `work_experience`
--

CREATE TABLE `work_experience` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `employment_status` varchar(50) DEFAULT NULL,
  `employer` varchar(150) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `years_experience` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `work_experience`
--

INSERT INTO `work_experience` (`id`, `application_id`, `employment_status`, `employer`, `job_title`, `years_experience`) VALUES
(1, 19, 'Employed', 'Access Bank', 'programmer/Analyst', 4),
(5, 21, 'Student', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure for view `v_applicant_accounts`
--
DROP TABLE IF EXISTS `v_applicant_accounts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_applicant_accounts`  AS SELECT `users`.`user_id` AS `user_id`, `users`.`email` AS `email`, `users`.`password_hash` AS `password_hash`, `users`.`last_login` AS `last_login`, `users`.`created_at` AS `created_at`, `users`.`reset_token` AS `reset_token`, `users`.`reset_expires` AS `reset_expires`, coalesce(`users`.`account_status`,'Active') AS `account_status` FROM `users` ;

-- --------------------------------------------------------

--
-- Structure for view `v_document_verification_like`
--
DROP TABLE IF EXISTS `v_document_verification_like`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_document_verification_like`  AS SELECT `d`.`doc_id` AS `upload_id`, `d`.`application_id` AS `application_id`, `d`.`document_type` AS `document_type`, `d`.`file_path` AS `file_path`, `d`.`uploaded_at` AS `uploaded_at`, `d`.`status` AS `verification_status`, `d`.`comments` AS `admin_remark` FROM `documents` AS `d` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_admin_notifications_read` (`is_read`),
  ADD KEY `idx_admin_notifications_created` (`created_at`),
  ADD KEY `admin_notifications_ibfk_1` (`related_user_id`),
  ADD KEY `admin_notifications_ibfk_2` (`actor_user_id`);

--
-- Indexes for table `admin_recovery_codes`
--
ALTER TABLE `admin_recovery_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_admin_recovery` (`admin_id`);

--
-- Indexes for table `admin_reports`
--
ALTER TABLE `admin_reports`
  ADD PRIMARY KEY (`report_id`);

--
-- Indexes for table `applicants`
--
ALTER TABLE `applicants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `applicant_accounts`
--
ALTER TABLE `applicant_accounts`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `applicant_notifications`
--
ALTER TABLE `applicant_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `fk_applicant_notifications_application` (`application_id`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`application_id`),
  ADD UNIQUE KEY `application_number` (`application_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `application_progress`
--
ALTER TABLE `application_progress`
  ADD PRIMARY KEY (`progress_id`);

--
-- Indexes for table `application_status`
--
ALTER TABLE `application_status`
  ADD PRIMARY KEY (`status_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_actor_user` (`actor_user_id`);

--
-- Indexes for table `chapter_submissions`
--
ALTER TABLE `chapter_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_chapter_student` (`student_user_id`),
  ADD KEY `idx_chapter_number` (`chapter_no`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD KEY `dept_id` (`dept_id`),
  ADD KEY `degree_id` (`degree_id`);

--
-- Indexes for table `degree_types`
--
ALTER TABLE `degree_types`
  ADD PRIMARY KEY (`degree_id`),
  ADD UNIQUE KEY `degree_name` (`degree_name`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`dept_id`),
  ADD UNIQUE KEY `dept_name` (`dept_name`,`faculty_id`),
  ADD KEY `faculty_id` (`faculty_id`);

--
-- Indexes for table `dept_applications`
--
ALTER TABLE `dept_applications`
  ADD PRIMARY KEY (`app_code`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`doc_id`),
  ADD UNIQUE KEY `unique_app_doc` (`application_id`,`document_type`),
  ADD KEY `idx_documents_app_doc` (`application_id`,`document_type`);

--
-- Indexes for table `document_verification`
--
ALTER TABLE `document_verification`
  ADD PRIMARY KEY (`verification_id`);

--
-- Indexes for table `faculties`
--
ALTER TABLE `faculties`
  ADD PRIMARY KEY (`faculty_id`),
  ADD UNIQUE KEY `faculty_name` (`faculty_name`);

--
-- Indexes for table `higher_education`
--
ALTER TABLE `higher_education`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_id` (`application_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `nysc_details`
--
ALTER TABLE `nysc_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_id` (`application_id`);

--
-- Indexes for table `olevel_exams`
--
ALTER TABLE `olevel_exams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sitting` (`application_id`,`sitting_number`);

--
-- Indexes for table `olevel_results`
--
ALTER TABLE `olevel_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_subject` (`subject_name`),
  ADD KEY `fk_exam_results` (`exam_id`);

--
-- Indexes for table `olevel_sittings`
--
ALTER TABLE `olevel_sittings`
  ADD PRIMARY KEY (`sitting_id`),
  ADD UNIQUE KEY `application_id` (`application_id`,`sitting_number`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reset_email` (`email`),
  ADD KEY `idx_reset_token` (`token_hash`);

--
-- Indexes for table `personal_details`
--
ALTER TABLE `personal_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_id` (`application_id`);

--
-- Indexes for table `programme_capacities`
--
ALTER TABLE `programme_capacities`
  ADD PRIMARY KEY (`capacity_id`),
  ADD UNIQUE KEY `unique_course_capacity` (`course_id`);

--
-- Indexes for table `programme_choices`
--
ALTER TABLE `programme_choices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_id` (`application_id`),
  ADD KEY `fk_faculty` (`faculty`),
  ADD KEY `fk_dept` (`department`),
  ADD KEY `fk_degree` (`degree_type`),
  ADD KEY `fk_course` (`course`),
  ADD KEY `fk_mode` (`mode_of_study`);

--
-- Indexes for table `referees`
--
ALTER TABLE `referees`
  ADD PRIMARY KEY (`referee_id`),
  ADD KEY `application_id` (`application_id`);

--
-- Indexes for table `referee_requests`
--
ALTER TABLE `referee_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD UNIQUE KEY `uniq_ref_token` (`token`),
  ADD KEY `idx_referee_id` (`referee_id`),
  ADD KEY `idx_app_id` (`application_id`);

--
-- Indexes for table `referee_status`
--
ALTER TABLE `referee_status`
  ADD PRIMARY KEY (`referee_id`);

--
-- Indexes for table `referee_uploads`
--
ALTER TABLE `referee_uploads`
  ADD PRIMARY KEY (`upload_id`),
  ADD KEY `idx_ref_upload_ref` (`referee_id`),
  ADD KEY `idx_ref_upload_app` (`application_id`);

--
-- Indexes for table `research_details`
--
ALTER TABLE `research_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_id` (`application_id`);

--
-- Indexes for table `reviewer_assignments`
--
ALTER TABLE `reviewer_assignments`
  ADD PRIMARY KEY (`assignment_id`);

--
-- Indexes for table `reviewer_feedback`
--
ALTER TABLE `reviewer_feedback`
  ADD PRIMARY KEY (`feedback_id`);

--
-- Indexes for table `reviewer_history`
--
ALTER TABLE `reviewer_history`
  ADD PRIMARY KEY (`history_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_key` (`role_key`);

--
-- Indexes for table `student_messages`
--
ALTER TABLE `student_messages`
  ADD PRIMARY KEY (`message_id`);

--
-- Indexes for table `student_notifications`
--
ALTER TABLE `student_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_notify` (`student_user_id`);

--
-- Indexes for table `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD PRIMARY KEY (`student_id`);

--
-- Indexes for table `student_tracking_updates`
--
ALTER TABLE `student_tracking_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_tracking` (`student_user_id`);

--
-- Indexes for table `study_modes`
--
ALTER TABLE `study_modes`
  ADD PRIMARY KEY (`mode_id`);

--
-- Indexes for table `supervisor_messages`
--
ALTER TABLE `supervisor_messages`
  ADD PRIMARY KEY (`message_id`);

--
-- Indexes for table `supervisor_milestones`
--
ALTER TABLE `supervisor_milestones`
  ADD PRIMARY KEY (`milestone_id`);

--
-- Indexes for table `supervisor_notifications`
--
ALTER TABLE `supervisor_notifications`
  ADD PRIMARY KEY (`notification_id`);

--
-- Indexes for table `supervisor_profiles`
--
ALTER TABLE `supervisor_profiles`
  ADD PRIMARY KEY (`supervisor_id`);

--
-- Indexes for table `supervisor_students`
--
ALTER TABLE `supervisor_students`
  ADD PRIMARY KEY (`student_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`settings_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_role_id` (`role_id`),
  ADD KEY `idx_department_id` (`department_id`);

--
-- Indexes for table `work_experience`
--
ALTER TABLE `work_experience`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_id` (`application_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `notification_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_recovery_codes`
--
ALTER TABLE `admin_recovery_codes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_reports`
--
ALTER TABLE `admin_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `applicants`
--
ALTER TABLE `applicants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `applicant_accounts`
--
ALTER TABLE `applicant_accounts`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `applicant_notifications`
--
ALTER TABLE `applicant_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `application_progress`
--
ALTER TABLE `application_progress`
  MODIFY `progress_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `application_status`
--
ALTER TABLE `application_status`
  MODIFY `status_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chapter_submissions`
--
ALTER TABLE `chapter_submissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `degree_types`
--
ALTER TABLE `degree_types`
  MODIFY `degree_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `dept_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `doc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=206;

--
-- AUTO_INCREMENT for table `document_verification`
--
ALTER TABLE `document_verification`
  MODIFY `verification_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `faculties`
--
ALTER TABLE `faculties`
  MODIFY `faculty_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `higher_education`
--
ALTER TABLE `higher_education`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `nysc_details`
--
ALTER TABLE `nysc_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `olevel_exams`
--
ALTER TABLE `olevel_exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `olevel_results`
--
ALTER TABLE `olevel_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `olevel_sittings`
--
ALTER TABLE `olevel_sittings`
  MODIFY `sitting_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `personal_details`
--
ALTER TABLE `personal_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `programme_capacities`
--
ALTER TABLE `programme_capacities`
  MODIFY `capacity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `programme_choices`
--
ALTER TABLE `programme_choices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `referees`
--
ALTER TABLE `referees`
  MODIFY `referee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `referee_requests`
--
ALTER TABLE `referee_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `referee_uploads`
--
ALTER TABLE `referee_uploads`
  MODIFY `upload_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `research_details`
--
ALTER TABLE `research_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `reviewer_assignments`
--
ALTER TABLE `reviewer_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviewer_feedback`
--
ALTER TABLE `reviewer_feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviewer_history`
--
ALTER TABLE `reviewer_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `student_messages`
--
ALTER TABLE `student_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_notifications`
--
ALTER TABLE `student_notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student_tracking_updates`
--
ALTER TABLE `student_tracking_updates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `study_modes`
--
ALTER TABLE `study_modes`
  MODIFY `mode_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `supervisor_messages`
--
ALTER TABLE `supervisor_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `supervisor_milestones`
--
ALTER TABLE `supervisor_milestones`
  MODIFY `milestone_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `supervisor_notifications`
--
ALTER TABLE `supervisor_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `settings_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `work_experience`
--
ALTER TABLE `work_experience`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD CONSTRAINT `admin_notifications_ibfk_1` FOREIGN KEY (`related_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `admin_notifications_ibfk_2` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `applicant_notifications`
--
ALTER TABLE `applicant_notifications`
  ADD CONSTRAINT `fk_applicant_notifications_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE;

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `courses_ibfk_2` FOREIGN KEY (`degree_id`) REFERENCES `degree_types` (`degree_id`) ON DELETE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`faculty_id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE;

--
-- Constraints for table `higher_education`
--
ALTER TABLE `higher_education`
  ADD CONSTRAINT `higher_education_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE;

--
-- Constraints for table `nysc_details`
--
ALTER TABLE `nysc_details`
  ADD CONSTRAINT `nysc_details_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE;

--
-- Constraints for table `olevel_exams`
--
ALTER TABLE `olevel_exams`
  ADD CONSTRAINT `fk_application_olevel` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE;

--
-- Constraints for table `olevel_results`
--
ALTER TABLE `olevel_results`
  ADD CONSTRAINT `fk_exam_results` FOREIGN KEY (`exam_id`) REFERENCES `olevel_exams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `olevel_sittings`
--
ALTER TABLE `olevel_sittings`
  ADD CONSTRAINT `olevel_sittings_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE;

--
-- Constraints for table `personal_details`
--
ALTER TABLE `personal_details`
  ADD CONSTRAINT `personal_details_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE;

--
-- Constraints for table `programme_capacities`
--
ALTER TABLE `programme_capacities`
  ADD CONSTRAINT `programme_capacities_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE;

--
-- Constraints for table `programme_choices`
--
ALTER TABLE `programme_choices`
  ADD CONSTRAINT `fk_course` FOREIGN KEY (`course`) REFERENCES `courses` (`course_id`),
  ADD CONSTRAINT `fk_degree` FOREIGN KEY (`degree_type`) REFERENCES `degree_types` (`degree_id`),
  ADD CONSTRAINT `fk_dept` FOREIGN KEY (`department`) REFERENCES `departments` (`dept_id`),
  ADD CONSTRAINT `fk_faculty` FOREIGN KEY (`faculty`) REFERENCES `faculties` (`faculty_id`),
  ADD CONSTRAINT `fk_mode` FOREIGN KEY (`mode_of_study`) REFERENCES `study_modes` (`mode_id`),
  ADD CONSTRAINT `programme_choices_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE;

--
-- Constraints for table `referees`
--
ALTER TABLE `referees`
  ADD CONSTRAINT `referees_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE;

--
-- Constraints for table `referee_requests`
--
ALTER TABLE `referee_requests`
  ADD CONSTRAINT `fk_ref_req_app` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ref_req_referee` FOREIGN KEY (`referee_id`) REFERENCES `referees` (`referee_id`) ON DELETE CASCADE;

--
-- Constraints for table `referee_uploads`
--
ALTER TABLE `referee_uploads`
  ADD CONSTRAINT `fk_ref_upload_app` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ref_upload_referee` FOREIGN KEY (`referee_id`) REFERENCES `referees` (`referee_id`) ON DELETE CASCADE;

--
-- Constraints for table `research_details`
--
ALTER TABLE `research_details`
  ADD CONSTRAINT `research_details_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE SET NULL;

--
-- Constraints for table `work_experience`
--
ALTER TABLE `work_experience`
  ADD CONSTRAINT `work_experience_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
