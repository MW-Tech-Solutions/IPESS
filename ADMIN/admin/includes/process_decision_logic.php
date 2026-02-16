<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../../PHPMailer-master/src/Exception.php';
require_once '../../PHPMailer-master/src/PHPMailer.php';
require_once '../../PHPMailer-master/src/SMTP.php';

function processAdmissionDecision($pdo, $appNumber, $decision, $notes) {
    $newStatus = $decision === 'approve' ? 'Admitted' : 'Rejected';

    try {
        $sql = "UPDATE applications SET status = ? WHERE application_number = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$newStatus, $appNumber])) {
            $applicantSql = "
                SELECT p.email, p.first_name, p.surname 
                FROM personal_details p
                JOIN applications a ON p.application_id = a.application_id
                WHERE a.application_number = ?
            ";
            $applicantStmt = $pdo->prepare($applicantSql);
            $applicantStmt->execute([$appNumber]);
            $applicant = $applicantStmt->fetch(PDO::FETCH_ASSOC);

            if ($applicant) {
                $mail = new PHPMailer(true);

                try {
                    //Server settings - REPLACE WITH YOUR OWN
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'your_email@gmail.com'; // Your Gmail address
                    $mail->Password   = 'your_app_password';    // Your Gmail app password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    //Recipients
                    $mail->setFrom('no-reply@jostum.edu.ng', 'JOSTUM Admissions');
                    $mail->addAddress($applicant['email'], $applicant['first_name'] . ' ' . $applicant['surname']);

                    // Content
                    $mail->isHTML(true);
                    if ($newStatus === 'Admitted') {
                        $mail->Subject = 'Congratulations! Your Admission to JOSTUM';
                        $mail->Body    = "Dear " . htmlspecialchars($applicant['first_name']) . ",<br><br>We are pleased to inform you that your application to Joseph Sarwuan Tarka University, Makurdi has been successful. You have been admitted to your chosen programme.<br><br>Further details regarding your admission and registration will be sent to you shortly.<br><br>Congratulations!<br><br>Best regards,<br>The Admissions Team<br>JOSTUM";
                    } else {
                        $mail->Subject = 'Admission Status Update from JOSTUM';
                        $mail->Body    = "Dear " . htmlspecialchars($applicant['first_name']) . ",<br><br>Thank you for your interest in Joseph Sarwuan Tarka University, Makurdi and for taking the time to apply.<br><br>We received a large number of applications this year, and the selection process was very competitive. We regret to inform you that we are unable to offer you admission at this time.<br><br>We wish you the best in your future academic endeavors.<br><br>Sincerely,<br>The Admissions Team<br>JOSTUM";
                    }

                    $mail->send();
                    return ['success' => true, 'message' => "Application $appNumber has been $newStatus and an email notification has been sent."];

                } catch (Exception $e) {
                    // Log email error instead of echoing sensitive info
                    error_log("Mailer Error: {$mail->ErrorInfo}");
                    $combined = strtolower(trim((string) ($mail->ErrorInfo . ' ' . $e->getMessage())));
                    if (
                        strpos($combined, 'could not connect') !== false ||
                        strpos($combined, 'failed to connect') !== false ||
                        strpos($combined, 'timed out') !== false ||
                        strpos($combined, 'network is unreachable') !== false ||
                        strpos($combined, 'getaddrinfo') !== false ||
                        strpos($combined, 'smtp connect() failed') !== false
                    ) {
                        return ['success' => false, 'message' => 'Application status updated, but email could not be sent. Checking network: please check your internet connection and try again.'];
                    }
                    return ['success' => false, 'message' => "Application status was updated, but failed to send email notification. Please check the system email configuration."];
                }
            } else {
                return ['success' => false, 'message' => 'Could not find applicant details to send email.'];
            }
        } else {
            return ['success' => false, 'message' => 'Failed to update application status.'];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}
?>
