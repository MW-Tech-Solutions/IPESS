<?php

require 'db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['email'])) {
    echo json_encode(['success' => false, 'message' => 'Email not received']);
    exit;
}

$email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

$isResend = !empty($data['resend']);
if (!$isResend) {
    $requiredFields = [
        'surname' => 'Surname',
        'first_name' => 'First name',
        'phone' => 'Phone number',
        'mode_of_study' => 'Mode of study',
        'programme_option' => 'Programme option',
        'programme' => 'Programme',
        'password' => 'Password',
        'confirm' => 'Confirm password',
    ];

    foreach ($requiredFields as $field => $label) {
        if (trim((string) ($data[$field] ?? '')) === '') {
            echo json_encode(['success' => false, 'message' => $label . ' is required.']);
            exit;
        }
    }

    if (strlen((string) $data['password']) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit;
    }

    if (($data['password'] ?? '') !== ($data['confirm'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }
}

try {
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists.']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

$verificationActive = 1;
try {
    $settingsStmt = $pdo->query("SELECT student_verification_active FROM system_settings LIMIT 1");
    if ($settingsStmt) {
        $val = $settingsStmt->fetchColumn();
        if ($val !== false) {
            $verificationActive = (int)$val;
        }
    }
} catch (Throwable $se) {
    // Ignore setting query failures
}

if ($verificationActive === 0 && !$isResend) {
    require_once __DIR__ . '/includes/register_user_helper.php';
    $signupData = [
        'surname' => trim((string) ($data['surname'] ?? '')),
        'first_name' => trim((string) ($data['first_name'] ?? '')),
        'other_name' => trim((string) ($data['other_name'] ?? '')),
        'phone' => trim((string) ($data['phone'] ?? '')),
        'email' => $email,
        'mode_of_study' => (int) ($data['mode_of_study'] ?? 0),
        'programme_option' => (int) ($data['programme_option'] ?? 0),
        'programme' => (int) ($data['programme'] ?? 0),
        'department' => (int) ($data['department'] ?? 0),
        'faculty' => (int) ($data['faculty'] ?? 0),
        'password' => (string) ($data['password'] ?? ''),
    ];
    $regResult = register_new_student($pdo, $signupData);
    if ($regResult['success']) {
        echo json_encode(['success' => true, 'verification_disabled' => true]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $regResult['message']]);
        exit;
    }
}
$otp = rand(100000, 999999);

$_SESSION['auth_otp'] = $otp;   
$_SESSION['auth_email'] = $email; 
$_SESSION['auth_time'] = time();  

if (!$isResend) {
    $_SESSION['signup_data'] = [
        'surname' => trim((string) ($data['surname'] ?? '')),
        'first_name' => trim((string) ($data['first_name'] ?? '')),
        'other_name' => trim((string) ($data['other_name'] ?? '')),
        'phone' => trim((string) ($data['phone'] ?? '')),
        'email' => $email,
        'mode_of_study' => (int) ($data['mode_of_study'] ?? 0),
        'programme_option' => (int) ($data['programme_option'] ?? 0),
        'programme' => (int) ($data['programme'] ?? 0),
        'department' => (int) ($data['department'] ?? 0),
        'faculty' => (int) ($data['faculty'] ?? 0),
        'password' => (string) ($data['password'] ?? ''),
    ];
}

$subject = 'Your Verification Code';
$contentHtml = "<h2>Your OTP is: <b>$otp</b></h2><p>This code is valid for 5 minutes.</p>";
$contentText = "Your OTP is: $otp. Valid for 5 minutes.";

$result = portal_send_mail($email, $email, $subject, $contentHtml, $contentText);

if ($result['success']) {
    echo json_encode(['success' => true, 'message' => 'OTP sent successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Mailer Error: ' . $result['message']]);
}
