<?php
require_once __DIR__ . '/../admin/includes/db.php';

if (!$pdo) {
    http_response_code(500);
    echo 'Database unavailable.';
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'Invalid file.';
    exit;
}

$stmt = $pdo->prepare("SELECT file_path, file_name FROM chapter_submissions WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

$relative = ltrim($row['file_path'] ?? '', '/');
if ($relative === '') {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

$fullPath = __DIR__ . '/../..//APPLICANT/ACADEMICS/student-portal/' . $relative;
if (!file_exists($fullPath)) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mimeMap = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

$filename = $row['file_name'] ?: basename($fullPath);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) . '"');
readfile($fullPath);
exit;
