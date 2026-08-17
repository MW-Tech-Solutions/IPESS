<?php
$pageTitle = 'Applicant Directory';
$pageSubtitle = 'Browse and filter applicants by college, department, and programme.';

require_once 'db.php';

$q              = trim((string)($_GET['q'] ?? ''));
$filterFaculty  = (int)($_GET['faculty'] ?? 0);
$filterDept     = (int)($_GET['department'] ?? 0);
$filterCourse   = (int)($_GET['course'] ?? 0);
$filterStatus   = trim($_GET['status'] ?? '');
$allowedStatus  = ['Draft', 'Submitted', 'Admitted', 'Rejected'];
if (!in_array($filterStatus, $allowedStatus, true)) $filterStatus = '';
$page           = max(1, (int)($_GET['page'] ?? 1));
$limitRaw       = (int)($_GET['limit'] ?? 50);
$limit          = in_array($limitRaw, [25, 50, 100, 200]) ? $limitRaw : 50;

$faculties   = [];
$departments = [];
$courses     = [];
$stats       = ['total' => 0, 'submitted' => 0, 'admitted' => 0, 'rejected' => 0];
$students    = [];
$totalRecords = 0;
$totalPages   = 1;
$offset       = 0;

if ($pdo) {
    $faculties   = $pdo->query("SELECT faculty_id, faculty_name FROM faculties ORDER BY faculty_name")->fetchAll(PDO::FETCH_ASSOC);
    $departments = $pdo->query("SELECT dept_id, dept_name, faculty_id FROM departments ORDER BY dept_name")->fetchAll(PDO::FETCH_ASSOC);
    $courses     = $pdo->query("SELECT course_id, course_title, dept_id FROM courses ORDER BY course_title")->fetchAll(PDO::FETCH_ASSOC);

    $statsSql = "
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN a.status='Admitted'  THEN 1 ELSE 0 END) AS admitted,
            SUM(CASE WHEN a.status='Submitted' THEN 1 ELSE 0 END) AS submitted,
            SUM(CASE WHEN a.status='Rejected'  THEN 1 ELSE 0 END) AS rejected
        FROM applications a
        WHERE NOT EXISTS (
            SELECT 1 FROM applications nx
            WHERE nx.user_id = a.user_id AND nx.application_id > a.application_id
        )
    ";
    $stats = $pdo->query($statsSql)->fetch(PDO::FETCH_ASSOC) ?: $stats;

    $where  = ["NOT EXISTS (SELECT 1 FROM applications nx WHERE nx.user_id = a.user_id AND nx.application_id > a.application_id)"];
    $params = [];

    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[]  = "(u.email LIKE ? OR COALESCE(pd.first_name,'') LIKE ? OR COALESCE(pd.surname,'') LIKE ? OR COALESCE(a.application_number,'') LIKE ?)";
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($filterFaculty)  { $where[] = 'pc.faculty = ?';     $params[] = $filterFaculty; }
    if ($filterDept)     { $where[] = 'pc.department = ?';  $params[] = $filterDept; }
    if ($filterCourse)   { $where[] = 'pc.course = ?';      $params[] = $filterCourse; }
    if ($filterStatus)   { $where[] = 'a.status = ?';       $params[] = $filterStatus; }

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
            a.application_id, a.application_number, a.status, a.submitted_at,
            u.email, u.full_name,
            pd.surname, pd.first_name, pd.phone,
            f.faculty_name, d.dept_name, dt.degree_name, c.course_title
        $joinSql
        GROUP BY a.application_id
        ORDER BY a.updated_at DESC, a.application_id DESC
        LIMIT {$limit} OFFSET {$offset}
    ");
    $selStmt->execute($params);
    $students = $selStmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once 'includes/dev_header.php';
require_once 'includes/sidebar.php';
require_once 'includes/dev_topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Applicant Directory</h1>
        <p class="panel-muted">Browse and filter all applicants by college, department, or programme.</p>
    </div>
    <div class="hero-actions">
        <a href="applicant-directory.php" class="btn btn-outline-secondary"><i class="fas fa-times me-1"></i>Clear Filters</a>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div><div class="stat-title">Total Applicants</div><div class="stat-value"><?php echo number_format((int)$stats['total']); ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="color:#f59e0b"><i class="fas fa-file-alt"></i></div>
        <div><div class="stat-title">Submitted</div><div class="stat-value"><?php echo number_format((int)$stats['submitted']); ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="color:#10b981"><i class="fas fa-user-check"></i></div>
        <div><div class="stat-title">Admitted</div><div class="stat-value"><?php echo number_format((int)$stats['admitted']); ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="color:#ef4444"><i class="fas fa-user-times"></i></div>
        <div><div class="stat-title">Rejected</div><div class="stat-value"><?php echo number_format((int)$stats['rejected']); ?></div></div>
    </div>
</section>

