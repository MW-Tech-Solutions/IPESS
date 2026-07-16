<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once 'includes/db.php';

if (!has_permission('view_applications')) {
    http_response_code(403);
    exit('403 Forbidden: View Records duty not assigned.');
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userDeptId = null;
$userDeptName = '';
if ($userId > 0 && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.department_id, d.dept_name 
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.dept_id
            WHERE u.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $userDeptId = $row['department_id'] ? (int) $row['department_id'] : null;
            $userDeptName = $row['dept_name'] ?? '';
        }
    } catch (PDOException $e) {
    }
}

$faculties = [];
$departments = [];
try {
    if ($userDeptId === null) {
        $facStmt = $pdo->query("SELECT faculty_id, faculty_name FROM faculties ORDER BY faculty_name ASC");
        $faculties = $facStmt->fetchAll(PDO::FETCH_ASSOC);

        $deptSql = "SELECT dept_id, dept_name, faculty_id FROM departments ORDER BY dept_name ASC";
        $deptStmt = $pdo->query($deptSql);
        $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
}

$stats = ['total' => 0, 'pending' => 0, 'admitted' => 0, 'rejected' => 0]; 

if (isset($pdo)) {
    try {
        $statsSql = "
            SELECT 
                COUNT(DISTINCT a.application_id) as total,
                SUM(CASE WHEN a.status = 'Submitted' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN a.status = 'Admitted' THEN 1 ELSE 0 END) as admitted,
                SUM(CASE WHEN a.status = 'Rejected' THEN 1 ELSE 0 END) as rejected
            FROM applications a
            LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
        ";
        if ($userDeptId !== null) {
            $statsSql .= " WHERE (pc.department = ? OR a.department_id = ?)";
            $statsStmt = $pdo->prepare($statsSql);
            $statsStmt->execute([$userDeptId, $userDeptId]);
        } else {
            $statsStmt = $pdo->query($statsSql);
        }
        $result = $statsStmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $stats = $result;
        }
    } catch (PDOException $e) {
    }
}

$whereClauses = [];
$params = [];
$applications = [];
$totalRows = 0;
$totalPages = 1;

$joinSql = " FROM applications a 
             LEFT JOIN personal_details p ON a.application_id = p.application_id 
             LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
             LEFT JOIN faculties f ON pc.faculty = f.faculty_id
             LEFT JOIN departments d ON pc.department = d.dept_id
             LEFT JOIN degree_types dt ON pc.degree_type = dt.degree_id
             LEFT JOIN courses c ON pc.course = c.course_id
             LEFT JOIN study_modes sm ON pc.mode_of_study = sm.mode_id"; 

$countSql = "SELECT COUNT(DISTINCT a.application_id) " . $joinSql;

$sql = "SELECT 
            a.application_id, 
            a.application_number, 
            a.status, 
            a.submitted_at, 
            p.surname, 
            p.first_name, 
            f.faculty_name, 
            d.dept_name, 
            dt.degree_name, 
            sm.mode_name, 
            c.course_title 
        " . $joinSql;

$filters = [
    'status' => 'a.status',
    'faculty' => 'pc.faculty',
    'department' => 'pc.department',
    'degree_type' => 'pc.degree_type',
    'mode_of_study' => 'pc.mode_of_study'
];

foreach ($filters as $getVar => $column) {
    if (!empty($_GET[$getVar])) {
        $whereClauses[] = "$column = ?";
        $params[] = $_GET[$getVar];
    }
}

if (!empty($_GET['search'])) {
    $searchTerm = "%" . $_GET['search'] . "%";
    $whereClauses[] = "(p.first_name LIKE ? OR p.surname LIKE ? OR a.application_number LIKE ?)";
    array_push($params, $searchTerm, $searchTerm, $searchTerm);
}

if ($userDeptId !== null) {
    $whereClauses[] = "(pc.department = ? OR a.department_id = ?)";
    $params[] = $userDeptId;
    $params[] = $userDeptId;
}

if (count($whereClauses) > 0) {
    $whereStr = " WHERE " . implode(" AND ", $whereClauses);
    $countSql .= $whereStr;
    $sql .= $whereStr;
}

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

