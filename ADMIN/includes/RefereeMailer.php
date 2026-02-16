<?php
$baseDir = __DIR__ . '/../../PhpMailer/src/'; 

require $baseDir . 'Exception.php';
require $baseDir . 'PHPMailer.php';
require $baseDir . 'SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class RefereeMailer {
    
    /**
     * Internal helper to configure PHPMailer with SMTP settings and common assets
     */
    private static function setupMailer() {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jostumpg@gmail.com';
        $mail->Password   = 'avajrmliqzokhbbi'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = 587;

        $mail->setFrom('jostumpg@gmail.com', 'JOSTUM PG Portal');
        $mail->isHTML(true);
        
        // Embed the logo once for all mail types
        $mail->addEmbeddedImage(__DIR__ . '/../../images/jostum.jpeg', 'jostum_logo'); 
        
        return $mail;
    }

    /**
     * Standard HTML Wrapper for consistent branding
     */
    private static function getHtmlTemplate($title, $content, $accentColor = '#0d6efd') {
        $year = date('Y');
        return "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;'>
            <div style='text-align: center; background-color: #ffffff; padding: 20px; border-bottom: 3px solid {$accentColor};'>
                <img src='cid:jostum_logo' alt='JOSTUM Logo' style='width: 80px; height: auto; margin-bottom: 10px;'>
                <h2 style='color: {$accentColor}; margin: 0; font-size: 24px;'>{$title}</h2>
                <p style='margin: 0; color: #666; font-size: 14px;'>Postgraduate School Admissions</p>
            </div>
            <div style='padding: 20px;'>
                {$content}
            </div>
            <div style='background-color: #f4f4f4; padding: 15px; text-align: center; font-size: 0.8em; color: #888;'>
                <p style='margin: 0;'>&copy; {$year} Joseph Sarwuan Tarka University, Makurdi.</p>
                <p style='margin: 5px 0;'>This is an automated message. Please do not reply.</p>
            </div>
        </div>";
    }

    public static function sendVerificationRequest($refereeEmail, $refereeName, $applicantData, $verificationLink) {
        try {
            $mail = self::setupMailer();
            $mail->addAddress($refereeEmail, $refereeName);
            $mail->Subject = 'Urgent: Referee Verification Request - ' . $applicantData['name'];

            $content = "
                <p>Dear <strong>{$refereeName}</strong>,</p>
                <p>You have been nominated as a referee for an applicant seeking admission into the <strong>Joseph Sarwuan Tarka University, Makurdi</strong>.</p>
                <div style='background-color: #f8f9fa; border-left: 4px solid #0d6efd; padding: 15px; margin: 20px 0;'>
                    <p style='margin: 0;'><strong>Applicant:</strong> {$applicantData['name']}</p>
                    <p style='margin: 5px 0;'><strong>Email:</strong> {$applicantData['email']}</p>
                    <p style='margin: 5px 0;'><strong>Phone:</strong> {$applicantData['phone']}</p>
                    
                </div>
                <p>To proceed, please verify your details and upload your <strong>Organization ID card</strong>.</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$verificationLink}' style='background-color: #198754; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Verify Details & Upload ID</a>
                </div>
                <p style='font-size: 0.8em; color: #0d6efd; word-break: break-all;'>{$verificationLink}</p>";

            $mail->Body = self::getHtmlTemplate('JOSTUM PG', $content);
            $mail->AltBody = "Dear {$refereeName}, please visit {$verificationLink} to verify your referee request for {$applicantData['name']}.";

            $mail->send();
            return ['success' => true, 'message' => 'Message has been sent'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $mail->ErrorInfo];
        }
    }

    public static function sendAcknowledgment($refereeEmail, $refereeName, $applicantName) {
        try {
            $mail = self::setupMailer();
            $mail->addAddress($refereeEmail, $refereeName);
            $mail->Subject = 'ID Receipt Acknowledged - ' . $applicantName;

            $content = "
                <p>Dear <strong>{$refereeName}</strong>,</p>
                <p>We have successfully received your identification document for <strong>{$applicantName}</strong>.</p>
                <p>Your submission is currently being reviewed. No further action is required.</p>";

            $mail->Body = self::getHtmlTemplate('Submission Received', $content, '#198754');
            
            $mail->send();
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $mail->ErrorInfo];
        }
    }

    public static function notifyApplicantOfRequest($applicantEmail, $applicantName, $refereeName) {
        try {
            $mail = self::setupMailer();
            $mail->addAddress($applicantEmail, $applicantName);
            $mail->Subject = 'Referee Notification Sent - ' . $refereeName;

            $content = "
                <p>Dear <strong>{$applicantName}</strong>,</p>
                <p>An automated verification request has been sent to your referee: <strong>{$refereeName}</strong>.</p>
                <p>Please ensure they check their email (including spam) to complete the process.</p>";

            $mail->Body = self::getHtmlTemplate('Referee Request Update', $content);

            $mail->send();
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $mail->ErrorInfo];
        }
    }
}