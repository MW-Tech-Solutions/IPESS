<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');

if (!has_permission('reports')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied: Requires reports permission.']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$dompdfAvailable = false;
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
    $dompdfAvailable = class_exists('Dompdf\\Dompdf');
}

header('Content-Type: application/json');

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}

function json_error(string $message, int $code = 500): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function ensure_reports_table(PDO $pdo): bool {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_reports (
            report_id INT AUTO_INCREMENT PRIMARY KEY,
            report_name VARCHAR(255) NOT NULL,
            report_type VARCHAR(100) NOT NULL,
            format VARCHAR(20) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            generated_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

if (!ensure_reports_table($pdo)) {
    json_error('Reports table unavailable.');
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'list') {
    try {
        $reports = $pdo->query("
            SELECT report_id, report_name, report_type, format, file_path, created_at
            FROM admin_reports
            ORDER BY created_at DESC
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reports as &$report) {
            $report['view_url'] = 'report-file.php?id=' . (int) $report['report_id'] . '&mode=view';
            $report['download_url'] = 'report-file.php?id=' . (int) $report['report_id'] . '&mode=download';
        }
        unset($report);

        echo json_encode(['success' => true, 'data' => $reports]);
        exit;
    } catch (PDOException $e) {
        json_error('Unable to load reports.');
    }
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id === 0) {
        json_error('Invalid report id.', 400);
    }

    try {
        $stmt = $pdo->prepare("SELECT file_path FROM admin_reports WHERE report_id = ?");
        $stmt->execute([$id]);
        $filePath = $stmt->fetchColumn();
        if ($filePath) {
            $fullPath = __DIR__ . '/../' . ltrim($filePath, '/');
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }
        $deleteStmt = $pdo->prepare("DELETE FROM admin_reports WHERE report_id = ?");
        $deleteStmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        json_error('Unable to delete report.');
    }
}

if ($action === 'search_students') {
    $query = trim($_GET['query'] ?? '');
    if ($query === '') {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    try {
        $searchTerm = '%' . $query . '%';
        $stmt = $pdo->prepare("
            SELECT a.application_id, a.application_number, 
                   CONCAT(COALESCE(pd.surname,''), ' ', COALESCE(pd.first_name,'')) AS full_name
            FROM applications a
            LEFT JOIN personal_details pd ON pd.application_id = a.application_id
            WHERE a.application_number LIKE ? 
               OR pd.surname LIKE ? 
               OR pd.first_name LIKE ?
            LIMIT 10
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $results]);
        exit;
    } catch (PDOException $e) {
        json_error('Error performing search.');
    }
}

if ($action === 'download_individual_package') {
    $applicationId = (int)($_GET['app_id'] ?? $_POST['app_id'] ?? 0);
    $includeSlip = (bool)($_GET['include_slip'] ?? $_POST['include_slip'] ?? 0);
    $includeAdmission = (bool)($_GET['include_admission'] ?? $_POST['include_admission'] ?? 0);
    $includeAcceptance = (bool)($_GET['include_acceptance'] ?? $_POST['include_acceptance'] ?? 0);
    $includeDocs = (bool)($_GET['include_docs'] ?? $_POST['include_docs'] ?? 0);

    if ($applicationId <= 0) {
        json_error('Invalid application ID.', 400);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT a.application_id, a.application_number, a.status, a.current_status,
                   pd.first_name, pd.surname
            FROM applications a
            LEFT JOIN personal_details pd ON pd.application_id = a.application_id
            WHERE a.application_id = ?
            LIMIT 1
        ");
        $stmt->execute([$applicationId]);
        $applicant = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$applicant) {
            json_error('Applicant not found.', 404);
        }

        $appNo = $applicant['application_number'] ?: 'N_A';
        
        $pdfContent = generateStudentDossier($pdo, $applicant, $includeSlip, $includeAdmission, $includeAcceptance, $includeDocs, $dompdfAvailable);
        if (!$pdfContent) {
            json_error('No document files available to generate dossier.');
        }

        $filename = str_replace('/', '_', $appNo) . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $pdfContent;
        exit;
    } catch (Throwable $e) {
        json_error('Package download error: ' . $e->getMessage());
    }
}

if ($action !== 'generate') {
    json_error('Unsupported action.', 400);
}

