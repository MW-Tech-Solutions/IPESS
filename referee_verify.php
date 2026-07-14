<?php
require_once 'admin/includes/db.php';
require_once __DIR__ . '/includes/referee_service.php';
require_once __DIR__ . '/includes/status_engine.php';
require_once __DIR__ . '/ADMIN/includes/mailer.php';
require_once __DIR__ . '/config/urls.php';

$message = "";
$messageType = "";
$showForm = false;
$status = "";

// 1. Validate the Request
$refId = filter_input(INPUT_GET, 'rid', FILTER_VALIDATE_INT);
$authHash = $_GET['auth'] ?? '';
$token = $_GET['token'] ?? '';

if (!$token && (!$refId || !$authHash)) {
    die("Invalid access link. Please check your email and try again.");
}

try {
    // JOIN to applicant information and programme details
    if ($token) {
        $stmt = $pdo->prepare("
            SELECT r.*, 
                   req.application_id, 
                   pd.first_name, 
                   pd.surname, 
                   pd.other_name,
                   pd.phone AS applicant_phone,
                   a.application_number,
                   acc.email AS app_email, 
                   d.file_path AS photo_path, 
                   req.token, 
                   req.expires_at, 
                   acc.user_id AS applicant_user_id, 
                   ru.verified_status,
                   c.course_title AS position_applied_for,
                   dept.dept_name,
                   f.faculty_name
            FROM referee_requests req
            JOIN referees r ON r.referee_id = req.referee_id
            JOIN applications a ON r.application_id = a.application_id
            JOIN personal_details pd ON a.application_id = pd.application_id
            JOIN users acc ON a.user_id = acc.user_id
            LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
            LEFT JOIN courses c ON pc.course = c.course_id
            LEFT JOIN departments dept ON pc.department = dept.dept_id
            LEFT JOIN faculties f ON pc.faculty = f.faculty_id
            LEFT JOIN documents d ON a.application_id = d.application_id AND d.document_type = 'passport'
            LEFT JOIN referee_uploads ru ON r.referee_id = ru.referee_id
            WHERE req.token = ? AND (req.expires_at IS NULL OR req.expires_at >= NOW())
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            $refId = (int) $data['referee_id'];
        }
    } else {
        $stmt = $pdo->prepare("
            SELECT r.*, 
                   r.application_id,
                   pd.first_name, 
                   pd.surname, 
                   pd.other_name,
                   pd.phone AS applicant_phone,
                   a.application_number,
                   acc.email AS app_email, 
                   d.file_path AS photo_path, 
                   acc.user_id AS applicant_user_id, 
                   ru.verified_status,
                   c.course_title AS position_applied_for,
                   dept.dept_name,
                   f.faculty_name
            FROM referees r
            JOIN applications a ON r.application_id = a.application_id
            JOIN personal_details pd ON a.application_id = pd.application_id
            JOIN users acc ON a.user_id = acc.user_id
            LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
            LEFT JOIN courses c ON pc.course = c.course_id
            LEFT JOIN departments dept ON pc.department = dept.dept_id
            LEFT JOIN faculties f ON pc.faculty = f.faculty_id
            LEFT JOIN documents d ON a.application_id = d.application_id AND d.document_type = 'passport'
            LEFT JOIN referee_uploads ru ON r.referee_id = ru.referee_id
            WHERE r.referee_id = ?
        ");
        $stmt->execute([$refId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$data) {
        die("Referee record not found.");
    }

    if (!$token) {
        // Legacy security check
        $expectedHash = md5($data['email'] . "JOSTUM_SALT_2024");
        if ($authHash !== $expectedHash) {
            die("Security authentication failed. Unauthorized access.");
        }
    }

    require_once __DIR__ . '/classes/ApplicationProgressManager.php';
    $progManager = new ApplicationProgressManager($pdo);
    $appId = (int) $data['application_id'];

    $status = $data['verified_status'] ?? '';
    if ($status === 'Verified') {
        $message = "You have already completed this verification process. Thank you!";
        $messageType = "success";
    } elseif (!$progManager->isStageCompleted($appId, ApplicationProgressManager::STAGE_DOC_VERIFY)) {
        $message = "This application is not yet ready for referee verification. Documents verification must be completed first by the admissions office.";
        $messageType = "warning";
        $showForm = false;
    } else {
        $showForm = true;
    }

    // 3. Handle Form Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_request'])) {
        $reason = trim($_POST['reject_reason'] ?? '') ?: 'Referee declined the request.';
        $pdo->prepare("UPDATE referee_uploads SET verified_status = 'Rejected', rejection_reason = ?, submitted_at = NOW() WHERE referee_id = ?")
            ->execute([$reason, $refId]);
        if ($token) {
            $pdo->prepare("UPDATE referee_requests SET status = 'Rejected' WHERE token = ?")->execute([$token]);
        }

        if (!empty($data['applicant_user_id'])) {
            notify_user($pdo, (int) $data['applicant_user_id'], 'Referee Declined', 'A referee has declined the verification request.');
        }
        if (!empty($data['app_email'])) {
            portal_send_mail(
                $data['app_email'],
                $data['first_name'] . ' ' . $data['surname'],
                'Referee Declined Request',
                '<p>Your referee has declined the verification request. Please update your referee details and resubmit.</p>',
                'Referee declined.'
            );
        }

        $message = "Referee request has been declined.";
        $messageType = "danger";
        $showForm = false;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_verification'])) {
        $maxFileSize = 250 * 1024; 
        $file = $_FILES['id_card'];
        $passportFile = $_FILES['passport_photo'] ?? null;
        $workEmail = trim($_POST['work_email'] ?? '');

        // Form fields
        $refereeName = trim($_POST['referee_name'] ?? '');
        $refereeTitle = trim($_POST['referee_title'] ?? '');
        $refereeOrg = trim($_POST['referee_org'] ?? '');
        $refereeDept = trim($_POST['referee_dept'] ?? '');
        $refereePosition = trim($_POST['referee_position'] ?? '');
        $refereeAddress = trim($_POST['referee_address'] ?? '');
        $refereePhone = trim($_POST['referee_phone'] ?? '');
        $relationship = trim($_POST['relationship'] ?? '');
        $yearsKnown = filter_input(INPUT_POST, 'years_known', FILTER_VALIDATE_INT) ?: 0;
        
        $assessCharacter = $_POST['assess_character'] ?? null;
        $assessCompetence = $_POST['assess_competence'] ?? null;
        $assessLeadership = $_POST['assess_leadership'] ?? null;
        $assessCommunication = $_POST['assess_communication'] ?? null;
        $assessTeamwork = $_POST['assess_teamwork'] ?? null;
        $assessReliability = $_POST['assess_reliability'] ?? null;
        $assessInitiative = $_POST['assess_initiative'] ?? null;
        $assessStability = $_POST['assess_stability'] ?? null;
        
        $majorStrengths = trim($_POST['major_strengths'] ?? '');
        $weaknesses = trim($_POST['weaknesses'] ?? '');
        $recommendation = $_POST['recommendation'] ?? null;
        $additionalComments = trim($_POST['additional_comments'] ?? '');
        
        $declarationAccepted = isset($_POST['declaration_accepted']) ? 1 : 0;
        $signature = trim($_POST['signature'] ?? '');
        $declarationDate = date('Y-m-d');

        if ($file['size'] > $maxFileSize) {
            $message = "Error: File is too large. Maximum size allowed is 250KB.";
            $messageType = "danger";
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $message = "Error uploading file. Please try again.";
            $messageType = "danger";
        } else {
            $uploadDir = 'uploads/referee_ids/';
            $passportDir = 'uploads/referee_passports/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            if (!is_dir($passportDir)) mkdir($passportDir, 0777, true);

            $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $file['name']);
            $targetPath = $uploadDir . $fileName;
            $fileExtension = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];

            $passportPath = null;
            if (in_array($fileExtension, $allowedTypes)) {
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    if ($passportFile && $passportFile['error'] === UPLOAD_ERR_OK && $passportFile['size'] <= $maxFileSize) {
                        $passportName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $passportFile['name']);
                        $passportTarget = $passportDir . $passportName;
                        if (move_uploaded_file($passportFile['tmp_name'], $passportTarget)) {
                            $passportPath = $passportTarget;
                        }
                    }

                    $details = [
                        'work_email' => $workEmail,
                        'passport_path' => $passportPath,
                        'work_id_path' => $targetPath,
                        'referee_name' => $refereeName,
                        'referee_title' => $refereeTitle,
                        'referee_organization' => $refereeOrg,
                        'referee_department' => $refereeDept,
                        'referee_position' => $refereePosition,
                        'referee_address' => $refereeAddress,
                        'referee_phone' => $refereePhone,
                        'relationship' => $relationship,
                        'years_known' => $yearsKnown,
                        'assessment_character_integrity' => $assessCharacter,
                        'assessment_professional_competence' => $assessCompetence,
                        'assessment_leadership_ability' => $assessLeadership,
                        'assessment_communication_skills' => $assessCommunication,
                        'assessment_teamwork' => $assessTeamwork,
                        'assessment_reliability' => $assessReliability,
                        'assessment_initiative' => $assessInitiative,
                        'assessment_emotional_stability' => $assessStability,
                        'major_strengths' => $majorStrengths,
                        'weaknesses' => $weaknesses,
                        'recommendation' => $recommendation,
                        'additional_comments' => $additionalComments,
                        'declaration_accepted' => $declarationAccepted,
                        'signature' => $signature,
                        'declaration_date' => $declarationDate
                    ];

                    record_referee_submission($pdo, $refId, (int) $data['application_id'], $details);
                    if ($token) {
                        $pdo->prepare("UPDATE referee_requests SET status = 'Submitted' WHERE token = ?")->execute([$token]);
                    }

                    $message = "Thank you! Your verification and evaluation report have been submitted successfully.";
                    $messageType = "success";
                    $showForm = false;

                    if (!empty($data['applicant_user_id'])) {
                        notify_user($pdo, (int) $data['applicant_user_id'], 'Referee Submitted', 'Your referee has submitted verification details.');
                    }
                    if (!empty($data['app_email'])) {
                        portal_send_mail(
                            $data['app_email'],
                            $data['first_name'] . ' ' . $data['surname'],
                            'Referee Submission Received',
                            '<p>Your referee has submitted verification details. You will be notified once it is reviewed.</p>',
                            'Referee submission received.'
                        );
                    }
                } else {
                    $message = "Failed to save file to server.";
                    $messageType = "danger";
                }
            } else {
                $message = "Invalid file type. Only JPG, PNG, and PDF are allowed.";
                $messageType = "danger";
            }
        }
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referee Verification & Assessment | IPESS PG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="ADMIN/assets/css/style.css">
    <style>
        :root { --primary-color: #6EB533; --bg-color: #f4f7f6; }
        body { background-color: var(--bg-color); font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .verify-container { max-width: 850px; margin: 60px auto; }
        .card { border: none; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.08); overflow: hidden; }
        .header-box { background: var(--primary-color); color: white; padding: 40px 20px 60px; text-align: center; }
        
        .applicant-profile { position: relative; margin-top: -50px; text-align: center; margin-bottom: 20px; }
        .photo-circle { 
            width: 110px; height: 110px; 
            border-radius: 50%; 
            border: 5px solid white; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            object-fit: cover;
            background: #fff;
        }
        
        .form-label { font-weight: 600; font-size: 0.9rem; color: #444; }
        .btn-primary { padding: 12px; font-weight: 600; border-radius: 10px; background-color: var(--primary-color); border-color: var(--primary-color); }
        .btn-primary:hover { background-color: #5ea12a; border-color: #5ea12a; }
        .table th { font-weight: 600; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; }
        .criteria-row:hover { background-color: #f8f9fa; }
    </style>
</head>
<body>

<div class="container verify-container">
    <div class="card">
        <div class="header-box">
            <i class="fas fa-shield-halved fa-3x mb-3"></i>
            <h2 class="h4 mb-1">IPESS Postgraduate</h2>
            <p class="mb-0 opacity-75">Secure Referee Assessment Portal</p>
        </div>
        
        <div class="card-body p-4 pt-0">
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> text-center py-4 mt-4">
                    <i class="fas fa-<?= $messageType == 'success' ? 'check-circle' : 'exclamation-triangle' ?> fa-2x mb-2"></i>
                    <h4><?= $message ?></h4>
                </div>
            <?php endif; ?>

            <?php if ($showForm): ?>
                <div class="applicant-profile">
                    <?php 
                        $photo = !empty($data['photo_path']) ? $data['photo_path'] : 'assets/img/default-avatar.png';
                    ?>
                    <img src="<?= htmlspecialchars($photo) ?>" alt="Applicant Photo" class="photo-circle">
                </div>

                <div class="text-center mb-4">
                    <p class="text-muted small mb-1">REFEREE REQUEST FOR:</p>
                    <h4 class="fw-bold mb-0"><?= htmlspecialchars($data['first_name'] . ' ' . $data['surname']) ?></h4>
                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($data['app_email']) ?></span>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <!-- Section A: Applicant Information -->
                    <div class="card mb-4 border-1 border-light bg-light rounded-3">
                        <div class="card-body p-3">
                            <h6 class="text-uppercase text-primary fw-bold mb-3"><i class="fas fa-user-graduate me-2"></i>Section A: Applicant Information</h6>
                            <div class="row g-3 small">
                                <div class="col-md-6">
                                    <span class="text-muted d-block">Applicant's Full Name:</span>
                                    <strong class="text-dark"><?= htmlspecialchars($data['first_name'] . ' ' . ($data['other_name'] ? $data['other_name'] . ' ' : '') . $data['surname']) ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-muted d-block">Application Number:</span>
                                    <strong class="text-dark"><?= htmlspecialchars($data['application_number'] ?: '-') ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-muted d-block">Position / Programme Applied For:</span>
                                    <strong class="text-dark"><?= htmlspecialchars($data['position_applied_for'] ?: '-') ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-muted d-block">Department / Faculty:</span>
                                    <strong class="text-dark"><?= htmlspecialchars(($data['dept_name'] ? $data['dept_name'] : '') . ($data['faculty_name'] ? ' / ' . $data['faculty_name'] : '-')) ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-muted d-block">Phone Number:</span>
                                    <strong class="text-dark"><?= htmlspecialchars($data['applicant_phone'] ?: '-') ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-muted d-block">Email Address:</span>
                                    <strong class="text-dark"><?= htmlspecialchars($data['app_email'] ?: '-') ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section B: Referee Information -->
                    <div class="mb-4">
                        <h5 class="border-bottom pb-2 mb-3 text-primary fw-bold"><i class="fas fa-user-tie me-2"></i>Section B: Referee Information</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name of Referee</label>
                                <input type="text" name="referee_name" class="form-control" value="<?= htmlspecialchars($data['full_name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Title</label>
                                <select name="referee_title" class="form-select" required>
                                    <option value="">Select Title</option>
                                    <?php 
                                    $titles = ['Prof.', 'Dr.', 'Engr.', 'Mr.', 'Mrs.', 'Ms.', 'Rev.', 'Pastor'];
                                    $currentTitle = $data['title'] ?? '';
                                    foreach ($titles as $t) {
                                        $selected = (strcasecmp($currentTitle, $t) === 0 || strpos(strtolower($currentTitle), strtolower($t)) !== false) ? 'selected' : '';
                                        echo "<option value='{$t}' {$selected}>{$t}</option>";
                                    }
                                    ?>
                                    <option value="Other" <?= (!in_array($currentTitle, $titles) && !empty($currentTitle)) ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Organization / Institution</label>
                                <input type="text" name="referee_org" class="form-control" value="<?= htmlspecialchars($data['organization']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Department</label>
                                <input type="text" name="referee_dept" class="form-control" required placeholder="e.g. Computer Science">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Position / Designation</label>
                                <input type="text" name="referee_position" class="form-control" value="<?= htmlspecialchars($data['title']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="referee_phone" class="form-control" value="<?= htmlspecialchars($data['phone']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="referee_email" class="form-control" value="<?= htmlspecialchars($data['email']) ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Work Email (Verification)</label>
                                <input type="email" name="work_email" class="form-control" required placeholder="you@organization.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Relationship with Applicant</label>
                                <input type="text" name="relationship" class="form-control" required placeholder="e.g. Academic Supervisor, Line Manager">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Number of Years Known</label>
                                <input type="number" name="years_known" class="form-control" required min="0" max="80">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Official Address</label>
                                <textarea name="referee_address" class="form-control" rows="2" required placeholder="Enter your official address"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Section C: Assessment -->
                    <div class="mb-4">
                        <h5 class="border-bottom pb-2 mb-2 text-primary fw-bold"><i class="fas fa-chart-line me-2"></i>Section C: Assessment</h5>
                        <p class="text-muted small mb-3">Rate the applicant on a scale of Excellent, Very Good, Good, Fair, Poor.</p>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle text-center small">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-start ps-3" style="width: 40%;">Criteria</th>
                                        <th>Excellent</th>
                                        <th>Very Good</th>
                                        <th>Good</th>
                                        <th>Fair</th>
                                        <th>Poor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $criteria = [
                                        'character_integrity' => 'Character & Integrity',
                                        'professional_competence' => 'Professional Competence',
                                        'leadership_ability' => 'Leadership Ability',
                                        'communication_skills' => 'Communication Skills',
                                        'teamwork' => 'Teamwork',
                                        'reliability' => 'Reliability',
                                        'initiative' => 'Initiative',
                                        'emotional_stability' => 'Emotional Stability'
                                    ];
                                    $ratings = ['Excellent', 'Very Good', 'Good', 'Fair', 'Poor'];
                                    foreach ($criteria as $key => $label):
                                    ?>
                                    <tr class="criteria-row">
                                        <td class="text-start ps-3 fw-semibold text-dark"><?= $label ?></td>
                                        <?php foreach ($ratings as $r): ?>
                                        <td>
                                            <input class="form-check-input" type="radio" name="assess_<?= $key ?>" value="<?= $r ?>" required>
                                        </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Section D: Narrative Comments -->
                    <div class="mb-4">
                        <h5 class="border-bottom pb-2 mb-3 text-primary fw-bold"><i class="fas fa-comment-alt me-2"></i>Section D: Narrative Comments</h5>
                        <div class="mb-3">
                            <label class="form-label">1. What are the applicant's major strengths?</label>
                            <textarea name="major_strengths" class="form-control" rows="3" required placeholder="Describe the applicant's strengths..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">2. What are the applicant's weaknesses?</label>
                            <textarea name="weaknesses" class="form-control" rows="3" required placeholder="Describe the applicant's weaknesses..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label d-block">3. Would you recommend this applicant for the position?</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="recommendation" id="rec1" value="Strongly Recommend" required>
                                <label class="form-check-label" for="rec1">Strongly Recommend</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="recommendation" id="rec2" value="Recommend" required>
                                <label class="form-check-label" for="rec2">Recommend</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="recommendation" id="rec3" value="Recommend with Reservation" required>
                                <label class="form-check-label" for="rec3">Recommend with Reservation</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="recommendation" id="rec4" value="Do Not Recommend" required>
                                <label class="form-check-label" for="rec4">Do Not Recommend</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">4. Additional comments</label>
                            <textarea name="additional_comments" class="form-control" rows="3" placeholder="Provide any other relevant details..."></textarea>
                        </div>
                    </div>

                    <!-- Verification Uploads -->
                    <div class="mb-4">
                        <h5 class="border-bottom pb-2 mb-3 text-primary fw-bold"><i class="fas fa-file-invoice me-2"></i>Verification Documents</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Upload Your Organization ID / Professional ID</label>
                                <input type="file" name="id_card" class="form-control" required accept=".jpg,.jpeg,.png,.pdf">
                                <div class="form-text text-danger">Max size: 250KB (JPG, PNG, or PDF)</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Upload Your Passport Photo (Optional)</label>
                                <input type="file" name="passport_photo" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                                <div class="form-text text-muted">Max size: 250KB (JPG, PNG, or PDF)</div>
                            </div>
                        </div>
                    </div>

                    <!-- Section E: Declaration -->
                    <div class="mb-4 p-3 bg-light rounded-3 border-start border-4 border-success">
                        <h5 class="text-success fw-bold mb-2"><i class="fas fa-check-circle me-2"></i>Section E: Declaration</h5>
                        <p class="small text-muted mb-3">"I certify that the information provided above is true and based on my personal knowledge of the applicant."</p>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="confirmCheck" name="declaration_accepted" required>
                            <label class="form-check-label small" for="confirmCheck">
                                I accept and agree to the declaration statement.
                            </label>
                        </div>
                        <div class="row g-3 small">
                            <div class="col-md-6">
                                <label class="form-label">Referee Name (Digital Signature)</label>
                                <input type="text" name="signature" class="form-control" required placeholder="Type your full name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date</label>
                                <input type="text" class="form-control bg-white" value="<?= date('Y-m-d') ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" name="submit_verification" class="btn btn-primary btn-lg shadow-sm">
                            Submit Verification & Evaluation
                        </button>
                    </div>
                </form>
                <form method="POST" class="mt-3" onsubmit="return confirm('Are you sure you want to reject this referee request?');">
                    <input type="hidden" name="reject_request" value="1">
                    <input type="text" name="reject_reason" class="form-control mb-2" placeholder="Reason (optional)">
                    <button type="submit" class="btn btn-outline-danger w-100">I Do Not Know This Applicant</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="card-footer text-center text-muted small py-3 border-0">
            &copy; <?= date('Y') ?> Joseph Sarwuan Tarka University, Makurdi
        </div>
    </div>
</div>

<script>
// Bootstrap form validation
(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add('was-validated')
      }, false)
    })
})()
</script>
</body>
</html>

