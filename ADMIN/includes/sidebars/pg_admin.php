<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$sidebarDisplayName = 'PG School Admin';
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
} catch (Exception $e) {}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">
            <img src="<?php echo app_url('ADMIN/images/ipess_logo.png'); ?>" alt="IPESS Logo" class="sidebar-brand-logo">
        </div>
        <div class="brand-text">
            <span class="brand-name">IPESS FUAM</span>
            <span class="brand-sub">PG School Suite</span>
        </div>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-label">Core</div>
        <ul class="sidebar-nav">
            <li>
                <a class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/pg-admin/dashboard.php'); ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <?php if (is_module_accessible('admissions')): ?>
            <li>
                <a class="<?php echo $currentPage === 'applications.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/pg-admin/applications.php'); ?>">
                    <i class="fas fa-folder-open"></i>
                    <span>PG Review &amp; Decisions</span>
                </a>
            </li>
            <?php else: ?>
            <li>
                <div class="px-3 py-2 text-muted small">
                    <i class="fas fa-lock me-1"></i> Admissions module is currently disabled.
                </div>
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
