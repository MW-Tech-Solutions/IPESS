-- =========================================================================
-- Safe DB-wide primary key and auto-increment repair script for wdlgdgmy_ipess.sql
-- Automatically cleans duplicate rows in any table before applying primary keys
-- =========================================================================
SET FOREIGN_KEY_CHECKS = 0;

-- Table: admins
SET @new_id := 0;
UPDATE `admins` SET `id` = (@new_id := @new_id + 1) WHERE `id` = 0;
ALTER TABLE `admins` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `admins` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `admins` t1 INNER JOIN `admins` t2 WHERE t1.`id` = t2.`id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `admins` DROP COLUMN `temp_id`;
ALTER TABLE `admins` ADD PRIMARY KEY (`id`);
ALTER TABLE `admins` MODIFY `id` INT NOT NULL AUTO_INCREMENT;

-- Table: admin_notifications
SET @new_id := 0;
UPDATE `admin_notifications` SET `notification_id` = (@new_id := @new_id + 1) WHERE `notification_id` = 0;
ALTER TABLE `admin_notifications` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `admin_notifications` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `admin_notifications` t1 INNER JOIN `admin_notifications` t2 WHERE t1.`notification_id` = t2.`notification_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `admin_notifications` DROP COLUMN `temp_id`;
ALTER TABLE `admin_notifications` ADD PRIMARY KEY (`notification_id`);
ALTER TABLE `admin_notifications` MODIFY `notification_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- Table: admin_recovery_codes
SET @new_id := 0;
UPDATE `admin_recovery_codes` SET `id` = (@new_id := @new_id + 1) WHERE `id` = 0;
ALTER TABLE `admin_recovery_codes` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `admin_recovery_codes` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `admin_recovery_codes` t1 INNER JOIN `admin_recovery_codes` t2 WHERE t1.`id` = t2.`id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `admin_recovery_codes` DROP COLUMN `temp_id`;
ALTER TABLE `admin_recovery_codes` ADD PRIMARY KEY (`id`);
ALTER TABLE `admin_recovery_codes` MODIFY `id` INT NOT NULL AUTO_INCREMENT;

-- Table: admin_reports
SET @new_id := 0;
UPDATE `admin_reports` SET `report_id` = (@new_id := @new_id + 1) WHERE `report_id` = 0;
ALTER TABLE `admin_reports` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `admin_reports` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `admin_reports` t1 INNER JOIN `admin_reports` t2 WHERE t1.`report_id` = t2.`report_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `admin_reports` DROP COLUMN `temp_id`;
ALTER TABLE `admin_reports` ADD PRIMARY KEY (`report_id`);
ALTER TABLE `admin_reports` MODIFY `report_id` INT NOT NULL AUTO_INCREMENT;

-- Table: applicants
SET @new_id := 0;
UPDATE `applicants` SET `id` = (@new_id := @new_id + 1) WHERE `id` = 0;
ALTER TABLE `applicants` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `applicants` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `applicants` t1 INNER JOIN `applicants` t2 WHERE t1.`id` = t2.`id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `applicants` DROP COLUMN `temp_id`;
ALTER TABLE `applicants` ADD PRIMARY KEY (`id`);
ALTER TABLE `applicants` MODIFY `id` INT NOT NULL AUTO_INCREMENT;

