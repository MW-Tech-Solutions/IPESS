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
    return $_SESSION['department_id'] ?? $_SESSION['dept_id'] ?? null;
}

if ($action === 'list') {
    $deptId = fetch_department_id();
    if (!$deptId) {
        echo json_encode(['success' => false, 'message' => 'Department not assigned.']);
        exit;
    }

    $stmt = $pdo->prepare("\
        SELECT a.application_id, a.application_number, a.current_status, a.submitted_at,
               pd.first_name, pd.surname,
               c.course_title,
               u.full_name AS reviewer_name
        FROM applications a
        LEFT JOIN personal_details pd ON a.application_id = pd.application_id
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
        LEFT JOIN courses c ON pc.course = c.course_id
        LEFT JOIN users u ON a.assigned_reviewer_id = u.user_id
        WHERE a.department_id = ? AND a.current_status IN ('ASSIGNED_TO_DEPARTMENT', 'UNDER_DEPT_REVIEW', 'ACTION_REQUIRED_DOCS', 'TOPIC_REJECTED', 'DEPT_APPROVED', 'DEPT_REJECTED', 'REVIEWER_ASSIGNED')
        ORDER BY a.submitted_at DESC
    ");
    $stmt->execute([$deptId]);
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

    $stmt = $pdo->prepare("\
        SELECT a.application_number, a.current_status, a.submitted_at,
               pd.first_name, pd.surname,
               pc.cgpa, c.course_title,
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

        $stmt = $pdo->prepare("SELECT application_id, user_id FROM applications WHERE application_number = ? LIMIT 1");
        $stmt->execute([$item['app_code']]);
        $appRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$appRow) {
            continue;
        }

        $appId = (int) $appRow['application_id'];
        require_once __DIR__ . '/../../../classes/ApplicationProgressManager.php';
        $progManager = new ApplicationProgressManager($pdo);
        if (!$progManager->isStageCompleted($appId, ApplicationProgressManager::STAGE_REFEREES)) {
            echo json_encode(['success' => false, 'message' => 'Cannot perform Departmental Review before Referee Report is completed for application ' . $item['app_code']]);
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
