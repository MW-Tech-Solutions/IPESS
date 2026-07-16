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
<?php
if (basename($_SERVER['PHP_SELF']) === 'dashboard.php') {
    // Determine the current user's role to identify "extra" non-native permissions
    $userRole = normalize_role(current_user_role());
    
    // We only show extra widgets for non-super admins
    if (!in_array($userRole, ['SUPER_ADMIN', 'ICT_ADMIN'], true)) {
        $extraVerifyDocs = false;
        $extraDeptReview = false;
        $extraFacultyReview = false;
        $extraPgReview = false;
        $extraIctProcessing = false;
        $extraSupervisor = false;
        
        // Define native permissions per role to avoid duplicate cards on the dashboard
        if ($userRole === 'DEPARTMENT_ADMIN' || $userRole === 'HOD') {
            $extraVerifyDocs = has_permission('verify_applicants');
            $extraFacultyReview = has_permission('faculty_review');
            $extraPgReview = has_permission('pg_review') || has_permission('review_applications') || has_permission('manage_admissions');
            $extraIctProcessing = has_permission('ict_processing');
        } elseif ($userRole === 'PG_SCHOOL_OFFICER') {
            $extraVerifyDocs = has_permission('verify_applicants');
            $extraDeptReview = has_permission('department_review');
            $extraFacultyReview = has_permission('faculty_review');
            $extraIctProcessing = has_permission('ict_processing');
            $extraSupervisor = has_permission('assign_supervisor') || has_permission('supervisor_management');
        } elseif ($userRole === 'ICTO') {
            $extraDeptReview = has_permission('department_review');
            $extraFacultyReview = has_permission('faculty_review');
            $extraPgReview = has_permission('pg_review') || has_permission('review_applications') || has_permission('manage_admissions');
            $extraIctProcessing = has_permission('ict_processing');
            $extraSupervisor = has_permission('assign_supervisor') || has_permission('supervisor_management');
        } elseif ($userRole === 'ICT_STAFF') {
            $extraVerifyDocs = has_permission('verify_applicants');
            $extraDeptReview = has_permission('department_review');
            $extraFacultyReview = has_permission('faculty_review');
            $extraPgReview = has_permission('pg_review') || has_permission('review_applications') || has_permission('manage_admissions');
            $extraSupervisor = has_permission('assign_supervisor') || has_permission('supervisor_management');
        } elseif ($userRole === 'FACULTY_OFFICER') {
            $extraVerifyDocs = has_permission('verify_applicants');
            $extraDeptReview = has_permission('department_review');
            $extraPgReview = has_permission('pg_review') || has_permission('review_applications') || has_permission('manage_admissions');
            $extraIctProcessing = has_permission('ict_processing');
            $extraSupervisor = has_permission('assign_supervisor') || has_permission('supervisor_management');
        } elseif ($userRole === 'REVIEWER') {
            $extraVerifyDocs = has_permission('verify_applicants');
            $extraDeptReview = has_permission('department_review');
            $extraFacultyReview = has_permission('faculty_review');
            $extraIctProcessing = has_permission('ict_processing');
            $extraSupervisor = has_permission('assign_supervisor') || has_permission('supervisor_management');
        }
        
        $hasExtraDuties = $extraVerifyDocs || $extraDeptReview || $extraFacultyReview || $extraPgReview || $extraIctProcessing || $extraSupervisor;
        
        if ($hasExtraDuties):
            // Include database connection if not set
            if (!isset($pdo)) {
                try {
                    require_once __DIR__ . '/db.php';
                } catch (Exception $e) {}
            }
            
            // Query count statistics if possible
            $extraPendingDocs = 0;
            $extraPendingDept = 0;
            $extraPendingFaculty = 0;
            $extraPendingPg = 0;
            
            if (isset($pdo)) {
                try {
                    $userId = (int) ($_SESSION['user_id'] ?? 0);
                    $uDeptId = null;
                    if ($userId > 0) {
                        $s = $pdo->prepare("SELECT department_id FROM users WHERE user_id = ? LIMIT 1");
                        $s->execute([$userId]);
                        $uRow = $s->fetch(PDO::FETCH_ASSOC);
                        if ($uRow) {
                            $uDeptId = $uRow['department_id'] ? (int) $uRow['department_id'] : null;
                        }
                    }
                    
                    if ($extraVerifyDocs) {
                        $sql = "SELECT COUNT(DISTINCT a.application_id)
                                FROM applications a
                                JOIN documents d ON a.application_id = d.application_id
                                LEFT JOIN document_verification dv ON d.doc_id = dv.upload_id
                                WHERE (dv.verification_status IS NULL OR dv.verification_status = 'Pending')";
                        if ($uDeptId !== null) {
                            $sql .= " AND EXISTS (SELECT 1 FROM programme_choices pc WHERE pc.application_id = a.application_id AND (pc.department = ? OR a.department_id = ?))";
                            $s = $pdo->prepare($sql); $s->execute([$uDeptId, $uDeptId]);
                        } else {
                            $s = $pdo->query($sql);
                        }
                        $extraPendingDocs = (int) $s->fetchColumn();
                    }
                    
                    if ($extraDeptReview) {
                        $sql = "SELECT COUNT(DISTINCT a.application_id) FROM applications a
                                LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
                                WHERE a.status = 'Submitted'
                                AND (a.current_status IS NULL OR a.current_status IN ('Submitted','ASSIGNED_TO_DEPARTMENT','UNDER_DEPT_REVIEW'))";
                        if ($uDeptId !== null) {
                            $sql .= " AND (pc.department = ? OR a.department_id = ?)";
                            $s = $pdo->prepare($sql); $s->execute([$uDeptId, $uDeptId]);
                        } else {
                            $s = $pdo->query($sql);
                        }
                        $extraPendingDept = (int) $s->fetchColumn();
                    }
                    
                    if ($extraFacultyReview) {
                        $s = $pdo->query("SELECT COUNT(DISTINCT application_id) FROM applications
                                          WHERE current_status IN ('DEPT_APPROVED','UNDER_FACULTY_REVIEW')");
                        $extraPendingFaculty = (int) $s->fetchColumn();
                    }
                    
                    if ($extraPgReview) {
                        $s = $pdo->query("SELECT COUNT(DISTINCT application_id) FROM applications
                                          WHERE current_status IN ('FACULTY_APPROVED','DEPT_APPROVED','UNDER_PG_REVIEW')");
                        $extraPendingPg = (int) $s->fetchColumn();
                    }
                } catch (PDOException $e) {}
            }
            ?>
            <div class="mb-4 bg-light p-4 rounded-4 shadow-sm border-start border-4 border-primary">
                <h4 class="mb-3 text-primary fw-bold"><i class="fas fa-tasks me-2"></i>Delegated Modular Duties</h4>
                <p class="text-muted small">In addition to your role, the system administrator has delegated the following workflows to you:</p>
                <div class="stat-grid mt-3">
                    <?php if ($extraVerifyDocs): ?>
                    <div class="stat-card cursor-pointer bg-white border border-light" onclick="location.href='<?php echo app_url('ADMIN/general/document-verification.php'); ?>'">
                        <div class="stat-icon bg-info text-white"><i class="fas fa-check-circle"></i></div>
                        <div>
                            <div class="stat-title text-muted">Document Verification</div>
                            <div class="stat-value text-info" style="font-size:1.5rem"><?php echo number_format($extraPendingDocs); ?> Pending</div>
                            <div class="small text-info mt-1">Verify Uploaded Credentials <i class="fas fa-arrow-right ms-1"></i></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($extraDeptReview): ?>
                    <div class="stat-card cursor-pointer bg-white border border-light" onclick="location.href='<?php echo app_url('ADMIN/general/academic-review.php'); ?>'">
                        <div class="stat-icon bg-success text-white"><i class="fas fa-book-open"></i></div>
                        <div>
                            <div class="stat-title text-muted">Departmental Review</div>
                            <div class="stat-value text-success" style="font-size:1.5rem"><?php echo number_format($extraPendingDept); ?> Pending</div>
                            <div class="small text-success mt-1">Review &amp; Endorse Applicants <i class="fas fa-arrow-right ms-1"></i></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($extraFacultyReview): ?>
                    <div class="stat-card cursor-pointer bg-white border border-light" onclick="location.href='<?php echo app_url('ADMIN/faculty/applications.php'); ?>'">
                        <div class="stat-icon bg-warning text-white"><i class="fas fa-university"></i></div>
                        <div>
                            <div class="stat-title text-muted">Faculty Verification</div>
                            <div class="stat-value text-warning" style="font-size:1.5rem"><?php echo number_format($extraPendingFaculty); ?> Pending</div>
                            <div class="small text-warning mt-1">Faculty Endorsement Stage <i class="fas fa-arrow-right ms-1"></i></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($extraPgReview): ?>
                    <div class="stat-card cursor-pointer bg-white border border-light" onclick="location.href='<?php echo app_url('ADMIN/pg-admin/applications.php'); ?>'">
                        <div class="stat-icon bg-purple text-white" style="background:#7c3aed !important"><i class="fas fa-graduation-cap"></i></div>
                        <div>
                            <div class="stat-title text-muted">PG School Evaluation</div>
                            <div class="stat-value" style="font-size:1.5rem;color:#7c3aed"><?php echo number_format($extraPendingPg); ?> Pending</div>
                            <div class="small mt-1" style="color:#7c3aed">Issue Final Approvals <i class="fas fa-arrow-right ms-1"></i></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($extraIctProcessing): ?>
                    <div class="stat-card cursor-pointer bg-white border border-light" onclick="location.href='<?php echo app_url('ADMIN/ict-staff/admissions.php'); ?>'">
                        <div class="stat-icon bg-secondary text-white"><i class="fas fa-id-card"></i></div>
                        <div>
                            <div class="stat-title text-muted">ICT Registration</div>
                            <div class="stat-value text-secondary" style="font-size:1.5rem">Active</div>
                            <div class="small text-secondary mt-1">Admissions Processing desk <i class="fas fa-arrow-right ms-1"></i></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($extraSupervisor): ?>
                    <div class="stat-card cursor-pointer bg-white border border-light" onclick="location.href='<?php echo app_url('ADMIN/dept-admin/supervisor-management.php'); ?>'">
                        <div class="stat-icon bg-teal text-white" style="background:#0d9488 !important"><i class="fas fa-user-plus"></i></div>
                        <div>
                            <div class="stat-title text-muted">Supervisor Assignment</div>
                            <div class="stat-value text-teal" style="font-size:1.5rem;color:#0d9488">Active</div>
                            <div class="small mt-1" style="color:#0d9488">Allocate Supervisors <i class="fas fa-arrow-right ms-1"></i></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        endif;
    }
}
?>
