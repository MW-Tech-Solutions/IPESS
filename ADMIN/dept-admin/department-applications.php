<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'DEPARTMENT_ADMIN') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'Department Applications';
$pageSubtitle = 'Review applications, assign reviewers, and manage approvals.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Department Applications</h1>
        <p class="panel-muted">Monitor the application funnel and coordinate reviewer actions.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" onclick="refreshApplications()"><i class="fas fa-sync me-2"></i>Refresh</button>
        <button class="btn btn-primary" onclick="assignReviewers()"><i class="fas fa-user-plus me-2"></i>Assign Reviewers</button>
        <div class="dropdown">
            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-filter me-2"></i>Filter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="filterByProgramme('all')">All Programmes</a></li>
                <li><a class="dropdown-item" href="#" onclick="filterByProgramme('msc')">M.Sc Programmes</a></li>
                <li><a class="dropdown-item" href="#" onclick="filterByProgramme('phd')">Ph.D Programmes</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="filterByStatus('all')">All Status</a></li>
                <li><a class="dropdown-item" href="#" onclick="filterByStatus('pending')">Pending Review</a></li>
                <li><a class="dropdown-item" href="#" onclick="filterByStatus('under_review')">Under Review</a></li>
                <li><a class="dropdown-item" href="#" onclick="filterByStatus('approved')">Approved</a></li>
            </ul>
        </div>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
        <div>
            <div class="stat-title">Total Applications</div>
            <div class="stat-value" id="dept-total-apps">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-title">Awaiting Review</div>
            <div class="stat-value" id="dept-pending">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-title">Department Approved</div>
            <div class="stat-value" id="dept-approved">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div>
            <div class="stat-title">Assigned Reviewers</div>
            <div class="stat-value" id="assigned-reviewers">0</div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Computer Science Applications</h3>
            <div class="panel-muted">Applications awaiting departmental review.</div>
        </div>
        <div class="input-group" style="max-width: 320px;">
            <input type="text" class="form-control" id="searchInput" placeholder="Search applications...">
            <button class="btn btn-outline-primary" type="button"><i class="fas fa-search"></i></button>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="applicationsTable">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Application ID</th>
                        <th>Applicant Name</th>
                        <th>Programme</th>
                        <th>Status</th>
                        <th>Reviewer</th>
                        <th>Submitted Date</th>
                        <th>Priority</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="applicationsBody"></tbody>
            </table>
        </div>

        <nav aria-label="Applications pagination" class="mt-3">
            <ul class="pagination justify-content-center">
                <li class="page-item disabled"><span class="page-link">Previous</span></li>
                <li class="page-item active"><span class="page-link">1</span></li>
                <li class="page-item"><a class="page-link" href="#">2</a></li>
                <li class="page-item"><a class="page-link" href="#">3</a></li>
                <li class="page-item"><a class="page-link" href="#">Next</a></li>
            </ul>
        </nav>
    </div>
</section>

<section class="panel" id="bulkActionsCard" style="display:none;">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Bulk Actions</h3>
            <div class="panel-muted"><span id="selectedCount">0 applications selected</span></div>
        </div>
    </div>
    <div class="panel-body">
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-outline-primary btn-sm" onclick="bulkAssignReviewers()"><i class="fas fa-users"></i> Bulk Assign Reviewers</button>
            <button class="btn btn-warning btn-sm" onclick="bulkApprove()"><i class="fas fa-check"></i> Bulk Department Approve</button>
            <button class="btn btn-info btn-sm" onclick="bulkSendToReview()"><i class="fas fa-forward"></i> Send to Final Review</button>
            <button class="btn btn-danger btn-sm" onclick="bulkEscalate()"><i class="fas fa-exclamation-triangle"></i> Bulk Escalate</button>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Reviewer Workload Overview</h3>
            <div class="panel-muted">Current assignments and workload distribution.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="text-muted">No data yet.</div>
    </div>
</section>

