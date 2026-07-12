<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../ADMIN/admin/includes/db.php';
require_once __DIR__ . '/../../../app/helpers/auth.php';
require_once __DIR__ . '/../../../includes/status_engine.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../../classes/ApplicationProgressManager.php';

// RBAC Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

$sessionUserId = (int) $_SESSION['user_id'];
$sessionRole = $_SESSION['role'] ?? '';

if (!has_permission('pg_review', $sessionRole, $sessionUserId)) {
    echo json_encode(['success' => false, 'message' => 'Forbidden. Insufficient permissions.']);
    exit;
}

// Helpers for logging OS/Browser
function get_os($user_agent) {
    $os_platform = "Unknown OS";
    $os_array = [
        '/windows nt 10/i'      =>  'Windows 10/11',
        '/windows nt 6.3/i'     =>  'Windows 8.1',
        '/windows nt 6.2/i'     =>  'Windows 8',
        '/windows nt 6.1/i'     =>  'Windows 7',
        '/macintosh|mac os x/i' =>  'Mac OS X',
        '/linux/i'              =>  'Linux',
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

function get_browser_name($user_agent) {
    $browser = "Unknown Browser";
    $browser_array = [
        '/msie/i'      => 'Internet Explorer',
        '/firefox/i'   => 'Firefox',
        '/safari/i'    => 'Safari',
        '/chrome/i'    => 'Chrome',
        '/edge/i'      => 'Edge',
        '/opera/i'     => 'Opera'
    ];
    foreach ($browser_array as $regex => $value) {
        if (preg_match($regex, $user_agent)) {
            $browser = $value;
            break;
        }
    }
    return $browser;
}

function log_workflow($pdo, $userId, $role, $action, $applicantId, $oldStatus, $newStatus, $remarks = null, $bulkId = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $browser = get_browser_name($ua);
    $os = get_os($ua);

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
        // Fetch applicant with supervisor details
        $stmt = $pdo->prepare("
            SELECT a.application_id, a.application_number, a.status, a.current_status,
                   p.first_name, p.surname, p.email, c.course_title,
                   su.full_name AS supervisor_name, sp.supervisor_status, sa.assigned_at AS supervisor_assigned_at
            FROM applications a
            LEFT JOIN personal_details p ON a.application_id = p.application_id
            LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
            LEFT JOIN courses c ON pc.course = c.course_id
            LEFT JOIN student_profiles sp ON (sp.student_id = a.application_number OR sp.email = p.email)
            LEFT JOIN supervisor_assignments sa ON sa.application_id = a.application_id AND sa.status = 'Assigned'
            LEFT JOIN supervisors su ON sa.supervisor_id = su.supervisor_id
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

        // Fetch audit logs
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'decision') {
    $appId = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
    $decision = $_POST['decision'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');

    if (!$appId || !in_array($decision, ['approve', 'reject', 'correct'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $progManager = new ApplicationProgressManager($pdo);
        $missingStage = null;
        if (!$progManager->canAdvanceToStage($appId, ApplicationProgressManager::STAGE_PG_REVIEW, $missingStage)) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => "Cannot complete Postgraduate School evaluation before '{$missingStage}' is completed."]);
            exit;
        }

        // Get original status
        $statusStmt = $pdo->prepare("SELECT current_status FROM applications WHERE application_id = ? LIMIT 1 FOR UPDATE");
        $statusStmt->execute([$appId]);
        $oldStatus = $statusStmt->fetchColumn() ?: 'SUBMITTED';

        if ($decision === 'approve') {
            $newStatus = 'APPROVED_BY_POSTGRADUATE_SCHOOL';
        } elseif ($decision === 'reject') {
            $newStatus = 'REJECTED_BY_POSTGRADUATE_SCHOOL';
        } else {
            // Correction requested
            $newStatus = 'ACTION_REQUIRED_DOCS';
        }

        // Update overall application status
        update_application_status($pdo, $appId, $newStatus, [
            'actor_id' => $sessionUserId,
            'actor_role' => $sessionRole,
            'note' => 'PG School Decision: ' . $remarks
        ]);

        if ($decision === 'approve') {
            // Mark stage PG School Review as Completed
            $progressQuery = "
                INSERT INTO application_progress 
                    (application_id, stage, stage_status, stage_updated_at) 
                VALUES 
                    (:app_id, 'PG School Review', 'Completed', NOW())
                ON DUPLICATE KEY UPDATE 
                    stage_status = 'Completed',
                    stage_updated_at = NOW()
            ";
            $progStmt = $pdo->prepare($progressQuery);
            $progStmt->execute([':app_id' => $appId]);

            // Mark Main ICT Processing as In Progress
            $nextQuery = "
                INSERT INTO application_progress 
                    (application_id, stage, stage_status, stage_updated_at) 
                VALUES 
                    (:app_id, 'Main ICT Processing', 'In Progress', NOW())
                ON DUPLICATE KEY UPDATE 
                    stage_status = 'In Progress',
                    stage_updated_at = NOW()
            ";
            $nextStmt = $pdo->prepare($nextQuery);
            $nextStmt->execute([':app_id' => $appId]);
        } else {
            // Send email notification for rejection/correction
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
                $subject = ($decision === 'reject') ? 'Application Decision Notice - PG School' : 'Application Feedback - Correction Requested';
                $bodyHtml = ($decision === 'reject') 
                    ? '<p>Dear Applicant,</p><p>We regret to inform you that your postgraduate admission application was declined by the PG School board.</p>'
                    : '<p>Dear Applicant,</p><p>The Postgraduate School evaluation board has requested correction on your application credentials.</p>';
                
                portal_send_mail(
                    $userRow['email'],
                    $userRow['name'] ?: $userRow['email'],
                    $subject,
                    $bodyHtml 
                    . '<p><strong>Feedback Comments:</strong> ' . htmlspecialchars($remarks) . '</p>'
                    . '<p><strong>Application Number:</strong> ' . htmlspecialchars($userRow['application_number']) . '</p>',
                    'PG evaluation decision.'
                );
            }
        }

        // Recalculate helper
        if (file_exists(__DIR__ . '/../../../includes/completion_service.php')) {
            require_once __DIR__ . '/../../../includes/completion_service.php';
            update_completion($pdo, $appId);
        }

        // Log decision
        $actionText = ($decision === 'approve') ? "PG School approved admission" : (($decision === 'reject') ? "PG School declined admission" : "PG School requested corrections");
        log_workflow($pdo, $sessionUserId, $sessionRole, $actionText, $appId, $oldStatus, $newStatus, $remarks);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Decision processed successfully.']);
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

    if (empty($appIds) || !in_array($bulkAction, ['approve', 'reject'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid bulk parameters.']);
        exit;
    }

    if ($bulkAction === 'reject' && empty($remarks)) {
        echo json_encode(['success' => false, 'message' => 'Feedback reason comment is required for bulk rejection.']);
        exit;
    }

    $bulkId = 'BULK-PG-' . strtoupper($bulkAction) . '-' . time();

    try {
        $pdo->beginTransaction();
        $successCount = 0;

        foreach ($appIds as $appId) {
            $appId = (int) $appId;
            if ($appId <= 0) continue;

            $statusStmt = $pdo->prepare("SELECT current_status FROM applications WHERE application_id = ? LIMIT 1 FOR UPDATE");
            $statusStmt->execute([$appId]);
            $oldStatus = $statusStmt->fetchColumn() ?: 'SUBMITTED';

            $newStatus = ($bulkAction === 'approve') ? 'APPROVED_BY_POSTGRADUATE_SCHOOL' : 'REJECTED_BY_POSTGRADUATE_SCHOOL';

            $progManager = new ApplicationProgressManager($pdo);
            $missingStage = null;
            if (!$progManager->canAdvanceToStage($appId, ApplicationProgressManager::STAGE_PG_REVIEW, $missingStage)) {
                continue; // Skip
            }

            update_application_status($pdo, $appId, $newStatus, [
                'actor_id' => $sessionUserId,
                'actor_role' => $sessionRole,
                'note' => 'Bulk PG School Decision: ' . $remarks
            ]);

            if ($bulkAction === 'approve') {
                $progressQuery = "
                    INSERT INTO application_progress 
                        (application_id, stage, stage_status, stage_updated_at) 
                    VALUES 
                        (:app_id, 'PG School Review', 'Completed', NOW())
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
                        (:app_id, 'Main ICT Processing', 'In Progress', NOW())
                    ON DUPLICATE KEY UPDATE 
                        stage_status = 'In Progress',
                        stage_updated_at = NOW()
                ";
                $nextStmt = $pdo->prepare($nextQuery);
                $nextStmt->execute([':app_id' => $appId]);

                log_workflow($pdo, $sessionUserId, $sessionRole, "Bulk PG School Approved", $appId, $oldStatus, $newStatus, null, $bulkId);
            } else {
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
                        'Application Decision Notice - PG School',
                        '<p>Dear Applicant,</p><p>We regret to inform you that your postgraduate application was declined by the PG School board.</p>'
                        . '<p><strong>Decline Comments:</strong> ' . htmlspecialchars($remarks) . '</p>'
                        . '<p><strong>Application Number:</strong> ' . htmlspecialchars($userRow['application_number']) . '</p>',
                        'PG evaluation: application declined.'
                    );
                }

                log_workflow($pdo, $sessionUserId, $sessionRole, "Bulk PG School Rejected", $appId, $oldStatus, $newStatus, $remarks, $bulkId);
            }
            $successCount++;
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "Bulk PG action completed. Processed {$successCount} records."]);
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
