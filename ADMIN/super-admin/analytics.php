<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SUPER_ADMIN') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'Analytics';
$pageSubtitle = 'Visual trends across submissions, conversions, and faculty throughput.';

require_once 'includes/db.php';

$trendLabels = [];
$trendValues = [];
$statusLabels = [];
$statusValues = [];
$facultyLabels = [];
$facultyValues = [];

if ($pdo) {
    $trendSql = "
        SELECT DATE_FORMAT(MIN(submitted_at), '%b %Y') AS label, COUNT(*) AS total,
               YEAR(submitted_at) AS yr, MONTH(submitted_at) AS mo
        FROM applications
        WHERE submitted_at IS NOT NULL
        GROUP BY YEAR(submitted_at), MONTH(submitted_at)
        ORDER BY yr DESC, mo DESC
        LIMIT 6
    ";
    $trendRows = $pdo->query($trendSql)->fetchAll(PDO::FETCH_ASSOC);
    $trendRows = array_reverse($trendRows);
    foreach ($trendRows as $row) {
        $trendLabels[] = $row['label'];
        $trendValues[] = (int) $row['total'];
    }

    $statusSql = "
        SELECT status, COUNT(*) AS total
        FROM applications
        GROUP BY status
        ORDER BY total DESC
    ";
    $statusRows = $pdo->query($statusSql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($statusRows as $row) {
        $statusLabels[] = $row['status'];
        $statusValues[] = (int) $row['total'];
    }

    $facultySql = "
        SELECT f.faculty_name, COUNT(*) AS total
        FROM programme_choices pc
        LEFT JOIN faculties f ON f.faculty_id = pc.faculty
        GROUP BY f.faculty_name
        ORDER BY total DESC
        LIMIT 5
    ";
    $facultyRows = $pdo->query($facultySql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($facultyRows as $row) {
        $facultyLabels[] = $row['faculty_name'] ?? 'Unassigned';
        $facultyValues[] = (int) $row['total'];
    }
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Analytics Studio</h1>
        <p class="panel-muted">Track how applications evolve, spot peaks, and align resources by faculty.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light">Download Charts</button>
        <button class="btn btn-primary">Refresh Data</button>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Submission Trends</h3>
            <div class="panel-muted">Applications submitted in the last six cycles.</div>
        </div>
    </div>
    <div class="panel-body">
        <canvas id="trendChart" height="120"></canvas>
    </div>
</section>

<div class="row g-4">
    <div class="col-lg-6">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">Status Distribution</h3>
                    <div class="panel-muted">Submitted, admitted, and rejected split.</div>
                </div>
            </div>
            <div class="panel-body">
                <canvas id="statusChart" height="200"></canvas>
            </div>
        </section>
    </div>
    <div class="col-lg-6">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">Top Faculties</h3>
                    <div class="panel-muted">Most active faculty clusters by submissions.</div>
                </div>
            </div>
            <div class="panel-body">
                <canvas id="facultyChart" height="200"></canvas>
            </div>
        </section>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const trendCtx = document.getElementById('trendChart');
const statusCtx = document.getElementById('statusChart');
const facultyCtx = document.getElementById('facultyChart');

new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($trendLabels); ?>,
        datasets: [{
            label: 'Applications',
            data: <?php echo json_encode($trendValues); ?>,
            borderColor: '#0b5b3f',
            backgroundColor: 'rgba(11, 91, 63, 0.15)',
            tension: 0.35,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($statusLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($statusValues); ?>,
            backgroundColor: ['#0b5b3f', '#d4af37', '#9a3412', '#6b7280']
        }]
    },
    options: { responsive: true }
});

new Chart(facultyCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($facultyLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($facultyValues); ?>,
            backgroundColor: '#147a57'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
