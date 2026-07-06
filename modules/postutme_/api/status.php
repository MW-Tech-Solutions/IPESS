<?php
require_once __DIR__ . '/bootstrap.php';

$jamb = normalize_jamb($_GET['jamb_reg_no'] ?? '');
$stmt = db()->prepare('SELECT a.jamb_reg_no, a.screening_status, a.admission_status, sa.status application_status, p.status payment_status, sr.qualification_status FROM applicants a LEFT JOIN screening_applications sa ON sa.applicant_id = a.id LEFT JOIN payments p ON p.applicant_id = a.id LEFT JOIN screening_results sr ON sr.applicant_id = a.id WHERE a.jamb_reg_no = ? LIMIT 1');
$stmt->execute([$jamb]);
$row = $stmt->fetch();
if (!$row) {
    json_response(['ok' => false, 'message' => 'No application profile found.'], 404);
}
json_response(['ok' => true, 'status' => $row]);
