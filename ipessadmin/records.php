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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="bulkModalTitle">
                    <i class="fas fa-cog fa-spin me-2 text-warning" id="bulkModalIcon"></i>Preparing Dossier Download...
                </h5>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold text-secondary" id="bulkProgressLabel">Initializing session...</span>
                    <span class="badge bg-primary fs-6" id="bulkProgressCounter">0 of 0</span>
                </div>
                <div class="progress mb-3" style="height: 24px; border-radius: 12px; background-color: #e9ecef;">
                    <div id="bulkProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-warning text-dark fw-bold" 
                         role="progressbar" style="width: 0%; transition: width 0.3s ease; font-size: 0.85rem;">0%</div>
                </div>
                <div class="alert alert-light border small text-muted mb-0 d-flex align-items-center" id="bulkCurrentItemBox">
                    <i class="fas fa-file-pdf me-2 text-warning fs-5"></i>
                    <span id="bulkCurrentItemText" class="text-truncate">Connecting to server...</span>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" id="bulkCancelBtn" onclick="cancelBulkProgress()">Cancel</button>
                <a href="#" id="bulkDirectDownloadLink" class="btn btn-success d-none"><i class="fas fa-download me-1"></i>Save ZIP File</a>
            </div>
        </div>
    </div>
</div>

<script>
var bulkCancelRequested = false;

function startBulkProgressDownload() {
    bulkCancelRequested = false;
    
    var modalEl = document.getElementById('bulkProgressModal');
    var bsModal = new bootstrap.Modal(modalEl);
    
    // Reset UI
    document.getElementById('bulkModalIcon').className = 'fas fa-cog fa-spin me-2 text-warning';
    document.getElementById('bulkModalTitle').innerText = 'Preparing Dossier Download...';
    document.getElementById('bulkProgressLabel').innerText = 'Initializing session...';
    document.getElementById('bulkProgressCounter').innerText = '0 of 0';
    document.getElementById('bulkProgressBar').style.width = '0%';
    document.getElementById('bulkProgressBar').innerText = '0%';
    document.getElementById('bulkProgressBar').className = 'progress-bar progress-bar-striped progress-bar-animated bg-warning text-dark fw-bold';
    document.getElementById('bulkCurrentItemText').innerText = 'Fetching applicant list from server...';
    document.getElementById('bulkCancelBtn').innerText = 'Cancel';
    document.getElementById('bulkCancelBtn').classList.remove('d-none');
    document.getElementById('bulkDirectDownloadLink').classList.add('d-none');

    bsModal.show();

    // 1. Send init request with current page query params
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

            var sessionId = data.session_id;
            var items = data.items;
            var total = items.length;

            document.getElementById('bulkProgressCounter').innerText = '0 of ' + total;
            document.getElementById('bulkProgressLabel').innerText = 'Downloading dossiers...';

            var index = 0;

            function processNext() {
                if (bulkCancelRequested) {
                    document.getElementById('bulkCurrentItemText').innerText = 'Download cancelled by user.';
                    return;
                }

                if (index >= total) {
                    // All items processed!
                    document.getElementById('bulkModalIcon').className = 'fas fa-check-circle me-2 text-success';
                    document.getElementById('bulkModalTitle').innerText = 'Dossiers Prepared Successfully!';
                    document.getElementById('bulkProgressLabel').innerText = 'Compiling ZIP archive...';
                    document.getElementById('bulkProgressBar').style.width = '100%';
                    document.getElementById('bulkProgressBar').innerText = '100%';
                    document.getElementById('bulkProgressBar').className = 'progress-bar bg-success text-white fw-bold';
                    document.getElementById('bulkCurrentItemText').innerText = 'ZIP file compiled! Downloading automatically...';
                    document.getElementById('bulkCancelBtn').innerText = 'Close';

                    var finalUrl = 'api/download-applicant.php' + (currentQuery ? currentQuery + '&' : '?') + 'action=finalize_bulk&session_id=' + encodeURIComponent(sessionId);
                    document.getElementById('bulkDirectDownloadLink').href = finalUrl;
                    document.getElementById('bulkDirectDownloadLink').classList.remove('d-none');

                    // Trigger browser download
                    window.location.href = finalUrl;
                    return;
                }

                var item = items[index];
                var currentNum = index + 1;
                var pct = Math.round((currentNum / total) * 100);

                document.getElementById('bulkProgressCounter').innerText = currentNum + ' of ' + total;
                document.getElementById('bulkProgressBar').style.width = pct + '%';
                document.getElementById('bulkProgressBar').innerText = pct + '%';
                document.getElementById('bulkCurrentItemText').innerText = 'Processing candidate ' + currentNum + ' of ' + total + ': ' + item.name + ' (' + item.app_no + ')';

                var processUrl = 'api/download-applicant.php?action=process_item&session_id=' + encodeURIComponent(sessionId) + '&app_id=' + item.id;

                fetch(processUrl)
                    .then(function(r) { return r.json(); })
                    .catch(function(e) { /* continue on error */ })
                    .then(function() {
                        index++;
                        processNext();
                    });
            }

            processNext();
        })
        .catch(function(err) {
            alert('Failed to initialize bulk download session.');
            bsModal.hide();
        });
}

function cancelBulkProgress() {
    bulkCancelRequested = true;
}
</script>

<?php require_once 'includes/footer.php'; ?>