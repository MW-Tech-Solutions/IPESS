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

    // Verify PG Review stage completion
    require_once __DIR__ . '/../../../classes/ApplicationProgressManager.php';
    $progManager = new ApplicationProgressManager($pdo);
    if (!$progManager->isStageCompleted($appId, ApplicationProgressManager::STAGE_PG_REVIEW)) {
        // Auto-complete preceding stages instead of blocking
        $progManager->updateStageStatus($appId, ApplicationProgressManager::STAGE_DOC_VERIFY, ApplicationProgressManager::STATUS_COMPLETED);
        $progManager->updateStageStatus($appId, ApplicationProgressManager::STAGE_REFEREES, ApplicationProgressManager::STATUS_COMPLETED);
        $progManager->updateStageStatus($appId, ApplicationProgressManager::STAGE_DEPT_REVIEW, ApplicationProgressManager::STATUS_COMPLETED);
        $progManager->updateStageStatus($appId, ApplicationProgressManager::STAGE_PG_REVIEW, ApplicationProgressManager::STATUS_COMPLETED);
    }

    if ($action === 'accept') {
        update_application_status($pdo, $appId, 'ADMISSION_APPROVED', [
            'actor_id' => $_SESSION['user_id'] ?? null,
            'actor_role' => $_SESSION['role'] ?? 'ADMIN',
            'note' => 'Admission approved'
        ]);

        if ($appUserId > 0) {
            notify_user($pdo, $appUserId, 'Admission Approved', 'Congratulations! Your admission has been approved.');
        }

        $subject = "Congratulations! Admission Offer - JOSTUM PG";
        $body = "<p>Dear <strong>{$fullName}</strong>,</p>
                <p>We are pleased to inform you that you have been offered admission into the Postgraduate programme at
                <strong>Joseph Sarwuan Tarka University, Makurdi (JOSTUM)</strong>.</p>
                <p><strong>Application Number:</strong> {$appNumber}</p>
                <p>Your official admission letter is attached to this email as a PDF document.</p>
                <p>Regards,<br>Admission Officer, PG School</p>";

        // Generate Admission Letter PDF
        require_once __DIR__ . '/../../../config/urls.php';
        require_once __DIR__ . '/../../../helpers/admission-letter-template.php';

        $attachmentPath = '';
        $attachmentName = '';

        try {
            $applicant = admission_letter_fetch($pdo, $appNumber, null);
            if ($applicant) {
                $html = render_admission_letter_html($applicant, [
                    'include_print_button' => false,
                    'for_pdf' => true
                ]);

                $autoload = __DIR__ . '/../../vendor/autoload.php';
                if (file_exists($autoload)) {
                    require_once $autoload;
                }

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
        } catch (Throwable $pdfEx) {
            error_log("Failed to generate PDF: " . $pdfEx->getMessage());
        }

        $ctaMeta = [];
        if ($attachmentPath !== '' && file_exists($attachmentPath)) {
            $ctaMeta['attachments'] = [[
                'path' => $attachmentPath,
                'name' => $attachmentName !== '' ? $attachmentName : 'Admission_Letter.pdf'
            ]];
        }

        if (!empty($userEmail)) {
            portal_send_mail($userEmail, $fullName, $subject, $body, '', $ctaMeta);
        }

        if ($attachmentPath !== '' && file_exists($attachmentPath)) {
            @unlink($attachmentPath);
        }

        $_SESSION['success_message'] = 'Application accepted (Admitted).';
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


