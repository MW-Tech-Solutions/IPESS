CREATE DATABASE IF NOT EXISTS postutme_jostum CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE postutme_jostum;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(40) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('applicant','admissions_officer','ict_admin','super_admin','finance_officer') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admission_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  year_label VARCHAR(20) NOT NULL UNIQUE,
  application_fee DECIMAL(12,2) NOT NULL DEFAULT 2000.00,
  opens_at DATE NULL,
  closes_at DATE NULL,
  is_open TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  body TEXT NOT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS deadlines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(190) NOT NULL,
  deadline_date DATE NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS jamb_candidates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admission_session_id BIGINT UNSIGNED NOT NULL,
  jamb_reg_no VARCHAR(40) NOT NULL,
  surname VARCHAR(100) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  other_names VARCHAR(140) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  course_applied VARCHAR(190) NULL,
  utme_score INT NULL,
  state_origin VARCHAR(100) NULL,
  lga VARCHAR(100) NULL,
  raw_payload JSON NULL,
  verified_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_session_jamb (admission_session_id, jamb_reg_no),
  INDEX idx_jamb_reg_no (jamb_reg_no),
  CONSTRAINT fk_jamb_session FOREIGN KEY (admission_session_id) REFERENCES admission_sessions(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS applicants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL UNIQUE,
  jamb_candidate_id BIGINT UNSIGNED NOT NULL UNIQUE,
  jamb_reg_no VARCHAR(40) NOT NULL,
  surname VARCHAR(100) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  other_names VARCHAR(140) NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  gender VARCHAR(20) NULL,
  date_of_birth DATE NULL,
  contact_address TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_applicant_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_applicant_jamb FOREIGN KEY (jamb_candidate_id) REFERENCES jamb_candidates(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  applicant_id BIGINT UNSIGNED NOT NULL,
  provider ENUM('remita','paystack','manual') NOT NULL DEFAULT 'paystack',
  reference VARCHAR(120) NOT NULL UNIQUE,
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
  paid_at DATETIME NULL,
  verified_by BIGINT UNSIGNED NULL,
  metadata JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_applicant FOREIGN KEY (applicant_id) REFERENCES applicants(id),
  CONSTRAINT fk_payment_verifier FOREIGN KEY (verified_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS screening_applications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  applicant_id BIGINT UNSIGNED NOT NULL UNIQUE,
  olevel_type VARCHAR(80) NULL,
  olevel_exam_no VARCHAR(80) NULL,
  olevel_year VARCHAR(20) NULL,
  subjects_json JSON NULL,
  choice_course VARCHAR(190) NULL,
  alternative_course VARCHAR(190) NULL,
  passport_path VARCHAR(255) NULL,
  olevel_result_path VARCHAR(255) NULL,
  birth_certificate_path VARCHAR(255) NULL,
  status ENUM('draft','submitted','under_review','approved','rejected') NOT NULL DEFAULT 'draft',
  officer_comment TEXT NULL,
  submitted_at DATETIME NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_screening_applicant FOREIGN KEY (applicant_id) REFERENCES applicants(id),
  CONSTRAINT fk_screening_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS app_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(120) NOT NULL UNIQUE,
  setting_value TEXT NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(160) NOT NULL,
  subject_type VARCHAR(80) NOT NULL,
  subject_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_subject (subject_type, subject_id),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

INSERT IGNORE INTO admission_sessions (year_label, application_fee, opens_at, closes_at, is_open, is_active)
VALUES ('2025/2026', 2000.00, '2025-08-01', '2025-10-31', 1, 1);

INSERT IGNORE INTO users (name, email, phone, password_hash, role)
VALUES
('JOSTUM Super Admin', 'admin@jostum.edu.ng', '08000000000', '$2y$10$z5GGpkjI48m5Q2IVscCbQuftOJDDwBeZqEwv9fFAJLje7FgRVtpZ.', 'super_admin'),
('Admissions Officer', 'admissions@jostum.edu.ng', '08000000001', '$2y$10$z5GGpkjI48m5Q2IVscCbQuftOJDDwBeZqEwv9fFAJLje7FgRVtpZ.', 'admissions_officer'),
('Finance Officer', 'finance@jostum.edu.ng', '08000000002', '$2y$10$z5GGpkjI48m5Q2IVscCbQuftOJDDwBeZqEwv9fFAJLje7FgRVtpZ.', 'finance_officer'),
('ICT Admin', 'ict@jostum.edu.ng', '08000000003', '$2y$10$z5GGpkjI48m5Q2IVscCbQuftOJDDwBeZqEwv9fFAJLje7FgRVtpZ.', 'ict_admin');

INSERT IGNORE INTO app_settings (setting_key, setting_value)
VALUES
('payment_provider', 'paystack'),
('paystack_public_key', ''),
('paystack_secret_key', ''),
('remita_merchant_id', ''),
('remita_service_type_id', ''),
('remita_api_key', ''),
('support_email', 'admissions@jostum.edu.ng'),
('support_phone', '08000000000');

INSERT IGNORE INTO notices (title, body)
VALUES
('Screening portal is open', 'Eligible candidates must verify their JAMB registration number before creating a profile.'),
('Use valid contact details', 'Applicants should provide an active phone number and email address for screening updates.');

INSERT IGNORE INTO deadlines (label, deadline_date)
VALUES
('JAMB verification and profile creation', '2025-10-15'),
('Final submission of screening form', '2025-10-31');
