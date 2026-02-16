        <?php
        $currentPage = basename($_SERVER['PHP_SELF']);
        $sidebarDisplayName = 'Department Admin';
        try {
            require_once __DIR__ . '/../../admin/includes/db.php';
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
                    <img src="../images/logo.jpeg" alt="JOSTUM Logo" class="sidebar-brand-logo">
                </div>
                <div class="brand-text">
                    <span class="brand-name">JOSTUM PG</span>
                    <span class="brand-sub">Department Admin</span>
                </div>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-label">Overview</div>
                <ul class="sidebar-nav">
                    <li>
                        <a class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Department Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'department-applications.php' ? 'active' : ''; ?>" href="department-applications.php">
                            <i class="fas fa-folder-open"></i>
                            <span>Applications</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'supervisor-management.php' ? 'active' : ''; ?>" href="supervisor-management.php">
                            <i class="fas fa-user-tie"></i>
                            <span>Supervisors</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'student-management.php' ? 'active' : ''; ?>" href="student-management.php">
                            <i class="fas fa-user-graduate"></i>
                            <span>Students</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'department-reports.php' ? 'active' : ''; ?>" href="department-reports.php">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports</span>
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
