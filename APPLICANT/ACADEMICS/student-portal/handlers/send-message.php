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
    error_log('student-send-message exception: ' . $e->getMessage());
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
        error_log('student-send-message fatal: ' . $error['message']);
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Server error: ' . $error['message']]);
    }
});

require_once __DIR__ . '/../../../../ADMIN/includes/db.php';

$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($subject === '' || $message === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Subject and message are required.']);
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;
$applicationId = $_SESSION['application_id'] ?? 0;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Student session missing.']);
    exit;
}

if (!table_exists($pdo, 'supervisor_messages')) {
    echo json_encode(['ok' => false, 'message' => 'Supervisor messages table missing.']);
    exit;
}

$supervisorUserId = null;
$supervisorName = '';
if (table_exists($pdo, 'supervisor_students')) {
    $stmt = $pdo->prepare("SELECT supervisor_user_id, supervisor_name FROM supervisor_students WHERE student_user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $supervisorUserId = $row['supervisor_user_id'] ?? null;
    $supervisorName = $row['supervisor_name'] ?? '';
}

if (!$supervisorUserId && $supervisorName !== '') {
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE full_name = ? LIMIT 1");
    $stmt->execute([$supervisorName]);
    $supervisorUserId = $stmt->fetchColumn() ?: null;
}

if (!$supervisorUserId) {
    echo json_encode(['ok' => false, 'message' => 'Supervisor not assigned yet.']);
    exit;
}

$studentIdentifier = '';
if (table_exists($pdo, 'supervisor_students')) {
    $stmt = $pdo->prepare("SELECT student_id FROM supervisor_students WHERE student_user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $studentIdentifier = (string) ($stmt->fetchColumn() ?: '');
}
if ($studentIdentifier === '') {
    $studentIdentifier = (string) $userId;
}

$stmt = $pdo->prepare("
    INSERT INTO supervisor_messages (student_id, supervisor_user_id, student_user_id, sender_role, subject, message, created_at)
    VALUES (?, ?, ?, 'STUDENT', ?, ?, NOW())
");
$stmt->execute([$studentIdentifier, $supervisorUserId, $userId, $subject, $message]);

echo json_encode(['ok' => true, 'message' => 'Message sent.']);

function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}
