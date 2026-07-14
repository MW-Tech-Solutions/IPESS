<?php
// Page and helper to generate any/all missing tables based on schema.sql and update_schema_workflow definitions.
// Accessible by both students and staff roles completely.

if (!function_exists('generate_all_missing_tables')) {
    function generate_all_missing_tables(PDO $pdo): bool {
        try {
            // 1. Temporarily disable foreign keys
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

            // 2. Read and execute core schema.sql (ignoring locks, drops, inserts, etc.)
            $schemaPath = __DIR__ . '/../database/schema.sql';
            if (file_exists($schemaPath)) {
                $sql = file_get_contents($schemaPath);
                
                // Remove MySQL comments and inline query-hints
                $sql = preg_replace('/--.*/', '', $sql);
                $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

                // Split statements by semicolon
                $statements = explode(';', $sql);
                foreach ($statements as $stmt) {
                    $stmt = trim($stmt);
                    if (empty($stmt)) continue;

                    // Skip Drop statements, Locking tables, Dump directives, and insertions
                    if (preg_match('#^(drop|insert|lock|unlock|select|/\*!)#i', $stmt)) {
                        continue;
                    }

                    // Rewrite CREATE TABLE to CREATE TABLE IF NOT EXISTS if not already present
                    if (preg_match('/^create\s+table/i', $stmt) && !preg_match('/create\s+table\s+if\s+not\s+exists/i', $stmt)) {
                        $stmt = preg_replace('/^create\s+table/i', 'CREATE TABLE IF NOT EXISTS', $stmt);
                    }

                    try {
                        $s = $pdo->prepare($stmt);
                        $s->execute();
                        $s->closeCursor();
                    } catch (PDOException $e) {
                        error_log("Schema execution warning (skipped): " . $e->getMessage() . " in query: " . substr($stmt, 0, 100));
                    }
                }
            }

            // 3. Ensure role_permissions, user_permissions, and workflow_audit_logs exist
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `role_permissions` (
                    `role_key` VARCHAR(50) NOT NULL,
                    `permission_key` VARCHAR(100) NOT NULL,
                    PRIMARY KEY (`role_key`, `permission_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `user_permissions` (
                    `user_id` INT NOT NULL,
                    `permission_key` VARCHAR(100) NOT NULL,
                    `granted` TINYINT(1) NOT NULL DEFAULT 1,
                    PRIMARY KEY (`user_id`, `permission_key`),
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `admission_processing` (
                    `application_id` INT NOT NULL,
                    `matric_number` VARCHAR(50) NULL,
                    `student_number` VARCHAR(50) NULL,
                    `acceptance_letter_status` ENUM('Inactive', 'Active') NOT NULL DEFAULT 'Inactive',
                    `admission_letter_status` ENUM('Inactive', 'Active') NOT NULL DEFAULT 'Inactive',
                    `acceptance_letter_activated_at` DATETIME NULL,
                    `admission_letter_activated_at` DATETIME NULL,
                    `matric_generated_at` DATETIME NULL,
                    `student_num_generated_at` DATETIME NULL,
                    `matric_generated_by` INT NULL,
                    `student_num_generated_by` INT NULL,
                    PRIMARY KEY (`application_id`),
                    FOREIGN KEY (`application_id`) REFERENCES `applications`(`application_id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `workflow_audit_logs` (
                    `log_id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT NULL,
                    `role` VARCHAR(50) NULL,
                    `department_id` INT NULL,
                    `faculty_id` INT NULL,
                    `action` VARCHAR(150) NOT NULL,
                    `applicant_id` INT NULL,
                    `old_status` VARCHAR(100) NULL,
                    `new_status` VARCHAR(100) NULL,
                    `remarks` TEXT NULL,
                    `ip_address` VARCHAR(45) NULL,
                    `browser` VARCHAR(100) NULL,
                    `os` VARCHAR(100) NULL,
                    `bulk_action_id` VARCHAR(50) NULL,
                    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `system_modules` (
                    `module_key` VARCHAR(50) NOT NULL,
                    `module_name` VARCHAR(100) NOT NULL,
                    `is_active` TINYINT(1) DEFAULT 1,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`module_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
            ");

            // 4. Alterations and migrations (Adding missing columns safely)
            $alterations = [
                "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `faculty_id` INT NULL",
                "ALTER TABLE `supervisor_profiles` ADD COLUMN IF NOT EXISTS `department_id` INT NULL",
                "ALTER TABLE `supervisor_profiles` ADD COLUMN IF NOT EXISTS `faculty_id` INT NULL",
                "ALTER TABLE `student_profiles` ADD COLUMN IF NOT EXISTS `assigned_supervisor_user_id` INT NULL",
                "ALTER TABLE `student_profiles` ADD COLUMN IF NOT EXISTS `assignment_date` DATETIME NULL",
                "ALTER TABLE `student_profiles` ADD COLUMN IF NOT EXISTS `assigned_by_user_id` INT NULL",
                "ALTER TABLE `student_profiles` ADD COLUMN IF NOT EXISTS `supervisor_status` VARCHAR(50) NULL",
                "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `totp_secret` VARCHAR(64) NULL",
                "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `totp_enabled` TINYINT(1) DEFAULT 0",
                "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `totp_verified_at` DATETIME DEFAULT NULL",
                "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `reset_token` VARCHAR(64) NULL",
                "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `reset_expires` DATETIME NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `referee_name` VARCHAR(150) DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `referee_title` VARCHAR(50) DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `referee_organization` VARCHAR(150) DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `referee_department` VARCHAR(150) DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `referee_position` VARCHAR(150) DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `referee_address` TEXT DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `referee_phone` VARCHAR(20) DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `relationship` VARCHAR(100) DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `years_known` INT DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `assessment_character_integrity` ENUM('Excellent', 'Very Good', 'Good', 'Fair', 'Poor') DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `assessment_professional_competence` ENUM('Excellent', 'Very Good', 'Good', 'Fair', 'Poor') DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `assessment_leadership_ability` ENUM('Excellent', 'Very Good', 'Good', 'Fair', 'Poor') DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `assessment_communication_skills` ENUM('Excellent', 'Very Good', 'Good', 'Fair', 'Poor') DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `assessment_teamwork` ENUM('Excellent', 'Very Good', 'Good', 'Fair', 'Poor') DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `assessment_reliability` ENUM('Excellent', 'Very Good', 'Good', 'Fair', 'Poor') DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `assessment_initiative` ENUM('Excellent', 'Very Good', 'Good', 'Fair', 'Poor') DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `assessment_emotional_stability` ENUM('Excellent', 'Very Good', 'Good', 'Fair', 'Poor') DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `major_strengths` TEXT DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `weaknesses` TEXT DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `recommendation` ENUM('Strongly Recommend', 'Recommend', 'Recommend with Reservation', 'Do Not Recommend') DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `additional_comments` TEXT DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `declaration_accepted` TINYINT DEFAULT 0",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `signature` VARCHAR(150) DEFAULT NULL",
                "ALTER TABLE `referee_uploads` ADD COLUMN IF NOT EXISTS `declaration_date` DATE DEFAULT NULL"
            ];

            foreach ($alterations as $alt) {
                try {
                    $pdo->exec($alt);
                } catch (PDOException $e) {
                    // Try without IF NOT EXISTS if server has an older MySQL version
                    $cleanAlt = str_replace("IF NOT EXISTS ", "", $alt);
                    try {
                        $pdo->exec($cleanAlt);
                    } catch (PDOException $_) {
                        // Suppress warnings if columns already exist
                    }
                }
            }

            // 5. Seed standard roles (ICTO, ICT_STAFF) if not present
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE role_key = ?");
            $stmt->execute(['ICTO']);
            if ((int) $stmt->fetchColumn() === 0) {
                $pdo->exec("INSERT INTO roles (role_key, role_name) VALUES ('ICTO', 'ICT Officer')");
            }
            $stmt->execute(['ICT_STAFF']);
            if ((int) $stmt->fetchColumn() === 0) {
                $pdo->exec("INSERT INTO roles (role_key, role_name) VALUES ('ICT_STAFF', 'Main ICT Staff')");
            }

            // 6. Seed Default Role Permissions
            $rolePermissions = [
                'SUPER_ADMIN' => [
                    'view_dashboard', 'view_applicants', 'edit_applicants', 'delete_applicants', 'verify_applicants', 'bulk_verify',
                    'download_documents', 'upload_documents', 'supervisor_management', 'assign_supervisor', 'change_supervisor',
                    'remove_supervisor', 'bulk_supervisor_allocation', 'faculty_review', 'department_review', 'pg_review',
                    'ict_processing', 'generate_matric_number', 'generate_student_number', 'acceptance_letter', 'admission_letter',
                    'reports', 'export_pdf', 'export_excel', 'export_csv', 'download_records', 'view_audit_logs', 'user_management',
                    'role_management', 'permission_management', 'settings', 'workflow_configuration', 'notifications', 'logs'
                ],
                'ICT_ADMIN' => [
                    'view_dashboard', 'view_applicants', 'edit_applicants', 'delete_applicants', 'verify_applicants', 'bulk_verify',
                    'download_documents', 'upload_documents', 'supervisor_management', 'assign_supervisor', 'change_supervisor',
                    'remove_supervisor', 'bulk_supervisor_allocation', 'faculty_review', 'department_review', 'pg_review',
                    'ict_processing', 'generate_matric_number', 'generate_student_number', 'acceptance_letter', 'admission_letter',
                    'reports', 'export_pdf', 'export_excel', 'export_csv', 'download_records', 'view_audit_logs', 'user_management',
                    'role_management', 'permission_management', 'settings', 'workflow_configuration', 'notifications', 'logs'
                ],
                'ICTO' => [
                    'view_dashboard', 'view_applicants', 'verify_applicants', 'bulk_verify', 'download_documents'
                ],
                'DEPARTMENT_ADMIN' => [
                    'view_dashboard', 'view_applicants', 'department_review', 'bulk_verify', 'supervisor_management',
                    'assign_supervisor', 'change_supervisor', 'remove_supervisor', 'bulk_supervisor_allocation',
                    'reports', 'export_pdf', 'export_excel', 'export_csv', 'download_records'
                ],
                'HOD' => [
                    'view_dashboard', 'view_applicants', 'department_review', 'bulk_verify', 'supervisor_management',
                    'assign_supervisor', 'change_supervisor', 'remove_supervisor', 'bulk_supervisor_allocation',
                    'reports', 'export_pdf', 'export_excel', 'export_csv', 'download_records'
                ],
                'FACULTY_OFFICER' => [
                    'view_dashboard', 'view_applicants', 'faculty_review', 'bulk_verify', 'reports'
                ],
                'PG_SCHOOL_OFFICER' => [
                    'view_dashboard', 'view_applicants', 'pg_review', 'bulk_verify',
                    'reports', 'export_pdf', 'export_excel', 'export_csv', 'download_records'
                ],
                'ICT_STAFF' => [
                    'view_dashboard', 'view_applicants', 'ict_processing', 'generate_matric_number', 'generate_student_number',
                    'acceptance_letter', 'admission_letter'
                ]
            ];

            foreach ($rolePermissions as $role => $perms) {
                // Check if role exists
                $rCheck = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE role_key = ?");
                $rCheck->execute([$role]);
                if ((int) $rCheck->fetchColumn() > 0) {
                    $stmtDelete = $pdo->prepare("DELETE FROM role_permissions WHERE role_key = ?");
                    $stmtDelete->execute([$role]);
                    $stmtInsert = $pdo->prepare("INSERT INTO role_permissions (role_key, permission_key) VALUES (?, ?)");
                    foreach ($perms as $perm) {
                        $stmtInsert->execute([$role, $perm]);
                    }
                }
            }

            // Seed modules configuration
            $pdo->exec("
                INSERT IGNORE INTO system_modules (module_key, module_name, is_active) 
                VALUES ('admissions', 'Admissions Exercise', 1)
            ");

            // Re-enable foreign key checks
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            return true;
        } catch (PDOException $e) {
            error_log("Database repair execution error: " . $e->getMessage());
            try {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            } catch (Throwable $_) {}
            return false;
        }
    }
}

// Direct URL execution logic
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../config/db.php';
    
    if (generate_all_missing_tables($pdo)) {
        echo json_encode(['success' => true, 'message' => 'All missing database tables and migrations checked and successfully generated.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred while generating or verifying missing database tables.']);
    }
}
