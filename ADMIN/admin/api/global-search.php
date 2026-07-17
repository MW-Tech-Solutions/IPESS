<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../config/urls.php';

if (!isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.', 'data' => []]);
    exit;
}

$sessionRole = strtoupper((string) $_SESSION['role']);
$allowedRoles = ['SUPER_ADMIN', 'ADMIN', 'DEPARTMENT_ADMIN', 'DEPT_ADMIN', 'REVIEWER', 'SUPERVISOR'];
if (!in_array($sessionRole, $allowedRoles, true)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.', 'data' => []]);
    exit;
}

function table_exists_search(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function search_role_url(string $role, string $type, int $applicationId = 0): string
{
    $role = strtoupper($role);
    $type = strtolower($type);

    if ($role === 'ICT_ADMIN') {
        if ($type === 'application') {
            return app_url('ADMIN/general/application-management.php' . ($applicationId > 0 ? '?app_no=' . urlencode($applicationId) : ''));
        }
        if ($type === 'referee') {
            return app_url('ADMIN/admin/referees.php');
        }
        return app_url('ADMIN/ict-admin/dashboard.php');
    }

    if ($role === 'SUPER_ADMIN') {
        if ($type === 'application' || $type === 'referee') {
            return app_url('ADMIN/super-admin/applications.php' . ($applicationId > 0 ? '?application_id=' . $applicationId : ''));
        }
        if ($type === 'user') return app_url('ADMIN/super-admin/user-management.php');
        if ($type === 'supervision') return app_url('ADMIN/super-admin/assign-supervisor.php');
        if ($type === 'department') return app_url('ADMIN/super-admin/departments.php');
        if ($type === 'course') return app_url('ADMIN/super-admin/courses.php');
        return app_url('ADMIN/super-admin/dashboard.php');
    }

    if ($role === 'ADMIN') {
        if ($type === 'application') return app_url('ADMIN/admin/application-management.php' . ($applicationId > 0 ? '?application_id=' . $applicationId : ''));
        if ($type === 'referee') return app_url('ADMIN/admin/referees.php' . ($applicationId > 0 ? '?application_id=' . $applicationId : ''));
        if ($type === 'supervision') return app_url('ADMIN/admin/assign-supervisor.php');
        if ($type === 'user') return app_url('ADMIN/admin/application-management.php');
        if ($type === 'department' || $type === 'course') return app_url('ADMIN/admin/application-management.php');
        return app_url('ADMIN/admin/dashboard.php');
    }

    if ($role === 'DEPARTMENT_ADMIN' || $role === 'DEPT_ADMIN') {
        if ($type === 'supervision') return app_url('ADMIN/dept-admin/supervisor-management.php');
        if ($type === 'user') return app_url('ADMIN/dept-admin/student-management.php');
        if ($type === 'application' || $type === 'referee') return app_url('ADMIN/dept-admin/department-applications.php');
        if ($type === 'department' || $type === 'course') return app_url('ADMIN/dept-admin/department-applications.php');
        return app_url('ADMIN/dept-admin/dashboard.php');
    }

    if ($role === 'REVIEWER') {
        if ($type === 'application' || $type === 'referee' || $type === 'user' || $type === 'department' || $type === 'course' || $type === 'supervision') {
            return app_url('ADMIN/reviewer/assigned-applications.php');
        }
        return app_url('ADMIN/reviewer/dashboard.php');
    }

    if ($role === 'SUPERVISOR') {
        if ($type === 'supervision') return app_url('ADMIN/supervisor/student-interaction.php');
        if ($type === 'application' || $type === 'referee' || $type === 'user' || $type === 'department' || $type === 'course') {
            return app_url('ADMIN/supervisor/my-students.php');
        }
        return app_url('ADMIN/supervisor/dashboard.php');
    }

    return app_url('ADMIN/login.php');
}

$q = trim((string) ($_GET['q'] ?? ''));
if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

$like = '%' . $q . '%';
$results = [];

try {
    if (table_exists_search($pdo, 'applications') && table_exists_search($pdo, 'users')) {
        $hasPersonal = table_exists_search($pdo, 'personal_details');
        $nameSelect = $hasPersonal
            ? "COALESCE(CONCAT(p.first_name, ' ', p.surname), u.full_name, u.email) AS applicant_name"
            : "COALESCE(u.full_name, u.email) AS applicant_name";
        $nameJoin = $hasPersonal ? "LEFT JOIN personal_details p ON p.application_id = a.application_id" : "";

        $stmt = $pdo->prepare("
            SELECT a.application_id, a.application_number, a.status, u.email, {$nameSelect}
            FROM applications a
            JOIN users u ON u.user_id = a.user_id
            {$nameJoin}
            WHERE a.application_number LIKE ?
               OR u.email LIKE ?
               OR COALESCE(u.full_name, '') LIKE ?
               " . ($hasPersonal ? "OR COALESCE(p.first_name, '') LIKE ? OR COALESCE(p.surname, '') LIKE ?" : "") . "
            ORDER BY a.application_id DESC
            LIMIT 10
        ");
        $params = [$like, $like, $like];
        if ($hasPersonal) {
            $params[] = $like;
            $params[] = $like;
        }
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'type' => 'Application',
                'label' => ($row['application_number'] ?: ('App #' . (int) $row['application_id'])) . ' - ' . ($row['applicant_name'] ?: $row['email']),
                'meta' => 'Status: ' . ($row['status'] ?: 'Draft'),
                'url' => search_role_url($sessionRole, 'application', (int) $row['application_id'])
            ];
        }
    }

    if (table_exists_search($pdo, 'users')) {
        $hasRoles = table_exists_search($pdo, 'roles');
        $roleJoin = $hasRoles ? "LEFT JOIN roles r ON r.role_id = u.role_id" : "";
        $roleSelect = $hasRoles ? "COALESCE(r.role_name, r.role_key, 'User') AS role_name" : "'User' AS role_name";
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.full_name, u.email, {$roleSelect}
            FROM users u
            {$roleJoin}
            WHERE u.email LIKE ? OR COALESCE(u.full_name, '') LIKE ?
            ORDER BY u.user_id DESC
            LIMIT 8
        ");
        $stmt->execute([$like, $like]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $role = (string) ($row['role_name'] ?? 'User');
            $url = search_role_url($sessionRole, 'user');

            $results[] = [
                'type' => 'User',
                'label' => ($row['full_name'] ?: $row['email']) . ' (' . $row['email'] . ')',
                'meta' => 'Role: ' . $role,
                'url' => $url
            ];
        }
    }

    if (table_exists_search($pdo, 'referees') && table_exists_search($pdo, 'applications')) {
        $stmt = $pdo->prepare("
            SELECT r.referee_id, r.full_name, r.email, a.application_id, a.application_number
            FROM referees r
            JOIN applications a ON a.application_id = r.application_id
            WHERE COALESCE(r.full_name, '') LIKE ? OR COALESCE(r.email, '') LIKE ?
            ORDER BY r.referee_id DESC
            LIMIT 8
        ");
        $stmt->execute([$like, $like]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'type' => 'Referee',
                'label' => ($row['full_name'] ?: 'Referee') . ' (' . ($row['email'] ?: 'No email') . ')',
                'meta' => 'Application: ' . ($row['application_number'] ?: ('#' . (int) $row['application_id'])),
                'url' => search_role_url($sessionRole, 'referee', (int) $row['application_id'])
            ];
        }
    }

    if (table_exists_search($pdo, 'supervisor_students')) {
        $stmt = $pdo->prepare("
            SELECT student_id, full_name, email, programme, supervisor_name
            FROM supervisor_students
            WHERE COALESCE(full_name, '') LIKE ?
               OR COALESCE(email, '') LIKE ?
               OR COALESCE(programme, '') LIKE ?
               OR COALESCE(supervisor_name, '') LIKE ?
            ORDER BY updated_at DESC
            LIMIT 8
        ");
        $stmt->execute([$like, $like, $like, $like]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'type' => 'Supervision',
                'label' => ($row['full_name'] ?: $row['student_id']) . ($row['programme'] ? ' - ' . $row['programme'] : ''),
                'meta' => 'Supervisor: ' . ($row['supervisor_name'] ?: 'Not assigned'),
                'url' => search_role_url($sessionRole, 'supervision')
            ];
        }
    }

    if (table_exists_search($pdo, 'departments')) {
        $stmt = $pdo->prepare("
            SELECT dept_id, dept_name
            FROM departments
            WHERE dept_name LIKE ?
            ORDER BY dept_name ASC
            LIMIT 6
        ");
        $stmt->execute([$like]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'type' => 'Department',
                'label' => (string) $row['dept_name'],
                'meta' => 'Department setup',
                'url' => search_role_url($sessionRole, 'department')
            ];
        }
    }

    if (table_exists_search($pdo, 'courses')) {
        $stmt = $pdo->prepare("
            SELECT course_id, course_title
            FROM courses
            WHERE course_title LIKE ?
            ORDER BY course_title ASC
            LIMIT 6
        ");
        $stmt->execute([$like]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'type' => 'Course',
                'label' => (string) $row['course_title'],
                'meta' => 'Course record',
                'url' => search_role_url($sessionRole, 'course')
            ];
        }
    }

    $seen = [];
    $deduped = [];
    foreach ($results as $item) {
        $key = $item['type'] . '|' . $item['label'] . '|' . $item['url'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $deduped[] = $item;
        if (count($deduped) >= 20) {
            break;
        }
    }

    echo json_encode(['success' => true, 'data' => $deduped]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Search failed.', 'data' => []]);
}
