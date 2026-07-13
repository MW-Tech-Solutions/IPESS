<?php
$pageTitle = 'Document Verification Desk';
$pageSubtitle = 'Review applicant credentials, verify submissions, or reject files with comments.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
require_once __DIR__ . '/../../ADMIN/admin/includes/db.php';

// Fetch faculties and departments for filters
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
$filterStatus = $_GET['status'] ?? 'Pending'; // Default to Pending
$searchTerm = trim($_GET['search'] ?? '');
$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
if ($currentPage === false || $currentPage === null || $currentPage < 1) {
    $currentPage = 1;
}

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

        // Pending vs Verified vs Rejected status filter logic
        if ($filterStatus === 'Pending') {
            $whereClauses[] = "(ap.stage_status IS NULL OR ap.stage_status != 'Completed') AND a.status != 'Draft'";
        } elseif ($filterStatus === 'Verified') {
            $whereClauses[] = "ap.stage_status = 'Completed'";
        } elseif ($filterStatus === 'Rejected') {
            $whereClauses[] = "a.current_status = 'ACTION_REQUIRED_DOCS'";
        }

        $whereSql = implode(' AND ', $whereClauses);

        // Count query
        $countQuery = "
            SELECT COUNT(DISTINCT a.application_id)
            FROM applications a
            JOIN programme_choices pc ON a.application_id = pc.application_id
            JOIN personal_details pd ON a.application_id = pd.application_id
            LEFT JOIN application_progress ap ON a.application_id = ap.application_id AND ap.stage = 'Documents Verification'
            WHERE {$whereSql}
        ";
        $stmtCount = $pdo->prepare($countQuery);
        $stmtCount->execute($params);
        $totalApplicants = (int) $stmtCount->fetchColumn();

        // Data query
        $dataQuery = "
            SELECT DISTINCT a.application_id, a.application_number, a.status, a.current_status, a.submitted_at,
                   pd.first_name, pd.surname,
                   d.dept_name, f.faculty_name,
                   ap.stage_status AS verify_stage_status
            FROM applications a
            JOIN programme_choices pc ON a.application_id = pc.application_id
            JOIN personal_details pd ON a.application_id = pd.application_id
            LEFT JOIN departments d ON pc.department = d.dept_id
            LEFT JOIN faculties f ON pc.faculty = f.faculty_id
            LEFT JOIN application_progress ap ON a.application_id = ap.application_id AND ap.stage = 'Documents Verification'
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
        error_log("ICTO Verification Fetch Error: " . $e->getMessage());
    }
}

$totalPages = ceil($totalApplicants / $limit) ?: 1;
?>

<section class="page-hero">
    <div>
        <h1>Credentials Verification Queue</h1>
        <p class="panel-muted">Review, verify, or decline applicant documents. Use bulk operations to process multiple candidates at once.</p>
    </div>
</section>

