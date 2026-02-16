<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

$baseDir = __DIR__ . '/../PhpMailer/src/'; 
if (!file_exists($baseDir . 'PHPMailer.php')) {
    echo json_encode(['success' => false, 'message' => "PHPMailer folder not found."]);
    exit;
}

require $baseDir . 'Exception.php';
require $baseDir . 'PHPMailer.php';
require $baseDir . 'SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'jostumpg@gmail.com';
    $mail->Password   = 'avajrmliqzokhbbi'; 
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
    $mail->Port       = 587;

    $mail->setFrom('jostumpg@gmail.com', 'JOSTUM-PG');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Your Verification Code';
    $mail->Body    = "<h2>Your OTP is: <b>$otp</b></h2><p>Valid for 5 minutes.</p>";

    $mail->send();

    echo json_encode(['success' => true, 'message' => 'OTP sent successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Mailer Error: {$mail->ErrorInfo}"]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}