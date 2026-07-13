<?php
$pageTitle = 'Faculty Review Console';
$pageSubtitle = 'Perform administrative evaluations on department-approved applications.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
require_once __DIR__ . '/../../ADMIN/admin/includes/db.php';

$facultyId = null;
if (isset($_SESSION['faculty_id'])) {
    $facultyId = $_SESSION['faculty_id'];
} else if (isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT faculty_id FROM users WHERE user_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $fVal = $stmt->fetchColumn();
        if ($fVal !== false && $fVal !== null) {
            $_SESSION['faculty_id'] = (int) $fVal;
            $facultyId = (int) $fVal;
        }
    } catch (PDOException $e) {}
}

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

        if ($facultyId) {
            $whereClauses[] = "pc.faculty = :faculty";
            $params[':faculty'] = $facultyId;
        }

        if ($searchTerm !== '') {
            $whereClauses[] = "(pd.first_name LIKE :search OR pd.surname LIKE :search OR a.application_number LIKE :search)";
            $params[':search'] = '%' . $searchTerm . '%';
        }

        // Status filter logic
        if ($filterStatus === 'Pending') {
            $whereClauses[] = "a.current_status IN ('DEPT_APPROVED', 'UNDER_FACULTY_REVIEW')";
        } elseif ($filterStatus === 'Approved') {
            $whereClauses[] = "a.current_status IN ('FACULTY_APPROVED', 'APPROVED_BY_FACULTY', 'APPROVED_BY_POSTGRADUATE_SCHOOL', 'ADMISSION_APPROVED', 'Admitted')";
        } elseif ($filterStatus === 'Rejected') {
            $whereClauses[] = "a.current_status IN ('FACULTY_REJECTED', 'REJECTED_BY_POSTGRADUATE_SCHOOL', 'ADMISSION_REJECTED', 'Rejected')";
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
                   d.dept_name, c.course_title
            FROM applications a
            JOIN programme_choices pc ON a.application_id = pc.application_id
            JOIN personal_details pd ON a.application_id = pd.application_id
            LEFT JOIN departments d ON pc.department = d.dept_id
            LEFT JOIN courses c ON pc.course = c.course_id
            WHERE {$whereSql}
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
        error_log("Faculty Applications Fetch Error: " . $e->getMessage());
    }
}

$totalPages = ceil($totalApplicants / $limit) ?: 1;
?>

<section class="page-hero">
    <div>
        <h1>Faculty Verification Queue</h1>
        <p class="panel-muted">Select department-approved candidates, review credentials, and approve them for Postgraduate School decision.</p>
    </div>
</section>

<!-- Filter Panel -->
<section class="panel mb-4">
    <div class="panel-body">
        <form method="GET" action="applications.php" class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Admissions Status</label>
                <select name="status" class="form-select">
                    <option value="Pending" <?= $filterStatus === 'Pending' ? 'selected' : '' ?>>Awaiting Faculty Review</option>
                    <option value="Approved" <?= $filterStatus === 'Approved' ? 'selected' : '' ?>>Approved by Faculty</option>
                    <option value="Rejected" <?= $filterStatus === 'Rejected' ? 'selected' : '' ?>>Rejected at Faculty</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label fw-semibold">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search by name, email or application number..." value="<?= htmlspecialchars($searchTerm) ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Filter Candidates</button>
            </div>
        </form>
    </div>
</section>

