<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/_applicant.php';

$user = require_login(['applicant']);
[$applicant, $payment, $application] = applicant_context((int) $user['id']);

$cards = [
    ['JAMB Verified', true, 'verify.php'],
    ['Profile Created', true, 'create-profile.php'],
    ['Payment Status', payment_confirmed($payment), 'applicant/payment.php'],
    ['Bio-data Completion', !empty($applicant['profile_saved']), 'applicant/form.php#bio-data'],
    ['O Level Completion', !empty($application['subjects_json']), 'applicant/form.php#olevel'],
    ['Uploads', !empty($application['passport_path']) && !empty($application['olevel_result_path']), 'applicant/form.php#uploads'],
    ['Review', (bool) $application, 'applicant/review.php'],
    ['Final Submission', ($application['status'] ?? 'draft') !== 'draft', 'applicant/review.php'],
    ['Screening Status', !empty($applicant['screening_status']), 'applicant/status.php'],
];

render_header('Applicant Dashboard');
?>
<?php render_workspace_start('applicant', $applicant, 'Dashboard'); ?>
                <div class="dashboard-head">
                    <div>
                        <p class="eyebrow">Applicant Dashboard</p>
                        <h1><?= e($applicant['surname'] . ' ' . $applicant['first_name']) ?></h1>
                        <p class="text-muted mb-0"><?= e($applicant['jamb_reg_no']) ?> · <?= e($applicant['course_name'] ?: $applicant['course_applied']) ?></p>
                    </div>
                    <a class="btn btn-outline-secondary" href="<?= e(url('applicant/status.php')) ?>">Track Status</a>
                </div>

                <div class="portal-card mb-4">
                    <?php render_steps(applicant_progress($applicant, $payment, $application)); ?>
                </div>

                <div class="row g-3">
                    <?php foreach ($cards as $card): ?>
                        <div class="col-6 col-lg-4">
                            <div class="metric-card">
                                <?= icon($card[1] ? 'check-circle-2' : 'clock') ?>
                                <span><?= e($card[0]) ?></span>
                                <strong><?= $card[1] ? status_badge('successful') : status_badge('pending') ?></strong>
                                <a href="<?= e(url($card[2])) ?>">Open</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <div class="metric-card">
                            <?= icon('credit-card') ?>
                            <span>Payment</span>
                            <strong><?= $payment ? status_badge($payment['status']) : status_badge('pending') ?></strong>
                            <a href="<?= e(url('applicant/payment.php')) ?>">Open payment</a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="metric-card">
                            <?= icon('file-pen-line') ?>
                            <span>Screening Form</span>
                            <strong><?= $application ? status_badge($application['status']) : status_badge('draft') ?></strong>
                            <a href="<?= e(url('applicant/form.php')) ?>">Fill form</a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="metric-card">
                            <?= icon('graduation-cap') ?>
                            <span>UTME Score</span>
                            <strong><?= e((string) ($applicant['jamb_score'] ?: $applicant['utme_score'])) ?></strong>
                            <a href="<?= e(url('applicant/review.php')) ?>">Review application</a>
                        </div>
                    </div>
                </div>
<?php render_workspace_end(); ?>
<?php render_footer(); ?>
