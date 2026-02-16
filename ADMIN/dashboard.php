<?php
session_start();
// if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
//     header('Location: /login.php');
//     exit;
// }

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';

if (file_exists('db.php')) {
    require 'db.php';
}

$stats = ['total' => 0, 'pending' => 0, 'admitted' => 0, 'rejected' => 0]; // Default

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

        $chart_labels = [];
        $chart_data = [];
        foreach($trendsData as $row) {
            $chart_labels[] = date("M d", strtotime($row['submission_date']));
            $chart_data[] = $row['app_count'];
        }

    } catch (PDOException $e) {
    }
}

$whereClauses = [];
$params = [];
$applications = [];
$totalRows = 0;
$totalPages = 1;

$countSql = "SELECT COUNT(*) FROM applications a LEFT JOIN personal_details p ON a.application_id = p.application_id LEFT JOIN programme_choices pc ON a.application_id = pc.application_id";
$sql = "SELECT a.application_id, a.application_number, a.status, a.submitted_at, p.surname, p.first_name, pc.faculty, pc.department, pc.degree_type, pc.mode_of_study, pc.course FROM applications a LEFT JOIN personal_details p ON a.application_id = p.application_id LEFT JOIN programme_choices pc ON a.application_id = pc.application_id";

$filters = [
    'status' => 'a.status',
    'faculty' => 'pc.faculty',
    'department' => 'pc.department',
    'degree_type' => 'pc.degree_type',
    'mode_of_study' => 'pc.mode_of_study'
];

foreach ($filters as $getVar => $column) {
    if (!empty($_GET[$getVar])) {
        $whereClauses[] = "$column = ?";
        $params[] = $_GET[$getVar];
    }
}

if (!empty($_GET['search'])) {
    $searchTerm = "%" . $_GET['search'] . "%";
    $whereClauses[] = "(p.first_name LIKE ? OR p.surname LIKE ? OR a.application_number LIKE ?)";
    array_push($params, $searchTerm, $searchTerm, $searchTerm);
}

if (count($whereClauses) > 0) {
    $whereStr = " WHERE " . implode(" AND ", $whereClauses);
    $countSql .= $whereStr;
    $sql .= $whereStr;
}

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

