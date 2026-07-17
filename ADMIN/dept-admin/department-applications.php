<?php
$pageTitle = 'Departmental Admission & Vetting Desk';
$pageSubtitle = 'Verify applicants, assign supervisors, and forward files to the College review.';

require_once __DIR__ . '/../../ADMIN/admin/includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';

$deptId = null;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT department_id FROM users WHERE user_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $dVal = $stmt->fetchColumn();
        if ($dVal !== false && $dVal !== null) {
            $_SESSION['department_id'] = (int) $dVal;
            $deptId = (int) $dVal;
        }
    } catch (Throwable $e) {}
}

$supervisors = [];
if ($deptId) {
    try {
        $supStmt = $pdo->prepare("SELECT supervisor_id, full_name, email FROM supervisors WHERE department_id = ? AND status = 'Active' ORDER BY full_name");
        $supStmt->execute([$deptId]);
        $supervisors = $supStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}
?>

<section class="page-hero bg-dark text-white p-4 rounded-3 mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="h2 fw-bold text-warning mb-1">Departmental Review Panel</h1>
            <p class="text-white-50 mb-0">Follow applicant progress, assign research supervisors, and route applications up the validation chain.</p>
        </div>
        <div class="mt-2">
            <button class="btn btn-outline-warning btn-sm" onclick="refreshQueue()"><i class="fas fa-sync me-2"></i>Refresh Queue</button>
        </div>
    </div>
</section>

<!-- Tabs Navigation -->
<ul class="nav nav-pills mb-4 gap-2" id="reviewTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-bold btn-lg position-relative" id="vetted-tab" data-bs-toggle="pill" data-bs-target="#vetted" type="button" role="tab">
            Awaiting Verification
            <span class="badge bg-danger ms-2" id="badge-vetted-count">0</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-bold btn-lg position-relative" id="verified-tab" data-bs-toggle="pill" data-bs-target="#verified" type="button" role="tab">
            Awaiting Supervisor
            <span class="badge bg-warning text-dark ms-2" id="badge-verified-count">0</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-bold btn-lg position-relative" id="assigned-tab" data-bs-toggle="pill" data-bs-target="#assigned" type="button" role="tab">
            Assigned (Ready for College)
            <span class="badge bg-success ms-2" id="badge-assigned-count">0</span>
        </button>
    </li>
</ul>

<!-- Tabs Content -->
<div class="tab-content" id="reviewTabsContent">
    
    <!-- Tab 1: Vetted Candidates Awaiting Verification -->
    <div class="tab-pane fade show active" id="vetted" role="tabpanel">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-user-clock me-2 text-warning"></i>Vetted Candidates</h5>
                    <small class="text-muted">These applicants have passed ICT document checks and require HOD verification.</small>
                </div>
                <div>
                    <button class="btn btn-warning fw-bold text-dark btn-sm" onclick="bulkAction('verify')">
                        <i class="fas fa-check-double me-1"></i> Verify Selected (Bulk)
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tableVetted">
                        <thead class="table-light">
                            <tr>
                                <th width="40"><input type="checkbox" onclick="toggleSelectAll(this, 'vetted')"></th>
                                <th>Applicant Details</th>
                                <th>Programme</th>
                                <th>ICT Vetting Details</th>
                                <th>Submitted Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="6" class="text-center py-4 text-muted">Loading vetted applications...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 2: Verified Candidates Awaiting Supervisor -->
    <div class="tab-pane fade" id="verified" role="tabpanel">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-user-tag me-2 text-warning"></i>Assign Supervisors</h5>
                    <small class="text-muted">Allocate department supervisors for verified candidates.</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <select id="bulkSupervisor" class="form-select form-select-sm" style="max-width: 250px;">
                        <option value="">Select Supervisor</option>
                        <?php foreach ($supervisors as $s): ?>
                            <option value="<?= $s['supervisor_id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary fw-bold btn-sm text-nowrap" onclick="bulkAction('assign')">
                        Assign Selected
                    </button>
                    <button class="btn btn-outline-primary btn-sm text-nowrap" onclick="bulkAction('auto_distribute')">
                        <i class="fas fa-random me-1"></i> Auto-Distribute
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tableVerified">
                        <thead class="table-light">
                            <tr>
                                <th width="40"><input type="checkbox" onclick="toggleSelectAll(this, 'verified')"></th>
                                <th>Applicant Details</th>
                                <th>Programme</th>
                                <th>Status</th>
                                <th>Verification Details</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="6" class="text-center py-4 text-muted">Loading verified applications...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 3: Assigned Candidates Ready for College -->
    <div class="tab-pane fade" id="assigned" role="tabpanel">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success bg-opacity-10 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-file-export me-2 text-success"></i>Ready for College Review</h5>
                    <small class="text-muted">Advance candidates with assigned supervisors up the chain.</small>
                </div>
                <div>
                    <button class="btn btn-success fw-bold btn-sm" onclick="bulkAction('advance')">
                        <i class="fas fa-share-square me-1"></i> Forward to College Review (Bulk)
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tableAssigned">
                        <thead class="table-light">
                            <tr>
                                <th width="40"><input type="checkbox" onclick="toggleSelectAll(this, 'assigned')"></th>
                                <th>Applicant Details</th>
                                <th>Programme</th>
                                <th>Assigned Supervisor</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="6" class="text-center py-4 text-muted">Loading assigned applications...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Styles override -->
<style>
.nav-pills .nav-link {
    color: #495057;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
}
.nav-pills .nav-link.active {
    background-color: #ffc107;
    color: #000;
    border-color: #ffc107;
}
.status-badge {
    font-size: 0.75rem;
    padding: 0.35em 0.65em;
    border-radius: 50rem;
    font-weight: 700;
}
</style>

<script>
let allApplicants = [];

document.addEventListener('DOMContentLoaded', () => {
    refreshQueue();
});

function refreshQueue() {
    fetch('api/applications.php?action=list')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                allApplicants = data.data || [];
                renderTables();
            } else {
                alert(data.message || 'Failed to refresh lists.');
            }
        });
}

