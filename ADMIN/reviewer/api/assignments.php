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

function map_status_to_ui(string $status): string {
    return match ($status) {
        'REVIEWER_ASSIGNED', 'DEPT_APPROVED' => 'Pending',
        'UNDER_REVIEWER_REVIEW' => 'In Progress',
        'REVIEWER_APPROVED' => 'Complete',
        'REVIEWER_REJECTED', 'ACTION_REQUIRED_REVIEW' => 'Revision Required',
        default => 'Pending',
    };
}

function map_ui_to_status(string $status): string {
    $value = strtolower($status);
    if ($value === 'in progress') return 'UNDER_REVIEWER_REVIEW';
    if ($value === 'complete') return 'REVIEWER_APPROVED';
    if ($value === 'revision required') return 'ACTION_REQUIRED_REVIEW';
    return 'REVIEWER_ASSIGNED';
}

if ($action === 'list') {
    $reviewerId = $_SESSION['user_id'] ?? null;
    if (!$reviewerId) {
        echo json_encode(['success' => false, 'message' => 'Reviewer session missing.']);
        exit;
    }

    $stmt = $pdo->prepare("\
        SELECT a.application_id, a.application_number, a.current_status, a.submitted_at,
               pd.first_name, pd.surname, c.course_title,
               (SELECT h.note FROM application_status_history h WHERE h.application_id = a.application_id AND h.actor_role = 'REVIEWER' ORDER BY h.created_at DESC LIMIT 1) AS reviewer_note
        FROM applications a
        LEFT JOIN personal_details pd ON a.application_id = pd.application_id
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
        LEFT JOIN courses c ON pc.course = c.course_id
        WHERE a.assigned_reviewer_id = ?
          AND a.current_status IN ('REVIEWER_ASSIGNED', 'DEPT_APPROVED', 'UNDER_REVIEWER_REVIEW', 'REVIEWER_APPROVED', 'REVIEWER_REJECTED', 'ACTION_REQUIRED_REVIEW')
        ORDER BY a.submitted_at DESC
    ");
    $stmt->execute([$reviewerId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($row) {
        return [
            'assignment_id' => $row['application_id'],
            'application_code' => $row['application_number'],
            'applicant_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['surname'] ?? '')),
            'programme' => $row['course_title'] ?? 'N/A',
            'status' => map_status_to_ui($row['current_status'] ?? 'REVIEWER_ASSIGNED'),
            'due_date' => $row['submitted_at'] ? date('Y-m-d', strtotime($row['submitted_at'] . ' +7 days')) : null,
            'remarks' => $row['reviewer_note'] ?? null,
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

if ($action === 'update_status') {
    $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
    $uiStatus = $_POST['status'] ?? 'Pending';
    $remarks = trim($_POST['remarks'] ?? '');

    if ($assignmentId === 0) {
        echo json_encode(['success' => false, 'message' => 'Missing assignment id.']);
        exit;
    }

    require_once __DIR__ . '/../../../classes/ApplicationProgressManager.php';
    $progManager = new ApplicationProgressManager($pdo);
    if (!$progManager->isStageCompleted($assignmentId, ApplicationProgressManager::STAGE_DEPT_REVIEW)) {
        echo json_encode(['success' => false, 'message' => 'Cannot perform PG Review before Departmental Review is completed.']);
        exit;
    }

    $newStatus = map_ui_to_status($uiStatus);
    update_application_status($pdo, $assignmentId, $newStatus, [
        'actor_id' => $_SESSION['user_id'] ?? null,
        'actor_role' => $_SESSION['role'] ?? 'REVIEWER',
        'note' => $remarks
    ]);

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'bulk') {
    $ids = $_POST['assignment_ids'] ?? [];
    if (!is_array($ids) || count($ids) === 0) {
        echo json_encode(['success' => false, 'message' => 'No assignments selected.']);
        exit;
    }
    
    require_once __DIR__ . '/../../../classes/ApplicationProgressManager.php';
    $progManager = new ApplicationProgressManager($pdo);
    foreach ($ids as $id) {
        if (!$progManager->isStageCompleted((int) $id, ApplicationProgressManager::STAGE_DEPT_REVIEW)) {
            echo json_encode(['success' => false, 'message' => 'Cannot perform PG Review on application ID ' . $id . ' before Departmental Review is completed.']);
            exit;
        }
    }

    $uiStatus = $_POST['status'] ?? 'Complete';
    $newStatus = map_ui_to_status($uiStatus);
    foreach ($ids as $id) {
        update_application_status($pdo, (int) $id, $newStatus, [
            'actor_id' => $_SESSION['user_id'] ?? null,
            'actor_role' => $_SESSION['role'] ?? 'REVIEWER',
            'note' => 'Bulk update'
        ]);
    }
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unsupported action.']);
