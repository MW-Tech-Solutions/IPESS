<?php
$pageTitle = 'Departmental Records';
$pageSubtitle = 'View and export student application records for your department.';

require_once 'db.php';
session_start();

$currentRole = function_exists('normalize_role') ? normalize_role($_SESSION['roleid'] ?? '') : strtoupper(trim($_SESSION['roleid'] ?? ''));
if (!in_array($currentRole, ['HOD', 'DEPARTMENT_ADMIN', 'SUPER_ADMIN', 'DEVELOPER'], true)) {
    header("Location: index.php");
    exit;
}

$loggedInUserAccessName = $_SESSION['userid'] ?? '';
$loggedInDepartmentId = null;

if (!function_exists('table_exists')) {
    function table_exists(PDO $pdo, string $table): bool {
        try {
            $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $pdo->query("SELECT 1 FROM `{$sanitized}` LIMIT 0");
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

// Resolve HOD department mapping
try {
    $userIdVal = $_SESSION['user_id'] ?? null;
    
    // 1. Try querying users table by user_id
    if ($userIdVal) {
        $stmtDept = $pdo->prepare("SELECT department_id FROM users WHERE user_id = ? LIMIT 1");
        $stmtDept->execute([(int)$userIdVal]);
        $loggedInDepartmentId = $stmtDept->fetchColumn();
    }

    // 2. Try querying users table by userSessionName if it looks like an email or numeric ID
    if (!$loggedInDepartmentId && $loggedInUserAccessName) {
        $stmtDept = $pdo->prepare("SELECT department_id FROM users WHERE user_id = ? OR email = ? LIMIT 1");
        $stmtDept->execute([$loggedInUserAccessName, $loggedInUserAccessName]);
        $loggedInDepartmentId = $stmtDept->fetchColumn();
    }

    // 3. Try legacy mapping table sch_departmental_officer by username (loggedInUserAccessName)
    if (!$loggedInDepartmentId && $loggedInUserAccessName) {
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', 'sch_departmental_officer');
        $tableExists = false;
        try {
            $pdo->query("SELECT 1 FROM `{$sanitized}` LIMIT 0");
            $tableExists = true;
        } catch (Throwable $e) {}

        if ($tableExists) {
            $stmtDept2 = $pdo->prepare("SELECT departmentID FROM sch_departmental_officer WHERE userID = ? LIMIT 1");
            $stmtDept2->execute([$loggedInUserAccessName]);
            $loggedInDepartmentId = $stmtDept2->fetchColumn();
        }
    }

    // 4. Try legacy mapping table sch_departmental_officer by username fetched from user_access
    if (!$loggedInDepartmentId && ($userIdVal || $loggedInUserAccessName)) {
        $stmtAcc = $pdo->prepare("SELECT userName, EmailAddress FROM user_access WHERE staffIDs = ? OR userName = ? OR EmailAddress = ? LIMIT 1");
        $stmtAcc->execute([$userIdVal, $loggedInUserAccessName, $loggedInUserAccessName]);
        $accRow = $stmtAcc->fetch(PDO::FETCH_ASSOC);
        if ($accRow) {
            $uname = $accRow['userName'];
            $uemail = $accRow['EmailAddress'];

            // Try users table with email
            $stmtDept = $pdo->prepare("SELECT department_id FROM users WHERE email = ? LIMIT 1");
            $stmtDept->execute([$uemail]);
            $loggedInDepartmentId = $stmtDept->fetchColumn();

            // Try sch_departmental_officer with username
            if (!$loggedInDepartmentId) {
                $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', 'sch_departmental_officer');
                $tableExists = false;
                try {
                    $pdo->query("SELECT 1 FROM `{$sanitized}` LIMIT 0");
                    $tableExists = true;
                } catch (Throwable $e) {}

                if ($tableExists) {
                    $stmtDept2 = $pdo->prepare("SELECT departmentID FROM sch_departmental_officer WHERE userID = ? LIMIT 1");
                    $stmtDept2->execute([$uname]);
                    $loggedInDepartmentId = $stmtDept2->fetchColumn();
                }
            }
        }
    }
} catch (Throwable $e) {}

// If HOD or Department Admin but has no assigned department, error out
if (($currentRole === 'HOD' || $currentRole === 'DEPARTMENT_ADMIN' || stripos($currentRole, 'department') !== false) && !$loggedInDepartmentId) {
    die("Error: No department has been assigned to your account. Please contact the administrator.");
}

// Self-register in page_main_menus if not already there, so it is assignable
try {
    $url = 'ipessadmin/departmental-records.php';
    $checkMenuExists = $pdo->prepare("SELECT COUNT(*) FROM page_main_menus WHERE page_url = ?");
    $checkMenuExists->execute([$url]);
    if ((int)$checkMenuExists->fetchColumn() === 0) {
        $folderId = $pdo->query("SELECT folderid FROM setup_folder WHERE LOWER(fname) LIKE '%ipessadmin%' LIMIT 1")->fetchColumn() ?: 1;
        $stmtIns = $pdo->prepare("INSERT INTO page_main_menus (menu_name, page_status, pageType, tabID, page_url, folder) VALUES ('Departmental Records', '1', 'Sub', '1', ?, ?)");
        $stmtIns->execute([$url, $folderId]);
    }
} catch (Throwable $e) {}

// Fetch department name
$deptName = 'All Departments';
if ($loggedInDepartmentId) {
    try {
        $stmtDName = $pdo->prepare("SELECT dept_name FROM departments WHERE dept_id = ? LIMIT 1");
        $stmtDName->execute([$loggedInDepartmentId]);
        $dName = $stmtDName->fetchColumn();
        if ($dName) {
            $deptName = $dName;
        }
    } catch (Throwable $e) {}
}

$q            = trim((string)($_GET['q'] ?? ''));
$filterStatus = trim($_GET['status'] ?? '');
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
$availableYears = [];

if ($pdo) {
    try {
        // Load available years
        $availableYears = $pdo->query("SELECT DISTINCT YEAR(submitted_at) AS yr FROM applications WHERE submitted_at IS NOT NULL ORDER BY yr DESC")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {}

    // Stats query with department filter if restricted
    $statsWhere = "NOT EXISTS (SELECT 1 FROM applications nx WHERE nx.user_id = applications.user_id AND nx.application_id > applications.application_id)";
    $statsParams = [];
    if ($loggedInDepartmentId) {
        $statsWhere .= " AND (department_id = ? OR EXISTS (
            SELECT 1 FROM programme_choices pc WHERE pc.application_id = applications.application_id AND pc.department = ?
        ))";
        $statsParams = [$loggedInDepartmentId, $loggedInDepartmentId];
    }

    $statsStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status='Admitted'  THEN 1 ELSE 0 END) AS admitted,
            SUM(CASE WHEN status='Submitted' THEN 1 ELSE 0 END) AS submitted,
            SUM(CASE WHEN status='Rejected'  THEN 1 ELSE 0 END) AS rejected
        FROM applications
        WHERE $statsWhere
    ");
    $statsStmt->execute($statsParams);
    $statsRow = $statsStmt->fetch(PDO::FETCH_ASSOC);
    if ($statsRow) $stats = $statsRow;

    // Filters for main query
    $where  = ["NOT EXISTS (SELECT 1 FROM applications nx WHERE nx.user_id = a.user_id AND nx.application_id > a.application_id)"];
    $params = [];

    if ($loggedInDepartmentId) {
        $where[] = '(pc.department = ? OR a.department_id = ?)';
        $params[] = $loggedInDepartmentId;
        $params[] = $loggedInDepartmentId;
    }

    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[]  = "(u.email LIKE ? OR COALESCE(pd.first_name,'') LIKE ? OR COALESCE(pd.surname,'') LIKE ? OR COALESCE(a.application_number,'') LIKE ? OR COALESCE(pd.phone,'') LIKE ?)";
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($filterStatus)  { $where[] = 'a.status = ?';          $params[] = $filterStatus; }
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
        <h1>Departmental Records</h1>
        <p class="panel-muted">Student applications history desk for <strong><?php echo htmlspecialchars($deptName); ?></strong>.</p>
    </div>
    <div class="hero-actions">
        <a href="departmental-records.php" class="btn btn-outline-secondary"><i class="fas fa-times me-1"></i>Clear Filters</a>
        <a href="export-students.php<?php echo $_SERVER['QUERY_STRING'] ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : ''; ?>" class="btn btn-success">
            <i class="fas fa-file-excel me-1"></i>Export CSV
        </a>
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
            <h3 class="panel-title">Department Applicant List</h3>
            <div class="panel-muted">Find candidates under <?php echo htmlspecialchars($deptName); ?>.</div>
        </div>
    </div>
    <div class="panel-body">
        <form method="get" class="row g-2 mb-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold text-muted">Search</label>
                <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="Student name, email, app number, phone...">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <?php foreach ($allowedStatus as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $filterStatus === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Year</label>
                <select class="form-select" name="year">
                    <option value="">All Years</option>
                    <?php foreach ($availableYears as $yr): ?>
                        <option value="<?php echo $yr; ?>" <?php echo $filterYear == $yr ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <label class="form-label small fw-semibold text-muted">&nbsp;</label>
                <button class="btn btn-primary" type="submit"><i class="fas fa-search me-1"></i>Search</button>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="text-muted small">
                Showing <?php echo number_format(min($totalRecords, $offset + 1)); ?>
                to <?php echo number_format(min($totalRecords, $offset + $limit)); ?>
                of <?php echo number_format($totalRecords); ?> records
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Application No</th>
                        <th>Phone</th>
                        <th>Programme</th>
                        <th>Status</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($records)): ?>
                        <?php foreach ($records as $i => $student): ?>
                            <?php
                            $studentName = trim((string)($student['full_name'] ?? ''));
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
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fas fa-folder-open fa-2x mb-2 d-block opacity-25"></i>
                                No records found for this department.
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

<?php require_once 'includes/footer.php'; ?>
