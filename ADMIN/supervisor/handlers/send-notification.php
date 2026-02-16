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
    error_log('supervisor-send-notification exception: ' . $e->getMessage());
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
        error_log('supervisor-send-notification fatal: ' . $error['message']);
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
$title = trim($_POST['title'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($studentUserId <= 0 || $title === '' || $message === '') {
    echo json_encode(['success' => false, 'message' => 'Missing notification details.']);
    exit;
}

if (!table_exists($pdo, 'student_notifications')) {
    echo json_encode(['success' => false, 'message' => 'Student notifications table missing.']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO student_notifications (student_user_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
$stmt->execute([$studentUserId, $title, $message]);

echo json_encode(['success' => true]);
