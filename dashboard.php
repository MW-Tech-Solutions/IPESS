<?php
session_start();
require 'config/db.php';

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array('y' => 'year','m' => 'month','w' => 'week','d' => 'day','h' => 'hour','i' => 'minute','s' => 'second');
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

function format_stage_date(?string $datetime): string {
    if (!$datetime) return 'Pending';
    try {
        $dt = new DateTime($datetime);
        return $dt->format('M d, Y');
    } catch (Exception $e) {
        return 'Pending';
    }
}

if (!isset($_SESSION['user_id'])) {
    redirect_to('APPLICANT/ADMISSIONS/login.php');
    exit();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'STUDENT') {
    redirect_to('APPLICANT/ADMISSIONS/login.php');
    exit();
}
$timeoutSeconds = 900;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeoutSeconds) {
    session_unset();
    session_destroy();
    redirect_to('APPLICANT/ADMISSIONS/login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

$user_id = $_SESSION['user_id'];

$userEmail = '';
try {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $userEmail = (string) ($stmt->fetchColumn() ?: '');
    if ($userEmail === '') {
        $stmt = $pdo->prepare("SELECT email FROM applicant_accounts WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $userEmail = (string) ($stmt->fetchColumn() ?: '');
    }
} catch (Exception $e) {
    $userEmail = '';
}

if ($userEmail !== '') {
    $_SESSION['user_email'] = $userEmail;
    if (empty($_SESSION['form_data']['step_1']['email'])) {
        $_SESSION['form_data']['step_1']['email'] = $userEmail;
    }
}

$stmt = $pdo->prepare("
    SELECT 
        a.application_id, 
        a.status, 
        a.current_step as db_step, 
        d.file_path as passport
    FROM applications a
    LEFT JOIN documents d 
        ON a.application_id = d.application_id 
        AND d.document_type IN ('passport_profile','passport')
    WHERE a.user_id = ?
    ORDER BY 
        CASE WHEN d.document_type = 'passport_profile' THEN 0 ELSE 1 END,
        a.updated_at DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$app_data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($app_data) {
    $_SESSION['application_id'] = $app_data['application_id'];
    $_SESSION['passport_path'] = $app_data['passport'];

    if ($app_data['status'] === 'Submitted') {
        $current_step = 10;
    } else {
        $current_step = isset($_GET['step']) ? (int)$_GET['step'] : (int)$app_data['db_step'];
    }
} else {
    $current_step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
}

function resolve_passport_path(?string $path): string {
    if (!$path) {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $path = ltrim($path, '/');
    $local = __DIR__ . '/' . $path;
    if (file_exists($local)) {
        return $path;
    }
    $alt = __DIR__ . '/APPLICANT/ADMISSIONS/' . $path;
    if (file_exists($alt)) {
        return 'APPLICANT/ADMISSIONS/' . $path;
    }
    return $path;
}

$passportSrc = resolve_passport_path($_SESSION['passport_path'] ?? '') ?: 'assets/img/default-avatar.png';

if ($current_step > 10) $current_step = 10;
$notifications = [];
$unread_count = 0;
$stage_dates = [
    'Application Submitted' => null,
    'Documents Verified' => null,
    'Academic Review' => null,
    'Referee Reports' => null,
    'Final Decision' => null
];

if (isset($_SESSION['application_id'])) {
    try {
        $stmtStages = $pdo->prepare("
            SELECT stage, stage_updated_at
            FROM application_progress
            WHERE application_id = ?
            ORDER BY stage_updated_at ASC
        ");
        $stmtStages->execute([$_SESSION['application_id']]);
        foreach ($stmtStages->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($stage_dates[$row['stage']])) {
                $stage_dates[$row['stage']] = $row['stage_updated_at'];
            }
        }
        // Fallback: map notification titles to stages if any stage is still empty
        if (in_array(null, $stage_dates, true)) {
            $stmtNotifStages = $pdo->prepare("
                SELECT notification_title, created_at
                FROM applicant_notifications
                WHERE application_id = ?
                ORDER BY created_at ASC
            ");
            $stmtNotifStages->execute([$_SESSION['application_id']]);
            $map = [
                'Submitted' => 'Application Submitted',
                'Application Accepted' => 'Application Submitted',
                'Documents Verified' => 'Documents Verified',
                'Academic Review' => 'Academic Review',
                'Referee Verified' => 'Referee Reports',
                'Referee Submitted' => 'Referee Reports',
                'Admission Approved' => 'Final Decision',
                'Application Rejected' => 'Final Decision',
                'Admission Rejected' => 'Final Decision'
            ];
            foreach ($stmtNotifStages->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $title = $row['notification_title'] ?? '';
                if (isset($map[$title])) {
                    $stage = $map[$title];
                    if ($stage_dates[$stage] === null) {
                        $stage_dates[$stage] = $row['created_at'];
                    }
                }
            }
        }
    } catch (Exception $e) {
    }
}

if (isset($_SESSION['application_id'])) {
    try {
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM applicant_notifications WHERE application_id = ? AND is_read = 0");
        $stmtCount->execute([$_SESSION['application_id']]);
        $unread_count = $stmtCount->fetchColumn();

        $stmtNotif = $pdo->prepare("
            SELECT * FROM applicant_notifications 
            WHERE application_id = ? 
            ORDER BY is_read ASC, created_at DESC 
            LIMIT 5
        ");
        $stmtNotif->execute([$_SESSION['application_id']]);
        $notifications = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
    }
}

$nav_steps = [
    1 => ['icon' => 'bi-person', 'title' => 'Personal Info'],
    2 => ['icon' => 'bi-mortarboard', 'title' => 'Programme'],
    3 => ['icon' => 'bi-book', 'title' => 'Academic'],
    4 => ['icon' => 'bi-flag', 'title' => 'NYSC'],
    5 => ['icon' => 'bi-briefcase', 'title' => 'Experience'],
    6 => ['icon' => 'bi-lightbulb', 'title' => 'Research'],
    7 => ['icon' => 'bi-people', 'title' => 'Referees'],
    8 => ['icon' => 'bi-upload', 'title' => 'Documents'],
    9 => ['icon' => 'bi-check-circle', 'title' => 'Submit'],
    10 => ['icon' => 'bi-bar-chart-steps', 'title' => 'Admission Status']
];

$step_file = "forms/step_{$current_step}.php";

function render_notification_list($notifications) {
    if (empty($notifications)) {
        echo '
        <li class="text-center p-4">
            <i class="bi bi-bell-slash empty-state-icon mb-2"></i>
            <p class="text-muted small mb-0">No new notifications</p>
        </li>';
        return;
    }
    
    foreach ($notifications as $note): 
        $unread_class = $note['is_read'] ? '' : 'notif-unread';
        $speech_text = htmlspecialchars(strip_tags($note['notification_message']), ENT_QUOTES);
    ?>
        <li>
            <div class="notif-item <?php echo $unread_class; ?> position-relative" 
               onclick="markNotificationRead(event, <?php echo $note['notification_id']; ?>, this)">
                <div class="d-flex align-items-start gap-2">
                    <i class="bi bi-info-circle-fill text-primary mt-1" style="font-size: 0.8rem;"></i>
                    
                    <div class="flex-grow-1">
                        <span class="notif-title"><?php echo htmlspecialchars($note['notification_title']); ?></span>
                        <span class="notif-desc"><?php echo htmlspecialchars($note['notification_message']); ?></span>
                        <span class="notif-time"><?php echo time_elapsed_string($note['created_at']); ?></span>
                    </div>

                    <button type="button" 
                            class="btn btn-sm btn-light rounded-circle border-0 text-secondary shadow-sm ms-2 p-0 d-flex align-items-center justify-content-center" 
                            style="width: 32px; height: 32px; min-width: 32px;"
                            onclick="toggleNotificationAudio(event, this)"
                            data-message="<?php echo $speech_text; ?>"
                            title="Listen to notification">
                        <i class="bi bi-play-fill fs-5"></i>
                    </button>
                </div>
            </div>
        </li>
    <?php endforeach; 
}?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="icon" type="image/jpeg" href="/ADMIN/images/logo.jpeg">
<title>PG Provisional Admission</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root { --primary-blue: #1a4388; --bg-gray: #f8f9fa; }
        body { background-color: var(--bg-gray); font-family: 'Inter', sans-serif; font-size: 14px; }
        
        .mobile-header { background: #fff; padding: 10px 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 1020; }
        .desktop-sidebar { min-height: 100vh; }
        .main-content { min-height: 100vh; }
        
        .profile-widget { display: flex; align-items: center; }
        .passport-photo { width: 70px; height: 70px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; background: #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .mobile-header .passport-photo { width: 45px; height: 45px;object-fit: cover;} 

        .nav-link { color: #555; border-radius: 8px; margin-bottom: 5px; padding: 12px; transition: 0.2s; }
        .nav-link.active { background: var(--primary-blue); color: #fff !important; }
        .nav-link.completed { color: #198754; background: #e8f5e9; }
        .locked-nav { pointer-events: none !important; opacity: 0.7; cursor: default !important; }

        .form-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .form-control, .form-select { padding: 12px; font-size: 16px; }

        .notification-dropdown { width: 320px; max-height: 400px; overflow-y: auto; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.15); border-radius: 12px; }
        .notif-item { border-bottom: 1px solid #f0f0f0; padding: 12px 16px; transition: background 0.2s; cursor: pointer; text-decoration: none; display: block; }
        .notif-item:hover { background-color: #f8f9fa; }
        .notif-item:last-child { border-bottom: none; }
        .notif-unread { background-color: #eef2ff; }
        .notif-title { font-weight: 600; font-size: 0.9rem; color: #333; display: block; margin-bottom: 2px;}
        .notif-desc { font-size: 0.8rem; color: #666; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; }
        .notif-time { font-size: 0.7rem; color: #999; margin-top: 5px; display: block; }
        .notif-badge { position: absolute; top: 0; right: 0; font-size: 0.65rem; border: 2px solid #fff; display: flex; align-items: center; justify-content: center; transform: translate(25%, -25%); width: 18px; height: 18px; }
        .empty-state-icon { font-size: 2.5rem; color: #dee2e6; }
        
        @media (max-width: 991.98px) {
            .desktop-sidebar { display: none; }
            .main-content { padding: 15px !important; }
        }
    </style>
</head>
<body>

<?php if (isset($_SESSION['msg'])): ?>
    <div id="layout-alert" class="position-fixed top-0 start-50 translate-middle-x w-100 p-3" style="z-index: 2000; max-width: 600px;">
        <div class="alert alert-<?= $_SESSION['msg']['type']; ?> alert-dismissible fade show shadow-lg border-0 overflow-hidden" role="alert">
          
            <div class="d-flex align-items-center p-1">
                <i class="bi <?= $_SESSION['msg']['type'] == 'danger' ? 'bi-exclamation-octagon-fill' : 'bi-check-circle-fill'; ?> me-3 fs-4"></i>
                <div>
                    <span><?= $_SESSION['msg']['text']; ?></span>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            
            <div class="alert-progress-bar bg-<?= $_SESSION['msg']['type']; ?>"></div>
        </div>

        <style>
            .alert-progress-bar {
                position: absolute;
                bottom: 0;
                left: 0;
                height: 4px;
                width: 100%;
                opacity: 0.4;
                animation: alert-timeout 5s linear forwards;
            }
            @keyframes alert-timeout {
                from { width: 100%; }
                to { width: 0%; }
            }
        </style>
    </div>
    <?php unset($_SESSION['msg']); ?>
<?php endif; ?>

<header class="mobile-header d-lg-none d-flex justify-content-between align-items-center">
    <button class="btn btn-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu">
        <i class="bi bi-list fs-4"></i>
    </button>
    
    <div class="d-flex align-items-center gap-3">
        <div class="btn-group btn-group-sm" role="group" aria-label="Dashboard switcher">
            <a class="btn btn-primary" href="dashboard.php" aria-current="page">Admission</a>
            <a class="btn btn-outline-secondary" href="APPLICANT/ACADEMICS/student-portal/index.php#dashboard">Academics</a>
        </div>
        <div class="dropdown">
            <button class="btn btn-link text-dark position-relative p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-bell fs-4"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light unread-badge-count">
                        <?= $unread_count > 9 ? '9+' : $unread_count; ?>
                    </span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end notification-dropdown pt-0">
                <li class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light rounded-top">
                    <span class="fw-bold small text-uppercase text-secondary">Notifications</span>
                    <a href="#" onclick="markAllRead(event)" class="text-decoration-none small">Mark all read</a>
                </li>
                <?php render_notification_list($notifications); ?>
            </ul>
        </div>

        <div class="profile-widget">
            <img src="<?php echo htmlspecialchars($passportSrc); ?>" class="passport-photo">
        </div>
    </div>
</header>

<div class="container-fluid">
    <div class="row">
        <nav class="col-lg-3 col-xl-2 desktop-sidebar sticky-top bg-white p-3 border-end">
            <div class="d-flex align-items-center mb-4 px-3">
                <i class="bi bi-mortarboard-fill fs-3 text-primary me-2"></i>
                <span class="fs-5 fw-bold text-dark">PG Portal</span>
            </div>
            <div class="nav flex-column nav-pills">
                <?php foreach ($nav_steps as $key => $val): 
                    $status_class = ($key == $current_step) ? 'active' : (($key < $current_step) ? 'completed' : 'disabled');
                    $lock_class = ($current_step == 10) ? 'locked-nav' : '';
                ?>
                    <a href="?step=<?php echo $key; ?>" class="nav-link <?php echo $status_class; ?> <?php echo $lock_class; ?>">
                        <i class="bi <?php echo ($key < $current_step) ? 'bi-check-circle-fill' : $val['icon']; ?> me-2"></i>
                        <?php echo $val['title']; ?>
                    </a>
                <?php endforeach; ?>
                <hr>
                <a href="./auth/logout.php" class="nav-link text-danger mt-auto">
                    <i class="bi bi-power me-2"></i> Logout
                </a>
            </div>
        </nav>

        <main class="col-lg-9 col-xl-10 main-content p-lg-5 p-3">
            
            <div class="d-none d-lg-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-0"><?php echo $nav_steps[$current_step]['title']; ?></h2>
                    <small class="text-muted">Step <?php echo $current_step; ?> of 10</small>
                </div>
                
                <div class="d-flex align-items-center gap-4">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Dashboard switcher">
                        <a class="btn btn-primary" href="dashboard.php" aria-current="page">Admission</a>
                        <a class="btn btn-outline-secondary" href="APPLICANT/ACADEMICS/student-portal/index.php#dashboard">Academics</a>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-light rounded-circle position-relative shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 45px; height: 45px;">
                            <i class="bi bi-bell text-secondary"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notif-badge unread-badge-count">
                                    <?= $unread_count > 9 ? '9+' : $unread_count; ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end notification-dropdown pt-0">
                            <li class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light rounded-top">
                                <span class="fw-bold small text-uppercase text-secondary">Notifications</span>
                                <a href="#" onclick="markAllRead(event)" class="text-decoration-none small">Mark all read</a>
                            </li>
                            <?php render_notification_list($notifications); ?>
                        </ul>
                    </div>

                    <div class="profile-widget">
                         
                        <img src="<?php echo htmlspecialchars($passportSrc); ?>" class="passport-photo">
                    </div>
                </div>
            </div>

            <div class="form-card">
                <form id="stepForm" action="./helpers/save_progress.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="step_id" value="<?php echo $current_step; ?>">
                    
                    <?php 
                    if(file_exists($step_file)) {
                        include $step_file; 
                    } else {
                        echo "<div class='alert alert-warning'>Section file not found: {$step_file}</div>";
                    }
                    ?>

                    <?php if ($current_step != 10): ?>
                        <div class="d-flex flex-column flex-md-row justify-content-between mt-5 gap-3">
                            <?php if($current_step > 1): ?>
                                <a href="dashboard.php?step=<?php echo $current_step - 1; ?>" class="btn btn-light border p-3 order-2 order-md-1">
                                    <i class="bi bi-chevron-left"></i> Previous
                                </a>
                            <?php else: ?>
                                <div></div>
                            <?php endif; ?>

                            <button type="submit" class="btn btn-primary p-3 px-md-5 fw-bold order-1 order-md-2 shadow-sm">
                                <?php echo ($current_step == 9) ? 'Submit Application' : 'Save & Continue'; ?> 
                                <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </main>
    </div>
</div>

<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileMenu" style="width: 280px;">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold text-primary">Application Steps</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div class="nav flex-column nav-pills">
            <?php foreach ($nav_steps as $key => $val): 
                $status_class = ($key == $current_step) ? 'active' : (($key < $current_step) ? 'completed' : 'disabled');
                $lock_class = ($current_step == 10) ? 'locked-nav' : '';
            ?>
                <a href="?step=<?php echo $key; ?>" class="nav-link py-3 <?php echo $status_class; ?> <?php echo $lock_class; ?>">
                    <i class="bi <?php echo ($key < $current_step) ? 'bi-check-circle-fill' : $val['icon']; ?> me-2"></i>
                    <?php echo $val['title']; ?>
                </a>
            <?php endforeach; ?>
            <hr>
            <a href="./auth/logout.php" class="nav-link text-danger"><i class="bi bi-power me-2"></i> Logout</a>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmSubmitModal" tabindex="-1" aria-labelledby="confirmSubmitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="confirmSubmitModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Final Confirmation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="fw-bold">Are you sure you want to submit your application?</p>
                <p class="text-muted">Once submitted, you will <strong>not</strong> be able to edit your documents or any other information in this application.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="cancelSubmitBtn">Cancel</button>
                <button type="button" class="btn btn-primary" id="finalSubmitBtn">
                    <span id="modalSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    <span id="modalBtnText">Yes, Submit Application</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const stepForm = document.getElementById('stepForm');
    const finalSubmitBtn = document.getElementById('finalSubmitBtn');
    const cancelSubmitBtn = document.getElementById('cancelSubmitBtn');
    const modalSpinner = document.getElementById('modalSpinner');
    const modalBtnText = document.getElementById('modalBtnText');
    const currentStep = <?php echo $current_step; ?>;
    const alertWrapper = document.getElementById('layout-alert');
    if (alertWrapper) {
        const alertNode = alertWrapper.querySelector('.alert');
        
        const timeout = setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alertNode);
            if (bsAlert) bsAlert.close();
        }, 5000);

        alertNode.addEventListener('closed.bs.alert', () => {
            alertWrapper.remove();
            clearTimeout(timeout);
        });
    }
    
    const submitModalEl = document.getElementById('confirmSubmitModal');
    let submitModal;
    if(submitModalEl) {
        submitModal = new bootstrap.Modal(submitModalEl);
    }

    if (stepForm && currentStep === 9 && submitModal) {
        stepForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitModal.show();
        });

        finalSubmitBtn.addEventListener('click', function() {
            finalSubmitBtn.disabled = true;
            if(cancelSubmitBtn) cancelSubmitBtn.disabled = true;

            modalSpinner.classList.remove('d-none');
            modalBtnText.innerText = " Submitting...";

            stepForm.submit();
        });
    }
});

function markNotificationRead(event, id, element) {
    event.preventDefault(); 
    
    if (!element.classList.contains('notif-unread')) return;

    console.log("Marking notification read:", id);

    fetch('./helpers/mark_notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'mark_one', id: id })
    })
    .then(res => res.text()) 
    .then(text => {
        try {
            const data = JSON.parse(text);
            if(data.success) {
                element.classList.remove('notif-unread');
                updateBadgeCount(-1);
            } else {
                console.error('Server error:', data.message);
            }
        } catch(e) {
            console.error('Invalid JSON response:', text);
        }
    })
    .catch(err => console.error('Fetch Error:', err));
}

function markAllRead(event) {
    event.preventDefault();

    fetch('./helpers/mark_notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'mark_all' })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            document.querySelectorAll('.notif-item').forEach(el => el.classList.remove('notif-unread'));
            document.querySelectorAll('.unread-badge-count').forEach(el => el.remove());
        }
    })
    .catch(err => console.error('Error:', err));
}

function updateBadgeCount(change) {
    const badges = document.querySelectorAll('.unread-badge-count');
    badges.forEach(badge => {
        let current = parseInt(badge.innerText);
        if (isNaN(current)) current = 10; 
        
        let newVal = current + change;
        
        if (newVal <= 0) {
            badge.remove();
        } else {
            badge.innerText = newVal > 9 ? '9+' : newVal;
        }
    });
}
let currentSpeechBtn = null;

function toggleNotificationAudio(event, btn) {
    event.stopPropagation();
    event.preventDefault();

    const synth = window.speechSynthesis;
    const icon = btn.querySelector('i');
    const message = btn.getAttribute('data-message');

    if (currentSpeechBtn === btn && synth.speaking) {
        synth.cancel();
        icon.classList.remove('bi-stop-fill', 'text-danger');
        icon.classList.add('bi-play-fill');
        currentSpeechBtn = null;
        return;
    }

    if (synth.speaking || currentSpeechBtn) {
        synth.cancel();
        if (currentSpeechBtn) {
            const prevIcon = currentSpeechBtn.querySelector('i');
            if (prevIcon) {
                prevIcon.classList.remove('bi-stop-fill', 'text-danger');
                prevIcon.classList.add('bi-play-fill');
            }
        }
    }

    const utterance = new SpeechSynthesisUtterance(message);
    
    utterance.rate = 1; 
    utterance.pitch = 1;

    currentSpeechBtn = btn;
    icon.classList.remove('bi-play-fill');
    icon.classList.add('bi-stop-fill', 'text-danger'); 

    utterance.onend = function() {
        icon.classList.remove('bi-stop-fill', 'text-danger');
        icon.classList.add('bi-play-fill');
        currentSpeechBtn = null;
    };

    utterance.onerror = function() {
        icon.classList.remove('bi-stop-fill', 'text-danger');
        icon.classList.add('bi-play-fill');
        currentSpeechBtn = null;
    };

    synth.speak(utterance);
}
</script>
</body>
</html>
