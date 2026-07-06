<?php

require 'db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
$pass  = $data['password'] ?? '';

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT user_id FROM applicant_accounts WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists.']);
        exit;
    }

    $otp = rand(100000, 999999);

    $_SESSION['auth_otp'] = $otp;   
    $_SESSION['auth_email'] = $email; 
    $_SESSION['auth_time'] = time();

    $subject = 'Your Verification Code';
    $contentHtml = "<h2>Your OTP is: <b>$otp</b></h2><p>Valid for 5 minutes.</p>";
    $contentText = "Your OTP is: $otp. Valid for 5 minutes.";

    $result = portal_send_mail($email, $email, $subject, $contentHtml, $contentText);

    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => 'OTP sent successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Mailer Error: ' . $result['message']]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}