<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function get_columns(PDO $pdo, string $table): array {
    $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function get_column_type(PDO $pdo, string $table, string $column): ?string {
    $stmt = $pdo->prepare("SELECT data_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    $type = $stmt->fetchColumn();
    return $type ? strtolower((string) $type) : null;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if (!$pdo) {
        echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
        exit;
    }

    if ($action === 'departments') {
        $facultyId = $_GET['faculty_id'] ?? '';
        if (table_exists($pdo, 'departments')) {
            if ($facultyId !== '') {
                $stmt = $pdo->prepare("SELECT dept_id AS id, dept_name AS name FROM departments WHERE faculty_id = ? ORDER BY dept_name ASC");
                $stmt->execute([$facultyId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $rows = $pdo->query("SELECT dept_id AS id, dept_name AS name FROM departments ORDER BY dept_name ASC")->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            $rows = $pdo->query("SELECT DISTINCT department AS id, department AS name FROM programme_choices ORDER BY department ASC")->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    if ($action === 'faculties') {
        if (table_exists($pdo, 'faculties')) {
            $rows = $pdo->query("SELECT faculty_id AS id, faculty_name AS name FROM faculties ORDER BY faculty_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $rows = $pdo->query("SELECT DISTINCT faculty AS id, faculty AS name FROM programme_choices ORDER BY faculty ASC")->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    // programme and course filters removed; use applications.department_id only

    if ($action === 'supervisors') {
        $departmentId = $_GET['department_id'] ?? '';
        $rows = [];

        if (column_exists($pdo, 'users', 'role_id')) {
            $roleId = 4;
            if (table_exists($pdo, 'roles')) {
                $roleStmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_key = 'SUPERVISOR' OR role_name = 'Supervisor' LIMIT 1");
                $roleStmt->execute();
                $roleId = (int) ($roleStmt->fetchColumn() ?: 4);
            }
            if ($departmentId !== '') {
                $stmt = $pdo->prepare("
                    SELECT user_id AS id, COALESCE(full_name, email) AS name, email
                    FROM users
                    WHERE role_id = ? AND department_id = ?
                    ORDER BY COALESCE(full_name, email) ASC
                ");
                $stmt->execute([$roleId, $departmentId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT user_id AS id, COALESCE(full_name, email) AS name, email
                    FROM users
                    WHERE role_id = ?
                    ORDER BY COALESCE(full_name, email) ASC
                ");
                $stmt->execute([$roleId]);
            }
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif (table_exists($pdo, 'supervisor_profiles')) {
            $stmt = $pdo->prepare("SELECT supervisor_id AS id, full_name AS name, email FROM supervisor_profiles WHERE status = 'Active' ORDER BY full_name ASC");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    if ($action === 'students') {
        $departmentId = $_GET['department_id'] ?? '';
        if ($departmentId === '') {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        $hasCurrentStatus = column_exists($pdo, 'applications', 'current_status');
        $admitWhere = $hasCurrentStatus
            ? "(LOWER(a.status) = 'admitted' OR LOWER(a.current_status) IN ('admission_approved'))"
            : "LOWER(a.status) = 'admitted'";

        $deptJoin = table_exists($pdo, 'departments')
            ? "LEFT JOIN departments d ON a.department_id = d.dept_id"
            : "LEFT JOIN departments d ON 1=0";

        $where = "a.department_id = ? AND {$admitWhere}";
        $params = [$departmentId];

        $studentSql = "
            SELECT a.application_id,
                   a.user_id AS student_id,
                   a.application_number,
                   a.status,
                   p.first_name,
                   p.surname,
                   COALESCE(u.email, aa.email) AS email,
                   pc.department,
                   d.dept_name,
                   COALESCE(dt.degree_name, '') AS degree_name,
                   COALESCE(c.course_title, '') AS course_title
            FROM applications a
            LEFT JOIN programme_choices pc ON pc.application_id = a.application_id
            LEFT JOIN personal_details p ON p.application_id = a.application_id
            LEFT JOIN degree_types dt ON pc.degree_type = dt.degree_id
            LEFT JOIN courses c ON pc.course = c.course_id
            LEFT JOIN users u ON u.user_id = a.user_id
            LEFT JOIN applicant_accounts aa ON aa.user_id = a.user_id
            {$deptJoin}
            WHERE {$where}
            ORDER BY p.surname ASC, p.first_name ASC
        ";
        $stmt = $pdo->prepare($studentSql);
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $assignmentMap = [];
        if (table_exists($pdo, 'supervisor_students') && !empty($students)) {
            $cols = get_columns($pdo, 'supervisor_students');
            $studentCol = in_array('student_id', $cols, true) ? 'student_id' : (in_array('user_id', $cols, true) ? 'user_id' : null);
            $supervisorNameCol = in_array('supervisor_name', $cols, true) ? 'supervisor_name' : null;
            $keyCol = $studentCol;

            if ($supervisorNameCol && $keyCol) {
                $keys = array_map(static function ($row) {
                    return $row['application_number'] ?: (string) $row['student_id'];
                }, $students);
                $keys = array_values(array_filter(array_unique($keys)));
                if (!empty($keys)) {
                    $placeholders = implode(',', array_fill(0, count($keys), '?'));
                    $stmt = $pdo->prepare("SELECT {$keyCol} AS ref_id, {$supervisorNameCol} AS supervisor_name FROM supervisor_students WHERE {$keyCol} IN ({$placeholders})");
                    $stmt->execute($keys);
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $assignmentMap[(string) $row['ref_id']] = (string) $row['supervisor_name'];
                    }
                }
            }
        }

        if (!empty($assignmentMap)) {
            foreach ($students as &$student) {
                $refId = $student['application_number'] ?: (string) $student['student_id'];
                $student['assigned_supervisor_name'] = $assignmentMap[$refId] ?? null;
            }
            unset($student);
        }

        echo json_encode(['success' => true, 'data' => $students]);
        exit;
    }

    if ($action === 'assign') {
        $studentId = (int) ($_POST['student_id'] ?? 0);
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $applicationNumber = trim((string) ($_POST['application_number'] ?? ''));
        $supervisorInput = trim((string) ($_POST['supervisor_id'] ?? ''));
        $departmentId = $_POST['department_id'] ?? null;

        if (($studentId <= 0 && $applicationNumber === '') || $supervisorInput === '') {
            echo json_encode(['success' => false, 'message' => 'Missing student or supervisor.']);
            exit;
        }

        if (!table_exists($pdo, 'supervisor_students')) {
            echo json_encode(['success' => false, 'message' => 'supervisor_students table not found.']);
            exit;
        }

        $cols = get_columns($pdo, 'supervisor_students');
        $studentCol = in_array('student_id', $cols, true) ? 'student_id' : (in_array('user_id', $cols, true) ? 'user_id' : null);
        $supervisorNameCol = in_array('supervisor_name', $cols, true) ? 'supervisor_name' : null;
        $fullNameCol = in_array('full_name', $cols, true) ? 'full_name' : null;
        $programmeCol = in_array('programme', $cols, true) ? 'programme' : null;
        $emailCol = in_array('email', $cols, true) ? 'email' : null;
        $assignedAtCol = in_array('assigned_at', $cols, true) ? 'assigned_at' : null;
        $supervisorUserIdCol = in_array('supervisor_user_id', $cols, true) ? 'supervisor_user_id' : null;
        $studentUserIdCol = in_array('student_user_id', $cols, true) ? 'student_user_id' : null;
        $applicationIdCol = in_array('application_id', $cols, true) ? 'application_id' : null;
        $applicationNumberCol = in_array('application_number', $cols, true) ? 'application_number' : null;
        $departmentIdCol = in_array('department_id', $cols, true) ? 'department_id' : null;

        if ($studentCol === null) {
            echo json_encode(['success' => false, 'message' => 'supervisor_students schema missing student_id column.']);
            exit;
        }

        $keyCol = $studentCol;
        $keyVal = $applicationNumber !== '' ? $applicationNumber : (string) $studentId;

        $stmt = $pdo->prepare("SELECT 1 FROM supervisor_students WHERE {$keyCol} = ? LIMIT 1");
        $stmt->execute([$keyVal]);
        $exists = (bool) $stmt->fetchColumn();

        $supervisorId = 0;
        $supervisorName = '';
        if (is_numeric($supervisorInput)) {
            $supervisorId = (int) $supervisorInput;
            $supStmt = $pdo->prepare("SELECT COALESCE(full_name, email) AS name, email FROM users WHERE user_id = ? LIMIT 1");
            $supStmt->execute([$supervisorId]);
            $supRow = $supStmt->fetch(PDO::FETCH_ASSOC);
            if ($supRow) {
                $supervisorName = (string) $supRow['name'];
            }
        } else {
            if (table_exists($pdo, 'supervisor_profiles')) {
                $supStmt = $pdo->prepare("SELECT full_name, email FROM supervisor_profiles WHERE supervisor_id = ? LIMIT 1");
                $supStmt->execute([$supervisorInput]);
                $supRow = $supStmt->fetch(PDO::FETCH_ASSOC);
                if ($supRow) {
                    $supervisorName = (string) $supRow['full_name'];
                    $supervisorEmail = (string) ($supRow['email'] ?? '');
                    if ($supervisorEmail !== '') {
                        $userStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
                        $userStmt->execute([$supervisorEmail]);
                        $supervisorId = (int) ($userStmt->fetchColumn() ?: 0);
                    }
                }
            }
        }

        $studentName = trim((string) ($_POST['student_name'] ?? ''));
        $studentEmail = trim((string) ($_POST['student_email'] ?? ''));
        $programme = trim((string) ($_POST['programme'] ?? ''));

        if ($exists) {
            $setParts = [];
            $params = [];
            if ($supervisorNameCol !== null && $supervisorName !== '') {
                $setParts[] = "{$supervisorNameCol} = ?";
                $params[] = $supervisorName;
            }
            if ($assignedAtCol !== null) {
                $setParts[] = "{$assignedAtCol} = NOW()";
            }
            if ($fullNameCol !== null && $studentName !== '') {
                $setParts[] = "{$fullNameCol} = ?";
                $params[] = $studentName;
            }
            if ($programmeCol !== null && $programme !== '') {
                $setParts[] = "{$programmeCol} = ?";
                $params[] = $programme;
            }
            if ($emailCol !== null && $studentEmail !== '') {
                $setParts[] = "{$emailCol} = ?";
                $params[] = $studentEmail;
            }
            if ($supervisorUserIdCol !== null) {
                $setParts[] = "{$supervisorUserIdCol} = ?";
                $params[] = $supervisorId;
            }
            if ($studentUserIdCol !== null && $studentId > 0) {
                $setParts[] = "{$studentUserIdCol} = ?";
                $params[] = $studentId;
            }
            if ($applicationIdCol !== null && $applicationId > 0) {
                $setParts[] = "{$applicationIdCol} = ?";
                $params[] = $applicationId;
            }
            if ($applicationNumberCol !== null && $applicationNumber !== '') {
                $setParts[] = "{$applicationNumberCol} = ?";
                $params[] = $applicationNumber;
            }
            if ($departmentIdCol !== null && $departmentId !== null && $departmentId !== '') {
                $setParts[] = "{$departmentIdCol} = ?";
                $params[] = (int) $departmentId;
            }
            if (empty($setParts)) {
                echo json_encode(['success' => true, 'message' => 'Supervisor assigned.']);
                exit;
            }
            $params[] = $keyVal;
            $stmt = $pdo->prepare("UPDATE supervisor_students SET " . implode(', ', $setParts) . " WHERE {$keyCol} = ?");
            $stmt->execute($params);
        } else {
            $insertCols = [$studentCol];
            $insertVals = [$keyVal];
            if ($supervisorNameCol !== null && $supervisorName !== '') {
                $insertCols[] = $supervisorNameCol;
                $insertVals[] = $supervisorName;
            }
            if ($fullNameCol !== null && $studentName !== '') {
                $insertCols[] = $fullNameCol;
                $insertVals[] = $studentName;
            }
            if ($programmeCol !== null && $programme !== '') {
                $insertCols[] = $programmeCol;
                $insertVals[] = $programme;
            }
            if ($emailCol !== null && $studentEmail !== '') {
                $insertCols[] = $emailCol;
                $insertVals[] = $studentEmail;
            }
            if ($supervisorUserIdCol !== null) {
                $insertCols[] = $supervisorUserIdCol;
                $insertVals[] = $supervisorId;
            }
            if ($studentUserIdCol !== null && $studentId > 0) {
                $insertCols[] = $studentUserIdCol;
                $insertVals[] = $studentId;
            }
            if ($applicationIdCol !== null && $applicationId > 0) {
                $insertCols[] = $applicationIdCol;
                $insertVals[] = $applicationId;
            }
            if ($applicationNumberCol !== null && $applicationNumber !== '') {
                $insertCols[] = $applicationNumberCol;
                $insertVals[] = $applicationNumber;
            }
            if ($departmentIdCol !== null && $departmentId !== null && $departmentId !== '') {
                $insertCols[] = $departmentIdCol;
                $insertVals[] = (int) $departmentId;
            }
            if ($assignedAtCol !== null) {
                $insertCols[] = $assignedAtCol;
            }
            $placeholders = array_fill(0, count($insertVals), '?');
            if ($assignedAtCol !== null) {
                $placeholders[] = 'NOW()';
            }
            $stmt = $pdo->prepare("INSERT INTO supervisor_students (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")");
            $stmt->execute($insertVals);
        }

        echo json_encode(['success' => true, 'message' => 'Supervisor assigned.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