-- Table: applicant_accounts
SET @new_id := 0;
UPDATE `applicant_accounts` SET `user_id` = (@new_id := @new_id + 1) WHERE `user_id` = 0;
ALTER TABLE `applicant_accounts` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `applicant_accounts` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `applicant_accounts` t1 INNER JOIN `applicant_accounts` t2 WHERE t1.`user_id` = t2.`user_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `applicant_accounts` DROP COLUMN `temp_id`;
ALTER TABLE `applicant_accounts` ADD PRIMARY KEY (`user_id`);
ALTER TABLE `applicant_accounts` MODIFY `user_id` INT NOT NULL AUTO_INCREMENT;

-- Table: applicant_notifications
SET @new_id := 0;
UPDATE `applicant_notifications` SET `notification_id` = (@new_id := @new_id + 1) WHERE `notification_id` = 0;
ALTER TABLE `applicant_notifications` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `applicant_notifications` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `applicant_notifications` t1 INNER JOIN `applicant_notifications` t2 WHERE t1.`notification_id` = t2.`notification_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `applicant_notifications` DROP COLUMN `temp_id`;
ALTER TABLE `applicant_notifications` ADD PRIMARY KEY (`notification_id`);
ALTER TABLE `applicant_notifications` MODIFY `notification_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- Table: applications
SET @new_id := 0;
UPDATE `applications` SET `application_id` = (@new_id := @new_id + 1) WHERE `application_id` = 0;
ALTER TABLE `applications` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `applications` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `applications` t1 INNER JOIN `applications` t2 WHERE t1.`application_id` = t2.`application_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `applications` DROP COLUMN `temp_id`;
ALTER TABLE `applications` ADD PRIMARY KEY (`application_id`);
ALTER TABLE `applications` MODIFY `application_id` INT NOT NULL AUTO_INCREMENT;

-- Table: application_progress
SET @new_id := 0;
UPDATE `application_progress` SET `progress_id` = (@new_id := @new_id + 1) WHERE `progress_id` = 0;
ALTER TABLE `application_progress` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `application_progress` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `application_progress` t1 INNER JOIN `application_progress` t2 WHERE t1.`progress_id` = t2.`progress_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `application_progress` DROP COLUMN `temp_id`;
ALTER TABLE `application_progress` ADD PRIMARY KEY (`progress_id`);
ALTER TABLE `application_progress` MODIFY `progress_id` INT NOT NULL AUTO_INCREMENT;

-- Table: application_status
SET @new_id := 0;
UPDATE `application_status` SET `status_id` = (@new_id := @new_id + 1) WHERE `status_id` = 0;
ALTER TABLE `application_status` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `application_status` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `application_status` t1 INNER JOIN `application_status` t2 WHERE t1.`status_id` = t2.`status_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `application_status` DROP COLUMN `temp_id`;
ALTER TABLE `application_status` ADD PRIMARY KEY (`status_id`);
ALTER TABLE `application_status` MODIFY `status_id` INT NOT NULL AUTO_INCREMENT;

-- Table: audit_logs
SET @new_id := 0;
UPDATE `audit_logs` SET `log_id` = (@new_id := @new_id + 1) WHERE `log_id` = 0;
ALTER TABLE `audit_logs` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `audit_logs` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `audit_logs` t1 INNER JOIN `audit_logs` t2 WHERE t1.`log_id` = t2.`log_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `audit_logs` DROP COLUMN `temp_id`;
ALTER TABLE `audit_logs` ADD PRIMARY KEY (`log_id`);
ALTER TABLE `audit_logs` MODIFY `log_id` INT NOT NULL AUTO_INCREMENT;

-- Table: chapter_submissions
SET @new_id := 0;
UPDATE `chapter_submissions` SET `id` = (@new_id := @new_id + 1) WHERE `id` = 0;
ALTER TABLE `chapter_submissions` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `chapter_submissions` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `chapter_submissions` t1 INNER JOIN `chapter_submissions` t2 WHERE t1.`id` = t2.`id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `chapter_submissions` DROP COLUMN `temp_id`;
ALTER TABLE `chapter_submissions` ADD PRIMARY KEY (`id`);
ALTER TABLE `chapter_submissions` MODIFY `id` INT NOT NULL AUTO_INCREMENT;

