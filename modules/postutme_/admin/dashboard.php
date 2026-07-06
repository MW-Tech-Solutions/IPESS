<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/_admin.php';

$user = require_admin();
$stats = [
    'jamb' => db()->query('SELECT COUNT(*) c FROM jamb_candidates')->fetch()['c'],
    'applicants' => db()->query('SELECT COUNT(*) c FROM applicants')->fetch()['c'],
    'paid' => db()->query('SELECT COUNT(*) c FROM payments WHERE status IN ("paid", "successful")')->fetch()['c'],
    'submitted' => db()->query('SELECT COUNT(*) c FROM screening_applications WHERE status <> "draft"')->fetch()['c'],
];
$byCourse = db()->query('SELECT COALESCE(jc.course_name, jc.course_applied) label, COUNT(*) total FROM applicants a JOIN jamb_candidates jc ON jc.id = a.jamb_candidate_id GROUP BY label ORDER BY total DESC LIMIT 5')->fetchAll();
$byState = db()->query('SELECT COALESCE(jc.state_origin, "Unknown") label, COUNT(*) total FROM applicants a JOIN jamb_candidates jc ON jc.id = a.jamb_candidate_id GROUP BY label ORDER BY total DESC LIMIT 5')->fetchAll();
$paymentSummary = db()->query('SELECT status label, COUNT(*) total, COALESCE(SUM(amount),0) amount FROM payments GROUP BY status')->fetchAll();

render_header('Admin Dashboard');
?>
<?php render_workspace_start('admin', $user, 'Dashboard'); ?>
                <div class="dashboard-head">
                    <div>
                        <p class="eyebrow">Staff Console</p>
                        <h1><?= e(ucwords(str_replace('_', ' ', $user['role']))) ?></h1>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <?php foreach ($stats as $label => $value): ?>
                        <div class="col-6 col-lg-3">
                            <div class="metric-card">
                                <?= icon(['jamb' => 'file-spreadsheet', 'applicants' => 'users-round', 'paid' => 'credit-card', 'submitted' => 'clipboard-check'][$label] ?? 'activity') ?>
                                <span><?= e(ucwords($label)) ?></span>
                                <strong><?= number_format((int) $value) ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="portal-card">
                    <h2>Administrative Modules</h2>
                    <div class="admin-grid">
                        <?php if (in_array($user['role'], ['ict_admin', 'super_admin'], true)): ?>
                            <a href="<?= e(url('admin/import-jamb.php')) ?>"><?= icon('file-spreadsheet') ?> Import JAMB CSV/Excel</a>
                            <a href="<?= e(url('admin/settings.php')) ?>"><?= icon('settings') ?> Payment & Portal Settings</a>
                            <a href="<?= e(url('admin/users.php')) ?>"><?= icon('user-cog') ?> Staff Users</a>
                        <?php endif; ?>
                        <a href="<?= e(url('admin/candidates.php')) ?>"><?= icon('users-round') ?> Imported Candidates</a>
                        <?php if (in_array($user['role'], ['admissions_officer', 'super_admin'], true)): ?>
                            <a href="<?= e(url('admin/applications.php')) ?>"><?= icon('clipboard-check') ?> Review Applications</a>
                        <?php endif; ?>
                        <?php if (in_array($user['role'], ['finance_officer', 'super_admin'], true)): ?>
                            <a href="<?= e(url('admin/payments.php')) ?>"><?= icon('credit-card') ?> Verify Payments</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-lg-4">
                        <div class="portal-card h-100">
                            <h2>Applicants by Course</h2>
                            <?php foreach ($byCourse as $row): ?>
                                <div class="report-row"><span><?= e($row['label']) ?></span><strong><?= e((string) $row['total']) ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="portal-card h-100">
                            <h2>Applicants by State</h2>
                            <?php foreach ($byState as $row): ?>
                                <div class="report-row"><span><?= e($row['label']) ?></span><strong><?= e((string) $row['total']) ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="portal-card h-100">
                            <h2>Payment Summary</h2>
                            <?php foreach ($paymentSummary as $row): ?>
                                <div class="report-row"><span><?= e(ucwords($row['label'])) ?></span><strong><?= number_format((float) $row['amount'], 2) ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
<?php render_workspace_end(); ?>
<?php render_footer(); ?>
