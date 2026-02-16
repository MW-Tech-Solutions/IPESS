        <?php
        $currentPage = basename($_SERVER['PHP_SELF']);
        $sidebarDisplayName = 'Reviewer';
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
                    <span class="brand-sub">Reviewer Desk</span>
                </div>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-label">Workflow</div>
                <ul class="sidebar-nav">
                    <li>
                        <a class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Review Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'assigned-applications.php' ? 'active' : ''; ?>" href="assigned-applications.php">
                            <i class="fas fa-folder-open"></i>
                            <span>Assigned Applications</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'feedback-management.php' ? 'active' : ''; ?>" href="feedback-management.php">
                            <i class="fas fa-comments"></i>
                            <span>Feedback Management</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'review-history.php' ? 'active' : ''; ?>" href="review-history.php">
                            <i class="fas fa-history"></i>
                            <span>Review History</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo $currentPage === 'reviewer-reports.php' ? 'active' : ''; ?>" href="reviewer-reports.php">
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
