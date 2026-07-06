<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/_applicant.php';

$user = require_login(['applicant']);
[$applicant, $payment, $application] = applicant_context((int) $user['id']);
if (!payment_confirmed($payment) && !setting_bool('allow_form_without_payment')) {
    flash('info', 'Payment must be confirmed before completing the screening form.');
    redirect('applicant/payment.php');
}
if (application_locked($application)) {
    flash('info', 'Your application has been submitted and is locked.');
    redirect('applicant/review.php');
}

$steps = ['bio' => 'Bio-data', 'olevel' => 'O Level', 'course' => 'Course', 'uploads' => 'Uploads'];
$step = $_GET['step'] ?? 'bio';
if (!array_key_exists($step, $steps)) {
    $step = 'bio';
}

$subjects = [
    'English Language', 'General Mathematics', 'Further Mathematics', 'Civic Education',
    'Biology', 'Chemistry', 'Physics', 'Agricultural Science', 'Animal Husbandry', 'Fisheries', 'Health Education', 'Physical Education',
    'Economics', 'Geography', 'Government', 'History', 'Literature in English', 'Christian Religious Studies', 'Islamic Studies',
    'Commerce', 'Financial Accounting', 'Book Keeping', 'Office Practice', 'Insurance', 'Marketing', 'Store Management',
    'Computer Studies', 'Data Processing', 'Information and Communication Technology',
    'Technical Drawing', 'Building Construction', 'Woodwork', 'Metalwork', 'Auto Mechanics', 'Basic Electricity', 'Basic Electronics',
    'Food and Nutrition', 'Home Management', 'Clothing and Textiles', 'Fine Art', 'Music', 'Visual Art',
    'Yoruba', 'Igbo', 'Hausa', 'Arabic', 'French',
    'Salesmanship', 'Tourism', 'Catering Craft Practice', 'Garment Making', 'Dyeing and Bleaching', 'Photography', 'Cosmetology'
];
$grades = ['A1', 'B2', 'B3', 'C4', 'C5', 'C6', 'D7', 'E8', 'F9'];
$nigerianStates = ['Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno', 'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT', 'Gombe', 'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba', 'Yobe', 'Zamfara'];
$lgaOptions = [
    'Benue' => ['Ado', 'Agatu', 'Apa', 'Buruku', 'Gboko', 'Guma', 'Gwer East', 'Gwer West', 'Katsina-Ala', 'Konshisha', 'Kwande', 'Logo', 'Makurdi', 'Obi', 'Ogbadibo', 'Ohimini', 'Oju', 'Okpokwu', 'Otukpo', 'Tarka', 'Ukum', 'Ushongo', 'Vandeikya'],
    'Lagos' => ['Agege', 'Ajeromi-Ifelodun', 'Alimosho', 'Amuwo-Odofin', 'Apapa', 'Badagry', 'Epe', 'Eti-Osa', 'Ibeju-Lekki', 'Ifako-Ijaiye', 'Ikeja', 'Ikorodu', 'Kosofe', 'Lagos Island', 'Lagos Mainland', 'Mushin', 'Ojo', 'Oshodi-Isolo', 'Shomolu', 'Surulere'],
    'FCT' => ['Abaji', 'Bwari', 'Gwagwalada', 'Kuje', 'Kwali', 'Municipal Area Council'],
    'Nasarawa' => ['Akwanga', 'Awe', 'Doma', 'Karu', 'Keana', 'Keffi', 'Kokona', 'Lafia', 'Nasarawa', 'Nasarawa Egon', 'Obi', 'Toto', 'Wamba'],
];
$programmes = db()->query('SELECT * FROM programmes WHERE is_active = 1 ORDER BY name')->fetchAll();

$profileStmt = db()->prepare('SELECT * FROM applicant_profiles WHERE applicant_id = ? LIMIT 1');
$profileStmt->execute([$applicant['id']]);
$profile = $profileStmt->fetch() ?: [];

