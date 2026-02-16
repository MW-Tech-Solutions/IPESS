<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SUPER_ADMIN') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'Applications';
$pageSubtitle = 'Control admissions volume, filter by faculty, and resolve pending reviews.';

require_once 'includes/db.php';

$stats = ['total' => 0, 'submitted' => 0, 'admitted' => 0, 'rejected' => 0];
$applications = [];
$faculties = [];
$departments = [];
$degrees = [];
$modes = [];
$totalRows = 0;
$totalPages = 1;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

if ($pdo) {
    $statsSql = "
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) AS submitted,
            SUM(CASE WHEN status = 'Admitted' THEN 1 ELSE 0 END) AS admitted,
            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) AS rejected
        FROM applications
    ";
    $stats = $pdo->query($statsSql)->fetch(PDO::FETCH_ASSOC) ?: $stats;

    $faculties = $pdo->query("SELECT faculty_id, faculty_name FROM faculties ORDER BY faculty_name")->fetchAll(PDO::FETCH_ASSOC);
    $departments = $pdo->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name")->fetchAll(PDO::FETCH_ASSOC);
    $degrees = $pdo->query("SELECT degree_id, degree_name FROM degree_types ORDER BY degree_name")->fetchAll(PDO::FETCH_ASSOC);
    $modes = $pdo->query("SELECT mode_id, mode_name FROM study_modes ORDER BY mode_name")->fetchAll(PDO::FETCH_ASSOC);

    $where = [];
    $params = [];

    if (!empty($_GET['status'])) {
        $where[] = 'a.status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['faculty'])) {
        $where[] = 'f.faculty_id = ?';
        $params[] = $_GET['faculty'];
    }
    if (!empty($_GET['department'])) {
        $where[] = 'd.dept_id = ?';
        $params[] = $_GET['department'];
    }
    if (!empty($_GET['degree_type'])) {
        $where[] = 'dt.degree_id = ?';
        $params[] = $_GET['degree_type'];
    }
    if (!empty($_GET['mode'])) {
        $where[] = 'sm.mode_id = ?';
        $params[] = $_GET['mode'];
    }
    if (!empty($_GET['search'])) {
        $searchTerm = '%' . $_GET['search'] . '%';
        $where[] = '(a.application_number LIKE ? OR p.first_name LIKE ? OR p.surname LIKE ?)';
        array_push($params, $searchTerm, $searchTerm, $searchTerm);
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $countSql = "
        SELECT COUNT(*)
        FROM applications a
        LEFT JOIN personal_details p ON p.application_id = a.application_id
        LEFT JOIN programme_choices pc ON pc.application_id = a.application_id
        LEFT JOIN faculties f ON f.faculty_id = pc.faculty
        LEFT JOIN departments d ON d.dept_id = pc.department
        LEFT JOIN degree_types dt ON dt.degree_id = pc.degree_type
        LEFT JOIN study_modes sm ON sm.mode_id = pc.mode_of_study
        $whereSql
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRows = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRows / $limit));

    $dataSql = "
        SELECT a.application_id, a.application_number, a.status, a.submitted_at,
               p.first_name, p.surname,
               f.faculty_name, d.dept_name, dt.degree_name, sm.mode_name, c.course_title
        FROM applications a
        LEFT JOIN personal_details p ON p.application_id = a.application_id
        LEFT JOIN programme_choices pc ON pc.application_id = a.application_id
        LEFT JOIN faculties f ON f.faculty_id = pc.faculty
        LEFT JOIN departments d ON d.dept_id = pc.department
        LEFT JOIN degree_types dt ON dt.degree_id = pc.degree_type
        LEFT JOIN study_modes sm ON sm.mode_id = pc.mode_of_study
        LEFT JOIN courses c ON c.course_id = pc.course
        $whereSql
        ORDER BY a.submitted_at DESC, a.application_id DESC
        LIMIT $limit OFFSET $offset
    ";
    $dataStmt = $pdo->prepare($dataSql);
    $dataStmt->execute($params);
    $applications = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
}

function buildUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Applications Control Room</h1>
        <p class="panel-muted">Track admissions flow by faculty, programme, and status.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-light" href="reports.php">Export Summary</a>
        <a class="btn btn-primary" href="dashboard.php">Back to Command Center</a>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
        <div>
            <div class="stat-title">Total Applications</div>
            <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-paper-plane"></i></div>
        <div>
            <div class="stat-title">Submitted</div>
            <div class="stat-value"><?php echo number_format($stats['submitted']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-title">Admitted</div>
            <div class="stat-value"><?php echo number_format($stats['admitted']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        <div>
            <div class="stat-title">Rejected</div>
            <div class="stat-value"><?php echo number_format($stats['rejected']); ?></div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Filter Applications</h3>
            <div class="panel-muted">Apply multi-criteria filters and narrow to critical cases.</div>
        </div>
    </div>
    <div class="panel-body">
        <form class="row g-3" method="GET">
            <div class="col-md-3">
                <input type="text" class="form-control" name="search" placeholder="Search name or application no" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <?php foreach (['Draft', 'Submitted', 'Admitted', 'Rejected'] as $status): ?>
                        <option value="<?php echo $status; ?>" <?php echo (($_GET['status'] ?? '') === $status) ? 'selected' : ''; ?>><?php echo $status; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="faculty">
                    <option value="">All Faculties</option>
                    <?php foreach ($faculties as $faculty): ?>
                        <option value="<?php echo $faculty['faculty_id']; ?>" <?php echo (($_GET['faculty'] ?? '') == $faculty['faculty_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($faculty['faculty_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?php echo $department['dept_id']; ?>" <?php echo (($_GET['department'] ?? '') == $department['dept_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($department['dept_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="degree_type">
                    <option value="">All Degrees</option>
                    <?php foreach ($degrees as $degree): ?>
                        <option value="<?php echo $degree['degree_id']; ?>" <?php echo (($_GET['degree_type'] ?? '') == $degree['degree_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($degree['degree_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary w-100" type="submit">Apply</button>
            </div>
        </form>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Application Pipeline</h3>
            <div class="panel-muted">Showing <?php echo number_format($totalRows); ?> applications.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Application No.</th>
                        <th>Programme</th>
                        <th>Faculty</th>
                        <th>Status</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($applications)): ?>
                        <?php foreach ($applications as $app): ?>
                            <?php
                            $statusClass = 'status-muted';
                            if ($app['status'] === 'Submitted') {
                                $statusClass = 'status-warning';
                            } elseif ($app['status'] === 'Admitted') {
                                $statusClass = 'status-success';
                            } elseif ($app['status'] === 'Rejected') {
                                $statusClass = 'status-danger';
                            }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars(trim(($app['first_name'] ?? '') . ' ' . ($app['surname'] ?? ''))); ?></td>
                                <td><?php echo htmlspecialchars($app['application_number'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($app['course_title'] ?? $app['degree_name'] ?? 'Pending'); ?></td>
                                <td><?php echo htmlspecialchars($app['faculty_name'] ?? 'Unassigned'); ?></td>
                                <td><span class="status-chip <?php echo $statusClass; ?>"><?php echo htmlspecialchars($app['status'] ?? 'Draft'); ?></span></td>
                                <td><?php echo $app['submitted_at'] ? date('M d, Y', strtotime($app['submitted_at'])) : 'Not submitted'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No applications match these filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo buildUrl($page - 1); ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo buildUrl($i); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo buildUrl($page + 1); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
