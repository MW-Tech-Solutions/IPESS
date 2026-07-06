<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/_applicant.php';

$user = require_login(['applicant']);
[$applicant, $payment, $application] = applicant_context((int) $user['id']);
if (!$application || ($application['status'] ?? 'draft') === 'draft') {
    redirect('applicant/review.php');
}

$profileStmt = db()->prepare('SELECT * FROM applicant_profiles WHERE applicant_id = ? LIMIT 1');
$profileStmt->execute([$applicant['id']]);
$profile = $profileStmt->fetch() ?: [];

$olevelStmt = db()->prepare('SELECT orr.*, os.subject, os.grade FROM olevel_results orr LEFT JOIN olevel_subjects os ON os.olevel_result_id = orr.id WHERE orr.applicant_id = ? ORDER BY orr.sitting_no, os.id');
$olevelStmt->execute([$applicant['id']]);
$olevelRows = $olevelStmt->fetchAll();

$docStmt = db()->prepare('SELECT * FROM uploaded_documents WHERE applicant_id = ? ORDER BY document_type');
$docStmt->execute([$applicant['id']]);
$documents = $docStmt->fetchAll();
$docsByType = [];
foreach ($documents as $document) {
    $docsByType[$document['document_type']] = $document;
}
$passport = $docsByType['passport'] ?? null;

$passportSrc = '';
if ($passport && is_file(__DIR__ . '/../' . $passport['stored_path'])) {
    $mime = mime_content_type(__DIR__ . '/../' . $passport['stored_path']) ?: 'image/jpeg';
    $passportSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents(__DIR__ . '/../' . $passport['stored_path']));
}
$groupedOlevel = [];
foreach ($olevelRows as $row) {
    $groupedOlevel[(int) $row['sitting_no']]['meta'] = $row;
    if (!empty($row['subject'])) {
        $groupedOlevel[(int) $row['sitting_no']]['subjects'][] = $row;
    }
}

