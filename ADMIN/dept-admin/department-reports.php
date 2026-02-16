<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'DEPARTMENT_ADMIN') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'Department Reports';
$pageSubtitle = 'Generate, export, and schedule departmental analytics.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Department Reports</h1>
        <p class="panel-muted">Generate analytics, monitor progress, and share insights.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" onclick="refreshReports()"><i class="fas fa-sync me-2"></i>Refresh</button>
        <button class="btn btn-primary" onclick="generateReport()"><i class="fas fa-plus me-2"></i>Generate Report</button>
        <div class="dropdown">
            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-calendar me-2"></i>Date Range
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="setDateRange('today')">Today</a></li>
                <li><a class="dropdown-item" href="#" onclick="setDateRange('week')">This Week</a></li>
                <li><a class="dropdown-item" href="#" onclick="setDateRange('month')">This Month</a></li>
                <li><a class="dropdown-item" href="#" onclick="setDateRange('quarter')">This Quarter</a></li>
                <li><a class="dropdown-item" href="#" onclick="setDateRange('year')">This Year</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="setCustomDateRange()">Custom Range</a></li>
            </ul>
        </div>
        <button class="btn btn-info" onclick="exportReports()"><i class="fas fa-download me-2"></i>Export</button>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
        <div>
            <div class="stat-title">Reports Generated</div>
            <div class="stat-value" id="total-reports">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div>
            <div class="stat-title">Active Students</div>
            <div class="stat-value" id="active-students">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
        <div>
            <div class="stat-title">Completion Rate</div>
            <div class="stat-value" id="completion-rate">0%</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-title">Avg Completion</div>
            <div class="stat-value" id="avg-completion">0 yrs</div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Performance Insights</h3>
            <div class="panel-muted">Student progress, programme mix, and supervisor performance.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="border rounded-4 p-3 bg-white">
                    <h6 class="mb-3">Student Progress Overview</h6>
                    <canvas id="progressChart" height="250"></canvas>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="border rounded-4 p-3 bg-white">
                    <h6 class="mb-3">Programme Distribution</h6>
                    <canvas id="programmeChart" height="250"></canvas>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-2">
            <div class="col-lg-8">
                <div class="border rounded-4 p-3 bg-white">
                    <h6 class="mb-3">Supervisor Performance</h6>
                    <canvas id="supervisorChart" height="200"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="border rounded-4 p-3 bg-white h-100">
                    <h6 class="mb-3">Key Metrics</h6>
                    <div class="metric-item">
                        <div class="d-flex justify-content-between">
                            <span>Student Satisfaction</span>
                            <span class="fw-bold text-success" id="metric-satisfaction">0%</span>
                        </div>
                        <div class="progress mt-1" style="height: 6px;"><div class="progress-bar bg-success" id="metric-satisfaction-bar" style="width: 0%"></div></div>
                    </div>
                    <div class="metric-item mt-3">
                        <div class="d-flex justify-content-between">
                            <span>Supervisor Utilization</span>
                            <span class="fw-bold text-info" id="metric-utilization">0%</span>
                        </div>
                        <div class="progress mt-1" style="height: 6px;"><div class="progress-bar bg-info" id="metric-utilization-bar" style="width: 0%"></div></div>
                    </div>
                    <div class="metric-item mt-3">
                        <div class="d-flex justify-content-between">
                            <span>On-time Completions</span>
                            <span class="fw-bold text-warning" id="metric-ontime">0%</span>
                        </div>
                        <div class="progress mt-1" style="height: 6px;"><div class="progress-bar bg-warning" id="metric-ontime-bar" style="width: 0%"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Generated Reports</h3>
            <div class="panel-muted">Recent exports and scheduled documents.</div>
        </div>
        <div class="input-group" style="max-width: 260px;">
            <input type="text" class="form-control" id="searchReports" placeholder="Search reports...">
            <button class="btn btn-outline-primary" type="button"><i class="fas fa-search"></i></button>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Report ID</th>
                        <th>Report Type</th>
                        <th>Title</th>
                        <th>Generated By</th>
                        <th>Format</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="reportsTable"></tbody>
            </table>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Quick Report Generation</h3>
            <div class="panel-muted">Generate standard reports in one click.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-3">
                <div class="report-card text-center p-3 border rounded" onclick="generateQuickReport('progress')">
                    <div class="report-icon mb-2"><i class="fas fa-chart-line fa-2x text-primary"></i></div>
                    <h6>Progress Report</h6>
                    <small class="text-muted">Student progress overview</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="report-card text-center p-3 border rounded" onclick="generateQuickReport('supervisor')">
                    <div class="report-icon mb-2"><i class="fas fa-users fa-2x text-success"></i></div>
                    <h6>Supervisor Report</h6>
                    <small class="text-muted">Supervisor performance</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="report-card text-center p-3 border rounded" onclick="generateQuickReport('enrollment')">
                    <div class="report-icon mb-2"><i class="fas fa-user-plus fa-2x text-info"></i></div>
                    <h6>Enrollment Report</h6>
                    <small class="text-muted">New student enrollments</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="report-card text-center p-3 border rounded" onclick="generateQuickReport('completion')">
                    <div class="report-icon mb-2"><i class="fas fa-graduation-cap fa-2x text-warning"></i></div>
                    <h6>Completion Report</h6>
                    <small class="text-muted">Graduation statistics</small>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Scheduled Reports</h3>
            <div class="panel-muted">Automated reports delivery.</div>
        </div>
        <button class="btn btn-outline-primary btn-sm" onclick="scheduleReport()"><i class="fas fa-calendar-plus"></i> Schedule Report</button>
    </div>
    <div class="panel-body">
        <div class="scheduled-reports" id="scheduledReports"></div>
    </div>
