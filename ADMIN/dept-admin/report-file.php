<?php
require_once __DIR__ . '/../admin/includes/db.php';

if (!$pdo) {
    http_response_code(500);
    echo 'Database unavailable.';
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$mode = strtolower($_GET['mode'] ?? 'view');
$mode = $mode === 'download' ? 'download' : 'view';

if ($id === 0) {
    http_response_code(400);
    echo 'Invalid report id.';
    exit;
}

$stmt = $pdo->prepare("SELECT report_title, format, file_path FROM role_reports WHERE report_id = ? AND owner_role = ? LIMIT 1");
$stmt->execute([$id, 'DEPARTMENT_ADMIN']);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    http_response_code(404);
    echo 'Report not found.';
    exit;
}

$filePath = __DIR__ . '/' . ltrim($report['file_path'], '/');
if (!file_exists($filePath)) {
    http_response_code(404);
    echo 'Report file missing.';
    exit;
}

$format = strtoupper($report['format'] ?? 'PDF');
$extension = $format === 'CSV' ? 'csv' : 'pdf';
$filename = preg_replace('/[^A-Za-z0-9_-]+/', '_', $report['report_title']) . '.' . $extension;

if ($extension === 'pdf' && $mode === 'view' && !isset($_GET['raw'])) {
    $self = htmlspecialchars((string) basename($_SERVER['PHP_SELF']), ENT_QUOTES, 'UTF-8');
    $rawSrc = $self . '?id=' . (int) $id . '&mode=view&raw=1';
    $title = htmlspecialchars((string) $report['report_title'], ENT_QUOTES, 'UTF-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="/JOSTUM/ADMIN/images/logo.jpeg">
    <title><?php echo $title; ?></title>
    <style>
        html, body { margin: 0; padding: 0; width: 100%; height: 100%; background: #fff; }
        iframe { width: 100%; height: 100%; border: 0; display: block; }
    </style>
</head>
<body>
    <iframe src="<?php echo htmlspecialchars($rawSrc, ENT_QUOTES, 'UTF-8'); ?>" title="Report Preview"></iframe>
</body>
</html>
<?php
    exit;
}

if ($extension === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
} else {
    header('Content-Type: application/pdf');
}

$disposition = $mode === 'download' ? 'attachment' : 'inline';
header('Content-Disposition: ' . $disposition . '; filename=' . $filename);
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