<div class="modal fade" id="applicationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Application Details - <span id="modalAppId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8">
                        <h6>Applicant Information</h6>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6"><strong>Name:</strong> <span id="modalName"></span></div>
                            <div class="col-md-6"><strong>Email:</strong> <span id="modalEmail"></span></div>
                            <div class="col-md-6"><strong>Programme:</strong> <span id="modalProgramme"></span></div>
                            <div class="col-md-6"><strong>GPA:</strong> <span id="modalGPA"></span></div>
                        </div>

                        <h6>Application Status</h6>
                        <div class="mb-3">
                            <span class="status-chip status-muted" id="modalStatus">No data yet</span>
                            <small class="text-muted ms-2">Department Review Phase</small>
                        </div>

                        <h6>Reviewer Assignment</h6>
                        <div class="mb-3">
                            <span id="modalReviewer">No data yet</span>
                            <button class="btn btn-sm btn-outline-primary ms-2" onclick="changeReviewer()">
                                <i class="fas fa-exchange-alt"></i> Change
                            </button>
                        </div>

                        <h6>Research Proposal Summary</h6>
                        <div class="border p-3 rounded mb-3">
                            <p class="mb-0 small" id="modalProposal">No data yet.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <h6>Quick Actions</h6>
                        <div class="d-grid gap-2">
                            <button class="btn btn-success" onclick="approveApplicationModal()"><i class="fas fa-check"></i> Department Approve</button>
                            <button class="btn btn-danger" onclick="rejectApplicationModal()"><i class="fas fa-times"></i> Reject Application</button>
                            <button class="btn btn-info" onclick="assignReviewerModal()"><i class="fas fa-user-plus"></i> Assign Reviewer</button>
                            <button class="btn btn-warning" onclick="requestMoreInfoModal()"><i class="fas fa-question-circle"></i> Request More Info</button>
                            <button class="btn btn-secondary" onclick="escalateToAdminModal()"><i class="fas fa-exclamation-triangle"></i> Escalate to Admin</button>
                        </div>

                        <h6 class="mt-3">Recent Activity</h6>
                        <div class="activity-timeline">
                            <div class="text-muted">No data yet.</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveChanges()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
// Select all functionality
let applications = [];
const selectAll = document.getElementById('selectAll');
if (selectAll) {
    selectAll.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.app-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateBulkActions();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    loadApplications();
});

// Individual checkbox change
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('app-checkbox')) {
        updateBulkActions();
        updateSelectAllState();
    }
});

function updateBulkActions() {
    const checkedBoxes = document.querySelectorAll('.app-checkbox:checked');
    const bulkActionsCard = document.getElementById('bulkActionsCard');
    const selectedCount = document.getElementById('selectedCount');

    if (!bulkActionsCard || !selectedCount) return;

    if (checkedBoxes.length > 0) {
        bulkActionsCard.style.display = 'block';
        selectedCount.textContent = `${checkedBoxes.length} application${checkedBoxes.length > 1 ? 's' : ''} selected`;
    } else {
        bulkActionsCard.style.display = 'none';
    }
}

function updateSelectAllState() {
    const allCheckboxes = document.querySelectorAll('.app-checkbox');
    const checkedBoxes = document.querySelectorAll('.app-checkbox:checked');
    if (!selectAll) return;

    selectAll.checked = allCheckboxes.length === checkedBoxes.length && allCheckboxes.length > 0;
    selectAll.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < allCheckboxes.length;
}

async function loadApplications() {
    const res = await fetch('api/applications.php?action=list');
    const data = await res.json();
    if (!data.success) {
        alert(data.message || 'Unable to load applications.');
        return;
    }
    applications = data.data || [];
    renderApplications(applications);
    updateStats(applications);
    updateBulkActions();
}

