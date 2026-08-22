<?php
/**
 * Send Reminders Page — Admin UI
 * Sends bulk email reminders to applicants who are yet to finish/submit their applications.
 * Uses batching via AJAX to avoid server execution time limits.
 */
require_once __DIR__ . '/../app/bootstrap.php';
enforce_session_timeout(900, 'login.php');

$normRole = normalize_role(current_user_role());
$hasAccess = false;
if ($normRole === 'SUPER_ADMIN' || $normRole === 'DEVELOPER') {
    $hasAccess = true;
} else {
    $currentFile = 'send-reminders.php';
    $userId = $_SESSION['user_id'] ?? $_SESSION['userid'] ?? '';
    $roleId = $_SESSION['role'] ?? $_SESSION['roleid'] ?? '';
    $userRoles = array_unique(array_filter([$roleId, $normRole, current_user_role()]));
    try {
        require_once __DIR__ . '/../app/config/database.php';
        $pdo = db();
        if ($userId) {
            $stmtStaffRole = $pdo->prepare("SELECT userRoleID FROM user_access WHERE userName = ? OR staffIDs = ? OR EmailAddress = ? LIMIT 1");
            $stmtStaffRole->execute([$userId, $userId, $userId]);
            $uRoleId = (int)$stmtStaffRole->fetchColumn();
            if ($uRoleId > 0) {
                $userRoles[] = (string)$uRoleId;
                $stmtModern = $pdo->prepare("SELECT role_key, role_name FROM roles WHERE role_id = ? LIMIT 1");
                $stmtModern->execute([$uRoleId]);
                $modRow = $stmtModern->fetch(PDO::FETCH_ASSOC);
                if ($modRow) {
                    if (!empty($modRow['role_key'])) $userRoles[] = $modRow['role_key'];
                    if (!empty($modRow['role_name'])) $userRoles[] = $modRow['role_name'];
                }
            }
        }
        $userRoles = array_values(array_unique(array_filter($userRoles)));
        if (!empty($userRoles)) {
            $placeholders = implode(',', array_fill(0, count($userRoles), '?'));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM right_page_main_menus WHERE (page_url LIKE ? OR page_url LIKE ?) AND roleID IN ($placeholders) AND page_status = '1'");
            $stmt->execute(array_merge(['%' . $currentFile, $currentFile], $userRoles));
            if ((int)$stmt->fetchColumn() > 0) {
                $hasAccess = true;
            }
        }
        if (!$hasAccess && $userId) {
            $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM pesonal_right_page_main_menus WHERE (page_url LIKE ? OR page_url LIKE ?) AND userID = ? AND page_status = '1'");
            $stmt2->execute(['%' . $currentFile, $currentFile, $userId]);
            if ((int)$stmt2->fetchColumn() > 0) {
                $hasAccess = true;
            }
        }
    } catch (Throwable $e) {}
}

if (!$hasAccess) {
    http_response_code(403);
    exit('403 Forbidden — You do not have permission to access this page.');
}

require_once 'db.php';

