<?php
session_start();
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
$format = in_array($format, ['PDF', 'EXCEL'], true) ? $format : 'PDF';

$reportData = buildSuperAdminReportData($pdo, $reportType);
$lines = buildReportLines($reportData);

$baseName = 'report_' . date('Ymd_His') . '_' . str_replace('.', '', uniqid('', true));
$relativePath = 'reports/' . $baseName . ($format === 'PDF' ? '.pdf' : '.csv');
$fullPath = __DIR__ . '/../' . $relativePath;

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
        $html = buildReportHtml($reportData);
        $dompdf = new Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        if (file_put_contents($fullPath, $dompdf->output()) === false) {
            json_error('Unable to write report file.');
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

function buildSuperAdminReportData(PDO $pdo, string $reportType): array
{
    $type = strtolower(trim($reportType));
    $generated = date('M d, Y H:i');
    $sections = [];

    if ($type === 'faculty breakdown') {
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
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 12px; }
        .header { margin-bottom: 16px; border-bottom: 1px solid #e5e7eb; padding-bottom: 10px; }
        .header-row { width: 100%; border-collapse: collapse; }
        .header-row td { border: none; padding: 0; vertical-align: middle; }
        .logo-cell { width: 64px; }
        .logo { width: 52px; height: 52px; object-fit: cover; border-radius: 6px; }
        .title-wrap { text-align: center; }
        .header h1 { font-size: 18px; margin: 0; color: #0f3b2e; }
        .meta { font-size: 11px; color: #6b7280; margin-top: 4px; }
        .section-title { font-size: 13px; font-weight: 600; margin: 18px 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px 10px; text-align: left; }
        th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; }
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
