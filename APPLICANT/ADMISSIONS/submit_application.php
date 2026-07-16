<?php

require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

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

    $subject = "Application Received - ID: $appId";
    $contentHtml = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: auto; border: 1px solid #eee; padding: 20px;'>
            <h2 style='color: #6EB533; text-align: center;'>Application Confirmation</h2>
            <p>Dear $firstName $surname,</p>
            <p>Your postgraduate application has been received successfully.</p>
            
            <div style='background: #f2fce9; border: 1px dashed #6EB533; padding: 20px; text-align: center; margin: 20px 0;'>
                <span style='font-size: 12px; text-transform: uppercase; color: #666; letter-spacing: 1px;'>Your Application ID</span><br>
                <strong style='font-size: 28px; color: #6EB533;'>$appId</strong>
            </div>

            <p>Please keep this ID safe. You will need it to login to the portal and track your admission status.</p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='" . app_absolute_url('APPLICANT/ADMISSIONS/login.php') . "' style='background-color: #6EB533; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Login to Status Portal</a>
            </div>

            <p style='font-size: 12px; color: #888; border-top: 1px solid #eee; pt-10px;'>
                This is an automated acknowledgment. For further inquiries, contact the admissions office.
            </p>
        </div>
    ";
    $contentText = "Your application has been received. Your Application ID is: $appId";

    $result = portal_send_mail($email, "$firstName $surname", $subject, $contentHtml, $contentText);

    if ($result['success']) {
        echo json_encode([
            'status' => 'success',
            'appId' => $appId,
            'email' => $email
        ]);
    } else {
        throw new Exception($result['message']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => "Mailer Error: " . $e->getMessage()
    ]);
}

