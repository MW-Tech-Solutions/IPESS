<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../../../includes/status_engine.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../ADMIN/includes/mailer.php';
require_once __DIR__ . '/../../../config/urls.php';

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

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($action === 'faculties') {
        if (table_exists_local($pdo, 'faculties')) {
            $rows = $pdo->query("SELECT faculty_id AS id, faculty_name AS name FROM faculties ORDER BY faculty_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $rows = [];
        }
        echo json_encode(['data' => $rows]);
        exit;
    }

    if ($action === 'departments') {
        $facultyId = (int) ($_GET['faculty_id'] ?? 0);
        if ($facultyId <= 0) {
            echo json_encode(['data' => []]);
            exit;
        }
        if (table_exists_local($pdo, 'departments')) {
            $stmt = $pdo->prepare("
                SELECT DISTINCT d.dept_id AS id, d.dept_name AS name
                FROM programme_choices pc
                JOIN departments d ON pc.department = d.dept_id
                WHERE pc.faculty = ?
                ORDER BY d.dept_name ASC
            ");
            $stmt->execute([$facultyId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $rows = [];
        }
        echo json_encode(['data' => $rows]);
        exit;
    }

    if ($action === 'programmes') {
        $facultyId = (int) ($_GET['faculty_id'] ?? 0);
        $departmentId = (int) ($_GET['department_id'] ?? 0);
        if ($facultyId <= 0 || $departmentId <= 0) {
            echo json_encode(['data' => []]);
            exit;
        }

        if (table_exists_local($pdo, 'degree_types')) {
            $stmt = $pdo->prepare("
                SELECT DISTINCT dt.degree_id AS id, dt.degree_name AS name
                FROM programme_choices pc
                JOIN degree_types dt ON pc.degree_type = dt.degree_id
                WHERE pc.faculty = ? AND pc.department = ?
                ORDER BY dt.degree_name ASC
            ");
            $stmt->execute([$facultyId, $departmentId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare("
                SELECT DISTINCT pc.degree_type AS id, pc.degree_type AS name
                FROM programme_choices pc
                WHERE pc.faculty = ? AND pc.department = ?
                ORDER BY pc.degree_type ASC
            ");
            $stmt->execute([$facultyId, $departmentId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(['data' => $rows]);
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

        if (table_exists_local($pdo, 'courses')) {
            $stmt = $pdo->prepare("
                SELECT DISTINCT c.course_id AS id, c.course_title AS name
                FROM programme_choices pc
                JOIN courses c ON pc.course = c.course_id
                WHERE pc.faculty = ? AND pc.department = ? AND pc.degree_type = ?
                ORDER BY c.course_title ASC
            ");
            $stmt->execute([$facultyId, $departmentId, $programmeId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare("
                SELECT DISTINCT pc.course AS id, pc.course AS name
                FROM programme_choices pc
                WHERE pc.faculty = ? AND pc.department = ? AND pc.degree_type = ?
                ORDER BY pc.course ASC
            ");
            $stmt->execute([$facultyId, $departmentId, $programmeId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(['data' => $rows]);
        exit;
    }

    if ($action === 'stats') {
        $facultyId = (int) ($_GET['faculty_id'] ?? 0);
        $departmentId = (int) ($_GET['department_id'] ?? 0);
        $programmeId = $_GET['programme_id'] ?? '';
        $courseId = $_GET['course_id'] ?? '';

        $where = [];
        $params = [];
        if ($facultyId > 0) { $where[] = 'pc.faculty = ?'; $params[] = $facultyId; }
        if ($departmentId > 0) { $where[] = 'pc.department = ?'; $params[] = $departmentId; }
        if ($programmeId !== '') { $where[] = 'pc.degree_type = ?'; $params[] = $programmeId; }
        if ($courseId !== '') { $where[] = 'pc.course = ?'; $params[] = $courseId; }

        $filterSql = $where ? 'AND ' . implode(' AND ', $where) : '';
        $adminId = $_SESSION['user_id'] ?? 0;

        $totalApplicants = 0;
        $verifiedApplicants = 0;

        if (table_exists_local($pdo, 'documents') && table_exists_local($pdo, 'document_verification')) {
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT a.application_id)
                FROM applications a
                JOIN programme_choices pc ON a.application_id = pc.application_id
                JOIN documents d ON d.application_id = a.application_id
                WHERE 1=1 {$filterSql}
            ");
            $stmt->execute($params);
            $totalApplicants = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT a.application_id)
                FROM applications a
                JOIN programme_choices pc ON a.application_id = pc.application_id
                JOIN documents d ON d.application_id = a.application_id
                JOIN document_verification dv ON dv.upload_id = d.doc_id
                WHERE dv.verification_status = 'Verified'
                  AND dv.verified_by = ?
                  {$filterSql}
            ");
            $stmt->execute(array_merge([$adminId], $params));
            $verifiedApplicants = (int) $stmt->fetchColumn();
        }

        $rate = $totalApplicants > 0 ? round(($verifiedApplicants / $totalApplicants) * 100) : 0;
        echo json_encode([
            'success' => true,
            'data' => [
                'total_applicants' => $totalApplicants,
                'verified_applicants' => $verifiedApplicants,
                'rate' => $rate
            ]
        ]);
        exit;
    }

    if ($action === 'students') {
        $facultyId = (int) ($_GET['faculty_id'] ?? 0);
        $departmentId = (int) ($_GET['department_id'] ?? 0);
        $programmeId = $_GET['programme_id'] ?? '';
        $courseId = $_GET['course_id'] ?? '';
        if ($facultyId <= 0 || $departmentId <= 0 || $programmeId === '' || $courseId === '') {
            echo json_encode(['data' => []]);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT a.application_id,
                   a.application_number,
                   a.current_status,
                   a.status,
                   CONCAT(p.first_name, ' ', p.surname) AS full_name,
                   COALESCE(dt.degree_name, pc.degree_type) AS programme,
                   COALESCE(c.course_title, pc.course) AS course
            FROM applications a
            JOIN personal_details p ON a.application_id = p.application_id
            JOIN programme_choices pc ON a.application_id = pc.application_id
            LEFT JOIN degree_types dt ON pc.degree_type = dt.degree_id
            LEFT JOIN courses c ON pc.course = c.course_id
            WHERE pc.faculty = ? AND pc.department = ? AND pc.degree_type = ? AND pc.course = ?
            GROUP BY a.application_id
            ORDER BY p.surname ASC
        ");
        $stmt->execute([$facultyId, $departmentId, $programmeId, $courseId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['data' => $rows]);
        exit;
    }

    if ($action === 'student_detail') {
        $applicationId = (int) ($_GET['application_id'] ?? 0);
        if ($applicationId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid applicant.']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT a.application_number,
                   a.completion_percentage,
                   a.current_status,
                   a.status,
                   CONCAT(p.first_name, ' ', p.surname) AS full_name,
                   COALESCE(u.email, aa.email) AS email,
                   f.faculty_name,
                   d.dept_name,
                   COALESCE(dt.degree_name, pc.degree_type) AS programme,
                   COALESCE(c.course_title, pc.course) AS course
            FROM applications a
            JOIN personal_details p ON a.application_id = p.application_id
            LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
            LEFT JOIN degree_types dt ON pc.degree_type = dt.degree_id
            LEFT JOIN courses c ON pc.course = c.course_id
            LEFT JOIN faculties f ON pc.faculty = f.faculty_id
            LEFT JOIN departments d ON pc.department = d.dept_id
            LEFT JOIN users u ON a.user_id = u.user_id
            LEFT JOIN applicant_accounts aa ON aa.user_id = a.user_id
            WHERE a.application_id = ?
            LIMIT 1
        ");
        $stmt->execute([$applicationId]);
        $base = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $degrees = [];
        if (table_exists_local($pdo, 'higher_education')) {
            $stmt = $pdo->prepare("SELECT highest_qualification, institution, grad_year, cgpa, course_study FROM higher_education WHERE application_id = ?");
            $stmt->execute([$applicationId]);
            $degrees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $olevel = [];
        if (table_exists_local($pdo, 'olevel_exams')) {
            $stmt = $pdo->prepare("SELECT id, exam_type, exam_year, sitting_number, exam_number FROM olevel_exams WHERE application_id = ? ORDER BY sitting_number ASC");
            $stmt->execute([$applicationId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                $examIds = array_column($rows, 'id');
                $resultsMap = [];
                if (!empty($examIds) && table_exists_local($pdo, 'olevel_results')) {
                    $placeholders = implode(',', array_fill(0, count($examIds), '?'));
                    $resStmt = $pdo->prepare("SELECT exam_id, subject_name, grade FROM olevel_results WHERE exam_id IN ({$placeholders})");
                    $resStmt->execute($examIds);
                    foreach ($resStmt->fetchAll(PDO::FETCH_ASSOC) as $res) {
                        $resultsMap[$res['exam_id']][] = $res;
                    }
                }
                foreach ($rows as $exam) {
                    $exam['results'] = $resultsMap[$exam['id']] ?? [];
                    $olevel[] = $exam;
                }
            }
        }

        $topic = '';
        if (table_exists_local($pdo, 'research_details')) {
            $stmt = $pdo->prepare("SELECT research_area FROM research_details WHERE application_id = ? LIMIT 1");
            $stmt->execute([$applicationId]);
            $topic = $stmt->fetchColumn() ?: '';
        }

        $refStatus = 'Pending';
        if (table_exists_local($pdo, 'referee_uploads')) {
            $stmt = $pdo->prepare("SELECT verified_status FROM referee_uploads WHERE application_id = ? ORDER BY submitted_at DESC LIMIT 1");
            $stmt->execute([$applicationId]);
            $refStatus = $stmt->fetchColumn() ?: 'Pending';
        }

        $docStatus = 'Pending';
        $docVerified = 0;
        $docTotal = 0;
        if (table_exists_local($pdo, 'document_verification')) {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(d.doc_id) AS total_docs,
                    SUM(CASE WHEN dv.verification_status = 'Verified' THEN 1 ELSE 0 END) AS verified_docs
                FROM documents d
                LEFT JOIN document_verification dv ON dv.upload_id = d.doc_id
                WHERE d.application_id = ?
            ");
            $stmt->execute([$applicationId]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($doc && (int) $doc['total_docs'] > 0) {
                $docVerified = (int) $doc['verified_docs'];
                $docTotal = (int) $doc['total_docs'];
                $docStatus = $docVerified . '/' . $docTotal . ' Verified';
            }
        }

        $documents = [];
        if (table_exists_local($pdo, 'documents')) {
            $stmt = $pdo->prepare("
                SELECT d.doc_id, d.document_type, d.file_path, d.uploaded_at,
                       COALESCE(dv.verification_status, 'Pending') AS status,
                       dv.admin_remark AS comments
                FROM documents d
                LEFT JOIN document_verification dv ON dv.upload_id = d.doc_id
                WHERE d.application_id = ?
                ORDER BY d.uploaded_at DESC
            ");
            $stmt->execute([$applicationId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $row['file_url'] = app_url($row['file_path'] ?? '');
                $documents[] = $row;
            }
        }

        $cvDoc = null;
        foreach ($documents as $docRow) {
            $type = strtolower((string) ($docRow['document_type'] ?? ''));
            if (preg_match('/\bcv\b|curriculum|resume/', $type)) {
                $cvDoc = $docRow;
                break;
            }
        }

        $referees = [];
        if (table_exists_local($pdo, 'referees')) {
            $stmt = $pdo->prepare("SELECT full_name, title, organization, email, phone FROM referees WHERE application_id = ? ORDER BY referee_id ASC");
            $stmt->execute([$applicationId]);
            $referees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'full_name' => $base['full_name'] ?? '',
            'application_number' => $base['application_number'] ?? '',
            'email' => $base['email'] ?? '',
            'faculty' => $base['faculty_name'] ?? '',
            'department' => $base['dept_name'] ?? '',
            'programme' => $base['programme'] ?? '',
            'course' => $base['course'] ?? '',
            'degrees' => $degrees,
            'olevel' => $olevel,
            'topic' => $topic,
            'completion' => $base['completion_percentage'] ?? 0,
            'referee_status' => $refStatus,
            'document_status' => $docStatus,
            'document_verified_count' => $docVerified,
            'document_total' => $docTotal,
            'documents' => $documents,
            'cv_document' => $cvDoc,
            'referees' => $referees,
            'current_status' => $base['current_status'] ?? '',
            'status' => $base['status'] ?? ''
        ]);
        exit;
    }

    if ($action === 'accept') {
        $appId = (int) ($_POST['application_id'] ?? 0);
        if ($appId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid application.']);
            exit;
        }

        update_application_status($pdo, $appId, 'SUBMITTED', [
            'actor_id' => $_SESSION['user_id'] ?? null,
            'actor_role' => $_SESSION['role'] ?? 'ADMIN',
            'note' => 'Application accepted for processing'
        ]);

        $stmt = $pdo->prepare("SELECT u.user_id FROM applications a JOIN users u ON a.user_id = u.user_id WHERE a.application_id = ? LIMIT 1");
        $stmt->execute([$appId]);
        $userId = (int) $stmt->fetchColumn();
        if ($userId > 0) {
            notify_user($pdo, $userId, 'Submitted', 'Your application is currently being processed.');
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'reject') {
        $appId = (int) ($_POST['application_id'] ?? 0);
        if ($appId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid application.']);
            exit;
        }

        update_application_status($pdo, $appId, 'ADMISSION_REJECTED', [
            'actor_id' => $_SESSION['user_id'] ?? null,
            'actor_role' => $_SESSION['role'] ?? 'ADMIN',
            'note' => 'Application rejected in Academic Review'
        ]);

        $stmt = $pdo->prepare("SELECT u.user_id FROM applications a JOIN users u ON a.user_id = u.user_id WHERE a.application_id = ? LIMIT 1");
        $stmt->execute([$appId]);
        $userId = (int) $stmt->fetchColumn();
        if ($userId > 0) {
            notify_user($pdo, $userId, 'Application Rejected', 'Your application has been rejected.');
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'bulk') {
        $bulkAction = $_POST['bulk_action'] ?? '';
        $ids = json_decode($_POST['ids'] ?? '[]', true);
        if (!$bulkAction || !is_array($ids) || count($ids) === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid bulk action.']);
            exit;
        }
        foreach ($ids as $id) {
            $appId = (int) $id;
            if ($appId <= 0) {
                continue;
            }
            if ($bulkAction === 'mark_reviewed') {
                update_application_status($pdo, $appId, 'UNDER_DEPT_REVIEW', [
                    'actor_id' => $_SESSION['user_id'] ?? null,
                    'actor_role' => $_SESSION['role'] ?? 'ADMIN',
                    'note' => 'Marked reviewed in Academic Review'
                ]);
            } elseif ($bulkAction === 'request_docs') {
                update_application_status($pdo, $appId, 'ACTION_REQUIRED_DOCS', [
                    'actor_id' => $_SESSION['user_id'] ?? null,
                    'actor_role' => $_SESSION['role'] ?? 'ADMIN',
                    'note' => 'Requested documents in Academic Review'
                ]);
            } elseif ($bulkAction === 'accept_application') {
                update_application_status($pdo, $appId, 'SUBMITTED', [
                    'actor_id' => $_SESSION['user_id'] ?? null,
                    'actor_role' => $_SESSION['role'] ?? 'ADMIN',
                    'note' => 'Application accepted for processing'
                ]);
                $stmt = $pdo->prepare("SELECT u.user_id, u.email, CONCAT(p.first_name, ' ', p.surname) AS name FROM applications a JOIN users u ON a.user_id = u.user_id JOIN personal_details p ON a.application_id = p.application_id WHERE a.application_id = ? LIMIT 1");
                $stmt->execute([$appId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['user_id'])) {
                    notify_user($pdo, (int) $row['user_id'], 'Submitted', 'Your application is currently being processed.');
                }
            } elseif ($bulkAction === 'reject_application') {
                update_application_status($pdo, $appId, 'ADMISSION_REJECTED', [
                    'actor_id' => $_SESSION['user_id'] ?? null,
                    'actor_role' => $_SESSION['role'] ?? 'ADMIN',
                    'note' => 'Application rejected'
                ]);
                $stmt = $pdo->prepare("SELECT u.user_id, u.email, CONCAT(p.first_name, ' ', p.surname) AS name FROM applications a JOIN users u ON a.user_id = u.user_id JOIN personal_details p ON a.application_id = p.application_id WHERE a.application_id = ? LIMIT 1");
                $stmt->execute([$appId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['email'])) {
                    notify_user($pdo, (int) ($row['user_id'] ?? 0), 'Application Rejected', 'Your application has been rejected.');
                    portal_send_mail(
                        $row['email'],
                        $row['name'] ?: $row['email'],
                        'Application Rejected',
                        '<p>Your postgraduate application has been rejected. Please contact the admissions office for clarification.</p>',
                        'Application rejected.'
                    );
                }
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
