<?php
require_once __DIR__ . '/../../../app/bootstrap.php';

// For API endpoints, do NOT redirect on session timeout — return JSON instead
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please refresh the page and log in again.']);
    exit;
}

if (!has_permission('manage_students')) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied: Requires manage_students permission.']);
    exit;
}

header('Content-Type: application/json');
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../../config/urls.php';
require_once __DIR__ . '/../../../includes/status_engine.php';
require_once __DIR__ . '/../../../includes/referee_service.php';

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}

function upsert_single_application_row(PDO $pdo, string $table, int $applicationId, array $fields): void
{
    $checkStmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE application_id = ? LIMIT 1");
    $checkStmt->execute([$applicationId]);
    $exists = (bool) $checkStmt->fetchColumn();

    $columns = array_keys($fields);
    $values = array_values($fields);

    if ($exists) {
        $setClause = implode(', ', array_map(static fn($col) => "{$col} = ?", $columns));
        $sql = "UPDATE {$table} SET {$setClause} WHERE application_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($values, [$applicationId]));
        return;
    }

    $insertColumns = array_merge(['application_id'], $columns);
    $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
    $sql = "INSERT INTO {$table} (" . implode(', ', $insertColumns) . ") VALUES ({$placeholders})";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$applicationId], $values));
}