</section>

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
                                <option value="progress">Progress Report</option>
                                <option value="supervisor">Supervisor Report</option>
                                <option value="enrollment">Enrollment Report</option>
                                <option value="completion">Completion Report</option>
                                <option value="analytics">Analytics Report</option>
                                <option value="custom">Custom Report</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Report Title</label>
                            <input type="text" class="form-control" id="reportTitle" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="startDate" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" id="endDate" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Format</label>
                            <select class="form-select" id="format" required>
                                <option value="pdf">PDF</option>
                                <option value="excel">Excel</option>
                                <option value="csv">CSV</option>
                                <option value="html">HTML</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Recipients</label>
                            <input type="text" class="form-control" id="recipients" placeholder="Email addresses (comma separated)">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Additional Filters</label>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="includeCharts">
                                        <label class="form-check-label" for="includeCharts">Include Charts</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="includeDetails">
                                        <label class="form-check-label" for="includeDetails">Include Details</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="includeSummary">
                                        <label class="form-check-label" for="includeSummary">Include Summary</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="description" rows="3" placeholder="Report description..."></textarea>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let reports = [];
let schedules = [];
let progressChart = null;
let programmeChart = null;
let supervisorChart = null;

document.addEventListener('DOMContentLoaded', function() {
    initCharts();
    loadReports();
    loadSchedules();
    loadMetrics();
    const searchReports = document.getElementById('searchReports');
    if (searchReports) {
        searchReports.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            renderReports(reports.filter(item => {
                const text = `${item.report_title} ${item.report_type}`.toLowerCase();
                return text.includes(searchTerm);
            }));
        });
    }
});

function loadSchedules() {
    const stored = localStorage.getItem('deptReportSchedules');
    schedules = stored ? JSON.parse(stored) : [];
    renderSchedules();
}

