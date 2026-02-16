<?php
session_start();
require 'db.php';

// --- AUTHENTICATION MODULE ---
// Ideally, move this to a separate include file (e.g., auth_guard.php)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Redirect to login or show access denied
    // header("Location: login.php");
    // exit();
    // For demo purposes, we proceed, but normally you must enforce this.
}

// --- HELPER FUNCTIONS ---
function getStatusBadge($status) {
    return match ($status) {
        'Submitted' => 'bg-success',
        'Draft' => 'bg-warning text-dark',
        'Rejected' => 'bg-danger',
        'Admitted' => 'bg-primary',
        default => 'bg-secondary',
    };
}

// --- CONTROLLER LOGIC ---

// 1. Handle Filters
$filter_status = $_GET['status'] ?? 'All';
$search_query = $_GET['search'] ?? '';

// 2. Statistics Logic
// Fetch counts for the stats cards
$statsSQL = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) as submitted,
    SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'Admitted' THEN 1 ELSE 0 END) as admitted
    FROM applications";
$stats = $pdo->query($statsSQL)->fetch(PDO::FETCH_ASSOC);

// 3. Main Data Fetching (Modular Query Building)
// We JOIN personal_details and programme_choices to get human-readable data
$sql = "SELECT 
            a.application_id, 
            a.application_number, 
            a.status, 
            a.submitted_at, 
            a.created_at,
            pd.surname, 
            pd.first_name, 
            pd.phone,
            pc.department, 
            pc.degree_type
        FROM applications a
        LEFT JOIN personal_details pd ON a.application_id = pd.application_id
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
        WHERE 1=1";

$params = [];

// Apply Status Filter
if ($filter_status !== 'All') {
    $sql .= " AND a.status = ?";
    $params[] = $filter_status;
}