function renderTables() {
    // Stage 1: Vetted (Awaiting HOD verification)
    const vettedList = allApplicants.filter(x => ['ICT_VETTED', 'ASSIGNED_TO_DEPARTMENT', 'UNDER_DEPT_REVIEW', 'SUBMITTED'].includes(x.status));
    document.getElementById('badge-vetted-count').innerText = vettedList.length;
    
    // Stage 2: Verified (Awaiting supervisor)
    const verifiedList = allApplicants.filter(x => x.status === 'HOD_VERIFIED' && !x.supervisor_name);
    document.getElementById('badge-verified-count').innerText = verifiedList.length;
    
    // Stage 3: Assigned (Ready for College)
    const assignedList = allApplicants.filter(x => x.status === 'HOD_VERIFIED' && x.supervisor_name);
    document.getElementById('badge-assigned-count').innerText = assignedList.length;

    renderVetted(vettedList);
    renderVerified(verifiedList);
    renderAssigned(assignedList);
}

function renderVetted(list) {
    const tbody = document.querySelector('#tableVetted tbody');
    if (!list.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">No applications awaiting HOD verification.</td></tr>`;
        return;
    }
    tbody.innerHTML = list.map(item => `
        <tr>
            <td><input type="checkbox" class="cb-vetted" value="${item.app_id}"></td>
            <td>
                <div class="fw-bold text-dark">${item.applicant_name}</div>
                <div class="text-muted small">Code: <code>${item.app_code}</code></div>
            </td>
            <td><span class="text-dark fw-semibold">${item.programme}</span></td>
            <td><span class="status-badge bg-warning text-dark">ICT Vetted</span></td>
            <td><span class="text-muted">${item.submitted_date || '-'}</span></td>
            <td class="text-end">
                <button class="btn btn-sm btn-success fw-bold" onclick="verifyApplicant(${item.app_id})"><i class="fas fa-check me-1"></i>Verify</button>
                <a href="../view.php?app_no=${encodeURIComponent(item.app_code)}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a>
            </td>
        </tr>
    `).join('');
}

function renderVerified(list) {
    const tbody = document.querySelector('#tableVerified tbody');
    if (!list.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">No applications awaiting supervisor assignment.</td></tr>`;
        return;
    }
    tbody.innerHTML = list.map(item => `
        <tr>
            <td><input type="checkbox" class="cb-verified" value="${item.app_id}"></td>
            <td>
                <div class="fw-bold text-dark">${item.applicant_name}</div>
                <div class="text-muted small">Code: <code>${item.app_code}</code></div>
            </td>
            <td><span class="text-dark fw-semibold">${item.programme}</span></td>
            <td><span class="status-badge bg-info text-white">Verified by HOD</span></td>
            <td><span class="text-muted">Awaiting Supervisor Allocation</span></td>
            <td class="text-end">
                <div class="d-inline-flex gap-1 align-items-center">
                    <select class="form-select form-select-sm select-supervisor-single" data-id="${item.app_id}" style="width: 180px;">
                        <option value="">Choose Supervisor</option>
                        <?php foreach ($supervisors as $s): ?>
                            <option value="<?= $s['supervisor_id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-primary fw-bold" onclick="assignSingle(${item.app_id})">Assign</button>
                </div>
            </td>
        </tr>
    `).join('');
}

