<?php
$pageTitle = 'General Desk Dashboard';
$pageSubtitle = 'Quick access to your modular duties and assigned records.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
require_once 'includes/db.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userDeptId   = null;
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
            $userDeptId   = $row['department_id'] ? (int) $row['department_id'] : null;
            $userDeptName = $row['dept_name'] ?? 'All Departments';
        }
    } catch (PDOException $e) {}
}

// --- Check ALL assignable permissions ---
$canViewApplications  = has_permission('view_applications') || has_permission('view_applicants');
$canVerifyDocs        = has_permission('verify_applicants');
$canDeptReview        = has_permission('department_review');
$canFacultyReview     = has_permission('faculty_review');
$canPgReview          = has_permission('pg_review') || has_permission('review_applications');
$canManageAdmissions  = has_permission('manage_admissions');
$canIctProcessing     = has_permission('ict_processing');
$canManageUsers       = has_permission('user_management') || has_permission('manage_users');
$canReports           = has_permission('reports');
$canViewAuditLogs     = has_permission('view_audit_logs');
$canManageStudents    = has_permission('manage_students') || has_permission('view_students');
$canAssignSupervisor  = has_permission('assign_supervisor') || has_permission('supervisor_management');
$canManageRoles       = has_permission('role_management') || has_permission('manage_roles');
$canSettings          = has_permission('settings');

// Aggregate: does user have ANY duty at all?
$hasAnyDuty = $canViewApplications || $canVerifyDocs || $canDeptReview || $canFacultyReview
           || $canPgReview || $canManageAdmissions || $canIctProcessing || $canManageUsers
           || $canReports || $canViewAuditLogs || $canManageStudents || $canAssignSupervisor
           || $canManageRoles || $canSettings;

// --- Stat counts ---
$totalApplications   = 0;
$pendingDocsCount    = 0;
$pendingDeptCount    = 0;
$pendingFacultyCount = 0;
$pendingPgCount      = 0;
$totalStudents       = 0;

