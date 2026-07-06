<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
if (in_array(normalize_role(current_user_role()), ['SUPER_ADMIN', 'ICT_ADMIN'], true)) {
    require_once __DIR__ . '/../../super-admin/includes/sidebar.php';
    return;
}
$currentPage = basename($_SERVER['PHP_SELF']);
$sidebarDisplayName = 'Admissions Operations';


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
            <img src="../images/logo.jpeg" alt="JOSTUM Logo" class="sidebar-brand-logo">
        </div>
        <div class="brand-text">
            <span class="brand-name">JOSTUM PG</span>
            <span class="brand-sub">Admin Desk</span>
        </div>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-label">Admissions</div>
        <ul class="sidebar-nav">
            <li>
                <a class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Admin Dashboard</span>
                </a>
            </li>
            <li>
                <a class="<?php echo $currentPage === 'application-management.php' ? 'active' : ''; ?>" href="application-management.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Application Management</span>
                </a>
            </li>
            <li>
                <a class="<?php echo $currentPage === 'document-verification.php' ? 'active' : ''; ?>" href="document-verification.php">
                    <i class="fas fa-check-circle"></i>
                    <span>Document Verification</span>
                </a>
            </li>
            <li>
                <a class="<?php echo $currentPage === 'academic-review.php' ? 'active' : ''; ?>" href="academic-review.php">
                    <i class="fas fa-book-open"></i>
                    <span>Academic Review</span>
                </a>
            </li>
            <li>
                <a class="<?php echo $currentPage === 'referees.php' ? 'active' : ''; ?>" href="referees.php">
                    <i class="fas fa-user-check"></i>
                    <span>Referees</span>
                </a>
            </li>
            <li>
                <a class="<?php echo $currentPage === 'admission-decisions.php' ? 'active' : ''; ?>" href="admission-decisions.php">
                    <i class="fas fa-gavel"></i>
                    <span>Admission Decisions</span>
                </a>
            </li>
            <li>
                <a class="<?php echo $currentPage === 'assign-supervisor.php' ? 'active' : ''; ?>" href="assign-supervisor.php">
                    <i class="fas fa-user-tag"></i>
                    <span>Assign Supervisors</span>
                </a>
            </li>
            <li>
                <a class="<?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li>
                <a href="../../modules/postutme/admin/dashboard.php">
                    <i class="fas fa-clipboard-list"></i>
                    <span>POST-UTME Screening</span>
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
