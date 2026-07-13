<?php
$pageTitle = 'Postgraduate School Office';
$pageSubtitle = 'Final academic evaluations, review endorsements, and grant final admission approvals.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
require_once __DIR__ . '/../../ADMIN/admin/includes/db.php';

$stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$facultyBreakdown = [];
$recentLogs = [];

if (isset($pdo)) {
    try {
        // 1. Total
        $stats['total'] = (int) $pdo->query("SELECT COUNT(*) FROM applications WHERE status != 'Draft'")->fetchColumn();

        // 2. Pending PG Review (status is Submitted and current_status is FACULTY_APPROVED)
        $stats['pending'] = (int) $pdo->query("
            SELECT COUNT(*) 
            FROM applications 
            WHERE status = 'Submitted' AND current_status IN ('DEPT_APPROVED', 'FACULTY_APPROVED', 'UNDER_PG_REVIEW')
        ")->fetchColumn();

        // 3. Approved by PG School (APPROVED_BY_POSTGRADUATE_SCHOOL or ADMISSION_APPROVED)
        $stats['approved'] = (int) $pdo->query("
            SELECT COUNT(*) 
            FROM applications 
            WHERE current_status IN ('APPROVED_BY_POSTGRADUATE_SCHOOL', 'ADMISSION_APPROVED', 'Admitted')
        ")->fetchColumn();

        // 4. Rejected by PG School (REJECTED_BY_POSTGRADUATE_SCHOOL or ADMISSION_REJECTED)
        $stats['rejected'] = (int) $pdo->query("
            SELECT COUNT(*) 
            FROM applications 
            WHERE current_status IN ('REJECTED_BY_POSTGRADUATE_SCHOOL', 'ADMISSION_REJECTED', 'Rejected')
        ")->fetchColumn();

        // 5. Faculty breakdown
        $facQuery = "
            SELECT f.faculty_name, COUNT(a.application_id) AS count
            FROM applications a
            JOIN programme_choices pc ON a.application_id = pc.application_id
            JOIN faculties f ON pc.faculty = f.faculty_id
            WHERE a.status != 'Draft'
            GROUP BY f.faculty_id, f.faculty_name
            ORDER BY count DESC
            LIMIT 5;
        ";
        $facultyBreakdown = $pdo->query($facQuery)->fetchAll(PDO::FETCH_ASSOC);

        // 6. Recent Logs
        $logQuery = "
            SELECT wal.*, pd.first_name, pd.surname, u.full_name AS actor_name
            FROM workflow_audit_logs wal
            LEFT JOIN personal_details pd ON wal.applicant_id = pd.application_id
            LEFT JOIN users u ON wal.user_id = u.user_id
            ORDER BY wal.timestamp DESC
            LIMIT 6
        ";
        $recentLogs = $pdo->query($logQuery)->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("PG Dashboard Error: " . $e->getMessage());
    }
}
?>

<section class="page-hero">
    <div>
        <h1>Postgraduate School Command Center</h1>
        <p class="panel-muted">Review credentials and issue final academic approvals. Candidates approved here will proceed to ICT for registration and letter activation.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-primary" href="applications.php">
            <i class="fas fa-gavel me-2"></i>Review PG Applications
        </a>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon" style="color: #6EB533;"><i class="fas fa-graduation-cap"></i></div>
        <div>
            <div class="stat-title">Total Submissions</div>
            <div class="stat-value"><?= number_format($stats['total']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon text-warning"><i class="fas fa-hourglass-half"></i></div>
        <div>
            <div class="stat-title">Awaiting PG Review</div>
            <div class="stat-value"><?= number_format($stats['pending']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon text-success"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-title">PG Approved</div>
            <div class="stat-value"><?= number_format($stats['approved']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon text-danger"><i class="fas fa-times-circle"></i></div>
        <div>
            <div class="stat-title">PG Rejected</div>
            <div class="stat-value"><?= number_format($stats['rejected']) ?></div>
        </div>
    </div>
</section>

<div class="row g-4 mt-1">
    <div class="col-lg-7">
        <section class="panel h-100">
            <div class="panel-header border-bottom-0 pb-0">
                <h3 class="panel-title">Recent Workflow Activities</h3>
                <div class="panel-muted">Latest log events tracked across all admission stages.</div>
            </div>
            <div class="panel-body">
                <?php if (!empty($recentLogs)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-sm">
                            <thead>
                                <tr>
                                    <th>User</th>
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
                                    if (strpos($log['new_status'], 'APPROVED') !== false || $log['new_status'] === 'Completed') {
                                        $statusBadge = '<span class="badge bg-success">Approved</span>';
                                    } elseif (strpos($log['new_status'], 'REJECTED') !== false || $log['new_status'] === 'ACTION_REQUIRED_DOCS') {
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
                    <p class="text-muted text-center py-4">No recent activities recorded.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
    
    <div class="col-lg-5">
        <section class="panel h-100">
            <div class="panel-header border-bottom-0 pb-0">
                <h3 class="panel-title">Submissions by Faculty</h3>
                <div class="panel-muted">Breakdown of candidates across faculties.</div>
            </div>
            <div class="panel-body">
                <?php if (!empty($facultyBreakdown)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($facultyBreakdown as $row): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span class="fw-semibold text-muted"><?= htmlspecialchars($row['faculty_name']) ?></span>
                                <span class="badge rounded-pill px-3" style="background-color: #6EB533;"><?= number_format($row['count']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-4">No submissions records found.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
