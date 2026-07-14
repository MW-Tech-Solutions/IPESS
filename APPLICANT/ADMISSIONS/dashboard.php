<?php
ob_start();
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../includes/permissions.php';
enforce_session_timeout(300, 'APPLICANT/ADMISSIONS/login.php');

require_role(['STUDENT'], 'APPLICANT/ADMISSIONS/login.php');

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

if (!isset($_SESSION['user_id'])) {
    redirect_to('APPLICANT/ADMISSIONS/login.php');
    exit();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'STUDENT') {
    redirect_to('APPLICANT/ADMISSIONS/login.php');
    exit();
}
$timeoutSeconds = 300;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeoutSeconds) {
    session_unset();
    session_destroy();
    redirect_to('APPLICANT/ADMISSIONS/login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT 
        a.application_id, 
        a.status, 
        a.current_status,
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

    if (!can_edit_application($app_data['current_status'] ?? 'DRAFT')) {
        $current_step = 10;
    } else {
        $current_step = isset($_GET['step']) ? (int)$_GET['step'] : (int)$app_data['db_step'];
    }
} else {
    $current_step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
}

if (!isset($_SESSION['form_data']) && isset($_SESSION['application_id'])) {
    $app_id = $_SESSION['application_id'];
    $_SESSION['form_data'] = [];

    // Load Step 1: Personal Info
    $stmt = $pdo->prepare("SELECT * FROM personal_details WHERE application_id = ?");
    $stmt->execute([$app_id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($p) {
        $_SESSION['form_data']['step_1'] = [
            'surname' => $p['surname'],
            'firstName' => $p['first_name'],
            'otherName' => $p['other_name'],
            'dob' => $p['dob'],
            'sex' => $p['sex'],
            'nationality' => $p['nationality'],
            'state' => $p['state_origin'],
            'lga' => $p['lga'],
            'phone' => $p['phone'],
            'address' => $p['address'],
            'email' => $_SESSION['user_email'] ?? ''
        ];
    }

    // Load Step 2: Programme Info
    $stmt = $pdo->prepare("
        SELECT 
            pc.*,
            f.faculty_name,
            d.dept_name,
            dt.degree_name,
            c.course_title,
            sm.mode_name
        FROM programme_choices pc
        LEFT JOIN faculties f ON pc.faculty = f.faculty_id
        LEFT JOIN departments d ON pc.department = d.dept_id
        LEFT JOIN degree_types dt ON pc.degree_type = dt.degree_id
        LEFT JOIN courses c ON pc.course = c.course_id
        LEFT JOIN study_modes sm ON pc.mode_of_study = sm.mode_id
        WHERE pc.application_id = ?
    ");
    $stmt->execute([$app_id]);
    $pc = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($pc) {
        $course_title = $pc['course_title'] ?? '';
        $course_clean = preg_replace('/^(PGD|MSC)\s+/i', '', $course_title);

        $_SESSION['form_data']['step_2'] = [
            'faculty' => $pc['faculty_name'] ?? '',
            'department' => $pc['dept_name'] ?? '',
            'degree_type' => ($pc['degree_name'] == 'Msc') ? 'MSc' : ($pc['degree_name'] ?? ''),
            'course' => $course_clean,
            'mode' => $pc['mode_name'] ?? ''
        ];
    }

    // Load Step 3: Academic History & O-Levels
    $stmt = $pdo->prepare("SELECT * FROM higher_education WHERE application_id = ?");
    $stmt->execute([$app_id]);
    $edu = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($edu) {
        $_SESSION['form_data']['step_3'] = [
            'highest_qualification' => $edu['highest_qualification'],
            'course_study' => $edu['course_study'],
            'institution' => $edu['institution'],
            'grad_year' => $edu['grad_year'],
            'cgpa' => $edu['cgpa'],
            'mode_study' => $edu['mode_study']
        ];
    }

    // Load O-Level Sittings
    $stmt = $pdo->prepare("SELECT * FROM olevel_exams WHERE application_id = ? ORDER BY sitting_number ASC");
    $stmt->execute([$app_id]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($exams as $exam) {
        $sitNum = $exam['sitting_number'];
        $prefix = "ssce" . $sitNum;
        
        $_SESSION['form_data']['step_3'][$prefix . '_school'] = $exam['school_name'];
        $_SESSION['form_data']['step_3'][$prefix . '_exam_number'] = $exam['exam_number'];
        $_SESSION['form_data']['step_3'][$prefix . '_year'] = $exam['exam_year'];
        
        $type = $exam['exam_type'];
        if (in_array($type, ['WAEC', 'NECO', 'NABTEB', 'GCE'])) {
            $_SESSION['form_data']['step_3'][$prefix . '_type'] = $type;
            $_SESSION['form_data']['step_3'][$prefix . '_type_other'] = '';
        } else {
            $_SESSION['form_data']['step_3'][$prefix . '_type'] = 'Others';
            $_SESSION['form_data']['step_3'][$prefix . '_type_other'] = $type;
        }

        // Fetch subjects & grades
        $stmt_res = $pdo->prepare("SELECT * FROM olevel_results WHERE exam_id = ?");
        $stmt_res->execute([$exam['id']]);
        $res = $stmt_res->fetchAll(PDO::FETCH_ASSOC);
        
        $_SESSION['form_data']['step_3'][$prefix . '_subjects'] = [];
        $_SESSION['form_data']['step_3'][$prefix . '_grades'] = [];
        foreach ($res as $r) {
            $_SESSION['form_data']['step_3'][$prefix . '_subjects'][] = $r['subject_name'];
            $_SESSION['form_data']['step_3'][$prefix . '_grades'][] = $r['grade'];
        }
    }

    // Load Step 4: NYSC Info
    $stmt = $pdo->prepare("SELECT * FROM nysc_details WHERE application_id = ?");
    $stmt->execute([$app_id]);
    $nysc = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($nysc) {
        $_SESSION['form_data']['step_4'] = [
            'nysc_status' => $nysc['nysc_status'],
            'nysc_number' => $nysc['certificate_number'],
            'nysc_year' => $nysc['completion_year']
        ];
    }

    // Load Step 5: Work Experience
    $stmt = $pdo->prepare("SELECT * FROM work_experience WHERE application_id = ?");
    $stmt->execute([$app_id]);
    $w = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($w) {
        $_SESSION['form_data']['step_5'] = [
            'emp_status' => $w['employment_status'],
            'employer' => $w['employer'],
            'job_title' => $w['job_title'],
            'years_experience' => $w['years_experience']
        ];
    }

    // Load Step 6: Research Details
    $stmt = $pdo->prepare("SELECT * FROM research_details WHERE application_id = ?");
    $stmt->execute([$app_id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($r) {
        $_SESSION['form_data']['step_6'] = [
            'proposed_research_area' => $r['research_area'],
            'reason_for_choosing_programme' => $r['reason_for_choosing'],
            'statement_of_purpose' => $r['statement_of_purpose'],
            'career_objectives' => $r['career_objectives']
        ];
    }

    // Load Step 7: Referees
    $stmt = $pdo->prepare("SELECT * FROM referees WHERE application_id = ?");
    $stmt->execute([$app_id]);
    $refs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($refs) {
        $_SESSION['form_data']['step_7'] = [
            'ref_name' => [],
            'ref_title' => [],
            'ref_org' => [],
            'ref_email' => [],
            'ref_phone' => []
        ];
        foreach ($refs as $ref) {
            $_SESSION['form_data']['step_7']['ref_name'][] = $ref['full_name'];
            $_SESSION['form_data']['step_7']['ref_title'][] = $ref['title'];
            $_SESSION['form_data']['step_7']['ref_org'][] = $ref['organization'];
            $_SESSION['form_data']['step_7']['ref_email'][] = $ref['email'];
            $_SESSION['form_data']['step_7']['ref_phone'][] = $ref['phone'];
        }
    }

    // Load Step 8: Documents
    $stmt = $pdo->prepare("SELECT * FROM documents WHERE application_id = ?");
    $stmt->execute([$app_id]);
    $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($docs) {
        $_SESSION['form_data']['step_8'] = [];
        foreach ($docs as $doc) {
            $_SESSION['form_data']['step_8'][$doc['document_type'] . '_file'] = $doc['file_path'];
        }
    }
}

function can_access_academics_portal(?array $app): bool {
    if (!$app) {
        return false;
    }
    $status = strtolower((string) ($app['status'] ?? ''));
    $current = strtolower((string) ($app['current_status'] ?? ''));
    return $status === 'admitted' || in_array($current, ['admission_approved', 'admission_admitted'], true);
}

 $canAccessAcademics = can_access_academics_portal($app_data ?: null);

// Check if admissions module is currently active
$admissions_closed = false;
try {
    $modStmt = $pdo->prepare("SELECT is_active FROM system_modules WHERE module_key = 'admissions'");
    $modStmt->execute();
    $modVal = $modStmt->fetchColumn();
    $admissions_closed = ($modVal !== false && (int)$modVal === 0);
} catch (Throwable $e) {
    $admissions_closed = false;
}

// Hide portal switcher toggler when admissions is closed (admitted students who go to academics
// and come back won't see a broken Admissions link)
$showPortalSwitcher = $canAccessAcademics && !$admissions_closed;

function resolve_passport_path(?string $path): string {
    if (!$path) {
        return app_url('asset/homepage/new_jostum_logo.png');
    }

    $path = trim((string) $path);
    if ($path === '') {
        return app_url('asset/homepage/new_jostum_logo.png');
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    if (preg_match('#^(localhost|127\.0\.0\.1)(:\d+)?/#i', $path)) {
        return app_url($path);
    }

    $normalized = ltrim(str_replace('\\', '/', $path), '/');
    return app_url($normalized);
}

$passportSrc = resolve_passport_path($_SESSION['passport_path'] ?? '');

if ($current_step > 10) $current_step = 10;
$notifications = [];
$unread_count = 0;

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
    } catch (Throwable $e) {
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
    <link rel="icon" type="image/png" href="<?= htmlspecialchars(app_url('asset/homepage/ipess_logo.png'), ENT_QUOTES, 'UTF-8'); ?>">
    <title>Applicant Dashboard - IPESS FUAM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root { --primary-blue: #d11b27; --bg-gray: #f8f9fa; }
        .btn-primary { background-color: #d11b27 !important; border-color: #d11b27 !important; }
        .btn-primary:hover { background-color: #a81520 !important; border-color: #a81520 !important; }
        .text-primary { color: #d11b27 !important; }
        .btn-outline-primary { color: #d11b27 !important; border-color: #d11b27 !important; }
        .btn-outline-primary:hover { background-color: #d11b27 !important; color: #fff !important; }
        body { background-color: var(--bg-gray); font-family: 'Inter', sans-serif; font-size: 14px; }
        
        .mobile-header { background: #fff; padding: 10px 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 1020; }
        .desktop-sidebar { min-height: 100vh; }
        .main-content { min-height: 100vh; }
        
        .profile-widget { display: flex; align-items: center; }
        .passport-photo { width: 70px; height: 70px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; background: #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .mobile-header .passport-photo { width: 45px; height: 45px;object-fit: cover;} 

        .nav-link { color: #555; border-radius: 8px; margin-bottom: 5px; padding: 12px; transition: 0.2s; }
        .nav-link.active,
        .nav-pills .nav-link.active,
        .nav-pills .show > .nav-link { background-color: #d11b27 !important; color: #fff !important; border-color: #d11b27 !important; }
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
<?php if ($showPortalSwitcher): ?>
<div class="d-lg-none px-3 pb-2">
    <div class="btn-group btn-group-sm w-100" role="group" aria-label="Portal switcher">
        <a class="btn btn-primary" href="<?php echo htmlspecialchars(app_url('APPLICANT/ADMISSIONS/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>">Admission</a>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(app_url('APPLICANT/ACADEMICS/student-portal/index.php#dashboard'), ENT_QUOTES, 'UTF-8'); ?>">Academics</a>
    </div>
</div>
<?php endif; ?>

<div class="container-fluid">
    <div class="row">
        <nav class="col-lg-3 col-xl-2 desktop-sidebar sticky-top bg-white p-3 border-end">
            <div class="d-flex align-items-center mb-4 px-3">
                <img src="<?= htmlspecialchars(app_url('asset/homepage/ipess_logo.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="IPESS Logo" style="width:42px;height:42px;object-fit:contain;border-radius:50%;margin-right:10px;">
                <span class="fs-5 fw-bold text-dark">IPESS Portal</span>
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
                <a href="logout.php" class="nav-link text-danger mt-auto">
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
                    <?php if ($showPortalSwitcher): ?>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Portal switcher">
                            <a class="btn btn-primary" href="<?php echo htmlspecialchars(app_url('APPLICANT/ADMISSIONS/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>">Admission</a>
                            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(app_url('APPLICANT/ACADEMICS/student-portal/index.php#dashboard'), ENT_QUOTES, 'UTF-8'); ?>">Academics</a>
                        </div>
                    <?php endif; ?>
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
                <form id="stepForm" action="save_progress.php" method="POST" enctype="multipart/form-data">
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
        <div class="d-flex align-items-center gap-2">
            <img src="<?= htmlspecialchars(app_url('asset/homepage/ipess_logo.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="IPESS Logo" style="width:38px;height:38px;object-fit:contain;border-radius:50%;">
            <div>
                <h5 class="offcanvas-title fw-bold mb-0" style="color:#d11b27;font-size:0.95rem;">IPESS Portal</h5>
                <small class="text-muted" style="font-size:0.72rem;">Application Steps</small>
            </div>
        </div>
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
            <a href="logout.php" class="nav-link text-danger"><i class="bi bi-power me-2"></i> Logout</a>
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

    fetch('../../helpers/mark_notifications.php', {
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

    fetch('../../helpers/mark_notifications.php', {
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

