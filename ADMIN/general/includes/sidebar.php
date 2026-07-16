<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$sidebarDisplayName = 'General Admin';
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
            <img src="<?php echo app_url('ADMIN/images/ipess_logo.png'); ?>" alt="IPESS Logo" class="sidebar-brand-logo">
        </div>
        <div class="brand-text">
            <span class="brand-name">IPESS FUAM</span>
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
            <?php if (has_permission('view_applications')): ?>
            <li>
                <a class="<?php echo $currentPage === 'application-management.php' ? 'active' : ''; ?>" href="application-management.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Application Management</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if (has_permission('verify_applicants')): ?>
            <li>
                <a class="<?php echo $currentPage === 'document-verification.php' ? 'active' : ''; ?>" href="document-verification.php">
                    <i class="fas fa-check-circle"></i>
                    <span>Document Verification</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if (has_permission('department_review')): ?>
            <li>
                <a class="<?php echo $currentPage === 'academic-review.php' ? 'active' : ''; ?>" href="academic-review.php">
                    <i class="fas fa-book-open"></i>
                    <span>Academic Review</span>
                </a>
            </li>
            <?php endif; ?>
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
