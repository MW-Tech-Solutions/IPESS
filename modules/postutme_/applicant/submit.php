<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/_applicant.php';

$user = require_login(['applicant']);
verify_csrf();
if (empty($_POST['declaration'])) {
    flash('error', 'Please accept the declaration before final submission.');
    redirect('applicant/review.php');
}
[$applicant, $payment, $application] = applicant_context((int) $user['id']);
if (!payment_confirmed($payment) || !$application) {
    redirect('applicant/dashboard.php');
}
$code = 'ACK-' . strtoupper(bin2hex(random_bytes(4)));
db()->beginTransaction();
db()->prepare('UPDATE screening_applications SET status = "submitted", submitted_at = NOW(), acknowledgement_code = COALESCE(acknowledgement_code, ?) WHERE applicant_id = ? AND status = "draft"')->execute([$code, $applicant['id']]);
db()->prepare('UPDATE applicants SET screening_status = "Submitted" WHERE id = ?')->execute([$applicant['id']]);
db()->commit();
audit_log('submitted screening application', 'screening_application', (int) $application['id']);
flash('success', 'Application submitted successfully.');
redirect('applicant/slip.php');