function fetch_student_profile(PDO $pdo, int $studentUserId): array
{
    $studentSql = "
        SELECT
            u.user_id AS student_user_id,
            u.email,
            u.full_name,
            u.account_status,
            a.application_id,
            a.application_number,
            a.status,
            a.current_status
        FROM users u
        INNER JOIN applications a ON a.user_id = u.user_id
        WHERE u.user_id = ?
          AND NOT EXISTS (
              SELECT 1
              FROM applications nx
              WHERE nx.user_id = a.user_id
                AND nx.application_id > a.application_id
          )
        LIMIT 1
    ";
    $stmt = $pdo->prepare($studentSql);
    $stmt->execute([$studentUserId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        throw new RuntimeException('Student record not found.');
    }

    $applicationId = (int) $student['application_id'];

    $bioStmt = $pdo->prepare("SELECT surname, first_name, other_name, dob, sex, nationality, state_origin, lga, phone, address FROM personal_details WHERE application_id = ? LIMIT 1");
    $bioStmt->execute([$applicationId]);
    $biodata = $bioStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $acadStmt = $pdo->prepare("SELECT faculty AS faculty_id, department AS department_id, degree_type AS degree_id, mode_of_study AS mode_id, course AS course_id FROM programme_choices WHERE application_id = ? LIMIT 1");
    $acadStmt->execute([$applicationId]);
    $academics = $acadStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $heStmt = $pdo->prepare("SELECT highest_qualification, course_study, institution, grad_year, cgpa FROM higher_education WHERE application_id = ? LIMIT 1");
    $heStmt->execute([$applicationId]);
    $higherEducation = $heStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $researchStmt = $pdo->prepare("SELECT research_area, reason_for_choosing, statement_of_purpose, career_objectives FROM research_details WHERE application_id = ? LIMIT 1");
    $researchStmt->execute([$applicationId]);
    $research = $researchStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $refStmt = $pdo->prepare("SELECT referee_id, full_name, title, organization, email, phone FROM referees WHERE application_id = ? ORDER BY referee_id ASC");
    $refStmt->execute([$applicationId]);
    $referees = $refStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $apStmt = $pdo->prepare("SELECT matric_number, admission_letter_status, acceptance_letter_status FROM admission_processing WHERE application_id = ? LIMIT 1");
    $apStmt->execute([$applicationId]);
    $admissionProcessing = $apStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $passportStmt = $pdo->prepare("
        SELECT file_path 
        FROM documents 
        WHERE application_id = ? 
          AND document_type IN ('passport_profile','passport') 
        ORDER BY CASE WHEN document_type = 'passport_profile' THEN 0 ELSE 1 END
        LIMIT 1
    ");
    $passportStmt->execute([$applicationId]);
    $passportFile = $passportStmt->fetchColumn() ?: '';

    return [
        'student' => $student,
        'biodata' => $biodata,
        'academics' => $academics,
        'higher_education' => $higherEducation,
        'research' => $research,
        'referees' => $referees,
        'admission_processing' => $admissionProcessing,
        'passport_photo_path' => $passportFile,
        'encrypted_application_number' => encrypt_app_number($student['application_number'] ?? ''),
    ];
}

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $action = trim((string) (($method === 'GET' ? $_GET['action'] : $_POST['action']) ?? ''));

    if ($method === 'GET' && $action === 'fetch') {
        $studentUserId = (int) ($_GET['student_user_id'] ?? 0);
        if ($studentUserId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Student ID is required.']);
            exit;
        }

        $profile = fetch_student_profile($pdo, $studentUserId);
        echo json_encode(['success' => true] + $profile);
        exit;
    }

    if ($method !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit;
    }

    $studentUserId = (int) ($_POST['student_user_id'] ?? 0);
    $applicationId = (int) ($_POST['application_id'] ?? 0);

    // Auto-resolve missing references if referee_id is provided
    if ($applicationId <= 0 && !empty($_POST['referee_id'])) {
        $refId = (int)$_POST['referee_id'];
        $stmtRef = $pdo->prepare("SELECT a.application_id, a.user_id FROM referees r JOIN applications a ON r.application_id = a.application_id WHERE r.referee_id = ? LIMIT 1");
        $stmtRef->execute([$refId]);
        $refRow = $stmtRef->fetch(PDO::FETCH_ASSOC);
        if ($refRow) {
            $applicationId = (int)$refRow['application_id'];
            $studentUserId = (int)$refRow['user_id'];
        }
    }

    if ($applicationId <= 0 && $studentUserId > 0) {
        $stmtApp = $pdo->prepare("SELECT application_id FROM applications WHERE user_id = ? ORDER BY application_id DESC LIMIT 1");
        $stmtApp->execute([$studentUserId]);
        $applicationId = (int)$stmtApp->fetchColumn();
    }

    if ($studentUserId <= 0 && $applicationId > 0) {
        $stmtUser = $pdo->prepare("SELECT user_id FROM applications WHERE application_id = ? LIMIT 1");
        $stmtUser->execute([$applicationId]);
        $studentUserId = (int)$stmtUser->fetchColumn();
    }

    if ($applicationId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Application reference is required.']);
        exit;
    }

    $profile = ($studentUserId > 0) ? fetch_student_profile($pdo, $studentUserId) : null;

    if ($action === 'undo_application') {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE applications SET status = 'Draft', current_status = 'DRAFT', current_step = 9, submitted_at = NULL WHERE application_id = ?");
        $stmt->execute([$applicationId]);

        $stmtProg = $pdo->prepare("DELETE FROM application_progress WHERE application_id = ?");
        $stmtProg->execute([$applicationId]);

        $stmtAP = $pdo->prepare("DELETE FROM admission_processing WHERE application_id = ?");
        $stmtAP->execute([$applicationId]);

        $prev_status = $profile['student']['status'] ?? 'Unknown';
        $adminUserId = (int) ($_SESSION['user_id'] ?? 0);
        log_application_history($pdo, $applicationId, $prev_status, 'DRAFT', $adminUserId, 'SUPER_ADMIN', 'Application reverted to Draft by Super Admin');
        log_audit($pdo, 'Application Undone', $adminUserId, "Application {$applicationId} reverted to Draft (Step 9) by Super Admin");

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Application reverted to Draft (Step 9) successfully.']);
        exit;
    }

    if ($action === 'reset_password') {
        $pdo->beginTransaction();

        $newHash = password_hash('12345678', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $stmt->execute([$newHash, $studentUserId]);

        $adminUserId = (int) ($_SESSION['user_id'] ?? 0);
        log_audit($pdo, 'Password Reset', $adminUserId, "Password for student user ID {$studentUserId} reset to default by Super Admin");

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Password reset successfully to 12345678.']);
        exit;
    }

    if ($action === 'save_referees') {
        $refereeIds = $_POST['referee_id'] ?? [];
        $emails = $_POST['email'] ?? [];
        $phones = $_POST['phone'] ?? [];
        $names = $_POST['full_name'] ?? [];
        $titles = $_POST['title'] ?? [];
        $orgs = $_POST['organization'] ?? [];

        $pdo->beginTransaction();

        for ($i = 0; $i < count($refereeIds); $i++) {
            $refId = (int)$refereeIds[$i];
            $email = trim((string)($emails[$i] ?? ''));
            $phone = trim((string)($phones[$i] ?? ''));
            $fullName = trim((string)($names[$i] ?? ''));
            $title = trim((string)($titles[$i] ?? ''));
            $org = trim((string)($orgs[$i] ?? ''));

            if ($refId > 0) {
                $stmt = $pdo->prepare("UPDATE referees SET email = ?, phone = ?, full_name = ?, title = ?, organization = ? WHERE referee_id = ? AND application_id = ?");
                $stmt->execute([$email, $phone, $fullName, $title, $org, $refId, $applicationId]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Referee contact details updated successfully.']);
        exit;
    }

    if ($action === 'save_biodata') {
        $email = trim((string) ($_POST['email'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));

        if ($email === '') {
            echo json_encode(['success' => false, 'message' => 'Email is required.']);
            exit;
        }

        $emailCheck = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1");
        $emailCheck->execute([$email, $studentUserId]);
        if ($emailCheck->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'Email already belongs to another user.']);
            exit;
        }

        $pdo->beginTransaction();

        $userUpdate = $pdo->prepare("UPDATE users SET email = ?, full_name = ? WHERE user_id = ?");
        $userUpdate->execute([$email, $fullName !== '' ? $fullName : null, $studentUserId]);

        $biodataFields = [
            'surname' => trim((string) ($_POST['surname'] ?? '')),
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'other_name' => trim((string) ($_POST['other_name'] ?? '')),
            'dob' => trim((string) ($_POST['dob'] ?? '')) ?: null,
            'sex' => trim((string) ($_POST['sex'] ?? '')) ?: null,
            'nationality' => trim((string) ($_POST['nationality'] ?? '')) ?: null,
            'state_origin' => trim((string) ($_POST['state_origin'] ?? '')) ?: null,
            'lga' => trim((string) ($_POST['lga'] ?? '')) ?: null,
            'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null,
            'address' => trim((string) ($_POST['address'] ?? '')) ?: null,
        ];

        upsert_single_application_row($pdo, 'personal_details', $applicationId, $biodataFields);

        if (isset($_FILES['passport_photo']) && $_FILES['passport_photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['passport_photo'];
            $max_file_size = 2 * 1024 * 1024;
            if ($file['size'] > $max_file_size) {
                echo json_encode(['success' => false, 'message' => 'Passport photograph file size exceeds 2MB limit.']);
                exit;
            }

            $allowed_mime_types = [
                'image/jpeg' => 'jpg',
                'image/jpg'  => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif'
            ];

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($file['tmp_name']);

            if (!array_key_exists($mime_type, $allowed_mime_types)) {
                echo json_encode(['success' => false, 'message' => 'Invalid image format. Allowed formats: JPG, PNG, GIF.']);
                exit;
            }

            $upload_base_dir = __DIR__ . '/../../../uploads/';
            $target_dir = $upload_base_dir . 'passports/';
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }

            $extension = $allowed_mime_types[$mime_type];
            $filename = sprintf('%s_%s.%s', time(), bin2hex(random_bytes(8)), $extension);
            $target_path = $target_dir . $filename;
            $db_relative_path = 'uploads/passports/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $delStmt = $pdo->prepare("DELETE FROM documents WHERE application_id = ? AND document_type IN ('passport_profile','passport')");
                $delStmt->execute([$applicationId]);

                $stmt = $pdo->prepare("INSERT INTO documents (application_id, document_type, file_path) VALUES (?, 'passport_profile', ?)");
                $stmt->execute([$applicationId, $db_relative_path]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save passport photo upload.']);
                exit;
            }
        }

        // Propagate updated name, email, and phone to student_profiles and supervisor_students
        $profile = fetch_student_profile($pdo, $studentUserId);
        $appNo = $profile['student']['application_number'] ?? '';
        $calcFullName = trim($biodataFields['first_name'] . ' ' . $biodataFields['surname']);
        if ($biodataFields['other_name'] !== '') {
            $calcFullName .= ' ' . $biodataFields['other_name'];
        }
        $finalName = $fullName !== '' ? $fullName : $calcFullName;

        $hasStudentProfiles = false;
        try {
            $pdo->query("SELECT 1 FROM `student_profiles` LIMIT 0");
            $hasStudentProfiles = true;
        } catch (Throwable $e) {}

        if ($hasStudentProfiles) {
            $stmtProfile = $pdo->prepare("UPDATE student_profiles SET full_name = ?, email = ?, phone = ? WHERE student_id = ? OR email = ?");
            $stmtProfile->execute([$finalName, $email, $biodataFields['phone'], $appNo, $profile['student']['email'] ?? '']);
        }

        $hasSupervisorStudents = false;
        try {
            $pdo->query("SELECT 1 FROM `supervisor_students` LIMIT 0");
            $hasSupervisorStudents = true;
        } catch (Throwable $e) {}

        if ($hasSupervisorStudents) {
            $stmtSup = $pdo->prepare("UPDATE supervisor_students SET full_name = ?, email = ? WHERE student_id = ? OR student_user_id = ?");
            $stmtSup->execute([$finalName, $email, $appNo, $studentUserId]);
        }

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Student biodata updated successfully.']);
        exit;
    }

    if ($action === 'save_academics') {
        $facultyId = (int) ($_POST['faculty_id'] ?? 0);
        $departmentId = (int) ($_POST['department_id'] ?? 0);
        $degreeId = (int) ($_POST['degree_id'] ?? 0);
        $modeId = (int) ($_POST['mode_id'] ?? 0);
        $courseId = (int) ($_POST['course_id'] ?? 0);

        $pdo->beginTransaction();

        $programmeFields = [
            'faculty' => $facultyId > 0 ? $facultyId : null,
            'department' => $departmentId > 0 ? $departmentId : null,
            'degree_type' => $degreeId > 0 ? $degreeId : null,
            'mode_of_study' => $modeId > 0 ? $modeId : null,
            'course' => $courseId > 0 ? $courseId : null,
        ];
        upsert_single_application_row($pdo, 'programme_choices', $applicationId, $programmeFields);

        if ($departmentId > 0) {
            $appDeptStmt = $pdo->prepare("UPDATE applications SET department_id = ? WHERE application_id = ?");
            $appDeptStmt->execute([$departmentId, $applicationId]);
        }

        $higherEducationFields = [
            'highest_qualification' => trim((string) ($_POST['highest_qualification'] ?? '')) ?: null,
            'course_study' => trim((string) ($_POST['course_study'] ?? '')) ?: null,
            'institution' => trim((string) ($_POST['institution'] ?? '')) ?: null,
            'grad_year' => (trim((string) ($_POST['grad_year'] ?? '')) !== '') ? (int) $_POST['grad_year'] : null,
            'cgpa' => (trim((string) ($_POST['cgpa'] ?? '')) !== '') ? (float) $_POST['cgpa'] : null,
        ];
        upsert_single_application_row($pdo, 'higher_education', $applicationId, $higherEducationFields);

        $researchFields = [
            'research_area' => trim((string) ($_POST['research_area'] ?? '')) ?: null,
            'reason_for_choosing' => trim((string) ($_POST['reason_for_choosing'] ?? '')) ?: null,
            'statement_of_purpose' => trim((string) ($_POST['statement_of_purpose'] ?? '')) ?: null,
            'career_objectives' => trim((string) ($_POST['career_objectives'] ?? '')) ?: null,
        ];
        upsert_single_application_row($pdo, 'research_details', $applicationId, $researchFields);

        // Fetch course title
        $courseTitle = '';
        if ($courseId > 0) {
            $stmtCourse = $pdo->prepare("SELECT course_title FROM courses WHERE course_id = ?");
            $stmtCourse->execute([$courseId]);
            $courseTitle = $stmtCourse->fetchColumn() ?: '';
        }

        // 1. Update users table's faculty_id and department_id
        $userUpdate = $pdo->prepare("UPDATE users SET department_id = ?, faculty_id = ? WHERE user_id = ?");
        $userUpdate->execute([
            $departmentId > 0 ? $departmentId : null,
            $facultyId > 0 ? $facultyId : null,
            $studentUserId
        ]);

        // Fetch application number
        $stmtAppNum = $pdo->prepare("SELECT application_number FROM applications WHERE application_id = ?");
        $stmtAppNum->execute([$applicationId]);
        $appNo = $stmtAppNum->fetchColumn() ?: '';

        // 2. Update student_profiles if it exists
        if ($courseTitle !== '') {
            $hasStudentProfiles = false;
            try {
                $pdo->query("SELECT 1 FROM `student_profiles` LIMIT 0");
                $hasStudentProfiles = true;
            } catch (Throwable $e) {}

            if ($hasStudentProfiles) {
                $stmtProfile = $pdo->prepare("UPDATE student_profiles SET programme = ? WHERE student_id = ? OR email = (SELECT email FROM users WHERE user_id = ? LIMIT 1)");
                $stmtProfile->execute([$courseTitle, $appNo, $studentUserId]);
            }
        }

        // 3. Update supervisor_students if it exists
        if ($courseTitle !== '') {
            $hasSupervisorStudents = false;
            try {
                $pdo->query("SELECT 1 FROM `supervisor_students` LIMIT 0");
                $hasSupervisorStudents = true;
            } catch (Throwable $e) {}

            if ($hasSupervisorStudents) {
                $stmtSup = $pdo->prepare("UPDATE supervisor_students SET programme = ?, department_id = ? WHERE student_id = ? OR student_user_id = ?");
                $stmtSup->execute([$courseTitle, $departmentId > 0 ? $departmentId : null, $appNo, $studentUserId]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Student academics updated successfully.']);
        exit;
    }

    // ====================================================
    // ADD REFEREE
    // ====================================================
    if ($action === 'add_referee') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        $org = trim((string) ($_POST['organization'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));

        if ($fullName === '' || $email === '') {
            echo json_encode(['success' => false, 'message' => 'Full Name and Email are required.']);
            exit;
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO referees (application_id, full_name, title, organization, email, phone) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$applicationId, $fullName, $title ?: null, $org ?: null, $email, $phone ?: null]);
        $newRefId = (int) $pdo->lastInsertId();
        $adminUserId = (int) ($_SESSION['user_id'] ?? 0);
        log_audit($pdo, 'Add Referee', $adminUserId, "Admin added referee '{$fullName}' ({$email}) to application #{$applicationId}");
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => "Referee '{$fullName}' added successfully. You can now contact them from the Contact Referee tab.", 'referee_id' => $newRefId]);
        exit;
    }

    // ====================================================
    // CONTACT SINGLE REFEREE
    // ====================================================
    if ($action === 'contact_referee') {
        $refereeId = (int) ($_POST['referee_id'] ?? 0);
        if ($refereeId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Referee ID is required.']);
            exit;
        }

        // Check or create a referee_request token
        $checkReq = $pdo->prepare("SELECT token FROM referee_requests WHERE referee_id = ? AND application_id = ? AND status != 'Submitted' LIMIT 1");
        $checkReq->execute([$refereeId, $applicationId]);
        $existing = $checkReq->fetchColumn();

        $adminUserId = (int) ($_SESSION['user_id'] ?? 0);
        if ($existing) {
            $token = $existing;
        } else {
            $request = create_referee_request($pdo, $refereeId, $applicationId, $adminUserId);
            $token = $request['token'];
        }

        $verifyLink = app_absolute_url('APPLICANT/ADMISSIONS/referee_verify.php?token=' . urlencode($token));
        $sent = send_referee_request_email($pdo, $refereeId, $verifyLink);

        if ($sent) {
            echo json_encode(['success' => true, 'message' => 'Verification email sent to the referee successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send email. Please check your mail configuration.']);
        }
        exit;
    }

    // ====================================================
    // CONTACT ALL REFEREES
    // ====================================================
    if ($action === 'contact_all_referees') {
        $refStmt = $pdo->prepare("SELECT referee_id FROM referees WHERE application_id = ?");
        $refStmt->execute([$applicationId]);
        $refereeIds = $refStmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($refereeIds)) {
            echo json_encode(['success' => false, 'message' => 'No referees found for this application.']);
            exit;
        }

        $adminUserId = (int) ($_SESSION['user_id'] ?? 0);
        $sentCount = 0;
        $failCount = 0;

        foreach ($refereeIds as $refId) {
            $refId = (int) $refId;
            $checkReq = $pdo->prepare("SELECT token FROM referee_requests WHERE referee_id = ? AND application_id = ? AND status != 'Submitted' LIMIT 1");
            $checkReq->execute([$refId, $applicationId]);
            $existing = $checkReq->fetchColumn();

            if ($existing) {
                $token = $existing;
            } else {
                $request = create_referee_request($pdo, $refId, $applicationId, $adminUserId);
                $token = $request['token'];
            }

            $verifyLink = app_absolute_url('APPLICANT/ADMISSIONS/referee_verify.php?token=' . urlencode($token));
            $sent = send_referee_request_email($pdo, $refId, $verifyLink);
            if ($sent) {
                $sentCount++;
            } else {
                $failCount++;
            }
        }

        $msg = "Emails sent: {$sentCount}";
        if ($failCount > 0) $msg .= ", Failed: {$failCount}. Check mail configuration.";
        echo json_encode(['success' => $sentCount > 0, 'message' => $msg]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unsupported action.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
