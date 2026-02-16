<?php
session_start();
require_once __DIR__ . '/../../config/urls.php';
header('Content-Type: application/json');

$baseDir = __DIR__ . '/PhpMailer/src/'; 
if (!file_exists($baseDir . 'PHPMailer.php')) {
    echo json_encode(['status' => 'error', 'message' => "PHPMailer files not found at: " . $baseDir]);
    exit;
}

require $baseDir . 'Exception.php';
require $baseDir . 'PHPMailer.php';
require $baseDir . 'SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    $year = date('Y');
    $randomDigits = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    $appId = "PG-{$year}-{$randomDigits}";

    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $surname = htmlspecialchars($_POST['surname'] ?? 'Applicant');
    $firstName = htmlspecialchars($_POST['firstName'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("A valid recipient email is required.");
    }

    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'jostumpg@gmail.com'; 
    $mail->Password   = 'avajrmliqzokhbbi'; 
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
    $mail->Port       = 587;

    $mail->setFrom('jostumpg@gmail.com', 'JOSTUM-PG Admissions');
    $mail->addAddress($email, "$firstName $surname");

    $mail->isHTML(true);
    $mail->Subject = "Application Received - ID: $appId";
    
    $mail->Body = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: auto; border: 1px solid #eee; padding: 20px;'>
            <h2 style='color: #0d6efd; text-align: center;'>Application Confirmation</h2>
            <p>Dear $firstName $surname,</p>
            <p>Your postgraduate application has been received successfully.</p>
            
            <div style='background: #e7f1ff; border: 1px dashed #0d6efd; padding: 20px; text-align: center; margin: 20px 0;'>
                <span style='font-size: 12px; text-transform: uppercase; color: #666; letter-spacing: 1px;'>Your Application ID</span><br>
                <strong style='font-size: 28px; color: #0d6efd;'>$appId</strong>
            </div>

            <p>Please keep this ID safe. You will need it to login to the portal and track your admission status.</p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='" . app_url('APPLICANT/ADMISSIONS/login.php') . "' style='background-color: #0d6efd; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Login to Status Portal</a>
            </div>

            <p style='font-size: 12px; color: #888; border-top: 1px solid #eee; pt-10px;'>
                This is an automated acknowledgment. For further inquiries, contact the admissions office.
            </p>
        </div>
    ";

    $mail->AltBody = "Your application has been received. Your Application ID is: $appId";

    $mail->send();

    echo json_encode([
        'status' => 'success',
        'appId' => $appId,
        'email' => $email
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => "Mailer Error: {$mail->ErrorInfo}"
    ]);
}
