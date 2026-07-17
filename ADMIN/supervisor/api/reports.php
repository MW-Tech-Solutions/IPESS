<?php
session_start();
require_once __DIR__ . '/../../admin/includes/db.php';

header('Content-Type: application/json');

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$reportsDir = __DIR__ . '/../reports';
if (!is_dir($reportsDir)) {
    mkdir($reportsDir, 0775, true);
}

if ($action === 'list') {
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT report_id, report_name, report_type, format, file_path, created_at
        FROM admin_reports
        WHERE generated_by = ? AND report_type LIKE 'Supervisor:%'
        ORDER BY created_at DESC
        LIMIT 30
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $data = array_map(function ($row) {
        return [
            'report_id' => $row['report_id'],
            'report_title' => $row['report_name'],
            'report_type' => $row['report_type'],
            'format' => $row['format'],
            'file_path' => $row['file_path'],
            'created_at' => $row['created_at'],
            'status' => 'Ready'
        ];
    }, $rows);
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

if ($action === 'generate') {
    $title = $_POST['report_title'] ?? 'Supervisor Summary Report';
    $subType = trim((string) ($_POST['report_type'] ?? 'Weekly'));
    if ($subType === '') {
        $subType = 'Weekly';
    }
    $type = 'Supervisor:' . $subType;
    $format = strtoupper($_POST['format'] ?? 'PDF');
    $format = in_array($format, ['PDF', 'CSV'], true) ? $format : 'PDF';

    $base = 'supervisor_report_' . date('Ymd_His') . '_' . str_replace('.', '', uniqid('', true));
    $relative = 'reports/' . $base . ($format === 'PDF' ? '.pdf' : '.csv');
    $full = __DIR__ . '/../' . $relative;
    $metricRows = buildSupervisorMetricRows($pdo, $subType);

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
                    error_log("Dompdf failed in supervisor reports: " . $e->getMessage() . ". Falling back to HTML format.");
                }
            }
        }
        if (!$written) {
            file_put_contents($full, $html);
        }
    }

    $stmt = $pdo->prepare("INSERT INTO admin_reports (report_name, report_type, format, file_path, generated_by, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$title, $type, $format, $relative, $_SESSION['user_id'] ?? null]);

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unsupported action.']);

function buildSupervisorMetricRows(PDO $pdo, string $subType): array
{
    $key = strtolower(trim($subType));
    if ($key === 'milestone') {
        $total = (int) safeScalarSupervisor($pdo, "SELECT COUNT(*) FROM supervisor_milestones");
        $upcoming = (int) safeScalarSupervisor($pdo, "SELECT COUNT(*) FROM supervisor_milestones WHERE status = 'Upcoming'");
        $completed = (int) safeScalarSupervisor($pdo, "SELECT COUNT(*) FROM supervisor_milestones WHERE status IN ('Completed','Done')");
        return [
            ['Total Milestones', number_format($total)],
            ['Upcoming', number_format($upcoming)],
            ['Completed', number_format($completed)],
        ];
    }
    if ($key === 'chapter' || $key === 'review') {
        $submitted = (int) safeScalarSupervisor($pdo, "SELECT COUNT(*) FROM chapter_submissions");
        $approved = (int) safeScalarSupervisor($pdo, "SELECT COUNT(*) FROM chapter_submissions WHERE status = 'Approved'");
        $changes = (int) safeScalarSupervisor($pdo, "SELECT COUNT(*) FROM chapter_submissions WHERE status = 'Changes Requested'");
        return [
            ['Chapter Submissions', number_format($submitted)],
            ['Approved Chapters', number_format($approved)],
            ['Changes Requested', number_format($changes)],
        ];
    }

    $activeStudents = (int) safeScalarSupervisor($pdo, "SELECT COUNT(*) FROM supervisor_students");
    $pendingReview = (int) safeScalarSupervisor($pdo, "SELECT COUNT(*) FROM supervisor_students WHERE status IN ('Pending Review','Under Review')");
    $messages = (int) safeScalarSupervisor($pdo, "SELECT COUNT(*) FROM supervisor_messages");
    return [
        ['Assigned Students', number_format($activeStudents)],
        ['Pending Reviews', number_format($pendingReview)],
        ['Messages Exchanged', number_format($messages)],
    ];
}

function safeScalarSupervisor(PDO $pdo, string $sql)
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
