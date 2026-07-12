<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../admin/includes/db.php';
require_once __DIR__ . '/../../../app/helpers/auth.php';
require_once __DIR__ . '/../../../includes/status_engine.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../../classes/ApplicationProgressManager.php';

// RBAC check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

$sessionUserId = (int) $_SESSION['user_id'];
$sessionRole = $_SESSION['role'] ?? '';

// Check permission
if (!has_permission('verify_applicants', $sessionRole, $sessionUserId)) {
    echo json_encode(['success' => false, 'message' => 'Forbidden. Insufficient permissions.']);
    exit;
}

// Helper to get OS
function get_os($user_agent) {
    $os_platform = "Unknown OS";
    $os_array = [
        '/windows nt 10/i'      =>  'Windows 10/11',
        '/windows nt 6.3/i'     =>  'Windows 8.1',
        '/windows nt 6.2/i'     =>  'Windows 8',
        '/windows nt 6.1/i'     =>  'Windows 7',
        '/windows nt 6.0/i'     =>  'Windows Vista',
        '/windows nt 5.2/i'     =>  'Windows Server 2003/XP x64',
        '/windows nt 5.1/i'     =>  'Windows XP',
        '/macintosh|mac os x/i' =>  'Mac OS X',
        '/linux/i'              =>  'Linux',
        '/ubuntu/i'             =>  'Ubuntu',
        '/iphone/i'             =>  'iPhone',
        '/ipad/i'               =>  'iPad',
        '/android/i'            =>  'Android'
    ];
    foreach ($os_array as $regex => $value) {
        if (preg_match($regex, $user_agent)) {
            $os_platform = $value;
            break;
        }
    }
    return $os_platform;
}

// Helper to get Browser
function get_browser_name($user_agent) {
    $browser = "Unknown Browser";
    $browser_array = [
        '/msie/i'      => 'Internet Explorer',
        '/firefox/i'   => 'Firefox',
        '/safari/i'    => 'Safari',
        '/chrome/i'    => 'Chrome',
        '/edge/i'      => 'Edge',
        '/opera/i'     => 'Opera',
        '/netscape/i'  => 'Netscape'
    ];
    foreach ($browser_array as $regex => $value) {
        if (preg_match($regex, $user_agent)) {
            $browser = $value;
            break;
        }
    }
    return $browser;
}

