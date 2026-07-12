<?php
$pageTitle = 'Faculty Admissions Portal';
$pageSubtitle = 'Review department-approved applications and endorse candidates for PG School review.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
require_once __DIR__ . '/../../ADMIN/admin/includes/db.php';

$stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$deptBreakdown = [];
$recentLogs = [];

$facultyId = null;
if (isset($_SESSION['faculty_id'])) {
    $facultyId = $_SESSION['faculty_id'];
} else if (isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT faculty_id FROM users WHERE user_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $fVal = $stmt->fetchColumn();
        if ($fVal !== false && $fVal !== null) {
            $_SESSION['faculty_id'] = (int) $fVal;
            $facultyId = (int) $fVal;
        }
    } catch (PDOException $e) {}
}

if (isset($pdo)) {
    try {
        $whereSql = "a.status != 'Draft'";
        $params = [];
        if ($facultyId) {
            $whereSql .= " AND pc.faculty = :faculty";
            $params[':faculty'] = $facultyId;
        }

        // 1. Total
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT a.application_id) 
            FROM applications a 
            JOIN programme_choices pc ON a.application_id = pc.application_id 
            WHERE {$whereSql}
        ");
        $stmt->execute($params);
        $stats['total'] = (int) $stmt->fetchColumn();

        // 2. Pending Faculty Review (current_status = 'DEPT_APPROVED' or 'UNDER_FACULTY_REVIEW')
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT a.application_id) 
            FROM applications a 
            JOIN programme_choices pc ON a.application_id = pc.application_id 
            WHERE {$whereSql} AND a.current_status IN ('DEPT_APPROVED', 'UNDER_FACULTY_REVIEW')
        ");
        $stmt->execute($params);
        $stats['pending'] = (int) $stmt->fetchColumn();

        // 3. Approved by Faculty
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT a.application_id) 
            FROM applications a 
            JOIN programme_choices pc ON a.application_id = pc.application_id 
            WHERE {$whereSql} AND a.current_status IN ('FACULTY_APPROVED', 'APPROVED_BY_FACULTY')
        ");
        $stmt->execute($params);
        $stats['approved'] = (int) $stmt->fetchColumn();

        // 4. Rejected/Action required
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT a.application_id) 
            FROM applications a 
            JOIN programme_choices pc ON a.application_id = pc.application_id 
            WHERE {$whereSql} AND a.current_status IN ('FACULTY_REJECTED', 'ACTION_REQUIRED_DOCS')
        ");
        $stmt->execute($params);
        $stats['rejected'] = (int) $stmt->fetchColumn();

        // 5. Dept breakdowns
        $deptQuery = "
            SELECT d.dept_name, COUNT(a.application_id) AS count
            FROM applications a
            JOIN programme_choices pc ON a.application_id = pc.application_id
            JOIN departments d ON pc.department = d.dept_id
            WHERE {$whereSql}
            GROUP BY d.dept_id, d.dept_name
            ORDER BY count DESC
            LIMIT 5;
        ";
        $stmt = $pdo->prepare($deptQuery);
        $stmt->execute($params);
        $deptBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 6. Recent Logs
        $logQuery = "
            SELECT wal.*, pd.first_name, pd.surname, u.full_name AS actor_name
            FROM workflow_audit_logs wal
            LEFT JOIN personal_details pd ON wal.applicant_id = pd.application_id
            LEFT JOIN users u ON wal.user_id = u.user_id
            " . ($facultyId ? "JOIN programme_choices pc ON wal.applicant_id = pc.application_id AND pc.faculty = :faculty" : "") . "
            ORDER BY wal.timestamp DESC
            LIMIT 6
        ";
        $stmt = $pdo->prepare($logQuery);
        $stmt->execute($params);
        $recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Faculty Dashboard Error: " . $e->getMessage());
    }
}
?>

<section class="page-hero">
    <div>
        <h1>Faculty Panel Overview</h1>
        <p class="panel-muted">Review submissions endorsed by departmental HODs and prepare recommendations for the Postgraduate School.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-primary" href="applications.php">
            <i class="fas fa-folder-open me-2"></i>Review Faculty Applications
        </a>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon" style="color: #6EB533;"><i class="fas fa-university"></i></div>
        <div>
            <div class="stat-title">Faculty Submissions</div>
            <div class="stat-value"><?= number_format($stats['total']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon text-warning"><i class="fas fa-hourglass-half"></i></div>
        <div>
            <div class="stat-title">Awaiting Review</div>
            <div class="stat-value"><?= number_format($stats['pending']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon text-success"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-title">Approved by Faculty</div>
            <div class="stat-value"><?= number_format($stats['approved']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon text-danger"><i class="fas fa-times-circle"></i></div>
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
                <h3 class="panel-title">Recent Faculty Activities</h3>
                <div class="panel-muted">Latest transitions in this faculty's review pipeline.</div>
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
                    <p class="text-muted text-center py-4">No recent activities logged in this faculty.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
    
    <div class="col-lg-5">
        <section class="panel h-100">
            <div class="panel-header border-bottom-0 pb-0">
                <h3 class="panel-title">Submissions by Department</h3>
                <div class="panel-muted">Breakdown of candidates across departments.</div>
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
                    <p class="text-muted text-center py-4">No submissions records found.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
