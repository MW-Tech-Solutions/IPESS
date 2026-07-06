<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$sidebarDisplayName = 'Portal Admin';

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
} catch (Throwable $e) {
}
?>
<?php
$mainOpen = in_array($currentPage, ['main-page-content.php'], true);
$admissionOpen = in_array($currentPage, ['admission-landing-page.php', 'content-management.php'], true);
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">
            <img src="../images/ipess_logo.png" alt="IPESS Logo" class="sidebar-brand-logo">
        </div>
        <div class="brand-text">
            <span class="brand-name">IPESS FUAM</span>
            <span class="brand-sub">Portal Admin</span>
        </div>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-label">Portal Content</div>
        <ul class="sidebar-nav portal-admin-nav">
            <li>
                <a class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-gauge"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-group">
                <details <?php echo $mainOpen ? 'open' : ''; ?>>
                    <summary>
                        <span><i class="fas fa-house me-2"></i>Main Page</span>
                    </summary>
                    <div class="sidebar-subnav">
                        <a class="<?php echo $currentPage === 'main-page-content.php' ? 'active' : ''; ?>" href="main-page-content.php">Overview</a>
                        <a href="main-page-content.php#hero">Hero and Slides</a>
                        <a href="main-page-content.php#vc-message">Center Leader Desk</a>
                        <a href="main-page-content.php#events">Events</a>
                        <a href="main-page-content.php#footer">CTA and Footer</a>
                    </div>
                </details>
            </li>
            <li class="sidebar-group">
                <details <?php echo $admissionOpen ? 'open' : ''; ?>>
                    <summary>
                        <span><i class="fas fa-graduation-cap me-2"></i>Admission Landing Page</span>
                    </summary>
                    <div class="sidebar-subnav">
                        <a class="<?php echo $currentPage === 'admission-landing-page.php' ? 'active' : ''; ?>" href="admission-landing-page.php">Page Settings</a>
                        <a href="content-management.php#programmes-section">Programmes</a>
                        <a href="content-management.php#notices-section">Quick Notice</a>
                        <a href="content-management.php#requirements-section">Requirements</a>
                        <a href="content-management.php#dates-section">Important Dates</a>
                        <a href="content-management.php#faqs-section">FAQs</a>
                    </div>
                </details>
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
.portal-admin-nav details summary {
    list-style: none;
    cursor: pointer;
    padding: 0.75rem 1rem;
    color: inherit;
    font-weight: 600;
}
.portal-admin-nav details summary::-webkit-details-marker {
    display: none;
}
.sidebar-subnav {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0 1rem 0.85rem 1rem;
}
.sidebar-subnav a {
    padding: 0.55rem 0.75rem;
    border-radius: 10px;
    color: #d7e0db;
    text-decoration: none;
}
.sidebar-subnav a.active,
.sidebar-subnav a:hover {
    background: rgba(255,255,255,0.08);
    color: #fff;
}
</style>
