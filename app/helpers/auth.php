<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once JOSTUM_ROOT . '/config/urls.php';

const JOSTUM_ROLES = [
    'SUPER_ADMIN',
    'ICT_ADMIN',
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
        return normalize_role($_SESSION['role'] ?? '');
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
    function has_permission(string $permission, ?string $role = null): bool
    {
        $role = normalize_role($role ?? current_user_role());
        $permissions = [
            'manage_system' => ['SUPER_ADMIN', 'ICT_ADMIN'],
            'manage_portal_content' => ['SUPER_ADMIN', 'ICT_ADMIN', 'PORTAL_ADMIN'],
            'manage_admissions' => ['SUPER_ADMIN', 'PORTAL_ADMIN', 'ADMISSIONS_OFFICER', 'PG_SCHOOL_OFFICER'],
            'manage_registry' => ['SUPER_ADMIN', 'REGISTRY', 'PG_SCHOOL_OFFICER'],
            'manage_bursary' => ['SUPER_ADMIN', 'BURSARY'],
            'manage_academics' => ['SUPER_ADMIN', 'PG_SCHOOL_OFFICER', 'FACULTY_OFFICER', 'DEPARTMENT_ADMIN', 'HOD'],
            'manage_supervision' => ['SUPER_ADMIN', 'DEPARTMENT_ADMIN', 'HOD', 'SUPERVISOR'],
            'review_applications' => ['SUPER_ADMIN', 'REVIEWER', 'PG_SCHOOL_OFFICER'],
            'student_portal' => ['STUDENT'],
        ];

        return in_array($role, $permissions[$permission] ?? [], true);
    }
}

if (!function_exists('dashboard_for_role')) {
    function dashboard_for_role(?string $role = null): string
    {
        return match (normalize_role($role ?? current_user_role())) {
            'SUPER_ADMIN', 'ICT_ADMIN' => 'ADMIN/super-admin/dashboard.php',
            'PORTAL_ADMIN' => 'ADMIN/portal-admin/dashboard.php',
            'REGISTRY' => 'modules/registry/dashboard.php',
            'ADMISSIONS_OFFICER', 'PG_SCHOOL_OFFICER' => 'ADMIN/admin/dashboard.php',
            'BURSARY' => 'modules/bursary/dashboard.php',
            'FACULTY_OFFICER', 'DEPARTMENT_ADMIN', 'HOD' => 'ADMIN/dept-admin/dashboard.php',
            'SUPERVISOR' => 'ADMIN/supervisor/dashboard.php',
            'REVIEWER' => 'ADMIN/reviewer/dashboard.php',
            'STUDENT' => 'APPLICANT/ADMISSIONS/dashboard.php',
            default => 'ADMIN/login.php',
        };
    }
}