$olevelStmt = db()->prepare('SELECT * FROM olevel_results WHERE applicant_id = ? ORDER BY sitting_no');
$olevelStmt->execute([$applicant['id']]);
$olevelRows = $olevelStmt->fetchAll();
$savedOlevel = [];
foreach ($olevelRows as $row) {
    $savedOlevel[(int) $row['sitting_no']] = $row;
    $subStmt = db()->prepare('SELECT * FROM olevel_subjects WHERE olevel_result_id = ? ORDER BY id');
    $subStmt->execute([$row['id']]);
    $savedOlevel[(int) $row['sitting_no']]['subjects'] = $subStmt->fetchAll();
}

$docStmt = db()->prepare('SELECT * FROM uploaded_documents WHERE applicant_id = ?');
$docStmt->execute([$applicant['id']]);
$docs = [];
foreach ($docStmt->fetchAll() as $doc) {
    $docs[$doc['document_type']] = $doc;
}

function save_document_record(int $applicantId, string $type, ?string $path): void
{
    if (!$path) {
        return;
    }
    $full = __DIR__ . '/../' . $path;
    db()->prepare('INSERT INTO uploaded_documents (applicant_id, document_type, original_name, stored_path, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE original_name=VALUES(original_name), stored_path=VALUES(stored_path), mime_type=VALUES(mime_type), file_size=VALUES(file_size), uploaded_at=NOW()')
        ->execute([$applicantId, $type, basename($path), $path, mime_content_type($full) ?: 'application/octet-stream', filesize($full) ?: 0]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postedStep = $_POST['step'] ?? $step;
    try {
        if ($postedStep === 'bio') {
            $nationality = $_POST['nationality'] ?? 'Nigeria';
            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE applicants SET date_of_birth = ?, contact_address = ?, gender = COALESCE(gender, ?) WHERE id = ?')->execute([
                $_POST['date_of_birth'] ?? null,
                trim($_POST['contact_address'] ?? ''),
                $applicant['jamb_gender'] ?? null,
                $applicant['id'],
            ]);
            $pdo->prepare('INSERT INTO applicant_profiles (applicant_id, date_of_birth, marital_status, religion, nationality, state_origin, lga, home_address, contact_address, guardian_name, guardian_phone, emergency_contact) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE date_of_birth=VALUES(date_of_birth), marital_status=VALUES(marital_status), religion=VALUES(religion), nationality=VALUES(nationality), state_origin=VALUES(state_origin), lga=VALUES(lga), home_address=VALUES(home_address), contact_address=VALUES(contact_address), guardian_name=VALUES(guardian_name), guardian_phone=VALUES(guardian_phone), emergency_contact=VALUES(emergency_contact)')
                ->execute([$applicant['id'], $_POST['date_of_birth'] ?? null, $_POST['marital_status'] ?? '', $_POST['religion'] ?? '', $nationality, $_POST['state_origin'] ?? '', $_POST['lga'] ?? '', $_POST['home_address'] ?? '', $_POST['contact_address'] ?? '', $_POST['guardian_name'] ?? '', $_POST['guardian_phone'] ?? '', $_POST['emergency_contact'] ?? '']);
            $pdo->commit();
            flash('success', 'Bio-data saved. Continue with O Level details.');
            redirect('applicant/form.php?step=olevel');
        }

        if ($postedStep === 'olevel') {
            $postedSubjects = [];
            foreach (($_POST['subject'] ?? []) as $sittingSubjects) {
                foreach ((array) $sittingSubjects as $value) {
                    if (trim((string) $value) !== '') {
                        $postedSubjects[] = trim((string) $value);
                    }
                }
            }
            if (count($postedSubjects) !== count(array_unique($postedSubjects))) {
                throw new RuntimeException('Duplicate O Level subjects are not allowed.');
            }
            foreach (['English Language', 'Mathematics'] as $required) {
                if (!in_array($required, $postedSubjects, true)) {
                    flash('info', 'Warning: your O Level entries do not include ' . $required . '. This may affect screening.');
                }
            }

            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare('DELETE os FROM olevel_subjects os JOIN olevel_results orr ON orr.id = os.olevel_result_id WHERE orr.applicant_id = ?')->execute([$applicant['id']]);
            $pdo->prepare('DELETE FROM olevel_results WHERE applicant_id = ?')->execute([$applicant['id']]);
            $allSubjects = [];
            $sittings = max(1, min(2, (int) ($_POST['number_of_sittings'] ?? 1)));
            for ($sitting = 1; $sitting <= $sittings; $sitting++) {
                $pdo->prepare('INSERT INTO olevel_results (applicant_id, sitting_no, exam_type, exam_year, exam_number) VALUES (?, ?, ?, ?, ?)')->execute([$applicant['id'], $sitting, $_POST['exam_type'][$sitting] ?? '', $_POST['exam_year'][$sitting] ?? '', $_POST['exam_number'][$sitting] ?? '']);
                $resultId = (int) $pdo->lastInsertId();
                for ($i = 0; $i < 9; $i++) {
                    $subject = trim($_POST['subject'][$sitting][$i] ?? '');
                    $grade = trim($_POST['grade'][$sitting][$i] ?? '');
                    if ($subject !== '' && $grade !== '') {
                        $allSubjects[] = ['sitting' => $sitting, 'subject' => $subject, 'grade' => $grade];
                        $pdo->prepare('INSERT INTO olevel_subjects (olevel_result_id, subject, grade) VALUES (?, ?, ?)')->execute([$resultId, $subject, $grade]);
                    }
                }
            }
            $pdo->prepare('INSERT INTO screening_applications (applicant_id, application_number, olevel_type, olevel_exam_no, olevel_year, subjects_json, status) VALUES (?, ?, ?, ?, ?, ?, "draft") ON DUPLICATE KEY UPDATE olevel_type=VALUES(olevel_type), olevel_exam_no=VALUES(olevel_exam_no), olevel_year=VALUES(olevel_year), subjects_json=VALUES(subjects_json)')
                ->execute([$applicant['id'], $applicant['application_number'], $_POST['exam_type'][1] ?? '', $_POST['exam_number'][1] ?? '', $_POST['exam_year'][1] ?? '', json_encode($allSubjects)]);
            $pdo->commit();
            flash('success', 'O Level details saved. Continue with course information.');
            redirect('applicant/form.php?step=course');
        }

        if ($postedStep === 'course') {
            $choiceCourse = setting_bool('allow_change_of_course') ? ($_POST['choice_course'] ?? candidate_course($applicant)) : ($applicant['course_name'] ?: $applicant['course_applied']);
            db()->prepare('INSERT INTO screening_applications (applicant_id, application_number, choice_course, alternative_course, status) VALUES (?, ?, ?, ?, "draft") ON DUPLICATE KEY UPDATE choice_course=VALUES(choice_course), alternative_course=VALUES(alternative_course)')
                ->execute([$applicant['id'], $applicant['application_number'], $choiceCourse, $_POST['alternative_course'] ?? '']);
            flash('success', 'Course information saved. Continue with uploads.');
            redirect('applicant/form.php?step=uploads');
        }

        if ($postedStep === 'uploads') {
            $passport = upload_file('passport', 'passport', ['jpg', 'jpeg', 'png']);
            $olevelPath = upload_file('olevel_result', 'olevel', ['jpg', 'jpeg', 'png', 'pdf']);
            $jambSlip = upload_file('jamb_slip', 'jamb', ['jpg', 'jpeg', 'png', 'pdf']);
            $birth = upload_file('birth_certificate', 'birth', ['jpg', 'jpeg', 'png', 'pdf']);
            $stateCert = upload_file('state_certificate', 'state', ['jpg', 'jpeg', 'png', 'pdf']);

            db()->prepare('INSERT INTO screening_applications (applicant_id, application_number, passport_path, olevel_result_path, birth_certificate_path, status) VALUES (?, ?, ?, ?, ?, "draft") ON DUPLICATE KEY UPDATE passport_path=COALESCE(VALUES(passport_path), passport_path), olevel_result_path=COALESCE(VALUES(olevel_result_path), olevel_result_path), birth_certificate_path=COALESCE(VALUES(birth_certificate_path), birth_certificate_path)')
                ->execute([$applicant['id'], $applicant['application_number'], $passport, $olevelPath, $birth]);

            foreach (['passport' => $passport, 'olevel_result' => $olevelPath, 'jamb_slip' => $jambSlip, 'birth_certificate' => $birth, 'state_certificate' => $stateCert] as $type => $path) {
                save_document_record((int) $applicant['id'], $type, $path);
            }
            flash('success', 'Uploads saved. Please review before final submission.');
            redirect('applicant/review.php');
        }
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash('error', $e->getMessage());
    }
}

