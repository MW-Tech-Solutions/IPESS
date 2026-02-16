<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../../../ADMIN/includes/db.php';

if (!isset($pdo) || !$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Database unavailable.']);
    exit;
}

$milestone_id = trim($_POST['milestone_id'] ?? '');
if ($milestone_id === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Milestone ID is required.']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Student session missing.']);
    exit;
}

if (!table_exists($pdo, 'supervisor_milestones')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Milestones table missing.']);
    exit;
}

$stmt = $pdo->prepare("UPDATE supervisor_milestones SET acknowledged_at = NOW() WHERE milestone_id = ? AND student_user_id = ?");
$stmt->execute([(int) $milestone_id, $userId]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Milestone not found.']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Milestone acknowledged.']);

function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}
