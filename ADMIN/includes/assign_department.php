<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../../includes/status_engine.php';
require_once __DIR__ . '/../../includes/permissions.php';

if (!isset($_SESSION['role']) || !is_admin_role($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../application-management.php');
    exit;
}

$application_id = (int) ($_POST['application_id'] ?? 0);
$department_id = (int) ($_POST['department_id'] ?? 0);
$note = trim($_POST['note'] ?? '');
$app_no = trim($_POST['app_no'] ?? '');

if ($application_id <= 0 || $department_id <= 0) {
    $_SESSION['error'] = 'Invalid department assignment.';
    header('Location: ../application-management.php');
    exit;
}

try {
    update_application_status($pdo, $application_id, 'ASSIGNED_TO_DEPARTMENT', [
        'actor_id' => $_SESSION['user_id'] ?? null,
        'actor_role' => $_SESSION['role'] ?? 'ADMIN',
        'department_id' => $department_id,
        'note' => $note ?: 'Assigned to department'
    ]);

    $_SESSION['success_message'] = 'Department assigned successfully.';
    $redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : ($app_no !== '' ? '../view.php?app_no=' . urlencode($app_no) : '../application-management.php');
    header('Location: ' . $redirect);
    exit;
} catch (Exception $e) {
    $_SESSION['error'] = 'Assignment failed: ' . $e->getMessage();
    $redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : '../application-management.php';
    header('Location: ' . $redirect);
    exit;
}
