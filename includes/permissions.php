<?php
require_once __DIR__ . '/../app/helpers/auth.php';

function current_role(): ?string {
    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? null;
    return $role ? normalize_role($role) : null;
}

if (!function_exists('require_role')) {
function require_role(array $allowed): void {
    $role = current_role();
    $allowed = array_map('normalize_role', $allowed);
    if (!$role || !in_array($role, $allowed, true)) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        exit;
    }
}
}

function can_edit_application(string $current_status): bool {
    return in_array($current_status, ['DRAFT', 'ACTION_REQUIRED_DOCS', 'ACTION_REQUIRED_REVIEW', 'TOPIC_REJECTED'], true);
}

function can_view_department_application(?int $dept_id, ?int $user_dept_id): bool {
    if ($dept_id === null || $user_dept_id === null) {
        return false;
    }
    return (int) $dept_id === (int) $user_dept_id;
}

function is_admin_role(string $role): bool {
    return in_array(normalize_role($role), ['SUPER_ADMIN', 'ICT_ADMIN', 'PORTAL_ADMIN', 'PG_SCHOOL_OFFICER', 'ADMISSIONS_OFFICER'], true);
}

function is_department_admin(string $role): bool {
    return in_array(normalize_role($role), ['DEPARTMENT_ADMIN', 'FACULTY_OFFICER', 'HOD'], true);
}

function is_reviewer(string $role): bool {
    return normalize_role($role) === 'REVIEWER';
}

function is_supervisor(string $role): bool {
    return normalize_role($role) === 'SUPERVISOR';
}
