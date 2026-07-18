<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../../includes/status_engine.php';
require_once __DIR__ . '/../../includes/permissions.php';

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

function documents_verified(PDO $pdo, int $applicationId): bool {
    if (table_exists_local($pdo, 'document_verification')) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE application_id = ?");
        $stmt->execute([$applicationId]);
        $total = (int) $stmt->fetchColumn();
        if ($total === 0) {
            return false;
        }
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM documents d
            JOIN document_verification dv ON d.doc_id = dv.upload_id
            WHERE d.application_id = ? AND dv.verification_status = 'Verified'
        ");
        $stmt->execute([$applicationId]);
        $verified = (int) $stmt->fetchColumn();
        return $verified === $total;
    }

    if (table_exists_local($pdo, 'document_verifications')) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE application_id = ?");
        $stmt->execute([$applicationId]);
        $total = (int) $stmt->fetchColumn();
        if ($total === 0) {
            return false;
        }
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM documents d
            JOIN document_verifications dv ON d.doc_id = dv.doc_id
            WHERE d.application_id = ? AND dv.status = 'Verified'
        ");
        $stmt->execute([$applicationId]);
        $verified = (int) $stmt->fetchColumn();
        return $verified === $total;
    }

    if (table_exists_local($pdo, 'application_documents')) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM application_documents WHERE application_id = ?");
        $stmt->execute([$applicationId]);
        $total = (int) $stmt->fetchColumn();
        if ($total === 0) {
            return false;
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM application_documents WHERE application_id = ? AND verified_status = 'Verified'");
        $stmt->execute([$applicationId]);
        $verified = (int) $stmt->fetchColumn();
        return $verified === $total;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE application_id = ?");
    $stmt->execute([$applicationId]);
    return (int) $stmt->fetchColumn() > 0;
}

function olevel_completed(PDO $pdo, int $applicationId): bool {
    if (!table_exists_local($pdo, 'olevel_exams')) {
        return false;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM olevel_exams WHERE application_id = ?");
    $stmt->execute([$applicationId]);
    return (int) $stmt->fetchColumn() > 0;
}

function referees_responded(PDO $pdo, int $applicationId): bool {
    if (table_exists_local($pdo, 'referee_uploads')) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM referee_uploads WHERE application_id = ? AND verified_status = 'Verified'");
        $stmt->execute([$applicationId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    if (table_exists_local($pdo, 'referees')) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM referees WHERE application_id = ?");
        $stmt->execute([$applicationId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    return false;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
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
                SELECT course_id AS id, course_title AS name
                FROM courses
                WHERE dept_id = ? AND degree_id = ?
                ORDER BY course_title ASC
            ");
            $stmt->execute([$departmentId, $programmeId]);
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

    if ($action === 'eligible_students') {
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
                   a.status,
                   CONCAT(p.first_name, ' ', p.surname) AS full_name,
                   COALESCE(f.faculty_name, f2.faculty_name) AS faculty_name,
                   COALESCE(d.dept_name, d2.dept_name) AS dept_name,
                   COALESCE(dt.degree_name, pc.degree_type) AS programme,
                   COALESCE(c.course_title, pc.course) AS course
            FROM applications a
            JOIN personal_details p ON a.application_id = p.application_id
            JOIN programme_choices pc ON a.application_id = pc.application_id
            LEFT JOIN faculties f ON pc.faculty = f.faculty_id
            LEFT JOIN departments d ON pc.department = d.dept_id
            LEFT JOIN departments d2 ON d2.dept_id = COALESCE(pc.department, a.department_id)
            LEFT JOIN faculties f2 ON f2.faculty_id = d2.faculty_id
            LEFT JOIN degree_types dt ON pc.degree_type = dt.degree_id
            LEFT JOIN courses c ON pc.course = c.course_id
            WHERE pc.faculty = ? AND pc.department = ? AND pc.degree_type = ? AND pc.course = ?
              AND a.status NOT IN ('Admitted', 'Rejected')
            ORDER BY p.surname ASC
        ");
        $stmt->execute([$facultyId, $departmentId, $programmeId, $courseId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $eligible = [];
        foreach ($rows as $row) {
            $appId = (int) $row['application_id'];
            $docsOk = documents_verified($pdo, $appId);
            $olevelOk = olevel_completed($pdo, $appId);
            $refOk = referees_responded($pdo, $appId);
            $row['eligible'] = $docsOk && $olevelOk && $refOk;
            $eligible[] = $row;
        }

        echo json_encode(['data' => $eligible]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
