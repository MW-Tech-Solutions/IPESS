<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';
header('Content-Type: application/json');

// Super admin guard
if (normalize_role(current_user_role()) !== 'SUPER_ADMIN') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only Super Admins can perform this action.']);
    exit;
}

try {
    $pdo = db();
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'toggle';

if ($action === 'toggle') {
    $module_key = trim($_POST['module_key'] ?? '');
    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;
    
    if (empty($module_key)) {
        echo json_encode(['success' => false, 'message' => 'Module key is required.']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE system_modules SET is_active = ? WHERE module_key = ?");
        $stmt->execute([$is_active, $module_key]);
        
        $status_text = $is_active === 1 ? 'Activated' : 'Deactivated';
        
        // Log to audit_logs
        $stmtLog = $pdo->prepare("INSERT INTO audit_logs (actor_user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmtLog->execute([
            $_SESSION['user_id'] ?? 0,
            'Toggle Module',
            "Super Admin toggled module '{$module_key}' to status '{$status_text}'",
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
        
        echo json_encode(['success' => true, 'message' => "Module {$status_text} successfully."]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
