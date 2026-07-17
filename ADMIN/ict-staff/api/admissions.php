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

if (!has_permission('ict_processing', $sessionRole, $sessionUserId)) {
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
        '/macintosh|mac os x/i' =>  'Mac OS X',
        '/linux/i'              =>  'Linux',
        '/iphone/i'             =>  'iPhone',
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
        '/edge/i'      => 'Edge'
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

function activate_student_account(PDO $pdo, int $appId, int $studentUserId, string $fullName, string $email, string $matric) {
    try {
        $studentRoleId = (int)$pdo->query("SELECT role_id FROM roles WHERE role_key = 'STUDENT'")->fetchColumn();
        if ($studentRoleId > 0) {
            $pdo->prepare("UPDATE users SET role_id = ?, account_status = 'Active' WHERE user_id = ?")
                ->execute([$studentRoleId, $studentUserId]);
        }
        
        $subject = "Postgraduate Admission Confirmed & Student Portal Activated";
        $body = "Dear {$fullName},<br><br>";
        $body .= "Congratulations! Your admission into JOSTUM Postgraduate School has been confirmed.<br>";
        $body .= "Your Matriculation Number is: <strong>{$matric}</strong><br>";
        $body .= "Your Student Portal has been activated. You can now log in using your registered credentials to access your Student Dashboard.<br><br>";
        $body .= "Regards,<br>IPESS JOSTUM Admin";
        
        portal_send_mail($email, $fullName, $subject, $body, "Student admission and matriculation notice");
    } catch (Throwable $e) {
        error_log("Failed to activate student account: " . $e->getMessage());
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
        $stmt = $pdo->prepare("
            SELECT a.application_id, a.application_number, a.status, a.current_status,
                   p.first_name, p.surname, p.email, c.course_title, f.faculty_name, pc.degree_type,
                   ap.matric_number, ap.student_number, ap.acceptance_letter_status, ap.admission_letter_status
            FROM applications a
            LEFT JOIN personal_details p ON a.application_id = p.application_id
            LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
            LEFT JOIN courses c ON pc.course = c.course_id
            LEFT JOIN faculties f ON pc.faculty = f.faculty_id
            LEFT JOIN admission_processing ap ON a.application_id = ap.application_id
            WHERE a.application_id = ?
            LIMIT 1
        ");
        $stmt->execute([$appId]);
        $applicant = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$applicant) {
            echo json_encode(['success' => false, 'message' => 'Applicant not found.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'applicant' => $applicant
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'generate_matric') {
    $appId = filter_input(INPUT_GET, 'application_id', FILTER_VALIDATE_INT);
    if (!$appId) {
        echo json_encode(['success' => false, 'message' => 'Invalid application ID.']);
        exit;
    }

    try {
        // Find degree type
        $stmt = $pdo->prepare("SELECT degree_type FROM programme_choices WHERE application_id = ? LIMIT 1");
        $stmt->execute([$appId]);
        $degree = strtoupper($stmt->fetchColumn() ?: 'MSC');

        $year = date('Y');

        // Fetch format from settings or default
        $format = 'IPESS/{YEAR}/{DEGREE}/{SEQ}';
        $setStmt = $pdo->query("SELECT institution_name FROM system_settings LIMIT 1");
        $settings = $setStmt->fetch(PDO::FETCH_ASSOC);
        
        // Count count for sequence
        $countStmt = $pdo->query("SELECT COUNT(*) FROM admission_processing WHERE matric_number IS NOT NULL AND matric_number != ''");
        $seq = (int) $countStmt->fetchColumn() + 1;
        $seqStr = str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

        $matric = str_replace(['{YEAR}', '{DEGREE}', '{SEQ}'], [$year, $degree, $seqStr], $format);

        echo json_encode(['success' => true, 'matric' => $matric]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Generation error: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'generate_student_num') {
    $appId = filter_input(INPUT_GET, 'application_id', FILTER_VALIDATE_INT);
    if (!$appId) {
        echo json_encode(['success' => false, 'message' => 'Invalid application ID.']);
        exit;
    }

    try {
        $year = date('Y');
        $random = str_pad((string)rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $studentNum = "STU-{$year}-{$random}";

        echo json_encode(['success' => true, 'student_number' => $studentNum]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Generation error: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    $appId = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
    $matric = trim($_POST['matric_number'] ?? '');
    $studentNum = trim($_POST['student_number'] ?? '');
    $acceptStatus = $_POST['acceptance_letter_status'] ?? 'Inactive';
    $admitStatus = $_POST['admission_letter_status'] ?? 'Inactive';

    if (!$appId || empty($matric) || empty($studentNum)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $progManager = new ApplicationProgressManager($pdo);
        $missingStage = null;
        if (!$progManager->canAdvanceToStage($appId, ApplicationProgressManager::STAGE_ICT_PROCESSING, $missingStage)) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => "Cannot complete ICT Processing before '{$missingStage}' is completed."]);
            exit;
        }

        // Get original status
        $statusStmt = $pdo->prepare("SELECT current_status, status FROM applications WHERE application_id = ? LIMIT 1 FOR UPDATE");
        $statusStmt->execute([$appId]);
        $appRow = $statusStmt->fetch(PDO::FETCH_ASSOC);
        $oldStatus = $appRow['current_status'] ?: 'SUBMITTED';

        // 1. Insert / Update admission_processing settings
        $insQuery = "
            INSERT INTO admission_processing 
                (application_id, matric_number, student_number, acceptance_letter_status, admission_letter_status, 
                 acceptance_letter_activated_at, admission_letter_activated_at, matric_generated_at, student_num_generated_at, 
                 matric_generated_by, student_num_generated_by) 
            VALUES 
                (:app_id, :matric, :student_num, :accept, :admit, 
                 CASE WHEN :accept = 'Active' THEN NOW() ELSE NULL END,
                 CASE WHEN :admit = 'Active' THEN NOW() ELSE NULL END,
                 NOW(), NOW(), :user_id, :user_id)
            ON DUPLICATE KEY UPDATE 
                matric_number = VALUES(matric_number),
                student_number = VALUES(student_number),
                acceptance_letter_status = VALUES(acceptance_letter_status),
                admission_letter_status = VALUES(admission_letter_status),
                acceptance_letter_activated_at = CASE WHEN VALUES(acceptance_letter_status) = 'Active' AND acceptance_letter_status != 'Active' THEN NOW() ELSE acceptance_letter_activated_at END,
                admission_letter_activated_at = CASE WHEN VALUES(admission_letter_status) = 'Active' AND admission_letter_status != 'Active' THEN NOW() ELSE admission_letter_activated_at END
        ";
        $stmt = $pdo->prepare($insQuery);
        $stmt->execute([
            ':app_id' => $appId,
            ':matric' => $matric,
            ':student_num' => $studentNum,
            ':accept' => $acceptStatus,
            ':admit' => $admitStatus,
            ':user_id' => $sessionUserId
        ]);

        // 2. Mark stage ICT Processing as Completed
        $progressQuery = "
            INSERT INTO application_progress 
                (application_id, stage, stage_status, stage_updated_at) 
            VALUES 
                (:app_id, 'Main ICT Processing', 'Completed', NOW())
            ON DUPLICATE KEY UPDATE 
                stage_status = 'Completed',
                stage_updated_at = NOW()
        ";
        $progStmt = $pdo->prepare($progressQuery);
        $progStmt->execute([':app_id' => $appId]);

        // 3. Mark next stage 'Admission Completed' as Completed
        $nextQuery = "
            INSERT INTO application_progress 
                (application_id, stage, stage_status, stage_updated_at) 
            VALUES 
                (:app_id, 'Admission Completed', 'Completed', NOW())
            ON DUPLICATE KEY UPDATE 
                stage_status = 'Completed',
                stage_updated_at = NOW()
        ";
        $nextStmt = $pdo->prepare($nextQuery);
        $nextStmt->execute([':app_id' => $appId]);

        // 4. Update general application status to Admitted / ADMISSION_APPROVED
        update_application_status($pdo, $appId, 'ADMISSION_APPROVED', [
            'actor_id' => $sessionUserId,
            'actor_role' => $sessionRole,
            'note' => 'Matric number issued and letters activated.'
        ]);
        $pdo->prepare("UPDATE applications SET status = 'Admitted' WHERE application_id = ?")->execute([$appId]);

        // 5. Update/Create Student Profile
        $userStmt = $pdo->prepare("
            SELECT a.application_number, pd.first_name, pd.surname, u.email, c.course_title, pc.department, a.user_id
            FROM applications a
            JOIN users u ON a.user_id = u.user_id
            JOIN personal_details pd ON a.application_id = pd.application_id
            JOIN programme_choices pc ON a.application_id = pc.application_id
            JOIN courses c ON pc.course = c.course_id
            WHERE a.application_id = ? LIMIT 1
        ");
        $userStmt->execute([$appId]);
        $studentRow = $userStmt->fetch(PDO::FETCH_ASSOC);

        if ($studentRow) {
            $studentId = $studentRow['application_number'];
            $fullName = $studentRow['first_name'] . ' ' . $studentRow['surname'];
            $email = $studentRow['email'];
            $programme = $studentRow['course_title'];
            $studentUserId = (int)$studentRow['user_id'];

            $insProfile = "
                INSERT INTO student_profiles 
                    (student_id, full_name, email, programme, status, start_date) 
                VALUES 
                    (:student_id, :name, :email, :prog, 'Active', CURDATE())
                ON DUPLICATE KEY UPDATE 
                    full_name = VALUES(full_name),
                    programme = VALUES(programme),
                    status = 'Active'
            ";
            $pdo->prepare($insProfile)->execute([
                ':student_id' => $studentId,
                ':name' => $fullName,
                ':email' => $email,
                ':prog' => $programme
            ]);

            // Activate student dashboard and send notice
            activate_student_account($pdo, $appId, $studentUserId, $fullName, $email, $matric);
        }

        // Recalculate helper
        if (file_exists(__DIR__ . '/../../../includes/completion_service.php')) {
            require_once __DIR__ . '/../../../includes/completion_service.php';
            update_completion($pdo, $appId);
        }

        // Log this action
        log_workflow($pdo, $sessionUserId, $sessionRole, "Processed candidate registration (Matric: {$matric})", $appId, $oldStatus, "Admitted");

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Candidate processed successfully.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'regenerate_letter') {
    $appId = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
    if (!$appId) {
        echo json_encode(['success' => false, 'message' => 'Invalid application ID.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE admission_processing SET 
                admission_letter_activated_at = NOW(),
                admission_letter_status = 'Active'
            WHERE application_id = ?
        ");
        $stmt->execute([$appId]);
        log_workflow($pdo, $sessionUserId, $sessionRole, "Regenerated admission letter", $appId, null, "Active");
        echo json_encode(['success' => true, 'message' => 'Letter regenerated successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk') {
    $bulkAction = $_POST['bulk_action'] ?? '';
    $appIds = $_POST['application_ids'] ?? [];

    if (empty($appIds) || !in_array($bulkAction, ['matric', 'activate'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid bulk action parameters.']);
        exit;
    }

    $bulkId = 'BULK-ICT-' . strtoupper($bulkAction) . '-' . time();

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
            
            $oldStatus = $appRow['current_status'] ?: 'SUBMITTED';

            $progManager = new ApplicationProgressManager($pdo);
            $missingStage = null;
            if (!$progManager->canAdvanceToStage($appId, ApplicationProgressManager::STAGE_ICT_PROCESSING, $missingStage)) {
                continue; // Skip
            }

            if ($bulkAction === 'matric') {
                // Auto generate matric and student numbers
                $stmt = $pdo->prepare("SELECT degree_type FROM programme_choices WHERE application_id = ? LIMIT 1");
                $stmt->execute([$appId]);
                $degree = strtoupper($stmt->fetchColumn() ?: 'MSC');
                
                $year = date('Y');
                
                $countStmt = $pdo->query("SELECT COUNT(*) FROM admission_processing WHERE matric_number IS NOT NULL AND matric_number != ''");
                $seq = (int) $countStmt->fetchColumn() + 1;
                $seqStr = str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
                $matric = "IPESS/{$year}/{$degree}/{$seqStr}";
                
                $random = str_pad((string)rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                $studentNum = "STU-{$year}-{$random}";

                $insQuery = "
                    INSERT INTO admission_processing 
                        (application_id, matric_number, student_number, matric_generated_at, student_num_generated_at, 
                         matric_generated_by, student_num_generated_by) 
                    VALUES 
                        (:app_id, :matric, :student_num, NOW(), NOW(), :user_id, :user_id)
                    ON DUPLICATE KEY UPDATE 
                        matric_number = VALUES(matric_number),
                        student_number = VALUES(student_number),
                        matric_generated_at = NOW(),
                        student_num_generated_at = NOW()
                ";
                $stmt = $pdo->prepare($insQuery);
                $stmt->execute([
                    ':app_id' => $appId,
                    ':matric' => $matric,
                    ':student_num' => $studentNum,
                    ':user_id' => $sessionUserId
                ]);

                log_workflow($pdo, $sessionUserId, $sessionRole, "Bulk generated matric ({$matric})", $appId, $oldStatus, $oldStatus, null, $bulkId);
            } else {
                // Bulk activate letters
                $insQuery = "
                    INSERT INTO admission_processing 
                        (application_id, acceptance_letter_status, admission_letter_status, 
                         acceptance_letter_activated_at, admission_letter_activated_at) 
                    VALUES 
                        (:app_id, 'Active', 'Active', NOW(), NOW())
                    ON DUPLICATE KEY UPDATE 
                        acceptance_letter_status = 'Active',
                        admission_letter_status = 'Active',
                        acceptance_letter_activated_at = NOW(),
                        admission_letter_activated_at = NOW()
                ";
                $stmt = $pdo->prepare($insQuery);
                $stmt->execute([':app_id' => $appId]);

                // Finalize stages
                $progressQuery = "
                    INSERT INTO application_progress 
                        (application_id, stage, stage_status, stage_updated_at) 
                    VALUES 
                        (:app_id, 'Main ICT Processing', 'Completed', NOW())
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
                        (:app_id, 'Admission Completed', 'Completed', NOW())
                    ON DUPLICATE KEY UPDATE 
                        stage_status = 'Completed',
                        stage_updated_at = NOW()
                ";
                $nextStmt = $pdo->prepare($nextQuery);
                $nextStmt->execute([':app_id' => $appId]);

                update_application_status($pdo, $appId, 'ADMISSION_APPROVED', [
                    'actor_id' => $sessionUserId,
                    'actor_role' => $sessionRole,
                    'note' => 'Letters activated.'
                ]);
                $pdo->prepare("UPDATE applications SET status = 'Admitted' WHERE application_id = ?")->execute([$appId]);

                // Profile sync
                $userStmt = $pdo->prepare("
                    SELECT a.application_number, pd.first_name, pd.surname, u.email, c.course_title, a.user_id, ap.matric_number
                    FROM applications a
                    JOIN users u ON a.user_id = u.user_id
                    JOIN personal_details pd ON a.application_id = pd.application_id
                    JOIN programme_choices pc ON a.application_id = pc.application_id
                    JOIN courses c ON pc.course = c.course_id
                    LEFT JOIN admission_processing ap ON a.application_id = ap.application_id
                    WHERE a.application_id = ? LIMIT 1
                ");
                $userStmt->execute([$appId]);
                $studentRow = $userStmt->fetch(PDO::FETCH_ASSOC);

                if ($studentRow) {
                    $studentId = $studentRow['application_number'];
                    $fullName = $studentRow['first_name'] . ' ' . $studentRow['surname'];
                    $email = $studentRow['email'];
                    $programme = $studentRow['course_title'];
                    $studentUserId = (int)$studentRow['user_id'];
                    $matricVal = $studentRow['matric_number'] ?: 'N/A';

                    $insProfile = "
                        INSERT INTO student_profiles 
                            (student_id, full_name, email, programme, status, start_date) 
                        VALUES 
                            (:student_id, :name, :email, :prog, 'Active', CURDATE())
                        ON DUPLICATE KEY UPDATE 
                            full_name = VALUES(full_name),
                            programme = VALUES(programme),
                            status = 'Active'
                    ";
                    $pdo->prepare($insProfile)->execute([
                        ':student_id' => $studentId,
                        ':name' => $fullName,
                        ':email' => $email,
                        ':prog' => $programme
                    ]);

                    // Activate student dashboard and send notice
                    activate_student_account($pdo, $appId, $studentUserId, $fullName, $email, $matricVal);
                }

                log_workflow($pdo, $sessionUserId, $sessionRole, "Bulk activated admission letters", $appId, $oldStatus, "Admitted", null, $bulkId);
            }
            $successCount++;
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "Bulk action processed. Updated {$successCount} records."]);
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
