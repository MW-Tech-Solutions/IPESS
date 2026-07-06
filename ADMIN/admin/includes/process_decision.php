<?php
session_start();
require '../db.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../../includes/status_engine.php';
require_once __DIR__ . '/../../../config/urls.php';
require_once __DIR__ . '/../../../helpers/admission-letter-template.php';

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

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
    'admit'  => 'Admitted',
    'approve' => 'Admitted',
    'reject' => 'Rejected',
    'revoke' => 'Submitted'
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
    $app = null;
    $stmt = $pdo->prepare("
        SELECT a.application_number, p.first_name, p.surname, u.email 
        FROM applications a 
        JOIN users u ON a.user_id = u.user_id 
        JOIN personal_details p ON a.application_id = p.application_id
        WHERE a.application_id = ? LIMIT 1
    ");
    $stmt->execute([$appId]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$app) {
        $stmt = $pdo->prepare("
            SELECT a.application_number, p.first_name, p.surname, u.email 
            FROM applications a 
            JOIN applicant_accounts u ON a.user_id = u.user_id 
            JOIN personal_details p ON a.application_id = p.application_id
            WHERE a.application_id = ? LIMIT 1
        ");
        $stmt->execute([$appId]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$app) {
        throw new Exception("Applicant details not found.");
    }

    $appNumber = $app['application_number'];
    $fullName  = $app['first_name'] . ' ' . $app['surname'];
    $userEmail = $app['email'];
    $stmt = $pdo->prepare("SELECT user_id FROM applications WHERE application_id = ? LIMIT 1");
    $stmt->execute([$appId]);
    $appUserId = (int) $stmt->fetchColumn();

    // 3. Update Database Status
    if (in_array($decision, ['admit', 'approve', 'reject'], true)) {
        require_once __DIR__ . '/../../../classes/ApplicationProgressManager.php';
        $progManager = new ApplicationProgressManager($pdo);
        if (!$progManager->isStageCompleted($appId, ApplicationProgressManager::STAGE_PG_REVIEW)) {
            throw new Exception("Cannot make a final decision before the PG Review stage is completed.");
        }
    }

    if ($decision === 'admit' || $decision === 'approve') {
        update_application_status($pdo, $appId, 'ADMISSION_APPROVED', [
            'actor_id' => $_SESSION['user_id'] ?? null,
            'actor_role' => $_SESSION['role'] ?? 'ADMIN',
            'note' => 'Admission approved'
        ]);
        if ($appUserId > 0) {
            notify_user($pdo, $appUserId, 'Admission Approved', 'Congratulations! Your admission has been approved.');
        }
    } elseif ($decision === 'revoke') {
        update_application_status($pdo, $appId, 'SUBMITTED', [
            'actor_id' => $_SESSION['user_id'] ?? null,
            'actor_role' => $_SESSION['role'] ?? 'ADMIN',
            'note' => 'Admission revoked'
        ]);
        if ($appUserId > 0) {
            notify_user($pdo, $appUserId, 'Admission Revoked', 'Your admission has been revoked and your status is back to Submitted.');
        }
    } else {
        update_application_status($pdo, $appId, 'ADMISSION_REJECTED', [
            'actor_id' => $_SESSION['user_id'] ?? null,
            'actor_role' => $_SESSION['role'] ?? 'ADMIN',
            'note' => 'Admission rejected'
        ]);
        if ($appUserId > 0) {
            notify_user($pdo, $appUserId, 'Admission Rejected', 'Your admission decision is rejected.');
        }
    }

    $subject = ($decision === 'admit' || $decision === 'approve')
        ? "Congratulations! Admission Offer - JOSTUM PG"
        : "Application Status Update - JOSTUM PG";

    $attachmentPath = '';
    $attachmentName = '';

    if ($decision === 'admit' || $decision === 'approve') {
        $body = "<p>Dear <strong>{$fullName}</strong>,</p>
                <p>We are pleased to inform you that you have been offered admission into the Postgraduate programme at
                <strong>Joseph Sarwuan Tarka University, Makurdi (JOSTUM)</strong>.</p>
                <p><strong>Application Number:</strong> {$appNumber}</p>
                <p>Your official admission letter is attached to this email as a PDF document.</p>
                <p>Regards,<br>Admission Officer, PG School</p>";
    } elseif ($decision === 'revoke') {
        $body = "<p>Dear <strong>{$fullName}</strong>,</p>
                <p>Your admission decision has been revoked for further review.</p>
                <p><strong>Application Number:</strong> {$appNumber}</p>
                <p>We will notify you once a final decision is made.</p>
                <p>Regards,<br>Admission Officer, PG School</p>";
    } else {
        $body = "<p>Dear {$fullName},</p>
                <p>Thank you for your interest in our postgraduate programmes. After a careful review of your application ({$appNumber}),
                we regret to inform you that we are unable to offer you admission at this time.</p>
                <p>We wish you the best in your future academic endeavors.</p>
                <p>Regards,<br>Postgraduate School Admissions Team</p>";
    }

    $ctaMeta = [];
    if ($decision === 'admit' || $decision === 'approve') {
        try {
            $applicant = admission_letter_fetch($pdo, $appNumber, null);
            if ($applicant) {
                $html = render_admission_letter_html($applicant, [
                    'include_print_button' => false,
                    'for_pdf' => true
                ]);

                if (class_exists(\Dompdf\Dompdf::class)) {
                    $options = new \Dompdf\Options();
                    $options->set('isRemoteEnabled', true);
                    $options->set('defaultFont', 'DejaVu Sans');
                    $dompdf = new \Dompdf\Dompdf($options);
                    $dompdf->loadHtml($html);
                    $dompdf->setPaper('A4', 'portrait');
                    $dompdf->render();
                    $pdf = $dompdf->output();

                    $safeName = preg_replace('/[^A-Za-z0-9_]+/', '_', $fullName);
                    $safeName = trim($safeName, '_');
                    $attachmentName = 'Admission_Letter_' . ($safeName !== '' ? $safeName : 'Student') . '_' . $appNumber . '.pdf';
                    $attachmentPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . uniqid('admission_letter_', true) . '.pdf';
                    file_put_contents($attachmentPath, $pdf);
                }
            }
        } catch (Throwable $e) {
            $attachmentPath = '';
            $attachmentName = '';
        }
    }
    $mailResult = ['success' => true, 'message' => 'Mail skipped.'];
    if (!empty($userEmail)) {
        if ($attachmentPath !== '' && file_exists($attachmentPath)) {
            $ctaMeta['attachments'] = [[
                'path' => $attachmentPath,
                'name' => $attachmentName !== '' ? $attachmentName : 'Admission_Letter.pdf'
            ]];
        }
        $mailResult = portal_send_mail($userEmail, $fullName, $subject, $body, '', $ctaMeta);
    }
    if ($attachmentPath !== '' && file_exists($attachmentPath)) {
        @unlink($attachmentPath);
    }
    $pdo->commit();
    
    $message = "Application {$appNumber} has been <strong>{$newStatus}</strong>.";
    if (!($mailResult['success'] ?? true)) {
        $message .= " (Mail not sent: {$mailResult['message']})";
    }
    if ($wantsJson) {
        echo json_encode(['success' => true, 'message' => strip_tags($message)]);
        exit();
    }
    $_SESSION['success_message'] = $message;
    $embed = isset($_POST['embed']) && $_POST['embed'] === '1';
    redirect_to('ADMIN/admin/admission-decision-view.php?app_no=' . urlencode($appNumber) . ($embed ? '&embed=1' : ''));

} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $message = "Process failed: " . $e->getMessage();
    if ($wantsJson) {
        echo json_encode(['success' => false, 'message' => $message]);
        exit();
    }
    $_SESSION['error'] = $message;
    $embed = isset($_POST['embed']) && $_POST['embed'] === '1';
    redirect_to('ADMIN/admin/admission-decisions.php' . ($embed ? '?embed=1' : ''));
}
