<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../admin/includes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$sessionUserId = (int) $_SESSION['user_id'];
$sessionRole   = $_SESSION['role'] ?? '';

if (!has_permission('ict_processing')) {
    echo json_encode(['success' => false, 'message' => 'Forbidden. Requires ict_processing permission.']);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

/**
 * Build IPESS matric number.
 * Format: IPESS/{DEPT_ABBR}/{DEGREE}/{YEAR}/{SEQ}
 * e.g.  IPESS/SS/MSC/2026/0001
 */
function build_matric(PDO $pdo, int $appId): string {
    $year = date('Y');

    // Get department name
    $stmt = $pdo->prepare("
        SELECT d.dept_name, dt.degree_name
        FROM programme_choices pc
        LEFT JOIN departments d  ON pc.department = d.dept_id
        LEFT JOIN degree_types dt ON pc.degree_type = dt.degree_id
        WHERE pc.application_id = ?
        LIMIT 1
    ");
    $stmt->execute([$appId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $deptName  = $row['dept_name']   ?? '';
    $degreeName = strtoupper($row['degree_name'] ?? 'MSC');
    $degreeName = preg_replace('/[^A-Z0-9]/', '', $degreeName); // e.g. MSC, PGD

    // Extract acronym-style abbreviation: "Department of Social Standards" -> "SS"
    // Take first letter of each significant word (skip "Department", "of", "the", etc.)
    $skipWords = ['department', 'of', 'the', 'and', 'for', 'in'];
    $words     = preg_split('/\s+/', $deptName);
    $abbr      = '';
    foreach ($words as $word) {
        $lw = strtolower($word);
        if (!in_array($lw, $skipWords, true) && strlen($word) > 0) {
            $abbr .= strtoupper($word[0]);
        }
    }
    if (empty($abbr)) {
        $abbr = 'XX';
    }

    // Sequence: count existing matric numbers for this year with same dept/degree prefix
    $prefix = "IPESS/{$abbr}/{$degreeName}/{$year}/";
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM admission_processing
        WHERE matric_number LIKE ?
    ");
    $countStmt->execute([$prefix . '%']);
    $seq    = (int) $countStmt->fetchColumn() + 1;
    $seqStr = str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

    return "{$prefix}{$seqStr}";
}

// ─── GET: list admitted applicants (with or without matric) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $filter   = $_GET['filter']   ?? 'all';   // all | no_matric | with_matric
    $page     = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = 20;
    $offset   = ($page - 1) * $pageSize;

    $where = "WHERE (a.current_status = 'ADMISSION_APPROVED' OR a.status = 'Admitted')";
    if ($filter === 'no_matric')   $where .= " AND (ap.matric_number IS NULL OR ap.matric_number = '')";
    if ($filter === 'with_matric') $where .= " AND (ap.matric_number IS NOT NULL AND ap.matric_number != '')";

    try {
        $countQ = "SELECT COUNT(*) FROM applications a
                   LEFT JOIN admission_processing ap ON ap.application_id = a.application_id
                   $where";
        $total = (int) $pdo->query($countQ)->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT a.application_id, a.application_number, a.current_status,
                   CONCAT(p.first_name, ' ', p.surname) AS full_name,
                   u.email,
                   COALESCE(dt.degree_name, pc.degree_type) AS programme,
                   COALESCE(c.course_title, pc.course)      AS course,
                   COALESCE(d.dept_name, pc.department)     AS department,
                   COALESCE(ap.matric_number, '')           AS matric_number,
                   COALESCE(ap.acceptance_letter_status, 'Inactive') AS acceptance_status,
                   COALESCE(ap.admission_letter_status, 'Inactive')  AS admission_status
            FROM applications a
            LEFT JOIN admission_processing  ap ON ap.application_id  = a.application_id
            LEFT JOIN personal_details      p  ON p.application_id   = a.application_id
            LEFT JOIN users                 u  ON u.user_id           = a.user_id
            LEFT JOIN programme_choices     pc ON pc.application_id  = a.application_id
            LEFT JOIN degree_types          dt ON pc.degree_type      = dt.degree_id
            LEFT JOIN courses               c  ON pc.course           = c.course_id
            LEFT JOIN departments           d  ON pc.department       = d.dept_id
            $where
            ORDER BY p.surname ASC
            LIMIT $pageSize OFFSET $offset
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data'    => $rows,
            'total'   => $total,
            'page'    => $page,
            'pages'   => max(1, (int) ceil($total / $pageSize)),
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ─── POST: bulk generate matric numbers ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk_generate') {
    try {
        // Fetch all approved applicants without matric numbers
        $stmt = $pdo->query("
            SELECT a.application_id
            FROM applications a
            LEFT JOIN admission_processing ap ON ap.application_id = a.application_id
            WHERE (a.current_status = 'ADMISSION_APPROVED' OR a.status = 'Admitted')
              AND (ap.matric_number IS NULL OR ap.matric_number = '')
        ");
        $apps = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $generated = 0;
        $errors    = [];
        foreach ($apps as $appId) {
            try {
                $matric = build_matric($pdo, (int) $appId);
                $ins = $pdo->prepare("
                    INSERT INTO admission_processing (application_id, matric_number, matric_generated_at, matric_generated_by)
                    VALUES (:app_id, :matric, NOW(), :user_id)
                    ON DUPLICATE KEY UPDATE
                        matric_number       = VALUES(matric_number),
                        matric_generated_at = NOW(),
                        matric_generated_by = VALUES(matric_generated_by)
                ");
                $ins->execute([':app_id' => $appId, ':matric' => $matric, ':user_id' => $sessionUserId]);
                $generated++;
            } catch (Exception $e) {
                $errors[] = "App #{$appId}: " . $e->getMessage();
            }
        }
        echo json_encode(['success' => true, 'generated' => $generated, 'errors' => $errors]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ─── POST: activate/deactivate admission & acceptance letters ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'set_letter_status') {
    $appIds     = json_decode($_POST['app_ids'] ?? '[]', true);
    $admitStat  = ($_POST['admission_letter']  ?? 'Active') === 'Active' ? 'Active' : 'Inactive';
    $acceptStat = ($_POST['acceptance_letter'] ?? 'Active') === 'Active' ? 'Active' : 'Inactive';

    if (empty($appIds) || !is_array($appIds)) {
        echo json_encode(['success' => false, 'message' => 'No applications selected.']);
        exit;
    }

    try {
        $updated = 0;
        foreach ($appIds as $rawId) {
            $appId = (int) $rawId;
            if (!$appId) continue;
            $stmt = $pdo->prepare("
                INSERT INTO admission_processing
                    (application_id, admission_letter_status, acceptance_letter_status,
                     admission_letter_activated_at, acceptance_letter_activated_at)
                VALUES
                    (:app_id, :admit, :accept,
                     CASE WHEN :admit2  = 'Active' THEN NOW() ELSE NULL END,
                     CASE WHEN :accept2 = 'Active' THEN NOW() ELSE NULL END)
                ON DUPLICATE KEY UPDATE
                    admission_letter_status     = VALUES(admission_letter_status),
                    acceptance_letter_status    = VALUES(acceptance_letter_status),
                    admission_letter_activated_at  = CASE WHEN VALUES(admission_letter_status)  = 'Active' THEN NOW() ELSE admission_letter_activated_at  END,
                    acceptance_letter_activated_at = CASE WHEN VALUES(acceptance_letter_status) = 'Active' THEN NOW() ELSE acceptance_letter_activated_at END
            ");
            $stmt->execute([
                ':app_id'   => $appId,
                ':admit'    => $admitStat,
                ':accept'   => $acceptStat,
                ':admit2'   => $admitStat,
                ':accept2'  => $acceptStat,
            ]);
            $updated++;
        }
        echo json_encode(['success' => true, 'updated' => $updated]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
