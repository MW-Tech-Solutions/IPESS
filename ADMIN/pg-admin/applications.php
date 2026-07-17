<?php
$pageTitle = 'PG School Evaluation Desk';
$pageSubtitle = 'Issue final admissions approvals, request corrections, or reject candidates.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
require_once __DIR__ . '/../../ADMIN/admin/includes/db.php';

// Fetch filters
$faculties = [];
$departments = [];
if (isset($pdo)) {
    try {
        $faculties = $pdo->query("SELECT faculty_id, faculty_name FROM faculties ORDER BY faculty_name")->fetchAll(PDO::FETCH_ASSOC);
        $departments = $pdo->query("SELECT dept_id, dept_name, faculty_id FROM departments ORDER BY dept_name")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

$filterFaculty = $_GET['faculty_id'] ?? '';
$filterDept = $_GET['dept_id'] ?? '';
$filterStatus = $_GET['status'] ?? 'Pending';
$searchTerm = trim($_GET['search'] ?? '');
$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
if ($currentPage < 1) $currentPage = 1;

$limit = 10;
$offset = ($currentPage - 1) * $limit;

$applicants = [];
$totalApplicants = 0;

if (isset($pdo)) {
    try {
        $whereClauses = ["a.status != 'Draft'"];
        $params = [];

        if ($filterFaculty !== '') {
            $whereClauses[] = "pc.faculty = :faculty";
            $params[':faculty'] = $filterFaculty;
        }
        if ($filterDept !== '') {
            $whereClauses[] = "pc.department = :dept";
            $params[':dept'] = $filterDept;
        }
        if ($searchTerm !== '') {
            $whereClauses[] = "(pd.first_name LIKE :search OR pd.surname LIKE :search OR a.application_number LIKE :search)";
            $params[':search'] = '%' . $searchTerm . '%';
        }

        // PG evaluation status filter
        if ($filterStatus === 'Pending') {
            $whereClauses[] = "a.current_status IN ('COLLEGE_PENDING', 'HOD_VERIFIED', 'DEPT_APPROVED', 'FACULTY_APPROVED', 'UNDER_PG_REVIEW')";
        } elseif ($filterStatus === 'Approved') {
            $whereClauses[] = "a.current_status IN ('APPROVED_BY_POSTGRADUATE_SCHOOL', 'ADMISSION_APPROVED', 'Admitted')";
        } elseif ($filterStatus === 'Rejected') {
            $whereClauses[] = "a.current_status IN ('REJECTED_BY_POSTGRADUATE_SCHOOL', 'ADMISSION_REJECTED', 'Rejected')";
        }

        $whereSql = implode(' AND ', $whereClauses);

        // Count query
        $countQuery = "
            SELECT COUNT(DISTINCT a.application_id)
            FROM applications a
            JOIN programme_choices pc ON a.application_id = pc.application_id
            JOIN personal_details pd ON a.application_id = pd.application_id
            WHERE {$whereSql}
        ";
        $stmtCount = $pdo->prepare($countQuery);
        $stmtCount->execute($params);
        $totalApplicants = (int) $stmtCount->fetchColumn();

        // Data query
        $dataQuery = "
            SELECT DISTINCT a.application_id, a.application_number, a.status, a.current_status, a.submitted_at,
                   pd.first_name, pd.surname,
                   d.dept_name, f.faculty_name, c.course_title,
                    COALESCE(sp.supervisor_status, sa.status) AS supervisor_status, sa.updated_at AS supervisor_assigned_at, sa.supervisor_name AS supervisor_name
            FROM applications a
            JOIN programme_choices pc ON a.application_id = pc.application_id
            JOIN personal_details pd ON a.application_id = pd.application_id
            LEFT JOIN users u ON a.user_id = u.user_id
            LEFT JOIN departments d ON pc.department = d.dept_id
            LEFT JOIN faculties f ON pc.faculty = f.faculty_id
            LEFT JOIN courses c ON pc.course = c.course_id
            LEFT JOIN student_profiles sp ON (sp.student_id = a.application_number OR sp.email = u.email)
            LEFT JOIN supervisor_students sa ON (sa.student_id = a.application_number OR sa.application_id = a.application_id OR sa.application_number = a.application_number)
            WHERE {$whereSql}
            GROUP BY a.application_id
            ORDER BY a.submitted_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmtData = $pdo->prepare($dataQuery);
        foreach ($params as $key => $val) {
            $stmtData->bindValue($key, $val);
        }
        $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmtData->execute();
        $applicants = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("PG Applications Query Error: " . $e->getMessage());
    }
}

$totalPages = ceil($totalApplicants / $limit) ?: 1;
?>

<section class="page-hero">
    <div>
        <h1>PG School Review Console</h1>
        <p class="panel-muted">Review credentials and issue final academic endorsements for PG registrations.</p>
    </div>
</section>

<!-- Filter Panel -->
<section class="panel mb-4">
    <div class="panel-body">
        <form method="GET" action="applications.php" class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Faculty</label>
                <select name="faculty_id" id="filterFaculty" class="form-select">
                    <option value="">All Faculties</option>
                    <?php foreach ($faculties as $f): ?>
                        <option value="<?= $f['faculty_id'] ?>" <?= $filterFaculty == $f['faculty_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['faculty_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Department</label>
                <select name="dept_id" id="filterDept" class="form-select">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['dept_id'] ?>" data-faculty="<?= $d['faculty_id'] ?>" <?= $filterDept == $d['dept_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['dept_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">PG Status</label>
                <select name="status" class="form-select">
                    <option value="Pending" <?= $filterStatus === 'Pending' ? 'selected' : '' ?>>Awaiting PG Review</option>
                    <option value="Approved" <?= $filterStatus === 'Approved' ? 'selected' : '' ?>>PG Approved</option>
                    <option value="Rejected" <?= $filterStatus === 'Rejected' ? 'selected' : '' ?>>PG Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name or App Number" value="<?= htmlspecialchars($searchTerm) ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Filter</button>
            </div>
        </form>
    </div>
</section>

<!-- Bulk Actions & Listing -->
<section class="panel">
    <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h3 class="panel-title">PG Review List</h3>
            <div class="panel-muted"><?= $totalApplicants ?> candidate(s) found.</div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-success btn-sm" onclick="performBulkAction('approve')">
                <i class="fas fa-check-circle me-1"></i> Bulk Approve
            </button>
            <button class="btn btn-danger btn-sm" onclick="performBulkAction('reject')">
                <i class="fas fa-times-circle me-1"></i> Bulk Reject
            </button>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="40"><input type="checkbox" id="selectAll"></th>
                        <th>Applicant Details</th>
                        <th>Program / Faculty</th>
                        <th>Supervisor State</th>
                        <th>Application Code</th>
                        <th>Current Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($applicants)): ?>
                        <?php foreach ($applicants as $row): ?>
                            <?php
                            $fullName = htmlspecialchars($row['first_name'] . ' ' . $row['surname']);
                            $statusBadge = '';
                            if ($row['current_status'] === 'FACULTY_APPROVED') {
                                $statusBadge = '<span class="badge bg-info">Awaiting Final PG School Review</span>';
                            } elseif ($row['current_status'] === 'APPROVED_BY_POSTGRADUATE_SCHOOL' || $row['current_status'] === 'ADMISSION_APPROVED' || $row['current_status'] === 'Admitted') {
                                $statusBadge = '<span class="badge bg-success">Admitted / Approved by PG</span>';
                            } elseif ($row['current_status'] === 'REJECTED_BY_POSTGRADUATE_SCHOOL' || $row['current_status'] === 'ADMISSION_REJECTED' || $row['current_status'] === 'Rejected') {
                                $statusBadge = '<span class="badge bg-danger">PG Rejected</span>';
                            } else {
                                $statusBadge = '<span class="badge bg-warning text-dark">' . htmlspecialchars($row['current_status']) . '</span>';
                            }

                            // Supervisor Assignment text
                            $supText = '<span class="text-muted small">Not Assigned</span>';
                            if ($row['supervisor_name']) {
                                $supText = '<div class="fw-bold text-sm text-success">' . htmlspecialchars($row['supervisor_name']) . '</div>'
                                         . '<span class="badge bg-light text-dark text-xs">' . htmlspecialchars($row['supervisor_status'] ?: 'Assigned') . '</span>';
                            }
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="applicant-checkbox" value="<?= $row['application_id'] ?>">
                                </td>
                                <td>
                                    <div class="fw-bold"><?= $fullName ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-muted text-sm"><?= htmlspecialchars($row['course_title']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($row['faculty_name']) ?></div>
                                </td>
                                <td><?= $supText ?></td>
                                <td><code><?= htmlspecialchars($row['application_number']) ?></code></td>
                                <td><?= $statusBadge ?></td>
                                <td class="text-end">
                                    <button class="btn btn-outline-primary btn-sm" onclick="openReviewModal(<?= $row['application_id'] ?>)">
                                        <i class="fas fa-gavel me-1"></i> Review & Approve
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No candidates found in the postgraduate evaluation queue.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav class="d-flex justify-content-end mt-4">
                <ul class="pagination mb-0">
                    <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?status=<?= urlencode($filterStatus) ?>&faculty_id=<?= urlencode($filterFaculty) ?>&dept_id=<?= urlencode($filterDept) ?>&search=<?= urlencode($searchTerm) ?>&page=<?= $currentPage - 1 ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($currentPage == $i) ? 'active' : '' ?>">
                            <a class="page-link" href="?status=<?= urlencode($filterStatus) ?>&faculty_id=<?= urlencode($filterFaculty) ?>&dept_id=<?= urlencode($filterDept) ?>&search=<?= urlencode($searchTerm) ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?status=<?= urlencode($filterStatus) ?>&faculty_id=<?= urlencode($filterFaculty) ?>&dept_id=<?= urlencode($filterDept) ?>&search=<?= urlencode($searchTerm) ?>&page=<?= $currentPage + 1 ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</section>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header bg-light border-bottom">
                <div>
                    <h5 class="modal-title fw-bold" id="modalApplicantName">Postgraduate Evaluation Board</h5>
                    <div class="text-muted small">Application Number: <code id="modalAppNumber">-</code></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold mb-0">Program / Degree</label>
                        <div id="modalDegreeCourse" class="text-muted">Loading...</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold mb-0">Supervisor Assigned</label>
                        <div id="modalSupervisorInfo" class="text-muted">Loading...</div>
                    </div>
                </div>

                <hr>

                <h6 class="fw-bold mb-2">Applicant Uploaded Documents</h6>
                <div class="list-group mb-4" id="modalDocsList">
                    <!-- Populated by JS -->
                </div>

                <h6 class="fw-bold mb-2">Verification Action Trail</h6>
                <div class="p-3 bg-light rounded text-xs mb-4" id="modalLogsHistory" style="max-height: 180px; overflow-y: auto;">
                    <!-- Populated by JS -->
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Evaluation Remarks / Feedback</label>
                    <textarea class="form-control" id="pgRemarks" rows="3" placeholder="Enter evaluation comments, correction instructions or reasons for rejection..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light border-top d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-warning text-dark" onclick="submitPGDecision('correct')">
                        <i class="fas fa-edit me-1"></i> Request Correction
                    </button>
                    <button type="button" class="btn btn-danger" onclick="submitPGDecision('reject')">
                        <i class="fas fa-times-circle me-1"></i> Decline Application
                    </button>
                    <button type="button" class="btn btn-success" onclick="submitPGDecision('approve')">
                        <i class="fas fa-check-double me-1"></i> Grant Admission
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentApplicationId = null;

// Filter cascade
document.getElementById('filterFaculty').addEventListener('change', function() {
    const val = this.value;
    const deptSelect = document.getElementById('filterDept');
    Array.from(deptSelect.options).forEach(opt => {
        if (opt.value === '') {
            opt.style.display = 'block';
            return;
        }
        const fac = opt.dataset.faculty;
        if (val === '' || fac === val) {
            opt.style.display = 'block';
        } else {
            opt.style.display = 'none';
        }
    });
    deptSelect.value = '';
});

// Select All Checkbox
document.getElementById('selectAll').addEventListener('change', function() {
    const checked = this.checked;
    document.querySelectorAll('.applicant-checkbox').forEach(cb => {
        cb.checked = checked;
    });
});

async function openReviewModal(appId) {
    currentApplicationId = appId;
    const res = await fetch(`api/applications.php?action=details&application_id=${appId}`);
    const result = await res.json();
    if (!result.success) {
        alert(result.message || 'Failed to load details.');
        return;
    }

    const app = result.data.applicant;
    document.getElementById('modalApplicantName').textContent = `PG School Evaluation: ${app.first_name} ${app.surname}`;
    document.getElementById('modalAppNumber').textContent = app.application_number;
    document.getElementById('modalDegreeCourse').textContent = app.course_title;

    // Supervisor Text
    const supInfo = document.getElementById('modalSupervisorInfo');
    if (app.supervisor_name) {
        supInfo.innerHTML = `<strong>${app.supervisor_name}</strong> (${app.supervisor_status || 'Assigned'})<br><span class="text-xs text-muted">Assigned on: ${new Date(app.supervisor_assigned_at).toLocaleDateString()}</span>`;
    } else {
        supInfo.innerHTML = '<span class="text-muted">Not assigned yet.</span>';
    }

    // Load logs
    const logsHistory = document.getElementById('modalLogsHistory');
    logsHistory.innerHTML = '';
    if (result.data.logs.length) {
        result.data.logs.forEach(log => {
            const dateStr = new Date(log.timestamp).toLocaleString();
            logsHistory.innerHTML += `
                <div class="mb-2 pb-2 border-bottom text-muted">
                    <strong>${log.actor_name || log.role}</strong> - ${log.action} (${dateStr})
                    ${log.remarks ? `<div class="text-dark font-monospace">${log.remarks}</div>` : ''}
                </div>
            `;
        });
    } else {
        logsHistory.innerHTML = '<span class="text-muted">No audit logs recorded.</span>';
    }

    // Load documents
    const docsList = document.getElementById('modalDocsList');
    docsList.innerHTML = '';
    result.data.documents.forEach(doc => {
        const item = document.createElement('a');
        item.href = '../../' + doc.file_path;
        item.target = '_blank';
        item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3';
        item.innerHTML = `
            <div>
                <i class="fas fa-file-pdf text-danger me-2"></i>
                <span class="fw-bold">${doc.document_type.replace('_', ' ').toUpperCase()}</span>
            </div>
            <span class="badge bg-secondary">${doc.status || 'Verified'}</span>
        `;
        docsList.appendChild(item);
    });

    document.getElementById('pgRemarks').value = '';

    const myModal = new bootstrap.Modal(document.getElementById('reviewModal'));
    myModal.show();
}

async function submitPGDecision(decision) {
    if (!currentApplicationId) return;
    const remarks = document.getElementById('pgRemarks').value.trim();

    if (decision !== 'approve' && !remarks) {
        alert('Feedback / reason comment is required to request correction or reject candidates.');
        return;
    }

    let verb = 'approve';
    if (decision === 'reject') verb = 'decline';
    if (decision === 'correct') verb = 'request correction for';

    if (!confirm(`Are you sure you want to ${verb} this candidate?`)) return;

    const form = new FormData();
    form.append('action', 'decision');
    form.append('application_id', currentApplicationId);
    form.append('decision', decision);
    form.append('remarks', remarks);

    const res = await fetch('api/applications.php', { method: 'POST', body: form });
    const data = await res.json();

    if (data.success) {
        alert(`Evaluation saved. Candidate has been ${decision === 'approve' ? 'approved' : (decision === 'reject' ? 'rejected' : 'sent back for correction')}.`);
        location.reload();
    } else {
        alert(data.message || 'Failed to update candidate status.');
    }
}

async function performBulkAction(action) {
    const checked = Array.from(document.querySelectorAll('.applicant-checkbox:checked')).map(cb => cb.value);
    if (!checked.length) {
        alert('Please select at least one candidate first.');
        return;
    }

    let remarks = '';
    if (action === 'reject') {
        remarks = prompt('Enter rejection reason for selected candidates:');
        if (remarks === null) return;
        if (!remarks.trim()) {
            alert('Rejection reason is required.');
            return;
        }
    } else {
        if (!confirm(`Are you sure you want to bulk-approve all ${checked.length} selected candidates?`)) return;
    }

    const form = new FormData();
    form.append('action', 'bulk');
    form.append('bulk_action', action);
    form.append('remarks', remarks);
    checked.forEach(id => form.append('application_ids[]', id));

    const res = await fetch('api/applications.php', { method: 'POST', body: form });
    const data = await res.json();

    if (data.success) {
        alert(`Bulk PG School ${action === 'approve' ? 'endorsement' : 'rejection'} completed successfully.`);
        location.reload();
    } else {
        alert(data.message || 'Bulk endorsement failed.');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
