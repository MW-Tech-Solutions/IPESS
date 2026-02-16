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
    error_log('review-chapter exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (ob_get_length()) {
            ob_clean();
        }
        error_log('review-chapter fatal: ' . $error['message']);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $error['message']]);
    }
});

require_once __DIR__ . '/../../admin/includes/db.php';

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

function resolve_supervisor_name(PDO $pdo, int $userId): string {
    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $name = trim((string) ($row['full_name'] ?? ''));
    $email = trim((string) ($row['email'] ?? ''));
    return $name !== '' ? $name : $email;
}

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}

$submissionId = (int) ($_POST['submission_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$note = trim($_POST['note'] ?? '');
$allowed = ['Under Review', 'Changes Requested', 'Approved'];

if ($submissionId <= 0 || !in_array($status, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid review request.']);
    exit;
}

if (!table_exists($pdo, 'chapter_submissions')) {
    echo json_encode(['success' => false, 'message' => 'Chapter submissions table missing.']);
    exit;
}

$stmt = $pdo->prepare("SELECT student_user_id, chapter_no FROM chapter_submissions WHERE id = ? LIMIT 1");
$stmt->execute([$submissionId]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$submission) {
    echo json_encode(['success' => false, 'message' => 'Submission not found.']);
    exit;
}

$studentUserId = (int) $submission['student_user_id'];
$chapterNo = (int) $submission['chapter_no'];
$supervisorUserId = (int) ($_SESSION['user_id'] ?? 0);

if (!table_exists($pdo, 'supervisor_students')) {
    echo json_encode(['success' => false, 'message' => 'Supervisor assignments missing.']);
    exit;
}

$useUserId = column_exists($pdo, 'supervisor_students', 'supervisor_user_id');
if ($useUserId) {
    $stmt = $pdo->prepare("SELECT 1 FROM supervisor_students WHERE student_user_id = ? AND supervisor_user_id = ? LIMIT 1");
    $stmt->execute([$studentUserId, $supervisorUserId]);
} else {
    $supName = resolve_supervisor_name($pdo, $supervisorUserId);
    $stmt = $pdo->prepare("SELECT 1 FROM supervisor_students WHERE student_user_id = ? AND supervisor_name = ? LIMIT 1");
    $stmt->execute([$studentUserId, $supName]);
}
if (!$stmt->fetchColumn()) {
    echo json_encode(['success' => false, 'message' => 'Not authorized for this student.']);
    exit;
}

$reviewPath = null;
if (!empty($_FILES['review_file']) && $_FILES['review_file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['review_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExt = ['pdf', 'doc', 'docx'];
    if (!in_array($ext, $allowedExt, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid review file type.']);
        exit;
    }

    $uploadBase = __DIR__ . '/../../../APPLICANT/ACADEMICS/student-portal/uploads/supervision/reviews';
    if (!is_dir($uploadBase)) {
        mkdir($uploadBase, 0755, true);
    }
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
    $targetName = 'review_' . $studentUserId . '_' . date('Ymd_His') . '_' . $safeName . '.' . $ext;
    $targetPath = $uploadBase . '/' . $targetName;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        echo json_encode(['success' => false, 'message' => 'Unable to save review file.']);
        exit;
    }
    $reviewPath = 'uploads/supervision/reviews/' . $targetName;
}

$stmt = $pdo->prepare("UPDATE chapter_submissions SET status = ?, supervisor_note = ?, supervisor_user_id = ?, reviewed_at = NOW(), review_file_path = COALESCE(?, review_file_path) WHERE id = ?");
$stmt->execute([$status, $note, $supervisorUserId, $reviewPath, $submissionId]);

// Update supervisor_students progress and status
$approvedCount = 0;
for ($i = 1; $i <= 5; $i++) {
    $stmt = $pdo->prepare("SELECT status FROM chapter_submissions WHERE student_user_id = ? AND chapter_no = ? ORDER BY version_no DESC, submitted_at DESC LIMIT 1");
    $stmt->execute([$studentUserId, $i]);
    if ($stmt->fetchColumn() === 'Approved') {
        $approvedCount++;
    }
}
$progressPct = (int) round(($approvedCount / 5) * 100);
$nextChapter = $approvedCount >= 5 ? 'Completed' : 'Chapter ' . ($approvedCount + 1);
$overallStatus = $status === 'Changes Requested' ? 'Awaiting Revision' : ($approvedCount >= 5 ? 'Approved' : 'Pending Review');

$stmt = $pdo->prepare("UPDATE supervisor_students SET progress_pct = ?, current_chapter = ?, status = ?, updated_at = NOW() WHERE student_user_id = ?");
$stmt->execute([$progressPct, $nextChapter, $overallStatus, $studentUserId]);

if (table_exists($pdo, 'student_notifications')) {
    $title = "Chapter {$chapterNo} Review";
    $message = $status === 'Approved' ? 'Chapter approved. You can proceed to the next chapter.' : ($note ?: 'Chapter review updated.');
    $stmt = $pdo->prepare("INSERT INTO student_notifications (student_user_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
    $stmt->execute([$studentUserId, $title, $message]);
}

echo json_encode(['success' => true]);
