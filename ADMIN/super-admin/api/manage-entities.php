<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$entity = $_GET['entity'] ?? $_POST['entity'] ?? '';

if ($action === 'get_permissions' || $action === 'save_permissions' || $entity === 'roles') {
    if (!has_permission('manage_roles')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied: Requires manage_roles permission.']);
        exit;
    }
} else {
    if (!has_permission('manage_academics')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied: Requires manage_academics permission.']);
        exit;
    }
}

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}

// Ensure the table exists dynamically
function ensure_role_permissions_table(PDO $pdo): void {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS role_permissions (
            role_key VARCHAR(50) NOT NULL,
            permission_key VARCHAR(100) NOT NULL,
            PRIMARY KEY (role_key, permission_key)
        )");
    } catch (Throwable $e) {}
}
ensure_role_permissions_table($pdo);

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'get_permissions') {
    $roleKey = strtoupper(trim($_GET['role_key'] ?? ''));
    if ($roleKey === '') {
        echo json_encode(['success' => false, 'message' => 'Role key is required.']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("SELECT permission_key FROM role_permissions WHERE role_key = ?");
        $stmt->execute([$roleKey]);
        $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'data' => $permissions]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'save_permissions') {
    $roleKey = strtoupper(trim($_POST['role_key'] ?? ''));
    $permissions = $_POST['permissions'] ?? [];
    if ($roleKey === '') {
        echo json_encode(['success' => false, 'message' => 'Role key is required.']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_key = ?");
        $stmt->execute([$roleKey]);
        
        if (!empty($permissions) && is_array($permissions)) {
            $stmt = $pdo->prepare("INSERT INTO role_permissions (role_key, permission_key) VALUES (?, ?)");
            foreach ($permissions as $perm) {
                $stmt->execute([$roleKey, trim($perm)]);
            }
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$entity = $_GET['entity'] ?? $_POST['entity'] ?? '';

$allowedEntities = ['roles', 'faculties', 'departments', 'degree_types', 'courses', 'capacities'];
if (!in_array($entity, $allowedEntities, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid entity.']);
    exit;
}

try {
    if ($action === 'list') {
        switch ($entity) {
            case 'roles':
                $rows = $pdo->query("SELECT role_id, role_key, role_name FROM roles ORDER BY role_name")->fetchAll(PDO::FETCH_ASSOC);
                break;
            case 'faculties':
                $rows = $pdo->query("SELECT faculty_id, faculty_name FROM faculties ORDER BY faculty_name")->fetchAll(PDO::FETCH_ASSOC);
                break;
            case 'departments':
                $facultyFilter = (int) ($_GET['faculty_id'] ?? 0);
                if ($facultyFilter > 0) {
                    $stmt = $pdo->prepare("
                        SELECT d.dept_id, d.dept_name, f.faculty_name, d.faculty_id
                        FROM departments d
                        LEFT JOIN faculties f ON f.faculty_id = d.faculty_id
                        WHERE d.faculty_id = ?
                        ORDER BY d.dept_name
                    ");
                    $stmt->execute([$facultyFilter]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $rows = $pdo->query("
                        SELECT d.dept_id, d.dept_name, f.faculty_name, d.faculty_id
                        FROM departments d
                        LEFT JOIN faculties f ON f.faculty_id = d.faculty_id
                        ORDER BY d.dept_name
                    ")->fetchAll(PDO::FETCH_ASSOC);
                }
                break;
            case 'degree_types':
                $rows = $pdo->query("SELECT degree_id, degree_name FROM degree_types ORDER BY degree_name")->fetchAll(PDO::FETCH_ASSOC);
                break;
            case 'courses':
                $facultyFilter = (int) ($_GET['faculty_id'] ?? 0);
                $departmentId = (int) ($_GET['department_id'] ?? 0);
                $programmeId = (int) ($_GET['programme_id'] ?? 0);
                
                $where = [];
                $params = [];
                
                if ($facultyFilter > 0) {
                    $where[] = "d.faculty_id = ?";
                    $params[] = $facultyFilter;
                }
                if ($departmentId > 0) {
                    $where[] = "c.dept_id = ?";
                    $params[] = $departmentId;
                }
                if ($programmeId > 0) {
                    $where[] = "c.degree_id = ?";
                    $params[] = $programmeId;
                }
                
                $whereSql = '';
                if (!empty($where)) {
                    $whereSql = 'WHERE ' . implode(' AND ', $where);
                }
                
                $stmt = $pdo->prepare("
                    SELECT c.course_id, c.course_title, c.dept_id, c.degree_id,
                           d.dept_name, dt.degree_name
                     FROM courses c
                     LEFT JOIN departments d ON d.dept_id = c.dept_id
                     LEFT JOIN degree_types dt ON dt.degree_id = c.degree_id
                     {$whereSql}
                     ORDER BY c.course_title
                ");
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
            case 'capacities':
                $rows = $pdo->query("
                    SELECT pc.capacity_id, pc.course_id, pc.capacity, pc.is_active, c.course_title
                    FROM programme_capacities pc
                    LEFT JOIN courses c ON c.course_id = pc.course_id
                    ORDER BY c.course_title
                ")->fetchAll(PDO::FETCH_ASSOC);
                break;
        }
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    if ($action === 'create') {
        switch ($entity) {
            case 'roles':
                $roleKey = strtoupper(trim($_POST['role_key'] ?? ''));
                $roleName = trim($_POST['role_name'] ?? '');
                if ($roleKey === '' || $roleName === '') {
                    throw new RuntimeException('Role key and name are required.');
                }
                $stmt = $pdo->prepare("INSERT INTO roles (role_key, role_name) VALUES (?, ?)");
                $stmt->execute([$roleKey, $roleName]);
                break;
            case 'faculties':
                $name = trim($_POST['faculty_name'] ?? '');
                if ($name === '') {
                    throw new RuntimeException('Faculty name is required.');
                }
                $stmt = $pdo->prepare("INSERT INTO faculties (faculty_name) VALUES (?)");
                $stmt->execute([$name]);
                break;
            case 'departments':
                $name = trim($_POST['dept_name'] ?? '');
                $facultyId = (int) ($_POST['faculty_id'] ?? 0);
                if ($name === '' || $facultyId === 0) {
                    throw new RuntimeException('Department name and faculty are required.');
                }
                $stmt = $pdo->prepare("INSERT INTO departments (dept_name, faculty_id) VALUES (?, ?)");
                $stmt->execute([$name, $facultyId]);
                break;
            case 'degree_types':
                $name = trim($_POST['degree_name'] ?? '');
                if ($name === '') {
                    throw new RuntimeException('Programme type is required.');
                }
                $stmt = $pdo->prepare("INSERT INTO degree_types (degree_name) VALUES (?)");
                $stmt->execute([$name]);
                break;
            case 'courses':
                $title = trim($_POST['course_title'] ?? '');
                $deptId = (int) ($_POST['dept_id'] ?? 0);
                $degreeId = (int) ($_POST['degree_id'] ?? 0);
                if ($title === '' || $deptId === 0 || $degreeId === 0) {
                    throw new RuntimeException('Course title, department, and programme type are required.');
                }
                $stmt = $pdo->prepare("INSERT INTO courses (course_title, dept_id, degree_id) VALUES (?, ?, ?)");
                $stmt->execute([$title, $deptId, $degreeId]);
                break;
            case 'capacities':
                $courseId = (int) ($_POST['course_id'] ?? 0);
                $capacity = (int) ($_POST['capacity'] ?? 0);
                $isActive = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;
                if ($courseId === 0) {
                    throw new RuntimeException('Course is required.');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO programme_capacities (course_id, capacity, is_active)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE capacity = VALUES(capacity), is_active = VALUES(is_active)
                ");
                $stmt->execute([$courseId, $capacity, $isActive]);
                break;
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === 0) {
            throw new RuntimeException('Invalid record id.');
        }
        switch ($entity) {
            case 'roles':
                $stmt = $pdo->prepare("DELETE FROM roles WHERE role_id = ?");
                break;
            case 'faculties':
                $stmt = $pdo->prepare("DELETE FROM faculties WHERE faculty_id = ?");
                break;
            case 'departments':
                $stmt = $pdo->prepare("DELETE FROM departments WHERE dept_id = ?");
                break;
            case 'degree_types':
                $stmt = $pdo->prepare("DELETE FROM degree_types WHERE degree_id = ?");
                break;
            case 'courses':
                $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id = ?");
                break;
            case 'capacities':
                $stmt = $pdo->prepare("DELETE FROM programme_capacities WHERE capacity_id = ?");
                break;
        }
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    throw new RuntimeException('Unsupported action.');
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