function renderApplications(list) {
    const body = document.getElementById('applicationsBody');
    if (!body) return;
    if (list.length === 0) {
        body.innerHTML = '<tr><td colspan="9" class="text-muted">No data yet.</td></tr>';
        return;
    }
    body.innerHTML = list.map(item => `
        <tr data-app="${item.app_code}" data-applicant="${item.applicant_name}" data-programme="${item.programme}" data-status="${item.status}" data-reviewer="${item.reviewer_name || ''}" data-submitted="${item.submitted_date || ''}" data-priority="${item.priority}">
            <td><input type="checkbox" class="app-checkbox"></td>
            <td>${item.app_code}</td>
            <td>${item.applicant_name}</td>
            <td>${item.programme}</td>
            <td><span class="status-chip ${statusChipClass(item.status)}">${item.status}</span></td>
            <td>${item.reviewer_name || 'Unassigned'}</td>
            <td>${item.submitted_date || '-'}</td>
            <td><span class="status-chip status-success">${item.priority || 'Normal'}</span></td>
            <td>
                <div class="btn-group" role="group">
                    <button class="btn btn-sm btn-outline-primary" onclick="viewApplication('${item.app_code}')" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-primary" onclick="assignReviewer('${item.app_code}')" title="Change Reviewer">
                        <i class="fas fa-user-plus"></i>
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-h"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="approveApplication('${item.app_code}')">Approve for Department</a></li>
                            <li><a class="dropdown-item" href="#" onclick="rejectApplication('${item.app_code}')">Reject Application</a></li>
                            <li><a class="dropdown-item" href="#" onclick="requestMoreInfo('${item.app_code}')">Request More Info</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="escalateToAdmin('${item.app_code}')">Escalate to Admin</a></li>
                        </ul>
                    </div>
                </div>
            </td>
        </tr>
    `).join('');
}

function updateStats(list) {
    const totalEl = document.getElementById('dept-total-apps');
    const pendingEl = document.getElementById('dept-pending');
    const approvedEl = document.getElementById('dept-approved');
    const reviewersEl = document.getElementById('assigned-reviewers');
    if (totalEl) totalEl.textContent = list.length;
    if (pendingEl) pendingEl.textContent = list.filter(item => (item.status || '').toLowerCase().includes('pending')).length;
    if (approvedEl) approvedEl.textContent = list.filter(item => (item.status || '').toLowerCase().includes('approved')).length;
    if (reviewersEl) reviewersEl.textContent = list.filter(item => item.reviewer_name).length;
}

function statusChipClass(status) {
    const value = (status || '').toLowerCase();
    if (value.includes('approved')) return 'status-success';
    if (value.includes('pending')) return 'status-warning';
    if (value.includes('review')) return 'status-warning';
    if (value.includes('rejected')) return 'status-danger';
    return 'status-muted';
}

// Application actions
function viewApplication(appId) {
    window.open(`/ADMIN/view.php?app_no=${encodeURIComponent(appId)}`, '_blank');
}

function assignReviewer(appId) {
    const reviewer = prompt('Assign reviewer name:');
    if (!reviewer) return;
    const payload = getRowPayload(appId);
    if (!payload) return;
    payload.reviewer_name = reviewer;
    payload.status = 'Reviewer Assigned';
    updateApplication('update', payload).then(() => refreshApplications());
}

function approveApplication(appId) {
    if (!confirm(`Approve application ${appId} for department review?`)) return;
    const payload = getRowPayload(appId);
    if (!payload) return;
    payload.status = 'Department Approved';
    updateApplication('update', payload).then(() => refreshApplications());
}

function rejectApplication(appId) {
    const reason = prompt('Please provide a reason for rejection:');
    if (!reason) return;
    const payload = getRowPayload(appId);
    if (!payload) return;
    payload.status = 'Rejected';
    payload.priority = `Rejected: ${reason}`;
    updateApplication('update', payload).then(() => refreshApplications());
}

function requestMoreInfo(appId) {
    const payload = getRowPayload(appId);
    if (!payload) return;
    payload.status = 'Needs Info';
    updateApplication('update', payload).then(() => refreshApplications());
}

function escalateToAdmin(appId) {
    if (!confirm(`Escalate application ${appId} to admin review?`)) return;
    const payload = getRowPayload(appId);
    if (!payload) return;
    payload.status = 'Escalated';
    updateApplication('update', payload).then(() => refreshApplications());
}

function sendToFinalReview(appId) {
    if (!confirm(`Send application ${appId} to final admission review?`)) return;
    const payload = getRowPayload(appId);
    if (!payload) return;
    payload.status = 'Final Review';
    updateApplication('update', payload).then(() => refreshApplications());
}

