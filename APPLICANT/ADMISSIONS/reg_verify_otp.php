<?php
// verify_otp.php
session_start();
require 'db.php';
header('Content-Type: application/json');

ob_start();

function send_json_response(array $response) {
    if (ob_get_length()) {
        $extraOutput = ob_get_clean();
        if (trim($extraOutput) !== '') {
            error_log("Captured extra output in reg_verify_otp.php: " . $extraOutput);
        }
    }
    echo json_encode($response);
    exit;
}

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
    send_json_response(['success' => false, 'message' => 'OTP expired. Please reload.']);
}

// 2. Validate OTP Match
if (!$session_otp || $user_otp != $session_otp) {
    send_json_response(['success' => false, 'message' => 'Invalid OTP Code.']);
}

// 3. Register User
try {
    if (!$signupData || ($signupData['email'] ?? '') !== $session_email) {
        send_json_response(['success' => false, 'message' => 'Registration details expired. Please start again.']);
    }

    require_once __DIR__ . '/includes/register_user_helper.php';
    $regResult = register_new_student($pdo, $signupData);
    if (!$regResult['success']) {
        send_json_response(['success' => false, 'message' => 'Registration failed: ' . $regResult['message']]);
    }

    unset($_SESSION['auth_otp'], $_SESSION['auth_email'], $_SESSION['auth_time'], $_SESSION['signup_data']);
    send_json_response(['success' => true]);
} catch (Throwable $e) {
    send_json_response(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
}
