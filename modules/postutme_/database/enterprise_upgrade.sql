USE postutme_jostum;

CREATE TABLE IF NOT EXISTS import_batches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admission_session_id BIGINT UNSIGNED NOT NULL,
  uploaded_by BIGINT UNSIGNED NULL,
  original_filename VARCHAR(255) NOT NULL,
  stored_path VARCHAR(255) NOT NULL,
  total_rows INT NOT NULL DEFAULT 0,
  successful_rows INT NOT NULL DEFAULT 0,
  duplicate_rows INT NOT NULL DEFAULT 0,
  failed_rows INT NOT NULL DEFAULT 0,
  error_report_json JSON NULL,
  status ENUM('previewed','imported','rolled_back') NOT NULL DEFAULT 'imported',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  rolled_back_at DATETIME NULL,
  INDEX idx_import_session (admission_session_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS applicant_profiles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  applicant_id BIGINT UNSIGNED NOT NULL UNIQUE,
  date_of_birth DATE NULL,
  marital_status VARCHAR(40) NULL,
  religion VARCHAR(80) NULL,
  nationality VARCHAR(80) NULL DEFAULT 'Nigerian',
  state_origin VARCHAR(100) NULL,
  lga VARCHAR(100) NULL,
  home_address TEXT NULL,
  contact_address TEXT NULL,
  guardian_name VARCHAR(160) NULL,
  guardian_phone VARCHAR(40) NULL,
  emergency_contact VARCHAR(160) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_profile_applicant FOREIGN KEY (applicant_id) REFERENCES applicants(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS olevel_results (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  applicant_id BIGINT UNSIGNED NOT NULL,
  sitting_no TINYINT UNSIGNED NOT NULL DEFAULT 1,
  exam_type VARCHAR(40) NOT NULL,
  exam_year VARCHAR(20) NOT NULL,
  exam_number VARCHAR(80) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_olevel_sitting (applicant_id, sitting_no),
  CONSTRAINT fk_olevel_applicant FOREIGN KEY (applicant_id) REFERENCES applicants(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS olevel_subjects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  olevel_result_id BIGINT UNSIGNED NOT NULL,
  subject VARCHAR(120) NOT NULL,
  grade VARCHAR(10) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_olevel_subject (olevel_result_id, subject),
  CONSTRAINT fk_subject_result FOREIGN KEY (olevel_result_id) REFERENCES olevel_results(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS uploaded_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  applicant_id BIGINT UNSIGNED NOT NULL,
  document_type VARCHAR(80) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_document_type (applicant_id, document_type),
  CONSTRAINT fk_doc_applicant FOREIGN KEY (applicant_id) REFERENCES applicants(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS invoices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  applicant_id BIGINT UNSIGNED NOT NULL UNIQUE,
  invoice_number VARCHAR(80) NOT NULL UNIQUE,
  amount DECIMAL(12,2) NOT NULL,
  provider ENUM('remita','paystack','manual') NOT NULL DEFAULT 'paystack',
  rrr_reference VARCHAR(120) NULL UNIQUE,
  status ENUM('pending','successful','failed') NOT NULL DEFAULT 'pending',
  gateway_response JSON NULL,
  paid_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_invoice_applicant FOREIGN KEY (applicant_id) REFERENCES applicants(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS programmes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  faculty VARCHAR(160) NULL,
  department VARCHAR(160) NULL,
  minimum_jamb_score INT NOT NULL DEFAULT 140,
  cutoff_score DECIMAL(5,2) NOT NULL DEFAULT 50.00,
  required_subjects_json JSON NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS screening_results (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  applicant_id BIGINT UNSIGNED NOT NULL UNIQUE,
  jamb_score INT NOT NULL DEFAULT 0,
  olevel_score DECIMAL(8,2) NOT NULL DEFAULT 0,
  aggregate_score DECIMAL(8,2) NOT NULL DEFAULT 0,
  qualification_status ENUM('not_started','qualified','not_qualified','recommended','not_recommended','admitted','not_admitted') NOT NULL DEFAULT 'not_started',
  remarks TEXT NULL,
  computed_at DATETIME NULL,
  computed_by BIGINT UNSIGNED NULL,
  CONSTRAINT fk_result_applicant FOREIGN KEY (applicant_id) REFERENCES applicants(id),
  CONSTRAINT fk_result_user FOREIGN KEY (computed_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS support_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  jamb_reg_no VARCHAR(40) NULL,
  name VARCHAR(160) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  message TEXT NOT NULL,
  status ENUM('open','in_review','resolved') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier VARCHAR(190) NOT NULL,
  ip_address VARCHAR(64) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_attempt_identifier (identifier, attempted_at),
  INDEX idx_attempt_ip (ip_address, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  channel VARCHAR(40) NOT NULL DEFAULT 'email',
  subject VARCHAR(190) NOT NULL,
  body TEXT NOT NULL,
  status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO programmes (code, name, faculty, department, minimum_jamb_score, cutoff_score, required_subjects_json)
VALUES
('AGR', 'B.Sc. Agriculture', 'Agriculture', 'Agriculture', 140, 50, '["English Language","Mathematics","Biology","Chemistry"]'),
('AGE', 'B.Eng. Agricultural Engineering', 'Engineering', 'Agricultural Engineering', 160, 55, '["English Language","Mathematics","Physics","Chemistry"]');

INSERT IGNORE INTO app_settings (setting_key, setting_value)
VALUES
('manual_review_enabled', '0'),
('allow_form_without_payment', '0'),
('allow_change_of_course', '0'),
('allow_edit_after_submission', '0'),
('upload_max_mb', '2'),
('allowed_file_types', 'jpg,jpeg,png,pdf'),
('profile_requires_payment', '0'),
('maintenance_mode', '0'),
('session_timeout_minutes', '30'),
('jamb_weight', '50'),
('olevel_weight', '50');