render_header('Acknowledgement Slip');
?>
<?php render_workspace_start('applicant', $applicant, 'Acknowledgement Slip'); ?>
<div class="portal-card slip rich-slip">
    <div class="slip-topbar no-print">
        <button onclick="window.print()" class="btn btn-portal-green"><?= icon('printer') ?> Print / Download PDF</button>
    </div>

    <header class="rich-slip-header">
        <div class="slip-photo-box">
            <?php if ($passportSrc): ?>
                <img src="<?= e($passportSrc) ?>" alt="Applicant passport">
            <?php else: ?>
                <div class="passport-placeholder">Passport</div>
            <?php endif; ?>
            <span>Applicant Passport</span>
        </div>
        <div class="slip-school-title">
            <h1>Joseph Sarwuan Tarka University, Makurdi</h1>
            <h2>POST-UTME Online Screening Portal</h2>
            <p>Acknowledgement Slip</p>
            <strong><?= e($application['acknowledgement_code']) ?></strong>
        </div>
        <div class="slip-logo-box">
            <img src="<?= e(url('images/new_jostum_logo.png')) ?>" alt="JOSTUM logo">
            <span>Official Logo</span>
        </div>
    </header>

    <section class="slip-section">
        <h3>Application Summary</h3>
        <div class="slip-grid">
            <div><span>Application Number</span><strong><?= e($applicant['application_number'] ?: $application['application_number']) ?></strong></div>
            <div><span>Submission Date</span><strong><?= e($application['submitted_at'] ? date('M j, Y g:i A', strtotime($application['submitted_at'])) : '') ?></strong></div>
            <div><span>Application Status</span><strong><?= e(ucwords(str_replace('_', ' ', $application['status']))) ?></strong></div>
            <div><span>Verification Code</span><strong><?= e($application['acknowledgement_code']) ?></strong></div>
        </div>
    </section>

    <section class="slip-section">
        <h3>Applicant and JAMB Details</h3>
        <div class="slip-grid">
            <div><span>Full Name</span><strong><?= e($applicant['surname'] . ' ' . $applicant['first_name'] . ' ' . $applicant['other_names']) ?></strong></div>
            <div><span>JAMB Registration Number</span><strong><?= e($applicant['jamb_reg_no']) ?></strong></div>
            <div><span>Gender</span><strong><?= e($applicant['gender'] ?: $applicant['jamb_gender']) ?></strong></div>
            <div><span>Date of Birth</span><strong><?= e($profile['date_of_birth'] ?? $applicant['date_of_birth'] ?? '') ?></strong></div>
            <div><span>State / LGA</span><strong><?= e(($profile['state_origin'] ?? $applicant['profile_state'] ?? '') . ' / ' . ($profile['lga'] ?? $applicant['profile_lga'] ?? '')) ?></strong></div>
            <div><span>JAMB Score</span><strong><?= e((string) ($applicant['jamb_score'] ?: $applicant['utme_score'])) ?></strong></div>
        </div>
    </section>

    <section class="slip-section">
        <h3>Bio-data and Contact Details</h3>
        <div class="slip-grid">
            <div><span>Nationality</span><strong><?= e($profile['nationality'] ?? '') ?></strong></div>
            <div><span>Marital Status</span><strong><?= e($profile['marital_status'] ?? '') ?></strong></div>
            <div><span>Religion</span><strong><?= e($profile['religion'] ?? '') ?></strong></div>
            <div><span>Email</span><strong><?= e($applicant['email']) ?></strong></div>
            <div><span>Phone</span><strong><?= e($applicant['phone']) ?></strong></div>
            <div><span>Guardian</span><strong><?= e(($profile['guardian_name'] ?? '') . ' - ' . ($profile['guardian_phone'] ?? '')) ?></strong></div>
            <div class="span-2"><span>Home Address</span><strong><?= e($profile['home_address'] ?? '') ?></strong></div>
            <div class="span-2"><span>Contact Address</span><strong><?= e($profile['contact_address'] ?? $applicant['contact_address'] ?? '') ?></strong></div>
        </div>
    </section>

    <section class="slip-section">
        <h3>Course Information</h3>
        <div class="slip-grid">
            <div><span>JAMB Course</span><strong><?= e($applicant['course_name'] ?: $applicant['course_applied']) ?></strong></div>
            <div><span>Screening Course</span><strong><?= e($application['choice_course']) ?></strong></div>
            <div><span>Alternative Course</span><strong><?= e($application['alternative_course']) ?></strong></div>
        </div>
    </section>

    <section class="slip-section">
        <h3>O Level Results</h3>
        <?php foreach ($groupedOlevel as $sittingNo => $group): ?>
            <?php $meta = $group['meta']; ?>
            <div class="olevel-slip-group">
                <div class="olevel-slip-heading">
                    <strong><?= $sittingNo === 1 ? 'First Sitting' : 'Second Sitting' ?></strong>
                    <span>Sitting: <?= e((string) $sittingNo) ?></span>
                    <span>Exam Type: <?= e($meta['exam_type']) ?></span>
                    <span>Exam Year: <?= e($meta['exam_year']) ?></span>
                    <span>Exam Number: <?= e($meta['exam_number']) ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered slip-table">
                        <thead><tr><th style="width:70px">S/N</th><th>Subject</th><th style="width:140px">Grade</th></tr></thead>
                        <tbody>
                        <?php foreach (($group['subjects'] ?? []) as $index => $row): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= e($row['subject']) ?></td>
                                <td><?= e($row['grade']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="slip-section">
        <h3>Payment and Uploaded Documents</h3>
        <div class="slip-grid">
            <div><span>Payment Status</span><strong><?= e(ucwords($payment['status'] ?? 'Pending')) ?></strong></div>
            <div><span>Payment Reference</span><strong><?= e($payment['reference'] ?? '') ?></strong></div>
            <div><span>Amount</span><strong>NGN <?= e(number_format((float) ($payment['amount'] ?? 0), 2)) ?></strong></div>
            <div><span>Payment Date</span><strong><?= e(!empty($payment['paid_at']) ? date('M j, Y g:i A', strtotime($payment['paid_at'])) : '') ?></strong></div>
        </div>
        <div class="document-checklist">
            <?php foreach (['passport' => 'Passport Photograph', 'olevel_result' => 'O Level Result(s)', 'jamb_slip' => 'JAMB Result Slip', 'birth_certificate' => 'Birth Certificate/Declaration', 'state_certificate' => 'State of Origin Certificate'] as $type => $label): ?>
                <div><?= icon(isset($docsByType[$type]) ? 'check-circle-2' : 'circle') ?><span><?= e($label) ?></span><strong><?= isset($docsByType[$type]) ? 'Uploaded' : 'Not Uploaded' ?></strong></div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="slip-section instructions">
        <h3>Important Instructions</h3>
        <ol>
            <li>Print this acknowledgement slip and keep it safe.</li>
            <li>Bring this slip and original credentials if invited for physical screening.</li>
            <li>False information or forged documents may lead to disqualification.</li>
            <li>Use the verification code above when contacting the admissions help desk.</li>
        </ol>
    </section>
</div>
<?php render_workspace_end(); ?>
<?php render_footer(); ?>
