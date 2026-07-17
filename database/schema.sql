-- JOSTUM Consolidated Database Export
-- Generated manually via PHP PDO on 2026-07-17 12:13:43

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------
-- Table structure for table `admin_notifications`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `admin_notifications`;
CREATE TABLE `admin_notifications` (
  `notification_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `category` enum('SYSTEM','USER','APPLICATION','SECURITY') NOT NULL DEFAULT 'SYSTEM',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `related_user_id` int(11) DEFAULT NULL,
  `actor_user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`notification_id`),
  KEY `idx_admin_notifications_read` (`is_read`),
  KEY `idx_admin_notifications_created` (`created_at`),
  KEY `admin_notifications_ibfk_1` (`related_user_id`),
  KEY `admin_notifications_ibfk_2` (`actor_user_id`),
  CONSTRAINT `admin_notifications_ibfk_1` FOREIGN KEY (`related_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `admin_notifications_ibfk_2` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `admin_recovery_codes`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `admin_recovery_codes`;
CREATE TABLE `admin_recovery_codes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned NOT NULL,
  `code` varchar(255) NOT NULL,
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_admin_recovery` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `admin_reports`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `admin_reports`;
CREATE TABLE `admin_reports` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `report_name` varchar(255) NOT NULL,
  `report_type` varchar(100) NOT NULL,
  `format` varchar(20) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`report_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `admins`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `google2fa_enabled` tinyint(1) DEFAULT 0,
  `google2fa_secret` text DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `admission_processing`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `admission_processing`;
CREATE TABLE `admission_processing` (
  `application_id` int(11) NOT NULL,
  `matric_number` varchar(50) DEFAULT NULL,
  `student_number` varchar(50) DEFAULT NULL,
  `acceptance_letter_status` enum('Inactive','Active') NOT NULL DEFAULT 'Inactive',
  `admission_letter_status` enum('Inactive','Active') NOT NULL DEFAULT 'Inactive',
  `acceptance_letter_activated_at` datetime DEFAULT NULL,
  `admission_letter_activated_at` datetime DEFAULT NULL,
  `matric_generated_at` datetime DEFAULT NULL,
  `student_num_generated_at` datetime DEFAULT NULL,
  `matric_generated_by` int(11) DEFAULT NULL,
  `student_num_generated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`application_id`),
  CONSTRAINT `admission_processing_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `admissions_faqs`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `admissions_faqs`;
CREATE TABLE `admissions_faqs` (
  `faq_id` int(11) NOT NULL AUTO_INCREMENT,
  `question` varchar(255) NOT NULL,
  `answer` text NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`faq_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `admissions_important_dates`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `admissions_important_dates`;
CREATE TABLE `admissions_important_dates` (
  `date_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `event_date` date NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`date_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `admissions_notices`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `admissions_notices`;
CREATE TABLE `admissions_notices` (
  `notice_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `button_label` varchar(80) DEFAULT NULL,
  `button_url` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`notice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `admissions_programmes`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `admissions_programmes`;
CREATE TABLE `admissions_programmes` (
  `programme_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`programme_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `admissions_requirements`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `admissions_requirements`;
CREATE TABLE `admissions_requirements` (
  `requirement_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`requirement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `applicant_accounts`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `applicant_accounts`;
CREATE TABLE `applicant_accounts` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `account_status` enum('Active','Suspended','Locked') DEFAULT 'Active',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `applicant_notifications`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `applicant_notifications`;
CREATE TABLE `applicant_notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `notification_title` varchar(200) NOT NULL,
  `notification_message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `fk_applicant_notifications_application` (`application_id`),
  CONSTRAINT `fk_applicant_notifications_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `applicant_notifications`
INSERT INTO `applicant_notifications` (`notification_id`, `application_id`, `notification_title`, `notification_message`, `is_read`, `created_at`) VALUES ('29', '24', 'Referee Submitted', 'Your referee has submitted their evaluation report.', '0', '2026-07-17 10:40:00');

-- ------------------------------------------------------
-- Table structure for table `applicants`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `applicants`;
CREATE TABLE `applicants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `application_documents`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `application_documents`;
CREATE TABLE `application_documents` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `doc_type` varchar(80) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `verified_status` varchar(20) DEFAULT 'Pending',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`document_id`),
  KEY `idx_app_docs` (`application_id`,`doc_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `application_progress`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `application_progress`;
CREATE TABLE `application_progress` (
  `progress_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` bigint(20) unsigned NOT NULL,
  `stage` varchar(100) NOT NULL,
  `stage_status` enum('Pending','In Progress','Completed') NOT NULL DEFAULT 'Pending',
  `stage_updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`progress_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `application_progress`
INSERT INTO `application_progress` (`progress_id`, `application_id`, `stage`, `stage_status`, `stage_updated_at`) VALUES ('3', '24', 'Application Submitted', 'Completed', '2026-07-17 10:40:59');
INSERT INTO `application_progress` (`progress_id`, `application_id`, `stage`, `stage_status`, `stage_updated_at`) VALUES ('4', '24', '', 'Pending', '2026-07-17 10:40:59');
INSERT INTO `application_progress` (`progress_id`, `application_id`, `stage`, `stage_status`, `stage_updated_at`) VALUES ('5', '24', '', 'Pending', '2026-07-17 10:40:59');
INSERT INTO `application_progress` (`progress_id`, `application_id`, `stage`, `stage_status`, `stage_updated_at`) VALUES ('6', '24', '', 'Pending', '2026-07-17 10:40:59');
INSERT INTO `application_progress` (`progress_id`, `application_id`, `stage`, `stage_status`, `stage_updated_at`) VALUES ('7', '24', '', 'Pending', '2026-07-17 10:40:59');
INSERT INTO `application_progress` (`progress_id`, `application_id`, `stage`, `stage_status`, `stage_updated_at`) VALUES ('8', '24', '', 'Pending', '2026-07-17 10:40:59');
INSERT INTO `application_progress` (`progress_id`, `application_id`, `stage`, `stage_status`, `stage_updated_at`) VALUES ('9', '24', '', 'Pending', '2026-07-17 10:40:59');
INSERT INTO `application_progress` (`progress_id`, `application_id`, `stage`, `stage_status`, `stage_updated_at`) VALUES ('10', '24', '', 'Pending', '2026-07-17 10:40:59');
INSERT INTO `application_progress` (`progress_id`, `application_id`, `stage`, `stage_status`, `stage_updated_at`) VALUES ('11', '24', '', 'Completed', '2026-07-17 10:42:35');

-- ------------------------------------------------------
-- Table structure for table `application_status`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `application_status`;
CREATE TABLE `application_status` (
  `status_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` bigint(20) unsigned NOT NULL,
  `public_status` enum('Submitted','Under Review','Shortlisted','Decision Made','Admitted','Not Admitted','Deferred') NOT NULL DEFAULT 'Submitted',
  `internal_status` enum('Draft','Submitted','Document Verification','Academic Review','Referee Review','Committee Review','Final Decision') NOT NULL DEFAULT 'Submitted',
  `status_updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `application_status_history`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `application_status_history`;
CREATE TABLE `application_status_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `from_status` varchar(60) DEFAULT NULL,
  `to_status` varchar(60) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `actor_role` varchar(50) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`),
  KEY `idx_app_status` (`application_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `application_status_history`
INSERT INTO `application_status_history` (`history_id`, `application_id`, `from_status`, `to_status`, `actor_id`, `actor_role`, `note`, `created_at`) VALUES ('1', '24', 'SUBMITTED', 'SUBMITTED', '31', 'STUDENT', 'Application submitted', '2026-07-17 10:29:32');
INSERT INTO `application_status_history` (`history_id`, `application_id`, `from_status`, `to_status`, `actor_id`, `actor_role`, `note`, `created_at`) VALUES ('2', '24', 'SUBMITTED', 'ASSIGNED_TO_DEPARTMENT', '31', 'SYSTEM', 'Auto-assigned to department', '2026-07-17 10:29:32');

-- ------------------------------------------------------
-- Table structure for table `applications`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `applications`;
CREATE TABLE `applications` (
  `application_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `application_number` varchar(50) DEFAULT NULL,
  `status` enum('Draft','Submitted','Admitted','Rejected') DEFAULT 'Draft',
  `current_step` int(11) DEFAULT 1,
  `submitted_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `department_id` int(11) DEFAULT NULL,
  `reviewer_id` int(11) DEFAULT NULL,
  `completion_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `current_status` varchar(60) DEFAULT 'DRAFT',
  PRIMARY KEY (`application_id`),
  UNIQUE KEY `application_number` (`application_number`),
  KEY `user_id` (`user_id`),
  KEY `idx_app_status` (`current_status`),
  KEY `idx_app_submitted` (`submitted_at`),
  KEY `idx_app_department` (`department_id`),
  CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `applications`
INSERT INTO `applications` (`application_id`, `user_id`, `application_number`, `status`, `current_step`, `submitted_at`, `updated_at`, `department_id`, `reviewer_id`, `completion_percentage`, `current_status`) VALUES ('22', '29', 'APP-2026-472C97', 'Draft', '3', NULL, '2026-07-16 21:41:44', NULL, NULL, '30.00', 'DRAFT');
INSERT INTO `applications` (`application_id`, `user_id`, `application_number`, `status`, `current_step`, `submitted_at`, `updated_at`, `department_id`, `reviewer_id`, `completion_percentage`, `current_status`) VALUES ('23', '30', 'APP-2026-B09071', 'Draft', '9', NULL, '2026-07-17 10:34:30', '6', NULL, '96.00', 'DRAFT');
INSERT INTO `applications` (`application_id`, `user_id`, `application_number`, `status`, `current_step`, `submitted_at`, `updated_at`, `department_id`, `reviewer_id`, `completion_percentage`, `current_status`) VALUES ('24', '31', 'APP/IPESS/2026/0001', 'Submitted', '10', '2026-07-17 10:29:32', '2026-07-17 10:42:35', '7', NULL, '96.00', 'ASSIGNED_TO_DEPARTMENT');

-- ------------------------------------------------------
-- Table structure for table `audit_logs`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `log_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `entity` varchar(120) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `severity` enum('INFO','WARNING','CRITICAL') NOT NULL DEFAULT 'INFO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_actor_user` (`actor_user_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `audit_logs`
INSERT INTO `audit_logs` (`log_id`, `actor_user_id`, `action`, `entity`, `details`, `ip_address`, `user_agent`, `severity`, `created_at`) VALUES ('1', '31', 'Application Status Update', NULL, 'Application 24: SUBMITTED -> SUBMITTED', NULL, NULL, 'INFO', '2026-07-17 10:29:32');
INSERT INTO `audit_logs` (`log_id`, `actor_user_id`, `action`, `entity`, `details`, `ip_address`, `user_agent`, `severity`, `created_at`) VALUES ('2', '31', 'Application Status Update', NULL, 'Application 24: SUBMITTED -> ASSIGNED_TO_DEPARTMENT', NULL, NULL, 'INFO', '2026-07-17 10:29:32');

-- ------------------------------------------------------
-- Table structure for table `chapter_submissions`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `chapter_submissions`;
CREATE TABLE `chapter_submissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chapter_student` (`student_user_id`),
  KEY `idx_chapter_number` (`chapter_no`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `courses`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `courses`;
CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL AUTO_INCREMENT,
  `course_title` varchar(255) NOT NULL,
  `dept_id` int(11) NOT NULL,
  `degree_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`course_id`),
  KEY `dept_id` (`dept_id`),
  KEY `degree_id` (`degree_id`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`) ON DELETE CASCADE,
  CONSTRAINT `courses_ibfk_2` FOREIGN KEY (`degree_id`) REFERENCES `degree_types` (`degree_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `courses`
INSERT INTO `courses` (`course_id`, `course_title`, `dept_id`, `degree_id`, `created_at`) VALUES ('8', 'Social Standard', '8', '6', '2026-07-16 21:28:24');
INSERT INTO `courses` (`course_id`, `course_title`, `dept_id`, `degree_id`, `created_at`) VALUES ('9', 'Social Standard', '8', '7', '2026-07-16 21:28:40');
INSERT INTO `courses` (`course_id`, `course_title`, `dept_id`, `degree_id`, `created_at`) VALUES ('10', 'Social Standard', '8', '8', '2026-07-16 21:28:49');
INSERT INTO `courses` (`course_id`, `course_title`, `dept_id`, `degree_id`, `created_at`) VALUES ('11', 'Procurement', '7', '8', '2026-07-16 21:29:18');
INSERT INTO `courses` (`course_id`, `course_title`, `dept_id`, `degree_id`, `created_at`) VALUES ('12', 'Procurement', '7', '6', '2026-07-16 21:29:50');
INSERT INTO `courses` (`course_id`, `course_title`, `dept_id`, `degree_id`, `created_at`) VALUES ('13', 'Procurement', '7', '7', '2026-07-16 21:30:17');
INSERT INTO `courses` (`course_id`, `course_title`, `dept_id`, `degree_id`, `created_at`) VALUES ('14', 'Environmental Sustainability', '6', '7', '2026-07-16 21:30:54');
INSERT INTO `courses` (`course_id`, `course_title`, `dept_id`, `degree_id`, `created_at`) VALUES ('15', 'Environmental Sustainability', '6', '6', '2026-07-16 21:31:02');
INSERT INTO `courses` (`course_id`, `course_title`, `dept_id`, `degree_id`, `created_at`) VALUES ('16', 'Environmental Sustainability', '6', '8', '2026-07-16 21:31:10');

-- ------------------------------------------------------
-- Table structure for table `degree_types`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `degree_types`;
CREATE TABLE `degree_types` (
  `degree_id` int(11) NOT NULL AUTO_INCREMENT,
  `degree_name` varchar(50) NOT NULL,
  PRIMARY KEY (`degree_id`),
  UNIQUE KEY `degree_name` (`degree_name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `degree_types`
INSERT INTO `degree_types` (`degree_id`, `degree_name`) VALUES ('6', 'MSc');
INSERT INTO `degree_types` (`degree_id`, `degree_name`) VALUES ('7', 'PGD');
INSERT INTO `degree_types` (`degree_id`, `degree_name`) VALUES ('8', 'PhD');

-- ------------------------------------------------------
-- Table structure for table `departments`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `dept_id` int(11) NOT NULL AUTO_INCREMENT,
  `dept_name` varchar(100) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  PRIMARY KEY (`dept_id`),
  UNIQUE KEY `dept_name` (`dept_name`,`faculty_id`),
  KEY `faculty_id` (`faculty_id`),
  CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`faculty_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `departments`
INSERT INTO `departments` (`dept_id`, `dept_name`, `faculty_id`) VALUES ('6', 'Environmental Standard', '7');
INSERT INTO `departments` (`dept_id`, `dept_name`, `faculty_id`) VALUES ('7', 'Procurement', '6');
INSERT INTO `departments` (`dept_id`, `dept_name`, `faculty_id`) VALUES ('8', 'Social Standard', '9');

-- ------------------------------------------------------
-- Table structure for table `dept_applications`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `dept_applications`;
CREATE TABLE `dept_applications` (
  `app_code` varchar(50) NOT NULL,
  `applicant_name` varchar(150) NOT NULL,
  `programme` varchar(150) NOT NULL,
  `status` varchar(50) NOT NULL,
  `reviewer_name` varchar(150) DEFAULT NULL,
  `submitted_date` date DEFAULT NULL,
  `priority` varchar(20) DEFAULT 'Normal',
  `department` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`app_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `document_verification`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `document_verification`;
CREATE TABLE `document_verification` (
  `verification_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `upload_id` bigint(20) unsigned NOT NULL,
  `verification_status` enum('Pending','Verified','Re-upload Required') NOT NULL DEFAULT 'Pending',
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `admin_remark` text DEFAULT NULL,
  PRIMARY KEY (`verification_id`),
  UNIQUE KEY `unique_upload_id` (`upload_id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `document_verification`
INSERT INTO `document_verification` (`verification_id`, `upload_id`, `verification_status`, `verified_by`, `verified_at`, `admin_remark`) VALUES ('17', '206', 'Verified', '27', '2026-07-17 10:41:58', '');
INSERT INTO `document_verification` (`verification_id`, `upload_id`, `verification_status`, `verified_by`, `verified_at`, `admin_remark`) VALUES ('18', '207', 'Verified', '27', '2026-07-17 10:42:06', '');
INSERT INTO `document_verification` (`verification_id`, `upload_id`, `verification_status`, `verified_by`, `verified_at`, `admin_remark`) VALUES ('19', '208', 'Verified', '27', '2026-07-17 10:42:15', '');
INSERT INTO `document_verification` (`verification_id`, `upload_id`, `verification_status`, `verified_by`, `verified_at`, `admin_remark`) VALUES ('20', '209', 'Verified', '27', '2026-07-17 10:42:26', '');
INSERT INTO `document_verification` (`verification_id`, `upload_id`, `verification_status`, `verified_by`, `verified_at`, `admin_remark`) VALUES ('21', '210', 'Verified', '27', '2026-07-17 10:42:35', '');

-- ------------------------------------------------------
-- Table structure for table `documents`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
  `doc_id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `document_type` varchar(50) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
  `comments` text DEFAULT NULL,
  PRIMARY KEY (`doc_id`),
  UNIQUE KEY `unique_app_doc` (`application_id`,`document_type`),
  KEY `idx_documents_app_doc` (`application_id`,`document_type`),
  CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=218 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `documents`
INSERT INTO `documents` (`doc_id`, `application_id`, `document_type`, `file_path`, `uploaded_at`, `status`, `comments`) VALUES ('206', '24', 'passport', 'uploads/passports/1784280537_2d15ed99d7b568d0.jpg', '2026-07-17 10:28:57', 'Pending', NULL);
INSERT INTO `documents` (`doc_id`, `application_id`, `document_type`, `file_path`, `uploaded_at`, `status`, `comments`) VALUES ('207', '24', 'olevel_1', 'uploads/olevel/1784280537_ebce80e115b0af16.pdf', '2026-07-17 10:28:57', 'Pending', NULL);
INSERT INTO `documents` (`doc_id`, `application_id`, `document_type`, `file_path`, `uploaded_at`, `status`, `comments`) VALUES ('208', '24', 'degree', 'uploads/degree/1784280537_5752e7285dfd44e1.pdf', '2026-07-17 10:28:57', 'Pending', NULL);
INSERT INTO `documents` (`doc_id`, `application_id`, `document_type`, `file_path`, `uploaded_at`, `status`, `comments`) VALUES ('209', '24', 'transcript', 'uploads/transcripts/1784280537_8a46cef773c1cb79.pdf', '2026-07-17 10:28:57', 'Pending', NULL);
INSERT INTO `documents` (`doc_id`, `application_id`, `document_type`, `file_path`, `uploaded_at`, `status`, `comments`) VALUES ('210', '24', 'nysc', 'uploads/nysc/1784280537_915522b30c830094.pdf', '2026-07-17 10:28:57', 'Pending', NULL);
INSERT INTO `documents` (`doc_id`, `application_id`, `document_type`, `file_path`, `uploaded_at`, `status`, `comments`) VALUES ('213', '23', 'passport', 'uploads/passports/1784280870_1699414ecf54aaa5.jpg', '2026-07-17 10:34:30', 'Pending', NULL);
INSERT INTO `documents` (`doc_id`, `application_id`, `document_type`, `file_path`, `uploaded_at`, `status`, `comments`) VALUES ('214', '23', 'olevel_1', 'uploads/olevel/1784280870_1b4275087e4767dc.jpg', '2026-07-17 10:34:30', 'Pending', NULL);
INSERT INTO `documents` (`doc_id`, `application_id`, `document_type`, `file_path`, `uploaded_at`, `status`, `comments`) VALUES ('215', '23', 'degree', 'uploads/degree/1784280870_6313627745ea89b3.jpg', '2026-07-17 10:34:30', 'Pending', NULL);
INSERT INTO `documents` (`doc_id`, `application_id`, `document_type`, `file_path`, `uploaded_at`, `status`, `comments`) VALUES ('216', '23', 'transcript', 'uploads/transcripts/1784280870_eefffdfbe51c4f7a.jpg', '2026-07-17 10:34:30', 'Pending', NULL);
INSERT INTO `documents` (`doc_id`, `application_id`, `document_type`, `file_path`, `uploaded_at`, `status`, `comments`) VALUES ('217', '23', 'nysc', 'uploads/nysc/1784280870_1da8d28910a62f59.jpg', '2026-07-17 10:34:30', 'Pending', NULL);

-- ------------------------------------------------------
-- Table structure for table `faculties`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `faculties`;
CREATE TABLE `faculties` (
  `faculty_id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_name` varchar(100) NOT NULL,
  PRIMARY KEY (`faculty_id`),
  UNIQUE KEY `faculty_name` (`faculty_name`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `faculties`
INSERT INTO `faculties` (`faculty_id`, `faculty_name`) VALUES ('9', 'College of Agric Econ & Extension');
INSERT INTO `faculties` (`faculty_id`, `faculty_name`) VALUES ('6', 'College of Management Sciences');
INSERT INTO `faculties` (`faculty_id`, `faculty_name`) VALUES ('7', 'College of Physical Sciences');

-- ------------------------------------------------------
-- Table structure for table `feedback`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `feedback`;
CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`feedback_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `higher_education`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `higher_education`;
CREATE TABLE `higher_education` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `highest_qualification` varchar(50) DEFAULT NULL,
  `course_study` varchar(150) DEFAULT NULL,
  `institution` varchar(150) DEFAULT NULL,
  `grad_year` int(11) DEFAULT NULL,
  `cgpa` decimal(4,2) DEFAULT NULL,
  `mode_study` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  CONSTRAINT `higher_education_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `higher_education`
INSERT INTO `higher_education` (`id`, `application_id`, `highest_qualification`, `course_study`, `institution`, `grad_year`, `cgpa`, `mode_study`) VALUES ('6', '24', 'HND', 'B. Sc Information Technology', 'OXFORD UNIVERSITY', '2012', '5.00', 'FT');
INSERT INTO `higher_education` (`id`, `application_id`, `highest_qualification`, `course_study`, `institution`, `grad_year`, `cgpa`, `mode_study`) VALUES ('7', '23', 'BSc', 'Computer Science ', 'Modibbo Adama University ', '2024', '4.76', 'FT');

-- ------------------------------------------------------
-- Table structure for table `login_attempts`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `messages`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `thread_id` varchar(80) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `idx_thread` (`thread_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `notifications`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(30) DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `idx_notify_user` (`user_id`,`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `notifications`
INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES ('1', '31', 'Referee Submitted', 'Your referee has submitted their evaluation report.', 'info', '0', '2026-07-17 10:40:00');

-- ------------------------------------------------------
-- Table structure for table `nysc_details`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `nysc_details`;
CREATE TABLE `nysc_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `nysc_status` varchar(50) DEFAULT NULL,
  `certificate_number` varchar(50) DEFAULT NULL,
  `completion_year` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  CONSTRAINT `nysc_details_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `nysc_details`
INSERT INTO `nysc_details` (`id`, `application_id`, `nysc_status`, `certificate_number`, `completion_year`) VALUES ('6', '24', 'Completed', 'A8467284', '2058');
INSERT INTO `nysc_details` (`id`, `application_id`, `nysc_status`, `certificate_number`, `completion_year`) VALUES ('7', '23', 'Completed', 'A83773737', '2025');

-- ------------------------------------------------------
-- Table structure for table `olevel_exams`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `olevel_exams`;
CREATE TABLE `olevel_exams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `sitting_number` tinyint(4) NOT NULL COMMENT '1 for First Sitting, 2 for Second Sitting',
  `exam_type` enum('WAEC','NECO','NABTEB','GCE') NOT NULL,
  `school_name` varchar(255) NOT NULL,
  `exam_year` year(4) NOT NULL,
  `exam_number` varchar(50) NOT NULL COMMENT 'Registration Number',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_sitting` (`application_id`,`sitting_number`),
  CONSTRAINT `fk_application_olevel` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `olevel_exams`
INSERT INTO `olevel_exams` (`id`, `application_id`, `sitting_number`, `exam_type`, `school_name`, `exam_year`, `exam_number`, `created_at`) VALUES ('6', '24', '1', 'WAEC', 'DSS', '2015', '34432245', '2026-07-17 10:24:35');
INSERT INTO `olevel_exams` (`id`, `application_id`, `sitting_number`, `exam_type`, `school_name`, `exam_year`, `exam_number`, `created_at`) VALUES ('7', '23', '1', 'NECO', 'GDSS ARMY BARRACKS YOLA', '2018', '98389289BA', '2026-07-17 10:25:58');

-- ------------------------------------------------------
-- Table structure for table `olevel_results`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `olevel_results`;
CREATE TABLE `olevel_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `grade` char(2) NOT NULL COMMENT 'e.g., A1, B3, C6, F9',
  PRIMARY KEY (`id`),
  KEY `idx_subject` (`subject_name`),
  KEY `fk_exam_results` (`exam_id`),
  CONSTRAINT `fk_exam_results` FOREIGN KEY (`exam_id`) REFERENCES `olevel_exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `olevel_results`
INSERT INTO `olevel_results` (`id`, `exam_id`, `subject_name`, `grade`) VALUES ('22', '6', 'ICT', 'C6');
INSERT INTO `olevel_results` (`id`, `exam_id`, `subject_name`, `grade`) VALUES ('23', '6', 'Biology', 'C5');
INSERT INTO `olevel_results` (`id`, `exam_id`, `subject_name`, `grade`) VALUES ('24', '6', 'English Language', 'E8');
INSERT INTO `olevel_results` (`id`, `exam_id`, `subject_name`, `grade`) VALUES ('25', '6', 'Mathematics', 'B2');
INSERT INTO `olevel_results` (`id`, `exam_id`, `subject_name`, `grade`) VALUES ('26', '6', 'Civic Education', 'A1');
INSERT INTO `olevel_results` (`id`, `exam_id`, `subject_name`, `grade`) VALUES ('27', '6', 'Chemistry', 'B3');
INSERT INTO `olevel_results` (`id`, `exam_id`, `subject_name`, `grade`) VALUES ('28', '6', 'Physics', 'B3');
INSERT INTO `olevel_results` (`id`, `exam_id`, `subject_name`, `grade`) VALUES ('29', '6', 'Health Science', 'C6');
INSERT INTO `olevel_results` (`id`, `exam_id`, `subject_name`, `grade`) VALUES ('30', '6', 'Technical Drawing', 'B2');
INSERT INTO `olevel_results` (`id`, `exam_id`, `subject_name`, `grade`) VALUES ('31', '7', 'English Language', 'C5');
INSERT INTO `olevel_results` (`id`, `exam_id`, `subject_name`, `grade`) VALUES ('32', '7', 'Chemistry', 'C4');
INSERT INTO `olevel_results` (`id`, `exam_id`, `subject_name`, `grade`) VALUES ('33', '7', 'Biology', 'C5');
INSERT INTO `olevel_results` (`id`, `exam_id`, `subject_name`, `grade`) VALUES ('34', '7', 'Mathematics', 'C4');
INSERT INTO `olevel_results` (`id`, `exam_id`, `subject_name`, `grade`) VALUES ('35', '7', 'Agricultural Science', 'C5');
INSERT INTO `olevel_results` (`id`, `exam_id`, `subject_name`, `grade`) VALUES ('36', '7', 'ICT', 'C5');
INSERT INTO `olevel_results` (`id`, `exam_id`, `subject_name`, `grade`) VALUES ('37', '7', 'Civic Education', 'C5');
INSERT INTO `olevel_results` (`id`, `exam_id`, `subject_name`, `grade`) VALUES ('38', '7', 'Economics', 'C5');
INSERT INTO `olevel_results` (`id`, `exam_id`, `subject_name`, `grade`) VALUES ('39', '7', 'Geography', 'C4');

-- ------------------------------------------------------
-- Table structure for table `olevel_sittings`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `olevel_sittings`;
CREATE TABLE `olevel_sittings` (
  `sitting_id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `sitting_number` int(11) NOT NULL,
  `exam_year` int(11) DEFAULT NULL,
  `exam_type` varchar(50) DEFAULT NULL,
  `school_name` varchar(150) DEFAULT NULL,
  `exam_type_other` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`sitting_id`),
  UNIQUE KEY `application_id` (`application_id`,`sitting_number`),
  CONSTRAINT `olevel_sittings_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  CONSTRAINT `olevel_sittings_chk_1` CHECK (`sitting_number` in (1,2))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `password_resets`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_reset_email` (`email`),
  KEY `idx_reset_token` (`token_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `password_resets`
INSERT INTO `password_resets` (`id`, `user_id`, `email`, `token_hash`, `expires_at`, `created_at`) VALUES ('1', '27', 'muhdmukhtar2019@gmail.com', '4a2082979ddaee54a346097a367ca11b33ba36bad1236503188d3fdba9fd446f', '2026-07-17 22:18:11', '2026-07-16 21:18:11');

-- ------------------------------------------------------
-- Table structure for table `personal_details`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `personal_details`;
CREATE TABLE `personal_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `address` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  CONSTRAINT `personal_details_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `personal_details`
INSERT INTO `personal_details` (`id`, `application_id`, `surname`, `first_name`, `other_name`, `dob`, `sex`, `nationality`, `state_origin`, `lga`, `phone`, `address`) VALUES ('7', '22', 'UMAR', 'GADDAFI', 'Aondover', '1998-12-12', 'Female', 'Nigerian', 'Adamawa', 'Yola North', '09042340091', '18 Old Assembly Quarters');
INSERT INTO `personal_details` (`id`, `application_id`, `surname`, `first_name`, `other_name`, `dob`, `sex`, `nationality`, `state_origin`, `lga`, `phone`, `address`) VALUES ('9', '23', 'Omolaye', 'Philip', '', '1984-06-13', 'Male', 'Nigerian', 'Benue', 'Makurdi', '08034266266', 'Katsina Ala street');
INSERT INTO `personal_details` (`id`, `application_id`, `surname`, `first_name`, `other_name`, `dob`, `sex`, `nationality`, `state_origin`, `lga`, `phone`, `address`) VALUES ('11', '24', 'Garba', 'Muhammad', '', '1900-01-01', 'Male', 'Nigerian', 'Adamawa', 'Maiha', '07065883821', 'Ahmadu Bello Way');

-- ------------------------------------------------------
-- Table structure for table `portal_page_sections`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `portal_page_sections`;
CREATE TABLE `portal_page_sections` (
  `section_id` int(11) NOT NULL AUTO_INCREMENT,
  `page_key` varchar(80) NOT NULL,
  `section_key` varchar(120) NOT NULL,
  `content_json` longtext NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`section_id`),
  UNIQUE KEY `uniq_page_section` (`page_key`,`section_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `programme_capacities`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `programme_capacities`;
CREATE TABLE `programme_capacities` (
  `capacity_id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`capacity_id`),
  UNIQUE KEY `unique_course_capacity` (`course_id`),
  CONSTRAINT `programme_capacities_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `programme_choices`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `programme_choices`;
CREATE TABLE `programme_choices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `faculty` int(11) DEFAULT NULL,
  `department` int(11) DEFAULT NULL,
  `degree_type` int(11) DEFAULT NULL,
  `mode_of_study` int(11) DEFAULT NULL,
  `course` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  KEY `fk_faculty` (`faculty`),
  KEY `fk_dept` (`department`),
  KEY `fk_degree` (`degree_type`),
  KEY `fk_course` (`course`),
  KEY `fk_mode` (`mode_of_study`),
  CONSTRAINT `fk_course` FOREIGN KEY (`course`) REFERENCES `courses` (`course_id`),
  CONSTRAINT `fk_degree` FOREIGN KEY (`degree_type`) REFERENCES `degree_types` (`degree_id`),
  CONSTRAINT `fk_dept` FOREIGN KEY (`department`) REFERENCES `departments` (`dept_id`),
  CONSTRAINT `fk_faculty` FOREIGN KEY (`faculty`) REFERENCES `faculties` (`faculty_id`),
  CONSTRAINT `fk_mode` FOREIGN KEY (`mode_of_study`) REFERENCES `study_modes` (`mode_id`),
  CONSTRAINT `programme_choices_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `programme_choices`
INSERT INTO `programme_choices` (`id`, `application_id`, `faculty`, `department`, `degree_type`, `mode_of_study`, `course`) VALUES ('5', '22', '7', '6', '6', '3', '15');
INSERT INTO `programme_choices` (`id`, `application_id`, `faculty`, `department`, `degree_type`, `mode_of_study`, `course`) VALUES ('7', '23', '7', '6', '7', NULL, '14');
INSERT INTO `programme_choices` (`id`, `application_id`, `faculty`, `department`, `degree_type`, `mode_of_study`, `course`) VALUES ('9', '24', '6', '7', '6', '3', '12');

-- ------------------------------------------------------
-- Table structure for table `project_status_history`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `project_status_history`;
CREATE TABLE `project_status_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `from_status` varchar(40) DEFAULT NULL,
  `to_status` varchar(40) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `actor_role` varchar(50) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `projects`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `project_id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `supervisor_id` int(11) NOT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `current_stage` varchar(40) DEFAULT 'PROJECT_ACTIVE',
  `proposal_status` varchar(30) DEFAULT 'Pending',
  `report_status` varchar(30) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`project_id`),
  KEY `idx_project_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `proposals`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `proposals`;
CREATE TABLE `proposals` (
  `proposal_id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` varchar(30) DEFAULT 'Submitted',
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  PRIMARY KEY (`proposal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `referee_requests`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `referee_requests`;
CREATE TABLE `referee_requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `referee_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `token` varchar(100) NOT NULL,
  `status` enum('Requested','Submitted','Verified','Rejected') DEFAULT 'Requested',
  `requested_by` int(11) DEFAULT NULL,
  `requested_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  UNIQUE KEY `uniq_ref_token` (`token`),
  KEY `idx_referee_id` (`referee_id`),
  KEY `idx_app_id` (`application_id`),
  CONSTRAINT `fk_ref_req_app` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ref_req_referee` FOREIGN KEY (`referee_id`) REFERENCES `referees` (`referee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `referee_requests`
INSERT INTO `referee_requests` (`request_id`, `referee_id`, `application_id`, `token`, `status`, `requested_by`, `requested_at`, `expires_at`) VALUES ('8', '6', '24', '7b4570d0b996247180cb26c8ceebe364876a3096', 'Submitted', '31', '2026-07-17 10:29:38', '2026-07-24 11:29:38');

-- ------------------------------------------------------
-- Table structure for table `referee_status`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `referee_status`;
CREATE TABLE `referee_status` (
  `referee_id` bigint(20) unsigned NOT NULL,
  `submission_status` enum('Not Submitted','Submitted','Received','Verified') NOT NULL DEFAULT 'Not Submitted',
  `received_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`referee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `referee_uploads`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `referee_uploads`;
CREATE TABLE `referee_uploads` (
  `upload_id` int(11) NOT NULL AUTO_INCREMENT,
  `referee_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `work_email` varchar(150) DEFAULT NULL,
  `passport_path` varchar(255) DEFAULT NULL,
  `work_id_path` varchar(255) DEFAULT NULL,
  `verified_status` enum('Submitted','Verified','Rejected') DEFAULT 'Submitted',
  `submitted_at` datetime DEFAULT current_timestamp(),
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `referee_name` varchar(150) DEFAULT NULL,
  `referee_title` varchar(50) DEFAULT NULL,
  `referee_organization` varchar(150) DEFAULT NULL,
  `referee_department` varchar(150) DEFAULT NULL,
  `referee_position` varchar(150) DEFAULT NULL,
  `referee_address` text DEFAULT NULL,
  `referee_phone` varchar(20) DEFAULT NULL,
  `relationship` varchar(100) DEFAULT NULL,
  `years_known` int(11) DEFAULT NULL,
  `assessment_character_integrity` enum('Excellent','Very Good','Good','Fair','Poor') DEFAULT NULL,
  `assessment_professional_competence` enum('Excellent','Very Good','Good','Fair','Poor') DEFAULT NULL,
  `assessment_leadership_ability` enum('Excellent','Very Good','Good','Fair','Poor') DEFAULT NULL,
  `assessment_communication_skills` enum('Excellent','Very Good','Good','Fair','Poor') DEFAULT NULL,
  `assessment_teamwork` enum('Excellent','Very Good','Good','Fair','Poor') DEFAULT NULL,
  `assessment_reliability` enum('Excellent','Very Good','Good','Fair','Poor') DEFAULT NULL,
  `assessment_initiative` enum('Excellent','Very Good','Good','Fair','Poor') DEFAULT NULL,
  `assessment_emotional_stability` enum('Excellent','Very Good','Good','Fair','Poor') DEFAULT NULL,
  `major_strengths` text DEFAULT NULL,
  `weaknesses` text DEFAULT NULL,
  `recommendation` enum('Strongly Recommend','Recommend','Recommend with Reservation','Do Not Recommend') DEFAULT NULL,
  `additional_comments` text DEFAULT NULL,
  `declaration_accepted` tinyint(4) DEFAULT 0,
  `signature` varchar(150) DEFAULT NULL,
  `declaration_date` date DEFAULT NULL,
  PRIMARY KEY (`upload_id`),
  KEY `idx_ref_upload_ref` (`referee_id`),
  KEY `idx_ref_upload_app` (`application_id`),
  CONSTRAINT `fk_ref_upload_app` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ref_upload_referee` FOREIGN KEY (`referee_id`) REFERENCES `referees` (`referee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `referee_uploads`
INSERT INTO `referee_uploads` (`upload_id`, `referee_id`, `application_id`, `work_email`, `passport_path`, `work_id_path`, `verified_status`, `submitted_at`, `verified_by`, `verified_at`, `rejection_reason`, `referee_name`, `referee_title`, `referee_organization`, `referee_department`, `referee_position`, `referee_address`, `referee_phone`, `relationship`, `years_known`, `assessment_character_integrity`, `assessment_professional_competence`, `assessment_leadership_ability`, `assessment_communication_skills`, `assessment_teamwork`, `assessment_reliability`, `assessment_initiative`, `assessment_emotional_stability`, `major_strengths`, `weaknesses`, `recommendation`, `additional_comments`, `declaration_accepted`, `signature`, `declaration_date`) VALUES ('3', '6', '24', NULL, NULL, NULL, 'Submitted', '2026-07-17 10:40:00', NULL, NULL, NULL, 'Muhammad Adamu Garba', 'Prof.', 'JOSTUM', 'CS', 'Professor', 'Ahmadu Bello Way', '07065883821', 'Fa', '3', NULL, NULL, NULL, NULL, 'Excellent', 'Very Good', 'Very Good', NULL, 'GOOD', 'GOOD', 'Recommend', 'GOOD', '1', 'Maher', '2026-07-17');

-- ------------------------------------------------------
-- Table structure for table `referees`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `referees`;
CREATE TABLE `referees` (
  `referee_id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `organization` varchar(150) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`referee_id`),
  KEY `application_id` (`application_id`),
  CONSTRAINT `referees_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `referees`
INSERT INTO `referees` (`referee_id`, `application_id`, `full_name`, `title`, `organization`, `email`, `phone`) VALUES ('6', '24', 'Muhammad Adamu Garba', 'Professor', 'JOSTUM', 'muhdmukhtar2019@gmail.com', '07065883821');
INSERT INTO `referees` (`referee_id`, `application_id`, `full_name`, `title`, `organization`, `email`, `phone`) VALUES ('7', '23', 'GADDAFI UMAR', 'Professor ', 'GOTEL', 'gaddafiumar4445@gmail.com', '09042340091');

-- ------------------------------------------------------
-- Table structure for table `reports`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `version_no` int(11) DEFAULT 1,
  `status` varchar(30) DEFAULT 'Submitted',
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  PRIMARY KEY (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `research_details`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `research_details`;
CREATE TABLE `research_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `research_area` varchar(255) DEFAULT NULL,
  `reason_for_choosing` text DEFAULT NULL,
  `statement_of_purpose` text DEFAULT NULL,
  `career_objectives` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  CONSTRAINT `research_details_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `research_details`
INSERT INTO `research_details` (`id`, `application_id`, `research_area`, `reason_for_choosing`, `statement_of_purpose`, `career_objectives`) VALUES ('6', '24', 'ML AND DATA SCIENCE', 'ML AND DATA SCIENCE', 'ML AND DATA SCIENCE', 'ML AND DATA SCIENCE');
INSERT INTO `research_details` (`id`, `application_id`, `research_area`, `reason_for_choosing`, `statement_of_purpose`, `career_objectives`) VALUES ('7', '23', 'AI & Machine Learning ', 'allalal', 'alklaklakl', 'lkakakkla');

-- ------------------------------------------------------
-- Table structure for table `reviewer_assignments`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `reviewer_assignments`;
CREATE TABLE `reviewer_assignments` (
  `assignment_id` int(11) NOT NULL AUTO_INCREMENT,
  `application_code` varchar(50) NOT NULL,
  `applicant_name` varchar(150) NOT NULL,
  `programme` varchar(150) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'Pending',
  `due_date` date DEFAULT NULL,
  `reviewer_name` varchar(150) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`assignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `reviewer_feedback`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `reviewer_feedback`;
CREATE TABLE `reviewer_feedback` (
  `feedback_id` int(11) NOT NULL AUTO_INCREMENT,
  `application_code` varchar(50) NOT NULL,
  `student_name` varchar(150) NOT NULL,
  `chapter` varchar(50) DEFAULT NULL,
  `feedback` text NOT NULL,
  `status` varchar(30) DEFAULT 'Awaiting Response',
  `reviewer_name` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`feedback_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `reviewer_history`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `reviewer_history`;
CREATE TABLE `reviewer_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `application_code` varchar(50) NOT NULL,
  `applicant_name` varchar(150) NOT NULL,
  `programme` varchar(150) DEFAULT NULL,
  `decision` varchar(30) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `reviewer_name` varchar(150) DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `role_permissions`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `role_key` varchar(50) NOT NULL,
  `permission_key` varchar(100) NOT NULL,
  PRIMARY KEY (`role_key`,`permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `role_permissions`
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('CENTER_LEADER', 'reports');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('CENTER_LEADER', 'view_dashboard');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('COLLEGE_ADMIN', 'bulk_verify');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('COLLEGE_ADMIN', 'faculty_review');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('COLLEGE_ADMIN', 'reports');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('COLLEGE_ADMIN', 'view_applicants');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('COLLEGE_ADMIN', 'view_dashboard');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('GENERAL', 'view_dashboard');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('HOD', 'assign_supervisor');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('HOD', 'bulk_supervisor_allocation');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('HOD', 'bulk_verify');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('HOD', 'change_supervisor');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('HOD', 'department_review');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('HOD', 'download_records');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('HOD', 'export_csv');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('HOD', 'export_excel');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('HOD', 'export_pdf');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('HOD', 'remove_supervisor');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('HOD', 'reports');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('HOD', 'supervisor_management');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('HOD', 'view_applicants');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('HOD', 'view_dashboard');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICTO', 'bulk_verify');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICTO', 'download_documents');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICTO', 'verify_applicants');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICTO', 'view_applicants');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICTO', 'view_dashboard');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'acceptance_letter');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'admission_letter');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'assign_supervisor');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'bulk_supervisor_allocation');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'bulk_verify');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'change_supervisor');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'delete_applicants');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'department_review');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'download_documents');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'download_records');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'edit_applicants');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'export_csv');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'export_excel');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'export_pdf');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'faculty_review');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'generate_matric_number');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'generate_student_number');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'ict_processing');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'logs');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'notifications');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'permission_management');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'pg_review');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'remove_supervisor');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'reports');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'role_management');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'settings');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'supervisor_management');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'upload_documents');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'user_management');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'verify_applicants');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'view_applicants');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'view_audit_logs');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'view_dashboard');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_ADMIN', 'workflow_configuration');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_STAFF', 'acceptance_letter');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_STAFF', 'admission_letter');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_STAFF', 'generate_matric_number');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_STAFF', 'generate_student_number');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_STAFF', 'ict_processing');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_STAFF', 'view_applicants');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('ICT_STAFF', 'view_dashboard');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('PG_ADMIN', 'bulk_verify');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('PG_ADMIN', 'download_records');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('PG_ADMIN', 'export_csv');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('PG_ADMIN', 'export_excel');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('PG_ADMIN', 'export_pdf');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('PG_ADMIN', 'pg_review');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('PG_ADMIN', 'reports');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('PG_ADMIN', 'view_applicants');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('PG_ADMIN', 'view_dashboard');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPERVISOR', 'supervisor_management');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPERVISOR', 'view_dashboard');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'acceptance_letter');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'admission_letter');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'assign_supervisor');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'bulk_supervisor_allocation');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'bulk_verify');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'change_supervisor');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'delete_applicants');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'department_review');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'download_documents');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'download_records');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'edit_applicants');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'export_csv');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'export_excel');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'export_pdf');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'faculty_review');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'generate_matric_number');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'generate_student_number');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'ict_processing');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'logs');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'notifications');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'permission_management');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'pg_review');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'remove_supervisor');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'reports');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'role_management');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'settings');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'supervisor_management');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'upload_documents');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'user_management');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'verify_applicants');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'view_applicants');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'view_audit_logs');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'view_dashboard');
INSERT INTO `role_permissions` (`role_key`, `permission_key`) VALUES ('SUPER_ADMIN', 'workflow_configuration');

