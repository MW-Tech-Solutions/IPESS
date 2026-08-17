<?php
session_start();
require '../db.php';
require_once __DIR__ . '/../../includes/status_engine.php';
require_once __DIR__ . '/../../includes/completion_service.php';
require_once __DIR__ . '/../includes/mailer.php';

// 1. Security Check (Uncomment for production)
// if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$wantsJson = !empty($_POST['ajax']);
if ($wantsJson) {
    header('Content-Type: application/json');
}

$appId    = isset($_POST['app_id']) ? intval($_POST['app_id']) : 0;
$decision = isset($_POST['decision']) ? trim($_POST['decision']) : '';

$statusMap = [
    'admit'  => 'ADMISSION_APPROVED',
    'approve'  => 'ADMISSION_APPROVED',
    'reject' => 'ADMISSION_REJECTED'
];

if ($appId <= 0 || !array_key_exists($decision, $statusMap)) {
    $message = "Invalid application reference or decision type.";
    if ($wantsJson) {
        echo json_encode(['success' => false, 'message' => $message]);
        exit();
    }
    $_SESSION['error'] = $message;
    header("Location: dashboard.php");
    exit();
}

$newStatus = $statusMap[$decision];

try {
    $pdo->beginTransaction();

    // 2. Fetch Applicant Details (including email from users table)
    $stmt = $pdo->prepare("
        SELECT a.application_number, a.user_id, p.first_name, p.surname, u.email 
        FROM applications a 
        JOIN applicant_accounts u ON a.user_id = u.user_id 
        JOIN personal_details p ON a.application_id = p.application_id
        WHERE a.application_id = ? LIMIT 1
    ");
    $stmt->execute([$appId]);
    $app = $stmt->fetch();

    if (!$app) {
        throw new Exception("Applicant details not found.");
    }

    $appNumber = $app['application_number'];
    $fullName  = $app['first_name'] . ' ' . $app['surname'];
    $userEmail = $app['email'];

    if ($decision === 'admit') {
        $checks = can_final_approve($pdo, $appId);
        if (!$checks['percent_ok'] || !$checks['docs_ok'] || !$checks['ref_ok']) {
            throw new Exception("Final approval conditions not met. Completion {$checks['percent']}%, docs OK: " . ($checks['docs_ok'] ? 'yes' : 'no') . ", referees OK: " . ($checks['ref_ok'] ? 'yes' : 'no'));
        }
    }

    update_application_status($pdo, $appId, $newStatus, [
        'actor_id' => $_SESSION['user_id'] ?? null,
        'actor_role' => $_SESSION['role'] ?? 'ADMIN',
        'note' => $decision === 'admit' ? 'Final approval' : 'Final rejection',
        'notify_user_id' => $app['user_id'] ?? null,
        'notify_title' => $decision === 'admit' ? 'Admission Approved' : 'Admission Rejected',
        'notify_message' => $decision === 'admit' ? 'Congratulations! Your admission has been approved.' : 'Your admission was not approved.'
    ]);

    $subject = $decision === 'admit' || $decision === 'approve'
        ? "Congratulations! Admission Offer - JOSTUM PG"
        : "Application Status Update - JOSTUM PG";

    if ($decision === 'admit' || $decision === 'approve') {
        $body = "<p>Dear <strong>{$fullName}</strong>,</p>
                <p>We are pleased to inform you that you have been offered admission into the Postgraduate programme at
                <strong>Joseph Sarwuan Tarka University, Makurdi (JOSTUM)</strong>.</p>
                <p><strong>Application Number:</strong> {$appNumber}</p>
                <p>Please log in to the portal to download your official admission letter and proceed with acceptance fee payment.</p>
                <p>Regards,<br>Admission Officer, PG School</p>";
    } else {
        $body = "<p>Dear {$fullName},</p>
                <p>Thank you for your interest in our postgraduate programmes. After a careful review of your application ({$appNumber}),
                we regret to inform you that we are unable to offer you admission at this time.</p>
                <p>We wish you the best in your future academic endeavors.</p>
                <p>Regards,<br>Postgraduate School Admissions Team</p>";
    }

    $mailResult = portal_send_mail($userEmail, $fullName, $subject, $body);
    if (!$mailResult['success']) {
        throw new Exception($mailResult['message']);
    }
    $pdo->commit();
    
    $message = "Application {$appNumber} has been <strong>" . (($decision === 'admit' || $decision === 'approve') ? 'Admitted' : 'Rejected') . "</strong>.";
    if ($wantsJson) {
        echo json_encode(['success' => true, 'message' => strip_tags($message)]);
        exit();
    }
    $_SESSION['success_message'] = $message;
    $redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : "../view.php?app_no=" . urlencode($appNumber);
    header("Location: " . $redirect);
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $message = "Process failed: " . $e->getMessage();
    if ($wantsJson) {
        echo json_encode(['success' => false, 'message' => $message]);
        exit();
    }
    $_SESSION['error'] = $message;
    $redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : "dashboard.php";
    header("Location: " . $redirect);
    exit();
}
