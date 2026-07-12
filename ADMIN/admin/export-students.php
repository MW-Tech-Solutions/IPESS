<?php
/**
 * export-students.php
 * Exports student/applicant data as CSV.
 * GET ?status=all|Admitted|Rejected|Submitted|Pending
 * GET ?type=summary  → exports programme-level summary table instead
 */
session_start();
require_once __DIR__ . '/../../app/helpers/auth.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized access.');
}

$role = $_SESSION['role'] ?? '';
$userId = $_SESSION['user_id'];

if (!has_permission('export_csv', $role, $userId) && !has_permission('export_excel', $role, $userId)) {
    http_response_code(403);
    die('Forbidden. Insufficient permissions to export CSV/Excel.');
}

require_once 'db.php';

$allowedStatuses = ['Admitted', 'Rejected', 'Submitted'];
$statusParam   = $_GET['status'] ?? 'all';
$typeParam     = $_GET['type']   ?? 'students';

/* ── Programme Summary Export ─────────────────────────────── */
if ($typeParam === 'summary') {
    $sql = "
        SELECT
            pc.course                                                          AS Programme,
            pc.department                                                      AS Department,
            COUNT(a.application_id)                                            AS Total_Applications,
            SUM(CASE WHEN a.status = 'Admitted'  THEN 1 ELSE 0 END)          AS Admitted,
            SUM(CASE WHEN a.status = 'Rejected'  THEN 1 ELSE 0 END)          AS Rejected,
            SUM(CASE WHEN a.status = 'Submitted' THEN 1 ELSE 0 END)          AS Pending,
            ROUND(
                SUM(CASE WHEN a.status = 'Admitted' THEN 1 ELSE 0 END)
                / NULLIF(COUNT(a.application_id), 0) * 100, 1
            )                                                                  AS Approval_Rate_Pct
        FROM programme_choices pc
        JOIN applications a ON pc.application_id = a.application_id AND pc.faculty > 0
        GROUP BY pc.course, pc.department
        ORDER BY Total_Applications DESC
    ";
    $rows    = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $headers = ['Programme', 'Department', 'Total Applications', 'Admitted', 'Rejected', 'Pending', 'Approval Rate (%)'];
    $filename = 'programme_summary_' . date('Y-m-d') . '.csv';

/* ── Per-Status / All Students Export ─────────────────────── */
} else {
    $where = '';
    $label = 'all';

    if ($statusParam === 'Submitted') {
        // "Pending" in the UI = Submitted in DB
        $where = "AND a.status = 'Submitted'";
        $label = 'pending';
    } elseif (in_array($statusParam, $allowedStatuses, true)) {
        $where = "AND a.status = " . $pdo->quote($statusParam);
        $label = strtolower($statusParam);
    }

    $sql = "
        SELECT
            a.application_number                    AS Application_Number,
            p.surname                               AS Surname,
            p.first_name                            AS First_Name,
            p.other_names                           AS Other_Names,
            p.gender                                AS Gender,
            p.date_of_birth                         AS Date_of_Birth,
            p.phone                                 AS Phone,
            u.email                                 AS Email,
            pc.course                               AS Programme,
            pc.department                           AS Department,
            pc.faculty                              AS Faculty,
            pc.degree_type                          AS Degree_Type,
            a.status                                AS Status,
            a.current_status                        AS Workflow_Status,
            a.submitted_at                          AS Submitted_At
        FROM applications a
        LEFT JOIN users            u  ON a.user_id        = u.user_id
        LEFT JOIN personal_details p  ON a.application_id = p.application_id
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id AND pc.faculty > 0
        WHERE a.submitted_at IS NOT NULL
        $where
        ORDER BY a.submitted_at DESC
    ";
    $rows    = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $headers = [
        'Application Number', 'Surname', 'First Name', 'Other Names',
        'Gender', 'Date of Birth', 'Phone', 'Email',
        'Programme', 'Department', 'Faculty', 'Degree Type',
        'Status', 'Workflow Status', 'Submitted At'
    ];
    $filename = 'students_' . $label . '_' . date('Y-m-d') . '.csv';
}

/* ── Stream CSV ────────────────────────────────────────────── */
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// UTF-8 BOM so Excel opens it correctly
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, $headers);
foreach ($rows as $row) {
    fputcsv($out, array_values($row));
}

fclose($out);
exit();
