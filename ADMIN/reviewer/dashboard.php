<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'REVIEWER') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'Reviewer Dashboard';
$pageSubtitle = 'Track assigned applications, review workload, and pending feedback.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Review Command Center</h1>
        <p class="panel-muted">Daily snapshot of review tasks, pending feedback, and decision status.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" onclick="location.reload()"><i class="fas fa-sync me-2"></i>Refresh</button>
        <a class="btn btn-primary" href="assigned-applications.php"><i class="fas fa-folder-open me-2"></i>Assigned Applications</a>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-folder-open"></i></div>
        <div>
            <div class="stat-title">Assigned Reviews</div>
            <div class="stat-value" id="reviewer-total">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-title">Pending Feedback</div>
            <div class="stat-value" id="reviewer-pending">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-title">Completed Reviews</div>
            <div class="stat-value" id="reviewer-complete">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div>
            <div class="stat-title">Escalations</div>
            <div class="stat-value" id="reviewer-escalations">0</div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Today?s Review Queue</h3>
            <div class="panel-muted">Applications awaiting your assessment.</div>
        </div>
        <button class="btn btn-outline-primary btn-sm" onclick="openFilters()"><i class="fas fa-filter"></i> Filter</button>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Application</th>
                        <th>Applicant</th>
                        <th>Programme</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="reviewerAssignments"></tbody>
            </table>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Reviewer Activity</h3>
            <div class="panel-muted">Recent feedback entries and escalations.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="activity-list" id="reviewerActivity">
            <div class="text-muted">No data yet.</div>
        </div>
    </div>
</section>

<script>
function viewApplication(id) { window.location.href = `assigned-applications.php`; }
function addFeedback(id) { window.location.href = `feedback-management.php`; }
function openFilters() { window.location.href = `assigned-applications.php`; }

async function loadReviewerDashboard() {
    const assignmentsRes = await fetch('api/assignments.php?action=list');
    const assignmentsData = await assignmentsRes.json();
    const assignments = assignmentsData.success ? assignmentsData.data : [];

    const total = assignments.length;
    const pending = assignments.filter(item => item.status === 'Pending').length;
    const complete = assignments.filter(item => item.status === 'Complete').length;
    const escalations = assignments.filter(item => (item.status || '').toLowerCase().includes('escalated')).length;

    document.getElementById('reviewer-total').textContent = total;
    document.getElementById('reviewer-pending').textContent = pending;
    document.getElementById('reviewer-complete').textContent = complete;
    document.getElementById('reviewer-escalations').textContent = escalations;

    const body = document.getElementById('reviewerAssignments');
    if (assignments.length === 0) {
        body.innerHTML = '<tr><td colspan="6" class="text-muted">No data yet.</td></tr>';
    } else {
        body.innerHTML = assignments.map(item => `
            <tr>
                <td>${item.application_code}</td>
                <td>${item.applicant_name}</td>
                <td>${item.programme || '-'}</td>
                <td><span class="status-chip ${statusChipClass(item.status)}">${item.status}</span></td>
                <td>${item.due_date || '-'}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewApplication('${item.application_code}')"><i class="fas fa-eye"></i></button>
                    <button class="btn btn-sm btn-outline-primary" onclick="addFeedback('${item.application_code}')"><i class="fas fa-comment"></i></button>
                </td>
            </tr>
        `).join('');
    }

    const feedbackRes = await fetch('api/feedback.php?action=list');
    const feedbackData = await feedbackRes.json();
    const feedback = feedbackData.success ? feedbackData.data : [];
    const activity = document.getElementById('reviewerActivity');
    if (feedback.length === 0) {
        activity.innerHTML = '<div class="text-muted">No data yet.</div>';
    } else {
        activity.innerHTML = feedback.slice(0, 3).map(item => `
            <div class="activity-item">
                <div class="activity-icon"><i class="fas fa-comment"></i></div>
                <div>
                    <p class="mb-1">Feedback submitted for ${item.application_code}</p>
                    <div class="activity-meta">${new Date(item.updated_at).toLocaleDateString()}</div>
                </div>
            </div>
        `).join('');
    }
}

function statusChipClass(status) {
    const value = (status || '').toLowerCase();
    if (value === 'pending') return 'status-warning';
    if (value === 'in progress') return 'status-muted';
    if (value === 'complete') return 'status-success';
    return 'status-muted';
}

document.addEventListener('DOMContentLoaded', loadReviewerDashboard);
</script>

<?php require_once 'includes/footer.php'; ?>
