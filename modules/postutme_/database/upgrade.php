<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

function has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!has_column($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN $definition");
    }
}

$pdo = db();
$sql = file_get_contents(__DIR__ . '/enterprise_upgrade.sql');
$pdo->exec($sql);

add_column($pdo, 'users', 'username', 'username VARCHAR(80) NULL UNIQUE AFTER email');
add_column($pdo, 'users', 'two_factor_secret', 'two_factor_secret VARCHAR(255) NULL AFTER role');

add_column($pdo, 'jamb_candidates', 'admission_year', 'admission_year VARCHAR(20) NULL AFTER admission_session_id');
add_column($pdo, 'jamb_candidates', 'gender', 'gender VARCHAR(20) NULL AFTER other_names');
add_column($pdo, 'jamb_candidates', 'course_code', 'course_code VARCHAR(40) NULL AFTER lga');
add_column($pdo, 'jamb_candidates', 'course_name', 'course_name VARCHAR(190) NULL AFTER course_code');
add_column($pdo, 'jamb_candidates', 'jamb_score', 'jamb_score INT NULL AFTER course_name');
add_column($pdo, 'jamb_candidates', 'utme_subject_1', 'utme_subject_1 VARCHAR(120) NULL AFTER jamb_score');
add_column($pdo, 'jamb_candidates', 'utme_subject_2', 'utme_subject_2 VARCHAR(120) NULL AFTER utme_subject_1');
add_column($pdo, 'jamb_candidates', 'utme_subject_3', 'utme_subject_3 VARCHAR(120) NULL AFTER utme_subject_2');
add_column($pdo, 'jamb_candidates', 'utme_subject_4', 'utme_subject_4 VARCHAR(120) NULL AFTER utme_subject_3');
add_column($pdo, 'jamb_candidates', 'import_batch_id', 'import_batch_id BIGINT UNSIGNED NULL AFTER raw_payload');
add_column($pdo, 'jamb_candidates', 'is_registered', 'is_registered TINYINT(1) NOT NULL DEFAULT 0 AFTER import_batch_id');

add_column($pdo, 'applicants', 'application_number', 'application_number VARCHAR(80) NULL UNIQUE AFTER id');
add_column($pdo, 'applicants', 'screening_status', 'screening_status VARCHAR(60) NOT NULL DEFAULT "Not Started" AFTER contact_address');
add_column($pdo, 'applicants', 'admission_status', 'admission_status VARCHAR(60) NOT NULL DEFAULT "Not Started" AFTER screening_status');

add_column($pdo, 'payments', 'invoice_number', 'invoice_number VARCHAR(80) NULL UNIQUE AFTER provider');
add_column($pdo, 'payments', 'rrr_reference', 'rrr_reference VARCHAR(120) NULL UNIQUE AFTER reference');
add_column($pdo, 'payments', 'gateway_response', 'gateway_response JSON NULL AFTER metadata');

add_column($pdo, 'screening_applications', 'application_number', 'application_number VARCHAR(80) NULL UNIQUE AFTER applicant_id');
add_column($pdo, 'screening_applications', 'acknowledgement_code', 'acknowledgement_code VARCHAR(80) NULL UNIQUE AFTER officer_comment');

add_column($pdo, 'audit_logs', 'role', 'role VARCHAR(60) NULL AFTER user_id');
add_column($pdo, 'audit_logs', 'entity_type', 'entity_type VARCHAR(80) NULL AFTER subject_id');
add_column($pdo, 'audit_logs', 'entity_id', 'entity_id BIGINT UNSIGNED NULL AFTER entity_type');
add_column($pdo, 'audit_logs', 'old_value_json', 'old_value_json JSON NULL AFTER entity_id');
add_column($pdo, 'audit_logs', 'new_value_json', 'new_value_json JSON NULL AFTER old_value_json');

$pdo->exec('UPDATE jamb_candidates SET admission_year = (SELECT year_label FROM admission_sessions WHERE admission_sessions.id = jamb_candidates.admission_session_id) WHERE admission_year IS NULL');
$pdo->exec('UPDATE jamb_candidates SET jamb_score = utme_score WHERE jamb_score IS NULL AND utme_score IS NOT NULL');
$pdo->exec('UPDATE jamb_candidates SET course_name = course_applied WHERE course_name IS NULL AND course_applied IS NOT NULL');
$pdo->exec('UPDATE applicants a JOIN jamb_candidates jc ON jc.id = a.jamb_candidate_id SET jc.is_registered = 1');
$pdo->exec("ALTER TABLE payments MODIFY status ENUM('pending','paid','successful','failed') NOT NULL DEFAULT 'pending'");

echo "Enterprise upgrade completed.\n";
