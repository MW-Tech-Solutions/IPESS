<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../ADMIN/includes/db.php';
require_once __DIR__ . '/../../../../includes/status_engine.php';

function format_date(?string $date, string $fallback = 'N/A'): string {
    if (!$date) {
        return $fallback;
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return $fallback;
    }
    return date('d M Y', $ts);
}

function format_currency($amount): string {
    return 'NGN ' . number_format((float) $amount);
}

$student_name = 'Postgraduate Student';
$academic_session = '2024/2025 Session';
$academic_status = 'Active';
$semester_week = null;
$semester_total_weeks = null;
$fee_total = 0;
$fee_paid = 0;
$next_fee_deadline = null;

$project = [
    'project_id' => null,
    'topic' => 'No project topic submitted yet',
    'supervisor' => 'Pending Assignment',
    'current_stage' => 'PROJECT_ACTIVE',
    'updated_at' => null,
];

$alerts = [];
$messages = [];
$activities = [];
$kpi_uploads = 0;
$kpi_reviewed = 0;
$kpi_pending = 0;
$kpi_notifications = 0;

$userId = $_SESSION['user_id'] ?? 0;
$applicationId = $_SESSION['application_id'] ?? 0;

try {
    if ($userId && !$applicationId) {
        $stmt = $pdo->prepare("SELECT application_id FROM applications WHERE user_id = ? ORDER BY application_id DESC LIMIT 1");
        $stmt->execute([$userId]);
        $applicationId = (int) $stmt->fetchColumn();
        if ($applicationId) {
            $_SESSION['application_id'] = $applicationId;
        }
    }

    if ($applicationId) {
        $selectCols = ['a.application_id'];
        if (column_exists($pdo, 'applications', 'academic_session')) {
            $selectCols[] = 'a.academic_session';
        }
        if (column_exists($pdo, 'applications', 'academic_status')) {
            $selectCols[] = 'a.academic_status';
        }
        if (column_exists($pdo, 'applications', 'semester_week')) {
            $selectCols[] = 'a.semester_week';
        }
        if (column_exists($pdo, 'applications', 'semester_total_weeks')) {
            $selectCols[] = 'a.semester_total_weeks';
        }
        if (column_exists($pdo, 'applications', 'fee_total')) {
            $selectCols[] = 'a.fee_total';
        }
        if (column_exists($pdo, 'applications', 'fee_paid')) {
            $selectCols[] = 'a.fee_paid';
        }
        if (column_exists($pdo, 'applications', 'next_fee_deadline')) {
            $selectCols[] = 'a.next_fee_deadline';
        }
        $selectCols[] = 'pd.first_name';
        $selectCols[] = 'pd.surname';

        $sql = "SELECT " . implode(', ', $selectCols) . " FROM applications a LEFT JOIN personal_details pd ON pd.application_id = a.application_id WHERE a.application_id = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$applicationId]);
        $appRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($appRow) {
            $student_name = trim(($appRow['first_name'] ?? '') . ' ' . ($appRow['surname'] ?? '')) ?: $student_name;
            if (!empty($appRow['academic_session'])) {
                $academic_session = $appRow['academic_session'];
            }
            if (!empty($appRow['academic_status'])) {
                $academic_status = $appRow['academic_status'];
            }
            $semester_week = $appRow['semester_week'] ?? $semester_week;
            $semester_total_weeks = $appRow['semester_total_weeks'] ?? $semester_total_weeks;
            $fee_total = $appRow['fee_total'] ?? $fee_total;
            $fee_paid = $appRow['fee_paid'] ?? $fee_paid;
            $next_fee_deadline = $appRow['next_fee_deadline'] ?? $next_fee_deadline;
        }
    }

    if ($applicationId && table_exists($pdo, 'projects')) {
        $projectSelect = "p.project_id, p.topic, p.current_stage, p.updated_at, p.created_at";
        $joinSupervisor = '';
        if (table_exists($pdo, 'supervisors')) {
            $projectSelect .= ", s.full_name AS supervisor_name";
            $joinSupervisor = "LEFT JOIN supervisors s ON p.supervisor_id = s.supervisor_id";
        }
        $stmt = $pdo->prepare("SELECT {$projectSelect} FROM projects p {$joinSupervisor} WHERE p.application_id = ? ORDER BY p.project_id DESC LIMIT 1");
        $stmt->execute([$applicationId]);
        $projectRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($projectRow) {
            $project['project_id'] = $projectRow['project_id'];
            if (!empty($projectRow['topic'])) {
                $project['topic'] = $projectRow['topic'];
            }
            if (!empty($projectRow['current_stage'])) {
                $project['current_stage'] = $projectRow['current_stage'];
            }
            if (!empty($projectRow['updated_at'])) {
                $project['updated_at'] = $projectRow['updated_at'];
            } elseif (!empty($projectRow['created_at'])) {
                $project['updated_at'] = $projectRow['created_at'];
            }
            if (!empty($projectRow['supervisor_name'])) {
                $project['supervisor'] = $projectRow['supervisor_name'];
            }
        }
    }

    if ($userId && table_exists($pdo, 'student_notifications')) {
        $stmt = $pdo->prepare("SELECT title, message, created_at, is_read FROM student_notifications WHERE student_user_id = ? ORDER BY created_at DESC LIMIT 3");
        $stmt->execute([$userId]);
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_notifications WHERE student_user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        $kpi_notifications = (int) $stmt->fetchColumn();
    } elseif ($userId && table_exists($pdo, 'notifications')) {
        $stmt = $pdo->prepare("SELECT title, message, type, created_at, is_read FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
        $stmt->execute([$userId]);
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        $kpi_notifications = (int) $stmt->fetchColumn();
    }

    if ($userId && table_exists($pdo, 'supervisor_messages')) {
        $stmt = $pdo->prepare("SELECT subject, message, sender_role, created_at FROM supervisor_messages WHERE student_user_id = ? ORDER BY created_at ASC LIMIT 10");
        $stmt->execute([$userId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($userId && table_exists($pdo, 'chapter_submissions')) {
        $stmt = $pdo->prepare("SELECT chapter_no, status, submitted_at FROM chapter_submissions WHERE student_user_id = ? ORDER BY submitted_at DESC LIMIT 4");
        $stmt->execute([$userId]);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($project['project_id'] && table_exists($pdo, 'proposals')) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM proposals WHERE project_id = ?");
        $stmt->execute([$project['project_id']]);
        $kpi_uploads += (int) $stmt->fetchColumn();
    }

    if ($project['project_id'] && table_exists($pdo, 'reports')) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE project_id = ?");
        $stmt->execute([$project['project_id']]);
        $kpi_uploads += (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE project_id = ? AND status IN ('Reviewed', 'Approved')");
        $stmt->execute([$project['project_id']]);
        $kpi_reviewed = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE project_id = ? AND status IN ('Submitted', 'Under Review', 'Pending')");
        $stmt->execute([$project['project_id']]);
        $kpi_pending = (int) $stmt->fetchColumn();
    }
} catch (Exception $e) {
}

$chapterApproved = 0;
$chapterUploads = 0;
$chapterPending = 0;
$chapterReviewed = 0;
$latestChapterUpdate = null;

if ($userId && table_exists($pdo, 'chapter_submissions')) {
    $stmt = $pdo->prepare("SELECT chapter_no, status, submitted_at FROM chapter_submissions WHERE student_user_id = ? ORDER BY submitted_at DESC");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $latestStatusByChapter = [];

    foreach ($rows as $row) {
        $chapterUploads++;
        $status = $row['status'] ?? '';
        $chapterNo = (int) ($row['chapter_no'] ?? 0);
        if ($latestChapterUpdate === null && !empty($row['submitted_at'])) {
            $latestChapterUpdate = $row['submitted_at'];
        }
        if (!isset($latestStatusByChapter[$chapterNo])) {
            $latestStatusByChapter[$chapterNo] = $status;
        }
    }

    for ($i = 1; $i <= 5; $i++) {
        $status = $latestStatusByChapter[$i] ?? '';
        if ($status === 'Approved') {
            $chapterApproved++;
            $chapterReviewed++;
        } elseif ($status !== '') {
            $chapterPending++;
        }
    }

    $kpi_uploads = $chapterUploads;
    $kpi_reviewed = $chapterReviewed;
    $kpi_pending = $chapterPending;
}

if ($userId && table_exists($pdo, 'supervisor_students')) {
    $stmt = $pdo->prepare("SELECT supervisor_name FROM supervisor_students WHERE student_user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $supName = $stmt->fetchColumn();
    if ($supName) {
        $project['supervisor'] = $supName;
    }
}

if ($applicationId && table_exists($pdo, 'research_details')) {
    $stmt = $pdo->prepare("SELECT research_area FROM research_details WHERE application_id = ? LIMIT 1");
    $stmt->execute([$applicationId]);
    $topic = $stmt->fetchColumn();
    if ($topic) {
        $project['topic'] = $topic;
    }
}

if ($userId && table_exists($pdo, 'student_profiles')) {
    $stmt = $pdo->prepare("
        SELECT research_topic, supervisor_name
        FROM student_profiles
        WHERE email COLLATE utf8mb4_general_ci = (
            SELECT email COLLATE utf8mb4_general_ci FROM users WHERE user_id = ? LIMIT 1
        )
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!empty($profile['research_topic'])) {
        $project['topic'] = $profile['research_topic'];
    }
    if (!empty($profile['supervisor_name'])) {
        $project['supervisor'] = $profile['supervisor_name'];
    }
}

if ($latestChapterUpdate) {
    $project['updated_at'] = $latestChapterUpdate;
}

$project_progress = $chapterApproved > 0 ? (int) round(($chapterApproved / 5) * 100) : 0;
$steps_completed = $chapterApproved;
$project_stage_label = $chapterApproved === 5 ? 'All Chapters Approved' : 'Chapter ' . ($chapterApproved + 1) . ' In Progress';
$workflow_map = workflow_status_map();

$semester_progress = 0;
if ($semester_week && $semester_total_weeks) {
    $semester_progress = (int) round(($semester_week / max(1, $semester_total_weeks)) * 100);
}

$donut_radius = 52;
$donut_circ = 2 * pi() * $donut_radius;
$project_offset = $donut_circ * (1 - ($project_progress / 100));
$semester_offset = $donut_circ * (1 - ($semester_progress / 100));

$fee_outstanding = max(0, (int) $fee_total - (int) $fee_paid);
$important_notice = null;
if ($alerts) {
    foreach ($alerts as $alert) {
        if (!empty($alert['type']) && in_array(strtolower($alert['type']), ['warning', 'danger', 'alert'], true)) {
            $important_notice = $alert['message'];
            break;
        }
    }
}
if (!$important_notice && $alerts) {
    $important_notice = $alerts[0]['message'];
}
?>

<div class="container-fluid">
    <div class="jostum-hero p-4 mb-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <h1 class="h3 mb-1">
                    Welcome, <?php echo htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8'); ?>
                </h1>
                <p class="mb-0 text-muted">Your postgraduate progress, submissions, and payments at a glance.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="badge badge-jostum badge-jostum-primary"><?php echo htmlspecialchars($academic_session, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="badge badge-jostum"><?php echo htmlspecialchars($academic_status, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="jostum-kpi">
                <div class="jostum-kpi-icon">
                    <i class="bi bi-upload"></i>
                </div>
                <div>
                    <div class="jostum-kpi-title">Uploads</div>
                    <div class="jostum-kpi-value"><?php echo (int) $kpi_uploads; ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="jostum-kpi">
                <div class="jostum-kpi-icon">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div>
                    <div class="jostum-kpi-title">Reviewed</div>
                    <div class="jostum-kpi-value"><?php echo (int) $kpi_reviewed; ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="jostum-kpi">
                <div class="jostum-kpi-icon">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <div class="jostum-kpi-title">Pending</div>
                    <div class="jostum-kpi-value"><?php echo (int) $kpi_pending; ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="jostum-kpi">
                <div class="jostum-kpi-icon">
                    <i class="bi bi-bell-fill"></i>
                </div>
                <div>
                    <div class="jostum-kpi-title">Notifications</div>
                    <div class="jostum-kpi-value"><?php echo (int) $kpi_notifications; ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card card-jostum h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h2 class="h5 mb-0">Project Topic</h2>
                        <span class="badge badge-jostum badge-jostum-primary">PG-Research</span>
                    </div>
                    <p class="text-muted mb-1">Project Title</p>
                    <h3 class="h5 mb-4"><?php echo htmlspecialchars($project['topic'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <div class="text-muted small">Supervisor</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($project['supervisor'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="col-sm-4">
                            <div class="text-muted small">Status</div>
                            <span class="badge badge-jostum"><?php echo htmlspecialchars($project_stage_label, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="col-sm-4">
                            <div class="text-muted small">Last Updated</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars(format_date($project['updated_at']), ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card card-jostum h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Progress Tracker</h2>
                    <div class="jostum-stepper mb-4">
                        <?php
                        $step_labels = [
                            'Chapter 1 Approved',
                            'Chapter 2 Approved',
                            'Chapter 3 Approved',
                            'Chapter 4 Approved',
                            'Chapter 5 Approved',
                        ];
                        foreach ($step_labels as $index => $label):
                            $step_num = $index + 1;
                            $step_class = '';
                            if ($steps_completed >= $step_num) {
                                $step_class = 'completed';
                            } elseif ($steps_completed === ($step_num - 1)) {
                                $step_class = 'active';
                            }
                        ?>
                            <div class="jostum-step <?php echo $step_class; ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="progress progress-jostum">
                        <div class="progress-bar" style="width: <?php echo (int) $project_progress; ?>%;" role="progressbar" aria-label="Project progress" aria-valuenow="<?php echo (int) $project_progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-2 text-muted small">
                        <span><?php echo (int) $steps_completed; ?> of 5 steps completed</span>
                        <span><?php echo (int) $project_progress; ?>%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-7">
            <div class="card card-jostum h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Progress Overview</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="jostum-chart">
                                <svg class="jostum-donut" viewBox="0 0 120 120" role="img" aria-label="Project progress <?php echo (int) $project_progress; ?> percent">
                                    <circle class="jostum-donut-bg" cx="60" cy="60" r="52"></circle>
                                    <circle class="jostum-donut-value" cx="60" cy="60" r="52" stroke-dasharray="<?php echo number_format($donut_circ, 1, '.', ''); ?>" stroke-dashoffset="<?php echo number_format($project_offset, 1, '.', ''); ?>"></circle>
                                    <g transform="rotate(90 60 60)">
                                        <text class="jostum-donut-text" x="60" y="66" text-anchor="middle"><?php echo (int) $project_progress; ?>%</text>
                                    </g>
                                </svg>
                                <div>
                                    <div class="jostum-chart-label">Project Progress</div>
                                    <div class="jostum-chart-value"><?php echo htmlspecialchars($project_stage_label, ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="jostum-chart">
                                <svg class="jostum-donut" viewBox="0 0 120 120" role="img" aria-label="Semester progress <?php echo (int) $semester_progress; ?> percent">
                                    <circle class="jostum-donut-bg" cx="60" cy="60" r="52"></circle>
                                    <circle class="jostum-donut-value jostum-donut-secondary" cx="60" cy="60" r="52" stroke-dasharray="<?php echo number_format($donut_circ, 1, '.', ''); ?>" stroke-dashoffset="<?php echo number_format($semester_offset, 1, '.', ''); ?>"></circle>
                                    <g transform="rotate(90 60 60)">
                                        <text class="jostum-donut-text" x="60" y="66" text-anchor="middle"><?php echo (int) $semester_progress; ?>%</text>
                                    </g>
                                </svg>
                                <div>
                                    <div class="jostum-chart-label">Semester Progress</div>
                                    <div class="jostum-chart-value">
                                        <?php if ($semester_week && $semester_total_weeks): ?>
                                            Week <?php echo (int) $semester_week; ?> of <?php echo (int) $semester_total_weeks; ?>
                                        <?php else: ?>
                                            Not available
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card card-jostum h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Notifications</h2>
                    <ul class="nav nav-tabs mb-3" id="notifyTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="alerts-tab" data-bs-toggle="tab" data-bs-target="#alerts-pane" type="button" role="tab" aria-controls="alerts-pane" aria-selected="true">Alerts</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="messages-tab" data-bs-toggle="tab" data-bs-target="#messages-pane" type="button" role="tab" aria-controls="messages-pane" aria-selected="false">Messages</button>
                        </li>
                    </ul>
                    <div class="tab-content" id="notifyTabContent">
                        <div class="tab-pane fade show active" id="alerts-pane" role="tabpanel" aria-labelledby="alerts-tab">
                            <ul class="list-group list-group-flush">
                                <?php if ($alerts): ?>
                                    <?php foreach ($alerts as $alert): ?>
                                        <li class="list-group-item">
                                            <?php echo htmlspecialchars($alert['title'] . ': ' . $alert['message'], ENT_QUOTES, 'UTF-8'); ?>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="list-group-item text-muted">No alerts yet.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="tab-pane fade" id="messages-pane" role="tabpanel" aria-labelledby="messages-tab">
                            <div class="dashboard-chat-list">
                                <?php if ($messages): ?>
                                    <?php foreach ($messages as $message): ?>
                                        <?php
                                            $msgTitle = (string) ($message['subject'] ?? 'Message');
                                            $msgBody = (string) ($message['message'] ?? '');
                                            $msgFromRole = strtoupper((string) ($message['sender_role'] ?? 'SUPERVISOR'));
                                            $isMine = $msgFromRole === 'STUDENT';
                                            $msgFrom = $isMine ? 'You' : 'Supervisor';
                                            $msgTime = !empty($message['created_at']) ? date('M d, H:i', strtotime((string) $message['created_at'])) : '';
                                        ?>
                                        <div class="chat-bubble-row <?php echo $isMine ? 'mine' : 'theirs'; ?>">
                                            <div class="chat-bubble dashboard-chat-bubble">
                                                <div class="chat-bubble-meta">
                                                    <span><?php echo htmlspecialchars($msgFrom, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <time><?php echo htmlspecialchars($msgTime, ENT_QUOTES, 'UTF-8'); ?></time>
                                                </div>
                                                <div class="chat-bubble-subject"><?php echo htmlspecialchars($msgTitle, ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div class="chat-bubble-text"><?php echo nl2br(htmlspecialchars($msgBody, ENT_QUOTES, 'UTF-8')); ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-muted small">No messages yet.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-8">
            <div class="card card-jostum">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
                        <h2 class="h5 mb-0">Fees & Payments Summary</h2>
                        <a href="#fees" class="btn btn-jostum nav-link" data-page="fees">Pay Now</a>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6 col-lg-3">
                            <div class="jostum-stat">
                                <div class="label">Total Payable</div>
                                <div class="value"><?php echo format_currency($fee_total); ?></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="jostum-stat">
                                <div class="label">Paid</div>
                                <div class="value"><?php echo format_currency($fee_paid); ?></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="jostum-stat">
                                <div class="label">Outstanding</div>
                                <div class="value"><?php echo format_currency($fee_outstanding); ?></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="jostum-stat">
                                <div class="label">Next Deadline</div>
                                <div class="value"><?php echo htmlspecialchars(format_date($next_fee_deadline), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card card-jostum alert-jostum h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <div class="fs-4 text-primary">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        </div>
                        <div>
                            <h3 class="h6 mb-2">Important Notice</h3>
                            <p class="mb-0">
                                <?php echo htmlspecialchars($important_notice ?: 'No urgent notices at the moment.', ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-12">
            <div class="card card-jostum">
                <div class="card-body">
                    <h2 class="h5 mb-3">Recent Activities</h2>
                    <div class="table-responsive">
                        <table class="table table-jostum table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Activity</th>
                                    <th>Reference</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($activities): ?>
                                    <?php foreach ($activities as $activity): ?>
                                        <?php
                                        $chapterNo = (int) ($activity['chapter_no'] ?? 0);
                                        $status_label = $activity['status'] ?? 'Submitted';
                                        $activity_note = $chapterNo ? "Chapter {$chapterNo} submission" : 'Chapter update';
                                        $reference = $chapterNo ? 'CH-' . $chapterNo : 'CH';
                                        $activityDate = $activity['submitted_at'] ?? null;
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(format_date($activityDate), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($activity_note, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($reference, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><span class="badge badge-jostum"><?php echo htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-muted">No recent activities recorded.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
