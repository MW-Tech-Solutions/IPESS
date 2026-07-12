<?php
require_once __DIR__ . '/includes/db.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Create role_permissions
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `role_permissions` (
            `role_key` VARCHAR(50) NOT NULL,
            `permission_key` VARCHAR(100) NOT NULL,
            PRIMARY KEY (`role_key`, `permission_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "Table 'role_permissions' created or exists.<br>";

    // 2. Create user_permissions
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `user_permissions` (
            `user_id` INT NOT NULL,
            `permission_key` VARCHAR(100) NOT NULL,
            `granted` TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (`user_id`, `permission_key`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "Table 'user_permissions' created or exists.<br>";

    // 3. Create admission_processing
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
    echo "Table 'admission_processing' created or exists.<br>";

    // 4. Create workflow_audit_logs
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
    echo "Table 'workflow_audit_logs' created or exists.<br>";

    // 5. Alter supervisor_profiles
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `faculty_id` INT NULL");
        echo "Added column 'faculty_id' to 'users'.<br>";
    } catch (PDOException $e) {
        echo "Column 'faculty_id' already exists in 'users' or error: " . $e->getMessage() . "<br>";
    }

    try {
        $pdo->exec("ALTER TABLE `supervisor_profiles` ADD COLUMN `department_id` INT NULL");
        echo "Added column 'department_id' to 'supervisor_profiles'.<br>";
    } catch (PDOException $e) {
        echo "Column 'department_id' already exists in 'supervisor_profiles' or error: " . $e->getMessage() . "<br>";
    }
    try {
        $pdo->exec("ALTER TABLE `supervisor_profiles` ADD COLUMN `faculty_id` INT NULL");
        echo "Added column 'faculty_id' to 'supervisor_profiles'.<br>";
    } catch (PDOException $e) {
        echo "Column 'faculty_id' already exists in 'supervisor_profiles' or error: " . $e->getMessage() . "<br>";
    }

    // 6. Alter student_profiles
    try {
        $pdo->exec("ALTER TABLE `student_profiles` ADD COLUMN `assigned_supervisor_user_id` INT NULL");
        echo "Added column 'assigned_supervisor_user_id' to 'student_profiles'.<br>";
    } catch (PDOException $e) {
        echo "Column 'assigned_supervisor_user_id' already exists in 'student_profiles' or error: " . $e->getMessage() . "<br>";
    }
    try {
        $pdo->exec("ALTER TABLE `student_profiles` ADD COLUMN `assignment_date` DATETIME NULL");
        echo "Added column 'assignment_date' to 'student_profiles'.<br>";
    } catch (PDOException $e) {
        echo "Column 'assignment_date' already exists in 'student_profiles' or error: " . $e->getMessage() . "<br>";
    }
    try {
        $pdo->exec("ALTER TABLE `student_profiles` ADD COLUMN `assigned_by_user_id` INT NULL");
        echo "Added column 'assigned_by_user_id' to 'student_profiles'.<br>";
    } catch (PDOException $e) {
        echo "Column 'assigned_by_user_id' already exists in 'student_profiles' or error: " . $e->getMessage() . "<br>";
    }
    try {
        $pdo->exec("ALTER TABLE `student_profiles` ADD COLUMN `supervisor_status` VARCHAR(50) NULL");
        echo "Added column 'supervisor_status' to 'student_profiles'.<br>";
    } catch (PDOException $e) {
        echo "Column 'supervisor_status' already exists in 'student_profiles' or error: " . $e->getMessage() . "<br>";
    }

    // 7. Seed standard roles (ICTO, ICT_STAFF) if not present
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE role_key = ?");
    $stmt->execute(['ICTO']);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO roles (role_key, role_name) VALUES ('ICTO', 'ICT Officer')");
        echo "Seeded role 'ICTO'.<br>";
    }
    $stmt->execute(['ICT_STAFF']);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO roles (role_key, role_name) VALUES ('ICT_STAFF', 'Main ICT Staff')");
        echo "Seeded role 'ICT_STAFF'.<br>";
    }

    // 8. Seed Default Role Permissions
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

    $pdo->beginTransaction();
    foreach ($rolePermissions as $role => $perms) {
        $stmtDelete = $pdo->prepare("DELETE FROM role_permissions WHERE role_key = ?");
        $stmtDelete->execute([$role]);
        $stmtInsert = $pdo->prepare("INSERT INTO role_permissions (role_key, permission_key) VALUES (?, ?)");
        foreach ($perms as $perm) {
            $stmtInsert->execute([$role, $perm]);
        }
    }
    $pdo->commit();
    echo "Seeded default role permissions successfully.<br>";

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("MIGRATION ERROR: " . $e->getMessage());
}
