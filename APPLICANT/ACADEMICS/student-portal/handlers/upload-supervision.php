<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');
session_start();
ob_start();

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($e) {
    if (ob_get_length()) {
        ob_clean();
    }
    error_log('upload-supervision exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (ob_get_length()) {
            ob_clean();
        }
        error_log('upload-supervision fatal: ' . $error['message']);
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Server error: ' . $error['message']]);
    }
});

require_once __DIR__ . '/../../../../ADMIN/includes/db.php';
require_once __DIR__ . '/../../../../includes/status_engine.php';

try {
$allowed_chapters = ['chapter-1', 'chapter-2', 'chapter-3', 'chapter-4', 'chapter-5', 'final'];
$chapter = $_POST['chapter'] ?? '';

if (!in_array($chapter, $allowed_chapters, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid chapter.']);
    exit;
}

if (!isset($_FILES['work_file']) || $_FILES['work_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Upload failed.']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$applicationId = (int) ($_SESSION['application_id'] ?? 0);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Student session missing.']);
    exit;
}

$chapterMap = [
    'chapter-1' => 1,
    'chapter-2' => 2,
    'chapter-3' => 3,
    'chapter-4' => 4,
    'chapter-5' => 5,
    'final' => 6
];
$chapterNo = $chapterMap[$chapter] ?? 0;
if ($chapterNo === 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid chapter.']);
    exit;
}

if (!$applicationId) {
    $stmt = $pdo->prepare("SELECT application_id FROM applications WHERE user_id = ? ORDER BY application_id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $applicationId = (int) $stmt->fetchColumn();
    if ($applicationId) {
        $_SESSION['application_id'] = $applicationId;
    }
}

if (!$applicationId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Application not found.']);
    exit;
}

$appNumber = '';
$stmt = $pdo->prepare("SELECT application_number FROM applications WHERE application_id = ? LIMIT 1");
$stmt->execute([$applicationId]);
$appNumber = (string) ($stmt->fetchColumn() ?: '');

if (!table_exists($pdo, 'chapter_submissions')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Chapter submissions table missing.']);
    exit;
}

if ($chapterNo >= 2 && $chapterNo <= 5) {
    $prev = $chapterNo - 1;
    $stmt = $pdo->prepare("SELECT status FROM chapter_submissions WHERE student_user_id = ? AND chapter_no = ? ORDER BY version_no DESC, submitted_at DESC LIMIT 1");
    $stmt->execute([$userId, $prev]);
    $prevStatus = $stmt->fetchColumn();
    if ($prevStatus !== 'Approved') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => "Chapter {$prev} must be approved before uploading Chapter {$chapterNo}."]);
        exit;
    }
}

$file = $_FILES['work_file'];
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed_extensions = ['pdf'];

if (!in_array($extension, $allowed_extensions, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Only PDF or DOCX files are allowed.']);
    exit;
}

if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed_mimes = [
        'application/pdf',
    ];

    if (!in_array($mime, $allowed_mimes, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Invalid file type.']);
        exit;
    }
}

$upload_base = dirname(__DIR__) . '/uploads/supervision';
$chapter_dir = $upload_base . '/' . $chapter;
if (!is_dir($chapter_dir)) {
    mkdir($chapter_dir, 0755, true);
}

$timestamp = date('Ymd_His');
$safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
$target_name = $chapter . '_' . $timestamp . '_' . $safe_name . '.' . $extension;
$target_path = $chapter_dir . '/' . $target_name;
$db_path = 'uploads/supervision/' . $chapter . '/' . $target_name;

if (!move_uploaded_file($file['tmp_name'], $target_path)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Could not save the file.']);
    exit;
}

$stmt = $pdo->prepare("SELECT COALESCE(MAX(version_no), 0) FROM chapter_submissions WHERE student_user_id = ? AND chapter_no = ?");
$stmt->execute([$userId, $chapterNo]);
$version = (int) $stmt->fetchColumn() + 1;

$stmt = $pdo->prepare("
    INSERT INTO chapter_submissions
        (student_user_id, application_id, application_number, chapter_no, chapter_label, file_path, file_name, file_ext, status, version_no, submitted_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Submitted', ?, NOW())
");
$label = strtoupper(str_replace('-', ' ', $chapter));
$stmt->execute([$userId, $applicationId, $appNumber, $chapterNo, $label, $db_path, $target_name, $extension, $version]);

if (table_exists($pdo, 'supervisor_students')) {
    $useStudentUserId = column_exists($pdo, 'supervisor_students', 'student_user_id');
    $studentKeyCol = $useStudentUserId ? 'student_user_id' : 'student_id';
    $studentKeyVal = $useStudentUserId ? $userId : ($appNumber !== '' ? $appNumber : (string) $userId);

    $approvedCount = 0;
    for ($i = 1; $i <= 5; $i++) {
        $stmt = $pdo->prepare("SELECT status FROM chapter_submissions WHERE student_user_id = ? AND chapter_no = ? ORDER BY version_no DESC, submitted_at DESC LIMIT 1");
        $stmt->execute([$userId, $i]);
        if ($stmt->fetchColumn() === 'Approved') {
            $approvedCount++;
        }
    }
    $progressPct = (int) round(($approvedCount / 5) * 100);
    $currentChapter = $chapterNo <= 5 ? "Chapter {$chapterNo}" : 'Final Submission';
    $stmt = $pdo->prepare("
        UPDATE supervisor_students
        SET current_chapter = ?, status = 'Pending Review', last_submission = NOW(), progress_pct = ?, updated_at = NOW()
        WHERE {$studentKeyCol} = ?
    ");
    $stmt->execute([$currentChapter, $progressPct, $studentKeyVal]);
}

echo json_encode(['ok' => true, 'message' => 'Upload saved.', 'chapter' => $chapter, 'file' => $db_path]);

} catch (Throwable $e) {
    error_log('upload-supervision error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

if (!function_exists('table_exists')) {
function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}
}
