<?php
session_start();
require_once __DIR__ . '/../app/helpers/auth.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized access.');
}

require_once 'db.php';
if (!$pdo) {
    http_response_code(500);
    die('Database unavailable.');
}

$role = $_SESSION['role'] ?? $_SESSION['roleid'] ?? '';
$userId = $_SESSION['user_id'];

// Resolve normalized role
$normRole = function_exists('normalize_role') ? normalize_role($role) : strtoupper(trim($role));
$isHod = in_array($normRole, ['HOD', 'DEPARTMENT_ADMIN'], true) || stripos($normRole, 'department') !== false;

// Roles that are always allowed to export
$exportAllowedRoles = [
    'SUPER_ADMIN', 'DEVELOPER', 'ICT_ADMIN', 'ADMISSIONS_OFFICER',
    'PG_SCHOOL_OFFICER', 'PORTAL_ADMIN', 'STUDENT_MANAGER', 'REGISTRY',
    'ICT_SUPPORT', 'FACULTY_OFFICER', 'IPESS_ICT_ADMIN'
];
$hasExportRole = in_array($normRole, $exportAllowedRoles, true);

// Also check if the page is dynamically assigned to this user's role/sidebar
$hasPageAssigned = false;
try {
    $stmtPageCheck = $pdo->prepare("
        SELECT COUNT(*) FROM right_page_main_menus
        WHERE page_url LIKE '%export-students.php'
          AND roleID IN (SELECT role_id FROM roles WHERE role_key = ? OR role_name = ? LIMIT 1)
          AND page_status = '1'
    ");
    $stmtPageCheck->execute([$normRole, $normRole]);
    if ((int)$stmtPageCheck->fetchColumn() > 0) $hasPageAssigned = true;

    if (!$hasPageAssigned) {
        $stmtPersonal = $pdo->prepare("
            SELECT COUNT(*) FROM pesonal_right_page_main_menus
            WHERE page_url LIKE '%export-students.php' AND userID = ? AND page_status = '1'
        ");
        $stmtPersonal->execute([$userId]);
        if ((int)$stmtPersonal->fetchColumn() > 0) $hasPageAssigned = true;
    }
} catch (Throwable $e) {}

if (!$isHod && !$hasExportRole && !$hasPageAssigned
    && !has_permission('export_csv', $role, $userId)
    && !has_permission('export_excel', $role, $userId)) {
    http_response_code(403);
    die('Forbidden. Insufficient permissions to export CSV/Excel.');
}

// db.php already loaded above

// Resolve HOD department mapping
$loggedInUserAccessName = $_SESSION['userid'] ?? '';
$loggedInDepartmentId = null;

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

$allowedStatuses = ['Admitted', 'Rejected', 'Submitted'];
$statusParam   = $_GET['status'] ?? 'all';
$q             = trim((string)($_GET['q'] ?? ''));
$filterYear    = (int)($_GET['year'] ?? 0);
$filterProgram = (int)($_GET['program'] ?? 0);
$format        = strtolower(trim($_GET['format'] ?? ''));

$availableCourses = [];
$availableYears = [];

if ($pdo) {
    try {
        if ($loggedInDepartmentId) {
            $courseSql = "SELECT course_id, course_title FROM courses WHERE dept_id = ? ORDER BY course_title ASC";
            $stmtC = $pdo->prepare($courseSql);
            $stmtC->execute([$loggedInDepartmentId]);
            $availableCourses = $stmtC->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $courseSql = "SELECT course_id, course_title FROM courses ORDER BY course_title ASC";
            $availableCourses = $pdo->query($courseSql)->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {}

    try {
        $availableYears = $pdo->query("SELECT DISTINCT YEAR(submitted_at) AS yr FROM applications WHERE submitted_at IS NOT NULL ORDER BY yr DESC")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {}
}

// Build where clause
$deptFilterSql = "";
if ($isHod && $loggedInDepartmentId) {
    $deptFilterSql = " AND (pc.department = " . (int)$loggedInDepartmentId . " OR a.department_id = " . (int)$loggedInDepartmentId . ")";
}

$where = $deptFilterSql;
$label = 'all';

if ($statusParam === 'Submitted') {
    $where .= " AND a.status = 'Submitted'";
    $label = 'pending';
} elseif (in_array($statusParam, $allowedStatuses, true)) {
    $where .= " AND a.status = " . $pdo->quote($statusParam);
    $label = strtolower($statusParam);
}

if ($q !== '') {
    $like = '%' . $q . '%';
    $where .= " AND (u.email LIKE " . $pdo->quote($like) . " OR COALESCE(p.first_name,'') LIKE " . $pdo->quote($like) . " OR COALESCE(p.surname,'') LIKE " . $pdo->quote($like) . " OR COALESCE(a.application_number,'') LIKE " . $pdo->quote($like) . " OR COALESCE(p.phone,'') LIKE " . $pdo->quote($like) . ")";
}

if ($filterProgram) {
    $where .= " AND pc.course = " . (int)$filterProgram;
}

if ($filterYear) {
    $where .= " AND YEAR(a.submitted_at) = " . (int)$filterYear;
}

// Main query
$sql = "
    SELECT
        a.application_number                    AS Application_Number,
        p.surname                               AS Surname,
        p.first_name                            AS First_Name,
        p.other_name                            AS Other_Names,
        p.sex                                   AS Gender,
        p.dob                                   AS Date_of_Birth,
        p.phone                                 AS Phone,
        u.email                                 AS Email,
        c.course_title                          AS Programme,
        d.dept_name                             AS Department,
        f.faculty_name                          AS Faculty,
        dt.degree_name                          AS Degree_Type,
        a.status                                AS Status,
        a.submitted_at                          AS Submitted_At
    FROM applications a
    LEFT JOIN users            u  ON a.user_id        = u.user_id
    LEFT JOIN personal_details p  ON a.application_id = p.application_id
    LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
    LEFT JOIN faculties        f  ON f.faculty_id     = pc.faculty
    LEFT JOIN departments      d  ON d.dept_id        = COALESCE(pc.department, a.department_id)
    LEFT JOIN courses          c  ON c.course_id      = pc.course
    LEFT JOIN degree_types     dt ON dt.degree_id     = pc.degree_type
    WHERE a.submitted_at IS NOT NULL
    $where
    ORDER BY a.submitted_at DESC
";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

/* ────────────────────────────────────────────────────────────
   MODE 1: STREAM CSV / EXCEL
   ──────────────────────────────────────────────────────────── */
if ($format === 'csv') {
    $filename = 'students_export_' . $label . '_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM so Excel opens it correctly
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    $headers = [
        'Application Number', 'Surname', 'First Name', 'Other Names',
        'Gender', 'Date of Birth', 'Phone', 'Email',
        'Programme', 'Department', 'Faculty', 'Degree Type',
        'Status', 'Submitted At'
    ];
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, array_values($row));
    }
    fclose($out);
    exit();
}

/* ────────────────────────────────────────────────────────────
   MODE 2: STANDALONE PDF PRINT LAYOUT
   ──────────────────────────────────────────────────────────── */
if ($format === 'pdf') {
    $documentTitle = 'JOSTUM PG School - Student Export (' . $deptName . ')';
    $generatedAt = date('M d, Y H:i');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="icon" type="image/jpeg" href="images/logo.jpeg">
        <title><?php echo htmlspecialchars($documentTitle); ?></title>
        <style>
            body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; background: #f5f7fb; margin: 0; padding: 24px; color: #1e293b; }
            .sheet { background: #ffffff; border-radius: 12px; padding: 24px; box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08); }
            .header { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 16px; }
            .logo-title-group { display: flex; align-items: center; gap: 12px; }
            .logo-img { height: 50px; width: 50px; border-radius: 50%; object-fit: cover; }
            .title { font-size: 18px; font-weight: 700; margin: 0; color: #0f172a; }
            .meta { color: #64748b; font-size: 12px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 11px; }
            th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
            th { background: #f8fafc; text-transform: uppercase; font-size: 10px; letter-spacing: 0.05em; color: #475569; }
            .status-badge { font-weight: 600; font-size: 10px; padding: 2px 6px; border-radius: 4px; display: inline-block; }
            .status-Admitted { background: #d1fae5; color: #065f46; }
            .status-Rejected { background: #fee2e2; color: #991b1b; }
            .status-Submitted { background: #fef3c7; color: #92400e; }
            .status-Draft { background: #f1f5f9; color: #475569; }
            .toolbar { margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
            .action-btn { background: #10b981; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-size: 12px; text-decoration: none; }
            .action-btn.back-btn { background: #64748b; }
            @media print { body { background: #fff; padding: 0; } .toolbar { display: none; } .sheet { box-shadow: none; border-radius: 0; padding: 0; } }
        </style>
    </head>
    <body>
        <div class="toolbar">
            <a href="export-students.php?<?php echo http_build_query($_GET); ?>" class="action-btn back-btn">
                ← Back to View
            </a>
            <button onclick="window.print()" class="action-btn">
                Print / Save as PDF
            </button>
        </div>
        <div class="sheet">
            <div class="header">
                <div class="logo-title-group">
                    <img src="images/logo.jpeg" class="logo-img" alt="Logo">
                    <div>
                        <h1 class="title">JOSTUM PG School - Applicants Record Sheet</h1>
                        <div class="meta">Department: <strong><?php echo htmlspecialchars($deptName); ?></strong></div>
                    </div>
                </div>
                <div class="meta" style="text-align: right;">
                    <div>Generated: <?php echo $generatedAt; ?></div>
                    <div>Total Applicants: <strong><?php echo count($rows); ?></strong></div>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>App No</th>
                        <th>Candidate Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Programme</th>
                        <th>Degree</th>
                        <th>Status</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $i => $row): ?>
                            <?php
                            $candidateName = trim(($row['Surname'] ?? '') . ' ' . ($row['First_Name'] ?? '') . ' ' . ($row['Other_Names'] ?? ''));
                            if ($candidateName === '') $candidateName = 'N/A';
                            ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><code><?php echo htmlspecialchars((string)($row['Application_Number'] ?: 'N/A')); ?></code></td>
                                <td><strong><?php echo htmlspecialchars($candidateName); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['Email'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['Phone'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['Programme'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['Degree_Type'] ?? ''); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars($row['Status']); ?>">
                                        <?php echo htmlspecialchars($row['Status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $row['Submitted_At'] ? date('M d, Y', strtotime($row['Submitted_At'])) : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: #64748b;">No records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </body>
    </html>
    <?php
    exit();
}

/* ────────────────────────────────────────────────────────────
   MODE 3: DASHBOARD INTERACTIVE PREVIEW PAGE (DEFAULT)
   ──────────────────────────────────────────────────────────── */
$pageTitle = 'Export Students';
$pageSubtitle = 'Review applicant details and select format to download.';

require_once 'includes/dev_header.php';
require_once 'includes/sidebar.php';
require_once 'includes/dev_topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Export Records Sheet</h1>
        <p class="panel-muted">Previewing applications under department: <strong><?php echo htmlspecialchars($deptName); ?></strong></p>
    </div>
    <div class="hero-actions">
        <!-- Re-use existing query parameters for downloads -->
        <a href="export-students.php?<?php echo http_build_query(array_merge($_GET, ['format' => 'csv'])); ?>" class="btn btn-success">
            <i class="fas fa-file-excel me-1"></i>Download Excel / CSV
        </a>
        <a href="export-students.php?<?php echo http_build_query(array_merge($_GET, ['format' => 'pdf'])); ?>" class="btn btn-danger">
            <i class="fas fa-file-pdf me-1"></i>Download / Print PDF
        </a>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Records Preview (Total: <?php echo count($rows); ?>)</h3>
            <div class="panel-muted">Review the data columns before executing the report download.</div>
        </div>
    </div>
    <div class="panel-body">
        <form method="get" class="row g-2 mb-3 align-items-end">
            <!-- Retain current format if any -->
            <input type="hidden" name="format" value="<?php echo htmlspecialchars($format); ?>">
            
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Search</label>
                <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="Search candidate...">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Program</label>
                <select class="form-select" name="program">
                    <option value="">All Programs</option>
                    <?php foreach ($availableCourses as $course): ?>
                        <option value="<?php echo $course['course_id']; ?>" <?php echo $filterProgram == $course['course_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($course['course_title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Status</label>
                <select class="form-select" name="status">
                    <option value="all">All Statuses</option>
                    <?php foreach ($allowedStatuses as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $statusParam === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
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
                <button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i>Filter</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Name</th>
                        <th>App No</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Gender</th>
                        <th>DOB</th>
                        <th>Programme</th>
                        <th>Degree</th>
                        <th>Status</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $i => $row): ?>
                            <?php
                            $candidateName = trim(($row['Surname'] ?? '') . ' ' . ($row['First_Name'] ?? '') . ' ' . ($row['Other_Names'] ?? ''));
                            if ($candidateName === '') $candidateName = 'N/A';

                            $statusClass = 'status-muted';
                            if (($row['Status'] ?? '') === 'Admitted')  $statusClass = 'status-success';
                            elseif (($row['Status'] ?? '') === 'Rejected') $statusClass = 'status-danger';
                            elseif (($row['Status'] ?? '') === 'Submitted') $statusClass = 'status-warning';
                            ?>
                            <tr>
                                <td class="text-muted small"><?php echo $i + 1; ?></td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($candidateName); ?></div>
                                </td>
                                <td><code><?php echo htmlspecialchars((string)($row['Application_Number'] ?: 'N/A')); ?></code></td>
                                <td><?php echo htmlspecialchars($row['Email'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['Phone'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['Gender'] ?? ''); ?></td>
                                <td><?php echo $row['Date_of_Birth'] ? date('M d, Y', strtotime($row['Date_of_Birth'])) : '—'; ?></td>
                                <td><?php echo htmlspecialchars($row['Programme'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['Degree_Type'] ?? ''); ?></td>
                                <td><span class="status-chip <?php echo $statusClass; ?>"><?php echo htmlspecialchars((string)($row['Status'] ?: 'Unknown')); ?></span></td>
                                <td class="small text-muted">
                                    <?php echo $row['Submitted_At'] ? date('M d, Y', strtotime($row['Submitted_At'])) : '—'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="text-center text-muted py-5">
                                <i class="fas fa-folder-open fa-2x mb-2 d-block opacity-25"></i>
                                No records found matching the active filters.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