-- Table: courses
SET @new_id := 0;
UPDATE `courses` SET `course_id` = (@new_id := @new_id + 1) WHERE `course_id` = 0;
ALTER TABLE `courses` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `courses` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `courses` t1 INNER JOIN `courses` t2 WHERE t1.`course_id` = t2.`course_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `courses` DROP COLUMN `temp_id`;
ALTER TABLE `courses` ADD PRIMARY KEY (`course_id`);
ALTER TABLE `courses` MODIFY `course_id` INT NOT NULL AUTO_INCREMENT;

-- Table: degree_types
SET @new_id := 0;
UPDATE `degree_types` SET `degree_id` = (@new_id := @new_id + 1) WHERE `degree_id` = 0;
ALTER TABLE `degree_types` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `degree_types` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `degree_types` t1 INNER JOIN `degree_types` t2 WHERE t1.`degree_id` = t2.`degree_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `degree_types` DROP COLUMN `temp_id`;
ALTER TABLE `degree_types` ADD PRIMARY KEY (`degree_id`);
ALTER TABLE `degree_types` MODIFY `degree_id` INT NOT NULL AUTO_INCREMENT;

-- Table: departments
SET @new_id := 0;
UPDATE `departments` SET `dept_id` = (@new_id := @new_id + 1) WHERE `dept_id` = 0;
ALTER TABLE `departments` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `departments` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `departments` t1 INNER JOIN `departments` t2 WHERE t1.`dept_id` = t2.`dept_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `departments` DROP COLUMN `temp_id`;
ALTER TABLE `departments` ADD PRIMARY KEY (`dept_id`);
ALTER TABLE `departments` MODIFY `dept_id` INT NOT NULL AUTO_INCREMENT;

-- Table: dept_applications
SET @new_id := 0;
UPDATE `dept_applications` SET `app_code` = (@new_id := @new_id + 1) WHERE `app_code` = 0;
ALTER TABLE `dept_applications` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `dept_applications` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `dept_applications` t1 INNER JOIN `dept_applications` t2 WHERE t1.`app_code` = t2.`app_code` AND t1.temp_id > t2.temp_id;
ALTER TABLE `dept_applications` DROP COLUMN `temp_id`;
ALTER TABLE `dept_applications` ADD PRIMARY KEY (`app_code`);
ALTER TABLE `dept_applications` MODIFY `app_code` INT NOT NULL AUTO_INCREMENT;

-- Table: documents
SET @new_id := 0;
UPDATE `documents` SET `doc_id` = (@new_id := @new_id + 1) WHERE `doc_id` = 0;
ALTER TABLE `documents` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `documents` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `documents` t1 INNER JOIN `documents` t2 WHERE t1.`doc_id` = t2.`doc_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `documents` DROP COLUMN `temp_id`;
ALTER TABLE `documents` ADD PRIMARY KEY (`doc_id`);
ALTER TABLE `documents` MODIFY `doc_id` INT NOT NULL AUTO_INCREMENT;

-- Table: document_verification
SET @new_id := 0;
UPDATE `document_verification` SET `verification_id` = (@new_id := @new_id + 1) WHERE `verification_id` = 0;
ALTER TABLE `document_verification` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `document_verification` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `document_verification` t1 INNER JOIN `document_verification` t2 WHERE t1.`verification_id` = t2.`verification_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `document_verification` DROP COLUMN `temp_id`;
ALTER TABLE `document_verification` ADD PRIMARY KEY (`verification_id`);
ALTER TABLE `document_verification` MODIFY `verification_id` INT NOT NULL AUTO_INCREMENT;

-- Table: faculties
SET @new_id := 0;
UPDATE `faculties` SET `faculty_id` = (@new_id := @new_id + 1) WHERE `faculty_id` = 0;
ALTER TABLE `faculties` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `faculties` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `faculties` t1 INNER JOIN `faculties` t2 WHERE t1.`faculty_id` = t2.`faculty_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `faculties` DROP COLUMN `temp_id`;
ALTER TABLE `faculties` ADD PRIMARY KEY (`faculty_id`);
ALTER TABLE `faculties` MODIFY `faculty_id` INT NOT NULL AUTO_INCREMENT;

