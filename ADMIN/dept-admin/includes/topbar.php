        <?php
        require_once __DIR__ . '/../../admin/includes/db.php';

        $currentUser = [
            'name' => 'Department Admin',
            'email' => 'department@jostum.edu',
            'role' => 'Department Admin',
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
                <div class="topbar-left">
                    <button class="icon-btn topbar-menu" id="mobile-sidebar-toggle" type="button" aria-label="Open navigation">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <div class="topbar-title"><?php echo htmlspecialchars($pageTitle ?? 'Department Admin'); ?></div>
                        <?php if (!empty($pageSubtitle)) : ?>
                            <div class="topbar-subtitle"><?php echo htmlspecialchars($pageSubtitle); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="topbar-actions">
                    <div class="topbar-search">
                        <i class="fas fa-search"></i>
                        <input type="text" id="admin-global-search-input" placeholder="Search applications, supervisors..." autocomplete="off">
                        <div class="topbar-search-results" id="admin-global-search-results"></div>
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
                            <li><a class="dropdown-item" href="#" onclick="openAdminProfileModal(event)"><i class="fas fa-user me-2"></i> Profile</a></li>
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

(() => {
    const input = document.getElementById('admin-global-search-input');
    const resultsBox = document.getElementById('admin-global-search-results');
    if (!input || !resultsBox) return;

    const endpoint = "<?php echo app_url('ADMIN/api/global-search.php'); ?>";
    let timer = null;
    let results = [];

    function closeResults() {
        resultsBox.style.display = 'none';
        resultsBox.innerHTML = '';
        results = [];
    }

    function renderItems(items) {
        resultsBox.innerHTML = '';
        if (!items || items.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'search-result-empty';
            empty.textContent = 'No matches found.';
            resultsBox.appendChild(empty);
            resultsBox.style.display = 'block';
            return;
        }

        items.forEach((item) => {
            const a = document.createElement('a');
            a.className = 'search-result-item';
            a.href = item.url || '#';

            const type = document.createElement('span');
            type.className = 'search-result-type';
            type.textContent = item.type || 'Result';

            const textWrap = document.createElement('span');
            textWrap.className = 'search-result-text';

            const label = document.createElement('span');
            label.className = 'search-result-label';
            label.textContent = item.label || '';

            const meta = document.createElement('span');
            meta.className = 'search-result-meta';
            meta.textContent = item.meta || '';

            textWrap.appendChild(label);
            textWrap.appendChild(meta);
            a.appendChild(type);
            a.appendChild(textWrap);
            resultsBox.appendChild(a);
        });

        resultsBox.style.display = 'block';
    }

    async function runSearch() {
        const q = (input.value || '').trim();
        if (q.length < 2) {
            closeResults();
            return;
        }

        try {
            const res = await fetch(`${endpoint}?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            if (!data || !data.success) {
                closeResults();
                return;
            }
            results = data.data || [];
            renderItems(results);
        } catch (e) {
            closeResults();
        }
    }

    input.addEventListener('input', () => {
        if (timer) clearTimeout(timer);
        timer = setTimeout(runSearch, 220);
    });

    input.addEventListener('focus', () => {
        if ((input.value || '').trim().length >= 2 && results.length > 0) {
            renderItems(results);
        }
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (results.length > 0 && results[0].url) {
                window.location.href = results[0].url;
            }
        }
        if (e.key === 'Escape') {
            closeResults();
        }
    });

    document.addEventListener('click', (e) => {
        if (!resultsBox.contains(e.target) && e.target !== input) {
            closeResults();
        }
    });
})();

function openAdminProfileModal(e) {
    if (e) e.preventDefault();
    const modalEl = document.getElementById('adminProfileModal');
    const frame = document.getElementById('adminProfileFrame');
    if (frame) {
        frame.src = "<?php echo htmlspecialchars(app_url('ADMIN/profile.php')); ?>?ts=" + Date.now();
    }
    if (modalEl && typeof bootstrap !== 'undefined') {
        new bootstrap.Modal(modalEl).show();
    }
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

<style>
.topbar-search {
    position: relative;
}

.topbar-search-results {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #e2e0d9;
    border-radius: 12px;
    box-shadow: 0 10px 22px rgba(12, 24, 18, 0.14);
    z-index: 1200;
    max-height: 360px;
    overflow-y: auto;
    display: none;
}

.search-result-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    text-decoration: none;
    color: #1f2937;
    padding: 10px 12px;
    border-bottom: 1px solid #f3f1eb;
}

.search-result-item:hover {
    background: #f8f6ef;
}

.search-result-type {
    display: inline-block;
    min-width: 84px;
    text-align: center;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.03em;
    color: #0b5b3f;
    background: rgba(11, 91, 63, 0.12);
    border-radius: 999px;
    padding: 3px 8px;
}

.search-result-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.search-result-label {
    font-size: 13px;
    font-weight: 600;
    color: #1f2937;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.search-result-meta {
    font-size: 12px;
    color: #6b7280;
}

.search-result-empty {
    padding: 11px 12px;
    color: #6b7280;
    font-size: 13px;
}
</style>

            <main class="content-body">
