<?php
session_start();
require_once __DIR__ . '/../../admin/includes/db.php';
require_once __DIR__ . '/../../../includes/status_engine.php';

header('Content-Type: application/json');

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function dept_id_from_session(): ?int {
    if (!isset($_SESSION['department_id']) && isset($_SESSION['user_id'])) {
        try {
            require_once __DIR__ . '/../../admin/includes/db.php';
            global $pdo;
            $stmt = $pdo->prepare("SELECT department_id FROM users WHERE user_id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $deptId = $stmt->fetchColumn();
            if ($deptId !== false && $deptId !== null) {
                $_SESSION['department_id'] = (int) $deptId;
            }
        } catch (Throwable $e) {}
    }
    return $_SESSION['department_id'] ?? $_SESSION['dept_id'] ?? null;
}

function progress_from_stage(?string $stage): int {
    return match ($stage) {
        'PROPOSAL_SUBMITTED' => 30,
        'PROPOSAL_APPROVED' => 60,
        'REPORT_SUBMITTED' => 80,
        'REPORT_REVIEWED', 'PROJECT_COMPLETED' => 100,
        default => 10,
    };
}

if ($action === 'list') {
    $deptId = dept_id_from_session();
    if (!$deptId) {
        echo json_encode(['success' => false, 'message' => 'Department not assigned.']);
        exit;
    }

    $stmt = $pdo->prepare("\
        SELECT a.application_id, a.application_number, a.status,
               CONCAT(pd.first_name, ' ', pd.surname) AS full_name,
               acc.email,
               c.course_title AS programme,
               s.full_name AS supervisor_name,
               p.current_stage,
               p.updated_at,
               rd.research_area
        FROM applications a
        JOIN personal_details pd ON a.application_id = pd.application_id
        LEFT JOIN applicant_accounts acc ON a.user_id = acc.user_id
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
        LEFT JOIN courses c ON pc.course = c.course_id
        LEFT JOIN supervisor_assignments sa ON sa.application_id = a.application_id AND sa.status = 'Assigned'
        LEFT JOIN supervisors s ON sa.supervisor_id = s.supervisor_id
        LEFT JOIN projects p ON p.application_id = a.application_id
        LEFT JOIN research_details rd ON rd.application_id = a.application_id
        WHERE (a.department_id = ? OR pc.department = ?) AND a.status = 'Admitted'
        ORDER BY pd.surname ASC
    ");
    $stmt->execute([$deptId, $deptId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($row) {
        $progress = progress_from_stage($row['current_stage'] ?? null);
        $status = ($row['current_stage'] === 'PROJECT_COMPLETED') ? 'Graduated' : 'Active';
        return [
            'student_id' => $row['application_number'],
            'full_name' => $row['full_name'],
            'email' => $row['email'],
            'programme' => $row['programme'] ?? 'N/A',
            'supervisor_name' => $row['supervisor_name'] ?? '-',
            'status' => $status,
            'progress_pct' => $progress,
            'last_activity' => $row['updated_at'] ? date('Y-m-d', strtotime($row['updated_at'])) : '-',
            'research_topic' => $row['research_area'] ?? '',
            'notes' => ''
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

if ($action === 'save') {
    $studentId = $_POST['student_id'] ?? '';
    $supervisorName = $_POST['supervisor_name'] ?? '';
    $topic = $_POST['research_topic'] ?? null;

    $stmt = $pdo->prepare("SELECT application_id, user_id, application_number FROM applications WHERE application_number = ? LIMIT 1");
    $stmt->execute([$studentId]);
    $appRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$appRow) {
        echo json_encode(['success' => false, 'message' => 'Student not found.']);
        exit;
    }
    $appId = (int)$appRow['application_id'];
    $studentUserId = (int)$appRow['user_id'];
    $appNo = $appRow['application_number'];

    if ($topic) {
        $pdo->prepare("UPDATE research_details SET research_area = ? WHERE application_id = ?")->execute([$topic, $appId]);
    }

    if ($supervisorName) {
        $deptId = dept_id_from_session();
        $stmt = $pdo->prepare("SELECT supervisor_id, department_id, user_id FROM supervisors WHERE full_name = ? LIMIT 1");
        $stmt->execute([$supervisorName]);
        $supRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($supRow) {
            $supId = (int)$supRow['supervisor_id'];
            $supDeptId = $supRow['department_id'] !== null ? (int)$supRow['department_id'] : null;
            $supUserId = $supRow['user_id'] !== null ? (int)$supRow['user_id'] : null;

            if ($deptId && $supDeptId !== null && $supDeptId !== $deptId) {
                echo json_encode(['success' => false, 'message' => 'Cannot assign supervisor from outside your department.']);
                exit;
            }

            $pdo->prepare("INSERT INTO supervisor_assignments (supervisor_id, application_id, student_id, assigned_by, assigned_at, status)
                VALUES (?, ?, ?, ?, NOW(), 'Assigned')
                ON DUPLICATE KEY UPDATE supervisor_id = VALUES(supervisor_id), assigned_at = NOW(), status = 'Assigned'")
                ->execute([$supId, $appId, $appId, $_SESSION['user_id'] ?? null]);
            
            $pdo->prepare("INSERT INTO projects (application_id, student_id, supervisor_id, topic, current_stage) VALUES (?, ?, ?, ?, 'PROJECT_ACTIVE')
                ON DUPLICATE KEY UPDATE supervisor_id = VALUES(supervisor_id), topic = COALESCE(VALUES(topic), topic)")
                ->execute([$appId, $appId, $supId, $topic]);

            // Update student_profiles table
            $pdo->prepare("
                UPDATE student_profiles SET 
                    assigned_supervisor_user_id = ?,
                    assignment_date = NOW(),
                    assigned_by_user_id = ?,
                    supervisor_status = 'Assigned'
                WHERE student_id = ? OR email = (SELECT email FROM users WHERE user_id = ? LIMIT 1)
            ")->execute([$supUserId, $_SESSION['user_id'] ?? null, $appNo, $studentUserId]);
        }
    }

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'status') {
    $studentId = $_POST['student_id'] ?? '';
    $status = $_POST['status'] ?? 'Active';
    $stmt = $pdo->prepare("SELECT application_id FROM applications WHERE application_number = ? LIMIT 1");
    $stmt->execute([$studentId]);
    $appId = $stmt->fetchColumn();
    if (!$appId) {
        echo json_encode(['success' => false, 'message' => 'Student not found.']);
        exit;
    }
    if ($status === 'Graduated') {
        $pdo->prepare("UPDATE projects SET current_stage = 'PROJECT_COMPLETED', updated_at = NOW() WHERE application_id = ?")->execute([$appId]);
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'bulk') {
    $ids = $_POST['student_ids'] ?? [];
    if (!is_array($ids) || count($ids) === 0) {
        echo json_encode(['success' => false, 'message' => 'No students selected.']);
        exit;
    }
    $status = $_POST['status'] ?? null;
    $supervisorName = $_POST['supervisor_name'] ?? null;

    $supId = null;
    $supUserId = null;
    if ($supervisorName) {
        $deptId = dept_id_from_session();
        $stmt = $pdo->prepare("SELECT supervisor_id, department_id, user_id FROM supervisors WHERE full_name = ? LIMIT 1");
        $stmt->execute([$supervisorName]);
        $supRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($supRow) {
            $supId = (int)$supRow['supervisor_id'];
            $supDeptId = $supRow['department_id'] !== null ? (int)$supRow['department_id'] : null;
            $supUserId = $supRow['user_id'] !== null ? (int)$supRow['user_id'] : null;

            if ($deptId && $supDeptId !== null && $supDeptId !== $deptId) {
                echo json_encode(['success' => false, 'message' => 'Cannot assign supervisor from outside your department.']);
                exit;
            }
        }
    }

    foreach ($ids as $studentId) {
        $stmt = $pdo->prepare("SELECT application_id, user_id, application_number FROM applications WHERE application_number = ? LIMIT 1");
        $stmt->execute([$studentId]);
        $appRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$appRow) continue;
        $appId = (int)$appRow['application_id'];
        $studentUserId = (int)$appRow['user_id'];
        $appNo = $appRow['application_number'];

        if ($status && $status === 'Graduated') {
            $pdo->prepare("UPDATE projects SET current_stage = 'PROJECT_COMPLETED', updated_at = NOW() WHERE application_id = ?")->execute([$appId]);
        }
        if ($supId) {
            $pdo->prepare("INSERT INTO supervisor_assignments (supervisor_id, application_id, student_id, assigned_by, assigned_at, status)
                VALUES (?, ?, ?, ?, NOW(), 'Assigned')
                ON DUPLICATE KEY UPDATE supervisor_id = VALUES(supervisor_id), assigned_at = NOW(), status = 'Assigned'")
                ->execute([$supId, $appId, $appId, $_SESSION['user_id'] ?? null]);
            $pdo->prepare("INSERT INTO projects (application_id, student_id, supervisor_id, current_stage) VALUES (?, ?, ?, 'PROJECT_ACTIVE')
                ON DUPLICATE KEY UPDATE supervisor_id = VALUES(supervisor_id)")
                ->execute([$appId, $appId, $supId]);

            // Update student_profiles table
            $pdo->prepare("
                UPDATE student_profiles SET 
                    assigned_supervisor_user_id = ?,
                    assignment_date = NOW(),
                    assigned_by_user_id = ?,
                    supervisor_status = 'Assigned'
                WHERE student_id = ? OR email = (SELECT email FROM users WHERE user_id = ? LIMIT 1)
            ")->execute([$supUserId, $_SESSION['user_id'] ?? null, $appNo, $studentUserId]);
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'message') {
    $studentId = $_POST['student_id'] ?? '';
    $message = trim($_POST['message'] ?? '');
    if ($studentId === '' || $message === '') {
        echo json_encode(['success' => false, 'message' => 'Missing message details.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT user_id FROM applications WHERE application_number = ? LIMIT 1");
    $stmt->execute([$studentId]);
    $receiverId = $stmt->fetchColumn();

    $threadId = 'student_' . $studentId;
    $stmt = $pdo->prepare("INSERT INTO messages (thread_id, sender_id, receiver_id, body) VALUES (?, ?, ?, ?)");
    $stmt->execute([$threadId, $_SESSION['user_id'] ?? 0, $receiverId, $message]);

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unsupported action.']);