function renderSchedules() {
    const container = document.getElementById('scheduledReports');
    if (!container) return;
    if (schedules.length === 0) {
        container.innerHTML = '<div class="text-muted">No scheduled reports yet.</div>';
        return;
    }
    container.innerHTML = schedules.map(item => `
        <div class="scheduled-report-item">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1">${item.title}</h6>
                    <small class="text-muted">Generated ${item.cadence}</small>
                </div>
                <div class="text-end">
                    <span class="status-chip status-success mb-1">${item.status}</span>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary" onclick="editSchedule('${item.id}')"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-outline-primary" onclick="deleteSchedule('${item.id}')"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function initCharts() {
    const progressCtx = document.getElementById('progressChart').getContext('2d');
    progressChart = new Chart(progressCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Average Progress (%)',
                data: [],
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4
            }]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true, max: 100 } } }
    });

    const programmeCtx = document.getElementById('programmeChart').getContext('2d');
    programmeChart = new Chart(programmeCtx, {
        type: 'doughnut',
        data: {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: ['#0d6efd', '#198754', '#dc3545', '#ffc107']
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });

    const supervisorCtx = document.getElementById('supervisorChart').getContext('2d');
    supervisorChart = new Chart(supervisorCtx, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'Students Supervised',
                data: [],
                backgroundColor: '#198754'
            }, {
                label: 'Completed This Year',
                data: [],
                backgroundColor: '#0d6efd'
            }]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });
}

async function loadMetrics() {
    const [studentsRes, supervisorsRes] = await Promise.all([
        fetch('api/students.php?action=list'),
        fetch('api/supervisors.php?action=list')
    ]);
    const studentsData = await studentsRes.json();
    const supervisorsData = await supervisorsRes.json();
    const students = studentsData.success ? studentsData.data : [];
    const supervisors = supervisorsData.success ? supervisorsData.data : [];

    const active = students.filter(item => (item.status || '').toLowerCase() === 'active').length;
    const graduated = students.filter(item => (item.status || '').toLowerCase() === 'graduated').length;
    const completionRate = students.length > 0 ? Math.round((graduated / students.length) * 100) : 0;

    document.getElementById('active-students').textContent = active;
    document.getElementById('completion-rate').textContent = `${completionRate}%`;
    document.getElementById('avg-completion').textContent = students.length ? '0 yrs' : '0 yrs';

    document.getElementById('metric-satisfaction').textContent = `${students.length ? 0 : 0}%`;
    document.getElementById('metric-satisfaction-bar').style.width = `${students.length ? 0 : 0}%`;
    const utilization = supervisors.length ? Math.round((supervisors.reduce((sum, item) => sum + Number(item.current_students || 0), 0) / supervisors.length)) : 0;
    document.getElementById('metric-utilization').textContent = `${utilization}%`;
    document.getElementById('metric-utilization-bar').style.width = `${utilization}%`;
    document.getElementById('metric-ontime').textContent = `${graduated ? completionRate : 0}%`;
    document.getElementById('metric-ontime-bar').style.width = `${graduated ? completionRate : 0}%`;

    const programmeCounts = {};
    students.forEach(item => {
        const key = item.programme || 'Unspecified';
        programmeCounts[key] = (programmeCounts[key] || 0) + 1;
    });
    const programmeLabels = Object.keys(programmeCounts);
    const programmeValues = Object.values(programmeCounts);
    programmeChart.data.labels = programmeLabels;
    programmeChart.data.datasets[0].data = programmeValues;
    programmeChart.update();

    const progressBuckets = [0, 0, 0, 0];
    students.forEach(item => {
        const progress = Number(item.progress_pct || 0);
        if (progress <= 25) progressBuckets[0] += 1;
        else if (progress <= 50) progressBuckets[1] += 1;
        else if (progress <= 75) progressBuckets[2] += 1;
        else progressBuckets[3] += 1;
    });
    progressChart.data.labels = ['0-25%', '26-50%', '51-75%', '76-100%'];
    progressChart.data.datasets[0].data = progressBuckets;
    progressChart.update();

    supervisorChart.data.labels = supervisors.map(item => item.full_name || 'Supervisor');
    supervisorChart.data.datasets[0].data = supervisors.map(item => Number(item.current_students || 0));
    supervisorChart.data.datasets[1].data = supervisors.map(() => 0);
    supervisorChart.update();
}

async function loadReports() {
    const res = await fetch('api/reports.php?action=list');
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to load reports.');
        return;
    }
    reports = data.data || [];
    renderReports(reports);
    const total = document.getElementById('total-reports');
    if (total) total.textContent = reports.length;
}

function renderReports(list) {
    const body = document.getElementById('reportsTable');
    if (!body) return;
    if (list.length === 0) {
        body.innerHTML = '<tr><td colspan="7" class="text-muted">No reports generated yet.</td></tr>';
        return;
    }
    body.innerHTML = list.map(item => `
        <tr>
            <td>RPT-${item.report_id}</td>
            <td><span class="status-chip status-success">${item.report_type}</span></td>
            <td>${item.report_title}</td>
            <td>Dept Admin</td>
            <td>${item.format || 'PDF'}</td>
            <td>${new Date(item.created_at).toLocaleDateString()}</td>
            <td><span class="status-chip status-success">${item.status || 'Ready'}</span></td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewReport(${item.report_id})"><i class="fas fa-eye"></i></button>
                <button class="btn btn-sm btn-outline-primary" onclick="downloadReport(${item.report_id})"><i class="fas fa-download"></i></button>
                <button class="btn btn-sm btn-outline-primary" onclick="shareReport('${item.report_title}')"><i class="fas fa-share"></i></button>
            </td>
        </tr>
    `).join('');
}

function viewReport(reportId) {
    window.open(`report-file.php?id=${reportId}&mode=view`, '_blank');
}

function downloadReport(reportId) {
    window.location.href = `report-file.php?id=${reportId}&mode=download`;
}

function shareReport(title) {
    const mailto = `mailto:?subject=${encodeURIComponent('Department Report')}&body=${encodeURIComponent(`Please review the report: ${title}`)}`;
    window.location.href = mailto;
}

async function generateReportRequest(payload) {
    const formData = new FormData();
    formData.append('action', 'generate');
    formData.append('report_title', payload.title);
    formData.append('report_type', payload.type);
    formData.append('format', payload.format);
    const res = await fetch('api/reports.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Report generation failed.');
        return false;
    }
    await loadReports();
    return true;
}

function generateQuickReport(type) {
    const titles = {
        'progress': 'Student Progress Report',
        'supervisor': 'Supervisor Performance Report',
        'enrollment': 'Enrollment Statistics Report',
        'completion': 'Completion Rate Report'
    };
    generateReportRequest({ title: titles[type], type: titles[type], format: 'PDF' });
}

function generateReport() {
    document.getElementById('reportForm').reset();
    const modal = new bootstrap.Modal(document.getElementById('reportModal'));
    modal.show();
}

async function generateCustomReport() {
    const form = document.getElementById('reportForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    const payload = {
        title: document.getElementById('reportTitle').value,
        type: document.getElementById('reportType').selectedOptions[0].textContent,
        format: document.getElementById('format').value
    };
    const ok = await generateReportRequest(payload);
    if (ok) {
        bootstrap.Modal.getInstance(document.getElementById('reportModal')).hide();
    }
}

function scheduleReport() {
    const title = prompt('Enter report title:');
    if (!title) return;
    const cadence = prompt('Enter cadence (e.g., every Monday):');
    if (!cadence) return;
    const id = `SCH-${Date.now()}`;
    schedules.push({ id, title, cadence, status: 'Active' });
    localStorage.setItem('deptReportSchedules', JSON.stringify(schedules));
    renderSchedules();
}

function editSchedule(scheduleId) {
    const schedule = schedules.find(item => item.id === scheduleId);
    if (!schedule) return;
    const cadence = prompt('Update cadence:', schedule.cadence);
    if (!cadence) return;
    schedule.cadence = cadence;
    localStorage.setItem('deptReportSchedules', JSON.stringify(schedules));
    renderSchedules();
}

function deleteSchedule(scheduleId) {
    schedules = schedules.filter(item => item.id !== scheduleId);
    localStorage.setItem('deptReportSchedules', JSON.stringify(schedules));
    renderSchedules();
}

function refreshReports() { loadReports(); }
function setDateRange(range) {
    document.body.setAttribute('data-report-range', range);
}

function setCustomDateRange() {
    const start = prompt('Start date (YYYY-MM-DD):');
    const end = prompt('End date (YYYY-MM-DD):');
    if (start && end) {
        document.body.setAttribute('data-report-range', `${start} to ${end}`);
    }
}
function exportReports() { generateReportRequest({ title: 'Department Export', type: 'Export', format: 'CSV' }); }

const style = document.createElement('style');
style.textContent = `
    .report-card { cursor: pointer; transition: all 0.3s ease; }
    .report-card:hover { background-color: #f8f9fa; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
    .scheduled-report-item { padding: 1rem; border: 1px solid #e9ecef; border-radius: 0.75rem; margin-bottom: 0.5rem; }
    .metric-item { margin-bottom: 1rem; }
`;
if (!document.querySelector('style[data-app="dept-reports"]')) {
    style.setAttribute('data-app', 'dept-reports');
    document.head.appendChild(style);
}
</script>

<?php require_once 'includes/footer.php'; ?>
