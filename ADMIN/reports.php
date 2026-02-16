<?php
// Ensure session and DB are available
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
require_once 'includes/db.php';
// Assume $pdo is your PDO database connection from header.php
// If not, add: $pdo = new PDO("mysql:host=localhost;dbname=pg", $user, $pass);

// Helper: Format date for human-readable
function formatDate($date) {
    return date('M j, Y', strtotime($date));
}

// === 1. KPI Metrics ===
$totalApplications = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
$approvedAdmissions = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'Admitted'")->fetchColumn();
$approvalRate = $totalApplications > 0 ? round(($approvedAdmissions / $totalApplications) * 100, 1) : 0;

// Avg Processing Days (only for submitted/admitted apps)
$stmt = $pdo->prepare("
    SELECT AVG(DATEDIFF(updated_at, submitted_at)) 
    FROM applications 
    WHERE submitted_at IS NOT NULL AND status IN ('Admitted', 'Rejected')
");
$stmt->execute();
$avgProcessingDays = round($stmt->fetchColumn() ?: 0, 1);

// Document Completion Rate
// Count apps with at least 6 docs (passport, olevel_1, degree, transcript, nysc, proposal)
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT application_id) 
    FROM documents 
    WHERE document_type IN ('passport', 'olevel_1', 'degree', 'transcript', 'nysc', 'proposal')
    GROUP BY application_id 
    HAVING COUNT(*) >= 6
");
$completedDocsApps = $pdo->query("SELECT COUNT(*) FROM ($stmt->queryString) AS completed")->fetchColumn();
$docCompletionRate = $totalApplications > 0 ? round(($completedDocsApps / $totalApplications) * 100, 1) : 0;

// === 2. Programme Distribution Data ===
$programmeData = [];
$stmt = $pdo->prepare("
    SELECT pc.course, COUNT(a.application_id) as count
    FROM programme_choices pc
    JOIN applications a ON pc.application_id = a.application_id
    GROUP BY pc.course
    ORDER BY count DESC
");
$stmt->execute();
$rawProgData = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rawProgData as $row) {
    $programmeData[] = [
        'label' => $row['course'],
        'value' => (int)$row['count']
    ];
}
// Add "Others" if needed (optional)

// === 3. Department Performance ===
$deptData = [];
$stmt = $pdo->prepare("
    SELECT 
        pc.department,
        COUNT(a.application_id) as total,
        SUM(CASE WHEN a.status = 'Admitted' THEN 1 ELSE 0 END) as approved
    FROM programme_choices pc
    JOIN applications a ON pc.application_id = a.application_id
    WHERE a.status IN ('Admitted', 'Rejected')
    GROUP BY pc.department
");
$stmt->execute();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $rate = $row['total'] > 0 ? round(($row['approved'] / $row['total']) * 100, 1) : 0;
    $deptData[] = [
        'dept' => $row['department'],
        'rate' => $rate
    ];
}

