<?php
$pageTitle = 'Admissions Processing';
$pageSubtitle = 'Issue student identifiers, activate admission certificates, and register candidates.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
require_once __DIR__ . '/../../ADMIN/admin/includes/db.php';

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
        $whereClauses = ["a.status != 'Draft' AND a.current_status IN ('APPROVED_BY_POSTGRADUATE_SCHOOL', 'ADMISSION_APPROVED', 'Admitted')"];
        $params = [];

        if ($searchTerm !== '') {
            $whereClauses[] = "(pd.first_name LIKE :search OR pd.surname LIKE :search OR a.application_number LIKE :search)";
            $params[':search'] = '%' . $searchTerm . '%';
        }

        if ($filterStatus === 'Pending') {
            $whereClauses[] = "(ap.matric_number IS NULL OR ap.matric_number = '')";
        } elseif ($filterStatus === 'Processed') {
            $whereClauses[] = "(ap.matric_number IS NOT NULL AND ap.matric_number != '')";
        }

        $whereSql = implode(' AND ', $whereClauses);

        // Count query
        $countQuery = "
            SELECT COUNT(DISTINCT a.application_id)
            FROM applications a
            JOIN programme_choices pc ON a.application_id = pc.application_id
            JOIN personal_details pd ON a.application_id = pd.application_id
            LEFT JOIN admission_processing ap ON a.application_id = ap.application_id
            WHERE {$whereSql}
        ";
        $stmtCount = $pdo->prepare($countQuery);
        $stmtCount->execute($params);
        $totalApplicants = (int) $stmtCount->fetchColumn();

        // Data query
        $dataQuery = "
            SELECT DISTINCT a.application_id, a.application_number, a.status, a.current_status, a.submitted_at,
                   pd.first_name, pd.surname, u.email AS email,
                   d.dept_name, f.faculty_name, c.course_title,
                   ap.matric_number, ap.student_number, ap.acceptance_letter_status, ap.admission_letter_status
            FROM applications a
            JOIN programme_choices pc ON a.application_id = pc.application_id
            JOIN personal_details pd ON a.application_id = pd.application_id
            LEFT JOIN users u ON a.user_id = u.user_id
            LEFT JOIN departments d ON pc.department = d.dept_id
            LEFT JOIN faculties f ON pc.faculty = f.faculty_id
            LEFT JOIN courses c ON pc.course = c.course_id
            LEFT JOIN admission_processing ap ON a.application_id = ap.application_id
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
        error_log("ICT admissions list query error: " . $e->getMessage());
    }
}

$totalPages = ceil($totalApplicants / $limit) ?: 1;
?>

<section class="page-hero">
    <div>
        <h1>Admissions Registration Queue</h1>
        <p class="panel-muted">Select PG-approved students to issue matriculation numbers, activate letters, and finalize registration.</p>
    </div>
</section>

<!-- Filter Panel -->
<section class="panel mb-4">
    <div class="panel-body">
        <form method="GET" action="admissions.php" class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Processing State</label>
                <select name="status" class="form-select">
                    <option value="Pending" <?= $filterStatus === 'Pending' ? 'selected' : '' ?>>Awaiting Processing</option>
                    <option value="Processed" <?= $filterStatus === 'Processed' ? 'selected' : '' ?>>Admissions Processed</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label fw-semibold">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search by name, email or application number..." value="<?= htmlspecialchars($searchTerm) ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Filter Students</button>
            </div>
        </form>
    </div>
</section>

<!-- Bulk Actions & Listing -->
<section class="panel">
    <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h3 class="panel-title">Endorsement Registry</h3>
            <div class="panel-muted"><?= $totalApplicants ?> student(s) found.</div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-warning text-dark btn-sm" onclick="performBulkAction('matric')">
                <i class="fas fa-barcode me-1"></i> Bulk Generate Matrics
            </button>
            <button class="btn btn-success btn-sm" onclick="performBulkAction('activate')">
                <i class="fas fa-envelope-open-text me-1"></i> Bulk Activate Letters
            </button>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="40"><input type="checkbox" id="selectAll"></th>
                        <th>Student Name</th>
                        <th>Applied Course</th>
                        <th>Matriculation Number</th>
                        <th>Acceptance Letter</th>
                        <th>Admission Letter</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($applicants)): ?>
                        <?php foreach ($applicants as $row): ?>
                            <?php
                            $fullName = htmlspecialchars($row['first_name'] . ' ' . $row['surname']);
                            $matric = htmlspecialchars($row['matric_number'] ?? '');
                            
                            $acceptBadge = ($row['acceptance_letter_status'] === 'Active') 
                                ? '<span class="badge bg-success">Active</span>' 
                                : '<span class="badge bg-secondary">Inactive</span>';
                            
                            $admissionBadge = ($row['admission_letter_status'] === 'Active') 
                                ? '<span class="badge bg-success">Active</span>' 
                                : '<span class="badge bg-secondary">Inactive</span>';
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="applicant-checkbox" value="<?= $row['application_id'] ?>">
                                </td>
                                <td>
                                    <div class="fw-bold"><?= $fullName ?></div>
                                    <div class="text-muted small">Code: <code><?= htmlspecialchars($row['application_number']) ?></code></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-muted text-sm"><?= htmlspecialchars($row['course_title']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($row['dept_name']) ?></div>
                                </td>
                                <td>
                                    <?php if ($matric): ?>
                                        <code class="text-success fw-bold"><?= $matric ?></code>
                                    <?php else: ?>
                                        <span class="text-muted small">Not Generated</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $acceptBadge ?></td>
                                <td><?= $admissionBadge ?></td>
                                <td class="text-end">
                                    <button class="btn btn-outline-primary btn-sm" onclick="openProcessingModal(<?= $row['application_id'] ?>)">
                                        <i class="fas fa-id-card me-1"></i> Process Admission
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No student records found matching selected filters.</td>
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

