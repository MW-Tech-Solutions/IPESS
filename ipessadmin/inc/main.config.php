<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once __DIR__ . '/../../app/config/database.php';
    $con = db();
} catch (Throwable $e) {
    die("Database connection failed: " . $e->getMessage());
}

date_default_timezone_set("Africa/Lagos");
require_once __DIR__ . '/../../config/urls.php';

// Dynamic Session Key Mapping
if (isset($_SESSION['user_id'])) {
    $sessionUserId = (int)$_SESSION['user_id'];
    try {
        $stmtUser = $con->prepare("
            SELECT u.email, r.role_key 
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.role_id
            WHERE u.user_id = ?
            LIMIT 1
        ");
        $stmtUser->execute([$sessionUserId]);
        $sysUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
        
        if ($sysUser) {
            $email = $sysUser['email'] ?? '';
            $roleKey = $sysUser['role_key'] ?? '';
            
            // 1. Map userid to legacy user_access.userName
            $stmtAccess = $con->prepare("SELECT userName FROM user_access WHERE EmailAddress = ? LIMIT 1");
            $stmtAccess->execute([$email]);
            $userAccess = $stmtAccess->fetch(PDO::FETCH_ASSOC);
            if ($userAccess && !empty($userAccess['userName'])) {
                $_SESSION['userid'] = trim($userAccess['userName']);
            } else {
                $_SESSION['userid'] = $_SESSION['user_id'];
            }
            
            // 2. Map roleid to string role key
            $_SESSION['roleid'] = $roleKey;
        } else {
            // Fallback for legacy user_access users not present in the new users table
            $stmtLegacy = $con->prepare("SELECT userName, userRoleID FROM user_access WHERE staffIDs = ? LIMIT 1");
            $stmtLegacy->execute([$sessionUserId]);
            $legacyUser = $stmtLegacy->fetch(PDO::FETCH_ASSOC);
            if ($legacyUser) {
                $_SESSION['userid'] = trim($legacyUser['userName']);
                $roleMapKeys = [
                    1  => 'DEVELOPER',
                    12 => 'SUPER_ADMIN',
                    13 => 'ICTO',
                    2  => 'SUPERVISOR',
                    4  => 'HOD',
                    5  => 'FACULTY_OFFICER',
                    7  => 'ICT_ADMIN',
                    8  => 'REGISTRY',
                    11 => 'ACADEMIC_MANAGER'
                ];
                $_SESSION['roleid'] = $roleMapKeys[(int)$legacyUser['userRoleID']] ?? 'SUPER_ADMIN';
            } else {
                $_SESSION['userid'] = $_SESSION['user_id'];
                $_SESSION['roleid'] = $_SESSION['role'] ?? '';
            }
        }
    } catch (Throwable $e) {
        $_SESSION['userid'] = $_SESSION['user_id'];
        $_SESSION['roleid'] = $_SESSION['role'];
    }
}


	/*
	$servername = "localhost";
	$username = "fee_mysql";
	$password = "2018ICT@@P30";
	*/
	?>