render_header('Screening Form');
?>
<?php render_workspace_start('applicant', $applicant, 'Screening Form'); ?>
<div class="portal-card">
    <h1>Screening Form</h1>
    <p class="text-muted">Complete one section at a time. Each section is saved before moving forward.</p>
    <div class="form-flow-tabs">
        <?php foreach ($steps as $key => $label): ?>
            <a class="<?= $step === $key ? 'active' : '' ?>" href="<?= e(url('applicant/form.php?step=' . $key)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>

    <form method="post" enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="<?= e($step) ?>">

        <?php if ($step === 'bio'): ?>
            <div class="col-12"><h2 class="form-section-title">A. Bio-data</h2></div>
            <div class="col-md-4"><label class="form-label">Date of Birth</label><input type="date" name="date_of_birth" class="form-control" value="<?= e($profile['date_of_birth'] ?? '') ?>" required></div>
            <div class="col-md-4"><label class="form-label">Marital Status</label><select name="marital_status" class="form-select"><option <?= ($profile['marital_status'] ?? '') === 'Single' ? 'selected' : '' ?>>Single</option><option <?= ($profile['marital_status'] ?? '') === 'Married' ? 'selected' : '' ?>>Married</option></select></div>
            <div class="col-md-4"><label class="form-label">Religion optional</label><input name="religion" class="form-control" value="<?= e($profile['religion'] ?? '') ?>"></div>
            <div class="col-md-4">
                <label class="form-label">Nationality</label>
                <select name="nationality" id="nationality" class="form-select" required>
                    <option value="Nigeria" <?= ($profile['nationality'] ?? 'Nigeria') === 'Nigeria' ? 'selected' : '' ?>>Nigeria</option>
                    <option value="Non Nigeria" <?= ($profile['nationality'] ?? '') === 'Non Nigeria' ? 'selected' : '' ?>>Non Nigeria</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">State</label>
                <select name="state_origin" id="state_origin" class="form-select" required data-current="<?= e($profile['state_origin'] ?? '') ?>">
                    <option value="">Select state</option>
                    <?php foreach ($nigerianStates as $stateName): ?>
                        <option value="<?= e($stateName) ?>" <?= ($profile['state_origin'] ?? '') === $stateName ? 'selected' : '' ?>><?= e($stateName) ?></option>
                    <?php endforeach; ?>
                    <option value="Outside Nigeria" <?= ($profile['state_origin'] ?? '') === 'Outside Nigeria' ? 'selected' : '' ?>>Outside Nigeria</option>
                </select>
            </div>
            <div class="col-md-4"><label class="form-label">LGA</label><select name="lga" id="lga" class="form-select" required data-current="<?= e($profile['lga'] ?? '') ?>"><option value="">Select LGA</option></select></div>
            <div class="col-md-6"><label class="form-label">Home Address</label><textarea name="home_address" class="form-control" required><?= e($profile['home_address'] ?? '') ?></textarea></div>
            <div class="col-md-6"><label class="form-label">Contact Address</label><textarea name="contact_address" class="form-control" required><?= e($profile['contact_address'] ?? '') ?></textarea></div>
            <div class="col-md-4"><label class="form-label">Parent/Guardian Name</label><input name="guardian_name" class="form-control" value="<?= e($profile['guardian_name'] ?? '') ?>" required></div>
            <div class="col-md-4"><label class="form-label">Parent/Guardian Phone</label><input name="guardian_phone" class="form-control" value="<?= e($profile['guardian_phone'] ?? '') ?>" required></div>
            <div class="col-md-4"><label class="form-label">Emergency Contact</label><input name="emergency_contact" class="form-control" value="<?= e($profile['emergency_contact'] ?? '') ?>" required></div>
        <?php endif; ?>

        <?php if ($step === 'olevel'): ?>
            <div class="col-12"><h2 class="form-section-title">B. Academic Details</h2></div>
            <div class="col-md-4"><label class="form-label">Number of Sittings</label><select name="number_of_sittings" id="number_of_sittings" class="form-select"><option value="1">One Sitting</option><option value="2" <?= count($savedOlevel) > 1 ? 'selected' : '' ?>>Two Sittings</option></select></div>
            <?php for ($sitting = 1; $sitting <= 2; $sitting++): ?>
                <?php $saved = $savedOlevel[$sitting] ?? ['subjects' => []]; ?>
                <div class="col-12 sitting-block" data-sitting="<?= $sitting ?>">
                    <div class="sitting-panel">
                        <h3><?= $sitting === 1 ? 'First Sitting' : 'Second Sitting' ?></h3>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">Exam Type</label><select name="exam_type[<?= $sitting ?>]" class="form-select"><option <?= ($saved['exam_type'] ?? '') === 'WAEC' ? 'selected' : '' ?>>WAEC</option><option <?= ($saved['exam_type'] ?? '') === 'NECO' ? 'selected' : '' ?>>NECO</option><option <?= ($saved['exam_type'] ?? '') === 'NABTEB' ? 'selected' : '' ?>>NABTEB</option></select></div>
                            <div class="col-md-4"><label class="form-label">Exam Year</label><input name="exam_year[<?= $sitting ?>]" class="form-control" value="<?= e($saved['exam_year'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="form-label">Exam Number</label><input name="exam_number[<?= $sitting ?>]" class="form-control" value="<?= e($saved['exam_number'] ?? '') ?>"></div>
                            <?php for ($i = 0; $i < 9; $i++): ?>
                                <?php $savedSubject = $saved['subjects'][$i] ?? []; ?>
                                <div class="col-md-8">
                                    <label class="form-label">Subject <?= $i + 1 ?></label>
                                    <select name="subject[<?= $sitting ?>][]" class="form-select">
                                        <option value="">Select subject</option>
                                        <?php foreach ($subjects as $subject): ?><option <?= ($savedSubject['subject'] ?? '') === $subject ? 'selected' : '' ?>><?= e($subject) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Grade <?= $i + 1 ?></label>
                                    <select name="grade[<?= $sitting ?>][]" class="form-select">
                                        <option value="">Grade</option>
                                        <?php foreach ($grades as $grade): ?><option <?= ($savedSubject['grade'] ?? '') === $grade ? 'selected' : '' ?>><?= e($grade) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>
        <?php endif; ?>

        <?php if ($step === 'course'): ?>
            <div class="col-12"><h2 class="form-section-title">C. Course Information</h2></div>
            <div class="col-md-6"><label class="form-label">JAMB Course</label><input class="form-control" value="<?= e($applicant['course_name'] ?: $applicant['course_applied']) ?>" readonly></div>
            <div class="col-md-6">
                <label class="form-label">Course Change</label>
                <?php if (setting_bool('allow_change_of_course')): ?>
                    <select name="choice_course" class="form-select"><?php foreach ($programmes as $programme): ?><option <?= ($application['choice_course'] ?? '') === $programme['name'] ? 'selected' : '' ?>><?= e($programme['name']) ?></option><?php endforeach; ?></select>
                <?php else: ?>
                    <input name="choice_course" class="form-control" value="<?= e($applicant['course_name'] ?: $applicant['course_applied']) ?>" readonly>
                <?php endif; ?>
            </div>
            <div class="col-md-6"><label class="form-label">Alternative Course</label><input name="alternative_course" class="form-control" value="<?= e($application['alternative_course'] ?? '') ?>"></div>
        <?php endif; ?>

        <?php if ($step === 'uploads'): ?>
            <div class="col-12"><h2 class="form-section-title">D. Uploads</h2></div>
            <div class="col-md-4"><label class="form-label">Passport Photograph</label><input type="file" name="passport" class="form-control" accept=".jpg,.jpeg,.png" <?= isset($docs['passport']) ? '' : 'required' ?>><small class="text-muted"><?= isset($docs['passport']) ? 'Uploaded' : 'Required' ?></small></div>
            <div class="col-md-4"><label class="form-label">O Level Result(s)</label><input type="file" name="olevel_result" class="form-control" accept=".jpg,.jpeg,.png,.pdf" <?= isset($docs['olevel_result']) ? '' : 'required' ?>><small class="text-muted"><?= isset($docs['olevel_result']) ? 'Uploaded' : 'Required' ?></small></div>
            <div class="col-md-4"><label class="form-label">JAMB Result Slip</label><input type="file" name="jamb_slip" class="form-control" accept=".jpg,.jpeg,.png,.pdf" <?= isset($docs['jamb_slip']) ? '' : 'required' ?>><small class="text-muted"><?= isset($docs['jamb_slip']) ? 'Uploaded' : 'Required' ?></small></div>
            <div class="col-md-6"><label class="form-label">Birth Certificate/Declaration optional</label><input type="file" name="birth_certificate" class="form-control" accept=".jpg,.jpeg,.png,.pdf"></div>
            <div class="col-md-6"><label class="form-label">State of Origin Certificate optional</label><input type="file" name="state_certificate" class="form-control" accept=".jpg,.jpeg,.png,.pdf"></div>
        <?php endif; ?>

        <div class="col-12 sticky-actions">
            <button class="btn btn-portal-green btn-lg w-100"><?= $step === 'uploads' ? 'Save and Review' : 'Save and Continue' ?></button>
        </div>
    </form>
