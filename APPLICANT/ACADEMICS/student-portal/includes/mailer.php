<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../PhpMailer/src/Exception.php';
require_once __DIR__ . '/../../PhpMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../PhpMailer/src/SMTP.php';

function send_portal_mail(string $to, string $to_name, string $subject, string $html_body, string $text_body = ''): array
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = getenv('PORTAL_SMTP_HOST') ?: 'smtp.example.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('PORTAL_SMTP_USER') ?: 'user@example.com';
        $mail->Password = getenv('PORTAL_SMTP_PASS') ?: 'change_me';
        $mail->SMTPSecure = getenv('PORTAL_SMTP_SECURE') ?: PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) (getenv('PORTAL_SMTP_PORT') ?: 587);

        $from_email = getenv('PORTAL_FROM_EMAIL') ?: 'no-reply@jostum-portal.local';
        $from_name = getenv('PORTAL_FROM_NAME') ?: 'JOSTUM PG Portal';
        $mail->setFrom($from_email, $from_name);

        $mail->addAddress($to, $to_name);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html_body;
        $mail->AltBody = $text_body !== '' ? $text_body : strip_tags($html_body);

        $mail->send();
        return ['ok' => true, 'message' => 'Mail sent.'];
    } catch (Exception $e) {
        $combined = strtolower(trim((string) ($mail->ErrorInfo . ' ' . $e->getMessage())));
        if (
            strpos($combined, 'could not connect') !== false ||
            strpos($combined, 'failed to connect') !== false ||
            strpos($combined, 'timed out') !== false ||
            strpos($combined, 'network is unreachable') !== false ||
            strpos($combined, 'getaddrinfo') !== false ||
            strpos($combined, 'smtp connect() failed') !== false
        ) {
            return ['ok' => false, 'message' => 'Checking network: please check your internet connection and try again.'];
        }
        return ['ok' => false, 'message' => 'Mail error: ' . ($mail->ErrorInfo ?: $e->getMessage())];
    }
}
