<?php
// process_signup.php
session_start();
require 'db.php';
header('Content-Type: application/json');

// Helper to send JSON response
function sendJson($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = filter_var(trim($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$pass  = $input['password'] ?? '';
$conf  = $input['confirm'] ?? '';

// 1. Validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJson(false, "Invalid email address.");
}
if (strlen($pass) < 6) {
    sendJson(false, "Password must be at least 6 characters.");
}
if ($pass !== $conf) {
    sendJson(false, "Passwords do not match.");
}

try {
    // 2. Check if Email Exists (CORRECTED: using 'user_id' instead of 'id')
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        sendJson(false, "Email is already registered. Please login.");
    }

    // 3. Generate OTP & Store in Session
    $otp = rand(100000, 999999);
    
    $_SESSION['auth_otp'] = $otp;
    $_SESSION['auth_email'] = $email; 
    $_SESSION['auth_time'] = time();

    // 4. Send Email (Simulation)
    // In production: mail($email, "Your OTP", "Code: $otp");
    
    // RETURNING OTP FOR TESTING (Remove 'debug_otp' in production)
    echo json_encode(['success' => true, 'message' => 'OTP sent.', 'debug_otp' => $otp]);

} catch (Exception $e) {
    // Log the actual error internally if needed
    sendJson(false, "Database error occurred.");
}
?>