<?php
session_start();
require_once __DIR__ . '/../../admin/includes/db.php';

header('Content-Type: application/json');

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS reviewer_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    application_code VARCHAR(50) NOT NULL,
    applicant_name VARCHAR(150) NOT NULL,
    programme VARCHAR(150) DEFAULT NULL,
    decision VARCHAR(30) DEFAULT NULL,
    score INT DEFAULT NULL,
    comment TEXT DEFAULT NULL,
    reviewer_name VARCHAR(150) DEFAULT NULL,
    decided_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'list') {
    $rows = $pdo->query("SELECT * FROM reviewer_history ORDER BY decided_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unsupported action.']);
