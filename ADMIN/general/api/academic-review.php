<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once '../includes/db.php';
require_once __DIR__ . '/../../../includes/status_engine.php';
require_once __DIR__ . '/../../../includes/permissions.php';

header('Content-Type: application/json');

if (!has_permission('department_review')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Academic Review duty not assigned.']);
    exit;
}

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

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userDeptId = null;
if ($userId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT department_id FROM users WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $userDeptId = $stmt->fetchColumn();
        if ($userDeptId) {
            $userDeptId = (int) $userDeptId;
        } else {
            $userDeptId = null;
        }
    } catch (Throwable $e) {}
}

if ($action === 'list') {
    if ($userDeptId === null) {
        // If they have all access (no department constraint)
        $stmt = $pdo->prepare("
            SELECT a.application_id, a.application_number, a.current_status, a.submitted_at,
                   pd.first_name, pd.surname,
                   c.course_title,
                   u.full_name AS reviewer_name
            FROM applications a
            LEFT JOIN personal_details pd ON a.application_id = pd.application_id
            LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
            LEFT JOIN courses c ON pc.course = c.course_id
            LEFT JOIN users u ON a.assigned_reviewer_id = u.user_id
            WHERE a.status != 'Draft'
            ORDER BY a.submitted_at DESC
        ");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("
            SELECT a.application_id, a.application_number, a.current_status, a.submitted_at,
                   pd.first_name, pd.surname,
                   c.course_title,
                   u.full_name AS reviewer_name
            FROM applications a
            LEFT JOIN personal_details pd ON a.application_id = pd.application_id
            LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
            LEFT JOIN courses c ON pc.course = c.course_id
            LEFT JOIN users u ON a.assigned_reviewer_id = u.user_id
            WHERE (a.department_id = ? OR pc.department = ?) AND a.status != 'Draft'
            ORDER BY a.submitted_at DESC
        ");
        $stmt->execute([$userDeptId, $userDeptId]);
    }
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($row) {
        return [
            'app_code' => $row['application_number'] ?? 'N/A',
            'applicant_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['surname'] ?? '')),
            'programme' => $row['course_title'] ?? 'N/A',
            'status' => status_label($row['current_status'] ?? 'UNDER_DEPT_REVIEW'),
            'reviewer_name' => $row['reviewer_name'] ?? null,
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
        SELECT a.application_id, a.application_number, a.current_status, a.submitted_at, a.department_id,
               pd.first_name, pd.surname,
               pc.cgpa, c.course_title,
               pg.department AS pg_dept,
               u.full_name AS reviewer_name
        FROM applications a
        LEFT JOIN personal_details pd ON a.application_id = pd.application_id
        LEFT JOIN higher_education pc ON a.application_id = pc.application_id
        LEFT JOIN programme_choices pg ON a.application_id = pg.application_id
        LEFT JOIN courses c ON pg.course = c.course_id
        LEFT JOIN users u ON a.assigned_reviewer_id = u.user_id
        WHERE a.application_number = ?
        LIMIT 1
    ");
    $stmt->execute([$appCode]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Application not found.']);
        exit;
    }

    // Verify department access
    if ($userDeptId !== null && (int)$row['department_id'] !== $userDeptId && (int)$row['pg_dept'] !== $userDeptId) {
        echo json_encode(['success' => false, 'message' => 'Access Denied: Application does not belong to your department.']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $row]);
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

        $stmt = $pdo->prepare("
            SELECT a.application_id, a.user_id, a.department_id, pg.department AS pg_dept 
            FROM applications a 
            LEFT JOIN programme_choices pg ON a.application_id = pg.application_id
            WHERE a.application_number = ? 
            LIMIT 1
        ");
        $stmt->execute([$item['app_code']]);
        $appRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$appRow) {
            continue;
        }

        // Verify department access
        if ($userDeptId !== null && (int)$appRow['department_id'] !== $userDeptId && (int)$appRow['pg_dept'] !== $userDeptId) {
            echo json_encode(['success' => false, 'message' => 'Access Denied: One or more applications do not belong to your department.']);
            exit;
        }

        $appId = (int) $appRow['application_id'];
        require_once __DIR__ . '/../../../classes/ApplicationProgressManager.php';
        $progManager = new ApplicationProgressManager($pdo);
        $missingStage = null;
        if (!$progManager->canAdvanceToStage($appId, ApplicationProgressManager::STAGE_DEPT_REVIEW, $missingStage)) {
            echo json_encode(['success' => false, 'message' => "Cannot perform Departmental Review before the '{$missingStage}' stage is completed."]);
            exit;
        }

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
            'actor_role' => $_SESSION['role'] ?: 'DEPARTMENT_ADMIN',
        ];

        if (!empty($item['reviewer_name'])) {
            $reviewerId = resolve_reviewer_id($pdo, $item['reviewer_name']);
            if ($reviewerId) {
                $context['assigned_reviewer_id'] = $reviewerId;
            }
        }

        update_application_status($pdo, $appId, $newStatus, $context);
    }

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unsupported action.']);