<!-- Processing Modal -->
<div class="modal fade" id="processingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header bg-light border-bottom">
                <div>
                    <h5 class="modal-title fw-bold" id="modalStudentName">Register Candidate</h5>
                    <div class="text-muted small">Application Number: <code id="modalAppNumber">-</code></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Course details -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold mb-0">Program Choice</label>
                        <div id="modalDegreeCourse" class="text-muted">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold mb-0">Faculty</label>
                        <div id="modalFacultyName" class="text-muted">-</div>
                    </div>
                </div>

                <hr>

                <!-- Identifiers generation -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Matriculation Number</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="matricNumber" placeholder="E.g. IPESS/2026/MSC/0001">
                            <button class="btn btn-outline-primary" type="button" onclick="autoGenerateMatric()">
                                <i class="fas fa-magic me-1"></i> Auto
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Student Identifier</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="studentNumber" placeholder="E.g. STU-2026-9823">
                            <button class="btn btn-outline-primary" type="button" onclick="autoGenerateStudentNum()">
                                <i class="fas fa-magic me-1"></i> Auto
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Letter Activations -->
                <h6 class="fw-bold mb-3">Admission Certificates & Document Access</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="form-check form-switch p-3 bg-light rounded d-flex justify-content-between align-items-center">
                            <div class="ms-1">
                                <label class="form-check-label fw-bold" for="acceptanceLetterStatus">Acceptance Letter</label>
                                <div class="text-muted small">Enable candidate to view and print acceptance slips.</div>
                            </div>
                            <input class="form-check-input ms-3" type="checkbox" id="acceptanceLetterStatus" style="width: 2.5em; height: 1.25em;">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch p-3 bg-light rounded d-flex justify-content-between align-items-center">
                            <div class="ms-1">
                                <label class="form-check-label fw-bold" for="admissionLetterStatus">Admission Letter</label>
                                <div class="text-muted small">Enable candidate to print official admission certificate.</div>
                            </div>
                            <input class="form-check-input ms-3" type="checkbox" id="admissionLetterStatus" style="width: 2.5em; height: 1.25em;">
                        </div>
                    </div>
                </div>

                <!-- Preview Actions -->
                <div class="d-flex gap-2 justify-content-start border-top pt-3" id="letterActionsGroup" style="display:none !important;">
                    <a href="#" class="btn btn-outline-secondary btn-sm" id="btnPreviewLetter" target="_blank">
                        <i class="fas fa-eye me-1"></i> Preview Admission Letter
                    </a>
                    <button class="btn btn-outline-secondary btn-sm" onclick="printAdmissionLetter()">
                        <i class="fas fa-print me-1"></i> Print Letter
                    </button>
                    <button class="btn btn-outline-warning text-dark btn-sm" onclick="regenerateAdmissionLetter()">
                        <i class="fas fa-redo me-1"></i> Regenerate Letter
                    </button>
                </div>
            </div>
            <div class="modal-footer bg-light border-top">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveAdmissionsProcessing()">
                    <i class="fas fa-save me-1"></i> Save Registration Settings
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentApplicationId = null;
let currentAppNo = '';

// Select All
document.getElementById('selectAll').addEventListener('change', function() {
    const checked = this.checked;
    document.querySelectorAll('.applicant-checkbox').forEach(cb => {
        cb.checked = checked;
    });
});

