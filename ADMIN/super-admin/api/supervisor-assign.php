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
        if (table_exists($pdo, 'departments')) {
            $rows = $pdo->query("SELECT dept_id AS id, dept_name AS name FROM departments ORDER BY dept_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $rows = $pdo->query("SELECT DISTINCT department AS id, department AS name FROM programme_choices ORDER BY department ASC")->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    if ($action === 'supervisors') {
        $departmentId = $_GET['department_id'] ?? '';
        $rows = [];

        if (table_exists($pdo, 'supervisor_profiles')) {
            if ($departmentId !== '') {
                $stmt = $pdo->prepare("
                    SELECT s.supervisor_id AS id,
                           s.full_name AS name,
                           s.email
                    FROM supervisor_profiles s
                    WHERE s.status = 'Active'
                      AND (s.department_id = ? OR s.department_id IS NULL OR s.department_id = '')
                    ORDER BY s.full_name ASC
                ");
                $stmt->execute([$departmentId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT s.supervisor_id AS id,
                           s.full_name AS name,
                           s.email
                    FROM supervisor_profiles s
                    WHERE s.status = 'Active'
                    ORDER BY s.full_name ASC
                ");
                $stmt->execute();
            }
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif (table_exists($pdo, 'supervisors')) {
            if ($departmentId !== '') {
                $stmt = $pdo->prepare("
                    SELECT s.supervisor_id AS id,
                           COALESCE(s.full_name, u.full_name, u.email) AS name,
                           u.email
                    FROM supervisors s
                    LEFT JOIN users u ON s.user_id = u.user_id
                    WHERE s.status = 'Active'
                      AND (s.department_id = ? OR s.department_id IS NULL)
                    ORDER BY s.full_name ASC
                ");
                $stmt->execute([$departmentId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT s.supervisor_id AS id,
                           COALESCE(s.full_name, u.full_name, u.email) AS name,
                           u.email
                    FROM supervisors s
                    LEFT JOIN users u ON s.user_id = u.user_id
                    WHERE s.status = 'Active'
                    ORDER BY s.full_name ASC
                ");
                $stmt->execute();
            }
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // No supervisors table — use users table with role_id for SUPERVISOR (role_id=4)
            $supervisorRoleId = 4;
            if (table_exists($pdo, 'roles')) {
                $roleStmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_key = 'SUPERVISOR' OR role_name = 'Supervisor' LIMIT 1");
                $roleStmt->execute();
                $supervisorRoleId = (int) ($roleStmt->fetchColumn() ?: 4);
            }
            // Return all supervisors regardless of department so dept filtering doesn't hide valid supervisors
            $stmt = $pdo->prepare("
                SELECT u.user_id AS id,
                       COALESCE(u.full_name, u.email) AS name,
                       u.email
                FROM users u
                WHERE u.role_id = ?
                  AND (u.account_status IS NULL OR u.account_status = 'active')
                ORDER BY u.full_name ASC, u.email ASC
            ");
            $stmt->execute([$supervisorRoleId]);
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
            ? "(a.status = 'Admitted' OR a.current_status = 'ADMISSION_APPROVED')"
            : "a.status = 'Admitted'";

        $deptName = '';
        if (table_exists($pdo, 'departments')) {
            $stmt = $pdo->prepare("SELECT dept_name FROM departments WHERE dept_id = ? LIMIT 1");
            $stmt->execute([$departmentId]);
            $deptName = (string) ($stmt->fetchColumn() ?: '');
        }

        $pcDeptType = get_column_type($pdo, 'programme_choices', 'department');
        $pcDeptIsNumeric = in_array($pcDeptType, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'numeric'], true);
        $deptFilterValue = $pcDeptIsNumeric ? $departmentId : ($deptName !== '' ? $deptName : $departmentId);

        $deptJoin = table_exists($pdo, 'departments')
            ? "LEFT JOIN departments d ON pc.department = d.dept_id"
            : "LEFT JOIN departments d ON 1=0";

        // Determine admitted status filter without relying on outer alias inside subquery
        $admitInnerWhere = $hasCurrentStatus
            ? "(status = 'Admitted' OR current_status = 'ADMISSION_APPROVED')"
            : "status = 'Admitted'";

        $studentSql = "
            SELECT a.application_id,
                   a.user_id AS student_id,
                   a.application_number,
                   a.status,
                   p.first_name,
                   p.surname,
                   u.email,
                   pc.department,
                   d.dept_name
            FROM applications a
            JOIN (
                SELECT user_id, MAX(application_id) AS application_id
                FROM applications
                WHERE {$admitInnerWhere}
                GROUP BY user_id
            ) latest ON latest.application_id = a.application_id
            JOIN programme_choices pc ON pc.application_id = a.application_id
            LEFT JOIN personal_details p ON p.application_id = a.application_id
            JOIN users u ON u.user_id = a.user_id
            {$deptJoin}
            WHERE pc.department = ?
            ORDER BY p.surname ASC, p.first_name ASC
        ";
        $stmt = $pdo->prepare($studentSql);
        $stmt->execute([$deptFilterValue]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($students)) {
            $studentSql = "
                SELECT a.application_id,
                       a.user_id AS student_id,
                       a.application_number,
                       a.status,
                       p.first_name,
                       p.surname,
                       u.email,
                       pc.department,
                       d.dept_name
                FROM applications a
                JOIN (
                    SELECT user_id, MAX(application_id) AS application_id
                    FROM applications
                    GROUP BY user_id
                ) latest ON latest.application_id = a.application_id
                JOIN programme_choices pc ON pc.application_id = a.application_id
                LEFT JOIN personal_details p ON p.application_id = a.application_id
                JOIN users u ON u.user_id = a.user_id
                {$deptJoin}
                WHERE pc.department = ?
                ORDER BY p.surname ASC, p.first_name ASC
            ";
            $stmt = $pdo->prepare($studentSql);
            $stmt->execute([$deptFilterValue]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $assignmentMap = [];
        if (table_exists($pdo, 'supervisor_students') && !empty($students)) {
            $cols = get_columns($pdo, 'supervisor_students');
            $supervisorCol = in_array('supervisor_id', $cols, true) ? 'supervisor_id' : (in_array('supervisor_user_id', $cols, true) ? 'supervisor_user_id' : null);
            $studentCol = in_array('student_id', $cols, true) ? 'student_id' : (in_array('user_id', $cols, true) ? 'user_id' : null);
            $applicationCol = in_array('application_id', $cols, true) ? 'application_id' : null;
            $keyCol = $applicationCol ?: $studentCol;

            if ($supervisorCol && $keyCol) {
                $keys = array_map(static function ($row) use ($keyCol) {
                    return $keyCol === 'application_id' ? (int) $row['application_id'] : (int) $row['student_id'];
                }, $students);
                $keys = array_values(array_filter(array_unique($keys)));
                if (!empty($keys)) {
                    $placeholders = implode(',', array_fill(0, count($keys), '?'));
                    $stmt = $pdo->prepare("SELECT {$keyCol} AS ref_id, {$supervisorCol} AS supervisor_val FROM supervisor_students WHERE {$keyCol} IN ({$placeholders})");
                    $stmt->execute($keys);
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $val = trim((string) $row['supervisor_val']);
                        if ($val !== '' && is_numeric($val) && table_exists($pdo, 'supervisor_profiles')) {
                            $emailStmt = $pdo->prepare("SELECT email FROM users WHERE user_id = ? LIMIT 1");
                            $emailStmt->execute([(int)$val]);
                            $email = $emailStmt->fetchColumn();
                            if ($email) {
                                $profStmt = $pdo->prepare("SELECT supervisor_id FROM supervisor_profiles WHERE email = ? LIMIT 1");
                                $profStmt->execute([$email]);
                                $profId = $profStmt->fetchColumn();
                                if ($profId) {
                                    $val = $profId;
                                }
                            }
                        }
                        $assignmentMap[(int) $row['ref_id']] = $val;
                    }
                }
            }
        }

        if (!empty($assignmentMap)) {
            foreach ($students as &$student) {
                $refId = isset($assignmentMap[(int) $student['application_id']]) ? (int) $student['application_id'] : (int) $student['student_id'];
                $student['assigned_supervisor_id'] = $assignmentMap[$refId] ?? null;
            }
            unset($student);
        }

        echo json_encode(['success' => true, 'data' => $students]);
        exit;
    }

    if ($action === 'assign') {
        $studentId = (int) ($_POST['student_id'] ?? 0);
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $supervisorInput = trim((string) ($_POST['supervisor_id'] ?? ''));
        $departmentId = $_POST['department_id'] ?? null;

        if ($studentId <= 0 || $supervisorInput === '') {
            echo json_encode(['success' => false, 'message' => 'Missing student or supervisor.']);
            exit;
        }

        if (!table_exists($pdo, 'supervisor_students')) {
            echo json_encode(['success' => false, 'message' => 'supervisor_students table not found.']);
            exit;
        }

        // 1. Resolve supervisor details
        $supervisorId = 0;
        $supervisorName = '';
        if (is_numeric($supervisorInput)) {
            $supervisorId = (int) $supervisorInput;
            $supStmt = $pdo->prepare("SELECT COALESCE(full_name, email) AS name FROM users WHERE user_id = ? LIMIT 1");
            $supStmt->execute([$supervisorId]);
            $supervisorName = (string) ($supStmt->fetchColumn() ?: '');
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

        // 2. Fetch student details from database
        $studentName = '';
        $studentEmail = '';
        $programme = '';
        $applicationNumber = '';

        $stmt = $pdo->prepare("
            SELECT a.application_number, 
                   CONCAT(p.first_name, ' ', p.surname) AS full_name, 
                   COALESCE(u.email, aa.email) AS email,
                   COALESCE(dt.degree_name, pc.degree_type) AS degree_type,
                   COALESCE(c.course_title, pc.course) AS course
            FROM applications a
            LEFT JOIN personal_details p ON a.application_id = p.application_id
            LEFT JOIN users u ON a.user_id = u.user_id
            LEFT JOIN applicant_accounts aa ON aa.user_id = a.user_id
            LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
            LEFT JOIN degree_types dt ON pc.degree_type = dt.degree_id
            LEFT JOIN courses c ON pc.course = c.course_id
            WHERE a.application_id = ? LIMIT 1
        ");
        $stmt->execute([$applicationId]);
        $studentRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($studentRow) {
            $studentName = (string) $studentRow['full_name'];
            $studentEmail = (string) $studentRow['email'];
            $applicationNumber = (string) $studentRow['application_number'];
            $programme = trim(($studentRow['degree_type'] ?? '') . ' in ' . ($studentRow['course'] ?? ''));
        }

        // 3. Resolve columns
        $cols = get_columns($pdo, 'supervisor_students');
        $studentCol = in_array('student_id', $cols, true) ? 'student_id' : (in_array('user_id', $cols, true) ? 'user_id' : null);
        $supervisorCol = in_array('supervisor_id', $cols, true) ? 'supervisor_id' : (in_array('supervisor_user_id', $cols, true) ? 'supervisor_user_id' : null);
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

        if ($exists) {
            $setParts = [];
            $params = [];
            if ($supervisorCol !== null) {
                $setParts[] = "{$supervisorCol} = ?";
                $params[] = ($supervisorCol === 'supervisor_user_id') ? $supervisorId : $supervisorInput;
            }
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
            if (!empty($setParts)) {
                $params[] = $keyVal;
                $stmt = $pdo->prepare("UPDATE supervisor_students SET " . implode(', ', $setParts) . " WHERE {$keyCol} = ?");
                $stmt->execute($params);
            }
        } else {
            $insertCols = [$studentCol];
            $insertVals = [$keyVal];
            if ($supervisorCol !== null) {
                $insertCols[] = $supervisorCol;
                $insertVals[] = ($supervisorCol === 'supervisor_user_id') ? $supervisorId : $supervisorInput;
            }
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