// --- AJAX Batch Sending Endpoint ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_batch') {
    header('Content-Type: application/json');
    $offset = (int)($_POST['offset'] ?? 0);
    $batchSize = 15;
    $closingDate = trim($_POST['closing_date'] ?? '');
    $subject = trim($_POST['subject'] ?? 'Postgraduate Application Closing Date');
    $bodyTemplate = trim($_POST['body_template'] ?? '');

    if (empty($closingDate) || empty($bodyTemplate)) {
        echo json_encode(['success' => false, 'message' => 'Closing date and email message templates are required.']);
        exit;
    }

    $formattedDate = date('d F Y', strtotime($closingDate));

    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.user_id, u.email, u.full_name 
            FROM applications a
            JOIN users u ON a.user_id = u.user_id
            WHERE a.status = 'Draft'
            ORDER BY u.user_id ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $batchSize, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($candidates)) {
            echo json_encode(['success' => true, 'sent' => 0, 'success_count' => 0, 'failed_count' => 0, 'completed' => true]);
            exit;
        }

        require_once __DIR__ . '/../app/helpers/mailer.php';

        $successCount = 0;
        $failedCount = 0;
        $logs = [];

        foreach ($candidates as $cand) {
            $name = $cand['full_name'];
            $email = $cand['email'];

            // Personalize body template
            $personalizedBody = str_replace(
                ['[Name]', '[Closing Date]'],
                [$name, $formattedDate],
                $bodyTemplate
            );

            // Wrap in styled HTML template
            $emailContent = '
            <div style="font-family: sans-serif; padding: 20px; color: #333; max-width: 600px; margin: auto; border: 1px solid #eee; border-radius: 5px;">
                <h2 style="color: #6EB533; border-bottom: 2px solid #6EB533; padding-bottom: 10px;">IPESS JOSTUM</h2>
                ' . nl2br($personalizedBody) . '
                <div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #eee; font-size: 11px; color: #777;">
                    This is an automated administrative notification. Please do not reply directly to this email.
                </div>
            </div>';

            $textAlternative = strip_tags(str_replace('<br>', "\n", $personalizedBody));

            $mailResult = portal_send_mail($email, $name, $subject, $emailContent, $textAlternative);

            if (!empty($mailResult['success'])) {
                $successCount++;
                $logs[] = ['email' => $email, 'status' => 'success', 'message' => 'Sent'];
            } else {
                $failedCount++;
                $logs[] = ['email' => $email, 'status' => 'failed', 'message' => $mailResult['message'] ?? 'Failed'];
            }
        }

        echo json_encode([
            'success' => true,
            'sent' => count($candidates),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'logs' => $logs,
            'completed' => count($candidates) < $batchSize
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Get Count of Pending (Draft) Applications
$draftCount = 0;
try {
    $draftCount = (int)$pdo->query("
        SELECT COUNT(DISTINCT u.user_id) 
        FROM applications a 
        JOIN users u ON a.user_id = u.user_id 
        WHERE a.status = 'Draft'
    ")->fetchColumn();
} catch (Throwable $e) {}

$pageTitle = 'Send Application Reminders';
$pageSubtitle = 'Notify applicants with unsubmitted applications (Draft stage) about portal closing date.';

require_once 'includes/dev_header.php';
require_once 'includes/sidebar.php';
require_once 'includes/dev_topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Send Application Reminders</h1>
        <p class="panel-muted">Trigger batch notifications to draft status applicants regarding application deadlines.</p>
    </div>
</section>

<div class="row g-4">
    <!-- Configuration Form -->
    <div class="col-lg-6">
        <section class="panel">
            <div class="panel-header border-bottom">
                <h3 class="panel-title"><i class="fas fa-paper-plane me-2 text-primary"></i>Notification Parameters</h3>
            </div>
            <div class="panel-body py-4">
                <form id="reminderForm" onsubmit="startSending(event)">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Target Recipients</label>
                        <div class="form-control-plaintext fw-bold text-dark fs-5">
                            <i class="fas fa-users text-primary me-2"></i><?= number_format($draftCount) ?> Applicants in Draft (Step 1-9)
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="closing_date" class="form-label small fw-semibold text-muted">Specific Closing Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="closing_date" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" onchange="updateMessagePreview()">
                    </div>

                    <div class="mb-3">
                        <label for="subject" class="form-label small fw-semibold text-muted">Email Subject</label>
                        <input type="text" class="form-control" id="subject" value="IPESS JOSTUM - Postgraduate Application Deadline Reminder" required>
                    </div>

                    <div class="mb-3">
                        <label for="body_template" class="form-label small fw-semibold text-muted">Email Body Template</label>
                        <textarea class="form-control" id="body_template" rows="8" required oninput="updateMessagePreview()">Dear [Name],

We noticed that you have started but not yet completed/submitted your postgraduate application on the IPESS portal.

Please be informed that the application exercise will officially close on [Closing Date].

If you wish to be considered for admission for this academic session, please ensure you log in to the portal and submit your application before the closing date.

To complete your application, log in here:
<?= app_absolute_url('APPLICANT/ADMISSIONS/login.php') ?>

Best regards,
Admissions Office,
IPESS JOSTUM.</textarea>
                        <div class="form-text small text-muted">You can use <code>[Name]</code> and <code>[Closing Date]</code> tags. They will be personalized dynamically.</div>
                    </div>

                    <div class="mt-4 pt-2">
                        <button type="submit" id="startBtn" class="btn btn-primary px-4" <?= ($draftCount === 0) ? 'disabled' : '' ?>>
                            <i class="fas fa-play me-2"></i>Start Sending Notifications
                        </button>
                        <button type="button" id="pauseBtn" class="btn btn-outline-secondary px-4 ms-2 d-none" onclick="pauseSending()">
                            <i class="fas fa-pause me-2"></i>Pause
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <!-- Progress & Live Logs -->
    <div class="col-lg-6">
        <section class="panel mb-4">
            <div class="panel-header border-bottom">
                <h3 class="panel-title"><i class="fas fa-tasks me-2 text-warning"></i>Sending Status</h3>
            </div>
            <div class="panel-body py-4">
                <div class="row text-center g-3 mb-4">
                    <div class="col-4">
                        <div class="text-muted small">Processed</div>
                        <h2 class="fw-bold mb-0 text-dark" id="processedCount">0</h2>
                    </div>
                    <div class="col-4">
                        <div class="text-success small">Successful</div>
                        <h2 class="fw-bold mb-0 text-success" id="successCount">0</h2>
                    </div>
                    <div class="col-4">
                        <div class="text-danger small">Failed</div>
                        <h2 class="fw-bold mb-0 text-danger" id="failedCount">0</h2>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-semibold text-muted" id="progressPctText">Progress: 0%</span>
                        <span class="small text-muted" id="progressRatioText">0 / <?= $draftCount ?></span>
                    </div>
                    <div class="progress" style="height: 12px; border-radius: 50px;">
                        <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" id="progressBar" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>

                <label class="form-label small fw-semibold text-muted">Live Process Logs</label>
                <div class="border rounded bg-light p-3" id="logBox" style="height: 250px; overflow-y: scroll; font-family: monospace; font-size: 0.8rem; line-height: 1.4;">
                    <span class="text-muted">Process not started. Click "Start Sending Notifications" to begin.</span>
                </div>
            </div>
        </section>
    </div>
</div>

<?php require_once 'includes/dev_footer.php'; ?>

<script>
let totalDrafts = <?= $draftCount ?>;
let offset = 0;
let processed = 0;
let success = 0;
let failed = 0;
let isRunning = false;

function updateMessagePreview() {
    // Just a placeholder in case they edit live, no-op for now
}

function startSending(e) {
    e.preventDefault();
    if (totalDrafts === 0) return;

    isRunning = true;
    document.getElementById('startBtn').disabled = true;
    document.getElementById('startBtn').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
    document.getElementById('pauseBtn').classList.remove('d-none');
    document.getElementById('closing_date').disabled = true;
    document.getElementById('subject').disabled = true;
    document.getElementById('body_template').disabled = true;

    const logBox = document.getElementById('logBox');
    if (offset === 0) {
        logBox.innerHTML = '<span class="text-primary font-weight-bold">Starting email broadcast process...</span><br>';
    } else {
        logBox.innerHTML += '<span class="text-primary font-weight-bold">Resuming email broadcast process...</span><br>';
    }

    sendBatch();
}

function pauseSending() {
    isRunning = false;
    document.getElementById('startBtn').disabled = false;
    document.getElementById('startBtn').innerHTML = '<i class="fas fa-play me-2"></i>Resume Sending';
    document.getElementById('pauseBtn').classList.add('d-none');
    
    const logBox = document.getElementById('logBox');
    logBox.innerHTML += '<span class="text-warning fw-bold">[PAUSED] Broadcast process paused by administrator.</span><br>';
    logBox.scrollTop = logBox.scrollHeight;
}

function sendBatch() {
    if (!isRunning) return;

    const closingDate = document.getElementById('closing_date').value;
    const subject = document.getElementById('subject').value;
    const bodyTemplate = document.getElementById('body_template').value;

    const fd = new FormData();
    fd.append('action', 'send_batch');
    fd.append('offset', offset);
    fd.append('closing_date', closingDate);
    fd.append('subject', subject);
    fd.append('body_template', bodyTemplate);

    fetch('send-reminders.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            const logBox = document.getElementById('logBox');
            logBox.innerHTML += `<span class="text-danger fw-bold">[ERROR] Batch failed: ${data.message}</span><br>`;
            pauseSending();
            return;
        }

        const logBox = document.getElementById('logBox');
        
        // Output batch logs
        if (data.logs && data.logs.length) {
            data.logs.forEach(l => {
                if (l.status === 'success') {
                    logBox.innerHTML += `<span class="text-success">[OK] Reminder email sent to: ${l.email}</span><br>`;
                } else {
                    logBox.innerHTML += `<span class="text-danger">[FAIL] Failed for: ${l.email} (${l.message})</span><br>`;
                }
            });
        }

        // Update counts
        processed += data.sent;
        success += data.success_count;
        failed += data.failed_count;
        offset += data.sent;

        document.getElementById('processedCount').textContent = processed;
        document.getElementById('successCount').textContent = success;
        document.getElementById('failedCount').textContent = failed;

        // Calculate progress percentage
        const pct = totalDrafts > 0 ? Math.min(100, Math.round((processed / totalDrafts) * 100)) : 100;
        document.getElementById('progressBar').style.width = pct + '%';
        document.getElementById('progressPctText').textContent = `Progress: ${pct}%`;
        document.getElementById('progressRatioText').textContent = `${processed} / ${totalDrafts}`;

        logBox.scrollTop = logBox.scrollHeight;

        if (data.completed || processed >= totalDrafts) {
            logBox.innerHTML += '<span class="text-success fw-bold">[COMPLETED] Broadcast finished successfully!</span><br>';
            isRunning = false;
            document.getElementById('startBtn').disabled = true;
            document.getElementById('startBtn').innerHTML = '<i class="fas fa-check me-2"></i>Completed';
            document.getElementById('pauseBtn').classList.add('d-none');
        } else {
            // Send next batch
            setTimeout(sendBatch, 500); // 0.5s pause to prevent SMTP rate limits
        }
    })
    .catch(err => {
        const logBox = document.getElementById('logBox');
        logBox.innerHTML += `<span class="text-danger fw-bold">[ERROR] Network request failed.</span><br>`;
        pauseSending();
    });
}
</script>
