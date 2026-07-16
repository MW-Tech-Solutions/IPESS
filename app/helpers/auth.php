<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once JOSTUM_ROOT . '/config/urls.php';

const JOSTUM_ROLES = [
    'SUPER_ADMIN',
    'ICT_ADMIN',
    'ICT_SUPPORT',
    'STUDENT_MANAGER',
    'ACADEMIC_MANAGER',
    'SUPERVISOR_MANAGER',
    'PORTAL_ADMIN',
    'REGISTRY',
    'ADMISSIONS_OFFICER',
    'BURSARY',
    'PG_SCHOOL_OFFICER',
    'FACULTY_OFFICER',
    'DEPARTMENT_ADMIN',
    'HOD',
    'SUPERVISOR',
    'REVIEWER',
    'ICT_STAFF',
    'STUDENT',
];

const JOSTUM_LEGACY_ROLE_MAP = [
    'ADMIN' => 'PG_SCHOOL_OFFICER',
    'DEPT_ADMIN' => 'DEPARTMENT_ADMIN',
    'APPLICANT' => 'STUDENT',
];

if (!function_exists('normalize_role')) {
    function normalize_role(?string $role): string
    {
        $role = strtoupper(trim((string) $role));
        $role = str_replace([' ', '-'], '_', $role);
        return JOSTUM_LEGACY_ROLE_MAP[$role] ?? $role;
    }
}