function revokeApproval(appId) {
    if (!confirm(`Revoke department approval for ${appId}?`)) return;
    const payload = getRowPayload(appId);
    if (!payload) return;
    payload.status = 'Under Review';
    updateApplication('update', payload).then(() => refreshApplications());
}

function viewReviewerComments(appId) {
    const payload = getRowPayload(appId);
    if (!payload) return;
    document.getElementById('modalAppId').textContent = payload.app_code;
    document.getElementById('modalName').textContent = payload.applicant_name;
    document.getElementById('modalProgramme').textContent = payload.programme;
    document.getElementById('modalReviewer').textContent = payload.reviewer_name || 'Unassigned';
    document.getElementById('modalStatus').textContent = payload.status;
    new bootstrap.Modal(document.getElementById('applicationModal')).show();
}

// Bulk actions
function bulkAssignReviewers() {
    const reviewer = prompt('Assign reviewer name for selected applications:');
    if (!reviewer) return;
    const selected = Array.from(document.querySelectorAll('.app-checkbox:checked'))
        .map(cb => cb.closest('tr').dataset.app)
        .filter(Boolean);
    if (!selected.length) return;
    const items = selected.map(appId => {
        const payload = getRowPayload(appId);
        payload.status = 'Reviewer Assigned';
        payload.reviewer_name = reviewer;
        return payload;
    });
    const formData = new FormData();
    formData.append('action', 'bulk');
    formData.append('items', JSON.stringify(items));
    fetch('api/applications.php', { method: 'POST', body: formData })
        .then(() => refreshApplications());
}

function bulkApprove() {
    const selectedApps = Array.from(document.querySelectorAll('.app-checkbox:checked')).map(cb => cb.closest('tr').querySelector('td:nth-child(2)').textContent);
    if (!confirm(`Department approve ${selectedApps.length} applications?`)) return;
    const items = selectedApps.map(appId => {
        const payload = getRowPayload(appId);
        payload.status = 'Department Approved';
        return payload;
    });
    const formData = new FormData();
    formData.append('action', 'bulk');
    formData.append('items', JSON.stringify(items));
    fetch('api/applications.php', { method: 'POST', body: formData })
        .then(() => refreshApplications());
}

function bulkSendToReview() {
    const selectedApps = Array.from(document.querySelectorAll('.app-checkbox:checked')).map(cb => cb.closest('tr').querySelector('td:nth-child(2)').textContent);
    if (!confirm(`Send ${selectedApps.length} applications to final review?`)) return;
    const items = selectedApps.map(appId => {
        const payload = getRowPayload(appId);
        payload.status = 'Final Review';
        return payload;
    });
    const formData = new FormData();
    formData.append('action', 'bulk');
    formData.append('items', JSON.stringify(items));
    fetch('api/applications.php', { method: 'POST', body: formData })
        .then(() => refreshApplications());
}

function bulkEscalate() {
    const selectedApps = Array.from(document.querySelectorAll('.app-checkbox:checked')).map(cb => cb.closest('tr').querySelector('td:nth-child(2)').textContent);
    if (!confirm(`Escalate ${selectedApps.length} applications to admin?`)) return;
    const items = selectedApps.map(appId => {
        const payload = getRowPayload(appId);
        payload.status = 'Escalated';
        return payload;
    });
    const formData = new FormData();
    formData.append('action', 'bulk');
    formData.append('items', JSON.stringify(items));
    fetch('api/applications.php', { method: 'POST', body: formData })
        .then(() => refreshApplications());
}

function filterByProgramme(programme) {
    const rows = document.querySelectorAll('#applicationsTable tbody tr');
    rows.forEach(row => {
        const value = (row.dataset.programme || '').toLowerCase();
        if (programme === 'all') {
            row.style.display = '';
            return;
        }
        if (programme === 'msc') {
            row.style.display = value.includes('m.sc') || value.includes('msc') ? '' : 'none';
            return;
        }
        if (programme === 'phd') {
            row.style.display = value.includes('ph.d') || value.includes('phd') ? '' : 'none';
            return;
        }
        row.style.display = '';
    });
}

