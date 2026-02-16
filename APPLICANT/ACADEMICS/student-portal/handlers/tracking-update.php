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
    error_log('tracking-update exception: ' . $e->getMessage());
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
        error_log('tracking-update fatal: ' . $error['message']);
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Server error: ' . $error['message']]);
    }
});

require_once __DIR__ . '/../../../../ADMIN/includes/db.php';

$title = trim($_POST['title'] ?? '');
$note = trim($_POST['note'] ?? '');
$progress = isset($_POST['progress']) ? (int) $_POST['progress'] : 0;

if ($title === '' || $note === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Title and note are required.']);
    exit;
}

if ($progress < 0 || $progress > 100) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Progress must be between 0 and 100.']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Student session missing.']);
    exit;
}

if (!table_exists($pdo, 'student_tracking_updates')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Tracking updates table missing.']);
    exit;
}

$status = $progress >= 100 ? 'Completed' : 'In Progress';
$stmt = $pdo->prepare("
    INSERT INTO student_tracking_updates (student_user_id, title, note, status, progress, updated_at)
    VALUES (?, ?, ?, ?, ?, NOW())
");
$stmt->execute([$userId, $title, $note, $status, $progress]);

echo json_encode(['ok' => true, 'message' => 'Tracking update saved.']);

function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}
