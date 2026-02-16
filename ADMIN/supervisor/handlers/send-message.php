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
    error_log('supervisor-send-message exception: ' . $e->getMessage());
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
        error_log('supervisor-send-message fatal: ' . $error['message']);
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

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}

$studentUserId = (int) ($_POST['student_user_id'] ?? 0);
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
$supervisorUserId = (int) ($_SESSION['user_id'] ?? 0);

if ($studentUserId <= 0 || $subject === '' || $message === '') {
    echo json_encode(['success' => false, 'message' => 'Missing message details.']);
    exit;
}

if (!table_exists($pdo, 'supervisor_messages')) {
    echo json_encode(['success' => false, 'message' => 'Supervisor messages table missing.']);
    exit;
}

$studentIdentifier = '';
if (table_exists($pdo, 'supervisor_students')) {
    $stmt = $pdo->prepare("SELECT student_id FROM supervisor_students WHERE student_user_id = ? LIMIT 1");
    $stmt->execute([$studentUserId]);
    $studentIdentifier = (string) ($stmt->fetchColumn() ?: '');
}
if ($studentIdentifier === '') {
    $studentIdentifier = (string) $studentUserId;
}

$stmt = $pdo->prepare("
    INSERT INTO supervisor_messages (student_id, supervisor_user_id, student_user_id, sender_role, subject, message, created_at)
    VALUES (?, ?, ?, 'SUPERVISOR', ?, ?, NOW())
");
$stmt->execute([$studentIdentifier, $supervisorUserId, $studentUserId, $subject, $message]);

echo json_encode(['success' => true]);
