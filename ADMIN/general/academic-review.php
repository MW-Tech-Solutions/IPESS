<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once 'includes/db.php';

if (!has_permission('department_review')) {
    http_response_code(403);
    exit('403 Forbidden: Academic Review duty not assigned.');
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userDeptId = null;
$userDeptName = '';
if ($userId > 0 && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.department_id, d.dept_name 
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.dept_id
            WHERE u.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $userDeptId = $row['department_id'] ? (int) $row['department_id'] : null;
            $userDeptName = $row['dept_name'] ?? '';
        }
    } catch (PDOException $e) {
    }
}

$pageTitle = 'Academic Review';
$pageSubtitle = 'Review department applications, recommend decisions, and manage approvals.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Academic Review</h1>
        <p class="panel-muted">Monitor the application funnel for <strong><?php echo htmlspecialchars($userDeptName ?: 'All Departments'); ?></strong>.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" onclick="refreshApplications()"><i class="fas fa-sync me-2"></i>Refresh</button>
        <button class="btn btn-primary" onclick="assignReviewers()"><i class="fas fa-user-plus me-2"></i>Assign Reviewers</button>
    </div>
</section>

<section class="stat-grid mt-4">
    <div class="stat-card">
        <div class="stat-icon bg-primary text-white"><i class="fas fa-file-alt"></i></div>
        <div>
            <div class="stat-title text-muted">Total Applications</div>
            <div class="stat-value" id="dept-total-apps">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-warning text-white"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-title text-muted">Awaiting Review</div>
            <div class="stat-value" id="dept-pending">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-success text-white"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-title text-muted">Department Approved</div>
            <div class="stat-value" id="dept-approved">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-info text-white"><i class="fas fa-users"></i></div>
        <div>
            <div class="stat-title text-muted">Assigned Reviewers</div>
            <div class="stat-value" id="assigned-reviewers">0</div>
        </div>
    </div>
</section>

<section class="panel mt-4">
    <div class="panel-header d-flex justify-content-between align-items-center">
        <div>
            <h3 class="panel-title"><?php echo htmlspecialchars($userDeptName ?: 'All Departments'); ?> Applications</h3>
            <div class="panel-muted">Applications awaiting academic review.</div>
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
                <tbody id="applicationsBody">
                    <tr><td colspan="9" class="text-center py-5 text-muted">Loading applications...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="panel mt-4" id="bulkActionsCard" style="display:none;">
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

<!-- Detail View Modal -->
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
                            <span class="badge bg-secondary" id="modalStatus">No data yet</span>
                            <small class="text-muted ms-2">Department Review Phase</small>
                        </div>

                        <h6>Reviewer Assignment</h6>
                        <div class="mb-3">
                            <span id="modalReviewer" class="fw-semibold">No data yet</span>
                            <button class="btn btn-sm btn-outline-primary ms-2" onclick="changeReviewer()">
                                <i class="fas fa-exchange-alt"></i> Change
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4 border-start">
                        <h6>Quick Actions</h6>
                        <div class="d-grid gap-2">
                            <button class="btn btn-success" onclick="approveApplicationModal()"><i class="fas fa-check"></i> Department Approve</button>
                            <button class="btn btn-danger" onclick="rejectApplicationModal()"><i class="fas fa-times"></i> Reject Application</button>
                            <button class="btn btn-info" onclick="assignReviewerModal()"><i class="fas fa-user-plus"></i> Assign Reviewer</button>
                            <button class="btn btn-warning" onclick="requestMoreInfoModal()"><i class="fas fa-question-circle"></i> Request More Info</button>
                            <button class="btn btn-secondary" onclick="escalateToAdminModal()"><i class="fas fa-exclamation-triangle"></i> Escalate to Admin</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Assign Reviewer Modal -->
<div class="modal fade" id="assignReviewerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Reviewer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="assignAppCode">
                <div class="mb-3">
                    <label class="form-label">Reviewer Name or Email</label>
                    <input type="text" class="form-control" id="reviewerNameInput" placeholder="Enter full name or email address of reviewer">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitReviewerAssignment()">Assign Reviewer</button>
            </div>
        </div>
    </div>
</div>

<script>
let applications = [];
let selectedApplications = [];

document.addEventListener('DOMContentLoaded', () => {
    loadApplications();

    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.app-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateSelectedList();
    });

    document.getElementById('searchInput').addEventListener('input', function() {
        renderTable(this.value);
    });
});

async function loadApplications() {
    try {
        const res = await fetch('api/academic-review.php?action=list');
        const data = await res.json();
        if (data.success) {
            applications = data.data || [];
            renderTable();
            updateKPIs();
        } else {
            console.error('Failed to load applications:', data.message);
        }
    } catch (e) {
        console.error('Error loading applications:', e);
    }
}

