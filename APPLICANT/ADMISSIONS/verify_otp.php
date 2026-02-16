<?php
session_start();
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['otp'])) {
    echo json_encode(['success' => false, 'message' => 'OTP required']);
    exit;
}

if (!isset($_SESSION['otp'])) {
    echo json_encode(['success' => false, 'message' => 'OTP session expired']);
    exit;
}

if (time() - $_SESSION['otp_time'] > 300) {
    session_destroy();
    echo json_encode(['success' => false, 'message' => 'OTP expired']);
    exit;
}

if ($data['otp'] == $_SESSION['otp']) {
    $_SESSION['email_verified'] = true;

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP']);
}
