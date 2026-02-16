<?php
session_start();
require '../db.php'; 

ob_clean(); 
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['application_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$app_id = $_SESSION['application_id'];
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    if ($action === 'mark_all') {
        $stmt = $pdo->prepare("UPDATE applicant_notifications SET is_read = 1 WHERE application_id = ? AND is_read = 0");
        $stmt->execute([$app_id]);
        echo json_encode(['success' => true]);
    } 
    elseif ($action === 'mark_one' && isset($input['id'])) {
        $notif_id = (int)$input['id'];
        $stmt = $pdo->prepare("UPDATE applicant_notifications SET is_read = 1 WHERE notification_id = ? AND application_id = ?");
        $stmt->execute([$notif_id, $app_id]);
        echo json_encode(['success' => true]);
    } 
    else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
