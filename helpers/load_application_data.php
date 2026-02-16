<?php


if (!isset($_SESSION['user_id']) || !isset($pdo)) {
    return; 
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT application_id, current_step FROM applications WHERE user_id = ? ORDER BY application_id DESC LIMIT 1");
$stmt->execute([$user_id]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    return; 
}

$app_id = $app['application_id'];
$_SESSION['form_data'] = []; 

$stmt = $pdo->prepare("SELECT * FROM personal_details WHERE application_id = ?");
$stmt->execute([$app_id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if ($p) {
    $_SESSION['form_data']['step_1'] = [
        'surname'       => $p['surname'],
        'firstName'     => $p['first_name'],
        'otherName'     => $p['other_name'],
        'dob'           => $p['dob'],
        'sex'           => $p['sex'],
        'nationality'   => $p['nationality'],
        'state'         => $p['state_origin'],
        'lga'           => $p['lga'],
        'phone'         => $p['phone'],
        'address'       => $p['address'],
        'email'         => $_SESSION['user_email'] 
    ];
}

$stmt = $pdo->prepare("SELECT * FROM programme_choices WHERE application_id = ?");
$stmt->execute([$app_id]);
$prog = $stmt->fetch(PDO::FETCH_ASSOC);

if ($prog) {
    $_SESSION['form_data']['step_2'] = [
        'faculty'     => $prog['faculty'],
        'department'  => $prog['department'],
        'degree_type' => $prog['degree_type'],
        'mode'        => $prog['mode_of_study'],
        'course'      => $prog['course']
    ];
}

$stmt = $pdo->prepare("SELECT * FROM higher_education WHERE application_id = ?");
$stmt->execute([$app_id]);
$high = $stmt->fetch(PDO::FETCH_ASSOC);

if ($high) {
    $_SESSION['form_data']['step_3'] = [
        'highest_qualification' => $high['highest_qualification'],
        'course_study'          => $high['course_study'],
        'institution'           => $high['institution'],
        'grad_year'             => $high['grad_year'],
        'cgpa'                  => $high['cgpa'],
        'mode_study'            => $high['mode_study']
    ];
}

$stmt = $pdo->prepare("SELECT * FROM olevel_exams WHERE application_id = ? ORDER BY sitting_number ASC");
$stmt->execute([$app_id]);
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($exams as $exam) {
    $prefix = ($exam['sitting_number'] == 1) ? 'ssce1' : 'ssce2';
    
    $_SESSION['form_data']['step_3'][$prefix . '_type'] = $exam['exam_type'];
    $_SESSION['form_data']['step_3'][$prefix . '_school'] = $exam['school_name'];
    $_SESSION['form_data']['step_3'][$prefix . '_year'] = $exam['exam_year'];
    $_SESSION['form_data']['step_3'][$prefix . '_exam_number'] = $exam['exam_number'];

    $stmtRes = $pdo->prepare("SELECT subject_name, grade FROM olevel_results WHERE exam_id = ?");
    $stmtRes->execute([$exam['id']]);
    $results = $stmtRes->fetchAll(PDO::FETCH_ASSOC);

    $subjects = [];
    $grades = [];
    foreach ($results as $res) {
        $subjects[] = $res['subject_name'];
        $grades[] = $res['grade'];
    }
    
    $_SESSION['form_data']['step_3'][$prefix . '_subjects'] = $subjects;
    $_SESSION['form_data']['step_3'][$prefix . '_grades'] = $grades;
}

$stmt = $pdo->prepare("SELECT * FROM nysc_details WHERE application_id = ?");
$stmt->execute([$app_id]);
$nysc = $stmt->fetch(PDO::FETCH_ASSOC);

if ($nysc) {
    $_SESSION['form_data']['step_4'] = [
        'nysc_status' => $nysc['nysc_status'],
        'nysc_number' => $nysc['certificate_number'],
        'nysc_year'   => $nysc['completion_year']
    ];
}

$stmt = $pdo->prepare("SELECT * FROM work_experience WHERE application_id = ?");
$stmt->execute([$app_id]);
$work = $stmt->fetch(PDO::FETCH_ASSOC);

if ($work) {
    $_SESSION['form_data']['step_5'] = [
        'emp_status'       => $work['employment_status'],
        'employer'         => $work['employer'],
        'job_title'        => $work['job_title'],
        'years_experience' => $work['years_experience']
    ];
}

$stmt = $pdo->prepare("SELECT * FROM research_details WHERE application_id = ?");
$stmt->execute([$app_id]);
$res = $stmt->fetch(PDO::FETCH_ASSOC);

if ($res) {
    $_SESSION['form_data']['step_6'] = [
        'proposed_research_area'        => $res['research_area'],
        'reason_for_choosing_programme' => $res['reason_for_choosing'],
        'statement_of_purpose'          => $res['statement_of_purpose'],
        'career_objectives'             => $res['career_objectives']
    ];
}

$stmt = $pdo->prepare("SELECT * FROM referees WHERE application_id = ?");
$stmt->execute([$app_id]);
$referees = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($referees) {
    $ref_names = []; $ref_titles = []; $ref_orgs = []; $ref_emails = []; $ref_phones = [];
    
    foreach ($referees as $ref) {
        $ref_names[] = $ref['full_name'];
        $ref_titles[] = $ref['title'];
        $ref_orgs[] = $ref['organization'];
        $ref_emails[] = $ref['email'];
        $ref_phones[] = $ref['phone'];
    }

    $_SESSION['form_data']['step_7'] = [
        'ref_name'  => $ref_names,
        'ref_title' => $ref_titles,
        'ref_org'   => $ref_orgs,
        'ref_email' => $ref_emails,
        'ref_phone' => $ref_phones
    ];
}


$stmt = $pdo->prepare("SELECT document_type, file_path FROM documents WHERE application_id = ?");
$stmt->execute([$app_id]);
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$doc_map = [
    'passport'         => 'passport_file', 
    'passport_profile' => 'passport_profile_file',
    'olevel_1'         => 'olevel_file',
    'olevel_2'         => 'olevel_file_2',
    'degree'           => 'degree_file',
    'transcript'       => 'transcript_file',
    'nysc'             => 'nysc_file',
    'proposal'         => 'proposal_file'
];

foreach ($docs as $doc) {
    $db_type = $doc['document_type'];
    if (isset($doc_map[$db_type])) {
        $form_key = $doc_map[$db_type];
        $_SESSION['form_data']['step_8'][$form_key] = $doc['file_path'];
        
        if ($db_type === 'passport' || $db_type === 'passport_profile') {
            $_SESSION['form_data']['step_1']['passport_file'] = $doc['file_path'];
        }
    }
}
?>
