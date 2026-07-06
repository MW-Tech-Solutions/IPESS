<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/_applicant.php';

$user = require_login(['applicant']);
[$applicant, $payment, $application] = applicant_context((int) $user['id']);
if (!$application) {
    redirect('applicant/form.php');
}
$subjects = json_decode($application['subjects_json'] ?? '[]', true) ?: [];
$profileStmt = db()->prepare('SELECT * FROM applicant_profiles WHERE applicant_id = ? LIMIT 1');
$profileStmt->execute([$applicant['id']]);
$profile = $profileStmt->fetch() ?: [];
$docStmt = db()->prepare('SELECT * FROM uploaded_documents WHERE applicant_id = ? ORDER BY document_type');
$docStmt->execute([$applicant['id']]);
$documents = $docStmt->fetchAll();

render_header('Review Application');
?>
<?php render_workspace_start('applicant', $applicant, 'Review'); ?>
        <div class="portal-card">
            <div class="dashboard-head">
                <div>
                    <h1>Review Application</h1>
                    <p class="text-muted mb-0">Confirm all information before final submission.</p>
                </div>
                <?= status_badge($application['status']) ?>
            </div>
            <div class="row g-4">
                <div class="col-md-6">
                    <h2>Applicant</h2>
                    <dl class="review-list">
                        <dt>Name</dt><dd><?= e($applicant['surname'] . ' ' . $applicant['first_name'] . ' ' . $applicant['other_names']) ?></dd>
                        <dt>JAMB No.</dt><dd><?= e($applicant['jamb_reg_no']) ?></dd>
                        <dt>Course</dt><dd><?= e($application['choice_course']) ?></dd>
                        <dt>State/LGA</dt><dd><?= e(($profile['state_origin'] ?? '') . ' / ' . ($profile['lga'] ?? '')) ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <h2>O-level</h2>
                    <dl class="review-list">
                        <dt>Type</dt><dd><?= e($application['olevel_type']) ?></dd>
                        <dt>Exam No.</dt><dd><?= e($application['olevel_exam_no']) ?></dd>
                        <dt>Year</dt><dd><?= e($application['olevel_year']) ?></dd>
                    </dl>
                </div>
            </div>
            <div class="table-responsive mt-3">
                <table class="table table-striped align-middle">
                    <thead><tr><th>Subject</th><th>Grade</th></tr></thead>
                    <tbody>
                    <?php foreach ($subjects as $subject): ?>
                        <tr><td><?= e($subject['subject']) ?></td><td><?= e($subject['grade']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <h2 class="mt-4">Uploaded Documents</h2>
            <div class="row g-2 mb-3">
                <?php foreach ($documents as $document): ?>
                    <div class="col-md-4">
                        <div class="doc-tile">
                            <strong><?= e(ucwords(str_replace('_', ' ', $document['document_type']))) ?></strong>
                            <span><?= e($document['mime_type']) ?> · <?= number_format((int) $document['file_size'] / 1024, 1) ?>KB</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <h2>Payment</h2>
            <p><?= $payment ? status_badge($payment['status']) : status_badge('pending') ?> <?= $payment ? e($payment['reference']) : '' ?></p>
            <?php if ($application['status'] === 'draft'): ?>
                <form method="post" action="<?= e(url('applicant/submit.php')) ?>">
                    <?= csrf_field() ?>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="declaration" value="1" id="declaration" required>
                        <label class="form-check-label" for="declaration">I confirm that the information provided is true and correct.</label>
                    </div>
                    <button class="btn btn-gold btn-lg">Submit Final Application</button>
                    <a class="btn btn-outline-secondary btn-lg" href="<?= e(url('applicant/form.php')) ?>">Edit Form</a>
                </form>
            <?php else: ?>
                <a class="btn btn-portal-green" href="<?= e(url('applicant/status.php')) ?>">Track Status</a>
                <a class="btn btn-outline-secondary" href="<?= e(url('applicant/slip.php')) ?>">Print Acknowledgement Slip</a>
            <?php endif; ?>
        </div>
<?php render_workspace_end(); ?>
<?php render_footer(); ?>
