<?php
$pageTitle = 'ICTO Command Center';
$pageSubtitle = 'Perform document verification, screen applicants, and manage workflow progression.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
require_once __DIR__ . '/../../ADMIN/admin/includes/db.php';

$stats = ['total' => 0, 'pending' => 0, 'verified' => 0, 'rejected' => 0];
$deptBreakdown = [];
$recentLogs = [];

if (isset($pdo)) {
    try {
        // Total Applicants (excluding drafts)
        $stats['total'] = (int) $pdo->query("SELECT COUNT(*) FROM applications WHERE status != 'Draft'")->fetchColumn();

        // Pending Verification (Submitted status and Documents stage not completed)
        $stats['pending'] = (int) $pdo->query("
            SELECT COUNT(DISTINCT a.application_id) 
            FROM applications a
            LEFT JOIN application_progress ap ON a.application_id = ap.application_id AND ap.stage = 'Documents Verification'
            WHERE a.status != 'Draft' AND (ap.stage_status IS NULL OR ap.stage_status != 'Completed')
        ")->fetchColumn();

        // Verified Applicants (Documents stage is Completed)
        $stats['verified'] = (int) $pdo->query("
            SELECT COUNT(DISTINCT application_id) 
            FROM application_progress 
            WHERE stage = 'Documents Verification' AND stage_status = 'Completed'
        ")->fetchColumn();

        // Rejected Applicants (either global status is Rejected or ACTION_REQUIRED_DOCS)
        $stats['rejected'] = (int) $pdo->query("
            SELECT COUNT(*) 
            FROM applications 
            WHERE current_status = 'ACTION_REQUIRED_DOCS' OR status = 'Rejected'
        ")->fetchColumn();

        // Applications by Department
        $deptStmt = $pdo->query("
            SELECT d.dept_name, COUNT(a.application_id) AS count
            FROM applications a
            JOIN programme_choices pc ON a.application_id = pc.application_id
            JOIN departments d ON pc.department = d.dept_id
            WHERE a.status != 'Draft'
            GROUP BY d.dept_id, d.dept_name
            ORDER BY count DESC
            LIMIT 5
        ");
        $deptBreakdown = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent Activities from workflow_audit_logs
        $logsStmt = $pdo->query("
            SELECT wal.*, p.first_name, p.surname, u.full_name AS actor_name
            FROM workflow_audit_logs wal
            LEFT JOIN personal_details p ON wal.applicant_id = p.application_id
            LEFT JOIN users u ON wal.user_id = u.user_id
            ORDER BY wal.timestamp DESC
            LIMIT 6
        ");
        $recentLogs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("ICTO Dashboard Error: " . $e->getMessage());
    }
}
?>

<section class="page-hero">
    <div>
        <h1>ICTO Overview</h1>
        <p class="panel-muted">Monitor document queues and approve incoming candidate files.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-primary" href="document-verification.php">
            <i class="fas fa-tasks me-2"></i>Go to Document Verification
        </a>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon" style="color: #6EB533;"><i class="fas fa-users"></i></div>
        <div>
            <div class="stat-title">Total Applicants</div>
            <div class="stat-value"><?= number_format($stats['total']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon text-warning"><i class="fas fa-hourglass-half"></i></div>
        <div>
            <div class="stat-title">Pending Verification</div>
            <div class="stat-value"><?= number_format($stats['pending']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon text-success"><i class="fas fa-check-double"></i></div>
        <div>
            <div class="stat-title">Verified Applicants</div>
            <div class="stat-value"><?= number_format($stats['verified']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon text-danger"><i class="fas fa-user-times"></i></div>
        <div>
            <div class="stat-title">Rejected / Action Req.</div>
            <div class="stat-value"><?= number_format($stats['rejected']) ?></div>
        </div>
    </div>
</section>

<div class="row g-4 mt-1">
    <div class="col-lg-7">
        <section class="panel h-100">
            <div class="panel-header border-bottom-0 pb-0">
                <h3 class="panel-title">Recent Verification Activities</h3>
                <div class="panel-muted">Latest status changes and document actions logged.</div>
            </div>
            <div class="panel-body">
                <?php if (!empty($recentLogs)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-sm">
                            <thead>
                                <tr>
                                    <th>Officer</th>
                                    <th>Applicant</th>
                                    <th>Action</th>
                                    <th>New Status</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentLogs as $log): ?>
                                    <?php
                                    $actor = htmlspecialchars($log['actor_name'] ?: $log['role'] ?: 'Officer');
                                    $applicant = htmlspecialchars(trim(($log['first_name'] ?? '') . ' ' . ($log['surname'] ?? '')) ?: 'N/A');
                                    $statusBadge = '';
                                    if ($log['new_status'] === 'Verified' || $log['new_status'] === 'Completed') {
                                        $statusBadge = '<span class="badge bg-success">Verified</span>';
                                    } elseif ($log['new_status'] === 'Rejected') {
                                        $statusBadge = '<span class="badge bg-danger">Rejected</span>';
                                    } elseif (!empty($log['new_status'])) {
                                        $statusBadge = '<span class="badge bg-secondary">' . htmlspecialchars($log['new_status']) . '</span>';
                                    }
                                    ?>
                                    <tr>
                                        <td><strong><?= $actor ?></strong></td>
                                        <td><?= $applicant ?></td>
                                        <td><?= htmlspecialchars($log['action']) ?></td>
                                        <td><?= $statusBadge ?></td>
                                        <td class="text-muted"><?= date('M d, H:i', strtotime($log['timestamp'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-4">No recent verification activities recorded.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
    
    <div class="col-lg-5">
        <section class="panel h-100">
            <div class="panel-header border-bottom-0 pb-0">
                <h3 class="panel-title">Top Departments by Submissions</h3>
                <div class="panel-muted">Applicant counts grouped by course departments.</div>
            </div>
            <div class="panel-body">
                <?php if (!empty($deptBreakdown)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($deptBreakdown as $row): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span class="fw-semibold text-muted"><?= htmlspecialchars($row['dept_name']) ?></span>
                                <span class="badge rounded-pill px-3" style="background-color: #6EB533;"><?= number_format($row['count']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-4">No submissions record available.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
