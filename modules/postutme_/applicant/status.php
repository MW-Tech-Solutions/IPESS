<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/_applicant.php';

$user = require_login(['applicant']);
[$applicant, $payment, $application] = applicant_context((int) $user['id']);
$resultStmt = db()->prepare('SELECT * FROM screening_results WHERE applicant_id = ? LIMIT 1');
$resultStmt->execute([$applicant['id']]);
$result = $resultStmt->fetch();

render_header('Application Status', 'status');
?>
<?php render_workspace_start('applicant', $applicant, 'Status Tracking'); ?>
        <div class="portal-card text-center">
            <p class="eyebrow">Status Tracking</p>
            <h1><?= $application ? status_badge($application['status']) : status_badge('not_started') ?></h1>
            <p class="text-muted">JAMB Number: <?= e($applicant['jamb_reg_no']) ?></p>
            <?php render_steps(applicant_progress($applicant, $payment, $application)); ?>
            <div class="row g-2 text-start mt-4">
                <div class="col-md-6"><div class="doc-tile"><strong>Payment Status</strong><span><?= $payment ? status_badge($payment['status']) : status_badge('pending') ?></span></div></div>
                <div class="col-md-6"><div class="doc-tile"><strong>Application Status</strong><span><?= $application ? status_badge($application['status']) : status_badge('not_started') ?></span></div></div>
                <div class="col-md-6"><div class="doc-tile"><strong>Screening Status</strong><span><?= e($applicant['screening_status']) ?></span></div></div>
                <div class="col-md-6"><div class="doc-tile"><strong>Admission Consideration</strong><span><?= e($applicant['admission_status']) ?></span></div></div>
                <div class="col-md-6"><div class="doc-tile"><strong>Qualification</strong><span><?= $result ? status_badge($result['qualification_status']) : status_badge('not_started') ?></span></div></div>
                <div class="col-md-6"><div class="doc-tile"><strong>Aggregate</strong><span><?= $result ? e((string) $result['aggregate_score']) : 'Pending' ?></span></div></div>
            </div>
            <?php if ($application && $application['officer_comment']): ?>
                <div class="alert alert-info text-start mt-4"><?= e($application['officer_comment']) ?></div>
            <?php endif; ?>
            <a class="btn btn-portal-green mt-3" href="<?= e(url('applicant/dashboard.php')) ?>">Back to Dashboard</a>
        </div>
<?php render_workspace_end(); ?>
<?php render_footer(); ?>
