<?php
require_once __DIR__ . '/status_engine.php';

const COMPLETION_SUBMIT_THRESHOLD = 70;
const COMPLETION_FINAL_THRESHOLD = 85;

function completion_weights(): array {
    return [
        'personal' => 15,
        'programme' => 15,
        'academic' => 15,
        'olevel' => 10,
        'research' => 10,
        'referees' => 15,
        'documents' => 20,
    ];
}

function required_documents(): array {
    return [
        'passport',
        'olevel_1',
        'degree',
        'transcript',
        'proposal'
    ];
}

function calculate_completion(PDO $pdo, int $application_id, bool $referee_required = true): int {
    $weights = completion_weights();
    $score = 0;

    // Personal details
    $stmt = $pdo->prepare("SELECT surname, first_name, dob, sex, phone FROM personal_details WHERE application_id = ?");
    $stmt->execute([$application_id]);
    $personal = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($personal && $personal['surname'] && $personal['first_name'] && $personal['dob'] && $personal['sex'] && $personal['phone']) {
        $score += $weights['personal'];
    }

    // Programme choices
    $stmt = $pdo->prepare("SELECT faculty, department, degree_type, course, mode_of_study FROM programme_choices WHERE application_id = ?");
    $stmt->execute([$application_id]);
    $programme = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($programme && $programme['department'] && $programme['degree_type'] && $programme['course']) {
        $score += $weights['programme'];
    }

    // Academic (higher ed)
    $stmt = $pdo->prepare("SELECT highest_qualification, institution, grad_year FROM higher_education WHERE application_id = ?");
    $stmt->execute([$application_id]);
    $academic = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($academic && $academic['highest_qualification'] && $academic['institution'] && $academic['grad_year']) {
        $score += $weights['academic'];
    }

    // O'level
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM olevel_exams WHERE application_id = ?");
    $stmt->execute([$application_id]);
    if ((int) $stmt->fetchColumn() > 0) {
        $score += $weights['olevel'];
    }

    // Research
    $stmt = $pdo->prepare("SELECT research_area FROM research_details WHERE application_id = ?");
    $stmt->execute([$application_id]);
    $research = $stmt->fetchColumn();
    if (!empty($research)) {
        $score += $weights['research'];
    }

    // Referees
    if ($referee_required) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM referees WHERE application_id = ?");
        $stmt->execute([$application_id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $score += $weights['referees'];
        }
    } else {
        $score += $weights['referees'];
    }

    // Documents
    $stmt = $pdo->prepare("SELECT document_type FROM documents WHERE application_id = ?");
    $stmt->execute([$application_id]);
    $docs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $docs = array_map('strtolower', $docs);
    $required = required_documents();
    $missing = array_diff($required, $docs);
    if (count($missing) === 0) {
        $score += $weights['documents'];
    } else {
        $score += (int) round(($weights['documents'] * (count($required) - count($missing))) / max(count($required), 1));
    }

    return min(100, $score);
}

function update_completion(PDO $pdo, int $application_id): int {
    $percent = calculate_completion($pdo, $application_id);
    $stmt = $pdo->prepare("UPDATE applications SET completion_percentage = ? WHERE application_id = ?");
    $stmt->execute([$percent, $application_id]);
    return $percent;
}

function can_submit_application(PDO $pdo, int $application_id): bool {
    $percent = update_completion($pdo, $application_id);
    return $percent >= COMPLETION_SUBMIT_THRESHOLD;
}

function can_final_approve(PDO $pdo, int $application_id): array {
    $percent = update_completion($pdo, $application_id);

    $stmt = $pdo->prepare("
        SELECT 
            COUNT(d.doc_id) AS total_docs,
            SUM(CASE WHEN dv.verification_status = 'Verified' THEN 1 ELSE 0 END) AS verified_docs
        FROM documents d
        LEFT JOIN document_verification dv ON d.doc_id = dv.upload_id
        WHERE d.application_id = ?
    ");
    $stmt->execute([$application_id]);
    $docs = $stmt->fetch(PDO::FETCH_ASSOC);

    $docs_ok = ($docs && (int) $docs['total_docs'] > 0 && (int) $docs['total_docs'] === (int) $docs['verified_docs']);

    $ref_ok = true;
    if (table_exists($pdo, 'referee_uploads')) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM referee_uploads WHERE application_id = ? AND verified_status = 'Verified'");
        $stmt->execute([$application_id]);
        $ref_ok = (int) $stmt->fetchColumn() > 0;
    }

    return [
        'percent' => $percent,
        'percent_ok' => $percent >= COMPLETION_FINAL_THRESHOLD,
        'docs_ok' => $docs_ok,
        'ref_ok' => $ref_ok,
    ];
}