<!-- Filter Panel -->
<section class="panel mb-4">
    <div class="panel-body">
        <form method="GET" action="document-verification.php" class="row g-3">
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
                <label class="form-label fw-semibold">Verification Status</label>
                <select name="status" class="form-select">
                    <option value="Pending" <?= $filterStatus === 'Pending' ? 'selected' : '' ?>>Pending Verification</option>
                    <option value="Verified" <?= $filterStatus === 'Verified' ? 'selected' : '' ?>>Verified Applicants</option>
                    <option value="Rejected" <?= $filterStatus === 'Rejected' ? 'selected' : '' ?>>Rejected / Action Req.</option>
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
            <h3 class="panel-title">Applicants List</h3>
            <div class="panel-muted"><?= $totalApplicants ?> candidates found.</div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-success btn-sm" onclick="performBulkAction('verify')">
                <i class="fas fa-check-circle me-1"></i> Bulk Verify
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
                        <th>Faculty / Department</th>
                        <th>Application Number</th>
                        <th>Stage Status</th>
                        <th>Submission Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($applicants)): ?>
                        <?php foreach ($applicants as $row): ?>
                            <?php
                            $fullName = htmlspecialchars($row['first_name'] . ' ' . $row['surname']);
                            $stageBadge = '';
                            if ($row['verify_stage_status'] === 'Completed') {
                                $stageBadge = '<span class="badge bg-success">Verified</span>';
                            } elseif ($row['current_status'] === 'ACTION_REQUIRED_DOCS') {
                                $stageBadge = '<span class="badge bg-danger">Action Required</span>';
                            } else {
                                $stageBadge = '<span class="badge bg-warning text-dark">Pending</span>';
                            }
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="applicant-checkbox" value="<?= $row['application_id'] ?>" 
                                           data-dept="<?= $row['dept_name'] ?>" data-faculty="<?= $row['faculty_name'] ?>">
                                </td>
                                <td>
                                    <div class="fw-bold"><?= $fullName ?></div>
                                    <div class="text-muted small">Status: <?= htmlspecialchars($row['current_status']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-muted text-sm"><?= htmlspecialchars($row['faculty_name']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($row['dept_name']) ?></div>
                                </td>
                                <td><code><?= htmlspecialchars($row['application_number']) ?></code></td>
                                <td><?= $stageBadge ?></td>
                                <td class="text-muted small"><?= date('M d, Y H:i', strtotime($row['submitted_at'])) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-outline-primary btn-sm" onclick="openReviewModal(<?= $row['application_id'] ?>)">
                                        <i class="fas fa-eye me-1"></i> Review & Verify
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No applicants pending verification match the selected filters.</td>
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
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content border-0">
            <div class="modal-header bg-light border-bottom">
                <div>
                    <h5 class="modal-title fw-bold" id="modalApplicantName">Verify Credentials</h5>
                    <div class="text-muted small">Application Number: <code id="modalAppNumber">-</code></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row h-100 g-0">
                    <!-- Left: Docs list & controls -->
                    <div class="col-lg-4 border-end d-flex flex-column h-100 bg-white" style="overflow-y: auto;">
                        <div class="p-3 border-bottom bg-light">
                            <h6 class="fw-bold mb-1">Uploaded Credentials</h6>
                            <p class="text-muted small mb-0">Select a document to preview and update status.</p>
                        </div>
                        <div class="list-group list-group-flush flex-grow-1" id="modalDocsList">
                            <!-- Populated by JS -->
                        </div>
                        
                        <!-- History Log panel -->
                        <div class="p-3 border-top bg-light">
                            <h6 class="fw-bold mb-2">Verification Logs</h6>
                            <div class="small" id="modalLogsHistory" style="max-height: 150px; overflow-y: auto;">
                                <!-- Populated by JS -->
                            </div>
                        </div>

                        <!-- Main Submit Button to push the application to the next stage -->
                        <div class="p-3 border-top bg-white mt-auto">
                            <div class="d-grid gap-2">
                                <button class="btn btn-success" id="btnCompleteVerification" onclick="submitFinalVerification()">
                                    <i class="fas fa-check-double me-1"></i> Finalize Verification Stage
                                </button>
                                <button class="btn btn-outline-danger" id="btnRejectVerification" onclick="submitFinalRejection()">
                                    <i class="fas fa-times-circle me-1"></i> Send Back / Reject Application
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Doc preview and verify tool -->
                    <div class="col-lg-8 d-flex flex-column h-100 bg-light">
                        <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0" id="currentDocTitle">Select a document to begin</h6>
                            <a href="#" class="btn btn-outline-secondary btn-sm" id="btnDownloadDoc" target="_blank" style="display:none;">
                                <i class="fas fa-download me-1"></i> Download File
                            </a>
                        </div>
                        <div class="flex-grow-1 d-flex justify-content-center align-items-center" style="height: 60vh; overflow: hidden; position: relative;">
                            <div class="document-placeholder text-center text-muted" id="previewPlaceholder">
                                <i class="fas fa-file-pdf fa-4x mb-3 text-secondary"></i>
                                <div>Select a document from the left list to load the live preview.</div>
                            </div>
                            <iframe id="previewIframe" src="about:blank" style="width:100%; height:100%; border:none; display:none;"></iframe>
                            <img id="previewImage" src="" style="max-width:100%; max-height:100%; object-fit:contain; display:none;" alt="Preview">
                        </div>

                        <!-- Verification form panel -->
                        <div class="p-3 bg-white border-top" id="docDecisionContainer" style="display:none;">
                            <div class="row align-items-center g-3">
                                <input type="hidden" id="currentDocId" value="">
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Verification Decision</label>
                                    <select id="docVerifyStatus" class="form-select">
                                        <option value="Pending">Pending</option>
                                        <option value="Verified">Verified (Accept)</option>
                                        <option value="Rejected">Rejected (Decline)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Remarks / Comments</label>
                                    <input type="text" id="docVerifyRemarks" class="form-control" placeholder="Add comment/reason for rejection...">
                                </div>
                                <div class="col-md-3 d-flex align-items-end pt-4">
                                    <button class="btn btn-primary w-100" onclick="saveDocumentVerification()">
                                        <i class="fas fa-save me-1"></i> Update Status
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentApplicationId = null;
let currentDocs = [];

// Filter cascade logic
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

// Load Review Modal details
async function openReviewModal(appId) {
    currentApplicationId = appId;
    const res = await fetch(`api/verify.php?action=details&application_id=${appId}`);
    const result = await res.json();
    if (!result.success) {
        alert(result.message || 'Failed to load details.');
        return;
    }

    const app = result.data.applicant;
    document.getElementById('modalApplicantName').textContent = `Verify Credentials: ${app.first_name} ${app.surname}`;
    document.getElementById('modalAppNumber').textContent = app.application_number;

    // Load logs
    const logsHistory = document.getElementById('modalLogsHistory');
    logsHistory.innerHTML = '';
    if (result.data.logs.length) {
        result.data.logs.forEach(log => {
            const dateStr = new Date(log.timestamp).toLocaleString();
            logsHistory.innerHTML += `
                <div class="mb-2 pb-2 border-bottom text-muted" style="font-size:0.75rem;">
                    <strong>${log.actor_name || log.role}</strong> - ${log.action} (${dateStr})
                    ${log.remarks ? `<div class="text-dark font-monospace">${log.remarks}</div>` : ''}
                </div>
            `;
        });
    } else {
        logsHistory.innerHTML = '<span class="text-muted">No verification logs available.</span>';
    }

    // Load docs
    currentDocs = result.data.documents;
    const docsList = document.getElementById('modalDocsList');
    docsList.innerHTML = '';

    currentDocs.forEach(doc => {
        let badgeColor = 'bg-warning text-dark';
        if (doc.status === 'Verified') badgeColor = 'bg-success';
        if (doc.status === 'Rejected') badgeColor = 'bg-danger';

        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'list-group-item list-group-item-action p-3';
        item.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <span class="fw-bold">${doc.document_type.replace('_', ' ').toUpperCase()}</span>
                <span class="badge ${badgeColor}">${doc.status || 'Pending'}</span>
            </div>
            <div class="small text-muted mt-1">Uploaded: ${new Date(doc.uploaded_at).toLocaleDateString()}</div>
        `;
        item.addEventListener('click', () => selectDocument(doc, item));
        docsList.appendChild(item);
    });

    // Reset preview panel
    resetPreview();

    const myModal = new bootstrap.Modal(document.getElementById('reviewModal'));
    myModal.show();
}

function selectDocument(doc, element) {
    document.querySelectorAll('#modalDocsList .list-group-item').forEach(item => item.classList.remove('active'));
    element.classList.add('active');

    document.getElementById('currentDocTitle').textContent = doc.document_type.replace('_', ' ').toUpperCase();
    document.getElementById('currentDocId').value = doc.doc_id;
    document.getElementById('docVerifyStatus').value = doc.status || 'Pending';
    document.getElementById('docVerifyRemarks').value = doc.comments || '';

    // Show decision controls
    document.getElementById('docDecisionContainer').style.display = 'block';

    const dlBtn = document.getElementById('btnDownloadDoc');
    if (doc.file_path) {
        dlBtn.href = '../../' + doc.file_path;
        dlBtn.style.display = 'inline-block';

        const ext = doc.file_path.split('.').pop().toLowerCase();
        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
            document.getElementById('previewIframe').style.display = 'none';
            document.getElementById('previewImage').src = '../../' + doc.file_path;
            document.getElementById('previewImage').style.display = 'block';
            document.getElementById('previewPlaceholder').style.display = 'none';
        } else {
            document.getElementById('previewImage').style.display = 'none';
            document.getElementById('previewIframe').src = '../../' + doc.file_path;
            document.getElementById('previewIframe').style.display = 'block';
            document.getElementById('previewPlaceholder').style.display = 'none';
        }
    } else {
        dlBtn.style.display = 'none';
        resetPreview();
    }
}

function resetPreview() {
    document.getElementById('previewIframe').style.display = 'none';
    document.getElementById('previewIframe').src = 'about:blank';
    document.getElementById('previewImage').style.display = 'none';
    document.getElementById('previewImage').src = '';
    document.getElementById('previewPlaceholder').style.display = 'block';
    document.getElementById('docDecisionContainer').style.display = 'none';
}

async function saveDocumentVerification() {
    const docId = document.getElementById('currentDocId').value;
    const status = document.getElementById('docVerifyStatus').value;
    const remarks = document.getElementById('docVerifyRemarks').value;

    if (!docId) return;

    const form = new FormData();
    form.append('action', 'update_doc_status');
    form.append('doc_id', docId);
    form.append('status', status);
    form.append('remarks', remarks);

    const res = await fetch('api/verify.php', { method: 'POST', body: form });
    const data = await res.json();

    if (data.success) {
        alert('Document status updated.');
        // Refresh docs list
        if (currentApplicationId) {
            // Get references to close modal and reload
            const modalEl = document.getElementById('reviewModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            await openReviewModal(currentApplicationId);
        }
    } else {
        alert(data.message || 'Failed to update status.');
    }
}

async function submitFinalVerification() {
    if (!currentApplicationId) return;
    if (!confirm('Are you sure you want to finalize the verification stage for this applicant? This will advance them to Department Review.')) return;

    const form = new FormData();
    form.append('action', 'finalize');
    form.append('application_id', currentApplicationId);

    const res = await fetch('api/verify.php', { method: 'POST', body: form });
    const data = await res.json();

    if (data.success) {
        alert('Verification stage finalized successfully. Candidate advanced to Department Review.');
        location.reload();
    } else {
        alert(data.message || 'Failed to finalize verification.');
    }
}

async function submitFinalRejection() {
    if (!currentApplicationId) return;
    const reason = prompt('Please enter a rejection comment to notify the applicant:');
    if (reason === null) return;
    if (!reason.trim()) {
        alert('Rejection reason is required.');
        return;
    }

    const form = new FormData();
    form.append('action', 'reject');
    form.append('application_id', currentApplicationId);
    form.append('remarks', reason);

    const res = await fetch('api/verify.php', { method: 'POST', body: form });
    const data = await res.json();

    if (data.success) {
        alert('Application has been rejected. Notification sent to candidate.');
        location.reload();
    } else {
        alert(data.message || 'Failed to process rejection.');
    }
}

async function performBulkAction(action) {
    const checked = Array.from(document.querySelectorAll('.applicant-checkbox:checked')).map(cb => cb.value);
    if (!checked.length) {
        alert('Select at least one applicant first.');
        return;
    }

    let remarks = '';
    if (action === 'reject') {
        remarks = prompt('Enter rejection reason for selected applicants:');
        if (remarks === null) return;
        if (!remarks.trim()) {
            alert('Rejection reason is required.');
            return;
        }
    } else {
        if (!confirm(`Are you sure you want to bulk-verify and advance all ${checked.length} selected applicants?`)) return;
    }

    const form = new FormData();
    form.append('action', 'bulk');
    form.append('bulk_action', action);
    form.append('remarks', remarks);
    checked.forEach(id => form.append('application_ids[]', id));

    const res = await fetch('api/verify.php', { method: 'POST', body: form });
    const data = await res.json();

    if (data.success) {
        alert(`Bulk ${action === 'verify' ? 'verification' : 'rejection'} processed successfully.`);
        location.reload();
    } else {
        alert(data.message || 'Bulk action failed.');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
