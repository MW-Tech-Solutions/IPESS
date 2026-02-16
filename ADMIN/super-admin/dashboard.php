<?php
// session_start();
// if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SUPER_ADMIN') {
//     header('Location: /login.php');
//     exit;
// }

$pageTitle = 'Command Center';
$pageSubtitle = 'Institution-wide view of admissions, users, and verification flow.';

require_once 'includes/db.php';

$stats = [
    'total_applications' => 0,
    'submitted' => 0,
    'admitted' => 0,
    'rejected' => 0,
    'total_users' => 0,
    'pending_verifications' => 0,
    'documents_uploaded' => 0,
    'total_faculties' => 0,
    'total_departments' => 0,
    'total_programmes' => 0,
    'total_courses' => 0
];
$recentApplications = [];
$recentNotifications = [];

if ($pdo) {
    $statsSql = "
        SELECT 
            COUNT(*) AS total_applications,
            SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) AS submitted,
            SUM(CASE WHEN status = 'Admitted' THEN 1 ELSE 0 END) AS admitted,
            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) AS rejected
        FROM applications
    ";
    $statsStmt = $pdo->query($statsSql);
    $stats = array_merge($stats, $statsStmt->fetch(PDO::FETCH_ASSOC) ?: []);

    $stats['total_users'] = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['pending_verifications'] = (int) $pdo->query("SELECT COUNT(*) FROM document_verification WHERE verification_status = 'Pending'")->fetchColumn();
    $stats['documents_uploaded'] = (int) $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
    $stats['total_faculties'] = (int) $pdo->query("SELECT COUNT(*) FROM faculties")->fetchColumn();
    $stats['total_departments'] = (int) $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    $stats['total_programmes'] = (int) $pdo->query("SELECT COUNT(*) FROM degree_types")->fetchColumn();
    $stats['total_courses'] = (int) $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();

    $recentSql = "
        SELECT a.application_number, a.status, a.submitted_at,
               p.first_name, p.surname,
               d.dept_name, c.course_title
        FROM applications a
        LEFT JOIN personal_details p ON p.application_id = a.application_id
        LEFT JOIN programme_choices pc ON pc.application_id = a.application_id
        LEFT JOIN departments d ON d.dept_id = pc.department
        LEFT JOIN courses c ON c.course_id = pc.course
        ORDER BY a.submitted_at DESC, a.application_id DESC
        LIMIT 6
    ";
    $recentApplications = $pdo->query($recentSql)->fetchAll(PDO::FETCH_ASSOC);

    $notifySql = "
        SELECT n.notification_title, n.notification_message, n.created_at,
               p.first_name, p.surname
        FROM applicant_notifications n
        LEFT JOIN applications a ON a.application_id = n.application_id
        LEFT JOIN personal_details p ON p.application_id = a.application_id
        ORDER BY n.created_at DESC
        LIMIT 4
    ";
    $recentNotifications = $pdo->query($notifySql)->fetchAll(PDO::FETCH_ASSOC);
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Super Admin Command Center</h1>
        <p class="panel-muted">Monitor applications, user readiness, and verification queues from one place.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-outline-primary" href="reports.php">Generate Report</a>
        <a class="btn btn-primary" href="applications.php">Review Applications</a>
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
        <div class="stat-icon"><i class="fas fa-university"></i></div>
        <div>
            <div class="stat-title">Faculties</div>
            <div class="stat-value"><?php echo number_format($stats['total_faculties']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-paper-plane"></i></div>
        <div>
            <div class="stat-title">Submitted</div>
            <div class="stat-value"><?php echo number_format($stats['submitted']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-building"></i></div>
        <div>
            <div class="stat-title">Departments</div>
            <div class="stat-value"><?php echo number_format($stats['total_departments']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-title">Admitted</div>
            <div class="stat-value"><?php echo number_format($stats['admitted']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
        <div>
            <div class="stat-title">Programmes</div>
            <div class="stat-value"><?php echo number_format($stats['total_programmes']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
        <div>
            <div class="stat-title">Active Users</div>
            <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-book"></i></div>
        <div>
            <div class="stat-title">Courses</div>
            <div class="stat-value"><?php echo number_format($stats['total_courses']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-folder-open"></i></div>
        <div>
            <div class="stat-title">Documents Uploaded</div>
            <div class="stat-value"><?php echo number_format($stats['documents_uploaded']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
        <div>
            <div class="stat-title">Pending Verification</div>
            <div class="stat-value"><?php echo number_format($stats['pending_verifications']); ?></div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Recent Applications</h3>
            <div class="panel-muted">Latest submissions across faculties.</div>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="applications.php">View All</a>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Application No.</th>
                        <th>Programme</th>
                        <th>Status</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recentApplications)): ?>
                        <?php foreach ($recentApplications as $row): ?>
                            <?php
                            $status = $row['status'] ?: 'Draft';
                            $statusClass = 'status-muted';
                            if ($status === 'Submitted') {
                                $statusClass = 'status-warning';
                            } elseif ($status === 'Admitted') {
                                $statusClass = 'status-success';
                            } elseif ($status === 'Rejected') {
                                $statusClass = 'status-danger';
                            }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars(trim($row['first_name'] . ' ' . $row['surname'])); ?></td>
                                <td><?php echo htmlspecialchars($row['application_number'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['course_title'] ?? $row['dept_name'] ?? 'Pending'); ?></td>
                                <td><span class="status-chip <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                <td><?php echo $row['submitted_at'] ? date('M d, Y H:i', strtotime($row['submitted_at'])) : 'Not submitted'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No applications available.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Applicant Notifications</h3>
            <div class="panel-muted">Latest messages issued to applicants.</div>
        </div>
        <button class="btn btn-light btn-sm">Manage</button>
    </div>
    <div class="panel-body">
        <div class="activity-list">
            <?php if (!empty($recentNotifications)): ?>
                <?php foreach ($recentNotifications as $note): ?>
                    <div class="activity-item">
                        <div class="activity-icon"><i class="fas fa-envelope-open-text"></i></div>
                        <div>
                            <div><strong><?php echo htmlspecialchars($note['notification_title']); ?></strong></div>
                            <div class="activity-meta">
                                <?php echo htmlspecialchars(trim(($note['first_name'] ?? '') . ' ' . ($note['surname'] ?? ''))) ?: 'Applicant'; ?>
                                · <?php echo date('M d, Y H:i', strtotime($note['created_at'])); ?>
                            </div>
                            <div class="panel-muted"><?php echo htmlspecialchars($note['notification_message']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-muted">No notifications have been sent yet.</div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