function renderTable(filterQuery = '') {
    const body = document.getElementById('applicationsBody');
    body.innerHTML = '';

    const query = filterQuery.toLowerCase().trim();
    const filtered = applications.filter(app => {
        return app.app_code.toLowerCase().includes(query) || 
               app.applicant_name.toLowerCase().includes(query) || 
               app.programme.toLowerCase().includes(query);
    });

    if (filtered.length === 0) {
        body.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted">No applications found.</td></tr>';
        return;
    }

    filtered.forEach(app => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="checkbox" class="app-checkbox" value="${app.app_code}" onchange="updateSelectedList()"></td>
            <td><span class="font-monospace small text-primary bg-light px-2 py-1 rounded border">${app.app_code}</span></td>
            <td class="fw-bold">${app.applicant_name}</td>
            <td>${app.programme}</td>
            <td><span class="badge bg-light text-dark border">${app.status}</span></td>
            <td>${app.reviewer_name || '<span class="text-muted">Unassigned</span>'}</td>
            <td>${app.submitted_date || 'N/A'}</td>
            <td><span class="badge bg-light text-secondary border fw-normal">${app.priority}</span></td>
            <td>
                <button class="btn btn-sm btn-outline-primary border-0" onclick="viewApplication('${app.app_code}')">
                    <i class="fas fa-eye"></i> View
                </button>
            </td>
        `;
        body.appendChild(tr);
    });
}

function updateKPIs() {
    document.getElementById('dept-total-apps').innerText = applications.length;
    document.getElementById('dept-pending').innerText = applications.filter(a => a.status.toLowerCase().includes('pending') || a.status.toLowerCase().includes('review')).length;
    document.getElementById('dept-approved').innerText = applications.filter(a => a.status.toLowerCase().includes('approved')).length;
    document.getElementById('assigned-reviewers').innerText = new Set(applications.map(a => a.reviewer_name).filter(Boolean)).size;
}

function updateSelectedList() {
    const checkboxes = document.querySelectorAll('.app-checkbox:checked');
    selectedApplications = Array.from(checkboxes).map(cb => cb.value);
    
    const card = document.getElementById('bulkActionsCard');
    if (selectedApplications.length > 0) {
        card.style.display = 'block';
        document.getElementById('selectedCount').innerText = `${selectedApplications.length} application(s) selected`;
    } else {
        card.style.display = 'none';
    }
}

function viewApplication(appCode) {
    window.open(`../view.php?app_no=${encodeURIComponent(appCode)}`, '_blank');
}

function changeReviewer() {
    const appCode = document.getElementById('modalAppId').innerText;
    bootstrap.Modal.getInstance(document.getElementById('applicationModal')).hide();
    openAssignReviewer(appCode);
}

function openAssignReviewer(appCode) {
    document.getElementById('assignAppCode').value = appCode;
    document.getElementById('reviewerNameInput').value = '';
    new bootstrap.Modal(document.getElementById('assignReviewerModal')).show();
}

async function submitReviewerAssignment() {
    const appCode = document.getElementById('assignAppCode').value;
    const reviewer = document.getElementById('reviewerNameInput').value;

    if (!reviewer) {
        alert('Please enter reviewer name or email.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('app_code', appCode);
    formData.append('status', 'reviewer_assigned');
    formData.append('reviewer_name', reviewer);

    try {
        const res = await fetch('api/academic-review.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('assignReviewerModal')).hide();
            loadApplications();
        } else {
            alert(data.message || 'Error occurred.');
        }
    } catch(e) {
        console.error(e);
    }
}

async function updateApplicationStatus(appCode, status) {
    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('app_code', appCode);
    formData.append('status', status);

    try {
        const res = await fetch('api/academic-review.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('applicationModal')).hide();
            loadApplications();
        } else {
            alert(data.message || 'Error occurred.');
        }
    } catch(e) {
        console.error(e);
    }
}

function approveApplicationModal() {
    const appCode = document.getElementById('modalAppId').innerText;
    updateApplicationStatus(appCode, 'approved');
}

function rejectApplicationModal() {
    const appCode = document.getElementById('modalAppId').innerText;
    updateApplicationStatus(appCode, 'rejected');
}

function assignReviewerModal() {
    const appCode = document.getElementById('modalAppId').innerText;
    bootstrap.Modal.getInstance(document.getElementById('applicationModal')).hide();
    openAssignReviewer(appCode);
}

function requestMoreInfoModal() {
    const appCode = document.getElementById('modalAppId').innerText;
    updateApplicationStatus(appCode, 'needs_info');
}

function escalateToAdminModal() {
    const appCode = document.getElementById('modalAppId').innerText;
    updateApplicationStatus(appCode, 'escalated');
}

async function bulkAction(status) {
    const items = selectedApplications.map(code => ({ app_code: code, status }));
    const formData = new FormData();
    formData.append('action', 'bulk');
    formData.append('items', JSON.stringify(items));

    try {
        const res = await fetch('api/academic-review.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            loadApplications();
        } else {
            alert(data.message || 'Error occurred.');
        }
    } catch(e) {
        console.error(e);
    }
}

function bulkAssignReviewers() {
    if (selectedApplications.length === 0) return;
    openAssignReviewer(selectedApplications[0]); // Handles first selected as demo
}

function bulkApprove() {
    bulkAction('approved');
}

function bulkSendToReview() {
    bulkAction('final');
}

function bulkEscalate() {
    bulkAction('escalated');
}

function refreshApplications() {
    loadApplications();
}
</script>

<?php require_once 'includes/footer.php'; ?>
