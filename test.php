<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/ADMIN/includes/mailer.php';

$status = null;
$message = '';

function build_email_html(): string {
    $stages = [
        'Original Document Verification: 11 to 13 February, 2026',
        'Medical Fitness Examination: 16 February, 2026',
        'Appointment Letter Collection: 17 February, 2026',
        'Documentation & Clearance: 17 February, 2026'
    ];
    $listItems = '';
    foreach ($stages as $stage) {
        $listItems .= '<li style="margin-bottom:8px;">' . htmlspecialchars($stage) . '</li>';
    }

    return '
        <div style="font-family:Arial, sans-serif; background:#f6f8fb; padding:24px;">
            <div style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e6e9ef;">
                <div style="background:#0b5b3f; color:#ffffff; padding:20px 24px;">
                    <h2 style="margin:0; font-size:20px;">Congratulations!</h2>
                    <p style="margin:8px 0 0; font-size:14px;">Interview Result & Next Steps</p>
                </div>
                <div style="padding:24px; color:#1f2937;">
                    <p style="margin-top:0; font-size:15px;">Dear Muhammad Adamu Garba,</p>
                    <p style="font-size:15px; line-height:1.6;">
                        We are pleased to inform you that you have successfully passed the interview.
                        You are hereby invited to proceed to the following stages:
                    </p>
                    <ul style="padding-left:18px; margin:16px 0; font-size:15px; line-height:1.6; color:#111827;">
                        ' . $listItems . '
                    </ul>
                    <p style="font-size:14px; color:#4b5563; margin-bottom:0;">
                        Please come along with all required documents and arrive on time.
                        For any questions, kindly reply to this email.
                    </p>
                </div>
                <div style="padding:16px 24px; background:#f9fafb; color:#6b7280; font-size:12px;">
                    This is an automated message. Please do not share confidential information.
                </div>
            </div>
        </div>
    ';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = 'teckexpert4solutions.me@gmail.com';
    $subject = 'Congratulations - Interview Passed';
    $html = build_email_html();
    $result = portal_send_mail(
        $to,
        'Candidate',
        $subject,
        $html,
        'Congratulations! You have passed the interview and are invited to the next stages.'
    );
    $status = $result['success'] ? 'success' : 'error';
    $message = $result['message'] ?? ($result['success'] ? 'Mail sent.' : 'Mail failed.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="/ADMIN/images/logo.jpeg">
    <title>Test Mailer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="card shadow-sm mx-auto" style="max-width: 560px;">
            <div class="card-body">
                <h4 class="mb-2">Test Mailer</h4>
                <p class="text-muted">Send an enterprise-styled congratulatory email to <strong>teckexpert4solutions.me@gmail.com</strong>.</p>
                <?php if ($status): ?>
                    <div class="alert alert-<?php echo $status === 'success' ? 'success' : 'danger'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                <form method="post">
                    <button type="submit" class="btn btn-primary w-100">Send Test Email</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