<section class="panel mb-3">
    <div class="panel-header">
        <h3 class="panel-title"><i class="fas fa-filter me-2"></i>Filter Applicants</h3>
    </div>
    <div class="panel-body">
        <form method="get" id="filterForm" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Search</label>
                <input type="text" name="q" class="form-control" placeholder="Name, email, app number..." value="<?php echo htmlspecialchars($q); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">College / Faculty</label>
                <select name="faculty" class="form-select" id="sel-faculty" onchange="cascadeFilter()">
                    <option value="">All Colleges</option>
                    <?php foreach ($faculties as $fac): ?>
                        <option value="<?php echo $fac['faculty_id']; ?>" <?php echo $filterFaculty == $fac['faculty_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($fac['faculty_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Department</label>
                <select name="department" class="form-select" id="sel-department" onchange="cascadeFilter()">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dep): ?>
                        <option value="<?php echo $dep['dept_id']; ?>"
                            data-faculty="<?php echo $dep['faculty_id']; ?>"
                            <?php echo $filterDept == $dep['dept_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dep['dept_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Programme</label>
                <select name="course" class="form-select" id="sel-course">
                    <option value="">All Programmes</option>
                    <?php foreach ($courses as $crs): ?>
                        <option value="<?php echo $crs['course_id']; ?>"
                            data-dept="<?php echo $crs['dept_id']; ?>"
                            <?php echo $filterCourse == $crs['course_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($crs['course_title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small fw-semibold text-muted">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($allowedStatus as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $filterStatus === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small fw-semibold text-muted">Per page</label>
                <select name="limit" class="form-select" onchange="this.form.submit()">
                    <?php foreach ([25, 50, 100, 200] as $l): ?>
                        <option value="<?php echo $l; ?>" <?php echo $limit === $l ? 'selected' : ''; ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-grid">
                <label class="form-label small fw-semibold text-muted">&nbsp;</label>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</section>

<!-- Bulk Action Bar (hidden until selection) -->
<div id="bulkBar" class="alert alert-info d-flex align-items-center gap-3 mb-3 py-2 px-3" style="display:none!important;border-radius:10px;">
    <i class="fas fa-check-square fa-lg text-primary"></i>
    <span class="fw-semibold"><span id="selCount">0</span> applicant(s) selected</span>
    <div class="ms-auto d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">
            <i class="fas fa-times me-1"></i>Deselect All
        </button>
        <button class="btn btn-sm btn-success" onclick="bulkDownload('zip')">
            <i class="fas fa-file-archive me-1"></i>Download ZIP
        </button>
        <button class="btn btn-sm btn-primary" onclick="bulkDownload('pdf')">
            <i class="fas fa-file-pdf me-1"></i>Download as PDF
        </button>
    </div>
</div>

<section class="panel">
    <div class="panel-header d-flex justify-content-between align-items-center">
        <div>
            <h3 class="panel-title">Results</h3>
            <div class="panel-muted">
                Showing <?php echo number_format($offset + 1); ?>–<?php echo number_format(min($offset + $limit, $totalRecords)); ?>
                of <?php echo number_format($totalRecords); ?> applicants
                <?php if ($filterFaculty || $filterDept || $filterCourse || $filterStatus || $q): ?>
                    &mdash; <span class="text-primary fw-semibold">Filters active</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <label class="form-check-label small text-muted me-1" for="selectAllChk">Select all on page</label>
            <input type="checkbox" id="selectAllChk" class="form-check-input" style="width:1.1rem;height:1.1rem" onchange="toggleSelectAll(this)">
        </div>
    </div>
    <div class="panel-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="applicantsTable">
                <thead>
                    <tr>
                        <th style="width:36px"></th>
                        <th>#</th>
                        <th>Applicant</th>
                        <th>App. Number</th>
                        <th>Faculty / College</th>
                        <th>Department</th>
                        <th>Programme</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($students)): ?>
                        <?php foreach ($students as $i => $app): ?>
                            <?php
                            $name = trim($app['full_name'] ?? '');
                            if (!$name) $name = trim(($app['first_name'] ?? '') . ' ' . ($app['surname'] ?? ''));
                            if (!$name) $name = $app['email'] ?? 'N/A';
                            $statusClass = match($app['status'] ?? '') {
                                'Admitted'  => 'status-success',
                                'Rejected'  => 'status-danger',
                                'Submitted' => 'status-warning',
                                default     => 'status-muted'
                            };
                            $appId = (int)$app['application_id'];
                            $appNo = htmlspecialchars($app['application_number'] ?? '');
                            ?>
                            <tr data-id="<?php echo $appId; ?>">
                                <td>
                                    <input type="checkbox" class="form-check-input row-check"
                                        style="width:1.1rem;height:1.1rem"
                                        value="<?php echo $appId; ?>"
                                        onchange="updateBulkBar()">
                                </td>
                                <td class="text-muted small"><?php echo $offset + $i + 1; ?></td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($name); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($app['email'] ?? ''); ?></div>
                                </td>
                                <td><code><?php echo $appNo; ?></code></td>
                                <td><?php echo htmlspecialchars($app['faculty_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($app['dept_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars(trim(($app['degree_name'] ?? '') . ' ' . ($app['course_title'] ?? '')) ?: '—'); ?></td>
                                <td><span class="status-chip <?php echo $statusClass; ?>"><?php echo htmlspecialchars($app['status'] ?? 'Draft'); ?></span></td>
                                <td class="small text-muted">
                                    <?php echo $app['submitted_at'] ? date('M d, Y', strtotime($app['submitted_at'])) : 'Not yet'; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="<?php echo app_url('ipessadmin/view.php?app_no=' . urlencode($app['application_number'] ?? '')); ?>"
                                           class="btn btn-light btn-sm" target="_blank" title="View Application">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="api/download-applicant.php?app_id=<?php echo $appId; ?>"
                                           class="btn btn-outline-primary btn-sm download-btn"
                                           title="Download Application Slip + Documents as PDF"
                                           data-id="<?php echo $appId; ?>"
                                           data-name="<?php echo htmlspecialchars($name); ?>">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="fas fa-search fa-2x mb-2 d-block opacity-25"></i>
                                No applicants found matching your filters.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center p-3 border-top">
            <div class="text-muted small">Page <?php echo $page; ?> of <?php echo $totalPages; ?></div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo $page == 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])); ?>">&#8249;</a>
                    </li>
                    <?php
                    $s = max(1, $page - 2);
                    $e = min($totalPages, $page + 2);
                    for ($pg = $s; $pg <= $e; $pg++): ?>
                        <li class="page-item <?php echo $pg == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pg])); ?>"><?php echo $pg; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page == $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => min($totalPages, $page + 1)])); ?>">&#8250;</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Download Progress Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:9999">
    <div id="dlToast" class="toast align-items-center text-bg-primary border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="dlToastMsg">
                <i class="fas fa-spinner fa-spin me-2"></i>Preparing download...
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<!-- Hidden bulk form -->
<form id="bulkForm" method="post" action="api/download-applicant.php" target="_blank" style="display:none">
    <input type="hidden" name="format" id="bulkFormat" value="zip">
    <div id="bulkIdsContainer"></div>
