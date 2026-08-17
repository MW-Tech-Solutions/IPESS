<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$userRole = normalize_role(current_user_role());
$currentPage = basename($_SERVER['PHP_SELF']);
$sidebarDisplayName = 'Admin Desk';
$sidebarSubName = 'Admin Panel';

$pdo = db();
$sessionUserId = (int) ($_SESSION['user_id'] ?? 0);

try {
    if ($sessionUserId > 0 && isset($pdo)) {
        $stmt = $pdo->prepare("
            SELECT u.full_name, u.email, r.role_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.role_id 
            WHERE u.user_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$sessionUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $name = trim((string) ($row['full_name'] ?? ''));
        $email = trim((string) ($row['email'] ?? ''));
        $roleName = trim((string) ($row['role_name'] ?? ''));
        
        if ($name !== '') {
            $sidebarDisplayName = $name;
        } elseif ($email !== '') {
            $sidebarDisplayName = $email;
        }
        if ($roleName !== '') {
            $sidebarSubName = $roleName;
        }
    }
} catch (Exception $e) {
}

// ------------------------------------------------------
// Dynamic Sidebar Processing (Clone to Personal Tables)
// ------------------------------------------------------
if ($sessionUserId > 0 && isset($pdo)) {
    try {
        $stmtRight = $pdo->prepare("SELECT * FROM right_page_main_menus WHERE roleID = ?");
        $stmtRight->execute([$userRole]);
        
        while ($readpages = $stmtRight->fetch(PDO::FETCH_ASSOC)) {
            $pageID = $readpages['pageID'];
            $tabID = $readpages['tabID'];
            $roleID = $readpages['roleID'];
            $keep_active = $readpages['keep_active'] ?? 'inactive';
            $page_status = $readpages['page_status'];
            $pageType = $readpages['pageType'];
            
            // Get latest URL, Name and folder dynamically from page_main_menus
            $stmtMain = $pdo->prepare("SELECT page_url, menu_name, folder FROM page_main_menus WHERE pageID = ? LIMIT 1");
            $stmtMain->execute([$pageID]);
            $mainPage = $stmtMain->fetch(PDO::FETCH_ASSOC) ?: [];
            
            $page_url = $mainPage['page_url'] ?? $readpages['page_url'] ?? '';
            $menu_name = $mainPage['menu_name'] ?? $readpages['menu_name'] ?? '';
            $folderids = $mainPage['folder'] ?? '';
            
            // Ensure personal tab exists for this user
            $checkTab = $pdo->prepare("SELECT COUNT(*) FROM personal_page_menu_tab WHERE tabID = ? AND userID = ?");
            $checkTab->execute([$tabID, $sessionUserId]);
            if ($checkTab->fetchColumn() < 1) {
                $stmtGetTab = $pdo->prepare("SELECT * FROM page_menu_tab WHERE ID = ? AND tab_status = 1");
                $stmtGetTab->execute([$tabID]);
                $tab = $stmtGetTab->fetch(PDO::FETCH_ASSOC);
                if ($tab) {
                    $stmtInsTab = $pdo->prepare("
                        INSERT INTO personal_page_menu_tab (tabID, tab_name, open_active, userID, tab_status, collapslink) 
                        VALUES (?, ?, ?, ?, ?, 'collapsed')
                    ");
                    $stmtInsTab->execute([$tabID, $tab['tab_name'], 'notopen', $sessionUserId, $tab['tab_status']]);
                }
            }
            
            // Ensure personal right page exists for this user
            $checkPerson = $pdo->prepare("SELECT COUNT(*) FROM pesonal_right_page_main_menus WHERE pageID = ? AND userID = ?");
            $checkPerson->execute([$pageID, $sessionUserId]);
            if ($checkPerson->fetchColumn() < 1) {
                $stmtInsPerson = $pdo->prepare("
                    INSERT INTO pesonal_right_page_main_menus (pageID, menu_name, roleID, page_status, pageType, tabID, page_url, keep_active, userID, folderID) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtInsPerson->execute([$pageID, $menu_name, $roleID, $page_status, $pageType, $tabID, $page_url, $keep_active, $sessionUserId, $folderids]);
            }
        }
        
        // Update active page status
        $stmtMenus = $pdo->prepare("SELECT userpageID, page_url, tabID FROM pesonal_right_page_main_menus WHERE userID = ?");
        $stmtMenus->execute([$sessionUserId]);
        $activePageId = null;
        $activeTabId = null;
        $phpSelf = $_SERVER['PHP_SELF'] ?? '';
        
        while ($menu = $stmtMenus->fetch(PDO::FETCH_ASSOC)) {
            if (strpos($phpSelf, $menu['page_url']) !== false) {
                $activePageId = $menu['userpageID'];
                $activeTabId = $menu['tabID'];
                break;
            }
        }
        
        if ($activePageId !== null) {
            $stmtAct = $pdo->prepare("UPDATE pesonal_right_page_main_menus SET keep_active = 'active' WHERE userpageID = ? AND userID = ?");
            $stmtAct->execute([$activePageId, $sessionUserId]);
            
            $stmtDeact = $pdo->prepare("UPDATE pesonal_right_page_main_menus SET keep_active = 'inactive' WHERE userpageID != ? AND userID = ?");
            $stmtDeact->execute([$activePageId, $sessionUserId]);
            
            $stmtTabAct = $pdo->prepare("UPDATE personal_page_menu_tab SET open_active = 'show', collapslink = '' WHERE tabID = ? AND userID = ?");
            $stmtTabAct->execute([$activeTabId, $sessionUserId]);
            
            $stmtTabDeact = $pdo->prepare("UPDATE personal_page_menu_tab SET open_active = 'notopen', collapslink = 'collapsed' WHERE tabID != ? AND userID = ?");
            $stmtTabDeact->execute([$activeTabId, $sessionUserId]);
        }
    } catch (Throwable $e) {
        error_log("Sidebar dynamic processing error: " . $e->getMessage());
    }
}

// Dynamically determine the dashboard URL
$dashboardUrl = 'ADMIN/general/dashboard.php';
try {
    $stmtDash = $pdo->prepare("SELECT pageName FROM dash_borad WHERE userType = ? AND PageStatus = 1 LIMIT 1");
    $stmtDash->execute([$userRole]);
    $dbDash = $stmtDash->fetchColumn();
    if ($dbDash) {
        $dashboardUrl = $dbDash;
    } else {
        $dashboardUrl = dashboard_for_role($userRole);
    }
} catch (Throwable $e) {}

// Icon Helper mapping based on Tab names
function get_tab_icon(string $tabName): string {
    $tabName = strtolower(trim($tabName));
    return match ($tabName) {
        'core' => 'fas fa-tachometer-alt',
        'reviewer work' => 'fas fa-folder-open',
        'supervision' => 'fas fa-users',
        'workflow & admissions', 'workflow' => 'fas fa-file-alt',
        'administration' => 'fas fa-users-cog',
        'ict tools' => 'fas fa-toggle-on',
        'system & intelligence', 'intelligence' => 'fas fa-chart-bar',
        'developer' => 'fas fa-code',
        default => 'fas fa-link'
    };
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">
            <img src="<?php echo app_url('ADMIN/images/ipess_logo.png'); ?>" alt="IPESS Logo" class="sidebar-brand-logo">
        </div>
        <div class="brand-text">
            <span class="brand-name">IPESS JOSTUM</span>
            <span class="brand-sub"><?php echo htmlspecialchars($sidebarSubName, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    </div>

    <!-- Static Core / Dashboard Section -->
    <div class="sidebar-section">
        <div class="sidebar-label">Core</div>
        <ul class="sidebar-nav">
            <?php
            $requestSelf = $_SERVER['PHP_SELF'] ?? '';
            $isDashActive = (strpos($requestSelf, $dashboardUrl) !== false || $currentPage === 'dashboard.php');
            ?>
            <li>
                <a class="<?php echo $isDashActive ? 'active' : ''; ?>" href="<?php echo app_url($dashboardUrl); ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Dynamic Database-driven Sidebar sections -->
    <?php
    if ($sessionUserId > 0) {
        try {
            $stmtTabs = $pdo->prepare("SELECT * FROM personal_page_menu_tab WHERE tab_status = 1 AND userID = ? ORDER BY tabID");
            $stmtTabs->execute([$sessionUserId]);
            while ($tab = $stmtTabs->fetch(PDO::FETCH_ASSOC)):
                $tabID = $tab['tabID'];
                
                // Fetch visible links inside this category
                $stmtItems = $pdo->prepare("
                    SELECT DISTINCT pageID, page_url, menu_name, keep_active 
                    FROM pesonal_right_page_main_menus 
                    WHERE tabID = ? AND userID = ? AND page_status = 1 AND pageType = 'link' 
                    ORDER BY pageID
                ");
                $stmtItems->execute([$tabID, $sessionUserId]);
                $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($items)) {
                    continue;
                }
                
                $tabIcon = get_tab_icon($tab['tab_name']);
                ?>
                <div class="sidebar-section">
                    <div class="sidebar-label"><?php echo htmlspecialchars($tab['tab_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <ul class="sidebar-nav">
                        <?php foreach ($items as $item): 
                            $isActive = ($item['keep_active'] === 'active');
                        ?>
                            <li>
                                <a class="<?php echo $isActive ? 'active' : ''; ?>" href="<?php echo app_url($item['page_url']); ?>">
                                    <i class="<?php echo $tabIcon; ?>"></i>
                                    <span><?php echo htmlspecialchars($item['menu_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php 
            endwhile;
        } catch (Throwable $e) {
            error_log("Sidebar dynamic rendering error: " . $e->getMessage());
        }
    }
    ?>

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

