<?php
require_once __DIR__ . '/../app/bootstrap.php';
enforce_session_timeout(900, 'login.php');
require_role(['DEVELOPER', 'SUPER_ADMIN', 'ICT_ADMIN'], 'login.php');

$pageTitle = 'Developer Dashboard';
$pageSubtitle = 'System health, configuration, and developer tools.';

require_once 'db.php';

$stats = [
    'total_applications' => 0,
    'submitted'          => 0,
    'admitted'           => 0,
    'rejected'           => 0,
    'total_users'        => 0,
    'total_roles'        => 0,
    'total_permissions'  => 0,
    'total_faculties'    => 0,
    'total_departments'  => 0,
    'total_courses'      => 0,
    'php_version'        => PHP_VERSION,
    'db_version'         => 'N/A',
];
$recentAudit = [];
$recentUsers = [];

if ($pdo) {
    try {
        $s = $pdo->query("SELECT COUNT(*) AS t, SUM(status='Submitted') AS sub, SUM(status='Admitted') AS adm, SUM(status='Rejected') AS rej FROM applications")->fetch(PDO::FETCH_ASSOC);
        $stats['total_applications'] = (int)($s['t']   ?? 0);
        $stats['submitted']          = (int)($s['sub'] ?? 0);
        $stats['admitted']           = (int)($s['adm'] ?? 0);
        $stats['rejected']           = (int)($s['rej'] ?? 0);
        $stats['total_users']        = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats['total_roles']        = (int)$pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
        $stats['total_permissions']  = (int)$pdo->query("SELECT COUNT(*) FROM permissions")->fetchColumn();
        $stats['total_faculties']    = (int)$pdo->query("SELECT COUNT(*) FROM faculties")->fetchColumn();
        $stats['total_departments']  = (int)$pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
        $stats['total_courses']      = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
        $stats['db_version']         = $pdo->query("SELECT VERSION()")->fetchColumn();
    } catch (Throwable $e) {}
    try {
        $recentAudit = $pdo->query("SELECT action, description, created_at, performed_by FROM audit_logs ORDER BY created_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
    try {
        $recentUsers = $pdo->query("SELECT u.email, u.full_name, u.created_at, r.role_name FROM users u LEFT JOIN roles r ON r.role_id=u.role_id ORDER BY u.created_at DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

require_once 'includes/dev_header.php';
require_once 'includes/sidebar.php';
require_once 'includes/dev_topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Developer Dashboard</h1>
        <p class="panel-muted">System health, quick tools, and live metrics for the IPESS platform.</p>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
        <div>
            <div class="stat-title">Total Applications</div>
            <div class="stat-value"><?php echo number_format($stats['total_applications']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        <div>
            <div class="stat-title">Admitted</div>
            <div class="stat-value"><?php echo number_format($stats['admitted']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-title">Submitted</div>
            <div class="stat-value"><?php echo number_format($stats['submitted']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-times"></i></div>
        <div>
            <div class="stat-title">Rejected</div>
            <div class="stat-value"><?php echo number_format($stats['rejected']); ?></div>
        </div>
    </div>
</section>

<div class="row g-4 mt-1">
    <div class="col-12">
        <div class="panel">
            <div class="panel-header">
                <h3 class="panel-title"><i class="fas fa-history me-2 text-danger"></i>Recent Audit Log</h3>
                <a href="audit-logs.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="panel-body p-0">
                <?php if (!empty($recentAudit)): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Description</th>
                                <th>By</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentAudit as $log): ?>
                            <tr>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($log['action'] ?? ''); ?></span></td>
                                <td class="small"><?php echo htmlspecialchars(substr($log['description'] ?? '', 0, 80)); ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($log['performed_by'] ?? 'System'); ?></td>
                                <td class="small text-muted"><?php echo $log['created_at'] ? date('d M H:i', strtotime($log['created_at'])) : 'N/A'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-muted text-center py-4">No audit log entries yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/dev_footer.php'; ?>