<?php
session_start();
header('Content-Type: application/json');

// RBAC check and DB connection
// if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN' || !isset($_SESSION['user_id'])) {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized']);
//     exit;
// }

require_once '../includes/db.php';
require_once __DIR__ . '/../../../includes/status_engine.php';
require_once __DIR__ . '/../../../ADMIN/includes/mailer.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doc_id = filter_input(INPUT_POST, 'doc_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $comments = filter_input(INPUT_POST, 'comments', FILTER_SANITIZE_STRING);
    $user_id = $_SESSION['user_id'];

    if (!$doc_id || !$status) {
        $response['message'] = 'Invalid input.';
    } else {
        if ($status === 'Rejected') {
            $status = 'Re-upload Required';
        }
        try {
            require_once __DIR__ . '/../../../classes/ApplicationProgressManager.php';
            $progManager = new ApplicationProgressManager($pdo);
            $appStmt = $pdo->prepare("SELECT application_id FROM documents WHERE doc_id = ?");
            $appStmt->execute([$doc_id]);
            $applicationId = (int) $appStmt->fetchColumn();
            $missingStage = null;
            if ($applicationId > 0 && !$progManager->canAdvanceToStage($applicationId, ApplicationProgressManager::STAGE_DOC_VERIFY, $missingStage)) {
                $response['message'] = "Cannot verify documents before the '{$missingStage}' stage is completed.";
                echo json_encode($response);
                exit;
            }

            // We use INSERT ... ON DUPLICATE KEY UPDATE to handle both new and existing records
    // Note: This requires a UNIQUE constraint on upload_id (see step 2 below)
    $query = "
        INSERT INTO document_verification 
            (upload_id, verification_status, admin_remark, verified_by, verified_at) 
        VALUES 
            (:doc_id, :status, :comments, :user_id, NOW())
        ON DUPLICATE KEY UPDATE 
            verification_status = VALUES(verification_status),
            admin_remark = VALUES(admin_remark),
            verified_by = VALUES(verified_by),
            verified_at = NOW()
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':status', $status, PDO::PARAM_STR);
    $stmt->bindValue(':comments', $comments, PDO::PARAM_STR);
    $stmt->bindValue(':doc_id', $doc_id, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        // Update application status and notify on rejection
        if ($status === 'Re-upload Required') {
            $appStmt = $pdo->prepare("SELECT application_id, document_type FROM documents WHERE doc_id = ?");
            $appStmt->execute([$doc_id]);
            $appRow = $appStmt->fetch(PDO::FETCH_ASSOC);
            $appId = (int) ($appRow['application_id'] ?? 0);
            $docType = (string) ($appRow['document_type'] ?? '');
            $reason = trim((string) $comments);
            $reasonText = $reason !== '' ? $reason : 'Document quality or validity issue.';
            if ($appId > 0) {
                update_application_status($pdo, $appId, 'ACTION_REQUIRED_DOCS', [
                    'actor_id' => $user_id,
                    'actor_role' => $_SESSION['role'] ?? 'ADMIN',
                    'note' => 'Document rejected: ' . $reasonText
                ]);

                $userStmt = $pdo->prepare("SELECT u.email, CONCAT(p.first_name, ' ', p.surname) AS name, a.application_number
                                           FROM applications a
                                           JOIN users u ON a.user_id = u.user_id
                                           JOIN personal_details p ON a.application_id = p.application_id
                                           WHERE a.application_id = ? LIMIT 1");
                $userStmt->execute([$appId]);
                $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
                if ($userRow && !empty($userRow['email'])) {
                    $docLabel = $docType !== '' ? ucwords(str_replace('_', ' ', $docType)) : 'Document';
                    $reasonHtml = htmlspecialchars($reasonText);
                    portal_send_mail(
                        $userRow['email'],
                        $userRow['name'] ?: $userRow['email'],
                        'Document Re-upload Required',
                        '<p>Your <strong>' . htmlspecialchars($docLabel) . '</strong> was rejected. Please log in and re-upload the corrected document.</p>'
                        . '<p><strong>Reason:</strong> ' . $reasonHtml . '</p>'
                        . '<p><strong>Application No:</strong> ' . htmlspecialchars($userRow['application_number'] ?? '') . '</p>',
                        'Document re-upload required.'
                    );
                }
            }
        }
        if ($status === 'Verified') {
            $appStmt = $pdo->prepare("SELECT application_id FROM documents WHERE doc_id = ?");
            $appStmt->execute([$doc_id]);
            $applicationId = (int) $appStmt->fetchColumn();
            if ($applicationId > 0) {
                $countStmt = $pdo->prepare("
                    SELECT 
                        COUNT(d.doc_id) AS total_docs,
                        SUM(CASE WHEN dv.verification_status = 'Verified' THEN 1 ELSE 0 END) AS verified_docs
                    FROM documents d
                    LEFT JOIN document_verification dv ON dv.upload_id = d.doc_id
                    WHERE d.application_id = ?
                ");
                $countStmt->execute([$applicationId]);
                $counts = $countStmt->fetch(PDO::FETCH_ASSOC);
                if ($counts && (int) $counts['total_docs'] > 0 && (int) $counts['total_docs'] === (int) $counts['verified_docs']) {
                    // Update progress stage
                    $progressQuery = "
                        INSERT INTO application_progress 
                            (application_id, stage, stage_status, stage_updated_at) 
                        VALUES 
                            (:app_id, 'Documents Verification', 'Completed', NOW())
                        ON DUPLICATE KEY UPDATE 
                            stage_status = 'Completed',
                            stage_updated_at = NOW()
                    ";
                    $progStmt = $pdo->prepare($progressQuery);
                    $progStmt->execute([':app_id' => $applicationId]);

                    // Ensure completion percentage is 100
                    $upd = $pdo->prepare("UPDATE applications SET completion_percentage = 100 WHERE application_id = ? AND (completion_percentage IS NULL OR completion_percentage < 100)");
                    $upd->execute([$applicationId]);

                    // If status was ACTION_REQUIRED_DOCS, return it to SUBMITTED
                    $statusStmt = $pdo->prepare("SELECT current_status FROM applications WHERE application_id = ?");
                    $statusStmt->execute([$applicationId]);
                    $currStatus = $statusStmt->fetchColumn();
                    if ($currStatus === 'ACTION_REQUIRED_DOCS') {
                        update_application_status($pdo, $applicationId, 'SUBMITTED', [
                            'actor_id' => $user_id,
                            'actor_role' => $_SESSION['role'] ?? 'ADMIN',
                            'note' => 'All documents verified. Application status restored to Submitted.'
                        ]);
                    }
                }
                
                // Recalculate/update completion helper
                if (file_exists(__DIR__ . '/../../../includes/completion_service.php')) {
                    require_once __DIR__ . '/../../../includes/completion_service.php';
                    update_completion($pdo, $applicationId);
                }
            }
        }
        $response = ['success' => true, 'message' => 'Document status updated successfully.'];
    } else {
        $response['message'] = 'Failed to save to database.';
    }
} catch (Exception $e) {
    error_log('Verification API Error: ' . $e->getMessage());
    $response['message'] = 'Database error: ' . $e->getMessage();
}
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?>
