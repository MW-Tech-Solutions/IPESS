-- JOSTUM consolidated production schema
-- Generated from pg.sql plus workflow/content migrations on 2026-05-21.



-- Source: pg.sql

-- MySQL dump 10.13  Distrib 8.0.44, for Win64 (x86_64)
--
-- Host: localhost    Database: pg
-- ------------------------------------------------------
-- Server version	9.4.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin_notifications`
--

DROP TABLE IF EXISTS `admin_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_notifications` (
  `notification_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `category` enum('SYSTEM','USER','APPLICATION','SECURITY') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'SYSTEM',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `related_user_id` int DEFAULT NULL,
  `actor_user_id` int DEFAULT NULL,
  PRIMARY KEY (`notification_id`),
  KEY `idx_admin_notifications_read` (`is_read`),
  KEY `idx_admin_notifications_created` (`created_at`),
  KEY `admin_notifications_ibfk_1` (`related_user_id`),
  KEY `admin_notifications_ibfk_2` (`actor_user_id`),
  CONSTRAINT `admin_notifications_ibfk_1` FOREIGN KEY (`related_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `admin_notifications_ibfk_2` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_notifications`
--


--
-- Table structure for table `admin_recovery_codes`
--

DROP TABLE IF EXISTS `admin_recovery_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_recovery_codes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int unsigned NOT NULL,
  `code` varchar(255) NOT NULL,
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_admin_recovery` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_recovery_codes`
--


--
-- Table structure for table `admin_reports`
--

DROP TABLE IF EXISTS `admin_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_reports` (
  `report_id` int NOT NULL AUTO_INCREMENT,
  `report_name` varchar(255) NOT NULL,
  `report_type` varchar(100) NOT NULL,
  `format` varchar(20) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `generated_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`report_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_reports`
--


--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `google2fa_enabled` tinyint(1) DEFAULT '0',
  `google2fa_secret` text,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--


--
-- Table structure for table `applicant_accounts`
--

DROP TABLE IF EXISTS `applicant_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `applicant_accounts` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `account_status` enum('Active','Suspended','Locked') DEFAULT 'Active',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applicant_accounts`
--


--
-- Table structure for table `applicant_notifications`
--

DROP TABLE IF EXISTS `applicant_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `applicant_notifications` (
  `notification_id` int NOT NULL AUTO_INCREMENT,
  `application_id` int NOT NULL,
  `notification_title` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `notification_message` text COLLATE utf8mb4_general_ci NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `fk_applicant_notifications_application` (`application_id`),
  CONSTRAINT `fk_applicant_notifications_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applicant_notifications`
--


--
-- Table structure for table `applicants`
--

DROP TABLE IF EXISTS `applicants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `applicants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applicants`
--


--
-- Table structure for table `application_progress`
--

DROP TABLE IF EXISTS `application_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `application_progress` (
  `progress_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `application_id` bigint unsigned NOT NULL,
  `stage` enum('Application Submitted','Documents Verified','Academic Review','Referee Reports','Final Decision') COLLATE utf8mb4_general_ci NOT NULL,
  `stage_status` enum('Pending','In Progress','Completed') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pending',
  `stage_updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`progress_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application_progress`
--


--
-- Table structure for table `application_status`
--

DROP TABLE IF EXISTS `application_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `application_status` (
  `status_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `application_id` bigint unsigned NOT NULL,
  `public_status` enum('Submitted','Under Review','Shortlisted','Decision Made','Admitted','Not Admitted','Deferred') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Submitted',
  `internal_status` enum('Draft','Submitted','Document Verification','Academic Review','Referee Review','Committee Review','Final Decision') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Submitted',
  `status_updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application_status`
--


--
-- Table structure for table `applications`
--

DROP TABLE IF EXISTS `applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `applications` (
  `application_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `application_number` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('Draft','Submitted','Admitted','Rejected') COLLATE utf8mb4_general_ci DEFAULT 'Draft',
  `current_step` int DEFAULT '1',
  `submitted_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `department_id` int DEFAULT NULL,
  `reviewer_id` int DEFAULT NULL,
  `completion_percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `current_status` varchar(60) COLLATE utf8mb4_general_ci DEFAULT 'DRAFT',
  PRIMARY KEY (`application_id`),
  UNIQUE KEY `application_number` (`application_number`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applications`
--


--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `log_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` int DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `entity` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `details` text COLLATE utf8mb4_general_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `severity` enum('INFO','WARNING','CRITICAL') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'INFO',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_actor_user` (`actor_user_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--


--
-- Table structure for table `chapter_submissions`
--

DROP TABLE IF EXISTS `chapter_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chapter_submissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `student_user_id` int NOT NULL,
  `application_id` int DEFAULT NULL,
  `application_number` varchar(50) DEFAULT NULL,
  `chapter_no` tinyint NOT NULL,
  `chapter_label` varchar(100) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_ext` varchar(10) DEFAULT NULL,
  `status` enum('Submitted','Under Review','Changes Requested','Approved') NOT NULL DEFAULT 'Submitted',
  `supervisor_note` text,
  `supervisor_user_id` int DEFAULT NULL,
  `review_file_path` varchar(255) DEFAULT NULL,
  `version_no` int NOT NULL DEFAULT '1',
  `submitted_at` datetime NOT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chapter_student` (`student_user_id`),
  KEY `idx_chapter_number` (`chapter_no`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chapter_submissions`
--


--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `courses` (
  `course_id` int NOT NULL AUTO_INCREMENT,
  `course_title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `dept_id` int NOT NULL,
  `degree_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`course_id`),
  KEY `dept_id` (`dept_id`),
  KEY `degree_id` (`degree_id`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`) ON DELETE CASCADE,
  CONSTRAINT `courses_ibfk_2` FOREIGN KEY (`degree_id`) REFERENCES `degree_types` (`degree_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--


--
-- Table structure for table `degree_types`
--

DROP TABLE IF EXISTS `degree_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `degree_types` (
  `degree_id` int NOT NULL AUTO_INCREMENT,
  `degree_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`degree_id`),
  UNIQUE KEY `degree_name` (`degree_name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `degree_types`
--


--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `dept_id` int NOT NULL AUTO_INCREMENT,
  `dept_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `faculty_id` int NOT NULL,
  PRIMARY KEY (`dept_id`),
  UNIQUE KEY `dept_name` (`dept_name`,`faculty_id`),
  KEY `faculty_id` (`faculty_id`),
  CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`faculty_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--


--
-- Table structure for table `dept_applications`
--

DROP TABLE IF EXISTS `dept_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dept_applications` (
  `app_code` varchar(50) NOT NULL,
  `applicant_name` varchar(150) NOT NULL,
  `programme` varchar(150) NOT NULL,
  `status` varchar(50) NOT NULL,
  `reviewer_name` varchar(150) DEFAULT NULL,
  `submitted_date` date DEFAULT NULL,
  `priority` varchar(20) DEFAULT 'Normal',
  `department` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`app_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dept_applications`
--


--
-- Table structure for table `document_verification`
--

DROP TABLE IF EXISTS `document_verification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_verification` (
  `verification_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `upload_id` bigint unsigned NOT NULL,
  `verification_status` enum('Pending','Verified','Re-upload Required') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pending',
  `verified_by` bigint unsigned DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `admin_remark` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`verification_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_verification`
--


--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `doc_id` int NOT NULL AUTO_INCREMENT,
  `application_id` int NOT NULL,
  `document_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('Pending','Verified','Rejected') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pending',
  `comments` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`doc_id`),
  UNIQUE KEY `unique_app_doc` (`application_id`,`document_type`),
  KEY `idx_documents_app_doc` (`application_id`,`document_type`),
  CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=206 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--


--
-- Table structure for table `faculties`
--

DROP TABLE IF EXISTS `faculties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `faculties` (
  `faculty_id` int NOT NULL AUTO_INCREMENT,
  `faculty_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`faculty_id`),
  UNIQUE KEY `faculty_name` (`faculty_name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faculties`
--


--
-- Table structure for table `higher_education`
--

DROP TABLE IF EXISTS `higher_education`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `higher_education` (
  `id` int NOT NULL AUTO_INCREMENT,
  `application_id` int NOT NULL,
  `highest_qualification` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `course_study` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `institution` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `grad_year` int DEFAULT NULL,
  `cgpa` decimal(4,2) DEFAULT NULL,
  `mode_study` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  CONSTRAINT `higher_education_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `higher_education`
--


--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--


--
-- Table structure for table `nysc_details`
--

DROP TABLE IF EXISTS `nysc_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nysc_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `application_id` int NOT NULL,
  `nysc_status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `certificate_number` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `completion_year` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  CONSTRAINT `nysc_details_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `nysc_details`
--


--
-- Table structure for table `olevel_exams`
--

DROP TABLE IF EXISTS `olevel_exams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olevel_exams` (
  `id` int NOT NULL AUTO_INCREMENT,
  `application_id` int NOT NULL,
  `sitting_number` tinyint NOT NULL COMMENT '1 for First Sitting, 2 for Second Sitting',
  `exam_type` enum('WAEC','NECO','NABTEB','GCE') COLLATE utf8mb4_general_ci NOT NULL,
  `school_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `exam_year` year NOT NULL,
  `exam_number` varchar(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Registration Number',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_sitting` (`application_id`,`sitting_number`),
  CONSTRAINT `fk_application_olevel` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `olevel_exams`
--


--
-- Table structure for table `olevel_results`
--

DROP TABLE IF EXISTS `olevel_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olevel_results` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `subject_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `grade` char(2) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'e.g., A1, B3, C6, F9',
  PRIMARY KEY (`id`),
  KEY `idx_subject` (`subject_name`),
  KEY `fk_exam_results` (`exam_id`),
  CONSTRAINT `fk_exam_results` FOREIGN KEY (`exam_id`) REFERENCES `olevel_exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `olevel_results`
--


--
-- Table structure for table `olevel_sittings`
--

DROP TABLE IF EXISTS `olevel_sittings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olevel_sittings` (
  `sitting_id` int NOT NULL AUTO_INCREMENT,
  `application_id` int NOT NULL,
  `sitting_number` int NOT NULL,
  `exam_year` int DEFAULT NULL,
  `exam_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `school_name` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `exam_type_other` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`sitting_id`),
  UNIQUE KEY `application_id` (`application_id`,`sitting_number`),
  CONSTRAINT `olevel_sittings_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  CONSTRAINT `olevel_sittings_chk_1` CHECK ((`sitting_number` in (1,2)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `olevel_sittings`
--


--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `email` varchar(150) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reset_email` (`email`),
  KEY `idx_reset_token` (`token_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--


--
-- Table structure for table `personal_details`
--

DROP TABLE IF EXISTS `personal_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `application_id` int NOT NULL,
  `surname` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `other_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `dob` date NOT NULL,
  `sex` enum('Male','Female','Other') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nationality` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `state_origin` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lga` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  CONSTRAINT `personal_details_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_details`
--


--
-- Table structure for table `programme_capacities`
--

DROP TABLE IF EXISTS `programme_capacities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `programme_capacities` (
  `capacity_id` int NOT NULL AUTO_INCREMENT,
  `course_id` int NOT NULL,
  `capacity` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`capacity_id`),
  UNIQUE KEY `unique_course_capacity` (`course_id`),
  CONSTRAINT `programme_capacities_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `programme_capacities`
--


--
-- Table structure for table `programme_choices`
--

DROP TABLE IF EXISTS `programme_choices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `programme_choices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `application_id` int NOT NULL,
  `faculty` int DEFAULT NULL,
  `department` int DEFAULT NULL,
  `degree_type` int DEFAULT NULL,
  `mode_of_study` int DEFAULT NULL,
  `course` int DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `programme_choices`
--


--
-- Table structure for table `referee_requests`
--

DROP TABLE IF EXISTS `referee_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referee_requests` (
  `request_id` int NOT NULL AUTO_INCREMENT,
  `referee_id` int NOT NULL,
  `application_id` int NOT NULL,
  `token` varchar(100) NOT NULL,
  `status` enum('Requested','Submitted','Verified','Rejected') DEFAULT 'Requested',
  `requested_by` int DEFAULT NULL,
  `requested_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  UNIQUE KEY `uniq_ref_token` (`token`),
  KEY `idx_referee_id` (`referee_id`),
  KEY `idx_app_id` (`application_id`),
  CONSTRAINT `fk_ref_req_app` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ref_req_referee` FOREIGN KEY (`referee_id`) REFERENCES `referees` (`referee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referee_requests`
--


--
-- Table structure for table `referee_status`
--

DROP TABLE IF EXISTS `referee_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referee_status` (
  `referee_id` bigint unsigned NOT NULL,
  `submission_status` enum('Not Submitted','Submitted','Received','Verified') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Not Submitted',
  `received_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`referee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referee_status`
--


--
-- Table structure for table `referee_uploads`
--

DROP TABLE IF EXISTS `referee_uploads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referee_uploads` (
  `upload_id` int NOT NULL AUTO_INCREMENT,
  `referee_id` int NOT NULL,
  `application_id` int NOT NULL,
  `work_email` varchar(150) DEFAULT NULL,
  `passport_path` varchar(255) DEFAULT NULL,
  `work_id_path` varchar(255) DEFAULT NULL,
  `verified_status` enum('Submitted','Verified','Rejected') DEFAULT 'Submitted',
  `submitted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `verified_by` int DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `rejection_reason` text,
  PRIMARY KEY (`upload_id`),
  KEY `idx_ref_upload_ref` (`referee_id`),
  KEY `idx_ref_upload_app` (`application_id`),
  CONSTRAINT `fk_ref_upload_app` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ref_upload_referee` FOREIGN KEY (`referee_id`) REFERENCES `referees` (`referee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referee_uploads`
--


--
-- Table structure for table `referees`
--

DROP TABLE IF EXISTS `referees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referees` (
  `referee_id` int NOT NULL AUTO_INCREMENT,
  `application_id` int NOT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `title` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `organization` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`referee_id`),
  KEY `application_id` (`application_id`),
  CONSTRAINT `referees_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referees`
--


--
-- Table structure for table `research_details`
--

DROP TABLE IF EXISTS `research_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `research_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `application_id` int NOT NULL,
  `research_area` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reason_for_choosing` text COLLATE utf8mb4_general_ci,
  `statement_of_purpose` text COLLATE utf8mb4_general_ci,
  `career_objectives` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  CONSTRAINT `research_details_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `research_details`
--


--
-- Table structure for table `reviewer_assignments`
--

DROP TABLE IF EXISTS `reviewer_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviewer_assignments` (
  `assignment_id` int NOT NULL AUTO_INCREMENT,
  `application_code` varchar(50) NOT NULL,
  `applicant_name` varchar(150) NOT NULL,
  `programme` varchar(150) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'Pending',
  `due_date` date DEFAULT NULL,
  `reviewer_name` varchar(150) DEFAULT NULL,
  `score` int DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`assignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviewer_assignments`
--


--
-- Table structure for table `reviewer_feedback`
--

DROP TABLE IF EXISTS `reviewer_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviewer_feedback` (
  `feedback_id` int NOT NULL AUTO_INCREMENT,
  `application_code` varchar(50) NOT NULL,
  `student_name` varchar(150) NOT NULL,
  `chapter` varchar(50) DEFAULT NULL,
  `feedback` text NOT NULL,
  `status` varchar(30) DEFAULT 'Awaiting Response',
  `reviewer_name` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`feedback_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviewer_feedback`
--


--
-- Table structure for table `reviewer_history`
--

DROP TABLE IF EXISTS `reviewer_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviewer_history` (
  `history_id` int NOT NULL AUTO_INCREMENT,
  `application_code` varchar(50) NOT NULL,
  `applicant_name` varchar(150) NOT NULL,
  `programme` varchar(150) DEFAULT NULL,
  `decision` varchar(30) DEFAULT NULL,
  `score` int DEFAULT NULL,
  `comment` text,
  `reviewer_name` varchar(150) DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviewer_history`
--


--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `role_id` int NOT NULL AUTO_INCREMENT,
  `role_key` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `role_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `role_key` (`role_key`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--


--
-- Table structure for table `student_messages`
--

DROP TABLE IF EXISTS `student_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_messages` (
  `message_id` int NOT NULL AUTO_INCREMENT,
  `student_id` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_messages`
--


--
-- Table structure for table `student_notifications`
--

DROP TABLE IF EXISTS `student_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_notifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `student_user_id` int NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_notify` (`student_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_notifications`
--


--
-- Table structure for table `student_profiles`
--

DROP TABLE IF EXISTS `student_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_profiles` (
  `student_id` varchar(50) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `programme` varchar(150) DEFAULT NULL,
  `supervisor_name` varchar(150) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `progress_pct` int DEFAULT '0',
  `last_activity` varchar(50) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `research_topic` text,
  `notes` text,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_profiles`
--


--
-- Table structure for table `student_tracking_updates`
--

DROP TABLE IF EXISTS `student_tracking_updates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_tracking_updates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `student_user_id` int NOT NULL,
  `title` varchar(200) NOT NULL,
  `note` text NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'In Progress',
  `progress` int NOT NULL DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_tracking` (`student_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_tracking_updates`
--


--
-- Table structure for table `study_modes`
--

DROP TABLE IF EXISTS `study_modes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `study_modes` (
  `mode_id` int NOT NULL AUTO_INCREMENT,
  `mode_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`mode_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `study_modes`
--


--
-- Table structure for table `supervisor_messages`
--

DROP TABLE IF EXISTS `supervisor_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supervisor_messages` (
  `message_id` int NOT NULL AUTO_INCREMENT,
  `student_id` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `supervisor_user_id` int DEFAULT NULL,
  `student_user_id` int DEFAULT NULL,
  `sender_role` enum('SUPERVISOR','STUDENT') DEFAULT 'STUDENT',
  `subject` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`message_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supervisor_messages`
--


--
-- Table structure for table `supervisor_milestones`
--

DROP TABLE IF EXISTS `supervisor_milestones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supervisor_milestones` (
  `milestone_id` int NOT NULL AUTO_INCREMENT,
  `student_name` varchar(150) NOT NULL,
  `title` varchar(200) NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(30) DEFAULT 'Upcoming',
  `note` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `supervisor_user_id` int DEFAULT NULL,
  `student_user_id` int DEFAULT NULL,
  `application_id` int DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  PRIMARY KEY (`milestone_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supervisor_milestones`
--


--
-- Table structure for table `supervisor_notifications`
--

DROP TABLE IF EXISTS `supervisor_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supervisor_notifications` (
  `notification_id` int NOT NULL AUTO_INCREMENT,
  `supervisor_id` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supervisor_notifications`
--


--
-- Table structure for table `supervisor_profiles`
--

DROP TABLE IF EXISTS `supervisor_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supervisor_profiles` (
  `supervisor_id` varchar(50) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `title` varchar(120) DEFAULT NULL,
  `specialization` varchar(150) DEFAULT NULL,
  `max_capacity` int DEFAULT '8',
  `current_students` int DEFAULT '0',
  `status` varchar(20) DEFAULT 'Active',
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `research_interests` text,
  `notes` text,
  `last_active` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`supervisor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supervisor_profiles`
--


--
-- Table structure for table `supervisor_students`
--

DROP TABLE IF EXISTS `supervisor_students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supervisor_students` (
  `student_id` varchar(50) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `programme` varchar(150) DEFAULT NULL,
  `current_chapter` varchar(50) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'Pending Review',
  `last_submission` date DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `progress_pct` int DEFAULT '0',
  `supervisor_name` varchar(150) DEFAULT NULL,
  `notes` text,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `supervisor_user_id` int DEFAULT NULL,
  `student_user_id` int DEFAULT NULL,
  `application_id` int DEFAULT NULL,
  `application_number` varchar(50) DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  PRIMARY KEY (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supervisor_students`
--


--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `settings_id` int NOT NULL AUTO_INCREMENT,
  `institution_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `support_email` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `website_url` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `password_min_length` int NOT NULL DEFAULT '8',
  `lockout_attempts` int NOT NULL DEFAULT '5',
  `session_timeout` int NOT NULL DEFAULT '60',
  `two_factor_policy` enum('REQUIRED','OPTIONAL','DISABLED') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'REQUIRED',
  `audit_level` enum('STANDARD','VERBOSE','CRITICAL') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'STANDARD',
  `smtp_host` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `smtp_port` int NOT NULL DEFAULT '587',
  `smtp_encryption` enum('TLS','SSL','NONE') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'TLS',
  `system_email` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reply_to_email` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`settings_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--


--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role_id` int DEFAULT NULL,
  `avatar_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reset_token` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `account_status` enum('Active','Suspended','Locked') COLLATE utf8mb4_general_ci DEFAULT 'Active',
  `totp_secret` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `totp_enabled` tinyint(1) DEFAULT '0',
  `totp_verified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_role_id` (`role_id`),
  KEY `idx_department_id` (`department_id`),
  CONSTRAINT `users_ibfk_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--


--
-- Temporary view structure for view `v_applicant_accounts`
--

DROP TABLE IF EXISTS `v_applicant_accounts`;
/*!50001 DROP VIEW IF EXISTS `v_applicant_accounts`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_applicant_accounts` AS SELECT 
 1 AS `user_id`,
 1 AS `email`,
 1 AS `password_hash`,
 1 AS `last_login`,
 1 AS `created_at`,
 1 AS `reset_token`,
 1 AS `reset_expires`,
 1 AS `account_status`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_document_verification_like`
--

DROP TABLE IF EXISTS `v_document_verification_like`;
/*!50001 DROP VIEW IF EXISTS `v_document_verification_like`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_document_verification_like` AS SELECT 
 1 AS `upload_id`,
 1 AS `application_id`,
 1 AS `document_type`,
 1 AS `file_path`,
 1 AS `uploaded_at`,
 1 AS `verification_status`,
 1 AS `admin_remark`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `work_experience`
--

DROP TABLE IF EXISTS `work_experience`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_experience` (
  `id` int NOT NULL AUTO_INCREMENT,
  `application_id` int NOT NULL,
  `employment_status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `employer` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `job_title` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `years_experience` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  CONSTRAINT `work_experience_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_experience`
--


--
-- Final view structure for view `v_applicant_accounts`
--

/*!50001 DROP VIEW IF EXISTS `v_applicant_accounts`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_applicant_accounts` AS select `users`.`user_id` AS `user_id`,`users`.`email` AS `email`,`users`.`password_hash` AS `password_hash`,`users`.`last_login` AS `last_login`,`users`.`created_at` AS `created_at`,`users`.`reset_token` AS `reset_token`,`users`.`reset_expires` AS `reset_expires`,coalesce(`users`.`account_status`,'Active') AS `account_status` from `users` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_document_verification_like`
--

/*!50001 DROP VIEW IF EXISTS `v_document_verification_like`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_document_verification_like` AS select `d`.`doc_id` AS `upload_id`,`d`.`application_id` AS `application_id`,`d`.`document_type` AS `document_type`,`d`.`file_path` AS `file_path`,`d`.`uploaded_at` AS `uploaded_at`,`d`.`status` AS `verification_status`,`d`.`comments` AS `admin_remark` from `documents` `d` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-16 16:20:49

-- Source: workflow_migration.sql

-- Workflow + RBAC + Project system migrations (add-only).

CREATE TABLE IF NOT EXISTS roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_key VARCHAR(50) UNIQUE,
    role_name VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) UNIQUE,
    full_name VARCHAR(150) DEFAULT NULL,
    role_id INT DEFAULT NULL,
    department_id INT DEFAULT NULL,
    account_status VARCHAR(30) DEFAULT 'Active',
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE applications
    ADD COLUMN current_status VARCHAR(60) DEFAULT 'DRAFT',
    ADD COLUMN completion_percentage INT DEFAULT 0,
    ADD COLUMN department_id INT DEFAULT NULL,
    ADD COLUMN assigned_reviewer_id INT DEFAULT NULL,
    ADD COLUMN approved_at DATETIME DEFAULT NULL;

CREATE TABLE IF NOT EXISTS application_status_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    from_status VARCHAR(60) DEFAULT NULL,
    to_status VARCHAR(60) NOT NULL,
    actor_id INT DEFAULT NULL,
    actor_role VARCHAR(50) DEFAULT NULL,
    note TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_app_status (application_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS application_documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    doc_type VARCHAR(80) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    verified_status VARCHAR(20) DEFAULT 'Pending',
    verified_by INT DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    rejection_reason TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_app_docs (application_id, doc_type)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS referee_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    referee_id INT NOT NULL,
    application_id INT NOT NULL,
    token VARCHAR(80) UNIQUE NOT NULL,
    status VARCHAR(30) DEFAULT 'Requested',
    requested_by INT DEFAULT NULL,
    requested_at DATETIME DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS referee_uploads (
    upload_id INT AUTO_INCREMENT PRIMARY KEY,
    referee_id INT NOT NULL UNIQUE,
    application_id INT NOT NULL,
    work_email VARCHAR(150) DEFAULT NULL,
    passport_path VARCHAR(255) DEFAULT NULL,
    work_id_path VARCHAR(255) DEFAULT NULL,
    submitted_at DATETIME DEFAULT NULL,
    verified_status VARCHAR(30) DEFAULT 'Pending',
    verified_by INT DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    rejection_reason TEXT DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(30) DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notify_user (user_id, is_read)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    event VARCHAR(150) NOT NULL,
    user INT DEFAULT NULL,
    details TEXT DEFAULT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_time (timestamp)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS departments (
    dept_id INT AUTO_INCREMENT PRIMARY KEY,
    dept_name VARCHAR(150) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS supervisors (
    supervisor_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    department_id INT DEFAULT NULL,
    full_name VARCHAR(150) NOT NULL,
    specialization_keywords TEXT DEFAULT NULL,
    max_capacity INT DEFAULT 8,
    current_students INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS supervisor_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    supervisor_id INT NOT NULL,
    application_id INT NOT NULL,
    student_id INT NOT NULL,
    assigned_by INT DEFAULT NULL,
    assigned_at DATETIME DEFAULT NULL,
    status VARCHAR(30) DEFAULT 'Assigned',
    INDEX idx_supervisor_assign (supervisor_id, student_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS projects (
    project_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    student_id INT NOT NULL,
    supervisor_id INT NOT NULL,
    topic VARCHAR(255) DEFAULT NULL,
    current_stage VARCHAR(40) DEFAULT 'PROJECT_ACTIVE',
    proposal_status VARCHAR(30) DEFAULT 'Pending',
    report_status VARCHAR(30) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    INDEX idx_project_student (student_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS proposals (
    proposal_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    status VARCHAR(30) DEFAULT 'Submitted',
    submitted_at DATETIME DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    feedback TEXT DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    version_no INT DEFAULT 1,
    status VARCHAR(30) DEFAULT 'Submitted',
    submitted_at DATETIME DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    feedback TEXT DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    thread_id VARCHAR(80) NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME DEFAULT NULL,
    INDEX idx_thread (thread_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS project_status_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    from_status VARCHAR(40) DEFAULT NULL,
    to_status VARCHAR(40) NOT NULL,
    actor_id INT DEFAULT NULL,
    actor_role VARCHAR(50) DEFAULT NULL,
    note TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Indexes for core workflow filtering
CREATE INDEX idx_app_status ON applications (current_status);
CREATE INDEX idx_app_submitted ON applications (submitted_at);
CREATE INDEX idx_app_department ON applications (department_id);

-- Source: portal_admin_content_migration.sql

CREATE TABLE IF NOT EXISTS admissions_programmes (
    programme_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admissions_requirements (
    requirement_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admissions_important_dates (
    date_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    event_date DATE NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admissions_faqs (
    faq_id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admissions_notices (
    notice_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    button_label VARCHAR(80) NULL,
    button_url VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS portal_page_sections (
    section_id INT AUTO_INCREMENT PRIMARY KEY,
    page_key VARCHAR(80) NOT NULL,
    section_key VARCHAR(120) NOT NULL,
    content_json LONGTEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_page_section (page_key, section_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SELECT 'PORTAL_ADMIN', 'Portal Admin'
WHERE NOT EXISTS (
    SELECT 1 FROM roles WHERE role_key = 'PORTAL_ADMIN'
);

SELECT 'JUPEB', 'Joint Universities Preliminary Examinations Board programme for direct-entry progression.', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM admissions_programmes);

SELECT 'IPESS', 'Institute and professional studies pathways for specialized academic and professional development.', 2, 1
WHERE NOT EXISTS (SELECT 1 FROM admissions_programmes WHERE title = 'IPESS');

SELECT 'PGD', 'Postgraduate diploma programmes for academic transition and professional growth.', 3, 1
WHERE NOT EXISTS (SELECT 1 FROM admissions_programmes WHERE title = 'PGD');

SELECT 'Under Graduate', 'Undergraduate degree programmes for candidates seeking first-degree admission across available disciplines.', 5, 1
WHERE NOT EXISTS (SELECT 1 FROM admissions_programmes WHERE title = 'Under Graduate');

SELECT 'Academic Qualification', 'Applicants must possess the minimum academic qualifications required for the chosen programme.', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM admissions_requirements);

SELECT 'Supporting Documents', 'Applicants should provide relevant credentials, transcripts, and any supporting evidence required by the school.', 2, 1
WHERE NOT EXISTS (SELECT 1 FROM admissions_requirements WHERE title = 'Supporting Documents');

SELECT 'Applications Open', '2026-01-20', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM admissions_important_dates);

SELECT 'Final Submission Deadline', '2026-05-30', 2, 1
WHERE NOT EXISTS (SELECT 1 FROM admissions_important_dates WHERE title = 'Final Submission Deadline');

SELECT 'Can I edit my application after submission?', 'Yes, you can log in and update your application before the final deadline where the portal keeps that window open.', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM admissions_faqs);

SELECT 'Where do I pay my application fee?', 'Payment instructions are provided on the application portal after registration.', 2, 1
WHERE NOT EXISTS (SELECT 1 FROM admissions_faqs WHERE question = 'Where do I pay my application fee?');

SELECT 'Important Notice', 'Admissions updates, deadlines, and urgent notices will appear here once published by the portal administration team.', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM admissions_notices);

