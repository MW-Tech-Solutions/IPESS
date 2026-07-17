<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$sidebarDisplayName = 'General Admin';
try {
    require_once __DIR__ . '/../../admin/includes/db.php';
    $sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
    if ($sessionUserId > 0 && isset($pdo)) {
        $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1");
        $stmt->execute([$sessionUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $name  = trim((string) ($row['full_name'] ?? ''));
        $email = trim((string) ($row['email'] ?? ''));
        if ($name !== '') {
            $sidebarDisplayName = $name;
        } elseif ($email !== '') {
            $sidebarDisplayName = $email;
        }
    }
} catch (Exception $e) {
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">
            <img src="<?php echo app_url('ADMIN/images/ipess_logo.png'); ?>" alt="IPESS Logo" class="sidebar-brand-logo">
        </div>
        <div class="brand-text">
            <span class="brand-name">IPESS JOSTUM</span>
            <span class="brand-sub">General Desk</span>
        </div>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-label">Workflow</div>
        <ul class="sidebar-nav">
            <li>
                <a class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- Application Management -->
            <?php if (has_permission('view_applications') || has_permission('view_applicants')): ?>
            <li>
                <a class="<?php echo $currentPage === 'application-management.php' ? 'active' : ''; ?>" href="application-management.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Application Management</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Document Verification -->
            <?php if (has_permission('verify_applicants')): ?>
            <li>
                <a class="<?php echo $currentPage === 'document-verification.php' ? 'active' : ''; ?>" href="document-verification.php">
                    <i class="fas fa-check-circle"></i>
                    <span>Document Verification</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Departmental Academic Review -->
            <?php if (has_permission('department_review')): ?>
            <li>
                <a class="<?php echo $currentPage === 'academic-review.php' ? 'active' : ''; ?>" href="academic-review.php">
                    <i class="fas fa-book-open"></i>
                    <span>Departmental Review</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Faculty Verification Stage -->
            <?php if (has_permission('faculty_review')): ?>
            <li>
                <a class="<?php echo $currentPage === 'applications.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/faculty/applications.php'); ?>">
                    <i class="fas fa-university"></i>
                    <span>Faculty Review</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- PG School Review / Admission Decisions -->
            <?php if (has_permission('pg_review') || has_permission('review_applications') || has_permission('manage_admissions')): ?>
            <li>
                <a class="<?php echo $currentPage === 'applications.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/pg-admin/applications.php'); ?>">
                    <i class="fas fa-graduation-cap"></i>
                    <span>PG School Review</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- ICT Admissions Processing -->
            <?php if (has_permission('ict_processing')): ?>
            <li>
                <a class="<?php echo $currentPage === 'admissions.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/ict-staff/admissions.php'); ?>">
                    <i class="fas fa-id-card"></i>
                    <span>Admissions Processing</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Supervisor Assignment -->
            <?php if (has_permission('assign_supervisor') || has_permission('supervisor_management')): ?>
            <li>
                <a class="<?php echo $currentPage === 'supervisor-management.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/dept-admin/supervisor-management.php'); ?>">
                    <i class="fas fa-user-plus"></i>
                    <span>Supervisor Assignment</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Admin Tools -->
    <?php if (has_permission('manage_students') || has_permission('view_students') || has_permission('user_management') || has_permission('manage_users') || has_permission('role_management') || has_permission('manage_roles') || has_permission('view_audit_logs') || has_permission('settings') || has_permission('reports')): ?>
    <div class="sidebar-section">
        <div class="sidebar-label">Administration</div>
        <ul class="sidebar-nav">

            <?php if ((has_permission('manage_students') || has_permission('view_students')) && in_array(normalize_role(current_user_role()), ['SUPER_ADMIN', 'ICT_ADMIN'], true)): ?>
            <li>
                <a class="<?php echo $currentPage === 'manage-students.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/manage-students.php'); ?>">
                    <i class="fas fa-user-graduate"></i>
                    <span>Manage Students</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if ((has_permission('user_management') || has_permission('manage_users')) && in_array(normalize_role(current_user_role()), ['SUPER_ADMIN', 'ICT_ADMIN'], true)): ?>
            <li>
                <a class="<?php echo $currentPage === 'user-management.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/user-management.php'); ?>">
                    <i class="fas fa-users-cog"></i>
                    <span>User Management</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if ((has_permission('role_management') || has_permission('manage_roles')) && in_array(normalize_role(current_user_role()), ['SUPER_ADMIN', 'ICT_ADMIN'], true)): ?>
            <li>
                <a class="<?php echo $currentPage === 'role-management.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/role-management.php'); ?>">
                    <i class="fas fa-user-shield"></i>
                    <span>Role Management</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (has_permission('reports')): ?>
            <?php 
                $currentNormRole = normalize_role(current_user_role());
                $canAccessSuperReports = in_array($currentNormRole, ['SUPER_ADMIN', 'ICT_ADMIN'], true);
                $canAccessAdminReports = in_array($currentNormRole, ['PG_ADMIN', 'SUPER_ADMIN'], true);
            ?>
            <?php if ($canAccessSuperReports || $canAccessAdminReports): ?>
            <li>
                <a class="<?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>" href="<?php echo $canAccessSuperReports ? app_url('ADMIN/super-admin/reports.php') : app_url('ADMIN/admin/reports.php'); ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <?php endif; ?>
            <?php endif; ?>

            <?php if (has_permission('view_audit_logs') && in_array(normalize_role(current_user_role()), ['SUPER_ADMIN', 'ICT_ADMIN'], true)): ?>
            <li>
                <a class="<?php echo $currentPage === 'audit-logs.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/audit-logs.php'); ?>">
                    <i class="fas fa-shield-alt"></i>
                    <span>Audit Logs</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (has_permission('settings') && in_array(normalize_role(current_user_role()), ['SUPER_ADMIN', 'ICT_ADMIN'], true)): ?>
            <li>
                <a class="<?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/settings.php'); ?>">
                    <i class="fas fa-cog"></i>
                    <span>System Settings</span>
                </a>
            </li>
            <?php endif; ?>

        </ul>
    </div>
    <?php endif; ?>

    <div class="sidebar-footer">
        <div><?php echo htmlspecialchars($sidebarDisplayName, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
</aside>
<style>
.sidebar-brand-logo {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 10px;
}
</style>