// Helper to log workflow activity
function log_workflow($pdo, $userId, $role, $action, $applicantId, $oldStatus, $newStatus, $remarks = null, $bulkId = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $browser = get_browser_name($ua);
    $os = get_os($ua);

    // Fetch department and faculty of the actor if applicable
    $deptId = null;
    $facultyId = null;

    try {
        $stmtActor = $pdo->prepare("SELECT department_id, faculty_id FROM users WHERE user_id = ? LIMIT 1");
        $stmtActor->execute([$userId]);
        $actorRow = $stmtActor->fetch(PDO::FETCH_ASSOC);
        if ($actorRow) {
            $deptId = $actorRow['department_id'] ?: null;
            $facultyId = $actorRow['faculty_id'] ?: null;
        }
    } catch (PDOException $e) {}

    try {
        $stmt = $pdo->prepare("
            INSERT INTO workflow_audit_logs 
                (user_id, role, department_id, faculty_id, action, applicant_id, old_status, new_status, remarks, ip_address, browser, os, bulk_action_id, timestamp)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $role, $deptId, $facultyId, $action, $applicantId, $oldStatus, $newStatus, $remarks, $ip, $browser, $os, $bulkId]);
    } catch (PDOException $e) {
        error_log("Failed to write workflow audit log: " . $e->getMessage());
    }
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'details') {
    $appId = filter_input(INPUT_GET, 'application_id', FILTER_VALIDATE_INT);
    if (!$appId) {
        echo json_encode(['success' => false, 'message' => 'Invalid application ID.']);
        exit;
    }

    try {
        // Fetch applicant
        $stmt = $pdo->prepare("
            SELECT a.application_id, a.application_number, a.status, a.current_status,
                   p.first_name, p.surname, p.email
            FROM applications a
            LEFT JOIN personal_details p ON a.application_id = p.application_id
            WHERE a.application_id = ?
            LIMIT 1
        ");
        $stmt->execute([$appId]);
        $applicant = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$applicant) {
            echo json_encode(['success' => false, 'message' => 'Applicant not found.']);
            exit;
        }

        // Fetch documents
        $stmtDocs = $pdo->prepare("
            SELECT d.doc_id, d.document_type, d.file_path, d.uploaded_at,
                   dv.verification_status AS status, dv.admin_remark AS comments
            FROM documents d
            LEFT JOIN document_verification dv ON d.doc_id = dv.upload_id
            WHERE d.application_id = ?
            ORDER BY d.uploaded_at DESC
        ");
        $stmtDocs->execute([$appId]);
        $documents = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

        // Fetch audit logs for this applicant
        $stmtLogs = $pdo->prepare("
            SELECT wal.*, u.full_name AS actor_name
            FROM workflow_audit_logs wal
            LEFT JOIN users u ON wal.user_id = u.user_id
            WHERE wal.applicant_id = ?
            ORDER BY wal.timestamp DESC
        ");
        $stmtLogs->execute([$appId]);
        $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'applicant' => $applicant,
                'documents' => $documents,
                'logs' => $logs
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_doc_status') {
    $docId = filter_input(INPUT_POST, 'doc_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $remarks = filter_input(INPUT_POST, 'remarks', FILTER_SANITIZE_STRING) ?: '';

    if (!$docId || !$status) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
        exit;
    }

    $dbStatus = $status;
    if ($status === 'Rejected') {
        $dbStatus = 'Re-upload Required';
    }

    try {
        $appStmt = $pdo->prepare("SELECT application_id, document_type FROM documents WHERE doc_id = ?");
        $appStmt->execute([$docId]);
        $docRow = $appStmt->fetch(PDO::FETCH_ASSOC);
        $appId = (int) ($docRow['application_id'] ?? 0);
        $docType = $docRow['document_type'] ?? 'document';

        if ($appId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Associated application not found.']);
            exit;
        }

        $query = "
            INSERT INTO document_verification 
                (upload_id, verification_status, admin_remark, verified_by, verified_at) 
            VALUES 
                (:doc_id, :status, :remarks, :user_id, NOW())
            ON DUPLICATE KEY UPDATE 
                verification_status = VALUES(verification_status),
                admin_remark = VALUES(admin_remark),
                verified_by = VALUES(verified_by),
                verified_at = NOW()
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':doc_id' => $docId,
            ':status' => $dbStatus,
            ':remarks' => $remarks,
            ':user_id' => $sessionUserId
        ]);

        // Log this action
        log_workflow($pdo, $sessionUserId, $sessionRole, "Updated document status: " . ucwords(str_replace('_', ' ', $docType)), $appId, null, $status, $remarks);

        echo json_encode(['success' => true, 'message' => 'Document status updated successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'finalize') {
    $appId = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
    if (!$appId) {
        echo json_encode(['success' => false, 'message' => 'Invalid application ID.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Get original status
        $statusStmt = $pdo->prepare("SELECT current_status, status FROM applications WHERE application_id = ? FOR UPDATE");
        $statusStmt->execute([$appId]);
        $appRow = $statusStmt->fetch(PDO::FETCH_ASSOC);
        $oldCurrentStatus = $appRow['current_status'] ?? 'SUBMITTED';

        // Check if all uploaded documents are Verified
        $countStmt = $pdo->prepare("
            SELECT 
                COUNT(d.doc_id) AS total_docs,
                SUM(CASE WHEN dv.verification_status = 'Verified' THEN 1 ELSE 0 END) AS verified_docs
            FROM documents d
            LEFT JOIN document_verification dv ON dv.upload_id = d.doc_id
            WHERE d.application_id = ?
        ");
        $countStmt->execute([$appId]);
        $counts = $countStmt->fetch(PDO::FETCH_ASSOC);

        if (!$counts || (int) $counts['total_docs'] === 0) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Applicant has not uploaded any documents to verify.']);
            exit;
        }

        if ((int) $counts['total_docs'] !== (int) $counts['verified_docs']) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Cannot finalize verification stage. Some documents are not yet verified.']);
            exit;
        }

        // 2. Mark stage as Completed
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
        $progStmt->execute([':app_id' => $appId]);

        // 3. Mark next stage 'Referee Report' as In Progress
        $nextQuery = "
            INSERT INTO application_progress 
                (application_id, stage, stage_status, stage_updated_at) 
            VALUES 
                (:app_id, 'Referee Report', 'In Progress', NOW())
            ON DUPLICATE KEY UPDATE 
                stage_status = 'In Progress',
                stage_updated_at = NOW()
        ";
        $nextStmt = $pdo->prepare($nextQuery);
        $nextStmt->execute([':app_id' => $appId]);

        // 4. Reset applicant status to Submitted if it was ACTION_REQUIRED_DOCS
        if ($oldCurrentStatus === 'ACTION_REQUIRED_DOCS') {
            update_application_status($pdo, $appId, 'SUBMITTED', [
                'actor_id' => $sessionUserId,
                'actor_role' => $sessionRole,
                'note' => 'All documents verified by ICTO. Restored to Submitted.'
            ]);
        }

        // 5. Update completion weights helper
        if (file_exists(__DIR__ . '/../../../includes/completion_service.php')) {
            require_once __DIR__ . '/../../../includes/completion_service.php';
            update_completion($pdo, $appId);
        }

        // Log the finalization
        log_workflow($pdo, $sessionUserId, $sessionRole, "Completed document verification stage", $appId, $oldCurrentStatus, "Verified");

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Verification stage finalized and candidate advanced.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reject') {
    $appId = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
    $remarks = trim($_POST['remarks'] ?? '');

    if (!$appId || empty($remarks)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters. Remarks are required.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $statusStmt = $pdo->prepare("SELECT current_status FROM applications WHERE application_id = ? LIMIT 1 FOR UPDATE");
        $statusStmt->execute([$appId]);
        $oldStatus = $statusStmt->fetchColumn() ?: 'SUBMITTED';

        // Update overall application status
        update_application_status($pdo, $appId, 'ACTION_REQUIRED_DOCS', [
            'actor_id' => $sessionUserId,
            'actor_role' => $sessionRole,
            'note' => 'ICTO Verification Rejected: ' . $remarks
        ]);

        // Send Email
        $userStmt = $pdo->prepare("
            SELECT u.email, CONCAT(p.first_name, ' ', p.surname) AS name, a.application_number
            FROM applications a
            JOIN users u ON a.user_id = u.user_id
            JOIN personal_details p ON a.application_id = p.application_id
            WHERE a.application_id = ? LIMIT 1
        ");
        $userStmt->execute([$appId]);
        $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);

        if ($userRow && !empty($userRow['email'])) {
            portal_send_mail(
                $userRow['email'],
                $userRow['name'] ?: $userRow['email'],
                'Document Verification Issue - Action Required',
                '<p>Dear Applicant,</p>'
                . '<p>During document screening, the verification officer noticed issues with your uploaded credentials.</p>'
                . '<p><strong>Officer Feedback:</strong> ' . htmlspecialchars($remarks) . '</p>'
                . '<p>Please log in to your portal and re-upload the correct files.</p>'
                . '<p><strong>Application Number:</strong> ' . htmlspecialchars($userRow['application_number']) . '</p>',
                'Action required: document re-upload.'
            );
        }

        // Log rejection
        log_workflow($pdo, $sessionUserId, $sessionRole, "Rejected document verification stage", $appId, $oldStatus, "ACTION_REQUIRED_DOCS", $remarks);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Application rejected successfully. Notification sent to candidate.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk') {
    $bulkAction = $_POST['bulk_action'] ?? '';
    $appIds = $_POST['application_ids'] ?? [];
    $remarks = trim($_POST['remarks'] ?? '');

    if (empty($appIds) || !in_array($bulkAction, ['verify', 'reject'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid bulk action parameters.']);
        exit;
    }

    if ($bulkAction === 'reject' && empty($remarks)) {
        echo json_encode(['success' => false, 'message' => 'Remarks/reasons are required for bulk rejection.']);
        exit;
    }

    $bulkId = 'BULK-' . strtoupper($bulkAction) . '-' . time();

    try {
        $pdo->beginTransaction();
        $successCount = 0;

        foreach ($appIds as $appId) {
            $appId = (int) $appId;
            if ($appId <= 0) continue;

            $statusStmt = $pdo->prepare("SELECT current_status, status FROM applications WHERE application_id = ? LIMIT 1 FOR UPDATE");
            $statusStmt->execute([$appId]);
            $appRow = $statusStmt->fetch(PDO::FETCH_ASSOC);
            if (!$appRow) continue;

            $oldCurrentStatus = $appRow['current_status'] ?: 'SUBMITTED';

            if ($bulkAction === 'verify') {
                // Bulk verify: verify all documents automatically
                $docStmt = $pdo->prepare("SELECT doc_id FROM documents WHERE application_id = ?");
                $docStmt->execute([$appId]);
                $docs = $docStmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($docs as $docId) {
                    $verifyQuery = "
                        INSERT INTO document_verification 
                            (upload_id, verification_status, admin_remark, verified_by, verified_at) 
                        VALUES 
                            (:doc_id, 'Verified', 'Bulk verified by ICTO', :user_id, NOW())
                        ON DUPLICATE KEY UPDATE 
                            verification_status = 'Verified',
                            admin_remark = 'Bulk verified by ICTO',
                            verified_by = :user_id,
                            verified_at = NOW()
                    ";
                    $stmt = $pdo->prepare($verifyQuery);
                    $stmt->execute([':doc_id' => $docId, ':user_id' => $sessionUserId]);
                }

                // Advance application progress stages
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
                $progStmt->execute([':app_id' => $appId]);

                $nextQuery = "
                    INSERT INTO application_progress 
                        (application_id, stage, stage_status, stage_updated_at) 
                    VALUES 
                        (:app_id, 'Referee Report', 'In Progress', NOW())
                    ON DUPLICATE KEY UPDATE 
                        stage_status = 'In Progress',
                        stage_updated_at = NOW()
                ";
                $nextStmt = $pdo->prepare($nextQuery);
                $nextStmt->execute([':app_id' => $appId]);

                if ($oldCurrentStatus === 'ACTION_REQUIRED_DOCS') {
                    update_application_status($pdo, $appId, 'SUBMITTED', [
                        'actor_id' => $sessionUserId,
                        'actor_role' => $sessionRole,
                        'note' => 'Bulk verified. Restored to Submitted.'
                    ]);
                }

                log_workflow($pdo, $sessionUserId, $sessionRole, "Bulk verified application", $appId, $oldCurrentStatus, "Verified", null, $bulkId);
            } else {
                // Bulk reject: set status to ACTION_REQUIRED_DOCS
                update_application_status($pdo, $appId, 'ACTION_REQUIRED_DOCS', [
                    'actor_id' => $sessionUserId,
                    'actor_role' => $sessionRole,
                    'note' => 'Bulk Rejection: ' . $remarks
                ]);

                // Send email
                $userStmt = $pdo->prepare("
                    SELECT u.email, CONCAT(p.first_name, ' ', p.surname) AS name, a.application_number
                    FROM applications a
                    JOIN users u ON a.user_id = u.user_id
                    JOIN personal_details p ON a.application_id = p.application_id
                    WHERE a.application_id = ? LIMIT 1
                ");
                $userStmt->execute([$appId]);
                $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);

                if ($userRow && !empty($userRow['email'])) {
                    portal_send_mail(
                        $userRow['email'],
                        $userRow['name'] ?: $userRow['email'],
                        'Document Verification Issue - Action Required',
                        '<p>Dear Applicant,</p>'
                        . '<p>During bulk screening, the verification officer noticed issues with your credentials.</p>'
                        . '<p><strong>Officer Feedback:</strong> ' . htmlspecialchars($remarks) . '</p>'
                        . '<p>Please log in and re-upload the correct files.</p>'
                        . '<p><strong>Application Number:</strong> ' . htmlspecialchars($userRow['application_number']) . '</p>',
                        'Action required: document re-upload.'
                    );
                }

                log_workflow($pdo, $sessionUserId, $sessionRole, "Bulk rejected application", $appId, $oldCurrentStatus, "ACTION_REQUIRED_DOCS", $remarks, $bulkId);
            }
            $successCount++;
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "Bulk action processed successfully. {$successCount} records updated."]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Bulk action failed: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action requested.']);
?>
