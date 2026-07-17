<?php
require_once __DIR__ . '/../../app/bootstrap.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class RefereeMailer {
    
    /**
     * Internal helper to configure PHPMailer with SMTP settings and common assets
     */
    private static function setupMailer() {
        $config = portal_mail_config();
        $mail = new PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->Port = $config['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['user'];
        $mail->Password = $config['pass'];
        
        if ($config['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($config['encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
        }

        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->isHTML(true);
        
        $logoPath = __DIR__ . '/../images/logo.jpeg';
        if (file_exists($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'ipess_logo'); 
        }
        
        return $mail;
    }

    /**
     * Standard HTML Wrapper for consistent branding
     */
    private static function getHtmlTemplate($title, $content, $accentColor = '#782D32') {
        $year = date('Y');
        return "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;'>
            <div style='text-align: center; background-color: #ffffff; padding: 20px; border-bottom: 3px solid {$accentColor};'>
                <img src='cid:ipess_logo' alt='IPESS Logo' style='width: 80px; height: auto; margin-bottom: 10px;'>
                <h2 style='color: {$accentColor}; margin: 0; font-size: 24px;'>{$title}</h2>
                <p style='margin: 0; color: #666; font-size: 14px;'>IPESS Admissions Portal</p>
            </div>
            <div style='padding: 20px;'>
                {$content}
            </div>
            <div style='background-color: #f4f4f4; padding: 15px; text-align: center; font-size: 0.8em; color: #888;'>
                <p style='margin: 0;'>&copy; {$year} IPESS JOSTUM.</p>
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
                <p>You have been nominated as a referee for an applicant seeking admission into the <strong>Institute of Procurement, Environmental and Social Standards (IPESS), JOSTUM</strong>.</p>
                <div style='background-color: #f8f9fa; border-left: 4px solid #782D32; padding: 15px; margin: 20px 0;'>
                    <p style='margin: 0;'><strong>Applicant:</strong> {$applicantData['name']}</p>
                    <p style='margin: 5px 0;'><strong>Email:</strong> {$applicantData['email']}</p>
                    <p style='margin: 5px 0;'><strong>Phone:</strong> {$applicantData['phone']}</p>
                    
                </div>
                <p>To proceed, please verify your details and upload your <strong>Organization ID card</strong>.</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$verificationLink}' style='background-color: #6EB533; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Verify Details & Upload ID</a>
                </div>
                <p style='font-size: 0.8em; color: #782D32; word-break: break-all;'>{$verificationLink}</p>";

            $mail->Body = self::getHtmlTemplate('IPESS JOSTUM', $content);
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