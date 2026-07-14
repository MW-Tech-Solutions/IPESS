<?php
session_start();
if (file_exists('db.php')) {
    require 'db.php';
}


$stats = ['total' => 0, 'pending' => 0, 'admitted' => 0, 'rejected' => 0]; 

if (isset($pdo)) {
    try {
        $statsSql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'Admitted' THEN 1 ELSE 0 END) as admitted,
                SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
            FROM applications
        ";
        $statsStmt = $pdo->query($statsSql);
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

$countSql = "SELECT COUNT(DISTINCT a.application_id) FROM applications a 
             LEFT JOIN personal_details p ON a.application_id = p.application_id 
             LEFT JOIN programme_choices pc ON pc.application_id = a.application_id AND pc.faculty > 0";

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
        FROM applications a 
        LEFT JOIN personal_details p ON a.application_id = p.application_id 
        LEFT JOIN programme_choices pc ON pc.application_id = a.application_id AND pc.faculty > 0
        LEFT JOIN faculties f ON pc.faculty = f.faculty_id
        LEFT JOIN departments d ON pc.department = d.dept_id
        LEFT JOIN degree_types dt ON pc.degree_type = dt.degree_id
        LEFT JOIN courses c ON pc.course = c.course_id
        LEFT JOIN study_modes sm ON pc.mode_of_study = sm.mode_id";
// Filter mapping
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

if (count($whereClauses) > 0) {
    $whereStr = " WHERE " . implode(" AND ", $whereClauses);
    $countSql .= $whereStr;
    $sql .= $whereStr;
}

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Let's check limit or keep it original
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
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

        <div class="content-container">
            
<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
        <div>
            <div class="stat-title">Total Applications</div>
            <div class="stat-value"><?= number_format($stats['total']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-title">Approved Applications</div>
            <div class="stat-value"><?= number_format($stats['admitted']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-title">Under Review</div>
            <div class="stat-value"><?= number_format($stats['pending']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        <div>
            <div class="stat-title">Rejected</div>
            <div class="stat-value"><?= number_format($stats['rejected']) ?></div>
        </div>
    </div>
</section>

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

        <div class="col-md-2">
            <label class="small fw-bold text-muted mb-1">Faculty</label>
            <select name="faculty" id="facultySelect" class="form-select form-select-sm" data-selected="<?php echo htmlspecialchars($_GET['faculty'] ?? ''); ?>">
                <option value="">All Faculties</option>
            </select>
        </div>

        <div class="col-md-2">
            <label class="small fw-bold text-muted mb-1">Department</label>
            <select name="department" id="deptSelect" class="form-select form-select-sm" data-selected="<?php echo htmlspecialchars($_GET['department'] ?? ''); ?>">
                <option value="">All Departments</option>
            </select>
        </div>

        <div class="col-md-2">
            <label class="small fw-bold text-muted mb-1">Programme</label>
            <select name="degree_type" id="degreeSelect" class="form-select form-select-sm" data-selected="<?php echo htmlspecialchars($_GET['degree_type'] ?? ''); ?>">
                <option value="">All Programmes</option>
            </select>
        </div>
        
        <div class="col-md-2">
            <label class="small fw-bold text-muted mb-1">Mode</label>
            <select name="mode_of_study" id="modeSelect" class="form-select form-select-sm" data-selected="<?php echo htmlspecialchars($_GET['mode_of_study'] ?? ''); ?>">
                <option value="">All Modes</option>
            </select>
        </div>

        <div class="col-md-1 d-flex align-items-end">
            <button type="submit" class="btn btn-sm btn-primary w-100 fw-medium">Apply</button>
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
                <button type="button" class="btn btn-sm btn-outline-primary border-0 view-app" data-app-no="<?php echo htmlspecialchars($row['application_number']); ?>">
                    <i class="bi bi-eye"></i> View
                </button>
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


<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.view-app').forEach(btn => {
        btn.addEventListener('click', () => {
            const appNo = btn.dataset.appNo || '';
            const frame = document.getElementById('appViewFrame');
            frame.src = `view.php?app_no=${encodeURIComponent(appNo)}&embed=1`;
            const modal = new bootstrap.Modal(document.getElementById('appViewModal'));
            modal.show();
        });
    });

    const apiBase = 'api/application-management.php';
    const facultySelect = document.getElementById('facultySelect');
    const deptSelect = document.getElementById('deptSelect');
    const degreeSelect = document.getElementById('degreeSelect');
    const modeSelect = document.getElementById('modeSelect');

    async function loadFaculties() {
        const res = await fetch(`${apiBase}?action=faculties`);
        const data = await res.json();
        if (!data.success) return;
        const selected = facultySelect.dataset.selected || '';
        data.data.forEach(row => {
            const opt = document.createElement('option');
            opt.value = row.id;
            opt.textContent = row.name;
            if (selected !== '' && String(selected) === String(row.id)) {
                opt.selected = true;
            }
            facultySelect.appendChild(opt);
        });
    }

    async function loadDepartments(facultyId) {
        deptSelect.innerHTML = '<option value="">All Departments</option>';
        const res = await fetch(`${apiBase}?action=departments&faculty_id=${encodeURIComponent(facultyId || '')}`);
        const data = await res.json();
        if (!data.success) return;
        const selected = deptSelect.dataset.selected || '';
        data.data.forEach(row => {
            const opt = document.createElement('option');
            opt.value = row.id;
            opt.textContent = row.name;
            if (selected !== '' && String(selected) === String(row.id)) {
                opt.selected = true;
            }
            deptSelect.appendChild(opt);
        });
    }

    async function loadDegrees() {
        const res = await fetch(`${apiBase}?action=degrees`);
        const data = await res.json();
        if (!data.success) return;
        const selected = degreeSelect.dataset.selected || '';
        data.data.forEach(row => {
            const opt = document.createElement('option');
            opt.value = row.id;
            opt.textContent = row.name;
            if (selected !== '' && String(selected) === String(row.id)) {
                opt.selected = true;
            }
            degreeSelect.appendChild(opt);
        });
    }

    async function loadModes() {
        const res = await fetch(`${apiBase}?action=modes`);
        const data = await res.json();
        if (!data.success) return;
        const selected = modeSelect.dataset.selected || '';
        data.data.forEach(row => {
            const opt = document.createElement('option');
            opt.value = row.id;
            opt.textContent = row.name;
            if (selected !== '' && String(selected) === String(row.id)) {
                opt.selected = true;
            }
            modeSelect.appendChild(opt);
        });
    }

    facultySelect.addEventListener('change', () => {
        loadDepartments(facultySelect.value);
    });

    Promise.all([loadFaculties(), loadDegrees(), loadModes()]).then(() => {
        const facultySelected = facultySelect.dataset.selected || '';
        if (facultySelected !== '') {
            loadDepartments(facultySelected);
        } else {
            loadDepartments('');
        }
    });
});
</script>

<div class="modal fade" id="appViewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Application Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <iframe id="appViewFrame" src="about:blank" style="width:100%;height:80vh;border:0;"></iframe>
      </div>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