-- Table: higher_education
SET @new_id := 0;
UPDATE `higher_education` SET `id` = (@new_id := @new_id + 1) WHERE `id` = 0;
ALTER TABLE `higher_education` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `higher_education` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `higher_education` t1 INNER JOIN `higher_education` t2 WHERE t1.`id` = t2.`id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `higher_education` DROP COLUMN `temp_id`;
ALTER TABLE `higher_education` ADD PRIMARY KEY (`id`);
ALTER TABLE `higher_education` MODIFY `id` INT NOT NULL AUTO_INCREMENT;

-- Table: login_attempts
SET @new_id := 0;
UPDATE `login_attempts` SET `id` = (@new_id := @new_id + 1) WHERE `id` = 0;
ALTER TABLE `login_attempts` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `login_attempts` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `login_attempts` t1 INNER JOIN `login_attempts` t2 WHERE t1.`id` = t2.`id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `login_attempts` DROP COLUMN `temp_id`;
ALTER TABLE `login_attempts` ADD PRIMARY KEY (`id`);
ALTER TABLE `login_attempts` MODIFY `id` INT NOT NULL AUTO_INCREMENT;

-- Table: nysc_details
SET @new_id := 0;
UPDATE `nysc_details` SET `id` = (@new_id := @new_id + 1) WHERE `id` = 0;
ALTER TABLE `nysc_details` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `nysc_details` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `nysc_details` t1 INNER JOIN `nysc_details` t2 WHERE t1.`id` = t2.`id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `nysc_details` DROP COLUMN `temp_id`;
ALTER TABLE `nysc_details` ADD PRIMARY KEY (`id`);
ALTER TABLE `nysc_details` MODIFY `id` INT NOT NULL AUTO_INCREMENT;

-- Table: olevel_exams
SET @new_id := 0;
UPDATE `olevel_exams` SET `id` = (@new_id := @new_id + 1) WHERE `id` = 0;
ALTER TABLE `olevel_exams` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `olevel_exams` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `olevel_exams` t1 INNER JOIN `olevel_exams` t2 WHERE t1.`id` = t2.`id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `olevel_exams` DROP COLUMN `temp_id`;
ALTER TABLE `olevel_exams` ADD PRIMARY KEY (`id`);
ALTER TABLE `olevel_exams` MODIFY `id` INT NOT NULL AUTO_INCREMENT;

-- Table: olevel_results
SET @new_id := 0;
UPDATE `olevel_results` SET `id` = (@new_id := @new_id + 1) WHERE `id` = 0;
ALTER TABLE `olevel_results` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `olevel_results` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `olevel_results` t1 INNER JOIN `olevel_results` t2 WHERE t1.`id` = t2.`id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `olevel_results` DROP COLUMN `temp_id`;
ALTER TABLE `olevel_results` ADD PRIMARY KEY (`id`);
ALTER TABLE `olevel_results` MODIFY `id` INT NOT NULL AUTO_INCREMENT;

-- Table: olevel_sittings
SET @new_id := 0;
UPDATE `olevel_sittings` SET `sitting_id` = (@new_id := @new_id + 1) WHERE `sitting_id` = 0;
ALTER TABLE `olevel_sittings` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `olevel_sittings` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `olevel_sittings` t1 INNER JOIN `olevel_sittings` t2 WHERE t1.`sitting_id` = t2.`sitting_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `olevel_sittings` DROP COLUMN `temp_id`;
ALTER TABLE `olevel_sittings` ADD PRIMARY KEY (`sitting_id`);
ALTER TABLE `olevel_sittings` MODIFY `sitting_id` INT NOT NULL AUTO_INCREMENT;

