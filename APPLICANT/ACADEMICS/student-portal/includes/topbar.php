<?php
if (!isset($nav_items)) {
    include __DIR__ . '/nav-data.php';
}
require_once __DIR__ . '/../../../../ADMIN/includes/db.php';

function table_exists_local(PDO $pdo, string $table): bool
{
    try {
        $sanitizedTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $pdo->query("SELECT 1 FROM `{$sanitizedTable}` LIMIT 0");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function column_exists_local(PDO $pdo, string $table, string $column): bool
{
    try {
        $sanitizedTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $sanitizedColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $pdo->query("SELECT `{$sanitizedColumn}` FROM `{$sanitizedTable}` LIMIT 0");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function infer_student_nav_target(string $title, string $message): string
{
    $hay = strtolower($title . ' ' . $message);
    if (strpos($hay, 'fee') !== false || strpos($hay, 'payment') !== false || strpos($hay, 'receipt') !== false) {
        return 'fees';
    }
    if (strpos($hay, 'message') !== false || strpos($hay, 'supervisor') !== false || strpos($hay, 'chapter') !== false) {
        return 'supervision';
    }
    if (strpos($hay, 'progress') !== false || strpos($hay, 'milestone') !== false || strpos($hay, 'tracking') !== false) {
        return 'progress';
    }
    if (strpos($hay, 'resource') !== false || strpos($hay, 'library') !== false) {
        return 'resources';
    }
    return 'dashboard';
}

function normalize_student_media_url(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }
    if (preg_match('#^(localhost|127\.0\.0\.1)(:\d+)?/#i', $value)) {
        return app_url($value);
    }
    return app_url(ltrim(str_replace('\\', '/', $value), '/'));
}

$studentUser = [
    'name' => (string) ($_SESSION['student_name'] ?? ''),
    'email' => (string) ($_SESSION['user_email'] ?? ''),
    'avatar' => null
];
$studentNotificationsTopbar = [];
$studentInboxTopbar = [];
$sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
$latestApplicationId = 0;
$latestApplicationNumber = '';

try {
    if ($sessionUserId > 0 && isset($pdo)) {
        $stmt = $pdo->prepare("SELECT full_name, email, avatar_url FROM users WHERE user_id = ? LIMIT 1");
        $stmt->execute([$sessionUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if (!empty($row['full_name'])) {
                $studentUser['name'] = (string) $row['full_name'];
            }
            if (!empty($row['email'])) {
                $studentUser['email'] = (string) $row['email'];
            }
            if (!empty($row['avatar_url'])) {
                $studentUser['avatar'] = normalize_student_media_url((string) $row['avatar_url']);
            }
        }

        // Force-use uploaded student image when available (profile passport first, then passport).
        $appStmt = $pdo->prepare("SELECT application_id, application_number FROM applications WHERE user_id = ? ORDER BY application_id DESC LIMIT 1");
        $appStmt->execute([$sessionUserId]);
        $latestAppRow = $appStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $latestApplicationId = (int) ($latestAppRow['application_id'] ?? 0);
        $latestApplicationNumber = (string) ($latestAppRow['application_number'] ?? '');

        if (empty($studentUser['avatar']) && $latestApplicationId > 0) {
                $docStmt = $pdo->prepare("
                    SELECT file_path
                    FROM documents
                    WHERE application_id = ?
                      AND document_type IN ('passport_profile', 'passport')
                      AND file_path IS NOT NULL
                      AND file_path <> ''
                    ORDER BY (document_type = 'passport_profile') DESC, doc_id DESC
                    LIMIT 1
                ");
                $docStmt->execute([$latestApplicationId]);
                $docPath = (string) $docStmt->fetchColumn();
                if ($docPath !== '') {
                    $isImage = (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', $docPath);
                    if (!$isImage) {
                        $docPath = '';
                    }
                }
                if ($docPath !== '') {
                    $studentUser['avatar'] = normalize_student_media_url($docPath);
                }
        }

        if (table_exists_local($pdo, 'student_notifications') && column_exists_local($pdo, 'student_notifications', 'student_user_id')) {
            $stmtNotifications = $pdo->prepare("
                SELECT
                    COALESCE(title, 'Notification') AS title,
                    COALESCE(message, '') AS message,
                    created_at
                FROM student_notifications
                WHERE student_user_id = ?
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $stmtNotifications->execute([$sessionUserId]);
            $studentNotificationsTopbar = $stmtNotifications->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (empty($studentNotificationsTopbar) && $latestApplicationId > 0 && table_exists_local($pdo, 'applicant_notifications')) {
            $stmtNotifications = $pdo->prepare("
                SELECT
                    COALESCE(notification_title, 'Notification') AS title,
                    COALESCE(notification_message, '') AS message,
                    created_at
                FROM applicant_notifications
                WHERE application_id = ?
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $stmtNotifications->execute([$latestApplicationId]);
            $studentNotificationsTopbar = $stmtNotifications->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (table_exists_local($pdo, 'supervisor_messages')) {
            if (column_exists_local($pdo, 'supervisor_messages', 'student_user_id')) {
                $stmtInbox = $pdo->prepare("
                    SELECT
                        COALESCE(subject, '') AS subject,
                        COALESCE(message, '') AS message,
                        COALESCE(sender_role, 'SUPERVISOR') AS sender_role,
                        created_at
                    FROM supervisor_messages
                    WHERE student_user_id = ?
                    ORDER BY created_at DESC
                    LIMIT 20
                ");
                $stmtInbox->execute([$sessionUserId]);
                $studentInboxTopbar = $stmtInbox->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } elseif (column_exists_local($pdo, 'supervisor_messages', 'student_id')) {
                $lookupStudentId1 = (string) $sessionUserId;
                $lookupStudentId2 = (string) $latestApplicationNumber;
                if ($lookupStudentId2 !== '') {
                    $stmtInbox = $pdo->prepare("
                        SELECT
                            '' AS subject,
                            COALESCE(message, '') AS message,
                            'SUPERVISOR' AS sender_role,
                            created_at
                        FROM supervisor_messages
                        WHERE student_id IN (?, ?)
                        ORDER BY created_at DESC
                        LIMIT 20
                    ");
                    $stmtInbox->execute([$lookupStudentId1, $lookupStudentId2]);
                } else {
                    $stmtInbox = $pdo->prepare("
                        SELECT
                            '' AS subject,
                            COALESCE(message, '') AS message,
                            'SUPERVISOR' AS sender_role,
                            created_at
                        FROM supervisor_messages
                        WHERE student_id = ?
                        ORDER BY created_at DESC
                        LIMIT 20
                    ");
                    $stmtInbox->execute([$lookupStudentId1]);
                }
                $studentInboxTopbar = $stmtInbox->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }
    }
} catch (Throwable $e) {
}

$avatarInitialSource = $studentUser['name'] !== '' ? $studentUser['name'] : $studentUser['email'];
$avatarInitial = strtoupper(substr((string) $avatarInitialSource, 0, 1));
if ($avatarInitial === '') {
    $avatarInitial = 'S';
}
$studentCanAccessAcademics = !empty($_SESSION['student_can_access_academics']);
?>

<header class="topbar" id="topbar">
    <link rel="icon" type="image/jpeg" href="<?php echo htmlspecialchars(app_url('ADMIN/images/logo.jpeg'), ENT_QUOTES, 'UTF-8'); ?>">
<?php if ($studentCanAccessAcademics): ?>
    <button class="sidebar-toggler" type="button" id="sidebarToggler" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
        </button>
<?php endif; ?>

    <?php if ($studentCanAccessAcademics): ?>
        <div class="ms-2 d-none d-md-flex align-items-center">
            <div class="btn-group btn-group-sm" role="group" aria-label="Dashboard switcher">
                <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(app_url('APPLICANT/ADMISSIONS/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>">Admission</a>
                <a class="btn btn-primary" href="<?php echo htmlspecialchars(app_url('APPLICANT/ACADEMICS/student-portal/index.php#dashboard'), ENT_QUOTES, 'UTF-8'); ?>" aria-current="page">Academics</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="dropdown" data-bs-auto-close="outside">
        <a href="#" class="dropdown-toggle text-dark" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <?php if (!empty($studentUser['avatar'])): ?>
                <img src="<?php echo htmlspecialchars($studentUser['avatar'], ENT_QUOTES, 'UTF-8'); ?>" alt="Student profile image" class="student-topbar-avatar" style="width:34px;height:34px;border-radius:50%;object-fit:cover;display:block;">
            <?php else: ?>
                <span class="student-topbar-avatar student-topbar-avatar-fallback"><?php echo htmlspecialchars($avatarInitial, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
            <li>
                <div class="dropdown-item-text">
                    <div class="fw-semibold"><?php echo htmlspecialchars($studentUser['name'] !== '' ? $studentUser['name'] : 'Student', ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="small text-muted"><?php echo htmlspecialchars($studentUser['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#studentNotificationsModal"><i class="bi bi-bell me-2"></i>Notifications</a></li>
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#studentInboxModal"><i class="bi bi-inbox me-2"></i>Internal Inbox</a></li>
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#studentQuickNavModal"><i class="bi bi-compass me-2"></i>Quick Navigation</a></li>
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#studentAccountModal"><i class="bi bi-person-circle me-2"></i>Account</a></li>
        </ul>
    </div>
</header>

<div class="modal fade" id="studentProfileModal" tabindex="-1" aria-labelledby="studentProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="studentProfileModalLabel"><i class="bi bi-person-badge me-2"></i>Student Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center gap-3">
                    <?php if (!empty($studentUser['avatar'])): ?>
                        <img src="<?php echo htmlspecialchars($studentUser['avatar'], ENT_QUOTES, 'UTF-8'); ?>" alt="Student profile image" class="student-topbar-avatar" style="width:48px;height:48px;border-radius:50%;object-fit:cover;display:block;">
                    <?php else: ?>
                        <span class="student-topbar-avatar student-topbar-avatar-fallback" style="width:48px;height:48px;"><?php echo htmlspecialchars($avatarInitial, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($studentUser['name'] !== '' ? $studentUser['name'] : 'Student', ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="text-muted small"><?php echo htmlspecialchars($studentUser['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>
                <div class="mt-3 small text-muted">
                    Profile image is pulled from your uploaded passport/profile document.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="studentNotificationsModal" tabindex="-1" aria-labelledby="studentNotificationsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="studentNotificationsModalLabel"><i class="bi bi-bell me-2"></i>Notifications</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="list-group list-group-flush">
                    <?php if (!empty($studentNotificationsTopbar)): ?>
                        <?php foreach ($studentNotificationsTopbar as $notify): ?>
                            <?php
                            $notifyTitle = trim((string) ($notify['title'] ?? 'Notification'));
                            $notifyMessage = trim((string) ($notify['message'] ?? ''));
                            $notifyTarget = infer_student_nav_target($notifyTitle, $notifyMessage);
                            $notifyDate = !empty($notify['created_at']) ? date('M d, Y h:i A', strtotime((string) $notify['created_at'])) : '';
                            ?>
                            <a class="list-group-item list-group-item-action nav-link" href="#<?php echo htmlspecialchars($notifyTarget, ENT_QUOTES, 'UTF-8'); ?>" data-page="<?php echo htmlspecialchars($notifyTarget, ENT_QUOTES, 'UTF-8'); ?>" data-bs-dismiss="modal">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi bi-bell me-1 mt-1"></i>
                                    <div class="w-100">
                                        <div class="fw-semibold"><?php echo htmlspecialchars($notifyTitle !== '' ? $notifyTitle : 'Notification', ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php if ($notifyMessage !== ''): ?>
                                            <div class="small text-muted"><?php echo htmlspecialchars($notifyMessage, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php endif; ?>
                                        <?php if ($notifyDate !== ''): ?>
                                            <div class="small text-secondary mt-1"><?php echo htmlspecialchars($notifyDate, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="list-group-item text-muted">No notifications yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="studentInboxModal" tabindex="-1" aria-labelledby="studentInboxModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="studentInboxModalLabel"><i class="bi bi-inbox me-2"></i>Internal Inbox</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="list-group list-group-flush">
                    <?php if (!empty($studentInboxTopbar)): ?>
                        <?php foreach ($studentInboxTopbar as $msg): ?>
                            <?php
                            $msgSubject = trim((string) ($msg['subject'] ?? ''));
                            $msgBody = trim((string) ($msg['message'] ?? ''));
                            $msgSenderRole = strtoupper(trim((string) ($msg['sender_role'] ?? 'SUPERVISOR')));
                            $msgSenderLabel = $msgSenderRole === 'STUDENT' ? 'You' : 'Supervisor';
                            $msgDate = !empty($msg['created_at']) ? date('M d, Y h:i A', strtotime((string) $msg['created_at'])) : '';
                            ?>
                            <a class="list-group-item list-group-item-action nav-link" href="#supervision" data-page="supervision" data-bs-dismiss="modal">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi bi-chat-dots me-1 mt-1"></i>
                                    <div class="w-100">
                                        <div class="fw-semibold">
                                            <?php echo htmlspecialchars($msgSenderLabel, ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if ($msgSubject !== ''): ?>
                                                : <?php echo htmlspecialchars($msgSubject, ENT_QUOTES, 'UTF-8'); ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($msgBody !== ''): ?>
                                            <div class="small text-muted"><?php echo htmlspecialchars($msgBody, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php endif; ?>
                                        <?php if ($msgDate !== ''): ?>
                                            <div class="small text-secondary mt-1"><?php echo htmlspecialchars($msgDate, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="list-group-item text-muted">No messages yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="studentQuickNavModal" tabindex="-1" aria-labelledby="studentQuickNavModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="studentQuickNavModalLabel"><i class="bi bi-compass me-2"></i>Quick Navigation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($nav_items as $item): ?>
                        <a class="list-group-item list-group-item-action nav-link" href="#<?php echo htmlspecialchars($item['slug'], ENT_QUOTES, 'UTF-8'); ?>" data-page="<?php echo htmlspecialchars($item['slug'], ENT_QUOTES, 'UTF-8'); ?>" data-bs-dismiss="modal">
                            <i class="bi bi-chevron-right me-2"></i><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="studentAccountModal" tabindex="-1" aria-labelledby="studentAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="studentAccountModalLabel"><i class="bi bi-person-circle me-2"></i>Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary" onclick="openStudentProfileModalFromAccount()">
                        <i class="bi bi-person-vcard me-2"></i>Profile
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="openHelpModalFromAccount()">
                        <i class="bi bi-question-circle me-2"></i>Help & Support
                    </button>
                    <a class="btn btn-danger" href="<?php echo htmlspecialchars(app_url('APPLICANT/ADMISSIONS/logout.php'), ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openStudentProfileModalFromAccount() {
    const accountEl = document.getElementById('studentAccountModal');
    if (accountEl && typeof bootstrap !== 'undefined') {
        const accountModal = bootstrap.Modal.getInstance(accountEl) || new bootstrap.Modal(accountEl);
        accountModal.hide();
    }
    const profileEl = document.getElementById('studentProfileModal');
    if (profileEl && typeof bootstrap !== 'undefined') {
        new bootstrap.Modal(profileEl).show();
    }
}

function openHelpModalFromAccount() {
    const accountEl = document.getElementById('studentAccountModal');
    if (accountEl && typeof bootstrap !== 'undefined') {
        const accountModal = bootstrap.Modal.getInstance(accountEl) || new bootstrap.Modal(accountEl);
        accountModal.hide();
    }
    const helpEl = document.getElementById('helpModal');
    if (helpEl && typeof bootstrap !== 'undefined') {
        new bootstrap.Modal(helpEl).show();
    }
}
</script>
