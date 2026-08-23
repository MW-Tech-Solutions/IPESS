<?php
session_start();
require_once 'db.php';

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

$pageTitle = 'Program Dashboard';
$pageSubtitle = 'Track program-wide applications, supervisors, and student performance for ' . $deptName . '.';

require_once 'includes/dev_header.php';
require_once 'includes/sidebar.php';
require_once 'includes/dev_topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Program Command View</h1>
        <p class="panel-muted">Quick insight into admissions, supervision load, and student activity for <strong><?php echo htmlspecialchars($deptName); ?></strong>.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" onclick="location.reload()">Refresh</button>
        <a class="btn btn-primary" href="department-reports.php">View Reports</a>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
        <div>
            <div class="stat-title">Applications</div>
            <div class="stat-value" id="dept-applications-count">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
        <div>
            <div class="stat-title">Active Supervisors</div>
            <div class="stat-value" id="dept-supervisors-count">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div>
            <div class="stat-title">Enrolled Students</div>
            <div class="stat-value" id="dept-students-count">0</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div>
            <div class="stat-title">Pending Issues</div>
            <div class="stat-value" id="dept-issues-count">0</div>
        </div>
    </div>
</section>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Application Trends by Programme</h6>
                        <span class="text-muted small">This Semester</span>
                    </div>
                    <div class="text-muted" style="min-height:200px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:0.5rem;">
                        <i class="fas fa-chart-bar fa-2x"></i>
                        <span>No data yet</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="border rounded-4 p-4 h-100 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Supervisor Workload</h6>
                        <span class="text-muted small">Current Cycle</span>
                    </div>
                    <div class="text-muted" style="min-height:200px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:0.5rem;">
                        <i class="fas fa-chart-pie fa-2x"></i>
                        <span>No data yet</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Program Activity</h3>
            <div class="panel-muted">Latest actions from coordinators and reviewers.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="activity-list" id="dept-activity-list">
            <div class="text-muted">No data yet.</div>
        </div>
    </div>
</section>

<script>
async function loadDeptDashboard() {
    const [appsRes, supervisorsRes, studentsRes] = await Promise.all([
        fetch('api/dept-admin/applications.php?action=list'),
        fetch('api/dept-admin/supervisors.php?action=list'),
        fetch('api/dept-admin/students.php?action=list')
    ]);

    const apps = await appsRes.json();
    const supervisors = await supervisorsRes.json();
    const students = await studentsRes.json();

    const appsCount = (apps.success ? apps.data.length : 0);
    const supervisorsCount = (supervisors.success ? supervisors.data.filter(item => (item.status || '').toLowerCase() === 'active').length : 0);
    const studentsCount = (students.success ? students.data.length : 0);

    document.getElementById('dept-applications-count').textContent = appsCount;
    document.getElementById('dept-supervisors-count').textContent = supervisorsCount;
    document.getElementById('dept-students-count').textContent = studentsCount;
    document.getElementById('dept-issues-count').textContent = 0;
}

document.addEventListener('DOMContentLoaded', loadDeptDashboard);
</script>

<?php require_once 'includes/dev_footer.php'; ?>
