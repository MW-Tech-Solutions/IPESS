<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');

if (!has_permission('manage_students')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied: Requires manage_students permission.']);
    exit;
}

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

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

    return [
        'student' => $student,
        'biodata' => $biodata,
        'academics' => $academics,
        'higher_education' => $higherEducation,
        'research' => $research,
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
    if ($studentUserId <= 0 || $applicationId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Student and application references are required.']);
        exit;
    }

    $profile = fetch_student_profile($pdo, $studentUserId);
    if ((int) ($profile['student']['application_id'] ?? 0) !== $applicationId) {
        echo json_encode(['success' => false, 'message' => 'Application does not match latest student record.']);
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

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Student academics updated successfully.']);
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
