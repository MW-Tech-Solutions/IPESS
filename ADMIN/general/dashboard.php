<?php
$pageTitle = 'General Desk Dashboard';
$pageSubtitle = 'Quick access to your modular duties and assigned records.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
require_once 'includes/db.php';

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
            $userDeptName = $row['dept_name'] ?? 'All Departments';
        }
    } catch (PDOException $e) {
    }
}

$hasViewRecords = has_permission('view_applications');
$hasVerifyDocs = has_permission('verify_applicants');
$hasApproveApps = has_permission('department_review');

$totalApplications = 0;
$pendingDocsCount = 0;
$pendingAcademicCount = 0;

if ($pdo) {
    try {
        if ($hasViewRecords) {
            $sql = "SELECT COUNT(DISTINCT a.application_id) 
                    FROM applications a 
                    LEFT JOIN programme_choices pc ON a.application_id = pc.application_id";
            if ($userDeptId !== null) {
                $sql .= " WHERE (pc.department = ? OR a.department_id = ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$userDeptId, $userDeptId]);
            } else {
                $stmt = $pdo->query($sql);
            }
            $totalApplications = (int) $stmt->fetchColumn();
        }

        if ($hasVerifyDocs) {
            $sql = "SELECT COUNT(DISTINCT a.application_id) 
                    FROM applications a
                    JOIN documents d ON a.application_id = d.application_id
                    LEFT JOIN document_verification dv ON d.doc_id = dv.upload_id
                    LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
                    WHERE (dv.verification_status IS NULL OR dv.verification_status = 'Pending')";
            if ($userDeptId !== null) {
                $sql .= " AND (pc.department = ? OR a.department_id = ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$userDeptId, $userDeptId]);
            } else {
                $stmt = $pdo->query($sql);
            }
            $pendingDocsCount = (int) $stmt->fetchColumn();
        }

        if ($hasApproveApps) {
            $sql = "SELECT COUNT(DISTINCT a.application_id) 
                    FROM applications a
                    LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
                    WHERE a.status = 'Submitted' AND (a.current_status IS NULL OR a.current_status IN ('Submitted', 'ASSIGNED_TO_DEPARTMENT', 'UNDER_DEPT_REVIEW'))";
            if ($userDeptId !== null) {
                $sql .= " AND (pc.department = ? OR a.department_id = ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$userDeptId, $userDeptId]);
            } else {
                $stmt = $pdo->query($sql);
            }
            $pendingAcademicCount = (int) $stmt->fetchColumn();
        }
    } catch (PDOException $e) {
    }
}
?>

<section class="page-hero">
    <div>
        <h1>Welcome Back</h1>
        <p class="panel-muted">Your current assigned department: <strong><?php echo htmlspecialchars($userDeptName ?: 'None Assigned'); ?></strong></p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" onclick="location.reload()"><i class="fas fa-sync me-2"></i>Refresh</button>
    </div>
</section>

<?php if (!$hasViewRecords && !$hasVerifyDocs && !$hasApproveApps): ?>
    <div class="alert alert-warning p-4 rounded shadow-sm border-start border-4 border-warning">
        <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-triangle fa-2x me-3 text-warning"></i>
            <div>
                <h5 class="alert-heading fw-bold mb-1">No Duties Assigned</h5>
                <p class="mb-0">You do not currently have any active duties or permission overrides assigned to your account. Please contact the system administrator to configure your access privileges.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <section class="stat-grid mt-4">
        <?php if ($hasViewRecords): ?>
            <div class="stat-card cursor-pointer" onclick="location.href='application-management.php'">
                <div class="stat-icon bg-primary text-white"><i class="fas fa-file-alt"></i></div>
                <div>
                    <div class="stat-title text-muted">My Department Applications</div>
                    <div class="stat-value"><?php echo number_format($totalApplications); ?></div>
                    <div class="small text-primary mt-2">Manage & View Records <i class="fas fa-arrow-right ms-1"></i></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($hasVerifyDocs): ?>
            <div class="stat-card cursor-pointer" onclick="location.href='document-verification.php'">
                <div class="stat-icon bg-info text-white"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="stat-title text-muted">Pending Verification</div>
                    <div class="stat-value"><?php echo number_format($pendingDocsCount); ?></div>
                    <div class="small text-info mt-2">Verify Uploaded Files <i class="fas fa-arrow-right ms-1"></i></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($hasApproveApps): ?>
            <div class="stat-card cursor-pointer" onclick="location.href='academic-review.php'">
                <div class="stat-icon bg-success text-white"><i class="fas fa-book-open"></i></div>
                <div>
                    <div class="stat-title text-muted">Pending Academic Review</div>
                    <div class="stat-value"><?php echo number_format($pendingAcademicCount); ?></div>
                    <div class="small text-success mt-2">Process Decisions <i class="fas fa-arrow-right ms-1"></i></div>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <div class="row g-4 mt-2">
        <div class="col-lg-6">
            <div class="panel h-100">
                <div class="panel-header">
                    <h3 class="panel-title">Your Access Matrix</h3>
                </div>
                <div class="panel-body">
                    <p class="text-muted small">The privileges below have been granted specifically to your account to delegate workflows:</p>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="fas fa-folder me-2 text-muted"></i> View &amp; Access Records</span>
                            <span class="badge <?php echo $hasViewRecords ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $hasViewRecords ? 'Granted' : 'None'; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="fas fa-file-signature me-2 text-muted"></i> Document Verification Desk</span>
                            <span class="badge <?php echo $hasVerifyDocs ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $hasVerifyDocs ? 'Granted' : 'None'; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="fas fa-user-check me-2 text-muted"></i> Academic Decision Maker</span>
                            <span class="badge <?php echo $hasApproveApps ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $hasApproveApps ? 'Granted' : 'None'; ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel h-100">
                <div class="panel-header">
                    <h3 class="panel-title">System Guidance</h3>
                </div>
                <div class="panel-body">
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0"><i class="fas fa-info-circle text-primary me-3 mt-1"></i></div>
                        <div>
                            <h6 class="fw-bold mb-1">Department Limitation</h6>
                            <p class="text-muted small mb-0">All applicant files, documents, and review items are locked to your assigned department. You will not see applicants from other programs.</p>
                        </div>
                    </div>
                    <div class="d-flex">
                        <div class="flex-shrink-0"><i class="fas fa-lock text-muted me-3 mt-1"></i></div>
                        <div>
                            <h6 class="fw-bold mb-1">Audit Log Logging</h6>
                            <p class="text-muted small mb-0">Every decision, approval, or rejection you perform is recorded in the system audit database linked to your administrator profile.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