-- Table: password_resets
SET @new_id := 0;
UPDATE `password_resets` SET `id` = (@new_id := @new_id + 1) WHERE `id` = 0;
ALTER TABLE `password_resets` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `password_resets` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `password_resets` t1 INNER JOIN `password_resets` t2 WHERE t1.`id` = t2.`id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `password_resets` DROP COLUMN `temp_id`;
ALTER TABLE `password_resets` ADD PRIMARY KEY (`id`);
ALTER TABLE `password_resets` MODIFY `id` INT NOT NULL AUTO_INCREMENT;

-- Table: personal_details
SET @new_id := 0;
UPDATE `personal_details` SET `id` = (@new_id := @new_id + 1) WHERE `id` = 0;
ALTER TABLE `personal_details` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `personal_details` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `personal_details` t1 INNER JOIN `personal_details` t2 WHERE t1.`id` = t2.`id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `personal_details` DROP COLUMN `temp_id`;
ALTER TABLE `personal_details` ADD PRIMARY KEY (`id`);
ALTER TABLE `personal_details` MODIFY `id` INT NOT NULL AUTO_INCREMENT;

-- Table: programme_capacities
SET @new_id := 0;
UPDATE `programme_capacities` SET `capacity_id` = (@new_id := @new_id + 1) WHERE `capacity_id` = 0;
ALTER TABLE `programme_capacities` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `programme_capacities` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `programme_capacities` t1 INNER JOIN `programme_capacities` t2 WHERE t1.`capacity_id` = t2.`capacity_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `programme_capacities` DROP COLUMN `temp_id`;
ALTER TABLE `programme_capacities` ADD PRIMARY KEY (`capacity_id`);
ALTER TABLE `programme_capacities` MODIFY `capacity_id` INT NOT NULL AUTO_INCREMENT;

-- Table: programme_choices
SET @new_id := 0;
UPDATE `programme_choices` SET `id` = (@new_id := @new_id + 1) WHERE `id` = 0;
ALTER TABLE `programme_choices` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `programme_choices` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `programme_choices` t1 INNER JOIN `programme_choices` t2 WHERE t1.`id` = t2.`id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `programme_choices` DROP COLUMN `temp_id`;
ALTER TABLE `programme_choices` ADD PRIMARY KEY (`id`);
ALTER TABLE `programme_choices` MODIFY `id` INT NOT NULL AUTO_INCREMENT;

-- Table: referees
SET @new_id := 0;
UPDATE `referees` SET `referee_id` = (@new_id := @new_id + 1) WHERE `referee_id` = 0;
ALTER TABLE `referees` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `referees` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `referees` t1 INNER JOIN `referees` t2 WHERE t1.`referee_id` = t2.`referee_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `referees` DROP COLUMN `temp_id`;
ALTER TABLE `referees` ADD PRIMARY KEY (`referee_id`);
ALTER TABLE `referees` MODIFY `referee_id` INT NOT NULL AUTO_INCREMENT;

-- Table: referee_requests
SET @new_id := 0;
UPDATE `referee_requests` SET `request_id` = (@new_id := @new_id + 1) WHERE `request_id` = 0;
ALTER TABLE `referee_requests` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `referee_requests` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `referee_requests` t1 INNER JOIN `referee_requests` t2 WHERE t1.`request_id` = t2.`request_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `referee_requests` DROP COLUMN `temp_id`;
ALTER TABLE `referee_requests` ADD PRIMARY KEY (`request_id`);
ALTER TABLE `referee_requests` MODIFY `request_id` INT NOT NULL AUTO_INCREMENT;