if (!function_exists('current_user_role')) {
    function current_user_role(): string
    {
        static $cachedRole = null;
        if ($cachedRole !== null) {
            return $cachedRole;
        }

        $sessionRole = $_SESSION['role'] ?? '';
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        if ($userId > 0) {
            try {
                require_once JOSTUM_ROOT . '/app/config/database.php';
                $pdo = db();
                $stmt = $pdo->prepare("
                    SELECT r.role_key 
                    FROM users u 
                    LEFT JOIN roles r ON u.role_id = r.role_id 
                    WHERE u.user_id = ? 
                    LIMIT 1
                ");
                $stmt->execute([$userId]);
                $dbRole = $stmt->fetchColumn();
                if ($dbRole) {
                    $_SESSION['role'] = $dbRole;
                    $sessionRole = $dbRole;
                }
            } catch (Throwable $e) {
                // Keep session role as fallback if DB isn't available or ready
            }
        }

        $cachedRole = normalize_role($sessionRole);
        return $cachedRole;
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        start_secure_session();
        return !empty($_SESSION['user_id']);
    }
}

if (!function_exists('require_login')) {
    function require_login(string $loginPath = 'login.php'): void
    {
        start_secure_session();
        if (!is_logged_in()) {
            redirect_to($loginPath);
        }
    }
}

if (!function_exists('require_role')) {
    function require_role(array $roles, string $loginPath = 'login.php'): void
    {
        require_login($loginPath);
        $allowed = array_map('normalize_role', $roles);
        if (!in_array(current_user_role(), $allowed, true)) {
            http_response_code(403);
            exit('403 Forbidden');
        }
    }
}

if (!function_exists('has_permission')) {
    function has_permission(string $permission, ?string $role = null, ?int $userId = null): bool
    {
        $role = normalize_role($role ?? current_user_role());

        // Always allow super admins and ICT admins full control
        if (in_array($role, ['SUPER_ADMIN', 'ICT_ADMIN'], true)) {
            return true;
        }

        if ($userId === null && isset($_SESSION['user_id'])) {
            $userId = (int) $_SESSION['user_id'];
        }

        // Try checking database overrides first
        try {
            require_once JOSTUM_ROOT . '/app/config/database.php';
            $pdo = db();
            
            // Check user override first!
            if ($userId !== null && $userId > 0) {
                $stmtOverride = $pdo->prepare("SELECT granted FROM user_permissions WHERE user_id = ? AND permission_key = ?");
                $stmtOverride->execute([$userId, $permission]);
                $override = $stmtOverride->fetch(PDO::FETCH_ASSOC);
                if ($override !== false) {
                    return (int) $override['granted'] === 1;
                }
            }

            // Fall back to role permissions
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_key = ? AND permission_key = ?");
            $stmt->execute([$role, $permission]);
            if ((int) $stmt->fetchColumn() > 0) {
                return true;
            }
        } catch (Throwable $e) {
            // Fall back to hardcoded arrays if the table isn't created or has issues
        }

        $permissions = [
            'manage_system' => ['SUPER_ADMIN', 'ICT_ADMIN'],
            'manage_portal_content' => ['SUPER_ADMIN', 'ICT_ADMIN', 'PORTAL_ADMIN'],
            'manage_admissions' => ['SUPER_ADMIN', 'PORTAL_ADMIN', 'ADMISSIONS_OFFICER', 'PG_SCHOOL_OFFICER'],
            'manage_registry' => ['SUPER_ADMIN', 'REGISTRY', 'PG_SCHOOL_OFFICER'],
            'manage_bursary' => ['SUPER_ADMIN', 'BURSARY'],
            'manage_academics' => ['SUPER_ADMIN', 'PG_SCHOOL_OFFICER', 'FACULTY_OFFICER', 'DEPARTMENT_ADMIN', 'HOD', 'ACADEMIC_MANAGER'],
            'manage_supervision' => ['SUPER_ADMIN', 'DEPARTMENT_ADMIN', 'HOD', 'SUPERVISOR', 'SUPERVISOR_MANAGER'],
            'review_applications' => ['SUPER_ADMIN', 'REVIEWER', 'PG_SCHOOL_OFFICER'],
            'view_applications' => ['SUPER_ADMIN', 'ICT_ADMIN', 'PORTAL_ADMIN', 'ADMISSIONS_OFFICER', 'PG_SCHOOL_OFFICER', 'REVIEWER', 'REGISTRY', 'ICTO', 'DEPARTMENT_ADMIN', 'HOD', 'FACULTY_OFFICER', 'ICT_STAFF', 'STUDENT_MANAGER', 'ACADEMIC_MANAGER', 'SUPERVISOR_MANAGER', 'ICT_SUPPORT'],
            'view_applicants' => ['SUPER_ADMIN', 'ICT_ADMIN', 'PORTAL_ADMIN', 'ADMISSIONS_OFFICER', 'PG_SCHOOL_OFFICER', 'REVIEWER', 'REGISTRY', 'ICTO', 'DEPARTMENT_ADMIN', 'HOD', 'FACULTY_OFFICER', 'ICT_STAFF', 'STUDENT_MANAGER', 'ACADEMIC_MANAGER', 'SUPERVISOR_MANAGER', 'ICT_SUPPORT'],
            'download_applications' => ['SUPER_ADMIN', 'ICT_ADMIN', 'PORTAL_ADMIN', 'ADMISSIONS_OFFICER', 'PG_SCHOOL_OFFICER', 'STUDENT_MANAGER'],
            'download_documents' => ['SUPER_ADMIN', 'ICT_ADMIN', 'PORTAL_ADMIN', 'ADMISSIONS_OFFICER', 'PG_SCHOOL_OFFICER', 'ICTO', 'STUDENT_MANAGER'],
            'student_portal' => ['STUDENT'],
            'verify_applicants' => ['SUPER_ADMIN', 'ICT_ADMIN', 'ICTO', 'DEPARTMENT_ADMIN', 'HOD', 'FACULTY_OFFICER', 'PG_SCHOOL_OFFICER', 'STUDENT_MANAGER'],
            'bulk_verify' => ['SUPER_ADMIN', 'ICT_ADMIN', 'ICTO', 'DEPARTMENT_ADMIN', 'HOD', 'FACULTY_OFFICER', 'PG_SCHOOL_OFFICER', 'STUDENT_MANAGER'],
            'supervisor_management' => ['SUPER_ADMIN', 'DEPARTMENT_ADMIN', 'HOD', 'SUPERVISOR_MANAGER'],
            'assign_supervisor' => ['SUPER_ADMIN', 'DEPARTMENT_ADMIN', 'HOD', 'SUPERVISOR_MANAGER'],
            'change_supervisor' => ['SUPER_ADMIN', 'DEPARTMENT_ADMIN', 'HOD', 'SUPERVISOR_MANAGER'],
            'remove_supervisor' => ['SUPER_ADMIN', 'DEPARTMENT_ADMIN', 'HOD', 'SUPERVISOR_MANAGER'],
            'bulk_supervisor_allocation' => ['SUPER_ADMIN', 'DEPARTMENT_ADMIN', 'HOD', 'SUPERVISOR_MANAGER'],
            'faculty_review' => ['SUPER_ADMIN', 'FACULTY_OFFICER'],
            'department_review' => ['SUPER_ADMIN', 'DEPARTMENT_ADMIN', 'HOD'],
            'pg_review' => ['SUPER_ADMIN', 'PG_SCHOOL_OFFICER'],
            'ict_processing' => ['SUPER_ADMIN', 'ICT_STAFF'],
            'generate_matric_number' => ['SUPER_ADMIN', 'ICT_STAFF'],
            'generate_student_number' => ['SUPER_ADMIN', 'ICT_STAFF'],
            'acceptance_letter' => ['SUPER_ADMIN', 'ICT_STAFF'],
            'admission_letter' => ['SUPER_ADMIN', 'ICT_STAFF'],
            'reports' => ['SUPER_ADMIN', 'ICT_ADMIN', 'PORTAL_ADMIN', 'ADMISSIONS_OFFICER', 'PG_SCHOOL_OFFICER', 'DEPARTMENT_ADMIN', 'HOD', 'FACULTY_OFFICER'],
            'export_pdf' => ['SUPER_ADMIN', 'DEPARTMENT_ADMIN', 'HOD', 'PG_SCHOOL_OFFICER', 'STUDENT_MANAGER'],
            'export_excel' => ['SUPER_ADMIN', 'DEPARTMENT_ADMIN', 'HOD', 'PG_SCHOOL_OFFICER', 'STUDENT_MANAGER'],
            'export_csv' => ['SUPER_ADMIN', 'DEPARTMENT_ADMIN', 'HOD', 'PG_SCHOOL_OFFICER', 'STUDENT_MANAGER'],
            'download_records' => ['SUPER_ADMIN', 'DEPARTMENT_ADMIN', 'HOD', 'PG_SCHOOL_OFFICER', 'STUDENT_MANAGER'],
            'view_audit_logs' => ['SUPER_ADMIN', 'ICT_ADMIN'],
            'view_dashboard' => ['SUPER_ADMIN', 'ICT_ADMIN', 'PORTAL_ADMIN', 'ADMISSIONS_OFFICER', 'PG_SCHOOL_OFFICER', 'REVIEWER', 'REGISTRY', 'ICTO', 'DEPARTMENT_ADMIN', 'HOD', 'FACULTY_OFFICER', 'ICT_STAFF', 'SUPERVISOR', 'STUDENT_MANAGER', 'ACADEMIC_MANAGER', 'SUPERVISOR_MANAGER', 'ICT_SUPPORT'],
            'user_management' => ['SUPER_ADMIN', 'ICT_ADMIN'],
            'role_management' => ['SUPER_ADMIN', 'ICT_ADMIN'],
            'permission_management' => ['SUPER_ADMIN', 'ICT_ADMIN'],
            'settings' => ['SUPER_ADMIN', 'ICT_ADMIN'],
            'workflow_configuration' => ['SUPER_ADMIN', 'ICT_ADMIN'],
            'notifications' => ['SUPER_ADMIN', 'ICT_ADMIN', 'PORTAL_ADMIN', 'ADMISSIONS_OFFICER', 'PG_SCHOOL_OFFICER', 'DEPARTMENT_ADMIN', 'HOD', 'FACULTY_OFFICER', 'ICT_STAFF'],
            'logs' => ['SUPER_ADMIN', 'ICT_ADMIN'],
            
            // Exact keys from permissions registry
            'reset_authenticator' => ['SUPER_ADMIN', 'ICT_ADMIN', 'ICT_SUPPORT', 'ICTO'],
            'manage_users' => ['SUPER_ADMIN', 'ICT_ADMIN'],
            'manage_roles' => ['SUPER_ADMIN', 'ICT_ADMIN'],
            'manage_students' => ['SUPER_ADMIN', 'STUDENT_MANAGER'],
            'view_students' => ['SUPER_ADMIN', 'STUDENT_MANAGER'],
            'export_students' => ['SUPER_ADMIN', 'STUDENT_MANAGER'],
            'manage_faculties' => ['SUPER_ADMIN', 'ACADEMIC_MANAGER'],
            'manage_departments' => ['SUPER_ADMIN', 'ACADEMIC_MANAGER'],
            'manage_programmes' => ['SUPER_ADMIN', 'ACADEMIC_MANAGER'],
            'manage_courses' => ['SUPER_ADMIN', 'ACADEMIC_MANAGER'],
            'manage_supervisors' => ['SUPER_ADMIN', 'SUPERVISOR_MANAGER', 'DEPARTMENT_ADMIN', 'HOD'],
        ];

        return in_array($role, $permissions[$permission] ?? [], true);
    }
}

if (!function_exists('require_role_or_permission')) {
    function require_role_or_permission(array $roles, string $permission, string $loginPath = 'login.php'): void
    {
        require_login($loginPath);
        $allowed = array_map('normalize_role', $roles);
        $currentRole = current_user_role();
        if (in_array($currentRole, $allowed, true) || has_permission($permission)) {
            return;
        }
        http_response_code(403);
        exit('403 Forbidden');
    }
}

if (!function_exists('dashboard_for_role')) {
    function dashboard_for_role(?string $role = null): string
    {
        $role = normalize_role($role ?? current_user_role());
        
        $dashboard = match ($role) {
            'SUPER_ADMIN', 'ICT_ADMIN' => 'ADMIN/super-admin/dashboard.php',
            'PORTAL_ADMIN'       => 'ADMIN/portal-admin/dashboard.php',
            'REGISTRY'           => 'modules/registry/dashboard.php',
            'ICTO'               => 'ADMIN/icto/dashboard.php',
            'FACULTY_OFFICER'    => 'ADMIN/faculty/dashboard.php',
            'PG_SCHOOL_OFFICER'  => 'ADMIN/pg-admin/dashboard.php',
            'ICT_STAFF'          => 'ADMIN/ict-staff/dashboard.php',
            'DEPARTMENT_ADMIN', 'HOD' => 'ADMIN/dept-admin/dashboard.php',
            'SUPERVISOR'         => 'ADMIN/supervisor/dashboard.php',
            'REVIEWER'           => 'ADMIN/reviewer/dashboard.php',
            'STUDENT'            => 'APPLICANT/ADMISSIONS/dashboard.php',
            default              => 'ADMIN/general/dashboard.php'
        };

        if ($dashboard !== null) {
            return $dashboard;
        }

        if (has_permission('view_applications', $role)) {
            return 'ADMIN/admin/dashboard.php';
        }

        return 'ADMIN/login.php';
    }
}
