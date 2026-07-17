<?php
session_start();
require_once __DIR__ . '/../../admin/includes/db.php';
require_once __DIR__ . '/../../../includes/status_engine.php';
require_once __DIR__ . '/../../../includes/permissions.php';

header('Content-Type: application/json');

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function status_label(string $status): string {
    $map = workflow_status_map();
    return $map[$status]['label'] ?? $status;
}

function resolve_reviewer_id(PDO $pdo, ?string $nameOrEmail): ?int {
    if (!$nameOrEmail) return null;
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? OR full_name = ? LIMIT 1");
    $stmt->execute([$nameOrEmail, $nameOrEmail]);
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}

function fetch_department_id(): ?int {
    if (isset($_SESSION['user_id'])) {
        try {
            require_once __DIR__ . '/../../admin/includes/db.php';
            global $pdo;
            $stmt = $pdo->prepare("SELECT department_id FROM users WHERE user_id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $deptId = $stmt->fetchColumn();
            if ($deptId !== false && $deptId !== null) {
                $_SESSION['department_id'] = (int) $deptId;
                return (int) $deptId;
            }
        } catch (Throwable $e) {}
    }
    return $_SESSION['department_id'] ?? $_SESSION['dept_id'] ?? null;
}

function assign_single_supervisor(PDO $pdo, int $appId, array $sup, array &$notifications) {
    $supId = $sup['supervisor_id'];
    
    // Query student details
    $stmt = $pdo->prepare("
        SELECT a.application_number, pd.first_name, pd.surname, c.course_title, a.user_id, u.email AS student_email
        FROM applications a
        LEFT JOIN personal_details pd ON a.application_id = pd.application_id
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
        LEFT JOIN courses c ON pc.course = c.course_id
        LEFT JOIN users u ON a.user_id = u.user_id
        WHERE a.application_id = ? LIMIT 1
    ");
    $stmt->execute([$appId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return;
    
    $studentName = trim(($row['first_name'] ?? '') . ' ' . ($row['surname'] ?? ''));
    $appNo = $row['application_number'] ?? 'N/A';
    $courseTitle = $row['course_title'] ?? 'N/A';
    $studentUserId = (int)($row['user_id'] ?? 0);
    $studentEmail = $row['student_email'] ?? '';
    
    // Insert to supervisor_assignments
    $pdo->prepare("INSERT INTO supervisor_assignments (supervisor_id, application_id, student_id, assigned_by, assigned_at, status)
        VALUES (?, ?, ?, ?, NOW(), 'Assigned')
        ON DUPLICATE KEY UPDATE supervisor_id = VALUES(supervisor_id), assigned_at = NOW(), status = 'Assigned'")
        ->execute([$supId, $appId, $appId, $_SESSION['user_id'] ?? null]);
        
    // Insert to projects if exists
    $pdo->prepare("INSERT INTO projects (application_id, student_id, supervisor_id, topic, current_stage)
        VALUES (?, ?, ?, 'Thesis Topic Awaiting', 'PROJECT_ACTIVE')
        ON DUPLICATE KEY UPDATE supervisor_id = VALUES(supervisor_id)")
        ->execute([$appId, $appId, $supId]);
        
    // Synchronize supervisor_students table
    try {
        $supUserId = isset($sup['user_id']) ? (int)$sup['user_id'] : null;
        $hasSupUserIdCol = false;
        $checkCol = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'supervisor_students' AND column_name = 'supervisor_user_id' LIMIT 1");
        $checkCol->execute();
        $hasSupUserIdCol = (bool) $checkCol->fetchColumn();

        if ($hasSupUserIdCol) {
            $pdo->prepare("
                INSERT INTO supervisor_students 
                    (student_id, student_user_id, full_name, programme, current_chapter, status, email, progress_pct, supervisor_name, supervisor_user_id, updated_at)
                VALUES 
                    (?, ?, ?, ?, 'Chapter 1', 'Pending Review', ?, 0, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    supervisor_name = VALUES(supervisor_name),
                    supervisor_user_id = VALUES(supervisor_user_id),
                    updated_at = NOW()
            ")->execute([$appNo, $studentUserId, $studentName, $courseTitle, $studentEmail, $sup['full_name'], $supUserId]);
        } else {
            $pdo->prepare("
                INSERT INTO supervisor_students 
                    (student_id, student_user_id, full_name, programme, current_chapter, status, email, progress_pct, supervisor_name, updated_at)
                VALUES 
                    (?, ?, ?, ?, 'Chapter 1', 'Pending Review', ?, 0, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    supervisor_name = VALUES(supervisor_name),
                    updated_at = NOW()
            ")->execute([$appNo, $studentUserId, $studentName, $courseTitle, $studentEmail, $sup['full_name']]);
        }
    } catch (Throwable $e) {
        error_log("Failed to sync supervisor_students: " . $e->getMessage());
    }

    // Add to notifications array
    $supEmail = $sup['email'];
    if ($supEmail) {
        if (!isset($notifications[$supEmail])) {
            $notifications[$supEmail] = [
                'name' => $sup['full_name'],
                'students' => []
            ];
        }
        $notifications[$supEmail]['students'][] = [
            'name' => $studentName,
            'app_no' => $appNo,
            'course' => $courseTitle
        ];
    }
}

function send_grouped_supervisor_emails(PDO $pdo, array $notifications) {
    require_once __DIR__ . '/../../includes/mailer.php';
    $settings = $pdo->query("SELECT * FROM system_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    
    foreach ($notifications as $email => $info) {
        $supName = $info['name'];
        $studentsList = "";
        foreach ($info['students'] as $st) {
            $studentsList .= "<li><strong>{$st['name']}</strong> (App No: {$st['app_no']}) - Programme: {$st['course']}</li>";
        }
        
        $subject = "New Student Assignments - JOSTUM PG School";
        $body = "Dear {$supName},<br><br>";
        $body .= "The Department has assigned the following postgraduate students to you for supervision:<br><br>";
        $body .= "<ul>{$studentsList}</ul><br>";
        $body .= "Please log in to your Supervisor Dashboard to review their details.<br><br>";
        $body .= "Regards,<br>IPESS FUAM Portal";
        
        portal_send_mail($email, $supName, $subject, $body, "Postgraduate student supervision assignment");
    }
}

if ($action === 'list') {
    $deptId = fetch_department_id();
    if (!$deptId) {
        echo json_encode(['success' => false, 'message' => 'Department not assigned.']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT a.application_id, a.application_number, a.current_status, a.submitted_at,
               pd.first_name, pd.surname,
               c.course_title,
               u.full_name AS reviewer_name,
               sv.full_name AS supervisor_name
        FROM applications a
        LEFT JOIN personal_details pd ON a.application_id = pd.application_id
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
        LEFT JOIN courses c ON pc.course = c.course_id
        LEFT JOIN users u ON a.reviewer_id = u.user_id
        LEFT JOIN supervisor_assignments sa ON sa.application_id = a.application_id AND sa.status = 'Assigned'
        LEFT JOIN supervisors sv ON sa.supervisor_id = sv.supervisor_id
        WHERE (a.department_id = ? OR pc.department = ?) AND a.status != 'Draft'
        ORDER BY a.submitted_at DESC
    ");
    $stmt->execute([$deptId, $deptId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($row) {
        return [
            'app_id' => $row['application_id'],
            'app_code' => $row['application_number'] ?? 'N/A',
            'applicant_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['surname'] ?? '')),
            'programme' => $row['course_title'] ?? 'N/A',
            'status' => $row['current_status'] ?? 'SUBMITTED',
            'status_label' => status_label($row['current_status'] ?? 'SUBMITTED'),
            'reviewer_name' => $row['reviewer_name'] ?? null,
            'supervisor_name' => $row['supervisor_name'] ?? null,
            'submitted_date' => $row['submitted_at'] ? date('Y-m-d', strtotime($row['submitted_at'])) : null,
            'priority' => 'Normal',
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

if ($action === 'view') {
    $appCode = $_GET['app_code'] ?? '';
    if ($appCode === '') {
        echo json_encode(['success' => false, 'message' => 'Missing application code.']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT a.application_number, a.current_status, a.submitted_at,
               pd.first_name, pd.surname,
               pc.cgpa, c.course_title,
               u.full_name AS reviewer_name
        FROM applications a
        LEFT JOIN personal_details pd ON a.application_id = pd.application_id
        LEFT JOIN higher_education pc ON a.application_id = pc.application_id
        LEFT JOIN programme_choices pg ON a.application_id = pg.application_id
        LEFT JOIN courses c ON pg.course = c.course_id
        LEFT JOIN users u ON a.reviewer_id = u.user_id
        WHERE a.application_number = ?
        LIMIT 1
    ");
    $stmt->execute([$appCode]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Application not found.']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $row]);
    exit;
}

if ($action === 'verify') {
    $appIds = $_POST['application_ids'] ?? [];
    if (empty($appIds)) {
        echo json_encode(['success' => false, 'message' => 'No applications selected.']);
        exit;
    }
    foreach ($appIds as $appId) {
        update_application_status($pdo, (int)$appId, 'HOD_VERIFIED', [
            'actor_id' => $_SESSION['user_id'] ?? null,
            'actor_role' => $_SESSION['role'] ?? 'HOD',
            'note' => 'Verified by HOD.'
        ]);
    }
    echo json_encode(['success' => true, 'message' => 'Applications verified successfully.']);
    exit;
}

if ($action === 'assign_supervisors') {
    $appIds = $_POST['application_ids'] ?? [];
    $supervisorId = isset($_POST['supervisor_id']) ? trim((string)$_POST['supervisor_id']) : '';
    $autoDistribute = isset($_POST['auto_distribute']) ? (int)$_POST['auto_distribute'] : 0;
    
    if (empty($appIds)) {
        echo json_encode(['success' => false, 'message' => 'No applications selected.']);
        exit;
    }
    
    $deptId = fetch_department_id();
    if (!$deptId) {
        echo json_encode(['success' => false, 'message' => 'Department not assigned to you.']);
        exit;
    }
    
    // Fetch active supervisors
    $supStmt = $pdo->prepare("SELECT supervisor_id, full_name, email, user_id FROM supervisors WHERE department_id = ? AND status = 'Active'");
    $supStmt->execute([$deptId]);
    $supervisors = $supStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($supervisors)) {
        echo json_encode(['success' => false, 'message' => 'No active supervisors found in your department.']);
        exit;
    }
    
    $notifications = [];
    
    if ($autoDistribute) {
        // Equal distribution round-robin
        $supCount = count($supervisors);
        foreach ($appIds as $index => $appId) {
            $appId = (int)$appId;
            $sup = $supervisors[$index % $supCount];
            assign_single_supervisor($pdo, $appId, $sup, $notifications);
        }
    } else {
        // Individual / Group assign to one supervisor
        $selectedSup = null;
        foreach ($supervisors as $s) {
            if ($s['supervisor_id'] === $supervisorId) {
                $selectedSup = $s;
                break;
            }
        }
        if (!$selectedSup) {
            echo json_encode(['success' => false, 'message' => 'Selected supervisor is invalid or inactive.']);
            exit;
        }
        foreach ($appIds as $appId) {
            assign_single_supervisor($pdo, (int)$appId, $selectedSup, $notifications);
        }
    }
    
    // Send consolidated emails
    send_grouped_supervisor_emails($pdo, $notifications);
    
    echo json_encode(['success' => true, 'message' => 'Supervisors assigned successfully.']);
    exit;
}

if ($action === 'advance_to_college') {
    $appIds = $_POST['application_ids'] ?? [];
    if (empty($appIds)) {
        echo json_encode(['success' => false, 'message' => 'No applications selected.']);
        exit;
    }
    // Validate all have supervisor assigned
    foreach ($appIds as $appId) {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM supervisor_assignments WHERE application_id = ? AND status = 'Assigned'");
        $checkStmt->execute([$appId]);
        if ((int)$checkStmt->fetchColumn() === 0) {
            echo json_encode(['success' => false, 'message' => 'One or more selected students do not have a supervisor assigned yet.']);
            exit;
        }
    }
    foreach ($appIds as $appId) {
        update_application_status($pdo, (int)$appId, 'COLLEGE_PENDING', [
            'actor_id' => $_SESSION['user_id'] ?? null,
            'actor_role' => $_SESSION['role'] ?? 'HOD',
            'note' => 'Advanced to College Review.'
        ]);
        try {
            $pdo->prepare("UPDATE application_progress SET stage_status = 'Completed', stage_updated_at = NOW() WHERE application_id = ? AND stage = 'Departmental Review'")->execute([$appId]);
            $pdo->prepare("UPDATE application_progress SET stage_status = 'In Progress', stage_updated_at = NOW() WHERE application_id = ? AND stage = 'Faculty Review'")->execute([$appId]);
        } catch (Throwable $e) {}
    }
    echo json_encode(['success' => true, 'message' => 'Advanced to College Review successfully.']);
    exit;
}

if ($action === 'update' || $action === 'bulk') {
    $items = [];
    if ($action === 'bulk') {
        $items = json_decode($_POST['items'] ?? '[]', true);
        if (!is_array($items)) {
            echo json_encode(['success' => false, 'message' => 'Invalid payload.']);
            exit;
        }
    } else {
        $items[] = [
            'app_code' => $_POST['app_code'] ?? '',
            'status' => $_POST['status'] ?? '',
            'reviewer_name' => $_POST['reviewer_name'] ?? null,
        ];
    }

    foreach ($items as $item) {
        if (empty($item['app_code'])) {
            continue;
        }

        $stmt = $pdo->prepare("SELECT application_id, user_id FROM applications WHERE application_number = ? LIMIT 1");
        $stmt->execute([$item['app_code']]);
        $appRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$appRow) {
            continue;
        }

        $appId = (int) $appRow['application_id'];
        require_once __DIR__ . '/../../../classes/ApplicationProgressManager.php';
        $progManager = new ApplicationProgressManager($pdo);
        $missingStage = null;
        // Bypass progress constraint check to allow Departmental review at any time
        /*
        if (!$progManager->canAdvanceToStage($appId, ApplicationProgressManager::STAGE_DEPT_REVIEW, $missingStage)) {
            echo json_encode(['success' => false, 'message' => "Cannot perform Departmental Review before the '{$missingStage}' stage is completed."]);
            exit;
        }
        */

        $newStatus = 'UNDER_DEPT_REVIEW';
        $label = strtolower($item['status'] ?? '');
        if (str_contains($label, 'approved')) {
            $newStatus = 'DEPT_APPROVED';
        } elseif (str_contains($label, 'rejected')) {
            $newStatus = 'DEPT_REJECTED';
        } elseif (str_contains($label, 'needs')) {
            $newStatus = 'ACTION_REQUIRED_DOCS';
        } elseif (str_contains($label, 'reviewer')) {
            $newStatus = 'REVIEWER_ASSIGNED';
        } elseif (str_contains($label, 'final') || str_contains($label, 'escalated')) {
            $newStatus = 'ADMIN_FINAL_REVIEW';
        }

        $context = [
            'actor_id' => $_SESSION['user_id'] ?? null,
            'actor_role' => $_SESSION['role'] ?? 'DEPARTMENT_ADMIN',
        ];

        if (!empty($item['reviewer_name'])) {
            $reviewerId = resolve_reviewer_id($pdo, $item['reviewer_name']);
            if ($reviewerId) {
                $context['assigned_reviewer_id'] = $reviewerId;
            }
        }

        update_application_status($pdo, (int) $appRow['application_id'], $newStatus, $context);
    }

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unsupported action.']);
