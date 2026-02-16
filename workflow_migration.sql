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