if (isset($pdo)) {
    try {
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRows = $countStmt->fetchColumn();
        $totalPages = ceil($totalRows / $limit);

        $sql .= " ORDER BY a.submitted_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

function buildUrl($newPage) {
    $params = $_GET;
    $params['page'] = $newPage;
    return '?' . http_build_query($params);
}
?>




            <!-- Content Container -->
            <div class="content-container">

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
        <div>
            <div class="stat-title">Total Applications</div>
            <div class="stat-value"><?= number_format($stats['total']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-title">Approved Applications</div>
            <div class="stat-value"><?= number_format($stats['admitted']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-title">Under Review</div>
            <div class="stat-value"><?= number_format($stats['pending']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        <div>
            <div class="stat-title">Rejected</div>
            <div class="stat-value"><?= number_format($stats['rejected']) ?></div>
        </div>
    </div>
</section>

<!-- Charts Section -->
<div class="charts-section">
    <div class="chart-card">
        <div class="chart-header">
            <h4>Application Status Distribution</h4>
            <div class="dropdown">
                <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    This Month
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#">This Week</a></li>
                    <li><a class="dropdown-item" href="#">This Month</a></li>
                    <li><a class="dropdown-item" href="#">This Year</a></li>
                </ul>
            </div>
        </div>
        <div class="chart-placeholder">
            <canvas id="applicationStatusChart"></canvas>
        </div>
    </div>
    <div class="chart-card">
        <div class="chart-header">
            <h4>Processing Time Trends</h4>
            <div class="dropdown">
                <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    Last 30 Days
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#">Last 7 Days</a></li>
                    <li><a class="dropdown-item" href="#">Last 30 Days</a></li>
                    <li><a class="dropdown-item" href="#">Last 3 Months</a></li>
                </ul>
            </div>
        </div>
        <div class="chart-placeholder">
            <canvas id="processingTimeChart"></canvas>
        </div>
    </div>
</div>













<!-- Recent Activity -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Recent Applications</h5>
    </div>
    <div class="card-body">
        <div class="activity-list" id="activity-list">
            <?php if (!empty($applications)): ?>
                <?php foreach (array_slice($applications, 0, 3) as $app): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <?php
                            $icon = 'fa-file-alt text-primary'; // Default for submitted
                            if ($app['status'] === 'Admitted') {
                                $icon = 'fa-check-circle text-success';
                            } elseif ($app['status'] === 'Rejected') {
                                $icon = 'fa-times-circle text-danger';
                            } elseif ($app['status'] === 'Submitted') {
                                $icon = 'fa-clock text-warning';
                            }
                            ?>
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="activity-content">
                            <p>
                                Application <?php echo htmlspecialchars($app['status']); ?> for <strong><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['surname']); ?></strong>
                                (<a href="view.php?app_no=<?php echo urlencode($app['application_number']); ?>"><?php echo htmlspecialchars($app['application_number']); ?></a>)
                            </p>
                            <small class="text-muted"><?php echo date('M d, Y, h:i A', strtotime($app['submitted_at'])); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center p-3">
                    <p class="mb-0">No recent applications found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-footer text-center">
        <button class="btn btn-primary" id="view-more">View More</button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const statusCtx = document.getElementById('applicationStatusChart').getContext('2d');
        const applicationStatusChart = new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: ['Admitted', 'Rejected', 'Under Review'],
                datasets: [{
                    label: 'Application Status',
                    data: [<?php echo $stats['admitted']; ?>, <?php echo $stats['rejected']; ?>, <?php echo $stats['pending']; ?>],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.7)',  // Success
                        'rgba(220, 53, 69, 0.7)',  // Danger
                        'rgba(255, 193, 7, 0.7)'   // Warning
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(220, 53, 69, 1)',
                        'rgba(255, 193, 7, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });

        const trendsCtx = document.getElementById('processingTimeChart').getContext('2d');
        const processingTimeChart = new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Applications per Day',
                    data: <?php echo json_encode($chart_data); ?>,
                    fill: false,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        let currentPage = 1;
        const limit = 3;
        const totalApplications = <?php echo json_encode($applications); ?>;

        document.getElementById('view-more').addEventListener('click', function () {
            const activityList = document.getElementById('activity-list');
            const start = currentPage * limit;
            const end = start + limit;
            const nextApplications = totalApplications.slice(start, end);

            nextApplications.forEach(app => {
                const activityItem = document.createElement('div');
                activityItem.classList.add('activity-item');

                const activityIcon = document.createElement('div');
                activityIcon.classList.add('activity-icon');
                let iconClass = 'fa-file-alt text-primary';
                if (app.status === 'Admitted') {
                    iconClass = 'fa-check-circle text-success';
                } else if (app.status === 'Rejected') {
                    iconClass = 'fa-times-circle text-danger';
                } else if (app.status === 'Submitted') {
                    iconClass = 'fa-clock text-warning';
                }
                activityIcon.innerHTML = `<i class="fas ${iconClass}"></i>`;

                const activityContent = document.createElement('div');
                activityContent.classList.add('activity-content');
                activityContent.innerHTML = `
                    <p>
                        Application ${app.status} for <strong>${app.first_name} ${app.surname}</strong>
                        (<a href="view.php?app_no=${encodeURIComponent(app.application_number)}">${app.application_number}</a>)
                    </p>
                    <small class="text-muted">${new Date(app.submitted_at).toLocaleString()}</small>
                `;

                activityItem.appendChild(activityIcon);
                activityItem.appendChild(activityContent);
                activityList.appendChild(activityItem);
            });

            currentPage++;

            if (end >= totalApplications.length) {
                this.style.display = 'none';
            }
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>