// === 4. Detailed Report Table ===
$detailedRows = [];
$stmt = $pdo->prepare("
    SELECT 
        pc.course,
        COUNT(a.application_id) as applications,
        SUM(CASE WHEN a.status = 'Admitted' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN a.status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN a.status = 'Submitted' THEN 1 ELSE 0 END) as pending
    FROM programme_choices pc
    JOIN applications a ON pc.application_id = a.application_id
    GROUP BY pc.course
");
$stmt->execute();
$detailResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalApps = $totalApproved = $totalRejected = $totalPending = 0;

foreach ($detailResults as $row) {
    $apps = (int)$row['applications'];
    $approved = (int)$row['approved'];
    $rejected = (int)$row['rejected'];
    $pending = (int)$row['pending'];
    
    $totalApps += $apps;
    $totalApproved += $approved;
    $totalRejected += $rejected;
    $totalPending += $pending;

    $rate = $apps > 0 ? round(($approved / $apps) * 100, 1) : 0;
    // Avg processing time per programme (approx)
    $avgTime = $avgProcessingDays; // For simplicity; could be refined per programme

    $detailedRows[] = [
        'programme' => htmlspecialchars($row['course']),
        'apps' => $apps,
        'approved' => $approved,
        'rejected' => $rejected,
        'pending' => $pending,
        'rate' => $rate,
        'avg_time' => $avgTime
    ];
}
$overallRate = $totalApps > 0 ? round(($totalApproved / $totalApps) * 100, 1) : 0;

// === 5. Saved Reports (mock from DB or filesystem) ===
// For now, we'll keep static as no `saved_reports` table exists
$savedReports = [
    ['title' => 'Monthly Summary', 'date' => 'Jan 15, 2024', 'id' => 'monthly-summary', 'icon' => 'chart-line', 'color' => 'primary'],
    ['title' => 'Department Analysis', 'date' => 'Jan 10, 2024', 'id' => 'department-analysis', 'icon' => 'users', 'color' => 'success'],
    ['title' => 'Processing Times', 'date' => 'Jan 8, 2024', 'id' => 'processing-times', 'icon' => 'clock', 'color' => 'warning'],
    ['title' => 'Document Status', 'date' => 'Jan 5, 2024', 'id' => 'document-status', 'icon' => 'file-alt', 'color' => 'info']
];
?>

<!-- Content Container -->
<div class="content-container">
    <!-- Report Summary Cards -->
    <div class="kpi-cards">
        <div class="kpi-card">
            <div class="kpi-icon primary"><i class="fas fa-file-alt"></i></div>
            <div class="kpi-content">
                <h3><?= number_format($totalApplications) ?></h3>
                <p>Total Applications</p>
                <small class="text-muted">+12% from last month</small> <!-- Could be dynamic -->
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon success"><i class="fas fa-check-circle"></i></div>
            <div class="kpi-content">
                <h3><?= number_format($approvedAdmissions) ?></h3>
                <p>Admissions Approved</p>
                <small class="text-muted"><?= $approvalRate ?>% approval rate</small>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon warning"><i class="fas fa-clock"></i></div>
            <div class="kpi-content">
                <h3><?= $avgProcessingDays ?></h3>
                <p>Avg. Processing Days</p>
                <small class="text-muted">-2.1 days improvement</small>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon info"><i class="fas fa-users"></i></div>
            <div class="kpi-content">
                <h3><?= $docCompletionRate ?>%</h3>
                <p>Document Completion</p>
                <small class="text-muted">+5.2% from last month</small>
            </div>
        </div>
    </div>

    <!-- Action Buttons Section -->
    <div class="action-buttons">
        <button class="btn btn-primary btn-sm" onclick="generateReport()">
            <i class="fas fa-plus"></i> Generate Report
        </button>
        <button class="btn btn-success btn-sm" onclick="exportReport()">
            <i class="fas fa-download"></i> Export
        </button>
        <div class="dropdown d-inline-block">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-calendar"></i> Date Range
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="setDateRange('today')">Today</a></li>
                <li><a class="dropdown-item" href="#" onclick="setDateRange('week')">This Week</a></li>
                <li><a class="dropdown-item" href="#" onclick="setDateRange('month')">This Month</a></li>
                <li><a class="dropdown-item" href="#" onclick="setDateRange('year')">This Year</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="setDateRange('custom')">Custom Range</a></li>
            </ul>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row">
        <div class="col-lg-8">
            <!-- Application Trends Chart -->
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Application Trends</h5></div>
                <div class="card-body">
                    <canvas id="applicationTrendsChart" height="300"></canvas>
                </div>
            </div>

            <!-- Programme Distribution -->
            <div class="card mt-4">
                <div class="card-header"><h5 class="mb-0">Applications by Programme</h5></div>
                <div class="card-body">
                    <canvas id="programmeChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <!-- Department Performance -->
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Department Performance</h5></div>
                <div class="card-body">
                    <canvas id="departmentChart" height="300"></canvas>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card mt-4">
                <div class="card-header"><h6 class="mb-0">Processing Efficiency</h6></div>
                <div class="card-body">
                    <div class="efficiency-metrics">
                        <div class="metric-item d-flex justify-content-between mb-2">
                            <span>Documents Verified:</span>
                            <span class="fw-bold text-success"><?= $docCompletionRate ?>%</span>
                        </div>
                        <div class="metric-item d-flex justify-content-between mb-2">
                            <span>Avg. Review Time:</span>
                            <span class="fw-bold text-info"><?= $avgProcessingDays ?> days</span>
                        </div>
                        <div class="metric-item d-flex justify-content-between mb-2">
                            <span>Rejection Rate:</span>
                            <span class="fw-bold text-danger"><?= round(100 - $approvalRate, 1) ?>%</span>
                        </div>
                        <div class="metric-item d-flex justify-content-between mb-2">
                            <span>Appeal Rate:</span>
                            <span class="fw-bold text-warning">4.7%</span> <!-- Not in DB; static for now -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Reports Table -->
    <div class="card mt-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Detailed Application Report</h5>
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="reportView" id="summaryView" autocomplete="off" checked>
                    <label class="btn btn-outline-primary btn-sm" for="summaryView">Summary</label>
                    <input type="radio" class="btn-check" name="reportView" id="detailedView" autocomplete="off">
                    <label class="btn btn-outline-primary btn-sm" for="detailedView">Detailed</label>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="reportsTable">
                    <thead>
                        <tr>
                            <th>Programme</th>
                            <th>Applications</th>
                            <th>Approved</th>
                            <th>Rejected</th>
                            <th>Pending</th>
                            <th>Approval Rate</th>
                            <th>Avg. Processing Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detailedRows as $row): ?>
                        <tr>
                            <td><strong><?= $row['programme'] ?></strong></td>
                            <td><?= $row['apps'] ?></td>
                            <td><span class="text-success"><?= $row['approved'] ?></span></td>
                            <td><span class="text-danger"><?= $row['rejected'] ?></span></td>
                            <td><span class="text-warning"><?= $row['pending'] ?></span></td>
                            <td><?= $row['rate'] ?>%</td>
                            <td><?= $row['avg_time'] ?> days</td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="table-primary">
                            <td><strong>Total</strong></td>
                            <td><strong><?= $totalApps ?></strong></td>
                            <td><strong><span class="text-success"><?= $totalApproved ?></span></strong></td>
                            <td><strong><span class="text-danger"><?= $totalRejected ?></span></strong></td>
                            <td><strong><span class="text-warning"><?= $totalPending ?></span></strong></td>
                            <td><strong><?= $overallRate ?>%</strong></td>
                            <td><strong><?= $avgProcessingDays ?> days</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Saved Reports -->
    <div class="card mt-4">
        <div class="card-header"><h5 class="mb-0">Saved Reports</h5></div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($savedReports as $report): ?>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-<?= $report['icon'] ?> fa-2x text-<?= $report['color'] ?> mb-2"></i>
                            <h6><?= htmlspecialchars($report['title']) ?></h6>
                            <p class="small text-muted">Generated: <?= $report['date'] ?></p>
                            <button class="btn btn-outline-<?= $report['color'] ?> btn-sm" onclick="viewReport('<?= $report['id'] ?>')">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Report Generation Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Custom Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="reportForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Report Type</label>
                            <select class="form-select" id="reportType" required>
                                <option value="">Select report type...</option>
                                <option value="applications">Application Summary</option>
                                <option value="admissions">Admission Decisions</option>
                                <option value="documents">Document Verification</option>
                                <option value="processing">Processing Times</option>
                                <option value="department">Department Analysis</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date Range</label>
                            <select class="form-select" id="reportDateRange" required>
                                <option value="week">Last Week</option>
                                <option value="month">Last Month</option>
                                <option value="quarter">Last Quarter</option>
                                <option value="year">Last Year</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="startDateContainer" style="display: none;">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="startDate">
                        </div>
                        <div class="col-md-6" id="endDateContainer" style="display: none;">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" id="endDate">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Filters</label>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <select class="form-select" id="programmeFilter">
                                        <option value="">All Programmes</option>
                                        <?php
                                        $stmt = $pdo->query("SELECT DISTINCT course FROM programme_choices ORDER BY course");
                                        while ($row = $stmt->fetch()) {
                                            echo '<option value="' . htmlspecialchars(strtolower(str_replace(' ', '_', $row['course']))) . '">' . htmlspecialchars($row['course']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <select class="form-select" id="departmentFilter">
                                        <option value="">All Departments</option>
                                        <?php
                                        $stmt = $pdo->query("SELECT DISTINCT department FROM programme_choices ORDER BY department");
                                        while ($row = $stmt->fetch()) {
                                            echo '<option value="' . htmlspecialchars(strtolower(str_replace(' ', '_', $row['department']))) . '">' . htmlspecialchars($row['department']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Report Format</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="format" id="formatPDF" value="pdf" checked>
                                <label class="form-check-label" for="formatPDF">PDF</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="format" id="formatExcel" value="excel">
                                <label class="form-check-label" for="formatExcel">Excel</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="format" id="formatCSV" value="csv">
                                <label class="form-check-label" for="formatCSV">CSV</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="generateCustomReport()">Generate Report</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Sidebar toggle
document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
    const sidebar = document.getElementById('sidebar');
    const mainWrapper = document.getElementById('main-wrapper');
    sidebar?.classList.toggle('collapsed');
    mainWrapper?.classList.toggle('sidebar-collapsed');
});

// Prepare chart data from PHP
const programmeLabels = <?= json_encode(array_column($programmeData, 'label')) ?>;
const programmeValues = <?= json_encode(array_column($programmeData, 'value')) ?>;
const deptLabels = <?= json_encode(array_column($deptData, 'dept')) ?>;
const deptRates = <?= json_encode(array_column($deptData, 'rate')) ?>;

// Monthly trends (static for now – could be dynamic with time-series query)
const monthlyApps = [120, 145, 168, 189, 201, 234, 256, 278, 245, 267, 289, 312];
const monthlyApproved = [89, 112, 134, 145, 156, 178, 198, 223, 189, 201, 234, 267];

document.addEventListener('DOMContentLoaded', function() {
    // Application Trends
    const trendsCtx = document.getElementById('applicationTrendsChart').getContext('2d');
    new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Applications',
                data: monthlyApps,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4
            }, {
                label: 'Approved',
                data: monthlyApproved,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true } }
        }
    });

    // Programme Distribution
    const programmeCtx = document.getElementById('programmeChart').getContext('2d');
    new Chart(programmeCtx, {
        type: 'doughnut',
        data: {
            labels: programmeLabels,
            datasets: [{
                data: programmeValues,
                backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6c757d']
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // Department Performance
    const departmentCtx = document.getElementById('departmentChart').getContext('2d');
    new Chart(departmentCtx, {
        type: 'bar',
        data: {
            labels: deptLabels,
            datasets: [{
                label: 'Approval Rate (%)',
                data: deptRates,
                backgroundColor: deptLabels.map((_, i) => 
                    ['rgba(13, 110, 253, 0.8)', 'rgba(25, 135, 84, 0.8)', 'rgba(255, 193, 7, 0.8)', 'rgba(220, 53, 69, 0.8)'][i % 4]
                )
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true, max: 100 } }
        }
    });
});

// === JS Functions (unchanged) ===
function generateReport() {
    const modal = new bootstrap.Modal(document.getElementById('reportModal'));
    modal.show();
}
function generateCustomReport() {
    const form = document.getElementById('reportForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    const reportData = {
        type: document.getElementById('reportType').value,
        dateRange: document.getElementById('reportDateRange').value,
        startDate: document.getElementById('startDate').value,
        endDate: document.getElementById('endDate').value,
        programme: document.getElementById('programmeFilter').value,
        department: document.getElementById('departmentFilter').value,
        format: document.querySelector('input[name="format"]:checked').value
    };
    console.log('Generating report:', reportData);
    alert('Report generation started. You will be notified when it\'s ready.');
    bootstrap.Modal.getInstance(document.getElementById('reportModal')).hide();
}
function exportReport() {
    alert('Exporting current report...');
}
function viewReport(reportId) {
    alert(`Viewing report: ${reportId}`);
}
function setDateRange(range) {
    const show = range === 'custom';
    document.getElementById('startDateContainer').style.display = show ? 'block' : 'none';
    document.getElementById('endDateContainer').style.display = show ? 'block' : 'none';
    if (!show) alert(`Date range set to: ${range}`);
}
document.getElementById('reportDateRange')?.addEventListener('change', function() {
    setDateRange(this.value);
});
</script>

</body>
</html>