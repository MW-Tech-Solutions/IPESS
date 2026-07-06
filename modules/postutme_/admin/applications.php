<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/_admin.php';

$user = require_admin(['admissions_officer', 'super_admin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'compute') {
        $applicantId = (int) $_POST['applicant_id'];
        $result = compute_screening_result($applicantId, (int) $user['id']);
        db()->prepare('UPDATE applicants SET screening_status = ? WHERE id = ?')->execute([ucwords(str_replace('_', ' ', $result['status'])), $applicantId]);
        flash('success', 'Screening score computed.');
    } else {
        $status = $_POST['status'] ?? 'under_review';
        if (!in_array($status, ['under_review', 'approved', 'rejected'], true)) {
            $status = 'under_review';
        }
        $stmt = db()->prepare('UPDATE screening_applications SET status = ?, officer_comment = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?');
        $stmt->execute([$status, trim($_POST['officer_comment'] ?? ''), $user['id'], (int) $_POST['application_id']]);
        audit_log('reviewed screening application', 'screening_application', (int) $_POST['application_id']);
        flash('success', 'Application review saved.');
    }
}

$apps = db()->query('SELECT sa.*, a.id applicant_id, a.jamb_reg_no, a.surname, a.first_name, jc.utme_score, jc.jamb_score, sr.aggregate_score, sr.qualification_status FROM screening_applications sa JOIN applicants a ON a.id = sa.applicant_id JOIN jamb_candidates jc ON jc.id = a.jamb_candidate_id LEFT JOIN screening_results sr ON sr.applicant_id = a.id ORDER BY sa.submitted_at DESC, sa.id DESC LIMIT 200')->fetchAll();

render_header('Review Applications');
?>
<?php render_workspace_start('admin', $user, 'Applications'); ?>
        <div class="portal-card">
            <h1>Screening Applications</h1>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>JAMB No.</th><th>Name</th><th>Course</th><th>Score</th><th>Screening</th><th>Status</th><th>Review</th></tr></thead>
                    <tbody>
                    <?php foreach ($apps as $app): ?>
                        <tr>
                            <td><?= e($app['jamb_reg_no']) ?></td>
                            <td><?= e($app['surname'] . ' ' . $app['first_name']) ?></td>
                            <td><?= e($app['choice_course']) ?></td>
                            <td><?= e((string) ($app['jamb_score'] ?: $app['utme_score'])) ?></td>
                            <td><?= $app['qualification_status'] ? status_badge($app['qualification_status']) . '<br><small>' . e((string) $app['aggregate_score']) . '</small>' : status_badge('not_started') ?></td>
                            <td><?= status_badge($app['status']) ?></td>
                            <td>
                                <form method="post" class="review-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="application_id" value="<?= e((string) $app['id']) ?>">
                                    <select name="status" class="form-select form-select-sm">
                                        <option value="under_review">Under Review</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                    <input name="officer_comment" class="form-control form-control-sm" placeholder="Officer comment" value="<?= e($app['officer_comment']) ?>">
                                    <button class="btn btn-sm btn-portal-green">Save</button>
                                </form>
                                <form method="post" class="mt-1">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="compute">
                                    <input type="hidden" name="applicant_id" value="<?= e((string) $app['applicant_id']) ?>">
                                    <button class="btn btn-sm btn-outline-secondary">Compute</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
<?php render_workspace_end(); ?>
<?php render_footer(); ?>
