<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../ADMIN/includes/db.php';

function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$applicationId = (int) ($_SESSION['application_id'] ?? 0);

if ($userId && !$applicationId) {
    $stmt = $pdo->prepare("SELECT application_id FROM applications WHERE user_id = ? ORDER BY application_id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $applicationId = (int) $stmt->fetchColumn();
    if ($applicationId) {
        $_SESSION['application_id'] = $applicationId;
    }
}

$chapters = [];
$latestByChapter = [];
$chapterTitles = [
    1 => 'Chapter 1: Introduction',
    2 => 'Chapter 2: Literature Review',
    3 => 'Chapter 3: Methodology',
    4 => 'Chapter 4: Results & Discussion',
    5 => 'Chapter 5: Conclusion & Recommendations'
];

if ($userId && table_exists($pdo, 'chapter_submissions')) {
    $stmt = $pdo->prepare("SELECT * FROM chapter_submissions WHERE student_user_id = ? ORDER BY chapter_no ASC, version_no DESC, submitted_at DESC");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $chapterNo = (int) ($row['chapter_no'] ?? 0);
        if ($chapterNo >= 1 && $chapterNo <= 5 && !isset($latestByChapter[$chapterNo])) {
            $latestByChapter[$chapterNo] = $row;
        }
    }
}

$all_approved = true;
foreach ($chapterTitles as $i => $label) {
    $prevApproved = $i === 1 ? true : (($latestByChapter[$i - 1]['status'] ?? '') === 'Approved');
    $active = $prevApproved;
    $submission = $latestByChapter[$i] ?? null;
    $status = $submission['status'] ?? 'Not Submitted';
    $approved = $status === 'Approved';
    if (!$active) {
        $status = 'Locked';
        $approved = false;
    }
    if (!$approved) {
        $all_approved = false;
    }

    $progress = match ($status) {
        'Approved' => 100,
        'Under Review' => 70,
        'Submitted' => 60,
        'Changes Requested' => 50,
        'Locked' => 0,
        default => 0,
    };

    $chapters[] = [
        'id' => 'chapter-' . $i,
        'title' => $label,
        'status' => $status,
        'progress' => $progress,
        'approved' => $approved,
        'recommendation' => $submission['supervisor_note'] ?? ($approved ? 'Approved by supervisor.' : 'Awaiting supervisor feedback.'),
        'file_label' => $submission['file_name'] ?? 'No file uploaded yet.',
        'last_upload' => $submission['submitted_at'] ?? '',
        'file_path' => $submission['file_path'] ?? '',
    ];
}