-- Table: referee_status
SET @new_id := 0;
UPDATE `referee_status` SET `referee_id` = (@new_id := @new_id + 1) WHERE `referee_id` = 0;
ALTER TABLE `referee_status` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `referee_status` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `referee_status` t1 INNER JOIN `referee_status` t2 WHERE t1.`referee_id` = t2.`referee_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `referee_status` DROP COLUMN `temp_id`;
ALTER TABLE `referee_status` ADD PRIMARY KEY (`referee_id`);
ALTER TABLE `referee_status` MODIFY `referee_id` INT NOT NULL AUTO_INCREMENT;

-- Table: referee_uploads
SET @new_id := 0;
UPDATE `referee_uploads` SET `upload_id` = (@new_id := @new_id + 1) WHERE `upload_id` = 0;
ALTER TABLE `referee_uploads` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `referee_uploads` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `referee_uploads` t1 INNER JOIN `referee_uploads` t2 WHERE t1.`upload_id` = t2.`upload_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `referee_uploads` DROP COLUMN `temp_id`;
ALTER TABLE `referee_uploads` ADD PRIMARY KEY (`upload_id`);
ALTER TABLE `referee_uploads` MODIFY `upload_id` INT NOT NULL AUTO_INCREMENT;

-- Table: research_details
SET @new_id := 0;
UPDATE `research_details` SET `id` = (@new_id := @new_id + 1) WHERE `id` = 0;
ALTER TABLE `research_details` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `research_details` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `research_details` t1 INNER JOIN `research_details` t2 WHERE t1.`id` = t2.`id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `research_details` DROP COLUMN `temp_id`;
ALTER TABLE `research_details` ADD PRIMARY KEY (`id`);
ALTER TABLE `research_details` MODIFY `id` INT NOT NULL AUTO_INCREMENT;

-- Table: reviewer_assignments
SET @new_id := 0;
UPDATE `reviewer_assignments` SET `assignment_id` = (@new_id := @new_id + 1) WHERE `assignment_id` = 0;
ALTER TABLE `reviewer_assignments` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `reviewer_assignments` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `reviewer_assignments` t1 INNER JOIN `reviewer_assignments` t2 WHERE t1.`assignment_id` = t2.`assignment_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `reviewer_assignments` DROP COLUMN `temp_id`;
ALTER TABLE `reviewer_assignments` ADD PRIMARY KEY (`assignment_id`);
ALTER TABLE `reviewer_assignments` MODIFY `assignment_id` INT NOT NULL AUTO_INCREMENT;

-- Table: reviewer_feedback
SET @new_id := 0;
UPDATE `reviewer_feedback` SET `feedback_id` = (@new_id := @new_id + 1) WHERE `feedback_id` = 0;
ALTER TABLE `reviewer_feedback` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `reviewer_feedback` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `reviewer_feedback` t1 INNER JOIN `reviewer_feedback` t2 WHERE t1.`feedback_id` = t2.`feedback_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `reviewer_feedback` DROP COLUMN `temp_id`;
ALTER TABLE `reviewer_feedback` ADD PRIMARY KEY (`feedback_id`);
ALTER TABLE `reviewer_feedback` MODIFY `feedback_id` INT NOT NULL AUTO_INCREMENT;

-- Table: reviewer_history
SET @new_id := 0;
UPDATE `reviewer_history` SET `history_id` = (@new_id := @new_id + 1) WHERE `history_id` = 0;
ALTER TABLE `reviewer_history` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `reviewer_history` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `reviewer_history` t1 INNER JOIN `reviewer_history` t2 WHERE t1.`history_id` = t2.`history_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `reviewer_history` DROP COLUMN `temp_id`;
ALTER TABLE `reviewer_history` ADD PRIMARY KEY (`history_id`);
ALTER TABLE `reviewer_history` MODIFY `history_id` INT NOT NULL AUTO_INCREMENT;

-- Table: roles
SET @new_id := 0;
UPDATE `roles` SET `role_id` = (@new_id := @new_id + 1) WHERE `role_id` = 0;
ALTER TABLE `roles` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `roles` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `roles` t1 INNER JOIN `roles` t2 WHERE t1.`role_id` = t2.`role_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `roles` DROP COLUMN `temp_id`;
ALTER TABLE `roles` ADD PRIMARY KEY (`role_id`);
ALTER TABLE `roles` MODIFY `role_id` INT NOT NULL AUTO_INCREMENT;

-- Table: student_messages
SET @new_id := 0;
UPDATE `student_messages` SET `message_id` = (@new_id := @new_id + 1) WHERE `message_id` = 0;
ALTER TABLE `student_messages` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `student_messages` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `student_messages` t1 INNER JOIN `student_messages` t2 WHERE t1.`message_id` = t2.`message_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `student_messages` DROP COLUMN `temp_id`;
ALTER TABLE `student_messages` ADD PRIMARY KEY (`message_id`);
ALTER TABLE `student_messages` MODIFY `message_id` INT NOT NULL AUTO_INCREMENT;

