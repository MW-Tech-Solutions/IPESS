<?php
session_start();
// if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
//     header('Location: /login.php');
//     exit;
// }

$pageTitle = 'Admin Dashboard';
$pageSubtitle = 'Admissions activity, verification flow, and application throughput.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
require_once 'includes/db.php';

$stats = ['total' => 0, 'pending' => 0, 'admitted' => 0, 'rejected' => 0];
$chart_labels = [];
$chart_data = [];
$recentApplications = [];

if (isset($pdo)) {
    try {
        $statsSql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'Admitted' THEN 1 ELSE 0 END) as admitted,
                SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
            FROM applications
        ";
        $statsStmt = $pdo->query($statsSql);
        $result = $statsStmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $stats = $result;
        }

        $trendsSql = "
            SELECT 
                DATE(submitted_at) as submission_date,
                COUNT(*) as app_count
            FROM applications
            WHERE submitted_at >= CURDATE() - INTERVAL 30 DAY
            GROUP BY DATE(submitted_at)
            ORDER BY submission_date ASC
        ";
        $trendsStmt = $pdo->query($trendsSql);
        $trendsData = $trendsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($trendsData as $row) {
            $chart_labels[] = date("M d", strtotime($row['submission_date']));
            $chart_data[] = (int) $row['app_count'];
        }

        $recentSql = "
            SELECT a.application_number, a.status, a.submitted_at,
                   p.first_name, p.surname,
                   f.faculty_name, d.dept_name, c.course_title
            FROM applications a
            LEFT JOIN personal_details p ON p.application_id = a.application_id
            LEFT JOIN programme_choices pc ON pc.application_id = a.application_id
            LEFT JOIN faculties f ON pc.faculty = f.faculty_id
            LEFT JOIN departments d ON pc.department = d.dept_id
            LEFT JOIN courses c ON pc.course = c.course_id
            ORDER BY a.submitted_at DESC, a.application_id DESC
            LIMIT 6
        ";
        $recentApplications = $pdo->query($recentSql)->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
    }
}

?>




<section class="page-hero">
    <div>
        <h1>Admissions Admin Overview</h1>
        <p class="panel-muted">Stay on top of submissions, approvals, and verification queues.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-outline-primary" href="reports.php">Generate Report</a>
        <a class="btn btn-primary" href="application-management.php">Review Applications</a>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
        <div>
            <div class="stat-title">Total Applications</div>
            <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-title">Under Review</div>
            <div class="stat-value"><?php echo number_format($stats['pending']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-title">Approved</div>
            <div class="stat-value"><?php echo number_format($stats['admitted']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        <div>
            <div class="stat-title">Rejected</div>
            <div class="stat-value"><?php echo number_format($stats['rejected']); ?></div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Application Insight</h3>
            <div class="panel-muted">Status mix and submission volume (last 30 days).</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="row g-4">
            <div class="col-lg-5">
                <div style="min-height: 240px;">
                    <canvas id="applicationStatusChart"></canvas>
                </div>
            </div>
            <div class="col-lg-7">
                <div style="min-height: 240px;">
                    <canvas id="processingTimeChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Recent Applications</h3>
            <div class="panel-muted">Latest submissions awaiting action.</div>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="application-management.php">View All</a>
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
                        <?php foreach ($recentApplications as $app): ?>
                            <?php
                            $status = $app['status'] ?: 'Draft';
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
                                <td><?php echo htmlspecialchars(trim($app['first_name'] . ' ' . $app['surname'])); ?></td>
                                <td><?php echo htmlspecialchars($app['application_number'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($app['course_title'] ?? $app['dept_name'] ?? $app['faculty_name'] ?? 'Pending'); ?></td>
                                <td><span class="status-chip <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                <td><?php echo $app['submitted_at'] ? date('M d, Y H:i', strtotime($app['submitted_at'])) : 'Not submitted'; ?></td>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const statusCtx = document.getElementById('applicationStatusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Admitted', 'Rejected', 'Under Review'],
                datasets: [{
                    label: 'Application Status',
                    data: [<?php echo (int) $stats['admitted']; ?>, <?php echo (int) $stats['rejected']; ?>, <?php echo (int) $stats['pending']; ?>],
                    backgroundColor: [
                        'rgba(20, 122, 87, 0.75)',
                        'rgba(214, 59, 59, 0.75)',
                        'rgba(212, 175, 55, 0.75)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    const trendsCtx = document.getElementById('processingTimeChart');
    if (trendsCtx) {
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Applications per Day',
                    data: <?php echo json_encode($chart_data); ?>,
                    fill: true,
                    borderColor: 'rgba(11, 91, 63, 0.9)',
                    backgroundColor: 'rgba(11, 91, 63, 0.12)',
                    tension: 0.35
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
