        <?php
        $currentPage = basename($_SERVER['PHP_SELF']);
        $sidebarDisplayName = 'Supervisor';
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
                    <img src="<?php echo app_url('ADMIN/images/ipess_logo.png'); ?>" alt="IPESS Logo" class="sidebar-brand-logo">
                </div>
                <div class="brand-text">
                    <span class="brand-name">IPESS FUAM</span>
                    <span class="brand-sub">Supervisor Desk</span>
                </div>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-label">Supervision</div>
                <ul class="sidebar-nav">
                    <li>
                        <a class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Supervisor Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'my-students.php' ? 'active' : ''; ?>" href="my-students.php">
                            <i class="fas fa-users"></i>
                            <span>My Students</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'student-interaction.php' ? 'active' : ''; ?>" href="student-interaction.php">
                            <i class="fas fa-comments"></i>
                            <span>Student Interaction</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'chats.php' ? 'active' : ''; ?>" href="chats.php">
                            <i class="fas fa-comment-dots"></i>
                            <span>Chats</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'progress-tracking.php' ? 'active' : ''; ?>" href="progress-tracking.php">
                            <i class="fas fa-chart-line"></i>
                            <span>Progress Tracking</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'milestones.php' ? 'active' : ''; ?>" href="milestones.php">
                            <i class="fas fa-flag-checkered"></i>
                            <span>Milestones</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'supervisor-reports.php' ? 'active' : ''; ?>" href="supervisor-reports.php">
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
