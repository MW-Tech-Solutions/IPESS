<?php
session_start();
require_once __DIR__ . '/../../admin/includes/db.php';

header('Content-Type: application/json');

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS role_reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    owner_role VARCHAR(50) NOT NULL,
    owner_user_id INT DEFAULT NULL,
    report_title VARCHAR(200) NOT NULL,
    report_type VARCHAR(100) NOT NULL,
    format VARCHAR(20) NOT NULL,
    file_path VARCHAR(255) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'Ready',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$reportsDir = __DIR__ . '/../reports';
if (!is_dir($reportsDir)) {
    mkdir($reportsDir, 0775, true);
}

if ($action === 'list') {
    $rows = $pdo->prepare("SELECT * FROM role_reports WHERE owner_role = ? ORDER BY created_at DESC LIMIT 30");
    $rows->execute(['REVIEWER']);
    $data = $rows->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

if ($action === 'generate') {
    $title = $_POST['report_title'] ?? 'Reviewer Summary Report';
    $type = $_POST['report_type'] ?? 'Performance';
    $format = strtoupper($_POST['format'] ?? 'PDF');
    $format = in_array($format, ['PDF', 'CSV'], true) ? $format : 'PDF';

    $base = 'reviewer_report_' . date('Ymd_His') . '_' . str_replace('.', '', uniqid('', true));
    $relative = 'reports/' . $base . ($format === 'PDF' ? '.pdf' : '.csv');
    $full = __DIR__ . '/../' . $relative;
    $metricRows = buildReviewerMetricRows($pdo, $type);

    if ($format === 'CSV') {
        $handle = fopen($full, 'w');
        fputcsv($handle, ['Metric', 'Value']);
        foreach ($metricRows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    } else {
        $html = buildBrandedReportHtml($title, $type, $metricRows);
        $written = false;
        if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
            require_once __DIR__ . '/../../vendor/autoload.php';
            if (class_exists('Dompdf\\Dompdf')) {
                try {
                    $dompdf = new Dompdf\Dompdf();
                    $dompdf->loadHtml($html);
                    $dompdf->setPaper('A4');
                    $dompdf->render();
                    file_put_contents($full, $dompdf->output());
                    $written = true;
                } catch (Throwable $e) {
                    error_log("Dompdf failed in reviewer reports: " . $e->getMessage() . ". Falling back to HTML format.");
                }
            }
        }
        if (!$written) {
            file_put_contents($full, $html);
        }
    }

    $stmt = $pdo->prepare("INSERT INTO role_reports (owner_role, owner_user_id, report_title, report_type, format, file_path)
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['REVIEWER', $_SESSION['user_id'] ?? null, $title, $type, $format, $relative]);

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unsupported action.']);

function buildReviewerMetricRows(PDO $pdo, string $type): array
{
    $typeKey = strtolower(trim($type));
    if ($typeKey === 'feedback') {
        $total = (int) safeScalarReviewer($pdo, "SELECT COUNT(*) FROM reviewer_feedback");
        $awaiting = (int) safeScalarReviewer($pdo, "SELECT COUNT(*) FROM reviewer_feedback WHERE status = 'Awaiting Response'");
        $resolved = max(0, $total - $awaiting);
        return [
            ['Total Feedback Entries', number_format($total)],
            ['Awaiting Response', number_format($awaiting)],
            ['Resolved', number_format($resolved)],
        ];
    }
    if ($typeKey === 'history') {
        $total = (int) safeScalarReviewer($pdo, "SELECT COUNT(*) FROM reviewer_history");
        $approved = (int) safeScalarReviewer($pdo, "SELECT COUNT(*) FROM reviewer_history WHERE decision IN ('Approved','Accept','Pass')");
        $rejected = (int) safeScalarReviewer($pdo, "SELECT COUNT(*) FROM reviewer_history WHERE decision IN ('Rejected','Fail')");
        return [
            ['Total Reviewed History', number_format($total)],
            ['Approved Decisions', number_format($approved)],
            ['Rejected Decisions', number_format($rejected)],
        ];
    }

    $assigned = (int) safeScalarReviewer($pdo, "SELECT COUNT(*) FROM reviewer_assignments");
    $completed = (int) safeScalarReviewer($pdo, "SELECT COUNT(*) FROM reviewer_assignments WHERE status IN ('Completed','Reviewed')");
    $pending = max(0, $assigned - $completed);
    return [
        ['Assigned Applications', number_format($assigned)],
        ['Completed Reviews', number_format($completed)],
        ['Pending Reviews', number_format($pending)],
    ];
}

function safeScalarReviewer(PDO $pdo, string $sql)
{
    try {
        return $pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function buildBrandedReportHtml(string $title, string $type, array $metricRows): string
{
    $generated = date('M d, Y H:i');
    $logoDataUri = '';
    $logoPath = __DIR__ . '/../../images/logo.jpeg';
    if (is_file($logoPath)) {
        $raw = @file_get_contents($logoPath);
        if ($raw !== false) {
            $logoDataUri = 'data:image/jpeg;base64,' . base64_encode($raw);
        }
    }

    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeType = htmlspecialchars($type, ENT_QUOTES, 'UTF-8');

    $metricHtml = '';
    foreach ($metricRows as $row) {
        $metricHtml .= '<tr><td>' . htmlspecialchars((string) $row[0], ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars((string) $row[1], ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 12px; }
        .header { margin-bottom: 16px; border-bottom: 1px solid #e5e7eb; padding-bottom: 10px; }
        .header-row { width: 100%; border-collapse: collapse; }
        .header-row td { border: none; padding: 0; vertical-align: middle; }
        .logo-cell { width: 64px; }
        .logo { width: 52px; height: 52px; object-fit: cover; border-radius: 6px; }
        .title-wrap { text-align: center; }
        .title-wrap h1 { margin: 0; color: #0f3b2e; font-size: 18px; }
        .meta { font-size: 11px; color: #6b7280; margin-top: 4px; }
        .section-title { margin: 14px 0 8px; font-size: 13px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 8px 10px; text-align: left; }
        th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-row">
            <tr>
                <td class="logo-cell"><img class="logo" src="{$logoDataUri}" alt="School Logo"></td>
                <td class="title-wrap">
                    <h1>Joseph Sarwuan Tarka University, Makurdi</h1>
                    <div class="meta">Postgraduate School Report</div>
                    <div class="meta">Report: {$safeType} | Generated: {$generated}</div>
                </td>
                <td class="logo-cell"></td>
            </tr>
        </table>
    </div>
    <div class="section-title">{$safeTitle}</div>
    <table>
        <thead><tr><th>Metric</th><th>Value</th></tr></thead>
        <tbody>
            {$metricHtml}
        </tbody>
    </table>
</body>
</html>
HTML;
}