function filterByStatus(status) {
    const rows = document.querySelectorAll('#applicationsTable tbody tr');
    rows.forEach(row => {
        const value = (row.dataset.status || '').toLowerCase().replace(/\s+/g, '_');
        if (status === 'all') {
            row.style.display = '';
            return;
        }
        row.style.display = value.includes(status) ? '' : 'none';
    });
}

// Search functionality
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('#applicationsTable tbody tr');

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });
}

function refreshApplications() {
    loadApplications();
}

function assignReviewers() {
    bulkAssignReviewers();
}

function approveApplicationModal() {
    approveApplication(document.getElementById('modalAppId').textContent);
    bootstrap.Modal.getInstance(document.getElementById('applicationModal')).hide();
}

function rejectApplicationModal() {
    rejectApplication(document.getElementById('modalAppId').textContent);
    bootstrap.Modal.getInstance(document.getElementById('applicationModal')).hide();
}

function assignReviewerModal() {
    assignReviewer(document.getElementById('modalAppId').textContent);
    bootstrap.Modal.getInstance(document.getElementById('applicationModal')).hide();
}

function requestMoreInfoModal() {
    requestMoreInfo(document.getElementById('modalAppId').textContent);
    bootstrap.Modal.getInstance(document.getElementById('applicationModal')).hide();
}

function escalateToAdminModal() {
    escalateToAdmin(document.getElementById('modalAppId').textContent);
    bootstrap.Modal.getInstance(document.getElementById('applicationModal')).hide();
}

function changeReviewer() {
    const appId = document.getElementById('modalAppId').textContent;
    const reviewer = prompt('Assign new reviewer name:');
    if (!reviewer) return;
    const payload = getRowPayload(appId);
    if (!payload) return;
    payload.reviewer_name = reviewer;
    payload.status = 'Reviewer Assigned';
    updateApplication('update', payload).then(data => {
        if (data.success) {
            document.getElementById('modalReviewer').textContent = reviewer;
            refreshApplications();
        }
    });
}

function saveChanges() {
    bootstrap.Modal.getInstance(document.getElementById('applicationModal')).hide();
    refreshApplications();
}

// Add custom styling

function getRowPayload(appId) {
    const row = document.querySelector(`tr[data-app="${appId}"]`);
    if (!row) return null;
    return {
        app_code: row.dataset.app,
        applicant_name: row.dataset.applicant,
        programme: row.dataset.programme,
        status: row.dataset.status,
        reviewer_name: row.dataset.reviewer,
        submitted_date: row.dataset.submitted,
        priority: row.dataset.priority
    };
}

function updateApplication(action, payload) {
    const formData = new FormData();
    formData.append('action', action);
    Object.entries(payload || {}).forEach(([key, value]) => formData.append(key, value));
    return fetch('api/applications.php', { method: 'POST', body: formData })
        .then(response => response.json());
}

