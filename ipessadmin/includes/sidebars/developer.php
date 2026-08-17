<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$sidebarDisplayName = 'Developer';
try {
    require_once __DIR__ . '/db.php';
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
            <img src="<?php echo app_url('ipessadmin/images/ipess_logo.png'); ?>" alt="IPESS Logo" class="sidebar-brand-logo">
        </div>
        <div class="brand-text">
            <span class="brand-name">IPESS JOSTUM</span>
            <span class="brand-sub">Developer Suite</span>
        </div>
    </div>

    <!-- Developer Tools -->
    <div class="sidebar-section">
        <div class="sidebar-label">Developer Tools</div>
        <ul class="sidebar-nav">
            <li>
                <a class="<?php echo $currentPage === 'bind_roles_pages.php' ? 'active' : ''; ?>" href="<?php echo app_url('ipessadmin/usersetup/bind_roles_pages.php'); ?>">
                    <i class="fas fa-link"></i>
                    <span>Assign Page to Role</span>
                </a>
            </li>
            <li>
                <a class="<?php echo $currentPage === 'createpage.php' ? 'active' : ''; ?>" href="<?php echo app_url('ipessadmin/usersetup/createpage.php'); ?>">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add New Page</span>
                </a>
            </li>
            <li>
                <a class="<?php echo $currentPage === 'createdashboard.php' ? 'active' : ''; ?>" href="<?php echo app_url('ipessadmin/usersetup/createdashboard.php'); ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Add Dashboard</span>
                </a>
            </li>
            <li>
                <a class="<?php echo $currentPage === 'createtabs.php' ? 'active' : ''; ?>" href="<?php echo app_url('ipessadmin/usersetup/createtabs.php'); ?>">
                    <i class="fas fa-folder-plus"></i>
                    <span>Add New Tabs</span>
                </a>
            </li>
            <li>
                <a class="<?php echo $currentPage === 'createrole.php' ? 'active' : ''; ?>" href="<?php echo app_url('ipessadmin/usersetup/createrole.php'); ?>">
                    <i class="fas fa-user-tag"></i>
                    <span>Add Role</span>
                </a>
            </li>
            <li>
                <a class="<?php echo $currentPage === 'create_salutation.php' ? 'active' : ''; ?>" href="<?php echo app_url('ipessadmin/usersetup/create_salutation.php'); ?>">
                    <i class="fas fa-id-badge"></i>
                    <span>Add User Title</span>
                </a>
            </li>
            <li>
                <a class="<?php echo $currentPage === 'manage_access.php' ? 'active' : ''; ?>" href="<?php echo app_url('ipessadmin/usersetup/manage_access.php'); ?>">
                    <i class="fas fa-key"></i>
                    <span>Manage Pages</span>
                </a>
            </li>
            <li>
                <a class="<?php echo $currentPage === 'upload_menu_files.php' ? 'active' : ''; ?>" href="<?php echo app_url('ipessadmin/usersetup/upload_menu_files.php'); ?>">
                    <i class="fas fa-upload"></i>
                    <span>Upload Project Files</span>
                </a>
            </li>
            <li>
                <a class="<?php echo $currentPage === 'download_files.php' ? 'active' : ''; ?>" href="<?php echo app_url('ipessadmin/usersetup/download_files.php'); ?>">
                    <i class="fas fa-download"></i>
                    <span>Download Project File</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Manage Users -->
    <div class="sidebar-section">
        <div class="sidebar-label">Manage Users</div>
        <ul class="sidebar-nav">
            <li>
                <a class="<?php echo $currentPage === 'user-management.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/user-management.php'); ?>">
                    <i class="fas fa-users"></i>
                    <span>Manage Users</span>
                </a>
            </li>
            <li>
                <a class="<?php echo $currentPage === 'create_user.php' ? 'active' : ''; ?>" href="<?php echo app_url('ipessadmin/create_user.php'); ?>">
                    <i class="fas fa-user-plus"></i>
                    <span>Add New User</span>
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
        </ul>
    </div>

    <!-- System & Audit -->
    <div class="sidebar-section">
        <div class="sidebar-label">System</div>
        <ul class="sidebar-nav">
            <li>
                <a class="<?php echo $currentPage === 'audit-logs.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/audit-logs.php'); ?>">
                    <i class="fas fa-shield-alt"></i>
                    <span>Audit Logs</span>
                </a>
            </li>
            <li>
                <a class="<?php echo $currentPage === 'modules.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/modules.php'); ?>">
                    <i class="fas fa-cubes"></i>
                    <span>Module Settings</span>
                </a>
            </li>
            <li>
                <a class="<?php echo $currentPage === 'reset-authenticator.php' ? 'active' : ''; ?>" href="<?php echo app_url('ADMIN/super-admin/reset-authenticator.php'); ?>">
                    <i class="fas fa-mobile-alt"></i>
                    <span>Reset Authenticator</span>
                </a>
            </li>
            <li>
                <a href="<?php echo app_url('ipessadmin/logout.php'); ?>">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
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
