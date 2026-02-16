<?php
session_start();
require_once __DIR__ . '/../../admin/includes/db.php';

header('Content-Type: application/json');

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS reviewer_feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    application_code VARCHAR(50) NOT NULL,
    student_name VARCHAR(150) NOT NULL,
    chapter VARCHAR(50) DEFAULT NULL,
    feedback TEXT NOT NULL,
    status VARCHAR(30) DEFAULT 'Awaiting Response',
    reviewer_name VARCHAR(150) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'list') {
    $rows = $pdo->query("SELECT * FROM reviewer_feedback ORDER BY updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

if ($action === 'create') {
    $payload = [
        'application_code' => $_POST['application_code'] ?? '',
        'student_name' => $_POST['student_name'] ?? '',
        'chapter' => $_POST['chapter'] ?? null,
        'feedback' => $_POST['feedback'] ?? '',
        'status' => $_POST['status'] ?? 'Awaiting Response',
        'reviewer_name' => $_POST['reviewer_name'] ?? null,
    ];
    if ($payload['application_code'] === '' || $payload['student_name'] === '' || $payload['feedback'] === '') {
        echo json_encode(['success' => false, 'message' => 'Missing feedback details.']);
        exit;
    }
    $stmt = $pdo->prepare("INSERT INTO reviewer_feedback (application_code, student_name, chapter, feedback, status, reviewer_name)
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $payload['application_code'],
        $payload['student_name'],
        $payload['chapter'],
        $payload['feedback'],
        $payload['status'],
        $payload['reviewer_name'],
    ]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'status') {
    $id = (int) ($_POST['feedback_id'] ?? 0);
    $status = $_POST['status'] ?? 'Awaiting Response';
    if ($id === 0) {
        echo json_encode(['success' => false, 'message' => 'Missing feedback id.']);
        exit;
    }
    $stmt = $pdo->prepare("UPDATE reviewer_feedback SET status = ? WHERE feedback_id = ?");
    $stmt->execute([$status, $id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'followup') {
    $id = (int) ($_POST['feedback_id'] ?? 0);
    if ($id === 0) {
        echo json_encode(['success' => false, 'message' => 'Missing feedback id.']);
        exit;
    }
    $stmt = $pdo->prepare("UPDATE reviewer_feedback SET status = ? WHERE feedback_id = ?");
    $stmt->execute(['Follow-up Sent', $id]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unsupported action.']);
