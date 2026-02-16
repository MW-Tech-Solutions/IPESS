<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'REVIEWER') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'Assigned Applications';
$pageSubtitle = 'Manage applications assigned to your review queue.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Assigned Applications</h1>
        <p class="panel-muted">Review and submit feedback for your active assignments.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" onclick="refreshAssignments()"><i class="fas fa-sync me-2"></i>Refresh</button>
        <button class="btn btn-primary" onclick="bulkApprove()"><i class="fas fa-check me-2"></i>Bulk Approve</button>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-folder-open"></i></div>
        <div>
            <div class="stat-title">Total Assigned</div>
            <div class="stat-value">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-title">Pending</div>
            <div class="stat-value">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        <div>
            <div class="stat-title">In Progress</div>
            <div class="stat-value">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-title">Completed</div>
            <div class="stat-value">0</div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Active Review Queue</h3>
            <div class="panel-muted">Applications awaiting your evaluation.</div>
        </div>
        <div class="input-group" style="max-width: 260px;">
            <input type="text" class="form-control" id="searchAssignments" placeholder="Search...">
            <button class="btn btn-outline-primary" type="button"><i class="fas fa-search"></i></button>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Application</th>
                        <th>Applicant</th>
                        <th>Programme</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="assignmentsTable"></tbody>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="applicationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Application Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Application Code</div>
                        <div class="fw-bold" id="appCodeView">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Applicant</div>
                        <div class="fw-bold" id="applicantView">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Programme</div>
                        <div class="fw-bold" id="programmeView">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Status</div>
                        <div class="fw-bold" id="statusView">-</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Reviewer Notes</div>
                        <div class="fw-bold" id="remarksView">-</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="feedbackModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Submit Feedback</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="feedbackAssignmentId">
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="feedbackStatus">
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Complete">Complete</option>
                        <option value="Revision Required">Revision Required</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Score</label>
                    <input type="number" class="form-control" id="feedbackScore" min="0" max="100">
                </div>
                <div class="mb-3">
                    <label class="form-label">Remarks</label>
                    <textarea class="form-control" id="feedbackRemarks" rows="3" placeholder="Enter review notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="submitFeedback()">Submit</button>
            </div>
        </div>
    </div>
</div>

<script>
let assignments = [];

document.addEventListener('DOMContentLoaded', () => {
    loadAssignments();
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', () => {
            document.querySelectorAll('.app-checkbox').forEach(cb => cb.checked = selectAll.checked);
        });
    }
    const search = document.getElementById('searchAssignments');
    if (search) {
        search.addEventListener('input', () => {
            const term = search.value.toLowerCase();
            renderAssignments(assignments.filter(item => {
                const text = `${item.application_code} ${item.applicant_name} ${item.programme}`.toLowerCase();
                return text.includes(term);
            }));
        });
    }
});

async function loadAssignments() {
    const res = await fetch('api/assignments.php?action=list');
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to load assignments.');
        return;
    }
    assignments = data.data || [];
    renderAssignments(assignments);
    updateStats(assignments);
}

function renderAssignments(list) {
    const body = document.getElementById('assignmentsTable');
    if (!body) return;
    if (list.length === 0) {
        body.innerHTML = '<tr><td colspan="7" class="text-muted">No data yet.</td></tr>';
        return;
    }
    body.innerHTML = list.map(item => `
        <tr>
            <td><input type="checkbox" class="app-checkbox" value="${item.assignment_id}"></td>
            <td>${item.application_code}</td>
            <td>${item.applicant_name}</td>
            <td>${item.programme || '-'}</td>
            <td><span class="status-chip ${statusChipClass(item.status)}">${item.status}</span></td>
            <td>${item.due_date || '-'}</td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-primary" onclick="viewApplication(${item.assignment_id})"><i class="fas fa-eye"></i></button>
                <button class="btn btn-sm btn-outline-primary" onclick="addFeedback(${item.assignment_id})"><i class="fas fa-comment"></i></button>
            </td>
        </tr>
    `).join('');
}

function updateStats(list) {
    const total = list.length;
    const pending = list.filter(item => item.status === 'Pending').length;
    const inProgress = list.filter(item => item.status === 'In Progress').length;
    const completed = list.filter(item => item.status === 'Complete').length;
    const values = document.querySelectorAll('.stat-value');
    if (values.length >= 4) {
        values[0].textContent = total;
        values[1].textContent = pending;
        values[2].textContent = inProgress;
        values[3].textContent = completed;
    }
}

function statusChipClass(status) {
    const value = (status || '').toLowerCase();
    if (value === 'pending') return 'status-warning';
    if (value === 'in progress') return 'status-muted';
    if (value === 'complete') return 'status-success';
    if (value === 'revision required') return 'status-danger';
    return 'status-muted';
}

function getAssignment(id) {
    return assignments.find(item => Number(item.assignment_id) === Number(id));
}

function viewApplication(id) {
    const item = getAssignment(id);
    if (!item) return;
    document.getElementById('appCodeView').textContent = item.application_code;
    document.getElementById('applicantView').textContent = item.applicant_name;
    document.getElementById('programmeView').textContent = item.programme || '-';
    document.getElementById('statusView').textContent = item.status || '-';
    document.getElementById('remarksView').textContent = item.remarks || '-';
    new bootstrap.Modal(document.getElementById('applicationModal')).show();
}

function addFeedback(id) {
    document.getElementById('feedbackAssignmentId').value = id;
    document.getElementById('feedbackStatus').value = 'Pending';
    document.getElementById('feedbackScore').value = '';
    document.getElementById('feedbackRemarks').value = '';
    new bootstrap.Modal(document.getElementById('feedbackModal')).show();
}

async function submitFeedback() {
    const assignmentId = document.getElementById('feedbackAssignmentId').value;
    const status = document.getElementById('feedbackStatus').value;
    const score = document.getElementById('feedbackScore').value;
    const remarks = document.getElementById('feedbackRemarks').value;

    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('assignment_id', assignmentId);
    formData.append('status', status);
    formData.append('score', score);
    formData.append('remarks', remarks);
    const res = await fetch('api/assignments.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to submit feedback.');
        return;
    }
    bootstrap.Modal.getInstance(document.getElementById('feedbackModal')).hide();
    loadAssignments();
}

function refreshAssignments() { loadAssignments(); }

async function bulkApprove() {
    const ids = Array.from(document.querySelectorAll('.app-checkbox:checked')).map(cb => cb.value);
    if (ids.length === 0) {
        alert('Select at least one application.');
        return;
    }
    const formData = new FormData();
    formData.append('action', 'bulk');
    ids.forEach(id => formData.append('assignment_ids[]', id));
    formData.append('status', 'Complete');
    const res = await fetch('api/assignments.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Bulk approve failed.');
        return;
    }
    loadAssignments();
}
</script>

<?php require_once 'includes/footer.php'; ?>