function renderAssigned(list) {
    const tbody = document.querySelector('#tableAssigned tbody');
    if (!list.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">No applications ready to advance.</td></tr>`;
        return;
    }
    tbody.innerHTML = list.map(item => `
        <tr>
            <td><input type="checkbox" class="cb-assigned" value="${item.app_id}"></td>
            <td>
                <div class="fw-bold text-dark">${item.applicant_name}</div>
                <div class="text-muted small">Code: <code>${item.app_code}</code></div>
            </td>
            <td><span class="text-dark fw-semibold">${item.programme}</span></td>
            <td><span class="badge bg-secondary p-2"><i class="fas fa-user-tie me-1"></i>${item.supervisor_name}</span></td>
            <td><span class="status-badge bg-success text-white">HOD Verified & Assigned</span></td>
            <td class="text-end">
                <button class="btn btn-sm btn-success fw-bold" onclick="advanceSingle(${item.app_id})"><i class="fas fa-forward me-1"></i>Forward</button>
            </td>
        </tr>
    `).join('');
}

function toggleSelectAll(masterCb, type) {
    const selector = `.cb-${type}`;
    document.querySelectorAll(selector).forEach(cb => {
        cb.checked = masterCb.checked;
    });
}

function verifyApplicant(appId) {
    if (!confirm('Are you sure you want to verify this applicant?')) return;
    const form = new FormData();
    form.append('action', 'verify');
    form.append('application_ids[]', appId);
    fetch('api/applications.php', { method: 'POST', body: form })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                refreshQueue();
                const tabEl = document.getElementById('verified-tab');
                if (tabEl) {
                    const tab = bootstrap.Tab.getOrCreateInstance(tabEl);
                    if (tab) tab.show();
                }
            } else {
                alert(data.message || 'Verification failed.');
            }
        });
}

function assignSingle(appId) {
    const select = document.querySelector(`.select-supervisor-single[data-id="${appId}"]`);
    const val = select ? select.value : '';
    if (!val) {
        alert('Please select a supervisor first.');
        return;
    }
    const form = new FormData();
    form.append('action', 'assign_supervisors');
    form.append('supervisor_id', val);
    form.append('application_ids[]', appId);
    fetch('api/applications.php', { method: 'POST', body: form })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                refreshQueue();
                const tabEl = document.getElementById('assigned-tab');
                if (tabEl) {
                    const tab = bootstrap.Tab.getOrCreateInstance(tabEl);
                    if (tab) tab.show();
                }
            } else {
                alert(data.message || 'Assignment failed.');
            }
        });
}

function advanceSingle(appId) {
    if (!confirm('Forward this candidate to College Review?')) return;
    const form = new FormData();
    form.append('action', 'advance_to_college');
    form.append('application_ids[]', appId);
    fetch('api/applications.php', { method: 'POST', body: form })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                refreshQueue();
            } else {
                alert(data.message || 'Operation failed.');
            }
        });
}

function bulkAction(type) {
    let cbClass = '';
    let actionName = '';
    let form = new FormData();

    if (type === 'verify') {
        cbClass = '.cb-vetted:checked';
        actionName = 'verify';
    } else if (type === 'assign') {
        cbClass = '.cb-verified:checked';
        actionName = 'assign_supervisors';
        const supVal = document.getElementById('bulkSupervisor').value;
        if (!supVal) {
            alert('Please select a supervisor for bulk assignment.');
            return;
        }
        form.append('supervisor_id', supVal);
    } else if (type === 'auto_distribute') {
        cbClass = '.cb-verified:checked';
        actionName = 'assign_supervisors';
        form.append('auto_distribute', '1');
    } else if (type === 'advance') {
        cbClass = '.cb-assigned:checked';
        actionName = 'advance_to_college';
    }

    const checked = Array.from(document.querySelectorAll(cbClass)).map(cb => cb.value);
    if (!checked.length) {
        alert('Please select at least one applicant.');
        return;
    }

    if (type === 'auto_distribute') {
        if (!confirm(`Are you sure you want to distribute these ${checked.length} applicants evenly among all active departmental supervisors?`)) return;
    } else if (type === 'verify') {
        if (!confirm(`Verify all ${checked.length} selected applicants?`)) return;
    } else if (type === 'advance') {
        if (!confirm(`Forward all ${checked.length} selected applicants to College Review?`)) return;
    }

    form.append('action', actionName);
    checked.forEach(id => form.append('application_ids[]', id));

    fetch('api/applications.php', { method: 'POST', body: form })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Bulk operation completed.');
                refreshQueue();
                let nextTabId = '';
                if (type === 'verify') nextTabId = 'verified-tab';
                else if (type === 'assign' || type === 'auto_distribute') nextTabId = 'assigned-tab';
                
                if (nextTabId) {
                    const tabEl = document.getElementById(nextTabId);
                    if (tabEl) {
                        const tab = bootstrap.Tab.getOrCreateInstance(tabEl);
                        if (tab) tab.show();
                    }
                }
            } else {
                alert(data.message || 'Bulk operation failed.');
            }
        });
}
</script>

<?php require_once 'includes/footer.php'; ?>
