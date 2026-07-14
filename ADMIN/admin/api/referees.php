<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../../../config/urls.php';
require_once __DIR__ . '/../../../includes/status_engine.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../ADMIN/includes/mailer.php';
require_once __DIR__ . '/../../../includes/referee_service.php';
require_once __DIR__ . '/../../../includes/completion_service.php';

if (!isset($_SESSION['role']) || !is_admin_role($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

function table_exists_local(PDO $pdo, string $table): bool {
    try {
        $sanitizedTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $pdo->query("SELECT 1 FROM `{$sanitizedTable}` LIMIT 0");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function send_referee_request_for_row(PDO $pdo, array $data): array {
    $alreadyContacted = !empty($data['has_request']);
    $missingEmail = empty($data['referee_email']);
    if ($alreadyContacted) {
        return ['success' => false, 'status' => 'skipped', 'message' => 'Already contacted.'];
    }
    if ($missingEmail) {
        return ['success' => false, 'status' => 'skipped', 'message' => 'Referee email missing.'];
    }

    $token = bin2hex(random_bytes(20));
    $expires = (new DateTime('+7 days'))->format('Y-m-d H:i:s');

    $pdo->prepare("
        INSERT INTO referee_requests (referee_id, application_id, token, status, requested_by, requested_at, expires_at)
        VALUES (?, ?, ?, 'Requested', ?, NOW(), ?)
    ")->execute([$data['referee_id'], $data['application_id'], $token, $_SESSION['user_id'] ?? null, $expires]);

    $verifyLink = app_absolute_url('referee_verify.php?token=' . $token);
    $subject = 'Referee Verification Request';
    $content = sprintf(
        '<p>Dear %s,</p>
         <p>%s (%s) has nominated you as a referee for postgraduate admission.</p>
         <p>Please complete the referee assessment form and upload your passport and professional credentials using the link below.</p>
         <p><strong>Applicant Ref No:</strong> %s</p>
         <p>Click the button below to proceed.</p>',
        htmlspecialchars($data['referee_name'] ?: 'Referee', ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($data['applicant_name'] ?: 'Applicant', ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($data['applicant_email'] ?: '', ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($data['application_number'] ?: '', ENT_QUOTES, 'UTF-8')
    );

    $mailResult = portal_send_mail(
        $data['referee_email'],
        $data['referee_name'] ?: 'Referee',
        $subject,
        $content,
        'Referee verification request',
        ['cta_label' => 'Verify Referee Request', 'cta_url' => $verifyLink]
    );

    if (!$mailResult['success']) {
        return ['success' => false, 'status' => 'failed', 'message' => $mailResult['message'] ?? 'Unable to send mail.'];
    }

    notify_user($pdo, (int) $data['applicant_user_id'], 'Referee Contacted', 'We have emailed your referee to complete verification.');

    if (!empty($data['applicant_email'])) {
        portal_send_mail(
            $data['applicant_email'],
            $data['applicant_name'] ?: $data['applicant_email'],
            'Referee Request Sent',
            '<p>Your referee has been contacted. Please ensure they respond using the verification link sent to them.</p>',
            'Referee request sent.'
        );
    }

    return ['success' => true, 'status' => 'sent', 'message' => 'Sent'];
}

function fetch_pending_referee_rows(PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT r.referee_id,
               r.full_name AS referee_name,
               r.email AS referee_email,
               a.application_id,
               a.application_number,
               u.user_id AS applicant_user_id,
               u.email AS applicant_email,
               CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.surname, '')) AS applicant_name,
               rr.referee_id AS has_request
        FROM referees r
        JOIN applications a ON r.application_id = a.application_id
        JOIN users u ON a.user_id = u.user_id
        LEFT JOIN personal_details p ON a.application_id = p.application_id
        LEFT JOIN (
            SELECT referee_id, MAX(requested_at) AS last_requested_at
            FROM referee_requests
            GROUP BY referee_id
        ) rr ON rr.referee_id = r.referee_id
        ORDER BY r.referee_id ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$hasRequests = table_exists_local($pdo, 'referee_requests');
$hasUploads = table_exists_local($pdo, 'referee_uploads');

try {
    if ($action === 'contact_all_pending') {
        if (!$hasRequests) {
            echo json_encode(['success' => false, 'message' => 'Referee requests table missing.']);
            exit;
        }

        $rows = fetch_pending_referee_rows($pdo);

        if (!$rows) {
            echo json_encode(['success' => false, 'message' => 'No referees found.']);
            exit;
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $data) {
            $result = send_referee_request_for_row($pdo, $data);
            if (($result['status'] ?? '') === 'sent') {
                $sent++;
            } elseif (($result['status'] ?? '') === 'skipped') {
                $skipped++;
            } else {
                $failed++;
                $errors[] = ($data['referee_email'] ?: ('Referee ID ' . $data['referee_id'])) . ': ' . ($result['message'] ?? 'Unknown error');
            }
        }

        $summary = "Bulk contact complete. Sent: {$sent}, Skipped: {$skipped}, Failed: {$failed}.";
        if (!empty($errors)) {
            $summary .= ' First error: ' . $errors[0];
        }

        echo json_encode([
            'success' => true,
            'message' => $summary,
            'sent' => $sent,
            'skipped' => $skipped,
            'failed' => $failed
        ]);
        exit;
    }

    if ($action === 'pending_contact_list') {
        if (!$hasRequests) {
            echo json_encode(['success' => false, 'message' => 'Referee requests table missing.']);
            exit;
        }
        $rows = fetch_pending_referee_rows($pdo);
        $pending = [];
        foreach ($rows as $row) {
            if (!empty($row['has_request']) || empty($row['referee_email'])) {
                continue;
            }
            $pending[] = [
                'referee_id' => (int) $row['referee_id'],
                'referee_name' => $row['referee_name'] ?: 'Referee',
                'referee_email' => $row['referee_email'] ?: '',
                'application_number' => $row['application_number'] ?: '',
                'applicant_name' => trim((string) ($row['applicant_name'] ?? '')) ?: 'Applicant'
            ];
        }
        echo json_encode(['success' => true, 'data' => $pending, 'total' => count($pending)]);
        exit;
    }

    if ($action === 'contact_pending_referee') {
        if (!$hasRequests) {
            echo json_encode(['success' => false, 'message' => 'Referee requests table missing.']);
            exit;
        }
        $refereeId = (int) ($_POST['referee_id'] ?? 0);
        if ($refereeId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid referee.']);
            exit;
        }
        $rows = fetch_pending_referee_rows($pdo);
        $target = null;
        foreach ($rows as $row) {
            if ((int) ($row['referee_id'] ?? 0) === $refereeId) {
                $target = $row;
                break;
            }
        }
        if (!$target) {
            echo json_encode(['success' => false, 'status' => 'failed', 'message' => 'Referee record not found.']);
            exit;
        }
        $result = send_referee_request_for_row($pdo, $target);
        echo json_encode([
            'success' => (bool) ($result['success'] ?? false),
            'status' => $result['status'] ?? 'failed',
            'message' => $result['message'] ?? 'Unable to process request.'
        ]);
        exit;
    }

    if ($action === 'list') {
        $status = trim($_GET['status'] ?? '');
        $facultyId = (int) ($_GET['faculty_id'] ?? 0);
        $departmentId = (int) ($_GET['department_id'] ?? 0);
        $programmeId = $_GET['programme_id'] ?? '';
        $courseId = $_GET['course_id'] ?? '';

        $where = [];
        $params = [];
        if ($facultyId > 0) {
            $where[] = "pc.faculty = ?";
            $params[] = $facultyId;
        }
        if ($departmentId > 0) {
            $where[] = "pc.department = ?";
            $params[] = $departmentId;
        }
        if ($programmeId !== '') {
            $where[] = "pc.degree_type = ?";
            $params[] = $programmeId;
        }
        if ($courseId !== '') {
            $where[] = "pc.course = ?";
            $params[] = $courseId;
        }
        $whereSql = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

        $joinRequests = $hasRequests ? "LEFT JOIN (
            SELECT rr1.*
            FROM referee_requests rr1
            INNER JOIN (
                SELECT referee_id, MAX(requested_at) AS requested_at
                FROM referee_requests
                GROUP BY referee_id
            ) rr2 ON rr1.referee_id = rr2.referee_id AND rr1.requested_at = rr2.requested_at
        ) rr ON rr.referee_id = r.referee_id" : "";
        $joinUploads = $hasUploads ? "LEFT JOIN (
            SELECT ru1.*
            FROM referee_uploads ru1
            INNER JOIN (
                SELECT referee_id, MAX(submitted_at) AS submitted_at
                FROM referee_uploads
                GROUP BY referee_id
            ) ru2 ON ru1.referee_id = ru2.referee_id AND ru1.submitted_at = ru2.submitted_at
        ) ru ON ru.referee_id = r.referee_id" : "";
        $statusExpr = $hasUploads && $hasRequests
            ? "COALESCE(ru.verified_status, rr.status, 'Pending')"
            : ($hasUploads ? "COALESCE(ru.verified_status, 'Pending')" : ($hasRequests ? "COALESCE(rr.status, 'Pending')" : "'Pending'"));
        $updatedExpr = $hasUploads && $hasRequests
            ? "COALESCE(ru.submitted_at, rr.requested_at, a.submitted_at)"
            : ($hasUploads ? "COALESCE(ru.submitted_at, a.submitted_at)" : ($hasRequests ? "COALESCE(rr.requested_at, a.submitted_at)" : "a.submitted_at"));
        $hasSubmissionExpr = $hasUploads ? "CASE WHEN ru.referee_id IS NOT NULL THEN 1 ELSE 0 END" : "0";

        $stmt = $pdo->prepare("
            SELECT 
                a.application_id,
                a.application_number,
                COALESCE(CONCAT(p.first_name, ' ', p.surname), 'Applicant') AS applicant_name,
                f.faculty_name,
                d.dept_name,
                COALESCE(dt.degree_name, pc.degree_type) AS programme,
                COALESCE(c.course_title, pc.course) AS course,
                r.referee_id,
                r.full_name AS referee_name,
                r.title AS referee_title,
                r.email AS referee_email,
                {$statusExpr} AS status_label,
                {$updatedExpr} AS updated_at,
                {$hasSubmissionExpr} AS has_submission
            FROM applications a
            LEFT JOIN personal_details p ON a.application_id = p.application_id
            LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
            LEFT JOIN degree_types dt ON pc.degree_type = dt.degree_id
            LEFT JOIN courses c ON pc.course = c.course_id
            LEFT JOIN faculties f ON pc.faculty = f.faculty_id
            LEFT JOIN departments d ON pc.department = d.dept_id
            LEFT JOIN referees r ON r.application_id = a.application_id
            {$joinRequests}
            {$joinUploads}
            {$whereSql}
            ORDER BY a.application_id ASC, updated_at DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $appId = (int) ($row['application_id'] ?? 0);
            if ($appId <= 0) continue;
            if (!isset($grouped[$appId])) {
                $grouped[$appId] = [
                    'application_id' => $appId,
                    'application_number' => $row['application_number'] ?? '',
                    'applicant_name' => $row['applicant_name'] ?? '',
                    'faculty' => $row['faculty_name'] ?? '',
                    'department' => $row['dept_name'] ?? '',
                    'programme' => $row['programme'] ?? '',
                    'course' => $row['course'] ?? '',
                    'updated_at' => $row['updated_at'] ? date('M d, Y H:i', strtotime($row['updated_at'])) : null,
                    'referees' => []
                ];
            }
            if (!empty($row['referee_id'])) {
                $grouped[$appId]['referees'][] = [
                    'id' => (int) $row['referee_id'],
                    'name' => $row['referee_name'] ?? 'Referee',
                    'email' => $row['referee_email'] ?? '',
                    'title' => $row['referee_title'] ?? '',
                    'status' => $row['status_label'] ?? 'Pending',
                    'updated_at' => $row['updated_at'] ? date('M d, Y H:i', strtotime($row['updated_at'])) : null
                ];
            }
        }

        $list = array_values($grouped);
        if ($status !== '') {
            $filteredList = [];
            foreach ($list as $app) {
                $refs = $app['referees'] ?? [];
                $filteredRefs = array_values(array_filter($refs, function ($ref) use ($status) {
                    return (string) ($ref['status'] ?? 'Pending') === $status;
                }));
                if (empty($filteredRefs)) {
                    continue;
                }
                $app['referees'] = $filteredRefs;
                $filteredList[] = $app;
            }
            $list = $filteredList;
        }
        $priorityMap = ['Pending' => 1, 'Requested' => 2, 'Submitted' => 3, 'Verified' => 4, 'Rejected' => 5];
        usort($list, function ($a, $b) use ($priorityMap) {
            $aStatuses = array_map(fn($r) => $r['status'] ?? 'Pending', $a['referees']);
            $bStatuses = array_map(fn($r) => $r['status'] ?? 'Pending', $b['referees']);
            $aMin = min(array_map(fn($s) => $priorityMap[$s] ?? 1, $aStatuses)) ?: 1;
            $bMin = min(array_map(fn($s) => $priorityMap[$s] ?? 1, $bStatuses)) ?: 1;
            if ($aMin === $bMin) {
                return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
            }
            return $aMin <=> $bMin;
        });

        echo json_encode(['data' => $list]);
        exit;
    }

    if ($action === 'detail') {
        $applicationId = (int) ($_GET['application_id'] ?? 0);
        if ($applicationId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid applicant.']);
            exit;
        }

        $joinRequests = $hasRequests ? "LEFT JOIN (
            SELECT rr1.*
            FROM referee_requests rr1
            INNER JOIN (
                SELECT referee_id, MAX(requested_at) AS requested_at
                FROM referee_requests
                GROUP BY referee_id
            ) rr2 ON rr1.referee_id = rr2.referee_id AND rr1.requested_at = rr2.requested_at
        ) rr ON rr.referee_id = r.referee_id" : "";
        $joinUploads = $hasUploads ? "LEFT JOIN (
            SELECT ru1.*
            FROM referee_uploads ru1
            INNER JOIN (
                SELECT referee_id, MAX(submitted_at) AS submitted_at
                FROM referee_uploads
                GROUP BY referee_id
            ) ru2 ON ru1.referee_id = ru2.referee_id AND ru1.submitted_at = ru2.submitted_at
        ) ru ON ru.referee_id = r.referee_id" : "";
        $statusExpr = $hasUploads && $hasRequests
            ? "COALESCE(ru.verified_status, rr.status, 'Pending')"
            : ($hasUploads ? "COALESCE(ru.verified_status, 'Pending')" : ($hasRequests ? "COALESCE(rr.status, 'Pending')" : "'Pending'"));

        $stmt = $pdo->prepare("
            SELECT 
                a.application_number,
                CONCAT(p.first_name, ' ', p.surname) AS applicant_name,
                r.referee_id,
                r.full_name AS referee_name,
                r.title AS referee_title,
                r.organization AS referee_org,
                r.email AS referee_email,
                r.phone AS referee_phone,
                " . ($hasUploads ? "
                    ru.work_email,
                    ru.passport_path,
                    ru.work_id_path,
                    ru.referee_name AS submitted_referee_name,
                    ru.referee_title AS submitted_referee_title,
                    ru.referee_organization AS submitted_referee_org,
                    ru.referee_department AS submitted_referee_dept,
                    ru.referee_position AS submitted_referee_pos,
                    ru.referee_address AS submitted_referee_address,
                    ru.referee_phone AS submitted_referee_phone,
                    ru.relationship,
                    ru.years_known,
                    ru.assessment_character_integrity,
                    ru.assessment_professional_competence,
                    ru.assessment_leadership_ability,
                    ru.assessment_communication_skills,
                    ru.assessment_teamwork,
                    ru.assessment_reliability,
                    ru.assessment_initiative,
                    ru.assessment_emotional_stability,
                    ru.major_strengths,
                    ru.weaknesses,
                    ru.recommendation,
                    ru.additional_comments,
                    ru.declaration_accepted,
                    ru.signature,
                    ru.declaration_date,
                " : "
                    NULL AS work_email,
                    NULL AS passport_path,
                    NULL AS work_id_path,
                    NULL AS submitted_referee_name,
                    NULL AS submitted_referee_title,
                    NULL AS submitted_referee_org,
                    NULL AS submitted_referee_dept,
                    NULL AS submitted_referee_pos,
                    NULL AS submitted_referee_address,
                    NULL AS submitted_referee_phone,
                    NULL AS relationship,
                    NULL AS years_known,
                    NULL AS assessment_character_integrity,
                    NULL AS assessment_professional_competence,
                    NULL AS assessment_leadership_ability,
                    NULL AS assessment_communication_skills,
                    NULL AS assessment_teamwork,
                    NULL AS assessment_reliability,
                    NULL AS assessment_initiative,
                    NULL AS assessment_emotional_stability,
                    NULL AS major_strengths,
                    NULL AS weaknesses,
                    NULL AS recommendation,
                    NULL AS additional_comments,
                    NULL AS declaration_accepted,
                    NULL AS signature,
                    NULL AS declaration_date,
                ") . "
                {$statusExpr} AS status_label
            FROM referees r
            LEFT JOIN applications a ON r.application_id = a.application_id
            LEFT JOIN personal_details p ON a.application_id = p.application_id
            {$joinRequests}
            {$joinUploads}
            WHERE a.application_id = ?
            ORDER BY r.referee_id ASC
        ");
        $stmt->execute([$applicationId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            echo json_encode(['success' => false, 'message' => 'Referees not found.']);
            exit;
        }

        $referees = array_map(function ($row) {
            return [
                'referee_id' => (int) $row['referee_id'],
                'name' => $row['referee_name'] ?? '',
                'title' => $row['referee_title'] ?? '',
                'organization' => $row['referee_org'] ?? '',
                'email' => $row['referee_email'] ?? '',
                'phone' => $row['referee_phone'] ?? '',
                'work_email' => $row['work_email'] ?? '',
                'status' => $row['status_label'] ?? 'Pending',
                'passport_path' => !empty($row['passport_path']) ? app_url($row['passport_path']) : '',
                'work_id_path' => !empty($row['work_id_path']) ? app_url($row['work_id_path']) : '',
                
                // Form details
                'submitted_name' => $row['submitted_referee_name'] ?? '',
                'submitted_title' => $row['submitted_referee_title'] ?? '',
                'submitted_org' => $row['submitted_referee_org'] ?? '',
                'submitted_dept' => $row['submitted_referee_dept'] ?? '',
                'submitted_pos' => $row['submitted_referee_pos'] ?? '',
                'submitted_address' => $row['submitted_referee_address'] ?? '',
                'submitted_phone' => $row['submitted_referee_phone'] ?? '',
                'relationship' => $row['relationship'] ?? '',
                'years_known' => $row['years_known'] ?? '',
                
                'assess_character' => $row['assessment_character_integrity'] ?? '',
                'assess_competence' => $row['assessment_professional_competence'] ?? '',
                'assess_leadership' => $row['assessment_leadership_ability'] ?? '',
                'assess_communication' => $row['assessment_communication_skills'] ?? '',
                'assess_teamwork' => $row['assessment_teamwork'] ?? '',
                'assess_reliability' => $row['assessment_reliability'] ?? '',
                'assess_initiative' => $row['assessment_initiative'] ?? '',
                'assess_stability' => $row['assessment_emotional_stability'] ?? '',
                
                'strengths' => $row['major_strengths'] ?? '',
                'weaknesses' => $row['weaknesses'] ?? '',
                'recommendation' => $row['recommendation'] ?? '',
                'additional_comments' => $row['additional_comments'] ?? '',
                'decl_accepted' => $row['declaration_accepted'] ?? 0,
                'signature' => $row['signature'] ?? '',
                'decl_date' => $row['declaration_date'] ?? ''
            ];
        }, $rows);

        echo json_encode([
            'success' => true,
            'data' => [
                'applicant_name' => $rows[0]['applicant_name'] ?? '',
                'application_number' => $rows[0]['application_number'] ?? '',
                'referees' => $referees
            ]
        ]);
        exit;
    }

    if ($action === 'contact_applicant') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        if ($applicationId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid applicant.']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT r.referee_id,
                   r.full_name AS referee_name,
                   r.email AS referee_email,
                   a.application_id,
                   a.application_number,
                   u.user_id AS applicant_user_id,
                   u.email AS applicant_email,
                   CONCAT(p.first_name, ' ', p.surname) AS applicant_name
            FROM referees r
            JOIN applications a ON r.application_id = a.application_id
            JOIN users u ON a.user_id = u.user_id
            JOIN personal_details p ON a.application_id = p.application_id
            WHERE a.application_id = ?
        ");
        $stmt->execute([$applicationId]);
        $refRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$refRows) {
            echo json_encode(['success' => false, 'message' => 'No referees for this applicant.']);
            exit;
        }

        foreach ($refRows as $data) {
            $token = bin2hex(random_bytes(20));
            $expires = (new DateTime('+7 days'))->format('Y-m-d H:i:s');

            $pdo->prepare("
                INSERT INTO referee_requests (referee_id, application_id, token, status, requested_by, requested_at, expires_at)
                VALUES (?, ?, ?, 'Requested', ?, NOW(), ?)
            ")->execute([$data['referee_id'], $data['application_id'], $token, $_SESSION['user_id'] ?? null, $expires]);

            $verifyLink = app_absolute_url('referee_verify.php?token=' . $token);

            $subject = 'Referee Verification Request';
            $content = sprintf(
                '<p>Dear %s,</p>
                 <p>%s (%s) has listed you as a referee for postgraduate admission.</p>
                 <p>Please verify this request by uploading your passport and work ID using the link below.</p>
                 <p><strong>Applicant Ref No:</strong> %s</p>
                 <p>Click the button below to proceed.</p>',
                htmlspecialchars($data['referee_name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($data['applicant_name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($data['applicant_email'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($data['application_number'], ENT_QUOTES, 'UTF-8')
            );
            $mailResult = portal_send_mail(
                $data['referee_email'],
                $data['referee_name'],
                $subject,
                $content,
                'Referee verification request',
                ['cta_label' => 'Verify Referee Request', 'cta_url' => $verifyLink]
            );

            notify_user($pdo, (int) $data['applicant_user_id'], 'Referee Contacted', 'We have emailed your referee to complete verification.');

            if (!empty($data['applicant_email'])) {
                portal_send_mail(
                    $data['applicant_email'],
                    $data['applicant_name'] ?: $data['applicant_email'],
                    'Referee Request Sent',
                    '<p>Your referee has been contacted. Please ensure they respond using the verification link sent to them.</p>',
                    'Referee request sent.'
                );
            }

            if (!$mailResult['success']) {
                echo json_encode(['success' => false, 'message' => $mailResult['message']]);
                exit;
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'faculties') {
        $rows = table_exists_local($pdo, 'faculties')
            ? $pdo->query("SELECT faculty_id AS id, faculty_name AS name FROM faculties ORDER BY faculty_name ASC")->fetchAll(PDO::FETCH_ASSOC)
            : [];
        echo json_encode(['data' => $rows]);
        exit;
    }

    if ($action === 'departments') {
        $facultyId = (int) ($_GET['faculty_id'] ?? 0);
        if ($facultyId <= 0) {
            echo json_encode(['data' => []]);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT DISTINCT d.dept_id AS id, d.dept_name AS name
            FROM programme_choices pc
            JOIN departments d ON pc.department = d.dept_id
            WHERE pc.faculty = ?
            ORDER BY d.dept_name ASC
        ");
        $stmt->execute([$facultyId]);
        echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'programmes') {
        $facultyId = (int) ($_GET['faculty_id'] ?? 0);
        $departmentId = (int) ($_GET['department_id'] ?? 0);
        if ($facultyId <= 0 || $departmentId <= 0) {
            echo json_encode(['data' => []]);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT DISTINCT dt.degree_id AS id, dt.degree_name AS name
            FROM programme_choices pc
            JOIN degree_types dt ON pc.degree_type = dt.degree_id
            WHERE pc.faculty = ? AND pc.department = ?
            ORDER BY dt.degree_name ASC
        ");
        $stmt->execute([$facultyId, $departmentId]);
        echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'courses') {
        $facultyId = (int) ($_GET['faculty_id'] ?? 0);
        $departmentId = (int) ($_GET['department_id'] ?? 0);
        $programmeId = $_GET['programme_id'] ?? '';
        if ($facultyId <= 0 || $departmentId <= 0 || $programmeId === '') {
            echo json_encode(['data' => []]);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT DISTINCT c.course_id AS id, c.course_title AS name
            FROM programme_choices pc
            JOIN courses c ON pc.course = c.course_id
            WHERE pc.faculty = ? AND pc.department = ? AND c.degree_id = ?
            ORDER BY c.course_title ASC
        ");
        $stmt->execute([$facultyId, $departmentId, $programmeId]);
        echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'verify') {
        $refId = (int) ($_POST['referee_id'] ?? 0);
        if ($refId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid referee.']);
            exit;
        }
        if (!$hasUploads) {
            echo json_encode(['success' => false, 'message' => 'Referee uploads table missing.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT application_id FROM referees WHERE referee_id = ? LIMIT 1");
        $stmt->execute([$refId]);
        $appId = (int) $stmt->fetchColumn();
        
        if ($appId > 0) {
            require_once __DIR__ . '/../../../classes/ApplicationProgressManager.php';
            $progManager = new ApplicationProgressManager($pdo);
            $missingStage = null;
            if (!$progManager->canAdvanceToStage($appId, ApplicationProgressManager::STAGE_REFEREES, $missingStage)) {
                echo json_encode(['success' => false, 'message' => "Cannot verify referee reports before the '{$missingStage}' stage is completed."]);
                exit;
            }
        }

        $pdo->prepare("UPDATE referee_uploads SET verified_status = 'Verified', verified_by = ?, verified_at = NOW() WHERE referee_id = ?")
            ->execute([$_SESSION['user_id'] ?? null, $refId]);

        if ($appId > 0) {
            // Update Application Progress
            $stmtProgress = $pdo->prepare("
                INSERT INTO application_progress (application_id, stage, stage_status, stage_updated_at) 
                VALUES (?, 'Referee Report', 'Completed', NOW())
                ON DUPLICATE KEY UPDATE stage_status = 'Completed', stage_updated_at = NOW()
            ");
            $stmtProgress->execute([$appId]);

            // Advance the next stage (Departmental Review) to In Progress
            $progManager->updateStageStatus($appId, ApplicationProgressManager::STAGE_DEPT_REVIEW, ApplicationProgressManager::STATUS_IN_PROGRESS);

            // Update completion weights
            update_completion($pdo, $appId);

            $stmt = $pdo->prepare("SELECT user_id FROM applications WHERE application_id = ? LIMIT 1");
            $stmt->execute([$appId]);
            $userId = (int) $stmt->fetchColumn();
            if ($userId > 0) {
                notify_user($pdo, $userId, 'Referee Verified', 'Your referee submission has been verified.');
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'reject') {
        $refId = (int) ($_POST['referee_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? $_POST['remarks'] ?? '');
        if ($refId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid referee.']);
            exit;
        }
        if (!$hasUploads) {
            echo json_encode(['success' => false, 'message' => 'Referee uploads table missing.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT application_id FROM referees WHERE referee_id = ? LIMIT 1");
        $stmt->execute([$refId]);
        $appId = (int) $stmt->fetchColumn();

        if ($appId > 0) {
            require_once __DIR__ . '/../../../classes/ApplicationProgressManager.php';
            $progManager = new ApplicationProgressManager($pdo);
            $missingStage = null;
            if (!$progManager->canAdvanceToStage($appId, ApplicationProgressManager::STAGE_REFEREES, $missingStage)) {
                echo json_encode(['success' => false, 'message' => "Cannot reject referee reports before the '{$missingStage}' stage is completed."]);
                exit;
            }
        }

        $pdo->prepare("UPDATE referee_uploads SET verified_status = 'Rejected', verified_by = ?, verified_at = NOW(), rejection_reason = ? WHERE referee_id = ?")
            ->execute([$_SESSION['user_id'] ?? null, $reason, $refId]);

        if ($appId > 0) {
            $stmt = $pdo->prepare("SELECT user_id FROM applications WHERE application_id = ? LIMIT 1");
            $stmt->execute([$appId]);
            $userId = (int) $stmt->fetchColumn();
            if ($userId > 0) {
                notify_user($pdo, $userId, 'Referee Rejected', 'Your referee submission was rejected.');
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
