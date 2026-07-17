<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$sidebarDisplayName = 'ICT Admin';
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
            <span class="brand-sub">ICT Admin Panel</span>
        </div>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-label">Core</div>
        <ul class="sidebar-nav">
            <li>
                <a class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/ict-admin/dashboard.php'); ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Command Center</span>
                </a>
            </li>
        </ul>
    </div>
    
    <?php if (is_module_accessible('admissions')): ?>
    <div class="sidebar-section">
        <div class="sidebar-label">Admissions</div>
        <ul class="sidebar-nav">
            <?php if (has_permission('verify_applicants')): ?>
            <li>
                <a class="<?php echo $currentPage === 'document-verification.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/admin/document-verification.php'); ?>">
                    <i class="fas fa-check-circle"></i>
                    <span>Document Verification</span>
                </a>
            </li>
            <?php endif; ?>

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

            <?php if (has_permission('ict_processing') || has_permission('generate_matric_number') || has_permission('admission_letter') || has_permission('acceptance_letter')): ?>
            <li>
                <a class="<?php echo $currentPage === 'activate-admissions.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/activate-admissions.php'); ?>">
                    <i class="fas fa-toggle-on"></i>
                    <span>Activate Admissions</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (has_permission('reports') || has_permission('view_audit_logs')): ?>
    <div class="sidebar-section">
        <div class="sidebar-label">Intelligence</div>
        <ul class="sidebar-nav">
            <?php if (has_permission('reports')): ?>
            <li>
                <a class="<?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/reports.php'); ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if (has_permission('view_audit_logs')): ?>
            <li>
                <a class="<?php echo $currentPage === 'audit-logs.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/audit-logs.php'); ?>">
                    <i class="fas fa-shield-alt"></i>
                    <span>Audit Intelligence</span>
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