if (isset($pdo)) {
    try {
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRows = $countStmt->fetchColumn();
        $totalPages = ceil($totalRows / $limit);

        $sql .= " GROUP BY a.application_id ORDER BY a.submitted_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

function buildUrl($newPage) {
    $params = $_GET;
    $params['page'] = $newPage;
    return '?' . http_build_query($params);
}
?>

<?php
$pageTitle = 'Application Management';
$pageSubtitle = 'View and track incoming applicant records for ' . ($userDeptName ?: 'all departments') . '.';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<div class="content-container">
            
<div class="kpi-cards">
    <div class="kpi-card">
        <div class="kpi-icon primary">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="kpi-content">
            <h3><?= number_format($stats['total']) ?></h3>
            <p>Total Applications</p>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon success">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="kpi-content">
            <h3><?= number_format($stats['admitted']) ?></h3>
            <p>Approved Applications</p>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon warning">
            <i class="fas fa-clock"></i>
        </div>
        <div class="kpi-content">
            <h3><?= number_format($stats['pending']) ?></h3>
            <p>Under Review</p>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon danger">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="kpi-content">
            <h3><?= number_format($stats['rejected']) ?></h3>
            <p>Rejected</p>
        </div>
    </div>
</div>

<div class="card p-3 mb-4 border-0 shadow-sm rounded-3">
    <form class="row g-2" method="GET" id="filterForm">
        <input type="hidden" name="page" value="1"> 
        
        <div class="col-md-3">
            <label class="small fw-bold text-muted mb-1">Search</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Name or App ID..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </div>
        </div>

        <div class="col-md-2">
            <label class="small fw-bold text-muted mb-1">Status</label>
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Status</option>
                <?php foreach(['Submitted', 'Admitted', 'Rejected'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($_GET['status'] ?? '') == $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($userDeptId === null): ?>
            <div class="col-md-2">
                <label class="small fw-bold text-muted mb-1">Faculty</label>
                <select name="faculty" id="facultySelect" class="form-select form-select-sm">
                    <option value="">All Faculties</option>
                    <?php foreach ($faculties as $faculty): ?>
                        <option value="<?= $faculty['faculty_id'] ?>" 
                            <?= (isset($_GET['faculty']) && $_GET['faculty'] == $faculty['faculty_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($faculty['faculty_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="small fw-bold text-muted mb-1">Department</label>
                <select name="department" id="deptSelect" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['dept_id'] ?>" 
                            data-faculty="<?= $dept['faculty_id'] ?>"
                            <?= (isset($_GET['department']) && $_GET['department'] == $dept['dept_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['dept_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <div class="col-md-4">
                <label class="small fw-bold text-muted mb-1">Assigned Department</label>
                <input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($userDeptName); ?>" readonly disabled>
                <input type="hidden" name="department" value="<?php echo $userDeptId; ?>">
            </div>
        <?php endif; ?>
        
        <div class="col-md-2">
            <label class="small fw-bold text-muted mb-1">Mode</label>
            <select name="mode_of_study" class="form-select form-select-sm">
                <option value="">All Modes</option>
                <option value="Full Time" <?= ($_GET['mode_of_study'] ?? '') == 'Full Time' ? 'selected' : '' ?>>Full-Time</option>
                <option value="Part Time" <?= ($_GET['mode_of_study'] ?? '') == 'Part Time' ? 'selected' : '' ?>>Part-Time</option>
            </select>
        </div>

        <div class="col-md-1 d-flex align-items-end">
            <button type="submit" class="btn btn-sm btn-primary w-90 fw-medium">Apply</button>
        </div>
    </form>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="bg-light">
                <tr>
                    <th style="width: 40px; padding-left: 1.5rem;">
                        <input class="form-check-input" type="checkbox" id="selectAll">
                    </th>
                    <th>S/N</th>
                    <th>Full Name</th>
                    <th>App ID</th>
                    <th>Faculty / Dept</th>
                    <th>Degree</th>
                    <th>Status</th>
                    <th class="text-end" style="padding-right: 1.5rem;">Action</th>
                </tr>
            </thead>
            
            <tbody>
                <?php if (count($applications) > 0): ?>
                    <?php $sn = $offset + 1; foreach ($applications as $row): ?>
                    <tr>
                        <td style="padding-left: 1.5rem;">
                            <input class="form-check-input row-checkbox" type="checkbox" value="<?= $row['application_id'] ?>">
                        </td>
                        <td class="text-muted"><?php echo $sn++; ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['surname'] . ' ' . $row['first_name']); ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars($row['course_title'] ?? 'N/A'); ?></div>
                        </td>
                        <td><span class="font-monospace small text-primary bg-light px-2 py-1 rounded border"><?php echo htmlspecialchars($row['application_number']); ?></span></td>
                        <td>
                            <div class="small fw-semibold"><?php echo htmlspecialchars($row['faculty_name'] ?? 'N/A'); ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars($row['dept_name'] ?? 'N/A'); ?></div>
                        </td>
                        <td><span class="badge bg-light text-secondary border fw-normal"><?php echo htmlspecialchars($row['degree_name'] ?? 'N/A'); ?></span></td>
                        <td>
                            <?php 
                                $statusClass = match(strtolower($row['status'])) {
                                    'admitted' => 'badge-admitted badge-success text-success bg-opacity-10',
                                    'rejected' => 'badge-rejected badge-danger text-danger bg-opacity-10',
                                    default => 'badge-submitted badge-info text-info bg-opacity-10'
                                };
                            ?>
                            <span class="badge rounded-pill <?php echo $statusClass; ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td class="text-end" style="padding-right: 1.5rem;">
                            <a href="view.php?app_no=<?php echo urlencode($row['application_number']); ?>" class="btn btn-sm btn-outline-primary border-0">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No applications found matching your criteria.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between align-items-center p-3 border-top">
        <div class="text-muted small">
            Showing <strong><?= $totalRows > 0 ? $offset + 1 : 0 ?></strong> to 
            <strong><?= min($offset + $limit, $totalRows) ?></strong> of 
            <strong><?= number_format($totalRows) ?></strong> entries
        </div>
        
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= buildUrl($page - 1) ?>">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                        <a class="page-link" href="<?= buildUrl($i) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= buildUrl($page + 1) ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

</div> 

<?php if ($userDeptId === null): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const facultySelect = document.getElementById('facultySelect');
    const deptSelect = document.getElementById('deptSelect');
    if (facultySelect && deptSelect) {
        const allDeptOptions = Array.from(deptSelect.options);
        facultySelect.addEventListener('change', function () {
            const selectedFacultyId = this.value;
            deptSelect.innerHTML = '<option value="">All Departments</option>';
            allDeptOptions.forEach(option => {
                if (option.value === "" || option.getAttribute('data-faculty') === selectedFacultyId) {
                    deptSelect.appendChild(option);
                }
            });
        });
    }
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
