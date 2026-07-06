<?php
require_once __DIR__ . '/bootstrap.php';

$jamb = normalize_jamb($_GET['jamb_reg_no'] ?? '');
if ($jamb === '') {
    json_response(['ok' => false, 'message' => 'JAMB registration number is required.'], 422);
}
$session = active_session();
$stmt = db()->prepare('SELECT * FROM jamb_candidates WHERE jamb_reg_no = ? AND admission_year = ? LIMIT 1');
$stmt->execute([$jamb, $session['year_label']]);
$candidate = $stmt->fetch();
if (!$candidate) {
    json_response(['ok' => false, 'message' => 'Your JAMB record was not found in the current JOSTUM candidate list.'], 404);
}
json_response(['ok' => true, 'candidate' => [
    'full_name' => candidate_full_name($candidate),
    'jamb_reg_no' => $candidate['jamb_reg_no'],
    'gender' => $candidate['gender'],
    'state' => $candidate['state_origin'],
    'lga' => $candidate['lga'],
    'jamb_score' => candidate_score($candidate),
    'course' => candidate_course($candidate),
    'utme_subjects' => array_values(array_filter([$candidate['utme_subject_1'], $candidate['utme_subject_2'], $candidate['utme_subject_3'], $candidate['utme_subject_4']])),
]]);
