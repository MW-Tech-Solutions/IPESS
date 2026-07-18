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
$signupData = $_SESSION['signup_data'] ?? [];

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
    if (!$signupData || ($signupData['email'] ?? '') !== $session_email) {
        echo json_encode(['success' => false, 'message' => 'Registration details expired. Please start again.']);
        exit;
    }

    require_once __DIR__ . '/includes/register_user_helper.php';
    $regResult = register_new_student($pdo, $signupData);
    if (!$regResult['success']) {
        echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $regResult['message']]);
        exit;
    }

    unset($_SESSION['auth_otp'], $_SESSION['auth_email'], $_SESSION['auth_time'], $_SESSION['signup_data']);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
}
?>