</form>

<?php require_once 'includes/footer.php'; ?>

<script>
// ─── Cascade filter ─────────────────────────────────────────────────────────
function cascadeFilter() {
    const facId = parseInt(document.getElementById('sel-faculty').value) || 0;
    document.querySelectorAll('#sel-department option[data-faculty]').forEach(opt => {
        opt.style.display = (!facId || parseInt(opt.dataset.faculty) === facId) ? '' : 'none';
    });
    const activeDept = parseInt(document.getElementById('sel-department').value) || 0;
    document.querySelectorAll('#sel-course option[data-dept]').forEach(opt => {
        opt.style.display = (!activeDept || parseInt(opt.dataset.dept) === activeDept) ? '' : 'none';
    });
}
document.addEventListener('DOMContentLoaded', cascadeFilter);

// ─── Selection helpers ───────────────────────────────────────────────────────
function getSelectedIds() {
    return [...document.querySelectorAll('.row-check:checked')].map(c => c.value);
}

function updateBulkBar() {
    const ids = getSelectedIds();
    const bar = document.getElementById('bulkBar');
    document.getElementById('selCount').textContent = ids.length;
    bar.style.display = ids.length > 0 ? 'flex' : 'none';
    // Sync "select all" checkbox state
    const allChecks = document.querySelectorAll('.row-check');
    document.getElementById('selectAllChk').indeterminate = ids.length > 0 && ids.length < allChecks.length;
    document.getElementById('selectAllChk').checked = ids.length === allChecks.length && allChecks.length > 0;
}

function toggleSelectAll(masterChk) {
    document.querySelectorAll('.row-check').forEach(c => { c.checked = masterChk.checked; });
    updateBulkBar();
}

function clearSelection() {
    document.querySelectorAll('.row-check').forEach(c => { c.checked = false; });
    document.getElementById('selectAllChk').checked = false;
    updateBulkBar();
}

// ─── Bulk download ───────────────────────────────────────────────────────────
function bulkDownload(format) {
    const ids = getSelectedIds();
    if (!ids.length) { alert('Please select at least one applicant.'); return; }

    const label = format === 'pdf'
        ? `Generating merged PDF for ${ids.length} applicant(s)...`
        : `Packing ${ids.length} applicant(s) into ZIP...`;
    showToast(label);

    document.getElementById('bulkFormat').value = format;
    const container = document.getElementById('bulkIdsContainer');
    container.innerHTML = '';
    ids.forEach(id => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'ids[]';
        inp.value = id;
        container.appendChild(inp);
    });
    document.getElementById('bulkForm').submit();
}

// ─── Single download feedback ────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.download-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const name = this.dataset.name || 'applicant';
            showToast(`Generating PDF for ${name}…`);
        });
    });
});

// ─── Toast helper ────────────────────────────────────────────────────────────
function showToast(msg) {
    document.getElementById('dlToastMsg').innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>' + msg;
    const toastEl = document.getElementById('dlToast');
    const toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 6000 });
    toast.show();
}
</script>