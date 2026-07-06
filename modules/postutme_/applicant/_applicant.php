<?php
declare(strict_types=1);

function applicant_context(int $userId): array
{
    $stmt = db()->prepare('SELECT a.*, ap.date_of_birth, ap.marital_status, ap.religion, ap.nationality, ap.state_origin profile_state, ap.lga profile_lga, ap.home_address, ap.guardian_name, ap.guardian_phone, ap.emergency_contact, jc.course_applied, jc.course_name, jc.jamb_score, jc.utme_score, jc.gender jamb_gender, jc.utme_subject_1, jc.utme_subject_2, jc.utme_subject_3, jc.utme_subject_4, IF(ap.id IS NULL, 0, 1) profile_saved FROM applicants a JOIN jamb_candidates jc ON jc.id = a.jamb_candidate_id LEFT JOIN applicant_profiles ap ON ap.applicant_id = a.id WHERE a.user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $applicant = $stmt->fetch();
    if (!$applicant) {
        redirect('logout.php');
    }
    $pay = db()->prepare('SELECT p.*, i.invoice_number inv_invoice_number, i.rrr_reference inv_rrr_reference, i.status inv_status FROM payments p LEFT JOIN invoices i ON i.applicant_id = p.applicant_id WHERE p.applicant_id = ? ORDER BY p.id DESC LIMIT 1');
    $pay->execute([$applicant['id']]);
    $payment = $pay->fetch() ?: null;
    $form = db()->prepare('SELECT * FROM screening_applications WHERE applicant_id = ? LIMIT 1');
    $form->execute([$applicant['id']]);
    $application = $form->fetch() ?: null;
    return [$applicant, $payment, $application];
}