-- Table: student_notifications
SET @new_id := 0;
UPDATE `student_notifications` SET `id` = (@new_id := @new_id + 1) WHERE `id` = 0;
ALTER TABLE `student_notifications` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `student_notifications` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `student_notifications` t1 INNER JOIN `student_notifications` t2 WHERE t1.`id` = t2.`id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `student_notifications` DROP COLUMN `temp_id`;
ALTER TABLE `student_notifications` ADD PRIMARY KEY (`id`);
ALTER TABLE `student_notifications` MODIFY `id` INT NOT NULL AUTO_INCREMENT;

-- Table: student_profiles
SET @new_id := 0;
UPDATE `student_profiles` SET `student_id` = (@new_id := @new_id + 1) WHERE `student_id` = 0;
ALTER TABLE `student_profiles` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `student_profiles` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `student_profiles` t1 INNER JOIN `student_profiles` t2 WHERE t1.`student_id` = t2.`student_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `student_profiles` DROP COLUMN `temp_id`;
ALTER TABLE `student_profiles` ADD PRIMARY KEY (`student_id`);
ALTER TABLE `student_profiles` MODIFY `student_id` INT NOT NULL AUTO_INCREMENT;

-- Table: student_tracking_updates
SET @new_id := 0;
UPDATE `student_tracking_updates` SET `id` = (@new_id := @new_id + 1) WHERE `id` = 0;
ALTER TABLE `student_tracking_updates` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `student_tracking_updates` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `student_tracking_updates` t1 INNER JOIN `student_tracking_updates` t2 WHERE t1.`id` = t2.`id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `student_tracking_updates` DROP COLUMN `temp_id`;
ALTER TABLE `student_tracking_updates` ADD PRIMARY KEY (`id`);
ALTER TABLE `student_tracking_updates` MODIFY `id` INT NOT NULL AUTO_INCREMENT;

-- Table: study_modes
SET @new_id := 0;
UPDATE `study_modes` SET `mode_id` = (@new_id := @new_id + 1) WHERE `mode_id` = 0;
ALTER TABLE `study_modes` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `study_modes` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `study_modes` t1 INNER JOIN `study_modes` t2 WHERE t1.`mode_id` = t2.`mode_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `study_modes` DROP COLUMN `temp_id`;
ALTER TABLE `study_modes` ADD PRIMARY KEY (`mode_id`);
ALTER TABLE `study_modes` MODIFY `mode_id` INT NOT NULL AUTO_INCREMENT;

-- Table: supervisor_messages
SET @new_id := 0;
UPDATE `supervisor_messages` SET `message_id` = (@new_id := @new_id + 1) WHERE `message_id` = 0;
ALTER TABLE `supervisor_messages` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `supervisor_messages` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `supervisor_messages` t1 INNER JOIN `supervisor_messages` t2 WHERE t1.`message_id` = t2.`message_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `supervisor_messages` DROP COLUMN `temp_id`;
ALTER TABLE `supervisor_messages` ADD PRIMARY KEY (`message_id`);
ALTER TABLE `supervisor_messages` MODIFY `message_id` INT NOT NULL AUTO_INCREMENT;

-- Table: supervisor_milestones
SET @new_id := 0;
UPDATE `supervisor_milestones` SET `milestone_id` = (@new_id := @new_id + 1) WHERE `milestone_id` = 0;
ALTER TABLE `supervisor_milestones` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `supervisor_milestones` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `supervisor_milestones` t1 INNER JOIN `supervisor_milestones` t2 WHERE t1.`milestone_id` = t2.`milestone_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `supervisor_milestones` DROP COLUMN `temp_id`;
ALTER TABLE `supervisor_milestones` ADD PRIMARY KEY (`milestone_id`);
ALTER TABLE `supervisor_milestones` MODIFY `milestone_id` INT NOT NULL AUTO_INCREMENT;