function viewApplication(appId) {
    const payload = getRowPayload(appId);
    if (!payload) return;
    const params = new URLSearchParams({ action: 'view', ...payload });
    fetch(`api/applications.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Unable to load application.');
                return;
            }
            const modal = new bootstrap.Modal(document.getElementById('applicationModal'));
            document.getElementById('modalAppId').textContent = payload.app_code;
            document.getElementById('modalName').textContent = payload.applicant_name;
            document.getElementById('modalEmail').textContent = payload.applicant_name.replace(/\s+/g, '.').toLowerCase() + '@jostum.edu';
            document.getElementById('modalProgramme').textContent = payload.programme;
            document.getElementById('modalGPA').textContent = 'No data yet';
            document.getElementById('modalStatus').textContent = payload.status || 'No data yet';
            document.getElementById('modalReviewer').textContent = payload.reviewer_name || 'No data yet';
            document.getElementById('modalProposal').textContent = payload.priority ? `Priority: ${payload.priority}` : 'No data yet.';
            modal.show();
        });
}

function assignReviewer(appId) {
    const reviewer = prompt('Assign reviewer name:');
    if (!reviewer) return;
    const payload = getRowPayload(appId);
    if (!payload) return;
    payload.reviewer_name = reviewer;
    payload.status = 'Reviewer Assigned';
    updateApplication('update', payload).then(() => location.reload());
}

function approveApplication(appId) {
    const payload = getRowPayload(appId);
    if (!payload) return;
    payload.status = 'Department Approved';
    updateApplication('update', payload).then(() => location.reload());
}

function rejectApplication(appId) {
    const reason = prompt('Please provide a reason for rejection:');
    if (!reason) return;
    const payload = getRowPayload(appId);
    if (!payload) return;
    payload.status = 'Rejected';
    updateApplication('update', payload).then(() => location.reload());
}

function requestMoreInfo(appId) {
    const payload = getRowPayload(appId);
    if (!payload) return;
    payload.status = 'Needs Info';
    updateApplication('update', payload).then(() => location.reload());
}

function escalateToAdmin(appId) {
    const payload = getRowPayload(appId);
    if (!payload) return;
    payload.status = 'Escalated';
    updateApplication('update', payload).then(() => location.reload());
}

function sendToFinalReview(appId) {
    const payload = getRowPayload(appId);
    if (!payload) return;
    payload.status = 'Final Review';
    updateApplication('update', payload).then(() => location.reload());
}

function revokeApproval(appId) {
    const payload = getRowPayload(appId);
    if (!payload) return;
    payload.status = 'Under Review';
    updateApplication('update', payload).then(() => location.reload());
}

function bulkAssignReviewers() {
    const reviewer = prompt('Assign reviewer name for selected applications:');
    if (!reviewer) return;
    const selected = Array.from(document.querySelectorAll('.app-checkbox:checked'))
        .map(cb => cb.closest('tr').dataset.app)
        .filter(Boolean);
    if (!selected.length) return;
    const items = selected.map(appId => {
        const payload = getRowPayload(appId);
        payload.status = 'Reviewer Assigned';
        payload.reviewer_name = reviewer;
        return payload;
    });
    const formData = new FormData();
    formData.append('action', 'bulk');
    formData.append('items', JSON.stringify(items));
    fetch('api/applications.php', { method: 'POST', body: formData })
        .then(() => refreshApplications());
}

function bulkApprove() {
    const selected = Array.from(document.querySelectorAll('.app-checkbox:checked'))
        .map(cb => cb.closest('tr').dataset.app)
        .filter(Boolean);
    if (!selected.length) return;
    const items = selected.map(appId => {
        const payload = getRowPayload(appId);
        payload.status = 'Department Approved';
        return payload;
    });
    const formData = new FormData();
    formData.append('action', 'bulk');
    formData.append('items', JSON.stringify(items));
    fetch('api/applications.php', { method: 'POST', body: formData })
        .then(() => location.reload());
}

function bulkSendToReview() {
    const selected = Array.from(document.querySelectorAll('.app-checkbox:checked'))
        .map(cb => cb.closest('tr').dataset.app)
        .filter(Boolean);
    if (!selected.length) return;
    const items = selected.map(appId => {
        const payload = getRowPayload(appId);
        payload.status = 'Final Review';
        return payload;
    });
    const formData = new FormData();
    formData.append('action', 'bulk');
    formData.append('items', JSON.stringify(items));
    fetch('api/applications.php', { method: 'POST', body: formData })
        .then(() => location.reload());
}

function bulkEscalate() {
    const selected = Array.from(document.querySelectorAll('.app-checkbox:checked'))
        .map(cb => cb.closest('tr').dataset.app)
        .filter(Boolean);
    if (!selected.length) return;
    const items = selected.map(appId => {
        const payload = getRowPayload(appId);
        payload.status = 'Escalated';
        return payload;
    });
    const formData = new FormData();
    formData.append('action', 'bulk');
    formData.append('items', JSON.stringify(items));
    fetch('api/applications.php', { method: 'POST', body: formData })
        .then(() => location.reload());
}

const style = document.createElement('style');
style.textContent = `
    .avatar-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6c757d;
        margin: 0 auto;
    }
    .activity-item {
        padding: 4px 0;
        border-left: 2px solid #e9ecef;
        padding-left: 8px;
        margin-left: 8px;
    }
`;
if (!document.querySelector('style[data-app="dept-apps"]')) {
    style.setAttribute('data-app', 'dept-apps');
    document.head.appendChild(style);
}
</script>

<?php require_once 'includes/footer.php'; ?>
