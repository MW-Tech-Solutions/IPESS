<?php
$pageTitle = 'Application Records';
$pageSubtitle = 'Official record of all student application submissions.';

require_once 'db.php';

$q            = trim((string)($_GET['q'] ?? ''));
$filterStatus = trim($_GET['status'] ?? '');
$filterFaculty = (int)($_GET['faculty'] ?? 0);
$filterDept   = (int)($_GET['department'] ?? 0);
$filterYear   = (int)($_GET['year'] ?? 0);
$allowedStatus = ['Draft', 'Submitted', 'Admitted', 'Rejected'];
if (!in_array($filterStatus, $allowedStatus, true)) $filterStatus = '';

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = (int)($_GET['limit'] ?? 50);
if ($limit <= 0 || !in_array($limit, [50, 75, 100, 200], true)) {
    $limit = 50;
}

$stats        = ['total' => 0, 'submitted' => 0, 'admitted' => 0, 'rejected' => 0];
$records      = [];
$totalRecords = 0;
$totalPages   = 1;
$offset       = 0;
$faculties    = [];
$departments  = [];
$availableYears = [];

if ($pdo) {
    // Load filter options
    $faculties   = $pdo->query("SELECT faculty_id, faculty_name FROM faculties ORDER BY faculty_name")->fetchAll(PDO::FETCH_ASSOC);
    $departments = $pdo->query("SELECT dept_id, dept_name, faculty_id FROM departments ORDER BY dept_name")->fetchAll(PDO::FETCH_ASSOC);
    try {
        $availableYears = $pdo->query("SELECT DISTINCT YEAR(submitted_at) AS yr FROM applications WHERE submitted_at IS NOT NULL ORDER BY yr DESC")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {}

    // Stats
    $statsRow = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status='Admitted'  THEN 1 ELSE 0 END) AS admitted,
            SUM(CASE WHEN status='Submitted' THEN 1 ELSE 0 END) AS submitted,
            SUM(CASE WHEN status='Rejected'  THEN 1 ELSE 0 END) AS rejected
        FROM applications
        WHERE NOT EXISTS (
            SELECT 1 FROM applications nx WHERE nx.user_id = applications.user_id AND nx.application_id > applications.application_id
        )
    ")->fetch(PDO::FETCH_ASSOC);
    if ($statsRow) $stats = $statsRow;

    // Build filters
    $where  = ["NOT EXISTS (SELECT 1 FROM applications nx WHERE nx.user_id = a.user_id AND nx.application_id > a.application_id)"];
    $params = [];

    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[]  = "(u.email LIKE ? OR COALESCE(pd.first_name,'') LIKE ? OR COALESCE(pd.surname,'') LIKE ? OR COALESCE(a.application_number,'') LIKE ? OR COALESCE(pd.phone,'') LIKE ?)";
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($filterStatus)  { $where[] = 'a.status = ?';          $params[] = $filterStatus; }
    if ($filterFaculty) { $where[] = 'pc.faculty = ?';         $params[] = $filterFaculty; }
    if ($filterDept)    { $where[] = 'pc.department = ?';      $params[] = $filterDept; }
    if ($filterYear)    { $where[] = 'YEAR(a.submitted_at) = ?'; $params[] = $filterYear; }

    $joinSql = "
        FROM applications a
        INNER JOIN users u ON u.user_id = a.user_id
        LEFT JOIN personal_details pd ON pd.application_id = a.application_id
        LEFT JOIN programme_choices pc ON pc.application_id = a.application_id
        LEFT JOIN faculties f ON f.faculty_id = COALESCE(pc.faculty, 0)
        LEFT JOIN departments d ON d.dept_id = COALESCE(pc.department, a.department_id)
        LEFT JOIN courses c ON c.course_id = pc.course
        LEFT JOIN degree_types dt ON dt.degree_id = pc.degree_type
        WHERE " . implode(' AND ', $where);

    $cntStmt = $pdo->prepare("SELECT COUNT(DISTINCT a.application_id) $joinSql");
    $cntStmt->execute($params);
    $totalRecords = (int)$cntStmt->fetchColumn();
    $totalPages   = max(1, (int)ceil($totalRecords / $limit));
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $limit;

    $selStmt = $pdo->prepare("
        SELECT
            a.application_id, a.application_number, a.status, a.submitted_at, a.updated_at,
            u.email, u.full_name, u.user_id,
            pd.surname, pd.first_name, pd.other_name, pd.phone,
            f.faculty_name, d.dept_name, dt.degree_name, c.course_title
        $joinSql
        GROUP BY a.application_id
        ORDER BY a.updated_at DESC, a.application_id DESC
        LIMIT {$limit} OFFSET {$offset}
    ");
    $selStmt->execute($params);
    $records = $selStmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once 'includes/dev_header.php';
require_once 'includes/sidebar.php';
require_once 'includes/dev_topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Application Records</h1>
        <p class="panel-muted">Official record of all student applications. Search, filter, and view individual applications.</p>
    </div>
    <div class="hero-actions">
        <a href="records.php" class="btn btn-outline-secondary"><i class="fas fa-times me-1"></i>Clear Filters</a>
        <a href="export-students.php<?php echo $_SERVER['QUERY_STRING'] ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : ''; ?>" class="btn btn-success">
            <i class="fas fa-file-excel me-1"></i>Export
        </a>
        <button type="button" class="btn btn-warning text-white fw-semibold" onclick="startBulkProgressDownload()">
            <i class="fas fa-file-archive me-1"></i>Download PDFs (ZIP)
        </button>
    </div>
</section>

<!-- Stats -->
<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-folder-open"></i></div>
        <div><div class="stat-title">Total Records</div><div class="stat-value"><?php echo number_format((int)$stats['total']); ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="color:#f59e0b"><i class="fas fa-hourglass-half"></i></div>
        <div><div class="stat-title">Submitted</div><div class="stat-value"><?php echo number_format((int)$stats['submitted']); ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="color:#10b981"><i class="fas fa-graduation-cap"></i></div>
        <div><div class="stat-title">Admitted</div><div class="stat-value"><?php echo number_format((int)$stats['admitted']); ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="color:#ef4444"><i class="fas fa-times-circle"></i></div>
        <div><div class="stat-title">Rejected</div><div class="stat-value"><?php echo number_format((int)$stats['rejected']); ?></div></div>
    </div>
</section>

<!-- Search & Filter -->
<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Student Records</h3>
            <div class="panel-muted">Search by student name, email, application number, or phone number.</div>
        </div>
    </div>
    <div class="panel-body">
        <form method="get" class="row g-2 mb-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Search</label>
                <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="Student name, email, app number, phone...">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <?php foreach ($allowedStatus as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $filterStatus === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Faculty</label>
                <select class="form-select" name="faculty">
                    <option value="">All Faculties</option>
                    <?php foreach ($faculties as $fac): ?>
                        <option value="<?php echo $fac['faculty_id']; ?>" <?php echo $filterFaculty == $fac['faculty_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($fac['faculty_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Department</label>
                <select class="form-select" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['dept_id']; ?>" <?php echo $filterDept == $dept['dept_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['dept_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small fw-semibold text-muted">Year</label>
                <select class="form-select" name="year">
                    <option value="">All Years</option>
                    <?php foreach ($availableYears as $yr): ?>
                        <option value="<?php echo $yr; ?>" <?php echo $filterYear == $yr ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small fw-semibold text-muted">Per page</label>
                <select class="form-select" name="limit" onchange="this.form.submit()">
                    <?php foreach ([50, 75, 100, 200] as $l): ?>
                        <option value="<?php echo $l; ?>" <?php echo $limit === $l ? 'selected' : ''; ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-grid">
                <label class="form-label small fw-semibold text-muted">&nbsp;</label>
                <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="text-muted small">
                Showing <?php echo number_format(min($totalRecords, $offset + 1)); ?>
                to <?php echo number_format(min($totalRecords, $offset + $limit)); ?>
                of <?php echo number_format($totalRecords); ?> records
                <?php if ($q || $filterStatus || $filterFaculty || $filterDept || $filterYear): ?>
                    &mdash; <span class="text-primary fw-semibold">Filters active</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 40px">#</th>
                        <th>Student</th>
                        <th>App No</th>
                        <th>Phone</th>
                        <th>Programme / Dept</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th class="text-end" style="width: 170px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($records)): ?>
                        <?php foreach ($records as $i => $student): ?>
                            <?php
                            $studentName = trim(($student['full_name'] ?? ''));
                            if ($studentName === '') {
                                $studentName = trim(($student['surname'] ?? '') . ' ' . ($student['first_name'] ?? '') . ' ' . ($student['other_name'] ?? ''));
                            }
                            if ($studentName === '') $studentName = 'N/A';

                            $statusClass = 'status-muted';
                            if (($student['status'] ?? '') === 'Admitted')  $statusClass = 'status-success';
                            elseif (($student['status'] ?? '') === 'Rejected') $statusClass = 'status-danger';
                            elseif (($student['status'] ?? '') === 'Submitted') $statusClass = 'status-warning';

                            $programme = trim((($student['degree_name'] ?? '') . ' ' . ($student['course_title'] ?? '')));
                            if ($programme === '') $programme = $student['dept_name'] ?? 'N/A';
                            ?>
                            <tr>
                                <td class="text-muted small"><?php echo $offset + $i + 1; ?></td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars((string)($student['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                </td>
                                <td><code><?php echo htmlspecialchars((string)($student['application_number'] ?: 'N/A'), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                <td><?php echo htmlspecialchars((string)($student['phone'] ?: 'N/A'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($programme, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><span class="status-chip <?php echo $statusClass; ?>"><?php echo htmlspecialchars((string)($student['status'] ?: 'Unknown'), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td class="small text-muted">
                                    <?php echo $student['submitted_at'] ? date('M d, Y', strtotime($student['submitted_at'])) : '—'; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?php echo app_url('ipessadmin/view.php?app_no=' . urlencode($student['application_number'] ?? '')); ?>"
                                       class="btn btn-light btn-sm" target="_blank">
                                        <i class="fas fa-eye me-1"></i>View
                                    </a>
                                    <a href="manage-students.php?q=<?php echo urlencode($student['application_number'] ?? ''); ?>"
                                       class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-user-cog me-1"></i>Manage
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="fas fa-folder-open fa-2x mb-2 d-block opacity-25"></i>
                                No records found for the selected filters.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
            <div class="text-muted small">Page <?php echo $page; ?> of <?php echo $totalPages; ?></div>
            <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                        </li>
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage   = min($totalPages, $page + 2);
                        if ($startPage > 1): ?><li class="page-item"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a></li><?php endif;
                        if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif;
                        for ($pg = $startPage; $pg <= $endPage; $pg++): ?>
                            <li class="page-item <?php echo $page === $pg ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pg])); ?>"><?php echo $pg; ?></a>
                            </li>
                        <?php endfor;
                        if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif;
                        if ($endPage < $totalPages): ?><li class="page-item"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>"><?php echo $totalPages; ?></a></li><?php endif; ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Bulk Download Progress Modal -->
<div class="modal fade" id="bulkProgressModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0">
            <!-- High Contrast Primary Header -->
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold text-white d-flex align-items-center mb-0" id="bulkModalTitle">
                    <i class="fas fa-file-archive me-2 text-warning fs-4" id="bulkModalIcon"></i>
                    <span>Bulk Dossier PDF Generator</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" id="bulkModalCloseX"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- STEP 1: Range Setup Step -->
                <div id="bulkSetupStep">
                    <div class="alert alert-primary border-0 bg-primary-subtle text-primary-emphasis mb-3">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-info-circle fa-2x me-3 text-primary"></i>
                            <div>
                                <h6 class="fw-bold mb-1"><span id="bulkTotalFoundBadge" class="badge bg-primary fs-6">0</span> matching candidate records found</h6>
                                <div class="small">Select a record range to process into a downloadable ZIP archive (recommended: 100 to 200 per batch).</div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 bg-light p-3 mb-3">
                        <label class="form-label fw-bold text-dark mb-2">Record Range Selection</label>
                        <div class="row g-3 align-items-center mb-3">
                            <div class="col-md-5">
                                <label class="form-label small text-muted fw-semibold mb-1">Start Record</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fas fa-play text-muted"></i></span>
                                    <input type="number" id="bulkStartRange" class="form-control fw-bold" value="1" min="1" onchange="validateBulkRangeInput()">
                                </div>
                            </div>
                            <div class="col-md-2 text-center pt-3 fw-bold text-muted">&mdash; TO &mdash;</div>
                            <div class="col-md-5">
                                <label class="form-label small text-muted fw-semibold mb-1">End Record</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fas fa-stop text-muted"></i></span>
                                    <input type="number" id="bulkEndRange" class="form-control fw-bold" value="100" min="1" onchange="validateBulkRangeInput()">
                                </div>
                            </div>
                        </div>

                        <label class="form-label small text-muted fw-semibold mb-1">Quick Range Presets</label>
                        <div class="d-flex flex-wrap gap-2" id="bulkPresetButtons">
                            <!-- Preset pills injected dynamically -->
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Progress Execution Step -->
                <div id="bulkProgressStep" class="d-none">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-dark fs-6" id="bulkProgressLabel">Processing candidates...</span>
                        <span class="badge bg-primary fs-6 px-3 py-2" id="bulkProgressCounter">0 of 0</span>
                    </div>
                    <div class="progress mb-3" style="height: 26px; border-radius: 13px; background-color: #e9ecef;">
                        <div id="bulkProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-warning text-dark fw-bold" 
                             role="progressbar" style="width: 0%; transition: width 0.3s ease; font-size: 0.9rem;">0%</div>
                    </div>
                    <div class="alert alert-light border shadow-sm p-3 mb-0 d-flex align-items-center" id="bulkCurrentItemBox">
                        <i class="fas fa-file-pdf me-3 text-warning fs-3" id="bulkCurrentItemIcon"></i>
                        <div class="overflow-hidden">
                            <div class="fw-bold text-dark small" id="bulkCurrentItemText">Connecting to server...</div>
                            <div class="text-muted extra-small" id="bulkSubStatus">Generating application slips & uploaded documents...</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="modal-footer bg-light px-4 py-3">
                <button type="button" class="btn btn-outline-secondary px-4" id="bulkCancelBtn" onclick="cancelBulkProgress()">Cancel</button>
                <button type="button" class="btn btn-primary px-4 fw-bold" id="bulkStartProcessBtn" onclick="confirmStartBulkProcess()">
                    <i class="fas fa-rocket me-1"></i>Start Processing Range
                </button>
                <a href="#" id="bulkDirectDownloadLink" class="btn btn-success btn-lg px-4 fw-bold d-none" target="_blank">
                    <i class="fas fa-file-download me-2"></i>Download ZIP Archive
                </a>
            </div>
        </div>
    </div>
</div>

<script>
var bulkCancelRequested = false;
var bulkSessionId = '';
var bulkAllMatchingItems = [];
var bulkTotalFound = 0;

function startBulkProgressDownload() {
    bulkCancelRequested = false;
    bulkSessionId = '';
    bulkAllMatchingItems = [];
    bulkTotalFound = 0;

    var modalEl = document.getElementById('bulkProgressModal');
    var bsModal = new bootstrap.Modal(modalEl);

    // Reset UI to Setup Step
    document.getElementById('bulkModalIcon').className = 'fas fa-file-archive me-2 text-warning fs-4';
    document.getElementById('bulkModalTitle').innerHTML = '<i class="fas fa-file-archive me-2 text-warning fs-4"></i><span>Bulk Dossier PDF Generator</span>';
    document.getElementById('bulkSetupStep').classList.remove('d-none');
    document.getElementById('bulkProgressStep').classList.add('d-none');
    document.getElementById('bulkStartProcessBtn').classList.remove('d-none');
    document.getElementById('bulkStartProcessBtn').disabled = true;
    document.getElementById('bulkStartProcessBtn').innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Loading...';
    document.getElementById('bulkDirectDownloadLink').classList.add('d-none');
    document.getElementById('bulkCancelBtn').innerText = 'Cancel';

    bsModal.show();

    // Fetch init details
    var currentQuery = window.location.search;
    var initUrl = 'api/download-applicant.php' + (currentQuery ? currentQuery + '&' : '?') + 'action=init_bulk';

    fetch(initUrl)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.status !== 'success' || !data.items || data.items.length === 0) {
                alert(data.message || 'No applicants found matching active filters.');
                bsModal.hide();
                return;
            }

            bulkSessionId = data.session_id;
            bulkAllMatchingItems = data.items;
            bulkTotalFound = data.total;

            document.getElementById('bulkTotalFoundBadge').innerText = bulkTotalFound.toLocaleString();
            document.getElementById('bulkStartRange').value = 1;
            document.getElementById('bulkStartRange').max = bulkTotalFound;
            document.getElementById('bulkEndRange').value = Math.min(100, bulkTotalFound);
            document.getElementById('bulkEndRange').max = bulkTotalFound;

            // Generate presets
            var presetContainer = document.getElementById('bulkPresetButtons');
            presetContainer.innerHTML = '';

            var step = 100;
            for (var start = 1; start <= bulkTotalFound; start += step) {
                var end = Math.min(bulkTotalFound, start + step - 1);
                var pill = document.createElement('button');
                pill.type = 'button';
                pill.className = 'btn btn-sm btn-outline-primary fw-semibold';
                pill.innerText = start + ' - ' + end;
                (function(s, e) {
                    pill.onclick = function() {
                        document.getElementById('bulkStartRange').value = s;
                        document.getElementById('bulkEndRange').value = e;
                        validateBulkRangeInput();
                    };
                })(start, end);
                presetContainer.appendChild(pill);
            }

            if (bulkTotalFound > 0) {
                var allPill = document.createElement('button');
                allPill.type = 'button';
                allPill.className = 'btn btn-sm btn-outline-secondary fw-semibold';
                allPill.innerText = '1 - ' + bulkTotalFound + ' (All)';
                allPill.onclick = function() {
                    document.getElementById('bulkStartRange').value = 1;
                    document.getElementById('bulkEndRange').value = bulkTotalFound;
                    validateBulkRangeInput();
                };
                presetContainer.appendChild(allPill);
            }

            document.getElementById('bulkStartProcessBtn').disabled = false;
            document.getElementById('bulkStartProcessBtn').innerHTML = '<i class="fas fa-rocket me-1"></i>Start Processing Range';
        })
        .catch(function(err) {
            alert('Failed to initialize bulk download session.');
            bsModal.hide();
        });
}

function validateBulkRangeInput() {
    var start = parseInt(document.getElementById('bulkStartRange').value, 10) || 1;
    var end = parseInt(document.getElementById('bulkEndRange').value, 10) || 1;

    if (start < 1) start = 1;
    if (start > bulkTotalFound) start = bulkTotalFound;
    if (end < start) end = start;
    if (end > bulkTotalFound) end = bulkTotalFound;

    document.getElementById('bulkStartRange').value = start;
    document.getElementById('bulkEndRange').value = end;
}

function confirmStartBulkProcess() {
    validateBulkRangeInput();

    var start = parseInt(document.getElementById('bulkStartRange').value, 10);
    var end = parseInt(document.getElementById('bulkEndRange').value, 10);

    var activeItems = bulkAllMatchingItems.slice(start - 1, end);
    var totalRange = activeItems.length;

    if (totalRange === 0) {
        alert('Selected range has no records.');
        return;
    }

    // Switch UI to Progress Step
    document.getElementById('bulkSetupStep').classList.add('d-none');
    document.getElementById('bulkProgressStep').classList.remove('d-none');
    document.getElementById('bulkStartProcessBtn').classList.add('d-none');

    document.getElementById('bulkModalTitle').innerHTML = '<i class="fas fa-cog fa-spin me-2 text-warning fs-4"></i><span>Generating Dossier PDFs...</span>';
    document.getElementById('bulkProgressLabel').innerText = 'Processing candidates...';
    document.getElementById('bulkProgressCounter').innerText = '0 of ' + totalRange;
    document.getElementById('bulkProgressBar').style.width = '0%';
    document.getElementById('bulkProgressBar').innerText = '0%';
    document.getElementById('bulkProgressBar').className = 'progress-bar progress-bar-striped progress-bar-animated bg-warning text-dark fw-bold';

    var index = 0;

    function processNextItem() {
        if (bulkCancelRequested) {
            document.getElementById('bulkCurrentItemText').innerText = 'Download process cancelled by user.';
            document.getElementById('bulkSubStatus').innerText = 'Partial session saved.';
            return;
        }

        if (index >= totalRange) {
            // Range Processed Completely!
            var currentQuery = window.location.search;
            var rangeStr = start + '-' + end;
            var finalUrl = 'api/download-applicant.php' + (currentQuery ? currentQuery + '&' : '?') + 'action=finalize_bulk&session_id=' + encodeURIComponent(bulkSessionId) + '&range=' + encodeURIComponent(rangeStr);

            document.getElementById('bulkModalTitle').innerHTML = '<i class="fas fa-check-circle me-2 text-warning fs-4"></i><span>Dossier Range Prepared Successfully!</span>';
            document.getElementById('bulkProgressLabel').innerText = 'ZIP Archive Ready!';
            document.getElementById('bulkProgressCounter').innerText = totalRange + ' of ' + totalRange;
            document.getElementById('bulkProgressBar').style.width = '100%';
            document.getElementById('bulkProgressBar').innerText = '100%';
            document.getElementById('bulkProgressBar').className = 'progress-bar bg-success text-white fw-bold';
            document.getElementById('bulkCurrentItemText').innerText = 'ZIP file compiled! Click below to download.';
            document.getElementById('bulkSubStatus').innerText = 'Records ' + start + ' to ' + end + ' archived.';
            document.getElementById('bulkCancelBtn').innerText = 'Close';

            document.getElementById('bulkDirectDownloadLink').href = finalUrl;
            document.getElementById('bulkDirectDownloadLink').classList.remove('d-none');

            // Trigger browser download
            window.location.href = finalUrl;
            return;
        }

        var item = activeItems[index];
        var currentNum = index + 1;
        var pct = Math.round((currentNum / totalRange) * 100);

        document.getElementById('bulkProgressCounter').innerText = currentNum + ' of ' + totalRange;
        document.getElementById('bulkProgressBar').style.width = pct + '%';
        document.getElementById('bulkProgressBar').innerText = pct + '%';
        document.getElementById('bulkCurrentItemText').innerText = 'Processing Record ' + (start + index) + ' (' + currentNum + ' of ' + totalRange + '): ' + item.name;
        document.getElementById('bulkSubStatus').innerText = 'Application #' + item.app_no;

        var processUrl = 'api/download-applicant.php?action=process_item&session_id=' + encodeURIComponent(bulkSessionId) + '&app_id=' + item.id;

        fetch(processUrl)
            .then(function(r) { return r.json(); })
            .catch(function(e) { /* continue */ })
            .then(function() {
                index++;
                processNextItem();
            });
    }

    processNextItem();
}

function cancelBulkProgress() {
    bulkCancelRequested = true;
}
</script>

<?php require_once 'includes/footer.php'; ?>