-- Table: supervisor_notifications
SET @new_id := 0;
UPDATE `supervisor_notifications` SET `notification_id` = (@new_id := @new_id + 1) WHERE `notification_id` = 0;
ALTER TABLE `supervisor_notifications` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `supervisor_notifications` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `supervisor_notifications` t1 INNER JOIN `supervisor_notifications` t2 WHERE t1.`notification_id` = t2.`notification_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `supervisor_notifications` DROP COLUMN `temp_id`;
ALTER TABLE `supervisor_notifications` ADD PRIMARY KEY (`notification_id`);
ALTER TABLE `supervisor_notifications` MODIFY `notification_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- Table: supervisor_profiles
SET @new_id := 0;
UPDATE `supervisor_profiles` SET `supervisor_id` = (@new_id := @new_id + 1) WHERE `supervisor_id` = 0;
ALTER TABLE `supervisor_profiles` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `supervisor_profiles` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `supervisor_profiles` t1 INNER JOIN `supervisor_profiles` t2 WHERE t1.`supervisor_id` = t2.`supervisor_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `supervisor_profiles` DROP COLUMN `temp_id`;
ALTER TABLE `supervisor_profiles` ADD PRIMARY KEY (`supervisor_id`);
ALTER TABLE `supervisor_profiles` MODIFY `supervisor_id` INT NOT NULL AUTO_INCREMENT;

-- Table: supervisor_students
SET @new_id := 0;
UPDATE `supervisor_students` SET `student_id` = (@new_id := @new_id + 1) WHERE `student_id` = 0;
ALTER TABLE `supervisor_students` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `supervisor_students` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `supervisor_students` t1 INNER JOIN `supervisor_students` t2 WHERE t1.`student_id` = t2.`student_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `supervisor_students` DROP COLUMN `temp_id`;
ALTER TABLE `supervisor_students` ADD PRIMARY KEY (`student_id`);
ALTER TABLE `supervisor_students` MODIFY `student_id` INT NOT NULL AUTO_INCREMENT;

-- Table: system_settings
SET @new_id := 0;
UPDATE `system_settings` SET `settings_id` = (@new_id := @new_id + 1) WHERE `settings_id` = 0;
ALTER TABLE `system_settings` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `system_settings` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `system_settings` t1 INNER JOIN `system_settings` t2 WHERE t1.`settings_id` = t2.`settings_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `system_settings` DROP COLUMN `temp_id`;
ALTER TABLE `system_settings` ADD PRIMARY KEY (`settings_id`);
ALTER TABLE `system_settings` MODIFY `settings_id` INT NOT NULL AUTO_INCREMENT;

-- Table: users
SET @new_id := 0;
UPDATE `users` SET `user_id` = (@new_id := @new_id + 1) WHERE `user_id` = 0;
ALTER TABLE `users` ADD `temp_id` INT;
SET @counter := 0;
UPDATE `users` SET `temp_id` = (@counter := @counter + 1);
DELETE t1 FROM `users` t1 INNER JOIN `users` t2 WHERE t1.`user_id` = t2.`user_id` AND t1.temp_id > t2.temp_id;
ALTER TABLE `users` DROP COLUMN `temp_id`;
ALTER TABLE `users` ADD PRIMARY KEY (`user_id`);
ALTER TABLE `users` MODIFY `user_id` INT NOT NULL AUTO_INCREMENT;

SET FOREIGN_KEY_CHECKS = 1;