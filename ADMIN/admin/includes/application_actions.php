<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../includes/status_engine.php';
require_once __DIR__ . '/../../../ADMIN/includes/mailer.php';
enforce_session_timeout(900, 'ADMIN/login.php');
require_role(['PG_SCHOOL_OFFICER', 'ADMISSIONS_OFFICER', 'PORTAL_ADMIN', 'SUPER_ADMIN', 'ICT_ADMIN'], 'ADMIN/login.php');

$wantsJson = !empty($_POST['ajax']);
if ($wantsJson) {
    header('Content-Type: application/json');
}

$appId = isset($_POST['app_id']) ? (int) $_POST['app_id'] : 0;
$action = $_POST['action'] ?? '';

if ($appId <= 0 || !in_array($action, ['accept', 'reject'], true)) {
    $msg = 'Invalid action.';
    if ($wantsJson) {
        echo json_encode(['success' => false, 'message' => $msg]);
        exit();
    }
    $_SESSION['error'] = $msg;
    redirect_to('ADMIN/admin/application-management.php');
}

try {
    $pdo->beginTransaction();

    // Fetch Applicant Details
    $stmt = $pdo->prepare("
        SELECT a.application_number, p.first_name, p.surname, u.email, a.user_id 
        FROM applications a 
        JOIN users u ON a.user_id = u.user_id 
        JOIN personal_details p ON a.application_id = p.application_id
        WHERE a.application_id = ? LIMIT 1
    ");
    $stmt->execute([$appId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception("Application details not found.");
    }
    
    $fullName = trim(($row['first_name'] ?? '') . ' ' . ($row['surname'] ?? ''));
    $appNumber = $row['application_number'];
    $userEmail = $row['email'];
    $appUserId = (int) ($row['user_id'] ?? 0);

    require_once __DIR__ . '/../../../classes/ApplicationProgressManager.php';
    $progManager = new ApplicationProgressManager($pdo);
    $progManager->initializeApplication($appId);

    if ($action === 'accept') {
        update_application_status($pdo, $appId, 'SUBMITTED', [
            'actor_id' => $_SESSION['user_id'] ?? null,
            'actor_role' => $_SESSION['role'] ?? 'ADMIN',
            'note' => 'Application submission accepted'
        ]);

        $progManager->updateStageStatus($appId, ApplicationProgressManager::STAGE_SUBMITTED, ApplicationProgressManager::STATUS_COMPLETED);
        $progManager->updateStageStatus($appId, ApplicationProgressManager::STAGE_DOC_VERIFY, ApplicationProgressManager::STATUS_IN_PROGRESS);

        if ($appUserId > 0) {
            notify_user($pdo, $appUserId, 'Application Accepted', 'Your postgraduate application has been accepted and is now under verification.');
        }

        $subject = "Application Submission Received - JOSTUM PG";
        $body = "<p>Dear <strong>{$fullName}</strong>,</p>
                <p>We are pleased to inform you that your Postgraduate application has been successfully accepted into our review process.</p>
                <p><strong>Application Number:</strong> {$appNumber}</p>
                <p>Your documents are currently undergoing verification. We will update you as the review progresses.</p>
                <p>Regards,<br>Admissions Team, PG School</p>";

        if (!empty($userEmail)) {
            portal_send_mail($userEmail, $fullName, $subject, $body, '');
        }

        $_SESSION['success_message'] = 'Application submission accepted and moved to Document Verification stage.';
    }

    if ($action === 'reject') {
        update_application_status($pdo, $appId, 'ADMISSION_REJECTED', [
            'actor_id' => $_SESSION['user_id'] ?? null,
            'actor_role' => $_SESSION['role'] ?? 'ADMIN',
            'note' => 'Application rejected'
        ]);

        if ($appUserId > 0) {
            notify_user($pdo, $appUserId, 'Admission Rejected', 'Your postgraduate application has been rejected.');
        }

        $subject = "Application Status Update - JOSTUM PG";
        $body = "<p>Dear {$fullName},</p>
                <p>Thank you for your interest in our postgraduate programmes. After a careful review of your application ({$appNumber}),
                we regret to inform you that we are unable to offer you admission at this time.</p>
                <p>We wish you the best in your future academic endeavors.</p>
                <p>Regards,<br>Postgraduate School Admissions Team</p>";

        if (!empty($userEmail)) {
            portal_send_mail($userEmail, $fullName, $subject, $body, '');
        }

        $_SESSION['success_message'] = 'Application rejected.';
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($wantsJson) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
    $_SESSION['error'] = $e->getMessage();
}

if ($wantsJson) {
    echo json_encode(['success' => true, 'message' => $_SESSION['success_message'] ?? 'Action completed.']);
    unset($_SESSION['success_message']);
    exit();
}

$embed = isset($_POST['embed']) && $_POST['embed'] === '1';
redirect_to('ADMIN/admin/view.php?app_no=' . urlencode($_POST['app_no'] ?? '') . ($embed ? '&embed=1' : ''));


