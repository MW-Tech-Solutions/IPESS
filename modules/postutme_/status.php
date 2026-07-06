<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/layout.php';

$record = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = db()->prepare('SELECT a.jamb_reg_no, a.surname, a.first_name, sa.status, sa.officer_comment FROM applicants a LEFT JOIN screening_applications sa ON sa.applicant_id = a.id WHERE a.jamb_reg_no = ? LIMIT 1');
    $stmt->execute([strtoupper(trim($_POST['jamb_reg_no'] ?? ''))]);
    $record = $stmt->fetch();
    if (!$record) {
        flash('error', 'No application profile found for that JAMB number.');
    }
}

render_header('Check Status', 'status');
?>
<section class="section-pad gateway-shell">
    <div class="container">
        <div class="split-page">
            <div class="split-info">
                <img src="<?= e(url('images/new_jostum_logo.png')) ?>" alt="JOSTUM logo">
                <p class="eyebrow">Application Tracking</p>
                <h1>Check your screening progress</h1>
                <p>Use your JAMB registration number to view the latest application status, payment status, screening decision, and admissions office messages.</p>
                <div class="info-list">
                    <span>Track submitted screening forms</span>
                    <span>View admissions office comments</span>
                    <span>Use the same JAMB number on your profile</span>
                </div>
            </div>
            <div class="auth-card">
                <div class="auth-brand">
                    <img src="<?= e(url('images/new_jostum_logo.png')) ?>" alt="JOSTUM logo">
                    <div>
                        <p class="eyebrow mb-1">Status Tracking</p>
                        <h1>Check Status</h1>
                    </div>
                </div>
                <form method="post" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label class="form-label">JAMB Registration Number</label>
                        <input name="jamb_reg_no" class="form-control form-control-lg" required placeholder="Example: 202512345678AB">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-portal-green btn-lg w-100">Check Status</button>
                    </div>
                </form>
            </div>
        </div>
            <?php if ($record): ?>
                <div class="status-result mt-4">
                    <strong><?= e($record['surname'] . ' ' . $record['first_name']) ?></strong>
                    <span><?= status_badge($record['status'] ?? 'draft') ?></span>
                    <?php if ($record['officer_comment']): ?><p><?= e($record['officer_comment']) ?></p><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php render_footer(); ?>
