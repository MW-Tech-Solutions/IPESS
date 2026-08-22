<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../app/bootstrap.php';
header('Content-Type: application/json');

// Access guard: SUPER_ADMIN, DEVELOPER, or page-assigned roles/users
$normRole = normalize_role(current_user_role());
$hasAccess = false;
if ($normRole === 'SUPER_ADMIN' || $normRole === 'DEVELOPER') {
    $hasAccess = true;
} else {
    // Check if the parent UI page (modules.php) is assigned to this user's role or ID
    $currentFile = 'modules.php';
    $userId = $_SESSION['user_id'] ?? $_SESSION['userid'] ?? '';
    $roleId = $_SESSION['role'] ?? $_SESSION['roleid'] ?? '';
    $userRoles = array_unique(array_filter([$roleId, $normRole, current_user_role()]));
    try {
        require_once __DIR__ . '/../../../../app/config/database.php';
        $pdo = db();
        
        // Resolve exact DB userRoleID mapping if applicable (similar to sidebar.php)
        if ($userId) {
            $stmtStaffRole = $pdo->prepare("SELECT userRoleID FROM user_access WHERE userName = ? OR staffIDs = ? OR EmailAddress = ? LIMIT 1");
            $stmtStaffRole->execute([$userId, $userId, $userId]);
            $uRoleId = (int)$stmtStaffRole->fetchColumn();
            if ($uRoleId > 0) {
                $userRoles[] = (string)$uRoleId;
                
                // Resolve modern role_key
                $stmtModern = $pdo->prepare("SELECT role_key, role_name FROM roles WHERE role_id = ? LIMIT 1");
                $stmtModern->execute([$uRoleId]);
                $modRow = $stmtModern->fetch(PDO::FETCH_ASSOC);
                if ($modRow) {
                    if (!empty($modRow['role_key'])) $userRoles[] = $modRow['role_key'];
                    if (!empty($modRow['role_name'])) $userRoles[] = $modRow['role_name'];
                }
            }
        }
        
        $userRoles = array_values(array_unique(array_filter($userRoles)));
        
        if (!empty($userRoles)) {
            $placeholders = implode(',', array_fill(0, count($userRoles), '?'));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM right_page_main_menus WHERE (page_url LIKE ? OR page_url LIKE ?) AND roleID IN ($placeholders) AND page_status = '1'");
            $stmt->execute(array_merge(['%' . $currentFile, $currentFile], $userRoles));
            if ((int)$stmt->fetchColumn() > 0) {
                $hasAccess = true;
            }
        }
        if (!$hasAccess && $userId) {
            $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM pesonal_right_page_main_menus WHERE (page_url LIKE ? OR page_url LIKE ?) AND userID = ? AND page_status = '1'");
            $stmt2->execute(['%' . $currentFile, $currentFile, $userId]);
            if ((int)$stmt2->fetchColumn() > 0) {
                $hasAccess = true;
            }
        }
    } catch (Throwable $e) {}
}

if (!$hasAccess) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to perform this action.']);
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
