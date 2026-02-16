<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SUPERVISOR') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'Supervisor Dashboard';
$pageSubtitle = 'Track student progress, approvals, and upcoming milestones.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Supervision Overview</h1>
        <p class="panel-muted">Quick snapshot of student chapters, pending approvals, and milestone alerts.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" onclick="location.reload()"><i class="fas fa-sync me-2"></i>Refresh</button>
        <a class="btn btn-primary" href="my-students.php"><i class="fas fa-users me-2"></i>My Students</a>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div>
            <div class="stat-title">Active Students</div>
            <div class="stat-value" id="supervisor-total">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
        <div>
            <div class="stat-title">Chapters Pending</div>
            <div class="stat-value" id="supervisor-pending">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-title">Approved Chapters</div>
            <div class="stat-value" id="supervisor-approved">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-flag-checkered"></i></div>
        <div>
            <div class="stat-title">Upcoming Milestones</div>
            <div class="stat-value" id="supervisor-deadlines">0</div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">My Students</h3>
            <div class="panel-muted">Recent submissions awaiting feedback.</div>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="progress-tracking.php"><i class="fas fa-chart-line"></i> View Progress</a>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Programme</th>
                        <th>Current Chapter</th>
                        <th>Status</th>
                        <th>Last Submission</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="supervisorStudents"></tbody>
            </table>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Milestone Highlights</h3>
            <div class="panel-muted">Upcoming deadlines and departmental checks.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="activity-list" id="supervisorActivity">
            <div class="text-muted">No data yet.</div>
        </div>
    </div>
</section>

<script>
function viewSubmission(id) { window.location.href = 'progress-tracking.php'; }
function provideFeedback(id) { window.location.href = 'my-students.php'; }

async function loadSupervisorDashboard() {
    const studentsRes = await fetch('api/students.php?action=list');
    const studentsData = await studentsRes.json();
    const students = studentsData.success ? studentsData.data : [];

    document.getElementById('supervisor-total').textContent = students.length;
    document.getElementById('supervisor-pending').textContent = students.filter(item => item.status === 'Pending Review').length;
    document.getElementById('supervisor-approved').textContent = students.filter(item => item.status === 'Approved').length;

    const milestonesRes = await fetch('api/milestones.php?action=list');
    const milestonesData = await milestonesRes.json();
    const milestones = milestonesData.success ? milestonesData.data : [];
    document.getElementById('supervisor-deadlines').textContent = milestones.length;

    const body = document.getElementById('supervisorStudents');
    if (students.length === 0) {
        body.innerHTML = '<tr><td colspan="6" class="text-muted">No data yet.</td></tr>';
    } else {
        body.innerHTML = students.map(item => `
            <tr>
                <td>${item.full_name}</td>
                <td>${item.programme || '-'}</td>
                <td>${item.current_chapter || '-'}</td>
                <td><span class="status-chip ${statusChipClass(item.status)}">${item.status}</span></td>
                <td>${item.last_submission || '-'}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewSubmission('${item.student_id}')"><i class="fas fa-eye"></i></button>
                    <button class="btn btn-sm btn-outline-primary" onclick="provideFeedback('${item.student_id}')"><i class="fas fa-comment"></i></button>
                </td>
            </tr>
        `).join('');
    }

    const activity = document.getElementById('supervisorActivity');
    if (milestones.length === 0) {
        activity.innerHTML = '<div class="text-muted">No data yet.</div>';
    } else {
        activity.innerHTML = milestones.slice(0, 3).map(item => `
            <div class="activity-item">
                <div class="activity-icon"><i class="fas fa-flag-checkered"></i></div>
                <div>
                    <p class="mb-1">${item.title} for ${item.student_name}</p>
                    <div class="activity-meta">${item.due_date || '-'}</div>
                </div>
            </div>
        `).join('');
    }
}

function statusChipClass(status) {
    const value = (status || '').toLowerCase();
    if (value === 'approved') return 'status-success';
    if (value === 'awaiting revision') return 'status-muted';
    return 'status-warning';
}

document.addEventListener('DOMContentLoaded', loadSupervisorDashboard);
</script>

<?php require_once 'includes/footer.php'; ?>
