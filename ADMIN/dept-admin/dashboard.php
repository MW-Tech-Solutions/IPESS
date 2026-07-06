<?php
$pageTitle = 'Program Dashboard';
$pageSubtitle = 'Track program-wide applications, supervisors, and student performance.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Program Command View</h1>
        <p class="panel-muted">Quick insight into admissions, supervision load, and student activity.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" onclick="location.reload()">Refresh</button>
        <a class="btn btn-primary" href="department-reports.php">View Reports</a>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
        <div>
            <div class="stat-title">Applications</div>
            <div class="stat-value" id="dept-applications-count">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
        <div>
            <div class="stat-title">Active Supervisors</div>
            <div class="stat-value" id="dept-supervisors-count">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div>
            <div class="stat-title">Enrolled Students</div>
            <div class="stat-value" id="dept-students-count">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div>
            <div class="stat-title">Pending Issues</div>
            <div class="stat-value" id="dept-issues-count">0</div>
        </div>
    </div>
</section>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Application Trends by Programme</h6>
                        <span class="text-muted small">This Semester</span>
                    </div>
                    <div class="text-muted" style="min-height:200px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:0.5rem;">
                        <i class="fas fa-chart-bar fa-2x"></i>
                        <span>No data yet</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="border rounded-4 p-4 h-100 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Supervisor Workload</h6>
                        <span class="text-muted small">Current Cycle</span>
                    </div>
                    <div class="text-muted" style="min-height:200px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:0.5rem;">
                        <i class="fas fa-chart-pie fa-2x"></i>
                        <span>No data yet</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Program Activity</h3>
            <div class="panel-muted">Latest actions from coordinators and reviewers.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="activity-list" id="dept-activity-list">
            <div class="text-muted">No data yet.</div>
        </div>
    </div>
</section>

<script>
async function loadDeptDashboard() {
    const [appsRes, supervisorsRes, studentsRes] = await Promise.all([
        fetch('api/applications.php?action=list'),
        fetch('api/supervisors.php?action=list'),
        fetch('api/students.php?action=list')
    ]);

    const apps = await appsRes.json();
    const supervisors = await supervisorsRes.json();
    const students = await studentsRes.json();

    const appsCount = (apps.success ? apps.data.length : 0);
    const supervisorsCount = (supervisors.success ? supervisors.data.filter(item => (item.status || '').toLowerCase() === 'active').length : 0);
    const studentsCount = (students.success ? students.data.length : 0);

    document.getElementById('dept-applications-count').textContent = appsCount;
    document.getElementById('dept-supervisors-count').textContent = supervisorsCount;
    document.getElementById('dept-students-count').textContent = studentsCount;
    document.getElementById('dept-issues-count').textContent = 0;
}

document.addEventListener('DOMContentLoaded', loadDeptDashboard);
</script>

<?php require_once 'includes/footer.php'; ?>
