        <?php
        $currentPage = basename($_SERVER['PHP_SELF']);
        $sidebarDisplayName = 'Super Admin';
        try {
            require_once __DIR__ . '/db.php';
            $sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
            if ($sessionUserId > 0 && isset($pdo)) {
                $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1");
                $stmt->execute([$sessionUserId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $name = trim((string) ($row['full_name'] ?? ''));
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
                    <img src="../images/ipess_logo.png" alt="IPESS Logo" class="sidebar-brand-logo">
                </div>
                <div class="brand-text">
                    <span class="brand-name">IPESS FUAM</span>
                    <span class="brand-sub">Super Admin Suite</span>
                </div>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-label">Core</div>
                <ul class="sidebar-nav">
                    <li>
                        <a class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/dashboard.php'); ?>">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Command Center</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'user-management.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/user-management.php'); ?>">
                            <i class="fas fa-users"></i>
                            <span>User Management</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'manage-students.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/manage-students.php'); ?>">
                            <i class="fas fa-user-graduate"></i>
                            <span>Manage Students</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'role-management.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/role-management.php'); ?>">
                            <i class="fas fa-user-shield"></i>
                            <span>Role Management</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'audit-logs.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/audit-logs.php'); ?>">
                            <i class="fas fa-shield-alt"></i>
                            <span>Audit Intelligence</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/settings.php'); ?>">
                            <i class="fas fa-cog"></i>
                            <span>System Settings</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'content-management.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/portal-admin/content-management.php'); ?>">
                            <i class="fas fa-bullhorn"></i>
                            <span>Portal Content</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-label">Admissions</div>
                <ul class="sidebar-nav">
                    <li>
                        <a class="<?php echo $currentPage === 'application-management.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/admin/application-management.php'); ?>">
                            <i class="fas fa-file-alt"></i>
                            <span>Application Management</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'document-verification.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/admin/document-verification.php'); ?>">
                            <i class="fas fa-check-circle"></i>
                            <span>Document Verification</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'academic-review.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/admin/academic-review.php'); ?>">
                            <i class="fas fa-book-open"></i>
                            <span>Academic Review</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'assigned-applications.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/reviewer/assigned-applications.php'); ?>">
                            <i class="fas fa-user-shield"></i>
                            <span>PG Review</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'referees.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/admin/referees.php'); ?>">
                            <i class="fas fa-user-check"></i>
                            <span>Referees</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'admission-decisions.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/admin/admission-decisions.php'); ?>">
                            <i class="fas fa-gavel"></i>
                            <span>Admission Decisions</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-label">Academics</div>
                <ul class="sidebar-nav">
                    <li>
                        <a class="<?php echo $currentPage === 'faculties.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/faculties.php'); ?>">
                            <i class="fas fa-university"></i>
                            <span>Manage Faculties</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'departments.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/departments.php'); ?>">
                            <i class="fas fa-building"></i>
                            <span>Manage Departments</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'programmes.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/programmes.php'); ?>">
                            <i class="fas fa-graduation-cap"></i>
                            <span>Manage Programmes</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'courses.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/courses.php'); ?>">
                            <i class="fas fa-book"></i>
                            <span>Manage Courses</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'capacities.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/capacities.php'); ?>">
                            <i class="fas fa-chart-pie"></i>
                            <span>Programme Capacity</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'assign-supervisor.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/assign-supervisor.php'); ?>">
                            <i class="fas fa-user-tie"></i>
                            <span>Manage Supervisors</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-label">Analysis & Reports</div>
                <ul class="sidebar-nav">
                    <li>
                        <a class="<?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/reports.php'); ?>">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'analytics.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/analytics.php'); ?>">
                            <i class="fas fa-chart-line"></i>
                            <span>Analytics</span>
                        </a>
                    </li>
                </ul>
            </div>
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