async function openProcessingModal(appId) {
    currentApplicationId = appId;
    const res = await fetch(`api/admissions.php?action=details&application_id=${appId}`);
    const result = await res.json();
    if (!result.success) {
        alert(result.message || 'Failed to load details.');
        return;
    }

    const app = result.data.applicant;
    currentAppNo = app.application_number;
    document.getElementById('modalStudentName').textContent = `Register Candidate: ${app.first_name} ${app.surname}`;
    document.getElementById('modalAppNumber').textContent = app.application_number;
    document.getElementById('modalDegreeCourse').textContent = app.course_title;
    document.getElementById('modalFacultyName').textContent = app.faculty_name;

    // Load values
    document.getElementById('matricNumber').value = app.matric_number || '';
    document.getElementById('studentNumber').value = app.student_number || '';
    document.getElementById('acceptanceLetterStatus').checked = (app.acceptance_letter_status === 'Active');
    document.getElementById('admissionLetterStatus').checked = (app.admission_letter_status === 'Active');

    // Show/hide letters preview buttons group
    const actionsGroup = document.getElementById('letterActionsGroup');
    const previewBtn = document.getElementById('btnPreviewLetter');
    if (app.matric_number) {
        actionsGroup.style.setProperty('display', 'flex', 'important');
        previewBtn.href = `../generate-letter.php?app_no=${encodeURIComponent(app.application_number)}`;
    } else {
        actionsGroup.style.setProperty('display', 'none', 'important');
        previewBtn.href = '#';
    }

    const myModal = new bootstrap.Modal(document.getElementById('processingModal'));
    myModal.show();
}

async function autoGenerateMatric() {
    if (!currentApplicationId) return;
    const res = await fetch(`api/admissions.php?action=generate_matric&application_id=${currentApplicationId}`);
    const result = await res.json();
    if (result.success) {
        document.getElementById('matricNumber').value = result.matric;
    } else {
        alert(result.message || 'Failed to auto-generate.');
    }
}

async function autoGenerateStudentNum() {
    if (!currentApplicationId) return;
    const res = await fetch(`api/admissions.php?action=generate_student_num&application_id=${currentApplicationId}`);
    const result = await res.json();
    if (result.success) {
        document.getElementById('studentNumber').value = result.student_number;
    } else {
        alert(result.message || 'Failed to auto-generate.');
    }
}

async function saveAdmissionsProcessing() {
    if (!currentApplicationId) return;

    const matric = document.getElementById('matricNumber').value.trim();
    const stuNum = document.getElementById('studentNumber').value.trim();
    const acceptActive = document.getElementById('acceptanceLetterStatus').checked ? 'Active' : 'Inactive';
    const admitActive = document.getElementById('admissionLetterStatus').checked ? 'Active' : 'Inactive';

    if (!matric || !stuNum) {
        alert('Matriculation number and Student identifier are required.');
        return;
    }

    const form = new FormData();
    form.append('action', 'save');
    form.append('application_id', currentApplicationId);
    form.append('matric_number', matric);
    form.append('student_number', stuNum);
    form.append('acceptance_letter_status', acceptActive);
    form.append('admission_letter_status', admitActive);

    const res = await fetch('api/admissions.php', { method: 'POST', body: form });
    const data = await res.json();

    if (data.success) {
        alert('Registration settings saved successfully.');
        location.reload();
    } else {
        alert(data.message || 'Failed to save settings.');
    }
}

function printAdmissionLetter() {
    if (!currentAppNo) return;
    const url = `../generate-letter.php?app_no=${encodeURIComponent(currentAppNo)}&print=true`;
    window.open(url, '_blank');
}

async function regenerateAdmissionLetter() {
    if (!currentApplicationId) return;
    if (!confirm('Are you sure you want to regenerate and sign this letter?')) return;
    
    const form = new FormData();
    form.append('action', 'regenerate_letter');
    form.append('application_id', currentApplicationId);

    const res = await fetch('api/admissions.php', { method: 'POST', body: form });
    const data = await res.json();

    if (data.success) {
        alert('Letter regenerated successfully.');
        openProcessingModal(currentApplicationId);
    } else {
        alert(data.message || 'Failed to regenerate.');
    }
}

async function performBulkAction(action) {
    const checked = Array.from(document.querySelectorAll('.applicant-checkbox:checked')).map(cb => cb.value);
    if (!checked.length) {
        alert('Select at least one candidate first.');
        return;
    }

    if (!confirm(`Are you sure you want to perform bulk ${action === 'matric' ? 'matric generation' : 'letter activation'} for all ${checked.length} selected candidates?`)) return;

    const form = new FormData();
    form.append('action', 'bulk');
    form.append('bulk_action', action);
    checked.forEach(id => form.append('application_ids[]', id));

    const res = await fetch('api/admissions.php', { method: 'POST', body: form });
    const data = await res.json();

    if (data.success) {
        alert(`Bulk action completed successfully. ${data.message || ''}`);
        location.reload();
    } else {
        alert(data.message || 'Bulk action failed.');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
