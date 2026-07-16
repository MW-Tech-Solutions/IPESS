<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once __DIR__ . '/../../includes/status_engine.php';
require_once __DIR__ . '/../../includes/completion_service.php';
require_once __DIR__ . '/../includes/mailer.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doc_id = filter_input(INPUT_POST, 'doc_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $comments = filter_input(INPUT_POST, 'comments', FILTER_SANITIZE_STRING);
    $score = filter_input(INPUT_POST, 'score', FILTER_VALIDATE_INT) ?? 0;
    $user_id = $_SESSION['user_id'] ?? 0;
    $role = $_SESSION['role'] ?? 'ADMIN';

    if (!$doc_id || !$status) {
        $response['message'] = 'Invalid input.';
    } else {
        if ($status === 'Rejected') {
            $status = 'Re-upload Required';
        }
        try {
            $pdo->beginTransaction();

            require_once __DIR__ . '/../../classes/ApplicationProgressManager.php';
            $progManager = new ApplicationProgressManager($pdo);
            $appStmt = $pdo->prepare("SELECT application_id FROM documents WHERE doc_id = ?");
            $appStmt->execute([$doc_id]);
            $applicationId = (int) $appStmt->fetchColumn();

            // Department ownership check for general staff
            $userDeptId = null;
            if ($user_id > 0) {
                $deptStmt = $pdo->prepare("SELECT department_id FROM users WHERE user_id = ? LIMIT 1");
                $deptStmt->execute([$user_id]);
                $userDeptId = $deptStmt->fetchColumn();
            }
            if ($userDeptId) {
                $checkDeptStmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM applications a
                    LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
                    WHERE a.application_id = ? AND (pc.department = ? OR a.department_id = ?)
                ");
                $checkDeptStmt->execute([$applicationId, $userDeptId, $userDeptId]);
                if ((int)$checkDeptStmt->fetchColumn() === 0) {
                    $response['message'] = 'Access Denied: This application does not belong to your department.';
                    echo json_encode($response);
                    exit;
                }
            }

            $missingStage = null;
            if ($applicationId > 0 && !$progManager->canAdvanceToStage($applicationId, ApplicationProgressManager::STAGE_DOC_VERIFY, $missingStage)) {
                $response['message'] = "Cannot verify documents before the '{$missingStage}' stage is completed.";
                echo json_encode($response);
                exit;
            }

            $query = "
                INSERT INTO document_verification 
                    (upload_id, verification_status, admin_remark, score, verified_by, verified_at) 
                VALUES 
                    (:doc_id, :status, :comments, :score, :user_id, NOW())
                ON DUPLICATE KEY UPDATE 
                    verification_status = VALUES(verification_status),
                    admin_remark = VALUES(admin_remark),
                    score = VALUES(score),
                    verified_by = VALUES(verified_by),
                    verified_at = NOW()
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':status'   => $status,
                ':comments' => $comments,
                ':score'    => $score,
                ':doc_id'   => $doc_id,
                ':user_id'  => $user_id
            ]);

            if ($status === 'Verified') {
                $getAppIdStmt = $pdo->prepare("SELECT application_id FROM documents WHERE doc_id = ?");
                $getAppIdStmt->execute([$doc_id]);
                $application_id = $getAppIdStmt->fetchColumn();

                if ($application_id) {
                    $checkQuery = "
                        SELECT 
                            COUNT(d.doc_id) as total_docs,
                            SUM(CASE WHEN dv.verification_status = 'Verified' THEN 1 ELSE 0 END) as verified_docs
                        FROM documents d
                        LEFT JOIN document_verification dv ON d.doc_id = dv.upload_id
                        WHERE d.application_id = ?
                    ";
                    $checkStmt = $pdo->prepare($checkQuery);
                    $checkStmt->execute([$application_id]);
                    $counts = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if ($counts['total_docs'] > 0 && $counts['total_docs'] == $counts['verified_docs']) {
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
                        $progStmt->execute([':app_id' => $application_id]);

                        // Ensure completion percentage reflects fully verified docs
                        $upd = $pdo->prepare("UPDATE applications SET completion_percentage = 100 WHERE application_id = ? AND (completion_percentage IS NULL OR completion_percentage < 100)");
                        $upd->execute([$application_id]);

                        // If status was ACTION_REQUIRED_DOCS, return it to SUBMITTED
                        $statusStmt = $pdo->prepare("SELECT current_status FROM applications WHERE application_id = ?");
                        $statusStmt->execute([$application_id]);
                        $currStatus = $statusStmt->fetchColumn();
                        if ($currStatus === 'ACTION_REQUIRED_DOCS') {
                            update_application_status($pdo, $application_id, 'SUBMITTED', [
                                'actor_id' => $user_id,
                                'actor_role' => $_SESSION['role'] ?? 'ADMIN',
                                'note' => 'All documents verified. Application status restored to Submitted.'
                            ]);
                        }
                    }

                    update_completion($pdo, (int) $application_id);
                }
            }

            if ($status === 'Rejected') {
                $getAppIdStmt = $pdo->prepare("SELECT a.application_id, a.application_number, a.user_id, d.document_type, COALESCE(u.email, aa.email) AS email, CONCAT(p.first_name, ' ', p.surname) AS name
                                               FROM applications a
                                               JOIN documents d ON a.application_id = d.application_id
                                               LEFT JOIN users u ON a.user_id = u.user_id
                                               LEFT JOIN applicant_accounts aa ON aa.user_id = a.user_id
                                               LEFT JOIN personal_details p ON a.application_id = p.application_id
                                               WHERE d.doc_id = ? LIMIT 1");
                $getAppIdStmt->execute([$doc_id]);
                $appRow = $getAppIdStmt->fetch(PDO::FETCH_ASSOC);
                if ($appRow) {
                    $reason = trim((string) $comments);
                    $reasonText = $reason !== '' ? $reason : 'Document quality or validity issue.';
                    update_application_status($pdo, (int) $appRow['application_id'], 'ACTION_REQUIRED_DOCS', [
                        'actor_id' => $user_id,
                        'actor_role' => $role,
                        'note' => 'Document rejected: ' . $reasonText,
                        'notify_user_id' => (int) $appRow['user_id'],
                        'notify_title' => 'Document Re-upload Required',
                        'notify_message' => 'A document was rejected. Please re-upload the required document(s). Reason: ' . $reasonText
                    ]);

                    if (!empty($appRow['email'])) {
                        $docLabel = !empty($appRow['document_type']) ? ucwords(str_replace('_', ' ', $appRow['document_type'])) : 'Document';
                        portal_send_mail(
                            $appRow['email'],
                            $appRow['name'] ?: $appRow['email'],
                            'Document Re-upload Required',
                            '<p>Your <strong>' . htmlspecialchars($docLabel) . '</strong> was rejected. Please log in and re-upload the corrected document.</p>'
                            . '<p><strong>Reason:</strong> ' . htmlspecialchars($reasonText) . '</p>'
                            . '<p><strong>Application No:</strong> ' . htmlspecialchars($appRow['application_number'] ?? '') . '</p>',
                            'Document re-upload required.'
                        );
                    }
                }
            }

            $pdo->commit();
            $response = ['success' => true, 'message' => 'Verification saved and progress updated.'];

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Verification Logic Error: ' . $e->getMessage());
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
    }
}
echo json_encode($response);
