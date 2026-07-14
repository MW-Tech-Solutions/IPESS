<?php
require_once __DIR__ . '/status_engine.php';
require_once __DIR__ . '/../ADMIN/includes/mailer.php';
require_once __DIR__ . '/../config/urls.php';

function create_referee_request(PDO $pdo, int $referee_id, int $application_id, ?int $actor_id = null): array {
    $token = bin2hex(random_bytes(20));
    $expires = (new DateTime('+7 days'))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("INSERT INTO referee_requests (referee_id, application_id, token, status, requested_by, requested_at, expires_at)
        VALUES (?, ?, ?, 'Requested', ?, NOW(), ?)");
    $stmt->execute([$referee_id, $application_id, $token, $actor_id, $expires]);

    return ['token' => $token, 'expires_at' => $expires];
}

function send_referee_request_email(PDO $pdo, int $referee_id, string $verify_link): bool {
    $stmt = $pdo->prepare("
        SELECT r.full_name, r.email, a.application_id, pd.first_name, pd.surname, acc.email as applicant_email, pd.phone
        FROM referees r
        JOIN applications a ON r.application_id = a.application_id
        JOIN personal_details pd ON a.application_id = pd.application_id
        JOIN users acc ON a.user_id = acc.user_id
        WHERE r.referee_id = ?
        LIMIT 1
    ");
    $stmt->execute([$referee_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$data) {
        return false;
    }

    $applicantName = $data['first_name'] . ' ' . $data['surname'];

    $content = sprintf(
        '<p>Dear %s,</p>
         <p>%s (%s) has nominated you as a referee for postgraduate admission.</p>
         <p>Please complete the referee assessment form and upload your passport and professional credentials using the link below.</p>',
        htmlspecialchars($data['full_name'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($applicantName, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($data['applicant_email'], ENT_QUOTES, 'UTF-8')
    );

    $refResult = portal_send_mail(
        $data['email'],
        $data['full_name'],
        'Referee Verification Request',
        $content,
        'Referee verification request',
        ['cta_label' => 'Complete Referee Form', 'cta_url' => $verify_link]
    );

    if (!empty($refResult['success'])) {
        portal_send_mail(
            $data['applicant_email'],
            $applicantName,
            'Referee Request Sent',
            '<p>Your referee has been contacted. Please ensure they respond using the verification link sent to them.</p>',
            'Referee request sent.'
        );
        return true;
    }

    return false;
}

function record_referee_submission(PDO $pdo, int $referee_id, int $application_id, array $details): void {
    $sql = "
        INSERT INTO referee_uploads (
            referee_id, application_id, work_email, passport_path, work_id_path, submitted_at, verified_status,
            referee_name, referee_title, referee_organization, referee_department, referee_position, referee_address, referee_phone,
            relationship, years_known,
            assessment_character_integrity, assessment_professional_competence, assessment_leadership_ability, assessment_communication_skills,
            assessment_teamwork, assessment_reliability, assessment_initiative, assessment_emotional_stability,
            major_strengths, weaknesses, recommendation, additional_comments,
            declaration_accepted, signature, declaration_date
        )
        VALUES (
            :referee_id, :application_id, :work_email, :passport_path, :work_id_path, NOW(), 'Submitted',
            :referee_name, :referee_title, :referee_organization, :referee_department, :referee_position, :referee_address, :referee_phone,
            :relationship, :years_known,
            :assessment_character_integrity, :assessment_professional_competence, :assessment_leadership_ability, :assessment_communication_skills,
            :assessment_teamwork, :assessment_reliability, :assessment_initiative, :assessment_emotional_stability,
            :major_strengths, :weaknesses, :recommendation, :additional_comments,
            :declaration_accepted, :signature, :declaration_date
        )
        ON DUPLICATE KEY UPDATE 
            work_email = VALUES(work_email), 
            passport_path = COALESCE(VALUES(passport_path), passport_path), 
            work_id_path = COALESCE(VALUES(work_id_path), work_id_path), 
            submitted_at = NOW(), 
            verified_status = 'Submitted',
            referee_name = VALUES(referee_name),
            referee_title = VALUES(referee_title),
            referee_organization = VALUES(referee_organization),
            referee_department = VALUES(referee_department),
            referee_position = VALUES(referee_position),
            referee_address = VALUES(referee_address),
            referee_phone = VALUES(referee_phone),
            relationship = VALUES(relationship),
            years_known = VALUES(years_known),
            assessment_character_integrity = VALUES(assessment_character_integrity),
            assessment_professional_competence = VALUES(assessment_professional_competence),
            assessment_leadership_ability = VALUES(assessment_leadership_ability),
            assessment_communication_skills = VALUES(assessment_communication_skills),
            assessment_teamwork = VALUES(assessment_teamwork),
            assessment_reliability = VALUES(assessment_reliability),
            assessment_initiative = VALUES(assessment_initiative),
            assessment_emotional_stability = VALUES(assessment_emotional_stability),
            major_strengths = VALUES(major_strengths),
            weaknesses = VALUES(weaknesses),
            recommendation = VALUES(recommendation),
            additional_comments = VALUES(additional_comments),
            declaration_accepted = VALUES(declaration_accepted),
            signature = VALUES(signature),
            declaration_date = VALUES(declaration_date)
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([
        'referee_id' => $referee_id,
        'application_id' => $application_id
    ], $details));
}

function verify_referee_submission(PDO $pdo, int $referee_id, int $actor_id, string $status, ?string $reason = null): void {
    $stmt = $pdo->prepare("UPDATE referee_uploads SET verified_status = ?, verified_by = ?, verified_at = NOW(), rejection_reason = ? WHERE referee_id = ?");
    $stmt->execute([$status, $actor_id, $reason, $referee_id]);

    $stmt = $pdo->prepare("SELECT application_id FROM referees WHERE referee_id = ?");
    $stmt->execute([$referee_id]);
    $app_id = $stmt->fetchColumn();

    if ($app_id && $status === 'Verified') {
        if (table_exists($pdo, 'application_progress')) {
            $stmtCheck = $pdo->prepare("SELECT progress_id FROM application_progress WHERE application_id = ? AND stage = 'Referee Report'");
            $stmtCheck->execute([$app_id]);
            if ($stmtCheck->fetch()) {
                $pdo->prepare("UPDATE application_progress SET stage_status = 'Completed', stage_updated_at = NOW() WHERE application_id = ? AND stage = 'Referee Report'")->execute([$app_id]);
            } else {
                $pdo->prepare("INSERT INTO application_progress (application_id, stage, stage_status, stage_updated_at) VALUES (?, 'Referee Report', 'Completed', NOW())")->execute([$app_id]);
            }
        }
    }
}
