<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../../includes/status_engine.php';
require_once __DIR__ . '/../../../ADMIN/includes/mailer.php';
require_once __DIR__ . '/../../../config/urls.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    redirect_to('ADMIN/login.php');
}

$appId = isset($_POST['app_id']) ? (int) $_POST['app_id'] : 0;
$action = $_POST['action'] ?? '';

if ($appId <= 0 || !in_array($action, ['accept', 'reject'], true)) {
    $_SESSION['error'] = 'Invalid action.';
    redirect_to('ADMIN/admin/application-management.php');
}

try {
    if ($action === 'accept') {
        update_application_status($pdo, $appId, 'SUBMITTED', [
            'actor_id' => $_SESSION['user_id'] ?? null,
            'actor_role' => $_SESSION['role'] ?? 'ADMIN',
            'note' => 'Application accepted for processing'
        ]);
        $stmt = $pdo->prepare("SELECT u.user_id, u.email, CONCAT(p.first_name, ' ', p.surname) AS name FROM applications a JOIN users u ON a.user_id = u.user_id JOIN personal_details p ON a.application_id = p.application_id WHERE a.application_id = ? LIMIT 1");
        $stmt->execute([$appId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['user_id'])) {
            notify_user($pdo, (int) $row['user_id'], 'Application Accepted', 'Your application has been accepted for processing.');
        }
        $_SESSION['success_message'] = 'Application accepted for processing.';
    }

    if ($action === 'reject') {
        update_application_status($pdo, $appId, 'ADMISSION_REJECTED', [
            'actor_id' => $_SESSION['user_id'] ?? null,
            'actor_role' => $_SESSION['role'] ?? 'ADMIN',
            'note' => 'Application rejected'
        ]);

        $stmt = $pdo->prepare("SELECT u.user_id, u.email, CONCAT(p.first_name, ' ', p.surname) AS name FROM applications a JOIN users u ON a.user_id = u.user_id JOIN personal_details p ON a.application_id = p.application_id WHERE a.application_id = ? LIMIT 1");
        $stmt->execute([$appId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['email'])) {
            if (!empty($row['user_id'])) {
                notify_user($pdo, (int) $row['user_id'], 'Application Rejected', 'Your application has been rejected.');
            }
            portal_send_mail(
                $row['email'],
                $row['name'] ?: $row['email'],
                'Application Rejected',
                '<p>Your postgraduate application has been rejected. Please contact the admissions office for clarification.</p>',
                'Application rejected.'
            );
        }
        $_SESSION['success_message'] = 'Application rejected.';
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

redirect_to('ADMIN/admin/view.php?app_no=' . urlencode($_POST['app_no'] ?? ''));