$reportsDir = __DIR__ . '/../reports';
if (!is_dir($reportsDir) && !mkdir($reportsDir, 0775, true)) {
    json_error('Unable to create reports directory.');
}
if (!is_writable($reportsDir)) {
    json_error('Reports directory is not writable.');
}

$format = strtoupper(trim($_POST['format'] ?? 'PDF'));
$reportType = trim($_POST['report_type'] ?? 'Admissions Summary');
$allowedFormats = ['PDF', 'EXCEL', 'DOSSIERS_ZIP'];
$format = in_array($format, $allowedFormats, true) ? $format : 'PDF';

$baseName = 'report_' . date('Ymd_His') . '_' . str_replace('.', '', uniqid('', true));
$ext = ($format === 'PDF') ? '.pdf' : (($format === 'DOSSIERS_ZIP') ? '.zip' : '.csv');
$relativePath = 'reports/' . $baseName . $ext;
$fullPath = __DIR__ . '/../' . $relativePath;

if ($format === 'DOSSIERS_ZIP') {
    $zip = new ZipArchive();
    if ($zip->open($fullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        json_error('Unable to create ZIP file.');
    }
    
    $filters = $_POST;
    $where = [];
    $params = [];
    if (!empty($filters['college_id'])) {
        $where[] = 'pc.faculty = ?';
        $params[] = $filters['college_id'];
    }
    if (!empty($filters['department_id'])) {
        $where[] = 'pc.department = ?';
        $params[] = $filters['department_id'];
    }
    if (!empty($filters['degree_id'])) {
        $where[] = 'pc.degree_type = ?';
        $params[] = $filters['degree_id'];
    }
    if (!empty($filters['status'])) {
        $where[] = 'a.status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['state'])) {
        $where[] = 'pd.state_origin = ?';
        $params[] = $filters['state'];
    }
    if (!empty($filters['lga'])) {
        $where[] = 'pd.lga = ?';
        $params[] = $filters['lga'];
    }
    
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    
    $sql = "
        SELECT a.application_id, a.application_number, a.status, a.current_status,
               pd.first_name, pd.surname
        FROM applications a
        INNER JOIN users u ON a.user_id = u.user_id
        LEFT JOIN personal_details pd ON pd.application_id = a.application_id
        LEFT JOIN programme_choices pc ON pc.application_id = a.application_id
        $whereSql
        GROUP BY a.application_id
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $applicants = [];
    }
    
    $filesAdded = 0;
    foreach ($applicants as $applicant) {
        $pdfContent = generateStudentDossier($pdo, $applicant, true, true, true, true, $dompdfAvailable);
        if ($pdfContent) {
            $filename = str_replace('/', '_', $applicant['application_number']) . '.pdf';
            $zip->addFromString($filename, $pdfContent);
            $filesAdded++;
        }
    }
    
    $zip->close();
    
    if ($filesAdded === 0) {
        @unlink($fullPath);
        json_error('No dossiers could be generated for matching criteria.');
    }
} else {
    $reportData = buildSuperAdminReportData($pdo, $reportType, $_POST);
    $lines = buildReportLines($reportData);

    if ($format === 'EXCEL') {
        $handle = fopen($fullPath, 'w');
        if (!$handle) {
            json_error('Unable to write report file.');
        }
        foreach ($reportData['sections'] as $section) {
            fputcsv($handle, [$section['title']]);
            fputcsv($handle, $section['headers']);
            foreach ($section['rows'] as $row) {
                fputcsv($handle, $row);
            }
            fputcsv($handle, []);
        }
        fclose($handle);
    } else {
        if ($dompdfAvailable) {
            try {
                $html = buildReportHtml($reportData);
                $dompdf = new Dompdf\Dompdf();
                $dompdf->loadHtml($html);
            $orientation = in_array(strtolower($reportType), ['student admissions', 'staff records'], true) ? 'landscape' : 'portrait';
            $dompdf->setPaper('A4', $orientation);
            $dompdf->render();
            if (file_put_contents($fullPath, $dompdf->output()) === false) {
                json_error('Unable to write report file.');
            }
        } catch (Throwable $e) {
            error_log("Dompdf failed in super-admin/reports: " . $e->getMessage() . ". Falling back to simple PDF.");
            $pdf = buildSimplePdf($lines);
            if (file_put_contents($fullPath, $pdf) === false) {
                json_error('Unable to write report file.');
            }
        }
    } else {
        $pdf = buildSimplePdf($lines);
        if (file_put_contents($fullPath, $pdf) === false) {
            json_error('Unable to write report file.');
        }
    }
}

$reportName = $reportType . ' - ' . date('M d, Y H:i');
$generatedBy = $_SESSION['user_id'] ?? null;
if ($generatedBy === null) {
    try {
        $generatedBy = $pdo->query("SELECT user_id FROM users ORDER BY user_id ASC LIMIT 1")->fetchColumn();
    } catch (PDOException $e) {
        $generatedBy = null;
    }
}

$reportId = null;
try {
    $insert = $pdo->prepare("
        INSERT INTO admin_reports (report_name, report_type, format, file_path, generated_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $insert->execute([$reportName, $reportType, $format, $relativePath, $generatedBy]);
    $reportId = (int) $pdo->lastInsertId();
} catch (PDOException $e) {
    $reportId = null;
}

if ($reportId) {
    $viewUrl = 'report-file.php?id=' . $reportId . '&mode=view';
    $downloadUrl = 'report-file.php?id=' . $reportId . '&mode=download';
} else {
    $viewUrl = $relativePath;
    $downloadUrl = $relativePath;
}

echo json_encode([
    'success' => true,
    'file_path' => $relativePath,
    'view_url' => $viewUrl,
    'download_url' => $downloadUrl,
    'report_id' => $reportId,
]);


function safe_scalar(PDO $pdo, string $sql)
{
    try {
        return $pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function safe_rows(PDO $pdo, string $sql): array
{
    try {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function buildSuperAdminReportData(PDO $pdo, string $reportType, array $filters = []): array
{
    $type = strtolower(trim($reportType));
    $generated = date('M d, Y H:i');
    $sections = [];

    if ($type === 'student admissions') {
        $where = [];
        $params = [];
        if (!empty($filters['college_id'])) {
            $where[] = 'pc.faculty = ?';
            $params[] = $filters['college_id'];
        }
        if (!empty($filters['department_id'])) {
            $where[] = 'pc.department = ?';
            $params[] = $filters['department_id'];
        }
        if (!empty($filters['degree_id'])) {
            $where[] = 'pc.degree_type = ?';
            $params[] = $filters['degree_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'a.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['state'])) {
            $where[] = 'pd.state_origin = ?';
            $params[] = $filters['state'];
        }
        if (!empty($filters['lga'])) {
            $where[] = 'pd.lga = ?';
            $params[] = $filters['lga'];
        }
        
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        
        $sql = "
            SELECT 
                a.application_number,
                CONCAT(COALESCE(pd.surname,''), ' ', COALESCE(pd.first_name,''), ' ', COALESCE(pd.other_name,'')) AS full_name,
                u.email,
                COALESCE(pd.phone, '') AS phone,
                COALESCE(pd.state_origin, '') AS state_origin,
                COALESCE(pd.lga, '') AS lga,
                COALESCE(f.faculty_name, '') AS faculty_name,
                COALESCE(d.dept_name, '') AS dept_name,
                a.status,
                COALESCE(dt.degree_name, '') AS degree_name
            FROM applications a
            INNER JOIN users u ON a.user_id = u.user_id
            LEFT JOIN personal_details pd ON pd.application_id = a.application_id
            LEFT JOIN programme_choices pc ON pc.application_id = a.application_id
            LEFT JOIN faculties f ON pc.faculty = f.faculty_id
            LEFT JOIN departments d ON pc.department = d.dept_id
            LEFT JOIN degree_types dt ON pc.degree_type = dt.degree_id
            $whereSql
            GROUP BY a.application_id
            ORDER BY full_name ASC
        ";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $rows = [];
        }
        
        $tableRows = [];
        foreach ($rows as $r) {
            $tableRows[] = [
                (string)$r['application_number'],
                (string)$r['full_name'],
                (string)$r['email'],
                (string)$r['phone'],
                (string)($r['state_origin'] . ($r['lga'] ? ' / ' . $r['lga'] : '')),
                (string)($r['faculty_name'] . ($r['dept_name'] ? ' / ' . $r['dept_name'] : '')),
                (string)$r['degree_name'],
                (string)$r['status']
            ];
        }
        if (empty($tableRows)) {
            $tableRows[] = ['No matching records', '', '', '', '', '', '', ''];
        }

        $sections[] = [
            'title' => 'Filtered Student Admissions List',
            'headers' => ['App No', 'Full Name', 'Email', 'Phone', 'State / LGA', 'College / Dept', 'Degree', 'Status'],
            'rows' => $tableRows,
        ];

    } elseif ($type === 'staff records') {
        $where = [
            "NOT EXISTS (
                SELECT 1 FROM applications ax WHERE ax.user_id = u.user_id
            )"
        ];
        $params = [];
        if (!empty($filters['staff_department_id'])) {
            $where[] = 'u.department_id = ?';
            $params[] = $filters['staff_department_id'];
        }
        if (!empty($filters['role_id'])) {
            $where[] = 'u.role_id = ?';
            $params[] = $filters['role_id'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT 
                u.full_name,
                u.email,
                COALESCE(d.dept_name, 'N/A') AS dept_name,
                COALESCE(r.role_name, 'N/A') AS role_name,
                u.account_status,
                COALESCE(u.last_login, '') AS last_login
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.dept_id
            LEFT JOIN roles r ON u.role_id = r.role_id
            $whereSql
            ORDER BY u.full_name ASC
        ";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $rows = [];
        }

        $tableRows = [];
        foreach ($rows as $r) {
            $tableRows[] = [
                (string)($r['full_name'] ?: 'N/A'),
                (string)$r['email'],
                (string)$r['dept_name'],
                (string)$r['role_name'],
                (string)$r['account_status'],
                (string)($r['last_login'] ? date('M d, Y H:i', strtotime($r['last_login'])) : 'Never')
            ];
        }
        if (empty($tableRows)) {
            $tableRows[] = ['No matching records', '', '', '', '', ''];
        }

        $sections[] = [
            'title' => 'Filtered Staff Directory List',
            'headers' => ['Full Name', 'Email', 'Department', 'Role', 'Status', 'Last Login'],
            'rows' => $tableRows,
        ];

    } elseif ($type === 'faculty breakdown') {
        $rows = safe_rows($pdo, "
            SELECT
                COALESCE(f.faculty_name, 'Unassigned') AS faculty_name,
                COUNT(a.application_id) AS total_apps,
                SUM(CASE WHEN a.status = 'Admitted' THEN 1 ELSE 0 END) AS admitted_apps,
                SUM(CASE WHEN a.status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_apps
            FROM applications a
            LEFT JOIN programme_choices pc ON pc.application_id = a.application_id
            LEFT JOIN departments d ON d.dept_id = COALESCE(pc.department, a.department_id)
            LEFT JOIN faculties f ON f.faculty_id = COALESCE(pc.faculty, d.faculty_id)
            GROUP BY COALESCE(f.faculty_name, 'Unassigned')
            ORDER BY total_apps DESC, faculty_name ASC
        ");
        $tableRows = [];
        foreach ($rows as $r) {
            $tableRows[] = [
                (string) ($r['faculty_name'] ?? 'Unassigned'),
                number_format((int) ($r['total_apps'] ?? 0)),
                number_format((int) ($r['admitted_apps'] ?? 0)),
                number_format((int) ($r['rejected_apps'] ?? 0)),
            ];
        }
        if (empty($tableRows)) {
            $tableRows[] = ['No faculty records', '0', '0', '0'];
        }
        $sections[] = [
            'title' => 'Faculty Application Breakdown',
            'headers' => ['Faculty', 'Applications', 'Admitted', 'Rejected'],
            'rows' => $tableRows,
        ];
    } elseif ($type === 'programme capacity') {
        $rows = safe_rows($pdo, "
            SELECT
                c.course_title,
                COALESCE(pc.capacity, 0) AS capacity,
                SUM(CASE WHEN a.status = 'Admitted' THEN 1 ELSE 0 END) AS admitted_count
            FROM courses c
            LEFT JOIN programme_capacities pc ON pc.course_id = c.course_id
            LEFT JOIN programme_choices pgc ON pgc.course = c.course_id
            LEFT JOIN applications a ON a.application_id = pgc.application_id
            GROUP BY c.course_id, c.course_title, pc.capacity
            ORDER BY c.course_title ASC
        ");
        $tableRows = [];
        foreach ($rows as $r) {
            $capacity = (int) ($r['capacity'] ?? 0);
            $admitted = (int) ($r['admitted_count'] ?? 0);
            $remaining = max(0, $capacity - $admitted);
            $tableRows[] = [
                (string) ($r['course_title'] ?? 'Unknown Programme'),
                number_format($capacity),
                number_format($admitted),
                number_format($remaining),
            ];
        }
        if (empty($tableRows)) {
            $tableRows[] = ['No programme records', '0', '0', '0'];
        }
        $sections[] = [
            'title' => 'Programme Capacity and Admission Load',
            'headers' => ['Programme', 'Capacity', 'Admitted', 'Available Slots'],
            'rows' => $tableRows,
        ];
    } else {
        $summary = [
            'total' => (int) safe_scalar($pdo, "SELECT COUNT(*) FROM applications"),
            'submitted' => (int) safe_scalar($pdo, "SELECT COUNT(*) FROM applications WHERE status = 'Submitted'"),
            'admitted' => (int) safe_scalar($pdo, "SELECT COUNT(*) FROM applications WHERE status = 'Admitted'"),
            'rejected' => (int) safe_scalar($pdo, "SELECT COUNT(*) FROM applications WHERE status = 'Rejected'"),
        ];
        $sections[] = [
            'title' => 'Admissions Summary',
            'headers' => ['Metric', 'Value'],
            'rows' => [
                ['Total Applications', number_format($summary['total'])],
                ['Submitted', number_format($summary['submitted'])],
                ['Admitted', number_format($summary['admitted'])],
                ['Rejected', number_format($summary['rejected'])],
            ],
        ];

        $facultyRows = safe_rows($pdo, "
            SELECT f.faculty_name, COUNT(*) AS total
            FROM programme_choices pc
            LEFT JOIN faculties f ON f.faculty_id = pc.faculty
            GROUP BY f.faculty_name
            ORDER BY total DESC
            LIMIT 10
        ");
        $rows = [];
        foreach ($facultyRows as $row) {
            $rows[] = [(string) ($row['faculty_name'] ?: 'Unassigned'), number_format((int) ($row['total'] ?? 0))];
        }
        if (empty($rows)) {
            $rows[] = ['No faculty data available', '0'];
        }
        $sections[] = [
            'title' => 'Faculty Distribution',
            'headers' => ['Faculty', 'Applications'],
            'rows' => $rows,
        ];
    }

    return [
        'report_type' => $reportType,
        'generated' => $generated,
        'sections' => $sections,
    ];
}

function buildReportLines(array $reportData): array {
    $lines = [];
    $lines[] = 'JOSTUM PG SCHOOL REPORT';
    $lines[] = str_repeat('=', 60);
    $lines[] = 'Report Type: ' . ($reportData['report_type'] ?? 'Report');
    $lines[] = 'Generated: ' . ($reportData['generated'] ?? date('M d, Y H:i'));
    $lines[] = '';
    foreach ($reportData['sections'] as $section) {
        $lines[] = strtoupper((string) ($section['title'] ?? 'SECTION'));
        $lines[] = str_repeat('-', 60);
        $headers = $section['headers'] ?? [];
        if (count($headers) >= 2) {
            $lines[] = padRight((string) $headers[0], 32) . ' | ' . padRight((string) $headers[1], 20);
        } elseif (count($headers) === 1) {
            $lines[] = (string) $headers[0];
        }
        $lines[] = str_repeat('-', 60);
        foreach (($section['rows'] ?? []) as $row) {
            $col1 = (string) ($row[0] ?? '');
            $col2 = (string) ($row[1] ?? '');
            $lines[] = padRight($col1, 32) . ' | ' . padRight($col2, 20);
        }
        $lines[] = '';
    }

    return $lines;
}

function padRight(string $value, int $length): string {
    if (strlen($value) >= $length) {
        return substr($value, 0, $length - 1) . ' ';
    }
    return str_pad($value, $length, ' ', STR_PAD_RIGHT);
}


function buildReportHtml(array $reportData): string {
    $generated = (string) ($reportData['generated'] ?? date('M d, Y H:i'));
    $reportType = (string) ($reportData['report_type'] ?? 'Report');
    $logoDataUri = '';
    $logoPath = __DIR__ . '/../../images/ipess_logo.png';
    if (is_file($logoPath)) {
        $raw = @file_get_contents($logoPath);
        if ($raw !== false) {
            $logoDataUri = 'data:image/png;base64,' . base64_encode($raw);
        }
    } else {
        $fallbackPath = __DIR__ . '/../../images/logo.jpeg';
        if (is_file($fallbackPath)) {
            $raw = @file_get_contents($fallbackPath);
            if ($raw !== false) {
                $logoDataUri = 'data:image/jpeg;base64,' . base64_encode($raw);
            }
        }
    }
    $sectionsHtml = '';
    foreach ($reportData['sections'] as $section) {
        $title = htmlspecialchars((string) ($section['title'] ?? 'Section'));
        $headers = $section['headers'] ?? [];
        $thead = '';
        if (!empty($headers)) {
            $ths = '';
            foreach ($headers as $header) {
                $ths .= '<th>' . htmlspecialchars((string) $header) . '</th>';
            }
            $thead = '<thead><tr>' . $ths . '</tr></thead>';
        }
        $bodyRows = '';
        foreach (($section['rows'] ?? []) as $row) {
            $cells = '';
            foreach ($row as $cell) {
                $cells .= '<td>' . htmlspecialchars((string) $cell) . '</td>';
            }
            $bodyRows .= '<tr>' . $cells . '</tr>';
        }
        if ($bodyRows === '') {
            $bodyRows = '<tr><td colspan="' . max(1, count($headers)) . '">No data available</td></tr>';
        }
        $sectionsHtml .= "<div class='section-title'>{$title}</div><table>{$thead}<tbody>{$bodyRows}</tbody></table>";
    }

    return <<<HTML
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 10px; }
        .header { margin-bottom: 16px; border-bottom: 1px solid #e5e7eb; padding-bottom: 10px; }
        .header-row { width: 100%; border-collapse: collapse; }
        .header-row td { border: none; padding: 0; vertical-align: middle; }
        .logo-cell { width: 64px; }
        .logo { width: 52px; height: 52px; object-fit: cover; border-radius: 6px; }
        .title-wrap { text-align: center; }
        .header h1 { font-size: 16px; margin: 0; color: #0f3b2e; }
        .meta { font-size: 10px; color: #6b7280; margin-top: 4px; }
        .section-title { font-size: 11px; font-weight: 600; margin: 18px 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: auto; }
        th, td { border: 1px solid #e5e7eb; padding: 5px 6px; text-align: left; font-size: 9px; }
        th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; letter-spacing: 0.04em; }
        .summary-grid { width: 100%; }
    </style>
</head>
<body>
    <div class='header'>
        <table class='header-row'>
            <tr>
                <td class='logo-cell'>
                    <img class='logo' src='{$logoDataUri}' alt='School Logo'>
                </td>
                <td class='title-wrap'>
                    <h1>Joseph Sarwuan Tarka University, Makurdi</h1>
                    <div class='meta'>IPESS Postgraduate School Report</div>
                    <div class='meta'>Report: {$reportType} | Generated: {$generated}</div>
                </td>
                <td class='logo-cell'></td>
            </tr>
        </table>
    </div>
    {$sectionsHtml}
</body>
</html>
HTML;
}

function buildSimplePdf(array $lines): string {
    $content = "BT\n/F1 11 Tf\n50 760 Td\n";
    foreach ($lines as $line) {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
        $content .= "({$escaped}) Tj\nT*\n";
    }
    $content .= "ET";

    $objects = [];
    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
    $objects[] = "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    $xref = "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    $offsets = [];
    $pdfBody = "%PDF-1.4\n";
    $offset = strlen($pdfBody);
    foreach ($objects as $obj) {
        $offsets[] = $offset;
        $pdfBody .= $obj;
        $offset = strlen($pdfBody);
    }
    foreach ($offsets as $ofs) {
        $xref .= sprintf("%010d 00000 n \n", $ofs);
    }
    $trailer = "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$offset}\n%%EOF";

    return $pdfBody . $xref . $trailer;
}

function generateStudentDossier(PDO $pdo, array $applicant, bool $includeSlip, bool $includeAdmission, bool $includeAcceptance, bool $includeDocs, bool $dompdfAvailable): ?string
{
    $appId = (int)$applicant['application_id'];
    $appNo = $applicant['application_number'] ?: 'N_A';
    
    $items = [];

    // 1. Application Slip
    if ($includeSlip && $dompdfAvailable) {
        $_GET['app_no'] = encrypt_app_number($appNo);
        ob_start();
        include __DIR__ . '/../../../helpers/print_slip.php';
        $slipHtml = ob_get_clean();
        $slipHtml = str_replace('window.print();', '', $slipHtml);

        try {
            $dompdf = new Dompdf\Dompdf();
            $dompdf->loadHtml($slipHtml);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $items[] = [
                'type' => 'pdf_data',
                'data' => $dompdf->output()
            ];
        } catch (Throwable $e) {
            // Ignore error
        }
    }

    $appStatus = $applicant['status'] ?? $applicant['current_status'] ?? '';
    $isAdmitted = (strtolower($appStatus) === 'admitted' || $appStatus === 'ADMISSION_APPROVED');

    // 2. Admission Letter
    if ($includeAdmission && $isAdmitted && $dompdfAvailable) {
        $_GET['app_no'] = $appNo;
        ob_start();
        include __DIR__ . '/../../../helpers/admission-letter.php';
        $admHtml = ob_get_clean();
        $admHtml = str_replace('window.print();', '', $admHtml);

        try {
            $dompdf = new Dompdf\Dompdf();
            $dompdf->loadHtml($admHtml);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $items[] = [
                'type' => 'pdf_data',
                'data' => $dompdf->output()
            ];
        } catch (Throwable $e) {
            // Ignore
        }
    }

    // 3. Acceptance Letter
    if ($includeAcceptance && $isAdmitted && $dompdfAvailable) {
        $_GET['app_no'] = $appNo;
        ob_start();
        include __DIR__ . '/../../../helpers/acceptance-letter.php';
        $accHtml = ob_get_clean();
        $accHtml = str_replace('window.print();', '', $accHtml);

        try {
            $dompdf = new Dompdf\Dompdf();
            $dompdf->loadHtml($accHtml);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $items[] = [
                'type' => 'pdf_data',
                'data' => $dompdf->output()
            ];
        } catch (Throwable $e) {
            // Ignore
        }
    }

    // 4. Uploaded Documents
    if ($includeDocs) {
        $docStmt = $pdo->prepare("SELECT document_type, file_path FROM documents WHERE application_id = ?");
        $docStmt->execute([$appId]);
        $docs = $docStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($docs as $doc) {
            $filePath = __DIR__ . '/../../../' . ltrim($doc['file_path'], '/');
            if (file_exists($filePath) && is_file($filePath)) {
                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                if ($ext === 'pdf') {
                    $items[] = [
                        'type' => 'pdf_file',
                        'path' => $filePath
                    ];
                } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
                    $items[] = [
                        'type' => 'image_file',
                        'path' => $filePath
                    ];
                }
            }
        }
    }

    if (empty($items)) {
        return null;
    }

    $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }

    if (!class_exists('setasign\\Fpdi\\Fpdi')) {
        return null;
    }

    $pdf = new \setasign\Fpdi\Fpdi();

    foreach ($items as $item) {
        if ($item['type'] === 'pdf_data') {
            $tmpFile = tempnam(sys_get_temp_dir(), 'ipess_m_');
            file_put_contents($tmpFile, $item['data']);
            try {
                $pageCount = $pdf->setSourceFile($tmpFile);
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $tplIdx = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($tplIdx);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($tplIdx);
                }
            } catch (Throwable $e) {
                // Ignore
            }
            @unlink($tmpFile);
        } elseif ($item['type'] === 'pdf_file') {
            try {
                $pageCount = $pdf->setSourceFile($item['path']);
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $tplIdx = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($tplIdx);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($tplIdx);
                }
            } catch (Throwable $e) {
                // Ignore
            }
        } elseif ($item['type'] === 'image_file') {
            try {
                $pdf->AddPage('P', 'A4');
                $pdf->Image($item['path'], 10, 10, 190);
            } catch (Throwable $e) {
                // Ignore
            }
        }
    }

    return $pdf->Output('S');
}