if (isset($pdo)) {
    try {
        if ($canViewApplications) {
            $sql = "SELECT COUNT(DISTINCT a.application_id) FROM applications a
                    LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
                    WHERE a.status != 'Draft'";
            if ($userDeptId !== null) {
                $sql .= " AND (pc.department = ? OR a.department_id = ?)";
                $s = $pdo->prepare($sql); $s->execute([$userDeptId, $userDeptId]);
            } else {
                $s = $pdo->query($sql);
            }
            $totalApplications = (int) $s->fetchColumn();
        }

        if ($canVerifyDocs) {
            $sql = "SELECT COUNT(DISTINCT a.application_id)
                    FROM applications a
                    JOIN documents d ON a.application_id = d.application_id
                    LEFT JOIN document_verification dv ON d.doc_id = dv.upload_id
                    WHERE (dv.verification_status IS NULL OR dv.verification_status = 'Pending')";
            if ($userDeptId !== null) {
                $sql .= " AND EXISTS (SELECT 1 FROM programme_choices pc WHERE pc.application_id = a.application_id AND (pc.department = ? OR a.department_id = ?))";
                $s = $pdo->prepare($sql); $s->execute([$userDeptId, $userDeptId]);
            } else {
                $s = $pdo->query($sql);
            }
            $pendingDocsCount = (int) $s->fetchColumn();
        }

        if ($canDeptReview) {
            $sql = "SELECT COUNT(DISTINCT a.application_id) FROM applications a
                    LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
                    WHERE a.status = 'Submitted'
                    AND (a.current_status IS NULL OR a.current_status IN ('Submitted','ASSIGNED_TO_DEPARTMENT','UNDER_DEPT_REVIEW'))";
            if ($userDeptId !== null) {
                $sql .= " AND (pc.department = ? OR a.department_id = ?)";
                $s = $pdo->prepare($sql); $s->execute([$userDeptId, $userDeptId]);
            } else {
                $s = $pdo->query($sql);
            }
            $pendingDeptCount = (int) $s->fetchColumn();
        }

        if ($canFacultyReview) {
            $s = $pdo->query("SELECT COUNT(DISTINCT application_id) FROM applications
                              WHERE current_status IN ('DEPT_APPROVED','UNDER_FACULTY_REVIEW')");
            $pendingFacultyCount = (int) $s->fetchColumn();
        }

        if ($canPgReview || $canManageAdmissions) {
            $s = $pdo->query("SELECT COUNT(DISTINCT application_id) FROM applications
                              WHERE current_status IN ('FACULTY_APPROVED','DEPT_APPROVED','UNDER_PG_REVIEW')");
            $pendingPgCount = (int) $s->fetchColumn();
        }

        if ($canManageStudents) {
            $s = $pdo->query("SELECT COUNT(*) FROM student_profiles");
            $totalStudents = (int) $s->fetchColumn();
        }
    } catch (PDOException $e) {}
}
?>

<section class="page-hero">
    <div>
        <h1>Welcome Back</h1>
        <p class="panel-muted">Department: <strong><?php echo htmlspecialchars($userDeptName ?: 'None Assigned'); ?></strong></p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" onclick="location.reload()"><i class="fas fa-sync me-2"></i>Refresh</button>
    </div>
</section>

<?php if (!$hasAnyDuty): ?>
    <div class="alert alert-warning p-4 rounded shadow-sm border-start border-4 border-warning mt-3">
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

        <?php if ($canViewApplications): ?>
        <div class="stat-card cursor-pointer" onclick="location.href='application-management.php'">
            <div class="stat-icon bg-primary text-white"><i class="fas fa-file-alt"></i></div>
            <div>
                <div class="stat-title text-muted">Total Applications</div>
                <div class="stat-value"><?php echo number_format($totalApplications); ?></div>
                <div class="small text-primary mt-2">View &amp; Manage Records <i class="fas fa-arrow-right ms-1"></i></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canVerifyDocs): ?>
        <div class="stat-card cursor-pointer" onclick="location.href='document-verification.php'">
            <div class="stat-icon bg-info text-white"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="stat-title text-muted">Pending Document Verification</div>
                <div class="stat-value"><?php echo number_format($pendingDocsCount); ?></div>
                <div class="small text-info mt-2">Verify Uploaded Files <i class="fas fa-arrow-right ms-1"></i></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canDeptReview): ?>
        <div class="stat-card cursor-pointer" onclick="location.href='academic-review.php'">
            <div class="stat-icon bg-success text-white"><i class="fas fa-book-open"></i></div>
            <div>
                <div class="stat-title text-muted">Pending Dept. Review</div>
                <div class="stat-value"><?php echo number_format($pendingDeptCount); ?></div>
                <div class="small text-success mt-2">Process Departmental Decisions <i class="fas fa-arrow-right ms-1"></i></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canFacultyReview): ?>
        <div class="stat-card cursor-pointer" onclick="location.href='<?php echo app_url('ADMIN/faculty/applications.php'); ?>'">
            <div class="stat-icon bg-warning text-white"><i class="fas fa-university"></i></div>
            <div>
                <div class="stat-title text-muted">Pending Faculty Review</div>
                <div class="stat-value"><?php echo number_format($pendingFacultyCount); ?></div>
                <div class="small text-warning mt-2">Faculty Verification Stage <i class="fas fa-arrow-right ms-1"></i></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canPgReview || $canManageAdmissions): ?>
        <div class="stat-card cursor-pointer" onclick="location.href='<?php echo app_url('ADMIN/pg-admin/applications.php'); ?>'">
            <div class="stat-icon bg-purple text-white" style="background:#7c3aed !important"><i class="fas fa-graduation-cap"></i></div>
            <div>
                <div class="stat-title text-muted">Pending PG Review</div>
                <div class="stat-value"><?php echo number_format($pendingPgCount); ?></div>
                <div class="small mt-2" style="color:#7c3aed">PG School Evaluation <i class="fas fa-arrow-right ms-1"></i></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canIctProcessing): ?>
        <div class="stat-card cursor-pointer" onclick="location.href='<?php echo app_url('ADMIN/ict-staff/admissions.php'); ?>'">
            <div class="stat-icon bg-secondary text-white"><i class="fas fa-id-card"></i></div>
            <div>
                <div class="stat-title text-muted">ICT Admissions Processing</div>
                <div class="stat-value"><i class="fas fa-cog fa-spin text-muted"></i></div>
                <div class="small text-secondary mt-2">Matric &amp; Letter Generation <i class="fas fa-arrow-right ms-1"></i></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canManageStudents): ?>
        <div class="stat-card cursor-pointer" onclick="location.href='<?php echo app_url('ADMIN/super-admin/manage-students.php'); ?>'">
            <div class="stat-icon bg-teal text-white" style="background:#0d9488 !important"><i class="fas fa-user-graduate"></i></div>
            <div>
                <div class="stat-title text-muted">Total Students</div>
                <div class="stat-value"><?php echo number_format($totalStudents); ?></div>
                <div class="small mt-2" style="color:#0d9488">Manage Student Records <i class="fas fa-arrow-right ms-1"></i></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canManageUsers): ?>
        <div class="stat-card cursor-pointer" onclick="location.href='<?php echo app_url('ADMIN/super-admin/user-management.php'); ?>'">
            <div class="stat-icon bg-dark text-white"><i class="fas fa-users-cog"></i></div>
            <div>
                <div class="stat-title text-muted">User Management</div>
                <div class="stat-value"><i class="fas fa-arrow-right text-muted"></i></div>
                <div class="small text-dark mt-2">Manage System Accounts <i class="fas fa-arrow-right ms-1"></i></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canReports): ?>
        <div class="stat-card cursor-pointer" onclick="location.href='<?php echo app_url('ADMIN/super-admin/reports.php'); ?>'">
            <div class="stat-icon bg-indigo text-white" style="background:#4338ca !important"><i class="fas fa-chart-bar"></i></div>
            <div>
                <div class="stat-title text-muted">System Reports</div>
                <div class="stat-value"><i class="fas fa-arrow-right text-muted"></i></div>
                <div class="small mt-2" style="color:#4338ca">Access Analytics &amp; Reports <i class="fas fa-arrow-right ms-1"></i></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canViewAuditLogs): ?>
        <div class="stat-card cursor-pointer" onclick="location.href='<?php echo app_url('ADMIN/super-admin/audit-logs.php'); ?>'">
            <div class="stat-icon bg-danger text-white"><i class="fas fa-shield-alt"></i></div>
            <div>
                <div class="stat-title text-muted">Audit Intelligence</div>
                <div class="stat-value"><i class="fas fa-arrow-right text-muted"></i></div>
                <div class="small text-danger mt-2">View System Audit Logs <i class="fas fa-arrow-right ms-1"></i></div>
            </div>
        </div>
        <?php endif; ?>

    </section>

    <!-- Access Matrix -->
    <div class="row g-4 mt-2">
        <div class="col-lg-6">
            <div class="panel h-100">
                <div class="panel-header">
                    <h3 class="panel-title">Your Access Matrix</h3>
                </div>
                <div class="panel-body">
                    <p class="text-muted small">The privileges below have been granted specifically to your account:</p>
                    <ul class="list-group list-group-flush">
                        <?php
                        $allPerms = [
                            'view_applications'  => ['icon' => 'fa-folder',         'label' => 'View & Access Application Records', 'granted' => $canViewApplications],
                            'verify_applicants'  => ['icon' => 'fa-file-signature',  'label' => 'Document Verification Desk',       'granted' => $canVerifyDocs],
                            'department_review'  => ['icon' => 'fa-user-check',      'label' => 'Departmental Academic Review',     'granted' => $canDeptReview],
                            'faculty_review'     => ['icon' => 'fa-university',      'label' => 'Faculty Verification Stage',       'granted' => $canFacultyReview],
                            'pg_review'          => ['icon' => 'fa-graduation-cap',  'label' => 'Postgraduate School Evaluation',   'granted' => $canPgReview],
                            'manage_admissions'  => ['icon' => 'fa-gavel',           'label' => 'Admission Decision Making',        'granted' => $canManageAdmissions],
                            'ict_processing'     => ['icon' => 'fa-id-card',         'label' => 'ICT Admissions Processing',        'granted' => $canIctProcessing],
                            'manage_students'    => ['icon' => 'fa-user-graduate',   'label' => 'Manage Student Records',           'granted' => $canManageStudents],
                            'assign_supervisor'  => ['icon' => 'fa-user-plus',       'label' => 'Supervisor Assignment',            'granted' => $canAssignSupervisor],
                            'user_management'    => ['icon' => 'fa-users-cog',       'label' => 'System User Management',           'granted' => $canManageUsers],
                            'role_management'    => ['icon' => 'fa-user-shield',     'label' => 'Role Management',                  'granted' => $canManageRoles],
                            'reports'            => ['icon' => 'fa-chart-bar',       'label' => 'Access System Reports',            'granted' => $canReports],
                            'view_audit_logs'    => ['icon' => 'fa-shield-alt',      'label' => 'View Audit Logs',                  'granted' => $canViewAuditLogs],
                            'settings'           => ['icon' => 'fa-cog',             'label' => 'System Settings',                  'granted' => $canSettings],
                        ];
                        foreach ($allPerms as $perm):
                            if (!$perm['granted']) continue;
                        ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="fas <?php echo $perm['icon']; ?> me-2 text-muted"></i><?php echo htmlspecialchars($perm['label']); ?></span>
                            <span class="badge bg-success">Granted</span>
                        </li>
                        <?php endforeach; ?>
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
                            <h6 class="fw-bold mb-1">Scope Limitation</h6>
                            <p class="text-muted small mb-0">All applicant files, documents, and review items visible to you are scoped to your assigned department (if set). You will not see applicants outside your scope.</p>
                        </div>
                    </div>
                    <div class="d-flex">
                        <div class="flex-shrink-0"><i class="fas fa-lock text-muted me-3 mt-1"></i></div>
                        <div>
                            <h6 class="fw-bold mb-1">Audit Trail Active</h6>
                            <p class="text-muted small mb-0">Every decision, approval, or rejection you perform is recorded in the system audit database linked to your administrator profile.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
