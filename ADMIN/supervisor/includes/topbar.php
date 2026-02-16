        <?php
        require_once __DIR__ . '/../../admin/includes/db.php';

        $currentUser = [
            'name' => 'Supervisor',
            'email' => 'supervisor@jostum.edu',
            'role' => 'Supervisor',
            'avatar' => null
        ];
        $notifications = [];
        $unreadCount = 0;

        if ($pdo) {
            try {
                $sessionUserId = $_SESSION['user_id'] ?? null;
                if ($sessionUserId) {
                    $userStmt = $pdo->prepare("
                        SELECT u.full_name, u.email, u.avatar_url, r.role_name
                        FROM users u
                        LEFT JOIN roles r ON r.role_id = u.role_id
                        WHERE u.user_id = ?
                        LIMIT 1
                    ");
                    $userStmt->execute([$sessionUserId]);
                    $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $userRow = $pdo->query("
                        SELECT u.full_name, u.email, u.avatar_url, r.role_name
                        FROM users u
                        LEFT JOIN roles r ON r.role_id = u.role_id
                        ORDER BY u.user_id ASC
                        LIMIT 1
                    ")->fetch(PDO::FETCH_ASSOC);
                }

                if (!empty($userRow)) {
                    $currentUser['name'] = $userRow['full_name'] ?: $userRow['email'];
                    $currentUser['email'] = $userRow['email'] ?: $currentUser['email'];
                    $currentUser['role'] = $userRow['role_name'] ?: 'User';
                    $currentUser['avatar'] = $userRow['avatar_url'] ?: null;
                }

                $notifications = $pdo->query("
                    SELECT notification_id, title, message, category, is_read, created_at
                    FROM admin_notifications
                    ORDER BY created_at DESC
                    LIMIT 6
                ")->fetchAll(PDO::FETCH_ASSOC);

                $unreadCount = (int) $pdo->query("
                    SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0
                ")->fetchColumn();
            } catch (PDOException $e) {
            }
        }

        $avatarInitial = strtoupper(substr($currentUser['email'] ?: $currentUser['name'], 0, 1));
        ?>

        <div class="content-area">
            <header class="topbar">
                <link rel="icon" type="image/jpeg" href="/ADMIN/images/logo.jpeg">
<div class="topbar-left">
                    <button class="icon-btn topbar-menu" id="mobile-sidebar-toggle" type="button" aria-label="Open navigation">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <div class="topbar-title"><?php echo htmlspecialchars($pageTitle ?? 'Supervisor'); ?></div>
                        <?php if (!empty($pageSubtitle)) : ?>
                            <div class="topbar-subtitle"><?php echo htmlspecialchars($pageSubtitle); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="topbar-actions">
                    <div class="topbar-search">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search students, milestones...">
                    </div>
                    <div class="dropdown">
                        <button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <?php if ($unreadCount > 0): ?>
                                <span class="icon-badge"><?php echo (int) $unreadCount; ?></span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $note): ?>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="fas fa-circle me-2 text-<?php echo $note['is_read'] ? 'secondary' : 'success'; ?>"></i>
                                            <strong><?php echo htmlspecialchars($note['title']); ?></strong>
                                            <div class="text-muted small"><?php echo htmlspecialchars($note['message']); ?></div>
                                            <div class="text-muted small"><?php echo date('M d, H:i', strtotime($note['created_at'])); ?></div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li><span class="dropdown-item text-muted">No notifications yet.</span></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="dropdown">
                        <button class="profile-chip dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if (!empty($currentUser['avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($currentUser['avatar']); ?>" alt="Avatar" style="width:26px;height:26px;border-radius:50%;">
                            <?php else: ?>
                                <span style="width:26px;height:26px;border-radius:50%;background:#f6f2e7;color:#0b5b3f;display:inline-flex;align-items:center;justify-content:center;font-weight:700;">
                                    <?php echo htmlspecialchars($avatarInitial); ?>
                                </span>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($currentUser['email'] ?: $currentUser['name']); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header"><?php echo htmlspecialchars($currentUser['role']); ?></h6></li>
                            <li><span class="dropdown-item-text small text-muted"><?php echo htmlspecialchars($currentUser['name']); ?></span></li>
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="return openAdminProfileModal(event)"><i class="fas fa-user me-2"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </header>
<script>
try {
    const email = <?php echo json_encode((string) ($currentUser['email'] ?? '')); ?>;
    if (email) {
        localStorage.setItem('admin_reauth_email', email);
    }
} catch (e) {}

function openAdminProfileModal(e) {
    if (e) e.preventDefault();
    const modalEl = document.getElementById('adminProfileModal');
    const frame = document.getElementById('adminProfileFrame');
    if (frame) {
        frame.src = "../profile.php?ts=" + Date.now();
    }
    if (modalEl && typeof bootstrap !== 'undefined') {
        new bootstrap.Modal(modalEl).show();
    }
    return false;
}

window.addEventListener('message', function (evt) {
    if (!evt || !evt.data || evt.data.type !== 'profile-updated') return;
    const modalEl = document.getElementById('adminProfileModal');
    if (!modalEl || typeof bootstrap === 'undefined') {
        window.location.reload();
        return;
    }
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
    window.location.reload();
});
</script>

<div class="modal fade" id="adminProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="adminProfileFrame" src="about:blank" style="width:100%;height:78vh;border:0;"></iframe>
            </div>
        </div>
    </div>
</div>

            <main class="content-body">