</div>
<?php render_workspace_end(); ?>

<?php if ($step === 'bio'): ?>
<script>
const lgaOptions = <?= json_encode($lgaOptions) ?>;
const nationality = document.getElementById('nationality');
const stateSelect = document.getElementById('state_origin');
const lgaSelect = document.getElementById('lga');

function fillLgas() {
    const current = lgaSelect.dataset.current || '';
    const isNonNigeria = nationality.value === 'Non Nigeria';
    if (isNonNigeria) {
        stateSelect.value = 'Outside Nigeria';
        lgaSelect.innerHTML = '<option value="Not Applicable">Not Applicable</option>';
        return;
    }
    const lgas = lgaOptions[stateSelect.value] || ['Other'];
    lgaSelect.innerHTML = '<option value="">Select LGA</option>' + lgas.map((lga) => `<option value="${lga}">${lga}</option>`).join('');
    if (current) {
        lgaSelect.value = current;
    }
}

nationality.addEventListener('change', fillLgas);
stateSelect.addEventListener('change', () => {
    lgaSelect.dataset.current = '';
    fillLgas();
});
fillLgas();
</script>
<?php endif; ?>
<?php if ($step === 'olevel'): ?>
<script>
const sittingSelect = document.getElementById('number_of_sittings');
const sittingBlocks = document.querySelectorAll('.sitting-block');

function updateSittings() {
    const selected = parseInt(sittingSelect.value, 10);
    sittingBlocks.forEach((block) => {
        const sitting = parseInt(block.dataset.sitting, 10);
        const visible = sitting <= selected;
        block.hidden = !visible;
        block.querySelectorAll('select, input').forEach((field) => {
            field.disabled = !visible;
        });
    });
}

sittingSelect.addEventListener('change', updateSittings);
updateSittings();
</script>
<?php endif; ?>
<?php render_footer(); ?>
