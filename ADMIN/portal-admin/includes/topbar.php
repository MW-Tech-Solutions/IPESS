<?php
require_once __DIR__ . '/db.php';

$currentUser = [
    'name' => 'Portal Admin',
    'email' => 'portaladmin@jostum.edu',
];

try {
    $sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
    if ($sessionUserId > 0 && isset($pdo)) {
        $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1");
        $stmt->execute([$sessionUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!empty($row['full_name'])) {
            $currentUser['name'] = $row['full_name'];
        }
        if (!empty($row['email'])) {
            $currentUser['email'] = $row['email'];
        }
    }
} catch (Throwable $e) {
}

$avatarInitial = strtoupper(substr($currentUser['email'] ?: $currentUser['name'], 0, 1));
?>
<div class="content-area">
    <header class="topbar">
        <div class="topbar-left">
            <button class="icon-btn topbar-menu" id="mobile-sidebar-toggle" type="button" aria-label="Open navigation">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <div class="topbar-title"><?php echo htmlspecialchars($pageTitle ?? 'Portal Admin'); ?></div>
                <?php if (!empty($pageSubtitle)) : ?>
                    <div class="topbar-subtitle"><?php echo htmlspecialchars($pageSubtitle); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="topbar-actions">
            <div class="profile-chip">
                <span style="width:26px;height:26px;border-radius:50%;background:#f6f2e7;color:#0b5b3f;display:inline-flex;align-items:center;justify-content:center;font-weight:700;">
                    <?php echo htmlspecialchars($avatarInitial); ?>
                </span>
                <span><?php echo htmlspecialchars($currentUser['email']); ?></span>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="../logout.php">
                <i class="fas fa-sign-out-alt me-1"></i> Logout
            </a>
        </div>
    </header>
    <main class="content-body">
