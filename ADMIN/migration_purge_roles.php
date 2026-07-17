<?php
require_once __DIR__ . '/includes/db.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Fetch old roles to map them correctly
    $oldRoles = $pdo->query("SELECT role_id, role_key, role_name FROM roles")->fetchAll(PDO::FETCH_ASSOC);
    $oldRoleMap = [];
    foreach ($oldRoles as $r) {
        $oldRoleMap[$r['role_id']] = strtoupper($r['role_key']);
    }

    // 2. Disable foreign key checks temporarily
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // 3. Clear roles table
    $pdo->exec("TRUNCATE TABLE roles;");

    // 4. Seed new hardcoded roles
    $newRoles = [
        ['role_key' => 'SUPER_ADMIN', 'role_name' => 'Super Admin'],
        ['role_key' => 'GENERAL', 'role_name' => 'General'],
        ['role_key' => 'STUDENT', 'role_name' => 'Student'],
        ['role_key' => 'ICTO', 'role_name' => 'ICT Officer'],
        ['role_key' => 'HOD', 'role_name' => 'HOD/Departmental Admin'],
        ['role_key' => 'COLLEGE_ADMIN', 'role_name' => 'College Admin'],
        ['role_key' => 'PG_ADMIN', 'role_name' => 'PG Admin'],
        ['role_key' => 'ICT_ADMIN', 'role_name' => 'ICT Admin'],
        ['role_key' => 'CENTER_LEADER', 'role_name' => 'Center Leader'],
        ['role_key' => 'SUPERVISOR', 'role_name' => 'Supervisor'],
    ];

    $stmtInsert = $pdo->prepare("INSERT INTO roles (role_key, role_name) VALUES (?, ?)");
    $newRoleIds = [];
    foreach ($newRoles as $role) {
        $stmtInsert->execute([$role['role_key'], $role['role_name']]);
        $newRoleIds[$role['role_key']] = (int) $pdo->lastInsertId();
    }

    // 5. Update users role IDs
    // Select all users and update based on old role key
    $users = $pdo->query("SELECT user_id, role_id FROM users")->fetchAll(PDO::FETCH_ASSOC);
    $stmtUpdateUser = $pdo->prepare("UPDATE users SET role_id = ? WHERE user_id = ?");

    foreach ($users as $u) {
        $userId = (int) $u['user_id'];
        $oldId = (int) $u['role_id'];
        $oldKey = $oldRoleMap[$oldId] ?? '';

        $newKey = 'GENERAL'; // Default fallback
        if ($oldKey === 'SUPER_ADMIN') {
            $newKey = 'SUPER_ADMIN';
        } elseif ($oldKey === 'ICT_ADMIN') {
            $newKey = 'ICT_ADMIN';
        } elseif ($oldKey === 'ICTO') {
            $newKey = 'ICTO';
        } elseif ($oldKey === 'DEPARTMENT_ADMIN' || $oldKey === 'HOD') {
            $newKey = 'HOD';
        } elseif ($oldKey === 'FACULTY_OFFICER' || $oldKey === 'COLLEGE_ADMIN') {
            $newKey = 'COLLEGE_ADMIN';
        } elseif ($oldKey === 'PG_SCHOOL_OFFICER' || $oldKey === 'PG_ADMIN') {
            $newKey = 'PG_ADMIN';
        } elseif ($oldKey === 'ICT_STAFF') {
            $newKey = 'ICT_ADMIN';
        } elseif ($oldKey === 'SUPERVISOR') {
            $newKey = 'SUPERVISOR';
        } elseif ($oldKey === 'STUDENT') {
            $newKey = 'STUDENT';
        }

        $newId = $newRoleIds[$newKey] ?? $newRoleIds['GENERAL'];
        $stmtUpdateUser->execute([$newId, $userId]);
    }

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // 6. Reset role permissions
    $pdo->exec("TRUNCATE TABLE role_permissions;");

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
        'HOD' => [
            'view_dashboard', 'view_applicants', 'department_review', 'bulk_verify', 'supervisor_management',
            'assign_supervisor', 'change_supervisor', 'remove_supervisor', 'bulk_supervisor_allocation',
            'reports', 'export_pdf', 'export_excel', 'export_csv', 'download_records'
        ],
        'COLLEGE_ADMIN' => [
            'view_dashboard', 'view_applicants', 'faculty_review', 'bulk_verify', 'reports'
        ],
        'PG_ADMIN' => [
            'view_dashboard', 'view_applicants', 'pg_review', 'bulk_verify',
            'reports', 'export_pdf', 'export_excel', 'export_csv', 'download_records'
        ],
        'SUPERVISOR' => [
            'view_dashboard', 'supervisor_management'
        ],
        'CENTER_LEADER' => [
            'view_dashboard', 'reports'
        ],
        'GENERAL' => [
            'view_dashboard'
        ]
    ];

    $stmtInsertPerm = $pdo->prepare("INSERT INTO role_permissions (role_key, permission_key) VALUES (?, ?)");
    foreach ($rolePermissions as $rk => $perms) {
        foreach ($perms as $pk) {
            $stmtInsertPerm->execute([$rk, $pk]);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Migration successfully purged and seeded hardcoded roles.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Migration failed: ' . $e->getMessage()]);
}