$communication = [];
if ($userId && table_exists($pdo, 'supervisor_messages')) {
    $stmt = $pdo->prepare("SELECT subject, message, sender_role, created_at FROM supervisor_messages WHERE student_user_id = ? ORDER BY created_at ASC LIMIT 100");
    $stmt->execute([$userId]);
    $communication = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$tracking_updates = [];
if ($userId && table_exists($pdo, 'student_tracking_updates')) {
    $stmt = $pdo->prepare("SELECT title, note, status, progress, updated_at FROM student_tracking_updates WHERE student_user_id = ? ORDER BY updated_at DESC LIMIT 20");
    $stmt->execute([$userId]);
    $tracking_updates = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$overall_progress = 0;
$overall_status = 'Not Started';
$overall_label = 'No tracking updates yet.';
if (!empty($tracking_updates)) {
    $overall_progress = (int) ($tracking_updates[0]['progress'] ?? 0);
    $overall_status = $tracking_updates[0]['status'] ?? 'In Progress';
    $overall_label = $tracking_updates[0]['title'] ?? 'Latest Update';
} else {
    $approvedCount = 0;
    for ($i = 1; $i <= 5; $i++) {
        $status = $latestByChapter[$i]['status'] ?? '';
        if ($status === 'Approved') {
            $approvedCount++;
        }
    }
    if ($approvedCount > 0) {
        $overall_progress = (int) round(($approvedCount / 5) * 100);
        $overall_status = 'Chapter Progress';
        $overall_label = "Chapters approved: {$approvedCount} of 5";
    }
}

$milestones = [];
if ($userId && table_exists($pdo, 'supervisor_milestones')) {
    $stmt = $pdo->prepare("SELECT milestone_id, title, due_date, status, note FROM supervisor_milestones WHERE student_user_id = ? ORDER BY due_date ASC");
    $stmt->execute([$userId]);
    $milestones = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>

<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-2">
        <div>
            <h1 class="h2 mb-1">Supervision</h1>
            <p class="text-muted mb-0">Upload each chapter and review supervisor recommendations before progressing.</p>
        </div>
        <div class="badge badge-jostum badge-jostum-primary">Thesis Tracking</div>
    </div>

    <ul class="nav nav-tabs" id="supervisionTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="thesis-tab" data-bs-toggle="tab" data-bs-target="#thesis" type="button" role="tab" aria-controls="thesis" aria-selected="true">Thesis</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tracking-tab" data-bs-toggle="tab" data-bs-target="#tracking" type="button" role="tab" aria-controls="tracking" aria-selected="false">Project Tracking</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="communication-tab" data-bs-toggle="tab" data-bs-target="#communication" type="button" role="tab" aria-controls="communication" aria-selected="false">Communication</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="milestone-tab" data-bs-toggle="tab" data-bs-target="#milestone" type="button" role="tab" aria-controls="milestone" aria-selected="false">Milestone</button>
        </li>
    </ul>

    <div class="tab-content pt-3" id="supervisionTabContent">
        <div class="tab-pane fade show active" id="thesis" role="tabpanel" aria-labelledby="thesis-tab">
            <div class="row g-4">
                <?php foreach ($chapters as $chapter): ?>
                    <?php
                        $last_upload = $chapter['last_upload'] ?? '';
                        $file_label = $chapter['file_label'] ?? 'No file uploaded yet.';
                        $is_locked = ($chapter['status'] ?? '') === 'Locked';
                        $upload_disabled = $is_locked || !empty($chapter['approved']);
                    ?>
                    <div class="col-12 col-lg-6">
                        <div class="card card-jostum h-100">
                            <div class="card-body">
                                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                                    <div>
                                        <h2 class="h5 mb-1"><?php echo htmlspecialchars($chapter['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                                        <div class="text-muted small">Status: <?php echo htmlspecialchars($chapter['status'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="text-lg-end">
                                        <div class="text-muted small">Last Upload</div>
                                        <div class="fw-semibold"><?php echo $last_upload ? htmlspecialchars(date('Y-m-d H:i', strtotime($last_upload)), ENT_QUOTES, 'UTF-8') : 'Not submitted'; ?></div>
                                    </div>
                                </div>

                                <div class="progress progress-jostum mt-3">
                                    <div class="progress-bar" role="progressbar" style="width: <?php echo (int) $chapter['progress']; ?>%;" aria-valuenow="<?php echo (int) $chapter['progress']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="d-flex justify-content-between mt-2 text-muted small">
                                    <span>Progress</span>
                                    <span><?php echo (int) $chapter['progress']; ?>%</span>
                                </div>

                                <div class="alert alert-jostum mt-3 mb-3">
                                    <strong>Supervisor Recommendation:</strong>
                                    <?php echo htmlspecialchars($chapter['recommendation'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>

                                <div class="row g-3 align-items-center">
                                    <div class="col-lg-7">
                                        <div class="text-muted small">Current File</div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($file_label, ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="col-lg-5 text-lg-end">
                                        <?php if ($chapter['approved']): ?>
                                            <span class="badge badge-jostum">Approved - proceed to next chapter</span>
                                        <?php elseif ($is_locked): ?>
                                            <span class="badge badge-jostum badge-jostum-muted">Locked</span>
                                        <?php else: ?>
                                            <span class="badge badge-jostum badge-jostum-warning">Action Required</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <form class="chapter-upload mt-3" action="handlers/upload-supervision.php" method="post" enctype="multipart/form-data" data-current-hash="">
                                    <input type="hidden" name="chapter" value="<?php echo htmlspecialchars($chapter['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="row g-3 align-items-center">
                                        <div class="col-lg-7">
                                            <input class="form-control" type="file" name="work_file" accept=".pdf,application/pdf" <?php echo $upload_disabled ? 'disabled' : 'required'; ?>>
                                        </div>
                                        <div class="col-lg-5 text-lg-end">
                                            <button type="submit" class="btn btn-jostum chapter-submit" <?php echo $upload_disabled ? 'disabled' : 'disabled'; ?>>Submit Revision</button>
                                        </div>
                                    </div>
                                    <div class="chapter-feedback mt-2 text-muted small"></div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="col-12">
                    <div class="card card-jostum">
                        <div class="card-body">
                            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                                <div>
                                    <h2 class="h5 mb-1">Final Submission (Chapters 1 - 5)</h2>
                                    <p class="text-muted mb-0">Upload the complete work after all chapters are approved.</p>
                                </div>
                                <?php if (!$all_approved): ?>
                                    <span class="badge badge-jostum badge-jostum-warning">Complete chapter approvals first</span>
                                <?php else: ?>
                                    <span class="badge badge-jostum">Ready for final submission</span>
                                <?php endif; ?>
                            </div>

                            <form class="chapter-upload mt-3" action="handlers/upload-supervision.php" method="post" enctype="multipart/form-data" data-current-hash="">
                                <input type="hidden" name="chapter" value="final">
                                <div class="row g-3 align-items-center">
                                    <div class="col-lg-8">
                                        <input class="form-control" type="file" name="work_file" accept=".pdf,application/pdf" <?php echo $all_approved ? 'required' : 'disabled'; ?>>
                                    </div>
                                    <div class="col-lg-4 text-lg-end">
                                        <button type="submit" class="btn btn-jostum chapter-submit" disabled>Submit Complete Work</button>
                                    </div>
                                </div>
                                <div class="chapter-feedback mt-2 text-muted small"></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tracking" role="tabpanel" aria-labelledby="tracking-tab">
            <div class="row g-4">
                <div class="col-12">
                    <div class="card card-jostum">
                        <div class="card-body">
                            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                                <div>
                                    <h2 class="h5 mb-1">Overall Project Tracking</h2>
                                    <div class="text-muted small"><?php echo htmlspecialchars($overall_label, ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="text-md-end">
                                    <div class="text-muted small">Status</div>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($overall_status, ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>
                            <div class="progress progress-jostum mt-3">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo (int) $overall_progress; ?>%;" aria-valuenow="<?php echo (int) $overall_progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-2 text-muted small">
                                <span>Overall Progress</span>
                                <span><?php echo (int) $overall_progress; ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card card-jostum">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Progress Updates</h2>
                            <?php if ($tracking_updates): ?>
                                <?php foreach ($tracking_updates as $update): ?>
                                <div class="border-bottom pb-3 mb-3">
                                    <div class="d-flex justify-content-between">
                                        <strong><?php echo htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span class="text-muted small"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($update['updated_at'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <p class="mb-2 text-muted"><?php echo htmlspecialchars($update['note'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <div class="progress progress-jostum">
                                        <div class="progress-bar" role="progressbar" style="width: <?php echo (int) $update['progress']; ?>%;" aria-valuenow="<?php echo (int) $update['progress']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <div class="d-flex justify-content-between mt-2 text-muted small">
                                        <span>Status: <?php echo htmlspecialchars($update['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span><?php echo (int) $update['progress']; ?>%</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-muted">No tracking updates yet.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card card-jostum">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Log Update</h2>
                            <form class="supervision-form" action="handlers/tracking-update.php" method="post">
                                <div class="mb-3">
                                    <label class="form-label" for="tracking-title">Update Title</label>
                                    <input class="form-control" id="tracking-title" name="title" type="text" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="tracking-note">Update Note</label>
                                    <textarea class="form-control" id="tracking-note" name="note" rows="3" required></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="tracking-progress">Progress (%)</label>
                                    <input class="form-control" id="tracking-progress" name="progress" type="number" min="0" max="100" value="0" required>
                                </div>
                                <button type="submit" class="btn btn-jostum">Save Update</button>
                                <div class="supervision-feedback mt-2 text-muted small"></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="communication" role="tabpanel" aria-labelledby="communication-tab">
            <div class="card card-jostum chat-shell">
                <div class="chat-layout">
                    <aside class="chat-sidebar">
                        <h2 class="chat-sidebar-title">Chats</h2>
                        <div class="chat-contact active">
                            <div class="chat-contact-avatar">S</div>
                            <div class="chat-contact-meta">
                                <div class="chat-contact-name">Supervisor</div>
                                <div class="chat-contact-preview">
                                    <?php echo !empty($communication) ? htmlspecialchars((string) ($communication[count($communication) - 1]['subject'] ?: 'Latest update'), ENT_QUOTES, 'UTF-8') : 'No conversation yet'; ?>
                                </div>
                            </div>
                        </div>
                    </aside>
                    <section class="chat-main">
                        <header class="chat-header">
                            <div>
                                <h3 class="chat-title">Supervisor Conversation</h3>
                                <div class="chat-subtitle">Academic supervision messaging channel</div>
                            </div>
                        </header>
                        <div class="chat-thread" id="studentChatThread">
                            <?php if (!empty($communication)): ?>
                                <?php foreach ($communication as $message): ?>
                                    <?php
                                        $senderRole = strtoupper((string) ($message['sender_role'] ?? 'SUPERVISOR'));
                                        $isStudent = $senderRole === 'STUDENT';
                                        $msgFrom = $isStudent ? 'You' : 'Supervisor';
                                        $msgTime = !empty($message['created_at']) ? date('M d, Y H:i', strtotime((string) $message['created_at'])) : '';
                                    ?>
                                    <div class="chat-bubble-row <?php echo $isStudent ? 'mine' : 'theirs'; ?>">
                                        <div class="chat-bubble">
                                            <div class="chat-bubble-meta">
                                                <span><?php echo htmlspecialchars($msgFrom, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <time><?php echo htmlspecialchars($msgTime, ENT_QUOTES, 'UTF-8'); ?></time>
                                            </div>
                                            <?php if (!empty($message['subject'])): ?>
                                                <div class="chat-bubble-subject"><?php echo htmlspecialchars((string) $message['subject'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                            <div class="chat-bubble-text"><?php echo nl2br(htmlspecialchars((string) ($message['message'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="chat-empty">
                                    <div class="chat-empty-icon"><i class="bi bi-chat-left-text"></i></div>
                                    <div class="chat-empty-title">No messages yet</div>
                                    <div class="chat-empty-subtitle">Start the conversation with your supervisor.</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <form class="supervision-form supervision-chat-form" action="handlers/send-message.php" method="post">
                            <div class="chat-composer-top">
                                <input class="form-control" id="message-subject" name="subject" type="text" placeholder="Message subject (e.g. Chapter 2 update)" value="Update" required>
                            </div>
                            <div class="chat-composer-row">
                                <textarea class="form-control chat-composer-textarea" id="message-body" name="message" rows="2" placeholder="Type your message..." required></textarea>
                                <button type="submit" class="btn btn-jostum chat-send-btn">
                                    <i class="bi bi-send-fill"></i>
                                    <span>Send</span>
                                </button>
                            </div>
                            <div class="supervision-feedback mt-2 text-muted small"></div>
                        </form>
                    </section>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="milestone" role="tabpanel" aria-labelledby="milestone-tab">
            <div class="row g-4">
                <?php if ($milestones): ?>
                    <?php foreach ($milestones as $milestone): ?>
                        <?php
                            $status = $milestone['status'] ?? 'Open';
                            $badgeClass = 'badge-jostum-warning';
                            if (strtolower($status) === 'acknowledged' || strtolower($status) === 'completed') {
                                $badgeClass = 'badge-jostum';
                            } elseif (strtolower($status) === 'overdue') {
                                $badgeClass = 'badge-jostum-danger';
                            }
                            $isAck = strtolower($status) === 'acknowledged' || strtolower($status) === 'completed';
                        ?>
                        <div class="col-12 col-lg-6">
                            <div class="card card-jostum h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <h2 class="h6 mb-1"><?php echo htmlspecialchars($milestone['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                                        <span class="badge badge-jostum <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="text-muted small mb-2">Due: <?php echo htmlspecialchars($milestone['due_date'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <p class="mb-3"><?php echo htmlspecialchars($milestone['note'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <form class="supervision-form" action="handlers/acknowledge-milestone.php" method="post">
                                        <input type="hidden" name="milestone_id" value="<?php echo (int) $milestone['milestone_id']; ?>">
                                        <button type="submit" class="btn btn-jostum" <?php echo $isAck ? 'disabled' : ''; ?>>
                                            <?php echo $isAck ? 'Acknowledged' : 'Acknowledge'; ?>
                                        </button>
                                        <div class="supervision-feedback mt-2 text-muted small"></div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="card card-jostum">
                            <div class="card-body text-muted">No milestones assigned yet.</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