-- ------------------------------------------------------
-- Table structure for table `roles`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_key` varchar(50) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `role_key` (`role_key`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `roles`
INSERT INTO `roles` (`role_id`, `role_key`, `role_name`, `created_at`) VALUES ('1', 'SUPER_ADMIN', 'Super Admin', '2026-07-16 21:10:37');
INSERT INTO `roles` (`role_id`, `role_key`, `role_name`, `created_at`) VALUES ('2', 'GENERAL', 'General', '2026-07-16 21:10:37');
INSERT INTO `roles` (`role_id`, `role_key`, `role_name`, `created_at`) VALUES ('3', 'STUDENT', 'Student', '2026-07-16 21:10:37');
INSERT INTO `roles` (`role_id`, `role_key`, `role_name`, `created_at`) VALUES ('4', 'ICTO', 'ICT Officer', '2026-07-16 21:10:37');
INSERT INTO `roles` (`role_id`, `role_key`, `role_name`, `created_at`) VALUES ('5', 'HOD', 'HOD/Departmental Admin', '2026-07-16 21:10:37');
INSERT INTO `roles` (`role_id`, `role_key`, `role_name`, `created_at`) VALUES ('6', 'COLLEGE_ADMIN', 'College Admin', '2026-07-16 21:10:37');
INSERT INTO `roles` (`role_id`, `role_key`, `role_name`, `created_at`) VALUES ('7', 'PG_ADMIN', 'PG Admin', '2026-07-16 21:10:37');
INSERT INTO `roles` (`role_id`, `role_key`, `role_name`, `created_at`) VALUES ('8', 'ICT_ADMIN', 'ICT Admin', '2026-07-16 21:10:37');
INSERT INTO `roles` (`role_id`, `role_key`, `role_name`, `created_at`) VALUES ('9', 'CENTER_LEADER', 'Center Leader', '2026-07-16 21:10:37');
INSERT INTO `roles` (`role_id`, `role_key`, `role_name`, `created_at`) VALUES ('10', 'SUPERVISOR', 'Supervisor', '2026-07-16 21:10:37');
INSERT INTO `roles` (`role_id`, `role_key`, `role_name`, `created_at`) VALUES ('11', 'ICT_STAFF', 'Main ICT Staff', '2026-07-16 21:10:43');

-- ------------------------------------------------------
-- Table structure for table `student_messages`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `student_messages`;
CREATE TABLE `student_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `student_notifications`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `student_notifications`;
CREATE TABLE `student_notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_notify` (`student_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `student_profiles`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `student_profiles`;
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `assigned_supervisor_user_id` int(11) DEFAULT NULL,
  `assignment_date` datetime DEFAULT NULL,
  `assigned_by_user_id` int(11) DEFAULT NULL,
  `supervisor_status` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `student_tracking_updates`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `student_tracking_updates`;
CREATE TABLE `student_tracking_updates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `note` text NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'In Progress',
  `progress` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_tracking` (`student_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `study_modes`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `study_modes`;
CREATE TABLE `study_modes` (
  `mode_id` int(11) NOT NULL AUTO_INCREMENT,
  `mode_name` varchar(50) NOT NULL,
  PRIMARY KEY (`mode_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `study_modes`
INSERT INTO `study_modes` (`mode_id`, `mode_name`) VALUES ('3', 'Full Time');

-- ------------------------------------------------------
-- Table structure for table `supervisor_assignments`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `supervisor_assignments`;
CREATE TABLE `supervisor_assignments` (
  `assignment_id` int(11) NOT NULL AUTO_INCREMENT,
  `supervisor_id` varchar(50) NOT NULL,
  `application_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `status` varchar(30) DEFAULT 'Assigned',
  PRIMARY KEY (`assignment_id`),
  UNIQUE KEY `idx_app_sup` (`application_id`,`supervisor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `supervisor_messages`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `supervisor_messages`;
CREATE TABLE `supervisor_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `supervisor_user_id` int(11) DEFAULT NULL,
  `student_user_id` int(11) DEFAULT NULL,
  `sender_role` enum('SUPERVISOR','STUDENT') DEFAULT 'STUDENT',
  `subject` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`message_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `supervisor_milestones`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `supervisor_milestones`;
CREATE TABLE `supervisor_milestones` (
  `milestone_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `acknowledged_at` datetime DEFAULT NULL,
  PRIMARY KEY (`milestone_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `supervisor_notifications`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `supervisor_notifications`;
CREATE TABLE `supervisor_notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `supervisor_id` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `supervisor_profiles`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `supervisor_profiles`;
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `department_id` int(11) DEFAULT NULL,
  `faculty_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`supervisor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `supervisor_students`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `supervisor_students`;
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
  `department_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `supervisors`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `supervisors`;
CREATE TABLE `supervisors` (
  `supervisor_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `specialization_keywords` text DEFAULT NULL,
  `max_capacity` int(11) DEFAULT 8,
  `current_students` int(11) DEFAULT 0,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`supervisor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------
-- Table structure for table `system_modules`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `system_modules`;
CREATE TABLE `system_modules` (
  `module_key` varchar(50) NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`module_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `system_modules`
INSERT INTO `system_modules` (`module_key`, `module_name`, `is_active`, `updated_at`) VALUES ('admissions', 'Admissions Exercise', '1', '2026-07-16 21:10:27');

-- ------------------------------------------------------
-- Table structure for table `system_settings`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `institution_name` varchar(255) DEFAULT 'Institute of Procurement, Environmental and Social Standard IPESS JOSTUM',
  `session_timeout_seconds` int(11) DEFAULT 900,
  `email_smtp_host` varchar(150) DEFAULT '',
  `email_smtp_port` int(11) DEFAULT 465,
  `email_smtp_user` varchar(150) DEFAULT '',
  `email_smtp_pass` varchar(150) DEFAULT '',
  `email_smtp_encryption` varchar(10) DEFAULT 'ssl',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`setting_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `system_settings`
INSERT INTO `system_settings` (`setting_id`, `institution_name`, `session_timeout_seconds`, `email_smtp_host`, `email_smtp_port`, `email_smtp_user`, `email_smtp_pass`, `email_smtp_encryption`, `created_at`) VALUES ('1', 'Institute of Procurement, Environmental and Social Standard IPESS JOSTUM', '900', '', '465', '', '', 'ssl', '2026-07-16 21:09:31');

-- ------------------------------------------------------
-- Table structure for table `user_permissions`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `user_permissions`;
CREATE TABLE `user_permissions` (
  `user_id` int(11) NOT NULL,
  `permission_key` varchar(100) NOT NULL,
  `granted` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`user_id`,`permission_key`),
  CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `user_permissions`
INSERT INTO `user_permissions` (`user_id`, `permission_key`, `granted`) VALUES ('28', 'assign_supervisor', '1');
INSERT INTO `user_permissions` (`user_id`, `permission_key`, `granted`) VALUES ('28', 'supervisor_management', '1');

-- ------------------------------------------------------
-- Table structure for table `users`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `totp_verified_at` datetime DEFAULT NULL,
  `faculty_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_role_id` (`role_id`),
  KEY `idx_department_id` (`department_id`),
  CONSTRAINT `users_ibfk_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `users`
INSERT INTO `users` (`user_id`, `email`, `full_name`, `role_id`, `avatar_url`, `department_id`, `password_hash`, `last_login`, `created_at`, `reset_token`, `reset_expires`, `account_status`, `totp_secret`, `totp_enabled`, `totp_verified_at`, `faculty_id`) VALUES ('27', 'muhdmukhtar2019@gmail.com', 'Muhammad Mukhtar', '1', NULL, NULL, '$2y$10$2lAuVAxnyIzObhZpexKKg.NgsTP2hPtB6XdqonGG1QuaSS4DDaB4O', NULL, '2026-07-16 21:18:11', 'ab94cd69f6b1e9ed42adab0582ac70fe', '2026-07-17 22:18:11', 'Active', 'RCFOMOLCUPMEQBPN5G7C', '0', NULL, NULL);
INSERT INTO `users` (`user_id`, `email`, `full_name`, `role_id`, `avatar_url`, `department_id`, `password_hash`, `last_login`, `created_at`, `reset_token`, `reset_expires`, `account_status`, `totp_secret`, `totp_enabled`, `totp_verified_at`, `faculty_id`) VALUES ('28', 'muhd.maher4u@gmail.com', 'Muhammad Garba', '8', NULL, NULL, '$2y$10$vt32WNV9z1anJg4B2YAAcew..MXauuH/7VdFKl7qP9iH24Z4SjnwK', NULL, '2026-07-16 21:38:04', '075fc63429df5c6f9ac4ecf376067dba', '2026-07-17 22:38:04', 'Active', '64CGUOJBJEM4Q5XMDFL5', '0', NULL, NULL);
INSERT INTO `users` (`user_id`, `email`, `full_name`, `role_id`, `avatar_url`, `department_id`, `password_hash`, `last_login`, `created_at`, `reset_token`, `reset_expires`, `account_status`, `totp_secret`, `totp_enabled`, `totp_verified_at`, `faculty_id`) VALUES ('29', 'gaddafiumar4445@gmail.com', 'UMAR GADDAFI Aondover', '3', NULL, NULL, '$2y$10$.Ilz0bMMJfGZJxzCXNkCRe0vjKjDWzhgyvJiWoUOvo/hpgG/jmc3a', '2026-07-16 21:39:06', '2026-07-16 21:38:51', NULL, NULL, 'Active', NULL, '0', NULL, NULL);
INSERT INTO `users` (`user_id`, `email`, `full_name`, `role_id`, `avatar_url`, `department_id`, `password_hash`, `last_login`, `created_at`, `reset_token`, `reset_expires`, `account_status`, `totp_secret`, `totp_enabled`, `totp_verified_at`, `faculty_id`) VALUES ('30', 'umgaddafi6@gmail.com', 'UMAR GADDAFI', '3', NULL, NULL, '$2y$10$7vlhTjifGPDQAopDB2RbR.4BPb7AXxifYKlPOx5i42V8cCPrA4nr6', '2026-07-17 11:09:01', '2026-07-17 10:06:59', NULL, NULL, 'Active', NULL, '0', NULL, NULL);
INSERT INTO `users` (`user_id`, `email`, `full_name`, `role_id`, `avatar_url`, `department_id`, `password_hash`, `last_login`, `created_at`, `reset_token`, `reset_expires`, `account_status`, `totp_secret`, `totp_enabled`, `totp_verified_at`, `faculty_id`) VALUES ('31', 'adamu.mohammad@uam.edu.ng', 'Garba Muhammad', '3', NULL, NULL, '$2y$10$ddzXtC0h.f7ix.W7Ur1m.eyyUSPNmORsaIQp4EP2dp5UJKeV3uXB6', '2026-07-17 10:35:48', '2026-07-17 10:21:46', NULL, NULL, 'Active', NULL, '0', NULL, NULL);

-- ------------------------------------------------------
-- Table structure for table `work_experience`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `work_experience`;
CREATE TABLE `work_experience` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `employment_status` varchar(50) DEFAULT NULL,
  `employer` varchar(150) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `years_experience` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  CONSTRAINT `work_experience_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `work_experience`
INSERT INTO `work_experience` (`id`, `application_id`, `employment_status`, `employer`, `job_title`, `years_experience`) VALUES ('6', '24', 'Student', NULL, NULL, NULL);
INSERT INTO `work_experience` (`id`, `application_id`, `employment_status`, `employer`, `job_title`, `years_experience`) VALUES ('7', '23', 'Employed', 'Jostum', 'programmer/Analyst', '1');

-- ------------------------------------------------------
-- Table structure for table `workflow_audit_logs`
-- ------------------------------------------------------
DROP TABLE IF EXISTS `workflow_audit_logs`;
CREATE TABLE `workflow_audit_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `faculty_id` int(11) DEFAULT NULL,
  `action` varchar(150) NOT NULL,
  `applicant_id` int(11) DEFAULT NULL,
  `old_status` varchar(100) DEFAULT NULL,
  `new_status` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `bulk_action_id` varchar(50) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
