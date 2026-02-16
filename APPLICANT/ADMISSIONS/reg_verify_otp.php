<?php
// verify_otp.php
session_start();
require 'db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$otp_array = $input['otp'] ?? [];
$user_otp = implode('', $otp_array);

$email = $input['email'] ?? '';
$pass  = $input['password'] ?? '';

$session_otp   = $_SESSION['auth_otp'] ?? null;
$session_email = $_SESSION['auth_email'] ?? null;
$session_time  = $_SESSION['auth_time'] ?? 0;

// 1. Validate Expiration (5 min)
if (time() - $session_time > 300) {
    echo json_encode(['success' => false, 'message' => 'OTP expired. Please reload.']);
    exit;
}

// 2. Validate OTP Match
if (!$session_otp || $user_otp != $session_otp) {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP Code.']);
    exit;
}

// 3. Register User
try {
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $roleValue = 'STUDENT';
    $hasRole = false;
    $hasRoleId = false;

    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'role'");
    $stmt->execute();
    $hasRole = (bool) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'role_id'");
    $stmt->execute();
    $hasRoleId = (bool) $stmt->fetchColumn();

    if ($hasRole) {
        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)");
        $stmt->execute([$session_email, $hash, $roleValue]);
    } elseif ($hasRoleId) {
        $roleId = null;
        $roleStmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_key = ? LIMIT 1");
        $roleStmt->execute([$roleValue]);
        $roleId = $roleStmt->fetchColumn();
        if ($roleId) {
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, role_id) VALUES (?, ?, ?)");
            $stmt->execute([$session_email, $hash, $roleId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
            $stmt->execute([$session_email, $hash]);
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
        $stmt->execute([$session_email, $hash]);
    }
    
    unset($_SESSION['auth_otp'], $_SESSION['auth_email'], $_SESSION['auth_time']);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Registration failed.']);
}
?>
