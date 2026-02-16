<?php
$baseDir = __DIR__ . '/../../PhpMailer/src/'; 

require $baseDir . 'Exception.php';
require $baseDir . 'PHPMailer.php';
require $baseDir . 'SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class RefereeMailer {
    
    /**
     * Sends a notification to a referee
     * * @param string $refereeEmail Email of the referee
     * @param string $refereeName Name of the referee
     * @param array $applicantData ['name' => '', 'email' => '', 'phone' => '']
     * @param string $verificationLink The URL to the verification portal
     */
    public static function sendVerificationRequest($refereeEmail, $refereeName, $applicantData, $verificationLink) {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'jostumpg@gmail.com';
            $mail->Password   = 'avajrmliqzokhbbi'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('jostumpg@gmail.com', 'JOSTUM PG Portal');
            $mail->addAddress($refereeEmail, $refereeName);

        $mail->isHTML(true);
            $mail->Subject = 'Urgent: Referee Verification Request - ' . $applicantData['name'];
            
            // Embed the logo (CID is 'jostum_logo')
            // Path: admin/includes/../../images/jostum.jpeg => applicants/images/jostum.jpeg
            $mail->addEmbeddedImage(__DIR__ . '/../../images/jostum.jpeg', 'jostum_logo'); 

            // Professional HTML Template
            $mail->Body = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;'>
                <div style='text-align: center; background-color: #ffffff; padding: 20px; border-bottom: 3px solid #0d6efd;'>
                    <img src='cid:jostum_logo' alt='JOSTUM Logo' style='width: 80px; height: auto; margin-bottom: 10px;'>
                    <h2 style='color: #0d6efd; margin: 0; font-size: 24px;'>JOSTUM PG</h2>
                    <p style='margin: 0; color: #666; font-size: 14px;'>Postgraduate School Admissions</p>
                </div>
                
                <div style='padding: 20px;'>
                    <p>Dear <strong>{$refereeName}</strong>,</p>
                    
                    <p>You have been nominated as a referee for an applicant seeking admission into the <strong>Joseph Sarwuan Tarka University, Makurdi (Postgraduate School)</strong>.</p>
                    
                    <div style='background-color: #f8f9fa; border-left: 4px solid #0d6efd; padding: 15px; margin: 20px 0;'>
                        <p style='margin: 0;'><strong>Applicant Name:</strong> {$applicantData['name']}</p>
                        <p style='margin: 5px 0;'><strong>Applicant Email:</strong> {$applicantData['email']}</p>
                        <p style='margin: 0;'><strong>Applicant Phone:</strong> {$applicantData['phone']}</p>
                    </div>
                    
                    <p>To proceed, we require you to verify your details and upload a scanned copy of your <strong>Organization ID card</strong> for authentication.</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$verificationLink}' 
                           style='background-color: #198754; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>
                           Verify Details & Upload ID
                        </a>
                    </div>
                    
                    <p style='font-size: 0.9em; color: #666;'>If the button above does not work, copy and paste the link below into your browser:</p>
                    <p style='font-size: 0.8em; color: #0d6efd; word-break: break-all;'>{$verificationLink}</p>
                </div>
                
                <div style='background-color: #f4f4f4; padding: 15px; text-align: center; font-size: 0.8em; color: #888;'>
                    <p style='margin: 0;'>&copy; " . date('Y') . " Joseph Sarwuan Tarka University, Makurdi.</p>
                    <p style='margin: 5px 0;'>This is an automated message. Please do not reply.</p>
                </div>
            </div>";
            $mail->AltBody = "Dear {$refereeName}, You have been nominated as a referee for {$applicantData['name']}. Please visit {$verificationLink} to verify your details.";

            $mail->send();
            return ['success' => true, 'message' => 'Message has been sent'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"];
        }
    }
    /**
 * Sends an acknowledgment to a referee that their ID has been received
 */
public static function sendAcknowledgment($refereeEmail, $refereeName, $applicantName) {
    $mail = new PHPMailer(true);
    try {
        // Server settings (reuse your existing config)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jostumpg@gmail.com';
        $mail->Password   = 'avajrmliqzokhbbi'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = 587;

        $mail->setFrom('jostumpg@gmail.com', 'JOSTUM PG Portal');
        $mail->addAddress($refereeEmail, $refereeName);
        $mail->isHTML(true);
        $mail->Subject = 'ID Receipt Acknowledged - ' . $applicantName;

        $mail->addEmbeddedImage(__DIR__ . '/../../images/jostum.jpeg', 'jostum_logo'); 

        $mail->Body = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;'>
            <div style='text-align: center; background-color: #ffffff; padding: 20px; border-bottom: 3px solid #198754;'>
                <img src='cid:jostum_logo' alt='JOSTUM Logo' style='width: 80px; height: auto; margin-bottom: 10px;'>
                <h2 style='color: #198754; margin: 0; font-size: 24px;'>Submission Received</h2>
            </div>
            <div style='padding: 20px;'>
                <p>Dear <strong>{$refereeName}</strong>,</p>
                <p>This is to acknowledge that we have successfully received your identification document regarding the referee request for <strong>{$applicantName}</strong>.</p>
                <p>Your submission is currently being reviewed by the Postgraduate School admissions board. No further action is required from you at this time.</p>
                <p>Thank you for your cooperation.</p>
            </div>
            <div style='background-color: #f4f4f4; padding: 15px; text-align: center; font-size: 0.8em; color: #888;'>
                <p style='margin: 0;'>&copy; " . date('Y') . " Joseph Sarwuan Tarka University, Makurdi.</p>
            </div>
        </div>";

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}
/**
 * Notifies the applicant that a verification request was sent to their referee
 */
public static function notifyApplicantOfRequest($applicantEmail, $applicantName, $refereeName) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jostumpg@gmail.com';
        $mail->Password   = 'avajrmliqzokhbbi'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = 587;

        $mail->setFrom('jostumpg@gmail.com', 'JOSTUM PG Portal');
        $mail->addAddress($applicantEmail, $applicantName);
        $mail->isHTML(true);
        $mail->Subject = 'Referee Notification Sent - ' . $refereeName;

        $mail->addEmbeddedImage(__DIR__ . '/../../images/jostum.jpeg', 'jostum_logo'); 

        $mail->Body = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;'>
            <div style='text-align: center; background-color: #ffffff; padding: 20px; border-bottom: 3px solid #0d6efd;'>
                <img src='cid:jostum_logo' alt='JOSTUM Logo' style='width: 80px; height: auto; margin-bottom: 10px;'>
                <h2 style='color: #0d6efd; margin: 0; font-size: 22px;'>Referee Request Update</h2>
            </div>
            <div style='padding: 20px;'>
                <p>Dear <strong>{$applicantName}</strong>,</p>
                <p>This is to inform you that an automated verification request has been sent to your nominated referee: <strong>{$refereeName}</strong>.</p>
                <p>You may wish to reach out to them to ensure they check their email (including the spam folder) and complete the verification process promptly.</p>
                <p>You can track your application status via the PG Portal.</p>
            </div>
            <div style='background-color: #f4f4f4; padding: 15px; text-align: center; font-size: 0.8em; color: #888;'>
                <p style='margin: 0;'>&copy; " . date('Y') . " Joseph Sarwuan Tarka University, Makurdi.</p>
            </div>
        </div>";

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}
}