<!-- Bulk Actions & Listing -->
<section class="panel">
    <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h3 class="panel-title">Endorsement List</h3>
            <div class="panel-muted"><?= $totalApplicants ?> candidate(s) awaiting or evaluated.</div>
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
                        <th>Applicant details</th>
                        <th>Applied Course</th>
                        <th>Application Code</th>
                        <th>Current Status</th>
                        <th>Submitted At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($applicants)): ?>
                        <?php foreach ($applicants as $row): ?>
                            <?php
                            $fullName = htmlspecialchars($row['first_name'] . ' ' . $row['surname']);
                            $statusBadge = '';
                            if ($row['current_status'] === 'DEPT_APPROVED') {
                                $statusBadge = '<span class="badge bg-info">Endorsed by HOD</span>';
                            } elseif ($row['current_status'] === 'FACULTY_APPROVED' || $row['current_status'] === 'APPROVED_BY_FACULTY') {
                                $statusBadge = '<span class="badge bg-success">Faculty Endorsed</span>';
                            } elseif ($row['current_status'] === 'FACULTY_REJECTED') {
                                $statusBadge = '<span class="badge bg-danger">Faculty Rejected</span>';
                            } else {
                                $statusBadge = '<span class="badge bg-warning text-dark">' . htmlspecialchars($row['current_status']) . '</span>';
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
                                    <div class="text-muted small"><?= htmlspecialchars($row['dept_name']) ?></div>
                                </td>
                                <td><code><?= htmlspecialchars($row['application_number']) ?></code></td>
                                <td><?= $statusBadge ?></td>
                                <td class="text-muted small"><?= date('M d, Y', strtotime($row['submitted_at'])) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-outline-primary btn-sm" onclick="openReviewModal(<?= $row['application_id'] ?>)">
                                        <i class="fas fa-user-edit me-1"></i> Review & Endorse
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No candidates found in the faculty verification queue.</td>
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
                        <a class="page-link" href="?status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($searchTerm) ?>&page=<?= $currentPage - 1 ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($currentPage == $i) ? 'active' : '' ?>">
                            <a class="page-link" href="?status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($searchTerm) ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($searchTerm) ?>&page=<?= $currentPage + 1 ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</section>

<!-- Endorsement Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header bg-light border-bottom">
                <div>
                    <h5 class="modal-title fw-bold" id="modalApplicantName">Applicant Endorsement Panel</h5>
                    <div class="text-muted small">Application Number: <code id="modalAppNumber">-</code></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Info Section -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold mb-0">Program / Degree</label>
                        <div id="modalDegreeCourse" class="text-muted">Loading...</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold mb-0">Academic Status</label>
                        <div id="modalAppStatus" class="text-muted">Loading...</div>
                    </div>
                </div>

                <hr>

                <!-- Credentials Panel -->
                <h6 class="fw-bold mb-2">Verification Credentials</h6>
                <div class="list-group mb-4" id="modalDocsList">
                    <!-- Populated by JS -->
                </div>

                <!-- Logs History -->
                <h6 class="fw-bold mb-2">Admission Log Trail</h6>
                <div class="p-3 bg-light rounded text-xs mb-4" id="modalLogsHistory" style="max-height: 180px; overflow-y: auto;">
                    <!-- Populated by JS -->
                </div>

                <!-- Comments Form -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Faculty Remarks / Assessment Comments</label>
                    <textarea class="form-control" id="facultyRemarks" rows="3" placeholder="Enter comments, assessment notes or reasons for rejection..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light border-top d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-danger" onclick="submitFacultyDecision('reject')">
                        <i class="fas fa-times-circle me-1"></i> Decline Application
                    </button>
                    <button type="button" class="btn btn-success" onclick="submitFacultyDecision('approve')">
                        <i class="fas fa-check-circle me-1"></i> Endorse & Approve
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentApplicationId = null;

// Select All
document.getElementById('selectAll').addEventListener('change', function() {
    const checked = this.checked;
    document.querySelectorAll('.applicant-checkbox').forEach(cb => {
        cb.checked = checked;
    });
});

// Load details
async function openReviewModal(appId) {
    currentApplicationId = appId;
    const res = await fetch(`api/applications.php?action=details&application_id=${appId}`);
    const result = await res.json();
    if (!result.success) {
        alert(result.message || 'Failed to load details.');
        return;
    }

    const app = result.data.applicant;
    document.getElementById('modalApplicantName').textContent = `Faculty Endorsement: ${app.first_name} ${app.surname}`;
    document.getElementById('modalAppNumber').textContent = app.application_number;
    document.getElementById('modalDegreeCourse').textContent = app.course_title;
    document.getElementById('modalAppStatus').textContent = app.current_status;

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
        logsHistory.innerHTML = '<span class="text-muted">No audit logs recorded yet.</span>';
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

    document.getElementById('facultyRemarks').value = '';

    const myModal = new bootstrap.Modal(document.getElementById('reviewModal'));
    myModal.show();
}

async function submitFacultyDecision(decision) {
    if (!currentApplicationId) return;
    const remarks = document.getElementById('facultyRemarks').value.trim();

    if (decision === 'reject' && !remarks) {
        alert('Assessment comments / reason for rejection is required to decline an application.');
        return;
    }

    if (!confirm(`Are you sure you want to ${decision === 'approve' ? 'endorse' : 'decline'} this candidate?`)) return;

    const form = new FormData();
    form.append('action', 'decision');
    form.append('application_id', currentApplicationId);
    form.append('decision', decision);
    form.append('remarks', remarks);

    const res = await fetch('api/applications.php', { method: 'POST', body: form });
    const data = await res.json();

    if (data.success) {
        alert(`Candidate successfully ${decision === 'approve' ? 'endorsed' : 'declined'}.`);
        location.reload();
    } else {
        alert(data.message || 'Failed to update candidate status.');
    }
}

async function performBulkAction(action) {
    const checked = Array.from(document.querySelectorAll('.applicant-checkbox:checked')).map(cb => cb.value);
    if (!checked.length) {
        alert('Please select at least one candidate.');
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
        alert(`Bulk faculty ${action === 'approve' ? 'endorsement' : 'rejection'} completed successfully.`);
        location.reload();
    } else {
        alert(data.message || 'Bulk endorsement failed.');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