// Apply Search Filter
if (!empty($search_query)) {
    $sql .= " AND (a.application_number LIKE ? OR pd.surname LIKE ? OR pd.first_name LIKE ?)";
    $term = "%$search_query%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY a.submitted_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | PG School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        :root { --sidebar-width: 260px; --primary-color: #2c3e50; }
        body { background-color: #f4f6f9; font-family: 'Segoe UI', system-ui, sans-serif; }
        
        /* Layout */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0; left: 0;
            background: var(--primary-color);
            color: white;
            z-index: 1000;
            padding-top: 1rem;
        }
        .main-content { margin-left: var(--sidebar-width); padding: 2rem; }
        
        /* Components */
        .sidebar-link { color: rgba(255,255,255,0.8); text-decoration: none; display: block; padding: 12px 20px; border-radius: 4px; margin: 0 10px; transition: 0.2s; }
        .sidebar-link:hover, .sidebar-link.active { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-link i { margin-right: 10px; width: 20px; text-align: center; }
        
        .stat-card { border: none; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-icon { font-size: 2rem; opacity: 0.2; position: absolute; right: 20px; top: 20px; }
        
        .table-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; }
        .table th { background: #f8f9fa; font-weight: 600; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; }
        
        .avatar-initial { width: 35px; height: 35px; background: #e9ecef; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #495057; margin-right: 10px; }
    </style>
</head>
<body>

<nav class="sidebar">
    <div class="px-4 mb-4">
        <h4 class="fw-bold"><i class="bi bi-mortarboard-fill me-2"></i>PG Admin</h4>
        <small class="text-white-50">Admissions Portal v2.0</small>
    </div>
    
    <div class="d-flex flex-column gap-1">
        <a href="admin_dashboard.php" class="sidebar-link active"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="#" class="sidebar-link"><i class="bi bi-people"></i> Applicants</a>
        <a href="#" class="sidebar-link"><i class="bi bi-file-earmark-text"></i> Documents</a>
        <a href="#" class="sidebar-link"><i class="bi bi-sliders"></i> Settings</a>
        <hr class="border-secondary mx-3">
        <a href="logout.php" class="sidebar-link text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</nav>

<main class="main-content">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-dark">Dashboard Overview</h2>
            <p class="text-muted">Welcome back, Administrator.</p>
        </div>
        <div>
            <button class="btn btn-primary"><i class="bi bi-download me-2"></i>Export Report</button>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card stat-card bg-primary text-white h-100">
                <div class="card-body">
                    <h6 class="text-white-50">Total Applications</h6>
                    <h2 class="mb-0 fw-bold"><?php echo number_format($stats['total']); ?></h2>
                    <i class="bi bi-folder2-open stat-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-success text-white h-100">
                <div class="card-body">
                    <h6 class="text-white-50">Submitted</h6>
                    <h2 class="mb-0 fw-bold"><?php echo number_format($stats['submitted']); ?></h2>
                    <i class="bi bi-check-circle stat-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-warning h-100">
                <div class="card-body">
                    <h6 class="text-dark-50">Drafts / Pending</h6>
                    <h2 class="mb-0 fw-bold text-dark"><?php echo number_format($stats['pending']); ?></h2>
                    <i class="bi bi-hourglass-split stat-icon text-dark"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-info text-white h-100">
                <div class="card-body">
                    <h6 class="text-white-50">Admitted</h6>
                    <h2 class="mb-0 fw-bold"><?php echo number_format($stats['admitted']); ?></h2>
                    <i class="bi bi-award stat-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" 
                               placeholder="Search by Name or Ref No..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="All" <?php echo $filter_status == 'All' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="Submitted" <?php echo $filter_status == 'Submitted' ? 'selected' : ''; ?>>Submitted</option>
                        <option value="Draft" <?php echo $filter_status == 'Draft' ? 'selected' : ''; ?>>Drafts</option>
                        <option value="Admitted" <?php echo $filter_status == 'Admitted' ? 'selected' : ''; ?>>Admitted</option>
                        <option value="Rejected" <?php echo $filter_status == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-5 text-end">
                    <a href="admin_dashboard.php" class="btn btn-light border">Clear Filters</a>
                </div>
            </form>
        </div>
    </div>

    <div class="table-card">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="mb-0 fw-bold">Recent Applications</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Applicant</th>
                        <th>Reference / Prog</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($applications) > 0): ?>
                        <?php foreach ($applications as $app): ?>
                            <?php 
                                $initial = strtoupper(substr($app['surname'] ?? 'U', 0, 1));
                                $fullname = htmlspecialchars(($app['surname'] ?? 'Unknown') . ' ' . ($app['first_name'] ?? 'Applicant'));
                                $dept = htmlspecialchars($app['department'] ?? 'N/A');
                                $degree = htmlspecialchars($app['degree_type'] ?? 'PG');
                                $ref = $app['application_number'] ?? 'DRAFT-ID-'.$app['application_id'];
                                $date = $app['submitted_at'] ? date('M d, Y', strtotime($app['submitted_at'])) : 'Not Submitted';
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-initial"><?php echo $initial; ?></div>
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo $fullname; ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($app['phone'] ?? ''); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-primary small"><?php echo htmlspecialchars($ref); ?></div>
                                    <small class="text-muted"><?php echo $degree . ' - ' . $dept; ?></small>
                                </td>
                                <td>
                                    <span class="badge rounded-pill <?php echo getStatusBadge($app['status']); ?>">
                                        <?php echo htmlspecialchars($app['status']); ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?php echo $date; ?></td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <a href="admin_view_applicant.php?id=<?php echo $app['application_id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            View
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="#"><i class="bi bi-check-lg text-success me-2"></i>Admit</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="bi bi-x-lg text-danger me-2"></i>Reject</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="#"><i class="bi bi-envelope me-2"></i>Email Applicant</a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                No applications found matching your criteria.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card-footer bg-white py-3">
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item disabled"><a class="page-link" href="#">Previous</a></li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                    <li class="page-item"><a class="page-link" href="#">Next</a></li>
                </ul>
            </nav>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>


